<?php
/**
 * wpForo AI Features - Admin Page
 *
 * Main administration page for managing AI features integration, subscription,
 * and usage monitoring.
 *
 * @since 3.0.0
 */

// Security: Check permissions
if ( ! wpforo_current_user_is( 'admin' ) && ! WPF()->usergroup->can( 'mai' ) ) {
	wp_die( __( 'You do not have sufficient permissions to access this page.', 'wpforo' ) );
}

// Include helper functions and tab content files FIRST (before using them)
require_once __DIR__ . '/tabs/ai-features-helpers.php';
require_once __DIR__ . '/tabs/ai-features-tab-overview.php';
require_once __DIR__ . '/tabs/ai-features-tab-rag-indexing.php';
require_once __DIR__ . '/tabs/ai-features-tab-wp-indexing.php';
require_once __DIR__ . '/tabs/ai-features-tab-ai-tasks.php';
require_once __DIR__ . '/tabs/ai-features-tab-analytics.php';
require_once __DIR__ . '/tabs/ai-features-tab-ai-logs.php';

// Determine current tab early (needed for conditional script loading)
$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview';

// Enqueue AI Features scripts and styles
wp_enqueue_style( 'wpforo-ai-features', WPFORO_URL . '/admin/assets/css/ai-features.css', [], WPFORO_VERSION );

// Chart.js is only needed on the analytics tab - don't load it elsewhere
$ai_features_deps = [ 'jquery', 'suggest' ];
if ( $current_tab === 'analytics' ) {
	wp_enqueue_script( 'wpforo-chart-js', WPFORO_URL . '/admin/assets/js/chart.min.js', [], '4.4.1', true );
	$ai_features_deps[] = 'wpforo-chart-js';
}
wp_enqueue_script( 'wpforo-ai-features', WPFORO_URL . '/admin/assets/js/ai-features.js', $ai_features_deps, WPFORO_VERSION, false );

// WordPress Indexing tab - load isolated scripts/styles
if ( $current_tab === 'wp_indexing' ) {
	wp_enqueue_style( 'wpforo-ai-wp-indexing', WPFORO_URL . '/admin/assets/css/ai-features-wp-indexing.css', [ 'wpforo-ai-features' ], WPFORO_VERSION );
	wp_enqueue_script( 'wpforo-ai-wp-indexing', WPFORO_URL . '/admin/assets/js/ai-features-wp-indexing.js', [ 'jquery' ], WPFORO_VERSION, true );
}

// Localize script with AJAX URL and nonce
wp_localize_script( 'wpforo-ai-features', 'wpforoAIAdmin', [
	'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
	'nonce'      => wp_create_nonce( 'wpforo_ai_features_nonce' ),
	'adminNonce' => wp_create_nonce( 'wpforo_admin_ajax' ), // For WordPress content indexing AJAX calls
	'debugMode'  => (bool) wpforo_setting( 'general', 'debug_mode' ),
	'strings'    => [
		'indexing'   => __( 'Indexing...', 'wpforo' ),
		'idle'       => __( 'Idle', 'wpforo' ),
		'noActivity' => __( 'No activity yet', 'wpforo' ),
	],
] );

// Localize i18n strings for AI logs (wpforoAI is used by WpForoAILogs module)
wp_localize_script( 'wpforo-ai-features', 'wpforoAI', [
	'i18n' => [
		'confirmDelete'         => __( 'Are you sure you want to delete this log?', 'wpforo' ),
		'confirmDeleteSelected' => __( 'Are you sure you want to delete the selected logs?', 'wpforo' ),
		'deleteAllLogs'         => __( 'Delete All Logs', 'wpforo' ),
		'noLogs'                => __( 'No logs found.', 'wpforo' ),
	],
] );

// Initialize AI Client
if ( ! isset( WPF()->ai_client ) ) {
	WPF()->ai_client = new \wpforo\classes\AIClient();
}

// Pricing is now static - no cache refresh needed

// Handle form submissions
$notice = wpforo_ai_handle_form_actions();

// Clear WordPress object cache for options (in case of external cache plugins)
// This ensures fresh values from DB after form submissions
wp_cache_delete( 'alloptions', 'options' );
wp_cache_delete( 'notoptions', 'options' );

// Note: Status transient is only cleared by specific actions (connect, disconnect, refresh)
// NOT on every page load - the 5-minute cache in get_tenant_status() prevents excessive API calls

// Get current connection status (fresh from database)
// Use global options (shared across all boards) to check connection
$api_key    = WPF()->ai_client->get_api_key();
$tenant_id  = WPF()->ai_client->get_tenant_id();
$is_connected = ! empty( $api_key ) && ! empty( $tenant_id );

