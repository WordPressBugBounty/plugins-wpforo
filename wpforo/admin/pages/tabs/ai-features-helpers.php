<?php
/**
 * AI Features - Helper Functions
 *
 * Shared helper functions used across AI Features tabs
 *
 * @package wpForo
 * @subpackage Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if current site is running on localhost/development environment
 *
 * Detects common localhost patterns that are not suitable for API key generation.
 *
 * @return bool True if localhost/development site, false otherwise
 */
function wpforo_ai_is_localhost() {
	$site_url = site_url();
	$host = wp_parse_url( $site_url, PHP_URL_HOST );

	if ( empty( $host ) ) {
		return true; // Invalid URL, treat as localhost
	}

	$host_lower = strtolower( $host );

	// Common localhost patterns
	$localhost_patterns = [
		'localhost',
		'127.0.0.1',
		'0.0.0.0',
		'::1',
	];

	// Check exact matches
	if ( in_array( $host_lower, $localhost_patterns, true ) ) {
		return true;
	}

	// Check localhost TLDs (common development environments)
	$localhost_tlds = [
		'.local',
		'.localhost',
		'.test',
		'.example',
		'.invalid',
		'.dev',
		'.loc',  // Common MAMP/XAMPP local development
	];

	foreach ( $localhost_tlds as $tld ) {
		if ( substr( $host_lower, -strlen( $tld ) ) === $tld ) {
			return true;
		}
	}

	// Check for local IP ranges (private networks)
	if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
		// 10.x.x.x, 172.16-31.x.x, 192.168.x.x
		if ( preg_match( '/^(10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|192\.168\.)/', $host ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Get Freemius pricing for subscription plans and credit packs
 *
 * Returns static pricing configuration for all available plans and credit packs.
 *
 * IMPORTANT: If you change prices in the Freemius Dashboard, you must manually
 * update the prices in this function to keep them in sync. The prices here are
 * used for display purposes only - Freemius handles the actual checkout and payment.
 *
 * @return array Pricing data with 'plans' and 'credit_packs' keys
 */
function wpforo_ai_get_freemius_pricing() {
	return [
		'plans' => [
			'starter' => [
				'plan_id' => '36610',
				'pricing_id' => '47813',
				'price' => 15.00,
				'currency' => 'usd',
				'billing_cycle' => 'monthly'
			],
			'professional' => [
				'plan_id' => '36612',
				'pricing_id' => '47815',
				'price' => 25.00,
				'currency' => 'usd',
				'billing_cycle' => 'monthly'
			],
			'business' => [
				'plan_id' => '36613',
				'pricing_id' => '47816',
				'price' => 49.00,
				'currency' => 'usd',
				'billing_cycle' => 'monthly'
			],
			'enterprise' => [
				'plan_id' => '36615',
				'pricing_id' => '47818',
				'price' => 99.00,
				'currency' => 'usd',
				'billing_cycle' => 'monthly'
			],
		],
		'credit_packs' => [
			'500' => [
				'plan_id' => '36667',
				'pricing_id' => '47923',
				'price' => 10.00,
				'credits' => 500,
				'currency' => 'usd'
			],
			'1500' => [
				'plan_id' => '36668',
				'pricing_id' => '47924',
				'price' => 20.00,
				'credits' => 1500,
				'currency' => 'usd'
			],
			'4500' => [
				'plan_id' => '36669',
				'pricing_id' => '47925',
				'price' => 50.00,
				'credits' => 4500,
				'currency' => 'usd'
			],
			'15000' => [
				'plan_id' => '36670',
				'pricing_id' => '47926',
				'price' => 120.00,
				'credits' => 15000,
				'currency' => 'usd'
			],
		]
	];
}

/**
 * Get Paddle pricing for subscription plans and credit packs
 *
 * Returns Paddle price IDs for all plans and credit packs.
 * Prices are the same as Freemius — only the checkout flow differs.
 *
 * IMPORTANT: If you change prices in the Paddle Dashboard, you must manually
 * update the price IDs here. Paddle uses price_id (not plan_id/pricing_id like Freemius).
 *
 * Sandbox and Live have different price IDs. The correct set is selected based
 * on the WPFORO_AI_PADDLE_ENV constant (default: 'sandbox').
 *
 * @return array Pricing data with 'plans' and 'credit_packs' keys
 */
function wpforo_ai_get_paddle_pricing() {
	// Determine environment: 'live' for production (default), 'sandbox' for testing
	// To use sandbox, define WPFORO_AI_PADDLE_ENV as 'sandbox' in wp-config.php
	$env = defined( 'WPFORO_AI_PADDLE_ENV' ) ? WPFORO_AI_PADDLE_ENV : 'live';

	if ( $env === 'live' ) {
		return [
			'plans'        => [
				'starter'      => [ 'price_id' => 'pri_01km85yz3r7yakw5z9vj0a7j4f', 'price' => 15.00 ],
				'professional' => [ 'price_id' => 'pri_01km863kcef4vwtwm95ph7h5ys', 'price' => 25.00 ],
				'business'     => [ 'price_id' => 'pri_01km865rt82y0sckrw1fq59ftk', 'price' => 49.00 ],
				'enterprise'   => [ 'price_id' => 'pri_01km86kpkf9802tazpv912hgeh', 'price' => 99.00 ],
			],
			'credit_packs' => [
				'500'   => [ 'price_id' => 'pri_01km86ayn4dmqrj20fxwg5f816', 'price' => 10.00, 'credits' => 500 ],
				'1500'  => [ 'price_id' => 'pri_01km86cgmnq3cm2fxdrnv6s6zp', 'price' => 20.00, 'credits' => 1500 ],
				'4500'  => [ 'price_id' => 'pri_01km86dy96gf99qb20mpzq7ksa', 'price' => 50.00, 'credits' => 4500 ],
				'15000' => [ 'price_id' => 'pri_01km86f1br525kshdgqgw7yq0j', 'price' => 120.00, 'credits' => 15000 ],
			],
		];
	}

	// Sandbox (default)
	return [
		'plans'        => [
			'starter'      => [ 'price_id' => 'pri_01km87cgsb37gtznd8qvyfx2zt', 'price' => 15.00 ],
			'professional' => [ 'price_id' => 'pri_01km87dsd0ts43ay9rask9xsgg', 'price' => 25.00 ],
			'business'     => [ 'price_id' => 'pri_01km87evv46y756xwex931mjx5', 'price' => 49.00 ],
			'enterprise'   => [ 'price_id' => 'pri_01km87g2ppkw7xhp8j99qws7k7', 'price' => 99.00 ],
		],
		'credit_packs' => [
			'500'   => [ 'price_id' => 'pri_01km87hc8at4jmww1k167yvv43', 'price' => 10.00, 'credits' => 500 ],
			'1500'  => [ 'price_id' => 'pri_01km87j555ed649fn1nss6xr0z', 'price' => 20.00, 'credits' => 1500 ],
			'4500'  => [ 'price_id' => 'pri_01km87jzgm3zmerqy71rk3hsb1', 'price' => 50.00, 'credits' => 4500 ],
			'15000' => [ 'price_id' => 'pri_01km87kpva85jmvxxgnjaz43j5', 'price' => 120.00, 'credits' => 15000 ],
		],
	];
}

/**
 * Get the list of enabled payment providers
 *
 * To disable a payment provider, remove it from the returned array.
 * For example, to hide Freemius entirely, return only ['paddle'].
 *
 * @return array List of enabled provider slugs ('paddle', 'freemius')
 */
function wpforo_ai_get_enabled_providers() {
	// To disable Freemius: change to ['paddle']
	// To disable Paddle: change to ['freemius']
	return [ 'paddle', 'freemius' ];
}

/**
 * Get the default payment provider
 *
 * @return string Default provider slug
 */
function wpforo_ai_get_default_provider() {
	return 'paddle';
}

/**
 * Handle form actions (connect, regenerate, disconnect, refresh)
 *
 * @return array|null Notice data or null
 */
function wpforo_ai_handle_form_actions() {
	if ( ! isset( $_POST['wpforo_ai_action'] ) ) {
		return null;
	}

	$action = sanitize_key( $_POST['wpforo_ai_action'] );

	// Verify nonce
	$nonce_action = 'wpforo_ai_' . $action;
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], $nonce_action ) ) {
		return [
			'type'    => 'error',
			'message' => __( 'Security check failed. Please try again.', 'wpforo' ),
		];
	}

	// Check permissions
	if ( ! wpforo_current_user_is( 'admin' ) && ! WPF()->usergroup->can( 'ms' ) ) {
		return [
			'type'    => 'error',
			'message' => __( 'Insufficient permissions.', 'wpforo' ),
		];
	}

	switch ( $action ) {
		case 'connect':
			return wpforo_ai_handle_connect();

		case 'disconnect':
			return wpforo_ai_handle_disconnect();

		case 'refresh_status':
			WPF()->ai_client->clear_status_cache();
			delete_transient( 'wpforo_ai_indexed_counts' );
			// Note: pending_approval transient will be cleared automatically
			// in wpforo_ai_get_current_state if API returns active status
			return [
				'type'    => 'success',
				'message' => __( 'Status refreshed successfully.', 'wpforo' ),
			];

		case 'manual_ingest':
			return wpforo_ai_handle_manual_ingest();

		case 'filtered_ingest':
			return wpforo_ai_handle_filtered_ingest();

		case 'forum_ingest':
			return wpforo_ai_handle_forum_ingest();

		case 'reindex_all':
			return wpforo_ai_handle_reindex_all();

		case 'reindex_images':
			return wpforo_ai_handle_reindex_images();

		case 'clear_database':
			return wpforo_ai_handle_clear_database();

		case 'clear_and_reindex':
			return wpforo_ai_handle_clear_and_reindex();

		case 'save_chunking_config':
			return wpforo_ai_handle_save_chunking_config();

		case 'stop_indexing':
			return wpforo_ai_handle_stop_indexing();

		case 'process_local_batch':
			return wpforo_ai_handle_process_local_batch();

		case 'start_local_indexing':
			return wpforo_ai_handle_start_local_indexing();

		case 'stop_local_indexing':
			return wpforo_ai_handle_stop_local_indexing();

		case 'get_indexing_progress':
			return wpforo_ai_handle_get_indexing_progress();

		case 'clear_local_embeddings':
			return wpforo_ai_handle_clear_local_embeddings();
	}

	return null;
}

