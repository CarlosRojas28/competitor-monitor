<?php
/**
 * Product mapping view.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wc_competitor_monitor_is_editing                 = $wc_competitor_monitor_editing_mapping && ! empty( $wc_competitor_monitor_editing_mapping->id );
$wc_competitor_monitor_global_increase_mode       = sanitize_key( (string) ( $wc_competitor_monitor_settings['suggested_increase_limit_mode'] ?? 'percent' ) );
$wc_competitor_monitor_global_increase_percentage = max( 0, min( 999.99, (float) ( $wc_competitor_monitor_settings['suggested_increase_limit_percentage'] ?? 5.0 ) ) );
$wc_competitor_monitor_global_increase_text       = 'none' === $wc_competitor_monitor_global_increase_mode
	? __( 'Global setting: no increase limit.', 'competitor-price-stock-monitor' )
	: sprintf(
		/* translators: %s: configured percentage. */
		__( 'Global setting: limit suggested increases to %s%%.', 'competitor-price-stock-monitor' ),
		number_format_i18n( $wc_competitor_monitor_global_increase_percentage, 2 )
	);
$wc_competitor_monitor_edit_increase_mode = $wc_competitor_monitor_is_editing ? sanitize_key( (string) ( $wc_competitor_monitor_editing_mapping->suggested_increase_mode ?? 'global' ) ) : 'global';
if ( ! in_array( $wc_competitor_monitor_edit_increase_mode, array( 'global', 'percent', 'none' ), true ) ) {
	$wc_competitor_monitor_edit_increase_mode = 'global';
}
$wc_competitor_monitor_edit_increase_percentage = $wc_competitor_monitor_is_editing && null !== ( $wc_competitor_monitor_editing_mapping->suggested_increase_percentage ?? null )
	? (float) $wc_competitor_monitor_editing_mapping->suggested_increase_percentage
	: $wc_competitor_monitor_global_increase_percentage;
$wc_competitor_monitor_global_auto_price_mode   = sanitize_key( (string) ( $wc_competitor_monitor_settings['auto_price_adjustment_mode'] ?? 'disabled' ) );
if ( ! in_array( $wc_competitor_monitor_global_auto_price_mode, array( 'enabled', 'disabled' ), true ) ) {
	$wc_competitor_monitor_global_auto_price_mode = 'disabled';
}
$wc_competitor_monitor_global_auto_price_text = 'enabled' === $wc_competitor_monitor_global_auto_price_mode
	? __( 'Use global setting: apply recommended prices automatically.', 'competitor-price-stock-monitor' )
	: __( 'Use global setting: do not change prices automatically.', 'competitor-price-stock-monitor' );
