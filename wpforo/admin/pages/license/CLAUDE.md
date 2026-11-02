# gVectors License Module – CLAUDE.md

**Location:** `admin/pages/license/` (git submodule)
**Namespace:** `gVectors\License` | **Composer package:** `gvectors/license`
**PHP:** ≥ 7.2 | Autoloaded via PSR-4 from `src/`

---

## Purpose

Self-contained library that handles the complete addon licensing lifecycle for wpForo:
- Addon store page in WP admin (browse, purchase, install, activate)
- License activation / deactivation / validation against the gVectors proxy server
- Subscription management (cancel, resume, update payment)
- Free trial support
- Signed URL addon download + signature integrity verification
- WordPress auto-update integration for licensed addons
- Legacy license system compatibility shim (`GVT_API_Manager`)

All communication goes through the **gVectors proxy server** (`store.gvectors.com`). The module **never calls Paddle directly**.

---

## Directory Structure

```
admin/pages/license/
├── src/
│   ├── LicenseModule.php         # Entry point & site token/env detection utilities
│   ├── Config.php                # Configuration value object (immutable)
│   ├── AdminPage.php             # WP admin menu + asset enqueuing
│   └── Services/
│       ├── ActionsService.php    # All wp_ajax_* handlers (AJAX layer)
│       ├── AddonsService.php     # Addon install/activate/update/signature integrity
│       ├── LicenseService.php    # License storage, status, validation, revalidation
│       └── ApiService.php        # HTTP client — proxy server communication only
├── admin/
│   ├── store-page.php            # Store page template (rendered in WP admin)
│   └── assets/
│       ├── css/admin.css         # Admin page styles
│       └── js/admin.js           # Admin page JS (AJAX, checkout flow, UI)
├── includes/
│   └── compat-legacy-license-manager.php  # Dummy GVT_API_Manager shim
├── vendor/                       # Composer autoloader (no third-party dependencies)
└── composer.json
```

---

## Class Reference

### `LicenseModule` (entry point)

```php
new LicenseModule(new LicenseConfig(...));
```

Instantiates the full service tree and `AdminPage`. Also provides static utility methods used elsewhere.

| Static Method | Description |
|---|---|
| `get_host_slugs(): array` | Core slugs of every host plugin that instantiated the module on this site (`wpforo`, `wpdiscuz`, …) — never treated as store addons |
| `get_site_token(): string` | `HMAC-SHA256(domain, WP_salts)` – unique per installation, sent with API requests |
| `get_site_domain(): string` | Normalized domain (no protocol, no www, no trailing slash) |
| `normalize_domain(string $url): string` | Strips protocol/www/path – matches server-side normalization |
| `is_development_site(): bool` | Returns `true` for localhost, private IPs, dev TLDs, staging subdomains, non-standard ports, WP env type `local`/`development`/`staging`. Result is statically cached per request. |

**`is_development_site()` detects:**
- Loopback: `localhost`, `127.0.0.1`, `::1`, `0.0.0.0`
- Raw IP addresses
- Non-standard ports (not 80/443)
- Dev/staging TLDs: `.local`, `.loc`, `.test`, `.ddev.site`, `.lndo.site`, `.ngrok.io`, `.kinsta.cloud`, `.wpengine.com`, etc. (see source for full list)
- Dev subdomains: `dev.*`, `staging.*`, `test.*`, `qa.*`, `uat.*`, etc.
- WP environment type (`wp_get_environment_type()`)

**Site token generation:**
Combines `AUTH_SALT + SECURE_AUTH_SALT + LOGGED_IN_SALT + NONCE_SALT` as HMAC key.
Fallback (no salts): `SHA256(DB_NAME:DB_USER:domain)`.

---

### `Config`

Immutable value object. All configuration injected at construction time.

