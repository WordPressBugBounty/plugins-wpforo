<?php
// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

$args   = WPF()->current_object['args'];
$is_tag = wpfval( $args, 'type' ) === 'tag';
$posts  = WPF()->current_object['posts'];
?>
<h1 id="wpforo-search-title">
	<?php if( $is_tag ): ?>
        <i class="fas fa-tag"></i> &nbsp;<?php wpforo_phrase( 'Tag' ) ?>:&nbsp;
	<?php else: ?>
		<?php wpforo_phrase( 'Search result for' ) ?>:&nbsp;
	<?php endif; ?>
    <span class="wpfcl-5"><?php echo esc_html( wpfval( $args, 'needle' ) ) ?></span>
</h1>
<div class="wpforo-search-wrap <?php if( $is_tag ) echo 'wpforo-search-tag' ?>">
    <div class="wpf-search-bar"><?php wpforo_post_search_form( $args ) ?></div>
    <hr class="wpforo-search-sep">
    <div class="wpf-snavi"><?php wpforo_template_pagenavi( '', false ) ?></div>

    <div class="wpforo-search-results">
		<?php if( ! empty( $posts ) ) : ?>
			<?php foreach( $posts as $key => $post ) :
				if( ! $post['title'] ) $post['title'] = wpforo_topic( $post['topicid'], 'title' );
				$member = wpforo_member( $post );
				$post_url = WPF()->post->get_url( $post['postid'] );
				$forum_title = wpforo_forum( $post['forumid'], 'title' );
				$forum_url = wpforo_forum( $post['forumid'], 'url' );
			?>
                <div class="wpf-search-result-card">
                    <!-- Result Header: Title -->
                    <div class="wpf-sr-header">
                        <div class="wpf-sr-title">
                            <a href="<?php echo esc_url( $post_url ) ?>" title="<?php wpforo_phrase( 'View entire post' ) ?>">
                                <?php echo esc_html( $post['title'] ) ?>
                            </a>
                        </div>
                    </div>

                    <!-- Result Meta: Forum, User, Date, Relevance -->
                    <div class="wpf-sr-meta">
                        <?php if( ! $is_tag ): ?>
                            <div class="wpf-sr-meta-item wpf-sr-forum">
                                <i class="fas fa-folder-open"></i>
                                <a href="<?php echo esc_url( $forum_url ) ?>"><?php echo esc_html( $forum_title ) ?></a>
                            </div>
                        <?php endif; ?>

                        <div class="wpf-sr-meta-item wpf-sr-user">
                            <i class="fas fa-user"></i>
                            <?php wpforo_member_link( $member, '', 12 ) ?>
                        </div>

                        <div class="wpf-sr-meta-item wpf-sr-date">
                            <i class="far fa-clock"></i>
                            <span><?php wpforo_date( $post['created'] ); ?></span>
                        </div>

                        <?php if( ! $is_tag && isset( $post['matches'] ) && $post['matches'] ): ?>
                            <div class="wpf-sr-meta-item wpf-sr-relevance">
                                <i class="fas fa-bullseye"></i>
                                <span><?php echo ceil( $post['matches'] ) ?> <?php wpforo_phrase( 'relevance' ) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Result Body: Snippet or Tags -->
                    <?php if( ! $is_tag ): ?>
                        <div class="wpf-sr-body">
                            <?php echo wpforo_sanitize_search_body( $post['body'], $args['needle'], $post ); ?>
                        </div>
                    <?php else: ?>
                        <div class="wpf-sr-tags">
                            <?php wpforo_tags( $post['topicid'], false, 'small' ); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Result Footer: View Link -->
                    <div class="wpf-sr-footer">
                        <a href="<?php echo esc_url( $post_url ) ?>" class="wpf-sr-view-link">
                            <?php wpforo_phrase( 'View entire post' ) ?> <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
			<?php endforeach ?>
		<?php else : ?>
            <div class="wpf-sr-empty">
                <div class="wpf-sr-empty-icon">
                    <i class="fas fa-search"></i>
                </div>
                <p><?php wpforo_phrase( 'Posts not found' ) ?></p>
            </div>
		<?php endif ?>
    </div>

    <div class="wpf-snavi"><?php wpforo_template_pagenavi( '', false ) ?></div>
</div>
