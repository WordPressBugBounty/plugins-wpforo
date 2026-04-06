<?php

namespace wpforo\classes;

/**
 * wpForo AI Task Manager
 *
 * Handles AI task management including:
 * - Task CRUD operations (create, read, update, delete)
 * - Task scheduling via WordPress cron
 * - Task execution coordination with backend API
 * - Task logging and statistics
 *
 * @since 3.0.0
 */
class TaskManager {

	/**
	 * Task types and their labels
	 *
	 * @var array
	 */
	public static $task_types = [
		'topic_generator'  => 'AI Topic Generator',
		'reply_generator'  => 'AI Reply Generator',
		'tag_maintenance'  => 'AI Topic Tag Generator and Cleaning',
	];

	/**
	 * Task statuses
	 *
	 * @var array
	 */
	public static $statuses = [
		'draft'   => 'Draft',
		'active'  => 'Active',
		'paused'  => 'Paused',
		'error'   => 'Error',
	];

	/**
	 * Quality tiers and their credit costs
	 *
	 * @var array
	 */
	public static $quality_tiers = [
		'fast'     => [ 'label' => 'Fast', 'credits' => 1, 'model' => 'Fast Model' ],
		'balanced' => [ 'label' => 'Balanced', 'credits' => 2, 'model' => 'Balanced Model' ],
		'advanced' => [ 'label' => 'Advanced', 'credits' => 3, 'model' => 'Advanced Model' ],
		'premium'  => [ 'label' => 'Premium', 'credits' => 4, 'model' => 'Premium Model' ],
	];

	/**
	 * AIClient instance (lazy loaded)
	 *
	 * @var AIClient|null
	 */
	private $ai_client = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Note: ai_client is lazy loaded via get_ai_client() to avoid initialization order issues

		// Register AJAX handlers (admin only)
		if ( is_admin() ) {
			add_action( 'wp_ajax_wpforo_ai_save_task', [ $this, 'ajax_save_task' ] );
			add_action( 'wp_ajax_wpforo_ai_get_task', [ $this, 'ajax_get_task' ] );
			add_action( 'wp_ajax_wpforo_ai_delete_task', [ $this, 'ajax_delete_task' ] );
			add_action( 'wp_ajax_wpforo_ai_update_task_status', [ $this, 'ajax_update_task_status' ] );
			add_action( 'wp_ajax_wpforo_ai_run_task', [ $this, 'ajax_run_task' ] );
			add_action( 'wp_ajax_wpforo_ai_bulk_task_action', [ $this, 'ajax_bulk_action' ] );
			add_action( 'wp_ajax_wpforo_ai_get_task_logs', [ $this, 'ajax_get_task_logs' ] );
			add_action( 'wp_ajax_wpforo_ai_search_users', [ $this, 'ajax_search_users' ] );
			add_action( 'wp_ajax_wpforo_ai_duplicate_task', [ $this, 'ajax_duplicate_task' ] );
			add_action( 'wp_ajax_wpforo_ai_get_task_stats', [ $this, 'ajax_get_task_stats' ] );
		}

		// Register cron hooks
		add_action( 'wpforo_ai_execute_task', [ $this, 'cron_execute_task' ], 10, 1 );
		add_action( 'wpforo_ai_check_scheduled_tasks', [ $this, 'cron_check_scheduled_tasks' ] );

		// Schedule the task checker if not already scheduled
		if ( ! wp_next_scheduled( 'wpforo_ai_check_scheduled_tasks' ) ) {
			wp_schedule_event( time(), 'hourly', 'wpforo_ai_check_scheduled_tasks' );
		}

		// Check and reschedule overdue tasks when admin page loads
		add_action( 'admin_init', [ $this, 'reschedule_overdue_tasks' ] );

		// Hook into topic/post approval for run_on_approval tasks
		add_action( 'wpforo_topic_approve', [ $this, 'on_topic_approved' ], 20, 1 );
		add_action( 'wpforo_post_approve', [ $this, 'on_post_approved' ], 20, 1 );

