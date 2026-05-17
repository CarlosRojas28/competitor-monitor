<?php
/**
 * Settings view.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wc_competitor_monitor_increase_limit_mode = sanitize_key( (string) ( $wc_competitor_monitor_settings['suggested_increase_limit_mode'] ?? 'percent' ) );
if ( ! in_array( $wc_competitor_monitor_increase_limit_mode, array( 'percent', 'none' ), true ) ) {
	$wc_competitor_monitor_increase_limit_mode = 'percent';
}
$wc_competitor_monitor_increase_limit_percentage = max( 0, min( 999.99, (float) ( $wc_competitor_monitor_settings['suggested_increase_limit_percentage'] ?? 5.0 ) ) );
$wc_competitor_monitor_auto_price_adjustment     = sanitize_key( (string) ( $wc_competitor_monitor_settings['auto_price_adjustment_mode'] ?? 'disabled' ) );
$wc_competitor_monitor_kill_switch               = ! empty( $wc_competitor_monitor_settings['auto_price_kill_switch'] );

// Derive the 3-state value from the two stored fields.
if ( 'enabled' === $wc_competitor_monitor_auto_price_adjustment ) {
	$wc_competitor_monitor_auto_price_mode_3state = $wc_competitor_monitor_kill_switch ? 'paused' : 'on';
} else {
	$wc_competitor_monitor_auto_price_mode_3state = 'off';
}

$wc_competitor_monitor_pro_is_active  =! empty( $wc_competitor_monitor_settings['pro_enabled'] ) && 'active' === (string) ( $wc_competitor_monitor_settings['pro_license_status'] ?? '' );
$wc_competitor_monitor_key_preview   = (string) ( $wc_competitor_monitor_settings['pro_license_key_preview'] ?? '' );
$wc_competitor_monitor_key_last4     = $wc_competitor_monitor_key_preview !== '' ? substr( $wc_competitor_monitor_key_preview, -4 ) : '';
$wc_competitor_monitor_show_masked   = $wc_competitor_monitor_pro_is_active && '' !== $wc_competitor_monitor_key_last4;
$wc_competitor_monitor_saas_base_url = untrailingslashit( esc_url_raw( (string) ( $wc_competitor_monitor_settings['pro_saas_url'] ?? 'https://competitor-monitor-pro-production.up.railway.app' ) ) );
if ( '' === $wc_competitor_monitor_saas_base_url ) {
	$wc_competitor_monitor_saas_base_url = 'https://competitor-monitor-pro-production.up.railway.app';
}
$wc_competitor_monitor_upgrade_url          = $wc_competitor_monitor_saas_base_url . '/#pricing';
$wc_competitor_monitor_sites_url            = $wc_competitor_monitor_saas_base_url . '/app/sites';
$wc_competitor_monitor_cron_event           = WC_Competitor_Monitor_Activator::scheduled_event();
$wc_competitor_monitor_cron_schedule_labels = array(
	'hourly'                          => __( 'Hourly', 'competitor-price-stock-monitor' ),
	'wc_competitor_monitor_six_hours' => __( 'Every 6 hours', 'competitor-price-stock-monitor' ),
	'twicedaily'                      => __( 'Every 12 hours', 'competitor-price-stock-monitor' ),
	'daily'                           => __( 'Daily', 'competitor-price-stock-monitor' ),
);
?>
<div class="wrap wccm-wrap">
	<h1><?php esc_html_e( 'Settings', 'competitor-price-stock-monitor' ); ?></h1>

	<section class="wccm-panel">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wccm-form">
			<input type="hidden" name="action" value="wc_competitor_monitor_save_settings">
			<?php wp_nonce_field( 'wc_competitor_monitor_save_settings' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wccm_alert_email"><?php esc_html_e( 'Alert email', 'competitor-price-stock-monitor' ); ?></label></th>
					<td><input type="email" class="regular-text" id="wccm_alert_email" name="alert_email" value="<?php echo esc_attr( $wc_competitor_monitor_settings['alert_email'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Email alerts', 'competitor-price-stock-monitor' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="email_alerts" value="1" <?php checked( (int) $wc_competitor_monitor_settings['email_alerts'], 1 ); ?>>
							<?php esc_html_e( 'Send alert emails', 'competitor-price-stock-monitor' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wccm_threshold"><?php esc_html_e( 'Minimum price difference for alerts', 'competitor-price-stock-monitor' ); ?></label></th>
					<td><input type="number" step="0.01" min="0" id="wccm_threshold" name="price_change_threshold" value="<?php echo esc_attr( $wc_competitor_monitor_settings['price_change_threshold'] ); ?>"> %</td>
				</tr>
				<tr>
					<th scope="row"><label for="wccm_auto_price_mode"><?php esc_html_e( 'Automatic price updates (Pro)', 'competitor-price-stock-monitor' ); ?></label></th>
					<td>
						<select id="wccm_auto_price_mode" name="auto_price_mode"<?php disabled( $wc_competitor_monitor_pro_is_active, false ); ?>>
							<option value="off" <?php selected( $wc_competitor_monitor_auto_price_mode_3state, 'off' ); ?>><?php esc_html_e( 'Off — never change prices automatically', 'competitor-price-stock-monitor' ); ?></option>
							<option value="on" <?php selected( $wc_competitor_monitor_auto_price_mode_3state, 'on' ); ?>><?php esc_html_e( 'On — apply suggestions and notify me', 'competitor-price-stock-monitor' ); ?></option>
							<option value="paused" <?php selected( $wc_competitor_monitor_auto_price_mode_3state, 'paused' ); ?>><?php esc_html_e( 'Paused — keep rules, block all changes now', 'competitor-price-stock-monitor' ); ?></option>
						</select>
						<p class="description">
							<?php esc_html_e( 'When On and a Pro license is active, checks can update product prices to the current suggestion. Paused blocks changes immediately while keeping rules intact — use this as an emergency stop. Every automatic change creates an alert and sends email when email alerts are enabled.', 'competitor-price-stock-monitor' ); ?>
						</p>
						<?php if ( ! $wc_competitor_monitor_pro_is_active ) : ?>
							<p class="description">
								<a href="<?php echo esc_url( $wc_competitor_monitor_upgrade_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade to Pro to enable automatic price updates.', 'competitor-price-stock-monitor' ); ?></a>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr data-wccm-auto-pricing-settings<?php echo 'on' !== $wc_competitor_monitor_auto_price_mode_3state || ! $wc_competitor_monitor_pro_is_active ? ' hidden' : ''; ?>>
					<th scope="row"><label for="wccm_suggested_increase_limit_mode"><?php esc_html_e( 'Suggested price increase limit', 'competitor-price-stock-monitor' ); ?></label></th>
					<td>
						<select id="wccm_suggested_increase_limit_mode" name="suggested_increase_limit_mode">
							<option value="percent" <?php selected( $wc_competitor_monitor_increase_limit_mode, 'percent' ); ?>><?php esc_html_e( 'Limit by percentage', 'competitor-price-stock-monitor' ); ?></option>
							<option value="none" <?php selected( $wc_competitor_monitor_increase_limit_mode, 'none' ); ?>><?php esc_html_e( 'No limit', 'competitor-price-stock-monitor' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Controls how far recommendations may raise your current WooCommerce price when you are much cheaper than a competitor. Individual mappings can override this.', 'competitor-price-stock-monitor' ); ?></p>
					</td>
				</tr>
				<tr data-wccm-suggested-increase-percentage data-wccm-auto-pricing-settings<?php echo ( 'on' !== $wc_competitor_monitor_auto_price_mode_3state || ! $wc_competitor_monitor_pro_is_active || 'none' === $wc_competitor_monitor_increase_limit_mode ) ? ' hidden' : ''; ?>>
					<th scope="row"><label for="wccm_suggested_increase_limit_percentage"><?php esc_html_e( 'Suggested increase %', 'competitor-price-stock-monitor' ); ?></label></th>
					<td>
						<input type="number" step="0.01" min="0" max="999.99" id="wccm_suggested_increase_limit_percentage" name="suggested_increase_limit_percentage" value="<?php echo esc_attr( $wc_competitor_monitor_increase_limit_percentage ); ?>"> %
						<p class="description"><?php esc_html_e( 'Default is 5%. Set the mode above to No limit if recommendations may rise up to just below the competitor price.', 'competitor-price-stock-monitor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wccm_frequency"><?php esc_html_e( 'Monitoring frequency', 'competitor-price-stock-monitor' ); ?></label></th>
					<td>
						<select id="wccm_frequency" name="check_frequency">
							<option value="daily" <?php selected( $wc_competitor_monitor_settings['check_frequency'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'competitor-price-stock-monitor' ); ?></option>
							<option value="twelve_hours" <?php selected( $wc_competitor_monitor_settings['check_frequency'], 'twelve_hours' ); ?><?php echo $wc_competitor_monitor_pro_is_active ? '' : ' disabled'; ?>><?php esc_html_e( 'Every 12 hours (Pro)', 'competitor-price-stock-monitor' ); ?></option>
							<option value="six_hours" <?php selected( $wc_competitor_monitor_settings['check_frequency'], 'six_hours' ); ?><?php echo $wc_competitor_monitor_pro_is_active ? '' : ' disabled'; ?>><?php esc_html_e( 'Every 6 hours (Pro)', 'competitor-price-stock-monitor' ); ?></option>
							<option value="hourly" <?php selected( $wc_competitor_monitor_settings['check_frequency'], 'hourly' ); ?><?php echo $wc_competitor_monitor_pro_is_active ? '' : ' disabled'; ?>><?php esc_html_e( 'Hourly (Pro)', 'competitor-price-stock-monitor' ); ?></option>
						</select>
						<?php if ( ! $wc_competitor_monitor_pro_is_active ) : ?>
							<div class="notice notice-info inline" style="margin:8px 0 0;">
								<p>
									<?php esc_html_e( 'Hourly, 6-hour and 12-hour monitoring are Pro features.', 'competitor-price-stock-monitor' ); ?>
									<a href="<?php echo esc_url( $wc_competitor_monitor_upgrade_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade to Pro', 'competitor-price-stock-monitor' ); ?></a>
								</p>
							</div>
						<?php endif; ?>
						<?php if ( $wc_competitor_monitor_cron_event ) : ?>
							<p class="description">
								<?php
								printf(
									/* translators: 1: current WP-Cron schedule, 2: next run date. */
									esc_html__( 'Current WP-Cron schedule: %1$s. Next run: %2$s.', 'competitor-price-stock-monitor' ),
									esc_html( $wc_competitor_monitor_cron_schedule_labels[ $wc_competitor_monitor_cron_event->schedule ] ?? $wc_competitor_monitor_cron_event->schedule ),
									esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $wc_competitor_monitor_cron_event->timestamp ) )
								);
								?>
							</p>
						<?php else : ?>
							<p class="description"><?php esc_html_e( 'The monitoring event will be scheduled automatically when settings are saved or the admin reloads.', 'competitor-price-stock-monitor' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Uninstall behavior', 'competitor-price-stock-monitor' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( (int) $wc_competitor_monitor_settings['delete_data_on_uninstall'], 1 ); ?>>
							<?php esc_html_e( 'Delete all competitor monitor data when the plugin is uninstalled', 'competitor-price-stock-monitor' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<details style="margin-top:1.5rem">
				<summary style="cursor:pointer;font-weight:600;color:#3c434a"><?php esc_html_e( 'Advanced settings', 'competitor-price-stock-monitor' ); ?></summary>
				<p class="description" style="margin:.5rem 0 1rem"><?php esc_html_e( 'These settings control the technical behavior of the price checker. The defaults work for most stores.', 'competitor-price-stock-monitor' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wccm_user_agent"><?php esc_html_e( 'Crawler user-agent', 'competitor-price-stock-monitor' ); ?></label></th>
						<td><input type="text" class="large-text" id="wccm_user_agent" name="user_agent" value="<?php echo esc_attr( $wc_competitor_monitor_settings['user_agent'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="wccm_timeout"><?php esc_html_e( 'HTTP timeout', 'competitor-price-stock-monitor' ); ?></label></th>
						<td><input type="number" min="3" max="30" id="wccm_timeout" name="timeout" value="<?php echo esc_attr( $wc_competitor_monitor_settings['timeout'] ); ?>"> <?php esc_html_e( 'seconds', 'competitor-price-stock-monitor' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><label for="wccm_max_response_size"><?php esc_html_e( 'Maximum response size', 'competitor-price-stock-monitor' ); ?></label></th>
						<td><input type="number" min="10240" max="5242880" step="1024" id="wccm_max_response_size" name="max_response_size" value="<?php echo esc_attr( $wc_competitor_monitor_settings['max_response_size'] ); ?>"> <?php esc_html_e( 'bytes', 'competitor-price-stock-monitor' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><label for="wccm_batch_size"><?php esc_html_e( 'Max competitor checks per batch', 'competitor-price-stock-monitor' ); ?></label></th>
						<td><input type="number" min="1" max="100" id="wccm_batch_size" name="batch_size" value="<?php echo esc_attr( $wc_competitor_monitor_settings['batch_size'] ); ?>"></td>
					</tr>
				</table>
			</details>

			<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'competitor-price-stock-monitor' ); ?></button></p>
		</form>
	</section>

	<?php if ( $wc_competitor_monitor_pro_is_active ) : ?>
	<section class="wccm-panel wccm-danger-zone" id="wccm-restore-section">
		<h2><?php esc_html_e( 'Restore original prices', 'competitor-price-stock-monitor' ); ?></h2>
		<p>
			<?php
			if ( $wc_competitor_monitor_original_price_count > 0 ) {
				echo esc_html(
					sprintf(
						/* translators: %d: number of products with stored original prices. */
						_n(
							'Auto-pricing has adjusted %d product. Restoring will revert it to the price it had before the first automatic adjustment.',
							'Auto-pricing has adjusted %d products. Restoring will revert each one to the price it had before the first automatic adjustment.',
							$wc_competitor_monitor_original_price_count,
							'competitor-price-stock-monitor'
						),
						$wc_competitor_monitor_original_price_count
					)
				);
			} else {
				esc_html_e( 'No products have been adjusted by auto-pricing yet. Nothing to restore.', 'competitor-price-stock-monitor' );
			}
			?>
		</p>

		<?php if ( $wc_competitor_monitor_original_price_count > 0 ) : ?>
		<button type="button" id="wccm-restore-trigger" class="button">
			<?php esc_html_e( 'Restore original prices…', 'competitor-price-stock-monitor' ); ?>
		</button>

		<div id="wccm-restore-confirm" hidden style="margin-top:12px">
			<div class="notice notice-warning inline" style="margin:0 0 12px">
				<p>
					<strong><?php esc_html_e( 'Are you sure?', 'competitor-price-stock-monitor' ); ?></strong>
					<?php esc_html_e( 'This will revert all auto-adjusted products to their stored original price. Products where the competitor is still cheaper than the margin floor will be skipped.', 'competitor-price-stock-monitor' ); ?>
				</p>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wc_competitor_monitor_bulk_restore">
				<?php wp_nonce_field( 'wc_competitor_monitor_bulk_restore' ); ?>
				<button type="submit" class="button wccm-danger-btn">
					<?php esc_html_e( 'Yes, restore all original prices', 'competitor-price-stock-monitor' ); ?>
				</button>
				<button type="button" id="wccm-restore-cancel" class="button" style="margin-left:6px">
					<?php esc_html_e( 'Cancel', 'competitor-price-stock-monitor' ); ?>
				</button>
			</form>
		</div>
		<?php endif; ?>
	</section>
	<?php endif; ?>

	<section class="wccm-panel" id="wccm-pro-license-section">
		<h2><?php esc_html_e( 'Pro license', 'competitor-price-stock-monitor' ); ?></h2>
		<?php if ( ! $wc_competitor_monitor_pro_is_active ) : ?>
			<p>
				<?php esc_html_e( "Don't have a Pro account yet?", 'competitor-price-stock-monitor' ); ?>
				<a href="<?php echo esc_url( $wc_competitor_monitor_upgrade_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Sign up at Competitor Monitor Pro', 'competitor-price-stock-monitor' ); ?></a>
			</p>
			<p>
				<?php esc_html_e( 'Already have a Pro account? Open the SaaS Sites screen, register this site, copy the one-time key, and paste it below.', 'competitor-price-stock-monitor' ); ?>
				<a href="<?php echo esc_url( $wc_competitor_monitor_sites_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open SaaS Sites', 'competitor-price-stock-monitor' ); ?></a>
			</p>
		<?php else : ?>
			<p>
				<?php esc_html_e( 'Use the SaaS Sites screen to generate a one-time plugin registration key for this exact site.', 'competitor-price-stock-monitor' ); ?>
				<a href="<?php echo esc_url( $wc_competitor_monitor_sites_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open SaaS Sites', 'competitor-price-stock-monitor' ); ?></a>
			</p>
		<?php endif; ?>
		<?php if ( ! $wc_competitor_monitor_pro_is_active ) : ?>
			<div class="notice notice-info inline">
				<p><strong><?php esc_html_e( 'Unlock Pro: AI competitor discovery and profit impact', 'competitor-price-stock-monitor' ); ?></strong></p>
				<p><?php esc_html_e( 'Upgrade to use the SaaS for AI competitor discovery, automatic price updates and gross profit impact reporting.', 'competitor-price-stock-monitor' ); ?></p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( $wc_competitor_monitor_upgrade_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade to Pro', 'competitor-price-stock-monitor' ); ?></a>
					<a class="button" href="<?php echo esc_url( $wc_competitor_monitor_sites_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Connect existing Pro license', 'competitor-price-stock-monitor' ); ?></a>
				</p>
			</div>
		<?php endif; ?>
		<p>
			<strong><?php esc_html_e( 'Status:', 'competitor-price-stock-monitor' ); ?></strong>
			<?php echo esc_html( $wc_competitor_monitor_settings['pro_license_status'] ? $wc_competitor_monitor_settings['pro_license_status'] : __( 'inactive', 'competitor-price-stock-monitor' ) ); ?>
			<?php if ( ! empty( $wc_competitor_monitor_settings['pro_plan'] ) ) : ?>
				<?php echo esc_html( ' / ' . $wc_competitor_monitor_settings['pro_plan'] ); ?>
			<?php endif; ?>
		</p>
		<?php if ( ! empty( $wc_competitor_monitor_settings['pro_license_message'] ) ) : ?>
			<p><?php echo esc_html( $wc_competitor_monitor_settings['pro_license_message'] ); ?></p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wccm-form">
			<input type="hidden" name="action" value="wc_competitor_monitor_activate_pro_license">
			<?php wp_nonce_field( 'wc_competitor_monitor_activate_pro_license' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wccm_pro_license_key"><?php esc_html_e( 'Plugin registration key', 'competitor-price-stock-monitor' ); ?></label></th>
					<td>
						<?php if ( $wc_competitor_monitor_show_masked ) : ?>
							<input type="text" class="regular-text" id="wccm_pro_license_key" value="<?php echo esc_attr( str_repeat( '•', 12 ) . $wc_competitor_monitor_key_last4 ); ?>" readonly autocomplete="off">
							<input type="hidden" name="pro_license_key" value="">
						<?php else : ?>
							<input type="password" class="regular-text" id="wccm_pro_license_key" name="pro_license_key" value="" autocomplete="off" placeholder="<?php echo esc_attr( $wc_competitor_monitor_pro_is_active ? __( 'Stored securely. Enter a new key to reactivate.', 'competitor-price-stock-monitor' ) : __( 'CPSM-REG-...', 'competitor-price-stock-monitor' ) ); ?>">
						<?php endif; ?>
						<p class="description"><?php esc_html_e( 'Registration keys are generated in the SaaS, are scoped to one site URL, can be used once, and expire automatically.', 'competitor-price-stock-monitor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Secure bridge', 'competitor-price-stock-monitor' ); ?></th>
					<td>
						<?php if ( ! empty( $wc_competitor_monitor_settings['pro_site_id'] ) ) : ?>
							<p><code><?php echo esc_html( (string) $wc_competitor_monitor_settings['bridge_auth_version'] ); ?></code> / <code><?php echo esc_html( (string) $wc_competitor_monitor_settings['pro_site_id'] ); ?></code></p>
							<p class="description">
								<?php
								printf(
									/* translators: 1: plugin-to-SaaS secret preview, 2: SaaS-to-plugin secret preview. */
									esc_html__( 'Bridge secrets are stored encrypted and are not displayed again. Previews: plugin to SaaS %1$s, SaaS to plugin %2$s.', 'competitor-price-stock-monitor' ),
									esc_html( (string) ( $wc_competitor_monitor_settings['pro_plugin_to_saas_secret_preview'] ?? '' ) ),
									esc_html( (string) ( $wc_competitor_monitor_settings['pro_saas_to_plugin_secret_preview'] ?? '' ) )
								);
								?>
							</p>
						<?php else : ?>
							<code><?php esc_html_e( 'Not issued yet', 'competitor-price-stock-monitor' ); ?></code>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<p class="submit"><button type="submit" class="button button-primary"<?php disabled( $wc_competitor_monitor_pro_is_active, true ); ?>><?php esc_html_e( 'Activate Pro bridge', 'competitor-price-stock-monitor' ); ?></button></p>
		</form>
		<?php if ( $wc_competitor_monitor_pro_is_active ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wc_competitor_monitor_rotate_bridge">
				<?php wp_nonce_field( 'wc_competitor_monitor_rotate_bridge' ); ?>
				<p><button type="submit" class="button"><?php esc_html_e( 'Rotate Pro bridge keys', 'competitor-price-stock-monitor' ); ?></button></p>
			</form>
		<?php endif; ?>

		<?php
		$wc_competitor_monitor_step_license  = $wc_competitor_monitor_pro_is_active;
		$wc_competitor_monitor_step_bridge   = ! empty( $wc_competitor_monitor_settings['pro_site_id'] );
		$wc_competitor_monitor_step_cron     = (bool) $wc_competitor_monitor_cron_event;
		?>
		<h3 style="margin:16px 0 8px"><?php esc_html_e( 'Setup status', 'competitor-price-stock-monitor' ); ?></h3>
		<ul class="wccm-setup-checklist">
			<li>
				<span class="<?php echo $wc_competitor_monitor_step_license ? 'wccm-setup-check' : 'wccm-setup-pending'; ?>"><?php echo $wc_competitor_monitor_step_license ? '✓' : '✗'; ?></span>
				<?php esc_html_e( 'Pro license active', 'competitor-price-stock-monitor' ); ?>
				<?php if ( ! $wc_competitor_monitor_step_license ) : ?>
					&mdash; <span class="description"><?php esc_html_e( 'Enter a registration key above and click Activate Pro bridge.', 'competitor-price-stock-monitor' ); ?></span>
				<?php endif; ?>
			</li>
			<li>
				<span class="<?php echo $wc_competitor_monitor_step_bridge ? 'wccm-setup-check' : 'wccm-setup-pending'; ?>"><?php echo $wc_competitor_monitor_step_bridge ? '✓' : '✗'; ?></span>
				<?php esc_html_e( 'Secure bridge connected', 'competitor-price-stock-monitor' ); ?>
				<?php if ( ! $wc_competitor_monitor_step_bridge ) : ?>
					&mdash; <span class="description"><?php esc_html_e( 'Bridge is issued when you activate a valid license key.', 'competitor-price-stock-monitor' ); ?></span>
				<?php endif; ?>
			</li>
			<li>
				<span class="<?php echo $wc_competitor_monitor_step_cron ? 'wccm-setup-check' : 'wccm-setup-pending'; ?>"><?php echo $wc_competitor_monitor_step_cron ? '✓' : '✗'; ?></span>
				<?php esc_html_e( 'Price checks scheduled', 'competitor-price-stock-monitor' ); ?>
				<?php if ( ! $wc_competitor_monitor_step_cron ) : ?>
					&mdash; <span class="description"><?php esc_html_e( 'Save settings to reschedule the price check cron.', 'competitor-price-stock-monitor' ); ?></span>
				<?php endif; ?>
			</li>
		</ul>
	</section>

</div>
