<?php

namespace gVectors\News;

use gVectors\News\Services\ConsentService;
use gVectors\News\Services\PrefsService;

// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

/**
 * News & Emails settings page.
 *
 * Every registered gVectors plugin gets its own menu entry, but all entries
 * render the SAME page over the SAME shared options.
 *
 * Two mirrored sections, each a channel × category matrix:
 *  A) My Preferences  — the logged-in administrator's own choices (user meta):
 *     master email unsubscribe + per-category checkboxes for dashboard notices
 *     and for emails, including installed/not-installed addon relevance.
 *  B) Site-Wide       — the same matrix applied to the whole site for all
 *     admins, plus the master service switch (full opt-out after opt-in).
 */
class AdminPage {

    /** category key → [label, description] */
    private const CATEGORY_LABELS = [
        'expiry_reminder'      => [ 'License expiry & billing reminders', 'Transactional — expiring/expired license warnings and failed-payment notices. Email only.' ],
        'renewal_offer'        => [ 'Renewal discount offers', 'Reminders about unused personal renewal discounts reserved for this site, before they expire. Email only.' ],
        'abandoned_checkout'   => [ 'Unfinished purchase reminders', 'Reminders when a checkout you started was not completed, including any recovery discount. Sent only to the administrator who started the purchase. Email only.' ],
        'recommendations'      => [ 'Addon recommendations', 'Personalized suggestions of addons other sites with a similar setup use. Each recommendation is sent at most once. Email only.' ],
        'new_addon'            => [ 'New addon announcements', 'Marketing — when a new addon is released.' ],
        'new_feature'          => [ 'New feature announcements', 'Marketing — when an addon gets a major new feature.' ],
        'new_version'          => [ 'New version releases', 'Version releases — changelogs, scheduled release dates, and actions required before or after an update.' ],
        'discount'             => [ 'Discounts & promotions', 'Marketing — sales, coupon codes and limited-time offers.' ],
        'announcement'         => [ 'General announcements', 'Marketing — other news from gVectors.' ],
        'addons_installed'     => [ 'News about installed addons', 'Addon-targeted news for addons this site has installed.' ],
        'addons_not_installed' => [ 'News about addons not installed here', 'Addon-targeted news for addons this site does not have — disable to skip irrelevant news.' ],
    ];

    private $config;
    private $consent;
    private $prefs;

    public function __construct( Config $config, ConsentService $consent, PrefsService $prefs ) {
        $this->config  = $config;
        $this->consent = $consent;
        $this->prefs   = $prefs;

        // Form handlers are shared — registered once, whichever menu the form was opened from
        add_action( 'admin_post_' . $this->config->get_prefix() . 'save_my_email_prefs', [ $this, 'handle_save_my_prefs' ] );
        add_action( 'admin_post_' . $this->config->get_prefix() . 'save_site_email_settings', [ $this, 'handle_save_site_settings' ] );

        $this->add_context( $config );
    }

    /**
     * Register one gVectors plugin's "News & Emails" menu item.
     * Every registered plugin gets its own menu entry under its own admin menu,
     * but they all render the SAME page and store the SAME shared options.
     */
    public function add_context( Config $config ): void {
        add_action( $config->get_dashboard_menu_hook(), function( $parent_slug ) use ( $config ) {
            add_submenu_page(
                $parent_slug,
                __( 'News & Emails', 'gvectors' ),
                __( 'News & Emails', 'gvectors' ),
                'activate_plugins',
                $config->get_settings_page_slug(),
                function() {
                    $this->render_page();
                }
            );
        } );
    }

    // ==========================================
    // Form handlers (admin-post.php)
    // ==========================================

    /**
     * Save the current administrator's own preference matrix.
     */
    public function handle_save_my_prefs(): void {
        if( ! current_user_can( 'activate_plugins' ) ) {
            wp_die( esc_html__( 'Permission denied', 'gvectors' ) );
        }
        check_admin_referer( $this->config->get_prefix() . 'save_my_email_prefs' );

        $this->prefs->save_user_prefs(
            get_current_user_id(),
            ! empty( $_POST['gv_unsubscribed'] ),
            $this->read_matrix_input( 'my' )
        );

        $this->redirect_back( 'prefs' );
    }

