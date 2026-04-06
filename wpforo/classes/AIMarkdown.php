<?php

namespace wpforo\classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AIMarkdown - Unified Markdown to HTML converter for AI features
 *
 * Provides consistent markdown conversion across all AI features with
 * mode-based behavior for different security contexts.
 *
 * Modes:
 * - MODE_FRONTEND: Strict escaping with esc_html(), placeholder protection (chatbot, search)
 * - MODE_ADMIN: Permissive with wp_kses_post(), includes strikethrough (log viewer)
 * - MODE_SIMPLE: Basic conversion without escaping (for pre-escaped content)
 *
 * @since 3.0.0
 */
class AIMarkdown {

	/**
	 * Mode constants
	 */
	const MODE_FRONTEND = 'frontend';
	const MODE_ADMIN    = 'admin';
	const MODE_SIMPLE   = 'simple';

	/**
	 * Convert markdown to HTML
	 *
	 * @param string $text    Markdown text to convert
	 * @param string $mode    Conversion mode (MODE_FRONTEND, MODE_ADMIN, MODE_SIMPLE)
	 * @param array  $options Optional settings:
	 *                        - 'convert_urls': bool - Convert plain URLs to links (default: true for frontend)
	 *                        - 'header_offset': int - Offset for header levels (default: 2 for frontend, 0 for admin)
	 *                        - 'use_wpautop': bool - Use wpautop for paragraphs (default: true for admin)
	 *
	 * @return string HTML output
	 */
	public static function to_html( $text, $mode = self::MODE_FRONTEND, $options = [] ) {
		if ( empty( $text ) ) {
			return '';
		}

		// Normalize line endings
		$text = str_replace( [ "\r\n", "\r" ], "\n", $text );

		// Set defaults based on mode
		$defaults = self::get_mode_defaults( $mode );
		$options  = wp_parse_args( $options, $defaults );

		// Route to appropriate converter
		if ( $mode === self::MODE_FRONTEND ) {
			return self::convert_frontend( $text, $options );
		}

		return self::convert_admin( $text, $options );
	}

	/**
	 * Get default options for each mode
	 *
	 * @param string $mode Conversion mode
	 *
	 * @return array Default options
	 */
	private static function get_mode_defaults( $mode ) {
		switch ( $mode ) {
			case self::MODE_FRONTEND:
				return [
					'convert_urls'  => true,
					'header_offset' => 2,      // # → h3, ## → h4
					'use_wpautop'   => false,
					'strikethrough' => false,
				];

			case self::MODE_ADMIN:
			case self::MODE_SIMPLE:
			default:
				return [
					'convert_urls'  => false,
					'header_offset' => 0,      // # → h1, ## → h2
					'use_wpautop'   => true,
					'strikethrough' => true,
				];
		}
	}

	/**
	 * Frontend conversion with placeholder protection and strict escaping
	 *
	 * Used for: Chatbot responses, search results, public-facing content
	 * Security: Uses esc_html() during processing, placeholder protection
	 *
	 * @param string $text    Markdown text
	 * @param array  $options Conversion options
	 *
	 * @return string HTML output
	 */
	private static function convert_frontend( $text, $options ) {
		// Step 1: Extract and protect elements with placeholders
		$placeholders = [];
		$index        = 0;

		// Extract fenced code blocks ```language\ncode\n```
		$text = preg_replace_callback(
			'/```(\w*)\n([\s\S]*?)```/',
			function ( $matches ) use ( &$placeholders, &$index ) {
				$placeholder                   = "\x00CODEBLOCK{$index}\x00";
				$language                      = ! empty( $matches[1] ) ? ' class="language-' . esc_attr( $matches[1] ) . '"' : '';
				$code                          = esc_html( trim( $matches[2] ) );
				$placeholders[ $placeholder ]  = '<pre><code' . $language . '>' . $code . '</code></pre>';
				$index++;
				return $placeholder;
			},
			$text
		);

		// Extract inline code `code`
		$text = preg_replace_callback(
			'/`([^`\n]+)`/',
			function ( $matches ) use ( &$placeholders, &$index ) {
				$placeholder                  = "\x00INLINECODE{$index}\x00";
				$placeholders[ $placeholder ] = '<code>' . esc_html( $matches[1] ) . '</code>';
				$index++;
				return $placeholder;
			},
			$text
		);

		// Extract markdown links [text](url)
		$text = preg_replace_callback(
			'/\[([^\]]+)\]\(([^)]+)\)/',
			function ( $matches ) use ( &$placeholders, &$index ) {
				$placeholder                  = "\x00LINK{$index}\x00";
				$placeholders[ $placeholder ] = '<a href="' . esc_url( $matches[2] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $matches[1] ) . '</a>';
				$index++;
				return $placeholder;
			},
			$text
		);

		// Extract plain URLs if enabled
		if ( ! empty( $options['convert_urls'] ) ) {
			$text = preg_replace_callback(
				'/(?<!["\'])(https?:\/\/[^\s<>\[\]"\']+)/',
				function ( $matches ) use ( &$placeholders, &$index ) {
					$url                          = rtrim( $matches[1], '.,;:!?' );
					$placeholder                  = "\x00PLAINURL{$index}\x00";
					$placeholders[ $placeholder ] = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $url ) . '</a><br class="wpf-ai-br">';
					$index++;
					return $placeholder;
				},
				$text
			);
		}

