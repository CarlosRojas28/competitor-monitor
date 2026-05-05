<?php
/**
 * Main plugin bootstrap.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires plugin services into WordPress.
 */
class WC_Competitor_Monitor_Plugin {

	/**
	 * Database layer.
	 *
	 * @var WC_Competitor_Monitor_DB
	 */
	private WC_Competitor_Monitor_DB $db;

	/**
	 * Monitor.
	 *
	 * @var WC_Competitor_Monitor_Monitor
	 */
	private WC_Competitor_Monitor_Monitor $monitor;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->db = new WC_Competitor_Monitor_DB();

		$crawler          = new WC_Competitor_Monitor_Crawler( $this->db );
		$parser           = new WC_Competitor_Monitor_Parser();
		$alerts           = new WC_Competitor_Monitor_Alerts( $this->db );
		$recommendations  = new WC_Competitor_Monitor_Recommendations( $this->db );
		$pro_client       = new WC_Competitor_Monitor_Pro_Client( $this->db );
		$sync             = new WC_Competitor_Monitor_Sync( $this->db, $pro_client );
		$this->monitor    = new WC_Competitor_Monitor_Monitor( $this->db, $crawler, $parser, $alerts, $recommendations, $pro_client );
		new WC_Competitor_Monitor_REST_API( $this->db );

		if ( is_admin() ) {
			new WC_Competitor_Monitor_Admin( $this->db, $this->monitor, $recommendations, $pro_client, $sync );
		}
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function run(): void {
		add_action( WC_COMPETITOR_MONITOR_CRON_HOOK, array( $this->monitor, 'run_scheduled_check' ) );
		add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
		add_action( 'admin_init', array( $this, 'maybe_upgrade_database' ) );
		add_action( 'admin_init', array( $this, 'maybe_sync_cron_schedule' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'sync_profit_impact_after_order' ), 20, 2 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'sync_profit_impact_after_order' ), 20, 2 );
		add_action( 'woocommerce_payment_complete', array( $this, 'sync_profit_impact_after_order' ), 20, 1 );
	}

	/**
	 * Runs lightweight DB upgrades when the plugin is updated in place.
	 *
	 * @return void
	 */
	public function maybe_upgrade_database(): void {
		if ( get_option( WC_Competitor_Monitor_DB::DB_OPTION ) !== WC_Competitor_Monitor_DB::DB_VERSION ) {
			$this->db->install();
		}
	}

	/**
	 * Keeps the WP-Cron event aligned with the saved monitoring frequency.
	 *
	 * @return void
	 */
	public function maybe_sync_cron_schedule(): void {
		$settings = $this->db->get_settings();
		WC_Competitor_Monitor_Activator::ensure_cron( (string) ( $settings['check_frequency'] ?? 'daily' ) );
	}

	/**
	 * Syncs the latest profit impact snapshot after revenue-bearing orders change.
	 *
	 * @param int              $order_id Order ID.
	 * @param WC_Order|null    $order Order object when provided by WooCommerce.
	 * @return void
	 */
	public function sync_profit_impact_after_order( int $order_id = 0, $order = null ): void {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		if ( $order_id > 0 && ( ! is_object( $order ) || ! method_exists( $order, 'get_status' ) ) ) {
			$order = wc_get_order( $order_id );
		}

		if ( is_object( $order ) && method_exists( $order, 'get_status' ) ) {
			$status = sanitize_key( (string) $order->get_status() );
			if ( ! in_array( $status, array( 'processing', 'completed' ), true ) ) {
				return;
			}
		}

		$this->monitor->sync_profit_impact();
	}

	/**
	 * Shows a dependency notice when WooCommerce is inactive.
	 *
	 * @return void
	 */
	public function woocommerce_missing_notice(): void {
		if ( $this->is_woocommerce_active() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Competitor Price & Stock Monitor for WooCommerce is active, but WooCommerce is not active. Monitoring is paused until WooCommerce is enabled.', 'competitor-price-stock-monitor' );
		echo '</p></div>';
	}

	/**
	 * Detects WooCommerce.
	 *
	 * @return bool
	 */
	private function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
	}
}
