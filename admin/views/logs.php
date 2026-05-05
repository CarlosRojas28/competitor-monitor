<?php
/**
 * Logs view.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wccm-wrap">
	<h1><?php esc_html_e( 'Logs', 'competitor-price-stock-monitor' ); ?></h1>

	<div class="wccm-actions">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wccm-delete-form">
			<input type="hidden" name="action" value="wc_competitor_monitor_clear_logs">
			<?php wp_nonce_field( 'wc_competitor_monitor_clear_logs' ); ?>
			<button type="submit" class="button button-secondary"><?php esc_html_e( 'Clear logs', 'competitor-price-stock-monitor' ); ?></button>
		</form>
	</div>

	<section class="wccm-panel">
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'competitor-price-stock-monitor' ); ?></th>
					<th><?php esc_html_e( 'Level', 'competitor-price-stock-monitor' ); ?></th>
					<th><?php esc_html_e( 'Message', 'competitor-price-stock-monitor' ); ?></th>
					<th><?php esc_html_e( 'Context', 'competitor-price-stock-monitor' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $wc_competitor_monitor_logs ) ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'No logs yet.', 'competitor-price-stock-monitor' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $wc_competitor_monitor_logs as $wc_competitor_monitor_log ) : ?>
						<tr>
							<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $wc_competitor_monitor_log->created_at ) ); ?></td>
							<td><span class="wccm-log-level wccm-log-<?php echo esc_attr( $wc_competitor_monitor_log->level ); ?>"><?php echo esc_html( $wc_competitor_monitor_log->level ); ?></span></td>
							<td><?php echo esc_html( $wc_competitor_monitor_log->message ); ?></td>
							<td><code><?php echo esc_html( $wc_competitor_monitor_log->context ? wp_trim_words( $wc_competitor_monitor_log->context, 30, '...' ) : '' ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</section>
</div>