// Check if returning from Freemius purchase (for status badge update)
$is_post_purchase = (isset( $_GET['upgraded'] ) && $_GET['upgraded'] == '1') || (isset( $_GET['credits_purchased'] ) && $_GET['credits_purchased'] == '1');
$purchased_plan = isset( $_GET['plan'] ) ? sanitize_key( $_GET['plan'] ) : '';

// Get tenant status if connected
// Force fresh fetch when returning from purchase to get updated credits
$status = null;
if ( $is_connected ) {
	$status = WPF()->ai_client->get_tenant_status( $is_post_purchase );
	if ( is_wp_error( $status ) ) {
		// Connection exists but status fetch failed - show error state
		$connection_error = $status;
		$is_connected = false;
	}
}

// Determine current state
$current_state = wpforo_ai_get_current_state( $is_connected, $status );

// If pending_approval and status is missing/error, construct from transient
if ( $current_state === 'pending_approval' && ( ! $status || is_wp_error( $status ) ) ) {
	$pending = get_transient( 'wpforo_ai_pending_approval' );
	if ( $pending ) {
		$status = [
			'subscription' => [
				'status'        => 'pending_approval',
				'plan'          => 'free_trial',
				'credits_total' => wpfval( $pending, 'credits_total' ) ?: 500,
			],
		];
	}
}

?>

