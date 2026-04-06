/* global wpforo, $wpf, wpforo_phrase, wpforo_load_hide */
/**
 * wpForo AI Chatbot JavaScript
 *
 * AI-powered chat functionality for the AI Assistant widget
 * Requires: frontend.js to be loaded first for $wpf, wpforo_phrase
 *
 * @since 3.0.0
 */

$wpf(document).ready(function ($) {
	var wpforo_wrap = $('#wpforo-wrap');
	var chatContainer = wpforo_wrap.find('.wpf-ai-chat');

	if (!chatContainer.length) {
		return;
	}

	var chatNonce = chatContainer.data('nonce');
	var currentConversationId = null;
	var isLoading = false;

	// =========================================================================
	// HELPER FUNCTIONS
	// =========================================================================

	/**
	 * Format timestamp for display
	 */
	function formatTime(dateStr) {
		if (!dateStr) return '';
		var date = new Date(dateStr);
		return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
	}

	/**
	 * Format date for conversation list
	 */
	function formatDate(dateStr) {
		if (!dateStr) return '';
		var date = new Date(dateStr);
		var now = new Date();
		var diff = now - date;
		var dayMs = 24 * 60 * 60 * 1000;

		if (diff < dayMs) {
			return wpforo_phrase('Today');
		} else if (diff < 2 * dayMs) {
			return wpforo_phrase('Yesterday');
		} else if (diff < 7 * dayMs) {
			return date.toLocaleDateString([], { weekday: 'short' });
		} else {
			return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
		}
	}

	/**
	 * Escape HTML for user-generated content
	 */
	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	/**
	 * Auto-resize textarea
	 */
	function autoResizeTextarea(textarea) {
		textarea.style.height = 'auto';
		textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
	}

	// =========================================================================
	// CONVERSATIONS MANAGEMENT
	// =========================================================================

	/**
	 * Load conversations list
	 */
	function loadConversations() {
		var listContainer = chatContainer.find('.wpf-ai-chat-conversations');
		listContainer.html('<div class="wpf-ai-chat-loading"><i class="fas fa-spinner fa-spin"></i></div>');

		$.ajax({
			url: wpforo.ajax_url,
			type: 'GET',
			data: {
				action: 'wpforo_ai_chat_get_conversations',
				nonce: chatNonce
			},
			success: function (response) {
				if (response.success && response.data.conversations) {
					renderConversationsList(response.data.conversations);
				} else {
					listContainer.html('<div class="wpf-ai-chat-empty">' + wpforo_phrase('No conversations yet') + '</div>');
				}
			},
			error: function () {
				listContainer.html('<div class="wpf-ai-chat-error">' + wpforo_phrase('Error loading conversations') + '</div>');
			}
		});
	}

	/**
	 * Render conversations list
	 */
	function renderConversationsList(conversations) {
		var listContainer = chatContainer.find('.wpf-ai-chat-conversations');

		if (!conversations || conversations.length === 0) {
			listContainer.html('<div class="wpf-ai-chat-empty">' + wpforo_phrase('No conversations yet') + '</div>');
			return;
		}

		var html = '';
		conversations.forEach(function (conv) {
			var title = conv.title || wpforo_phrase('New Conversation');
			var date = formatDate(conv.updated_at || conv.created_at);
			var activeClass = (conv.conversation_id == currentConversationId) ? ' wpf-ai-chat-conv-active' : '';

			html += '<div class="wpf-ai-chat-conv' + activeClass + '" data-id="' + conv.conversation_id + '">';
			html += '<div class="wpf-ai-chat-conv-content">';
			html += '<div class="wpf-ai-chat-conv-title">' + escapeHtml(title) + '</div>';
			html += '<div class="wpf-ai-chat-conv-meta">';
			html += '<span class="wpf-ai-chat-conv-date">' + date + '</span>';
			html += '<span class="wpf-ai-chat-conv-count">' + conv.message_count + ' ' + wpforo_phrase('messages') + '</span>';
			html += '</div>';
			html += '</div>';
			html += '<button type="button" class="wpf-ai-chat-conv-delete" title="' + wpforo_phrase('Delete') + '">';
			html += '<i class="fas fa-trash"></i>';
			html += '</button>';
			html += '</div>';
		});

		listContainer.html(html);
	}

	/**
	 * Check conversation limit before creating
	 */
	function checkConversationLimit(callback) {
		$.ajax({
			url: wpforo.ajax_url,
			type: 'GET',
			data: {
				action: 'wpforo_ai_chat_check_limit',
				nonce: chatNonce
			},
			success: function (response) {
				if (response.success) {
					callback(response.data);
				} else {
					callback(null);
				}
			},
			error: function () {
				callback(null);
			}
		});
	}

	/**
	 * Show limit warning dialog
	 */
	function showLimitWarningDialog(limitInfo) {
		var oldest = limitInfo.oldest_conversation;
		var title = oldest.title;
		var msgCount = oldest.message_count;

		var message = wpforo_phrase('You have reached the maximum number of conversations') + ' (' + limitInfo.max_allowed + ').\n\n';
		message += wpforo_phrase('Creating a new conversation will automatically delete the oldest one') + ':\n';
		message += '"' + title + '" (' + msgCount + ' ' + wpforo_phrase('messages') + ')\n\n';
		message += wpforo_phrase('You can manually delete a different conversation from the list, or proceed to auto-delete the oldest one.');

		return confirm(message);
	}

	/**
	 * Create new conversation (with limit check)
	 */
	function createConversation() {
		if (isLoading) return;

		// First check if at limit
		checkConversationLimit(function (limitInfo) {
			if (limitInfo && limitInfo.at_limit) {
				// Show warning dialog
				if (!showLimitWarningDialog(limitInfo)) {
					// User cancelled - don't create
					return;
				}
			}

			// Proceed with creation
			doCreateConversation();
		});
	}

	/**
	 * Actually create the conversation
	 */
	function doCreateConversation() {
		if (isLoading) return;
		isLoading = true;

		$.ajax({
			url: wpforo.ajax_url,
			type: 'POST',
			data: {
				action: 'wpforo_ai_chat_create_conversation',
				nonce: chatNonce
			},
			success: function (response) {
				isLoading = false;
				if (response.success && response.data.conversation) {
					currentConversationId = response.data.conversation.conversation_id;
					loadConversations();
					showChatArea(response.data.conversation, response.data.welcome_message);
				} else {
					alert(response.data?.message || wpforo_phrase('Error creating conversation'));
				}
			},
			error: function () {
				isLoading = false;
				alert(wpforo_phrase('Network error. Please try again.'));
			}
		});
	}

	/**
	 * Delete conversation
	 */
	function deleteConversation(conversationId) {
		if (isLoading) return;

		if (!confirm(wpforo_phrase('Are you sure you want to delete this conversation?'))) {
			return;
		}

		isLoading = true;

		$.ajax({
			url: wpforo.ajax_url,
			type: 'POST',
			data: {
				action: 'wpforo_ai_chat_delete_conversation',
				nonce: chatNonce,
				conversation_id: conversationId
			},
			success: function (response) {
				isLoading = false;
				if (response.success) {
					if (conversationId == currentConversationId) {
						currentConversationId = null;
						showWelcomeArea();
					}
					loadConversations();
				} else {
					alert(response.data?.message || wpforo_phrase('Error deleting conversation'));
				}
			},
			error: function () {
				isLoading = false;
				alert(wpforo_phrase('Network error. Please try again.'));
			}
		});
	}

	/**
	 * Load conversation messages
	 */
	function loadConversation(conversationId) {
		if (isLoading) return;
		isLoading = true;

		currentConversationId = conversationId;

		// Update active state in list
		chatContainer.find('.wpf-ai-chat-conv').removeClass('wpf-ai-chat-conv-active');
		chatContainer.find('.wpf-ai-chat-conv[data-id="' + conversationId + '"]').addClass('wpf-ai-chat-conv-active');

		// Show loading in messages area
		var messagesContainer = chatContainer.find('.wpf-ai-chat-messages');
		messagesContainer.html('<div class="wpf-ai-chat-loading"><i class="fas fa-spinner fa-spin"></i></div>');

		$.ajax({
			url: wpforo.ajax_url,
			type: 'GET',
			data: {
				action: 'wpforo_ai_chat_get_messages',
				nonce: chatNonce,
				conversation_id: conversationId
			},
			success: function (response) {
				isLoading = false;
				if (response.success) {
					showChatArea(response.data.conversation, null, response.data.messages);
				} else {
					messagesContainer.html('<div class="wpf-ai-chat-error">' + (response.data?.message || wpforo_phrase('Error loading messages')) + '</div>');
				}
			},
			error: function () {
				isLoading = false;
				messagesContainer.html('<div class="wpf-ai-chat-error">' + wpforo_phrase('Network error. Please try again.') + '</div>');
			}
		});
	}

	// =========================================================================
	// CHAT DISPLAY
	// =========================================================================

	/**
	 * Show welcome area (no conversation selected)
	 */
	function showWelcomeArea() {
		var messagesContainer = chatContainer.find('.wpf-ai-chat-messages');
		var inputWrap = chatContainer.find('.wpf-ai-chat-input-wrap');

		messagesContainer.html(
			'<div class="wpf-ai-chat-welcome">' +
			'<div class="wpf-ai-chat-welcome-icon">' +
			'<svg viewBox="0 0 24 24" fill="currentColor" width="48" height="48">' +
			'<path d="M9.5 2l1.5 4.5L15.5 8l-4.5 1.5L9.5 14l-1.5-4.5L3.5 8l4.5-1.5L9.5 2z"/>' +
			'<path d="M18 12l1 3 3 1-3 1-1 3-1-3-3-1 3-1 1-3z"/>' +
			'</svg>' +
			'</div>' +
			'<p class="wpf-ai-chat-welcome-text">' + wpforo_phrase('Start a new conversation or select an existing one') + '</p>' +
			'</div>'
		);

		inputWrap.hide();
		chatContainer.find('input[name="conversation_id"]').val('');
	}

	/**
	 * Show chat area with messages
	 */
	function showChatArea(conversation, welcomeMessage, messages) {
		var messagesContainer = chatContainer.find('.wpf-ai-chat-messages');
		var inputWrap = chatContainer.find('.wpf-ai-chat-input-wrap');

		// Set conversation ID in form
		chatContainer.find('input[name="conversation_id"]').val(conversation.conversation_id);

		// Build messages HTML
		var html = '';

		// Add welcome message if provided
		if (welcomeMessage) {
			html += renderMessage({
				role: 'assistant',
				content: welcomeMessage,
				created_at: new Date().toISOString()
			});
		}

		// Add existing messages
		if (messages && messages.length > 0) {
			messages.forEach(function (msg) {
				html += renderMessage(msg);
			});
		}

		if (!html) {
			html = '<div class="wpf-ai-chat-empty">' + wpforo_phrase('No messages yet. Start typing!') + '</div>';
		}

		messagesContainer.html(html);
		inputWrap.show();

		// Scroll to bottom
		scrollToBottom();

		// Focus input
		chatContainer.find('.wpf-ai-chat-input').focus();
	}

	/**
	 * Render a single message
	 */
	function renderMessage(msg) {
		var isUser = msg.role === 'user';
		var className = isUser ? 'wpf-ai-chat-msg-user' : 'wpf-ai-chat-msg-assistant';
		var time = formatTime(msg.created_at);

		// User messages: escape HTML and convert newlines
		// Assistant messages: display as-is (already formatted by PHP)
		var content = isUser
			? escapeHtml(msg.content).replace(/\n/g, '<br>')
			: msg.content;

		var html = '<div class="wpf-ai-chat-msg ' + className + '">';
		html += '<div class="wpf-ai-chat-msg-content">';
		html += '<div class="wpf-ai-chat-msg-text">' + content + '</div>';

		// Add sources if available (only show if at least one source has a valid URL)
		if (msg.sources && msg.sources.length > 0) {
			var sourcesHtml = '';
			msg.sources.forEach(function (source) {
				if (source.url) {
					var postId = source.post_id || '';
					var title = source.title || wpforo_phrase('View topic');
					sourcesHtml += '<a href="' + escapeHtml(source.url) + '" class="wpf-ai-chat-msg-source" target="_blank">';
					sourcesHtml += postId ? '[' + escapeHtml(postId) + '] ' : '';
					sourcesHtml += escapeHtml(title);
					sourcesHtml += '</a>';
				}
			});
			// Only add sources section if we have valid source links
			if (sourcesHtml) {
				html += '<div class="wpf-ai-chat-msg-sources">';
				html += '<span class="wpf-ai-chat-msg-sources-label">' + wpforo_phrase('Sources') + ':</span>';
				html += sourcesHtml;
				html += '</div>';
			}
		}

		html += '<div class="wpf-ai-chat-msg-time">' + time + '</div>';
		html += '</div>';
		html += '</div>';

		return html;
	}

	/**
	 * Append message to chat
	 */
	function appendMessage(msg) {
		var messagesContainer = chatContainer.find('.wpf-ai-chat-messages');

		// Remove empty message placeholder
		messagesContainer.find('.wpf-ai-chat-empty').remove();

		// Append message
		messagesContainer.append(renderMessage(msg));

		// Scroll to bottom
		scrollToBottom();
	}

	/**
	 * Show typing indicator
	 */
	function showTypingIndicator() {
		var messagesContainer = chatContainer.find('.wpf-ai-chat-messages');
		messagesContainer.append(
			'<div class="wpf-ai-chat-typing">' +
			'<span></span><span></span><span></span>' +
			'</div>'
		);
		scrollToBottom();
	}

	/**
	 * Hide typing indicator
	 */
	function hideTypingIndicator() {
		chatContainer.find('.wpf-ai-chat-typing').remove();
	}

	/**
	 * Scroll chat to bottom
	 */
	function scrollToBottom() {
		var messagesContainer = chatContainer.find('.wpf-ai-chat-messages');
		messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
	}

	// =========================================================================
	// MESSAGE SENDING
	// =========================================================================

	/**
	 * Send a chat message
	 */
	function sendMessage(message) {
		if (isLoading || !currentConversationId || !message.trim()) return;
		isLoading = true;

		// Hide global wpforo loading indicator (chat has its own typing indicator)
		wpforo_load_hide();

		var textarea = chatContainer.find('.wpf-ai-chat-input');
		var sendBtn = chatContainer.find('.wpf-ai-chat-send');

		// Disable input
		textarea.prop('disabled', true);
		sendBtn.prop('disabled', true);

		// Add user message to chat
		appendMessage({
			role: 'user',
			content: message,
			created_at: new Date().toISOString()
		});

		// Show typing indicator
		showTypingIndicator();

		// Clear input
		textarea.val('').css('height', 'auto');

		// Get local context if on a topic page
		var localContext = getLocalContext();

		$.ajax({
			url: wpforo.ajax_url,
			type: 'POST',
			data: {
				action: 'wpforo_ai_chat_send_message',
				nonce: chatNonce,
				conversation_id: currentConversationId,
				message: message,
				local_context: localContext
			},
			success: function (response) {
				isLoading = false;
				hideTypingIndicator();
				wpforo_load_hide();
				textarea.prop('disabled', false);
				sendBtn.prop('disabled', false);

				if (response.success) {
					appendMessage({
						role: 'assistant',
						content: response.data.response,
						sources: response.data.sources,
						created_at: new Date().toISOString()
					});

					// Update conversation in list (message count)
					loadConversations();
				} else {
					appendMessage({
						role: 'assistant',
						content: response.data?.message || wpforo_phrase('Sorry, I encountered an error. Please try again.'),
						created_at: new Date().toISOString()
					});
				}

				textarea.focus();
			},
			error: function () {
				isLoading = false;
				hideTypingIndicator();
				wpforo_load_hide();
				textarea.prop('disabled', false);
				sendBtn.prop('disabled', false);

				appendMessage({
					role: 'assistant',
					content: wpforo_phrase('Network error. Please check your connection and try again.'),
					created_at: new Date().toISOString()
				});

				textarea.focus();
			}
		});
	}

	/**
	 * Get local context from current page
	 */
	function getLocalContext() {
		var context = {};

		// Check if we're on a topic page
		var topicTitle = wpforo_wrap.find('.wpforo-topic-title h1').text().trim();
		if (topicTitle) {
			context.topic_title = topicTitle;
		}

		// Get forum name from breadcrumb
		var forumName = wpforo_wrap.find('.wpforo-breadcrumb a').last().text().trim();
		if (forumName) {
			context.forum_name = forumName;
		}

		// Get topic content (first post)
		var topicContent = wpforo_wrap.find('.wpforo-post:first .wpf-post-content').text().trim();
		if (topicContent) {
			context.topic_content = topicContent.substring(0, 1000); // Limit to 1000 chars
		}

		return Object.keys(context).length > 0 ? context : null;
	}

	// =========================================================================
	// EVENT HANDLERS
	// =========================================================================

	// Load conversations when chat tab is activated
	wpforo_wrap.on('click', '.wpf-ai-tab[data-tab="ai-chat"]', function () {
		if (!chatContainer.data('loaded')) {
			loadConversations();
			chatContainer.data('loaded', true);
		}
	});

	// Create new conversation
	chatContainer.on('click', '.wpf-ai-chat-new', function (e) {
		e.preventDefault();
		createConversation();
	});

	// Select conversation
	chatContainer.on('click', '.wpf-ai-chat-conv', function (e) {
		if ($(e.target).closest('.wpf-ai-chat-conv-delete').length) {
			return; // Don't select if clicking delete button
		}
		var convId = $(this).data('id');
		loadConversation(convId);
	});

	// Delete conversation
	chatContainer.on('click', '.wpf-ai-chat-conv-delete', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var convId = $(this).closest('.wpf-ai-chat-conv').data('id');
		deleteConversation(convId);
	});

	// Send message form submit
	chatContainer.on('submit', '.wpf-ai-chat-form', function (e) {
		e.preventDefault();
		e.stopPropagation(); // Prevent global form handler from showing loading indicator
		var message = chatContainer.find('.wpf-ai-chat-input').val().trim();
		if (message) {
			sendMessage(message);
		}
	});

	// Enable/disable send button based on input
	chatContainer.on('input', '.wpf-ai-chat-input', function () {
		var textarea = $(this);
		var sendBtn = chatContainer.find('.wpf-ai-chat-send');
		sendBtn.prop('disabled', !textarea.val().trim());
		autoResizeTextarea(this);
	});

	// Handle Enter key to send (Shift+Enter for new line)
	chatContainer.on('keydown', '.wpf-ai-chat-input', function (e) {
		if (e.key === 'Enter' && !e.shiftKey) {
			e.preventDefault();
			chatContainer.find('.wpf-ai-chat-form').submit();
		}
	});

	// Initial load if chat tab is already active
	if (chatContainer.closest('.wpf-ai-tab-content-active').length) {
		loadConversations();
		chatContainer.data('loaded', true);
	}
});
