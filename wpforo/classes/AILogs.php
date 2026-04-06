<?php

namespace wpforo\classes;

/**
 * wpForo AI Logs Manager
 *
 * Handles logging of all AI actions and provides admin interface
 * for viewing, filtering, and managing logs.
 *
 * @since 3.0.0
 */
class AILogs {
	use AIAjaxTrait;
	use AIUserTrait;

	/**
	 * Action type constants
	 */
	const ACTION_SEMANTIC_SEARCH    = 'semantic_search';
	const ACTION_PUBLIC_SEARCH      = 'public_search';
	const ACTION_TRANSLATION        = 'translation';
	const ACTION_TOPIC_SUMMARY      = 'topic_summary';
	const ACTION_TOPIC_SUGGESTIONS  = 'topic_suggestions';
	const ACTION_BOT_REPLY          = 'bot_reply';
	const ACTION_SUGGEST_REPLY      = 'suggest_reply';
	const ACTION_ANALYTICS_INSIGHTS = 'analytics_insights';
	const ACTION_CONTENT_INDEXING   = 'content_indexing';
	const ACTION_BATCH_EMBEDDING    = 'batch_embedding';
	const ACTION_QUEUE_PROCESSING   = 'queue_processing';
	const ACTION_SPAM_DETECTION     = 'spam_detection';
	const ACTION_MODERATION         = 'moderation';
	const ACTION_TASK_EXECUTION     = 'task_execution';
	const ACTION_CHATBOT            = 'chatbot';

	/**
	 * User type constants
	 */
	const USER_TYPE_USER   = 'user';
	const USER_TYPE_GUEST  = 'guest';
	const USER_TYPE_CRON   = 'cron';
	const USER_TYPE_SYSTEM = 'system';

	/**
	 * Status constants
	 */
	const STATUS_SUCCESS = 'success';
	const STATUS_ERROR   = 'error';
	const STATUS_CACHED  = 'cached';

	/**
	 * Constructor - Register AJAX handlers
	 */
	public function __construct() {
		if ( is_admin() ) {
			add_action( 'wp_ajax_wpforo_ai_get_logs', [ $this, 'ajax_get_logs' ] );
			add_action( 'wp_ajax_wpforo_ai_delete_logs', [ $this, 'ajax_delete_logs' ] );
			add_action( 'wp_ajax_wpforo_ai_empty_all_logs', [ $this, 'ajax_empty_all_logs' ] );
			add_action( 'wp_ajax_wpforo_ai_get_log_detail', [ $this, 'ajax_get_log_detail' ] );
			add_action( 'wp_ajax_wpforo_ai_save_cleanup_days', [ $this, 'ajax_save_cleanup_days' ] );
			add_action( 'wp_ajax_wpforo_ai_save_per_page', [ $this, 'ajax_save_per_page' ] );
			add_action( 'wp_ajax_wpforo_ai_get_chat_messages', [ $this, 'ajax_get_chat_messages' ] );
			add_action( 'wp_ajax_wpforo_ai_get_chat_message_detail', [ $this, 'ajax_get_chat_message_detail' ] );
		}

		// Schedule daily cleanup cron
		add_action( 'wpforo_ai_logs_cleanup', [ $this, 'cron_cleanup_old_logs' ] );
	}

	/**
	 * Log an AI action
	 *
	 * @param array $data Log data
	 *
	 * @return int|false Insert ID or false on failure
	 */
	public function log( $data ) {
		global $wpdb;

		// Check if table exists
		if ( ! isset( WPF()->tables->ai_logs ) ) {
			return false;
		}

		$defaults = [
			'action_type'      => '',
			'userid'           => get_current_user_id(),
			'user_type'        => self::USER_TYPE_USER,
			'credits_used'     => 0,
			'status'           => self::STATUS_SUCCESS,
			'content_type'     => null,
			'content_id'       => null,
			'forumid'          => null,
			'topicid'          => null,
			'request_summary'  => null,
			'response_summary' => null,
			'error_message'    => null,
			'duration_ms'      => 0,
			'ip_address'       => $this->get_client_ip(),
			'extra_data'       => null,
			'created'          => current_time( 'mysql' ),
		];

		$data = wp_parse_args( $data, $defaults );

		// Validate required field
		if ( empty( $data['action_type'] ) ) {
			return false;
		}

		// Determine user type if not explicitly set
		if ( $data['user_type'] === self::USER_TYPE_USER ) {
			if ( $data['userid'] == 0 ) {
				$data['user_type'] = self::USER_TYPE_GUEST;
			}
			if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
				$data['user_type'] = self::USER_TYPE_CRON;
			}
		}

		// Encode extra_data if array
		if ( is_array( $data['extra_data'] ) ) {
			$data['extra_data'] = wp_json_encode( $data['extra_data'] );
		}

		// Truncate long strings
		if ( $data['request_summary'] && strlen( $data['request_summary'] ) > 500 ) {
			$data['request_summary'] = substr( $data['request_summary'], 0, 497 ) . '...';
		}
		if ( $data['response_summary'] && strlen( $data['response_summary'] ) > 500 ) {
			$data['response_summary'] = substr( $data['response_summary'], 0, 497 ) . '...';
		}
		if ( $data['error_message'] && strlen( $data['error_message'] ) > 1000 ) {
			$data['error_message'] = substr( $data['error_message'], 0, 997 ) . '...';
		}

		$result = $wpdb->insert(
			WPF()->tables->ai_logs,
			[
				'action_type'      => $data['action_type'],
				'userid'           => $data['userid'],
				'user_type'        => $data['user_type'],
				'credits_used'     => $data['credits_used'],
				'status'           => $data['status'],
				'content_type'     => $data['content_type'],
				'content_id'       => $data['content_id'],
				'forumid'          => $data['forumid'],
				'topicid'          => $data['topicid'],
				'request_summary'  => $data['request_summary'],
				'response_summary' => $data['response_summary'],
				'error_message'    => $data['error_message'],
				'duration_ms'      => $data['duration_ms'],
				'ip_address'       => $data['ip_address'],
				'extra_data'       => $data['extra_data'],
				'created'          => $data['created'],
			],
			[ '%s', '%d', '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ]
		);

		if ( $result === false ) {
			return false;
		}

		$this->schedule_cleanup();

		return $wpdb->insert_id;
	}

