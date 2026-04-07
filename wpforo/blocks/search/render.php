<?php
if( ! defined( 'ABSPATH' ) ) exit;

/**
 * @var array $attributes The block attributes.
 * @var string $content The block default content.
 * @var WP_Block $block The block instance.
 */

$instance = wp_parse_args( $attributes, [
        'title'   => __( 'Forum Search', 'wpforo' ),
        'boardid' => 0,
] );

// wpForo Search widget logic
wp_enqueue_script( 'wpforo-widgets-js' );

$data = [
        'boardid' => (int) $instance['boardid'],
        'action'  => 'wpforo_load_ajax_widget_Search',
];

if( WPF()->board->get_current( 'boardid' ) === (int) $instance['boardid'] ) {
    $html = '';
    ob_start(); ?>
    <form action="<?php echo wpforo_home_url() ?>" method="GET" id="wpforo-search-form">
        <?php wpforo_make_hidden_fields_from_url( wpforo_home_url() ) ?>
        <label class="wpf-search-widget-label">
            <input type="text" placeholder="<?php wpforo_phrase( 'Search...' ) ?>" name="wpfs" class="wpfw-100"
                   value="<?php echo isset( $_GET['wpfs'] ) ? esc_attr( sanitize_text_field( $_GET['wpfs'] ) ) : '' ?>">
            <svg onclick="this.closest('form').submit();" viewBox="0 0 16 16" xml:space="preserve" xmlns="http://www.w3.org/2000/svg"><g id="Guide"/>
                <g id="Layer_2">
                    <path d="M13.85,13.15l-2.69-2.69c0.74-0.9,1.2-2.03,1.2-3.28C12.37,4.33,10.04,2,7.18,2S2,4.33,2,7.18s2.33,5.18,5.18,5.18   c1.25,0,2.38-0.46,3.28-1.2l2.69,2.69c0.1,0.1,0.23,0.15,0.35,0.15s0.26-0.05,0.35-0.15C14.05,13.66,14.05,13.34,13.85,13.15z    M3,7.18C3,4.88,4.88,3,7.18,3s4.18,1.88,4.18,4.18s-1.88,4.18-4.18,4.18S3,9.49,3,7.18z"/>
                </g></svg>
        </label>
    </form>
    <?php
    $html   = ob_get_clean();
    $onload = false;
} else {
    $html            = '<div style="text-align: center; font-size: 20px;"><i class="fas fa-spinner fa-spin"></i></div>';
    $onload          = true;
    $data['referer'] = home_url();
}

$json = json_encode( $data );
?>
<div id="wpf-widget-search" class="wpforo-block-search wpforo-widget-wrap">
    <?php if( $instance['title'] ) : ?>
        <h3 class="widget-title"><?php echo apply_filters( 'widget_title', $instance['title'] ); ?></h3>
    <?php endif; ?>
    <div class="wpforo-widget-content wpforo-ajax-widget <?php echo( ! $onload ? 'wpforo-ajax-widget-onload-false' : '' ); ?>" data-json="<?php echo esc_attr( $json ); ?>">
        <?php echo $html; ?>
    </div>
</div>
