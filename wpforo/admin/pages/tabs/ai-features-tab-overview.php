<?php
/**
 * AI Features - Overview Tab
 *
 * Overview tab content showing connection status, subscription details, and pricing
 *
 * @package wpForo
 * @subpackage Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function wpforo_ai_render_not_connected_state() {
	// Check if current site is localhost/development WITHOUT dev key
	// If WPFORO_AI_DEV_KEY is defined, allow localhost connections
	$is_localhost = wpforo_ai_is_localhost();
	$has_dev_key = defined( 'WPFORO_AI_DEV_KEY' ) && WPFORO_AI_DEV_KEY;
	$block_localhost = $is_localhost && ! $has_dev_key;
	?>
	<div class="wpforo-ai-state wpforo-ai-not-connected">

		<!-- Connection Status Box -->
		<div class="wpforo-ai-box wpforo-ai-connection-box">
			<div class="wpforo-ai-box-header" style="display: flex; justify-content: space-between; align-items: center;">
				<h2 style="margin: 0;"><?php _e( 'Connection Status', 'wpforo' ); ?></h2>
				<form method="post" action="" style="margin: 0;">
					<?php wp_nonce_field( 'wpforo_ai_refresh_status' ); ?>
					<input type="hidden" name="wpforo_ai_action" value="refresh_status">
					<button type="submit" class="button button-small" title="<?php esc_attr_e( 'Refresh Status', 'wpforo' ); ?>">
						<span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
					</button>
				</form>
			</div>
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-status-badge status-warning">
					<span class="dashicons dashicons-warning"></span>
					<?php _e( 'Not Connected', 'wpforo' ); ?>
				</div>

				<p class="wpforo-ai-description">
					<?php _e( 'Connect your forum to wpForo AI to unlock powerful AI features including semantic search, content creation, moderation, and intelligent assistance.', 'wpforo' ); ?>
				</p>

				<div class="wpforo-ai-callout">
					<strong><?php _e( 'Start with 500 FREE credits! Generate your API key in one click and activate AI features!', 'wpforo' ); ?></strong>
				</div>

				<?php if ( $block_localhost ) : ?>
					<div class="wpforo-ai-localhost-warning">
						<span class="dashicons dashicons-warning"></span>
						<?php _e( 'API key generation is only available for live websites. Please deploy your site to a public domain to connect to wpForo AI.', 'wpforo' ); ?>
					</div>
					<button type="button" class="button button-primary button-hero" disabled>
						<span class="dashicons dashicons-admin-plugins" style="vertical-align: sub;"></span>
						<?php _e( 'Generate API Key & Connect', 'wpforo' ); ?>
					</button>
				<?php else : ?>
					<form method="post" action="" id="wpforo-ai-connect-form">
						<?php wp_nonce_field( 'wpforo_ai_connect' ); ?>
						<input type="hidden" name="wpforo_ai_action" value="connect">

						<!-- Terms Agreement Checkbox -->
						<div class="wpforo-ai-terms-agreement" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px;">
							<label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
								<input type="checkbox" name="wpforo_ai_agree_terms" id="wpforo-ai-agree-terms" value="1" required style="margin-top: 3px;">
								<span style="font-size: 13px; line-height: 1.5;">
									<?php
									printf(
										__( 'I have read and agree to the %1$s and %2$s. I understand that my forum content will be processed by %3$s service as described in these documents.', 'wpforo' ),
										'<a href="#" class="wpforo-ai-legal-link" data-document="terms" style="text-decoration: none;">' . __( 'Terms of Service', 'wpforo' ) . '</a>',
										'<a href="#" class="wpforo-ai-legal-link" data-document="privacy" style="text-decoration: none;">' . __( 'Privacy Policy', 'wpforo' ) . '</a>',
                                        '<a href="https://v3.wpforo.com/gvectors-ai/" target="_blank" style="text-decoration: none;">' . __( 'gVectors AI', 'wpforo' ) . '</a>',
									);
									?>
								</span>
							</label>
						</div>

						<button type="submit" class="button button-primary button-hero" id="wpforo-ai-connect-btn">
							<span class="dashicons dashicons-admin-plugins" style="vertical-align: sub;"></span>
							<?php _e( 'Generate API Key & Connect', 'wpforo' ); ?>
						</button>
					</form>
				<?php endif; ?>

				<!-- Legal Documents Modal -->
				<div id="wpforo-ai-legal-modal" class="wpforo-ai-modal" style="display: none;">
					<div class="wpforo-ai-modal-overlay"></div>
					<div class="wpforo-ai-modal-container">
						<div class="wpforo-ai-modal-header">
							<h2 id="wpforo-ai-modal-title"></h2>
							<button type="button" class="wpforo-ai-modal-close" aria-label="<?php esc_attr_e( 'Close', 'wpforo' ); ?>">
								<span class="dashicons dashicons-no-alt"></span>
							</button>
						</div>
						<div class="wpforo-ai-modal-body" id="wpforo-ai-modal-content">
							<!-- Content loaded dynamically -->
						</div>
						<div class="wpforo-ai-modal-footer">
							<button type="button" class="button button-primary wpforo-ai-modal-close-btn">
								<?php _e( 'Close', 'wpforo' ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Features Preview Box -->
		<div class="wpforo-ai-box wpforo-ai-features-preview-box">
			<div class="wpforo-ai-box-header">
				<h2><?php _e( 'wpForo AI Features', 'wpforo' ); ?></h2>
			</div>
			<div class="wpforo-ai-box-body">
				<?php wpforo_ai_render_features_preview(); ?>
			</div>
		</div>

	</div>
	<?php
}

/**
 * Render "Pending Approval" state
 *
 * Shown when tenant has registered but admin approval is required before
 * they can use the API. The API key is saved but API calls are blocked.
 *
 * @param array $status Tenant status data
 */
function wpforo_ai_render_pending_approval_state( $status ) {
	$subscription = isset( $status['subscription'] ) ? $status['subscription'] : [];
	$credits_total = isset( $subscription['credits_total'] ) ? $subscription['credits_total'] : 500;
	?>
	<div class="wpforo-ai-state wpforo-ai-pending-approval">

		<!-- Connection Status Box -->
		<div class="wpforo-ai-box wpforo-ai-connection-box">
			<div class="wpforo-ai-box-header" style="display: flex; justify-content: space-between; align-items: center;">
				<h2 style="margin: 0;"><?php _e( 'Connection Status', 'wpforo' ); ?></h2>
				<form method="post" action="" style="margin: 0;">
					<?php wp_nonce_field( 'wpforo_ai_refresh_status' ); ?>
					<input type="hidden" name="wpforo_ai_action" value="refresh_status">
					<button type="submit" class="button button-small" title="<?php esc_attr_e( 'Refresh Status', 'wpforo' ); ?>">
						<span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
					</button>
				</form>
			</div>
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-status-badge status-pending">
					<span class="dashicons dashicons-clock"></span>
					<?php _e( 'Awaiting Activation', 'wpforo' ); ?>
				</div>

				<p class="wpforo-ai-description">
					<?php _e( 'Your API key has been generated and your account is registered. An administrator will review and activate your account shortly.', 'wpforo' ); ?>
				</p>

				<div class="wpforo-ai-callout wpforo-ai-callout-info">
					<span class="dashicons dashicons-info"></span>
					<?php
					printf(
						__( 'Once activated, you will have %s free credits to get started with AI features.', 'wpforo' ),
						'<strong>' . number_format( $credits_total ) . '</strong>'
					);
					?>
				</div>

				<div class="wpforo-ai-pending-actions">
					<!-- Refresh Status Button -->
					<form method="post" action="" style="display: inline-block; margin-top: 15px;">
						<?php wp_nonce_field( 'wpforo_ai_refresh_status' ); ?>
						<input type="hidden" name="wpforo_ai_action" value="refresh_status">
						<button type="submit" class="button">
							<span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
							<?php _e( 'Check Activation Status', 'wpforo' ); ?>
						</button>
					</form>

					<!-- Disconnect Button -->
					<form method="post" action="" style="display: inline-block; margin-left: 10px;">
						<?php wp_nonce_field( 'wpforo_ai_disconnect' ); ?>
						<input type="hidden" name="wpforo_ai_action" value="disconnect">
						<button type="submit" class="button wpforo-ai-disconnect-btn">
							<?php _e( 'Cancel Registration', 'wpforo' ); ?>
						</button>
					</form>
				</div>
			</div>
		</div>

		<!-- Features Preview Box -->
		<div class="wpforo-ai-box wpforo-ai-features-preview-box">
			<div class="wpforo-ai-box-header">
				<h2><?php _e( 'Available After Activation', 'wpforo' ); ?></h2>
			</div>
			<div class="wpforo-ai-box-body">
				<?php wpforo_ai_render_features_preview(); ?>
			</div>
		</div>

	</div>
	<?php
}

/**
 * Render "Inactive" state
 *
 * Shown when an administrator has temporarily deactivated the tenant.
 * API calls are blocked until the admin reactivates the account.
 * No connect button should be shown since the account exists.
 *
 * @param array $status Tenant status data
 */
function wpforo_ai_render_inactive_state( $status ) {
	?>
	<div class="wpforo-ai-state wpforo-ai-inactive">

		<!-- Connection Status Box -->
		<div class="wpforo-ai-box wpforo-ai-connection-box">
			<div class="wpforo-ai-box-header" style="display: flex; justify-content: space-between; align-items: center;">
				<h2 style="margin: 0;"><?php _e( 'Connection Status', 'wpforo' ); ?></h2>
				<form method="post" action="" style="margin: 0;">
					<?php wp_nonce_field( 'wpforo_ai_refresh_status' ); ?>
					<input type="hidden" name="wpforo_ai_action" value="refresh_status">
					<button type="submit" class="button button-small" title="<?php esc_attr_e( 'Refresh Status', 'wpforo' ); ?>">
						<span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
					</button>
				</form>
			</div>
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-status-badge status-inactive">
					<span class="dashicons dashicons-marker"></span>
					<?php _e( 'Temporarily Inactive', 'wpforo' ); ?>
				</div>

				<p class="wpforo-ai-description">
					<?php _e( 'Your wpForo AI account has been temporarily deactivated by an administrator. AI features are currently unavailable.', 'wpforo' ); ?>
				</p>

				<div class="wpforo-ai-callout wpforo-ai-callout-warning">
					<span class="dashicons dashicons-info"></span>
					<?php
					printf(
						__( 'Please %s if you believe this is an error or if you need assistance reactivating your account.', 'wpforo' ),
						'<a href="https://v3.wpforo.com/login-register/?tab=login" target="_blank">' . __( 'open a support ticket', 'wpforo' ) . '</a>'
					);
					?>
				</div>

				<div class="wpforo-ai-inactive-actions">
					<!-- Refresh Status Button -->
					<form method="post" action="" style="display: inline-block; margin-top: 15px;">
						<?php wp_nonce_field( 'wpforo_ai_refresh_status' ); ?>
						<input type="hidden" name="wpforo_ai_action" value="refresh_status">
						<button type="submit" class="button">
							<span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
							<?php _e( 'Check Account Status', 'wpforo' ); ?>
						</button>
					</form>
				</div>
			</div>
		</div>

		<!-- Features Preview Box (disabled state) -->
		<div class="wpforo-ai-box wpforo-ai-features-preview-box" style="opacity: 0.6;">
			<div class="wpforo-ai-box-header">
				<h2><?php _e( 'Features Unavailable', 'wpforo' ); ?></h2>
			</div>
			<div class="wpforo-ai-box-body">
				<?php wpforo_ai_render_features_preview(); ?>
			</div>
		</div>

	</div>
	<?php
}

/**
 * Render "Free Trial" state
 *
 * @param array $status Tenant status data
 * @param bool  $is_post_purchase Whether this is a post-purchase page load
 */
function wpforo_ai_render_free_trial_state( $status, $is_post_purchase = false ) {
	$subscription = isset($status['subscription']) ? $status['subscription'] : [];
	$tenant_id    = isset($status['tenant_id']) ? $status['tenant_id'] : '';
	$features     = isset($status['features_enabled']) ? $status['features_enabled'] : [];
	$usage        = isset($status['usage_this_month']) ? $status['usage_this_month'] : [];

	$credits_total     = isset($subscription['credits_total']) ? $subscription['credits_total'] : 0;
	$credits_remaining = isset($subscription['credits_remaining']) ? $subscription['credits_remaining'] : 0;
	$credits_used      = isset($subscription['credits_used']) ? $subscription['credits_used'] : 0;
	$expires_at        = isset($subscription['expires_at']) ? $subscription['expires_at'] : '';
	$renews_at         = isset($subscription['renews_at']) ? $subscription['renews_at'] : '';

	$credits_percent = $credits_total > 0 ? round( ( $credits_remaining / $credits_total ) * 100 ) : 0;

	wpforo_ai_render_connected_state( $status, 'free_trial', $is_post_purchase );
}

/**
 * Render "Paid Plan" state
 *
 * @param array $status Tenant status data
 * @param bool  $is_post_purchase Whether this is a post-purchase page load
 */
function wpforo_ai_render_paid_plan_state( $status, $is_post_purchase = false ) {
	wpforo_ai_render_connected_state( $status, 'paid', $is_post_purchase );
}

/**
 * Render connected state (shared between free trial and paid)
 *
 * @param array  $status Tenant status data
 * @param string $mode 'free_trial' or 'paid'
 * @param bool   $is_post_purchase Whether this is a post-purchase page load
 */
