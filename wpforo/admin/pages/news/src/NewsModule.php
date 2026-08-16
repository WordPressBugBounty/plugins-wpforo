<?php

namespace gVectors\News;

use gVectors\News\Services\ApiService;
use gVectors\News\Services\ConsentService;
use gVectors\News\Services\CronService;
use gVectors\News\Services\EmailService;
use gVectors\News\Services\NoticesService;
use gVectors\News\Services\PrefsService;

// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

/**
 * gVectors News Module — entry point.
 *
 * Reusable library shared by gVectors plugins (wpForo, wpDiscuz, ...):
 *  - fetches global news from the gVectors proxy server (daily cron)
 *  - renders dismissible admin notices for news items
 *  - sends the news digest email and license expiry reminder emails
 *  - everything is gated behind an explicit admin opt-in (zero outbound
 *    requests before consent — WordPress.org Guideline 7)
 *
 * The proxy is the single source of truth: news content, at-risk license
 * selection, phase math and email bodies are all computed server-side.
 * This module is a thin renderer/sender.
 */
class NewsModule {

    /**
     * Shared service core — created ONCE by whichever gVectors plugin loads
     * first. All hooks (cron, admin notices, AJAX, emails) live here, so no
     * matter how many gVectors plugins instantiate the module, everything
     * runs exactly once.
     */
    private static $core = null;

    /**
     * Registered plugin contexts: slug → Config.
     * Each additional plugin only contributes its own settings menu item and
     * its own license storage to the shared versions map.
     */
    private static $contexts = [];

    public function __construct( Config $config ) {
        $slug = $config->get_core_plugin_slug();
        if( isset( self::$contexts[ $slug ] ) ) return; // same plugin twice — no-op
        self::$contexts[ $slug ] = $config;

        if( self::$core === null ) {
            // Translations for the shared 'gvectors' textdomain (also used by the
            // license module) — loaded once, from the first module copy that runs.
            add_action( 'init', [ __CLASS__, 'load_textdomain' ] );

            // First gVectors plugin in: build the shared service tree.
            $api        = new ApiService( $config );
            $consent    = new ConsentService( $config );
            $prefs      = new PrefsService( $config );
            $email      = new EmailService( $config, $prefs );
            $cron       = new CronService( $config, $api, $consent, $email );
            $notices    = new NoticesService( $config, $consent, $cron, $prefs );
            $admin_page = new AdminPage( $config, $consent, $prefs );

            // Purchase confirmation: license module fires this after the dashboard
            // polling activates purchased license(s) — email the keys to the BUYER
            // (4th arg; the polling may run in another admin's session).
            add_action( 'gvectors_transaction_licenses_activated', [ $email, 'handle_purchase_activated' ], 10, 4 );

            // Abandoned checkout recovery: license module confirms via the proxy
            // that a checkout is still unpaid at a phase — email the purchaser.
            add_action( 'gvectors_abandoned_checkout_email', [ $email, 'handle_abandoned_checkout' ], 10, 4 );

            self::$core = [
                'config'     => $config,
                'api'        => $api,
                'consent'    => $consent,
                'prefs'      => $prefs,
                'email'      => $email,
                'cron'       => $cron,
                'notices'    => $notices,
                'admin_page' => $admin_page,
            ];
        } else {
            // Another gVectors plugin already initialized the module — only add
            // this plugin's settings menu item; state, cron and notices are shared.
            self::$core['admin_page']->add_context( $config );
        }
    }

    /**
     * Get a shared service instance ('config' returns the given plugin's own context).
     * e.g. NewsModule::get( 'wpforo', 'cron' )
     */
    public static function get( string $slug, string $service = 'config' ) {
        if( ! isset( self::$contexts[ $slug ] ) ) return null;
        if( $service === 'config' ) return self::$contexts[ $slug ];

        return self::$core[ $service ] ?? null;
    }

    /**
     * All registered plugin contexts (slug → Config).
     * @return Config[]
     */
    public static function get_contexts(): array {
        return self::$contexts;
    }

    /**
     * Unschedule this module's cron for a plugin — call from the host plugin's
     * deactivation hook: NewsModule::clear_scheduled_events( new MyNewsConfig( ... ) ).
     */
    public static function clear_scheduled_events( Config $config ): void {
        wp_clear_scheduled_hook( $config->get_cron_hook() );
    }

    /**
     * Load the shared 'gvectors' textdomain from this module's languages/ dir.
     */
    public static function load_textdomain(): void {
        static $loaded = false;
        if( $loaded ) return;
        $loaded = true;

        $locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
        $mofile = dirname( __DIR__ ) . '/languages/gvectors-' . $locale . '.mo';
        if( file_exists( $mofile ) ) {
            load_textdomain( 'gvectors', $mofile );
        }
    }

