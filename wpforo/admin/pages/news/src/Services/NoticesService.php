<?php

namespace gVectors\News\Services;

use gVectors\News\Config;
use gVectors\News\NewsModule;

// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

/**
 * Dismissible admin notices for news items (WordPress.org Guideline 11):
 * non-intrusive, max 3 at once, per-user dismissal stored in user meta.
 */
class NoticesService {
    /** news type → WP notice class */
    private const TYPE_CLASSES = [
        'new_addon'    => 'notice-success',
        'new_feature'  => 'notice-warning',
        'new_version'  => 'notice-warning', // releases may carry required actions — needs attention
        'discount'     => 'notice-success',
        'announcement' => 'notice-info',
    ];

    private $config;
    private $consent;
    private $cron;
    private $prefs;

    public function __construct( Config $config, ConsentService $consent, CronService $cron, PrefsService $prefs ) {
        $this->config  = $config;
        $this->consent = $consent;
        $this->cron    = $cron;
        $this->prefs   = $prefs;

        add_action( 'admin_notices', [ $this, 'render_notices' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_' . $this->config->get_prefix() . 'dismiss_news', [ $this, 'ajax_dismiss' ] );
    }

    /**
     * Non-dismissed, non-expired news items for the current admin, oldest first —
     * filtered through the site + per-admin notices channel matrix
     * (category and installed/not-installed addon relevance).
     */
    public function get_visible_items(): array {
        if( ! $this->consent->is_enabled() || ! current_user_can( 'activate_plugins' ) ) return [];

        $items = $this->cron->get_cached_news();
        if( empty( $items ) ) return [];

        $user_id   = get_current_user_id();
        $dismissed = get_user_meta( $user_id, $this->config->get_dismissed_news_meta(), true );
        $dismissed = is_array( $dismissed ) ? $dismissed : [];
        $now       = time();

        $visible = [];
        foreach( $items as $item ) {
            if( in_array( $item['id'], $dismissed, true ) ) continue;
            if( ! empty( $item['expires_at'] ) && strtotime( $item['expires_at'] ) <= $now ) continue;
            if( ! $this->prefs->news_item_allowed( $item, PrefsService::CHANNEL_NOTICES, $user_id ) ) continue;
            $visible[] = $item;
        }

        // Oldest first, capped (Guideline 11: never flood the admin)
        usort( $visible, function( $a, $b ) {
            return strtotime( $a['published_at'] ?? 'now' ) <=> strtotime( $b['published_at'] ?? 'now' );
        } );

        return array_slice( $visible, 0, $this->config->get_max_notices() );
    }

    public function render_notices(): void {
        // No slug argument: notices show on the admin pages of ANY registered
        // gVectors plugin. This service is shared, so each news item prints
        // exactly once even when several gVectors plugins are installed.
        if( ! NewsModule::is_notice_page() ) return;
        $items = $this->get_visible_items();
        if( empty( $items ) ) return;

        foreach( $items as $item ) {
            $type_class = self::TYPE_CLASSES[ $item['type'] ] ?? 'notice-info';
            ?>
            <div class="notice <?php echo esc_attr( $type_class ); ?> gvectors-news-notice" data-news-id="<?php echo esc_attr( $item['id'] ); ?>">
                <p>
                    <strong><?php echo esc_html( $item['title'] ); ?></strong>
                    <?php if( ! empty( $item['body'] ) ) : ?>
                        &mdash; <?php echo esc_html( wp_trim_words( $item['body'], 40 ) ); ?>
                    <?php endif; ?>
                    <?php if( ! empty( $item['link_url'] ) ) : ?>
                        <a href="<?php echo esc_url( $item['link_url'] ); ?>" target="_blank" rel="noopener">
                            <?php echo esc_html( ! empty( $item['link_label'] ) ? $item['link_label'] : __( 'Learn more', 'gvectors' ) ); ?>
                        </a>
                    <?php endif; ?>
                </p>
                <button type="button" class="notice-dismiss gvectors-news-dismiss">
                    <span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'gvectors' ); ?></span>
                </button>
            </div>
            <?php
        }
    }

    /**
     * Enqueue the tiny dismiss/opt-in script only when this module has something on screen.
     */
    public function enqueue_assets(): void {
        if( ! NewsModule::is_notice_page() ) return;
        if( empty( $this->get_visible_items() ) && ! $this->consent->should_show_optin_notice() ) return;

        // Shared handle/config object — this service exists once no matter how
        // many gVectors plugins are installed, so assets load exactly once.
        $module_url = $this->config->get_news_module_url() . '/admin/assets';
        $module_dir = dirname( __DIR__, 2 ) . '/admin/assets';

        wp_enqueue_style(
            'gvectors-news-admin',
            $module_url . '/css/notices.css',
            [],
            filemtime( $module_dir . '/css/notices.css' )
        );
        wp_enqueue_script(
            'gvectors-news-admin',
            $module_url . '/js/notices.js',
            [ 'jquery' ],
            filemtime( $module_dir . '/js/notices.js' ),
            true
        );
        wp_localize_script( 'gvectors-news-admin', 'gvectorsNews', [
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( $this->config->get_nonce_action() ),
            'ajaxPrefix' => $this->config->get_prefix(),
        ] );
    }

    /**
     * AJAX: dismiss one news notice for the current admin.
     */
    public function ajax_dismiss(): void {
        check_ajax_referer( $this->config->get_nonce_action(), 'nonce' );
        if( ! current_user_can( 'activate_plugins' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied' ], 403 );
        }

        $id = isset( $_POST['news_id'] ) ? sanitize_text_field( wp_unslash( $_POST['news_id'] ) ) : '';
        if( ! preg_match( '/^[a-f0-9\-]{36}$/', $id ) ) {
            wp_send_json_error( [ 'message' => 'Invalid news id' ], 400 );
        }

        $user_id   = get_current_user_id();
        $meta_key  = $this->config->get_dismissed_news_meta();
        $dismissed = get_user_meta( $user_id, $meta_key, true );
        $dismissed = is_array( $dismissed ) ? $dismissed : [];

        $dismissed[] = $id;
        update_user_meta( $user_id, $meta_key, array_values( array_unique( $dismissed ) ) );

        wp_send_json_success();
    }
}