function wpforo_ai_render_connected_state( $status, $mode = 'free_trial', $is_post_purchase = false ) {
	$subscription = isset($status['subscription']) && is_array($status['subscription']) ? $status['subscription'] : [];
	$tenant_id    = isset($status['tenant_id']) ? $status['tenant_id'] : '';
	$features     = isset($status['features_enabled']) && is_array($status['features_enabled']) ? $status['features_enabled'] : [];
	$usage        = isset($status['usage_this_month']) && is_array($status['usage_this_month']) ? $status['usage_this_month'] : [];
	$usage_lifetime = isset($status['usage_lifetime']) && is_array($status['usage_lifetime']) ? $status['usage_lifetime'] : [];
	$plan         = isset($subscription['plan']) ? $subscription['plan'] : 'free_trial';

	$credits_total     = isset($subscription['credits_total']) ? (int) $subscription['credits_total'] : 0;
	$credits_baseline  = isset($subscription['credits_baseline']) ? (int) $subscription['credits_baseline'] : $credits_total;
	$credits_remaining = isset($subscription['credits_remaining']) ? (int) $subscription['credits_remaining'] : 0;
	$credits_used      = isset($subscription['credits_used']) ? (int) $subscription['credits_used'] : 0;
	$max_credits       = isset($subscription['max_credits']) ? (int) $subscription['max_credits'] : 0;
	$expires_at        = isset($subscription['expires_at']) ? $subscription['expires_at'] : '';
	$renews_at         = isset($subscription['renews_at']) ? $subscription['renews_at'] : '';

	// Payment providers: detect all providers used by this tenant
	// API returns payment_providers array (e.g., ["freemius", "paddle"]) and payment_provider string (most recent)
	$payment_providers = isset($subscription['payment_providers']) && is_array($subscription['payment_providers']) ? $subscription['payment_providers'] : [];
	$payment_provider  = isset($subscription['payment_provider']) ? $subscription['payment_provider'] : '';
	// Backwards compatibility: if API doesn't return payment_providers yet, build from single value
	if ( empty( $payment_providers ) && $payment_provider ) {
		$payment_providers = [ $payment_provider ];
	}
	// Legacy fallback: existing Freemius subscribers may not have payment_provider set
	if ( empty( $payment_providers ) && $mode === 'paid' ) {
		$payment_providers = [ 'freemius' ];
		$payment_provider  = 'freemius';
	}

	$credits_percent = $credits_baseline > 0 ? round( ( $credits_remaining / $credits_baseline ) * 100 ) : 0;
	$is_free_trial   = ( $mode === 'free_trial' );

	// Get stored API key (global option, shared across all boards)
	$api_key        = WPF()->ai_client->get_api_key();
	$api_key_masked = WPF()->ai_client->mask_api_key( WPF()->ai_client->decrypt_api_key( $api_key ) );

	// Check bonus credits status
	$bonus_credits_status = WPF()->ai_client->get_bonus_credits_status();
	$bonus_credits_claimed = ! empty( $bonus_credits_status['claimed'] );
	$bonus_credits_amount = $bonus_credits_claimed ? (int) $bonus_credits_status['amount'] : 0;

	// Check eligibility for bonus credits (only if not claimed)
	$bonus_credits_eligible = false;
	if ( ! $bonus_credits_claimed ) {
		$eligibility = WPF()->ai_client->check_bonus_credits_eligibility();
		$bonus_credits_eligible = $eligibility['eligible'];
	}

	?>
	<div class="wpforo-ai-state wpforo-ai-connected <?php echo $is_free_trial ? 'wpforo-ai-free-trial' : 'wpforo-ai-paid-plan'; ?>">

		<!-- Connection Status Box -->
		<div class="wpforo-ai-box wpforo-ai-connection-box">
			<div class="wpforo-ai-box-header">
				<h2><?php _e( 'Connection Status', 'wpforo' ); ?></h2>
				<form method="post" action="" class="wpforo-ai-refresh-form">
					<?php wp_nonce_field( 'wpforo_ai_refresh_status' ); ?>
					<input type="hidden" name="wpforo_ai_action" value="refresh_status">
					<button type="submit" class="button button-small" title="<?php esc_attr_e( 'Refresh Status', 'wpforo' ); ?>">
						<span class="dashicons dashicons-update"></span>
						<?php _e( 'Refresh', 'wpforo' ); ?>
					</button>
				</form>
			</div>
			<div class="wpforo-ai-box-body">
			<div class="wpforo-ai-status-badge status-success">
				<?php if ( $is_post_purchase ) : ?>
					<span class="dashicons dashicons-update dashicons-spin"></span>
					<?php _e( 'Subscription plan is being updated...', 'wpforo' ); ?>
				<?php else : ?>
					<span class="dashicons dashicons-yes-alt"></span>
					<?php
					if ( $is_free_trial ) {
						_e( 'Connected (Free Trial)', 'wpforo' );
					} else {
						printf( __( 'Connected (%s Plan)', 'wpforo' ), esc_html( ucfirst( $plan ) ) );
					}
					?>
				<?php endif; ?>
			</div>

				<div class="wpforo-ai-connection-details">
					<div class="detail-row">
						<span class="detail-label"><?php _e( 'Tenant ID:', 'wpforo' ); ?></span>
						<code class="detail-value"><?php echo esc_html( $tenant_id ); ?></code>
					</div>
					<div class="detail-row">
						<span class="detail-label"><?php _e( 'API Key:', 'wpforo' ); ?></span>
						<code class="detail-value wpforo-ai-key-masked" data-key-full="hidden"><?php echo esc_html( $api_key_masked ); ?></code>
					</div>

					<!-- License Activation Section (Freemius only) -->
					<?php if ( in_array( 'freemius', $payment_providers, true ) ) : ?>
					<div class="detail-row wpforo-ai-license-activation" style="flex-direction: column; align-items: flex-start;">
						<a href="#" class="wpforo-ai-license-toggle" onclick="jQuery('.wpforo-ai-license-fields').slideToggle(200); jQuery(this).find('.dashicons').toggleClass('dashicons-arrow-right-alt2 dashicons-arrow-down-alt2'); return false;" style="text-decoration: none; color: #666; font-size: 13px;">
							<span class="dashicons dashicons-arrow-right-alt2" style="vertical-align: middle; font-size: 16px; width: 16px; height: 16px;"></span>
							<?php _e( 'Activate by License ID (Freemius payment only)', 'wpforo' ); ?>
						</a>
						<div class="wpforo-ai-license-fields" style="display: none; margin-top: 10px;">
							<div class="license-input-wrapper">
								<input type="text"
									id="wpforo-ai-license-id"
									class="regular-text"
									placeholder="<?php esc_attr_e( 'Enter License ID from purchase email', 'wpforo' ); ?>"
									style="width: 250px; margin-right: 10px; inherit; font-size: 14px;"
								>
								<button type="button" class="button button-secondary wpforo-ai-activate-license-btn">
									<span class="dashicons dashicons-yes-alt" style="vertical-align: middle; font-size: 18px; line-height: 16px;"></span>
									<?php _e( 'Activate License', 'wpforo' ); ?>
								</button>
								<span class="spinner" style="float: none; margin-top: 0;"></span>
							</div>
							<p class="description" style="margin-top: 5px; color: #111;">
								<?php _e( 'If your Freemius plan was not activated automatically after purchase, enter your License ID here.', 'wpforo' ); ?>
								<br>
								<?php _e( 'You can find your License ID in the purchase confirmation email from Freemius.', 'wpforo' ); ?>
							</p>
							<div class="wpforo-ai-license-result" style="display: none; margin-top: 10px; min-width: 220px;"></div>
						</div>
					</div>
					<?php endif; ?>

					<!-- Paddle Transaction Activation Section (Paddle only) -->
					<?php if ( in_array( 'paddle', $payment_providers, true ) ) : ?>
					<div class="detail-row wpforo-ai-paddle-activation" style="flex-direction: column; align-items: flex-start;">
						<a href="#" class="wpforo-ai-paddle-toggle" onclick="jQuery('.wpforo-ai-paddle-fields').slideToggle(200); jQuery(this).find('.dashicons').toggleClass('dashicons-arrow-right-alt2 dashicons-arrow-down-alt2'); return false;" style="text-decoration: none; color: #666; font-size: 13px;">
							<span class="dashicons dashicons-arrow-right-alt2" style="vertical-align: middle; font-size: 16px; width: 16px; height: 16px;"></span>
							<?php _e( 'Activate by Transaction ID (Paddle payment only)', 'wpforo' ); ?>
						</a>
						<div class="wpforo-ai-paddle-fields" style="display: none; margin-top: 10px;">
							<div class="license-input-wrapper">
								<input type="text"
									id="wpforo-ai-paddle-txn-id"
									class="regular-text"
									placeholder="<?php esc_attr_e( 'e.g. txn_ . . . . ', 'wpforo' ); ?>"
									style="width: 300px; margin-right: 10px; font-size: 14px;"
								>
								<button type="button" class="button button-secondary wpforo-ai-activate-paddle-txn-btn">
									<span class="dashicons dashicons-yes-alt" style="vertical-align: middle; font-size: 18px; line-height: 16px;"></span>
									<?php _e( 'Activate Transaction', 'wpforo' ); ?>
								</button>
								<span class="spinner" style="float: none; margin-top: 0;"></span>
							</div>
							<p class="description" style="margin-top: 5px; color: #111;">
								<?php _e( 'If your Paddle payment was not registered automatically, enter your Transaction ID here.', 'wpforo' ); ?>
								<br>
								<?php _e( 'You can find the Transaction ID in invoice file of the payment confirmation email from Paddle.', 'wpforo' ); ?>
							</p>
							<div class="wpforo-ai-paddle-result" style="display: none; margin-top: 10px; min-width: 220px;"></div>
						</div>
					</div>
					<?php endif; ?>

					<div class="detail-row wpforo-ai-key-actions">
						<?php if ( $bonus_credits_claimed ) : ?>
							<!-- Bonus Credits Already Claimed -->
                            <span class="detail-label"><?php _e( 'Bonus Credits:', 'wpforo' ); ?></span>
							<button type="button" class="button wpforo-ai-bonus-credits-btn claimed" disabled>
								<span class="dashicons dashicons-awards"></span>
								<?php printf( __( 'Granted Extra Free Credits %s', 'wpforo' ), number_format( $bonus_credits_amount ) ); ?>
							</button>
						<?php elseif ( $bonus_credits_eligible ) : ?>
							<!-- Request Bonus Credits Button -->
                            <span class="detail-label"><?php _e( 'Bonus Credits:', 'wpforo' ); ?></span>
							<button type="button" class="button wpforo-ai-bonus-credits-btn eligible">
								<span class="dashicons dashicons-star-filled"></span>
								<?php _e( 'Request Free Credits for Forum Content Indexing', 'wpforo' ); ?>
							</button>
							<span class="spinner wpforo-ai-bonus-spinner" style="float: none; margin-top: 0;"></span>
						<?php endif; ?>
						<!-- Disconnect Service -->
						<button type="button" class="button button-secondary wpforo-ai-disconnect-btn">
							<span class="dashicons dashicons-dismiss"></span>
							<?php _e( 'Disconnect Service', 'wpforo' ); ?>
						</button>
						<!-- Disconnect and Remove All Data -->
						<button type="button" class="button button-secondary wpforo-ai-disconnect-purge-btn" style="color: #b32d2e; border-color: #b32d2e;">
							<span class="dashicons dashicons-trash"></span>
							<?php _e( 'Disconnect and Remove All Data', 'wpforo' ); ?>
						</button>
					</div>
				</div>

				<!-- Credits Display -->
				<div class="wpforo-ai-credits-section">
					<div class="credits-header">
						<h3><?php _e( 'AI Credits', 'wpforo' ); ?></h3>
						<div class="credits-numbers">
							<strong><?php echo number_format( $credits_remaining ); ?></strong>
							<span>/</span>
							<span><?php echo number_format( $credits_baseline ); ?></span>
							<span class="credits-label"><?php _e( 'remaining', 'wpforo' ); ?></span>
						</div>
					</div>
					<div class="credits-progress-bar">
						<div class="credits-progress-fill <?php echo wpforo_ai_get_credit_status_class( $credits_percent ); ?>" style="width: <?php echo esc_attr( $credits_percent ); ?>%;"></div>
					</div>
					<div class="credits-info">
						<?php
						if ( $is_free_trial && ! empty( $expires_at ) ) {
							printf(
								__( 'Trial expires: %s', 'wpforo' ),
								'<strong>' . esc_html( wpforo_ai_format_date( $expires_at ) ) . '</strong>'
							);
						} elseif ( ! empty( $renews_at ) ) {
							printf(
								__( 'Next billing: %s', 'wpforo' ),
								'<strong>' . esc_html( wpforo_ai_format_date( $renews_at ) ) . '</strong>'
							);
						}
						?>
					</div>
				</div>
				<?php if ( $max_credits > 0 && ! $is_free_trial ) : ?>
					<div class="credits-cap-info" style="margin-top: 5px; font-size: 12px; color: #666; text-align: right;">
						<span class="dashicons dashicons-info-outline" style="font-size: 14px; width: 14px; height: 14px; vertical-align: text-top;"></span>
						<?php printf(
							__( 'Maximum credit accumulation (one year of subscription plan credits): %s', 'wpforo' ),
							'<strong>' . number_format( $max_credits ) . '</strong>'
						); ?>
					</div>
				<?php endif; ?>

				<!-- Warning for Free Trial -->
				<?php if ( $is_free_trial ) : ?>
					<div class="wpforo-ai-warning-box">
						<span class="dashicons dashicons-warning"></span>
						<p>
                            <?php
                            printf(
                                __( 'You\'re on Free Trial. Expires on %s. Upgrade to  continue using all AI features!', 'wpforo' ),
                                esc_html( wpforo_ai_format_date( $expires_at ) )
                            );
                            ?>
                        </p>
					</div>
				<?php else : ?>
					<div class="wpforo-ai-success-box" style="display: none;">
						<span class="dashicons dashicons-yes-alt"></span>
						<p><?php printf( __( 'All features of %s plan are unlocked!', 'wpforo' ), $plan); ?></p>
					</div>
				<?php endif; ?>

				<?php if ( ! $is_free_trial && ! empty( $payment_providers ) ) : ?>
					<div class="wpforo-ai-payment-provider" style="margin-top: 8px; font-size: 13px; color: #555;">
						<span class="dashicons dashicons-money-alt" style="font-size: 16px; width: 16px; height: 16px; vertical-align: text-bottom;"></span>
						<?php
						$provider_labels = array_map( function( $p ) { return '<strong>' . esc_html( ucfirst( $p ) ) . '</strong>'; }, $payment_providers );
						printf( __( 'Payment: %s', 'wpforo' ), implode( ' + ', $provider_labels ) );
						?>
					</div>
				<?php endif; ?>

				<!-- Action Buttons -->
				<div class="wpforo-ai-actions">
					<?php if ( $is_free_trial ) : ?>
						<button type="button" class="button button-primary button-large" onclick="document.getElementById('wpforo-ai-plans').scrollIntoView({ behavior: 'smooth' })">
							<span class="dashicons dashicons-cart"></span>
							<?php _e( 'Upgrade Now / View Plans', 'wpforo' ); ?>&nbsp;
						</button>
					<?php else : ?>
						<?php foreach ( $payment_providers as $provider ) : ?>
							<a href="<?php echo esc_url( wpforo_ai_get_manage_subscription_url( $provider ) ); ?>" class="button button-secondary wpf-manage-subscription wpf-provider-<?php echo esc_attr( $provider ); ?>" target="_blank">
								<span class="dashicons dashicons-admin-generic"></span>
								<?php printf( __( 'Manage Subscription (%s)', 'wpforo' ), esc_html( ucfirst( $provider ) ) ); ?>
							</a>
						<?php endforeach; ?>
						<?php if ( $plan === 'starter' || $plan === 'professional' ) : ?>
							<button type="button" class="button button-secondary wpforo-ai-upgrade-btn" data-plan="business" data-tenant-id="<?php echo esc_attr( $tenant_id ); ?>">
								<span class="dashicons dashicons-cart"></span>
								<?php _e( 'Upgrade to Business', 'wpforo' ); ?>
							</button>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- Available Features Box -->
		<div class="wpforo-ai-box wpforo-ai-features-box">
			<div class="wpforo-ai-box-header">
				<h2><?php echo $is_free_trial ? __( 'Available AI Features', 'wpforo' ) : __( 'Active AI Features', 'wpforo' ); ?></h2>
			</div>
			<div class="wpforo-ai-box-body">
				<?php wpforo_ai_render_features_list( $features, $usage, $is_free_trial, $status ); ?>
			</div>
		</div>

		<!-- Pricing Plans Box -->
		<div id="wpforo-ai-plans" class="wpforo-ai-box wpforo-ai-upgrade-box">
			<div class="wpforo-ai-box-header">
				<h2><?php echo $is_free_trial ? __( 'Upgrade Your Plan', 'wpforo' ) : __( 'Subscription Plans', 'wpforo' ); ?></h2>
			</div>
			<div class="wpforo-ai-box-body">
				<?php wpforo_ai_render_pricing_table( $tenant_id, $subscription ); ?>
			</div>
		</div>

		<!-- Credit Packs Box (for paid users only) -->
		<?php if ( ! $is_free_trial ) : ?>
			<div class="wpforo-ai-box wpforo-ai-credit-packs-box">
				<div class="wpforo-ai-box-header">
					<h2><?php _e( 'Buy Additional Credits', 'wpforo' ); ?></h2>
				</div>
				<div class="wpforo-ai-box-body">
					<?php wpforo_ai_render_credit_packs( $tenant_id ); ?>
				</div>
			</div>
		<?php endif; ?>

		<!-- Usage Statistics Box -->
		<div class="wpforo-ai-box wpforo-ai-usage-box">
			<div class="wpforo-ai-box-header">
				<h2><?php _e( 'Usage Statistics', 'wpforo' ); ?></h2>
			</div>
			<div class="wpforo-ai-box-body">
				<?php wpforo_ai_render_usage_stats( $usage, $credits_used, $usage_lifetime, $plan ); ?>
			</div>
		</div>

		<!-- Account Information Box -->
		<div class="wpforo-ai-box wpforo-ai-advanced-box">
			<div class="wpforo-ai-box-header">
				<h2><?php _e( 'Account Information', 'wpforo' ); ?></h2>
			</div>
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-advanced-info">
					<div class="info-row">
						<span class="info-label"><?php _e( 'Forum URL:', 'wpforo' ); ?></span>
						<span class="info-value"><?php echo esc_html( get_site_url() ); ?></span>
					</div>
					<div class="info-row">
						<span class="info-label"><?php _e( 'Admin Email:', 'wpforo' ); ?></span>
						<span class="info-value"><?php echo esc_html( get_option( 'admin_email' ) ); ?></span>
					</div>
					<div class="info-row">
						<span class="info-label"><?php _e( 'Tenant ID:', 'wpforo' ); ?></span>
						<code class="info-value"><?php echo esc_html( $tenant_id ); ?></code>
					</div>
				</div>
			</div>
		</div>

	</div>

	<!-- Hidden Forms (triggered by buttons) -->
	<div id="wpforo-ai-hidden-forms" style="display:none;">
		<!-- Disconnect Form -->
		<form id="wpforo-ai-disconnect-form" method="post" action="">
			<?php wp_nonce_field( 'wpforo_ai_disconnect' ); ?>
			<input type="hidden" name="wpforo_ai_action" value="disconnect">
			<div class="wpforo-ai-disconnect-warning">
				<span class="dashicons dashicons-warning"></span>
				<p><strong><?php _e( 'Warning: This will disconnect your forum from wpForo AI service.', 'wpforo' ); ?></strong></p>
				<p><?php _e( 'Your credits will be preserved and restored when you reconnect. Your indexed content will be deleted after 30 days. You can reconnect anytime with the same site URL.', 'wpforo' ); ?></p>
			</div>
			<p>
				<label>
					<input type="checkbox" name="confirm" value="1" required>
					<?php _e( 'I understand and want to disconnect the service', 'wpforo' ); ?>
				</label>
			</p>
			<p>
				<label for="disconnect-reason"><?php _e( 'Reason (optional):', 'wpforo' ); ?></label>
				<textarea id="disconnect-reason" name="reason" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Help us improve by telling us why you\'re disconnecting', 'wpforo' ); ?>"></textarea>
			</p>
		</form>

		<!-- Disconnect and Remove All Data Form -->
		<form id="wpforo-ai-disconnect-purge-form" method="post" action="">
			<?php wp_nonce_field( 'wpforo_ai_disconnect' ); ?>
			<input type="hidden" name="wpforo_ai_action" value="disconnect">
			<input type="hidden" name="purge_data" value="1">
			<div class="wpforo-ai-disconnect-warning" style="border-left-color: #b32d2e;">
				<span class="dashicons dashicons-warning" style="color: #b32d2e;"></span>
				<p><strong style="color: #b32d2e;"><?php _e( 'Warning: This will permanently delete ALL your data from gVectors AI servers.', 'wpforo' ); ?></strong></p>
				<p><?php _e( 'This action will disconnect your forum and immediately delete all indexed content, embeddings, and tenant data from the AI service. This cannot be undone. Your credits will NOT be preserved.', 'wpforo' ); ?></p>
			</div>
			<p>
				<label>
					<input type="checkbox" name="confirm" value="1" required>
					<?php _e( 'I understand that all my data will be permanently deleted and want to proceed', 'wpforo' ); ?>
				</label>
			</p>
			<p>
				<label for="disconnect-purge-reason"><?php _e( 'Reason (optional):', 'wpforo' ); ?></label>
				<textarea id="disconnect-purge-reason" name="reason" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Help us improve by telling us why you\'re removing your data', 'wpforo' ); ?>"></textarea>
			</p>
		</form>
	</div>
	<?php
}