/**
 * Handle tenant connection/registration
 *
 * @return array Notice data
 */
function wpforo_ai_handle_connect() {
	$response = WPF()->ai_client->register_tenant();

	if ( is_wp_error( $response ) ) {
		return [
			'type'    => 'error',
			'message' => sprintf(
				__( 'Connection failed: %s', 'wpforo' ),
				$response->get_error_message()
			),
		];
	}

	// Store API key (encrypted) and tenant ID
	$api_key   = wpfval( $response, 'api_key' );
	$tenant_id = wpfval( $response, 'tenant_id' );

	if ( empty( $api_key ) || empty( $tenant_id ) ) {
		return [
			'type'    => 'error',
			'message' => __( 'Invalid response from server. Please try again.', 'wpforo' ),
		];
	}

	// Use global options (shared across all boards)
	WPF()->ai_client->update_global_option( 'ai_api_key', WPF()->ai_client->encrypt_api_key( $api_key ) );
	WPF()->ai_client->update_global_option( 'ai_tenant_id', $tenant_id );

	// Check if registration returned pending_approval status
	// Note: wpfval doesn't support default values - use ?: instead
	$subscription = wpfval( $response, 'subscription' ) ?: [];
	$sub_status = wpfval( $subscription, 'status' );

	// Cache subscription status and plan immediately (no API calls needed)
	// This ensures is_service_available() works right after registration
	WPF()->ai_client->update_global_option( 'ai_subscription_status', sanitize_text_field( $sub_status ) );
	$plan = wpfval( $subscription, 'plan' ) ?: 'free_trial';
	WPF()->ai_client->update_global_option( 'ai_subscription_plan', sanitize_text_field( $plan ) );

	// Cache features enabled from registration response
	$features_enabled = wpfval( $response, 'features_enabled' ) ?: [];
	WPF()->ai_client->update_global_option( 'ai_features_enabled', array_map( 'sanitize_text_field', $features_enabled ) );

	// Store last sync time
	WPF()->ai_client->update_global_option( 'ai_subscription_synced_at', current_time( 'mysql' ) );

	// Store pending_approval status so UI can show correct state immediately
	// This avoids needing to make API call which might be blocked for pending tenants
	if ( $sub_status === 'pending_approval' ) {
		set_transient( 'wpforo_ai_pending_approval', [
			'status'        => 'pending_approval',
			'credits_total' => wpfval( $subscription, 'credits_total' ) ?: 500,
			'registered_at' => time(),
		], DAY_IN_SECONDS );
	} else {
		// Clear any existing pending transient if registration succeeded with active status
		delete_transient( 'wpforo_ai_pending_approval' );
	}

	// Clear status transient cache (the persistent options are already updated above)
	WPF()->ai_client->clear_status_cache();

	$message = wpfval( $response, 'message' );
	if ( empty( $message ) ) {
		$credits = wpfval( $subscription, 'credits_total' ) ?: 500;
		$message = sprintf(
			__( 'Successfully connected! You have %s credits to get started.', 'wpforo' ),
			number_format( $credits )
		);
	}

	return [
		'type'    => 'success',
		'message' => $message,
	];
}

/**
 * Handle service disconnection
 *
 * @return array Notice data
 */
function wpforo_ai_handle_disconnect() {
	$confirm = (bool) wpfval( $_POST, 'confirm' );

	if ( ! $confirm ) {
		return [
			'type'    => 'error',
			'message' => __( 'You must confirm disconnection.', 'wpforo' ),
		];
	}

	$reason     = sanitize_text_field( wpfval( $_POST, 'reason' ) );
	$purge_data = (bool) wpfval( $_POST, 'purge_data' );

	$response = WPF()->ai_client->disconnect_tenant( $reason, $confirm, $purge_data );

	if ( is_wp_error( $response ) ) {
		return [
			'type'    => 'error',
			'message' => sprintf(
				__( 'Disconnection failed: %s', 'wpforo' ),
				$response->get_error_message()
			),
		];
	}

	// Clear global AI options (shared across all boards)
	WPF()->ai_client->delete_global_option( 'ai_api_key' );
	WPF()->ai_client->delete_global_option( 'ai_tenant_id' );

	// Clear cached subscription info (no longer connected)
	WPF()->ai_client->delete_global_option( 'ai_subscription_status' );
	WPF()->ai_client->delete_global_option( 'ai_subscription_plan' );
	WPF()->ai_client->delete_global_option( 'ai_features_enabled' );
	WPF()->ai_client->delete_global_option( 'ai_subscription_synced_at' );

	// Clear status transient cache (no longer connected)
	WPF()->ai_client->clear_status_cache();

	// Clear board-specific options (these use the current board prefix)
	wpforo_delete_option( 'ai_chunk_size' );
	wpforo_delete_option( 'ai_overlap_percent' );
	wpforo_delete_option( 'ai_pagination_size' );

	// Clear Freemius pricing cache
	delete_transient( 'wpforo_ai_freemius_pricing' );

	// Clear pending approval transient (if disconnecting from pending state)
	delete_transient( 'wpforo_ai_pending_approval' );

	// Clear AI cache table
	WPF()->ai_client->clear_ai_cache();

	// Unschedule all AI-related cron jobs
	WPF()->ai_client->clear_pending_cron_jobs();
	WPF()->ai_client->unschedule_cache_cleanup();
	WPF()->ai_client->unschedule_pending_topics_indexing();
	WPF()->ai_client->unschedule_daily_subscription_sync();
	\wpforo\classes\AIContentModeration::get_instance()->unschedule_moderation_cleanup();

	if ( $purge_data ) {
		return [
			'type'    => 'success',
			'message' => __( 'Service disconnected and all data has been permanently removed from gVectors AI servers.', 'wpforo' ),
		];
	}

	return [
		'type'    => 'success',
		'message' => __( 'Service disconnected successfully. Your credits are preserved and will be restored when you reconnect. Your indexed content will be deleted after 30 days. You can reconnect anytime with the same site URL.', 'wpforo' ),
	];
}

/**
 * Determine current state based on connection and subscription
 *
 * @param bool  $is_connected Whether tenant is connected
 * @param array $status Tenant status data
 * @return string State identifier
 */
function wpforo_ai_get_current_state( $is_connected, $status ) {
	if ( ! $is_connected ) {
		// Even if not connected (credentials not saved properly), check for pending transient
		// This handles race conditions where transient is set but credentials aren't read yet
		$pending = get_transient( 'wpforo_ai_pending_approval' );
		if ( $pending && wpfval( $pending, 'status' ) === 'pending_approval' ) {
			return 'pending_approval';
		}
		return 'not_connected';
	}

	// If API call failed, fall back to transient for pending approval state
	if ( is_wp_error( $status ) ) {
		$pending = get_transient( 'wpforo_ai_pending_approval' );
		if ( $pending && wpfval( $pending, 'status' ) === 'pending_approval' ) {
			return 'pending_approval';
		}
		return 'error';
	}

	$subscription = wpfval( $status, 'subscription' );
	$sub_status   = wpfval( $subscription, 'status' );
	$plan         = wpfval( $subscription, 'plan' );

	// If API returns active status, clear any stale pending transient
	// This must happen BEFORE checking the transient so activated tenants show correct state
	if ( in_array( $sub_status, [ 'active', 'trial' ] ) ) {
		delete_transient( 'wpforo_ai_pending_approval' );
	}

	// Check if tenant is pending approval (from API response)
	if ( $sub_status === 'pending_approval' ) {
		return 'pending_approval';
	}

	// Check if tenant is temporarily inactive (admin deactivated)
	if ( $sub_status === 'inactive' ) {
		return 'inactive';
	}

	// Check if subscription has explicitly expired (from API)
	if ( $sub_status === 'expired' ) {
		return 'expired';
	}

	// Check if subscription was cancelled or refunded (via Freemius/Paddle webhook)
	if ( $sub_status === 'cancelled' || $sub_status === 'refunded' ) {
		return 'cancelled';
	}

	// Check if subscription status is invalid (neither active, trial, nor a known state)
	if ( ! in_array( $sub_status, [ 'active', 'trial' ] ) ) {
		return 'error';
	}

	// Check if it's free trial
	if ( $plan === 'free_trial' ) {
		return 'free_trial';
	}

	// Otherwise it's a paid plan
	return 'paid_plan';
}

/**
 * Get indexed topic counts per forum from AI backend
 *
 * Uses VectorStorageManager abstraction to get counts from
 * either local storage or cloud, depending on storage mode.
 *
 * @return array Array mapping forum_id => indexed_count, or empty array on failure
 */
function wpforo_ai_get_indexed_counts_by_forum() {
	// Use VectorStorageManager abstraction
	return WPF()->vector_storage->get_indexed_counts_by_forum();
}

/**
 * Display notice
 *
 * @param array $notice Notice data (type, message)
 */
function wpforo_ai_display_notice( $notice ) {
	$type    = wpfval( $notice, 'type' );
	$message = wpfval( $notice, 'message' );

	if ( empty( $type ) || empty( $message ) ) {
		return;
	}

	$class = 'notice notice-' . esc_attr( $type ) . ' is-dismissible';
	?>
	<div class="<?php echo $class; ?>">
		<p><?php echo wp_kses_post( $message ); ?></p>
	</div>
	<?php
}

