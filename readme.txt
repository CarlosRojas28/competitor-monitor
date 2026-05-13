=== Competitor Price & Stock Monitor for WooCommerce ===
Contributors: competitor-monitor
Tags: woocommerce, price monitor, competitor pricing, stock monitor, repricing
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.1.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Know when a competitor changes their price or goes out of stock — and reprice safely before your customers notice.

== Description ==

**Competitor Price & Stock Monitor** keeps your WooCommerce store one step ahead. Map any product to one or more competitor URLs, let the plugin crawl those pages on a schedule, and receive instant alerts the moment a price drops or stock disappears.

No third-party monitoring services. No recurring API fees for the core feature. Everything runs from your own server using the WordPress HTTP API and WP-Cron.

= Free Features =

* Map any WooCommerce product to unlimited competitor product URLs.
* Automatic static-HTML price detection for the most common formats: 19.99, 19,99, EUR 19.99, 19,99 EUR, USD 29.90, GBP 25.00, and more.
* Optional CSS selectors when the automatic parser needs guidance.
* Stock detection for common English and Spanish availability phrases (in stock, out of stock, agotado, disponible, etc.).
* WP-Cron monitoring on daily, 12-hour, 6-hour, and hourly schedules.
* Internal dashboard alerts and optional admin email notifications on every detected change.
* Full history of every price and stock check in custom database tables.
* Margin-aware pricing recommendations based on your WooCommerce price, competitor price, and cost metadata from popular cost-of-goods plugins.
* Optional browser session fields (User-Agent and Cookie) for competitor pages that require an authenticated browser session.
* Admin notice when WP-Cron is disabled at the server level.

= Pro Features (requires active Pro license) =

* **Automatic price updates** — apply margin-safe recommendations to WooCommerce products automatically, with a global on/off toggle and per-product overrides.
* **AI Discovery** — paste a WooCommerce product URL into the SaaS dashboard; the AI finds and ranks matching competitor URLs for you.
* **SaaS dashboard** — manage all your connected stores, mappings, snapshots, and profit impact from a single interface.
* **Profit impact attribution** — see how much additional gross profit automatic repricing generated from real WooCommerce orders.
* **Secure bridge** — all Pro communication uses HMAC-SHA256 signed requests with a 60-second replay window and AES-256-GCM encrypted bridge secrets.

= Who Is This For? =

* **Independent store owners** who need to stay price-competitive without hiring a pricing analyst.
* **Agencies** managing multiple WooCommerce stores who want centralized visibility.
* **Brands** that need early warning when authorized resellers go below MAP pricing.

= Privacy =

The free crawler only fetches publicly accessible competitor pages. No competitor HTML is stored persistently. No customer or order data is ever sent to external services. Pro bridge communication is end-to-end authenticated and encrypted.

== Installation ==

1. Upload the `competitor-price-stock-monitor` folder to `/wp-content/plugins/`.
2. Activate the plugin through the WordPress Plugins screen.
3. Make sure WooCommerce 8+ is active.
4. Go to **Competitor Monitor > Settings** and configure email alerts, crawl timeout, batch size, and monitoring frequency.
5. Go to **Competitor Monitor > Product Mapping** and add competitor URLs for WooCommerce products.
6. Click **Run check now** to test a mapping immediately without waiting for the next cron run.

== Frequently Asked Questions ==

= Does the crawler execute JavaScript? =

No. The free crawler uses the WordPress HTTP API and parses static HTML only. Pages that require JavaScript execution to render prices will not return reliable data unless you supply browser session cookies from a real browser visit.

= Can it scan a competitor page behind a browser challenge? =

Only when the remote site accepts the supplied browser session. Edit the mapping and paste the exact browser User-Agent and Cookie request header from a browser session that can view the competitor page. Some protection systems still reject server-side HTTP requests even with cookies.

Use the browser DevTools Network panel to select the main document request and copy the `User-Agent` and `Cookie` request headers.