    /**
     * Save the site-wide matrix + the master service switch.
     */
    public function handle_save_site_settings(): void {
        if( ! current_user_can( 'activate_plugins' ) ) {
            wp_die( esc_html__( 'Permission denied', 'gvectors' ) );
        }
        check_admin_referer( $this->config->get_prefix() . 'save_site_email_settings' );

        $was_enabled = $this->consent->is_enabled();
        $enable      = ! empty( $_POST['gv_service_enabled'] );

        update_option( $this->config->get_service_enabled_option(), $enable );
        $this->prefs->save_site_prefs( $this->read_matrix_input( 'site' ) );

        if( $enable && ! $was_enabled ) {
            // Opt-in from this page counts as consent — suppress the opt-in notice
            // and run the first sync right away instead of waiting for the daily cron.
            update_option( $this->config->get_optin_notice_dismissed_option(), true );
            if( ! wp_next_scheduled( $this->config->get_cron_hook() ) ) {
                wp_schedule_single_event( time() + 10, $this->config->get_cron_hook() );
            }
        } elseif( ! $enable && $was_enabled ) {
            // Opt-out: stop the daily sync immediately (the scheduler on `init`
            // would also clear it, but don't leave a scheduled event behind).
            wp_clear_scheduled_hook( $this->config->get_cron_hook() );
        }

        $this->redirect_back( 'site' );
    }

    /**
     * Collect checked channel/category checkboxes from the submitted form.
     * Field names: gv_{scope}_{channel}_{category}, e.g. gv_my_notices_new_addon.
     */
    private function read_matrix_input( string $scope ): array {
        $enabled = [];
        foreach( PrefsService::CHANNELS as $channel ) {
            $enabled[ $channel ] = [];
            foreach( PrefsService::channel_categories( $channel ) as $category ) {
                if( ! empty( $_POST[ 'gv_' . $scope . '_' . $channel . '_' . $category ] ) ) {
                    $enabled[ $channel ][] = $category;
                }
            }
        }

        return $enabled;
    }

    /**
     * Settings page URL to return to after saving — the page the form was
     * submitted from (each registered plugin has its own menu entry).
     */
    private function get_return_url(): string {
        $requested = isset( $_POST['gv_return'] ) ? sanitize_key( $_POST['gv_return'] ) : '';
        foreach( NewsModule::get_contexts() as $config ) {
            if( $requested === $config->get_settings_page_slug() ) {
                return $config->get_settings_page_url();
            }
        }

        return $this->config->get_settings_page_url();
    }

    private function redirect_back( string $section ): void {
        wp_safe_redirect( add_query_arg( 'updated', $section, $this->get_return_url() ) );
        exit;
    }

    // ==========================================
    // Page rendering
    // ==========================================

