<?php
if( ! defined( 'ABSPATH' ) ) exit;

/**
 * @var array $attributes The block attributes.
 * @var string $content The block default content.
 * @var WP_Block $block The block instance.
 */

$default_instance = [
        'boardid'                => 0,
        'title'                  => 'Recent Posts',
        'forumids'               => [],
        'orderby'                => 'created',
        'order'                  => 'DESC',
        'count'                  => 9,
        'limit_per_topic'        => 0,
        'display_avatar'         => true,
        'forumids_filter'        => false,
        'current_forumid_filter' => false,
        'exclude_firstposts'     => false,
        'display_only_unread'    => false,
        'display_new_indicator'  => false,
        'refresh_interval'       => 0,
        'excerpt_length'         => 55,
];

$instance = wp_parse_args( $attributes, $default_instance );

// wpForo Recent Posts widget logic
wp_enqueue_script( 'wpforo-widgets-js' );

$is_user_logged_in = (bool) WPF()->current_userid;
if( $instance['display_only_unread'] ) {
    $display_block = $is_user_logged_in;
    $display_block = apply_filters( 'wpforo_widget_display_recent_posts', $display_block );
} else {
    $display_block = true;
}

if( ! $display_block ) return;

if( $instance['current_forumid_filter'] && $instance['boardid'] === WPF()->board->get_current( 'boardid' ) && $current_forumid = wpfval(
                WPF()->current_object,
                'forumid'
        ) ) {
    $instance['forumids'] = (array) $current_forumid;
}

$data = [
        'boardid'   => $instance['boardid'],
        'action'    => 'wpforo_load_ajax_widget_RecentPosts',
        'instance'  => $instance,
        'post_args' => [
                'forumids'        => ( $instance['forumids'] ?: $default_instance['forumids'] ),
                'orderby'         => $instance['orderby'],
                'order'           => $instance['order'],
                'row_count'       => ( intval( $instance['count'] ) ?: $default_instance['count'] ),
                'limit_per_topic' => ( intval( $instance['limit_per_topic'] ) ?: $default_instance['limit_per_topic'] ),
                'is_first_post'   => $instance['exclude_firstposts'] ? false : null,
                'check_private'   => true,
        ],
];

if( WPF()->board->get_current( 'boardid' ) === (int) $instance['boardid'] ) {
    $recent_posts_widget = new \wpforo\widgets\RecentPosts();
    $html                = $recent_posts_widget->get_widget( $data['instance'], $data['post_args'] );
    $onload              = false;
} else {
    $html            = '<div style="text-align: center; font-size: 20px;"><i class="fas fa-spinner fa-spin"></i></div>';
    $onload          = true;
    $data['referer'] = home_url();
}

$json = json_encode( $data );
?>
<div id="wpf-widget-recent-replies" class="wpforo-block-recent-posts wpforo-widget-wrap">
    <?php if( ! empty( $instance['title'] ) ) : ?>
        <h3 class="widget-title"><?php echo apply_filters( 'widget_title', $instance['title'] ); ?></h3>
    <?php endif; ?>
    <div class="wpforo-widget-content wpforo-ajax-widget <?php echo( ! $onload ? 'wpforo-ajax-widget-onload-false' : '' ); ?>" data-json="<?php echo esc_attr( $json ); ?>">
        <?php echo $html; ?>
    </div>
</div>
