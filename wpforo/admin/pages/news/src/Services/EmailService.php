<?php

namespace gVectors\News\Services;

use gVectors\News\Config;
use gVectors\News\NewsModule;
use WP_User;

// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

/**
 * Sends the two email types through the site's own wp_mail/SMTP:
 *  1. news digest — proxy-built wrapper + per-item HTML blocks
 *  2. license expiry reminder — fully proxy-built body
 *
 * The only local work is injecting the site tokens per recipient:
 *   {admin_name} {site_name} {site_url} {dashboard_url} {current_year}
 * License rows are NEVER built locally. The two email types are never combined.
 */
class EmailService {

    private $config;
    private $prefs;

    public function __construct( Config $config, PrefsService $prefs ) {
        $this->config = $config;
        $this->prefs  = $prefs;
    }

    /**
     * All site administrators — role query, NOT a capability query (unreliable).
     * @return WP_User[]
     */
    private function get_recipients(): array {
        return get_users( [ 'role__in' => [ 'administrator' ] ] );
    }

    /**
     * News emails: per-admin, only items neither dismissed nor already emailed,
     * filtered through the site + per-admin channel/category/relevance matrix.
     *
     * Items flagged `standalone` on the proxy bypass the digest and go out as
     * their own email (subject = item title). Everything else is combined into
     * ONE digest, each item rendered inside its category card; a single-item
     * digest uses that item's title as the subject (dynamic subject).
     */
    public function send_news_digest( array $payload ): void {
        $items = [];
        $now   = time();
        foreach( (array) ( $payload['news'] ?? [] ) as $item ) {
            if( ! empty( $item['expires_at'] ) && strtotime( $item['expires_at'] ) <= $now ) continue;
            $items[ $item['id'] ] = $item;
        }

        $recommendations = is_array( $payload['recommendations'] ?? null ) ? $payload['recommendations'] : [];
        $cross_sell      = is_array( $payload['cross_sell'] ?? null ) ? $payload['cross_sell'] : null;
        if( empty( $items ) && empty( $recommendations ) ) return;

        $wrapper  = ! empty( $payload['email_wrapper'] ) ? $payload['email_wrapper'] : $this->fallback_wrapper();
        $card_tpl = ! empty( $wrapper['item_card'] ) ? $wrapper['item_card'] : $this->fallback_card();

        $emailed_meta_key   = $this->config->get_emailed_news_meta();
        $dismissed_meta_key = $this->config->get_dismissed_news_meta();
        $recs_meta_key      = $this->config->get_emailed_recs_meta();

        foreach( $this->get_recipients() as $user ) {
            $emailed   = get_user_meta( $user->ID, $emailed_meta_key, true );
            $emailed   = is_array( $emailed ) ? $emailed : [];
            $dismissed = get_user_meta( $user->ID, $dismissed_meta_key, true );
            $dismissed = is_array( $dismissed ) ? $dismissed : [];

            $unseen = array_diff_key( $items, array_flip( array_merge( $emailed, $dismissed ) ) );

            // Site + per-admin matrix (category, relevance, unsubscribe). Filtered
            // items are NOT recorded as emailed — re-enabling resumes delivery.
            foreach( $unseen as $id => $item ) {
                if( ! $this->prefs->news_item_allowed( $item, PrefsService::CHANNEL_EMAILS, $user->ID ) ) {
                    unset( $unseen[ $id ] );
                }
            }

            // Cross-sell: not-yet-recommended, not-installed addons for THIS admin
            $recs        = $this->fresh_recommendations_for( $user, $recommendations );
            $rec_section = ! empty( $recs ) ? $this->render_recs_section( $recs, $cross_sell ) : '';

            if( empty( $unseen ) && $rec_section === '' ) continue;

            $standalone = [];
            $digest     = [];
            foreach( $unseen as $item ) {
                if( ! empty( $item['standalone'] ) ) $standalone[] = $item;
                else                                 $digest[]     = $item;
            }

            $sent_ids = [];

            // Standalone items: one email each, the item title as the subject
            foreach( $standalone as $item ) {
                $content = $this->render_news_card( $item, '', $card_tpl );
                $body    = str_replace( [ '{news_content}', '{news_count}' ], [ $content, '1' ], $wrapper['body_html'] );
                $subject = $item['title'] !== '' ? $item['title'] : $wrapper['subject'];
                if( $this->send( $user, $subject, $body ) ) {
                    $sent_ids[] = $item['id'];
                }
            }

            // Digest: every remaining item in its own category card, numbered when
            // multiple; the recommendations section (if any) rides at the bottom.
            if( ! empty( $digest ) ) {
                $count   = count( $digest );
                $content = '';
                $i       = 0;
                foreach( $digest as $item ) {
                    $i++;
                    $counter  = $count > 1 ? $i . ' / ' . $count : '';
                    $content .= $this->render_news_card( $item, $counter, $card_tpl );
                }
                $content .= $rec_section;

                $body = str_replace( [ '{news_content}', '{news_count}' ], [ $content, (string) $count ], $wrapper['body_html'] );

                // Dynamic subject: a single-item digest reads like a dedicated email
                if( $count === 1 && $digest[0]['title'] !== '' ) {
                    $subject = $digest[0]['title'];
                } else {
                    $subject = str_replace( '{news_count}', (string) $count, $wrapper['subject'] );
                }
                $subject = $subject !== '' ? $subject : __( 'News from gVectors', 'gvectors' );

                if( $this->send( $user, $subject, $body ) ) {
                    $sent_ids = array_merge( $sent_ids, array_column( $digest, 'id' ) );
                    $this->record_emailed_recs( $user->ID, $recs_meta_key, $recs );
                }
            } elseif( $rec_section !== '' ) {
                // No news today — the recommendations go out as their own email,
                // in the minimal local shell (the news wrapper's "what's new"
                // copy would read wrong with zero items).
                $shell   = $this->fallback_wrapper();
                $body    = str_replace( [ '{news_content}', '{news_count}' ], [ $rec_section, '0' ], $shell['body_html'] );
                $subject = ! empty( $cross_sell['subject'] ) ? $cross_sell['subject'] : __( 'Addons picked for your site', 'gvectors' );
                if( $this->send( $user, $subject, $body ) ) {
                    $this->record_emailed_recs( $user->ID, $recs_meta_key, $recs );
                }
            }

            if( ! empty( $sent_ids ) ) {
                update_user_meta( $user->ID, $emailed_meta_key, array_values( array_unique( array_merge( $emailed, $sent_ids ) ) ) );
            }
        }
    }