    /**
     * One channel × category checkbox matrix.
     *
     * @param string $scope  'my' | 'site' — field name prefix
     * @param array  $matrix ['notices' => [cat => bool], 'emails' => [cat => bool]]
     */
    private function render_matrix( string $scope, array $matrix ): void {
        $all_categories = array_merge(
            PrefsService::EMAIL_ONLY_CATEGORIES,
            PrefsService::NEWS_CATEGORIES,
            PrefsService::RELEVANCE_CATEGORIES
        );
        ?>
        <table class="widefat striped" style="max-width:860px;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Category', 'gvectors' ); ?></th>
                    <th style="width:130px;text-align:center;"><?php esc_html_e( 'Dashboard notices', 'gvectors' ); ?></th>
                    <th style="width:130px;text-align:center;"><?php esc_html_e( 'Emails', 'gvectors' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach( $all_categories as $category ) :
                    $label = self::CATEGORY_LABELS[ $category ] ?? [ $category, '' ];
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html( __( $label[0], 'gvectors' ) ); ?></strong>
                            <?php if( $label[1] ) : ?>
                                <br><span class="description"><?php echo esc_html( __( $label[1], 'gvectors' ) ); ?></span>
                            <?php endif; ?>
                        </td>
                        <?php foreach( PrefsService::CHANNELS as $channel ) : ?>
                            <td style="text-align:center;">
                                <?php if( in_array( $category, PrefsService::channel_categories( $channel ), true ) ) : ?>
                                    <input type="checkbox"
                                           name="gv_<?php echo esc_attr( $scope . '_' . $channel . '_' . $category ); ?>"
                                           value="1" <?php checked( ! empty( $matrix[ $channel ][ $category ] ) ); ?>>
                                <?php else : ?>
                                    <span aria-hidden="true">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function render_page(): void {
        if( ! current_user_can( 'activate_plugins' ) ) return;

        $user       = wp_get_current_user();
        $user_prefs = $this->prefs->get_user_prefs( $user->ID );
        $site_prefs = $this->prefs->get_site_prefs();
        $updated    = isset( $_GET['updated'] ) ? sanitize_key( $_GET['updated'] ) : '';
        $prefix     = $this->config->get_prefix();
        $current    = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
        $products   = array_keys( NewsModule::get_contexts() );
        ?>
        <div class="wrap gvectors-news-settings">
            <h1><?php esc_html_e( 'News & Emails', 'gvectors' ); ?></h1>

            <?php if( $updated === 'prefs' ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Your preferences have been saved.', 'gvectors' ); ?></p></div>
            <?php elseif( $updated === 'site' ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Site-wide settings have been saved.', 'gvectors' ); ?></p></div>
            <?php endif; ?>

            <!-- ── Section A: this administrator's own preferences ── -->
            <h2><?php esc_html_e( 'My Preferences', 'gvectors' ); ?></h2>
            <p class="description">
                <?php
                printf(
                    /* translators: %s: the administrator's email address */
                    esc_html__( 'These settings apply only to your own account (%s). Other administrators manage their preferences on this same page. A dashboard notice you dismiss is dismissed only for you.', 'gvectors' ),
                    '<strong>' . esc_html( $user->user_email ) . '</strong>'
                );
                ?>
            </p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr( $prefix . 'save_my_email_prefs' ); ?>">
                <input type="hidden" name="gv_return" value="<?php echo esc_attr( $current ); ?>">
                <?php wp_nonce_field( $prefix . 'save_my_email_prefs' ); ?>

                <p>
                    <label>
                        <input type="checkbox" name="gv_unsubscribed" value="1" <?php checked( $user_prefs['unsubscribed'] ); ?>>
                        <strong><?php esc_html_e( 'Unsubscribe my email from ALL news module emails', 'gvectors' ); ?></strong>
                    </label>
                    <br><span class="description"><?php esc_html_e( 'When checked, you receive no emails from this module regardless of the matrix below. Dashboard notices are not affected.', 'gvectors' ); ?></span>
                </p>

                <?php $this->render_matrix( 'my', $user_prefs ); ?>

                <p class="submit"><input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Save My Preferences', 'gvectors' ); ?>"></p>
            </form>

            <hr>

            <!-- ── Section B: site-wide switches (all administrators) ── -->
            <h2><?php esc_html_e( 'Site-Wide Settings', 'gvectors' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'These settings apply to the whole site and all administrators: a category disabled here is off for everyone, regardless of personal preferences.', 'gvectors' ); ?>
                <?php
                if( count( $products ) > 1 ) {
                    printf(
                        /* translators: %s: comma-separated list of gVectors plugin slugs */
                        esc_html__( 'They are shared by all installed gVectors products (%s) — news, emails and license reminders are managed once for all of them.', 'gvectors' ),
                        '<strong>' . esc_html( implode( ', ', $products ) ) . '</strong>'
                    );
                }
                ?>
            </p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr( $prefix . 'save_site_email_settings' ); ?>">
                <input type="hidden" name="gv_return" value="<?php echo esc_attr( $current ); ?>">
                <?php wp_nonce_field( $prefix . 'save_site_email_settings' ); ?>

                <p>
                    <label>
                        <input type="checkbox" name="gv_service_enabled" value="1" <?php checked( $this->consent->is_enabled() ); ?>>
                        <strong><?php esc_html_e( 'Enable the addon news service', 'gvectors' ); ?></strong>
                    </label>
                    <br><span class="description">
                        <?php esc_html_e( 'Master switch. When enabled, this site connects to the gVectors server once a day to fetch addon news and check for expiring licenses. When disabled, the daily sync stops and no outbound requests are made.', 'gvectors' ); ?>
                        <a href="<?php echo esc_url( $this->config->get_privacy_policy_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Privacy policy', 'gvectors' ); ?></a>
                    </span>
                </p>

                <?php $this->render_matrix( 'site', $site_prefs ); ?>

                <p class="submit"><input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Site-Wide Settings', 'gvectors' ); ?>"></p>
            </form>
        </div>
        <?php
    }
}
