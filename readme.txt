=== Competitor Price & Stock Monitor for WooCommerce ===
Contributors: competitor-monitor
Tags: woocommerce, competitors, pricing, stock, repricing
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.1.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Monitor competitor prices and stock automatically. Get alerted the moment a rival undercuts you — and reprice before you lose the sale.

== Description ==

Your competitors change prices every day. Most store owners find out too late — after a customer screenshots the lower price, asks for a match, or just leaves.

**Competitor Price & Stock Monitor** watches competitor product pages on a schedule using the WordPress HTTP API. The moment a price drops or stock disappears, you get an alert. With a Pro license, the plugin can apply margin-safe price updates to your WooCommerce products automatically — no spreadsheets, no manual checks.

= Free Features =

* **Unlimited competitor mappings** — link any WooCommerce product to as many competitor URLs as you need.
* **Automatic price detection** — parses prices in the most common formats without configuration: 19.99, 19,99, EUR 19.99, USD 29.90, GBP 25.00, and more.
* **Stock detection** — recognises common English and Spanish availability phrases (in stock, out of stock, agotado, disponible…).
* **Optional CSS selectors** — override the automatic parser for non-standard pages.
* **WP-Cron monitoring** — run checks hourly, every 6 hours, every 12 hours, or daily.
* **Instant alerts** — internal dashboard notices and optional admin email on every detected change.
* **Full history** — every price and stock check stored in custom database tables.
* **Margin-aware recommendations** — suggested price changes respect your minimum margin floor and read cost data from popular plugins (WooCommerce Cost of Goods, ATUM, and more).
* **Browser session support** — supply a User-Agent and Cookie header for competitor pages that require an authenticated session.
* **WP-Cron health notice** — warns you if server-level Cron is disabled so you never miss a monitoring gap.

= Pro Features (requires active Pro license) =

* **Automatic repricing** — apply margin-safe recommendations to WooCommerce products automatically, with a global kill switch and per-product overrides.
* **AI competitor discovery** — paste a product URL into the SaaS dashboard; the AI finds and scores matching competitor listings for you.
* **Centralized dashboard** — manage all connected stores, mappings, snapshots, and profit impact from one place.
* **Profit impact attribution** — see the gross profit gained from automatic price adjustments, traced back to real WooCommerce orders.
* **Secure bridge** — all Pro communication uses HMAC-SHA256 signed requests with a 60-second replay window and AES-256-GCM encrypted bridge secrets.

= Who Is This For? =

* **Independent store owners** who compete on price and want real-time visibility without hiring a pricing analyst.
* **Agencies** managing multiple WooCommerce stores who want centralized alerts and a single repricing dashboard.
* **Brands** that monitor authorized resellers for MAP pricing violations.

= Privacy =

The free crawler only fetches publicly accessible competitor pages using the WordPress HTTP API. No competitor HTML is stored persistently. No customer, order, or product cost data is sent to any external service. Pro bridge communication is fully opt-in, requires an active Pro license, and is authenticated end-to-end.

== Installation ==

1. Upload the `competitor-price-stock-monitor` folder to `/wp-content/plugins/` or install directly from the WordPress plugin directory.
2. Activate the plugin through the **Plugins** screen.
3. Make sure WooCommerce 8+ is active.
4. Go to **Competitor Monitor > Settings** to configure alert email, crawl timeout, batch size, and monitoring frequency.
5. Go to **Competitor Monitor > Product Mapping** and add competitor URLs for your WooCommerce products.
6. Click **Run check now** on any mapping to test immediately without waiting for the scheduled cron run.

== Frequently Asked Questions ==

= Does the free version send data to an external server? =

No. The free crawler runs entirely on your server using the WordPress HTTP API. The plugin fetches publicly accessible competitor pages — it does not transmit product data, order data, or personal data anywhere. The Pro version communicates with the Competitor Monitor SaaS dashboard for AI features and centralized management; that connection is fully opt-in, requires explicit Pro license configuration, and is documented in the External Services section below.

= Will the plugin slow down my WooCommerce store? =

No. All competitor checks run via WP-Cron in the background, completely separate from your customer-facing page loads. You can configure the batch size and schedule in Settings to control the load on your server.

= Does the crawler execute JavaScript? =

No. The free crawler uses the WordPress HTTP API and parses static HTML only. Pages that require JavaScript to render prices will not return reliable data unless you supply browser session cookies from a real browser visit.

= Can it scan a competitor page behind a browser challenge? =

Only when the remote site accepts the supplied browser session. Edit the mapping and paste the exact User-Agent and Cookie request headers from a browser session that can view the competitor page. Some protection systems still reject server-side HTTP requests even with valid cookies.

Use the browser DevTools Network panel to select the main document request and copy the `User-Agent` and `Cookie` request headers.

= Will the plugin change my prices automatically? =

Not by default. Automatic price updates require an active Pro license and must be explicitly enabled in **Competitor Monitor > Settings**. Each product can inherit the global setting or override it individually. The plugin always enforces your configured minimum margin before applying any price change.

= What minimum margin rules apply to automatic pricing? =

