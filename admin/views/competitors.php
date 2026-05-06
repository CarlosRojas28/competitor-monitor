<?php
/**
 * Competitor prices view.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wccm-wrap">
	<h1><?php esc_html_e( 'Competitor Prices', 'competitor-price-stock-monitor' ); ?></h1>

	<div class="wccm-actions">
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=competitor-price-stock-monitor-products' ) ); ?>"><?php esc_html_e( 'Add competitor', 'competitor-price-stock-monitor' ); ?></a>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:8px;">
			<input type="hidden" name="action" value="wc_competitor_monitor_sync_mappings">
			<?php wp_nonce_field( 'wc_competitor_monitor_sync_mappings' ); ?>
			<button type="submit" class="button"><?php esc_html_e( 'Sync with cloud', 'competitor-price-stock-monitor' ); ?></button>
		</form>
	</div>

	<?php if ( ! empty( $wc_competitor_monitor_settings['last_mapping_sync_at'] ) || ! empty( $wc_competitor_monitor_settings['last_mapping_sync_message'] ) ) : ?>
		<section class="wccm-panel">
			<p>
				<strong><?php esc_html_e( 'Last cloud sync:', 'competitor-price-stock-monitor' ); ?></strong>
				<?php
				echo ! empty( $wc_competitor_monitor_settings['last_mapping_sync_at'] )
					? esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $wc_competitor_monitor_settings['last_mapping_sync_at'] ) )
					: esc_html__( 'Not synced yet', 'competitor-price-stock-monitor' );
				?>
				<?php if ( ! empty( $wc_competitor_monitor_settings['last_mapping_sync_status'] ) ) : ?>
					<span class="wccm-status <?php echo 'success' === (string) $wc_competitor_monitor_settings['last_mapping_sync_status'] ? 'is-active' : 'is-inactive'; ?>">
						<?php echo esc_html( 'success' === (string) $wc_competitor_monitor_settings['last_mapping_sync_status'] ? __( 'synced', 'competitor-price-stock-monitor' ) : (string) $wc_competitor_monitor_settings['last_mapping_sync_status'] ); ?>
					</span>
				<?php endif; ?>
			</p>
			<?php if ( ! empty( $wc_competitor_monitor_settings['last_mapping_sync_message'] ) ) : ?>
				<p class="description"><?php echo esc_html( (string) $wc_competitor_monitor_settings['last_mapping_sync_message'] ); ?></p>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<section class="wccm-panel">
		<?php if ( empty( $wc_competitor_monitor_mappings ) ) : ?>
			<p><?php esc_html_e( 'No competitors added yet.', 'competitor-price-stock-monitor' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=competitor-price-stock-monitor-products' ) ); ?>"><?php esc_html_e( 'Add your first competitor to start monitoring.', 'competitor-price-stock-monitor' ); ?></a></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Competitor', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Your product', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Their price', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Stock', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Monitoring', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'competitor-price-stock-monitor' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $wc_competitor_monitor_mappings as $wc_competitor_monitor_mapping ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $wc_competitor_monitor_mapping->competitor_name ); ?></strong><br>
								<a href="<?php echo esc_url( $wc_competitor_monitor_mapping->competitor_url ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( (string) wp_parse_url( $wc_competitor_monitor_mapping->competitor_url, PHP_URL_HOST ) ); ?>
								</a>
								<?php if ( 'synced' !== (string) ( $wc_competitor_monitor_mapping->sync_status ?? '' ) && ! empty( $wc_competitor_monitor_mapping->sync_status ) ) : ?>
									<br><span class="wccm-status is-inactive" style="font-size:11px"><?php esc_html_e( 'Sync pending', 'competitor-price-stock-monitor' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $this->product_title( absint( $wc_competitor_monitor_mapping->product_id ) ) ); ?></td>
							<td><?php echo $this->format_price( null !== $wc_competitor_monitor_mapping->last_price ? (float) $wc_competitor_monitor_mapping->last_price : null ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td><span class="wccm-badge wccm-badge-<?php echo esc_attr( $wc_competitor_monitor_mapping->last_stock_status ?: 'unknown' ); ?>"><?php echo esc_html( $wc_competitor_monitor_mapping->last_stock_status ?: __( 'unknown', 'competitor-price-stock-monitor' ) ); ?></span></td>
							<td>
								<span class="wccm-status <?php echo empty( $wc_competitor_monitor_mapping->active ) ? 'is-inactive' : 'is-active'; ?>">
									<?php echo esc_html( empty( $wc_competitor_monitor_mapping->active ) ? __( 'Paused', 'competitor-price-stock-monitor' ) : __( 'Active', 'competitor-price-stock-monitor' ) ); ?>
								</span>
							</td>
							<td class="wccm-row-actions">
								<?php
								$wc_competitor_monitor_edit_url = add_query_arg(
									array(
										'page'       => 'competitor-price-stock-monitor-products',
										'mapping_id' => absint( $wc_competitor_monitor_mapping->id ),
									),
									admin_url( 'admin.php' )
								);
								?>
								<a class="button button-small" href="<?php echo esc_url( $wc_competitor_monitor_edit_url ); ?>"><?php esc_html_e( 'Edit', 'competitor-price-stock-monitor' ); ?></a>

								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="wc_competitor_monitor_run_check">
									<input type="hidden" name="mapping_id" value="<?php echo esc_attr( absint( $wc_competitor_monitor_mapping->id ) ); ?>">
									<?php wp_nonce_field( 'wc_competitor_monitor_run_check_' . absint( $wc_competitor_monitor_mapping->id ) ); ?>
									<button type="submit" class="button button-small"><?php esc_html_e( 'Run check', 'competitor-price-stock-monitor' ); ?></button>
								</form>

								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="wc_competitor_monitor_toggle_mapping">
									<input type="hidden" name="mapping_id" value="<?php echo esc_attr( absint( $wc_competitor_monitor_mapping->id ) ); ?>">
									<?php wp_nonce_field( 'wc_competitor_monitor_toggle_mapping_' . absint( $wc_competitor_monitor_mapping->id ) ); ?>
									<button type="submit" class="button button-small"><?php echo esc_html( empty( $wc_competitor_monitor_mapping->active ) ? __( 'Resume', 'competitor-price-stock-monitor' ) : __( 'Pause', 'competitor-price-stock-monitor' ) ); ?></button>
								</form>

								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wccm-delete-form">
									<input type="hidden" name="action" value="wc_competitor_monitor_delete_mapping">
									<input type="hidden" name="mapping_id" value="<?php echo esc_attr( absint( $wc_competitor_monitor_mapping->id ) ); ?>">
									<?php wp_nonce_field( 'wc_competitor_monitor_delete_mapping_' . absint( $wc_competitor_monitor_mapping->id ) ); ?>
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
