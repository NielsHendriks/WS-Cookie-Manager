# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Webshake Consent Manager is a WordPress plugin (GPL-2.0+) that auto-detects tracking scripts and blocks them until visitor consent is given. It targets GDPR/CCPA compliance and supports 30+ services out of the box.

- **Plugin entry point:** [webshake-consent-manager.php](webshake-consent-manager.php)
- **Requires:** WordPress 5.6+, PHP 7.4+
- **Text domain:** `webshake-consent-manager` (Dutch strings are the primary language)
- **Option key for all settings:** `wscm_settings` (single serialized array in `wp_options`)
- **DB table:** `{prefix}wscm_consent_log`
- **Constants:** `WSCM_VERSION`, `WSCM_PLUGIN_DIR`, `WSCM_PLUGIN_URL`, `WSCM_PLUGIN_BASENAME`

## Development

This is a standard WordPress plugin — there is no build step, bundler, or package manager. Edit PHP/JS/CSS directly and test inside a WordPress install.

To install for development, symlink or copy the plugin folder to `wp-content/plugins/` and activate it. There are no automated tests.

**Trigger a re-scan manually** (e.g. after changing signature patterns):
- WP Admin → Settings → Toestemmingsbeheer → "Site opnieuw scannen"
- Or call `WSCM_Scanner::request_scan()` directly (fires a non-blocking HTTP request to the homepage so the scan runs inside the output buffer)

**Admin panel:** `wp-admin/options-general.php?page=wscm-settings`

## Architecture

The main class `Webshake_Consent_Manager` (singleton) bootstraps everything in `plugins_loaded`. It conditionally instantiates classes:

| Class | File | Role |
|---|---|---|
| `WSCM_DB` | [includes/class-wscm-db.php](includes/class-wscm-db.php) | Table creation, consent logging, stats queries, log purge |
| `WSCM_Scanner` | [includes/class-wscm-scanner.php](includes/class-wscm-scanner.php) | Signature definitions, HTML scanning, blocked-pattern lookup |
| `WSCM_Blocker` | [includes/class-wscm-blocker.php](includes/class-wscm-blocker.php) | PHP output buffering; rewrites `<script>`, `<iframe>`, `<img>` |
| `WSCM_Frontend` | [includes/class-wscm-frontend.php](includes/class-wscm-frontend.php) | Enqueues assets, renders consent banner HTML in `wp_footer` |
| `WSCM_Admin` | [includes/class-wscm-admin.php](includes/class-wscm-admin.php) | Settings page (6 tabs), AJAX handlers |
| `WSCM_Rest` | [includes/class-wscm-rest.php](includes/class-wscm-rest.php) | REST endpoints: `POST wscm/v1/consent`, `GET wscm/v1/stats` |
| `WSCM_Cache` | [includes/class-wscm-cache.php](includes/class-wscm-cache.php) | Purges 12+ page-cache plugins on `update_option_wscm_settings` |
| `WSCM_Dashboard` | [includes/class-wscm-dashboard.php](includes/class-wscm-dashboard.php) | WP Dashboard widget (30-day consent KPIs + sparkline) |

### Script blocking flow

1. `WSCM_Blocker::start_buffer()` hooks `template_redirect` (priority 1) and starts `ob_start` with `process_buffer` as the callback — this runs on every frontend page load.
2. If a scan is pending (transient `wscm_scan_pending`), `process_buffer` passes the raw HTML to `WSCM_Scanner::scan_html()`, which pattern-matches against `WSCM_Scanner::SIGNATURES` and writes results back to `wscm_settings`.
3. `process_buffer` then calls four private methods that use `preg_replace_callback` to rewrite matched tags:
   - External `<script src="...">` → `<script type="text/plain" data-wscm-category="...">`
   - Inline `<script>` whose content matches a pattern → same rewrite
   - `<iframe src="...">` → `<iframe data-wscm-src="...">`
   - `<img src="...">` (tracking pixels) → `<img data-wscm-src="...">`
4. An early-blocker inline script is also injected at `wp_head` priority 1 to catch scripts added by other plugins after the buffer starts.

### Consent activation (frontend JS)

[frontend/js/consent.js](frontend/js/consent.js) is vanilla JS (~275 lines, no jQuery). On DOMContentLoaded:
- Reads `wscm_consent` cookie (JSON-encoded consent object).
- If consent exists: calls `activateScripts()` which replaces `type="text/plain"` scripts with real `<script>` nodes and restores `src` on iframes/imgs.
- If no consent: shows the banner.
- On any consent action, POSTs to `wscm/v1/consent` and dispatches `wscm:consent` custom event on `document`.

Configuration is passed via `wp_localize_script` as `window.wscm_config` (expiry days, active categories, position, primary color, geo targeting, REST URL).

### Consent categories

Three non-necessary categories: `analytics`, `marketing`, `functional`. Which categories appear in the banner is dynamic — only categories that have at least one detected blocked script are shown.

### Adding a new tracking service

Add one or more entries to `WSCM_Scanner::SIGNATURES` in [includes/class-wscm-scanner.php](includes/class-wscm-scanner.php):

```php
'pattern-string' => [ 'Service Name', 'category', 'Human-readable description' ],
```

Pattern matching is `stripos` against the full HTML, so patterns can be URL fragments, JS function names, or query parameters. After adding, trigger a re-scan for the new service to appear in the admin.

### Settings structure (`wscm_settings`)

Key fields in the option array:
- `detected_scripts` — array keyed by `sanitize_title($name)`, each entry has `name`, `category`, `description`, `pattern`, `blocked` (bool), `detected_at`
- `custom_scripts` — array of manually added scripts with same shape
- `last_scan` — MySQL datetime string
- `banner_position` — `bottom` | `top` | `center` | `bottom-left` | `bottom-right`
- `dark_mode` — `off` | `on` | `auto`
- `geo_targeting` — `all` | `eu` | `california`
- `consent_expiry_days` — integer
- `primary_color` — hex color

### REST API

- `POST /wp-json/wscm/v1/consent` — public (no auth), rate-limited to 10 req/IP/min via transients. Fires `wscm_consent_given` action hook after logging.
- `GET /wp-json/wscm/v1/stats?days=N` — requires `manage_options` capability.

### Uninstall

[uninstall.php](uninstall.php) deletes `wscm_settings`, `wscm_db_version` options, and drops the consent log table.
