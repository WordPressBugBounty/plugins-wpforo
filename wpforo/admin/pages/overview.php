<?php
// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;
?>

<div id="wpf-admin-wrap" class="wrap">
    <h1 style="margin: 0; padding: 0; line-height: 10px;">&nbsp</h1>
	<?php WPF()->notice->show() ?>

    <div id="dashboard-widgets-wrap">
        <div class="metabox-holder" id="dashboard-widgets">

            <div class="postbox-container" id="postbox-container-0" style="width:100%;">
                <div class="wpf-box-wrap" style="min-height:60px;">

                    <div class="postbox" id="wpforo_dashboard_widget_0" style="margin: 10px; padding: 15px 15px 10px 15px;">
                        <div class="inside">
                            <div class="main" style="padding:5px 15px 15px 15px;">
                                <div style="float:left; vertical-align:top; width:calc(100% - 200px);;">
                                    <p style="font-size:30px; margin:0 0 10px; font-family:Constantia, 'Lucida Bright', 'DejaVu Serif', Georgia, serif">
                                        <?php _e( 'Welcome to wpForo', 'wpforo' ); echo ' ' . esc_html( WPFORO_VERSION ) ?>
                                    </p>
                                    <p style="margin:0; font-size:14px;">
										<?php _e( 'Thank you for using wpForo! wpForo is the first 360° AI-powered forum platform for WordPress with revolutionary AI features and Multi-layout template system.
                                    The "Extended", "Simplified", "Q&A", "Threaded" and "Boxed" layouts fit almost all type of discussions needs. You can use wpForo for small and extremely large communities. If you found some issue or bug please open a support topic in wpForo Support forum at wpForo.com. If you liked wpForo please leave some good review for this plugin.',
											'wpforo'
										); ?>
                                    </p>
                                </div>
                                <div style="float:right; vertical-align:top; padding-right:0; width:150px; text-align:right; padding-top:20px;">
                                    <img class="wpforo-dashboard-logo" src="<?php echo WPFORO_URL ?>/assets/images/wpforo-logo.png" alt="wpforo logo">
                                    <p style="font-size:11px; color:#B1B1B1; font-style:italic; text-align:right; line-height:14px; padding-top:15px; margin:0;">
										<?php _e( 'Thank you!<br> Sincerely yours,<br> gVectors Team', 'wpforo' ); ?>&nbsp;
                                    </p>
                                </div>
                                <div style="clear:both;"></div>
                            </div>
                        </div>
                    </div><!-- widget / postbox -->

                </div>
            </div>

			<?php if( current_user_can( 'administrator' ) || current_user_can( 'editor' ) || current_user_can( 'author' ) ): ?>
                <div class="postbox-container" style="width: 100%;">
                    <div class="wpf-box-wrap">

                        <div class="postbox wpf-dash-box" id="wpforo_dashboard_widget_server">
                            <h2 class="wpf-box-header"><span><?php _e( 'Server Information', 'wpforo' ); ?></span></h2>
                            <div class="inside">
                                <div class="main">
                                    <table style="width:98%; margin:0 auto; text-align:left;">
                                        <tr class="wpf-dw-tr">
                                            <td class="wpf-dw-td">USER AGENT</td>
                                            <td class="wpf-dw-td-value"><?php echo esc_html( $_SERVER['HTTP_USER_AGENT'] ) ?></td>
                                        </tr>
                                        <tr class="wpf-dw-tr">
                                            <td class="wpf-dw-td">Web Server</td>
                                            <td class="wpf-dw-td-value"><?php echo esc_html( $_SERVER['SERVER_SOFTWARE'] ) ?></td>
                                        </tr>
                                        <tr class="wpf-dw-tr">
                                            <td class="wpf-dw-td">PHP Version</td>
                                            <td class="wpf-dw-td-value"><?php echo phpversion(); ?></td>
                                        </tr>
                                        <tr class="wpf-dw-tr">
                                            <td class="wpf-dw-td">MySQL Version</td>
                                            <td class="wpf-dw-td-value"><?php echo WPF()->db->get_var( "SELECT VERSION()" ); ?></td>
                                        </tr>
                                        <tr class="wpf-dw-tr">
                                            <td class="wpf-dw-td">PHP Max Post Size</td>
                                            <td class="wpf-dw-td-value"><?php echo ini_get( 'post_max_size' ); ?></td>
                                        </tr>
                                        <tr class="wpf-dw-tr">
                                            <td class="wpf-dw-td">PHP Max Upload Size</td>
                                            <td class="wpf-dw-td-value"><?php echo ini_get( 'upload_max_filesize' ); ?></td>
                                        </tr>
                                        <tr class="wpf-dw-tr">
                                            <td class="wpf-dw-td">PHP Memory Limit</td>
                                            <td class="wpf-dw-td-value"><?php echo ini_get( 'memory_limit' ); ?></td>
                                        </tr>
                                        <tr class="wpf-dw-tr">
                                            <td class="wpf-dw-td">PHP DateTime Class</td>
                                            <td class="wpf-dw-td-value" style="line-height: 18px!important;">
                                                <?php echo ( class_exists( 'DateTime' ) && class_exists( 'DateTimeZone' ) && method_exists( 'DateTime', 'setTimestamp' ) ) ? '<span class="wpf-green">' . __( 'Available', 'wpforo' ) . '</span>' : '<span class="wpf-red">' . __(
                                                        'The setTimestamp() method of PHP DateTime class is not available. Please make sure you use PHP 5.4 and higher version on your hosting service.',
                                                        'wpforo'
                                                    ) . '</span> | <a href="http://php.net/manual/en/datetime.settimestamp.php" target="_blank">more info&raquo;</a>'; ?> </td>
                                        </tr>
                                        <?php do_action( 'wpforo_dashboard_widget_server' ) ?>
                                    </table>
                                </div>
                            </div>
                        </div><!-- widget / postbox -->

                        <?php if( wpforo_current_user_is( 'admin' ) ) : ?>
                            <div class="postbox wpf-dash-box" id="wpforo_dashboard_widget_0" style="min-width: 250px; width: 290px">
                                <h2 class="wpf-box-header"><span><?php _e( 'General Maintenance', 'wpforo' ); ?></span></h2>
                                <p class="wpf-info" style="padding:10px;"><?php _e( "This process may take a few seconds or dozens of minutes, please be patient and don't close this page. If you got 500 Server Error please don't worry, the data updating process is still working in MySQL server.", 'wpforo' ); ?></p>
                                <div class="inside">
                                    <div class="main">

                                        <div style="width:100%; padding:7px;">
                                            <?php
                                            $synch_user_profiles   = wp_nonce_url( admin_url( 'admin.php?page=wpforo-overview&wpfaction=synch_user_profiles' ), 'wpforo_synch_user_profiles' );
                                            $reset_users_stat_url  = wp_nonce_url( admin_url( 'admin.php?page=wpforo-overview&wpfaction=reset_users_stats' ), 'wpforo_reset_users_stat' );
                                            $reset_user_cache      = wp_nonce_url( admin_url( 'admin.php?page=wpforo-overview&wpfaction=reset_user_cache' ), 'wpforo_reset_user_cache' );
                                            ?>
                                            <a href="<?php echo esc_url( (string) $reset_users_stat_url ); ?>" style="min-width:160px; margin-bottom:10px; text-align:center;" class="button button-secondary"><?php _e( 'Update Users Statistic', 'wpforo' ); ?></a>&nbsp;
                                            <a href="<?php echo esc_url( (string) $reset_user_cache ); ?>" style="min-width:160px; margin-bottom:10px; text-align:center;" class="button button-secondary"><?php _e( 'Delete User Cache', 'wpforo' ); ?></a>&nbsp;
                                            <a href="<?php echo esc_url( (string) $synch_user_profiles ); ?>" style="min-width:160px; margin-bottom:10px; text-align:center;" class="button button-secondary"><?php _e( 'Synch User Profiles', 'wpforo' ); ?></a>&nbsp;
                                        </div>

                                    </div>
                                </div>
                            </div><!-- widget / postbox -->
                        <?php endif ?>

                        <div class="postbox wpf-dash-box" id="wpforo_dashboard_widget_1" style="min-width: 250px;">
                            <h2 class="wpf-box-header"><span><?php _e( 'General Information', 'wpforo' ); ?></span></h2>
                            <div class="inside">
                                <div class="main">
                                    <ul>
                                        <li class="post-count"><?php _e( 'You are currently running', 'wpforo' ); ?> wpForo <?php echo esc_html( WPFORO_VERSION ) ?></li>
                                        <li class="page-count"><?php _e( 'Current active theme', 'wpforo' ); ?>: <?php echo WPF()->tpl->theme ?></li>
                                        <li class="page-count"><?php _e( 'wpForo Community', 'wpforo' ); ?>: <a href="https://wpforo.com/community/">https://wpforo.com/community/</a></li>
                                        <li class="page-count"><?php _e( 'wpForo Documentation', 'wpforo' ); ?>: <a href="https://wpforo.com/docs/">https://wpforo.com/docs/</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div><!-- widget / postbox -->

						<?php do_action( 'wpforo_dashboard_widgets_col1' ); ?>

                    </div><!-- normal-sortables -->
                </div><!-- wpforo_postbox_container -->
			<?php endif; ?>

            <div class="postbox-container" id="postbox-container-3">
                <div class="wpf-box-wrap">

					<?php do_action( 'wpforo_dashboard_widgets_col3', WPF() ); ?>

                </div><!-- normal-sortables -->
            </div><!-- wpforo_postbox_container -->

        </div><!-- dashboard-widgets -->
    </div><!-- dashboard-widgets-wrap -->

</div><!-- wpwrap -->

