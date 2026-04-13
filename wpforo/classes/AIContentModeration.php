<?php

namespace wpforo\classes;

/**
 * AI Content Moderation
 *
 * Central class for AI-powered content moderation in wpForo.
 * Hooks into all content events and provides a framework for moderation features.
 *
 * Features to be implemented:
 * - Content Safety & Toxicity Detection
 * - Spam & Low-Quality Detection
 * - Rule Compliance & Policy Enforcement
 * - Content Quality Enhancement
 * - User Behavior Analysis
 * - Automated Moderation Actions
 * - Moderator Assistance Tools
 *
 * @since 3.0.0
 */
class AIContentModeration {

	/**
	 * Singleton instance
	 *
	 * @var AIContentModeration|null
	 */
	private static $instance = null;

	/**
	 * Board ID
	 *
	 * @var int
	 */
	private $board_id = 0;

	/**
	 * Cached settings
	 *
	 * @var array
	 */
	private $settings = [];

	/**
	 * Registered moderation handlers
	 *
	 * @var array
	 */
	private $handlers = [];

	/**
	 * Moderation action constants
	 */
	const ACTION_APPROVE      = 'approve';
	const ACTION_HOLD         = 'hold';
	const ACTION_REJECT       = 'reject';
	const ACTION_DELETE       = 'delete';
	const ACTION_EDIT         = 'edit';
	const ACTION_MOVE         = 'move';
	const ACTION_CLOSE        = 'close';
	const ACTION_MERGE        = 'merge';
	const ACTION_WARN_USER    = 'warn_user';
	const ACTION_BAN_USER     = 'ban_user';
	const ACTION_SUSPEND_USER = 'suspend_user';

	/**
	 * Spam action setting values
	 */
	const SPAM_ACTION_UNAPPROVE     = 'unapprove';
	const SPAM_ACTION_UNAPPROVE_BAN = 'unapprove_ban';
	const SPAM_ACTION_DELETE_AUTHOR = 'delete_author';
	const SPAM_ACTION_NONE          = 'none';
	const SPAM_ACTION_AUTO_APPROVE  = 'auto_approve';

	/**
	 * Default score thresholds (hardcoded, customizable via filters)
	 *
	 * Score ranges:
	 * - 0-40: Clean content (no spam detected)
	 * - 41-69: Uncertain (uses uncertain action setting)
	 * - 70-89: Spam suspected
	 * - 90-100: Spam detected
	 */
	const SCORE_THRESHOLD_CLEAN     = 40;  // At or below this = clean (0-40%)
	const SCORE_THRESHOLD_SUSPECTED = 70;  // At or above this = suspected (70-89%)
	const SCORE_THRESHOLD_DETECTED  = 90;  // At or above this = detected (90-100%)

	/**
	 * Content type constants
	 */
	const CONTENT_TOPIC = 'topic';
	const CONTENT_POST  = 'post';

	/**
	 * Event type constants
	 */
	const EVENT_CREATE  = 'create';
	const EVENT_EDIT    = 'edit';
	const EVENT_APPROVE = 'approve';
	const EVENT_DELETE  = 'delete';

	/**
	 * Get singleton instance
	 *
	 * @return AIContentModeration
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - private for singleton
	 */
	private function __construct() {
		// Get current board - extract boardid as int (get_current returns array or object)
		$board          = WPF()->board->get_current();
		$this->board_id = is_array( $board ) ? ( $board['boardid'] ?? 0 ) : ( $board->boardid ?? 0 );

		// Settings may not be loaded yet during wpForo initialization.
		// Register content hooks immediately (they check is_enabled() at runtime),
		// but delay handler registration until settings are available.
		$this->register_hooks();

		// Try to load settings immediately if available
		if ( $this->are_settings_available() ) {
			$this->load_settings();
			$this->register_moderation_handlers();
		} else {
			// Settings not yet loaded - register handler on settings init
			add_action( 'wpforo_settings_after_init', [ $this, 'on_settings_loaded' ], 10 );
		}
	}

	/**
	 * Check if wpForo settings are available
	 *
	 * @return bool
	 */
	private function are_settings_available() {
		return ! empty( WPF()->settings ) &&
		       property_exists( WPF()->settings, 'ai' ) &&
		       ! is_null( WPF()->settings->ai );
	}

	/**
	 * Callback for when settings are loaded
	 */
	public function on_settings_loaded() {
		$this->load_settings();
		$this->register_moderation_handlers();
	}

	/**
	 * Prevent cloning
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}

	// =========================================================================
	// SETTINGS MANAGEMENT
	// =========================================================================

	/**
	 * Load all relevant settings
	 *
	 * Loads AI settings, antispam settings, and moderation-related options
	 * from wpForo's settings system.
	 */
	private function load_settings() {
		$this->settings = [
			// AI Settings
			'ai' => [
				'enabled'            => (bool) wpforo_setting( 'ai', 'assistant' ),
				'search'             => (bool) wpforo_setting( 'ai', 'search' ),
				'search_quality'     => wpforo_setting( 'ai', 'search_quality' ),
				'translation'        => (bool) wpforo_setting( 'ai', 'translation' ),
				'topic_summary'      => (bool) wpforo_setting( 'ai', 'topic_summary' ),
				'topic_suggestions'  => (bool) wpforo_setting( 'ai', 'topic_suggestions' ),
			],

			// AI Content Moderation Settings
			'moderation' => [
				'spam'       => (bool) wpforo_setting( 'ai', 'moderation_spam' ),
				'toxicity'   => (bool) wpforo_setting( 'ai', 'moderation_toxicity' ),
				'compliance' => (bool) wpforo_setting( 'ai', 'moderation_compliance' ),
			],

			// Spam Detection Settings
			// Note: wpforo_setting() does NOT support a default value as third argument
			// The third arg is treated as a nested key. Use ?? for defaults instead.
			'spam' => [
				'quality'          => wpforo_setting( 'ai', 'moderation_spam_quality' ) ?? 'balanced',
				'use_context'      => (bool) ( wpforo_setting( 'ai', 'moderation_spam_use_context' ) ?? true ),
				'min_indexed'      => (int) ( wpforo_setting( 'ai', 'moderation_spam_min_indexed' ) ?? 100 ),
				'action_detected'  => wpforo_setting( 'ai', 'moderation_spam_action_detected' ) ?? 'unapprove_ban',
				'action_suspected' => wpforo_setting( 'ai', 'moderation_spam_action_suspected' ) ?? 'unapprove_ban',
				'action_uncertain' => wpforo_setting( 'ai', 'moderation_spam_action_uncertain' ) ?? 'unapprove',
				'action_clean'     => wpforo_setting( 'ai', 'moderation_spam_action_clean' ) ?? 'none',
				'exempt_minposts'  => (int) ( wpforo_setting( 'ai', 'moderation_spam_exempt_minposts' ) ?? 10 ),
				'autoban_unapproved' => (int) ( wpforo_setting( 'ai', 'moderation_spam_autoban_unapproved' ) ?? 5 ),
			],

			// Compliance Settings
			'compliance' => [
				'custom_policy_page' => (int) ( wpforo_setting( 'ai', 'moderation_compliance_custom_policy' ) ?? 0 ),
				'custom_rules_page'  => (int) ( wpforo_setting( 'ai', 'moderation_compliance_custom_rules' ) ?? 0 ),
				'action'             => wpforo_setting( 'ai', 'moderation_compliance_action' ) ?? 'unapprove',
			],

			// Antispam Settings
			'antispam' => [
				'spam_filter'                   => (bool) wpforo_setting( 'antispam', 'spam_filter' ),
				'spam_user_ban'                 => (bool) wpforo_setting( 'antispam', 'spam_user_ban' ),
				'should_unapprove_after_report' => (bool) wpforo_setting( 'antispam', 'should_unapprove_after_report' ),
				'spam_filter_level_topic'       => (int) wpforo_setting( 'antispam', 'spam_filter_level_topic' ),
				'spam_filter_level_post'        => (int) wpforo_setting( 'antispam', 'spam_filter_level_post' ),
				'new_user_max_posts'            => (int) wpforo_setting( 'antispam', 'new_user_max_posts' ),
				'unapprove_post_if_user_is_new' => (bool) wpforo_setting( 'antispam', 'unapprove_post_if_user_is_new' ),
				'min_number_posts_to_link'      => (int) wpforo_setting( 'antispam', 'min_number_posts_to_link' ),
				'min_number_posts_to_attach'    => (int) wpforo_setting( 'antispam', 'min_number_posts_to_attach' ),
			],

			// Akismet Settings
			'akismet' => [
				'enabled' => (bool) wpforo_setting( 'akismet', 'akismet' ),
			],

			// AI Content Moderation Settings (to be added)
			'content_moderation' => [
				'enabled'                => (bool) wpforo_get_option( 'ai_content_moderation_enabled', 0 ),
				'toxicity_detection'     => (bool) wpforo_get_option( 'ai_toxicity_detection', 0 ),
				'spam_detection'         => (bool) wpforo_get_option( 'ai_spam_detection', 0 ),
				'rule_compliance'        => (bool) wpforo_get_option( 'ai_rule_compliance', 0 ),
				'auto_approve_threshold' => (int) wpforo_get_option( 'ai_auto_approve_threshold', 80 ),
				'auto_reject_threshold'  => (int) wpforo_get_option( 'ai_auto_reject_threshold', 20 ),
			],
		];
	}

	/**
	 * Get a specific setting value
	 *
	 * @param string $group   Setting group (ai, antispam, akismet, content_moderation)
	 * @param string $key     Setting key
	 * @param mixed  $default Default value if not found
	 * @return mixed Setting value
	 */
	public function get_setting( $group, $key, $default = null ) {
		return $this->settings[ $group ][ $key ] ?? $default;
	}

	/**
	 * Get all settings for a group
	 *
	 * @param string $group Setting group
	 * @return array Settings array
	 */
	public function get_settings( $group = '' ) {
		if ( empty( $group ) ) {
			return $this->settings;
		}
		return $this->settings[ $group ] ?? [];
	}

	/**
	 * Refresh settings from database
	 *
	 * Call this after settings are updated.
	 */
	public function refresh_settings() {
		$this->load_settings();
	}

	/**
	 * Check if AI content moderation is enabled
	 *
	 * Returns true if any moderation feature is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return $this->is_spam_detection_enabled()
			|| $this->is_toxicity_detection_enabled()
			|| $this->is_compliance_enabled();
	}

	/**
	 * Check if AI spam detection is enabled
	 *
	 * @return bool
	 */
	public function is_spam_detection_enabled() {
		return $this->get_setting( 'moderation', 'spam', false );
	}

	/**
	 * Check if AI content safety & toxicity detection is enabled
	 *
	 * @return bool
	 */
	public function is_toxicity_detection_enabled() {
		return $this->get_setting( 'moderation', 'toxicity', false );
	}

	/**
	 * Check if AI rule compliance & policy enforcement is enabled
	 *
	 * @return bool
	 */
	public function is_compliance_enabled() {
		return $this->get_setting( 'moderation', 'compliance', false );
	}

	/**
	 * Get spam detection quality tier
	 *
	 * @return string Quality tier (fast, balanced, advanced, premium)
	 */
	public function get_spam_quality() {
		return $this->get_setting( 'spam', 'quality', 'balanced' );
	}

	/**
	 * Check if forum context should be used for spam detection
	 *
	 * @return bool
	 */
	public function use_spam_context() {
		return $this->get_setting( 'spam', 'use_context', true );
	}

	/**
	 * Get minimum indexed topics required for context
	 *
	 * @return int
	 */
	public function get_spam_min_indexed() {
		return $this->get_setting( 'spam', 'min_indexed', 100 );
	}

	/**
	 * Get score threshold for clean content (no spam)
	 *
	 * Scores below this threshold are considered clean.
	 * Default: 50. Customizable via 'wpforo_spam_threshold_clean' filter.
	 *
	 * @return int Score threshold (0-100)
	 */
	public function get_spam_threshold_clean() {
		return apply_filters( 'wpforo_spam_threshold_clean', self::SCORE_THRESHOLD_CLEAN );
	}

	/**
	 * Get score threshold for suspected spam
	 *
	 * Scores at or above this threshold are suspected spam.
	 * Default: 70. Customizable via 'wpforo_spam_threshold_suspected' filter.
	 *
	 * @return int Score threshold (0-100)
	 */
	public function get_spam_threshold_suspected() {
		return apply_filters( 'wpforo_spam_threshold_suspected', self::SCORE_THRESHOLD_SUSPECTED );
	}

	/**
	 * Get score threshold for detected spam
	 *
	 * Scores at or above this threshold are definite spam.
	 * Default: 90. Customizable via 'wpforo_spam_threshold_detected' filter.
	 *
	 * @return int Score threshold (0-100)
	 */
	public function get_spam_threshold_detected() {
		return apply_filters( 'wpforo_spam_threshold_detected', self::SCORE_THRESHOLD_DETECTED );
	}

	/**
	 * Get action for when spam is detected (score 90-100%)
	 *
	 * @return string Action (unapprove, unapprove_ban, delete_author)
	 */
	public function get_spam_action_detected() {
		return $this->get_setting( 'spam', 'action_detected', self::SPAM_ACTION_UNAPPROVE_BAN );
	}

	/**
	 * Get action for when spam is suspected (score 70-90%)
	 *
	 * @return string Action (unapprove, unapprove_ban, delete_author)
	 */
	public function get_spam_action_suspected() {
		return $this->get_setting( 'spam', 'action_suspected', self::SPAM_ACTION_UNAPPROVE );
	}

	/**
	 * Get action for when spam detection is uncertain (score 41-69%)
	 *
	 * @return string Action (none, unapprove, unapprove_ban, delete_author)
	 */
	public function get_spam_action_uncertain() {
		return $this->get_setting( 'spam', 'action_uncertain', self::SPAM_ACTION_UNAPPROVE );
	}

	/**
	 * Get action for when content is clean (score 0-40%)
	 *
	 * @return string Action (none, auto_approve)
	 */
	public function get_spam_action_clean() {
		return $this->get_setting( 'spam', 'action_clean', self::SPAM_ACTION_NONE );
	}

	/**
	 * Get user groups exempt from spam detection
	 *
	 * @deprecated 2.4.0 Use the "Dashboard - Moderate Topics & Posts" (aum) usergroup permission instead.
	 *                   This method now always returns an empty array.
	 *
	 * @return array Empty array (deprecated)
	 */
	public function get_spam_exempt_usergroups() {
		return [];
	}

	/**
	 * Get minimum post count for exemption from spam detection
	 *
	 * @return int Minimum post count, 0 = disabled
	 */
	public function get_spam_exempt_minposts() {
		return $this->get_setting( 'spam', 'exempt_minposts', 10 );
	}

	/**
	 * Get unapproved post count threshold for auto-ban
	 *
	 * @return int Unapproved count threshold, 0 = disabled
	 */
	public function get_spam_autoban_unapproved_threshold() {
		return (int) $this->get_setting( 'spam', 'autoban_unapproved', 5 );
	}

	/**
	 * Count user's unapproved posts
	 *
	 * @param int $userid User ID
	 * @return int Number of unapproved posts
	 */
	public function count_user_unapproved_posts( $userid ) {
		if ( ! $userid ) {
			return 0;
		}

		return (int) WPF()->db->get_var(
			WPF()->db->prepare(
				"SELECT COUNT(*) FROM " . WPF()->tables->posts . " WHERE userid = %d AND status = 1",
				$userid
			)
		);
	}

