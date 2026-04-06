<?php
// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

/**
 *
 * @layout: Extended
 * @url: http://gvectors.com/
 * @version: 1.0.0
 * @author: gVectors Team
 * @description: Extended layout displays one level deeper information in advance.
 *
 */

$cover_styles = wpforo_get_forum_cover_styles( $cat );
?>

<div class="wpfl-1 wpforo-section">
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
		
		$sub_forums     = WPF()->forum->get_forums( [ "parentid" => $forum['forumid'], "type" => 'forum' ] );
		$has_sub_forums = is_array( $sub_forums ) && ! empty( $sub_forums );
		
		$data   = wpforo_forum( $forum['forumid'], 'childs' );
		$counts = wpforo_forum( $forum['forumid'], 'counts' );
		$topics = WPF()->topic->get_topics( [ "forumids" => $data, "orderby" => "type, modified", "order" => "DESC", "row_count" => wpforo_setting( 'forums', 'layout_extended_intro_topics_count' ) ]
		);
		
		$has_topics = is_array( $topics ) && ! empty( $topics );
		
		$forum_url    = wpforo_forum( $forum['forumid'], 'url' );
		$topic_toglle = wpforo_setting( 'forums', 'layout_extended_intro_topics_toggle' );
		
		if( ! empty( $forum['icon'] ) ) {
			$forum['icon'] = trim( (string) $forum['icon'] );
			if( strpos( (string) $forum['icon'], ' ' ) === false ) $forum['icon'] = 'fas ' . $forum['icon'];
		}
		$forum_icon = ( ! empty( $forum['icon'] ) ) ? $forum['icon'] : 'fas fa-comments';
		?>
        <div id="wpf-forum-<?php echo $forum['forumid'] ?>" class="forum-wrap <?php wpforo_unread( $forum['forumid'], 'forum' ) ?>">
            <div class="wpforo-forum">
                <div class="wpforo-forum-icon">
					<div style="border: 2px solid <?php echo esc_attr( $forum['color'] ) ?>; border-radius: 50%; width: 44px; height: 44px; display: flex; justify-content: center; align-items: center;">
						<i class="<?php echo esc_attr( $forum_icon ) ?>" style="color: <?php echo esc_attr( $forum['color'] ) ?>;"></i>
					</div>	
				</div>
                <div class="wpforo-forum-info">
                    <h3 class="wpforo-forum-title"><a href="<?php echo esc_url( (string) $forum_url ) ?>"><?php echo esc_html( $forum['title'] ); ?></a> <?php wpforo_viewing( $forum ); ?></h3>
                    <div class="wpforo-forum-description"><?php echo wp_kses_post( $forum['description'] ); ?></div>
					<div style="padding: 5px 0 0 0; font-size: 12px; color: #555;">
						<?php wpforo_phrase( 'Topics:' ); ?> <?php echo wpforo_print_number( $counts['topics'] ) ?> &nbsp; / &nbsp; 	
						<?php wpforo_phrase( 'Posts:' ); ?> <?php echo wpforo_print_number( $counts['posts'] ) ?>
					</div>
					<?php if( $has_sub_forums ) : ?>
                        <div class="wpforo-subforum">
                            <ul>
                                <li class="first" style="margin-top:1px;"><?php wpforo_phrase( 'Subforums' ); ?>:</li>
								<?php foreach( $sub_forums as $sub_forum ) :
									if( ! WPF()->perm->forum_can( 'vf', $sub_forum['forumid'] ) ) continue;
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

                <div class="wpforo-forum-topic-toggle">
					<?php if( $has_topics ) : ?>
                        <i id="img-arrow-<?php echo intval( $forum['forumid'] ) ?>" class="topictoggle fas fa-chevron-<?php echo( $topic_toglle == 1 ? 'up' : 'down' ) ?> wpfcl-0" style="font-size: 20px; cursor: pointer;"></i>
					<?php endif ?>
				</div>

            </div><!-- wpforo-forum -->
			
			<?php if( $has_topics ) : ?>

                <div class="wpforo-last-topics-<?php echo intval( $forum['forumid'] ) ?>" style="display: <?php echo( $topic_toglle == 1 ? 'block' : 'none' ) ?>;">
                    <div style="padding:6px 0; display: flex; justify-content: space-between; align-items: stretch;">
						<div style="width: 8%; max-width: 100px; min-width: 80px; padding: 0 10px 0 0px;"></div>
						<div style="width: 92%; border-top: 1px dashed #ccc; color: #999;"></div>
					</div>
					<div class="wpforo-last-topics-list">
                        <ul>
							<?php foreach( $topics as $topic ) : ?>
								<?php $last_post = wpforo_post( $topic['last_post'] ) ?>
								<?php $member = wpforo_member( $last_post ); ?>
								<?php if( ! empty( $last_post ) && ! empty( $member ) ): ?>
                                    <li class="<?php wpforo_topic_types( $topic ); wpforo_unread( $topic['topicid'], 'topic' ) ?>">
										<div class="wpforo-forum-icon"></div>
										<div class="wpforo-last-topic-icon">
											<div style="border: 1px dotted #999; border-radius: 3px; width: 44px; height: 38px; display: flex; justify-content: center; align-items: center;">
												<?php wpforo_topic_icon( $topic['topicid'], 'mixed' ); ?>
											</div>	
                                        </div>
                                        <div class="wpforo-last-topic-title">
											<?php wpforo_topic_title(
												$topic,
												$last_post['url'],
												'{p}{au}{tc}{/a}{n}',
												true,
												'',
												wpforo_setting( 'forums', 'layout_extended_intro_topics_length' )
											) ?>
											<div style="font-size:12px;"><?php wpforo_phrase( 'Replies:' ) ?> <?php echo (intval($topic['posts']) - 1) ?></div>
                                        </div>
										<div class="wpforo-last-topic-avatar">
											<?php echo wpforo_user_avatar( $member, 30 ) ?>
                                        </div>
                                        <div class="wpforo-last-topic-user" title="<?php echo esc_attr( $member['display_name'] ) ?>">
											<?php wpforo_member_link( $member, 'by', 12 ); ?><br>
                                            <span class="wpforo-last-topic-date wpforo-date-ago"><?php wpforo_date( $topic['modified'] ); ?></span>
                                        </div>
                                    </li>
								<?php endif; ?>
							<?php endforeach; ?>
							<?php if( intval( $forum['topics'] ) > wpforo_setting( 'forums', 'layout_extended_intro_topics_count' ) ): ?>
                                <li>
                                    <div class="wpf-vat">
                                        <a href="<?php echo esc_url( (string) $forum_url ) ?>"><?php wpforo_phrase( 'view all topics', true, 'lower' ); ?> <i class="fas fa-angle-right"
                                                                                                                                                              aria-hidden="true"></i></a>
                                    </div>
                                    <br class="wpf-clear"/>
                                </li>
							<?php endif ?>
                        </ul>
                    </div>
                    <br class="wpf-clear"/>
                </div><!-- wpforo-last-topics -->
			
			<?php endif; ?>

        </div><!-- forum-wrap -->
		
		<?php do_action( 'wpforo_loop_hook', $key, $forum ) ?>
	
	<?php endforeach; ?> <!-- $forums as $forum -->
	
	<?php if( ! $forum_list ): ?>
		<?php do_action( 'wpforo_forum_loop_no_forums', $cat ); ?>
	<?php endif; ?>

</div><!-- wpfl-1 -->