	/**
	 * Get logs with filtering
	 *
	 * @param array $args Query arguments
	 *
	 * @return array
	 */
	public function get_logs( $args = [] ) {
		global $wpdb;

		$defaults = [
			'action_type' => '',
			'date_filter' => 'all',
			'status'      => '',
			'user_type'   => '',
			'search'      => '',
			'limit'       => 50,
			'offset'      => 0,
			'orderby'     => 'created',
			'order'       => 'DESC',
		];

		$args  = wp_parse_args( $args, $defaults );
		$table = WPF()->tables->ai_logs;

		$where          = [ '1=1' ];
		$prepare_values = [];

		// Action type filter
		if ( ! empty( $args['action_type'] ) ) {
			$where[]          = 'action_type = %s';
			$prepare_values[] = $args['action_type'];
		}

		// Status filter
		if ( ! empty( $args['status'] ) ) {
			$where[]          = 'status = %s';
			$prepare_values[] = $args['status'];
		}

		// User type filter
		if ( ! empty( $args['user_type'] ) ) {
			$where[]          = 'user_type = %s';
			$prepare_values[] = $args['user_type'];
		}

		// Date filter
		if ( ! empty( $args['date_filter'] ) && $args['date_filter'] !== 'all' ) {
			$date_sql = $this->get_date_filter_sql( $args['date_filter'] );
			if ( $date_sql ) {
				$where[] = $date_sql;
			}
		}

		// Search
		if ( ! empty( $args['search'] ) ) {
			$where[]          = '(request_summary LIKE %s OR response_summary LIKE %s OR error_message LIKE %s)';
			$search_term      = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$prepare_values[] = $search_term;
			$prepare_values[] = $search_term;
			$prepare_values[] = $search_term;
		}

		$where_clause = implode( ' AND ', $where );

		// Sanitize order
		$allowed_orderby = [ 'id', 'created', 'action_type', 'status', 'credits_used', 'duration_ms' ];
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$sql              = "SELECT * FROM `{$table}` WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$prepare_values[] = intval( $args['limit'] );
		$prepare_values[] = intval( $args['offset'] );

		if ( ! empty( $prepare_values ) ) {
			$sql = $wpdb->prepare( $sql, $prepare_values );
		}

		$logs = $wpdb->get_results( $sql, ARRAY_A );

		// Enrich logs with user display names
		if ( $logs ) {
			$logs = $this->enrich_logs_with_user_data( $logs );
		}

		return $logs ?: [];
	}

	/**
	 * Get total count for pagination
	 *
	 * @param array $args Query arguments
	 *
	 * @return int
	 */
	public function get_logs_count( $args = [] ) {
		global $wpdb;

		$table          = WPF()->tables->ai_logs;
		$where          = [ '1=1' ];
		$prepare_values = [];

		if ( ! empty( $args['action_type'] ) ) {
			$where[]          = 'action_type = %s';
			$prepare_values[] = $args['action_type'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where[]          = 'status = %s';
			$prepare_values[] = $args['status'];
		}

		if ( ! empty( $args['user_type'] ) ) {
			$where[]          = 'user_type = %s';
			$prepare_values[] = $args['user_type'];
		}

		if ( ! empty( $args['date_filter'] ) && $args['date_filter'] !== 'all' ) {
			$date_sql = $this->get_date_filter_sql( $args['date_filter'] );
			if ( $date_sql ) {
				$where[] = $date_sql;
			}
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]          = '(request_summary LIKE %s OR response_summary LIKE %s OR error_message LIKE %s)';
			$search_term      = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$prepare_values[] = $search_term;
			$prepare_values[] = $search_term;
			$prepare_values[] = $search_term;
		}

		$where_clause = implode( ' AND ', $where );
		$sql          = "SELECT COUNT(*) FROM `{$table}` WHERE {$where_clause}";

		if ( ! empty( $prepare_values ) ) {
			$sql = $wpdb->prepare( $sql, $prepare_values );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Get a single log by ID
	 *
	 * @param int $id Log ID
	 *
	 * @return array|null
	 */
	public function get_log( $id ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT * FROM `" . WPF()->tables->ai_logs . "` WHERE id = %d",
			$id
		);

		$log = $wpdb->get_row( $sql, ARRAY_A );

		if ( $log ) {
			$logs = $this->enrich_logs_with_user_data( [ $log ] );
			$log  = $logs[0];
		}

		return $log;
	}

	/**
	 * Delete specific logs
	 *
	 * @param array $ids Log IDs
	 *
	 * @return int|false Number of deleted rows or false on error
	 */
	public function delete_logs( $ids ) {
		global $wpdb;

		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return false;
		}

		$ids          = array_map( 'intval', $ids );
		$ids          = array_filter( $ids );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		return $wpdb->query( $wpdb->prepare(
			"DELETE FROM `" . WPF()->tables->ai_logs . "` WHERE id IN ({$placeholders})",
			$ids
		) );
	}

	/**
	 * Empty all logs
	 *
	 * @return int|false Number of deleted rows or false on error
	 */
	public function empty_all_logs() {
		global $wpdb;

		return $wpdb->query( "TRUNCATE TABLE `" . WPF()->tables->ai_logs . "`" );
	}

	/**
	 * Cleanup old logs (called by cron)
	 *
	 * @param int $days Delete logs older than this many days (default 90)
	 *
	 * @return int Number of deleted rows
	 */
	public function cleanup_old_logs( $days = 90 ) {
		global $wpdb;

		$days = apply_filters( 'wpforo_ai_logs_retention_days', $days );

		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `" . WPF()->tables->ai_logs . "` WHERE created < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);