/**
 * Get current admin user's timezone
 *
 * Priority:
 * 1. wpforo_profile table (user's forum timezone)
 * 2. WordPress site timezone
 * 3. Default to UTC
 *
 * @return DateTimeZone User's timezone object
 */
function wpforo_ai_get_user_timezone() {
	static $timezone = null;

	if ( $timezone !== null ) {
		return $timezone;
	}

	$timezone_string = '';

	// 1. Try to get user's timezone from wpforo profile
	$user_id = get_current_user_id();
	if ( $user_id ) {
		$member = WPF()->member->get_member( $user_id );
		if ( ! empty( $member['timezone'] ) ) {
			$timezone_string = str_replace( '_', ' ', $member['timezone'] );
		}
	}

	// 2. Fall back to WordPress site timezone
	if ( empty( $timezone_string ) ) {
		$timezone_string = wp_timezone_string();
	}

	// 3. Fall back to UTC
	if ( empty( $timezone_string ) ) {
		$timezone_string = 'UTC';
	}

	try {
		// Handle UTC offset format (e.g., "UTC+3", "UTC-5")
		if ( strpos( $timezone_string, 'UTC/' ) === 0 ) {
			$timezone_string = str_replace( 'UTC/', '', $timezone_string );
		}
		$timezone = new DateTimeZone( $timezone_string );
	} catch ( Exception $e ) {
		$timezone = new DateTimeZone( 'UTC' );
	}

	return $timezone;
}

/**
 * Convert UTC database timestamp to user's timezone
 *
 * @param string|int $utc_datetime UTC datetime string or timestamp
 * @param string     $format       Output format (default: WordPress date + time format)
 *
 * @return string Formatted datetime in user's timezone
 */
function wpforo_ai_format_datetime( $utc_datetime, $format = '' ) {
	if ( empty( $utc_datetime ) ) {
		return '';
	}

	// Default format: WordPress date + time format
	if ( empty( $format ) ) {
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
	}

	try {
		// Parse the UTC datetime
		$utc_tz = new DateTimeZone( 'UTC' );

		if ( is_numeric( $utc_datetime ) ) {
			$datetime = new DateTime( '@' . $utc_datetime );
		} else {
			$datetime = new DateTime( $utc_datetime, $utc_tz );
		}

		// Convert to user's timezone
		$user_tz = wpforo_ai_get_user_timezone();
		$datetime->setTimezone( $user_tz );

		// Format using date_i18n for translation support
		return date_i18n( $format, $datetime->getTimestamp() + $datetime->getOffset() );
	} catch ( Exception $e ) {
		// Fallback to original value if parsing fails
		return is_numeric( $utc_datetime ) ? date( $format, $utc_datetime ) : $utc_datetime;
	}
}

/**
 * Format date only (without time) in user's timezone
 *
 * @param string|int $utc_datetime UTC datetime string or timestamp
 *
 * @return string Formatted date in user's timezone
 */
function wpforo_ai_format_date( $utc_datetime ) {
	if ( empty( $utc_datetime ) ) {
		return '';
	}

	return wpforo_ai_format_datetime( $utc_datetime, get_option( 'date_format' ) );
}

/**
 * Format time only (without date) in user's timezone
 *
 * @param string|int $utc_datetime UTC datetime string or timestamp
 *
 * @return string Formatted time in user's timezone
 */
function wpforo_ai_format_time( $utc_datetime ) {
	if ( empty( $utc_datetime ) ) {
		return '';
	}

	return wpforo_ai_format_datetime( $utc_datetime, get_option( 'time_format' ) );
}

/**
 * Format date with custom format in user's timezone
 *
 * @param string|int $utc_datetime UTC datetime string or timestamp
 * @param string     $format       PHP date format
 *
 * @return string Formatted datetime in user's timezone
 */
function wpforo_ai_format_date_custom( $utc_datetime, $format ) {
	return wpforo_ai_format_datetime( $utc_datetime, $format );
}

/**
 * Format time until a future date in "in X hours" style
 *
 * @param string|int $date Future date string or timestamp
 * @return string Formatted string like "in 1 hour", "in 3 hours", "in 2 days"
 */
function wpforo_ai_format_time_until( $date ) {
	if ( empty( $date ) ) {
		return '';
	}

	$timestamp = is_numeric( $date ) ? (int) $date : strtotime( $date );
	if ( ! $timestamp ) {
		return '';
	}

	$now = current_time( 'timestamp' );
	$diff = $timestamp - $now;

	// If the time has passed
	if ( $diff <= 0 ) {
		$overdue_mins = abs( $diff ) / MINUTE_IN_SECONDS;
		// If overdue by more than 5 minutes, show "overdue"
		if ( $overdue_mins > 5 ) {
			return __( 'overdue', 'wpforo' );
		}
		return __( 'now', 'wpforo' );
	}

	$minutes = floor( $diff / MINUTE_IN_SECONDS );
	$hours = floor( $diff / HOUR_IN_SECONDS );
	$days = floor( $diff / DAY_IN_SECONDS );

	if ( $days >= 1 ) {
		/* translators: %d is the number of days */
		return sprintf( _n( 'in %d day', 'in %d days', $days, 'wpforo' ), $days );
	} elseif ( $hours >= 1 ) {
		/* translators: %d is the number of hours */
		return sprintf( _n( 'in %d hour', 'in %d hours', $hours, 'wpforo' ), $hours );
	} else {
		/* translators: %d is the number of minutes */
		return sprintf( _n( 'in %d min', 'in %d mins', max( 1, $minutes ), 'wpforo' ), max( 1, $minutes ) );
	}
}

/**
 * Handle manual topic indexing
 *
 * @return array Notice data
 */
function wpforo_ai_handle_manual_ingest() {
	$topic_input = sanitize_text_field( wpfval( $_POST, 'topic_input' ) );

	if ( empty( $topic_input ) ) {
		return [
			'type'    => 'error',
			'message' => __( 'Please provide a Topic ID or URL.', 'wpforo' ),
		];
	}

	// Extract topic ID from input (could be ID or URL)
	$topic_id = wpforo_ai_extract_topic_id( $topic_input );

	if ( ! $topic_id ) {
		return [
			'type'    => 'error',
			'message' => __( 'Invalid Topic ID or URL.', 'wpforo' ),
		];
	}

	// Get chunking configuration from saved options
	$chunk_size = (int) wpforo_get_option( 'ai_chunk_size', 512 );
	$overlap_percent = (int) wpforo_get_option( 'ai_overlap_percent', 20 );

	// Use VectorStorageManager for indexing (handles both local and cloud modes)
	$response = WPF()->vector_storage->ingest_topics( [ $topic_id ], $chunk_size, $overlap_percent );

	if ( is_wp_error( $response ) ) {
		return [
			'type'    => 'error',
			'message' => sprintf(
				__( 'Content indexing failed: %s', 'wpforo' ),
				$response->get_error_message()
			),
		];
	}

	// Clear indexed counts cache to show updated stats
	delete_transient( 'wpforo_ai_indexed_counts' );

	// Check if all posts were unchanged (no reindexing needed)
	$threads_indexed = isset( $response['threads_indexed'] ) ? (int) $response['threads_indexed'] : 0;
	$posts_unchanged = isset( $response['stats']['deduplication']['posts_unchanged'] ) ? (int) $response['stats']['deduplication']['posts_unchanged'] : 0;

	// If threads_indexed is 0 and we have unchanged posts, show appropriate message
	if ( $threads_indexed === 0 && $posts_unchanged > 0 ) {
		return [
			'type'    => 'info',
			'message' => sprintf(
				__( 'Topic #%d is already up to date. All %d posts are unchanged, no reindexing needed. (0 credits used)', 'wpforo' ),
				$topic_id,
				$posts_unchanged
			),
		];
	}

	// Check for updates (existing topic with changes)
	$posts_changed = isset( $response['deduplication']['posts_changed'] ) ? (int) $response['deduplication']['posts_changed'] : 0;
	$posts_new = isset( $response['deduplication']['posts_new'] ) ? (int) $response['deduplication']['posts_new'] : 0;
	$credits_consumed = isset( $response['credits_consumed'] ) ? (int) $response['credits_consumed'] : 0;
	$topics_queued = isset( $response['topics_queued'] ) ? (int) $response['topics_queued'] : 0;

	// For queued jobs (local mode), credits are consumed during async processing
	$credits_message = ( $topics_queued > 0 && $credits_consumed === 0 )
		? __( 'Credits will be consumed during processing.', 'wpforo' )
		: sprintf( __( '%d credit(s) used.', 'wpforo' ), $credits_consumed );

	if ( $posts_changed > 0 || ( $posts_new > 0 && $posts_unchanged > 0 ) ) {
		// This is an UPDATE to an existing topic
		return [
			'type'    => 'success',
			'message' => sprintf(
				__( 'Topic #%d update queued. %d posts to re-index (%d changed, %d new). %s', 'wpforo' ),
				$topic_id,
				$posts_changed + $posts_new,
				$posts_changed,
				$posts_new,
				$credits_message
			),
		];
	}

	return [
		'type'    => 'success',
		'message' => sprintf(
			__( 'Topic #%d has been queued for indexing. Check the status box for progress. %s', 'wpforo' ),
			$topic_id,
			$credits_message
		),
	];
}

/**
 * Handle filtered topic indexing
 *
 * @return array Notice data
 */
