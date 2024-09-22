<?php

use wpforo\wpforo;

spl_autoload_register( function( $namespace ) {
	if( strpos( (string) $namespace, 'wpforo' ) === 0 || strpos( (string) $namespace, 'go2wpforo' ) === 0 ) {
		$filepath = rtrim(
            trim(
	            str_replace(
					[ '/', '\\', '\\\\' ], DIRECTORY_SEPARATOR, WP_PLUGIN_DIR . "\\" . $namespace
	            )
            ),
            DIRECTORY_SEPARATOR
        ) . ".php";
		if( is_file( $filepath ) && is_readable( $filepath ) ) require_once $filepath;
	}
} );

/**
 * Main instance of wpForo.
 *
 * Returns the main instance of WPF to prevent the need to use globals.
 *
 * @return wpforo
 * @since  1.4.3
 */
if( ! function_exists( 'WPF' ) ) {
	function WPF() {
		return wpforo::instance();
	}
}

// Global for backwards compatibility.
$GLOBALS['wpforo'] = WPF();

// ####  deactivate old addons
function wpforo_deactivate_all_old_addons() {
	$v = get_option( 'wpforo_version', '' );
	if( WPFORO_VERSION !== $v && version_compare( $v, '2.0.3', '<' ) ){
		// ####  deactivate old addons
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once  WPFORO_DIR . '/includes/functions.php';
		$plugins = [];
		foreach( wpforo_get_addons_info() as $addon ){
			if( file_exists( $addon['ABSPATH'] ) ){
				$plugin_data = get_plugin_data( $addon['ABSPATH'] );
				if( $plugin_data['Version'] ){
					$plugin_basename = plugin_basename( $addon['ABSPATH'] );
					if( version_compare( $plugin_data['Version'], '3.0.0', '<' ) && is_plugin_active( $plugin_basename ) ) $plugins[] = $plugin_basename;
				}
			}
		}
		if( $plugins ) deactivate_plugins($plugins);
	}

	add_action('admin_init', function(){
		if( strpos( (string) $_SERVER['REQUEST_URI'], '/plugins.php' ) !== false ){
			$new_plugins_by_text_domain = [
				'wpforo_mempress',
				'wpforo_surmem',
				'wpforo_mempro',
				'wpforo_groups'
			];
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			require_once  WPFORO_DIR . '/includes/functions.php';
			foreach( wpforo_get_addons_info() as $addon ){
				if( file_exists( $addon['ABSPATH'] ) ){
					$plugin_data = get_plugin_data( $addon['ABSPATH'] );
					// Allow new addons to start versioning from 1.0.0
					if( $plugin_data['TextDomain'] && in_array($plugin_data['TextDomain'], $new_plugins_by_text_domain) ) continue;
					if( $plugin_data['Version'] ){
						$plugin_basename = plugin_basename( $addon['ABSPATH'] );
						if( version_compare( $plugin_data['Version'], '3.0.0', '<' ) ){
							if( is_plugin_active( $plugin_basename ) ){
								deactivate_plugins( (array) $plugin_basename );
							}else{
								add_filter( 'plugin_action_links_' . $plugin_basename, function ( $links ) {
									$links['activate'] = '<a style="color: #ccc; cursor: pointer" onclick="alert( \'This plugin is not compatible with wpForo 2.0 version! Please update the plugin to the latest 3.0.x version, then activate it again. If the license key is expired, you should renew your license to get product updates. Otherwise, you should use old wpForo versions (1.9.X).\' );">Activate</a>';
									return $links;
								} );
							}
						}
					}
				}
			}
		}
	});
}
wpforo_deactivate_all_old_addons();