/**
 * Render expired subscription state
 *
 * @param array $status Tenant status data with expired subscription
 */
function wpforo_ai_render_expired_state( $status ) {
	$tenant_id    = wpfval( $status, 'tenant_id' ) ?: WPF()->ai_client->get_tenant_id();
	$subscription = wpfval( $status, 'subscription' );
	$expires_at   = wpfval( $subscription, 'expires_at' );
	$plan         = wpfval( $subscription, 'plan' );
	$is_trial     = ( $plan === 'free_trial' );

	?>
	<div class="wpforo-ai-state wpforo-ai-expired-state">
		<div class="wpforo-ai-box wpforo-ai-error-box" style="border-left: 4px solid #d63638;">
			<div class="wpforo-ai-box-header">
				<h2><?php _e( 'Subscription Status', 'wpforo' ); ?></h2>
			</div>
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-status-badge status-error">
					<span class="dashicons dashicons-warning"></span>
					<?php echo $is_trial ? __( 'Trial Expired', 'wpforo' ) : __( 'Subscription Expired', 'wpforo' ); ?>
				</div>

				<div class="wpforo-ai-error-message" style="margin: 20px 0;">
					<?php if ( $is_trial ) : ?>
						<p><strong><?php _e( 'Your free trial has expired.', 'wpforo' ); ?></strong></p>
						<p><?php _e( 'To continue using AI features, please upgrade to a paid plan.', 'wpforo' ); ?></p>
					<?php else : ?>
						<p><strong><?php _e( 'Your subscription has expired.', 'wpforo' ); ?></strong></p>
						<p><?php _e( 'Please renew your subscription to continue using AI features.', 'wpforo' ); ?></p>
					<?php endif; ?>

					<?php if ( ! empty( $expires_at ) ) : ?>
						<p class="wpforo-ai-expiry-date" style="color: #666; font-size: 13px;">
							<?php
							printf(
								__( 'Expired on: %s', 'wpforo' ),
								'<strong>' . esc_html( wpforo_ai_format_date( $expires_at ) ) . '</strong>'
							);
							?>
						</p>
					<?php endif; ?>
				</div>

				<div class="wpforo-ai-error-actions">
					<button type="button" class="button button-primary button-large" onclick="document.getElementById('wpforo-ai-plans').scrollIntoView({ behavior: 'smooth' })">
						<span class="dashicons dashicons-cart"></span>
						<?php _e( 'Upgrade Now / View Plans', 'wpforo' ); ?>&nbsp;
					</button>

					<form method="post" action="" style="display: inline;">
						<?php wp_nonce_field( 'wpforo_ai_refresh_status' ); ?>
						<input type="hidden" name="wpforo_ai_action" value="refresh_status">
						<button type="submit" class="button button-secondary">
							<span class="dashicons dashicons-update"></span>
							<?php _e( 'Check Status', 'wpforo' ); ?>
						</button>
					</form>
				</div>

				<div class="wpforo-ai-support-info" style="margin-top: 20px;">
					<p style="color: #666; font-size: 13px;">
						<?php
						printf(
							__( 'Need help? %s', 'wpforo' ),
							'<a href="https://v3.wpforo.com/login-register/?tab=login" target="_blank">' . __( 'Open Support Ticket', 'wpforo' ) . '</a>'
						);
						?>
					</p>
				</div>
			</div>
		</div>

		<!-- Plans Section for expired users -->
		<div id="wpforo-ai-plans" class="wpforo-ai-box" style="margin-top: 20px;">
			<div class="wpforo-ai-box-header">
				<h2><?php _e( 'Available Plans', 'wpforo' ); ?></h2>
			</div>
			<div class="wpforo-ai-box-body">
				<?php wpforo_ai_render_pricing_table( 'free_trial' ); ?>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Render cancelled/refunded subscription state
 *
 * Shown when Freemius or Paddle webhook sets subscription_status to
 * "cancelled" or "refunded". The user needs a clear explanation and
 * actionable options: buy a new plan, disconnect, or check status.
 *
 * @param array $status Tenant status data from /tenant/status API
 */
function wpforo_ai_render_cancelled_state( $status ) {
	$subscription = wpfval( $status, 'subscription' );
	$sub_status   = wpfval( $subscription, 'status' );
	$is_refunded  = ( $sub_status === 'refunded' );
	$tenant_id    = wpfval( $status, 'tenant_id' ) ?: WPF()->ai_client->get_tenant_id();

	$provider     = wpfval( $subscription, 'payment_provider' );
	if ( empty( $provider ) ) {
		$provider = get_option( 'wpforo_ai_payment_provider', 'freemius' );
	}

	?>
	<div class="wpforo-ai-state wpforo-ai-cancelled-state">
		<div class="wpforo-ai-box wpforo-ai-error-box" style="border-left: 4px solid #d63638;">
			<div class="wpforo-ai-box-header">
				<h2><?php _e( 'Subscription Status', 'wpforo' ); ?></h2>
			</div>
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-status-badge status-error">
					<span class="dashicons dashicons-warning"></span>
					<?php echo $is_refunded ? __( 'Subscription Refunded', 'wpforo' ) : __( 'Subscription Cancelled', 'wpforo' ); ?>
				</div>

				<div class="wpforo-ai-error-message" style="margin: 20px 0;">
					<?php if ( $is_refunded ) : ?>
						<p><strong><?php _e( 'Your subscription has been refunded.', 'wpforo' ); ?></strong></p>
						<p><?php _e( 'AI features are currently disabled. You can purchase a new plan to reactivate the service, or disconnect to start fresh.', 'wpforo' ); ?></p>
					<?php else : ?>
						<p><strong><?php _e( 'Your subscription has been cancelled.', 'wpforo' ); ?></strong></p>
						<p><?php _e( 'AI features are currently disabled. You can purchase a new plan to reactivate the service, or disconnect to start fresh.', 'wpforo' ); ?></p>
					<?php endif; ?>

					<p style="color: #666; font-size: 13px;">
						<?php _e( 'Your indexed content is still preserved. If you purchase a new plan, your data will be restored automatically.', 'wpforo' ); ?>
					</p>
				</div>

				<div class="wpforo-ai-error-actions">
					<button type="button" class="button button-primary button-large" onclick="document.getElementById('wpforo-ai-plans').scrollIntoView({ behavior: 'smooth' })">
						<span class="dashicons dashicons-cart"></span>
						<?php _e( 'Purchase New Plan', 'wpforo' ); ?>&nbsp;
					</button>

					<form method="post" action="" style="display: inline;">
						<?php wp_nonce_field( 'wpforo_ai_refresh_status' ); ?>
						<input type="hidden" name="wpforo_ai_action" value="refresh_status">
						<button type="submit" class="button button-secondary">
							<span class="dashicons dashicons-update"></span>
							<?php _e( 'Check Status', 'wpforo' ); ?>
						</button>
					</form>

					<a href="<?php echo esc_url( wpforo_ai_get_manage_subscription_url( $provider ) ); ?>" class="button button-secondary" target="_blank">
						<span class="dashicons dashicons-admin-generic"></span>
						<?php printf( __( 'Manage Subscription (%s)', 'wpforo' ), esc_html( ucfirst( $provider ) ) ); ?>
					</a>
				</div>

				<!-- Disconnect options -->
				<div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
					<p style="color: #666; font-size: 13px; margin-bottom: 10px;">
						<?php _e( 'Want to start fresh? Disconnect to remove the current connection and register again.', 'wpforo' ); ?>
					</p>
					<button type="button" class="button button-secondary wpforo-ai-disconnect-btn">
						<span class="dashicons dashicons-dismiss"></span>
						<?php _e( 'Disconnect Service', 'wpforo' ); ?>
					</button>
					<button type="button" class="button button-secondary wpforo-ai-disconnect-purge-btn" style="color: #b32d2e; border-color: #b32d2e;">
						<span class="dashicons dashicons-trash"></span>
						<?php _e( 'Disconnect and Remove All Data', 'wpforo' ); ?>
					</button>
				</div>

				<div class="wpforo-ai-support-info" style="margin-top: 20px;">
					<p style="color: #666; font-size: 13px;">
						<?php
						printf(
							__( 'Need help? %s', 'wpforo' ),
							'<a href="https://v3.wpforo.com/login-register/?tab=login" target="_blank">' . __( 'Open Support Ticket', 'wpforo' ) . '</a>'
						);
						?>
					</p>
				</div>
			</div>
		</div>

		<!-- Plans Section -->
		<div id="wpforo-ai-plans" class="wpforo-ai-box" style="margin-top: 20px;">
			<div class="wpforo-ai-box-header">
				<h2><?php _e( 'Available Plans', 'wpforo' ); ?></h2>
			</div>
			<div class="wpforo-ai-box-body">
				<?php wpforo_ai_render_pricing_table( 'free_trial' ); ?>
			</div>
		</div>
	</div>

	<!-- Hidden Forms for Disconnect (triggered by JS buttons above) -->
	<div id="wpforo-ai-hidden-forms" style="display:none;">
		<form id="wpforo-ai-disconnect-form" method="post" action="">
			<?php wp_nonce_field( 'wpforo_ai_disconnect' ); ?>
			<input type="hidden" name="wpforo_ai_action" value="disconnect">
			<div class="wpforo-ai-disconnect-warning">
				<span class="dashicons dashicons-warning"></span>
				<p><strong><?php _e( 'Warning: This will disconnect your forum from wpForo AI service.', 'wpforo' ); ?></strong></p>
				<p><?php _e( 'Your credits will be preserved and restored when you reconnect. Your indexed content will be deleted after 30 days. You can reconnect anytime with the same site URL.', 'wpforo' ); ?></p>
			</div>
			<p>
				<label>
					<input type="checkbox" name="confirm" value="1" required>
					<?php _e( 'I understand and want to disconnect the service', 'wpforo' ); ?>
				</label>
			</p>
			<p>
				<label for="disconnect-reason"><?php _e( 'Reason (optional):', 'wpforo' ); ?></label>
				<textarea id="disconnect-reason" name="reason" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Help us improve by telling us why you\'re disconnecting', 'wpforo' ); ?>"></textarea>
			</p>
		</form>

		<form id="wpforo-ai-disconnect-purge-form" method="post" action="">
			<?php wp_nonce_field( 'wpforo_ai_disconnect' ); ?>
			<input type="hidden" name="wpforo_ai_action" value="disconnect">
			<input type="hidden" name="purge_data" value="1">
			<div class="wpforo-ai-disconnect-warning" style="border-left-color: #b32d2e;">
				<span class="dashicons dashicons-warning" style="color: #b32d2e;"></span>
				<p><strong style="color: #b32d2e;"><?php _e( 'Warning: This will permanently delete ALL your data from gVectors AI servers.', 'wpforo' ); ?></strong></p>
				<p><?php _e( 'This action will disconnect your forum and immediately delete all indexed content, embeddings, and tenant data from the AI service. This cannot be undone. Your credits will NOT be preserved.', 'wpforo' ); ?></p>
			</div>
			<p>
				<label>
					<input type="checkbox" name="confirm" value="1" required>
					<?php _e( 'I understand that all my data will be permanently deleted and want to proceed', 'wpforo' ); ?>
				</label>
			</p>
			<p>
				<label for="disconnect-purge-reason"><?php _e( 'Reason (optional):', 'wpforo' ); ?></label>
				<textarea id="disconnect-purge-reason" name="reason" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Help us improve by telling us why you\'re removing your data', 'wpforo' ); ?>"></textarea>
			</p>
		</form>
	</div>
	<?php
}