<div class="wrap wpforo-ai-wrap">
	<h1 class="wpforo-ai-title">
		<svg width="50px" height="50px" viewBox="0 0 512 512" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
            <title>ai</title>
            <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                <g id="icon" fill="#000000" transform="translate(64.000000, 64.000000)">
                    <path d="M320,64 L320,320 L64,320 L64,64 L320,64 Z M171.749388,128 L146.817842,128 L99.4840387,256 L121.976629,256 L130.913039,230.977 L187.575039,230.977 L196.319607,256 L220.167172,256 L171.749388,128 Z M260.093778,128 L237.691519,128 L237.691519,256 L260.093778,256 L260.093778,128 Z M159.094727,149.47526 L181.409039,213.333 L137.135039,213.333 L159.094727,149.47526 Z M341.333333,256 L384,256 L384,298.666667 L341.333333,298.666667 L341.333333,256 Z M85.3333333,341.333333 L128,341.333333 L128,384 L85.3333333,384 L85.3333333,341.333333 Z M170.666667,341.333333 L213.333333,341.333333 L213.333333,384 L170.666667,384 L170.666667,341.333333 Z M85.3333333,0 L128,0 L128,42.6666667 L85.3333333,42.6666667 L85.3333333,0 Z M256,341.333333 L298.666667,341.333333 L298.666667,384 L256,384 L256,341.333333 Z M170.666667,0 L213.333333,0 L213.333333,42.6666667 L170.666667,42.6666667 L170.666667,0 Z M256,0 L298.666667,0 L298.666667,42.6666667 L256,42.6666667 L256,0 Z M341.333333,170.666667 L384,170.666667 L384,213.333333 L341.333333,213.333333 L341.333333,170.666667 Z M0,256 L42.6666667,256 L42.6666667,298.666667 L0,298.666667 L0,256 Z M341.333333,85.3333333 L384,85.3333333 L384,128 L341.333333,128 L341.333333,85.3333333 Z M0,170.666667 L42.6666667,170.666667 L42.6666667,213.333333 L0,213.333333 L0,170.666667 Z M0,85.3333333 L42.6666667,85.3333333 L42.6666667,128 L0,128 L0,85.3333333 Z" id="Combined-Shape">

        </path>
                </g>
            </g>
        </svg>
		<?php _e( 'wpForo AI Features', 'wpforo' ); ?>
	</h1>

	<?php
	// Display notices
	if ( $notice ) {
		wpforo_ai_display_notice( $notice );
	}
	?>

	<!-- Tab Navigation -->
	<?php
	// $current_tab is set at top of file (for conditional script loading)
	$tabs = array(
		'overview'      => __( 'Overview', 'wpforo' ),
	);

	// Only show AI feature tabs when subscription is active or trial
	// For expired, inactive, pending_approval, not_connected, or error states - only show Overview
	if ( in_array( $current_state, [ 'free_trial', 'paid_plan' ], true ) ) {
		$tabs['rag_indexing'] = __( 'Forum Indexing', 'wpforo' );

		// WordPress Indexing tab - only show if feature is available (Business+ plan)
		if ( isset( WPF()->ai_client ) && WPF()->ai_client->is_feature_available( 'wordpress_content_indexing' ) ) {
			$tabs['wp_indexing'] = __( 'WordPress Indexing', 'wpforo' );
		}

		$tabs['ai_tasks']     = __( 'AI Tasks', 'wpforo' );
		$tabs['analytics']    = __( 'AI Analytics', 'wpforo' );
		$tabs['ai_logs']      = __( 'AI Logs', 'wpforo' );
	}

	// Force redirect to overview tab if user tries to access restricted tab
	if ( ! isset( $tabs[ $current_tab ] ) ) {
		$current_tab = 'overview';
	}
	?>
	<?php
	// Get all active boards for AI Settings tab
	$all_boards = WPF()->board->get_boards( [ 'status' => true ] );
	$is_multiboard = count( $all_boards ) > 1;
	?>
	<nav class="nav-tab-wrapper wpforo-ai-tabs">
		<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
			<?php
			$tab_url = add_query_arg( array(
				'page' => 'wpforo-ai',
				'tab'  => $tab_key,
			), admin_url( 'admin.php' ) );
			$active_class = ( $current_tab === $tab_key ) ? 'nav-tab-active' : '';
			?>
			<a href="<?php echo esc_url( $tab_url ); ?>" class="nav-tab <?php echo esc_attr( $active_class ); ?>">
				<?php echo esc_html( $tab_label ); ?>
			</a>
		<?php endforeach; ?>

		<?php
		// AI Settings tab - links to board settings pages
		// Only show when subscription is active (not expired, inactive, etc.)
		$show_settings_tab = $is_connected && in_array( $current_state, [ 'free_trial', 'paid_plan' ], true );
		?>
		<?php if ( $show_settings_tab && $is_multiboard ) : ?>
			<span class="nav-tab wpforo-ai-settings-tab">
                <span style="color: #000;"><?php _e( 'AI Settings', 'wpforo' ); ?></span>
				[ <?php
				$board_links = [];
				foreach ( $all_boards as $board ) {
					$boardid = (int) $board['boardid'];
					// Build settings page URL: wpforo-settings for board 0, wpforo-{id}-settings for others
					$settings_page = ( $boardid === 0 ) ? 'wpforo-settings' : 'wpforo-' . $boardid . '-settings';
					$settings_url = admin_url( 'admin.php?page=' . $settings_page . '&wpf_tab=ai#wpf-settings-tab');
					$board_links[] = sprintf(
						'<a href="%s">%s</a>',
						esc_url( $settings_url ),
						esc_html( $board['title'] )
					);
				}
				echo implode( ' | ', $board_links );
				?> ]
			</span>
		<?php elseif ( $show_settings_tab ) : ?>
			<?php
			// Single board - direct link to settings
			$settings_url = admin_url( 'admin.php?page=wpforo-settings&wpf_tab=ai#wpf-settings-tab' );
			?>
			<a href="<?php echo esc_url( $settings_url ); ?>" class="nav-tab wpforo-ai-settings-tab" style="cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 7px;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12,8a4,4,0,1,0,4,4A4,4,0,0,0,12,8Zm0,6a2,2,0,1,1,2-2A2,2,0,0,1,12,14Z"></path><path d="M21.294,13.9l-.444-.256a9.1,9.1,0,0,0,0-3.29l.444-.256a3,3,0,1,0-3-5.2l-.445.257A8.977,8.977,0,0,0,15,3.513V3A3,3,0,0,0,9,3v.513A8.977,8.977,0,0,0,6.152,5.159L5.705,4.9a3,3,0,0,0-3,5.2l.444.256a9.1,9.1,0,0,0,0,3.29l-.444.256a3,3,0,1,0,3,5.2l.445-.257A8.977,8.977,0,0,0,9,20.487V21a3,3,0,0,0,6,0v-.513a8.977,8.977,0,0,0,2.848-1.646l.447.258a3,3,0,0,0,3-5.2Zm-2.548-3.776a7.048,7.048,0,0,1,0,3.75,1,1,0,0,0,.464,1.133l1.084.626a1,1,0,0,1-1,1.733l-1.086-.628a1,1,0,0,0-1.215.165,6.984,6.984,0,0,1-3.243,1.875,1,1,0,0,0-.751.969V21a1,1,0,0,1-2,0V19.748a1,1,0,0,0-.751-.969A6.984,6.984,0,0,1,7.006,16.9a1,1,0,0,0-1.215-.165l-1.084.627a1,1,0,1,1-1-1.732l1.084-.626a1,1,0,0,0,.464-1.133,7.048,7.048,0,0,1,0-3.75A1,1,0,0,0,4.79,8.992L3.706,8.366a1,1,0,0,1,1-1.733l1.086.628A1,1,0,0,0,7.006,7.1a6.984,6.984,0,0,1,3.243-1.875A1,1,0,0,0,11,4.252V3a1,1,0,0,1,2,0V4.252a1,1,0,0,0,.751.969A6.984,6.984,0,0,1,16.994,7.1a1,1,0,0,0,1.215.165l1.084-.627a1,1,0,1,1,1,1.732l-1.084.626A1,1,0,0,0,18.746,10.125Z"></path></svg>
				<?php _e( 'AI Settings', 'wpforo' ); ?>
			</a>
		<?php endif; ?>
	</nav>

	<!-- Tab Content -->
	<div class="wpforo-ai-tab-content">
		<?php
		switch ( $current_tab ) {
			case 'rag_indexing':
				wpforo_ai_render_rag_indexing_tab( $is_connected, $status );
				break;

			case 'wp_indexing':
				wpforo_ai_render_wp_indexing_tab( $is_connected, $status );
				break;

			case 'ai_tasks':
				wpforo_ai_render_ai_tasks_tab( $is_connected, $status );
				break;

			case 'analytics':
				wpforo_ai_render_analytics_tab( $is_connected, $status );
				break;

			case 'ai_logs':
				wpforo_ai_render_ai_logs_tab( $is_connected, $status );
				break;

			case 'overview':
			default:
				// Display appropriate state
				switch ( $current_state ) {
					case 'not_connected':
						wpforo_ai_render_not_connected_state();
						break;

					case 'pending_approval':
						wpforo_ai_render_pending_approval_state( $status );
						break;

					case 'inactive':
						wpforo_ai_render_inactive_state( $status );
						break;

					case 'free_trial':
						wpforo_ai_render_free_trial_state( $status, $is_post_purchase );
						break;

					case 'paid_plan':
						wpforo_ai_render_paid_plan_state( $status, $is_post_purchase );
						break;

					case 'expired':
						wpforo_ai_render_expired_state( $status );
						break;

					case 'cancelled':
						wpforo_ai_render_cancelled_state( $status );
						break;

					case 'error':
						wpforo_ai_render_error_state( $connection_error ?? $status );
						break;
				}
				break;
		}
		?>
	</div>

