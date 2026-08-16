<?php
/**
 * Compatibility layer for the old GVT_API_Manager license system.
 *
 * Old gVectors addons include gvt-api-manager.php, which registers hooks
 * to communicate with the old gVectors license server (gvectors.com/gvt-api.php).
 * This is now superseded by the Paddle module's license system.
 *
 * This file MUST be loaded in the global namespace (before the core plugin namespace).
 *
 * Strategy:
 * 1) Define a dummy GVT_API_Manager class so that old addon copies which
 *    include gvt-api-manager.php with `class_exists('GVT_API_Manager')` guard
 *    will NOT define or instantiate the real class — prevents all old hooks.
 *
 * 2) If the real class was already loaded (addon loaded before gVectors Core Plugin),
 *    remove all hooks registered by GVT_API_Manager instances to stop
 *    unnecessary API calls to the old server and conflicting update checks.
 */

if( ! class_exists( 'GVT_API_Manager' ) ) {
	/**
	 * Dummy GVT_API_Manager class.
	 * Accepts the same constructor arguments as the real class but does nothing.
	 * Prevents old addon installs from registering legacy license hooks.
	 */
	class GVT_API_Manager {
		public function __construct( $plugin_filename, $plugin_option_slug, $admin_hook ) {
			// Intentionally empty — old license manager superseded by the Paddle module.
		}
	}
} else {
	/**
	 * Real GVT_API_Manager was already loaded (addon loaded before gVectors Core Plugin).
	 * Remove all hooks it registered to prevent:
	 * - Update checks against the old gvectors.com server
	 * - Old license activation forms and admin notices
	 * - Old auto-update UI customizations
	 * - Old plugin action links (Disconnect License)
	 */
	function gvectors_remove_old_gvt_api_manager_hooks() {
		global $wp_filter;
		
		// Collect removals first, then apply — safe against foreach mutation issues
		$removals = [];
		foreach( $wp_filter as $tag => $filter_obj ) {
			if( ! is_object( $filter_obj ) || empty( $filter_obj->callbacks ) ) continue;
			
			foreach( $filter_obj->callbacks as $priority => $callbacks ) {
				foreach( $callbacks as $key => $callback ) {
					if( ! is_array( $callback['function'] ) ) continue;
					if( ! is_object( $callback['function'][0] ) ) continue;
					if( ! ( $callback['function'][0] instanceof GVT_API_Manager ) ) continue;
					
					$removals[] = [ $tag, $priority, $key ];
				}
			}
		}
		
		foreach( $removals as $r ) {
			unset( $wp_filter[ $r[0] ]->callbacks[ $r[1] ][ $r[2] ] );
		}
	}
	
	add_action( 'plugins_loaded', 'gvectors_remove_old_gvt_api_manager_hooks', 0 );       // priority 0 = as early as possible within plugins_loaded
	add_action( 'wp_loaded', 'gvectors_remove_old_gvt_api_manager_hooks', 999 ); // priority 0 = as early as possible within plugins_loaded
}
