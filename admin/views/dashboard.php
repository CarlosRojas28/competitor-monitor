<?php
/**
 * Dashboard view.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wc_competitor_monitor_pro_is_active = ! empty( $wc_competitor_monitor_settings['pro_enabled'] ) && 'active' === (string) ( $wc_competitor_monitor_settings['pro_license_status'] ?? '' );
$wc_competitor_monitor_saas_base_url = untrailingslashit( esc_url_raw( (string) ( $wc_competitor_monitor_settings['pro_saas_url'] ?? 'http://127.0.0.1:8788' ) ) );
if ( '' === $wc_competitor_monitor_saas_base_url ) {
	$wc_competitor_monitor_saas_base_url = 'http://127.0.0.1:8788';
}
$wc_competitor_monitor_upgrade_url = $wc_competitor_monitor_saas_base_url . '/app/checkout';
?>
<div class="wrap wccm-wrap">
	<h1><?php esc_html_e( 'Competitor Monitor', 'competitor-price-stock-monitor' ); ?></h1>

	<?php if ( ! $wc_competitor_monitor_pro_is_active ) : ?>
		<div class="notice notice-info">
			<p>
				<strong><?php esc_html_e( 'Get AI competitor discovery and automatic repricing with Pro', 'competitor-price-stock-monitor' ); ?></strong> &mdash;
				<?php esc_html_e( 'Find competitors automatically, apply safe price changes and measure the extra profit.', 'competitor-price-stock-monitor' ); ?>
				<a class="button button-small" style="margin-left:8px" href="<?php echo esc_url( $wc_competitor_monitor_upgrade_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade to Pro', 'competitor-price-stock-monitor' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<div class="wccm-actions">
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=competitor-price-stock-monitor-products' ) ); ?>">
			<?php esc_html_e( 'Monitor a new competitor', 'competitor-price-stock-monitor' ); ?>
		</a>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:8px">
			<input type="hidden" name="action" value="wc_competitor_monitor_run_check">
			<input type="hidden" name="mapping_id" value="0">
			<?php wp_nonce_field( 'wc_competitor_monitor_run_check_0' ); ?>
			<button type="submit" class="button"><?php esc_html_e( 'Check prices now', 'competitor-price-stock-monitor' ); ?></button>
		</form>
	</div>

	<?php if ( $wc_competitor_monitor_pro_is_active ) : ?>
		<div class="wccm-card-grid">
			<div class="wccm-card wccm-card-feature">
				<span class="wccm-card-label"><?php esc_html_e( 'Extra profit from smart pricing (last 30 days)', 'competitor-price-stock-monitor' ); ?></span>
				<strong><?php echo $this->format_money( (float) ( $wc_competitor_monitor_profit_impact['attributed_gross_profit'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
				<small><?php esc_html_e( 'Calculated from WooCommerce orders placed after automatic price adjustments. Requires product costs in WooCommerce.', 'competitor-price-stock-monitor' ); ?></small>
			</div>
			<div class="wccm-card">
				<span class="wccm-card-label"><?php esc_html_e( 'Products repriced', 'competitor-price-stock-monitor' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( (int) ( $wc_competitor_monitor_profit_impact['adjusted_products'] ?? 0 ) ) ); ?></strong>
			</div>
			<div class="wccm-card">
				<span class="wccm-card-label"><?php esc_html_e( 'Units sold after repricing', 'competitor-price-stock-monitor' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( (float) ( $wc_competitor_monitor_profit_impact['units_sold_after_adjustment'] ?? 0 ), 0 ) ); ?></strong>
			</div>
			<?php if ( (float) ( $wc_competitor_monitor_profit_impact['revenue_uplift_without_cost'] ?? 0 ) > 0 ) : ?>
				<div class="wccm-card">
					<span class="wccm-card-label"><?php esc_html_e( 'Extra revenue (add product costs for full tracking)', 'competitor-price-stock-monitor' ); ?></span>
					<strong><?php echo $this->format_money( (float) ( $wc_competitor_monitor_profit_impact['revenue_uplift_without_cost'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="wccm-card-grid">
		<div class="wccm-card">
			<span class="wccm-card-label"><?php esc_html_e( 'Products monitored', 'competitor-price-stock-monitor' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( $wc_competitor_monitor_stats['monitored_products'] ) ); ?></strong>
		</div>
		<div class="wccm-card">
			<span class="wccm-card-label"><?php esc_html_e( 'Competitors tracked', 'competitor-price-stock-monitor' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( $wc_competitor_monitor_stats['active_urls'] ) ); ?></strong>
		</div>
		<div class="wccm-card">
			<span class="wccm-card-label"><?php esc_html_e( 'Unread alerts', 'competitor-price-stock-monitor' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( $wc_competitor_monitor_stats['unread_alerts'] ) ); ?></strong>
		</div>
		<div class="wccm-card">
			<span class="wccm-card-label"><?php esc_html_e( 'We charge more', 'competitor-price-stock-monitor' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( $wc_competitor_monitor_stats['more_expensive'] ) ); ?></strong>
		</div>
		<div class="wccm-card">
			<span class="wccm-card-label"><?php esc_html_e( 'We charge less', 'competitor-price-stock-monitor' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( $wc_competitor_monitor_stats['cheaper'] ) ); ?></strong>
		</div>
		<div class="wccm-card">
			<span class="wccm-card-label"><?php esc_html_e( 'Competitor out of stock', 'competitor-price-stock-monitor' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( $wc_competitor_monitor_stats['out_of_stock'] ) ); ?></strong>
		</div>
	</div>

	<div class="wccm-two-column">
		<section class="wccm-panel">
			<h2><?php esc_html_e( 'Recent price movements', 'competitor-price-stock-monitor' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Competitor price', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Your price', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Difference', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Their stock', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Checked', 'competitor-price-stock-monitor' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $wc_competitor_monitor_history ) ) : ?>
						<tr>
							<td colspan="6">
								<?php esc_html_e( 'No price checks yet. Click "Check prices now" above or wait for the next automatic check.', 'competitor-price-stock-monitor' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $wc_competitor_monitor_history as $wc_competitor_monitor_history_row ) : ?>
							<tr>
								<td><?php echo esc_html( $this->product_title( absint( $wc_competitor_monitor_history_row->product_id ) ) ); ?></td>
								<td><?php echo $this->format_price( null !== $wc_competitor_monitor_history_row->competitor_price ? (float) $wc_competitor_monitor_history_row->competitor_price : null ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<td><?php echo $this->format_price( null !== $wc_competitor_monitor_history_row->our_price ? (float) $wc_competitor_monitor_history_row->our_price : null ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<td>
									<?php if ( null !== $wc_competitor_monitor_history_row->difference_percentage ) : ?>
										<?php echo esc_html( number_format_i18n( (float) $wc_competitor_monitor_history_row->difference_percentage, 2 ) ); ?>%
									<?php else : ?>
										&mdash;
									<?php endif; ?>
								</td>
								<td><span class="wccm-badge wccm-badge-<?php echo esc_attr( $wc_competitor_monitor_history_row->competitor_stock_status ?: 'unknown' ); ?>"><?php echo esc_html( $wc_competitor_monitor_history_row->competitor_stock_status ?: __( 'unknown', 'competitor-price-stock-monitor' ) ); ?></span></td>
								<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $wc_competitor_monitor_history_row->checked_at ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</section>

		<section class="wccm-panel">
			<h2><?php esc_html_e( 'Alerts', 'competitor-price-stock-monitor' ); ?></h2>
			<?php if ( empty( $wc_competitor_monitor_alerts ) ) : ?>
				<p class="description"><?php esc_html_e( 'No unread alerts. You\'ll be notified here when a competitor changes price or goes out of stock.', 'competitor-price-stock-monitor' ); ?></p>
			<?php else : ?>
				<ul class="wccm-alert-list">
					<?php foreach ( $wc_competitor_monitor_alerts as $wc_competitor_monitor_alert ) : ?>
						<?php $wc_competitor_monitor_alert_context = $this->alert_context( $wc_competitor_monitor_alert ); ?>
						<li class="wccm-alert wccm-alert-<?php echo esc_attr( $wc_competitor_monitor_alert->severity ); ?>">
							<strong><?php echo esc_html( $this->alert_display_message( $wc_competitor_monitor_alert ) ); ?></strong>
							<small>
								<?php echo esc_html( $wc_competitor_monitor_alert_context['woocommerce_product'] ); ?>
								&mdash;
								<?php echo esc_html( $wc_competitor_monitor_alert_context['competitor_name'] ); ?>
							</small>
							<small><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $wc_competitor_monitor_alert->created_at ) ); ?></small>
						</li>
					<?php endforeach; ?>
				</ul>
				<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=competitor-price-stock-monitor-alerts' ) ); ?>"><?php esc_html_e( 'View all alerts', 'competitor-price-stock-monitor' ); ?></a></p>
			<?php endif; ?>
		</section>
	</div>

	<?php if ( ! empty( $wc_competitor_monitor_recommendations ) ) : ?>
		<section class="wccm-panel">
			<h2><?php esc_html_e( 'Pricing suggestions', 'competitor-price-stock-monitor' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Competitor', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Your price', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Their price', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Suggestion', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Suggested price', 'competitor-price-stock-monitor' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $wc_competitor_monitor_recommendations as $wc_competitor_monitor_recommendation ) : ?>
						<tr>
							<td><?php echo esc_html( $this->product_title( absint( $wc_competitor_monitor_recommendation['product_id'] ) ) ); ?></td>
							<td><?php echo esc_html( $wc_competitor_monitor_recommendation['competitor_name'] ); ?></td>
							<td><?php echo $this->format_price( (float) $wc_competitor_monitor_recommendation['current_price'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td><?php echo $this->format_price( (float) $wc_competitor_monitor_recommendation['competitor_price'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td><?php echo esc_html( $wc_competitor_monitor_recommendation['message'] ); ?></td>
							<td>
								<?php
								echo isset( $wc_competitor_monitor_recommendation['recommended_price'] ) && null !== $wc_competitor_monitor_recommendation['recommended_price']
									? $this->format_price( (float) $wc_competitor_monitor_recommendation['recommended_price'] ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									: '&mdash;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>
	<?php endif; ?>
</div>