    /**
     * Recommendations this admin should still hear about: category allowed by
     * BOTH scopes, never emailed to them before, addon not installed locally.
     */
    private function fresh_recommendations_for( WP_User $user, array $recommendations ): array {
        if( empty( $recommendations ) ) return [];
        if( ! $this->prefs->allows( $user->ID, PrefsService::CHANNEL_EMAILS, 'recommendations' ) ) return [];

        $emailed = get_user_meta( $user->ID, $this->config->get_emailed_recs_meta(), true );
        $emailed = is_array( $emailed ) ? $emailed : [];

        $fresh = [];
        foreach( $recommendations as $rec ) {
            if( in_array( $rec['plugin_slug'], $emailed, true ) ) continue;
            if( $this->prefs->is_addon_installed( $rec['plugin_slug'] ) ) continue;
            $fresh[] = $rec;
        }

        return $fresh;
    }

    /**
     * Render the "Recommended for you" section from the proxy templates
     * (local fallbacks when the payload didn't carry them).
     */
    private function render_recs_section( array $recs, ?array $cross_sell ): string {
        $item_tpl = ! empty( $cross_sell['item'] ) ? $cross_sell['item']
            : '<div style="border:1px solid #e2e4e8;border-left:4px solid #2271b1;border-radius:4px;padding:14px 18px;margin:0 0 12px;">'
            . '<div style="font-size:14px;color:#1d2327;"><strong>{product_name}</strong></div>'
            . '<div style="font-size:13px;color:#50575e;margin-top:4px;">{reason}</div></div>';
        $section_tpl = ! empty( $cross_sell['section'] ) ? $cross_sell['section']
            : '<div style="border-top:2px solid #e2e4e8;margin:24px 0 0;padding:20px 0 0;">'
            . '<div style="font-size:11px;font-weight:bold;letter-spacing:0.8px;text-transform:uppercase;color:#8c8f94;margin:0 0 14px;">'
            . esc_html__( 'Recommended for you', 'gvectors' ) . '</div>{items}</div>';

        $items_html = '';
        foreach( $recs as $rec ) {
            $items_html .= str_replace(
                [ '{product_name}', '{reason}' ],
                [ esc_html( $rec['product_name'] ), esc_html( $rec['reason'] ) ],
                $item_tpl
            );
        }

        $section = str_replace( [ '{items}', '{item_count}' ], [ $items_html, (string) count( $recs ) ], $section_tpl );

        return wp_kses( $section, $this->email_allowed_html() );
    }

