<?php

namespace gVectors\License;

// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin UI: enqueues admin CSS/JS and renders the addon store page.
 */
class AdminPage {
    private $config;
    
    public function __construct( Config $config ) {
        $this->config = $config;
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( $this->config->get_dashboard_menu_hook(), function( $parent_slug ) {
            add_submenu_page(
                $parent_slug,
                __( 'Addons', 'gvectors' ),
                __( 'Addons', 'gvectors' ),
                'activate_plugins',
                $this->config->get_dashboard_addons_page_slug(),
                function() {
                    $this->render_store_page();
                }
            );
        } );
    }
    
    /**
     * Render the addon store page (called from admin/pages/addons.php replacement or new page)
     */
    private function render_store_page(): void {
        if( ! current_user_can( 'activate_plugins' ) ) return;
        $slug = $this->config->get_core_plugin_slug();
        require __DIR__ . '/../admin/store-page.php';
    }

    /**
     * Enqueue admin assets only on gVectors admin pages
     */
    public function enqueue_assets( $hook ): void {
        if( strpos( $hook, $this->config->get_core_plugin_slug() ) === false ) return;

        $slug       = $this->config->get_core_plugin_slug();
        $module_url = $this->config->get_license_module_url() . '/admin/assets';
        $module_dir = __DIR__ . '/../admin/assets';

        // Module CSS (handle prefixed per plugin to avoid dedup conflicts)
        wp_enqueue_style(
            $slug . '-gvlicense-admin',
            $module_url . '/css/admin.css',
            [],
            filemtime( $module_dir . '/css/admin.css' )
        );

        // Module JS (handle prefixed per plugin to avoid dedup conflicts)
        wp_enqueue_script(
            $slug . '-gvlicense-admin',
            $module_url . '/js/admin.js',
            [ 'jquery', 'wp-util' ],
            filemtime( $module_dir . '/js/admin.js' ),
            true
        );

        // Pass config to JS (global name prefixed per plugin: e.g. wpforoLicense, wpdiscuzLicense)
        wp_localize_script( $slug . '-gvlicense-admin', $slug . 'License', [
            'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
            'nonce'                => wp_create_nonce( $slug . '_gvectors_nonce' ),
            'ajaxPrefix'           => $slug . '_gvectors_',
            'proxy_server_url'         => $this->config->get_proxy_server_url(),
            'checkout_loading_url' => $this->config->get_proxy_checkout_loading_url(),
            'checkout_url'         => $this->config->get_proxy_checkout_base_url(),
            'siteDomain'           => LicenseModule::get_site_domain(),
            'i18n'                 => [
                'loading'            => __( 'Loading...', 'gvectors' ),
                'buy'                => __( 'Buy Now', 'gvectors' ),
                'startTrial'         => __( 'Start Free Trial', 'gvectors' ),
                'activateLicense'    => __( 'Activate License', 'gvectors' ),
                'deactivateLicense'  => __( 'Deactivate', 'gvectors' ),
                'install'            => __( 'Install', 'gvectors' ),
                'activate'           => __( 'Activate', 'gvectors' ),
                'installed'          => __( 'Installed', 'gvectors' ),
                'active'             => __( 'Active', 'gvectors' ),
                'expired'            => __( 'Expired', 'gvectors' ),
                'trial'              => __( 'Trial', 'gvectors' ),
                'cancelled'          => __( 'Cancelled', 'gvectors' ),
                'enterLicenseKey'    => __( 'Enter license key', 'gvectors' ),
                'enterKeyOrId'       => __( 'Enter license key or transaction ID', 'gvectors' ),
                'activatingByDomain' => __( 'Searching for licenses registered to this domain...', 'gvectors' ),
                'noLicensesFound'    => __( 'No licenses found for this domain', 'gvectors' ),
                'confirmCancel'      => __( 'Are you sure you want to cancel this subscription?', 'gvectors' ),
                'processing'         => __( 'Processing...', 'gvectors' ),
                'success'            => __( 'Success!', 'gvectors' ),
                'error'              => __( 'Error', 'gvectors' ),
                'perMonth'           => __( '/month', 'gvectors' ),
                'perYear'            => __( '/year', 'gvectors' ),
                'oneTime'            => __( 'one-time', 'gvectors' ),
                'manageSubscription' => __( 'Manage Subscription', 'gvectors' ),
                'updatePayment'      => __( 'Update Payment Method', 'gvectors' ),
                'cancelSubscription' => __( 'Cancel Subscription', 'gvectors' ),
                'pauseSubscription'  => __( 'Pause Subscription', 'gvectors' ),
                'resumeSubscription' => __( 'Resume Subscription', 'gvectors' ),
                'refreshProducts'    => __( 'Refresh', 'gvectors' ),
                'noProducts'         => __( 'No products available at this time.', 'gvectors' ),
                'checkoutError'      => __( 'Checkout could not be opened. Please try again.', 'gvectors' ),
            ],
        ] );
    }
}
