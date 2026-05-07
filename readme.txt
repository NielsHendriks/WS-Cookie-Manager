=== Webshake Consent Manager ===
Contributors: webshake
Tags: gdpr, cookie consent, privacy, tracking, facebook pixel, google analytics, matomo, gtm
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Auto-detect and block tracking scripts (GA4, GTM, Meta Pixel, Matomo, Hotjar, and 30+ more) until visitors give consent. GDPR & CCPA ready.

== Description ==

Webshake Consent Manager automatically scans your WordPress site for tracking scripts and blocks them until your visitors give explicit consent. No manual configuration required for common services.

**Auto-detected services include:**

* Google Analytics 4 (GA4)
* Google Tag Manager (GTM)
* Google Ads Remarketing
* Meta (Facebook) Pixel
* Matomo / Piwik
* Hotjar
* Microsoft Clarity
* LinkedIn Insight Tag
* TikTok Pixel
* Pinterest Tag
* X (Twitter) Pixel
* Snapchat Pixel
* HubSpot
* Intercom, Drift, Crisp, Freshchat
* Segment, Mixpanel, Amplitude, Heap
* Optimizely, VWO
* YouTube & Vimeo embeds
* Google Maps
* Google reCAPTCHA
* And more…

**Key Features:**

* One-click auto-scan to detect all tracking scripts
* Blocks scripts, iframes, and tracking pixels via output buffering
* Clean, modern consent banner with multiple position options
* Three consent categories: Analytics, Marketing, Functional
* Granular per-service blocking toggles in the admin panel
* Visitors can revisit their preferences anytime
* Cookie-based consent storage (no external dependencies)
* Fully translatable (i18n ready)
* Lightweight — no jQuery on the frontend, zero external requests
* GDPR and CCPA compliant

== Installation ==

1. Upload the `webshake-consent-manager` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → Consent Manager
4. Click "Re-scan Site" to auto-detect tracking scripts
5. Customise banner text and appearance as needed

== Frequently Asked Questions ==

= Does this plugin slow down my site? =
No. The plugin uses PHP output buffering and a tiny vanilla JS file (~3 KB). There are no external API calls or database queries on the frontend.

= How does script blocking work? =
The plugin intercepts the HTML output and changes blocked `<script>` tags to `type="text/plain"` so browsers ignore them. When a visitor gives consent, the scripts are re-activated via JavaScript.

= Can I add custom scripts to block? =
Not yet via the UI, but you can use the `wscm_consent_given` action hook to integrate custom logic.

== Changelog ==

= 1.0.0 =
* Initial release
* Auto-detection for 30+ tracking services
* Consent banner with category toggles
* Admin settings panel with scan, banner text, appearance, and advanced options