    private function record_emailed_recs( int $user_id, string $meta_key, array $recs ): void {
        if( empty( $recs ) ) return;
        $emailed = get_user_meta( $user_id, $meta_key, true );
        $emailed = is_array( $emailed ) ? $emailed : [];
        update_user_meta( $user_id, $meta_key, array_values( array_unique( array_merge( $emailed, array_column( $recs, 'plugin_slug' ) ) ) ) );
    }

    /**
     * Expiry reminder (upcoming expirations, phase > 0): per-admin, each
     * "license_id:phase" fires exactly once. Body fully assembled by the proxy.
     */
    public function send_expiry_reminders( array $at_risk ): void {
        $this->send_phase_email(
            $at_risk,
            'email',
            fn( $license ) => (int) ( $license['phase'] ?? 0 ) > 0,
            __( 'Your addon licenses are expiring soon', 'gvectors' )
        );
    }

    /**
     * Winback reminder (already-expired licenses, phase < 0): same per-admin
     * "license_id:phase" tracking — negative phases make the keys distinct.
     */
    public function send_winback_reminders( array $at_risk ): void {
        $this->send_phase_email(
            $at_risk,
            'winback_email',
            fn( $license ) => (int) ( $license['phase'] ?? 0 ) < 0,
            __( 'Your addon license has expired', 'gvectors' )
        );
    }

    /**
     * Shared engine for the two phase-keyed reminder emails.
     */
    private function send_phase_email( array $at_risk, string $email_field, callable $phase_filter, string $fallback_subject ): void {
        if( empty( $at_risk['licenses'] ) || empty( $at_risk[ $email_field ]['body_html'] ) ) return;

        $keys = [];
        foreach( $at_risk['licenses'] as $license ) {
            if( ! empty( $license['id'] ) && isset( $license['phase'] ) && $phase_filter( $license ) ) {
                $keys[] = sanitize_text_field( $license['id'] . ':' . (int) $license['phase'] );
            }
        }
        if( empty( $keys ) ) return;

        $meta_key = $this->config->get_emailed_phases_meta();
        $subject  = ! empty( $at_risk[ $email_field ]['subject'] ) ? $at_risk[ $email_field ]['subject'] : $fallback_subject;

        foreach( $this->get_recipients() as $user ) {
            if( ! $this->prefs->allows( $user->ID, PrefsService::CHANNEL_EMAILS, 'expiry_reminder' ) ) continue;

            $sent = get_user_meta( $user->ID, $meta_key, true );
            $sent = is_array( $sent ) ? $sent : [];

            $new_keys = array_diff( $keys, $sent );
            if( empty( $new_keys ) ) continue;

            if( $this->send( $user, $subject, $at_risk[ $email_field ]['body_html'] ) ) {
                update_user_meta( $user->ID, $meta_key, array_values( array_unique( array_merge( $sent, $new_keys ) ) ) );
            }
        }
    }

