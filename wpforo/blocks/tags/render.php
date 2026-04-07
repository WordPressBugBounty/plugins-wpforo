<?php
if( ! defined( 'ABSPATH' ) ) exit;

/**
 * @var array $attributes The block attributes.
 * @var string $content The block default content.
 * @var WP_Block $block The block instance.
 */

$instance = wp_parse_args( $attributes, [
        'title'   => __( 'Topic Tags', 'wpforo' ),
        'boardid' => 0,
        'topics'  => true,
        'count'   => 20,
] );

wp_enqueue_script( 'wpforo-widgets-js' );

$data = [
        'boardid'  => (int) $instance['boardid'],
        'action'   => 'wpforo_load_ajax_widget_Tags',
        'instance' => $instance,
];

if( WPF()->board->get_current( 'boardid' ) === (int) $instance['boardid'] ) {
    $tags_widget = new \wpforo\widgets\Tags();
    $html        = $tags_widget->get_widget( $instance );
    $onload      = false;
} else {
    $html            = '<div style="text-align: center; font-size: 20px;"><i class="fas fa-spinner fa-spin"></i></div>';
    $onload          = true;
    $data['referer'] = home_url();
}

$json = json_encode( $data );
?>
<div id="wpf-widget-tags" class="wpforo-block-tags wpforo-widget-wrap">
    <?php if( $instance['title'] ) : ?>
        <h3 class="widget-title"><?php echo apply_filters( 'widget_title', $instance['title'] ); ?></h3>
    <?php endif; ?>
    <div class="wpforo-widget-content wpforo-ajax-widget <?php echo( ! $onload ? 'wpforo-ajax-widget-onload-false' : '' ); ?>" data-json="<?php echo esc_attr( $json ); ?>">
        <?php echo $html; ?>
    </div>
</div>
