<?php
// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

/**
 *
 * @layout: Simplified
 * @url: http://gvectors.com/
 * @version: 1.0.0
 * @author: gVectors Team
 * @description: Simplified layout looks simple and clean.
 *
 */

$cover_styles = wpforo_get_forum_cover_styles( $cat );
?>

<div class="wpfl-2 wpforo-section">
    <div class="wpforo-category" <?php echo $cover_styles['cover'] ?>>
        <div class="wpforo-cat-panel" <?php echo $cover_styles['blur'] ?>>
            <div class="cat-title" title="<?php echo esc_attr( strip_tags( $cat['description'] ) ); ?>">
                <span class="cat-name" <?php echo $cover_styles['title'] ?>><?php echo esc_html( $cat['title'] ); ?></span>
            </div>
			<?php if( WPF()->current_object['template'] === 'forum' ) wpforo_template_add_topic_button( $cat['forumid'] ); ?>
        </div>
    </div><!-- wpforo-category -->
	
	<?php if( WPF()->current_object['template'] === 'forum' ) wpforo_template_topic_portable_form( $cat['forumid'] ); ?>
	
	<?php
	$forum_list = false;
	foreach( $forums as $key => $forum ) :
		if( ! WPF()->perm->forum_can( 'vf', $forum['forumid'] ) ) continue;
		$forum_list = true;
		
		if( ! empty( $forum['icon'] ) ) {
			$forum['icon'] = trim( (string) $forum['icon'] );
			if( strpos( (string) $forum['icon'], ' ' ) === false ) $forum['icon'] = 'fas ' . $forum['icon'];
		}
		$forum_icon = ( isset( $forum['icon'] ) && $forum['icon'] ) ? $forum['icon'] : 'fas fa-comments';
		
		$sum_of_topics_posts = wpforo_get_sum_of_topics_posts( $forum['forumid'] );
		?>

        <div id="wpf-forum-<?php echo $forum['forumid'] ?>" class="forum-wrap <?php wpforo_unread( $forum['forumid'], 'forum' ) ?>">
            <div class="wpforo-forum">
                <div class="wpforo-forum-icon">
					<div style="border: 2px solid <?php echo esc_attr( $forum['color'] ) ?>; border-radius: 50%; width: 44px; height: 44px; display: flex; justify-content: center; align-items: center;">
						<i class="<?php echo esc_attr( $forum_icon ) ?>" style="color: <?php echo esc_attr( $forum['color'] ) ?>;"></i>
					</div>	
				</div>
                <div class="wpforo-forum-info">
                    <h3 class="wpforo-forum-title"><a href="<?php echo esc_url( (string) wpforo_forum( $forum['forumid'], 'url' ) ) ?>"><?php echo esc_html(
								$forum['title']
							); ?></a> <?php wpforo_viewing( $forum ); ?></h3>
                    <div class="wpforo-forum-description"><?php echo wp_kses_post( $forum['description'] ) ?></div>
					<?php $sub_forums = WPF()->forum->get_forums( [ "parentid" => $forum['forumid'], "type" => 'forum' ] ); ?>
					<?php if( is_array( $sub_forums ) && ! empty( $sub_forums ) ) : ?>

                        <div class="wpforo-subforum">
                            <ul>
                                <li class="first" style="margin-top:1px;"><?php wpforo_phrase( 'Subforums' ); ?>:</li>
								
								<?php foreach( $sub_forums as $sub_forum ) :
									if( ! WPF()->perm->forum_can( 'vf', $sub_forum['forumid'] ) ) continue;
									if( ! empty( $sub_forum['icon'] ) ) {
										$sub_forum['icon'] = trim( (string) $sub_forum['icon'] );
										if( strpos( (string) $sub_forum['icon'], ' ' ) === false ) $sub_forum['icon'] = 'fas ' . $sub_forum['icon'];
									}
									$sub_forum_icon = ( isset( $sub_forum['icon'] ) && $sub_forum['icon'] ) ? $sub_forum['icon'] : 'fas fa-comments'; ?>

                                    <li class="<?php wpforo_unread( $sub_forum['forumid'], 'forum' ) ?>">
										<a href="<?php echo esc_url( (string) wpforo_forum( $sub_forum['forumid'], 'url' ) ) ?>" style="border-bottom: 2px solid <?php echo esc_attr( $sub_forum['color'] ) ?>; vertical-align: middle; text-decoration: none; padding-left: 1px;"><?php echo esc_html( $sub_forum['title'] ); ?>&nbsp;&nbsp;</a> <?php wpforo_viewing( $sub_forum ); ?>
									</li>
								
								<?php endforeach; ?>

                            </ul>
                            <br class="wpf-clear"/>
                        </div><!-- wpforo-subforum -->
					
					<?php endif; ?>

                </div><!-- wpforo-forum-info -->

                <div class="wpforo-forum-data">
					<?php if( apply_filters( 'wpforo_layout2_compact_data', true ) ) : ?>
						<div class="wpf-compact-view">
							<div class="wpf-compact-left">
								<div class="wpf-compact-row"><?php wpforo_phrase( 'Topics' ) ?>: <?php echo wpforo_print_number( $sum_of_topics_posts['topics'] ) ?></div>
								<div class="wpf-compact-row"><?php wpforo_phrase( 'Posts' ) ?>: <?php echo wpforo_print_number( $sum_of_topics_posts['posts'] ) ?></div>
							</div>
							<div class="wpf-compact-last-post">
								<?php if( $last_post = wpforo_post( $forum['last_postid'] ) ) : ?>
									<?php $last_post_topic = wpforo_topic( $last_post['topicid'] ) ?>
									<?php $member = wpforo_member( $last_post ) ?>
									<?php if( WPF()->usergroup->can( 'va' ) && wpforo_setting( 'profiles', 'avatars' ) ): ?>
										<div class="wpf-cl-avatar">
											<?php wpforo_member_link( $member, '', 96, '', true, 'avatar' ) ?>
										</div>
									<?php endif; ?>
									<div class="wpf-cl-content">
										<p class="wpf-cl-title">
											<?php wpforo_topic_title( $last_post_topic, $last_post['url'], '{p}{au}{tc}{/a}{n}', true, '', 45 ) ?>
										</p>
										<p class="wpf-cl-author"><?php wpforo_member_link( $member, 'by' ); ?><span class="wpforo-date wpforo-date-ago">, <?php wpforo_date( $forum['last_post_date'] ) ?></span></p>
									</div>
								<?php else: ?>
									<div class="wpf-cl-content">
										<p class="wpf-cl-title"><?php wpforo_phrase( 'Forum is empty' ); ?></p>
									</div>
								<?php endif ?>
							</div>
						</div>
					<?php else : ?>
						<?php $show_last_post = apply_filters( 'wpforo_layout2_always_show_last_post', false ); ?>
						<div class="wpforo-forum-details<?php if( $show_last_post ) echo ' wpf-always-last-post'; ?>">
							<div class="wpf-stat-box">
								<div class="wpf-sbl"><?php wpforo_phrase( 'Topics' ) ?></div>
								<div class="wpf-sbd"><?php echo wpforo_print_number( $sum_of_topics_posts['topics'] ) ?></div>
							</div>
							<div class="wpf-stat-box">
								<div class="wpf-sbl"><?php wpforo_phrase( 'Posts' ); ?></div>
								<div class="wpf-sbd"><?php echo wpforo_print_number( $sum_of_topics_posts['posts'] ) ?></div>
							</div>
							<div class="wpf-stat-box">
								<div class="wpf-sbl"><?php wpforo_phrase( 'Members' ); ?></div>
								<div class="wpf-sbd wpf-sbd-avatar"><?php wpforo_l2_forum_users( $forum ) ?></div>
							</div>
							<div class="wpforo-last-post-info">
								<?php if( $last_post = wpforo_post( $forum['last_postid'] ) ) : ?>
									<?php $last_post_topic = wpforo_topic( $last_post['topicid'] ) ?>
									<?php $member = wpforo_member( $last_post ) ?>
									<?php if( WPF()->usergroup->can( 'va' ) && wpforo_setting( 'profiles', 'avatars' ) ): ?>
										<div class="wpforo-last-post-avatar">
											<?php wpforo_member_link( $member, '', 96, '', true, 'avatar' ) ?>
										</div>
									<?php endif; ?>
									<div class="wpforo-last-post">
										<p class="wpforo-last-post-title">
											<?php wpforo_topic_title( $last_post_topic, $last_post['url'], '{p}{au}{tc}{/a}{n}', true, '', 60 ) ?>
										</p>
										<p class="wpforo-last-post-author"><?php wpforo_member_link( $member, 'by' ); ?><span class="wpforo-date wpforo-date-ago">, <?php wpforo_date( $forum['last_post_date'] ) ?></span></p>
									</div>
								<?php else: ?>
									<div class="wpforo-last-post">
										<p class="wpforo-last-post-title"><?php wpforo_phrase( 'Forum is empty' ); ?></p>
									</div>
								<?php endif ?>
							</div>
						</div>
					<?php endif; ?>
                </div>

            </div><!-- wpforo-forum -->
        </div><!-- forum-wrap -->
		
		<?php do_action( 'wpforo_loop_hook', $key, $forum ) ?>
	
	<?php endforeach; ?> <!-- $forums as $forum -->
	
	<?php if( ! $forum_list ): ?>
		<?php do_action( 'wpforo_forum_loop_no_forums', $cat ); ?>
	<?php endif; ?>

</div><!-- wpfl-2 -->