/**
 * Render error state (generic errors, not expiry)
 *
 * @param WP_Error|array $error Error object or status data
 */
function wpforo_ai_render_error_state( $error ) {
	$is_wp_error = is_wp_error( $error );
	$error_message = $is_wp_error ? $error->get_error_message() : __( 'Unable to connect to AI service.', 'wpforo' );

	?>
	<div class="wpforo-ai-state wpforo-ai-error-state">
		<div class="wpforo-ai-box wpforo-ai-error-box">
			<div class="wpforo-ai-box-header">
				<h2><?php _e( 'Connection Issue', 'wpforo' ); ?></h2>
			</div>
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-status-badge status-error">
					<span class="dashicons dashicons-dismiss"></span>
					<?php _e( 'Error', 'wpforo' ); ?>
				</div>

				<div class="wpforo-ai-error-message">
					<p><strong><?php echo esc_html( $error_message ); ?></strong></p>
				</div>

				<div class="wpforo-ai-error-actions">
					<form method="post" action="">
						<?php wp_nonce_field( 'wpforo_ai_refresh_status' ); ?>
						<input type="hidden" name="wpforo_ai_action" value="refresh_status">
						<button type="submit" class="button button-primary">
							<span class="dashicons dashicons-update"></span>
							<?php _e( 'Retry Connection', 'wpforo' ); ?>
						</button>
					</form>

					<?php if ( ! $is_wp_error ) : ?>
						<?php
						$err_provider = get_option( 'wpforo_ai_payment_provider', 'freemius' );
						?>
						<a href="<?php echo esc_url( wpforo_ai_get_manage_subscription_url( $err_provider ) ); ?>" class="button button-secondary wpf-manage-subscription wpf-provider-<?php echo esc_attr( $err_provider ); ?>" target="_blank">
							<span class="dashicons dashicons-admin-generic"></span>
							<?php printf( __( 'Manage Subscription (%s)', 'wpforo' ), esc_html( ucfirst( $err_provider ) ) ); ?>
						</a>
					<?php endif; ?>
				</div>

				<div class="wpforo-ai-support-info">
					<p>
						<?php
						printf(
							__( 'Need help? %s', 'wpforo' ),
							'<a href="https://v3.wpforo.com/login-register/?tab=login" target="_blank">' . __( 'Open Support Ticket', 'wpforo' ) . '</a>'
						);
						?>
					</p>
				</div>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Check if a feature is enabled based on plan hierarchy
 *
 * @param string $current_plan Current user's plan
 * @param string $feature_plan Feature's required plan
 * @return bool True if feature should be enabled
 */
function wpforo_ai_is_feature_enabled_by_plan( $current_plan, $feature_plan ) {
	// Plan hierarchy (lower to higher)
	$plan_hierarchy = array(
		'free_trial'   => 0,
		'starter'      => 0, // Starter and free_trial are same level
		'professional' => 1,
		'business'     => 2,
		'enterprise'   => 3,
	);

	$current_level = isset( $plan_hierarchy[ $current_plan ] ) ? $plan_hierarchy[ $current_plan ] : 0;
	$required_level = isset( $plan_hierarchy[ $feature_plan ] ) ? $plan_hierarchy[ $feature_plan ] : 0;

	// Feature is enabled if current plan level >= required plan level
	return $current_level >= $required_level;
}

/**
 * Render features list
 *
 * @param array $enabled_features List of enabled feature IDs
 * @param array $usage Usage statistics per feature
 * @param bool  $is_free_trial Whether on free trial
 * @param array $status Tenant status data from API (required for plan detection)
 */
function wpforo_ai_render_features_list( $enabled_features, $usage, $is_free_trial, $status = null ) {
	$all_features = wpforo_ai_get_all_features();

	// Ensure $enabled_features is an array
	if ( ! is_array( $enabled_features ) ) {
		$enabled_features = [];
	}

	// Ensure $usage is an array
	if ( ! is_array( $usage ) ) {
		$usage = [];
	}

	// Get current plan from status (passed from parent function)
	$current_plan = 'free_trial';
	if ( isset( $status['subscription']['plan'] ) ) {
		$current_plan = $status['subscription']['plan'];
	} elseif ( ! $is_free_trial ) {
		// If not free trial but no plan set, assume starter
		$current_plan = 'starter';
	}

	// Group features by plan
	$features_by_plan = [
		'starter'      => [],
		'professional' => [],
		'business'     => [],
		'enterprise'   => [],
	];

	foreach ( $all_features as $feature_id => $feature ) {
		$feature_plan = isset( $feature['plan'] ) ? $feature['plan'] : 'professional';
		if ( isset( $features_by_plan[ $feature_plan ] ) ) {
			$features_by_plan[ $feature_plan ][ $feature_id ] = $feature;
		}
	}

	// Plan display names
	$plan_names = [
		'starter'      => __( 'Starter Features', 'wpforo' ),
		'professional' => __( 'Professional Features', 'wpforo' ),
		'business'     => __( 'Business Features', 'wpforo' ),
		'enterprise'   => __( 'Enterprise Features', 'wpforo' ),
	];

	// Render accordion for each plan
	echo '<div class="wpforo-ai-features-accordion">';

	foreach ( $features_by_plan as $plan_level => $features ) {
		if ( empty( $features ) ) {
			continue;
		}

		$plan_id = 'plan-' . $plan_level;
		$is_plan_unlocked = wpforo_ai_is_feature_enabled_by_plan( $current_plan, $plan_level );
		$is_current_plan = ( $current_plan === $plan_level );
		$plan_status_icon = $is_plan_unlocked ? 'dashicons-yes-alt' : 'dashicons-lock';
		$plan_status_class = $is_plan_unlocked ? 'plan-unlocked' : 'plan-locked';

		echo '<div class="accordion-item ' . esc_attr( $plan_status_class ) . ( $is_current_plan ? ' current-plan' : '' ) . '">';
		echo '<button class="accordion-header" type="button" data-plan="' . esc_attr( $plan_level ) . '" aria-expanded="false" aria-controls="' . esc_attr( $plan_id ) . '">';
		echo '<span class="accordion-status-icon dashicons ' . esc_attr( $plan_status_icon ) . '"></span>';
		echo '<span class="accordion-title">' . esc_html( $plan_names[ $plan_level ] ) . '</span>';
		echo '<span class="accordion-icon dashicons dashicons-arrow-down-alt2"></span>';
		echo '</button>';

		echo '<div class="accordion-content" id="' . esc_attr( $plan_id ) . '" style="display: none;">';
		echo '<ul class="wpforo-ai-features-list">';

		foreach ( $features as $feature_id => $feature ) {
			// Check if feature is enabled based on plan hierarchy
			$is_enabled = in_array( $feature_id, $enabled_features ) || $is_plan_unlocked;
			$usage_count = (int) wpfval( $usage, $feature_id, 0 );
			$css_class = $is_enabled ? 'feature-enabled' : 'feature-locked';

			echo '<li class="' . esc_attr( $css_class ) . '">';
			echo '<span class="feature-icon">' . wpforo_ai_get_feature_icon( $feature_id ) . '</span>';
			echo '<div class="feature-info">';
			echo '<strong>' . esc_html( $feature['name'] ) . '</strong>';
			echo '<p>' . esc_html( $feature['description'] ) . '</p>';
			echo '</div>';



			echo '</li>';
		}

		echo '</ul>';
		echo '</div>'; // .accordion-content
		echo '</div>'; // .accordion-item
	}

	echo '</div>'; // .wpforo-ai-features-accordion
}

/**
 * Render features preview list (for "What You'll Get" section)
 * Shows all features except enterprise plan when no API key is registered
 */
function wpforo_ai_render_features_preview() {
	$features = wpforo_ai_get_all_features();

	// Only WooCommerce Products indexing is coming soon
	$coming_soon_features = [ 'woocommerce_products_indexing' ];

	// Plan display names and colors
	$plan_labels = [
		'starter'      => [ 'label' => __( 'Free / Starter', 'wpforo' ), 'class' => 'plan-starter' ],
		'professional' => [ 'label' => __( 'Professional', 'wpforo' ), 'class' => 'plan-professional' ],
		'business'     => [ 'label' => __( 'Business', 'wpforo' ), 'class' => 'plan-business' ],
		'enterprise'   => [ 'label' => __( 'Enterprise', 'wpforo' ), 'class' => 'plan-enterprise' ],
	];

	echo '<ul class="wpforo-ai-features-list wpforo-ai-features-preview">';

	foreach ( $features as $feature_id => $feature ) {
		// Skip enterprise features in preview
		if ( isset( $feature['plan'] ) && $feature['plan'] === 'enterprise' ) {
			continue;
		}

		$is_coming_soon = in_array( $feature_id, $coming_soon_features, true );
		$class          = $is_coming_soon ? 'feature-pending' : 'feature-enabled';
		$plan           = $feature['plan'] ?? 'starter';
		$plan_info      = $plan_labels[ $plan ] ?? $plan_labels['starter'];

		echo '<li class="' . esc_attr( $class ) . ' ' . esc_attr( $plan_info['class'] ) . '">';

		// Feature icon
		echo '<span class="feature-icon">' . wpforo_ai_get_feature_icon( $feature_id ) . '</span>';

		// Feature info
		echo '<div class="feature-info">';
		echo '<strong>' . esc_html( $feature['name'] ) . '</strong>';
		if ( $is_coming_soon ) {
			echo '<span class="coming-soon-badge">' . esc_html__( 'Coming Soon', 'wpforo' ) . '</span>';
		}
		echo '<p>' . esc_html( $feature['description'] ) . '</p>';
		echo '</div>';

		// Plan badge
		echo '<span class="plan-badge ' . esc_attr( $plan_info['class'] ) . '">' . esc_html( $plan_info['label'] ) . '</span>';

		echo '</li>';
	}

	// Add "More Coming Soon" teaser
	echo '<li class="feature-teaser">';
	echo '<span class="feature-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>';
	echo '<div class="feature-info">';
	echo '<strong>' . __( 'And Many More AI Features Coming Soon!', 'wpforo' ) . '</strong>';
	echo '<p>' . __( 'This is just the beginning. We\'re actively developing additional AI features including sentiment analysis, member retention predictions, personalized content feeds, and advanced automation tools. Join our growing community and help shape the future of forum AI.', 'wpforo' ) . '</p>';
	echo '</div>';
	echo '<span class="plan-badge plan-enterprise">' . esc_html__( 'For All Plans', 'wpforo' ) . '</span>';
	echo '</li>';

	echo '</ul>';
}

/**
 * Get all available features with metadata
 *
 * @return array Features array
 */
function wpforo_ai_get_all_features() {
	return apply_filters( 'wpforo_ai_features', [
        'content_indexing'     => [
            'name'        => __( 'AI Knowledge Generation - Forum Content Indexing & Vectorization', 'wpforo' ),
            'description' => __( 'Index forum content to build a searchable vector database optimized for Retrieval Augmented Generation (RAG). This feature transforms unstructured forum discussions, threads, and posts into semantic vectors, enabling AI that truly understands your domain, products, and audience.', 'wpforo' ),
            'plan'        => 'starter',
            'preview'     => false,
        ],
        'vector_db_local_storage' => [
            'name'        => __( 'AI Indexed Vector Database - Local Storage', 'wpforo' ),
            'description' => __( 'Store AI-generated vector embeddings locally on your WordPress server. Perfect for smaller forums or sites with storage capacity. Vectors are stored in your database, giving you full control over your data while enabling semantic search and AI features without external dependencies.', 'wpforo' ),
            'plan'        => 'starter',
            'preview'     => false,
        ],
        'semantic_search'      => [
            'name'        => __( "AI Powered Semantic Search", 'wpforo' ),
            'description' => __( "Traditional search fails when users don't know exact keywords. Stop losing members to \"no results found\". AI-powered semantic search understands what users mean, not just what they type. Find relevant discussions even with different wording, typos, or synonyms—delivering answers that actually help.", 'wpforo' ),
            'plan'        => 'starter',
            'preview'     => true,
		],
		'search_enhance'       => [
            'name'        => __( 'AI Search Enhancement', 'wpforo' ),
            'description' => __( 'Elevate search results with AI-powered summaries and recommendations. When users search, AI analyzes the results and generates a contextual summary explaining how each result relates to the query. Get intelligent suggestions for related topics and better answers instantly.', 'wpforo' ),
            'plan'        => 'starter',
            'preview'     => true,
		],
		'topic_summary' => [
            'name'        => __( 'AI Topic Summarization', 'wpforo' ),
            'description' => __( 'Get instant AI-generated summaries of long forum discussions. Help members quickly understand the key points, main conclusions, and important details of any topic without reading through hundreds of replies. Perfect for catching up on active discussions or reviewing complex threads at a glance.', 'wpforo' ),
            'plan'        => 'starter',
            'preview'     => true,
        ],
        'smart_topic_suggestions' => [
            'name'        => __( 'AI Topic Suggestions', 'wpforo' ),
            'description' => __( 'Enhance topic creation with intelligent suggestions. AI analyzes what members are typing and instantly recommends similar existing discussions, related topics, and potential answers. Reduce duplicate posts, improve search visibility, and help users discover solutions before they even ask—all in real-time.', 'wpforo' ),
            'plan'        => 'starter',
            'preview'     => true,
        ],
        'multi_language_translation' => [
            'name'        => __( 'AI Multi-Language Translation', 'wpforo' ),
            'description' => __( 'Break language barriers and grow your global community. No more limiting your community to one language. Multi-language AI translation lets members from anywhere participate naturally. Each user sees content in their language, posts in their language, and AI bridges the gap—no manual translation needed.', 'wpforo' ),
            'plan'        => 'starter',
            'preview'     => true,
        ],
        'ai_spam_detection' => [
            'name'        => __( 'AI Moderation - Spam Detection and User Banning', 'wpforo' ),
            'description' => __( 'Keep your forum clean with intelligent spam detection. AI automatically identifies spam content after post submission, blocking malicious posts before they appear while learning from your moderation decisions to improve accuracy.', 'wpforo' ),
            'plan'        => 'starter',
            'preview'     => true,
        ],
        'ai_toxicity_detection' => [
            'name'        => __( 'AI Moderation - Content Safety & Toxicity Detection', 'wpforo' ),
            'description' => __( 'Protect your community from harmful content with advanced toxicity detection. AI analyzes posts for hate speech, harassment, personal attacks, threats, and other toxic behavior. Configurable severity thresholds let you set how aggressively to filter content, while automated actions like unapproval or user banning help maintain a respectful environment without constant manual moderation.', 'wpforo' ),
            'plan'        => 'starter',
            'preview'     => false,
        ],
        'ai_rule_compliance' => [
            'name'        => __( 'AI Moderation - Rule Compliance & Policy Enforcement', 'wpforo' ),
            'description' => __( 'Automatically enforce your forum rules and community guidelines. AI checks every post against configurable policies including topic relevance, promotional content restrictions, and illegal content detection. Smart keyword pre-filtering catches obvious violations instantly without API calls, while nuanced cases receive full AI analysis. Define custom rules for your community and let AI ensure members stay on topic and follow your guidelines.', 'wpforo' ),
            'plan'        => 'starter',
            'preview'     => false,
        ],
        'forum_image_indexing' => [
            'name'        => __( 'AI Knowledge Generation - Forum Image Indexing & Vectorization', 'wpforo' ),
            'description' => __( 'Unlock the power of visual content in your forum. AI automatically analyzes images embedded in posts—screenshots, diagrams, infographics, product photos—and generates rich semantic embeddings. Visual information is indexed alongside text, enabling AI search and chat to understand and retrieve content based on what images show, not just surrounding text. Perfect for technical forums, support communities, and visual-heavy discussions.', 'wpforo' ),
            'plan'        => 'professional',
            'preview'     => false,
        ],
        'forum_document_indexing' => [
            'name'        => __( 'AI Knowledge Generation - Forum Document Indexing & Vectorization', 'wpforo' ),
            'description' => __( 'Transform document attachments into searchable knowledge. AI automatically extracts text from PDF, Word (DOCX), PowerPoint (PPTX), RTF, and plain text files attached to forum posts. Extracted content is chunked, embedded, and indexed alongside regular post text, making document contents fully searchable through AI semantic search and accessible to the AI chatbot. Ideal for forums with manuals, guides, reports, and technical documentation shared as attachments.', 'wpforo' ),
            'plan'        => 'professional',
            'preview'     => false,
        ],
        'ai_bot_reply'         => [
            'name'        => __( 'AI Bot Reply and Reply Suggestion', 'wpforo' ),
            'description' => __( 'Generate AI-powered replies manually with a single click or use the Suggest Reply button to get AI-generated content directly in the editor. Moderators can trigger bot replies from any post, or use the suggest feature to draft responses based on forum context, topic content, and AI knowledge base.', 'wpforo' ),
            'plan'        => 'professional',
            'preview'     => true,
        ],
        'ai_topic_generator'   => [
            'name'        => __( 'AI Tasks - Automated Topic Generation', 'wpforo' ),
            'description' => __( 'Keep your forum active with AI-generated topics! The AI analyzes your existing discussions and automatically creates engaging threads based on your instructions every day or in any time period you want. Topics appear naturally from AI created users or pre-defined users, sparking fresh conversations. The AI can search for trending news and current issues related to your community, then create timely, relevant topics that drive engagement.', 'wpforo' ),
            'plan'        => 'professional',
            'preview'     => true,
        ],
        'ai_reply_generator'   => [
            'name'        => __( 'AI Tasks - Automated Reply Generation', 'wpforo' ),
            'description' => __( 'Boost engagement with intelligent reply generation. AI monitors your forum for unanswered topics, analyzes context from existing discussions and knowledge base, and automatically posts helpful, relevant responses through bot users. Configure reply frequency, tone, and trigger conditions to maintain authentic community interaction.', 'wpforo' ),
            'plan'        => 'professional',
            'preview'     => true,
        ],
        'auto_tag_generation'  => [
            'name'        => __( 'AI Tasks - Automated Topic Tag Moderation', 'wpforo' ),
            'description' => __( 'Improve content discovery with smart tag management. Complete tag management powered by AI. Automatically generates relevant tags for new and existing topics, removes outdated or irrelevant tags, maintains consistent tag vocabulary, identifies duplicate tags, and suggests related keywords. Self-cleaning system ensures your tag structure stays organized as your forum evolves while leaving existing topics untouched to preserve forum integrity.', 'wpforo' ),
            'plan'        => 'professional',
            'preview'     => true,
        ],
        'analytics_insights'   => [
            'name'        => __( 'AI Analytics & Insights', 'wpforo' ),
            'description' => __( 'Stop guessing what your community wants. AI Analytics reveals hidden patterns in member behavior. Comprehensive AI-driven analytics for smarter community management. Monitor real-time engagement metrics, track topic performance, identify trending discussions, discover underserved content areas, and receive personalized growth recommendations. Advanced sentiment analysis, member retention insights, and predictive trends help you stay ahead.', 'wpforo' ),
            'plan'        => 'professional',
            'preview'     => true,
        ],
        'ai_assistant_chatbot' => [
            'name'        => __( 'AI Chat Assistant', 'wpforo' ),
            'description' => __( '24/7 intelligent assistance for your forum members. Intelligent chatbot powered by your forum\'s content. Provides instant answers based on indexed discussions, suggests relevant topics, guides new members through common tasks, and escalates complex questions to moderators when needed. Learns from interactions, supports multiple languages, and maintains your community\'s tone and style.', 'wpforo' ),
            'plan'        => 'business',
            'preview'     => false,
        ],
        'vector_db_cloud_storage' => [
            'name'        => __( 'AI Indexed Vector Database - Cloud Storage', 'wpforo' ),
            'description' => __( 'Scale your AI capabilities with cloud-based vector storage powered by AWS S3 Vectors. Ideal for large forums with thousands of posts and growing communities. Cloud storage provides unlimited scalability, faster query performance, automatic backups, and enterprise-grade reliability for your AI knowledge base.', 'wpforo' ),
            'plan'        => 'business',
            'preview'     => false,
        ],
        'extended_knowledge_base' => [
            'name'        => __( 'Extended Knowledge Base', 'wpforo' ),
            'description' => __( 'Expand AI knowledge beyond forum content to include WordPress posts, pages, and products.', 'wpforo' ),
            'plan'        => 'business',
            'preview'     => false,
		],
        'wordpress_content_indexing' => [
			'name'        => __( 'WordPress Content Indexing', 'wpforo' ),
			'description' => __( 'Index WordPress posts and pages for comprehensive site-wide AI search.', 'wpforo' ),
			'plan'        => 'business',
			'preview'     => false,
		],
		'custom_post_types_indexing' => [
			'name'        => __( 'Custom Post Types Indexing', 'wpforo' ),
			'description' => __( 'Include custom post types in AI knowledge base for complete content coverage.', 'wpforo' ),
			'plan'        => 'business',
			'preview'     => false,
		],
		'woocommerce_products_indexing' => [
			'name'        => __( 'WooCommerce Products Indexing', 'wpforo' ),
			'description' => __( 'Index WooCommerce products to provide AI-powered product search and support.', 'wpforo' ),
			'plan'        => 'business',
			'preview'     => false,
		],
		'developer_features'   => [
			'name'        => __( 'Developer Features', 'wpforo' ),
			'description' => __( 'Advanced developer tools and API access for custom integrations.', 'wpforo' ),
			'plan'        => 'enterprise',
			'preview'     => false,
		],
		'rest_api_access'      => [
			'name'        => __( 'REST API Access', 'wpforo' ),
			'description' => __( 'Full REST API access for custom applications and third-party integrations.', 'wpforo' ),
			'plan'        => 'enterprise',
			'preview'     => false,
		],
		'custom_ai_models'     => [
			'name'        => __( 'Custom AI Models', 'wpforo' ),
			'description' => __( 'Use custom-trained AI models tailored to your specific community needs.', 'wpforo' ),
			'plan'        => 'enterprise',
			'preview'     => false,
		],
		'custom_feature_development' => [
			'name'        => __( 'Custom feature development', 'wpforo' ),
			'description' => __( 'Dedicated development resources for custom AI features unique to your forum.', 'wpforo' ),
			'plan'        => 'enterprise',
			'preview'     => false,
		],
		'premium_support'      => [
			'name'        => __( 'Premium Support', 'wpforo' ),
			'description' => __( 'Priority support with dedicated account management and faster response times.', 'wpforo' ),
			'plan'        => 'enterprise',
			'preview'     => false,
		],
		'dedicated_account_manager' => [
			'name'        => __( 'Dedicated account manager', 'wpforo' ),
			'description' => __( 'Personal account manager for strategic guidance and priority assistance.', 'wpforo' ),
			'plan'        => 'enterprise',
			'preview'     => false,
		],
		'enterprise_capabilities' => [
			'name'        => __( 'Enterprise Capabilities', 'wpforo' ),
			'description' => __( 'Enterprise-grade features including SLA, compliance support, and advanced security.', 'wpforo' ),
			'plan'        => 'enterprise',
			'preview'     => false,
		],
	] );
}

/**
 * Get SVG icon for a feature
 *
 * @param string $feature_id Feature identifier
 * @return string SVG icon markup
 */
function wpforo_ai_get_feature_icon( $feature_id ) {
	$icons = [
		// Free Features - Vector Database Storage
		'vector_db_local_storage' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4 3 9 3s9-1.34 9-3"/><path d="M12 12v5"/><path d="M9 14.5l3 2.5 3-2.5"/></svg>',
		'vector_db_cloud_storage' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/><path d="M12 13v5"/><path d="M9.5 15.5l2.5 2.5 2.5-2.5"/></svg>',
		// Starter Features
		'semantic_search' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="M11 8a3 3 0 0 0-3 3"/></svg>',
		'content_indexing' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4 3 9 3s9-1.34 9-3"/></svg>',
		'image_indexing' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>',
		'document_indexing' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>',
		'multi_language_translation' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>',
		'topic_summary' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m21.64 3.64-1.28-1.28a1.21 1.21 0 0 0-1.72 0L2.36 18.64a1.21 1.21 0 0 0 0 1.72l1.28 1.28a1.2 1.2 0 0 0 1.72 0L21.64 5.36a1.2 1.2 0 0 0 0-1.72Z"/><path d="m14 7 3 3"/><path d="M5 6v4"/><path d="M19 14v4"/><path d="M10 2v2"/><path d="M7 8H3"/><path d="M21 16h-4"/><path d="M11 3H9"/></svg>',
		'smart_topic_suggestions' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/></svg>',
		'topic_suggestions' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/></svg>',
		'ai_topic_generator' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.375 2.625a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4Z"/></svg>',
		'ai_reply_generator' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 10h8"/><path d="M8 14h4"/></svg>',
		'ai_bot_reply' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="10" x="3" y="11" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/><path d="M8 16h0"/><path d="M16 16h0"/><path d="m2 2 2 2"/><path d="m22 2-2 2"/></svg>',
		'forum_image_indexing' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/><path d="M14 3v4"/><path d="M21 10h-4"/></svg>',
		'forum_document_indexing' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M14 2v4"/><path d="M20 9h-4"/></svg>',
		'automatic_faq_generation' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>',
		'auto_tag_generation' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>',
		'analytics_insights' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>',
		// Professional Features - Content Moderation
		'ai_spam_detection' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>',
		'ai_toxicity_detection' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>',
		'ai_rule_compliance' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="m9 15 2 2 4-4"/></svg>',
		'unified_moderation' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>',
		'ai_assistant_chatbot' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="10" x="3" y="11" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/><path d="M8 16h0"/><path d="M16 16h0"/></svg>',
		// Business Features
		'extended_knowledge_base' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/><path d="M8 7h6"/><path d="M8 11h8"/></svg>',
		'wordpress_content_indexing' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>',
		'custom_post_types_indexing' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/></svg>',
		'woocommerce_products_indexing' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>',
		// Enterprise Features
		'developer_features' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
		'rest_api_access' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/><circle cx="18" cy="7" r="3"/><circle cx="6" cy="11" r="3"/></svg>',
		'custom_ai_models' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a4 4 0 0 0-4 4v2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2h-2V6a4 4 0 0 0-4-4z"/><circle cx="12" cy="14" r="2"/><path d="M12 16v2"/></svg>',
		'custom_feature_development' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>',
		'premium_support' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5Zm0 0a9 9 0 1 1 18 0m0 0v5a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3Z"/></svg>',
		'dedicated_account_manager' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
		'enterprise_capabilities' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/></svg>',
	];

	// Return the icon or a default one
	if ( isset( $icons[ $feature_id ] ) ) {
		return $icons[ $feature_id ];
	}

	// Default icon (star)
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
}

/**
 * Render usage statistics
 *
 * @param array  $usage Usage data (since last credit purchase)
 * @param int    $total_credits Total credits used
 * @param array  $usage_lifetime Lifetime usage data (optional)
 * @param string $current_plan Current subscription plan (optional)
 */
function wpforo_ai_render_usage_stats( $usage, $total_credits, $usage_lifetime = [], $current_plan = 'free_trial' ) {
	// Ensure $total_credits is an integer
	$total_credits = (int) $total_credits;

	$features = wpforo_ai_get_all_features();
	$has_lifetime = ! empty( $usage_lifetime );

	echo '<div class="wpforo-ai-usage-stats">';
	echo '<div class="usage-summary">';
	echo '<div class="usage-stat-item">';
	echo '<span class="stat-icon dashicons dashicons-chart-bar"></span>';
	echo '<div class="stat-info">';
	echo '<strong>' . number_format( $total_credits ) . '</strong>';
	echo '<span class="stat-label">' . __( 'Lifetime Credits Used', 'wpforo' ) . '</span>';
	echo '</div>';
	echo '</div>';
	echo '</div>';

	echo '<table class="wpforo-ai-usage-table">';
	echo '<thead>';
	echo '<tr>';
	echo '<th>' . __( 'Feature', 'wpforo' ) . '</th>';
	echo '<th>' . __( 'Since Last Purchase', 'wpforo' ) . '</th>';
	if ( $has_lifetime ) {
		echo '<th>' . __( 'Lifetime Total', 'wpforo' ) . '</th>';
	}
	echo '</tr>';
	echo '</thead>';
	echo '<tbody>';

	// Get all features available for the current plan
	$plan_features = [];
	// Skip storage features and sub-indexing features (their credits are summed into content_indexing)
	$skip_features = [ 'vector_db_local_storage', 'vector_db_cloud_storage', 'forum_image_indexing', 'forum_document_indexing' ];
	foreach ( $features as $feature_id => $feature_data ) {
		// Skip storage features from usage statistics
		if ( in_array( $feature_id, $skip_features, true ) ) {
			continue;
		}

		$feature_plan = isset( $feature_data['plan'] ) ? $feature_data['plan'] : 'starter';
		$feature_status = isset( $feature_data['status'] ) ? $feature_data['status'] : 'available';

		// Only include features that are enabled for this plan and not "coming soon"
		if ( wpforo_ai_is_feature_enabled_by_plan( $current_plan, $feature_plan ) && $feature_status !== 'coming_soon' ) {
			$plan_features[] = $feature_id;
		}
	}

	// If no plan features found, fall back to usage keys
	if ( empty( $plan_features ) ) {
		$plan_features = $has_lifetime ? array_keys( $usage_lifetime ) : array_keys( $usage );
	}

	foreach ( $plan_features as $feature_id ) {
		$feature_name = '';
		if ( isset( $features[ $feature_id ]['name'] ) ) {
			$feature_name = $features[ $feature_id ]['name'];
		}
		if ( ! $feature_name ) {
			$feature_name = ucwords( str_replace( '_', ' ', $feature_id ) );
		}

		// Get counts (since last purchase and lifetime)
		$count_since_purchase = isset( $usage[ $feature_id ] ) ? (int) $usage[ $feature_id ] : 0;
		$count_lifetime = $has_lifetime && isset( $usage_lifetime[ $feature_id ] ) ? (int) $usage_lifetime[ $feature_id ] : 0;

		// For content_indexing, include image and document indexing credits
		if ( 'content_indexing' === $feature_id ) {
			$count_since_purchase += ( isset( $usage['multimodal_image_indexing'] ) ? (int) $usage['multimodal_image_indexing'] : 0 );
			$count_since_purchase += ( isset( $usage['document_indexing'] ) ? (int) $usage['document_indexing'] : 0 );
			if ( $has_lifetime ) {
				$count_lifetime += ( isset( $usage_lifetime['multimodal_image_indexing'] ) ? (int) $usage_lifetime['multimodal_image_indexing'] : 0 );
				$count_lifetime += ( isset( $usage_lifetime['document_indexing'] ) ? (int) $usage_lifetime['document_indexing'] : 0 );
			}
		}

		echo '<tr>';
		echo '<td>' . esc_html( $feature_name ) . '</td>';
		echo '<td><strong>' . number_format( $count_since_purchase ) . '</strong></td>';
		if ( $has_lifetime ) {
			echo '<td><strong>' . number_format( $count_lifetime ) . '</strong></td>';
		}
		echo '</tr>';
	}

	echo '</tbody>';
	echo '</table>';
	echo '</div>';
}

/**
 * Render pricing table
 *
 * @param string $tenant_id Tenant ID for upgrade URLs
 * @param array  $subscription Current subscription data (optional, for showing active plan)
 */
function wpforo_ai_render_pricing_table( $tenant_id, $subscription = [] ) {
	// Get current plan and expiration date
	$current_plan = isset( $subscription['plan'] ) ? $subscription['plan'] : 'free_trial';
	$renews_at    = isset( $subscription['renews_at'] ) ? $subscription['renews_at'] : '';
	$expires_at   = isset( $subscription['expires_at'] ) ? $subscription['expires_at'] : '';

	// Plan hierarchy for disabling lower plan buttons
	$plan_levels = [
		'free_trial'   => 0,
		'free'         => 0,
		'starter'      => 1,
		'professional' => 2,
		'business'     => 3,
		'enterprise'   => 4,
	];
	$current_level = isset( $plan_levels[ $current_plan ] ) ? $plan_levels[ $current_plan ] : 0;

	// Fetch dynamic pricing from Freemius API (with fallback to defaults)
	$pricing = wpforo_ai_get_freemius_pricing();
	$plans = $pricing['plans'];

	?>
	<div class="wpforo-ai-callout">
		<span class="dashicons dashicons-info"></span>
		<div>
			<strong><?php _e( 'Credit-Based Pricing', 'wpforo' ); ?></strong>
			<p><?php _e( 'Pay only for what you use with flexible credit-based pricing. Credits roll over month-to-month as long as you have an active subscription plan. Each plan allows credit accumulation up to one year of subscription plan credits. Start with 500 free credits, then choose a plan that fits your forum\'s activity level.', 'wpforo' ); ?><br><?php _e('You can select the quality of AI model for each feature individually: ', 'wpforo') ?></p>
		    <ul style="list-style: square; padding: 0 18px; margin: 5px 0 0 0;">
                <li style="margin-bottom: 1px;"><?php _e( 'Fast (1 action = 1 credit)', 'wpforo' ); ?></li>
                <li style="margin-bottom: 1px;"><?php _e( 'Balanced (1 action = 2 credits)', 'wpforo' ); ?></li>
                <li style="margin-bottom: 1px;"><?php _e( 'Advanced (1 action = 3 credits)', 'wpforo' ); ?></li>
                <li style="margin-bottom: 1px;"><?php _e( 'Premium (1 action = 4 credits)', 'wpforo' ); ?></li>
            </ul>
        </div>
	</div>
	<div class="wpforo-ai-pricing-table">
		<table class="pricing-table-modern">
			<thead>
				<tr>
					<th class="feature-column"><?php _e( 'AI Features', 'wpforo' ); ?></th>
					<th class="plan-column plan-starter <?php echo $current_plan === 'starter' ? 'plan-active' : ''; ?>">
						<div class="plan-header">
							<?php if ( $current_plan === 'starter' ) : ?>
								<div class="plan-active-badge"><?php _e( 'YOUR PLAN', 'wpforo' ); ?></div>
							<?php endif; ?>
							<div class="plan-name"><?php _e( 'Starter', 'wpforo' ); ?></div>
							<div class="plan-price">
								<span class="currency">$</span><span class="price-amount"><?php echo esc_html( $plans['starter']['price'] ); ?></span><small class="billing-period">/month</small>
							</div>
							<div class="plan-credits">
								<span class="dashicons dashicons-awards"></span>
								<strong>+1,000</strong> <?php _e( 'credits', 'wpforo' ); ?>
							</div>
						</div>
					</th>
					<th class="plan-column plan-professional <?php echo $current_plan === 'professional' ? 'plan-active' : ''; ?>">
						<div class="plan-header">
							<?php if ( $current_plan === 'professional' ) : ?>
								<div class="plan-active-badge"><?php _e( 'YOUR PLAN', 'wpforo' ); ?></div>
							<?php else : ?>
								<div class="plan-popular-badge"><?php _e( 'POPULAR', 'wpforo' ); ?></div>
							<?php endif; ?>
							<div class="plan-name"><?php _e( 'Professional', 'wpforo' ); ?></div>
							<div class="plan-price">
								<span class="currency">$</span><span class="price-amount"><?php echo esc_html( $plans['professional']['price'] ); ?></span><small class="billing-period">/month</small>
							</div>
							<div class="plan-credits">
								<span class="dashicons dashicons-awards"></span>
								<strong>+3,000</strong> <?php _e( 'credits', 'wpforo' ); ?>
							</div>
						</div>
					</th>
					<th class="plan-column plan-business <?php echo $current_plan === 'business' ? 'plan-active' : ''; ?>">
						<div class="plan-header">
							<?php if ( $current_plan === 'business' ) : ?>
								<div class="plan-active-badge"><?php _e( 'YOUR PLAN', 'wpforo' ); ?></div>
							<?php endif; ?>
							<div class="plan-name"><?php _e( 'Business', 'wpforo' ); ?></div>
							<div class="plan-price">
								<span class="currency">$</span><span class="price-amount"><?php echo esc_html( $plans['business']['price'] ); ?></span><small class="billing-period">/month</small>
							</div>
							<div class="plan-credits">
								<span class="dashicons dashicons-awards"></span>
								<strong>+9,000</strong> <?php _e( 'credits', 'wpforo' ); ?>
							</div>
						</div>
					</th>
					<th class="plan-column plan-enterprise <?php echo $current_plan === 'enterprise' ? 'plan-active' : ''; ?>">
						<div class="plan-header">
							<?php if ( $current_plan === 'enterprise' ) : ?>
								<div class="plan-active-badge"><?php _e( 'YOUR PLAN', 'wpforo' ); ?></div>
							<?php endif; ?>
							<div class="plan-name"><?php _e( 'Enterprise', 'wpforo' ); ?></div>
							<div class="plan-price">
								<span class="price-amount" style="font-weight: 400; font-size: 16px;"><?php _e( 'Custom Price', 'wpforo' ); ?></span>
							</div>
							<div class="plan-credits">
								<span class="dashicons dashicons-awards"></span>
								<strong style="font-weight: 500;"><?php _e( 'Custom', 'wpforo' ); ?></strong> <?php _e( 'credits', 'wpforo' ); ?>
							</div>
						</div>
					</th>
				</tr>
			</thead>
			<tbody>
				<!-- CTA Row -->
				<tr class="cta-row">
					<td></td>
					<td>
						<?php if ( $current_plan === 'starter' ) : ?>
							<div class="plan-active-info">
								<span class="dashicons dashicons-yes-alt"></span>
								<strong><?php _e( 'Active', 'wpforo' ); ?></strong>
								<?php if ( $renews_at ) : ?>
									<small><?php printf( __( 'Renews %s', 'wpforo' ), esc_html( wpforo_ai_format_date( $renews_at ) ) ); ?></small>
								<?php endif; ?>
							</div>
						<?php elseif ( $plan_levels['starter'] < $current_level ) : ?>
							<button type="button" class="button button-secondary button-cta" disabled>
								<?php _e( 'Upgrade', 'wpforo' ); ?>
							</button>
						<?php else : ?>
							<button type="button" class="button button-secondary button-cta wpforo-ai-upgrade-btn" data-plan="starter" data-tenant-id="<?php echo esc_attr( $tenant_id ); ?>">
								<?php $current_plan === 'free_trial' ? _e( 'Get Started', 'wpforo' ) : _e( 'Upgrade', 'wpforo' ); ?>
							</button>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $current_plan === 'professional' ) : ?>
							<div class="plan-active-info">
								<!-- <span class="dashicons dashicons-yes-alt"></span> -->
                                <strong><?php _e( 'Active', 'wpforo' ); ?></strong>
								<?php if ( $renews_at ) : ?>
									<small><?php printf( __( 'Renews %s', 'wpforo' ), esc_html( wpforo_ai_format_date( $renews_at ) ) ); ?></small>
								<?php endif; ?>
							</div>
						<?php elseif ( $plan_levels['professional'] < $current_level ) : ?>
							<button type="button" class="button button-secondary button-cta" disabled>
								<?php _e( 'Upgrade', 'wpforo' ); ?>
							</button>
						<?php else : ?>
							<button type="button" class="button button-secondary button-cta wpforo-ai-upgrade-btn" data-plan="professional" data-tenant-id="<?php echo esc_attr( $tenant_id ); ?>">
								<?php $current_plan === 'free_trial' ? _e( 'Get Started', 'wpforo' ) : _e( 'Upgrade', 'wpforo' ); ?>
							</button>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $current_plan === 'business' ) : ?>
							<div class="plan-active-info">
                                <!-- <span class="dashicons dashicons-yes-alt"></span> -->
                                <strong><?php _e( 'Active', 'wpforo' ); ?></strong>
								<?php if ( $renews_at ) : ?>
									<small><?php printf( __( 'Renews %s', 'wpforo' ), esc_html( wpforo_ai_format_date( $renews_at ) ) ); ?></small>
								<?php endif; ?>
							</div>
						<?php elseif ( $plan_levels['business'] < $current_level ) : ?>
							<button type="button" class="button button-secondary button-cta" disabled>
								<?php _e( 'Upgrade', 'wpforo' ); ?>
							</button>
						<?php else : ?>
							<button type="button" class="button button-secondary button-cta wpforo-ai-upgrade-btn" data-plan="business" data-tenant-id="<?php echo esc_attr( $tenant_id ); ?>">
								<?php $current_plan === 'free_trial' ? _e( 'Get Started', 'wpforo' ) : _e( 'Upgrade', 'wpforo' ); ?>
							</button>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $current_plan === 'enterprise' ) : ?>
							<div class="plan-active-info">
                                <!-- <span class="dashicons dashicons-yes-alt"></span> -->
                                <strong><?php _e( 'Active', 'wpforo' ); ?></strong>
								<?php if ( $renews_at ) : ?>
									<small><?php printf( __( 'Renews %s', 'wpforo' ), esc_html( wpforo_ai_format_date( $renews_at ) ) ); ?></small>
								<?php endif; ?>
							</div>
						<?php else : ?>
							<a href="https://v3.wpforo.com/gvectors-ai/#gvai-contact" target="_blank" class="button button-secondary" style="padding: 5px 20px;">
								<?php _e( 'Contact Us', 'wpforo' ) ?>
							</a>
						<?php endif; ?>
					</td>
				</tr>

				<!-- Core Features -->
				<tr class="feature-category">
					<td colspan="5"><span class="dashicons dashicons-yes-alt"></span> <?php _e( 'ALL Core AI Features', 'wpforo' ); ?></td>
				</tr>
                <tr>
                    <td><?php _e( 'AI Knowledge - Content Text Indexing', 'wpforo' ); ?></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>
                <tr>
                    <td><?php _e( 'AI Vector Database - Local Storage', 'wpforo' ); ?></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>
				<tr>
					<td><?php _e( 'AI Semantic Search', 'wpforo' ); ?></td>
					<td><span class="checkmark">✓</span></td>
					<td><span class="checkmark">✓</span></td>
					<td><span class="checkmark">✓</span></td>
					<td><span class="checkmark">✓</span></td>
				</tr>
                <tr>
                    <td><?php _e( 'AI Search Enhancement', 'wpforo' ); ?></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>
                <tr>
                    <td><?php _e( 'AI Topic Summarization', 'wpforo' ); ?></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>
                <tr>
                    <td><?php _e( 'AI Topic Suggestions', 'wpforo' ); ?></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>
                <tr>
                    <td><?php _e( 'AI Multi-Language Translation', 'wpforo' ); ?></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>
                <tr>
                    <td><strong><?php _e( 'AI Auto Moderation', 'wpforo' ); ?></strong></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>
                <tr class="feature-item">
                    <td><?php _e( 'Spam Detection and User Banning', 'wpforo' ); ?></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>
                <tr class="feature-item">
                    <td><?php _e( 'Content Safety & Toxicity Detection', 'wpforo' ); ?></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>
                <tr class="feature-item">
                    <td><?php _e( 'Rule Compliance & Policy Enforcement', 'wpforo' ); ?></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>
				<!-- Professional Features -->
				<tr class="feature-category">
					<td colspan="5"><span class="dashicons dashicons-shield-alt"></span> <?php _e( 'Professional Features', 'wpforo' ); ?></td>
				</tr>

                <tr>
                    <td><?php _e( 'AI Knowledge - Image Indexing', 'wpforo' ); ?></td>
                    <td><span class="crossmark">✗</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>
                <tr>
                    <td><?php _e( 'AI Knowledge - Document Indexing', 'wpforo' ); ?></td>
                    <td><span class="crossmark">✗</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>
                <tr>
                    <td><?php _e( 'AI Bot Reply', 'wpforo' ); ?></td>
                    <td><span class="crossmark">✗</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>
                <tr>
                    <td><?php _e( 'AI Reply Suggestion', 'wpforo' ); ?></td>
                    <td><span class="crossmark">✗</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>
                <tr>
                    <td><strong><?php _e( 'AI Tasks and Automations', 'wpforo' ); ?></strong></td>
                    <td><span class="crossmark">✗</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>
                <tr class="feature-item">
                    <td><?php _e( 'New Topic Creation', 'wpforo' ); ?></td>
                    <td><span class="crossmark">✗</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>
                <tr class="feature-item">
                    <td><?php _e( 'Auto Reply to Topics', 'wpforo' ); ?></td>
                    <td><span class="crossmark">✗</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>
                <tr class="feature-item">
                    <td><?php _e( 'Topic Tag Moderation', 'wpforo' ); ?></td>
                    <td><span class="crossmark">✗</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>
                <tr>
                    <td><strong><?php _e( 'AI Analytics & Insights', 'wpforo' ); ?></strong></td>
                    <td><span class="crossmark">✗</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>
                <tr class="feature-item">
                    <td><?php _e( 'AI Insights', 'wpforo' ); ?></td>
                    <td><span class="crossmark">✗</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>
                <tr class="feature-item">
                    <td><?php _e( 'AI Usage Analytics', 'wpforo' ); ?></td>
                    <td><span class="crossmark">✗</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>
                <tr class="feature-item">
                    <td><?php _e( 'Forum Activity', 'wpforo' ); ?></td>
                    <td><span class="crossmark">✗</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>
                <tr class="feature-item">
                    <td><?php _e( 'User Engagement', 'wpforo' ); ?></td>
                    <td><span class="crossmark">✗</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>
                <tr class="feature-item">
                    <td><?php _e( 'Content Performance', 'wpforo' ); ?></td>
                    <td><span class="crossmark">✗</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>


				<!-- Business Features -->
				<tr class="feature-category">
					<td colspan="5"><span class="dashicons dashicons-portfolio"></span> <?php _e( 'Business Features', 'wpforo' ); ?></td>
				</tr>
                <tr>
                    <td><?php _e( 'AI Chat Assistant', 'wpforo' ); ?></td>
                    <td><span class="crossmark">✗</span></td>
                    <td><span class="crossmark">✗</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>
                <tr>
                    <td><?php _e( 'AI Vector Database - Cloud Storage', 'wpforo' ); ?></td>
                    <td><span class="crossmark">✗</span></td>
                    <td><span class="crossmark">✗</span></td>
                    <td><span class="checkmark">✓</span></td>
                    <td><span class="checkmark">✓</span></td>
                </tr>
				<tr>
					<td><strong><?php _e( 'Extended Knowledge Base', 'wpforo' ); ?></strong></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="checkmark">✓</span></td>
					<td><span class="checkmark">✓</span></td>
				</tr>
				<tr class="feature-item">
					<td><?php _e( 'WordPress Content indexing', 'wpforo' ); ?></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="checkmark">✓</span></td>
					<td><span class="checkmark">✓</span></td>
				</tr>
				<tr class="feature-item">
					<td><?php  _e( 'Custom Post Types indexing', 'wpforo' ); ?> </td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="checkmark">✓</span></td>
					<td><span class="checkmark">✓</span></td>
				</tr>
				<tr class="feature-item">
					<td><?php _e( 'WooCommerce indexing', 'wpforo' ); echo '<span class="coming-soon-badge">' . esc_html__( 'Coming Soon', 'wpforo' ) . '</span>';  ?></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="checkmark">✓</span></td>
					<td><span class="checkmark">✓</span></td>
				</tr>

				<!-- Enterprise Features -->
				<tr class="feature-category">
					<td colspan="5"><span class="dashicons dashicons-admin-tools"></span> <?php _e( 'Enterprise Features', 'wpforo' ); ?></td>
				</tr>
				<tr>
					<td><strong><?php _e( 'Developer Features', 'wpforo' ); ?></strong></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="checkmark">✓</span></td>
				</tr>
				<tr class="feature-item">
					<td><?php _e( 'REST API Access', 'wpforo' ); ?></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="checkmark">✓</span></td>
				</tr>
				<tr class="feature-item">
					<td><?php _e( 'Custom AI Models', 'wpforo' ); ?></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="checkmark">✓</span></td>
				</tr>
				<tr class="feature-item">
					<td><?php _e( 'Custom feature development', 'wpforo' ); ?></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="checkmark">✓</span></td>
				</tr>
				<tr>
					<td><strong><?php _e( 'Premium Support', 'wpforo' ); ?></strong></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="checkmark">✓</span></td>
				</tr>
				<tr class="feature-item">
					<td><?php _e( 'Dedicated account manager', 'wpforo' ); ?></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="checkmark">✓</span></td>
				</tr>
				<tr class="feature-item">
					<td><?php _e( 'Enterprise Capabilities', 'wpforo' ); ?></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="crossmark">✗</span></td>
					<td><span class="checkmark">✓</span></td>
				</tr>

				<!-- Bottom CTA Row -->
				<tr class="cta-row">
					<td></td>
					<td>
						<?php if ( $current_plan === 'starter' ) : ?>
							<div class="plan-active-info">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php _e( 'Active', 'wpforo' ); ?>
							</div>
						<?php elseif ( $plan_levels['starter'] < $current_level ) : ?>
							<button type="button" class="button button-secondary button-cta" disabled>
								<?php _e( 'Upgrade', 'wpforo' ); ?>
							</button>
						<?php else : ?>
							<button type="button" class="button button-secondary button-cta wpforo-ai-upgrade-btn" data-plan="starter" data-tenant-id="<?php echo esc_attr( $tenant_id ); ?>">
								<?php _e( 'Upgrade', 'wpforo' ); ?>
							</button>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $current_plan === 'professional' ) : ?>
							<div class="plan-active-info">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php _e( 'Active', 'wpforo' ); ?>
							</div>
						<?php elseif ( $plan_levels['professional'] < $current_level ) : ?>
							<button type="button" class="button button-secondary button-cta" disabled>
								<?php _e( 'Upgrade', 'wpforo' ); ?>
							</button>
						<?php else : ?>
							<button type="button" class="button button-secondary button-cta wpforo-ai-upgrade-btn" data-plan="professional" data-tenant-id="<?php echo esc_attr( $tenant_id ); ?>">
								<?php _e( 'Upgrade', 'wpforo' ); ?>
							</button>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $current_plan === 'business' ) : ?>
							<div class="plan-active-info">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php _e( 'Active', 'wpforo' ); ?>
							</div>
						<?php elseif ( $plan_levels['business'] < $current_level ) : ?>
							<button type="button" class="button button-secondary button-cta" disabled>
								<?php _e( 'Upgrade', 'wpforo' ); ?>
							</button>
						<?php else : ?>
							<button type="button" class="button button-secondary button-cta wpforo-ai-upgrade-btn" data-plan="business" data-tenant-id="<?php echo esc_attr( $tenant_id ); ?>">
								<?php _e( 'Upgrade', 'wpforo' ); ?>
							</button>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $current_plan === 'enterprise' ) : ?>
							<div class="plan-active-info">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php _e( 'Active', 'wpforo' ); ?>
							</div>
						<?php else : ?>
                            <a href="https://v3.wpforo.com/gvectors-ai/#gvai-contact" target="_blank" class="button button-secondary" style="padding: 5px 20px;">
                                <?php _e( 'Contact Us', 'wpforo' ) ?>
                            </a>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<?php
	$enabled_providers = wpforo_ai_get_enabled_providers();
	$default_provider  = wpforo_ai_get_default_provider();
	$show_toggle       = count( $enabled_providers ) > 1;
	?>

	<?php if ( $show_toggle ) : ?>
		<!-- Payment Provider Toggle -->
		<div class="wpforo-ai-provider-toggle" style="margin-top: 15px; padding: 12px 20px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 6px; display: flex; align-items: center; gap: 20px;">
			<span style="font-weight: 500; color: #333;">
				<span class="dashicons dashicons-money-alt" style="vertical-align: middle; margin-right: 4px;"></span>
				<?php _e( 'Payment Method:', 'wpforo' ); ?>
			</span>
			<?php if ( in_array( 'paddle', $enabled_providers, true ) ) : ?>
				<label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
					<input type="radio" name="wpforo_ai_payment_provider" value="paddle" <?php checked( $default_provider, 'paddle' ); ?>>
					<?php _e( 'Paddle (Credit Card, PayPal)', 'wpforo' ); ?>
				</label>
			<?php endif; ?>
			<?php if ( in_array( 'freemius', $enabled_providers, true ) ) : ?>
				<label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
					<input type="radio" name="wpforo_ai_payment_provider" value="freemius" <?php checked( $default_provider, 'freemius' ); ?>>
					<?php _e( 'Freemius', 'wpforo' ); ?>
				</label>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="wpforo-ai-powered-by">
		<span class="dashicons dashicons-lock"></span>
		<?php
		if ( ! $show_toggle && in_array( 'paddle', $enabled_providers, true ) ) {
			_e( 'Payment and Subscription Management is Powered by Paddle - Secure Checkout', 'wpforo' );
		} elseif ( ! $show_toggle && in_array( 'freemius', $enabled_providers, true ) ) {
			_e( 'Payment and Subscription Management is Powered by Freemius - Secure Checkout', 'wpforo' );
		} else {
			_e( 'Secure Payment Processing', 'wpforo' );
		}
		?>
	</div>

	<!-- Checkout Configurations -->
	<script type="text/javascript">
		<?php if ( in_array( 'freemius', $enabled_providers, true ) ) : ?>
		// Freemius checkout configurations for each plan
		window.wpforoFreemiusCheckout = {
			tenant_id: <?php echo json_encode( $tenant_id ); ?>,
			plans: {
				starter: <?php echo json_encode( wpforo_ai_get_upgrade_url( $tenant_id, 'starter' ) ); ?>,
				professional: <?php echo json_encode( wpforo_ai_get_upgrade_url( $tenant_id, 'professional' ) ); ?>,
				business: <?php echo json_encode( wpforo_ai_get_upgrade_url( $tenant_id, 'business' ) ); ?>,
				enterprise: <?php echo json_encode( wpforo_ai_get_upgrade_url( $tenant_id, 'enterprise' ) ); ?>
			}
		};
		<?php endif; ?>

		<?php if ( in_array( 'paddle', $enabled_providers, true ) ) : ?>
		// Paddle checkout configurations
		<?php $paddle_pricing = wpforo_ai_get_paddle_pricing(); ?>
		window.wpforoPaddleCheckout = {
			tenant_id: <?php echo json_encode( $tenant_id ); ?>,
			plans: {
				starter: { price_id: <?php echo json_encode( $paddle_pricing['plans']['starter']['price_id'] ); ?> },
				professional: { price_id: <?php echo json_encode( $paddle_pricing['plans']['professional']['price_id'] ); ?> },
				business: { price_id: <?php echo json_encode( $paddle_pricing['plans']['business']['price_id'] ); ?> },
				enterprise: { price_id: <?php echo json_encode( $paddle_pricing['plans']['enterprise']['price_id'] ); ?> }
			},
			creditPacks: {
				'500': { price_id: <?php echo json_encode( $paddle_pricing['credit_packs']['500']['price_id'] ); ?> },
				'1500': { price_id: <?php echo json_encode( $paddle_pricing['credit_packs']['1500']['price_id'] ); ?> },
				'4500': { price_id: <?php echo json_encode( $paddle_pricing['credit_packs']['4500']['price_id'] ); ?> },
				'15000': { price_id: <?php echo json_encode( $paddle_pricing['credit_packs']['15000']['price_id'] ); ?> }
			}
		};
		<?php endif; ?>

		// Active payment provider and enabled providers
		window.wpforoPaymentProvider = <?php echo json_encode( $default_provider ); ?>;
		window.wpforoEnabledProviders = <?php echo json_encode( $enabled_providers ); ?>;
	</script>
	<?php
}

