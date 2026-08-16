<?php

namespace gVectors\License;

class Config {
    private $core_plugin_version;                             // "3.0.5"
    private $core_plugin_slug;                                // "wpforo"
    private $license_module_url;                              // "https://example.com/wp-content/plugins/wpforo/modules/license"
    private $dashboard_addons_page_slug;                      // admin_url('admin.php?page=XXXXXXX');
    private $dashboard_menu_hook;                             // add_action('admin_menu', function() { //the core plugin add menu actions; do_action('XXXXXXX', $parent_slug );}
    private $proxy_server_url = 'https://store.gvectors.com'; // "https://store.gvectors.com" | "https://gv.loc"
    
    public function __construct(
        $core_plugin_version,
        $core_plugin_slug,
        $license_module_url,
        $dashboard_addons_page_slug,
        $dashboard_menu_hook
    ) {
        $this->core_plugin_version        = $core_plugin_version;
        $this->core_plugin_slug           = $core_plugin_slug;
        $this->license_module_url         = $license_module_url;
        $this->dashboard_addons_page_slug = $dashboard_addons_page_slug;
        $this->dashboard_menu_hook        = $dashboard_menu_hook;
    }
    
    /*
     * "3.0.5"
     */
    public function get_core_plugin_version(): string {
        return $this->core_plugin_version;
    }
    
    /*
     * "wpforo"
     */
    public function get_core_plugin_slug(): string {
        return $this->core_plugin_slug;
    }
    
    /*
     * "https://example.com/wp-content/plugins/wpforo/modules/license"
     */
    public function get_license_module_url(): string {
        return $this->license_module_url;
    }
    
    /*
     * admin_url('admin.php?page=XXXXXXX');
     */
    public function get_dashboard_addons_page_slug(): string {
        return $this->dashboard_addons_page_slug;
    }
    
    /*
     * add_action('admin_menu', function() { //the core plugin add menu actions; do_action('XXXXXXX', $parent_slug );}
     */
    public function get_dashboard_menu_hook(): string {
        return $this->dashboard_menu_hook;
    }
    
    /*
     * "https://store.gvectors.com" | "https://gv.loc"
     */
    public function get_proxy_server_url(): string {
        return $this->proxy_server_url;
    }
    
    public function get_manifest_public_key(): string {
        // @TODO Replace with your production public key before release.
        return '008aed68f1caf91cce7fbf679091879091c8f8c660cd3a64107c9db11d2f62af';
    }
    
    public function get_tamper_grace_days(): int {
        return 5;
    }
    
    public function get_legacy_check_period(): int {
        return DAY_IN_SECONDS;
    }
    
    public function get_license_revalidation_period(): int {
        return DAY_IN_SECONDS;
    }
    
    public function get_license_batch_cache_ttl(): int {
        return 10 * MINUTE_IN_SECONDS;
    }
    
    /*
     * admin_url('XXXXXXX');
     */
    public function get_dashboard_addons_store_url(): string {
        return 'admin.php?page=' . $this->dashboard_addons_page_slug;
    }
    
    public function get_proxy_checkout_loading_url(): string {
        return $this->proxy_server_url . '/checkout-loading.html';
    }
    
    public function get_proxy_checkout_base_url(): string {
        return $this->proxy_server_url . '/pay/index.php';
    }
}
