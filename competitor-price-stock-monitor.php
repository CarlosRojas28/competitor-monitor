<?php
/**
 * Plugin Name: Competitor Price & Stock Monitor for WooCommerce
 * Description: Monitor competitor prices and stock for WooCommerce products, generate alerts, and show margin-aware pricing recommendations.
 * Version: 1.1.4
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: Competitor Monitor
 * Text Domain: competitor-price-stock-monitor
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WC_COMPETITOR_MONITOR_VERSION', '1.1.4' );
define( 'WC_COMPETITOR_MONITOR_FILE', __FILE__ );
define( 'WC_COMPETITOR_MONITOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_COMPETITOR_MONITOR_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_COMPETITOR_MONITOR_BASENAME', plugin_basename( __FILE__ ) );
define( 'WC_COMPETITOR_MONITOR_CRON_HOOK', 'wc_competitor_monitor_check_event' );

require_once WC_COMPETITOR_MONITOR_PATH . 'includes/class-security.php';
require_once WC_COMPETITOR_MONITOR_PATH . 'includes/class-bridge-auth.php';
require_once WC_COMPETITOR_MONITOR_PATH . 'includes/class-db.php';
require_once WC_COMPETITOR_MONITOR_PATH . 'includes/class-logger.php';
require_once WC_COMPETITOR_MONITOR_PATH . 'includes/class-activator.php';
require_once WC_COMPETITOR_MONITOR_PATH . 'includes/class-deactivator.php';
require_once WC_COMPETITOR_MONITOR_PATH . 'includes/class-parser.php';
require_once WC_COMPETITOR_MONITOR_PATH . 'includes/class-crawler.php';
require_once WC_COMPETITOR_MONITOR_PATH . 'includes/class-recommendations.php';
require_once WC_COMPETITOR_MONITOR_PATH . 'includes/class-alerts.php';
require_once WC_COMPETITOR_MONITOR_PATH . 'includes/class-monitor.php';
require_once WC_COMPETITOR_MONITOR_PATH . 'includes/class-pro-client.php';
require_once WC_COMPETITOR_MONITOR_PATH . 'includes/class-sync.php';
require_once WC_COMPETITOR_MONITOR_PATH . 'includes/class-rest-api.php';
require_once WC_COMPETITOR_MONITOR_PATH . 'includes/class-admin.php';
require_once WC_COMPETITOR_MONITOR_PATH . 'includes/class-plugin.php';

add_filter( 'cron_schedules', array( 'WC_Competitor_Monitor_Activator', 'add_cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- interval is 6 h, defined in add_cron_schedules()

register_activation_hook( __FILE__, array( 'WC_Competitor_Monitor_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WC_Competitor_Monitor_Deactivator', 'deactivate' ) );

/**
 * Boots the plugin after WordPress and WooCommerce have had a chance to load.
 *
 * @return void
 */
function wc_competitor_monitor_bootstrap() {
	$plugin = new WC_Competitor_Monitor_Plugin();
	$plugin->run();
}
add_action( 'plugins_loaded', 'wc_competitor_monitor_bootstrap', 20 );