    /**
     * Full data cleanup — call from the host plugin's uninstall flow.
     *
     * Shared data (options, news transient, per-admin user meta, cron) is
     * removed ONLY when no OTHER gVectors plugin carrying this module remains
     * installed: news preferences and reminder state are gVectors-wide, so the
     * last product out turns off the lights.
     */
    public static function uninstall( Config $config ): void {
        if( self::another_gvectors_plugin_installed( $config->get_core_plugin_slug() ) ) {
            return; // another gVectors product still owns the shared data
        }

        wp_clear_scheduled_hook( $config->get_cron_hook() );

        delete_option( $config->get_service_enabled_option() );
        delete_option( $config->get_site_prefs_option() );
        delete_option( $config->get_optin_notice_dismissed_option() );
        delete_option( $config->get_emailed_transactions_option() );
        delete_option( $config->get_abandoned_emailed_option() );
        delete_transient( $config->get_news_transient() );

        // Per-admin meta — remove for ALL users
        delete_metadata( 'user', 0, $config->get_dismissed_news_meta(), '', true );
        delete_metadata( 'user', 0, $config->get_emailed_news_meta(), '', true );
        delete_metadata( 'user', 0, $config->get_emailed_phases_meta(), '', true );
        delete_metadata( 'user', 0, $config->get_user_prefs_meta(), '', true );
        delete_metadata( 'user', 0, $config->get_emailed_recs_meta(), '', true );
    }

    /**
     * Is any OTHER installed plugin shipping this news module?
     * Detected by the module's entry file inside each plugin directory.
     */
    private static function another_gvectors_plugin_installed( string $current_slug ): bool {
        $pattern = trailingslashit( WP_PLUGIN_DIR ) . '*/admin/pages/news/src/NewsModule.php';
        foreach( glob( $pattern ) ?: [] as $file ) {
            // {WP_PLUGIN_DIR}/{plugin}/admin/pages/news/src/NewsModule.php → {plugin}
            $plugin_dir = basename( dirname( $file, 5 ) );
            if( $plugin_dir !== $current_slug ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the current admin page should display this module's notices.
     * Allowed pages: Dashboard Home, Updates, Installed Plugins, Add Plugins,
     * and the admin pages of ANY registered gVectors plugin (pass a slug to
     * check one specific plugin only).
     *
     * IMPORTANT: keep in sync with AddonsService::is_notice_page() in the
     * license module — both modules must surface notices on the same pages.
     */
    public static function is_notice_page( ?string $core_plugin_slug = null ): bool {
        if( ! is_admin() ) return false;

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if( $screen ) {
            // Dashboard Home, Updates, Plugins, Add Plugins
            if( in_array( $screen->id, [ 'dashboard', 'update-core', 'plugins', 'plugin-install' ], true ) ) {
                return true;
            }
            // Any page belonging to a registered gVectors plugin (screen id contains its slug)
            $slugs = $core_plugin_slug !== null ? [ $core_plugin_slug ] : array_keys( self::$contexts );
            foreach( $slugs as $slug ) {
                if( strpos( $screen->id, $slug ) !== false ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Generate the unique site token for authenticating with the proxy server.
     * IMPORTANT: must produce the exact same value as the license module's
     * LicenseModule::get_site_token() — the proxy stores one token per domain
     * (TOFU), so both modules must present the same identity.
     */
    public static function get_site_token(): string {
        if( class_exists( '\gVectors\License\LicenseModule' ) ) {
            return \gVectors\License\LicenseModule::get_site_token();
        }

        return hash_hmac( 'sha256', self::get_site_domain(), self::get_auth_salt() );
    }

    /**
     * Same salt derivation as the license module (kept in sync intentionally).
     */
    private static function get_auth_salt(): string {
        $parts = [];
        if( defined( 'AUTH_SALT' ) && AUTH_SALT !== '' )               $parts[] = AUTH_SALT;
        if( defined( 'SECURE_AUTH_SALT' ) && SECURE_AUTH_SALT !== '' ) $parts[] = SECURE_AUTH_SALT;
        if( defined( 'LOGGED_IN_SALT' ) && LOGGED_IN_SALT !== '' )     $parts[] = LOGGED_IN_SALT;
        if( defined( 'NONCE_SALT' ) && NONCE_SALT !== '' )             $parts[] = NONCE_SALT;

        if( ! empty( $parts ) ) {
            return implode( '|', $parts );
        }

        return hash( 'sha256', DB_NAME . ':' . DB_USER . ':' . self::get_site_domain() );
    }

    /**
     * Raw site domain (no protocol, no www, no trailing slash).
     */
    public static function get_site_domain(): string {
        if( class_exists( '\gVectors\License\LicenseModule' ) ) {
            return \gVectors\License\LicenseModule::get_site_domain();
        }

        return self::normalize_domain( get_site_url() );
    }

    /**
     * Normalize a site domain: lowercase, strip protocol/www/path.
     * Must match the server-side LicenseService::normalizeDomain() logic.
     */
    public static function normalize_domain( string $url ): string {
        $url = rtrim( strtolower( trim( $url ) ), '/' );
        $url = preg_replace( '#^https?://#', '', $url );
        $url = preg_replace( '#^www\.#', '', $url );

        return explode( '/', $url )[0];
    }
}
