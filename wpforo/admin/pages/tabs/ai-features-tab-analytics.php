<?php
/**
 * AI Features - Analytics Tab
 *
 * Provides comprehensive analytics dashboard for AI feature usage:
 * - AI Usage Analytics (credits, API calls, response times)
 * - Forum Activity Analytics (posts, topics, engagement)
 * - User Engagement Analytics (registrations, retention)
 * - Content Performance Analytics (views, replies)
 * - AI-Powered Insights (sentiment, trending topics)
 *
 * @package wpForo
 * @subpackage Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render Analytics tab content
 *
 * @param bool  $is_connected Whether tenant is connected to AI service
 * @param array $status       Tenant status data from API
 */
function wpforo_ai_render_analytics_tab( $is_connected, $status ) {
	if ( ! $is_connected ) {
		?>
		<div class="wpforo-ai-box wpforo-ai-not-connected-notice">
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-status-badge status-warning">
					<span class="dashicons dashicons-warning"></span>
					<?php _e( 'Not Connected', 'wpforo' ); ?>
				</div>
				<p><?php _e( 'Please connect to wpForo AI API first in the Overview tab to enable Analytics.', 'wpforo' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpforo-ai&tab=overview' ) ); ?>" class="button button-primary">
					<?php _e( 'Go to Overview', 'wpforo' ); ?>
				</a>
			</div>
		</div>
		<?php
		return;
	}

	// Check if user's plan allows AI Analytics (requires Professional or higher)
	$subscription = isset( $status['subscription'] ) && is_array( $status['subscription'] ) ? $status['subscription'] : [];
	$current_plan = isset( $subscription['plan'] ) ? strtolower( $subscription['plan'] ) : 'free_trial';

	// AI Analytics require Professional plan or higher
	if ( ! wpforo_ai_is_feature_enabled_by_plan( $current_plan, 'professional' ) ) {
		?>
		<div class="wpforo-ai-box wpforo-ai-upgrade-notice">
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-status-badge status-info">
					<span class="dashicons dashicons-lock"></span>
                    <?php _e( 'Upgrade to Professional Plan to Unlock', 'wpforo' ); ?>&nbsp;
				</div>
				<h3><?php _e( 'AI Analytics & Insights', 'wpforo' ); ?></h3>
				<p><?php _e( 'AI Analytics & Insights are available on Professional, Business, and Enterprise plans.', 'wpforo' ); ?></p>
				<ul class="wpforo-ai-feature-list">
					<li><span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Track AI feature usage and credit consumption', 'wpforo' ); ?></li>
					<li><span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Monitor forum activity and engagement metrics', 'wpforo' ); ?></li>
					<li><span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Analyze content performance and trends', 'wpforo' ); ?></li>
					<li><span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Get AI-powered insights and recommendations', 'wpforo' ); ?></li>
				</ul>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpforo-ai&tab=overview' ) ); ?>" class="button button-primary">
						<?php _e( 'Upgrade to Professional', 'wpforo' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
		return;
	}

	// Get all boards and determine current board
	$boards = WPF()->board->get_boards();
	$current_boardid = isset( $_GET['boardid'] ) ? intval( $_GET['boardid'] ) : 0;

	// Validate boardid exists
	$valid_boardids = array_column( $boards, 'boardid' );
	if ( ! in_array( $current_boardid, $valid_boardids ) ) {
		$current_boardid = 0; // Default to main board
	}

	// Switch to the selected board context
	if ( $current_boardid > 0 ) {
		WPF()->change_board( $current_boardid );
	}

	// Render board sub-tabs if multiple boards exist
	if ( count( $boards ) > 1 ) :
		// Find current board title (from settings, not label)
		$current_board_title = __( 'Board', 'wpforo' );
		foreach ( $boards as $board ) {
			if ( isset( $board['boardid'] ) && (int) $board['boardid'] === $current_boardid ) {
				$current_board_title = isset( $board['settings']['title'] ) ? esc_html( $board['settings']['title'] ) : ( isset( $board['title'] ) ? esc_html( $board['title'] ) : $current_board_title );
				break;
			}
		}
		?>
		<div class="wpforo-ai-board-tabs">
			<div class="wpforo-ai-board-current">
				<?php echo esc_html__( 'Board:', 'wpforo' ); ?> <strong><?php echo $current_board_title; ?></strong>
			</div>
			<div class="wpforo-ai-board-tabs-list">
				<?php foreach ( $boards as $board ) :
					$board_id = isset( $board['boardid'] ) ? (int) $board['boardid'] : 0;
					$board_label = isset( $board['title'] ) ? esc_html( $board['title'] ) : __( 'Board', 'wpforo' ) . ' ' . $board_id;
					$tab_url = add_query_arg( array(
						'page'    => 'wpforo-ai',
						'tab'     => 'analytics',
						'boardid' => $board_id,
					), admin_url( 'admin.php' ) );
					$active_class = ( $current_boardid === $board_id ) ? 'active' : '';
					?>
					<a href="<?php echo esc_url( $tab_url ); ?>" class="wpforo-ai-board-tab <?php echo esc_attr( $active_class ); ?>">
						<span class="dashicons dashicons-category"></span>
						<?php echo $board_label; ?>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	endif;

	// Get available credits from status
	$subscription = isset( $status['subscription'] ) && is_array( $status['subscription'] ) ? $status['subscription'] : [];
	$credits_remaining = isset( $subscription['credits_remaining'] ) ? (int) $subscription['credits_remaining'] : 0;

	// Get current sub-tab
	$current_subtab = isset( $_GET['subtab'] ) ? sanitize_key( $_GET['subtab'] ) : 'ai_usage';
	$subtabs = array(
		'ai_usage'        => __( 'AI Usage Analytics', 'wpforo' ),
		'forum_activity'  => __( 'Forum Activity', 'wpforo' ),
		'user_engagement' => __( 'User Engagement', 'wpforo' ),
		'content'         => __( 'Content Performance', 'wpforo' ),
		'ai_insights'     => __( 'AI Insights', 'wpforo' ),
	);

	?>
	<div class="wpforo-ai-analytics wpforo-ai-analytics-tab">

		<!-- Sub-tabs Navigation -->
		<div class="wpforo-ai-box wpforo-ai-analytics-subtabs-box">
			<div class="wpforo-ai-box-body">
				<nav class="wpforo-ai-analytics-subtabs">
					<?php foreach ( $subtabs as $subtab_key => $subtab_label ) :
						$subtab_url = add_query_arg( array(
							'page'    => 'wpforo-ai',
							'tab'     => 'analytics',
							'subtab'  => $subtab_key,
							'boardid' => $current_boardid,
						), admin_url( 'admin.php' ) );
						$active_class = ( $current_subtab === $subtab_key ) ? 'active' : '';
						$is_disabled = false; // All sub-tabs are now enabled
						$disabled_class = '';
						?>
						<a href="<?php echo $is_disabled ? '#' : esc_url( $subtab_url ); ?>"
						   class="wpforo-ai-analytics-subtab <?php echo esc_attr( $active_class ); ?> <?php echo esc_attr( $disabled_class ); ?>"
						   <?php echo $is_disabled ? 'onclick="return false;" title="' . esc_attr__( 'Coming soon', 'wpforo' ) . '"' : ''; ?>>
							<?php echo esc_html( $subtab_label ); ?>
							<?php if ( $is_disabled ) : ?>
								<span class="wpforo-ai-coming-soon"><?php _e( 'Soon', 'wpforo' ); ?></span>
							<?php endif; ?>
						</a>
					<?php endforeach; ?>
				</nav>
			</div>
		</div>

		<?php
		// Render sub-tab content
		switch ( $current_subtab ) {
			case 'ai_usage':
				wpforo_ai_render_usage_analytics_subtab( $current_boardid, $status );
				break;
			case 'forum_activity':
				wpforo_ai_render_forum_activity_subtab( $current_boardid );
				break;
			case 'user_engagement':
				wpforo_ai_render_user_engagement_subtab( $current_boardid );
				break;
			case 'content':
				wpforo_ai_render_content_performance_subtab( $current_boardid );
				break;
			case 'ai_insights':
				wpforo_ai_render_ai_insights_subtab( $current_boardid, $status );
				break;
			default:
				wpforo_ai_render_usage_analytics_subtab( $current_boardid, $status );
				break;
		}
		?>

	</div>
	<?php
}

/**
 * Render AI Usage Analytics sub-tab
 *
 * @param int   $board_id Current board ID
 * @param array $status   Tenant status data
 */
function wpforo_ai_render_usage_analytics_subtab( $board_id, $status ) {
	// Time range presets
	$time_ranges = array(
		'today'     => __( 'Today', 'wpforo' ),
		'7days'     => __( 'Last 7 Days', 'wpforo' ),
		'30days'    => __( 'Last 30 Days', 'wpforo' ),
		'90days'    => __( 'Last 90 Days', 'wpforo' ),
		'this_year' => __( 'This Year', 'wpforo' ),
		'all_time'  => __( 'All Time', 'wpforo' ),
		'custom'    => __( 'Custom Range', 'wpforo' ),
	);

	$current_range = isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : '7days';
	$custom_start = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : '';
	$custom_end = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : '';

	// Calculate time range
	$time_info = wpforo_ai_calculate_time_range( $current_range, $custom_start, $custom_end );
	?>

	<!-- Time Range Selector -->
	<div class="wpforo-ai-box wpforo-ai-time-range-box">
		<div class="wpforo-ai-box-body">
			<div class="wpforo-ai-time-range-selector">
				<div class="wpforo-ai-time-range-label">
					<span class="dashicons dashicons-calendar-alt"></span>
					<?php _e( 'Time Range:', 'wpforo' ); ?>
				</div>
				<div class="wpforo-ai-time-range-buttons">
					<?php foreach ( $time_ranges as $range_key => $range_label ) :
						if ( $range_key === 'custom' ) continue; // Handle custom separately
						$range_url = add_query_arg( array(
							'page'    => 'wpforo-ai',
							'tab'     => 'analytics',
							'subtab'  => 'ai_usage',
							'boardid' => $board_id,
							'range'   => $range_key,
						), admin_url( 'admin.php' ) );
						$active_class = ( $current_range === $range_key ) ? 'active' : '';
						?>
						<a href="<?php echo esc_url( $range_url ); ?>"
						   class="wpforo-ai-time-range-btn <?php echo esc_attr( $active_class ); ?>"
						   data-range="<?php echo esc_attr( $range_key ); ?>">
							<?php echo esc_html( $range_label ); ?>
						</a>
					<?php endforeach; ?>
					<button type="button" class="wpforo-ai-time-range-btn wpforo-ai-custom-range-toggle <?php echo $current_range === 'custom' ? 'active' : ''; ?>">
						<?php _e( 'Custom', 'wpforo' ); ?>
					</button>
				</div>
				<div class="wpforo-ai-custom-range-picker" style="<?php echo $current_range === 'custom' ? '' : 'display: none;'; ?>">
					<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
						<input type="hidden" name="page" value="wpforo-ai">
						<input type="hidden" name="tab" value="analytics">
						<input type="hidden" name="subtab" value="ai_usage">
						<input type="hidden" name="boardid" value="<?php echo esc_attr( $board_id ); ?>">
						<input type="hidden" name="range" value="custom">
						<label>
							<?php _e( 'From:', 'wpforo' ); ?>
							<input type="date" name="start_date" value="<?php echo esc_attr( $custom_start ); ?>" required>
						</label>
						<label>
							<?php _e( 'To:', 'wpforo' ); ?>
							<input type="date" name="end_date" value="<?php echo esc_attr( $custom_end ); ?>" required>
						</label>
						<button type="submit" class="button button-primary">
							<?php _e( 'Apply', 'wpforo' ); ?>
						</button>
					</form>
				</div>
				<div class="wpforo-ai-time-range-info">
					<span class="wpforo-ai-date-range-display">
						<?php echo esc_html( $time_info['display'] ); ?>
					</span>
				</div>
			</div>
		</div>
	</div>

	<!-- Main Loading Overlay -->
	<div class="wpforo-ai-analytics-main-loading" id="wpforo-ai-analytics-main-loading">
		<span class="wpforo-ai-loading-spinner"></span>
		<span><?php _e( 'Loading analytics data...', 'wpforo' ); ?></span>
	</div>

	<!-- Analytics Content (hidden until loaded) -->
	<div class="wpforo-ai-analytics-content" id="wpforo-ai-analytics-content">

	<!-- Summary Cards -->
	<div class="wpforo-ai-analytics-summary-cards" id="wpforo-ai-analytics-summary">
		<div class="wpforo-ai-analytics-card">
			<div class="wpforo-ai-analytics-card-icon">
				<span class="dashicons dashicons-database"></span>
			</div>
			<div class="wpforo-ai-analytics-card-content">
				<div class="wpforo-ai-analytics-card-value" id="total-credits-used">
					<span class="wpforo-ai-loading-spinner"></span>
				</div>
				<div class="wpforo-ai-analytics-card-label"><?php _e( 'Total Credits Used', 'wpforo' ); ?></div>
			</div>
		</div>
		<div class="wpforo-ai-analytics-card">
			<div class="wpforo-ai-analytics-card-icon">
				<span class="dashicons dashicons-chart-line"></span>
			</div>
			<div class="wpforo-ai-analytics-card-content">
				<div class="wpforo-ai-analytics-card-value" id="avg-credits-day">
					<span class="wpforo-ai-loading-spinner"></span>
				</div>
				<div class="wpforo-ai-analytics-card-label"><?php _e( 'Average per Day', 'wpforo' ); ?></div>
			</div>
		</div>
		<div class="wpforo-ai-analytics-card">
			<div class="wpforo-ai-analytics-card-icon">
				<span class="dashicons dashicons-update"></span>
			</div>
			<div class="wpforo-ai-analytics-card-content">
				<div class="wpforo-ai-analytics-card-value" id="total-api-calls">
					<span class="wpforo-ai-loading-spinner"></span>
				</div>
				<div class="wpforo-ai-analytics-card-label"><?php _e( 'API Calls', 'wpforo' ); ?></div>
			</div>
		</div>
		<div class="wpforo-ai-analytics-card">
			<div class="wpforo-ai-analytics-card-icon">
				<span class="dashicons dashicons-yes-alt"></span>
			</div>
			<div class="wpforo-ai-analytics-card-content">
				<div class="wpforo-ai-analytics-card-value" id="success-rate">
					<span class="wpforo-ai-loading-spinner"></span>
				</div>
				<div class="wpforo-ai-analytics-card-label"><?php _e( 'Success Rate', 'wpforo' ); ?></div>
			</div>
		</div>
	</div>

	<!-- Charts Row -->
	<div class="wpforo-ai-analytics-charts-row">
		<!-- Credits Usage Over Time -->
		<div class="wpforo-ai-box wpforo-ai-chart-box wpforo-ai-chart-wide">
			<div class="wpforo-ai-box-header">
				<h3>
					<span class="dashicons dashicons-chart-area"></span>
					<?php _e( 'Credits Usage Over Time', 'wpforo' ); ?>
				</h3>
			</div>
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-chart-container">
					<canvas id="credits-usage-chart"></canvas>
				</div>
				<div class="wpforo-ai-chart-loading" id="credits-usage-loading">
					<span class="wpforo-ai-loading-spinner"></span>
					<span><?php _e( 'Loading chart data...', 'wpforo' ); ?></span>
				</div>
			</div>
		</div>
	</div>

	<div class="wpforo-ai-analytics-charts-row">
		<!-- Credits by Feature -->
		<div class="wpforo-ai-box wpforo-ai-chart-box">
			<div class="wpforo-ai-box-header">
				<h3>
					<span class="dashicons dashicons-chart-pie"></span>
					<?php _e( 'Credits by Feature', 'wpforo' ); ?>
				</h3>
			</div>
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-chart-container wpforo-ai-chart-pie-container">
					<canvas id="credits-by-feature-chart"></canvas>
				</div>
				<div class="wpforo-ai-chart-loading" id="credits-feature-loading">
					<span class="wpforo-ai-loading-spinner"></span>
					<span><?php _e( 'Loading...', 'wpforo' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Moderation Statistics -->
		<div class="wpforo-ai-box wpforo-ai-chart-box">
			<div class="wpforo-ai-box-header">
				<h3>
					<span class="dashicons dashicons-shield"></span>
					<?php _e( 'Moderation Statistics', 'wpforo' ); ?>
				</h3>
			</div>
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-chart-container">
					<canvas id="moderation-stats-chart"></canvas>
				</div>
				<div class="wpforo-ai-chart-loading" id="moderation-loading">
					<span class="wpforo-ai-loading-spinner"></span>
					<span><?php _e( 'Loading...', 'wpforo' ); ?></span>
				</div>
			</div>
		</div>
	</div>

	<!-- Feature Usage Table -->
	<div class="wpforo-ai-box wpforo-ai-usage-table-box">
		<div class="wpforo-ai-box-header">
			<h3>
				<span class="dashicons dashicons-list-view"></span>
				<?php _e( 'Feature Usage Details', 'wpforo' ); ?>
			</h3>
		</div>
		<div class="wpforo-ai-box-body">
			<table class="wpforo-ai-usage-table widefat striped" id="feature-usage-table">
				<thead>
					<tr>
						<th><?php _e( 'Feature', 'wpforo' ); ?></th>
						<th><?php _e( 'Requests', 'wpforo' ); ?></th>
						<th><?php _e( 'Credits', 'wpforo' ); ?></th>
						<th><?php _e( 'Avg Response', 'wpforo' ); ?></th>
						<th><?php _e( 'Success Rate', 'wpforo' ); ?></th>
					</tr>
				</thead>
				<tbody id="feature-usage-tbody">
					<tr class="wpforo-ai-loading-row">
						<td colspan="5">
							<span class="wpforo-ai-loading-spinner"></span>
							<?php _e( 'Loading usage data...', 'wpforo' ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	</div><!-- /.wpforo-ai-analytics-content -->

	<?php
	// Output JavaScript data
	$analytics_data = array(
		'boardId'   => $board_id,
		'startTime' => $time_info['start'],
		'endTime'   => $time_info['end'],
		'range'     => $current_range,
		'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
		'nonce'     => wp_create_nonce( 'wpforo_ai_analytics_nonce' ),
		'i18n'      => array(
			'loading'       => __( 'Loading...', 'wpforo' ),
			'noData'        => __( 'No data available for this period', 'wpforo' ),
			'error'         => __( 'Error loading data', 'wpforo' ),
			'credits'       => __( 'Credits', 'wpforo' ),
			'requests'      => __( 'Requests', 'wpforo' ),
			'spamBlocked'   => __( 'Spam Blocked', 'wpforo' ),
			'toxicDetected' => __( 'Toxic Detected', 'wpforo' ),
			'policyViolations' => __( 'Policy Violations', 'wpforo' ),
			'cleanPassed'   => __( 'Clean Passed', 'wpforo' ),
		),
		'featureNames' => wpforo_ai_get_feature_display_names(),
		'featureColors' => wpforo_ai_get_feature_colors(),
	);
	?>
	<script type="text/javascript">
		var wpforoAIAnalytics = <?php echo wp_json_encode( $analytics_data ); ?>;
	</script>
	<?php
}

/**
 * Calculate time range based on preset or custom dates
 *
 * @param string $range       Time range preset
 * @param string $custom_start Custom start date (Y-m-d)
 * @param string $custom_end   Custom end date (Y-m-d)
 * @return array Time range info with start, end timestamps and display string
 */
function wpforo_ai_calculate_time_range( $range, $custom_start = '', $custom_end = '' ) {
	$now = current_time( 'timestamp' );
	$today_start = strtotime( 'today midnight', $now );
	$today_end = strtotime( 'tomorrow midnight', $now ) - 1;

	switch ( $range ) {
		case 'today':
			$start = $today_start;
			$end = $today_end;
			$display = date_i18n( get_option( 'date_format' ), $start );
			break;

		case '7days':
			$start = strtotime( '-6 days midnight', $now );
			$end = $today_end;
			$display = sprintf(
				'%s - %s',
				date_i18n( get_option( 'date_format' ), $start ),
				date_i18n( get_option( 'date_format' ), $end )
			);
			break;

		case '30days':
			$start = strtotime( '-29 days midnight', $now );
			$end = $today_end;
			$display = sprintf(
				'%s - %s',
				date_i18n( get_option( 'date_format' ), $start ),
				date_i18n( get_option( 'date_format' ), $end )
			);
			break;

		case '90days':
			$start = strtotime( '-89 days midnight', $now );
			$end = $today_end;
			$display = sprintf(
				'%s - %s',
				date_i18n( get_option( 'date_format' ), $start ),
				date_i18n( get_option( 'date_format' ), $end )
			);
			break;

		case 'this_year':
			$start = strtotime( 'first day of January ' . date( 'Y', $now ) . ' midnight' );
			$end = $today_end;
			$display = sprintf( __( 'Year %s', 'wpforo' ), date( 'Y', $now ) );
			break;

		case 'all_time':
			// Use a date far in the past
			$start = strtotime( '2020-01-01 00:00:00' );
			$end = $today_end;
			$display = __( 'All Time', 'wpforo' );
			break;

		case 'custom':
			if ( ! empty( $custom_start ) && ! empty( $custom_end ) ) {
				$start = strtotime( $custom_start . ' 00:00:00' );
				$end = strtotime( $custom_end . ' 23:59:59' );
				$display = sprintf(
					'%s - %s',
					date_i18n( get_option( 'date_format' ), $start ),
					date_i18n( get_option( 'date_format' ), $end )
				);
			} else {
				// Fallback to 7 days if custom dates not provided
				$start = strtotime( '-6 days midnight', $now );
				$end = $today_end;
				$display = sprintf(
					'%s - %s',
					date_i18n( get_option( 'date_format' ), $start ),
					date_i18n( get_option( 'date_format' ), $end )
				);
			}
			break;

		default:
			// Default to 7 days
			$start = strtotime( '-6 days midnight', $now );
			$end = $today_end;
			$display = sprintf(
				'%s - %s',
				date_i18n( get_option( 'date_format' ), $start ),
				date_i18n( get_option( 'date_format' ), $end )
			);
	}

	return array(
		'start'   => $start,
		'end'     => $end,
		'display' => $display,
	);
}

/**
 * Get feature display names for analytics
 *
 * @return array Feature key => Display name mapping
 */
function wpforo_ai_get_feature_display_names() {
	return array(
		'semantic_search'              => __( 'AI Search', 'wpforo' ),
		'search_enhance'               => __( 'Search Enhancement', 'wpforo' ),
		'multi_language_translation'   => __( 'Translation', 'wpforo' ),
		'topic_summary'                => __( 'Topic Summary', 'wpforo' ),
		'content_indexing'             => __( 'Content Indexing', 'wpforo' ),
		'multimodal_image_indexing'    => __( 'Image Indexing', 'wpforo' ),
		'document_indexing'            => __( 'Document Indexing', 'wpforo' ),
		'ai_topic_generator'           => __( 'Topic Generator', 'wpforo' ),
		'ai_reply_generator'           => __( 'Reply Generator', 'wpforo' ),
		'auto_tag_generation'          => __( 'Tag Maintenance', 'wpforo' ),
		'ai_suggestion'                => __( 'AI Suggestions', 'wpforo' ),
		'ai_spam_detection'            => __( 'Spam Detection', 'wpforo' ),
		'ai_toxicity_detection'        => __( 'Toxicity Detection', 'wpforo' ),
		'ai_rule_compliance'           => __( 'Rule Compliance', 'wpforo' ),
		'unified_moderation'           => __( 'Unified Moderation', 'wpforo' ),
		'ai_chat'                      => __( 'AI Chatbot', 'wpforo' ),
		'ai_insight_sentiment'         => __( 'Insight: Sentiment', 'wpforo' ),
		'ai_insight_trending'          => __( 'Insight: Trending', 'wpforo' ),
		'ai_insight_recommendations'   => __( 'Insight: Recommendations', 'wpforo' ),
		'ai_insight_deep_analysis'     => __( 'Insight: Deep Analysis', 'wpforo' ),
		'ai_insight_sentiment_trend'   => __( 'Insight: Sentiment Trend', 'wpforo' ),
	);
}

/**
 * Get feature colors for charts
 *
 * @return array Feature key => Color code mapping
 */
function wpforo_ai_get_feature_colors() {
	return array(
		'semantic_search'              => '#4CAF50',
		'search_enhance'               => '#8BC34A',
		'multi_language_translation'   => '#2196F3',
		'topic_summary'                => '#03A9F4',
		'content_indexing'             => '#9C27B0',
		'multimodal_image_indexing'    => '#CE93D8',
		'document_indexing'            => '#AB47BC',
		'ai_topic_generator'           => '#FF9800',
		'ai_reply_generator'           => '#FF5722',
		'auto_tag_generation'          => '#795548',
		'ai_suggestion'                => '#00BCD4',
		'ai_spam_detection'            => '#F44336',
		'ai_toxicity_detection'        => '#E91E63',
		'ai_rule_compliance'           => '#673AB7',
		'unified_moderation'           => '#3F51B5',
		'ai_chat'                      => '#009688',
		'ai_insight_sentiment'         => '#7C4DFF',
		'ai_insight_trending'          => '#536DFE',
		'ai_insight_recommendations'   => '#448AFF',
		'ai_insight_deep_analysis'     => '#40C4FF',
		'ai_insight_sentiment_trend'   => '#18FFFF',
	);
}

/**
 * Render Forum Activity Analytics sub-tab
 *
 * This is a FREE feature - all data comes from local wpForo database.
 * No API calls required.
 *
 * @param int $board_id Current board ID
 */
function wpforo_ai_render_forum_activity_subtab( $board_id ) {
	// Time range presets
	$time_ranges = array(
		'today'     => __( 'Today', 'wpforo' ),
		'7days'     => __( 'Last 7 Days', 'wpforo' ),
		'30days'    => __( 'Last 30 Days', 'wpforo' ),
		'90days'    => __( 'Last 90 Days', 'wpforo' ),
		'this_year' => __( 'This Year', 'wpforo' ),
		'all_time'  => __( 'All Time', 'wpforo' ),
		'custom'    => __( 'Custom Range', 'wpforo' ),
	);

	$current_range = isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : '7days';
	$custom_start  = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : '';
	$custom_end    = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : '';

	// Calculate time range
	$time_info = wpforo_ai_calculate_time_range( $current_range, $custom_start, $custom_end );

	// Get forum activity data
	$activity_data = wpforo_ai_get_forum_activity_data( $board_id, $time_info['start'], $time_info['end'] );
	?>

	<!-- Time Range Selector -->
	<div class="wpforo-ai-box wpforo-ai-time-range-box">
		<div class="wpforo-ai-box-body">
			<div class="wpforo-ai-time-range-selector">
				<div class="wpforo-ai-time-range-label">
					<span class="dashicons dashicons-calendar-alt"></span>
					<?php _e( 'Time Range:', 'wpforo' ); ?>
				</div>
				<div class="wpforo-ai-time-range-buttons">
					<?php foreach ( $time_ranges as $range_key => $range_label ) :
						if ( $range_key === 'custom' ) continue;
						$range_url = add_query_arg( array(
							'page'    => 'wpforo-ai',
							'tab'     => 'analytics',
							'subtab'  => 'forum_activity',
							'boardid' => $board_id,
							'range'   => $range_key,
						), admin_url( 'admin.php' ) );
						$active_class = ( $current_range === $range_key ) ? 'active' : '';
						?>
						<a href="<?php echo esc_url( $range_url ); ?>"
						   class="wpforo-ai-time-range-btn <?php echo esc_attr( $active_class ); ?>"
						   data-range="<?php echo esc_attr( $range_key ); ?>">
							<?php echo esc_html( $range_label ); ?>
						</a>
					<?php endforeach; ?>
					<button type="button" class="wpforo-ai-time-range-btn wpforo-ai-custom-range-toggle <?php echo $current_range === 'custom' ? 'active' : ''; ?>">
						<?php _e( 'Custom', 'wpforo' ); ?>
					</button>
				</div>
				<div class="wpforo-ai-custom-range-picker" style="<?php echo $current_range === 'custom' ? '' : 'display: none;'; ?>">
					<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
						<input type="hidden" name="page" value="wpforo-ai">
						<input type="hidden" name="tab" value="analytics">
						<input type="hidden" name="subtab" value="forum_activity">
						<input type="hidden" name="boardid" value="<?php echo esc_attr( $board_id ); ?>">
						<input type="hidden" name="range" value="custom">
						<label>
							<?php _e( 'From:', 'wpforo' ); ?>
							<input type="date" name="start_date" value="<?php echo esc_attr( $custom_start ); ?>" required>
						</label>
						<label>
							<?php _e( 'To:', 'wpforo' ); ?>
							<input type="date" name="end_date" value="<?php echo esc_attr( $custom_end ); ?>" required>
						</label>
						<button type="submit" class="button button-primary">
							<?php _e( 'Apply', 'wpforo' ); ?>
						</button>
					</form>
				</div>
				<div class="wpforo-ai-time-range-info">
					<span class="wpforo-ai-date-range-display">
						<?php echo esc_html( $time_info['display'] ); ?>
					</span>
				</div>
			</div>
		</div>
	</div>

	<!-- Summary Cards -->
	<div class="wpforo-ai-analytics-summary-cards">
		<div class="wpforo-ai-analytics-card">
			<div class="wpforo-ai-analytics-card-icon">
				<span class="dashicons dashicons-format-chat"></span>
			</div>
			<div class="wpforo-ai-analytics-card-content">
				<div class="wpforo-ai-analytics-card-value">
					<?php echo number_format( $activity_data['summary']['total_topics'] ); ?>
				</div>
				<div class="wpforo-ai-analytics-card-label"><?php _e( 'New Topics', 'wpforo' ); ?></div>
			</div>
		</div>
		<div class="wpforo-ai-analytics-card">
			<div class="wpforo-ai-analytics-card-icon">
				<span class="dashicons dashicons-admin-comments"></span>
			</div>
			<div class="wpforo-ai-analytics-card-content">
				<div class="wpforo-ai-analytics-card-value">
					<?php echo number_format( $activity_data['summary']['total_replies'] ); ?>
				</div>
				<div class="wpforo-ai-analytics-card-label"><?php _e( 'Replies', 'wpforo' ); ?></div>
			</div>
		</div>
		<div class="wpforo-ai-analytics-card">
			<div class="wpforo-ai-analytics-card-icon">
				<span class="dashicons dashicons-groups"></span>
			</div>
			<div class="wpforo-ai-analytics-card-content">
				<div class="wpforo-ai-analytics-card-value">
					<?php echo number_format( $activity_data['summary']['active_users'] ); ?>
				</div>
				<div class="wpforo-ai-analytics-card-label"><?php _e( 'Active Users', 'wpforo' ); ?></div>
			</div>
		</div>
		<div class="wpforo-ai-analytics-card">
			<div class="wpforo-ai-analytics-card-icon">
				<span class="dashicons dashicons-chart-bar"></span>
			</div>
			<div class="wpforo-ai-analytics-card-content">
				<div class="wpforo-ai-analytics-card-value">
					<?php echo number_format( $activity_data['summary']['avg_replies_per_topic'], 1 ); ?>
				</div>
				<div class="wpforo-ai-analytics-card-label"><?php _e( 'Avg Replies/Topic', 'wpforo' ); ?></div>
			</div>
		</div>
	</div>

	<!-- Charts Row 1: Posts Over Time -->
	<div class="wpforo-ai-analytics-charts-row">
		<div class="wpforo-ai-box wpforo-ai-chart-box wpforo-ai-chart-wide">
			<div class="wpforo-ai-box-header">
				<h3>
					<span class="dashicons dashicons-chart-area"></span>
					<?php _e( 'Posts Over Time', 'wpforo' ); ?>
				</h3>
			</div>
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-chart-container">
					<canvas id="forum-posts-chart"></canvas>
				</div>
			</div>
		</div>
	</div>

	<!-- Charts Row 2: Activity Heatmap and Top Forums -->
	<div class="wpforo-ai-analytics-charts-row">
		<!-- Activity Heatmap -->
		<div class="wpforo-ai-box wpforo-ai-chart-box">
			<div class="wpforo-ai-box-header">
				<h3>
					<span class="dashicons dashicons-calendar"></span>
					<?php _e( 'Activity Heatmap', 'wpforo' ); ?>
				</h3>
			</div>
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-heatmap-container" id="activity-heatmap">
					<?php wpforo_ai_render_activity_heatmap( $activity_data['heatmap'] ); ?>
				</div>
			</div>
		</div>

		<!-- Top Forums by Activity -->
		<div class="wpforo-ai-box wpforo-ai-chart-box">
			<div class="wpforo-ai-box-header">
				<h3>
					<span class="dashicons dashicons-category"></span>
					<?php _e( 'Top Forums by Activity', 'wpforo' ); ?>
				</h3>
			</div>
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-chart-container">
					<canvas id="top-forums-chart"></canvas>
				</div>
			</div>
		</div>
	</div>

	<!-- What's New Table -->
	<div class="wpforo-ai-box wpforo-ai-usage-table-box">
		<div class="wpforo-ai-box-header">
			<h3>
				<span class="dashicons dashicons-list-view"></span>
				<?php _e( 'Forum Activity Breakdown', 'wpforo' ); ?>
			</h3>
		</div>
		<div class="wpforo-ai-box-body">
			<table class="wpforo-ai-usage-table widefat striped">
				<thead>
					<tr>
						<th><?php _e( 'Forum', 'wpforo' ); ?></th>
						<th><?php _e( 'New Topics', 'wpforo' ); ?></th>
						<th><?php _e( 'Replies', 'wpforo' ); ?></th>
						<th><?php _e( 'Active Users', 'wpforo' ); ?></th>
						<th><?php _e( 'Total Views', 'wpforo' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $activity_data['forums'] ) ) : ?>
						<?php foreach ( $activity_data['forums'] as $forum ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $forum['title'] ); ?></strong>
								</td>
								<td><?php echo number_format( $forum['topics'] ); ?></td>
								<td><?php echo number_format( $forum['replies'] ); ?></td>
								<td><?php echo number_format( $forum['active_users'] ); ?></td>
								<td><?php echo number_format( $forum['views'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="5" class="wpforo-ai-no-data">
								<?php _e( 'No forum activity in this period', 'wpforo' ); ?>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<?php
	// Output JavaScript data for charts
	$chart_data = array(
		'postsOverTime' => $activity_data['time_series'],
		'topForums'     => array_slice( $activity_data['forums'], 0, 10 ),
		'i18n'          => array(
			'topics'  => __( 'Topics', 'wpforo' ),
			'replies' => __( 'Replies', 'wpforo' ),
			'posts'   => __( 'Posts', 'wpforo' ),
		),
	);
	?>
	<script type="text/javascript">
		var wpforoForumActivity = <?php echo wp_json_encode( $chart_data ); ?>;
	</script>
	<?php
}

/**
 * Get forum activity data from database
 *
 * @param int $board_id   Board ID
 * @param int $start_time Start timestamp
 * @param int $end_time   End timestamp
 * @return array Activity data
 */
function wpforo_ai_get_forum_activity_data( $board_id, $start_time, $end_time ) {
	$start_date = date( 'Y-m-d H:i:s', $start_time );
	$end_date   = date( 'Y-m-d H:i:s', $end_time );

	// Get summary stats
	$summary = wpforo_ai_get_activity_summary( $start_date, $end_date );

	// Get time series data (posts per day)
	$time_series = wpforo_ai_get_posts_time_series( $start_date, $end_date );

	// Get activity heatmap (hour vs day of week)
	$heatmap = wpforo_ai_get_activity_heatmap( $start_date, $end_date );

	// Get forum breakdown
	$forums = wpforo_ai_get_forum_breakdown( $start_date, $end_date );

	return array(
		'summary'     => $summary,
		'time_series' => $time_series,
		'heatmap'     => $heatmap,
		'forums'      => $forums,
	);
}

/**
 * Get activity summary statistics
 *
 * @param string $start_date Start date (Y-m-d H:i:s)
 * @param string $end_date   End date (Y-m-d H:i:s)
 * @return array Summary stats
 */
function wpforo_ai_get_activity_summary( $start_date, $end_date ) {
	// Total new topics
	$total_topics = (int) WPF()->db->get_var( WPF()->db->prepare(
		"SELECT COUNT(*) FROM " . WPF()->tables->topics . "
		 WHERE created BETWEEN %s AND %s
		 AND status = 0",
		$start_date, $end_date
	) );

	// Total replies (posts that are not first posts)
	$total_replies = (int) WPF()->db->get_var( WPF()->db->prepare(
		"SELECT COUNT(*) FROM " . WPF()->tables->posts . "
		 WHERE created BETWEEN %s AND %s
		 AND is_first_post = 0
		 AND status = 0",
		$start_date, $end_date
	) );

	// Active users (distinct users who posted)
	$active_users = (int) WPF()->db->get_var( WPF()->db->prepare(
		"SELECT COUNT(DISTINCT userid) FROM " . WPF()->tables->posts . "
		 WHERE created BETWEEN %s AND %s
		 AND userid > 0
		 AND status = 0",
		$start_date, $end_date
	) );

	// Average replies per topic
	$avg_replies = $total_topics > 0 ? ( $total_replies / $total_topics ) : 0;

	return array(
		'total_topics'         => $total_topics,
		'total_replies'        => $total_replies,
		'active_users'         => $active_users,
		'avg_replies_per_topic' => $avg_replies,
	);
}

/**
 * Get posts time series (for area chart)
 *
 * @param string $start_date Start date
 * @param string $end_date   End date
 * @return array Time series data
 */
function wpforo_ai_get_posts_time_series( $start_date, $end_date ) {
	$results = WPF()->db->get_results( WPF()->db->prepare(
		"SELECT DATE(created) as date,
		        SUM(CASE WHEN is_first_post = 1 THEN 1 ELSE 0 END) as topics,
		        SUM(CASE WHEN is_first_post = 0 THEN 1 ELSE 0 END) as replies
		 FROM " . WPF()->tables->posts . "
		 WHERE created BETWEEN %s AND %s
		 AND status = 0
		 GROUP BY DATE(created)
		 ORDER BY date ASC",
		$start_date, $end_date
	), ARRAY_A );

	// Fill in missing dates with zeros
	$time_series = array();
	$current     = strtotime( date( 'Y-m-d', strtotime( $start_date ) ) );
	$end         = strtotime( date( 'Y-m-d', strtotime( $end_date ) ) );

	// Create a lookup for results
	$data_lookup = array();
	foreach ( $results as $row ) {
		$data_lookup[ $row['date'] ] = $row;
	}

	// Fill in all dates
	while ( $current <= $end ) {
		$date_str = date( 'Y-m-d', $current );
		if ( isset( $data_lookup[ $date_str ] ) ) {
			$time_series[] = array(
				'date'    => $date_str,
				'topics'  => (int) $data_lookup[ $date_str ]['topics'],
				'replies' => (int) $data_lookup[ $date_str ]['replies'],
			);
		} else {
			$time_series[] = array(
				'date'    => $date_str,
				'topics'  => 0,
				'replies' => 0,
			);
		}
		$current = strtotime( '+1 day', $current );
	}

	return $time_series;
}

/**
 * Get activity heatmap data (day of week vs hour)
 *
 * @param string $start_date Start date
 * @param string $end_date   End date
 * @return array Heatmap data [day][hour] => count
 */
function wpforo_ai_get_activity_heatmap( $start_date, $end_date ) {
	$results = WPF()->db->get_results( WPF()->db->prepare(
		"SELECT DAYOFWEEK(created) as day_of_week,
		        HOUR(created) as hour_of_day,
		        COUNT(*) as count
		 FROM " . WPF()->tables->posts . "
		 WHERE created BETWEEN %s AND %s
		 AND status = 0
		 GROUP BY DAYOFWEEK(created), HOUR(created)",
		$start_date, $end_date
	), ARRAY_A );

	// Initialize heatmap (7 days x 24 hours)
	$heatmap = array();
	for ( $day = 1; $day <= 7; $day++ ) {
		$heatmap[ $day ] = array_fill( 0, 24, 0 );
	}

	// Fill in values
	$max_count = 0;
	foreach ( $results as $row ) {
		$day   = (int) $row['day_of_week'];
		$hour  = (int) $row['hour_of_day'];
		$count = (int) $row['count'];
		$heatmap[ $day ][ $hour ] = $count;
		$max_count = max( $max_count, $count );
	}

	return array(
		'data'      => $heatmap,
		'max_count' => $max_count,
	);
}

/**
 * Get forum activity breakdown
 *
 * @param string $start_date Start date
 * @param string $end_date   End date
 * @return array Forum stats
 */
function wpforo_ai_get_forum_breakdown( $start_date, $end_date ) {
	$results = WPF()->db->get_results( WPF()->db->prepare(
		"SELECT f.forumid,
		        f.title,
		        COUNT(DISTINCT CASE WHEN p.is_first_post = 1 THEN p.topicid END) as topics,
		        SUM(CASE WHEN p.is_first_post = 0 THEN 1 ELSE 0 END) as replies,
		        COUNT(DISTINCT p.userid) as active_users,
		        COALESCE(SUM(t.views), 0) as views
		 FROM " . WPF()->tables->forums . " f
		 LEFT JOIN " . WPF()->tables->posts . " p ON f.forumid = p.forumid
		        AND p.created BETWEEN %s AND %s
		        AND p.status = 0
		 LEFT JOIN " . WPF()->tables->topics . " t ON p.topicid = t.topicid
		        AND p.is_first_post = 1
		 WHERE f.is_cat = 0
		 GROUP BY f.forumid, f.title
		 HAVING topics > 0 OR replies > 0
		 ORDER BY (topics + replies) DESC",
		$start_date, $end_date
	), ARRAY_A );

	return $results ?: array();
}

/**
 * Render activity heatmap HTML
 *
 * @param array $heatmap_data Heatmap data from wpforo_ai_get_activity_heatmap()
 */
function wpforo_ai_render_activity_heatmap( $heatmap_data ) {
	$days = array(
		1 => __( 'Sun', 'wpforo' ),
		2 => __( 'Mon', 'wpforo' ),
		3 => __( 'Tue', 'wpforo' ),
		4 => __( 'Wed', 'wpforo' ),
		5 => __( 'Thu', 'wpforo' ),
		6 => __( 'Fri', 'wpforo' ),
		7 => __( 'Sat', 'wpforo' ),
	);

	$data      = $heatmap_data['data'];
	$max_count = max( 1, $heatmap_data['max_count'] ); // Avoid division by zero
	?>
	<div class="wpforo-ai-heatmap">
		<div class="wpforo-ai-heatmap-header">
			<div class="wpforo-ai-heatmap-day-label"></div>
			<?php for ( $hour = 0; $hour < 24; $hour += 3 ) : ?>
				<div class="wpforo-ai-heatmap-hour-label"><?php echo sprintf( '%02d:00', $hour ); ?></div>
			<?php endfor; ?>
		</div>
		<?php foreach ( $days as $day_num => $day_name ) : ?>
			<div class="wpforo-ai-heatmap-row">
				<div class="wpforo-ai-heatmap-day-label"><?php echo esc_html( $day_name ); ?></div>
				<div class="wpforo-ai-heatmap-cells">
					<?php for ( $hour = 0; $hour < 24; $hour++ ) :
						$count     = isset( $data[ $day_num ][ $hour ] ) ? $data[ $day_num ][ $hour ] : 0;
						$intensity = $max_count > 0 ? ( $count / $max_count ) : 0;
						$opacity   = 0.1 + ( $intensity * 0.9 ); // Range from 0.1 to 1.0
						$bg_color  = $count > 0 ? "rgba(76, 175, 80, {$opacity})" : 'rgba(0, 0, 0, 0.05)';
						?>
						<div class="wpforo-ai-heatmap-cell"
						     style="background-color: <?php echo esc_attr( $bg_color ); ?>"
						     title="<?php echo esc_attr( sprintf( __( '%s %d:00 - %d posts', 'wpforo' ), $day_name, $hour, $count ) ); ?>">
						</div>
					<?php endfor; ?>
				</div>
			</div>
		<?php endforeach; ?>
		<div class="wpforo-ai-heatmap-legend">
			<span class="wpforo-ai-heatmap-legend-label"><?php _e( 'Less', 'wpforo' ); ?></span>
			<div class="wpforo-ai-heatmap-legend-scale">
				<?php for ( $i = 0; $i <= 4; $i++ ) :
					$opacity = 0.1 + ( $i * 0.225 );
					?>
					<div class="wpforo-ai-heatmap-legend-cell" style="background-color: rgba(76, 175, 80, <?php echo $opacity; ?>)"></div>
				<?php endfor; ?>
			</div>
			<span class="wpforo-ai-heatmap-legend-label"><?php _e( 'More', 'wpforo' ); ?></span>
		</div>
	</div>
	<?php
}

/**
 * Render User Engagement Analytics sub-tab
 *
 * FREE feature - all data comes from local wpForo/WordPress database.
 *
 * @param int $board_id Current board ID
 */
function wpforo_ai_render_user_engagement_subtab( $board_id ) {
	// Time range presets
	$time_ranges = array(
		'today'     => __( 'Today', 'wpforo' ),
		'7days'     => __( 'Last 7 Days', 'wpforo' ),
		'30days'    => __( 'Last 30 Days', 'wpforo' ),
		'90days'    => __( 'Last 90 Days', 'wpforo' ),
		'this_year' => __( 'This Year', 'wpforo' ),
		'all_time'  => __( 'All Time', 'wpforo' ),
		'custom'    => __( 'Custom Range', 'wpforo' ),
	);

	$current_range = isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : '30days';
	$custom_start  = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : '';
	$custom_end    = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : '';

	// Calculate time range
	$time_info = wpforo_ai_calculate_time_range( $current_range, $custom_start, $custom_end );

	// Get user engagement data
	$engagement_data = wpforo_ai_get_user_engagement_data( $board_id, $time_info['start'], $time_info['end'] );
	?>

	<!-- Time Range Selector -->
	<div class="wpforo-ai-box wpforo-ai-time-range-box">
		<div class="wpforo-ai-box-body">
			<div class="wpforo-ai-time-range-selector">
				<div class="wpforo-ai-time-range-label">
					<span class="dashicons dashicons-calendar-alt"></span>
					<?php _e( 'Time Range:', 'wpforo' ); ?>
				</div>
				<div class="wpforo-ai-time-range-buttons">
					<?php foreach ( $time_ranges as $range_key => $range_label ) :
						if ( $range_key === 'custom' ) continue;
						$range_url = add_query_arg( array(
							'page'    => 'wpforo-ai',
							'tab'     => 'analytics',
							'subtab'  => 'user_engagement',
							'boardid' => $board_id,
							'range'   => $range_key,
						), admin_url( 'admin.php' ) );
						$active_class = ( $current_range === $range_key ) ? 'active' : '';
						?>
						<a href="<?php echo esc_url( $range_url ); ?>"
						   class="wpforo-ai-time-range-btn <?php echo esc_attr( $active_class ); ?>"
						   data-range="<?php echo esc_attr( $range_key ); ?>">
							<?php echo esc_html( $range_label ); ?>
						</a>
					<?php endforeach; ?>
					<button type="button" class="wpforo-ai-time-range-btn wpforo-ai-custom-range-toggle <?php echo $current_range === 'custom' ? 'active' : ''; ?>">
						<?php _e( 'Custom', 'wpforo' ); ?>
					</button>
				</div>
				<div class="wpforo-ai-custom-range-picker" style="<?php echo $current_range === 'custom' ? '' : 'display: none;'; ?>">
					<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
						<input type="hidden" name="page" value="wpforo-ai">
						<input type="hidden" name="tab" value="analytics">
						<input type="hidden" name="subtab" value="user_engagement">
						<input type="hidden" name="boardid" value="<?php echo esc_attr( $board_id ); ?>">
						<input type="hidden" name="range" value="custom">
						<label>
							<?php _e( 'From:', 'wpforo' ); ?>
							<input type="date" name="start_date" value="<?php echo esc_attr( $custom_start ); ?>" required>
						</label>
						<label>
							<?php _e( 'To:', 'wpforo' ); ?>
							<input type="date" name="end_date" value="<?php echo esc_attr( $custom_end ); ?>" required>
						</label>
						<button type="submit" class="button button-primary">
							<?php _e( 'Apply', 'wpforo' ); ?>
						</button>
					</form>
				</div>
				<div class="wpforo-ai-time-range-info">
					<span class="wpforo-ai-date-range-display">
						<?php echo esc_html( $time_info['display'] ); ?>
					</span>
				</div>
			</div>
		</div>
	</div>

	<!-- Summary Cards -->
	<div class="wpforo-ai-analytics-summary-cards">
		<div class="wpforo-ai-analytics-card">
			<div class="wpforo-ai-analytics-card-icon">
				<span class="dashicons dashicons-admin-users"></span>
			</div>
			<div class="wpforo-ai-analytics-card-content">
				<div class="wpforo-ai-analytics-card-value">
					<?php echo number_format( $engagement_data['summary']['new_members'] ); ?>
				</div>
				<div class="wpforo-ai-analytics-card-label"><?php _e( 'New Members', 'wpforo' ); ?></div>
			</div>
		</div>
		<div class="wpforo-ai-analytics-card">
			<div class="wpforo-ai-analytics-card-icon">
				<span class="dashicons dashicons-groups"></span>
			</div>
			<div class="wpforo-ai-analytics-card-content">
				<div class="wpforo-ai-analytics-card-value">
					<?php echo number_format( $engagement_data['summary']['active_members'] ); ?>
				</div>
				<div class="wpforo-ai-analytics-card-label"><?php _e( 'Active Members', 'wpforo' ); ?></div>
			</div>
		</div>
		<div class="wpforo-ai-analytics-card">
			<div class="wpforo-ai-analytics-card-icon">
				<span class="dashicons dashicons-chart-line"></span>
			</div>
			<div class="wpforo-ai-analytics-card-content">
				<div class="wpforo-ai-analytics-card-value">
					<?php echo number_format( $engagement_data['summary']['engagement_rate'], 1 ); ?>%
				</div>
				<div class="wpforo-ai-analytics-card-label"><?php _e( 'Engagement Rate', 'wpforo' ); ?></div>
			</div>
		</div>
		<div class="wpforo-ai-analytics-card">
			<div class="wpforo-ai-analytics-card-icon">
				<span class="dashicons dashicons-star-filled"></span>
			</div>
			<div class="wpforo-ai-analytics-card-content">
				<div class="wpforo-ai-analytics-card-value wpforo-ai-card-value-small">
					<?php echo esc_html( $engagement_data['summary']['top_contributor_name'] ?: '-' ); ?>
				</div>
				<div class="wpforo-ai-analytics-card-label"><?php _e( 'Top Contributor', 'wpforo' ); ?></div>
			</div>
		</div>
	</div>

	<!-- Charts Row 1: Registrations Over Time -->
	<div class="wpforo-ai-analytics-charts-row">
		<div class="wpforo-ai-box wpforo-ai-chart-box wpforo-ai-chart-wide">
			<div class="wpforo-ai-box-header">
				<h3>
					<span class="dashicons dashicons-chart-line"></span>
					<?php _e( 'New Registrations Over Time', 'wpforo' ); ?>
				</h3>
			</div>
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-chart-container">
					<canvas id="registrations-chart"></canvas>
				</div>
			</div>
		</div>
	</div>

	<!-- Charts Row 2: Top Contributors and Activity Distribution -->
	<div class="wpforo-ai-analytics-charts-row">
		<!-- Top Contributors -->
		<div class="wpforo-ai-box wpforo-ai-chart-box">
			<div class="wpforo-ai-box-header">
				<h3>
					<span class="dashicons dashicons-awards"></span>
					<?php _e( 'Top Contributors', 'wpforo' ); ?>
				</h3>
			</div>
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-top-contributors">
					<?php if ( ! empty( $engagement_data['top_contributors'] ) ) : ?>
						<div class="wpforo-ai-contributor-header">
							<div class="wpforo-ai-contributor-rank-header">#</div>
							<div class="wpforo-ai-contributor-avatar-header"></div>
							<div class="wpforo-ai-contributor-name-header"><?php _e( 'User', 'wpforo' ); ?></div>
							<div class="wpforo-ai-contributor-group-header"><?php _e( 'Group', 'wpforo' ); ?></div>
							<div class="wpforo-ai-contributor-topics-header"><?php _e( 'Topics', 'wpforo' ); ?></div>
							<div class="wpforo-ai-contributor-posts-header"><?php _e( 'Posts', 'wpforo' ); ?></div>
						</div>
						<?php foreach ( $engagement_data['top_contributors'] as $index => $contributor ) :
							$profile_url = WPF()->member->get_profile_url( $contributor['userid'] );
							?>
							<div class="wpforo-ai-contributor-row">
								<div class="wpforo-ai-contributor-rank"><?php echo $index + 1; ?></div>
								<div class="wpforo-ai-contributor-avatar-col">
									<?php echo get_avatar( $contributor['userid'], 32, '', '', array( 'class' => 'wpforo-ai-contributor-avatar' ) ); ?>
								</div>
								<div class="wpforo-ai-contributor-name">
									<a href="<?php echo esc_url( $profile_url ); ?>" target="_blank" rel="noopener">
										<?php echo esc_html( $contributor['display_name'] ); ?>
									</a>
								</div>
								<div class="wpforo-ai-contributor-group">
									<span class="wpforo-ai-usergroup-badge" style="color: <?php echo esc_attr( $contributor['usergroup_color'] ); ?>">
										<?php echo esc_html( $contributor['usergroup_name'] ); ?>
									</span>
								</div>
								<div class="wpforo-ai-contributor-topics"><?php echo number_format( $contributor['topics'] ); ?></div>
								<div class="wpforo-ai-contributor-posts"><?php echo number_format( $contributor['replies'] ); ?></div>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<div class="wpforo-ai-no-data"><?php _e( 'No contributors in this period', 'wpforo' ); ?></div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- User Activity Distribution -->
		<div class="wpforo-ai-box wpforo-ai-chart-box">
			<div class="wpforo-ai-box-header">
				<h3>
					<span class="dashicons dashicons-chart-pie"></span>
					<?php _e( 'User Activity Distribution', 'wpforo' ); ?>
				</h3>
			</div>
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-chart-container wpforo-ai-chart-pie-container">
					<canvas id="user-distribution-chart"></canvas>
				</div>
			</div>
		</div>
	</div>

	<?php
	// Output JavaScript data for charts
	$chart_data = array(
		'registrations'  => $engagement_data['registrations_time_series'],
		'distribution'   => $engagement_data['activity_distribution'],
		'i18n'           => array(
			'registrations' => __( 'New Registrations', 'wpforo' ),
			'powerUsers'    => __( 'Power Users (50+ posts)', 'wpforo' ),
			'activeUsers'   => __( 'Active Users (10-49 posts)', 'wpforo' ),
			'occasional'    => __( 'Occasional (2-9 posts)', 'wpforo' ),
			'oneTime'       => __( 'One-time (1 post)', 'wpforo' ),
			'lurkers'       => __( 'Lurkers (0 posts)', 'wpforo' ),
		),
	);
	?>
	<script type="text/javascript">
		var wpforoUserEngagement = <?php echo wp_json_encode( $chart_data ); ?>;
	</script>
	<?php
}

/**
 * Get user engagement data from database
 *
 * @param int $board_id   Board ID
 * @param int $start_time Start timestamp
 * @param int $end_time   End timestamp
 * @return array Engagement data
 */
function wpforo_ai_get_user_engagement_data( $board_id, $start_time, $end_time ) {
	$start_date = date( 'Y-m-d H:i:s', $start_time );
	$end_date   = date( 'Y-m-d H:i:s', $end_time );

	// Get summary stats
	$summary = wpforo_ai_get_engagement_summary( $start_date, $end_date );

	// Get registrations time series
	$registrations = wpforo_ai_get_registrations_time_series( $start_date, $end_date );

	// Get top contributors
	$contributors = wpforo_ai_get_top_contributors( $start_date, $end_date );

	// Get user activity distribution
	$distribution = wpforo_ai_get_user_activity_distribution( $start_date, $end_date );

	// Get recent registrations
	$recent = wpforo_ai_get_recent_registrations( $start_date, $end_date );

	return array(
		'summary'                   => $summary,
		'registrations_time_series' => $registrations,
		'top_contributors'          => $contributors,
		'activity_distribution'     => $distribution,
		'recent_registrations'      => $recent,
	);
}

/**
 * Get engagement summary statistics
 *
 * @param string $start_date Start date
 * @param string $end_date   End date
 * @return array Summary stats
 */
function wpforo_ai_get_engagement_summary( $start_date, $end_date ) {
	global $wpdb;

	// New members registered in period
	$new_members = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->users}
		 WHERE user_registered BETWEEN %s AND %s",
		$start_date, $end_date
	) );

	// Active members (users who posted in period)
	$active_members = (int) WPF()->db->get_var( WPF()->db->prepare(
		"SELECT COUNT(DISTINCT userid) FROM " . WPF()->tables->posts . "
		 WHERE created BETWEEN %s AND %s
		 AND userid > 0
		 AND status = 0",
		$start_date, $end_date
	) );

	// Total registered users
	$total_users = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );

	// Engagement rate (active / total)
	$engagement_rate = $total_users > 0 ? ( $active_members / $total_users ) * 100 : 0;

	// Top contributor
	$top_contributor = WPF()->db->get_row( WPF()->db->prepare(
		"SELECT p.userid, u.display_name, COUNT(*) as posts
		 FROM " . WPF()->tables->posts . " p
		 JOIN {$wpdb->users} u ON p.userid = u.ID
		 WHERE p.created BETWEEN %s AND %s
		 AND p.userid > 0
		 AND p.status = 0
		 GROUP BY p.userid
		 ORDER BY posts DESC
		 LIMIT 1",
		$start_date, $end_date
	), ARRAY_A );

	return array(
		'new_members'           => $new_members,
		'active_members'        => $active_members,
		'engagement_rate'       => $engagement_rate,
		'top_contributor_name'  => $top_contributor ? $top_contributor['display_name'] : '',
		'top_contributor_posts' => $top_contributor ? (int) $top_contributor['posts'] : 0,
	);
}

/**
 * Get registrations time series
 *
 * @param string $start_date Start date
 * @param string $end_date   End date
 * @return array Time series data
 */
function wpforo_ai_get_registrations_time_series( $start_date, $end_date ) {
	global $wpdb;

	$results = $wpdb->get_results( $wpdb->prepare(
		"SELECT DATE(user_registered) as date, COUNT(*) as count
		 FROM {$wpdb->users}
		 WHERE user_registered BETWEEN %s AND %s
		 GROUP BY DATE(user_registered)
		 ORDER BY date ASC",
		$start_date, $end_date
	), ARRAY_A );

	// Fill in missing dates with zeros
	$time_series = array();
	$current     = strtotime( date( 'Y-m-d', strtotime( $start_date ) ) );
	$end         = strtotime( date( 'Y-m-d', strtotime( $end_date ) ) );

	$data_lookup = array();
	foreach ( $results as $row ) {
		$data_lookup[ $row['date'] ] = (int) $row['count'];
	}

	while ( $current <= $end ) {
		$date_str      = date( 'Y-m-d', $current );
		$time_series[] = array(
			'date'  => $date_str,
			'count' => isset( $data_lookup[ $date_str ] ) ? $data_lookup[ $date_str ] : 0,
		);
		$current = strtotime( '+1 day', $current );
	}

	return $time_series;
}

/**
 * Get top contributors
 *
 * @param string $start_date Start date
 * @param string $end_date   End date
 * @return array Top contributors
 */
function wpforo_ai_get_top_contributors( $start_date, $end_date ) {
	global $wpdb;

	$results = WPF()->db->get_results( WPF()->db->prepare(
		"SELECT p.userid,
		        u.display_name,
		        SUM(CASE WHEN p.is_first_post = 1 THEN 1 ELSE 0 END) as topics,
		        SUM(CASE WHEN p.is_first_post = 0 THEN 1 ELSE 0 END) as replies,
		        COUNT(*) as total_posts,
		        pr.groupid
		 FROM " . WPF()->tables->posts . " p
		 JOIN {$wpdb->users} u ON p.userid = u.ID
		 LEFT JOIN " . WPF()->tables->profiles . " pr ON p.userid = pr.userid
		 WHERE p.created BETWEEN %s AND %s
		 AND p.userid > 0
		 AND p.status = 0
		 GROUP BY p.userid, u.display_name, pr.groupid
		 ORDER BY total_posts DESC
		 LIMIT 10",
		$start_date, $end_date
	), ARRAY_A );

	// Add usergroup names
	if ( $results ) {
		foreach ( $results as &$row ) {
			$groupid = (int) $row['groupid'];
			$usergroup = WPF()->usergroup->get_usergroup( $groupid );
			$row['usergroup_name'] = $usergroup ? $usergroup['name'] : '';
			$row['usergroup_color'] = $usergroup && ! empty( $usergroup['color'] ) ? $usergroup['color'] : '#666';
		}
	}

	return $results ?: array();
}

/**
 * Get user activity distribution
 *
 * @param string $start_date Start date
 * @param string $end_date   End date
 * @return array Distribution data
 */
function wpforo_ai_get_user_activity_distribution( $start_date, $end_date ) {
	global $wpdb;

	// Get post counts for all users
	$user_posts = WPF()->db->get_results( WPF()->db->prepare(
		"SELECT userid, COUNT(*) as posts
		 FROM " . WPF()->tables->posts . "
		 WHERE created BETWEEN %s AND %s
		 AND userid > 0
		 AND status = 0
		 GROUP BY userid",
		$start_date, $end_date
	), ARRAY_A );

	// Count users in each category
	$power_users   = 0; // 50+ posts
	$active_users  = 0; // 10-49 posts
	$occasional    = 0; // 2-9 posts
	$one_time      = 0; // 1 post

	foreach ( $user_posts as $user ) {
		$posts = (int) $user['posts'];
		if ( $posts >= 50 ) {
			$power_users++;
		} elseif ( $posts >= 10 ) {
			$active_users++;
		} elseif ( $posts >= 2 ) {
			$occasional++;
		} else {
			$one_time++;
		}
	}

	// Count lurkers (users registered in period who haven't posted)
	$registered_users = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->users}
		 WHERE user_registered BETWEEN %s AND %s",
		$start_date, $end_date
	) );

	$posting_users = count( $user_posts );
	$lurkers       = max( 0, (int) $registered_users - $posting_users );

	return array(
		'power_users'  => $power_users,
		'active_users' => $active_users,
		'occasional'   => $occasional,
		'one_time'     => $one_time,
		'lurkers'      => $lurkers,
	);
}

