<?php

namespace wpforo\classes;

if( ! defined( 'ABSPATH' ) ) exit;

class Blocks {
    public function __construct() {
        if( did_action( 'init' ) ) {
            $this->register_blocks();
        } else {
            add_action( 'init', [ $this, 'register_blocks' ] );
        }
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] );
    }
    
    public function register_blocks() {
        $dir = WPFORO_DIR . '/blocks/online-members';
        register_block_type( $dir );
        
        $dir = WPFORO_DIR . '/blocks/search';
        register_block_type( $dir );
        
        $dir = WPFORO_DIR . '/blocks/tags';
        register_block_type( $dir );
        
        $dir = WPFORO_DIR . '/blocks/forums';
        register_block_type( $dir );
        
        $dir = WPFORO_DIR . '/blocks/profile';
        register_block_type( $dir );
        
        $dir = WPFORO_DIR . '/blocks/recent-posts';
        register_block_type( $dir );
        
        $dir = WPFORO_DIR . '/blocks/recent-topics';
        register_block_type( $dir );
    }
    
    public function enqueue_block_editor_assets() {
        $dir        = WPFORO_DIR . '/blocks/online-members';
        $asset_file = include( $dir . '/index.asset.php' );
        $handle     = 'wpforo-online-members-editor-script';
        
        wp_enqueue_script(
            $handle,
            WPFORO_URL . '/blocks/online-members/index.js',
            $asset_file['dependencies'],
            $asset_file['version']
        );
        
        wp_enqueue_style(
            'wpforo-online-members-editor-style',
            WPFORO_URL . '/blocks/online-members/editor.css',
            [],
            $asset_file['version']
        );
        
        $dir           = WPFORO_DIR . '/blocks/search';
        $asset_file    = include( $dir . '/index.asset.php' );
        $handle_search = 'wpforo-search-editor-script';
        
        wp_enqueue_script(
            $handle_search,
            WPFORO_URL . '/blocks/search/index.js',
            $asset_file['dependencies'],
            $asset_file['version']
        );
        
        wp_enqueue_style(
            'wpforo-search-editor-style',
            WPFORO_URL . '/blocks/online-members/editor.css',
            [],
            $asset_file['version']
        );
        
        $user_groups = WPF()->usergroup->usergroup_list_data();
        $boards      = WPF()->board->get_boards( [ 'status' => 1 ] );
        $forums      = WPF()->forum->get_forums();
        $data        = [
            'user_groups' => array_values( $user_groups ),
            'boards'      => array_values( $boards ),
            'forums'      => array_values( $forums ),
        ];

        // Localize data once on the first script — all blocks read the same global
        wp_localize_script( $handle, 'wpforo_block_data', $data );
        wp_add_inline_script( $handle, "window.wpforo_block_data = " . json_encode( $data ) . ";", 'before' );

        $dir         = WPFORO_DIR . '/blocks/tags';
        $asset_file  = include( $dir . '/index.asset.php' );
        $handle_tags = 'wpforo-tags-editor-script';

        wp_enqueue_script(
            $handle_tags,
            WPFORO_URL . '/blocks/tags/index.js',
            $asset_file['dependencies'],
            $asset_file['version']
        );

        wp_enqueue_style(
            'wpforo-tags-editor-style',
            WPFORO_URL . '/blocks/online-members/editor.css',
            [],
            $asset_file['version']
        );

        $dir           = WPFORO_DIR . '/blocks/forums';
        $asset_file    = include( $dir . '/index.asset.php' );
        $handle_forums = 'wpforo-forums-editor-script';

        wp_enqueue_script(
            $handle_forums,
            WPFORO_URL . '/blocks/forums/index.js',
            $asset_file['dependencies'],
            $asset_file['version']
        );

        wp_enqueue_style(
            'wpforo-forums-editor-style',
            WPFORO_URL . '/blocks/online-members/editor.css',
            [],
            $asset_file['version']
        );

        $dir            = WPFORO_DIR . '/blocks/profile';
        $asset_file     = include( $dir . '/index.asset.php' );
        $handle_profile = 'wpforo-profile-editor-script';

        wp_enqueue_script(
            $handle_profile,
            WPFORO_URL . '/blocks/profile/index.js',
            $asset_file['dependencies'],
            $asset_file['version']
        );

        wp_enqueue_style(
            'wpforo-profile-editor-style',
            WPFORO_URL . '/blocks/online-members/editor.css',
            [],
            $asset_file['version']
        );

        $dir                 = WPFORO_DIR . '/blocks/recent-posts';
        $asset_file          = include( $dir . '/index.asset.php' );
        $handle_recent_posts = 'wpforo-recent-posts-editor-script';

        wp_enqueue_script(
            $handle_recent_posts,
            WPFORO_URL . '/blocks/recent-posts/index.js',
            $asset_file['dependencies'],
            $asset_file['version']
        );

        wp_enqueue_style(
            'wpforo-recent-posts-editor-style',
            WPFORO_URL . '/blocks/online-members/editor.css',
            [],
            $asset_file['version']
        );

        $dir                  = WPFORO_DIR . '/blocks/recent-topics';
        $asset_file           = include( $dir . '/index.asset.php' );
        $handle_recent_topics = 'wpforo-recent-topics-editor-script';

        wp_enqueue_script(
            $handle_recent_topics,
            WPFORO_URL . '/blocks/recent-topics/index.js',
            $asset_file['dependencies'],
            $asset_file['version']
        );

        wp_enqueue_style(
            'wpforo-recent-topics-editor-style',
            WPFORO_URL . '/blocks/online-members/editor.css',
            [],
            $asset_file['version']
        );
    }
}
