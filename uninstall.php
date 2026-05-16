<?php
/**
 * Plugin uninstall.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-db.php';

wp_clear_scheduled_hook( 'wc_competitor_monitor_check_event' );
delete_transient( 'wc_competitor_monitor_welcome' );

$wc_competitor_monitor_settings = get_option( WC_Competitor_Monitor_DB::OPTION_KEY, array() );
if ( is_array( $wc_competitor_monitor_settings ) && ! empty( $wc_competitor_monitor_settings['delete_data_on_uninstall'] ) ) {
	$wc_competitor_monitor_db = new WC_Competitor_Monitor_DB();
	$wc_competitor_monitor_db->uninstall();
}
