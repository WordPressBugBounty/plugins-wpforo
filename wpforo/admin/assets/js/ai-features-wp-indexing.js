/**
 * wpForo AI Features - WordPress Content Indexing
 *
 * Isolated JavaScript for WordPress content indexing tab.
 * Completely independent from forum indexing handlers.
 *
 * @package wpForo
 * @subpackage Admin
 * @since 3.0.0
 */

(function($) {
	'use strict';

	const WpForoWPIndexing = {
		initialized: false,
		wpIndexingPollInterval: null,
		_wpAutoRefreshTimeout: null,

		/**
		 * Initialize WordPress content indexing features
		 */
		init: function() {
			if (this.initialized) return;

			// Only init if on WordPress Indexing tab
			if (!$('.wpforo-ai-wp-indexing-tab').length) return;

			this.initialized = true;
			this.bindEvents();
			this.loadWPIndexingStatus();
			this.checkWPIndexingAutoRefresh();
		},

		/**
		 * Bind all event handlers
		 */
		bindEvents: function() {
			const self = this;

			// Refresh status button
			$(document).on('click', '.wpforo-ai-wp-refresh-status', this.handleRefreshWPStatus.bind(this));

			// Taxonomy dropdown change
			$(document).on('change', '#wp-taxonomy-select', this.handleTaxonomyChange.bind(this));

			// Form submissions
			$(document).on('submit', '.wpforo-ai-wp-taxonomy-form', this.handleWPTaxonomyIndex.bind(this));
			$(document).on('submit', '.wpforo-ai-wp-custom-form', this.handleWPCustomIndex.bind(this));
			$(document).on('submit', '.wpforo-ai-wp-ids-form', this.handleWPIndexByIds.bind(this));

			// Clear index button
			$(document).on('click', '.wpforo-ai-wp-clear-index', this.handleWPClearIndex.bind(this));

			// Cleanup session button (WordPress-specific)
			$(document).on('click', '.wpforo-ai-wp-cleanup-session', this.handleCleanupSession.bind(this));

			// Toggle switches
			$(document).on('change', '#wpforo-ai-wp-auto-indexing', this.handleWPAutoIndexingToggle.bind(this));
			$(document).on('change', '#wpforo-ai-wp-image-indexing', this.handleWPImageIndexingToggle.bind(this));

			// Select All / Deselect All for terms
			$(document).on('click', '.wpforo-ai-wp-select-all-terms', function() {
				$('#wp-terms-container input[type="checkbox"]').prop('checked', true);
				self.updateTermIndexButton();
			});
			$(document).on('click', '.wpforo-ai-wp-deselect-all-terms', function() {
				$('#wp-terms-container input[type="checkbox"]').prop('checked', false);
				self.updateTermIndexButton();
			});
		},

		/**
		 * Refresh WordPress indexing status
		 */
		handleRefreshWPStatus: function(e) {
			e.preventDefault();
			const self = this;
			const $button = $(e.currentTarget);
			const $icon = $button.find('.dashicons-update');

			$icon.addClass('wpforo-spin');
			$button.prop('disabled', true);

			this.loadWPIndexingStatus(function() {
				$icon.removeClass('wpforo-spin');
				$button.prop('disabled', false);
			});
		},

		/**
		 * Load WordPress indexing status from API
		 */
		loadWPIndexingStatus: function(callback) {
			const self = this;

			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_wp_get_indexing_status',
					security: wpforoAIAdmin.adminNonce
				},
				success: function(response) {
					if (response.success && response.data) {
						self.updateWPIndexingDisplay(response.data);

						// Start polling if indexing is in progress
						if (response.data.queue && response.data.queue.status === 'processing') {
							self.startWPIndexingPolling();
						} else {
							self.stopWPIndexingPolling();
						}
					}
				},
				error: function(xhr, status, error) {
					console.error('Failed to load WordPress indexing status:', error);
				},
				complete: function() {
					if (typeof callback === 'function') {
						callback();
					}
				}
			});
		},

		/**
		 * Start polling for WordPress indexing status
		 */
		startWPIndexingPolling: function() {
			const self = this;

			// Don't start if already polling
			if (this.wpIndexingPollInterval) {
				return;
			}

			// Poll every 5 seconds
			this.wpIndexingPollInterval = setInterval(function() {
				self.loadWPIndexingStatus();
			}, 5000);
		},

		/**
		 * Stop polling for WordPress indexing status
		 */
		stopWPIndexingPolling: function() {
			if (this.wpIndexingPollInterval) {
				clearInterval(this.wpIndexingPollInterval);
				this.wpIndexingPollInterval = null;
			}
		},

		/**
		 * Start auto page refresh for WordPress content indexing.
		 */
		startWPIndexingAutoRefresh: function() {
			// Store flag to indicate we're in auto-refresh mode
			try {
				localStorage.setItem('wpforo_wp_indexing_auto_refresh', '1');
			} catch (e) { /* localStorage may be blocked */ }

			console.log('WP content indexing: reloading page to trigger cron nudge...');
			window.location.hash = 'wp-indexing-status-box'; window.location.reload();
		},

		/**
		 * Check on page load if auto-refresh should continue.
		 */
		checkWPIndexingAutoRefresh: function() {
			const self = this;

			// Check if we're in auto-refresh mode
			let inAutoRefresh = false;
			try {
				inAutoRefresh = localStorage.getItem('wpforo_wp_indexing_auto_refresh') === '1';
			} catch (e) { /* localStorage may be blocked */ }

			if (!inAutoRefresh) {
				return;
			}

			// Check current indexing status
			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_wp_get_indexing_status',
					security: wpforoAIAdmin.adminNonce
				},
				success: function(response) {
					if (response.success && response.data) {
						// Check if still processing
						if (response.data.queue && response.data.queue.status === 'processing') {
							console.log('WP indexing in progress, will refresh in 20 seconds...');
							// Schedule next refresh in 20 seconds
							self._wpAutoRefreshTimeout = setTimeout(function() {
								window.location.hash = 'wp-indexing-status-box'; window.location.reload();
							}, 20000);
						} else {
							// Done - clear auto-refresh flag and do final reload
							// to ensure server-rendered HTML shows correct state
							console.log('WP content indexing complete, final reload');
							self.stopWPIndexingAutoRefresh();
							window.location.hash = 'wp-indexing-status-box'; window.location.reload();
						}
					}
				},
				error: function() {
					// On error, stop auto-refresh to avoid infinite reload loop
					self.stopWPIndexingAutoRefresh();
				}
			});
		},

		/**
		 * Stop auto page refresh and clear the flag.
		 */
		stopWPIndexingAutoRefresh: function() {
			try {
				localStorage.removeItem('wpforo_wp_indexing_auto_refresh');
			} catch (e) { /* localStorage may be blocked */ }

			if (this._wpAutoRefreshTimeout) {
				clearTimeout(this._wpAutoRefreshTimeout);
				this._wpAutoRefreshTimeout = null;
			}
		},

		/**
		 * Update WordPress indexing display with status data
		 */
		updateWPIndexingDisplay: function(data) {
			// Update total indexed
			if (data.total_indexed !== undefined) {
				$('#wp-total-indexed').text(data.total_indexed.toLocaleString());

				// Update coverage percentage
				const totalContent = parseInt($('#wp-total-content').text().replace(/,/g, ''), 10) || 0;
				if (totalContent > 0) {
					const percentage = Math.round((data.total_indexed / totalContent) * 100 * 10) / 10;
					$('#wp-indexed-percentage').text(percentage + '%');
				}
			}

			// Update by_type counts
			if (data.by_type) {
				for (const [type, info] of Object.entries(data.by_type)) {
					const postType = type.replace('wp_', '');
					const $indexed = $('#wp-indexed-' + postType + ' .indexed-count');
					if ($indexed.length) {
						$indexed.text(info.indexed || 0);
					}
				}
			}

			// Update last activity
			if (data.last_indexed_at_formatted) {
				$('#wp-last-activity').text(data.last_indexed_at_formatted);
			} else if (!data.last_indexed_at) {
				$('#wp-last-activity').text(wpforoAIAdmin.strings?.noActivity || 'No activity yet');
			}

			// Get status elements
			const $statusElement = $('#wp-indexing-status');

			// Update status with spinner animation
			if (data.queue && data.queue.status === 'processing') {
				$statusElement.html(
					'<span class="dashicons dashicons-update-alt wpforo-wp-indexing-spin"></span> ' +
					(wpforoAIAdmin.strings?.indexing || 'Indexing...')
				).removeClass('status-idle').addClass('status-active');
			} else {
				$statusElement.html(
					'<span class="dashicons dashicons-yes-alt"></span> ' +
					(wpforoAIAdmin.strings?.idle || 'Idle')
				).removeClass('status-active').addClass('status-idle');
			}
		},

		/**
		 * Handle taxonomy dropdown change - load terms as checkboxes
		 */
		handleTaxonomyChange: function(e) {
			const self = this;
			const taxonomy = $(e.currentTarget).val();
			const $termsContainer = $('#wp-terms-container');
			const $termsActions = $('#wp-terms-actions');
			const $indexBtn = $('.wpforo-ai-wp-index-taxonomy');

			if (!taxonomy) {
				$termsContainer.html('<div class="wpforo-ai-terms-placeholder">Select a taxonomy first to load terms...</div>');
				$termsActions.hide();
				$indexBtn.prop('disabled', true);
				return;
			}

			$termsContainer.html('<div class="wpforo-ai-terms-loading"><span class="spinner is-active"></span> Loading terms...</div>');
			$termsActions.hide();

			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_wp_get_taxonomy_terms',
					security: wpforoAIAdmin.adminNonce,
					taxonomy: taxonomy
				},
				success: function(response) {
					if (response.success && response.data && response.data.terms) {
						const terms = response.data.terms;
						if (terms.length === 0) {
							$termsContainer.html('<div class="wpforo-ai-terms-placeholder">No terms found in this taxonomy.</div>');
							$termsActions.hide();
							$indexBtn.prop('disabled', true);
							return;
						}

						let html = '<div class="wpforo-ai-terms-checklist">';
						terms.forEach(function(term) {
							const indexed = term.indexed || 0;
							const total = term.count || 0;
							html += self.renderTermCheckbox(term, indexed, total, false);

							// Add children if any
							if (term.children && term.children.length) {
								term.children.forEach(function(child) {
									const childIndexed = child.indexed || 0;
									const childTotal = child.count || 0;
									html += self.renderTermCheckbox(child, childIndexed, childTotal, true);
								});
							}
						});
						html += '</div>';

						$termsContainer.html(html);
						$termsActions.show();

						// Bind checkbox change events
						$termsContainer.find('input[type="checkbox"]').on('change', function() {
							self.updateTermIndexButton();
						});

						self.updateTermIndexButton();
					} else {
						$termsContainer.html('<div class="wpforo-ai-terms-placeholder">Error loading terms.</div>');
						$termsActions.hide();
					}
				},
				error: function() {
					$termsContainer.html('<div class="wpforo-ai-terms-placeholder">Error loading terms.</div>');
					$termsActions.hide();
				}
			});
		},

		/**
		 * Render a single term checkbox item
		 */
		renderTermCheckbox: function(term, indexed, total, isChild) {
			const itemClass = isChild ? 'wpforo-ai-term-checkbox-item wpforo-ai-term-child' : 'wpforo-ai-term-checkbox-item';
			return '<label class="' + itemClass + '">' +
				'<input type="checkbox" name="term_ids[]" value="' + term.term_id + '" data-count="' + total + '">' +
				'<span class="term-name">' + this.escapeHtml(term.name) + '</span>' +
				'<span class="wpforo-ai-term-info">(' + indexed + '/' + total + ')</span>' +
				'</label>';
		},

		/**
		 * Update the index button state based on selected terms
		 */
		updateTermIndexButton: function() {
			const $indexBtn = $('.wpforo-ai-wp-index-taxonomy');
			const checkedCount = $('#wp-terms-container input[type="checkbox"]:checked').length;
			$indexBtn.prop('disabled', checkedCount === 0);
		},

		/**
		 * Escape HTML special characters
		 */
		escapeHtml: function(text) {
			const div = document.createElement('div');
			div.appendChild(document.createTextNode(text));
			return div.innerHTML;
		},

		/**
		 * Handle taxonomy-based indexing form submission
		 */
		handleWPTaxonomyIndex: function(e) {
			e.preventDefault();
			const self = this;
			const $form = $(e.currentTarget);
			const $button = $form.find('.wpforo-ai-wp-index-taxonomy');
			const taxonomy = $form.find('#wp-taxonomy-select').val();

			// Collect all selected term IDs from checkboxes
			const termIds = [];
			$('#wp-terms-container input[type="checkbox"]:checked').each(function() {
				termIds.push($(this).val());
			});

			if (!taxonomy || termIds.length === 0) {
				alert('Please select a taxonomy and at least one term.');
				return;
			}

			// Get selected post types
			const postTypes = [];
			$('.wpforo-ai-wp-type-checkbox:checked').each(function() {
				postTypes.push($(this).val());
			});

			if (postTypes.length === 0) {
				alert('Please select at least one content type.');
				return;
			}

			$button.prop('disabled', true).text('Indexing...');

			// Build request data including optional date range
			const requestData = {
				action: 'wpforo_ai_wp_index_by_taxonomy',
				security: wpforoAIAdmin.adminNonce,
				taxonomy: taxonomy,
				term_ids: termIds,
				post_types: postTypes
			};

			// Add date range if specified
			const dateFrom = $form.find('#wp-tax-date-from').val();
			const dateTo = $form.find('#wp-tax-date-to').val();
			if (dateFrom) requestData.date_from = dateFrom;
			if (dateTo) requestData.date_to = dateTo;

			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: requestData,
				success: function(response) {
					if (response.success) {
						// Start auto page refresh - each refresh triggers inline cron nudge
						self.startWPIndexingAutoRefresh();
					} else {
						alert('Error: ' + (response.data?.message || 'Unknown error'));
						$button.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Index Selected Terms');
					}
				},
				error: function() {
					alert('Error starting indexing. Please try again.');
					$button.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Index Selected Terms');
				}
			});
		},

		/**
		 * Handle custom indexing form submission
		 */
		handleWPCustomIndex: function(e) {
			e.preventDefault();
			const self = this;
			const $form = $(e.currentTarget);
			const $button = $form.find('.wpforo-ai-wp-index-custom');

			// Get selected post types from within this form
			const postTypes = [];
			$form.find('.wpforo-ai-wp-type-checkbox:checked').each(function() {
				postTypes.push($(this).val());
			});

			if (postTypes.length === 0) {
				alert('Please select at least one content type.');
				return;
			}

			const data = {
				action: 'wpforo_ai_wp_index_custom',
				security: wpforoAIAdmin.adminNonce,
				post_types: postTypes,
				date_from: $form.find('#wp-date-from').val(),
				date_to: $form.find('#wp-date-to').val()
			};

			$button.prop('disabled', true).text('Indexing...');

			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: data,
				success: function(response) {
					if (response.success) {
						// Start auto page refresh - each refresh triggers inline cron nudge
						self.startWPIndexingAutoRefresh();
					} else {
						alert('Error: ' + (response.data?.message || 'Unknown error'));
						$button.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Index Selected Content');
					}
				},
				error: function() {
					alert('Error starting indexing. Please try again.');
					$button.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Index Selected Content');
				}
			});
		},

		/**
		 * Handle index by specific IDs form submission
		 */
		handleWPIndexByIds: function(e) {
			e.preventDefault();
			const self = this;
			const $form = $(e.currentTarget);
			const $button = $form.find('.wpforo-ai-wp-index-ids');
			const postIds = $form.find('#wp-post-ids').val().trim();

			if (!postIds) {
				alert('Please enter at least one post ID.');
				return;
			}

			const data = {
				action: 'wpforo_ai_wp_index_custom',
				security: wpforoAIAdmin.adminNonce,
				post_ids: postIds
			};

			$button.prop('disabled', true).text('Indexing...');

			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: data,
				success: function(response) {
					if (response.success) {
						$form.find('#wp-post-ids').val(''); // Clear the field
						// Start auto page refresh - each refresh triggers inline cron nudge
						self.startWPIndexingAutoRefresh();
					} else {
						alert('Error: ' + (response.data?.message || 'Unknown error'));
						$button.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Index by IDs');
					}
				},
				error: function() {
					alert('Error starting indexing. Please try again.');
					$button.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Index by IDs');
				}
			});
		},

		/**
		 * Handle Clear WordPress index button
		 */
		handleWPClearIndex: function(e) {
			e.preventDefault();
			const self = this;
			const $button = $(e.currentTarget);
			const confirmMessage = $button.data('confirm');

			if (!confirm(confirmMessage)) {
				return;
			}

			$button.prop('disabled', true).text('Clearing...');

			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_wp_delete_content',
					security: wpforoAIAdmin.adminNonce,
					delete_all: 'true'
				},
				success: function(response) {
					if (response.success) {
						// Stop any auto-refresh cycle
						self.stopWPIndexingAutoRefresh();
						// Reload page to show updated stats
						window.location.hash = 'wp-indexing-status-box'; window.location.reload();
					} else {
						alert('Error: ' + (response.data?.message || 'Unknown error'));
						$button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Clear WordPress Index');
					}
				},
				error: function() {
					alert('Error clearing index. Please try again.');
					$button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Clear WordPress Index');
				}
			});
		},

		/**
		 * Handle WordPress auto-indexing toggle change
		 */
		handleWPAutoIndexingToggle: function(e) {
			const $input = $(e.currentTarget);
			const isEnabled = $input.is(':checked') ? 1 : 0;
			const optionName = $input.data('option-name') || 'ai_wp_auto_indexing_enabled';
			const $toggle = $input.closest('.wpforo-ai-auto-index-toggle');

			// Disable the toggle during AJAX request
			$input.prop('disabled', true);
			$toggle.css('opacity', '0.6');

			// Save via AJAX
			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_save_wp_indexing_option',
					nonce: wpforoAIAdmin.nonce,
					option_name: optionName,
					enabled: isEnabled
				},
				success: function(response) {
					$input.prop('disabled', false);
					$toggle.css('opacity', '1');
					if (!response.success) {
						// Revert the change on failure
						$input.prop('checked', !isEnabled);
						alert('Error: ' + (response.data?.message || 'Failed to save setting'));
					}
				},
				error: function() {
					$input.prop('disabled', false);
					$toggle.css('opacity', '1');
					$input.prop('checked', !isEnabled);
					alert('Error saving setting. Please try again.');
				}
			});
		},

		/**
		 * Handle WordPress image indexing toggle change
		 */
		handleWPImageIndexingToggle: function(e) {
			const $input = $(e.currentTarget);
			const isEnabled = $input.is(':checked') ? 1 : 0;
			const optionName = $input.data('option-name') || 'ai_wp_image_indexing_enabled';
			const $toggle = $input.closest('.wpforo-ai-auto-index-toggle');

			// Show confirmation when enabling (due to credit impact)
			if (isEnabled) {
				const confirmed = confirm(
					'Enable Image Indexing for WordPress Content?\n\n' +
					'When enabled, posts with images will consume +1 additional credit during indexing.\n\n' +
					'• Maximum 10 images per post are processed\n' +
					'• Images are converted to text descriptions for search\n' +
					'• Small images (< 50x50px) like smileys are skipped\n\n' +
					'Continue?'
				);
				if (!confirmed) {
					$input.prop('checked', false);
					return;
				}
			}

			// Disable the toggle during AJAX request
			$input.prop('disabled', true);
			$toggle.css('opacity', '0.6');

			// Save via AJAX
			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_save_wp_indexing_option',
					nonce: wpforoAIAdmin.nonce,
					option_name: optionName,
					enabled: isEnabled
				},
				success: function(response) {
					$input.prop('disabled', false);
					$toggle.css('opacity', '1');
					if (!response.success) {
						// Revert the change on failure
						$input.prop('checked', !isEnabled);
						alert('Error: ' + (response.data?.message || 'Failed to save setting'));
					}
				},
				error: function() {
					$input.prop('disabled', false);
					$toggle.css('opacity', '1');
					$input.prop('checked', !isEnabled);
					alert('Error saving setting. Please try again.');
				}
			});
		},

		/**
		 * Handle WordPress-specific cleanup session
		 */
		handleCleanupSession: function(e) {
			e.preventDefault();
			const self = this;
			const $button = $(e.currentTarget);
			const confirmMsg = $button.data('confirm') || 'Reset stuck WordPress indexing session?';

			if (!window.confirm(confirmMsg)) {
				return;
			}

			const originalHtml = $button.html();
			$button.prop('disabled', true).html('<span class="dashicons dashicons-update wpforo-spin"></span> Cleaning up...');

			// Clear browser-side state
			try {
				localStorage.removeItem('wpforo_wp_indexing_auto_refresh');
			} catch (err) { /* localStorage may be blocked */ }
			this.stopWPIndexingAutoRefresh();

			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_cleanup_indexing_session',
					scope: 'wp',
					_wpnonce: wpforoAIAdmin.nonce
				},
				success: function(response) {
					$button.prop('disabled', false).html(originalHtml);
					if (response && response.success) {
						// Reload to refresh all server-rendered counts
						window.location.hash = 'wp-indexing-status-box'; window.location.reload();
					} else {
						const msg = (response && response.data && response.data.message) || 'Cleanup failed.';
						window.alert(msg);
					}
				},
				error: function(xhr, status, error) {
					$button.prop('disabled', false).html(originalHtml);
					console.error('Cleanup indexing session failed:', error);
					window.alert('Cleanup failed. Check the browser console for details.');
				}
			});
		}
	};

	// Initialize when DOM ready
	$(document).ready(function() {
		WpForoWPIndexing.init();
	});

	// Expose globally for debugging
	window.WpForoWPIndexing = WpForoWPIndexing;

})(jQuery);