function wpforo_ai_handle_filtered_ingest() {
	$date_from    = sanitize_text_field( wpfval( $_POST, 'date_from' ) );
	$date_to      = sanitize_text_field( wpfval( $_POST, 'date_to' ) );
	$topic_tags   = sanitize_text_field( wpfval( $_POST, 'topic_tags' ) );
	$user_ids     = sanitize_text_field( wpfval( $_POST, 'user_ids' ) );
	$topic_inputs = sanitize_textarea_field( wpfval( $_POST, 'topic_inputs' ) );

	// If specific topics are provided, use them directly (ignores other filters)
	if ( ! empty( $topic_inputs ) ) {
		// Parse comma or newline separated values
		$inputs = preg_split( '/[\s,]+/', $topic_inputs, -1, PREG_SPLIT_NO_EMPTY );
		$topic_ids = [];
		$invalid_inputs = [];

		foreach ( $inputs as $input ) {
			$input = trim( $input );
			if ( empty( $input ) ) {
				continue;
			}
			$topic_id = wpforo_ai_extract_topic_id( $input );
			if ( $topic_id ) {
				$topic_ids[] = $topic_id;
			} else {
				$invalid_inputs[] = $input;
			}
		}

		if ( ! empty( $invalid_inputs ) && empty( $topic_ids ) ) {
			return [
				'type'    => 'error',
				'message' => sprintf(
					__( 'Could not find topics for: %s', 'wpforo' ),
					implode( ', ', array_slice( $invalid_inputs, 0, 5 ) )
				),
			];
		}

		if ( empty( $topic_ids ) ) {
			return [
				'type'    => 'error',
				'message' => __( 'No valid topic IDs or URLs provided.', 'wpforo' ),
			];
		}

		// Remove duplicates
		$topic_ids = array_unique( $topic_ids );

		// Get chunking configuration from saved options
		$chunk_size = (int) wpforo_get_option( 'ai_chunk_size', 512 );
		$overlap_percent = (int) wpforo_get_option( 'ai_overlap_percent', 20 );

		// Use VectorStorageManager for indexing (handles both local and cloud modes)
		$response = WPF()->vector_storage->ingest_topics( $topic_ids, $chunk_size, $overlap_percent );

		if ( is_wp_error( $response ) ) {
			return [
				'type'    => 'error',
				'message' => sprintf(
					__( 'Content indexing failed: %s', 'wpforo' ),
					$response->get_error_message()
				),
			];
		}

		// Clear indexed counts cache
		delete_transient( 'wpforo_ai_indexed_counts' );

		$threads_indexed = isset( $response['threads_indexed'] ) ? (int) $response['threads_indexed'] : 0;
		$credits_consumed = isset( $response['credits_consumed'] ) ? (int) $response['credits_consumed'] : 0;
		$topics_queued = isset( $response['topics_queued'] ) ? (int) $response['topics_queued'] : 0;

		// For queued jobs (local mode), credits are consumed during async processing
		if ( $topics_queued > 0 && $credits_consumed === 0 ) {
			$message = sprintf(
				__( '%d topic(s) queued for indexing. Credits will be consumed during processing.', 'wpforo' ),
				count( $topic_ids )
			);
		} else {
			$message = sprintf(
				__( '%d topic(s) queued for indexing. %d credit(s) used.', 'wpforo' ),
				count( $topic_ids ),
				$credits_consumed
			);
		}

		if ( ! empty( $invalid_inputs ) ) {
			$message .= ' ' . sprintf(
				__( 'Note: Could not find topics for: %s', 'wpforo' ),
				implode( ', ', array_slice( $invalid_inputs, 0, 3 ) )
			);
		}

		return [
			'type'    => 'success',
			'message' => $message,
		];
	}

	// Validate filter combinations
	if ( ! empty( $topic_tags ) && ! empty( $user_ids ) ) {
		return [
			'type'    => 'error',
			'message' => __( 'Cannot combine Topic Tags with User IDs filter. Please use only one.', 'wpforo' ),
		];
	}

	// Must have at least one filter
	if ( empty( $date_from ) && empty( $date_to ) && empty( $topic_tags ) && empty( $user_ids ) ) {
		return [
			'type'    => 'error',
			'message' => __( 'Please provide at least one filter (date range, tags, user IDs, or specific topics).', 'wpforo' ),
		];
	}

	// Build WHERE conditions for direct SQL query
	global $wpdb;
	$table = WPF()->tables->topics;
	$where_conditions = [];
	$topics = [];

	// Only index approved/public topics (status = 0)
	$where_conditions[] = "`status` = 0";

	// Only get topics that haven't been indexed based on storage mode
	// Use VectorStorageManager's method to ensure consistency with actual storage mode
	$storage_mode = WPF()->vector_storage->get_storage_mode();
	$index_column = ( $storage_mode === 'cloud' ) ? 'cloud' : 'local';
	// Note: We still include already-indexed topics when specific filters are used
	// This allows re-indexing of specific content when users want to update it
	// The ingest process will handle updating existing vectors

	// Date filter
	if ( ! empty( $date_from ) ) {
		$where_conditions[] = $wpdb->prepare( "`created` >= %s", $date_from . ' 00:00:00' );
	}
	if ( ! empty( $date_to ) ) {
		$where_conditions[] = $wpdb->prepare( "`created` <= %s", $date_to . ' 23:59:59' );
	}

	// User ID filter
	if ( ! empty( $user_ids ) ) {
		$user_id_array = array_map( 'intval', array_filter( explode( ',', $user_ids ) ) );
		if ( ! empty( $user_id_array ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $user_id_array ), '%d' ) );
			$where_conditions[] = $wpdb->prepare( "`userid` IN ($placeholders)", ...$user_id_array );
		}
	}

	// Tag filter - use same sanitization as wpForo's tag storage (sanitize_text_field, not sanitize_title)
	// Tags are stored comma-separated in the database using sanitize_text_field, so we must match that format
	if ( ! empty( $topic_tags ) ) {
		// Split by comma and trim each tag (same as wpForo's sanitize_tags function)
		$tag_list = array_map( 'trim', explode( ',', $topic_tags ) );
		$tag_list = array_map( 'sanitize_text_field', $tag_list );
		$tag_list = array_filter( $tag_list );
		// Apply lowercase if wpForo setting is enabled (same as sanitize_tags)
		if ( wpforo_setting( 'tags', 'lowercase' ) ) {
			$tag_list = array_map( function_exists( 'mb_strtolower' ) ? 'mb_strtolower' : 'strtolower', $tag_list );
		}

		if ( ! empty( $tag_list ) ) {
			$tag_conditions = [];
			foreach ( $tag_list as $tag ) {
				$tag_conditions[] = $wpdb->prepare( "FIND_IN_SET(%s, `tags`)", $tag );
			}
			// Match topics that have ANY of the specified tags (OR logic)
			$where_conditions[] = '(' . implode( ' OR ', $tag_conditions ) . ')';
		}
	}

	// Execute direct SQL query
	$where_sql = implode( ' AND ', $where_conditions );
	$sql = "SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY `created` DESC LIMIT 500";

	$topics = $wpdb->get_results( $sql, ARRAY_A );

	if ( empty( $topics ) ) {
		$filter_description = [];
		if ( ! empty( $date_from ) || ! empty( $date_to ) ) {
			$filter_description[] = __( 'date range', 'wpforo' );
		}
		if ( ! empty( $topic_tags ) ) {
			$filter_description[] = __( 'tags', 'wpforo' );
		}
		if ( ! empty( $user_ids ) ) {
			$filter_description[] = __( 'user IDs', 'wpforo' );
		}

		return [
			'type'    => 'info',
			'message' => sprintf(
				__( 'No topics found matching the selected filters (%s).', 'wpforo' ),
				implode( ', ', $filter_description )
			),
		];
	}

	// Extract topic IDs
	$topic_ids = array_column( $topics, 'topicid' );

	// Get chunking configuration from saved options
	$chunk_size = (int) wpforo_get_option( 'ai_chunk_size', 512 );
	$overlap_percent = (int) wpforo_get_option( 'ai_overlap_percent', 20 );

	// Use VectorStorageManager for indexing (handles both local and cloud modes)
	$response = WPF()->vector_storage->ingest_topics( $topic_ids, $chunk_size, $overlap_percent );

	if ( is_wp_error( $response ) ) {
		return [
			'type'    => 'error',
			'message' => sprintf(
				__( 'Content indexing failed: %s', 'wpforo' ),
				$response->get_error_message()
			),
		];
	}

	// Clear indexed counts cache
	delete_transient( 'wpforo_ai_indexed_counts' );

	$credits_consumed = isset( $response['credits_consumed'] ) ? (int) $response['credits_consumed'] : 0;
	$topics_queued = isset( $response['topics_queued'] ) ? (int) $response['topics_queued'] : 0;

	// For queued jobs (local mode), credits are consumed during async processing
	if ( $topics_queued > 0 && $credits_consumed === 0 ) {
		return [
			'type'    => 'success',
			'message' => sprintf(
				__( '%d topic(s) matching your filters have been queued for indexing. Credits will be consumed during processing.', 'wpforo' ),
				count( $topic_ids )
			),
		];
	}

	return [
		'type'    => 'success',
		'message' => sprintf(
			__( '%d topic(s) matching your filters have been queued for indexing. %d credit(s) used.', 'wpforo' ),
			count( $topic_ids ),
			$credits_consumed
		),
	];
}

/**
 * Handle forum-based topic indexing
 *
 * Uses direct SQL with LIMIT to safely handle very large forums (hundreds of
 * thousands of topics) without loading every row into PHP memory. Filters
 * private/unapproved topics at the SQL level (not post-fetch) and skips
 * topics already indexed in the current storage mode (cloud/local column).
 *
 * The per-request cap is `WPFORO_AI_FORUM_INGEST_MAX_PER_RUN` (default 10000),
 * filterable via `wpforo_ai_forum_ingest_max_per_run`. Subsequent runs pick
 * up where the previous one left off because already-indexed topics are
 * excluded by the `cloud=0` / `local=0` WHERE clause.
 *
 * @return array Notice data
 */
