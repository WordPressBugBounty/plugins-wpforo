<?php
// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;
if( ! current_user_can( 'administrator' ) ) exit;

// Handle AJAX actions
if( isset( WPF()->email_queue ) && wpfval( $_POST, 'wpforo_email_queue_action' ) && wp_verify_nonce( wpfval( $_POST, '_wpnonce' ), 'wpforo_email_queue' ) ) {
	$action = sanitize_text_field( $_POST['wpforo_email_queue_action'] );
	$ids = isset( $_POST['email_ids'] ) ? array_map( 'intval', (array) $_POST['email_ids'] ) : [];

	switch( $action ) {
		case 'retry':
			if( ! empty( $ids ) ) {
				$count = WPF()->email_queue->retry_failed( $ids );
				WPF()->notice->add( sprintf( __( '%d email(s) queued for retry', 'wpforo' ), $count ), 'success' );
			}
			break;

		case 'delete':
			if( ! empty( $ids ) ) {
				$count = WPF()->email_queue->delete_items( $ids );
				WPF()->notice->add( sprintf( __( '%d email(s) deleted', 'wpforo' ), $count ), 'success' );
			}
			break;

		case 'process_now':
			$processed = WPF()->email_queue->process_batch();
			WPF()->notice->add( sprintf( __( '%d email(s) processed', 'wpforo' ), $processed ), 'success' );
			break;

		case 'clear_history':
			$deleted = WPF()->email_queue->clear_sent_history();
			WPF()->notice->add( sprintf( __( '%d sent email(s) cleared from history', 'wpforo' ), $deleted ), 'success' );
			break;

		case 'retry_all':
			$failed_items = WPF()->email_queue->get_queue_items( 'failed', 1, 1000 );
			if( ! empty( $failed_items['items'] ) ) {
				$ids = wp_list_pluck( $failed_items['items'], 'id' );
				$count = WPF()->email_queue->retry_failed( $ids );
				WPF()->notice->add( sprintf( __( '%d failed email(s) queued for retry', 'wpforo' ), $count ), 'success' );
			}
			break;
	}
}

// Self-heal check - process stalled queue if needed
$self_healed = 0;
if( isset( WPF()->email_queue ) ) {
	$self_healed = WPF()->email_queue->maybe_process_stalled_queue();
	if( $self_healed > 0 ) {
		WPF()->notice->add( sprintf( __( 'Self-healed: processed %d stalled email(s)', 'wpforo' ), $self_healed ), 'success' );
	}
}

// Get current data
$stats = isset( WPF()->email_queue ) ? WPF()->email_queue->get_stats() : [];
$view = sanitize_text_field( wpfval( $_GET, 'view' ) ?: 'pending' );
$page = max( 1, intval( wpfval( $_GET, 'paged' ) ?: 1 ) );
$per_page = 20;

$queue_data = isset( WPF()->email_queue ) ? WPF()->email_queue->get_queue_items( $view, $page, $per_page ) : [ 'items' => [], 'total' => 0, 'total_pages' => 0 ];

$cron_status = $stats['cron'] ?? [];
?>

