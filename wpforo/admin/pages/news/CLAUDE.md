# gVectors News Module – CLAUDE.md

**Location:** `admin/pages/news/` (git submodule)
**Namespace:** `gVectors\News` | **Composer package:** `gvectors/news`
**PHP:** ≥ 7.2 | Autoloaded via PSR-4 from `src/`

---

## Purpose

Self-contained, reusable library shared by gVectors plugins (wpForo, wpDiscuz, ...) that handles:
- Daily fetch of global news (new addons, new features, announcements) from the gVectors proxy server
- Dismissible admin notices for news items (max 3 at once, per-user dismissal)
- News digest emails to site administrators (proxy-built wrapper, sent via the site's own `wp_mail`)
- License expiry reminder emails (body fully assembled by the proxy — no local date math or row building)
- Explicit opt-in consent gate: **zero outbound requests before consent** (WordPress.org Guideline 7)

The proxy server (`store.gvectors.com`) is the single source of truth. This module is a thin renderer/sender.

## ONE module for ALL gVectors products (singleton)

The module ships as a submodule inside every gVectors plugin (wpForo, wpDiscuz, ...), but on a site
running several of them it initializes ONCE:

- The bootstrap's `class_exists()` guard means only the first-loaded plugin's copy of the code runs.
- `NewsModule::__construct` builds the shared service tree (cron, notices, AJAX, emails) only for the
  FIRST plugin; every later plugin just registers a **context** (its Config) and gets its own
  settings menu item via `AdminPage::add_context()`.
- Storage is shared: all options, the news transient, user meta and the cron hook use the plain
  `gvectors_` prefix (NOT plugin-prefixed) — news and licenses are gVectors-wide. Same pattern as the
  license module's shared `gvectors_revalidate` cron.
- Result: one cron job, one news cache, one set of admin notices (each item printed once), one
  opt-in, one email per admin — no matter how many gVectors plugins are installed.
- Per-plugin bits: each plugin's settings menu item (`{slug}-news-settings`, all rendering the SAME
  page over the SAME options) and each plugin's license storage (`{slug}_gvectors_licenses`), which
  the cron merges into one versions map so expiry reminders cover wpForo AND wpDiscuz addon licenses
  in a single request/email.

---

## Directory Structure

```
admin/pages/news/
├── src/
│   ├── NewsModule.php            # Entry point, service registry, site token utilities
│   ├── Config.php                # Immutable configuration value object (all names/knobs)
│   ├── AdminPage.php             # "News & Emails" settings page (channel × category matrices)
│   └── Services/
│       ├── ApiService.php        # HTTP client — GET /news, GET /at-risk-licenses (opt-in guarded)
│       ├── ConsentService.php    # Opt-in notice + AJAX (enable / not-now / master switch)
│       ├── CronService.php       # Daily sync: news transient, versions map, email dispatch
│       ├── NoticesService.php    # Admin notices rendering + AJAX dismiss (user meta)
│       ├── EmailService.php      # Digest + expiry emails, kses sanitizing, site tokens
│       └── PrefsService.php      # THE preference gate: channel × category × relevance, both scopes
├── admin/assets/
│   ├── css/notices.css
│   └── js/notices.js             # Dismiss + opt-in buttons (works for any {slug}GvNews global)
├── vendor/                       # Composer autoloader (no third-party dependencies)
└── composer.json
```

---

## Wiring (host plugin side)

```php
// wpForo: modules/news/bootstrap.php
new NewsModule( new NewsConfig(
    WPFORO_VERSION,                       // core plugin version
    WPFORO_BASEFOLDER,                    // core plugin slug → prefixes everything
    WPFORO_URL . '/admin/pages/news',     // module URL (assets)
    'wpforo-addons',                      // Addons dashboard page slug ({dashboard_url} token)
    'wpforo_admin_base_menu',             // host plugin's admin menu hook (settings submenu)
    'https://gv.loc'                      // proxy URL override (dev); default store.gvectors.com
) );
```

