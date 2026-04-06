<?php

namespace wpforo\classes;

/**
 * WordPress Content Type Indexer
 *
 * Handles AI content indexing for WordPress posts, pages, and custom post types.
 * Uses the existing RAG infrastructure via /rag/wordpress/* API endpoints.
 *
 * Content Types:
 * - Posts, Pages, and any public custom post types
 * - Excludes internal types (revisions, nav_menu_item, etc.)
 *
 * @since 3.0.0
 */
class AIWordPressIndexer {

	/**
	 * Internal/structural post types to skip
	 * These should never be indexed as they're not user-facing content.
	 *
	 * @var array
	 */
	private $skip_post_types = [
		// WordPress core internal
		'attachment',
		'revision',
		'nav_menu_item',
		'wp_block',
		'wp_template',
		'wp_template_part',
		'wp_navigation',
		'wp_font_family',
		'wp_font_face',
		'oembed_cache',
		'user_request',
		'wp_global_styles',
		'custom_css',
		'customize_changeset',

		// ACF internal
		'acf-field-group',
		'acf-field',
		'acf-post-type',
		'acf-taxonomy',

		// wpForo posts (indexed separately via forum indexing)
		'wpforo_post',
	];

	/**
	 * WooCommerce post types to skip (temporary exclusion)
	 * TODO: Remove this exclusion when WooCommerce support is implemented
	 *
	 * @var array
	 */
	private $skip_post_types_woocommerce = [
		'product',              // Main products
		'product_variation',    // Product variations
		'shop_order',           // Orders (legacy, pre-HPOS)
		'shop_order_refund',    // Order refunds
		'shop_coupon',          // Coupons
		'shop_order_placehold', // Order placeholders
		'shop_webhook',         // Webhooks
	];

	/**
	 * Internal/structural taxonomies to skip
	 *
	 * @var array
	 */
	private $skip_taxonomies = [
		// WordPress core internal
		'nav_menu',
		'link_category',
		'post_format',
		'wp_theme',
		'wp_template_part_area',
		'wp_pattern_category',
	];

	/**
	 * WooCommerce taxonomies to skip (temporary exclusion)
	 * TODO: Remove this exclusion when WooCommerce support is implemented
	 *
	 * @var array
	 */
	private $skip_taxonomies_woocommerce = [
		'product_cat',            // Product categories
		'product_tag',            // Product tags
		'product_type',           // Product types (simple, variable, etc.)
		'product_visibility',     // Product visibility
		'product_shipping_class', // Shipping classes
		// Note: pa_* (product attributes) are handled dynamically in get_taxonomies_for_post_type()
	];

	/**
	 * Batch size for indexing
	 *
	 * @var int
	 */
	const BATCH_SIZE = 50;

	/**
	 * Minimum content length after stripping HTML and shortcodes
	 *
	 * @var int
	 */
	const MIN_CONTENT_LENGTH = 10;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Register admin-only AJAX handlers
		if ( is_admin() ) {
			add_action( 'wp_ajax_wpforo_ai_wp_get_post_types', [ $this, 'ajax_get_post_types' ] );
			add_action( 'wp_ajax_wpforo_ai_wp_get_taxonomies', [ $this, 'ajax_get_taxonomies' ] );
			add_action( 'wp_ajax_wpforo_ai_wp_get_taxonomy_terms', [ $this, 'ajax_get_taxonomy_terms' ] );
			add_action( 'wp_ajax_wpforo_ai_wp_get_indexing_status', [ $this, 'ajax_get_indexing_status' ] );
			add_action( 'wp_ajax_wpforo_ai_wp_index_by_taxonomy', [ $this, 'ajax_index_by_taxonomy' ] );
			add_action( 'wp_ajax_wpforo_ai_wp_index_custom', [ $this, 'ajax_index_custom' ] );
			add_action( 'wp_ajax_wpforo_ai_wp_delete_content', [ $this, 'ajax_delete_content' ] );
		}