		// Extract bold **text** or __text__
		$text = preg_replace_callback(
			'/(\*\*|__)(.+?)\1/',
			function ( $matches ) use ( &$placeholders, &$index ) {
				$placeholder                  = "\x00BOLD{$index}\x00";
				$placeholders[ $placeholder ] = '<strong>' . esc_html( $matches[2] ) . '</strong>';
				$index++;
				return $placeholder;
			},
			$text
		);

		// Extract italic *text* or _text_ (but not inside words, not matching HR like ***)
		// Uses [^\*_] to prevent matching horizontal rules or other markers
		$text = preg_replace_callback(
			'/(?<![a-zA-Z0-9\*_])(\*|_)([^\*_\n]+?)\1(?![a-zA-Z0-9\*_])/',
			function ( $matches ) use ( &$placeholders, &$index ) {
				$placeholder                  = "\x00ITALIC{$index}\x00";
				$placeholders[ $placeholder ] = '<em>' . esc_html( $matches[2] ) . '</em>';
				$index++;
				return $placeholder;
			},
			$text
		);

		// Step 2: Process block-level elements
		$text = self::process_blocks_frontend( $text, $options, $placeholders );

		// Step 3: Restore all placeholders (in reverse order to handle nested placeholders)
		foreach ( array_reverse( $placeholders, true ) as $placeholder => $html ) {
			$text = str_replace( $placeholder, $html, $text );
		}

		// Step 4: Convert remaining newlines to <br>
		$text = preg_replace( '/(?<!>)\n(?!<)/', '<br class="wpf-ai-br">' . "\n", $text );

		// Clean up extra line breaks
		$text = preg_replace( '/(<br[^>]*>\s*)+/', '<br class="wpf-ai-br">', $text );
		$text = preg_replace( '/<br[^>]*>\s*(<\/?(ul|ol|li|pre|blockquote|h[1-6]|hr))/', '$1', $text );
		$text = preg_replace( '/(<\/?(ul|ol|li|pre|blockquote|h[1-6]|hr)[^>]*>)\s*<br[^>]*>/', '$1', $text );