</div>

<div style="margin-bottom: 150px;">&nbsp;</div>

<!-- Global JavaScript for all tabs -->
<script type="text/javascript">
	jQuery(document).ready(function($) {
		// Set AJAX nonce (ai-features.js will call init() automatically)
		if (typeof window.WpForoAI !== 'undefined') {
			WpForoAI.ajaxNonce = '<?php echo wp_create_nonce( 'wpforo_ai_features_nonce' ); ?>';
		}

		// Post-purchase notifications (works on all tabs)
		<?php
		$upgraded = isset( $_GET['upgraded'] ) && $_GET['upgraded'] == '1';
		$credits_purchased = isset( $_GET['credits_purchased'] ) && $_GET['credits_purchased'] == '1';
		$plan = isset( $_GET['plan'] ) ? sanitize_key( $_GET['plan'] ) : '';
		$pack = isset( $_GET['pack'] ) ? sanitize_key( $_GET['pack'] ) : '';

		if ( $upgraded || $credits_purchased ) {
			// Clear status cache to force fresh fetch
			delete_transient( 'wpforo_ai_tenant_status' );
		}
		?>

		<?php if ( $upgraded ) : ?>
			// Purchase detected - show processing message
			// The checkPostPurchaseRefresh() function will auto-refresh after 60 seconds
			if (typeof WpForoAI !== 'undefined' && typeof WpForoAI.showNotice === 'function') {
				WpForoAI.showNotice('Processing your <?php echo esc_js( ucfirst( $plan ) ); ?> plan purchase... Page will refresh in 60 seconds.', 'info');
			}
		<?php endif; ?>

		<?php if ( $credits_purchased ) : ?>
			// Credit pack purchase detected
			if (typeof WpForoAI !== 'undefined' && typeof WpForoAI.showNotice === 'function') {
				WpForoAI.showNotice('Processing your <?php echo esc_js( $pack ); ?> credits purchase... Your credits will be added shortly.', 'info');
			}
		<?php endif; ?>

		<?php
		// Show success messages after plan activation or credits added
		$plan_activated = isset( $_GET['plan_activated'] ) && $_GET['plan_activated'] == '1';
		$credits_added = isset( $_GET['credits_added'] ) && $_GET['credits_added'] == '1';
		?>

		<?php if ( $plan_activated ) : ?>
			// Plan successfully activated
			if (typeof WpForoAI !== 'undefined' && typeof WpForoAI.showNotice === 'function') {
				WpForoAI.showNotice('✓ Your subscription plan has been successfully activated!', 'success');
			}
		<?php endif; ?>

		<?php if ( $credits_added ) : ?>
			// Credits successfully added
			if (typeof WpForoAI !== 'undefined' && typeof WpForoAI.showNotice === 'function') {
				WpForoAI.showNotice('✓ Credits have been successfully added to your account!', 'success');
			}
		<?php endif; ?>
	});
</script>
