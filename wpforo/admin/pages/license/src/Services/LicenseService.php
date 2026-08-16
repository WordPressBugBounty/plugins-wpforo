<?php

namespace gVectors\License\Services;

use gVectors\License\Config;
use gVectors\License\LicenseModule;

// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

/**
 * Manages license storage, validation, and revalidation.
 * Licenses are stored locally, but the proxy server is the source of truth.
 */
class LicenseService {
    /**
     * Terminal statuses/reasons: license is removed AND the addon is deactivated + deleted.
     */
    const TERMINAL_STATUSES = [ 'invalid' ];
    const REFUND_REASONS    = [ 'refunded' ];
    public  $apiService;
    public  $tampered_option;
    public  $expired_notice_option;
    private $config;
    private $batch_cache_key;
    private $option_key;
    
    public function __construct( Config $config, ApiService $apiService ) {
        $this->config                = $config;
        $this->apiService            = $apiService;
        $this->batch_cache_key       = $this->config->get_core_plugin_slug() . '_gvectors_batch_validation';
        $this->option_key            = $this->config->get_core_plugin_slug() . '_gvectors_licenses';
        $this->tampered_option       = $this->config->get_core_plugin_slug() . '_gvectors_tampered_addons';
        $this->expired_notice_option = $this->config->get_core_plugin_slug() . '_gvectors_expired_license_notices';
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action( 'gvectors_daily_cron', [ $this, 'maybe_revalidate_all' ] );
        if( ! wp_next_scheduled( 'gvectors_revalidate' ) ) {
            wp_schedule_event( time(), 'daily', 'gvectors_revalidate' );
        }
        add_action( 'gvectors_revalidate', [ $this, 'maybe_revalidate_all' ] );
    }
    
    /**
     * Check if a product has an active license
     */
    public function is_active( string $product_id ): bool {
        $license = $this->get( $product_id );
        if( empty( $license ) || empty( $license['status'] ) ) return false;
        if( ! in_array( $license['status'], [ 'active', 'trial' ], true ) ) return false;
        $expires_ts = ! empty( $license['expires_at'] ) ? strtotime( $license['expires_at'] ) : false;
        if( $expires_ts !== false && $expires_ts < time() ) return false;
        
        return true;
    }
    
    /**
     * Get a license for a specific product
     */
    public function get( string $product_id ): array {
        $licenses = $this->get_all();
        
        return $licenses[ $product_id ] ?? [];
    }
    
    /**
     * Get all stored licenses
     */
    public function get_all(): array {
        return get_option( $this->option_key, [] );
    }
    
    /**
     * Check if a product is on trial
     */
    public function is_trial( string $product_id ): bool {
        $license = $this->get( $product_id );
        
        return ! empty( $license ) && $license['status'] === 'trial';
    }
    