function wpforo_ai_handle_forum_ingest() {
	global $wpdb;

	$forum_ids = isset( $_POST['forum_ids'] ) && is_array( $_POST['forum_ids'] ) ? array_map( 'intval', $_POST['forum_ids'] ) : [];
	$forum_ids = array_values( array_filter( $forum_ids, function( $id ) { return $id > 0; } ) );
	$date_from = sanitize_text_field( wpfval( $_POST, 'date_from' ) );
	$date_to   = sanitize_text_field( wpfval( $_POST, 'date_to' ) );

	if ( empty( $forum_ids ) ) {
		return [
			'type'    => 'error',
			'message' => __( 'Please select at least one forum to index.', 'wpforo' ),
		];
	}

	$has_date_filter = ! empty( $date_from ) || ! empty( $date_to );

	// Per-run cap, prevents OOM / autoloaded option bloat on huge forums.
	// Remaining topics are picked up on the next run because the SQL below
	// excludes rows that are already indexed in the current storage mode.
	if ( ! defined( 'WPFORO_AI_FORUM_INGEST_MAX_PER_RUN' ) ) {
		define( 'WPFORO_AI_FORUM_INGEST_MAX_PER_RUN', 10000 );
	}
	$max_per_run = (int) apply_filters( 'wpforo_ai_forum_ingest_max_per_run', WPFORO_AI_FORUM_INGEST_MAX_PER_RUN );
	if ( $max_per_run <= 0 ) {
		$max_per_run = 10000;
	}

	// Choose the "already indexed" column based on current storage mode.
	// This way each run picks only not-yet-indexed topics, so running the
	// button again continues from where it stopped.
	$is_local   = WPF()->vector_storage->is_local_mode();
	$mode_col   = $is_local ? 'local' : 'cloud';
	$topics_tbl = WPF()->tables->topics;

	// Build the WHERE clause with safe placeholders.
	// Filters applied at SQL level:
	//   - forumid IN (...)    : only selected forums
	//   - status = 0          : only approved topics (skip pending/spam)
	//   - private = 0         : never index private topics
	//   - {mode_col} = 0      : skip already-indexed topics in this storage mode
	//   - optional date range on `created`
	// ORDER BY topicid DESC   : newest-first, deterministic, matches pagination UX
	// LIMIT {max_per_run}     : hard cap per run
	$in_placeholders = implode( ',', array_fill( 0, count( $forum_ids ), '%d' ) );
	$where_sql = "`forumid` IN ({$in_placeholders}) AND `status` = 0 AND `private` = 0 AND `{$mode_col}` = 0";
	$params    = $forum_ids;

	if ( ! empty( $date_from ) ) {
		$where_sql .= " AND `created` >= %s";
		$params[]   = $date_from . ' 00:00:00';
	}
	if ( ! empty( $date_to ) ) {
		$where_sql .= " AND `created` <= %s";
		$params[]   = $date_to . ' 23:59:59';
	}

	$params[] = $max_per_run;

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared — table name, column name, and IN placeholders are built from trusted sources
	$sql = "SELECT `topicid` FROM `{$topics_tbl}` WHERE {$where_sql} ORDER BY `topicid` DESC LIMIT %d";
	$topic_ids = $wpdb->get_col( $wpdb->prepare( $sql, $params ) );
	$topic_ids = array_values( array_map( 'intval', (array) $topic_ids ) );

	if ( empty( $topic_ids ) ) {
		$message = $has_date_filter
			? __( 'No topics found in the selected forums within the specified date range (already-indexed, private and unapproved topics are excluded).', 'wpforo' )
			: __( 'No topics found in the selected forums to index. All eligible topics may already be indexed (private and unapproved topics are always skipped).', 'wpforo' );
		return [
			'type'    => 'warning',
			'message' => $message,
		];
	}

	$selected_count = count( $topic_ids );
	$capped         = ( $selected_count >= $max_per_run );

	// Get chunking configuration from saved options
	$chunk_size      = (int) wpforo_get_option( 'ai_chunk_size', 512 );
	$overlap_percent = (int) wpforo_get_option( 'ai_overlap_percent', 20 );

	// Use VectorStorageManager for indexing (handles both local and cloud modes).
	// For cloud mode this enqueues to the option-backed queue and schedules ONE
	// self-rescheduling cron event. For local mode it uses the same pattern.
	$response = WPF()->vector_storage->ingest_topics( $topic_ids, $chunk_size, $overlap_percent );

	if ( is_wp_error( $response ) ) {
		return [
			'type'    => 'error',
			'message' => sprintf(
				__( 'Forum indexing failed: %s', 'wpforo' ),
				$response->get_error_message()
			),
		];
	}

	$forum_count = count( $forum_ids );

	// Clear indexed counts cache to show updated stats
	delete_transient( 'wpforo_ai_indexed_counts' );

	// Build success message
	$base_message = sprintf(
		_n(
			'%1$d topic(s) from %2$d forum queued for indexing.',
			'%1$d topic(s) from %2$d forums queued for indexing.',
			$forum_count,
			'wpforo'
		),
		$selected_count,
		$forum_count
	);

	if ( $has_date_filter ) {
		if ( $date_from && $date_to ) {
			$base_message .= ' ' . sprintf( __( '(from %1$s to %2$s)', 'wpforo' ), $date_from, $date_to );
		} elseif ( $date_from ) {
			$base_message .= ' ' . sprintf( __( '(from %s)', 'wpforo' ), $date_from );
		} elseif ( $date_to ) {
			$base_message .= ' ' . sprintf( __( '(until %s)', 'wpforo' ), $date_to );
		}
	}

	if ( $capped ) {
		$base_message .= ' ' . sprintf(
			__( 'Note: capped at %d topics per run. Click the button again after this run completes to continue with the remaining topics.', 'wpforo' ),
			$max_per_run
		);
	}

	$base_message .= ' ' . __( 'Check the status box for progress.', 'wpforo' );

	return [
		'type'    => 'success',
		'message' => $base_message,
	];
}

/**
 * Handle re-index all topics
 *
 * @return array Notice data
 */
function wpforo_ai_handle_reindex_all() {
	// Get chunking configuration from POST, fallback to saved options
	$saved_chunk_size = (int) wpforo_get_option( 'ai_chunk_size', 512 );
	$saved_overlap_percent = (int) wpforo_get_option( 'ai_overlap_percent', 20 );

	$chunk_size      = isset( $_POST['chunk_size'] ) ? (int) $_POST['chunk_size'] : $saved_chunk_size;
	$overlap_percent = isset( $_POST['overlap_percent'] ) ? (int) $_POST['overlap_percent'] : $saved_overlap_percent;

	// Validate parameters (chunk_size is in tokens, range 100-1024)
	if ( $chunk_size < 100 || $chunk_size > 1024 ) {
		$chunk_size = $saved_chunk_size; // Reset to saved if invalid
	}

	if ( $overlap_percent < 5 || $overlap_percent > 50 ) {
		$overlap_percent = $saved_overlap_percent; // Reset to saved if invalid
	}

	// Use VectorStorageManager for reindexing (handles both local and cloud modes)
	$response = WPF()->vector_storage->reindex_all_topics( $chunk_size, $overlap_percent );

	if ( is_wp_error( $response ) ) {
		return [
			'type'    => 'error',
			'message' => sprintf(
				__( 'Re-indexing failed: %s', 'wpforo' ),
				$response->get_error_message()
			),
		];
	}

	$topics_queued = isset( $response['topics_queued'] ) ? (int) $response['topics_queued'] : 0;
	$topics_limited = isset( $response['topics_limited'] ) ? (bool) $response['topics_limited'] : false;
	$skipped_topics = isset( $response['skipped_topics'] ) ? (int) $response['skipped_topics'] : 0;

	// Clear indexed counts cache to show updated stats
	delete_transient( 'wpforo_ai_indexed_counts' );

	// Use the detailed message from VectorStorageManager if available, otherwise build one
	if ( isset( $response['message'] ) && ! empty( $response['message'] ) ) {
		$message = $response['message'];
	} else {
		$message = sprintf(
			__( 'Re-indexing started! %d topics have been queued for processing with chunk size %d and %d%% overlap. This will run in the background.', 'wpforo' ),
			$topics_queued,
			$chunk_size,
			$overlap_percent
		);
	}

	return [
		'type'    => 'success',
		'message' => $message,
	];
}

/**
 * Handle re-index topics with images only (cloud mode)
 *
 * Finds all topics with images in the first post and re-indexes them.
 * This is used when user wants to update image embeddings.
 *
 * @return array Notice data
 */
function wpforo_ai_handle_reindex_images() {
	// Get chunking configuration
	$saved_chunk_size      = (int) wpforo_get_option( 'ai_chunk_size', 512 );
	$saved_overlap_percent = (int) wpforo_get_option( 'ai_overlap_percent', 20 );

	$chunk_size      = isset( $_POST['chunk_size'] ) ? (int) $_POST['chunk_size'] : $saved_chunk_size;
	$overlap_percent = isset( $_POST['overlap_percent'] ) ? (int) $_POST['overlap_percent'] : $saved_overlap_percent;

	// Validate parameters
	$chunk_size      = max( 100, min( 1024, $chunk_size ) );
	$overlap_percent = max( 5, min( 50, $overlap_percent ) );

	// Get all topics
	$topics = WPF()->topic->get_topics( [
		'status'    => 0,
		'row_count' => 999999999,
		'orderby'   => 'topicid',
		'order'     => 'ASC',
	] );

	if ( empty( $topics ) ) {
		return [
			'type'    => 'info',
			'message' => __( 'No topics found.', 'wpforo' ),
		];
	}

	// Filter to only topics with images
	$topics_with_images = wpforo_ai_filter_topics_with_images( $topics );

	if ( empty( $topics_with_images ) ) {
		return [
			'type'    => 'info',
			'message' => __( 'No topics with images found to re-index.', 'wpforo' ),
		];
	}

	$topic_ids = array_column( $topics_with_images, 'topicid' );
	$total_topics = count( $topic_ids );

	// Clear cloud indexed status for these topics to force re-indexing
	global $wpdb;
	$placeholders = implode( ',', array_fill( 0, count( $topic_ids ), '%d' ) );
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE `" . WPF()->tables->topics . "` SET `cloud` = 0 WHERE `topicid` IN ($placeholders)",
			...$topic_ids
		)
	);

	// Clear cache
	wpforo_clean_cache( 'topic' );
	delete_transient( 'wpforo_ai_indexed_counts' );

	// Now use the regular reindex which will pick up unindexed topics
	$response = WPF()->vector_storage->reindex_all_topics( $chunk_size, $overlap_percent );

	if ( is_wp_error( $response ) ) {
		return [
			'type'    => 'error',
			'message' => sprintf(
				__( 'Re-indexing failed: %s', 'wpforo' ),
				$response->get_error_message()
			),
		];
	}

	$message = sprintf(
		__( 'Re-indexing started! %d topics with images have been queued for processing. This will run in the background.', 'wpforo' ),
		$total_topics
	);

	return [
		'type'    => 'success',
		'message' => $message,
	];
}

