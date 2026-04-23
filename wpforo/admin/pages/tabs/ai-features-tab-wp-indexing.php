<?php
/**
 * AI Features - WordPress Content Indexing Tab
 *
 * Completely isolated tab for WordPress post/page/CPT indexing.
 * Separate from forum content indexing in AI Content Indexing tab.
 *
 * @package wpForo
 * @subpackage Admin
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render WordPress Content Indexing tab content
 *
 * @param bool  $is_connected Whether tenant is connected to AI service
 * @param array $status       Tenant status data from API
 */
function wpforo_ai_render_wp_indexing_tab( $is_connected, $status ) {
	if ( ! $is_connected ) {
		?>
		<div class="wpforo-ai-box wpforo-ai-not-connected-notice">
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-status-badge status-warning">
					<span class="dashicons dashicons-warning"></span>
					<?php _e( 'Not Connected', 'wpforo' ); ?>
				</div>
				<p><?php _e( 'Please connect to wpForo AI API first in the Overview tab to enable WordPress Content Indexing.', 'wpforo' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpforo-ai&tab=overview' ) ); ?>" class="button button-primary">
					<?php _e( 'Go to Overview', 'wpforo' ); ?>
				</a>
			</div>
		</div>
		<?php
		return;
	}

	// Feature gate check - Business+ plan required
	$wpf_ai_wp_indexing_available = isset( WPF()->ai_client ) && WPF()->ai_client->is_feature_available( 'wordpress_content_indexing' );
	if ( ! $wpf_ai_wp_indexing_available ) {
		?>
		<div class="wpforo-ai-box wpforo-ai-upgrade-notice">
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-status-badge status-warning">
					<span class="dashicons dashicons-lock"></span>
					<?php _e( 'Business Plan Required', 'wpforo' ); ?>
				</div>
				<p><?php _e( 'WordPress Content Indexing is available on Business and Enterprise plans. Upgrade to index WordPress posts, pages, and custom post types for AI-powered search.', 'wpforo' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpforo-ai&tab=overview' ) ); ?>" class="button button-primary">
					<?php _e( 'View Plans', 'wpforo' ); ?>
				</a>
			</div>
		</div>
		<?php
		return;
	}

	// Get storage mode from main board (WordPress content is global, not board-specific)
	$storage_manager = WPF()->vector_storage->for_board( 0 );
	$storage_mode = $storage_manager->get_storage_mode();

	// Get WordPress auto-indexing option (global, not board-specific)
	$wp_auto_indexing_enabled = (bool) get_option( 'wpforo_ai_wp_auto_indexing_enabled', 0 );

	// Get WordPress image indexing option
	$wp_image_indexing_enabled = (bool) get_option( 'wpforo_ai_wp_image_indexing_enabled', 0 );

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

	// Get initial indexed counts for page load
	$wp_initial_status  = null;
	$wp_initial_indexed = 0;
	$wp_is_indexing     = false;
	$wp_last_activity   = null;
	if ( isset( WPF()->ai_wp_indexer ) ) {
		$wp_initial_status = WPF()->ai_wp_indexer->get_indexing_status( true );
		if ( ! is_wp_error( $wp_initial_status ) ) {
			$wp_initial_indexed = (int) ( $wp_initial_status['total_indexed'] ?? 0 );
			$wp_is_indexing     = (bool) ( $wp_initial_status['is_indexing'] ?? false );
			$wp_last_activity   = $wp_initial_status['last_indexed_at'] ?? null;
		}
	}

	// Get remaining credits from subscription data
	$remaining_credits = 0;
	if ( isset( $status['subscription']['credits_remaining'] ) ) {
		$remaining_credits = (int) $status['subscription']['credits_remaining'];
	}
	?>
	<div class="wpforo-ai-wp-indexing-tab">

		<!-- Indexing Status Box -->
		<div id="wp-indexing-status-box" class="wpforo-ai-box wpforo-ai-wp-status-box">
			<div class="wpforo-ai-box-header">
				<h2>
					<span class="dashicons dashicons-chart-bar"></span>
					<?php _e( 'Indexing Status', 'wpforo' ); ?>
				</h2>
				<div class="wpforo-ai-header-actions">
					<button type="button" class="button button-small wpforo-ai-wp-refresh-status" data-loading-text="<?php esc_attr_e( 'Refreshing...', 'wpforo' ); ?>">
						<span class="dashicons dashicons-update"></span>
						<?php _e( 'Refresh Status', 'wpforo' ); ?>
					</button>
				</div>
			</div>
			<div class="wpforo-ai-box-body">
				<div class="wpforo-ai-wp-status-grid">
					<div class="wpforo-ai-wp-status-item">
						<div class="status-label"><?php _e( 'Storage Mode', 'wpforo' ); ?></div>
						<div class="status-value">
							<?php if ( $storage_mode === 'cloud' ) : ?>
								<span class="dashicons dashicons-cloud"></span>
								<?php _e( 'Cloud (AWS S3 Vectors)', 'wpforo' ); ?>
							<?php else : ?>
								<span class="dashicons dashicons-database"></span>
								<?php _e( 'Local (WordPress Database)', 'wpforo' ); ?>
							<?php endif; ?>
						</div>
						<div class="status-action">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpforo-ai&tab=rag_indexing' ) ); ?>">
								<?php _e( 'Change in AI Content Indexing', 'wpforo' ); ?> &rarr;
							</a>
						</div>
					</div>

					<div class="wpforo-ai-wp-status-item wpforo-ai-wp-stat-large">
						<div class="status-label"><?php _e( 'Current Status', 'wpforo' ); ?></div>
						<div class="status-value <?php echo $wp_is_indexing ? 'status-active' : 'status-idle'; ?>" id="wp-indexing-status">
							<?php if ( $wp_is_indexing ) : ?>
								<span class="dashicons dashicons-update-alt wpforo-wp-indexing-spin"></span>
								<?php _e( 'Indexing...', 'wpforo' ); ?>
							<?php else : ?>
								<span class="dashicons dashicons-yes-alt"></span>
								<?php _e( 'Idle', 'wpforo' ); ?>
							<?php endif; ?>
						</div>
					</div>

					<div class="wpforo-ai-wp-status-item wpforo-ai-wp-stat-large">
						<div class="status-label"><?php _e( 'Last Activity', 'wpforo' ); ?></div>
						<div class="status-value" id="wp-last-activity">
							<?php
							if ( $wp_last_activity ) {
								echo esc_html( wpforo_ai_format_date( $wp_last_activity ) );
							} else {
								_e( 'No activity yet', 'wpforo' );
							}
							?>
						</div>
					</div>

					<div class="wpforo-ai-wp-status-item wpforo-ai-wp-stat-large">
						<div class="status-label"><?php _e( 'Remaining Credits', 'wpforo' ); ?></div>
						<div class="status-value" id="wp-remaining-credits">
							<?php echo number_format( $remaining_credits ); ?>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- WordPress Content Indexing Box -->
		<div class="wpforo-ai-box wpforo-ai-wordpress-indexing-box">
			<div class="wpforo-ai-box-header">
				<h2>
					<span class="dashicons dashicons-wordpress-alt"></span>
					<?php _e( 'WordPress Content Indexing', 'wpforo' ); ?>
				</h2>
				<div class="wpforo-ai-header-actions">
					<!-- Image indexing temporarily disabled - feature not yet implemented -->
					<label class="wpforo-ai-auto-index-toggle" style="display:none;" title="<?php esc_attr_e( 'Include images in indexing (Business/Enterprise). Posts with images will consume +1 credit.', 'wpforo' ); ?>">
						<span class="wpforo-ai-auto-index-label">
							<?php _e( 'Index Images', 'wpforo' ); ?>
						</span>
						<div class="wpforo-ai-switch">
							<input type="checkbox" id="wpforo-ai-wp-image-indexing" name="ai_wp_image_indexing_enabled" value="1" <?php checked( $wp_image_indexing_enabled ); ?> data-option-name="ai_wp_image_indexing_enabled">
							<span class="wpforo-ai-switch-slider"></span>
						</div>
					</label>
					<!-- Auto-indexing temporarily disabled - will be improved in future release -->
					<label class="wpforo-ai-auto-index-toggle" style="display:none;" title="<?php esc_attr_e( 'Automatically index new and updated WordPress content', 'wpforo' ); ?>">
						<span class="wpforo-ai-auto-index-label"><?php _e( 'Automatically index new content', 'wpforo' ); ?></span>
						<div class="wpforo-ai-switch">
							<input type="checkbox" id="wpforo-ai-wp-auto-indexing" name="ai_wp_auto_indexing_enabled" value="1" <?php checked( $wp_auto_indexing_enabled ); ?> data-option-name="ai_wp_auto_indexing_enabled">
							<span class="wpforo-ai-switch-slider"></span>
						</div>
					</label>
				</div>
			</div>
			<div class="wpforo-ai-box-body">
				<p class="wpforo-ai-description">
					<?php _e( 'Index WordPress posts, pages, and custom post types to enable AI-powered search across all your site content. This works alongside forum content indexing.', 'wpforo' ); ?>
				</p>

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
							<span class="dashicons dashicons-chart-pie"></span>
						</div>
						<div class="stat-info">
							<?php
							$wp_percentage = $wp_total_content > 0 ? round( ( $wp_initial_indexed / $wp_total_content ) * 100, 1 ) : 0;
							?>
							<div class="stat-value" id="wp-indexed-percentage"><?php echo $wp_percentage; ?>%</div>
							<div class="stat-label"><?php _e( 'Coverage', 'wpforo' ); ?></div>
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
										<button type="button" class="button button-small wpforo-ai-wp-select-all-terms">
											<?php _e( 'Select All', 'wpforo' ); ?>
										</button>
										<button type="button" class="button button-small wpforo-ai-wp-deselect-all-terms">
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
									<button type="submit" class="button button-large  button-secondary wpforo-ai-wp-index-ids">
										<span class="dashicons dashicons-upload"></span>
										<?php _e( 'Index by IDs', 'wpforo' ); ?>
									</button>
								</div>
							</form>
						</div>
					</div>

					<!-- Quick Actions -->
					<div class="wpforo-ai-wp-quick-actions">
						<button type="button" class="button wpforo-ai-wp-cleanup-session" data-confirm="<?php esc_attr_e( 'Reset stuck WordPress indexing session? This clears the pending post queue, cron jobs and cached status. Already-indexed posts are NOT affected.', 'wpforo' ); ?>" title="<?php esc_attr_e( 'Clears pending WordPress post/page indexing queue, WP-Cron jobs and status cache. Use this if WordPress indexing is stuck on "Indexing..." but nothing is progressing. Works for both local and cloud storage modes. Does not delete any indexed data.', 'wpforo' ); ?>">
							<span class="dashicons dashicons-update-alt"></span>
							<?php _e( 'Cleanup Indexing Session', 'wpforo' ); ?>
						</button>
						<button type="button" class="button button-link-delete wpforo-ai-wp-clear-index" data-confirm="<?php esc_attr_e( 'This will remove all WordPress content from the AI index. Continue?', 'wpforo' ); ?>">
							<span class="dashicons dashicons-trash"></span>
							<?php _e( 'Clear WordPress Index', 'wpforo' ); ?>
						</button>
					</div>
				<?php endif; ?>
			</div>
		</div>

	</div>
	<?php
}
