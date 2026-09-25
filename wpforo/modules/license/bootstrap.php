<?php
/**
 * Submodule Loader and Initializer
 */

use gVectors\License\LicenseModule;
use wpforo\modules\license\LicenseConfig;

// 1. Try to load the autoloader if the class isn't available yet
if( ! class_exists( LicenseModule::class ) ) {
    $autoloader = __DIR__ . '/../../admin/pages/license/vendor/autoload.php';
    
    if( file_exists( $autoloader ) ) {
        require_once $autoloader;
    }
}

// 2. Safely instantiate the module only if both classes exist
if( class_exists( LicenseModule::class ) && class_exists( LicenseConfig::class ) ) {
    new LicenseModule(
        new LicenseConfig(
            WPFORO_VERSION,
            WPFORO_BASEFOLDER,
            WPFORO_URL . '/admin/pages/license',
            'wpforo-addons',
            'wpforo_admin_base_menu'
        )
    );
} else {
    // Logs a message to your server's debug.log if the files are missing
    error_log( 'wpForo License Error: The admin/pages/license submodule files are missing. Please clone the project recursively using: git clone --recurse-submodules <repository_url>' );
}