/**
 * Handle clear RAG database
 *
 * @return array Notice data
 */
function wpforo_ai_handle_clear_database() {
	$confirm = sanitize_text_field( wpfval( $_POST, 'confirm' ) );

	if ( $confirm !== 'DELETE' ) {
		return [
			'type'    => 'error',
			'message' => __( 'You must type DELETE to confirm clearing the database.', 'wpforo' ),
		];
	}

	// Use VectorStorageManager for clearing (handles both local and cloud modes)
	$response = WPF()->vector_storage->clear_all_embeddings();

	if ( is_wp_error( $response ) ) {
		return [
			'type'    => 'error',
			'message' => sprintf(
				__( 'Clear failed: %s', 'wpforo' ),
				$response->get_error_message()
			),
		];
	}

	// Clear indexed counts cache to show updated stats
	delete_transient( 'wpforo_ai_indexed_counts' );

	return [
		'type'    => 'success',
		'message' => __( 'RAG database has been cleared successfully. All indexed data has been removed.', 'wpforo' ),
	];
}

/**
 * Handle clear and re-index
 *
 * @return array Notice data
 */
function wpforo_ai_handle_clear_and_reindex() {
	$confirm = sanitize_text_field( wpfval( $_POST, 'confirm' ) );

	if ( $confirm !== 'CLEAR' ) {
		return [
			'type'    => 'error',
			'message' => __( 'You must type CLEAR to confirm this action.', 'wpforo' ),
		];
	}

	// Get chunking configuration from POST, fallback to saved options
	$saved_chunk_size = (int) wpforo_get_option( 'ai_chunk_size', 512 );
	$saved_overlap_percent = (int) wpforo_get_option( 'ai_overlap_percent', 20 );

	$chunk_size      = isset( $_POST['chunk_size'] ) ? (int) $_POST['chunk_size'] : $saved_chunk_size;
	$overlap_percent = isset( $_POST['overlap_percent'] ) ? (int) $_POST['overlap_percent'] : $saved_overlap_percent;

	// Validate parameters (chunk_size is in tokens, range 100-1024)
	if ( $chunk_size < 100 || $chunk_size > 1024 ) {
		$chunk_size = $saved_chunk_size; // Reset to saved if invalid
	}

	if ( $overlap_percent < 5 || $overlap_percent > 50 ) {
		$overlap_percent = $saved_overlap_percent; // Reset to saved if invalid
	}

	// First clear (using VectorStorageManager)
	$clear_response = WPF()->vector_storage->clear_all_embeddings();

	if ( is_wp_error( $clear_response ) ) {
		return [
			'type'    => 'error',
			'message' => sprintf(
				__( 'Clear failed: %s', 'wpforo' ),
				$clear_response->get_error_message()
			),
		];
	}

	// Then re-index (using VectorStorageManager)
	$reindex_response = WPF()->vector_storage->reindex_all_topics( $chunk_size, $overlap_percent );

	if ( is_wp_error( $reindex_response ) ) {
		return [
			'type'    => 'error',
			'message' => sprintf(
				__( 'Database cleared but re-indexing failed: %s', 'wpforo' ),
				$reindex_response->get_error_message()
			),
		];
	}

	$total_topics = isset( $reindex_response['total_topics'] ) ? (int) $reindex_response['total_topics'] : 0;

	// Clear indexed counts cache to show updated stats
	delete_transient( 'wpforo_ai_indexed_counts' );

	return [
		'type'    => 'success',
		'message' => sprintf(
			__( 'Database cleared successfully! %d topics are now being indexed fresh with chunk size %d and %d%% overlap. This will run in the background.', 'wpforo' ),
			$total_topics,
			$chunk_size,
			$overlap_percent
		),
	];
}

/**
 * Handle saving chunking configuration
 *
 * @return array Notice data
 */
function wpforo_ai_handle_save_chunking_config() {
	// Get and validate chunk_size (in tokens, not characters)
	$chunk_size = isset( $_POST['chunk_size'] ) ? (int) $_POST['chunk_size'] : 512;
	if ( $chunk_size < 100 || $chunk_size > 1024 ) {
		return [
			'type'    => 'error',
			'message' => __( 'Chunk size must be between 100 and 1024 tokens.', 'wpforo' ),
		];
	}

	// Get and validate overlap_percent
	$overlap_percent = isset( $_POST['overlap_percent'] ) ? (int) $_POST['overlap_percent'] : 20;
	if ( $overlap_percent < 5 || $overlap_percent > 50 ) {
		return [
			'type'    => 'error',
			'message' => __( 'Overlap percentage must be between 5 and 50.', 'wpforo' ),
		];
	}

	// Get and validate pagination_size (also used as batch size for local indexing)
	$pagination_size = isset( $_POST['pagination_size'] ) ? (int) $_POST['pagination_size'] : 10;
	if ( $pagination_size < 1 || $pagination_size > 50 ) {
		return [
			'type'    => 'error',
			'message' => __( 'Topics per batch must be between 1 and 50.', 'wpforo' ),
		];
	}

	// Save to wpForo options
	wpforo_update_option( 'ai_chunk_size', $chunk_size );
	wpforo_update_option( 'ai_overlap_percent', $overlap_percent );
	wpforo_update_option( 'ai_pagination_size', $pagination_size );

	return [
		'type'    => 'success',
		'message' => sprintf(
			__( 'Indexing configuration saved: Chunk size %d tokens, Overlap %d%%, Topics per batch %d.', 'wpforo' ),
			$chunk_size,
			$overlap_percent,
			$pagination_size
		),
	];
}

/**
 * Handle stop indexing (clear all pending cron jobs)
 *
 * @return array Notice data
 */
function wpforo_ai_handle_stop_indexing() {
	$result = WPF()->ai_client->clear_pending_cron_jobs();

	if ( empty( $result['success'] ) ) {
		return [
			'type'    => 'error',
			'message' => __( 'Failed to stop indexing. Please try again.', 'wpforo' ),
		];
	}

	$cleared_jobs = isset( $result['cleared_jobs'] ) ? (int) $result['cleared_jobs'] : 0;
	$cleared_topics = isset( $result['cleared_topics'] ) ? (int) $result['cleared_topics'] : 0;

	if ( $cleared_jobs === 0 ) {
		return [
			'type'    => 'info',
			'message' => __( 'No pending indexing jobs found.', 'wpforo' ),
		];
	}

	return [
		'type'    => 'success',
		'message' => sprintf(
			__( 'Indexing stopped. %d scheduled jobs removed (%d topics will not be processed).', 'wpforo' ),
			$cleared_jobs,
			$cleared_topics
		),
	];
}

/**
 * Extract topic ID from input (ID or URL)
 *
 * @param string $input Topic ID or URL
 * @return int|false Topic ID or false on failure
 */
function wpforo_ai_extract_topic_id( $input ) {
	$input = trim( $input );

	// If it's already numeric, return it
	if ( is_numeric( $input ) ) {
		return (int) $input;
	}

	// Try to extract ID from URL
	// Common formats:
	// - /topic/123/slug
	// - /topic/123
	// - ?topicid=123
	if ( preg_match( '/topic[\/=](\d+)/i', $input, $matches ) ) {
		return (int) $matches[1];
	}

	// Try wpForo's native URL parsing if available
	if ( function_exists( 'wpforo_get_topic_id_from_url' ) ) {
		$topic_id = wpforo_get_topic_id_from_url( $input );
		if ( $topic_id ) {
			return $topic_id;
		}
	}

	// Try to extract slug from URL and look up topic
	// URL format: https://example.com/forum/category/topic-slug-123/#postid456
	$url = $input;

	// Remove anchor (e.g., #postid456)
	$url = preg_replace( '/#.*$/', '', $url );

	// Remove query string
	$url = preg_replace( '/\?.*$/', '', $url );

	// Remove trailing slash
	$url = rtrim( $url, '/' );

	// Get the last path segment (slug)
	$slug = basename( $url );

	if ( ! empty( $slug ) && ! is_numeric( $slug ) ) {
		// Try to find topic by slug
		$topic = WPF()->topic->get_topic( $slug );
		if ( $topic && ! empty( $topic['topicid'] ) ) {
			return (int) $topic['topicid'];
		}
	}

	return false;
}

// =============================================================================
// LOCAL INDEXING - AJAX-DRIVEN BATCH PROCESSING
// =============================================================================

/**
 * Filter topics to only include those with images in the first post
 *
 * Used by the "Re-Index Topic Images" button to only index topics
 * that contain images.
 *
 * @param array $topics Array of topic data from get_topics()
 * @return array Filtered topics with images
 */