    /**
     * Purchase confirmation — fired by the license module's
     * `gvectors_transaction_licenses_activated` hook right after the dashboard
     * polling verified a completed checkout and activated the license(s).
     *
     * Sent ONLY to the administrator who actually made the purchase, with the
     * transaction id and EVERY license key — so the keys live in their inbox
     * for future activations on other domains (e.g. staging → production).
     *
     * IMPORTANT: the pending-transactions polling is site-wide — ANOTHER
     * admin's dashboard refresh may be the request that verifies/activates the
     * purchase. The license module therefore records the purchaser's user id
     * at checkout time and passes it here ($purchaser_id); the current user is
     * only a fallback for legacy pending entries without attribution.
     *
     * Deliberately bypasses the preference matrix: this is a receipt for the
     * recipient's own action, and losing the keys email would hurt them.
     * No proxy request is made — templates come from the cached daily payload
     * (or the local fallback), so consent state is not involved either.
     */
    public function handle_purchase_activated( string $transaction_id, array $licenses, string $core_slug = '', int $purchaser_id = 0 ): void {
        if( $transaction_id === '' || empty( $licenses ) ) return;

        $user = $purchaser_id > 0 ? get_user_by( 'id', $purchaser_id ) : null;
        if( ! $user || empty( $user->user_email ) ) {
            $user = wp_get_current_user(); // legacy entries without purchaser attribution
        }
        if( ! $user || ! $user->exists() || empty( $user->user_email ) ) return;
        if( ! user_can( $user, 'activate_plugins' ) ) return;

        // One confirmation per transaction, ever (polling/tabs may verify twice)
        $option_key = $this->config->get_emailed_transactions_option();
        $emailed    = get_option( $option_key, [] );
        $emailed    = is_array( $emailed ) ? $emailed : [];
        if( in_array( $transaction_id, $emailed, true ) ) return;

        $templates = $this->get_purchase_templates();

        $rows = '';
        foreach( $licenses as $license ) {
            if( ! is_array( $license ) || empty( $license['license_key'] ) ) continue;
            $expires = ! empty( $license['expires_at'] )
                ? date_i18n( 'F j, Y', strtotime( $license['expires_at'] ) )
                : __( 'Lifetime', 'gvectors' );
            $rows .= str_replace(
                [ '{product_name}', '{license_key}', '{expires_at}' ],
                [
                    esc_html( $license['product_name'] ?? ( $license['plugin_slug'] ?? __( 'Addon', 'gvectors' ) ) ),
                    esc_html( $license['license_key'] ),
                    esc_html( $expires ),
                ],
                $templates['license_row']
            );
        }
        if( $rows === '' ) return;

        $count   = count( $licenses );
        $body    = str_replace(
            [ '{transaction_id}', '{license_rows}', '{license_count}' ],
            [ esc_html( $transaction_id ), $rows, (string) $count ],
            $templates['body_html']
        );
        $subject = str_replace( '{license_count}', (string) $count, $templates['subject'] );

        if( $this->send( $user, $subject, $body ) ) {
            $emailed[] = $transaction_id;
            update_option( $option_key, array_slice( array_values( array_unique( $emailed ) ), -50 ) );
        }
    }

    /**
     * Abandoned checkout recovery — fired by the license module's scheduled
     * `gvectors_abandoned_checkout_check` when the proxy confirms a checkout
     * is still unpaid at a recovery phase. The email body arrives fully built
     * (proxy templates, incl. the attached-discount callout when the phase
     * carries one); this only resolves the recipient and dedups.
     *
     * Sent ONLY to the admin who started the checkout (recorded in the
     * scheduled event args at creation time). Gated by its own email-only
     * preference category 'abandoned_checkout' (per-admin + site-wide, and
     * allows() also covers the master unsubscribe); the server-side kill
     * switch is ABANDONED_CHECKOUT_PHASES (empty = the proxy hands out no
     * phases and nothing is ever scheduled).
     */
    public function handle_abandoned_checkout( string $transaction_id, array $email, int $purchaser_id = 0, int $phase_minutes = 0 ): void {
        if( $transaction_id === '' || empty( $email['body_html'] ) || ! is_string( $email['body_html'] ) ) return;

        // Runs in cron context — there is no meaningful current user to fall
        // back to; without a valid recorded purchaser, nothing is sent.
        $user = $purchaser_id > 0 ? get_user_by( 'id', $purchaser_id ) : null;
        if( ! $user || empty( $user->user_email ) ) return;
        if( ! user_can( $user, 'activate_plugins' ) ) return;

        if( ! $this->prefs->allows( $user->ID, PrefsService::CHANNEL_EMAILS, 'abandoned_checkout' ) ) return;

        $option_key = $this->config->get_abandoned_emailed_option();
        $emailed    = get_option( $option_key, [] );
        $emailed    = is_array( $emailed ) ? $emailed : [];
        $key        = sanitize_text_field( $transaction_id . ':' . (int) $phase_minutes );
        if( in_array( $key, $emailed, true ) ) return;

        $subject = ! empty( $email['subject'] ) && is_string( $email['subject'] )
            ? $email['subject']
            : __( 'You didn\'t finish your purchase', 'gvectors' );

        if( $this->send( $user, $subject, $email['body_html'] ) ) {
            $emailed[] = $key;
            update_option( $option_key, array_slice( array_values( array_unique( $emailed ) ), -100 ) );
        }
    }

