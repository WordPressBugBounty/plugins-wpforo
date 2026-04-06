<?php
// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

$activities    = wpfval( WPF()->current_object, 'activities' ) ?: [];
$args          = wpfval( WPF()->current_object, 'args' ) ?: [];
$activity_type = wpfval( $args, 'activity_type' ) ?: 'all';
$period        = wpfval( $args, 'period' ) ?: 'week';
$items_count   = wpfval( WPF()->current_object, 'items_count' ) ?: 0;
$paged         = wpfval( WPF()->current_object, 'paged' ) ?: 1;
$items_per_page = 20;
$has_more      = ( $paged * $items_per_page ) < $items_count;
?>
<div class="wpforo-recent-activity-wrap">
	<?php do_action( 'wpforo_activity_list_head' ); ?>
	<div class="wpf-head-bar">
		<div class="wpf-head-top">
			<h1 id="wpforo-title"><?php wpforo_phrase( 'Recent Activity Timeline' ) ?></h1>
		</div>
		<div class="wpf-head-bottom">
			<div class="wpf-head-prefix">
				<form method="get" action="<?php echo esc_url( wpforo_home_url( 'recent-activity' ) ) ?>" class="wpf-recent-activity-filter-form">
					<select name="type" id="wpf-activity-type-filter" onchange="this.form.submit()">
						<option value="all" <?php selected( $activity_type, 'all' ) ?>><?php wpforo_phrase( 'All Activities' ) ?></option>
						<option value="topic" <?php selected( $activity_type, 'topic' ) ?>><?php wpforo_phrase( 'Topics' ) ?></option>
						<option value="reply" <?php selected( $activity_type, 'reply' ) ?>><?php wpforo_phrase( 'Replies' ) ?></option>
						<option value="reaction" <?php selected( $activity_type, 'reaction' ) ?>><?php wpforo_phrase( 'Reactions' ) ?></option>
						<option value="favorite" <?php selected( $activity_type, 'favorite' ) ?>><?php wpforo_phrase( 'Favorites' ) ?></option>
						<option value="solved" <?php selected( $activity_type, 'solved' ) ?>><?php wpforo_phrase( 'Solved Topics' ) ?></option>
						<option value="closed" <?php selected( $activity_type, 'closed' ) ?>><?php wpforo_phrase( 'Closed Topics' ) ?></option>
						<option value="answer" <?php selected( $activity_type, 'answer' ) ?>><?php wpforo_phrase( 'Marked as Answer' ) ?></option>
					</select>
					<select name="period" id="wpf-activity-period-filter" onchange="this.form.submit()">
						<option value="day" <?php selected( $period, 'day' ) ?>><?php wpforo_phrase( 'Last 24 Hours' ) ?></option>
						<option value="week" <?php selected( $period, 'week' ) ?>><?php wpforo_phrase( 'Last Week' ) ?></option>
						<option value="month" <?php selected( $period, 'month' ) ?>><?php wpforo_phrase( 'Last Month' ) ?></option>
						<option value="all" <?php selected( $period, 'all' ) ?>><?php wpforo_phrase( 'All Time' ) ?></option>
					</select>
				</form>
			</div>
		</div>
	</div>

	<div class="wpf-recent-activity-content">
		<?php if( ! empty( $activities ) ) : ?>
			<div class="wpf-timeline" data-page="<?php echo esc_attr( $paged ) ?>" data-type="<?php echo esc_attr( $activity_type ) ?>" data-period="<?php echo esc_attr( $period ) ?>">
				<div class="wpf-timeline-line"></div>
				<?php
				$can_delete = WPF()->usergroup->can( 'aum' );
				foreach( $activities as $index => $activity ) :
					// For non-content actions (reactions, favorites, votes, etc.), the actor is in itemid_second
					$actor_actions = [ 'new_like', 'new_dislike', 'new_up_vote', 'new_down_vote', 'new_reaction', 'new_favorite', 'topic_solved', 'topic_closed', 'post_answer' ];
					$actor_userid  = in_array( $activity['type'], $actor_actions ) && ! empty( $activity['itemid_second'] )
						? $activity['itemid_second']
						: $activity['userid'];
					$member       = wpforo_member( $actor_userid );
					$icon         = wpforo_activity_icon( $activity['type'] );
					$info_html    = wpforo_activity_info_html( $activity, $member );
					$show_excerpt = in_array( $activity['type'], [ 'wpforo_topic', 'wpforo_post' ] );
					$excerpt      = '';
					if( $show_excerpt && $activity['itemid'] ) {
						// For wpforo_topic, itemid is topicid - get first post via topic's first_postid
						if( $activity['type'] === 'wpforo_topic' ) {
							$topic_data = wpforo_topic( $activity['itemid'] );
							if( $topic_data && ! empty( $topic_data['first_postid'] ) ) {
								$first_post = wpforo_post( $topic_data['first_postid'] );
								if( $first_post && ! empty( $first_post['body'] ) ) {
									$excerpt = wpforo_text( $first_post['body'], 300, false );
								}
							}
						} else {
							$post = wpforo_post( $activity['itemid'] );
							if( $post && ! empty( $post['body'] ) ) {
								$excerpt = wpforo_text( $post['body'], 300, false );
							}
						}
					}
					?>
					<div class="wpf-timeline-item wpf-timeline-<?php echo esc_attr( $activity['type'] ) ?>" data-activity-id="<?php echo esc_attr( $activity['id'] ) ?>" style="--delay: <?php echo $index * 0.03 ?>s">
						<!-- Left Side: User Info -->
						<div class="wpf-timeline-left">
							<?php if( WPF()->usergroup->can( 'va' ) && wpforo_setting( 'profiles', 'avatars' ) ) : ?>
								<div class="wpf-timeline-avatar"><?php echo wpforo_user_avatar( $member, 40 ) ?></div>
							<?php endif; ?>
							<div class="wpf-timeline-user"><?php wpforo_member_link( $member ) ?></div>
                            <div class="wpf-timeline-date" style="margin-right: -51px; color: #8a969a;"><?php wpforo_date( $activity['date'], 'ago' ) ?> <span class="wpf-timeline-date-stick">&nbsp</span></div>
						</div>

						<!-- Center: Icon Node -->
						<div class="wpf-timeline-node">
							<div class="wpf-timeline-icon"><?php echo $icon ?></div>
						</div>

						<!-- Right Side: Activity Content -->
						<div class="wpf-timeline-right">
							<div class="wpf-timeline-card">
								<?php if( $can_delete ) : ?>
									<button type="button" class="wpf-activity-delete" title="<?php wpforo_phrase( 'Delete Activity', false ) ?>">
										<i class="fas fa-trash-alt"></i>
									</button>
								<?php endif; ?>
								<!-- Mobile-only header with user and date -->
								<div class="wpf-timeline-card-header">
									<div class="wpf-timeline-date"><?php wpforo_date( $activity['date'], 'ago' ) ?></div>
									<div class="wpf-timeline-user"><?php wpforo_member_link( $member ) ?></div>
								</div>
								<div class="wpf-timeline-activity"><?php echo $info_html ?></div>
								<?php if( $show_excerpt && $excerpt ) : ?>
									<div class="wpf-timeline-excerpt"><?php echo esc_html( $excerpt ) ?></div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if( $has_more ) : ?>
				<div class="wpf-timeline-load-more-wrap">
					<button type="button" class="wpf-timeline-load-more wpf-button wpf-button-primary" data-loading="<?php wpforo_phrase( 'Loading...', false ) ?>">
						<span class="wpf-load-more-text"><?php wpforo_phrase( 'Load More Activities' ) ?></span>
						<span class="wpf-load-more-spinner"></span>
					</button>
				</div>
			<?php endif; ?>

		<?php else : ?>
			<div class="wpf-timeline-empty">
				<div class="wpf-timeline-empty-icon"><i class="far fa-clock"></i></div>
				<p><?php wpforo_phrase( 'No activities found' ) ?></p>
			</div>
		<?php endif; ?>
	</div>

	<?php do_action( 'wpforo_activity_list_footer' ); ?>
