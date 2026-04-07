<?php
if( ! defined( 'ABSPATH' ) ) exit;

/**
 * @var array $attributes The block attributes.
 * @var string $content The block default content.
 * @var WP_Block $block The block instance.
 */

$instance = wp_parse_args( $attributes, [
        'title'             => __( 'My Profile', 'wpforo' ),
        'title_guest'       => __( 'Join Us!', 'wpforo' ),
        'hide_avatar'       => false,
        'hide_name'         => false,
        'hide_notification' => false,
        'hide_data'         => false,
        'hide_buttons'      => false,
        'hide_for_guests'   => false,
] );

$display_widget = ! ( ! is_user_logged_in() ) || ! wpfval( $instance, 'hide_for_guests' );
if( $display_widget ) {
    $class = 'wpf-' . wpforo_setting( 'styles', 'color_style' );
    ?>
    <div id="wpf-widget-profile" class="wpforo-block-profile wpforo-widget-wrap <?php echo esc_attr( $class ); ?>">
        <?php if( wpfval( $instance, 'title' ) && is_user_logged_in() ) : ?>
            <h3 class="widget-title"><?php echo apply_filters( 'widget_title', $instance['title'] ); ?></h3>
        <?php elseif( ! is_user_logged_in() ) : ?>
            <?php $title_guest = wpfval( $instance, 'title_guest' ) ? wpfval( $instance, 'title_guest' ) : apply_filters( 'wpforo_profile_widget_guest_title', __( 'Join Us!', 'wpforo' ) ); ?>
            <h3 class="widget-title"><?php echo apply_filters( 'widget_title', $title_guest ); ?></h3>
        <?php endif; ?>

        <div class="wpforo-widget-content">
            <?php $member = WPF()->current_user; ?>
            <div class="wpf-prof-wrap">
                <?php if( is_user_logged_in() ): wp_enqueue_script( 'wpforo-widgets-js' ); ?>
                    <div class="wpf-prof-header">
                        <?php if( ! wpfval( $instance, 'hide_avatar' ) && wpforo_setting( 'profiles', 'avatars' ) ): ?>
                            <div class="wpf-prof-avatar">
                                <?php echo wpforo_user_avatar( $member, 80 ); ?>
                            </div>
                        <?php endif; ?>
                        <?php if( ! wpfval( $instance, 'hide_name' ) ): ?>
                            <div class="wpf-prof-info">
                                <div class="wpf-prof-name">
                                    <?php WPF()->member->show_online_indicator( $member['userid'] ) ?>
                                    <?php echo wpfval( $member, 'display_name' ) ? esc_html( $member['display_name'] ) : esc_html( urldecode( (string) $member['nicename'] ) ) ?>
                                    <?php if( function_exists( 'wpforo_is_anonymous_mask_member' ) && ! wpforo_is_anonymous_mask_member( $member ) ) {
                                        wpforo_member_nicename( $member, '@' );
                                    } ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if( ! wpfval( $instance, 'hide_notification' ) && wpforo_setting( 'notifications', 'notifications' ) ): ?>
                            <div class="wpf-prof-alerts">
                                <?php WPF()->activity->bell( 'wpf-widget-alerts' ); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if( ! wpfval( $instance, 'hide_notification' ) && wpforo_setting( 'notifications', 'notifications' ) ): ?>
                        <div class="wpf-prof-notifications" style="flex-basis: 100%;">
                            <?php wpforo_notifications() ?>
                        </div>
                    <?php endif; ?>
                    <?php if( ! wpfval( $instance, 'hide_data' ) ): ?>
                        <div class="wpf-prof-content">
                            <?php do_action( 'wpforo_wiget_profile_content_before', $member ); ?>
                            <div class="wpf-prof-data">
                                <div class="wpf-prof-rating">
                                    <?php echo in_array( $member['groupid'], wpforo_setting( 'rating', 'rating_title_ug' ) ) ? '<span class="wpf-member-title wpfrt">' . esc_html(
                                                    $member['rating']['title']
                                            ) . '</span>' : ''; ?>
                                    <?php wpforo_member_badge( $member ); ?>
                                </div>
                                <?php wpforo_member_title( $member, true, '', '', [ 'rating-title' ] ); ?>
                            </div>
                            <?php do_action( 'wpforo_wiget_profile_content_after', $member ); ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="wpf-prof-footer">
                    <?php do_action( 'wpforo_wiget_profile_footer_before', $member ); ?>
                    <?php if( is_user_logged_in() ): ?>
                        <?php if( ! wpfval( $instance, 'hide_buttons' ) ): ?>
                            <div class="wpf-prof-buttons">
                                <?php WPF()->tpl->member_buttons( $member, 'wpforo\widgets\Profile' ) ?>
                                <?php if( ! wpforo_is_bot() ) : ?>
                                    <a href="<?php echo wpforo_logout_url() ?>" class="wpf-logout"
                                       title="<?php wpforo_phrase( 'Logout' ) ?>">
                                        <svg height="12" width="12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                                            <path fill="currentColor"
                                                  d="M497 273L329 441c-15 15-41 4.5-41-17v-96H152c-13.3 0-24-10.7-24-24v-96c0-13.3 10.7-24 24-24h136V88c0-21.4 25.9-32 41-17l168 168c9.3 9.4 9.3 24.6 0 34zM192 436v-40c0-6.6-5.4-12-12-12H96c-17.7 0-32-14.3-32-32V160c0-17.7 14.3-32 32-32h84c6.6 0 12-5.4 12-12V76c0-6.6-5.4-12-12-12H96c-53 0-96 43-96 96v192c0 53 43 96 96 96h84c6.6 0 12-5.4 12-12z"/>
                                        </svg>
                                    </a>
                                <?php endif ?>
                            </div>
                        <?php endif; ?>
                    <?php elseif( ! wpforo_is_bot() ): ?>
                        <div class="wpf-prof-loginout">
                            <a href="<?php echo wpforo_login_url(); ?>" class="wpf-button">
                                <svg height="12" width="12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                                    <path fill="currentColor"
                                          d="M416 448h-84c-6.6 0-12-5.4-12-12v-40c0-6.6 5.4-12 12-12h84c17.7 0 32-14.3 32-32V160c0-17.7-14.3-32-32-32h-84c-6.6 0-12-5.4-12-12V76c0-6.6-5.4-12-12-12h84c53 0 96 43 96 96v192c0 53-43 96-96 96zm-47-201L201 79c-15-15-41-4.5-41 17v96H24c-13.3 0-24 10.7-24 24v96c0 13.3 10.7 24 24 24h136v96c0 21.5 26 32 41 17l168-168c9.3-9.4 9.3-24.6 0-34z"/>
                                </svg> <?php wpforo_phrase( 'Login' ) ?></a> &nbsp;
                            <a href="<?php echo wpforo_register_url(); ?>" class="wpf-button">
                                <svg height="12" width="12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512">
                                    <path fill="currentColor"
                                          d="M96 128a128 128 0 1 1 256 0A128 128 0 1 1 96 128zM0 482.3C0 383.8 79.8 304 178.3 304h91.4C368.2 304 448 383.8 448 482.3c0 16.4-13.3 29.7-29.7 29.7H29.7C13.3 512 0 498.7 0 482.3zM504 312V248H440c-13.3 0-24-10.7-24-24s10.7-24 24-24h64V136c0-13.3 10.7-24 24-24s24 10.7 24 24v64h64c13.3 0 24 10.7 24 24s-10.7 24-24 24H552v64c0 13.3-10.7 24-24 24s-24-10.7-24-24z"/>
                                </svg> <?php wpforo_phrase( 'Register' ) ?></a>
                        </div>
                    <?php endif; ?>
                    <?php do_action( 'wpforo_wiget_profile_footer_after', $member ); ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}