    /**
     * Purchase templates: proxy-editable versions from the cached daily news
     * payload, with a complete local fallback (a purchase must never lose its
     * confirmation email because the cache is cold).
     */
    private function get_purchase_templates(): array {
        $payload = get_transient( $this->config->get_news_transient() );
        $remote  = is_array( $payload ) && ! empty( $payload['purchase_email']['body_html'] ) ? $payload['purchase_email'] : null;

        if( $remote ) {
            return [
                'subject'     => ! empty( $remote['subject'] ) ? $remote['subject'] : __( 'Payment received — your license key(s)', 'gvectors' ),
                'body_html'   => $remote['body_html'],
                'license_row' => ! empty( $remote['license_row'] ) ? $remote['license_row'] : $this->fallback_purchase_row(),
            ];
        }

        return [
            'subject'     => __( 'Payment received — your license key(s) for {site_name}', 'gvectors' ),
            'body_html'   => '<div style="background:#f4f5f7;padding:24px 0;font-family:Arial,Helvetica,sans-serif;">'
                . '<div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:8px;border:1px solid #e2e4e8;padding:28px;">'
                . '<p style="margin:0 0 16px;color:#1d2327;font-size:15px;">Hi {admin_name},</p>'
                . '<p style="margin:0 0 16px;color:#50575e;font-size:14px;line-height:1.6;">Your payment was received and {license_count} license(s) have been activated on {site_name}.</p>'
                . '<p style="margin:0 0 16px;color:#50575e;font-size:13px;">Transaction ID: <span style="font-family:monospace;color:#1d2327;">{transaction_id}</span></p>'
                . '{license_rows}'
                . '<p style="margin:16px 0 0;color:#996800;font-size:13px;line-height:1.6;"><strong>Keep this email</strong> — you will need these license keys to activate your addons on another domain (e.g. when moving from staging to production).</p>'
                . '</div></div>',
            'license_row' => $this->fallback_purchase_row(),
        ];
    }

    private function fallback_purchase_row(): string {
        return '<div style="border:1px solid #e2e4e8;border-left:4px solid #00753e;border-radius:4px;padding:14px 18px;margin:0 0 12px;">'
            . '<div style="font-size:14px;color:#1d2327;"><strong>{product_name}</strong></div>'
            . '<div style="margin-top:8px;font-family:monospace;font-size:13px;color:#1d2327;background:#f6f7f7;border:1px dashed #c3c4c7;border-radius:3px;padding:8px 10px;">{license_key}</div>'
            . '<div style="font-size:12px;color:#8c8f94;margin-top:6px;">Valid until: {expires_at}</div>'
            . '</div>';
    }

