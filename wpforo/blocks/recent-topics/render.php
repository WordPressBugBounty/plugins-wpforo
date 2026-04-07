<?php
if( ! defined( 'ABSPATH' ) ) exit;

/**
 * @var array $attributes The block attributes.
 * @var string $content The block default content.
 * @var WP_Block $block The block instance.
 */

$instance = wp_parse_args( $attributes, [
        'boardid'                => 0,
        'title'                  => __( 'Recent Topics', 'wpforo' ),
        'forumids'               => [],
        'orderby'                => 'created',
        'order'                  => 'DESC',
        'count'                  => 9,
        'display_avatar'         => true,
        'forumids_filter'        => false,
        'current_forumid_filter' => false,
        'goto_unread'            => false,
        'refresh_interval'       => 0,
] );

// wpForo Recent Topics widget logic
wp_enqueue_script( 'wpforo-widgets-js' );

if( $instance['current_forumid_filter'] && $instance['boardid'] === WPF()->board->get_current( 'boardid' ) && $current_forumid = wpfval(
                WPF()->current_object,
                'forumid'
        ) ) {
    $instance['forumids'] = (array) $current_forumid;
}

$data = [
        'boardid'    => (int) $instance['boardid'],
        'action'     => 'wpforo_load_ajax_widget_RecentTopics',
        'instance'   => $instance,
        'topic_args' => [
                'forumids'  => ( $instance['forumids'] ?: [] ),
                'orderby'   => $instance['orderby'],
                'order'     => $instance['order'],
                'row_count' => (int) $instance['count'],
        ],
];

$recentTopicsWidget = new \wpforo\widgets\RecentTopics();

if( WPF()->board->get_current( 'boardid' ) === (int) $instance['boardid'] ) {
    $html   = $recentTopicsWidget->get_widget( $data['instance'], $data['topic_args'] );
    $onload = false;
} else {
    $html            = '<div style="text-align: center; font-size: 20px;"><i class="fas fa-spinner fa-spin"></i></div>';
    $onload          = true;
    $data['referer'] = home_url();
}

$json = json_encode( $data );
?>
<div id="wpf-widget-recent-replies" class="wpforo-block-recent-topics wpforo-widget-wrap">
    <?php if( ! empty( $instance['title'] ) ) : ?>
        <h3 class="widget-title"><?php echo apply_filters( 'widget_title', $instance['title'] ); ?></h3>
    <?php endif; ?>
    <div class="wpforo-widget-content wpforo-ajax-widget <?php echo( ! $onload ? 'wpforo-ajax-widget-onload-false' : '' ); ?>" data-json="<?php echo esc_attr( $json ); ?>">
        <?php echo $html; ?>
    </div>
</div>
