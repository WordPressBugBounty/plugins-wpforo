<?php

namespace gVectors\News\Services;

use gVectors\News\Config;
use gVectors\News\NewsModule;

// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

/**
 * Explicit opt-in gate (WordPress.org Guideline 7).
 *
 * Shows a one-time notice to users who can activate plugins, explaining what
 * connects to the gVectors server and what data is sent. Until the admin
 * clicks [Enable], no proxy HTTP request is made by this module.
 */
class ConsentService {
    private $config;

    public function __construct( Config $config ) {
        $this->config = $config;
        add_action( 'admin_notices', [ $this, 'render_optin_notice' ] );
        add_action( 'wp_ajax_' . $this->config->get_prefix() . 'news_optin', [ $this, 'ajax_optin' ] );
        add_action( 'wp_ajax_' . $this->config->get_prefix() . 'news_settings', [ $this, 'ajax_settings' ] );
    }

    public function is_enabled(): bool {
        return (bool) get_option( $this->config->get_service_enabled_option(), false );
    }

    public function should_show_optin_notice(): bool {
        return ! $this->is_enabled()
            && ! get_option( $this->config->get_optin_notice_dismissed_option(), false )
            && current_user_can( 'activate_plugins' );
    }

    /**
     * First-activation opt-in notice: what connects, what is sent, why.
     */
    public function render_optin_notice(): void {
        if( ! NewsModule::is_notice_page() ) return;
        if( ! $this->should_show_optin_notice() ) return;
        ?>
        <div class="notice notice-info gvectors-news-optin">
            <p>
                <strong><?php esc_html_e( 'Enable addon news & license reminders?', 'gvectors' ); ?></strong>
            </p>
            <p>
                <?php esc_html_e( 'Get notified about new addons, new features and expiring addon licenses. When enabled, this site connects to the gVectors server once a day and sends: your site address, an anonymous site identifier (a hash), and the slugs and versions of installed gVectors addons. No personal data, email addresses or content is ever sent.', 'gvectors' ); ?>
                <?php
                printf(
                    /* translators: %s: link to the News & Emails settings page */
                    esc_html__( 'You can change this decision or manage email preferences anytime on the %s page.', 'gvectors' ),
                    '<a href="' . esc_url( $this->config->get_settings_page_url() ) . '">' . esc_html__( 'News & Emails', 'gvectors' ) . '</a>'
                );
                ?>
                <a href="<?php echo esc_url( $this->config->get_privacy_policy_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Privacy policy', 'gvectors' ); ?></a>
            </p>
            <p>
                <button type="button" class="button button-primary gvectors-news-optin-enable"><?php esc_html_e( 'Enable', 'gvectors' ); ?></button>
                <button type="button" class="button gvectors-news-optin-later"><?php esc_html_e( 'Not now', 'gvectors' ); ?></button>
            </p>
        </div>
        <?php
    }

    /**
     * AJAX: [Enable] / [Not now] on the opt-in notice.
     */
    public function ajax_optin(): void {
        check_ajax_referer( $this->config->get_nonce_action(), 'nonce' );
        if( ! current_user_can( 'activate_plugins' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied' ], 403 );
        }

        $enable = ! empty( $_POST['enable'] );
        if( $enable ) {
            update_option( $this->config->get_service_enabled_option(), true );
            // First sync right away so news/reminders appear without waiting a day
            if( ! wp_next_scheduled( $this->config->get_cron_hook() ) ) {
                wp_schedule_single_event( time() + 10, $this->config->get_cron_hook() );
            }
        }
        update_option( $this->config->get_optin_notice_dismissed_option(), true );

        wp_send_json_success( [ 'enabled' => $enable ] );
    }

    /**
     * AJAX: master service switch toggle. Category-level control lives in the
     * PrefsService matrices, managed from the News & Emails settings page.
     */
    public function ajax_settings(): void {
        check_ajax_referer( $this->config->get_nonce_action(), 'nonce' );
        if( ! current_user_can( 'activate_plugins' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied' ], 403 );
        }

        if( isset( $_POST['service_enabled'] ) ) {
            update_option( $this->config->get_service_enabled_option(), (bool) (int) $_POST['service_enabled'] );
        }

        wp_send_json_success( [ 'service_enabled' => $this->is_enabled() ] );
    }
}
