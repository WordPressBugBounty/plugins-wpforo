<?php

namespace wpforo\classes;

/**
 * wpForo AI Features API Client
 *
 * Handles communication with the wpForo AI backend service including:
 * - Tenant registration and API key generation
 * - Subscription status retrieval
 * - API key regeneration
 * - Service disconnection
 *
 * @since 3.0.0
 */
class AIClient {
	use AIAjaxTrait;
	use AIUserTrait;

	/**
	 * API base URL
	 *
	 * @var string
	 */
	private $api_base_url = 'https://api.gvectors.com/v1';

	/**
	 * Fallback API base URL (used when primary domain is blocked)
	 *
	 * @var string
	 */
	private $fallback_api_url = 'https://api.gvectors.net/v1';

	/**
	 * Image extensions supported for multimodal indexing
	 *
	 * @var array
	 */
	private static $image_extensions = [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp' ];

	/**
	 * Document extensions supported for document indexing
	 *
	 * @var array
	 */
	private static $document_extensions = [ 'pdf', 'docx', 'doc', 'pptx', 'txt', 'rtf' ];

	/**
	 * Request timeout in seconds
	 *
	 * @var int
	 */
	private $timeout = 30;

	/**
	 * Constructor
	 */
	public function __construct() {
		if ( defined( 'WPFORO_AI_API' ) && WPFORO_AI_API ) {
			$this->api_base_url = WPFORO_AI_API;
		}

		// Allow API URLs to be filtered for different environments
		$this->api_base_url = apply_filters( 'wpforo_ai_api_base_url', $this->api_base_url );
		$this->fallback_api_url = apply_filters( 'wpforo_ai_fallback_api_url', $this->fallback_api_url );

		// Register admin-only AJAX handlers
		if ( is_admin() ) {
			add_action( 'wp_ajax_wpforo_ai_get_rag_status', [ $this, 'ajax_get_rag_status' ] );
			add_action( 'wp_ajax_wpforo_ai_cancel_cloud_indexing', [ $this, 'ajax_cancel_cloud_indexing' ] );
			add_action( 'wp_ajax_wpforo_ai_cleanup_indexing_session', [ $this, 'ajax_cleanup_indexing_session' ] );
			add_action( 'wp_ajax_wpforo_ai_action', [ $this, 'ajax_generic_action' ] );
			add_action( 'wp_ajax_wpforo_ai_save_storage_mode', [ $this, 'ajax_save_storage_mode' ] );
			add_action( 'wp_ajax_wpforo_ai_save_auto_indexing', [ $this, 'ajax_save_auto_indexing' ] );
			add_action( 'wp_ajax_wpforo_ai_save_image_indexing', [ $this, 'ajax_save_image_indexing' ] );
			add_action( 'wp_ajax_wpforo_ai_save_document_indexing', [ $this, 'ajax_save_document_indexing' ] );
			add_action( 'wp_ajax_wpforo_ai_save_wp_indexing_option', [ $this, 'ajax_save_wp_indexing_option' ] );
			add_action( 'wp_ajax_wpforo_ai_get_analytics', [ $this, 'ajax_get_analytics' ] );
			add_action( 'wp_ajax_wpforo_ai_run_insight', [ $this, 'ajax_run_insight' ] );
			add_action( 'wp_ajax_wpforo_ai_link_subscription', [ $this, 'ajax_link_subscription' ] );
			add_action( 'wp_ajax_wpforo_ai_activate_license', [ $this, 'ajax_activate_license' ] );
			add_action( 'wp_ajax_wpforo_ai_paddle_checkout', [ $this, 'ajax_paddle_checkout' ] );
			add_action( 'wp_ajax_wpforo_ai_link_paddle_subscription', [ $this, 'ajax_link_paddle_subscription' ] );
			add_action( 'wp_ajax_wpforo_ai_activate_paddle_transaction', [ $this, 'ajax_activate_paddle_transaction' ] );
			add_action( 'wp_ajax_wpforo_ai_search_bot_users', [ $this, 'ajax_search_bot_users' ] );
			add_action( 'wp_ajax_wpforo_ai_request_bonus_credits', [ $this, 'ajax_request_bonus_credits' ] );

			// Register privacy policy content for AI features
			add_action( 'admin_init', [ $this, 'register_privacy_policy_content' ] );

			// Self-heal stalled WP-Cron on wpForo AI admin page loads.
			// Runs once per page refresh (not on AJAX polls) to avoid any
			// interference with the Stop Indexing flow. Hosts where AJAX
			// responses are unreliable (proxies stripping bodies, etc.)
			// still get the nudge whenever an admin reloads the page.
			add_action( 'admin_init', [ $this, 'maybe_nudge_wp_cron_on_admin_page' ] );
		}

		// Register front-end and admin AJAX handlers (for semantic search, antispam, etc.)
		add_action( 'wp_ajax_wpforo_ai_semantic_search', [ $this, 'ajax_semantic_search' ] );
		add_action( 'wp_ajax_nopriv_wpforo_ai_semantic_search', [ $this, 'ajax_semantic_search' ] );

		// Register public front-end semantic search (no admin permissions required)
		add_action( 'wp_ajax_wpforo_ai_public_search', [ $this, 'ajax_public_semantic_search' ] );
		add_action( 'wp_ajax_nopriv_wpforo_ai_public_search', [ $this, 'ajax_public_semantic_search' ] );

		// Register translation AJAX handlers (for logged-in and guest users)
		add_action( 'wp_ajax_wpforo_ai_translate', [ $this, 'ajax_translate_content' ] );
		add_action( 'wp_ajax_nopriv_wpforo_ai_translate', [ $this, 'ajax_translate_content' ] );

		// Register topic summarization AJAX handlers (for logged-in and guest users)
		add_action( 'wp_ajax_wpforo_ai_summarize_topic', [ $this, 'ajax_summarize_topic' ] );
		add_action( 'wp_ajax_nopriv_wpforo_ai_summarize_topic', [ $this, 'ajax_summarize_topic' ] );

		// Register topic suggestions AJAX handlers (for logged-in and guest users)
		add_action( 'wp_ajax_wpforo_ai_get_topic_suggestions', [ $this, 'ajax_get_topic_suggestions' ] );
		add_action( 'wp_ajax_nopriv_wpforo_ai_get_topic_suggestions', [ $this, 'ajax_get_topic_suggestions' ] );

		// Register translation button hook for post content
		add_action( 'wpforo_post_content_top_left', [ $this, 'render_translation_button' ] );

		// Register AI Bot Reply button hook for post action buttons
		add_filter( 'wpforo_template_buttons_bottom', [ $this, 'render_bot_reply_button' ], 8, 5 );

		// Register Suggest Reply button hook for reply form
		add_action( 'wpforo_editor_post_submit_button_before', [ $this, 'render_suggest_reply_button' ], 10, 3 );

		// Register Bot Reply AJAX handlers (logged-in users only)
		add_action( 'wp_ajax_wpforo_ai_bot_reply', [ $this, 'ajax_bot_reply' ] );
		add_action( 'wp_ajax_wpforo_ai_suggest_reply', [ $this, 'ajax_suggest_reply' ] );

		// Register topic summarization button hook (in head-bar with subscribe button)
		add_action( 'wpforo_template_post_head_bar_action_links', [ $this, 'render_topic_summary_button' ], 11, 3 );

		// Register topic summary container hook (after head-bar, for slide-down area)
		add_action( 'wpforo_template_post_head_bar', [ $this, 'render_topic_summary_container_standalone' ], 10, 3 );

		// Register user AI preferences handler (logged-in users only)
		add_action( 'wp_ajax_wpforo_save_ai_preferences', [ $this, 'ajax_save_ai_preferences' ] );

		// Register WP Cron handler for background batch processing
		// IMPORTANT: Must be registered unconditionally (not only in admin context)
		// because WP Cron runs in a separate request where is_admin() returns FALSE
		// Accept 3 args for backwards compatibility with old cron format
		add_action( 'wpforo_ai_process_batch', [ $this, 'cron_process_batch' ], 10, 3 );

		// Register WP Cron handler for local indexing queue (self-rescheduling pattern)
		// This processes batches from the queue and reschedules itself until queue is empty
		add_action( 'wpforo_ai_process_queue', [ $this, 'cron_process_queue' ], 10, 1 );

		// Register mode-specific WP Cron handlers for auto-indexing queues
		// These ensure local topics are processed with local indexing and cloud topics with cloud indexing
		add_action( 'wpforo_ai_process_queue_local', [ $this, 'cron_process_queue_local' ], 10, 1 );
		add_action( 'wpforo_ai_process_queue_cloud', [ $this, 'cron_process_queue_cloud' ], 10, 1 );

		// Register WP Cron handler for AI cache cleanup (daily)
		add_action( 'wpforo_ai_cache_cleanup', [ $this, 'cron_cache_cleanup' ] );

		// Register WP Cron handler for daily pending topics indexing
		add_action( 'wpforo_ai_pending_topics_indexing', [ $this, 'cron_pending_topics_indexing' ] );

		// Register WP Cron handler for daily subscription status sync
		add_action( 'wpforo_ai_daily_subscription_sync', [ $this, 'cron_daily_subscription_sync' ] );

		// Clear translation cache and invalidate indexed status when posts change
		add_action( 'wpforo_after_add_post', [ $this, 'on_post_add' ], 10, 2 );
		add_action( 'wpforo_after_edit_post', [ $this, 'on_post_edit' ], 10, 4 );
		add_action( 'wpforo_after_delete_post', [ $this, 'on_post_delete' ], 10, 1 );
		add_action( 'wpforo_post_approve', [ $this, 'on_post_approve' ], 10, 1 );

		// Auto-index new approved topics and topics that get approved
		add_action( 'wpforo_after_add_topic', [ $this, 'on_topic_add' ], 10, 2 );
		add_action( 'wpforo_topic_approve', [ $this, 'on_topic_approve' ], 10, 1 );

		// Clean up embeddings when topics are deleted
		add_action( 'wpforo_after_delete_topic', [ $this, 'on_topic_delete' ], 10, 1 );

		// Add AI suggestions panel right after title field using form fields filter
		add_filter( 'wpforo_form_fields', [ $this, 'add_ai_suggestions_after_title' ] );

		// Disable built-in wpForo topic suggestions when AI Topic Suggestions is enabled
		add_filter( 'wpforo_topic_suggestion', [ $this, 'filter_built_in_suggestions' ] );
	}

	/**
	 * Get API base URL
	 *
	 * @return string API base URL
	 */
	public function get_api_base_url() {
		return $this->api_base_url;
	}

	/**
	 * Register suggested privacy policy content for AI features
	 *
	 * Adds a suggestion to Settings > Privacy so site admins can include
	 * AI data processing disclosure in their site's privacy policy.
	 */
	public function register_privacy_policy_content() {
		$content = '<h2>' . __( 'wpForo AI Features', 'wpforo' ) . '</h2>' .
			'<p>' . __( 'When AI features are enabled by the forum administrator, this site sends forum content (topics, posts, and metadata) to the gVectors AI API (api.gvectors.com) for processing. This processing includes semantic search, AI translation, summarization, content moderation, AI chat, and topic suggestions.', 'wpforo' ) . '</p>' .
			'<p>' . __( 'No forum data is sent to external servers unless the site administrator has explicitly enabled AI features and configured the service. The data is processed on secure AWS infrastructure and is used solely to provide the requested AI functionality.', 'wpforo' ) . '</p>' .
			'<p>' . sprintf(
				__( 'For more information, see the gVectors %1$sTerms of Service%2$s and %3$sPrivacy Policy%4$s.', 'wpforo' ),
				'<a href="https://gvectors.com/terms-and-conditions/">',
				'</a>',
				'<a href="https://gvectors.com/privacy-policy/">',
				'</a>'
			) . '</p>';

		wp_add_privacy_policy_content( 'wpForo Forum', $content );
	}

	// =========================================================================
	// GLOBAL AI OPTIONS
	// =========================================================================
	// These options are shared across ALL boards (use base prefix 'wpforo_')
	// Unlike board-specific options, these don't use the board prefix.
	//
	// Global options:
	//   - ai_api_key    : API key for authentication (shared)
	//   - ai_tenant_id  : Tenant identifier (shared)
	//
	// Board-specific options (use wpforo_get_option/wpforo_update_option):
	//   - ai_chunk_size      : Chunking size per board
	//   - ai_overlap_percent : Overlap percentage per board
	//   - ai_pagination_size : Pagination size per board
	// =========================================================================

	/**
	 * Get a global AI option (shared across all boards)
	 *
	 * Uses base prefix 'wpforo_' regardless of current board context.
	 * This ensures API key and tenant ID are always found.
	 *
	 * @param string $option Option name without prefix (e.g., 'ai_api_key')
	 * @param mixed $default Default value if option not found
	 * @return mixed Option value
	 */
	public function get_global_option( $option, $default = '' ) {
		return get_option( 'wpforo_' . $option, $default );
	}

	/**
	 * Update a global AI option (shared across all boards)
	 *
	 * Uses base prefix 'wpforo_' regardless of current board context.
	 *
	 * @param string $option Option name without prefix (e.g., 'ai_api_key')
	 * @param mixed $value Value to save
	 * @return bool True on success
	 */
	public function update_global_option( $option, $value ) {
		$result = update_option( 'wpforo_' . $option, $value );
		wpforo_clean_cache( 'option' );
		return $result;
	}

	/**
	 * Delete a global AI option (shared across all boards)
	 *
	 * Uses base prefix 'wpforo_' regardless of current board context.
	 *
	 * @param string $option Option name without prefix (e.g., 'ai_api_key')
	 * @return bool True on success
	 */
	public function delete_global_option( $option ) {
		$result = delete_option( 'wpforo_' . $option );
		wpforo_clean_cache( 'option' );
		return $result;
	}

	/**
	 * Get the API key (global option)
	 *
	 * @return string Encrypted API key or empty string
	 */
	public function get_api_key() {
		return $this->get_global_option( 'ai_api_key', '' );
	}

	/**
	 * Get the tenant ID (global option)
	 *
	 * @return string Tenant ID or empty string
	 */
	public function get_tenant_id() {
		return $this->get_global_option( 'ai_tenant_id', '' );
	}

	/**
	 * Check if tenant is connected to AI service
	 *
	 * @return bool True if API key and tenant ID are configured
	 */
	public function is_connected() {
		$api_key   = $this->get_api_key();
		$tenant_id = $this->get_tenant_id();
		return ! empty( $api_key ) && ! empty( $tenant_id );
	}

	/**
	 * Check if AI service is available for use
	 *
	 * This checks both connection AND subscription status.
	 * AI features should only work when:
	 * 1. Tenant is connected (has API key and tenant ID)
	 * 2. Subscription status is active or trial
	 *
	 * Returns false for: inactive, pending_approval, disconnected, expired
	 *
	 * @return bool True if AI features can be used
	 */
	public function is_service_available() {
		// First check connection
		if ( ! $this->is_connected() ) {
			return false;
		}

		// Use cached subscription status from database options (no API calls)
		// Status is synced when:
		// 1. AI Features > Overview tab is loaded
		// 2. User clicks refresh button
		// 3. On first connection/registration
		$sub_status = $this->get_subscription_status();

		// Only allow active and trial statuses
		return in_array( $sub_status, [ 'active', 'trial' ], true );
	}

	/**
	 * Check API health status
	 *
	 * @return array|WP_Error Health status or error object
	 */
	public function health_check() {
		$response = $this->get( '/tenant/health' );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'health_check_failed', $response->get_error_message() );
			return $response;
		}

		return $response;
	}

	/**
	 * Register new tenant and generate API key
	 *
	 * Creates a new tenant account with free trial (500 credits, 30 days)
	 *
	 * @return array|WP_Error Response data or error object
	 */
	public function register_tenant() {
		$site_url = get_site_url();

		// For localhost development, allow URL override via filter or constant
		if ( strpos( $site_url, 'localhost' ) !== false || strpos( $site_url, '127.0.0.1' ) !== false ) {
			// Check for development mode override
			if ( defined( 'WPFORO_AI_DEV_URL' ) && WPFORO_AI_DEV_URL ) {
				$site_url = WPFORO_AI_DEV_URL;
			}
		}

		$data = [
			'site_url'           => $site_url,
			'admin_email'        => get_option( 'admin_email' ),
			'wordpress_version'  => get_bloginfo( 'version' ),
			'wpforo_version'     => defined( 'WPFORO_VERSION' ) ? WPFORO_VERSION : 'unknown',
			'site_name'          => get_bloginfo( 'name' ),
			'language'           => get_bloginfo( 'language' ),
			'timezone'           => wp_timezone_string(),
		];

		// Allow filtering registration data
		$data = apply_filters( 'wpforo_ai_registration_data', $data );

		// Log the registration attempt
		$this->log_info( 'attempting_tenant_registration', [
			'site_url'    => $data['site_url'],
			'admin_email' => $data['admin_email'],
		] );

		$response = $this->post( '/tenant/register', $data );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'registration_failed', $response->get_error_message() );
			return $response;
		}

		// Log successful registration
		$this->log_info( 'tenant_registered', [
			'tenant_id' => wpfval( $response, 'tenant_id' ),
			'plan'      => wpfval( $response, 'subscription', 'plan' ),
		] );

		do_action( 'wpforo_ai_tenant_registered', $response );

		return $response;
	}

	/**
	 * Get current tenant status and subscription info
	 *
	 * @param bool $force_fresh Whether to bypass the cache and fetch fresh data
	 * @return array|WP_Error Status data or error object
	 */
	public function get_tenant_status( $force_fresh = false ) {
		// Check cache first (5 minute cache)
		$cache_key = 'wpforo_ai_tenant_status';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && ! $force_fresh && ! $this->is_debug_mode() ) {
			return $cached;
		}

		// Validate credentials exist before making API call (use global options)
		$api_key   = $this->get_stored_api_key();
		$tenant_id = $this->get_tenant_id();

		if ( empty( $api_key ) || empty( $tenant_id ) ) {
			return new \WP_Error(
				'no_credentials',
				wpforo_phrase( 'No credentials found. Please connect to the service first.', false )
			);
		}

		$response = $this->get( '/tenant/status' );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'status_fetch_failed', $response->get_error_message() );
			return $response;
		}

		// Cache the response for 5 minutes
		set_transient( $cache_key, $response, 5 * MINUTE_IN_SECONDS );

		// Also update persistent subscription info for frontend feature gating
		// This allows checking plan without API calls on every page load
		$this->update_cached_subscription_info( $response );

		return $response;
	}

	/**
	 * Update cached subscription info from status response
	 *
	 * Stores plan and features in WordPress options for quick access
	 * without making API calls on every page load.
	 *
	 * @param array $status_response Response from /tenant/status API
	 */
	private function update_cached_subscription_info( $status_response ) {
		if ( ! is_array( $status_response ) ) {
			return;
		}

		$subscription = isset( $status_response['subscription'] ) ? $status_response['subscription'] : [];
		$features_enabled = isset( $status_response['features_enabled'] ) ? $status_response['features_enabled'] : [];

		// Store subscription status (e.g., 'active', 'trial', 'inactive', 'pending_approval')
		$sub_status = isset( $subscription['status'] ) ? sanitize_text_field( $subscription['status'] ) : '';
		$this->update_global_option( 'ai_subscription_status', $sub_status );

		// Store plan (e.g., 'free_trial', 'starter', 'professional', 'business', 'enterprise')
		$plan = isset( $subscription['plan'] ) ? sanitize_text_field( $subscription['plan'] ) : 'free_trial';
		$this->update_global_option( 'ai_subscription_plan', $plan );

		// Store features enabled (array of feature IDs)
		$this->update_global_option( 'ai_features_enabled', array_map( 'sanitize_text_field', $features_enabled ) );

		// Store payment provider (freemius, paddle, or empty for free trial)
		$payment_provider = isset( $subscription['payment_provider'] ) ? sanitize_text_field( $subscription['payment_provider'] ) : '';
		if ( $payment_provider ) {
			update_option( 'wpforo_ai_payment_provider', $payment_provider );
		}

		// Store all payment providers list (for tenants with both Freemius and Paddle)
		if ( isset( $subscription['payment_providers'] ) && is_array( $subscription['payment_providers'] ) ) {
			update_option( 'wpforo_ai_payment_providers', array_map( 'sanitize_text_field', $subscription['payment_providers'] ) );
		}

		// Store last sync time for debugging
		$this->update_global_option( 'ai_subscription_synced_at', current_time( 'mysql' ) );
	}

	/**
	 * Get cached subscription plan
	 *
	 * Returns the plan stored in WordPress options.
	 * This doesn't make API calls - use get_tenant_status() to refresh.
	 *
	 * @return string Plan name (free_trial, starter, professional, business, enterprise)
	 */
	public function get_subscription_plan() {
		return $this->get_global_option( 'ai_subscription_plan', 'free_trial' );
	}

	/**
	 * Get cached subscription status
	 *
	 * Returns the subscription status stored in WordPress options.
	 * This doesn't make API calls - use get_tenant_status() to refresh.
	 *
	 * @return string Status (active, trial, inactive, pending_approval, etc.)
	 */
	public function get_subscription_status() {
		return $this->get_global_option( 'ai_subscription_status', '' );
	}

	/**
	 * Get cached features enabled list
	 *
	 * Returns the features_enabled array from last status sync.
	 *
	 * @return array List of enabled feature IDs
	 */
	public function get_features_enabled() {
		$features = $this->get_global_option( 'ai_features_enabled', [] );
		return is_array( $features ) ? $features : [];
	}

	/**
	 * Check if a specific feature is available based on subscription plan
	 *
	 * This method checks locally cached plan data to avoid API calls.
	 * Use this for frontend feature gating (showing/hiding UI elements).
	 *
	 * Note: Backend APIs still verify plan independently for security.
	 *
	 * @param string $feature_id Feature identifier (e.g., 'ai_assistant_chatbot', 'multi_language_translation')
	 * @return bool True if feature is available for current plan
	 */
	public function is_feature_available( $feature_id ) {
		// Service must be available (connected + active subscription)
		if ( ! $this->is_service_available() ) {
			return false;
		}

		// Get current plan from cache
		$current_plan = $this->get_subscription_plan();

		// Get feature definitions to find required plan
		$all_features = $this->get_feature_definitions();
		$feature = isset( $all_features[ $feature_id ] ) ? $all_features[ $feature_id ] : null;

		// Unknown feature - deny by default
		if ( ! $feature ) {
			return false;
		}

		$required_plan = isset( $feature['plan'] ) ? $feature['plan'] : 'enterprise';

		// Check if current plan meets requirement
		return $this->plan_meets_requirement( $current_plan, $required_plan );
	}

	/**
	 * Check if current plan meets or exceeds required plan level
	 *
	 * @param string $current_plan Current subscription plan
	 * @param string $required_plan Required plan for feature
	 * @return bool True if current plan is sufficient
	 */
	private function plan_meets_requirement( $current_plan, $required_plan ) {
		// Plan hierarchy (lower to higher)
		$plan_hierarchy = [
			'free_trial'   => 0,
			'starter'      => 0, // Starter and free_trial are same level
			'professional' => 1,
			'business'     => 2,
			'enterprise'   => 3,
		];

		$current_level = isset( $plan_hierarchy[ $current_plan ] ) ? $plan_hierarchy[ $current_plan ] : 0;
		$required_level = isset( $plan_hierarchy[ $required_plan ] ) ? $plan_hierarchy[ $required_plan ] : 0;

		return $current_level >= $required_level;
	}

	/**
	 * Get feature definitions with plan requirements
	 *
	 * Returns a simplified version of feature definitions for plan checking.
	 * This is a subset of what wpforo_ai_get_all_features() returns.
	 *
	 * @return array Feature ID => ['plan' => required_plan]
	 */
	private function get_feature_definitions() {
		return [
			// Starter Plan Features (also available on free_trial)
			'semantic_search'            => [ 'plan' => 'starter' ],
			'search_enhance'             => [ 'plan' => 'starter' ],
			'content_indexing'           => [ 'plan' => 'starter' ],
			'multi_language_translation' => [ 'plan' => 'starter' ],
			'topic_summary'              => [ 'plan' => 'starter' ],
			'smart_topic_suggestions'    => [ 'plan' => 'starter' ],
			'ai_spam_detection'          => [ 'plan' => 'starter' ],
			'ai_toxicity_detection'      => [ 'plan' => 'starter' ],
			'ai_rule_compliance'         => [ 'plan' => 'starter' ],

			// Professional Plan Features
			'analytics_insights'         => [ 'plan' => 'professional' ],
			'ai_topic_generator'         => [ 'plan' => 'professional' ],
			'ai_reply_generator'         => [ 'plan' => 'professional' ],
			'ai_bot_reply'               => [ 'plan' => 'professional' ],
			'auto_tag_generation'        => [ 'plan' => 'professional' ],

			// Business Plan Features
			'ai_assistant_chatbot'          => [ 'plan' => 'business' ],
			'extended_knowledge_base'       => [ 'plan' => 'business' ],
			'wordpress_content_indexing'    => [ 'plan' => 'business' ],
			'custom_post_types_indexing'    => [ 'plan' => 'business' ],
			'woocommerce_products_indexing' => [ 'plan' => 'business' ],
			'vector_db_cloud_storage'       => [ 'plan' => 'business' ],

			// Enterprise Plan Features
			'developer_features'         => [ 'plan' => 'enterprise' ],
			'rest_api_access'            => [ 'plan' => 'enterprise' ],
			'custom_ai_models'           => [ 'plan' => 'enterprise' ],
			'custom_feature_development' => [ 'plan' => 'enterprise' ],
			'premium_support'            => [ 'plan' => 'enterprise' ],
			'dedicated_account_manager'  => [ 'plan' => 'enterprise' ],
			'enterprise_capabilities'    => [ 'plan' => 'enterprise' ],
		];
	}

	/**
	 * Clear cached tenant status
	 * Forces fresh fetch on next status request
	 */
	public function clear_status_cache() {
		delete_transient( 'wpforo_ai_tenant_status' );
	}

	/**
	 * Get indexed topic statistics by forum
	 *
	 * Returns indexed topic counts per forum for displaying in admin UI
	 *
	 * @return array|WP_Error Response data with forum_counts or error object
	 */
	public function get_indexed_stats_by_forum() {
		$response = $this->get( '/rag/indexed-stats/forums' );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'indexed_stats_fetch_failed', $response->get_error_message() );
			return $response;
		}

		return $response;
	}

	/**
	 * Disconnect service (soft delete)
	 *
	 * @param string $reason Reason for disconnection
	 * @param bool $confirm Confirmation flag
	 * @return array|WP_Error Response data or error object
	 */
	public function disconnect_tenant( $reason = '', $confirm = false, $purge_data = false ) {
		$data = [
			'reason'     => sanitize_text_field( $reason ),
			'confirm'    => (bool) $confirm,
			'purge_data' => (bool) $purge_data,
		];

		$response = $this->delete( '/tenant/disconnect', $data );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'disconnection_failed', $response->get_error_message() );
			return $response;
		}

		$this->log_info( 'tenant_disconnected', [ 'reason' => $reason ] );
		$this->clear_status_cache();

		// Clear indexed status for all topics (vectors are deleted on disconnect)
		$this->clear_topics_indexed_status();

		do_action( 'wpforo_ai_tenant_disconnected', $response );

		return $response;
	}

	/**
	 * Check eligibility for bonus credits (large forum incentive)
	 *
	 * @return array Eligibility data with 'eligible' boolean and 'data' array
	 */
	public function check_bonus_credits_eligibility() {
		global $wpdb;

		// Get wpforo table names
		$topics_table  = WPF()->tables->topics ?? $wpdb->prefix . 'wpforo_topics';
		$posts_table   = WPF()->tables->posts ?? $wpdb->prefix . 'wpforo_posts';
		$profile_table = WPF()->tables->profiles ?? $wpdb->prefix . 'wpforo_profiles';

		// 1. Count approved, non-private topics (status=0, private=0)
		$topic_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$topics_table} WHERE status = 0 AND private = 0"
		);

		// 2. Count approved posts (status=0)
		$post_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$posts_table} WHERE status = 0"
		);

		// 3. Get days between first and last topic
		$date_range = $wpdb->get_row(
			"SELECT
				MIN(created) as first_topic,
				MAX(created) as last_topic
			FROM {$topics_table}
			WHERE status = 0 AND private = 0"
		);

		$days_active = 0;
		if ( $date_range && $date_range->first_topic && $date_range->last_topic ) {
			$first_time   = strtotime( $date_range->first_topic );
			$last_time    = strtotime( $date_range->last_topic );
			$days_active  = (int) floor( ( $last_time - $first_time ) / DAY_IN_SECONDS );
		}

		// 4. Count distinct topic authors who have login history (online_time > 0)
		$active_authors = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT t.userid)
			FROM {$topics_table} t
			INNER JOIN {$profile_table} p ON t.userid = p.userid
			WHERE t.status = 0 AND t.private = 0 AND t.userid > 0 AND p.online_time > 0"
		);

		// Determine eligibility
		$eligible = (
			$topic_count >= 501 &&
			$post_count >= 511 &&
			$days_active >= 30 &&
			$active_authors >= 10
		);

		return [
			'eligible' => $eligible,
			'data'     => [
				'topic_count'    => $topic_count,
				'post_count'     => $post_count,
				'days_active'    => $days_active,
				'active_authors' => $active_authors,
			],
			'requirements' => [
				'min_topics'         => 501,
				'min_posts'          => 511,
				'min_days'           => 30,
				'min_active_authors' => 10,
			],
		];
	}

	/**
	 * Request bonus credits from API
	 *
	 * @param array $eligibility_data Data from check_bonus_credits_eligibility()
	 * @return array|WP_Error Response with credits_added or error
	 */
	public function request_bonus_credits( $eligibility_data ) {
		$response = $this->post( '/tenant/bonus-credits', [
			'topic_count'    => (int) $eligibility_data['topic_count'],
			'post_count'     => (int) $eligibility_data['post_count'],
			'days_active'    => (int) $eligibility_data['days_active'],
			'active_authors' => (int) $eligibility_data['active_authors'],
		] );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'bonus_credits_failed', $response->get_error_message() );
			return $response;
		}

		// Store bonus credits info locally
		// Use isset() instead of !empty() for credits_added — empty(0) is true in PHP,
		// which would skip saving when credits_added is 0 (e.g., due to cap enforcement)
		if ( ! empty( $response['success'] ) && isset( $response['credits_added'] ) ) {
			update_option( 'wpforo_ai_bonus_credits_claimed', true );
			update_option( 'wpforo_ai_bonus_credits_amount', (int) $response['credits_added'] );
			update_option( 'wpforo_ai_bonus_credits_claimed_at', current_time( 'mysql' ) );

			$this->log_info( 'bonus_credits_granted', [
				'credits_added' => $response['credits_added'],
			] );

			// Clear status cache to reflect new credits
			$this->clear_status_cache();
		}

		return $response;
	}

	/**
	 * AJAX handler for requesting bonus credits
	 *
	 * @return void
	 */
	public function ajax_request_bonus_credits() {
		$this->verify_ajax_admin_request( 'wpforo_ai_features_nonce', '_wpnonce' );

		// Check if already claimed locally
		if ( get_option( 'wpforo_ai_bonus_credits_claimed', false ) ) {
			$this->send_error(
				wpforo_phrase( 'Bonus credits have already been claimed.', false ),
				[ 'code' => 'already_claimed' ]
			);
		}

		// Check eligibility
		$eligibility = $this->check_bonus_credits_eligibility();

		if ( ! $eligibility['eligible'] ) {
			$this->send_error(
				wpforo_phrase( 'Forum does not meet eligibility requirements for bonus credits.', false ),
				[
					'code'         => 'not_eligible',
					'requirements' => $eligibility['requirements'],
					'current'      => $eligibility['data'],
				]
			);
		}

		// Request bonus credits from API
		$response = $this->request_bonus_credits( $eligibility['data'] );

		if ( is_wp_error( $response ) ) {
			$this->send_error( $response->get_error_message() );
		}

		$this->send_success( [
			'message'       => $response['message'] ?? wpforo_phrase( 'Bonus credits added successfully!', false ),
			'credits_added' => $response['credits_added'] ?? 0,
		] );
	}

	/**
	 * Check if bonus credits have been claimed
	 *
	 * @return bool|array False if not claimed, array with details if claimed
	 */
	public function get_bonus_credits_status() {
		$claimed = get_option( 'wpforo_ai_bonus_credits_claimed', false );

		if ( ! $claimed ) {
			return false;
		}

		return [
			'claimed'    => true,
			'amount'     => (int) get_option( 'wpforo_ai_bonus_credits_amount', 0 ),
			'claimed_at' => get_option( 'wpforo_ai_bonus_credits_claimed_at', '' ),
		];
	}

	/**
	 * Get AI Content Indexing status
	 *
	 * Returns current status of AI Content Indexing including total indexed threads,
	 * progress, and whether indexing is currently active.
	 *
	 * @return array|WP_Error Status data or error object
	 */
	public function get_rag_status( $boardid = 0 ) {
		// Check cache first (30 second cache for frequent updates)
		// Board-specific cache key
		$cache_key = 'wpforo_ai_rag_status_' . intval( $boardid );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && ! $this->is_debug_mode() ) {
			return $cached;
		}

		// Include boardid in API request for future backend filtering
		$endpoint = '/rag/status';
		if ( $boardid > 0 ) {
			$endpoint .= '?boardid=' . intval( $boardid );
		}

		$response = $this->get( $endpoint );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'rag_status_fetch_failed', $response->get_error_message() );
			return $response;
		}

		// Cache the response for 30 seconds (short cache for indexing status)
		set_transient( $cache_key, $response, 30 );

		return $response;
	}

	/**
	 * AJAX handler for getting RAG status
	 *
	 * @return void
	 */
	public function ajax_get_rag_status() {
		$this->verify_ajax_admin_request( 'wpforo_ai_features_nonce', '_wpnonce' );

		// Use VectorStorageManager to get stats (routes to local or cloud automatically)
		$storage_manager = WPF()->vector_storage;
		$status = $storage_manager->get_indexing_stats();

		// Clear tenant status cache to get fresh credit info
		$this->clear_status_cache();

		// Add tenant status info for credits display (fresh fetch)
		$tenant_status = $this->get_tenant_status();
		if ( ! is_wp_error( $tenant_status ) ) {
			$status['tenant_id'] = $tenant_status['tenant_id'] ?? '';
			$status['credits'] = $tenant_status['credits'] ?? [];
			$status['subscription_tier'] = $tenant_status['subscription_tier'] ?? '';
		}

		// Add pending cron jobs info
		$pending_jobs = $this->get_pending_cron_jobs();
		$status['pending_cron_jobs'] = $pending_jobs;

		$this->send_success( $status );
	}

	/**
	 * Ask the backend to stop any in-flight cloud indexing for this tenant.
	 *
	 * Sends POST /v1/rag/cancel, which sets a cancellation flag on the
	 * tenant record with a ~10 minute TTL. The backend reads the flag
	 * at the start of every queued message and skips (without charging
	 * credits) anything still pending. Already in-flight processing
	 * calls are allowed to finish and are billed.
	 *
	 * @return array|WP_Error Backend response or error object
	 */
	public function cancel_indexing() {
		return $this->post( '/rag/cancel', [], 30 );
	}

	/**
	 * Cleanup stuck cloud indexing jobs (backend).
	 *
	 * Calls POST /v1/rag/cleanup-jobs — scans the tenant's rag_jobs
	 * records, force-finalizes anything stuck (media_done < media_total,
	 * created >1h ago), refunds the unused portion of the image/document
	 * credit reservation, and sets indexing_cancel_until so any in-flight
	 * SQS messages drain without charging.
	 *
	 * Idempotent: backend uses a conditional update on
	 * media_credits_charged, so re-clicking is safe — no double refund.
	 * Safe in local storage mode: tenants with no rag_jobs get a zero-count
	 * response with no side effects.
	 *
	 * @return array|WP_Error Backend response: status, jobs_scanned,
	 *                        jobs_finalized, refund_images, refund_docs, ...
	 */
	public function cleanup_jobs() {
		return $this->post( '/rag/cleanup-jobs', [], 30 );
	}

	/**
	 * AJAX handler: stop in-flight cloud indexing.
	 *
	 * Mirrors the local-mode `clearLocalIndexingQueue` flow — no page
	 * reload, UI polling will pick up the drained state via the regular
	 * `/rag/status` poll.
	 *
	 * @return void
	 */
	public function ajax_cancel_cloud_indexing() {
		$this->verify_ajax_admin_request( 'wpforo_ai_features_nonce', '_wpnonce' );

		// Clear any WordPress cron jobs scheduled for indexing (cloud/local/legacy
		// hooks) and the per-board queue options. The legacy form-submit Stop
		// flow did this via wpforo_ai_handle_stop_indexing(); the new AJAX path
		// must do the same or orphaned crons continue firing after Stop.
		$this->clear_pending_cron_jobs();

		$result = $this->cancel_indexing();

		if ( is_wp_error( $result ) ) {
			$this->send_error(
				wpforo_phrase( 'Failed to stop indexing. Please try again.', false ),
				500
			);
		}

		$this->send_success( [
			'message' => wpforo_phrase( 'Indexing is being stopped. In-flight items may take a few minutes to drain.', false ),
		] );
	}

	/**
	 * Cleanup stuck indexing session state.
	 *
	 * Resets all "in-progress" markers (queues, crons, locks, caches, backend
	 * cancel flag) for either the forum-indexing pipeline or the WordPress
	 * content indexing pipeline — without touching any successfully-indexed
	 * data (vectors, wpforo_ai_local_vectors rows, topics.cloud/local/indexed
	 * columns, credit counters).
	 *
	 * Covers BOTH local and cloud storage modes in a single call: forum
	 * cleanup clears local queues, cloud queues, legacy queues, and tells the
	 * backend worker to drop any queued items.
	 *
	 * @param string $scope 'forum' | 'wp' | 'all'
	 * @return array Summary of what was cleared.
	 */
	public function cleanup_indexing_session( $scope = 'forum' ) {
		$board_id = WPF()->board->get_current( 'boardid' ) ?: 0;
		$summary = [
			'scope'              => $scope,
			'options_deleted'    => 0,
			'transients_deleted' => 0,
			'crons_cleared'      => 0,
			'topics_cleared'     => 0,
			'backend_cancelled'  => false,
			'backend_cleanup'    => false,
		];

		if ( $scope === 'forum' || $scope === 'all' ) {
			// Reuse the existing helper — it clears local/cloud/legacy queue
			// options AND their cron hooks for the current board, including
			// the legacy wpforo_ai_process_batch pattern.
			$cron_result = $this->clear_pending_cron_jobs();
			$summary['topics_cleared'] += (int) ( $cron_result['cleared_topics'] ?? 0 );
			$summary['crons_cleared']  += (int) ( $cron_result['cleared_jobs'] ?? 0 );

			// Session snapshot (chunk_size/overlap/batch_size/total/started_at)
			if ( delete_option( 'wpforo_ai_indexing_settings_' . $board_id ) ) {
				$summary['options_deleted']++;
			}

			// Per-board 2-min batch lock (ai-features-helpers.php uses this to
			// serialize AJAX + WP-Cron processing). If it's stale, new batches
			// refuse to run until the TTL expires.
			if ( delete_transient( 'wpforo_ai_indexing_lock_' . $board_id ) ) {
				$summary['transients_deleted']++;
			}

			// Global 5-min "clearing in progress" semaphore. If set while
			// a clear operation crashed, it blocks the UI for up to 5 minutes.
			if ( delete_transient( 'wpforo_ai_clearing_in_progress' ) ) {
				$summary['transients_deleted']++;
			}

			// Force-refresh the cached RAG status so the UI flips to Idle
			// immediately after cleanup.
			if ( delete_transient( 'wpforo_ai_rag_status' ) ) {
				$summary['transients_deleted']++;
			}
			if ( delete_transient( 'wpforo_ai_rag_status_' . $board_id ) ) {
				$summary['transients_deleted']++;
			}

			// Tell the backend to drop any in-flight cloud image_worker items.
			// Safe in local mode: backend simply sets indexing_cancel_until on
			// the tenant record with no other side effects. Errors are
			// non-fatal — WP-side cleanup has already succeeded.
			$cancel = $this->cancel_indexing();
			$summary['backend_cancelled'] = ! is_wp_error( $cancel );

			// Force-finalize any stuck rag_jobs server-side and refund the
			// unused portion of the image/document credit reservation.
			// Idempotent and tenant-wide; safe in local mode (no jobs to
			// finalize). Errors are non-fatal — WP-side cleanup has already
			// succeeded and the backend reaper cron runs hourly anyway.
			$cleanup = $this->cleanup_jobs();
			$summary['backend_cleanup'] = is_wp_error( $cleanup ) ? false : $cleanup;
		}

		if ( $scope === 'wp' || $scope === 'all' ) {
			// WordPress post/page indexing queue (single global key, not
			// board-scoped — WP content is global).
			if ( delete_option( 'wpforo_ai_wp_indexing_queue' ) ) {
				$summary['options_deleted']++;
			}

			// WP indexing status cache (5-min TTL) — deleting it forces the
			// UI to query fresh state.
			if ( delete_transient( 'wpforo_ai_wp_indexing_status' ) ) {
				$summary['transients_deleted']++;
			}

			// WP post/page batch cron (single-event, no args, reschedules
			// itself). wp_clear_scheduled_hook removes all pending events.
			if ( wp_next_scheduled( 'wpforo_ai_process_wp_batch' ) ) {
				$summary['crons_cleared']++;
			}
			wp_clear_scheduled_hook( 'wpforo_ai_process_wp_batch' );
		}

		return $summary;
	}

	/**
	 * AJAX handler: cleanup stuck indexing session state.
	 *
	 * POST params:
	 *   scope    — 'forum' | 'wp' | 'all' (default 'forum')
	 *   _wpnonce — wpforo_ai_features_nonce
	 *
	 * @return void
	 */
	public function ajax_cleanup_indexing_session() {
		$this->verify_ajax_admin_request( 'wpforo_ai_features_nonce', '_wpnonce' );

		$scope = $this->get_post_param( 'scope', 'forum' );
		if ( ! in_array( $scope, [ 'forum', 'wp', 'all' ], true ) ) {
			$scope = 'forum';
		}

		$summary = $this->cleanup_indexing_session( $scope );

		$this->send_success( [
			'summary' => $summary,
			'message' => wpforo_phrase( 'Indexing session cleaned up. Stuck jobs, queues and cached state have been cleared.', false ),
		] );
	}

	/**
	 * AJAX handler for saving storage mode setting
	 *
	 * @return void
	 */
	public function ajax_save_storage_mode() {
		$this->verify_ajax_admin_request( 'wpforo_ai_features_nonce', 'nonce' );

		// Get and validate storage mode
		$storage_mode = $this->get_post_param( 'storage_mode', 'local' );
		if ( ! in_array( $storage_mode, [ 'local', 'cloud' ], true ) ) {
			$storage_mode = 'local';
		}

		$board_id = $this->get_post_param( 'board_id', 0, 'int' );

		// Save the setting
		$option_name = 'wpforo_ai_storage_mode_' . $board_id;
		$old_mode = get_option( $option_name, 'local' );
		update_option( $option_name, $storage_mode );

		// If mode changed, sync the indexed status for the new mode
		$sync_result = null;
		if ( $old_mode !== $storage_mode ) {
			// Reset cached mode in VectorStorageManager
			WPF()->vector_storage->reset_storage_mode_cache();

			// Sync indexed status based on new mode
			if ( $storage_mode === 'local' ) {
				$sync_result = WPF()->vector_storage->sync_local_indexed_status();
			} else {
				$sync_result = WPF()->vector_storage->sync_cloud_indexed_status();
			}
		}

		// Log the change
		$this->log_info( 'storage_mode_changed', [
			'board_id' => $board_id,
			'old_mode' => $old_mode,
			'storage_mode' => $storage_mode,
			'sync_result' => is_wp_error( $sync_result ) ? $sync_result->get_error_message() : $sync_result
		] );

		$response_data = [
			'message' => wpforo_phrase( 'Storage mode saved successfully.', false ),
			'storage_mode' => $storage_mode,
			'board_id' => $board_id
		];

		if ( $sync_result && ! is_wp_error( $sync_result ) ) {
			$response_data['sync'] = $sync_result;
		}

		$this->send_success( $response_data );
	}

	/**
	 * AJAX handler to save auto-indexing setting
	 *
	 * Saves the auto-indexing enabled/disabled state for a specific board.
	 * When enabled, new and approved topics will be automatically queued for indexing.
	 *
	 * @return void Sends JSON response
	 */
	public function ajax_save_auto_indexing() {
		$this->verify_ajax_admin_request( 'wpforo_ai_features_nonce', 'nonce' );

		$enabled  = $this->get_post_param( 'enabled', 0, 'bool' ) ? 1 : 0;
		$board_id = $this->get_post_param( 'board_id', 0, 'int' );

		// Switch to the correct board context
		if ( $board_id > 0 ) {
			WPF()->change_board( $board_id );
		}

		// Save the setting using wpforo options (board-specific)
		wpforo_update_option( 'ai_auto_indexing_enabled', $enabled );

		// If enabling auto-indexing, schedule the cron jobs
		if ( $enabled ) {
			$this->schedule_pending_topics_indexing();
		} else {
			$this->unschedule_pending_topics_indexing();
		}

		// Log the change
		$this->log_info( 'auto_indexing_changed', [
			'board_id' => $board_id,
			'enabled'  => $enabled
		] );

		$this->send_success( [
			'message'  => $enabled
				? wpforo_phrase( 'Auto-indexing enabled successfully.', false )
				: wpforo_phrase( 'Auto-indexing disabled successfully.', false ),
			'enabled'  => $enabled,
			'board_id' => $board_id
		] );
	}

	/**
	 * AJAX handler to save image indexing setting
	 *
	 * Saves the image indexing enabled/disabled state for a specific board.
	 * When enabled, posts with images will consume +1 additional credit for
	 * multimodal processing (image → text description → embedding).
	 *
	 * Feature Requirements:
	 * - Business or Enterprise plan required
	 * - Maximum 10 images per post (enforced by API)
	 * - +1 credit per post that has images (not per image)
	 *
	 * @return void Sends JSON response
	 */
	public function ajax_save_image_indexing() {
		// Verify nonce
		check_ajax_referer( 'wpforo_ai_features_nonce', 'nonce' );

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Insufficient permissions', false )
			], 403 );
		}

		// Check plan eligibility (Business/Enterprise only)
		$status = $this->get_tenant_status();
		if ( is_wp_error( $status ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Could not verify subscription status', false )
			], 400 );
		}

		$plan = isset( $status['subscription']['plan'] ) ? strtolower( $status['subscription']['plan'] ) : '';
		if ( ! in_array( $plan, [ 'professional', 'business', 'enterprise' ], true ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Image indexing requires Professional plan or higher', false )
			], 403 );
		}

		// Get and validate enabled state
		$enabled = isset( $_POST['enabled'] ) ? (int) $_POST['enabled'] : 0;
		$enabled = $enabled ? 1 : 0;

		// Get board ID
		$board_id = isset( $_POST['board_id'] ) ? intval( $_POST['board_id'] ) : 0;

		// Switch to the correct board context
		if ( $board_id > 0 ) {
			WPF()->change_board( $board_id );
		}

		// Save the setting using wpforo options (board-specific)
		wpforo_update_option( 'ai_image_indexing_enabled', $enabled );

		// Log the change
		$this->log_info( 'image_indexing_changed', [
			'board_id' => $board_id,
			'enabled'  => $enabled
		] );

		wp_send_json_success( [
			'message'  => $enabled
				? wpforo_phrase( 'Image indexing enabled. Posts with images will consume +1 additional credit.', false )
				: wpforo_phrase( 'Image indexing disabled.', false ),
			'enabled'  => $enabled,
			'board_id' => $board_id
		] );
	}

	/**
	 * AJAX handler for saving document indexing setting
	 *
	 * Saves the board-specific document indexing enabled/disabled state.
	 * Requires Professional+ plan.
	 */
	public function ajax_save_document_indexing() {
		check_ajax_referer( 'wpforo_ai_features_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Insufficient permissions', false )
			], 403 );
		}

		$status = $this->get_tenant_status();
		if ( is_wp_error( $status ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Could not verify subscription status', false )
			], 400 );
		}

		$plan = isset( $status['subscription']['plan'] ) ? strtolower( $status['subscription']['plan'] ) : '';
		if ( ! in_array( $plan, [ 'professional', 'business', 'enterprise' ], true ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Document indexing requires Professional plan or higher', false )
			], 403 );
		}

		$enabled = isset( $_POST['enabled'] ) ? (int) $_POST['enabled'] : 0;
		$enabled = $enabled ? 1 : 0;

		$board_id = isset( $_POST['board_id'] ) ? intval( $_POST['board_id'] ) : 0;
		if ( $board_id > 0 ) {
			WPF()->change_board( $board_id );
		}

		wpforo_update_option( 'ai_document_indexing_enabled', $enabled );

		$this->log_info( 'document_indexing_changed', [
			'board_id' => $board_id,
			'enabled'  => $enabled
		] );

		wp_send_json_success( [
			'message'  => $enabled
				? wpforo_phrase( 'Document indexing enabled. Credit cost: 1 per page.', false )
				: wpforo_phrase( 'Document indexing disabled.', false ),
			'enabled'  => $enabled,
			'board_id' => $board_id
		] );
	}

	/**
	 * AJAX handler for saving WordPress indexing options
	 *
	 * WordPress content is global (not board-specific), so these settings
	 * are saved globally using update_option() instead of wpforo_update_option().
	 *
	 * Supported options:
	 * - ai_wp_auto_indexing_enabled: Auto-index new WordPress content
	 * - ai_wp_image_indexing_enabled: Include images in WP content indexing
	 */
	public function ajax_save_wp_indexing_option() {
		// Verify nonce
		check_ajax_referer( 'wpforo_ai_features_nonce', 'nonce' );

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Insufficient permissions', false )
			], 403 );
		}

		// Get option name and validate it's one of the allowed options
		$option_name = isset( $_POST['option_name'] ) ? sanitize_key( $_POST['option_name'] ) : '';
		$allowed_options = [ 'ai_wp_auto_indexing_enabled', 'ai_wp_image_indexing_enabled' ];

		if ( ! in_array( $option_name, $allowed_options, true ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Invalid option name', false )
			], 400 );
		}

		// For image indexing, check plan eligibility (Professional/Business/Enterprise)
		if ( $option_name === 'ai_wp_image_indexing_enabled' ) {
			$status = $this->get_tenant_status();
			if ( is_wp_error( $status ) ) {
				wp_send_json_error( [
					'message' => wpforo_phrase( 'Could not verify subscription status', false )
				], 400 );
			}

			$plan = isset( $status['subscription']['plan'] ) ? strtolower( $status['subscription']['plan'] ) : '';
			if ( ! in_array( $plan, [ 'professional', 'business', 'enterprise' ], true ) ) {
				wp_send_json_error( [
					'message' => wpforo_phrase( 'Image indexing requires Professional, Business or Enterprise plan', false )
				], 403 );
			}
		}

		// Get and validate enabled state
		$enabled = isset( $_POST['enabled'] ) ? (int) $_POST['enabled'] : 0;
		$enabled = $enabled ? 1 : 0;

		// Save globally using WordPress options (not board-specific)
		update_option( 'wpforo_' . $option_name, $enabled );

		// Log the change
		$this->log_info( 'wp_indexing_option_changed', [
			'option'  => $option_name,
			'enabled' => $enabled
		] );

		// Prepare success message based on option
		if ( $option_name === 'ai_wp_image_indexing_enabled' ) {
			$message = $enabled
				? wpforo_phrase( 'WordPress image indexing enabled. Posts with images will consume +1 additional credit.', false )
				: wpforo_phrase( 'WordPress image indexing disabled.', false );
		} else {
			$message = $enabled
				? wpforo_phrase( 'WordPress auto-indexing enabled. New content will be indexed automatically.', false )
				: wpforo_phrase( 'WordPress auto-indexing disabled.', false );
		}

		wp_send_json_success( [
			'message' => $message,
			'enabled' => $enabled,
			'option'  => $option_name
		] );
	}

	/**
	 * AJAX handler for linking Freemius subscription to tenant
	 *
	 * Called after successful Freemius checkout to store subscription_id and user_id.
	 * This enables webhook matching when emails don't match.
	 *
	 * @return void Sends JSON response
	 */
	public function ajax_link_subscription() {
		// Verify nonce
		check_ajax_referer( 'wpforo_ai_features_nonce', 'nonce' );

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions' ], 403 );
		}

		$subscription_id = isset( $_POST['subscription_id'] ) ? sanitize_text_field( $_POST['subscription_id'] ) : '';
		$user_id         = isset( $_POST['user_id'] ) ? sanitize_text_field( $_POST['user_id'] ) : '';
		$plan            = isset( $_POST['plan'] ) ? sanitize_text_field( $_POST['plan'] ) : '';

		if ( empty( $subscription_id ) ) {
			wp_send_json_error( [ 'message' => 'Missing subscription_id' ], 400 );
		}

		// Call backend API to link subscription
		$response = $this->post( '/tenant/link-subscription', [
			'freemius_subscription_id' => $subscription_id,
			'freemius_user_id'         => $user_id,
			'plan'                     => $plan,
		] );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'link_subscription_failed', $response->get_error_message() );
			wp_send_json_error( [ 'message' => $response->get_error_message() ], 400 );
		}

		$this->log_info( 'subscription_linked', [
			'subscription_id' => $subscription_id,
			'user_id'         => $user_id,
			'plan'            => $plan,
		] );

		wp_send_json_success( [ 'message' => 'Subscription linked successfully' ] );
	}

	/**
	 * AJAX handler for manual license activation
	 *
	 * Called when user enters a License ID to manually activate their plan.
	 * The backend verifies with Freemius API and updates the subscription.
	 *
	 * Note: We only transmit the License ID (a numeric identifier like "1845944"),
	 * NOT the License Key (sk_...). The License ID is safe to store as it's just
	 * a reference number, not a secret.
	 *
	 * @return void Sends JSON response
	 */
	public function ajax_activate_license() {
		// Verify nonce
		check_ajax_referer( 'wpforo_ai_features_nonce', 'nonce' );

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions' ], 403 );
		}

		$license_id = isset( $_POST['license_id'] ) ? sanitize_text_field( $_POST['license_id'] ) : '';

		if ( empty( $license_id ) ) {
			wp_send_json_error( [ 'message' => 'Please enter your License ID' ], 400 );
		}

		// Validate license_id format (should be numeric)
		if ( ! preg_match( '/^\d+$/', $license_id ) ) {
			wp_send_json_error( [ 'message' => 'Invalid License ID format. Please enter the numeric License ID from your purchase confirmation.' ], 400 );
		}

		// Call backend API to verify and activate license
		$response = $this->post( '/tenant/activate-license', [
			'license_id' => $license_id,
		] );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'license_activation_failed', $response->get_error_message() );
			wp_send_json_error( [ 'message' => $response->get_error_message() ], 400 );
		}

		// Clear cached subscription data so it refreshes
		delete_option( 'wpforo_ai_subscription_plan' );
		delete_transient( 'wpforo_ai_subscription' );
		delete_transient( 'wpforo_ai_tenant_status' );

		$this->log_info( 'license_activated', [
			'license_id' => $license_id,
			'plan'       => $response['plan'] ?? '',
		] );

		wp_send_json_success( [
			'message'       => $response['message'] ?? 'License activated successfully',
			'plan'          => $response['plan'] ?? '',
			'credits_added' => $response['credits_added'] ?? 0,
		] );
	}

	/**
	 * AJAX handler for activating a Paddle transaction manually.
	 * Mirrors ajax_activate_license() but for Paddle Transaction IDs.
	 */
	public function ajax_activate_paddle_transaction() {
		check_ajax_referer( 'wpforo_ai_features_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions' ], 403 );
		}

		$transaction_id = isset( $_POST['transaction_id'] ) ? sanitize_text_field( $_POST['transaction_id'] ) : '';

		if ( empty( $transaction_id ) ) {
			wp_send_json_error( [ 'message' => 'Please enter your Transaction ID' ], 400 );
		}

		if ( strpos( $transaction_id, 'txn_' ) !== 0 ) {
			wp_send_json_error( [ 'message' => 'Invalid Transaction ID format. Must start with "txn_".' ], 400 );
		}

		$response = $this->post( '/tenant/activate-paddle-transaction', [
			'transaction_id' => $transaction_id,
		] );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'paddle_transaction_activation_failed', $response->get_error_message() );
			wp_send_json_error( [ 'message' => $response->get_error_message() ], 400 );
		}

		// Clear cached subscription data so it refreshes
		delete_option( 'wpforo_ai_subscription_plan' );
		delete_transient( 'wpforo_ai_subscription' );
		delete_transient( 'wpforo_ai_tenant_status' );

		$this->log_info( 'paddle_transaction_activated', [
			'transaction_id'   => $transaction_id,
			'plan'             => $response['plan'] ?? '',
			'transaction_type' => $response['transaction_type'] ?? '',
		] );

		wp_send_json_success( [
			'message'          => $response['message'] ?? 'Transaction activated successfully',
			'plan'             => $response['plan'] ?? '',
			'credits_added'    => $response['credits_added'] ?? 0,
			'transaction_type' => $response['transaction_type'] ?? '',
		] );
	}

	/**
	 * AJAX handler for creating a Paddle checkout
	 *
	 * Creates a server-side Paddle transaction via the backend.
	 * Returns a checkout URL where Paddle.js is loaded and opens the
	 * checkout overlay for the transaction.
	 *
	 * @return void Sends JSON response with checkout_url
	 */
	public function ajax_paddle_checkout() {
		// Verify nonce
		check_ajax_referer( 'wpforo_ai_features_nonce', 'nonce' );

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions' ], 403 );
		}

		$price_id = isset( $_POST['price_id'] ) ? sanitize_text_field( $_POST['price_id'] ) : '';
		$plan     = isset( $_POST['plan'] ) ? sanitize_text_field( $_POST['plan'] ) : '';

		if ( empty( $price_id ) ) {
			wp_send_json_error( [ 'message' => 'Missing price_id' ], 400 );
		}

		// Get tenant info
		$tenant_id      = $this->get_tenant_id();
		$current_user   = wp_get_current_user();
		$customer_email = ! empty( $current_user->user_email ) ? $current_user->user_email : get_option( 'admin_email' );
		$customer_name  = trim( $current_user->first_name . ' ' . $current_user->last_name );

		if ( empty( $tenant_id ) ) {
			wp_send_json_error( [ 'message' => 'Not connected. Please generate an API key first.' ], 400 );
		}

		// Call backend Lambda to create Paddle checkout transaction
		$response = $this->post( '/paddle/create-checkout', [
			'tenant_id'      => $tenant_id,
			'price_id'       => $price_id,
			'customer_email' => $customer_email,
			'customer_name'  => $customer_name ?: null,
			'site_url'       => site_url(),
		] );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'paddle_checkout_failed', $response->get_error_message() );
			wp_send_json_error( [ 'message' => $response->get_error_message() ], 400 );
		}

		$checkout_url = $response['checkout_url'] ?? '';
		if ( empty( $checkout_url ) ) {
			wp_send_json_error( [ 'message' => 'No checkout URL returned. Please try again.' ], 500 );
		}

		$this->log_info( 'paddle_checkout_created', [
			'price_id'       => $price_id,
			'plan'           => $plan,
			'transaction_id' => $response['transaction_id'] ?? '',
		] );

		wp_send_json_success( [
			'checkout_url'   => $checkout_url,
			'transaction_id' => $response['transaction_id'] ?? '',
		] );
	}

	/**
	 * AJAX handler for linking Paddle subscription to tenant
	 *
	 * Called after Paddle checkout to store paddle_subscription_id and paddle_customer_id.
	 * This is a belt-and-suspenders approach — webhooks should already handle this via
	 * custom_data.tenant_id, but calling this ensures the link is established immediately.
	 *
	 * @return void Sends JSON response
	 */
	public function ajax_link_paddle_subscription() {
		// Verify nonce
		check_ajax_referer( 'wpforo_ai_features_nonce', 'nonce' );

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions' ], 403 );
		}

		$paddle_subscription_id = isset( $_POST['paddle_subscription_id'] ) ? sanitize_text_field( $_POST['paddle_subscription_id'] ) : '';
		$paddle_customer_id     = isset( $_POST['paddle_customer_id'] ) ? sanitize_text_field( $_POST['paddle_customer_id'] ) : '';
		$plan                   = isset( $_POST['plan'] ) ? sanitize_text_field( $_POST['plan'] ) : '';

		if ( empty( $paddle_subscription_id ) ) {
			wp_send_json_error( [ 'message' => 'Missing paddle_subscription_id' ], 400 );
		}

		// Call backend API to link Paddle subscription
		$response = $this->post( '/tenant/link-paddle-subscription', [
			'paddle_subscription_id' => $paddle_subscription_id,
			'paddle_customer_id'     => $paddle_customer_id,
			'plan'                   => $plan,
		] );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'link_paddle_subscription_failed', $response->get_error_message() );
			wp_send_json_error( [ 'message' => $response->get_error_message() ], 400 );
		}

		// Store payment provider locally for manage subscription URL routing
		update_option( 'wpforo_ai_payment_provider', 'paddle' );

		$this->log_info( 'paddle_subscription_linked', [
			'paddle_subscription_id' => $paddle_subscription_id,
			'paddle_customer_id'     => $paddle_customer_id,
			'plan'                   => $plan,
		] );

		wp_send_json_success( [ 'message' => 'Paddle subscription linked successfully' ] );
	}

	/**
	 * AJAX handler for searching bot users (for Bot Reply settings)
	 *
	 * Searches for activated WordPress users by login, display name, or email.
	 * Only returns users with empty user_activation_key (active accounts).
	 *
	 * @return void Sends JSON response
	 */
	public function ajax_search_bot_users() {
		// Verify nonce - use settings form nonce
		check_ajax_referer( 'wpforo_ai_bot_user_search', '_wpnonce' );

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied' ] );
		}

		$search  = sanitize_text_field( $_POST['search'] ?? '' );
		$user_id = intval( $_POST['user_id'] ?? 0 );

		global $wpdb;

		// If user_id is provided, look up that specific user
		if ( $user_id > 0 ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$role = ! empty( $user->roles ) ? ucfirst( $user->roles[0] ) : '';
				wp_send_json_success( [
					'users' => [
						[
							'id'           => $user->ID,
							'user_login'   => $user->user_login,
							'display_name' => $user->display_name,
							'role'         => $role,
							'label'        => sprintf(
								'%s (%s)%s',
								$user->display_name,
								$user->user_login,
								$role ? ' - ' . $role : ''
							),
						]
					]
				] );
			} else {
				wp_send_json_success( [ 'users' => [] ] );
			}
			return;
		}

		// Otherwise, search by text
		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( [ 'users' => [] ] );
		}

		// Search for activated users (empty user_activation_key) by login, display name, or email
		$like  = '%' . $wpdb->esc_like( $search ) . '%';
		$users = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, user_login, display_name, user_email
				FROM {$wpdb->users}
				WHERE user_activation_key = ''
				AND (user_login LIKE %s OR display_name LIKE %s OR user_email LIKE %s)
				ORDER BY display_name ASC
				LIMIT 50",
				$like,
				$like,
				$like
			)
		);

		// Batch fetch user roles using single query
		$user_ids   = wp_list_pluck( $users, 'ID' );
		$user_roles = [];
		if ( ! empty( $user_ids ) ) {
			$wp_users = get_users( [ 'include' => $user_ids, 'fields' => 'all_with_meta' ] );
			foreach ( $wp_users as $wp_user ) {
				$user_roles[ $wp_user->ID ] = ! empty( $wp_user->roles ) ? ucfirst( $wp_user->roles[0] ) : '';
			}
		}

		$results = [];
		foreach ( $users as $user ) {
			$role      = $user_roles[ $user->ID ] ?? '';
			$results[] = [
				'id'           => $user->ID,
				'user_login'   => $user->user_login,
				'display_name' => $user->display_name,
				'role'         => $role,
				'label'        => sprintf(
					'%s (%s)%s',
					$user->display_name,
					$user->user_login,
					$role ? ' - ' . $role : ''
				),
			];
		}

		wp_send_json_success( [ 'users' => $results ] );
	}

	/**
	 * AJAX handler for getting analytics data
	 *
	 * Fetches AI usage analytics from backend API with local caching
	 *
	 * @return void Sends JSON response
	 */
	public function ajax_get_analytics() {
		// Verify nonce
		check_ajax_referer( 'wpforo_ai_analytics_nonce', 'nonce' );

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Insufficient permissions', false )
			], 403 );
		}

		// Get parameters
		$board_id   = isset( $_POST['board_id'] ) ? intval( $_POST['board_id'] ) : 0;
		$start_time = isset( $_POST['start_time'] ) ? intval( $_POST['start_time'] ) : strtotime( '-7 days' );
		$end_time   = isset( $_POST['end_time'] ) ? intval( $_POST['end_time'] ) : time();

		// Check cache first
		$cache_key = 'analytics_usage_' . md5( $board_id . ':' . $start_time . ':' . $end_time );
		$cached_data = $this->get_analytics_cache( $cache_key );

		if ( $cached_data !== false ) {
			wp_send_json_success( $cached_data );
			return;
		}

		// Fetch from backend API
		$analytics_data = $this->fetch_analytics_from_api( $start_time, $end_time, $board_id );

		if ( is_wp_error( $analytics_data ) ) {
			wp_send_json_error( [
				'message' => $analytics_data->get_error_message()
			] );
			return;
		}

		// Cache the result for 1 hour
		$this->set_analytics_cache( $cache_key, $analytics_data, 3600 );

		wp_send_json_success( $analytics_data );
	}

	/**
	 * Fetch analytics data from backend API
	 *
	 * @param int $start_time Start timestamp
	 * @param int $end_time End timestamp
	 * @param int $board_id Board ID for filtering (0 for all boards)
	 * @return array|WP_Error Analytics data or error
	 */
	private function fetch_analytics_from_api( $start_time, $end_time, $board_id = 0 ) {
		// Build request data
		$data = [
			'start_time'  => $start_time,
			'end_time'    => $end_time,
			'granularity' => $this->determine_granularity( $start_time, $end_time ),
			'group_by'    => 'request_type',
		];

		// Add board_id filter if specified (non-zero)
		if ( $board_id > 0 ) {
			$data['board_id'] = $board_id;
		}

		// Make API request (longer timeout for large date ranges scanning CloudWatch logs)
		$response = $this->make_request( 'POST', '/logs/analytics', $data, [], 45 );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Process and structure the response
		return $this->process_analytics_response( $response, $start_time, $end_time );
	}

	/**
	 * Process analytics API response into structured format
	 *
	 * @param array $response Raw API response
	 * @param int $start_time Start timestamp
	 * @param int $end_time End timestamp
	 * @return array Processed analytics data
	 */
	private function process_analytics_response( $response, $start_time, $end_time ) {
		$data = wpfval( $response, 'data' ) ?: $response;

		// Calculate days in range for average
		$days_in_range = max( 1, ceil( ( $end_time - $start_time ) / DAY_IN_SECONDS ) );

		// Time series data
		$time_series = wpfval( $data, 'time_series' ) ?: [];

		// Feature breakdown
		$by_feature = wpfval( $data, 'by_feature' ) ?: [];

		// Moderation stats
		$moderation = wpfval( $data, 'moderation' ) ?: [
			'spam_blocked'      => 0,
			'toxic_detected'    => 0,
			'policy_violations' => 0,
			'clean_passed'      => 0,
		];

		// Summary calculations - prefer by_feature, fallback to time_series
		$total_credits = 0;
		$total_requests = 0;
		$success_count = 0;

		if ( ! empty( $by_feature ) ) {
			// Calculate from feature breakdown (more accurate)
			foreach ( $by_feature as $feature => $stats ) {
				$total_credits  += (float) wpfval( $stats, 'credits' ) ?: 0;
				$total_requests += (int) wpfval( $stats, 'requests' ) ?: 0;
				$success_count  += (int) wpfval( $stats, 'success_count' ) ?: wpfval( $stats, 'requests' ) ?: 0;
			}
		} else {
			// Fallback: calculate from time series data
			foreach ( $time_series as $point ) {
				$total_credits  += (float) wpfval( $point, 'credits' ) ?: 0;
				$total_requests += (int) wpfval( $point, 'requests' ) ?: 0;
			}
			$success_count = $total_requests; // Assume all successful when no feature breakdown
		}

		$success_rate = $total_requests > 0 ? ( $success_count / $total_requests ) * 100 : 100;

		return [
			'time_series' => $time_series,
			'by_feature'  => $by_feature,
			'moderation'  => $moderation,
			'summary'     => [
				'total_credits'      => round( $total_credits, 2 ),
				'total_requests'     => $total_requests,
				'success_rate'       => round( $success_rate, 1 ),
				'avg_credits_per_day' => round( $total_credits / $days_in_range, 2 ),
			],
		];
	}

	/**
	 * Determine granularity based on time range
	 *
	 * @param int $start_time Start timestamp
	 * @param int $end_time End timestamp
	 * @return string Granularity (daily, weekly, monthly)
	 */
	private function determine_granularity( $start_time, $end_time ) {
		$days = ( $end_time - $start_time ) / DAY_IN_SECONDS;

		if ( $days <= 31 ) {
			return 'daily';
		} elseif ( $days <= 180 ) {
			return 'weekly';
		} else {
			return 'monthly';
		}
	}

	/**
	 * Get cached analytics data
	 *
	 * @param string $cache_key Cache key
	 * @return mixed Cached data or false if not found/expired
	 */
	private function get_analytics_cache( $cache_key ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpforo_ai_cache';

		// Suppress errors and return false on any database issue
		// This prevents cache table issues from breaking analytics
		$wpdb->suppress_errors( true );
		$result = $wpdb->get_row( $wpdb->prepare(
			"SELECT response, expires_at FROM {$table}
			 WHERE cache_key = %s AND type = 'analytics' AND expires_at > %d",
			$cache_key,
			time()
		) );
		$wpdb->suppress_errors( false );

		// Check for database errors (table doesn't exist, column issues, etc.)
		if ( $wpdb->last_error ) {
			return false;
		}

		if ( $result && ! empty( $result->response ) ) {
			$data = json_decode( $result->response, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				return $data;
			}
		}

		return false;
	}

	/**
	 * Set analytics cache
	 *
	 * @param string $cache_key Cache key
	 * @param array $data Data to cache
	 * @param int $ttl Time to live in seconds
	 * @return bool Success
	 */
	private function set_analytics_cache( $cache_key, $data, $ttl = 3600 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpforo_ai_cache';

		$expires_at = time() + $ttl;
		$cache_value = wp_json_encode( $data );

		// Suppress errors - caching failure shouldn't break analytics
		// This handles cases where the table doesn't exist or has schema issues
		$wpdb->suppress_errors( true );
		$result = $wpdb->replace( $table, [
			'cache_key'   => $cache_key,
			'type'        => 'analytics',
			'response'    => $cache_value,
			'expires_at'  => $expires_at,
			'postid'      => 0,
		], [ '%s', '%s', '%s', '%d', '%d' ] );
		$wpdb->suppress_errors( false );

		return $result !== false && ! $wpdb->last_error;
	}

	/**
	 * AJAX handler for running AI insights analysis
	 *
	 * Sends forum content to AI for analysis (sentiment, trending, recommendations)
	 * Uses credits and returns results with HTML rendering.
	 *
	 * @return void Sends JSON response
	 */
	public function ajax_run_insight() {
		// Track start time for logging
		$start_time = microtime( true );

		// Load analytics functions (needed for caching and rendering)
		require_once WPFORO_DIR . '/admin/pages/tabs/ai-features-tab-analytics.php';
		// Verify nonce
		check_ajax_referer( 'wpforo_ai_insights_nonce', 'nonce' );

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Insufficient permissions', false )
			], 403 );
		}

		// Get parameters
		$insight_type = isset( $_POST['insight_type'] ) ? sanitize_key( $_POST['insight_type'] ) : '';
		$board_id     = isset( $_POST['board_id'] ) ? intval( $_POST['board_id'] ) : 0;

		// Validate insight type
		$valid_types = [ 'sentiment', 'trending', 'recommendations', 'deep_analysis', 'sentiment_trend' ];
		if ( ! in_array( $insight_type, $valid_types, true ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Invalid insight type', false )
			] );
			return;
		}

		// Get credit costs
		$credit_costs = [
			'sentiment'       => 2,
			'trending'        => 1,
			'recommendations' => 1,
			'deep_analysis'   => 5,
			'sentiment_trend' => 4,
		];
		$credit_cost = $credit_costs[ $insight_type ];

		// Insight types with daily limits
		$daily_limit_types = [ 'recommendations' ];

		// Check daily limit for restricted insight types
		if ( in_array( $insight_type, $daily_limit_types, true ) ) {
			$cached_insights = wpforo_ai_get_cached_insights( $board_id );
			if ( isset( $cached_insights[ $insight_type ] ) && ! empty( $cached_insights[ $insight_type ]['timestamp'] ) ) {
				$cached_date = date( 'Y-m-d', $cached_insights[ $insight_type ]['timestamp'] );
				$today_date  = date( 'Y-m-d', current_time( 'timestamp' ) );
				if ( $cached_date === $today_date ) {
					wp_send_json_error( [
						'message' => wpforo_phrase( 'This analysis is limited to once per day. Please try again tomorrow.', false )
					] );
					return;
				}
			}
		}

		// Check if tenant has enough credits
		$status = $this->get_tenant_status();
		$credits_remaining = 0;
		if ( ! is_wp_error( $status ) && isset( $status['subscription']['credits_remaining'] ) ) {
			$credits_remaining = (int) $status['subscription']['credits_remaining'];
		}

		if ( $credits_remaining < $credit_cost ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Insufficient credits for this analysis', false )
			] );
			return;
		}

		// Switch to the board if needed
		if ( $board_id > 0 ) {
			WPF()->change_board( $board_id );
		}

		// Gather forum data for analysis
		$content_sample = $this->gather_insight_content( $insight_type );

		if ( empty( $content_sample ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Not enough forum content for analysis', false )
			] );
			return;
		}

		// Send to backend for AI analysis
		$result = $this->run_ai_insight( $insight_type, $content_sample );

		if ( is_wp_error( $result ) ) {
			// Log the error
			$duration_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );
			WPF()->ai_logs->log( [
				'action_type'      => AILogs::ACTION_ANALYTICS_INSIGHTS,
				'credits_used'     => 0,
				'status'           => AILogs::STATUS_ERROR,
				'request_summary'  => 'Insight type: ' . $insight_type,
				'error_message'    => $result->get_error_message(),
				'duration_ms'      => $duration_ms,
				'user_type'        => 'admin',
			] );

			wp_send_json_error( [
				'message' => $result->get_error_message()
			] );
			return;
		}

		// For deep_analysis, merge database metrics with LLM results
		if ( $insight_type === 'deep_analysis' ) {
			$db_metrics = $this->get_deep_analysis_db_metrics();
			$result = array_merge( $db_metrics, $result );
		}

		// Cache the result
		wpforo_ai_cache_insight( $board_id, $insight_type, $result );

		// Log successful insight generation
		$duration_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );
		WPF()->ai_logs->log( [
			'action_type'      => AILogs::ACTION_ANALYTICS_INSIGHTS,
			'credits_used'     => $credit_cost,
			'status'           => AILogs::STATUS_SUCCESS,
			'request_summary'  => 'Insight type: ' . $insight_type,
			'response_summary' => 'Generated ' . $insight_type . ' analysis successfully',
			'duration_ms'      => $duration_ms,
			'user_type'        => 'admin',
		] );

		// Get updated credits (clear cache first to force refresh)
		$this->clear_status_cache();
		$new_status = $this->get_tenant_status();
		$new_credits = 0;
		if ( ! is_wp_error( $new_status ) && isset( $new_status['subscription']['credits_remaining'] ) ) {
			$new_credits = (int) $new_status['subscription']['credits_remaining'];
		}

		// Render HTML for the results
		ob_start();
		wpforo_ai_render_insight_results( $insight_type, $result );
		$html = ob_get_clean();

		wp_send_json_success( [
			'html'              => $html,
			'data'              => $result,
			'credits_remaining' => $new_credits,
		] );
	}

	/**
	 * Gather forum content for AI insight analysis
	 *
	 * @param string $insight_type Type of insight
	 * @return array Content sample for analysis
	 */
	private function gather_insight_content( $insight_type ) {
		$content = [];

		switch ( $insight_type ) {
			case 'sentiment':
				// Get recent posts for sentiment analysis
				$posts = WPF()->db->get_results(
					"SELECT p.body, p.created, t.title as topic_title
					 FROM " . WPF()->tables->posts . " p
					 LEFT JOIN " . WPF()->tables->topics . " t ON p.topicid = t.topicid
					 WHERE p.status = 0
					 ORDER BY p.created DESC
					 LIMIT 200",
					ARRAY_A
				);
				foreach ( $posts as $post ) {
					$content[] = [
						'text'  => wp_strip_all_tags( $post['body'] ),
						'topic' => $post['topic_title'],
					];
				}
				break;

			case 'trending':
				// Get recent topics with activity metrics using JOIN instead of correlated subquery
				$seven_days_ago = time() - ( 7 * DAY_IN_SECONDS );
				$topics = WPF()->db->get_results(
					WPF()->db->prepare(
						"SELECT t.title, t.posts, t.views, t.created, COALESCE(rp.recent_posts, 0) as recent_posts
						 FROM " . WPF()->tables->topics . " t
						 LEFT JOIN (
						     SELECT topicid, COUNT(*) as recent_posts
						     FROM " . WPF()->tables->posts . "
						     WHERE created > %d
						     GROUP BY topicid
						 ) rp ON t.topicid = rp.topicid
						 WHERE t.status = 0
						 AND t.created > UNIX_TIMESTAMP(NOW() - INTERVAL 30 DAY)
						 ORDER BY t.created DESC
						 LIMIT 100",
						$seven_days_ago
					),
					ARRAY_A
				);
				foreach ( $topics as $topic ) {
					$content[] = [
						'title'        => $topic['title'],
						'posts'        => (int) $topic['posts'],
						'views'        => (int) $topic['views'],
						'recent_posts' => (int) $topic['recent_posts'],
					];
				}
				break;

			case 'recommendations':
				// Get forum statistics for recommendations
				$stats = [];

				// Total topics and posts
				$stats['total_topics'] = (int) WPF()->db->get_var(
					"SELECT COUNT(*) FROM " . WPF()->tables->topics . " WHERE status = 0"
				);
				$stats['total_posts'] = (int) WPF()->db->get_var(
					"SELECT COUNT(*) FROM " . WPF()->tables->posts . " WHERE status = 0"
				);

				// Unanswered topics
				$stats['unanswered_topics'] = (int) WPF()->db->get_var(
					"SELECT COUNT(*) FROM " . WPF()->tables->topics . " WHERE status = 0 AND posts = 1"
				);

				// Active users this week
				$stats['active_users_week'] = (int) WPF()->db->get_var(
					"SELECT COUNT(DISTINCT userid) FROM " . WPF()->tables->posts . "
					 WHERE created > UNIX_TIMESTAMP(NOW() - INTERVAL 7 DAY)"
				);

				// Average response time (first reply)
				$stats['avg_response_hours'] = WPF()->db->get_var(
					"SELECT AVG(TIMESTAMPDIFF(HOUR, FROM_UNIXTIME(t.created), FROM_UNIXTIME(
						(SELECT MIN(p.created) FROM " . WPF()->tables->posts . " p
						 WHERE p.topicid = t.topicid AND p.is_first_post = 0)
					)))
					FROM " . WPF()->tables->topics . " t
					WHERE t.posts > 1 AND t.created > UNIX_TIMESTAMP(NOW() - INTERVAL 30 DAY)"
				);

				// Recent topic titles for context
				$recent_topics = WPF()->db->get_col(
					"SELECT title FROM " . WPF()->tables->topics . "
					 WHERE status = 0
					 ORDER BY created DESC LIMIT 50"
				);

				$content = [
					'stats'         => $stats,
					'recent_topics' => $recent_topics,
				];
				break;

			case 'deep_analysis':
				// Get comprehensive forum data for deep analysis
				$data = [];

				// Get usergroup IDs that have 'aum' (admin user management) permission
				// These are admins/moderators who should be excluded from contributor analysis
				$admin_groupids = [];
				$all_groups     = WPF()->db->get_results(
					"SELECT groupid, cans FROM " . WPF()->tables->usergroups,
					ARRAY_A
				);
				foreach ( $all_groups as $group ) {
					$cans = maybe_unserialize( $group['cans'] );
					if ( is_array( $cans ) && ! empty( $cans['aum'] ) ) {
						$admin_groupids[] = (int) $group['groupid'];
					}
				}
				// Fallback to default admin/mod groups if none found
				if ( empty( $admin_groupids ) ) {
					$admin_groupids = [ 1, 2 ];
				}
				$admin_groupids_str = implode( ',', $admin_groupids );

				// User engagement data - exclude users with admin permissions (aum capability)
				$data['user_stats'] = WPF()->db->get_results(
					"SELECT p.userid, COUNT(*) as post_count
					 FROM " . WPF()->tables->posts . " p
					 INNER JOIN " . WPF()->tables->profiles . " pr ON p.userid = pr.userid
					 WHERE p.status = 0
					 AND p.created > UNIX_TIMESTAMP(NOW() - INTERVAL 30 DAY)
					 AND pr.groupid NOT IN ({$admin_groupids_str})
					 GROUP BY p.userid
					 ORDER BY post_count DESC
					 LIMIT 20",
					ARRAY_A
				);

				// Topic and post length metrics
				$data['content_metrics'] = WPF()->db->get_row(
					"SELECT
						AVG(LENGTH(p.body)) as avg_post_length,
						AVG(CASE WHEN p.is_first_post = 1 THEN LENGTH(p.body) END) as avg_topic_length
					 FROM " . WPF()->tables->posts . " p
					 WHERE p.status = 0 AND p.created > UNIX_TIMESTAMP(NOW() - INTERVAL 30 DAY)",
					ARRAY_A
				);

				// Recent posts with content for keyword/sentiment analysis
				// Exclude users with admin permissions (aum capability)
				$posts = WPF()->db->get_results(
					"SELECT p.body, p.created, p.userid, t.title as topic_title
					 FROM " . WPF()->tables->posts . " p
					 LEFT JOIN " . WPF()->tables->topics . " t ON p.topicid = t.topicid
					 INNER JOIN " . WPF()->tables->profiles . " pr ON p.userid = pr.userid
					 WHERE p.status = 0
					 AND pr.groupid NOT IN ({$admin_groupids_str})
					 ORDER BY p.created DESC
					 LIMIT 150",
					ARRAY_A
				);

				// Batch fetch all user display names for user_stats and posts
				$all_user_ids = array_merge(
					array_column( $data['user_stats'], 'userid' ),
					array_column( $posts, 'userid' )
				);
				$user_names = $this->batch_get_user_display_names( $all_user_ids );
				$guest_label = wpforo_phrase( 'Guest', false );

				// Get usernames for top posters
				foreach ( $data['user_stats'] as &$user ) {
					$user['username'] = $user_names[ $user['userid'] ] ?? $guest_label;
				}

				// Build posts array with usernames
				$data['posts'] = [];
				foreach ( $posts as $post ) {
					$timestamp = is_numeric( $post['created'] ) ? $post['created'] : strtotime( $post['created'] );
					$data['posts'][] = [
						'text'     => wp_strip_all_tags( $post['body'] ),
						'topic'    => $post['topic_title'],
						'username' => $user_names[ $post['userid'] ] ?? $guest_label,
						'date'     => date( 'Y-m-d H:i', $timestamp ),
					];
				}

				// Reply frequency data
				$data['reply_stats'] = WPF()->db->get_row(
					"SELECT
						COUNT(*) as total_posts,
						SUM(CASE WHEN is_first_post = 0 THEN 1 ELSE 0 END) as total_replies,
						COUNT(DISTINCT userid) as unique_users
					 FROM " . WPF()->tables->posts . "
					 WHERE status = 0 AND created > UNIX_TIMESTAMP(NOW() - INTERVAL 30 DAY)",
					ARRAY_A
				);

				$content = $data;
				break;

			case 'sentiment_trend':
				// Get posts with timestamps for trend analysis
				$posts = WPF()->db->get_results(
					"SELECT p.body, p.created, t.title as topic_title, u.display_name
					 FROM " . WPF()->tables->posts . " p
					 LEFT JOIN " . WPF()->tables->topics . " t ON p.topicid = t.topicid
					 LEFT JOIN " . WPF()->db->users . " u ON p.userid = u.ID
					 WHERE p.status = 0 AND p.created > DATE_SUB(NOW(), INTERVAL 30 DAY)
					 ORDER BY p.created ASC
					 LIMIT 300",
					ARRAY_A
				);

				foreach ( $posts as $post ) {
					$timestamp = is_numeric( $post['created'] ) ? $post['created'] : strtotime( $post['created'] );
					$content[] = [
						'text'      => wp_strip_all_tags( $post['body'] ),
						'topic'     => $post['topic_title'],
						'timestamp' => date( 'Y-m-d', $timestamp ),
						'author'    => $post['display_name'] ?: 'Guest',
					];
				}
				break;
		}

		return $content;
	}

	/**
	 * Calculate deep analysis metrics from database
	 *
	 * These are factual metrics that should be computed from the database,
	 * not generated by the LLM.
	 *
	 * @return array Database-calculated metrics
	 */
	private function get_deep_analysis_db_metrics() {
		$metrics = [];

		// Get total users and active users
		$total_members = (int) WPF()->db->get_var(
			"SELECT COUNT(*) FROM " . WPF()->tables->members
		);

		$active_users = (int) WPF()->db->get_var(
			"SELECT COUNT(DISTINCT userid) FROM " . WPF()->tables->posts . "
			 WHERE status = 0 AND created > DATE_SUB(NOW(), INTERVAL 30 DAY)"
		);

		// Get reply stats
		$reply_stats = WPF()->db->get_row(
			"SELECT
				COUNT(*) as total_posts,
				SUM(CASE WHEN is_first_post = 0 THEN 1 ELSE 0 END) as total_replies,
				COUNT(DISTINCT userid) as unique_users
			 FROM " . WPF()->tables->posts . "
			 WHERE status = 0 AND created > DATE_SUB(NOW(), INTERVAL 30 DAY)",
			ARRAY_A
		);

		$avg_replies_per_user = 0;
		if ( $reply_stats && $reply_stats['unique_users'] > 0 ) {
			$avg_replies_per_user = round( (int) $reply_stats['total_replies'] / (int) $reply_stats['unique_users'], 1 );
		}

		$active_users_percent = 0;
		if ( $total_members > 0 ) {
			$active_users_percent = round( ( $active_users / $total_members ) * 100, 1 );
		}

		// Get average response time (hours between topic creation and first reply)
		$avg_response_hours = WPF()->db->get_var(
			"SELECT AVG(response_time) FROM (
				SELECT TIMESTAMPDIFF(HOUR, t.created,
					(SELECT MIN(p.created) FROM " . WPF()->tables->posts . " p
					 WHERE p.topicid = t.topicid AND p.is_first_post = 0)
				) as response_time
				FROM " . WPF()->tables->topics . " t
				WHERE t.posts > 1 AND t.created > DATE_SUB(NOW(), INTERVAL 30 DAY)
			) as response_times WHERE response_time IS NOT NULL"
		);

		// Get top repliers with usernames
		$top_repliers_raw = WPF()->db->get_results(
			"SELECT userid, COUNT(*) as reply_count
			 FROM " . WPF()->tables->posts . "
			 WHERE status = 0 AND is_first_post = 0 AND created > DATE_SUB(NOW(), INTERVAL 30 DAY)
			 GROUP BY userid
			 ORDER BY reply_count DESC
			 LIMIT 5",
			ARRAY_A
		);

		// Batch fetch user display names with proper fallback chain
		$replier_user_ids = array_column( $top_repliers_raw, 'userid' );
		$replier_names    = $this->batch_get_user_display_names( $replier_user_ids );
		$guest_label      = wpforo_phrase( 'Guest', false );

		$top_repliers = [];
		foreach ( $top_repliers_raw as $replier ) {
			$top_repliers[] = [
				'username'    => $replier_names[ $replier['userid'] ] ?? $guest_label,
				'reply_count' => (int) $replier['reply_count'],
				'sentiment'   => 'neutral', // Will be filled by LLM if available
			];
		}

		// Get content metrics
		$content_stats = WPF()->db->get_row(
			"SELECT
				AVG(LENGTH(body) / 5) as avg_reply_words,
				AVG(CASE WHEN is_first_post = 1 THEN LENGTH(body) / 5 END) as avg_topic_words
			 FROM " . WPF()->tables->posts . "
			 WHERE status = 0 AND created > DATE_SUB(NOW(), INTERVAL 30 DAY)",
			ARRAY_A
		);

		// Build user_engagement data
		$metrics['user_engagement'] = [
			'avg_replies_per_user'     => $avg_replies_per_user,
			'active_users_percent'     => $active_users_percent,
			'lurker_percent'           => max( 0, 100 - $active_users_percent ),
			'avg_response_time_hours'  => round( floatval( $avg_response_hours ) ?: 0, 1 ),
			'top_repliers'             => $top_repliers,
			'summary'                  => '', // Will be filled by LLM
		];

		// Build content_metrics data
		$avg_topic_words = round( floatval( $content_stats['avg_topic_words'] ?? 0 ) );
		$avg_reply_words = round( floatval( $content_stats['avg_reply_words'] ?? 0 ) );

		$detailed_percent = 0;
		if ( $avg_reply_words > 0 ) {
			// Consider replies > 100 words as "detailed"
			$detailed_count = (int) WPF()->db->get_var(
				"SELECT COUNT(*) FROM " . WPF()->tables->posts . "
				 WHERE status = 0 AND is_first_post = 0 AND LENGTH(body) / 5 > 100
				 AND created > DATE_SUB(NOW(), INTERVAL 30 DAY)"
			);
			$total_replies = (int) ( $reply_stats['total_replies'] ?? 1 );
			$detailed_percent = $total_replies > 0 ? round( ( $detailed_count / $total_replies ) * 100 ) : 0;
		}

		$metrics['content_metrics'] = [
			'avg_topic_length_words'       => $avg_topic_words,
			'avg_reply_length_words'       => $avg_reply_words,
			'detailed_discussions_percent' => $detailed_percent,
			'quick_exchanges_percent'      => max( 0, 100 - $detailed_percent ),
			'summary'                      => '', // Will be filled by LLM
		];

		// Get activity patterns from database
		$peak_hours_raw = WPF()->db->get_results(
			"SELECT HOUR(created) as hour, COUNT(*) as cnt
			 FROM " . WPF()->tables->posts . "
			 WHERE status = 0 AND created > DATE_SUB(NOW(), INTERVAL 30 DAY)
			 GROUP BY HOUR(created)
			 ORDER BY cnt DESC
			 LIMIT 3",
			ARRAY_A
		);

		$peak_hours = [];
		foreach ( $peak_hours_raw as $h ) {
			$peak_hours[] = sprintf( '%02d:00', $h['hour'] );
		}

		$peak_days_raw = WPF()->db->get_results(
			"SELECT DAYNAME(created) as day_name, COUNT(*) as cnt
			 FROM " . WPF()->tables->posts . "
			 WHERE status = 0 AND created > DATE_SUB(NOW(), INTERVAL 30 DAY)
			 GROUP BY DAYNAME(created)
			 ORDER BY cnt DESC
			 LIMIT 3",
			ARRAY_A
		);

		$peak_days = [];
		foreach ( $peak_days_raw as $d ) {
			$peak_days[] = $d['day_name'];
		}

		// Determine trend by comparing last 15 days to previous 15 days
		$recent_count = (int) WPF()->db->get_var(
			"SELECT COUNT(*) FROM " . WPF()->tables->posts . "
			 WHERE status = 0 AND created > DATE_SUB(NOW(), INTERVAL 15 DAY)"
		);
		$previous_count = (int) WPF()->db->get_var(
			"SELECT COUNT(*) FROM " . WPF()->tables->posts . "
			 WHERE status = 0
			 AND created > DATE_SUB(NOW(), INTERVAL 30 DAY)
			 AND created <= DATE_SUB(NOW(), INTERVAL 15 DAY)"
		);

		$trend = 'stable';
		if ( $previous_count > 0 ) {
			$change = ( $recent_count - $previous_count ) / $previous_count;
			if ( $change > 0.1 ) {
				$trend = 'increasing';
			} elseif ( $change < -0.1 ) {
				$trend = 'decreasing';
			}
		}

		$metrics['activity_patterns'] = [
			'peak_hours' => $peak_hours,
			'peak_days'  => $peak_days,
			'trend'      => $trend,
			'summary'    => '', // Will be filled by LLM
		];

		return $metrics;
	}

	/**
	 * Run AI insight analysis via backend API
	 *
	 * @param string $insight_type Type of insight
	 * @param array  $content      Content to analyze
	 * @return array|WP_Error Analysis result or error
	 */
	private function run_ai_insight( $insight_type, $content ) {
		$data = [
			'insight_type' => $insight_type,
			'content'      => $content,
		];

		$response = $this->post( '/analytics/insights', $data );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Extract result from response
		$result = wpfval( $response, 'data' ) ?: $response;

		// Ensure expected structure based on type
		switch ( $insight_type ) {
			case 'sentiment':
				// 7 emotion categories
				$result = [
					'happy'      => (int) wpfval( $result, 'happy' ) ?: 0,
					'excited'    => (int) wpfval( $result, 'excited' ) ?: 0,
					'neutral'    => (int) wpfval( $result, 'neutral' ) ?: 0,
					'confused'   => (int) wpfval( $result, 'confused' ) ?: 0,
					'frustrated' => (int) wpfval( $result, 'frustrated' ) ?: 0,
					'angry'      => (int) wpfval( $result, 'angry' ) ?: 0,
					'sad'        => (int) wpfval( $result, 'sad' ) ?: 0,
					'summary'    => wpfval( $result, 'summary' ) ?: '',
				];
				break;

			case 'trending':
				$result = [
					'topics'  => wpfval( $result, 'topics' ) ?: [],
					'summary' => wpfval( $result, 'summary' ) ?: '',
				];
				break;

			case 'recommendations':
				$result = [
					'recommendations' => wpfval( $result, 'recommendations' ) ?: [],
					'summary'         => wpfval( $result, 'summary' ) ?: '',
				];
				break;
		}

		return $result;
	}

	/**
	 * Get forum IDs the current user can view (for search filtering)
	 *
	 * Uses cached WPF()->current_user_accesses (board-specific).
	 * Returns null if user can access all forums (no filtering needed).
	 *
	 * @return array|null Array of accessible forum IDs, or null for full access
	 */
	public function get_accessible_forumids() {
		// Admins see everything
		if ( current_user_can( 'administrator' ) ) {
			return null;
		}

		// Get all forums for current board (cached by usergroup)
		$all_forums = WPF()->forum->get_forums( [ 'type' => 'forum' ] );
		if ( empty( $all_forums ) ) {
			return null;
		}

		$accessible   = [];
		$total_forums = 0;

		foreach ( $all_forums as $forum ) {
			if ( empty( $forum['is_cat'] ) ) { // Skip categories
				$total_forums++;
				// 'vf' = can view forum
				if ( WPF()->perm->forum_can( 'vf', $forum['forumid'] ) ) {
					$accessible[] = (int) $forum['forumid'];
				}
			}
		}

		// If user can access all forums, return null (no filtering needed)
		if ( count( $accessible ) === $total_forums ) {
			return null;
		}

		return $accessible;
	}

	/**
	 * Perform semantic search query
	 *
	 * @param string $query Search query text
	 * @param int $limit Maximum number of results to return
	 * @param array $filters Optional filters
	 * @return array|WP_Error Search results or error object
	 */
	public function semantic_search( $query, $limit = 10, $filters = [] ) {
		if ( empty( $query ) ) {
			return new \WP_Error( 'empty_query', wpforo_phrase( 'Search query cannot be empty', false ) );
		}

		// Get tenant ID from stored status
		$status = $this->get_tenant_status();
		if ( is_wp_error( $status ) ) {
			return $status;
		}

		$tenant_id = wpfval( $status, 'tenant_id' );
		if ( empty( $tenant_id ) ) {
			return new \WP_Error( 'no_tenant_id', wpforo_phrase( 'Tenant ID not found', false ) );
		}

		// Automatically add current board_id to filters (multi-board support)
		// Note: board_id is stored as string in vector metadata, so we send it as string
		$current_boardid = (string) WPF()->board->get_current( 'boardid' );
		if ( ! isset( $filters['board_id'] ) ) {
			$filters['board_id'] = $current_boardid;
		}

		// Add forum access filtering (only forums current user can view)
		// Skip if already set by VectorStorageManager (avoids double-add)
		// Returns null for admins or users with full access (no filtering needed)
		if ( ! isset( $filters['accessible_forumids'] ) ) {
			$accessible_forumids = $this->get_accessible_forumids();
			if ( $accessible_forumids !== null ) {
				$filters['accessible_forumids'] = $accessible_forumids;
			}
		}

		$data = [
			'tenant_id' => $tenant_id,
			'query' => sanitize_text_field( $query ),
			'limit' => min( (int) $limit, 100 ), // Cap at 100 results
		];

		if ( ! empty( $filters ) ) {
			$data['filters'] = $filters;
		}

		// Add quality parameter from settings (for re-ranking model selection)
		$search_quality = wpfval( WPF()->settings->ai, 'search_quality' );
		if ( ! empty( $search_quality ) ) {
			$data['quality'] = sanitize_text_field( $search_quality );
		}

		// Add minimum score threshold from settings (server-side filtering)
		$min_score_setting = (int) wpfval( WPF()->settings->ai, 'search_min_score' );
		if ( $min_score_setting > 0 ) {
			$data['min_score'] = $min_score_setting / 100; // Convert percentage to 0-1
		}

		$response = $this->post( '/search/semantic', $data );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'semantic_search_failed', $response->get_error_message() );
			return $response;
		}

		$this->log_info( 'semantic_search_completed', [
			'query' => $query,
			'results_count' => wpfval( $response, 'total' ) ?: 0
		] );

		return $response;
	}

	/**
	 * Generate embedding vector for content
	 *
	 * Used for local storage mode - generates embeddings via cloud API
	 * but stores them locally in WordPress database.
	 *
	 * Supports multimodal image indexing (Professional+ plans):
	 * - Pass images array with URLs from site domain
	 * - Images are processed by vision models
	 * - Returns processed_content with image descriptions appended
	 *
	 * Supports document indexing (Professional+ plans):
	 * - Pass documents array with URLs from site domain
	 * - Documents are processed (text extraction, OCR, embedded images)
	 * - Returns processed_content with document text appended
	 *
	 * @param string $content       Content text to embed
	 * @param array  $images        Optional. Array of image data: [['url' => '...', 'attach_id' => 123], ...]
	 * @param string $topic_context Optional. Topic title for better image/document descriptions
	 * @param array  $documents     Optional. Array of document data: [['url' => '...', 'attach_id' => 123], ...]
	 * @return array|WP_Error Array with 'embedding' key or error object. Also includes
	 *                        'processed_content' if images/documents were processed.
	 */
	public function generate_embedding( $content, $images = [], $topic_context = '', $documents = [] ) {
		if ( empty( $content ) ) {
			return new \WP_Error( 'empty_content', wpforo_phrase( 'Content cannot be empty', false ) );
		}

		// Get tenant ID from stored status
		$status = $this->get_tenant_status();
		if ( is_wp_error( $status ) ) {
			return $status;
		}

		$tenant_id = wpfval( $status, 'tenant_id' );
		if ( empty( $tenant_id ) ) {
			return new \WP_Error( 'no_tenant_id', wpforo_phrase( 'Tenant ID not found', false ) );
		}

		$data = [
			'tenant_id' => $tenant_id,
			'content'   => $content,
		];

		// Add image processing parameters if images provided
		if ( ! empty( $images ) && is_array( $images ) ) {
			// Get site domain (without protocol)
			$site_url = get_site_url();
			$parsed   = wp_parse_url( $site_url );
			$domain   = $parsed['host'] ?? '';

			$data['images']      = $images;
			$data['site_domain'] = $domain;

			if ( ! empty( $topic_context ) ) {
				$data['topic_context'] = $topic_context;
			}
		}

		// Add document processing parameters if documents provided
		if ( ! empty( $documents ) && is_array( $documents ) ) {
			if ( empty( $data['site_domain'] ) ) {
				$site_url = get_site_url();
				$parsed   = wp_parse_url( $site_url );
				$data['site_domain'] = $parsed['host'] ?? '';
			}
			$data['documents'] = $documents;
			if ( ! empty( $topic_context ) && empty( $data['topic_context'] ) ) {
				$data['topic_context'] = $topic_context;
			}
		}

		$response = $this->post( '/search/embedding/generate', $data );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'embedding_generation_failed', $response->get_error_message() );
			return $response;
		}

		// Validate response
		if ( ! isset( $response['embedding'] ) || ! is_array( $response['embedding'] ) ) {
			return new \WP_Error( 'invalid_response', wpforo_phrase( 'Invalid embedding response from API', false ) );
		}

		$log_data = [
			'dimensions'   => count( $response['embedding'] ),
			'credits_used' => $response['credits_used'] ?? 1,
		];

		// Log image processing stats if present
		if ( ! empty( $response['image_processing'] ) ) {
			$log_data['images_processed'] = $response['image_processing']['images_processed'] ?? 0;
			$log_data['images_skipped']   = $response['image_processing']['images_skipped'] ?? 0;
		}

		// Log document processing stats if present
		if ( ! empty( $response['document_processing'] ) ) {
			$log_data['documents_processed'] = $response['document_processing']['documents_processed'] ?? 0;
			$log_data['total_pages']         = $response['document_processing']['total_pages'] ?? 0;
		}

		$this->log_info( 'embedding_generated', $log_data );

		return $response;
	}

	/**
	 * Generate embedding vectors for multiple content items in a single request
	 *
	 * Used for efficient local storage indexing - generates all embeddings
	 * in one API call, matching the cloud indexing pattern.
	 *
	 * @param array $items Array of items: [ ['id' => 'post_123', 'content' => '...'], ... ]
	 * @return array|WP_Error Response with 'results' array containing embeddings, or error
	 */
	public function generate_embeddings_batch( $items, $topic_count = null ) {
		if ( empty( $items ) || ! is_array( $items ) ) {
			return new \WP_Error( 'empty_items', wpforo_phrase( 'Items array cannot be empty', false ) );
		}

		// Get tenant ID from stored status
		$status = $this->get_tenant_status();
		if ( is_wp_error( $status ) ) {
			return $status;
		}

		$tenant_id = wpfval( $status, 'tenant_id' );
		if ( empty( $tenant_id ) ) {
			return new \WP_Error( 'no_tenant_id', wpforo_phrase( 'Tenant ID not found', false ) );
		}

		// Format items for API
		$api_items = [];
		foreach ( $items as $item ) {
			if ( ! empty( $item['id'] ) && ! empty( $item['content'] ) ) {
				$api_items[] = [
					'id'      => (string) $item['id'],
					'content' => $item['content'],
				];
			}
		}

		if ( empty( $api_items ) ) {
			return new \WP_Error( 'no_valid_items', wpforo_phrase( 'No valid items to process', false ) );
		}

		$data = [
			'tenant_id' => $tenant_id,
			'items'     => $api_items,
		];

		// Add topic_count for credit charging (charge per topic, not per post)
		// IMPORTANT: Always send topic_count when provided, including 0 for continuation chunks
		// If topic_count is not sent, API falls back to per-item charging (expensive!)
		if ( $topic_count !== null ) {
			$data['topic_count'] = (int) $topic_count;
		}

		\wpforo_ai_log( 'debug', sprintf(
			'generate_embeddings_batch: topic_count=%s, items=%d, data_keys=%s',
			$topic_count !== null ? (string) $topic_count : 'NULL',
			count( $api_items ),
			implode( ',', array_keys( $data ) )
		), 'Client' );

		// API Gateway has 29-second hard limit, so use 25 seconds to fail fast
		// With smaller batches (5 topics for local mode), this should be sufficient
		$response = $this->api_post( '/search/embedding/generate-batch', $data, 25 );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'batch_embedding_failed', $response->get_error_message() );
			return $response;
		}

		// Validate response
		if ( ! isset( $response['results'] ) || ! is_array( $response['results'] ) ) {
			return new \WP_Error( 'invalid_response', wpforo_phrase( 'Invalid batch embedding response from API', false ) );
		}

		$this->log_info( 'batch_embedding_completed', [
			'total_items'      => $response['total_items'] ?? count( $api_items ),
			'successful_items' => $response['successful_items'] ?? 0,
			'failed_items'     => $response['failed_items'] ?? 0,
			'credits_used'     => $response['credits_used'] ?? 0,
		] );

		return $response;
	}

	/**
	 * Enhance search results with AI-generated summary and recommendations
	 *
	 * Credits are consumed based on quality tier selected.
	 *
	 * @param string $query The original search query
	 * @param array $results Array of search results (title, excerpt, url, score)
	 * @param string $user_language Language for AI response (default: English)
	 * @return array|WP_Error Enhancement data or error object
	 */
	public function enhance_search_results( $query, $results, $user_language = 'English' ) {
		// Check if AI Summary & Recommendations is enabled
		// Handle various stored formats: true, "1", 1, "on" = enabled; false, "0", 0, "" = disabled
		$enhance_setting = wpfval( WPF()->settings->ai, 'search_enhance' );
		// Consider enabled if value is truthy and not explicitly "0" or 0
		$enhance_enabled = ! empty( $enhance_setting ) && $enhance_setting !== '0' && $enhance_setting !== 0 && $enhance_setting !== 'false';
		if ( ! $enhance_enabled ) {
			$this->log_info( 'search_enhance_disabled', [
				'setting_value' => $enhance_setting,
				'setting_type'  => gettype( $enhance_setting ),
			] );
			return [
				'success'         => false,
				'disabled'        => true,
				'summary'         => '',
				'quick_answer'    => '',
				'recommendations' => [],
			];
		}

		if ( empty( $query ) || empty( $results ) ) {
			return new \WP_Error( 'invalid_params', wpforo_phrase( 'Query and results are required', false ) );
		}

		// Get tenant ID from stored status
		$status = $this->get_tenant_status();
		if ( is_wp_error( $status ) ) {
			return $status;
		}

		$tenant_id = wpfval( $status, 'tenant_id' );
		if ( empty( $tenant_id ) ) {
			return new \WP_Error( 'no_tenant_id', wpforo_phrase( 'Tenant ID not found', false ) );
		}

		// Format results for the API (max 5 results)
		$formatted_results = [];
		$result_num = 1;
		foreach ( array_slice( $results, 0, 5 ) as $result ) {
			$formatted_results[] = [
				'result_number' => $result_num,
				'title'         => wpfval( $result, 'title' ) ?: '',
				'excerpt'       => wpfval( $result, 'content' ) ?: '',
				'url'           => wpfval( $result, 'url' ) ?: '',
				'score'         => ( wpfval( $result, 'score' ) ?: 0 ) / 100, // Convert from % back to 0-1
			];
			$result_num++;
		}

		$data = [
			'tenant_id'     => $tenant_id,
			'query'         => sanitize_text_field( $query ),
			'user_language' => sanitize_text_field( $user_language ),
			'results'       => $formatted_results,
		];

		// Add quality parameter from settings (for AI summary/recommendations model selection)
		$enhance_quality = wpfval( WPF()->settings->ai, 'search_enhance_quality' );
		if ( ! empty( $enhance_quality ) ) {
			$data['quality'] = sanitize_text_field( $enhance_quality );
		}

		$response = $this->post( '/search/enhance', $data );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'search_enhance_failed', $response->get_error_message() );
			return $response;
		}

		$this->log_info( 'search_enhance_completed', [
			'query'            => $query,
			'results_count'    => count( $formatted_results ),
			'processing_time'  => wpfval( $response, 'processing_time_ms' ) ?: 0,
		] );

		return $response;
	}

	/**
	 * Get user's language for AI responses
	 *
	 * Priority order:
	 * 1. Explicit language code parameter (from POST/request)
	 * 2. User preference (from user_meta if logged in)
	 * 3. Board AI settings (search_language)
	 * 4. Board locale
	 * 5. WordPress locale
	 * 6. Default (English)
	 *
	 * @param string|null $language_code Explicit language code (e.g., 'en_US', 'de_DE')
	 * @return string Language name (e.g., "English", "Spanish", "French")
	 */
	public function get_user_language( $language_code = null, $setting_key = 'search_language' ) {
		// Build language map from master list (2-letter code => English name)
		$language_map = [];
		foreach ( wpforo_get_ai_languages() as $lang ) {
			if ( ! isset( $language_map[ $lang['code'] ] ) ) {
				$language_map[ $lang['code'] ] = $lang['name'];
			}
		}

		$locale = null;

		// 1. Use explicit language code if provided
		if ( ! empty( $language_code ) ) {
			$locale = $language_code;
		}

		// 2. If no explicit code, try user preferences (logged in users only)
		if ( empty( $locale ) ) {
			$user_id = WPF()->current_userid;
			if ( $user_id > 0 ) {
				$saved_prefs = get_user_meta( $user_id, 'wpforo_ai_search', true );
				if ( is_array( $saved_prefs ) && ! empty( $saved_prefs['language'] ) ) {
					$locale = $saved_prefs['language'];
				}
			}
		}

		// 3. If still no locale, try board AI settings
		if ( empty( $locale ) ) {
			$locale = wpforo_setting( 'ai', $setting_key );
		}

		// 4. If still no locale, try board locale
		if ( empty( $locale ) ) {
			$locale = wpfval( WPF()->board, 'locale' );
		}

		// 5. If still no locale, use WordPress locale
		if ( empty( $locale ) ) {
			$locale = get_locale();
		}

		// Extract 2-letter language code from locale (e.g., 'en_US' -> 'en')
		$lang_code = substr( $locale, 0, 2 );

		if ( isset( $language_map[ $lang_code ] ) ) {
			return $language_map[ $lang_code ];
		}

		// Default to English
		return 'English';
	}

	/**
	 * Replace AI link markers with actual HTML links
	 *
	 * Converts [[#N]] and [[#N:Title]] markers to clickable links
	 *
	 * @param string $text Text containing link markers
	 * @param array $url_map Map of result numbers to URLs (1-indexed)
	 * @return string Text with markers replaced by HTML links
	 */
	private function replace_ai_link_markers( $text, $url_map ) {
		if ( empty( $text ) ) {
			return '';
		}

		// Replace [[#N:Title]] format - title with link
		$text = preg_replace_callback(
			'/\[\[#(\d+):([^\]]+)\]\]/',
			function ( $matches ) use ( $url_map ) {
				$num = (int) $matches[1];
				// Strip guillemet quotes «» from title (AI uses them as formatting markers)
				$title = trim( $matches[2], '«» ' );
				$title = esc_html( $title );
				$url = isset( $url_map[ $num ] ) ? esc_url( $url_map[ $num ] ) : '#';
				return '<a href="' . $url . '" class="wpf-ai-result-link" target="_blank" rel="noopener">' . $title . '</a>';
			},
			$text
		);

		// Replace [[#N]] format - just number with link
		$text = preg_replace_callback(
			'/\[\[#(\d+)\]\]/',
			function ( $matches ) use ( $url_map ) {
				$num = (int) $matches[1];
				$url = isset( $url_map[ $num ] ) ? esc_url( $url_map[ $num ] ) : '#';
				return '<a href="' . $url . '" class="wpf-ai-result-link" target="_blank" rel="noopener">#' . $num . '</a>';
			},
			$text
		);

		return $text;
	}

	/**
	 * Generate HTML for AI search recommendations section
	 *
	 * Builds the complete HTML for the recommendations section server-side
	 * to avoid JavaScript having to handle HTML escaping issues.
	 *
	 * @param array $recommendations Array of recommendation objects with title, recommendation, url, result_number
	 * @return string Complete HTML for the recommendations section
	 */
	private function render_recommendations_html( $recommendations ) {
		if ( empty( $recommendations ) ) {
			return '';
		}

		$html = '<div class="wpf-ai-recommendations-section">';
		$html .= '<div class="wpf-ai-section-header"><i class="fas fa-lightbulb"></i> ' . esc_html( wpforo_phrase( 'AI Recommendations', false ) ) . '</div>';
		$html .= '<div class="wpf-ai-recommendations-list">';

		foreach ( $recommendations as $rec ) {
			$url = ! empty( $rec['url'] ) ? esc_url( $rec['url'] ) : '#';
			$result_number = isset( $rec['result_number'] ) ? (int) $rec['result_number'] : 0;
			// title and recommendation already contain safe HTML from replace_ai_link_markers()
			$title = $rec['title'] ?? '';
			$recommendation = $rec['recommendation'] ?? '';

			$html .= '<div class="wpf-ai-recommendation-card">';
			$html .= '<div class="wpf-ai-recommendation-number">' . $result_number . '</div>';
			$html .= '<div class="wpf-ai-recommendation-content">';
			$html .= '<div class="wpf-ai-recommendation-title"><a href="' . $url . '" target="_blank" rel="noopener">' . $title . '</a></div>';
			$html .= '<div class="wpf-ai-recommendation-text">' . $recommendation . '</div>';
			$html .= '</div>';
			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Replace topic summary link markers with actual HTML links
	 *
	 * Converts [[#POST_ID]] and [[#POST_ID:Title]] markers to clickable links to specific posts.
	 * The POST_ID is the actual database post ID, not a sequential number.
	 *
	 * @param string $text Text containing link markers
	 * @param int $topicid Topic ID for fallback URL
	 * @return string Text with markers replaced by HTML links
	 */
	private function replace_summary_link_markers( $text, $topicid ) {
		if ( empty( $text ) ) {
			return '';
		}

		// Replace [[#POST_ID:Title]] format - title with link to post
		$text = preg_replace_callback(
			'/\[\[#(\d+):([^\]]+)\]\]/',
			function ( $matches ) use ( $topicid ) {
				$postid = (int) $matches[1];
				$title = esc_html( $matches[2] );
				$url = WPF()->post->get_url( $postid );
				if ( empty( $url ) || $url === wpforo_home_url() ) {
					// Fallback to topic URL if post URL not found
					$url = WPF()->topic->get_url( $topicid );
				}
				return '<a href="' . esc_url( $url ) . '" class="wpf-ai-post-link">' . $title . '</a>';
			},
			$text
		);

		// Replace [[#POST_ID]] format - just number with "Reply #N" link
		$text = preg_replace_callback(
			'/\[\[#(\d+)\]\]/',
			function ( $matches ) use ( $topicid ) {
				$postid = (int) $matches[1];
				$url = WPF()->post->get_url( $postid );
				if ( empty( $url ) || $url === wpforo_home_url() ) {
					// Fallback to topic URL if post URL not found
					$url = WPF()->topic->get_url( $topicid );
				}
				return '<a href="' . esc_url( $url ) . '" class="wpf-ai-post-link">#' . $postid . '</a>';
			},
			$text
		);

		return $text;
	}

	/**
	 * AJAX handler for semantic search
	 *
	 * @return void
	 */
	public function ajax_semantic_search() {
		// Verify nonce
		check_ajax_referer( 'wpforo_ai_features_nonce', '_wpnonce' );

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Insufficient permissions', false )
			], 403 );
		}

		// Get search parameters
		$query = sanitize_text_field( wpfval( $_POST, 'query' ) );
		$limit = isset( $_POST['limit'] ) ? min( (int) $_POST['limit'], 100 ) : 10;

		if ( empty( $query ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Search query is required', false )
			], 400 );
		}

		// Perform search via VectorStorageManager (handles local vs cloud routing)
		$results = WPF()->vector_storage->semantic_search( $query, $limit );

		if ( is_wp_error( $results ) ) {
			wp_send_json_error( [
				'message' => $results->get_error_message()
			], 500 );
		}

		// Clean content/excerpt for display (strip Lambda processing markers)
		if ( ! empty( $results['results'] ) ) {
			foreach ( $results['results'] as &$result ) {
				if ( ! empty( $result['excerpt'] ) ) {
					$result['excerpt'] = $this->clean_content_for_search_display( $result['excerpt'] );
				}
				if ( ! empty( $result['content'] ) ) {
					$result['content'] = $this->clean_content_for_search_display( $result['content'] );
				}
			}
			unset( $result );
		}

		// Return success response
		wp_send_json_success( $results );
	}

	/**
	 * AJAX handler for public front-end semantic search
	 *
	 * Accessible to all users (logged-in and guests)
	 * Returns enriched results with topic metadata
	 *
	 * @return void
	 */
	public function ajax_public_semantic_search() {
		// Track start time for logging
		$_log_start_time = microtime( true );

		// Verify nonce (action name matches nonce key in wpforo.nonces object)
		check_ajax_referer( 'wpforo_ai_public_search', '_wpnonce' );

		// Note: Rate limit check moved after cache check
		// Cached search results should bypass rate limits since they don't use API resources

		// Check if AI service is available
		if ( ! $this->is_service_available() ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'AI service is not available', false )
			], 403 );
		}

		// Check if AI semantic search is enabled
		if ( ! wpforo_setting( 'ai', 'search' ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'AI Semantic Search is disabled', false )
			], 403 );
		}

		// Check usergroup permission
		if ( ! WPF()->usergroup->can( 'ai_search' ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'You do not have permission to use this feature', false )
			], 403 );
		}

		// Check if any content has been indexed
		if ( WPF()->vector_storage && ! WPF()->vector_storage->has_indexed_content() ) {
			$message = wpforo_phrase( 'No content has been indexed from this forum yet', false );
			if ( current_user_can( 'manage_options' ) ) {
				$message .= '. ' . wpforo_phrase( 'Please go to Dashboard > wpForo > AI Features > AI Content Indexing and start the content indexing', false );
			}
			wp_send_json_success( [
				'results'            => [],
				'total'              => 0,
				'has_more'           => false,
				'no_indexed_content' => true,
				'message'            => $message,
			] );
		}

		// Get search parameters
		$query = sanitize_text_field( wpfval( $_POST, 'query' ) );
		$limit = isset( $_POST['limit'] ) ? min( (int) $_POST['limit'], 20 ) : 5;
		$offset = isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 0;
		$language_code = sanitize_text_field( wpfval( $_POST, 'language' ) );

		if ( empty( $query ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Search query is required', false )
			], 400 );
		}

		// Build cache key for semantic search (include quality tier to prevent stale cache on tier change)
		$current_boardid = (string) WPF()->board->get_current( 'boardid' );
		$search_quality = wpfval( WPF()->settings->ai, 'search_quality' ) ?: 'balanced';
		$search_cache_key = $this->build_search_cache_key( $query, $limit, $offset, $current_boardid, $search_quality );
		$search_from_cache = false;

		// Check cache first
		$cached_results = $this->get_ai_cache( self::CACHE_TYPE_SEARCH, $search_cache_key );

		if ( $cached_results ) {
			// Cache hit - use cached response
			$results = $cached_results;
			$search_from_cache = true;
		} else {
			// Cache miss - check rate limit before making API call
			// Rate limit is only checked for non-cached requests that consume API resources
			$this->check_rate_limit( 'search' );

			// Cache miss - perform semantic search via VectorStorageManager (fetch more results for pagination)
			$results = WPF()->vector_storage->semantic_search( $query, $limit + $offset );

			if ( is_wp_error( $results ) ) {
				wp_send_json_error( [
					'message' => $results->get_error_message()
				], 500 );
			}

			// Cache the results for future requests (24h TTL)
			$this->set_ai_cache( self::CACHE_TYPE_SEARCH, $search_cache_key, $results );
		}

		if ( is_wp_error( $results ) ) {
			wp_send_json_error( [
				'message' => $results->get_error_message()
			], 500 );
		}

		// Get results array
		$search_results = wpfval( $results, 'results' ) ?: [];
		$total = wpfval( $results, 'total' ) ?: 0;

		// Apply offset and limit for pagination
		if ( $offset > 0 ) {
			$search_results = array_slice( $search_results, $offset, $limit );
		} else {
			$search_results = array_slice( $search_results, 0, $limit );
		}

		// Get minimum score threshold from settings (default 30%)
		// Local cosine similarities are on a different scale (5-25%) than cloud scores (30-90%),
		// so apply 1/3 of the configured threshold for local mode.
		// Absolute minimum of 15% for local mode prevents garbage results (random vectors
		// have ~5-15% cosine similarity with any query).
		$is_local_mode = WPF()->vector_storage && WPF()->vector_storage->is_local_mode();
		$min_score_setting = (int) wpfval( WPF()->settings->ai, 'search_min_score' );
		if ( $is_local_mode ) {
			$local_threshold   = round( $min_score_setting / 3 );
			$min_score_percent = max( 15, $local_threshold ); // Absolute minimum 15%
		} else {
			$min_score_percent = max( 0, min( 100, $min_score_setting ) );
		}

		// Relevance label thresholds - different for local vs cloud modes
		// Local cosine scores: 5-25% range (raw similarity)
		// Cloud re-ranked scores: 30-90% range (LLM re-ranked)
		if ( $is_local_mode ) {
			$threshold_excellent = 25;
			$threshold_good      = 20;
			$threshold_relevant  = 15;
		} else {
			$threshold_excellent = 80;
			$threshold_good      = 60;
			$threshold_relevant  = 40;
		}

		// Enrich results with wpForo/WordPress data
		// Cloud API returns: id, score, title, excerpt, url, content_source, metadata{...}
		// Local storage returns: topic_id, post_id, forum_id, title, content, score, url, post_url, created, user_id, content_type
		$enriched_results = [];
		foreach ( $search_results as $result ) {
			$content_source = wpfval( $result, 'content_source' ) ?: wpfval( $result, 'metadata', 'content_source' );

			if ( $content_source === 'wordpress' ) {
				// ── WordPress CPT result ──
				$wp_post_id = wpfval( $result, 'metadata', 'post_id' );
				if ( empty( $wp_post_id ) ) continue;

				$wp_post = get_post( (int) $wp_post_id );
				if ( ! $wp_post || $wp_post->post_status !== 'publish' ) continue;

				// Use real WP post_type (post, page, product, etc.)
				$post_type_obj   = get_post_type_object( $wp_post->post_type );
				$post_type_label = $post_type_obj ? $post_type_obj->labels->singular_name : ucfirst( $wp_post->post_type );

				$url = get_permalink( $wp_post );

				$author      = get_user_by( 'id', $wp_post->post_author );
				$author_name = $author ? $author->display_name : '';

				$content = wpfval( $result, 'content' ) ?: wpfval( $result, 'excerpt' ) ?: '';
				$content = $this->clean_content_for_search_display( $content );

				$score         = wpfval( $result, 'score' ) ?: 0;
				$score_percent = round( $score * 100 );

				if ( $min_score_percent > 0 && $score_percent < $min_score_percent ) continue;

				if ( $score_percent >= $threshold_excellent ) {
					$relevance_label = wpforo_phrase( 'Excellent match', false );
				} elseif ( $score_percent >= $threshold_good ) {
					$relevance_label = wpforo_phrase( 'Good match', false );
				} elseif ( $score_percent >= $threshold_relevant ) {
					$relevance_label = wpforo_phrase( 'Relevant', false );
				} else {
					$relevance_label = wpforo_phrase( 'Possibly relevant', false );
				}

				$enriched_results[] = [
					'title'           => wpfval( $result, 'title' ) ?: $wp_post->post_title,
					'url'             => $url,
					'content'         => $content,
					'score'           => $score_percent,
					'relevance_label' => $relevance_label,
					'content_source'  => 'wordpress',
					'post_type_label' => $post_type_label,
					'post_id'         => (int) $wp_post_id,
					'forum_title'     => '',
					'forum_url'       => '',
					'author_name'     => $author_name,
					'author_url'      => '',
					'created'         => $wp_post->post_date ? date( 'Y-m-d H:i', strtotime( $wp_post->post_date ) ) : '',
					'created_ago'     => $wp_post->post_date ? human_time_diff( strtotime( $wp_post->post_date ) ) . ' ago' : '',
				];
			} else {
				// ── Forum result ──
				$topic_id = wpfval( $result, 'topic_id' ) ?: wpfval( $result, 'metadata', 'thread_id' );

				if ( empty( $topic_id ) ) {
					continue;
				}

				$topic = wpforo_topic( $topic_id );
				if ( empty( $topic ) ) {
					continue;
				}

				// Get URL - use /postid/<postid>/ format for direct post linking
				$chunk_post_id = wpfval( $result, 'post_id' ) ?: wpfval( $result, 'metadata', 'chunk_post_id' );
				if ( $chunk_post_id ) {
					$url = wpforo_home_url( wpforo_settings_get_slug( 'postid' ) . '/' . intval( $chunk_post_id ) . '/' );
				} else {
					$url = wpfval( $result, 'url' ) ?: wpforo_topic( $topic_id, 'url' );
				}

				// Author: prefer chunk author (specific post), fallback to topic author
				$author_id = wpfval( $result, 'user_id' ) ?: wpfval( $result, 'metadata', 'chunk_author_id' ) ?: wpfval( $result, 'metadata', 'topic_author_id' ) ?: $topic['userid'];
				$author_name = wpfval( $result, 'metadata', 'chunk_display_name' ) ?: wpfval( $result, 'metadata', 'topic_display_name' );
				if ( ! $author_name ) {
					$author = wpforo_member( $author_id );
					$author_name = wpfval( $author, 'display_name' ) ?: wpfval( $author, 'user_login' );
				}

				$content = wpfval( $result, 'content' ) ?: wpfval( $result, 'excerpt' ) ?: '';
				$content = $this->clean_content_for_search_display( $content );

				$score         = wpfval( $result, 'score' ) ?: 0;
				$score_percent = round( $score * 100 );

				if ( $min_score_percent > 0 && $score_percent < $min_score_percent ) {
					continue;
				}

				if ( $score_percent >= $threshold_excellent ) {
					$relevance_label = wpforo_phrase( 'Excellent match', false );
				} elseif ( $score_percent >= $threshold_good ) {
					$relevance_label = wpforo_phrase( 'Good match', false );
				} elseif ( $score_percent >= $threshold_relevant ) {
					$relevance_label = wpforo_phrase( 'Relevant', false );
				} else {
					$relevance_label = wpforo_phrase( 'Possibly relevant', false );
				}

				$forum_title = wpfval( $result, 'metadata', 'forum_name' ) ?: wpforo_forum( $topic['forumid'], 'title' );
				$forum_url   = wpforo_forum( $topic['forumid'], 'url' );

				$title = wpfval( $result, 'title' ) ?: wpfval( $result, 'metadata', 'topic_title' ) ?: $topic['title'];

				$enriched_results[] = [
					'title'           => $title,
					'url'             => $url,
					'content'         => $content,
					'score'           => $score_percent,
					'relevance_label' => $relevance_label,
					'content_source'  => 'forum',
					'post_type_label' => '',
					'post_id'         => $chunk_post_id ? (int) $chunk_post_id : 0,
					'forum_title'     => $forum_title,
					'forum_url'       => $forum_url,
					'author_name'     => $author_name,
					'author_url'      => wpforo_member( $author_id, 'profile_url' ),
					'created'         => wpforo_date( $topic['created'], 'Y-m-d H:i', false ),
					'created_ago'     => wpforo_date( $topic['created'], '', false ),
				];
			}
		}

		// Get AI enhancement (summary + recommendations) if we have 3+ results
		$ai_enhancement = null;
		$enhance_credits_used = 0;
		$enhance_from_cache = false;
		$_debug_enhance = [
			'results_count'    => count( $enriched_results ),
			'min_required'     => 3,
			'setting_value'    => wpfval( WPF()->settings->ai, 'search_enhance' ),
			'setting_type'     => gettype( wpfval( WPF()->settings->ai, 'search_enhance' ) ),
			'all_ai_settings'  => WPF()->settings->ai,
		];
		if ( count( $enriched_results ) >= 3 ) {
			$user_language = $this->get_user_language( $language_code );

			// Build cache key based on query + results + language
			$cache_key = $this->build_enhance_cache_key( $query, $enriched_results, $user_language );

			// Check cache first
			$cached_enhancement = $this->get_ai_cache( self::CACHE_TYPE_SEARCH_ENHANCE, $cache_key );

			if ( $cached_enhancement ) {
				// Cache hit - use cached response, but reprocess recommendations for link markers
				// (older cached entries may have unprocessed [[#N:Title]] markers)
				$enhance_from_cache = true;
				$url_map = [];
				foreach ( $enriched_results as $index => $result ) {
					$url_map[ $index + 1 ] = wpfval( $result, 'url' ) ?: '#';
				}
				$cached_recs = wpfval( $cached_enhancement, 'recommendations' ) ?: [];
				foreach ( $cached_recs as &$rec ) {
					if ( ! empty( $rec['title'] ) ) {
						$rec['title'] = $this->replace_ai_link_markers( $rec['title'], $url_map );
					}
					if ( ! empty( $rec['description'] ) ) {
						$rec['description'] = $this->replace_ai_link_markers( $rec['description'], $url_map );
					}
				}
				unset( $rec );
				$cached_enhancement['recommendations'] = $cached_recs;
				// Generate recommendations HTML server-side
				$cached_enhancement['recommendations_html'] = $this->render_recommendations_html( $cached_recs );
				$ai_enhancement = $cached_enhancement;
				$_debug_enhance['source'] = 'cache';
			} else {
				// Cache miss - call enhance API
				$enhance_response = $this->enhance_search_results(
					$query,
					$enriched_results,
					$user_language
				);
				$_debug_enhance['source'] = 'api';
				$_debug_enhance['api_response'] = $enhance_response;

				if ( ! is_wp_error( $enhance_response ) && wpfval( $enhance_response, 'success' ) ) {
					// Track credits used by AI enhancement
					$enhance_credits_used = wpfval( $enhance_response, 'credits_used' ) ?: 0;

					// Build URL map for link replacement (1-indexed)
					$url_map = [];
					foreach ( $enriched_results as $index => $result ) {
						$url_map[ $index + 1 ] = wpfval( $result, 'url' ) ?: '#';
					}

					// Process recommendations to add URL for each and convert link markers
					$recommendations = wpfval( $enhance_response, 'recommendations' ) ?: [];
					foreach ( $recommendations as &$rec ) {
						$result_num = wpfval( $rec, 'result_number' );
						$rec['url'] = isset( $url_map[ $result_num ] ) ? $url_map[ $result_num ] : '#';
						// Process link markers in title and recommendation
						if ( ! empty( $rec['title'] ) ) {
							$rec['title'] = $this->replace_ai_link_markers( $rec['title'], $url_map );
						}
						if ( ! empty( $rec['recommendation'] ) ) {
							$rec['recommendation'] = $this->replace_ai_link_markers( $rec['recommendation'], $url_map );
						}
					}
					unset( $rec ); // Break reference

					$ai_enhancement = [
						'summary'            => $this->replace_ai_link_markers( wpfval( $enhance_response, 'summary' ) ?: '', $url_map ),
						'quick_answer'       => $this->replace_ai_link_markers( wpfval( $enhance_response, 'quick_answer' ) ?: '', $url_map ),
						'recommendations'    => $recommendations,
						// Generate recommendations HTML server-side
						'recommendations_html' => $this->render_recommendations_html( $recommendations ),
					];

					// Store in cache for future requests
					$this->set_ai_cache( self::CACHE_TYPE_SEARCH_ENHANCE, $cache_key, $ai_enhancement );
				}
			}
		}

		// Log the action
		if ( isset( WPF()->ai_logs ) && WPF()->ai_logs ) {
			// Determine status: cached only if both search and enhance (if used) came from cache
			$all_from_cache = $search_from_cache && ( ! $ai_enhancement || $enhance_from_cache );
			$log_status = $all_from_cache ? AILogs::STATUS_CACHED : AILogs::STATUS_SUCCESS;

			// Build response summary including enhancement info
			$response_parts = [ sprintf( '%d results', $total ) ];
			if ( $ai_enhancement ) {
				$response_parts[] = $enhance_from_cache ? 'AI enhanced (cached)' : 'AI enhanced';
			}

			WPF()->ai_logs->log( [
				'action_type'      => AILogs::ACTION_PUBLIC_SEARCH,
				'credits_used'     => $enhance_credits_used,
				'status'           => $log_status,
				'request_summary'  => $query,
				'response_summary' => implode( ', ', $response_parts ),
				'duration_ms'      => (int) ( ( microtime( true ) - $_log_start_time ) * 1000 ),
			] );
		}

		// Update total to reflect filtered results count
		$filtered_total = count( $enriched_results );

		// Return enriched results with AI enhancement
		wp_send_json_success( [
			'results'            => $enriched_results,
			'total'              => $filtered_total,
			'has_more'           => ( $offset + $limit ) < $total && $filtered_total >= $limit,
			'query'              => $query,
			'ai_enhancement'     => $ai_enhancement,
			'search_from_cache'  => $search_from_cache,
			'_debug_enhance'     => $_debug_enhance, // TEMP DEBUG - remove after fixing
		] );
	}

	/**
	 * AJAX handler to save user AI search preferences
	 *
	 * Stores preferences in user_meta as serialized array
	 * Only accessible to logged-in users
	 *
	 * @return void
	 */
	public function ajax_save_ai_preferences() {
		// Verify nonce
		if ( ! wp_verify_nonce( wpfval( $_POST, 'nonce' ), 'wpforo_ai_preferences' ) ) {
			wp_send_json_error( wpforo_phrase( 'Security check failed', false ), 403 );
		}

		// Must be logged in
		$user_id = WPF()->current_userid;
		if ( ! $user_id ) {
			wp_send_json_error( wpforo_phrase( 'You must be logged in to save preferences', false ), 401 );
		}

		// Get and validate language
		$language = sanitize_text_field( wpfval( $_POST, 'language' ) );
		$valid_languages = array_merge( [ '' ], array_column( wpforo_get_ai_languages(), 'locale' ) );
		if ( ! in_array( $language, $valid_languages, true ) ) {
			$language = get_locale(); // Fallback to WP locale
		}

		// Get and validate max results (backend validation - max 10)
		$max_results = isset( $_POST['max_results'] ) ? (int) $_POST['max_results'] : 5;
		$max_results = max( 1, min( 10, $max_results ) ); // Clamp between 1 and 10

		// Build preferences array
		$preferences = [
			'language'    => $language,
			'max_results' => $max_results,
		];

		// Save to user meta
		$updated = update_user_meta( $user_id, 'wpforo_ai_search', $preferences );

		if ( false === $updated ) {
			// Check if it failed or just unchanged
			$existing = get_user_meta( $user_id, 'wpforo_ai_search', true );
			if ( $existing === $preferences ) {
				// No change needed, still success
				wp_send_json_success( [ 'message' => wpforo_phrase( 'Preferences saved', false ) ] );
			} else {
				wp_send_json_error( wpforo_phrase( 'Failed to save preferences', false ), 500 );
			}
		}

		wp_send_json_success( [ 'message' => wpforo_phrase( 'Preferences saved', false ) ] );
	}

	/**
	 * Get user AI search preferences
	 *
	 * @param int|null $user_id User ID (defaults to current user)
	 * @return array Preferences array with defaults applied
	 */
	public function get_user_ai_preferences( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = WPF()->current_userid;
		}

		$defaults = [
			'language'    => get_locale(),
			'max_results' => 5,
		];

		if ( ! $user_id ) {
			return $defaults;
		}

		$saved = get_user_meta( $user_id, 'wpforo_ai_search', true );
		if ( is_array( $saved ) ) {
			return wp_parse_args( $saved, $defaults );
		}

		return $defaults;
	}

	/**
	 * AJAX handler for generic AI actions (refresh_status, etc.)
	 *
	 * @return void
	 */
	public function ajax_generic_action() {
		// Verify nonce
		check_ajax_referer( 'wpforo_ai_features_nonce', '_wpnonce' );

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Insufficient permissions', false )
			], 403 );
		}

		// Get the specific action
		$wpforo_ai_action = sanitize_text_field( wpfval( $_POST, 'wpforo_ai_action' ) );

		if ( empty( $wpforo_ai_action ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Action is required', false )
			], 400 );
		}

		// Handle different actions
		switch ( $wpforo_ai_action ) {
			case 'refresh_status':
				// Clear status cache and fetch fresh data
				$this->clear_status_cache();
				$status = $this->get_tenant_status();

				if ( is_wp_error( $status ) ) {
					\wpforo_ai_log( 'error', 'Error getting status: ' . $status->get_error_message(), 'Client' );
					wp_send_json_error( [
						'message' => $status->get_error_message()
					], 500 );
				}

				wp_send_json_success( [
					'status' => $status,
					'message' => wpforo_phrase( 'Status refreshed successfully', false )
				] );
				break;

			case 'start_local_indexing':
				// Include helper file if not already loaded
				if ( ! function_exists( 'wpforo_ai_handle_start_local_indexing' ) ) {
					require_once WPFORO_DIR . '/admin/pages/tabs/ai-features-helpers.php';
				}
				$result = wpforo_ai_handle_start_local_indexing();
				if ( isset( $result['type'] ) && $result['type'] === 'success' ) {
					wp_send_json_success( $result );
				} else {
					wp_send_json_error( $result );
				}
				break;

			case 'process_local_batch':
				try {
					// Include helper file if not already loaded
					if ( ! function_exists( 'wpforo_ai_handle_process_local_batch' ) ) {
						require_once WPFORO_DIR . '/admin/pages/tabs/ai-features-helpers.php';
					}
					$result = wpforo_ai_handle_process_local_batch();
					if ( isset( $result['type'] ) && $result['type'] === 'success' ) {
						wp_send_json_success( $result );
					} else {
						wp_send_json_error( $result );
					}
				} catch ( \Exception $e ) {
					\wpforo_ai_log( 'error', 'process_local_batch AJAX exception: ' . $e->getMessage(), 'Client' );
					wp_send_json_error( [ 'message' => 'Exception: ' . $e->getMessage() ] );
				} catch ( \Error $e ) {
					\wpforo_ai_log( 'error', 'process_local_batch AJAX error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 'Client' );
					wp_send_json_error( [ 'message' => 'PHP Error: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine() ] );
				}
				break;

			case 'get_indexing_progress':
				// Include helper file if not already loaded
				if ( ! function_exists( 'wpforo_ai_handle_get_indexing_progress' ) ) {
					require_once WPFORO_DIR . '/admin/pages/tabs/ai-features-helpers.php';
				}
				$result = wpforo_ai_handle_get_indexing_progress();
				if ( isset( $result['type'] ) && $result['type'] === 'success' ) {
					wp_send_json_success( $result );
				} else {
					wp_send_json_error( $result );
				}
				break;

			case 'stop_local_indexing':
				// Include helper file if not already loaded
				if ( ! function_exists( 'wpforo_ai_handle_stop_local_indexing' ) ) {
					require_once WPFORO_DIR . '/admin/pages/tabs/ai-features-helpers.php';
				}
				$result = wpforo_ai_handle_stop_local_indexing();
				if ( isset( $result['type'] ) && $result['type'] === 'success' ) {
					wp_send_json_success( $result );
				} else {
					wp_send_json_error( $result );
				}
				break;

			case 'clear_local_embeddings':
				// Include helper file if not already loaded
				if ( ! function_exists( 'wpforo_ai_handle_clear_local_embeddings' ) ) {
					require_once WPFORO_DIR . '/admin/pages/tabs/ai-features-helpers.php';
				}
				$result = wpforo_ai_handle_clear_local_embeddings();
				if ( isset( $result['type'] ) && $result['type'] === 'success' ) {
					wp_send_json_success( $result );
				} else {
					wp_send_json_error( $result );
				}
				break;

			default:
				wp_send_json_error( [
					'message' => sprintf( wpforo_phrase( 'Unknown action: %s', false ), $wpforo_ai_action )
				], 400 );
		}
	}

	/**
	 * WP Cron handler for processing batches in background
	 *
	 * @param array $args Arguments containing topic_ids, chunk_size, and overlap_percent
	 * @return void
	 */
	public function cron_process_batch( $args, $chunk_size_param = null, $overlap_percent_param = null ) {
		// Handle both old format (args spread as separate params) and new format (single associative array)
		// Old format: $args = topic_ids array, $chunk_size_param = chunk_size, $overlap_percent_param = overlap
		// New format: $args = ['topic_ids' => [...], 'chunk_size' => ..., 'overlap_percent' => ...]
		if ( is_array( $args ) && isset( $args['topic_ids'] ) ) {
			// New format: associative array with all params
			$topic_ids = $args['topic_ids'];
			// Use explicit checks because wpfval returns 0/null if that's the stored value
			$chunk_size = ! empty( $args['chunk_size'] ) ? (int) $args['chunk_size'] : 512;
			$overlap_percent = isset( $args['overlap_percent'] ) && $args['overlap_percent'] !== null ? (int) $args['overlap_percent'] : 20;
		} else {
			// Old format: args are spread as separate parameters
			$topic_ids = is_array( $args ) ? $args : [];
			$chunk_size = ! empty( $chunk_size_param ) ? (int) $chunk_size_param : 512;
			$overlap_percent = $overlap_percent_param !== null ? (int) $overlap_percent_param : 20;
		}

		if ( empty( $topic_ids ) ) {
			$this->log_error( 'cron_batch_empty', 'No topic IDs in batch' );
			return;
		}

		$this->log_info( 'cron_batch_started', [
			'topics_count' => count( $topic_ids ),
			'chunk_size' => $chunk_size,
			'overlap_percent' => $overlap_percent,
		] );

		// Use VectorStorageManager to route to appropriate storage backend
		$storage_manager = WPF()->vector_storage;
		$storage_mode = $storage_manager->get_storage_mode();

		$this->log_info( 'cron_batch_storage_mode', [
			'storage_mode' => $storage_mode,
		] );

		if ( $storage_manager->is_local_mode() ) {
			// Local storage: use batch embedding API for efficiency
			// Check if batch API flag is set (new format) or fall back to old per-topic method
			$use_batch_api = isset( $args['use_batch_api'] ) && $args['use_batch_api'];

			if ( $use_batch_api ) {
				// New efficient batch method: one API call for all topics
				$result = $storage_manager->index_topics_batch_local( $topic_ids, [
					'chunk_size'      => $chunk_size,
					'overlap_percent' => $overlap_percent,
				] );

				if ( is_array( $result ) ) {
					$this->log_info( 'cron_batch_completed', [
						'topics_count'   => count( $topic_ids ),
						'indexed_count'  => $result['indexed_count'] ?? 0,
						'skipped_count'  => $result['skipped_count'] ?? 0,
						'credits_used'   => $result['credits_used'] ?? 0,
						'errors'         => $result['errors'] ?? [],
						'storage_mode'   => 'local_batch',
					] );
				} else {
					$this->log_error( 'cron_batch_failed', [
						'error' => is_wp_error( $result ) ? $result->get_error_message() : 'Unknown error',
						'topics_count' => count( $topic_ids ),
					] );
				}
			} else {
				// Legacy per-topic method (for backwards compatibility)
				$success_count = 0;
				$error_count = 0;

				foreach ( $topic_ids as $topic_id ) {
					$result = $storage_manager->index_topic( $topic_id, [
						'chunk_size'      => $chunk_size,
						'overlap_percent' => $overlap_percent,
					] );

					if ( is_wp_error( $result ) ) {
						$this->log_error( 'cron_topic_index_failed', [
							'topic_id' => $topic_id,
							'error'    => $result->get_error_message(),
						] );
						$error_count++;
					} else {
						$success_count += $result['indexed_count'] ?? 0;
					}
				}

				$this->log_info( 'cron_batch_completed', [
					'topics_count'  => count( $topic_ids ),
					'success_count' => $success_count,
					'error_count'   => $error_count,
					'storage_mode'  => 'local',
				] );
			}
		} else {
			// Cloud storage: use existing batch ingestion
			$response = $this->ingest_topics( $topic_ids, $chunk_size, $overlap_percent );

			if ( is_wp_error( $response ) ) {
				$this->log_error( 'cron_batch_failed', [
					'error' => $response->get_error_message(),
					'topics_count' => count( $topic_ids ),
				] );
			} else {
				$this->log_info( 'cron_batch_completed', [
					'topics_count' => count( $topic_ids ),
					'response' => $response,
					'storage_mode' => 'cloud',
				] );
			}
		}

		// Clear status caches to update progress and credits
		$this->clear_rag_status_cache();
		$this->clear_status_cache(); // Also clear tenant status to refresh credits
	}

	/**
	 * WP Cron handler for processing local indexing queue
	 *
	 * This uses a self-rescheduling pattern to process large batches efficiently:
	 * - Processes 50 topics per execution
	 * - Reschedules itself if more topics remain in queue
	 * - Only ONE cron job exists at a time (no job pile-up)
	 *
	 * @param int $board_id Board ID for multi-board support
	 * @return void
	 */
	public function cron_process_queue( $board_id = 0 ) {
		// Ensure WPF classes are initialized (may not be in cron context)
		if ( is_null( WPF()->vector_storage ) ) {
			WPF()->init();
		}

		$storage_manager = WPF()->vector_storage;
		if ( ! $storage_manager ) {
			return; // Still null, can't proceed
		}

		// Delegate to VectorStorageManager which handles the queue processing
		$storage_manager->cron_process_queue( $board_id );

		// Clear status caches to update progress and credits
		$this->clear_rag_status_cache();
		$this->clear_status_cache();
	}

	/**
	 * Process the LOCAL auto-indexing queue (mode-specific cron handler)
	 *
	 * This ensures topics queued for local indexing are processed with local indexing,
	 * even if the user has since switched to cloud mode.
	 *
	 * @param int $board_id Board ID
	 */
	public function cron_process_queue_local( $board_id = 0 ) {
		// Ensure WPF classes are initialized (may not be in cron context)
		if ( is_null( WPF()->vector_storage ) ) {
			WPF()->init();
		}

		$storage_manager = WPF()->vector_storage;
		if ( ! $storage_manager ) {
			return;
		}

		// Process the local-specific queue
		$storage_manager->cron_process_queue_mode( $board_id, 'local' );

		// Clear status caches
		$this->clear_rag_status_cache();
		$this->clear_status_cache();
	}

	/**
	 * Process the CLOUD auto-indexing queue (mode-specific cron handler)
	 *
	 * This ensures topics queued for cloud indexing are processed with cloud indexing,
	 * even if the user has since switched to local mode.
	 *
	 * @param int $board_id Board ID
	 */
	public function cron_process_queue_cloud( $board_id = 0 ) {
		// Ensure WPF classes are initialized (may not be in cron context)
		if ( is_null( WPF()->vector_storage ) ) {
			WPF()->init();
		}

		$storage_manager = WPF()->vector_storage;
		if ( ! $storage_manager ) {
			return;
		}

		// Process the cloud-specific queue
		$storage_manager->cron_process_queue_mode( $board_id, 'cloud' );

		// Clear status caches
		$this->clear_rag_status_cache();
		$this->clear_status_cache();
	}

	/**
	 * Ingest specific topics by ID
	 *
	 * @param array $topic_ids Array of topic IDs to ingest
	 * @return array|WP_Error Response data or error object
	 */
	public function ingest_topics( $topic_ids, $chunk_size = 512, $overlap_percent = 20 ) {
		// Check if database clearing is in progress
		if ( $this->is_clearing_in_progress() ) {
			$remaining = $this->get_clearing_time_remaining();
			$minutes = ceil( $remaining / 60 );
			return new \WP_Error(
				'clearing_in_progress',
				sprintf(
					/* translators: %d: minutes remaining */
					wpforo_phrase( 'Database clearing is in progress. Please wait approximately %d minute(s) before starting new indexing.', false ),
					$minutes
				)
			);
		}

		if ( empty( $topic_ids ) || ! is_array( $topic_ids ) ) {
			return new \WP_Error( 'invalid_topic_ids', wpforo_phrase( 'Please provide valid topic IDs', false ) );
		}

		// Prepare topic data for indexing
		$threads = [];

		foreach ( $topic_ids as $topic_id ) {
			$topic = wpforo_topic( $topic_id );

			if ( empty( $topic ) ) {
				$this->log_error( 'topic_not_found', "Topic ID {$topic_id} not found" );
				continue;
			}

			// Skip private topics - they should never be indexed
			if ( ! empty( $topic['private'] ) ) {
				$this->log_info( 'skipping_private_topic', "Skipping private topic ID {$topic_id}" );
				continue;
			}

			// Skip unapproved topics
			if ( isset( $topic['status'] ) && (int) $topic['status'] !== 0 ) {
				$this->log_info( 'skipping_unapproved_topic', "Skipping unapproved topic ID {$topic_id}" );
				continue;
			}

			// Get topic posts/replies
			$posts = WPF()->post->get_posts(
				[
					'topicid' => $topic_id,
					'orderby' => 'created',
					'order'   => 'ASC',
				]
			);

			// Format thread data
			$thread = $this->format_thread_data( $topic, $posts );

			if ( $thread ) {
				// Check if topic needs force re-indexing
				// When cloud = 0, it means the topic has changed (new post added)
				// and backend should delete old vectors before re-indexing
				$cloud_status = isset( $topic['cloud'] ) ? (int) $topic['cloud'] : 0;
				if ( $cloud_status === 0 ) {
					$thread['force_reindex'] = true;
				}

				$threads[] = $thread;
			}
		}

		if ( empty( $threads ) ) {
			return new \WP_Error( 'no_threads_to_ingest', wpforo_phrase( 'No valid threads found to ingest', false ) );
		}

		// Get site domain for image/document URL validation on Lambda side
		$site_url = get_site_url();
		$parsed   = wp_parse_url( $site_url );
		$domain   = $parsed['host'] ?? '';

		$data = [
			'threads'         => $threads,
			'chunk_size'      => (int) $chunk_size,
			'overlap_percent' => (int) $overlap_percent,
			'site_domain'     => $domain,
		];

		// Bumped to 60s (from default 30s) as a safety margin. The backend
		// now processes images/documents asynchronously so /rag/ingest
		// typically returns in <5s, but large text-only batches can still
		// approach the old limit.
		$response = $this->post( '/rag/ingest', $data, 60 );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'ingest_failed', $response->get_error_message() );

			// Log error to AILogs database
			if ( isset( WPF()->ai_logs ) ) {
				$user_type = AILogs::USER_TYPE_USER;
				if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
					$user_type = AILogs::USER_TYPE_CRON;
				} elseif ( ! get_current_user_id() ) {
					$user_type = AILogs::USER_TYPE_SYSTEM;
				}

				WPF()->ai_logs->log( [
					'action_type'      => AILogs::ACTION_CONTENT_INDEXING,
					'user_type'        => $user_type,
					'credits_used'     => 0,
					'status'           => AILogs::STATUS_ERROR,
					'content_type'     => 'topic',
					'request_summary'  => sprintf( 'Cloud indexing: %d topics', count( $topic_ids ) ),
					'error_message'    => $response->get_error_message(),
					'extra_data'       => wp_json_encode( [
						'storage_mode' => 'cloud',
						'topic_ids'    => array_slice( $topic_ids, 0, 20 ),
					] ),
				] );
			}

			return $response;
		}

		// Update indexed hash in wpforo_topics table for each indexed topic
		// This enables summarization to use cloud-stored posts instead of sending from WordPress
		if ( ! empty( $response['indexed_hashes'] ) && is_array( $response['indexed_hashes'] ) ) {
			$this->update_topics_indexed_hash( $response['indexed_hashes'] );
		}

		$this->log_info( 'topics_ingested', [ 'count' => count( $threads ) ] );
		$this->clear_rag_status_cache();

		do_action( 'wpforo_ai_topics_ingested', $topic_ids, $response );

		// Log to AILogs database for tracking
		$indexed_count = count( $response['indexed_hashes'] ?? [] );
		if ( $indexed_count > 0 && isset( WPF()->ai_logs ) ) {
			$user_type = AILogs::USER_TYPE_USER;
			if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
				$user_type = AILogs::USER_TYPE_CRON;
			} elseif ( ! get_current_user_id() ) {
				$user_type = AILogs::USER_TYPE_SYSTEM;
			}

			// Build response summary with image/document stats
			$summary_parts = [ sprintf( 'Indexed %d topics to cloud storage', $indexed_count ) ];
			$extra = [
				'storage_mode'    => 'cloud',
				'topic_ids'       => array_slice( $topic_ids, 0, 20 ),
				'topics_indexed'  => $indexed_count,
				'chunk_size'      => $chunk_size,
				'overlap_percent' => $overlap_percent,
			];

			if ( ! empty( $response['image_processing'] ) ) {
				$img_count = $response['image_processing']['images_processed'] ?? 0;
				if ( $img_count > 0 ) {
					$summary_parts[] = sprintf( '%d images', $img_count );
				}
				$extra['image_processing'] = $response['image_processing'];
			}

			if ( ! empty( $response['document_processing'] ) ) {
				$doc_count  = $response['document_processing']['documents_processed'] ?? 0;
				$page_count = $response['document_processing']['total_pages'] ?? 0;
				if ( $doc_count > 0 ) {
					$summary_parts[] = sprintf( '%d documents (%d pages)', $doc_count, $page_count );
				}
				$extra['document_processing'] = $response['document_processing'];
			}

			WPF()->ai_logs->log( [
				'action_type'      => AILogs::ACTION_CONTENT_INDEXING,
				'user_type'        => $user_type,
				'credits_used'     => $response['credits_consumed'] ?? $response['credits_used'] ?? $indexed_count,
				'status'           => AILogs::STATUS_SUCCESS,
				'content_type'     => 'topic',
				'request_summary'  => sprintf( 'Cloud indexing: %d topics', count( $topic_ids ) ),
				'response_summary' => implode( ', ', $summary_parts ),
				'extra_data'       => wp_json_encode( $extra ),
			] );
		}

		return $response;
	}

	/**
	 * Update indexed hash for topics after successful indexing
	 *
	 * The indexed hash is an MD5 of "topicid_postcount" which changes when posts are added/removed.
	 * This enables the summarization feature to use cloud-stored posts instead of requiring WordPress to send them.
	 *
	 * @param array $indexed_hashes Array of topicid => hash mappings
	 * @return int Number of topics updated
	 */
	private function update_topics_indexed_hash( $indexed_hashes ) {
		if ( empty( $indexed_hashes ) ) {
			return 0;
		}

		global $wpdb;

		// Build CASE statement for hash values (each topic has a specific hash from API)
		$case_parts = [];
		$topic_ids = [];
		foreach ( $indexed_hashes as $topic_id => $hash ) {
			$topic_id = (int) $topic_id;
			$hash = sanitize_text_field( $hash );
			$topic_ids[] = $topic_id;
			$case_parts[] = "WHEN {$topic_id} THEN '{$hash}'";
		}

		if ( empty( $topic_ids ) ) {
			return 0;
		}

		$ids_list = implode( ',', $topic_ids );
		$case_statement = implode( ' ', $case_parts );

		// Update indexed hash and cloud column in a single query
		$updated = $wpdb->query(
			"UPDATE `" . WPF()->tables->topics . "`
			SET `indexed` = CASE topicid {$case_statement} END,
			    `cloud` = 1
			WHERE topicid IN ({$ids_list})"
		);

		if ( $updated > 0 ) {
			$this->log_info( 'topics_indexed_hash_updated', [ 'count' => $updated ] );
			// Clear topic cache to reflect indexed status
			wpforo_clean_cache( 'topic' );
		}

		return (int) $updated;
	}

	/**
	 * Clear indexed status for all topics
	 *
	 * Called when:
	 * - Clear Database is clicked (vectors deleted)
	 * - Re-Index All is clicked (forcing re-indexing)
	 * - Disconnect Service is clicked (vectors deleted)
	 *
	 * Clears both the `indexed` hash and the `cloud` column.
	 *
	 * @return int Number of topics updated
	 */
	private function clear_topics_indexed_status() {
		$result = WPF()->db->query(
			"UPDATE `" . WPF()->tables->topics . "` SET `indexed` = NULL, `cloud` = 0 WHERE `indexed` IS NOT NULL OR `cloud` = 1"
		);

		if ( $result !== false && $result > 0 ) {
			$this->log_info( 'topics_indexed_status_cleared', [ 'count' => $result ] );
			wpforo_clean_cache( 'topic' );
		}

		return (int) $result;
	}

	/**
	 * Re-index all topics
	 *
	 * Memory-efficient implementation using pagination to handle large forums (100,000+ topics)
	 * Processes topics in batches without loading all topics into memory at once
	 *
	 * @param int $chunk_size Chunk size for text splitting in tokens (default: 512 tokens)
	 * @param int $overlap_percent Overlap percentage for chunking (default: 20%)
	 * @return array|WP_Error Response data or error object
	 */
	public function reindex_all_topics( $chunk_size = 512, $overlap_percent = 20 ) {
		// Check if database clearing is in progress
		if ( $this->is_clearing_in_progress() ) {
			$remaining = $this->get_clearing_time_remaining();
			$minutes = ceil( $remaining / 60 );
			return new \WP_Error(
				'clearing_in_progress',
				sprintf(
					/* translators: %d: minutes remaining */
					wpforo_phrase( 'Database clearing is in progress. Please wait approximately %d minute(s) before starting new indexing.', false ),
					$minutes
				)
			);
		}

		$storage_manager = WPF()->vector_storage;

		// Get total topic count
		$total_topics = WPF()->topic->get_count();

		if ( empty( $total_topics ) || $total_topics <= 0 ) {
			return new \WP_Error( 'no_topics', wpforo_phrase( 'No topics found in forum', false ) );
		}

		// Check available credits before starting
		$status = $this->get_tenant_status( true ); // Force fresh status
		if ( is_wp_error( $status ) ) {
			return $status;
		}

		$credits_available = isset( $status['subscription']['credits_remaining'] ) ? (int) $status['subscription']['credits_remaining'] : 0;

		if ( $credits_available <= 0 ) {
			return new \WP_Error(
				'no_credits',
				wpforo_phrase( 'No credits available for indexing. Please wait for your monthly credit reset or upgrade your plan.', false )
			);
		}

		// Get unindexed topics (cloud = 0)
		$unindexed_topic_ids = $storage_manager->get_unindexed_topic_ids();
		$unindexed_count = count( $unindexed_topic_ids );

		// Determine if we're doing incremental indexing or full re-index
		$is_reindex_all = ( $unindexed_count === 0 );

		if ( $is_reindex_all ) {
			// All topics are indexed - user wants to re-index everything
			// Clear indexed status to force re-indexing
			$this->clear_topics_indexed_status();

			$this->log_info( 'reindex_mode', [
				'mode' => 'reindex_all',
				'total_topics' => $total_topics,
			] );

			// Get all topic IDs for re-indexing
			$all_topic_ids = $storage_manager->get_unindexed_topic_ids(); // Now all have cloud=0
		} else {
			// Some topics need indexing - only index those (don't clear status)
			$all_topic_ids = $unindexed_topic_ids;

			$this->log_info( 'reindex_mode', [
				'mode' => 'incremental',
				'unindexed_count' => $unindexed_count,
				'total_topics' => $total_topics,
			] );
		}

		// Limit topics to available credits
		if ( count( $all_topic_ids ) > $credits_available ) {
			$all_topic_ids = array_slice( $all_topic_ids, 0, $credits_available );
		}

		$this->log_info( 'reindex_credit_check', [
			'topics_to_index'   => count( $all_topic_ids ),
			'credits_available' => $credits_available,
			'is_reindex_all'    => $is_reindex_all,
		] );

		if ( empty( $all_topic_ids ) ) {
			return new \WP_Error( 'no_topics', wpforo_phrase( 'No topic IDs collected', false ) );
		}

		// Configuration for memory-efficient processing
		$pagination_size = (int) wpforo_get_option( 'ai_pagination_size', 20 );
		$api_batch_size = $pagination_size;

		$this->log_info( 'reindex_started', [
			'topics_to_index' => count( $all_topic_ids ),
			'api_batch_size'  => $api_batch_size,
			'chunk_size'      => $chunk_size,
			'overlap_percent' => $overlap_percent,
		] );

		// Check if we limited topics due to credits
		$original_count = $is_reindex_all ? $total_topics : $unindexed_count;
		$topics_limited = count( $all_topic_ids ) < $original_count;
		$skipped_topics = $topics_limited ? $original_count - count( $all_topic_ids ) : 0;

		// Split topic IDs into batches for API calls
		$api_batches = array_chunk( $all_topic_ids, $api_batch_size );
		$total_batches = count( $api_batches );

		$this->log_info( 'reindex_topic_ids_collected', [
			'topic_ids_count'  => count( $all_topic_ids ),
			'api_batch_size'   => $api_batch_size,
			'total_batches'    => $total_batches,
			'batches_to_queue' => max( 0, $total_batches - 1 ), // -1 because first batch is processed immediately
			'topics_limited'   => $topics_limited,
			'skipped_topics'   => $skipped_topics,
		] );

		// Process first batch immediately (synchronous)
		$first_batch = array_shift( $api_batches );
		$response = $this->ingest_topics( $first_batch, $chunk_size, $overlap_percent );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'reindex_first_batch_failed', $response->get_error_message() );
			return $response;
		}

		// Queue remaining batches for background processing via WP Cron
		$scheduled_count = 0;
		$failed_schedules = [];

		if ( ! empty( $api_batches ) ) {
			// Get plan-based interval (Enterprise: 5s, Business: 10s, Professional: 10s, Starter/Free: 20s)
			$batch_interval = $this->get_cron_interval_for_plan();

			// Reset array keys after array_shift to ensure sequential indices starting from 0
			$api_batches = array_values( $api_batches );

			foreach ( $api_batches as $batch_index => $batch ) {
				// Schedule with staggered timing based on subscription plan
				// Higher tier plans get faster indexing speeds
				// Note: Args must be wrapped in indexed array so the entire associative array
				// is passed as the first argument to the callback
				$scheduled_time = time() + ( ( $batch_index + 1 ) * $batch_interval );
				$cron_args = [
					[
						'topic_ids'       => $batch,
						'chunk_size'      => $chunk_size,
						'overlap_percent' => $overlap_percent,
					]
				];

				$result = wp_schedule_single_event( $scheduled_time, 'wpforo_ai_process_batch', $cron_args );

				if ( $result === false ) {
					$failed_schedules[] = [
						'batch_index' => $batch_index,
						'topics_count' => count( $batch ),
						'scheduled_time' => $scheduled_time,
					];
				} else {
					$scheduled_count++;
				}
			}

			$this->log_info( 'reindex_batches_scheduled', [
				'scheduled_batches' => $scheduled_count,
				'failed_schedules'  => count( $failed_schedules ),
				'topics_per_batch'  => $api_batch_size,
				'batch_interval_seconds' => $batch_interval,
				'failed_details'    => $failed_schedules,
			] );
		} else {
			$this->log_info( 'reindex_no_batches_to_schedule', [
				'reason' => 'All topics fit in first batch',
				'first_batch_size' => count( $first_batch ),
			] );
		}

		// Clear cached status to force refresh
		$this->clear_rag_status_cache();

		// Trigger action hook for external integrations
		do_action( 'wpforo_ai_reindex_started', count( $all_topic_ids ) );

		// Build message with credit limiting info if applicable
		if ( $topics_limited ) {
			$message = sprintf(
				wpforo_phrase( 'Re-indexing started: %1$d topics queued (limited by %2$d available credits). %3$d topics skipped.', false ),
				count( $all_topic_ids ),
				$credits_available,
				$skipped_topics
			);
		} else {
			$message = sprintf(
				wpforo_phrase( 'Re-indexing started: %d topics queued', false ),
				count( $all_topic_ids )
			);
		}

		return [
			'success'          => true,
			'message'          => $message,
			'topics_queued'    => count( $all_topic_ids ),
			'topics_limited'   => $topics_limited,
			'skipped_topics'   => $skipped_topics,
			'total_topics'     => $total_topics,
			'total_batches'    => $total_batches,
			'first_batch_sent' => count( $first_batch ),
			'scheduled_crons'  => $scheduled_count,
			'failed_schedules' => count( $failed_schedules ),
		];
	}

	/**
	 * Clear RAG database
	 *
	 * The actual deletion is queued and processed asynchronously (up to 5 minutes).
	 * This returns immediately after queueing the operation.
	 *
	 * Local database is updated immediately to reflect the clearing state.
	 *
	 * @param int $board_id Board ID (reserved for future multi-board support)
	 * @return array|WP_Error Response data or error object
	 */
	public function clear_rag_database( $board_id = 0 ) {
		// API returns 202 Accepted immediately, actual deletion happens async
		$response = $this->delete( '/rag/clear', [], 30 );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'rag_clear_failed', $response->get_error_message() );
			return $response;
		}

		$this->log_info( 'rag_database_clear_queued', [
			'status' => $response['status'] ?? 'unknown',
		] );
		$this->clear_rag_status_cache();

		// Set clearing lock to prevent new indexing during async clear
		// Lock expires after 5 minutes (async clear should complete by then)
		$this->set_clearing_lock();

		// Clear indexed status for all topics immediately
		// The cloud data will be deleted asynchronously, but we update local state now
		// so the UI shows correct counts right away
		$this->clear_topics_indexed_status();

		do_action( 'wpforo_ai_rag_cleared', $response );

		// Return response with note about async processing
		return [
			'success' => true,
			'message' => $response['message'] ?? wpforo_phrase( 'Database clear operation queued', false ),
			'status'  => 'clearing',
			'note'    => $response['note'] ?? wpforo_phrase( 'The database is being cleared in the background. This may take a few minutes.', false ),
		];
	}

	/**
	 * Set a lock to prevent new indexing during async database clear
	 *
	 * The lock expires after 5 minutes (the maximum time for async clear to complete).
	 */
	private function set_clearing_lock() {
		set_transient( 'wpforo_ai_clearing_in_progress', time(), 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Check if database clearing is in progress
	 *
	 * @return bool True if clearing is in progress, false otherwise
	 */
	public function is_clearing_in_progress() {
		return (bool) get_transient( 'wpforo_ai_clearing_in_progress' );
	}

	/**
	 * Get remaining time until clearing lock expires
	 *
	 * @return int Seconds remaining, or 0 if not clearing
	 */
	public function get_clearing_time_remaining() {
		$started = get_transient( 'wpforo_ai_clearing_in_progress' );
		if ( ! $started ) {
			return 0;
		}
		$elapsed = time() - (int) $started;
		$remaining = ( 5 * MINUTE_IN_SECONDS ) - $elapsed;
		return max( 0, $remaining );
	}

	/**
	 * Clear RAG status cache
	 */
	public function clear_rag_status_cache() {
		delete_transient( 'wpforo_ai_rag_status' );
	}

	/**
	 * Queue a topic for cloud indexing
	 *
	 * This method is called by VectorStorageManager when in cloud mode.
	 * It queues the topic for background processing via the cloud RAG pipeline.
	 *
	 * @param int $topicid  Topic ID to queue
	 * @param int $board_id Board ID (reserved for future multi-board support)
	 * @return array|WP_Error Result or error
	 */
	public function queue_topic_for_indexing( $topicid, $board_id = 0 ) {
		$response = $this->post( '/rag/queue', [
			'topic_ids' => [ $topicid ],
			'board_id'  => $board_id,
		] );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'queue_topic_failed', $response->get_error_message(), [ 'topicid' => $topicid ] );
			return $response;
		}

		$this->log_info( 'topic_queued_for_indexing', [
			'topicid'  => $topicid,
			'board_id' => $board_id,
		] );

		return $response;
	}

	/**
	 * Delete a topic from the cloud index
	 *
	 * This method is called by VectorStorageManager when in cloud mode.
	 * It removes all embeddings associated with the topic from the cloud storage.
	 *
	 * @param int $topicid  Topic ID to delete
	 * @param int $board_id Board ID (reserved for future multi-board support)
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function delete_topic_from_index( $topicid, $board_id = 0 ) {
		$response = $this->delete( '/rag/topic/' . intval( $topicid ), [
			'board_id' => $board_id,
		] );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'delete_topic_failed', $response->get_error_message(), [ 'topicid' => $topicid ] );
			return $response;
		}

		$this->log_info( 'topic_deleted_from_index', [
			'topicid'  => $topicid,
			'board_id' => $board_id,
		] );

		return true;
	}

	/**
	 * Delete a post from the cloud index
	 *
	 * This method is called by VectorStorageManager when in cloud mode.
	 * It removes the embedding for a specific post from the cloud storage.
	 *
	 * @param int $postid   Post ID to delete
	 * @param int $board_id Board ID (reserved for future multi-board support)
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function delete_post_from_index( $postid, $board_id = 0 ) {
		$response = $this->delete( '/rag/post/' . intval( $postid ), [
			'board_id' => $board_id,
		] );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'delete_post_failed', $response->get_error_message(), [ 'postid' => $postid ] );
			return $response;
		}

		$this->log_info( 'post_deleted_from_index', [
			'postid'   => $postid,
			'board_id' => $board_id,
		] );

		return true;
	}

	/**
	 * Find similar topics via cloud API
	 *
	 * This method is called by VectorStorageManager when in cloud mode.
	 * It uses the cloud semantic search to find topics similar to the given one.
	 *
	 * @param int $topic_id Topic ID to find similar topics for
	 * @param int $limit    Maximum number of results to return
	 * @return array|WP_Error Array of similar topic IDs with scores, or WP_Error on failure
	 */
	public function find_similar_topics( $topic_id, $limit = 5 ) {
		$response = $this->api_get( '/rag/similar', [
			'topic_id' => $topic_id,
			'limit'    => $limit,
		] );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'find_similar_failed', $response->get_error_message(), [ 'topic_id' => $topic_id ] );
			return $response;
		}

		return isset( $response['results'] ) ? $response['results'] : [];
	}

	/**
	 * Get cron interval (in seconds) based on subscription plan
	 *
	 * Higher tier plans get faster indexing speeds:
	 * - Free trial & Starter: 20 seconds between batches
	 * - Professional & Business: 10 seconds
	 * - Enterprise: 5 seconds
	 *
	 * Note: Faster intervals mean topics get QUEUED faster, but actual processing
	 * speed depends on API concurrency limits. SQS handles message accumulation.
	 *
	 * @return int Interval in seconds
	 */
	public function get_cron_interval_for_plan() {
		// Use cached plan from database (no API calls during cron)
		$plan = strtolower( $this->get_subscription_plan() );

		// Plan-based intervals (in seconds)
		// Faster intervals queue topics quicker; actual processing depends on API concurrency
		$intervals = [
			'enterprise'   => 5,   // Fastest queuing for enterprise
			'business'     => 10,
			'professional' => 10,
			'starter'      => 20,
			'free_trial'   => 20,
		];

		// Return interval for plan, default to 20 seconds for unknown plans
		return isset( $intervals[ $plan ] ) ? $intervals[ $plan ] : 20;
	}

	/**
	 * Check if there are pending WP Cron jobs for background indexing
	 *
	 * @return array Status information about pending jobs
	 */
	/**
	 * admin_init wrapper: only nudge on real wpForo AI admin page loads.
	 *
	 * Checks `$_GET['page']` against the wpForo AI admin slugs and skips
	 * everything else (other admin pages, AJAX requests, cron requests,
	 * REST requests). This is the single entry point for the nudge — it
	 * does NOT run on AJAX status polls.
	 *
	 * @return void
	 */
	public function maybe_nudge_wp_cron_on_admin_page() {
		// Never run inside AJAX / cron / REST contexts — only on a real
		// admin page load (i.e. an admin hitting Refresh on the wpForo AI
		// admin page, or opening it from the menu).
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		// Only on the wpForo AI admin pages.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( $page === '' || strpos( $page, 'wpforo-ai' ) !== 0 ) {
			return;
		}

		$this->maybe_nudge_wp_cron();
	}

	/**
	 * Self-heal stalled WP-Cron for indexing background jobs.
	 *
	 * On some hosts (managed hosts where the loopback HTTP used by
	 * `spawn_cron()` is blocked, sites with `DISABLE_WP_CRON`, sites with
	 * plugins that suppress cron, or sites with no recent front-end traffic)
	 * a scheduled `wpforo_ai_process_queue_*` event sits in the cron array
	 * forever and the admin sees "Indexing..." with zero progress.
	 *
	 * Scope — this is intentionally narrow:
	 * - Only the per-board queue hooks: wpforo_ai_process_queue_cloud,
	 *   wpforo_ai_process_queue_local, wpforo_ai_process_queue.
	 * - Only events whose first arg equals the CURRENT board id (admins
	 *   view AI settings one board at a time; nudge only the board they
	 *   are looking at — never other boards, never non-board hooks).
	 * - Only events overdue by >= 2 minutes.
	 * - No other plugin's cron jobs, ever.
	 *
	 * Previous attempts used `spawn_cron()` to kick off WP's normal cron
	 * loopback. That fails on hosts that block or strip the loopback
	 * request, which is exactly the environments we need to heal. This
	 * version does what `leira-cron-jobs` does in its "Run now" action:
	 * it fires the scheduled hooks INLINE in the current PHP request via
	 * `do_action_ref_array()` — no loopback HTTP, no background request.
	 *
	 * Flow (per overdue hook):
	 * 1. Find the scheduled event in `_get_cron_array()`.
	 * 2. Reschedule recurring events for their next tick, or unschedule
	 *    single-run events so they don't fire twice.
	 * 3. `do_action_ref_array( $hook, $args )` to run the handler inline.
	 *
	 * This helper only runs on wpForo AI admin page loads (refresh), NOT
	 * on AJAX status polls. The admin hitting Refresh is effectively the
	 * user asking "please run any stalled indexing work right now".
	 *
	 * Safety notes:
	 * - The indexing processors have their own lock transient
	 *   (`wpforo_ai_indexing_lock_{mode}_{board_id}`, 300s TTL) so rapid
	 *   refreshes cannot cause duplicate batch processing.
	 * - We only fire hooks that are already OVERDUE (>= 2 min past due).
	 *   On a healthy site WP-Cron fires events on schedule, nothing is
	 *   overdue, and this is a pure no-op.
	 * - Not called during AJAX polling → zero race window with Stop Indexing.
	 * - Not called during REST / cron / non-wpforo-ai pages.
	 * - No new settings, no new UI, no new cron hooks.
	 *
	 * Tradeoff: the page refresh will take as long as one batch of
	 * indexing work takes (typically a few seconds). Admins refreshing
	 * a stalled indexing dialog already expect work to happen.
	 *
	 * @return void
	 */
	public function maybe_nudge_wp_cron() {
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( '_get_cron_array' ) ) {
			return;
		}

		// Scope the nudge to the current board. Each board has its own
		// queue cron (args = [ $board_id ]) and admins view the AI page
		// one board at a time — we should only run indexing for the board
		// the admin is currently looking at, nothing else.
		if ( ! isset( WPF()->board ) || ! method_exists( WPF()->board, 'get_current' ) ) {
			return;
		}
		$current_board_id = WPF()->board->get_current( 'boardid' );
		if ( $current_board_id === null || $current_board_id === false ) {
			return;
		}
		$current_board_id = (int) $current_board_id;

		$now = time();
		// Grace period so a normal brief WP-Cron tardiness on healthy sites
		// does not trigger the inline run.
		$overdue_threshold = 120;

		// Only the per-board indexing queue hooks. These are the hooks
		// scheduled by VectorStorageManager with args = [ $board_id ] and
		// protected by the wpforo_ai_indexing_lock_{mode}_{board_id}
		// transient. We do NOT touch any other cron jobs on the site —
		// not other plugins, not other wpForo hooks, not even other
		// wpForo AI hooks that are not per-board scoped.
		$our_hooks = [
			'wpforo_ai_process_queue_cloud' => true,
			'wpforo_ai_process_queue_local' => true,
			'wpforo_ai_process_queue'       => true,
		];

		$crons = _get_cron_array();
		if ( ! is_array( $crons ) || empty( $crons ) ) {
			return;
		}

		// Collect overdue events for the current board only.
		$to_run = [];
		foreach ( $crons as $timestamp => $events ) {
			if ( ( $now - $timestamp ) < $overdue_threshold ) {
				continue;
			}
			if ( ! is_array( $events ) ) {
				continue;
			}
			foreach ( $events as $hook => $dings ) {
				if ( ! isset( $our_hooks[ $hook ] ) || ! is_array( $dings ) ) {
					continue;
				}
				foreach ( $dings as $sig => $event ) {
					$args = isset( $event['args'] ) ? (array) $event['args'] : [];
					// Must be scoped to the board the admin is viewing.
					$event_board_id = isset( $args[0] ) ? (int) $args[0] : 0;
					if ( $event_board_id !== $current_board_id ) {
						continue;
					}
					$to_run[] = [
						'timestamp' => $timestamp,
						'hook'      => $hook,
						'args'      => $args,
						'schedule'  => isset( $event['schedule'] ) ? $event['schedule'] : false,
					];
				}
			}
		}

		if ( empty( $to_run ) ) {
			return;
		}

		// Mark the current request as a cron context so the inline handlers
		// behave identically to a normal WP-Cron invocation.
		if ( ! defined( 'DOING_CRON' ) ) {
			define( 'DOING_CRON', true );
		}

		foreach ( $to_run as $event ) {
			// Defensive: skip hooks with no registered handler.
			if ( ! has_action( $event['hook'] ) ) {
				continue;
			}

			// Single-run events only in this whitelist — unschedule so
			// do_action below is the only run. (Guard against recurring
			// anyway in case a future caller schedules one recurring.)
			if ( false !== $event['schedule'] ) {
				wp_reschedule_event( $event['timestamp'], $event['schedule'], $event['hook'], $event['args'] );
			}
			wp_unschedule_event( $event['timestamp'], $event['hook'], $event['args'] );

			// Fire the handler inline. Wrapped in try/catch so a fatal
			// in the indexing handler does not white-screen the admin
			// page — log and move on.
			try {
				do_action_ref_array( $event['hook'], $event['args'] );
			} catch ( \Throwable $e ) {
				if ( method_exists( $this, 'log_error' ) ) {
					$this->log_error(
						'inline_cron_run_failed',
						[
							'hook'  => $event['hook'],
							'error' => $e->getMessage(),
						]
					);
				}
			}
		}
	}

	public function get_pending_cron_jobs() {
		$crons = _get_cron_array();
		$pending_jobs = 0;
		$pending_topics = 0;
		$is_actively_processing = false;
		$board_id = WPF()->board->get_current( 'boardid' ) ?: 0;
		$now = time();

		// Check for queue-based processing (self-rescheduling pattern)
		// Mode-specific keys (current format): wpforo_ai_indexing_queue_{mode}_{board_id}
		// Legacy key (old format): wpforo_ai_indexing_queue_{board_id}
		foreach ( [ 'local', 'cloud', '' ] as $mode ) {
			$queue_key = 'wpforo_ai_indexing_queue_' . ( $mode ? $mode . '_' : '' ) . $board_id;
			$queue = get_option( $queue_key, [] );
			if ( ! empty( $queue ) ) {
				$pending_topics += count( $queue );
				$pending_jobs = max( $pending_jobs, 1 );
			}
		}

		// Check if any queue processor cron is scheduled (mode-specific or legacy)
		// and whether it's due now (actively processing) or scheduled for the future (just queued)
		foreach ( [ 'wpforo_ai_process_queue_local', 'wpforo_ai_process_queue_cloud', 'wpforo_ai_process_queue' ] as $cron_hook ) {
			$next = wp_next_scheduled( $cron_hook, [ $board_id ] );
			if ( $next ) {
				$pending_jobs = max( $pending_jobs, 1 );
				// Cron is due or overdue — batch processing is imminent or in progress
				if ( $next <= $now ) {
					$is_actively_processing = true;
				}
			}
		}

		// Also check if a processing lock is held (batch is running right now)
		if ( get_transient( 'wpforo_ai_indexing_lock_local_' . $board_id )
		  || get_transient( 'wpforo_ai_indexing_lock_cloud_' . $board_id )
		  || get_transient( 'wpforo_ai_indexing_lock_' . $board_id ) ) {
			$is_actively_processing = true;
		}

		// Legacy: Search for wpforo_ai_process_batch scheduled events (old pattern)
		if ( ! empty( $crons ) ) {
			foreach ( $crons as $timestamp => $cron ) {
				if ( isset( $cron['wpforo_ai_process_batch'] ) ) {
					foreach ( $cron['wpforo_ai_process_batch'] as $key => $job ) {
						$pending_jobs++;
						if ( $timestamp <= $now ) {
							$is_actively_processing = true;
						}
						// Count topics in this batch
						$args = isset( $job['args'] ) ? $job['args'] : [];
						if ( isset( $args[0] ) && is_array( $args[0] ) ) {
							$args = $args[0];
						}
						$topic_ids = isset( $args['topic_ids'] ) ? $args['topic_ids'] : [];
						$pending_topics += count( $topic_ids );
					}
				}
			}
		}

		return [
			'has_pending_jobs'       => $pending_jobs > 0,
			'is_actively_processing' => $is_actively_processing,
			'pending_jobs'           => $pending_jobs,
			'pending_topics'         => $pending_topics,
		];
	}

	/**
	 * Clear all pending WP Cron jobs for background indexing
	 *
	 * @return array Status information about cleared jobs
	 */
	public function clear_pending_cron_jobs() {
		$crons = _get_cron_array();
		$cleared_jobs = 0;
		$cleared_topics = 0;
		$board_id = WPF()->board->get_current( 'boardid' ) ?: 0;

		// Clear queue-based pattern: mode-specific keys (current) and legacy key
		foreach ( [ 'local', 'cloud', '' ] as $mode ) {
			$queue_key = 'wpforo_ai_indexing_queue_' . ( $mode ? $mode . '_' : '' ) . $board_id;
			$queue = get_option( $queue_key, [] );
			if ( ! empty( $queue ) ) {
				$cleared_topics += count( $queue );
				$cleared_jobs++;
				delete_option( $queue_key );
			}
		}

		// Clear all queue processor crons (mode-specific and legacy)
		wp_clear_scheduled_hook( 'wpforo_ai_process_queue_local', [ $board_id ] );
		wp_clear_scheduled_hook( 'wpforo_ai_process_queue_cloud', [ $board_id ] );
		wp_clear_scheduled_hook( 'wpforo_ai_process_queue', [ $board_id ] );

		// Legacy: Find and remove all wpforo_ai_process_batch scheduled events
		if ( ! empty( $crons ) ) {
			foreach ( $crons as $timestamp => $cron ) {
				if ( isset( $cron['wpforo_ai_process_batch'] ) ) {
					foreach ( $cron['wpforo_ai_process_batch'] as $key => $job ) {
						// Count topics in this batch before removing
						$args = isset( $job['args'] ) ? $job['args'] : [];
						if ( isset( $args[0] ) && is_array( $args[0] ) ) {
							$args = $args[0];
						}
						$topic_ids = isset( $args['topic_ids'] ) ? $args['topic_ids'] : [];
						$cleared_topics += count( $topic_ids );
						$cleared_jobs++;

						// Unschedule this specific event
						wp_unschedule_event( $timestamp, 'wpforo_ai_process_batch', $job['args'] );
					}
				}
			}
		}

		// Also clear any recurring hooks (just in case)
		wp_clear_scheduled_hook( 'wpforo_ai_process_batch' );

		return [
			'cleared_jobs' => $cleared_jobs,
			'cleared_topics' => $cleared_topics,
			'success' => true,
		];
	}

	/**
	 * Get user display name with proper fallbacks
	 *
	 * Delegates to AIUserTrait::get_single_user_display_name() for consistent
	 * user name resolution with proper fallback chain.
	 *
	 * @param int $userid User ID (0 for guests)
	 * @return string User display name
	 */
	private function get_user_display_name( $userid ) {
		return $this->get_single_user_display_name( (int) $userid );
	}

	/**
	 * Format thread data for RAG Indexing
	 *
	 * @param array $topic Topic data
	 * @param array $posts Array of posts/replies
	 * @return array|null Formatted thread data or null
	 */
	private function format_thread_data( $topic, $posts ) {
		if ( empty( $topic ) ) {
			return null;
		}

		// Get topic URL
		$topic_url = wpforo_topic( $topic['topicid'], 'url' );

		// Separate first post (topic) from replies
		$first_post = ! empty( $posts ) ? array_shift( $posts ) : null;

		if ( ! $first_post ) {
			return null;
		}

		// Get current board ID (for multi-board support)
		$boardid = (int) WPF()->board->get_current( 'boardid' );

		// Get display names with proper fallbacks
		$topic_display_name = $this->get_user_display_name( $first_post['userid'] );

		// Check if image/document indexing is enabled (Professional+ only)
		$include_images    = $this->is_image_indexing_enabled();
		$include_documents = $this->is_document_indexing_enabled();

		// Clean post content for indexing (strip quoted content, etc.)
		// This removes quoted replies to prevent duplicate content in the index
		$cleaned_first_post_body = $this->clean_content_for_indexing( $first_post['body'] );

		// Format topic data
		$thread_data = [
			'thread_id' => (string) $topic['topicid'],
			'board_id'  => $boardid,
			'topic'     => [
				'title'        => $topic['title'],
				'body'         => $cleaned_first_post_body,
				'author'       => wpforo_member( $first_post['userid'], 'user_login' ) ?: $topic_display_name,
				'author_id'    => (string) $first_post['userid'],
				'display_name' => $topic_display_name,
				'post_id'      => (string) $first_post['postid'],
				'created_at'   => is_numeric( $first_post['created'] ) ? date( 'Y-m-d H:i:s', (int) $first_post['created'] ) : $first_post['created'],
				'url'          => $topic_url,
				'forum_id'     => (int) $topic['forumid'],
				'forum_name'   => wpforo_forum( $topic['forumid'], 'title' ),
				// New fields for embedding strategy
				'is_solved'    => ! empty( $topic['solved'] ),
				'likes_count'  => (int) wpfval( $first_post, 'likes', 0 ),
			],
			'replies'   => [],
		];

		// Add images for first post if image indexing is enabled
		// Note: Extract images from cleaned content (quotes already removed)
		if ( $include_images ) {
			$topic_images = $this->extract_post_images( $cleaned_first_post_body );
			if ( ! empty( $topic_images ) ) {
				$thread_data['topic']['images'] = $topic_images;
			}
		}

		// Add documents for first post if document indexing is enabled
		if ( $include_documents ) {
			$topic_documents = $this->extract_post_documents( $cleaned_first_post_body );
			if ( ! empty( $topic_documents ) ) {
				$thread_data['topic']['documents'] = $topic_documents;
			}
		}

		// Format replies
		foreach ( $posts as $post ) {
			$reply_display_name = $this->get_user_display_name( $post['userid'] );

			// Clean reply content for indexing (strip quoted content, etc.)
			$cleaned_reply_body = $this->clean_content_for_indexing( $post['body'] );

			$reply_data = [
				'body'           => $cleaned_reply_body,
				'author'         => wpforo_member( $post['userid'], 'user_login' ) ?: $reply_display_name,
				'author_id'      => (string) $post['userid'],
				'display_name'   => $reply_display_name,
				'post_id'        => (string) $post['postid'],
				'created_at'     => is_numeric( $post['created'] ) ? date( 'Y-m-d H:i:s', (int) $post['created'] ) : $post['created'],
				// New fields for embedding strategy
				'is_best_answer' => ! empty( $post['is_answer'] ),
				'likes_count'    => (int) wpfval( $post, 'likes', 0 ),
			];

			// Add images for reply if image indexing is enabled
			// Note: Extract images from cleaned content (quotes already removed)
			if ( $include_images ) {
				$reply_images = $this->extract_post_images( $cleaned_reply_body );
				if ( ! empty( $reply_images ) ) {
					$reply_data['images'] = $reply_images;
				}
			}

			// Add documents for reply if document indexing is enabled
			if ( $include_documents ) {
				$reply_documents = $this->extract_post_documents( $cleaned_reply_body );
				if ( ! empty( $reply_documents ) ) {
					$reply_data['documents'] = $reply_documents;
				}
			}

			$thread_data['replies'][] = $reply_data;
		}

		return $thread_data;
	}

	/**
	 * Make GET request to API
	 *
	 * @param string $endpoint API endpoint (e.g., '/tenant/status')
	 * @param array  $query_params Query parameters
	 * @return array|WP_Error Response data or error object
	 */
	private function get( $endpoint, $query_params = [] ) {
		return $this->make_request( 'GET', $endpoint, [], $query_params );
	}

	/**
	 * Make POST request to API
	 *
	 * @param string $endpoint API endpoint
	 * @param array  $data Request body data
	 * @param int    $timeout Optional custom timeout in seconds (0 = use default)
	 * @return array|WP_Error Response data or error object
	 */
	private function post( $endpoint, $data = [], $timeout = 0 ) {
		return $this->make_request( 'POST', $endpoint, $data, [], $timeout );
	}

	/**
	 * Make public POST request to API
	 *
	 * This method is for external use by other classes (e.g., TaskManager)
	 * that need to make API calls with custom timeouts.
	 *
	 * @param string $endpoint API endpoint
	 * @param array  $data Request body data
	 * @param int    $timeout Optional custom timeout in seconds (default: 30)
	 * @return array|WP_Error Response data or error object
	 */
	public function api_post( $endpoint, $data = [], $timeout = 30 ) {
		return $this->make_request( 'POST', $endpoint, $data, [], $timeout );
	}

	/**
	 * Make public GET request to API
	 *
	 * This method is for external use by other classes (e.g., VectorStorageManager)
	 * that need to make API calls.
	 *
	 * @param string $endpoint API endpoint
	 * @param array  $query_params Query parameters
	 * @param int    $timeout Optional custom timeout in seconds (default: 30)
	 * @return array|WP_Error Response data or error object
	 */
	public function api_get( $endpoint, $query_params = [], $timeout = 30 ) {
		return $this->make_request( 'GET', $endpoint, [], $query_params, $timeout );
	}

	/**
	 * Make PUT request to API
	 *
	 * @param string $endpoint API endpoint
	 * @param array  $data Request body data
	 * @return array|WP_Error Response data or error object
	 */
	private function put( $endpoint, $data = [] ) {
		return $this->make_request( 'PUT', $endpoint, $data );
	}

	/**
	 * Make DELETE request to API
	 *
	 * @param string $endpoint API endpoint
	 * @param array  $data Request body data
	 * @param int    $timeout Optional custom timeout in seconds (default: 30)
	 * @return array|WP_Error Response data or error object
	 */
	private function delete( $endpoint, $data = [], $timeout = 30 ) {
		return $this->make_request( 'DELETE', $endpoint, $data, [], $timeout );
	}

	/**
	 * Central request method with automatic fallback domain support
	 *
	 * Tries the primary API domain first. If the request fails due to a connection
	 * error (DNS failure, timeout, blocked domain), automatically retries on the
	 * fallback domain.
	 *
	 * @param string $method HTTP method (GET, POST, PUT, DELETE)
	 * @param string $endpoint API endpoint
	 * @param array  $data Request body data
	 * @param array  $query_params Query parameters (for GET requests)
	 * @param int    $timeout Optional custom timeout in seconds (0 = use default)
	 * @return array|WP_Error Response data or error object
	 */
	private function make_request( $method, $endpoint, $data = [], $query_params = [], $timeout = 0 ) {
		$args = $this->build_request_args( $method, $data, $endpoint );
		if ( $timeout > 0 ) {
			$args['timeout'] = $timeout;
		}

		$this->log_request( $method, $endpoint, ! empty( $data ) ? $data : $query_params );

		// Try primary domain
		$url      = $this->build_url( $endpoint, $query_params );
		$response = $this->dispatch_request( $method, $url, $args );

		// If connection failed and fallback domain is available, retry
		if ( is_wp_error( $response ) && $this->is_connection_error( $response ) && $this->fallback_api_url ) {
			$this->log_error( 'Primary API domain failed', $response->get_error_message() );

			$fallback_url = $this->build_url( $endpoint, $query_params, $this->fallback_api_url );
			$response     = $this->dispatch_request( $method, $fallback_url, $args );

			if ( ! is_wp_error( $response ) ) {
				$this->log_error( 'Fallback domain succeeded', $this->fallback_api_url );
			}
		}

		return $this->process_response( $response );
	}

	/**
	 * Dispatch an HTTP request using the appropriate WordPress function
	 *
	 * @param string $method HTTP method
	 * @param string $url Full request URL
	 * @param array  $args Request arguments
	 * @return array|WP_Error Raw response
	 */
	private function dispatch_request( $method, $url, $args ) {
		switch ( $method ) {
			case 'GET':
				return wp_remote_get( $url, $args );
			case 'POST':
				return wp_remote_post( $url, $args );
			default:
				return wp_remote_request( $url, $args );
		}
	}

	/**
	 * Check if a WP_Error represents a connection-level failure
	 *
	 * These are errors where the request never reached the server:
	 * DNS failures, connection refused, timeouts, SSL errors, blocked requests.
	 *
	 * @param \WP_Error $error The error to check
	 * @return bool True if this is a connection error worth retrying on fallback
	 */
	private function is_connection_error( $error ) {
		$code    = $error->get_error_code();
		$message = strtolower( $error->get_error_message() );

		// WordPress HTTP API error codes for connection failures
		$connection_codes = [
			'http_request_failed',
			'http_request_not_executed',
		];

		if ( in_array( $code, $connection_codes, true ) ) {
			return true;
		}

		// Check error message for connection-related keywords
		$connection_keywords = [
			'could not resolve host',
			'connection refused',
			'connection timed out',
			'operation timed out',
			'name or service not known',
			'network is unreachable',
			'no route to host',
			'ssl',
			'curl error',
		];

		foreach ( $connection_keywords as $keyword ) {
			if ( strpos( $message, $keyword ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build full API URL
	 *
	 * @param string $endpoint API endpoint
	 * @param array  $query_params Query parameters
	 * @param string $base_url Optional base URL override (for fallback domain)
	 * @return string Full URL
	 */
	private function build_url( $endpoint, $query_params = [], $base_url = '' ) {
		$base = $base_url ?: $this->api_base_url;
		$url  = rtrim( $base, '/' ) . '/' . ltrim( $endpoint, '/' );

		if ( ! empty( $query_params ) ) {
			$url = add_query_arg( $query_params, $url );
		}

		return $url;
	}

	/**
	 * Build request arguments
	 *
	 * @param string $method HTTP method
	 * @param array  $data Request body data
	 * @return array Request arguments
	 */
	private function build_request_args( $method = 'GET', $data = [], $endpoint = '' ) {
		$headers = [
			'Content-Type' => 'application/json',
		];

		// Add Origin header for cross-domain security validation
		// The backend validates that requests come from the registered site_url
		$site_url = get_site_url();
		if ( $site_url ) {
			$headers['Origin'] = $site_url;
		}

		// Add development key header for localhost access
		// Define WPFORO_AI_DEV_KEY in wp-config.php to enable localhost API access
		if ( defined( 'WPFORO_AI_DEV_KEY' ) && WPFORO_AI_DEV_KEY ) {
			$headers['X-Dev-Key'] = WPFORO_AI_DEV_KEY;
		}

		// Add API key header if available (for authenticated requests)
		// Skip API key for registration endpoint
		$api_key = $this->get_stored_api_key();
		if ( $api_key && strpos( $endpoint, '/register' ) === false ) {
			$headers['Authorization'] = 'Bearer ' . $api_key;
		}

		$args = [
			'method'      => $method,
			'timeout'     => $this->timeout,
			'redirection' => 5,
			'httpversion' => '1.1',
			'blocking'    => true,
			'headers'     => $headers,
			'sslverify'   => apply_filters( 'wpforo_ai_ssl_verify', true ),
		];

		// Add body for POST/DELETE requests
		if ( ! empty( $data ) && in_array( $method, [ 'POST', 'PUT', 'DELETE', 'PATCH' ] ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		return apply_filters( 'wpforo_ai_request_args', $args, $method, $data );
	}

	/**
	 * Process API response
	 *
	 * @param array|\WP_Error $response Raw response
	 * @return array|\WP_Error Processed response data or error
	 */
	private function process_response( $response ) {
		if ( is_wp_error( $response ) ) {
			$this->log_error( 'API Request Failed', $response->get_error_message() );
			return new \WP_Error(
				'api_request_failed',
				sprintf( wpforo_phrase( 'API request failed: %s', false ), $response->get_error_message() )
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );
		$data          = json_decode( $body, true );

		$this->log_response( $response_code, $data );

		// Handle HTTP error codes
		if ( $response_code >= 400 ) {
			$error_message = $this->get_error_message( $response_code, $data );
			return new \WP_Error( 'api_error_' . $response_code, $error_message, $data );
		}

		// Handle API-level errors
		if ( isset( $data['success'] ) && false === $data['success'] ) {
			$error_message = wpfval( $data, 'message' ) ?: wpforo_phrase( 'Unknown API error occurred', false );
			return new \WP_Error( 'api_error', $error_message, $data );
		}

		return $data;
	}

	/**
	 * Get user-friendly error message based on response code
	 *
	 * @param int   $code HTTP status code
	 * @param array $data Response data
	 * @return string Error message
	 */
	private function get_error_message( $code, $data ) {
		// Check for FastAPI 'detail' field (common in 4xx/5xx errors)
		if ( isset( $data['detail'] ) ) {
			// If detail is an object/array with message or error fields
			if ( is_array( $data['detail'] ) ) {
				if ( isset( $data['detail']['message'] ) ) {
					return sanitize_text_field( $data['detail']['message'] );
				}
				if ( isset( $data['detail']['error'] ) ) {
					return sanitize_text_field( $data['detail']['error'] );
				}
			}
			// If detail is a string
			if ( is_string( $data['detail'] ) ) {
				return sanitize_text_field( $data['detail'] );
			}
		}

		// Check for API-provided message first
		if ( isset( $data['message'] ) ) {
			$message = sanitize_text_field( $data['message'] );
			// In debug mode, append additional details
			if ( $this->is_debug_mode() && isset( $data['error'] ) ) {
				$message .= ' [' . sanitize_text_field( $data['error'] ) . ']';
			}
			return $message;
		}

		// Check for error field
		if ( isset( $data['error'] ) ) {
			return sanitize_text_field( $data['error'] );
		}

		// Fallback to standard HTTP status messages
		$messages = [
			400 => wpforo_phrase( 'Bad request. Please check your input and try again.', false ),
			401 => wpforo_phrase( 'Unauthorized. This may be due to localhost URL or missing authentication.', false ),
			402 => wpforo_phrase( 'Insufficient credits available for this search. Please upgrade your plan or purchase additional credits.', false ),
			403 => wpforo_phrase( 'Access forbidden. This feature is not available in your plan.', false ),
			404 => wpforo_phrase( 'Resource not found. The requested endpoint does not exist.', false ),
			429 => wpforo_phrase( 'Rate limit exceeded. Please try again later.', false ),
			500 => wpforo_phrase( 'Internal server error. Please try again later.', false ),
			503 => wpforo_phrase( 'Service temporarily unavailable. Please try again later.', false ),
		];

		return wpfval( $messages, $code ) ?: sprintf( wpforo_phrase( 'API error (HTTP %d)', false ), $code );
	}

	/**
	 * Get stored API key (decrypted)
	 *
	 * Uses global option (shared across all boards)
	 *
	 * @return string|null API key or null if not set
	 */
	public function get_stored_api_key() {
		$encrypted = $this->get_api_key(); // Uses global option

		if ( empty( $encrypted ) ) {
			return null;
		}

		return $this->decrypt_api_key( $encrypted );
	}

	/**
	 * Encrypt API key for storage
	 *
	 * @param string $key Plain text API key
	 * @return string Encrypted key
	 */
	public function encrypt_api_key( $key ) {
		// Use base64 encoding as minimum obfuscation
		return base64_encode( $key );
	}

	/**
	 * Decrypt API key from storage
	 *
	 * @param string $encrypted Encrypted API key
	 * @return string Plain text API key
	 */
	public function decrypt_api_key( $encrypted ) {
		return base64_decode( $encrypted );
	}

	/**
	 * Get masked API key for display in UI
	 *
	 * @param string $key Full API key
	 * @return string Masked key (e.g., "wp_abc***")
	 */
	public function mask_api_key( $key ) {
		if ( empty( $key ) || strlen( $key ) < 10 ) {
			return '***';
		}

		$prefix = substr( $key, 0, 6 ); // Show first 6 characters (e.g., "wp_abc")
		return $prefix . '***';
	}

	/**
	 * Check if debug mode is enabled
	 *
	 * @return bool
	 */
	private function is_debug_mode() {
		return (bool) wpforo_setting( 'general', 'debug_mode' );
	}

	/**
	 * Log API request (only in debug mode)
	 *
	 * @param string $method HTTP method
	 * @param string $endpoint API endpoint
	 * @param array  $data Request data
	 */
	private function log_request( $method, $endpoint, $data = [] ) {
		if ( ! $this->is_debug_mode() ) {
			return;
		}

		\wpforo_ai_log( 'debug', sprintf(
			'%s %s | Data: %s',
			$method,
			$endpoint,
			wp_json_encode( $this->sanitize_log_data( $data ) )
		), 'Client' );
	}

	/**
	 * Log API response (only in debug mode)
	 *
	 * @param int   $code Response code
	 * @param array $data Response data
	 */
	private function log_response( $code, $data ) {
		if ( ! $this->is_debug_mode() ) {
			return;
		}

		\wpforo_ai_log( 'debug', sprintf(
			'Response %d | Data: %s',
			$code,
			wp_json_encode( $this->sanitize_log_data( $data ) )
		), 'Client' );
	}

	/**
	 * Log error message
	 *
	 * @param string $context Error context
	 * @param string $message Error message
	 * @param array  $data    Optional additional data
	 */
	private function log_error( $context, $message, $data = [] ) {
		$log_message = sprintf( '%s: %s', $context, $message );
		if ( ! empty( $data ) ) {
			$log_message .= ' ' . wp_json_encode( $data );
		}
		\wpforo_ai_log( 'error', $log_message, 'Client' );
		do_action( 'wpforo_ai_error', $context, $message, $data );
	}

	/**
	 * Log info message (only in debug mode)
	 *
	 * @param string $context Info context
	 * @param array  $data Additional data
	 */
	private function log_info( $context, $data = [] ) {
		if ( ! $this->is_debug_mode() ) {
			return;
		}

		\wpforo_ai_log( 'info', sprintf(
			'%s | Data: %s',
			$context,
			wp_json_encode( $this->sanitize_log_data( $data ) )
		), 'Client' );
	}

	/**
	 * Sanitize data for logging (remove sensitive info)
	 *
	 * @param array $data Data to sanitize
	 * @return array Sanitized data
	 */
	private function sanitize_log_data( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$sensitive_keys = [ 'api_key', 'new_api_key', 'password', 'token', 'secret' ];

		foreach ( $sensitive_keys as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$data[ $key ] = '***REDACTED***';
			}
		}

		// Recursively sanitize nested arrays
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ $key ] = $this->sanitize_log_data( $value );
			}
		}

		return $data;
	}

	// =========================================================================
	// AI CACHE METHODS
	// =========================================================================

	/**
	 * Cache type constants
	 */
	const CACHE_TYPE_SEARCH = 'search';
	const CACHE_TYPE_SEARCH_ENHANCE = 'search_enhance';
	const CACHE_TYPE_TRANSLATE = 'translate';
	const CACHE_TYPE_TOPIC_SUMMARY = 'topic_summary';

	/**
	 * Cache TTL in seconds (24 hours)
	 */
	const CACHE_TTL = 86400;

	/**
	 * Build cache key for search enhancement
	 *
	 * Cache key is based on: query + result IDs + language
	 * This ensures cache invalidates when results change (e.g., after reindexing)
	 *
	 * @param string $query Search query
	 * @param array $results Search results array
	 * @param string $language User language
	 * @return string Raw MD5 hash (16 bytes binary)
	 */
	private function build_enhance_cache_key( $query, $results, $language ) {
		// Extract result IDs to include in cache key
		// If results change (different topics returned), cache key changes
		$result_ids = [];
		foreach ( $results as $result ) {
			$result_ids[] = wpfval( $result, 'url' ) ?: ''; // Use URL as unique identifier
		}

		$cache_string = strtolower( trim( $query ) ) . '|' . implode( ',', $result_ids ) . '|' . $language;

		// Return raw binary MD5 (16 bytes) for BINARY(16) column
		return md5( $cache_string, true );
	}

	/**
	 * Build cache key for semantic search
	 *
	 * Cache key is based on: query + limit + offset + board_id + quality
	 * Quality tier is included to prevent serving stale cached results
	 * when the admin changes the search quality setting.
	 *
	 * @param string $query Search query
	 * @param int $limit Result limit
	 * @param int $offset Result offset
	 * @param string $board_id Board ID for multi-board support
	 * @param string $quality Search quality tier (fast, balanced, advanced, premium)
	 * @return string Raw MD5 hash (16 bytes binary)
	 */
	private function build_search_cache_key( $query, $limit, $offset, $board_id, $quality = 'fast' ) {
		$cache_string = 'search:' . strtolower( trim( $query ) ) . '|limit:' . intval( $limit ) . '|offset:' . intval( $offset ) . '|board:' . $board_id . '|quality:' . $quality;

		// Return raw binary MD5 (16 bytes) for BINARY(16) column
		return md5( $cache_string, true );
	}

	/**
	 * Build cache key for translation
	 *
	 * Cache key is based on: postid + language
	 *
	 * @param int $postid Post ID
	 * @param string $language Target language code
	 * @return string Raw MD5 hash (16 bytes binary)
	 */
	private function build_translate_cache_key( $postid, $language ) {
		$cache_string = 'post:' . intval( $postid ) . '|lang:' . strtolower( trim( $language ) );
		return md5( $cache_string, true );
	}

	/**
	 * Build cache key for topic summary
	 *
	 * Cache key is based on: topicid + reply_count + last_modified + quality + style + language
	 * This ensures cache invalidates when:
	 * - New replies are added (reply_count changes)
	 * - Content is edited (last_modified changes)
	 * - AI quality tier changes (quality changes)
	 * - Summary style changes (style changes)
	 * - Target language changes (language changes)
	 *
	 * @param int $topicid Topic ID
	 * @param int $reply_count Number of replies in the topic
	 * @param string $last_modified Last modified timestamp of the topic
	 * @param string $quality AI quality tier (fast, balanced, advanced, premium)
	 * @param string $style Summary style (compact, structured, conversational, detailed, minimal)
	 * @param string $language Target language for summary (empty = auto-detect)
	 * @return string Raw MD5 hash (16 bytes binary)
	 */
	private function build_topic_summary_cache_key( $topicid, $reply_count, $last_modified = '', $quality = 'advanced', $style = 'detailed', $language = '' ) {
		$cache_string = 'topic:' . intval( $topicid ) . '|replies:' . intval( $reply_count ) . '|modified:' . $last_modified . '|quality:' . $quality . '|style:' . $style . '|lang:' . $language;
		return md5( $cache_string, true );
	}

	/**
	 * Get cached AI response
	 *
	 * Includes opportunistic cleanup: 1% chance to run garbage collection
	 * on each cache read. This ensures expired entries are cleaned even if
	 * WP Cron fails or is disabled.
	 *
	 * @param string $type Cache type (e.g., 'search_enhance')
	 * @param string $cache_key Raw MD5 hash (16 bytes binary)
	 * @return array|null Cached response or null if not found/expired
	 */
	private function get_ai_cache( $type, $cache_key ) {
		global $wpdb;

		$table = WPF()->tables->ai_cache;
		$now   = time();

		// Opportunistic cleanup: 1% chance to clean expired entries
		// This is a fallback if WP Cron doesn't run
		if ( wp_rand( 1, 100 ) === 1 ) {
			$this->cleanup_expired_cache();
		}

		// Query for valid cache entry
		// Translation cache (expires_at = 0) never expires, other types check expiry time
		$response = $wpdb->get_var( $wpdb->prepare(
			"SELECT response FROM `{$table}` WHERE type = %s AND cache_key = %s AND (expires_at = 0 OR expires_at > %d)",
			$type,
			$cache_key,
			$now
		) );

		if ( $response ) {
			$decoded = json_decode( $response, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$this->log_info( 'ai_cache_hit', [ 'type' => $type ] );
				return $decoded;
			}
		}

		return null;
	}

	/**
	 * Store AI response in cache
	 *
	 * @param string $type Cache type (e.g., 'search_enhance', 'translate')
	 * @param string $cache_key Raw MD5 hash (16 bytes binary)
	 * @param array $response Response data to cache
	 * @param int $ttl Time-to-live in seconds (default: CACHE_TTL), 0 for no expiry
	 * @param int $postid Post ID for translation cache (default: 0)
	 * @return bool True on success, false on failure
	 */
	private function set_ai_cache( $type, $cache_key, $response, $ttl = null, $postid = 0 ) {
		global $wpdb;

		if ( $ttl === null ) {
			$ttl = self::CACHE_TTL;
		}

		// Auto-schedule cleanup cron if not already scheduled
		$this->schedule_cache_cleanup();

		$table = WPF()->tables->ai_cache;
		$json  = wp_json_encode( $response );

		// expires_at = 0 means never expires (for translation cache)
		$expires_at = ( $ttl === 0 ) ? 0 : time() + $ttl;

		// Use REPLACE to insert or update existing cache entry
		$result = $wpdb->replace(
			$table,
			[
				'type'       => $type,
				'cache_key'  => $cache_key,
				'response'   => $json,
				'expires_at' => $expires_at,
				'postid'     => intval( $postid ),
			],
			[ '%s', '%s', '%s', '%d', '%d' ]
		);

		if ( $result !== false ) {
			$this->log_info( 'ai_cache_set', [ 'type' => $type, 'ttl' => $ttl, 'postid' => $postid ] );
			return true;
		}

		$this->log_error( 'ai_cache_set_failed', $wpdb->last_error );
		return false;
	}

	/**
	 * Clean up expired cache entries
	 *
	 * Should be called periodically (e.g., via WP Cron)
	 *
	 * @return int Number of deleted rows
	 */
	public function cleanup_expired_cache() {
		global $wpdb;

		$table = WPF()->tables->ai_cache;
		$now   = time();

		$deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM `{$table}` WHERE expires_at < %d",
			$now
		) );

		if ( $deleted > 0 ) {
			$this->log_info( 'ai_cache_cleanup', [ 'deleted' => $deleted ] );
		}

		return (int) $deleted;
	}

	/**
	 * WP Cron handler for AI cache cleanup
	 *
	 * Runs daily to remove expired cache entries
	 */
	public function cron_cache_cleanup() {
		$deleted = $this->cleanup_expired_cache();
		$this->log_info( 'cron_cache_cleanup_complete', [ 'deleted' => $deleted ] );
	}

	/**
	 * Schedule AI cache cleanup cron job
	 *
	 * Should be called on plugin activation
	 */
	public function schedule_cache_cleanup() {
		if ( ! wp_next_scheduled( 'wpforo_ai_cache_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'wpforo_ai_cache_cleanup' );
		}
	}

	/**
	 * Unschedule AI cache cleanup cron job
	 *
	 * Should be called on plugin deactivation
	 */
	public function unschedule_cache_cleanup() {
		$timestamp = wp_next_scheduled( 'wpforo_ai_cache_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'wpforo_ai_cache_cleanup' );
		}
	}

	/**
	 * Schedule daily pending topics indexing cron job
	 *
	 * Should be called on plugin activation or when AI is connected.
	 * Runs once daily to find and queue topics that need indexing.
	 */
	public function schedule_pending_topics_indexing() {
		if ( ! wp_next_scheduled( 'wpforo_ai_pending_topics_indexing' ) ) {
			// Schedule to run at 3 AM server time (off-peak hours)
			$next_run = strtotime( 'tomorrow 3:00am' );
			wp_schedule_event( $next_run, 'daily', 'wpforo_ai_pending_topics_indexing' );
		}
	}

	/**
	 * Unschedule daily pending topics indexing cron job
	 *
	 * Should be called on plugin deactivation.
	 */
	public function unschedule_pending_topics_indexing() {
		$timestamp = wp_next_scheduled( 'wpforo_ai_pending_topics_indexing' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'wpforo_ai_pending_topics_indexing' );
		}
	}

	/**
	 * Schedule daily subscription sync cron job
	 *
	 * Syncs subscription status and plan from API once per day.
	 * This ensures cached subscription info stays up-to-date without
	 * making API calls on every page load.
	 */
	public function schedule_daily_subscription_sync() {
		if ( ! wp_next_scheduled( 'wpforo_ai_daily_subscription_sync' ) ) {
			// Schedule to run at 4 AM server time (off-peak hours, after other daily jobs)
			$next_run = strtotime( 'tomorrow 4:00am' );
			wp_schedule_event( $next_run, 'daily', 'wpforo_ai_daily_subscription_sync' );
		}
	}

	/**
	 * Unschedule daily subscription sync cron job
	 *
	 * Should be called on plugin deactivation.
	 */
	public function unschedule_daily_subscription_sync() {
		$timestamp = wp_next_scheduled( 'wpforo_ai_daily_subscription_sync' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'wpforo_ai_daily_subscription_sync' );
		}
	}

	/**
	 * Cron handler for daily subscription status sync
	 *
	 * Fetches fresh status from API and updates cached subscription info.
	 * Only runs if tenant is connected.
	 */
	public function cron_daily_subscription_sync() {
		// Only sync if connected
		if ( ! $this->is_connected() ) {
			return;
		}

		// Force fresh API call to get latest subscription status
		$this->clear_status_cache();
		$status = $this->get_tenant_status( true );

		if ( is_wp_error( $status ) ) {
			$this->log_error( 'daily_subscription_sync_failed', $status->get_error_message() );
			return;
		}

		$this->log_info( 'daily_subscription_sync_completed', [
			'plan'   => $status['subscription']['plan'] ?? 'unknown',
			'status' => $status['subscription']['status'] ?? 'unknown',
		] );
	}

	/**
	 * Clear all AI cache entries of a specific type
	 *
	 * @param string $type Cache type to clear (or null for all types)
	 * @return int Number of deleted rows
	 */
	public function clear_ai_cache( $type = null ) {
		global $wpdb;

		$table = WPF()->tables->ai_cache;

		if ( $type ) {
			$deleted = $wpdb->query( $wpdb->prepare(
				"DELETE FROM `{$table}` WHERE type = %s",
				$type
			) );
		} else {
			$deleted = $wpdb->query( "TRUNCATE TABLE `{$table}`" );
		}

		$this->log_info( 'ai_cache_cleared', [ 'type' => $type ?: 'all', 'deleted' => $deleted ] );

		return (int) $deleted;
	}

	/**
	 * Clear translation cache for a specific post
	 *
	 * Called when a post is updated or deleted to invalidate cached translations
	 *
	 * @param int $postid Post ID
	 * @return int Number of deleted cache entries
	 */
	public function clear_translation_cache_by_postid( $postid ) {
		global $wpdb;

		$postid = intval( $postid );
		if ( ! $postid ) {
			return 0;
		}

		$table = WPF()->tables->ai_cache;

		$deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM `{$table}` WHERE type = %s AND postid = %d",
			self::CACHE_TYPE_TRANSLATE,
			$postid
		) );

		if ( $deleted ) {
			$this->log_info( 'translation_cache_cleared', [ 'postid' => $postid, 'deleted' => $deleted ] );
		}

		return (int) $deleted;
	}

	/**
	 * Clear topic summary cache for a specific topic
	 *
	 * Called when a post is added/edited/deleted to invalidate cached summaries.
	 * Note: The summary cache key includes reply_count and last_modified, so deleting
	 * by topicid clears all cached versions of the summary for this topic.
	 *
	 * @param int $topicid Topic ID
	 * @return int Number of deleted cache entries
	 */
	public function clear_topic_summary_cache( $topicid ) {
		global $wpdb;

		$topicid = intval( $topicid );
		if ( ! $topicid ) {
			return 0;
		}

		$table = WPF()->tables->ai_cache;

		// Topic summary cache uses postid column to store topicid
		$deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM `{$table}` WHERE type = %s AND postid = %d",
			self::CACHE_TYPE_TOPIC_SUMMARY,
			$topicid
		) );

		if ( $deleted ) {
			$this->log_info( 'topic_summary_cache_cleared', [ 'topicid' => $topicid, 'deleted' => $deleted ] );
		}

		return (int) $deleted;
	}

	/**
	 * Callback for post edit action
	 *
	 * Clears translation cache when a post is edited
	 *
	 * @param array $post Post data
	 * @param array $topic Topic data
	 * @param array $forum Forum data
	 * @param array $args Edit arguments
	 */
	public function on_post_edit( $post, $topic, $forum, $args ) {
		$postid = wpfval( $post, 'postid' );
		if ( $postid ) {
			$this->clear_translation_cache_by_postid( $postid );
		}

		// Update indexed hash for the topic (invalidates summarization cache)
		// Don't set cloud = 0 - per-reply deduplication handles content changes
		$topicid = wpfval( $post, 'topicid' );
		if ( $topicid ) {
			$this->update_topic_indexed_hash( $topicid );
		}
	}

	/**
	 * Callback for post add action
	 *
	 * Invalidates cloud status when a new post is added to an indexed topic.
	 * This ensures the topic will be force re-indexed on next indexing run.
	 *
	 * @param array $post Post data
	 * @param array $topic Topic data
	 */
	public function on_post_add( $post, $topic ) {
		$topicid = wpfval( $post, 'topicid' );
		if ( $topicid ) {
			// Set local = 0 and cloud = 0 to trigger force re-indexing (new post = structural change)
			$this->invalidate_topic_indexed_status( $topicid, 'post_added' );
			// Also update indexed hash for cache invalidation
			$this->update_topic_indexed_hash( $topicid );
		}
	}

	/**
	 * Callback for post delete action
	 *
	 * When a post is deleted:
	 * 1. Clear translation cache for the post
	 * 2. Delete local embedding for the post
	 * 3. Invalidate topic indexed status (triggers re-indexing)
	 * 4. Update indexed hash (invalidates summarization cache)
	 *
	 * Note: Cloud vectors are cleaned up on next sync when the comparison
	 * detects the missing post. This avoids blocking the delete operation
	 * with an API call.
	 *
	 * @param array $post Post data
	 */
	public function on_post_delete( $post ) {
		$postid  = wpfval( $post, 'postid' );
		$topicid = wpfval( $post, 'topicid' );

		if ( $postid ) {
			// 1. Clear translation cache for this post
			$this->clear_translation_cache_by_postid( $postid );

			// 2. Delete local embedding for this post
			if ( WPF()->vector_storage ) {
				WPF()->vector_storage->delete_post_embedding( $postid );
			}
		}

		if ( $topicid ) {
			// 3. Invalidate topic indexed status (local=0, cloud=0)
			// This ensures the topic gets re-indexed without the deleted post
			$this->invalidate_topic_indexed_status( $topicid, 'post_deleted' );

			// 4. Update indexed hash (invalidates summarization cache)
			$this->update_topic_indexed_hash( $topicid );

			// 5. Clear topic summary cache
			$this->clear_topic_summary_cache( $topicid );

			// 6. Clear indexed counts transient
			delete_transient( 'wpforo_ai_indexed_counts' );
		}
	}

	/**
	 * Callback for post approve action
	 *
	 * When an unapproved post is approved, we need to:
	 * 1. Update the indexed hash (content versioning)
	 * 2. Set local = 0 and cloud = 0 to trigger re-indexing
	 *
	 * This ensures the approved post content gets indexed properly.
	 *
	 * @param array $post Post data
	 */
	public function on_post_approve( $post ) {
		$topicid = wpfval( $post, 'topicid' );
		if ( $topicid ) {
			// Update indexed hash for cache invalidation (new content version)
			$this->update_topic_indexed_hash( $topicid );
			// Set local = 0 and cloud = 0 to trigger force re-indexing
			$this->invalidate_topic_indexed_status( $topicid, 'post_approved' );
		}
	}

	/**
	 * Callback for topic add action
	 *
	 * When a new approved topic is created, queue it for auto-indexing.
	 * This ensures new content gets indexed without manual intervention.
	 *
	 * @param array $topic Topic data
	 * @param array $forum Forum data
	 */
	public function on_topic_add( $topic, $forum ) {
		$topicid = wpfval( $topic, 'topicid' );
		$status = intval( wpfval( $topic, 'status' ) );

		// Only auto-index approved topics (status = 0)
		if ( $topicid && $status === 0 ) {
			// Queue for auto-indexing (background processing)
			WPF()->vector_storage->queue_topic_for_auto_indexing( $topicid );

			$this->log_info( 'topic_queued_on_create', [
				'topicid' => $topicid,
				'status'  => $status,
			] );
		}
	}

	/**
	 * Callback for topic approve action
	 *
	 * When an unapproved topic is approved by admin, queue it for auto-indexing.
	 * Also updates the indexed hash and invalidates any existing indexed status.
	 *
	 * @param array $topic Topic data
	 */
	public function on_topic_approve( $topic ) {
		$topicid = wpfval( $topic, 'topicid' );
		if ( $topicid ) {
			// Update indexed hash for cache invalidation
			$this->update_topic_indexed_hash( $topicid );

			// Set local = 0 and cloud = 0 to ensure fresh indexing
			$this->invalidate_topic_indexed_status( $topicid, 'topic_approved' );

			// Queue for auto-indexing (background processing)
			WPF()->vector_storage->queue_topic_for_auto_indexing( $topicid );

			$this->log_info( 'topic_queued_on_approve', [
				'topicid' => $topicid,
			] );
		}
	}

	/**
	 * Callback for topic delete action
	 *
	 * When a topic is deleted:
	 * 1. Delete all local embeddings for the topic
	 * 2. Clear topic summary cache
	 *
	 * Note: Translation caches for individual posts are already cleared via
	 * on_post_delete() which fires for each post before the topic is deleted.
	 *
	 * Note: Cloud vectors are cleaned up automatically - either during the next
	 * sync (comparison detects missing topic) or when tenant is disconnected.
	 * We don't need to reset topic indexed status since the topic row is deleted.
	 *
	 * @param array $topic Topic data
	 */
	public function on_topic_delete( $topic ) {
		$topicid = wpfval( $topic, 'topicid' );
		if ( ! $topicid ) {
			return;
		}

		// 1. Delete all local embeddings for this topic
		if ( WPF()->vector_storage ) {
			WPF()->vector_storage->delete_topic_embeddings( $topicid );
		}

		// 2. Clear topic summary cache
		$this->clear_topic_summary_cache( $topicid );

		// 3. Clear indexed counts transient
		delete_transient( 'wpforo_ai_indexed_counts' );

		$this->log_info( 'topic_deleted_cleanup', [
			'topicid' => $topicid,
		] );
	}

	/**
	 * Daily cron handler for pending topics indexing
	 *
	 * Runs once a day to find topics with local=0 or cloud=0
	 * (based on current storage mode) and queue them for indexing.
	 *
	 * This catches:
	 * - Topics that failed to index previously
	 * - Topics added when cron was missed
	 * - Topics that need re-indexing after content changes
	 */
	public function cron_pending_topics_indexing() {
		if ( ! $this->is_service_available() ) {
			return;
		}

		$result = WPF()->vector_storage->cron_process_pending_topics();

		$this->log_info( 'daily_pending_topics_processed', $result );
	}

	/**
	 * Invalidate cloud indexed status for a topic
	 *
	 * Sets cloud = 0 for a topic that was previously indexed (cloud = 1).
	 * This triggers force re-indexing when the topic is next indexed,
	 * ensuring old vectors are deleted before re-embedding.
	 *
	 * Called when a new reply is added to the topic (structural change).
	 * NOT called for edits/deletes - those use per-reply deduplication.
	 *
	 * @param int $topicid Topic ID
	 */
	private function invalidate_topic_cloud_status( $topicid ) {
		global $wpdb;

		// Only update if topic is currently indexed in cloud (cloud = 1)
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE `" . WPF()->tables->topics . "`
				SET `cloud` = 0
				WHERE `topicid` = %d AND `cloud` = 1",
				$topicid
			)
		);

		if ( $updated > 0 ) {
			$this->log_info( 'topic_cloud_status_invalidated', [
				'topicid' => $topicid,
				'reason'  => 'post_added'
			] );
		}
	}

	/**
	 * Invalidate both local and cloud indexed status for a topic
	 *
	 * Sets local = 0 and cloud = 0 for a topic.
	 * This triggers force re-indexing for both local and cloud storage
	 * when the topic is next indexed.
	 *
	 * Called when:
	 * - A new approved post is added to the topic
	 * - An unapproved post is approved
	 *
	 * @param int    $topicid Topic ID
	 * @param string $reason  Reason for invalidation (for logging)
	 */
	private function invalidate_topic_indexed_status( $topicid, $reason = 'unknown' ) {
		global $wpdb;

		// Set both local and cloud to 0 to trigger re-indexing
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE `" . WPF()->tables->topics . "`
				SET `local` = 0, `cloud` = 0
				WHERE `topicid` = %d AND (`local` = 1 OR `cloud` = 1)",
				$topicid
			)
		);

		if ( $updated > 0 ) {
			$this->log_info( 'topic_indexed_status_invalidated', [
				'topicid' => $topicid,
				'reason'  => $reason
			] );
		}
	}

	/**
	 * Update indexed hash for a topic
	 *
	 * Recalculates the indexed hash (MD5 of topicid_postcount).
	 * This invalidates the summarization cache when posts change.
	 *
	 * Called when:
	 * - A new reply is added to the topic
	 * - An existing post is edited
	 * - A post is deleted
	 *
	 * @param int $topicid Topic ID
	 */
	private function update_topic_indexed_hash( $topicid ) {
		global $wpdb;

		// Update indexed hash based on current post count
		// Hash formula: MD5(topicid + '_' + posts)
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE `" . WPF()->tables->topics . "`
				SET `indexed` = MD5(CONCAT(topicid, '_', posts))
				WHERE `topicid` = %d",
				$topicid
			)
		);

		if ( $updated > 0 ) {
			$this->log_info( 'topic_indexed_hash_updated', [
				'topicid' => $topicid,
			] );
			// Clear topic cache to reflect updated hash
			wpforo_clean_cache( 'topic', $topicid );
		}
	}

	// =========================================================================
	// AI TRANSLATION METHODS
	// =========================================================================

	/**
	 * Render translation button in post content
	 *
	 * Displays a "Translate" dropdown button that allows users to translate
	 * post content to their preferred language.
	 *
	 * @param array $post Post data
	 * @return void
	 */
	public function render_translation_button( $post ) {
		// Check if AI service is available and translation is available
		if ( ! $this->is_service_available() ) {
			return;
		}

		// Check if translation is enabled in settings
		if ( ! wpfval( WPF()->settings->ai, 'translation' ) ) {
			return;
		}

		// Check usergroup permission
		if ( ! WPF()->usergroup->can( 'ai_translation' ) ) {
			return;
		}

		// Check if we have the post ID
		$post_id = wpfval( $post, 'postid' );
		if ( empty( $post_id ) ) {
			return;
		}

		// Get available translation languages
		$languages = $this->get_available_translation_languages();

		// Render the translation dropdown button
		?>
		<div class="wpf-ai-translate-wrapper" data-postid="<?php echo esc_attr( $post_id ); ?>">
			<div class="wpf-ai-translate-btn" title="<?php echo esc_attr( wpforo_phrase( 'Translate this post', false ) ); ?>">
				<span class="wpf-ai-translate-label"><?php echo esc_html( wpforo_phrase( 'Translate', false ) ); ?></span>
				<span class="wpf-ai-translate-arrow">▼</span>
			</div>
			<div class="wpf-ai-translate-dropdown">
				<?php foreach ( $languages as $code => $name ) : ?>
					<div class="wpf-ai-translate-option" data-lang="<?php echo esc_attr( $code ); ?>">
						<?php echo esc_html( $name ); ?>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="wpf-ai-translate-original" style="display:none;">
				<span class="wpf-ai-translate-label"><?php echo esc_html( wpforo_phrase( 'Show original', false ) ); ?></span>
			</div>
			<div class="wpf-ai-translate-loading" style="display:none;">
				<span class="wpf-ai-translate-spinner"></span>
				<span><?php echo esc_html( wpforo_phrase( 'Translating...', false ) ); ?></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Get available translation languages
	 *
	 * @return array Associative array of language code => language name
	 */
	public function get_available_translation_languages() {
		$languages = [
			'en'    => wpforo_phrase( 'English', false ),
			'es'    => wpforo_phrase( 'Spanish', false ),
			'fr'    => wpforo_phrase( 'French', false ),
			'de'    => wpforo_phrase( 'German', false ),
			'it'    => wpforo_phrase( 'Italian', false ),
			'pt'    => wpforo_phrase( 'Portuguese', false ),
			'ru'    => wpforo_phrase( 'Russian', false ),
			'zh'    => wpforo_phrase( 'Chinese', false ),
			'ja'    => wpforo_phrase( 'Japanese', false ),
			'ko'    => wpforo_phrase( 'Korean', false ),
			'ar'    => wpforo_phrase( 'Arabic', false ),
			'hi'    => wpforo_phrase( 'Hindi', false ),
			'nl'    => wpforo_phrase( 'Dutch', false ),
			'pl'    => wpforo_phrase( 'Polish', false ),
			'tr'    => wpforo_phrase( 'Turkish', false ),
			'vi'    => wpforo_phrase( 'Vietnamese', false ),
			'th'    => wpforo_phrase( 'Thai', false ),
			'sv'    => wpforo_phrase( 'Swedish', false ),
			'da'    => wpforo_phrase( 'Danish', false ),
			'fi'    => wpforo_phrase( 'Finnish', false ),
			'no'    => wpforo_phrase( 'Norwegian', false ),
			'cs'    => wpforo_phrase( 'Czech', false ),
			'hu'    => wpforo_phrase( 'Hungarian', false ),
			'ro'    => wpforo_phrase( 'Romanian', false ),
			'el'    => wpforo_phrase( 'Greek', false ),
			'he'    => wpforo_phrase( 'Hebrew', false ),
			'id'    => wpforo_phrase( 'Indonesian', false ),
			'ms'    => wpforo_phrase( 'Malay', false ),
			'uk'    => wpforo_phrase( 'Ukrainian', false ),
			'bg'    => wpforo_phrase( 'Bulgarian', false ),
			'hr'    => wpforo_phrase( 'Croatian', false ),
			'sk'    => wpforo_phrase( 'Slovak', false ),
			'sl'    => wpforo_phrase( 'Slovenian', false ),
			'sr'    => wpforo_phrase( 'Serbian', false ),
			'lt'    => wpforo_phrase( 'Lithuanian', false ),
			'lv'    => wpforo_phrase( 'Latvian', false ),
			'et'    => wpforo_phrase( 'Estonian', false ),
		];

		return apply_filters( 'wpforo_ai_translation_languages', $languages );
	}

	/**
	 * Translate content via AI API
	 *
	 * @param string $content HTML content to translate
	 * @param string $target_language Target language code (e.g., 'es', 'fr', 'de')
	 * @return array|WP_Error Translated content or error
	 */
	public function translate_content( $content, $target_language ) {
		if ( empty( $content ) ) {
			return new \WP_Error( 'empty_content', wpforo_phrase( 'Content cannot be empty', false ) );
		}

		if ( empty( $target_language ) ) {
			return new \WP_Error( 'empty_language', wpforo_phrase( 'Target language is required', false ) );
		}

		// Get language name for API
		$languages = $this->get_available_translation_languages();
		$language_name = isset( $languages[ $target_language ] ) ? $languages[ $target_language ] : $target_language;

		$data = [
			'content'         => $content,
			'target_language' => $language_name,
		];

		// Add quality parameter from settings (for translation model selection)
		$translation_quality = wpfval( WPF()->settings->ai, 'translation_quality' );
		if ( ! empty( $translation_quality ) ) {
			$data['quality'] = sanitize_text_field( $translation_quality );
		}

		$response = $this->post( '/translate', $data );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'translation_failed', $response->get_error_message() );
			return $response;
		}

		$this->log_info( 'translation_completed', [
			'target_language' => $target_language,
			'content_length'  => strlen( $content ),
		] );

		return $response;
	}

	/**
	 * AJAX handler for content translation
	 *
	 * @return void
	 */
	public function ajax_translate_content() {
		// Track start time for logging
		$_log_start_time = microtime( true );

		// Verify nonce
		if ( ! wp_verify_nonce( wpfval( $_POST, 'nonce' ), 'wpforo_ai_translate' ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Security check failed', false )
			], 403 );
		}

		// Note: Rate limit check moved after cache check
		// Cached content should bypass rate limits since it doesn't use API resources

		// Check if AI service is available
		if ( ! $this->is_service_available() ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'AI service is not available', false )
			], 403 );
		}

		// Check if translation is enabled in settings
		if ( ! wpfval( WPF()->settings->ai, 'translation' ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Translation feature is disabled', false )
			], 403 );
		}

		// Check usergroup permission
		if ( ! WPF()->usergroup->can( 'ai_translation' ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'You do not have permission to use this feature', false )
			], 403 );
		}

		// Get post ID and validate
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		if ( ! $post_id ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Invalid post ID', false )
			], 400 );
		}

		// Get target language
		$target_language = sanitize_text_field( wpfval( $_POST, 'language' ) );
		if ( empty( $target_language ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Target language is required', false )
			], 400 );
		}

		// Get post content from database
		$post = wpforo_post( $post_id );
		if ( empty( $post ) || empty( $post['body'] ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Post not found', false )
			], 404 );
		}

		// Get the rendered HTML content using output buffering
		// (wpforo_content echoes instead of returning)
		ob_start();
		wpforo_content( $post );
		$content = ob_get_clean();

		if ( empty( trim( $content ) ) ) {
			// Fallback to raw body content
			$content = $post['body'];
		}

		// Build cache key from postid + language
		$cache_key = $this->build_translate_cache_key( $post_id, $target_language );

		// Check cache first
		$cached_result = $this->get_ai_cache( self::CACHE_TYPE_TRANSLATE, $cache_key );
		if ( $cached_result ) {
			// Log cached translation
			if ( isset( WPF()->ai_logs ) && WPF()->ai_logs ) {
				WPF()->ai_logs->log( [
					'action_type'      => AILogs::ACTION_TRANSLATION,
					'credits_used'     => 0,
					'status'           => AILogs::STATUS_CACHED,
					'content_type'     => 'post',
					'content_id'       => $post_id,
					'request_summary'  => sprintf( 'Translate post #%d to %s', $post_id, $target_language ),
					'response_summary' => 'Cached translation',
					'duration_ms'      => (int) ( ( microtime( true ) - $_log_start_time ) * 1000 ),
				] );
			}
			// Return cached translation (no credits used)
			wp_send_json_success( [
				'translated_content' => wpfval( $cached_result, 'translated_content' ) ?: '',
				'source_language'    => wpfval( $cached_result, 'source_language' ) ?: 'auto',
				'target_language'    => $target_language,
				'credits_used'       => 0,
				'cached'             => true,
			] );
		}

		// Cache miss - check rate limit before making API call
		// Rate limit is only checked for non-cached requests that consume API resources
		$this->check_rate_limit( 'translation' );

		// Translate the content (cache miss)
		$result = $this->translate_content( $content, $target_language );

		if ( is_wp_error( $result ) ) {
			// Log error
			if ( isset( WPF()->ai_logs ) && WPF()->ai_logs ) {
				WPF()->ai_logs->log( [
					'action_type'      => AILogs::ACTION_TRANSLATION,
					'credits_used'     => 0,
					'status'           => AILogs::STATUS_ERROR,
					'content_type'     => 'post',
					'content_id'       => $post_id,
					'request_summary'  => sprintf( 'Translate post #%d to %s', $post_id, $target_language ),
					'error_message'    => $result->get_error_message(),
					'duration_ms'      => (int) ( ( microtime( true ) - $_log_start_time ) * 1000 ),
				] );
			}
			wp_send_json_error( [
				'message' => $result->get_error_message()
			], 500 );
		}

		$credits_used = wpfval( $result, 'credits_used' ) ?: 1;

		// Log successful translation
		if ( isset( WPF()->ai_logs ) && WPF()->ai_logs ) {
			WPF()->ai_logs->log( [
				'action_type'      => AILogs::ACTION_TRANSLATION,
				'credits_used'     => $credits_used,
				'status'           => AILogs::STATUS_SUCCESS,
				'content_type'     => 'post',
				'content_id'       => $post_id,
				'request_summary'  => sprintf( 'Translate post #%d to %s', $post_id, $target_language ),
				'response_summary' => sprintf( 'Translated from %s', wpfval( $result, 'source_language' ) ?: 'auto' ),
				'duration_ms'      => (int) ( ( microtime( true ) - $_log_start_time ) * 1000 ),
			] );
		}

		// Cache only essential fields (exclude model_used, credits_remaining, credits_used)
		$cache_data = [
			'translated_content' => wpfval( $result, 'translated_content' ) ?: '',
			'source_language'    => wpfval( $result, 'source_language' ) ?: 'auto',
		];
		$this->set_ai_cache( self::CACHE_TYPE_TRANSLATE, $cache_key, $cache_data, 0, $post_id );

		// Return translated content
		wp_send_json_success( [
			'translated_content' => wpfval( $result, 'translated_content' ) ?: '',
			'source_language'    => wpfval( $result, 'source_language' ) ?: 'auto',
			'target_language'    => $target_language,
			'credits_used'       => $credits_used,
			'cached'             => false,
		] );
	}

	// =========================================================================
	// TOPIC SUMMARIZATION
	// =========================================================================

	/**
	 * Summarize a forum topic using AI
	 *
	 * Sends topic content and replies to the AI summarization API.
	 * If indexed_hash is provided, backend loads posts from cloud storage.
	 * If posts are provided, they are sent directly to the API.
	 *
	 * @param int $topicid Topic ID to summarize
	 * @param array|null $posts Array of post data (post_id, author, content, is_first_post) or null if using indexed_hash
	 * @param string $style Summary style (compact, structured, conversational, detailed, minimal)
	 * @param string $last_modified Topic last modified timestamp for cache key
	 * @param string $indexed_hash MD5 hash of indexed topic (if posts are stored in cloud)
	 * @return array|WP_Error Summary result or error
	 */
	public function summarize_topic( $topicid, $posts = null, $style = 'detailed', $last_modified = '', $indexed_hash = '', $language = '' ) {
		if ( empty( $topicid ) ) {
			return new \WP_Error( 'empty_topicid', wpforo_phrase( 'Topic ID is required', false ) );
		}

		// Must have either posts or indexed_hash
		if ( ( empty( $posts ) || ! is_array( $posts ) ) && empty( $indexed_hash ) ) {
			return new \WP_Error( 'empty_posts', wpforo_phrase( 'Topic posts or indexed_hash are required', false ) );
		}

		// Get topic title
		$topic = wpforo_topic( $topicid );
		if ( empty( $topic ) || empty( $topic['title'] ) ) {
			return new \WP_Error( 'topic_not_found', wpforo_phrase( 'Topic not found', false ) );
		}

		$data = [
			'topic_id'    => (int) $topicid,
			'topic_title' => sanitize_text_field( $topic['title'] ),
			'style'       => sanitize_text_field( $style ),
		];

		// If indexed_hash is provided, backend will load posts from cloud storage
		// Otherwise, send posts directly in the request
		if ( ! empty( $indexed_hash ) ) {
			$data['indexed_hash'] = sanitize_text_field( $indexed_hash );
		}

		if ( ! empty( $posts ) && is_array( $posts ) ) {
			$data['posts'] = $posts;
		}

		// Add quality parameter from settings
		$summary_quality = wpfval( WPF()->settings->ai, 'topic_summary_quality' );
		if ( ! empty( $summary_quality ) ) {
			$data['quality'] = sanitize_text_field( $summary_quality );
		}

		// Add last_modified for cache key generation on server side
		if ( ! empty( $last_modified ) ) {
			$data['last_modified'] = sanitize_text_field( $last_modified );
		}

		// Add language for summary generation
		if ( ! empty( $language ) ) {
			$data['language'] = sanitize_text_field( $language );
		}

		$response = $this->post( '/summarize', $data );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'topic_summary_failed', $response->get_error_message() );
			return $response;
		}

		$this->log_info( 'topic_summary_completed', [
			'topic_id'      => $topicid,
			'reply_count'   => is_array( $posts ) ? count( $posts ) : 0,
			'style'         => $style,
			'indexed_hash'  => ! empty( $indexed_hash ),
		] );

		return $response;
	}

	/**
	 * Get available summary styles
	 *
	 * @return array Style ID => Style name mapping
	 */
	public function get_available_summary_styles() {
		return [
			'compact'        => wpforo_phrase( 'Compact with Key Points', false ),
			'structured'     => wpforo_phrase( 'Structured with Sections', false ),
			'conversational' => wpforo_phrase( 'Conversational Flow', false ),
			'detailed'       => wpforo_phrase( 'Short Summary + Details', false ),
			'minimal'        => wpforo_phrase( 'Minimal and Clean', false ),
		];
	}

	/**
	 * AJAX handler for topic summarization
	 *
	 * @return void
	 */
	public function ajax_summarize_topic() {
		// Track start time for logging
		$_log_start_time = microtime( true );

		// Verify nonce
		if ( ! wp_verify_nonce( wpfval( $_POST, 'nonce' ), 'wpforo_ai_summarize_topic' ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Security check failed', false )
			], 403 );
		}

		// Note: Rate limit check moved after cache check
		// Cached summaries should bypass rate limits since they don't use API resources

		// Check if AI service is available
		if ( ! $this->is_service_available() ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'AI service is not available', false )
			], 403 );
		}

		// Check if topic summarization is enabled in settings
		if ( ! wpfval( WPF()->settings->ai, 'topic_summary' ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Topic summarization feature is disabled', false )
			], 403 );
		}

		// Check usergroup permission
		if ( ! WPF()->usergroup->can( 'ai_summary' ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'You do not have permission to use this feature', false )
			], 403 );
		}

		// Get topic ID and validate
		$topicid = isset( $_POST['topicid'] ) ? (int) $_POST['topicid'] : 0;
		if ( ! $topicid ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Invalid topic ID', false )
			], 400 );
		}

		// Get topic data
		$topic = wpforo_topic( $topicid );
		if ( empty( $topic ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Topic not found', false )
			], 404 );
		}

		// Get summary style from settings or request
		$style = sanitize_text_field( wpfval( $_POST, 'style' ) );
		if ( empty( $style ) ) {
			$style = wpfval( WPF()->settings->ai, 'topic_summary_style' ) ?: 'detailed';
		}

		// Check if topic is indexed in CLOUD storage (posts stored in cloud)
		// Only use cloud loading when:
		// 1. We're in cloud storage mode
		// 2. The topic's `cloud` column is 1 (indexed in cloud)
		// 3. The indexed hash exists (for backend to identify the topic)
		$indexed_hash = $topic['indexed'] ?? '';
		$is_cloud_indexed = WPF()->vector_storage && WPF()->vector_storage->is_cloud_mode()
			&& ! empty( $topic['cloud'] ) && (int) $topic['cloud'] === 1
			&& ! empty( $indexed_hash );

		$posts = null;
		$total_posts_count = 0;
		$posts_limited = false;
		$max_posts = 220; // first 20 + last 200

		if ( $is_cloud_indexed ) {
			// Topic is indexed in cloud - backend will load posts from cloud storage
			// Use topic's posts count for display purposes
			$total_posts_count = (int) ( $topic['posts'] ?? 0 );
			$reply_count = $total_posts_count; // All posts available in cloud
		} else {
			// Clear indexed_hash if not using cloud - don't send to API
			$indexed_hash = '';
			// Topic not indexed - load posts from WordPress and send to API
			$items_count = 0;
			$posts_data = WPF()->post->get_posts( [ 'topicid' => $topicid ], $items_count );
			if ( empty( $posts_data ) ) {
				wp_send_json_error( [
					'message' => wpforo_phrase( 'No posts found in this topic', false )
				], 404 );
			}

			// Prepare posts array for API
			$posts = [];
			$first_post_id = $topic['first_postid'] ?? 0;

			foreach ( $posts_data as $post ) {
				// Get rendered HTML content using output buffering
				ob_start();
				wpforo_content( $post );
				$content = ob_get_clean();

				if ( empty( trim( $content ) ) ) {
					$content = $post['body'] ?? '';
				}

				$posts[] = [
					'post_id'       => (int) $post['postid'],
					'author'        => $this->get_user_display_name( $post['userid'] ?? 0 ),
					'content'       => $content,
					'created_at'    => $post['created'] ?? '',
					'is_first_post' => ( (int) $post['postid'] === (int) $first_post_id ),
				];
			}

			// Store total count before limiting
			$total_posts_count = count( $posts );

			// Limit posts for large topics: first 20 + last 200
			// This captures the original context and most recent discussion
			$first_posts_limit = 20;
			$last_posts_limit  = 200;

			if ( $total_posts_count > $max_posts ) {
				$first_posts = array_slice( $posts, 0, $first_posts_limit );
				$last_posts  = array_slice( $posts, -$last_posts_limit );
				$posts       = array_merge( $first_posts, $last_posts );
				$posts_limited = true;
			}

			$reply_count = count( $posts );
		}

		$last_modified = $topic['modified'] ?? $topic['created'] ?? '';

		// Get quality and language for cache key
		$quality = wpfval( WPF()->settings->ai, 'topic_summary_quality' ) ?: 'advanced';
		$language_code = sanitize_text_field( wpfval( $_POST, 'language' ) ) ?: '';
		$language = $this->get_user_language( $language_code, 'topic_summary_language' );

		// Build cache key from topicid + reply_count + last_modified + quality + style + language
		$cache_key = $this->build_topic_summary_cache_key( $topicid, $reply_count, $last_modified, $quality, $style, $language );

		// Check cache first
		$cached_result = $this->get_ai_cache( self::CACHE_TYPE_TOPIC_SUMMARY, $cache_key );
		if ( $cached_result ) {
			// Log cached summary
			if ( isset( WPF()->ai_logs ) && WPF()->ai_logs ) {
				WPF()->ai_logs->log( [
					'action_type'      => AILogs::ACTION_TOPIC_SUMMARY,
					'credits_used'     => 0,
					'status'           => AILogs::STATUS_CACHED,
					'content_type'     => 'topic',
					'content_id'       => $topicid,
					'topicid'          => $topicid,
					'forumid'          => $topic['forumid'] ?? null,
					'request_summary'  => sprintf( 'Summarize topic: %s', wp_trim_words( $topic['title'], 10 ) ),
					'response_summary' => 'Cached summary',
					'duration_ms'      => (int) ( ( microtime( true ) - $_log_start_time ) * 1000 ),
				] );
			}

			// Return cached summary (no credits used)
			// Process link markers to convert [[#POST_ID]] to clickable links
			$cached_summary = wpfval( $cached_result, 'summary' ) ?: '';
			$cached_summary = $this->replace_summary_link_markers( $cached_summary, $topicid );

			wp_send_json_success( [
				'summary'           => $cached_summary,
				'style'             => wpfval( $cached_result, 'style' ) ?: $style,
				'topic_id'          => $topicid,
				'reply_count'       => $reply_count,
				'total_posts_count' => $total_posts_count,
				'posts_limited'     => $posts_limited,
				'credits_used'      => 0,
				'cached'            => true,
				'from_s3'           => $is_cloud_indexed,
			] );
		}

		// Cache miss - check rate limit before making API call
		// Rate limit is only checked for non-cached requests that consume API resources
		$this->check_rate_limit( 'summarization' );

		// Summarize the topic (cache miss) - pass indexed_hash if available
		$result = $this->summarize_topic( $topicid, $posts, $style, $last_modified, $indexed_hash, $language );

		if ( is_wp_error( $result ) ) {
			// Log error
			if ( isset( WPF()->ai_logs ) && WPF()->ai_logs ) {
				WPF()->ai_logs->log( [
					'action_type'      => AILogs::ACTION_TOPIC_SUMMARY,
					'credits_used'     => 0,
					'status'           => AILogs::STATUS_ERROR,
					'content_type'     => 'topic',
					'content_id'       => $topicid,
					'topicid'          => $topicid,
					'forumid'          => $topic['forumid'] ?? null,
					'request_summary'  => sprintf( 'Summarize topic: %s', wp_trim_words( $topic['title'], 10 ) ),
					'error_message'    => $result->get_error_message(),
					'duration_ms'      => (int) ( ( microtime( true ) - $_log_start_time ) * 1000 ),
				] );
			}
			wp_send_json_error( [
				'message' => $result->get_error_message()
			], 500 );
		}

		// Get raw summary and store in cache (keep raw with link markers for re-processing)
		$raw_summary = wpfval( $result, 'summary' ) ?: '';
		// Strip markdown code fence wrappers (```html ... ```) that LLMs sometimes add around HTML output
		$raw_summary = preg_replace( '/^\s*```\w*\s*\n([\s\S]*?)\n\s*```\s*$/s', '$1', $raw_summary );
		$credits_used = wpfval( $result, 'credits_used' ) ?: 1;

		// Log success
		if ( isset( WPF()->ai_logs ) && WPF()->ai_logs ) {
			WPF()->ai_logs->log( [
				'action_type'      => AILogs::ACTION_TOPIC_SUMMARY,
				'credits_used'     => $credits_used,
				'status'           => AILogs::STATUS_SUCCESS,
				'content_type'     => 'topic',
				'content_id'       => $topicid,
				'topicid'          => $topicid,
				'forumid'          => $topic['forumid'] ?? null,
				'request_summary'  => sprintf( 'Summarize topic: %s (%d posts)', wp_trim_words( $topic['title'], 10 ), $reply_count ),
				'response_summary' => sprintf( 'Generated %s-style summary', $style ),
				'duration_ms'      => (int) ( ( microtime( true ) - $_log_start_time ) * 1000 ),
			] );
		}

		// Cache only essential fields (store raw with link markers)
		// Note: Using postid parameter to store topicid for topic summary cache
		$cache_data = [
			'summary' => $raw_summary,
			'style'   => wpfval( $result, 'style' ) ?: $style,
		];
		$this->set_ai_cache( self::CACHE_TYPE_TOPIC_SUMMARY, $cache_key, $cache_data, self::CACHE_TTL, $topicid );

		// Process link markers to convert [[#POST_ID]] to clickable links
		$processed_summary = $this->replace_summary_link_markers( $raw_summary, $topicid );

		// Return summary with clickable links
		wp_send_json_success( [
			'summary'           => $processed_summary,
			'style'             => wpfval( $result, 'style' ) ?: $style,
			'topic_id'          => $topicid,
			'reply_count'       => $reply_count,
			'total_posts_count' => $total_posts_count,
			'posts_limited'     => $posts_limited,
			'credits_used'      => $credits_used,
			'cached'            => false,
			'from_s3'           => $is_cloud_indexed,
		] );
	}

	/**
	 * Render topic summary button in the head-bar (next to Subscribe button)
	 *
	 * @param array $forum Forum data
	 * @param array $topic Topic data
	 * @param array $posts Posts data
	 * @return void
	 */
	public function render_topic_summary_button( $forum, $topic, $posts ) {
		// Check if topic summarization is enabled
		if ( ! wpfval( WPF()->settings->ai, 'topic_summary' ) ) {
			return;
		}

		// Check if AI service is available (connected + active/trial subscription)
		if ( ! $this->is_service_available() ) {
			return;
		}

		// Check usergroup permission
		if ( ! WPF()->usergroup->can( 'ai_summary' ) ) {
			return;
		}

		$topicid = $topic['topicid'] ?? 0;
		if ( ! $topicid ) {
			return;
		}

		// Get the number of replies (posts_count includes the original post, so replies = posts_count - 1)
		$posts_count = $topic['posts'] ?? count( $posts );
		$replies_count = max( 0, $posts_count - 1 );

		// Check minimum replies setting (default 1 = at least one reply required)
		$min_replies = intval( wpfval( WPF()->settings->ai, 'topic_summary_min_replies' ) ?? 1 );
		if ( $replies_count < $min_replies ) {
			return;
		}

		$nonce = wp_create_nonce( 'wpforo_ai_summarize_topic' );
		$button_text = wpforo_phrase( 'Summarize Topic', false );

		?>
		<span class="wpf-ai-summarize-btn wpf-button-outlined"
			data-topicid="<?php echo esc_attr( $topicid ); ?>"
			data-nonce="<?php echo esc_attr( $nonce ); ?>"
			title="<?php echo esc_attr( $button_text ); ?>">
			<i class="fa-solid fa-wand-magic-sparkles"></i>&nbsp; <?php echo esc_html( $button_text ); ?>
		</span>
		<?php
	}

	/**
	 * Standalone wrapper for rendering topic summary container
	 * Called via wpforo_template_post_head_bar action hook
	 *
	 * @param array $forum Forum data
	 * @param array $topic Topic data
	 * @param array $posts Posts data
	 * @return void
	 */
	public function render_topic_summary_container_standalone( $forum, $topic, $posts ) {
		$topicid = $topic['topicid'] ?? 0;
		$this->render_topic_summary_container( $topicid );
	}

	/**
	 * Render the topic summary container (slide-down area under head-bar)
	 *
	 * @param int $topicid Topic ID
	 * @return void
	 */
	public function render_topic_summary_container( $topicid = 0 ) {
		if ( ! $topicid ) {
			$topic = WPF()->current_object['topic'] ?? [];
			$topicid = $topic['topicid'] ?? 0;
		}

		if ( ! $topicid ) {
			return;
		}

		// Check if topic summarization is enabled
		if ( ! wpfval( WPF()->settings->ai, 'topic_summary' ) ) {
			return;
		}

		// Check if AI service is available (connected + active/trial subscription)
		if ( ! $this->is_service_available() ) {
			return;
		}

		// Check usergroup permission
		if ( ! WPF()->usergroup->can( 'ai_summary' ) ) {
			return;
		}

		?>
		<div class="wpf-ai-summary-container" id="wpf-ai-summary-<?php echo esc_attr( $topicid ); ?>" data-topicid="<?php echo esc_attr( $topicid ); ?>" style="display: none;">
			<div class="wpf-ai-summary-loading" style="display: none;">
				<span class="wpf-ai-loading-stars">
					<span class="wpf-ai-star">&#10022;</span>
					<span class="wpf-ai-star">&#10022;</span>
					<span class="wpf-ai-star">&#10022;</span>
				</span>
				<span class="wpf-ai-loading-text"><?php wpforo_phrase( 'AI is analyzing the discussion...' ); ?></span>
			</div>
			<div class="wpf-ai-summary-content"></div>
			<div class="wpf-ai-summary-footer" style="display: none;">
				<span class="wpf-ai-summary-info">
					<span class="wpf-ai-summary-credits"></span>
				</span>
				<button type="button" class="wpf-ai-summary-close" title="<?php echo esc_attr( wpforo_phrase( 'Close', false ) ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler for topic suggestions
	 *
	 * Returns similar topics, related topics, and quick AI answers based on topic title.
	 * Used during topic creation to help users find existing discussions.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function ajax_get_topic_suggestions() {
		// Verify nonce (action name matches the AJAX action)
		if ( ! wp_verify_nonce( wpfval( $_POST, 'nonce' ), 'wpforo_ai_get_topic_suggestions' ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Security check failed', false )
			], 403 );
		}

		// Check rate limit (guests and users have daily limits, moderators exempt)
		$this->check_rate_limit( 'suggestions' );

		// Check if AI service is available
		if ( ! $this->is_service_available() ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'AI service is not available', false )
			], 403 );
		}

		// Check if topic suggestions feature is enabled
		if ( ! wpfval( WPF()->settings->ai, 'topic_suggestions' ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Topic suggestions feature is disabled', false )
			], 403 );
		}

		// Check usergroup permission
		if ( ! WPF()->usergroup->can( 'ai_suggestion' ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'You do not have permission to use this feature', false )
			], 403 );
		}

		// Check if user has API key configured
		$api_key = $this->get_api_key();
		if ( ! $api_key ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'AI service not configured', false )
			], 400 );
		}

		// Get and validate title
		$title = sanitize_text_field( wpfval( $_POST, 'title' ) );
		if ( empty( $title ) ) {
			wp_send_json_error( [
				'message' => wpforo_phrase( 'Topic title is required', false )
			], 400 );
		}

		// Get forum ID (optional, for context)
		$forumid = isset( $_POST['forumid'] ) ? (int) $_POST['forumid'] : 0;

		// Get settings for API request
		$quality = wpfval( WPF()->settings->ai, 'topic_suggestions_quality' ) ?: 'balanced';
		$show_related = wpfval( WPF()->settings->ai, 'topic_suggestions_show_related' );
		$show_answer = wpfval( WPF()->settings->ai, 'topic_suggestions_show_answer' );
		$max_similar = (int) ( wpfval( WPF()->settings->ai, 'topic_suggestions_max_similar' ) ?: 3 );
		$max_related = (int) ( wpfval( WPF()->settings->ai, 'topic_suggestions_max_related' ) ?: 3 );
		$similarity_threshold = (int) ( wpfval( WPF()->settings->ai, 'topic_suggestions_similarity' ) ?: 55 );
		$language = $this->get_user_language( null, 'topic_suggestions_language' );

		// Check if using local storage mode
		$is_local_mode = WPF()->vector_storage && WPF()->vector_storage->is_local_mode();

		// For local mode, use hybrid approach:
		// 1. Search local embeddings for similar topics
		// 2. Send results to cloud API for AI-generated related topics and quick answer
		if ( $is_local_mode ) {
			$result = $this->get_local_topic_suggestions(
				$title,
				$forumid,
				$max_similar,
				$similarity_threshold,
				$quality,
				(bool) $show_related,
				(bool) $show_answer,
				$max_related,
				$language
			);
			wp_send_json_success( $result );
			return;
		}

		// Cloud mode: Use the suggestions API endpoint
		// Build API request payload
		// Note: include_similar is always true - similar topics are required for the feature to work
		$payload = [
			'title'               => $title,
			'quality'             => $quality,
			'include_similar'     => true,
			'include_related'     => (bool) $show_related,
			'include_answer'      => (bool) $show_answer,
			'max_similar'         => max( 1, $max_similar ), // Ensure at least 1 similar topic
			'max_related'         => $max_related,
			'similarity_threshold' => $similarity_threshold / 100, // Convert percentage to decimal
			'language'            => $language,
		];

		// Add forum context if available
		if ( $forumid ) {
			$forum = wpforo_forum( $forumid );
			if ( ! empty( $forum['title'] ) ) {
				$payload['forum_context'] = $forum['title'];
			}
		}

		// Add forum access filtering (only show suggestions from forums user can access)
		// Returns null for admins or users with full access (no filtering needed)
		$accessible_forumids = $this->get_accessible_forumids();
		if ( $accessible_forumids !== null ) {
			$payload['accessible_forumids'] = $accessible_forumids;
		}

		// Make API request to suggestions endpoint
		$response = $this->post( '/suggestions/suggest', $payload );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( [
				'message' => $response->get_error_message()
			], 500 );
		}

		// Process and enrich results with topic URLs
		$result = [
			'similar_topics' => [],
			'related_topics' => [],
			'quick_answer'   => null,
			'credits_used'   => $response['credits_used'] ?? 0,
		];

		// Process similar topics
		if ( ! empty( $response['similar_topics'] ) && is_array( $response['similar_topics'] ) ) {
			foreach ( $response['similar_topics'] as $item ) {
				// Extract topic ID from content_id (format: topic_XX_post_YY or topic_XX_reply_YY)
				$content_id = $item['content_id'] ?? '';
				$topicid = 0;
				if ( preg_match( '/^topic_(\d+)_/', $content_id, $matches ) ) {
					$topicid = (int) $matches[1];
				}

				if ( $topicid ) {
					$topic = wpforo_topic( $topicid );
					if ( ! empty( $topic ) ) {
						$result['similar_topics'][] = [
							'topicid'    => $topicid,
							'title'      => $topic['title'] ?? '',
							'url'        => wpforo_topic( $topicid, 'url' ),
							'score'      => round( ( $item['score'] ?? 0 ) * 100 ),
							'replies'    => (int) ( $topic['posts'] ?? 0 ) - 1,
							'views'      => (int) ( $topic['views'] ?? 0 ),
							'snippet'    => $this->truncate_text( wp_strip_all_tags( $item['content'] ?? '' ), 150 ),
							'created'    => $topic['created'] ?? '',
						];
					}
				}
			}
		}

		// Process related topics - these are selected from existing indexed content
		// They contain 'title', 'reason', and 'content_id' (format: topic_XX_post_YY or topic_XX_reply_YY)
		if ( ! empty( $response['related_topics'] ) && is_array( $response['related_topics'] ) ) {
			foreach ( $response['related_topics'] as $item ) {
				// Extract topic ID from content_id (same pattern as similar_topics)
				$content_id = $item['content_id'] ?? '';
				$topicid = 0;
				if ( preg_match( '/^topic_(\d+)_/', $content_id, $matches ) ) {
					$topicid = (int) $matches[1];
				}

				$related_item = [
					'title'  => $item['title'] ?? '',
					'reason' => $item['reason'] ?? '',
				];

				// Add URL if we have a valid topic ID
				if ( $topicid ) {
					$related_item['topicid'] = $topicid;
					$related_item['url'] = wpforo_topic( $topicid, 'url' );
				}

				$result['related_topics'][] = $related_item;
			}
		}

		// Process quick answer
		// Note: API returns 'ai_insight' as truncated string and 'ai_insight_full' as full text
		if ( ! empty( $response['ai_insight'] ) || ! empty( $response['ai_insight_full'] ) ) {
			// Use ai_insight_full if available, otherwise use ai_insight
			$answer_text = ! empty( $response['ai_insight_full'] )
				? $response['ai_insight_full']
				: $response['ai_insight'];

			$result['quick_answer'] = [
				'text'     => wp_kses_post( $answer_text ),
				'sources'  => [],
				'caveat'   => wpforo_phrase( 'This is an AI-generated suggestion based on existing forum content. Please verify the information.', false ),
			];
		}

		// Deduplicate: Remove related topics that are already in similar topics
		if ( ! empty( $result['related_topics'] ) && ! empty( $result['similar_topics'] ) ) {
			$similar_topic_ids = array_column( $result['similar_topics'], 'topicid' );
			$result['related_topics'] = array_values( array_filter(
				$result['related_topics'],
				function( $related ) use ( $similar_topic_ids ) {
					return empty( $related['topicid'] ) || ! in_array( $related['topicid'], $similar_topic_ids, true );
				}
			) );
		}

		// Set has_suggestions flag - JS checks this to decide whether to show results or "no results" message
		$result['has_suggestions'] = ! empty( $result['similar_topics'] ) || ! empty( $result['related_topics'] ) || ! empty( $result['quick_answer'] );

		// Return success response
		wp_send_json_success( $result );
	}

	/**
	 * Get topic suggestions using hybrid approach (local search + cloud AI)
	 *
	 * For local storage mode, we use hybrid approach:
	 * 1. Search local embeddings for similar topics
	 * 2. Send results to cloud API for AI-generated related topics and quick answer
	 *
	 * @param string $title               Topic title to search for
	 * @param int    $forumid             Optional forum ID filter
	 * @param int    $max_similar         Maximum similar topics to return
	 * @param int    $similarity_threshold Minimum similarity percentage (0-100)
	 * @param string $quality             AI quality tier (fast, balanced, advanced, premium)
	 * @param bool   $show_related        Include related topics from AI
	 * @param bool   $show_answer         Include quick AI answer
	 * @param int    $max_related         Maximum related topics to return
	 * @return array Result with similar_topics, related_topics, quick_answer, credits_used
	 */
	private function get_local_topic_suggestions(
		$title,
		$forumid = 0,
		$max_similar = 3,
		$similarity_threshold = 70,
		$quality = 'balanced',
		$show_related = true,
		$show_answer = true,
		$max_related = 3,
		$language = ''
	) {
		$result = [
			'similar_topics' => [],
			'related_topics' => [],
			'quick_answer'   => null,
			'credits_used'   => 0,
			'storage_mode'   => 'hybrid',
		];

		// Build filters
		$filters = [];
		if ( $forumid > 0 ) {
			$filters['forumid'] = $forumid;
		}

		// Step 1: Search local embeddings
		// Fetch more results to have candidates for both similar and related topics
		$fetch_count = ( $max_similar + $max_related ) * 2;
		$search_results = WPF()->vector_storage->semantic_search( $title, $fetch_count, $filters );

		if ( is_wp_error( $search_results ) ) {
			\wpforo_ai_log( 'error', 'Local topic suggestions error: ' . $search_results->get_error_message(), 'Client' );
			$result['has_suggestions'] = false;
			return $result;
		}

		// Convert similarity threshold from percentage to decimal
		// Local cosine similarities are on a different scale (5-25%) than cloud scores (30-90%),
		// so apply 1/3 of the configured threshold for local mode with minimum of 15%
		$threshold_decimal = max( 0.15, ( $similarity_threshold / 100 ) / 3 );
		$related_threshold = max( 0.10, $threshold_decimal - 0.05 ); // 5% lower for related topics

		// Group by topic (take best match per topic)
		$by_topic = [];
		$results_array = $search_results['results'] ?? [];

		foreach ( $results_array as $item ) {
			$topicid = (int) ( $item['topic_id'] ?? 0 );
			if ( $topicid <= 0 ) {
				continue;
			}

			$similarity = (float) ( $item['similarity'] ?? $item['score'] ?? 0 );

			// Skip if below the lower threshold (for related topics)
			if ( $similarity < $related_threshold ) {
				continue;
			}

			// Keep only best match per topic
			if ( ! isset( $by_topic[ $topicid ] ) || $similarity > $by_topic[ $topicid ]['similarity'] ) {
				$by_topic[ $topicid ] = [
					'topicid'    => $topicid,
					'similarity' => $similarity,
					'snippet'    => $item['snippet'] ?? $item['content_preview'] ?? $item['content'] ?? '',
				];
			}
		}

		// Sort by similarity
		$all_topics = array_values( $by_topic );
		usort( $all_topics, function( $a, $b ) {
			return $b['similarity'] <=> $a['similarity'];
		} );

		// Enrich with full topic data and prepare for API
		$similar_topics_for_api = [];
		$similar_count = 0;

		foreach ( $all_topics as $item ) {
			$topic = wpforo_topic( $item['topicid'] );
			if ( empty( $topic ) ) {
				continue;
			}

			$topic_url = wpforo_topic( $item['topicid'], 'url' );
			$snippet = $this->truncate_text( wp_strip_all_tags( $item['snippet'] ), 500 );

			// Build topic data for API (format matches SimilarTopicInput model)
			$topic_data = [
				'content_id' => 'topic_' . $item['topicid'],
				'title'      => $topic['title'] ?? '',
				'url'        => $topic_url,
				'content'    => $snippet,
				'score'      => $item['similarity'], // Keep as decimal for API
			];

			// Add first_post and replies for AI to generate meaningful insights
			// Only include this data for topics that will be used for AI features
			if ( $show_related || $show_answer ) {
				$topic_author_id = (int) ( $topic['userid'] ?? 0 );

				// Get first post content
				$first_postid = $topic['first_postid'] ?? 0;
				if ( $first_postid ) {
					$first_post = wpforo_post( $first_postid );
					if ( $first_post ) {
						$first_post_content = $this->truncate_text( wp_strip_all_tags( $first_post['body'] ?? '' ), 500 );
						$topic_data['first_post'] = [
							'content' => $first_post_content,
							'author'  => $this->get_user_display_name( $first_post['userid'] ?? 0 ),
						];
					}
				}

				// Get replies (exclude topic author, sort by is_answer + likes)
				$replies_args = [
					'topicid'       => $item['topicid'],
					'is_first_post' => 0,
					'status'        => 0, // Only approved
					'orderby'       => 'is_answer DESC, likes DESC, created DESC',
					'row_count'     => 15, // Fetch more to filter
				];
				$topic_replies = WPF()->post->get_posts( $replies_args );

				if ( ! empty( $topic_replies ) && is_array( $topic_replies ) ) {
					$formatted_replies = [];
					foreach ( $topic_replies as $reply ) {
						// Skip topic author's replies
						if ( (int) ( $reply['userid'] ?? 0 ) === $topic_author_id ) {
							continue;
						}

						// Skip very short replies (less than 50 chars)
						$reply_content = wp_strip_all_tags( $reply['body'] ?? '' );
						if ( mb_strlen( $reply_content ) < 50 ) {
							continue;
						}

						$formatted_replies[] = [
							'content'   => $this->truncate_text( $reply_content, 500 ),
							'author'    => $this->get_user_display_name( $reply['userid'] ?? 0 ),
							'is_answer' => ! empty( $reply['is_answer'] ),
							'likes'     => (int) ( $reply['likes'] ?? 0 ),
							'votes'     => 0, // wpForo doesn't have votes, only likes
						];

						// Limit to 10 best replies
						if ( count( $formatted_replies ) >= 10 ) {
							break;
						}
					}

					if ( ! empty( $formatted_replies ) ) {
						$topic_data['replies'] = $formatted_replies;
					}
				}
			}

			$similar_topics_for_api[] = $topic_data;

			// Also build result format for similar topics (above threshold)
			if ( $item['similarity'] >= $threshold_decimal && $similar_count < $max_similar ) {
				$result['similar_topics'][] = [
					'topicid'    => $item['topicid'],
					'title'      => $topic['title'] ?? '',
					'url'        => $topic_url,
					'score'      => round( $item['similarity'] * 100 ),
					'replies'    => (int) ( $topic['posts'] ?? 0 ) - 1,
					'views'      => (int) ( $topic['views'] ?? 0 ),
					'snippet'    => $this->truncate_text( $snippet, 150 ),
					'created'    => $topic['created'] ?? '',
				];
				$similar_count++;
			}
		}

		// Step 2: If we have topics and need AI features, call cloud API
		if ( ! empty( $similar_topics_for_api ) && ( $show_related || $show_answer ) ) {
			// Collect similar topic IDs to exclude from related topics (prevents duplicates)
			$exclude_topic_ids = array_filter( array_column( $result['similar_topics'], 'topicid' ) );

			// Build API request payload for hybrid mode
			$payload = [
				'title'                => $title,
				'quality'              => $quality,
				'include_similar'      => false, // We already have similar topics from local search
				'include_related'      => (bool) $show_related,
				'include_answer'       => (bool) $show_answer,
				'max_similar'          => $max_similar,
				'max_related'          => $max_related,
				'similarity_threshold' => $similarity_threshold / 100, // Convert to decimal
				'similar_topics_input' => array_slice( $similar_topics_for_api, 0, 20 ), // Max 20 topics
				'exclude_topic_ids'    => array_values( $exclude_topic_ids ), // Exclude similar topics from related
				'language'             => $language,
			];

			// Make API request to suggestions endpoint
			$response = $this->post( '/suggestions/suggest', $payload );

			if ( ! is_wp_error( $response ) ) {
				// Get related topics from cloud AI response
				if ( $show_related && ! empty( $response['related_topics'] ) ) {
					foreach ( $response['related_topics'] as $related ) {
						$result['related_topics'][] = [
							'title'  => $related['title'] ?? '',
							'url'    => $related['url'] ?? '',
							'reason' => $related['reason'] ?? '',
						];
					}
				}

				// Get quick answer from cloud AI response
				// Note: API returns 'ai_insight' as truncated string and 'ai_insight_full' as full text
				if ( $show_answer && ( ! empty( $response['ai_insight'] ) || ! empty( $response['ai_insight_full'] ) ) ) {
					// Use ai_insight_full if available, otherwise use ai_insight
					$answer_text = ! empty( $response['ai_insight_full'] )
						? $response['ai_insight_full']
						: $response['ai_insight'];

					$result['quick_answer'] = [
						'text'     => wp_kses_post( $answer_text ),
						'sources'  => [],
						'caveat'   => wpforo_phrase( 'This is an AI-generated suggestion based on existing forum content. Please verify the information.', false ),
					];
				}

				// Add credits used by cloud AI
				$result['credits_used'] = $response['credits_used'] ?? 0;
			} else {
				// Log error but don't fail - we still have similar topics
				\wpforo_ai_log( 'error', 'Hybrid mode cloud API error: ' . $response->get_error_message(), 'Client' );
			}
		}

		// Deduplicate: Remove related topics that are already in similar topics
		// In hybrid mode, related_topics only have title/url/reason, so we match by URL
		if ( ! empty( $result['related_topics'] ) && ! empty( $result['similar_topics'] ) ) {
			$similar_topic_urls = array_filter( array_column( $result['similar_topics'], 'url' ) );

			$result['related_topics'] = array_values( array_filter(
				$result['related_topics'],
				function( $related ) use ( $similar_topic_urls ) {
					// Check by URL (the only common identifier in hybrid mode)
					return empty( $related['url'] ) || ! in_array( $related['url'], $similar_topic_urls, true );
				}
			) );
		}

		$result['has_suggestions'] = ! empty( $result['similar_topics'] ) ||
		                             ! empty( $result['related_topics'] ) ||
		                             ! empty( $result['quick_answer'] );

		return $result;
	}

	/**
	 * Truncate text to specified length
	 *
	 * @param string $text Text to truncate
	 * @param int $length Maximum length
	 * @param string $suffix Suffix to append if truncated
	 * @return string Truncated text
	 */
	private function truncate_text( $text, $length = 100, $suffix = '...' ) {
		$text = trim( $text );
		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}
		return mb_substr( $text, 0, $length ) . $suffix;
	}

	/**
	 * Filter to disable built-in wpForo topic suggestions when AI Topic Suggestions is enabled
	 *
	 * When AI Topic Suggestions is enabled in settings, the built-in wpForo suggested topics
	 * feature (which uses basic keyword matching) should be disabled to avoid duplication.
	 *
	 * @param bool $enabled Whether built-in suggestions are enabled
	 * @return bool False if AI Topic Suggestions is enabled, otherwise unchanged
	 */
	public function filter_built_in_suggestions( $enabled ) {
		// If AI Topic Suggestions is enabled, we have an API key, and user has permission, disable built-in suggestions
		if ( wpfval( WPF()->settings->ai, 'topic_suggestions' ) && $this->get_api_key() && WPF()->usergroup->can( 'ai_suggestion' ) ) {
			return false;
		}
		return $enabled;
	}

	/**
	 * Add AI suggestions panel as an HTML field right after the title field
	 *
	 * Uses the wpforo_form_fields filter to insert an HTML-type field containing
	 * the AI suggestions panel directly after the topic title field.
	 *
	 * @param array $fields 3D array of form fields [row][col][field]
	 * @return array Modified fields array
	 */
	public function add_ai_suggestions_after_title( $fields ) {
		// Check if topic suggestions feature is enabled
		if ( ! wpfval( WPF()->settings->ai, 'topic_suggestions' ) ) {
			return $fields;
		}

		// Check if AI service is available (connected + active/trial subscription)
		if ( ! $this->is_service_available() ) {
			return $fields;
		}

		// Check usergroup permission
		if ( ! WPF()->usergroup->can( 'ai_suggestion' ) ) {
			return $fields;
		}

		// Skip if editing existing topic (only show on new topic creation)
		// When editing, the title field has a pre-filled value from the existing topic
		foreach ( $fields as $row ) {
			foreach ( $row as $cols ) {
				foreach ( $cols as $field_key => $field ) {
					if ( $field_key === 'title' || ( is_array( $field ) && wpfval( $field, 'fieldKey' ) === 'title' ) ) {
						// If title field has a value, we're in edit mode
						if ( is_array( $field ) && ! empty( $field['value'] ) ) {
							return $fields;
						}
					}
				}
			}
		}

		// Get the suggestions panel HTML
		$panel_html = $this->get_ai_suggestions_panel_html();
		if ( empty( $panel_html ) ) {
			return $fields;
		}

		// Create the HTML field for the suggestions panel
		$suggestions_field = [
			'fieldKey'    => 'ai_suggestions',
			'name'        => 'ai_suggestions',
			'type'        => 'html',
			'isDefault'   => 1,
			'isRemovable' => 0,
			'isRequired'  => 0,
			'label'       => '',
			'html'        => $panel_html,
		];

		// Find title field and insert suggestions field after it
		$new_fields = [];
		foreach ( $fields as $row_key => $row ) {
			$new_row = [];
			foreach ( $row as $col_key => $cols ) {
				$new_col = [];
				foreach ( $cols as $field_key => $field ) {
					$new_col[ $field_key ] = $field;
					// Insert suggestions field right after title field
					if ( $field_key === 'title' || ( is_array( $field ) && wpfval( $field, 'fieldKey' ) === 'title' ) ) {
						$new_col['ai_suggestions'] = $suggestions_field;
					}
				}
				$new_row[ $col_key ] = $new_col;
			}
			$new_fields[ $row_key ] = $new_row;
		}

		return $new_fields;
	}

	/**
	 * Get the AI suggestions panel HTML
	 *
	 * Returns the HTML for the collapsible AI suggestions panel that displays
	 * similar topics, related topics, and quick AI answers.
	 *
	 * @return string Panel HTML
	 */
	private function get_ai_suggestions_panel_html() {
		// Get settings for suggestion config
		// Note: show_similar is not included - similar topics are always required for the feature
		$config = [
			'enabled'      => true,
			'quality'      => wpfval( WPF()->settings->ai, 'topic_suggestions_quality' ) ?: 'balanced',
			'min_words'    => (int) ( wpfval( WPF()->settings->ai, 'topic_suggestions_min_words' ) ?: 3 ),
			'max_calls'    => (int) ( wpfval( WPF()->settings->ai, 'topic_suggestions_max_calls' ) ?: 2 ),
			'show_related' => (bool) wpfval( WPF()->settings->ai, 'topic_suggestions_show_related' ),
			'show_answer'  => (bool) wpfval( WPF()->settings->ai, 'topic_suggestions_show_answer' ),
		];

		ob_start();
		?>
		<div class="wpf-ai-suggestions-panel" data-suggestion-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
			<div class="wpf-ai-suggestions-header">
				<div class="wpf-ai-suggestions-header-left">
					<span class="wpf-ai-suggestions-title"><?php wpforo_phrase( 'AI Topic Suggestions' ); ?></span>
				</div>
				<span class="wpf-ai-suggestions-close" title="<?php echo esc_attr( wpforo_phrase( 'Close', false ) ); ?>">&times;</span>
			</div>
			<div class="wpf-ai-suggestions-content">
				<!-- Content will be populated by JavaScript -->
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	// =========================================================================
	// CONTENT CLEANING METHODS FOR INDEXING
	// =========================================================================

	/**
	 * Strip quoted content from post body before indexing
	 *
	 * Forum posts often contain quoted replies. When indexing, we should remove
	 * quoted content because:
	 * 1. The original content is already indexed from the original post
	 * 2. Including quotes would create duplicate/redundant content in the index
	 * 3. Search results would be skewed by over-representing quoted content
	 *
	 * Patterns removed:
	 * - [quote data-userid="1" data-postid="488"]...content...[/quote]
	 * - [quote ...]...content...[/quote] (any attributes)
	 * - <blockquote class="..." data-...>...content...</blockquote> (with attributes)
	 *
	 * Patterns kept (user-written content, not quotes of other posts):
	 * - <blockquote>...content...</blockquote> (clean tag without attributes)
	 *
	 * @param string $content Post content (HTML)
	 * @return string Content with quoted sections removed
	 */
	public function strip_quoted_content( $content ) {
		if ( empty( $content ) ) {
			return $content;
		}

		// Pattern 1: Remove [quote ...attributes...] shortcodes
		// Matches [quote data-userid="1" data-postid="488"]...[/quote]
		// and [quote anything...]...[/quote]
		// Uses DOTALL flag (s) to match across newlines
		$content = preg_replace(
			'/\[quote\s+[^\]]+\].*?\[\/quote\]/is',
			'',
			$content
		);

		// Pattern 2: Remove <blockquote> tags that have ANY attributes
		// This indicates a quoted post (wpForo adds data-* attributes)
		// Matches <blockquote class="..." data-userid="...">...</blockquote>
		// But NOT <blockquote>...</blockquote> (clean tag = user content)
		$content = preg_replace(
			'/<blockquote\s+[^>]+>.*?<\/blockquote>/is',
			'',
			$content
		);

		// Clean up any resulting multiple blank lines
		$content = preg_replace( '/(\r?\n){3,}/', "\n\n", $content );

		return trim( $content );
	}

	/**
	 * Clean post content for indexing
	 *
	 * Applies all content cleaning transformations:
	 * - Strip quoted content (duplicates from other posts)
	 * - Future: other content cleaning rules
	 *
	 * @param string $content Post content (HTML)
	 * @return string Cleaned content ready for indexing
	 */
	public function clean_content_for_indexing( $content ) {
		// Strip quoted content first
		$content = $this->strip_quoted_content( $content );

		// Future: Add other cleaning rules here

		return $content;
	}

	/**
	 * Clean content for search result display
	 *
	 * Strips HTML tags, shortcodes, and Lambda processing markers
	 * (image descriptions, document content blocks) from search result excerpts.
	 * These markers are useful for embeddings but should not be shown to users.
	 *
	 * @param string $content Raw content from search result (cloud excerpt or local preview)
	 * @return string Cleaned content for display
	 */
	public function clean_content_for_search_display( $content ) {
		// Strip HTML tags
		$content = wp_strip_all_tags( $content );

		// Remove [TOPIC] or [TOPIC: title] prefix (cloud format)
		$content = preg_replace( '/^\[TOPIC[^\]]*\]\s*/i', '', $content );
		// Remove "Topic: Title\n\n" prefix (local format)
		$content = preg_replace( '/^Topic:\s*[^\n]*\n+/i', '', $content );

		// Count image and document markers BEFORE stripping (for attachment summary)
		$image_count = preg_match_all( '/\[IMAGE:\s*[^\]]*\]/', $content );
		$doc_matches = [];
		preg_match_all( '/\[DOCUMENT:\s*([^\]]*)\]/', $content, $doc_matches );
		$doc_count = count( $doc_matches[0] );
		// Extract page counts from document markers like [DOCUMENT: filename.pdf (5 pages)]
		$total_pages = 0;
		if ( $doc_count > 0 ) {
			foreach ( $doc_matches[1] as $doc_info ) {
				if ( preg_match( '/\((\d+)\s+pages?\)/', $doc_info, $page_match ) ) {
					$total_pages += (int) $page_match[1];
				}
			}
		}

		// Strip enrichment tags added for embedding quality: [FORUM: name], [SOLVED], [BEST ANSWER]
		$content = preg_replace( '/\[(?:FORUM|SOLVED|BEST ANSWER)[^\]]*\]/', '', $content );
		// Strip wpForo shortcodes: [attach]N[/attach], [attach]N,M[/attach]
		$content = preg_replace( '/\[attach\]\d+(?:,\d+)?\[\/attach\]/', '', $content );
		// Strip any remaining shortcode-like patterns: [something]...[/something] or [something]
		$content = preg_replace( '/\[(?:\/)?[a-zA-Z0-9_-]+(?:\s[^\]]*?)?\]/', '', $content );

		// Strip Lambda image processing markers
		$content = preg_replace( '/---\s*Image\s+Content\s*---/', '', $content );
		$content = preg_replace( '/\[IMAGE:\s*[^\]]*\]/', '', $content );

		// Strip Lambda document processing markers
		$content = preg_replace( '/---\s*Document\s+Content\s*---/', '', $content );
		$content = preg_replace( '/\[DOCUMENT:\s*[^\]]*\]/', '', $content );
		$content = preg_replace( '/\[\/DOCUMENT\]/', '', $content );
		$content = preg_replace( '/\[DOC_IMAGE:\s*[^\]]*\]/', '', $content );

		// Normalize whitespace
		$content = preg_replace( '/\s+/', ' ', $content );
		$content = trim( $content );

		// Append attachment summary (same format as local mode build_content_preview)
		$attachments = [];
		if ( $doc_count > 0 ) {
			if ( $total_pages > 0 ) {
				$attachments[] = sprintf( '%d %s, %d %s',
					$doc_count,
					$doc_count === 1 ? 'document' : 'documents',
					$total_pages,
					$total_pages === 1 ? 'page' : 'pages'
				);
			} else {
				$attachments[] = sprintf( '%d %s',
					$doc_count,
					$doc_count === 1 ? 'document' : 'documents'
				);
			}
		}
		if ( $image_count > 0 ) {
			$attachments[] = sprintf( '%d %s',
				$image_count,
				$image_count === 1 ? 'image' : 'images'
			);
		}
		if ( ! empty( $attachments ) ) {
			$content .= ' [+ ' . implode( ', ', $attachments ) . ']';
		}

		return $content;
	}

	// =========================================================================
	// AI BOT REPLY METHODS
	// =========================================================================

	/**
	 * Render Bot Reply button in post action buttons
	 *
	 * Displays an AI bot icon button before the quote button that allows
	 * moderators to generate AI-powered replies to posts.
	 *
	 * @param array|string $button_html Current button HTML (may be array or string)
	 * @param string       $button Button type
	 * @param array        $forum Forum data
	 * @param array        $topic Topic data
	 * @param array        $post Post data
	 * @return array Modified button HTML array
	 */
	public function render_bot_reply_button( $button_html, $button, $forum, $topic, $post ) {
		// Ensure $button_html is an array
		if ( ! is_array( $button_html ) ) {
			$button_html = $button_html ? [ $button_html ] : [];
		}

		// Check if Bot Reply feature is enabled in settings
		if ( ! wpfval( WPF()->settings->ai, 'bot_reply' ) ) {
			return $button_html;
		}

		// Check if feature is available for this plan (wpforo AI specific)
		if ( ! $this->is_feature_available( 'ai_bot_reply' ) ) {
			return $button_html;
		}

		// Get IDs
		$forumid    = (int) ( wpfval( $forum, 'forumid' ) ?: wpfval( $topic, 'forumid' ) );
		$topicid    = (int) wpfval( $topic, 'topicid' );
		$postid     = (int) wpfval( $post, 'postid' );
		$is_closed  = (int) wpfval( $topic, 'closed' );
		$is_approve = (int) wpfval( $post, 'status' );

		// Skip if topic closed, post unapproved, or missing IDs (same as wpforo-aibot)
		if ( $is_closed || $is_approve || ! $postid ) {
			return $button_html;
		}

		// Permission check: Can reply OR (is owner AND can reply to own)
		// Plus: Must have 'au' (approve/unapprove) permission (moderator/admin only)
		$can_reply = WPF()->perm->forum_can( 'cr', $forumid );
		$is_owner  = wpforo_is_owner( wpforo_bigintval( wpfval( $topic, 'userid' ) ), (string) wpfval( $topic, 'email' ) );
		$can_own_reply = $is_owner && WPF()->perm->forum_can( 'ocr', $forumid );

		if ( $can_reply || $can_own_reply ) {
			if ( WPF()->perm->forum_can( 'au', $forumid ) ) {
				$layout       = WPF()->forum->get_layout( $forumid );
				$layout_class = 'wpforo_layout_' . $layout;

				// Build the Bot Reply button HTML (matching wpforo-aibot structure exactly)
				$button_html[] = '<span id="parentpostid' . wpforo_bigintval( $postid ) . '" class="wpf-bot-reply wpf-ai-bot-reply wpf-action ' . $layout_class . '" title="' . esc_attr( wpforo_phrase( 'Ask AI Bot to reply', false ) ) . '" data-postid="' . wpforo_bigintval( $postid ) . '" data-topicid="' . wpforo_bigintval( $topicid ) . '"><i class="fas fa-robot"></i><span class="wpf-button-text">' . wpforo_phrase( 'Bot Reply', false ) . '</span></span>';
			}
		}

		return $button_html;
	}

	/**
	 * Render Suggest Reply button in reply form
	 *
	 * Displays a "Suggest Reply" button before the "Add Reply" submit button
	 * that loads AI-generated content into the TinyMCE editor.
	 *
	 * @param array $topic Topic data
	 * @param array $values Form values (empty for new reply, populated for edit)
	 * @param array $forum Forum data
	 * @return void
	 */
	public function render_suggest_reply_button( $topic, $values, $forum ) {
		// Skip if editing (values is not empty means edit mode)
		if ( ! empty( $values ) && wpfval( $values, 'postid' ) ) {
			return;
		}

		// Check if Bot Reply feature is enabled
		if ( ! wpfval( WPF()->settings->ai, 'bot_reply' ) ) {
			return;
		}

		// Check if feature is available for this plan
		if ( ! $this->is_feature_available( 'ai_bot_reply' ) ) {
			return;
		}

		// Check if topic is closed
		if ( ! empty( $topic['closed'] ) ) {
			return;
		}

		// Check if user has 'au' permission for this forum
		$forumid = wpfval( $forum, 'forumid' ) ?: wpfval( $topic, 'forumid' );
		if ( ! $forumid || ! WPF()->perm->forum_can( 'au', $forumid ) ) {
			return;
		}

		$topic_id = (int) wpfval( $topic, 'topicid' );
		if ( ! $topic_id ) {
			return;
		}

		// Render the Suggest Reply button
		?>
		<button type="button"
		        class="wpf-button wpf-button-secondary wpf-ai-suggest-reply"
		        data-topicid="<?php echo esc_attr( $topic_id ); ?>"
		        title="<?php echo esc_attr( wpforo_phrase( 'Generate AI reply suggestion', false ) ); ?>">
			<i class="fas fa-circle-notch fa-spin wpf-ai-spinner"></i>
			<svg class="wpf-ai-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.912 5.813a2 2 0 0 0 1.275 1.275L21 12l-5.813 1.912a2 2 0 0 0-1.275 1.275L12 21l-1.912-5.813a2 2 0 0 0-1.275-1.275L3 12l5.813-1.912a2 2 0 0 0 1.275-1.275L12 3z"/></svg>
			<span><?php echo esc_html( wpforo_phrase( 'Suggest Reply', false ) ); ?></span>
		</button>
		<?php
	}

	/**
	 * AJAX handler for Bot Reply
	 *
	 * Generates an AI reply and creates a new post from the bot user.
	 *
	 * @return void
	 */
	public function ajax_bot_reply() {
		// Track start time for logging
		$_log_start_time = microtime( true );

		// Verify nonce
		if ( ! wp_verify_nonce( sanitize_text_field( wpfval( $_POST, '_wpnonce' ) ), 'wpforo_ai_bot_reply' ) ) {
			wp_send_json_error( [ 'message' => wpforo_phrase( 'Security check failed', false ) ], 403 );
		}

		// Check if feature is enabled and available
		if ( ! wpfval( WPF()->settings->ai, 'bot_reply' ) || ! $this->is_feature_available( 'ai_bot_reply' ) ) {
			wp_send_json_error( [ 'message' => wpforo_phrase( 'AI Bot Reply feature is not available', false ) ], 403 );
		}

		// Get parameters
		$post_id  = (int) wpfval( $_POST, 'post_id' );
		$topic_id = (int) wpfval( $_POST, 'topic_id' );

		if ( ! $post_id || ! $topic_id ) {
			wp_send_json_error( [ 'message' => wpforo_phrase( 'Invalid request parameters', false ) ], 400 );
		}

		// Get topic and post data
		$topic = WPF()->topic->get_topic( $topic_id );
		if ( ! $topic ) {
			wp_send_json_error( [ 'message' => wpforo_phrase( 'Topic not found', false ) ], 404 );
		}

		// Check permission
		$forumid = (int) $topic['forumid'];
		if ( ! WPF()->perm->forum_can( 'au', $forumid ) ) {
			wp_send_json_error( [ 'message' => wpforo_phrase( 'Permission denied', false ) ], 403 );
		}

		// Check if topic is closed
		if ( ! empty( $topic['closed'] ) ) {
			wp_send_json_error( [ 'message' => wpforo_phrase( 'Topic is closed', false ) ], 403 );
		}

		// Check rate limits
		$limit_error = $this->check_bot_reply_limits( $topic_id );
		if ( $limit_error ) {
			wp_send_json_error( [ 'message' => $limit_error ], 429 );
		}

		// Get the post being replied to
		$parent_post = WPF()->post->get_post( $post_id );
		if ( ! $parent_post ) {
			wp_send_json_error( [ 'message' => wpforo_phrase( 'Post not found', false ) ], 404 );
		}

		// Get first post for topic context (if replying to a reply, not the first post)
		$first_post = null;
		if ( (int) $parent_post['is_first_post'] !== 1 ) {
			$first_post = WPF()->post->get_post( $topic['first_postid'] );
		}

		// Generate AI reply
		$result = $this->generate_bot_reply( $topic, $parent_post, $first_post );
		if ( is_wp_error( $result ) ) {
			// Log error
			if ( isset( WPF()->ai_logs ) && WPF()->ai_logs ) {
				WPF()->ai_logs->log( [
					'action_type'      => AILogs::ACTION_BOT_REPLY,
					'credits_used'     => 0,
					'status'           => AILogs::STATUS_ERROR,
					'content_type'     => 'topic',
					'content_id'       => $topic_id,
					'topicid'          => $topic_id,
					'forumid'          => $forumid,
					'request_summary'  => sprintf( 'Bot reply to topic: %s', wp_trim_words( $topic['title'], 10 ) ),
					'error_message'    => $result->get_error_message(),
					'duration_ms'      => (int) ( ( microtime( true ) - $_log_start_time ) * 1000 ),
				] );
			}
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
		}

		// Create the bot reply post
		$new_post_id = $this->create_bot_reply_post( $topic, $parent_post, $result['reply'] );
		if ( is_wp_error( $new_post_id ) ) {
			// Log error
			if ( isset( WPF()->ai_logs ) && WPF()->ai_logs ) {
				WPF()->ai_logs->log( [
					'action_type'      => AILogs::ACTION_BOT_REPLY,
					'credits_used'     => $result['credits_used'] ?? 0,
					'status'           => AILogs::STATUS_ERROR,
					'content_type'     => 'topic',
					'content_id'       => $topic_id,
					'topicid'          => $topic_id,
					'forumid'          => $forumid,
					'request_summary'  => sprintf( 'Bot reply to topic: %s', wp_trim_words( $topic['title'], 10 ) ),
					'error_message'    => $new_post_id->get_error_message(),
					'duration_ms'      => (int) ( ( microtime( true ) - $_log_start_time ) * 1000 ),
				] );
			}
			wp_send_json_error( [ 'message' => $new_post_id->get_error_message() ], 500 );
		}

		$credits_used = $result['credits_used'] ?? 0;

		// Log success
		if ( isset( WPF()->ai_logs ) && WPF()->ai_logs ) {
			WPF()->ai_logs->log( [
				'action_type'      => AILogs::ACTION_BOT_REPLY,
				'credits_used'     => $credits_used,
				'status'           => AILogs::STATUS_SUCCESS,
				'content_type'     => 'topic',
				'content_id'       => $topic_id,
				'topicid'          => $topic_id,
				'forumid'          => $forumid,
				'request_summary'  => sprintf( 'Bot reply to topic: %s', wp_trim_words( $topic['title'], 10 ) ),
				'response_summary' => sprintf( 'Created post #%d', $new_post_id ),
				'duration_ms'      => (int) ( ( microtime( true ) - $_log_start_time ) * 1000 ),
			] );
		}

		wp_send_json_success( [
			'post_id'      => $new_post_id,
			'credits_used' => $credits_used,
			'message'      => wpforo_phrase( 'Bot reply created successfully', false ),
		] );
	}

	/**
	 * AJAX handler for Suggest Reply
	 *
	 * Generates an AI reply suggestion and returns it for insertion into the editor.
	 *
	 * @return void
	 */
	public function ajax_suggest_reply() {
		// Track start time for logging
		$_log_start_time = microtime( true );

		// Verify nonce
		if ( ! wp_verify_nonce( sanitize_text_field( wpfval( $_POST, '_wpnonce' ) ), 'wpforo_ai_suggest_reply' ) ) {
			wp_send_json_error( [ 'message' => wpforo_phrase( 'Security check failed', false ) ], 403 );
		}

		// Check if feature is enabled and available
		if ( ! wpfval( WPF()->settings->ai, 'bot_reply' ) || ! $this->is_feature_available( 'ai_bot_reply' ) ) {
			wp_send_json_error( [ 'message' => wpforo_phrase( 'AI Bot Reply feature is not available', false ) ], 403 );
		}

		// Get parameters
		$topic_id  = (int) wpfval( $_POST, 'topic_id' );
		$parent_id = (int) wpfval( $_POST, 'parent_id' ); // Optional: if replying to specific post

		if ( ! $topic_id ) {
			wp_send_json_error( [ 'message' => wpforo_phrase( 'Invalid request parameters', false ) ], 400 );
		}

		// Get topic data
		$topic = WPF()->topic->get_topic( $topic_id );
		if ( ! $topic ) {
			wp_send_json_error( [ 'message' => wpforo_phrase( 'Topic not found', false ) ], 404 );
		}

		// Check permission
		$forumid = (int) $topic['forumid'];
		if ( ! WPF()->perm->forum_can( 'au', $forumid ) ) {
			wp_send_json_error( [ 'message' => wpforo_phrase( 'Permission denied', false ) ], 403 );
		}

		// Get the post being replied to (default to first post if no parent specified)
		$parent_post = null;
		if ( $parent_id ) {
			$parent_post = WPF()->post->get_post( $parent_id );
		}
		if ( ! $parent_post ) {
			$parent_post = WPF()->post->get_post( $topic['first_postid'] );
		}

		if ( ! $parent_post ) {
			wp_send_json_error( [ 'message' => wpforo_phrase( 'Post not found', false ) ], 404 );
		}

		// Get first post for topic context (if replying to a reply)
		$first_post = null;
		if ( (int) $parent_post['is_first_post'] !== 1 ) {
			$first_post = WPF()->post->get_post( $topic['first_postid'] );
		}

		// Generate AI reply
		$result = $this->generate_bot_reply( $topic, $parent_post, $first_post );
		if ( is_wp_error( $result ) ) {
			// Log error
			if ( isset( WPF()->ai_logs ) && WPF()->ai_logs ) {
				WPF()->ai_logs->log( [
					'action_type'      => AILogs::ACTION_SUGGEST_REPLY,
					'credits_used'     => 0,
					'status'           => AILogs::STATUS_ERROR,
					'content_type'     => 'topic',
					'content_id'       => $topic_id,
					'topicid'          => $topic_id,
					'forumid'          => $forumid,
					'request_summary'  => sprintf( 'Suggest reply for topic: %s', wp_trim_words( $topic['title'], 10 ) ),
					'error_message'    => $result->get_error_message(),
					'duration_ms'      => (int) ( ( microtime( true ) - $_log_start_time ) * 1000 ),
				] );
			}
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
		}

		$credits_used = $result['credits_used'] ?? 0;

		// Log success
		if ( isset( WPF()->ai_logs ) && WPF()->ai_logs ) {
			WPF()->ai_logs->log( [
				'action_type'      => AILogs::ACTION_SUGGEST_REPLY,
				'credits_used'     => $credits_used,
				'status'           => AILogs::STATUS_SUCCESS,
				'content_type'     => 'topic',
				'content_id'       => $topic_id,
				'topicid'          => $topic_id,
				'forumid'          => $forumid,
				'request_summary'  => sprintf( 'Suggest reply for topic: %s', wp_trim_words( $topic['title'], 10 ) ),
				'response_summary' => 'Generated reply suggestion',
				'duration_ms'      => (int) ( ( microtime( true ) - $_log_start_time ) * 1000 ),
			] );
		}

		wp_send_json_success( [
			'content'      => $result['reply'],
			'credits_used' => $credits_used,
		] );
	}

	/**
	 * Generate bot reply using Tasks API
	 *
	 * @param array $topic Topic data
	 * @param array $parent_post Post being replied to
	 * @param array|null $first_post First post of topic (if replying to reply)
	 * @return array|WP_Error Result with 'reply' and 'credits_used' or WP_Error
	 */
	private function generate_bot_reply( $topic, $parent_post, $first_post = null ) {
		$api_key = $this->get_stored_api_key();
		if ( ! $api_key ) {
			return new \WP_Error( 'no_api_key', wpforo_phrase( 'API key not configured', false ) );
		}

		// Get settings
		$settings = WPF()->settings->ai;
		$quality  = wpfval( $settings, 'bot_reply_quality' ) ?: 'premium';
		$style    = wpfval( $settings, 'bot_reply_style' ) ?: 'helpful_answer';
		$tone     = wpfval( $settings, 'bot_reply_tone' ) ?: 'neutral';
		$length   = wpfval( $settings, 'bot_reply_length' ) ?: 'medium';
		$knowledge_source = wpfval( $settings, 'bot_reply_knowledge_source' ) ?: 'forum_and_ai';
		$response_language = $this->get_user_language( null, 'bot_reply_language' );

		// Build include options from checkbox array
		$include = [];
		$includes_setting = wpfval( $settings, 'bot_reply_includes' );
		if ( is_array( $includes_setting ) ) {
			// Map setting values to backend expected keys
			$include_map = [
				'code'      => 'code_examples',
				'docs'      => 'documentation_links',
				'steps'     => 'step_by_step',
				'questions' => 'follow_up_questions',
				'youtube'   => 'youtube_videos',
				'greeting'  => 'personalized_greeting',
			];
			foreach ( $includes_setting as $key ) {
				if ( isset( $include_map[ $key ] ) ) {
					$include[] = $include_map[ $key ];
				}
			}
		}

		// Get forum info
		$forum = WPF()->forum->get_forum( (int) $topic['forumid'] );

		// Get parent post author name for greeting (use @nicename format)
		$parent_author_name = '';
		if ( $parent_post['userid'] ) {
			$parent_author = WPF()->member->get_member( $parent_post['userid'] );
			$nicename      = wpfval( $parent_author, 'user_nicename' ) ?: wpfval( $parent_author, 'display_name' );
			$parent_author_name = $nicename ? '@' . $nicename : '';
		} else {
			$parent_author_name = wpfval( $parent_post, 'name' ) ?: '';
		}

		// Build posts array for the topic context
		$posts = [];

		// Add first post if available
		if ( $first_post ) {
			$first_post_author = '';
			if ( $first_post['userid'] ) {
				$first_author = WPF()->member->get_member( $first_post['userid'] );
				$first_post_author = wpfval( $first_author, 'display_name' ) ?: '';
			} else {
				$first_post_author = wpfval( $first_post, 'name' ) ?: '';
			}
			$posts[] = [
				'postid'  => (int) $first_post['postid'],
				'author'  => $first_post_author,
				'content' => wp_strip_all_tags( $first_post['body'] ),
			];
		}

		// Add parent post (the one being replied to)
		$posts[] = [
			'postid'  => (int) $parent_post['postid'],
			'author'  => $parent_author_name,
			'content' => wp_strip_all_tags( $parent_post['body'] ),
		];

		// Build the request payload matching backend ReplyGeneratorRequest schema
		$request_body = [
			'task_type'        => 'reply_generator',
			'topics'           => [
				[
					'topic_id'       => (int) $topic['topicid'],
					'title'          => $topic['title'],
					'forum_id'       => (int) $topic['forumid'],
					'posts'          => $posts,
					'reply_strategy' => 'last_post',
				],
			],
			'replies_count'    => 1,
			'quality'          => $quality,
			'reply_style'      => $style,
			'reply_tone'       => $tone,
			'reply_strategy'   => 'last_post',
			'reply_length'     => $length,
			'include'          => $include,
			'knowledge_source' => $knowledge_source,
			'response_language' => $response_language,
		];

		// Make API request to /tasks/generate endpoint
		$response = wp_remote_post( $this->api_base_url . '/tasks/generate', [
			'timeout' => 60,
			'headers' => [
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			],
			'body'    => wp_json_encode( $request_body ),
		] );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'api_error', $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code >= 400 ) {
			$error_message = wpfval( $body, 'error' ) ?: wpfval( $body, 'detail' ) ?: 'API request failed';
			return new \WP_Error( 'api_error', $error_message );
		}

		// Backend returns { success: bool, replies: [{topic_id, content}], credits_used }
		$replies = wpfval( $body, 'replies' );
		$reply_content = '';
		if ( is_array( $replies ) && ! empty( $replies ) ) {
			$reply_content = wpfval( $replies[0], 'content' ) ?: '';
		}

		// Fallback to legacy response fields
		if ( empty( $reply_content ) ) {
			$reply_content = wpfval( $body, 'reply' ) ?: wpfval( $body, 'content' ) ?: '';
		}

		if ( empty( $reply_content ) ) {
			return new \WP_Error( 'empty_reply', wpforo_phrase( 'AI generated an empty reply', false ) );
		}

		return [
			'reply'        => $reply_content,
			'credits_used' => wpfval( $body, 'credits_used' ) ?: 0,
		];
	}

	/**
	 * Create bot reply post
	 *
	 * @param array  $topic Topic data
	 * @param array  $parent_post Parent post data
	 * @param string $content Reply content
	 * @return int|WP_Error New post ID or WP_Error
	 */
	private function create_bot_reply_post( $topic, $parent_post, $content ) {
		$settings = WPF()->settings->ai;

		// Get bot user ID
		$bot_user_id = (int) wpfval( $settings, 'bot_reply_user_id' );
		if ( ! $bot_user_id ) {
			return new \WP_Error( 'no_bot_user', wpforo_phrase( 'Bot user not configured', false ) );
		}

		// Check if bot user exists
		$bot_user = get_user_by( 'ID', $bot_user_id );
		if ( ! $bot_user ) {
			return new \WP_Error( 'invalid_bot_user', wpforo_phrase( 'Bot user does not exist', false ) );
		}

		// Determine status (1 = unapproved, 0 = approved)
		$status = wpfval( $settings, 'bot_reply_unapproved' ) ? 1 : 0;

		// Build post data
		$post_data = [
			'forumid'  => (int) $topic['forumid'],
			'topicid'  => (int) $topic['topicid'],
			'parentid' => (int) $parent_post['postid'],
			'userid'   => $bot_user_id,
			'title'    => wpforo_phrase( 'RE', false ) . ': ' . $topic['title'],
			'body'     => $content,
			'status'   => $status,
		];

		// Store current user to restore later
		$current_user_id = get_current_user_id();

		// Temporarily switch to bot user
		wp_set_current_user( $bot_user_id );
		WPF()->current_userid = $bot_user_id;

		// Create the post using wpForo's add method
		$new_post_id = WPF()->post->add( $post_data );

		// Restore original user
		wp_set_current_user( $current_user_id );
		WPF()->current_userid = $current_user_id;

		if ( ! $new_post_id ) {
			return new \WP_Error( 'post_creation_failed', wpforo_phrase( 'Failed to create bot reply', false ) );
		}

		return $new_post_id;
	}

	/**
	 * Check bot reply rate limits
	 *
	 * @param int $topic_id Topic ID
	 * @return string|null Error message if limit exceeded, null if OK
	 */
	private function check_bot_reply_limits( $topic_id ) {
		global $wpdb;
		$settings = WPF()->settings->ai;

		$bot_user_id = (int) wpfval( $settings, 'bot_reply_user_id' );
		if ( ! $bot_user_id ) {
			return wpforo_phrase( 'Bot user not configured', false );
		}

		// Check max per topic
		$max_per_topic = (int) wpfval( $settings, 'bot_reply_max_per_topic' );
		if ( $max_per_topic > 0 ) {
			$topic_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `" . WPF()->tables->posts . "`
					WHERE `topicid` = %d AND `userid` = %d",
					$topic_id,
					$bot_user_id
				)
			);

			if ( (int) $topic_count >= $max_per_topic ) {
				return sprintf(
					wpforo_phrase( 'Maximum bot replies per topic reached (%d)', false ),
					$max_per_topic
				);
			}
		}

		// Check max per day
		$max_per_day = (int) wpfval( $settings, 'bot_reply_max_per_day' );
		if ( $max_per_day > 0 ) {
			$today_start = current_time( 'Y-m-d' ) . ' 00:00:00';
			$day_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `" . WPF()->tables->posts . "`
					WHERE `userid` = %d AND `created` >= %s",
					$bot_user_id,
					$today_start
				)
			);

			if ( (int) $day_count >= $max_per_day ) {
				return sprintf(
					wpforo_phrase( 'Maximum bot replies per day reached (%d)', false ),
					$max_per_day
				);
			}
		}

		return null;
	}

	// =========================================================================
	// MULTIMODAL IMAGE EXTRACTION METHODS
	// =========================================================================

	/**
	 * Check if multimodal image indexing is enabled for the current board
	 *
	 * Image indexing requires:
	 * 1. Professional, Business or Enterprise plan
	 * 2. Board-specific setting enabled (ai_image_indexing_enabled)
	 *
	 * Credit Impact:
	 * - When enabled, posts with images consume +1 additional credit
	 * - Maximum 10 images per post (enforced by API)
	 *
	 * @return bool True if image indexing is enabled
	 */
	public function is_image_indexing_enabled() {
		// Check board-specific setting first (fast check)
		$board_setting = (bool) wpforo_get_option( 'ai_image_indexing_enabled', 0 );
		if ( ! $board_setting ) {
			return false;
		}

		// Check plan eligibility using cached plan (no API calls)
		$plan = strtolower( $this->get_subscription_plan() );

		// Professional, Business and Enterprise plans have image indexing
		return in_array( $plan, [ 'professional', 'business', 'enterprise' ], true );
	}

	/**
	 * Check if URL points to an image file
	 *
	 * @param string $url URL to check
	 * @return bool True if URL has an image extension
	 */
	private function is_image_url( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! $path ) {
			return false;
		}
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		return in_array( $ext, self::$image_extensions, true );
	}

	/**
	 * Check if document indexing is enabled for the current board
	 *
	 * Requires both:
	 * 1. Professional+ subscription plan
	 * 2. Board-specific setting enabled (ai_document_indexing_enabled)
	 *
	 * @return bool True if document indexing is enabled and eligible
	 */
	public function is_document_indexing_enabled() {
		$board_setting = (bool) wpforo_get_option( 'ai_document_indexing_enabled', 0 );
		if ( ! $board_setting ) {
			return false;
		}

		$plan = strtolower( $this->get_subscription_plan() );

		return in_array( $plan, [ 'professional', 'business', 'enterprise' ], true );
	}

	/**
	 * Check if URL points to a document file
	 *
	 * @param string $url URL to check
	 * @return bool True if URL has a document extension
	 */
	private function is_document_url( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! $path ) {
			return false;
		}
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		return in_array( $ext, self::$document_extensions, true );
	}

	/**
	 * Check if URL belongs to local site (not external domain)
	 *
	 * @param string $url      URL to check
	 * @param string $site_url Site URL for comparison (optional)
	 * @return bool True if URL is local
	 */
	private function is_local_url( $url, $site_url = null ) {
		if ( ! $site_url ) {
			$site_url = get_site_url();
		}

		$site_host = wp_parse_url( $site_url, PHP_URL_HOST );
		$url_host = wp_parse_url( $url, PHP_URL_HOST );

		// Relative URLs are local
		if ( ! $url_host ) {
			return true;
		}

		// Exact match
		if ( $url_host === $site_host ) {
			return true;
		}

		// Normalize both hosts (strip www prefix for comparison)
		$site_host_normalized = preg_replace( '/^www\./', '', $site_host );
		$url_host_normalized = preg_replace( '/^www\./', '', $url_host );

		// Match after www normalization (example.com == www.example.com)
		if ( $url_host_normalized === $site_host_normalized ) {
			return true;
		}

		// Check if URL host is a subdomain of site host (e.g., cdn.example.com for example.com)
		// Must end with .site_host to be a subdomain
		if ( substr( $url_host_normalized, -strlen( '.' . $site_host_normalized ) ) === '.' . $site_host_normalized ) {
			return true;
		}

		return false;
	}

	/**
	 * Normalize URL to canonical form for deduplication
	 *
	 * Handles protocol-relative URLs, relative URLs, http→https normalization,
	 * and query string/fragment removal.
	 *
	 * @param string $url      URL to normalize
	 * @param string $site_url Site URL for relative URL expansion (optional)
	 * @return string Normalized URL, or empty string if invalid
	 */
	private function normalize_url( $url, $site_url = null ) {
		if ( ! $site_url ) {
			$site_url = get_site_url();
		}

		$url = trim( $url );

		// Skip data URIs
		if ( strpos( $url, 'data:' ) === 0 ) {
			return '';
		}

		// Expand relative URLs
		if ( strpos( $url, '/' ) === 0 && strpos( $url, '//' ) !== 0 ) {
			$url = rtrim( $site_url, '/' ) . $url;
		} elseif ( strpos( $url, '//' ) === 0 ) {
			// Protocol-relative URL
			$url = 'https:' . $url;
		}

		// Normalize protocol to https
		$url = preg_replace( '#^http://#i', 'https://', $url );

		// Remove query string and fragment for deduplication
		$url = strtok( $url, '?#' );

		return $url;
	}

	/**
	 * Normalize image URL (delegates to normalize_url)
	 *
	 * @param string $url      URL to normalize
	 * @param string $site_url Site URL for relative URL expansion (optional)
	 * @return string Normalized URL, or empty string if invalid
	 */
	private function normalize_image_url( $url, $site_url = null ) {
		return $this->normalize_url( $url, $site_url );
	}

	/**
	 * Extract images from <img> tags in content
	 *
	 * @param string $content  Post HTML content
	 * @param string $site_url Site URL for validation
	 * @return array Array of normalized image URLs
	 */
	private function extract_img_tags( $content, $site_url = null ) {
		$images = [];

		if ( preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches ) ) {
			foreach ( $matches[1] as $src ) {
				$normalized = $this->normalize_image_url( $src, $site_url );
				if ( $normalized && $this->is_image_url( $normalized ) && $this->is_local_url( $normalized, $site_url ) ) {
					$images[] = $normalized;
				}
			}
		}

		return $images;
	}

	/**
	 * Extract images from <a> tags in content (wpForo default attachments)
	 *
	 * @param string $content  Post HTML content
	 * @param string $site_url Site URL for validation
	 * @return array Array of normalized image URLs
	 */
	private function extract_anchor_images( $content, $site_url = null ) {
		$images = [];

		if ( preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches ) ) {
			foreach ( $matches[1] as $href ) {
				$normalized = $this->normalize_image_url( $href, $site_url );
				if ( $normalized && $this->is_image_url( $normalized ) && $this->is_local_url( $normalized, $site_url ) ) {
					$images[] = $normalized;
				}
			}
		}

		return $images;
	}

	/**
	 * Extract plain text image URLs from content
	 *
	 * @param string $content  Post content
	 * @param string $site_url Site URL for validation
	 * @return array Array of normalized image URLs
	 */
	private function extract_plain_urls( $content, $site_url = null ) {
		$images = [];

		// Strip HTML tags first to find plain text URLs
		$text = wp_strip_all_tags( $content );

		// Match URLs ending with image extensions
		$pattern = '#https?://[^\s<>"\']+\.(?:' . implode( '|', self::$image_extensions ) . ')#i';

		if ( preg_match_all( $pattern, $text, $matches ) ) {
			foreach ( $matches[0] as $url ) {
				$normalized = $this->normalize_image_url( $url, $site_url );
				if ( $normalized && $this->is_local_url( $normalized, $site_url ) ) {
					$images[] = $normalized;
				}
			}
		}

		return $images;
	}

	/**
	 * Extract attachment IDs from [attach] shortcodes
	 *
	 * @param string $content Post content with shortcodes
	 * @return array Array of attachment IDs (integers)
	 */
	private function extract_attach_ids( $content ) {
		$attach_ids = [];

		// Match [attach...]ID[/attach] patterns
		if ( preg_match_all( '/\[attach[^\]]*\](\d+(?:,\s*\d+)*)\[\/attach\]/i', $content, $matches ) ) {
			foreach ( $matches[1] as $ids_string ) {
				$ids = array_map( 'intval', explode( ',', $ids_string ) );
				$attach_ids = array_merge( $attach_ids, $ids );
			}
		}

		return array_unique( array_filter( $attach_ids ) );
	}

	/**
	 * Get image URLs from attachment IDs
	 *
	 * Handles missing Advanced Attachments addon gracefully.
	 *
	 * @param array $attach_ids Array of attachment IDs
	 * @return array Array of image data with url and attach_id
	 */
	private function get_attachment_urls( $attach_ids ) {
		if ( empty( $attach_ids ) ) {
			return [];
		}

		// Check if wpForo is available
		if ( ! function_exists( 'WPF' ) ) {
			return [];
		}

		// Check if Advanced Attachments addon exists
		if ( ! isset( WPF()->tables->attachments ) ) {
			return [];
		}

		global $wpdb;
		$table = WPF()->tables->attachments;

		// Check if table exists
		$table_exists = $wpdb->get_var( $wpdb->prepare(
			"SHOW TABLES LIKE %s",
			$table
		) );

		if ( ! $table_exists ) {
			return [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $attach_ids ), '%d' ) );
		$query = $wpdb->prepare(
			"SELECT attachid, fileurl, mime FROM {$table} WHERE attachid IN ({$placeholders})",
			$attach_ids
		);

		$attachments = $wpdb->get_results( $query, ARRAY_A );

		if ( ! $attachments ) {
			return [];
		}

		$image_urls = [];
		foreach ( $attachments as $attach ) {
			// Only include image MIME types
			if ( isset( $attach['mime'] ) && strpos( $attach['mime'], 'image/' ) === 0 ) {
				$normalized = $this->normalize_image_url( $attach['fileurl'] );
				if ( $normalized ) {
					$image_urls[] = [
						'attach_id' => (int) $attach['attachid'],
						'url'       => $normalized,
					];
				}
			}
		}

		return $image_urls;
	}

	/**
	 * Get document URLs from attachment IDs
	 *
	 * Filters attachments by document MIME types (application/*, text/*).
	 * Validates that the file extension matches supported document formats.
	 *
	 * @param array $attach_ids Array of attachment IDs
	 * @return array Array of document data with url and attach_id
	 */
	private function get_attachment_document_urls( $attach_ids ) {
		if ( empty( $attach_ids ) ) {
			return [];
		}

		if ( ! function_exists( 'WPF' ) ) {
			return [];
		}

		if ( ! isset( WPF()->tables->attachments ) ) {
			return [];
		}

		global $wpdb;
		$table = WPF()->tables->attachments;

		$table_exists = $wpdb->get_var( $wpdb->prepare(
			"SHOW TABLES LIKE %s",
			$table
		) );

		if ( ! $table_exists ) {
			return [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $attach_ids ), '%d' ) );
		$query = $wpdb->prepare(
			"SELECT attachid, fileurl, mime FROM {$table} WHERE attachid IN ({$placeholders})",
			$attach_ids
		);

		$attachments = $wpdb->get_results( $query, ARRAY_A );

		if ( ! $attachments ) {
			return [];
		}

		$doc_urls = [];
		foreach ( $attachments as $attach ) {
			$mime = $attach['mime'] ?? '';
			// Include application/* and text/* MIME types (PDFs, DOCX, TXT, etc.)
			if ( strpos( $mime, 'application/' ) === 0 || strpos( $mime, 'text/' ) === 0 ) {
				$normalized = $this->normalize_url( $attach['fileurl'] );
				if ( $normalized && $this->is_document_url( $normalized ) ) {
					$doc_urls[] = [
						'attach_id' => (int) $attach['attachid'],
						'url'       => $normalized,
					];
				}
			}
		}

		return $doc_urls;
	}

	/**
	 * Extract ALL images from post content with deduplication
	 *
	 * Handles all 4 image source types:
	 * 1. <img> tags
	 * 2. <a> tags (wpForo default attachments)
	 * 3. Plain text URLs
	 * 4. [attach] shortcodes (Advanced Attachments addon)
	 *
	 * @param string $content Post body content
	 * @return array Array of unique image data
	 */
	public function extract_post_images( $content ) {
		if ( empty( $content ) ) {
			return [];
		}

		$site_url = get_site_url();

		// Track URLs for deduplication (normalized URL => image data)
		$url_map = [];

		// 1. Extract from <img> tags
		foreach ( $this->extract_img_tags( $content, $site_url ) as $url ) {
			if ( ! isset( $url_map[ $url ] ) ) {
				$url_map[ $url ] = [
					'type'      => 'img_tag',
					'url'       => $url,
					'attach_id' => null,
				];
			}
		}

		// 2. Extract from <a> tags (default wpForo attachments)
		foreach ( $this->extract_anchor_images( $content, $site_url ) as $url ) {
			if ( ! isset( $url_map[ $url ] ) ) {
				$url_map[ $url ] = [
					'type'      => 'anchor_link',
					'url'       => $url,
					'attach_id' => null,
				];
			}
		}

		// 3. Extract plain text URLs
		foreach ( $this->extract_plain_urls( $content, $site_url ) as $url ) {
			if ( ! isset( $url_map[ $url ] ) ) {
				$url_map[ $url ] = [
					'type'      => 'plain_url',
					'url'       => $url,
					'attach_id' => null,
				];
			}
		}

		// 4. Extract [attach] shortcode images (if addon exists)
		$attach_ids = $this->extract_attach_ids( $content );
		if ( ! empty( $attach_ids ) ) {
			$attach_images = $this->get_attachment_urls( $attach_ids );
			foreach ( $attach_images as $attach ) {
				$url = $attach['url'];
				if ( ! isset( $url_map[ $url ] ) ) {
					$url_map[ $url ] = [
						'type'      => 'shortcode',
						'url'       => $url,
						'attach_id' => $attach['attach_id'],
					];
				} else {
					// URL already exists from another source, add attach_id
					$url_map[ $url ]['attach_id'] = $attach['attach_id'];
				}
			}
		}

		// Return deduplicated images as array
		return array_values( $url_map );
	}

	/**
	 * Extract ALL documents from post content with deduplication
	 *
	 * Handles 3 document source types:
	 * 1. <a> tags with href pointing to document files (linked PDFs, DOCX, etc.)
	 * 2. Plain text URLs ending in document extensions
	 * 3. [attach] shortcodes resolving to document attachments
	 *
	 * @param string $content Post body content
	 * @return array Array of unique document data: [['type' => '...', 'url' => '...', 'attach_id' => ...], ...]
	 */
	public function extract_post_documents( $content ) {
		if ( empty( $content ) ) {
			return [];
		}

		$site_url = get_site_url();
		$url_map = [];

		// 1. Extract from <a> tags (most common - linked PDFs)
		if ( preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches ) ) {
			foreach ( $matches[1] as $href ) {
				$normalized = $this->normalize_url( $href, $site_url );
				if ( $normalized && $this->is_document_url( $normalized ) && $this->is_local_url( $normalized, $site_url ) ) {
					$url_map[ $normalized ] = [
						'type'      => 'anchor_link',
						'url'       => $normalized,
						'attach_id' => null,
					];
				}
			}
		}

		// 2. Extract plain text document URLs
		$doc_ext_pattern = implode( '|', self::$document_extensions );
		if ( preg_match_all( '#https?://[^\s<>"\']+\.(?:' . $doc_ext_pattern . ')#i', $content, $matches ) ) {
			foreach ( $matches[0] as $url ) {
				$normalized = $this->normalize_url( $url, $site_url );
				if ( $normalized && $this->is_local_url( $normalized, $site_url ) && ! isset( $url_map[ $normalized ] ) ) {
					$url_map[ $normalized ] = [
						'type'      => 'plain_url',
						'url'       => $normalized,
						'attach_id' => null,
					];
				}
			}
		}

		// 3. Extract [attach] shortcode documents (if addon exists)
		$attach_ids = $this->extract_attach_ids( $content );
		if ( ! empty( $attach_ids ) ) {
			$attach_docs = $this->get_attachment_document_urls( $attach_ids );
			foreach ( $attach_docs as $doc ) {
				$url = $doc['url'];
				if ( ! isset( $url_map[ $url ] ) ) {
					$url_map[ $url ] = [
						'type'      => 'shortcode',
						'url'       => $url,
						'attach_id' => $doc['attach_id'],
					];
				} else {
					$url_map[ $url ]['attach_id'] = $doc['attach_id'];
				}
			}
		}

		return array_values( $url_map );
	}
}
