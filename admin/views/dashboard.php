<?php
/**
 * Dashboard view.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wccm-wrap">
	<h1><?php esc_html_e( 'Competitor Monitor', 'competitor-price-stock-monitor' ); ?></h1>

	<div class="wccm-actions">
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=competitor-price-stock-monitor-products' ) ); ?>">
			<?php esc_html_e( 'Add competitor URL', 'competitor-price-stock-monitor' ); ?>
		</a>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wc_competitor_monitor_run_check">
			<input type="hidden" name="mapping_id" value="0">
			<?php wp_nonce_field( 'wc_competitor_monitor_run_check_0' ); ?>
			<button type="submit" class="button"><?php esc_html_e( 'Run check now', 'competitor-price-stock-monitor' ); ?></button>
		</form>
	</div>

	<div class="wccm-card-grid">
		<div class="wccm-card wccm-card-feature">
			<span class="wccm-card-label"><?php esc_html_e( 'Attributed gross profit from Pro pricing', 'competitor-price-stock-monitor' ); ?></span>
			<strong><?php echo $this->format_money( (float) ( $wc_competitor_monitor_profit_impact['attributed_gross_profit'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
			<small><?php esc_html_e( 'Real WooCommerce orders in the 30 days after automatic Pro price changes. Requires product cost data.', 'competitor-price-stock-monitor' ); ?></small>
		</div>
		<div class="wccm-card">
			<span class="wccm-card-label"><?php esc_html_e( 'Products adjusted', 'competitor-price-stock-monitor' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( (int) ( $wc_competitor_monitor_profit_impact['adjusted_products'] ?? 0 ) ) ); ?></strong>
		</div>
		<div class="wccm-card">
			<span class="wccm-card-label"><?php esc_html_e( 'Units sold after adjustment', 'competitor-price-stock-monitor' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( (float) ( $wc_competitor_monitor_profit_impact['units_sold_after_adjustment'] ?? 0 ), 0 ) ); ?></strong>
		</div>
		<div class="wccm-card">
			<span class="wccm-card-label"><?php esc_html_e( 'Revenue uplift without cost data', 'competitor-price-stock-monitor' ); ?></span>
			<strong><?php echo $this->format_money( (float) ( $wc_competitor_monitor_profit_impact['revenue_uplift_without_cost'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
		</div>
		<div class="wccm-card">
			<span class="wccm-card-label"><?php esc_html_e( 'Events missing cost', 'competitor-price-stock-monitor' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( (int) ( $wc_competitor_monitor_profit_impact['missing_cost_events'] ?? 0 ) ) ); ?></strong>
		</div>
	</div>

	<div class="wccm-card-grid">
		<div class="wccm-card">
			<span class="wccm-card-label"><?php esc_html_e( 'Products monitored', 'competitor-price-stock-monitor' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( $wc_competitor_monitor_stats['monitored_products'] ) ); ?></strong>
		</div>
		<div class="wccm-card">
			<span class="wccm-card-label"><?php esc_html_e( 'Active competitor URLs', 'competitor-price-stock-monitor' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( $wc_competitor_monitor_stats['active_urls'] ) ); ?></strong>
		</div>
		<div class="wccm-card">
			<span class="wccm-card-label"><?php esc_html_e( 'Unread alerts', 'competitor-price-stock-monitor' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( $wc_competitor_monitor_stats['unread_alerts'] ) ); ?></strong>
		</div>
		<div class="wccm-card">
			<span class="wccm-card-label"><?php esc_html_e( 'We are more expensive', 'competitor-price-stock-monitor' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( $wc_competitor_monitor_stats['more_expensive'] ) ); ?></strong>
		</div>
		<div class="wccm-card">
			<span class="wccm-card-label"><?php esc_html_e( 'We are cheaper', 'competitor-price-stock-monitor' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( $wc_competitor_monitor_stats['cheaper'] ) ); ?></strong>
		</div>
		<div class="wccm-card">
			<span class="wccm-card-label"><?php esc_html_e( 'Competitor out of stock', 'competitor-price-stock-monitor' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( $wc_competitor_monitor_stats['out_of_stock'] ) ); ?></strong>
		</div>
	</div>

	<div class="wccm-two-column">
		<section class="wccm-panel">
			<h2><?php esc_html_e( 'Latest Changes', 'competitor-price-stock-monitor' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Competitor price', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Our price', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Difference', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Stock', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Checked', 'competitor-price-stock-monitor' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $wc_competitor_monitor_history ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No checks have been recorded yet.', 'competitor-price-stock-monitor' ); ?></td></tr>
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
			<h2><?php esc_html_e( 'Unread Alerts', 'competitor-price-stock-monitor' ); ?></h2>
			<?php if ( empty( $wc_competitor_monitor_alerts ) ) : ?>
				<p><?php esc_html_e( 'No unread alerts.', 'competitor-price-stock-monitor' ); ?></p>
			<?php else : ?>
				<ul class="wccm-alert-list">
					<?php foreach ( $wc_competitor_monitor_alerts as $wc_competitor_monitor_alert ) : ?>
						<?php $wc_competitor_monitor_alert_context = $this->alert_context( $wc_competitor_monitor_alert ); ?>
						<li class="wccm-alert wccm-alert-<?php echo esc_attr( $wc_competitor_monitor_alert->severity ); ?>">
							<strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $wc_competitor_monitor_alert->alert_type ) ) ); ?></strong>
							<span><?php echo esc_html( $this->alert_display_message( $wc_competitor_monitor_alert ) ); ?></span>
							<small>
								<?php
								printf(
									/* translators: 1: competitor product title, 2: WooCommerce product title. */
									esc_html__( 'Competitor product: %1$s. WooCommerce product: %2$s.', 'competitor-price-stock-monitor' ),
									esc_html( $wc_competitor_monitor_alert_context['competitor_product'] ),
									esc_html( $wc_competitor_monitor_alert_context['woocommerce_product'] )
								);
								?>
							</small>
							<small><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $wc_competitor_monitor_alert->created_at ) ); ?></small>
						</li>
					<?php endforeach; ?>
				</ul>
				<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=competitor-price-stock-monitor-alerts' ) ); ?>"><?php esc_html_e( 'View all alerts', 'competitor-price-stock-monitor' ); ?></a></p>
			<?php endif; ?>
		</section>
	</div>

	<section class="wccm-panel">
		<h2><?php esc_html_e( 'Recent Recommendations', 'competitor-price-stock-monitor' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Product', 'competitor-price-stock-monitor' ); ?></th>
					<th><?php esc_html_e( 'Competitor', 'competitor-price-stock-monitor' ); ?></th>
					<th><?php esc_html_e( 'Current price', 'competitor-price-stock-monitor' ); ?></th>
					<th><?php esc_html_e( 'Competitor price', 'competitor-price-stock-monitor' ); ?></th>
					<th><?php esc_html_e( 'Recommendation', 'competitor-price-stock-monitor' ); ?></th>
					<th><?php esc_html_e( 'Suggested price', 'competitor-price-stock-monitor' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $wc_competitor_monitor_recommendations ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No recommendations are available yet.', 'competitor-price-stock-monitor' ); ?></td></tr>
				<?php else : ?>
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
				<?php endif; ?>
			</tbody>
		</table>
	</section>
</div>