	/**
	 * Check if user is exempt from spam detection
	 *
	 * @param int $userid User ID
	 * @return bool True if user is exempt
	 */
	public function is_user_spam_exempt( $userid ) {
		if ( ! $userid ) {
			return false; // Guests are never exempt
		}

		// Check general exemption (admins, moderators)
		if ( $this->is_user_exempt( $userid ) ) {
			return true;
		}

		// Check "Dashboard - Moderate Topics & Posts" permission (aum)
		// Users with this permission bypass both standard and AI moderation
		$member = WPF()->member->get_member( $userid );
		$user_groupids = [];
		if ( ! empty( $member['groupid'] ) ) {
			$user_groupids[] = (int) $member['groupid'];
		}
		if ( ! empty( $member['secondary_groupids'] ) ) {
			$user_groupids = array_merge( $user_groupids, array_map( 'intval', (array) $member['secondary_groupids'] ) );
		}
		if ( ! empty( $user_groupids ) && WPF()->usergroup->can( 'aum', $user_groupids ) ) {
			return true;
		}

		// Check post count exemption
		$min_posts = $this->get_spam_exempt_minposts();
		if ( $min_posts > 0 ) {
			$post_count = WPF()->member->member_approved_posts( $userid );
			if ( $post_count >= $min_posts ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine action based on spam score
	 *
	 * Score ranges (customizable via filters):
	 * - 0-40: Clean content -> action_clean setting
	 * - 41-69: Uncertain -> action_uncertain setting
	 * - 70-89: Spam suspected -> action_suspected setting
	 * - 90-100: Spam detected -> action_detected setting
	 *
	 * @param int $score Spam score (0-100)
	 * @return array Action info with 'action' and 'user_action' keys
	 */
	public function get_spam_action( $score ) {
		// Get thresholds (can be customized via filters)
		$threshold_detected  = $this->get_spam_threshold_detected();
		$threshold_suspected = $this->get_spam_threshold_suspected();
		$threshold_clean     = $this->get_spam_threshold_clean();

		$result = [
			'action'      => null,      // Content action (hold, delete, approve)
			'user_action' => null,      // User action (ban_user, delete_author)
			'level'       => 'uncertain', // Score level (clean, uncertain, suspected, detected)
		];

		// Spam Detected (score 90-100%)
		if ( $score >= $threshold_detected ) {
			$result['level'] = 'detected';
			$action_setting = $this->get_spam_action_detected();
			$result = $this->map_spam_action_setting( $action_setting, $result );
		}
		// Spam Suspected (score 70-89%)
		elseif ( $score >= $threshold_suspected ) {
			$result['level'] = 'suspected';
			$action_setting = $this->get_spam_action_suspected();
			$result = $this->map_spam_action_setting( $action_setting, $result );
		}
		// Clean Content (score 0-40%)
		elseif ( $score <= $threshold_clean ) {
			$result['level'] = 'clean';
			$action_setting = $this->get_spam_action_clean();
			if ( $action_setting === self::SPAM_ACTION_AUTO_APPROVE ) {
				$result['action'] = self::ACTION_APPROVE;
			}
			// If 'none', action remains null (no action taken)
		}
		// Uncertain (score 41-69%) - use uncertain action setting
		else {
			$result['level'] = 'uncertain';
			$action_setting = $this->get_spam_action_uncertain();
			$result = $this->map_spam_action_setting( $action_setting, $result );
		}

		/**
		 * Filter the spam action result
		 *
		 * @param array $result Action result with 'action', 'user_action', and 'level'
		 * @param int   $score  Spam score (0-100)
		 */
		return apply_filters( 'wpforo_spam_action', $result, $score );
	}

	/**
	 * Map spam action setting to action constants
	 *
	 * @param string $action_setting Setting value (unapprove, unapprove_ban, delete_author)
	 * @param array  $result Current result array
	 * @return array Updated result array
	 */
	private function map_spam_action_setting( $action_setting, $result ) {
		switch ( $action_setting ) {
			case self::SPAM_ACTION_UNAPPROVE:
				$result['action'] = self::ACTION_HOLD;
				break;

			case self::SPAM_ACTION_UNAPPROVE_BAN:
				$result['action'] = self::ACTION_HOLD;
				$result['user_action'] = self::ACTION_BAN_USER;
				break;

			case self::SPAM_ACTION_DELETE_AUTHOR:
				$result['action'] = self::ACTION_DELETE;
				$result['user_action'] = 'delete_author'; // Special case: delete user with posts
				break;
		}

		return $result;
	}

	/**
	 * Get all spam detection settings
	 *
	 * @return array All spam settings
	 */
	public function get_spam_settings() {
		return $this->get_settings( 'spam' );
	}

	// =========================================================================
	// HOOK REGISTRATION
	// =========================================================================

	/**
	 * Register all content event hooks
	 *
	 * Hooks into wpForo's content lifecycle events for topics and posts.
	 */
	private function register_hooks() {
		// Topic creation hooks
		// Priority 25: Run AFTER wpForo built-in antispam (akismet=8, spam_filter=9, auto_moderate=10, remove_links=20)
		add_filter( 'wpforo_add_topic_data_filter', [ $this, 'filter_topic_on_create' ], 25, 2 );
		add_action( 'wpforo_after_add_topic', [ $this, 'on_topic_created' ], 10, 2 );

		// Topic edit hooks
		add_filter( 'wpforo_edit_topic_data_filter', [ $this, 'filter_topic_on_edit' ], 25, 2 );
		add_action( 'wpforo_after_edit_topic', [ $this, 'on_topic_edited' ], 10, 3 );

		// Topic status hooks
		add_action( 'wpforo_topic_approve', [ $this, 'on_topic_approved' ], 10, 1 );
		add_action( 'wpforo_topic_unapprove', [ $this, 'on_topic_unapproved' ], 10, 1 );
		add_action( 'wpforo_topic_status_update', [ $this, 'on_topic_status_change' ], 10, 2 );

		// Topic management hooks
		add_action( 'wpforo_before_delete_topic', [ $this, 'on_before_topic_delete' ], 10, 1 );
		add_action( 'wpforo_after_delete_topic', [ $this, 'on_topic_deleted' ], 10, 1 );
		add_action( 'wpforo_after_move_topic', [ $this, 'on_topic_moved' ], 10, 2 );
		add_action( 'wpforo_after_merge_topic', [ $this, 'on_topics_merged' ], 10, 5 );

		// Post/Reply creation hooks
		// Priority 25: Run AFTER wpForo built-in antispam (akismet=8, spam_filter=9, auto_moderate=10, remove_links=20)
		add_filter( 'wpforo_add_post_data_filter', [ $this, 'filter_post_on_create' ], 25, 1 );
		add_action( 'wpforo_after_add_post', [ $this, 'on_post_created' ], 10, 3 );

		// Post/Reply edit hooks
		add_filter( 'wpforo_edit_post_data_filter', [ $this, 'filter_post_on_edit' ], 25, 1 );
		add_action( 'wpforo_after_edit_post', [ $this, 'on_post_edited' ], 10, 4 );

		// Post/Reply status hooks
		add_action( 'wpforo_post_approve', [ $this, 'on_post_approved' ], 10, 1 );
		add_action( 'wpforo_post_unapprove', [ $this, 'on_post_unapproved' ], 10, 1 );
		add_action( 'wpforo_post_status_update', [ $this, 'on_post_status_change' ], 10, 2 );

		// Post/Reply management hooks
		add_action( 'wpforo_before_delete_post', [ $this, 'on_before_post_delete' ], 10, 1 );
		add_action( 'wpforo_after_delete_post', [ $this, 'on_post_deleted' ], 10, 1 );

		// User hooks for behavior analysis
		add_action( 'wpforo_after_ban_user', [ $this, 'on_user_banned' ], 10, 1 );
		add_action( 'wpforo_after_unban_user', [ $this, 'on_user_unbanned' ], 10, 1 );

		// Display moderation report under posts for authorized users
		add_action( 'wpforo_post_content_footer', [ $this, 'display_moderation_report' ], 10, 4 );

		// Delete moderation report when content is approved (report no longer needed)
		// Note: wpforo_post_approve fires for all posts including first posts (topics)
		add_action( 'wpforo_post_approve', [ $this, 'on_post_approved' ], 10, 1 );

		// Cron job for cleaning up old moderation logs
		add_action( 'wpforo_ai_moderation_cleanup', [ $this, 'cron_moderation_cleanup' ] );

		// Note: Moderation report styles are defined in theme style.css files
		// with proper wpForo specificity (#wpforo #wpforo-wrap prefix)

		// Note: Moderation handlers are registered separately after settings are loaded.
		// See constructor and on_settings_loaded() for handler registration.
	}

	/**
	 * Register built-in moderation handlers
	 *
	 * These handlers perform the actual AI analysis of content.
	 */
	private function register_moderation_handlers() {
		// Spam detection handler (priority 10 - first)
		if ( $this->is_spam_detection_enabled() ) {
			$this->register_handler( 'spam', [ $this, 'spam_detection_handler' ], 10 );
		}

		// Future handlers:
		// if ( $this->is_toxicity_detection_enabled() ) {
		//     $this->register_handler( 'toxicity', [ $this, 'toxicity_detection_handler' ], 20 );
		// }
	}

	// =========================================================================
	// HANDLER REGISTRATION
	// =========================================================================

	/**
	 * Register a moderation handler
	 *
	 * Handlers are called during content analysis to check for specific issues.
	 *
	 * @param string   $id       Unique handler ID
	 * @param callable $callback Callback function that receives content data
	 * @param int      $priority Priority (lower = earlier)
	 */
	public function register_handler( $id, $callback, $priority = 10 ) {
		$this->handlers[ $id ] = [
			'callback' => $callback,
			'priority' => $priority,
		];

		// Sort handlers by priority
		uasort( $this->handlers, function( $a, $b ) {
			return $a['priority'] <=> $b['priority'];
		} );
	}

	/**
	 * Unregister a moderation handler
	 *
	 * @param string $id Handler ID
	 */
	public function unregister_handler( $id ) {
		unset( $this->handlers[ $id ] );
	}

	/**
	 * Get registered handlers
	 *
	 * @return array
	 */
	public function get_handlers() {
		return $this->handlers;
	}

	// =========================================================================
	// UNIFIED MODERATION HANDLER
	// =========================================================================

	/**
	 * Unified moderation handler
	 *
	 * Calls the AI backend API to analyze content for spam, toxicity, and compliance.
	 * Uses the unified /moderation/analyze endpoint for efficiency (single LLM call).
	 *
	 * @param array $context Analysis context from build_analysis_context()
	 * @return array|null Analysis results or null on failure
	 */
	public function spam_detection_handler( $context ) {
		// Check if user is exempt from spam detection
		$exempt_minposts = $this->get_setting( 'spam', 'exempt_minposts', 10 );
		$user_post_count = $context['user_info']['post_count'] ?? 0;

		if ( $user_post_count >= $exempt_minposts ) {
			return null;
		}

		// Skip for moderators and admins
		if ( ! empty( $context['user_info']['is_moderator'] ) || ! empty( $context['user_info']['is_admin'] ) ) {
			return null;
		}

		// Get AI client
		$ai_client = $this->get_ai_client();
		if ( ! $ai_client || ! $ai_client->is_service_available() ) {
			return null;
		}

		// Check which features are enabled
		$spam_enabled     = $this->is_spam_detection_enabled();
		$toxicity_enabled = $this->is_toxicity_detection_enabled();
		$compliance_enabled = $this->is_compliance_enabled();

		// Build base request data
		$forum = $context['forum'] ?? [];

		// Build enhanced forum description with site, board, and parent forum context
		// This helps AI understand what topics are appropriate for this forum
		// Hierarchy: Site > Board > Parent Category > Current Category
		$forum_description = $forum['description'] ?? '';
		$forum_title       = $forum['title'] ?? '';

		// Get board context (what this forum installation is about)
		$board_settings    = WPF()->board->get_current( 'settings' );
		$board_title       = $board_settings['title'] ?? '';
		$board_description = $board_settings['desc'] ?? '';

		// Get site context (WordPress site info)
		$site_name        = get_bloginfo( 'name' );
		$site_description = get_bloginfo( 'description' );

		// Get parent forum context (if this forum has a parent category)
		$parent_title       = '';
		$parent_description = '';
		$parent_id          = (int) ( $forum['parentid'] ?? 0 );
		if ( $parent_id > 0 && WPF()->forum ) {
			$parent_forum = WPF()->forum->get_forum( $parent_id );
			if ( ! empty( $parent_forum ) && is_array( $parent_forum ) ) {
				$parent_title       = $parent_forum['title'] ?? '';
				$parent_description = $parent_forum['description'] ?? '';
			}
		}

		// Build context string: Site > Board > Parent Category > Current Category
		$context_parts = [];

		if ( $site_name || $site_description ) {
			$site_context = 'Website: ' . ( $site_name ?: 'Unknown' );
			if ( $site_description ) {
				$site_context .= ' - ' . $site_description;
			}
			$context_parts[] = $site_context;
		}

		if ( $board_title || $board_description ) {
			$board_context = 'Forum: ' . ( $board_title ?: 'Community' );
			if ( $board_description ) {
				$board_context .= ' - ' . $board_description;
			}
			$context_parts[] = $board_context;
		}

		// Add parent category if exists (this is the top-level category)
		if ( $parent_title || $parent_description ) {
			$parent_context = 'Parent Category: ' . ( $parent_title ?: 'General' );
			if ( $parent_description ) {
				$parent_context .= ' - ' . $parent_description;
			}
			$context_parts[] = $parent_context;
		}

		// Add current forum/category context
		if ( $forum_title || $forum_description ) {
			$current_context = 'Category: ' . ( $forum_title ?: 'General' );
			if ( $forum_description ) {
				$current_context .= ' - ' . $forum_description;
			}
			$context_parts[] = $current_context;
		}

		$forum_description = implode( '. ', $context_parts );

		$request_data = [
			'content_type' => $context['content_type'],
			'title'        => $context['title'] ?? '',
			'body'         => $context['body'] ?? '',
			'quality'      => $this->get_spam_quality(),
			'forum'        => [
				'id'          => (int) ( $forum['forumid'] ?? 0 ),
				'title'       => $forum['title'] ?? '',
				'description' => $forum_description,
				'slug'        => $forum['slug'] ?? '',
			],
			'user'         => [
				'userid'            => (int) $context['userid'],
				'display_name'      => $context['user_info']['display_name'] ?? 'User',
				'post_count'        => $user_post_count,
				'registration_days' => $this->get_user_registration_days( $context['userid'] ),
				'is_banned'         => (bool) ( $context['user_info']['status'] === 'banned' ),
				'usergroup_id'      => (int) ( $context['user_info']['groupid'] ?? 0 ),
			],
		];

		// Determine which endpoint to use
		$use_unified = $toxicity_enabled || $compliance_enabled;

		if ( $use_unified ) {
			// Use unified /moderation/analyze endpoint
			$request_data['spam'] = [
				'enabled' => $spam_enabled,
			];

			$request_data['toxicity'] = [
				'enabled'     => $toxicity_enabled,
				'sensitivity' => $this->get_toxicity_sensitivity(),
			];

			// Build compliance data with timestamps for cache validation
			// Note: sources_modified must be an object (not array) for API validation
			$sources_modified = $compliance_enabled ? $this->get_compliance_sources_modified() : null;
			$request_data['compliance'] = [
				'enabled'          => $compliance_enabled,
				'sources_modified' => $sources_modified,
			];

			// Add context settings for spam
			if ( $spam_enabled ) {
				// Forum context only works in cloud mode (local mode has no S3 Vectors index)
				$request_data['use_forum_context']  = $this->use_spam_context() && ! WPF()->vector_storage->is_local_mode();
				$request_data['min_indexed_topics'] = $this->get_setting( 'spam', 'min_indexed', 100 );
				$request_data['board_id']           = $this->board_id;
			}

			$endpoint = '/moderation/analyze';
		} else {
			// Use spam-only endpoint (more efficient)
			// Forum context only works in cloud mode (local mode has no S3 Vectors index)
			$request_data['use_forum_context']  = $this->use_spam_context() && ! WPF()->vector_storage->is_local_mode();
			$request_data['min_indexed_topics'] = $this->get_setting( 'spam', 'min_indexed', 100 );
			$request_data['board_id']           = $this->board_id;

			$endpoint = '/moderation/spam/detect';
		}

		// Make API request
		\wpforo_ai_log( 'debug', "Calling endpoint: $endpoint", 'Moderation' );
		$response = $ai_client->api_post( $endpoint, $request_data, 30 );

		\wpforo_ai_log( 'debug', 'API response: ' . ( is_wp_error( $response ) ? 'WP_Error: ' . $response->get_error_message() : wp_json_encode( $response ) ), 'Moderation' );

		// Check for errors
		if ( is_wp_error( $response ) ) {
			\wpforo_ai_log( 'error', 'API error: ' . $response->get_error_message(), 'Moderation' );
			return null;
		}

		// Process response
		if ( empty( $response['success'] ) ) {
			\wpforo_ai_log( 'error', 'API returned unsuccessful response', 'Moderation' );
			return null;
		}

		// Parse response based on endpoint used
		if ( $use_unified ) {
			return $this->parse_unified_response( $response, $spam_enabled, $toxicity_enabled, $compliance_enabled );
		} else {
			return $this->parse_spam_response( $response );
		}
	}

	/**
	 * Parse spam-only endpoint response
	 *
	 * @param array $response API response
	 * @return array Parsed result
	 */
	protected function parse_spam_response( $response ) {
		return [
			'type'             => 'spam',
			'spam_score'       => (int) ( $response['spam_score'] ?? 0 ),
			'is_spam'          => (bool) ( $response['is_spam'] ?? false ),
			'confidence'       => (float) ( $response['confidence'] ?? 0.0 ),
			'indicators'       => $response['indicators'] ?? [],
			'analysis_summary' => $response['analysis_summary'] ?? '',
			'credits_used'     => (int) ( $response['credits_used'] ?? 0 ),
			'context_used'     => (bool) ( $response['context_used'] ?? false ),
		];
	}

	/**
	 * Parse unified endpoint response
	 *
	 * @param array $response           API response
	 * @param bool  $spam_enabled       Spam detection enabled
	 * @param bool  $toxicity_enabled   Toxicity detection enabled
	 * @param bool  $compliance_enabled Compliance detection enabled
	 * @return array Parsed result
	 */
	protected function parse_unified_response( $response, $spam_enabled, $toxicity_enabled, $compliance_enabled ) {
		$result = [
			'type'         => 'unified',
			'credits_used' => (int) ( $response['credits_used'] ?? 0 ),
			'spam'         => null,
			'toxicity'     => null,
			'compliance'   => null,
		];

		// Parse spam results
		if ( $spam_enabled && isset( $response['spam'] ) ) {
			$spam = $response['spam'];
			$result['spam'] = [
				'score'      => (int) ( $spam['score'] ?? 0 ),
				'is_spam'    => (bool) ( $spam['is_spam'] ?? false ),
				'confidence' => (float) ( $spam['confidence'] ?? 0.0 ),
				'indicators' => $spam['indicators'] ?? [],
				'summary'    => $spam['summary'] ?? '',
			];
			// For backwards compatibility, set main fields
			$result['spam_score']       = $result['spam']['score'];
			$result['is_spam']          = $result['spam']['is_spam'];
			$result['confidence']       = $result['spam']['confidence'];
			$result['indicators']       = $result['spam']['indicators'];
			$result['analysis_summary'] = $result['spam']['summary'];
		}

		// Parse toxicity results
		if ( $toxicity_enabled && isset( $response['toxicity'] ) ) {
			$toxicity = $response['toxicity'];
			$result['toxicity'] = [
				'score'      => (int) ( $toxicity['score'] ?? 0 ),
				'is_toxic'   => (bool) ( $toxicity['is_toxic'] ?? false ),
				'confidence' => (float) ( $toxicity['confidence'] ?? 0.0 ),
				'categories' => $toxicity['categories'] ?? [],
				'summary'    => $toxicity['summary'] ?? '',
			];
		}

		// Parse compliance results
		if ( $compliance_enabled && isset( $response['compliance'] ) ) {
			$compliance = $response['compliance'];
			$result['compliance'] = [
				'score'        => (int) ( $compliance['score'] ?? 0 ),
				'is_compliant' => (bool) ( $compliance['is_compliant'] ?? true ),
				'confidence'   => (float) ( $compliance['confidence'] ?? 0.0 ),
				'violations'   => $compliance['violations'] ?? [],
				'summary'      => $compliance['summary'] ?? '',
			];
		}

		// Parse overall results (contains action and primary_reason)
		if ( isset( $response['overall'] ) ) {
			$overall = $response['overall'];
			$result['overall'] = [
				'action'         => $overall['action'] ?? 'review',
				'primary_reason' => $overall['primary_reason'] ?? 'none',
				'summary'        => $overall['summary'] ?? '',
			];
		}

		return $result;
	}

	/**
	 * Get toxicity detection sensitivity setting
	 *
	 * @return string Sensitivity level (low, medium, high)
	 */
	public function get_toxicity_sensitivity() {
		return wpforo_setting( 'ai', 'moderation_toxicity_sensitivity' ) ?? 'medium';
	}

	/**
	 * Get toxicity action setting
	 *
	 * @return string Action (none, unapprove, unapprove_ban)
	 */
	public function get_toxicity_action() {
		return wpforo_setting( 'ai', 'moderation_toxicity_action' ) ?? 'unapprove';
	}

	/**
	 * Get compliance action setting
	 *
	 * @return string Action (none, unapprove, unapprove_ban)
	 */
	public function get_compliance_action() {
		return $this->get_setting( 'compliance', 'action', 'unapprove' );
	}

	/**
	 * Get all compliance content sources with their content and timestamps
	 *
	 * Gathers content from:
	 * - Built-in forum privacy policy (if enabled)
	 * - Built-in forum rules (if enabled)
	 * - Custom policy page (if selected)
	 * - Custom rules page (if selected)
	 *
	 * @return array Array of sources with type, content, and modified timestamp
	 */
	public function get_compliance_sources() {
		$sources = [];
		$legal   = WPF()->settings->legal;

		// Built-in forum privacy policy
		if ( ! empty( $legal['checkbox_forum_privacy'] ) && ! empty( $legal['forum_privacy_text'] ) ) {
			$sources[] = [
				'type'     => 'builtin_policy',
				'content'  => wp_strip_all_tags( $legal['forum_privacy_text'] ),
				'modified' => $this->get_option_modified_time( 'wpforo_legal' ),
			];
		}

		// Built-in forum rules
		if ( ! empty( $legal['rules_checkbox'] ) && ! empty( $legal['rules_text'] ) ) {
			$sources[] = [
				'type'     => 'builtin_rules',
				'content'  => wp_strip_all_tags( $legal['rules_text'] ),
				'modified' => $this->get_option_modified_time( 'wpforo_legal' ),
			];
		}

		// Custom policy page
		$custom_policy_id = $this->get_setting( 'compliance', 'custom_policy_page', 0 );
		if ( $custom_policy_id ) {
			$page = get_post( $custom_policy_id );
			if ( $page && $page->post_status === 'publish' ) {
				$sources[] = [
					'type'     => 'custom_policy',
					'content'  => wp_strip_all_tags( $page->post_content ),
					'modified' => strtotime( $page->post_modified_gmt ),
				];
			}
		}

		// Custom rules page
		$custom_rules_id = $this->get_setting( 'compliance', 'custom_rules_page', 0 );
		if ( $custom_rules_id ) {
			$page = get_post( $custom_rules_id );
			if ( $page && $page->post_status === 'publish' ) {
				$sources[] = [
					'type'     => 'custom_rules',
					'content'  => wp_strip_all_tags( $page->post_content ),
					'modified' => strtotime( $page->post_modified_gmt ),
				];
			}
		}

		return $sources;
	}

	/**
	 * Get just the modification timestamps for compliance sources
	 *
	 * Used for checking if cached rules are still valid.
	 *
	 * @return array Associative array of source type => timestamp (or null if not configured)
	 */
	public function get_compliance_sources_modified() {
		$legal     = WPF()->settings->legal;
		$modified  = [];

		// Built-in policy
		$modified['builtin_policy'] = ( ! empty( $legal['checkbox_forum_privacy'] ) && ! empty( $legal['forum_privacy_text'] ) )
			? $this->get_option_modified_time( 'wpforo_legal' )
			: null;

		// Built-in rules
		$modified['builtin_rules'] = ( ! empty( $legal['rules_checkbox'] ) && ! empty( $legal['rules_text'] ) )
			? $this->get_option_modified_time( 'wpforo_legal' )
			: null;

		// Custom policy page
		$custom_policy_id = $this->get_setting( 'compliance', 'custom_policy_page', 0 );
		if ( $custom_policy_id ) {
			$page = get_post( $custom_policy_id );
			$modified['custom_policy'] = ( $page && $page->post_status === 'publish' )
				? strtotime( $page->post_modified_gmt )
				: null;
		} else {
			$modified['custom_policy'] = null;
		}

		// Custom rules page
		$custom_rules_id = $this->get_setting( 'compliance', 'custom_rules_page', 0 );
		if ( $custom_rules_id ) {
			$page = get_post( $custom_rules_id );
			$modified['custom_rules'] = ( $page && $page->post_status === 'publish' )
				? strtotime( $page->post_modified_gmt )
				: null;
		} else {
			$modified['custom_rules'] = null;
		}

		return $modified;
	}

	/**
	 * Get the last modified time for a WordPress option
	 *
	 * Since options don't have a modified timestamp, we use a custom option
	 * that's updated when settings are saved.
	 *
	 * @param string $option_name Option name
	 * @return int Unix timestamp or 0 if not tracked
	 */
	protected function get_option_modified_time( $option_name ) {
		// We store a timestamp when legal settings are saved
		$modified_key = $option_name . '_modified';
		$modified     = get_option( $modified_key, 0 );

		// If not tracked, use a fallback (settings init time or current time)
		if ( ! $modified ) {
			// Store current time as initial timestamp
			$modified = time();
			update_option( $modified_key, $modified, false );
		}

		return (int) $modified;
	}

	/**
	 * Check if compliance sources have content
	 *
	 * @return bool True if at least one compliance source is configured
	 */
	public function has_compliance_sources() {
		$sources = $this->get_compliance_sources();
		return ! empty( $sources );
	}

	/**
	 * Sync compliance rules with the backend
	 *
	 * Sends all policy/rules content to the backend for rule extraction.
	 * The backend uses AI to extract keywords and patterns.
	 *
	 * @return array|WP_Error Sync result or error
	 */
	public function sync_compliance_rules() {
		$ai_client = $this->get_ai_client();
		if ( ! $ai_client || ! $ai_client->is_service_available() ) {
			return new \WP_Error( 'not_connected', wpforo_phrase( 'AI service not available', false ) );
		}

		$sources = $this->get_compliance_sources();
		if ( empty( $sources ) ) {
			return new \WP_Error( 'no_sources', wpforo_phrase( 'No policy or rules content configured', false ) );
		}

		// Send to backend for rule extraction
		$response = $ai_client->api_post( '/moderation/compliance/sync', [
			'sources' => $sources,
		], 60 ); // Longer timeout for AI extraction

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['success'] ) ) {
			return new \WP_Error(
				'sync_failed',
				$response['error'] ?? wpforo_phrase( 'Failed to sync compliance rules', false )
			);
		}

		// Store sync timestamp locally
		update_option( 'wpforo_compliance_last_synced', time(), false );
		update_option( 'wpforo_compliance_sources_hash', $response['content_hash'] ?? '', false );

		return [
			'success'      => true,
			'content_hash' => $response['content_hash'] ?? '',
			'synced_at'    => time(),
			'rules_count'  => $response['rules_count'] ?? 0,
		];
	}

	/**
	 * Get user registration days
	 *
	 * @param int $userid User ID
	 * @return int Days since registration
	 */
	protected function get_user_registration_days( $userid ) {
		if ( ! $userid ) {
			return 0;
		}

		$user = get_userdata( $userid );
		if ( ! $user || empty( $user->user_registered ) ) {
			return 0;
		}

		$registered = strtotime( $user->user_registered );
		$now        = time();
		$days       = floor( ( $now - $registered ) / DAY_IN_SECONDS );

		return max( 0, (int) $days );
	}

	// =========================================================================
	// CONTENT FILTERS (Pre-save)
	// =========================================================================

	/**
	 * Check if user has any unapproved posts
	 *
	 * If user has unapproved posts, their new content should also be unapproved
	 * without spending credits on AI spam detection. This prevents spam users
	 * from flooding the system while waiting for moderation.
	 *
	 * @param int $userid User ID
	 * @return bool True if user has unapproved posts
	 */
	protected function user_has_unapproved_posts( $userid ) {
		if ( ! $userid ) {
			return false; // Guests don't have post history
		}

		// Use wpForo's moderation class if available
		if ( isset( WPF()->moderation ) && method_exists( WPF()->moderation, 'has_unapproved' ) ) {
			return WPF()->moderation->has_unapproved( $userid );
		}

		// Fallback: direct database check
		global $wpdb;
		$has_unapproved = WPF()->db->get_var(
			WPF()->db->prepare(
				"SELECT postid FROM " . WPF()->tables->posts . " WHERE userid = %d AND status = 1 LIMIT 1",
				$userid
			)
		);

		return ! empty( $has_unapproved );
	}

	/**
	 * Filter topic data on creation
	 *
	 * Called before topic is saved to database.
	 * Can modify content, set status, or block creation.
	 *
	 * Note: This filter runs at priority 25, AFTER wpForo's built-in antispam features.
	 * If the topic is already unapproved (status=1), we skip AI processing to save resources.
	 *
	 * @param array $args  Topic data
	 * @param array $forum Forum data
	 * @return array Modified topic data (or empty to block)
	 */
	public function filter_topic_on_create( $args, $forum ) {
		if ( ! $this->is_enabled() || empty( $args ) ) {
			return $args;
		}

		// Skip AI moderation for AI-generated content (created by AI Tasks)
		if ( ! empty( $args['is_ai_generated'] ) ) {
			return $args;
		}

		// Skip AI moderation if content is already unapproved by wpForo built-in antispam
		// This saves credits and resources - no need to double-check already flagged content
		if ( isset( $args['status'] ) && (int) $args['status'] === 1 ) {
			// If unapproved due to flood protection, log the specific reason
			if ( ! empty( $args['_flood_reason'] ) ) {
				$userid = $args['userid'] ?? WPF()->current_userid;
				$flood_reason = $args['_flood_reason'];
				$analysis_summary = $this->get_flood_moderation_message( $flood_reason );

				$log_data = [
					'content_type'     => self::CONTENT_TOPIC,
					'content_id'       => 0, // Not saved yet
					'topicid'          => 0,
					'forumid'          => $forum['forumid'] ?? 0,
					'userid'           => $userid,
					'moderation_type'  => 'flood',
					'score'            => 100,
					'is_flagged'       => 1,
					'confidence'       => 1.0,
					'action_taken'     => 'unapprove',
					'action_reason'    => 'flood_' . $flood_reason,
					'analysis_summary' => $analysis_summary,
					'quality_tier'     => 'rule_based',
					'credits_used'     => 0,
					'content_preview'  => isset( $args['title'] ) ? wp_trim_words( $args['title'], 20 ) : null,
				];
				$this->save_moderation_log( $log_data );

				// Also log to AI Logs
				$this->log_flood_to_ai_logs( 'topic', $userid, $flood_reason, $analysis_summary, $log_data );
			}
			return $args;
		}

		// Skip AI moderation for users exempt from spam detection
		// This includes users with "Dashboard - Moderate Topics & Posts" (aum) permission
		$userid = $args['userid'] ?? WPF()->current_userid;
		if ( $this->is_user_spam_exempt( $userid ) ) {
			return $args;
		}

		// If user has ANY unapproved posts, auto-unapprove new content without AI check
		// This saves credits and prevents spam flooding while waiting for moderation
		if ( $userid && $this->user_has_unapproved_posts( $userid ) ) {
			$args['status'] = 1;

			// Check if user should be auto-banned based on unapproved posts count
			$unapproved_count   = $this->count_user_unapproved_posts( $userid );
			$autoban_threshold  = $this->get_spam_autoban_unapproved_threshold();
			$should_ban         = $autoban_threshold > 0 && ( $unapproved_count + 1 ) >= $autoban_threshold;
			$action_taken       = $should_ban ? 'unapprove_ban' : 'unapprove';
			$action_reason      = $should_ban ? 'autoban_unapproved_threshold' : 'user_has_unapproved_posts';
			$analysis_summary   = $should_ban
				? sprintf(
					wpforo_phrase( 'User auto-banned: reached %d unapproved posts (threshold: %d). Content auto-unapproved.', false ),
					$unapproved_count + 1,
					$autoban_threshold
				)
				: wpforo_phrase( 'Content auto-unapproved because user has existing unapproved posts awaiting moderation.', false );

			// Log this decision to the moderation table
			$this->save_moderation_log( [
				'content_type'     => self::CONTENT_TOPIC,
				'content_id'       => 0, // Not saved yet
				'topicid'          => 0,
				'forumid'          => $forum['forumid'] ?? 0,
				'userid'           => $userid,
				'moderation_type'  => 'spam',
				'score'            => 100,
				'is_flagged'       => 1,
				'confidence'       => 1.0,
				'action_taken'     => $action_taken,
				'action_reason'    => $action_reason,
				'analysis_summary' => $analysis_summary,
				'quality_tier'     => 'rule_based',
				'credits_used'     => 0,
				'content_preview'  => isset( $args['title'] ) ? wp_trim_words( $args['title'], 20 ) : null,
			] );

			// Ban user if threshold reached
			if ( $should_ban ) {
				$this->ban_user( $userid, $analysis_summary );
			}

			return $args;
		}

		return $this->analyze_content( $args, self::CONTENT_TOPIC, self::EVENT_CREATE, [
			'forum' => $forum,
		] );
	}

	/**
	 * Filter topic data on edit
	 *
	 * Called before topic is updated in database.
	 *
	 * Note: This filter runs at priority 25, AFTER wpForo's built-in antispam features.
	 * If the topic is already unapproved (status=1), we skip AI processing to save resources.
	 *
	 * @param array $args  Topic data
	 * @param array $forum Forum data
	 * @return array Modified topic data
	 */
	public function filter_topic_on_edit( $args, $forum ) {
		if ( ! $this->is_enabled() || empty( $args ) ) {
			return $args;
		}

		// Skip AI moderation if content is already unapproved by wpForo built-in antispam
		if ( isset( $args['status'] ) && (int) $args['status'] === 1 ) {
			return $args;
		}

		// Skip AI moderation for users exempt from spam detection
		// This includes users with "Dashboard - Moderate Topics & Posts" (aum) permission
		$userid = $args['userid'] ?? WPF()->current_userid;
		if ( $this->is_user_spam_exempt( $userid ) ) {
			return $args;
		}

		return $this->analyze_content( $args, self::CONTENT_TOPIC, self::EVENT_EDIT, [
			'forum' => $forum,
		] );
	}

	/**
	 * Filter post data on creation
	 *
	 * Called before post is saved to database.
	 *
	 * Note: This filter runs at priority 25, AFTER wpForo's built-in antispam features.
	 * If the post is already unapproved (status=1), we skip AI processing to save resources.
	 *
	 * @param array $post Post data
	 * @return array Modified post data (or empty to block)
	 */
	public function filter_post_on_create( $post ) {
		if ( ! $this->is_enabled() || empty( $post ) ) {
			return $post;
		}

		// Skip AI moderation for AI-generated content (created by AI Tasks)
		if ( ! empty( $post['is_ai_generated'] ) ) {
			return $post;
		}

		// Skip AI moderation if content is already unapproved by wpForo built-in antispam
		// This saves credits and resources - no need to double-check already flagged content
		if ( isset( $post['status'] ) && (int) $post['status'] === 1 ) {
			// If unapproved due to flood protection, log the specific reason
			if ( ! empty( $post['_flood_reason'] ) ) {
				$userid = $post['userid'] ?? WPF()->current_userid;
				$flood_reason = $post['_flood_reason'];
				$analysis_summary = $this->get_flood_moderation_message( $flood_reason );

				$log_data = [
					'content_type'     => self::CONTENT_POST,
					'content_id'       => 0, // Not saved yet
					'topicid'          => $post['topicid'] ?? 0,
					'forumid'          => $post['forumid'] ?? 0,
					'userid'           => $userid,
					'moderation_type'  => 'flood',
					'score'            => 100,
					'is_flagged'       => 1,
					'confidence'       => 1.0,
					'action_taken'     => 'unapprove',
					'action_reason'    => 'flood_' . $flood_reason,
					'analysis_summary' => $analysis_summary,
					'quality_tier'     => 'rule_based',
					'credits_used'     => 0,
					'content_preview'  => isset( $post['body'] ) ? wp_trim_words( wp_strip_all_tags( $post['body'] ), 20 ) : null,
				];
				$this->save_moderation_log( $log_data );

				// Also log to AI Logs
				$this->log_flood_to_ai_logs( 'post', $userid, $flood_reason, $analysis_summary, $log_data );
			}
			return $post;
		}

		// Skip AI moderation for users exempt from spam detection
		// This includes users with "Dashboard - Moderate Topics & Posts" (aum) permission
		$userid = $post['userid'] ?? WPF()->current_userid;
		if ( $this->is_user_spam_exempt( $userid ) ) {
			return $post;
		}

		// If user has ANY unapproved posts, auto-unapprove new content without AI check
		// This saves credits and prevents spam flooding while waiting for moderation
		if ( $userid && $this->user_has_unapproved_posts( $userid ) ) {
			$post['status'] = 1;

			// Check if user should be auto-banned based on unapproved posts count
			$unapproved_count   = $this->count_user_unapproved_posts( $userid );
			$autoban_threshold  = $this->get_spam_autoban_unapproved_threshold();
			$should_ban         = $autoban_threshold > 0 && ( $unapproved_count + 1 ) >= $autoban_threshold;
			$action_taken       = $should_ban ? 'unapprove_ban' : 'unapprove';
			$action_reason      = $should_ban ? 'autoban_unapproved_threshold' : 'user_has_unapproved_posts';
			$analysis_summary   = $should_ban
				? sprintf(
					wpforo_phrase( 'User auto-banned: reached %d unapproved posts (threshold: %d). Content auto-unapproved.', false ),
					$unapproved_count + 1,
					$autoban_threshold
				)
				: wpforo_phrase( 'Content auto-unapproved because user has existing unapproved posts awaiting moderation.', false );

			// Log this decision to the moderation table
			$this->save_moderation_log( [
				'content_type'     => self::CONTENT_POST,
				'content_id'       => 0, // Not saved yet
				'topicid'          => $post['topicid'] ?? 0,
				'forumid'          => $post['forumid'] ?? 0,
				'userid'           => $userid,
				'moderation_type'  => 'spam',
				'score'            => 100,
				'is_flagged'       => 1,
				'confidence'       => 1.0,
				'action_taken'     => $action_taken,
				'action_reason'    => $action_reason,
				'analysis_summary' => $analysis_summary,
				'quality_tier'     => 'rule_based',
				'credits_used'     => 0,
				'content_preview'  => isset( $post['body'] ) ? wp_trim_words( wp_strip_all_tags( $post['body'] ), 20 ) : null,
			] );

			// Ban user if threshold reached
			if ( $should_ban ) {
				$this->ban_user( $userid, $analysis_summary );
			}

			return $post;
		}

		// Get forum context for proper logging
		$forum_context = [];
		if ( ! empty( $post['forumid'] ) ) {
			$forum_context['forum'] = wpforo_forum( $post['forumid'] );
		}

		return $this->analyze_content( $post, self::CONTENT_POST, self::EVENT_CREATE, $forum_context );
	}

	/**
	 * Filter post data on edit
	 *
	 * Called before post is updated in database.
	 *
	 * Note: This filter runs at priority 25, AFTER wpForo's built-in antispam features.
	 * If the post is already unapproved (status=1), we skip AI processing to save resources.
	 *
	 * @param array $args Post data
	 * @return array Modified post data
	 */
	public function filter_post_on_edit( $args ) {
		if ( ! $this->is_enabled() || empty( $args ) ) {
			return $args;
		}

		// Skip AI moderation if content is already unapproved by wpForo built-in antispam
		if ( isset( $args['status'] ) && (int) $args['status'] === 1 ) {
			return $args;
		}

		// Skip AI moderation for users exempt from spam detection
		// This includes users with "Front - Can pass moderation" (aup) permission
		$userid = $args['userid'] ?? WPF()->current_userid;
		if ( $this->is_user_spam_exempt( $userid ) ) {
			return $args;
		}

		// Get forum context for proper logging
		$forum_context = [];
		if ( ! empty( $args['forumid'] ) ) {
			$forum_context['forum'] = wpforo_forum( $args['forumid'] );
		}

		return $this->analyze_content( $args, self::CONTENT_POST, self::EVENT_EDIT, $forum_context );
	}

	// =========================================================================
	// CONTENT ANALYSIS (Core Logic)
	// =========================================================================

	/**
	 * Analyze content through registered handlers
	 *
	 * This is the main entry point for content analysis.
	 * Runs content through all registered handlers and applies moderation decisions.
	 *
	 * @param array  $data         Content data (topic or post array)
	 * @param string $content_type Content type (topic or post)
	 * @param string $event_type   Event type (create, edit, approve)
	 * @param array  $context      Additional context (forum, etc.)
	 * @return array Modified content data
	 */
	protected function analyze_content( $data, $content_type, $event_type, $context = [] ) {
		// Build analysis context
		$analysis_context = $this->build_analysis_context( $data, $content_type, $event_type, $context );

		// Run through all registered handlers
		$results = [];
		foreach ( $this->handlers as $id => $handler ) {
			$result = call_user_func( $handler['callback'], $analysis_context );
			if ( ! empty( $result ) ) {
				$results[ $id ] = $result;
			}
		}

		// Allow external filtering of analysis results
		$results = apply_filters( 'wpforo_ai_moderation_results', $results, $analysis_context );

		// Make moderation decision based on results
		$decision = $this->make_decision( $results, $analysis_context );

		// Execute decision actions
		$data = $this->execute_decision( $data, $decision, $analysis_context );

		// Log the moderation action
		$this->log_moderation( $analysis_context, $results, $decision );

		return $data;
	}

	/**
	 * Build analysis context for handlers
	 *
	 * @param array  $data         Content data
	 * @param string $content_type Content type
	 * @param string $event_type   Event type
	 * @param array  $context      Additional context
	 * @return array Analysis context
	 */
	protected function build_analysis_context( $data, $content_type, $event_type, $context = [] ) {
		$userid = $data['userid'] ?? get_current_user_id();

		// Get user info and trust level
		$user_info = $this->get_user_moderation_info( $userid );

		return [
			'content_type' => $content_type,
			'event_type'   => $event_type,
			'data'         => $data,
			'title'        => $data['title'] ?? '',
			'body'         => $data['body'] ?? '',
			'userid'       => $userid,
			'user_info'    => $user_info,
			'forum'        => $context['forum'] ?? null,
			'board_id'     => $this->board_id,
			'settings'     => $this->settings,
			'timestamp'    => current_time( 'mysql' ),
		];
	}

	/**
	 * Get user information relevant to moderation
	 *
	 * @param int $userid User ID
	 * @return array User moderation info
	 */
	protected function get_user_moderation_info( $userid ) {
		if ( ! $userid ) {
			return [
				'is_guest'      => true,
				'is_new'        => true,
				'is_trusted'    => false,
				'is_moderator'  => false,
				'post_count'    => 0,
				'trust_level'   => 0,
				'points'        => 0,
				'status'        => 'guest',
				'warnings'      => 0,
			];
		}

		$member = WPF()->member->get_member( $userid );
		if ( empty( $member ) ) {
			return [
				'is_guest'      => true,
				'is_new'        => true,
				'is_trusted'    => false,
				'is_moderator'  => false,
				'is_admin'      => false,
				'post_count'    => 0,
				'trust_level'   => 0,
				'points'        => 0,
				'status'        => 'unknown',
				'warnings'      => 0,
			];
		}

		$post_count = (int) wpfval( $member, 'posts', 0 );
		$points = (float) wpfval( $member, 'points', 0 );
		$rating = wpfval( $member, 'rating', [] );
		$trust_level = (int) wpfval( $rating, 'level', 0 );
		$new_user_threshold = $this->get_setting( 'antispam', 'new_user_max_posts', 3 );

		// Get display name
		$user         = get_userdata( $userid );
		$display_name = $user ? $user->display_name : 'User';

		return [
			'is_guest'      => false,
			'is_new'        => $post_count < $new_user_threshold,
			'is_trusted'    => $trust_level >= 3, // Trusted Member level
			'is_moderator'  => WPF()->usergroup->can( 'em' ), // Edit members permission
			'is_admin'      => WPF()->usergroup->can( 'ms' ), // Manage settings permission
			'post_count'    => $post_count,
			'trust_level'   => $trust_level,
			'points'        => $points,
			'status'        => $member['status'] ?? 'active',
			'warnings'      => $this->get_user_warning_count( $userid ),
			'display_name'  => $display_name,
			'groupid'       => (int) wpfval( $member, 'groupid', 0 ),
			'member'        => $member,
		];
	}

	/**
	 * Get user warning count
	 *
	 * @param int $userid User ID
	 * @return int Warning count
	 */
	protected function get_user_warning_count( $userid ) {
		// TODO: Implement warning tracking
		return 0;
	}

	/**
	 * Make moderation decision based on handler results
	 *
	 * @param array $results         Handler results
	 * @param array $analysis_context Analysis context
	 * @return array Decision with action and reason
	 */
	protected function make_decision( $results, $analysis_context ) {
		// Default: approve content
		$decision = [
			'action'         => self::ACTION_APPROVE,
			'reason'         => '',
			'primary_reason' => 'none',
			'confidence'     => 100,
			'details'        => [],
			'spam_score'     => 0,
			'indicators'     => [],
			'credits_used'   => 0,
		];

		// Process spam detection results
		if ( ! empty( $results['spam'] ) ) {
			$spam_result = $results['spam'];
			$spam_score  = $spam_result['spam_score'] ?? 0;
			$is_spam     = $spam_result['is_spam'] ?? false;
			$confidence  = $spam_result['confidence'] ?? 0.0;

			$decision['spam_score']   = $spam_score;
			$decision['confidence']   = (int) ( $confidence * 100 );
			$decision['indicators']   = $spam_result['indicators'] ?? [];
			$decision['credits_used'] = $spam_result['credits_used'] ?? 0;
			$decision['details']      = [
				'type'             => 'spam',
				'analysis_summary' => $spam_result['analysis_summary'] ?? '',
				'context_used'     => $spam_result['context_used'] ?? false,
			];

			// Determine action based on spam score AND is_spam flag
			// The AI returns both a score AND a boolean is_spam judgment
			// We trust the is_spam flag when confidence is high enough
			$threshold_detected  = $this->get_spam_threshold_detected();  // 90
			$threshold_suspected = $this->get_spam_threshold_suspected(); // 70
			$threshold_clean     = $this->get_spam_threshold_clean();     // 50

			// If AI explicitly says is_spam=true with decent confidence (>= 60%),
			// treat as suspected even if score is below threshold
			$ai_flag_threshold = 60; // Minimum confidence to trust is_spam flag
			$trust_ai_flag     = $is_spam && ( $confidence * 100 ) >= $ai_flag_threshold && $spam_score > $threshold_clean;

			if ( $spam_score >= $threshold_detected ) {
				// High confidence spam - use detected action
				$action = $this->get_spam_action_detected();
				$decision['reason'] = sprintf(
					wpforo_phrase( 'Spam detected (score: %d%%). %s', false ),
					$spam_score,
					$spam_result['analysis_summary'] ?? ''
				);
				$decision = $this->apply_spam_action( $decision, $action, 'detected' );

			} elseif ( $spam_score >= $threshold_suspected ) {
				// Suspicious content - use suspected action
				$action = $this->get_spam_action_suspected();
				$decision['reason'] = sprintf(
					wpforo_phrase( 'Spam suspected (score: %d%%). %s', false ),
					$spam_score,
					$spam_result['analysis_summary'] ?? ''
				);
				$decision = $this->apply_spam_action( $decision, $action, 'suspected' );

			} elseif ( $spam_score <= $threshold_clean ) {
				// Clean content - use clean action
				$action = $this->get_spam_action_clean();
				$decision['reason'] = sprintf(
					wpforo_phrase( 'Content passed spam check (score: %d%%).', false ),
					$spam_score
				);
				$decision = $this->apply_spam_action( $decision, $action, 'clean' );

			} elseif ( $trust_ai_flag ) {
				// AI says is_spam=true with confidence, treat as suspected (override uncertain)
				$action = $this->get_spam_action_suspected();
				$decision['reason'] = sprintf(
					wpforo_phrase( 'AI flagged as spam (score: %d%%, confidence: %d%%). %s', false ),
					$spam_score,
					(int) ( $confidence * 100 ),
					$spam_result['analysis_summary'] ?? ''
				);
				$decision = $this->apply_spam_action( $decision, $action, 'suspected' );

			} else {
				// Uncertain (score 41-69%) - use uncertain action setting
				$action = $this->get_spam_action_uncertain();
				$decision['reason'] = sprintf(
					wpforo_phrase( 'Spam uncertain (score: %d%%). %s', false ),
					$spam_score,
					$spam_result['analysis_summary'] ?? ''
				);
				$decision = $this->apply_spam_action( $decision, $action, 'uncertain' );
			}
		}

		// Process toxicity detection results (if enabled)
		// Toxicity can override approve decision, but not a more severe action
		if ( ! empty( $results['spam']['toxicity'] ) ) {
			$toxicity_result = $results['spam']['toxicity'];
			$is_toxic = $toxicity_result['is_toxic'] ?? false;

			if ( $is_toxic ) {
				$toxicity_score = $toxicity_result['score'] ?? 0;
				$toxicity_action = $this->get_toxicity_action();

				// Only apply toxicity action if it's more severe than current decision
				// or if content was approved
				$should_apply = ( $decision['action'] === self::ACTION_APPROVE );

				if ( $should_apply ) {
					$decision['toxicity_score'] = $toxicity_score;
					$decision['toxicity_categories'] = $toxicity_result['categories'] ?? [];
					$decision['details']['toxicity'] = [
						'summary' => $toxicity_result['summary'] ?? '',
						'categories' => $toxicity_result['categories'] ?? [],
					];

					switch ( $toxicity_action ) {
						case 'unapprove':
							$decision['action']         = self::ACTION_HOLD;
							$decision['primary_reason'] = 'toxicity';
							$decision['reason'] = sprintf(
								wpforo_phrase( 'Toxic content detected (score: %d%%). %s', false ),
								$toxicity_score,
								$toxicity_result['summary'] ?? ''
							);
							break;

						case 'unapprove_ban':
							$decision['action']         = self::ACTION_HOLD;
							$decision['user_action']    = self::ACTION_BAN_USER;
							$decision['primary_reason'] = 'toxicity';
							$decision['reason'] = sprintf(
								wpforo_phrase( 'Toxic content detected (score: %d%%). User banned. %s', false ),
								$toxicity_score,
								$toxicity_result['summary'] ?? ''
							);
							break;

						case 'none':
						default:
							// Log but take no action
							$decision['reason'] .= sprintf(
								wpforo_phrase( ' Toxicity noted (score: %d%%).', false ),
								$toxicity_score
							);
							break;
					}
				} else {
					// Append toxicity info to reason
					$decision['reason'] .= sprintf(
						wpforo_phrase( ' Also toxic (score: %d%%).', false ),
						$toxicity_result['score'] ?? 0
					);
				}
			}
		}

		// Process compliance results (if enabled)
		// Compliance can override approve decision, but not a more severe action
		if ( ! empty( $results['spam']['compliance'] ) ) {
			$compliance_result = $results['spam']['compliance'];
			$is_compliant = $compliance_result['is_compliant'] ?? true;

			if ( ! $is_compliant ) {
				$compliance_score = $compliance_result['score'] ?? 0;
				$compliance_action = $this->get_compliance_action();
				$violations = $compliance_result['violations'] ?? [];

				// Only apply compliance action if content was approved
				$should_apply = ( $decision['action'] === self::ACTION_APPROVE );

				if ( $should_apply ) {
					$decision['compliance_score'] = $compliance_score;
					$decision['compliance_violations'] = $violations;
					$decision['details']['compliance'] = [
						'summary'    => $compliance_result['summary'] ?? '',
						'violations' => $violations,
					];

					switch ( $compliance_action ) {
						case 'unapprove':
							$decision['action']         = self::ACTION_HOLD;
							$decision['primary_reason'] = 'compliance';
							$decision['reason'] = sprintf(
								wpforo_phrase( 'Policy violation detected (score: %d%%). %s', false ),
								$compliance_score,
								$compliance_result['summary'] ?? ''
							);
							break;

						case 'unapprove_ban':
							$decision['action']         = self::ACTION_HOLD;
							$decision['user_action']    = self::ACTION_BAN_USER;
							$decision['primary_reason'] = 'compliance';
							$decision['reason'] = sprintf(
								wpforo_phrase( 'Policy violation detected (score: %d%%). User banned. %s', false ),
								$compliance_score,
								$compliance_result['summary'] ?? ''
							);
							break;

						case 'none':
						default:
							// Log but take no action
							$decision['reason'] .= sprintf(
								wpforo_phrase( ' Policy violation noted (score: %d%%).', false ),
								$compliance_score
							);
							break;
					}
				} else {
					// Append compliance info to reason
					$decision['reason'] .= sprintf(
						wpforo_phrase( ' Also violates policy (score: %d%%).', false ),
						$compliance_result['score'] ?? 0
					);
				}
			}
		}

		// Allow external decision making (can override our decision)
		$decision = apply_filters( 'wpforo_ai_moderation_decision', $decision, $results, $analysis_context );

		\wpforo_ai_log( 'info', 'Final decision: action=' . $decision['action'] . ', reason=' . $decision['reason'], 'Moderation' );
		return $decision;
	}

	/**
	 * Apply spam action to decision
	 *
	 * @param array  $decision Decision array
	 * @param string $action   Action setting (unapprove, unapprove_ban, delete_author, none, auto_approve)
	 * @param string $level    Detection level (detected, suspected, clean)
	 * @return array Modified decision
	 */
	protected function apply_spam_action( $decision, $action, $level ) {
		switch ( $action ) {
			case self::SPAM_ACTION_UNAPPROVE:
				$decision['action']         = self::ACTION_HOLD;
				$decision['primary_reason'] = 'spam';
				break;

			case self::SPAM_ACTION_UNAPPROVE_BAN:
				$decision['action']         = self::ACTION_HOLD;
				$decision['user_action']    = self::ACTION_BAN_USER;
				$decision['primary_reason'] = 'spam';
				break;

			case self::SPAM_ACTION_DELETE_AUTHOR:
				$decision['action']         = self::ACTION_DELETE;
				$decision['user_action']    = self::ACTION_BAN_USER;
				$decision['primary_reason'] = 'spam';
				break;

			case self::SPAM_ACTION_AUTO_APPROVE:
				$decision['action'] = self::ACTION_APPROVE;
				break;

			case self::SPAM_ACTION_NONE:
			default:
				// No action - keep current decision
				break;
		}

		$decision['action_level'] = $level;
		return $decision;
	}

	/**
	 * Execute moderation decision
	 *
	 * @param array $data             Content data
	 * @param array $decision         Moderation decision
	 * @param array $analysis_context Analysis context
	 * @return array Modified content data
	 */
	protected function execute_decision( $data, $decision, $analysis_context ) {
		\wpforo_ai_log( 'info', 'execute_decision() - action: ' . $decision['action'], 'Moderation' );
		switch ( $decision['action'] ) {
			case self::ACTION_HOLD:
				\wpforo_ai_log( 'info', 'Setting status to 1 (unapproved)', 'Moderation' );
				// Set status to unapproved
				$data['status'] = 1;
				break;

			case self::ACTION_REJECT:
			case self::ACTION_DELETE:
				// Return empty to block content creation
				return [];

			case self::ACTION_EDIT:
				// Apply content modifications
				if ( ! empty( $decision['modifications'] ) ) {
					$data = array_merge( $data, $decision['modifications'] );
				}
				break;

			case self::ACTION_APPROVE:
			default:
				// Allow content as-is
				break;
		}

		// Apply any user-level actions
		if ( ! empty( $decision['user_action'] ) ) {
			$this->execute_user_action( $decision['user_action'], $analysis_context['userid'], $decision['reason'] );
		}

		return $data;
	}

	/**
	 * Execute user-level moderation action
	 *
	 * @param string $action  Action to take
	 * @param int    $userid  User ID
	 * @param string $reason  Reason for action
	 */
	protected function execute_user_action( $action, $userid, $reason = '' ) {
		if ( ! $userid ) {
			return;
		}

		switch ( $action ) {
			case self::ACTION_WARN_USER:
				$this->warn_user( $userid, $reason );
				break;

			case self::ACTION_BAN_USER:
				$this->ban_user( $userid, $reason );
				break;

			case self::ACTION_SUSPEND_USER:
				$this->suspend_user( $userid, $reason );
				break;
		}
	}

	// =========================================================================
	// POST-EVENT HOOKS (After save)
	// =========================================================================

	/**
	 * Called after topic is created
	 *
	 * @param array $topic Topic data
	 * @param array $forum Forum data
	 */
	public function on_topic_created( $topic, $forum ) {
		// Update moderation log with actual topic ID (was 0 during filter)
		$this->update_pending_moderation_log( self::CONTENT_TOPIC, $topic, $forum );

		do_action( 'wpforo_ai_moderation_topic_created', $topic, $forum );
	}

	/**
	 * Called after topic is edited
	 *
	 * @param array $topic_data Full topic data
	 * @param array $args       Edit arguments
	 * @param array $forum      Forum data
	 */
	public function on_topic_edited( $topic_data, $args, $forum ) {
		do_action( 'wpforo_ai_moderation_topic_edited', $topic_data, $args, $forum );
	}

	/**
	 * Called when topic is approved
	 *
	 * @param array $topic Topic data
	 */
	public function on_topic_approved( $topic ) {
		do_action( 'wpforo_ai_moderation_topic_approved', $topic );
	}

	/**
	 * Called when topic is unapproved
	 *
	 * @param array $topic Topic data
	 */
	public function on_topic_unapproved( $topic ) {
		do_action( 'wpforo_ai_moderation_topic_unapproved', $topic );
	}

	/**
	 * Called on any topic status change
	 *
	 * @param array $topic  Topic data
	 * @param int   $status New status
	 */
	public function on_topic_status_change( $topic, $status ) {
		do_action( 'wpforo_ai_moderation_topic_status_changed', $topic, $status );
	}

	/**
	 * Called before topic deletion
	 *
	 * @param array $topic Topic data
	 */
	public function on_before_topic_delete( $topic ) {
		do_action( 'wpforo_ai_moderation_before_topic_delete', $topic );
	}

	/**
	 * Called after topic deletion
	 *
	 * Deletes the moderation log from local database since deleted content
	 * no longer needs the report displayed. CloudWatch logs remain intact.
	 *
	 * @param array $topic Topic data
	 */
	public function on_topic_deleted( $topic ) {
		if ( ! empty( $topic['topicid'] ) ) {
			$this->delete_moderation_log( self::CONTENT_TOPIC, (int) $topic['topicid'] );
		}

		do_action( 'wpforo_ai_moderation_topic_deleted', $topic );
	}

	/**
	 * Called after topic is moved
	 *
	 * @param array $topic   Topic data
	 * @param int   $forumid New forum ID
	 */
	public function on_topic_moved( $topic, $forumid ) {
		do_action( 'wpforo_ai_moderation_topic_moved', $topic, $forumid );
	}

	/**
	 * Called after topics are merged
	 *
	 * @param array $target          Target topic
	 * @param array $current         Source topic
	 * @param array $postids         Merged post IDs
	 * @param bool  $to_target_title Update titles
	 * @param bool  $append          Append posts
	 */
	public function on_topics_merged( $target, $current, $postids, $to_target_title, $append ) {
		do_action( 'wpforo_ai_moderation_topics_merged', $target, $current, $postids );
	}

	/**
	 * Called after post is created
	 *
	 * @param array $post  Post data
	 * @param array $topic Topic data
	 * @param array $forum Forum data
	 */
	public function on_post_created( $post, $topic, $forum ) {
		// Update moderation log with actual post ID (was 0 during filter)
		$this->update_pending_moderation_log( self::CONTENT_POST, $post, $forum );

		do_action( 'wpforo_ai_moderation_post_created', $post, $topic, $forum );
	}

	/**
	 * Called after post is edited
	 *
	 * @param array $post  Post data
	 * @param array $topic Topic data
	 * @param array $forum Forum data
	 * @param array $args  Edit arguments
	 */
	public function on_post_edited( $post, $topic, $forum, $args ) {
		do_action( 'wpforo_ai_moderation_post_edited', $post, $topic, $forum, $args );
	}

	/**
	 * Called when post is unapproved
	 *
	 * @param array $post Post data
	 */
	public function on_post_unapproved( $post ) {
		do_action( 'wpforo_ai_moderation_post_unapproved', $post );
	}

	/**
	 * Called on any post status change
	 *
	 * @param array $post   Post data
	 * @param int   $status New status
	 */
	public function on_post_status_change( $post, $status ) {
		do_action( 'wpforo_ai_moderation_post_status_changed', $post, $status );
	}

	/**
	 * Called before post deletion
	 *
	 * @param array $post Post data
	 */
	public function on_before_post_delete( $post ) {
		do_action( 'wpforo_ai_moderation_before_post_delete', $post );
	}

	/**
	 * Called after post deletion
	 *
	 * Deletes the moderation log from local database since deleted content
	 * no longer needs the report displayed. CloudWatch logs remain intact.
	 *
	 * @param array $post Post data
	 */
	public function on_post_deleted( $post ) {
		if ( ! empty( $post['postid'] ) ) {
			// Check if this is the first post (topic) or a reply
			$is_first_post = ! empty( $post['is_first_post'] );
			$content_type  = $is_first_post ? self::CONTENT_TOPIC : self::CONTENT_POST;
			$content_id    = $is_first_post ? ( $post['topicid'] ?? $post['postid'] ) : $post['postid'];

			$this->delete_moderation_log( $content_type, (int) $content_id );
		}

		do_action( 'wpforo_ai_moderation_post_deleted', $post );
	}

	/**
	 * Called when user is banned
	 *
	 * @param int $userid User ID
	 */
	public function on_user_banned( $userid ) {
		do_action( 'wpforo_ai_moderation_user_banned', $userid );
	}

	/**
	 * Called when user is unbanned
	 *
	 * @param int $userid User ID
	 */
	public function on_user_unbanned( $userid ) {
		do_action( 'wpforo_ai_moderation_user_unbanned', $userid );
	}

	/**
	 * Called when a post is approved
	 *
	 * Deletes the moderation report from local database since approved content
	 * no longer needs the report displayed. CloudWatch logs remain intact.
	 *
	 * @param array $post Post data
	 */
	public function on_post_approved( $post ) {
		if ( empty( $post['postid'] ) ) {
			return;
		}

		// Check if this is the first post (topic) or a reply
		$is_first_post = ! empty( $post['is_first_post'] );
		$content_type  = $is_first_post ? self::CONTENT_TOPIC : self::CONTENT_POST;
		$content_id    = $is_first_post ? ( $post['topicid'] ?? $post['postid'] ) : $post['postid'];

		$this->delete_moderation_log( $content_type, (int) $content_id );
	}

	/**
	 * Delete moderation log from local database
	 *
	 * Removes the moderation report for approved content.
	 * This only affects the local wpForo database - CloudWatch logs are preserved.
	 *
	 * @param string $content_type Content type (topic, post)
	 * @param int    $content_id   Content ID
	 * @return bool True if deleted, false otherwise
	 */
	public function delete_moderation_log( $content_type, $content_id ) {
		global $wpdb;

		if ( empty( $content_type ) || empty( $content_id ) ) {
			return false;
		}

		$result = $wpdb->delete(
			WPF()->tables->ai_moderation,
			[
				'content_type' => $content_type,
				'content_id'   => $content_id,
			],
			[ '%s', '%d' ]
		);

		return $result !== false;
	}

	// =========================================================================
	// MODERATION ACTIONS
	// =========================================================================

	/**
	 * Approve a topic
	 *
	 * @param int $topicid Topic ID
	 * @return bool Success
	 */
	public function approve_topic( $topicid ) {
		return WPF()->topic->set_status( $topicid, 0 );
	}

	/**
	 * Unapprove/hold a topic
	 *
	 * @param int $topicid Topic ID
	 * @return bool Success
	 */
	public function hold_topic( $topicid ) {
		return WPF()->topic->set_status( $topicid, 1 );
	}

	/**
	 * Delete a topic
	 *
	 * @param int  $topicid           Topic ID
	 * @param bool $check_permissions Check user permissions
	 * @return bool Success
	 */
	public function delete_topic( $topicid, $check_permissions = false ) {
		return WPF()->topic->delete( $topicid, true, $check_permissions );
	}

	/**
	 * Move a topic to different forum
	 *
	 * @param int $topicid Topic ID
	 * @param int $forumid Target forum ID
	 * @return bool Success
	 */
	public function move_topic( $topicid, $forumid ) {
		return WPF()->topic->move( $topicid, $forumid );
	}

	/**
	 * Close a topic (lock)
	 *
	 * @param int $topicid Topic ID
	 * @return bool Success
	 */
	public function close_topic( $topicid ) {
		return WPF()->topic->close( $topicid );
	}

	/**
	 * Open a topic (unlock)
	 *
	 * @param int $topicid Topic ID
	 * @return bool Success
	 */
	public function open_topic( $topicid ) {
		return WPF()->topic->open( $topicid );
	}

	/**
	 * Merge topics
	 *
	 * @param int   $target_topicid  Target topic ID
	 * @param int   $source_topicid  Source topic ID
	 * @param array $postids         Specific post IDs to merge (empty = all)
	 * @param bool  $update_titles   Update post titles to match target
	 * @param bool  $append          Append posts to end of target
	 * @return bool Success
	 */
	public function merge_topics( $target_topicid, $source_topicid, $postids = [], $update_titles = false, $append = true ) {
		$target = WPF()->topic->get_topic( $target_topicid );
		$source = WPF()->topic->get_topic( $source_topicid );

		if ( ! $target || ! $source ) {
			return false;
		}

		return WPF()->topic->merge( $target, $source, $postids, $update_titles, $append );
	}

	/**
	 * Approve a post
	 *
	 * @param int $postid Post ID
	 * @return bool Success
	 */
	public function approve_post( $postid ) {
		return WPF()->post->set_status( $postid, 0 );
	}

	/**
	 * Unapprove/hold a post
	 *
	 * @param int $postid Post ID
	 * @return bool Success
	 */
	public function hold_post( $postid ) {
		return WPF()->post->set_status( $postid, 1 );
	}

	/**
	 * Delete a post
	 *
	 * @param int  $postid            Post ID
	 * @param bool $check_permissions Check user permissions
	 * @return bool Success
	 */
	public function delete_post( $postid, $check_permissions = false ) {
		return WPF()->post->delete( $postid, true, true, [], $check_permissions );
	}

	/**
	 * Edit content (redact PII, profanity, etc.)
	 *
	 * @param int    $id           Content ID (topic or post)
	 * @param string $content_type Content type (topic or post)
	 * @param string $new_body     New body content
	 * @param string $new_title    New title (optional, for topics)
	 * @return bool Success
	 */
	public function edit_content( $id, $content_type, $new_body, $new_title = null ) {
		global $wpdb;

		if ( $content_type === self::CONTENT_TOPIC ) {
			$table = WPF()->tables->topics;
			$id_column = 'topicid';
			$update_data = [ 'body' => $new_body ];
			if ( $new_title !== null ) {
				$update_data['title'] = $new_title;
			}
		} else {
			$table = WPF()->tables->posts;
			$id_column = 'postid';
			$update_data = [ 'body' => $new_body ];
			if ( $new_title !== null ) {
				$update_data['title'] = $new_title;
			}
		}

		$result = $wpdb->update(
			$table,
			$update_data,
			[ $id_column => $id ]
		);

		WPF()->ram_cache->reset( $content_type );

		return $result !== false;
	}

	/**
	 * Warn a user
	 *
	 * @param int    $userid User ID
	 * @param string $reason Warning reason
	 * @return bool Success
	 */
	public function warn_user( $userid, $reason = '' ) {
		// TODO: Implement user warning system
		// This would involve:
		// 1. Storing warning in database
		// 2. Sending notification to user
		// 3. Incrementing warning count
		do_action( 'wpforo_ai_moderation_user_warned', $userid, $reason );
		return true;
	}

	/**
	 * Ban a user
	 *
	 * @param int    $userid User ID
	 * @param string $reason Ban reason
	 * @return bool Success
	 */
	public function ban_user( $userid, $reason = '' ) {
		// Use direct database update to bypass wpForo's "can't ban yourself" check
		// This is necessary because AI moderation runs in the context of the posting user
		global $wpdb;

		// Get the user's profile table
		$profile_table = WPF()->tables->profiles;

		// Update user status to 'banned' directly
		// wpForo uses the 'status' field for banning, not a separate usergroup
		$result = $wpdb->update(
			$profile_table,
			[ 'status' => 'banned' ],
			[ 'userid' => (int) $userid ],
			[ '%s' ],
			[ '%d' ]
		);

		// Clear user cache to reflect the ban immediately
		WPF()->member->reset( $userid );

		// Also clear general wpForo caches that may reference this user
		if ( function_exists( 'wpforo_clean_cache' ) ) {
			wpforo_clean_cache( 'user', $userid );
		}

		if ( $result !== false ) {
			do_action( 'wpforo_ai_moderation_user_banned_by_ai', $userid, $reason );

			// Log the ban action
			\wpforo_ai_log( 'info', sprintf( 'User #%d banned by AI. Reason: %s', $userid, $reason ), 'Moderation' );
		}

		return $result !== false;
	}

	/**
	 * Suspend a user temporarily
	 *
	 * @param int    $userid   User ID
	 * @param string $reason   Suspension reason
	 * @param int    $duration Duration in seconds (0 = permanent)
	 * @return bool Success
	 */
	public function suspend_user( $userid, $reason = '', $duration = 0 ) {
		// Deactivate user (wpForo's version of suspension)
		$result = WPF()->member->deactivate( $userid );

		if ( $result && $duration > 0 ) {
			// Schedule reactivation
			wp_schedule_single_event(
				time() + $duration,
				'wpforo_ai_moderation_reactivate_user',
				[ $userid ]
			);
		}

		if ( $result ) {
			do_action( 'wpforo_ai_moderation_user_suspended', $userid, $reason, $duration );
		}

		return $result;
	}

	// =========================================================================
	// LOGGING
	// =========================================================================

	/**
	 * Log moderation action
	 *
	 * @param array $context  Analysis context
	 * @param array $results  Handler results
	 * @param array $decision Moderation decision
	 */
	protected function log_moderation( $context, $results, $decision ) {
		// Only log if there was actual AI analysis
		if ( empty( $results ) && $decision['action'] === self::ACTION_APPROVE ) {
			return;
		}

		// Determine action taken string
		$action_taken = 'none';
		switch ( $decision['action'] ) {
			case self::ACTION_HOLD:
				$action_taken = ! empty( $decision['user_action'] ) && $decision['user_action'] === self::ACTION_BAN_USER
					? 'unapprove_ban'
					: 'unapprove';
				break;
			case self::ACTION_DELETE:
				$action_taken = 'delete_author';
				break;
			case self::ACTION_APPROVE:
				$action_taken = $decision['spam_score'] > 0 ? 'auto_approve' : 'approve';
				break;
		}

		// Build log data
		$forum = $context['forum'] ?? [];

		// Determine moderation type and score based on what was detected
		$spam_score = $decision['spam_score'] ?? 0;
		$toxicity_score = $decision['toxicity_score'] ?? 0;
		$compliance_score = $decision['compliance_score'] ?? 0;

		// Initialize with defaults (will be overwritten below)
		$moderation_type = 'spam';
		$score = 0;

		// Use primary_reason from API response if available (preferred method)
		$primary_reason = $decision['primary_reason'] ?? null;
		if ( $primary_reason && $primary_reason !== 'none' ) {
			// Map API primary_reason to moderation_type
			switch ( $primary_reason ) {
				case 'spam':
					$moderation_type = 'spam';
					$score = $spam_score;
					break;
				case 'toxicity':
					$moderation_type = 'toxicity';
					$score = $toxicity_score;
					break;
				case 'compliance':
					$moderation_type = 'compliance';
					$score = $compliance_score;
					break;
				default:
					// Unknown reason, fall through to score-based logic
					$primary_reason = null;
			}
		}

		// Fallback: Use the highest score and appropriate type (priority: compliance > toxicity > spam)
		if ( ! $primary_reason || $primary_reason === 'none' ) {
			if ( $compliance_score > $spam_score && $compliance_score > $toxicity_score && $compliance_score > 0 ) {
				$moderation_type = 'compliance';
				$score = $compliance_score;
			} elseif ( $toxicity_score > $spam_score && $toxicity_score > 0 ) {
				$moderation_type = 'toxicity';
				$score = $toxicity_score;
			} elseif ( $spam_score > 0 ) {
				$moderation_type = 'spam';
				$score = $spam_score;
			} elseif ( $toxicity_score > 0 ) {
				$moderation_type = 'toxicity';
				$score = $toxicity_score;
			} elseif ( $compliance_score > 0 ) {
				$moderation_type = 'compliance';
				$score = $compliance_score;
			} else {
				$moderation_type = 'spam';
				$score = 0;
			}
		}

		$log_data = [
			'content_type'     => $context['content_type'],
			'content_id'       => 0, // Not saved yet, will be updated after save
			'topicid'          => 0, // Will be updated after save
			'forumid'          => (int) ( $forum['forumid'] ?? 0 ),
			'userid'           => $context['userid'],
			'moderation_type'  => $moderation_type,
			'score'            => $score,
			'is_flagged'       => ( $decision['action'] !== self::ACTION_APPROVE ) ? 1 : 0,
			'confidence'       => ( $decision['confidence'] ?? 100 ) / 100,
			'action_taken'     => $action_taken,
			'action_reason'    => $decision['action_level'] ?? null,
			'analysis_summary' => $decision['reason'] ?? null,
			'indicators'       => ! empty( $decision['indicators'] ) ? wp_json_encode( $decision['indicators'] ) : null,
			'quality_tier'     => $this->get_spam_quality(),
			'credits_used'     => $decision['credits_used'] ?? 0,
			'content_preview'  => isset( $context['title'] ) ? wp_trim_words( $context['title'], 20 ) : null,
		];

		$this->save_moderation_log( $log_data );

		// Also log to AI Logs for visibility in AI Features > AI Logs tab
		$this->log_to_ai_logs( $context, $decision, $log_data );

		do_action( 'wpforo_ai_moderation_logged', $context, $results, $decision );
	}

	/**
	 * Log moderation action to AI Logs table
	 *
	 * This ensures moderation actions appear in the AI Features > AI Logs tab
	 * alongside other AI actions (search, translation, etc.)
	 *
	 * @param array $context Analysis context
	 * @param array $decision Moderation decision
	 * @param array $log_data Moderation log data
	 */
	protected function log_to_ai_logs( $context, $decision, $log_data ) {
		if ( ! isset( WPF()->ai_logs ) || ! method_exists( WPF()->ai_logs, 'log' ) ) {
			return;
		}

		// Determine action type based on moderation type
		$action_type = 'moderation';
		if ( $log_data['moderation_type'] === 'spam' ) {
			$action_type = 'spam_detection';
		}

		// Status is always 'success' since the moderation completed
		// The action_taken and response_summary indicate if content was flagged
		$status = 'success';

		// Build request summary
		$request_summary = sprintf(
			'%s %s: "%s"',
			ucfirst( $context['content_type'] ?? 'content' ),
			$context['event_type'] ?? 'submitted',
			wp_trim_words( $context['title'] ?? $context['body'] ?? '', 10 )
		);

		// Build response summary
		$response_parts = [];
		if ( $log_data['score'] > 0 ) {
			$response_parts[] = sprintf( '%s score: %d%%', ucfirst( $log_data['moderation_type'] ), $log_data['score'] );
		}
		if ( $log_data['action_taken'] && $log_data['action_taken'] !== 'none' ) {
			$response_parts[] = sprintf( 'Action: %s', str_replace( '_', ' ', $log_data['action_taken'] ) );
		}
		if ( ! empty( $decision['reason'] ) ) {
			$response_parts[] = $decision['reason'];
		}
		$response_summary = implode( ' | ', $response_parts );

		// Prepare extra data for detailed view
		$extra_data = [
			'moderation_type' => $log_data['moderation_type'],
			'score'           => $log_data['score'],
			'is_flagged'      => $log_data['is_flagged'],
			'confidence'      => $log_data['confidence'],
			'action_taken'    => $log_data['action_taken'],
			'action_reason'   => $log_data['action_reason'],
			'quality_tier'    => $log_data['quality_tier'],
		];
		if ( ! empty( $decision['indicators'] ) ) {
			$extra_data['indicators'] = $decision['indicators'];
		}

		WPF()->ai_logs->log( [
			'action_type'      => $action_type,
			'userid'           => $context['userid'] ?? 0,
			'user_type'        => ( $context['userid'] ?? 0 ) > 0 ? 'user' : 'guest',
			'credits_used'     => $log_data['credits_used'] ?? 0,
			'status'           => $status,
			'content_type'     => $context['content_type'] ?? null,
			'content_id'       => $log_data['content_id'] ?? null,
			'forumid'          => $log_data['forumid'] ?? null,
			'topicid'          => $log_data['topicid'] ?? null,
			'request_summary'  => $request_summary,
			'response_summary' => $response_summary,
			'duration_ms'      => $log_data['detection_time_ms'] ?? 0,
			'extra_data'       => wp_json_encode( $extra_data ),
		] );
	}

	/**
	 * Log flood control action to AI Logs table
	 *
	 * @param string $content_type 'topic' or 'post'
	 * @param int    $userid       User ID
	 * @param string $flood_reason Flood reason code
	 * @param string $analysis_summary Human-readable message
	 * @param array  $log_data     Moderation log data
	 */
	protected function log_flood_to_ai_logs( $content_type, $userid, $flood_reason, $analysis_summary, $log_data ) {
		if ( ! isset( WPF()->ai_logs ) || ! method_exists( WPF()->ai_logs, 'log' ) ) {
			return;
		}

		$request_summary = sprintf(
			'%s submitted by user',
			ucfirst( $content_type )
		);

		$response_summary = sprintf(
			'Flood protection: %s | Action: unapproved',
			$analysis_summary
		);

		$extra_data = [
			'moderation_type' => 'flood',
			'flood_reason'    => $flood_reason,
			'score'           => 100,
			'is_flagged'      => 1,
			'action_taken'    => 'unapprove',
			'quality_tier'    => 'rule_based',
		];

		WPF()->ai_logs->log( [
			'action_type'      => 'moderation',
			'userid'           => $userid,
			'user_type'        => $userid > 0 ? 'user' : 'guest',
			'credits_used'     => 0,
			'status'           => 'success',
			'content_type'     => $content_type,
			'content_id'       => $log_data['content_id'] ?? null,
			'forumid'          => $log_data['forumid'] ?? null,
			'topicid'          => $log_data['topicid'] ?? null,
			'request_summary'  => $request_summary,
			'response_summary' => $response_summary,
			'duration_ms'      => 0,
			'extra_data'       => wp_json_encode( $extra_data ),
		] );
	}

	// =========================================================================
	// UTILITY METHODS
	// =========================================================================

	/**
	 * Check if user is exempt from moderation
	 *
	 * Moderators and admins can be exempt from AI moderation.
	 *
	 * @param int $userid User ID
	 * @return bool True if exempt
	 */
	public function is_user_exempt( $userid ) {
		if ( ! $userid ) {
			return false;
		}

		// Admins are always exempt
		if ( WPF()->usergroup->can( 'ms' ) ) { // Manage settings = admin
			return true;
		}

		// Check if moderators are exempt (configurable)
		$moderators_exempt = apply_filters( 'wpforo_ai_moderation_moderators_exempt', true );
		if ( $moderators_exempt && WPF()->usergroup->can( 'em' ) ) { // Edit members = moderator
			return true;
		}

		return false;
	}

	/**
	 * Get human-readable message for flood protection moderation
	 *
	 * @param string $flood_reason The flood reason code (per_minute, per_hour, ip_per_hour, etc.)
	 * @return string Localized message explaining the flood protection action
	 */
	protected function get_flood_moderation_message( $flood_reason ) {
		switch ( $flood_reason ) {
			case 'per_minute':
				return wpforo_phrase( 'Content auto-unapproved: Exceeded maximum posts per minute (flood protection).', false );
			case 'per_hour':
				return wpforo_phrase( 'Content auto-unapproved: Exceeded maximum posts per hour (flood protection).', false );
			case 'ip_per_hour':
				return wpforo_phrase( 'Content auto-unapproved: Exceeded maximum posts per hour from this IP address (flood protection).', false );
			case 'temp_ban':
				return wpforo_phrase( 'Content auto-unapproved: User is temporarily banned due to flood protection.', false );
			case 'interval':
				return wpforo_phrase( 'Content auto-unapproved: Posted too quickly (flood interval not met).', false );
			default:
				return wpforo_phrase( 'Content auto-unapproved: Flood protection triggered.', false );
		}
	}

	/**
	 * Get AI client instance
	 *
	 * @return \wpforo\classes\AIClient|null
	 */
	protected function get_ai_client() {
		return WPF()->ai_client ?? null;
	}

	/**
	 * Check if AI services are available
	 *
	 * @return bool
	 */
	public function is_ai_available() {
		$ai_client = $this->get_ai_client();
		return $ai_client && $ai_client->is_service_available();
	}

	// =========================================================================
	// DATABASE LOGGING METHODS
	// =========================================================================

	/**
	 * Save moderation result to database
	 *
	 * @param array $data Moderation data
	 * @return int|false Insert ID on success, false on failure
	 */
	public function save_moderation_log( $data ) {
		global $wpdb;

		$defaults = [
			'content_type'        => '',
			'content_id'          => 0,
			'topicid'             => 0,
			'forumid'             => 0,
			'userid'              => 0,
			'moderation_type'     => 'spam',
			'score'               => 0,
			'is_flagged'          => 0,
			'confidence'          => 0.00,
			'action_taken'        => null,
			'action_reason'       => null,
			'indicators'          => null,
			'analysis_summary'    => null,
			'quality_tier'        => 'balanced',
			'credits_used'        => 0,
			'context_used'        => 0,
			'indexed_topics_count' => 0,
			'detection_time_ms'   => 0,
			'content_preview'     => null,
			'created'             => current_time( 'mysql' ),
		];

		$data = wp_parse_args( $data, $defaults );

		// Skip saving clean moderation logs (score < 50%) by default.
		// Use filter 'wpforo_ai_save_clean_moderation_logs' to override (return true to save all logs).
		$score = (int) $data['score'];
		if ( $score < 50 ) {
			$save_clean_logs = apply_filters( 'wpforo_ai_save_clean_moderation_logs', false, $data );
			if ( ! $save_clean_logs ) {
				return false;
			}
		}

		// Encode indicators as JSON if array
		if ( is_array( $data['indicators'] ) ) {
			$data['indicators'] = wp_json_encode( $data['indicators'] );
		}

		// Truncate content preview
		if ( $data['content_preview'] && strlen( $data['content_preview'] ) > 500 ) {
			$data['content_preview'] = substr( $data['content_preview'], 0, 497 ) . '...';
		}

		$result = $wpdb->insert(
			WPF()->tables->ai_moderation,
			[
				'content_type'        => $data['content_type'],
				'content_id'          => $data['content_id'],
				'topicid'             => $data['topicid'],
				'forumid'             => $data['forumid'],
				'userid'              => $data['userid'],
				'moderation_type'     => $data['moderation_type'],
				'score'               => $data['score'],
				'is_flagged'          => $data['is_flagged'],
				'confidence'          => $data['confidence'],
				'action_taken'        => $data['action_taken'],
				'action_reason'       => $data['action_reason'],
				'indicators'          => $data['indicators'],
				'analysis_summary'    => $data['analysis_summary'],
				'quality_tier'        => $data['quality_tier'],
				'credits_used'        => $data['credits_used'],
				'context_used'        => $data['context_used'],
				'indexed_topics_count' => $data['indexed_topics_count'],
				'detection_time_ms'   => $data['detection_time_ms'],
				'content_preview'     => $data['content_preview'],
				'created'             => $data['created'],
			],
			[ '%s', '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s' ]
		);

		if ( $result === false ) {
			return false;
		}

		// Auto-schedule cleanup cron if not already scheduled
		$this->schedule_moderation_cleanup();

		return $wpdb->insert_id;
	}

	/**
	 * Update pending moderation log with actual content ID
	 *
	 * Called after topic/post is saved to update the log entry that was
	 * created with content_id = 0 during the pre-save filter.
	 *
	 * @param string $content_type Content type (topic or post)
	 * @param array  $content      Topic or post data with actual ID
	 * @param array  $forum        Forum data
	 */
	protected function update_pending_moderation_log( $content_type, $content, $forum ) {
		global $wpdb;

		// Get the actual content ID
		$content_id = 0;
		$topicid = 0;

		if ( $content_type === self::CONTENT_TOPIC ) {
			$content_id = (int) ( $content['topicid'] ?? 0 );
			$topicid = $content_id;
		} else {
			$content_id = (int) ( $content['postid'] ?? 0 );
			$topicid = (int) ( $content['topicid'] ?? 0 );
		}

		if ( ! $content_id ) {
			return; // No valid content ID
		}

		$userid = (int) ( $content['userid'] ?? 0 );
		$forumid = (int) ( $content['forumid'] ?? $forum['forumid'] ?? 0 );

		// Find the most recent pending log entry for this user in this forum
		// (content_id = 0 means it was created during pre-save filter)
		$log_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM " . WPF()->tables->ai_moderation . "
				WHERE content_type = %s
				AND content_id = 0
				AND userid = %d
				AND forumid = %d
				ORDER BY created DESC
				LIMIT 1",
				$content_type,
				$userid,
				$forumid
			)
		);