| Method | Returns | Description |
|---|---|---|
| `get_core_plugin_version()` | string | Plugin version (e.g. `'3.1.1'`) |
| `get_core_plugin_slug()` | string | `'wpforo'` |
| `get_license_module_url()` | string | URL to this module directory |
| `get_dashboard_addons_page_slug()` | string | WP admin menu page slug for addons |
| `get_dashboard_menu_hook()` | string | `do_action` hook name for menu registration |
| `get_proxy_server_url()` | string | `'https://store.gvectors.com'` (or override) |
| `get_manifest_public_key()` | string | Ed25519 public key for addon signature verification |
| `get_tamper_grace_days()` | int | `5` – days before tampered addon is blocked |
| `get_legacy_check_period()` | int | `DAY_IN_SECONDS` |
| `get_license_revalidation_period()` | int | `DAY_IN_SECONDS` |
| `get_license_batch_cache_ttl()` | int | `10 * MINUTE_IN_SECONDS` |

---

### `AdminPage`

Hooks registered:

| Hook | Action |
|---|---|
| `admin_enqueue_scripts` | Enqueues `gvlicense-admin` CSS + JS on wpForo admin pages only (checks hook for plugin slug) |
| `{dashboard_menu_hook}` | Registers "Addons" submenu page under wpForo menu (capability: `activate_plugins`) |

**JS localization (`gvectorsLicense` object):**
- `ajaxUrl`, `nonce` – standard WP AJAX
- `checkout_loading_url`, `checkout_url` – Paddle proxy checkout endpoints
- `siteDomain` – normalized site domain
- `i18n` – full set of translated UI strings