		return $result !== false ? $result : 0;
	}

	/**
	 * Cron callback for log cleanup
	 */
	public function cron_cleanup_old_logs() {
		$days = $this->get_cleanup_days();

		// If 0, auto-cleanup is disabled
		if ( $days <= 0 ) {
			return;
		}

		$deleted = $this->cleanup_old_logs( $days );

		if ( $deleted > 0 ) {
			\wpforo_ai_log( 'info', "Cron cleanup: deleted {$deleted} logs older than {$days} days", 'Logs' );
		}
	}

	/**
	 * Get cleanup days setting (board-specific)
	 *
	 * @return int Number of days (0 = keep forever)
	 */
	public function get_cleanup_days() {
		return (int) wpforo_get_option( 'ai_logs_cleanup_days', 90 );
	}

	/**
	 * Save cleanup days setting (board-specific)
	 *
	 * @param int $days Number of days (0 = keep forever)
	 *
	 * @return bool Success
	 */
	public function save_cleanup_days( $days ) {
		$days = max( 0, min( 365, intval( $days ) ) );
		return wpforo_update_option( 'ai_logs_cleanup_days', $days );
	}

	/**
	 * Get per page setting (board-specific)
	 *
	 * @return int Number of logs per page
	 */
	public function get_per_page() {
		return (int) wpforo_get_option( 'ai_logs_per_page', 50 );
	}

	/**
	 * Save per page setting (board-specific)
	 *
	 * @param int $per_page Number of logs per page
	 *
	 * @return bool Success
	 */
	public function save_per_page( $per_page ) {
		$allowed = [ 25, 50, 100, 200 ];
		$per_page = in_array( $per_page, $allowed, true ) ? $per_page : 50;
		return wpforo_update_option( 'ai_logs_per_page', $per_page );
	}

	/**
	 * Schedule cleanup cron if not already scheduled
	 */
	public function schedule_cleanup() {
		if ( ! wp_next_scheduled( 'wpforo_ai_logs_cleanup' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 5:00am' ), 'daily', 'wpforo_ai_logs_cleanup' );
		}
	}

	/**
	 * Unschedule cleanup cron
	 */
	public function unschedule_cleanup() {
		$timestamp = wp_next_scheduled( 'wpforo_ai_logs_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'wpforo_ai_logs_cleanup' );
		}
	}

	// =========================================================================
	// AJAX HANDLERS
	// =========================================================================

	/**
	 * AJAX: Get logs with filtering
	 */
	public function ajax_get_logs() {
		$this->verify_ajax_admin_request( 'wpforo_ai_logs_nonce', 'nonce' );
		$this->switch_board_context();

		// Accept both 'date_filter' and 'date_range' parameter names (JS sends date_range)
		$date_filter = $this->get_post_param( 'date_filter', '' ) ?: $this->get_post_param( 'date_range', 'all' );
		$page        = $this->get_post_param( 'page', 1, 'int' );
		$per_page    = $this->get_post_param( 'per_page', 50, 'int' );

		$args = [
			'action_type' => $this->get_post_param( 'action_type', '' ),
			'date_filter' => $date_filter,
			'status'      => $this->get_post_param( 'status', '' ),
			'user_type'   => $this->get_post_param( 'user_type', '' ),
			'search'      => $this->get_post_param( 'search', '' ),
			'limit'       => $per_page,
			'offset'      => ( $page - 1 ) * $per_page,
			'orderby'     => $this->get_post_param( 'orderby', 'created' ),
			'order'       => $this->get_post_param( 'order', 'DESC' ),
		];

		$logs  = $this->get_logs( $args );
		$total = $this->get_logs_count( $args );

		// Enrich logs with user data
		$logs = $this->enrich_logs_with_user_data( $logs );

		// Render HTML for table rows
		$html = $this->render_logs_table_html( $logs );

		$this->send_success( [
			'html'        => $html,
			'logs'        => $logs,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total / $per_page ),
		] );
	}

	/**
	 * AJAX: Delete selected logs
	 */
	public function ajax_delete_logs() {
		$this->verify_ajax_admin_request( 'wpforo_ai_logs_nonce', 'nonce' );
		$this->switch_board_context();

		// Accept both 'log_ids' and 'ids' parameter names
		$ids = $this->get_post_param( 'log_ids', [], 'array_int' );
		if ( empty( $ids ) ) {
			$ids = $this->get_post_param( 'ids', [], 'array_int' );
		}

		if ( empty( $ids ) ) {
			$this->send_error( __( 'No logs selected', 'wpforo' ), 400 );
		}

		$deleted = $this->delete_logs( $ids );

		if ( $deleted === false ) {
			$this->send_error( __( 'Failed to delete logs', 'wpforo' ), 500 );
		}

		$this->send_success( [
			'message' => sprintf( __( '%d log(s) deleted', 'wpforo' ), $deleted ),
			'deleted' => $deleted,
		] );
	}

	/**
	 * AJAX: Empty all logs
	 */
	public function ajax_empty_all_logs() {
		$this->verify_ajax_admin_request( 'wpforo_ai_logs_nonce', 'nonce' );
		$this->switch_board_context();

		$result = $this->empty_all_logs();

		if ( $result === false ) {
			$this->send_error( __( 'Failed to empty logs', 'wpforo' ), 500 );
		}

		$this->send_success( [
			'message' => __( 'All logs have been deleted', 'wpforo' ),
		] );
	}

	/**
	 * AJAX: Get single log detail
	 */
	public function ajax_get_log_detail() {
		$this->verify_ajax_admin_request( 'wpforo_ai_logs_nonce', 'nonce' );
		$this->switch_board_context();

		// Accept both 'id' and 'log_id' parameter names
		$id = $this->get_post_param( 'log_id', 0, 'int' );
		if ( ! $id ) {
			$id = $this->get_post_param( 'id', 0, 'int' );
		}

		if ( ! $id ) {
			$this->send_error( __( 'Invalid log ID', 'wpforo' ), 400 );
		}

		$log = $this->get_log( $id );

		if ( ! $log ) {
			$this->send_error( __( 'Log not found', 'wpforo' ), 404 );
		}

		// Enrich with user data
		$logs = $this->enrich_logs_with_user_data( [ $log ] );
		$log  = $logs[0];

		// Decode extra_data for display
		$extra_data_decoded = null;
		if ( ! empty( $log['extra_data'] ) ) {
			$extra_data_decoded = json_decode( $log['extra_data'], true );
		}

		// Build HTML for modal
		$html = $this->render_log_detail_html( $log, $extra_data_decoded );

		$this->send_success( [ 'html' => $html, 'log' => $log ] );
	}

	/**
	 * AJAX: Save cleanup days setting
	 */
	public function ajax_save_cleanup_days() {
		$this->verify_ajax_admin_request( 'wpforo_ai_logs_nonce', 'nonce' );
		$this->switch_board_context();

		$days = $this->get_post_param( 'days', 90, 'int' );

		if ( $days < 0 || $days > 365 ) {
			$this->send_error( __( 'Days must be between 0 and 365', 'wpforo' ), 400 );
		}

		$result = $this->save_cleanup_days( $days );

		if ( ! $result ) {
			$this->send_error( __( 'Failed to save setting', 'wpforo' ), 500 );
		}

		if ( $days > 0 ) {
			$message = sprintf( __( 'Logs older than %d days will be automatically deleted', 'wpforo' ), $days );
		} else {
			$message = __( 'Auto-cleanup disabled. Logs will be kept forever.', 'wpforo' );
		}

		$this->send_success( [ 'message' => $message, 'days' => $days ] );
	}

	/**
	 * AJAX: Save per page setting
	 */
	public function ajax_save_per_page() {
		$this->verify_ajax_admin_request( 'wpforo_ai_logs_nonce', 'nonce' );
		$this->switch_board_context();

		$per_page = $this->get_post_param( 'per_page', 50, 'int' );
		$allowed  = [ 25, 50, 100, 200 ];

		if ( ! in_array( $per_page, $allowed, true ) ) {
			$this->send_error( __( 'Invalid value', 'wpforo' ), 400 );
		}

		$result = $this->save_per_page( $per_page );

		if ( ! $result ) {
			$this->send_error( __( 'Failed to save setting', 'wpforo' ), 500 );
		}

		$this->send_success( [
			'message'  => __( 'Setting saved', 'wpforo' ),
			'per_page' => $per_page,
		] );
	}

	/**
	 * AJAX: Get AI Chatbot messages (displayed as logs)
	 */
	public function ajax_get_chat_messages() {
		$this->verify_ajax_admin_request( 'wpforo_ai_logs_nonce', 'nonce' );
		$this->switch_board_context();

		// Accept both 'date_filter' and 'date_range' parameter names
		$date_filter = $this->get_post_param( 'date_filter', '' ) ?: $this->get_post_param( 'date_range', 'all' );
		$page        = $this->get_post_param( 'page', 1, 'int' );
		$per_page    = $this->get_post_param( 'per_page', 50, 'int' );

		$args = [
			'date_filter' => $date_filter,
			'status'      => $this->get_post_param( 'status', '' ),
			'user_type'   => $this->get_post_param( 'user_type', '' ),
			'search'      => $this->get_post_param( 'search', '' ),
			'limit'       => $per_page,
			'offset'      => ( $page - 1 ) * $per_page,
		];

		$messages = $this->get_chat_messages( $args );
		$total    = $this->get_chat_messages_count( $args );

		// Render HTML for table rows
		$html = $this->render_chat_messages_table_html( $messages );

		$this->send_success( [
			'html'        => $html,
			'messages'    => $messages,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total / $per_page ),
		] );
	}

	/**
	 * AJAX: Get single chat message detail
	 */
	public function ajax_get_chat_message_detail() {
		$this->verify_ajax_admin_request( 'wpforo_ai_logs_nonce', 'nonce' );
		$this->switch_board_context();

		// Accept both 'message_id' and 'id' parameter names
		$message_id = $this->get_post_param( 'message_id', 0, 'int' );
		if ( ! $message_id ) {
			$message_id = $this->get_post_param( 'id', 0, 'int' );
		}

		if ( ! $message_id ) {
			$this->send_error( __( 'Invalid message ID', 'wpforo' ), 400 );
		}

		$message = $this->get_chat_message( $message_id );

		if ( ! $message ) {
			$this->send_error( __( 'Message not found', 'wpforo' ), 404 );
		}

		// Build HTML for modal
		$html = $this->render_chat_message_detail_html( $message );

		$this->send_success( [ 'html' => $html, 'message' => $message ] );
	}

	/**
	 * Get chat messages with filtering
	 *
	 * @param array $args Query arguments
	 *
	 * @return array
	 */
	public function get_chat_messages( $args = [] ) {
		global $wpdb;

		$defaults = [
			'date_filter' => 'all',
			'status'      => '',
			'user_type'   => '',
			'search'      => '',
			'limit'       => 50,
			'offset'      => 0,
		];

		$args              = wp_parse_args( $args, $defaults );
		$messages_table    = WPF()->tables->ai_chat_messages;
		$conversations_table = WPF()->tables->ai_chat_conversations;

		$where          = [ '1=1' ];
		$prepare_values = [];

		// Date filter
		if ( ! empty( $args['date_filter'] ) && $args['date_filter'] !== 'all' ) {
			$date_sql = $this->get_chat_date_filter_sql( $args['date_filter'] );
			if ( $date_sql ) {
				$where[] = $date_sql;
			}
		}

		// Status filter - for chat messages, we interpret this as role
		// 'success' = assistant messages (responses), 'error' would be messages with no credits, etc.
		// Since chat messages don't have status, we skip this filter for now

		// User type filter - filter by conversation owner type
		if ( ! empty( $args['user_type'] ) ) {
			if ( $args['user_type'] === 'guest' ) {
				$where[] = 'c.userid = 0';
			} elseif ( $args['user_type'] === 'user' ) {
				$where[] = 'c.userid > 0';
			}
			// 'cron' and 'system' don't apply to chat messages
		}

		// Search in message content or conversation title
		if ( ! empty( $args['search'] ) ) {
			$where[]          = '(m.content LIKE %s OR c.title LIKE %s)';
			$search_term      = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$prepare_values[] = $search_term;
			$prepare_values[] = $search_term;
		}

		$where_clause = implode( ' AND ', $where );

		$sql = "SELECT m.*, c.title as conversation_title, c.userid
				FROM `{$messages_table}` m
				LEFT JOIN `{$conversations_table}` c ON m.conversation_id = c.conversation_id
				WHERE {$where_clause}
				ORDER BY m.created_at DESC
				LIMIT %d OFFSET %d";

		$prepare_values[] = intval( $args['limit'] );
		$prepare_values[] = intval( $args['offset'] );

		if ( ! empty( $prepare_values ) ) {
			$sql = $wpdb->prepare( $sql, $prepare_values );
		}

		$messages = $wpdb->get_results( $sql, ARRAY_A );

		// Enrich with user data
		if ( $messages ) {
			$messages = $this->enrich_chat_messages_with_user_data( $messages );
		}

		return $messages ?: [];
	}

	/**
	 * Get total count of chat messages for pagination
	 *
	 * @param array $args Query arguments
	 *
	 * @return int
	 */
	public function get_chat_messages_count( $args = [] ) {
		global $wpdb;

		$messages_table      = WPF()->tables->ai_chat_messages;
		$conversations_table = WPF()->tables->ai_chat_conversations;

		$where          = [ '1=1' ];
		$prepare_values = [];

		if ( ! empty( $args['date_filter'] ) && $args['date_filter'] !== 'all' ) {
			$date_sql = $this->get_chat_date_filter_sql( $args['date_filter'] );
			if ( $date_sql ) {
				$where[] = $date_sql;
			}
		}

		if ( ! empty( $args['user_type'] ) ) {
			if ( $args['user_type'] === 'guest' ) {
				$where[] = 'c.userid = 0';
			} elseif ( $args['user_type'] === 'user' ) {
				$where[] = 'c.userid > 0';
			}
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]          = '(m.content LIKE %s OR c.title LIKE %s)';
			$search_term      = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$prepare_values[] = $search_term;
			$prepare_values[] = $search_term;
		}

		$where_clause = implode( ' AND ', $where );

		$sql = "SELECT COUNT(*)
				FROM `{$messages_table}` m
				LEFT JOIN `{$conversations_table}` c ON m.conversation_id = c.conversation_id
				WHERE {$where_clause}";

		if ( ! empty( $prepare_values ) ) {
			$sql = $wpdb->prepare( $sql, $prepare_values );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Get a single chat message by ID
	 *
	 * @param int $message_id Message ID
	 *
	 * @return array|null
	 */
	public function get_chat_message( $message_id ) {
		global $wpdb;

		$messages_table      = WPF()->tables->ai_chat_messages;
		$conversations_table = WPF()->tables->ai_chat_conversations;

		$sql = $wpdb->prepare(
			"SELECT m.*, c.title as conversation_title, c.userid, c.running_summary, c.message_count, c.total_credits
			 FROM `{$messages_table}` m
			 LEFT JOIN `{$conversations_table}` c ON m.conversation_id = c.conversation_id
			 WHERE m.message_id = %d",
			$message_id
		);

		$message = $wpdb->get_row( $sql, ARRAY_A );

		if ( $message ) {
			$messages = $this->enrich_chat_messages_with_user_data( [ $message ] );
			$message  = $messages[0];
		}

		return $message;
	}

	/**
	 * Get date filter SQL for chat messages
	 *
	 * @param string $filter Date filter key
	 *
	 * @return string SQL condition
	 */
	private function get_chat_date_filter_sql( $filter ) {
		switch ( $filter ) {
			case 'last_hour':
				return 'm.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)';
			case 'last_day':
				return 'm.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)';
			case 'last_week':
				return 'm.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)';
			case 'last_month':
				return 'm.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)';
			default:
				return '';
		}
	}

	/**
	 * Enrich chat messages with user display names
	 *
	 * @param array $messages Array of chat messages
	 *
	 * @return array Enriched messages
	 */
	private function enrich_chat_messages_with_user_data( $messages ) {
		return $this->enrich_items_with_user_data( $messages, 'userid', [
			'user_type_field' => 'user_type',
			'output_field'    => 'user_display',
			'use_type_labels' => false, // Chat messages only have user or guest
		] );
	}

	/**
	 * Render chat messages as log table rows HTML
	 *
	 * @param array $messages Array of chat messages
	 *
	 * @return string HTML content for table tbody
	 */
	private function render_chat_messages_table_html( $messages ) {
		if ( empty( $messages ) ) {
			ob_start();
			?>
			<tr class="wpforo-ai-logs-empty-row">
				<td colspan="8">
					<div class="wpforo-ai-logs-empty">
						<span class="dashicons dashicons-info-outline"></span>
						<?php esc_html_e( 'No chat messages found matching your filters.', 'wpforo' ); ?>
					</div>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}

		ob_start();
		foreach ( $messages as $message ) {
			$this->render_chat_message_row( $message );
		}
		return ob_get_clean();
	}

	/**
	 * Render a single chat message as a log table row
	 *
	 * @param array $message Chat message data
	 */
	private function render_chat_message_row( $message ) {
		$message_id   = intval( $message['message_id'] );
		$role         = sanitize_text_field( $message['role'] );
		$user_type    = sanitize_text_field( $message['user_type'] ?? 'user' );
		$credits      = intval( $message['credits_spent'] ?? 0 );
		$created      = $message['created_at'];

		// Format date in user's timezone
		$date_display = self::format_datetime( $created, 'M j, Y' );
		$time_display = self::format_datetime( $created, 'g:i a' );

		// Get display values
		$user_display = isset( $message['user_display'] ) ? $message['user_display'] : __( 'Unknown', 'wpforo' );

		// Build summary: conversation title + truncated message
		$conv_title = ! empty( $message['conversation_title'] ) ? $message['conversation_title'] : __( 'Conversation', 'wpforo' );
		$msg_preview = wp_trim_words( wp_strip_all_tags( $message['content'] ), 12 );
		$summary = '<strong>' . esc_html( $conv_title ) . ':</strong> ' . esc_html( $msg_preview );

		// Role badge - user or assistant
		$role_label = $role === 'assistant' ? __( 'AI Response', 'wpforo' ) : __( 'User Message', 'wpforo' );
		$role_class = $role === 'assistant' ? 'wpforo-ai-chat-role-assistant' : 'wpforo-ai-chat-role-user';

		// Status based on role and credits
		$status_label = $role === 'assistant' ? __( 'Response', 'wpforo' ) : __( 'Query', 'wpforo' );
		$status_class = $role === 'assistant' ? 'wpforo-ai-log-status-success' : 'wpforo-ai-log-status-cached';
		?>
		<tr data-message-id="<?php echo esc_attr( $message_id ); ?>" data-role="<?php echo esc_attr( $role ); ?>" class="wpforo-ai-chat-message-row">
			<td class="check-column">
				<input type="checkbox" name="message_ids[]" value="<?php echo esc_attr( $message_id ); ?>" class="wpforo-ai-log-checkbox" disabled>
			</td>
			<td class="column-datetime">
				<span class="wpforo-ai-log-date"><?php echo esc_html( $date_display ); ?></span>
				<span class="wpforo-ai-log-time"><?php echo esc_html( $time_display ); ?></span>
			</td>
			<td class="column-action-type">
				<span class="wpforo-ai-log-action-badge <?php echo esc_attr( $role_class ); ?>">
					<?php echo esc_html( $role_label ); ?>
				</span>
			</td>
			<td class="column-user">
				<span class="wpforo-ai-log-user wpforo-ai-user-type-<?php echo esc_attr( $user_type ); ?>">
					<?php echo esc_html( $user_display ); ?>
				</span>
			</td>
			<td class="column-credits">
				<?php if ( $credits > 0 ) : ?>
					<span class="wpforo-ai-log-credits"><?php echo number_format( $credits ); ?></span>
				<?php else : ?>
					<span class="wpforo-ai-log-credits-zero">-</span>
				<?php endif; ?>
			</td>
			<td class="column-status">
				<span class="wpforo-ai-log-status <?php echo esc_attr( $status_class ); ?>">
					<?php echo esc_html( $status_label ); ?>
				</span>
			</td>
			<td class="column-summary">
				<span class="wpforo-ai-log-summary"><?php echo $summary; ?></span>
			</td>
			<td class="column-actions">
				<button type="button" class="button button-small wpforo-ai-chat-message-view" data-message-id="<?php echo esc_attr( $message_id ); ?>" title="<?php esc_attr_e( 'View Details', 'wpforo' ); ?>">
					<span class="dashicons dashicons-visibility"></span>
				</button>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render chat message detail HTML for modal
	 *
	 * @param array $message Chat message data
	 *
	 * @return string HTML content
	 */
	private function render_chat_message_detail_html( $message ) {
		$role_label = $message['role'] === 'assistant' ? __( 'AI Response', 'wpforo' ) : __( 'User Message', 'wpforo' );
		$role_class = $message['role'] === 'assistant' ? 'wpforo-ai-chat-role-assistant' : 'wpforo-ai-chat-role-user';
		$user_display = $message['user_display'] ?? __( 'Unknown', 'wpforo' );

		// Decode sources if present
		$sources = null;
		if ( ! empty( $message['sources_json'] ) ) {
			$sources = json_decode( $message['sources_json'], true );
		}

		// Process message content: convert markdown and post references
		$content = $this->format_chat_message_content( $message['content'] );

		ob_start();
		?>
		<div class="wpforo-ai-log-detail-grid">
			<div class="wpforo-ai-log-detail-item">
				<div class="wpforo-ai-log-detail-label"><?php esc_html_e( 'Message Type', 'wpforo' ); ?></div>
				<div class="wpforo-ai-log-detail-value">
					<span class="wpforo-ai-log-action-badge <?php echo esc_attr( $role_class ); ?>">
						<?php echo esc_html( $role_label ); ?>
					</span>
				</div>
			</div>
			<div class="wpforo-ai-log-detail-item">
				<div class="wpforo-ai-log-detail-label"><?php esc_html_e( 'Conversation', 'wpforo' ); ?></div>
				<div class="wpforo-ai-log-detail-value">
					<?php echo esc_html( $message['conversation_title'] ?? __( 'Untitled', 'wpforo' ) ); ?>
				</div>
			</div>
			<div class="wpforo-ai-log-detail-item">
				<div class="wpforo-ai-log-detail-label"><?php esc_html_e( 'Date/Time', 'wpforo' ); ?></div>
				<div class="wpforo-ai-log-detail-value">
					<?php echo esc_html( self::format_datetime( $message['created_at'], 'F j, Y \a\t g:i:s a' ) ); ?>
				</div>
			</div>
			<div class="wpforo-ai-log-detail-item">
				<div class="wpforo-ai-log-detail-label"><?php esc_html_e( 'User', 'wpforo' ); ?></div>
				<div class="wpforo-ai-log-detail-value">
					<?php echo esc_html( $user_display ); ?>
				</div>
			</div>
			<?php if ( intval( $message['credits_spent'] ?? 0 ) > 0 ) : ?>
				<div class="wpforo-ai-log-detail-item">
					<div class="wpforo-ai-log-detail-label"><?php esc_html_e( 'Credits Used', 'wpforo' ); ?></div>
					<div class="wpforo-ai-log-detail-value">
						<?php echo number_format( $message['credits_spent'] ); ?>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( intval( $message['tokens_used'] ?? 0 ) > 0 ) : ?>
				<div class="wpforo-ai-log-detail-item">
					<div class="wpforo-ai-log-detail-label"><?php esc_html_e( 'Tokens Used', 'wpforo' ); ?></div>
					<div class="wpforo-ai-log-detail-value">
						<?php echo number_format( $message['tokens_used'] ); ?>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $message['quality_tier'] ) ) : ?>
				<div class="wpforo-ai-log-detail-item">
					<div class="wpforo-ai-log-detail-label"><?php esc_html_e( 'Quality Tier', 'wpforo' ); ?></div>
					<div class="wpforo-ai-log-detail-value">
						<?php echo esc_html( ucfirst( $message['quality_tier'] ) ); ?>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( intval( $message['sources_count'] ?? 0 ) > 0 ) : ?>
				<div class="wpforo-ai-log-detail-item">
					<div class="wpforo-ai-log-detail-label"><?php esc_html_e( 'Sources Used', 'wpforo' ); ?></div>
					<div class="wpforo-ai-log-detail-value">
						<?php echo intval( $message['sources_count'] ); ?>
					</div>
				</div>
			<?php endif; ?>
			<div class="wpforo-ai-log-detail-item full-width">
				<div class="wpforo-ai-log-detail-label"><?php esc_html_e( 'Message Content', 'wpforo' ); ?></div>
				<div class="wpforo-ai-log-detail-value wpforo-ai-chat-message-content">
					<?php echo $content; ?>
				</div>
			</div>
			<?php if ( ! empty( $sources ) && is_array( $sources ) ) : ?>
				<div class="wpforo-ai-log-detail-item full-width">
					<div class="wpforo-ai-log-detail-label"><?php esc_html_e( 'Sources', 'wpforo' ); ?></div>
					<div class="wpforo-ai-log-detail-value wpforo-ai-sources-list">
						<ul>
							<?php foreach ( $sources as $source ) : ?>
								<?php
								// Handle different source formats
								$url   = '';
								$title = '';
								$postid = 0;

								if ( is_array( $source ) ) {
									// Source is an array with metadata
									$postid = isset( $source['postid'] ) ? intval( $source['postid'] ) : ( isset( $source['post_id'] ) ? intval( $source['post_id'] ) : 0 );
									$title  = isset( $source['title'] ) ? $source['title'] : '';
									$url    = isset( $source['url'] ) ? $source['url'] : '';
								} elseif ( is_numeric( $source ) ) {
									// Source is just a post ID
									$postid = intval( $source );
								}

								// Get URL from post ID if not provided
								if ( empty( $url ) && $postid > 0 ) {
									$url = WPF()->post->get_url( $postid );
								}

								// Get title from post if not provided
								if ( empty( $title ) && $postid > 0 ) {
									$post = WPF()->post->get_post( $postid );
									if ( $post ) {
										$title = ! empty( $post['title'] ) ? $post['title'] : sprintf( __( 'Post #%d', 'wpforo' ), $postid );
									}
								}

								// Fallback title
								if ( empty( $title ) ) {
									$title = $postid > 0 ? sprintf( __( 'Post #%d', 'wpforo' ), $postid ) : __( 'Source', 'wpforo' );
								}
								?>
								<li>
									<?php if ( ! empty( $url ) ) : ?>
										<a href="<?php echo esc_url( $url ); ?>" target="_blank" class="wpforo-ai-source-link">
											<?php echo esc_html( $title ); ?>
										</a>
									<?php else : ?>
										<?php echo esc_html( $title ); ?>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $message['running_summary'] ) ) : ?>
				<div class="wpforo-ai-log-detail-item full-width">
					<div class="wpforo-ai-log-detail-label"><?php esc_html_e( 'Conversation Summary', 'wpforo' ); ?></div>
					<div class="wpforo-ai-log-detail-value">
						<?php echo esc_html( $message['running_summary'] ); ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Format chat message content: convert markdown to HTML and post references to links
	 *
	 * Delegates to AIMarkdown for consistent markdown conversion.
	 *
	 * @param string $content Raw message content
	 *
	 * @return string Formatted HTML content
	 */
	private function format_chat_message_content( $content ) {
		if ( empty( $content ) ) {
			return '';
		}

		// Convert citations to links (using inline format for admin view)
		$content = AIMarkdown::convert_citations( $content, [
			'format' => 'inline',
			'class'  => 'wpforo-ai-post-link',
		] );

		// Convert markdown to HTML (admin mode with strikethrough support)
		return AIMarkdown::to_html( $content, AIMarkdown::MODE_ADMIN );
	}

	/**
	 * Convert basic markdown to HTML
	 *
	 * Delegates to AIMarkdown for consistent markdown conversion.
	 *
	 * @param string $text Markdown text
	 *
	 * @return string HTML
	 */
	private function markdown_to_html( $text ) {
		return AIMarkdown::to_html( $text, AIMarkdown::MODE_ADMIN );
	}

	/**
	 * Render log detail HTML for modal
	 *
	 * @param array      $log               Log data
	 * @param array|null $extra_data_decoded Decoded extra data
	 *
	 * @return string HTML content
	 */
	private function render_log_detail_html( $log, $extra_data_decoded = null ) {
		$action_label = self::get_action_label( $log['action_type'] );
		$status_label = self::get_status_label( $log['status'] );
		$status_class = self::get_status_class( $log['status'] );
		$user_display = $log['user_display'] ?? $this->get_user_type_display_label( $log['user_type'] );

		ob_start();
		?>
		<div class="wpforo-ai-log-detail-grid">
			<div class="wpforo-ai-log-detail-item">
				<div class="wpforo-ai-log-detail-label"><?php esc_html_e( 'Action Type', 'wpforo' ); ?></div>
				<div class="wpforo-ai-log-detail-value">
					<span class="wpforo-ai-log-action-badge wpforo-ai-action-<?php echo esc_attr( $log['action_type'] ); ?>">
						<?php echo esc_html( $action_label ); ?>
					</span>
				</div>
			</div>
			<div class="wpforo-ai-log-detail-item">
				<div class="wpforo-ai-log-detail-label"><?php esc_html_e( 'Status', 'wpforo' ); ?></div>
				<div class="wpforo-ai-log-detail-value">
					<span class="wpforo-ai-log-status <?php echo esc_attr( $status_class ); ?>">
						<?php echo esc_html( $status_label ); ?>
					</span>
				</div>
			</div>
			<div class="wpforo-ai-log-detail-item">
				<div class="wpforo-ai-log-detail-label"><?php esc_html_e( 'Date/Time', 'wpforo' ); ?></div>
				<div class="wpforo-ai-log-detail-value">
					<?php echo esc_html( self::format_datetime( $log['created'], 'F j, Y \a\t g:i:s a' ) ); ?>
				</div>
			</div>
			<div class="wpforo-ai-log-detail-item">
				<div class="wpforo-ai-log-detail-label"><?php esc_html_e( 'User', 'wpforo' ); ?></div>
				<div class="wpforo-ai-log-detail-value">
					<?php echo esc_html( $user_display ); ?>
					<?php if ( $log['user_type'] !== 'user' ) : ?>
						<small>(<?php echo esc_html( $log['user_type'] ); ?>)</small>
					<?php endif; ?>
				</div>
			</div>
			<div class="wpforo-ai-log-detail-item">
				<div class="wpforo-ai-log-detail-label"><?php esc_html_e( 'Credits Used', 'wpforo' ); ?></div>
				<div class="wpforo-ai-log-detail-value">
					<?php echo intval( $log['credits_used'] ) > 0 ? number_format( $log['credits_used'] ) : '-'; ?>
				</div>
			</div>
			<div class="wpforo-ai-log-detail-item">
				<div class="wpforo-ai-log-detail-label"><?php esc_html_e( 'Duration', 'wpforo' ); ?></div>
				<div class="wpforo-ai-log-detail-value">
					<?php echo intval( $log['duration_ms'] ) > 0 ? number_format( $log['duration_ms'] ) . 'ms' : '-'; ?>
				</div>
			</div>
			<?php if ( ! empty( $log['content_type'] ) || ! empty( $log['content_id'] ) ) : ?>
				<div class="wpforo-ai-log-detail-item">
					<div class="wpforo-ai-log-detail-label"><?php esc_html_e( 'Content', 'wpforo' ); ?></div>
					<div class="wpforo-ai-log-detail-value">
						<?php
						$content_info = [];
						if ( ! empty( $log['content_type'] ) ) {
							$content_info[] = ucfirst( $log['content_type'] );
						}
						if ( ! empty( $log['content_id'] ) ) {
							$content_info[] = '#' . $log['content_id'];
						}
						echo esc_html( implode( ' ', $content_info ) );
						?>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $log['ip_address'] ) ) : ?>
				<div class="wpforo-ai-log-detail-item">
					<div class="wpforo-ai-log-detail-label"><?php esc_html_e( 'IP Address', 'wpforo' ); ?></div>
					<div class="wpforo-ai-log-detail-value"><?php echo esc_html( $log['ip_address'] ); ?></div>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $log['request_summary'] ) ) : ?>
				<div class="wpforo-ai-log-detail-item full-width">
					<div class="wpforo-ai-log-detail-label"><?php esc_html_e( 'Request Summary', 'wpforo' ); ?></div>
					<div class="wpforo-ai-log-detail-value"><?php echo esc_html( $log['request_summary'] ); ?></div>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $log['response_summary'] ) ) : ?>
				<div class="wpforo-ai-log-detail-item full-width">
					<div class="wpforo-ai-log-detail-label"><?php esc_html_e( 'Response Summary', 'wpforo' ); ?></div>
					<div class="wpforo-ai-log-detail-value"><?php echo esc_html( $log['response_summary'] ); ?></div>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $log['error_message'] ) ) : ?>
				<div class="wpforo-ai-log-detail-item full-width wpforo-ai-log-detail-error">
					<div class="wpforo-ai-log-detail-label"><?php esc_html_e( 'Error Message', 'wpforo' ); ?></div>
					<div class="wpforo-ai-log-detail-value"><?php echo esc_html( $log['error_message'] ); ?></div>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $extra_data_decoded ) && wpforo_setting( 'general', 'debug_mode' ) ) : ?>
				<div class="wpforo-ai-log-detail-item full-width">
					<div class="wpforo-ai-log-detail-label"><?php esc_html_e( 'Extra Data', 'wpforo' ); ?></div>
					<div class="wpforo-ai-log-detail-value">
						<pre><?php echo esc_html( json_encode( $extra_data_decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render logs table rows HTML for AJAX response
	 *
	 * @param array $logs Array of log entries (already enriched with user data)
	 *
	 * @return string HTML content for table tbody
	 */
	private function render_logs_table_html( $logs ) {
		if ( empty( $logs ) ) {
			ob_start();
			?>
			<tr class="wpforo-ai-logs-empty-row">
				<td colspan="8">
					<div class="wpforo-ai-logs-empty">
						<span class="dashicons dashicons-info-outline"></span>
						<?php esc_html_e( 'No logs found matching your filters.', 'wpforo' ); ?>
					</div>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}

		ob_start();
		foreach ( $logs as $log ) {
			$this->render_log_row( $log );
		}
		return ob_get_clean();
	}

	/**
	 * Render a single log table row
	 *
	 * @param array $log Log data (enriched with user_display)
	 */
	private function render_log_row( $log ) {
		$log_id      = intval( $log['id'] );
		$action_type = sanitize_text_field( $log['action_type'] );
		$status      = sanitize_text_field( $log['status'] );
		$user_type   = sanitize_text_field( $log['user_type'] );
		$credits     = intval( $log['credits_used'] );
		$duration    = intval( $log['duration_ms'] );
		$created     = $log['created'];

		// Format date in user's timezone (database stores UTC)
		$date_display = self::format_datetime( $created, 'M j, Y' );
		$time_display = self::format_datetime( $created, 'g:i a' );

		// Get display values
		$action_label = self::get_action_label( $action_type );
		$status_label = self::get_status_label( $status );
		$status_class = self::get_status_class( $status );
		$user_display = isset( $log['user_display'] ) ? $log['user_display'] : __( 'Unknown', 'wpforo' );

		// Build summary
		$summary = '';
		if ( ! empty( $log['request_summary'] ) ) {
			$summary = esc_html( wp_trim_words( $log['request_summary'], 15 ) );
		} elseif ( ! empty( $log['response_summary'] ) ) {
			$summary = esc_html( wp_trim_words( $log['response_summary'], 15 ) );
		} elseif ( ! empty( $log['error_message'] ) ) {
			$summary = '<span class="wpforo-ai-log-error-summary">' . esc_html( wp_trim_words( $log['error_message'], 15 ) ) . '</span>';
		}
		?>
		<tr data-log-id="<?php echo esc_attr( $log_id ); ?>" data-action-type="<?php echo esc_attr( $action_type ); ?>" data-status="<?php echo esc_attr( $status ); ?>">
			<td class="check-column">
				<input type="checkbox" name="log_ids[]" value="<?php echo esc_attr( $log_id ); ?>" class="wpforo-ai-log-checkbox">
			</td>
			<td class="column-datetime">
				<span class="wpforo-ai-log-date"><?php echo esc_html( $date_display ); ?></span>
				<span class="wpforo-ai-log-time"><?php echo esc_html( $time_display ); ?></span>
			</td>
			<td class="column-action-type">
				<span class="wpforo-ai-log-action-badge wpforo-ai-action-<?php echo esc_attr( $action_type ); ?>">
					<?php echo esc_html( $action_label ); ?>
				</span>
			</td>
			<td class="column-user">
				<span class="wpforo-ai-log-user wpforo-ai-user-type-<?php echo esc_attr( $user_type ); ?>">
					<?php echo esc_html( $user_display ); ?>
				</span>
			</td>
			<td class="column-credits">
				<?php if ( $credits > 0 ) : ?>
					<span class="wpforo-ai-log-credits"><?php echo number_format( $credits ); ?></span>
				<?php else : ?>
					<span class="wpforo-ai-log-credits-zero">-</span>
				<?php endif; ?>
			</td>
			<td class="column-status">
				<span class="wpforo-ai-log-status <?php echo esc_attr( $status_class ); ?>">
					<?php echo esc_html( $status_label ); ?>
				</span>
			</td>
			<td class="column-summary">
				<span class="wpforo-ai-log-summary"><?php echo $summary; ?></span>
				<?php if ( $duration > 0 ) : ?>
					<span class="wpforo-ai-log-duration" title="<?php esc_attr_e( 'Duration', 'wpforo' ); ?>">
						(<?php echo number_format( $duration ); ?>ms)
					</span>
				<?php endif; ?>
			</td>
			<td class="column-actions">
				<button type="button" class="button button-small wpforo-ai-log-view" data-log-id="<?php echo esc_attr( $log_id ); ?>" title="<?php esc_attr_e( 'View Details', 'wpforo' ); ?>">
					<span class="dashicons dashicons-visibility"></span>
				</button>
				<button type="button" class="button button-small wpforo-ai-log-delete" data-log-id="<?php echo esc_attr( $log_id ); ?>" title="<?php esc_attr_e( 'Delete', 'wpforo' ); ?>">
					<span class="dashicons dashicons-trash"></span>
				</button>
			</td>
		</tr>
		<?php
	}

	// =========================================================================
	// HELPER METHODS
	// =========================================================================

	/**
	 * Get date filter SQL condition
	 *
	 * @param string $filter Date filter key
	 *
	 * @return string SQL condition
	 */
	private function get_date_filter_sql( $filter ) {
		switch ( $filter ) {
			case 'last_hour':
				return 'created >= DATE_SUB(NOW(), INTERVAL 1 HOUR)';
			case 'last_day':
				return 'created >= DATE_SUB(NOW(), INTERVAL 1 DAY)';
			case 'last_week':
				return 'created >= DATE_SUB(NOW(), INTERVAL 1 WEEK)';
			case 'last_month':
				return 'created >= DATE_SUB(NOW(), INTERVAL 1 MONTH)';
			default:
				return '';
		}
	}

	/**
	 * Enrich logs with user display names
	 *
	 * @param array $logs Array of log entries
	 *
	 * @return array Enriched logs
	 */
	private function enrich_logs_with_user_data( $logs ) {
		return $this->enrich_items_with_user_data( $logs, 'userid', [
			'user_type_field' => 'user_type',
			'output_field'    => 'user_display',
			'use_type_labels' => true, // Logs can have cron, system types
		] );
	}

	/**
	 * Get client IP address
	 *
	 * @return string|null
	 */
	private function get_client_ip() {
		$ip_keys = [
			'HTTP_CF_CONNECTING_IP',
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		];

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( $_SERVER[ $key ] );
				// Handle comma-separated list (X-Forwarded-For)
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return null;
	}

	/**
	 * Get action type display label
	 *
	 * @param string $action_type Action type constant
	 *
	 * @return string Display label
	 */
	public static function get_action_label( $action_type ) {
		$labels = [
			self::ACTION_SEMANTIC_SEARCH    => __( 'Semantic Search', 'wpforo' ),
			self::ACTION_PUBLIC_SEARCH      => __( 'Public Search', 'wpforo' ),
			self::ACTION_TRANSLATION        => __( 'Translation', 'wpforo' ),
			self::ACTION_TOPIC_SUMMARY      => __( 'Topic Summary', 'wpforo' ),
			self::ACTION_TOPIC_SUGGESTIONS  => __( 'Topic Suggestions', 'wpforo' ),
			self::ACTION_BOT_REPLY          => __( 'Bot Reply', 'wpforo' ),
			self::ACTION_SUGGEST_REPLY      => __( 'Suggest Reply', 'wpforo' ),
			self::ACTION_ANALYTICS_INSIGHTS => __( 'Analytics Insights', 'wpforo' ),
			self::ACTION_CONTENT_INDEXING   => __( 'Content Indexing', 'wpforo' ),
			self::ACTION_BATCH_EMBEDDING    => __( 'Batch Embedding', 'wpforo' ),
			self::ACTION_QUEUE_PROCESSING   => __( 'Queue Processing', 'wpforo' ),
			self::ACTION_SPAM_DETECTION     => __( 'Spam Detection', 'wpforo' ),
			self::ACTION_MODERATION         => __( 'Moderation', 'wpforo' ),
			self::ACTION_TASK_EXECUTION     => __( 'Task Execution', 'wpforo' ),
			self::ACTION_CHATBOT            => __( 'AI Chatbot', 'wpforo' ),
		];

		return $labels[ $action_type ] ?? ucwords( str_replace( '_', ' ', $action_type ) );
	}

	/**
	 * Get all action types for filter dropdown
	 *
	 * Note: AI Chatbot is excluded - chat messages are viewed separately via the "AI ChatBot Messages" button
	 *
	 * @return array
	 */
	public static function get_action_types() {
		return [
			self::ACTION_SEMANTIC_SEARCH    => __( 'Semantic Search', 'wpforo' ),
			self::ACTION_PUBLIC_SEARCH      => __( 'Public Search', 'wpforo' ),
			self::ACTION_TRANSLATION        => __( 'Translation', 'wpforo' ),
			self::ACTION_TOPIC_SUMMARY      => __( 'Topic Summary', 'wpforo' ),
			self::ACTION_TOPIC_SUGGESTIONS  => __( 'Topic Suggestions', 'wpforo' ),
			self::ACTION_BOT_REPLY          => __( 'Bot Reply', 'wpforo' ),
			self::ACTION_SUGGEST_REPLY      => __( 'Suggest Reply', 'wpforo' ),
			self::ACTION_ANALYTICS_INSIGHTS => __( 'Analytics Insights', 'wpforo' ),
			self::ACTION_CONTENT_INDEXING   => __( 'Content Indexing', 'wpforo' ),
			self::ACTION_SPAM_DETECTION     => __( 'Spam Detection', 'wpforo' ),
			self::ACTION_MODERATION         => __( 'Moderation', 'wpforo' ),
			self::ACTION_TASK_EXECUTION     => __( 'Task Execution', 'wpforo' ),
		];
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
	public static function get_user_timezone() {
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
			$timezone = new \DateTimeZone( $timezone_string );
		} catch ( \Exception $e ) {
			$timezone = new \DateTimeZone( 'UTC' );
		}

		return $timezone;
	}

	/**
	 * Format UTC datetime in user's timezone
	 *
	 * @param string|int $utc_datetime UTC datetime string or timestamp
	 * @param string     $format       PHP date format
	 *
	 * @return string Formatted datetime in user's timezone
	 */
	public static function format_datetime( $utc_datetime, $format = '' ) {
		if ( empty( $utc_datetime ) ) {
			return '';
		}

		// Default format: WordPress date + time format
		if ( empty( $format ) ) {
			$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		}

		try {
			// Parse the UTC datetime
			$utc_tz = new \DateTimeZone( 'UTC' );

			if ( is_numeric( $utc_datetime ) ) {
				$datetime = new \DateTime( '@' . $utc_datetime );
			} else {
				$datetime = new \DateTime( $utc_datetime, $utc_tz );
			}

			// Convert to user's timezone
			$user_tz = self::get_user_timezone();
			$datetime->setTimezone( $user_tz );

			// Format using date_i18n for translation support
			return date_i18n( $format, $datetime->getTimestamp() + $datetime->getOffset() );
		} catch ( \Exception $e ) {
			// Fallback to original value if parsing fails
			return is_numeric( $utc_datetime ) ? date( $format, $utc_datetime ) : $utc_datetime;
		}
	}

	/**
	 * Get status badge class
	 *
	 * @param string $status Status constant
	 *
	 * @return string CSS class
	 */
	public static function get_status_class( $status ) {
		$classes = [
			self::STATUS_SUCCESS => 'wpforo-ai-log-status-success',
			self::STATUS_ERROR   => 'wpforo-ai-log-status-error',
			self::STATUS_CACHED  => 'wpforo-ai-log-status-cached',
		];

		return $classes[ $status ] ?? '';
	}

	/**
	 * Get status display label
	 *
	 * @param string $status Status constant
	 *
	 * @return string Display label
	 */
	public static function get_status_label( $status ) {
		$labels = [
			self::STATUS_SUCCESS => __( 'Success', 'wpforo' ),
			self::STATUS_ERROR   => __( 'Error', 'wpforo' ),
			self::STATUS_CACHED  => __( 'Cached', 'wpforo' ),
		];

		return $labels[ $status ] ?? ucfirst( $status );
	}
}
