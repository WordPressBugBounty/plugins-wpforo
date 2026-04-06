<?php
// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

/**
 * wpForo Classic Theme - WordPress theme specific functions
 * @hook: action - 'after_setup_theme'
 * @description: WordPress theme functions, for wpForo theme functions use functions.php file.
 * @theme: Classic
 */

add_filter('wpforo_editor_settings', function($s){
    if ( 'dark' === wpforo_setting( 'styles', 'color_style' ) ){
        $s['tinymce']['content_style'] .= "body{background-color:#bbb; color:#111;}";
        return $s;
    }
    return $s;
}, 1);