</div>

<script>
(function() {
	document.addEventListener('DOMContentLoaded', function() {
		// Intersection Observer for fade-in animations
		const observer = new IntersectionObserver((entries) => {
			entries.forEach(entry => {
				if (entry.isIntersecting) {
					entry.target.classList.add('wpf-timeline-visible');
					observer.unobserve(entry.target);
				}
			});
		}, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

		document.querySelectorAll('.wpf-timeline-item').forEach(item => {
			observer.observe(item);
		});

		// Load More functionality
		const loadMoreBtn = document.querySelector('.wpf-timeline-load-more');
		if (loadMoreBtn) {
			loadMoreBtn.addEventListener('click', function() {
				const timeline = document.querySelector('.wpf-timeline');
				const btn = this;
				const btnText = btn.querySelector('.wpf-load-more-text');
				const originalText = btnText.textContent;

				if (btn.classList.contains('wpf-loading')) return;

				btn.classList.add('wpf-loading');
				btnText.textContent = btn.dataset.loading;

				const currentPage = parseInt(timeline.dataset.page) || 1;
				const nextPage = currentPage + 1;
				const type = timeline.dataset.type || 'all';
				const period = timeline.dataset.period || 'week';

				const formData = new FormData();
				formData.append('action', 'wpforo_load_more_activities');
				formData.append('page', nextPage);
				formData.append('type', type);
				formData.append('period', period);
				formData.append('_wpfnonce', (typeof wpforo !== 'undefined' && wpforo.nonces ? wpforo.nonces.wpforo_load_more_activities : ''));

				fetch((typeof wpforo !== 'undefined' ? wpforo.ajax_url : '/wp-admin/admin-ajax.php'), {
					method: 'POST',
					body: formData
				})
				.then(response => response.json())
				.then(data => {
					btn.classList.remove('wpf-loading');
					btnText.textContent = originalText;

					if (data.success && data.data.html) {
						// Append new items at the end of the timeline
						timeline.insertAdjacentHTML('beforeend', data.data.html);
						timeline.dataset.page = nextPage;

						// Observe new items
						timeline.querySelectorAll('.wpf-timeline-item:not(.wpf-timeline-visible)').forEach(item => {
							observer.observe(item);
						});

						if (!data.data.has_more) {
							btn.parentElement.remove();
						}
					}
				})
				.catch(error => {
					btn.classList.remove('wpf-loading');
					btnText.textContent = originalText;
					console.error('Load more error:', error);
				});
			});
		}

		// Delete activity functionality
		document.addEventListener('click', function(e) {
			const deleteBtn = e.target.closest('.wpf-activity-delete');
			if (!deleteBtn) return;

			e.preventDefault();
			e.stopPropagation();

			const timelineItem = deleteBtn.closest('.wpf-timeline-item');
			if (!timelineItem) return;

			const activityId = timelineItem.dataset.activityId;
			if (!activityId) return;

			if (!confirm('<?php echo esc_js( wpforo_phrase( 'Are you sure you want to delete this activity?', false ) ) ?>')) {
				return;
			}

			deleteBtn.classList.add('wpf-deleting');

			const formData = new FormData();
			formData.append('action', 'wpforo_delete_activity');
			formData.append('activity_id', activityId);
			formData.append('_wpfnonce', (typeof wpforo !== 'undefined' && wpforo.nonces ? wpforo.nonces.wpforo_delete_activity : ''));

			fetch((typeof wpforo !== 'undefined' ? wpforo.ajax_url : '/wp-admin/admin-ajax.php'), {
				method: 'POST',
				body: formData
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					timelineItem.style.opacity = '0';
					timelineItem.style.transform = 'translateX(100px)';
					setTimeout(() => {
						timelineItem.remove();
					}, 300);
				} else {
					deleteBtn.classList.remove('wpf-deleting');
					alert(data.data && data.data.message ? data.data.message : '<?php echo esc_js( wpforo_phrase( 'Failed to delete activity', false ) ) ?>');
				}
			})
			.catch(error => {
				deleteBtn.classList.remove('wpf-deleting');
				console.error('Delete error:', error);
			});
		});
	});
})();
</script>
