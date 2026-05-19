<?php

namespace wpforo\classes;

// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

class EmailQueue {
	const BATCH_SIZE        = 20;
	const MAX_ATTEMPTS      = 3;
	const CRON_HOOK         = 'wpforo_process_email_queue';
	const CLEANUP_HOOK      = 'wpforo_cleanup_email_queue';
	const CRON_INTERVAL     = 30; // seconds
	const STALE_THRESHOLD   = 120; // 2 minutes - consider cron stalled
	const CLEANUP_DAYS      = 7;
	const PROCESSING_LOCK   = 'wpforo_email_queue_processing';
	const LOCK_TIMEOUT      = 300; // 5 minutes
	const SELF_HEAL_LOCK    = 'wpforo_email_queue_self_heal_check';
	const SELF_HEAL_THROTTLE = 300; // 5 minutes between auto self-heal attempts

	private $table;
	private $table_exists = null;
	private $last_mail_error = '';

	public function __construct() {
		$this->table = WPF()->db->prefix . 'wpforo_email_queue';
		$this->init_hooks();
	}

	private function init_hooks() {
		add_action( self::CRON_HOOK, [ $this, 'process_batch' ] );
		add_action( self::CLEANUP_HOOK, [ $this, 'cleanup_old' ] );
		add_action( 'wp_mail_failed', [ $this, 'capture_mail_error' ] );

		// Auto self-heal for sites where WP-Cron is silently broken (page-cache
		// stripping cron triggers, very low traffic, etc). Runs after WordPress
		// finishes rendering the response, throttled to at most once per
		// SELF_HEAL_THROTTLE per site. No-op on healthy sites.
		add_action( 'shutdown', [ $this, 'maybe_self_heal' ], 100 );
	}

	/**
	 * Capture mail errors from wp_mail_failed action
	 * Works with native wp_mail and all SMTP plugins
	 */
	public function capture_mail_error( $wp_error ): void {
		if( is_wp_error( $wp_error ) ) {
			$this->last_mail_error = $wp_error->get_error_message();
		}
	}

	/**
	 * Check if the email_queue table exists
	 */
	private function table_exists(): bool {
		if( $this->table_exists === null ) {
			$this->table_exists = (bool) WPF()->db->get_var(
				WPF()->db->prepare( "SHOW TABLES LIKE %s", $this->table )
			);
		}
		return $this->table_exists;
	}

	/**
	 * Contexts that should always be sent synchronously (user expects immediate delivery)
	 */
	private function get_sync_contexts(): array {
		return apply_filters( 'wpforo_email_sync_contexts', [
			'password_reset',
			'subscription_confirm',
			'welcome',
			'moderation_alert',
		] );
	}