    /**
     * Validate all licenses via a single batch call (or use cache), then return
     * the result for the requested product along with ALL updated licenses.
     *
     * Called by the AJAX validate button. One click validates everything.
     *
     * Returns the same array shape as revalidate(), plus:
     *   'all_licenses' => array (full refreshed licenses keyed by product_id)
     *   'cached' => bool
     */
    public function validate_with_cache( string $product_id = '' ): array {
        // If a recent batch cache exists, all local data is already up to date
        $cached    = get_transient( $this->batch_cache_key );
        $is_cached = ! empty( $cached ) && ! empty( $cached['time'] );
        
        if( ! $is_cached ) {
            // No cache — fetch fresh batch and process ALL licenses
            $status = $this->batch_validate_and_cache();
            
            if( $status === 'server_unavailable' ) {
                return [
                    'valid'         => false,
                    'reason'        => 'server_unavailable',
                    'error'         => __( 'Could not reach the license server. Your current license status is preserved. Will retry automatically.', 'gvectors' ),
                    'status'        => '',
                    'removed'       => false,
                    'addon_deleted' => false,
                    'all_licenses'  => $this->get_all(),
                ];
            }
        }
        
        $all_licenses = $this->get_all();
        
        // If a specific product was requested, return its individual result
        if( ! empty( $product_id ) ) {
            $license = $this->get( $product_id );
            $valid   = ! empty( $license ) && ! empty( $license['status'] ) && in_array( $license['status'], [ 'active', 'trial' ], true );
            
            if( $valid && ! empty( $license['expires_at'] ) ) {
                $expires_ts = strtotime( $license['expires_at'] );
                if( $expires_ts !== false && $expires_ts < time() ) {
                    $valid = false;
                }
            }
            
            $reason = '';
            if( ! $valid && ! empty( $license['status'] ) ) {
                $reason = $license['status'];
            }
            if( empty( $license ) ) {
                $reason = 'missing';
            }
            
            return [
                'valid'         => $valid,
                'reason'        => $reason,
                'error'         => '',
                'status'        => $license['status'] ?? '',
                'removed'       => empty( $license ),
                'addon_deleted' => false,
                'all_licenses'  => $all_licenses,
                'cached'        => $is_cached,
            ];
        }
        
        // No specific product — return summary for all
        // Consider valid if all licenses are active/trial
        $all_valid = true;
        foreach( $all_licenses as $lic ) {
            if( empty( $lic['status'] ) || ! in_array( $lic['status'], [ 'active', 'trial' ], true ) ) {
                $all_valid = false;
                break;
            }
            if( ! empty( $lic['expires_at'] ) ) {
                $expires_ts = strtotime( $lic['expires_at'] );
                if( $expires_ts !== false && $expires_ts < time() ) {
                    $all_valid = false;
                    break;
                }
            }
        }
        
        return [
            'valid'         => $all_valid,
            'reason'        => $all_valid ? '' : 'some_invalid',
            'error'         => '',
            'status'        => '',
            'removed'       => false,
            'addon_deleted' => false,
            'all_licenses'  => $all_licenses,
            'cached'        => $is_cached,
        ];
    }
    
    /**
     * Statuses that indicate a license is permanently invalid and should be removed locally.
     */
    
    /**
     * Perform a batch validation for all stored licenses, process each result
     * (update local DB/options), and cache the raw results.
     *
     * Returns 'server_unavailable' on server error, 'ok' on success, 'empty' if no licenses.
     */
    public function batch_validate_and_cache(): string {
        $licenses = $this->get_all();
        if( empty( $licenses ) ) return 'empty';
        
        $key_to_product = []; // license_key => product_id
        foreach( $licenses as $product_id => $license ) {
            if( ! empty( $license['license_key'] ) ) {
                $key_to_product[ $license['license_key'] ] = $product_id;
            }
        }
        if( empty( $key_to_product ) ) return 'empty';
        
        $response = $this->apiService->validate_licenses_batch( array_keys( $key_to_product ) );
        
        $response_code   = $response['code'] ?? 0;
        $is_server_error = (
            $response_code === 'wp_error'
            || $response_code === 'invalid_response'
            || ( is_int( $response_code ) && ( $response_code >= 500 || $response_code === 401 || $response_code === 403 || $response_code === 429 ) )
        );
        if( $is_server_error ) return 'server_unavailable';
        
        if( empty( $response['success'] ) || empty( $response['data']['licenses'] ) ) return 'empty';
        
        $batch_results = $response['data']['licenses'];
        
        // Cache the raw batch results
        set_transient( $this->batch_cache_key, [
            'time'     => time(),
            'licenses' => $batch_results,
        ],             $this->config->get_license_batch_cache_ttl() );
        
        // Process every license result — update local storage and notices
        foreach( $key_to_product as $license_key => $product_id ) {
            if( ! isset( $batch_results[ $license_key ] ) ) continue;
            
            $result  = $batch_results[ $license_key ];
            $license = $licenses[ $product_id ];
            
            if( ! empty( $result['success'] ) && ! empty( $result['data'] ) ) {
                $this->save( $product_id, $result['data'] );
                
                // Clear any expired notice for this license
                $plugin_slug = $result['data']['plugin_slug'] ?? '';
                if( $plugin_slug ) {
                    $expired_notices = get_option( $this->expired_notice_option, [] );
                    if( isset( $expired_notices[ $plugin_slug ] ) ) {
                        unset( $expired_notices[ $plugin_slug ] );
                        update_option( $this->expired_notice_option, $expired_notices );
                    }
                }
            } else {
                $this->process_failed_revalidation( $product_id, $license, $result );
            }
        }
        
        return 'ok';
    }
    