When automatic pricing is enabled, the plugin compares the competitor price against your WooCommerce regular price, your minimum margin percentage (set in Settings), and your cost metadata if available. A price update is only applied when the result stays above the configured floor. The recommendation log shows the calculated margin for every suggested change.

= What product cost fields are supported? =

The recommendation engine checks cost metadata keys in this order: `_wc_cog_cost`, `_wc_cogs_cost`, `_alg_wc_cog_cost`, `_cost`, and `_product_cost`. These cover WooCommerce Cost of Goods, ATUM, and several other popular cost-tracking plugins.

= Does it work with variable products? =

The plugin maps competitor URLs to WooCommerce products by product ID. For variable products, mapping at the parent product level is recommended. Recommendations reflect the regular price of the parent product.

= How many competitor URLs can I map per product? =

There is no hard limit. Each WooCommerce product can have multiple competitor mappings. The dashboard shows all competitor prices for a product side by side.

= Does it work with WooCommerce multisite? =

Yes. The plugin can be network-activated. Each site in the network maintains its own mappings, settings, and cron schedule independently.

= What happens if WooCommerce is deactivated? =

The plugin remains active and shows an admin notice. Cron monitoring is paused until WooCommerce is re-enabled. No data is deleted.

= Does it support non-English price formats? =

Yes. The parser detects prices in formats common in European markets (comma as decimal separator), Latin American markets (period or comma), and currency prefixes/suffixes including EUR, USD, GBP, BRL, MXN, COP, ARS, and more.

= Can I delete all plugin data when I uninstall? =

Yes. Enable **Delete all competitor monitor data when the plugin is uninstalled** in Settings before uninstalling. This removes all custom database tables, options, and product metadata added by the plugin.

= Is the plugin secure? =

Yes. All admin form handlers verify a WordPress nonce and require the `manage_woocommerce` capability. The crawler includes SSRF protection — it blocks requests to private IP ranges and localhost. All database queries use `$wpdb->prepare()`. The Pro bridge uses HMAC-SHA256 signed requests with a 60-second replay window and AES-256-GCM encrypted secrets stored locally.

== Screenshots ==

1. **Dashboard** — side-by-side view of all monitored products: competitor URL count, latest price changes, outstanding alerts, and margin-aware recommendations. A profit impact panel shows estimated gross profit gained from automatic price adjustments.
2. **Product Mapping table** — full list of competitor mappings with current competitor price, stock status, price difference vs. your WooCommerce price, last check timestamp, and quick-action buttons for manual check and edit.
3. **Settings screen** — configure alert email, crawl timeout, batch size, monitoring frequency (hourly to daily), automatic pricing toggle, global minimum margin percentage, and the uninstall data cleanup option.

== External Services ==

= Competitor Monitor Pro SaaS =

When a Pro license is active and configured, the plugin communicates with the Competitor Monitor Pro service (competitor-monitor-pro-production.up.railway.app) for the following Pro features:

* AI-assisted competitor discovery
* Centralized mapping and snapshot management via the SaaS dashboard
* Profit impact attribution using WooCommerce order data aggregated on your server

All requests are authenticated with HMAC-SHA256 signed headers and sent over HTTPS. No customer data, order data, or payment information is transmitted — only product URLs, mapping results, and aggregated profit metrics that you choose to sync.

This connection is entirely opt-in. It requires explicit Pro license configuration in **Competitor Monitor > Settings**. The free version of the plugin does not communicate with this service.

* Service website: https://competitor-monitor-pro-production.up.railway.app
* Terms of service: https://competitor-monitor-pro-production.up.railway.app/terms
* Privacy policy: https://competitor-monitor-pro-production.up.railway.app/privacy

= Competitor product pages (free feature) =

The free crawler sends HTTP requests to competitor product pages that you explicitly configure in **Competitor Monitor > Product Mapping**. These requests use the WordPress HTTP API (`wp_remote_get`). No data from those responses is transmitted to any third-party service or stored persistently beyond the extracted price and stock values saved in your local WordPress database.

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
* Added global automatic pricing setting and per-product override.
* Added internal alerts and optional email notifications for automatic price changes.

= 1.0.1 =
* Added optional browser session User-Agent and Cookie header fields for protected competitor pages.
* Improved detection and messaging for JavaScript browser challenges.

= 1.0.0 =
* Initial release with competitor mappings, crawler, parser, cron monitoring, alerts, history, logs, and recommendations.

== Upgrade Notice ==

= 1.1.4 =
Bridge timestamp window tightened to 60 seconds. Re-save Pro settings if the bridge stops authenticating after upgrading. Filter hook renamed: `competitor_price_stock_monitor_product_cost_meta_keys` → `wc_competitor_monitor_product_cost_meta_keys`.

= 1.1.3 =
The selected monitoring frequency is now re-applied automatically if the WP-Cron event is missing or out of sync.

= 1.1.2 =
Stored Pro license keys are migrated to encrypted storage on upgrade and hidden from the admin screen.

= 1.1.1 =
Re-activate or rotate Pro bridge credentials after upgrading to use signed HMAC communication.

= 1.1.0 =
Adds opt-in Pro automatic price updates. The feature is disabled by default.

= 1.0.1 =
Adds mapping fields for optional browser session cookies and user-agent overrides.

= 1.0.0 =
Initial release.
