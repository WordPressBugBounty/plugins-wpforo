<?php

namespace gVectors\News\Services;

use gVectors\News\Config;

// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

/**
 * Daily sync with the proxy server:
 *  1. bail unless the service is enabled (consent gate)
 *  2. fetch news → cache in a transient for the admin notices
 *  3. build the installed-versions map of licensed addons
 *  4. fetch at-risk licenses (proxy computes phases + builds the email)
 *  5. dispatch news digest emails
 *  6. dispatch expiry reminder emails
 */
class CronService {
    private $config;
    private $api;
    private $consent;
    private $email;

    public function __construct( Config $config, ApiService $api, ConsentService $consent, EmailService $email ) {
        $this->config  = $config;
        $this->api     = $api;
        $this->consent = $consent;
        $this->email   = $email;

        add_action( $this->config->get_cron_hook(), [ $this, 'sync' ] );
        add_action( 'init', [ $this, 'maybe_schedule' ] );
    }

    /**
     * Self-healing scheduler: keep the daily event scheduled while the service
     * is enabled, clear it when consent is withdrawn.
     */
    public function maybe_schedule(): void {
        $scheduled = wp_next_scheduled( $this->config->get_cron_hook() );
        if( $this->consent->is_enabled() ) {
            if( ! $scheduled ) {
                wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', $this->config->get_cron_hook() );
            }
        } elseif( $scheduled ) {
            wp_clear_scheduled_hook( $this->config->get_cron_hook() );
        }
    }

    /**
     * Daily sync handler.
     */
    public function sync(): void {
        if( ! $this->consent->is_enabled() ) return;

        // 1. News → transient (admin notices read from here; no request per pageload)
        $news_payload = $this->api->get_news();
        if( $news_payload !== null ) {
            $news_payload['fetched_at'] = time();
            set_transient( $this->config->get_news_transient(), $news_payload, $this->config->get_news_cache_ttl() );
        }

        // 2. At-risk licenses (only when there are licensed addons to report on)
        $at_risk  = null;
        $versions = $this->get_installed_versions_map();
        if( ! empty( $versions ) ) {
            $at_risk = $this->api->get_at_risk_licenses( $versions );
        }

        // 3. Emails — EmailService filters through the site + per-admin
        //    channel/category matrix (PrefsService), so no gating needed here.
        if( $news_payload !== null ) {
            $this->email->send_news_digest( $news_payload );
        }
        if( $at_risk !== null ) {
            $this->email->send_expiry_reminders( $at_risk );  // upcoming expirations (phase > 0)
            $this->email->send_winback_reminders( $at_risk ); // already expired (phase < 0)
            $this->email->send_dunning_notices( $at_risk );   // failed payments (past_due)
            $this->email->send_offer_reminders( $at_risk );   // unused personal discounts
        }
    }

    /**
     * Cached news payload for the notices renderer.
     */
    public function get_cached_news(): array {
        $payload = get_transient( $this->config->get_news_transient() );

        return is_array( $payload ) && ! empty( $payload['news'] ) && is_array( $payload['news'] ) ? $payload['news'] : [];
    }

    /**
     * plugin_slug => installed version map for every addon ANY registered
     * gVectors plugin has a local license for. Licenses are gVectors-wide, so
     * the map merges the license storage of every plugin context (wpForo
     * addon licenses + wpDiscuz addon licenses + ...), and the proxy answers
     * with at-risk licenses for all of them in one request.
     */
    private function get_installed_versions_map(): array {
        $slugs = [];
        foreach( \gVectors\News\NewsModule::get_contexts() as $config ) {
            $licenses = get_option( $config->get_licenses_option(), [] );
            if( ! is_array( $licenses ) ) continue;
            foreach( $licenses as $license ) {
                if( is_array( $license ) && ! empty( $license['plugin_slug'] ) && is_string( $license['plugin_slug'] ) ) {
                    $slugs[ $license['plugin_slug'] ] = true;
                }
            }
        }
        if( empty( $slugs ) ) return [];

        if( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $versions = [];
        foreach( get_plugins() as $plugin_file => $plugin_data ) {
            $dir = dirname( $plugin_file );
            if( isset( $slugs[ $dir ] ) ) {
                $versions[ $dir ] = (string) ( $plugin_data['Version'] ?? '' );
            }
        }

        // Licensed but not installed addons are still reported (version unknown) so
        // the proxy can include them in expiry reminders.
        foreach( array_keys( $slugs ) as $slug ) {
            if( ! isset( $versions[ $slug ] ) ) {
                $versions[ $slug ] = '';
            }
        }

        return $versions;
    }
}
