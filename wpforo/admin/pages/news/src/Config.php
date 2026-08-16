<?php

namespace gVectors\News;

/**
 * Immutable configuration value object for the News module.
 * The host plugin (wpForo, wpDiscuz, ...) injects everything at construction time,
 * so the module itself never references any specific plugin.
 */
class Config {
    private $core_plugin_version;                             // "3.1.1"
    private $core_plugin_slug;                                // "wpforo" | "wpdiscuz"
    private $news_module_url;                                 // "https://example.com/wp-content/plugins/{plugin}/admin/pages/news"
    private $dashboard_addons_page_slug;                      // admin_url('admin.php?page=XXXXXXX');
    private $dashboard_menu_hook;                             // add_action('admin_menu', function() { //the core plugin add menu actions; do_action('XXXXXXX', $parent_slug );}
    private $proxy_server_url = 'https://store.gvectors.com'; // "https://store.gvectors.com" | "https://gv.loc"

    public function __construct(
        $core_plugin_version,
        $core_plugin_slug,
        $news_module_url,
        $dashboard_addons_page_slug,
        $dashboard_menu_hook
    ) {
        $this->core_plugin_version        = $core_plugin_version;
        $this->core_plugin_slug           = $core_plugin_slug;
        $this->news_module_url            = $news_module_url;
        $this->dashboard_addons_page_slug = $dashboard_addons_page_slug;
        $this->dashboard_menu_hook        = $dashboard_menu_hook;
    }

    /**
     * Host plugin's admin menu hook (same one the license module uses):
     * add_action( 'admin_menu', function() { ...; do_action( 'XXXXXXX', $parent_slug ); } );
     */
    public function get_dashboard_menu_hook(): string {
        return $this->dashboard_menu_hook;
    }

    public function get_core_plugin_version(): string {
        return $this->core_plugin_version;
    }

    public function get_core_plugin_slug(): string {
        return $this->core_plugin_slug;
    }

    public function get_news_module_url(): string {
        return $this->news_module_url;
    }

    public function get_dashboard_addons_page_slug(): string {
        return $this->dashboard_addons_page_slug;
    }

    public function get_proxy_server_url(): string {
        return $this->proxy_server_url;
    }

    /**
     * Absolute URL of the host plugin's Addons dashboard page.
     * Used for the {dashboard_url} email token and notice links.
     */
    public function get_dashboard_addons_url(): string {
        return admin_url( 'admin.php?page=' . $this->dashboard_addons_page_slug );
    }

    /**
     * Common prefix for every option, transient, user meta key, cron hook and
     * AJAX action this module registers.
     *
     * INTENTIONALLY NOT plugin-prefixed: the news module is ONE module for ALL
     * gVectors products. When wpForo and wpDiscuz are installed together, they
     * share the same options, the same news cache, the same cron job and the
     * same per-admin email preferences — news and licenses are gVectors-wide,
     * not per-plugin. (Same pattern as the license module's shared
     * 'gvectors_revalidate' / 'gvectors_daily_cron' hooks.)
     */
    public function get_prefix(): string {
        return 'gvectors_';
    }

    // ── Option / meta / transient names ──

    /** Master opt-in (WordPress.org Guideline 7). No opt-in → zero outbound requests. */
    public function get_service_enabled_option(): string {
        return $this->get_prefix() . 'addons_service_enabled';
    }

    /** Option: site-wide channel × category matrix {notices{cat=>bool}, emails{cat=>bool}}. */
    public function get_site_prefs_option(): string {
        return $this->get_prefix() . 'site_channel_prefs';
    }

    /** User meta: per-admin matrix {unsubscribed, notices{cat=>bool}, emails{cat=>bool}}. */
    public function get_user_prefs_meta(): string {
        return $this->get_prefix() . 'news_prefs';
    }

    /** Admin page slug of the News & Emails settings page. */
    public function get_settings_page_slug(): string {
        return $this->core_plugin_slug . '-news-settings';
    }

    /** Absolute URL of the News & Emails settings page (email footers link here). */
    public function get_settings_page_url(): string {
        return admin_url( 'admin.php?page=' . $this->get_settings_page_slug() );
    }

    /** "Not now" flag for the first-activation opt-in notice. */
    public function get_optin_notice_dismissed_option(): string {
        return $this->get_prefix() . 'optin_notice_dismissed';
    }

    /** Transient caching the daily news payload from the proxy. */
    public function get_news_transient(): string {
        return $this->get_prefix() . 'news';
    }

    /** User meta: array of dismissed news ids. */
    public function get_dismissed_news_meta(): string {
        return $this->get_prefix() . 'dismissed_news';
    }

    /** User meta: array of news ids already emailed to this admin. */
    public function get_emailed_news_meta(): string {
        return $this->get_prefix() . 'emailed_news';
    }

    /** User meta: array of "license_id:phase" reminder keys already emailed. */
    public function get_emailed_phases_meta(): string {
        return $this->get_prefix() . 'emailed_license_phases';
    }

    /** Option: transaction ids a purchase-confirmation email was already sent for. */
    public function get_emailed_transactions_option(): string {
        return $this->get_prefix() . 'emailed_transactions';
    }

    /** Option: "transaction_id:phase" keys an abandoned-checkout email was already sent for. */
    public function get_abandoned_emailed_option(): string {
        return $this->get_prefix() . 'abandoned_emailed';
    }

    /** User meta: addon slugs already recommended to this admin (each at most once, ever). */
    public function get_emailed_recs_meta(): string {
        return $this->get_prefix() . 'emailed_recommendations';
    }

    /** Daily cron hook name. */
    public function get_cron_hook(): string {
        return $this->get_prefix() . 'daily_news_sync';
    }

    /** Nonce action shared by this module's AJAX endpoints. */
    public function get_nonce_action(): string {
        return $this->get_prefix() . 'news_nonce';
    }

    /**
     * Option name THIS plugin's license module stores local licenses in.
     * This one IS plugin-prefixed — the license module keeps per-plugin storage.
     * The cron merges the licenses of every registered gVectors plugin.
     */
    public function get_licenses_option(): string {
        return $this->core_plugin_slug . '_gvectors_licenses';
    }

    // ── Behavior knobs ──

    public function get_request_timeout(): int {
        return 10;
    }

    public function get_news_cache_ttl(): int {
        return DAY_IN_SECONDS;
    }

    /** Maximum number of news admin notices rendered at once (Guideline 11). */
    public function get_max_notices(): int {
        return 3;
    }

    public function get_privacy_policy_url(): string {
        return $this->get_proxy_server_url() . '/privacy.html';
    }
}
