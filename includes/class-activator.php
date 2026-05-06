<?php
/**
 * Activation routines.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles activation and cron scheduling.
 */
class WC_Competitor_Monitor_Activator {

	/**
	 * Runs on activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		$db = new WC_Competitor_Monitor_DB();
		$db->install();

		$settings = $db->get_settings();
		self::schedule_cron( (string) $settings['check_frequency'] );

		self::send_activation_telemetry( $settings );
	}

	/**
	 * Fires a plugin.installed telemetry event to the Pro SaaS if connected.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 * @return void
	 */
	private static function send_activation_telemetry( array $settings ): void {
		$saas_url = (string) ( $settings['pro_saas_url'] ?? '' );
		if ( empty( $settings['pro_enabled'] ) || '' === $saas_url ) {
			return;
		}

		$site_id          = (string) ( $settings['pro_site_id'] ?? '' );
		$key_id           = (string) ( $settings['pro_key_id'] ?? '' );
		$secret_encrypted = (string) ( $settings['pro_plugin_to_saas_secret_encrypted'] ?? '' );

		if ( '' === $site_id || '' === $key_id || '' === $secret_encrypted ) {
			return;
		}

		$secret = WC_Competitor_Monitor_Bridge_Auth::decrypt_secret( $secret_encrypted );
		if ( '' === $secret ) {
			return;
		}

		$body                    = wp_json_encode(
			array(
				'event'       => 'plugin.installed',
				'version'     => WC_COMPETITOR_MONITOR_VERSION,
				'php_version' => PHP_VERSION,
				'wp_version'  => get_bloginfo( 'version' ),
				'wc_version'  => defined( 'WC_VERSION' ) ? WC_VERSION : '',
				'site_url'    => home_url( '/' ),
			)
		);
		$url                     = rtrim( $saas_url, '/' ) . '/v1/plugin/telemetry';
		$headers                 = WC_Competitor_Monitor_Bridge_Auth::sign_headers( 'POST', $url, (string) $body, $site_id, $key_id, $secret );
		$headers['Content-Type'] = 'application/json';

		wp_remote_post(
			$url,
			array(
				'headers'  => $headers,
				'body'     => $body,
				'timeout'  => 5,
				'blocking' => false,
			)
		);
	}

	/**
	 * Adds custom monitoring schedules.
	 *
	 * @param array<string,array<string,mixed>> $schedules Existing schedules.
	 * @return array<string,array<string,mixed>>
	 */
	public static function add_cron_schedules( array $schedules ): array {
		$schedules['wc_competitor_monitor_six_hours'] = array(
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 6 hours', 'competitor-price-stock-monitor' ),
		);

		return $schedules;
	}

	/**
	 * Converts internal frequency to a WordPress schedule.
	 *
	 * @param string $frequency Internal frequency.
	 * @return string
	 */
	public static function frequency_to_schedule( string $frequency ): string {
		switch ( $frequency ) {
			case 'hourly':
				return 'hourly';
			case 'six_hours':
				return 'wc_competitor_monitor_six_hours';
			case 'twelve_hours':
				return 'twicedaily';
			case 'daily':
			default:
				return 'daily';
		}
	}

	/**
	 * Schedules the cron event.
	 *
	 * @param string $frequency Internal frequency.
	 * @return void
	 */
	public static function schedule_cron( string $frequency = 'daily' ): void {
		self::clear_cron();

		$schedule = self::frequency_to_schedule( $frequency );
		if ( ! wp_next_scheduled( WC_COMPETITOR_MONITOR_CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, $schedule, WC_COMPETITOR_MONITOR_CRON_HOOK );
		}
	}

	/**
	 * Ensures the scheduled event exists and matches the saved frequency.
	 *
	 * @param string $frequency Internal frequency.
	 * @return void
	 */
	public static function ensure_cron( string $frequency = 'daily' ): void {
		$expected_schedule = self::frequency_to_schedule( $frequency );
		$event             = wp_get_scheduled_event( WC_COMPETITOR_MONITOR_CRON_HOOK );

		if ( ! $event || $event->schedule !== $expected_schedule ) {
			self::schedule_cron( $frequency );
		}
	}

	/**
	 * Returns the currently scheduled WP-Cron event.
	 *
	 * @return object|null
	 */
	public static function scheduled_event(): ?object {
		$event = wp_get_scheduled_event( WC_COMPETITOR_MONITOR_CRON_HOOK );
		return $event ? $event : null;
	}

	/**
	 * Clears scheduled events.
	 *
	 * @return void
	 */
	public static function clear_cron(): void {
		wp_clear_scheduled_hook( WC_COMPETITOR_MONITOR_CRON_HOOK );
	}
}
