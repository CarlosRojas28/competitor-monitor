<?php
/**
 * Alerts view.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wccm-wrap">
	<h1><?php esc_html_e( 'Alerts', 'competitor-price-stock-monitor' ); ?></h1>

	<section class="wccm-panel">
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Type', 'competitor-price-stock-monitor' ); ?></th>
					<th><?php esc_html_e( 'Competitor product', 'competitor-price-stock-monitor' ); ?></th>
					<th><?php esc_html_e( 'WooCommerce product', 'competitor-price-stock-monitor' ); ?></th>
					<th><?php esc_html_e( 'Event', 'competitor-price-stock-monitor' ); ?></th>
					<th><?php esc_html_e( 'Severity', 'competitor-price-stock-monitor' ); ?></th>
					<th><?php esc_html_e( 'Created', 'competitor-price-stock-monitor' ); ?></th>
					<th><?php esc_html_e( 'Status', 'competitor-price-stock-monitor' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'competitor-price-stock-monitor' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $wc_competitor_monitor_alerts ) ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'No alerts yet.', 'competitor-price-stock-monitor' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $wc_competitor_monitor_alerts as $wc_competitor_monitor_alert ) : ?>
						<?php $wc_competitor_monitor_alert_context = $this->alert_context( $wc_competitor_monitor_alert ); ?>
						<tr>
							<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $wc_competitor_monitor_alert->alert_type ) ) ); ?></td>
							<td>
								<strong><?php echo esc_html( wp_html_excerpt( $wc_competitor_monitor_alert_context['competitor_product'], 90, '...' ) ); ?></strong><br>
								<span class="description"><?php echo esc_html( $wc_competitor_monitor_alert_context['competitor_name'] ); ?></span>
								<?php if ( ! empty( $wc_competitor_monitor_alert_context['competitor_url'] ) ) : ?>
									<br><a href="<?php echo esc_url( $wc_competitor_monitor_alert_context['competitor_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Visit', 'competitor-price-stock-monitor' ); ?></a>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $wc_competitor_monitor_alert_context['woocommerce_product'] ); ?></td>
							<td><?php echo esc_html( $this->alert_display_message( $wc_competitor_monitor_alert ) ); ?></td>
							<td><span class="wccm-alert-severity wccm-alert-<?php echo esc_attr( $wc_competitor_monitor_alert->severity ); ?>"><?php echo esc_html( $wc_competitor_monitor_alert->severity ); ?></span></td>
							<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $wc_competitor_monitor_alert->created_at ) ); ?></td>
							<td><?php echo esc_html( empty( $wc_competitor_monitor_alert->is_read ) ? __( 'Unread', 'competitor-price-stock-monitor' ) : __( 'Read', 'competitor-price-stock-monitor' ) ); ?></td>
							<td class="wccm-row-actions">
								<?php if ( empty( $wc_competitor_monitor_alert->is_read ) ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="wc_competitor_monitor_mark_alert_read">
										<input type="hidden" name="alert_id" value="<?php echo esc_attr( absint( $wc_competitor_monitor_alert->id ) ); ?>">
										<?php wp_nonce_field( 'wc_competitor_monitor_mark_alert_read_' . absint( $wc_competitor_monitor_alert->id ) ); ?>
										<button type="submit" class="button button-small"><?php esc_html_e( 'Mark read', 'competitor-price-stock-monitor' ); ?></button>
									</form>
								<?php endif; ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wccm-delete-form">
									<input type="hidden" name="action" value="wc_competitor_monitor_delete_alert">
									<input type="hidden" name="alert_id" value="<?php echo esc_attr( absint( $wc_competitor_monitor_alert->id ) ); ?>">
									<?php wp_nonce_field( 'wc_competitor_monitor_delete_alert_' . absint( $wc_competitor_monitor_alert->id ) ); ?>
									<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'competitor-price-stock-monitor' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</section>
</div>