		// Register WP Cron handler for batch processing
		// IMPORTANT: Must be registered unconditionally (not only in admin context)
		// because WP Cron runs in a separate request where is_admin() returns FALSE
		add_action( 'wpforo_ai_process_wp_batch', [ $this, 'process_batch' ] );
	}

	/**
	 * Get public post types available for indexing
	 *
	 * @return array Array of post type objects with name, label, and count
	 */
	public function get_public_post_types() {
		$post_types = get_post_types(
			[
				'public'             => true,
				'publicly_queryable' => true,
			],
			'objects'
		);

		// Also include 'page' which has publicly_queryable = false by default
		$page_type = get_post_type_object( 'page' );
		if ( $page_type ) {
			$post_types['page'] = $page_type;
		}

		// Also include 'post' which may have different settings
		$post_type = get_post_type_object( 'post' );
		if ( $post_type ) {
			$post_types['post'] = $post_type;
		}

		// Combine skip lists
		$all_skip_types = array_merge( $this->skip_post_types, $this->skip_post_types_woocommerce );

		$result = [];
		foreach ( $post_types as $name => $type ) {
			// Skip internal/structural types and WooCommerce types
			if ( in_array( $name, $all_skip_types, true ) ) {
				continue;
			}

			// Count indexable published posts (for display accuracy)
			$indexable_count = $this->count_indexable_posts( $name );

			// Include ALL public post types, regardless of current indexable count
			// Content quality filtering happens at indexing time, not at type listing
			$result[] = [
				'name'  => $name,
				'label' => $type->labels->name,
				'count' => $indexable_count, // Shows indexable posts (may be 0)
			];
		}

		return $result;
	}

	/**
	 * Count indexable published posts for a post type
	 *
	 * Only counts posts with meaningful textual content.
	 * Results are cached for 5 minutes to improve performance.
	 *
	 * @param string $post_type Post type name
	 * @return int Number of indexable posts
	 */
	private function count_indexable_posts( $post_type ) {
		// Check transient cache first
		$cache_key = 'wpforo_ai_indexable_count_' . sanitize_key( $post_type );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		$query = new \WP_Query( [
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'fields'         => 'ids', // Only fetch IDs first for efficiency
		] );

		// If no posts, return 0
		if ( empty( $query->posts ) ) {
			set_transient( $cache_key, 0, 5 * MINUTE_IN_SECONDS );
			return 0;
		}

		// For small result sets, check all posts
		// For large sets, sample to estimate (check first 500)
		$post_ids        = $query->posts;
		$total_posts     = count( $post_ids );
		$sample_size     = min( 500, $total_posts );
		$indexable_count = 0;

		// Check sample of posts
		for ( $i = 0; $i < $sample_size; $i++ ) {
			$post = get_post( $post_ids[ $i ] );
			if ( $post && $this->is_content_indexable( $post ) ) {
				$indexable_count++;
			}
		}

		// If we sampled, extrapolate the count
		if ( $sample_size < $total_posts ) {
			$ratio           = $indexable_count / $sample_size;
			$indexable_count = (int) round( $total_posts * $ratio );
		}

		set_transient( $cache_key, $indexable_count, 5 * MINUTE_IN_SECONDS );

		return $indexable_count;
	}

	/**
	 * Get public taxonomies for a post type
	 *
	 * @param string $post_type Post type name
	 * @return array Array of taxonomy objects
	 */
	public function get_taxonomies_for_post_type( $post_type ) {
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );

		// Combine skip lists
		$all_skip_taxonomies = array_merge( $this->skip_taxonomies, $this->skip_taxonomies_woocommerce );

		$result = [];
		foreach ( $taxonomies as $name => $taxonomy ) {
			// Only include public taxonomies
			if ( ! $taxonomy->public ) {
				continue;
			}

			// Skip internal/structural taxonomies and WooCommerce taxonomies
			if ( in_array( $name, $all_skip_taxonomies, true ) ) {
				continue;
			}

			// Skip WooCommerce product attributes (pa_* prefix)
			// TODO: Remove this when WooCommerce support is implemented
			if ( strpos( $name, 'pa_' ) === 0 ) {
				continue;
			}

			// Get term count (show all terms, not just those with posts)
			$count = wp_count_terms( [ 'taxonomy' => $name, 'hide_empty' => false ] );

			$result[] = [
				'name'       => $name,
				'label'      => $taxonomy->labels->name,
				'term_count' => is_wp_error( $count ) ? 0 : (int) $count,
			];
		}

		return $result;
	}

	/**
	 * Get terms for a taxonomy
	 *
	 * @param string $taxonomy Taxonomy name
	 * @param bool   $hierarchical Whether to return hierarchical structure
	 * @param array  $post_types Post types to count (defaults to all public types using this taxonomy)
	 * @return array Array of terms
	 */
	public function get_taxonomy_terms( $taxonomy, $hierarchical = true, $post_types = [] ) {
		$args = [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false, // Show all terms, not just those with posts
			'orderby'    => 'name',
			'order'      => 'ASC',
		];

		if ( $hierarchical && is_taxonomy_hierarchical( $taxonomy ) ) {
			$args['parent'] = 0; // Get top-level terms first
		}

		$terms = get_terms( $args );

		if ( is_wp_error( $terms ) ) {
			return [];
		}

		// Get post types that use this taxonomy if not specified
		if ( empty( $post_types ) ) {
			$tax_obj = get_taxonomy( $taxonomy );
			$post_types = $tax_obj ? $tax_obj->object_type : [ 'post' ];
		}

		$result = [];
		foreach ( $terms as $term ) {
			// Count only published posts for this term
			$published_count = $this->count_published_posts_in_term( $term->term_id, $taxonomy, $post_types );

			$term_data = [
				'term_id' => $term->term_id,
				'name'    => $term->name,
				'slug'    => $term->slug,
				'count'   => $published_count, // Only published posts
				'indexed' => 0, // Placeholder - actual indexed count from AI backend
			];

			// Get children for hierarchical taxonomies
			if ( $hierarchical && is_taxonomy_hierarchical( $taxonomy ) ) {
				$children = get_terms( [
					'taxonomy'   => $taxonomy,
					'hide_empty' => false, // Show all terms, not just those with posts
					'parent'     => $term->term_id,
					'orderby'    => 'name',
					'order'      => 'ASC',
				] );

				if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
					$term_data['children'] = [];
					foreach ( $children as $child ) {
						// Count only published posts for child term
						$child_published_count = $this->count_published_posts_in_term( $child->term_id, $taxonomy, $post_types );

						$term_data['children'][] = [
							'term_id' => $child->term_id,
							'name'    => $child->name,
							'slug'    => $child->slug,
							'count'   => $child_published_count, // Only published posts
							'indexed' => 0, // Placeholder - actual indexed count from AI backend
						];
					}
				}
			}

			$result[] = $term_data;
		}

		return $result;
	}

	/**
	 * Count published and indexable posts in a specific term
	 *
	 * Only counts posts that have meaningful textual content (not just shortcodes,
	 * not binary data, and at least MIN_CONTENT_LENGTH characters).
	 * Results are cached for 5 minutes to improve performance.
	 *
	 * @param int    $term_id Term ID
	 * @param string $taxonomy Taxonomy name
	 * @param array  $post_types Post types to count
	 * @return int Number of indexable published posts
	 */
	private function count_published_posts_in_term( $term_id, $taxonomy, $post_types = [ 'post' ] ) {
		// Check transient cache first
		$cache_key = 'wpforo_ai_term_count_' . $term_id . '_' . sanitize_key( $taxonomy );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		$query = new \WP_Query( [
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'tax_query'      => [
				[
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $term_id,
				],
			],
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'fields'                 => 'ids', // Only fetch IDs first
		] );

		// If no posts, return 0
		if ( empty( $query->posts ) ) {
			set_transient( $cache_key, 0, 5 * MINUTE_IN_SECONDS );
			return 0;
		}

		$post_ids        = $query->posts;
		$total_posts     = count( $post_ids );
		$sample_size     = min( 200, $total_posts ); // Smaller sample for term counts
		$indexable_count = 0;

		for ( $i = 0; $i < $sample_size; $i++ ) {
			$post = get_post( $post_ids[ $i ] );
			if ( $post && $this->is_content_indexable( $post ) ) {
				$indexable_count++;
			}
		}

		// Extrapolate if sampled
		if ( $sample_size < $total_posts ) {
			$ratio           = $indexable_count / $sample_size;
			$indexable_count = (int) round( $total_posts * $ratio );
		}

		set_transient( $cache_key, $indexable_count, 5 * MINUTE_IN_SECONDS );

		return $indexable_count;
	}

	/**
	 * Get WordPress posts for indexing
	 *
	 * @param array $args Query arguments
	 * @return array Array of formatted post data
	 */
	public function get_posts_for_indexing( $args = [] ) {
		$defaults = [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => self::BATCH_SIZE,
			'paged'          => 1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		];

		$args = wp_parse_args( $args, $defaults );

		// Ensure we only get published posts
		$args['post_status'] = 'publish';

		$query = new \WP_Query( $args );
		$posts = [];

		foreach ( $query->posts as $post ) {
			$posts[] = $this->format_post_for_indexing( $post );
		}

		return [
			'posts'       => $posts,
			'total'       => $query->found_posts,
			'total_pages' => $query->max_num_pages,
			'current'     => $args['paged'],
		];
	}

	/**
	 * Format a WordPress post for indexing
	 *
	 * @param \WP_Post $post WordPress post object
	 * @return array Formatted post data for API
	 */
	public function format_post_for_indexing( $post ) {
		// Get taxonomy terms
		$taxonomies = get_object_taxonomies( $post->post_type, 'names' );
		$taxonomy_terms = [];

		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_post_terms( $post->ID, $taxonomy, [ 'fields' => 'names' ] );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$tax_obj = get_taxonomy( $taxonomy );
				$tax_label = $tax_obj ? $tax_obj->labels->singular_name : $taxonomy;
				$taxonomy_terms[ $tax_label ] = $terms;
			}
		}

		// Get important post meta (configurable)
		$post_meta = $this->get_indexable_post_meta( $post );

		return [
			'post_id'        => $post->ID,
			'post_type'      => $post->post_type,
			'title'          => $post->post_title,
			'content'        => $post->post_content,
			'excerpt'        => $post->post_excerpt,
			'author_id'      => (int) $post->post_author,
			'post_status'    => $post->post_status,
			'permalink'      => get_permalink( $post->ID ),
			'created_at'     => $post->post_date_gmt,
			'updated_at'     => $post->post_modified_gmt,
			'taxonomy_terms' => $taxonomy_terms,
			'post_meta'      => $post_meta,
		];
	}

	/**
	 * Get indexable post meta
	 *
	 * @param \WP_Post $post WordPress post object
	 * @return array Filtered post meta
	 */
	private function get_indexable_post_meta( $post ) {
		$meta = [];

		// WooCommerce product meta
		if ( $post->post_type === 'product' ) {
			$meta['_price'] = get_post_meta( $post->ID, '_price', true );
			$meta['_sku'] = get_post_meta( $post->ID, '_sku', true );
			$meta['_stock_status'] = get_post_meta( $post->ID, '_stock_status', true );
		}

		// Allow plugins to add custom meta
		$meta = apply_filters( 'wpforo_ai_indexable_post_meta', $meta, $post );

		// Remove empty values
		return array_filter( $meta, function( $v ) {
			return $v !== '' && $v !== null;
		} );
	}

	/**
	 * Check if post content is suitable for indexing
	 *
	 * Validates that content is:
	 * - Not just shortcodes
	 * - Has meaningful text (>= MIN_CONTENT_LENGTH chars after stripping)
	 * - Is textual (not binary/garbage data)
	 *
	 * @param \WP_Post|int $post Post object or ID
	 * @return bool True if content is indexable
	 */
	public function is_content_indexable( $post ) {
		if ( is_numeric( $post ) ) {
			$post = get_post( $post );
		}

		if ( ! $post || ! isset( $post->post_content ) ) {
			return false;
		}

		$content = $post->post_content;

		// Check for binary/non-textual content
		// Binary data often contains null bytes or high ratio of non-printable characters
		if ( $this->is_binary_content( $content ) ) {
			return false;
		}

		// Strip shortcodes first (e.g., [gallery], [contact-form-7 id="123"])
		$content = strip_shortcodes( $content );

		// Strip all HTML tags
		$content = wp_strip_all_tags( $content );

		// Decode HTML entities
		$content = html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );

		// Normalize whitespace
		$content = preg_replace( '/\s+/', ' ', $content );
		$content = trim( $content );

		// Check minimum length
		$length = mb_strlen( $content, 'UTF-8' );

		return $length >= self::MIN_CONTENT_LENGTH;
	}

	/**
	 * Check if content appears to be binary/non-textual data
	 *
	 * @param string $content Content to check
	 * @return bool True if content appears to be binary
	 */
	private function is_binary_content( $content ) {
		if ( empty( $content ) ) {
			return false;
		}

		// Check for null bytes (common in binary data)
		if ( strpos( $content, "\0" ) !== false ) {
			return true;
		}

		// Sample the content (check first 1000 bytes for performance)
		$sample = substr( $content, 0, 1000 );
		$sample_length = strlen( $sample );

		if ( $sample_length === 0 ) {
			return false;
		}

		// Count non-printable characters (excluding common whitespace)
		$non_printable = 0;
		for ( $i = 0; $i < $sample_length; $i++ ) {
			$ord = ord( $sample[ $i ] );
			// Allow: tab (9), newline (10), carriage return (13), space and above (32-126)
			// Allow extended ASCII/UTF-8 (128+)
			if ( $ord < 9 || ( $ord > 13 && $ord < 32 ) || ( $ord > 126 && $ord < 128 ) ) {
				$non_printable++;
			}
		}

		// If more than 10% is non-printable, likely binary
		$ratio = $non_printable / $sample_length;

		return $ratio > 0.1;
	}

	/**
	 * Get the clean text content for a post (for display/counting purposes)
	 *
	 * @param \WP_Post|int $post Post object or ID
	 * @return string Clean text content
	 */
	public function get_clean_content( $post ) {
		if ( is_numeric( $post ) ) {
			$post = get_post( $post );
		}

		if ( ! $post || ! isset( $post->post_content ) ) {
			return '';
		}

		$content = $post->post_content;
		$content = strip_shortcodes( $content );
		$content = wp_strip_all_tags( $content );
		$content = html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );
		$content = preg_replace( '/\s+/', ' ', $content );

		return trim( $content );
	}

	/**
	 * Index posts by taxonomy term(s)
	 *
	 * @param string    $taxonomy Taxonomy name
	 * @param int|array $term_ids Term ID or array of term IDs
	 * @param array     $post_types Post types to index
	 * @param string    $date_from Optional start date (Y-m-d format)
	 * @param string    $date_to Optional end date (Y-m-d format)
	 * @return array|WP_Error Result or error
	 */
	public function index_by_taxonomy( $taxonomy, $term_ids, $post_types = [ 'post' ], $date_from = '', $date_to = '' ) {
		if ( ! WPF()->ai_client || ! WPF()->ai_client->is_service_available() ) {
			return new \WP_Error( 'not_connected', __( 'AI service is not connected', 'wpforo' ) );
		}

		// Ensure term_ids is an array
		$term_ids = (array) $term_ids;
		$term_ids = array_map( 'intval', $term_ids );
		$term_ids = array_filter( $term_ids ); // Remove zeros

		if ( empty( $term_ids ) ) {
			return new \WP_Error( 'no_terms', __( 'No valid terms specified', 'wpforo' ) );
		}

		// Get all posts in these terms
		$args = [
			'post_type'      => $post_types,
			'posts_per_page' => -1, // Get all
			'post_status'    => 'publish',
			'tax_query'      => [
				[
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $term_ids,
				],
			],
			'fields' => 'ids',
		];

		// Add date range filter if specified
		if ( ! empty( $date_from ) ) {
			$args['date_query'][] = [
				'after'     => $date_from,
				'inclusive' => true,
			];
		}

		if ( ! empty( $date_to ) ) {
			$args['date_query'][] = [
				'before'    => $date_to,
				'inclusive' => true,
			];
		}

		$query = new \WP_Query( $args );
		$post_ids = $query->posts;

		if ( empty( $post_ids ) ) {
			return new \WP_Error( 'no_posts', __( 'No posts found in this term', 'wpforo' ) );
		}

		// Queue posts for batch indexing
		return $this->queue_posts_for_indexing( $post_ids );
	}

	/**
	 * Index posts with custom filters
	 *
	 * @param array $params Custom indexing parameters
	 * @return array|WP_Error Result or error
	 */
	public function index_custom( $params ) {
		if ( ! WPF()->ai_client || ! WPF()->ai_client->is_service_available() ) {
			return new \WP_Error( 'not_connected', __( 'AI service is not connected', 'wpforo' ) );
		}

		$args = [
			'post_type'      => isset( $params['post_types'] ) ? $params['post_types'] : [ 'post' ],
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		];

		// Date range filter
		if ( ! empty( $params['date_from'] ) ) {
			$args['date_query'][] = [
				'after'     => $params['date_from'],
				'inclusive' => true,
			];
		}

		if ( ! empty( $params['date_to'] ) ) {
			$args['date_query'][] = [
				'before'    => $params['date_to'],
				'inclusive' => true,
			];
		}

		// Specific post IDs
		if ( ! empty( $params['post_ids'] ) ) {
			$args['post__in'] = array_map( 'intval', (array) $params['post_ids'] );
		}

		// Author filter
		if ( ! empty( $params['author'] ) ) {
			$args['author'] = intval( $params['author'] );
		}

		$query = new \WP_Query( $args );
		$post_ids = $query->posts;

		if ( empty( $post_ids ) ) {
			return new \WP_Error( 'no_posts', __( 'No posts found matching criteria', 'wpforo' ) );
		}

		return $this->queue_posts_for_indexing( $post_ids );
	}

	/**
	 * Queue posts for batch indexing
	 *
	 * @param array $post_ids Array of post IDs to index
	 * @return array Result with job info
	 */
	public function queue_posts_for_indexing( $post_ids ) {
		$batches = array_chunk( $post_ids, self::BATCH_SIZE );
		$job_id = 'wp_index_' . uniqid();
		$total_posts = count( $post_ids );

		// Clear the status cache so polling gets fresh data
		delete_transient( 'wpforo_ai_wp_indexing_status' );

		// Store queue in options for processing
		update_option( 'wpforo_ai_wp_indexing_queue', [
			'job_id'      => $job_id,
			'batches'     => $batches,
			'current'     => 0,
			'total_posts' => $total_posts,
			'indexed'     => 0,
			'failed'      => 0,
			'skipped'     => 0,
			'status'      => 'processing',
			'started_at'  => current_time( 'mysql', true ),
		] );

		// Schedule first batch
		wp_schedule_single_event( time() + 1, 'wpforo_ai_process_wp_batch' );

		return [
			'job_id'      => $job_id,
			'total_posts' => $total_posts,
			'batches'     => count( $batches ),
			'status'      => 'queued',
		];
	}

	/**
	 * Process a batch of posts for indexing
	 *
	 * @return array|WP_Error Result or error
	 */
	public function process_batch() {
		$queue = get_option( 'wpforo_ai_wp_indexing_queue' );

		if ( empty( $queue ) || empty( $queue['batches'] ) ) {
			return new \WP_Error( 'no_queue', 'No indexing queue found' );
		}

		$current_batch_index = $queue['current'];

		if ( ! isset( $queue['batches'][ $current_batch_index ] ) ) {
			// All batches processed
			delete_option( 'wpforo_ai_wp_indexing_queue' );
			return [ 'status' => 'completed' ];
		}

		$post_ids = $queue['batches'][ $current_batch_index ];
		$posts = [];
		$skipped = 0;

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( $post && $post->post_status === 'publish' ) {
				// Skip posts with non-indexable content (shortcodes only, binary, too short)
				if ( ! $this->is_content_indexable( $post ) ) {
					$skipped++;
					continue;
				}
				$posts[] = $this->format_post_for_indexing( $post );
			}
		}

		// Track skipped posts
		if ( ! isset( $queue['skipped'] ) ) {
			$queue['skipped'] = 0;
		}
		$queue['skipped'] += $skipped;

		if ( empty( $posts ) ) {
			// Move to next batch
			$queue['current']++;
			update_option( 'wpforo_ai_wp_indexing_queue', $queue );

			// Schedule next batch
			if ( isset( $queue['batches'][ $queue['current'] ] ) ) {
				wp_schedule_single_event( time() + 2, 'wpforo_ai_process_wp_batch' );
			}

			return [ 'status' => 'batch_empty', 'current' => $queue['current'] ];
		}

		// Check storage mode — local mode stores embeddings in WordPress DB
		$storage_manager = WPF()->vector_storage;
		if ( $storage_manager && $storage_manager->is_local_mode() ) {
			$local_result = $this->process_batch_local( $posts, $queue );

			return $local_result;
		}

		// CLOUD MODE: Send to cloud API
		$response = WPF()->ai_client->api_post( '/rag/wordpress/ingest', [
			'posts'           => $posts,
			'chunk_size'      => 512,
			'overlap_percent' => 20,
		], 120 );

		if ( is_wp_error( $response ) ) {
			$queue['failed'] += count( $post_ids );
		} else {
			$queue['indexed'] += count( $posts );
		}

		// Move to next batch
		$queue['current']++;
		update_option( 'wpforo_ai_wp_indexing_queue', $queue );

		// Schedule next batch
		if ( isset( $queue['batches'][ $queue['current'] ] ) ) {
			wp_schedule_single_event( time() + 2, 'wpforo_ai_process_wp_batch' );
		} else {
			// All done
			$queue['status'] = 'completed';
			$queue['completed_at'] = current_time( 'mysql', true );
			update_option( 'wpforo_ai_wp_indexing_queue', $queue );

			// Clear cache
			delete_transient( 'wpforo_ai_wp_indexing_status' );
		}

		return [
			'status'  => 'processing',
			'current' => $queue['current'],
			'indexed' => $queue['indexed'],
			'failed'  => $queue['failed'],
			'skipped' => $queue['skipped'],
		];
	}

	/**
	 * Process a batch of posts for local storage mode
	 *
	 * Generates embeddings via cloud API but stores them in WordPress DB
	 * instead of cloud vector storage. Uses content_hash dedup to skip
	 * unchanged posts.
	 *
	 * @param array $posts   Formatted post data from format_post_for_indexing()
	 * @param array $queue   Current queue state (modified by reference via option update)
	 * @return array Result with status, indexed, failed, skipped counts
	 */
	private function process_batch_local( $posts, $queue ) {
		$storage_manager = WPF()->vector_storage;
		$local           = $storage_manager->get_local_storage();
		$indexed         = 0;
		$failed          = 0;
		$skipped         = 0;

		foreach ( $posts as $post_data ) {
			$post_id   = $post_data['post_id'];
			$post_type = $post_data['post_type'];

			// Build text content for embedding
			$content_parts = [];
			if ( ! empty( $post_data['title'] ) ) {
				$content_parts[] = $post_data['title'];
			}
			if ( ! empty( $post_data['excerpt'] ) ) {
				$content_parts[] = wp_strip_all_tags( $post_data['excerpt'] );
			}
			if ( ! empty( $post_data['content'] ) ) {
				$clean_content = strip_shortcodes( $post_data['content'] );
				$clean_content = wp_strip_all_tags( $clean_content );
				$clean_content = html_entity_decode( $clean_content, ENT_QUOTES, 'UTF-8' );
				$clean_content = preg_replace( '/\s+/', ' ', trim( $clean_content ) );
				$content_parts[] = $clean_content;
			}

			// Add taxonomy context
			if ( ! empty( $post_data['taxonomy_terms'] ) ) {
				foreach ( $post_data['taxonomy_terms'] as $tax_label => $terms ) {
					$content_parts[] = $tax_label . ': ' . implode( ', ', $terms );
				}
			}

			$content      = implode( "\n\n", $content_parts );
			$content_hash = md5( $content );

			// Check if already indexed with same content (dedup)
			$existing = $local->get_embedding( $post_id );
			if ( $existing && $existing['content_hash'] === $content_hash ) {
				$skipped++;
				continue;
			}

			// Generate embedding via cloud API
			$embedding = $storage_manager->generate_embedding( $content );

			if ( is_wp_error( $embedding ) ) {
				\wpforo_ai_log( 'error', sprintf(
					'Failed to generate embedding for WP post %d: %s',
					$post_id,
					$embedding->get_error_message()
				), 'WPIndexer' );
				$failed++;
				continue;
			}

			// Build content preview
			$preview = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post_data['content'] ?? '' ) ), 80, '...' );

			// Store locally with content_type = post_type
			$stored = $local->store_embedding(
				0,                         // topicid (not a forum topic)
				$post_id,                  // postid = WP post ID
				0,                         // forumid (not a forum)
				(int) $post_data['author_id'],
				$embedding,
				$content_hash,
				$preview,
				'amazon.titan-embed-text-v2',
				$post_type                 // content_type = post type (page, post, product, etc.)
			);

			if ( $stored ) {
				$indexed++;
			} else {
				$failed++;
			}
		}

		// Update queue progress
		$queue['indexed'] += $indexed;
		$queue['failed']  += $failed;
		$queue['skipped']  = ( $queue['skipped'] ?? 0 ) + $skipped;

		// Move to next batch
		$queue['current']++;
		update_option( 'wpforo_ai_wp_indexing_queue', $queue );

		// Schedule next batch
		if ( isset( $queue['batches'][ $queue['current'] ] ) ) {
			wp_schedule_single_event( time() + 2, 'wpforo_ai_process_wp_batch' );
		} else {
			// All done
			$queue['status']       = 'completed';
			$queue['completed_at'] = current_time( 'mysql', true );
			update_option( 'wpforo_ai_wp_indexing_queue', $queue );
			delete_transient( 'wpforo_ai_wp_indexing_status' );
		}

		return [
			'status'  => 'processing',
			'current' => $queue['current'],
			'indexed' => $queue['indexed'],
			'failed'  => $queue['failed'],
			'skipped' => $queue['skipped'] ?? 0,
		];
	}

	/**
	 * Get WordPress content indexing status
	 *
	 * @return array|WP_Error Status data or error
	 */
	public function get_indexing_status( $skip_cache = false ) {
		if ( ! WPF()->ai_client || ! WPF()->ai_client->is_service_available() ) {
			return new \WP_Error( 'not_connected', __( 'AI service is not connected', 'wpforo' ) );
		}

		// Check cache (skip if explicitly requested or if indexing is in progress)
		$queue = get_option( 'wpforo_ai_wp_indexing_queue' );
		$is_processing = ! empty( $queue ) && isset( $queue['status'] ) && $queue['status'] === 'processing';

		if ( ! $skip_cache && ! $is_processing ) {
			$cached = get_transient( 'wpforo_ai_wp_indexing_status' );
			if ( $cached !== false ) {
				return $cached;
			}
		}

		// Get indexed counts — source depends on storage mode
		$storage_manager = WPF()->vector_storage;
		if ( $storage_manager && $storage_manager->is_local_mode() ) {
			// LOCAL mode: count from WordPress ai_embeddings table
			$local = $storage_manager->get_local_storage();
			$indexed_counts = $local->get_wp_indexed_counts();
			$response = [
				'content_source' => 'wordpress',
				'indexed_counts' => $indexed_counts,
				'total_indexed'  => array_sum( $indexed_counts ),
			];
		} else {
			// CLOUD mode: query backend API (has sync_state records)
			$response = WPF()->ai_client->api_get( '/rag/wordpress/status' );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
		}

		// Build by_type structure that JavaScript expects
		$post_types = $this->get_public_post_types();
		$indexed_counts = isset( $response['indexed_counts'] ) ? $response['indexed_counts'] : [];
		$by_type = [];

		foreach ( $post_types as $type ) {
			$type_key = 'wp_' . $type['name'];
			$indexed = isset( $indexed_counts[ $type_key ] ) ? (int) $indexed_counts[ $type_key ] : 0;
			$total = (int) $type['count'];
			$by_type[ $type_key ] = [
				'indexed'    => $indexed,
				'total'      => $total,
				'percentage' => $total > 0 ? round( ( $indexed / $total ) * 100, 1 ) : 0,
			];
		}

		$response['by_type'] = $by_type;
		$response['total_indexed'] = isset( $response['total_indexed'] ) ? (int) $response['total_indexed'] : 0;

		// Check if there's an active queue
		$queue = get_option( 'wpforo_ai_wp_indexing_queue' );
		if ( ! empty( $queue ) ) {
			$response['queue'] = [
				'job_id'      => $queue['job_id'],
				'total_posts' => $queue['total_posts'],
				'indexed'     => $queue['indexed'],
				'failed'      => $queue['failed'],
				'current'     => $queue['current'],
				'total'       => count( $queue['batches'] ),
				'status'      => isset( $queue['status'] ) ? $queue['status'] : 'processing',
			];
		}

		// Cache for 5 minutes
		set_transient( 'wpforo_ai_wp_indexing_status', $response, 5 * MINUTE_IN_SECONDS );

		return $response;
	}

	/**
	 * Delete WordPress content from index
	 *
	 * @param array $params Delete parameters (post_types, post_ids, all)
	 * @return array|WP_Error Result or error
	 */
	public function delete_content( $params ) {
		$storage_manager = WPF()->vector_storage;

		if ( $storage_manager && $storage_manager->is_local_mode() ) {
			// LOCAL mode: delete from WordPress ai_embeddings table
			$local      = $storage_manager->get_local_storage();
			$post_types = isset( $params['post_types'] ) ? $params['post_types'] : null;
			$post_ids   = isset( $params['post_ids'] ) ? $params['post_ids'] : null;

			// 'all' flag means delete all non-forum CPT embeddings
			if ( ! empty( $params['all'] ) ) {
				$post_types = null;
				$post_ids   = null;
			}

			$deleted = $local->delete_wp_embeddings( $post_types, $post_ids );

			// Clear cache
			delete_transient( 'wpforo_ai_wp_indexing_status' );

			return [
				'deleted' => $deleted,
				'message' => sprintf( 'Deleted %d embeddings from local storage.', $deleted ),
			];
		}

		// CLOUD mode: delete via backend API
		if ( ! WPF()->ai_client || ! WPF()->ai_client->is_service_available() ) {
			return new \WP_Error( 'not_connected', __( 'AI service is not connected', 'wpforo' ) );
		}

		$response = WPF()->ai_client->api_post( '/rag/wordpress/delete', $params, 60 );

		if ( ! is_wp_error( $response ) ) {
			// Clear cache
			delete_transient( 'wpforo_ai_wp_indexing_status' );
		}

		return $response;
	}

	// ===============================
	// AJAX Handlers
	// ===============================

	/**
	 * AJAX: Get public post types
	 */
	public function ajax_get_post_types() {
		check_ajax_referer( 'wpforo_admin_ajax', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'wpforo' ) ] );
		}

		$post_types = $this->get_public_post_types();
		wp_send_json_success( [ 'post_types' => $post_types ] );
	}

	/**
	 * AJAX: Get taxonomies for post type
	 */
	public function ajax_get_taxonomies() {
		check_ajax_referer( 'wpforo_admin_ajax', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'wpforo' ) ] );
		}

		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : 'post';
		$taxonomies = $this->get_taxonomies_for_post_type( $post_type );

		wp_send_json_success( [ 'taxonomies' => $taxonomies ] );
	}

	/**
	 * AJAX: Get terms for taxonomy
	 */
	public function ajax_get_taxonomy_terms() {
		check_ajax_referer( 'wpforo_admin_ajax', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'wpforo' ) ] );
		}

		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( $_POST['taxonomy'] ) : 'category';

		// Get post types if provided (to count only published posts for specific types)
		$post_types = [];
		if ( ! empty( $_POST['post_types'] ) ) {
			$post_types = array_map( 'sanitize_key', (array) $_POST['post_types'] );
		}

		$terms = $this->get_taxonomy_terms( $taxonomy, true, $post_types );

		wp_send_json_success( [ 'terms' => $terms ] );
	}

	/**
	 * AJAX: Get indexing status
	 */
	public function ajax_get_indexing_status() {
		check_ajax_referer( 'wpforo_admin_ajax', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'wpforo' ) ] );
		}

		$status = $this->get_indexing_status();

		if ( is_wp_error( $status ) ) {
			wp_send_json_error( [ 'message' => $status->get_error_message() ] );
		}

		wp_send_json_success( $status );
	}

	/**
	 * AJAX: Index by taxonomy
	 */
	public function ajax_index_by_taxonomy() {
		check_ajax_referer( 'wpforo_admin_ajax', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'wpforo' ) ] );
		}

		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( $_POST['taxonomy'] ) : '';
		$post_types = isset( $_POST['post_types'] ) ? array_map( 'sanitize_key', (array) $_POST['post_types'] ) : [ 'post' ];
		$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( $_POST['date_from'] ) : '';
		$date_to = isset( $_POST['date_to'] ) ? sanitize_text_field( $_POST['date_to'] ) : '';

		// Support both single term_id (legacy) and multiple term_ids
		$term_ids = [];
		if ( isset( $_POST['term_ids'] ) && is_array( $_POST['term_ids'] ) ) {
			$term_ids = array_map( 'intval', $_POST['term_ids'] );
		} elseif ( isset( $_POST['term_id'] ) ) {
			$term_ids = [ intval( $_POST['term_id'] ) ];
		}

		if ( empty( $taxonomy ) || empty( $term_ids ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid taxonomy or term', 'wpforo' ) ] );
		}

		$result = $this->index_by_taxonomy( $taxonomy, $term_ids, $post_types, $date_from, $date_to );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Custom indexing
	 */
	public function ajax_index_custom() {
		check_ajax_referer( 'wpforo_admin_ajax', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'wpforo' ) ] );
		}

		$params = [];

		if ( ! empty( $_POST['post_types'] ) ) {
			$params['post_types'] = array_map( 'sanitize_key', (array) $_POST['post_types'] );
		}

		if ( ! empty( $_POST['date_from'] ) ) {
			$params['date_from'] = sanitize_text_field( $_POST['date_from'] );
		}

		if ( ! empty( $_POST['date_to'] ) ) {
			$params['date_to'] = sanitize_text_field( $_POST['date_to'] );
		}

		if ( ! empty( $_POST['post_ids'] ) ) {
			$params['post_ids'] = array_map( 'intval', (array) $_POST['post_ids'] );
		}

		if ( ! empty( $_POST['author'] ) ) {
			$params['author'] = intval( $_POST['author'] );
		}

		$result = $this->index_custom( $params );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Delete content
	 */
	public function ajax_delete_content() {
		check_ajax_referer( 'wpforo_admin_ajax', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'wpforo' ) ] );
		}

		$params = [];

		if ( isset( $_POST['delete_all'] ) && $_POST['delete_all'] === 'true' ) {
			$params['all'] = true;  // API expects 'all', not 'delete_all'
		} elseif ( ! empty( $_POST['post_types'] ) ) {
			$params['post_types'] = array_map( 'sanitize_key', (array) $_POST['post_types'] );
		} elseif ( ! empty( $_POST['post_ids'] ) ) {
			$params['post_ids'] = array_map( 'intval', (array) $_POST['post_ids'] );
		}

		$result = $this->delete_content( $params );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( $result );
	}
}