    /**
     * Store/update a license locally
     */
    public function save( string $product_id, array $license_data ): bool {
        $licenses = $this->get_all();
        
        // Guard: Never overwrite a locally-stored active lifetime license with a subscription license.
        // Only protect lifetime licenses that are still valid (active/trial status).
        $existing        = $licenses[ $product_id ] ?? [];
        $existing_status = $existing['status'] ?? '';
        if( ! empty( $existing ) && empty( $existing['expires_at'] ) && ! empty( $license_data['expires_at'] )
            && in_array( $existing_status, [ 'active', 'trial', '' ] ) ) {
            return true;
        }
        
        $license_data['last_validated'] = time();
        $licenses[ $product_id ]        = wp_parse_args( $license_data, [
            'license_key'     => '',
            'product_id'      => $product_id,
            'subscription_id' => '',
            'status'          => '',
            'expires_at'      => '',
            'activated_at'    => '',
            'site_domain'     => LicenseModule::get_site_domain(),
            'customer_id'     => '',
            'plan_name'       => '',
            'last_validated'  => time(),
        ] );
        
        return update_option( $this->option_key, $licenses );
    }
    
    /**
     * Process a failed batch revalidation result for a single license.
     */
    private function process_failed_revalidation( string $product_id, array $license, array $result ): void {
        $server_status = $result['data']['status'] ?? '';
        $server_error  = $result['error'] ?? '';
        $server_reason = '';
        
        if( ! empty( $result['data']['invalid_reason'] ) ) {
            $server_reason = $result['data']['invalid_reason'];
        }
        if( empty( $server_reason ) && preg_match( '/invalid:\\s*(.+)$/i', $server_error, $m ) ) {
            $server_reason = trim( $m[1] );
        }
        if( empty( $server_reason ) && stripos( $server_error, 'expired' ) !== false ) {
            $server_reason = 'expired';
        }
        if( empty( $server_reason ) && stripos( $server_error, 'not found' ) !== false ) {
            $server_reason = 'not_found';
            $server_status = $server_status ?: 'invalid';
        }
        
        $is_terminal = in_array( $server_reason, self::REFUND_REASONS, true )
                       || in_array( $server_status, self::TERMINAL_STATUSES, true )
                       || $server_reason === 'not_found';
        
        if( $is_terminal ) {
            $plugin_slug = $license['plugin_slug'] ?? '';
            $this->remove( $product_id );
            
            if( $plugin_slug ) {
                $this->deactivate_and_delete_addon( $plugin_slug );
                
                $expired_notices = get_option( $this->expired_notice_option, [] );
                if( isset( $expired_notices[ $plugin_slug ] ) ) {
                    unset( $expired_notices[ $plugin_slug ] );
                    update_option( $this->expired_notice_option, $expired_notices );
                }
                $tampered = get_option( $this->tampered_option, [] );
                if( isset( $tampered[ $plugin_slug ] ) ) {
                    unset( $tampered[ $plugin_slug ] );
                    update_option( $this->tampered_option, $tampered );
                }
            }
        } else {
            // Non-terminal: update local license with server data
            $server_data        = ( ! empty( $result['data'] ) && is_array( $result['data'] ) ) ? $result['data'] : [];
            $has_license_fields = ! empty( $server_data['license_key'] ) || ! empty( $server_data['status'] ) || ! empty( $server_data['expires_at'] );
            
            if( $has_license_fields ) {
                $updated                   = wp_parse_args( $server_data, $license );
                $updated['last_validated'] = time();
                $this->save( $product_id, $updated );
            } else {
                if( $server_status ) {
                    $license['status'] = $server_status;
                } elseif( $server_reason === 'expired' ) {
                    $license['status'] = 'expired';
                }
                $license['last_validated'] = time();
                $this->save( $product_id, $license );
            }
            
            if( in_array( $server_status, [ 'expired', 'cancelled' ], true ) || $server_reason === 'expired' ) {
                $this->update_expired_notice( $product_id );
            }
        }
    }
    