/**
 * Render credit packs section
 *
 * @param string $tenant_id Tenant ID for purchase URLs
 */
function wpforo_ai_render_credit_packs( $tenant_id ) {
	// Fetch dynamic pricing from Freemius API (with fallback to defaults)
	$pricing = wpforo_ai_get_freemius_pricing();
	$credit_packs = $pricing['credit_packs'];
	?>
	<div class="wpforo-ai-credit-packs-wrapper">
		<div class="credit-packs-header">
			<p class="credit-packs-description">
				<?php printf( __( 'Need more credits? Purchase one-time credit packs. Credits accumulate with your subscription credits up to your plan\'s maximum (one year of subscription plan credits) and can be used %s.', 'wpforo' ), '<span style="color: #990000">' . __('as long as you have an active subscription', 'wpforo' ) . '</span>' ); ?>
			</p>
		</div>

		<div class="wpforo-ai-credit-packs-table">
			<!-- 500 Credits Pack -->
			<div class="credit-pack">
				<div class="credit-pack-header">
					<h4><?php _e( '500 Credits', 'wpforo' ); ?></h4>
					<div class="credit-pack-price">$<?php echo esc_html( number_format( $credit_packs['500']['price'], 0 ) ); ?><span class="credit-pack-period"><?php _e( 'one-time', 'wpforo' ); ?></span></div>
				</div>
				<div class="credit-pack-action">
					<button type="button" class="button button-large wpforo-ai-buy-credits-btn" data-pack="500" data-tenant-id="<?php echo esc_attr( $tenant_id ); ?>">
						<?php _e( 'Buy Now', 'wpforo' ); ?>
					</button>
				</div>
			</div>

			<!-- 1500 Credits Pack -->
			<div class="credit-pack">
				<div class="credit-pack-header">
					<h4><?php _e( '1,500 Credits', 'wpforo' ); ?></h4>
					<div class="credit-pack-price">$<?php echo esc_html( number_format( $credit_packs['1500']['price'], 0 ) ); ?><span class="credit-pack-period"><?php _e( 'one-time', 'wpforo' ); ?></span></div>
				</div>
				<div class="credit-pack-action">
					<button type="button" class="button button-large wpforo-ai-buy-credits-btn" data-pack="1500" data-tenant-id="<?php echo esc_attr( $tenant_id ); ?>">
						<?php _e( 'Buy Now', 'wpforo' ); ?>
					</button>
				</div>
			</div>

			<!-- 4500 Credits Pack -->
			<div class="credit-pack">
				<div class="credit-pack-header">
					<h4><?php _e( '4,500 Credits', 'wpforo' ); ?></h4>
					<div class="credit-pack-price">$<?php echo esc_html( number_format( $credit_packs['4500']['price'], 0 ) ); ?><span class="credit-pack-period"><?php _e( 'one-time', 'wpforo' ); ?></span></div>
				</div>
				<div class="credit-pack-action">
					<button type="button" class="button button-large wpforo-ai-buy-credits-btn" data-pack="4500" data-tenant-id="<?php echo esc_attr( $tenant_id ); ?>">
						<?php _e( 'Buy Now', 'wpforo' ); ?>
					</button>
				</div>
			</div>

			<!-- 15000 Credits Pack -->
			<div class="credit-pack">
				<div class="credit-pack-header">
					<h4><?php _e( '15,000 Credits', 'wpforo' ); ?></h4>
					<div class="credit-pack-price">$<?php echo esc_html( number_format( $credit_packs['15000']['price'], 0 ) ); ?><span class="credit-pack-period"><?php _e( 'one-time', 'wpforo' ); ?></span></div>
				</div>
				<div class="credit-pack-action">
					<button type="button" class="button button-large wpforo-ai-buy-credits-btn" data-pack="15000" data-tenant-id="<?php echo esc_attr( $tenant_id ); ?>">
						<?php _e( 'Buy Now', 'wpforo' ); ?>
					</button>
				</div>
			</div>
		</div>

		<div class="wpforo-ai-credit-packs-note">
			<p><strong><?php _e( 'Important:', 'wpforo' ); ?></strong> <?php _e( 'Credit packs are one-time purchases that accumulate with your monthly subscription credits up to your plan\'s maximum accumulation limit (one year of subscription plan credits). Credits can only be used while you have an active subscription. If your subscription expires, your credits will remain in your account but cannot be used until you reactivate your subscription.', 'wpforo' ); ?></p>
		</div>
	</div>

	<!-- Credit Pack Checkout Configurations -->
	<script type="text/javascript">
		<?php $credit_providers = wpforo_ai_get_enabled_providers(); ?>
		<?php if ( in_array( 'freemius', $credit_providers, true ) ) : ?>
		// Add credit pack configurations to existing wpforoFreemiusCheckout object
		if (typeof window.wpforoFreemiusCheckout === 'undefined') {
			window.wpforoFreemiusCheckout = { tenant_id: <?php echo json_encode( $tenant_id ); ?> };
		}
		window.wpforoFreemiusCheckout.creditPacks = {
			'500': <?php echo json_encode( wpforo_ai_get_credit_pack_config( $tenant_id, '500' ) ); ?>,
			'1500': <?php echo json_encode( wpforo_ai_get_credit_pack_config( $tenant_id, '1500' ) ); ?>,
			'4500': <?php echo json_encode( wpforo_ai_get_credit_pack_config( $tenant_id, '4500' ) ); ?>,
			'15000': <?php echo json_encode( wpforo_ai_get_credit_pack_config( $tenant_id, '15000' ) ); ?>
		};
		<?php endif; ?>
	</script>
	<?php
}

