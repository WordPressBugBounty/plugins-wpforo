<?php

namespace wpforo\classes;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Local Vector Storage for AI Embeddings
 *
 * Handles storing and searching embeddings in WordPress MySQL database
 * as an alternative to cloud-based vector storage (gVectors AI Services on AWS Cloud).
 *
 * Features:
 * - Binary packed vector storage (efficient BLOB)
 * - Pre-computed magnitudes for fast cosine similarity
 * - Similarity cache with TTL
 * - PHP-based cosine similarity calculation
 *
 * @since 3.0.0
 */
class VectorStorageLocal {

	/**
	 * Default cache TTL in seconds (1 hour)
	 */
	const CACHE_TTL = 3600;

	/**
	 * Maximum similar items to cache per source
	 */
	const MAX_CACHED_SIMILAR = 20;

	/**
	 * Post count threshold for performance warning
	 */
	const PERFORMANCE_THRESHOLD = 5000000;

	/**
	 * Default vector dimensions
	 */
	const DEFAULT_DIMENSIONS = 1024;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Note: Cron registration moved to VectorStorageManager::register_cron_hooks()
		// This ensures the cleanup callback is available when the cron fires
	}

	/**
	 * Store an embedding vector for a post
	 *
	 * @param int    $topicid          Topic ID
	 * @param int    $postid           Post ID (chunk)
	 * @param int    $forumid          Forum ID
	 * @param int    $userid           User ID
	 * @param array  $vector           Float array of embeddings
	 * @param string $content_hash     MD5 hash of content
	 * @param string $content_preview  Content preview (full chunk text)
	 * @param string $model_name       Model used for embedding
	 * @return int|false Insert ID or false on failure
	 */
	public function store_embedding( $topicid, $postid, $forumid, $userid, $vector, $content_hash, $content_preview = '', $model_name = 'amazon.titan-embed-text-v2', $content_type = 'forum' ) {
		global $wpdb;

		if ( empty( $vector ) || ! is_array( $vector ) ) {
			return false;
		}

		$dimensions = count( $vector );
		$magnitude = $this->calculate_magnitude( $vector );

		// Normalize vector for faster similarity computation
		$normalized_vector = $this->normalize_vector( $vector, $magnitude );
		$binary_vector = $this->pack_vector( $normalized_vector );

		// Check if embedding already exists
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM " . WPF()->tables->ai_embeddings . " WHERE postid = %d AND model_name = %s",
			$postid,
			$model_name
		) );

		$data = [
			'topicid'           => $topicid,
			'postid'            => $postid,
			'forumid'           => $forumid,
			'userid'            => $userid,
			'embedding_vector'  => $binary_vector,
			'vector_dimensions' => $dimensions,
			'vector_magnitude'  => 1.0, // Normalized vectors have magnitude 1
			'model_name'        => $model_name,
			'content_hash'      => $content_hash,
			'content_preview'   => $content_preview,
			'content_type'      => $content_type,
		];

		if ( $existing ) {
			// Update existing
			$result = $wpdb->update(
				WPF()->tables->ai_embeddings,
				$data,
				[ 'id' => $existing ],
				[ '%d', '%d', '%d', '%d', '%s', '%d', '%f', '%s', '%s', '%s', '%s' ],
				[ '%d' ]
			);

			// Invalidate cache for this post
			$this->invalidate_cache( 'post', $postid );

			return $existing;
		} else {
			// Insert new
			$result = $wpdb->insert(
				WPF()->tables->ai_embeddings,
				$data,
				[ '%d', '%d', '%d', '%d', '%s', '%d', '%f', '%s', '%s', '%s', '%s' ]
			);

			return $result ? $wpdb->insert_id : false;
		}
	}

	/**
	 * Get embedding for a post
	 *
	 * @param int    $postid     Post ID
	 * @param string $model_name Model name
	 * @return array|null Embedding data or null
	 */
	public function get_embedding( $postid, $model_name = 'amazon.titan-embed-text-v2' ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . WPF()->tables->ai_embeddings . " WHERE postid = %d AND model_name = %s",
			$postid,
			$model_name
		), ARRAY_A );

		if ( ! $row ) {
			return null;
		}

		$row['vector'] = $this->unpack_vector( $row['embedding_vector'] );
		unset( $row['embedding_vector'] );

		return $row;
	}

	/**
	 * Delete embedding for a post
	 *
	 * @param int $postid Post ID
	 * @return bool Success
	 */
	public function delete_embedding( $postid ) {
		global $wpdb;

		$result = $wpdb->delete(
			WPF()->tables->ai_embeddings,
			[ 'postid' => $postid ],
			[ '%d' ]
		);

		// Also delete from cache
		$this->invalidate_cache( 'post', $postid );

		return $result !== false;
	}

	/**
	 * Delete all embeddings for a topic
	 *
	 * @param int $topicid Topic ID
	 * @return int Number of deleted rows
	 */
	public function delete_topic_embeddings( $topicid ) {
		global $wpdb;

		// Get all postids first for cache invalidation
		$postids = $wpdb->get_col( $wpdb->prepare(
			"SELECT postid FROM " . WPF()->tables->ai_embeddings . " WHERE topicid = %d",
			$topicid
		) );

		$result = $wpdb->delete(
			WPF()->tables->ai_embeddings,
			[ 'topicid' => $topicid ],
			[ '%d' ]
		);

		// Invalidate cache for all posts
		foreach ( $postids as $postid ) {
			$this->invalidate_cache( 'post', $postid );
		}

		return $result;
	}

	/**
	 * Delete WordPress CPT embeddings from local storage.
	 *
	 * @param array|null $post_types Post types to delete (e.g., ['docs', 'page']). Null = all non-forum.
	 * @param array|null $post_ids   Specific post IDs to delete.
	 * @return int Number of deleted rows.
	 */
	public function delete_wp_embeddings( $post_types = null, $post_ids = null ) {
		global $wpdb;

		// Guard: ensure tables object and ai_embeddings property exist
		if ( ! WPF()->tables || ! isset( WPF()->tables->ai_embeddings ) ) {
			return 0;
		}

		$table = WPF()->tables->ai_embeddings;

		if ( ! empty( $post_ids ) ) {
			// Delete specific post IDs
			$placeholders = implode( ', ', array_fill( 0, count( $post_ids ), '%d' ) );
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $table WHERE postid IN ($placeholders) AND content_type != 'forum'",
					...$post_ids
				)
			);
		} elseif ( ! empty( $post_types ) ) {
			// Delete by content_type
			$placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $table WHERE content_type IN ($placeholders)",
					...$post_types
				)
			);
		} else {
			// Delete all non-forum embeddings
			$deleted = $wpdb->query(
				"DELETE FROM $table WHERE content_type != 'forum'"
			);
		}

		// Clear the embeddings cache
		$wpdb->query( "DELETE FROM " . WPF()->tables->ai_embeddings_cache );

		return (int) $deleted;
	}

	/**
	 * Semantic search using cosine similarity
	 *
	 * @param array  $query_vector Query embedding vector
	 * @param int    $limit        Maximum results
	 * @param array  $filters      Optional filters: forumid, userid, etc.
	 * @return array Search results with scores
	 */
	public function semantic_search( $query_vector, $limit = 10, $filters = [] ) {
		global $wpdb;

		if ( empty( $query_vector ) ) {
			return [];
		}

		// Extract minimum score threshold from filters (0-1 scale, 0 = no filtering)
		$min_score = isset( $filters['min_score'] ) ? (float) $filters['min_score'] : 0;

		// Normalize query vector
		$query_magnitude = $this->calculate_magnitude( $query_vector );
		$normalized_query = $this->normalize_vector( $query_vector, $query_magnitude );

		// Build WHERE clause for filters
		$where = [];
		$values = [];

		if ( ! empty( $filters['forumid'] ) ) {
			$where[] = 'forumid = %d';
			$values[] = (int) $filters['forumid'];
		}

		if ( ! empty( $filters['forumids'] ) && is_array( $filters['forumids'] ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $filters['forumids'] ), '%d' ) );
			$where[] = "forumid IN ($placeholders)";
			$values = array_merge( $values, array_map( 'intval', $filters['forumids'] ) );
		}

		if ( ! empty( $filters['userid'] ) ) {
			$where[] = 'userid = %d';
			$values[] = (int) $filters['userid'];
		}

		if ( ! empty( $filters['exclude_topicids'] ) && is_array( $filters['exclude_topicids'] ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $filters['exclude_topicids'] ), '%d' ) );
			$where[] = "topicid NOT IN ($placeholders)";
			$values = array_merge( $values, array_map( 'intval', $filters['exclude_topicids'] ) );
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		// Process embeddings in batches to avoid memory exhaustion on large datasets.
		// Each batch loads ~2000 rows (~9MB with 1024-dim vectors), keeping peak memory safe.
		$batch_size  = 2000;
		$offset      = 0;
		$top_results = [];

		$table = WPF()->tables->ai_embeddings;

		while ( true ) {
			$batch_query  = "SELECT id, topicid, postid, forumid, userid, embedding_vector, vector_dimensions, content_preview, content_type
			                 FROM {$table} {$where_sql}
			                 ORDER BY id ASC LIMIT %d OFFSET %d";
			$batch_values = array_merge( $values, [ $batch_size, $offset ] );
			$batch_query  = $wpdb->prepare( $batch_query, $batch_values );

			$rows = $wpdb->get_results( $batch_query, ARRAY_A );

			if ( empty( $rows ) ) {
				break;
			}

			// Calculate similarity for each embedding in this batch
			foreach ( $rows as $row ) {
				$stored_vector = $this->unpack_vector( $row['embedding_vector'] );
				$similarity    = $this->dot_product( $normalized_query, $stored_vector );

				// Skip results below minimum score threshold
				if ( $min_score > 0 && $similarity < $min_score ) {
					continue;
				}

				$top_results[] = [
					'id'              => $row['id'],
					'topicid'         => $row['topicid'],
					'postid'          => $row['postid'],
					'forumid'         => $row['forumid'],
					'userid'          => $row['userid'],
					'similarity'      => $similarity,
					'content_preview' => $row['content_preview'],
					'content_type'    => $row['content_type'] ?? 'forum',
				];
			}

			// Trim accumulated results to top N to bound memory growth
			if ( count( $top_results ) > $limit * 3 ) {
				usort( $top_results, function( $a, $b ) {
					return $b['similarity'] <=> $a['similarity'];
				} );
				$top_results = array_slice( $top_results, 0, $limit );
			}

			// If fewer rows than batch size, we've processed everything
			if ( count( $rows ) < $batch_size ) {
				break;
			}

			$offset += $batch_size;

			// Free batch memory before loading next batch
			unset( $rows );
		}

		if ( empty( $top_results ) ) {
			return [];
		}

		// Final sort by similarity (descending)
		usort( $top_results, function( $a, $b ) {
			return $b['similarity'] <=> $a['similarity'];
		} );

		// Return top N results
		return array_slice( $top_results, 0, $limit );
	}

	/**
	 * Find similar items with caching
	 *
	 * @param string $source_type 'topic' or 'post'
	 * @param int    $source_id   Source item ID
	 * @param int    $limit       Maximum results
	 * @param bool   $force_refresh Force cache refresh
	 * @return array Similar items with scores
	 */
	public function find_similar( $source_type, $source_id, $limit = 10, $force_refresh = false ) {
		// Check cache first
		if ( ! $force_refresh ) {
			$cached = $this->get_cached_similar( $source_type, $source_id, $limit );
			if ( $cached !== null ) {
				return $cached;
			}
		}

		// Get source embedding
		if ( $source_type === 'topic' ) {
			// Get first post's embedding for topic
			global $wpdb;
			$first_postid = $wpdb->get_var( $wpdb->prepare(
				"SELECT postid FROM " . WPF()->tables->ai_embeddings . " WHERE topicid = %d ORDER BY postid ASC LIMIT 1",
				$source_id
			) );
			if ( ! $first_postid ) {
				return [];
			}
			$embedding = $this->get_embedding( $first_postid );
		} else {
			$embedding = $this->get_embedding( $source_id );
		}

		if ( ! $embedding || empty( $embedding['vector'] ) ) {
			return [];
		}

		// Search for similar items
		$filters = [
			'exclude_topicids' => [ $embedding['topicid'] ], // Exclude self
		];

		$results = $this->semantic_search( $embedding['vector'], self::MAX_CACHED_SIMILAR, $filters );

		// Group by topic (forum) or post (CPT) and take best match per group
		$by_group = [];
		foreach ( $results as $result ) {
			$content_type = $result['content_type'] ?? 'forum';
			if ( $content_type !== 'forum' ) {
				// WordPress CPT: group by postid (topicid is 0 for all CPT)
				$group_key = 'wp_' . $result['postid'];
			} else {
				// Forum: group by topicid
				$group_key = 'topic_' . $result['topicid'];
			}
			if ( ! isset( $by_group[ $group_key ] ) || $result['similarity'] > $by_group[ $group_key ]['similarity'] ) {
				$by_group[ $group_key ] = $result;
			}
		}

		// Re-sort and limit
		$similar = array_values( $by_group );
		usort( $similar, function( $a, $b ) {
			return $b['similarity'] <=> $a['similarity'];
		} );
		$similar = array_slice( $similar, 0, self::MAX_CACHED_SIMILAR );

		// Cache results
		$this->cache_similar( $source_type, $source_id, 'topic', $similar );

		return array_slice( $similar, 0, $limit );
	}

	/**
	 * Get cached similar items
	 *
	 * @param string $source_type Source type
	 * @param int    $source_id   Source ID
	 * @param int    $limit       Maximum results
	 * @return array|null Cached results or null if not cached/expired
	 */
	private function get_cached_similar( $source_type, $source_id, $limit ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT similar_id, similarity_score
			 FROM " . WPF()->tables->ai_embeddings_cache . "
			 WHERE source_type = %s AND source_id = %d AND expires_at > %s
			 ORDER BY rank_position ASC
			 LIMIT %d",
			$source_type,
			$source_id,
			$now,
			$limit
		), ARRAY_A );

		if ( empty( $results ) ) {
			return null;
		}

		// Enrich with topic data
		$enriched = [];
		foreach ( $results as $row ) {
			$enriched[] = [
				'topicid'    => (int) $row['similar_id'],
				'similarity' => (float) $row['similarity_score'],
			];
		}

		return $enriched;
	}

	/**
	 * Cache similar items
	 *
	 * @param string $source_type  Source type
	 * @param int    $source_id    Source ID
	 * @param string $similar_type Similar item type
	 * @param array  $similar      Similar items
	 */
	private function cache_similar( $source_type, $source_id, $similar_type, $similar ) {
		global $wpdb;

		// Delete existing cache for this source
		$wpdb->delete(
			WPF()->tables->ai_embeddings_cache,
			[
				'source_type' => $source_type,
				'source_id'   => $source_id,
			],
			[ '%s', '%d' ]
		);

		// Insert new cache entries
		$expires_at = date( 'Y-m-d H:i:s', time() + self::CACHE_TTL );

		foreach ( $similar as $rank => $item ) {
			$wpdb->insert(
				WPF()->tables->ai_embeddings_cache,
				[
					'source_type'      => $source_type,
					'source_id'        => $source_id,
					'similar_type'     => $similar_type,
					'similar_id'       => $item['topicid'],
					'similarity_score' => $item['similarity'],
					'rank_position'    => $rank + 1,
					'expires_at'       => $expires_at,
				],
				[ '%s', '%d', '%s', '%d', '%f', '%d', '%s' ]
			);
		}
	}

	/**
	 * Invalidate cache for an item
	 *
	 * @param string $type Item type
	 * @param int    $id   Item ID
	 */
	public function invalidate_cache( $type, $id ) {
		global $wpdb;

		// Delete where this item is the source
		$wpdb->delete(
			WPF()->tables->ai_embeddings_cache,
			[
				'source_type' => $type,
				'source_id'   => $id,
			],
			[ '%s', '%d' ]
		);

		// Delete where this item is in similar results
		$wpdb->delete(
			WPF()->tables->ai_embeddings_cache,
			[
				'similar_type' => $type,
				'similar_id'   => $id,
			],
			[ '%s', '%d' ]
		);
	}

	/**
	 * Cleanup expired cache entries
	 */
	public function cleanup_expired_cache() {
		global $wpdb;

		$now = current_time( 'mysql' );

		$wpdb->query( $wpdb->prepare(
			"DELETE FROM " . WPF()->tables->ai_embeddings_cache . " WHERE expires_at < %s",
			$now
		) );
	}

	/**
	 * Get embedding statistics
	 *
	 * @return array Statistics
	 */
	public function get_stats() {
		global $wpdb;

		$stats = [
			'total_embeddings' => 0,
			'total_topics'     => 0,
			'total_posts'      => 0,
			'cache_entries'    => 0,
			'storage_size_mb'  => 0,
			'last_indexed_at'  => null,
		];

		// Combined query: total embeddings, unique topics, unique posts, last indexed
		// (4 queries → 1 on the same table)
		$combined = $wpdb->get_row(
			"SELECT COUNT(*) as total_embeddings,
				COUNT(DISTINCT topicid) as total_topics,
				COUNT(DISTINCT postid) as total_posts,
				MAX(updated_at) as last_indexed_at
			FROM " . WPF()->tables->ai_embeddings,
			ARRAY_A
		);
		if ( $combined ) {
			$stats['total_embeddings'] = (int) $combined['total_embeddings'];
			$stats['total_topics']     = (int) $combined['total_topics'];
			$stats['total_posts']      = (int) $combined['total_posts'];
			$stats['last_indexed_at']  = $combined['last_indexed_at'];
		}

		// Cache entries (separate table)
		$stats['cache_entries'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM " . WPF()->tables->ai_embeddings_cache
		);

		// Storage size (approximate)
		$table_name = WPF()->tables->ai_embeddings;
		$table_status = $wpdb->get_row(
			$wpdb->prepare( "SHOW TABLE STATUS WHERE Name = %s", $table_name )
		);
		if ( $table_status ) {
			$stats['storage_size_mb'] = round( ( $table_status->Data_length + $table_status->Index_length ) / 1024 / 1024, 2 );
		}

		return $stats;
	}

	/**
	 * Get counts of indexed WordPress CPT content grouped by post type.
	 *
	 * Queries the ai_embeddings table for non-forum content and returns
	 * counts keyed by wp_{content_type} (e.g., wp_docs, wp_page, wp_post).
	 * This matches the format returned by the cloud API's /rag/wordpress/status.
	 *
	 * @return array<string, int> Post type => count (e.g., ['wp_docs' => 145, 'wp_page' => 20])
	 */
	public function get_wp_indexed_counts() {
		global $wpdb;

		// Guard: ensure tables object and ai_embeddings property exist
		if ( ! WPF()->tables || ! isset( WPF()->tables->ai_embeddings ) ) {
			return [];
		}

		$results = $wpdb->get_results(
			"SELECT content_type, COUNT(*) as cnt
			 FROM " . WPF()->tables->ai_embeddings . "
			 WHERE content_type != 'forum'
			 GROUP BY content_type",
			ARRAY_A
		);

		$counts = [];
		if ( $results ) {
			foreach ( $results as $row ) {
				$counts[ 'wp_' . $row['content_type'] ] = (int) $row['cnt'];
			}
		}

		return $counts;
	}

	/**
	 * Get all indexed WordPress post IDs (non-forum content)
	 *
	 * @return array Array of post IDs that have been indexed
	 */
	public function get_wp_indexed_post_ids() {
		global $wpdb;

		if ( ! WPF()->tables || ! isset( WPF()->tables->ai_embeddings ) ) {
			return [];
		}

		$results = $wpdb->get_col(
			"SELECT DISTINCT postid FROM " . WPF()->tables->ai_embeddings . " WHERE content_type != 'forum'"
		);

		return array_map( 'intval', $results );
	}

	/**
	 * Check if local storage should be recommended based on post count
	 *
	 * @return array Recommendation with status and message
	 */
	public function get_storage_recommendation() {
		$post_count = WPF()->post->get_count();

		if ( $post_count < 10000 ) {
			return [
				'status'  => 'good',
				'message' => wpforo_phrase( 'Excellent choice for your forum size. Local storage will provide fast performance.', false ),
				'icon'    => 'yes-alt',
			];
		} elseif ( $post_count < self::PERFORMANCE_THRESHOLD ) {
			return [
				'status'  => 'good',
				'message' => wpforo_phrase( 'Good choice. Local storage with caching will provide acceptable performance.', false ),
				'icon'    => 'yes',
			];
		} elseif ( $post_count < 100000 ) {
			return [
				'status'  => 'warning',
				'message' => sprintf(
					wpforo_phrase( 'Your forum has %s posts. Local storage may have slower search performance. Consider using cloud storage for better results.', false ),
					number_format( $post_count )
				),
				'icon'    => 'warning',
			];
		} else {
			return [
				'status'  => 'not_recommended',
				'message' => sprintf(
					wpforo_phrase( 'Your forum has %s posts. Cloud storage (gVectors) is recommended for optimal performance.', false ),
					number_format( $post_count )
				),
				'icon'    => 'dismiss',
			];
		}
	}

	// =========================================================================
	// Vector Math Utilities
	// =========================================================================

	/**
	 * Pack float array to binary
	 *
	 * @param array $vector Float array
	 * @return string Binary packed data
	 */
	private function pack_vector( $vector ) {
		return pack( 'f*', ...$vector );
	}

	/**
	 * Unpack binary to float array
	 *
	 * @param string $binary Binary data
	 * @return array Float array
	 */
	private function unpack_vector( $binary ) {
		$floats = unpack( 'f*', $binary );
		return array_values( $floats );
	}

	/**
	 * Calculate vector magnitude
	 *
	 * @param array $vector Float array
	 * @return float Magnitude
	 */
	private function calculate_magnitude( $vector ) {
		$sum = 0;
		foreach ( $vector as $val ) {
			$sum += $val * $val;
		}
		return sqrt( $sum );
	}

	/**
	 * Normalize vector to unit length
	 *
	 * @param array $vector    Float array
	 * @param float $magnitude Pre-computed magnitude (optional)
	 * @return array Normalized vector
	 */
	private function normalize_vector( $vector, $magnitude = null ) {
		if ( $magnitude === null ) {
			$magnitude = $this->calculate_magnitude( $vector );
		}

		if ( $magnitude == 0 ) {
			return $vector;
		}

		return array_map( function( $val ) use ( $magnitude ) {
			return $val / $magnitude;
		}, $vector );
	}

	/**
	 * Calculate dot product of two vectors
	 *
	 * @param array $a Vector A
	 * @param array $b Vector B
	 * @return float Dot product
	 */
	private function dot_product( $a, $b ) {
		$sum = 0;
		$len = min( count( $a ), count( $b ) );

		for ( $i = 0; $i < $len; $i++ ) {
			$sum += $a[ $i ] * $b[ $i ];
		}

		return $sum;
	}

	/**
	 * Calculate cosine similarity between two vectors
	 *
	 * @param array $a Vector A
	 * @param array $b Vector B
	 * @return float Similarity score (0-1)
	 */
	public function cosine_similarity( $a, $b ) {
		$dot = $this->dot_product( $a, $b );
		$mag_a = $this->calculate_magnitude( $a );
		$mag_b = $this->calculate_magnitude( $b );

		if ( $mag_a == 0 || $mag_b == 0 ) {
			return 0;
		}

		return $dot / ( $mag_a * $mag_b );
	}
}
