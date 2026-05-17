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
$wc_competitor_monitor_auto_price_mode           = sanitize_key( (string) ( $wc_competitor_monitor_settings['auto_price_adjustment_mode'] ?? 'disabled' ) );
if ( ! in_array( $wc_competitor_monitor_auto_price_mode, array( 'disabled', 'enabled' ), true ) ) {
	$wc_competitor_monitor_auto_price_mode = 'disabled';
}
$wc_competitor_monitor_restore_mode = sanitize_key( (string) ( $wc_competitor_monitor_settings['original_price_restore_mode'] ?? 'disabled' ) );
if ( ! in_array( $wc_competitor_monitor_restore_mode, array( 'disabled', 'enabled' ), true ) ) {
	$wc_competitor_monitor_restore_mode = 'disabled';
}
$wc_competitor_monitor_pro_is_active  = ! empty( $wc_competitor_monitor_settings['pro_enabled'] ) && 'active' === (string) ( $wc_competitor_monitor_settings['pro_license_status'] ?? '' );
$wc_competitor_monitor_key_preview   = (string) ( $wc_competitor_monitor_settings['pro_license_key_preview'] ?? '' );
$wc_competitor_monitor_key_last4     = $wc_competitor_monitor_key_preview !== '' ? substr( $wc_competitor_monitor_key_preview, -4 ) : '';
$wc_competitor_monitor_show_masked   = $wc_competitor_monitor_pro_is_active && '' !== $wc_competitor_monitor_key_last4;
$wc_competitor_monitor_saas_base_url = untrailingslashit( esc_url_raw( (string) ( $wc_competitor_monitor_settings['pro_saas_url'] ?? 'https://competitor-monitor-pro-production.up.railway.app' ) ) );
if ( '' === $wc_competitor_monitor_saas_base_url ) {
	$wc_competitor_monitor_saas_base_url = 'https://competitor-monitor-pro-production.up.railway.app';
}
$wc_competitor_monitor_upgrade_url          = $wc_competitor_monitor_saas_base_url . '/pricing';
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
	</section>

	<section class="wccm-panel wccm-pro-automation-panel">
		<div class="wccm-panel-heading">
			<div>
				<h2><?php esc_html_e( 'Pro automatic price updates', 'competitor-price-stock-monitor' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Allow the plugin to apply the recommended WooCommerce price after a competitor check. Recommendations still respect product cost and minimum margin rules.', 'competitor-price-stock-monitor' ); ?></p>
			</div>
			<span class="wccm-status <?php echo 'enabled' === $wc_competitor_monitor_auto_price_mode ? 'is-active' : 'is-inactive'; ?>">
				<?php echo esc_html( 'enabled' === $wc_competitor_monitor_auto_price_mode ? __( 'Automatic updates enabled', 'competitor-price-stock-monitor' ) : __( 'Automatic updates disabled', 'competitor-price-stock-monitor' ) ); ?>
			</span>
		</div>

		<?php if ( ! $wc_competitor_monitor_pro_is_active ) : ?>
			<div class="notice notice-info inline">
				<p>
					<strong><?php esc_html_e( 'Pro feature: automatic price updates require a Pro license.', 'competitor-price-stock-monitor' ); ?></strong><br>
					<?php esc_html_e( 'With a free license you can review price suggestions and apply them manually from the competitor list.', 'competitor-price-stock-monitor' ); ?>
					<a href="<?php echo esc_url( $wc_competitor_monitor_upgrade_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade to Pro', 'competitor-price-stock-monitor' ); ?></a>
				</p>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wccm-auto-pricing-form">
			<input type="hidden" name="action" value="wc_competitor_monitor_save_auto_pricing">
			<?php wp_nonce_field( 'wc_competitor_monitor_save_auto_pricing' ); ?>
			<input type="hidden" name="auto_price_adjustment_mode" value="disabled">
			<label class="wccm-toggle-row">
				<input type="checkbox" name="auto_price_adjustment_mode" value="enabled" <?php checked( $wc_competitor_monitor_auto_price_mode, 'enabled' ); ?><?php disabled( $wc_competitor_monitor_pro_is_active, false ); ?>>
				<span>
					<strong><?php esc_html_e( 'Apply recommended prices automatically and notify me', 'competitor-price-stock-monitor' ); ?></strong>
					<small><?php esc_html_e( 'When enabled globally, products set to "Use global setting" can be updated after checks. Each WooCommerce product can override this in the product edit screen or Product Mapping.', 'competitor-price-stock-monitor' ); ?></small>
				</span>
			</label>
			<div class="wccm-pro-automation-notes">
				<ul>
					<li><?php esc_html_e( 'The plugin does not apply a recommendation when it would break the configured minimum margin.', 'competitor-price-stock-monitor' ); ?></li>
					<li><?php esc_html_e( 'Every automatic price change creates an internal alert. Email is sent when Email alerts are enabled below.', 'competitor-price-stock-monitor' ); ?></li>
					<li><?php esc_html_e( 'No prices are changed when automatic updates are disabled globally or disabled for the individual product.', 'competitor-price-stock-monitor' ); ?></li>
					<li><?php esc_html_e( 'Attributed gross profit is calculated from completed and processing WooCommerce orders during the 30 days after each automatic change. Product cost is required for events to count as gross profit; otherwise the amount is shown separately as revenue uplift without cost data.', 'competitor-price-stock-monitor' ); ?></li>
				</ul>
			</div>
			<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save automatic pricing', 'competitor-price-stock-monitor' ); ?></button></p>
		</form>
	</section>

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
					<th scope="row"><label for="wccm_auto_price_adjustment_mode"><?php esc_html_e( 'Apply suggested prices automatically (Pro)', 'competitor-price-stock-monitor' ); ?></label></th>
					<td>
						<select id="wccm_auto_price_adjustment_mode" name="auto_price_adjustment_mode">
							<option value="disabled" <?php selected( $wc_competitor_monitor_auto_price_mode, 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'competitor-price-stock-monitor' ); ?></option>
							<option value="enabled" <?php selected( $wc_competitor_monitor_auto_price_mode, 'enabled' ); ?><?php echo $wc_competitor_monitor_pro_is_active ? '' : ' disabled'; ?>><?php esc_html_e( 'Enabled: apply suggested prices and notify me (Pro)', 'competitor-price-stock-monitor' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'When enabled and the Pro license is active, checks can update WooCommerce product prices to the current suggestion. Every automatic change creates an internal alert and sends email when email alerts are enabled. Individual products can override this in the product edit screen or Product Mapping.', 'competitor-price-stock-monitor' ); ?></p>
						<?php if ( ! $wc_competitor_monitor_pro_is_active ) : ?>
							<p class="description">
								<a href="<?php echo esc_url( $wc_competitor_monitor_upgrade_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade to Pro to enable automatic price updates.', 'competitor-price-stock-monitor' ); ?></a>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Automatic pricing kill switch', 'competitor-price-stock-monitor' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="auto_price_kill_switch" value="1" <?php checked( (int) ( $wc_competitor_monitor_settings['auto_price_kill_switch'] ?? 0 ), 1 ); ?>>
							<?php esc_html_e( 'Immediately block every automatic price change', 'competitor-price-stock-monitor' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Use this emergency control if you suspect bad competitor data, compromised credentials, or unexpected pricing behavior. Recommendations and alerts still work.', 'competitor-price-stock-monitor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wccm_original_price_restore_mode"><?php esc_html_e( 'Allow original price restore (Pro)', 'competitor-price-stock-monitor' ); ?></label></th>
					<td>
						<select id="wccm_original_price_restore_mode" name="original_price_restore_mode">
							<option value="disabled" <?php selected( $wc_competitor_monitor_restore_mode, 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'competitor-price-stock-monitor' ); ?></option>
							<option value="enabled" <?php selected( $wc_competitor_monitor_restore_mode, 'enabled' ); ?><?php echo $wc_competitor_monitor_pro_is_active ? '' : ' disabled'; ?>><?php esc_html_e( 'Enabled: allow manual restore when still competitive (Pro)', 'competitor-price-stock-monitor' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'When enabled and the Pro license is active, users can manually restore the captured original customer price from the WooCommerce product edit screen. Restore is blocked when the original price would be above the cheapest in-stock competitor.', 'competitor-price-stock-monitor' ); ?></p>
						<?php if ( ! $wc_competitor_monitor_pro_is_active ) : ?>
							<p class="description">
								<a href="<?php echo esc_url( $wc_competitor_monitor_upgrade_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade to Pro to enable original price restore.', 'competitor-price-stock-monitor' ); ?></a>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wccm_suggested_increase_limit_mode"><?php esc_html_e( 'Suggested price increase limit', 'competitor-price-stock-monitor' ); ?></label></th>
					<td>
						<select id="wccm_suggested_increase_limit_mode" name="suggested_increase_limit_mode">
							<option value="percent" <?php selected( $wc_competitor_monitor_increase_limit_mode, 'percent' ); ?>><?php esc_html_e( 'Limit by percentage', 'competitor-price-stock-monitor' ); ?></option>
							<option value="none" <?php selected( $wc_competitor_monitor_increase_limit_mode, 'none' ); ?>><?php esc_html_e( 'No limit', 'competitor-price-stock-monitor' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Controls how far recommendations may raise your current WooCommerce price when you are much cheaper than a competitor. Individual mappings can override this.', 'competitor-price-stock-monitor' ); ?></p>
					</td>
				</tr>
				<tr data-wccm-suggested-increase-percentage<?php echo 'none' === $wc_competitor_monitor_increase_limit_mode ? ' hidden' : ''; ?>>
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
					<th scope="row"><?php esc_html_e( 'Pro features', 'competitor-price-stock-monitor' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="pro_enabled" value="1" <?php checked( (int) $wc_competitor_monitor_settings['pro_enabled'], 1 ); ?><?php disabled( $wc_competitor_monitor_pro_is_active, false ); ?>>
							<?php esc_html_e( 'Enable Pro cloud features', 'competitor-price-stock-monitor' ); ?>
						</label>
						<?php if ( ! $wc_competitor_monitor_pro_is_active ) : ?>
							<p class="description"><?php esc_html_e( 'Activate a Pro license above to enable this option.', 'competitor-price-stock-monitor' ); ?></p>
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
</div>
