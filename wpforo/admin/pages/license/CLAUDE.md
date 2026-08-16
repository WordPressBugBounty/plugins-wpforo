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

---

### `ActionsService` (AJAX layer)

All handlers require: `current_user_can('administrator')` + `check_ajax_referer('gvectors_nonce', 'nonce')`.

**Product endpoints:**

| AJAX action | Method | Description |
|---|---|---|
| `gvectors_get_products` | `ajax_get_products()` | Fetch product list from proxy; enriches with local license status, addon install status, plan info, bundle info |
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
| `gvectors_install_activate_addon` | `ajax_install_activate_addon()` | Install + activate in one step |

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
| `install($product_id)` | Downloads signed zip via proxy URL, installs via `Plugin_Upgrader` |
| `activate($plugin_file)` | Activates installed plugin |
| `deactivate_addon($plugin_file)` | Deactivates plugin |
| `install_and_activate($product_id)` | Combined install + activate |
| `check_for_updates($transient)` | Compares installed vs latest version; injects update data |
| `verify_all_addon_signatures()` | Checks Ed25519 signature of each addon zip; sets tampered flag |
| `check_all_license_validity()` | Revalidates all licenses; deactivates/removes for terminal statuses |

**WordPress options used:**

| Option | Purpose |
|---|---|
| `{slug}_gvectors_tampered_addons` | List of addons with invalid signatures |
| `{slug}_gvectors_expired_license_notices` | Tracks which expired license notices have been shown |
| `{slug}_gvectors_tamper_notice_seen` | Whether admin has seen the tamper warning |
| `{slug}_gvectors_legacy_addon_licenses` | Legacy license data for migration |
| `{slug}_gvectors_legacy_license_notices` | Legacy notice state |

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
