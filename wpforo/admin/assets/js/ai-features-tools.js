/**
 * wpForo AI Features - AI Tools (Custom Knowledge)
 *
 * Isolated JavaScript for AI Tools tab.
 * Handles custom knowledge file management and priority settings.
 *
 * @package wpForo
 * @subpackage Admin
 * @since 3.0.0
 */

(function($) {
	'use strict';

	const WpForoAITools = {
		initialized: false,
		deleteFileId: null,
		deleteFileName: null,
		mediaFrame: null,

		/**
		 * Initialize AI Tools features
		 */
		init: function() {
			if (this.initialized) return;

			// Only init if on AI Tools tab
			if (!$('.wpforo-ai-tools-tab').length) return;

			this.initialized = true;
			this.bindEvents();
			this.loadKnowledgeFiles();
			this.loadBoardSettings();
		},

		/**
		 * Bind all event handlers
		 */
		bindEvents: function() {
			const self = this;

			// Media Library button (TXT/MD/JSON)
			$(document).on('click', '#knowledge-media-btn', function(e) {
				e.preventDefault();
				self.openMediaLibrary('text');
			});

			// Media Library button (PDF)
			$(document).on('click', '#knowledge-pdf-media-btn', function(e) {
				e.preventDefault();
				self.openMediaLibrary('pdf');
			});

			// URL input change - auto-detect file info (TXT/MD/JSON)
			$(document).on('input', '#knowledge-file-url', function() {
				self.detectFileInfo($(this).val(), 'text');
			});

			// URL input change - auto-detect file info (PDF)
			$(document).on('input', '#knowledge-pdf-file-url', function() {
				self.detectFileInfo($(this).val(), 'pdf');
			});

			// Add knowledge form (TXT/MD/JSON)
			$(document).on('submit', '#wpforo-ai-knowledge-upload-form', function(e) {
				e.preventDefault();
				self.handleAddKnowledge('text');
			});

			// Add knowledge form (PDF)
			$(document).on('submit', '#wpforo-ai-knowledge-pdf-upload-form', function(e) {
				e.preventDefault();
				self.handleAddKnowledge('pdf');
			});

			// Delete knowledge button
			$(document).on('click', '.wpforo-ai-delete-file', function(e) {
				e.preventDefault();
				self.handleDeleteClick($(this));
			});

			// Board selector change
			$(document).on('change', '#knowledge-board-select', function() {
				self.loadBoardSettings();
			});

			// Enable toggle change - show/hide priority section
			$(document).on('change', '#knowledge-enabled', function() {
				self.togglePrioritySection($(this).is(':checked'));
			});

			// Save settings form (combined enable + priorities)
			$(document).on('submit', '#wpforo-ai-knowledge-settings-form', function(e) {
				e.preventDefault();
				self.handleSaveSettings();
			});

			// Refresh files button
			$(document).on('click', '#knowledge-refresh-files', function(e) {
				e.preventDefault();
				self.handleRefreshFiles();
			});

			// Delete confirmation modal
			$(document).on('click', '#knowledge-delete-confirm', function(e) {
				e.preventDefault();
				self.confirmDelete();
			});
			$(document).on('click', '#knowledge-delete-cancel, .wpforo-ai-modal-close', function(e) {
				e.preventDefault();
				self.cancelDelete();
			});

			// Priority select change - prevent duplicate selections
			$(document).on('change', '.priority-select', function() {
				self.handlePriorityChange($(this));
			});
		},

		/**
		 * Open WordPress Media Library.
		 *
		 * kind: 'text' (default — TXT/MD/JSON form) or 'pdf' (PDF form).
		 */
		openMediaLibrary: function(kind) {
			const self = this;
			kind = kind === 'pdf' ? 'pdf' : 'text';

			const isPdf = kind === 'pdf';
			const frameKey = isPdf ? 'mediaFramePdf' : 'mediaFrame';
			const urlInputId = isPdf ? '#knowledge-pdf-file-url' : '#knowledge-file-url';
			const mimeFilter = isPdf
				? ['application/pdf']
				: ['application/json', 'text/plain', 'text/markdown', 'text/x-markdown'];
			const title = isPdf ? 'Select PDF File' : 'Select Knowledge File';

			// Reuse the per-kind frame if already created
			if (this[frameKey]) {
				this[frameKey].open();
				return;
			}

			this[frameKey] = wp.media({
				title: title,
				button: { text: 'Use This File' },
				multiple: false,
				library: { type: mimeFilter }
			});

			this[frameKey].on('select', function() {
				const attachment = self[frameKey].state().get('selection').first().toJSON();
				const url = attachment.url;

				$(urlInputId).val(url);
				self.detectFileInfo(url, kind);
			});

			this[frameKey].open();
		},

		/**
		 * Detect file type and name from URL.
		 *
		 * kind: 'text' (TXT/MD/JSON form) or 'pdf' (PDF form). The PDF form
		 * keeps file_type=pdf regardless of detected extension, but still
		 * updates the display name and badge for the user.
		 */
		detectFileInfo: function(url, kind) {
			kind = kind === 'pdf' ? 'pdf' : 'text';
			const isPdf = kind === 'pdf';

			const $detected = $(isPdf ? '#knowledge-pdf-file-detected' : '#knowledge-file-detected');
			const $typeInput = $(isPdf ? '#knowledge-pdf-file-type' : '#knowledge-file-type');
			const $nameInput = $(isPdf ? '#knowledge-pdf-file-name' : '#knowledge-file-name');

			if (!url || !url.trim()) {
				$detected.hide();
				$typeInput.val(isPdf ? 'pdf' : 'text');
				$nameInput.val('');
				return;
			}

			// Extract filename from URL
			let filename = '';
			try {
				const urlObj = new URL(url);
				const path = urlObj.pathname;
				filename = path.split('/').pop() || '';
			} catch (e) {
				// Invalid URL, try simple extraction
				filename = url.split('/').pop().split('?')[0] || '';
			}

			if (!filename) {
				$detected.hide();
				return;
			}

			// Detect file type from extension
			const ext = filename.split('.').pop().toLowerCase();
			let fileType = isPdf ? 'pdf' : 'text';
			let typeLabel = isPdf ? 'PDF' : 'TEXT';
			let icon = isPdf ? '📕' : '📄';

			if (isPdf) {
				// PDF form: force-set type=pdf, but keep label honest if a non-PDF was pasted
				fileType = 'pdf';
				if (ext !== 'pdf') {
					typeLabel = ext.toUpperCase();
				}
			} else if (ext === 'json') {
				fileType = 'json';
				typeLabel = 'JSON';
				icon = '📋';
			} else if (ext === 'md' || ext === 'markdown') {
				fileType = 'markdown';
				typeLabel = 'MD';
				icon = '📝';
			} else if (ext === 'txt') {
				fileType = 'text';
				typeLabel = 'TXT';
				icon = '📄';
			}

			// Get display name (filename without extension)
			const displayName = filename.replace(/\.[^/.]+$/, '').replace(/[-_]/g, ' ');

			// Update hidden fields
			$typeInput.val(fileType);
			$nameInput.val(displayName);

			// Show detection UI
			$detected.find('.file-icon').text(icon);
			$detected.find('.file-name').text(displayName);
			$detected.find('.file-type-badge').text(typeLabel);
			$detected.show();
		},

		/**
		 * Handle add-knowledge form submission.
		 *
		 * kind: 'text' (TXT/MD/JSON form) or 'pdf' (PDF form) — controls
		 * which form's inputs are read.
		 */
		handleAddKnowledge: function(kind) {
			const self = this;
			kind = kind === 'pdf' ? 'pdf' : 'text';
			const isPdf = kind === 'pdf';

			const $form = $(isPdf ? '#wpforo-ai-knowledge-pdf-upload-form' : '#wpforo-ai-knowledge-upload-form');
			const $button = $(isPdf ? '#knowledge-pdf-upload-btn' : '#knowledge-upload-btn');
			const $progress = $(isPdf ? '#knowledge-pdf-upload-progress' : '#knowledge-upload-progress');
			const $detected = $(isPdf ? '#knowledge-pdf-file-detected' : '#knowledge-file-detected');

			const fileUrl = $(isPdf ? '#knowledge-pdf-file-url' : '#knowledge-file-url').val().trim();
			const fileType = $(isPdf ? '#knowledge-pdf-file-type' : '#knowledge-file-type').val();
			const fileName = $(isPdf ? '#knowledge-pdf-file-name' : '#knowledge-file-name').val().trim();

			if (!fileUrl) {
				this.showNotice('Please enter a file URL.', 'error');
				return;
			}

			// Reject mismatched extensions on the client to avoid a confusing
			// "type=pdf but file is .txt" round-trip to the backend.
			const urlExt = (fileUrl.split('?')[0].split('#')[0].split('.').pop() || '').toLowerCase();
			if (isPdf && urlExt !== 'pdf') {
				this.showNotice(
					'This form is for PDF files. Please use the form above for TXT, MD, or JSON files.',
					'error'
				);
				return;
			}
			if (!isPdf && urlExt === 'pdf') {
				this.showNotice(
					'PDF files should be uploaded via the PDF form below.',
					'error'
				);
				return;
			}

			$button.prop('disabled', true).addClass('updating-message');
			$progress.show();
			$progress.find('.wpforo-ai-progress-text').text('Submitting...');

			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_add_knowledge',
					nonce: wpforoAIAdmin.nonce,
					file_url: fileUrl,
					file_type: fileType,
					file_name: fileName
				},
				success: function(response) {
					if (response.success) {
						const data = response.data;
						$form[0].reset();
						$detected.hide();
						// PDF form's hidden file_type defaults back to 'pdf' after reset
						if (isPdf) {
							$('#knowledge-pdf-file-type').val('pdf');
						}

						if (data.async && data.file_id) {
							// Async processing - start polling for status
							self.showNotice('File queued for processing. This may take a few minutes...', 'info');
							self.loadKnowledgeFiles();
							self.startPollingJobStatus(data.file_id, data.name || fileName);
						} else {
							// Sync processing complete
							self.showNotice(data.message || 'Knowledge file added successfully.', 'success');
							self.loadKnowledgeFiles();
						}
					} else {
						self.showNotice(response.data.message || 'Failed to add knowledge file.', 'error');
					}
				},
				error: function(xhr, status, error) {
					self.showNotice('Network error: ' + error, 'error');
				},
				complete: function() {
					$button.prop('disabled', false).removeClass('updating-message');
					$progress.hide();
				}
			});
		},

		/**
		 * Poll for async job status
		 */
		startPollingJobStatus: function(fileId, fileName) {
			const self = this;
			const pollInterval = 5000; // 5 seconds
			// Backend Lambda timeout is 300s. Poll for 6 min so a job that
			// finishes right at the deadline is still picked up cleanly.
			const maxPolls = 72; // Max 6 minutes
			let pollCount = 0;

			const poll = function() {
				pollCount++;
				if (pollCount > maxPolls) {
					self.showNotice('Processing is taking longer than expected. Check the file list for status.', 'warning');
					return;
				}

				$.ajax({
					url: wpforoAIAdmin.ajaxUrl,
					type: 'POST',
					data: {
						action: 'wpforo_ai_get_job_status',
						nonce: wpforoAIAdmin.nonce,
						file_id: fileId
					},
					success: function(response) {
						if (response.success) {
							const status = response.data.status;

							if (status === 'enabled' || status === 'completed') {
								self.showNotice('"' + fileName + '" indexed successfully! (' + (response.data.chunk_count || 0) + ' chunks)', 'success');
								self.loadKnowledgeFiles();
							} else if (status === 'failed') {
								self.showNotice('Indexing failed: ' + (response.data.error_message || 'Unknown error'), 'error');
								self.loadKnowledgeFiles();
							} else {
								// Still processing - poll again
								setTimeout(poll, pollInterval);
							}
						} else {
							// Error getting status - poll again
							setTimeout(poll, pollInterval);
						}
					},
					error: function() {
						// Network error - poll again
						setTimeout(poll, pollInterval);
					}
				});
			};

			// Start polling after a short delay
			setTimeout(poll, 2000);
		},

		/**
		 * Handle delete button click - show modal
		 */
		handleDeleteClick: function($button) {
			this.deleteFileId = $button.data('file-id');
			this.deleteFileName = $button.data('file-name') || this.deleteFileId;

			$('.wpforo-ai-delete-file-name').text(this.deleteFileName);
			$('#knowledge-delete-modal').show();
		},

		/**
		 * Confirm delete action
		 */
		confirmDelete: function() {
			const self = this;
			const $modal = $('#knowledge-delete-modal');
			const $button = $('#knowledge-delete-confirm');
			const fileId = this.deleteFileId;

			if (!fileId) {
				$modal.hide();
				return;
			}

			// Close modal immediately and show "deleting" status on the file row
			$modal.hide();
			$button.prop('disabled', false).removeClass('updating-message');

			// Update the file row to show "deleting" status with spinner
			const $row = $('tr[data-file-id="' + fileId + '"]');
			if ($row.length) {
				$row.find('.column-status .wpforo-ai-status-badge')
					.removeClass('status-enabled status-disabled status-processing status-queued status-error')
					.addClass('status-deleting')
					.html('<span class="wpforo-ai-mini-spinner"></span>deleting');
				// Disable the delete button for this row
				$row.find('.wpforo-ai-delete-file').prop('disabled', true).css('opacity', '0.5');
			}

			// Clear delete state
			this.deleteFileId = null;
			this.deleteFileName = null;

			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				timeout: 60000,
				data: {
					action: 'wpforo_ai_delete_knowledge',
					nonce: wpforoAIAdmin.nonce,
					file_id: fileId
				},
				success: function(response) {
					if (response.success) {
						self.showNotice(response.data.message || 'Knowledge file deleted successfully.', 'success');
						// Remove the row directly - don't rely on reload which might get stale data
						$row.fadeOut(300, function() {
							$(this).remove();
							// Update file count
							const remaining = $('#knowledge-files-tbody tr').length;
							$('#knowledge-file-count').text('(' + remaining + ')');
							// Show empty state if no files left
							if (remaining === 0) {
								$('#knowledge-files-table').hide();
								$('#knowledge-files-empty').show();
							}
						});
					} else {
						self.showNotice(response.data.message || 'Failed to delete knowledge file.', 'error');
						// Restore the row on failure
						self.loadKnowledgeFiles();
					}
				},
				error: function(xhr, status, error) {
					if (status === 'timeout') {
						self.showNotice('Deletion is taking longer than expected. Please refresh the page in a moment.', 'warning');
					} else {
						self.showNotice('Network error: ' + error, 'error');
					}
					// Reload to get current state
					self.loadKnowledgeFiles();
				}
			});
		},

		/**
		 * Cancel delete action
		 */
		cancelDelete: function() {
			this.deleteFileId = null;
			this.deleteFileName = null;
			$('#knowledge-delete-modal').hide();
		},

		/**
		 * Handle priority select change - prevent duplicates within same feature
		 */
		handlePriorityChange: function($select) {
			const feature = $select.data('feature');
			const selectedValue = $select.val();

			// Get all selects for this feature
			const $featureSelects = $('.priority-select[data-feature="' + feature + '"]');
			const selectIndex = $featureSelects.index($select);

			// Find which other select has the same value and swap
			$featureSelects.each(function(index) {
				const $other = $(this);

				if (index !== selectIndex && $other.val() === selectedValue) {
					// Find an available value for the other select
					const usedValues = [];
					$featureSelects.each(function(i) {
						if (i !== index) {
							usedValues.push($(this).val());
						}
					});

					const allValues = ['forum', 'wordpress', 'custom_knowledge'];
					for (let i = 0; i < allValues.length; i++) {
						if (usedValues.indexOf(allValues[i]) === -1) {
							$other.val(allValues[i]);
							break;
						}
					}
				}
			});
		},

		/**
		 * Load board settings from WordPress (per-board)
		 */
		loadBoardSettings: function() {
			const self = this;
			const boardId = $('#knowledge-board-select').val() || '0';
			const $loading = $('#knowledge-settings-loading');
			const $content = $('#knowledge-settings-content');

			$loading.show();
			$content.hide();

			// Update hidden field
			$('#knowledge-settings-board-id').val(boardId);

			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_get_knowledge_settings',
					nonce: wpforoAIAdmin.nonce,
					board_id: boardId
				},
				success: function(response) {
					$loading.hide();
					$content.show();

					if (response.success && response.data) {
						const enabled = response.data.enabled || false;
						const priorities = response.data.priorities || {};

						// Set enabled toggle
						$('#knowledge-enabled').prop('checked', enabled);
						self.togglePrioritySection(enabled);

						// Set priority selects
						self.updatePrioritySelects(priorities);
					} else {
						// Use defaults
						$('#knowledge-enabled').prop('checked', false);
						self.togglePrioritySection(false);
						self.updatePrioritySelects({});
					}
				},
				error: function() {
					$loading.hide();
					$content.show();
					// Use defaults on error
					$('#knowledge-enabled').prop('checked', false);
					self.togglePrioritySection(false);
					self.updatePrioritySelects({});
				}
			});
		},

		/**
		 * Toggle visibility of priority section based on enabled state
		 */
		togglePrioritySection: function(enabled) {
			const $section = $('#knowledge-priority-section');
			if (enabled) {
				$section.slideDown(200);
			} else {
				$section.slideUp(200);
			}
		},

		/**
		 * Handle save settings form (combined enable + priorities)
		 */
		handleSaveSettings: function() {
			const self = this;
			const $form = $('#wpforo-ai-knowledge-settings-form');
			const $button = $('#knowledge-save-settings');
			const $status = $('#settings-save-status');

			const boardId = $('#knowledge-settings-board-id').val();
			const enabled = $('#knowledge-enabled').is(':checked');

			// Collect priorities from selects - build arrays in order
			const priorities = {
				search_priority: [],
				chat_priority: [],
				bot_reply_priority: []
			};

			['search', 'chat', 'bot_reply'].forEach(function(feature) {
				$form.find('[name="' + feature + '_priority[]"]').each(function() {
					priorities[feature + '_priority'].push($(this).val());
				});
			});

			$button.prop('disabled', true).addClass('updating-message');
			$status.text('Saving...');

			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_save_knowledge_settings',
					nonce: wpforoAIAdmin.nonce,
					board_id: boardId,
					enabled: enabled ? 1 : 0,
					search_priority: priorities.search_priority,
					chat_priority: priorities.chat_priority,
					bot_reply_priority: priorities.bot_reply_priority
				},
				success: function(response) {
					if (response.success) {
						$status.text('Saved!').addClass('success');
						setTimeout(function() {
							$status.text('').removeClass('success');
						}, 2000);
					} else {
						self.showNotice(response.data.message || 'Failed to save settings.', 'error');
						$status.text('');
					}
				},
				error: function(xhr, status, error) {
					self.showNotice('Network error: ' + error, 'error');
					$status.text('');
				},
				complete: function() {
					$button.prop('disabled', false).removeClass('updating-message');
				}
			});
		},

		/**
		 * Handle refresh files button
		 */
		handleRefreshFiles: function() {
			const self = this;
			const $button = $('#knowledge-refresh-files');
			const $icon = $button.find('.dashicons-update');

			$icon.addClass('wpforo-spin');
			$button.prop('disabled', true);

			this.loadKnowledgeFiles(function() {
				$icon.removeClass('wpforo-spin');
				$button.prop('disabled', false);
			});
		},

		/**
		 * Load knowledge files from API
		 */
		loadKnowledgeFiles: function(callback) {
			const self = this;
			const $loading = $('#knowledge-files-loading');
			const $table = $('#knowledge-files-table');
			const $empty = $('#knowledge-files-empty');

			$loading.show();
			$table.hide();
			$empty.hide();

			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_get_knowledge_files',
					nonce: wpforoAIAdmin.nonce
				},
				success: function(response) {
					$loading.hide();

					if (response.success) {
						const files = response.data.files || [];
						const totals = response.data.totals || {};

						if (files.length > 0) {
							self.renderFilesTable(files, totals);
							$table.show();
						} else {
							$empty.show();
						}

						// Update file count
						$('#knowledge-file-count').text('(' + files.length + ')');
					} else {
						// API returned error - show empty state
						$empty.show();
					}
				},
				error: function(xhr, status, error) {
					// Network/API error - show empty state silently
					// Backend endpoints may not exist yet (Phase 6)
					$loading.hide();
					$empty.show();
				},
				complete: function() {
					if (typeof callback === 'function') {
						callback();
					}
				}
			});
		},

		/**
		 * Render files table
		 */
		renderFilesTable: function(files, totals) {
			const self = this;
			const $tbody = $('#knowledge-files-tbody');
			$tbody.empty();

			let totalChunks = 0;
			let totalCredits = 0;
			let totalSize = 0;

			files.forEach(function(file) {
				const statusLabel = file.status || 'unknown';
				const statusClass = statusLabel === 'enabled' ? 'status-enabled' :
				                   statusLabel === 'disabled' ? 'status-disabled' :
				                   statusLabel === 'processing' ? 'status-processing' :
				                   statusLabel === 'queued' ? 'status-queued' :
				                   statusLabel === 'pending' ? 'status-pending' :
				                   statusLabel === 'deleting' ? 'status-deleting' :
				                   statusLabel === 'error' || statusLabel === 'failed' ? 'status-error' : '';

				const sizeBytes = parseInt(file.size_bytes || 0, 10);
				totalSize += sizeBytes;
				totalChunks += parseInt(file.chunks || 0, 10);
				totalCredits += parseInt(file.credits_used || 0, 10);

				const row = '<tr data-file-id="' + self.escapeHtml(file.file_id) + '">' +
					'<td class="column-name">' +
						'<strong>' + self.escapeHtml(file.name || file.file_id) + '</strong>' +
						'<div class="row-actions">' +
							'<span class="view">' +
								'<a href="' + self.escapeHtml(file.url) + '" target="_blank" rel="noopener">View</a>' +
							'</span>' +
						'</div>' +
					'</td>' +
					'<td class="column-type">' + self.escapeHtml(file.type || 'text') + '</td>' +
					'<td class="column-size">' + self.formatFileSize(sizeBytes) + '</td>' +
					'<td class="column-chunks">' + (file.chunks || 0) + '</td>' +
					'<td class="column-credits">' + (file.credits_used || 0) + '</td>' +
					'<td class="column-status">' +
						'<span class="wpforo-ai-status-badge ' + statusClass + '">' +
							(statusLabel === 'processing' || statusLabel === 'queued' || statusLabel === 'deleting' ? '<span class="wpforo-ai-mini-spinner"></span>' : '') +
							self.escapeHtml(statusLabel) +
						'</span>' +
					'</td>' +
					'<td class="column-actions">' +
						'<button type="button" class="wpforo-ai-delete-file" title="Delete this file" data-file-id="' + self.escapeHtml(file.file_id) + '" data-file-name="' + self.escapeHtml(file.name || file.file_id) + '">' +
							'<span class="dashicons dashicons-trash"></span>' +
						'</button>' +
					'</td>' +
				'</tr>';

				$tbody.append(row);
			});

			// Update totals - prefer server-provided totals if available
			$('#knowledge-total-size').text(self.formatFileSize(totalSize));
			$('#knowledge-total-chunks').text(totals.total_chunks || totalChunks);
			$('#knowledge-total-credits').text(totals.total_credits || totalCredits);
		},

		/**
		 * Format file size in human readable format
		 */
		formatFileSize: function(bytes) {
			if (bytes === 0) return '-';
			if (bytes < 1024) return bytes + ' B';
			if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
			return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
		},

		/**
		 * Update priority selects with current values from server
		 */
		updatePrioritySelects: function(priorities) {
			// Default priorities per feature
			const defaults = {
				search: ['forum', 'wordpress', 'custom_knowledge'],
				chat: ['custom_knowledge', 'forum', 'wordpress'],
				bot_reply: ['forum', 'custom_knowledge', 'wordpress']
			};

			const features = ['search', 'chat', 'bot_reply'];

			features.forEach(function(feature) {
				// PHP returns priorities.search, priorities.chat, etc. (no _priority suffix)
				const priority = priorities[feature];
				const order = (Array.isArray(priority) && priority.length === 3) ? priority : defaults[feature];

				// Select array-based inputs: name="search_priority[]"
				const $selects = $('[name="' + feature + '_priority[]"]');
				$selects.each(function(index) {
					if (order[index]) {
						$(this).val(order[index]);
					}
				});
			});
		},

		/**
		 * Show notice
		 */
		showNotice: function(message, type) {
			const $container = $('.wpforo-ai-tools-tab');
			const noticeClass = type === 'error' ? 'notice-error' : type === 'success' ? 'notice-success' : 'notice-info';

			// Remove existing notices
			$container.find('.wpforo-ai-notice').remove();

			const $notice = $('<div class="notice ' + noticeClass + ' wpforo-ai-notice is-dismissible" style="margin: 15px 0;">' +
				'<p>' + message + '</p>' +
				'<button type="button" class="notice-dismiss">' +
					'<span class="screen-reader-text">Dismiss this notice.</span>' +
				'</button>' +
			'</div>');

			$container.prepend($notice);

			// Auto-dismiss after 5 seconds
			setTimeout(function() {
				$notice.fadeOut(function() {
					$(this).remove();
				});
			}, 5000);

			// Manual dismiss
			$notice.on('click', '.notice-dismiss', function() {
				$notice.fadeOut(function() {
					$(this).remove();
				});
			});
		},

		/**
		 * Escape HTML to prevent XSS
		 */
		escapeHtml: function(text) {
			if (text === null || text === undefined) return '';
			const div = document.createElement('div');
			div.textContent = String(text);
			return div.innerHTML;
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		WpForoAITools.init();
	});

	// Expose globally for debugging
	window.WpForoAITools = WpForoAITools;

})(jQuery);