		// Also hook into topic/post creation for run_on_approval tasks (for content created with status=0)
		add_action( 'wpforo_after_add_topic', [ $this, 'on_topic_created' ], 20, 2 );
		add_action( 'wpforo_after_add_post', [ $this, 'on_post_created' ], 20, 3 );
	}

	/**
	 * Reschedule overdue active tasks
	 * Called on admin_init to catch tasks that missed their cron execution
	 */
	public function reschedule_overdue_tasks() {
		// Only run on wpForo AI admin page
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'wpforo-ai' ) {
			return;
		}

		global $wpdb;
		$table = $this->get_tasks_table();
		$now = current_time( 'mysql' );

		// Find active tasks that are overdue (more than 5 minutes past next_run_time)
		$five_mins_ago = date( 'Y-m-d H:i:s', strtotime( '-5 minutes' ) );
		$overdue_tasks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT task_id FROM {$table} WHERE status = 'active' AND next_run_time IS NOT NULL AND next_run_time <= %s",
				$five_mins_ago
			),
			ARRAY_A
		);

		foreach ( $overdue_tasks as $task ) {
			$task_id = (int) $task['task_id'];
			// Only reschedule if not already scheduled
			if ( ! wp_next_scheduled( 'wpforo_ai_execute_task', [ $task_id ] ) ) {
				// Schedule for immediate execution
				wp_schedule_single_event( time() + 10, 'wpforo_ai_execute_task', [ $task_id ] );
			}
		}
	}

	/**
	 * Get AIClient instance (lazy loaded)
	 *
	 * @return AIClient|null
	 */
	private function get_ai_client() {
		if ( $this->ai_client === null && isset( WPF()->ai_client ) ) {
			$this->ai_client = WPF()->ai_client;
		}
		return $this->ai_client;
	}

	// =========================================================================
	// TASK CRUD OPERATIONS
	// =========================================================================

	/**
	 * Get tasks table name
	 *
	 * @return string Table name with prefix
	 */
	private function get_tasks_table() {
		global $wpdb;
		return $wpdb->prefix . 'wpforo_ai_tasks';
	}

	/**
	 * Get task logs table name
	 *
	 * @return string Table name with prefix
	 */
	private function get_logs_table() {
		global $wpdb;
		return $wpdb->prefix . 'wpforo_ai_task_logs';
	}

	/**
	 * Get all tasks
	 *
	 * @param array $args Query arguments
	 * @return array Tasks list
	 */
	public function get_tasks( $args = [] ) {
		global $wpdb;

		$defaults = [
			'board_id' => null,
			'status'   => null,
			'type'     => null,
			'orderby'  => 'created_at',
			'order'    => 'DESC',
			'limit'    => 50,
			'offset'   => 0,
		];

		$args = wp_parse_args( $args, $defaults );
		$table = $this->get_tasks_table();

		$where = [];
		$values = [];

		if ( $args['board_id'] !== null ) {
			$where[] = 'board_id = %d';
			$values[] = intval( $args['board_id'] );
		}

		if ( $args['status'] !== null ) {
			$where[] = 'status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		if ( $args['type'] !== null ) {
			$where[] = 'task_type = %s';
			$values[] = sanitize_text_field( $args['type'] );
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		// Validate orderby and order
		$allowed_orderby = [ 'task_id', 'task_name', 'task_type', 'status', 'created_at', 'last_run_time', 'next_run_time' ];
		$orderby = in_array( $args['orderby'], $allowed_orderby ) ? $args['orderby'] : 'created_at';
		$order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$sql = "SELECT * FROM {$table} {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$values[] = intval( $args['limit'] );
		$values[] = intval( $args['offset'] );

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		$results = $wpdb->get_results( $sql, ARRAY_A );

		// Decode JSON config for each task
		foreach ( $results as &$task ) {
			if ( ! empty( $task['config'] ) ) {
				$task['config'] = json_decode( $task['config'], true );
			}
		}

		return $results;
	}

	/**
	 * Get a single task by ID
	 *
	 * @param int $task_id Task ID
	 * @return array|null Task data or null if not found
	 */
	public function get_task( $task_id ) {
		global $wpdb;

		$table = $this->get_tasks_table();
		$task = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE task_id = %d", intval( $task_id ) ),
			ARRAY_A
		);

		if ( $task && ! empty( $task['config'] ) ) {
			$task['config'] = json_decode( $task['config'], true );
		}

		return $task;
	}

	/**
	 * Create a new task
	 *
	 * @param array $data Task data
	 * @return int|false Task ID on success, false on failure
	 */
	public function create_task( $data ) {
		global $wpdb;

		$table = $this->get_tasks_table();
		$current_user_id = get_current_user_id();

		// Sanitize and validate data
		$insert_data = [
			'task_name'   => sanitize_text_field( $data['task_name'] ?? '' ),
			'task_type'   => sanitize_key( $data['task_type'] ?? '' ),
			'status'      => sanitize_key( $data['status'] ?? 'draft' ),
			'board_id'    => intval( $data['board_id'] ?? 0 ),
			'config'      => is_array( $data['config'] ?? null ) ? wp_json_encode( $data['config'] ) : ( $data['config'] ?? '{}' ),
			'created_by'  => $current_user_id,
			'created_at'  => current_time( 'mysql' ),
			'updated_at'  => current_time( 'mysql' ),
		];

		// Validate task type
		if ( ! array_key_exists( $insert_data['task_type'], self::$task_types ) ) {
			return false;
		}

		// Validate status
		if ( ! array_key_exists( $insert_data['status'], self::$statuses ) ) {
			$insert_data['status'] = 'draft';
		}

		$result = $wpdb->insert( $table, $insert_data, [
			'%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s'
		] );

		if ( $result === false ) {
			return false;
		}

		$task_id = $wpdb->insert_id;

		// Schedule task if active AND not run_on_approval
		// run_on_approval tasks trigger on topic/post approval, not cron
		$config = is_array( $data['config'] ?? null ) ? $data['config'] : json_decode( $data['config'] ?? '{}', true );
		$run_on_approval = ! empty( $config['run_on_approval'] );

		if ( $insert_data['status'] === 'active' && ! $run_on_approval ) {
			$this->schedule_next_run( $task_id );
		}

		return $task_id;
	}

	/**
	 * Update an existing task
	 *
	 * @param int   $task_id Task ID
	 * @param array $data    Task data to update
	 * @return bool True on success
	 */
	public function update_task( $task_id, $data ) {
		global $wpdb;

		$table = $this->get_tasks_table();
		$task_id = intval( $task_id );

		// Get existing task
		$existing = $this->get_task( $task_id );
		if ( ! $existing ) {
			return false;
		}

		$update_data = [];
		$formats = [];

		// Only update provided fields
		if ( isset( $data['task_name'] ) ) {
			$update_data['task_name'] = sanitize_text_field( $data['task_name'] );
			$formats[] = '%s';
		}

		if ( isset( $data['task_type'] ) && array_key_exists( $data['task_type'], self::$task_types ) ) {
			$update_data['task_type'] = sanitize_key( $data['task_type'] );
			$formats[] = '%s';
		}

		if ( isset( $data['status'] ) && array_key_exists( $data['status'], self::$statuses ) ) {
			$update_data['status'] = sanitize_key( $data['status'] );
			$formats[] = '%s';
		}

		if ( isset( $data['board_id'] ) ) {
			$update_data['board_id'] = intval( $data['board_id'] );
			$formats[] = '%d';
		}

		if ( isset( $data['config'] ) ) {
			$update_data['config'] = is_array( $data['config'] ) ? wp_json_encode( $data['config'] ) : $data['config'];
			$formats[] = '%s';
		}

		if ( empty( $update_data ) ) {
			return true; // Nothing to update
		}

		$update_data['updated_at'] = current_time( 'mysql' );
		$formats[] = '%s';

		$result = $wpdb->update(
			$table,
			$update_data,
			[ 'task_id' => $task_id ],
			$formats,
			[ '%d' ]
		);

		// Handle scheduling based on status change or config update
		$new_status = $data['status'] ?? $existing['status'];
		$config_changed = isset( $data['config'] );

		// Check if run_on_approval is enabled in the new config
		$new_config = isset( $data['config'] )
			? ( is_array( $data['config'] ) ? $data['config'] : json_decode( $data['config'], true ) )
			: ( $existing['config'] ?? [] );
		$run_on_approval = ! empty( $new_config['run_on_approval'] );

		if ( $new_status === 'active' ) {
			// If run_on_approval is enabled, unschedule any existing cron and skip scheduling
			if ( $run_on_approval ) {
				$this->unschedule_task( $task_id );
			} elseif ( $existing['status'] !== 'active' || $config_changed ) {
				// Reschedule if: status changed to active OR config changed while active
				// Clear old schedule first
				$this->unschedule_task( $task_id );
				// Schedule with new config
				$this->schedule_next_run( $task_id );
			}
		} elseif ( $new_status !== 'active' && $existing['status'] === 'active' ) {
			// Status changed from active to non-active
			$this->unschedule_task( $task_id );
		}

		return $result !== false;
	}

	/**
	 * Delete a task
	 *
	 * @param int $task_id Task ID
	 * @return bool True on success
	 */
	public function delete_task( $task_id ) {
		global $wpdb;

		$task_id = intval( $task_id );

		// Unschedule any pending runs
		$this->unschedule_task( $task_id );

		// Delete logs first (foreign key constraint)
		$logs_table = $this->get_logs_table();
		$wpdb->delete( $logs_table, [ 'task_id' => $task_id ], [ '%d' ] );

		// Delete task
		$table = $this->get_tasks_table();
		$result = $wpdb->delete( $table, [ 'task_id' => $task_id ], [ '%d' ] );

		return $result !== false;
	}

	// =========================================================================
	// TASK SCHEDULING
	// =========================================================================

	/**
	 * Schedule the next run for a task
	 *
	 * @param int $task_id Task ID
	 * @return bool True if scheduled
	 */
	public function schedule_next_run( $task_id ) {
		$task = $this->get_task( $task_id );
		if ( ! $task || $task['status'] !== 'active' ) {
			return false;
		}

		$config = $task['config'];

		// run_on_approval tasks don't use cron scheduling - they trigger on topic/post approval
		if ( ! empty( $config['run_on_approval'] ) ) {
			return false;
		}
		$next_run = $this->calculate_next_run_time( $config );

		if ( $next_run ) {
			// Update next_run_time in database
			global $wpdb;
			$table = $this->get_tasks_table();
			$wpdb->update(
				$table,
				[ 'next_run_time' => date( 'Y-m-d H:i:s', $next_run ) ],
				[ 'task_id' => $task_id ],
				[ '%s' ],
				[ '%d' ]
			);

			// Schedule WordPress cron event
			wp_schedule_single_event( $next_run, 'wpforo_ai_execute_task', [ $task_id ] );

			return true;
		}

		return false;
	}

	/**
	 * Unschedule a task
	 *
	 * @param int $task_id Task ID
	 */
	public function unschedule_task( $task_id ) {
		wp_clear_scheduled_hook( 'wpforo_ai_execute_task', [ $task_id ] );
	}

	/**
	 * Calculate next run time based on task config
	 *
	 * @param array $config Task configuration
	 * @return int|false Unix timestamp or false
	 */
	private function calculate_next_run_time( $config ) {
		$schedule_type = $config['schedule_type'] ?? 'recurring';

		if ( $schedule_type === 'once' ) {
			// One-time execution - schedule immediately if in active hours
			return $this->get_next_active_time( $config );
		}

		// Recurring execution - parse frequency
		// Support combined frequency format (e.g., "hourly", "3hours", "daily", "weekly")
		// and legacy separate frequency_value/frequency_unit format
		$interval = 0;

		if ( isset( $config['frequency'] ) ) {
			// Parse combined frequency format
			$frequency = $config['frequency'];
			switch ( $frequency ) {
				case 'hourly':
					$interval = HOUR_IN_SECONDS;
					break;
				case '3hours':
					$interval = 3 * HOUR_IN_SECONDS;
					break;
				case '6hours':
					$interval = 6 * HOUR_IN_SECONDS;
					break;
				case 'daily':
					$interval = DAY_IN_SECONDS;
					break;
				case '3days':
					$interval = 3 * DAY_IN_SECONDS;
					break;
				case 'weekly':
					$interval = WEEK_IN_SECONDS;
					break;
			}
		}

		// Fallback to legacy format if combined frequency not set or invalid
		if ( $interval === 0 ) {
			$frequency_value = intval( $config['frequency_value'] ?? 1 );
			$frequency_unit = $config['frequency_unit'] ?? 'day';

			switch ( $frequency_unit ) {
				case 'hour':
					$interval = $frequency_value * HOUR_IN_SECONDS;
					break;
				case 'day':
					$interval = $frequency_value * DAY_IN_SECONDS;
					break;
				case 'week':
					$interval = $frequency_value * WEEK_IN_SECONDS;
					break;
			}
		}

		if ( $interval === 0 ) {
			return false;
		}

		$next_time = time() + $interval;

		// Adjust to active hours if configured
		return $this->get_next_active_time( $config, $next_time );
	}

	/**
	 * Get the next time within active hours
	 *
	 * @param array $config Task configuration
	 * @param int   $from   Starting timestamp (default: now)
	 * @return int Unix timestamp
	 */
	private function get_next_active_time( $config, $from = null ) {
		$from = $from ?? time();

		// Check active days - default to all days if not specified
		$active_days = $config['active_days'] ?? [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ];
		if ( empty( $active_days ) ) {
			$active_days = [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ];
		}

		$day_map = [
			'sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3,
			'thu' => 4, 'fri' => 5, 'sat' => 6
		];

		// Check active time window - default to 24/7 if not specified
		$time_start = $config['active_time_start'] ?? '00:00';
		$time_end = $config['active_time_end'] ?? '23:59';

		// Parse times
		$start_parts = explode( ':', $time_start );
		$end_parts = explode( ':', $time_end );
		$start_hour = intval( $start_parts[0] );
		$start_min = intval( $start_parts[1] ?? 0 );
		$end_hour = intval( $end_parts[0] );
		$end_min = intval( $end_parts[1] ?? 0 );

		// Find next valid time
		$check_time = $from;
		for ( $i = 0; $i < 14; $i++ ) { // Check up to 2 weeks ahead
			$day_of_week = date( 'w', $check_time );
			$day_abbrev = array_search( $day_of_week, $day_map );

			if ( in_array( $day_abbrev, $active_days ) ) {
				// Check time window
				$current_hour = intval( date( 'G', $check_time ) );
				$current_min = intval( date( 'i', $check_time ) );

				// If before start time, set to start time
				if ( $current_hour < $start_hour || ( $current_hour === $start_hour && $current_min < $start_min ) ) {
					return strtotime( date( 'Y-m-d', $check_time ) . ' ' . sprintf( '%02d:%02d:00', $start_hour, $start_min ) );
				}

				// If within time window, return as is
				if ( $current_hour < $end_hour || ( $current_hour === $end_hour && $current_min <= $end_min ) ) {
					return $check_time;
				}
			}

			// Move to next day at start time
			$check_time = strtotime( date( 'Y-m-d', $check_time ) . ' +1 day ' . sprintf( '%02d:%02d:00', $start_hour, $start_min ) );
		}

		// Fallback to from time
		return $from;
	}

	/**
	 * Cron handler to check and execute scheduled tasks
	 */
	public function cron_check_scheduled_tasks() {
		global $wpdb;

		$table = $this->get_tasks_table();
		$now = current_time( 'mysql' );

		// Find active tasks with next_run_time in the past
		$tasks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT task_id FROM {$table} WHERE status = 'active' AND next_run_time <= %s",
				$now
			),
			ARRAY_A
		);

		foreach ( $tasks as $task ) {
			// Schedule immediate execution if not already scheduled
			if ( ! wp_next_scheduled( 'wpforo_ai_execute_task', [ $task['task_id'] ] ) ) {
				wp_schedule_single_event( time(), 'wpforo_ai_execute_task', [ $task['task_id'] ] );
			}
		}
	}

	/**
	 * Cron handler to execute a task
	 *
	 * @param int $task_id Task ID
	 */
	public function cron_execute_task( $task_id ) {
		$task = $this->get_task( $task_id );
		if ( ! $task || $task['status'] !== 'active' ) {
			return;
		}

		// Execute the task
		$result = $this->execute_task( $task_id );

		// Schedule next run
		$this->schedule_next_run( $task_id );
	}

	// =========================================================================
	// TASK EXECUTION
	// =========================================================================

	/**
	 * Execute a task
	 *
	 * @param int $task_id Task ID
	 * @return array Execution result
	 */
	public function execute_task( $task_id ) {
		$task = $this->get_task( $task_id );
		if ( ! $task ) {
			return [ 'success' => false, 'error' => 'Task not found' ];
		}

		$start_time = microtime( true );

		// Check credit threshold
		if ( ! $this->check_credit_threshold( $task ) ) {
			return $this->log_execution( $task_id, [
				'status'        => 'skipped',
				'error_message' => 'Credit threshold reached',
			] );
		}

		// Execute based on task type
		$result = [];
		switch ( $task['task_type'] ) {
			case 'topic_generator':
				$result = $this->execute_topic_generator( $task );
				break;
			case 'reply_generator':
				$result = $this->execute_reply_generator( $task );
				break;
			case 'tag_maintenance':
				$result = $this->execute_tag_maintenance( $task );
				break;
			default:
				$result = [ 'success' => false, 'error' => 'Unknown task type' ];
		}

		// Calculate execution time
		$execution_time = microtime( true ) - $start_time;

		// Log the execution
		return $this->log_execution( $task_id, [
			'status'             => $result['success'] ? 'completed' : 'error',
			'items_created'      => $result['items_created'] ?? 0,
			'credits_used'       => $result['credits_used'] ?? 0,
			'execution_duration' => $execution_time,
			'error_message'      => $result['error'] ?? null,
			'result_data'        => $result['data'] ?? null,
		] );
	}

	/**
	 * Resolve the response language from task config.
	 *
	 * Converts a 2-letter language code (or empty for auto) to an English language name
	 * that the backend LLM can understand (e.g., "Spanish", "French").
	 *
	 * @param array $config Task configuration array
	 * @return string English language name
	 */
	private function resolve_response_language( $config ) {
		$code = $config['response_language'] ?? '';

		if ( ! function_exists( 'wpforo_get_ai_languages' ) ) {
			return $code ?: 'English';
		}

		$languages = wpforo_get_ai_languages();

		if ( ! empty( $code ) ) {
			// Map 2-letter code to English name
			foreach ( $languages as $lang ) {
				if ( $lang['code'] === $code ) {
					return $lang['name'];
				}
			}
			return $code; // Fallback: return the code itself
		}

		// Auto: resolve from board locale
		$board_locale = WPF()->board->locale;
		foreach ( $languages as $lang ) {
			if ( $lang['locale'] === $board_locale ) {
				return $lang['name'];
			}
		}

		return 'English'; // Final fallback
	}

	/**
	 * Execute topic generator task
	 *
	 * @param array $task Task data
	 * @return array Execution result
	 */
	private function execute_topic_generator( $task ) {
		$config = $task['config'];

		// Call backend API for topic generation
		$ai_client = $this->get_ai_client();
		$api_key = $ai_client ? $ai_client->get_stored_api_key() : '';
		if ( empty( $api_key ) ) {
			return [ 'success' => false, 'error' => 'AI service not connected' ];
		}

		$topics_per_run = intval( $config['topics_per_run'] ?? 1 );
		$quality_tier = $config['quality_tier'] ?? 'balanced';
		$target_forums = $config['target_forums'] ?? [];

		// Get forum details for context
		$forum_context = [];
		foreach ( $target_forums as $forum_id ) {
			$forum = WPF()->forum->get_forum( $forum_id );
			if ( $forum ) {
				$forum_context[] = [
					'id'    => $forum['forumid'],
					'title' => $forum['title'],
					'slug'  => $forum['slug'],
				];
			}
		}

		// Build custom instructions from topic_theme and content options
		$custom_instructions = $this->build_topic_custom_instructions( $config );

		// Build API request
		$request_data = [
			'task_type'           => 'topic_generator',
			'topics_count'        => $topics_per_run,
			'quality'             => $quality_tier,
			'target_forums'       => $forum_context,
			'topic_style'         => $config['topic_style'] ?? 'neutral',
			'custom_instructions' => $custom_instructions,
			'board_id'            => $task['board_id'],
			'response_language'   => $this->resolve_response_language( $config ),
		];

		// Make API call
		$response = $this->call_tasks_api( 'generate', $request_data, $api_key );

		if ( is_wp_error( $response ) ) {
			return [ 'success' => false, 'error' => $response->get_error_message() ];
		}

		// Process response and create topics
		$topics_created = 0;
		$topics_skipped = 0;
		$credits_used = 0;

		// Check if duplicate prevention is enabled
		$duplicate_prevention = ! empty( $config['duplicate_prevention'] );
		$similarity_threshold = intval( $config['similarity_threshold'] ?? 75 );
		$check_days = intval( $config['duplicate_check_days'] ?? 90 );

		if ( ! empty( $response['topics'] ) ) {
			foreach ( $response['topics'] as $topic_data ) {
				// Check for duplicates if enabled
				if ( $duplicate_prevention ) {
					$is_duplicate = $this->check_duplicate_topic(
						$topic_data['title'] ?? '',
						$api_key,
						$similarity_threshold,
						$check_days
					);

					if ( $is_duplicate ) {
						$topics_skipped++;
						continue; // Skip this topic
					}
				}

				$created = $this->create_forum_topic( $task, $topic_data );
				if ( $created ) {
					$topics_created++;
				}
			}
			$credits_used = $response['credits_used'] ?? 0;
		}

		// Update task statistics
		$this->update_task_statistics( $task['task_id'], $topics_created, $credits_used );

		return [
			'success'        => true,
			'items_created'  => $topics_created,
			'items_skipped'  => $topics_skipped,
			'credits_used'   => $credits_used,
			'data'           => $response,
		];
	}

	/**
	 * Check if a topic title is a duplicate using semantic search
	 *
	 * Uses VectorStorageManager for semantic search (supports both local and cloud modes).
	 *
	 * @param string $title              Topic title to check
	 * @param string $api_key            API key for search (kept for compatibility, unused)
	 * @param int    $similarity_threshold Threshold percentage (0-100)
	 * @param int    $check_days         Days to look back (0 = all topics) - reserved for future use
	 * @return bool True if duplicate found
	 */
	private function check_duplicate_topic( $title, $api_key, $similarity_threshold = 75, $check_days = 90 ) {
		if ( empty( $title ) ) {
			return false;
		}

		// Check if vector storage is available
		if ( ! isset( WPF()->vector_storage ) ) {
			return false; // Can't check, allow topic creation
		}

		// Use VectorStorageManager for semantic search (handles both local and cloud modes)
		// Note: check_days filtering is reserved for future implementation
		$response = WPF()->vector_storage->semantic_search( $title, 10 );

		if ( is_wp_error( $response ) ) {
			return false; // On error, allow topic creation
		}

		// Check results for similarity above threshold
		$threshold_decimal = $similarity_threshold / 100;

		if ( ! empty( $response['results'] ) ) {
			foreach ( $response['results'] as $result ) {
				$score = floatval( $result['score'] ?? 0 );
				if ( $score >= $threshold_decimal ) {
					// Found a similar topic
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Execute reply generator task
	 *
	 * @param array $task Task data
	 * @return array Execution result
	 */
	private function execute_reply_generator( $task ) {
		$config = $task['config'];

		// Call backend API for reply generation
		$ai_client = $this->get_ai_client();
		$api_key = $ai_client ? $ai_client->get_stored_api_key() : '';
		if ( empty( $api_key ) ) {
			return [ 'success' => false, 'error' => 'AI service not connected' ];
		}

		$replies_per_run = intval( $config['replies_per_run'] ?? 1 );
		$quality_tier = $config['quality_tier'] ?? 'balanced';
		$target_forums = $config['target_forums'] ?? [];

		// Find topics to reply to based on selection criteria
		$topics = $this->find_topics_for_reply( $task );
		if ( empty( $topics ) ) {
			return [ 'success' => true, 'items_created' => 0, 'credits_used' => 0, 'data' => [ 'message' => 'No eligible topics found' ] ];
		}

		// Build custom instructions from config
		$custom_instructions = $this->build_reply_custom_instructions( $config );

		// Get knowledge source setting (forum_only, forum_and_ai, forum_and_web, forum_and_web_and_ai, ai_only)
		$knowledge_source = $config['knowledge_source'] ?? 'forum_only';

		// Get reply strategy (determines context and where to place the reply)
		$reply_strategy = $config['reply_strategy'] ?? 'first_post';

		// Get local RAG context if using local storage mode and RAG is enabled
		$rag_contexts = null;
		$use_rag = in_array( $knowledge_source, [ 'forum_only', 'forum_and_ai', 'forum_and_web', 'forum_and_web_and_ai' ], true );
		if ( $use_rag && WPF()->vector_storage && WPF()->vector_storage->is_local_mode() ) {
			$rag_contexts = $this->get_local_rag_contexts( $topics );
		}

		// Build API request
		$request_data = [
			'task_type'           => 'reply_generator',
			'topics'              => $topics,
			'replies_count'       => $replies_per_run,
			'quality'             => $quality_tier,
			'reply_style'         => $config['reply_style'] ?? 'helpful',
			'reply_strategy'      => $reply_strategy,
			'custom_instructions' => $custom_instructions,
			'knowledge_source'    => $knowledge_source,
			'board_id'            => $task['board_id'],
			'response_language'   => $this->resolve_response_language( $config ),
		];

		// Add local RAG contexts if available (for local storage mode)
		if ( ! empty( $rag_contexts ) ) {
			$request_data['rag_contexts'] = $rag_contexts;
		}

		// Build topics map for looking up context when processing replies
		$topics_map = [];
		foreach ( $topics as $topic ) {
			$topics_map[ $topic['topic_id'] ] = $topic;
		}

		// Make API call
		$response = $this->call_tasks_api( 'generate', $request_data, $api_key );

		if ( is_wp_error( $response ) ) {
			return [ 'success' => false, 'error' => $response->get_error_message() ];
		}

		// Process response and create replies
		$replies_created = 0;
		$credits_used = 0;

		if ( ! empty( $response['replies'] ) ) {
			foreach ( $response['replies'] as $reply_data ) {
				$topic_id = intval( $reply_data['topic_id'] ?? 0 );
				$topic_context = $topics_map[ $topic_id ] ?? [];
				$created = $this->create_forum_reply( $task, $reply_data, $topic_context );
				if ( $created ) {
					$replies_created++;
				}
			}
			$credits_used = $response['credits_used'] ?? 0;
		}

		// Update task statistics
		$this->update_task_statistics( $task['task_id'], $replies_created, $credits_used );

		return [
			'success'       => true,
			'items_created' => $replies_created,
			'credits_used'  => $credits_used,
			'data'          => $response,
		];
	}

	/**
	 * Execute tag maintenance task
	 *
	 * Generates and cleans tags for topics using AI.
	 * All topics in one run are processed in a SINGLE LLM call.
	 *
	 * @param array $task Task data
	 * @return array Execution result
	 */
	private function execute_tag_maintenance( $task ) {
		$config = $task['config'];

		// Call backend API for tag generation
		$ai_client = $this->get_ai_client();
		$api_key = $ai_client ? $ai_client->get_stored_api_key() : '';
		if ( empty( $api_key ) ) {
			return [ 'success' => false, 'error' => 'AI service not connected' ];
		}

		$topics_per_run = intval( $config['topics_per_run'] ?? 20 );
		$quality_tier = $config['quality_tier'] ?? 'balanced';
		$max_tags = intval( $config['max_tags'] ?? 5 );

		// Tag maintenance settings
		$preserve_existing = ! empty( $config['preserve_existing'] );
		$maintain_vocabulary = ! empty( $config['maintain_vocabulary'] );
		$remove_duplicates = ! empty( $config['remove_duplicates'] );
		$remove_irrelevant = ! empty( $config['remove_irrelevant'] );
		$lowercase = ! empty( $config['lowercase'] );

		// Find topics to process
		$topics = $this->find_topics_for_tagging( $task );
		if ( empty( $topics ) ) {
			return [ 'success' => true, 'items_created' => 0, 'credits_used' => 0, 'data' => [ 'message' => 'No eligible topics found' ] ];
		}

		// Get existing forum vocabulary for consistency
		$existing_vocabulary = [];
		if ( $maintain_vocabulary ) {
			$existing_vocabulary = $this->get_forum_tag_vocabulary( $task['board_id'] );
		}

		// Build API request - ALL topics in ONE request
		$request_data = [
			'task_type'           => 'tag_maintenance',
			'topics'              => $topics,
			'max_tags'            => $max_tags,
			'preserve_existing'   => $preserve_existing,
			'maintain_vocabulary' => $maintain_vocabulary,
			'remove_duplicates'   => $remove_duplicates,
			'remove_irrelevant'   => $remove_irrelevant,
			'lowercase'           => $lowercase,
			'quality'             => $quality_tier,
			'existing_vocabulary' => $existing_vocabulary,
			'board_id'            => $task['board_id'],
		];

		// Make API call (single call for all topics)
		$response = $this->call_tasks_api( 'generate', $request_data, $api_key );

		if ( is_wp_error( $response ) ) {
			return [ 'success' => false, 'error' => $response->get_error_message() ];
		}

		// Process response and update topic tags
		$topics_updated = 0;
		$credits_used = 0;

		if ( ! empty( $response['results'] ) ) {
			foreach ( $response['results'] as $result_data ) {
				$topic_id = intval( $result_data['topic_id'] ?? 0 );
				$new_tags = $result_data['tags'] ?? [];

				if ( $topic_id && ! empty( $new_tags ) ) {
					$updated = $this->update_topic_tags( $topic_id, $new_tags );
					if ( $updated ) {
						$topics_updated++;
					}
				}
			}
			$credits_used = $response['credits_used'] ?? 0;
		}

		// Update task statistics
		$this->update_task_statistics( $task['task_id'], $topics_updated, $credits_used );

		return [
			'success'       => true,
			'items_created' => $topics_updated,
			'credits_used'  => $credits_used,
			'data'          => $response,
		];
	}

	/**
	 * Find topics eligible for tag maintenance based on task config
	 *
	 * @param array $task Task data
	 * @return array Topics for tagging
	 */
	private function find_topics_for_tagging( $task ) {
		global $wpdb;

		$config = $task['config'];
		$limit = intval( $config['topics_per_run'] ?? 20 );
		$only_not_tagged = ! empty( $config['only_not_tagged'] );

		$all_topic_ids = [];

		// 1. Get specific topic IDs if provided (filter out already processed)
		$target_topic_ids_raw = $config['target_topic_ids'] ?? '';
		if ( ! empty( $target_topic_ids_raw ) ) {
			$target_topic_ids_raw = str_replace( [ "\r\n", "\r", "\n" ], ',', $target_topic_ids_raw );
			$specific_ids = array_map( 'trim', explode( ',', $target_topic_ids_raw ) );
			$specific_ids = array_filter( $specific_ids, 'is_numeric' );
			$specific_ids = array_map( 'intval', $specific_ids );

			// Filter out already processed topics (task_tag > 0)
			if ( ! empty( $specific_ids ) ) {
				$ids_str = implode( ',', $specific_ids );
				$unprocessed_ids = $wpdb->get_col(
					"SELECT topicid FROM " . WPF()->tables->topics . "
					 WHERE topicid IN ($ids_str) AND task_tag = 0"
				);
				$all_topic_ids = array_merge( $all_topic_ids, $unprocessed_ids );
			}
		}

		// 2. Get topics from target forums
		$target_forums = $config['tag_target_forum_ids'] ?? [];
		if ( ! empty( $target_forums ) ) {
			$forum_ids = array_map( 'intval', $target_forums );
			$forum_ids_str = implode( ',', $forum_ids );

			$where_clauses = [ "t.forumid IN ($forum_ids_str)", "t.status = 0", "t.private = 0" ];

			// Exclude already processed topics (task_tag > 0 means already processed)
			$where_clauses[] = "t.task_tag = 0";

			// Only not tagged filter
			if ( $only_not_tagged ) {
				$where_clauses[] = "(t.tags IS NULL OR t.tags = '')";
			}

			// Date range filters
			if ( ! empty( $config['date_range_from'] ) ) {
				$where_clauses[] = $wpdb->prepare( "t.created >= %s", $config['date_range_from'] );
			}
			if ( ! empty( $config['date_range_to'] ) ) {
				$where_clauses[] = $wpdb->prepare( "t.created <= %s", $config['date_range_to'] );
			}

			$where_sql = implode( ' AND ', $where_clauses );

			// Get random topics from forums
			$forum_topic_ids = $wpdb->get_col(
				"SELECT t.topicid FROM " . WPF()->tables->topics . " t
				 WHERE $where_sql
				 ORDER BY RAND()
				 LIMIT $limit"
			);

			if ( $forum_topic_ids ) {
				$all_topic_ids = array_merge( $all_topic_ids, $forum_topic_ids );
			}
		}

		// Remove duplicates and limit
		$all_topic_ids = array_unique( $all_topic_ids );
		$all_topic_ids = array_slice( $all_topic_ids, 0, $limit );

		if ( empty( $all_topic_ids ) ) {
			return [];
		}

		// Build topics data with title, content, and existing tags
		$topics = [];
		foreach ( $all_topic_ids as $topic_id ) {
			$topic = WPF()->topic->get_topic( $topic_id );
			if ( ! $topic ) continue;

			// Get first post content
			$first_post = WPF()->post->get_post( $topic['first_postid'] );
			$content = $first_post ? wp_strip_all_tags( $first_post['body'] ) : '';
			$content = mb_substr( $content, 0, 2000 ); // Limit content length

			// Get existing tags
			$existing_tags = [];
			if ( ! empty( $topic['tags'] ) ) {
				$existing_tags = array_map( 'trim', explode( ',', $topic['tags'] ) );
				$existing_tags = array_filter( $existing_tags );
			}

			$topics[] = [
				'topic_id'      => intval( $topic_id ),
				'title'         => $topic['title'],
				'content'       => $content,
				'existing_tags' => $existing_tags,
			];
		}

		return $topics;
	}

	/**
	 * Get existing tag vocabulary from the forum
	 *
	 * @param int $board_id Board ID
	 * @return array List of existing tags
	 */
	private function get_forum_tag_vocabulary( $board_id = 0 ) {
		// Get most used tags from the tags table
		$tags = WPF()->topic->get_tags( [
			'orderby'   => 'count',
			'order'     => 'DESC',
			'row_count' => 200,
		] );

		$vocabulary = [];
		if ( ! empty( $tags ) ) {
			foreach ( $tags as $tag ) {
				$vocabulary[] = $tag['tag'];
			}
		}

		return $vocabulary;
	}

	/**
	 * Update tags for a topic
	 *
	 * @param int   $topic_id Topic ID
	 * @param array $new_tags New tags array
	 * @return bool Success
	 */
	private function update_topic_tags( $topic_id, $new_tags ) {
		$topic = WPF()->topic->get_topic( $topic_id );
		if ( ! $topic ) {
			return false;
		}

		// Sanitize and format tags
		$tags_string = WPF()->topic->sanitize_tags( $new_tags, false, true );

		// Update topic tags using the Topics class method
		WPF()->topic->edit_tags( $tags_string, $topic );

		// Update the topic record with tags and task_tag timestamp
		WPF()->db->update(
			WPF()->tables->topics,
			[
				'tags'     => $tags_string,
				'task_tag' => time(), // Mark as processed with current timestamp
			],
			[ 'topicid' => $topic_id ],
			[ '%s', '%d' ],
			[ '%d' ]
		);

		// Clean topic cache to ensure fresh data
		wpforo_clean_cache( 'topic', $topic_id );
		wpforo_clean_cache( 'tag' );

		return true;
	}

	/**
	 * Find topics eligible for reply based on task config
	 *
	 * Supports multiple targeting methods:
	 * - target_topic_ids: Specific topic IDs (any status)
	 * - reply_target_forums: Random topics from selected forums
	 * - date_range_from/to: Filter by topic creation date
	 *
	 * @param array $task Task data
	 * @return array Topics for reply
	 */
	private function find_topics_for_reply( $task ) {
		global $wpdb;

		$config = $task['config'];
		$reply_strategy = $config['reply_strategy'] ?? 'first_post';
		$limit = intval( $config['replies_per_run'] ?? 1 );
		$only_not_replied = ! empty( $config['only_not_replied'] );

		$all_topic_ids = [];

		// 1. Get specific topic IDs (always included regardless of status)
		$target_topic_ids_raw = $config['target_topic_ids'] ?? '';
		if ( ! empty( $target_topic_ids_raw ) ) {
			$target_topic_ids_raw = str_replace( [ "\r\n", "\r", "\n" ], ',', $target_topic_ids_raw );
			$specific_ids = array_map( 'trim', explode( ',', $target_topic_ids_raw ) );
			$specific_ids = array_filter( $specific_ids, function( $id ) {
				return is_numeric( $id ) && intval( $id ) > 0;
			} );
			$all_topic_ids = array_merge( $all_topic_ids, array_map( 'intval', $specific_ids ) );
		}

		// 2. Get topics from forums/date range (random, excludes private/closed/unapproved)
		$target_forums = $config['reply_target_forums'] ?? [];
		$date_from = $config['date_range_from'] ?? '';
		$date_to = $config['date_range_to'] ?? '';

		if ( ! empty( $target_forums ) || ! empty( $date_from ) || ! empty( $date_to ) ) {
			$forum_topic_ids = $this->find_random_forum_topics( $target_forums, $date_from, $date_to, $limit * 3, $only_not_replied );
			$all_topic_ids = array_merge( $all_topic_ids, $forum_topic_ids );
		}

		// Remove duplicates
		$all_topic_ids = array_unique( $all_topic_ids );

		if ( empty( $all_topic_ids ) ) {
			return [];
		}

		// Shuffle and limit
		shuffle( $all_topic_ids );
		$all_topic_ids = array_slice( $all_topic_ids, 0, $limit );

		// Fetch each topic and build context
		$filtered = [];
		foreach ( $all_topic_ids as $topic_id ) {
			$topic = WPF()->topic->get_topic( $topic_id );
			if ( ! $topic ) {
				continue;
			}

			// Skip topics with replies if only_not_replied is enabled
			// posts = 1 means only the first post exists (no replies)
			if ( $only_not_replied && intval( $topic['posts'] ?? 0 ) > 1 ) {
				continue;
			}

			// Build topic context based on reply strategy
			$topic_data = $this->build_topic_context_for_reply( $topic, $reply_strategy );
			if ( $topic_data ) {
				$filtered[] = $topic_data;
			}
		}

		return $filtered;
	}

	/**
	 * Find random topics from forums with optional date filtering
	 *
	 * Excludes private, closed, and unapproved topics.
	 *
	 * @param array  $forum_ids       Forum IDs to search (empty = all forums)
	 * @param string $date_from       Start date (Y-m-d format)
	 * @param string $date_to         End date (Y-m-d format)
	 * @param int    $limit           Maximum topics to return
	 * @param bool   $only_not_replied Only include topics without replies (posts = 1)
	 * @return array Topic IDs
	 */
	private function find_random_forum_topics( $forum_ids, $date_from, $date_to, $limit = 10, $only_not_replied = false ) {
		global $wpdb;

		$topics_table = WPF()->tables->topics;

		$where = [];
		$values = [];

		// Exclude private topics
		$where[] = 'private = 0';

		// Exclude closed topics
		$where[] = 'closed = 0';

		// Exclude unapproved topics (status = 0 means approved)
		$where[] = 'status = 0';

		// Only include topics without replies (posts = 1 means only first post, no replies)
		if ( $only_not_replied ) {
			$where[] = 'posts = 1';
		}

		// Filter by forums
		if ( ! empty( $forum_ids ) ) {
			$forum_ids = array_map( 'intval', $forum_ids );
			$placeholders = implode( ',', array_fill( 0, count( $forum_ids ), '%d' ) );
			$where[] = "forumid IN ($placeholders)";
			$values = array_merge( $values, $forum_ids );
		}

		// Filter by date range
		if ( ! empty( $date_from ) ) {
			$where[] = 'created >= %s';
			$values[] = $date_from . ' 00:00:00';
		}
		if ( ! empty( $date_to ) ) {
			$where[] = 'created <= %s';
			$values[] = $date_to . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );

		// Get random topics
		$query = "SELECT topicid FROM {$topics_table} WHERE {$where_sql} ORDER BY RAND() LIMIT %d";
		$values[] = $limit;

		$results = $wpdb->get_col( $wpdb->prepare( $query, $values ) );

		return $results ? array_map( 'intval', $results ) : [];
	}

	/**
	 * Build topic context for API based on reply strategy
	 *
	 * @param array  $topic          Topic data from wpForo
	 * @param string $reply_strategy Strategy: first_post, whole_topic, or last_post
	 * @return array|null Topic context for API
	 */
	private function build_topic_context_for_reply( $topic, $reply_strategy ) {
		$topic_id = intval( $topic['topicid'] );
		$first_postid = intval( $topic['first_postid'] ?? 0 );

		// Get first post (always needed)
		$first_post = WPF()->post->get_post( $first_postid );
		if ( ! $first_post ) {
			return null;
		}

		$first_post_content = [
			'postid'   => $first_postid,
			'author'   => WPF()->member->get_member( $first_post['userid'] )['display_name'] ?? 'Unknown',
			'content'  => wp_strip_all_tags( $first_post['body'] ),
			'parentid' => 0,
			'root'     => -1,
		];

		$topic_data = [
			'topic_id'       => $topic_id,
			'forum_id'       => $topic['forumid'],
			'title'          => $topic['title'],
			'first_postid'   => $first_postid,
			'reply_strategy' => $reply_strategy,
		];

		switch ( $reply_strategy ) {
			case 'first_post':
				// Only include first post content
				$topic_data['posts'] = [ $first_post_content ];
				$topic_data['context_type'] = 'first_post_only';
				break;

			case 'whole_topic':
				// Include first post + last 100 replies for full context
				$posts = WPF()->post->get_posts( [
					'topicid'   => $topic_id,
					'row_count' => 100,
					'orderby'   => 'created',
					'order'     => 'ASC',
				] );
				$post_contents = [];
				foreach ( $posts as $post ) {
					$post_contents[] = [
						'postid'   => intval( $post['postid'] ),
						'author'   => WPF()->member->get_member( $post['userid'] )['display_name'] ?? 'Unknown',
						'content'  => wp_strip_all_tags( $post['body'] ),
						'parentid' => intval( $post['parentid'] ),
						'root'     => intval( $post['root'] ),
					];
				}
				$topic_data['posts'] = $post_contents;
				$topic_data['context_type'] = 'whole_topic';
				break;

			case 'last_post':
				// Get last post and its sub-thread context
				$last_post = WPF()->post->get_posts( [
					'topicid'   => $topic_id,
					'row_count' => 1,
					'orderby'   => 'created',
					'order'     => 'DESC',
				] );

				if ( empty( $last_post ) ) {
					// No replies, fall back to first post only
					$topic_data['posts'] = [ $first_post_content ];
					$topic_data['context_type'] = 'first_post_only';
					$topic_data['last_postid'] = $first_postid;
					$topic_data['reply_parentid'] = 0;
					$topic_data['reply_root'] = -1;
					break;
				}

				$last = $last_post[0];
				$last_postid = intval( $last['postid'] );
				$last_parentid = intval( $last['parentid'] );
				$last_root = intval( $last['root'] );

				// If the last post IS the first post, just use first post context
				if ( $last_postid === $first_postid ) {
					$topic_data['posts'] = [ $first_post_content ];
					$topic_data['context_type'] = 'first_post_only';
					$topic_data['last_postid'] = $first_postid;
					$topic_data['reply_parentid'] = 0;
					$topic_data['reply_root'] = -1;
					break;
				}

				// Build sub-thread context: first post + thread branch leading to last post
				$post_contents = [ $first_post_content ];

				// Find the sub-thread root (the top-level reply that started this thread)
				// root=-1 or root=0 means it's a top-level reply
				if ( $last_root <= 0 ) {
					// Last post is a top-level reply, use it directly
					$subthread_root_postid = $last_postid;
				} else {
					// Last post is nested, get its sub-thread root
					$subthread_root_postid = $last_root;
				}

				// Get posts in this sub-thread (from subthread root to last post)
				if ( $subthread_root_postid !== $first_postid ) {
					// Get the sub-thread root post
					$subthread_root_post = WPF()->post->get_post( $subthread_root_postid );
					if ( $subthread_root_post ) {
						$post_contents[] = [
							'postid'   => $subthread_root_postid,
							'author'   => WPF()->member->get_member( $subthread_root_post['userid'] )['display_name'] ?? 'Unknown',
							'content'  => wp_strip_all_tags( $subthread_root_post['body'] ),
							'parentid' => intval( $subthread_root_post['parentid'] ),
							'root'     => intval( $subthread_root_post['root'] ),
							'is_subthread_root' => true,
						];
					}

					// Get nested replies in this sub-thread (up to 20)
					$subthread_posts = WPF()->post->get_posts( [
						'topicid'   => $topic_id,
						'root'      => $subthread_root_postid,
						'row_count' => 20,
						'orderby'   => 'created',
						'order'     => 'ASC',
					] );
					foreach ( $subthread_posts as $post ) {
						$postid = intval( $post['postid'] );
						if ( $postid !== $subthread_root_postid ) {
							$post_contents[] = [
								'postid'   => $postid,
								'author'   => WPF()->member->get_member( $post['userid'] )['display_name'] ?? 'Unknown',
								'content'  => wp_strip_all_tags( $post['body'] ),
								'parentid' => intval( $post['parentid'] ),
								'root'     => intval( $post['root'] ),
							];
						}
					}
				}

				// Determine parentid and root for the AI reply
				// The reply should be nested under the last post
				if ( $last_root <= 0 ) {
					// Last post is a top-level reply, so AI reply's root is the last post
					$reply_parentid = $last_postid;
					$reply_root = $last_postid;
				} else {
					// Last post is already nested, AI reply's root is the sub-thread root
					$reply_parentid = $last_postid;
					$reply_root = $last_root;
				}

				$topic_data['posts'] = $post_contents;
				$topic_data['context_type'] = 'last_post_subthread';
				$topic_data['last_postid'] = $last_postid;
				$topic_data['reply_parentid'] = $reply_parentid;
				$topic_data['reply_root'] = $reply_root;
				break;

			default:
				// Fallback to first_post
				$topic_data['posts'] = [ $first_post_content ];
				$topic_data['context_type'] = 'first_post_only';
				break;
		}

		return $topic_data;
	}

	/**
	 * Build custom instructions string from task config
	 *
	 * Combines topic_theme (the main theme/focus), topic_tone, content_length,
	 * and include options into a comprehensive instruction string for the AI.
	 *
	 * @param array $config Task configuration
	 * @return string Custom instructions for the AI
	 */
	private function build_topic_custom_instructions( $config ) {
		$instructions = [];

		// Primary theme/focus (required) - truncate to 120 chars (multibyte-safe)
		$topic_theme = trim( $config['topic_theme'] ?? '' );
		if ( ! empty( $topic_theme ) ) {
			// Multibyte-safe truncation to prevent prompt injection via long inputs
			$topic_theme = mb_substr( $topic_theme, 0, 120, 'UTF-8' );
			$instructions[] = "Topic theme/focus: {$topic_theme}";
		}

		// Topic tone
		$topic_tone = $config['topic_tone'] ?? 'neutral';
		if ( $topic_tone !== 'neutral' ) {
			$tone_labels = [
				'professional'   => 'Professional - Business-like and formal',
				'friendly'       => 'Friendly - Warm and approachable',
				'casual'         => 'Casual - Relaxed and informal',
				'technical'      => 'Technical - Detailed and precise',
				'enthusiastic'   => 'Enthusiastic - Excited and energetic',
				'helpful'        => 'Helpful - Supportive and guiding',
				'authoritative'  => 'Authoritative - Expert and confident',
				'conversational' => 'Conversational - Like chatting with a friend',
				'educational'    => 'Educational - Teaching and informative',
				'inspirational'  => 'Inspirational - Motivating and uplifting',
				'humorous'       => 'Humorous - Light-hearted and witty',
				'serious'        => 'Serious - Focused and earnest',
				'encouraging'    => 'Encouraging - Positive and supportive',
			];
			$tone_desc = $tone_labels[ $topic_tone ] ?? $topic_tone;
			$instructions[] = "Tone: {$tone_desc}";
		}

		// Content length
		$content_length = $config['content_length'] ?? 'medium';
		$length_labels = [
			'brief'         => 'Brief (100-200 words)',
			'medium'        => 'Medium (200-400 words)',
			'detailed'      => 'Detailed (400-800 words)',
			'comprehensive' => 'Comprehensive (800-1200 words)',
		];
		$length_desc = $length_labels[ $content_length ] ?? $content_length;
		$instructions[] = "Content length: {$length_desc}";

		// Content inclusion options
		$include_options = [];
		if ( ! empty( $config['include_code'] ) ) {
			$include_options[] = 'code examples';
		}
		if ( ! empty( $config['include_links'] ) ) {
			$include_options[] = 'relevant external links';
		}
		if ( ! empty( $config['include_steps'] ) ) {
			$include_options[] = 'step-by-step instructions';
		}
		if ( ! empty( $config['include_youtube'] ) ) {
			// Note: YouTube placeholders are handled separately below
			// to ensure proper formatting on new lines
		}

		if ( ! empty( $include_options ) ) {
			$instructions[] = 'Include: ' . implode( ', ', $include_options );
		}

		// YouTube video placeholders (special handling for proper formatting)
		if ( ! empty( $config['include_youtube'] ) ) {
			$instructions[] = 'YOUTUBE VIDEO REQUIREMENT: When suggesting a YouTube video, DO NOT invent fake URLs. ' .
				'Instead, add a placeholder on a new line in this exact format: [YOUTUBE_SEARCH: descriptive search term]. ' .
				'Example: [YOUTUBE_SEARCH: AWS Bedrock tutorial for beginners]. ' .
				'The placeholder must be on its own line, not embedded in a sentence. ' .
				'Only suggest videos when they would genuinely add value to the content.';
		}

		// Web search placeholders (when links are requested)
		if ( ! empty( $config['include_links'] ) ) {
			$instructions[] = 'WEB SEARCH REQUIREMENT: When including external links or documentation references, add a placeholder in this exact format: [WEB_SEARCH: descriptive search term]. ' .
				'Example: [WEB_SEARCH: AWS Lambda best practices documentation]. ' .
				'The placeholder must be on its own line. Use this for documentation links, tutorials, or authoritative resources.';
		}

		// Code search placeholders (when code examples are requested)
		if ( ! empty( $config['include_code'] ) ) {
			$instructions[] = 'CODE SEARCH REQUIREMENT: When providing code examples for common programming tasks, you may add a placeholder in this exact format: [CODE_SEARCH: programming question]. ' .
				'Example: [CODE_SEARCH: python list comprehension with condition]. ' .
				'The placeholder must be on its own line. This will fetch real code snippets from Stack Overflow. Only use when it adds value.';
		}

		// Search keywords for inspiration (if set)
		$search_keywords = trim( $config['search_keywords'] ?? '' );
		if ( ! empty( $search_keywords ) ) {
			$instructions[] = "Related keywords for topic ideas: {$search_keywords}";
		}

		return implode( "\n", $instructions );
	}

	/**
	 * Build custom instructions for reply generation
	 *
	 * @param array $config Task configuration
	 * @return string Custom instructions for the AI
	 */
	private function build_reply_custom_instructions( $config ) {
		$instructions = [];

		// Response guidelines (primary instructions) - truncate to 120 chars (multibyte-safe)
		$response_guidelines = trim( $config['response_guidelines'] ?? '' );
		if ( ! empty( $response_guidelines ) ) {
			// Multibyte-safe truncation to prevent prompt injection via long inputs
			$response_guidelines = mb_substr( $response_guidelines, 0, 120, 'UTF-8' );
			$instructions[] = "Response Guidelines: {$response_guidelines}";
		}

		// Reply tone
		$reply_tone = $config['reply_tone'] ?? 'neutral';
		if ( $reply_tone !== 'neutral' ) {
			$tone_labels = [
				'professional'   => 'Professional - Business-like and formal',
				'friendly'       => 'Friendly - Warm and approachable',
				'casual'         => 'Casual - Relaxed and informal',
				'technical'      => 'Technical - Detailed and precise',
				'enthusiastic'   => 'Enthusiastic - Excited and energetic',
				'helpful'        => 'Helpful - Supportive and guiding',
				'authoritative'  => 'Authoritative - Expert and confident',
				'conversational' => 'Conversational - Like chatting with a friend',
				'educational'    => 'Educational - Teaching and informative',
				'inspirational'  => 'Inspirational - Motivating and uplifting',
				'humorous'       => 'Humorous - Light-hearted and witty',
				'serious'        => 'Serious - Focused and earnest',
				'encouraging'    => 'Encouraging - Positive and supportive',
				'warm'           => 'Warm - Caring and compassionate',
				'direct'         => 'Direct - Straightforward and to the point',
				'thoughtful'     => 'Thoughtful - Considerate and reflective',
				'empathetic'     => 'Empathetic - Understanding and relatable',
				'confident'      => 'Confident - Self-assured and decisive',
				'curious'        => 'Curious - Inquisitive and engaging',
				'playful'        => 'Playful - Fun and lighthearted',
				'sincere'        => 'Sincere - Genuine and honest',
			];
			$tone_desc = $tone_labels[ $reply_tone ] ?? $reply_tone;
			$instructions[] = "Tone: {$tone_desc}";
		}

		// Reply length
		$reply_length = $config['reply_length'] ?? 'medium';
		$length_labels = [
			'brief'    => 'Brief (100-200 words)',
			'medium'   => 'Medium (200-400 words)',
			'detailed' => 'Detailed (400-800 words)',
		];
		$length_desc = $length_labels[ $reply_length ] ?? $reply_length;
		$instructions[] = "Reply length: {$length_desc}";

		// Content inclusion options
		$include_options = [];
		if ( ! empty( $config['reply_include_code'] ) ) {
			$include_options[] = 'code examples';
		}
		if ( ! empty( $config['reply_include_links'] ) ) {
			$include_options[] = 'relevant links to documentation';
		}
		if ( ! empty( $config['reply_include_steps'] ) ) {
			$include_options[] = 'step-by-step solutions';
		}
		if ( ! empty( $config['reply_include_followup'] ) ) {
			$include_options[] = 'follow-up questions';
		}
		if ( ! empty( $config['reply_include_greeting'] ) ) {
			$include_options[] = 'personalized greeting (e.g., "Hi John")';
		}

		if ( ! empty( $include_options ) ) {
			$instructions[] = 'Include: ' . implode( ', ', $include_options );
		}

		// YouTube video placeholders (special handling for proper formatting)
		if ( ! empty( $config['reply_include_youtube'] ) ) {
			$instructions[] = 'YOUTUBE VIDEO REQUIREMENT: When suggesting a YouTube video, DO NOT invent fake URLs. ' .
				'Instead, add a placeholder on a new line in this exact format: [YOUTUBE_SEARCH: descriptive search term]. ' .
				'Example: [YOUTUBE_SEARCH: AWS Bedrock tutorial for beginners]. ' .
				'The placeholder must be on its own line, not embedded in a sentence. ' .
				'Only suggest videos when they would genuinely add value to the reply.';
		}

		// Knowledge source and fallback behavior
		$knowledge_source = $config['knowledge_source'] ?? 'forum_only';
		$no_content_action = $config['no_content_action'] ?? 'use_other_sources';
		$uses_web_search = in_array( $knowledge_source, [ 'forum_and_web', 'forum_and_web_and_ai' ], true );

		if ( $knowledge_source === 'forum_only' ) {
			$instructions[] = 'KNOWLEDGE SOURCE: Use only information from the forum context provided. Do not use general AI knowledge.';
		} elseif ( $knowledge_source === 'forum_and_ai' ) {
			$instructions[] = 'KNOWLEDGE SOURCE: Primarily use forum context, but supplement with general AI knowledge when helpful.';
		} elseif ( $knowledge_source === 'forum_and_web' ) {
			$instructions[] = 'KNOWLEDGE SOURCE: Use forum context and supplement with web search results. Do not use general AI knowledge.';
		} elseif ( $knowledge_source === 'forum_and_web_and_ai' ) {
			$instructions[] = 'KNOWLEDGE SOURCE: Use forum context, web search results, and general AI knowledge to provide comprehensive responses.';
		} else {
			$instructions[] = 'KNOWLEDGE SOURCE: Use your general AI knowledge to provide helpful responses.';
		}

		// Web search placeholders (when web search is enabled)
		if ( $uses_web_search || ! empty( $config['reply_include_links'] ) ) {
			$instructions[] = 'WEB SEARCH REQUIREMENT: When you need current information or external resources, add a placeholder in this exact format: [WEB_SEARCH: descriptive search term]. ' .
				'Example: [WEB_SEARCH: best practices for API rate limiting 2024]. ' .
				'The placeholder must be on its own line. Use this for documentation links, tutorials, or current information.';
		}

		// Code search placeholders (when code examples are enabled)
		if ( ! empty( $config['reply_include_code'] ) ) {
			$instructions[] = 'CODE SEARCH REQUIREMENT: When providing code examples for common programming questions, you may add a placeholder in this exact format: [CODE_SEARCH: programming question]. ' .
				'Example: [CODE_SEARCH: python async await example]. ' .
				'The placeholder must be on its own line. This will fetch real code snippets from Stack Overflow. Only use when relevant.';
		}

		// What to do when no relevant content is found in forum
		if ( $no_content_action === 'skip' ) {
			$instructions[] = 'NO FORUM CONTENT FALLBACK: If no relevant forum content is available, indicate that you cannot provide a helpful response and do not generate a reply.';
		} elseif ( $no_content_action === 'ask_details' ) {
			$instructions[] = 'NO FORUM CONTENT FALLBACK: If no relevant forum content is available, ask the user for more details about their question to better assist them.';
		} else {
			$instructions[] = 'NO FORUM CONTENT FALLBACK: If no relevant forum content is available, use other available knowledge sources (AI knowledge, web search if enabled) to provide a helpful response.';
		}

		return implode( "\n", $instructions );
	}

	/**
	 * Process YouTube placeholders in content
	 *
	 * Converts [YOUTUBE_SEARCH: search term] placeholders to actual YouTube search links.
	 * The links are placed on their own line so wpForo addons can replace them with embeds.
	 *
	 * @param string $content The content with placeholders
	 * @return string Content with YouTube search links
	 */
	private function process_youtube_placeholders( $content ) {
		// Pattern matches [YOUTUBE_SEARCH: any search term]
		$pattern = '/\[YOUTUBE_SEARCH:\s*([^\]]+)\]/i';

		return preg_replace_callback( $pattern, function( $matches ) {
			$search_term = trim( $matches[1] );
			if ( empty( $search_term ) ) {
				return '';
			}
			// Create YouTube search URL
			$search_url = 'https://www.youtube.com/results?search_query=' . rawurlencode( $search_term );
			// Return as plain URL on its own line (not wrapped in HTML tags)
			// This allows wpForo video addons to detect and convert to embed
			return "\n" . $search_url . "\n";
		}, $content );
	}

	/**
	 * Process web search placeholders in content
	 *
	 * Removes any unresolved [WEB_SEARCH: search term] placeholders.
	 * These should have been processed by the backend (Tavily), but may remain
	 * if the Tavily API key is not configured or the service is unavailable.
	 *
	 * @param string $content The content with placeholders
	 * @return string Content with placeholders removed
	 */
	private function process_web_search_placeholders( $content ) {
		$pattern = '/\[WEB_SEARCH:\s*[^\]]+\]/i';
		return preg_replace( $pattern, '', $content );
	}

	/**
	 * Process code search placeholders in content
	 *
	 * Removes any unresolved [CODE_SEARCH: search term] placeholders.
	 * These should have been processed by the backend (Stack Overflow), but may remain
	 * if the service is unavailable.
	 *
	 * @param string $content The content with placeholders
	 * @return string Content with placeholders removed
	 */
	private function process_code_search_placeholders( $content ) {
		$pattern = '/\[CODE_SEARCH:\s*[^\]]+\]/i';
		return preg_replace( $pattern, '', $content );
	}

	/**
	 * Append AI disclosure notice to content.
	 *
	 * Adds a small disclaimer line at the end of AI-generated content
	 * indicating the content was created by AI and may contain inaccuracies.
	 *
	 * @param string $content The content to append disclosure to
	 * @return string Content with AI disclosure notice
	 */
	private function append_ai_disclosure( $content ) {
		$disclosure = __( 'ℹ️ This content was generated by AI and may contain inaccuracies.', 'wpforo' );
		return $content . "\n\n" . $disclosure;
	}

	/**
	 * Create a forum topic from AI-generated content
	 *
	 * @param array $task       Task data
	 * @param array $topic_data Generated topic data
	 * @return bool True on success
	 */
	private function create_forum_topic( $task, $topic_data ) {
		$config = $task['config'];
		// Use author_userid (current name) with fallback to bot_user_id (legacy name)
		$author_user_id = intval( $config['author_userid'] ?? $config['bot_user_id'] ?? 0 );
		$author_groupid = intval( $config['author_groupid'] ?? 0 );

		// If usergroup is set and no specific user, pick random member from group
		if ( $author_groupid > 0 && $author_user_id <= 0 ) {
			$author_user_id = $this->resolve_author_from_group( $author_groupid );
		}

		$target_forums = $config['target_forums'] ?? [];

		// Select forum (random from targets if multiple)
		$forum_id = ! empty( $target_forums ) ? $target_forums[ array_rand( $target_forums ) ] : 0;
		if ( ! $forum_id ) {
			return false;
		}

		// Get forum details
		$forum = WPF()->forum->get_forum( $forum_id );
		if ( ! $forum ) {
			return false;
		}

		// Get topic status from config (0 = published/approved, 1 = unapproved)
		$topic_status = intval( $config['topic_status'] ?? 0 );

		// Handle topic prefix - either addon prefix ID or text prefix
		$topic_prefix_id = intval( $config['topic_prefix_id'] ?? 0 );
		$topic_prefix    = trim( $config['topic_prefix'] ?? '' );
		$title           = sanitize_text_field( $topic_data['title'] ?? 'AI Generated Topic' );

		// If wpForo Topic Prefix addon is active and prefix ID is set, we'll pass it to the topic data
		// Otherwise, prepend text prefix to title (legacy behavior)
		if ( empty( $topic_prefix_id ) && ! empty( $topic_prefix ) ) {
			$title = $topic_prefix . ' ' . $title;
		}

		// Merge auto tags with generated tags
		$auto_tags_str = $config['auto_tags'] ?? '';
		$auto_tags = array_filter( array_map( 'trim', explode( ',', $auto_tags_str ) ) );
		$generated_tags = $topic_data['tags'] ?? [];
		$all_tags = array_unique( array_merge( $generated_tags, $auto_tags ) );

		// Process content - convert/strip tool placeholders
		$content = $topic_data['content'] ?? '';
		$content = $this->process_youtube_placeholders( $content );
		$content = $this->process_web_search_placeholders( $content );
		$content = $this->process_code_search_placeholders( $content );

		// Add AI disclosure notice if enabled
		$show_ai_badge = ! empty( $config['show_ai_badge'] );
		if ( $show_ai_badge ) {
			$content = $this->append_ai_disclosure( $content );
		}

		// Prepare topic data
		$topic = [
			'forumid'         => $forum_id,
			'title'           => $title,
			'body'            => wp_kses_post( $content ),
			'userid'          => $author_user_id > 0 ? $author_user_id : get_current_user_id(),
			'status'          => $topic_status,
			'private'         => 0,
			'name'            => '',
			'email'           => '',
			'tags'            => $all_tags,
			'is_ai_generated' => true, // Skip all spam/moderation checks for AI-created content
		];

		// Add prefix ID if wpForo Topic Prefix addon is active and prefix ID is configured
		if ( $topic_prefix_id > 0 && function_exists( 'WPF_TOPIC_PREFIX' ) ) {
			$topic['prefix'] = $topic_prefix_id;
		}

		// In cron context, we need to set the current user for wpForo permission checks
		// Save the original user and switch to the author user
		$original_user_id = get_current_user_id();
		$target_user_id = $author_user_id > 0 ? $author_user_id : 1; // Fallback to admin (ID 1)

		// Set WordPress current user
		wp_set_current_user( $target_user_id );

		// Re-initialize wpForo's current user context
		if ( isset( WPF()->current_userid ) ) {
			WPF()->current_userid = $target_user_id;
		}

		// Add filter to enforce the intended topic status and userid from task config
		// This runs after auto_moderate (priority 10) to override its changes
		$enforce_topic_data = function( $args ) use ( $topic_status, $target_user_id ) {
			if ( ! empty( $args ) ) {
				$args['status'] = $topic_status;
				$args['userid'] = $target_user_id;
			}
			return $args;
		};
		add_filter( 'wpforo_add_topic_data_filter', $enforce_topic_data, 99 );

		// Create topic using wpForo API
		$result = WPF()->topic->add( $topic );

		$notices = WPF()->notice->get_notices();
		\wpforo_ai_log( 'debug', sprintf(
			'create_forum_topic: forum_id=%d, user_id=%d, result=%s, notices=%s',
			$forum_id,
			$target_user_id,
			wp_json_encode( $result ),
			wp_json_encode( $notices )
		), 'Task' );

		// Remove the temporary filter
		remove_filter( 'wpforo_add_topic_data_filter', $enforce_topic_data, 99 );

		// Restore the original user context
		wp_set_current_user( $original_user_id );
		if ( isset( WPF()->current_userid ) ) {
			WPF()->current_userid = $original_user_id;
		}

		return ! empty( $result );
	}

	/**
	 * Create a forum reply from AI-generated content
	 *
	 * @param array $task          Task data
	 * @param array $reply_data    Generated reply data
	 * @param array $topic_context Topic context with reply strategy info
	 * @return bool True on success
	 */
	private function create_forum_reply( $task, $reply_data, $topic_context = [] ) {
		$config = $task['config'];

		$topic_id = intval( $reply_data['topic_id'] ?? 0 );
		if ( ! $topic_id ) {
			return false;
		}

		// Get topic details
		$topic = WPF()->topic->get_topic( $topic_id );
		if ( ! $topic ) {
			return false;
		}

		// Resolve author: specific user, or random from usergroup (excluding topic creator)
		$author_user_id = intval( $config['author_userid'] ?? $config['bot_user_id'] ?? 0 );
		$author_groupid = intval( $config['author_groupid'] ?? 0 );
		if ( $author_groupid > 0 && $author_user_id <= 0 ) {
			$topic_creator_id = intval( $topic['userid'] ?? 0 );
			$author_user_id   = $this->resolve_author_from_group( $author_groupid, $topic_creator_id );
		}

		// Get reply status from config (0 = published/approved, 1 = unapproved)
		$reply_status = intval( $config['reply_status'] ?? 0 );

		// Determine parentid and root based on reply strategy
		$reply_strategy = $config['reply_strategy'] ?? 'first_post';
		$parentid = 0;
		$root     = -1;

		switch ( $reply_strategy ) {
			case 'first_post':
			case 'whole_topic':
				// Both strategies create a top-level reply (not nested under any post)
				// parentid=0 means not replying to any specific post
				// root=-1 means this is a top-level reply
				$parentid = 0;
				$root     = -1;
				break;

			case 'last_post':
				// Use parentid and root from topic context (calculated in build_topic_context_for_reply)
				// This nests the reply under the last post in the sub-thread
				if ( ! empty( $topic_context['reply_parentid'] ) || ! empty( $topic_context['reply_root'] ) ) {
					$parentid = intval( $topic_context['reply_parentid'] ?? 0 );
					$root     = intval( $topic_context['reply_root'] ?? -1 );
				}
				// If context not available, fall back to top-level reply
				break;

			default:
				// Unknown strategy, use top-level reply
				$parentid = 0;
				$root     = -1;
				break;
		}

		// Process content - convert/strip tool placeholders
		$content = $reply_data['content'] ?? '';
		$content = $this->process_youtube_placeholders( $content );
		$content = $this->process_web_search_placeholders( $content );
		$content = $this->process_code_search_placeholders( $content );

		// Add AI disclosure notice if enabled
		$show_ai_badge = ! empty( $config['show_ai_badge'] );
		if ( $show_ai_badge ) {
			$content = $this->append_ai_disclosure( $content );
		}

		// Prepare reply data
		$post = [
			'forumid'         => $topic['forumid'],
			'topicid'         => $topic_id,
			'parentid'        => $parentid,
			'root'            => $root,
			'body'            => wp_kses_post( $content ),
			'userid'          => $author_user_id > 0 ? $author_user_id : get_current_user_id(),
			'status'          => $reply_status,
			'private'         => 0,
			'name'            => '',
			'email'           => '',
			'is_ai_generated' => true, // Skip all spam/moderation checks for AI-created content
		];

		// In cron context, we need to set the current user for wpForo permission checks
		// Save the original user and switch to the author user
		$original_user_id = get_current_user_id();
		$target_user_id = $author_user_id > 0 ? $author_user_id : 1; // Fallback to admin (ID 1)

		// Set WordPress current user
		wp_set_current_user( $target_user_id );

		// Re-initialize wpForo's current user context
		if ( isset( WPF()->current_userid ) ) {
			WPF()->current_userid = $target_user_id;
		}

		// Add filter to enforce the intended reply status and userid from task config
		// This runs after auto_moderate (priority 10) to override its changes
		$enforce_post_data = function( $args ) use ( $reply_status, $target_user_id ) {
			if ( ! empty( $args ) ) {
				$args['status'] = $reply_status;
				$args['userid'] = $target_user_id;
			}
			return $args;
		};
		add_filter( 'wpforo_add_post_data_filter', $enforce_post_data, 99 );

		// Create reply using wpForo API
		$result = WPF()->post->add( $post );

		$notices = WPF()->notice->get_notices();
		\wpforo_ai_log( 'debug', sprintf(
			'create_forum_reply: topic_id=%d, user_id=%d, result=%s, notices=%s',
			$topic_id,
			$target_user_id,
			wp_json_encode( $result ),
			wp_json_encode( $notices )
		), 'Task' );

		// Remove the temporary filter
		remove_filter( 'wpforo_add_post_data_filter', $enforce_post_data, 99 );

		// Restore the original user context
		wp_set_current_user( $original_user_id );
		if ( isset( WPF()->current_userid ) ) {
			WPF()->current_userid = $original_user_id;
		}

		return ! empty( $result );
	}

	/**
	 * Resolve author user ID from a usergroup by random selection.
	 *
	 * @param int $groupid        Usergroup ID to pick a member from
	 * @param int $exclude_userid User ID to exclude (e.g., topic creator for replies)
	 * @return int Selected user ID, or 0 if no members found
	 */
	private function resolve_author_from_group( $groupid, $exclude_userid = 0 ) {
		$members = WPF()->member->get_members( [ 'groupid' => $groupid ] );
		if ( empty( $members ) ) {
			return 0;
		}

		// Filter out excluded user (e.g., topic creator for replies)
		$candidates = $members;
		if ( $exclude_userid > 0 ) {
			$candidates = array_filter( $members, function( $m ) use ( $exclude_userid ) {
				return intval( $m['userid'] ) !== $exclude_userid;
			} );
			// If filtering removed all candidates, fall back to full list
			if ( empty( $candidates ) ) {
				$candidates = $members;
			}
		}

		$candidates = array_values( $candidates );
		$random     = $candidates[ array_rand( $candidates ) ];

		return intval( $random['userid'] );
	}

	/**
	 * Call the tasks API endpoint
	 *
	 * Uses AIClient's api_post() method for consistent HTTP handling.
	 *
	 * @param string $endpoint API endpoint
	 * @param array  $data     Request data
	 * @param string $api_key  API key (kept for compatibility, AIClient handles auth)
	 * @return array|WP_Error Response or error
	 */
	private function call_tasks_api( $endpoint, $data, $api_key ) {
		$ai_client = $this->get_ai_client();
		if ( ! $ai_client ) {
			return new \WP_Error( 'no_ai_client', 'AI service not available' );
		}

		// Use AIClient's api_post() method with 60 second timeout for LLM generation
		return $ai_client->api_post( '/tasks/' . $endpoint, $data, 60 );
	}

	// =========================================================================
	// TASK STATISTICS & LOGGING
	// =========================================================================

	/**
	 * Check if credit threshold allows execution
	 *
	 * @param array $task Task data
	 * @return bool True if execution allowed
	 */
	private function check_credit_threshold( $task ) {
		$config = $task['config'];
		$max_daily = intval( $config['max_daily_credits'] ?? 0 );

		if ( $max_daily <= 0 ) {
			return true; // No limit set
		}

		// Get today's credit usage for this task
		global $wpdb;
		$logs_table = $this->get_logs_table();
		$today = date( 'Y-m-d' );

		$used_today = $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(credits_used), 0) FROM {$logs_table} WHERE task_id = %d AND DATE(execution_time) = %s",
			$task['task_id'],
			$today
		) );

		if ( intval( $used_today ) >= $max_daily ) {
			// Check if we should pause the task
			if ( ! empty( $config['pause_on_threshold'] ) ) {
				$this->update_task( $task['task_id'], [ 'status' => 'paused' ] );
			}
			return false;
		}

		return true;
	}

	/**
	 * Update task statistics after execution
	 *
	 * @param int $task_id       Task ID
	 * @param int $items_created Number of items created
	 * @param int $credits_used  Credits consumed
	 */
	private function update_task_statistics( $task_id, $items_created, $credits_used ) {
		global $wpdb;
		$table = $this->get_tasks_table();

		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET
				total_runs = total_runs + 1,
				items_created = items_created + %d,
				credits_used = credits_used + %d,
				last_run_time = %s
			WHERE task_id = %d",
			$items_created,
			$credits_used,
			current_time( 'mysql' ),
			$task_id
		) );
	}

	/**
	 * Log task execution
	 *
	 * @param int   $task_id Task ID
	 * @param array $data    Log data
	 * @return array Execution result
	 */
	private function log_execution( $task_id, $data ) {
		global $wpdb;
		$table = $this->get_logs_table();

		$log_data = [
			'task_id'            => intval( $task_id ),
			'execution_time'     => current_time( 'mysql' ),
			'status'             => sanitize_key( $data['status'] ?? 'unknown' ),
			'items_created'      => intval( $data['items_created'] ?? 0 ),
			'credits_used'       => intval( $data['credits_used'] ?? 0 ),
			'execution_duration' => floatval( $data['execution_duration'] ?? 0 ),
			'error_message'      => sanitize_text_field( $data['error_message'] ?? '' ),
			'result_data'        => ! empty( $data['result_data'] ) ? wp_json_encode( $data['result_data'] ) : null,
		];

		$wpdb->insert( $table, $log_data, [
			'%d', '%s', '%s', '%d', '%d', '%f', '%s', '%s'
		] );

		// Also log to central AI Logs for visibility in AI Logs tab
		$this->log_to_ai_logs( $task_id, $log_data, $data );

		return [
			'success'       => $data['status'] !== 'error',
			'log_id'        => $wpdb->insert_id,
			'items_created' => $log_data['items_created'],
			'credits_used'  => $log_data['credits_used'],
			'error'         => $log_data['error_message'],
		];
	}

	/**
	 * Log task execution to central AI Logs
	 *
	 * This ensures task executions appear in the wpForo > AI Features > AI Logs tab
	 * alongside other AI actions like searches, translations, etc.
	 *
	 * @param int   $task_id  Task ID
	 * @param array $log_data Processed log data
	 * @param array $raw_data Original raw data
	 */
	private function log_to_ai_logs( $task_id, $log_data, $raw_data ) {
		if ( ! isset( WPF()->ai_logs ) ) {
			return;
		}

		// Get task info for better log context
		$task = $this->get_task( $task_id );
		$task_name = $task['task_name'] ?? 'Unknown Task';
		$task_type = $task['task_type'] ?? 'unknown';

		// Map task status to AI Logs status
		$status_map = [
			'completed' => AILogs::STATUS_SUCCESS,
			'error'     => AILogs::STATUS_ERROR,
			'skipped'   => AILogs::STATUS_CACHED, // Use cached for skipped tasks
		];
		$ai_log_status = $status_map[ $log_data['status'] ] ?? AILogs::STATUS_SUCCESS;

		// Build request summary
		$request_summary = sprintf( '%s: %s', $this->get_task_type_label( $task_type ), $task_name );

		// Build response summary
		if ( $log_data['status'] === 'completed' ) {
			$response_summary = sprintf(
				'%d items created, %d credits used',
				$log_data['items_created'],
				$log_data['credits_used']
			);
		} elseif ( $log_data['status'] === 'skipped' ) {
			$response_summary = 'Skipped: ' . ( $log_data['error_message'] ?: 'threshold reached' );
		} else {
			$response_summary = 'Error: ' . ( $log_data['error_message'] ?: 'unknown error' );
		}

		// Log to central AI Logs
		WPF()->ai_logs->log( [
			'action_type'      => AILogs::ACTION_TASK_EXECUTION,
			'userid'           => 0, // Tasks run as system/cron
			'user_type'        => defined( 'DOING_CRON' ) && DOING_CRON ? AILogs::USER_TYPE_CRON : AILogs::USER_TYPE_SYSTEM,
			'credits_used'     => $log_data['credits_used'],
			'status'           => $ai_log_status,
			'content_type'     => 'task',
			'content_id'       => $task_id,
			'request_summary'  => $request_summary,
			'response_summary' => $response_summary,
			'error_message'    => $log_data['status'] === 'error' ? $log_data['error_message'] : null,
			'duration_ms'      => intval( $log_data['execution_duration'] * 1000 ),
			'extra_data'       => [
				'task_id'       => $task_id,
				'task_name'     => $task_name,
				'task_type'     => $task_type,
				'items_created' => $log_data['items_created'],
				'result_data'   => $raw_data['result_data'] ?? null,
			],
		] );
	}

	/**
	 * Get human-readable label for task type
	 *
	 * @param string $task_type Task type slug
	 * @return string Human-readable label
	 */
	private function get_task_type_label( $task_type ) {
		$labels = [
			'topic_generator'  => __( 'Topic Generator', 'wpforo' ),
			'reply_generator'  => __( 'Reply Generator', 'wpforo' ),
			'tag_maintenance'  => __( 'Tag Maintenance', 'wpforo' ),
		];
		return $labels[ $task_type ] ?? ucwords( str_replace( '_', ' ', $task_type ) );
	}

	/**
	 * Get task execution logs
	 *
	 * @param int   $task_id Task ID
	 * @param array $args    Query arguments
	 * @return array Logs list
	 */
	public function get_task_logs( $task_id, $args = [] ) {
		global $wpdb;

		$defaults = [
			'limit'  => 50,
			'offset' => 0,
		];

		$args = wp_parse_args( $args, $defaults );
		$table = $this->get_logs_table();

		$logs = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE task_id = %d ORDER BY execution_time DESC LIMIT %d OFFSET %d",
			intval( $task_id ),
			intval( $args['limit'] ),
			intval( $args['offset'] )
		), ARRAY_A );

		return $logs;
	}

	// =========================================================================
	// AJAX HANDLERS
	// =========================================================================

	/**
	 * AJAX handler to save a task
	 */
	public function ajax_save_task() {
		check_ajax_referer( 'wpforo_ai_task_nonce', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied' ] );
		}

		$task_id = isset( $_POST['task_id'] ) ? intval( $_POST['task_id'] ) : 0;

		$data = [
			'task_name' => sanitize_text_field( $_POST['task_name'] ?? '' ),
			'task_type' => sanitize_key( $_POST['task_type'] ?? '' ),
			'status'    => sanitize_key( $_POST['status'] ?? 'draft' ),
			'board_id'  => intval( $_POST['board_id'] ?? 0 ),
			'config'    => $_POST['config'] ?? '{}',
		];

		// Decode config if it's a JSON string
		if ( is_string( $data['config'] ) ) {
			$data['config'] = json_decode( stripslashes( $data['config'] ), true );
		}

		if ( $task_id > 0 ) {
			// Update existing task
			$result = $this->update_task( $task_id, $data );
			if ( $result ) {
				wp_send_json_success( [ 'message' => 'Task updated successfully', 'task_id' => $task_id ] );
			} else {
				wp_send_json_error( [ 'message' => 'Failed to update task' ] );
			}
		} else {
			// Create new task
			$new_id = $this->create_task( $data );
			if ( $new_id ) {
				wp_send_json_success( [ 'message' => 'Task created successfully', 'task_id' => $new_id ] );
			} else {
				wp_send_json_error( [ 'message' => 'Failed to create task' ] );
			}
		}
	}

	/**
	 * AJAX handler to get a task
	 */
	public function ajax_get_task() {
		check_ajax_referer( 'wpforo_ai_task_nonce', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied' ] );
		}

		$task_id = intval( $_POST['task_id'] ?? 0 );
		$task = $this->get_task( $task_id );

		if ( $task ) {
			wp_send_json_success( [ 'task' => $task ] );
		} else {
			wp_send_json_error( [ 'message' => 'Task not found' ] );
		}
	}

	/**
	 * AJAX handler to delete a task
	 */
	public function ajax_delete_task() {
		check_ajax_referer( 'wpforo_ai_task_nonce', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied' ] );
		}

		$task_id = intval( $_POST['task_id'] ?? 0 );
		$result = $this->delete_task( $task_id );

		if ( $result ) {
			wp_send_json_success( [ 'message' => 'Task deleted successfully' ] );
		} else {
			wp_send_json_error( [ 'message' => 'Failed to delete task' ] );
		}
	}

	/**
	 * AJAX handler to update task status
	 */
	public function ajax_update_task_status() {
		check_ajax_referer( 'wpforo_ai_task_nonce', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied' ] );
		}

		$task_id = intval( $_POST['task_id'] ?? 0 );
		$status = sanitize_key( $_POST['status'] ?? '' );

		if ( ! $task_id ) {
			wp_send_json_error( [ 'message' => 'Invalid task ID' ] );
		}

		if ( ! array_key_exists( $status, self::$statuses ) ) {
			wp_send_json_error( [ 'message' => 'Invalid status: ' . $status ] );
		}

		// Check if task exists
		$task = $this->get_task( $task_id );
		if ( ! $task ) {
			wp_send_json_error( [ 'message' => 'Task not found: ' . $task_id ] );
		}

		$result = $this->update_task( $task_id, [ 'status' => $status ] );

		if ( $result ) {
			wp_send_json_success( [ 'message' => 'Task status updated', 'status' => $status ] );
		} else {
			global $wpdb;
			wp_send_json_error( [ 'message' => 'Failed to update status. DB error: ' . $wpdb->last_error ] );
		}
	}

	/**
	 * AJAX handler to run a task immediately
	 */
	public function ajax_run_task() {
		check_ajax_referer( 'wpforo_ai_task_nonce', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied' ] );
		}

		$task_id = intval( $_POST['task_id'] ?? 0 );
		$result = $this->execute_task( $task_id );

		if ( $result['success'] ) {
			wp_send_json_success( [
				'message'       => 'Task executed successfully',
				'items_created' => $result['items_created'] ?? 0,
				'credits_used'  => $result['credits_used'] ?? 0,
			] );
		} else {
			wp_send_json_error( [ 'message' => $result['error'] ?? 'Task execution failed' ] );
		}
	}

	/**
	 * AJAX handler for bulk actions
	 */
	public function ajax_bulk_action() {
		check_ajax_referer( 'wpforo_ai_task_nonce', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied' ] );
		}

		$action = sanitize_key( $_POST['bulk_action'] ?? '' );
		$task_ids = array_map( 'intval', $_POST['task_ids'] ?? [] );

		if ( empty( $task_ids ) ) {
			wp_send_json_error( [ 'message' => 'No tasks selected' ] );
		}

		$count = 0;
		foreach ( $task_ids as $task_id ) {
			switch ( $action ) {
				case 'delete':
					if ( $this->delete_task( $task_id ) ) {
						$count++;
					}
					break;
				case 'activate':
					if ( $this->update_task( $task_id, [ 'status' => 'active' ] ) ) {
						$count++;
					}
					break;
				case 'pause':
					if ( $this->update_task( $task_id, [ 'status' => 'paused' ] ) ) {
						$count++;
					}
					break;
			}
		}

		wp_send_json_success( [ 'message' => sprintf( '%d task(s) updated', $count ) ] );
	}

	/**
	 * AJAX handler to get task logs
	 */
	public function ajax_get_task_logs() {
		check_ajax_referer( 'wpforo_ai_task_nonce', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied' ] );
		}

		$task_id = intval( $_POST['task_id'] ?? 0 );
		$limit = intval( $_POST['limit'] ?? 50 );
		$offset = intval( $_POST['offset'] ?? 0 );

		$logs = $this->get_task_logs( $task_id, [
			'limit'  => $limit,
			'offset' => $offset,
		] );

		// Format execution_time in user's timezone
		foreach ( $logs as &$log ) {
			if ( ! empty( $log->execution_time ) ) {
				$log->execution_time = wpforo_ai_format_datetime( $log->execution_time );
			}
		}
		unset( $log );

		wp_send_json_success( [ 'logs' => $logs ] );
	}

	/**
	 * AJAX handler to search users for author selection
	 * Only returns activated users (empty user_activation_key)
	 */
	public function ajax_search_users() {
		check_ajax_referer( 'wpforo_ai_task_nonce', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied' ] );
		}

		$search = sanitize_text_field( $_POST['search'] ?? '' );
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
		$like = '%' . $wpdb->esc_like( $search ) . '%';
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

		$results = [];
		foreach ( $users as $user ) {
			$user_obj = get_userdata( $user->ID );
			$role = $user_obj && ! empty( $user_obj->roles ) ? ucfirst( $user_obj->roles[0] ) : '';
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
	 * AJAX handler to duplicate a task
	 */
	public function ajax_duplicate_task() {
		check_ajax_referer( 'wpforo_ai_task_nonce', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied' ] );
		}

		$task_id = intval( $_POST['task_id'] ?? 0 );

		// Get the original task
		$original = $this->get_task( $task_id );
		if ( ! $original ) {
			wp_send_json_error( [ 'message' => 'Task not found' ] );
		}

		// Create a copy with modified name and paused status
		$new_data = [
			'task_name' => $original['task_name'] . ' (Copy)',
			'task_type' => $original['task_type'],
			'status'    => 'paused',
			'board_id'  => $original['board_id'],
			'config'    => $original['config'],
		];

		$new_task_id = $this->create_task( $new_data );

		if ( $new_task_id ) {
			wp_send_json_success( [
				'message' => 'Task duplicated successfully',
				'task_id' => $new_task_id,
			] );
		} else {
			wp_send_json_error( [ 'message' => 'Failed to duplicate task' ] );
		}
	}

	/**
	 * AJAX handler to get task statistics
	 */
	public function ajax_get_task_stats() {
		check_ajax_referer( 'wpforo_ai_task_nonce', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied' ] );
		}

		$task_id = intval( $_POST['task_id'] ?? 0 );

		// Get the task
		$task = $this->get_task( $task_id );
		if ( ! $task ) {
			wp_send_json_error( [ 'message' => 'Task not found' ] );
		}

		// Get logs to calculate statistics
		global $wpdb;
		$logs_table = $this->get_logs_table();

		// Total runs and success/error counts
		$run_stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_runs,
					SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful_runs,
					SUM(items_created) as items_created,
					SUM(credits_used) as credits_used
				FROM {$logs_table}
				WHERE task_id = %d",
				$task_id
			)
		);

		$total_runs = intval( $run_stats->total_runs ?? 0 );
		$successful_runs = intval( $run_stats->successful_runs ?? 0 );
		$items_created = intval( $run_stats->items_created ?? 0 );
		$credits_used = intval( $run_stats->credits_used ?? 0 );

		// Calculate success rate
		$success_rate = $total_runs > 0 ? round( ( $successful_runs / $total_runs ) * 100 ) . '%' : '0%';

		// Calculate average items per run
		$avg_items = $total_runs > 0 ? round( $items_created / $total_runs, 1 ) : 0;

		// Format last run time
		$last_run = ! empty( $task['last_run_time'] ) ? human_time_diff( strtotime( $task['last_run_time'] ), current_time( 'timestamp' ) ) . ' ago' : 'Never';

		// Get next scheduled run
		$next_run = 'Not scheduled';
		if ( $task['status'] === 'active' ) {
			$next_scheduled = wp_next_scheduled( 'wpforo_ai_execute_task', [ $task_id ] );
			if ( $next_scheduled ) {
				$next_run = human_time_diff( current_time( 'timestamp' ), $next_scheduled ) . ' from now';
			}
		} elseif ( ! empty( $task['next_run_time'] ) ) {
			// Show stored next run time even if paused (convert to user timezone)
			$next_run = wpforo_ai_format_datetime( $task['next_run_time'] ) . ' (paused)';
		}

		wp_send_json_success( [
			'stats' => [
				'total_runs'       => $total_runs,
				'items_created'    => $items_created,
				'credits_used'     => $credits_used,
				'success_rate'     => $success_rate,
				'last_run'         => $last_run,
				'next_run'         => $next_run,
				'avg_items_per_run' => $avg_items,
			],
		] );
	}

	/**
	 * Get local RAG contexts for topics
	 *
	 * When using local storage mode, performs semantic search for each topic
	 * and formats the results as RAG context to be sent to the Lambda API.
	 * This allows the Reply Generator to use forum context even in local storage mode.
	 *
	 * @param array $topics Array of topic data with topic_id and title
	 * @return array Associative array of topic_id => rag_context_string
	 */
	private function get_local_rag_contexts( $topics ) {
		$rag_contexts = [];

		if ( ! WPF()->vector_storage ) {
			return $rag_contexts;
		}

		foreach ( $topics as $topic ) {
			$topic_id = intval( $topic['topic_id'] ?? 0 );
			$title = $topic['title'] ?? '';
			$first_post_content = '';

			// Get first post content for better context query
			if ( ! empty( $topic['posts'] ) && is_array( $topic['posts'] ) ) {
				$first_post_content = $topic['posts'][0]['content'] ?? '';
			} elseif ( ! empty( $topic['content'] ) ) {
				$first_post_content = $topic['content'];
			}

			// Build search query from title + first post content
			$search_query = $title;
			if ( $first_post_content ) {
				$search_query .= ' ' . mb_substr( wp_strip_all_tags( $first_post_content ), 0, 200 );
			}

			if ( empty( $search_query ) ) {
				continue;
			}

			// Perform semantic search
			$results = WPF()->vector_storage->semantic_search( $search_query, 3 );

			if ( is_wp_error( $results ) || empty( $results['results'] ) ) {
				continue;
			}

			// Format results as RAG context (same format as Lambda _get_rag_context)
			$context_parts = [];
			foreach ( $results['results'] as $result ) {
				// Get topic title from result
				$result_title = '';
				if ( ! empty( $result['topic_id'] ) ) {
					// Local format - fetch topic title
					$result_topic = WPF()->topic->get_topic( intval( $result['topic_id'] ) );
					$result_title = $result_topic['title'] ?? '';
				} elseif ( ! empty( $result['metadata']['topic_title'] ) ) {
					// Cloud format
					$result_title = $result['metadata']['topic_title'];
				}

				// Get excerpt
				$excerpt = '';
				if ( ! empty( $result['content'] ) ) {
					// Local format - use content_preview
					$excerpt = wp_strip_all_tags( $result['content'] );
				} elseif ( ! empty( $result['excerpt'] ) ) {
					// Cloud format
					$excerpt = wp_strip_all_tags( $result['excerpt'] );
				}
				$excerpt = mb_substr( $excerpt, 0, 300 );

				if ( $result_title || $excerpt ) {
					$context_parts[] = "- {$result_title}: {$excerpt}";
				}
			}

			if ( ! empty( $context_parts ) ) {
				$rag_contexts[ strval( $topic_id ) ] = "Relevant existing content:\n" . implode( "\n", $context_parts );
			}
		}

		return $rag_contexts;
	}

	/**
	 * Handle topic creation for run_on_approval tasks
	 *
	 * Triggers Tag Generator tasks with run_on_approval enabled when topic is created with status=0
	 *
	 * @param array $topic Topic data
	 * @param array $forum Forum data
	 */
	public function on_topic_created( $topic, $forum ) {
		// Only trigger for approved topics (status = 0)
		// Unapproved topics will trigger via wpforo_topic_approve hook later
		if ( empty( $topic['topicid'] ) || intval( $topic['status'] ?? 1 ) !== 0 ) {
			return;
		}

		$this->trigger_tag_maintenance_for_topic( $topic );
	}

	/**
	 * Handle topic approval for run_on_approval tasks
	 *
	 * Triggers Tag Generator tasks with run_on_approval enabled
	 *
	 * @param array $topic Topic data
	 */
	public function on_topic_approved( $topic ) {
		if ( empty( $topic['topicid'] ) ) {
			return;
		}

		$this->trigger_tag_maintenance_for_topic( $topic );
	}

	/**
	 * Trigger tag maintenance tasks for a topic
	 *
	 * @param array $topic Topic data
	 */
	private function trigger_tag_maintenance_for_topic( $topic ) {
		$topicid = intval( $topic['topicid'] );
		$forumid = intval( $topic['forumid'] ?? 0 );

		// Get active tag_maintenance tasks with run_on_approval enabled
		$tasks = $this->get_run_on_approval_tasks( 'tag_maintenance', $forumid );

		foreach ( $tasks as $task ) {
			// Schedule task execution for this specific topic
			$this->execute_task_for_topic( $task, $topicid );
		}
	}

	/**
	 * Handle post creation for run_on_approval tasks
	 *
	 * Triggers Reply Generator tasks with run_on_approval enabled when post is created with status=0
	 *
	 * @param array $post Post data
	 * @param array $topic Topic data
	 * @param array $forum Forum data
	 */
	public function on_post_created( $post, $topic, $forum ) {
		// Only trigger for approved posts (status = 0)
		// Unapproved posts will trigger via wpforo_post_approve hook later
		if ( empty( $post['postid'] ) || intval( $post['status'] ?? 1 ) !== 0 ) {
			return;
		}

		// Skip first posts (topics themselves) - we only want replies
		if ( ! empty( $post['is_first_post'] ) ) {
			return;
		}

		$this->trigger_reply_generator_for_post( $post );
	}

	/**
	 * Handle post approval for run_on_approval tasks
	 *
	 * Triggers Reply Generator tasks with run_on_approval enabled
	 * Note: Only triggers for non-first posts (replies, not topics)
	 *
	 * @param array $post Post data
	 */
	public function on_post_approved( $post ) {
		if ( empty( $post['postid'] ) || empty( $post['topicid'] ) ) {
			return;
		}

		// Skip first posts (topics themselves) - we only want replies
		// is_first_post = 1 means this is the first post in a topic (the topic itself)
		if ( ! empty( $post['is_first_post'] ) ) {
			return;
		}

		$this->trigger_reply_generator_for_post( $post );
	}

	/**
	 * Trigger reply generator tasks for a post
	 *
	 * @param array $post Post data
	 */
	private function trigger_reply_generator_for_post( $post ) {
		$topicid = intval( $post['topicid'] );
		$forumid = intval( $post['forumid'] ?? 0 );

		// Get active reply_generator tasks with run_on_approval enabled
		$tasks = $this->get_run_on_approval_tasks( 'reply_generator', $forumid );

		foreach ( $tasks as $task ) {
			// Execute task to generate a reply to this topic
			$this->execute_task_for_topic( $task, $topicid );
		}
	}

	/**
	 * Get active tasks with run_on_approval enabled for a specific task type
	 *
	 * @param string $task_type Task type (reply_generator, tag_maintenance)
	 * @param int $forum_id Forum ID to check target forums
	 * @return array Array of tasks
	 */
	private function get_run_on_approval_tasks( $task_type, $forum_id ) {
		global $wpdb;
		$table = $this->get_tasks_table();

		// Get all active tasks of this type
		$tasks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE task_type = %s AND status = 'active'",
				$task_type
			),
			ARRAY_A
		);

		$matching_tasks = [];

		foreach ( $tasks as $task ) {
			$config = ! empty( $task['config'] ) ? json_decode( $task['config'], true ) : [];

			// Check if run_on_approval is enabled
			if ( empty( $config['run_on_approval'] ) ) {
				continue;
			}

			// Check if forum is in target forums (if forum filtering is configured)
			$target_forums = [];
			if ( $task_type === 'reply_generator' ) {
				$target_forums = $config['reply_target_forums'] ?? [];
			} elseif ( $task_type === 'tag_maintenance' ) {
				$target_forums = $config['tag_target_forum_ids'] ?? [];
			}

			// If target forums are specified, check if this forum is included
			if ( ! empty( $target_forums ) && $forum_id > 0 ) {
				if ( ! in_array( $forum_id, array_map( 'intval', $target_forums ) ) ) {
					continue;
				}
			}

			$task['config'] = $config;
			$matching_tasks[] = $task;
		}

		return $matching_tasks;
	}

	/**
	 * Execute a task for a specific topic (for run_on_approval tasks)
	 *
	 * @param array $task Task data with parsed config
	 * @param int $topic_id Topic ID to process
	 * @return array Execution result
	 */
	private function execute_task_for_topic( $task, $topic_id ) {
		$task_id = intval( $task['task_id'] );
		$task_type = $task['task_type'];
		$config = $task['config'];

		// Check credit threshold
		if ( ! $this->check_credit_threshold( $task ) ) {
			return [
				'success' => false,
				'error' => 'Credit threshold reached',
			];
		}

		$start_time = microtime( true );
		$result = [];

		switch ( $task_type ) {
			case 'reply_generator':
				// Create a modified config to target only this topic
				$config['target_topic_ids'] = strval( $topic_id );
				$config['replies_per_run'] = 1;
				$task['config'] = $config;
				$result = $this->execute_reply_generator( $task );
				break;

			case 'tag_maintenance':
				// Create a modified config to target only this topic
				$config['target_topic_ids'] = strval( $topic_id );
				$config['topics_per_run'] = 1;
				$task['config'] = $config;
				$result = $this->execute_tag_maintenance( $task );
				break;

			default:
				return [ 'success' => false, 'error' => 'Unsupported task type for run_on_approval' ];
		}

		// Calculate execution time
		$execution_time = microtime( true ) - $start_time;

		// Log the execution
		return $this->log_execution( $task_id, [
			'status'             => $result['success'] ? 'completed' : 'error',
			'items_created'      => $result['items_created'] ?? 0,
			'credits_used'       => $result['credits_used'] ?? 0,
			'execution_duration' => $execution_time,
			'error_message'      => $result['error'] ?? null,
			'result_data'        => array_merge( $result['data'] ?? [], [ 'trigger' => 'on_approval', 'topic_id' => $topic_id ] ),
		] );
	}
}