    /**
     * Unused-discount reminder: the site holds active, still-redeemable
     * renewal offers (individual single-use discounts minted by the proxy —
     * either by the automatic expiry phases or manually via the dashboard
     * bulk tool) that have NOT been used yet. The proxy pre-builds the email;
     * this only handles recipients and dedup.
     *
     * Per-admin dedup keys in the shared phases meta:
     *   "offer:{id}"        — the first announcement of an offer
     *   "offer:{id}:final"  — one last-chance nudge when the offer is within
     *                         3 days of expiring and still unused
     * so each admin gets at most two emails per offer, ever.
     *
     * Own preference category 'renewal_offer' — unsubscribable on the settings
     * page separately from the expiry/billing reminders.
     */
    public function send_offer_reminders( array $at_risk ): void {
        if( empty( $at_risk['active_offers'] ) || ! is_array( $at_risk['active_offers'] ) ) return;
        if( empty( $at_risk['offer_email']['body_html'] ) ) return;

        $keys = [];
        foreach( $at_risk['active_offers'] as $offer ) {
            if( ! is_array( $offer ) || empty( $offer['id'] ) ) continue;
            $id     = (int) $offer['id'];
            $keys[] = 'offer:' . $id;
            if( isset( $offer['days_left'] ) && (int) $offer['days_left'] <= 3 ) {
                $keys[] = 'offer:' . $id . ':final';
            }
        }
        if( empty( $keys ) ) return;

        $meta_key = $this->config->get_emailed_phases_meta();
        $subject  = ! empty( $at_risk['offer_email']['subject'] )
            ? $at_risk['offer_email']['subject']
            : __( 'You have an unused discount for your addons', 'gvectors' );

        foreach( $this->get_recipients() as $user ) {
            if( ! $this->prefs->allows( $user->ID, PrefsService::CHANNEL_EMAILS, 'renewal_offer' ) ) continue;

            $sent = get_user_meta( $user->ID, $meta_key, true );
            $sent = is_array( $sent ) ? $sent : [];

            $new_keys = array_diff( $keys, $sent );
            if( empty( $new_keys ) ) continue;

            if( $this->send( $user, $subject, $at_risk['offer_email']['body_html'] ) ) {
                update_user_meta( $user->ID, $meta_key, array_values( array_unique( array_merge( $sent, $new_keys ) ) ) );
            }
        }
    }

    /**
     * Dunning notice: a subscription is past_due (payment failed). Per-admin
     * dedup key "dun:{subscription_id}:{Y-m}" — at most one email per
     * subscription per month while the dunning state persists.
     */
    public function send_dunning_notices( array $at_risk ): void {
        if( empty( $at_risk['past_due'] ) || empty( $at_risk['dunning_email']['body_html'] ) ) return;

        $keys = [];
        foreach( $at_risk['past_due'] as $entry ) {
            if( ! empty( $entry['subscription_id'] ) ) {
                $keys[] = sanitize_text_field( 'dun:' . $entry['subscription_id'] . ':' . gmdate( 'Y-m' ) );
            }
        }
        if( empty( $keys ) ) return;

        $meta_key = $this->config->get_emailed_phases_meta();
        $subject  = ! empty( $at_risk['dunning_email']['subject'] )
            ? $at_risk['dunning_email']['subject']
            : __( 'A payment failed for your addon subscription', 'gvectors' );

        foreach( $this->get_recipients() as $user ) {
            if( ! $this->prefs->allows( $user->ID, PrefsService::CHANNEL_EMAILS, 'expiry_reminder' ) ) continue;

            $sent = get_user_meta( $user->ID, $meta_key, true );
            $sent = is_array( $sent ) ? $sent : [];

            $new_keys = array_diff( $keys, $sent );
            if( empty( $new_keys ) ) continue;

            if( $this->send( $user, $subject, $at_risk['dunning_email']['body_html'] ) ) {
                update_user_meta( $user->ID, $meta_key, array_values( array_unique( array_merge( $sent, $new_keys ) ) ) );
            }
        }
    }

    /**
     * Replace the site tokens for a recipient, strip any leftover {tokens},
     * append the compliance footer and send as HTML mail.
     */
    private function send( WP_User $user, string $subject, string $body_html ): bool {
        $subject = $this->render_site_tokens( $subject, $user, false );
        $body    = $this->render_site_tokens( $body_html, $user, true );
        $body   .= $this->footer_line();

        return (bool) wp_mail(
            $user->user_email,
            wp_specialchars_decode( $subject, ENT_QUOTES ),
            $body,
            [ 'Content-Type: text/html; charset=UTF-8' ]
        );
    }