$wc_competitor_monitor_edit_auto_price_mode   = 'global';
if ( $wc_competitor_monitor_is_editing ) {
	$wc_competitor_monitor_edit_auto_price_mode = sanitize_key( (string) get_post_meta( absint( $wc_competitor_monitor_editing_mapping->product_id ), WC_Competitor_Monitor_DB::PRODUCT_AUTO_PRICE_MODE_META, true ) );
	if ( ! in_array( $wc_competitor_monitor_edit_auto_price_mode, array( 'global', 'enabled', 'disabled' ), true ) ) {
		$wc_competitor_monitor_edit_auto_price_mode = 'global';
	}
}
$wc_competitor_monitor_product_search_enabled = ! empty( $wc_competitor_monitor_product_search_enabled ) && function_exists( 'WC' );
$wc_competitor_monitor_product_search_nonce   = wp_create_nonce( 'search-products' );
$wc_competitor_monitor_edit_product_id        = $wc_competitor_monitor_is_editing ? absint( $wc_competitor_monitor_editing_mapping->product_id ) : 0;
$wc_competitor_monitor_edit_product_label     = $wc_competitor_monitor_edit_product_id > 0 ? $this->product_title( $wc_competitor_monitor_edit_product_id ) : '';
$wc_competitor_monitor_pro_is_active          = ! empty( $wc_competitor_monitor_settings['pro_enabled'] ) && 'active' === (string) ( $wc_competitor_monitor_settings['pro_license_status'] ?? '' );
$wc_competitor_monitor_saas_base_url          = untrailingslashit( esc_url_raw( (string) ( $wc_competitor_monitor_settings['pro_saas_url'] ?? 'https://competitor-monitor-pro-production.up.railway.app' ) ) );
if ( '' === $wc_competitor_monitor_saas_base_url ) {
	$wc_competitor_monitor_saas_base_url = 'https://competitor-monitor-pro-production.up.railway.app';
}
$wc_competitor_monitor_upgrade_url   = $wc_competitor_monitor_saas_base_url . '/#pricing';
$wc_competitor_monitor_discovery_url = add_query_arg(
	array(
		'site_url' => home_url(),
	),
	$wc_competitor_monitor_saas_base_url . '/app/discovery'
);
$wc_competitor_monitor_settings_url  = admin_url( 'admin.php?page=competitor-price-stock-monitor-settings' );
?>
<div class="wrap wccm-wrap">
	<h1><?php esc_html_e( 'Competitors', 'competitor-price-stock-monitor' ); ?></h1>

	<?php if ( ! function_exists( 'wc_get_products' ) ) : ?>
		<div class="notice notice-warning"><p><?php esc_html_e( 'WooCommerce is not active. You can keep the plugin enabled, but mappings require WooCommerce products.', 'competitor-price-stock-monitor' ); ?></p></div>
	<?php endif; ?>

	<section class="wccm-panel wccm-pro-cta-panel">
		<?php if ( $wc_competitor_monitor_pro_is_active ) : ?>
			<h2><?php esc_html_e( 'AI competitor discovery lives in the SaaS', 'competitor-price-stock-monitor' ); ?></h2>
			<p><?php esc_html_e( 'Use the SaaS to discover competitors with AI, review matches and sync mappings back to this store. Manual mapping remains available below as a fallback.', 'competitor-price-stock-monitor' ); ?></p>
			<p class="wccm-actions">
				<a class="button button-primary" href="<?php echo esc_url( $wc_competitor_monitor_discovery_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open AI Discovery', 'competitor-price-stock-monitor' ); ?></a>
				<a class="button" href="<?php echo esc_url( $wc_competitor_monitor_settings_url ); ?>"><?php esc_html_e( 'Manage Pro bridge', 'competitor-price-stock-monitor' ); ?></a>
			</p>
		<?php else : ?>
			<h2><?php esc_html_e( 'Unlock Pro: AI competitor discovery and profit impact', 'competitor-price-stock-monitor' ); ?></h2>
			<p><?php esc_html_e( 'Upgrade to use the SaaS for AI competitor discovery, automatic price updates and gross profit impact reporting. Manual mapping remains available below in the free plugin.', 'competitor-price-stock-monitor' ); ?></p>
			<p class="wccm-actions">
				<a class="button button-primary" href="<?php echo esc_url( $wc_competitor_monitor_upgrade_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade to Pro', 'competitor-price-stock-monitor' ); ?></a>
				<a class="button" href="<?php echo esc_url( $wc_competitor_monitor_settings_url ); ?>"><?php esc_html_e( 'Connect existing Pro license', 'competitor-price-stock-monitor' ); ?></a>
			</p>
		<?php endif; ?>
	</section>

	<section class="wccm-panel">
		<h2><?php echo esc_html( $wc_competitor_monitor_is_editing ? __( 'Edit competitor', 'competitor-price-stock-monitor' ) : __( 'Add competitor', 'competitor-price-stock-monitor' ) ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wccm-form">
			<input type="hidden" name="action" value="wc_competitor_monitor_save_mapping">
			<input type="hidden" name="mapping_id" value="<?php echo esc_attr( $wc_competitor_monitor_is_editing ? absint( $wc_competitor_monitor_editing_mapping->id ) : 0 ); ?>">
			<?php wp_nonce_field( 'wc_competitor_monitor_save_mapping' ); ?>

			<div class="wccm-form-grid">
				<label class="wccm-full">
					<span><?php esc_html_e( 'WooCommerce product', 'competitor-price-stock-monitor' ); ?></span>
					<?php if ( $wc_competitor_monitor_product_search_enabled ) : ?>
						<select
							name="product_id"
							class="wc-product-search wccm-product-search"
							style="width:100%;"
							data-placeholder="<?php echo esc_attr__( 'Search product by name or SKU...', 'competitor-price-stock-monitor' ); ?>"
							data-action="woocommerce_json_search_products_and_variations"
							data-security="<?php echo esc_attr( $wc_competitor_monitor_product_search_nonce ); ?>"
							data-minimum_input_length="3"
							data-allow_clear="true"
							required
						>
							<option value=""></option>
							<?php foreach ( $wc_competitor_monitor_product_options as $wc_competitor_monitor_product_option_id => $wc_competitor_monitor_product_option_label ) : ?>
								<option value="<?php echo esc_attr( absint( $wc_competitor_monitor_product_option_id ) ); ?>" <?php selected( $wc_competitor_monitor_edit_product_id, absint( $wc_competitor_monitor_product_option_id ) ); ?>><?php echo esc_html( $wc_competitor_monitor_product_option_label ); ?></option>
							<?php endforeach; ?>
							<?php if ( $wc_competitor_monitor_edit_product_id > 0 && ! isset( $wc_competitor_monitor_product_options[ $wc_competitor_monitor_edit_product_id ] ) ) : ?>
								<option value="<?php echo esc_attr( $wc_competitor_monitor_edit_product_id ); ?>" selected="selected"><?php echo esc_html( $wc_competitor_monitor_edit_product_label ); ?></option>
							<?php endif; ?>
						</select>
						<small><?php esc_html_e( 'Type at least 3 characters to search products.', 'competitor-price-stock-monitor' ); ?></small>
					<?php else : ?>
						<input type="number" min="1" name="product_id" value="<?php echo esc_attr( $wc_competitor_monitor_edit_product_id ?: '' ); ?>" required>
						<small><?php esc_html_e( 'WooCommerce product search is unavailable. Enter the product ID.', 'competitor-price-stock-monitor' ); ?></small>
					<?php endif; ?>
				</label>

				<label class="wccm-full">
					<span><?php esc_html_e( 'Competitor name', 'competitor-price-stock-monitor' ); ?></span>
					<input type="text" name="competitor_name" value="<?php echo esc_attr( $wc_competitor_monitor_is_editing ? $wc_competitor_monitor_editing_mapping->competitor_name : '' ); ?>" required maxlength="190">
				</label>

				<label class="wccm-full">
					<span><?php esc_html_e( 'Competitor URL', 'competitor-price-stock-monitor' ); ?></span>
					<input type="url" name="competitor_url" id="wccm-competitor-url" value="<?php echo esc_url( $wc_competitor_monitor_is_editing ? $wc_competitor_monitor_editing_mapping->competitor_url : '' ); ?>" required aria-describedby="wccm-competitor-url-error">
				<span class="wccm-field-error" id="wccm-competitor-url-error" hidden></span>
				</label>

				<details class="wccm-full" style="margin-bottom:.5rem">
					<summary style="cursor:pointer;font-weight:600;color:#3c434a;margin-bottom:.75rem"><?php esc_html_e( 'Advanced: selectors and session headers', 'competitor-price-stock-monitor' ); ?></summary>
					<p class="description" style="margin-bottom:1rem"><?php esc_html_e( 'Leave these empty — the plugin auto-detects price and stock on most competitor sites. Only fill them in if automatic detection fails.', 'competitor-price-stock-monitor' ); ?></p>
					<div class="wccm-form-grid">
						<label>
							<span><?php esc_html_e( 'Price CSS selector', 'competitor-price-stock-monitor' ); ?></span>
							<input type="text" name="price_selector" id="wccm-price-selector" value="<?php echo esc_attr( $wc_competitor_monitor_is_editing ? $wc_competitor_monitor_editing_mapping->price_selector : '' ); ?>" maxlength="255" placeholder=".price" aria-describedby="wccm-price-selector-error">
						<span class="wccm-field-error" id="wccm-price-selector-error" hidden></span>
						</label>

						<label>
							<span><?php esc_html_e( 'Stock CSS selector', 'competitor-price-stock-monitor' ); ?></span>
							<input type="text" name="stock_selector" id="wccm-stock-selector" value="<?php echo esc_attr( $wc_competitor_monitor_is_editing ? $wc_competitor_monitor_editing_mapping->stock_selector : '' ); ?>" maxlength="255" placeholder=".stock" aria-describedby="wccm-stock-selector-error">
							<span class="wccm-field-error" id="wccm-stock-selector-error" hidden></span>
						</label>

						<label class="wccm-full">
							<span><?php esc_html_e( 'Browser user-agent override', 'competitor-price-stock-monitor' ); ?></span>
							<input type="text" name="browser_user_agent" value="<?php echo esc_attr( $wc_competitor_monitor_is_editing ? ( $wc_competitor_monitor_editing_mapping->browser_user_agent ?? '' ) : '' ); ?>" maxlength="255">
							<small>
								<?php esc_html_e( 'Only needed if the competitor site blocks the default checker. Copy the User-Agent from Chrome DevTools → Network tab.', 'competitor-price-stock-monitor' ); ?>
								<a href="<?php echo esc_url( 'https://developer.chrome.com/docs/devtools/network/reference' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'How to find it.', 'competitor-price-stock-monitor' ); ?></a>
							</small>
						</label>

						<label class="wccm-full">
							<span><?php esc_html_e( 'Browser Cookie header', 'competitor-price-stock-monitor' ); ?></span>
							<textarea name="browser_cookie_header" rows="3" maxlength="4096"><?php echo esc_textarea( $wc_competitor_monitor_is_editing ? ( $wc_competitor_monitor_editing_mapping->browser_cookie_header ?? '' ) : '' ); ?></textarea>
							<small>
								<?php esc_html_e( 'Only needed for sites that require a login session. Copy the Cookie header from Chrome DevTools → Network tab. Cookies expire — you may need to update this periodically.', 'competitor-price-stock-monitor' ); ?>
							</small>
						</label>
					</div>
				</details>

				<label>
					<span><?php esc_html_e( 'Currency', 'competitor-price-stock-monitor' ); ?></span>
					<input type="text" name="currency" value="<?php echo esc_attr( $wc_competitor_monitor_is_editing ? $wc_competitor_monitor_editing_mapping->currency : ( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD' ) ); ?>" maxlength="10">
				</label>

				<label>
					<span><?php esc_html_e( 'Minimum margin %', 'competitor-price-stock-monitor' ); ?></span>
					<input type="number" step="0.01" min="0" max="99" name="min_margin_percentage" value="<?php echo esc_attr( $wc_competitor_monitor_is_editing ? $wc_competitor_monitor_editing_mapping->min_margin_percentage : '20.00' ); ?>">
				</label>

				<label>
					<span><?php esc_html_e( 'Suggested price increase limit', 'competitor-price-stock-monitor' ); ?></span>
					<select name="suggested_increase_mode">
						<option value="global" <?php selected( $wc_competitor_monitor_edit_increase_mode, 'global' ); ?>><?php echo esc_html( $wc_competitor_monitor_global_increase_text ); ?></option>
						<option value="percent" <?php selected( $wc_competitor_monitor_edit_increase_mode, 'percent' ); ?>><?php esc_html_e( 'Use custom percentage limit', 'competitor-price-stock-monitor' ); ?></option>
						<option value="none" <?php selected( $wc_competitor_monitor_edit_increase_mode, 'none' ); ?>><?php esc_html_e( 'No limit', 'competitor-price-stock-monitor' ); ?></option>
					</select>
				</label>

				<label data-wccm-suggested-increase-percentage<?php echo 'percent' !== $wc_competitor_monitor_edit_increase_mode ? ' hidden' : ''; ?>>
					<span><?php esc_html_e( 'Custom increase %', 'competitor-price-stock-monitor' ); ?></span>
					<input type="number" step="0.01" min="0" max="999.99" name="suggested_increase_percentage" value="<?php echo esc_attr( $wc_competitor_monitor_edit_increase_percentage ); ?>">
					<small><?php esc_html_e( 'Used only when the custom percentage option is selected.', 'competitor-price-stock-monitor' ); ?></small>
				</label>

				<label>
					<span><?php esc_html_e( 'Apply recommended prices automatically', 'competitor-price-stock-monitor' ); ?></span>
					<select name="auto_price_adjustment_mode">
						<option value="global" <?php selected( $wc_competitor_monitor_edit_auto_price_mode, 'global' ); ?>><?php echo esc_html( $wc_competitor_monitor_global_auto_price_text ); ?></option>
						<option value="enabled" <?php selected( $wc_competitor_monitor_edit_auto_price_mode, 'enabled' ); ?>><?php esc_html_e( 'Yes, apply recommended prices for this product', 'competitor-price-stock-monitor' ); ?></option>
						<option value="disabled" <?php selected( $wc_competitor_monitor_edit_auto_price_mode, 'disabled' ); ?>><?php esc_html_e( 'No, never change this product price automatically', 'competitor-price-stock-monitor' ); ?></option>
					</select>
					<small><?php esc_html_e( 'This Pro override is saved on the WooCommerce product and applies to all competitor URLs mapped to it.', 'competitor-price-stock-monitor' ); ?></small>
				</label>

				<label class="wccm-checkbox">
					<input type="checkbox" name="active" value="1" <?php checked( $wc_competitor_monitor_is_editing ? (int) $wc_competitor_monitor_editing_mapping->active : 1, 1 ); ?>>
					<span><?php esc_html_e( 'Active', 'competitor-price-stock-monitor' ); ?></span>
				</label>
			</div>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php echo esc_html( $wc_competitor_monitor_is_editing ? __( 'Update competitor', 'competitor-price-stock-monitor' ) : __( 'Save competitor', 'competitor-price-stock-monitor' ) ); ?></button>
				<?php if ( $wc_competitor_monitor_is_editing ) : ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=competitor-price-stock-monitor-products' ) ); ?>"><?php esc_html_e( 'Cancel', 'competitor-price-stock-monitor' ); ?></a>
				<?php endif; ?>
			</p>
		</form>
	</section>

	<section class="wccm-panel" id="wccm-mappings-panel">
		<div style="display:flex;align-items:baseline;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px">
			<h2 style="margin:0"><?php esc_html_e( 'Competitors being monitored', 'competitor-price-stock-monitor' ); ?></h2>
			<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="wc_competitor_monitor_export_mappings_csv">
					<?php wp_nonce_field( 'wc_competitor_monitor_export_mappings_csv' ); ?>
					<button type="submit" class="button button-small"><?php esc_html_e( 'Export CSV', 'competitor-price-stock-monitor' ); ?></button>
				</form>
				<button type="button" class="button button-small" id="wccm-import-csv-toggle"><?php esc_html_e( 'Import CSV', 'competitor-price-stock-monitor' ); ?></button>
			</div>
		</div>

		<div id="wccm-import-csv-section" hidden style="margin-bottom:16px;padding:12px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px">
			<p class="description" style="margin-top:0"><?php esc_html_e( 'CSV must have columns: product_id, competitor_name, competitor_url. Optional: currency, min_margin_percentage. Duplicate URLs are skipped.', 'competitor-price-stock-monitor' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="wc_competitor_monitor_import_mappings_csv">
				<?php wp_nonce_field( 'wc_competitor_monitor_import_mappings_csv' ); ?>
				<input type="file" name="mappings_csv" accept=".csv,text/csv" required style="margin-right:8px">
				<button type="submit" class="button button-primary button-small"><?php esc_html_e( 'Import', 'competitor-price-stock-monitor' ); ?></button>
			</form>
		</div>

		<table class="widefat striped wccm-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Your product', 'competitor-price-stock-monitor' ); ?></th>
					<th><?php esc_html_e( 'Competitor', 'competitor-price-stock-monitor' ); ?></th>
					<th class="wccm-col-tablet"><?php esc_html_e( 'Their price', 'competitor-price-stock-monitor' ); ?></th>
					<th class="wccm-col-tablet"><?php esc_html_e( 'Your price', 'competitor-price-stock-monitor' ); ?></th>
					<th class="wccm-col-desktop"><?php esc_html_e( 'Difference', 'competitor-price-stock-monitor' ); ?></th>
					<th class="wccm-col-desktop"><?php esc_html_e( 'Their stock', 'competitor-price-stock-monitor' ); ?></th>
					<th class="wccm-col-desktop"><?php esc_html_e( 'Extra profit (30d)', 'competitor-price-stock-monitor' ); ?></th>
					<th class="wccm-col-desktop"><?php esc_html_e( 'Last check', 'competitor-price-stock-monitor' ); ?></th>
					<th class="wccm-col-tablet"><?php esc_html_e( 'Status', 'competitor-price-stock-monitor' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'competitor-price-stock-monitor' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $wc_competitor_monitor_mappings ) ) : ?>
					<tr><td colspan="10"><?php esc_html_e( 'No competitors added yet. Add one above to start monitoring prices.', 'competitor-price-stock-monitor' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $wc_competitor_monitor_mappings as $wc_competitor_monitor_mapping ) : ?>
						<?php
						$wc_competitor_monitor_latest    = $this->latest_history( absint( $wc_competitor_monitor_mapping->id ) );
						$wc_competitor_monitor_our_price = $this->product_price( absint( $wc_competitor_monitor_mapping->product_id ) );
						$wc_competitor_monitor_impact    = $this->profit_impact_for_mapping( absint( $wc_competitor_monitor_mapping->id ) );
						?>
						<tr>
							<td>
								<?php $wc_competitor_monitor_edit_link = $this->product_edit_link( absint( $wc_competitor_monitor_mapping->product_id ) ); ?>
								<?php if ( $wc_competitor_monitor_edit_link ) : ?>
									<a href="<?php echo esc_url( $wc_competitor_monitor_edit_link ); ?>"><?php echo esc_html( $this->product_title( absint( $wc_competitor_monitor_mapping->product_id ) ) ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $this->product_title( absint( $wc_competitor_monitor_mapping->product_id ) ) ); ?>
								<?php endif; ?>
							</td>
							<td>
								<strong><?php echo esc_html( $wc_competitor_monitor_mapping->competitor_name ); ?></strong><br>
								<a href="<?php echo esc_url( $wc_competitor_monitor_mapping->competitor_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) wp_parse_url( $wc_competitor_monitor_mapping->competitor_url, PHP_URL_HOST ) ); ?></a>
							</td>
							<td class="wccm-col-tablet"><?php echo $this->format_price( null !== $wc_competitor_monitor_mapping->last_price ? (float) $wc_competitor_monitor_mapping->last_price : null ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td class="wccm-col-tablet"><?php echo $this->format_price( $wc_competitor_monitor_our_price ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td class="wccm-col-desktop">
								<?php if ( $wc_competitor_monitor_latest && null !== $wc_competitor_monitor_latest->difference_percentage ) : ?>
									<?php echo esc_html( number_format_i18n( (float) $wc_competitor_monitor_latest->difference_percentage, 2 ) ); ?>%
								<?php else : ?>
									&mdash;
								<?php endif; ?>
							</td>
							<td class="wccm-col-desktop"><span class="wccm-badge wccm-badge-<?php echo esc_attr( $wc_competitor_monitor_mapping->last_stock_status ?: 'unknown' ); ?>"><?php echo esc_html( $wc_competitor_monitor_mapping->last_stock_status ?: __( 'unknown', 'competitor-price-stock-monitor' ) ); ?></span></td>
							<td class="wccm-col-desktop">
								<strong><?php echo $this->format_money( (float) ( $wc_competitor_monitor_impact['attributed_gross_profit'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
								<?php if ( ! empty( $wc_competitor_monitor_impact['revenue_uplift_without_cost'] ) ) : ?>
									<br><small>
									<?php
									echo esc_html(
										sprintf(
										/* translators: %s: revenue uplift amount. */
											__( '%s unverified uplift', 'competitor-price-stock-monitor' ),
											wp_strip_all_tags( $this->format_money( (float) $wc_competitor_monitor_impact['revenue_uplift_without_cost'] ) )
										)
									);
									?>
									</small>
								<?php endif; ?>
							</td>
							<td class="wccm-col-desktop">
								<?php
								echo $wc_competitor_monitor_mapping->last_checked_at
									? esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $wc_competitor_monitor_mapping->last_checked_at ) )
									: '&mdash;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								?>
							</td>
							<td class="wccm-col-tablet">
								<span class="wccm-status <?php echo empty( $wc_competitor_monitor_mapping->active ) ? 'is-inactive' : 'is-active'; ?>">
									<?php echo esc_html( empty( $wc_competitor_monitor_mapping->active ) ? __( 'Inactive', 'competitor-price-stock-monitor' ) : __( 'Active', 'competitor-price-stock-monitor' ) ); ?>
								</span>
							</td>
							<td class="wccm-row-actions">
								<a class="button button-small" href="
								<?php
								echo esc_url(
									add_query_arg(
										array(
											'page'       => 'competitor-price-stock-monitor-products',
											'mapping_id' => absint( $wc_competitor_monitor_mapping->id ),
										),
										admin_url( 'admin.php' )
									)
								);
								?>
																		"><?php esc_html_e( 'Edit', 'competitor-price-stock-monitor' ); ?></a>

								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="wc_competitor_monitor_run_check">
									<input type="hidden" name="mapping_id" value="<?php echo esc_attr( absint( $wc_competitor_monitor_mapping->id ) ); ?>">
									<?php wp_nonce_field( 'wc_competitor_monitor_run_check_' . absint( $wc_competitor_monitor_mapping->id ) ); ?>
									<button type="submit" class="button button-small"><?php esc_html_e( 'Run check now', 'competitor-price-stock-monitor' ); ?></button>
								</form>

								<a class="button button-small" href="
								<?php
								echo esc_url(
									add_query_arg(
										array(
											'page'       => 'competitor-price-stock-monitor-products',
											'view'       => 'history',
											'mapping_id' => absint( $wc_competitor_monitor_mapping->id ),
										),
										admin_url( 'admin.php' )
									)
								);
								?>
																		"><?php esc_html_e( 'View history', 'competitor-price-stock-monitor' ); ?></a>

								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="wc_competitor_monitor_toggle_mapping">
									<input type="hidden" name="mapping_id" value="<?php echo esc_attr( absint( $wc_competitor_monitor_mapping->id ) ); ?>">
									<?php wp_nonce_field( 'wc_competitor_monitor_toggle_mapping_' . absint( $wc_competitor_monitor_mapping->id ) ); ?>
									<button type="submit" class="button button-small"><?php echo esc_html( empty( $wc_competitor_monitor_mapping->active ) ? __( 'Activate', 'competitor-price-stock-monitor' ) : __( 'Deactivate', 'competitor-price-stock-monitor' ) ); ?></button>
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
				<?php endif; ?>
				<?php if ( ! $wc_competitor_monitor_pro_is_active && ! empty( $wc_competitor_monitor_mappings ) ) : ?>
					<tr class="wccm-ghost-row">
						<td colspan="10">
							<span class="wccm-ghost-label">&#128274; <?php esc_html_e( 'Pro would auto-discover additional competitors for your products using AI — no URL hunting required.', 'competitor-price-stock-monitor' ); ?></span>
							<a href="<?php echo esc_url( $wc_competitor_monitor_upgrade_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade to Pro', 'competitor-price-stock-monitor' ); ?></a>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</section>

	<?php if ( ! empty( $wc_competitor_monitor_history ) ) : ?>
		<section class="wccm-panel">
			<h2><?php esc_html_e( 'History', 'competitor-price-stock-monitor' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Checked', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Competitor price', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Stock', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Our price', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Difference', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Status', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Error', 'competitor-price-stock-monitor' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $wc_competitor_monitor_history as $wc_competitor_monitor_history_row ) : ?>
						<tr>
							<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $wc_competitor_monitor_history_row->checked_at ) ); ?></td>
							<td><?php echo $this->format_price( null !== $wc_competitor_monitor_history_row->competitor_price ? (float) $wc_competitor_monitor_history_row->competitor_price : null ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td><?php echo esc_html( $wc_competitor_monitor_history_row->competitor_stock_status ?: __( 'unknown', 'competitor-price-stock-monitor' ) ); ?></td>
							<td><?php echo $this->format_price( null !== $wc_competitor_monitor_history_row->our_price ? (float) $wc_competitor_monitor_history_row->our_price : null ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td><?php echo null !== $wc_competitor_monitor_history_row->difference_percentage ? esc_html( number_format_i18n( (float) $wc_competitor_monitor_history_row->difference_percentage, 2 ) . '%' ) : '&mdash;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td><?php echo esc_html( $wc_competitor_monitor_history_row->raw_status ); ?></td>
							<td><?php echo esc_html( $wc_competitor_monitor_history_row->error_message ?: '' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>
	<?php endif; ?>
</div>