		if ( ! $log_id ) {
			return; // No pending log entry found
		}

		// Update the log entry with actual content ID
		$wpdb->update(
			WPF()->tables->ai_moderation,
			[
				'content_id' => $content_id,
				'topicid'    => $topicid,
			],
			[ 'id' => $log_id ],
			[ '%d', '%d' ],
			[ '%d' ]
		);
	}

	/**
	 * Get moderation logs for content
	 *
	 * @param string $content_type Content type (topic or post)
	 * @param int    $content_id   Content ID
	 * @return array Moderation logs
	 */
	public function get_moderation_logs( $content_type, $content_id ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . WPF()->tables->ai_moderation . "
				WHERE content_type = %s AND content_id = %d
				ORDER BY created DESC",
				$content_type,
				$content_id
			),
			ARRAY_A
		);

		// Decode indicators JSON
		foreach ( $results as &$row ) {
			if ( ! empty( $row['indicators'] ) ) {
				$row['indicators'] = json_decode( $row['indicators'], true );
			}
		}

		return $results;
	}

	/**
	 * Get latest moderation log for content
	 *
	 * @param string $content_type   Content type (topic or post)
	 * @param int    $content_id     Content ID
	 * @param string $moderation_type Moderation type (spam, toxicity, etc.)
	 * @return array|null Moderation log or null
	 */
	public function get_latest_moderation( $content_type, $content_id, $moderation_type = null ) {
		global $wpdb;

		// If no specific type requested, get the latest log regardless of type
		if ( $moderation_type === null ) {
			$result = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM " . WPF()->tables->ai_moderation . "
					WHERE content_type = %s AND content_id = %d
					ORDER BY created DESC
					LIMIT 1",
					$content_type,
					$content_id
				),
				ARRAY_A
			);
		} else {
			$result = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM " . WPF()->tables->ai_moderation . "
					WHERE content_type = %s AND content_id = %d AND moderation_type = %s
					ORDER BY created DESC
					LIMIT 1",
					$content_type,
					$content_id,
					$moderation_type
				),
				ARRAY_A
			);
		}

		if ( $result && ! empty( $result['indicators'] ) ) {
			$result['indicators'] = json_decode( $result['indicators'], true );
		}

		return $result;
	}

	/**
	 * Get flagged content for review
	 *
	 * @param array $args Query arguments
	 * @return array Flagged content
	 */
	public function get_flagged_content( $args = [] ) {
		global $wpdb;

		$defaults = [
			'moderation_type' => null,
			'forumid'         => null,
			'min_score'       => 50,
			'reviewed'        => false, // false = unreviewed only
			'limit'           => 50,
			'offset'          => 0,
			'order_by'        => 'score',
			'order'           => 'DESC',
		];

		$args = wp_parse_args( $args, $defaults );

		$where = [ 'is_flagged = 1' ];
		$params = [];

		if ( $args['moderation_type'] ) {
			$where[] = 'moderation_type = %s';
			$params[] = $args['moderation_type'];
		}

		if ( $args['forumid'] ) {
			$where[] = 'forumid = %d';
			$params[] = $args['forumid'];
		}

		if ( $args['min_score'] > 0 ) {
			$where[] = 'score >= %d';
			$params[] = $args['min_score'];
		}

		if ( $args['reviewed'] === false ) {
			$where[] = 'reviewed_by IS NULL';
		} elseif ( $args['reviewed'] === true ) {
			$where[] = 'reviewed_by IS NOT NULL';
		}

		$where_sql = implode( ' AND ', $where );
		$order_by = in_array( $args['order_by'], [ 'score', 'created', 'confidence' ], true )
			? $args['order_by']
			: 'score';
		$order = $args['order'] === 'ASC' ? 'ASC' : 'DESC';

		$sql = "SELECT * FROM " . WPF()->tables->ai_moderation . "
				WHERE $where_sql
				ORDER BY $order_by $order
				LIMIT %d OFFSET %d";

		$params[] = $args['limit'];
		$params[] = $args['offset'];

		$results = $wpdb->get_results(
			$wpdb->prepare( $sql, ...$params ),
			ARRAY_A
		);

		foreach ( $results as &$row ) {
			if ( ! empty( $row['indicators'] ) ) {
				$row['indicators'] = json_decode( $row['indicators'], true );
			}
		}

		return $results;
	}

	/**
	 * Mark moderation as reviewed
	 *
	 * @param int    $id         Moderation log ID
	 * @param int    $reviewer_id Reviewer user ID
	 * @param string $action     Action taken (override)
	 * @param string $notes      Review notes
	 * @return bool Success
	 */
	public function mark_as_reviewed( $id, $reviewer_id, $action = null, $notes = '' ) {
		global $wpdb;

		$result = $wpdb->update(
			WPF()->tables->ai_moderation,
			[
				'reviewed_by'   => $reviewer_id,
				'reviewed_at'   => current_time( 'mysql' ),
				'review_action' => $action,
				'review_notes'  => $notes,
			],
			[ 'id' => $id ],
			[ '%d', '%s', '%s', '%s' ],
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Get moderation statistics
	 *
	 * @param array $args Query arguments
	 * @return array Statistics
	 */
	public function get_moderation_stats( $args = [] ) {
		global $wpdb;

		$defaults = [
			'moderation_type' => null,
			'forumid'         => null,
			'days'            => 30,
		];

		$args = wp_parse_args( $args, $defaults );

		$where = [ '1=1' ];
		$params = [];

		if ( $args['moderation_type'] ) {
			$where[] = 'moderation_type = %s';
			$params[] = $args['moderation_type'];
		}

		if ( $args['forumid'] ) {
			$where[] = 'forumid = %d';
			$params[] = $args['forumid'];
		}

		if ( $args['days'] > 0 ) {
			$where[] = 'created >= DATE_SUB(NOW(), INTERVAL %d DAY)';
			$params[] = $args['days'];
		}

		$where_sql = implode( ' AND ', $where );

		$sql = "SELECT
			COUNT(*) as total_checks,
			SUM(is_flagged) as total_flagged,
			SUM(CASE WHEN action_taken = 'approve' THEN 1 ELSE 0 END) as auto_approved,
			SUM(CASE WHEN action_taken = 'hold' THEN 1 ELSE 0 END) as auto_held,
			SUM(CASE WHEN action_taken = 'delete' THEN 1 ELSE 0 END) as auto_deleted,
			SUM(CASE WHEN action_taken = 'ban_user' THEN 1 ELSE 0 END) as auto_banned,
			AVG(score) as avg_score,
			SUM(credits_used) as total_credits,
			AVG(detection_time_ms) as avg_detection_time,
			SUM(CASE WHEN reviewed_by IS NOT NULL THEN 1 ELSE 0 END) as reviewed_count
			FROM " . WPF()->tables->ai_moderation . "
			WHERE $where_sql";

		if ( ! empty( $params ) ) {
			$result = $wpdb->get_row( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
		} else {
			$result = $wpdb->get_row( $sql, ARRAY_A );
		}

		return $result ?: [
			'total_checks'      => 0,
			'total_flagged'     => 0,
			'auto_approved'     => 0,
			'auto_held'         => 0,
			'auto_deleted'      => 0,
			'auto_banned'       => 0,
			'avg_score'         => 0,
			'total_credits'     => 0,
			'avg_detection_time' => 0,
			'reviewed_count'    => 0,
		];
	}

	/**
	 * Delete old moderation logs
	 *
	 * @param int $days Delete logs older than this many days
	 * @return int Number of deleted rows
	 */
	public function cleanup_old_logs( $days = 90 ) {
		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM " . WPF()->tables->ai_moderation . "
				WHERE created < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);

		return $result !== false ? $result : 0;
	}

	/**
	 * Cron callback for moderation log cleanup
	 *
	 * Runs daily to remove old moderation log entries.
	 * Retention period is controlled by 'wpforo_ai_moderation_log_retention_days' filter (default 90).
	 */
	public function cron_moderation_cleanup() {
		$retention_days = apply_filters( 'wpforo_ai_moderation_log_retention_days', 90 );
		$deleted        = $this->cleanup_old_logs( $retention_days );

		if ( $deleted > 0 ) {
			\wpforo_ai_log( 'info', "Cron cleanup: deleted {$deleted} logs older than {$retention_days} days", 'Moderation' );
		}
	}

	/**
	 * Schedule moderation log cleanup cron job
	 *
	 * Should be called on plugin activation or when moderation logs are created.
	 */
	public function schedule_moderation_cleanup() {
		if ( ! wp_next_scheduled( 'wpforo_ai_moderation_cleanup' ) ) {
			// Schedule to run at 4 AM server time (off-peak hours)
			$next_run = strtotime( 'tomorrow 4:00am' );
			wp_schedule_event( $next_run, 'daily', 'wpforo_ai_moderation_cleanup' );
		}
	}

	/**
	 * Unschedule moderation log cleanup cron job
	 *
	 * Should be called on plugin deactivation.
	 */
	public function unschedule_moderation_cleanup() {
		$timestamp = wp_next_scheduled( 'wpforo_ai_moderation_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'wpforo_ai_moderation_cleanup' );
		}
	}

	// =========================================================================
	// MODERATION REPORT DISPLAY (Admin View)
	// =========================================================================

	/**
	 * Display moderation report under posts for authorized users
	 *
	 * Shows AI moderation analysis results to users with 'au' (approve/unapprove)
	 * permission for the current forum.
	 *
	 * @param array $post      Post data
	 * @param array $topic     Topic data
	 * @param array $forum     Forum data
	 * @param int   $layout_id Layout ID
	 */
	public function display_moderation_report( $post, $topic, $forum, $layout_id ) {
		// Check if user has 'au' permission for this forum
		// Use $forum parameter (more reliable) with fallback to $post['forumid']
		$forumid = (int) ( $forum['forumid'] ?? $post['forumid'] ?? 0 );
		if ( ! $forumid || ! WPF()->perm->forum_can( 'au', $forumid ) ) {
			return;
		}

		// Post authors should NEVER see their own moderation reports
		$current_userid = WPF()->current_userid;
		$post_userid = (int) ( $post['userid'] ?? 0 );
		if ( $current_userid && $current_userid === $post_userid ) {
			return;
		}

		// Determine content type and ID
		$is_first_post = ! empty( $post['is_first_post'] );
		$content_type  = $is_first_post ? self::CONTENT_TOPIC : self::CONTENT_POST;
		$content_id    = $is_first_post ? ( $post['topicid'] ?? $post['postid'] ) : $post['postid'];

		// Get the latest moderation log for this content
		$moderation = $this->get_latest_moderation( $content_type, (int) $content_id );

		// If no moderation log exists, don't display anything
		if ( empty( $moderation ) ) {
			return;
		}

		// Render the moderation report
		$this->render_moderation_report( $moderation, $post );
	}

	/**
	 * Render the moderation report HTML
	 *
	 * @param array $moderation Moderation log data
	 * @param array $post       Post data
	 */
	protected function render_moderation_report( $moderation, $post ) {
		$score       = (int) ( $moderation['score'] ?? 0 );
		$is_flagged  = (bool) ( $moderation['is_flagged'] ?? false );
		$confidence  = (float) ( $moderation['confidence'] ?? 0 );
		$action      = $moderation['action_taken'] ?? 'none';
		$summary     = $moderation['analysis_summary'] ?? '';
		$indicators  = $moderation['indicators'] ?? [];
		$quality     = $moderation['quality_tier'] ?? 'fast';
		$credits     = (int) ( $moderation['credits_used'] ?? 0 );
		$created     = $moderation['created'] ?? '';
		$mod_type    = $moderation['moderation_type'] ?? 'spam';
		$is_ai       = ( $quality !== 'rule_based' );

		// Decode indicators if string
		if ( is_string( $indicators ) && ! empty( $indicators ) ) {
			$indicators = json_decode( $indicators, true ) ?: [];
		}

		// Determine status color
		$status_class = 'wpf-ai-mod-clean';
		$status_label = wpforo_phrase( 'Clean', false );
		if ( $score >= 85 ) {
			$status_class = 'wpf-ai-mod-detected';
			$status_label = wpforo_phrase( 'Detected', false );
		} elseif ( $score >= 70 ) {
			$status_class = 'wpf-ai-mod-suspected';
			$status_label = wpforo_phrase( 'Suspected', false );
		} elseif ( $score >= 51 ) {
			$status_class = 'wpf-ai-mod-uncertain';
			$status_label = wpforo_phrase( 'Uncertain', false );
		}

		// Action label
		$action_labels = [
			'none'          => wpforo_phrase( 'No action', false ),
			'approve'       => wpforo_phrase( 'Auto-approved', false ),
			'auto_approve'  => wpforo_phrase( 'Auto-approved', false ),
			'unapprove'     => wpforo_phrase( 'Unapproved', false ),
			'unapprove_ban' => wpforo_phrase( 'Unapproved + Banned', false ),
			'delete_author' => wpforo_phrase( 'Deleted + Banned', false ),
		];
		$action_label = $action_labels[ $action ] ?? $action;

		// Moderation type label (short for row display)
		if ( $is_ai ) {
			$type_labels = [
				'spam'       => wpforo_phrase( 'Spam Detection', false ),
				'toxicity'   => wpforo_phrase( 'Toxicity Detection', false ),
				'compliance' => wpforo_phrase( 'Policy Compliance', false ),
			];
			$type_label = $type_labels[ $mod_type ] ?? ucfirst( $mod_type );
		} else {
			$type_label = wpforo_phrase( 'Auto Moderation', false );
		}

		// Feature name (full name for footer)
		if ( $is_ai ) {
			$feature_names = [
				'spam'       => wpforo_phrase( 'AI Spam Detection', false ),
				'toxicity'   => wpforo_phrase( 'AI Content Safety & Toxicity Detection', false ),
				'compliance' => wpforo_phrase( 'AI Policy Compliance', false ),
			];
			$feature_name = $feature_names[ $mod_type ] ?? wpforo_phrase( 'AI Content Moderation', false );
		} else {
			$feature_name = wpforo_phrase( 'Auto Moderation', false );
		}

		// Quality tier label
		$quality_labels = [
			'fast'       => wpforo_phrase( 'Fast', false ),
			'balanced'   => wpforo_phrase( 'Balanced', false ),
			'advanced'   => wpforo_phrase( 'Advanced', false ),
			'premium'    => wpforo_phrase( 'Premium', false ),
			'rule_based' => wpforo_phrase( 'Rule-based', false ),
		];
		$quality_label = $quality_labels[ $quality ] ?? $quality;

		?>
		<div class="wpf-ai-moderation-report <?php echo esc_attr( $status_class ); ?>">
			<div class="wpf-ai-mod-header">
				<span class="wpf-ai-mod-icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/></svg>
				</span>
				<span class="wpf-ai-mod-title"><?php $is_ai ? wpforo_phrase( 'AI Moderation Report' ) : wpforo_phrase( 'Moderation Report' ); ?></span>
				<span class="wpf-ai-mod-status"><?php echo esc_html( $status_label ); ?></span>
			</div>

			<div class="wpf-ai-mod-body">
				<div class="wpf-ai-mod-row">
					<span class="wpf-ai-mod-label"><?php wpforo_phrase( 'Type:' ); ?></span>
					<span class="wpf-ai-mod-value"><?php echo esc_html( $type_label ); ?></span>
				</div>

				<div class="wpf-ai-mod-row">
					<span class="wpf-ai-mod-label"><?php wpforo_phrase( 'Score:' ); ?></span>
					<span class="wpf-ai-mod-value">
						<?php if ( $is_ai ) : ?>
							<span class="wpf-ai-mod-score"><?php echo esc_html( $score ); ?>%</span>
							<span class="wpf-ai-mod-confidence">(<?php printf( wpforo_phrase( '%d%% confidence', false ), round( $confidence * 100 ) ); ?>)</span>
						<?php else : ?>
							<span class="wpf-ai-mod-score">-</span>
						<?php endif; ?>
					</span>
				</div>

				<div class="wpf-ai-mod-row">
					<span class="wpf-ai-mod-label"><?php wpforo_phrase( 'Action:' ); ?></span>
					<span class="wpf-ai-mod-value"><?php echo esc_html( $action_label ); ?></span>
				</div>

				<?php if ( ! empty( $summary ) ) : ?>
				<div class="wpf-ai-mod-row wpf-ai-mod-summary">
					<span class="wpf-ai-mod-label"><?php wpforo_phrase( 'Summary:' ); ?></span>
					<span class="wpf-ai-mod-value"><?php echo esc_html( $summary ); ?></span>
				</div>
				<?php endif; ?>

				<?php if ( ! empty( $indicators ) && is_array( $indicators ) ) : ?>
				<div class="wpf-ai-mod-indicators">
					<span class="wpf-ai-mod-label"><?php wpforo_phrase( 'Indicators:' ); ?></span>
					<ul class="wpf-ai-mod-indicator-list">
						<?php foreach ( $indicators as $indicator ) : ?>
							<li class="wpf-ai-mod-indicator wpf-ai-mod-severity-<?php echo esc_attr( strtolower( $indicator['severity'] ?? 'medium' ) ); ?>">
								<span class="wpf-ai-mod-indicator-cat"><?php echo esc_html( $indicator['category'] ?? '' ); ?></span>
								<?php if ( ! empty( $indicator['description'] ) ) : ?>
									<span class="wpf-ai-mod-indicator-desc"><?php echo esc_html( $indicator['description'] ); ?></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php endif; ?>

				<div class="wpf-ai-mod-meta">
					<?php if ( $is_ai ) : ?>
						<span class="wpf-ai-mod-quality"><?php wpforo_phrase( 'AI Quality:' ); ?> <?php echo esc_html( $quality_label ); ?> (<?php echo esc_html( $credits ); ?> <?php echo ( $credits == 1 ) ? wpforo_phrase( 'credit', false ) : wpforo_phrase( 'credits', false ); ?>)</span>
					<?php endif; ?>
					<span class="wpf-ai-mod-feature"><?php echo esc_html( $feature_name ); ?></span>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Output moderation report styles in wp_head
	 *
	 * Only outputs styles on wpForo pages.
	 */
	public function output_moderation_report_styles() {
		// Only output on wpForo pages
		if ( ! function_exists( 'is_wpforo_page' ) || ! is_wpforo_page() ) {
			return;
		}

		// Only output if user might see moderation reports
		// (checking here would be too slow, so we always output on wpForo pages)
		echo '<style id="wpforo-ai-moderation-report-styles">' . self::get_moderation_report_styles() . '</style>';
	}

	/**
	 * Get CSS styles for moderation report
	 *
	 * @return string CSS styles
	 */
	public static function get_moderation_report_styles() {
		return '
			.wpf-ai-moderation-report {
				margin: 15px 0;
				padding: 12px 15px;
				border-radius: 6px;
				border: 1px solid #e0e0e0;
				background: #f8f9fa;
				font-size: 13px;
			}
			.wpf-ai-moderation-report.wpf-ai-mod-clean {
				border-color: #c3e6cb;
				background: #d4edda;
			}
			.wpf-ai-moderation-report.wpf-ai-mod-uncertain {
				border-color: #ffeeba;
				background: #fff3cd;
			}
			.wpf-ai-moderation-report.wpf-ai-mod-suspected {
				border-color: #ffcc80;
				background: #ffe0b2;
			}
			.wpf-ai-moderation-report.wpf-ai-mod-detected {
				border-color: #f5c6cb;
				background: #f8d7da;
			}
			.wpf-ai-mod-header {
				display: flex;
				align-items: center;
				gap: 8px;
				margin-bottom: 10px;
				padding-bottom: 8px;
				border-bottom: 1px solid rgba(0,0,0,0.1);
			}
			.wpf-ai-mod-icon svg {
				display: block;
			}
			.wpf-ai-mod-title {
				font-weight: 600;
				flex-grow: 1;
			}
			.wpf-ai-mod-status {
				font-size: 11px;
				font-weight: 500;
				text-transform: uppercase;
				padding: 2px 8px;
				border-radius: 3px;
				background: rgba(0,0,0,0.1);
			}
			.wpf-ai-mod-body {
				display: flex;
				flex-direction: column;
				gap: 6px;
			}
			.wpf-ai-mod-row {
				display: flex;
				gap: 8px;
			}
			.wpf-ai-mod-label {
				font-weight: 500;
				color: #555;
				min-width: 70px;
			}
			.wpf-ai-mod-value {
				color: #333;
			}
			.wpf-ai-mod-score {
				font-weight: 600;
			}
			.wpf-ai-mod-confidence {
				color: #666;
				font-size: 12px;
			}
			.wpf-ai-mod-summary {
				flex-direction: column;
			}
			.wpf-ai-mod-summary .wpf-ai-mod-value {
				margin-top: 2px;
				font-style: italic;
			}
			.wpf-ai-mod-indicators {
				margin-top: 6px;
			}
			.wpf-ai-mod-indicator-list {
				list-style: none;
				margin: 4px 0 0 0;
				padding: 0;
			}
			.wpf-ai-mod-indicator {
				display: flex;
				gap: 6px;
				padding: 4px 8px;
				margin: 2px 0;
				border-radius: 3px;
				font-size: 12px;
			}
			.wpf-ai-mod-indicator.wpf-ai-mod-severity-high {
				background: rgba(220, 53, 69, 0.15);
			}
			.wpf-ai-mod-indicator.wpf-ai-mod-severity-medium {
				background: rgba(255, 193, 7, 0.15);
			}
			.wpf-ai-mod-indicator.wpf-ai-mod-severity-low {
				background: rgba(108, 117, 125, 0.1);
			}
			.wpf-ai-mod-indicator-cat {
				font-weight: 500;
				text-transform: capitalize;
			}
			.wpf-ai-mod-indicator-desc {
				color: #666;
			}
			.wpf-ai-mod-meta {
				display: flex;
				justify-content: space-between;
				margin-top: 8px;
				padding-top: 8px;
				border-top: 1px solid rgba(0,0,0,0.1);
				font-size: 11px;
				color: #888;
			}
		';
	}
}
