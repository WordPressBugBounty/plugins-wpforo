<?php
/**
 * AI Features - AI Tasks Tab
 *
 * Provides scheduling system for automated AI content generation:
 * - AI Topic Generator - Automatically creates new forum topics
 * - AI Reply Generator - Automatically replies to topics
 *
 * @package wpForo
 * @subpackage Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render AI Tasks tab content
 *
 * @param bool  $is_connected Whether tenant is connected to AI service
 * @param array $status       Tenant status data from API
 */
function wpforo_ai_render_ai_tasks_tab( $is_connected, $status ) {
	if ( ! $is_connected ) {
		?>
		<div class="wpforo-ai-box wpforo-ai-not-connected-notice">
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-status-badge status-warning">
					<span class="dashicons dashicons-warning"></span>
					<?php _e( 'Not Connected', 'wpforo' ); ?>
				</div>
				<p><?php _e( 'Please connect to wpForo AI API first in the Overview tab to enable AI Tasks.', 'wpforo' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpforo-ai&tab=overview' ) ); ?>" class="button button-primary">
					<?php _e( 'Go to Overview', 'wpforo' ); ?>
				</a>
			</div>
		</div>
		<?php
		return;
	}

	// Check if user's plan allows AI Tasks (requires Professional or higher)
	$subscription = isset( $status['subscription'] ) && is_array( $status['subscription'] ) ? $status['subscription'] : [];
	$current_plan = isset( $subscription['plan'] ) ? strtolower( $subscription['plan'] ) : 'free_trial';

	// AI Tasks require Professional plan or higher
	if ( ! wpforo_ai_is_feature_enabled_by_plan( $current_plan, 'professional' ) ) {
		?>
		<div class="wpforo-ai-box wpforo-ai-upgrade-notice">
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-status-badge status-info">
					<span class="dashicons dashicons-lock"></span>
					<?php _e( 'Upgrade to Professional Plan to Unlock', 'wpforo' ); ?>&nbsp;
				</div>
				<h3><?php _e( 'AI Tasks and Automations', 'wpforo' ); ?></h3>
				<p><?php _e( 'AI Tasks (Topic Generation, Reply Generation, and Tag Moderation) are available on Professional, Business, and Enterprise plans.', 'wpforo' ); ?></p>
				<ul class="wpforo-ai-feature-list">
					<li><span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Generate new topics automatically on schedule', 'wpforo' ); ?></li>
					<li><span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Reply to discussions to boost engagement', 'wpforo' ); ?></li>
					<li><span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Automatically manage topic tags', 'wpforo' ); ?></li>
					<li><span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Flexible scheduling (hourly, daily, weekly)', 'wpforo' ); ?></li>
				</ul>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpforo-ai&tab=overview#wpforo-ai-plans' ) ); ?>" class="button button-primary">
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

	// Validate boardid exists (use strict comparison for type safety)
	$valid_boardids = array_map( 'intval', array_column( $boards, 'boardid' ) );
	if ( ! in_array( $current_boardid, $valid_boardids, true ) ) {
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
						'tab'     => 'ai_tasks',
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

	// Get available credits from status (subscription already defined above)
	$credits_remaining = isset( $subscription['credits_remaining'] ) ? (int) $subscription['credits_remaining'] : 0;

	// Get tasks for the current board
	$tasks = wpforo_ai_get_tasks( $current_boardid );
	$task_count = count( $tasks );

	?>
	<div class="wpforo-ai-tasks wpforo-ai-tasks-tab">

		<!-- Description Panel -->
		<div class="wpforo-ai-box wpforo-ai-tasks-description-box">
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-tasks-header">
					<div class="wpforo-ai-tasks-info">
						<h2>
							<span class="dashicons dashicons-clock"></span>
							<?php _e( 'AI Tasks', 'wpforo' ); ?>
						</h2>
						<p class="wpforo-ai-description">
							<?php _e( 'Automate your forum with scheduled AI tasks. Create topics, generate replies, and keep your community active 24/7.', 'wpforo' ); ?>
						</p>
						<ul class="wpforo-ai-tasks-features">
							<li><span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Generate new topics automatically on schedule', 'wpforo' ); ?></li>
							<li><span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Reply to discussions to boost engagement', 'wpforo' ); ?></li>
							<li><span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Flexible scheduling (hourly, daily, weekly)', 'wpforo' ); ?></li>
							<li><span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Credit usage controls and limits', 'wpforo' ); ?></li>
						</ul>
						<div class="wpforo-ai-credit-notice wpforo-ai-notice-warning">
							<span class="dashicons dashicons-warning"></span>
							<p><?php _e( 'Tasks consume AI credits. Monitor usage carefully to avoid unexpected costs.', 'wpforo' ); ?></p>
						</div>
					</div>
					<div class="wpforo-ai-tasks-action">
						<button type="button" class="button button-primary button-hero wpforo-ai-create-task-btn" id="wpforo-ai-create-task-btn">
							<span class="dashicons dashicons-plus-alt2"></span>
							<?php _e( 'Create AI Task', 'wpforo' ); ?>
						</button>
						<p class="wpforo-ai-credits-info">
							<span class="dashicons dashicons-database"></span>
							<?php printf(
								__( '%s credits available', 'wpforo' ),
								'<strong>' . number_format( $credits_remaining ) . '</strong>'
							); ?>
						</p>
					</div>
				</div>
			</div>
		</div>

		<!-- Create Task Form (Hidden by default, slides down on button click) -->
		<div class="wpforo-ai-box wpforo-ai-task-form-box wpforo-ai-task-form-container" id="wpforo-ai-create-task-form-box">
			<div class="wpforo-ai-box-header">
				<h2>
					<span class="dashicons dashicons-plus-alt2"></span>
					<span id="wpforo-ai-task-form-title"><?php _e( 'Create New Task', 'wpforo' ); ?></span>
				</h2>
				<button type="button" class="button button-secondary wpforo-ai-cancel-task-btn">
					<span class="dashicons dashicons-no-alt"></span>
					<?php _e( 'Cancel', 'wpforo' ); ?>
				</button>
			</div>
			<div class="wpforo-ai-box-body">
				<form id="wpforo-ai-task-form" class="wpforo-ai-task-form">
					<?php wp_nonce_field( 'wpforo_ai_task_nonce', 'wpforo_ai_task_nonce' ); ?>
					<input type="hidden" id="wpforo-ai-task-nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpforo_ai_task_nonce' ) ); ?>">
					<input type="hidden" name="task_id" id="wpforo-ai-task-id" value="">
					<input type="hidden" name="board_id" id="wpforo-ai-task-board-id" value="<?php echo esc_attr( $current_boardid ); ?>">

					<!-- Basic Information Section -->
					<div class="wpforo-ai-form-section">
						<h3 class="wpforo-ai-form-section-title"><?php _e( 'Basic Information', 'wpforo' ); ?></h3>

						<div class="wpforo-ai-form-row">
							<div class="wpforo-ai-form-field wpforo-ai-form-field-half">
								<label for="wpforo-ai-task-type"><?php _e( 'Task Type', 'wpforo' ); ?> <span class="required">*</span></label>
								<select id="wpforo-ai-task-type" name="task_type" required>
									<option value=""><?php _e( 'Select task type...', 'wpforo' ); ?></option>
									<option value="topic_generator"><?php _e( 'AI Topic Generator', 'wpforo' ); ?></option>
									<option value="reply_generator"><?php _e( 'AI Reply Generator', 'wpforo' ); ?></option>
									<option value="tag_maintenance"><?php _e( 'AI Topic Tag Generator and Cleaning', 'wpforo' ); ?></option>
								</select>
								<p class="description"><?php _e( 'Choose what this task will do', 'wpforo' ); ?></p>
							</div>

							<div class="wpforo-ai-form-field wpforo-ai-form-field-half">
								<label for="wpforo-ai-task-name"><?php _e( 'Task Name', 'wpforo' ); ?> <span class="required">*</span></label>
								<input type="text" id="wpforo-ai-task-name" name="task_name" maxlength="100" required placeholder="<?php esc_attr_e( 'e.g., Daily Tech Topics', 'wpforo' ); ?>">
								<p class="description"><?php _e( 'A descriptive name for this task (3-100 characters)', 'wpforo' ); ?></p>
							</div>
						</div>

						<div class="wpforo-ai-form-row">
							<div class="wpforo-ai-form-field wpforo-ai-form-field-half">
								<label><?php _e( 'Status', 'wpforo' ); ?></label>
								<div class="wpforo-ai-radio-group">
									<label class="wpforo-ai-radio-item">
										<input type="radio" name="status" value="active">
										<span class="wpforo-ai-radio-label">
											<?php _e( 'Active', 'wpforo' ); ?>
										</span>
									</label>
									<label class="wpforo-ai-radio-item">
										<input type="radio" name="status" value="paused" checked>
										<span class="wpforo-ai-radio-label">
											<?php _e( 'Paused', 'wpforo' ); ?>
										</span>
									</label>
								</div>
								<p class="description"><?php _e( 'Set to Paused to configure before activating', 'wpforo' ); ?></p>
							</div>

							<div class="wpforo-ai-form-field wpforo-ai-form-field-half">
								<label for="wpforo-ai-task-language"><?php _e( 'Generated Content Language', 'wpforo' ); ?></label>
								<select id="wpforo-ai-task-language" name="response_language">
									<?php foreach ( WPF()->settings->get_variants_ai_languages() as $lang ) : ?>
										<option value="<?php echo esc_attr( $lang['value'] ); ?>"><?php echo esc_html( $lang['label'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php _e( 'Language for AI-generated content', 'wpforo' ); ?></p>
							</div>
						</div>
					</div>

					<!-- Dynamic Configuration Section (changes based on task type) -->
					<div id="wpforo-ai-task-config-section" class="wpforo-ai-form-section" style="display: none;">
						<!-- Content will be loaded dynamically based on task type -->
					</div>

					<!-- Form Actions -->
					<div class="wpforo-ai-form-actions">
						<button type="button" class="button button-secondary wpforo-ai-cancel-task-btn">
							<?php _e( 'Cancel', 'wpforo' ); ?>
						</button>
						<button type="submit" class="button button-primary button-large" id="wpforo-ai-save-task-btn">
							<span class="dashicons dashicons-saved"></span>
							<?php _e( 'Save Task', 'wpforo' ); ?>
						</button>
					</div>
				</form>
			</div>
		</div>

		<!-- Task List Table -->
		<div class="wpforo-ai-box wpforo-ai-tasks-list-box">
			<div class="wpforo-ai-box-header">
				<h2><?php _e( 'Your Tasks', 'wpforo' ); ?> <span class="wpforo-ai-task-count">(<?php echo $task_count; ?>)</span></h2>
				<div class="wpforo-ai-tasks-filters" style="display: flex; justify-content: flex-end; align-content: center;">
					<select id="wpforo-ai-tasks-filter-type" class="wpforo-ai-tasks-filter wpforo-ai-filter-type">
						<option value=""><?php _e( 'All Types', 'wpforo' ); ?></option>
						<option value="topic_generator"><?php _e( 'Topic Generator', 'wpforo' ); ?></option>
						<option value="reply_generator"><?php _e( 'Reply Generator', 'wpforo' ); ?></option>
						<option value="tag_maintenance"><?php _e( 'Tag Maintenance', 'wpforo' ); ?></option>
					</select>
					<select id="wpforo-ai-tasks-filter-status" class="wpforo-ai-tasks-filter wpforo-ai-filter-status">
						<option value=""><?php _e( 'All Status', 'wpforo' ); ?></option>
						<option value="active"><?php _e( 'Active', 'wpforo' ); ?></option>
						<option value="paused"><?php _e( 'Paused', 'wpforo' ); ?></option>
						<option value="error"><?php _e( 'Error', 'wpforo' ); ?></option>
					</select>
					<input type="text" id="wpforo-ai-tasks-search" class="wpforo-ai-tasks-search wpforo-ai-search-tasks" placeholder="<?php esc_attr_e( 'Search tasks...', 'wpforo' ); ?>">
				</div>
			</div>
			<div class="wpforo-ai-box-body">
				<?php if ( $task_count > 0 ) : ?>
					<!-- Bulk Actions -->
					<div class="wpforo-ai-tasks-bulk-actions">
						<select id="wpforo-ai-tasks-bulk-action" class="wpforo-ai-bulk-action-select">
							<option value=""><?php _e( 'Bulk Actions', 'wpforo' ); ?></option>
							<option value="activate"><?php _e( 'Activate Selected', 'wpforo' ); ?></option>
							<option value="pause"><?php _e( 'Pause Selected', 'wpforo' ); ?></option>
							<option value="delete"><?php _e( 'Delete Selected', 'wpforo' ); ?></option>
						</select>
						<button type="button" class="button wpforo-ai-bulk-apply" id="wpforo-ai-tasks-bulk-apply">
							<?php _e( 'Apply', 'wpforo' ); ?>
						</button>
					</div>

					<!-- Tasks Table -->
					<table class="wp-list-table widefat fixed striped wpforo-ai-tasks-table" id="wpforo-ai-tasks-table">
						<thead>
							<tr>
								<th class="check-column">
									<input type="checkbox" id="wpforo-ai-tasks-select-all" class="wpforo-ai-select-all-tasks">
								</th>
								<th class="column-name sortable" data-sort="name"><?php _e( 'Task Name', 'wpforo' ); ?></th>
								<th class="column-type"><?php _e( 'Type', 'wpforo' ); ?></th>
								<th class="column-status sortable" data-sort="status"><?php _e( 'Status', 'wpforo' ); ?></th>
								<th class="column-schedule"><?php _e( 'Schedule', 'wpforo' ); ?></th>
								<th class="column-run-times sortable" data-sort="last_run"><?php _e( 'Next / Last Run', 'wpforo' ); ?></th>
								<th class="column-stats"><?php _e( 'Stats', 'wpforo' ); ?></th>
								<th class="column-actions" style="width: 80px"><?php _e( 'Actions', 'wpforo' ); ?></th>
							</tr>
						</thead>
						<tbody id="wpforo-ai-tasks-tbody">
							<?php foreach ( $tasks as $task ) : ?>
								<?php wpforo_ai_render_task_row( $task ); ?>
							<?php endforeach; ?>
						</tbody>
					</table>

					<!-- Pagination -->
					<div class="wpforo-ai-tasks-pagination" id="wpforo-ai-tasks-pagination">
						<!-- Pagination will be rendered by JavaScript -->
					</div>

				<?php else : ?>
					<!-- Empty State -->
					<div class="wpforo-ai-tasks-empty">
						<span class="dashicons dashicons-schedule"></span>
						<h3><?php _e( 'No AI Tasks Yet', 'wpforo' ); ?></h3>
						<p><?php _e( 'Create your first AI task to automate forum content generation.', 'wpforo' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>

	</div>

	<!-- Task Type Configuration Templates -->
	<?php wpforo_ai_render_task_type_templates( $current_boardid ); ?>

	<!-- AI Tasks Tab JavaScript -->
	<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Initialize AI Tasks functionality
			if (typeof window.WpForoAI !== 'undefined' && typeof WpForoAI.initAITasks === 'function') {
				WpForoAI.initAITasks();
			}
		});
	</script>
	<?php
}

/**
 * Get tasks for a specific board
 *
 * @param int $board_id Board ID
 * @return array Array of task objects
 */
function wpforo_ai_get_tasks( $board_id = 0 ) {
	global $wpdb;

	$table = WPF()->tables->ai_tasks;

	// Check if table exists
	$table_exists = $wpdb->get_var( $wpdb->prepare(
		"SHOW TABLES LIKE %s",
		$table
	) );

	if ( ! $table_exists ) {
		return [];
	}

	$sql = $wpdb->prepare(
		"SELECT * FROM `{$table}` WHERE board_id = %d ORDER BY created_at DESC",
		$board_id
	);

	$tasks = $wpdb->get_results( $sql, ARRAY_A );

	return $tasks ? $tasks : [];
}

/**
 * Render a single task row for the table
 *
 * @param array $task Task data
 */
function wpforo_ai_render_task_row( $task ) {
	$task_id = (int) $task['task_id'];
	$task_name = esc_html( $task['task_name'] );
	$task_type = $task['task_type'];
	$status = $task['status'];
	// Decode config if it's a JSON string
	$config = $task['config'] ?? [];
	if ( is_string( $config ) ) {
		$config = json_decode( $config, true ) ?: [];
	}
	$frequency = $config['frequency'] ?? 'daily';
	$last_run = $task['last_run_time'];
	$last_run_status = $task['last_run_status'];
	$total_runs = (int) ( $task['total_runs'] ?? 0 );
	$items_created = (int) ( $task['items_created'] ?? 0 );
	$credits_used = (int) ( $task['credits_used'] ?? 0 );

	// Task type display
	$type_labels = [
		'topic_generator'  => __( 'Topic Generator', 'wpforo' ),
		'reply_generator'  => __( 'Reply Generator', 'wpforo' ),
		'tag_maintenance'  => __( 'Tag Maintenance', 'wpforo' ),
	];
	$type_icons = [
		'topic_generator'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><g><path d="M21,0H3A3,3,0,0,0,0,3V20H6.9l3.808,3.218a2,2,0,0,0,2.582,0L17.1,20H24V3A3,3,0,0,0,21,0Zm1,18H16.366L12,21.69,7.634,18H2V3A1,1,0,0,1,3,2H21a1,1,0,0,1,1,1Z"/><rect x="6" y="5" width="6" height="2"/><rect x="6" y="9" width="12" height="2"/><rect x="6" y="13" width="12" height="2"/></g></svg>',
		'reply_generator'  => '<svg style="transform: rotate(180deg);" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M23,24a1,1,0,0,1-1-1,6.006,6.006,0,0,0-6-6H10.17v1.586A2,2,0,0,1,6.756,20L.877,14.121a3,3,0,0,1,0-4.242L6.756,4A2,2,0,0,1,10.17,5.414V7H15a9.01,9.01,0,0,1,9,9v7A1,1,0,0,1,23,24ZM8.17,5.414,2.291,11.293a1,1,0,0,0,0,1.414L8.17,18.586V16a1,1,0,0,1,1-1H16a7.984,7.984,0,0,1,6,2.714V16a7.008,7.008,0,0,0-7-7H9.17a1,1,0,0,1-1-1Z"/></svg>',
		'tag_maintenance'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12,0a4,4,0,0,0-4,4V6.75L.854,13.9a2.978,2.978,0,0,0,0,4.2L5.9,23.146a2.978,2.978,0,0,0,4.2,0L17.25,16H20a4,4,0,0,0,4-4V4a4,4,0,0,0-4-4ZM22,12a2,2,0,0,1-2,2H16.664a1,1,0,0,0-.707.293L8.686,21.564a1,1,0,0,1-1.372,0l-5.05-5.05a1,1,0,0,1,0-1.372l7.271-7.271A1,1,0,0,0,10,7.164V4a2,2,0,0,1,2-2h8a2,2,0,0,1,2,2ZM17,5a2,2,0,1,0,2,2A2,2,0,0,0,17,5Z"/></svg>',
	];
	$type_label = isset( $type_labels[ $task_type ] ) ? $type_labels[ $task_type ] : $task_type;
	$type_icon = isset( $type_icons[ $task_type ] ) ? $type_icons[ $task_type ] : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12,8a4,4,0,1,0,4,4A4,4,0,0,0,12,8Zm0,6a2,2,0,1,1,2-2A2,2,0,0,1,12,14Z"/><path d="M21.294,13.9l-.444-.256a9.1,9.1,0,0,0,0-3.29l.444-.256a3,3,0,1,0-3-5.2l-.445.257A8.977,8.977,0,0,0,15,3.513V3A3,3,0,0,0,9,3v.513A8.977,8.977,0,0,0,6.152,5.159L5.705,4.9a3,3,0,0,0-3,5.2l.444.256a9.1,9.1,0,0,0,0,3.29l-.444.256a3,3,0,1,0,3,5.2l.445-.257A8.977,8.977,0,0,0,9,20.487V21a3,3,0,0,0,6,0v-.513a8.977,8.977,0,0,0,2.848-1.646l.447.258a3,3,0,0,0,3-5.2Zm-2.548-3.776a7.048,7.048,0,0,1,0,3.75,1,1,0,0,0,.464,1.133l1.084.626a1,1,0,0,1-1,1.733l-1.086-.628a1,1,0,0,0-1.215.165,6.984,6.984,0,0,1-3.243,1.875,1,1,0,0,0-.751.969V21a1,1,0,0,1-2,0V19.748a1,1,0,0,0-.751-.969A6.984,6.984,0,0,1,7.006,16.9a1,1,0,0,0-1.215-.165l-1.084.627a1,1,0,1,1-1-1.732l1.084-.626a1,1,0,0,0,.464-1.133,7.048,7.048,0,0,1,0-3.75A1,1,0,0,0,4.79,8.992L3.706,8.366a1,1,0,0,1,1-1.733l1.086.628A1,1,0,0,0,7.006,7.1a6.984,6.984,0,0,1,3.243-1.875A1,1,0,0,0,11,4.252V3a1,1,0,0,1,2,0V4.252a1,1,0,0,0,.751.969A6.984,6.984,0,0,1,16.994,7.1a1,1,0,0,0,1.215.165l1.084-.627a1,1,0,1,1,1,1.732l-1.084.626A1,1,0,0,0,18.746,10.125Z"/></svg>';

	// Status display
	$status_classes = [
		'active' => 'status-active',
		'paused' => 'status-paused',
		'error'  => 'status-error',
	];
	$status_labels = [
		'active' => '<span class="dashicons dashicons-yes-alt"></span> ' . __( 'Active', 'wpforo' ),
		'paused' => '<span class="dashicons dashicons-clock"></span> ' . __( 'Paused', 'wpforo' ),
		'error'  => '<span class="dashicons dashicons-warning"></span> ' . __( 'Error', 'wpforo' ),
	];
	$status_class = isset( $status_classes[ $status ] ) ? $status_classes[ $status ] : 'status-paused';
	$status_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status;

	// Frequency display
	$frequency_labels = [
		'hourly'  => __( 'Every Hour', 'wpforo' ),
		'3hours'  => __( 'Every 3 Hours', 'wpforo' ),
		'6hours'  => __( 'Every 6 Hours', 'wpforo' ),
		'daily'   => __( 'Daily', 'wpforo' ),
		'3days'   => __( 'Every 3 Days', 'wpforo' ),
		'weekly'  => __( 'Weekly', 'wpforo' ),
		'custom'  => __( 'Custom', 'wpforo' ),
	];
	$frequency_label = isset( $frequency_labels[ $frequency ] ) ? $frequency_labels[ $frequency ] : $frequency;

	// Check if run_on_approval is enabled
	$run_on_approval = ! empty( $config['run_on_approval'] );

	// Next run display (time until next run in "in X hours" style)
	$next_run = $task['next_run_time'] ?? null;
	$next_run_display = '';
	$next_run_class = '';
	if ( $run_on_approval && $status === 'active' ) {
		// run_on_approval tasks trigger on topic/post approval, not cron
		$next_run_display = __( 'New Topic', 'wpforo' );
		$next_run_class = 'next-run-on-approval';
	} elseif ( $next_run && $status === 'active' ) {
		$next_run_display = wpforo_ai_format_time_until( $next_run );
		// Check if overdue
		if ( $next_run_display === __( 'overdue', 'wpforo' ) ) {
			$next_run_class = 'next-run-overdue';
		}
	} elseif ( $status === 'paused' ) {
		$next_run_display = __( 'Paused', 'wpforo' );
	} else {
		$next_run_display = '—';
	}

	// Last run display
	$last_run_display = $last_run ? wpforo_ai_format_date( $last_run ) : __( 'Never', 'wpforo' );
	$last_run_status_class = '';
	if ( $last_run_status === 'success' ) {
		$last_run_status_class = 'last-run-success';
	} elseif ( $last_run_status === 'failure' ) {
		$last_run_status_class = 'last-run-failure';
	}

	?>
	<tr data-task-id="<?php echo $task_id; ?>" data-task-type="<?php echo esc_attr( $task_type ); ?>" data-status="<?php echo esc_attr( $status ); ?>">
		<td class="check-column">
			<input type="checkbox" name="task_ids[]" value="<?php echo $task_id; ?>" class="wpforo-ai-task-checkbox">
		</td>
		<td class="column-name">
			<strong class="wpforo-ai-task-name"><?php echo $task_name; ?></strong>
			<div class="row-actions" style="display: none;">
				<span class="edit">
					<a href="#" class="wpforo-ai-task-edit" data-task-id="<?php echo $task_id; ?>"><?php _e( 'Edit', 'wpforo' ); ?></a> |
				</span>
				<span class="run">
					<a href="#" class="wpforo-ai-task-run" data-task-id="<?php echo $task_id; ?>"><?php _e( 'Run Now', 'wpforo' ); ?></a> |
				</span>
				<span class="logs">
					<a href="#" class="wpforo-ai-task-logs" data-task-id="<?php echo $task_id; ?>"><?php _e( 'View Logs', 'wpforo' ); ?></a> |
				</span>
				<span class="delete">
					<a href="#" class="wpforo-ai-task-delete" data-task-id="<?php echo $task_id; ?>"><?php _e( 'Delete', 'wpforo' ); ?></a>
				</span>
			</div>
		</td>
		<td class="column-type">
			<span class="wpforo-ai-task-type-badge">
				<?php echo $type_icon; ?>
				<?php echo $type_label; ?>
			</span>
		</td>
		<td class="column-status">
			<span class="wpforo-ai-task-status <?php echo esc_attr( $status_class ); ?>">
				<?php echo $status_label; ?>
			</span>
		</td>
		<td class="column-schedule">
			<?php echo $run_on_approval ? '—' : $frequency_label; ?>
		</td>
		<td class="column-run-times <?php echo esc_attr( $last_run_status_class ); ?> <?php echo esc_attr( $next_run_class ); ?>">
			<div class="wpforo-ai-next-run"><?php echo esc_html( $next_run_display ); ?></div>
			<div class="wpforo-ai-last-run"><?php echo $last_run_display; ?></div>
		</td>
		<td class="column-stats">
			<span class="wpforo-ai-task-stat" title="<?php esc_attr_e( 'Total Runs', 'wpforo' ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M20.492,7.969,10.954.975A5,5,0,0,0,3,5.005V19a4.994,4.994,0,0,0,7.954,4.03l9.538-6.994a5,5,0,0,0,0-8.062Z"/></svg>
				<?php echo number_format( $total_runs ); ?>
			</span>
			<span class="wpforo-ai-task-stat" title="<?php esc_attr_e( 'Number of Items', 'wpforo' ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M18.656.93,6.464,13.122A4.966,4.966,0,0,0,5,16.657V18a1,1,0,0,0,1,1H7.343a4.966,4.966,0,0,0,3.535-1.464L23.07,5.344a3.125,3.125,0,0,0,0-4.414A3.194,3.194,0,0,0,18.656.93Zm3,3L9.464,16.122A3.02,3.02,0,0,1,7.343,17H7v-.343a3.02,3.02,0,0,1,.878-2.121L20.07,2.344a1.148,1.148,0,0,1,1.586,0A1.123,1.123,0,0,1,21.656,3.93Z"/><path d="M23,8.979a1,1,0,0,0-1,1V15H18a3,3,0,0,0-3,3v4H5a3,3,0,0,1-3-3V5A3,3,0,0,1,5,2h9.042a1,1,0,0,0,0-2H5A5.006,5.006,0,0,0,0,5V19a5.006,5.006,0,0,0,5,5H16.343a4.968,4.968,0,0,0,3.536-1.464l2.656-2.658A4.968,4.968,0,0,0,24,16.343V9.979A1,1,0,0,0,23,8.979ZM18.465,21.122a2.975,2.975,0,0,1-1.465.8V18a1,1,0,0,1,1-1h3.925a3.016,3.016,0,0,1-.8,1.464Z"/></svg>
				<?php echo number_format( $items_created ); ?>
			</span>
			<span class="wpforo-ai-task-stat" title="<?php esc_attr_e( 'Credits Used', 'wpforo' ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12,0C5.383,0,0,3.134,0,7v4c0,3.866,5.383,7,12,7s12-3.134,12-7V7C24,3.134,18.617,0,12,0Zm10,11c0,2.414-4.037,5-10,5S2,13.414,2,11V9.455C4.144,11.03,7.789,12,12,12s7.856-.97,10-2.545V11ZM12,2c5.963,0,10,2.586,10,5s-4.037,5-10,5S2,9.414,2,7,6.037,2,12,2Z"/><path d="M12,16c-6.617,0-12-3.134-12-7v6c0,3.866,5.383,7,12,7s12-3.134,12-7v-6C24,12.866,18.617,16,12,16Zm0,5c-5.963,0-10-2.586-10-5v-2.545C4.144,15.03,7.789,16,12,16s7.856-.97,10-2.545V16C22,18.414,17.963,21,12,21Z"/></svg>
				<?php echo number_format( $credits_used ); ?>
			</span>
		</td>
		<td class="column-actions">
			<div class="wpforo-ai-task-actions-dropdown">
				<button type="button" class="wpforo-ai-task-actions-toggle">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12,8a4,4,0,1,0,4,4A4,4,0,0,0,12,8Zm0,6a2,2,0,1,1,2-2A2,2,0,0,1,12,14Z"/><path d="M21.294,13.9l-.444-.256a9.1,9.1,0,0,0,0-3.29l.444-.256a3,3,0,1,0-3-5.2l-.445.257A8.977,8.977,0,0,0,15,3.513V3A3,3,0,0,0,9,3v.513A8.977,8.977,0,0,0,6.152,5.159L5.705,4.9a3,3,0,0,0-3,5.2l.444.256a9.1,9.1,0,0,0,0,3.29l-.444.256a3,3,0,1,0,3,5.2l.445-.257A8.977,8.977,0,0,0,9,20.487V21a3,3,0,0,0,6,0v-.513a8.977,8.977,0,0,0,2.848-1.646l.447.258a3,3,0,0,0,3-5.2Zm-2.548-3.776a7.048,7.048,0,0,1,0,3.75,1,1,0,0,0,.464,1.133l1.084.626a1,1,0,0,1-1,1.733l-1.086-.628a1,1,0,0,0-1.215.165,6.984,6.984,0,0,1-3.243,1.875,1,1,0,0,0-.751.969V21a1,1,0,0,1-2,0V19.748a1,1,0,0,0-.751-.969A6.984,6.984,0,0,1,7.006,16.9a1,1,0,0,0-1.215-.165l-1.084.627a1,1,0,1,1-1-1.732l1.084-.626a1,1,0,0,0,.464-1.133,7.048,7.048,0,0,1,0-3.75A1,1,0,0,0,4.79,8.992L3.706,8.366a1,1,0,0,1,1-1.733l1.086.628A1,1,0,0,0,7.006,7.1a6.984,6.984,0,0,1,3.243-1.875A1,1,0,0,0,11,4.252V3a1,1,0,0,1,2,0V4.252a1,1,0,0,0,.751.969A6.984,6.984,0,0,1,16.994,7.1a1,1,0,0,0,1.215.165l1.084-.627a1,1,0,1,1,1,1.732l-1.084.626A1,1,0,0,0,18.746,10.125Z"/></svg>
				</button>
				<div class="wpforo-ai-task-actions-menu">
					<a href="#" class="wpforo-ai-task-run" data-task-id="<?php echo $task_id; ?>">
						<span class="dashicons dashicons-controls-play"></span>
						<?php _e( 'Run Now', 'wpforo' ); ?>
					</a>
					<?php if ( $status === 'active' ) : ?>
						<a href="#" class="wpforo-ai-task-pause" data-task-id="<?php echo $task_id; ?>">
							<span class="dashicons dashicons-controls-pause"></span>
							<?php _e( 'Pause', 'wpforo' ); ?>
						</a>
					<?php else : ?>
						<a href="#" class="wpforo-ai-task-activate" data-task-id="<?php echo $task_id; ?>">
							<span class="dashicons dashicons-controls-play"></span>
							<?php _e( 'Activate', 'wpforo' ); ?>
						</a>
					<?php endif; ?>
					<a href="#" class="wpforo-ai-task-edit" data-task-id="<?php echo $task_id; ?>">
						<span class="dashicons dashicons-edit"></span>
						<?php _e( 'Edit', 'wpforo' ); ?>
					</a>
					<a href="#" class="wpforo-ai-task-duplicate" data-task-id="<?php echo $task_id; ?>">
						<span class="dashicons dashicons-admin-page"></span>
						<?php _e( 'Duplicate', 'wpforo' ); ?>
					</a>
					<a href="#" class="wpforo-ai-task-stats" data-task-id="<?php echo $task_id; ?>">
						<span class="dashicons dashicons-chart-bar"></span>
						<?php _e( 'View Stats', 'wpforo' ); ?>
					</a>
					<a href="#" class="wpforo-ai-task-logs" data-task-id="<?php echo $task_id; ?>">
						<span class="dashicons dashicons-list-view"></span>
						<?php _e( 'View Logs', 'wpforo' ); ?>
					</a>
					<hr>
					<a href="#" class="wpforo-ai-task-delete" data-task-id="<?php echo $task_id; ?>">
						<span class="dashicons dashicons-trash"></span>
						<?php _e( 'Delete', 'wpforo' ); ?>
					</a>
				</div>
			</div>
		</td>
	</tr>
	<?php
}

/**
 * Render task type configuration templates (hidden, used by JavaScript)
 *
 * @param int $board_id Current board ID
 */
function wpforo_ai_render_task_type_templates( $board_id = 0 ) {
	// Get forums for the current board and sort hierarchically
	$forums = WPF()->forum->get_forums();

	// Sort forums hierarchically: parents first, then children under each parent
	$forums_by_parent = [];
	foreach ( $forums as $forum ) {
		$parent = (int) ( $forum['parentid'] ?? 0 );
		if ( ! isset( $forums_by_parent[ $parent ] ) ) {
			$forums_by_parent[ $parent ] = [];
		}
		$forums_by_parent[ $parent ][] = $forum;
	}
	foreach ( $forums_by_parent as &$children ) {
		usort( $children, function( $a, $b ) {
			return (int) ( $a['order'] ?? 0 ) - (int) ( $b['order'] ?? 0 );
		} );
	}
	unset( $children );
	$sorted_forums = [];
	$add_forums_recursive = function( $parentid ) use ( &$add_forums_recursive, &$sorted_forums, &$forums_by_parent ) {
		if ( ! isset( $forums_by_parent[ $parentid ] ) ) {
			return;
		}
		foreach ( $forums_by_parent[ $parentid ] as $forum ) {
			$sorted_forums[] = $forum;
			$add_forums_recursive( (int) $forum['forumid'] );
		}
	};
	$add_forums_recursive( 0 );
	$forums = $sorted_forums;
	?>

	<!-- Topic Generator Configuration Template -->
	<script type="text/template" id="wpforo-ai-task-config-topic_generator">
		<?php wpforo_ai_render_topic_generator_config( $forums ); ?>
	</script>

	<!-- Reply Generator Configuration Template -->
	<script type="text/template" id="wpforo-ai-task-config-reply_generator">
		<?php wpforo_ai_render_reply_generator_config( $forums ); ?>
	</script>

	<!-- Tag Maintenance Configuration Template -->
	<script type="text/template" id="wpforo-ai-task-config-tag_maintenance">
		<?php wpforo_ai_render_tag_maintenance_config( $forums ); ?>
	</script>
	<?php
}

/**
 * Render Topic Generator configuration form fields
 *
 * @param array $forums Array of forums
 */
function wpforo_ai_render_topic_generator_config( $forums ) {
	?>
	<!-- Content Settings Section -->
	<div class="wpforo-ai-form-section">
		<h3 class="wpforo-ai-form-section-title">
			<span class="dashicons dashicons-edit-page"></span>
			<?php _e( 'Content Settings', 'wpforo' ); ?>
		</h3>

		<div class="wpforo-ai-two-column-layout">
			<!-- Left Column: Target Forums -->
			<div class="wpforo-ai-column wpforo-ai-column-left">
				<div class="wpforo-ai-form-field">
					<label><?php _e( 'Target Forums', 'wpforo' ); ?> <span class="required">*</span></label>
					<div class="wpforo-ai-forum-checklist">
						<?php foreach ( $forums as $forum ) :
							$forum_id = (int) $forum['forumid'];
							$forum_title = esc_html( $forum['title'] );
							$parent_id = (int) $forum['parentid'];
							$is_cat = (int) $forum['is_cat'];
							$item_class = $parent_id > 0 ? 'wpforo-ai-forum-child' : 'wpforo-ai-forum-parent';
							if ( $is_cat ) {
								$item_class .= ' wpforo-ai-forum-category';
							}
							?>
							<label class="wpforo-ai-forum-checkbox-item <?php echo esc_attr( $item_class ); ?>">
								<?php if ( $is_cat ) : ?>
								<input type="checkbox"
									class="forum-checkbox forum-parent-toggle"
									data-forum-id="<?php echo $forum_id; ?>"
									data-parent-id="<?php echo $parent_id; ?>"
									data-is-category="1">
								<?php else : ?>
								<input type="checkbox"
									name="config[target_forum_ids][]"
									value="<?php echo $forum_id; ?>"
									class="forum-checkbox"
									data-forum-id="<?php echo $forum_id; ?>"
									data-parent-id="<?php echo $parent_id; ?>"
									data-is-category="0">
								<?php endif; ?>
								<?php echo $forum_title; ?>
							</label>
						<?php endforeach; ?>
					</div>
					<div class="wpforo-ai-forum-actions">
						<button type="button" class="button button-small wpforo-ai-select-all-forums">
							<?php _e( 'Select All', 'wpforo' ); ?>
						</button>
						<button type="button" class="button button-small wpforo-ai-deselect-all-forums">
							<?php _e( 'Deselect All', 'wpforo' ); ?>
						</button>
					</div>
					<p class="description"><?php _e( 'Select forums where new topics will be created', 'wpforo' ); ?></p>
				</div>
			</div>

			<!-- Right Column: Topic Settings -->
			<div class="wpforo-ai-column wpforo-ai-column-right">
				<div class="wpforo-ai-form-field">
					<label for="wpforo-ai-config-topic_theme"><?php _e( 'Topic Theme/Focus', 'wpforo' ); ?></label>
					<div class="wpforo-ai-textarea-with-counter">
						<textarea id="wpforo-ai-config-topic_theme" name="config[topic_theme]" rows="3" maxlength="120" placeholder="<?php esc_attr_e( 'e.g., Web development tutorials, JavaScript tips...', 'wpforo' ); ?>" data-char-limit="120"></textarea>
						<span class="wpforo-ai-char-counter"><span class="current">0</span>/120</span>
					</div>
					<p class="description"><?php _e( 'Brief theme to guide topic creation (max 120 characters)', 'wpforo' ); ?></p>
				</div>

				<div class="wpforo-ai-form-field">
					<label for="wpforo-ai-config-topic_style"><?php _e( 'Topic Style', 'wpforo' ); ?></label>
					<select id="wpforo-ai-config-topic_style" name="config[topic_style]" required>
						<option value="neutral" selected="selected"><?php _e( 'Neutral - Let custom instructions decide', 'wpforo' ); ?></option>
						<option value="tutorial"><?php _e( 'Tutorial - Step-by-step guide', 'wpforo' ); ?></option>
						<option value="question"><?php _e( 'Question - Asking for help/opinions', 'wpforo' ); ?></option>
						<option value="discussion"><?php _e( 'Discussion - Open conversation starter', 'wpforo' ); ?></option>
						<option value="news"><?php _e( 'News - Updates and announcements', 'wpforo' ); ?></option>
						<option value="tips"><?php _e( 'Tips & Tricks - Quick helpful hints', 'wpforo' ); ?></option>
						<option value="how_to"><?php _e( 'How-To Guide - Practical instructions', 'wpforo' ); ?></option>
						<option value="review"><?php _e( 'Review - Product/service evaluation', 'wpforo' ); ?></option>
						<option value="comparison"><?php _e( 'Comparison - Comparing options', 'wpforo' ); ?></option>
						<option value="case_study"><?php _e( 'Case Study - Real-world example', 'wpforo' ); ?></option>
						<option value="opinion"><?php _e( 'Opinion Piece - Personal perspective', 'wpforo' ); ?></option>
						<option value="problem_solution"><?php _e( 'Problem/Solution - Issues and fixes', 'wpforo' ); ?></option>
						<option value="listicle"><?php _e( 'List/Roundup - Curated collection', 'wpforo' ); ?></option>
						<option value="interview"><?php _e( 'Interview Style - Q&A format', 'wpforo' ); ?></option>
						<option value="beginners_guide"><?php _e( 'Beginner\'s Guide - Introductory content', 'wpforo' ); ?></option>
						<option value="deep_dive"><?php _e( 'Deep Dive - In-depth analysis', 'wpforo' ); ?></option>
						<option value="best_practices"><?php _e( 'Best Practices - Recommended approaches', 'wpforo' ); ?></option>
						<option value="troubleshooting"><?php _e( 'Troubleshooting - Fixing common issues', 'wpforo' ); ?></option>
						<option value="resources"><?php _e( 'Resource Collection - Useful links/tools', 'wpforo' ); ?></option>
						<option value="myth_busting"><?php _e( 'Myth Busting - Correcting misconceptions', 'wpforo' ); ?></option>
						<option value="success_story"><?php _e( 'Success Story - Sharing achievements', 'wpforo' ); ?></option>
						<option value="challenge"><?php _e( 'Challenge/Exercise - Interactive content', 'wpforo' ); ?></option>
						<option value="faq"><?php _e( 'FAQ - Frequently asked questions', 'wpforo' ); ?></option>
					</select>
				</div>

				<div class="wpforo-ai-form-field">
					<label for="wpforo-ai-config-topic_tone"><?php _e( 'Topic Tone', 'wpforo' ); ?></label>
					<select id="wpforo-ai-config-topic_tone" name="config[topic_tone]" required>
						<option value="neutral" selected="selected"><?php _e( 'Neutral - Let custom instructions decide', 'wpforo' ); ?></option>
						<option value="professional"><?php _e( 'Professional - Business-like and formal', 'wpforo' ); ?></option>
						<option value="friendly"><?php _e( 'Friendly - Warm and approachable', 'wpforo' ); ?></option>
						<option value="casual"><?php _e( 'Casual - Relaxed and informal', 'wpforo' ); ?></option>
						<option value="technical"><?php _e( 'Technical - Detailed and precise', 'wpforo' ); ?></option>
						<option value="enthusiastic"><?php _e( 'Enthusiastic - Excited and energetic', 'wpforo' ); ?></option>
						<option value="helpful"><?php _e( 'Helpful - Supportive and guiding', 'wpforo' ); ?></option>
						<option value="authoritative"><?php _e( 'Authoritative - Expert and confident', 'wpforo' ); ?></option>
						<option value="conversational"><?php _e( 'Conversational - Like chatting with a friend', 'wpforo' ); ?></option>
						<option value="educational"><?php _e( 'Educational - Teaching and informative', 'wpforo' ); ?></option>
						<option value="inspirational"><?php _e( 'Inspirational - Motivating and uplifting', 'wpforo' ); ?></option>
						<option value="humorous"><?php _e( 'Humorous - Light-hearted and witty', 'wpforo' ); ?></option>
						<option value="serious"><?php _e( 'Serious - Focused and earnest', 'wpforo' ); ?></option>
						<option value="encouraging"><?php _e( 'Encouraging - Positive and supportive', 'wpforo' ); ?></option>
						<option value="warm"><?php _e( 'Warm - Caring and compassionate', 'wpforo' ); ?></option>
						<option value="direct"><?php _e( 'Direct - Straightforward and to the point', 'wpforo' ); ?></option>
						<option value="thoughtful"><?php _e( 'Thoughtful - Considerate and reflective', 'wpforo' ); ?></option>
						<option value="empathetic"><?php _e( 'Empathetic - Understanding and relatable', 'wpforo' ); ?></option>
						<option value="confident"><?php _e( 'Confident - Self-assured and decisive', 'wpforo' ); ?></option>
						<option value="curious"><?php _e( 'Curious - Inquisitive and engaging', 'wpforo' ); ?></option>
						<option value="playful"><?php _e( 'Playful - Fun and lighthearted', 'wpforo' ); ?></option>
						<option value="sincere"><?php _e( 'Sincere - Genuine and honest', 'wpforo' ); ?></option>
					</select>
				</div>

				<div class="wpforo-ai-form-field">
					<label for="wpforo-ai-config-content_length"><?php _e( 'Content Length', 'wpforo' ); ?></label>
					<select id="wpforo-ai-config-content_length" name="config[content_length]" required>
						<option value="brief"><?php _e( 'Brief (100-200 words)', 'wpforo' ); ?></option>
						<option value="medium" selected="selected"><?php _e( 'Medium (200-400 words)', 'wpforo' ); ?></option>
						<option value="detailed"><?php _e( 'Detailed (400-800 words)', 'wpforo' ); ?></option>
						<option value="comprehensive"><?php _e( 'Comprehensive (800-1200 words)', 'wpforo' ); ?></option>
					</select>
				</div>

				<div class="wpforo-ai-form-field">
					<label><?php _e( 'Include in Topics', 'wpforo' ); ?></label>
					<div class="wpforo-ai-checkbox-group wpforo-ai-checkbox-group-inline">
						<label class="wpforo-ai-checkbox-item">
							<input type="checkbox" name="config[include_code]" value="1">
							<span><?php _e( 'Code examples', 'wpforo' ); ?></span>
						</label>
						<label class="wpforo-ai-checkbox-item">
							<input type="checkbox" name="config[include_links]" value="1">
							<span><?php _e( 'External links', 'wpforo' ); ?></span>
						</label>
						<label class="wpforo-ai-checkbox-item">
							<input type="checkbox" name="config[include_steps]" value="1">
							<span><?php _e( 'Step-by-step instructions', 'wpforo' ); ?></span>
						</label>
						<label class="wpforo-ai-checkbox-item">
							<input type="checkbox" name="config[include_youtube]" value="1">
							<span><?php _e( 'YouTube video URLs', 'wpforo' ); ?></span>
						</label>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Author Settings & Scheduling Section -->
	<div class="wpforo-ai-form-section">
		<div class="wpforo-ai-two-column-layout">
			<!-- Left Column: Author Settings -->
			<div class="wpforo-ai-column wpforo-ai-column-left">
				<h3 class="wpforo-ai-form-section-title">
					<span class="dashicons dashicons-admin-users"></span>
					<?php _e( 'Author Settings', 'wpforo' ); ?>
				</h3>

				<div class="wpforo-ai-form-row">
					<div class="wpforo-ai-form-field">
						<label for="wpforo-ai-config-author_userid"><?php _e( 'Topic Author', 'wpforo' ); ?></label>
						<div class="wpforo-ai-user-search-wrapper">
							<input type="hidden" id="wpforo-ai-config-author_userid" name="config[author_userid]" class="wpforo-ai-user-id-input">
							<input type="text" id="wpforo-ai-config-author_search" class="wpforo-ai-user-search" placeholder="<?php esc_attr_e( 'Type to search users...', 'wpforo' ); ?>" autocomplete="off">
							<div class="wpforo-ai-user-search-results"></div>
						</div>
						<p class="description"><?php _e( 'Search by username, display name, or email. Only activated users are shown.', 'wpforo' ); ?></p>
					</div>
				</div>

				<div class="wpforo-ai-author-or-separator">
					<span><?php _e( '-- OR --', 'wpforo' ); ?></span>
				</div>

				<div class="wpforo-ai-form-row">
					<div class="wpforo-ai-form-field">
						<label for="wpforo-ai-config-author_groupid"><?php _e( 'Author Usergroup', 'wpforo' ); ?></label>
						<select id="wpforo-ai-config-author_groupid" name="config[author_groupid]" class="wpforo-ai-author-groupid-select">
							<option value=""><?php _e( '-- Select Usergroup --', 'wpforo' ); ?></option>
							<?php
							$usergroups = WPF()->usergroup->get_usergroups();
							if ( ! empty( $usergroups ) ) :
								foreach ( $usergroups as $group ) :
									$gid = intval( $group['groupid'] );
									if ( $gid === 4 ) continue; // Exclude Guest group
									?>
									<option value="<?php echo esc_attr( $gid ); ?>"><?php echo esc_html( $group['name'] ); ?></option>
								<?php endforeach;
							endif;
							?>
						</select>
						<p class="description"><?php _e( 'Randomly selects a user from this group for each generated topic.', 'wpforo' ); ?></p>
					</div>
				</div>

				<div class="wpforo-ai-form-row">
					<div class="wpforo-ai-form-field">
						<label class="wpforo-ai-checkbox-item">
							<input type="checkbox" name="config[show_ai_badge]" value="1">
							<span><?php _e( 'Add AI disclosure notice to generated content', 'wpforo' ); ?></span>
						</label>
						<p class="description"><?php _e( 'Adds a small disclaimer line at the end of AI-generated topics indicating the content was created by AI.', 'wpforo' ); ?></p>
					</div>
				</div>
			</div>

			<!-- Right Column: Scheduling -->
			<div class="wpforo-ai-column wpforo-ai-column-right">
				<h3 class="wpforo-ai-form-section-title">
					<span class="dashicons dashicons-calendar-alt"></span>
					<?php _e( 'Scheduling', 'wpforo' ); ?>
				</h3>

				<div class="wpforo-ai-form-row">
					<div class="wpforo-ai-form-field wpforo-ai-form-field-half">
						<label for="wpforo-ai-config-frequency"><?php _e( 'Frequency', 'wpforo' ); ?> <span class="required">*</span></label>
						<select id="wpforo-ai-config-frequency" name="config[frequency]" required>
							<option value="hourly"><?php _e( 'Every Hour', 'wpforo' ); ?></option>
							<option value="3hours"><?php _e( 'Every 3 Hours', 'wpforo' ); ?></option>
							<option value="6hours"><?php _e( 'Every 6 Hours', 'wpforo' ); ?></option>
							<option value="daily" selected="selected"><?php _e( 'Daily', 'wpforo' ); ?></option>
							<option value="3days"><?php _e( 'Every 3 Days', 'wpforo' ); ?></option>
							<option value="weekly"><?php _e( 'Weekly', 'wpforo' ); ?></option>
						</select>
					</div>

					<div class="wpforo-ai-form-field wpforo-ai-form-field-half">
						<label for="wpforo-ai-config-topics_per_run"><?php _e( 'Topics Per Run', 'wpforo' ); ?></label>
						<select id="wpforo-ai-config-topics_per_run" name="config[topics_per_run]">
							<option value="1" selected="selected">1</option>
							<option value="2">2</option>
							<option value="3">3</option>
							<option value="5">5</option>
							<option value="10">10</option>
						</select>
					</div>
				</div>

				<div class="wpforo-ai-form-row">
					<div class="wpforo-ai-form-field">
						<label><?php _e( 'Active Days', 'wpforo' ); ?></label>
						<div class="wpforo-ai-checkbox-group wpforo-ai-checkbox-group-inline">
							<?php
							$days = [
								'mon' => __( 'Mon', 'wpforo' ),
								'tue' => __( 'Tue', 'wpforo' ),
								'wed' => __( 'Wed', 'wpforo' ),
								'thu' => __( 'Thu', 'wpforo' ),
								'fri' => __( 'Fri', 'wpforo' ),
								'sat' => __( 'Sat', 'wpforo' ),
								'sun' => __( 'Sun', 'wpforo' ),
							];
							foreach ( $days as $day_key => $day_label ) :
								?>
								<label class="wpforo-ai-checkbox-item">
									<input type="checkbox" name="config[active_days][]" value="<?php echo $day_key; ?>" checked>
									<span><?php echo $day_label; ?></span>
								</label>
							<?php endforeach; ?>
						</div>
						<p class="description"><?php _e( 'Task will only run on selected days', 'wpforo' ); ?></p>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- AI Quality & Credits + Content Safety Section -->
	<div class="wpforo-ai-form-section">
		<div class="wpforo-ai-two-column-layout">
			<!-- Left Column: AI Quality & Credits -->
			<div class="wpforo-ai-column wpforo-ai-column-left">
				<h3 class="wpforo-ai-form-section-title">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php _e( 'AI Quality & Credits', 'wpforo' ); ?>
				</h3>

				<div class="wpforo-ai-form-row">
					<div class="wpforo-ai-form-field">
						<label for="wpforo-ai-config-quality_tier"><?php _e( 'AI Quality Level', 'wpforo' ); ?> <span class="required">*</span></label>
						<select id="wpforo-ai-config-quality_tier" name="config[quality_tier]" required>
							<option value="fast"><?php _e( 'Fast - 1 credit per topic', 'wpforo' ); ?></option>
							<option value="balanced"><?php _e( 'Balanced - 2 credits per topic', 'wpforo' ); ?></option>
							<option value="advanced"><?php _e( 'Advanced - 3 credits per topic', 'wpforo' ); ?></option>
							<option value="premium" selected="selected"><?php _e( 'Premium - 4 credits per topic', 'wpforo' ); ?></option>
						</select>
					</div>
				</div>

				<div class="wpforo-ai-form-row">
					<div class="wpforo-ai-form-field">
						<label for="wpforo-ai-config-credit_stop_threshold"><?php _e( 'Credit Stop Threshold', 'wpforo' ); ?></label>
						<input type="number" id="wpforo-ai-config-credit_stop_threshold" name="config[credit_stop_threshold]" min="0" value="500" placeholder="0">
						<p class="description"><?php _e( 'Stop when credits fall below this', 'wpforo' ); ?></p>
					</div>
				</div>

				<div class="wpforo-ai-form-row">
					<div class="wpforo-ai-form-field">
						<label class="wpforo-ai-checkbox-item">
							<input type="checkbox" name="config[auto_pause_on_limit]" value="1" checked>
							<span><?php _e( 'Auto-pause when limit reached', 'wpforo' ); ?></span>
						</label>
					</div>
				</div>

				<div class="wpforo-ai-estimated-credits-notice">
					<span class="dashicons dashicons-info-outline"></span>
					<span class="wpforo-ai-estimated-credits-text">
						<?php _e( 'Estimated monthly usage:', 'wpforo' ); ?>
						<strong class="wpforo-ai-estimated-credits-value">~60 credits</strong>
					</span>
				</div>

				<div class="wpforo-ai-credit-saving-notice">
					<span class="dashicons dashicons-saved"></span>
					<span class="wpforo-ai-credit-saving-text">
						<strong><?php _e( 'Credit Saving Tip:', 'wpforo' ); ?></strong>
						<?php _e( 'Manual Run is available. Create this task as paused, then manually run it whenever you want.', 'wpforo' ); ?>
						<span class="wpforo-ai-manual-run-cost"><?php _e( 'One manual run costs:', 'wpforo' ); ?> <strong class="wpforo-ai-manual-run-cost-value">4 credits</strong></span>
					</span>
				</div>
			</div>

			<!-- Right Column: Content Safety -->
			<div class="wpforo-ai-column wpforo-ai-column-right">
				<h3 class="wpforo-ai-form-section-title">
					<span class="dashicons dashicons-shield"></span>
					<?php _e( 'Content Safety', 'wpforo' ); ?>
				</h3>

				<div class="wpforo-ai-form-row">
					<div class="wpforo-ai-form-field">
						<label class="wpforo-ai-checkbox-item">
							<input type="checkbox" name="config[duplicate_prevention]" value="1" checked>
							<span><?php _e( 'Enable duplicate prevention', 'wpforo' ); ?></span>
						</label>
					</div>
				</div>

				<div class="wpforo-ai-form-row wpforo-ai-duplicate-settings">
					<div class="wpforo-ai-form-field">
						<label for="wpforo-ai-config-similarity_threshold"><?php _e( 'Similarity Threshold', 'wpforo' ); ?></label>
						<input type="range" id="wpforo-ai-config-similarity_threshold" name="config[similarity_threshold]" min="0" max="100" value="75" class="wpforo-ai-range-slider">
						<span class="wpforo-ai-range-value">75%</span>
					</div>
				</div>

				<div class="wpforo-ai-form-row wpforo-ai-duplicate-settings">
					<div class="wpforo-ai-form-field">
						<label for="wpforo-ai-config-duplicate_check_days"><?php _e( 'Check Period', 'wpforo' ); ?></label>
						<input type="number" id="wpforo-ai-config-duplicate_check_days" name="config[duplicate_check_days]" min="0" max="365" value="90" style="width: 80px; display: inline-block;">
						<span class="wpforo-ai-input-suffix"><?php _e( 'days', 'wpforo' ); ?></span>
						<p class="description"><?php _e( '0 = check all topics, or limit to last N days', 'wpforo' ); ?></p>
					</div>
				</div>

				<div class="wpforo-ai-form-row">
					<div class="wpforo-ai-form-field">
						<label><?php _e( 'Topic Status', 'wpforo' ); ?></label>
						<div class="wpforo-ai-radio-group">
							<label class="wpforo-ai-radio-item">
								<input type="radio" name="config[topic_status]" value="0" checked>
								<span class="wpforo-ai-radio-label"><?php _e( 'Published', 'wpforo' ); ?></span>
							</label>
							<label class="wpforo-ai-radio-item">
								<input type="radio" name="config[topic_status]" value="1">
								<span class="wpforo-ai-radio-label"><?php _e( 'Unapproved', 'wpforo' ); ?></span>
							</label>
						</div>
					</div>
				</div>

			</div>
		</div>
	</div>

	<!-- Advanced Options Section (Collapsible) -->
	<div class="wpforo-ai-form-section wpforo-ai-form-section-collapsible">
		<h3 class="wpforo-ai-form-section-title wpforo-ai-collapsible-toggle" aria-expanded="false">
			<span class="dashicons dashicons-admin-settings"></span>
			<?php _e( 'Advanced Options', 'wpforo' ); ?>
			<span class="dashicons dashicons-arrow-down-alt2 wpforo-ai-toggle-icon"></span>
		</h3>

		<div class="wpforo-ai-collapsible-content" style="display: none;">
			<div class="wpforo-ai-form-row">
				<div class="wpforo-ai-form-field wpforo-ai-form-field-half">
					<label for="wpforo-ai-config-topic_prefix"><?php _e( 'Topic Prefix', 'wpforo' ); ?></label>
					<?php if ( function_exists( 'WPF_TOPIC_PREFIX' ) ) : ?>
						<?php
						// wpForo Topic Prefix addon is active - show dropdown
						$prefix_dropdown = WPF_TOPIC_PREFIX()->prefix_list( 'dropdown', 0, '', -1, false );
						?>
						<select id="wpforo-ai-config-topic_prefix_id" name="config[topic_prefix_id]">
							<?php echo $prefix_dropdown; ?>
						</select>
						<input type="hidden" name="config[topic_prefix]" value="">
						<p class="description"><?php _e( 'Select a topic prefix from wpForo Topic Prefix addon', 'wpforo' ); ?></p>
					<?php else : ?>
						<input type="text" id="wpforo-ai-config-topic_prefix" name="config[topic_prefix]" maxlength="50" placeholder="<?php esc_attr_e( 'e.g., [Tutorial]', 'wpforo' ); ?>">
						<input type="hidden" name="config[topic_prefix_id]" value="">
						<p class="description"><?php _e( 'Optional prefix added to topic titles', 'wpforo' ); ?></p>
					<?php endif; ?>
				</div>

				<div class="wpforo-ai-form-field wpforo-ai-form-field-half">
					<label for="wpforo-ai-config-auto_tags"><?php _e( 'Auto-Tags', 'wpforo' ); ?></label>
					<input type="text" id="wpforo-ai-config-auto_tags" name="config[auto_tags]" placeholder="<?php esc_attr_e( 'tag1, tag2, tag3', 'wpforo' ); ?>">
					<p class="description"><?php _e( 'Comma-separated tags to add to topics', 'wpforo' ); ?></p>
				</div>
			</div>

			<div class="wpforo-ai-form-row">
				<div class="wpforo-ai-form-field">
					<label for="wpforo-ai-config-search_keywords"><?php _e( 'Search Keywords for Ideas', 'wpforo' ); ?></label>
					<input type="text" id="wpforo-ai-config-search_keywords" name="config[search_keywords]" placeholder="<?php esc_attr_e( 'e.g., javascript, react, nodejs', 'wpforo' ); ?>">
					<p class="description"><?php _e( 'Keywords to help AI find topic inspiration from your forum content', 'wpforo' ); ?></p>
				</div>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Render Reply Generator configuration form fields
 *
 * @param array $forums Array of forums
 */
function wpforo_ai_render_reply_generator_config( $forums ) {
	?>
	<!-- Target Settings Section -->
	<div class="wpforo-ai-form-section">
		<h3 class="wpforo-ai-form-section-title">
			<span class="dashicons dashicons-admin-comments"></span>
			<?php _e( 'Target Settings', 'wpforo' ); ?>
		</h3>
		<p class="wpforo-ai-section-description"><?php _e( 'Select forums to reply to random topics, or specify exact topic IDs. Both can be combined for random selection between them.', 'wpforo' ); ?></p>

		<div class="wpforo-ai-two-column-layout">
			<!-- Left Column: Forum Selection -->
			<div class="wpforo-ai-column wpforo-ai-column-left">
				<div class="wpforo-ai-form-field">
					<label><?php _e( 'Target Forums', 'wpforo' ); ?></label>
					<div class="wpforo-ai-forum-checklist">
						<?php foreach ( $forums as $forum ) :
							$forum_id = (int) $forum['forumid'];
							$forum_title = esc_html( $forum['title'] );
							$parent_id = (int) $forum['parentid'];
							$is_cat = (int) $forum['is_cat'];
							$item_class = $parent_id > 0 ? 'wpforo-ai-forum-child' : 'wpforo-ai-forum-parent';
							if ( $is_cat ) {
								$item_class .= ' wpforo-ai-forum-category';
							}
							?>
							<label class="wpforo-ai-forum-checkbox-item <?php echo esc_attr( $item_class ); ?>">
								<?php if ( $is_cat ) : ?>
								<input type="checkbox"
									class="forum-checkbox forum-parent-toggle"
									data-forum-id="<?php echo $forum_id; ?>"
									data-parent-id="<?php echo $parent_id; ?>"
									data-is-category="1">
								<?php else : ?>
								<input type="checkbox"
									name="config[reply_target_forum_ids][]"
									value="<?php echo $forum_id; ?>"
									class="forum-checkbox"
									data-forum-id="<?php echo $forum_id; ?>"
									data-parent-id="<?php echo $parent_id; ?>"
									data-is-category="0">
								<?php endif; ?>
								<?php echo $forum_title; ?>
							</label>
						<?php endforeach; ?>
					</div>
					<div class="wpforo-ai-forum-actions">
						<button type="button" class="button button-small wpforo-ai-select-all-forums">
							<?php _e( 'Select All', 'wpforo' ); ?>
						</button>
						<button type="button" class="button button-small wpforo-ai-deselect-all-forums">
							<?php _e( 'Deselect All', 'wpforo' ); ?>
						</button>
					</div>
					<p class="description"><?php _e( 'Random topic from selected forums (excludes private, closed, unapproved)', 'wpforo' ); ?></p>
				</div>
			</div>

			<!-- Right Column: Topic IDs and Date Range -->
			<div class="wpforo-ai-column wpforo-ai-column-right">
				<div class="wpforo-ai-form-field">
					<label for="wpforo-ai-config-target_topic_ids"><?php _e( 'Target Topic IDs', 'wpforo' ); ?></label>
					<textarea id="wpforo-ai-config-target_topic_ids" name="config[target_topic_ids]" rows="3" placeholder="<?php esc_attr_e( 'e.g.: 123, 456, 789', 'wpforo' ); ?>"></textarea>
					<p class="description"><?php _e( 'Specific topic IDs (any status). Separate with commas or new lines.', 'wpforo' ); ?></p>
				</div>

				<div class="wpforo-ai-form-field">
					<label><?php _e( 'Topic Date Range Filter', 'wpforo' ); ?></label>
					<div class="wpforo-ai-tasks-date-range">
						<div class="wpforo-ai-tasks-date-field">
							<label for="wpforo-ai-config-date_from"><?php _e( 'From', 'wpforo' ); ?></label>
							<input type="date" id="wpforo-ai-config-date_from" name="config[date_range_from]">
						</div>
						<div class="wpforo-ai-tasks-date-field">
							<label for="wpforo-ai-config-date_to"><?php _e( 'To', 'wpforo' ); ?></label>
							<input type="date" id="wpforo-ai-config-date_to" name="config[date_range_to]">
						</div>
					</div>
					<p class="description"><?php _e( 'Filter topics by creation date. Works alone (any forum) or with forum selection.', 'wpforo' ); ?></p>
				</div>

                <div class="wpforo-ai-form-field">
                    <label class="wpforo-ai-checkbox-label">
                        <input type="checkbox" name="config[only_not_replied]" value="1">
                        <?php _e( 'Only Not Replied Topics', 'wpforo' ); ?>
                    </label>
                    <p class="description"><?php _e( 'Only select topics that have no replies yet. Works with all filters.', 'wpforo' ); ?></p>
                </div>

				<div class="wpforo-ai-info-box">
					<span class="dashicons dashicons-info"></span>
					<div>
						<strong><?php _e( 'How targeting works:', 'wpforo' ); ?></strong>
						<ul>
							<li><?php _e( 'Forums only = random topic from selected forums', 'wpforo' ); ?></li>
							<li><?php _e( 'Date range only = random topic from any forum in date range', 'wpforo' ); ?></li>
							<li><?php _e( 'Forums + Date range = topics matching both criteria', 'wpforo' ); ?></li>
							<li><?php _e( 'Topic IDs = always included (any status)', 'wpforo' ); ?></li>
						</ul>
						<p style="margin: 8px 0 0; font-size: 11px; color: #646970;"><?php _e( 'Random selection excludes private, closed, and unapproved topics.', 'wpforo' ); ?></p>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Reply Content Settings Section -->
	<div class="wpforo-ai-form-section">
		<h3 class="wpforo-ai-form-section-title">
			<span class="dashicons dashicons-edit-page"></span>
			<?php _e( 'Reply Content Settings', 'wpforo' ); ?>
		</h3>

		<div class="wpforo-ai-form-row">
			<div class="wpforo-ai-form-field wpforo-ai-form-field-half">
				<label for="wpforo-ai-config-reply_style"><?php _e( 'Reply Style', 'wpforo' ); ?> <span class="required">*</span></label>
				<select id="wpforo-ai-config-reply_style" name="config[reply_style]" required>
					<option value="neutral" selected="selected"><?php _e( 'Neutral - Let custom instructions decide', 'wpforo' ); ?></option>
					<option value="helpful_answer"><?php _e( 'Helpful Answer - Direct solution', 'wpforo' ); ?></option>
					<option value="clarifying_questions"><?php _e( 'Clarifying Questions - Ask for details', 'wpforo' ); ?></option>
					<option value="provide_context"><?php _e( 'Provide Context - Background info', 'wpforo' ); ?></option>
					<option value="encourage_discussion"><?php _e( 'Encourage Discussion - Promote conversation', 'wpforo' ); ?></option>
					<option value="step_by_step"><?php _e( 'Step-by-Step Solution - Detailed guide', 'wpforo' ); ?></option>
					<option value="quick_answer"><?php _e( 'Quick Answer - Brief response', 'wpforo' ); ?></option>
					<option value="expert_opinion"><?php _e( 'Expert Opinion - Authoritative view', 'wpforo' ); ?></option>
					<option value="supportive"><?php _e( 'Supportive Response - Empathetic help', 'wpforo' ); ?></option>
					<option value="resource_sharing"><?php _e( 'Resource Sharing - Links/references', 'wpforo' ); ?></option>
					<option value="alternatives"><?php _e( 'Alternative Solutions - Multiple options', 'wpforo' ); ?></option>
					<option value="troubleshooting"><?php _e( 'Troubleshooting Guide - Diagnostic approach', 'wpforo' ); ?></option>
					<option value="best_practice"><?php _e( 'Best Practice Advice - Recommended way', 'wpforo' ); ?></option>
					<option value="experience_sharing"><?php _e( 'Experience Sharing - Personal story', 'wpforo' ); ?></option>
					<option value="detailed_explanation"><?php _e( 'Detailed Explanation - Comprehensive answer', 'wpforo' ); ?></option>
					<option value="summary"><?php _e( 'Summary Response - Condensed info', 'wpforo' ); ?></option>
					<option value="follow_up"><?php _e( 'Follow-up Questions - Dig deeper', 'wpforo' ); ?></option>
					<option value="confirmation"><?php _e( 'Confirmation - Validate understanding', 'wpforo' ); ?></option>
					<option value="constructive_feedback"><?php _e( 'Constructive Feedback - Helpful critique', 'wpforo' ); ?></option>
					<option value="collaborative"><?php _e( 'Collaborative - Work together approach', 'wpforo' ); ?></option>
					<option value="educational"><?php _e( 'Educational - Teaching moment', 'wpforo' ); ?></option>
					<option value="acknowledgment"><?php _e( 'Acknowledgment - Recognize the issue', 'wpforo' ); ?></option>
					<option value="recommendation"><?php _e( 'Recommendation - Suggest specific action', 'wpforo' ); ?></option>
				</select>
			</div>

			<div class="wpforo-ai-form-field wpforo-ai-form-field-half">
				<label for="wpforo-ai-config-reply_tone"><?php _e( 'Reply Tone', 'wpforo' ); ?> <span class="required">*</span></label>
				<select id="wpforo-ai-config-reply_tone" name="config[reply_tone]" required>
					<option value="neutral" selected="selected"><?php _e( 'Neutral - Let custom instructions decide', 'wpforo' ); ?></option>
					<option value="professional"><?php _e( 'Professional - Business-like and formal', 'wpforo' ); ?></option>
					<option value="friendly"><?php _e( 'Friendly - Warm and approachable', 'wpforo' ); ?></option>
					<option value="casual"><?php _e( 'Casual - Relaxed and informal', 'wpforo' ); ?></option>
					<option value="technical"><?php _e( 'Technical - Detailed and precise', 'wpforo' ); ?></option>
					<option value="enthusiastic"><?php _e( 'Enthusiastic - Excited and energetic', 'wpforo' ); ?></option>
					<option value="helpful"><?php _e( 'Helpful - Supportive and guiding', 'wpforo' ); ?></option>
					<option value="authoritative"><?php _e( 'Authoritative - Expert and confident', 'wpforo' ); ?></option>
					<option value="conversational"><?php _e( 'Conversational - Like chatting with a friend', 'wpforo' ); ?></option>
					<option value="educational"><?php _e( 'Educational - Teaching and informative', 'wpforo' ); ?></option>
					<option value="inspirational"><?php _e( 'Inspirational - Motivating and uplifting', 'wpforo' ); ?></option>
					<option value="humorous"><?php _e( 'Humorous - Light-hearted and witty', 'wpforo' ); ?></option>
					<option value="serious"><?php _e( 'Serious - Focused and earnest', 'wpforo' ); ?></option>
					<option value="encouraging"><?php _e( 'Encouraging - Positive and supportive', 'wpforo' ); ?></option>
					<option value="warm"><?php _e( 'Warm - Caring and compassionate', 'wpforo' ); ?></option>
					<option value="direct"><?php _e( 'Direct - Straightforward and to the point', 'wpforo' ); ?></option>
					<option value="thoughtful"><?php _e( 'Thoughtful - Considerate and reflective', 'wpforo' ); ?></option>
					<option value="empathetic"><?php _e( 'Empathetic - Understanding and relatable', 'wpforo' ); ?></option>
					<option value="confident"><?php _e( 'Confident - Self-assured and decisive', 'wpforo' ); ?></option>
					<option value="curious"><?php _e( 'Curious - Inquisitive and engaging', 'wpforo' ); ?></option>
					<option value="playful"><?php _e( 'Playful - Fun and lighthearted', 'wpforo' ); ?></option>
					<option value="sincere"><?php _e( 'Sincere - Genuine and honest', 'wpforo' ); ?></option>
				</select>
			</div>
		</div>

		<div class="wpforo-ai-form-row">
			<div class="wpforo-ai-form-field">
				<label for="wpforo-ai-config-response_guidelines"><?php _e( 'Response Guidelines', 'wpforo' ); ?> <span class="required">*</span></label>
				<div class="wpforo-ai-textarea-with-counter">
					<textarea id="wpforo-ai-config-response_guidelines" name="config[response_guidelines]" rows="3" maxlength="120" required placeholder="<?php esc_attr_e( 'e.g., Be helpful, include links when useful, ask follow-up questions...', 'wpforo' ); ?>" data-char-limit="120"></textarea>
					<span class="wpforo-ai-char-counter"><span class="current">0</span>/120</span>
				</div>
				<p class="description"><?php _e( 'Brief guidelines for AI responses (max 120 characters)', 'wpforo' ); ?></p>
			</div>
		</div>

		<div class="wpforo-ai-form-row">
			<div class="wpforo-ai-form-field wpforo-ai-form-field-half">
				<label for="wpforo-ai-config-reply_length"><?php _e( 'Reply Length', 'wpforo' ); ?></label>
				<select id="wpforo-ai-config-reply_length" name="config[reply_length]">
					<option value="brief" selected="selected"><?php _e( 'Brief (100-200 words)', 'wpforo' ); ?></option>
					<option value="medium"><?php _e( 'Medium (200-400 words)', 'wpforo' ); ?></option>
					<option value="detailed"><?php _e( 'Detailed (400-800 words)', 'wpforo' ); ?></option>
				</select>
                <div>&nbsp;</div>
                <div class="wpforo-ai-form-field wpforo-ai-form-field-half">
                    <label for="wpforo-ai-config-max_replies_per_topic"><?php _e( 'Max Replies Per Topic', 'wpforo' ); ?></label>
                    <input type="number" id="wpforo-ai-config-max_replies_per_topic" name="config[max_replies_per_topic]" min="1" max="10" value="1">
                    <p class="description"><?php _e( 'Maximum AI replies allowed per topic', 'wpforo' ); ?></p>
                </div>
			</div>

			<div class="wpforo-ai-form-field wpforo-ai-form-field-half">
				<label><?php _e( 'Include in Replies', 'wpforo' ); ?></label>
				<div class="wpforo-ai-checkbox-group wpforo-ai-checkbox-group-vertical">
					<label class="wpforo-ai-checkbox-item">
						<input type="checkbox" name="config[reply_include_code]" value="1">
						<span><?php _e( 'Code examples', 'wpforo' ); ?></span>
					</label>
					<label class="wpforo-ai-checkbox-item">
						<input type="checkbox" name="config[reply_include_links]" value="1">
						<span><?php _e( 'Links to documentation', 'wpforo' ); ?></span>
					</label>
					<label class="wpforo-ai-checkbox-item">
						<input type="checkbox" name="config[reply_include_steps]" value="1">
						<span><?php _e( 'Step-by-step solutions', 'wpforo' ); ?></span>
					</label>
					<label class="wpforo-ai-checkbox-item">
						<input type="checkbox" name="config[reply_include_followup]" value="1" checked>
						<span><?php _e( 'Follow-up questions', 'wpforo' ); ?></span>
					</label>
					<label class="wpforo-ai-checkbox-item">
						<input type="checkbox" name="config[reply_include_youtube]" value="1">
						<span><?php _e( 'YouTube video URLs', 'wpforo' ); ?></span>
					</label>
					<label class="wpforo-ai-checkbox-item">
						<input type="checkbox" name="config[reply_include_greeting]" value="1" checked>
						<span><?php _e( 'Personalized greeting (e.g., "Hi John")', 'wpforo' ); ?></span>
					</label>
				</div>
			</div>
		</div>
	</div>

	<!-- Knowledge Source Section -->
	<div class="wpforo-ai-form-section">
		<h3 class="wpforo-ai-form-section-title">
			<span class="dashicons dashicons-database"></span>
			<?php _e( 'Knowledge Source', 'wpforo' ); ?>
		</h3>

		<div class="wpforo-ai-form-row">
			<div class="wpforo-ai-form-field wpforo-ai-form-field-half">
				<label for="wpforo-ai-config-knowledge_source"><?php _e( 'Knowledge Source', 'wpforo' ); ?></label>
				<select id="wpforo-ai-config-knowledge_source" name="config[knowledge_source]">
					<option value="forum_only"><?php _e( 'Forum content only (RAG)', 'wpforo' ); ?></option>
					<option value="forum_and_ai" selected="selected"><?php _e( 'Forum + AI general knowledge', 'wpforo' ); ?></option>
					<option value="forum_and_web"><?php _e( 'Forum + Web Search', 'wpforo' ); ?></option>
					<option value="forum_and_web_and_ai"><?php _e( 'Forum + Web Search + AI knowledge', 'wpforo' ); ?></option>
					<option value="ai_only"><?php _e( 'AI general knowledge only', 'wpforo' ); ?></option>
				</select>
			</div>

			<div class="wpforo-ai-form-field wpforo-ai-form-field-half">
				<label for="wpforo-ai-config-no_content_action"><?php _e( 'If No Relevant Content in Forum Found', 'wpforo' ); ?></label>
				<select id="wpforo-ai-config-no_content_action" name="config[no_content_action]">
					<option value="use_other_sources" selected="selected"><?php _e( 'Use other knowledge sources', 'wpforo' ); ?></option>
					<option value="skip"><?php _e( 'Skip this topic', 'wpforo' ); ?></option>
					<option value="ask_details"><?php _e( 'Ask for more details', 'wpforo' ); ?></option>
				</select>
			</div>
		</div>
	</div>

	<!-- Author Settings & Scheduling Section -->
	<div class="wpforo-ai-form-section">
		<div class="wpforo-ai-two-column-layout">
			<!-- Left Column: Author Settings -->
			<div class="wpforo-ai-column wpforo-ai-column-left">
				<h3 class="wpforo-ai-form-section-title">
					<span class="dashicons dashicons-admin-users"></span>
					<?php _e( 'Author Settings', 'wpforo' ); ?>
				</h3>

				<div class="wpforo-ai-form-row">
					<div class="wpforo-ai-form-field">
						<label for="wpforo-ai-config-reply-author_userid"><?php _e( 'Reply Author', 'wpforo' ); ?></label>
						<div class="wpforo-ai-user-search-wrapper">
							<input type="hidden" id="wpforo-ai-config-reply-author_userid" name="config[author_userid]" class="wpforo-ai-user-id-input">
							<input type="text" id="wpforo-ai-config-reply-author_search" class="wpforo-ai-user-search" placeholder="<?php esc_attr_e( 'Type to search users...', 'wpforo' ); ?>" autocomplete="off">
							<div class="wpforo-ai-user-search-results"></div>
						</div>
						<p class="description"><?php _e( 'Search by username, display name, or email. Only activated users are shown.', 'wpforo' ); ?></p>
					</div>
				</div>

				<div class="wpforo-ai-author-or-separator">
					<span><?php _e( '-- OR --', 'wpforo' ); ?></span>
				</div>

				<div class="wpforo-ai-form-row">
					<div class="wpforo-ai-form-field">
						<label for="wpforo-ai-config-reply-author_groupid"><?php _e( 'Author Usergroup', 'wpforo' ); ?></label>
						<select id="wpforo-ai-config-reply-author_groupid" name="config[author_groupid]" class="wpforo-ai-author-groupid-select">
							<option value=""><?php _e( '-- Select Usergroup --', 'wpforo' ); ?></option>
							<?php
							$usergroups = WPF()->usergroup->get_usergroups();
							if ( ! empty( $usergroups ) ) :
								foreach ( $usergroups as $group ) :
									$gid = intval( $group['groupid'] );
									if ( $gid === 4 ) continue; // Exclude Guest group
									?>
									<option value="<?php echo esc_attr( $gid ); ?>"><?php echo esc_html( $group['name'] ); ?></option>
								<?php endforeach;
							endif;
							?>
						</select>
						<p class="description"><?php _e( 'Randomly selects a user from this group for each reply (avoids replying as the topic author).', 'wpforo' ); ?></p>
					</div>
				</div>

				<div class="wpforo-ai-form-row">
					<div class="wpforo-ai-form-field">
						<label class="wpforo-ai-checkbox-item">
							<input type="checkbox" name="config[show_ai_badge]" value="1">
							<span><?php _e( 'Add AI disclosure notice to generated content', 'wpforo' ); ?></span>
						</label>
						<p class="description"><?php _e( 'Adds a small disclaimer line at the end of AI-generated replies indicating the content was created by AI.', 'wpforo' ); ?></p>
					</div>
				</div>
			</div>

			<!-- Right Column: Scheduling -->
			<div class="wpforo-ai-column wpforo-ai-column-right">
			<h3 class="wpforo-ai-form-section-title">
				<span class="dashicons dashicons-calendar-alt"></span>
				<?php _e( 'Scheduling', 'wpforo' ); ?>
			</h3>

			<div class="wpforo-ai-form-row">
				<div class="wpforo-ai-form-field">
					<label class="wpforo-ai-auto-index-toggle wpforo-ai-run-on-approval-toggle">
						<span class="wpforo-ai-auto-index-label"><?php _e( 'Run on each approved post', 'wpforo' ); ?></span>
						<div class="wpforo-ai-switch">
							<input type="checkbox" id="wpforo-ai-config-reply-run_on_approval" name="config[run_on_approval]" value="1" class="wpforo-ai-run-on-approval-checkbox" data-task-type="reply_generator">
							<span class="wpforo-ai-switch-slider"></span>
						</div>
					</label>
					<p class="description"><?php _e( 'When enabled, task runs automatically when a new post is approved (instead of scheduled cron)', 'wpforo' ); ?></p>
				</div>
			</div>

			<div class="wpforo-ai-form-row wpforo-ai-scheduled-options">
				<div class="wpforo-ai-form-field wpforo-ai-form-field-half">
					<label for="wpforo-ai-config-reply-frequency"><?php _e( 'Frequency', 'wpforo' ); ?> <span class="required">*</span></label>
					<select id="wpforo-ai-config-reply-frequency" name="config[frequency]" required>
						<option value="hourly"><?php _e( 'Every Hour', 'wpforo' ); ?></option>
						<option value="3hours"><?php _e( 'Every 3 Hours', 'wpforo' ); ?></option>
						<option value="6hours"><?php _e( 'Every 6 Hours', 'wpforo' ); ?></option>
						<option value="daily" selected="selected"><?php _e( 'Daily', 'wpforo' ); ?></option>
					</select>
				</div>

				<div class="wpforo-ai-form-field wpforo-ai-form-field-half">
					<label for="wpforo-ai-config-replies_per_run"><?php _e( 'Replies Per Run', 'wpforo' ); ?></label>
					<select id="wpforo-ai-config-replies_per_run" name="config[replies_per_run]">
						<option value="1" selected="selected">1</option>
						<option value="2">2</option>
						<option value="3">3</option>
						<option value="5">5</option>
						<option value="10">10</option>
					</select>
				</div>
			</div>

			<div class="wpforo-ai-form-row wpforo-ai-scheduled-options">
				<div class="wpforo-ai-form-field">
					<label><?php _e( 'Active Days', 'wpforo' ); ?></label>
					<div class="wpforo-ai-checkbox-group wpforo-ai-checkbox-group-inline">
						<?php
						$days = [
							'mon' => __( 'Mon', 'wpforo' ),
							'tue' => __( 'Tue', 'wpforo' ),
							'wed' => __( 'Wed', 'wpforo' ),
							'thu' => __( 'Thu', 'wpforo' ),
							'fri' => __( 'Fri', 'wpforo' ),
							'sat' => __( 'Sat', 'wpforo' ),
							'sun' => __( 'Sun', 'wpforo' ),
						];
						foreach ( $days as $day_key => $day_label ) :
							?>
							<label class="wpforo-ai-checkbox-item">
								<input type="checkbox" name="config[active_days][]" value="<?php echo $day_key; ?>" checked>
								<span><?php echo $day_label; ?></span>
							</label>
						<?php endforeach; ?>
					</div>
					<p class="description"><?php _e( 'Task will only run on selected days', 'wpforo' ); ?></p>
				</div>
			</div>
			</div>
		</div>
	</div>

	<!-- AI Quality & Credits + Quality Control Section -->
	<div class="wpforo-ai-form-section">
		<div class="wpforo-ai-two-column-layout">
			<!-- Left Column: AI Quality & Credits -->
			<div class="wpforo-ai-column wpforo-ai-column-left">
				<h3 class="wpforo-ai-form-section-title">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php _e( 'AI Quality & Credits', 'wpforo' ); ?>
				</h3>

				<div class="wpforo-ai-form-row">
					<div class="wpforo-ai-form-field">
						<label for="wpforo-ai-config-reply-quality_tier"><?php _e( 'AI Quality Level', 'wpforo' ); ?> <span class="required">*</span></label>
						<select id="wpforo-ai-config-reply-quality_tier" name="config[quality_tier]" required>
							<option value="fast"><?php _e( 'Fast - 1 credit per reply', 'wpforo' ); ?></option>
							<option value="balanced"><?php _e( 'Balanced - 2 credits per reply', 'wpforo' ); ?></option>
							<option value="advanced"><?php _e( 'Advanced - 3 credits per reply', 'wpforo' ); ?></option>
							<option value="premium" selected="selected"><?php _e( 'Premium - 4 credits per reply', 'wpforo' ); ?></option>
						</select>
					</div>
				</div>

				<div class="wpforo-ai-form-row">
					<div class="wpforo-ai-form-field">
						<label for="wpforo-ai-config-reply-credit_stop_threshold"><?php _e( 'Credit Stop Threshold', 'wpforo' ); ?></label>
						<input type="number" id="wpforo-ai-config-reply-credit_stop_threshold" name="config[credit_stop_threshold]" min="0" value="500" placeholder="0">
						<p class="description"><?php _e( 'Stop when credits fall below this', 'wpforo' ); ?></p>
					</div>
				</div>

				<div class="wpforo-ai-form-row">
					<div class="wpforo-ai-form-field">
						<label class="wpforo-ai-checkbox-item">
							<input type="checkbox" name="config[auto_pause_on_limit]" value="1" checked>
							<span><?php _e( 'Auto-pause when limit reached', 'wpforo' ); ?></span>
						</label>
					</div>
				</div>

				<div class="wpforo-ai-estimated-credits-notice">
					<span class="dashicons dashicons-info-outline"></span>
					<span class="wpforo-ai-estimated-credits-text">
						<?php _e( 'Estimated monthly usage:', 'wpforo' ); ?>
						<strong class="wpforo-ai-estimated-credits-value">~180 credits</strong>
					</span>
				</div>

				<div class="wpforo-ai-credit-saving-notice">
					<span class="dashicons dashicons-saved"></span>
					<span class="wpforo-ai-credit-saving-text">
						<strong><?php _e( 'Credit Saving Tip:', 'wpforo' ); ?></strong>
						<?php _e( 'Manual Run is available. Create this task as paused, then manually run it whenever you want.', 'wpforo' ); ?>
						<span class="wpforo-ai-manual-run-cost"><?php _e( 'One manual run costs:', 'wpforo' ); ?> <strong class="wpforo-ai-manual-run-cost-value">4 credits</strong></span>
					</span>
				</div>
			</div>

			<!-- Right Column: Content Safety -->
			<div class="wpforo-ai-column wpforo-ai-column-right">
				<h3 class="wpforo-ai-form-section-title">
					<span class="dashicons dashicons-shield"></span>
					<?php _e( 'Content Safety', 'wpforo' ); ?>
				</h3>

				<div class="wpforo-ai-form-row">
					<div class="wpforo-ai-form-field">
						<label class="wpforo-ai-checkbox-item">
							<input type="checkbox" name="config[duplicate_prevention]" value="1" checked>
							<span><?php _e( 'Enable duplicate prevention', 'wpforo' ); ?></span>
						</label>
					</div>
				</div>

				<div class="wpforo-ai-form-row wpforo-ai-duplicate-settings">
					<div class="wpforo-ai-form-field">
						<label for="wpforo-ai-reply-config-similarity_threshold"><?php _e( 'Similarity Threshold', 'wpforo' ); ?></label>
						<input type="range" id="wpforo-ai-reply-config-similarity_threshold" name="config[similarity_threshold]" min="0" max="100" value="75" class="wpforo-ai-range-slider">
						<span class="wpforo-ai-range-value">75%</span>
					</div>
				</div>

				<div class="wpforo-ai-form-row wpforo-ai-duplicate-settings">
					<div class="wpforo-ai-form-field">
						<label for="wpforo-ai-reply-config-duplicate_check_days"><?php _e( 'Check Period', 'wpforo' ); ?></label>
						<input type="number" id="wpforo-ai-reply-config-duplicate_check_days" name="config[duplicate_check_days]" min="0" max="365" value="90" style="width: 80px; display: inline-block;">
						<span class="wpforo-ai-input-suffix"><?php _e( 'days.', 'wpforo' ); ?> (<?php _e( 'Set 0 to check all replies, or limit to last N days.', 'wpforo' ); ?>)</span>
					</div>
				</div>

				<div class="wpforo-ai-form-row">
					<div class="wpforo-ai-form-field">
						<label><?php _e( 'Reply Status', 'wpforo' ); ?></label>
						<div class="wpforo-ai-radio-group">
							<label class="wpforo-ai-radio-item">
								<input type="radio" name="config[reply_status]" value="0" checked>
								<span class="wpforo-ai-radio-label"><?php _e( 'Published', 'wpforo' ); ?></span>
							</label>
							<label class="wpforo-ai-radio-item">
								<input type="radio" name="config[reply_status]" value="1">
								<span class="wpforo-ai-radio-label"><?php _e( 'Unapproved', 'wpforo' ); ?></span>
							</label>
						</div>
					</div>
				</div>

				<div class="wpforo-ai-form-row">
					<div class="wpforo-ai-form-field">
						<label for="reply_strategy"><?php _e( 'Reply Strategy', 'wpforo' ); ?></label>
						<select name="config[reply_strategy]" id="reply_strategy" class="wpforo-ai-select">
							<option value="first_post"><?php _e( 'Reply to first post', 'wpforo' ); ?></option>
							<option value="whole_topic"><?php _e( 'Reply to whole topic', 'wpforo' ); ?></option>
							<option value="last_post"><?php _e( 'Reply to last post', 'wpforo' ); ?></option>
						</select>
						<p class="description"><?php _e( 'Choose how AI generates replies:', 'wpforo' ); ?></p>
						<ul class="description" style="margin-top: 5px; margin-left: 20px; list-style-type: disc;">
							<li style="margin-bottom: 0px;"><?php _e( '<strong>Reply to first post</strong> - Generates a reply based only on the first post content', 'wpforo' ); ?></li>
							<li style="margin-bottom: 0px;"><?php _e( '<strong>Reply to whole topic</strong> - Considers the topic first post and all replies for context', 'wpforo' ); ?></li>
							<li style="margin-bottom: 0px;"><?php _e( '<strong>Reply to last post</strong> - Responds to the last post while understanding the sub-thread context', 'wpforo' ); ?></li>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Advanced Options Section (Collapsible) - Hidden for future use -->
	<div class="wpforo-ai-form-section wpforo-ai-form-section-collapsible" style="display: none;">
		<h3 class="wpforo-ai-form-section-title wpforo-ai-collapsible-toggle" aria-expanded="false">
			<span class="dashicons dashicons-admin-settings"></span>
			<?php _e( 'Advanced Options', 'wpforo' ); ?>
			<span class="dashicons dashicons-arrow-down-alt2 wpforo-ai-toggle-icon"></span>
		</h3>
		<div class="wpforo-ai-collapsible-content" style="display: none;">
		</div>
	</div>
	<?php
}

/**
 * Render Tag Maintenance configuration form fields
 *
 * @param array $forums Array of forums
 */
function wpforo_ai_render_tag_maintenance_config( $forums ) {
	?>
	<!-- Target Settings Section -->
	<div class="wpforo-ai-form-section">
		<h3 class="wpforo-ai-form-section-title">
			<span class="dashicons dashicons-tag"></span>
			<?php _e( 'Target Settings', 'wpforo' ); ?>
		</h3>
		<p class="wpforo-ai-section-description"><?php _e( 'Select forums to process topic tags, or specify exact topic IDs. Both can be combined.', 'wpforo' ); ?></p>

		<div class="wpforo-ai-two-column-layout">
			<!-- Left Column: Forum Selection -->
			<div class="wpforo-ai-column wpforo-ai-column-left">
				<div class="wpforo-ai-form-field">
					<label><?php _e( 'Target Forums', 'wpforo' ); ?></label>
					<div class="wpforo-ai-forum-checklist">
						<?php foreach ( $forums as $forum ) :
							$forum_id = (int) $forum['forumid'];
							$forum_title = esc_html( $forum['title'] );
							$parent_id = (int) $forum['parentid'];
							$is_cat = (int) $forum['is_cat'];
							$item_class = $parent_id > 0 ? 'wpforo-ai-forum-child' : 'wpforo-ai-forum-parent';
							if ( $is_cat ) {
								$item_class .= ' wpforo-ai-forum-category';
							}
							?>
							<label class="wpforo-ai-forum-checkbox-item <?php echo esc_attr( $item_class ); ?>">
								<?php if ( $is_cat ) : ?>
								<input type="checkbox"
									class="forum-checkbox forum-parent-toggle"
									data-forum-id="<?php echo $forum_id; ?>"
									data-parent-id="<?php echo $parent_id; ?>"
									data-is-category="1">
								<?php else : ?>
								<input type="checkbox"
									name="config[tag_target_forum_ids][]"
									value="<?php echo $forum_id; ?>"
									class="forum-checkbox"
									data-forum-id="<?php echo $forum_id; ?>"
									data-parent-id="<?php echo $parent_id; ?>"
									data-is-category="0">
								<?php endif; ?>
								<?php echo $forum_title; ?>
							</label>
						<?php endforeach; ?>
					</div>
					<div class="wpforo-ai-forum-actions">
						<button type="button" class="button button-small wpforo-ai-select-all-forums">
							<?php _e( 'Select All', 'wpforo' ); ?>
						</button>
						<button type="button" class="button button-small wpforo-ai-deselect-all-forums">
							<?php _e( 'Deselect All', 'wpforo' ); ?>
						</button>
					</div>
					<p class="description"><?php _e( 'Topics from selected forums (excludes private, closed, unapproved)', 'wpforo' ); ?></p>
				</div>
			</div>

			<!-- Right Column: Topic IDs and Date Range -->
			<div class="wpforo-ai-column wpforo-ai-column-right">
				<div class="wpforo-ai-form-field">
					<label for="wpforo-ai-tag-config-target_topic_ids"><?php _e( 'Target Topic IDs', 'wpforo' ); ?></label>
					<textarea id="wpforo-ai-tag-config-target_topic_ids" name="config[target_topic_ids]" rows="3" placeholder="<?php esc_attr_e( 'e.g.: 123, 456, 789', 'wpforo' ); ?>"></textarea>
					<p class="description"><?php _e( 'Specific topic IDs (any status). Separate with commas or new lines.', 'wpforo' ); ?></p>
				</div>

				<div class="wpforo-ai-form-field">
					<label><?php _e( 'Topic Date Range Filter', 'wpforo' ); ?></label>
					<div class="wpforo-ai-tasks-date-range">
						<div class="wpforo-ai-tasks-date-field">
							<label for="wpforo-ai-tag-config-date_from"><?php _e( 'From', 'wpforo' ); ?></label>
							<input type="date" id="wpforo-ai-tag-config-date_from" name="config[date_range_from]">
						</div>
						<div class="wpforo-ai-tasks-date-field">
							<label for="wpforo-ai-tag-config-date_to"><?php _e( 'To', 'wpforo' ); ?></label>
							<input type="date" id="wpforo-ai-tag-config-date_to" name="config[date_range_to]">
						</div>
					</div>
					<p class="description"><?php _e( 'Filter topics by creation date. Works alone (any forum) or with forum selection.', 'wpforo' ); ?></p>
				</div>

				<div class="wpforo-ai-form-field">
					<label class="wpforo-ai-checkbox-label">
						<input type="checkbox" name="config[only_not_tagged]" value="1">
						<?php _e( 'Only Topics Without Tags', 'wpforo' ); ?>
					</label>
					<p class="description"><?php _e( 'Only select topics that have no tags yet. Works with all filters.', 'wpforo' ); ?></p>
				</div>

				<div class="wpforo-ai-info-box">
					<span class="dashicons dashicons-info"></span>
					<div>
						<strong><?php _e( 'How targeting works:', 'wpforo' ); ?></strong>
						<ul>
							<li><?php _e( 'Forums only = topics from selected forums', 'wpforo' ); ?></li>
							<li><?php _e( 'Date range only = topics from any forum in date range', 'wpforo' ); ?></li>
							<li><?php _e( 'Forums + Date range = topics matching both criteria', 'wpforo' ); ?></li>
							<li><?php _e( 'Topic IDs = always included (any status)', 'wpforo' ); ?></li>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Tag Options Section -->
	<div class="wpforo-ai-form-section">
		<h3 class="wpforo-ai-form-section-title">
			<span class="dashicons dashicons-admin-settings"></span>
			<?php _e( 'Tag Options', 'wpforo' ); ?>
		</h3>

		<div class="wpforo-ai-two-column-layout">
			<!-- Left Column: Tag Generation Settings -->
			<div class="wpforo-ai-column wpforo-ai-column-left">
				<div class="wpforo-ai-form-field">
					<label for="wpforo-ai-tag-config-max_tags"><?php _e( 'Maximum Tags Per Topic', 'wpforo' ); ?></label>
					<select id="wpforo-ai-tag-config-max_tags" name="config[max_tags]">
						<option value="1">1</option>
						<option value="2">2</option>
						<option value="3">3</option>
						<option value="4">4</option>
						<option value="5" selected="selected">5</option>
					</select>
					<p class="description"><?php _e( 'Maximum number of tags to generate for each topic', 'wpforo' ); ?></p>
				</div>

				<div class="wpforo-ai-form-field">
					<label class="wpforo-ai-checkbox-label">
						<input type="checkbox" name="config[preserve_existing]" value="1" checked>
						<?php _e( 'Preserve Existing Tags', 'wpforo' ); ?>
					</label>
					<p class="description"><?php _e( 'Keep existing tags and add new ones. If unchecked, existing tags will be replaced.', 'wpforo' ); ?></p>
				</div>

				<div class="wpforo-ai-form-field">
					<label class="wpforo-ai-checkbox-label">
						<input type="checkbox" name="config[maintain_vocabulary]" value="1" checked>
						<?php _e( 'Maintain Tag Vocabulary', 'wpforo' ); ?>
					</label>
					<p class="description"><?php _e( 'Prefer existing forum tags to maintain consistent vocabulary', 'wpforo' ); ?></p>
				</div>
			</div>

			<!-- Right Column: Tag Cleaning Settings -->
			<div class="wpforo-ai-column wpforo-ai-column-right">
				<div class="wpforo-ai-form-field">
					<label class="wpforo-ai-checkbox-label">
						<input type="checkbox" name="config[remove_duplicates]" value="1" checked>
						<?php _e( 'Remove Duplicate Tags', 'wpforo' ); ?>
					</label>
					<p class="description"><?php _e( 'Identify and remove duplicate or near-duplicate tags', 'wpforo' ); ?></p>
				</div>

				<div class="wpforo-ai-form-field">
					<label class="wpforo-ai-checkbox-label">
						<input type="checkbox" name="config[remove_irrelevant]" value="1" checked>
						<?php _e( 'Remove Irrelevant Tags', 'wpforo' ); ?>
					</label>
					<p class="description"><?php _e( 'Remove tags that are not relevant to the topic content', 'wpforo' ); ?></p>
				</div>

				<div class="wpforo-ai-form-field">
					<label class="wpforo-ai-checkbox-label">
						<input type="checkbox" name="config[lowercase]" value="1">
						<?php _e( 'Lowercase All Tags', 'wpforo' ); ?>
					</label>
					<p class="description"><?php _e( 'Convert all tags to lowercase for consistency', 'wpforo' ); ?></p>
				</div>
			</div>
		</div>
	</div>

	<!-- Scheduling Section -->
	<div class="wpforo-ai-form-section">
		<h3 class="wpforo-ai-form-section-title">
			<span class="dashicons dashicons-calendar-alt"></span>
			<?php _e( 'Scheduling', 'wpforo' ); ?>
		</h3>

		<div class="wpforo-ai-form-row">
			<div class="wpforo-ai-form-field">
				<label class="wpforo-ai-auto-index-toggle wpforo-ai-run-on-approval-toggle">
					<span class="wpforo-ai-auto-index-label"><?php _e( 'Run on each approved topic', 'wpforo' ); ?></span>
					<div class="wpforo-ai-switch">
						<input type="checkbox" id="wpforo-ai-config-tag-run_on_approval" name="config[run_on_approval]" value="1" class="wpforo-ai-run-on-approval-checkbox" data-task-type="tag_generator">
						<span class="wpforo-ai-switch-slider"></span>
					</div>
				</label>
				<p class="description"><?php _e( 'When enabled, task runs automatically when a new topic is approved (instead of scheduled cron)', 'wpforo' ); ?></p>
			</div>
		</div>

		<div class="wpforo-ai-form-row wpforo-ai-scheduled-options">
			<div class="wpforo-ai-form-field wpforo-ai-form-field-third">
				<label for="wpforo-ai-tag-config-frequency"><?php _e( 'Frequency', 'wpforo' ); ?> <span class="required">*</span></label>
				<select id="wpforo-ai-tag-config-frequency" name="config[frequency]" required>
					<option value="hourly"><?php _e( 'Every Hour', 'wpforo' ); ?></option>
					<option value="3hours"><?php _e( 'Every 3 Hours', 'wpforo' ); ?></option>
					<option value="6hours"><?php _e( 'Every 6 Hours', 'wpforo' ); ?></option>
					<option value="daily" selected="selected"><?php _e( 'Daily', 'wpforo' ); ?></option>
				</select>
			</div>

			<div class="wpforo-ai-form-field wpforo-ai-form-field-third">
				<label for="wpforo-ai-tag-config-topics_per_run"><?php _e( 'Topics Per Run', 'wpforo' ); ?></label>
				<input type="number" id="wpforo-ai-tag-config-topics_per_run" name="config[topics_per_run]" min="1" max="50" value="20">
				<p class="description"><?php _e( '1-50 topics processed per run', 'wpforo' ); ?></p>
			</div>
		</div>

		<div class="wpforo-ai-form-row wpforo-ai-scheduled-options">
			<div class="wpforo-ai-form-field">
				<label><?php _e( 'Active Days', 'wpforo' ); ?></label>
				<div class="wpforo-ai-checkbox-group wpforo-ai-checkbox-group-inline">
					<?php
					$days = [
						'mon' => __( 'Mon', 'wpforo' ),
						'tue' => __( 'Tue', 'wpforo' ),
						'wed' => __( 'Wed', 'wpforo' ),
						'thu' => __( 'Thu', 'wpforo' ),
						'fri' => __( 'Fri', 'wpforo' ),
						'sat' => __( 'Sat', 'wpforo' ),
						'sun' => __( 'Sun', 'wpforo' ),
					];
					foreach ( $days as $day_key => $day_label ) :
						?>
						<label class="wpforo-ai-checkbox-item">
							<input type="checkbox" name="config[active_days][]" value="<?php echo $day_key; ?>" checked>
							<span><?php echo $day_label; ?></span>
						</label>
					<?php endforeach; ?>
				</div>
				<p class="description"><?php _e( 'Task will only run on selected days', 'wpforo' ); ?></p>
			</div>
		</div>
	</div>

	<!-- AI Quality & Credits Section -->
	<div class="wpforo-ai-form-section">
		<h3 class="wpforo-ai-form-section-title">
			<span class="dashicons dashicons-admin-generic"></span>
			<?php _e( 'AI Quality & Credits', 'wpforo' ); ?>
		</h3>

		<div class="wpforo-ai-form-row">
			<div class="wpforo-ai-form-field wpforo-ai-form-field-half">
				<label for="wpforo-ai-tag-config-quality_tier"><?php _e( 'AI Quality Level', 'wpforo' ); ?> <span class="required">*</span></label>
				<select id="wpforo-ai-tag-config-quality_tier" name="config[quality_tier]" required>
					<option value="fast"><?php _e( 'Fast - 1 credit per run', 'wpforo' ); ?></option>
					<option value="balanced"><?php _e( 'Balanced - 2 credits per run', 'wpforo' ); ?></option>
					<option value="advanced" selected="selected"><?php _e( 'Advanced - 3 credits per run (recommended for 20+ topics)', 'wpforo' ); ?></option>
					<option value="premium"><?php _e( 'Premium - 4 credits per run (recommended for 20+ topics)', 'wpforo' ); ?></option>
				</select>
				<p class="description"><?php _e( 'All topics in one run are processed in a single AI call', 'wpforo' ); ?></p>
			</div>

			<div class="wpforo-ai-form-field wpforo-ai-form-field-half">
				<label for="wpforo-ai-tag-config-credit_stop_threshold"><?php _e( 'Credit Stop Threshold', 'wpforo' ); ?></label>
				<input type="number" id="wpforo-ai-tag-config-credit_stop_threshold" name="config[credit_stop_threshold]" min="0" value="500" placeholder="0">
				<p class="description"><?php _e( 'Stop when credits fall below this', 'wpforo' ); ?></p>
			</div>
		</div>

		<div class="wpforo-ai-form-row">
			<div class="wpforo-ai-form-field">
				<label class="wpforo-ai-checkbox-item">
					<input type="checkbox" name="config[auto_pause_on_limit]" value="1" checked>
					<span><?php _e( 'Auto-pause when limit reached', 'wpforo' ); ?></span>
				</label>
			</div>
		</div>

		<div class="wpforo-ai-estimated-credits-notice">
			<span class="dashicons dashicons-info-outline"></span>
			<span class="wpforo-ai-estimated-credits-text">
				<?php _e( 'Estimated monthly usage:', 'wpforo' ); ?>
				<strong class="wpforo-ai-estimated-credits-value">~120 credits</strong>
			</span>
		</div>

		<div class="wpforo-ai-credit-saving-notice">
			<span class="dashicons dashicons-saved"></span>
			<span class="wpforo-ai-credit-saving-text">
				<strong><?php _e( 'Credit Saving Tip:', 'wpforo' ); ?></strong>
				<?php _e( 'Manual Run is available. Create this task as paused, then manually run it whenever you want.', 'wpforo' ); ?>
				<span class="wpforo-ai-manual-run-cost"><?php _e( 'One manual run costs:', 'wpforo' ); ?> <strong class="wpforo-ai-manual-run-cost-value">4 credits</strong></span>
			</span>
		</div>
	</div>
	<?php
}
