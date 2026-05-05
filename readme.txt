=== Competitor Price & Stock Monitor for WooCommerce ===
Contributors: competitor-monitor
Tags: woocommerce, pricing, competitors, stock, alerts
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.1.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Monitor competitor prices and stock for WooCommerce products, receive alerts, and review margin-aware pricing recommendations.

== Description ==

Competitor Price & Stock Monitor for WooCommerce helps store owners map WooCommerce products to competitor URLs, crawl static HTML pages, detect price and stock changes, and review margin-aware recommendations. Pro users can optionally apply recommended prices automatically with internal alerts and optional email notifications.

Features:

* Product-to-competitor URL mappings.
* Optional CSS selectors for price and stock extraction.
* Automatic static HTML price detection for common formats such as 19.99, 19,99, EUR 19.99, 19,99 EUR, USD 29.90, and GBP 25.00.
* Optional browser session fields for protected competitor pages that require cookies from a real browser session.
* Stock detection for common English and Spanish availability phrases.
* WP-Cron monitoring with daily, 12-hour, 6-hour, and hourly intervals.
* Internal dashboard alerts and optional admin email alerts.
* Historical checks in custom database tables.
* Margin-aware pricing recommendations based on WooCommerce price, competitor price, and cost metadata when available.
* Pro automatic price updates with a global setting and a per-product override.

The free local crawler does not execute JavaScript and does not store full competitor HTML. Automatic WooCommerce price updates are disabled by default and require an active Pro license plus explicit opt-in.

== Installation ==

1. Upload the `competitor-price-stock-monitor` folder to `/wp-content/plugins/`.
2. Activate the plugin through the WordPress Plugins screen.
3. Make sure WooCommerce 8+ is active.
4. Go to **Competitor Monitor > Settings** and configure email alerts, crawl timeout, batch size, and monitoring frequency.
5. Go to **Competitor Monitor > Product Mapping** and add competitor URLs for WooCommerce products.
6. Use **Run check now** to test mappings manually.

== Frequently Asked Questions ==

= Does the crawler execute JavaScript? =

No. The crawler uses the WordPress HTTP API and parses static HTML only.

= Can it scan a competitor page behind a browser challenge? =

Only when the remote site accepts the supplied browser session. For protected pages, edit the mapping and paste the exact browser user-agent and Cookie request header from a browser session that can view the competitor page. Some protection systems can still reject server-side HTTP requests even with cookies.

Use the browser DevTools Network panel to select the main document request and copy the `User-Agent` and `Cookie` request headers. Chrome documents the Network panel at https://developer.chrome.com/docs/devtools/network/reference.

= Will the plugin change my product prices automatically? =

Not by default. Automatic price updates require an active Pro license and must be enabled in **Competitor Monitor > Settings**. Each WooCommerce product can inherit the global setting or override it from **Competitor Monitor > Product Mapping**.

= What product cost fields are supported? =

The recommendation engine checks common cost metadata keys including `_wc_cog_cost`, `_wc_cogs_cost`, `_alg_wc_cog_cost`, `_cost`, and `_product_cost`.

= What happens if WooCommerce is inactive? =

The plugin remains active and shows an admin notice. Monitoring is paused until WooCommerce is enabled.

= Can I delete all plugin data on uninstall? =

Yes. Enable **Delete all competitor monitor data when the plugin is uninstalled** in Settings before uninstalling.

== Screenshots ==

1. Dashboard with monitored products, competitor URL counts, alerts, latest changes, and recommendations.
2. Product Mapping table with competitor prices, stock status, differences, and actions.
3. Settings screen for alerts, crawl limits, and WP-Cron frequency.

== Changelog ==

= 1.1.3 =
* Keep the monitoring WP-Cron event synchronized with the selected frequency and show the active schedule in Settings.

= 1.1.2 =
* Encrypt stored Pro license keys and keep only a short preview visible in the admin.

= 1.1.1 =
* Hardened Pro bridge communication with signed HMAC requests, replay protection, bridge key rotation, encrypted local bridge secrets, emergency automatic-pricing kill switch, and redacted logs.

= 1.1.0 =

* Added Pro automatic WooCommerce price updates from approved recommendations.
* Added a global automatic pricing setting and per-product override.
* Added internal alerts and optional email notifications for automatic price changes.

= 1.0.1 =

* Added optional browser session user-agent and Cookie header fields for protected competitor pages.
* Improved detection and messaging for JavaScript browser challenges.

= 1.0.0 =

* Initial release with competitor mappings, crawler, parser, cron monitoring, alerts, history, logs, and recommendations.

== Upgrade Notice ==

= 1.1.3 =

The selected monitoring frequency is now re-applied automatically if the WP-Cron event is missing or out of sync.

= 1.1.2 =

Stored Pro license keys are migrated to encrypted storage and hidden from the admin screen.

= 1.1.1 =

Re-activate or rotate the Pro bridge credentials after upgrading to use signed HMAC communication.

= 1.1.0 =

Adds opt-in Pro automatic price updates. The feature is disabled by default.

= 1.0.1 =

Adds mapping fields for optional browser session cookies and user-agent overrides.

= 1.0.0 =

Initial release.
