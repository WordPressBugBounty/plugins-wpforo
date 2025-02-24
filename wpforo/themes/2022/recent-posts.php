<div class="wpforo-recent-content wpfr-posts">
	<?php if( ! empty( WPF()->current_object['posts'] ) ): ?>
        <table style="width: 100%; border: 0; border-spacing: 0; border-collapse: collapse;">
            <tr class="wpf-htr">
                <td class="wpf-shead-title" colspan="3"><?php wpforo_phrase( 'Posts with their shortened content' ) ?></td>
            </tr>
			<?php foreach( WPF()->current_object['posts'] as $key => $post ) :
				$member = wpforo_member( $post );
				$forum = wpforo_forum( $post['forumid'] );
				?>
                <tr class="wpf-ttr <?php wpforo_unread( $post['topicid'], 'post', true, $post['postid'] ); ?>">
                    <td class="wpf-spost-avatar">
						<?php if( WPF()->usergroup->can( 'va' ) && wpforo_setting( 'profiles', 'avatars' ) ): ?>
							<?php echo wpforo_user_avatar( $member, 40 ) ?>
						<?php endif; ?>
                    </td>
                    <td class="wpf-spost-title">
                        <a href="<?php echo esc_url( WPF()->post->get_url( $post['postid'] ) ) ?>" class="wpf-spost-title-link"
                           title="<?php wpforo_phrase( 'View entire post' ) ?>"><?php echo esc_html( $post['title'] ) ?> &nbsp;<i class="fas fa-chevron-right" style="font-size:11px;"></i></a>
                        <p style="font-size:12px"><?php wpforo_member_link( $member, 'by' ); ?>, <?php wpforo_date( $post['created'] ); ?></p>
                        <div class="wpf-spost-forum">
                            <span class="wpf-spost-forum-label"><?php wpforo_phrase( 'Forum' ) ?></span>
							<?php $forum_icon = ( isset( $forum['icon'] ) && $forum['icon'] ) ? $forum['icon'] : 'fas fa-comments'; ?>
                            <i class="<?php echo esc_attr( $forum_icon ) ?>" style="color: <?php echo esc_attr( $forum['color'] ) ?>"></i>
                            <a href="<?php echo $forum['url'] ?>"><?php echo esc_html( $forum['title'] ); ?></a>
                        </div>
                    </td>
                </tr>
				<?php if( apply_filters( 'wpforo_recent_posts_intro', true, $post ) ): ?>
                <tr class="wpf-ptr">
                    <td class="wpf-spost-icon">&nbsp;</td>
                    <td colspan="2" class="wpf-stext">
						<?php
						$body = wpforo_content_filter( $post['body'], $post );
						$body = preg_replace( '#\[attach][^\[\]]*\[/attach]#i', '', strip_tags( $body ) );
						wpforo_text( $body, 200 );
						?>
                    </td>
                </tr>
			<?php endif; ?>
				<?php do_action( 'wpforo_recent_loop_hook_posts', $key, $post ) ?>
			<?php endforeach ?>
        </table>
	<?php else: ?>
        <p class="wpf-p-error"><?php wpforo_phrase( 'No posts were found here' ) ?>  </p>
	<?php endif; ?>
</div>
