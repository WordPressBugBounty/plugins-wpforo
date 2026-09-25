<?php
/**
 * Submodule Loader and Initializer — gVectors News Module
 */

use gVectors\News\NewsModule;
use wpforo\modules\news\NewsConfig;

// 1. Try to load the autoloader if the class isn't available yet
if( ! class_exists( NewsModule::class ) ) {
    $autoloader = __DIR__ . '/../../admin/pages/news/vendor/autoload.php';

    if( file_exists( $autoloader ) ) {
        require_once $autoloader;
    }
}

// 2. Safely instantiate the module only if both classes exist
if( class_exists( NewsModule::class ) && class_exists( NewsConfig::class ) ) {
    new NewsModule(
        new NewsConfig(
            WPFORO_VERSION,
            WPFORO_BASEFOLDER,
            WPFORO_URL . '/admin/pages/news',
            'wpforo-addons',
            'wpforo_admin_base_menu'
        )
    );
} else {
    // Logs a message to your server's debug.log if the files are missing
    error_log( 'wpForo News Error: The admin/pages/news submodule files are missing. Please clone the project recursively using: git clone --recurse-submodules <repository_url>' );
}
