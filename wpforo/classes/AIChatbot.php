<?php

namespace wpforo\classes;

/**
 * wpForo AI Chatbot
 *
 * Handles AI-powered chat functionality including:
 * - Conversation management (create, list, delete)
 * - Message handling with context management
 * - Integration with RAG for forum knowledge
 * - AJAX handlers for frontend chat widget
 *
 * @since 3.0.0
 */
class AIChatbot {
	use AIAjaxTrait;

	/**
	 * Maximum conversations per user (default)
	 */
	const DEFAULT_MAX_CONVERSATIONS = 10;

	/**
	 * Context update threshold (messages)
	 */
	const DEFAULT_CONTEXT_UPDATE_THRESHOLD = 5;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Only register AJAX handlers if chatbot is enabled
		// Note: Only wp_ajax (logged-in users) - guests cannot use chatbot
		if ( $this->is_enabled() ) {
			add_action( 'wp_ajax_wpforo_ai_chat_send_message', [ $this, 'ajax_send_message' ] );
			add_action( 'wp_ajax_wpforo_ai_chat_get_conversations', [ $this, 'ajax_get_conversations' ] );
			add_action( 'wp_ajax_wpforo_ai_chat_get_messages', [ $this, 'ajax_get_messages' ] );
			add_action( 'wp_ajax_wpforo_ai_chat_create_conversation', [ $this, 'ajax_create_conversation' ] );
			add_action( 'wp_ajax_wpforo_ai_chat_delete_conversation', [ $this, 'ajax_delete_conversation' ] );
			add_action( 'wp_ajax_wpforo_ai_chat_check_limit', [ $this, 'ajax_check_conversation_limit' ] );
		}
	}

	/**
	 * Check if chatbot is enabled
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return (bool) wpforo_setting( 'ai', 'chatbot' );
	}

	/**
	 * Check if current user can use chatbot
	 *
	 * @return bool
	 */
	public function user_can_chat() {
		// Check if chatbot is enabled
		if ( ! $this->is_enabled() ) {
			return false;
		}

		// Guests cannot use chatbot - login required
		if ( ! WPF()->current_userid ) {
			return false;
		}

		// Check allowed usergroups
		$allowed_groups = wpforo_setting( 'ai', 'chatbot_allowed_groups' );
		if ( ! empty( $allowed_groups ) && is_array( $allowed_groups ) ) {
			$user_groupid = WPF()->current_user_groupid;
			if ( ! in_array( (int) $user_groupid, $allowed_groups, false ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get conversations for current user
	 *
	 * @param int $limit Maximum number of conversations
	 * @return array
	 */
	public function get_conversations( $limit = 10 ) {
		$userid = WPF()->current_userid;

		$table = WPF()->tables->ai_chat_conversations;
		$sql = WPF()->db->prepare(
			"SELECT * FROM `{$table}` WHERE userid = %d ORDER BY updated_at DESC LIMIT %d",
			$userid,
			$limit
		);

		$conversations = WPF()->db->get_results( $sql, ARRAY_A );

		// Parse key_facts JSON
		foreach ( $conversations as &$conv ) {
			$conv['key_facts'] = $conv['key_facts'] ? json_decode( $conv['key_facts'], true ) : [];
		}

		return $conversations ?: [];
	}

	/**
	 * Get a specific conversation
	 *
	 * @param int $conversation_id
	 * @return array|null
	 */
	public function get_conversation( $conversation_id ) {
		$userid = WPF()->current_userid;

		$table = WPF()->tables->ai_chat_conversations;
		$sql = WPF()->db->prepare(
			"SELECT * FROM `{$table}` WHERE conversation_id = %d AND userid = %d",
			$conversation_id,
			$userid
		);

		$conversation = WPF()->db->get_row( $sql, ARRAY_A );

		if ( $conversation ) {
			$conversation['key_facts'] = $conversation['key_facts'] ? json_decode( $conversation['key_facts'], true ) : [];
		}

		return $conversation;
	}

	/**
	 * Create a new conversation
	 *
	 * @param string $title Optional title
	 * @return int|false Conversation ID or false on failure
	 */
	public function create_conversation( $title = '' ) {
		$userid = WPF()->current_userid;

		// Check max conversations limit
		$max = intval( wpforo_setting( 'ai', 'chatbot_max_conversations' ) ) ?: self::DEFAULT_MAX_CONVERSATIONS;
		$existing = $this->get_conversations( $max + 1 );

		// Delete oldest conversations if over limit
		if ( count( $existing ) >= $max ) {
			$to_delete = array_slice( $existing, $max - 1 );
			foreach ( $to_delete as $conv ) {
				$this->delete_conversation( $conv['conversation_id'] );
			}
		}

		$table = WPF()->tables->ai_chat_conversations;
		$result = WPF()->db->insert(
			$table,
			[
				'userid' => $userid,
				'title'  => $title ?: '',
				'message_count' => 0,
				'total_tokens' => 0,
				'total_credits' => 0,
			],
			[ '%d', '%s', '%d', '%d', '%d' ]
		);

		return $result ? WPF()->db->insert_id : false;
	}

	/**
	 * Update conversation context (summary and key facts)
	 *
	 * @param int    $conversation_id
	 * @param string $summary
	 * @param array  $key_facts
	 * @return bool
	 */
	public function update_conversation_context( $conversation_id, $summary, $key_facts = [] ) {
		$userid = WPF()->current_userid;

		$table = WPF()->tables->ai_chat_conversations;
		$result = WPF()->db->update(
			$table,
			[
				'running_summary' => $summary,
				'key_facts'       => json_encode( $key_facts ),
			],
			[
				'conversation_id' => $conversation_id,
				'userid'          => $userid,
			],
			[ '%s', '%s' ],
			[ '%d', '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Delete a conversation and its messages
	 *
	 * @param int $conversation_id
	 * @return bool
	 */
	public function delete_conversation( $conversation_id ) {
		$userid = WPF()->current_userid;

		// Delete messages first
		$messages_table = WPF()->tables->ai_chat_messages;
		WPF()->db->delete(
			$messages_table,
			[ 'conversation_id' => $conversation_id ],
			[ '%d' ]
		);

		// Delete conversation
		$conv_table = WPF()->tables->ai_chat_conversations;
		$result = WPF()->db->delete(
			$conv_table,
			[
				'conversation_id' => $conversation_id,
				'userid'          => $userid,
			],
			[ '%d', '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Get messages for a conversation
	 *
	 * @param int $conversation_id
	 * @param int $limit
	 * @return array
	 */
	public function get_messages( $conversation_id, $limit = 100 ) {

		// Verify conversation ownership
		$conversation = $this->get_conversation( $conversation_id );
		if ( ! $conversation ) {
			return [];
		}

		$table = WPF()->tables->ai_chat_messages;
		$sql = WPF()->db->prepare(
			"SELECT * FROM `{$table}` WHERE conversation_id = %d ORDER BY created_at ASC LIMIT %d",
			$conversation_id,
			$limit
		);

		$messages = WPF()->db->get_results( $sql, ARRAY_A );

		// Parse sources_json and format assistant messages
		foreach ( $messages as &$msg ) {
			$msg['sources'] = $msg['sources_json'] ? json_decode( $msg['sources_json'], true ) : [];
			unset( $msg['sources_json'] );

			// Convert post_id to URL in sources (regenerate URLs from stored post_ids)
			if ( ! empty( $msg['sources'] ) ) {
				foreach ( $msg['sources'] as &$source ) {
					if ( ! empty( $source['post_id'] ) && empty( $source['url'] ) ) {
						$content_source = $source['content_source'] ?? 'wpforo';
						if ( $content_source === 'wordpress' ) {
							// WordPress content - use get_permalink() or stored permalink
							if ( ! empty( $source['permalink'] ) ) {
								$source['url'] = $source['permalink'];
							} else {
								$source['url'] = get_permalink( (int) $source['post_id'] );
							}
						} else {
							// wpForo content - use WPF()->post->get_url()
							$source['url'] = WPF()->post->get_url( (int) $source['post_id'] );
						}
					}
				}
			}

			// Format assistant messages (convert markdown to HTML)
			if ( $msg['role'] === 'assistant' && ! empty( $msg['content'] ) ) {
				// Replace [NO_FORUM_CONTENT] placeholder with custom message
				$original_content = $msg['content'];
				$msg['content'] = $this->replace_no_content_placeholder( $msg['content'] );
				$has_no_content = ( $msg['content'] !== $original_content );

				// Skip markdown formatting for no-content messages (they may contain HTML)
				if ( ! $has_no_content ) {
					// Format markdown first, then convert [[#POST_ID:Title]] markers to clickable links
					// (link markers must be processed AFTER format_ai_response to avoid HTML escaping)
					$msg['content'] = $this->format_ai_response( $msg['content'] );
					$msg['content'] = $this->replace_post_link_markers( $msg['content'] );
				}
			}
		}

		return $messages ?: [];
	}

	/**
	 * Get recent messages for context
	 *
	 * @param int $conversation_id
	 * @param int $limit
	 * @return array
	 */
	public function get_recent_messages( $conversation_id, $limit = 3 ) {

		$table = WPF()->tables->ai_chat_messages;
		$sql = WPF()->db->prepare(
			"SELECT role, content FROM `{$table}` WHERE conversation_id = %d ORDER BY created_at DESC LIMIT %d",
			$conversation_id,
			$limit
		);

		$messages = WPF()->db->get_results( $sql, ARRAY_A );

		// Reverse to get chronological order
		return array_reverse( $messages ?: [] );
	}

	/**
	 * Add a message to conversation
	 *
	 * @param int    $conversation_id
	 * @param string $role 'user' or 'assistant'
	 * @param string $content
	 * @param array  $metadata Optional metadata (tokens, credits, sources, etc.)
	 * @return int|false Message ID or false on failure
	 */
	public function add_message( $conversation_id, $role, $content, $metadata = [] ) {
		$table = WPF()->tables->ai_chat_messages;

		$data = [
			'conversation_id' => $conversation_id,
			'role'            => $role,
			'content'         => $content,
			'tokens_used'     => $metadata['tokens_used'] ?? 0,
			'credits_spent'   => $metadata['credits_spent'] ?? 0,
			'quality_tier'    => $metadata['quality_tier'] ?? 'fast',
			'sources_count'   => $metadata['sources_count'] ?? 0,
			'sources_json'    => ! empty( $metadata['sources'] ) ? json_encode( $metadata['sources'] ) : null,
			'context_updated' => $metadata['context_updated'] ?? 0,
		];

		$result = WPF()->db->insert(
			$table,
			$data,
			[ '%d', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%d' ]
		);

		if ( $result ) {
			// Update conversation stats
			$this->update_conversation_stats(
				$conversation_id,
				$content,
				$metadata['tokens_used'] ?? 0,
				$metadata['credits_spent'] ?? 0
			);
			return WPF()->db->insert_id;
		}

		return false;
	}

	/**
	 * Update conversation statistics
	 *
	 * @param int    $conversation_id
	 * @param string $message_content For title generation
	 * @param int    $tokens
	 * @param int    $credits
	 */
	private function update_conversation_stats( $conversation_id, $message_content, $tokens, $credits ) {
		$table = WPF()->tables->ai_chat_conversations;
		$conversation = $this->get_conversation( $conversation_id );

		if ( ! $conversation ) {
			return;
		}

		$updates = [
			'message_count'   => $conversation['message_count'] + 1,
			'total_tokens'    => $conversation['total_tokens'] + $tokens,
			'total_credits'   => $conversation['total_credits'] + $credits,
			'last_message_at' => current_time( 'mysql' ),
		];
		$formats = [ '%d', '%d', '%d', '%s' ];

		// Set title from first user message if empty (25 chars max)
		if ( empty( $conversation['title'] ) && $conversation['message_count'] == 0 ) {
			$title = trim( strip_tags( $message_content ) );
			$updates['title'] = mb_strlen( $title ) > 25 ? mb_substr( $title, 0, 25 ) . '...' : $title;
			$formats[] = '%s';
		}

		WPF()->db->update(
			$table,
			$updates,
			[ 'conversation_id' => $conversation_id ],
			$formats,
			[ '%d' ]
		);
	}

	/**
	 * Send message to AI and get response
	 *
	 * @param int    $conversation_id
	 * @param string $message
	 * @param array  $local_context Optional local page context
	 * @return array|WP_Error
	 */
	public function send_message( $conversation_id, $message, $local_context = [] ) {
		$conversation = $this->get_conversation( $conversation_id );
		if ( ! $conversation ) {
			return new \WP_Error( 'not_found', __( 'Conversation not found', 'wpforo' ) );
		}

		// Build conversation context for API
		$recent_messages = $this->get_recent_messages( $conversation_id, 3 );

		$conversation_context = [
			'running_summary' => $conversation['running_summary'] ?? '',
			'key_facts'       => $conversation['key_facts'] ?? [],
			'recent_messages' => $recent_messages,
			'message_count'   => (int) $conversation['message_count'],
		];

		// Get settings
		$quality = wpforo_setting( 'ai', 'chatbot_quality' ) ?: 'fast';
		$use_rag = (bool) wpforo_setting( 'ai', 'chatbot_use_rag' );
		$use_local = (bool) wpforo_setting( 'ai', 'chatbot_use_local_context' );
		$context_threshold = (int) wpforo_setting( 'ai', 'chatbot_context_update_threshold' ) ?: self::DEFAULT_CONTEXT_UPDATE_THRESHOLD;
		$no_content_message = wpforo_setting( 'ai', 'chatbot_no_content_message' ) ?: '';
		$min_score_setting = (int) wpforo_setting( 'ai', 'chatbot_min_score' );

		// Get response language
		$language = WPF()->ai_client->get_user_language( null, 'chatbot_language' );

		// Build request data
		$request_data = [
			'message'              => $message,
			'conversation_context' => $conversation_context,
			'settings'             => [
				'quality'                   => $quality,
				'language'                  => $language,
				'use_rag'                   => $use_rag,
				'context_update_threshold'  => $context_threshold,
				'no_content_message'        => $no_content_message,
			],
		];

		// Add min_score to settings if set (for cloud RAG filtering)
		// Converted from percentage (30) to decimal (0.3) for API
		if ( $min_score_setting > 0 ) {
			$request_data['settings']['min_score'] = $min_score_setting / 100;
		}

		// Add local context if enabled
		if ( $use_local && ! empty( $local_context ) ) {
			$request_data['local_context'] = $local_context;
		}

		// Check storage mode and perform local RAG search if needed
		// When in local mode, WordPress performs the vector search and sends results to API
		if ( $use_rag && WPF()->vector_storage->is_local_mode() ) {
			$rag_results = $this->perform_local_rag_search( $message );
			if ( ! is_wp_error( $rag_results ) && ! empty( $rag_results ) ) {
				$request_data['rag_context'] = $rag_results;
			}
		}

		// Call chat API via AIClient
		$response = WPF()->ai_client->api_post( '/chat/message', $request_data, 60 );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Store user message
		$this->add_message( $conversation_id, 'user', $message );

		// Store assistant response
		$credits_map = [ 'fast' => 1, 'balanced' => 2, 'advanced' => 3, 'premium' => 4 ];
		$credits = $credits_map[ $quality ] ?? 2;

		$this->add_message( $conversation_id, 'assistant', $response['response'], [
			'tokens_used'     => $response['tokens_used'] ?? 0,
			'credits_spent'   => $credits,
			'quality_tier'    => $quality,
			'sources_count'   => count( $response['sources'] ?? [] ),
			'sources'         => $response['sources'] ?? [],
			'context_updated' => ! empty( $response['context_update']['should_update'] ) ? 1 : 0,
		] );

		// Update conversation context if API returned updates
		if ( ! empty( $response['context_update']['should_update'] ) ) {
			$this->update_conversation_context(
				$conversation_id,
				$response['context_update']['summary'] ?? '',
				$response['context_update']['key_facts'] ?? []
			);
		}

		// Replace [NO_FORUM_CONTENT] placeholder if present, then format
		$response_text = $this->replace_no_content_placeholder( $response['response'] );
		$has_no_content = ( $response_text !== $response['response'] );

		// Format markdown first, then convert [[#POST_ID:Title]] markers to clickable links
		// (link markers must be processed AFTER format_ai_response to avoid HTML escaping)
		$formatted_response = $has_no_content ? $response_text : $this->format_ai_response( $response_text );
		$formatted_response = $has_no_content ? $formatted_response : $this->replace_post_link_markers( $formatted_response );

		// Convert post_id to url in sources (API sends post_id, PHP generates URL)
		$sources = $has_no_content ? [] : ( $response['sources'] ?? [] );
		foreach ( $sources as &$source ) {
			if ( ! empty( $source['post_id'] ) && empty( $source['url'] ) ) {
				$content_source = $source['content_source'] ?? 'wpforo';
				if ( $content_source === 'wordpress' ) {
					// WordPress content - use get_permalink() or stored permalink
					if ( ! empty( $source['permalink'] ) ) {
						$source['url'] = $source['permalink'];
					} else {
						$source['url'] = get_permalink( (int) $source['post_id'] );
					}
				} else {
					// wpForo content - use WPF()->post->get_url()
					$source['url'] = WPF()->post->get_url( (int) $source['post_id'] );
				}
			}
		}

		return [
			'response' => $formatted_response,
			'sources'  => $sources,
		];
	}

	/**
	 * Replace [NO_FORUM_CONTENT] placeholder with custom message
	 *
	 * @param string $text Response text
	 * @return string Text with placeholder replaced (or original if no placeholder)
	 */
	private function replace_no_content_placeholder( $text ) {
		if ( strpos( $text, '[NO_FORUM_CONTENT]' ) === false ) {
			return $text;
		}

		$custom_message = wpforo_setting( 'ai', 'chatbot_no_content_message' );
		if ( ! empty( $custom_message ) ) {
			// Replace placeholders with actual URLs
			$add_topic_url = wpforo_home_url( '/add-topic/' );
			$custom_message = str_replace( '{add_topic_url}', esc_url( $add_topic_url ), $custom_message );

			// Use custom message with HTML preserved (sanitize with allowed tags)
			$allowed_tags = '<a><br><p><img><strong><em><ul><ol><li>';
			return strip_tags( $custom_message, $allowed_tags );
		}

		// Default message if no custom message set
		return __( "I couldn't find information about that topic in the forum. Please try searching directly or create a new topic to start a discussion.", 'wpforo' );
	}

	/**
	 * Replace post link markers with superscript reference links
	 *
	 * Converts [[#POST_ID]] and [[#POST_ID:Title]] markers to superscript reference links.
	 * Also handles WordPress content markers like [[#wp_123]] and [[#wp_123:Title]].
	 * Format: <sup class="wpf-ai-chat-reference"><a href="url">[POST_ID]</a></sup>
	 *
	 * @param string $text Text containing link markers
	 * @return string Text with markers replaced by superscript links
	 */
	private function replace_post_link_markers( $text ) {
		if ( empty( $text ) ) {
			return $text;
		}

		// First: Normalize common wrong formats from LLM
		// Handle grouped citations like [[#1360],[#1506]] or [[#1360], [#1506]]
		$text = preg_replace_callback(
			'/\[\[#(\d+)\](?:,\s*\[#(\d+)\])+\]/',
			function ( $matches ) {
				// Extract all IDs from the grouped format
				preg_match_all( '/#(\d+)/', $matches[0], $ids );
				$result = [];
				foreach ( $ids[1] as $id ) {
					$result[] = '[[#' . $id . ']]';
				}
				return implode( ' ', $result );
			},
			$text
		);

		// Handle double brackets without hash: [[1360]] -> [[#1360]]
		$text = preg_replace( '/\[\[(\d+)\]\]/', '[[#$1]]', $text );

		// Handle single bracket format [1360] or [#1360] (without double brackets)
		// Only match if it looks like a post ID (not part of markdown link)
		$text = preg_replace( '/(?<!\[)\[#?(\d{2,})\](?!\])(?!\()/', '[[#$1]]', $text );

		// Early exit if no citations to process
		if ( strpos( $text, '[[#' ) === false ) {
			return $text;
		}

		// Replace [[#wp_POST_ID:Title]] format - WordPress content with title
		$text = preg_replace_callback(
			'/\[\[#wp_(\d+):([^\]]+)\]\]/',
			function ( $matches ) {
				$postid = (int) $matches[1];
				$title  = trim( $matches[2] );
				$url    = get_permalink( $postid );
				if ( empty( $url ) ) {
					return '';  // Remove invalid references
				}
				// Use title as link text, with post ID in brackets for reference
				return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $title ) . '</a> <sup class="wpf-ai-chat-reference">[wp:' . $postid . ']</sup>';
			},
			$text
		);

		// Replace [[#wp_POST_ID]] format - WordPress content
		$text = preg_replace_callback(
			'/\[\[#wp_(\d+)\]\]/',
			function ( $matches ) {
				$postid = (int) $matches[1];
				$url    = get_permalink( $postid );
				$title  = get_the_title( $postid );
				if ( empty( $url ) ) {
					return '';  // Remove invalid references
				}
				// Use post title as link text if available
				if ( ! empty( $title ) ) {
					return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $title ) . '</a> <sup class="wpf-ai-chat-reference">[wp:' . $postid . ']</sup>';
				}
				return '<sup class="wpf-ai-chat-reference"><a href="' . esc_url( $url ) . '">[wp:' . $postid . ']</a></sup>';
			},
			$text
		);

		// Replace [[#POST_ID:Title]] format - wpForo content with title (ignore title, use post ID)
		$text = preg_replace_callback(
			'/\[\[#(\d+):([^\]]+)\]\]/',
			function ( $matches ) {
				$postid = (int) $matches[1];
				$url = WPF()->post->get_url( $postid );
				if ( empty( $url ) || $url === wpforo_home_url() ) {
					return '';  // Remove invalid references
				}
				return '<sup class="wpf-ai-chat-reference"><a href="' . esc_url( $url ) . '">[' . $postid . ']</a></sup>';
			},
			$text
		);

		// Replace [[#POST_ID]] format - wpForo content
		$text = preg_replace_callback(
			'/\[\[#(\d+)\]\]/',
			function ( $matches ) {
				$postid = (int) $matches[1];
				$url = WPF()->post->get_url( $postid );
				if ( empty( $url ) || $url === wpforo_home_url() ) {
					return '';  // Remove invalid references
				}
				return '<sup class="wpf-ai-chat-reference"><a href="' . esc_url( $url ) . '">[' . $postid . ']</a></sup>';
			},
			$text
		);

		return $text;
	}

	/**
	 * Format AI response text (convert markdown to HTML)
	 *
	 * Delegates to AIMarkdown::to_html() for consistent markdown conversion.
	 *
	 * @param string $text Raw AI response text
	 * @return string Formatted HTML
	 */
	private function format_ai_response( $text ) {
		return AIMarkdown::to_html( $text, AIMarkdown::MODE_FRONTEND );
	}

	/**
	 * Perform local RAG search using VectorStorageManager
	 *
	 * Searches local MySQL vector storage and formats results for API.
	 *
	 * @param string $query Search query
	 * @param int    $limit Maximum results
	 * @return array|WP_Error Array of RAG results or WP_Error on failure
	 */
	private function perform_local_rag_search( $query, $limit = 5 ) {
		// Get chatbot-specific min_score setting and apply local mode scaling
		// Local cosine similarities are on a different scale (5-25%) than cloud scores (30-90%),
		// so apply 1/3 of the configured threshold for local mode with 15% absolute minimum.
		$min_score_setting = (int) wpforo_setting( 'ai', 'chatbot_min_score' );
		$local_threshold   = $min_score_setting > 0 ? ( $min_score_setting / 100 ) / 3 : 0;
		$absolute_min      = 0.15; // 15% - below this, results are definitely garbage
		$filters           = [ 'min_score' => max( $local_threshold, $absolute_min ) ];

		// Perform semantic search using VectorStorageManager with chatbot's score threshold
		$search_results = WPF()->vector_storage->semantic_search( $query, $limit, $filters );

		if ( is_wp_error( $search_results ) ) {
			return $search_results;
		}

		if ( empty( $search_results['results'] ) ) {
			return [];
		}

		// Transform results to API's expected rag_context format
		$rag_context = [];
		foreach ( $search_results['results'] as $result ) {
			// Get forum name for context
			$forum_name = '';
			if ( ! empty( $result['forum_id'] ) ) {
				$forum = WPF()->forum->get_forum( $result['forum_id'] );
				$forum_name = $forum['title'] ?? '';
			}

			// Get author name
			$author = '';
			if ( ! empty( $result['user_id'] ) ) {
				$user = WPF()->member->get_member( $result['user_id'] );
				$author = $user['display_name'] ?? '';
			}

			$rag_context[] = [
				'id'           => (string) ( $result['post_id'] ?? $result['topic_id'] ),
				'title'        => $result['title'] ?? 'Untitled',
				'content'      => $result['content'] ?? '',
				'url'          => $result['post_url'] ?? $result['url'] ?? '',
				'score'        => (float) ( $result['score'] ?? 0 ),
				'content_type' => ! empty( $result['post_id'] ) ? 'post' : 'topic',
				'forum_name'   => $forum_name,
				'author'       => $author,
			];
		}

		return $rag_context;
	}

	// =========================================================================
	// AJAX HANDLERS
	// =========================================================================

	/**
	 * AJAX: Send a chat message
	 */
	public function ajax_send_message() {
		$this->verify_ajax_nonce( 'wpforo_ai_chatbot', 'nonce' );

		// Check rate limit (users have daily limits, moderators exempt)
		$this->check_rate_limit( 'chatbot' );

		if ( ! $this->user_can_chat() ) {
			$this->send_error( __( 'You do not have permission to use the chatbot', 'wpforo' ), 403 );
		}

		$conversation_id = $this->get_post_param( 'conversation_id', 0, 'int' );
		$message = sanitize_textarea_field( wpfval( $_POST, 'message' ) );

		if ( ! $conversation_id || ! $message ) {
			$this->send_error( __( 'Missing required parameters', 'wpforo' ), 400 );
		}

		// Parse local context if provided
		$local_context = [];
		if ( ! empty( $_POST['local_context'] ) ) {
			$local_context = [
				'current_topic_title'   => sanitize_text_field( wpfval( $_POST['local_context'], 'topic_title' ) ),
				'current_forum_name'    => sanitize_text_field( wpfval( $_POST['local_context'], 'forum_name' ) ),
				'current_topic_content' => sanitize_textarea_field( wpfval( $_POST['local_context'], 'topic_content' ) ),
			];
		}

		$result = $this->send_message( $conversation_id, $message, $local_context );

		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message(), 500 );
		}

		$this->send_success( $result );
	}

	/**
	 * AJAX: Get user's conversations
	 */
	public function ajax_get_conversations() {
		$this->verify_ajax_nonce( 'wpforo_ai_chatbot', 'nonce' );

		if ( ! $this->user_can_chat() ) {
			$this->send_error( __( 'You do not have permission to use the chatbot', 'wpforo' ), 403 );
		}

		$max = intval( wpforo_setting( 'ai', 'chatbot_max_conversations' ) ) ?: self::DEFAULT_MAX_CONVERSATIONS;
		$conversations = $this->get_conversations( $max );

		$this->send_success( [ 'conversations' => $conversations ] );
	}

	/**
	 * AJAX: Get messages for a conversation
	 */
	public function ajax_get_messages() {
		$this->verify_ajax_nonce( 'wpforo_ai_chatbot', 'nonce' );

		if ( ! $this->user_can_chat() ) {
			$this->send_error( __( 'You do not have permission to use the chatbot', 'wpforo' ), 403 );
		}

		$conversation_id = isset( $_GET['conversation_id'] ) ? intval( $_GET['conversation_id'] ) : 0;
		if ( ! $conversation_id ) {
			$this->send_error( __( 'Missing conversation ID', 'wpforo' ), 400 );
		}

		$messages = $this->get_messages( $conversation_id );
		$conversation = $this->get_conversation( $conversation_id );

		$this->send_success( [
			'messages'     => $messages,
			'conversation' => $conversation,
		] );
	}

	/**
	 * AJAX: Create a new conversation
	 */
	public function ajax_create_conversation() {
		$this->verify_ajax_nonce( 'wpforo_ai_chatbot', 'nonce' );

		if ( ! $this->user_can_chat() ) {
			$this->send_error( __( 'You do not have permission to use the chatbot', 'wpforo' ), 403 );
		}

		$title = $this->get_post_param( 'title', '' );
		$conversation_id = $this->create_conversation( $title );

		if ( ! $conversation_id ) {
			$this->send_error( __( 'Failed to create conversation', 'wpforo' ), 500 );
		}

		$conversation = $this->get_conversation( $conversation_id );

		// Get welcome message and format it
		$welcome = wpforo_setting( 'ai', 'chatbot_welcome_message' );
		if ( empty( $welcome ) ) {
			$welcome = __( "Hello! I'm your forum assistant. I can help you find information, answer questions about discussions, and navigate the forum. What would you like to know?", 'wpforo' );
		}

		// Replace {user_display_name} placeholder with actual user name
		if ( WPF()->current_userid ) {
			$display_name = wpforo_member( WPF()->current_userid, 'display_name' );
			$welcome = str_replace( '{user_display_name}', esc_html( $display_name ), $welcome );
		} else {
			// For guests, remove the placeholder (and any space before it)
			$welcome = str_replace( ' {user_display_name}', '', $welcome );
			$welcome = str_replace( '{user_display_name}', '', $welcome );
		}

		// Convert line breaks to <br> and sanitize HTML
		$welcome = nl2br( $welcome );
		$allowed_tags = '<a><br><p><strong><em><ul><ol><li>';
		$welcome = strip_tags( $welcome, $allowed_tags );

		$this->send_success( [
			'conversation'    => $conversation,
			'welcome_message' => $welcome,
		] );
	}

	/**
	 * AJAX: Delete a conversation
	 */
	public function ajax_delete_conversation() {
		$this->verify_ajax_nonce( 'wpforo_ai_chatbot', 'nonce' );

		if ( ! $this->user_can_chat() ) {
			$this->send_error( __( 'You do not have permission to use the chatbot', 'wpforo' ), 403 );
		}

		$conversation_id = $this->get_post_param( 'conversation_id', 0, 'int' );
		if ( ! $conversation_id ) {
			$this->send_error( __( 'Missing conversation ID', 'wpforo' ), 400 );
		}

		$result = $this->delete_conversation( $conversation_id );

		if ( ! $result ) {
			$this->send_error( __( 'Failed to delete conversation', 'wpforo' ), 500 );
		}

		$this->send_success( [ 'deleted' => true ] );
	}

	/**
	 * AJAX: Check if creating a new conversation would exceed the limit
	 *
	 * Returns information about which conversation would be auto-deleted
	 * if the user creates a new one.
	 */
	public function ajax_check_conversation_limit() {
		$this->verify_ajax_nonce( 'wpforo_ai_chatbot', 'nonce' );

		if ( ! $this->user_can_chat() ) {
			$this->send_error( __( 'You do not have permission to use the chatbot', 'wpforo' ), 403 );
		}

		$max = intval( wpforo_setting( 'ai', 'chatbot_max_conversations' ) ) ?: self::DEFAULT_MAX_CONVERSATIONS;
		$conversations = $this->get_conversations( $max + 1 );
		$count = count( $conversations );

		$response = [
			'at_limit'      => $count >= $max,
			'max_allowed'   => $max,
			'current_count' => $count,
		];

		// If at limit, include info about the oldest conversation that would be deleted
		if ( $count >= $max && ! empty( $conversations ) ) {
			// Get the oldest conversation (last in the array since sorted by updated_at DESC)
			$oldest = end( $conversations );
			$response['oldest_conversation'] = [
				'conversation_id' => $oldest['conversation_id'],
				'title'           => $oldest['title'] ?: __( 'New Conversation', 'wpforo' ),
				'message_count'   => $oldest['message_count'],
				'created_at'      => $oldest['created_at'],
				'updated_at'      => $oldest['updated_at'],
			];
		}

		$this->send_success( $response );
	}
}
