<?php

namespace gVectors\License\Services;

// Exit if accessed directly

use gVectors\License\Config;

if( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX action handlers for the Paddle module.
 * All admin AJAX endpoints for products, checkout, license, addon management.
 */
class ActionsService {
    public  $addonsService;
    private $config;
    private $pending_transactions_option_name;
    
    public function __construct( Config $config, AddonsService $addonsService ) {
        $this->addonsService                    = $addonsService;
        $this->config                           = $config;
        $this->pending_transactions_option_name = $this->config->get_core_plugin_slug() . '_gvectors_pending_transactions';
        $this->init_hooks();
    }
    
    private function init_hooks() {
        $p = 'wp_ajax_' . $this->config->get_core_plugin_slug() . '_gvectors_';

        // Product listing
        add_action( $p . 'get_products', [ $this, 'ajax_get_products' ] );
        add_action( $p . 'get_prices', [ $this, 'ajax_get_prices' ] );

        // Checkout
        add_action( $p . 'check_overlap', [ $this, 'ajax_check_overlap' ] );
        add_action( $p . 'create_checkout', [ $this, 'ajax_create_checkout' ] );
        add_action( $p . 'verify_transaction', [ $this, 'ajax_verify_transaction' ] );

        // License
        add_action( $p . 'activate_license', [ $this, 'ajax_activate_license' ] );
        add_action( $p . 'activate_by_transaction', [ $this, 'ajax_activate_by_transaction' ] );
        add_action( $p . 'unified_activate', [ $this, 'ajax_unified_activate' ] );
        add_action( $p . 'activate_by_domain', [ $this, 'ajax_activate_by_domain' ] );
        add_action( $p . 'deactivate_license', [ $this, 'ajax_deactivate_license' ] );
        add_action( $p . 'validate_license', [ $this, 'ajax_validate_license' ] );
        add_action( $p . 'get_licenses', [ $this, 'ajax_get_licenses' ] );

        // Addons
        add_action( $p . 'install_addon', [ $this, 'ajax_install_addon' ] );
        add_action( $p . 'activate_addon', [ $this, 'ajax_activate_addon' ] );
        add_action( $p . 'deactivate_addon', [ $this, 'ajax_deactivate_addon' ] );
        add_action( $p . 'install_activate_addon', [ $this, 'ajax_install_activate_addon' ] );

        // Subscription
        add_action( $p . 'cancel_subscription', [ $this, 'ajax_cancel_subscription' ] );
        add_action( $p . 'resume_subscription', [ $this, 'ajax_resume_subscription' ] );
        add_action( $p . 'update_payment', [ $this, 'ajax_update_payment' ] );
        add_action( $p . 'get_portal_url', [ $this, 'ajax_get_portal_url' ] );

        // Trial
        add_action( $p . 'start_trial', [ $this, 'ajax_start_trial' ] );

        // Account
        add_action( $p . 'get_account', [ $this, 'ajax_get_account' ] );

        // Pending transactions
        add_action( $p . 'save_pending_transaction', [ $this, 'ajax_save_pending_transaction' ] );
        add_action( $p . 'get_pending_transactions', [ $this, 'ajax_get_pending_transactions' ] );
        add_action( $p . 'clear_pending_transaction', [ $this, 'ajax_clear_pending_transaction' ] );

        // Timeline
        add_action( $p . 'get_timeline', [ $this, 'ajax_get_timeline' ] );

        // Cache
        add_action( $p . 'clear_cache', [ $this, 'ajax_clear_cache' ] );

        // Abandoned checkout recovery: scheduled per-phase checks (shared,
        // unprefixed hook — a static guard in the handler prevents double
        // processing when several gVectors plugins carry this module)
        add_action( 'gvectors_abandoned_checkout_check', [ $this, 'handle_abandoned_check' ], 10, 3 );
    }
    
    public function ajax_get_products(): void {
        if( ! $this->verify_admin() ) return;
        
        $result        = $this->addonsService->licenseService->apiService->get_products();
        $products      = $result['products'] ?? [];
        $checkout_mode = $result['checkout_mode'] ?? 'both';
        
        if( ! empty( $products ) ) {
            // Build a slug-to-product map for resolving bundle addon names
            $slug_to_name = [];
            foreach( $products as $p ) {
                if( $p['is_saas'] ) continue;
                if( ! empty( $p['plugin_slug'] ) ) {
                    $slug_to_name[ $p['plugin_slug'] ] = $p['name'];
                }
            }
            
            // Enrich with a local license /addon status
            foreach( $products as $k => &$product ) {
                if( $product['is_saas'] ) {
                    unset( $products[ $k ] );
                    continue;
                }
                $pid       = $product['id'];
                $is_bundle = ! empty( $product['is_bundle'] );
                
                // For bundle products, check if all included addons are licensed
                if( $is_bundle && ! empty( $product['bundle_slugs'] ) ) {
                    $bundle_all_licensed = true;
                    $bundle_addon_names  = [];
                    foreach( $product['bundle_slugs'] as $bslug ) {
                        $bundle_addon_names[] = $slug_to_name[ $bslug ] ?? $bslug;
                        // Find the individual product for this slug to check its license
                        $slug_licensed = false;
                        foreach( $products as $sp ) {
                            if( ! empty( $sp['plugin_slug'] ) && $sp['plugin_slug'] === $bslug ) {
                                if( $this->addonsService->licenseService->is_active( $sp['id'] ) ) {
                                    $slug_licensed = true;
                                }
                                break;
                            }
                        }
                        if( ! $slug_licensed ) {
                            $bundle_all_licensed = false;
                        }
                    }
                    $product['bundle_all_licensed'] = $bundle_all_licensed;
                    $product['bundle_addon_names']  = $bundle_addon_names;
                }
                
                $product['license_status'] = $this->addonsService->licenseService->get_status_label( $pid );
                $product['is_licensed']    = $this->addonsService->licenseService->is_active( $pid );
                $product['is_trial']       = $this->addonsService->licenseService->is_trial( $pid );
                $slug                      = $product['plugin_slug'] ?? '';
                $product['addon_status']   = ( $slug && ! $is_bundle ) ? $this->addonsService->get_status( $slug ) : 'not_installed';
                
                // Attach active license info for display and manage modal
                $license                           = $this->addonsService->licenseService->get( $pid );
                $product['active_plan_name']       = ! empty( $license['plan_name'] ) ? $license['plan_name'] : '';
                $product['active_price_formatted'] = '';
                $product['active_price_interval']  = '';
                
                // Pass full license details for the manage modal
                $product['license_key']     = ! empty( $license['license_key'] ) ? $license['license_key'] : '';
                $product['plan_name']       = ! empty( $license['plan_name'] ) ? $license['plan_name'] : '';
                $product['subscription_id'] = ! empty( $license['subscription_id'] ) ? $license['subscription_id'] : '';
                $product['customer_id']     = ! empty( $license['customer_id'] ) ? $license['customer_id'] : '';
                $product['expires_at']      = ! empty( $license['expires_at'] ) ? $license['expires_at'] : '';
                $product['activated_at']    = ! empty( $license['activated_at'] ) ? $license['activated_at'] : '';
                $product['max_sites']       = ! empty( $license['max_sites'] ) ? (int) $license['max_sites'] : 0;
                $product['activated_sites'] = ! empty( $license['activated_sites'] ) ? $license['activated_sites'] : [];
                $product['product_name']    = ! empty( $license['product_name'] ) ? $license['product_name'] : '';
                $product['last_validated']  = ! empty( $license['last_validated'] ) ? (int) $license['last_validated'] : 0;
                
                if( ( $product['is_licensed'] || $product['is_trial'] ) && ! empty( $product['prices'] ) ) {
                    // Try to find the matching price by plan_name or use the first price
                    $matched_price = null;
                    if( ! empty( $license['plan_name'] ) ) {
                        foreach( $product['prices'] as $pr ) {
                            if( isset( $pr['name'] ) && $pr['name'] === $license['plan_name'] ) {
                                $matched_price = $pr;
                                break;
                            }
                        }
                    }
                    if( ! $matched_price ) {
                        $matched_price = $product['prices'][0];
                    }
                    $product['active_price_formatted'] = $matched_price['formatted_price'] ?? '';
                    $product['active_price_interval']  = $matched_price['interval_label'] ?? '';
                }
            }
            unset( $product );
            $products = array_values( $products );
            wp_send_json_success( [ 'products' => $products, 'checkout_mode' => $checkout_mode ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Failed to load products', 'gvectors' ) ] );
        }
    }
    
    private function verify_admin(): bool {
        if( ! current_user_can( 'administrator' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'gvectors' ) ] );
            
            return false;
        }
        if( ! check_ajax_referer( $this->config->get_core_plugin_slug() . '_gvectors_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed', 'gvectors' ) ] );
            
            return false;
        }
        
        return true;
    }
    
    // ==========================================
    // Products
    // ==========================================
    
    public function ajax_get_prices(): void {
        if( ! $this->verify_admin() ) return;
        
        $product_id = isset( $_POST['product_id'] ) ? sanitize_text_field( $_POST['product_id'] ) : '';
        if( empty( $product_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Product ID required', 'gvectors' ) ] );
            
            return;
        }
        
        $prices = $this->addonsService->licenseService->apiService->get_prices( $product_id );
        if( ! empty( $prices ) ) {
            wp_send_json_success( $prices );
        } else {
            wp_send_json_error( [ 'message' => __( 'Failed to load prices', 'gvectors' ) ] );
        }
    }
    
    public function ajax_check_overlap(): void {
        if( ! $this->verify_admin() ) return;
        
        $price_id   = isset( $_POST['price_id'] ) ? sanitize_text_field( $_POST['price_id'] ) : '';
        $product_id = isset( $_POST['product_id'] ) ? sanitize_text_field( $_POST['product_id'] ) : '';
        
        if( empty( $price_id ) || empty( $product_id ) ) {
            wp_send_json_success( [ 'overlap_type' => 'none', 'message' => '', 'overlapping_licenses' => [] ] );
            
            return;
        }
        
        $response = $this->addonsService->licenseService->apiService->check_overlap( $price_id, $product_id );
        if( ! empty( $response['success'] ) ) {
            wp_send_json_success( $response['data'] );
        } else {
            $error = $response['error'] ?? __( 'Failed to check overlap', 'gvectors' );
            wp_send_json_error( [ 'message' => $error ] );
        }
    }
    
    // ==========================================
    // Checkout
    // ==========================================
    
    public function ajax_create_checkout(): void {
        if( ! $this->verify_admin() ) return;
        
        $price_id = isset( $_POST['price_id'] ) ? sanitize_text_field( $_POST['price_id'] ) : '';
        if( empty( $price_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Price ID required', 'gvectors' ) ] );
            
            return;
        }
        
        $product_id = isset( $_POST['product_id'] ) ? sanitize_text_field( $_POST['product_id'] ) : '';
        
        $custom_data      = [];
        $checkout_options = null;
        if( ! empty( $_POST['checkout_options'] ) && is_array( $_POST['checkout_options'] ) ) {
            $checkout_options = $this->sanitize_checkout_options( $_POST['checkout_options'] );
        }
        
        $response = $this->addonsService->licenseService->apiService->create_checkout( $price_id, $product_id, $custom_data, $checkout_options );
        if( ! empty( $response['success'] ) ) {
            $this->schedule_abandoned_checks( is_array( $response['data'] ?? null ) ? $response['data'] : [] );
            wp_send_json_success( $response['data'] );
        } else {
            $error = $response['error'] ?? __( 'Failed to create checkout', 'gvectors' );
            wp_send_json_error( [ 'message' => $error ] );
        }
    }

    /**
     * Abandoned checkout recovery: schedule one check per phase the proxy
     * announced in the checkout response (`abandoned_phases`, minute offsets
     * from ABANDONED_CHECKOUT_PHASES — empty when the feature is disabled,
     * so nothing is ever scheduled).
     *
     * The purchaser's user id rides along in the event args: it is known HERE,
     * at creation time, and must not depend on the pending-transactions entry
     * (saved by a later AJAX call the buyer may never reach).
     */
    private function schedule_abandoned_checks( array $data ): void {
        if( empty( $data['transaction_id'] ) || empty( $data['abandoned_phases'] ) || ! is_array( $data['abandoned_phases'] ) ) return;

        $transaction_id = sanitize_text_field( (string) $data['transaction_id'] );
        $phases         = [];
        foreach( $data['abandoned_phases'] as $minutes ) {
            $minutes = (int) $minutes;
            if( $minutes < 1 || $minutes > 20160 ) continue; // 1 min .. 14 days
            $phases[] = $minutes;
            $args = [ $transaction_id, $minutes, get_current_user_id() ];
            // Reused pending transactions (15-min checkout reuse) would schedule
            // identical events — args-exact dedup keeps one check per phase.
            if( ! wp_next_scheduled( 'gvectors_abandoned_checkout_check', $args ) ) {
                wp_schedule_single_event( time() + $minutes * MINUTE_IN_SECONDS, 'gvectors_abandoned_checkout_check', $args );
            }
        }

        // Persist the phases in the pending entry: WP-Cron single events can be
        // lost (parallel requests racing on the cron option, or consumed by a
        // failed run) — heal_abandoned_checks() re-creates missing ones from
        // this record on every dashboard load.
        if( ! empty( $phases ) ) {
            $pending = get_option( $this->pending_transactions_option_name, [] );
            if( ! is_array( $pending ) ) $pending = [];
            $entry = isset( $pending[ $transaction_id ] ) && is_array( $pending[ $transaction_id ] ) ? $pending[ $transaction_id ] : [];
            $pending[ $transaction_id ] = [
                'time'    => (int) ( $entry['time'] ?? time() ),
                'user_id' => (int) ( $entry['user_id'] ?? get_current_user_id() ),
                'phases'  => $phases,
            ];
            update_option( $this->pending_transactions_option_name, $pending );
        }
    }

    /**
     * Self-healing for abandoned-checkout checks (mirrors the news module's
     * self-healing cron scheduler): pending entries remember their phases, so
     * any check event that went missing — WP cron-array write races between
     * parallel dashboard requests, or an event consumed by a run that errored
     * before emailing — is re-scheduled here. Runs on every dashboard load via
     * ajax_get_pending_transactions; overdue phases are scheduled to fire in
     * ~2 minutes (the news module's per-phase dedup makes re-runs harmless).
     */
    private function heal_abandoned_checks( array $pending ): void {
        foreach( $pending as $transaction_id => $entry ) {
            if( ! is_array( $entry ) || empty( $entry['phases'] ) || ! is_array( $entry['phases'] ) ) continue;
            $created = (int) ( $entry['time'] ?? 0 );
            $user_id = (int) ( $entry['user_id'] ?? 0 );
            if( $created <= 0 ) continue;

            foreach( $entry['phases'] as $minutes ) {
                $minutes = (int) $minutes;
                if( $minutes < 1 || $minutes > 20160 ) continue;
                $args = [ (string) $transaction_id, $minutes, $user_id ];
                if( wp_next_scheduled( 'gvectors_abandoned_checkout_check', $args ) ) continue;

                $due = $created + $minutes * MINUTE_IN_SECONDS;
                if( $due <= time() ) {
                    // The event is gone AND its time has passed: it may have been
                    // lost before running, or run without sending. Re-fire soon —
                    // proxy status + email dedup decide whether anything happens.
                    $due = time() + 2 * MINUTE_IN_SECONDS;
                }
                wp_schedule_single_event( $due, 'gvectors_abandoned_checkout_check', $args );
            }
        }
    }

    /**
     * Scheduled abandoned-checkout check: ask the proxy whether the transaction
     * is still unpaid. The proxy is authoritative (it also attaches the phase
     * discount and builds the email); if still pending, hand the email to the
     * news module via the shared action — it emails the recorded purchaser.
     */
    public function handle_abandoned_check( $transaction_id, $minutes, $purchaser_id = 0 ): void {
        $transaction_id = sanitize_text_field( (string) $transaction_id );
        $minutes        = (int) $minutes;
        if( $transaction_id === '' || $minutes <= 0 ) return;

        // Every gVectors plugin's license module listens on this shared hook —
        // process each (transaction, phase) once per request.
        static $handled = [];
        $key = $transaction_id . ':' . $minutes;
        if( isset( $handled[ $key ] ) ) return;
        $handled[ $key ] = true;

        $response = $this->addonsService->licenseService->apiService->check_abandoned( $transaction_id, $minutes );
        if( empty( $response['success'] ) ) return;

        $data = is_array( $response['data'] ?? null ) ? $response['data'] : [];
        if( ( $data['status'] ?? '' ) !== 'pending' || empty( $data['email']['body_html'] ) ) return;

        do_action( 'gvectors_abandoned_checkout_email', $transaction_id, $data['email'], (int) $purchaser_id, $minutes, $this->config->get_core_plugin_slug() );
    }
    
    /**
     * Sanitize the checkout_options structure from JS.
     */
    private function sanitize_checkout_options( array $raw ): array {
        $options = [
            'site_domain'   => sanitize_text_field( $raw['site_domain'] ?? '' ),
            'decided_at'    => sanitize_text_field( $raw['decided_at'] ?? '' ),
            'overlap_type'  => sanitize_text_field( $raw['overlap_type'] ?? '' ),
            'subscriptions' => [],
        ];
        
        if( ! empty( $raw['subscriptions'] ) && is_array( $raw['subscriptions'] ) ) {
            foreach( $raw['subscriptions'] as $sub_id => $sub ) {
                if( ! is_array( $sub ) ) continue;
                $clean_sub_id                              = sanitize_text_field( $sub_id );
                $options['subscriptions'][ $clean_sub_id ] = [
                    'action'          => sanitize_text_field( $sub['action'] ?? '' ),
                    'product_id'      => sanitize_text_field( $sub['product_id'] ?? '' ),
                    'product_name'    => sanitize_text_field( $sub['product_name'] ?? '' ),
                    'price_id'        => sanitize_text_field( $sub['price_id'] ?? '' ),
                    'license_keys'    => ! empty( $sub['license_keys'] ) && is_array( $sub['license_keys'] )
                        ? array_map( 'sanitize_text_field', $sub['license_keys'] )
                        : [],
                    'deactivate_keys' => ! empty( $sub['deactivate_keys'] ) && is_array( $sub['deactivate_keys'] )
                        ? array_map( 'sanitize_text_field', $sub['deactivate_keys'] )
                        : [],
                ];
            }
        }
        
        return $options;
    }
    
    public function ajax_verify_transaction(): void {
        if( ! $this->verify_admin() ) return;
        
        $transaction_id = isset( $_POST['transaction_id'] ) ? sanitize_text_field( $_POST['transaction_id'] ) : '';
        if( empty( $transaction_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Transaction ID required', 'gvectors' ) ] );
            
            return;
        }
        
        $response = $this->addonsService->licenseService->apiService->verify_transaction( $transaction_id );
        if( ! empty( $response['success'] ) && ! empty( $response['data'] ) ) {
            $data = $response['data'];
            
            // If completed, save the license(s) locally
            if( $data['status'] === 'completed' ) {
                $saved_licenses = [];
                // Bundle: multiple licenses
                if( ! empty( $data['is_bundle'] ) && ! empty( $data['licenses'] ) ) {
                    foreach( $data['licenses'] as $license ) {
                        $pid = ! empty( $license['product_id'] ) ? $license['product_id'] : '';
                        if( $pid ) {
                            $this->addonsService->licenseService->save( $pid, $license );
                            $saved_licenses[] = $license;
                        }
                    }
                } elseif( ! empty( $data['license'] ) ) {
                    // Single license
                    $license = $data['license'];
                    $pid     = ! empty( $license['product_id'] ) ? $license['product_id'] : '';
                    if( $pid ) {
                        $this->addonsService->licenseService->save( $pid, $license );
                        $saved_licenses[] = $license;
                    }
                }

                /**
                 * Fires after a completed checkout transaction activated license(s)
                 * on this site (dashboard polling of pending transactions).
                 * The gVectors News module listens to send the purchase/keys
                 * confirmation email to the purchasing administrator.
                 *
                 * @param string $transaction_id Paddle transaction id
                 * @param array  $saved_licenses License data arrays (license_key, product_name, expires_at, ...)
                 * @param string $core_slug      Host plugin slug (wpforo, wpdiscuz, ...)
                 * @param int    $purchaser_id   User id of the admin who opened the checkout
                 */
                if( ! empty( $saved_licenses ) ) {
                    // The verification polling may be executed by ANY admin whose
                    // dashboard is open — resolve the REAL purchaser from the
                    // pending-transactions record (captured at checkout time).
                    // Fallback: the current user (covers the same-session flow).
                    $pending       = get_option( $this->pending_transactions_option_name, [] );
                    $pending_entry = is_array( $pending ) ? ( $pending[ $transaction_id ] ?? null ) : null;
                    $purchaser_id  = is_array( $pending_entry ) && ! empty( $pending_entry['user_id'] )
                        ? (int) $pending_entry['user_id']
                        : get_current_user_id();

                    do_action( 'gvectors_transaction_licenses_activated', $transaction_id, $saved_licenses, $this->config->get_core_plugin_slug(), $purchaser_id );
                }
            }
            
            wp_send_json_success( $data );
        } else {
            $error = $response['error'] ?? __( 'Failed to verify transaction', 'gvectors' );
            wp_send_json_error( [ 'message' => $error ] );
        }
    }
    
    // ==========================================
    // License
    // ==========================================
    
    public function ajax_activate_license(): void {
        if( ! $this->verify_admin() ) return;
        
        $license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( $_POST['license_key'] ) : '';
        $product_id  = isset( $_POST['product_id'] ) ? sanitize_text_field( $_POST['product_id'] ) : '';
        
        if( empty( $license_key ) ) {
            wp_send_json_error( [ 'message' => __( 'License key required', 'gvectors' ) ] );
            
            return;
        }
        
        $response = $this->addonsService->licenseService->activate( $license_key, $product_id );
        if( ! empty( $response['success'] ) ) {
            // Revalidate all licenses to clear expired notices for newly purchased/renewed addons
            $this->addonsService->check_all_license_validity();
            wp_send_json_success( [
                                      'license' => $response['data'],
                                      'message' => __( 'License activated successfully', 'gvectors' ),
                                  ] );
        } else {
            $error = $response['error'] ?? __( 'Failed to activate license', 'gvectors' );
            wp_send_json_error( [ 'message' => $error ] );
        }
    }
    
    public function ajax_activate_by_transaction(): void {
        if( ! $this->verify_admin() ) return;
        
        $transaction_id = isset( $_POST['transaction_id'] ) ? sanitize_text_field( $_POST['transaction_id'] ) : '';
        $product_id     = isset( $_POST['product_id'] ) ? sanitize_text_field( $_POST['product_id'] ) : '';
        
        if( empty( $transaction_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Transaction ID required', 'gvectors' ) ] );
            
            return;
        }
        
        $response = $this->addonsService->licenseService->activate_by_transaction( $transaction_id, $product_id );
        if( ! empty( $response['success'] ) ) {
            $this->addonsService->check_all_license_validity();
            wp_send_json_success( [
                                      'activated' => $response['data']['activated'] ?? [],
                                      'errors'    => $response['data']['errors'] ?? [],
                                      'message'   => $response['data']['message'] ?? __( 'Licenses activated successfully', 'gvectors' ),
                                  ] );
        } else {
            $error = $response['error'] ?? __( 'Failed to activate licenses by transaction', 'gvectors' );
            wp_send_json_error( [ 'message' => $error ] );
        }
    }
    
    public function ajax_unified_activate(): void {
        if( ! $this->verify_admin() ) return;
        
        // Empty key = activate-by-domain (proxy auto-detects all key types)
        $key        = isset( $_POST['key'] ) ? sanitize_text_field( $_POST['key'] ) : '';
        $product_id = isset( $_POST['product_id'] ) ? sanitize_text_field( $_POST['product_id'] ) : '';
        
        $response = $this->addonsService->licenseService->activate_unified( $key, $product_id );
        
        if( ! empty( $response['success'] ) ) {
            $this->addonsService->check_all_license_validity();
            // Multi-license response (txn_* or by-domain)
            if( ! empty( $response['data']['activated'] ) ) {
                wp_send_json_success( [
                                          'activated' => $response['data']['activated'],
                                          'errors'    => $response['data']['errors'] ?? [],
                                          'message'   => $response['data']['message'] ?? __( 'Licenses activated successfully', 'gvectors' ),
                                      ] );
            } else {
                // Single license response (gvl_* or legacy key)
                wp_send_json_success( [
                                          'license' => $response['data'],
                                          'message' => __( 'License activated successfully', 'gvectors' ),
                                      ] );
            }
            
            return;
        }
        
        wp_send_json_error( [ 'message' => $response['error'] ?? __( 'Could not activate. Please check your key.', 'gvectors' ) ] );
    }
    
    public function ajax_activate_by_domain(): void {
        if( ! $this->verify_admin() ) return;
        
        $response = $this->addonsService->licenseService->activate_by_domain();
        if( ! empty( $response['success'] ) ) {
            $this->addonsService->check_all_license_validity();
            wp_send_json_success( [
                                      'activated' => $response['data']['activated'] ?? [],
                                      'errors'    => $response['data']['errors'] ?? [],
                                      'message'   => $response['data']['message'] ?? __( 'Licenses activated successfully', 'gvectors' ),
                                  ] );
        } else {
            wp_send_json_error( [ 'message' => $response['error'] ?? __( 'No licenses found for this domain', 'gvectors' ) ] );
        }
    }
    
    public function ajax_deactivate_license(): void {
        if( ! $this->verify_admin() ) return;
        
        $product_id = isset( $_POST['product_id'] ) ? sanitize_text_field( $_POST['product_id'] ) : '';
        if( empty( $product_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Product ID required', 'gvectors' ) ] );
            
            return;
        }
        
        $response = $this->addonsService->licenseService->deactivate( $product_id );
        if( ! empty( $response['success'] ) ) {
            wp_send_json_success( [ 'message' => __( 'License deactivated', 'gvectors' ) ] );
        } else {
            $error = $response['error'] ?? __( 'Failed to deactivate license', 'gvectors' );
            wp_send_json_error( [ 'message' => $error ] );
        }
    }
    
    public function ajax_validate_license(): void {
        if( ! $this->verify_admin() ) return;
        
        // Batch-validates ALL licenses in one API call (or uses cache).
        // product_id is optional — when omitted, returns a summary for all.
        $product_id = isset( $_POST['product_id'] ) ? sanitize_text_field( $_POST['product_id'] ) : '';
        $result     = $this->addonsService->licenseService->validate_with_cache( $product_id );
        
        $all_licenses = $result['all_licenses'] ?? $this->addonsService->licenseService->get_all();
        
        // ── Server unavailable — local state preserved ──
        if( $result['reason'] === 'server_unavailable' ) {
            wp_send_json_success( [
                                      'valid'        => false,
                                      'reason'       => 'server_unavailable',
                                      'message'      => __( 'Could not reach the license server. Your current license status is preserved. Will retry automatically.', 'gvectors' ),
                                      'all_licenses' => $all_licenses,
                                  ] );
            
            return;
        }
        
        // Build response message
        if( $result['valid'] ) {
            $message = __( 'All licenses validated successfully', 'gvectors' );
        } elseif( $result['reason'] === 'some_invalid' ) {
            $message = __( 'Validation complete. Some licenses have issues — check the status column below.', 'gvectors' );
        } else {
            $message = __( 'All licenses validated', 'gvectors' );
        }
        
        wp_send_json_success( [
                                  'valid'        => $result['valid'],
                                  'reason'       => $result['reason'],
                                  'message'      => $message,
                                  'all_licenses' => $all_licenses,
                                  'cached'       => ! empty( $result['cached'] ),
                              ] );
    }
    
    public function ajax_get_licenses(): void {
        if( ! $this->verify_admin() ) return;
        wp_send_json_success( $this->addonsService->licenseService->get_all() );
    }
    
    // ==========================================
    // Addons
    // ==========================================
    
    public function ajax_install_addon(): void {
        if( ! $this->verify_admin() ) return;
        
        $product_id = isset( $_POST['product_id'] ) ? sanitize_text_field( $_POST['product_id'] ) : '';
        if( empty( $product_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Product ID required', 'gvectors' ) ] );
            
            return;
        }
        
        $result = $this->addonsService->install( $product_id );
        if( ! empty( $result['success'] ) ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( [ 'message' => $result['error'] ?? __( 'Installation failed', 'gvectors' ) ] );
        }
    }
    
    public function ajax_activate_addon(): void {
        if( ! $this->verify_admin() ) return;
        
        $plugin_file = isset( $_POST['plugin_file'] ) ? sanitize_text_field( $_POST['plugin_file'] ) : '';
        if( empty( $plugin_file ) ) {
            wp_send_json_error( [ 'message' => __( 'Plugin file required', 'gvectors' ) ] );
            
            return;
        }
        
        $result = $this->addonsService->activate( $plugin_file );
        if( ! empty( $result['success'] ) ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( [ 'message' => $result['error'] ?? __( 'Activation failed', 'gvectors' ) ] );
        }
    }
    
    public function ajax_deactivate_addon(): void {
        if( ! $this->verify_admin() ) return;
        
        $plugin_file = isset( $_POST['plugin_file'] ) ? sanitize_text_field( $_POST['plugin_file'] ) : '';
        if( empty( $plugin_file ) ) {
            wp_send_json_error( [ 'message' => __( 'Plugin file required', 'gvectors' ) ] );
            
            return;
        }
        
        $result = $this->addonsService->deactivate_addon( $plugin_file );
        if( ! empty( $result['success'] ) ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( [ 'message' => $result['error'] ?? __( 'Deactivation failed', 'gvectors' ) ] );
        }
    }
    
    public function ajax_install_activate_addon(): void {
        if( ! $this->verify_admin() ) return;
        
        $product_id = isset( $_POST['product_id'] ) ? sanitize_text_field( $_POST['product_id'] ) : '';
        if( empty( $product_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Product ID required', 'gvectors' ) ] );
            
            return;
        }
        
        $result = $this->addonsService->install_and_activate( $product_id );
        if( ! empty( $result['success'] ) ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( [ 'message' => $result['error'] ?? __( 'Install & activate failed', 'gvectors' ) ] );
        }
    }
    
    // ==========================================
    // Subscription
    // ==========================================
    
    public function ajax_cancel_subscription(): void {
        if( ! $this->verify_admin() ) return;
        
        $subscription_id = isset( $_POST['subscription_id'] ) ? sanitize_text_field( $_POST['subscription_id'] ) : '';
        if( empty( $subscription_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Subscription ID required', 'gvectors' ) ] );
            
            return;
        }
        
        $response = $this->addonsService->licenseService->apiService->cancel_subscription( $subscription_id );
        if( ! empty( $response['success'] ) ) {
            wp_send_json_success( [ 'message' => __( 'Subscription will be cancelled at the end of the billing period', 'gvectors' ) ] );
        } else {
            $error = $response['error'] ?? __( 'Failed to cancel subscription', 'gvectors' );
            wp_send_json_error( [ 'message' => $error ] );
        }
    }
    
    public function ajax_resume_subscription(): void {
        if( ! $this->verify_admin() ) return;
        
        $subscription_id = isset( $_POST['subscription_id'] ) ? sanitize_text_field( $_POST['subscription_id'] ) : '';
        if( empty( $subscription_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Subscription ID required', 'gvectors' ) ] );
            
            return;
        }
        
        $response = $this->addonsService->licenseService->apiService->resume_subscription( $subscription_id );
        if( ! empty( $response['success'] ) ) {
            wp_send_json_success( [ 'message' => __( 'Subscription resumed', 'gvectors' ) ] );
        } else {
            $error = $response['error'] ?? __( 'Failed to resume subscription', 'gvectors' );
            wp_send_json_error( [ 'message' => $error ] );
        }
    }
    
    public function ajax_update_payment(): void {
        if( ! $this->verify_admin() ) return;
        
        $subscription_id = isset( $_POST['subscription_id'] ) ? sanitize_text_field( $_POST['subscription_id'] ) : '';
        if( empty( $subscription_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Subscription ID required', 'gvectors' ) ] );
            
            return;
        }
        
        $response = $this->addonsService->licenseService->apiService->update_subscription_payment( $subscription_id );
        if( ! empty( $response['success'] ) ) {
            wp_send_json_success( $response['data'] );
        } else {
            $error = $response['error'] ?? __( 'Failed to get update URL', 'gvectors' );
            wp_send_json_error( [ 'message' => $error ] );
        }
    }
    
    public function ajax_get_portal_url(): void {
        if( ! $this->verify_admin() ) return;
        
        $subscription_id = isset( $_POST['subscription_id'] ) ? sanitize_text_field( $_POST['subscription_id'] ) : '';
        if( empty( $subscription_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Subscription ID required', 'gvectors' ) ] );
            
            return;
        }
        
        $response = $this->addonsService->licenseService->apiService->get_subscription_portal_url( $subscription_id );
        if( ! empty( $response['success'] ) && ! empty( $response['data']['portal_url'] ) ) {
            wp_send_json_success( [ 'portal_url' => $response['data']['portal_url'] ] );
        } else {
            $error = $response['error'] ?? __( 'Failed to get portal URL', 'gvectors' );
            wp_send_json_error( [ 'message' => $error ] );
        }
    }
    
    // ==========================================
    // Trial
    // ==========================================
    
    public function ajax_start_trial(): void {
        if( ! $this->verify_admin() ) return;
        
        $product_id = isset( $_POST['product_id'] ) ? sanitize_text_field( $_POST['product_id'] ) : '';
        if( empty( $product_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Product ID required', 'gvectors' ) ] );
            
            return;
        }
        
        $response = $this->addonsService->licenseService->apiService->start_trial( $product_id );
        if( ! empty( $response['success'] ) ) {
            // Store trial license locally
            if( ! empty( $response['data'] ) ) {
                $this->addonsService->licenseService->save( $product_id, $response['data'] );
            }
            wp_send_json_success( [
                                      'message' => __( 'Trial started successfully', 'gvectors' ),
                                      'data'    => $response['data'],
                                  ] );
        } else {
            $error = $response['error'] ?? __( 'Failed to start trial', 'gvectors' );
            wp_send_json_error( [ 'message' => $error ] );
        }
    }
    
    // ==========================================
    // Account
    // ==========================================
    
    public function ajax_get_account(): void {
        if( ! $this->verify_admin() ) return;
        
        $response = $this->addonsService->licenseService->apiService->get_account();
        if( ! empty( $response['success'] ) ) {
            wp_send_json_success( $response['data'] );
        } else {
            $error = $response['error'] ?? __( 'Failed to load account', 'gvectors' );
            wp_send_json_error( [ 'message' => $error ] );
        }
    }
    
    // ==========================================
    // Cache
    // ==========================================
    
    public function ajax_get_pending_transactions(): void {
        if( ! $this->verify_admin() ) return;
        
        $pending = get_option( $this->pending_transactions_option_name, [] );
        if( ! is_array( $pending ) ) $pending = [];
        
        // Remove entries older than 24 hours (entries are ['time'=>..,'user_id'=>..],
        // legacy entries may be a plain timestamp)
        $cutoff  = time() - 86400;
        $pending = array_filter( $pending, function( $entry ) use ( $cutoff ) {
            $ts = is_array( $entry ) ? (int) ( $entry['time'] ?? 0 ) : (int) $entry;
            return $ts > $cutoff;
        } );
        update_option( $this->pending_transactions_option_name, $pending );

        // Re-create any abandoned-checkout check events that got lost
        $this->heal_abandoned_checks( $pending );

        wp_send_json_success( array_keys( $pending ) );
    }
    
    public function ajax_clear_pending_transaction(): void {
        $this->ajax_save_pending_transaction( true );
    }
    
    public function ajax_save_pending_transaction( bool $shouldClear = false ): void {
        if( ! $this->verify_admin() ) return;
        
        $transaction_id = isset( $_POST['transaction_id'] ) ? sanitize_text_field( $_POST['transaction_id'] ) : '';
        if( empty( $transaction_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Transaction ID required', 'gvectors' ) ] );
            
            return;
        }
        
        $pending = get_option( $this->pending_transactions_option_name, [] );
        if( ! is_array( $pending ) ) $pending = [];
        if( $shouldClear ) {
            unset( $pending[ $transaction_id ] );
        } else {
            // Record WHO opened the checkout: the pending list is a site-wide
            // option and ANY admin's dashboard may later run the verification
            // polling, so the purchaser must be captured here, at purchase time.
            // Preserve fields set by schedule_abandoned_checks (phases) — this
            // AJAX may land after the checkout response already stored them.
            $entry = isset( $pending[ $transaction_id ] ) && is_array( $pending[ $transaction_id ] ) ? $pending[ $transaction_id ] : [];
            $pending[ $transaction_id ] = array_merge( $entry, [ 'time' => time(), 'user_id' => get_current_user_id() ] );
        }
        update_option( $this->pending_transactions_option_name, $pending );
        wp_send_json_success();
    }
    
    public function ajax_get_timeline(): void {
        if( ! $this->verify_admin() ) return;
        
        $license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( $_POST['license_key'] ) : '';
        if( empty( $license_key ) ) {
            wp_send_json_error( [ 'message' => __( 'License key required', 'gvectors' ) ] );
            
            return;
        }
        
        $response = $this->addonsService->licenseService->apiService->get_license_timeline( $license_key );
        if( ! empty( $response['success'] ) ) {
            wp_send_json_success( $response['data'] );
        } else {
            $error = $response['error'] ?? __( 'Failed to load timeline', 'gvectors' );
            wp_send_json_error( [ 'message' => $error ] );
        }
    }
    
    public function ajax_clear_cache(): void {
        if( ! $this->verify_admin() ) return;
        $this->addonsService->licenseService->apiService->clear_cache();
        wp_send_json_success( [ 'message' => __( 'Cache cleared', 'gvectors' ) ] );
    }
}