function wpforo_ai_filter_topics_with_images( $topics ) {
	if ( empty( $topics ) ) {
		return [];
	}

	$ai_client = WPF()->ai_client;
	$filtered = [];

	foreach ( $topics as $topic ) {
		$first_postid = isset( $topic['first_postid'] ) ? (int) $topic['first_postid'] : 0;
		if ( ! $first_postid ) {
			continue;
		}

		// Get the first post content
		$post = WPF()->post->get_post( $first_postid );
		if ( empty( $post['body'] ) ) {
			continue;
		}

		// Check if post has images
		$images = $ai_client->extract_post_images( $post['body'] );
		if ( ! empty( $images ) ) {
			$filtered[] = $topic;
		}
	}

	return $filtered;
}

/**
 * Start local indexing - queues all topics and returns initial info
 *
 * This is called when user clicks "Index All" in local mode.
 * Topics are queued, then JavaScript calls process_local_batch in a loop.
 *
 * @return array Response with queue info
 */
function wpforo_ai_handle_start_local_indexing() {
	$storage_manager = WPF()->vector_storage;

	// Only for local mode
	if ( ! $storage_manager->is_local_mode() ) {
		return [
			'type'    => 'error',
			'message' => __( 'This action is only for local storage mode.', 'wpforo' ),
		];
	}

	// Get settings from POST or saved options
	$saved_chunk_size = (int) wpforo_get_option( 'ai_chunk_size', 512 );
	$saved_overlap_percent = (int) wpforo_get_option( 'ai_overlap_percent', 20 );
	$saved_batch_size = (int) wpforo_get_option( 'ai_pagination_size', 5 );

	$chunk_size = isset( $_POST['chunk_size'] ) ? (int) $_POST['chunk_size'] : $saved_chunk_size;
	$overlap_percent = isset( $_POST['overlap_percent'] ) ? (int) $_POST['overlap_percent'] : $saved_overlap_percent;
	$batch_size = isset( $_POST['batch_size'] ) ? (int) $_POST['batch_size'] : $saved_batch_size;

	// Validate
	$chunk_size = max( 100, min( 1024, $chunk_size ) );
	$overlap_percent = max( 5, min( 50, $overlap_percent ) );
	$batch_size = max( 1, min( 50, $batch_size ) );

	// Check if we're only indexing topics with images
	$images_only = isset( $_POST['images_only'] ) && $_POST['images_only'];

	// Get all topics - order by topicid to ensure consistent processing
	$topics = WPF()->topic->get_topics( [
		'status'    => 0,
		'row_count' => 999999999,
		'orderby'   => 'topicid',
		'order'     => 'ASC',
	] );

	if ( empty( $topics ) ) {
		return [
			'type'    => 'info',
			'message' => __( 'No topics found to index.', 'wpforo' ),
		];
	}

	// If images_only mode, filter to topics with images in first post
	if ( $images_only ) {
		$topics = wpforo_ai_filter_topics_with_images( $topics );
		if ( empty( $topics ) ) {
			return [
				'type'    => 'info',
				'message' => __( 'No topics with images found to index.', 'wpforo' ),
			];
		}
	}

	$topic_ids = array_column( $topics, 'topicid' );
	$total_topics = count( $topic_ids );

	// Check credits
	$ai_client = WPF()->ai_client;
	$status = $ai_client->get_tenant_status( true );
	if ( is_wp_error( $status ) ) {
		return [
			'type'    => 'error',
			'message' => $status->get_error_message(),
		];
	}

	$credits_available = isset( $status['subscription']['credits_remaining'] )
		? (int) $status['subscription']['credits_remaining']
		: 0;

	if ( $credits_available <= 0 ) {
		return [
			'type'    => 'error',
			'message' => __( 'No credits available. Please wait for your monthly reset or purchase additional credits.', 'wpforo' ),
		];
	}

	// Check if there are enough credits for all topics (1 credit per topic)
	$credits_needed = $total_topics;
	$will_complete = $credits_available >= $credits_needed;

	// Store queue and settings in options
	$board_id = WPF()->board->get_current( 'boardid' ) ?: 0;
	$queue_key = 'wpforo_ai_indexing_queue_' . $board_id;
	$settings_key = 'wpforo_ai_indexing_settings_' . $board_id;

	update_option( $queue_key, $topic_ids, false );
	update_option( $settings_key, [
		'chunk_size'      => $chunk_size,
		'overlap_percent' => $overlap_percent,
		'batch_size'      => $batch_size,
		'total_topics'    => $total_topics,
		'started_at'      => time(),
	], false );

	// Also schedule WP Cron as fallback (in case page is closed)
	$cron_hook = 'wpforo_ai_process_queue';
	$cron_args = [ $board_id ];
	if ( ! wp_next_scheduled( $cron_hook, $cron_args ) ) {
		wp_schedule_single_event( time() + 60, $cron_hook, $cron_args );
	}

	// Build message based on credit availability and mode
	$topic_label = $images_only
		? __( 'topics with images', 'wpforo' )
		: __( 'topics', 'wpforo' );

	if ( $will_complete ) {
		$message = sprintf(
			/* translators: 1: number of topics, 2: topic type label, 3: batch size, 4: credits available */
			__( 'Indexing started! %1$d %2$s will be processed in batches of %3$d. You have %4$d credits available.', 'wpforo' ),
			$total_topics,
			$topic_label,
			$batch_size,
			$credits_available
		);
	} else {
		$message = sprintf(
			/* translators: 1: number of topics, 2: topic type label, 3: batch size, 4: credits available, 5: credits needed */
			__( 'Indexing started! %1$d %2$s will be processed in batches of %3$d. Note: You have %4$d credits but need %5$d. Indexing will stop when credits run out.', 'wpforo' ),
			$total_topics,
			$topic_label,
			$batch_size,
			$credits_available,
			$credits_needed
		);
	}

	return [
		'type'              => 'success',
		'action'            => 'indexing_started',
		'total_topics'      => $total_topics,
		'queue_count'       => $total_topics,
		'batch_size'        => $batch_size,
		'credits_available' => $credits_available,
		'credits_needed'    => $credits_needed,
		'will_complete'     => $will_complete,
		'message'           => $message,
	];
}

/**
 * Stop local indexing
 *
 * Clears the indexing queue from WordPress options.
 * Called via AJAX when user clicks Stop Indexing in local mode.
 *
 * @return array Response with status info
 */
function wpforo_ai_handle_stop_local_indexing() {
	$board_id = WPF()->board->get_current( 'boardid' ) ?: 0;
	$settings_key = 'wpforo_ai_indexing_settings_' . $board_id;
	$remaining_count = 0;

	// Clear mode-specific queue keys (current format) and legacy key
	foreach ( [ 'local', 'cloud', '' ] as $mode ) {
		$queue_key = 'wpforo_ai_indexing_queue_' . ( $mode ? $mode . '_' : '' ) . $board_id;
		$queue = get_option( $queue_key, [] );
		if ( ! empty( $queue ) && is_array( $queue ) ) {
			$remaining_count += count( $queue );
		}
		delete_option( $queue_key );
	}

	// Clear settings
	delete_option( $settings_key );

	// Clear all queue processor crons (mode-specific and legacy)
	wp_clear_scheduled_hook( 'wpforo_ai_process_queue_local', [ $board_id ] );
	wp_clear_scheduled_hook( 'wpforo_ai_process_queue_cloud', [ $board_id ] );
	wp_clear_scheduled_hook( 'wpforo_ai_process_queue', [ $board_id ] );

	// Release the indexing lock
	$lock_key = 'wpforo_ai_indexing_lock_' . $board_id;
	delete_transient( $lock_key );

	// Clear the RAG status cache
	WPF()->ai_client->clear_rag_status_cache();

	return [
		'type'    => 'success',
		'message' => sprintf(
			__( 'Local indexing stopped. %d topics removed from queue.', 'wpforo' ),
			$remaining_count
		),
		'cleared' => $remaining_count,
	];
}

/**
 * Process one batch of local indexing
 *
 * Called by JavaScript in a loop until queue is empty.
 *
 * @return array Response with progress info
 */
