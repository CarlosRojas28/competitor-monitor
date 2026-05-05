<?php
/**
 * Deactivation routines.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles deactivation.
 */
class WC_Competitor_Monitor_Deactivator {

	/**
	 * Runs on deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		WC_Competitor_Monitor_Activator::clear_cron();
	}
}
