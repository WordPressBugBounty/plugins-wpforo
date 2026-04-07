<?php
if( ! defined( 'ABSPATH' ) ) exit;

/**
 * @var array $attributes The block attributes.
 * @var string $content The block default content.
 * @var WP_Block $block The block instance.
 */

$instance = wp_parse_args( $attributes, [
        'title'            => __( 'Online Members', 'wpforo' ),
        'count'            => 15,
        'display_avatar'   => true,
        'groupids'         => WPF()->usergroup->get_visible_usergroup_ids(),
        'refresh_interval' => 0,
] );

if( is_string( $instance['groupids'] ) && wpforo_is_json( $instance['groupids'] ) ) {
    $instance['groupids'] = json_decode( $instance['groupids'], true );
}

if( is_array( $instance['groupids'] ) ) {
    $instance['groupids'] = array_filter( array_map( 'intval', $instance['groupids'] ) );
}

// wpForo OnlineMembers widget logic
$data = [
        'boardid'  => 0,
        'action'   => 'wpforo_load_ajax_widget_OnlineMembers',
        'instance' => $instance,
];

if( WPF()->board->get_current( 'boardid' ) !== 0 ) $data['referer'] = home_url();

// We need to use the OnlineMembers widget class to get the HTML
// To avoid duplicating logic, we can try to instantiate it or use its methods if possible.
// However, the widget class is designed to be used as a WP_Widget.
// Let's manually implement the HTML generation logic here, similar to get_widget() in OnlineMembers.php

$online_members = WPF()->member->get_online_members( $instance['count'], $instance['groupids'] );
$html           = '';

if( ! empty( $online_members ) ) {
    $html .= '<ul>
                 <li>
                    <div class="wpforo-list-item">';
    foreach( $online_members as $member ) {
        if( $instance['display_avatar'] ) {
            $html .= wpforo_member_link( $member, '', 96, 'onlineavatar', false, 'avatar', 'style="width:95%;" class="avatar"' );
        } else {
            $html .= wpforo_member_link( $member, '', 30, 'onlineuser', false );
        }
    }
    $html .= '<div class="wpf-clear"></div>
                    </div>
                </li>
            </ul>';
} else {
    $html .= '<p class="wpf-widget-note">&nbsp;' . wpforo_phrase( 'No online members at the moment', false ) . '</p>';
}

$json = json_encode( $data );
wp_enqueue_script( 'wpforo-widgets-js' );
?>
<div id="wpf-widget-online-users" class="wpforo-block-online-members wpforo-widget-wrap">
    <?php if( $instance['title'] ) : ?>
        <h3 class="widget-title"><?php echo apply_filters( 'widget_title', $instance['title'] ); ?></h3>
    <?php endif; ?>
    <div class="wpforo-widget-content wpforo-ajax-widget wpforo-ajax-widget-onload-false" data-json="<?php echo esc_attr( $json ); ?>">
        <?php echo $html; ?>
    </div>
</div>
