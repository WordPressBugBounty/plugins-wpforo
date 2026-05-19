<?php
/**
 * AI Features - AI Tools Tab (Custom Knowledge)
 *
 * Allows Business+ tenants to upload custom knowledge files (JSON/Markdown/Text)
 * and configure priority settings for AI features.
 *
 * @package wpForo
 * @subpackage Admin
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render AI Tools tab content
 *
 * @param bool  $is_connected Whether tenant is connected to AI service
 * @param array $status       Tenant status data from API
 */
function wpforo_ai_render_ai_tools_tab( $is_connected, $status ) {
	if ( ! $is_connected ) {
		?>
		<div class="wpforo-ai-box wpforo-ai-not-connected-notice">
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-status-badge status-warning">
					<span class="dashicons dashicons-warning"></span>
					<?php _e( 'Not Connected', 'wpforo' ); ?>
				</div>
				<p><?php _e( 'Please connect to wpForo AI API first in the Overview tab to enable AI Tools.', 'wpforo' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpforo-ai&tab=overview' ) ); ?>" class="button button-primary">
					<?php _e( 'Go to Overview', 'wpforo' ); ?>
				</a>
			</div>
		</div>
		<?php
		return;
	}

	// Feature gate check - Business+ plan required
	$custom_knowledge_available = isset( WPF()->ai_client ) && WPF()->ai_client->is_feature_available( 'custom_knowledge' );
	if ( ! $custom_knowledge_available ) {
		?>
		<div class="wpforo-ai-box wpforo-ai-upgrade-notice">
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-status-badge status-warning">
					<span class="dashicons dashicons-lock"></span>
					<?php _e( 'Business Plan Required', 'wpforo' ); ?>
				</div>
				<p><?php _e( 'Custom Knowledge is available on Business and Enterprise plans. Upgrade to upload your own knowledge files for AI-powered search, chatbot, and bot replies.', 'wpforo' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpforo-ai&tab=overview' ) ); ?>" class="button button-primary">
					<?php _e( 'View Plans', 'wpforo' ); ?>
				</a>
			</div>
		</div>
		<?php
		return;
	}

	// Check storage mode - custom knowledge requires cloud mode
	$storage_manager = WPF()->vector_storage->for_board( 0 );
	$storage_mode = $storage_manager->get_storage_mode();

	if ( $storage_mode !== 'cloud' ) {
		?>
		<div class="wpforo-ai-box wpforo-ai-upgrade-notice">
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-status-badge status-warning">
					<span class="dashicons dashicons-cloud"></span>
					<?php _e( 'Cloud Storage Required', 'wpforo' ); ?>
				</div>
				<p><?php _e( 'Custom Knowledge requires Cloud storage mode. Please switch to Cloud storage in the Forum Indexing tab to use this feature.', 'wpforo' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpforo-ai&tab=rag_indexing' ) ); ?>" class="button button-primary">
					<?php _e( 'Go to Forum Indexing', 'wpforo' ); ?>
				</a>
			</div>
		</div>
		<?php
		return;
	}

	// Get remaining credits
	$remaining_credits = 0;
	if ( isset( $status['subscription']['credits_remaining'] ) ) {
		$remaining_credits = (int) $status['subscription']['credits_remaining'];
	}

	// Get all boards for board settings
	$all_boards = WPF()->board->get_boards( [ 'status' => true ] );
	$is_multiboard = count( $all_boards ) > 1;
	?>

	<div class="wpforo-ai-tools-tab">

		<!-- Add Custom Knowledge Box -->
		<div class="wpforo-ai-box wpforo-ai-knowledge-upload-box">
			<div class="wpforo-ai-box-header">
				<h2>
					<span class="dashicons dashicons-database-add"></span>
					<?php _e( 'Add Custom Knowledge', 'wpforo' ); ?>
				</h2>
			</div>
			<div class="wpforo-ai-box-body">
				<p class="wpforo-ai-section-desc kind-text">
					<span class="dashicons dashicons-media-text desc-icon"></span>
					<?php _e( 'Add JSON, Markdown, or Text files to enhance AI search, chatbot, and bot replies with your custom content.', 'wpforo' ); ?>
				</p>

				<form id="wpforo-ai-knowledge-upload-form" class="wpforo-ai-knowledge-form kind-text">
					<div class="wpforo-ai-url-input-group">
						<input type="url"
							   id="knowledge-file-url"
							   name="file_url"
							   placeholder="<?php esc_attr_e( 'Select a TXT, MD, or JSON file from Media Library or paste URL', 'wpforo' ); ?>"
							   required>
						<button type="button" class="button" id="knowledge-media-btn">
							<span class="dashicons dashicons-admin-media"></span>
							<?php _e( 'Media Library', 'wpforo' ); ?>
						</button>
						<button type="submit" class="button button-primary" id="knowledge-upload-btn" style="width: 20%; text-align: center; display: inline-block;">
							<?php _e( 'Index Text File', 'wpforo' ); ?>
						</button>
					</div>

					<!-- Hidden fields for auto-detected values -->
					<input type="hidden" id="knowledge-file-type" name="file_type" value="text">
					<input type="hidden" id="knowledge-file-name" name="name" value="">

					<div class="wpforo-ai-upload-info">
						<div class="wpforo-ai-file-detected" id="knowledge-file-detected" style="display: none;">
							<span class="file-icon"></span>
							<span class="file-name"></span>
							<span class="file-type-badge"></span>
						</div>
						<div class="wpforo-ai-credits-info">
							<span class="dashicons dashicons-database"></span>
							<?php printf(
								__( 'Credits: %s remaining', 'wpforo' ),
								'<strong>' . number_format( $remaining_credits ) . '</strong>'
							); ?>
							<span class="wpforo-ai-credit-rate"><?php _e( '(1 credit per 100KB or 1 file with small size, max 20MB)', 'wpforo' ); ?></span>
						</div>
					</div>

					<div class="wpforo-ai-upload-progress" id="knowledge-upload-progress" style="display: none;">
						<div class="wpforo-ai-progress-bar">
							<div class="wpforo-ai-progress-fill"></div>
						</div>
						<div class="wpforo-ai-progress-text"></div>
					</div>
				</form>

				<p class="wpforo-ai-section-desc kind-pdf" style="margin-top: 24px;">
					<span class="dashicons dashicons-media-document desc-icon"></span>
					<?php _e( 'Add PDF files to enhance AI search, chatbot, and bot replies with your custom content.', 'wpforo' ); ?>
				</p>

				<form id="wpforo-ai-knowledge-pdf-upload-form" class="wpforo-ai-knowledge-form kind-pdf">
					<div class="wpforo-ai-url-input-group">
						<input type="url"
							   id="knowledge-pdf-file-url"
							   name="file_url"
							   placeholder="<?php esc_attr_e( 'Select a PDF from Media Library or paste URL', 'wpforo' ); ?>"
							   required>
						<button type="button" class="button" id="knowledge-pdf-media-btn">
							<span class="dashicons dashicons-admin-media"></span>
							<?php _e( 'Media Library', 'wpforo' ); ?>
						</button>
						<button type="submit" class="button button-primary" id="knowledge-pdf-upload-btn" style="width: 20%; text-align: center; display: inline-block;">
							<?php _e( 'Index PDF File', 'wpforo' ); ?>
						</button>
					</div>

					<input type="hidden" id="knowledge-pdf-file-type" name="file_type" value="pdf">
					<input type="hidden" id="knowledge-pdf-file-name" name="name" value="">

					<div class="wpforo-ai-upload-info">
						<div class="wpforo-ai-file-detected" id="knowledge-pdf-file-detected" style="display: none;">
							<span class="file-icon"></span>
							<span class="file-name"></span>
							<span class="file-type-badge"></span>
						</div>
						<div class="wpforo-ai-credits-info">
							<span class="dashicons dashicons-database"></span>
							<?php printf(
								__( 'Credits: %s remaining', 'wpforo' ),
								'<strong>' . number_format( $remaining_credits ) . '</strong>'
							); ?>
							<span class="wpforo-ai-credit-rate"><?php _e( '(1 credit per 1 page, max 50MB)', 'wpforo' ); ?></span>
						</div>
					</div>

					<div class="wpforo-ai-upload-progress" id="knowledge-pdf-upload-progress" style="display: none;">
						<div class="wpforo-ai-progress-bar">
							<div class="wpforo-ai-progress-fill"></div>
						</div>
						<div class="wpforo-ai-progress-text"></div>
					</div>
				</form>

				<details class="wpforo-ai-file-format-help">
					<summary><?php _e( 'Supported file formats', 'wpforo' ); ?></summary>
					<div class="wpforo-ai-format-list">
						<div class="format-item">
							<strong>.json</strong> - <?php _e( 'Array of objects with "title" and "content" fields (recommended)', 'wpforo' ); ?>
						</div>
						<div class="format-item">
							<strong>.md</strong> - <?php _e( 'Markdown with ## headings to split into chunks', 'wpforo' ); ?>
						</div>
						<div class="format-item">
							<strong>.txt</strong> - <?php _e( 'Plain text, split by "---" separators or paragraphs', 'wpforo' ); ?>
						</div>
						<div class="format-item">
							<strong>.pdf</strong> - <?php _e( 'PDF documents — each page becomes a section. Scanned/image-only PDFs are auto-OCR\'d when text is too sparse.', 'wpforo' ); ?>
						</div>
					</div>
				</details>
			</div>
		</div>

		<!-- Indexed Knowledge Files Box -->
		<div class="wpforo-ai-box wpforo-ai-knowledge-files-box">
			<div class="wpforo-ai-box-header">
				<h2>
					<span class="dashicons dashicons-media-document"></span>
					<?php _e( 'Indexed Knowledge Files', 'wpforo' ); ?>
					<span class="wpforo-ai-file-count" id="knowledge-file-count"></span>
				</h2>
				<div class="wpforo-ai-header-actions">
					<button type="button" class="button button-small" id="knowledge-refresh-files">
						<span class="dashicons dashicons-update"></span>
						<?php _e( 'Refresh', 'wpforo' ); ?>
					</button>
				</div>
			</div>
			<div class="wpforo-ai-box-body">
				<div id="knowledge-files-loading" class="wpforo-ai-loading">
					<span class="spinner is-active"></span>
					<?php _e( 'Loading files...', 'wpforo' ); ?>
				</div>

				<div id="knowledge-files-empty" class="wpforo-ai-empty-state" style="display: none;">
					<span class="dashicons dashicons-portfolio"></span>
					<p><?php _e( 'No knowledge files indexed yet. Upload your first file above.', 'wpforo' ); ?></p>
				</div>

				<table id="knowledge-files-table" class="wp-list-table widefat fixed striped" style="display: none;">
					<thead>
						<tr>
							<th class="column-name"><?php _e( 'Name', 'wpforo' ); ?></th>
							<th class="column-type"><?php _e( 'Type', 'wpforo' ); ?></th>
							<th class="column-size"><?php _e( 'Size', 'wpforo' ); ?></th>
							<th class="column-chunks"><?php _e( 'Chunks', 'wpforo' ); ?></th>
							<th class="column-credits"><?php _e( 'Credits', 'wpforo' ); ?></th>
							<th class="column-status"><?php _e( 'Status', 'wpforo' ); ?></th>
							<th class="column-actions"><?php _e( 'Actions', 'wpforo' ); ?></th>
						</tr>
					</thead>
					<tbody id="knowledge-files-tbody">
					</tbody>
					<tfoot>
						<tr class="wpforo-ai-files-totals">
							<td class="column-name"><strong><?php _e( 'Total', 'wpforo' ); ?></strong></td>
							<td class="column-type"></td>
							<td class="column-size" id="knowledge-total-size">-</td>
							<td class="column-chunks" id="knowledge-total-chunks">0</td>
							<td class="column-credits" id="knowledge-total-credits">0</td>
							<td class="column-status"></td>
							<td class="column-actions"></td>
						</tr>
					</tfoot>
				</table>
			</div>
		</div>

		<!-- Board Settings Box -->
		<div class="wpforo-ai-box wpforo-ai-knowledge-settings-box">
			<div class="wpforo-ai-box-header">
				<h2>
					<span class="dashicons dashicons-admin-settings"></span>
					<?php _e( 'Board Settings', 'wpforo' ); ?>
				</h2>
			</div>
			<div class="wpforo-ai-box-body">
				<?php if ( $is_multiboard ) : ?>
				<div class="wpforo-ai-board-selector">
					<label for="knowledge-board-select"><?php _e( 'Select Board:', 'wpforo' ); ?></label>
					<select id="knowledge-board-select">
						<?php foreach ( $all_boards as $board ) : ?>
						<option value="<?php echo esc_attr( $board['boardid'] ); ?>">
							<?php echo esc_html( $board['title'] ); ?>
						</option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php else : ?>
				<input type="hidden" id="knowledge-board-select" value="0">
				<?php endif; ?>

				<form id="wpforo-ai-knowledge-settings-form" class="wpforo-ai-settings-form">
					<input type="hidden" name="board_id" id="knowledge-settings-board-id" value="0">

					<div id="knowledge-settings-loading" class="wpforo-ai-loading">
						<span class="spinner is-active"></span>
						<?php _e( 'Loading settings...', 'wpforo' ); ?>
					</div>

					<div id="knowledge-settings-content" style="display: none;">
						<!-- Enable/Disable Toggle -->
						<div class="wpforo-ai-setting-row wpforo-ai-enable-row">
							<label class="wpforo-ai-toggle-label">
								<span class="wpforo-ai-toggle">
									<input type="checkbox" name="enabled" id="knowledge-enabled" value="1">
									<span class="wpforo-ai-toggle-slider"></span>
								</span>
								<span class="toggle-text"><?php _e( 'Enable Custom Knowledge for this board', 'wpforo' ); ?></span>
							</label>
							<p class="description"><?php _e( 'When enabled, AI Search, Chatbot, and Bot Reply will include your custom knowledge files.', 'wpforo' ); ?></p>
						</div>

						<!-- Priority Settings (hidden by default, shown when enabled) -->
						<div id="knowledge-priority-section" style="display: none;">
							<h4><?php _e( 'Source Priority', 'wpforo' ); ?></h4>
							<p class="description"><?php _e( 'Higher priority sources appear first and influence AI responses more.', 'wpforo' ); ?></p>

							<div class="wpforo-ai-priority-row">
								<label><?php _e( 'AI Search', 'wpforo' ); ?></label>
								<div class="wpforo-ai-priority-selects">
									<select name="search_priority[]" class="priority-select" data-feature="search">
										<option value="forum"><?php _e( 'Forum', 'wpforo' ); ?></option>
										<option value="wordpress"><?php _e( 'WordPress', 'wpforo' ); ?></option>
										<option value="custom_knowledge"><?php _e( 'Custom Knowledge', 'wpforo' ); ?></option>
									</select>
									<span class="priority-arrow">→</span>
									<select name="search_priority[]" class="priority-select" data-feature="search">
										<option value="wordpress"><?php _e( 'WordPress', 'wpforo' ); ?></option>
										<option value="forum"><?php _e( 'Forum', 'wpforo' ); ?></option>
										<option value="custom_knowledge"><?php _e( 'Custom Knowledge', 'wpforo' ); ?></option>
									</select>
									<span class="priority-arrow">→</span>
									<select name="search_priority[]" class="priority-select" data-feature="search">
										<option value="custom_knowledge"><?php _e( 'Custom Knowledge', 'wpforo' ); ?></option>
										<option value="forum"><?php _e( 'Forum', 'wpforo' ); ?></option>
										<option value="wordpress"><?php _e( 'WordPress', 'wpforo' ); ?></option>
									</select>
								</div>
							</div>

							<div class="wpforo-ai-priority-row">
								<label><?php _e( 'AI Chatbot', 'wpforo' ); ?></label>
								<div class="wpforo-ai-priority-selects">
									<select name="chat_priority[]" class="priority-select" data-feature="chat">
										<option value="custom_knowledge"><?php _e( 'Custom Knowledge', 'wpforo' ); ?></option>
										<option value="forum"><?php _e( 'Forum', 'wpforo' ); ?></option>
										<option value="wordpress"><?php _e( 'WordPress', 'wpforo' ); ?></option>
									</select>
									<span class="priority-arrow">→</span>
									<select name="chat_priority[]" class="priority-select" data-feature="chat">
										<option value="forum"><?php _e( 'Forum', 'wpforo' ); ?></option>
										<option value="custom_knowledge"><?php _e( 'Custom Knowledge', 'wpforo' ); ?></option>
										<option value="wordpress"><?php _e( 'WordPress', 'wpforo' ); ?></option>
									</select>
									<span class="priority-arrow">→</span>
									<select name="chat_priority[]" class="priority-select" data-feature="chat">
										<option value="wordpress"><?php _e( 'WordPress', 'wpforo' ); ?></option>
										<option value="forum"><?php _e( 'Forum', 'wpforo' ); ?></option>
										<option value="custom_knowledge"><?php _e( 'Custom Knowledge', 'wpforo' ); ?></option>
									</select>
								</div>
							</div>

							<div class="wpforo-ai-priority-row">
								<label><?php _e( 'Bot Reply', 'wpforo' ); ?></label>
								<div class="wpforo-ai-priority-selects">
									<select name="bot_reply_priority[]" class="priority-select" data-feature="bot_reply">
										<option value="forum"><?php _e( 'Forum', 'wpforo' ); ?></option>
										<option value="custom_knowledge"><?php _e( 'Custom Knowledge', 'wpforo' ); ?></option>
										<option value="wordpress"><?php _e( 'WordPress', 'wpforo' ); ?></option>
									</select>
									<span class="priority-arrow">→</span>
									<select name="bot_reply_priority[]" class="priority-select" data-feature="bot_reply">
										<option value="custom_knowledge"><?php _e( 'Custom Knowledge', 'wpforo' ); ?></option>
										<option value="forum"><?php _e( 'Forum', 'wpforo' ); ?></option>
										<option value="wordpress"><?php _e( 'WordPress', 'wpforo' ); ?></option>
									</select>
									<span class="priority-arrow">→</span>
									<select name="bot_reply_priority[]" class="priority-select" data-feature="bot_reply">
										<option value="wordpress"><?php _e( 'WordPress', 'wpforo' ); ?></option>
										<option value="forum"><?php _e( 'Forum', 'wpforo' ); ?></option>
										<option value="custom_knowledge"><?php _e( 'Custom Knowledge', 'wpforo' ); ?></option>
									</select>
								</div>
							</div>
						</div>

						<div class="wpforo-ai-form-actions">
							<button type="submit" class="button button-primary" id="knowledge-save-settings">
								<?php _e( 'Save Settings', 'wpforo' ); ?>
							</button>
							<span class="wpforo-ai-save-status" id="settings-save-status"></span>
						</div>
					</div>
				</form>
			</div>
		</div>

	</div>

	<!-- Delete Confirmation Modal -->
	<div id="knowledge-delete-modal" class="wpforo-ai-modal" style="display: none;">
		<div class="wpforo-ai-modal-content">
			<div class="wpforo-ai-modal-header">
				<h3><?php _e( 'Delete Knowledge File', 'wpforo' ); ?></h3>
				<button type="button" class="wpforo-ai-modal-close">&times;</button>
			</div>
			<div class="wpforo-ai-modal-body">
				<p><?php _e( 'Are you sure you want to delete this knowledge file? This will remove all indexed vectors and cannot be undone.', 'wpforo' ); ?></p>
				<p class="wpforo-ai-delete-file-name"></p>
			</div>
			<div class="wpforo-ai-modal-footer">
				<button type="button" class="button" id="knowledge-delete-cancel"><?php _e( 'Cancel', 'wpforo' ); ?></button>
				<button type="button" class="button button-danger" id="knowledge-delete-confirm"><?php _e( 'Delete', 'wpforo' ); ?></button>
			</div>
		</div>
	</div>

	<?php
}