`modules/news/NewsConfig.php` extends `gVectors\News\Config` (override points: proxy URL, TTLs, max notices).
Loaded from `autoload.php` right after the license module bootstrap.

Deactivation hook helper: `NewsModule::clear_scheduled_events( $config )`.
Uninstall helper: `NewsModule::uninstall( $config )` — wired into `wpforo_uninstall()`; removes all
shared `gvectors_*` options/transient/user-meta/cron, but ONLY when no other installed plugin still
carries this module (detected by `{plugin}/admin/pages/news/src/NewsModule.php` on disk).
i18n: the shared `gvectors` textdomain loads once on `init` from `languages/gvectors-{locale}.mo`;
template: `languages/gvectors.pot` (regenerate after adding strings).
Service access: `NewsModule::get( 'wpforo', 'cron'|'api'|'consent'|'prefs'|'email'|'notices'|'config' )`.
Proxy URL: overridable sitewide via the `GVECTORS_PROXY_URL` constant (both bootstraps honor it).
wordpress.org submission notes + pre-release checklist: `WORDPRESS-ORG.md`.

---

## Proxy endpoints used

| Endpoint | Auth | Purpose |
|---|---|---|
| `GET /news` | site token headers | News items + news digest email wrapper template (content is global, but the route is auth-gated against DDoS/scraping) |
| `GET /at-risk-licenses?versions[slug]=ver` | site token headers | Four selections in one response: expiring licenses (`email`, phases > 0), already-expired winback (`winback_email`, phases < 0), failed payments (`past_due` + `dunning_email`), unused active discounts (`active_offers` + `offer_email`). Renewal discounts are minted and applied entirely proxy-side — emails only announce the percent |

