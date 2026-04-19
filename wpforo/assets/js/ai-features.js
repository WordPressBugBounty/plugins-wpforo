/* global wpforo, $wpf, wpforo_phrase, wpforo_load_hide */
/**
 * wpForo AI Features JavaScript
 *
 * AI-powered search and helper functionality
 * Requires: frontend.js to be loaded first for $wpf, wpforo_phrase, wpforo_load_hide
 */

$wpf(document).ready(function ($) {
	var wpforo_wrap = $('#wpforo-wrap');

	// =========================================================================
	// AI HELPER TOGGLE & TABS
	// =========================================================================

	// AI Helper Toggle
	wpforo_wrap.on('click', '.wpf-ai-helper-toggle', function () {
		var helper = $(this).closest('.wpforo-ai-helper');
		helper.toggleClass('wpf-ai-open');
		$('.wpf-ai-helper-content', helper).slideToggle(350);
	});

	// AI Helper Close Button
	wpforo_wrap.on('click', '.wpf-ai-tab-close', function () {
		var helper = $(this).closest('.wpforo-ai-helper');
		helper.removeClass('wpf-ai-open');
		$('.wpf-ai-helper-content', helper).slideUp(350);
	});

	// AI Helper Tab Switching
	wpforo_wrap.on('click', '.wpf-ai-tab', function () {
		var tab = $(this);
		var tabId = tab.data('tab');
		var helper = tab.closest('.wpf-ai-helper-inner');
		// Switch active tab
		$('.wpf-ai-tab', helper).removeClass('wpf-ai-tab-active');
		tab.addClass('wpf-ai-tab-active');
		// Switch active content
		$('.wpf-ai-tab-content', helper).removeClass('wpf-ai-tab-content-active');
		$('.wpf-ai-tab-content[data-tab-content="' + tabId + '"]', helper).addClass('wpf-ai-tab-content-active');
	});

	// AI Search Mode Toggle (switch between AI Search and Classic Search forms)
	wpforo_wrap.on('click', '.wpf-ai-search-mode', function () {
		var mode = $(this);
		var modeId = mode.data('mode');
		var tabContent = mode.closest('.wpf-ai-tab-content');
		// Switch active mode
		$('.wpf-ai-search-mode', tabContent).removeClass('wpf-ai-search-mode-active');
		mode.addClass('wpf-ai-search-mode-active');
		// Switch active form
		$('.wpf-ai-search-form', tabContent).removeClass('wpf-ai-search-form-active');
		$('.wpf-ai-search-form[data-search-form="' + modeId + '"]', tabContent).addClass('wpf-ai-search-form-active');
	});

	// =========================================================================
	// AI PREFERENCES
	// =========================================================================

	// Save AI Preferences
	wpforo_wrap.on('submit', '.wpf-ai-preferences-form', function (e) {
		e.preventDefault();
        wpforo_load_hide();

		var form = $(this);
		var saveBtn = form.find('.wpf-ai-pref-save');
		var statusEl = form.find('.wpf-ai-pref-status');

		// Get form values
		var language = form.find('#wpf-ai-pref-language').val();
		var maxResults = parseInt(form.find('#wpf-ai-pref-max-results').val(), 10);

		// Client-side validation (max 10 results)
		if (maxResults < 1) maxResults = 1;
		if (maxResults > 10) maxResults = 10;
		form.find('#wpf-ai-pref-max-results').val(maxResults);

		// Disable button during save
		saveBtn.prop('disabled', true).text(wpforo_phrase('Saving...'));
		statusEl.removeClass('success error').text('');

		// AJAX save
		$.ajax({
			url: wpforo.ajax_url,
			type: 'POST',
			data: {
				action: 'wpforo_save_ai_preferences',
				nonce: form.find('#wpf_ai_pref_nonce').val(),
				language: language,
				max_results: maxResults
			},
			success: function (response) {
				saveBtn.prop('disabled', false).text(wpforo_phrase('Save Preferences'));
				if (response.success) {
					statusEl.addClass('success').text(wpforo_phrase('Preferences saved!'));
					// Update the search preferences for current session
					if (typeof aiSearchLimit !== 'undefined') {
						aiSearchLimit = maxResults;
					}
					if (typeof aiSearchLanguage !== 'undefined') {
						aiSearchLanguage = language;
					}
				} else {
					statusEl.addClass('error').text(response.data || wpforo_phrase('Error saving preferences'));
				}
				// Clear status after 3 seconds
				setTimeout(function () {
					statusEl.removeClass('success error').text('');
				}, 3000);
			},
			error: function (xhr) {
				saveBtn.prop('disabled', false).text(wpforo_phrase('Save Preferences'));
				// Try to get error message from response
				var errorMsg = wpforo_phrase('Network error. Please try again.');
				try {
					var response = JSON.parse(xhr.responseText);
					if (response.data && response.data.message) {
						errorMsg = response.data.message;
					}
				} catch (e) {
					// Keep default error message
				}
				statusEl.addClass('error').text(errorMsg);
			}
		});
	});

	// =========================================================================
	// AI SEMANTIC SEARCH
	// =========================================================================

	var aiSearchOffset = 0;
	var aiSearchQuery = '';
	var aiSearchLimit = 5;
	var aiSearchLanguage = 'en_US';

	// Load preferences from data attribute (set by PHP with settings hierarchy)
	var aiHelper = $('.wpforo-ai-helper');
	if (aiHelper.length && aiHelper.data('ai-preferences')) {
		var prefs = aiHelper.data('ai-preferences');
		if (prefs.max_results) {
			aiSearchLimit = parseInt(prefs.max_results, 10) || 5;
		}
		if (prefs.language) {
			aiSearchLanguage = prefs.language;
		}
	}

	// AI Loading animation messages
	var aiLoadingMessages = [
		'Searching forum content...',
		'Analyzing with AI...',
		'Finding relevant discussions...',
		'Processing semantic matches...',
		'Generating AI summary...',
		'Ranking results by relevance...',
		'Almost ready...'
	];
	var aiLoadingInterval = null;

	function wpforoShowAiLoading(container) {
		var loadingHtml = '<div class="wpf-ai-loading">' +
			'<div class="wpf-ai-loading-animation">' +
			'<div class="wpf-ai-loading-stars">' +
			'<svg class="wpf-ai-star wpf-ai-star-1" viewBox="0 0 24 24"><path d="M12 0L14.59 8.41L23 11L14.59 13.59L12 22L9.41 13.59L1 11L9.41 8.41L12 0Z"/></svg>' +
			'<svg class="wpf-ai-star wpf-ai-star-2" viewBox="0 0 24 24"><path d="M12 0L14.59 8.41L23 11L14.59 13.59L12 22L9.41 13.59L1 11L9.41 8.41L12 0Z"/></svg>' +
			'<svg class="wpf-ai-star wpf-ai-star-3" viewBox="0 0 24 24"><path d="M12 0L14.59 8.41L23 11L14.59 13.59L12 22L9.41 13.59L1 11L9.41 8.41L12 0Z"/></svg>' +
			'</div>' +
			'</div>' +
			'<div class="wpf-ai-loading-text">' + wpforo_phrase(aiLoadingMessages[0]) + '</div>' +
			'</div>';
		container.html(loadingHtml);
		container.closest('.wpf-ai-results').show();

		// Rotate through loading messages
		var msgIndex = 0;
		aiLoadingInterval = setInterval(function() {
			msgIndex = (msgIndex + 1) % aiLoadingMessages.length;
			container.find('.wpf-ai-loading-text').text(wpforo_phrase(aiLoadingMessages[msgIndex]));
		}, 3000);
	}

	function wpforoHideAiLoading() {
		if (aiLoadingInterval) {
			clearInterval(aiLoadingInterval);
			aiLoadingInterval = null;
		}
	}

	// AI Search Form Submit Handler
	wpforo_wrap.on('submit', '.wpf-ai-form', function (e) {
		e.preventDefault();
        wpforo_load_hide();
		var form = $(this);
		var input = form.find('.wpf-ai-input');
		var query = input.val().trim();
		var submitBtn = form.find('.wpf-ai-submit');
		var resultsWrap = form.closest('.wpf-ai-search-form').find('.wpf-ai-results');
		var resultsList = resultsWrap.find('.wpf-ai-results-list');
		var moreBtn = resultsWrap.find('.wpf-ai-results-more');

		if (!query) return;

		// Reset for new search
		aiSearchOffset = 0;
		aiSearchQuery = query;

		// Show loading state on button
		submitBtn.find('i').removeClass('fa-search').addClass('fa-spinner fa-spin');
		submitBtn.prop('disabled', true);

		// Show AI loading animation in results area
		wpforoShowAiLoading(resultsList);
		moreBtn.hide();

		// Perform search
		wpforoAiSearch(query, aiSearchLimit, aiSearchOffset, function (response) {
			// Stop loading animation
			wpforoHideAiLoading();
			// Restore button
			submitBtn.find('i').removeClass('fa-spinner fa-spin').addClass('fa-search');
			submitBtn.prop('disabled', false);

			if (response.success && response.data.results.length > 0) {
				// Render AI enhancement (Summary + Recommendations) first, then results
				// Pass results to enhancement renderer for resolving [[#N]] link markers
				var enhancementHtml = wpforoRenderAiEnhancement(response.data.ai_enhancement, response.data.results);
				var resultsHtml = wpforoRenderAiResults(response.data.results);
				resultsList.html(enhancementHtml + resultsHtml);
				resultsWrap.show();

				// Hide recommendations and results until summary typewriter completes
				var recsSection = resultsList.find('.wpf-ai-recommendations-section');
				var resultsSection = resultsList.find('.wpf-ai-result-wrapper');
				recsSection.hide();
				resultsSection.hide();

				// Trigger typewriter effect for AI summary
				// When complete, reveal the other sections with animation
				var typewriterEl = resultsList.find('.wpf-ai-typewriter')[0];
				if (typewriterEl) {
					var content = typewriterEl.getAttribute('data-typewriter-content');
					if (content) {
						wpforoTypewriterEffect(typewriterEl, content, 10, function() {
							// Summary complete - now show recommendations and results
							recsSection.slideDown(300);
							setTimeout(function() {
								resultsSection.slideDown(400);
							}, 150);
						});
					} else {
						// No summary content - show sections immediately
						recsSection.show();
						resultsSection.show();
					}
				} else {
					// No typewriter element - show sections immediately
					recsSection.show();
					resultsSection.show();
				}

				// Show/hide more button
				if (response.data.has_more) {
					moreBtn.show();
				} else {
					moreBtn.hide();
				}

				aiSearchOffset = aiSearchLimit;
			} else if (response.success && response.data.no_indexed_content) {
				resultsList.html('<div class="wpf-ai-no-results"><i class="fas fa-info-circle"></i><p>' + response.data.message + '</p></div>');
				resultsWrap.show();
				moreBtn.hide();
			} else if (response.success && response.data.results.length === 0) {
				resultsList.html('<div class="wpf-ai-no-results"><i class="fas fa-search"></i><p>' + wpforo_phrase('No results found') + '</p></div>');
				resultsWrap.show();
				moreBtn.hide();
			} else {
				resultsList.html('<div class="wpf-ai-error"><i class="fas fa-exclamation-circle"></i><p>' + (response.data ? response.data.message : wpforo_phrase('Search failed')) + '</p></div>');
				resultsWrap.show();
				moreBtn.hide();
			}
		});
	});

	// More Results button
	wpforo_wrap.on('click', '.wpf-ai-more-btn', function () {
		var btn = $(this);
		var resultsWrap = btn.closest('.wpf-ai-results');
		var resultsList = resultsWrap.find('.wpf-ai-results-list');

		// Show loading
		btn.prop('disabled', true).text(wpforo_phrase('Loading...'));

		wpforoAiSearch(aiSearchQuery, aiSearchLimit, aiSearchOffset, function (response) {
			btn.prop('disabled', false).text(wpforo_phrase('More Results'));

			if (response.success && response.data.results.length > 0) {
				resultsList.append(wpforoRenderAiResults(response.data.results));
				aiSearchOffset += aiSearchLimit;

				if (!response.data.has_more) {
					btn.parent().hide();
				}
			}
		});
	});

	// AI Search AJAX function
	function wpforoAiSearch(query, limit, offset, callback) {
		$.ajax({
			url: wpforo.ajax_url,
			type: 'POST',
			data: {
				action: 'wpforo_ai_public_search',
				query: query,
				limit: limit,
				offset: offset,
				language: aiSearchLanguage,
				_wpnonce: wpforo.nonces.wpforo_ai_public_search
			},
			success: callback,
			error: function (xhr) {
				// Try to get error message from response (handles 429 rate limit errors)
				var errorMsg = wpforo_phrase('Request failed');
				try {
					var response = JSON.parse(xhr.responseText);
					if (response.data && response.data.message) {
						errorMsg = response.data.message;
					}
				} catch (e) {
					// Keep default error message
				}
				callback({ success: false, data: { message: errorMsg } });
			}
		});
	}

	// =========================================================================
	// TYPEWRITER EFFECT
	// =========================================================================

	// Typewriter effect for AI summary text
	// onComplete callback fires when typing is finished
	function wpforoTypewriterEffect(element, html, speed, onComplete) {
		if (!element || !html) {
			if (onComplete) onComplete();
			return;
		}
		speed = speed || 15; // milliseconds per character

		// Parse HTML to extract text nodes and tags
		var tempDiv = document.createElement('div');
		tempDiv.innerHTML = html;

		element.innerHTML = '';
		element.style.visibility = 'visible';

		// Recursive function to type through DOM nodes
		function typeNode(node, callback) {
			if (node.nodeType === Node.TEXT_NODE) {
				// Text node - type character by character
				var text = node.textContent;
				var textNode = document.createTextNode('');
				element.appendChild(textNode);
				var charIndex = 0;

				function typeChar() {
					if (charIndex < text.length) {
						textNode.textContent += text[charIndex];
						charIndex++;
						setTimeout(typeChar, speed);
					} else {
						callback();
					}
				}
				typeChar();
			} else if (node.nodeType === Node.ELEMENT_NODE) {
				// Element node - clone and append, then process children
				var clone = node.cloneNode(false);
				element.appendChild(clone);

				var children = Array.from(node.childNodes);
				var childIndex = 0;

				function processNextChild() {
					if (childIndex < children.length) {
						// Temporarily change element to append to clone
						var originalElement = element;
						element = clone;
						typeNode(children[childIndex], function() {
							element = originalElement;
							childIndex++;
							processNextChild();
						});
					} else {
						callback();
					}
				}
				processNextChild();
			} else {
				callback();
			}
		}

		// Process all top-level nodes
		var topNodes = Array.from(tempDiv.childNodes);
		var nodeIndex = 0;

		function processNextTopNode() {
			if (nodeIndex < topNodes.length) {
				typeNode(topNodes[nodeIndex], function() {
					nodeIndex++;
					processNextTopNode();
				});
			} else {
				// All nodes processed - call onComplete callback
				if (onComplete) onComplete();
			}
		}
		processNextTopNode();
	}

	// =========================================================================
	// RENDER FUNCTIONS
	// =========================================================================

	// Render AI Enhancement sections (Summary and Recommendations)
	// All HTML is pre-rendered by PHP - JavaScript only inserts it
	function wpforoRenderAiEnhancement(enhancement, results) {
		if (!enhancement) return '';

		var html = '';

		// AI Search Summary Section
		// PHP already converts [[#N]] and [[#N:Title]] to HTML links
		if (enhancement.summary || enhancement.quick_answer) {
			html += '<div class="wpf-ai-summary-section notranslate" translate="no">';
			html += '<div class="wpf-ai-section-header"><i class="fas fa-brain"></i> ' + wpforo_phrase('AI Search Summary') + '</div>';
			if (enhancement.quick_answer) {
				// Output directly - PHP has already processed link markers
				html += '<div class="wpf-ai-quick-answer notranslate" translate="no">' + enhancement.quick_answer + '</div>';
			}
			if (enhancement.summary) {
				// Store summary in data attribute for typewriter effect
				var encodedSummary = enhancement.summary.replace(/"/g, '&quot;');
				html += '<div class="wpf-ai-summary-text wpf-ai-typewriter notranslate" translate="no" data-typewriter-content="' + encodedSummary + '" style="visibility:hidden;min-height:50px;"></div>';
			}
			html += '</div>';
		}

		// AI Recommendations Section - use pre-rendered HTML from PHP
		if (enhancement.recommendations_html) {
			html += enhancement.recommendations_html;
		}

		return html;
	}

	// Render AI search results HTML
	function wpforoRenderAiResults(results) {
		var html = '';
		// Add "AI Search Results" header before real results
        html += '<div class="wpf-ai-result-wrapper">';
		html += '<div class="wpf-ai-section-header"><i class="fas fa-search"></i> ' + wpforo_phrase('AI Search Results') + '</div>';
		for (var i = 0; i < results.length; i++) {
			var r = results[i];
			html += '<div class="wpf-ai-result-card">';
			var postIdBadge = r.post_id ? ' <span class="wpf-ai-result-postid">[ <i class="fa-regular fa-message"></i> ' + r.post_id + ' ]</span>' : '';
			html += '<div class="wpf-ai-result-title"><a href="' + r.url + '" target="_blank" rel="noopener">' + wpforoEscapeHtml(r.title) + postIdBadge + '</a></div>';
			html += '<div class="wpf-ai-result-meta">';
			if (r.content_source === 'wordpress') {
				var typeLabel = r.post_type_label || 'Post';
				html += '<span class="wpf-ai-result-post-type"><i class="fas fa-file-alt"></i> ' + wpforoEscapeHtml(typeLabel) + '</span>';
			} else if (r.forum_title) {
				html += '<span class="wpf-ai-result-forum"><i class="fas fa-folder-open"></i> ' + wpforoEscapeHtml(r.forum_title) + '</span>';
			}
			html += '<span class="wpf-ai-result-author"><i class="fas fa-user"></i> ' + wpforoEscapeHtml(r.author_name) + '</span>';
			html += '<span class="wpf-ai-result-date"><i class="far fa-clock"></i> ' + r.created_ago + '</span>';
			html += '<span class="wpf-ai-result-score"><i class="fas fa-bullseye"></i> ' + r.score + '%</span>';
			html += '</div>';
			if (r.content) {
				var formattedContent = wpforoFormatAiContent(r.content);
				var lineCount = (r.content.match(/[\r\n]+/g) || []).length + 1;
				var isLong = r.content.length > 500 || lineCount > 5;

				html += '<div class="wpf-ai-result-content-wrap' + (isLong ? ' wpf-ai-collapsed' : '') + '">';
				html += '<div class="wpf-ai-result-content">' + formattedContent + '</div>';
				if (isLong) {
					html += '<div class="wpf-ai-content-toggle"><span class="wpf-ai-toggle-btn" data-expanded="false"><i class="fas fa-chevron-down"></i> <span class="wpf-ai-toggle-text">' + wpforo_phrase('Show more') + '</span></span></div>';
				}
				html += '</div>';
			}
			html += '</div>';
		}
        html += '</div>';
		return html;
	}

	// Format AI content: escape HTML and convert line breaks to <br>
	function wpforoFormatAiContent(text) {
		if (!text) return '';
		// First escape HTML
		var escaped = wpforoEscapeHtml(text);
		// Convert \r\n, \r, \n to <br> for proper line breaks
		escaped = escaped.replace(/\r\n/g, '<br>');
		escaped = escaped.replace(/\r/g, '<br>');
		escaped = escaped.replace(/\n/g, '<br>');
		// Convert multiple <br> to paragraph breaks
		escaped = escaped.replace(/(<br>){3,}/g, '<br><br>');
		return escaped;
	}

	// Toggle AI search result content expand/collapse
	wpforo_wrap.on('click', '.wpf-ai-toggle-btn', function() {
		var $btn = $(this);
		var $wrap = $btn.closest('.wpf-ai-result-content-wrap');
		var isExpanded = $btn.data('expanded');

		if (isExpanded) {
			$wrap.addClass('wpf-ai-collapsed');
			$btn.data('expanded', false);
			$btn.find('.wpf-ai-toggle-text').text(wpforo_phrase('Show more'));
			$btn.find('i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
		} else {
			$wrap.removeClass('wpf-ai-collapsed');
			$btn.data('expanded', true);
			$btn.find('.wpf-ai-toggle-text').text(wpforo_phrase('Show less'));
			$btn.find('i').removeClass('fa-chevron-down').addClass('fa-chevron-up');
		}
	});

	// =========================================================================
	// HELPER FUNCTIONS
	// =========================================================================

	// Escape HTML helper
	function wpforoEscapeHtml(text) {
		if (!text) return '';
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	// =========================================================================
	// AI TRANSLATION
	// =========================================================================

	// Toggle translation dropdown
	wpforo_wrap.on('click', '.wpf-ai-translate-btn', function (e) {
		e.stopPropagation();
		var wrapper = $(this).closest('.wpf-ai-translate-wrapper');
		var dropdown = wrapper.find('.wpf-ai-translate-dropdown');

		// Close other dropdowns
		$('.wpf-ai-translate-dropdown').not(dropdown).removeClass('wpf-ai-translate-dropdown-open');

		// Toggle this dropdown
		dropdown.toggleClass('wpf-ai-translate-dropdown-open');
	});

	// Close dropdown when clicking outside
	$(document).on('click', function () {
		$('.wpf-ai-translate-dropdown').removeClass('wpf-ai-translate-dropdown-open');
	});

	// Prevent dropdown from closing when clicking inside it
	wpforo_wrap.on('click', '.wpf-ai-translate-dropdown', function (e) {
		e.stopPropagation();
	});

	// Handle language selection for translation
	wpforo_wrap.on('click', '.wpf-ai-translate-option', function () {
		var option = $(this);
		var wrapper = option.closest('.wpf-ai-translate-wrapper');
		var postId = wrapper.data('postid');
		var language = option.data('lang');
		var dropdown = wrapper.find('.wpf-ai-translate-dropdown');
		var translateBtn = wrapper.find('.wpf-ai-translate-btn');
		var originalBtn = wrapper.find('.wpf-ai-translate-original');
		var loadingEl = wrapper.find('.wpf-ai-translate-loading');

		// Close dropdown
		dropdown.removeClass('wpf-ai-translate-dropdown-open');

		// Find the post/comment content element
		// Structure for Post: .post-wrap > .wpforo-post > .wpforo-post-content
		// Structure for Q&A Layout Comment: .comment-wrap > .wpforo-comment-content > .wpforo-comment-text
		var postElement = wrapper.closest('.wpforo-post').find('.wpforo-post-content');
		if (!postElement.length) {
			postElement = wrapper.closest('.post-wrap').find('.wpforo-post-content');
		}
		if (!postElement.length) {
			postElement = wrapper.closest('.wpforo-comment').find('.wpforo-comment-text');
		}
		if (!postElement.length) {
			postElement = wrapper.closest('.comment-wrap').find('.wpforo-comment-text');
		}
		if (!postElement.length) {
			console.error('wpForo AI: Could not find post content element');
			return;
		}

		// Store original content if not already stored
		if (!postElement.data('original-content')) {
			postElement.data('original-content', postElement.html());
		}

		// Show loading state
		translateBtn.hide();
		loadingEl.show();

		// Make AJAX request
		$.ajax({
			url: wpforo.ajax_url,
			type: 'POST',
			data: {
				action: 'wpforo_ai_translate',
				post_id: postId,
				language: language,
				nonce: wpforo.nonces.wpforo_ai_translate
			},
			success: function (response) {
				loadingEl.hide();

				if (response.success && response.data.translated_content) {
					// Replace content with translated version
					postElement.html(response.data.translated_content);
					postElement.addClass('wpf-ai-translated');

					// Add RTL class for right-to-left languages (Arabic, Hebrew)
					var rtlLanguages = ['Arabic', 'Hebrew', 'ar', 'he'];
					if (rtlLanguages.indexOf(language) !== -1) {
						postElement.addClass('wpf-ai-translated-rtl');
					}

					// Show "Show Original" button
					originalBtn.show();
				} else {
					// Show error
					translateBtn.show();
					var errorMsg = response.data && response.data.message ? response.data.message : wpforo_phrase('Translation failed');
					alert(errorMsg);
				}
			},
			error: function (xhr) {
				loadingEl.hide();
				translateBtn.show();
				// Try to get error message from response (handles 429 rate limit errors)
				var errorMsg = wpforo_phrase('Network error. Please try again.');
				try {
					var response = JSON.parse(xhr.responseText);
					if (response.data && response.data.message) {
						errorMsg = response.data.message;
					}
				} catch (e) {
					// Keep default error message
				}
				alert(errorMsg);
			}
		});
	});

	// Handle "Show Original" button click
	wpforo_wrap.on('click', '.wpf-ai-translate-original', function () {
		var wrapper = $(this).closest('.wpf-ai-translate-wrapper');
		var originalBtn = wrapper.find('.wpf-ai-translate-original');
		var translateBtn = wrapper.find('.wpf-ai-translate-btn');

		// Find the post/comment content element
		var postElement = wrapper.closest('.wpforo-post').find('.wpforo-post-content');
		if (!postElement.length) {
			postElement = wrapper.closest('.post-wrap').find('.wpforo-post-content');
		}
		if (!postElement.length) {
			postElement = wrapper.closest('.wpforo-comment').find('.wpforo-comment-text');
		}
		if (!postElement.length) {
			postElement = wrapper.closest('.comment-wrap').find('.wpforo-comment-text');
		}

		// Restore original content
		var originalContent = postElement.data('original-content');
		if (originalContent) {
			postElement.html(originalContent);
			postElement.removeClass('wpf-ai-translated wpf-ai-translated-rtl');
		}

		// Show translate button, hide original button
		originalBtn.hide();
		translateBtn.show();
	});

	// =========================================================================
	// AI TOPIC SUMMARIZATION
	// =========================================================================

	var summaryLoadingInterval = null;
	var summaryLoadingMessages = [
		'Reading topic posts...',
		'Analyzing discussion...',
		'Generating summary...',
		'Almost ready...'
	];

	// Show loading animation for topic summary
	function wpforoShowSummaryLoading(container) {
		var loadingHtml = '<div class="wpf-ai-loading">' +
			'<div class="wpf-ai-loading-animation">' +
			'<div class="wpf-ai-loading-stars">' +
			'<svg class="wpf-ai-star wpf-ai-star-1" viewBox="0 0 24 24"><path d="M12 0L14.59 8.41L23 11L14.59 13.59L12 22L9.41 13.59L1 11L9.41 8.41L12 0Z"/></svg>' +
			'<svg class="wpf-ai-star wpf-ai-star-2" viewBox="0 0 24 24"><path d="M12 0L14.59 8.41L23 11L14.59 13.59L12 22L9.41 13.59L1 11L9.41 8.41L12 0Z"/></svg>' +
			'<svg class="wpf-ai-star wpf-ai-star-3" viewBox="0 0 24 24"><path d="M12 0L14.59 8.41L23 11L14.59 13.59L12 22L9.41 13.59L1 11L9.41 8.41L12 0Z"/></svg>' +
			'</div>' +
			'</div>' +
			'<div class="wpf-ai-loading-text">' + wpforo_phrase(summaryLoadingMessages[0]) + '</div>' +
			'</div>';
		container.html(loadingHtml);

		// Rotate through loading messages
		var msgIndex = 0;
		summaryLoadingInterval = setInterval(function() {
			msgIndex = (msgIndex + 1) % summaryLoadingMessages.length;
			container.find('.wpf-ai-loading-text').text(wpforo_phrase(summaryLoadingMessages[msgIndex]));
		}, 2500);
	}

	function wpforoHideSummaryLoading() {
		if (summaryLoadingInterval) {
			clearInterval(summaryLoadingInterval);
			summaryLoadingInterval = null;
		}
	}

	// Topic Summary Button Click Handler
	wpforo_wrap.on('click', '.wpf-ai-summarize-btn', function (e) {
		e.preventDefault();
		wpforo_load_hide();

		var btn = $(this);
		var topicId = btn.data('topicid');
		var nonce = btn.data('nonce');
		var container = $('#wpf-ai-summary-' + topicId);
		var contentArea = container.find('.wpf-ai-summary-content');

		// If container is already visible and has content, just toggle it
		if (container.is(':visible') && contentArea.find('.wpf-ai-summary-result').length > 0) {
			container.slideUp(350);
			return;
		}

		// Show container with loading
		container.slideDown(350);
		wpforoShowSummaryLoading(contentArea);

		// Disable button during request
		btn.addClass('wpf-ai-loading-btn');

		// Make AJAX request
		$.ajax({
			url: wpforo.ajax_url,
			type: 'POST',
			data: {
				action: 'wpforo_ai_summarize_topic',
				topicid: topicId,
				nonce: nonce
			},
			success: function (response) {
				wpforoHideSummaryLoading();
				btn.removeClass('wpf-ai-loading-btn');

				if (response.success && response.data.summary) {
					// Build posts info (show notice when posts were limited)
					var postsInfo = '';
					if (response.data.posts_limited && response.data.total_posts_count) {
						postsInfo = '<span class="wpf-ai-summary-meta wpf-ai-posts-limited">' +
							response.data.reply_count + ' ' + wpforo_phrase('of') + ' ' + response.data.total_posts_count + ' ' + wpforo_phrase('posts') +
							'</span>';
					}

					// Render summary with close button in header (like AI Assistant)
					var summaryHtml = '<div class="wpf-ai-summary-result">' +
						'<div class="wpf-ai-summary-header">' +
						'<span class="wpf-ai-summary-title"><i class="fa-solid fa-wand-magic-sparkles"></i> ' + wpforo_phrase('AI Summary') + '</span>' +
						'<span class="wpf-ai-summary-meta">' + wpforo_phrase('Style') + ': ' + wpforoEscapeHtml(response.data.style || 'detailed') + '</span>' +
						postsInfo +
						'<div class="wpf-ai-summary-close-btn">' +
						'<i class="fas fa-times"></i>' +
						'<span>' + wpforo_phrase('Close') + '</span>' +
						'</div>' +
						'</div>' +
						'<div class="wpf-ai-summary-body">' + response.data.summary + '</div>' +
						'</div>';
					contentArea.html(summaryHtml);
				} else {
					// Show error with close button
					var errorMsg = response.data && response.data.message ? response.data.message : wpforo_phrase('Failed to generate summary');
					contentArea.html('<div class="wpf-ai-summary-error">' +
						'<i class="fas fa-exclamation-circle"></i> ' + wpforoEscapeHtml(errorMsg) +
						'<div class="wpf-ai-summary-close-btn" style="margin-left: 10px;">' +
						'<i class="fas fa-times"></i>' +
						'<span>' + wpforo_phrase('Close') + '</span>' +
						'</div>' +
						'</div>');
				}
			},
			error: function (xhr) {
				wpforoHideSummaryLoading();
				btn.removeClass('wpf-ai-loading-btn');
				// Try to get error message from response (handles 429 rate limit errors)
				var errorMsg = wpforo_phrase('Network error. Please try again.');
				try {
					var response = JSON.parse(xhr.responseText);
					if (response.data && response.data.message) {
						errorMsg = response.data.message;
					}
				} catch (e) {
					// Keep default error message
				}
				contentArea.html('<div class="wpf-ai-summary-error">' +
					'<i class="fas fa-exclamation-circle"></i> ' + wpforoEscapeHtml(errorMsg) +
					'<div class="wpf-ai-summary-close-btn" style="margin-left: 10px;">' +
					'<i class="fas fa-times"></i>' +
					'<span>' + wpforo_phrase('Close') + '</span>' +
					'</div>' +
					'</div>');
			}
		});
	});

	// Topic Summary Close Button Handler (handles both footer close and header close button)
	wpforo_wrap.on('click', '.wpf-ai-summary-close, .wpf-ai-summary-close-btn', function () {
		var container = $(this).closest('.wpf-ai-summary-container');
		container.slideUp(350);
	});

	// =========================================================================
	// AI TOPIC SUGGESTIONS (Smart Topic Suggestions)
	// =========================================================================

	var suggestionCallCount = 0;
	var suggestionConfig = null;
	var suggestionLastQuery = '';

	// Initialize suggestion config from data attributes
	function wpforoInitSuggestionConfig() {
		var panel = $('.wpf-ai-suggestions-panel');
		if (panel.length && panel.data('suggestion-config')) {
			suggestionConfig = panel.data('suggestion-config');
		} else {
			// Default config - disabled if panel not found
			suggestionConfig = {
				enabled: false,
				min_words: 3,
				max_calls: 2,
				show_related: true,
				show_answer: true,
				quality: 'balanced'
			};
		}
	}

	// Count words in a string
	function wpforoCountWords(str) {
		if (!str) return 0;
		return str.trim().split(/\s+/).filter(function(w) { return w.length > 0; }).length;
	}

	// Topic title input handler - triggers on blur (when user leaves the title field)
	// Topic title field has name="thread[title]" and id="thread_title" in wpForo
	wpforo_wrap.on('blur', '#thread_title, input[name="thread[title]"]', function (e) {
		// Initialize config if not done
		if (!suggestionConfig) {
			wpforoInitSuggestionConfig();
		}

		// Check if suggestions are enabled
		if (!suggestionConfig || !suggestionConfig.enabled) {
			return;
		}

		var input = $(this);
		var title = input.val().trim();
		var wordCount = wpforoCountWords(title);

		// Check minimum words (from config)
		if (wordCount < suggestionConfig.min_words) {
			return;
		}

		// Skip if same query as last time
		if (title === suggestionLastQuery) {
			return;
		}

		// Check max API calls per topic creation session (from config)
		if (suggestionCallCount >= suggestionConfig.max_calls) {
			return;
		}

		// Fetch suggestions immediately on blur (no debounce needed)
		wpforoFetchSuggestions(title, input);
	});

	// Fetch suggestions from API
	function wpforoFetchSuggestions(title, inputElement) {
		if (!suggestionConfig || !suggestionConfig.enabled) return;

		suggestionLastQuery = title;
		suggestionCallCount++;

		var form = inputElement.closest('form');
		var panel = form.find('.wpf-ai-suggestions-panel');
		var contentArea = panel.find('.wpf-ai-suggestions-content');

		// Show panel with loading immediately on blur
		panel.slideDown(300);
		wpforoShowSuggestionLoading(contentArea);

		// Make AJAX request
		$.ajax({
			url: wpforo.ajax_url,
			type: 'POST',
			data: {
				action: 'wpforo_ai_get_topic_suggestions',
				title: title,
				quality: suggestionConfig.quality || 'balanced',
				include_similar: 1, // Always include similar topics - required for the feature
				include_related: suggestionConfig.show_related ? 1 : 0,
				include_answer: suggestionConfig.show_answer ? 1 : 0,
				nonce: wpforo.nonces.wpforo_ai_get_topic_suggestions
			},
			success: function (response) {
				wpforoHideSuggestionLoading();

				if (response.success && response.data.has_suggestions) {
					wpforoRenderSuggestions(contentArea, response.data);
				} else if (response.success && !response.data.has_suggestions) {
					// No similar topics found - show message briefly then hide
					contentArea.html('<div class="wpf-ai-suggestions-no-results">' +
						'<i class="fas fa-info-circle"></i> ' + wpforo_phrase('No similar topics have been found.') +
						'</div>');
					// Hide panel after 3 seconds
					setTimeout(function() {
						panel.slideUp(300);
					}, 3000);
				} else {
					// Error
					var errorMsg = response.data && response.data.message ? response.data.message : wpforo_phrase('Could not fetch suggestions');
					contentArea.html('<div class="wpf-ai-suggestions-error">' +
						'<i class="fas fa-exclamation-circle"></i> ' + wpforoEscapeHtml(errorMsg) +
						'</div>');
				}
			},
			error: function (xhr) {
				wpforoHideSuggestionLoading();
				// Try to get error message from response (handles 429 rate limit errors)
				var errorMsg = wpforo_phrase('Network error. Please try again.');
				try {
					var response = JSON.parse(xhr.responseText);
					if (response.data && response.data.message) {
						errorMsg = response.data.message;
					}
				} catch (e) {
					// Keep default error message
				}
				contentArea.html('<div class="wpf-ai-suggestions-error">' +
					'<i class="fas fa-exclamation-circle"></i> ' + wpforoEscapeHtml(errorMsg) +
					'</div>');
			}
		});
	}

	// Loading animation for suggestions - compact single line
	var suggestionLoadingInterval = null;
	var suggestionLoadingMessages = [
		'Searching similar topics...',
		'Analyzing your question...',
		'Finding relevant discussions...',
		'Almost ready...'
	];

	function wpforoShowSuggestionLoading(container) {
		var loadingHtml = '<div class="wpf-ai-suggestion-loading-inline">' +
			'<svg class="wpf-ai-star-inline" viewBox="0 0 24 24"><path d="M12 0L14.59 8.41L23 11L14.59 13.59L12 22L9.41 13.59L1 11L9.41 8.41L12 0Z"/></svg>' +
			'<span class="wpf-ai-loading-text-inline">' + wpforo_phrase(suggestionLoadingMessages[0]) + '</span>' +
			'</div>';
		container.html(loadingHtml);

		var msgIndex = 0;
		suggestionLoadingInterval = setInterval(function() {
			msgIndex = (msgIndex + 1) % suggestionLoadingMessages.length;
			container.find('.wpf-ai-loading-text-inline').text(wpforo_phrase(suggestionLoadingMessages[msgIndex]));
		}, 2000);
	}

	function wpforoHideSuggestionLoading() {
		if (suggestionLoadingInterval) {
			clearInterval(suggestionLoadingInterval);
			suggestionLoadingInterval = null;
		}
	}

	// Render suggestions UI
	function wpforoRenderSuggestions(container, data) {
		// No duplicate header - PHP already renders the main header
		var html = '';

		// Similar Topics Section
		if (data.similar_topics && data.similar_topics.length > 0) {
			html += '<div class="wpf-ai-suggestions-section wpf-ai-similar-topics">' +
				'<div class="wpf-ai-suggestions-section-header">' +
				'<i class="fas fa-copy"></i> ' + wpforo_phrase('Similar Topics Already Exist') +
				'</div>' +
				'<div class="wpf-ai-suggestions-section-content">' +
				'<ul class="wpf-ai-similar-list">';

			for (var i = 0; i < data.similar_topics.length; i++) {
				var topic = data.similar_topics[i];
				html += '<li class="wpf-ai-similar-item">' +
					'<i class="fas fa-angle-double-right wpf-ai-item-icon"></i>' +
                    '<span class="wpf-ai-similar-score">' + topic.score + '% ' + wpforo_phrase('match') + '</span>' +
					'<a href="' + topic.url + '" target="_blank" rel="noopener" class="wpf-ai-similar-link">' +
					'<span class="wpf-ai-similar-title">' + wpforoEscapeHtml(topic.title) + '</span>' +
					'</a>' +
					'</li>';
			}

			html += '</ul>' +
				'<div class="wpf-ai-similar-hint">' +
				'<i class="fas fa-info-circle"></i> ' + wpforo_phrase('Check these topics - your question might already be answered!') +
				'</div>' +
				'</div>' +
				'</div>';
		}

		// Related Topics Section (AI suggestions)
		if (data.related_topics && data.related_topics.length > 0) {
			html += '<div class="wpf-ai-suggestions-section wpf-ai-related-topics">' +
				'<div class="wpf-ai-suggestions-section-header">' +
				'<svg class="wpf-ai-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width:22px;height:22px;vertical-align:middle;margin-right:6px;fill:currentColor;margin-bottom: 3px;"><path d="M9,13 L19,13 C19.5522847,13 20,13.4477153 20,14 C20,14.5522847 19.5522847,15 19,15 L9,15 C8.44771525,15 8,14.5522847 8,14 C8,13.4477153 8.44771525,13 9,13 Z M9,17 L19,17 C19.5522847,17 20,17.4477153 20,18 C20,18.5522847 19.5522847,19 19,19 L9,19 C8.44771525,19 8,18.5522847 8,18 C8,17.4477153 8.44771525,17 9,17 Z M15,9 L19,9 C19.5522847,9 20,9.44771525 20,10 C20,10.5522847 19.5522847,11 19,11 L15,11 C14.4477153,11 14,10.5522847 14,10 C14,9.44771525 14.4477153,9 15,9 Z M7.74264069,10.9142136 L4,7.17157288 L5.41421356,5.75735931 L7.74264069,8.08578644 L12.8284271,3 L14.2426407,4.41421356 L7.74264069,10.9142136 Z"/></svg>' + wpforo_phrase('Related Topics You Might Explore') +
				'</div>' +
				'<div class="wpf-ai-suggestions-section-content">' +
				'<ul class="wpf-ai-related-list">';

			for (var j = 0; j < data.related_topics.length; j++) {
				var related = data.related_topics[j];
				// Use the URL from API if available, fallback to search
				var topicUrl = related.url || (wpforo_url + '?foro=search&wpfkeyword=' + encodeURIComponent(related.title));
				html += '<li class="wpf-ai-related-item">' +
					'<svg class="wpf-ai-icon wpf-ai-item-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width:14px;height:14px;vertical-align:middle;margin-right:5px;fill:currentColor;flex-shrink:0;"><polygon points="6 10 4 12 10 18 20 8 18 6 10 14"/></svg>' +
					'<a href="' + topicUrl + '" target="_blank" rel="noopener" class="wpf-ai-related-link">' +
					'<span class="wpf-ai-related-title">' + wpforoEscapeHtml(related.title) + '</span>' +
					'</a>' +
					(related.reason ? '<span class="wpf-ai-related-reason">' + wpforoEscapeHtml(related.reason) + '</span>' : '') +
					'</li>';
			}

			html += '</ul>' +
				'</div>' +
				'</div>';
		}

		// Quick AI Answer Section
		if (data.quick_answer && data.quick_answer.text) {
			html += '<div class="wpf-ai-suggestions-section wpf-ai-quick-answer-section">' +
				'<div class="wpf-ai-suggestions-section-header">' +
				'<svg class="wpf-ai-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:22px;height:22px;vertical-align:middle;margin-right:6px;margin-bottom: 3px;"><path fill-rule="evenodd" clip-rule="evenodd" d="M7.33569 3.38268C7.93132 1.87244 10.0687 1.87244 10.6643 3.38268L11.7363 6.10082C11.7657 6.17532 11.8247 6.23429 11.8992 6.26367L14.6173 7.33569C16.1276 7.93132 16.1276 10.0687 14.6173 10.6643L11.8992 11.7363C11.8247 11.7657 11.7657 11.8247 11.7363 11.8992L10.6643 14.6173C10.0687 16.1276 7.93132 16.1276 7.33569 14.6173L6.26367 11.8992C6.23429 11.8247 6.17532 11.7657 6.10082 11.7363L3.38268 10.6643C1.87244 10.0687 1.87244 7.93132 3.38268 7.33569L6.10082 6.26367C6.17532 6.23429 6.23429 6.17532 6.26367 6.10082L7.33569 3.38268ZM9.26891 3.93301C9.17267 3.68899 8.82733 3.689 8.73109 3.93301L7.65907 6.65115C7.47722 7.11224 7.11224 7.47722 6.65116 7.65907L3.93301 8.73109C3.68899 8.82733 3.689 9.17267 3.93301 9.26891L6.65115 10.3409C7.11224 10.5228 7.47722 10.8878 7.65907 11.3488L8.73109 14.067C8.82733 14.311 9.17267 14.311 9.26891 14.067L10.3409 11.3488C10.5228 10.8878 10.8878 10.5228 11.3488 10.3409L14.067 9.26891C14.311 9.17267 14.311 8.82733 14.067 8.73109L11.3488 7.65907C10.8878 7.47722 10.5228 7.11224 10.3409 6.65116L9.26891 3.93301ZM15.7908 13.073C16.2235 11.9757 17.7765 11.9757 18.2092 13.073L18.9779 15.0221L20.927 15.7908C22.0243 16.2235 22.0243 17.7765 20.927 18.2092L18.9779 18.9779L18.2092 20.927C17.7765 22.0243 16.2235 22.0243 15.7908 20.927L15.0221 18.9779L13.073 18.2092C11.9757 17.7765 11.9757 16.2235 13.073 15.7908L15.0221 15.0221L15.7908 13.073ZM17 14.0953L16.3856 15.6533C16.2534 15.9883 15.9883 16.2534 15.6533 16.3856L14.0953 17L15.6533 17.6144C15.9883 17.7466 16.2534 18.0117 16.3856 18.3467L17 19.9047L17.6144 18.3467C17.7466 18.0117 18.0117 17.7466 18.3467 17.6144L19.9047 17L18.3467 16.3856C18.0117 16.2534 17.7466 15.9883 17.6144 15.6533L17 14.0953Z" fill="currentColor"/></svg>' + wpforo_phrase('Quick AI Answer') +
				'</div>' +
				'<div class="wpf-ai-suggestions-section-content">' +
				'<div class="wpf-ai-quick-answer-text">' + wpforoFormatAiContent(data.quick_answer.text) + '</div>';

			html += '<div class="wpf-ai-answer-hint">' +
				wpforo_phrase('This is an AI-generated answer based on existing forum content. Post your topic for more accurate human responses.') +
				'</div>' +
				'</div>' +
				'</div>';
		}

		container.html(html);
	}

	// Close suggestions panel
	wpforo_wrap.on('click', '.wpf-ai-suggestions-close', function () {
		var panel = $(this).closest('.wpf-ai-suggestions-panel');
		panel.slideUp(300);
	});

	// Toggle show more/less for quick answer
	wpforo_wrap.on('click', '.wpf-ai-show-more-answer', function () {
		var btn = $(this);
		var answerText = btn.closest('.wpf-ai-suggestions-section-content').find('.wpf-ai-quick-answer-text');
		var fullAnswer = btn.data('full');
		var isExpanded = btn.data('expanded');

		if (isExpanded) {
			// Collapse - would need original stored, for now just hide
			btn.html('<i class="fas fa-chevron-down"></i> ' + wpforo_phrase('Show more'));
			btn.data('expanded', false);
		} else {
			answerText.html(wpforoFormatAiContent(fullAnswer));
			btn.html('<i class="fas fa-chevron-up"></i> ' + wpforo_phrase('Show less'));
			btn.data('expanded', true);
		}
	});

	// Initialize suggestion config on page load
	wpforoInitSuggestionConfig();

	// Re-initialize suggestion config when topic form is loaded via AJAX
	// wpForo triggers 'wpforo_topic_portable_form' event after AJAX form load
	$(document).on('wpforo_topic_portable_form', function(event, formElement) {
		// Reset suggestion state for new form
		suggestionCallCount = 0;
		suggestionLastQuery = '';
		suggestionConfig = null;

		// Re-initialize config from the new form's panel
		if (formElement && formElement.length) {
			var panel = formElement.find('.wpf-ai-suggestions-panel');
			if (panel.length && panel.data('suggestion-config')) {
				suggestionConfig = panel.data('suggestion-config');
			}
		}

		// Fallback to global search if not found in form element
		if (!suggestionConfig) {
			wpforoInitSuggestionConfig();
		}
	});

	// =========================================================================
	// AI BOT REPLY
	// =========================================================================

	/**
	 * AI Bot Reply button click handler
	 * Creates a bot-generated reply to the post
	 */
	wpforo_wrap.on('click', '.wpf-ai-bot-reply', function (e) {
		e.preventDefault();
		e.stopPropagation();

		var btn = $(this);
		var postId = btn.data('postid');
		var topicId = btn.data('topicid');

		if (!postId || !topicId) {
			console.error('AI Bot Reply: Missing post or topic ID');
			return;
		}

		// Prevent double-clicks (check for wpf-processing spinning class)
		if (btn.hasClass('wpf-processing')) {
			return;
		}

		// Check if nonce is available
		var nonce = wpforo.nonces && wpforo.nonces.wpforo_ai_bot_reply;
		if (!nonce) {
			alert(wpforo_phrase('AI Bot Reply is not properly configured. Please refresh the page and try again.'));
			return;
		}

		// Show loading state - spinning icon (like wpforo-aibot plugin)
		btn.addClass('wpf-processing');
		if (typeof wpforo_load_show === 'function') {
			wpforo_load_show();
		}

		// Make AJAX request
		$.ajax({
			url: wpforo.ajax_url,
			type: 'POST',
			data: {
				action: 'wpforo_ai_bot_reply',
				_wpnonce: nonce,
				post_id: postId,
				topic_id: topicId
			}
		}).done(function (response) {
			if (response.success) {
				// Reload page to show new reply (with anchor to new post)
				var newPostId = response.data.post_id;
				setTimeout(function() {
					if (newPostId) {
						window.location.href = window.location.pathname + window.location.search + '#post-' + newPostId;
						window.location.reload();
					} else {
						window.location.reload();
					}
				}, 500);
			} else {
				btn.removeClass('wpf-processing');
				if (typeof wpforo_load_hide === 'function') {
					wpforo_load_hide();
				}

				var errorMsg = response.data && response.data.message
					? response.data.message
					: wpforo_phrase('Failed to generate bot reply');

				// Provide helpful message for common configuration issues
				if (errorMsg.indexOf('Bot user not configured') !== -1) {
					errorMsg = wpforo_phrase('Bot user not configured') + '.\n\n' +
						wpforo_phrase('Please go to') + ' wpForo > Settings > AI Features > AI Bot Reply ' +
						wpforo_phrase('and select a WordPress user for the bot.');
				}

				alert(errorMsg);
			}
		}).fail(function (xhr, status, error) {
			btn.removeClass('wpf-processing');
			if (typeof wpforo_load_hide === 'function') {
				wpforo_load_hide();
			}

			console.error('AI Bot Reply error:', status, error, xhr.responseText);

			// Try to parse error message from response
			var errorMsg = wpforo_phrase('Network error. Please try again.');
			try {
				var jsonResponse = JSON.parse(xhr.responseText);
				if (jsonResponse.data && jsonResponse.data.message) {
					errorMsg = jsonResponse.data.message;
					// Add helpful message for bot user not configured
					if (errorMsg.indexOf('Bot user not configured') !== -1) {
						errorMsg = wpforo_phrase('Bot user not configured') + '.\n\n' +
							wpforo_phrase('Please go to') + ' wpForo > Settings > AI Features > AI Bot Reply ' +
							wpforo_phrase('and select a WordPress user for the bot.');
					}
				}
			} catch (e) {
				// Keep default error message
			}
			alert(errorMsg);
		});
	});

	/**
	 * AI Suggest Reply button click handler
	 * Generates AI reply suggestion and inserts into TinyMCE editor
	 */
	wpforo_wrap.on('click', '.wpf-ai-suggest-reply', function (e) {
		e.preventDefault();

		var btn = $(this);
		var topicId = btn.data('topicid');

		if (!topicId) {
			console.error('AI Suggest Reply: Missing topic ID');
			return;
		}

		// Prevent double-clicks
		if (btn.hasClass('wpf-ai-loading')) {
			return;
		}

		// Check if nonce is available
		var nonce = wpforo.nonces && wpforo.nonces.wpforo_ai_suggest_reply;
		if (!nonce) {
			alert(wpforo_phrase('AI Suggest Reply is not properly configured. Please refresh the page and try again.'));
			return;
		}

		// Find the form and get parent post ID if available (for threaded replies)
		var form = btn.closest('form');
		var parentId = 0;
		if (form.length) {
			var parentInput = form.find('input[name="parentid"]');
			if (parentInput.length) {
				parentId = parseInt(parentInput.val(), 10) || 0;
			}
		}

		// Show loading state (CSS handles icon visibility via .wpf-ai-suggest-loading class)
		btn.addClass('wpf-ai-suggest-loading');
		btn.find('span').text(wpforo_phrase('Processing...'));

		// Make AJAX request
		$.ajax({
			url: wpforo.ajax_url,
			type: 'POST',
			data: {
				action: 'wpforo_ai_suggest_reply',
				_wpnonce: nonce,
				topic_id: topicId,
				parent_id: parentId
			},
			success: function (response) {
				// Reset button state (CSS handles icon visibility)
				btn.removeClass('wpf-ai-suggest-loading');
				btn.find('span').text(wpforo_phrase('Suggest Reply'));

				if (response.success) {
					var content = response.data.content || '';
					if (!content) {
						alert(wpforo_phrase('AI generated an empty reply'));
						return;
					}

					// Append content to TinyMCE editor
					var inserted = wpforoInsertIntoEditor(content);
					if (!inserted) {
						// Fallback: try to append to textarea
						var textarea = form.find('textarea[name="postbody"]');
						if (textarea.length) {
							var existingVal = textarea.val().trim();
							if (existingVal) {
								textarea.val(existingVal + '\n\n' + content);
							} else {
								textarea.val(content);
							}
						} else {
							alert(wpforo_phrase('Could not insert content into editor'));
						}
					}

					// Show credits used info
					var credits = response.data.credits_used || 0;
					if (credits > 0) {
						console.log('AI Suggest Reply: ' + credits + ' credits used');
					}
				} else {
					var errorMsg = response.data && response.data.message
						? response.data.message
						: wpforo_phrase('Failed to generate reply suggestion');
					alert(errorMsg);
				}
			},
			error: function (xhr, status, error) {
				// Reset button state (CSS handles icon visibility)
				btn.removeClass('wpf-ai-suggest-loading');
				btn.find('span').text(wpforo_phrase('Suggest Reply'));
				console.error('AI Suggest Reply error:', error);
				// Try to get error message from response (handles 429 rate limit errors)
				var errorMsg = wpforo_phrase('Network error. Please try again.');
				try {
					var response = JSON.parse(xhr.responseText);
					if (response.data && response.data.message) {
						errorMsg = response.data.message;
					}
				} catch (e) {
					// Keep default error message
				}
				alert(errorMsg);
			}
		});
	});

	/**
	 * Append content to TinyMCE editor
	 * @param {string} content HTML content to append
	 * @returns {boolean} True if successful
	 */
	function wpforoInsertIntoEditor(content) {
		// Try to find the active TinyMCE editor
		if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor) {
			var editor = tinyMCE.activeEditor;
			// Append content to existing content with line break
			var existingContent = editor.getContent().trim();
			if (existingContent) {
				editor.setContent(existingContent + '<p>&nbsp;</p>' + content);
			} else {
				editor.setContent(content);
			}
			// Focus the editor
			editor.focus();
			return true;
		}

		// Try by ID (wpForo's default editor ID)
		if (typeof tinyMCE !== 'undefined') {
			var editorIds = ['postbody', 'wpf_editor_postbody'];
			for (var i = 0; i < editorIds.length; i++) {
				var ed = tinyMCE.get(editorIds[i]);
				if (ed) {
					var existingContent = ed.getContent().trim();
					if (existingContent) {
						ed.setContent(existingContent + '<p>&nbsp;</p>' + content);
					} else {
						ed.setContent(content);
					}
					ed.focus();
					return true;
				}
			}
		}

		return false;
	}

	// =========================================================================
	// AI BUTTON VISIBILITY ANIMATIONS
	// =========================================================================

	// Trigger animations when AI buttons become visible on screen
	if ('IntersectionObserver' in window) {
		var aiButtonObserver = new IntersectionObserver(function(entries) {
			entries.forEach(function(entry) {
				if (entry.isIntersecting) {
					// Add visible class to trigger animation
					entry.target.classList.add('wpf-ai-visible');
					// Stop observing after animation triggered
					aiButtonObserver.unobserve(entry.target);
				}
			});
		}, {
			threshold: 0.5 // Trigger when 50% visible
		});

		// Observe AI Helper Toggle buttons
		document.querySelectorAll('.wpf-ai-helper-toggle').forEach(function(el) {
			aiButtonObserver.observe(el);
		});

		// Observe AI Summarize buttons
		document.querySelectorAll('.wpf-ai-summarize-btn').forEach(function(el) {
			aiButtonObserver.observe(el);
		});
	}
});