	/**
	 * Check if WP Cron is healthy and capable of processing the queue
	 */
	public function is_cron_healthy(): bool {
		if( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			return false;
		}

		$last_run = (int) get_option( 'wpforo_email_queue_last_run', 0 );
		$next_scheduled = wp_next_scheduled( self::CRON_HOOK );

		if( $next_scheduled && $next_scheduled < ( time() - self::STALE_THRESHOLD * 5 ) ) {
			if( $this->get_pending_count() > 0 ) {
				return false;
			}
		}

		if( $last_run > 0 && $last_run < ( time() - 600 ) ) {
			if( $this->get_pending_count() > 0 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get cron status for admin display
	 */
	public function get_cron_status(): array {
		$last_run = (int) get_option( 'wpforo_email_queue_last_run', 0 );
		$next_scheduled = wp_next_scheduled( self::CRON_HOOK );
		$pending = $this->get_pending_count();
		$is_healthy = $this->is_cron_healthy();

		if( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$status = 'disabled';
			$message = __( 'WP Cron is disabled - emails sent synchronously', 'wpforo' );
		} elseif( ! $is_healthy ) {
			$status = 'stalled';
			$message = __( 'Cron appears stalled - emails sent synchronously', 'wpforo' );
		} elseif( $last_run > ( time() - 300 ) ) {
			$status = 'healthy';
			$message = __( 'Cron is running normally', 'wpforo' );
		} elseif( $pending === 0 ) {
			$status = 'idle';
			$message = __( 'Cron idle - no emails to send', 'wpforo' );
		} else {
			$status = 'unknown';
			$message = __( 'Cron status unknown - no recent activity', 'wpforo' );
		}

		return [
			'status'         => $status,
			'message'        => $message,
			'is_healthy'     => $is_healthy,
			'last_run'       => $last_run,
			'last_run_human' => $last_run ? human_time_diff( $last_run ) . ' ' . __( 'ago', 'wpforo' ) : __( 'Never', 'wpforo' ),
			'next_scheduled' => $next_scheduled,
			'pending_count'  => $pending,
		];
	}

	/**
	 * Queue an email or send synchronously based on context and cron health
	 */
	public function queue_or_send( string $email, string $subject, string $message, string $headers = '', string $context = 'general', int $related_id = 0 ): bool {
		if( in_array( $context, $this->get_sync_contexts(), true ) ) {
			return $this->send_sync( $email, $subject, $message, $headers );
		}

		if( ! wpforo_setting( 'email', 'async_notifications' ) ) {
			return $this->send_sync( $email, $subject, $message, $headers );
		}

		if( ! $this->is_cron_healthy() ) {
			$this->log_cron_fallback();
			return $this->send_sync( $email, $subject, $message, $headers );
		}

		return $this->queue( $email, $subject, $message, $headers, $context, $related_id );
	}

	/**
	 * Add email to the queue
	 */
	public function queue( string $email, string $subject, string $message, string $headers = '', string $context = 'general', int $related_id = 0 ): bool {
		if( ! $this->table_exists() ) {
			return $this->send_sync( $email, $subject, $message, $headers );
		}

		$now = current_time( 'mysql', true );
		$boardid = 0;
		if( isset( WPF()->board ) && method_exists( WPF()->board, 'get_current' ) ) {
			$boardid = (int) WPF()->board->get_current( 'boardid' );
		}

		$result = WPF()->db->insert(
			$this->table,
			[
				'email'        => $email,
				'subject'      => $subject,
				'message'      => $message,
				'headers'      => $headers,
				'priority'     => 10,
				'status'       => 'pending',
				'attempts'     => 0,
				'max_attempts' => self::MAX_ATTEMPTS,
				'created_at'   => $now,
				'scheduled_at' => $now,
				'context'      => $context,
				'related_id'   => $related_id,
				'boardid'      => $boardid,
			],
			[ '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d' ]
		);

		if( $result ) {
			$this->ensure_cron_scheduled();
			return true;
		}

		return $this->send_sync( $email, $subject, $message, $headers );
	}

	/**
	 * Send email synchronously (fallback)
	 */
	public function send_sync( string $email, string $subject, string $message, string $headers = '' ): bool {
		if( defined( 'IS_GO2WPFORO' ) && IS_GO2WPFORO ) return false;

		add_filter( 'wp_mail_content_type', 'wpforo_set_html_content_type', 999 );
		$result = wp_mail( $email, $subject, $message, $headers ?: wpforo_mail_headers() );
		remove_filter( 'wp_mail_content_type', 'wpforo_set_html_content_type' );

		return $result;
	}

	/**
	 * Process a batch of pending emails
	 */
	public function process_batch(): int {
		if( ! $this->acquire_lock() ) {
			return 0;
		}

		$processed = 0;

		try {
			$emails = $this->get_pending_emails( self::BATCH_SIZE );

			if( empty( $emails ) ) {
				$this->release_lock();
				return 0;
			}

			$ids = wp_list_pluck( $emails, 'id' );
			$this->mark_as_processing( $ids );

			foreach( $emails as $email_row ) {
				$success = $this->send_queued_email( $email_row );

				if( $success ) {
					$this->mark_as_sent( $email_row['id'] );
					$processed++;
				} else {
					$this->handle_failed_email( $email_row );
				}
			}

			update_option( 'wpforo_email_queue_last_run', time() );

			if( $this->get_pending_count() > 0 ) {
				$this->ensure_cron_scheduled();
			}

		} finally {
			$this->release_lock();
		}

		return $processed;
	}

	/**
	 * Get pending emails ready to be sent
	 */
	private function get_pending_emails( int $limit ): array {
		if( ! $this->table_exists() ) return [];

		$now = current_time( 'mysql', true );

		$results = WPF()->db->get_results(
			WPF()->db->prepare(
				"SELECT * FROM `{$this->table}`
				WHERE `status` = 'pending'
				AND (`scheduled_at` <= %s OR `scheduled_at` IS NULL)
				AND (`next_retry_at` IS NULL OR `next_retry_at` <= %s)
				ORDER BY `priority` ASC, `created_at` ASC
				LIMIT %d",
				$now,
				$now,
				$limit
			),
			ARRAY_A
		);

		return $results ?: [];
	}

	/**
	 * Send a single queued email
	 */
	private function send_queued_email( array $email_row ): bool {
		$this->last_mail_error = '';

		add_filter( 'wp_mail_content_type', 'wpforo_set_html_content_type', 999 );
		$result = wp_mail(
			$email_row['email'],
			$email_row['subject'],
			$email_row['message'],
			$email_row['headers'] ?: wpforo_mail_headers()
		);
		remove_filter( 'wp_mail_content_type', 'wpforo_set_html_content_type' );

		return $result;
	}

	/**
	 * Mark emails as processing
	 */
	private function mark_as_processing( array $ids ): void {
		if( empty( $ids ) ) return;

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		WPF()->db->query(
			WPF()->db->prepare(
				"UPDATE `{$this->table}` SET `status` = 'processing' WHERE `id` IN ($placeholders)",
				...$ids
			)
		);
	}

	/**
	 * Mark email as sent
	 */
	private function mark_as_sent( int $id ): void {
		WPF()->db->update(
			$this->table,
			[
				'status'       => 'sent',
				'processed_at' => current_time( 'mysql', true ),
			],
			[ 'id' => $id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Handle failed email - retry or mark as failed
	 */
	private function handle_failed_email( array $email_row ): void {
		$attempts = (int) $email_row['attempts'] + 1;
		$max_attempts = (int) $email_row['max_attempts'];

		if( $attempts >= $max_attempts ) {
			WPF()->db->update(
				$this->table,
				[
					'status'        => 'failed',
					'attempts'      => $attempts,
					'processed_at'  => current_time( 'mysql', true ),
					'error_message' => $this->get_last_mail_error(),
				],
				[ 'id' => $email_row['id'] ],
				[ '%s', '%d', '%s', '%s' ],
				[ '%d' ]
			);
		} else {
			$delay = pow( 2, $attempts ) * 60;
			$next_retry = gmdate( 'Y-m-d H:i:s', time() + $delay );

			WPF()->db->update(
				$this->table,
				[
					'status'        => 'pending',
					'attempts'      => $attempts,
					'next_retry_at' => $next_retry,
					'error_message' => $this->get_last_mail_error(),
				],
				[ 'id' => $email_row['id'] ],
				[ '%s', '%d', '%s', '%s' ],
				[ '%d' ]
			);
		}
	}

	/**
	 * Get last wp_mail error if available
	 * Uses wp_mail_failed hook (works with all SMTP plugins)
	 */
	private function get_last_mail_error(): string {
		if( ! empty( $this->last_mail_error ) ) {
			$error = substr( $this->last_mail_error, 0, 500 );
			$this->last_mail_error = '';
			return $error;
		}
		return __( 'Unknown error', 'wpforo' );
	}

	/**
	 * Ensure cron job is scheduled
	 */
	public function ensure_cron_scheduled(): void {
		if( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + self::CRON_INTERVAL, self::CRON_HOOK );
		}
	}

	/**
	 * Self-heal stalled queue when admin visits the page
	 */
	public function maybe_process_stalled_queue(): int {
		$next_scheduled = wp_next_scheduled( self::CRON_HOOK );

		if( $next_scheduled && $next_scheduled < ( time() - self::STALE_THRESHOLD ) ) {
			if( $this->get_pending_count() > 0 ) {
				return $this->process_batch();
			}
		}

		return 0;
	}

	/**
	 * Auto self-heal hooked to `shutdown` on every regular page request.
	 *
	 * Bridges the gap when WP-Cron is silently broken — page-cache plugins
	 * stripping cron triggers, very low traffic, or `DISABLE_WP_CRON` set
	 * without an external trigger. The check is heavily gated so it costs
	 * a single transient read on healthy sites:
	 *
	 * 1. Skips cron / AJAX / REST / XML-RPC / CLI — those are not user
	 *    requests and self-healing on them would either be wasted or
	 *    interfere with their normal flow.
	 * 2. Throttled by transient — at most one attempt per site per
	 *    SELF_HEAL_THROTTLE seconds.
	 * 3. The actual processing is delegated to maybe_process_stalled_queue()
	 *    which itself is a no-op unless the next scheduled cron is overdue
	 *    AND pending emails exist.
	 */
	public function maybe_self_heal(): void {
		if( ! $this->table_exists() )                       return;
		if( wp_doing_cron() )                               return;
		if( wp_doing_ajax() )                               return;
		if( defined( 'WP_CLI' )       && WP_CLI )           return;
		if( defined( 'REST_REQUEST' ) && REST_REQUEST )     return;
		if( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) return;

		if( get_transient( self::SELF_HEAL_LOCK ) ) return;
		set_transient( self::SELF_HEAL_LOCK, 1, self::SELF_HEAL_THROTTLE );

		$this->maybe_process_stalled_queue();
	}

	/**
	 * Clean up old sent emails
	 */
	public function cleanup_old(): int {
		if( ! $this->table_exists() ) return 0;

		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-' . self::CLEANUP_DAYS . ' days' ) );

		$deleted = WPF()->db->query(
			WPF()->db->prepare(
				"DELETE FROM `{$this->table}` WHERE `status` = 'sent' AND `processed_at` < %s",
				$cutoff
			)
		);

		return (int) $deleted;
	}

	/**
	 * Get count of pending emails
	 */
	public function get_pending_count(): int {
		if( ! $this->table_exists() ) return 0;
		return (int) WPF()->db->get_var(
			"SELECT COUNT(*) FROM `{$this->table}` WHERE `status` IN ('pending', 'processing')"
		);
	}

	/**
	 * Get count of failed emails
	 */
	public function get_failed_count(): int {
		if( ! $this->table_exists() ) return 0;
		return (int) WPF()->db->get_var(
			"SELECT COUNT(*) FROM `{$this->table}` WHERE `status` = 'failed'"
		);
	}

	/**
	 * Get count of sent emails (today)
	 */
	public function get_sent_today_count(): int {
		if( ! $this->table_exists() ) return 0;
		$today = gmdate( 'Y-m-d 00:00:00' );
		return (int) WPF()->db->get_var(
			WPF()->db->prepare(
				"SELECT COUNT(*) FROM `{$this->table}` WHERE `status` = 'sent' AND `processed_at` >= %s",
				$today
			)
		);
	}

	/**
	 * Get total sent count
	 */
	public function get_total_sent_count(): int {
		if( ! $this->table_exists() ) return 0;
		return (int) WPF()->db->get_var(
			"SELECT COUNT(*) FROM `{$this->table}` WHERE `status` = 'sent'"
		);
	}

	/**
	 * Get queue statistics
	 */
	public function get_stats(): array {
		return [
			'pending'    => $this->get_pending_count(),
			'failed'     => $this->get_failed_count(),
			'sent_today' => $this->get_sent_today_count(),
			'sent_total' => $this->get_total_sent_count(),
			'cron'       => $this->get_cron_status(),
		];
	}

	/**
	 * Get paginated queue items for admin display
	 */
	public function get_queue_items( string $status = 'pending', int $page = 1, int $per_page = 20 ): array {
		if( ! $this->table_exists() ) {
			return [ 'items' => [], 'total' => 0, 'page' => $page, 'per_page' => $per_page, 'total_pages' => 0 ];
		}

		$offset = ( $page - 1 ) * $per_page;

		$items = WPF()->db->get_results(
			WPF()->db->prepare(
				"SELECT `id`, `email`, `subject`, `context`, `status`, `attempts`, `max_attempts`,
				        `created_at`, `processed_at`, `error_message`, `related_id`, `boardid`
				FROM `{$this->table}`
				WHERE `status` = %s
				ORDER BY `created_at` DESC
				LIMIT %d OFFSET %d",
				$status,
				$per_page,
				$offset
			),
			ARRAY_A
		);

		$total = (int) WPF()->db->get_var(
			WPF()->db->prepare(
				"SELECT COUNT(*) FROM `{$this->table}` WHERE `status` = %s",
				$status
			)
		);

		return [
			'items'      => $items ?: [],
			'total'      => $total,
			'page'       => $page,
			'per_page'   => $per_page,
			'total_pages'=> ceil( $total / $per_page ),
		];
	}

	/**
	 * Retry failed emails
	 */
	public function retry_failed( array $ids ): int {
		if( empty( $ids ) || ! $this->table_exists() ) return 0;

		$ids = array_map( 'intval', $ids );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$updated = WPF()->db->query(
			WPF()->db->prepare(
				"UPDATE `{$this->table}`
				SET `status` = 'pending', `attempts` = 0, `next_retry_at` = NULL, `error_message` = NULL
				WHERE `id` IN ($placeholders) AND `status` = 'failed'",
				...$ids
			)
		);

		if( $updated ) {
			$this->ensure_cron_scheduled();
		}

		return (int) $updated;
	}

	/**
	 * Delete queue items
	 */
	public function delete_items( array $ids ): int {
		if( empty( $ids ) || ! $this->table_exists() ) return 0;

		$ids = array_map( 'intval', $ids );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		return (int) WPF()->db->query(
			WPF()->db->prepare(
				"DELETE FROM `{$this->table}` WHERE `id` IN ($placeholders)",
				...$ids
			)
		);
	}

	/**
	 * Clear all sent emails
	 */
	public function clear_sent_history(): int {
		if( ! $this->table_exists() ) return 0;
		return (int) WPF()->db->query(
			"DELETE FROM `{$this->table}` WHERE `status` = 'sent'"
		);
	}

	/**
	 * Acquire processing lock to prevent concurrent batch processing
	 */
	private function acquire_lock(): bool {
		$lock = get_transient( self::PROCESSING_LOCK );
		if( $lock ) {
			return false;
		}
		set_transient( self::PROCESSING_LOCK, time(), self::LOCK_TIMEOUT );
		return true;
	}

	/**
	 * Release processing lock
	 */
	private function release_lock(): void {
		delete_transient( self::PROCESSING_LOCK );
	}

	/**
	 * Log when falling back to sync due to cron issues
	 */
	private function log_cron_fallback(): void {
		$count = (int) get_option( 'wpforo_email_queue_sync_fallback_count', 0 );
		update_option( 'wpforo_email_queue_sync_fallback_count', $count + 1 );
	}

	/**
	 * Schedule daily cleanup cron
	 */
	public function schedule_cleanup_cron(): void {
		if( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( strtotime( 'tomorrow 3:00am' ), 'daily', self::CLEANUP_HOOK );
		}
	}

	/**
	 * Reset processing status for stuck emails (called on activation/upgrade)
	 */
	public function reset_stuck_processing(): void {
		if( ! $this->table_exists() ) return;
		WPF()->db->query(
			"UPDATE `{$this->table}` SET `status` = 'pending' WHERE `status` = 'processing'"
		);
	}
}