function wpforo_ai_handle_process_local_batch() {
	// Extend PHP timeout for this long-running operation
	// API timeout is 25 seconds, so we need at least 30+ seconds
	@set_time_limit( 120 );

	try {
		$storage_manager = WPF()->vector_storage;

		// Only for local mode
		if ( ! $storage_manager->is_local_mode() ) {
			return [
				'type'    => 'error',
				'message' => __( 'This action is only for local storage mode.', 'wpforo' ),
			];
		}

		$board_id = WPF()->board->get_current( 'boardid' ) ?: 0;
		$lock_key = 'wpforo_ai_indexing_lock_' . $board_id;
		$queue_key = 'wpforo_ai_indexing_queue_' . $board_id;
		$settings_key = 'wpforo_ai_indexing_settings_' . $board_id;

		// Acquire lock to prevent concurrent processing (AJAX vs AJAX and AJAX vs WP-Cron).
		// Without this, page reloads trigger checkLocalIndexingProgress() which starts
		// a second processing loop while the first is still in-flight.
		$existing_lock = get_transient( $lock_key );
		if ( $existing_lock ) {
			return [
				'type'    => 'info',
				'message' => __( 'Another batch is being processed. Retrying...', 'wpforo' ),
				'action'  => 'wait',
			];
		}
		// Lock per batch — expires after 2 minutes in case of crash
		set_transient( $lock_key, 'batch_' . time(), 120 );

		// Get queue and settings
		$pending_topics = get_option( $queue_key, [] );
		$settings = get_option( $settings_key, [
			'chunk_size'      => 512,
			'overlap_percent' => 20,
			'batch_size'      => 10,
			'total_topics'    => 0,
		] );

		if ( empty( $pending_topics ) ) {
			// Queue is empty - indexing complete
			delete_option( $queue_key );
			delete_option( $settings_key );
			wp_clear_scheduled_hook( 'wpforo_ai_process_queue', [ $board_id ] );
			delete_transient( $lock_key ); // Release lock

			// Get final stats
			$stats = $storage_manager->get_indexing_stats();

			return [
				'type'       => 'success',
				'action'     => 'indexing_complete',
				'done'       => true,
				'processed'  => 0,
				'remaining'  => 0,
				'total'      => $settings['total_topics'],
				'indexed'    => $stats['total_indexed'] ?? 0,
				'message'    => __( 'Indexing complete!', 'wpforo' ),
			];
		}

		$batch_size = (int) ( $settings['batch_size'] ?? 10 );
		$chunk_size = (int) ( $settings['chunk_size'] ?? 512 );
		$overlap_percent = (int) ( $settings['overlap_percent'] ?? 20 );
		$total_topics = (int) ( $settings['total_topics'] ?? 0 );

		// Early credit check - stop immediately if no credits available
		$status = WPF()->ai_client->get_tenant_status( true );
		if ( ! is_wp_error( $status ) && isset( $status['subscription']['credits_remaining'] ) ) {
			$credits_remaining = (int) $status['subscription']['credits_remaining'];
			if ( $credits_remaining <= 0 ) {
				$processed = $total_topics - count( $pending_topics );

				// Clear queue so page reload doesn't auto-resume
				delete_option( $queue_key );
				delete_option( $settings_key );
				wp_clear_scheduled_hook( 'wpforo_ai_process_queue', [ $board_id ] );
				delete_transient( $lock_key );
				WPF()->ai_client->clear_rag_status_cache();

				return [
					'type'              => 'error',
					'action'            => 'credits_exhausted',
					'done'              => true,
					'processed'         => $processed,
					'remaining'         => 0,
					'total'             => $total_topics,
					'credits_remaining' => 0,
					'errors'            => [],
					'message'           => __( 'Indexing stopped: Insufficient credits. Please wait for your monthly reset or purchase additional credits.', 'wpforo' ),
				];
			}
		}

		// Take next batch
		$batch = array_slice( $pending_topics, 0, $batch_size );
		$remaining = array_slice( $pending_topics, $batch_size );

		// Update queue IMMEDIATELY before processing — matches cron_process_queue() pattern.
		// Prevents duplicate batch extraction if page reloads during processing.
		// If processing fails, topics are removed from queue but content_hash dedup
		// in index_topics_batch_local() will skip them on next "Index Remaining" run.
		if ( ! empty( $remaining ) ) {
			update_option( $queue_key, $remaining, false );
		} else {
			delete_option( $queue_key );
		}

		// Process this batch
		$result = $storage_manager->index_topics_batch_local( $batch, [
			'chunk_size'      => $chunk_size,
			'overlap_percent' => $overlap_percent,
		] );

		$indexed_count = is_array( $result ) ? ( $result['indexed_count'] ?? 0 ) : 0;
		$skipped_count = is_array( $result ) ? ( $result['skipped_count'] ?? 0 ) : 0;
		$credits_used = is_array( $result ) ? ( $result['credits_used'] ?? 0 ) : 0;
		$errors = is_array( $result ) ? ( $result['errors'] ?? [] ) : [];

		// Check for credit exhaustion - stop if no credits left
		$has_credit_error = false;
		foreach ( $errors as $error ) {
			if ( stripos( $error, 'insufficient credits' ) !== false || stripos( $error, '402' ) !== false ) {
				$has_credit_error = true;
				break;
			}
		}

		if ( $has_credit_error ) {
			$processed = $total_topics - count( $pending_topics );

			// Clear queue so page reload doesn't auto-resume
			delete_option( $queue_key );
			delete_option( $settings_key );
			wp_clear_scheduled_hook( 'wpforo_ai_process_queue', [ $board_id ] );
			delete_transient( $lock_key );
			WPF()->ai_client->clear_rag_status_cache();

			return [
				'type'              => 'error',
				'action'            => 'credits_exhausted',
				'done'              => true,
				'processed'         => $processed,
				'remaining'         => 0,
				'total'             => $total_topics,
				'credits_remaining' => 0,
				'errors'            => $errors,
				'message'           => __( 'Indexing stopped: Insufficient credits. Please wait for your monthly reset or purchase additional credits.', 'wpforo' ),
			];
		}

		// Reschedule WP Cron as backup (in case page is closed now)
		if ( ! empty( $remaining ) ) {
			$cron_hook = 'wpforo_ai_process_queue';
			$cron_args = [ $board_id ];
			if ( ! wp_next_scheduled( $cron_hook, $cron_args ) ) {
				wp_schedule_single_event( time() + 60, $cron_hook, $cron_args );
			}
		} else {
			wp_clear_scheduled_hook( 'wpforo_ai_process_queue', [ $board_id ] );
		}

		// Clear caches
		WPF()->ai_client->clear_rag_status_cache();
		delete_transient( 'wpforo_ai_indexed_counts' );

		// Always release lock after each batch — next AJAX call will reacquire.
		// The queue was already updated before processing (line ~1892), so even if
		// a concurrent request sneaks in between batches, it gets different topics.
		delete_transient( $lock_key );

		// Get updated credit balance (fresh fetch)
		$status = WPF()->ai_client->get_tenant_status( true );
		$credits_remaining = 0;
		if ( ! is_wp_error( $status ) && isset( $status['subscription']['credits_remaining'] ) ) {
			$credits_remaining = (int) $status['subscription']['credits_remaining'];
		}

		$processed = $total_topics - count( $remaining );

		return [
			'type'              => 'success',
			'action'            => 'batch_processed',
			'done'              => empty( $remaining ),
			'processed'         => $processed,
			'remaining'         => count( $remaining ),
			'total'             => $total_topics,
			'batch_indexed'     => $indexed_count,
			'batch_skipped'     => $skipped_count,
			'credits_used'      => $credits_used,
			'credits_remaining' => $credits_remaining,
			'errors'            => $errors,
			'message'           => sprintf(
				__( 'Processed %d of %d topics...', 'wpforo' ),
				$processed,
				$total_topics
			),
		];

	} catch ( \Exception $e ) {
		if ( isset( $lock_key ) ) {
			delete_transient( $lock_key );
		}
		wpforo_ai_log( 'error', 'process_local_batch exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 'Indexing' );
		return [
			'type'    => 'error',
			'message' => 'Exception: ' . $e->getMessage(),
			'file'    => $e->getFile(),
			'line'    => $e->getLine(),
		];
	} catch ( \Error $e ) {
		if ( isset( $lock_key ) ) {
			delete_transient( $lock_key );
		}
		wpforo_ai_log( 'error', 'process_local_batch error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 'Indexing' );
		return [
			'type'    => 'error',
			'message' => 'PHP Error: ' . $e->getMessage(),
			'file'    => $e->getFile(),
			'line'    => $e->getLine(),
		];
	}
}

/**
 * Get current indexing progress
 *
 * Called on page load to check if indexing is in progress and auto-resume.
 *
 * @return array Response with current progress
 */
function wpforo_ai_handle_get_indexing_progress() {
	$storage_manager = WPF()->vector_storage;

	// Only for local mode
	if ( ! $storage_manager->is_local_mode() ) {
		return [
			'type'            => 'success',
			'is_local_mode'   => false,
			'indexing_active' => false,
		];
	}

	$board_id = WPF()->board->get_current( 'boardid' ) ?: 0;
	$queue_key = 'wpforo_ai_indexing_queue_' . $board_id;
	$settings_key = 'wpforo_ai_indexing_settings_' . $board_id;

	$pending_topics = get_option( $queue_key, [] );
	$settings = get_option( $settings_key, [] );

	$indexing_active = ! empty( $pending_topics );

	if ( ! $indexing_active ) {
		return [
			'type'            => 'success',
			'is_local_mode'   => true,
			'indexing_active' => false,
		];
	}

	$total_topics = (int) ( $settings['total_topics'] ?? count( $pending_topics ) );
	$processed = $total_topics - count( $pending_topics );

	return [
		'type'            => 'success',
		'is_local_mode'   => true,
		'indexing_active' => true,
		'processed'       => $processed,
		'remaining'       => count( $pending_topics ),
		'total'           => $total_topics,
		'batch_size'      => (int) ( $settings['batch_size'] ?? 10 ),
		'started_at'      => $settings['started_at'] ?? null,
		'message'         => sprintf(
			__( 'Indexing in progress: %d of %d topics processed', 'wpforo' ),
			$processed,
			$total_topics
		),
	];
}

/**
 * Clear local embeddings only (no reindex)
 *
 * Used before starting AJAX-driven local indexing.
 *
 * @return array Response
 */
function wpforo_ai_handle_clear_local_embeddings() {
	$storage_manager = WPF()->vector_storage;

	// Only for local mode
	if ( ! $storage_manager->is_local_mode() ) {
		return [
			'type'    => 'error',
			'message' => __( 'This action is only for local storage mode.', 'wpforo' ),
		];
	}

	// Clear all local embeddings
	$result = $storage_manager->clear_all_embeddings();

	if ( is_wp_error( $result ) ) {
		return [
			'type'    => 'error',
			'message' => $result->get_error_message(),
		];
	}

	// Clear any pending indexing queue
	$board_id = WPF()->board->get_current( 'boardid' ) ?: 0;
	delete_option( 'wpforo_ai_indexing_queue_' . $board_id );
	delete_option( 'wpforo_ai_indexing_settings_' . $board_id );
	wp_clear_scheduled_hook( 'wpforo_ai_process_queue', [ $board_id ] );

	// Clear caches
	WPF()->ai_client->clear_rag_status_cache();
	delete_transient( 'wpforo_ai_indexed_counts' );

	return [
		'type'    => 'success',
		'message' => __( 'Local embeddings cleared successfully.', 'wpforo' ),
	];
}
