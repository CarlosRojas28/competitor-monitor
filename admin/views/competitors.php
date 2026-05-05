<?php
/**
 * Competitor URLs view.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wccm-wrap">
	<h1><?php esc_html_e( 'Competitor URLs', 'competitor-price-stock-monitor' ); ?></h1>

	<div class="wccm-actions">
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=competitor-price-stock-monitor-products' ) ); ?>"><?php esc_html_e( 'Add competitor URL', 'competitor-price-stock-monitor' ); ?></a>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:8px;">
			<input type="hidden" name="action" value="wc_competitor_monitor_sync_mappings">
			<?php wp_nonce_field( 'wc_competitor_monitor_sync_mappings' ); ?>
			<button type="submit" class="button"><?php esc_html_e( 'Sync with SaaS', 'competitor-price-stock-monitor' ); ?></button>
		</form>
	</div>

	<?php if ( ! empty( $wc_competitor_monitor_settings['last_mapping_sync_at'] ) || ! empty( $wc_competitor_monitor_settings['last_mapping_sync_message'] ) ) : ?>
		<section class="wccm-panel">
			<p>
				<strong><?php esc_html_e( 'Last SaaS sync:', 'competitor-price-stock-monitor' ); ?></strong>
				<?php
				echo ! empty( $wc_competitor_monitor_settings['last_mapping_sync_at'] )
					? esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $wc_competitor_monitor_settings['last_mapping_sync_at'] ) )
					: esc_html__( 'Not synced yet', 'competitor-price-stock-monitor' );
				?>
				<?php if ( ! empty( $wc_competitor_monitor_settings['last_mapping_sync_status'] ) ) : ?>
					<span class="wccm-status <?php echo 'success' === (string) $wc_competitor_monitor_settings['last_mapping_sync_status'] ? 'is-active' : 'is-inactive'; ?>">
						<?php echo esc_html( (string) $wc_competitor_monitor_settings['last_mapping_sync_status'] ); ?>
					</span>
				<?php endif; ?>
			</p>
			<?php if ( ! empty( $wc_competitor_monitor_settings['last_mapping_sync_message'] ) ) : ?>
				<p class="description"><?php echo esc_html( (string) $wc_competitor_monitor_settings['last_mapping_sync_message'] ); ?></p>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<section class="wccm-panel">
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Competitor', 'competitor-price-stock-monitor' ); ?></th>
					<th><?php esc_html_e( 'URL', 'competitor-price-stock-monitor' ); ?></th>
					<th><?php esc_html_e( 'Product', 'competitor-price-stock-monitor' ); ?></th>
					<th><?php esc_html_e( 'Selectors', 'competitor-price-stock-monitor' ); ?></th>
					<th><?php esc_html_e( 'Last price', 'competitor-price-stock-monitor' ); ?></th>
					<th><?php esc_html_e( 'Stock', 'competitor-price-stock-monitor' ); ?></th>
					<th><?php esc_html_e( 'Active', 'competitor-price-stock-monitor' ); ?></th>
					<th><?php esc_html_e( 'SaaS sync', 'competitor-price-stock-monitor' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $wc_competitor_monitor_mappings ) ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'No competitor URLs have been added yet.', 'competitor-price-stock-monitor' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $wc_competitor_monitor_mappings as $wc_competitor_monitor_mapping ) : ?>
						<tr>
							<td><?php echo esc_html( $wc_competitor_monitor_mapping->competitor_name ); ?></td>
							<td><a href="<?php echo esc_url( $wc_competitor_monitor_mapping->competitor_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $wc_competitor_monitor_mapping->competitor_url ); ?></a></td>
							<td><?php echo esc_html( $this->product_title( absint( $wc_competitor_monitor_mapping->product_id ) ) ); ?></td>
							<td>
								<?php if ( $wc_competitor_monitor_mapping->price_selector ) : ?>
									<code><?php echo esc_html( $wc_competitor_monitor_mapping->price_selector ); ?></code>
								<?php endif; ?>
								<?php if ( $wc_competitor_monitor_mapping->stock_selector ) : ?>
									<code><?php echo esc_html( $wc_competitor_monitor_mapping->stock_selector ); ?></code>
								<?php endif; ?>
								<?php if ( ! $wc_competitor_monitor_mapping->price_selector && ! $wc_competitor_monitor_mapping->stock_selector ) : ?>
									&mdash;
								<?php endif; ?>
							</td>
							<td><?php echo $this->format_price( null !== $wc_competitor_monitor_mapping->last_price ? (float) $wc_competitor_monitor_mapping->last_price : null ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td><span class="wccm-badge wccm-badge-<?php echo esc_attr( $wc_competitor_monitor_mapping->last_stock_status ?: 'unknown' ); ?>"><?php echo esc_html( $wc_competitor_monitor_mapping->last_stock_status ?: __( 'unknown', 'competitor-price-stock-monitor' ) ); ?></span></td>
							<td><?php echo esc_html( empty( $wc_competitor_monitor_mapping->active ) ? __( 'No', 'competitor-price-stock-monitor' ) : __( 'Yes', 'competitor-price-stock-monitor' ) ); ?></td>
							<td>
								<span class="wccm-status <?php echo 'synced' === (string) ( $wc_competitor_monitor_mapping->sync_status ?? '' ) ? 'is-active' : 'is-inactive'; ?>">
									<?php echo esc_html( (string) ( $wc_competitor_monitor_mapping->sync_status ?? __( 'pending', 'competitor-price-stock-monitor' ) ) ); ?>
								</span>
								<?php if ( ! empty( $wc_competitor_monitor_mapping->last_synced_at ) ) : ?>
									<br><small><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $wc_competitor_monitor_mapping->last_synced_at ) ); ?></small>
								<?php endif; ?>
								<?php if ( ! empty( $wc_competitor_monitor_mapping->sync_error ) ) : ?>
									<br><small><?php echo esc_html( (string) $wc_competitor_monitor_mapping->sync_error ); ?></small>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</section>
</div>
