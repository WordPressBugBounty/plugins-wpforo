<div class="wpforo-recent-content wpfr-topics">
	<?php if( ! empty( WPF()->current_object['topics'] ) ): ?>
        <table style="width: 100%; border: 0; border-spacing: 0; border-collapse: collapse;">
            <tr class="wpf-htr">
                <td class="wpf-shead-title" colspan="3"><?php wpforo_phrase( 'Topics with their latest replies in reversed order' ) ?></td>
            </tr>
			<?php foreach( WPF()->current_object['topics'] as $key => $topic ) : ?>
                <tr class="wpf-ttr <?php wpforo_unread( $topic['topicid'], 'topic' ); ?>">
                    <td class="wpf-spost-avatar">
						<?php if( WPF()->usergroup->can( 'va' ) && wpforo_setting( 'profiles', 'avatars' ) ): ?>
							<?php echo wpforo_user_avatar( $topic['member'], 30 ) ?>
						<?php endif; ?>
                    </td>
                    <td class="wpf-spost-title" colspan="2">
						<?php wpforo_topic_title( $topic, $topic['topic_url'], '{i}{p}{au}{t}{/a}{n}{v}', true, 'wpf-spost-title-link' ) ?>
                        <p style="font-size: 12px"><?php wpforo_member_link( $topic['member'], 'by' ); ?> <?php if( wpfkey( $topic, 'created' ) ) wpforo_date( $topic['created'] ); ?>
                            &nbsp;|&nbsp;
                            <span style="text-transform: lowercase;"><?php wpforo_phrase( 'Last post' ) ?>:</span> <?php if( wpfkey( $topic['last_post'], 'created' ) ) {
								wpforo_date( $topic['last_post']['created'] );
							} ?>
                        </p>
                        <div class="wpf-spost-forum">
                            <span class="wpf-spost-forum-label"><?php wpforo_phrase( 'Forum' ) ?></span>
							<?php $forum_icon = ( isset( $topic['forum']['icon'] ) && $topic['forum']['icon'] ) ? $topic['forum']['icon'] : 'fas fa-comments'; ?>
                            <i class="<?php echo esc_attr( $forum_icon ) ?>" style="color: <?php echo esc_attr( $topic['forum']['color'] ) ?>"></i>
                            <a href="<?php echo $topic['forum']['url'] ?>"><?php echo esc_html( $topic['forum']['title'] ); ?></a>
                        </div>
                    </td>
                </tr>
				<?php if( ! empty( $topic['replies'] ) && is_array( $topic['replies'] ) ) : ?>
                    <tr class="wpf-ptr">
                        <td class="wpf-spost-icon">&nbsp;</td>
                        <td colspan="2" class="wpf-stext">
                            <ul>
								<?php foreach( $topic['replies'] as $post ) : $poster = wpforo_member( $post ); ?>
                                    <li>
                                        <i class="fas fa-reply fa-rotate-180 wpfsx wpfcl-0"></i> &nbsp;
                                        <a href="<?php echo esc_url( wpforo_post( $post['postid'], 'url' ) ); ?>"
                                           title="<?php wpforo_phrase( 'REPLY:' ) ?> <?php echo esc_html( wpforo_text( $post['body'], 100, false ) ) ?>"><?php echo( ( $post_body = esc_html(
												wpforo_text( $post['body'], wpforo_setting( 'topics', 'layout_extended_intro_posts_length' ), false )
											) ) ? $post_body : esc_html( $post['title'] ) ) ?></a>
                                        <div class="wpf-spost-topic-recent-posts"><?php wpforo_date( $post['created'] ); ?>&nbsp; <?php wpforo_member_link( $poster, 'by %s', 5 ); ?> </div>
                                        <br class="wpf-clear">
                                    </li>
								<?php endforeach ?>
								<?php if( intval( $topic['posts'] ) > ( intval( wpfval( WPF()->current_object['args'], 'intro_posts' ) ) + 1 ) ): ?>
                                    <li style="text-align:right;"><a href="<?php echo esc_url( (string) $topic['topic_url'] ) ?>"><?php wpforo_phrase( 'view all posts', true, 'lower' ); ?> <i
                                                    class="fas fa-angle-right" aria-hidden="true"></i></a></li>
								<?php endif ?>
                            </ul>
                        </td>
                    </tr>
				<?php else: ?>
                    <tr class="wpf-tr-sep">
                        <td colspan="3" style="height: 2px;"></td>
                    </tr>
				<?php endif; ?>
				
				<?php do_action( 'wpforo_recent_loop_hook_topics', $key, $topic ) ?>
			<?php endforeach ?>
        </table>
	<?php else: ?>
		<?php if( wpfval( WPF()->current_object['args'], 'view' ) === 'unread' ): ?>
            <p class="wpf-p-error"><?php wpforo_phrase( 'No unread posts were found' ) ?>  </p>
		<?php else: ?>
            <p class="wpf-p-error"><?php wpforo_phrase( 'No topics were found here' ) ?>  </p>
		<?php endif; ?>
	<?php endif; ?>
</div>
