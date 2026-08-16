<?php

namespace gVectors\News\Services;

use gVectors\News\Config;

// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

/**
 * Central preference matrix: channel × category, at two scopes.
 *
 * Channels:
 *   - notices : dashboard admin notices
 *   - emails  : emails sent via wp_mail
 *
 * Categories:
 *   - expiry_reminder                  (emails only — transactional)
 *   - renewal_offer                    (emails only — unused personal renewal
 *     discount reminders, unsubscribable separately from expiry warnings)
 *   - new_addon, new_feature, announcement  (news content types)
 *   - addons_installed / addons_not_installed  (relevance filter — applies only
 *     to news items targeted at a specific addon via plugin_slug: news about an
 *     addon the site has installed vs news about addons it doesn't have)
 *
 * Scopes:
 *   - site  : one shared option, applies to everyone (option: site prefs)
 *   - user  : each administrator's own choices (user meta), incl. a master
 *             email unsubscribe
 *
 * A news item reaches an admin through a channel only when BOTH scopes allow
 * its content category AND (for addon-targeted items) its relevance category.
 * Everything defaults to enabled — only an explicit false disables.
 */
class PrefsService {

    public const CHANNEL_NOTICES = 'notices';
    public const CHANNEL_EMAILS  = 'emails';
    public const CHANNELS        = [ self::CHANNEL_NOTICES, self::CHANNEL_EMAILS ];

    /** News content categories (map 1:1 from news item types). */
    public const NEWS_CATEGORIES = [ 'new_addon', 'new_feature', 'new_version', 'discount', 'announcement' ];

    /** Relevance filter for addon-targeted news (items with a plugin_slug). */
    public const RELEVANCE_CATEGORIES = [ 'addons_installed', 'addons_not_installed' ];

    /** Categories that only exist on the emails channel. */
    public const EMAIL_ONLY_CATEGORIES = [ 'expiry_reminder', 'renewal_offer', 'abandoned_checkout', 'recommendations' ];

    private $config;

    public function __construct( Config $config ) {
        $this->config = $config;
    }

    /**
     * All category keys available on a channel.
     */
    public static function channel_categories( string $channel ): array {
        $categories = array_merge( self::NEWS_CATEGORIES, self::RELEVANCE_CATEGORIES );
        if( $channel === self::CHANNEL_EMAILS ) {
            $categories = array_merge( self::EMAIL_ONLY_CATEGORIES, $categories );
        }

        return $categories;
    }

    // ==========================================
    // Per-admin preferences (user meta)
    // ==========================================

    /**
     * Shape: ['unsubscribed' => bool, 'notices' => [cat => bool], 'emails' => [cat => bool]]
     * Missing meta or missing keys mean enabled.
     */
    public function get_user_prefs( int $user_id ): array {
        $raw = get_user_meta( $user_id, $this->config->get_user_prefs_meta(), true );
        $raw = is_array( $raw ) ? $raw : [];

        $prefs = [ 'unsubscribed' => ! empty( $raw['unsubscribed'] ) ];
        foreach( self::CHANNELS as $channel ) {
            foreach( self::channel_categories( $channel ) as $category ) {
                $prefs[ $channel ][ $category ] = ! isset( $raw[ $channel ][ $category ] ) || ! empty( $raw[ $channel ][ $category ] );
            }
        }

        return $prefs;
    }

    /**
     * @param array $enabled_by_channel ['notices' => ['new_addon', ...], 'emails' => [...]] — enabled category keys per channel
     */
    public function save_user_prefs( int $user_id, bool $unsubscribed, array $enabled_by_channel ): void {
        update_user_meta(
            $user_id,
            $this->config->get_user_prefs_meta(),
            [ 'unsubscribed' => $unsubscribed ] + $this->normalize_matrix( $enabled_by_channel )
        );
    }

    // ==========================================
    // Site-wide preferences (shared option)
    // ==========================================

    /**
     * Shape: ['notices' => [cat => bool], 'emails' => [cat => bool]] — missing keys mean enabled.
     */
    public function get_site_prefs(): array {
        $raw = get_option( $this->config->get_site_prefs_option(), [] );
        $raw = is_array( $raw ) ? $raw : [];

        $prefs = [];
        foreach( self::CHANNELS as $channel ) {
            foreach( self::channel_categories( $channel ) as $category ) {
                $prefs[ $channel ][ $category ] = ! isset( $raw[ $channel ][ $category ] ) || ! empty( $raw[ $channel ][ $category ] );
            }
        }

        return $prefs;
    }

    /**
     * @param array $enabled_by_channel ['notices' => [enabled keys], 'emails' => [enabled keys]]
     */
    public function save_site_prefs( array $enabled_by_channel ): void {
        update_option( $this->config->get_site_prefs_option(), $this->normalize_matrix( $enabled_by_channel ) );
    }

    private function normalize_matrix( array $enabled_by_channel ): array {
        $matrix = [];
        foreach( self::CHANNELS as $channel ) {
            $enabled = isset( $enabled_by_channel[ $channel ] ) && is_array( $enabled_by_channel[ $channel ] )
                ? $enabled_by_channel[ $channel ]
                : [];
            foreach( self::channel_categories( $channel ) as $category ) {
                $matrix[ $channel ][ $category ] = in_array( $category, $enabled, true );
            }
        }

        return $matrix;
    }

    // ==========================================
    // Decision helpers
    // ==========================================

    public function site_allows( string $channel, string $category ): bool {
        $site = $this->get_site_prefs();

        return ! empty( $site[ $channel ][ $category ] );
    }

    /**
     * Per-admin check. On the emails channel a master unsubscribe blocks everything.
     */
    public function user_allows( int $user_id, string $channel, string $category ): bool {
        $prefs = $this->get_user_prefs( $user_id );
        if( $channel === self::CHANNEL_EMAILS && $prefs['unsubscribed'] ) return false;

        return ! empty( $prefs[ $channel ][ $category ] );
    }

    /**
     * Combined site + user check — the only gate senders/renderers should use.
     */
    public function allows( int $user_id, string $channel, string $category ): bool {
        return $this->site_allows( $channel, $category ) && $this->user_allows( $user_id, $channel, $category );
    }

    /**
     * Full decision for one news item on one channel for one admin:
     * content category must be allowed, and for addon-targeted items the
     * relevance category (installed / not installed) must be allowed too.
     */
    public function news_item_allowed( array $item, string $channel, int $user_id ): bool {
        $category = in_array( $item['type'] ?? '', self::NEWS_CATEGORIES, true ) ? $item['type'] : 'announcement';
        if( ! $this->allows( $user_id, $channel, $category ) ) return false;

        if( ! empty( $item['plugin_slug'] ) ) {
            $relevance = $this->is_addon_installed( (string) $item['plugin_slug'] ) ? 'addons_installed' : 'addons_not_installed';
            if( ! $this->allows( $user_id, $channel, $relevance ) ) return false;
        }

        return true;
    }

    /**
     * Is an addon with this plugin slug installed on the site (any activation state)?
     * Matched by plugin directory name; result cached per request.
     */
    public function is_addon_installed( string $slug ): bool {
        static $installed = null;
        if( $installed === null ) {
            if( ! function_exists( 'get_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $installed = [];
            foreach( array_keys( get_plugins() ) as $plugin_file ) {
                $installed[ dirname( $plugin_file ) ] = true;
            }
        }

        return isset( $installed[ $slug ] );
    }
}
