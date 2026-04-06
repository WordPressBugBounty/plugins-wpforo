<?php
	// Exit if accessed directly
	if( !defined( 'ABSPATH' ) ) exit;
?>

	<div class="wpfl-2 wpfl-5 wpforo-section">

		<div class="wpforo-topic-head">
			<div class="head-title"><?php wpforo_phrase('Topic Title') ?></div>
			<div class="head-stat-views"><?php wpforo_phrase('Views') ?></div>
			<div class="head-stat-posts"><?php wpforo_phrase('Posts') ?></div>
            <div class="head-stat-lastpost"><?php wpforo_phrase('Members') ?></div>
        </div>

		<?php foreach($topics as $key => $topic) : ?>

			<?php
                $last_poster = array();
                $last_post = array();
				$member = wpforo_member($topic);
			    $topic_url = wpforo_topic($topic['topicid'], 'url')
			?>

          <div class="topic-wrap <?php wpforo_topic_types($topic); wpforo_unread($topic['topicid'], 'topic'); ?>">
              <div class="wpforo-topic">
				  <?php if( WPF()->usergroup->can('va') && wpforo_setting( 'profiles', 'avatars' ) ): ?>
                      <div class="wpforo-topic-avatar"><?php echo wpforo_user_avatar($member, 48) ?></div>
                  <?php endif; ?>
                  <div class="wpforo-topic-icon">
                        <div style="border: 1px dotted #999; border-radius: 5px; width: 42px; height: 48px; display: flex; justify-content: center; align-items: center;">
                            <?php wpforo_topic_icon( $topic['topicid'], 'mixed' ); ?>
                        </div>	
                    </div>
                  <div class="wpforo-topic-info">
                    <div class="wpforo-topic-title"><?php wpforo_topic_title($topic, $topic_url, '{p}{au}{t}{/a}{n}{v}') ?></div>
					  <div class="wpforo-topic-start-info wpfcl-2"><?php wpforo_member_link($member); ?><span class="wpforo-date wpforo-date-ago">, <?php wpforo_date($topic['created']); ?></span></div>
                  	<div class="wpforo-topic-badges"><?php do_action('wpforo_topic_info_end', $topic); ?></div>
                  </div>
                  <div class="wpforo-topic-stat-views"><?php echo intval($topic['views']) ?></div>
                  <div class="wpforo-topic-stat-posts"><?php echo intval($topic['posts']) ?></div>
                  <div class="wpforo-topic-stat-lastpost wpf-sbd wpf-sbd-avatar">
                      <?php
                      if( $topic['last_post'] !== $topic['first_postid'] ){
	                      wpforo_l2_topic_users( $topic['topicid'] );
                      }else{
                          printf( '<div class="wpf-sbd-count">%1$s</div>', wpforo_phrase('no replies', false) );
                      }
                      ?>
                  </div>
              </div><!-- wpforo-topic -->
          </div><!-- topic-wrap -->

	        <?php do_action( 'wpforo_loop_hook', $key ) ?>

		<?php endforeach; ?>
    </div><!-- wpfl-2 -->
