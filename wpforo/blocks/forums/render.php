<?php
if( ! defined( 'ABSPATH' ) ) exit;

/**
 * @var array $attributes The block attributes.
 * @var string $content The block default content.
 * @var WP_Block $block The block instance.
 */

$instance = wp_parse_args( $attributes, [
        'boardid'  => 0,
        'title'    => __( 'Forums', 'wpforo' ),
        'dropdown' => false,
] );

// Logic from wpforo\widgets\Forums::get_widget
if( ! function_exists( 'wpforo_block_forums_get_widget' ) ) {
    function wpforo_block_forums_get_widget( $instance ) {
        ob_start();

        if( wpfval( $instance, 'dropdown' ) ) {
            $forum_urls = [];
            $forums     = array_filter( WPF()->forum->get_forums(), function( $forum ) {
                return WPF()->perm->forum_can( 'vf', $forum['forumid'] );
            } );
            if( ! empty( $forums ) ) {
                foreach( $forums as $forum ) {
                    $forum_urls[ 'forum_' . $forum['forumid'] ] = wpforo_home_url( $forum['slug'] );
                }
            }
            if( ! empty( $forum_urls ) ) {
                echo '<select onchange="window.location.href = wpf_forum_urls[\'forum_\' + this.value]">';
                WPF()->forum->tree( 'select_box', true, WPF()->current_object['forumid'] );
                echo '</select>';
                ?>
                <script>
					var wpf_forum_json = '<?php echo json_encode( $forum_urls ) ?>';
					var wpf_forum_urls = JSON.parse(wpf_forum_json);
                </script>
                <?php
            }
        } else {
            WPF()->forum->tree( 'front_list', true, WPF()->current_object['forumid'], false );
        }

        return ob_get_clean();
    }
}

wp_enqueue_script( 'wpforo-widgets-js' );

$data = [
        'boardid'  => $instance['boardid'],
        'action'   => 'wpforo_load_ajax_widget_Forums',
        'instance' => $instance,
];

if( WPF()->board->get_current( 'boardid' ) === $instance['boardid'] ) {
    $html   = wpforo_block_forums_get_widget( $data['instance'] );
    $onload = false;
} else {
    $html            = '<div style="text-align: center; font-size: 20px;"><i class="fas fa-spinner fa-spin"></i></div>';
    $onload          = true;
    $data['referer'] = home_url();
}

$json = json_encode( $data );
?>
<div id="wpf-widget-forums" class="wpforo-block-forums wpforo-widget-wrap">
    <?php if( $instance['title'] ) : ?>
        <h3 class="widget-title"><?php echo apply_filters( 'widget_title', $instance['title'] ); ?></h3>
    <?php endif; ?>
    <div class="wpforo-widget-content wpforo-ajax-widget <?php echo( ! $onload ? 'wpforo-ajax-widget-onload-false' : '' ); ?>" data-json="<?php echo esc_attr( $json ); ?>">
        <?php echo $html; ?>
    </div>
</div>