/**
 * Get user data for Freemius checkout
 *
 * Returns the current WordPress user's email and name for pre-filling the checkout form.
 * Only uses actual first_name and last_name from user meta - no fallbacks to avoid wrong data.
 *
 * @return array User data with 'email', 'first', and 'last' keys
 */
function wpforo_ai_get_freemius_user_data() {
	$current_user = wp_get_current_user();

	// Get email - prefer current user's email, fallback to admin email
	$email = ! empty( $current_user->user_email ) ? $current_user->user_email : get_option( 'admin_email' );

	// Get first name and last name from user meta only - no fallbacks
	// Empty is better than wrong data (like website title)
	$first_name = ! empty( $current_user->first_name ) ? $current_user->first_name : '';
	$last_name  = ! empty( $current_user->last_name ) ? $current_user->last_name : '';

	return [
		'email' => $email,
		'first' => $first_name,
		'last'  => $last_name,
	];
}

/**
 * Get Freemius upgrade URL
 *
 * @param string $tenant_id Tenant ID
 * @param string $plan Plan name ('starter', 'professional', 'business', 'enterprise')
 * @return string Checkout URL
 */
function wpforo_ai_get_upgrade_url( $tenant_id, $plan = 'professional' ) {
	// Freemius plugin configuration
	// wpForo AI Addon ID: 21923
	$freemius_plugin_id = wpforo_get_option( 'ai_freemius_plugin_id', '21923' );

	// Ensure plugin_id is never empty
	if ( empty( $freemius_plugin_id ) ) {
		$freemius_plugin_id = '21923';
	}

	// Fetch dynamic pricing from Freemius API (with fallback to defaults)
	$pricing = wpforo_ai_get_freemius_pricing();
	$plans = $pricing['plans'];

	$config = [
		'starter'      => [
			'plugin_id'  => $freemius_plugin_id,
			'plan_id'    => $plans['starter']['plan_id'],
			'pricing_id' => $plans['starter']['pricing_id'],
		],
		'professional' => [
			'plugin_id'  => $freemius_plugin_id,
			'plan_id'    => $plans['professional']['plan_id'],
			'pricing_id' => $plans['professional']['pricing_id'],
		],
		'business'     => [
			'plugin_id'  => $freemius_plugin_id,
			'plan_id'    => $plans['business']['plan_id'],
			'pricing_id' => $plans['business']['pricing_id'],
		],
		'enterprise'   => [
			'plugin_id'  => $freemius_plugin_id,
			'plan_id'    => $plans['enterprise']['plan_id'],
			'pricing_id' => $plans['enterprise']['pricing_id'],
		],
	];

	$plan_config = isset( $config[ $plan ] ) ? $config[ $plan ] : $config['professional'];

	// Ensure plan_config is valid
	if ( ! is_array( $plan_config ) ) {
		$plan_config = $config['professional'];
	}

	// Get API Gateway webhook URL
	$webhook_url = WPF()->ai_client->get_api_base_url() . '/v1/freemius/webhook';

	// Build config object for JavaScript (not a URL)
	$plugin_id  = isset( $plan_config['plugin_id'] ) && ! empty( $plan_config['plugin_id'] ) ? $plan_config['plugin_id'] : '21923';
	$plan_id    = isset( $plan_config['plan_id'] ) && ! empty( $plan_config['plan_id'] ) ? $plan_config['plan_id'] : $plans['professional']['plan_id'];
	$pricing_id = isset( $plan_config['pricing_id'] ) && ! empty( $plan_config['pricing_id'] ) ? $plan_config['pricing_id'] : $plans['professional']['pricing_id'];

	return [
		'plugin_id'     => $plugin_id,
		'plan_id'       => $plan_id,
		'pricing_id'    => $pricing_id,
		'public_key'    => 'pk_bbb26ca8c136ce743c4661e2dd23f', // Freemius Public Key
		'billing_cycle' => 'monthly',
		'currency'      => 'usd',
		'success'       => admin_url( 'admin.php?page=wpforo-ai&upgraded=1&plan=' . $plan ),
		'cancel'        => admin_url( 'admin.php?page=wpforo-ai' ),
		'webhook_url'   => $webhook_url,
		'user'          => wpforo_ai_get_freemius_user_data(),
		'metadata'      => [
			'tenant_id' => $tenant_id, // CRITICAL: Pass tenant_id so webhook can identify tenant
		],
	];
}