    /**
     * Inject the 5 WP-side tokens (escaped), then strip any leftover {token}
     * so a proxy-side typo never reaches a recipient.
     */
    public function render_site_tokens( string $html, WP_User $user, bool $escape = true ): string {
        $admin_name = $user->display_name !== '' ? $user->display_name : $user->user_login;
        $tokens     = [
            '{admin_name}'    => $escape ? esc_html( $admin_name ) : $admin_name,
            '{site_name}'     => $escape ? esc_html( get_bloginfo( 'name' ) ) : get_bloginfo( 'name' ),
            '{site_url}'      => esc_url( home_url() ),
            '{dashboard_url}' => esc_url( $this->config->get_dashboard_addons_url() ),
            '{current_year}'  => gmdate( 'Y' ),
        ];

        $html = strtr( $html, $tokens );

        return preg_replace( '/\{[a-z0-9_]+\}/i', '', $html );
    }

    /**
     * Local fallback map when the proxy didn't enrich items with display meta
     * (older proxy version). Keep in sync with NewsService::TYPE_META proxy-side.
     */
    private const TYPE_META_FALLBACK = [
        'new_addon'    => [ 'label' => 'New Addon',    'badge_bg' => '#dcfce7', 'badge_color' => '#166534' ],
        'new_feature'  => [ 'label' => 'New Feature',  'badge_bg' => '#fff7ed', 'badge_color' => '#9a3412' ],
        'new_version'  => [ 'label' => 'New Version',  'badge_bg' => '#f3e8ff', 'badge_color' => '#7e22ce' ],
        'discount'     => [ 'label' => 'Discount',     'badge_bg' => '#fce7f3', 'badge_color' => '#be185d' ],
        'announcement' => [ 'label' => 'Announcement', 'badge_bg' => '#dbeafe', 'badge_color' => '#1d4ed8' ],
    ];

    /**
     * One news item → its category card: colored badge header (category label +
     * "i / n" counter when the email holds several items), bold title, then the
     * item's rich content. Content is sanitized with an email-safe allowlist
     * (defense in depth — the proxy is trusted, but its content still never
     * reaches the mail body unfiltered).
     */
    private function render_news_card( array $item, string $counter, string $card_tpl ): string {
        // Inner content: proxy-rendered rich HTML, or a paragraph from the plain body
        if( ! empty( $item['html_content'] ) ) {
            $content = $item['html_content'];
        } else {
            $content = ! empty( $item['body'] )
                ? '<p style="margin:0;">' . nl2br( esc_html( $item['body'] ) ) . '</p>'
                : '';
        }
        if( ! empty( $item['link_url'] ) ) {
            $label    = ! empty( $item['link_label'] ) ? $item['link_label'] : __( 'Learn more', 'gvectors' );
            $content .= '<div style="margin-top:12px;"><a href="' . esc_url( $item['link_url'] ) . '" style="background:#2271b1;color:#ffffff;text-decoration:none;padding:8px 16px;border-radius:4px;font-size:13px;display:inline-block;">' . esc_html( $label ) . '</a></div>';
        }
        $content = wp_kses( $content, $this->email_allowed_html() );

        $meta = $this->item_type_meta( $item );

        return str_replace(
            [ '{type_label}', '{badge_bg}', '{badge_color}', '{title}', '{content}', '{item_counter}' ],
            [ esc_html( $meta['label'] ), $meta['badge_bg'], $meta['badge_color'], esc_html( $item['title'] ), $content, esc_html( $counter ) ],
            $card_tpl
        );
    }