<style>
.wpf-email-queue-stats {
	display: flex;
	gap: 15px;
	margin-bottom: 20px;
	flex-wrap: wrap;
}
.wpf-email-queue-stat {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 15px 25px;
	min-width: 120px;
	text-align: center;
}
.wpf-email-queue-stat-value {
	font-size: 28px;
	font-weight: 600;
	line-height: 1.2;
}
.wpf-email-queue-stat-label {
	font-size: 12px;
	color: #666;
	text-transform: uppercase;
	margin-top: 5px;
}
.wpf-email-queue-stat.pending .wpf-email-queue-stat-value { color: #0073aa; }
.wpf-email-queue-stat.failed .wpf-email-queue-stat-value { color: #dc3232; }
.wpf-email-queue-stat.sent .wpf-email-queue-stat-value { color: #46b450; }

.wpf-cron-status {
	padding: 10px 15px;
	border-radius: 4px;
	margin-bottom: 20px;
	display: flex;
	align-items: center;
	gap: 10px;
}
.wpf-cron-status.healthy { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
.wpf-cron-status.disabled { background: #fff3cd; border: 1px solid #ffc107; color: #856404; }
.wpf-cron-status.stalled { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
.wpf-cron-status.unknown { background: #e2e3e5; border: 1px solid #d6d8db; color: #383d41; }

.wpf-email-queue-views {
	display: flex;
	gap: 10px;
	margin-bottom: 15px;
}
.wpf-email-queue-views a {
	text-decoration: none;
	padding: 5px 15px;
	background: #f1f1f1;
	border-radius: 4px;
}
.wpf-email-queue-views a.current {
	background: #0073aa;
	color: #fff;
}
.wpf-email-queue-views a span {
	font-weight: 600;
}

.wpf-email-queue-table {
	width: 100%;
	border-collapse: collapse;
	background: #fff;
	border: 1px solid #ccd0d4;
}
.wpf-email-queue-table th,
.wpf-email-queue-table td {
	padding: 10px 12px;
	text-align: left;
	border-bottom: 1px solid #ccd0d4;
}
.wpf-email-queue-table th {
	background: #f9f9f9;
	font-weight: 600;
}
.wpf-email-queue-table tr:hover {
	background: #f7f7f7;
}
.wpf-email-queue-table .email-subject {
	max-width: 300px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.wpf-email-queue-table .email-error {
	color: #dc3232;
	font-size: 12px;
	max-width: 200px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.wpf-email-queue-table .status-badge {
	display: inline-block;
	padding: 2px 8px;
	border-radius: 3px;
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
}
.wpf-email-queue-table .status-badge.pending { background: #e5f3ff; color: #0073aa; }
.wpf-email-queue-table .status-badge.processing { background: #fff8e5; color: #b26200; }
.wpf-email-queue-table .status-badge.sent { background: #e5f5e7; color: #1e7e34; }
.wpf-email-queue-table .status-badge.failed { background: #fce4e4; color: #c0392b; }

.wpf-email-queue-actions {
	display: flex;
	gap: 10px;
	margin-bottom: 15px;
	flex-wrap: wrap;
}
.wpf-email-queue-pagination {
	display: flex;
	gap: 5px;
	align-items: center;
	margin-top: 15px;
}
.wpf-email-queue-pagination a {
	padding: 5px 10px;
	background: #f1f1f1;
	text-decoration: none;
	border-radius: 3px;
}
.wpf-email-queue-pagination a.current {
	background: #0073aa;
	color: #fff;
}
.wpf-email-queue-empty {
	text-align: center;
	padding: 40px;
	color: #666;
	background: #f9f9f9;
	border-radius: 4px;
}
</style>

<div class="wpf-tool-box" style="margin-top: 15px;">
	<h3><?php _e( 'Email Queue', 'wpforo' ); ?></h3>

	<?php WPF()->notice->show(); ?>

	<?php if( ! isset( WPF()->email_queue ) ): ?>
		<p style="color: #dc3232;"><?php _e( 'Email Queue is not initialized.', 'wpforo' ); ?></p>
	<?php else: ?>

		<!-- Cron Status -->
		<div class="wpf-cron-status <?php echo esc_attr( $cron_status['status'] ?? 'unknown' ); ?>">
			<?php if( ( $cron_status['status'] ?? '' ) === 'healthy' ): ?>
				<span class="dashicons dashicons-yes-alt"></span>
			<?php elseif( ( $cron_status['status'] ?? '' ) === 'stalled' ): ?>
				<span class="dashicons dashicons-warning"></span>
			<?php elseif( ( $cron_status['status'] ?? '' ) === 'disabled' ): ?>
				<span class="dashicons dashicons-info"></span>
			<?php else: ?>
				<span class="dashicons dashicons-editor-help"></span>
			<?php endif; ?>
			<span>
				<strong><?php _e( 'Cron Status:', 'wpforo' ); ?></strong>
				<?php echo esc_html( $cron_status['message'] ?? __( 'Unknown', 'wpforo' ) ); ?>
				<?php if( ! empty( $cron_status['last_run_human'] ) ): ?>
					&nbsp;|&nbsp; <?php _e( 'Last run:', 'wpforo' ); ?> <?php echo esc_html( $cron_status['last_run_human'] ); ?>
				<?php endif; ?>
			</span>
		</div>

		<!-- Statistics -->
		<div class="wpf-email-queue-stats">
			<div class="wpf-email-queue-stat pending">
				<div class="wpf-email-queue-stat-value"><?php echo intval( $stats['pending'] ?? 0 ); ?></div>
				<div class="wpf-email-queue-stat-label"><?php _e( 'Pending', 'wpforo' ); ?></div>
			</div>
			<div class="wpf-email-queue-stat failed">
				<div class="wpf-email-queue-stat-value"><?php echo intval( $stats['failed'] ?? 0 ); ?></div>
				<div class="wpf-email-queue-stat-label"><?php _e( 'Failed', 'wpforo' ); ?></div>
			</div>
			<div class="wpf-email-queue-stat sent">
				<div class="wpf-email-queue-stat-value"><?php echo intval( $stats['sent_today'] ?? 0 ); ?></div>
				<div class="wpf-email-queue-stat-label"><?php _e( 'Sent Today', 'wpforo' ); ?></div>
			</div>
			<div class="wpf-email-queue-stat sent">
				<div class="wpf-email-queue-stat-value"><?php echo intval( $stats['sent_total'] ?? 0 ); ?></div>
				<div class="wpf-email-queue-stat-label"><?php _e( 'Total Sent', 'wpforo' ); ?></div>
			</div>
		</div>

		<!-- View Tabs -->
		<?php
		$base_url = admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'tools' ) . '&tab=email_queue' );
		$views = [
			'pending' => [ 'label' => __( 'Pending', 'wpforo' ), 'count' => $stats['pending'] ?? 0 ],
			'failed'  => [ 'label' => __( 'Failed', 'wpforo' ), 'count' => $stats['failed'] ?? 0 ],
			'sent'    => [ 'label' => __( 'Sent', 'wpforo' ), 'count' => $stats['sent_total'] ?? 0 ],
		];
		?>
		<div class="wpf-email-queue-views">
			<?php foreach( $views as $view_key => $view_data ): ?>
				<a href="<?php echo esc_url( add_query_arg( 'view', $view_key, $base_url ) ); ?>"
				   class="<?php echo $view === $view_key ? 'current' : ''; ?>">
					<?php echo esc_html( $view_data['label'] ); ?> <span>(<?php echo intval( $view_data['count'] ); ?>)</span>
				</a>
			<?php endforeach; ?>
		</div>

		<!-- Bulk Actions -->
		<form method="POST" id="wpf-email-queue-form">
			<?php wp_nonce_field( 'wpforo_email_queue' ); ?>

			<div class="wpf-email-queue-actions">
				<?php if( $view === 'pending' && ( $stats['pending'] ?? 0 ) > 0 ): ?>
					<button type="submit" name="wpforo_email_queue_action" value="process_now" class="button button-primary">
						<span class="dashicons dashicons-controls-play" style="vertical-align: middle; margin-top: -2px;"></span>
						<?php _e( 'Process Now', 'wpforo' ); ?>
					</button>
				<?php endif; ?>

				<?php if( $view === 'failed' && ( $stats['failed'] ?? 0 ) > 0 ): ?>
					<button type="submit" name="wpforo_email_queue_action" value="retry_all" class="button">
						<span class="dashicons dashicons-update" style="vertical-align: middle; margin-top: -2px;"></span>
						<?php _e( 'Retry All Failed', 'wpforo' ); ?>
					</button>
				<?php endif; ?>

				<?php if( $view === 'sent' && ( $stats['sent_total'] ?? 0 ) > 0 ): ?>
					<button type="submit" name="wpforo_email_queue_action" value="clear_history" class="button"
					        onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to clear all sent email history?', 'wpforo' ); ?>');">
						<span class="dashicons dashicons-trash" style="vertical-align: middle; margin-top: -2px;"></span>
						<?php _e( 'Clear History', 'wpforo' ); ?>
					</button>
				<?php endif; ?>

				<?php if( ! empty( $queue_data['items'] ) && $view !== 'sent' ): ?>
					<button type="submit" name="wpforo_email_queue_action" value="<?php echo $view === 'failed' ? 'retry' : 'delete'; ?>" class="button">
						<?php if( $view === 'failed' ): ?>
							<span class="dashicons dashicons-update" style="vertical-align: middle; margin-top: -2px;"></span>
							<?php _e( 'Retry Selected', 'wpforo' ); ?>
						<?php else: ?>
							<span class="dashicons dashicons-trash" style="vertical-align: middle; margin-top: -2px;"></span>
							<?php _e( 'Delete Selected', 'wpforo' ); ?>
						<?php endif; ?>
					</button>
				<?php endif; ?>
			</div>

			<!-- Email List -->
			<?php if( empty( $queue_data['items'] ) ): ?>
				<div class="wpf-email-queue-empty">
					<?php
					switch( $view ) {
						case 'pending':
							_e( 'No pending emails in the queue.', 'wpforo' );
							break;
						case 'failed':
							_e( 'No failed emails.', 'wpforo' );
							break;
						case 'sent':
							_e( 'No sent emails in history.', 'wpforo' );
							break;
					}
					?>
				</div>
			<?php else: ?>
				<table class="wpf-email-queue-table">
					<thead>
					<tr>
						<?php if( $view !== 'sent' ): ?>
							<th style="width: 30px;"><input type="checkbox" id="wpf-select-all-emails"></th>
						<?php endif; ?>
						<th><?php _e( 'Email', 'wpforo' ); ?></th>
						<th><?php _e( 'Subject', 'wpforo' ); ?></th>
						<th><?php _e( 'Context', 'wpforo' ); ?></th>
						<th><?php _e( 'Status', 'wpforo' ); ?></th>
						<?php if( $view === 'failed' ): ?>
							<th><?php _e( 'Attempts', 'wpforo' ); ?></th>
							<th><?php _e( 'Error', 'wpforo' ); ?></th>
						<?php endif; ?>
						<th><?php _e( 'Created', 'wpforo' ); ?></th>
						<?php if( $view === 'sent' ): ?>
							<th><?php _e( 'Sent', 'wpforo' ); ?></th>
						<?php endif; ?>
					</tr>
					</thead>
					<tbody>
					<?php foreach( $queue_data['items'] as $item ): ?>
						<tr>
							<?php if( $view !== 'sent' ): ?>
								<td><input type="checkbox" name="email_ids[]" value="<?php echo intval( $item['id'] ); ?>"></td>
							<?php endif; ?>
							<td><?php echo esc_html( $item['email'] ); ?></td>
							<td class="email-subject" title="<?php echo esc_attr( $item['subject'] ); ?>">
								<?php echo esc_html( $item['subject'] ); ?>
							</td>
							<td><?php echo esc_html( $item['context'] ); ?></td>
							<td><span class="status-badge <?php echo esc_attr( $item['status'] ); ?>"><?php echo esc_html( $item['status'] ); ?></span></td>
							<?php if( $view === 'failed' ): ?>
								<td><?php echo intval( $item['attempts'] ); ?>/<?php echo intval( $item['max_attempts'] ); ?></td>
								<td class="email-error" title="<?php echo esc_attr( $item['error_message'] ); ?>">
									<?php echo esc_html( $item['error_message'] ); ?>
								</td>
							<?php endif; ?>
							<td><?php echo esc_html( $item['created_at'] ); ?></td>
							<?php if( $view === 'sent' ): ?>
								<td><?php echo esc_html( $item['processed_at'] ); ?></td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<!-- Pagination -->
				<?php if( $queue_data['total_pages'] > 1 ): ?>
					<div class="wpf-email-queue-pagination">
						<?php for( $i = 1; $i <= $queue_data['total_pages']; $i++ ): ?>
							<a href="<?php echo esc_url( add_query_arg( [ 'view' => $view, 'paged' => $i ], $base_url ) ); ?>"
							   class="<?php echo $page === $i ? 'current' : ''; ?>">
								<?php echo $i; ?>
							</a>
						<?php endfor; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</form>

		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$('#wpf-select-all-emails').on('change', function() {
					$('input[name="email_ids[]"]').prop('checked', $(this).prop('checked'));
				});
			});
		</script>

	<?php endif; ?>
</div>
