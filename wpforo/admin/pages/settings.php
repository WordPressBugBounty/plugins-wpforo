<?php
// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;
if( ! WPF()->usergroup->can( 'ms' ) ) exit;
$tab = sanitize_key( (string) wpfval( $_GET, 'wpf_tab' ) );
WPF()->settings->init_info();
?>

<div class="wrap" style="margin:10px 20px 0 20px">
    <div id="wpf-setbox" class="wpf-dash">
        <!-- wpf-setbox-head start -->
        <div class="wpf-setbox-head">
            <div class="wpf-head-logo">
                <img src="<?php echo esc_url_raw( WPFORO_URL . "/assets/images/dashboard/wpforo-logo.png" ); ?>" alt="wpForo Logo">
            </div>
            <div class="wpf-head-title">
				<?php //esc_html_e("wpForo", "wpforo") ?>
            </div>
            <div class="wpf-head-info">
                <span><a href="https://wpforo.com/docs/wpforo-v2/" target="_blank"><?php esc_html_e( "Documentation", "wpforo" ); ?></a></span>
                <span><a href="https://wpforo.com/community/" target="_blank"><?php esc_html_e( "Support", "wpforo" ); ?></a></span>
                <span><a href="https://gvectors.com/product-category/wpforo/" target="_blank"><?php esc_html_e( "Addons", "wpforo" ); ?></a></span>
            </div>
        </div>
        <h1 style="width:0;height:0;margin:0;padding:0;"></h1>
		<?php WPF()->notice->show(); ?>
		<?php do_action( 'wpforo_settings_page_top' ) ?>
		<?php do_action( "wpforo_option_page" ); ?>
		<?php settings_errors( "wpforo" ); ?>
        <!-- wpf-setbox-head end -->

        <!-- wpf-setbox-body start -->
        <div id="wpf-admin-wrap" class="wpf-setbox-body">
            <div class="wpf-section">
                <h3><?php esc_html_e( "Forum Settings", "wpforo" ) ?></h3>
                <div class="wpf-opt-search">
                    <input id="wpf-opt-search-field" type="text" name="" value="" placeholder="<?php esc_attr_e( "Find an option...", "wpforo" ) ?>"/>
                    <span class="dashicons dashicons-search"></span>
                    <div id="wpf-opt-search-results"></div>
                </div>
            </div>
            <div class="wpf-section-imp-exp">
                <a href="<?php echo admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'settings' ) . '&wpf_settings_imp_exp=export' ); ?>">
                    <button class="button-secondary">
                        <svg height="24" width="24" viewBox="0 0 640 512">
                            <path fill="currentColor"
                                  d="M537.6 226.6c4.1-10.7 6.4-22.4 6.4-34.6c0-53-43-96-96-96c-19.7 0-38.1 6-53.3 16.2C367 64.2 315.3 32 256 32c-88.4 0-160 71.6-160 160c0 2.7.1 5.4.2 8.1C40.2 219.8 0 273.2 0 336c0 79.5 64.5 144 144 144h368c70.7 0 128-57.3 128-128c0-61.9-44-113.6-102.4-125.4m-132.9 88.7L299.3 420.7c-6.2 6.2-16.4 6.2-22.6 0L171.3 315.3c-10.1-10.1-2.9-27.3 11.3-27.3H248V176c0-8.8 7.2-16 16-16h48c8.8 0 16 7.2 16 16v112h65.4c14.2 0 21.4 17.2 11.3 27.3"></path>
                        </svg>&nbsp;<?php _e( 'Export', 'wpforo' ) ?></button> &nbsp;
                </a>
                <a href="<?php echo admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'settings' ) . '&wpf_settings_imp_exp=import' ); ?>">
                    <button class="button-secondary">
                        <svg height="24" width="24" viewBox="0 0 640 512">
                            <path fill="currentColor"
                                  d="M537.6 226.6c4.1-10.7 6.4-22.4 6.4-34.6c0-53-43-96-96-96c-19.7 0-38.1 6-53.3 16.2C367 64.2 315.3 32 256 32c-88.4 0-160 71.6-160 160c0 2.7.1 5.4.2 8.1C40.2 219.8 0 273.2 0 336c0 79.5 64.5 144 144 144h368c70.7 0 128-57.3 128-128c0-61.9-44-113.6-102.4-125.4M393.4 288H328v112c0 8.8-7.2 16-16 16h-48c-8.8 0-16-7.2-16-16V288h-65.4c-14.3 0-21.4-17.2-11.3-27.3l105.4-105.4c6.2-6.2 16.4-6.2 22.6 0l105.4 105.4c10.1 10.1 2.9 27.3-11.3 27.3"></path>
                        </svg>&nbsp;<?php _e( 'Import', 'wpforo' ) ?></button>
                </a>
            </div>
			
			<?php if( in_array( ( $imp_exp = sanitize_key( (string) wpfval( $_GET, 'wpf_settings_imp_exp' ) ) ), [ 'import', 'export' ], true ) ): ?>
                <div id="wpf-settings-tab" class="wpf-box-wrap" style="align-items:flex-start;">
                    <!-- Settings Content start -->
                    <div class="wpf-box wpf-setcon" style="width: 100%;">
                        <div class="wpf-setcon-head">
							<?php echo esc_html( __( ucfirst( $_GET['wpf_settings_imp_exp'] ), 'wpforo' ) ); ?>
                            <a class="wpf-back" href="<?php echo esc_url_raw( admin_url( "admin.php?page=" . wpfval( $_GET, 'page' ) ) ); ?>"><span
                                        class="dashicons dashicons-arrow-left-alt2"></span><?php esc_html_e( "Back", "wpforo" ) ?></a>
                        </div>
                        <div class="wpf-setcon-body" style="padding-top: 20px;">
							<?php include_once WPFORO_DIR . '/admin/settings/' . $imp_exp . ".php"; ?>
                        </div>
                    </div>
                    <div class="wpf-clear"></div>
                </div>
			<?php else: ?>
				<?php if( ! $tab || ( ! isset( WPF()->settings->info->core[ $tab ] ) && ! isset( WPF()->settings->info->addons[ $tab ] ) ) ) : ?>
                <!-- wpf-box-wrap start -->
                <div class="wpf-box-wrap wpf-settings-home">
					<?php
					foreach( WPF()->settings->info->core as $tab_key => $setting ) {
						if( ( $_GET['page'] === 'wpforo-base-settings' && ! $setting['base'] && is_wpforo_multiboard(
								) ) || ( $_GET['page'] !== 'wpforo-base-settings' && $setting['base'] && is_wpforo_multiboard() ) ) {
							continue;
						}
						?>
                        <!-- Settings start -->
                        <div class="wpf-box">
                            <div class="wpf-box-info wpf-<?php echo esc_attr( $setting["status"] ); ?>">
                                <img src="<?php echo esc_url_raw( WPFORO_URL . "/assets/images/dashboard/" . $setting["status"] . ".png" ); ?>">
                            </div>
                            <div class="wpf-box-head">
                                <a href="<?php echo esc_url_raw( admin_url( "admin.php?page=" . $_GET['page'] . "&wpf_tab=" . $tab_key ) ); ?>"
                                   title="<?php esc_attr_e( "Open Settings", "wpforo" ) ?>">
									<?php echo $setting["icon"]; ?>
                                </a>
                            </div>
                            <div class="wpf-box-foot">
                                <div class="wpf-box-title">
                                    <a href="<?php echo esc_url_raw( admin_url( "admin.php?page=" . $_GET['page'] . "&wpf_tab=" . $tab_key ) ); ?>"
                                       title="<?php esc_attr_e( "Open Settings", "wpforo" ) ?>">
										<?php echo $setting["title"] ?>
                                    </a>
                                </div>
                                <div class="wpf-box-arrow">
                                    <img src="<?php echo esc_url_raw( WPFORO_URL . "/assets/images/dashboard/arrow-right.png" ); ?>">
                                </div>
                            </div>
                        </div>
                        <!-- Settings end -->
						<?php
					}
					?>
                    <div class="wpf-clear"></div>
                </div>
                <!-- wpf-box-wrap end -->
				<?php if( WPF()->settings->info->addons ): ?>
                <div class="wpf-section">
                    <h3><?php esc_html_e( "Addons Settings", "wpforo" ) ?></h3>
                </div>
                <!-- wpf-box-wrap start -->
                <div class="wpf-box-wrap wpf-box-addons wpf-settings-home">
					<?php foreach( WPF()->settings->info->addons as $addon_key => $addon ) {
						if( ( $_GET['page'] === 'wpforo-base-settings' && ! $addon['base'] && is_wpforo_multiboard(
								) ) || ( $_GET['page'] !== 'wpforo-base-settings' && $addon['base'] && is_wpforo_multiboard() ) ) {
							continue;
						}
						?>
                        <!-- Settings start -->
                        <div class="wpf-box">
                            <div class="wpf-box-info wpf-<?php echo esc_attr( $addon["status"] ); ?>">
                                <img src="<?php echo esc_url_raw( WPFORO_URL . "/assets/images/dashboard/" . $addon["status"] . ".png" ); ?>" alt="">
                            </div>
                            <div class="wpf-box-head">
                                <a href="<?php echo esc_url_raw( admin_url( "admin.php?page=" . $_GET['page'] . "&wpf_tab=" . $addon_key ) ); ?>"
                                   title="<?php esc_attr_e( "Open Settings", "wpforo" ) ?>">
									<?php echo $addon["icon"]; ?>
                                </a>
                            </div>
                            <div class="wpf-box-foot">
                                <div class="wpf-box-title">
                                    <a href="<?php echo esc_url_raw( admin_url( "admin.php?page=" . $_GET['page'] . "&wpf_tab=" . $addon_key ) ); ?>"
                                       title="<?php esc_attr_e( "Open Settings", "wpforo" ) ?>">
										<?php echo $addon["title"] ?>
                                    </a>
                                </div>
                                <div class="wpf-box-arrow">
                                    <img src="<?php echo esc_url_raw( WPFORO_URL . "/assets/images/dashboard/arrow-right.png" ); ?>" alt="">
                                </div>
                            </div>
                        </div>
                        <!-- Settings end -->
						<?php
					} ?>
                </div>
                <!-- wpf-box-wrap end -->
                <div class="wpf-clear"></div>
			<?php endif; ?>
			<?php else: ?>
                <div id="wpf-settings-tab" class="wpf-box-wrap wpf-settings-tab_<?php echo esc_attr( $tab ); ?>" style="align-items:flex-start;">
                    <!-- Settings Content start -->
                    <div class="wpf-box wpf-setcon">
						<?php $setting = ( wpfkey( WPF()->settings->info->core, $tab ) ) ? WPF()->settings->info->core[ $tab ] : WPF()->settings->info->addons[ $tab ]; ?>
                        <div class="wpf-setcon-head">
							<?php echo esc_html( $setting["title"] ); ?>
                            <a class="wpf-back" href="<?php echo esc_url_raw( admin_url( "admin.php?page=" . wpfval( $_GET, 'page' ) ) ); ?>"><span
                                        class="dashicons dashicons-arrow-left-alt2"></span><?php esc_html_e( "Back", "wpforo" ) ?></a>
                        </div>
                        <div class="wpf-setcon-body">
							<?php
							if( wpfval( $setting, 'with_custom_form' ) ) {
								if( is_callable( $setting['callback_for_page'] ) ) call_user_func( $setting['callback_for_page'], $setting );
								do_action( "wpforo_settings_after", $tab, $setting );
							} else {
								?>
                                <form method="POST" enctype="multipart/form-data">
									<?php
									wp_nonce_field( "wpforo_settings_save_" . sanitize_title( $tab ) );
									if( is_callable( $setting['callback_for_page'] ) ) call_user_func( $setting['callback_for_page'], $setting );
									do_action( "wpforo_settings_after", $tab, $setting );
									?>
                                    <div class="wpf-opt-row" style="flex-wrap: wrap; margin-top: 50px; display: flex;">
                                        <div style="display: flex;">
                                            <input style="padding-left: 21px; padding-right: 21px;" type="submit" class="button button-primary" name="save"
                                                   value="<?php esc_attr_e( "Save Options", "wpforo" ); ?>">
											<?php if( ! $setting['base'] && is_wpforo_multiboard() ) : ?>
                                                <input style="order: -1;" type="submit" class="button button-primary" name="save_for_all"
                                                       value="<?php esc_attr_e( "Save Options for All Boards", "wpforo" ); ?>">
											<?php endif; ?>
                                        </div>
                                        <div style="order: -1;">
                                            <input type="submit" class="button" value="<?php _e( 'Reset Options', 'wpforo' ); ?>" name="reset"
                                                   onclick="return confirm('<?php _e( 'Do you really want to reset options?', 'wpforo' ) ?>')"/>
                                        </div>
                                    </div>
                                </form>
								<?php do_action( "wpforo_settings_after_form", $tab, $setting ); ?>
								<?php
							}
							?>
                        </div>
                    </div>
                    <!-- Settings Content end -->
                    <!-- Settings Sidebar start -->
                    <div class="wpf-setbar">
                        <ul class="wpf-box wpf-menu-group">
                            <li class="wpf-menu-head"><?php esc_html_e( "Settings", "wpforo" ) ?> <span class="dashicons dashicons-arrow-up"></span></li>
							<?php foreach( WPF()->settings->info->core as $tab_key => $setting ): ?>
                                <li<?php if( $tab === $tab_key ) echo " class='wpf-active'"; ?>><a href="<?php echo esc_url_raw(
										admin_url(
											"admin.php?page=" . ( ( $setting['base'] && is_wpforo_multiboard() ) ? 'wpforo-base-settings' : wpforo_prefix_slug(
												'settings'
											) ) . "&wpf_tab=" . $tab_key
										)
									); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span> <?php echo esc_html( $setting["title"] ); ?></a></li>
							<?php endforeach; ?>
                        </ul>
						<?php if( WPF()->settings->info->addons ): ?>
                            <ul class="wpf-box wpf-menu-group">
                                <li class="wpf-menu-head"><?php esc_html_e( "Addons Settings", 'wpforo' ) ?> <span class="dashicons dashicons-arrow-up"></span></li>
								<?php foreach( WPF()->settings->info->addons as $addon_key => $addon ): ?>
                                    <li<?php if( $tab === $addon_key ) echo " class='wpf-active'"; ?>><a href="<?php echo esc_url_raw(
											admin_url(
												"admin.php?page=" . ( ( $addon['base'] && is_wpforo_multiboard() ) ? 'wpforo-base-settings' : wpforo_prefix_slug(
													'settings'
												) ) . "&wpf_tab=" . $addon_key
											)
										); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span> <?php echo esc_html( $addon["title"] ); ?></a></li>
								<?php endforeach; ?>
                            </ul>
						<?php endif; ?>
                    </div>
                    <!-- Settings Sidebar end -->
                    <div class="wpf-clear"></div>
                </div>
                <script>
					jQuery(document).ready(function ($) {
						$(document).on('click', '.wpf-setbar .wpf-menu-head .dashicons-arrow-down, .wpf-setbar .wpf-menu-head .dashicons-arrow-up', function () {
							var $this = $(this);
							var up = $this.hasClass('dashicons-arrow-up');
							$this.toggleClass('dashicons-arrow-down dashicons-arrow-up');
							if (up) {
								$this.parents('.wpf-menu-group').find('li:not(.wpf-menu-head)').hide();
							} else {
								$this.parents('.wpf-menu-group').find('li:not(.wpf-menu-head)').show();
							}
						});
					});
                </script>
			<?php endif; ?>
			<?php endif; ?>
        </div>
        <!-- wpf-setbox-body end -->

    </div>
</div>