**Store page sections** (`store-page.php` markup, filled by `admin.js` `groupProducts()` from each product's `custom_data.parent_slug`):
- **Addons** (`#gvlicense-section-addons`) – non-bundle, `parent_slug` === core slug
- **Addon Bundles** (`#gvlicense-section-bundles`) – every bundle of this host or shared (empty `parent_slug`)
- **Related PRO Plugins** (`#gvlicense-section-pro`) – non-bundle, empty/missing `parent_slug` (shared across hosts)
- Products of another host are skipped. Empty sections and their nav links are hidden; the quick nav (`#gvlicense-section-nav`) shows only with 2+ sections — a sticky bar above the sections at every width. Section hashes deep-link on first load.

**Card links (Paddle product custom attributes):** `product_url` → **Learn More** (marketing page with screenshots/explanations), `demo_url` → **Live Demo** (a site where the addon can be tried). The proxy passes the product's whole `custom_data` through untouched; `ajax_get_products()` copies both to top-level `product_url` / `demo_url` via `product_link()` — trimmed, http/https only (`esc_url_raw`), anything else (missing, empty, `javascript:`, non-string) becomes `''`. The card template shows a `.gvlicense-product-links` row right above the price (below the bundle "Includes" list) with a button per non-empty link, in every card state (licensed, trial, not licensed); no row when both are empty. Plain `<a target="_blank" rel="noopener noreferrer">`, no JS. New values appear after the 6-hour `{slug}_gvectors_products` transient expires or the store page **Refresh** button.

---

### `ActionsService` (AJAX layer)

All handlers require: `current_user_can('administrator')` + `check_ajax_referer('gvectors_nonce', 'nonce')`.

**Product endpoints:**

| AJAX action | Method | Description |
|---|---|---|
| `gvectors_get_products` | `ajax_get_products()` | Fetch product list from proxy; enriches with local license status, addon install status, plan info, bundle info, card links (`product_url` / `demo_url`) |
| `gvectors_get_prices` | `ajax_get_prices()` | Fetch prices for a specific product ID |
| `gvectors_check_overlap` | `ajax_check_overlap()` | Check for overlapping subscriptions before checkout |

**Checkout endpoints:**

| AJAX action | Method | Description |
|---|---|---|
| `gvectors_create_checkout` | `ajax_create_checkout()` | Create a Paddle checkout session via proxy |
| `gvectors_verify_transaction` | `ajax_verify_transaction()` | Poll transaction status; saves license locally on completion (single or bundle) |

**License endpoints:**

| AJAX action | Method | Description |
|---|---|---|
| `gvectors_activate_license` | `ajax_activate_license()` | Activate by license key |
| `gvectors_activate_by_transaction` | `ajax_activate_by_transaction()` | Activate by Paddle transaction ID |
| `gvectors_unified_activate` | `ajax_unified_activate()` | Smart activate: key, txn ID, or empty (auto domain lookup) |
| `gvectors_activate_by_domain` | `ajax_activate_by_domain()` | Auto-find and activate all licenses for this domain |
| `gvectors_deactivate_license` | `ajax_deactivate_license()` | Deactivate license for a product |
| `gvectors_validate_license` | `ajax_validate_license()` | Batch-validate all licenses; uses 10-min cache |
| `gvectors_get_licenses` | `ajax_get_licenses()` | Return all locally stored licenses |

**Addon endpoints:**

| AJAX action | Method | Description |
|---|---|---|
| `gvectors_install_addon` | `ajax_install_addon()` | Download + install addon via WP upgrader |
| `gvectors_activate_addon` | `ajax_activate_addon()` | Activate an installed addon plugin |
| `gvectors_deactivate_addon` | `ajax_deactivate_addon()` | Deactivate an addon plugin |
| `gvectors_install_activate_addon` | `ajax_install_activate_addon()` | Install + activate in one step; an addon already on disk is only activated |
| `gvectors_download_addon` | `ajax_download_addon()` | Signed one-time ZIP URL for a manual (FTP) install — the browser downloads it straight from the proxy |
| `gvectors_install_verified_copy` | `ajax_install_verified_copy()` | **Reinstall Addon**: replace an installed licensed addon with a fresh copy signed for this site (`manual_install` on sites that can't write plugins) |

Install errors carry `manual_install: true` in the JSON error payload only when WordPress can't write the addon: no `install_plugins` capability / blocked filesystem (`can_install_addons()` false), an upgrader filesystem error (`AddonsService::is_filesystem_error()`: `fs_*`, `mkdir_failed*`, `copy_failed*`, `files_not_writable`, ...), or `false` with no error (filesystem credentials needed). Other failures (download, archive, ...) are a plain toast. With `manual_install`, `admin.js` opens the manual-install guide instead of a toast.

**Subscription endpoints:**

| AJAX action | Method | Description |
|---|---|---|
| `gvectors_cancel_subscription` | `ajax_cancel_subscription()` | Cancel at end of billing period |
| `gvectors_resume_subscription` | `ajax_resume_subscription()` | Resume a paused subscription |
| `gvectors_update_payment` | `ajax_update_payment()` | Get payment update URL |
| `gvectors_get_portal_url` | `ajax_get_portal_url()` | Get subscription management portal URL |

**Other endpoints:**

| AJAX action | Description |
|---|---|
| `gvectors_start_trial` | Start free trial for a product; saves trial license locally |
| `gvectors_get_account` | Fetch customer account info from proxy |
| `gvectors_save_pending_transaction` | Track transaction IDs during checkout (auto-expire after 24h) |
| `gvectors_get_pending_transactions` | Return pending transaction IDs (pruning old ones) |
| `gvectors_clear_pending_transaction` | Remove a resolved transaction from pending list |
| `gvectors_get_timeline` | Fetch license event timeline from proxy |
| `gvectors_clear_cache` | Clear all API transient caches |

**WordPress option:** `{slug}_gvectors_pending_transactions` – stores pending transaction entries `{time, user_id}` (purchaser recorded at checkout time — the verification polling is site-wide, so attribution must not depend on whose session polls).

**Abandoned checkout recovery (no AJAX):** `ajax_create_checkout()` schedules one
`gvectors_abandoned_checkout_check` single event per phase from the `/checkout` response's
`abandoned_phases` (proxy env `ABANDONED_CHECKOUT_PHASES`; empty = feature off, nothing
scheduled), args `[transaction_id, minutes, purchaser_user_id]`, args-exact dedup. The handler
`handle_abandoned_check()` (shared unprefixed hook + static per-request guard for multi-plugin
installs) calls `ApiService::check_abandoned()` → `GET /abandoned-checkout`; when the proxy says
the transaction is still pending it fires `gvectors_abandoned_checkout_email` with the
proxy-built email — the news module emails the purchaser (dedup + master-unsubscribe there).

---

### `AddonsService`

Handles addon lifecycle, update management, and security.

**WordPress hooks registered:**

| Hook | Description |
|---|---|
| `pre_set_site_transient_update_plugins` (filter) | Inject addon updates for licensed products |
| `plugins_api` (filter, priority 20) | Provide plugin info for WP update UI |
| `admin_init` | Refresh update transient if legacy addons exist; register unlicensed update row hooks; handle dev notice dismissals; track tamper notice views |
| `{slug}_gvectors_addon_signature_check` (cron, twicedaily) | Verify cryptographic signatures of all installed addons |
| `{slug}_gvectors_addon_license_check` (cron, daily) | Revalidate all licenses |
| `admin_notices` | Four notice types: tampered addon, expired license, legacy license, dev environment |
| `activate_plugin` | Validate addon license before allowing activation |
| `upgrader_pre_download` (filter) | Block download of tampered addon updates |

**Key methods:**

| Method | Description |
|---|---|
| `get_status($plugin_slug)` | Returns addon install status: `not_installed`, `installed`, `active` |
| `install($product_id)` | Downloads signed zip via proxy URL, installs via `Plugin_Upgrader`. Refuses with `manual_install` when `can_install_addons()` is false |
| `activate($plugin_file)` | Activates installed plugin |
| `deactivate_addon($plugin_file)` | Deactivates plugin |
| `install_and_activate($product_id)` | Combined install + activate. If the licensed addon is already on disk (inactive, or uploaded via FTP) it only verifies signatures (clean JSON error instead of the activation gate's `wp_die()`) and activates — never re-runs the upgrader |
| `can_install_addons()` | Whether an AJAX install can work: `install_plugins` capability AND `site_can_install_plugins()` |
| `static site_can_install_plugins()` | Site-level check without user context (cron-safe): `wp_is_file_mod_allowed()` (false under `DISALLOW_FILE_MODS` / `file_mod_allowed` filter) AND (direct filesystem for `WP_CONTENT_DIR` + writable `WP_PLUGIN_DIR`, or predefined FTP/SSH constants). Static per request |
| `queue_blocked_updates($updates)` (private) | End of `check_for_updates()`: the entitled updates (licensed / legacy-licensed, with a package) found while `site_can_install_plugins()` is false are merged into the shared option `gvectors_blocked_updates_queue`, and one single event `gvectors_blocked_updates_notify` is scheduled (+1 min). Never sends during the update check |
| `static notify_blocked_updates()` | Cron handler (registered as a static callback, so it runs once even with several host instances): reads + deletes the queue and, unless the site can install plugins again, fires `gvectors_blocked_updates( $updates )` (`slug => [name, current_version, new_version]`). The news module emails the admins |
| `get_download_link($product_id)` | Manual install path: needs only an active license (`is_active()`), not `install_plugins`; allowed for tampered addons (a clean copy fixes them). Returns `download_url`, `plugin_slug`, `file_name` |
| `request_download($product_id)` (private) | Shared by `install()` / `get_download_link()`: proxy `POST /addon/download-url` + appended `site_domain` |
| `check_for_updates($transient)` | Compares installed vs latest version; injects update data |
| `verify_all_addon_signatures()` | Checks Ed25519 signature of each addon zip; sets tampered flag |
| `install_verified_copy($product_id)` | **Reinstall Addon**: active license (`is_active()`) + installed addon → `request_download()` → `Plugin_Upgrader::install( $url, [ 'overwrite_package' => true ] )` (WordPress's "Replace current with uploaded": works without a pending update, same version too; the folder is replaced, the addon keeps its active state, settings/data stay in the DB). Allowed for flagged addons — a clean copy is the fix. The result must verify `valid` (clears the tamper flag, re-activates an addon the check had deactivated) and drops the legacy cache. `manual_install` when `can_install_addons()` is false |
| `needs_verified_copy($slug)` | Side-effect free: installed copy without `.addon-signatures.json` or flagged in `{slug}_gvectors_tampered_addons`; false on dev sites |
| `reverify_licensed_addons()` | Called by the four license-activation AJAX handlers: re-runs `verify_addon_signatures()` on this host's licensed addons that are flagged, so linking an old-store key clears the notice (and re-activates the addon) at once instead of on the next cron |
| `check_all_license_validity()` | Revalidates all licenses; deactivates/removes for terminal statuses |
| `prune_missing_addons()` | Once per request: for every slug in the expired/tampered/tamper-seen/legacy options whose plugin is not physically installed (`is_addon_present()` = folder + plugin file via `get_plugins()`), calls `forget_addon()` to drop those rows + dismissal transients. Runs at the top of every addon notice, both crons and `track_tamper_notice_view()`. License records in `{slug}_gvectors_licenses` are intentionally kept (store page needs them). |

**Manual install (sites where WordPress can't write plugins):**

- **Download ZIP is offered only when WordPress couldn't install the addon for a permission reason** — never for installed/active addons or on sites where the normal install works. `ajax_get_products()` sets `can_install` on every product (probes the filesystem only when a licensed addon is not installed) and `offer_download` = `!can_install` AND licensed/trial, non-bundle, `addon_status === 'not_installed'`. Card template: `can_install === false` → primary **Download ZIP** + hint instead of **Install & Activate**. The Manage modal and licenses table show **Download ZIP** only when `offer_download`. When an install fails with `manual_install`, `admin.js` sets `offer_download` on that product in memory (until reload) and re-renders, so the card gets a download icon next to **Install & Activate** and the table a **Download ZIP** button. An installed addon with bad signatures: blocked sites get the manual re-upload guide; sites that can install are told to delete it and use **Install & Activate**. When the site blocks installs (`can_install === false`, or an install error), the guide adds an updates notice (new releases can't arrive through the standard WordPress update process) and a green "Recommended — get updates immediately" box: restore the default permissions (remove `DISALLOW_FILE_MODS`, writable `wp-content/plugins/`), with a tip that `DISALLOW_FILE_EDIT` doesn't block updates. Texts never suggest re-downloading for updates.
- `admin.js` `downloadAddon()` → `gvectors_download_addon` → `window.location.assign(url)` (proxy responds `Content-Disposition: attachment`, the page stays) → `showManualInstall()` renders the steps into the shared `#gvlicense-manage-modal`: download the ZIP, then upload it in WordPress (Plugins → Add New Plugin → Upload Plugin, linked via `config.pluginUploadUrl`); only if that doesn't work (Upload Plugin missing or failing), the FTP/SFTP steps (unzip, upload the folder with the hidden `.addon-signatures.json` in binary mode, Activate). The store's support email, landing page and dashboard customer message use the same order.
- The ZIP is signed for this domain; the upload must include the hidden `.addon-signatures.json` and use binary FTP mode, otherwise `verify_addon_signatures()` flags the addon (`no_manifest` / `tampered`).
- Support can mint a longer-lived link without WP: proxy dashboard **Admin → Download Links** or `scripts/make-download-link.php`.

**Reinstall Addon (reinstall a clean signed copy; the button was called "Get Verified Copy" until 2026-09-28 — internal names `*verified_copy*` / `verifiedCopy*` / `.gvlicense-verified-copy-btn` were kept on purpose, so mixed wpForo/wpDiscuz module copies keep working):** `ajax_get_products()` sets `offer_verified_copy` on every installed, licensed/trial, non-bundle product and `needs_verified_copy` when `needs_verified_copy()` says the copy isn't verified. The card, the licenses table and the Manage modal show the **Reinstall Addon** button (`dashicons-update`) for `offer_verified_copy` — primary, plus a hint on the card, when `needs_verified_copy` (old-store copies, modified files). `admin.js` `installVerifiedCopy()` asks for confirmation (`i18n.verifiedCopyConfirm`), calls `gvectors_install_verified_copy`, reloads products + licenses; `manual_install` opens the manual guide. Expired licenses get no button (the store's `/addon/download-url` refuses them anyway). Texts that used to say "delete the addon and reinstall" (`install_and_activate()`, tampered update/install errors, the tamper notice of a licensed addon — titled "Addon Files Not Verified") point to this button.

**Blocked-update emails:** WordPress never *attempts* these updates. `DISALLOW_FILE_MODS` disables the auto-updater outright (`WP_Automatic_Updater::is_disabled()`), and with a read-only plugins folder it silently skips plugins. So the signal is WordPress's own update check (twice-daily `wp_update_plugins`, or the Updates/Plugins screens) running our `check_for_updates()` filter while `site_can_install_plugins()` is false. See `queue_blocked_updates()` / `notify_blocked_updates()`. The email itself (recipients, preferences, dedup per addon version, templates) lives in the news module (`EmailService::handle_blocked_updates`, category `blocked_update`). wpDiscuz doesn't ship the news module, so on wpDiscuz-only sites nothing is sent.

**Which plugins are "our addons" (store list only — never by name):**

- `is_known_addon($slug)` = slug is in the store server's `GET /addon/list` (`get_store_addons()`: slug => `parent_slugs`). Plugin/folder names are never inspected — `forums-censure-pro` is handled exactly like `wpforo-polls`, and a third-party `wpforo-something` plugin is ignored. Used by updates, the activation gate, signature/tamper cron, unlicensed scan and the "license not active" Plugins-page row.
- Every successful list fetch is persisted to `{slug}_gvectors_store_addons` (slug => parent host slugs); while the store is unreachable that last-known list is used. No list ever fetched → nothing is treated as an addon (fail open). The Plugins-page row (`admin_init`) reads only the persisted list — no HTTP on page load.
- Host plugins (`LicenseModule::get_host_slugs()` = every registered core slug: `wpforo`, `wpdiscuz`, …) are never addons, even if the store list contained them — their core updates always come from wordpress.org untouched.
- Addon ownership comes from the Paddle product custom attribute `parent_slug`, exposed by the proxy as `parent_slugs` per addon. Empty/missing `parent_slug` = the product belongs to **every** host (wpForo and wpDiscuz); an addon in any such shared product is shared. `addon_belongs_to_host($slug)` = `parent_slugs` empty or contains this core slug.
- Several hosts on one site share one class definition but keep separate license options. `manages_addon($slug)` decides which instance handles an addon: this host holds a license → yes; another host holds one (`is_licensed_by_other_host()`) → no; otherwise `addon_belongs_to_host()`. Non-managing instances skip the addon in the legacy/unlicensed update loops, the activation gate, the unlicensed scan and signature checks (clearing their own stale tamper/legacy rows), so a host never wipes another host's licensed update package and notices link to the store that actually sells the addon. The licensed update loop (own license) is not scoped. `claim_notice()` renders each addon notice / Plugins-page row once per request across hosts (shared addons).

**Old-store (legacy) customers are never treated as pirates:**

Addons installed from the old WooCommerce store have no `.addon-signatures.json`. `verify_addon_signatures()` handles such a copy in this order: nulled patterns → `patched`; this host holds a license for it (bought, or an old key linked on the Addons page — any status) → `legacy_valid`; the proxy's legacy check (`check_legacy_license()`) confirms an old-store license → `legacy_valid` (expired ones too; they just get no updates); store unreachable with nothing known → `legacy_valid` (fail open, never flag on missing data); otherwise `no_manifest`.
- The proxy recognizes an old key by the domain it was activated on, by its **owner** having activated any other old key on this domain (many buyers never activated every key), or by a migrated license row that lists this domain — see the proxy's `LegacyLicenseService`.
- Legacy cache (`{slug}_gvectors_legacy_addon_licenses`): API failures keep the last known answer or store `unknown => true` (retried after an hour; `scan_unlicensed_addons()` and the activation gate never flag/block on it). Confirmed legitimacy is sticky (`merge_legacy_result()`): a later negative answer never revokes it; only deleting the addon (`forget_addon()`) drops it.
- `clear_tamper_flag( $slug, true )` — when an addon verifies again, an addon the tamper check had deactivated (`deactivated_at`) is re-activated (`reactivate_addon()`: silent `activate_plugin()` + its own `activate_{file}` hook; skipped inside `activate_plugin`, where nesting would duplicate the entry).
- `no_manifest` (no license found anywhere) still gets the 5-day grace + deactivation, but the notice is a warning titled "License Not Found for This Site" that tells old-store buyers to enter their old key on the Addons page; the red "Unauthorized Addon Detected" alert is only for real tampering (`tampered`, `domain_mismatch`, `no_signatures`, `patched`).

**WordPress options used:**

| Option | Purpose |
|---|---|
| `{slug}_gvectors_tampered_addons` | List of addons with invalid signatures |
| `{slug}_gvectors_expired_license_notices` | Tracks which expired license notices have been shown |
| `{slug}_gvectors_tamper_notice_seen` | Whether admin has seen the tamper warning |
| `{slug}_gvectors_legacy_addon_licenses` | Legacy license data for migration |
| `{slug}_gvectors_legacy_license_notices` | Legacy notice state |
| `{slug}_gvectors_store_addons` | Last successfully fetched store addon list, slug => parent host slugs (not autoloaded) — addon identity/ownership fallback during store outages |

**Transients used:**

| Transient | TTL | Purpose |
|---|---|---|
| `{slug}_gvectors_all_addons` | varies | Cached addon list from proxy |
| `{slug}_gvectors_dev_env_notice_dismissed` | varies | Dev environment notice dismissed |
| `{slug}_gvectors_dev_licenses_notice_dismissed` | varies | Dev licenses notice dismissed |

---

### `LicenseService`

Local license storage and validation logic.

**Storage:** All licenses stored in WP option `{slug}_gvectors_licenses` as array keyed by `product_id`.

**Terminal statuses** (addon deactivated + deleted): `['invalid']`
**Refund reasons** (treated as removal): `['refunded']`
**Legacy licenses** (`plan_name` `Legacy License`, migrated old-store keys) are never terminal: `invalid` / `not_found` keep the license as `expired` (`keep_legacy_license()`) and the addon is never deactivated or deleted — old-store owners use their addons for life, updates need an active license.

**Key methods:**

| Method | Description |
|---|---|
| `get(string $product_id): array` | Get stored license for product |
| `get_all(): array` | Get all stored licenses |
| `is_active(string $product_id): bool` | True if status is `active` or `trial` AND not expired |
| `is_trial(string $product_id): bool` | True if status is `trial` |
| `save(string $product_id, array $license)` | Store/update license locally |
| `validate_with_cache(string $product_id = '')` | Batch-validate all licenses; 10-min cache via transient `{slug}_gvectors_batch_validation` |
| `get_status_label(string $product_id)` | Human-readable status string |
| `activate(string $license_key, string $product_id)` | Activate via key → proxy |
| `activate_by_transaction(string $txn_id, string $product_id)` | Activate via transaction ID → proxy |
| `activate_unified(string $key, string $product_id)` | Smart activate (key, txn ID, or domain lookup) |
| `activate_by_domain()` | Auto-find licenses by site domain |
| `deactivate(string $product_id)` | Deactivate via proxy, remove local |
| `maybe_revalidate_all()` | Daily cron: revalidate all; handle expired/refunded/invalid |

Licenses must be keyed by the real Paddle product_id (`pro_…`) — the store grid decides Buy vs Install/Activate via `is_active( $product['id'] )`. On every (batch) validation, `sync_product_key()` moves a local entry to the server-reported `data.product_id` when it differs (repairs migrated legacy licenses that were stored under their plugin slug).

**Cron events:**
- `gvectors_revalidate` (daily) → `maybe_revalidate_all()`

---

### `ApiService`

HTTP client. All requests go to the proxy server. Never calls Paddle directly.

**Request authentication:** Sends `site_token` (HMAC-SHA256) and `site_domain` with every request.

**Transients (caching):**

| Transient | TTL | Data |
|---|---|---|
| `{slug}_gvectors_products` | 6 hours | Product list from proxy |
| `{slug}_gvectors_all_addons` | varies | All addon metadata |
| `{slug}_gvectors_prices_{id}` | varies | Prices for a specific product |

**Key methods:**

| Method | Description |
|---|---|
| `get_products()` | Fetch all products/addons (cached 6h) |
| `get_product(string $product_id)` | Find single product from cached list |
| `get_prices(string $product_id)` | Fetch prices for product |
| `check_overlap(string $price_id, string $product_id)` | Check subscription overlap |
| `create_checkout(...)` | Create Paddle checkout session via proxy |
| `verify_transaction(string $txn_id)` | Poll transaction completion status |
| `activate_license(string $key, string $product_id)` | Activate via proxy |
| `deactivate_license(string $product_id)` | Deactivate via proxy |
| `validate_batch(array $product_ids)` | Validate multiple licenses in one call |
| `cancel_subscription(string $sub_id)` | Cancel via proxy |
| `resume_subscription(string $sub_id)` | Resume via proxy |
| `update_subscription_payment(string $sub_id)` | Get payment update URL |
| `get_subscription_portal_url(string $sub_id)` | Get management portal URL |
| `start_trial(string $product_id)` | Start free trial |
| `get_account()` | Fetch customer account info |
| `get_license_timeline(string $key)` | Fetch license history |
| `clear_cache()` | Delete all API transients |

---

## `includes/compat-legacy-license-manager.php`

Loaded via Composer `files` autoload (global namespace, before plugin namespace).

**Strategy:**
1. If `GVT_API_Manager` class doesn't exist yet → define a no-op dummy class to prevent old addon copies from registering legacy hooks
2. If `GVT_API_Manager` already loaded (addon before core) → remove all its registered WP hooks

This prevents old addons from making API calls to the defunct `gvectors.com/gvt-api.php` endpoint.

---

## wpForo Integration

The module is instantiated from `modules/license/bootstrap.php`:

```php
// modules/license/LicenseConfig.php extends gVectors\License\Config
new LicenseModule(
    new LicenseConfig(
        WPFORO_VERSION,
        WPFORO_BASEFOLDER,                               // 'wpforo'
        WPFORO_URL . '/admin/pages/license',
        'wpforo-addons',                                 // addons page slug
        'wpforo_admin_base_menu',                        // menu hook
        'https://gv.loc'                                 // proxy URL override (dev)
    )
);
```

The static `LicenseModule::$actionsService` is publicly accessible for external use.

---

## Security Notes

- All AJAX handlers verify `administrator` capability + `gvectors_nonce` nonce
- Addon zip signatures verified via Ed25519 (public key in `Config::get_manifest_public_key()`)
- Site tokens use WP salts as HMAC key — unique per installation, not guessable
- Dev/local/staging environments are detected and exempt from certain checks
- Terminal license statuses (`invalid`, `refunded`) trigger addon deactivation + deletion
- `upgrader_pre_download` filter blocks WP from downloading updates for tampered addons
