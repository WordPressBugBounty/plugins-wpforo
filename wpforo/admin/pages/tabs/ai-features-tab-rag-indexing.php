<?php
/**
 * AI Features - AI Content Indexing Tab
 *
 * @package wpForo
 * @subpackage Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render AI Content Indexing tab content
 *
 * @param bool  $is_connected Whether tenant is connected to AI service
 * @param array $status       Tenant status data from API
 */
function wpforo_ai_render_rag_indexing_tab( $is_connected, $status ) {
	if ( ! $is_connected ) {
		?>
		<div class="wpforo-ai-box wpforo-ai-not-connected-notice">
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-status-badge status-warning">
					<span class="dashicons dashicons-warning"></span>
					<?php _e( 'Not Connected', 'wpforo' ); ?>
				</div>
				<p><?php _e( 'Please connect to wpForo AI API  first in the Overview tab to enable AI Content Indexing.', 'wpforo' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpforo-ai&tab=overview' ) ); ?>" class="button button-primary">
					<?php _e( 'Go to Overview', 'wpforo' ); ?>
				</a>
			</div>
		</div>
		<?php
		return;
	}

	// Get all boards and determine current board
	$boards = WPF()->board->get_boards();
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
						'tab'     => 'rag_indexing',
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

	// Use VectorStorageManager abstraction for all statistics
	$storage_manager = WPF()->vector_storage->for_board( $current_boardid );
	$storage_mode = $storage_manager->get_storage_mode();

	// Get unified indexing stats (works for both local and cloud)
	$indexing_stats = $storage_manager->get_indexing_stats();

	$total_indexed     = (int) ( $indexing_stats['total_indexed'] ?? 0 );
	$total_topics      = (int) ( $indexing_stats['total_topics'] ?? 0 );
	$indexing_progress = (int) ( $indexing_stats['indexing_progress'] ?? 0 );
	$is_indexing       = (bool) ( $indexing_stats['is_indexing'] ?? false );
	$last_indexed_at   = $indexing_stats['last_indexed_at'] ?? null;
	$storage_size_mb   = $indexing_stats['storage_size_mb'] ?? null;

	// Check for pending WP Cron jobs
	$pending_jobs_info = $storage_manager->get_pending_cron_jobs();
	$has_pending_cron_jobs = $pending_jobs_info['has_pending_jobs'];
	$pending_topics_count_early = $pending_jobs_info['pending_topics'];

	// Only show the spinner when actually indexing (backend is processing or cron batch is running).
	// Queued topics waiting for their scheduled time should NOT trigger the spinner.
	$is_processing = $is_indexing || $pending_jobs_info['is_actively_processing'];

	// Get storage recommendation
	$recommendation = $storage_manager->get_storage_recommendation();

	// Local stats for display (only relevant in local mode)
	$local_stats = $storage_manager->is_local_mode() ? [
		'total_embeddings' => $total_indexed,
		'total_topics'     => $total_topics,
		'storage_size_mb'  => $storage_size_mb,
	] : [];

	?>
	<div class="wpforo-ai-rag-indexing">

        <!-- RAG Status Box -->
        <div class="wpforo-ai-box wpforo-ai-rag-status-box">
            <div class="wpforo-ai-box-header">
                <h2><?php _e( 'Indexing Status', 'wpforo' ); ?></h2>
                <div class="wpforo-ai-header-actions">
                    <button type="button" class="button button-small wpforo-ai-refresh-rag-status" data-loading-text="<?php esc_attr_e( 'Refreshing...', 'wpforo' ); ?>">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e( 'Refresh Status', 'wpforo' ); ?>
                    </button>
                </div>
            </div>
            <div class="wpforo-ai-box-body">
                <div class="wpforo-ai-rag-stats">
                    <div class="rag-stat-item">
                        <div class="stat-icon">
                            <span class="dashicons dashicons-format-chat"></span>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value" id="rag-total-topics"><?php echo number_format( $total_topics ); ?><?php if ( $pending_topics_count_early > 0 ) : ?> <small class="rag-queued-count">| <?php echo number_format( $pending_topics_count_early ); ?> <?php _e( 'queued...', 'wpforo' ); ?></small><?php endif; ?></div>
                            <div class="stat-label"><?php _e( 'Total Threads Indexed', 'wpforo' ); ?></div>
                        </div>
                    </div>

                    <div class="rag-stat-item">
                        <div class="stat-icon">
                            <span class="dashicons dashicons-<?php echo $is_processing ? 'update-alt wpforo-rag-status-spin' : 'saved'; ?>"></span>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value <?php echo $is_processing ? 'status-active' : 'status-idle'; ?>" id="rag-indexing-status">
                                <?php
                                if ( $is_processing ) {
                                    _e( 'Indexing...', 'wpforo' );
                                } else {
                                    _e( 'Idle', 'wpforo' );
                                }
                                ?>
                            </div>
                            <div class="stat-label"><?php _e( 'Current Status', 'wpforo' ); ?></div>
                        </div>
                    </div>

                    <?php if ( $last_indexed_at ) : ?>
                        <div class="rag-stat-item">
                            <div class="stat-icon">
                                <span class="dashicons dashicons-clock"></span>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value"><?php echo esc_html( wpforo_ai_format_date( $last_indexed_at ) ); ?></div>
                                <div class="stat-label"><?php _e( 'Last Indexed', 'wpforo' ); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

		<!-- Storage Settings Box (Business+ plan required for cloud storage) -->
		<?php
		// Cloud storage requires Business plan or higher
		$wpf_ai_cloud_storage_available = isset( WPF()->ai_client ) && WPF()->ai_client->is_feature_available( 'vector_db_cloud_storage' );
		if ( $wpf_ai_cloud_storage_available ) :
		?>
		<div class="wpforo-ai-box wpforo-ai-storage-settings-box">
			<div class="wpforo-ai-box-header">
				<h2><?php _e( 'Storage Settings', 'wpforo' ); ?></h2>
			</div>
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-storage-options">
					<div class="wpforo-ai-storage-toggle">
						<label class="wpforo-ai-toggle-label">
							<span class="wpforo-ai-toggle-text"><?php _e( 'Storage Mode', 'wpforo' ); ?></span>
							<div class="wpforo-ai-toggle-switch">
								<input type="radio" name="wpforo_ai_storage_mode" value="local" id="storage-mode-local" <?php checked( $storage_mode, 'local' ); ?>>
								<label for="storage-mode-local" class="wpforo-ai-storage-option <?php echo $storage_mode === 'local' ? 'active' : ''; ?>">
									<span class="dashicons dashicons-database"></span>
									<?php _e( 'Local (WordPress)', 'wpforo' ); ?>
								</label>
								<input type="radio" name="wpforo_ai_storage_mode" value="cloud" id="storage-mode-cloud" <?php checked( $storage_mode, 'cloud' ); ?>>
								<label for="storage-mode-cloud" class="wpforo-ai-storage-option <?php echo $storage_mode === 'cloud' ? 'active' : ''; ?>">
									<span class="dashicons dashicons-cloud"></span>
									<?php _e( 'Cloud (gVectors on AWS)', 'wpforo' ); ?>
								</label>
							</div>
						</label>
					</div>

					<div class="wpforo-ai-storage-info">
						<?php if ( $storage_mode === 'local' ) : ?>
							<div class="wpforo-ai-storage-recommendation status-<?php echo esc_attr( $recommendation['status'] ); ?>">
								<span class="dashicons dashicons-<?php echo esc_attr( $recommendation['icon'] ); ?>"></span>
								<span><?php echo esc_html( $recommendation['message'] ); ?></span>
							</div>
							<?php if ( ! empty( $local_stats ) ) : ?>
								<div class="wpforo-ai-storage-stats">
									<span class="stat-item">
										<strong id="local-total-embeddings"><?php echo number_format( $local_stats['total_embeddings'] ); ?></strong>
										<?php _e( 'vectors', 'wpforo' ); ?>
									</span>
									<span class="stat-item">
										<strong id="local-total-topics"><?php echo number_format( $local_stats['total_topics'] ); ?></strong>
										<?php _e( 'topics', 'wpforo' ); ?>
									</span>
									<span class="stat-item">
										<strong id="local-storage-size"><?php echo $local_stats['total_embeddings'] > 0 ? $local_stats['storage_size_mb'] : '0'; ?> MB</strong>
										<?php _e( 'storage', 'wpforo' ); ?>
									</span>
								</div>
							<?php endif; ?>
						<?php else : ?>
							<div class="wpforo-ai-storage-recommendation status-info">
								<span class="dashicons dashicons-cloud"></span>
								<span><?php _e( 'Vectors are stored in gVectors AI Services on AWS for optimal performance with large datasets.', 'wpforo' ); ?></span>
							</div>
						<?php endif; ?>
					</div>

					<p class="description">
						<?php _e( 'Local storage keeps all data within your WordPress database. Recommended for forums with up to 100,000 posts.', 'wpforo' ); ?>
						<br>
						<?php _e( 'Cloud storage (gVectors AI Services on AWS Cloud) provides faster search performance for large forums but requires sharing data with our API.', 'wpforo' ); ?>
					</p>
				</div>
			</div>
		</div>
		<?php endif; // End Storage Settings plan check ?>

		<!-- Manual Content Indexing Box -->
		<div class="wpforo-ai-box wpforo-ai-manual-ingest-box">
			<div class="wpforo-ai-box-header">
				<h2><?php _e( 'Forum Content Indexing', 'wpforo' ); ?></h2>
				<div class="wpforo-ai-header-actions">
					<?php
					// Get auto-indexing option (board-specific)
					$auto_indexing_enabled = (bool) wpforo_get_option( 'ai_auto_indexing_enabled', 0 );

					// Get image indexing option (board-specific, Professional+ only)
					$image_indexing_enabled = (bool) wpforo_get_option( 'ai_image_indexing_enabled', 0 );

					// Get document indexing option (board-specific, Professional+ only)
					$document_indexing_enabled = (bool) wpforo_get_option( 'ai_document_indexing_enabled', 0 );

					// Check if user has eligible plan for image/document indexing
					$plan = isset( $status['subscription']['plan'] ) ? strtolower( $status['subscription']['plan'] ) : '';
					$has_image_plan = in_array( $plan, [ 'professional', 'business', 'enterprise' ], true );
					$has_document_plan = in_array( $plan, [ 'professional', 'business', 'enterprise' ], true );
					?>
					<label class="wpforo-ai-auto-index-toggle" title="<?php esc_attr_e( 'Include images in indexing (Professional/Business/Enterprise only). Posts with images will consume +1 credit.', 'wpforo' ); ?>">
						<span class="wpforo-ai-auto-index-label">
							<?php _e( 'Index Images', 'wpforo' ); ?>
							<?php if ( ! $has_image_plan ) : ?>
								<span class="wpforo-ai-plan-badge" title="<?php esc_attr_e( 'Requires Professional, Business or Enterprise plan', 'wpforo' ); ?>">[ Professional+ Plan ]</span>
							<?php endif; ?>
						</span>
						<div class="wpforo-ai-switch">
							<input type="checkbox" id="wpforo-ai-image-indexing" name="ai_image_indexing_enabled" value="1" <?php checked( $image_indexing_enabled ); ?> data-board-id="<?php echo esc_attr( $current_boardid ); ?>" <?php disabled( ! $has_image_plan ); ?>>
							<span class="wpforo-ai-switch-slider"></span>
						</div>
					</label>
					<label class="wpforo-ai-auto-index-toggle" title="<?php esc_attr_e( 'Include document attachments (PDF, DOCX, etc.) in indexing (Professional+ only). Credit cost: 1 per document page.', 'wpforo' ); ?>">
						<span class="wpforo-ai-auto-index-label">
							<?php _e( 'Index Documents', 'wpforo' ); ?>
							<?php if ( ! $has_document_plan ) : ?>
								<span class="wpforo-ai-plan-badge" title="<?php esc_attr_e( 'Requires Professional, Business or Enterprise plan', 'wpforo' ); ?>">[ Professional+ Plan ]</span>
							<?php endif; ?>
						</span>
						<div class="wpforo-ai-switch">
							<input type="checkbox" id="wpforo-ai-document-indexing" name="ai_document_indexing_enabled" value="1" <?php checked( $document_indexing_enabled ); ?> data-board-id="<?php echo esc_attr( $current_boardid ); ?>" <?php disabled( ! $has_document_plan ); ?>>
							<span class="wpforo-ai-switch-slider"></span>
						</div>
					</label>
					<label class="wpforo-ai-auto-index-toggle" title="<?php esc_attr_e( 'Automatically index new and approved topics', 'wpforo' ); ?>">
						<span class="wpforo-ai-auto-index-label"><?php _e( 'Automatically index all new approved topics', 'wpforo' ); ?></span>
						<div class="wpforo-ai-switch">
							<input type="checkbox" id="wpforo-ai-auto-indexing" name="ai_auto_indexing_enabled" value="1" <?php checked( $auto_indexing_enabled ); ?> data-board-id="<?php echo esc_attr( $current_boardid ); ?>">
							<span class="wpforo-ai-switch-slider"></span>
						</div>
					</label>
				</div>
			</div>
			<div class="wpforo-ai-box-body">
				<?php
				// Get total topics count (transient-cached, board+storage specific)
				$_ttc_cache_key = 'wpforo_ai_ttc_' . $current_boardid . '_' . $storage_mode;
				$total_topics_count = get_transient( $_ttc_cache_key );
				if ( false === $total_topics_count ) {
					$total_topics_count = (int) WPF()->db->get_var(
						"SELECT COUNT(*) FROM `" . WPF()->tables->topics . "`"
					);
					set_transient( $_ttc_cache_key, $total_topics_count, 10 * MINUTE_IN_SECONDS );
				}
				$total_topics_count = (int) $total_topics_count;

				// Get available credits from status
				$subscription = isset( $status['subscription'] ) && is_array( $status['subscription'] ) ? $status['subscription'] : [];
				$credits_remaining = isset( $subscription['credits_remaining'] ) ? (int) $subscription['credits_remaining'] : 0;

				// Calculate remaining topics to index (not already indexed)
				// Note: $total_topics = unique topics with embeddings, $total_indexed = total embeddings (can be > topics due to chunking)
				$remaining_to_index = max( 0, $total_topics_count - $total_topics );

				// Calculate how many topics can be indexed with available credits
				# $credits_needed = $total_topics_count; // Max credits if indexing from scratch
				$can_index_count = min( $remaining_to_index, $credits_remaining );
				$has_enough_credits = $credits_remaining >= $remaining_to_index;
				$all_indexed = $remaining_to_index === 0;
				?>

				<!-- Index All Section -->
				<div class="wpforo-ai-index-all-section">
					<div class="wpforo-ai-index-all-content">
						<div class="wpforo-ai-index-all-info">
							<h3>
								<span class="dashicons dashicons-database-view"></span>
								<?php _e( 'Full Content Indexing', 'wpforo' ); ?>
							</h3>
							<p class="description">
								<?php _e( 'Index all forum topics in one click. This will queue all topics for AI features. Only new or modified topics will consume credits - unchanged topics are skipped automatically.', 'wpforo' ); ?>
							</p>
							<p class="description" style="color: #2e7d32;">
								<?php _e( 'All new topics and replies are automatically indexed. If a topic or post is unapproved, the indexing process will only start when a moderator has approved them.', 'wpforo' ); ?>
							</p>
						</div>

						<div class="wpforo-ai-index-all-stats">
							<div class="stat-item">
								<span class="stat-label"><?php _e( 'Total Topics:', 'wpforo' ); ?></span>
								<strong class="stat-value" id="index-total-topics-count"><?php echo number_format( $total_topics_count ); ?></strong>
							</div>
							<div class="stat-item">
								<span class="stat-label"><?php _e( 'Total Indexed:', 'wpforo' ); ?></span>
								<strong class="stat-value stat-indexed" id="index-total-indexed"><?php echo number_format( $total_topics ); ?></strong>
							</div>
							<div class="stat-item">
								<span class="stat-label"><?php _e( 'Remaining to Index:', 'wpforo' ); ?></span>
								<strong class="stat-value" id="index-remaining">
									<?php echo number_format( $remaining_to_index ); ?>
								</strong>
							</div>
							<div class="stat-item">
								<span class="stat-label"><?php _e( 'Credits Available:', 'wpforo' ); ?></span>
								<strong class="stat-value" id="index-credits-available">
									<?php echo number_format( $credits_remaining ); ?>
								</strong>
							</div>
						</div>

					</div>

					<?php
					// Use already-computed pending jobs info from earlier
					$has_pending_jobs = $has_pending_cron_jobs;
					$pending_topics_count = $pending_topics_count_early;
					?>

					<?php if ( $has_pending_jobs ) : ?>
						<div class="wpforo-ai-credit-notice wpforo-ai-notice-info">
							<span class="dashicons dashicons-backup"></span>
							<p>
								<?php
								printf(
									__( 'Background processing in progress: %s topics are queued for indexing.', 'wpforo' ),
									'<strong>' . number_format( $pending_topics_count ) . '</strong>'
								);
								?>
							</p>
						</div>
					<?php endif; ?>

					<div class="wpforo-ai-index-all-action">
						<?php
						// For local storage mode, always render both buttons for JavaScript toggle
						// For cloud mode, use server-side rendering
						$can_index = $total_topics_count > 0 && $credits_remaining > 0;
						?>
						<?php if ( $can_index ) : ?>
							<!-- Reindex button: hidden when indexing is in progress -->
							<button type="button" class="button button-primary button-hero wpforo-ai-reindex-all" style="<?php echo $has_pending_jobs ? 'display:none;' : ''; ?>" data-confirm="<?php esc_attr_e( 'This will index all forum topics. Continue?', 'wpforo' ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wpforo_ai_reindex_all' ) ); ?>">
								<span class="dashicons dashicons-update"></span>
								<?php
								if ( $all_indexed ) {
									printf(
										__( 'Re-Index All %s Topics', 'wpforo' ),
										number_format( $total_topics_count )
									);
								} elseif ( $has_enough_credits ) {
									printf(
										__( 'Index Remaining %s Topics', 'wpforo' ),
										number_format( $remaining_to_index )
									);
								} else {
									printf(
										__( 'Index %s Topics (Using All Credits)', 'wpforo' ),
										number_format( $can_index_count )
									);
								}
								?>
							</button>
							<!-- Stop button: hidden when indexing is NOT in progress -->
							<button type="button" class="button button-secondary button-hero wpforo-ai-stop-indexing" style="<?php echo ! $has_pending_jobs ? 'display:none;' : ''; ?>" data-confirm="<?php esc_attr_e( 'This will stop all pending indexing jobs. Continue?', 'wpforo' ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wpforo_ai_stop_indexing' ) ); ?>">
								<span class="dashicons dashicons-controls-pause"></span>
								<?php _e( 'Stop Indexing', 'wpforo' ); ?>
							</button>
						<?php else : ?>
							<button type="button" class="button button-primary button-hero" disabled>
								<span class="dashicons dashicons-update"></span>
								<?php _e( 'Index All Topics', 'wpforo' ); ?>
							</button>
						<?php endif; ?>
					</div>


                    <?php
                    // Get indexing status breakdown
                    $status_breakdown = $storage_manager->get_indexing_status_breakdown();
                    $has_excluded_topics = ( $status_breakdown['private'] > 0 || $status_breakdown['unapproved'] > 0 );
                    ?>
                    <?php if ( $has_excluded_topics ) : ?>
                        <div class="wpforo-ai-indexing-breakdown">
                            <details class="wpforo-ai-breakdown-details">
                                <summary class="wpforo-ai-breakdown-summary">
                                    <span class="dashicons dashicons-info-outline"></span>
                                    <?php
                                    $excluded_count = $status_breakdown['private'] + $status_breakdown['unapproved'];
                                    printf(
                                            __( '%s topics are excluded from indexing', 'wpforo' ),
                                            '<strong>' . number_format( $excluded_count ) . '</strong>'
                                    );
                                    ?>
                                    <span class="dashicons dashicons-arrow-down-alt2 wpforo-ai-breakdown-arrow"></span>
                                </summary>
                                <div class="wpforo-ai-breakdown-content">
                                    <p class="wpforo-ai-breakdown-intro">
                                        <?php _e( 'The following topics are automatically excluded from AI indexing:', 'wpforo' ); ?>
                                    </p>
                                    <ul class="wpforo-ai-breakdown-list">
                                        <?php if ( $status_breakdown['private'] > 0 ) : ?>
                                            <li>
                                                <span class="dashicons dashicons-lock"></span>
                                                <strong><?php echo number_format( $status_breakdown['private'] ); ?></strong>
                                                <?php _e( 'private topics - these are only visible to their authors', 'wpforo' ); ?>
                                            </li>
                                        <?php endif; ?>
                                        <?php if ( $status_breakdown['unapproved'] > 0 ) : ?>
                                            <li>
                                                <span class="dashicons dashicons-clock"></span>
                                                <strong><?php echo number_format( $status_breakdown['unapproved'] ); ?></strong>
                                                <?php _e( 'unapproved topics - these will be indexed once approved by moderators', 'wpforo' ); ?>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                    <p class="wpforo-ai-breakdown-note">
                                        <em><?php _e( 'Private topics are never indexed to protect user privacy. Unapproved topics will be automatically indexed when approved.', 'wpforo' ); ?></em>
                                    </p>
                                </div>
                            </details>
                        </div>
                    <?php endif; ?>
				</div>

				<div class="wpforo-ai-section-divider">
					<span><?php _e( 'Or select specific content to index:', 'wpforo' ); ?></span>
				</div>

				<div class="wpforo-ai-ingest-grid">
					<!-- Forum Selection Column -->
					<div class="wpforo-ai-ingest-column">
						<h3><?php _e( 'Index Topics by Forums', 'wpforo' ); ?></h3>

						<form method="post" action="" class="wpforo-ai-forum-ingest-form">
							<?php wp_nonce_field( 'wpforo_ai_forum_ingest' ); ?>
							<input type="hidden" name="wpforo_ai_action" value="forum_ingest">

							<!-- Date Range Filter (optional) -->
							<div class="form-row wpforo-ai-date-range wpforo-ai-forum-date-range">
								<label><?php _e( 'Date Range (optional)', 'wpforo' ); ?></label>
								<div class="wpforo-ai-date-inputs">
									<input type="date" id="forum-date-from" name="date_from" class="regular-text" placeholder="<?php esc_attr_e( 'From', 'wpforo' ); ?>">
									<span class="wpforo-ai-date-separator"><?php _e( 'to', 'wpforo' ); ?></span>
									<input type="date" id="forum-date-to" name="date_to" class="regular-text" placeholder="<?php esc_attr_e( 'To', 'wpforo' ); ?>">
								</div>
							</div>

							<p class="description">
								<?php _e( 'Select one or more forums to index their topics. Use date range to filter by topic creation date.', 'wpforo' ); ?>
								<br>
								<em><?php _e( 'Up to 10,000 not-yet-indexed topics are queued per click (newest first). Private and unapproved topics are always skipped. Click again after processing completes to continue with the remaining topics.', 'wpforo' ); ?></em>
							</p>

							<?php
							// Get all forums and sort hierarchically
							// (get_forums() orders by `order` field only, not by parent hierarchy)
							$all_forums = WPF()->forum->get_forums();

							// Sort forums hierarchically: parents first, then children under each parent
							$forums_by_parent = [];
							foreach ( $all_forums as $forum ) {
								$parent = (int) ( $forum['parentid'] ?? 0 );
								if ( ! isset( $forums_by_parent[ $parent ] ) ) {
									$forums_by_parent[ $parent ] = [];
								}
								$forums_by_parent[ $parent ][] = $forum;
							}
							// Sort children by order within each parent group
							foreach ( $forums_by_parent as &$children ) {
								usort( $children, function( $a, $b ) {
									return (int) ( $a['order'] ?? 0 ) - (int) ( $b['order'] ?? 0 );
								} );
							}
							unset( $children );
							// Build flat list by walking the tree
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
							$all_forums = $sorted_forums;
							// Get indexed counts from AI backend
							$indexed_counts = wpforo_ai_get_indexed_counts_by_forum();

							// Get all forum topic counts in a single GROUP BY query
							// instead of calling WPF()->topic->get_count() per forum (N+1 problem)
							// Cache for 5 minutes to avoid heavy GROUP BY on large forums
							$_ftc_cache_key = 'wpforo_ai_ftc_' . $current_boardid;
							$forum_topic_counts = get_transient( $_ftc_cache_key );
							if ( false === $forum_topic_counts ) {
								$forum_topic_counts = [];
								$_ftc_rows = WPF()->db->get_results(
									"SELECT `forumid`, COUNT(*) as `cnt` FROM `" . WPF()->tables->topics . "` GROUP BY `forumid`",
									ARRAY_A
								);
								if ( $_ftc_rows ) {
									foreach ( $_ftc_rows as $_ftc_row ) {
										$forum_topic_counts[ (int) $_ftc_row['forumid'] ] = (int) $_ftc_row['cnt'];
									}
								}
								set_transient( $_ftc_cache_key, $forum_topic_counts, 5 * MINUTE_IN_SECONDS );
							}

							if ( ! empty( $all_forums ) ) :
							?>
								<div class="wpforo-ai-forum-checklist">
									<?php
									foreach ( $all_forums as $forum ) {
										$forum_id = isset( $forum['forumid'] ) ? (int) $forum['forumid'] : 0;
										$forum_title = isset( $forum['title'] ) ? esc_html( $forum['title'] ) : '';
										$parent_id = isset( $forum['parentid'] ) ? (int) $forum['parentid'] : 0;
										$is_cat = isset( $forum['is_cat'] ) ? (int) $forum['is_cat'] : 0;

										// Get topic count for this forum (from pre-fetched GROUP BY)
										$topic_count = isset( $forum_topic_counts[ $forum_id ] ) ? $forum_topic_counts[ $forum_id ] : 0;

										// Get indexed count for this forum
										$indexed_count = isset( $indexed_counts[ $forum_id ] ) ? $indexed_counts[ $forum_id ] : 0;

										// Add class for child forums
										$item_class = $parent_id > 0 ? 'wpforo-ai-forum-child' : 'wpforo-ai-forum-parent';
										if ( $is_cat ) {
											$item_class .= ' wpforo-ai-forum-category';
										}
										?>
										<label class="wpforo-ai-forum-checkbox-item <?php echo esc_attr( $item_class ); ?>">
											<input
												type="checkbox"
												name="forum_ids[]"
												value="<?php echo esc_attr( $forum_id ); ?>"
												data-parent-id="<?php echo esc_attr( $parent_id ); ?>"
												data-forum-id="<?php echo esc_attr( $forum_id ); ?>"
												data-is-category="<?php echo esc_attr( $is_cat ); ?>"
											>
											<?php echo $forum_title; ?>
											<?php if ( ! $is_cat || $parent_id > 0 ) : ?>
												<span class="wpforo-ai-forum-info">
													(<?php echo number_format_i18n( $topic_count ); ?> topics / <?php echo number_format_i18n( $indexed_count ); ?> indexed)
												</span>
											<?php endif; ?>
										</label>
										<?php
									}
									?>
								</div>

								<div class="wpforo-ai-forum-actions">
									<button type="button" class="button button-small wpforo-ai-select-all-forums">
										<?php _e( 'Select All', 'wpforo' ); ?>
									</button>
									<button type="button" class="button button-small wpforo-ai-deselect-all-forums">
										<?php _e( 'Deselect All', 'wpforo' ); ?>
									</button>
								</div>

								<div class="form-actions">
									<button type="submit" name="wpforo_ai_action" value="clear_forum_index" class="button button-large wpforo-ai-clear-forum-btn" onclick="return confirm('<?php echo esc_js( __( 'This will permanently delete all indexed content from the selected forums. Topics will need to be re-indexed to appear in AI search results. Continue?', 'wpforo' ) ); ?>');">
										<span class="dashicons dashicons-trash"></span>
										<?php _e( 'Clear Selected Forum Index', 'wpforo' ); ?>
									</button>
									<button type="submit" class="button button-primary button-large" onclick="return confirm('<?php echo esc_js( __( 'This will queue up to 10,000 not-yet-indexed topics from the selected forums for background indexing. Private and unapproved topics are automatically excluded. Continue?', 'wpforo' ) ); ?>');">
										<span class="dashicons dashicons-upload"></span>
										<?php _e( 'Index Selected Forums', 'wpforo' ); ?>
									</button>
								</div>
							<?php else : ?>
								<p class="wpforo-ai-no-forums"><?php _e( 'No forums found.', 'wpforo' ); ?></p>
							<?php endif; ?>
						</form>
					</div>

					<!-- Custom Indexing Column -->
					<div class="wpforo-ai-ingest-column wpforo-ai-filtered-index">
						<h3><?php _e( 'Custom Indexing', 'wpforo' ); ?></h3>
						<p class="description">
							<?php _e( 'Index topics using custom filters. Combine date range with other filters.', 'wpforo' ); ?>
						</p>

						<form method="post" action="" class="wpforo-ai-filtered-ingest-form">
							<?php wp_nonce_field( 'wpforo_ai_filtered_ingest' ); ?>
							<input type="hidden" name="wpforo_ai_action" value="filtered_ingest">

							<!-- Date Range Filter -->
							<div class="form-row wpforo-ai-date-range">
								<label><?php _e( 'Date Range', 'wpforo' ); ?></label>
								<div class="wpforo-ai-date-inputs">
									<input type="date" id="filter-date-from" name="date_from" class="regular-text" placeholder="<?php esc_attr_e( 'From', 'wpforo' ); ?>">
									<span class="wpforo-ai-date-separator"><?php _e( 'to', 'wpforo' ); ?></span>
									<input type="date" id="filter-date-to" name="date_to" class="regular-text" placeholder="<?php esc_attr_e( 'To', 'wpforo' ); ?>">
								</div>
								<p class="description">
									<?php _e( 'Filter by topic creation date. Can be combined with any other filter.', 'wpforo' ); ?>
								</p>
							</div>

							<!-- Topic Tags Filter -->
							<div class="form-row">
								<label for="filter-tags">
									<?php _e( 'Topic Tags', 'wpforo' ); ?>
								</label>
								<input type="text" id="filter-tags" name="topic_tags" class="regular-text wpforo-ai-tags-input" placeholder="<?php esc_attr_e( 'Start typing to search tags...', 'wpforo' ); ?>" autocomplete="off">
								<p class="description">
									<?php _e( 'Type to search and select tags (comma-separated). Cannot be combined with User IDs filter.', 'wpforo' ); ?>
								</p>
							</div>

							<!-- Topic User IDs Filter -->
							<div class="form-row">
								<label for="filter-userids">
									<?php _e( 'Topic Author User IDs', 'wpforo' ); ?>
								</label>
								<input type="text" id="filter-userids" name="user_ids" class="regular-text" placeholder="<?php esc_attr_e( 'e.g., 1, 5, 12', 'wpforo' ); ?>">
								<p class="description">
									<?php _e( 'Comma-separated user IDs. Cannot be combined with Tags filter.', 'wpforo' ); ?>
								</p>
							</div>

							<hr class="wpforo-ai-filter-divider">

							<!-- Specific Topics Filter (independent) -->
							<div class="form-row">
								<label for="filter-topics">
									<?php _e( 'Specific Topic IDs or URLs', 'wpforo' ); ?>
								</label>
								<textarea id="filter-topics" name="topic_inputs" class="large-text" rows="3" placeholder="<?php esc_attr_e( "e.g., 123, 456\nhttps://yourforum.com/topic/sample-topic/", 'wpforo' ); ?>"></textarea>
								<p class="description">
									<?php _e( 'Comma or newline separated. This filter works independently - ignores other filters above.', 'wpforo' ); ?>
								</p>
							</div>

							<div class="form-actions">
								<button type="submit" class="button button-primary button-large">
									<span class="dashicons dashicons-upload"></span>
									<?php _e( 'Index Filtered Topics', 'wpforo' ); ?>
								</button>
							</div>
						</form>
					</div>
				</div>

				<!-- Quick Actions -->
				<div class="wpforo-ai-quick-actions">
					<button type="button" class="button wpforo-ai-reindex-all" data-confirm="<?php esc_attr_e( 'This will re-index all topics. Continue?', 'wpforo' ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wpforo_ai_reindex_all' ) ); ?>">
						<span class="dashicons dashicons-update"></span>
						<?php _e( 'Re-Index All Topics', 'wpforo' ); ?>
					</button>
					<?php
					// Show Re-Index Topic Images button for Professional+ plans
					$has_image_reindex_plan = in_array( $plan, [ 'professional', 'business', 'enterprise' ], true );
					if ( $has_image_reindex_plan ) :
					?>
					<button type="button" class="button wpforo-ai-reindex-images" data-confirm="<?php esc_attr_e( 'This will re-index all topics that contain images. Topics without images will not be affected. Continue?', 'wpforo' ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wpforo_ai_reindex_images' ) ); ?>">
						<span class="dashicons dashicons-format-image"></span>
						<?php _e( 'Re-Index Topic Images', 'wpforo' ); ?>
					</button>
					<?php endif; ?>
					<button type="button" class="button wpforo-ai-cleanup-session" data-scope="forum" data-confirm="<?php esc_attr_e( 'Reset stuck forum indexing session? This clears pending queues, cron jobs and cached state for BOTH local and cloud modes. Already-indexed topics are NOT affected.', 'wpforo' ); ?>" title="<?php esc_attr_e( 'Clears pending indexing queues, WP-Cron jobs, session locks and status caches. Use this if indexing is stuck on "Indexing..." but nothing is progressing. Works for both local and cloud storage modes. Does not delete any indexed data.', 'wpforo' ); ?>">
						<span class="dashicons dashicons-update-alt"></span>
						<?php _e( 'Cleanup Indexing Session', 'wpforo' ); ?>
					</button>
					<button type="button" class="button button-link-delete wpforo-ai-clear-database" data-confirm="<?php esc_attr_e( 'Are you sure? This will delete ALL indexed data and cannot be undone!', 'wpforo' ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wpforo_ai_clear_database' ) ); ?>">
						<span class="dashicons dashicons-trash"></span>
						<?php _e( 'Clear Forum Index', 'wpforo' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- WordPress Content Indexing Box (Business plan required) -->
		<?php
		$wpf_ai_wp_indexing_available = isset( WPF()->ai_client ) && WPF()->ai_client->is_feature_available( 'wordpress_content_indexing' );
		if ( $wpf_ai_wp_indexing_available ) :
		?>
		<div class="wpforo-ai-box wpforo-ai-wordpress-indexing-box">
			<div class="wpforo-ai-box-header">
				<h2>
					<span class="dashicons dashicons-wordpress-alt"></span>
					<?php _e( 'WordPress Content Indexing', 'wpforo' ); ?>
				</h2>
				<div class="wpforo-ai-header-actions">
					<?php
					// Get WordPress auto-indexing option (global, not board-specific since WP content is global)
					$wp_auto_indexing_enabled = (bool) get_option( 'wpforo_ai_wp_auto_indexing_enabled', 0 );

					// Get WordPress image indexing option (Professional/Business/Enterprise only)
					$wp_image_indexing_enabled = (bool) get_option( 'wpforo_ai_wp_image_indexing_enabled', 0 );
					?>
					<label class="wpforo-ai-auto-index-toggle" title="<?php esc_attr_e( 'Include images in indexing (Business/Enterprise). Posts with images will consume +1 credit.', 'wpforo' ); ?>">
						<span class="wpforo-ai-auto-index-label">
							<?php _e( 'Index Images', 'wpforo' ); ?>
						</span>
						<div class="wpforo-ai-switch">
							<input type="checkbox" id="wpforo-ai-wp-image-indexing" name="ai_wp_image_indexing_enabled" value="1" <?php checked( $wp_image_indexing_enabled ); ?> data-option-name="ai_wp_image_indexing_enabled">
							<span class="wpforo-ai-switch-slider"></span>
						</div>
					</label>
					<label class="wpforo-ai-auto-index-toggle" title="<?php esc_attr_e( 'Automatically index new and updated WordPress content', 'wpforo' ); ?>">
						<span class="wpforo-ai-auto-index-label"><?php _e( 'Automatically index new content', 'wpforo' ); ?></span>
						<div class="wpforo-ai-switch">
							<input type="checkbox" id="wpforo-ai-wp-auto-indexing" name="ai_wp_auto_indexing_enabled" value="1" <?php checked( $wp_auto_indexing_enabled ); ?> data-option-name="ai_wp_auto_indexing_enabled">
							<span class="wpforo-ai-switch-slider"></span>
						</div>
					</label>
					<button type="button" class="button button-small wpforo-ai-refresh-wp-status" data-loading-text="<?php esc_attr_e( 'Refreshing...', 'wpforo' ); ?>">
						<span class="dashicons dashicons-update"></span>
						<?php _e( 'Refresh Status', 'wpforo' ); ?>
					</button>
				</div>
			</div>
			<div class="wpforo-ai-box-body">
				<p class="wpforo-ai-description">
					<?php _e( 'Index WordPress posts, pages, and custom post types to enable AI-powered search across all your site content. This works alongside forum content indexing.', 'wpforo' ); ?>
				</p>

				<?php
				// Get post types from the indexer
				$wp_post_types = [];
				if ( isset( WPF()->ai_wp_indexer ) ) {
					$wp_post_types = WPF()->ai_wp_indexer->get_public_post_types();
				}

				// Calculate totals
				$wp_total_content = 0;
				foreach ( $wp_post_types as $type ) {
					$wp_total_content += $type['count'];
				}

				// Get initial indexed counts for page load (avoids flash of 0)
				$wp_initial_status  = null;
				$wp_initial_indexed = 0;
				if ( isset( WPF()->ai_wp_indexer ) ) {
					$wp_initial_status = WPF()->ai_wp_indexer->get_indexing_status( true );
					if ( ! is_wp_error( $wp_initial_status ) ) {
						$wp_initial_indexed = (int) ( $wp_initial_status['total_indexed'] ?? 0 );
					}
				}
				?>

				<!-- WordPress Content Stats -->
				<div class="wpforo-ai-wp-stats">
					<div class="rag-stat-item">
						<div class="stat-icon">
							<span class="dashicons dashicons-admin-page"></span>
						</div>
						<div class="stat-info">
							<div class="stat-value" id="wp-total-content"><?php echo number_format( $wp_total_content ); ?></div>
							<div class="stat-label"><?php _e( 'Total WordPress Content', 'wpforo' ); ?></div>
						</div>
					</div>
					<div class="rag-stat-item">
						<div class="stat-icon">
							<span class="dashicons dashicons-database-view"></span>
						</div>
						<div class="stat-info">
							<div class="stat-value" id="wp-total-indexed"><?php echo number_format( $wp_initial_indexed ); ?></div>
							<div class="stat-label"><?php _e( 'Total Indexed', 'wpforo' ); ?></div>
						</div>
					</div>
					<div class="rag-stat-item">
						<div class="stat-icon">
							<span class="dashicons dashicons-saved"></span>
						</div>
						<div class="stat-info">
							<div class="stat-value" id="wp-indexing-status"><?php _e( 'Idle', 'wpforo' ); ?></div>
							<div class="stat-label"><?php _e( 'Current Status', 'wpforo' ); ?></div>
						</div>
					</div>
				</div>

				<?php if ( empty( $wp_post_types ) ) : ?>
					<div class="wpforo-ai-notice wpforo-ai-notice-warning">
						<span class="dashicons dashicons-warning"></span>
						<p><?php _e( 'No public post types with content found.', 'wpforo' ); ?></p>
					</div>
				<?php else : ?>

					<div class="wpforo-ai-ingest-grid wpforo-ai-wp-ingest-grid">
						<!-- Taxonomy Selection Column -->
						<div class="wpforo-ai-ingest-column">
							<h3><?php _e( 'Index by Categories/Taxonomies', 'wpforo' ); ?></h3>
							<p class="description">
								<?php _e( 'Select a taxonomy and term to index all content in that category.', 'wpforo' ); ?>
							</p>

							<form class="wpforo-ai-wp-taxonomy-form">
								<?php wp_nonce_field( 'wpforo_admin_ajax', 'security' ); ?>

								<!-- Date Range Filter -->
								<div class="form-row wpforo-ai-date-range">
									<label><?php _e( 'Date Range (optional)', 'wpforo' ); ?></label>
									<div class="wpforo-ai-date-inputs">
										<input type="date" id="wp-tax-date-from" name="date_from" class="regular-text">
										<span class="wpforo-ai-date-separator"><?php _e( 'to', 'wpforo' ); ?></span>
										<input type="date" id="wp-tax-date-to" name="date_to" class="regular-text">
									</div>
									<p class="description">
										<?php _e( 'Filter by content publication date within the selected category.', 'wpforo' ); ?>
									</p>
								</div>

								<div class="form-row">
									<label for="wp-taxonomy-select">
										<?php _e( 'Taxonomy', 'wpforo' ); ?>
									</label>
									<select id="wp-taxonomy-select" name="taxonomy" class="regular-text">
										<option value=""><?php _e( 'Select a taxonomy...', 'wpforo' ); ?></option>
										<?php
										// Get taxonomies for the first post type by default
										$first_type = reset( $wp_post_types );
										if ( $first_type && isset( WPF()->ai_wp_indexer ) ) {
											$taxonomies = WPF()->ai_wp_indexer->get_taxonomies_for_post_type( $first_type['name'] );
											foreach ( $taxonomies as $tax ) :
												?>
												<option value="<?php echo esc_attr( $tax['name'] ); ?>">
													<?php echo esc_html( $tax['label'] ); ?> (<?php echo number_format( $tax['term_count'] ); ?> terms)
												</option>
												<?php
											endforeach;
										}
										?>
									</select>
								</div>

								<div class="form-row">
									<label>
										<?php _e( 'Terms/Categories', 'wpforo' ); ?>
									</label>
									<div id="wp-terms-container" class="wpforo-ai-terms-container">
										<div class="wpforo-ai-terms-placeholder">
											<?php _e( 'Select a taxonomy first to load terms...', 'wpforo' ); ?>
										</div>
									</div>
									<div id="wp-terms-actions" class="wpforo-ai-terms-actions" style="display: none;">
										<button type="button" class="button button-small wpforo-ai-select-all-terms">
											<?php _e( 'Select All', 'wpforo' ); ?>
										</button>
										<button type="button" class="button button-small wpforo-ai-deselect-all-terms">
											<?php _e( 'Deselect All', 'wpforo' ); ?>
										</button>
									</div>
								</div>

								<div class="form-actions">
									<button type="submit" class="button button-primary button-large wpforo-ai-wp-index-taxonomy" disabled>
										<span class="dashicons dashicons-upload"></span>
										<?php _e( 'Index Selected Terms', 'wpforo' ); ?>
									</button>
								</div>
							</form>
						</div>

						<!-- Custom Indexing Column -->
						<div class="wpforo-ai-ingest-column wpforo-ai-wp-custom-index">
							<h3><?php _e( 'Index by Content Type', 'wpforo' ); ?></h3>
							<p class="description">
								<?php _e( 'Select content types and optionally filter by date range.', 'wpforo' ); ?>
							</p>

							<form class="wpforo-ai-wp-custom-form">
								<?php wp_nonce_field( 'wpforo_admin_ajax', 'security' ); ?>

								<!-- Date Range Filter -->
								<div class="form-row wpforo-ai-date-range">
									<label><?php _e( 'Date Range (optional)', 'wpforo' ); ?></label>
									<div class="wpforo-ai-date-inputs">
										<input type="date" id="wp-date-from" name="date_from" class="regular-text">
										<span class="wpforo-ai-date-separator"><?php _e( 'to', 'wpforo' ); ?></span>
										<input type="date" id="wp-date-to" name="date_to" class="regular-text">
									</div>
									<p class="description">
										<?php _e( 'Leave empty to index all content of selected types.', 'wpforo' ); ?>
									</p>
								</div>

								<!-- Content Types Selection -->
								<div class="form-row">
									<label><?php _e( 'Content Types', 'wpforo' ); ?></label>
									<div class="wpforo-ai-wp-types-grid wpforo-ai-wp-types-compact">
										<?php foreach ( $wp_post_types as $type ) : ?>
											<label class="wpforo-ai-wp-type-item">
												<input type="checkbox"
													   name="wp_post_types[]"
													   value="<?php echo esc_attr( $type['name'] ); ?>"
													   data-count="<?php echo esc_attr( $type['count'] ); ?>"
													   class="wpforo-ai-wp-type-checkbox"
													   checked>
												<span class="type-label"><?php echo esc_html( $type['label'] ); ?></span>
												<span class="type-count">(<?php echo number_format( $type['count'] ); ?>)</span>
												<?php
												$type_key            = 'wp_' . $type['name'];
												$type_indexed_count  = 0;
												if ( $wp_initial_status && ! is_wp_error( $wp_initial_status ) && isset( $wp_initial_status['by_type'][ $type_key ]['indexed'] ) ) {
													$type_indexed_count = (int) $wp_initial_status['by_type'][ $type_key ]['indexed'];
												}
												?>
												<span class="type-indexed" id="wp-indexed-<?php echo esc_attr( $type['name'] ); ?>">
													<span class="indexed-count"><?php echo (int) $type_indexed_count; ?></span> <?php _e( 'indexed', 'wpforo' ); ?>
												</span>
											</label>
										<?php endforeach; ?>
									</div>
								</div>

								<div class="form-actions">
									<button type="submit" class="button button-primary button-large wpforo-ai-wp-index-custom">
										<span class="dashicons dashicons-upload"></span>
										<?php _e( 'Index Selected Content', 'wpforo' ); ?>
									</button>
								</div>
							</form>

							<div class="wpforo-ai-section-divider wpforo-ai-section-divider-small">
								<span><?php _e( 'Or index by specific IDs:', 'wpforo' ); ?></span>
							</div>

							<!-- Specific IDs Form (Independent) -->
							<form class="wpforo-ai-wp-ids-form">
								<?php wp_nonce_field( 'wpforo_admin_ajax', 'security' ); ?>

								<div class="form-row">
									<label for="wp-post-ids">
										<?php _e( 'Post/Page IDs', 'wpforo' ); ?>
									</label>
									<textarea id="wp-post-ids" name="post_ids" class="large-text" rows="2" placeholder="<?php esc_attr_e( 'e.g., 123, 456, 789', 'wpforo' ); ?>"></textarea>
									<p class="description">
										<?php _e( 'Comma-separated post IDs. Indexes regardless of content type or date.', 'wpforo' ); ?>
									</p>
								</div>

								<div class="form-actions">
									<button type="submit" class="button button-secondary wpforo-ai-wp-index-ids">
										<span class="dashicons dashicons-upload"></span>
										<?php _e( 'Index by IDs', 'wpforo' ); ?>
									</button>
								</div>
							</form>
						</div>
					</div>

					<!-- Quick Actions -->
					<div class="wpforo-ai-wp-quick-actions">
						<button type="button" class="button wpforo-ai-cleanup-session" data-scope="wp" data-confirm="<?php esc_attr_e( 'Reset stuck WordPress indexing session? This clears the pending post queue, cron jobs and cached status. Already-indexed posts are NOT affected.', 'wpforo' ); ?>" title="<?php esc_attr_e( 'Clears pending WordPress post/page indexing queue, WP-Cron jobs and status cache. Use this if WordPress indexing is stuck on "Indexing..." but nothing is progressing. Works for both local and cloud storage modes. Does not delete any indexed data.', 'wpforo' ); ?>">
							<span class="dashicons dashicons-update-alt"></span>
							<?php _e( 'Cleanup Indexing Session', 'wpforo' ); ?>
						</button>
						<button type="button" class="button button-link-delete wpforo-ai-wp-clear-index" data-confirm="<?php esc_attr_e( 'This will remove all WordPress content from the AI index. Continue?', 'wpforo' ); ?>">
							<span class="dashicons dashicons-trash"></span>
							<?php _e( 'Clear WordPress Index', 'wpforo' ); ?>
						</button>
					</div>

					<!-- Indexing Progress (hidden by default) -->
					<div class="wpforo-ai-wp-progress" style="display: none;">
						<div class="progress-bar">
							<div class="progress-fill" style="width: 0%;"></div>
						</div>
						<div class="progress-text">
							<span class="progress-status"><?php _e( 'Indexing...', 'wpforo' ); ?></span>
							<span class="progress-percent">0%</span>
						</div>
					</div>

				<?php endif; ?>
			</div>
		</div>
		<?php endif; // End WordPress Content Indexing plan check ?>

        <!-- Chunking Configuration Box -->
        <div class="wpforo-ai-box wpforo-ai-chunking-config-box">
            <div class="wpforo-ai-box-header">
                <h2><?php _e( 'Chunking Configuration', 'wpforo' ); ?></h2>
            </div>
            <div class="wpforo-ai-box-body">
                <p class="wpforo-ai-description">
                    <?php _e( 'Configure how forum content is split into chunks for vector indexing. These settings affect search quality and cost.', 'wpforo' ); ?>
                </p>

                <form method="post" action="" class="wpforo-ai-chunking-form">
                    <?php wp_nonce_field( 'wpforo_ai_save_chunking_config' ); ?>
                    <input type="hidden" name="wpforo_ai_action" value="save_chunking_config">

                    <div class="wpforo-ai-chunking-settings">
                        <?php
                        // Get saved values
                        $saved_chunk_size = (int) wpforo_get_option( 'ai_chunk_size', 512 );
                        $saved_overlap_percent = (int) wpforo_get_option( 'ai_overlap_percent', 20 );
                        $saved_pagination_size = (int) wpforo_get_option( 'ai_pagination_size', 20 );
                        ?>

                        <div class="chunking-setting-row">
                            <div class="setting-label">
                                <label for="wpforo-ai-chunk-size">
                                    <?php _e( 'Chunk Size (tokens)', 'wpforo' ); ?>
                                </label>
                                <p class="description">
                                    <?php _e( 'Number of tokens per chunk. 1 token ≈ 4 characters. Smaller chunks give more precise search results. Larger chunks provide more context but may include less relevant content. Default: 512 (~2000 characters)', 'wpforo' ); ?>
                                </p>
                            </div>
                            <div class="setting-control">
                                <input type="number" id="wpforo-ai-chunk-size" name="chunk_size" class="regular-text" value="<?php echo esc_attr( $saved_chunk_size ); ?>" min="100" max="1024">
                                <span class="input-hint"><?php _e( 'Range: 100-1024 tokens', 'wpforo' ); ?></span>
                            </div>
                        </div>

                        <div class="chunking-setting-row">
                            <div class="setting-label">
                                <label for="wpforo-ai-overlap-percent">
                                    <?php _e( 'Overlap Percentage (%)', 'wpforo' ); ?>
                                </label>
                                <p class="description">
                                    <?php _e( 'Percentage of overlap between consecutive chunks. Helps maintain context across chunk boundaries. Default: 20%', 'wpforo' ); ?>
                                </p>
                            </div>
                            <div class="setting-control">
                                <input type="number" id="wpforo-ai-overlap-percent" name="overlap_percent" class="regular-text" value="<?php echo esc_attr( $saved_overlap_percent ); ?>" min="5" max="50">
                                <span class="input-hint"><?php _e( 'Range: 5-50%', 'wpforo' ); ?></span>
                            </div>
                        </div>

                        <div class="chunking-setting-row">
                            <div class="setting-label">
                                <label for="wpforo-ai-pagination-size">
                                    <?php _e( 'Topics Per Batch', 'wpforo' ); ?>
                                </label>
                                <p class="description">
                                    <?php _e( 'Number of topics to process per batch during indexing. Lower values are more reliable but slower. Higher values are faster but may timeout on slower servers. Default: 10', 'wpforo' ); ?>
                                </p>
                            </div>
                            <div class="setting-control">
                                <input type="number" id="wpforo-ai-pagination-size" name="pagination_size" class="regular-text" value="<?php echo esc_attr( $saved_pagination_size ); ?>" min="1" max="50">
                                <span class="input-hint"><?php _e( 'Range: 1-50', 'wpforo' ); ?></span>
                            </div>
                        </div>

                        <div class="chunking-info-notice">
                            <span class="dashicons dashicons-info"></span>
                            <div class="notice-content">
                                <strong><?php _e( 'How this affects indexing:', 'wpforo' ); ?></strong>
                                <ul>
                                    <li><?php _e( 'Larger chunk size = better context preservation, fewer vectors.', 'wpforo' ); ?></li>
                                    <li><?php _e( 'Higher overlap = better search quality at chunk boundaries.', 'wpforo' ); ?></li>
                                    <li><?php _e( 'More topics per step = faster re-indexing but uses more memory. Reduce if you experience memory issues.', 'wpforo' ); ?></li>
                                    <li><?php _e( 'These settings will be used for all future indexing operations.', 'wpforo' ); ?></li>
                                </ul>
                            </div>
                        </div>

                        <div class="chunking-actions">
                            <button type="submit" class="button button-primary button-large">
                                <span class="dashicons dashicons-saved"></span>
                                <?php _e( 'Save Configuration', 'wpforo' ); ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Search Test Box (only show if topics are indexed) -->
		<?php if ( $total_indexed > 0 ) : ?>
			<div class="wpforo-ai-box wpforo-ai-search-test-box">
				<div class="wpforo-ai-box-header">
					<h2><?php _e( 'Test Semantic Search', 'wpforo' ); ?></h2>
				</div>
				<div class="wpforo-ai-box-body">
					<p class="wpforo-ai-description">
						<?php _e( 'Test the AI semantic search with your indexed topics. Results include metadata for generating topic and post URLs.', 'wpforo' ); ?>
					</p>

					<form id="wpforo-ai-search-test-form" class="wpforo-ai-search-test-form">
						<?php wp_nonce_field( 'wpforo_ai_features_nonce' ); ?>
						<div class="form-row">
							<label for="search-query">
								<?php _e( 'Search Query', 'wpforo' ); ?>
							</label>
							<input type="text" id="search-query" name="query" class="large-text" placeholder="<?php esc_attr_e( 'e.g., how to reset password', 'wpforo' ); ?>" required>
							<p class="description">
								<?php _e( 'Enter a natural language search query to test semantic search.', 'wpforo' ); ?>
							</p>
						</div>

						<div class="form-row">
							<label for="search-limit">
								<?php _e( 'Number of Results', 'wpforo' ); ?>
							</label>
							<input type="number" id="search-limit" name="limit" class="small-text" value="1" min="1" max="20">
							<p class="description">
								<?php _e( 'Maximum number of search results to return (1-20).', 'wpforo' ); ?>
							</p>
						</div>

						<div class="form-actions">
							<button type="submit" class="button button-primary" id="search-test-btn">
								<span class="dashicons dashicons-search"></span>
								<?php _e( 'Test Search', 'wpforo' ); ?>
							</button>
							<span class="spinner" style="float:none; margin: 0 10px;"></span>
						</div>
					</form>

					<!-- Search Results Display -->
					<div id="search-test-results" class="wpforo-ai-search-results" style="display:none;">
						<h3><?php _e( 'Search Results', 'wpforo' ); ?></h3>
						<div id="search-results-content"></div>
					</div>
				</div>
			</div>
		<?php endif; ?>


		<!-- Indexing Queue Info (if indexing) -->
		<?php if ( $is_indexing ) : ?>
			<div class="wpforo-ai-box wpforo-ai-queue-info-box">
				<div class="wpforo-ai-box-header">
					<h2><?php _e( 'Indexing Queue', 'wpforo' ); ?></h2>
				</div>
				<div class="wpforo-ai-box-body">
					<div class="queue-info" id="wpforo-ai-queue-info">
						<div class="queue-stat">
							<span class="dashicons dashicons-hourglass"></span>
							<span id="queue-pending">-</span> <?php _e( 'pending', 'wpforo' ); ?>
						</div>
						<div class="queue-stat">
							<span class="dashicons dashicons-yes-alt"></span>
							<span id="queue-completed">-</span> <?php _e( 'completed', 'wpforo' ); ?>
						</div>
						<div class="queue-stat">
							<span class="dashicons dashicons-warning"></span>
							<span id="queue-failed">-</span> <?php _e( 'failed', 'wpforo' ); ?>
						</div>
					</div>
					<p class="description">
						<?php _e( 'The queue updates automatically every 30 seconds while indexing is in progress.', 'wpforo' ); ?>
					</p>
				</div>
			</div>
		<?php endif; ?>

	</div>

	<!-- AI Content Indexing Tab Specific JavaScript -->
	<script type="text/javascript">
		jQuery(document).ready(function($) {
			// RAG-specific functionality (polling and auto-refresh)
			if (typeof window.WpForoAI !== 'undefined') {
				// Start polling if indexing or processing is active
				<?php if ( $is_processing ) : ?>
					if (typeof WpForoAI.startRAGStatusPolling === 'function') {
						WpForoAI.startRAGStatusPolling();
					}
				<?php endif; ?>
			}

			// Auto-refresh page only when actively processing (cron is due or batch running).
			// Queued topics scheduled for future (e.g., 1h/24h auto-indexing) should NOT trigger refresh.
			<?php if ( $pending_jobs_info['is_actively_processing'] ) : ?>
				setTimeout(function() {
					window.location.reload();
				}, 30000); // Refresh after 30 seconds
			<?php endif; ?>
		});
	</script>
	<?php
}