    /**
     * Remove a license for a product
     */
    public function remove( string $product_id ): bool {
        $licenses = $this->get_all();
        if( isset( $licenses[ $product_id ] ) ) {
            unset( $licenses[ $product_id ] );
            
            return update_option( $this->option_key, $licenses );
        }
        
        return true;
    }
    
    /**
     * Deactivate and delete an addon plugin by its slug.
     * Used when a license is refunded to fully remove the addon.
     */
    private function deactivate_and_delete_addon( string $plugin_slug ): bool {
        if( empty( $plugin_slug ) ) return false;
        
        if( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if( ! function_exists( 'delete_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        
        // Find the installed plugin file
        $plugin_file = '';
        $all_plugins = get_plugins();
        foreach( $all_plugins as $file => $data ) {
            if( strpos( $file, $plugin_slug . '/' ) === 0 ) {
                $plugin_file = $file;
                break;
            }
        }
        
        if( empty( $plugin_file ) ) return false;
        
        // Deactivate if active
        if( is_plugin_active( $plugin_file ) ) {
            deactivate_plugins( $plugin_file );
        }
        
        // Delete the plugin files
        $result = delete_plugins( [ $plugin_file ] );
        
        return ! is_wp_error( $result );
    }
    
    /**
     * Update the expired license notice for a specific product.
     */
    private function update_expired_notice( string $product_id ): void {
        $license = $this->get( $product_id );
        if( empty( $license ) ) return;
        
        $plugin_slug = $license['plugin_slug'] ?? '';
        if( empty( $plugin_slug ) ) return;
        
        $expired_notices                 = get_option( $this->expired_notice_option, [] );
        $expired_notices[ $plugin_slug ] = [
            'product_name' => $license['product_name'] ?? $plugin_slug,
            'status'       => $license['status'] ?? 'expired',
            'expires_at'   => $license['expires_at'] ?? '',
            'has_update'   => false,
        ];
        update_option( $this->expired_notice_option, $expired_notices );
    }
    
    /**
     * Revalidate a single license against the proxy server.
     *
     * Returns an associative array with:
     *   'valid' => bool
     *   'reason' => string (failure reason from server, e.g. 'refunded', 'expired')
     *   'error' => string (human-readable error message from server)
     *   'status' => string (license status from server response)
     *   'removed' => bool (whether the license was removed locally)
     *   'addon_deleted' => bool (whether the addon plugin was deactivated and deleted - only on refund)
     */
    public function revalidate( string $product_id ): array {
        $license = $this->get( $product_id );
        if( empty( $license ) || empty( $license['license_key'] ) ) {
            return [
                'valid'         => false,
                'reason'        => 'missing',
                'error'         => __( 'No license found for this product', 'gvectors' ),
                'status'        => '',
                'removed'       => false,
                'addon_deleted' => false,
            ];
        }
        
        $response = $this->apiService->validate_license( $license['license_key'] );
        
        // ── Server/network error — keep current local state, retry later ──
        // Only trust the response if the server actually processed our request.
        // Connection failures, HTTP errors (401, 403, 500), and invalid responses
        // should NOT alter the local license status.
        $response_code   = $response['code'] ?? 0;
        $is_server_error = (
            $response_code === 'wp_error'           // Connection failure, timeout, DNS error
            || $response_code === 'invalid_response' // Server returned non-JSON
            || ( is_int( $response_code ) && ( $response_code >= 500 || $response_code === 401 || $response_code === 403 || $response_code === 429 ) )
        );
        
        if( $is_server_error ) {
            // Don't update last_validated — will retry on next cron run
            return [
                'valid'         => ! empty( $license['status'] ) && in_array( $license['status'], [ 'active', 'trial' ], true ),
                'reason'        => 'server_unavailable',
                'error'         => $response['error'] ?? 'Server unavailable',
                'status'        => $license['status'] ?? '',
                'removed'       => false,
                'addon_deleted' => false,
            ];
        }
        
        // License is valid on the server
        if( ! empty( $response['success'] ) && ! empty( $response['data'] ) ) {
            $this->save( $product_id, $response['data'] );
            
            return [
                'valid'         => true,
                'reason'        => '',
                'error'         => '',
                'status'        => $response['data']['status'] ?? 'active',
                'removed'       => false,
                'addon_deleted' => false,
            ];
        }
        
        // License validation failed - extract details from server response
        $server_status = $response['data']['status'] ?? '';
        $server_error  = $response['error'] ?? '';
        $server_reason = '';
        
        // Extract invalid_reason from response data if available
        if( ! empty( $response['data']['invalid_reason'] ) ) {
            $server_reason = $response['data']['invalid_reason'];
        }
        // Parse reason from the error message (e.g. "License is invalid: refunded")
        if( empty( $server_reason ) && preg_match( '/invalid:\s*(.+)$/i', $server_error, $m ) ) {
            $server_reason = trim( $m[1] );
        }
        // If the error indicates expiration, set reason accordingly
        if( empty( $server_reason ) && stripos( $server_error, 'expired' ) !== false ) {
            $server_reason = 'expired';
        }
        // If the license key was not found on server
        if( empty( $server_reason ) && stripos( $server_error, 'not found' ) !== false ) {
            $server_reason = 'not_found';
            $server_status = $server_status ?: 'invalid';
        }
        
        // Determine if this is a terminal state: license removed + addon deactivated + deleted
        // Terminal = refunded, not found on server, or invalid status
        $is_terminal = in_array( $server_reason, self::REFUND_REASONS, true )
                       || in_array( $server_status, self::TERMINAL_STATUSES, true )
                       || $server_reason === 'not_found';
        
        $removed       = false;
        $addon_deleted = false;
        
        if( $is_terminal ) {
            // Terminal: remove license, deactivate and delete the addon plugin
            $plugin_slug = $license['plugin_slug'] ?? '';
            $this->remove( $product_id );
            $removed = true;
            
            // Deactivate and delete the addon if installed
            if( $plugin_slug ) {
                $addon_deleted = $this->deactivate_and_delete_addon( $plugin_slug );
                
                // Clean-up-related notices
                $expired_notices = get_option( $this->expired_notice_option, [] );
                if( isset( $expired_notices[ $plugin_slug ] ) ) {
                    unset( $expired_notices[ $plugin_slug ] );
                    update_option( $this->expired_notice_option, $expired_notices );
                }
                $tampered = get_option( $this->tampered_option, [] );
                if( isset( $tampered[ $plugin_slug ] ) ) {
                    unset( $tampered[ $plugin_slug ] );
                    update_option( $this->tampered_option, $tampered );
                }
            }
        } else {
            // Non-terminal failure (expired, cancelled, past_due, paused, etc.)
            // Overwrite local license with authoritative server data to purge stale values.
            $server_data = ( ! empty( $response['data'] ) && is_array( $response['data'] ) ) ? $response['data'] : [];
            
            // Only treat server data as license fields if it has actual license keys
            // (not just a raw error envelope like {success:false, error:'...'})
            $has_license_fields = ! empty( $server_data['license_key'] ) || ! empty( $server_data['status'] ) || ! empty( $server_data['expires_at'] );
            
            if( $has_license_fields ) {
                // Server returned full license data — use it as the source of truth
                $updated                   = wp_parse_args( $server_data, $license );
                $updated['last_validated'] = time();
                $this->save( $product_id, $updated );
            } else {
                // Server didn't return license fields — force-update status from error context
                if( $server_status ) {
                    $license['status'] = $server_status;
                } elseif( $server_reason === 'expired' ) {
                    $license['status'] = 'expired';
                }
                $license['last_validated'] = time();
                $this->save( $product_id, $license );
            }
            
            // Trigger expired notice update for this license
            if( in_array( $server_status, [ 'expired', 'cancelled' ], true ) || $server_reason === 'expired' ) {
                $this->update_expired_notice( $product_id );
            }
        }
        
        return [
            'valid'         => false,
            'reason'        => $server_reason ?: $server_status,
            'error'         => $server_error,
            'status'        => $server_status,
            'removed'       => $removed,
            'addon_deleted' => $addon_deleted,
        ];
    }
    
    /**
     * Revalidate all stored licenses (called by cron).
     * Uses batch validation to check all licenses in a single API call.
     */
    public function maybe_revalidate_all(): void {
        $licenses = $this->get_all();
        if( empty( $licenses ) ) return;
        
        // Collect license keys that need revalidation
        $keys_to_validate = []; // license_key => product_id
        foreach( $licenses as $product_id => $license ) {
            if( $this->needs_revalidation( $product_id ) && ! empty( $license['license_key'] ) ) {
                $keys_to_validate[ $license['license_key'] ] = $product_id;
            }
        }
        
        if( empty( $keys_to_validate ) ) return;
        
        $response = $this->apiService->validate_licenses_batch( array_keys( $keys_to_validate ) );
        
        // Server/network error — don't touch local state, retry on next cron
        $response_code   = $response['code'] ?? 0;
        $is_server_error = (
            $response_code === 'wp_error'
            || $response_code === 'invalid_response'
            || ( is_int( $response_code ) && ( $response_code >= 500 || $response_code === 401 || $response_code === 403 || $response_code === 429 ) )
        );
        if( $is_server_error ) return;
        
        if( empty( $response['success'] ) || empty( $response['data']['licenses'] ) ) return;
        
        $batch_results = $response['data']['licenses'];
        
        // Cache the raw batch results so individual Validate clicks can reuse them
        set_transient( $this->batch_cache_key, [
            'time'     => time(),
            'licenses' => $batch_results,
        ],             $this->config->get_license_batch_cache_ttl() );
        
        foreach( $keys_to_validate as $license_key => $product_id ) {
            if( ! isset( $batch_results[ $license_key ] ) ) continue;
            
            $result  = $batch_results[ $license_key ];
            $license = $licenses[ $product_id ];
            
            if( ! empty( $result['success'] ) && ! empty( $result['data'] ) ) {
                // License is valid — update local data
                $this->save( $product_id, $result['data'] );
            } else {
                // License validation failed — process like single "revalidate"
                $this->process_failed_revalidation( $product_id, $license, $result );
            }
        }
    }
    
    /**
     * Check if the license needs revalidation
     */
    public function needs_revalidation( string $product_id ): bool {
        $license = $this->get( $product_id );
        if( empty( $license ) ) return false;
        $last = isset( $license['last_validated'] ) ? (int) $license['last_validated'] : 0;
        
        return ( time() - $last ) > $this->config->get_license_revalidation_period();
    }
    
    /**
     * Unified activation: proxy detects key type (gvl_*, txn_*, legacy, or empty=by-domain).
     * Saves whatever license(s) the proxy returns into the local gvectors_licenses option.
     */
    public function activate_unified( string $key, string $product_id = '' ): array {
        $response = $this->apiService->activate_unified( $key, $product_id );
        
        if( ! empty( $response['success'] ) ) {
            // Transaction / by-domain response: data.activated[] array
            if( ! empty( $response['data']['activated'] ) ) {
                foreach( $response['data']['activated'] as $license_data ) {
                    $pid = ! empty( $license_data['product_id'] ) ? $license_data['product_id'] : '';
                    if( $pid ) {
                        $this->save( $pid, $license_data );
                    }
                }
            } // Single license response (gvl_* or legacy key): data.license_key present
            elseif( ! empty( $response['data']['license_key'] ) ) {
                $pid = ! empty( $response['data']['product_id'] ) ? $response['data']['product_id'] : $product_id;
                if( $pid ) {
                    $this->save( $pid, $response['data'] );
                }
            }
        }
        
        return $response;
    }
    
    /**
     * Activate a license key
     */
    public function activate( string $license_key, string $product_id = '' ): array {
        $response = $this->apiService->activate_license( $license_key, $product_id );
        
        if( ! empty( $response['success'] ) && ! empty( $response['data'] ) ) {
            $pid = ! empty( $response['data']['product_id'] ) ? $response['data']['product_id'] : $product_id;
            if( $pid ) {
                $this->save( $pid, $response['data'] );
            }
        }
        
        return $response;
    }
    
    /**
     * Activate licenses by transaction ID
     */
    public function activate_by_transaction( string $transaction_id, string $product_id = '' ): array {
        $response = $this->apiService->activate_license_by_transaction( $transaction_id, $product_id );
        
        if( ! empty( $response['success'] ) && ! empty( $response['data']['activated'] ) ) {
            foreach( $response['data']['activated'] as $license_data ) {
                $pid = ! empty( $license_data['product_id'] ) ? $license_data['product_id'] : '';
                if( $pid ) {
                    $this->save( $pid, $license_data );
                }
            }
        }
        
        return $response;
    }
    
    /**
     * Activate all licenses registered for this site's domain
     */
    public function activate_by_domain(): array {
        $response = $this->apiService->activate_licenses_by_domain();
        
        if( ! empty( $response['success'] ) && ! empty( $response['data']['activated'] ) ) {
            foreach( $response['data']['activated'] as $license_data ) {
                $pid = ! empty( $license_data['product_id'] ) ? $license_data['product_id'] : '';
                if( $pid ) {
                    $this->save( $pid, $license_data );
                }
            }
        }
        
        return $response;
    }
    
    /**
     * Deactivate a license key
     */
    public function deactivate( string $product_id ): array {
        $license = $this->get( $product_id );
        if( empty( $license ) || empty( $license['license_key'] ) ) {
            return [ 'success' => false, 'error' => 'No license found for this product' ];
        }
        
        $response = $this->apiService->deactivate_license( $license['license_key'] );
        
        if( ! empty( $response['success'] ) ) {
            $this->remove( $product_id );
        }
        
        return $response;
    }
    
    /**
     * Get a license status label
     */
    public function get_status_label( string $product_id ): string {
        $license = $this->get( $product_id );
        if( empty( $license ) ) return __( 'Not Licensed', 'gvectors' );
        
        $labels = [
            'active'    => __( 'Active', 'gvectors' ),
            'trial'     => __( 'Trial', 'gvectors' ),
            'expired'   => __( 'Expired', 'gvectors' ),
            'cancelled' => __( 'Cancelled', 'gvectors' ),
            'past_due'  => __( 'Past Due', 'gvectors' ),
            'paused'    => __( 'Paused', 'gvectors' ),
        ];
        
        $status = $license['status'] ?? '';
        
        return $labels[ $status ] ?? __( 'Unknown', 'gvectors' );
    }
}