= Will the plugin change my product prices automatically? =

Not by default. Automatic price updates require an active Pro license and must be enabled in **Competitor Monitor > Settings**. Each WooCommerce product can inherit the global setting or override it at the mapping level. The plugin always enforces your minimum margin before applying any price change.

= What minimum margin rules apply to automatic pricing? =

When automatic pricing is enabled, the plugin checks the competitor price against your WooCommerce regular price, your minimum margin percentage (set in Settings), and your cost metadata if available. A price update is only applied when the result stays above the configured floor. The recommendation log shows the calculated margin for every suggested change.

= What product cost fields are supported? =

The recommendation engine checks common cost metadata keys in this order: `_wc_cog_cost`, `_wc_cogs_cost`, `_alg_wc_cog_cost`, `_cost`, and `_product_cost`. These cover the WooCommerce Cost of Goods plugin, the ATUM plugin, and several other popular cost-tracking plugins.

= Does it work with variable products? =

The plugin maps competitor URLs to WooCommerce products by product ID. For variable products, mapping at the parent product level is recommended. The price shown in recommendations reflects the regular price of the parent product.

= How many competitor URLs can I map per product? =

There is no hard limit. Each WooCommerce product can have multiple competitor mappings. The dashboard shows all competitor prices for a product side by side.

= Does it work with WooCommerce multisite? =

Yes. The plugin can be network-activated. Each site in the network maintains its own mappings, settings, and cron schedule.

= What happens if WooCommerce is deactivated? =

The plugin remains active and shows an admin notice. Cron monitoring is paused until WooCommerce is re-enabled. No data is deleted when WooCommerce is deactivated.

= Does it support non-English price formats? =

Yes. The parser detects prices in formats commonly used in European markets (comma as decimal separator), Latin American markets (period or comma), and several currency prefixes and suffixes (EUR, USD, GBP, BRL, MXN, COP, ARS, and more).

= Can I delete all plugin data when I uninstall? =

Yes. Enable **Delete all competitor monitor data when the plugin is uninstalled** in Settings before uninstalling. This removes all custom database tables, options, and product metadata added by the plugin.

= Does the free version send data to an external server? =

No. The free crawler runs entirely on your server using the WordPress HTTP API. No data is sent externally. The Pro version communicates with the SaaS dashboard for AI features and centralized management; that connection is opt-in and requires an active Pro license.

== Screenshots ==

1. **Dashboard** — overview of all monitored products with competitor URL counts, latest price changes, outstanding alerts, and margin-aware recommendations. A profit impact panel shows estimated gross profit gained from automatic price adjustments.
2. **Product Mapping table** — full list of competitor mappings with current competitor price, stock status, price difference vs. your WooCommerce price, last check time, and quick-action buttons for manual check and edit.
3. **Settings screen** — configure alert email, crawl timeout, batch size, monitoring frequency (hourly to daily), automatic pricing toggle, global minimum margin, and the uninstall data cleanup option.

== Changelog ==

= 1.1.4 =
* Added 90-day attribution window to profit impact queries for consistent reporting in large stores.
* Clean all product cost and price metadata on full uninstall when the delete-data option is enabled.
* Strengthened Pro bridge timestamp validation to a 60-second window on both sides.
* Added REST API pagination to the mappings endpoint to handle large product catalogs safely.
* Added admin notice when WP-Cron is disabled at the server level to prevent silent monitoring gaps.
* Renamed filter hook to `wc_competitor_monitor_product_cost_meta_keys` (was `competitor_price_stock_monitor_product_cost_meta_keys`).

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

= 1.1.4 =
Bridge timestamp window tightened to 60 seconds. Re-save Pro settings if the bridge stops authenticating. Filter hook renamed: `competitor_price_stock_monitor_product_cost_meta_keys` → `wc_competitor_monitor_product_cost_meta_keys`.

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