/**
 * Get Freemius credit pack configuration
 *
 * @param string $tenant_id Tenant ID
 * @param string $pack Pack size ('500', '1500', '4500', '15000')
 * @return array Checkout configuration
 */
function wpforo_ai_get_credit_pack_config( $tenant_id, $pack = '1500' ) {
	// Freemius plugin configuration
	// AI Credits Addon ID: 21955 (different from main wpForo AI plugin 21923)
	$freemius_addon_id = wpforo_get_option( 'ai_freemius_addon_id', '21955' );

	// Ensure addon_id is never empty
	if ( empty( $freemius_addon_id ) ) {
		$freemius_addon_id = '21955';
	}

	// Fetch dynamic pricing from Freemius API (with fallback to defaults)
	$pricing = wpforo_ai_get_freemius_pricing();
	$credit_packs = $pricing['credit_packs'];

	// Credit pack add-on plan IDs from Freemius
	$config = [
		'500'  => [
			'plugin_id'  => $freemius_addon_id,
			'plan_id'    => $credit_packs['500']['plan_id'],
			'pricing_id' => $credit_packs['500']['pricing_id'],
		],
		'1500'  => [
			'plugin_id'  => $freemius_addon_id,
			'plan_id'    => $credit_packs['1500']['plan_id'],
			'pricing_id' => $credit_packs['1500']['pricing_id'],
		],
		'4500'  => [
			'plugin_id'  => $freemius_addon_id,
			'plan_id'    => $credit_packs['4500']['plan_id'],
			'pricing_id' => $credit_packs['4500']['pricing_id'],
		],
		'15000' => [
			'plugin_id'  => $freemius_addon_id,
			'plan_id'    => $credit_packs['15000']['plan_id'],
			'pricing_id' => $credit_packs['15000']['pricing_id'],
		],
	];

	$pack_config = isset( $config[ $pack ] ) ? $config[ $pack ] : $config['1500'];

	// Ensure pack_config is valid
	if ( ! is_array( $pack_config ) ) {
		$pack_config = $config['1500'];
	}

	// Get API Gateway webhook URL
	$webhook_url = WPF()->ai_client->get_api_base_url() . '/v1/freemius/webhook';

	// Build config object for JavaScript (not a URL)
	$plugin_id  = isset( $pack_config['plugin_id'] ) && ! empty( $pack_config['plugin_id'] ) ? $pack_config['plugin_id'] : '21955';
	$plan_id    = isset( $pack_config['plan_id'] ) && ! empty( $pack_config['plan_id'] ) ? $pack_config['plan_id'] : $credit_packs['1500']['plan_id'];
	$pricing_id = isset( $pack_config['pricing_id'] ) && ! empty( $pack_config['pricing_id'] ) ? $pack_config['pricing_id'] : $credit_packs['1500']['pricing_id'];

	return [
		'plugin_id'     => $plugin_id,
		'plan_id'       => $plan_id,
		'pricing_id'    => $pricing_id,
		'public_key'    => 'pk_d241fb18a0097c75e4de7c12d8b67', // AI Credits Addon Public Key
		'billing_cycle' => 'one-time',
		'currency'      => 'usd',
		'success'       => admin_url( 'admin.php?page=wpforo-ai&credits_purchased=1&pack=' . $pack ),
		'cancel'        => admin_url( 'admin.php?page=wpforo-ai' ),
		'webhook_url'   => $webhook_url,
		'user'          => wpforo_ai_get_freemius_user_data(),
		'metadata'      => [
			'tenant_id' => $tenant_id,  // CRITICAL: Pass tenant_id so webhook can identify tenant
			'is_addon'  => true,        // Flag to distinguish add-on from subscription
		],
	];
}