/**
 * Get recent registrations with post info
 *
 * @param string $start_date Start date
 * @param string $end_date   End date
 * @return array Recent registrations
 */
function wpforo_ai_get_recent_registrations( $start_date, $end_date ) {
	global $wpdb;

	$users = $wpdb->get_results( $wpdb->prepare(
		"SELECT ID, display_name, user_registered
		 FROM {$wpdb->users}
		 WHERE user_registered BETWEEN %s AND %s
		 ORDER BY user_registered DESC
		 LIMIT 20",
		$start_date, $end_date
	), ARRAY_A );

	$result = array();
	foreach ( $users as $user ) {
		// Get post count and first post date for this user
		$post_info = WPF()->db->get_row( WPF()->db->prepare(
			"SELECT COUNT(*) as posts, MIN(created) as first_post
			 FROM " . WPF()->tables->posts . "
			 WHERE userid = %d AND status = 0",
			$user['ID']
		), ARRAY_A );

		$result[] = array(
			'userid'          => $user['ID'],
			'display_name'    => $user['display_name'],
			'registered_date' => wpforo_ai_format_date( $user['user_registered'] ),
			'posts'           => $post_info ? (int) $post_info['posts'] : 0,
			'first_post_date' => ( $post_info && $post_info['first_post'] ) ? wpforo_ai_format_date( $post_info['first_post'] ) : null,
		);
	}

	return $result;
}

