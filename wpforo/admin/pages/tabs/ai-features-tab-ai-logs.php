<?php
/**
 * AI Logs Tab
 *
 * Displays AI action logs with filtering, search, and management capabilities.
 *
 * @since 3.0.0
 */

use wpforo\classes\AILogs;

/**
 * Render the AI Logs tab
 *
 * @param bool  $is_connected Whether the AI service is connected
 * @param array $status       Tenant status data
 */
function wpforo_ai_render_ai_logs_tab( $is_connected, $status ) {
	if ( ! $is_connected ) {
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Please connect to AI service to view logs.', 'wpforo' ) . '</p></div>';
		return;
	}

	// Get all boards and determine current board
	$boards          = WPF()->board->get_boards();
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
		// Find current board title
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
					$board_id    = isset( $board['boardid'] ) ? (int) $board['boardid'] : 0;
					$board_label = isset( $board['title'] ) ? esc_html( $board['title'] ) : __( 'Board', 'wpforo' ) . ' ' . $board_id;
					$tab_url     = add_query_arg( [
						'page'    => 'wpforo-ai',
						'tab'     => 'ai_logs',
						'boardid' => $board_id,
					], admin_url( 'admin.php' ) );
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

	// Get action types for filter dropdown
	$action_types = AILogs::get_action_types();

	// Get per page setting
	$per_page = WPF()->ai_logs->get_per_page();

	// Get initial logs
	$initial_logs = WPF()->ai_logs->get_logs( [
		'limit'   => $per_page,
		'offset'  => 0,
		'orderby' => 'created',
		'order'   => 'DESC',
	] );
	$total_logs   = WPF()->ai_logs->get_logs_count( [] );

	// Create nonce for AJAX
	$nonce = wp_create_nonce( 'wpforo_ai_logs_nonce' );
	?>

	<div class="wpforo-ai-logs-tab" id="wpforo-ai-logs-tab" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-boardid="<?php echo esc_attr( $current_boardid ); ?>">

		<!-- Header Section -->
		<div class="wpforo-ai-box wpforo-ai-logs-header-box">
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-logs-header">
					<div class="wpforo-ai-logs-info">
						<h2>
							<span class="dashicons dashicons-list-view"></span>
							<?php esc_html_e( 'AI Activity Logs', 'wpforo' ); ?>
							<span class="wpforo-ai-logs-count" id="wpforo-ai-logs-total-count">(<?php echo number_format( $total_logs ); ?>)</span>
						</h2>
						<p class="wpforo-ai-description">
							<?php esc_html_e( 'View all AI actions performed on your forum including searches, translations, content generation, and more.', 'wpforo' ); ?>
						</p>
					</div>
					<div class="wpforo-ai-logs-actions">
						<button type="button" class="button button-secondary wpforo-ai-empty-logs-btn" id="wpforo-ai-empty-logs-btn">
							<span class="dashicons dashicons-trash"></span>
							<?php esc_html_e( 'Empty All Logs', 'wpforo' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Filters Section -->
		<div class="wpforo-ai-box wpforo-ai-logs-filters-box">
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-logs-filters">
					<!-- Action Type Filter -->
					<div class="wpforo-ai-filter-group">
						<label for="wpforo-ai-logs-filter-action"><?php esc_html_e( 'Action Type', 'wpforo' ); ?></label>
						<select id="wpforo-ai-logs-filter-action" class="wpforo-ai-logs-filter">
							<option value=""><?php esc_html_e( 'All Actions', 'wpforo' ); ?></option>
							<?php foreach ( $action_types as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<!-- Date Filter -->
					<div class="wpforo-ai-filter-group">
						<label for="wpforo-ai-logs-filter-date"><?php esc_html_e( 'Date Range', 'wpforo' ); ?></label>
						<select id="wpforo-ai-logs-filter-date" class="wpforo-ai-logs-filter">
							<option value="all"><?php esc_html_e( 'All Time', 'wpforo' ); ?></option>
							<option value="last_hour"><?php esc_html_e( 'Last Hour', 'wpforo' ); ?></option>
							<option value="last_day"><?php esc_html_e( 'Last 24 Hours', 'wpforo' ); ?></option>
							<option value="last_week"><?php esc_html_e( 'Last 7 Days', 'wpforo' ); ?></option>
							<option value="last_month"><?php esc_html_e( 'Last 30 Days', 'wpforo' ); ?></option>
						</select>
					</div>

					<!-- Status Filter -->
					<div class="wpforo-ai-filter-group">
						<label for="wpforo-ai-logs-filter-status"><?php esc_html_e( 'Status', 'wpforo' ); ?></label>
						<select id="wpforo-ai-logs-filter-status" class="wpforo-ai-logs-filter">
							<option value=""><?php esc_html_e( 'All Status', 'wpforo' ); ?></option>
							<option value="success"><?php esc_html_e( 'Success', 'wpforo' ); ?></option>
							<option value="error"><?php esc_html_e( 'Error', 'wpforo' ); ?></option>
							<option value="cached"><?php esc_html_e( 'Cached', 'wpforo' ); ?></option>
						</select>
					</div>

					<!-- User Type Filter -->
					<div class="wpforo-ai-filter-group">
						<label for="wpforo-ai-logs-filter-user-type"><?php esc_html_e( 'Triggered By', 'wpforo' ); ?></label>
						<select id="wpforo-ai-logs-filter-user-type" class="wpforo-ai-logs-filter">
							<option value=""><?php esc_html_e( 'All', 'wpforo' ); ?></option>
							<option value="user"><?php esc_html_e( 'User', 'wpforo' ); ?></option>
							<option value="guest"><?php esc_html_e( 'Guest', 'wpforo' ); ?></option>
							<option value="cron"><?php esc_html_e( 'Cron Job', 'wpforo' ); ?></option>
							<option value="system"><?php esc_html_e( 'System', 'wpforo' ); ?></option>
						</select>
					</div>

					<!-- Search -->
					<div class="wpforo-ai-filter-group wpforo-ai-filter-search">
						<label for="wpforo-ai-logs-search"><?php esc_html_e( 'Search', 'wpforo' ); ?></label>
						<input type="text" id="wpforo-ai-logs-search" class="wpforo-ai-logs-filter"
						       placeholder="<?php esc_attr_e( 'Search logs...', 'wpforo' ); ?>">
					</div>

					<!-- Filter Button -->
					<div class="wpforo-ai-filter-group wpforo-ai-filter-button">
						<button type="button" class="button button-primary" id="wpforo-ai-logs-apply-filter">
							<span class="dashicons dashicons-search"></span>
							<?php esc_html_e( 'Apply', 'wpforo' ); ?>
						</button>
						<?php if ( isset( WPF()->ai_client ) && WPF()->ai_client->is_feature_available( 'ai_assistant_chatbot' ) ) : ?>
						<button type="button" class="button button-primary" id="wpforo-ai-logs-chat-messages-btn">
							<span class="dashicons dashicons-format-chat"></span>
							<?php esc_html_e( 'AI ChatBot Messages', 'wpforo' ); ?>
						</button>
						<?php endif; ?>
						<button type="button" class="button button-secondary" id="wpforo-ai-logs-reset-filter">
							<?php esc_html_e( 'Reset', 'wpforo' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Logs Table Section -->
		<div class="wpforo-ai-box wpforo-ai-logs-table-box">
			<div class="wpforo-ai-box-header">
				<h3>
					<?php esc_html_e( 'Activity Logs', 'wpforo' ); ?>
					<span class="wpforo-ai-logs-showing" id="wpforo-ai-logs-showing"></span>
				</h3>
				<div class="wpforo-ai-logs-bulk-actions">
					<select id="wpforo-ai-logs-bulk-action" class="wpforo-ai-bulk-action-select">
						<option value=""><?php esc_html_e( 'Bulk Actions', 'wpforo' ); ?></option>
						<option value="delete"><?php esc_html_e( 'Delete Selected', 'wpforo' ); ?></option>
					</select>
					<button type="button" class="button wpforo-ai-bulk-apply" id="wpforo-ai-logs-bulk-apply">
						<?php esc_html_e( 'Apply', 'wpforo' ); ?>
					</button>
				</div>
			</div>
			<div class="wpforo-ai-box-body">
				<!-- Loading indicator -->
				<div class="wpforo-ai-logs-loading" id="wpforo-ai-logs-loading" style="display: none;">
					<span class="spinner is-active"></span>
					<?php esc_html_e( 'Loading logs...', 'wpforo' ); ?>
				</div>

				<!-- Logs Table -->
				<table class="wp-list-table widefat fixed striped wpforo-ai-logs-table" id="wpforo-ai-logs-table">
					<thead>
						<tr>
							<th class="check-column">
								<input type="checkbox" id="wpforo-ai-logs-select-all" class="wpforo-ai-select-all-logs">
							</th>
							<th class="column-datetime" style="width: 140px;"><?php esc_html_e( 'Date/Time', 'wpforo' ); ?></th>
							<th class="column-action-type" style="width: 150px;"><?php esc_html_e( 'Action', 'wpforo' ); ?></th>
							<th class="column-user" style="width: 120px;"><?php esc_html_e( 'User', 'wpforo' ); ?></th>
							<th class="column-credits" style="width: 80px;"><?php esc_html_e( 'Credits', 'wpforo' ); ?></th>
							<th class="column-status" style="width: 90px;"><?php esc_html_e( 'Status', 'wpforo' ); ?></th>
							<th class="column-summary"><?php esc_html_e( 'Summary', 'wpforo' ); ?></th>
							<th class="column-actions" style="width: 100px;"><?php esc_html_e( 'Actions', 'wpforo' ); ?></th>
						</tr>
					</thead>
					<tbody id="wpforo-ai-logs-tbody">
						<?php if ( empty( $initial_logs ) ) : ?>
							<tr class="wpforo-ai-logs-empty-row">
								<td colspan="8">
									<div class="wpforo-ai-logs-empty">
										<span class="dashicons dashicons-info-outline"></span>
										<?php esc_html_e( 'No logs found. AI action logs will appear here as users interact with AI features.', 'wpforo' ); ?>
									</div>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $initial_logs as $log ) : ?>
								<?php wpforo_ai_render_log_row( $log ); ?>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<!-- Pagination -->
				<div class="wpforo-ai-logs-pagination" id="wpforo-ai-logs-pagination" data-per-page="<?php echo esc_attr( $per_page ); ?>">
					<?php if ( $total_logs > $per_page ) : ?>
						<div class="tablenav-pages">
							<span class="displaying-num">
								<?php printf( esc_html__( '%s items', 'wpforo' ), number_format( $total_logs ) ); ?>
							</span>
							<span class="pagination-links">
								<button type="button" class="button first-page" data-page="1" disabled>
									<span class="screen-reader-text"><?php esc_html_e( 'First page', 'wpforo' ); ?></span>
									<span aria-hidden="true">&laquo;</span>
								</button>
								<button type="button" class="button prev-page" data-page="1" disabled>
									<span class="screen-reader-text"><?php esc_html_e( 'Previous page', 'wpforo' ); ?></span>
									<span aria-hidden="true">&lsaquo;</span>
								</button>
								<span class="paging-input">
									<span class="current-page">1</span>
									<?php esc_html_e( 'of', 'wpforo' ); ?>
									<span class="total-pages"><?php echo ceil( $total_logs / $per_page ); ?></span>
								</span>
								<button type="button" class="button next-page" data-page="2" <?php echo $total_logs <= $per_page ? 'disabled' : ''; ?>>
									<span class="screen-reader-text"><?php esc_html_e( 'Next page', 'wpforo' ); ?></span>
									<span aria-hidden="true">&rsaquo;</span>
								</button>
								<button type="button" class="button last-page" data-page="<?php echo ceil( $total_logs / $per_page ); ?>" <?php echo $total_logs <= $per_page ? 'disabled' : ''; ?>>
									<span class="screen-reader-text"><?php esc_html_e( 'Last page', 'wpforo' ); ?></span>
									<span aria-hidden="true">&raquo;</span>
								</button>
							</span>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- Settings Section -->
		<div class="wpforo-ai-box wpforo-ai-logs-settings-box">
			<div class="wpforo-ai-box-header">
				<h3 style="margin: 2px 0;">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'Log Settings', 'wpforo' ); ?>
				</h3>
			</div>
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-logs-settings">
					<div class="wpforo-ai-logs-setting-row">
						<label for="wpforo-ai-logs-per-page">
							<?php esc_html_e( 'Logs per page', 'wpforo' ); ?>
						</label>
						<select id="wpforo-ai-logs-per-page" class="wpforo-ai-logs-setting">
							<?php
							$per_page_options = [ 25, 50, 100, 200 ];
							$current_per_page = WPF()->ai_logs->get_per_page();
							foreach ( $per_page_options as $option ) :
							?>
								<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $current_per_page, $option ); ?>>
									<?php echo esc_html( $option ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<span class="wpforo-ai-logs-setting-status">
							<span class="wpforo-ai-logs-setting-spinner spinner" id="wpforo-ai-logs-per-page-spinner"></span>
							<span class="wpforo-ai-logs-setting-saved" id="wpforo-ai-logs-per-page-saved">
								<span class="dashicons dashicons-yes-alt"></span>
							</span>
						</span>
					</div>
					<div class="wpforo-ai-logs-setting-row">
						<label for="wpforo-ai-logs-cleanup-days">
							<?php esc_html_e( 'Auto-delete logs older than', 'wpforo' ); ?>
						</label>
						<input type="number" id="wpforo-ai-logs-cleanup-days"
							   class="small-text wpforo-ai-logs-setting"
							   min="0" max="365"
							   value="<?php echo esc_attr( WPF()->ai_logs->get_cleanup_days() ); ?>"
							   placeholder="90">
						<span class="wpforo-ai-logs-setting-days"><?php esc_html_e( 'days', 'wpforo' ); ?></span>
						<span class="wpforo-ai-logs-setting-status">
							<span class="wpforo-ai-logs-setting-spinner spinner" id="wpforo-ai-logs-cleanup-spinner"></span>
							<span class="wpforo-ai-logs-setting-saved" id="wpforo-ai-logs-cleanup-saved">
								<span class="dashicons dashicons-yes-alt"></span>
							</span>
						</span>
						<span class="wpforo-ai-logs-setting-hint">
							<?php esc_html_e( '(0 = keep forever)', 'wpforo' ); ?>
						</span>
					</div>
				</div>
			</div>
		</div>

		<!-- Log Detail Modal -->
		<div class="wpforo-ai-log-detail-overlay" id="wpforo-ai-log-detail-overlay" style="display: none;">
			<div class="wpforo-ai-log-detail-modal">
				<div class="wpforo-ai-log-detail-header">
					<h3>
						<span class="dashicons dashicons-visibility"></span>
						<?php esc_html_e( 'Log Details', 'wpforo' ); ?>
					</h3>
					<button type="button" class="wpforo-ai-log-detail-close" id="wpforo-ai-log-detail-close">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
				<div class="wpforo-ai-log-detail-body" id="wpforo-ai-log-detail-body">
					<!-- Content loaded via AJAX -->
				</div>
			</div>
		</div>

		<!-- Empty All Confirmation Modal -->
		<div class="wpforo-ai-confirm-overlay" id="wpforo-ai-empty-confirm-overlay" style="display: none;">
			<div class="wpforo-ai-confirm-modal">
				<div class="wpforo-ai-confirm-header">
					<span class="dashicons dashicons-warning"></span>
					<?php esc_html_e( 'Confirm Delete All Logs', 'wpforo' ); ?>
				</div>
				<div class="wpforo-ai-confirm-body">
					<p><?php esc_html_e( 'Are you sure you want to delete ALL AI logs? This action cannot be undone.', 'wpforo' ); ?></p>
				</div>
				<div class="wpforo-ai-confirm-footer">
					<button type="button" class="button button-secondary" id="wpforo-ai-empty-cancel">
						<?php esc_html_e( 'Cancel', 'wpforo' ); ?>
					</button>
					<button type="button" class="button button-primary wpforo-ai-btn-danger" id="wpforo-ai-empty-confirm">
						<?php esc_html_e( 'Delete All Logs', 'wpforo' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>

	<?php
}

/**
 * Render a single log row
 *
 * @param array $log Log data
 */
function wpforo_ai_render_log_row( $log ) {
	$log_id      = intval( $log['id'] );
	$action_type = sanitize_text_field( $log['action_type'] );
	$status      = sanitize_text_field( $log['status'] );
	$user_type   = sanitize_text_field( $log['user_type'] );
	$credits     = intval( $log['credits_used'] );
	$duration    = intval( $log['duration_ms'] );
	$created     = $log['created'];

	// Format date in user's timezone (database stores UTC)
	$date_display = wpforo_ai_format_date_custom( $created, 'M j, Y' );
	$time_display = wpforo_ai_format_date_custom( $created, 'g:i a' );

	// Get display values
	$action_label  = AILogs::get_action_label( $action_type );
	$status_label  = AILogs::get_status_label( $status );
	$status_class  = AILogs::get_status_class( $status );
	$user_display  = isset( $log['user_display'] ) ? $log['user_display'] : __( 'Unknown', 'wpforo' );

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