		return trim( $text );
	}

	/**
	 * Process block-level elements for frontend mode
	 *
	 * @param string $text         Text to process
	 * @param array  $options      Conversion options
	 * @param array  $placeholders Reference to placeholders array
	 *
	 * @return string Processed text
	 */
	private static function process_blocks_frontend( $text, $options, &$placeholders ) {
		$lines         = explode( "\n", $text );
		$result        = [];
		$in_list       = false;
		$list_type     = '';
		$in_blockquote = false;
		$header_offset = $options['header_offset'] ?? 2;

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );

			// Skip if line is a placeholder (code block)
			if ( preg_match( '/^\x00CODEBLOCK\d+\x00$/', $trimmed ) ) {
				if ( $in_list ) {
					$result[] = $list_type === 'ul' ? '</ul>' : '</ol>';
					$in_list  = false;
				}
				if ( $in_blockquote ) {
					$result[]      = '</blockquote>';
					$in_blockquote = false;
				}
				$result[] = $trimmed;
				continue;
			}

			// Horizontal rule
			if ( preg_match( '/^(-{3,}|\*{3,}|_{3,})$/', $trimmed ) ) {
				if ( $in_list ) {
					$result[] = $list_type === 'ul' ? '</ul>' : '</ol>';
					$in_list  = false;
				}
				if ( $in_blockquote ) {
					$result[]      = '</blockquote>';
					$in_blockquote = false;
				}
				$result[] = '<hr>';
				continue;
			}

			// Headers (# ## ### up to ######)
			if ( preg_match( '/^(#{1,6})\s+(.+)$/', $trimmed, $matches ) ) {
				if ( $in_list ) {
					$result[] = $list_type === 'ul' ? '</ul>' : '</ol>';
					$in_list  = false;
				}
				if ( $in_blockquote ) {
					$result[]      = '</blockquote>';
					$in_blockquote = false;
				}
				$level    = min( strlen( $matches[1] ) + $header_offset, 6 );
				$result[] = '<h' . $level . '>' . esc_html( $matches[2] ) . '</h' . $level . '>';
				continue;
			}

			// Blockquote
			if ( preg_match( '/^>\s*(.*)$/', $trimmed, $matches ) ) {
				if ( $in_list ) {
					$result[] = $list_type === 'ul' ? '</ul>' : '</ol>';
					$in_list  = false;
				}
				if ( ! $in_blockquote ) {
					$result[]      = '<blockquote>';
					$in_blockquote = true;
				}
				$result[] = esc_html( $matches[1] );
				continue;
			} elseif ( $in_blockquote && ! empty( $trimmed ) ) {
				$result[]      = '</blockquote>';
				$in_blockquote = false;
			}

			// Unordered list (- or *)
			if ( preg_match( '/^[-*]\s+(.+)$/', $trimmed, $matches ) ) {
				if ( $in_blockquote ) {
					$result[]      = '</blockquote>';
					$in_blockquote = false;
				}
				if ( ! $in_list || $list_type !== 'ul' ) {
					if ( $in_list ) {
						$result[] = $list_type === 'ul' ? '</ul>' : '</ol>';
					}
					$result[]  = '<ul>';
					$in_list   = true;
					$list_type = 'ul';
				}
				$result[] = '<li>' . esc_html( $matches[1] ) . '</li>';
				continue;
			}

			// Ordered list (1. or 1) format)
			if ( preg_match( '/^\d+[.)]\s+(.+)$/', $trimmed, $matches ) ) {
				if ( $in_blockquote ) {
					$result[]      = '</blockquote>';
					$in_blockquote = false;
				}
				if ( ! $in_list || $list_type !== 'ol' ) {
					if ( $in_list ) {
						$result[] = $list_type === 'ul' ? '</ul>' : '</ol>';
					}
					$result[]  = '<ol>';
					$in_list   = true;
					$list_type = 'ol';
				}
				$result[] = '<li>' . esc_html( $matches[1] ) . '</li>';
				continue;
			}

			// End list if we hit a non-list line
			if ( $in_list && ! empty( $trimmed ) ) {
				$result[] = $list_type === 'ul' ? '</ul>' : '</ol>';
				$in_list  = false;
			}

			// Regular line - escape and add
			if ( ! empty( $trimmed ) ) {
				$result[] = esc_html( $line );
			} elseif ( ! $in_list && ! $in_blockquote ) {
				$result[] = '';
			}
		}

		// Close any open tags
		if ( $in_list ) {
			$result[] = $list_type === 'ul' ? '</ul>' : '</ol>';
		}
		if ( $in_blockquote ) {
			$result[] = '</blockquote>';
		}

		return implode( "\n", $result );
	}

	/**
	 * Admin conversion with simple regex and wp_kses_post
	 *
	 * Used for: Log viewer, admin panels, trusted content
	 * Security: Uses wp_kses_post() at the end for sanitization
	 *
	 * @param string $text    Markdown text
	 * @param array  $options Conversion options
	 *
	 * @return string HTML output
	 */
	private static function convert_admin( $text, $options ) {
		$header_offset = $options['header_offset'] ?? 0;

		// Code blocks FIRST: ```code``` (must be before inline code)
		$text = preg_replace( '/```(\w*)\n?([\s\S]*?)```/', '<pre><code>$2</code></pre>', $text );

		// Inline code: `code` (after code blocks)
		$text = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $text );

		// Headers (h1-h6) with optional offset
		if ( $header_offset === 0 ) {
			$text = preg_replace( '/^######\s+(.+)$/m', '<h6>$1</h6>', $text );
			$text = preg_replace( '/^#####\s+(.+)$/m', '<h5>$1</h5>', $text );
			$text = preg_replace( '/^####\s+(.+)$/m', '<h4>$1</h4>', $text );
			$text = preg_replace( '/^###\s+(.+)$/m', '<h3>$1</h3>', $text );
			$text = preg_replace( '/^##\s+(.+)$/m', '<h2>$1</h2>', $text );
			$text = preg_replace( '/^#\s+(.+)$/m', '<h1>$1</h1>', $text );
		} else {
			// Apply header offset (e.g., # → h3 when offset is 2)
			for ( $i = 6; $i >= 1; $i-- ) {
				$hashes    = str_repeat( '#', $i );
				$new_level = min( $i + $header_offset, 6 );
				$text      = preg_replace( '/^' . $hashes . '\s+(.+)$/m', '<h' . $new_level . '>$1</h' . $new_level . '>', $text );
			}
		}

		// Bold: **text** or __text__
		$text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
		$text = preg_replace( '/__(.+?)__/', '<strong>$1</strong>', $text );

		// Italic: *text* or _text_ (but not inside words)
		$text = preg_replace( '/(?<!\w)\*([^\*]+)\*(?!\w)/', '<em>$1</em>', $text );
		$text = preg_replace( '/(?<!\w)_([^_]+)_(?!\w)/', '<em>$1</em>', $text );

		// Strikethrough: ~~text~~ (admin mode only)
		if ( ! empty( $options['strikethrough'] ) ) {
			$text = preg_replace( '/~~(.+?)~~/', '<del>$1</del>', $text );
		}

		// Links: [text](url)
		$text = preg_replace( '/\[([^\]]+)\]\(([^\)]+)\)/', '<a href="$2" target="_blank">$1</a>', $text );

		// Ordered lists: 1. or 1) item (process BEFORE unordered to wrap correctly)
		$text = preg_replace( '/^\d+[.)]\s+(.+)$/m', '<li>$1</li>', $text );
		$text = preg_replace( '/(<li>.*<\/li>\n?)+/', '<ol>$0</ol>', $text );

		// Unordered lists: - item or * item
		$text = preg_replace( '/^[\-\*]\s+(.+)$/m', '<li>$1</li>', $text );
		$text = preg_replace( '/(<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $text );

		// Blockquotes: > text
		$text = preg_replace( '/^>\s+(.+)$/m', '<blockquote>$1</blockquote>', $text );

		// Horizontal rules: --- or ***
		$text = preg_replace( '/^[\-\*]{3,}$/m', '<hr>', $text );

		// Use wpautop for paragraph handling if enabled
		if ( ! empty( $options['use_wpautop'] ) ) {
			$text = wpautop( $text );
		}

		// Sanitize with wp_kses_post for admin context
		return wp_kses_post( $text );
	}

	/**
	 * Convert citation markers to clickable links
	 *
	 * Handles various citation formats from AI responses:
	 * - [[#123]] - wpForo post reference
	 * - [[#123:Title]] - wpForo with title (title ignored)
	 * - [[#wp_123]] - WordPress post reference
	 * - [[#wp_123:Title]] - WordPress with title
	 * - [[123]] - Missing hash (common LLM mistake)
	 * - [#123] - Missing outer brackets
	 *
	 * @param string $text    Text containing citation markers
	 * @param array  $options Optional settings:
	 *                        - 'format': 'superscript' (default) or 'inline'
	 *                        - 'class': CSS class for links (default: 'wpf-ai-chat-reference')
	 *
	 * @return string Text with citations converted to HTML links
	 */
	public static function convert_citations( $text, $options = [] ) {
		if ( empty( $text ) ) {
			return '';
		}

		$defaults = [
			'format' => 'superscript',
			'class'  => 'wpf-ai-chat-reference',
		];
		$options  = wp_parse_args( $options, $defaults );

		// Step 1: Normalize malformed citations
		// Convert [[123]] to [[#123]]
		$text = preg_replace( '/\[\[(\d+)\]\]/', '[[#$1]]', $text );
		// Convert [#123] to [[#123]]
		$text = preg_replace( '/(?<!\[)\[#(\d+)\](?!\])/', '[[#$1]]', $text );

		// Step 2: Split grouped citations like [[#1360],[#1506]]
		$text = preg_replace( '/\[\[#(\d+)\],\s*\[#(\d+)\]\]/', '[[#$1]][[#$2]]', $text );
		$text = preg_replace( '/\[\[#(\d+)\],\s*\[#(\d+)\],\s*\[#(\d+)\]\]/', '[[#$1]][[#$2]][[#$3]]', $text );

		// Step 3: Convert wpForo citations [[#postid]] or [[#postid:title]]
		$text = preg_replace_callback(
			'/\[\[#(\d+)(?::[^\]]+)?\]\]/',
			function ( $matches ) use ( $options ) {
				$postid = intval( $matches[1] );
				$url    = \WPF()->post->get_url( $postid );

				if ( ! $url ) {
					return $matches[0]; // Return original if URL not found
				}

				if ( $options['format'] === 'superscript' ) {
					return '<sup class="' . esc_attr( $options['class'] ) . '"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">[' . $postid . ']</a></sup>';
				}

				return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" class="' . esc_attr( $options['class'] ) . '">#' . $postid . '</a>';
			},
			$text
		);

		// Step 4: Convert WordPress citations [[#wp_postid]] or [[#wp_postid:title]]
		$text = preg_replace_callback(
			'/\[\[#wp_(\d+)(?::([^\]]+))?\]\]/',
			function ( $matches ) use ( $options ) {
				$postid = intval( $matches[1] );
				$title  = ! empty( $matches[2] ) ? $matches[2] : '';
				$url    = get_permalink( $postid );

				if ( ! $url ) {
					return $matches[0]; // Return original if URL not found
				}

				// Get title from post if not provided
				if ( empty( $title ) ) {
					$post  = get_post( $postid );
					$title = $post ? $post->post_title : "Post #{$postid}";
				}

				if ( $options['format'] === 'superscript' ) {
					return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $title ) . '</a> <sup class="' . esc_attr( $options['class'] ) . '">[wp:' . $postid . ']</sup>';
				}

				return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" class="' . esc_attr( $options['class'] ) . '">' . esc_html( $title ) . '</a>';
			},
			$text
		);

		return $text;
	}

	/**
	 * Replace [NO_FORUM_CONTENT] placeholder with custom message
	 *
	 * @param string $text           Text containing placeholder
	 * @param string $custom_message Custom message to use (or empty for default)
	 * @param array  $replacements   Key-value pairs for placeholder replacement in message
	 *
	 * @return string Text with placeholder replaced
	 */
	public static function replace_no_content_placeholder( $text, $custom_message = '', $replacements = [] ) {
		if ( strpos( $text, '[NO_FORUM_CONTENT]' ) === false ) {
			return $text;
		}

		// Default message
		if ( empty( $custom_message ) ) {
			$custom_message = __( "I couldn't find specific forum content related to your question. Would you like to start a new topic to discuss this?", 'wpforo' );
		}

		// Apply replacements (e.g., {add_topic_url})
		foreach ( $replacements as $key => $value ) {
			$custom_message = str_replace( '{' . $key . '}', $value, $custom_message );
		}

		// Sanitize: allow only safe HTML tags
		$allowed_tags   = '<a><br><p><img><strong><em><ul><ol><li>';
		$custom_message = strip_tags( $custom_message, $allowed_tags );

		return str_replace( '[NO_FORUM_CONTENT]', $custom_message, $text );
	}

	/**
	 * Full AI response formatting pipeline
	 *
	 * Combines all formatting steps in the correct order:
	 * 1. Replace [NO_FORUM_CONTENT] placeholder
	 * 2. Convert markdown to HTML
	 * 3. Convert citation markers to links
	 *
	 * @param string $text              Raw AI response text
	 * @param string $mode              Conversion mode (default: MODE_FRONTEND)
	 * @param array  $options           Options for to_html()
	 * @param array  $citation_options  Options for convert_citations()
	 * @param string $no_content_msg    Custom message for [NO_FORUM_CONTENT]
	 * @param array  $no_content_vars   Replacements for no-content message
	 *
	 * @return string Fully formatted HTML
	 */
	public static function format_ai_response( $text, $mode = self::MODE_FRONTEND, $options = [], $citation_options = [], $no_content_msg = '', $no_content_vars = [] ) {
		if ( empty( $text ) ) {
			return '';
		}

		// Step 1: Replace [NO_FORUM_CONTENT] placeholder
		$text = self::replace_no_content_placeholder( $text, $no_content_msg, $no_content_vars );

		// Step 2: Convert markdown to HTML
		$text = self::to_html( $text, $mode, $options );

		// Step 3: Convert citation markers to links
		$text = self::convert_citations( $text, $citation_options );

		return $text;
	}
}