    /**
     * Display meta for an item: prefer proxy-provided label/colors (source of
     * truth), validated; fall back to the local map for older proxies.
     */
    private function item_type_meta( array $item ): array {
        $meta = self::TYPE_META_FALLBACK[ $item['type'] ?? '' ] ?? self::TYPE_META_FALLBACK['announcement'];

        if( ! empty( $item['type_label'] ) && is_string( $item['type_label'] ) ) {
            $meta['label'] = $item['type_label'];
        }
        foreach( [ 'badge_bg', 'badge_color' ] as $key ) {
            if( ! empty( $item[ $key ] ) && is_string( $item[ $key ] ) && preg_match( '/^#[0-9a-fA-F]{3,8}$/', $item[ $key ] ) ) {
                $meta[ $key ] = $item[ $key ];
            }
        }

        return $meta;
    }

    /**
     * Minimal local card used only when the proxy wrapper didn't ship one.
     */
    private function fallback_card(): string {
        return '<div style="border:1px solid #e2e4e8;border-radius:6px;margin:0 0 18px;overflow:hidden;">'
            . '<div style="background:{badge_bg};padding:8px 16px;">'
            . '<span style="color:{badge_color};font-size:11px;font-weight:bold;letter-spacing:0.8px;text-transform:uppercase;">{type_label}</span>'
            . '<span style="float:right;color:{badge_color};font-size:11px;font-weight:bold;opacity:0.75;">{item_counter}</span>'
            . '</div>'
            . '<div style="padding:16px 18px;">'
            . '<div style="font-size:15px;color:#1d2327;font-weight:bold;margin:0 0 10px;">{title}</div>'
            . '<div style="font-size:13px;color:#50575e;line-height:1.6;">{content}</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * Email-safe HTML allowlist for news content blocks.
     */
    private function email_allowed_html(): array {
        $common = [ 'style' => true, 'class' => true, 'align' => true, 'width' => true, 'height' => true ];
        return [
            'div'    => $common,
            'p'      => $common,
            'span'   => $common,
            'a'      => $common + [ 'href' => true, 'target' => true, 'rel' => true ],
            'img'    => $common + [ 'src' => true, 'alt' => true ],
            'strong' => $common, 'b' => $common, 'em' => $common, 'i' => $common,
            'h1'     => $common, 'h2' => $common, 'h3' => $common, 'h4' => $common,
            'ul'     => $common, 'ol' => $common, 'li' => $common,
            'table'  => $common + [ 'cellpadding' => true, 'cellspacing' => true, 'border' => true ],
            'thead'  => $common, 'tbody' => $common, 'tr' => $common, 'td' => $common + [ 'colspan' => true ], 'th' => $common + [ 'colspan' => true ],
            'br'     => [], 'hr' => $common,
        ];
    }

    /**
     * Compliance footer: why this email was received + how to stop it.
     * Links to the News & Emails settings page where each administrator can
     * unsubscribe, pick email categories, or disable the whole service.
     */
    private function footer_line(): string {
        return '<div style="max-width:600px;margin:12px auto 0;padding:0 4px;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#8c8f94;line-height:1.5;">'
            . sprintf(
                /* translators: 1: site URL, 2: News & Emails settings page URL */
                esc_html__( 'You received this email because you are an administrator of %1$s. To unsubscribe or choose which emails you receive, open the %2$s settings page in your WordPress dashboard.', 'gvectors' ),
                '<a href="' . esc_url( home_url() ) . '" style="color:#8c8f94;">' . esc_html( NewsModule::get_site_domain() ) . '</a>',
                '<a href="' . esc_url( $this->config->get_settings_page_url() ) . '" style="color:#8c8f94;">' . esc_html__( 'News & Emails', 'gvectors' ) . '</a>'
            )
            . '</div>';
    }

    /**
     * Minimal local shell used only when the proxy wrapper is unavailable.
     */
    private function fallback_wrapper(): array {
        return [
            'subject'   => __( 'News from gVectors', 'gvectors' ),
            'body_html' => '<div style="background:#f4f5f7;padding:24px 0;font-family:Arial,Helvetica,sans-serif;">'
                . '<div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:8px;border:1px solid #e2e4e8;padding:28px;">'
                . '<p style="margin:0 0 16px;color:#1d2327;font-size:15px;">Hi {admin_name},</p>'
                . '{news_content}'
                . '</div></div>',
        ];
    }
}
