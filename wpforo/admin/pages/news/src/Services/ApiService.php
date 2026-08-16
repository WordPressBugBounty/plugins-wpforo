<?php

namespace gVectors\News\Services;

use gVectors\News\Config;
use gVectors\News\NewsModule;

// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

/**
 * Thin HTTP client for the gVectors proxy server (news + at-risk endpoints).
 *
 * IMPORTANT: every method must stay behind the master opt-in — callers pass
 * through ConsentService, and this class re-checks the option as a hard guard
 * so a coding mistake can never produce an outbound request without consent.
 */
class ApiService {
    private $config;
    private $proxy_url;

    public function __construct( Config $config ) {
        $this->config    = $config;
        $this->proxy_url = trailingslashit( $config->get_proxy_server_url() );
    }

    /**
     * GET /news — global news items + the news digest email wrapper template.
     * Returns ['news' => [...], 'email_wrapper' => [...]] or null on failure.
     */
    public function get_news( ?int $since = null ): ?array {
        $query = [];
        if( $since !== null && $since > 0 ) {
            $query['since'] = $since;
        }

        $response = $this->request( 'news', $query );
        if( empty( $response['success'] ) || ! isset( $response['data']['news'] ) || ! is_array( $response['data']['news'] ) ) {
            return null;
        }

        $purchase   = $response['data']['purchase_email'] ?? null;
        $cross_sell = $response['data']['cross_sell'] ?? null;

        $recommendations = [];
        foreach( (array) ( $response['data']['recommendations'] ?? [] ) as $rec ) {
            if( ! is_array( $rec ) || empty( $rec['plugin_slug'] ) || ! is_string( $rec['plugin_slug'] ) ) continue;
            if( ! preg_match( '/^[a-zA-Z0-9_\-]+$/', $rec['plugin_slug'] ) ) continue;
            $recommendations[] = [
                'plugin_slug'  => $rec['plugin_slug'],
                'product_name' => isset( $rec['product_name'] ) && is_string( $rec['product_name'] ) ? $rec['product_name'] : $rec['plugin_slug'],
                'reason'       => isset( $rec['reason'] ) && is_string( $rec['reason'] ) ? $rec['reason'] : '',
            ];
        }

        return [
            'news'            => array_values( array_filter( $response['data']['news'], [ $this, 'is_valid_news_item' ] ) ),
            'email_wrapper'   => $this->sanitize_wrapper( $response['data']['email_wrapper'] ?? null ),
            'purchase_email'  => is_array( $purchase ) && ! empty( $purchase['body_html'] ) && is_string( $purchase['body_html'] ) ? [
                'subject'     => isset( $purchase['subject'] ) && is_string( $purchase['subject'] ) ? $purchase['subject'] : '',
                'body_html'   => $purchase['body_html'],
                'license_row' => isset( $purchase['license_row'] ) && is_string( $purchase['license_row'] ) ? $purchase['license_row'] : '',
            ] : null,
            'recommendations' => $recommendations,
            'cross_sell'      => is_array( $cross_sell ) && ! empty( $cross_sell['section'] ) && is_string( $cross_sell['section'] ) ? [
                'subject' => isset( $cross_sell['subject'] ) && is_string( $cross_sell['subject'] ) ? $cross_sell['subject'] : '',
                'section' => $cross_sell['section'],
                'item'    => isset( $cross_sell['item'] ) && is_string( $cross_sell['item'] ) ? $cross_sell['item'] : '',
            ] : null,
        ];
    }

    /**
     * GET /at-risk-licenses — subscriptions expiring soon with no active billing.
     * The proxy computes phases and pre-builds the reminder email; this client does no date math.
     *
     * @param array $versions plugin_slug => installed version
     */
    public function get_at_risk_licenses( array $versions ): ?array {
        $query = [];
        foreach( $versions as $slug => $version ) {
            $slug = preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $slug );
            if( $slug === '' ) continue;
            $query[ 'versions[' . $slug . ']' ] = substr( (string) $version, 0, 20 );
        }

        $response = $this->request( 'at-risk-licenses', $query );
        if( empty( $response['success'] ) || ! isset( $response['data']['licenses'] ) || ! is_array( $response['data']['licenses'] ) ) {
            return null;
        }

        return [
            'warning_phases' => array_map( 'intval', (array) ( $response['data']['warning_phases'] ?? [] ) ),
            'winback_phases' => array_map( 'intval', (array) ( $response['data']['winback_phases'] ?? [] ) ),
            'licenses'       => $response['data']['licenses'],
            'past_due'       => is_array( $response['data']['past_due'] ?? null ) ? $response['data']['past_due'] : [],
            'active_offers'  => is_array( $response['data']['active_offers'] ?? null ) ? $response['data']['active_offers'] : [],
            'email'          => $this->sanitize_wrapper( $response['data']['email'] ?? null ),
            'winback_email'  => $this->sanitize_wrapper( $response['data']['winback_email'] ?? null ),
            'dunning_email'  => $this->sanitize_wrapper( $response['data']['dunning_email'] ?? null ),
            'offer_email'    => $this->sanitize_wrapper( $response['data']['offer_email'] ?? null ),
        ];
    }

    /**
     * Authenticated GET request to the proxy server.
     * Hard opt-in guard: no consent → no request, ever.
     */
    private function request( string $endpoint, array $query = [] ): array {
        if( ! get_option( $this->config->get_service_enabled_option() ) ) {
            return [ 'success' => false, 'error' => 'Addons service is disabled', 'code' => 'consent_missing' ];
        }

        $url = add_query_arg( $query, $this->proxy_url . ltrim( $endpoint, '/' ) );

        $response = wp_remote_get( $url, [
            'timeout'   => $this->config->get_request_timeout(),
            'sslverify' => true,
            'headers'   => [
                'Accept'           => 'application/json',
                'X-gVectors-Site'  => NewsModule::get_site_domain(),
                'X-gVectors-Token' => NewsModule::get_site_token(),
            ],
        ] );

        if( is_wp_error( $response ) ) {
            $this->log( $endpoint, $response->get_error_message() );

            return [ 'success' => false, 'error' => $response->get_error_message(), 'code' => 'wp_error' ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
            $this->log( $endpoint, 'HTTP ' . $code );

            return [ 'success' => false, 'error' => $data['error'] ?? 'HTTP Error ' . $code, 'code' => $code ];
        }

        return $data;
    }

    /**
     * Schema sanity check for a news item coming from the proxy.
     */
    private function is_valid_news_item( $item ): bool {
        return is_array( $item )
            && ! empty( $item['id'] ) && is_string( $item['id'] )
            && preg_match( '/^[a-f0-9\-]{36}$/', $item['id'] )
            && isset( $item['title'] ) && is_string( $item['title'] )
            && isset( $item['type'] ) && is_string( $item['type'] );
    }

    /**
     * Schema sanity check for email wrapper/body structures.
     */
    private function sanitize_wrapper( $wrapper ): ?array {
        if( ! is_array( $wrapper ) || empty( $wrapper['body_html'] ) || ! is_string( $wrapper['body_html'] ) ) {
            return null;
        }

        return [
            'subject'   => isset( $wrapper['subject'] ) && is_string( $wrapper['subject'] ) ? $wrapper['subject'] : '',
            'body_html' => $wrapper['body_html'],
            'item_card' => isset( $wrapper['item_card'] ) && is_string( $wrapper['item_card'] ) ? $wrapper['item_card'] : '',
        ];
    }

    private function log( string $endpoint, string $message ): void {
        if( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf( '[%snews] %s request failed: %s', $this->config->get_prefix(), $endpoint, $message ) );
        }
    }
}