/**
 * Get manage subscription URL based on payment provider
 *
 * For Paddle: customer-portal.paddle.com/cpl_{ID} (seller-specific portal with email login)
 * For Freemius: customers.gvectors.com (Freemius customer portal)
 *
 * @return string Manage subscription URL
 */
function wpforo_ai_get_manage_subscription_url( $provider = '' ) {
	// If no provider specified, use the stored default
	if ( ! $provider ) {
		$provider = get_option( 'wpforo_ai_payment_provider', '' );
	}

	if ( $provider === 'paddle' ) {
		// Paddle customer portal with seller-specific CPL ID
		// Shows email login page where customers get a magic link
		$url = 'https://customer-portal.paddle.com/cpl_01f49qd36rqh2vswk36847drxt';
	} else {
		$url = 'https://customers.gvectors.com';
	}

	return apply_filters( 'wpforo_ai_manage_subscription_url', $url, $provider );
}

/**
 * Get CSS class for credit status based on percentage remaining
 *
 * @param int $percent Percentage remaining
 * @return string CSS class
 */
function wpforo_ai_get_credit_status_class( $percent ) {
	if ( $percent >= 50 ) {
		return 'status-good';
	} elseif ( $percent >= 20 ) {
		return 'status-warning';
	} else {
		return 'status-critical';
	}
}

/**
 * Format date for display
 *
 * @param string $date ISO 8601 date string
 * @return string Formatted date
 */
