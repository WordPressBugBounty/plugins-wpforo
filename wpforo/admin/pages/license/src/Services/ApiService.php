<?php

namespace gVectors\License\Services;

// Exit if accessed directly
use gVectors\License\Config;
use gVectors\License\LicenseModule;

if( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles all communication with the gVectors proxy server.
 * NEVER communicates directly with Paddle API - all requests go through the proxy.
 */
class ApiService {
    private $config;
    private $proxy_url;
    private $timeout = 30;
    private $products_transient_name;
    private $all_addons_transient_name;
    private $prices_transient_name;
    
    public function __construct( Config $config ) {
        $this->config                    = $config;
        $this->proxy_url                 = trailingslashit( $this->config->get_proxy_server_url() );
        $this->products_transient_name   = $this->config->get_core_plugin_slug() . '_gvectors_products';
        $this->all_addons_transient_name = $this->config->get_core_plugin_slug() . '_gvectors_all_addons';
        $this->prices_transient_name     = $this->config->get_core_plugin_slug() . '_gvectors_prices_';
    }
    
    /**
     * Get single product details
     */
    public function get_product( string $product_id ): array {
        $data     = $this->get_products();
        $products = $data['products'] ?? $data;
        foreach( $products as $product ) {
            if( $product['id'] === $product_id ) return $product;
        }
        
        return [];
    }
    
    // ==========================================
    // Products
    // ==========================================
    
    /**
     * Get all available gVectors products/addons from the License Server
     */
    public function get_products(): array {
        $cached = get_transient( $this->products_transient_name );
        if( $cached !== false ) return $cached;
        
        $response = $this->request( 'products', [], 'GET' );
        if( ! empty( $response['success'] ) && ! empty( $response['data'] ) ) {
            $data = $response['data'];
            // Handle wrapped response with checkout_mode
            if( isset( $data['products'] ) && is_array( $data['products'] ) ) {
                $result = [
                    'products'      => $data['products'],
                    'checkout_mode' => ! empty( $data['checkout_mode'] ) ? $data['checkout_mode'] : 'both',
                ];
            } else {
                // Backward compatibility: plain array of products
                $result = [
                    'products'      => $data,
                    'checkout_mode' => 'both',
                ];
            }
            set_transient( $this->products_transient_name, $result, 6 * HOUR_IN_SECONDS );
            
            return $result;
        }
        
        return [ 'products' => [], 'checkout_mode' => 'both' ];
    }
    
    /**
     * Make an authenticated request to the proxy server
     */
    private function request( string $endpoint, array $body = [], string $method = 'POST' ): array {
        $url = $this->proxy_url . ltrim( $endpoint, '/' );
        
        $body['site_domain']  = LicenseModule::get_site_domain();
        $body['site_token']   = LicenseModule::get_site_token();
        $body['wp_version']   = get_bloginfo( 'version' );
        $body['core_slug']    = $this->config->get_core_plugin_slug();
        $body['core_version'] = $this->config->get_core_plugin_slug() . '-' . $this->config->get_core_plugin_version();
        $body['php_version']  = phpversion();
        
        $args = [
            'timeout'   => $this->timeout,
            'sslverify' => true,
            'headers'   => [
                'Content-Type'     => 'application/json',
                'Accept'           => 'application/json',
                'X-gVectors-Site'  => LicenseModule::get_site_domain(),
                'X-gVectors-Token' => LicenseModule::get_site_token(),
            ],
        ];
        
        if( $method === 'GET' ) {
            $url            = add_query_arg( $body, $url );
            $args['method'] = 'GET';
            $response       = wp_remote_get( $url, $args );
        } else {
            $args['body']   = wp_json_encode( $body );
            $args['method'] = $method;
            $response       = wp_remote_post( $url, $args );
        }
        
        if( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
                'code'    => 'wp_error',
            ];
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if( $code < 200 || $code >= 300 ) {
            return [
                'success' => false,
                'error'   => $data['error'] ?? 'HTTP Error ' . $code,
                'code'    => $code,
                'data'    => $data,
            ];
        }
        
        if( ! is_array( $data ) ) {
            return [
                'success' => false,
                'error'   => 'Invalid response from server',
                'code'    => 'invalid_response',
            ];
        }
        
        return $data;
    }
    
    /**
     * Get product prices
     */
    public function get_prices( string $product_id ): array {
        $cached = get_transient( $this->prices_transient_name . $product_id );
        if( $cached !== false ) return $cached;
        
        $response = $this->request( 'products/' . $product_id . '/prices', [], 'GET' );
        if( ! empty( $response['success'] ) && ! empty( $response['data'] ) ) {
            set_transient( $this->prices_transient_name . $product_id, $response['data'], 6 * HOUR_IN_SECONDS );
            
            return $response['data'];
        }
        
        return [];
    }
    
    // ==========================================
    // Checkout
    // ==========================================
    
    /**
     * Create a checkout transaction server-side via the proxy.
     * Returns transaction_id for Paddle.js to open the checkout overlay.
     */
    public function check_overlap( string $price_id, string $product_id ): array {
        return $this->request( 'checkout/check-overlap', [
            'price_id'   => $price_id,
            'product_id' => $product_id,
        ] );
    }
    
    public function create_checkout( string $price_id, string $product_id = '', array $custom_data = [], ?array $checkout_options = null ): array {
        $body = [
            'price_id'    => $price_id,
            'product_id'  => $product_id,
            'custom_data' => $custom_data,
        ];
        if( $checkout_options ) {
            $body['checkout_options'] = $checkout_options;
        }
        
        return $this->request( 'checkout', $body );
    }
    
    /**
     * Poll/verify a transaction status after checkout
     */
    public function verify_transaction( string $transaction_id ): array {
        return $this->request( 'verify-transaction', [
            'transaction_id' => $transaction_id,
        ],                     'GET' );
    }

    /**
     * Abandoned-checkout phase check: is the transaction still unpaid?
     * The proxy attaches the phase discount and returns the recovery email.
     */
    public function check_abandoned( string $transaction_id, int $phase_minutes ): array {
        return $this->request( 'abandoned-checkout', [
            'transaction_id' => $transaction_id,
            'phase'          => $phase_minutes,
        ],                     'GET' );
    }
    
    // ==========================================
    // License
    // ==========================================
    
    /**
     * Activate a license key for this site
     */
    public function activate_license( string $license_key, string $product_id = '' ): array {
        return $this->request( 'license/activate', [
            'license_key' => $license_key,
            'product_id'  => $product_id,
        ] );
    }
    
    /**
     * Unified activation — send any key type to license/activate; proxy detects the type.
     * Pass an empty string for $key to trigger activate-by-domain.
     */
    public function activate_unified( string $key, string $product_id = '' ): array {
        return $this->request( 'license/activate', [
            'key'        => $key,
            'product_id' => $product_id,
        ] );
    }
    
    /**
     * Activate licenses by transaction ID (optionally for a specific product)
     */
    public function activate_license_by_transaction( string $transaction_id, string $product_id = '' ): array {
        return $this->request( 'license/activate', [
            'key'        => $transaction_id,
            'product_id' => $product_id,
        ] );
    }
    
    /**
     * Activate all licenses registered for this site's domain
     */
    public function activate_licenses_by_domain(): array {
        return $this->request( 'license/activate', [ 'key' => '' ] );
    }
    
    /**
     * Deactivate a license key for this site
     */
    public function deactivate_license( string $license_key ): array {
        return $this->request( 'license/deactivate', [
            'license_key' => $license_key,
        ] );
    }
    
    /**
     * Validate/check a license key status
     */
    public function validate_license( string $license_key ): array {
        return $this->request( 'license/validate', [
            'license_key' => $license_key,
        ] );
    }
    
    /**
     * Validate multiple license keys in a single request.
     * Returns: { success: true, data: { licenses: { 'gvl_xxx': { status, valid, ... }, ... } } }
     */
    public function validate_licenses_batch( array $license_keys ): array {
        return $this->request( 'license/validate-batch', [
            'license_keys' => $license_keys,
        ] );
    }
    
    // ==========================================
    // Subscription
    // ==========================================
    
    /**
     * Get subscription details
     */
    public function get_subscription( string $subscription_id ): array {
        return $this->request( 'subscription/' . $subscription_id, [], 'GET' );
    }
    
    public function get_license_timeline( string $license_key ): array {
        return $this->request( 'license/timeline', [ 'license_key' => $license_key ] );
    }
    
    /**
     * Cancel a subscription
     */
    public function cancel_subscription( string $subscription_id ): array {
        return $this->request( 'subscription/' . $subscription_id . '/cancel' );
    }
    
    /**
     * Pause a subscription
     */
    public function pause_subscription( string $subscription_id ): array {
        return $this->request( 'subscription/' . $subscription_id . '/pause' );
    }
    
    /**
     * Resume a paused subscription
     */
    public function resume_subscription( string $subscription_id ): array {
        return $this->request( 'subscription/' . $subscription_id . '/resume' );
    }
    
    /**
     * Update subscription payment method
     */
    public function update_subscription_payment( string $subscription_id ): array {
        return $this->request( 'subscription/' . $subscription_id . '/update-payment' );
    }
    
    /**
     * Get Paddle customer portal URL for a subscription
     */
    public function get_subscription_portal_url( string $subscription_id ): array {
        return $this->request( 'subscription/' . $subscription_id . '/portal-url' );
    }
    
    // ==========================================
    // Legacy License (old gVectors license system)
    // ==========================================
    
    /**
     * Check if an addon has a valid legacy license (from the old wp_gvt_activation_keys system)
     * for this site domain. The proxy server checks the old license tables.
     *
     * Returns: { success: true, data: { has_legacy_license: bool, status: 'active'|'expired', expired: bool, ... } }
     */
    public function check_legacy_license( string $plugin_slug ): array {
        return $this->request( 'addon/check-legacy-license', [
            'plugin_slug' => $plugin_slug,
        ] );
    }
    
    /**
     * Batch-check legacy licenses for multiple addon slugs in a single request.
     * More efficient than individual calls during cron verification.
     *
     * Returns: { success: true, data: { addons: { 'slug' => { has_legacy_license: bool, status: ..., ... }, ... } } }
     */
    public function check_legacy_licenses_batch( array $plugin_slugs ): array {
        return $this->request( 'addon/check-legacy-licenses', [
            'plugin_slugs' => $plugin_slugs,
        ] );
    }
    
    /**
     * Migrate an active legacy gVectors license to the new Paddle-based license system.
     * The proxy server creates a new row in the licenses table using the original activation
     * key.  Returns full license data that the client stores in gvectors_licenses so that
     * all future validation and update flows run through the new system.
     */
    public function activate_legacy_license( string $plugin_slug ): array {
        return $this->request( 'addon/activate-legacy-license', [
            'plugin_slug' => $plugin_slug,
        ] );
    }
    
    // ==========================================
    // Addon Updates
    // ==========================================
    
    /**
     * Get the full list of all available addons from the proxy server (installed or not).
     * Returns an array of addons with slug, name, version, description.
     */
    public function get_all_addons(): array {
        $cached = get_transient( $this->all_addons_transient_name );
        if( $cached !== false ) return [ 'success' => true, 'data' => $cached ];
        
        $response = $this->request( 'addon/list', [], 'GET' );
        if( ! empty( $response['success'] ) && ! empty( $response['data']['addons'] ) ) {
            set_transient( $this->all_addons_transient_name, $response['data'], 12 * HOUR_IN_SECONDS );
            
            return $response;
        }
        
        return $response;
    }
    
    // ==========================================
    // Addon Downloads
    // ==========================================
    
    /**
     * Get a signed download URL for an addon
     */
    public function get_addon_download_url( string $product_id, string $license_key ): array {
        return $this->request( 'addon/download-url', [
            'product_id'  => $product_id,
            'license_key' => $license_key,
        ] );
    }
    
    // ==========================================
    // Trial
    // ==========================================
    
    /**
     * Start a free trial for a product
     */
    public function start_trial( string $product_id ): array {
        return $this->request( 'trial/start', [
            'product_id' => $product_id,
        ] );
    }
    
    // ==========================================
    // Account / Customer
    // ==========================================
    
    /**
     * Get customer account info (purchases, subscriptions, licenses)
     */
    public function get_account(): array {
        return $this->request( 'account', [], 'GET' );
    }
    
    /**
     * Clear all cached data
     */
    public function clear_cache(): void {
        delete_transient( $this->products_transient_name );
        delete_transient( $this->all_addons_transient_name );
        
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_' . $this->prices_transient_name . '%',
                '_transient_timeout_' . $this->prices_transient_name . '%'
            )
        );
    }
}