Reminder emails: `send_expiry_reminders` (phase > 0) and `send_winback_reminders` (phase < 0) share
per-admin `license_id:phase` tracking; `send_dunning_notices` dedups per admin on
`dun:{subscription_id}:{Y-m}` (max one email per subscription per month);
`send_offer_reminders` announces UNUSED renewal offers with per-admin keys `offer:{id}` plus one
last-chance nudge `offer:{id}:final` when the offer has ≤ 3 days left (max two emails per offer,
ever). Expiry/winback/dunning are the `expiry_reminder` preference category ("License expiry &
billing reminders"); offer reminders have their OWN category `renewal_offer` ("Renewal discount
offers") so they can be unsubscribed separately on the settings page (per-admin and site-wide).

Unused-offer timing is decided proxy-side (`RenewalOfferService::getActiveOffersForSite`): MANUAL
offers (phase 0, minted from the proxy dashboard's "Renewal Offers" bulk tool) are announced on the
next daily sync; phase-minted offers only once `OFFER_REMINDER_MIN_AGE_DAYS` (default 3) old, since
the phase reminder email already announced them on day 0.

## Purchase confirmation email

The license module fires `gvectors_transaction_licenses_activated( $transaction_id, $licenses, $slug, $purchaser_id )`
after the dashboard polling verifies a completed checkout and saves the license(s).
`EmailService::handle_purchase_activated()` then emails ONLY the purchasing administrator the
transaction id and EVERY license key — so the keys live in their inbox for future activations on
other domains (staging → production moves, reinstalls).

**Purchaser attribution (race-safe):** the pending-transactions list is a site-wide option and the
verification polling can run from ANY admin's dashboard refresh — so the license module records
`user_id` in the pending entry at checkout time (`{txn: {time, user_id}}`) and resolves the REAL
purchaser in `ajax_verify_transaction`, passing it as the hook's 4th argument. The current user is
only a fallback for legacy entries without attribution.

- Dedup: option `gvectors_emailed_transactions` (one email per transaction, ever; last 50 kept)
- Templates: proxy-editable `purchase_confirmation` + `purchase_license_row`, shipped in the `/news`
  payload (`purchase_email`) and CACHED in the news transient — the email sends instantly at purchase
  time with zero proxy round-trips; a complete local fallback exists for cold caches
- Deliberately bypasses the preference matrix AND the consent gate: it is a receipt for the
  recipient's own action and makes no outbound request

## Abandoned checkout recovery email

Dashboard checkouts create the Paddle transaction server-side (that's what enables coupon-less
personal discounts), which makes them ineligible for Paddle's native checkout-recovery — this
module is the only recovery path. Flow: the LICENSE module schedules single events per phase at
checkout creation (`gvectors_abandoned_checkout_check`, args `[txn, minutes, purchaser_id]`;
phase minute offsets come from the `/checkout` response = proxy env `ABANDONED_CHECKOUT_PHASES`,
empty = disabled → nothing scheduled). Each check asks `GET /abandoned-checkout`; still-pending →
the proxy returns the fully built email (discounted phases attach a single-use discount to the
SAME transaction, so the reopened checkout shows the reduced price) and the license module fires
`gvectors_abandoned_checkout_email( $txn_id, $email, $purchaser_id, $minutes, $slug )` — handled
here by `EmailService::handle_abandoned_checkout()`: purchaser-only (no current-user fallback in
cron context), dedup option `gvectors_abandoned_emailed` (`txn:minutes` keys, last 100), gated by
its own email-only category `abandoned_checkout` ("Unfinished purchase reminders" — per-admin +
site-wide checkboxes; `allows()` also covers the master unsubscribe). Purchase confirmations
remain the ONLY non-unsubscribable email (receipt carrying the license keys).

Site identity: same `X-gVectors-Site` / `X-gVectors-Token` HMAC headers as the license module.
`NewsModule::get_site_token()` delegates to `LicenseModule::get_site_token()` when present, otherwise computes the identical HMAC — both modules always present the same TOFU identity.

---

## Storage (all prefixed `gvectors_` — SHARED across all gVectors plugins)

| Key | Type | Purpose |
|---|---|---|
| `gvectors_addons_service_enabled` | option | Master opt-in, default false. Gates every HTTP request. Opt-out anytime from the settings page |
| `gvectors_site_channel_prefs` | option | Site-wide matrix `{notices{cat=>bool}, emails{cat=>bool}}` — missing keys = enabled |
| `gvectors_optin_notice_dismissed` | option | "Not now" on the consent notice (also set when enabling from the settings page) |
| `gvectors_news` | transient (1 day) | Cached news payload `{news, email_wrapper, fetched_at}` |
| `gvectors_dismissed_news` | user meta | Array of dismissed news ids — PER-ADMIN, PER-ITEM (dismissing one item never hides others, never affects other admins) |
| `gvectors_emailed_news` | user meta | Array of news ids already emailed to this admin |
| `gvectors_emailed_license_phases` | user meta | Array of `license_id:phase` keys (each phase fires once per admin) |
| `gvectors_news_prefs` | user meta | Per-admin matrix `{unsubscribed, notices{cat=>bool}, emails{cat=>bool}}` — missing meta = all enabled |
| `gvectors_daily_news_sync` | cron hook | Daily; self-healing scheduler on `init` (scheduled iff consent given). ONE event regardless of how many plugins load the module |

## Preference matrix (`PrefsService`)

Channels: `notices` (dashboard) and `emails`. Categories per channel:
- `expiry_reminder` — emails only (transactional: expiry warnings, winback, dunning)
- `renewal_offer` — emails only (unused personal renewal-discount reminders — its own
  category so admins can unsubscribe from offer nudges without losing expiry warnings)
- `abandoned_checkout` — emails only (unfinished-purchase reminders, purchaser-only)
- `recommendations` — emails only (cross-sell: proxy-computed addon suggestions shipped in
  the /news payload; rendered at the bottom of the digest — or as their own email on
  no-news days — each slug at most once ever per admin, meta `gvectors_emailed_recommendations`,
  installed addons skipped)
- `new_addon`, `new_feature`, `new_version`, `discount`, `announcement` — news content types
  (map 1:1 from item `type`; `new_version` = version releases: changelogs, scheduled release
  dates, required pre/post-update actions — renders as `notice-warning`; `discount` = sales,
  coupon codes and limited-time offers — renders as `notice-success`)
- `addons_installed` / `addons_not_installed` — relevance filter for addon-targeted news
  (items with a `plugin_slug`): whether the addon is installed on this site (matched by
  plugin directory via `get_plugins()`). Lets admins skip news about addons they don't have.

Delivery rule (`news_item_allowed`): an item reaches an admin through a channel only when the
SITE matrix AND that admin's OWN matrix allow both its content category and (if addon-targeted)
its relevance category. Per-admin master `unsubscribed` kills the emails channel only — dashboard
notices are unaffected by it. Everything defaults to enabled; only explicit false disables.
All senders/renderers go through `PrefsService` — never check categories anywhere else.

Per-plugin (NOT shared): `{slug}_gvectors_licenses` — the license module's own storage, read per
context to build the merged versions map.

## "News & Emails" settings page (`AdminPage`)

EVERY registered gVectors plugin gets its own submenu entry (via its `get_dashboard_menu_hook()`, capability `activate_plugins`, page slug `{slug}-news-settings`), but all entries render the SAME page over the SAME shared options; after saving, the user is redirected back to whichever plugin's menu they came from (`gv_return`). Forms post to `admin-post.php` (`admin_post_gvectors_save_my_email_prefs` / `admin_post_gvectors_save_site_email_settings`, nonce-protected). Two mirrored sections:

1. **My Preferences (per-admin)** — master email unsubscribe + the full channel × category
   matrix for the logged-in administrator's own account (checkbox names `gv_my_{channel}_{category}`).
   Digest items filtered per admin are NOT marked emailed, so re-enabling resumes delivery.
2. **Site-Wide Settings** — the master service switch (full opt-out after opt-in: clears the cron
   immediately, zero outbound requests; enabling counts as consent + immediate first sync) plus the
   same matrix applied site-wide for everyone (`gv_site_{channel}_{category}`). A category disabled
   here wins over any personal preference.

Email footers link to this page ("unsubscribe or choose which emails you receive").

AJAX actions (`wp_ajax_{slug}_gvectors_*`, nonce `{slug}_gvectors_news_nonce`, capability `activate_plugins`):
`news_optin`, `news_settings`, `dismiss_news`.

## Notice page gating

ALL admin notices (opt-in + news) render only on the pages allowed by
`NewsModule::is_notice_page( $slug )` — same rule as the license module's
`AddonsService::is_notice_page()` (keep the two in sync):

- Dashboard Home (`dashboard`), Updates (`update-core`), Installed Plugins (`plugins`), Add Plugins (`plugin-install`)
- Any admin page whose screen id contains the host plugin slug (the plugin's own pages)

`NoticesService::enqueue_assets()` applies the same gate, so the module's JS/CSS
never load on unrelated admin pages.

---

## Email rules

- **Digest design**: every news item renders inside its category card (`news_item_card` proxy
  template, shipped in the `/news` response's `email_wrapper.item_card`; local fallback exists) —
  colored badge header with the category label + an `i / n` counter when the email holds several
  items, bold title, then the item's rich content. Type labels/badge colors come enriched on each
  item from the proxy (`NewsService::TYPE_META`); invalid colors are rejected and replaced locally.
- **Dynamic subject**: a digest with exactly ONE item uses that item's title as the email subject;
  multi-item digests use the wrapper subject with `{news_count}`.
- **Standalone items**: news flagged `standalone` on the proxy dashboard bypass the digest — each
  goes out as its own email with its title as the subject. Use for time-critical items only.
- Recipients: `get_users(['role__in' => ['administrator']])` — role query, NOT capability query.
- Only 5 tokens are replaced locally (escaped): `{admin_name} {site_name} {site_url} {dashboard_url} {current_year}`; any leftover `{token}` is stripped.
- News `html_content` passes `wp_kses` with an email-safe allowlist before embedding (defense in depth).
- Compliance footer appended to every email: why received + how to disable.
- The two email types (news digest, expiry reminder) are never combined.
- IDs are recorded in user meta ONLY when `wp_mail()` returns true.