/**
 * Render Content Performance Analytics sub-tab
 *
 * FREE feature - all data comes from local wpForo database.
 *
 * @param int $board_id Current board ID
 */
function wpforo_ai_render_content_performance_subtab( $board_id ) {
	// Time range presets
	$time_ranges = array(
		'today'     => __( 'Today', 'wpforo' ),
		'7days'     => __( 'Last 7 Days', 'wpforo' ),
		'30days'    => __( 'Last 30 Days', 'wpforo' ),
		'90days'    => __( 'Last 90 Days', 'wpforo' ),
		'this_year' => __( 'This Year', 'wpforo' ),
		'all_time'  => __( 'All Time', 'wpforo' ),
		'custom'    => __( 'Custom Range', 'wpforo' ),
	);

	$current_range = isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : '30days';
	$custom_start  = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : '';
	$custom_end    = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : '';

	// Calculate time range
	$time_info = wpforo_ai_calculate_time_range( $current_range, $custom_start, $custom_end );

	// Get content performance data
	$content_data = wpforo_ai_get_content_performance_data( $board_id, $time_info['start'], $time_info['end'] );
	?>

	<!-- Time Range Selector -->
	<div class="wpforo-ai-box wpforo-ai-time-range-box">
		<div class="wpforo-ai-box-body">
			<div class="wpforo-ai-time-range-selector">
				<div class="wpforo-ai-time-range-label">
					<span class="dashicons dashicons-calendar-alt"></span>
					<?php _e( 'Time Range:', 'wpforo' ); ?>
				</div>
				<div class="wpforo-ai-time-range-buttons">
					<?php foreach ( $time_ranges as $range_key => $range_label ) :
						if ( $range_key === 'custom' ) continue;
						$range_url = add_query_arg( array(
							'page'    => 'wpforo-ai',
							'tab'     => 'analytics',
							'subtab'  => 'content',
							'boardid' => $board_id,
							'range'   => $range_key,
						), admin_url( 'admin.php' ) );
						$active_class = ( $current_range === $range_key ) ? 'active' : '';
						?>
						<a href="<?php echo esc_url( $range_url ); ?>"
						   class="wpforo-ai-time-range-btn <?php echo esc_attr( $active_class ); ?>"
						   data-range="<?php echo esc_attr( $range_key ); ?>">
							<?php echo esc_html( $range_label ); ?>
						</a>
					<?php endforeach; ?>
					<button type="button" class="wpforo-ai-time-range-btn wpforo-ai-custom-range-toggle <?php echo $current_range === 'custom' ? 'active' : ''; ?>">
						<?php _e( 'Custom', 'wpforo' ); ?>
					</button>
				</div>
				<div class="wpforo-ai-custom-range-picker" style="<?php echo $current_range === 'custom' ? '' : 'display: none;'; ?>">
					<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
						<input type="hidden" name="page" value="wpforo-ai">
						<input type="hidden" name="tab" value="analytics">
						<input type="hidden" name="subtab" value="content">
						<input type="hidden" name="boardid" value="<?php echo esc_attr( $board_id ); ?>">
						<input type="hidden" name="range" value="custom">
						<label>
							<?php _e( 'From:', 'wpforo' ); ?>
							<input type="date" name="start_date" value="<?php echo esc_attr( $custom_start ); ?>" required>
						</label>
						<label>
							<?php _e( 'To:', 'wpforo' ); ?>
							<input type="date" name="end_date" value="<?php echo esc_attr( $custom_end ); ?>" required>
						</label>
						<button type="submit" class="button button-primary">
							<?php _e( 'Apply', 'wpforo' ); ?>
						</button>
					</form>
				</div>
				<div class="wpforo-ai-time-range-info">
					<span class="wpforo-ai-date-range-display">
						<?php echo esc_html( $time_info['display'] ); ?>
					</span>
				</div>
			</div>
		</div>
	</div>

	<!-- Summary Cards -->
	<div class="wpforo-ai-analytics-summary-cards">
		<div class="wpforo-ai-analytics-card">
			<div class="wpforo-ai-analytics-card-icon">
				<span class="dashicons dashicons-visibility"></span>
			</div>
			<div class="wpforo-ai-analytics-card-content">
				<div class="wpforo-ai-analytics-card-value">
					<?php echo number_format( $content_data['summary']['total_views'] ); ?>
				</div>
				<div class="wpforo-ai-analytics-card-label"><?php _e( 'Total Views', 'wpforo' ); ?></div>
			</div>
		</div>
		<div class="wpforo-ai-analytics-card">
			<div class="wpforo-ai-analytics-card-icon">
				<span class="dashicons dashicons-format-chat"></span>
			</div>
			<div class="wpforo-ai-analytics-card-content">
				<div class="wpforo-ai-analytics-card-value">
					<?php echo number_format( $content_data['summary']['total_topics'] ); ?>
				</div>
				<div class="wpforo-ai-analytics-card-label"><?php _e( 'New Topics', 'wpforo' ); ?></div>
			</div>
		</div>
		<div class="wpforo-ai-analytics-card">
			<div class="wpforo-ai-analytics-card-icon">
				<span class="dashicons dashicons-warning"></span>
			</div>
			<div class="wpforo-ai-analytics-card-content">
				<div class="wpforo-ai-analytics-card-value">
					<?php echo number_format( $content_data['summary']['unanswered_count'] ); ?>
				</div>
				<div class="wpforo-ai-analytics-card-label"><?php _e( 'Unanswered', 'wpforo' ); ?></div>
			</div>
		</div>
		<div class="wpforo-ai-analytics-card">
			<div class="wpforo-ai-analytics-card-icon">
				<span class="dashicons dashicons-yes-alt"></span>
			</div>
			<div class="wpforo-ai-analytics-card-content">
				<div class="wpforo-ai-analytics-card-value">
					<?php echo number_format( $content_data['summary']['solved_rate'], 1 ); ?>%
				</div>
				<div class="wpforo-ai-analytics-card-label"><?php _e( 'Solved Rate', 'wpforo' ); ?></div>
			</div>
		</div>
	</div>

	<!-- Charts Row 1: Most Viewed and Most Discussed -->
	<div class="wpforo-ai-analytics-charts-row">
		<!-- Most Viewed Topics -->
		<div class="wpforo-ai-box wpforo-ai-chart-box">
			<div class="wpforo-ai-box-header">
				<h3><?php _e( 'Most Viewed Topics', 'wpforo' ); ?></h3>
			</div>
			<div class="wpforo-ai-box-body">
				<?php if ( ! empty( $content_data['most_viewed'] ) ) : ?>
					<div class="wpforo-ai-content-perf-list">
						<div class="wpforo-ai-content-perf-header">
							<span class="wpforo-ai-content-perf-col-rank">#</span>
							<span class="wpforo-ai-content-perf-col-title"><?php _e( 'Topic', 'wpforo' ); ?></span>
							<span class="wpforo-ai-content-perf-col-views"><?php _e( 'Views', 'wpforo' ); ?></span>
							<span class="wpforo-ai-content-perf-col-replies"><?php _e( 'Replies', 'wpforo' ); ?></span>
						</div>
						<?php foreach ( $content_data['most_viewed'] as $index => $topic ) :
							$topic_url = WPF()->topic->get_url( $topic['topicid'] );
							?>
							<div class="wpforo-ai-content-perf-row">
								<span class="wpforo-ai-content-perf-col-rank"><?php echo $index + 1; ?></span>
								<span class="wpforo-ai-content-perf-col-title">
									<a href="<?php echo esc_url( $topic_url ); ?>" target="_blank" rel="noopener">
										<?php echo esc_html( wp_trim_words( $topic['title'], 10 ) ); ?>
									</a>
									<small><?php echo esc_html( $topic['forum_title'] ); ?></small>
								</span>
								<span class="wpforo-ai-content-perf-col-views"><?php echo number_format( $topic['views'] ); ?></span>
								<span class="wpforo-ai-content-perf-col-replies"><?php echo number_format( $topic['replies'] ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="wpforo-ai-no-data"><?php _e( 'No topics in this period', 'wpforo' ); ?></div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Most Discussed Topics -->
		<div class="wpforo-ai-box wpforo-ai-chart-box">
			<div class="wpforo-ai-box-header">
				<h3><?php _e( 'Most Discussed Topics', 'wpforo' ); ?></h3>
			</div>
			<div class="wpforo-ai-box-body">
				<?php if ( ! empty( $content_data['most_discussed'] ) ) : ?>
					<div class="wpforo-ai-content-perf-list">
						<div class="wpforo-ai-content-perf-header">
							<span class="wpforo-ai-content-perf-col-rank">#</span>
							<span class="wpforo-ai-content-perf-col-title"><?php _e( 'Topic', 'wpforo' ); ?></span>
							<span class="wpforo-ai-content-perf-col-replies"><?php _e( 'Replies', 'wpforo' ); ?></span>
							<span class="wpforo-ai-content-perf-col-views"><?php _e( 'Views', 'wpforo' ); ?></span>
						</div>
						<?php foreach ( $content_data['most_discussed'] as $index => $topic ) :
							$topic_url = WPF()->topic->get_url( $topic['topicid'] );
							?>
							<div class="wpforo-ai-content-perf-row">
								<span class="wpforo-ai-content-perf-col-rank"><?php echo $index + 1; ?></span>
								<span class="wpforo-ai-content-perf-col-title">
									<a href="<?php echo esc_url( $topic_url ); ?>" target="_blank" rel="noopener">
										<?php echo esc_html( wp_trim_words( $topic['title'], 10 ) ); ?>
									</a>
									<small><?php echo esc_html( $topic['forum_title'] ); ?></small>
								</span>
								<span class="wpforo-ai-content-perf-col-replies"><?php echo number_format( $topic['replies'] ); ?></span>
								<span class="wpforo-ai-content-perf-col-views"><?php echo number_format( $topic['views'] ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="wpforo-ai-no-data"><?php _e( 'No topics in this period', 'wpforo' ); ?></div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- Content by Forum Distribution -->
	<div class="wpforo-ai-box wpforo-ai-usage-table-box">
		<div class="wpforo-ai-box-header">
			<h3><?php _e( 'Content by Forum', 'wpforo' ); ?></h3>
		</div>
		<div class="wpforo-ai-box-body">
			<div class="wpforo-ai-chart-container wpforo-ai-chart-pie-container">
				<canvas id="content-distribution-chart"></canvas>
			</div>
		</div>
	</div>

	<!-- Popular Tags -->
	<div class="wpforo-ai-box wpforo-ai-usage-table-box">
		<div class="wpforo-ai-box-header">
			<h3><?php _e( 'Popular Tags', 'wpforo' ); ?></h3>
		</div>
		<div class="wpforo-ai-box-body">
			<?php if ( ! empty( $content_data['popular_tags'] ) ) : ?>
				<div class="wpforo-ai-tag-cloud">
					<?php
					$max_count  = max( array_column( $content_data['popular_tags'], 'count' ) );
					$forum_url  = wpforo_home_url();
					foreach ( $content_data['popular_tags'] as $tag ) :
						$size    = 0.8 + ( ( $tag['count'] / $max_count ) * 0.8 );
						$tag_url = add_query_arg( array(
							'wpfin' => 'tag',
							'wpfs'  => $tag['tag'],
						), $forum_url );
						?>
						<a href="<?php echo esc_url( $tag_url ); ?>" class="wpforo-ai-tag" target="_blank" rel="noopener" style="font-size: <?php echo $size; ?>em;">
							<?php echo esc_html( $tag['tag'] ); ?>
							<span class="wpforo-ai-tag-count"><?php echo number_format( $tag['count'] ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="wpforo-ai-no-data"><?php _e( 'No tags found', 'wpforo' ); ?></div>
			<?php endif; ?>
		</div>
	</div>

	<?php
	// Output JavaScript data for charts
	$chart_data = array(
		'forumDistribution' => $content_data['forum_distribution'],
		'i18n'              => array(
			'topics' => __( 'Topics', 'wpforo' ),
		),
	);
	?>
	<script type="text/javascript">
		var wpforoContentPerformance = <?php echo wp_json_encode( $chart_data ); ?>;
	</script>
	<?php
}

/**
 * Get content performance data from database
 *
 * @param int $board_id   Board ID
 * @param int $start_time Start timestamp
 * @param int $end_time   End timestamp
 * @return array Content performance data
 */
function wpforo_ai_get_content_performance_data( $board_id, $start_time, $end_time ) {
	$start_date = date( 'Y-m-d H:i:s', $start_time );
	$end_date   = date( 'Y-m-d H:i:s', $end_time );

	// Get summary stats
	$summary = wpforo_ai_get_content_summary( $start_date, $end_date );

	// Get most viewed topics
	$most_viewed = wpforo_ai_get_most_viewed_topics( $start_date, $end_date );

	// Get most discussed topics
	$most_discussed = wpforo_ai_get_most_discussed_topics( $start_date, $end_date );

	// Get unanswered topics
	$unanswered = wpforo_ai_get_unanswered_topics( $start_date, $end_date );

	// Get forum distribution
	$forum_distribution = wpforo_ai_get_forum_distribution( $start_date, $end_date );

	// Get popular tags
	$popular_tags = wpforo_ai_get_popular_tags( $start_date, $end_date );

	return array(
		'summary'            => $summary,
		'most_viewed'        => $most_viewed,
		'most_discussed'     => $most_discussed,
		'unanswered'         => $unanswered,
		'forum_distribution' => $forum_distribution,
		'popular_tags'       => $popular_tags,
	);
}

/**
 * Get content summary statistics
 *
 * @param string $start_date Start date
 * @param string $end_date   End date
 * @return array Summary stats
 */
function wpforo_ai_get_content_summary( $start_date, $end_date ) {
	// Total views of topics created in period
	$total_views = (int) WPF()->db->get_var( WPF()->db->prepare(
		"SELECT COALESCE(SUM(views), 0) FROM " . WPF()->tables->topics . "
		 WHERE created BETWEEN %s AND %s
		 AND status = 0",
		$start_date, $end_date
	) );

	// Total topics created
	$total_topics = (int) WPF()->db->get_var( WPF()->db->prepare(
		"SELECT COUNT(*) FROM " . WPF()->tables->topics . "
		 WHERE created BETWEEN %s AND %s
		 AND status = 0",
		$start_date, $end_date
	) );

	// Unanswered topics (created in period with only 1 post - the original)
	$unanswered_count = (int) WPF()->db->get_var( WPF()->db->prepare(
		"SELECT COUNT(*) FROM " . WPF()->tables->topics . "
		 WHERE created BETWEEN %s AND %s
		 AND posts = 1
		 AND status = 0",
		$start_date, $end_date
	) );

	// Solved topics (if solved field exists)
	$solved_count = (int) WPF()->db->get_var( WPF()->db->prepare(
		"SELECT COUNT(*) FROM " . WPF()->tables->topics . "
		 WHERE created BETWEEN %s AND %s
		 AND solved = 1
		 AND status = 0",
		$start_date, $end_date
	) );

	// Calculate solved rate
	$solved_rate = $total_topics > 0 ? ( $solved_count / $total_topics ) * 100 : 0;

	return array(
		'total_views'      => $total_views,
		'total_topics'     => $total_topics,
		'unanswered_count' => $unanswered_count,
		'solved_rate'      => $solved_rate,
	);
}

/**
 * Get most viewed topics
 *
 * @param string $start_date Start date
 * @param string $end_date   End date
 * @return array Most viewed topics
 */
function wpforo_ai_get_most_viewed_topics( $start_date, $end_date ) {
	$results = WPF()->db->get_results( WPF()->db->prepare(
		"SELECT t.topicid, t.title, t.views, (t.posts - 1) as replies, f.title as forum_title
		 FROM " . WPF()->tables->topics . " t
		 LEFT JOIN " . WPF()->tables->forums . " f ON t.forumid = f.forumid
		 WHERE t.created BETWEEN %s AND %s
		 AND t.status = 0
		 ORDER BY t.views DESC
		 LIMIT 10",
		$start_date, $end_date
	), ARRAY_A );

	return $results ?: array();
}

/**
 * Get most discussed topics
 *
 * @param string $start_date Start date
 * @param string $end_date   End date
 * @return array Most discussed topics
 */
function wpforo_ai_get_most_discussed_topics( $start_date, $end_date ) {
	$results = WPF()->db->get_results( WPF()->db->prepare(
		"SELECT t.topicid, t.title, t.views, (t.posts - 1) as replies, f.title as forum_title
		 FROM " . WPF()->tables->topics . " t
		 LEFT JOIN " . WPF()->tables->forums . " f ON t.forumid = f.forumid
		 WHERE t.created BETWEEN %s AND %s
		 AND t.status = 0
		 ORDER BY t.posts DESC
		 LIMIT 10",
		$start_date, $end_date
	), ARRAY_A );

	return $results ?: array();
}

/**
 * Get unanswered topics
 *
 * @param string $start_date Start date
 * @param string $end_date   End date
 * @return array Unanswered topics
 */
function wpforo_ai_get_unanswered_topics( $start_date, $end_date ) {
	$results = WPF()->db->get_results( WPF()->db->prepare(
		"SELECT t.topicid, t.title, t.created, f.title as forum_title
		 FROM " . WPF()->tables->topics . " t
		 LEFT JOIN " . WPF()->tables->forums . " f ON t.forumid = f.forumid
		 WHERE t.created BETWEEN %s AND %s
		 AND t.posts = 1
		 AND t.status = 0
		 ORDER BY t.created DESC
		 LIMIT 15",
		$start_date, $end_date
	), ARRAY_A );

	return $results ?: array();
}

/**
 * Get forum distribution for pie chart
 *
 * @param string $start_date Start date
 * @param string $end_date   End date
 * @return array Forum distribution
 */
function wpforo_ai_get_forum_distribution( $start_date, $end_date ) {
	$results = WPF()->db->get_results( WPF()->db->prepare(
		"SELECT f.forumid, f.title, COUNT(t.topicid) as topics
		 FROM " . WPF()->tables->forums . " f
		 LEFT JOIN " . WPF()->tables->topics . " t ON f.forumid = t.forumid
		        AND t.created BETWEEN %s AND %s
		        AND t.status = 0
		 WHERE f.is_cat = 0
		 GROUP BY f.forumid, f.title
		 HAVING topics > 0
		 ORDER BY topics DESC
		 LIMIT 8",
		$start_date, $end_date
	), ARRAY_A );

	return $results ?: array();
}

/**
 * Get popular tags
 *
 * @param string $start_date Start date
 * @param string $end_date   End date
 * @return array Popular tags
 */
function wpforo_ai_get_popular_tags( $start_date, $end_date ) {
	// Get topics with tags in the period
	$topics_with_tags = WPF()->db->get_results( WPF()->db->prepare(
		"SELECT tags FROM " . WPF()->tables->topics . "
		 WHERE created BETWEEN %s AND %s
		 AND tags IS NOT NULL AND tags != ''
		 AND status = 0",
		$start_date, $end_date
	), ARRAY_A );

	// Count tag occurrences
	$tag_counts = array();
	foreach ( $topics_with_tags as $topic ) {
		$tags = maybe_unserialize( $topic['tags'] );
		if ( is_array( $tags ) ) {
			foreach ( $tags as $tag ) {
				$tag = trim( $tag );
				if ( $tag ) {
					if ( ! isset( $tag_counts[ $tag ] ) ) {
						$tag_counts[ $tag ] = 0;
					}
					$tag_counts[ $tag ]++;
				}
			}
		} elseif ( is_string( $tags ) && $tags ) {
			// Handle comma-separated tags
			$tag_array = explode( ',', $tags );
			foreach ( $tag_array as $tag ) {
				$tag = trim( $tag );
				if ( $tag ) {
					if ( ! isset( $tag_counts[ $tag ] ) ) {
						$tag_counts[ $tag ] = 0;
					}
					$tag_counts[ $tag ]++;
				}
			}
		}
	}

	// Sort by count and limit
	arsort( $tag_counts );
	$tag_counts = array_slice( $tag_counts, 0, 30, true );

	// Format for output
	$result = array();
	foreach ( $tag_counts as $tag => $count ) {
		$result[] = array(
			'tag'   => $tag,
			'count' => $count,
		);
	}

	return $result;
}

/**
 * Render AI-Powered Insights sub-tab
 *
 * Premium features that use credits for AI analysis:
 * - Sentiment Analysis: Analyze community mood from recent posts
 * - Trending Topics: AI-detected emerging discussions
 * - Growth Recommendations: AI-generated actionable suggestions
 *
 * @param int   $board_id Current board ID
 * @param array $status   Tenant status data
 */
function wpforo_ai_render_ai_insights_subtab( $board_id, $status ) {
	// Get available credits
	$subscription       = isset( $status['subscription'] ) && is_array( $status['subscription'] ) ? $status['subscription'] : [];
	$credits_remaining  = isset( $subscription['credits_remaining'] ) ? (int) $subscription['credits_remaining'] : 0;
	$credits_total      = isset( $subscription['credits_total'] ) ? (int) $subscription['credits_total'] : 0;

	// Define insight types with their credit costs
	// Top row: 2 column grid (half width each)
	$insight_types_top = array(
		'sentiment'       => array(
			'label'       => __( 'Community Sentiment', 'wpforo' ),
			'description' => __( 'Analyze mood with different emotion categories: happy, excited, frustrated, angry...', 'wpforo' ),
			'period'      => __( 'Based on last 200 posts', 'wpforo' ),
			'credits'     => 2,
			'icon'        => 'chart-pie',
		),
		'trending'        => array(
			'label'       => __( 'Trending Topics', 'wpforo' ),
			'description' => __( 'Discover emerging discussions and hot topics in your forum using AI pattern detection.', 'wpforo' ),
			'period'      => __( 'Last 30 days', 'wpforo' ),
			'credits'     => 1,
			'icon'        => 'chart-line',
		),
	);

	// Full-width sections below
	$insight_types_full = array(
		'deep_analysis'   => array(
			'label'       => __( 'Community Deep Dive', 'wpforo' ),
			'description' => __( 'Comprehensive analysis of user engagement patterns, content metrics, keyword sentiment hotspots, top contributors mood, and activity patterns.', 'wpforo' ),
			'period'      => __( 'Last 30 days', 'wpforo' ),
			'credits'     => 5,
			'icon'        => 'analytics',
			'full_width'  => true,
		),
		'sentiment_trend' => array(
			'label'       => __( 'Sentiment Trend Over Time', 'wpforo' ),
			'description' => __( 'Track how community mood has changed over time with visualization of sentiment shifts, key events, and mood forecasting.', 'wpforo' ),
			'period'      => __( 'Last 30 days', 'wpforo' ),
			'credits'     => 4,
			'icon'        => 'chart-area',
			'full_width'  => true,
		),
		'recommendations' => array(
			'label'       => __( 'Growth Recommendations', 'wpforo' ),
			'description' => __( 'Get AI-generated actionable moderation suggestions based on your forum\'s data. Available once per day.', 'wpforo' ),
			'period'      => __( 'All-time statistics with recent activity', 'wpforo' ),
			'credits'     => 1,
			'icon'        => 'lightbulb',
			'full_width'  => true,
			'daily_limit' => true,
		),
	);

	// Combined for cache lookup
	$insight_types = array_merge( $insight_types_top, $insight_types_full );

	// Check for cached results
	$cached_insights = wpforo_ai_get_cached_insights( $board_id );
	?>

	<div class="wpforo-ai-insights-container">

		<!-- Top Row Insights Grid (3 columns) -->
		<div class="wpforo-ai-insights-grid wpforo-ai-insights-grid-top">

			<?php foreach ( $insight_types_top as $type => $insight ) :
				$cached_data = isset( $cached_insights[ $type ] ) ? $cached_insights[ $type ] : null;
				$has_cached  = ! empty( $cached_data ) && isset( $cached_data['data'] );
				$cached_time = $has_cached && isset( $cached_data['timestamp'] ) ? human_time_diff( $cached_data['timestamp'], time() ) : '';
				$is_outdated = $has_cached && isset( $cached_data['timestamp'] ) && wpforo_ai_is_insight_outdated( $cached_data['timestamp'] );
				?>

				<!-- <?php echo esc_html( $insight['label'] ); ?> Widget -->
				<div class="wpforo-ai-box wpforo-ai-insights-widget" data-insight-type="<?php echo esc_attr( $type ); ?>">
					<div class="wpforo-ai-box-header">
						<h3>
							<span class="dashicons dashicons-<?php echo esc_attr( $insight['icon'] ); ?>"></span>
							<?php echo esc_html( $insight['label'] ); ?>
						</h3>
						<span class="wpforo-ai-insights-credits-cost">
							<?php echo esc_html( $insight['credits'] ); ?> <?php _e( 'credits', 'wpforo' ); ?>
						</span>
					</div>
					<div class="wpforo-ai-box-body">
						<p class="wpforo-ai-insights-description"><?php echo esc_html( $insight['description'] ); ?></p>
						<?php if ( ! empty( $insight['period'] ) ) : ?>
							<p class="wpforo-ai-insights-period">
								<span class="dashicons dashicons-calendar-alt"></span>
								<?php echo esc_html( $insight['period'] ); ?>
							</p>
						<?php endif; ?>

						<!-- Results Container (hidden until analysis is run) -->
						<div class="wpforo-ai-insights-results" id="wpforo-ai-insight-results-<?php echo esc_attr( $type ); ?>" style="<?php echo $has_cached ? '' : 'display: none;'; ?>">
							<?php if ( $has_cached ) : ?>
								<?php wpforo_ai_render_insight_results( $type, $cached_data['data'] ); ?>
								<div class="wpforo-ai-insights-cached-notice">
									<span class="dashicons dashicons-clock"></span>
									<?php
									/* translators: %s: time ago */
									printf( __( 'Cached %s ago', 'wpforo' ), $cached_time );
									?>
								</div>
							<?php endif; ?>
						</div>

						<!-- Loading State -->
						<div class="wpforo-ai-insights-loading" style="display: none;">
							<span class="spinner is-active"></span>
							<span><?php _e( 'Analyzing...', 'wpforo' ); ?></span>
						</div>

						<!-- Error Message -->
						<div class="wpforo-ai-insights-error" style="display: none;"></div>

						<!-- Action Button -->
						<div class="wpforo-ai-insights-actions">
							<button type="button"
								class="button button-primary wpforo-ai-run-insight-btn"
								data-insight-type="<?php echo esc_attr( $type ); ?>"
								data-credits="<?php echo esc_attr( $insight['credits'] ); ?>"
								<?php echo $credits_remaining < $insight['credits'] ? 'disabled' : ''; ?>>
								<?php if ( $has_cached ) : ?>
									<span class="dashicons dashicons-update"></span>
									<?php _e( 'Refresh Analysis', 'wpforo' ); ?>
								<?php else : ?>
									<span class="dashicons dashicons-yes"></span>
									<?php _e( 'Run Analysis', 'wpforo' ); ?>
								<?php endif; ?>
							</button>
							<?php if ( $is_outdated ) : ?>
								<span class="wpforo-ai-insights-outdated-notice">
									<span class="dashicons dashicons-warning"></span>
									<?php _e( 'Data may be outdated. Refresh for current information.', 'wpforo' ); ?>
								</span>
							<?php endif; ?>
							<?php if ( $credits_remaining < $insight['credits'] ) : ?>
								<span class="wpforo-ai-insights-insufficient">
									<?php _e( 'Insufficient credits', 'wpforo' ); ?>
								</span>
							<?php endif; ?>
						</div>
					</div>
				</div>

			<?php endforeach; ?>

		</div>

		<!-- Full-Width Insight Sections -->
		<?php foreach ( $insight_types_full as $type => $insight ) :
			$cached_data = isset( $cached_insights[ $type ] ) ? $cached_insights[ $type ] : null;
			$has_cached  = ! empty( $cached_data ) && isset( $cached_data['data'] );
			$cached_time = $has_cached && isset( $cached_data['timestamp'] ) ? human_time_diff( $cached_data['timestamp'], time() ) : '';
			$is_outdated = $has_cached && isset( $cached_data['timestamp'] ) && wpforo_ai_is_insight_outdated( $cached_data['timestamp'] );

			// Check for daily limit
			$has_daily_limit  = isset( $insight['daily_limit'] ) && $insight['daily_limit'];
			$used_today       = false;
			if ( $has_daily_limit && $has_cached && isset( $cached_data['timestamp'] ) ) {
				// Check if cached result is from today
				$cached_date = date( 'Y-m-d', $cached_data['timestamp'] );
				$today_date  = date( 'Y-m-d', current_time( 'timestamp' ) );
				$used_today  = ( $cached_date === $today_date );
			}
			?>

			<!-- <?php echo esc_html( $insight['label'] ); ?> Full-Width Section -->
			<div class="wpforo-ai-box wpforo-ai-insights-widget wpforo-ai-insights-widget-full" data-insight-type="<?php echo esc_attr( $type ); ?>">
				<div class="wpforo-ai-box-header">
					<h3>
						<span class="dashicons dashicons-<?php echo esc_attr( $insight['icon'] ); ?>"></span>
						<?php echo esc_html( $insight['label'] ); ?>
					</h3>
					<span class="wpforo-ai-insights-credits-cost wpforo-ai-insights-credits-cost-premium">
						<span class="dashicons dashicons-star-filled"></span>
						<?php echo esc_html( $insight['credits'] ); ?> <?php _e( 'credits', 'wpforo' ); ?>
						<?php if ( $has_daily_limit ) : ?>
							<span class="wpforo-ai-daily-limit-badge"><?php _e( 'Can be refreshed one time per day', 'wpforo' ); ?></span>
						<?php endif; ?>
					</span>
				</div>
				<div class="wpforo-ai-box-body">
					<p class="wpforo-ai-insights-description"><?php echo esc_html( $insight['description'] ); ?></p>
					<?php if ( ! empty( $insight['period'] ) ) : ?>
						<p class="wpforo-ai-insights-period">
							<span class="dashicons dashicons-calendar-alt"></span>
							<?php echo esc_html( $insight['period'] ); ?>
						</p>
					<?php endif; ?>

					<!-- Results Container (hidden until analysis is run) -->
					<div class="wpforo-ai-insights-results wpforo-ai-insights-results-full" id="wpforo-ai-insight-results-<?php echo esc_attr( $type ); ?>" style="<?php echo $has_cached ? '' : 'display: none;'; ?>">
						<?php if ( $has_cached ) : ?>
							<?php wpforo_ai_render_insight_results( $type, $cached_data['data'] ); ?>
							<div class="wpforo-ai-insights-cached-notice">
								<span class="dashicons dashicons-clock"></span>
								<?php
								/* translators: %s: time ago */
								printf( __( 'Cached %s ago', 'wpforo' ), $cached_time );
								?>
							</div>
						<?php endif; ?>
					</div>

					<!-- Loading State -->
					<div class="wpforo-ai-insights-loading" style="display: none;">
						<span class="spinner is-active"></span>
						<span><?php _e( 'Analyzing... This may take a moment for comprehensive analysis.', 'wpforo' ); ?></span>
					</div>

					<!-- Error Message -->
					<div class="wpforo-ai-insights-error" style="display: none;"></div>

					<!-- Action Button -->
					<div class="wpforo-ai-insights-actions">
						<?php
						$btn_disabled = $credits_remaining < $insight['credits'] || $used_today;
						?>
						<button type="button"
							class="button button-primary wpforo-ai-run-insight-btn"
							data-insight-type="<?php echo esc_attr( $type ); ?>"
							data-credits="<?php echo esc_attr( $insight['credits'] ); ?>"
							<?php echo $btn_disabled ? 'disabled' : ''; ?>>
							<?php if ( $has_cached ) : ?>
								<span class="dashicons dashicons-update"></span>
								<?php _e( 'Refresh Analysis', 'wpforo' ); ?>
							<?php else : ?>
								<span class="dashicons dashicons-yes"></span>
								<?php _e( 'Run Analysis', 'wpforo' ); ?>
							<?php endif; ?>
						</button>
						<?php if ( $is_outdated ) : ?>
							<span class="wpforo-ai-insights-outdated-notice">
								<span class="dashicons dashicons-warning"></span>
								<?php _e( 'Data may be outdated. Refresh for current information.', 'wpforo' ); ?>
							</span>
						<?php endif; ?>
						<?php if ( $used_today ) : ?>
							<span class="wpforo-ai-insights-daily-used">
								<span class="dashicons dashicons-calendar-alt"></span>
								<?php _e( 'Available again tomorrow', 'wpforo' ); ?>
							</span>
						<?php elseif ( $credits_remaining < $insight['credits'] ) : ?>
							<span class="wpforo-ai-insights-insufficient">
								<?php _e( 'Insufficient credits', 'wpforo' ); ?>
							</span>
						<?php endif; ?>
					</div>
				</div>
			</div>

		<?php endforeach; ?>

		<!-- Additional Info Section -->
		<div class="wpforo-ai-box wpforo-ai-insights-info-box">
			<div class="wpforo-ai-box-body">
				<h4><span class="dashicons dashicons-info"></span> <?php _e( 'About AI Insights', 'wpforo' ); ?></h4>
				<ul class="wpforo-ai-insights-info-list">
					<li><?php _e( 'AI insights analyze your recent forum posts and topics to generate actionable intelligence.', 'wpforo' ); ?></li>
					<li><?php _e( 'Results are stored permanently until you click "Refresh" to get updated analysis. A warning will appear after 15 days to indicate data may be outdated.', 'wpforo' ); ?></li>
					<li><?php _e( 'The analysis considers the last 100-500 posts depending on forum activity level.', 'wpforo' ); ?></li>
				</ul>
			</div>
		</div>

	</div>

	<?php
	// Output JavaScript data
	$js_data = array(
		'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
		'nonce'            => wp_create_nonce( 'wpforo_ai_insights_nonce' ),
		'boardId'          => $board_id,
		'creditsRemaining' => $credits_remaining,
		'i18n'             => array(
			'confirmTitle'    => __( 'Run AI Analysis', 'wpforo' ),
			'confirmMessage'  => __( 'This will use %d credit(s) from your balance.', 'wpforo' ),
			'confirmButton'   => __( 'Run Analysis', 'wpforo' ),
			'cancelButton'    => __( 'Cancel', 'wpforo' ),
			'analyzing'       => __( 'Analyzing...', 'wpforo' ),
			'error'           => __( 'An error occurred. Please try again.', 'wpforo' ),
			'insufficientCredits' => __( 'Insufficient credits to run this analysis.', 'wpforo' ),
			'cachedJustNow'   => __( 'Just now', 'wpforo' ),
		),
	);
	?>
	<script type="text/javascript">
		var wpforoAIInsights = <?php echo wp_json_encode( $js_data ); ?>;
	</script>
	<?php
}

/**
 * Render insight results based on type
 *
 * @param string $type Insight type
 * @param array  $data Result data
 */
function wpforo_ai_render_insight_results( $type, $data ) {
	switch ( $type ) {
		case 'sentiment':
			wpforo_ai_render_sentiment_results( $data );
			break;
		case 'trending':
			wpforo_ai_render_trending_results( $data );
			break;
		case 'recommendations':
			wpforo_ai_render_recommendations_results( $data );
			break;
		case 'deep_analysis':
			wpforo_ai_render_deep_analysis_results( $data );
			break;
		case 'sentiment_trend':
			wpforo_ai_render_sentiment_trend_results( $data );
			break;
	}
}

/**
 * Render sentiment analysis results with 7 emotion categories
 *
 * @param array $data Sentiment data
 */
function wpforo_ai_render_sentiment_results( $data ) {
	// Define 7 emotion categories with their display properties
	$emotions = array(
		'happy'      => array( 'emoji' => '😊', 'label' => __( 'Happy', 'wpforo' ), 'color' => '#4CAF50' ),
		'excited'    => array( 'emoji' => '🎉', 'label' => __( 'Excited', 'wpforo' ), 'color' => '#8BC34A' ),
		'neutral'    => array( 'emoji' => '😐', 'label' => __( 'Neutral', 'wpforo' ), 'color' => '#9E9E9E' ),
		'confused'   => array( 'emoji' => '🤔', 'label' => __( 'Confused', 'wpforo' ), 'color' => '#FF9800' ),
		'frustrated' => array( 'emoji' => '😤', 'label' => __( 'Frustrated', 'wpforo' ), 'color' => '#FF5722' ),
		'angry'      => array( 'emoji' => '😠', 'label' => __( 'Angry', 'wpforo' ), 'color' => '#F44336' ),
		'sad'        => array( 'emoji' => '😢', 'label' => __( 'Sad', 'wpforo' ), 'color' => '#2196F3' ),
	);

	// Backwards compatibility: if old format (positive/neutral/negative), convert
	if ( isset( $data['positive'] ) && ! isset( $data['happy'] ) ) {
		$data['happy']      = isset( $data['positive'] ) ? (int) $data['positive'] : 0;
		$data['neutral']    = isset( $data['neutral'] ) ? (int) $data['neutral'] : 0;
		$data['angry']      = isset( $data['negative'] ) ? (int) $data['negative'] : 0;
		$data['excited']    = 0;
		$data['confused']   = 0;
		$data['frustrated'] = 0;
		$data['sad']        = 0;
	}

	$summary = isset( $data['summary'] ) ? sanitize_text_field( $data['summary'] ) : '';
	?>
	<div class="wpforo-ai-sentiment-results wpforo-ai-sentiment-results-7">
		<div class="wpforo-ai-sentiment-bars">
			<?php foreach ( $emotions as $key => $emotion ) :
				$value = isset( $data[ $key ] ) ? (int) $data[ $key ] : 0;
				if ( $value > 0 ) : // Only show emotions with values > 0
				?>
				<div class="wpforo-ai-sentiment-bar <?php echo esc_attr( $key ); ?>">
					<span class="wpforo-ai-sentiment-emoji"><?php echo esc_html( $emotion['emoji'] ); ?></span>
					<span class="wpforo-ai-sentiment-label"><?php echo esc_html( $emotion['label'] ); ?></span>
					<div class="wpforo-ai-sentiment-bar-track">
						<div class="wpforo-ai-sentiment-bar-fill" style="width: <?php echo esc_attr( $value ); ?>%; background-color: <?php echo esc_attr( $emotion['color'] ); ?>;"></div>
					</div>
					<span class="wpforo-ai-sentiment-percent"><?php echo esc_html( $value ); ?>%</span>
				</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php if ( $summary ) : ?>
			<div class="wpforo-ai-sentiment-summary">
				<p><?php echo esc_html( $summary ); ?></p>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render trending topics results
 *
 * @param array $data Trending data
 */
function wpforo_ai_render_trending_results( $data ) {
	$topics  = isset( $data['topics'] ) && is_array( $data['topics'] ) ? $data['topics'] : [];
	$summary = isset( $data['summary'] ) ? sanitize_text_field( $data['summary'] ) : '';
	?>
	<div class="wpforo-ai-trending-results">
		<?php if ( ! empty( $topics ) ) : ?>
			<ol class="wpforo-ai-trending-list">
				<?php foreach ( $topics as $index => $topic ) :
					$topic_title = isset( $topic['title'] ) ? $topic['title'] : ( is_string( $topic ) ? $topic : '' );
					$topic_heat  = isset( $topic['heat'] ) ? (int) $topic['heat'] : 0;
					?>
					<li class="wpforo-ai-trending-item">
						<span class="wpforo-ai-trending-rank"><?php echo $index + 1; ?></span>
						<span class="wpforo-ai-trending-title"><?php echo esc_html( $topic_title ); ?></span>
						<?php if ( $topic_heat > 0 ) : ?>
							<span class="wpforo-ai-trending-heat" title="<?php esc_attr_e( 'Heat score', 'wpforo' ); ?>">
								🔥 <?php echo esc_html( $topic_heat ); ?>
							</span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php else : ?>
			<p class="wpforo-ai-no-data"><?php _e( 'No trending topics detected.', 'wpforo' ); ?></p>
		<?php endif; ?>
		<?php if ( $summary ) : ?>
			<div class="wpforo-ai-trending-summary">
				<p><?php echo esc_html( $summary ); ?></p>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render growth recommendations results
 *
 * @param array $data Recommendations data
 */
function wpforo_ai_render_recommendations_results( $data ) {
	$recommendations = isset( $data['recommendations'] ) && is_array( $data['recommendations'] ) ? $data['recommendations'] : [];
	$summary         = isset( $data['summary'] ) ? sanitize_text_field( $data['summary'] ) : '';
	?>
	<div class="wpforo-ai-recommendations-results">
		<?php if ( ! empty( $recommendations ) ) : ?>
			<ul class="wpforo-ai-recommendations-list">
				<?php foreach ( $recommendations as $rec ) :
					$rec_title    = isset( $rec['title'] ) ? $rec['title'] : ( is_string( $rec ) ? $rec : '' );
					$rec_desc     = isset( $rec['description'] ) ? $rec['description'] : '';
					$rec_priority = isset( $rec['priority'] ) ? strtolower( $rec['priority'] ) : 'medium';
					?>
					<li class="wpforo-ai-recommendation-item priority-<?php echo esc_attr( $rec_priority ); ?>">
						<div class="wpforo-ai-recommendation-icon">
							<span class="dashicons dashicons-lightbulb"></span>
						</div>
						<div class="wpforo-ai-recommendation-content">
							<strong><?php echo esc_html( $rec_title ); ?></strong>
							<?php if ( $rec_desc ) : ?>
								<p><?php echo esc_html( $rec_desc ); ?></p>
							<?php endif; ?>
						</div>
						<?php if ( $rec_priority !== 'medium' ) : ?>
							<span class="wpforo-ai-recommendation-priority">
								<?php echo esc_html( ucfirst( $rec_priority ) ); ?>
							</span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="wpforo-ai-no-data"><?php _e( 'No recommendations available.', 'wpforo' ); ?></p>
		<?php endif; ?>
		<?php if ( $summary ) : ?>
			<div class="wpforo-ai-recommendations-summary">
				<p><?php echo esc_html( $summary ); ?></p>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render deep analysis results (Community Deep Dive)
 *
 * @param array $data Deep analysis data
 */
function wpforo_ai_render_deep_analysis_results( $data ) {
	$user_engagement     = isset( $data['user_engagement'] ) ? $data['user_engagement'] : array();
	$content_metrics     = isset( $data['content_metrics'] ) ? $data['content_metrics'] : array();
	$keyword_hotspots    = isset( $data['keyword_hotspots'] ) && is_array( $data['keyword_hotspots'] ) ? $data['keyword_hotspots'] : array();
	$contributors_mood   = isset( $data['top_contributors_mood'] ) ? $data['top_contributors_mood'] : array();
	$activity_patterns   = isset( $data['activity_patterns'] ) ? $data['activity_patterns'] : array();
	$overall_summary     = isset( $data['overall_summary'] ) ? sanitize_text_field( $data['overall_summary'] ) : '';
	?>
	<div class="wpforo-ai-deep-analysis-results">
		<!-- User Engagement Section -->
		<div class="wpforo-ai-deep-section">
			<h4><span class="dashicons dashicons-groups"></span> <?php _e( 'User Engagement', 'wpforo' ); ?></h4>
			<div class="wpforo-ai-deep-metrics">
				<div class="wpforo-ai-metric">
					<span class="wpforo-ai-metric-value"><?php echo esc_html( number_format( floatval( $user_engagement['avg_replies_per_user'] ?? 0 ), 1 ) ); ?></span>
					<span class="wpforo-ai-metric-label"><?php _e( 'Avg Replies/User', 'wpforo' ); ?></span>
				</div>
				<div class="wpforo-ai-metric">
					<span class="wpforo-ai-metric-value"><?php echo esc_html( intval( $user_engagement['active_users_percent'] ?? 0 ) ); ?>%</span>
					<span class="wpforo-ai-metric-label"><?php _e( 'Active Users', 'wpforo' ); ?></span>
				</div>
				<div class="wpforo-ai-metric">
					<span class="wpforo-ai-metric-value"><?php echo esc_html( number_format( floatval( $user_engagement['avg_response_time_hours'] ?? 0 ), 1 ) ); ?>h</span>
					<span class="wpforo-ai-metric-label"><?php _e( 'Avg Response Time', 'wpforo' ); ?></span>
				</div>
			</div>
			<?php if ( ! empty( $user_engagement['top_repliers'] ) ) : ?>
				<div class="wpforo-ai-top-repliers">
					<strong><?php _e( 'Top Repliers:', 'wpforo' ); ?></strong>
					<?php foreach ( array_slice( $user_engagement['top_repliers'], 0, 5 ) as $replier ) : ?>
						<span class="wpforo-ai-replier-badge sentiment-<?php echo esc_attr( strtolower( $replier['sentiment'] ?? 'neutral' ) ); ?>">
							<?php echo esc_html( $replier['username'] ?? 'Unknown' ); ?> (<?php echo esc_html( $replier['reply_count'] ?? 0 ); ?>)
						</span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $user_engagement['summary'] ) ) : ?>
				<p class="wpforo-ai-section-summary"><?php echo esc_html( $user_engagement['summary'] ); ?></p>
			<?php endif; ?>
		</div>

		<!-- Content Metrics Section -->
		<div class="wpforo-ai-deep-section">
			<h4><span class="dashicons dashicons-media-document"></span> <?php _e( 'Content Metrics', 'wpforo' ); ?></h4>
			<div class="wpforo-ai-deep-metrics">
				<div class="wpforo-ai-metric">
					<span class="wpforo-ai-metric-value"><?php echo esc_html( intval( $content_metrics['avg_topic_length_words'] ?? 0 ) ); ?></span>
					<span class="wpforo-ai-metric-label"><?php _e( 'Avg Topic Length', 'wpforo' ); ?></span>
				</div>
				<div class="wpforo-ai-metric">
					<span class="wpforo-ai-metric-value"><?php echo esc_html( intval( $content_metrics['avg_reply_length_words'] ?? 0 ) ); ?></span>
					<span class="wpforo-ai-metric-label"><?php _e( 'Avg Reply Length', 'wpforo' ); ?></span>
				</div>
				<div class="wpforo-ai-metric">
					<span class="wpforo-ai-metric-value"><?php echo esc_html( intval( $content_metrics['detailed_discussions_percent'] ?? 0 ) ); ?>%</span>
					<span class="wpforo-ai-metric-label"><?php _e( 'Detailed Discussions', 'wpforo' ); ?></span>
				</div>
			</div>
			<?php if ( ! empty( $content_metrics['summary'] ) ) : ?>
				<p class="wpforo-ai-section-summary"><?php echo esc_html( $content_metrics['summary'] ); ?></p>
			<?php endif; ?>
		</div>

		<!-- Keyword Hotspots Section -->
		<?php if ( ! empty( $keyword_hotspots ) ) : ?>
		<div class="wpforo-ai-deep-section">
			<h4><span class="dashicons dashicons-tag"></span> <?php _e( 'Keyword Sentiment Hotspots', 'wpforo' ); ?></h4>
			<div class="wpforo-ai-keyword-hotspots">
				<?php foreach ( array_slice( $keyword_hotspots, 0, 10 ) as $hotspot ) :
					$heat = isset( $hotspot['heat'] ) ? intval( $hotspot['heat'] ) : 50;
					$sentiment = strtolower( $hotspot['sentiment'] ?? 'neutral' );
					$sentiment_colors = array(
						'positive' => '#4CAF50',
						'negative' => '#F44336',
						'mixed'    => '#FF9800',
						'neutral'  => '#9E9E9E',
					);
					$color = isset( $sentiment_colors[ $sentiment ] ) ? $sentiment_colors[ $sentiment ] : '#9E9E9E';
					?>
					<div class="wpforo-ai-keyword-tag" style="border-color: <?php echo esc_attr( $color ); ?>; background: <?php echo esc_attr( $color ); ?>20;">
						<span class="wpforo-ai-keyword-text"><?php echo esc_html( $hotspot['keyword'] ?? '' ); ?></span>
						<span class="wpforo-ai-keyword-heat" style="background: <?php echo esc_attr( $color ); ?>;">
							<?php echo esc_html( $heat ); ?>
						</span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<!-- Top Contributors Mood Section -->
		<div class="wpforo-ai-deep-section">
			<h4><span class="dashicons dashicons-admin-users"></span> <?php _e( 'Top Contributors Mood', 'wpforo' ); ?></h4>
			<p class="wpforo-ai-section-note"><?php _e( 'Sentiment analysis of your most active community members (excluding admins/moderators).', 'wpforo' ); ?></p>
			<?php
			$overall_sentiment = strtolower( $contributors_mood['overall_sentiment'] ?? 'neutral' );
			$positive_pct = intval( $contributors_mood['positive_percent'] ?? 0 );
			$negative_pct = intval( $contributors_mood['negative_percent'] ?? 0 );
			$neutral_pct  = max( 0, 100 - $positive_pct - $negative_pct );
			?>
			<div class="wpforo-ai-contributors-mood-grid">
				<!-- Positive -->
				<div class="wpforo-ai-mood-stat positive">
					<div class="wpforo-ai-mood-stat-icon">😊</div>
					<div class="wpforo-ai-mood-stat-info">
						<div class="wpforo-ai-mood-stat-value"><?php echo esc_html( $positive_pct ); ?>%</div>
						<div class="wpforo-ai-mood-stat-label"><?php _e( 'Positive', 'wpforo' ); ?></div>
					</div>
					<div class="wpforo-ai-mood-stat-bar">
						<div class="wpforo-ai-mood-stat-bar-fill positive" style="width: <?php echo esc_attr( $positive_pct ); ?>%;"></div>
					</div>
				</div>
				<!-- Neutral -->
				<div class="wpforo-ai-mood-stat neutral">
					<div class="wpforo-ai-mood-stat-icon">😐</div>
					<div class="wpforo-ai-mood-stat-info">
						<div class="wpforo-ai-mood-stat-value"><?php echo esc_html( $neutral_pct ); ?>%</div>
						<div class="wpforo-ai-mood-stat-label"><?php _e( 'Neutral', 'wpforo' ); ?></div>
					</div>
					<div class="wpforo-ai-mood-stat-bar">
						<div class="wpforo-ai-mood-stat-bar-fill neutral" style="width: <?php echo esc_attr( $neutral_pct ); ?>%;"></div>
					</div>
				</div>
				<!-- Negative -->
				<div class="wpforo-ai-mood-stat negative">
					<div class="wpforo-ai-mood-stat-icon">😟</div>
					<div class="wpforo-ai-mood-stat-info">
						<div class="wpforo-ai-mood-stat-value"><?php echo esc_html( $negative_pct ); ?>%</div>
						<div class="wpforo-ai-mood-stat-label"><?php _e( 'Negative', 'wpforo' ); ?></div>
					</div>
					<div class="wpforo-ai-mood-stat-bar">
						<div class="wpforo-ai-mood-stat-bar-fill negative" style="width: <?php echo esc_attr( $negative_pct ); ?>%;"></div>
					</div>
				</div>
			</div>
			<?php if ( ! empty( $contributors_mood['influence_summary'] ) ) : ?>
				<div class="wpforo-ai-mood-influence">
					<strong><?php _e( 'Impact:', 'wpforo' ); ?></strong>
					<?php echo esc_html( $contributors_mood['influence_summary'] ); ?>
				</div>
			<?php endif; ?>
		</div>

		<!-- Activity Patterns Section -->
		<div class="wpforo-ai-deep-section">
			<h4><span class="dashicons dashicons-clock"></span> <?php _e( 'Activity Patterns', 'wpforo' ); ?></h4>
			<div class="wpforo-ai-activity-patterns">
				<?php if ( ! empty( $activity_patterns['peak_hours'] ) ) : ?>
					<div class="wpforo-ai-pattern-item">
						<span class="wpforo-ai-pattern-label"><?php _e( 'Peak Hours:', 'wpforo' ); ?></span>
						<span class="wpforo-ai-pattern-value"><?php echo esc_html( implode( ', ', (array) $activity_patterns['peak_hours'] ) ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $activity_patterns['peak_days'] ) ) : ?>
					<div class="wpforo-ai-pattern-item">
						<span class="wpforo-ai-pattern-label"><?php _e( 'Peak Days:', 'wpforo' ); ?></span>
						<span class="wpforo-ai-pattern-value"><?php echo esc_html( implode( ', ', (array) $activity_patterns['peak_days'] ) ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $activity_patterns['trend'] ) ) : ?>
					<div class="wpforo-ai-pattern-item">
						<span class="wpforo-ai-pattern-label"><?php _e( 'Trend:', 'wpforo' ); ?></span>
						<?php
						$trend = strtolower( $activity_patterns['trend'] );
						$trend_icon = '';
						$trend_class = '';
						if ( $trend === 'increasing' ) {
							$trend_icon = '📈';
							$trend_class = 'trend-up';
						} elseif ( $trend === 'decreasing' ) {
							$trend_icon = '📉';
							$trend_class = 'trend-down';
						} else {
							$trend_icon = '➡️';
							$trend_class = 'trend-stable';
						}
						?>
						<span class="wpforo-ai-pattern-value <?php echo esc_attr( $trend_class ); ?>">
							<?php echo esc_html( $trend_icon . ' ' . ucfirst( $trend ) ); ?>
						</span>
					</div>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $activity_patterns['summary'] ) ) : ?>
				<p class="wpforo-ai-section-summary"><?php echo esc_html( $activity_patterns['summary'] ); ?></p>
			<?php endif; ?>
		</div>

		<!-- Overall Summary -->
		<?php if ( $overall_summary ) : ?>
		<div class="wpforo-ai-deep-overall-summary">
			<p><?php echo esc_html( $overall_summary ); ?></p>
		</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render sentiment trend results (Sentiment Trend Over Time)
 *
 * @param array $data Sentiment trend data
 */
function wpforo_ai_render_sentiment_trend_results( $data ) {
	$trend_direction = isset( $data['trend_direction'] ) ? sanitize_text_field( $data['trend_direction'] ) : 'stable';
	$trend_strength  = isset( $data['trend_strength'] ) ? intval( $data['trend_strength'] ) : 5;
	$time_series     = isset( $data['time_series'] ) && is_array( $data['time_series'] ) ? $data['time_series'] : array();
	$mood_shifts     = isset( $data['mood_shifts'] ) && is_array( $data['mood_shifts'] ) ? $data['mood_shifts'] : array();
	$peaks           = isset( $data['peaks'] ) ? $data['peaks'] : array();
	$patterns        = isset( $data['patterns'] ) ? $data['patterns'] : array();
	$summary         = isset( $data['summary'] ) ? sanitize_text_field( $data['summary'] ) : '';
	$forecast        = isset( $data['forecast'] ) ? sanitize_text_field( $data['forecast'] ) : '';

	/**
	 * Format date from "2024-12-21" to "21st Dec 2024" format
	 *
	 * @param string $date_str Date string in Y-m-d format
	 * @param bool   $short    If true, returns short format like "21 Dec"
	 * @return string Formatted date
	 */
	$format_date = function( $date_str, $short = false ) {
		if ( empty( $date_str ) || $date_str === 'N/A' ) {
			return $date_str;
		}
		$timestamp = strtotime( $date_str );
		if ( ! $timestamp ) {
			return $date_str;
		}
		$day = date( 'j', $timestamp );
		// Add ordinal suffix (st, nd, rd, th)
		$suffix = 'th';
		if ( ! in_array( ( $day % 100 ), array( 11, 12, 13 ) ) ) {
			switch ( $day % 10 ) {
				case 1: $suffix = 'st'; break;
				case 2: $suffix = 'nd'; break;
				case 3: $suffix = 'rd'; break;
			}
		}
		if ( $short ) {
			return $day . ' ' . date( 'M', $timestamp );
		}
		return $day . $suffix . ' ' . date( 'M Y', $timestamp );
	};

	// Trend direction SVG icons and colors
	$trend_icons = array(
		'improving' => array(
			'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>',
			'color' => '#4CAF50',
			'label' => __( 'Improving', 'wpforo' ),
		),
		'declining' => array(
			'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"></polyline><polyline points="17 18 23 18 23 12"></polyline></svg>',
			'color' => '#F44336',
			'label' => __( 'Declining', 'wpforo' ),
		),
		'stable'    => array(
			'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>',
			'color' => '#9E9E9E',
			'label' => __( 'Stable', 'wpforo' ),
		),
		'volatile'  => array(
			'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>',
			'color' => '#FF9800',
			'label' => __( 'Volatile', 'wpforo' ),
		),
	);
	$trend_info = isset( $trend_icons[ $trend_direction ] ) ? $trend_icons[ $trend_direction ] : $trend_icons['stable'];
	?>
	<div class="wpforo-ai-sentiment-trend-results">
		<!-- Trend Overview -->
		<div class="wpforo-ai-trend-overview">
			<div class="wpforo-ai-trend-direction" style="border-color: <?php echo esc_attr( $trend_info['color'] ); ?>;">
				<span class="wpforo-ai-trend-icon" style="color: <?php echo esc_attr( $trend_info['color'] ); ?>;"><?php echo $trend_info['icon']; ?></span>
				<span class="wpforo-ai-trend-label"><?php echo esc_html( $trend_info['label'] ); ?></span>
				<div class="wpforo-ai-trend-strength">
					<span class="wpforo-ai-strength-label"><?php _e( 'Strength:', 'wpforo' ); ?></span>
					<div class="wpforo-ai-strength-bar">
						<div class="wpforo-ai-strength-fill" style="width: <?php echo esc_attr( $trend_strength * 10 ); ?>%; background: <?php echo esc_attr( $trend_info['color'] ); ?>;"></div>
					</div>
					<span class="wpforo-ai-strength-value"><?php echo esc_html( $trend_strength ); ?>/10</span>
				</div>
			</div>

			<!-- Peaks Info -->
			<?php if ( ! empty( $peaks ) ) : ?>
			<div class="wpforo-ai-trend-peaks">
				<?php if ( ! empty( $peaks['most_positive_period'] ) && $peaks['most_positive_period'] !== 'N/A' ) : ?>
					<div class="wpforo-ai-peak-item positive">
						<span class="wpforo-ai-peak-emoji">😊</span>
						<span class="wpforo-ai-peak-label"><?php _e( 'Most Positive:', 'wpforo' ); ?></span>
						<span class="wpforo-ai-peak-value"><?php echo esc_html( $format_date( $peaks['most_positive_period'] ) ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $peaks['most_negative_period'] ) && $peaks['most_negative_period'] !== 'N/A' ) : ?>
					<div class="wpforo-ai-peak-item negative">
						<span class="wpforo-ai-peak-emoji">😟</span>
						<span class="wpforo-ai-peak-label"><?php _e( 'Most Negative:', 'wpforo' ); ?></span>
						<span class="wpforo-ai-peak-value"><?php echo esc_html( $format_date( $peaks['most_negative_period'] ) ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $peaks['most_active_period'] ) && $peaks['most_active_period'] !== 'N/A' ) : ?>
					<div class="wpforo-ai-peak-item active">
						<span class="wpforo-ai-peak-emoji">🔥</span>
						<span class="wpforo-ai-peak-label"><?php _e( 'Most Active:', 'wpforo' ); ?></span>
						<span class="wpforo-ai-peak-value"><?php echo esc_html( $format_date( $peaks['most_active_period'] ) ); ?></span>
					</div>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>

		<!-- Time Series Chart -->
		<?php if ( ! empty( $time_series ) ) : ?>
		<div class="wpforo-ai-trend-chart-container">
			<h4><?php _e( 'Sentiment Over Time', 'wpforo' ); ?> <span class="wpforo-ai-chart-subtitle">(<?php _e( 'posts per day by dominant mood', 'wpforo' ); ?>)</span></h4>
			<div class="wpforo-ai-trend-chart" id="wpforo-sentiment-trend-chart">
				<?php
				$max_posts = max( array_column( $time_series, 'post_count' ) );
				$max_posts = max( 1, $max_posts ); // Avoid division by zero
				$emotion_colors = array(
					'happy'      => '#4CAF50',
					'excited'    => '#8BC34A',
					'neutral'    => '#9E9E9E',
					'confused'   => '#FF9800',
					'frustrated' => '#FF5722',
					'angry'      => '#F44336',
					'sad'        => '#2196F3',
				);
				$emotion_emojis = array(
					'happy'      => '😊',
					'excited'    => '🎉',
					'neutral'    => '😐',
					'confused'   => '🤔',
					'frustrated' => '😤',
					'angry'      => '😠',
					'sad'        => '😢',
				);
				?>
				<!-- Y-axis labels -->
				<div class="wpforo-ai-chart-y-axis">
					<span class="wpforo-ai-chart-y-label"><?php echo esc_html( $max_posts ); ?></span>
					<span class="wpforo-ai-chart-y-label"><?php echo esc_html( intval( $max_posts * 0.75 ) ); ?></span>
					<span class="wpforo-ai-chart-y-label"><?php echo esc_html( intval( $max_posts * 0.5 ) ); ?></span>
					<span class="wpforo-ai-chart-y-label"><?php echo esc_html( intval( $max_posts * 0.25 ) ); ?></span>
					<span class="wpforo-ai-chart-y-label">0</span>
				</div>
				<!-- Chart area with grid lines -->
				<div class="wpforo-ai-chart-area">
					<div class="wpforo-ai-chart-bars">
						<?php foreach ( $time_series as $period ) :
							$dominant = strtolower( $period['dominant_emotion'] ?? 'neutral' );
							$post_count = intval( $period['post_count'] ?? 0 );
							$bar_height = ( $post_count / $max_posts ) * 100;
							$color = isset( $emotion_colors[ $dominant ] ) ? $emotion_colors[ $dominant ] : '#9E9E9E';
							$emoji = isset( $emotion_emojis[ $dominant ] ) ? $emotion_emojis[ $dominant ] : '😐';
							$period_date = $period['period'] ?? '';
							$formatted_full = $format_date( $period_date );
							$formatted_short = $format_date( $period_date, true );
							?>
							<div class="wpforo-ai-chart-bar-wrapper" title="<?php echo esc_attr( $formatted_full ); ?>: <?php echo esc_attr( $post_count ); ?> posts (<?php echo esc_attr( ucfirst( $dominant ) ); ?>)">
								<div class="wpforo-ai-chart-bar" style="height: <?php echo esc_attr( $bar_height ); ?>%; background: <?php echo esc_attr( $color ); ?>;" data-emoji="<?php echo esc_attr( $emoji ); ?>"></div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<!-- X-axis labels -->
				<div class="wpforo-ai-chart-x-axis">
					<?php foreach ( $time_series as $period ) : ?>
						<span class="wpforo-ai-chart-label"><?php echo esc_html( $format_date( $period['period'] ?? '', true ) ); ?></span>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="wpforo-ai-chart-legend">
				<?php
				$legend_emotions = array(
					'happy'      => array( 'emoji' => '😊', 'label' => __( 'Happy', 'wpforo' ), 'color' => '#4CAF50' ),
					'excited'    => array( 'emoji' => '🎉', 'label' => __( 'Excited', 'wpforo' ), 'color' => '#8BC34A' ),
					'neutral'    => array( 'emoji' => '😐', 'label' => __( 'Neutral', 'wpforo' ), 'color' => '#9E9E9E' ),
					'confused'   => array( 'emoji' => '🤔', 'label' => __( 'Confused', 'wpforo' ), 'color' => '#FF9800' ),
					'frustrated' => array( 'emoji' => '😤', 'label' => __( 'Frustrated', 'wpforo' ), 'color' => '#FF5722' ),
					'angry'      => array( 'emoji' => '😠', 'label' => __( 'Angry', 'wpforo' ), 'color' => '#F44336' ),
					'sad'        => array( 'emoji' => '😢', 'label' => __( 'Sad', 'wpforo' ), 'color' => '#2196F3' ),
				);
				foreach ( $legend_emotions as $key => $emotion ) : ?>
					<span class="wpforo-ai-legend-item">
						<span class="wpforo-ai-legend-color" style="background: <?php echo esc_attr( $emotion['color'] ); ?>;"></span>
						<?php echo esc_html( $emotion['emoji'] . ' ' . $emotion['label'] ); ?>
					</span>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<!-- Mood Shifts -->
		<?php if ( ! empty( $mood_shifts ) ) : ?>
		<div class="wpforo-ai-mood-shifts">
			<h4><?php _e( 'Significant Mood Shifts', 'wpforo' ); ?></h4>
			<div class="wpforo-ai-shifts-list">
				<?php foreach ( array_slice( $mood_shifts, 0, 5 ) as $shift ) :
					$magnitude = strtolower( $shift['magnitude'] ?? 'minor' );
					$magnitude_class = 'magnitude-' . $magnitude;
					?>
					<div class="wpforo-ai-shift-item <?php echo esc_attr( $magnitude_class ); ?>">
						<div class="wpforo-ai-shift-header">
							<span class="wpforo-ai-shift-period"><?php echo esc_html( $format_date( $shift['period'] ?? '' ) ); ?></span>
							<span class="wpforo-ai-shift-magnitude"><?php echo esc_html( ucfirst( $magnitude ) ); ?></span>
						</div>
						<div class="wpforo-ai-shift-transition">
							<span class="wpforo-ai-shift-from"><?php echo esc_html( ucfirst( $shift['from_mood'] ?? '' ) ); ?></span>
							<span class="wpforo-ai-shift-arrow">→</span>
							<span class="wpforo-ai-shift-to"><?php echo esc_html( ucfirst( $shift['to_mood'] ?? '' ) ); ?></span>
						</div>
						<?php if ( ! empty( $shift['possible_cause'] ) ) : ?>
							<p class="wpforo-ai-shift-cause"><?php echo esc_html( $shift['possible_cause'] ); ?></p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<!-- Patterns -->
		<?php if ( ! empty( $patterns ) && ! empty( $patterns['pattern_description'] ) ) : ?>
		<div class="wpforo-ai-trend-patterns">
			<h4><?php _e( 'Detected Patterns', 'wpforo' ); ?></h4>
			<div class="wpforo-ai-pattern-info">
				<?php if ( ! empty( $patterns['cyclical'] ) ) : ?>
					<span class="wpforo-ai-cyclical-badge"><?php _e( '🔄 Cyclical Pattern Detected', 'wpforo' ); ?></span>
				<?php endif; ?>
				<p><?php echo esc_html( $patterns['pattern_description'] ); ?></p>
			</div>
		</div>
		<?php endif; ?>

		<!-- Summary and Forecast -->
		<div class="wpforo-ai-trend-summary-section">
			<?php if ( $summary ) : ?>
				<div class="wpforo-ai-trend-summary">
					<h4><?php _e( 'Summary', 'wpforo' ); ?></h4>
					<p><?php echo esc_html( $summary ); ?></p>
				</div>
			<?php endif; ?>
			<?php if ( $forecast ) : ?>
				<div class="wpforo-ai-trend-forecast">
					<h4><span class="dashicons dashicons-chart-line"></span> <?php _e( 'Forecast', 'wpforo' ); ?></h4>
					<p><?php echo esc_html( $forecast ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Get cached AI insights
 *
 * Uses WordPress options for permanent storage (not transients which can be cleared by cache plugins).
 * Each insight type is stored separately with board ID suffix for better organization.
 *
 * @param int $board_id Board ID
 * @return array Cached insights keyed by type
 */
function wpforo_ai_get_cached_insights( $board_id ) {
	$insight_types = array( 'sentiment', 'trending', 'deep_analysis', 'sentiment_trend', 'recommendations' );
	$cached        = array();

	foreach ( $insight_types as $type ) {
		$option_key = 'wpforo_ai_insight_' . $type . '_' . intval( $board_id );
		$data       = get_option( $option_key, false );

		if ( false !== $data && is_array( $data ) ) {
			$cached[ $type ] = $data;
		}
	}

	return $cached;
}

/**
 * Cache AI insight result
 *
 * Uses WordPress options for permanent storage until user explicitly refreshes.
 * Data is stored per insight type with board ID suffix (e.g., wpforo_ai_insight_sentiment_0).
 *
 * @param int    $board_id Board ID
 * @param string $type     Insight type
 * @param array  $data     Result data
 */
function wpforo_ai_cache_insight( $board_id, $type, $data ) {
	$option_key = 'wpforo_ai_insight_' . sanitize_key( $type ) . '_' . intval( $board_id );

	$insight_data = array(
		'data'      => $data,
		'timestamp' => time(),
	);

	// Use update_option for permanent storage (survives cache clears)
	update_option( $option_key, $insight_data, false ); // autoload = false for performance
}

/**
 * Check if cached insight data is outdated (older than 15 days)
 *
 * @param int $timestamp Unix timestamp when data was cached
 * @return bool True if data is older than 15 days
 */
function wpforo_ai_is_insight_outdated( $timestamp ) {
	$days_old = ( time() - $timestamp ) / DAY_IN_SECONDS;
	return $days_old >= 15;
}

/**
 * Delete cached AI insight for a specific type and board
 *
 * @param int    $board_id Board ID
 * @param string $type     Insight type (or 'all' to delete all)
 */
function wpforo_ai_delete_cached_insight( $board_id, $type = 'all' ) {
	if ( 'all' === $type ) {
		$insight_types = array( 'sentiment', 'trending', 'deep_analysis', 'sentiment_trend', 'recommendations' );
		foreach ( $insight_types as $t ) {
			$option_key = 'wpforo_ai_insight_' . $t . '_' . intval( $board_id );
			delete_option( $option_key );
		}
	} else {
		$option_key = 'wpforo_ai_insight_' . sanitize_key( $type ) . '_' . intval( $board_id );
		delete_option( $option_key );
	}
}
