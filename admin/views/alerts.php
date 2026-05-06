<?php
/**
 * Alerts view.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wc_competitor_monitor_alert_type_labels = array(
	'price_drop'          => __( 'Price drop', 'competitor-price-stock-monitor' ),
	'price_increase'      => __( 'Price increase', 'competitor-price-stock-monitor' ),
	'out_of_stock'        => __( 'Out of stock', 'competitor-price-stock-monitor' ),
	'back_in_stock'       => __( 'Back in stock', 'competitor-price-stock-monitor' ),
	'price_applied'       => __( 'Price updated', 'competitor-price-stock-monitor' ),
	'price_blocked'       => __( 'Update blocked (margin)', 'competitor-price-stock-monitor' ),
	'unknown'             => __( 'Alert', 'competitor-price-stock-monitor' ),
);
?>
<div class="wrap wccm-wrap">
	<h1><?php esc_html_e( 'Alerts', 'competitor-price-stock-monitor' ); ?></h1>

	<section class="wccm-panel">
		<?php if ( empty( $wc_competitor_monitor_alerts ) ) : ?>
			<p class="description"><?php esc_html_e( 'No alerts yet. You\'ll see notifications here when competitors change price, go out of stock, or when automatic repricing is applied or blocked.', 'competitor-price-stock-monitor' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'What happened', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Your product', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Competitor', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Date', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Status', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'competitor-price-stock-monitor' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $wc_competitor_monitor_alerts as $wc_competitor_monitor_alert ) : ?>
						<?php $wc_competitor_monitor_alert_context = $this->alert_context( $wc_competitor_monitor_alert ); ?>
						<tr>
							<td>
								<strong>
									<?php
									$wc_competitor_monitor_alert_key = sanitize_key( (string) $wc_competitor_monitor_alert->alert_type );
									echo esc_html( $wc_competitor_monitor_alert_type_labels[ $wc_competitor_monitor_alert_key ] ?? ucwords( str_replace( '_', ' ', $wc_competitor_monitor_alert_key ) ) );
									?>
								</strong><br>
								<span class="description"><?php echo esc_html( $this->alert_display_message( $wc_competitor_monitor_alert ) ); ?></span>
							</td>
							<td><?php echo esc_html( $wc_competitor_monitor_alert_context['woocommerce_product'] ); ?></td>
							<td>
								<strong><?php echo esc_html( wp_html_excerpt( $wc_competitor_monitor_alert_context['competitor_product'], 70, '...' ) ); ?></strong><br>
								<span class="description"><?php echo esc_html( $wc_competitor_monitor_alert_context['competitor_name'] ); ?></span>
								<?php if ( ! empty( $wc_competitor_monitor_alert_context['competitor_url'] ) ) : ?>
									<br><a href="<?php echo esc_url( $wc_competitor_monitor_alert_context['competitor_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'competitor-price-stock-monitor' ); ?></a>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $wc_competitor_monitor_alert->created_at ) ); ?></td>
							<td>
								<?php if ( empty( $wc_competitor_monitor_alert->is_read ) ) : ?>
									<span class="wccm-badge wccm-badge-<?php echo esc_attr( $wc_competitor_monitor_alert->severity ); ?>"><?php esc_html_e( 'Unread', 'competitor-price-stock-monitor' ); ?></span>
								<?php else : ?>
									<span class="wccm-status is-inactive"><?php esc_html_e( 'Read', 'competitor-price-stock-monitor' ); ?></span>
								<?php endif; ?>
							</td>
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
				</tbody>
			</table>
		<?php endif; ?>
	</section>
</div>
