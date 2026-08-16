<?php

namespace gVectors\License\Services;

// Exit if accessed directly
use FilesystemIterator;
use gVectors\License\Config;
use gVectors\License\LicenseModule;
use Plugin_Upgrader;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SodiumException;
use stdClass;
use WP_Ajax_Upgrader_Skin;
use WP_Error;

if( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles addon download, installation, activation, and update checks.
 * Downloads are always through signed URLs from the proxy server.
 */
class AddonsService {
    public  $licenseService;
    private $config;
    /**
     * Check for addon updates via the proxy server.
     * Fetches latest version info directly from the proxy (read from addon file headers on server),
     * only offers updates for licenses that are active/trial AND activated for this domain.
     * Expired licenses are NOT offered updates (addon keeps working but no new versions).
     */
    private $update_check_done = false;
    private $all_addons_transient_name;
    private $signature_check_hook;
    private $license_check_hook;
    private $tampered_option;
    private $expired_notice_option;
    private $tamper_dismissed_option;
    private $legacy_licenses_option;
    private $legacy_notice_option;
    /** Shared transient (not slug-prefixed) so one dismissing covers all plugin instances */
    private static $shared_dev_env_transient      = 'gvectors_dev_env_notice_dismissed';
    private static $shared_dev_licenses_transient = 'gvectors_dev_licenses_notice_dismissed';

    /** Static collectors for cross-instance notice deduplication */
    private static $dev_env_notice_shown    = false;
    private static $dev_licenses_collected  = [];
    private static $dev_licenses_registered = false;
    
    public function __construct( Config $config, LicenseService $licenseService ) {
        $this->config                        = $config;
        $this->licenseService                = $licenseService;
        $this->all_addons_transient_name     = $this->config->get_core_plugin_slug() . '_gvectors_all_addons';
        $this->signature_check_hook          = $this->config->get_core_plugin_slug() . '_gvectors_addon_signature_check';
        $this->license_check_hook            = $this->config->get_core_plugin_slug() . '_gvectors_addon_license_check';
        $this->tampered_option               = $this->licenseService->tampered_option;
        $this->expired_notice_option         = $this->licenseService->expired_notice_option;
        $this->tamper_dismissed_option       = $this->config->get_core_plugin_slug() . '_gvectors_tamper_notice_seen';
        $this->legacy_licenses_option        = $this->config->get_core_plugin_slug() . '_gvectors_legacy_addon_licenses';
        $this->legacy_notice_option          = $this->config->get_core_plugin_slug() . '_gvectors_legacy_license_notices';
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_updates' ] );
        add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
        
        // Force-refresh the update transient if unmigrated legacy addons exist
        add_action( 'admin_init', [ $this, 'maybe_refresh_update_transient' ] );
        
        // Show license-required notice on plugin page for addons with updates but no active license
        add_action( 'admin_init', [ $this, 'register_unlicensed_update_row_hooks' ] );
        
        // Signature integrity check cron (twice daily)
        add_action( $this->signature_check_hook, [ $this, 'verify_all_addon_signatures' ] );
        if( ! wp_next_scheduled( $this->signature_check_hook ) ) {
            wp_schedule_event( time(), 'twicedaily', $this->signature_check_hook );
        }
        
        // License validity check cron (daily)
        add_action( $this->license_check_hook, [ $this, 'check_all_license_validity' ] );
        if( ! wp_next_scheduled( $this->license_check_hook ) ) {
            wp_schedule_event( time(), 'daily', $this->license_check_hook );
        }
        
        // Admin notices
        add_action( 'admin_notices', [ $this, 'tampered_addon_notice' ] );
        add_action( 'admin_notices', [ $this, 'expired_license_notice' ] );
        add_action( 'admin_notices', [ $this, 'legacy_license_notice' ] );
        add_action( 'admin_notices', [ $this, 'dev_environment_notice' ] );
        add_action( 'admin_notices', [ $this, 'dev_licenses_notice' ] );
        
        // Handle dismissal of dev environment notices
        add_action( 'admin_init', [ $this, 'handle_dev_notice_dismiss' ] );
        
        // Track when admin has seen tamper notices
        add_action( 'admin_init', [ $this, 'track_tamper_notice_view' ] );
        
        // Intercept plugin activation to validate addon before allowing it
        add_action( 'activate_plugin', [ $this, 'validate_on_activation' ] );
        
        // Intercept WordPress updater downloads to block tampered addons with a visible error
        add_filter( 'upgrader_pre_download', [ $this, 'block_tampered_update_download' ], 10, 2 );
        
        // Clear tamper flag when a plugin is deleted
        add_action( 'deleted_plugin', [ $this, 'on_plugin_deleted' ], 10, 2 );
    }
    
    /**
     * Install and activate an addon in one step
     */
    public function install_and_activate( string $product_id ): array {
        $install_result = $this->install( $product_id );
        if( empty( $install_result['success'] ) ) return $install_result;
        
        $plugin_file = $install_result['plugin_file'];
        if( empty( $plugin_file ) ) {
            return [ 'success' => false, 'error' => __( 'Could not determine plugin file after installation', 'gvectors' ) ];
        }
        
        $activate_result = $this->activate( $plugin_file );
        if( empty( $activate_result['success'] ) ) return $activate_result;
        
        // Clear cached plugin list so subsequent get_plugins() calls see the new addon
        wp_cache_delete( 'plugins', 'plugins' );
        
        return [
            'success' => true,
            'message' => __( 'Addon installed and activated successfully', 'gvectors' ),
        ];
    }
    
    /**
     * Download and install an addon from the proxy server
     */
    public function install( string $product_id ): array {
        if( ! current_user_can( 'install_plugins' ) ) {
            return [ 'success' => false, 'error' => __( 'Permission denied', 'gvectors' ) ];
        }
        
        $license = $this->licenseService->get( $product_id );
        if( empty( $license ) || empty( $license['license_key'] ) ) {
            return [ 'success' => false, 'error' => __( 'No active license for this product', 'gvectors' ) ];
        }
        
        // Block install/update for tampered/unauthorized addons
        $plugin_slug = $license['plugin_slug'] ?? '';
        if( $plugin_slug && $this->is_addon_tampered( $plugin_slug ) ) {
            return [
                'success' => false,
                'error'   => __(
                    'This addon cannot be updated because its files have been modified or are not original. To resolve this, please: 1) Go to Plugins and deactivate, then delete this addon. 2) Visit the gVectors Store Addons page and make sure your license is active. 3) Re-install the addon from the gVectors Store Addons page. Once re-installed, everything will work normally again.',
                    'gvectors'
                ),
            ];
        }
        
        // Get signed download URL from proxy
        $response = $this->licenseService->apiService->get_addon_download_url( $product_id, $license['license_key'] );
        error_log( '[gVectors Addon] download-url response: ' . print_r( $response, true ) );
        if( empty( $response['success'] ) || empty( $response['data']['download_url'] ) ) {
            $error = $response['error'] ?? __( 'Failed to get download URL', 'gvectors' );
            if( isset( $response['data']['error'] ) ) $error = $response['data']['error'];
            error_log( '[gVectors Addon] Failed to get download URL: ' . $error );
            
            return [ 'success' => false, 'error' => $error ];
        }
        
        $download_url = $response['data']['download_url'];
        $download_url = add_query_arg( 'site_domain', rawurlencode( LicenseModule::get_site_domain() ), $download_url );
        $plugin_slug  = $response['data']['plugin_slug'] ?? '';
        error_log( '[gVectors Addon] download_url: ' . $download_url . ' | plugin_slug: ' . $plugin_slug );
        
        // Use WordPress built-in plugin installer
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        
        $skin     = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader( $skin );
        
        // Check if plugin already installed - if so, upgrade
        $installed_plugin = $this->get_installed_plugin_file( $plugin_slug );
        if( $installed_plugin ) {
            $result = $upgrader->upgrade( $installed_plugin, [ 'clear_update_cache' => true ] );
        } else {
            $result = $upgrader->install( $download_url );
        }
        
        if( is_wp_error( $result ) ) {
            error_log( '[gVectors Addon] WP_Error from upgrader: ' . $result->get_error_message() );
            
            return [ 'success' => false, 'error' => $result->get_error_message() ];
        }
        
        if( $result === false ) {
            $errors        = $skin->get_errors();
            $error         = is_wp_error( $errors ) ? $errors->get_error_message() : __( 'Installation failed', 'gvectors' );
            $skin_feedback = method_exists( $skin, 'get_upgrade_messages' ) ? $skin->get_upgrade_messages() : [];
            error_log( '[gVectors Addon] Install result=false. Error: ' . $error . ' | Feedback: ' . print_r( $skin_feedback, true ) );
            
            return [ 'success' => false, 'error' => $error ];
        }
        
        error_log( '[gVectors Addon] Install result: ' . print_r( $result, true ) );
        error_log( '[gVectors Addon] Skin messages: ' . print_r( $skin->get_upgrade_messages(), true ) );
        
        $plugin_file = $installed_plugin ?: $upgrader->plugin_info();
        
        // Fallback: if plugin_info() returned empty, re-scan installed plugins by slug
        if( empty( $plugin_file ) && ! empty( $plugin_slug ) ) {
            // Clear cached plugin list so get_plugins() picks up the newly installed addon
            wp_cache_delete( 'plugins', 'plugins' );
            $plugin_file = $this->get_installed_plugin_file( $plugin_slug );
        }
        
        // Last resort: scan the plugin directory for a file with a Plugin Name header
        if( empty( $plugin_file ) && ! empty( $plugin_slug ) ) {
            $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;
            if( is_dir( $plugin_dir ) ) {
                foreach( glob( $plugin_dir . '/*.php' ) as $php_file ) {
                    $headers = get_plugin_data( $php_file, false, false );
                    if( ! empty( $headers['Name'] ) ) {
                        $plugin_file = $plugin_slug . '/' . basename( $php_file );
                        break;
                    }
                }
            }
        }
        
        // Verify file signatures after installation — if verification fails, block activation
        if( $plugin_slug ) {
            $sig_result = $this->verify_addon_signatures( $plugin_slug );
            if( ! in_array( $sig_result, [ 'valid', 'legacy_valid' ], true ) ) {
                // Signatures invalid after fresh installation — possible MITM or corrupted download
                if( $plugin_file && is_plugin_active( $plugin_file ) ) {
                    deactivate_plugins( $plugin_file );
                }
                
                return [
                    'success' => false,
                    'error'   => __( 'Addon installed but signature verification failed. The download may have been corrupted. Please try again.', 'gvectors' ),
                ];
            }
        }
        
        return [
            'success'     => true,
            'plugin_file' => $plugin_file,
            'message'     => __( 'Addon installed successfully', 'gvectors' ),
        ];
    }
    
    /**
     * Check if an addon is flagged as tampered/unauthorized
     */
    public function is_addon_tampered( string $plugin_slug ): bool {
        $tampered = get_option( $this->tampered_option, [] );
        
        return isset( $tampered[ $plugin_slug ] );
    }
    
    /**
     * Find the installed plugin file by slug
     */
    private function get_installed_plugin_file( string $plugin_slug ): string {
        if( empty( $plugin_slug ) ) return '';
        
        if( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $all_plugins = get_plugins();
        foreach( $all_plugins as $file => $data ) {
            if( strpos( $file, $plugin_slug . '/' ) === 0 ) {
                return $file;
            }
        }
        
        return '';
    }
    
    /**
     * Provide plugin info for the WordPress updater popup ("View version X details").
     * Uses proxy server data for version/compatibility info (from addon file headers).
     * Does NOT fetch download URL — that is handled by check_for_updates() in the update transient.
     */
    public function plugin_info( $result, $action, $args ) {
        if( $action !== 'plugin_information' ) return $result;
        
        // Fetch addon metadata from proxy server (cached via transient)
        $proxy_addons = $this->get_proxy_addons_map();
        if( ! isset( $proxy_addons[ $args->slug ] ) ) return $result;
        
        $proxy_info = $proxy_addons[ $args->slug ];
        
        $info                 = new stdClass();
        $info->name           = ! empty( $proxy_info['name'] ) ? $proxy_info['name'] : $args->slug;
        $info->slug           = $args->slug;
        $info->version        = ! empty( $proxy_info['version'] ) ? $proxy_info['version'] : '';
        $info->author         = ! empty( $proxy_info['author'] ) ? $proxy_info['author'] : 'gVectors Team';
        $info->author_profile = ! empty( $proxy_info['author_uri'] ) ? $proxy_info['author_uri'] : 'https://gvectors.com';
        $info->homepage       = ! empty( $proxy_info['plugin_uri'] ) ? $proxy_info['plugin_uri'] : 'https://gvectors.com';
        $info->requires       = ! empty( $proxy_info['requires'] ) ? $proxy_info['requires'] : '5.0';
        $info->tested         = ! empty( $proxy_info['tested'] ) ? $proxy_info['tested'] : get_bloginfo( 'version' );
        $info->requires_php   = ! empty( $proxy_info['requires_php'] ) ? $proxy_info['requires_php'] : '7.4';
        $info->download_link  = ''; // No download URL here — WordPress uses $update->package from the transient
        
        $info->sections = [
            'description' => ! empty( $proxy_info['description'] ) ? $proxy_info['description'] : '',
            'changelog'   => ! empty( $proxy_info['changelog'] ) ? $proxy_info['changelog'] : '',
        ];
        
        // Use the addon's featured image from Paddle as the update popup banner
        if( ! empty( $proxy_info['image_url'] ) ) {
            $info->banners = [
                'high' => $proxy_info['image_url'],
                'low'  => $proxy_info['image_url'],
            ];
        }
        
        // Use the logo as the plugin icon
        if( ! empty( $proxy_info['logo'] ) ) {
            $info->icons = [
                '1x' => $proxy_info['logo'],
                '2x' => $proxy_info['logo'],
            ];
        }
        
        return $info;
    }
    
    /**
     * Fetch all addon info from the proxy server, keyed by slug.
     * Returns associative array: slug => [ name, version, description, author, requires, tested, requires_php, plugin_uri, ... ]
     */
    private function get_proxy_addons_map(): array {
        $response = $this->licenseService->apiService->get_all_addons();
        if( empty( $response['success'] ) || empty( $response['data']['addons'] ) ) {
            return [];
        }
        $map = [];
        foreach( $response['data']['addons'] as $addon ) {
            if( ! empty( $addon['slug'] ) ) {
                $map[ $addon['slug'] ] = $addon;
            }
        }
        
        return $map;
    }
    
    /**
     * Verify signatures of a single addon by its slug.
     * Checks: manifest existence, file hashes, domain signature, PHP header signatures.
     * For addons without a manifest, checks legacy license before flagging as tampered.
     * Returns 'valid', 'legacy_valid', 'no_manifest', 'tampered', 'domain_mismatch', 'no_signatures', or 'patched'.
     */
    public function verify_addon_signatures( string $plugin_slug ): string {
        // Development/local/staging environments are always valid
        if( LicenseModule::is_development_site() ) return 'valid';
        
        $plugin_slug   = self::sanitize_slug( $plugin_slug );
        $plugin_dir    = WP_PLUGIN_DIR . '/' . $plugin_slug;
        $manifest_file = $plugin_dir . '/.addon-signatures.json';
        
        // No manifest at all — could be a pirated copy OR a legacy-licensed installation
        // from before the new signature system. Check legacy license before flagging.
        if( ! file_exists( $manifest_file ) ) {
            // Always check for nulled/patched patterns first (catches case 4 regardless)
            $patch_check = $this->detect_nulled_patterns( $plugin_slug );
            if( $patch_check !== 'valid' ) {
                return $patch_check;
            }
            
            // Check if this addon has a legacy license from the old gVectors system
            $legacy = $this->check_legacy_license( $plugin_slug );
            if( ! empty( $legacy['has_license'] ) ) {
                // Legacy licensed addon — clear any previous tamper flags
                $this->clear_tamper_flag( $plugin_slug );
                // Track expired legacy licenses for admin notice
                $this->update_legacy_notice( $plugin_slug, $legacy );
                
                return 'legacy_valid';
            }
            
            // No legacy license either — this is an unauthorized copy
            $this->mark_addon_tampered( $plugin_slug, [ 'Missing signature manifest' ], 'no_manifest' );
            
            return 'no_manifest';
        }
        
        $raw_manifest = json_decode( file_get_contents( $manifest_file ), true );
        if( ! is_array( $raw_manifest ) || empty( $raw_manifest ) ) {
            $this->mark_addon_tampered( $plugin_slug, [ 'Empty or corrupted signature manifest' ] );
            
            return 'tampered';
        }
        
        // Support both new format { "files": {...}, "manifest_signature": "..." }
        // and legacy format { "file.php": {...} } for backwards compatibility
        if( isset( $raw_manifest['files'] ) && is_array( $raw_manifest['files'] ) ) {
            $manifest           = $raw_manifest['files'];
            $manifest_signature = $raw_manifest['manifest_signature'] ?? null;
        } else {
            $manifest           = $raw_manifest;
            $manifest_signature = null;
        }
        
        if( empty( $manifest ) ) {
            $this->mark_addon_tampered( $plugin_slug, [ 'Empty signature manifest (no files)' ] );
            
            return 'tampered';
        }
        
        // 0) Verify manifest cryptographic signature (Ed25519)
        // Prevents manifest forgery — attacker cannot modify signed_for/file_hash/signatures
        // without invalidating the signature, and cannot re-sign without the server's private key.
        if( $manifest_signature !== null
            && $this->config->get_manifest_public_key() !== 'REPLACE_WITH_YOUR_ED25519_PUBLIC_KEY_HEX'
            && function_exists( 'sodium_crypto_sign_verify_detached' )
        ) {
            try {
                $canonical_json = json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
                $public_key     = sodium_hex2bin( $this->config->get_manifest_public_key() );
                $sig            = sodium_hex2bin( $manifest_signature );
                if( ! sodium_crypto_sign_verify_detached( $sig, $canonical_json, $public_key ) ) {
                    $this->mark_addon_tampered( $plugin_slug, [ 'Manifest cryptographic signature is invalid — possible forgery' ] );
                    
                    return 'tampered';
                }
            } catch ( SodiumException $e ) {
                $this->mark_addon_tampered( $plugin_slug, [ 'Manifest signature corrupted: ' . $e->getMessage() ] );
                
                return 'tampered';
            }
        }
        
        // 1) Verify file hashes
        $tampered_files  = [];
        $real_plugin_dir = realpath( $plugin_dir );
        foreach( $manifest as $relative_path => $info ) {
            // Prevent path traversal via crafted manifest keys
            if( strpos( $relative_path, '..' ) !== false || strpos( $relative_path, '/' ) === 0 ) {
                $this->mark_addon_tampered( $plugin_slug, [ 'Manifest contains invalid path: ' . $relative_path ] );
                
                return 'tampered';
            }
            $file_path = $plugin_dir . '/' . $relative_path;
            if( ! file_exists( $file_path ) ) {
                $tampered_files[] = $relative_path . ' (missing)';
                continue;
            }
            
            // Verify resolved path is within the plugin directory (prevents symlink escapes)
            if( $real_plugin_dir ) {
                $real_file = realpath( $file_path );
                if( $real_file === false || strpos( $real_file, $real_plugin_dir . DIRECTORY_SEPARATOR ) !== 0 ) {
                    $this->mark_addon_tampered( $plugin_slug, [ 'File escapes plugin directory: ' . $relative_path ] );
                    
                    return 'tampered';
                }
            }
            
            $current_hash = hash( 'sha256', file_get_contents( $file_path ) );
            if( isset( $info['file_hash'] ) && $current_hash !== $info['file_hash'] ) {
                $tampered_files[] = $relative_path;
            }
        }
        
        if( ! empty( $tampered_files ) ) {
            $this->mark_addon_tampered( $plugin_slug, $tampered_files );
            
            return 'tampered';
        }
        
        // 2) Verify domain signature matches this site
        $site_domain     = LicenseModule::get_site_domain();
        $site_normalized = LicenseModule::normalize_domain( $site_domain );
        foreach( $manifest as $info ) {
            if( empty( $info['signed_for'] ) ) continue;
            $signed_normalized = LicenseModule::normalize_domain( $info['signed_for'] );
            if( $signed_normalized !== $site_normalized ) {
                $this->mark_addon_tampered( $plugin_slug, [
                    'Domain mismatch: addon signed for ' . $info['signed_for'] . ', running on ' . $site_domain,
                ],                          'domain_mismatch' );
                
                return 'domain_mismatch';
            }
        }
        
        // 3) Detect extra PHP files not listed in the manifest.
        // An attacker could add malicious PHP files that bypass all signature checks
        // if we only iterate over manifest keys. Scan the actual directory instead.
        $all_php_files = $this->get_php_files_recursive( $plugin_dir );
        foreach( $all_php_files as $php_file ) {
            $relative = str_replace( $plugin_dir . '/', '', $php_file );
            if( ! isset( $manifest[ $relative ] ) ) {
                $this->mark_addon_tampered( $plugin_slug, [
                    'Unauthorized PHP file not in manifest: ' . $relative,
                ] );
                
                return 'tampered';
            }
        }
        
        // 4) Verify PHP file headers contain our signature comment
        $header_check = $this->verify_php_header_signatures( $plugin_slug, $manifest );
        if( $header_check !== 'valid' ) {
            return $header_check;
        }
        
        // 5) Check for known nulled/patched patterns in PHP files
        $patch_check = $this->detect_nulled_patterns( $plugin_slug );
        if( $patch_check !== 'valid' ) {
            return $patch_check;
        }
        
        // All checks passed - clear any previous tamper flags
        $this->clear_tamper_flag( $plugin_slug );
        
        return 'valid';
    }
    
    /**
     * Sanitize a plugin slug to prevent directory traversal.
     * Only allows alphanumeric characters, hyphens, and underscores.
     */
    public static function sanitize_slug( string $slug ): string {
        return preg_replace( '/[^a-zA-Z0-9_-]/', '', $slug );
    }
    
    /**
     * Detect common nulled/patched plugin patterns:
     * - License check bypasses
     * - Known nulling tool signatures
     * - Suspicious eval/base64 injections
     * - Removed or stubbed license verification functions
     */
    private function detect_nulled_patterns( string $plugin_slug ): string {
        $plugin_slug = self::sanitize_slug( $plugin_slug );
        $plugin_dir  = WP_PLUGIN_DIR . '/' . $plugin_slug;
        if( ! is_dir( $plugin_dir ) ) return 'valid';
        
        $suspicious_patterns = [
            '/\b(nulled|cracked|patched|warez|gpl\s*club|gpldl)\b/i',
            '/eval\s*\(\s*base64_decode\s*\(/i',
            '/eval\s*\(\s*gzinflate\s*\(/i',
            '/eval\s*\(\s*str_rot13\s*\(/i',
            '/\$GLOBALS\s*\[\s*[\'"][a-z0-9_]{30,}[\'"]\s*\]/i',
            '/preg_replace\s*\(\s*[\'"]\/[^\/]*\/e[\'"]/i',
        ];
        
        $flagged_files = [];
        $php_files     = $this->get_php_files_recursive( $plugin_dir );
        
        foreach( $php_files as $file ) {
            $content = file_get_contents( $file );
            if( $content === false ) continue;
            
            foreach( $suspicious_patterns as $pattern ) {
                if( preg_match( $pattern, $content, $matches ) ) {
                    $relative        = str_replace( $plugin_dir . '/', '', $file );
                    $flagged_files[] = $relative . ' (suspicious: ' . trim( $matches[0] ) . ')';
                    break; // One match per file is enough
                }
            }
        }
        
        if( ! empty( $flagged_files ) ) {
            $this->mark_addon_tampered( $plugin_slug, $flagged_files, 'patched' );
            
            return 'patched';
        }
        
        return 'valid';
    }
    
    /**
     * Get all PHP files recursively in a directory
     */
    private function get_php_files_recursive( string $dir, int $max_depth = 10 ): array {
        $files    = [];
        $real_dir = realpath( $dir );
        if( $real_dir === false ) return $files;
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS ),
            RecursiveIteratorIterator::SELF_FIRST
        );
        $iterator->setMaxDepth( $max_depth );
        
        foreach( $iterator as $file ) {
            if( ! $file->isFile() || $file->getExtension() !== 'php' ) continue;
            
            // Ensure file is actually within the plugin directory (prevent symlink escapes)
            $real_path = realpath( $file->getPathname() );
            if( $real_path === false || strpos( $real_path, $real_dir ) !== 0 ) continue;
            
            $files[] = $file->getPathname();
        }
        
        return $files;
    }
    
    /**
     * Mark an addon as tampered in the options
     */
    private function mark_addon_tampered( string $plugin_slug, array $files, string $reason = 'tampered' ): void {
        $tampered = get_option( $this->tampered_option, [] );
        // Don't overwrite detected_at if already flagged (preserve grace period start)
        if( isset( $tampered[ $plugin_slug ] ) ) {
            $tampered[ $plugin_slug ]['files']  = $files;
            $tampered[ $plugin_slug ]['reason'] = $reason;
        } else {
            $tampered[ $plugin_slug ] = [
                'files'       => $files,
                'reason'      => $reason,
                'detected_at' => current_time( 'mysql' ),
            ];
        }
        update_option( $this->tampered_option, $tampered );
    }
    
    /**
     * Check if an addon has a legacy license from the old gVectors license system.
     * Results are cached locally and revalidated daily to avoid repeated API calls.
     *
     * Returns cached legacy license data or false if no legacy license.
     */
    private function check_legacy_license( string $plugin_slug ): array {
        $cached = $this->get_cached_legacy_license( $plugin_slug );
        if( $cached !== false ) return $cached;
        
        $response = $this->licenseService->apiService->check_legacy_license( $plugin_slug );
        
        if( ! empty( $response['success'] ) && ! empty( $response['data'] ) ) {
            $data        = $response['data'];
            $legacy_data = [
                'has_license'  => ! empty( $data['has_legacy_license'] ),
                'status'       => $data['status'] ?? '',
                'expired'      => ! empty( $data['expired'] ),
                'expired_time' => isset( $data['expired_time'] ) ? (int) $data['expired_time'] : 0,
                'last_checked' => time(),
            ];
            $this->save_cached_legacy_license( $plugin_slug, $legacy_data );
            
            return $legacy_data;
        }
        
        // API call failed — cache a negative result with a shorter TTL (1 hour)
        // so we retry sooner, but don't hammer the server on every cron run
        $negative = [
            'has_license'  => false,
            'status'       => '',
            'expired'      => false,
            'expired_time' => 0,
            'last_checked' => time() - $this->config->get_legacy_check_period() + HOUR_IN_SECONDS,
        ];
        $this->save_cached_legacy_license( $plugin_slug, $negative );
        
        return $negative;
    }
    
    /**
     * Get cached legacy license data for a slug.
     * Returns the cached array or false if not cached or stale.
     */
    private function get_cached_legacy_license( string $plugin_slug ) {
        $all_legacy = get_option( $this->legacy_licenses_option, [] );
        if( ! isset( $all_legacy[ $plugin_slug ] ) ) return false;
        
        $cached = $all_legacy[ $plugin_slug ];
        $last   = isset( $cached['last_checked'] ) ? (int) $cached['last_checked'] : 0;
        
        // Stale if older than LEGACY_CHECK_PERIOD
        if( ( time() - $last ) > $this->config->get_legacy_check_period() ) return false;
        
        return $cached;
    }
    
    // ==========================================
    // Activation Gate
    // ==========================================
    
    /**
     * Save legacy license check result to the persistent cache.
     */
    private function save_cached_legacy_license( string $plugin_slug, array $data ): void {
        $all_legacy                 = get_option( $this->legacy_licenses_option, [] );
        $all_legacy[ $plugin_slug ] = $data;
        update_option( $this->legacy_licenses_option, $all_legacy );
    }
    
    // ==========================================
    // Signature & Piracy Verification
    // ==========================================
    
    /**
     * Clear tamper flag for an addon
     */
    private function clear_tamper_flag( string $plugin_slug ): void {
        $tampered = get_option( $this->tampered_option, [] );
        if( isset( $tampered[ $plugin_slug ] ) ) {
            unset( $tampered[ $plugin_slug ] );
            update_option( $this->tampered_option, $tampered );
        }
        
        // Also clear the seen flag
        $seen = get_option( $this->tamper_dismissed_option, [] );
        if( isset( $seen[ $plugin_slug ] ) ) {
            unset( $seen[ $plugin_slug ] );
            update_option( $this->tamper_dismissed_option, $seen );
        }
    }
    
    /**
     * Track legacy-licensed addons that have expired licenses for admin notice.
     */
    private function update_legacy_notice( string $plugin_slug, array $legacy_data ): void {
        $notices = get_option( $this->legacy_notice_option, [] );
        
        if( ! empty( $legacy_data['expired'] ) ) {
            $plugin_file = $this->get_installed_plugin_file( $plugin_slug );
            $plugin_name = $plugin_slug;
            if( $plugin_file ) {
                $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file, false, false );
                $plugin_name = $plugin_data['Name'] ?? $plugin_slug;
            }
            $notices[ $plugin_slug ] = [
                'plugin_name'  => $plugin_name,
                'status'       => 'expired',
                'expired_time' => $legacy_data['expired_time'] ?? 0,
            ];
        } else {
            // Active legacy license — remove any notice
            unset( $notices[ $plugin_slug ] );
        }
        
        update_option( $this->legacy_notice_option, $notices );
    }
    
    /**
     * Verify that PHP files contain valid embedded signature headers.
     * Checks both @addon-signature (HMAC hash) and @addon-domain (base64 site URL).
     * Validates the domain hash matches this site and the signature hash matches the manifest.
     */
    private function verify_php_header_signatures( string $plugin_slug, array $manifest ): string {
        $plugin_dir     = WP_PLUGIN_DIR . '/' . $plugin_slug;
        $site_domain    = LicenseModule::get_site_domain();
        $missing_sigs   = [];
        $invalid_domain = [];
        $invalid_hash   = [];
        
        foreach( $manifest as $relative_path => $info ) {
            if( strpos( $relative_path, '..' ) !== false || strpos( $relative_path, '/' ) === 0 ) continue;
            $file_path = $plugin_dir . '/' . $relative_path;
            if( ! file_exists( $file_path ) ) continue;
            if( pathinfo( $file_path, PATHINFO_EXTENSION ) !== 'php' ) continue;
            
            $header = file_get_contents( $file_path, false, null, 0, 4096 );
            if( $header === false ) continue;
            
            // Extract @addon-signature hash
            if( ! preg_match( '/\/\*\s*@addon-signature\s+([a-f0-9]{64})\s*\*\//', $header, $sig_match ) ) {
                $missing_sigs[] = $relative_path . ' (missing @addon-signature header)';
                continue;
            }
            
            // Extract @addon-domain base64-encoded site URL
            if( ! preg_match( '/\/\*\s*@addon-domain\s+([A-Za-z0-9+\/=]+)\s*\*\//', $header, $domain_match ) ) {
                $missing_sigs[] = $relative_path . ' (missing @addon-domain header)';
                continue;
            }
            
            // Validate domain matched this site
            $signed_domain = base64_decode( $domain_match[1] );
            if( $signed_domain === false ) {
                $invalid_domain[] = $relative_path . ' (corrupted domain encoding)';
                continue;
            }
            if( LicenseModule::normalize_domain( $signed_domain ) !== LicenseModule::normalize_domain( $site_domain ) ) {
                $invalid_domain[] = $relative_path . ' (domain: ' . $signed_domain . ' vs ' . $site_domain . ')';
                continue;
            }
            
            // Validate signature hash matches the one stored in manifest
            if( ! empty( $info['signature'] ) && $sig_match[1] !== $info['signature'] ) {
                $invalid_hash[] = $relative_path . ' (signature hash mismatch)';
            }
        }
        
        if( ! empty( $missing_sigs ) ) {
            $this->mark_addon_tampered( $plugin_slug, $missing_sigs, 'no_signatures' );
            
            return 'no_signatures';
        }
        
        if( ! empty( $invalid_domain ) ) {
            $this->mark_addon_tampered( $plugin_slug, $invalid_domain, 'domain_mismatch' );
            
            return 'domain_mismatch';
        }
        
        if( ! empty( $invalid_hash ) ) {
            $this->mark_addon_tampered( $plugin_slug, $invalid_hash );
            
            return 'tampered';
        }
        
        return 'valid';
    }
    
    /**
     * Activate an installed addon
     */
    public function activate( string $plugin_file ): array {
        if( ! current_user_can( 'activate_plugins' ) ) {
            return [ 'success' => false, 'error' => __( 'Permission denied', 'gvectors' ) ];
        }
        
        $result = activate_plugin( $plugin_file );
        
        if( is_wp_error( $result ) ) {
            return [ 'success' => false, 'error' => $result->get_error_message() ];
        }
        
        return [ 'success' => true, 'message' => __( 'Addon activated successfully', 'gvectors' ) ];
    }
    
    /**
     * Deactivate an addon
     */
    public function deactivate_addon( string $plugin_file ): array {
        if( ! current_user_can( 'activate_plugins' ) ) {
            return [ 'success' => false, 'error' => __( 'Permission denied', 'gvectors' ) ];
        }
        
        deactivate_plugins( $plugin_file );
        
        return [ 'success' => true, 'message' => __( 'Addon deactivated successfully', 'gvectors' ) ];
    }
    
    // ==========================================
    // Legacy License Checking (old gVectors system)
    // ==========================================
    
    public function check_for_updates( $transient ) {
        if( empty( $transient->checked ) ) return $transient;
        
        $licenses = $this->licenseService->get_all();
        
        // Clear cached addon data only once per request, so we fetch fresh version info without DDOSing the server
        if( ! $this->update_check_done ) {
            delete_transient( $this->all_addons_transient_name );
            $this->update_check_done = true;
        }
        
        // Fetch all addon info from proxy server (version, requires, tested, etc.)
        $proxy_addons = $this->get_proxy_addons_map();
        if( empty( $proxy_addons ) ) return $transient;
        
        $site_domain = LicenseModule::get_site_domain();
        
        // Track which plugin files already got a licensed update (so we don't override with unlicensed)
        $licensed_plugin_files = [];
        
        if( ! empty( $licenses ) ) {
            foreach( $licenses as $product_id => $license ) {
                if( empty( $license['license_key'] ) ) continue;
                
                // Only active/trial licenses get updates - expired licenses do NOT
                if( ! in_array( $license['status'], [ 'active', 'trial' ], true ) ) continue;
                
                // Verify license is activated for this specific domain
                if( ! empty( $site_domain ) ) {
                    $activated_site = $license['site_domain'] ?? '';
                    if( ! empty( $activated_site ) && LicenseModule::normalize_domain( $activated_site ) !== LicenseModule::normalize_domain( $site_domain ) ) {
                        continue;
                    }
                }
                
                // Check expiry date - do not offer updates for expired licenses
                $expires_ts = ! empty( $license['expires_at'] ) ? strtotime( $license['expires_at'] ) : false;
                if( $expires_ts !== false && $expires_ts < time() ) {
                    continue;
                }
                
                $plugin_slug = $license['plugin_slug'] ?? '';
                if( empty( $plugin_slug ) ) continue;
                
                $plugin_file = $this->get_installed_plugin_file( $plugin_slug );
                if( ! $plugin_file ) continue;
                
                
                $current_version = $transient->checked[ $plugin_file ] ?? '0.0.0';
                
                // Use proxy server version (from addon file header) instead of local options
                $proxy_info     = $proxy_addons[ $plugin_slug ] ?? [];
                $latest_version = ! empty( $proxy_info['version'] ) ? $proxy_info['version'] : '';
                
                if( $latest_version && version_compare( $latest_version, $current_version, '>' ) ) {
                    $update              = new stdClass();
                    $update->slug        = $plugin_slug;
                    $update->plugin      = $plugin_file;
                    $update->new_version = $latest_version;
                    $update->url         = ! empty( $proxy_info['plugin_uri'] ) ? $proxy_info['plugin_uri'] : '';
                    
                    // Set package to the proxy server's wp-download endpoint
                    // The proxy validates the license and 302 redirects to a temporary download URL
                    $update->package = add_query_arg( [
                                                          'product_id'  => $product_id,
                                                          'license_key' => $license['license_key'],
                                                          'site_domain' => LicenseModule::get_site_domain(),
                                                          'site_token'  => LicenseModule::get_site_token(),
                                                      ],
                                                      trailingslashit(
                                                          $this->config->get_proxy_server_url()
                                                      ) . 'addon/wp-download' );
                    
                    $update->icons        = ! empty( $proxy_info['logo'] ) ? [ '1x' => $proxy_info['logo'], '2x' => $proxy_info['logo'] ] : [];
                    $update->banners      = [];
                    $update->tested       = ! empty( $proxy_info['tested'] ) ? $proxy_info['tested'] : '';
                    $update->requires     = ! empty( $proxy_info['requires'] ) ? $proxy_info['requires'] : '';
                    $update->requires_php = ! empty( $proxy_info['requires_php'] ) ? $proxy_info['requires_php'] : '';
                    
                    $transient->response[ $plugin_file ] = $update;
                    $licensed_plugin_files[]             = $plugin_file;
                }
            }
        }
        
        // Also check installed addons with an active (non-expired) legacy license.
        // These users purchased before the new Paddle system and still deserve updates.
        $all_legacy = get_option( $this->legacy_licenses_option, [] );
        if( ! empty( $all_legacy ) ) {
            foreach( $all_legacy as $plugin_slug => $legacy ) {
                // Only active, non-expired legacy licenses get updates
                if( empty( $legacy['has_license'] ) || ! empty( $legacy['expired'] ) ) continue;
                
                $plugin_file = $this->get_installed_plugin_file( $plugin_slug );
                if( ! $plugin_file ) continue;
                
                // Skip if already handled by a new Paddle license above
                if( in_array( $plugin_file, $licensed_plugin_files, true ) ) continue;
                
                // Only process known gVectors addons from the proxy
                if( ! isset( $proxy_addons[ $plugin_slug ] ) ) continue;
                
                // Migrate legacy license to new system eagerly — even without a pending update.
                // On success, save() stores the license in gvectors_licenses so the Paddle loop
                // handles this slug on the next check_for_updates() call.
                $migrated = $this->maybe_migrate_legacy_license( $plugin_slug );
                
                $proxy_info      = $proxy_addons[ $plugin_slug ];
                $latest_version  = ! empty( $proxy_info['version'] ) ? $proxy_info['version'] : '';
                $current_version = $transient->checked[ $plugin_file ] ?? '0.0.0';
                
                if( $latest_version && version_compare( $latest_version, $current_version, '>' ) ) {
                    $update              = new stdClass();
                    $update->slug        = $plugin_slug;
                    $update->plugin      = $plugin_file;
                    $update->new_version = $latest_version;
                    $update->url         = ! empty( $proxy_info['plugin_uri'] ) ? $proxy_info['plugin_uri'] : '';
                    if( $migrated && ! empty( $migrated['license_key'] ) ) {
                        $update->package = add_query_arg( [
                                                              'product_id'  => $migrated['product_id'],
                                                              'license_key' => $migrated['license_key'],
                                                              'site_domain' => LicenseModule::get_site_domain(),
                                                              'site_token'  => LicenseModule::get_site_token(),
                                                          ],
                                                          trailingslashit(
                                                              $this->config->get_proxy_server_url()
                                                          ) . 'addon/wp-download' );
                    } else {
                        $update->package = add_query_arg( [
                                                              'plugin_slug' => $plugin_slug,
                                                              'site_domain' => LicenseModule::get_site_domain(),
                                                              'site_token'  => LicenseModule::get_site_token(),
                                                          ], trailingslashit( $this->config->get_proxy_server_url() ) . 'addon/legacy-wp-download' );
                    }
                    
                    $update->icons        = ! empty( $proxy_info['logo'] ) ? [ '1x' => $proxy_info['logo'], '2x' => $proxy_info['logo'] ] : [];
                    $update->banners      = [];
                    $update->tested       = ! empty( $proxy_info['tested'] ) ? $proxy_info['tested'] : '';
                    $update->requires     = ! empty( $proxy_info['requires'] ) ? $proxy_info['requires'] : '';
                    $update->requires_php = ! empty( $proxy_info['requires_php'] ) ? $proxy_info['requires_php'] : '';
                    
                    $transient->response[ $plugin_file ] = $update;
                    $licensed_plugin_files[]             = $plugin_file;
                }
            }
        }
        
        // Also check installed addons that have a new version but NO active license
        // Show them as available updates but with empty package (download blocked)
        if( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all_plugins = get_plugins();
        
        // Batch-check legacy license status for any installed gVectors addon slugs
        // that are not yet in the local cache (e.g. first run before cron has executed).
        // This prevents showing "Automatic update is unavailable" for legitimate legacy users.
        $uncached_slugs = [];
        foreach( $all_plugins as $_pf => $_pd ) {
            if( in_array( $_pf, $licensed_plugin_files, true ) ) continue;
            $_slug = dirname( $_pf );
            if( $_slug === '.' || $_slug === $this->config->get_core_plugin_slug() ) continue;
            if( ! isset( $proxy_addons[ $_slug ] ) ) continue;
            if( ! isset( $all_legacy[ $_slug ] ) ) $uncached_slugs[] = $_slug;
        }
        if( ! empty( $uncached_slugs ) ) {
            $this->check_legacy_licenses_batch( array_unique( $uncached_slugs ) );
            $all_legacy = get_option( $this->legacy_licenses_option, [] );
            // Apply legacy download URLs for newly-discovered active licenses
            foreach( $uncached_slugs as $_slug ) {
                $_legacy = $all_legacy[ $_slug ] ?? [];
                if( empty( $_legacy['has_license'] ) || ! empty( $_legacy['expired'] ) ) continue;
                $_plugin_file = $this->get_installed_plugin_file( $_slug );
                if( ! $_plugin_file || in_array( $_plugin_file, $licensed_plugin_files, true ) ) continue;
                if( ! isset( $proxy_addons[ $_slug ] ) ) continue;
                // Migrate eagerly — even without a pending update
                $_migrated = $this->maybe_migrate_legacy_license( $_slug );
                $_proxy    = $proxy_addons[ $_slug ];
                $_latest   = $_proxy['version'] ?? '';
                $_current  = $transient->checked[ $_plugin_file ] ?? '0.0.0';
                if( ! $_latest || ! version_compare( $_latest, $_current, '>' ) ) continue;
                $_update              = new stdClass();
                $_update->slug        = $_slug;
                $_update->plugin      = $_plugin_file;
                $_update->new_version = $_latest;
                $_update->url         = $_proxy['plugin_uri'] ?? '';
                if( $_migrated && ! empty( $_migrated['license_key'] ) ) {
                    $_update->package = add_query_arg( [
                                                           'product_id'  => $_migrated['product_id'],
                                                           'license_key' => $_migrated['license_key'],
                                                           'site_domain' => LicenseModule::get_site_domain(),
                                                           'site_token'  => LicenseModule::get_site_token(),
                                                       ],
                                                       trailingslashit(
                                                           $this->config->get_proxy_server_url()
                                                       ) . 'addon/wp-download' );
                } else {
                    $_update->package = add_query_arg( [
                                                           'plugin_slug' => $_slug,
                                                           'site_domain' => LicenseModule::get_site_domain(),
                                                           'site_token'  => LicenseModule::get_site_token(),
                                                       ], trailingslashit( $this->config->get_proxy_server_url() ) . 'addon/legacy-wp-download' );
                }
                $_update->icons                       = ! empty( $_proxy['logo'] ) ? [ '1x' => $_proxy['logo'], '2x' => $_proxy['logo'] ] : [];
                $_update->banners                     = [];
                $_update->tested                      = $_proxy['tested'] ?? '';
                $_update->requires                    = $_proxy['requires'] ?? '';
                $_update->requires_php                = $_proxy['requires_php'] ?? '';
                $transient->response[ $_plugin_file ] = $_update;
                $licensed_plugin_files[]              = $_plugin_file;
            }
        }
        
        foreach( $all_plugins as $plugin_file => $plugin_data ) {
            // Skip if already handled by licensed update above
            if( in_array( $plugin_file, $licensed_plugin_files, true ) ) continue;
            
            $slug = dirname( $plugin_file );
            if( $slug === '.' || $slug === $this->config->get_core_plugin_slug() ) continue;
            
            // Only process known gVectors addons from the proxy
            if( ! isset( $proxy_addons[ $slug ] ) ) continue;
            
            $proxy_info      = $proxy_addons[ $slug ];
            $latest_version  = ! empty( $proxy_info['version'] ) ? $proxy_info['version'] : '';
            $current_version = $transient->checked[ $plugin_file ] ?? '0.0.0';
            
            if( $latest_version && version_compare( $latest_version, $current_version, '>' ) ) {
                $update               = new stdClass();
                $update->slug         = $slug;
                $update->plugin       = $plugin_file;
                $update->new_version  = $latest_version;
                $update->url          = ! empty( $proxy_info['plugin_uri'] ) ? $proxy_info['plugin_uri'] : '';
                $update->package      = ''; // Empty package — download blocked without active license
                $update->icons        = ! empty( $proxy_info['logo'] ) ? [ '1x' => $proxy_info['logo'], '2x' => $proxy_info['logo'] ] : [];
                $update->banners      = [];
                $update->tested       = ! empty( $proxy_info['tested'] ) ? $proxy_info['tested'] : '';
                $update->requires     = ! empty( $proxy_info['requires'] ) ? $proxy_info['requires'] : '';
                $update->requires_php = ! empty( $proxy_info['requires_php'] ) ? $proxy_info['requires_php'] : '';
                
                $transient->response[ $plugin_file ] = $update;
            }
        }
        
        return $transient;
    }
    
    /**
     * Attempt to migrate an active legacy gVectors license to the new Paddle license system.
     *
     * Calls addon/activate-legacy-license on the proxy server, which creates a row in the
     * new licenses table using the original activation key.  On success the returned data
     * is stored in gvectors_licenses, so from this point forward:
     *   - validate / batch-validate resolve the license from the new table
     *   - check_for_updates() builds an addon/wp-download package URL (not legacy-wp-download)
     *   - downloaded files arrive with a manifest + signed PHP headers
     *   - the addon never re-enters the legacy scan scope (it's in $licensed_slugs)
     *
     * The call is idempotent — running it multiple times is safe.
     * Rate-limited: won't retry for 6 hours after a failure to avoid API spam.
     *
     * @return array|null  [ 'product_id' => ..., 'license_key' => ... ] on success, null on failure
     */
    private function maybe_migrate_legacy_license( string $plugin_slug ): ?array {
        // Rate limit: don't retry within 6 hours after a failure
        $attempt_key = 'gvectors_lgc_mig_' . md5( $plugin_slug );
        if( get_transient( $attempt_key ) ) return null;
        
        $response = $this->licenseService->apiService->activate_legacy_license( $plugin_slug );
        
        if( ! empty( $response['success'] ) && ! empty( $response['data'] ) ) {
            $data       = $response['data'];
            $product_id = $data['product_id'] ?? '';
            if( ! empty( $product_id ) && ! empty( $data['license_key'] ) ) {
                $this->licenseService->save( $product_id, $data );
                
                // Remove slug from legacy caches — it's now a first-class new-system license
                $all_legacy = get_option( $this->legacy_licenses_option, [] );
                if( isset( $all_legacy[ $plugin_slug ] ) ) {
                    unset( $all_legacy[ $plugin_slug ] );
                    update_option( $this->legacy_licenses_option, $all_legacy );
                }
                $notices = get_option( $this->legacy_notice_option, [] );
                if( isset( $notices[ $plugin_slug ] ) ) {
                    unset( $notices[ $plugin_slug ] );
                    update_option( $this->legacy_notice_option, $notices );
                }
                
                return [
                    'product_id'  => $product_id,
                    'license_key' => $data['license_key'],
                ];
            }
        }
        
        // Cache failure to prevent repeated attempts on every page load
        set_transient( $attempt_key, 1, 6 * HOUR_IN_SECONDS );
        
        return null;
    }
    
    /**
     * Batch-check legacy licenses for multiple addon slugs.
     * Populates the local cache for all slugs in one API call.
     */
    private function check_legacy_licenses_batch( array $plugin_slugs ): void {
        if( empty( $plugin_slugs ) ) return;
        
        $response = $this->licenseService->apiService->check_legacy_licenses_batch( $plugin_slugs );
        
        if( ! empty( $response['success'] ) && ! empty( $response['data']['addons'] ) ) {
            $all_legacy = get_option( $this->legacy_licenses_option, [] );
            foreach( $response['data']['addons'] as $slug => $data ) {
                $all_legacy[ $slug ] = [
                    'has_license'  => ! empty( $data['has_legacy_license'] ),
                    'status'       => $data['status'] ?? '',
                    'expired'      => ! empty( $data['expired'] ),
                    'expired_time' => isset( $data['expired_time'] ) ? (int) $data['expired_time'] : 0,
                    'last_checked' => time(),
                ];
            }
            // Also cache negative results for slugs not returned by the server
            foreach( $plugin_slugs as $slug ) {
                if( ! isset( $all_legacy[ $slug ] ) || $all_legacy[ $slug ]['last_checked'] < time() - 60 ) {
                    $all_legacy[ $slug ] = [
                        'has_license'  => false,
                        'status'       => '',
                        'expired'      => false,
                        'expired_time' => 0,
                        'last_checked' => time(),
                    ];
                }
            }
            update_option( $this->legacy_licenses_option, $all_legacy );
        }
    }
    
    /**
     * Intercept WordPress updater package downloads to block tampered addons with a visible error.
     * This hooks into 'upgrader_pre_download' so the user sees a clear message in the update UI.
     * The actual download is handled by the proxy server's addon/wp-download endpoint (302 redirect).
     */
    public function block_tampered_update_download( $reply, $package ) {
        if( is_wp_error( $reply ) || ! is_string( $package ) ) return $reply;
        
        // Intercept our own addon download URLs (both regular and legacy-wp-download endpoints)
        $is_regular = strpos( $package, 'addon/wp-download' ) !== false;
        $is_legacy  = strpos( $package, 'addon/legacy-wp-download' ) !== false;
        if( ! $is_regular && ! $is_legacy ) return $reply;
        
        $parsed = [];
        parse_str( wp_parse_url( $package, PHP_URL_QUERY ) ?: '', $parsed );
        
        if( $is_legacy ) {
            // Legacy download — plugin_slug is a direct query param
            $plugin_slug = self::sanitize_slug( $parsed['plugin_slug'] ?? '' );
        } else {
            // Regular download — resolve plugin_slug via product_id
            $product_id = $parsed['product_id'] ?? '';
            if( empty( $product_id ) ) return $reply;
            $license     = $this->licenseService->get( $product_id );
            $plugin_slug = $license['plugin_slug'] ?? '';
        }
        
        if( $plugin_slug && $this->is_addon_tampered( $plugin_slug ) ) {
            return new WP_Error(
                'tampered_addon',
                __(
                    'This addon cannot be updated because its files have been modified or are not original. To resolve this, please: 1) Go to Plugins and deactivate, then delete this addon. 2) Visit the gVectors Store Addons page and make sure your license is active. 3) Re-install the addon from the gVectors Store Addons page. Once re-installed, everything will work normally again.',
                    'gvectors'
                )
            );
        }
        
        return $reply;
    }
    
    /**
     * Get addon status: 'not_installed', 'installed', 'active'
     */
    public function get_status( string $plugin_slug ): string {
        if( ! $this->is_installed( $plugin_slug ) ) return 'not_installed';
        if( $this->is_active( $plugin_slug ) ) return 'active';
        
        return 'installed';
    }
    
    /**
     * Check if an addon is installed
     */
    public function is_installed( string $plugin_slug ): bool {
        return ! empty( $this->get_installed_plugin_file( $plugin_slug ) );
    }
    
    /**
     * Check if an addon is active
     */
    public function is_active( string $plugin_slug ): bool {
        $file = $this->get_installed_plugin_file( $plugin_slug );
        if( empty( $file ) ) return false;
        
        return is_plugin_active( $file );
    }
    
    /**
     * Intercept plugin activation. If the plugin is a known gVectors addon,
     * run full validity checks (license, signatures, domain, nulled patterns).
     * Block activation with wp_die() if any check fails.
     */
    public function validate_on_activation( string $plugin_file ): void {
        $slug = dirname( $plugin_file );
        if( $slug === '.' || $slug === $this->config->get_core_plugin_slug() ) return;
        
        // Skip all checks on development/local/staging environments
        if( LicenseModule::is_development_site() ) return;
        
        // Check if this is a known gVectors addon
        $all_addon_slugs = $this->get_all_addon_slugs_from_proxy();
        if( ! $this->is_known_addon( $slug, $all_addon_slugs ) ) return;
        
        $reasons = [];
        
        // 1) License check — new Paddle license OR legacy gVectors license
        $has_new_license    = $this->addon_has_license( $slug );
        $has_legacy_license = false;
        if( ! $has_new_license ) {
            $has_legacy_license = $this->has_legacy_license( $slug );
        }
        if( ! $has_new_license && ! $has_legacy_license ) {
            $reasons[] = __( 'No valid license found for this addon on this site.', 'gvectors' );
        }
        
        // 2) Signature & integrity checks
        $sig_result = $this->verify_addon_signatures( $slug );
        if( $sig_result !== 'valid' && $sig_result !== 'legacy_valid' ) {
            $labels    = [
                'no_manifest'     => __( 'Missing signature manifest — addon was not installed through the official channel.', 'gvectors' ),
                'tampered'        => __( 'File integrity check failed — one or more addon files have been modified.', 'gvectors' ),
                'domain_mismatch' => __( 'Domain mismatch — this addon copy is signed for a different website.', 'gvectors' ),
                'no_signatures'   => __( 'Missing PHP header signatures — addon files lack required security headers.', 'gvectors' ),
                'patched'         => __( 'Nulled/patched code detected — this addon appears to be a pirated copy.', 'gvectors' ),
            ];
            $reasons[] = $labels[ $sig_result ] ?? __( 'Addon verification failed.', 'gvectors' );
        }
        
        if( ! empty( $reasons ) ) {
            // Store a transient so we can show an admin notice on redirect back
            set_transient( 'gvectors_activation_blocked_' . $slug, $reasons, 60 );
            
            wp_die(
                '<h2>' . esc_html__( 'gVectors Addon Activation Blocked', 'gvectors' ) . '</h2>'
                . '<p><strong>' . esc_html( $slug ) . '</strong></p>'
                . '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $reasons ) ) . '</li></ul>'
                . '<p>' . esc_html__( 'Please install a valid licensed copy from the gVectors Store Addons dashboard.', 'gvectors' ) . '</p>',
                esc_html__( 'Activation Blocked', 'gvectors' ),
                [ 'back_link' => true ]
            );
        }
    }
    
    /**
     * Fetch all known addon slugs from the proxy server.
     * Returns array of slug strings.
     */
    private function get_all_addon_slugs_from_proxy(): array {
        $response = $this->licenseService->apiService->get_all_addons();
        if( empty( $response['success'] ) || empty( $response['data']['addons'] ) ) {
            return [];
        }
        
        return array_column( $response['data']['addons'], 'slug' );
    }
    
    /**
     * Check if a plugin slug is a known gVectors addon by querying the proxy's full addon list.
     */
    private function is_known_addon( string $plugin_slug, array $all_addon_slugs = [] ): bool {
        if( ! empty( $all_addon_slugs ) ) {
            return in_array( $plugin_slug, $all_addon_slugs, true );
        }
        
        // Fallback: check by naming convention
        return ( strpos( $plugin_slug, $this->config->get_core_plugin_slug() . '-' ) === 0 || strpos( $plugin_slug, $this->config->get_core_plugin_slug() . '_' ) === 0 );
    }
    
    /**
     * Check if an addon slug has an associated license (local) or is a known addon from proxy.
     */
    private function addon_has_license( string $plugin_slug ): bool {
        $licenses = $this->licenseService->get_all();
        foreach( $licenses as $license ) {
            if( isset( $license['plugin_slug'] ) && $license['plugin_slug'] === $plugin_slug ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Quick check: does this addon have a legacy license (from cache)?
     * Returns true if the cached legacy license exists and is valid.
     */
    private function has_legacy_license( string $plugin_slug ): bool {
        $legacy = $this->check_legacy_license( $plugin_slug );
        
        return ! empty( $legacy['has_license'] );
    }
    
    /**
     * Verify all installed addons — uses the proxy's full addon list (not just local licenses).
     * Checks every installed plugin that matches a known addon slug from the proxy.
     * Uses a grace period: show FATAL notice first, deactivate after TAMPER_GRACE_DAYS.
     */
    public function verify_all_addon_signatures(): void {
        // Skip all checks on development/local/staging environments
        if( LicenseModule::is_development_site() ) return;
        
        // Get the full list of known addon slugs from the proxy server
        $all_addon_slugs = $this->get_all_addon_slugs_from_proxy();
        
        // Collect locally licensed plugin slugs
        $licenses       = $this->licenseService->get_all();
        $licensed_slugs = [];
        foreach( $licenses as $product_id => $license ) {
            $slug = $license['plugin_slug'] ?? '';
            if( ! empty( $slug ) ) $licensed_slugs[] = $slug;
        }
        
        // Scan for installed addons that are known to the proxy but have no license
        $this->scan_unlicensed_addons( $licensed_slugs, $all_addon_slugs );
        
        // Verify signatures for all installed plugins that match known addon slugs
        if( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all_plugins = get_plugins();
        
        foreach( $all_plugins as $file => $data ) {
            $slug = dirname( $file );
            if( $slug === '.' || $slug === $this->config->get_core_plugin_slug() ) continue;
            
            // Check against proxy's known addon list
            if( ! $this->is_known_addon( $slug, $all_addon_slugs ) ) continue;
            if( ! $this->is_installed( $slug ) ) continue;
            
            $result = $this->verify_addon_signatures( $slug );
            if( $result !== 'valid' && $result !== 'legacy_valid' ) {
                $this->maybe_deactivate_tampered( $slug );
            }
        }
    }
    
    /**
     * Scan for installed plugins that are known gVectors addons (from the proxy list) but have no license.
     * These could be pirated copies installed manually, OR legacy-licensed installations.
     * Checks legacy license before flagging as tampered.
     */
    private function scan_unlicensed_addons( array $licensed_slugs, array $all_addon_slugs = [] ): void {
        if( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $all_plugins = get_plugins();
        
        // Two buckets for unlicensed known addons:
        // - $needs_legacy_check: no manifest → must verify legacy license or flag as tampered
        // - $needs_legacy_refresh: has manifest (updated via legacy-wp-download) → refresh legacy
        //   cache so check_for_updates() keeps offering update packages; no tamper action here
        //   since verify_all_addon_signatures() handles file integrity for manifest-having addons.
        $needs_legacy_check   = [];
        $needs_legacy_refresh = [];
        foreach( $all_plugins as $file => $data ) {
            $slug = dirname( $file );
            if( $slug === '.' || $slug === $this->config->get_core_plugin_slug() ) continue;
            if( ! $this->is_known_addon( $slug, $all_addon_slugs ) ) continue;
            if( in_array( $slug, $licensed_slugs, true ) ) continue;
            if( ! is_plugin_active( $file ) ) continue;
            
            $manifest_file = WP_PLUGIN_DIR . '/' . $slug . '/.addon-signatures.json';
            if( ! file_exists( $manifest_file ) ) {
                $needs_legacy_check[] = $slug;
            } else {
                $needs_legacy_refresh[] = $slug;
            }
        }
        
        // Batch-check legacy licenses for all unlicensed addons in a single API call
        $all_to_check = array_values( array_unique( array_merge( $needs_legacy_check, $needs_legacy_refresh ) ) );
        if( ! empty( $all_to_check ) ) {
            $this->check_legacy_licenses_batch( $all_to_check );
        }
        
        // No-manifest addons: apply tamper/clear logic based on legacy license presence
        foreach( $needs_legacy_check as $slug ) {
            $legacy = $this->get_cached_legacy_license( $slug );
            if( $legacy !== false && ! empty( $legacy['has_license'] ) ) {
                // Legacy licensed — not piracy. Track for admin notice if expired.
                $this->clear_tamper_flag( $slug );
                $this->update_legacy_notice( $slug, $legacy );
                
                // Proactively migrate active legacy licenses to the new system.
                // Once migrated, the slug enters $licensed_slugs and exits this scan
                // scope permanently — future updates go through addon/wp-download.
                if( empty( $legacy['expired'] ) ) {
                    $this->maybe_migrate_legacy_license( $slug );
                }
                
                continue;
            }
            
            // No legacy license — suspicious, flag as tampered
            $this->mark_addon_tampered( $slug, [
                'Active gVectors addon without a valid license or signature manifest',
            ],                          'no_manifest' );
            $this->maybe_deactivate_tampered( $slug );
        }
        
        // Manifest-having addons: only refresh legacy notice so update offers stay active.
        // Tamper/integrity decisions are handled by verify_all_addon_signatures() above.
        foreach( $needs_legacy_refresh as $slug ) {
            $legacy = $this->get_cached_legacy_license( $slug );
            if( $legacy !== false && ! empty( $legacy['has_license'] ) ) {
                $this->update_legacy_notice( $slug, $legacy );
            }
        }
    }
    
    /**
     * Decide whether to deactivate a tampered addon based on the grace period.
     * Grace period: show FATAL notice for TAMPER_GRACE_DAYS days.
     * After the grace period AND admin has viewed the notice, deactivate.
     */
    private function maybe_deactivate_tampered( string $plugin_slug ): void {
        $tampered = get_option( $this->tampered_option, [] );
        if( ! isset( $tampered[ $plugin_slug ] ) ) return;
        
        $info        = $tampered[ $plugin_slug ];
        $detected_at = isset( $info['detected_at'] ) ? strtotime( $info['detected_at'] ) : false;
        if( $detected_at === false || $detected_at <= 0 ) {
            // Invalid timestamp — reset now so grace period starts fresh
            $tampered[ $plugin_slug ]['detected_at'] = current_time( 'mysql' );
            update_option( $this->tampered_option, $tampered );
            
            return;
        }
        $days_since = ( time() - $detected_at ) / DAY_IN_SECONDS;
        
        // Check if admin has seen the notice
        $seen           = get_option( $this->tamper_dismissed_option, [] );
        $admin_has_seen = ! empty( $seen[ $plugin_slug ] );
        
        // Deactivate after grace period if admin has viewed the notice
        if( $days_since >= $this->config->get_tamper_grace_days() && $admin_has_seen ) {
            $plugin_file = $this->get_installed_plugin_file( $plugin_slug );
            if( $plugin_file && is_plugin_active( $plugin_file ) ) {
                deactivate_plugins( $plugin_file );
                // Update tamper record to note deactivation
                $tampered[ $plugin_slug ]['deactivated_at'] = current_time( 'mysql' );
                update_option( $this->tampered_option, $tampered );
            }
        }
    }
    
    // ==========================================
    // Tamper Flags
    // ==========================================
    
    /**
     * Track when an admin views tamper notices (called on admin_init).
     * Records the first time admin sees each tamper notice.
     */
    public function track_tamper_notice_view(): void {
        if( ! current_user_can( 'administrator' ) ) return;
        
        $tampered = get_option( $this->tampered_option, [] );
        if( empty( $tampered ) ) return;
        
        $seen    = get_option( $this->tamper_dismissed_option, [] );
        $updated = false;
        
        foreach( $tampered as $slug => $info ) {
            if( ! isset( $seen[ $slug ] ) ) {
                $seen[ $slug ] = current_time( 'mysql' );
                $updated       = true;
            }
        }
        
        if( $updated ) {
            update_option( $this->tamper_dismissed_option, $seen );
        }
    }
    
    /**
     * Force-delete the update_plugins transient once per 12-hour cycle.
     *
     * This ensures check_for_updates() runs on the next transient access, which:
     * - Discovers legacy licenses (populates LEGACY_LICENSES_OPTION)
     * - Triggers migration (maybe_migrate_legacy_license)
     * - Builds correct download URLs for all addons
     *
     * Without this, the transient may contain stale package URLs (from before
     * migration) that cause "Download failed. Forbidden" on the first update attempt.
     *
     * Cost: one extra wp_update_plugins() call per 12h — same as the normal WP refresh interval.
     * Skips AJAX requests to avoid interfering with in-progress update downloads.
     */
    public function maybe_refresh_update_transient(): void {
        if( wp_doing_ajax() ) return;
        
        $flag = $this->config->get_core_plugin_slug() . '_gvectors_update_transient_refreshed';
        if( get_transient( $flag ) ) return;
        
        delete_site_transient( 'update_plugins' );
        set_transient( $flag, 1, 12 * HOUR_IN_SECONDS );
    }
    
    /**
     * When a plugin is deleted, clear its tamper flag if it had one.
     */
    public function on_plugin_deleted( string $plugin_file, bool $deleted ): void {
        if( ! $deleted ) return;
        
        $slug = dirname( $plugin_file );
        if( $slug && $slug !== '.' ) {
            $this->clear_tamper_flag( $slug );
            $this->clear_legacy_cache( $slug );
        }
    }
    
    /**
     * Clear the legacy license cache for a specific addon.
     */
    private function clear_legacy_cache( string $plugin_slug ): void {
        $all_legacy = get_option( $this->legacy_licenses_option, [] );
        if( isset( $all_legacy[ $plugin_slug ] ) ) {
            unset( $all_legacy[ $plugin_slug ] );
            update_option( $this->legacy_licenses_option, $all_legacy );
        }
        $notices = get_option( $this->legacy_notice_option, [] );
        if( isset( $notices[ $plugin_slug ] ) ) {
            unset( $notices[ $plugin_slug ] );
            update_option( $this->legacy_notice_option, $notices );
        }
    }
    
    // ==========================================
    // License Validity Check
    // ==========================================
    
    /**
     * Periodically check all license validity (called by daily cron).
     * Marks expired licenses for admin notice display.
     * Does NOT deactivate addons for expired licenses - they keep working.
     */
    public function check_all_license_validity(): void {
        $licenses = $this->licenseService->get_all();
        if( empty( $licenses ) ) return;
        
        $expired_notices = get_option( $this->expired_notice_option, [] );
        
        foreach( $licenses as $license ) {
            $plugin_slug = $license['plugin_slug'] ?? '';
            if( empty( $plugin_slug ) ) continue;
            if( ! $this->is_installed( $plugin_slug ) ) continue;
            
            $status     = $license['status'] ?? '';
            $expires_at = $license['expires_at'] ?? '';
            $is_expired = false;
            
            // Check if status is expired/canceled
            if( in_array( $status, [ 'expired', 'cancelled' ], true ) ) {
                $is_expired = true;
            }
            
            // Check if expiry date has passed
            $expires_ts = ! empty( $expires_at ) ? strtotime( $expires_at ) : false;
            if( $expires_ts !== false && $expires_ts < time() ) {
                $is_expired = true;
            }
            
            if( $is_expired ) {
                $latest_version  = $license['latest_version'] ?? '';
                $plugin_file     = $this->get_installed_plugin_file( $plugin_slug );
                $current_version = '';
                if( $plugin_file ) {
                    $plugin_data     = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file, false, false );
                    $current_version = $plugin_data['Version'] ?? '';
                }
                
                $has_update = $latest_version && $current_version && version_compare( $latest_version, $current_version, '>' );
                
                $expired_notices[ $plugin_slug ] = [
                    'product_name'    => $license['product_name'] ?? $plugin_slug,
                    'status'          => $status,
                    'expires_at'      => $expires_at,
                    'has_update'      => $has_update,
                    'latest_version'  => $latest_version,
                    'current_version' => $current_version,
                ];
            } else {
                // License is valid - remove any expired notice
                unset( $expired_notices[ $plugin_slug ] );
            }
        }
        
        update_option( $this->expired_notice_option, $expired_notices );
    }
    
    // ==========================================
    // Admin Notices
    // ==========================================

    /**
     * Check if the current admin page should display addon notices.
     * Allowed pages: plugin's own admin pages, Dashboard Home, Updates, Installed Plugins, Add Plugins.
     */
    private function is_notice_page(): bool {
        if( ! is_admin() ) return false;

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if( $screen ) {
            // Dashboard Home, Updates, Plugins, Add Plugins
            if( in_array( $screen->id, [ 'dashboard', 'update-core', 'plugins', 'plugin-install' ], true ) ) {
                return true;
            }
            // Any page belonging to this plugin (screen id contains the plugin slug)
            if( strpos( $screen->id, $this->config->get_core_plugin_slug() ) !== false ) {
                return true;
            }
        }

        return false;
    }
    
    /**
     * Handle dismissal of dev environment admin notices via a nonce-secured GET parameter.
     * Saves a transient so the notice is suppressed for a set period.
     */
    public function handle_dev_notice_dismiss(): void {
        if( ! current_user_can( 'administrator' ) ) return;

        if( ! empty( $_GET['gvectors_dismiss_dev_env'] ) ) {
            check_admin_referer( 'gvectors_dismiss_dev_env' );
            set_transient( self::$shared_dev_env_transient, 1, 7 * DAY_IN_SECONDS );
            wp_safe_redirect( remove_query_arg( [ 'gvectors_dismiss_dev_env', '_wpnonce' ] ) );
            exit;
        }

        if( ! empty( $_GET['gvectors_dismiss_dev_licenses'] ) ) {
            check_admin_referer( 'gvectors_dismiss_dev_licenses' );
            set_transient( self::$shared_dev_licenses_transient, 1, DAY_IN_SECONDS );
            wp_safe_redirect( remove_query_arg( [ 'gvectors_dismiss_dev_licenses', '_wpnonce' ] ) );
            exit;
        }

        if( ! empty( $_GET['gvectors_dismiss_addon_notice'] ) && ! empty( $_GET['gvectors_notice_slug'] ) ) {
            $type = sanitize_key( $_GET['gvectors_dismiss_addon_notice'] );
            $slug = sanitize_key( $_GET['gvectors_notice_slug'] );
            if( in_array( $type, [ 'tampered', 'expired', 'legacy' ], true ) ) {
                check_admin_referer( 'gvectors_dismiss_' . $type . '_' . $slug );
                set_transient( 'gvectors_' . $type . '_dismissed_' . $slug, 1, 5 * DAY_IN_SECONDS );
                wp_safe_redirect( remove_query_arg( [ 'gvectors_dismiss_addon_notice', 'gvectors_notice_slug', '_wpnonce' ] ) );
                exit;
            }
        }
    }
    
    /**
     * Warn administrators that the site is running in a development/staging environment.
     * All local addon validation and tamper checks are bypassed in this state.
     * Dismissible for 7 days; reappears automatically as a periodic reminder.
     */
    public function dev_environment_notice(): void {
        if( ! $this->is_notice_page() ) return;
        if( self::$dev_env_notice_shown ) return;
        if( ! current_user_can( 'administrator' ) ) return;
        if( ! LicenseModule::is_development_site() ) return;
        if( get_transient( self::$shared_dev_env_transient ) ) return;

        self::$dev_env_notice_shown = true;

        $dismiss_url = wp_nonce_url(
            add_query_arg( 'gvectors_dismiss_dev_env', '1' ),
            'gvectors_dismiss_dev_env'
        );

        printf(
            '<div class="notice notice-warning">'
            . '<p><strong>⚠️ %s</strong></p>'
            . '<p>%s</p>'
            . '<p><a href="%s">%s</a></p>'
            . '</div>',
            esc_html__( 'gVectors: Development Environment Detected', 'gvectors' ),
            esc_html__(
                'This site is running in a development environment. Some features, including gVectors-Addons updates are disabled. Please contact your developer to configure the site for production.',
                'gvectors'
            ),
            esc_url( $dismiss_url ),
            esc_html__( 'Dismiss for 7 days', 'gvectors' )
        );
    }
    
    /**
     * Warn administrators about active licenses on the current development domain.
     * Lists each active license with its Transaction ID or License Key so the admin
     * can note them before deactivating and re-activating on the production domain.
     * Dismissible for 24 hours.
     */
    public function dev_licenses_notice(): void {
        if( ! $this->is_notice_page() ) return;
        if( ! current_user_can( 'administrator' ) ) return;
        if( ! LicenseModule::is_development_site() ) return;
        if( get_transient( self::$shared_dev_licenses_transient ) ) return;

        // Collect this instance's active licenses into the shared static array
        $licenses = $this->licenseService->get_all();
        foreach( $licenses as $product_id => $license ) {
            if( empty( $license['status'] ) ) continue;
            if( ! in_array( $license['status'], [ 'active', 'trial' ], true ) ) continue;
            if( ! empty( $license['expires_at'] ) && strtotime( $license['expires_at'] ) < time() ) continue;
            self::$dev_licenses_collected[ $product_id ] = $license;
        }

        // Register the actual rendering callback once (fires after all instances have collected)
        if( ! self::$dev_licenses_registered ) {
            self::$dev_licenses_registered = true;
            add_action( 'admin_notices', [ __CLASS__, 'render_dev_licenses_notice' ], 999 );
        }
    }

    /**
     * Render a single consolidated dev-licenses notice with licenses from all plugin instances.
     * Fires at priority 999 so all instances have collected their licenses first.
     */
    public static function render_dev_licenses_notice(): void {
        if( empty( self::$dev_licenses_collected ) ) return;

        $dismiss_url = wp_nonce_url(
            add_query_arg( 'gvectors_dismiss_dev_licenses', '1' ),
            'gvectors_dismiss_dev_licenses'
        );

        $rows = '';
        foreach( self::$dev_licenses_collected as $product_id => $license ) {
            $name = ! empty( $license['product_name'] ) ? $license['product_name'] : ( $license['plugin_slug'] ?? '' );
            $plan = ! empty( $license['plan_name'] ) ? $license['plan_name'] : $product_id;
            $txn  = ! empty( $license['transaction_id'] ) ? $license['transaction_id'] : '';
            $key  = ! empty( $license['license_key'] ) ? $license['license_key'] : '';

            if( $txn ) {
                $rows .= '<li><strong>' . esc_html( $name . ' (' . $plan . ')' ) . '</strong> &mdash; '
                         . esc_html__( 'Transaction ID', 'gvectors' ) . ': <code>' . esc_html( $txn ) . '</code>';
            } elseif( $key ) {
                $rows .= '<li><strong>' . esc_html( $name . ' (' . $plan . ')' ) . '</strong> &mdash; '
                         . esc_html__( 'License Key', 'gvectors' ) . ': <code>' . esc_html( $key ) . '</code>';
            } else {
                $rows .= '<li><strong>' . esc_html( $name . ' (' . $plan . ')' ) . '</strong>';
            }

            if( ! empty( $license['status'] ) && $license['status'] === 'trial' ) {
                $rows .= ' <em>(' . esc_html__( 'Trial', 'gvectors' ) . ')</em>';
            }
            $rows .= '</li>';
        }

        printf(
            '<div class="notice notice-info">'
            . '<p><strong>ℹ️ %s</strong></p>'
            . '<p>%s</p>'
            . '<ul style="list-style:disc;padding-left:20px;margin:.4em 0 .8em;">%s</ul>'
            . '<p>%s</p>'
            . '<p><a href="%s">%s</a></p>'
            . '</div>',
            esc_html__( 'gVectors: Active Licenses on Development Domain', 'gvectors' ),
            esc_html__(
                'You have active addon licenses on this development/staging site. Before deploying to production, note the Transaction IDs or License Keys below, deactivate all licenses from this domain, then re-activate them on your live site using the Transaction ID or License Key:',
                'gvectors'
            ),
            $rows,
            sprintf(
                esc_html__( 'On your production site go to %s and activate each license using its Transaction ID or License Key.', 'gvectors' ),
                '<a href="' . esc_url( admin_url( 'admin.php?page=gvectors-addons' ) ) . '">' . esc_html__( 'gVectors Store Addons', 'gvectors' ) . '</a>'
            ),
            esc_url( $dismiss_url ),
            esc_html__( 'Dismiss for 24 hours', 'gvectors' )
        );
    }
    
    /**
     * Display FATAL admin notice for tampered/nulled/pirated addons.
     * Shows permanently until resolved. After the grace period + admin view, addon gets deactivated.
     */
    public function tampered_addon_notice(): void {
        if( ! $this->is_notice_page() ) return;
        if( ! current_user_can( 'administrator' ) ) return;
        if( LicenseModule::is_development_site() ) return;
        
        $tampered = get_option( $this->tampered_option, [] );
        if( empty( $tampered ) ) return;
        
        $changed = false;
        foreach( $tampered as $slug => $info ) {
            if( ! is_dir( WP_PLUGIN_DIR . '/' . $slug ) ) {
                unset( $tampered[ $slug ] );
                $changed = true;
            }
        }
        if( $changed ) {
            update_option( $this->tampered_option, $tampered );
        }
        if( empty( $tampered ) ) return;
        
        foreach( $tampered as $slug => $info ) {
            if( get_transient( 'gvectors_tampered_dismissed_' . $slug ) ) continue;

            $files       = $info['files'] ?? [];
            $reason      = $info['reason'] ?? 'tampered';
            $detected    = $info['detected_at'] ?? '';
            $deactivated = $info['deactivated_at'] ?? '';
            
            $reason_labels = [
                'tampered'        => __( 'File integrity check failed — files have been modified.', 'gvectors' ),
                'no_manifest'     => __( 'Missing signature manifest — this copy was not obtained through an authorized license.', 'gvectors' ),
                'domain_mismatch' => __( 'Domain signature mismatch — this addon was licensed for a different website.', 'gvectors' ),
                'no_signatures'   => __( 'Missing file header signatures — files have been stripped of authorization data.', 'gvectors' ),
                'patched'         => __( 'Suspicious code patterns detected — this appears to be a nulled or patched version.', 'gvectors' ),
            ];
            
            $reason_text = $reason_labels[ $reason ] ?? $reason_labels['tampered'];
            
            if( $deactivated ) {
                $status_text = sprintf(
                    '<strong style="color:#dc3232;">%s %s</strong>',
                    esc_html__( 'This addon has been deactivated on:', 'gvectors' ),
                    esc_html( $deactivated )
                );
            } else {
                $days_left = $this->config->get_tamper_grace_days();
                if( $detected ) {
                    $detected_ts = strtotime( $detected );
                    if( $detected_ts !== false && $detected_ts > 0 ) {
                        $days_since = ( time() - $detected_ts ) / DAY_IN_SECONDS;
                        $days_left  = max( 0, ceil( $this->config->get_tamper_grace_days() - $days_since ) );
                    }
                }
                if( $days_left > 0 ) {
                    $status_text = sprintf(
                        '<strong style="color:#dc3232;">%s</strong>',
                        sprintf(
                            esc_html__( 'This addon will be automatically deactivated in %d day(s) if not resolved.', 'gvectors' ),
                            $days_left
                        )
                    );
                } else {
                    $status_text = sprintf(
                        '<strong style="color:#dc3232;">%s</strong>',
                        esc_html__( 'This addon will be deactivated on the next security check.', 'gvectors' )
                    );
                }
            }
            
            $dismiss_url = wp_nonce_url(
                add_query_arg( [ 'gvectors_dismiss_addon_notice' => 'tampered', 'gvectors_notice_slug' => $slug ] ),
                'gvectors_dismiss_tampered_' . $slug
            );

            printf(
                '<div class="notice notice-error" style="border-left-color:#dc3232;border-left-width:4px;">'
                . '<p><strong style="font-size:14px;">⚠️ %s</strong> %s</p>'
                . '<p>%s</p>'
                . '<p>%s</p>'
                . '<p>%s</p>'
                . '<p><a href="%s">%s</a></p>'
                . '</div>',
                esc_html__( 'gVectors Security Alert — Unauthorized Addon Detected', 'gvectors' ),
                '<code>' . esc_html( $slug ) . '</code>',
                esc_html( $reason_text ),
                $status_text,
                sprintf(
                    esc_html__( 'Please purchase a valid license at %s or remove the unauthorized addon.', 'gvectors' ),
                    '<a href="' . admin_url( $this->config->get_dashboard_addons_store_url() ) . '">Addons Store</a>'
                ),
                esc_url( $dismiss_url ),
                esc_html__( 'Dismiss for 5 days', 'gvectors' )
            );
        }
    }
    
    /**
     * Register after_plugin_row hooks for installed addons that have updates but no active license.
     * Shows a notice row on the Plugins page explaining that a license is required to update.
     */
    public function register_unlicensed_update_row_hooks(): void {
        if( ! is_admin() ) return;
        
        $update_plugins = get_site_transient( 'update_plugins' );
        if( empty( $update_plugins->response ) ) return;
        
        $licenses = $this->licenseService->get_all();
        
        // Build a set of plugin slugs that have an active/trial license for this domain
        $active_licensed_slugs = [];
        $site_domain           = LicenseModule::get_site_domain();
        foreach( $licenses as $license ) {
            if( empty( $license['license_key'] ) ) continue;
            if( ! in_array( $license['status'] ?? '', [ 'active', 'trial' ], true ) ) continue;
            if( ! empty( $license['expires_at'] ) && strtotime( $license['expires_at'] ) < time() ) continue;
            if( ! empty( $site_domain ) ) {
                $activated_site = $license['site_domain'] ?? '';
                if( ! empty( $activated_site ) && LicenseModule::normalize_domain( $activated_site ) !== LicenseModule::normalize_domain( $site_domain ) ) continue;
            }
            $slug = $license['plugin_slug'] ?? '';
            if( ! empty( $slug ) ) $active_licensed_slugs[] = $slug;
        }
        
        // Also include slugs that have an active (non-expired) legacy license
        $all_legacy = get_option( $this->legacy_licenses_option, [] );
        foreach( $all_legacy as $legacy_slug => $legacy ) {
            if( empty( $legacy['has_license'] ) || ! empty( $legacy['expired'] ) ) continue;
            $active_licensed_slugs[] = $legacy_slug;
        }
        
        foreach( $update_plugins->response as $plugin_file => $update_data ) {
            $slug = dirname( $plugin_file );
            if( $slug === '.' || $slug === $this->config->get_core_plugin_slug() ) continue;
            
            // Only for our addons that have empty package (no active license)
            $package = is_object( $update_data ) ? ( $update_data->package ?? '' ) : '';
            if( ! empty( $package ) ) continue;
            
            // Confirm it's a known gVectors addon
            if( ! $this->is_known_addon( $slug ) ) continue;
            
            // Confirm no active license
            if( in_array( $slug, $active_licensed_slugs, true ) ) continue;
            
            add_action( "after_plugin_row_$plugin_file", [ $this, 'unlicensed_update_notice_row' ] );
        }
    }
    
    /**
     * Display an inline notice row on the Plugins page for addons that need a license to update.
     */
    public function unlicensed_update_notice_row( $plugin_file ): void {
        $update_plugins = get_site_transient( 'update_plugins' );
        $update         = $update_plugins->response[ $plugin_file ] ?? null;
        if( ! $update ) return;
        
        $wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );
        $columns_count = $wp_list_table ? $wp_list_table->get_column_count() : 3;
        
        echo '<tr class="plugin-update-tr' . ( is_plugin_active( $plugin_file ) ? ' active' : '' ) . '" id="' . esc_attr( dirname( $plugin_file ) ) . '-update-license-notice">';
        echo '<td colspan="' . esc_attr( $columns_count ) . '" class="colspanchange" style="padding:0;">';
        echo '<div class="update-message notice inline notice-warning notice-alt" style="padding:9px 12px;">';
        printf(
            '<p>' .
            __( 'Warning: your license is not active. Please <a href="%1$s">activate your existing license</a> or <a href="%1$s">purchase a new one</a> to receive updates.', 'gvectors' ) .
            '</p>',
            esc_url( admin_url( $this->config->get_dashboard_addons_store_url() ) )
        );
        echo '</div>';
        echo '</td>';
        echo '</tr>';
    }
    
    /**
     * Display persistent admin notice for expired licenses.
     * Addon keeps working, but no updates are available.
     */
    public function expired_license_notice(): void {
        if( ! $this->is_notice_page() ) return;
        if( ! current_user_can( 'administrator' ) ) return;
        
        $expired = get_option( $this->expired_notice_option, [] );
        if( empty( $expired ) ) return;
        
        foreach( $expired as $slug => $info ) {
            if( get_transient( 'gvectors_expired_dismissed_' . $slug ) ) continue;

            $product_name = $info['product_name'] ?? $slug;
            $has_update   = ! empty( $info['has_update'] );
            $latest       = $info['latest_version'] ?? '';
            $current      = $info['current_version'] ?? '';

            $update_text = '';
            if( $has_update ) {
                $update_text = sprintf(
                    ' ' . esc_html__( 'A new version (%1$s) is available but your current version (%2$s) cannot be updated without an active subscription.', 'gvectors' ),
                    '<strong>' . esc_html( $latest ) . '</strong>',
                    '<strong>' . esc_html( $current ) . '</strong>'
                );
            }

            $dismiss_url = wp_nonce_url(
                add_query_arg( [ 'gvectors_dismiss_addon_notice' => 'expired', 'gvectors_notice_slug' => $slug ] ),
                'gvectors_dismiss_expired_' . $slug
            );

            printf(
                '<div class="notice notice-warning" style="border-left-color:#ffb900;border-left-width:4px;">'
                . '<p><strong>%s</strong> %s%s</p>'
                . '<p>%s</p>'
                . '<p><a href="%s">%s</a></p>'
                . '</div>',
                esc_html__( 'gVectors License Expired:', 'gvectors' ),
                sprintf(
                    esc_html__( 'Your license for "%s" has expired. The addon will continue to work, but you will not receive updates or support.', 'gvectors' ),
                    '<strong>' . esc_html( $product_name ) . '</strong>'
                ),
                $update_text,
                sprintf(
                    esc_html__( 'Renew your subscription at %s to receive updates and support.', 'gvectors' ),
                    '<a href="' . admin_url( $this->config->get_dashboard_addons_store_url() ) . '">Addons Store</a>'
                ),
                esc_url( $dismiss_url ),
                esc_html__( 'Dismiss for 5 days', 'gvectors' )
            );
        }
    }
    
    /**
     * Display admin notice for expired legacy-licensed addons.
     * Informs admin that addon works but cannot receive updates without a new subscription.
     * Active (non-expired) legacy licenses show NO notice — completely silent.
     */
    public function legacy_license_notice(): void {
        if( ! $this->is_notice_page() ) return;
        if( ! current_user_can( 'administrator' ) ) return;
        
        $notices = get_option( $this->legacy_notice_option, [] );
        if( empty( $notices ) ) return;
        
        $addons_page_url = admin_url( $this->config->get_dashboard_addons_store_url() );
        
        foreach( $notices as $slug => $info ) {
            // Only show notices for expired legacy licenses
            if( empty( $info['status'] ) || $info['status'] !== 'expired' ) continue;

            // Verify the addon is still installed
            if( ! is_dir( WP_PLUGIN_DIR . '/' . $slug ) ) continue;

            if( get_transient( 'gvectors_legacy_dismissed_' . $slug ) ) continue;

            $plugin_name = $info['plugin_name'] ?? $slug;

            $dismiss_url = wp_nonce_url(
                add_query_arg( [ 'gvectors_dismiss_addon_notice' => 'legacy', 'gvectors_notice_slug' => $slug ] ),
                'gvectors_dismiss_legacy_' . $slug
            );

            printf(
                '<div class="notice notice-info" style="border-left-color:#0073aa;border-left-width:4px;">'
                . '<p><strong>%s</strong> %s</p>'
                . '<p>%s</p>'
                . '<p><a href="%s">%s</a></p>'
                . '</div>',
                esc_html__( 'gVectors Addon — Legacy License:', 'gvectors' ),
                sprintf(
                    esc_html__(
                        'Your "%s" addon is using a legacy license that has expired. The addon will continue to work without any issues, but automatic updates are not available.',
                        'gvectors'
                    ),
                    '<strong>' . esc_html( $plugin_name ) . '</strong>'
                ),
                sprintf(
                    esc_html__(
                        'To receive new updates, please purchase a new subscription at the %1$s. After completing the transaction, re-download and install the addon to get the latest version with full license activation.',
                        'gvectors'
                    ),
                    '<a href="' . esc_url( $addons_page_url ) . '">' . esc_html__( 'Addons Store', 'gvectors' ) . '</a>'
                ),
                esc_url( $dismiss_url ),
                esc_html__( 'Dismiss for 5 days', 'gvectors' )
            );
        }
    }
}
