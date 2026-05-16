<?php
/**
 * Admin UI.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress admin screens and form handlers.
 */
class WC_Competitor_Monitor_Admin {

	/**
	 * Database layer.
	 *
	 * @var WC_Competitor_Monitor_DB
	 */
	private WC_Competitor_Monitor_DB $db;

	/**
	 * Monitor service.
	 *
	 * @var WC_Competitor_Monitor_Monitor
	 */
	private WC_Competitor_Monitor_Monitor $monitor;

	/**
	 * Recommendation service.
	 *
	 * @var WC_Competitor_Monitor_Recommendations
	 */
	private WC_Competitor_Monitor_Recommendations $recommendations;

	/**
	 * Pro SaaS client.
	 *
	 * @var WC_Competitor_Monitor_Pro_Client
	 */
	private WC_Competitor_Monitor_Pro_Client $pro_client;

	/**
	 * Mapping sync service.
	 *
	 * @var WC_Competitor_Monitor_Sync
	 */
	private WC_Competitor_Monitor_Sync $sync;

	/**
	 * Constructor.
	 *
	 * @param WC_Competitor_Monitor_DB              $db Database layer.
	 * @param WC_Competitor_Monitor_Monitor         $monitor Monitor service.
	 * @param WC_Competitor_Monitor_Recommendations $recommendations Recommendation service.
	 * @param WC_Competitor_Monitor_Pro_Client      $pro_client Pro SaaS client.
	 * @param WC_Competitor_Monitor_Sync            $sync Mapping sync service.
	 */
	public function __construct(
		WC_Competitor_Monitor_DB $db,
		WC_Competitor_Monitor_Monitor $monitor,
		WC_Competitor_Monitor_Recommendations $recommendations,
		WC_Competitor_Monitor_Pro_Client $pro_client,
		WC_Competitor_Monitor_Sync $sync
	) {
		$this->db              = $db;
		$this->monitor         = $monitor;
		$this->recommendations = $recommendations;
		$this->pro_client      = $pro_client;
		$this->sync            = $sync;

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'render_welcome_notice' ) );
		add_action( 'admin_notices', array( $this, 'render_product_metabox_notice' ) );
		add_action( 'admin_notices', array( $this, 'render_cron_disabled_notice' ) );
		add_action( 'admin_post_wc_competitor_monitor_dismiss_welcome', array( $this, 'handle_dismiss_welcome' ) );
		add_action( 'add_meta_boxes_product', array( $this, 'register_product_metabox' ) );
		add_action( 'save_post_product', array( $this, 'handle_save_product_metabox' ), 10, 2 );
		add_action( 'admin_post_wc_competitor_monitor_save_mapping', array( $this, 'handle_save_mapping' ) );
		add_action( 'admin_post_wc_competitor_monitor_restore_original_price', array( $this, 'handle_restore_original_price' ) );
		add_action( 'admin_post_wc_competitor_monitor_delete_mapping', array( $this, 'handle_delete_mapping' ) );
		add_action( 'admin_post_wc_competitor_monitor_toggle_mapping', array( $this, 'handle_toggle_mapping' ) );
		add_action( 'admin_post_wc_competitor_monitor_run_check', array( $this, 'handle_run_check' ) );
		add_action( 'admin_post_wc_competitor_monitor_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_wc_competitor_monitor_save_auto_pricing', array( $this, 'handle_save_auto_pricing' ) );
		add_action( 'admin_post_wc_competitor_monitor_activate_pro_license', array( $this, 'handle_activate_pro_license' ) );
		add_action( 'admin_post_wc_competitor_monitor_rotate_bridge', array( $this, 'handle_rotate_bridge' ) );
		add_action( 'admin_post_wc_competitor_monitor_mark_alert_read', array( $this, 'handle_mark_alert_read' ) );
		add_action( 'admin_post_wc_competitor_monitor_delete_alert', array( $this, 'handle_delete_alert' ) );
		add_action( 'admin_post_wc_competitor_monitor_clear_logs', array( $this, 'handle_clear_logs' ) );
		add_action( 'wp_ajax_wccm_quick_add_mapping', array( $this, 'handle_ajax_quick_add_mapping' ) );
	}

	/**
	 * Registers menu pages.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$capability = $this->menu_capability();

		add_menu_page(
			__( 'Competitor Monitor', 'competitor-price-stock-monitor' ),
			__( 'Competitor Monitor', 'competitor-price-stock-monitor' ),
			$capability,
			'competitor-price-stock-monitor',
			array( $this, 'render_dashboard' ),
			'dashicons-chart-line',
			56
		);

		add_submenu_page(
			'competitor-price-stock-monitor',
			__( 'Dashboard', 'competitor-price-stock-monitor' ),
			__( 'Dashboard', 'competitor-price-stock-monitor' ),
			$capability,
			'competitor-price-stock-monitor',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'competitor-price-stock-monitor',
			__( 'Product Mapping', 'competitor-price-stock-monitor' ),
			__( 'Product Mapping', 'competitor-price-stock-monitor' ),
			$capability,
			'competitor-price-stock-monitor-products',
			array( $this, 'render_products' )
		);

		add_submenu_page(
			'competitor-price-stock-monitor',
			__( 'Competitor URLs', 'competitor-price-stock-monitor' ),
			__( 'Competitor URLs', 'competitor-price-stock-monitor' ),
			$capability,
			'competitor-price-stock-monitor-competitors',
			array( $this, 'render_competitors' )
		);

		add_submenu_page(
			'competitor-price-stock-monitor',
			__( 'Alerts', 'competitor-price-stock-monitor' ),
			__( 'Alerts', 'competitor-price-stock-monitor' ),
			$capability,
			'competitor-price-stock-monitor-alerts',
			array( $this, 'render_alerts' )
		);

		add_submenu_page(
			'competitor-price-stock-monitor',
			__( 'Settings', 'competitor-price-stock-monitor' ),
			__( 'Settings', 'competitor-price-stock-monitor' ),
			$capability,
			'competitor-price-stock-monitor-settings',
			array( $this, 'render_settings' )
		);

		add_submenu_page(
			'competitor-price-stock-monitor',
			__( 'Logs', 'competitor-price-stock-monitor' ),
			__( 'Logs', 'competitor-price-stock-monitor' ),
			$capability,
			'competitor-price-stock-monitor-logs',
			array( $this, 'render_logs' )
		);
	}

	/**
	 * Enqueues admin assets.
	 *
	 * @param string $hook Current hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		$is_plugin_screen = str_contains( $hook, 'competitor-price-stock-monitor' );
		$is_product_edit  = false;

		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			$screen          = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			$is_product_edit = $screen && 'product' === $screen->post_type;
		}

		if ( ! $is_plugin_screen && ! $is_product_edit ) {
			return;
		}

		$script_dependencies = array( 'jquery' );

		if ( $is_plugin_screen && function_exists( 'WC' ) && wp_script_is( 'wc-enhanced-select', 'registered' ) ) {
			wp_enqueue_script( 'wc-enhanced-select' );
			$script_dependencies[] = 'wc-enhanced-select';
		}

		if ( $is_plugin_screen && wp_style_is( 'woocommerce_admin_styles', 'registered' ) ) {
			wp_enqueue_style( 'woocommerce_admin_styles' );
		}

		$admin_css_path = WC_COMPETITOR_MONITOR_PATH . 'assets/css/admin.css';
		$admin_js_path  = WC_COMPETITOR_MONITOR_PATH . 'assets/js/admin.js';
		$admin_css_ver  = file_exists( $admin_css_path ) ? (string) filemtime( $admin_css_path ) : WC_COMPETITOR_MONITOR_VERSION;
		$admin_js_ver   = file_exists( $admin_js_path ) ? (string) filemtime( $admin_js_path ) : WC_COMPETITOR_MONITOR_VERSION;

		wp_enqueue_style(
			'competitor-price-stock-monitor-admin',
			WC_COMPETITOR_MONITOR_URL . 'assets/css/admin.css',
			array(),
			$admin_css_ver
		);

		wp_enqueue_script(
			'competitor-price-stock-monitor-admin',
			WC_COMPETITOR_MONITOR_URL . 'assets/js/admin.js',
			$script_dependencies,
			$admin_js_ver,
			true
		);

		wp_localize_script(
			'competitor-price-stock-monitor-admin',
			'wccmAdmin',
			array(
				'ajaxUrl'                   => admin_url( 'admin-ajax.php' ),
				'confirmDelete'             => __( 'This action cannot be undone. Continue?', 'competitor-price-stock-monitor' ),
				'searchProductsNonce'       => wp_create_nonce( 'search-products' ),
				'searchProductsPlaceholder' => __( 'Search product by name or SKU...', 'competitor-price-stock-monitor' ),
				'labelSearching'            => __( 'Searching...', 'competitor-price-stock-monitor' ),
				'labelNoProducts'           => __( 'No products found.', 'competitor-price-stock-monitor' ),
				'labelSearchFailed'         => __( 'Product search failed.', 'competitor-price-stock-monitor' ),
				'labelSelectProduct'        => __( 'Select a WooCommerce product from the search results.', 'competitor-price-stock-monitor' ),
				'labelAdding'               => __( 'Adding...', 'competitor-price-stock-monitor' ),
				'labelAddCompetitor'        => __( 'Add competitor', 'competitor-price-stock-monitor' ),
				'labelEnterUrl'             => __( 'Enter a competitor product URL.', 'competitor-price-stock-monitor' ),
				'labelAdded'                => __( 'Competitor mapping added.', 'competitor-price-stock-monitor' ),
				'labelRequestFailed'        => __( 'Request failed. Please try again.', 'competitor-price-stock-monitor' ),
				'labelDuplicateUrl'         => __( 'This competitor URL is already mapped to this product.', 'competitor-price-stock-monitor' ),
			)
		);
	}

	/**
	 * Renders dashboard.
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		$this->require_page_capability();

		$wc_competitor_monitor_stats           = $this->db->get_dashboard_stats();
		$wc_competitor_monitor_history         = $this->db->get_history( 8 );
		$wc_competitor_monitor_alerts          = $this->db->get_alerts( 6, true );
		$wc_competitor_monitor_recommendations = $this->recommendations->get_recent_recommendations( 6 );
		$wc_competitor_monitor_profit_impact   = $this->db->get_profit_impact_summary( 5 );

		include WC_COMPETITOR_MONITOR_PATH . 'admin/views/dashboard.php';
	}

	/**
	 * Renders product mapping page.
	 *
	 * @return void
	 */
	public function render_products(): void {
		$this->require_page_capability();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin view state.
		$wc_competitor_monitor_mapping_id = isset( $_GET['mapping_id'] ) ? absint( wp_unslash( $_GET['mapping_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin view state.
		$wc_competitor_monitor_view                   = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
		$wc_competitor_monitor_editing_mapping        = $wc_competitor_monitor_mapping_id > 0 ? $this->db->get_mapping( $wc_competitor_monitor_mapping_id ) : null;
		$wc_competitor_monitor_mappings               = $this->db->get_mappings( array( 'limit' => 200 ) );
		$wc_competitor_monitor_history                = ( 'history' === $wc_competitor_monitor_view && $wc_competitor_monitor_mapping_id > 0 ) ? $this->db->get_history( 50, $wc_competitor_monitor_mapping_id ) : array();
		$wc_competitor_monitor_settings               = $this->db->get_settings();
		$wc_competitor_monitor_product_search_enabled = function_exists( 'WC' ) && wp_script_is( 'wc-enhanced-select', 'registered' );
		$wc_competitor_monitor_product_options        = $this->product_select_options( 300 );

		include WC_COMPETITOR_MONITOR_PATH . 'admin/views/products.php';
	}

	/**
	 * Renders competitor URLs page.
	 *
	 * @return void
	 */
	public function render_competitors(): void {
		$this->require_page_capability();

		$wc_competitor_monitor_mappings = $this->db->get_mappings( array( 'limit' => 300 ) );
		$wc_competitor_monitor_settings = $this->db->get_settings();

		include WC_COMPETITOR_MONITOR_PATH . 'admin/views/competitors.php';
	}

	/**
	 * Renders alerts page.
	 *
	 * @return void
	 */
	public function render_alerts(): void {
		$this->require_page_capability();

		$wc_competitor_monitor_alerts = $this->db->get_alerts( 100, null );

		include WC_COMPETITOR_MONITOR_PATH . 'admin/views/alerts.php';
	}

	/**
	 * Renders settings.
	 *
	 * @return void
	 */
	public function render_settings(): void {
		$this->require_page_capability();

		$wc_competitor_monitor_settings = $this->db->get_settings();

		include WC_COMPETITOR_MONITOR_PATH . 'admin/views/settings.php';
	}

	/**
	 * Renders logs.
	 *
	 * @return void
	 */
	public function render_logs(): void {
		$this->require_page_capability();

		$wc_competitor_monitor_logs = $this->db->get_logs( 200 );

		include WC_COMPETITOR_MONITOR_PATH . 'admin/views/logs.php';
	}

	/**
	 * Registers the WooCommerce product edit metabox.
	 *
	 * @return void
	 */
	public function register_product_metabox(): void {
		if ( ! WC_Competitor_Monitor_Security::current_user_can_manage() ) {
			return;
		}

		add_meta_box(
			'wc-competitor-monitor-product-mappings',
			__( 'Competitor Monitor Pro', 'competitor-price-stock-monitor' ),
			array( $this, 'render_product_metabox' ),
			'product',
			'normal',
			'high'
		);
	}

	/**
	 * Renders product-level competitor mappings and Pro automation controls.
	 *
	 * @param WP_Post $post Product post.
	 * @return void
	 */
	public function render_product_metabox( WP_Post $post ): void {
		if ( ! WC_Competitor_Monitor_Security::current_user_can_manage() ) {
			return;
		}

		$product_id           = absint( $post->ID );
		$settings             = $this->db->get_settings();
		$mappings             = $this->db->get_mappings(
			array(
				'product_id' => $product_id,
				'limit'      => 50,
			)
		);
		$product_mode         = sanitize_key( (string) get_post_meta( $product_id, WC_Competitor_Monitor_DB::PRODUCT_AUTO_PRICE_MODE_META, true ) );
		$product_mode         = in_array( $product_mode, array( 'enabled', 'disabled' ), true ) ? $product_mode : 'global';
		$global_auto_mode     = 'enabled' === sanitize_key( (string) ( $settings['auto_price_adjustment_mode'] ?? 'disabled' ) );
		$global_mode_label    = $global_auto_mode
			? __( 'Use global setting: enabled', 'competitor-price-stock-monitor' )
			: __( 'Use global setting: disabled', 'competitor-price-stock-monitor' );
		$restore_mode         = sanitize_key( (string) get_post_meta( $product_id, WC_Competitor_Monitor_DB::PRODUCT_ORIGINAL_PRICE_RESTORE_MODE_META, true ) );
		$restore_mode         = in_array( $restore_mode, array( 'enabled', 'disabled' ), true ) ? $restore_mode : 'global';
		$global_restore_mode  = 'enabled' === sanitize_key( (string) ( $settings['original_price_restore_mode'] ?? 'disabled' ) );
		$global_restore_label = $global_restore_mode
			? __( 'Use global setting: enabled', 'competitor-price-stock-monitor' )
			: __( 'Use global setting: disabled', 'competitor-price-stock-monitor' );
		$currency             = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
		$last_adjusted_at     = get_post_meta( $product_id, '_cpsm_last_auto_price_adjusted_at', true );
		$last_old_price       = get_post_meta( $product_id, '_cpsm_last_auto_price_old', true );
		$last_new_price       = get_post_meta( $product_id, '_cpsm_last_auto_price_new', true );
		$original_price       = $this->db->get_original_product_price( $product_id );
		$original_captured_at = get_post_meta( $product_id, WC_Competitor_Monitor_DB::PRODUCT_ORIGINAL_PRICE_CAPTURED_AT_META, true );
		$original_source      = sanitize_key( (string) get_post_meta( $product_id, WC_Competitor_Monitor_DB::PRODUCT_ORIGINAL_PRICE_SOURCE_META, true ) );
		$profit_impact        = $this->db->get_profit_impact_for_product( $product_id );
		$latest_adjustment    = is_array( $profit_impact['latest_adjustment'] ?? null ) ? $profit_impact['latest_adjustment'] : null;
		$restore_status       = $this->monitor->get_original_price_restore_status( $product_id );
		$product_cost_data    = $this->db->get_product_cost_data( $product_id );
		$manual_cost          = get_post_meta( $product_id, WC_Competitor_Monitor_DB::PRODUCT_COST_META, true );
		$pro_is_active        = ! empty( $settings['pro_enabled'] ) && 'active' === sanitize_key( (string) ( $settings['pro_license_status'] ?? '' ) );
		$settings_url         = admin_url( 'admin.php?page=competitor-price-stock-monitor-settings' );

		wp_nonce_field( 'wc_competitor_monitor_product_metabox_' . $product_id, 'wc_competitor_monitor_product_metabox_nonce' );
		?>
		<div class="wccm-product-box">
			<div class="wccm-product-grid">
				<div>
					<label for="wccm_product_auto_price_mode"><strong><?php esc_html_e( 'Apply recommended prices automatically', 'competitor-price-stock-monitor' ); ?></strong></label>
					<select id="wccm_product_auto_price_mode" name="wccm_product_auto_price_mode"<?php disabled( $pro_is_active, false ); ?>>
						<option value="global" <?php selected( $product_mode, 'global' ); ?>><?php echo esc_html( $global_mode_label ); ?></option>
						<option value="enabled" <?php selected( $product_mode, 'enabled' ); ?>><?php esc_html_e( 'Yes, apply recommended prices for this product', 'competitor-price-stock-monitor' ); ?></option>
						<option value="disabled" <?php selected( $product_mode, 'disabled' ); ?>><?php esc_html_e( 'No, never change this product price automatically', 'competitor-price-stock-monitor' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'When allowed and a Pro license is active, competitor checks can update this WooCommerce product to the margin-aware recommended price. Every automatic change creates an alert and can send email.', 'competitor-price-stock-monitor' ); ?></p>
					<?php if ( ! $pro_is_active ) : ?>
						<p class="description"><a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Requires an active Pro license.', 'competitor-price-stock-monitor' ); ?></a></p>
					<?php endif; ?>
				</div>
				<div>
					<label for="wccm_product_original_price_restore_mode"><strong><?php esc_html_e( 'Allow original price restore', 'competitor-price-stock-monitor' ); ?></strong></label>
					<select id="wccm_product_original_price_restore_mode" name="wccm_product_original_price_restore_mode"<?php disabled( $pro_is_active, false ); ?>>
						<option value="global" <?php selected( $restore_mode, 'global' ); ?>><?php echo esc_html( $global_restore_label ); ?></option>
						<option value="enabled" <?php selected( $restore_mode, 'enabled' ); ?>><?php esc_html_e( 'Allow restore for this product', 'competitor-price-stock-monitor' ); ?></option>
						<option value="disabled" <?php selected( $restore_mode, 'disabled' ); ?>><?php esc_html_e( 'Never restore this product', 'competitor-price-stock-monitor' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'When allowed, Pro users can manually restore the captured original price if it is still competitive against in-stock competitors.', 'competitor-price-stock-monitor' ); ?></p>
					<?php if ( ! $pro_is_active ) : ?>
						<p class="description"><a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Requires an active Pro license.', 'competitor-price-stock-monitor' ); ?></a></p>
					<?php endif; ?>
				</div>
				<div>
					<label for="wccm_product_cost"><strong><?php esc_html_e( 'Product cost for margin checks', 'competitor-price-stock-monitor' ); ?></strong></label>
					<input id="wccm_product_cost" type="number" step="0.0001" min="0" name="wccm_product_cost" value="<?php echo esc_attr( (string) $manual_cost ); ?>" placeholder="<?php echo esc_attr_x( 'Example: 42.50', 'product cost example', 'competitor-price-stock-monitor' ); ?>">
					<p class="description"><?php esc_html_e( 'Used as a fallback when no supported cost-of-goods plugin value exists. Automatic price drops are blocked until the plugin can verify the minimum margin.', 'competitor-price-stock-monitor' ); ?></p>
					<?php if ( null !== $product_cost_data['cost'] ) : ?>
						<p class="description">
							<?php
							echo wp_kses_post(
								sprintf(
									/* translators: 1: product cost, 2: cost source label, 3: meta key. */
									__( 'Current cost used: %1$s. Source: %2$s (%3$s).', 'competitor-price-stock-monitor' ),
									$this->format_money( (float) $product_cost_data['cost'] ),
									esc_html( (string) $product_cost_data['source_label'] ),
									esc_html( (string) $product_cost_data['source_key'] )
								)
							);
							?>
						</p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'No product cost is available yet, so automatic price reductions will stay blocked for safety.', 'competitor-price-stock-monitor' ); ?></p>
					<?php endif; ?>
				</div>
				<div class="wccm-product-summary">
					<strong><?php esc_html_e( 'Original customer price', 'competitor-price-stock-monitor' ); ?></strong>
					<?php if ( null !== $original_price ) : ?>
						<p><?php echo $this->format_money( (float) $original_price ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
						<?php if ( isset( $restore_status['current_price'] ) && null !== $restore_status['current_price'] ) : ?>
							<p class="description">
								<?php
								printf(
									/* translators: %s: current price. */
									esc_html__( 'Current price: %s.', 'competitor-price-stock-monitor' ),
									wp_kses_post( $this->format_money( (float) $restore_status['current_price'] ) )
								);
								?>
							</p>
						<?php endif; ?>
						<?php if ( $original_captured_at ) : ?>
							<p class="description">
								<?php
								printf(
									/* translators: 1: date, 2: source. */
									esc_html__( 'Captured on %1$s. Source: %2$s.', 'competitor-price-stock-monitor' ),
									esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $original_captured_at ) ),
									esc_html( $original_source ?: __( 'mapping', 'competitor-price-stock-monitor' ) )
								);
								?>
							</p>
						<?php endif; ?>
						<?php if ( ! empty( $restore_status['can_restore'] ) ) : ?>
							<p>
								<a class="button" href="
								<?php
								echo esc_url(
									wp_nonce_url(
										add_query_arg(
											array(
												'action' => 'wc_competitor_monitor_restore_original_price',
												'product_id' => $product_id,
											),
											admin_url( 'admin-post.php' )
										),
										'wc_competitor_monitor_restore_original_price_' . $product_id
									)
								);
								?>
														"><?php esc_html_e( 'Restore original price', 'competitor-price-stock-monitor' ); ?></a>
							</p>
						<?php elseif ( ! empty( $restore_status['message'] ) ) : ?>
							<p class="description"><?php echo esc_html( (string) $restore_status['message'] ); ?></p>
						<?php endif; ?>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'This price will be captured once before the first competitor mapping or automatic Pro adjustment.', 'competitor-price-stock-monitor' ); ?></p>
					<?php endif; ?>
				</div>
				<div class="wccm-product-summary">
					<strong><?php esc_html_e( 'Last automatic price change', 'competitor-price-stock-monitor' ); ?></strong>
					<?php if ( $last_adjusted_at ) : ?>
						<p>
							<?php
							printf(
								/* translators: 1: old price, 2: new price, 3: date. */
								esc_html__( '%1$s to %2$s on %3$s', 'competitor-price-stock-monitor' ),
								esc_html( (string) $last_old_price ),
								esc_html( (string) $last_new_price ),
								esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $last_adjusted_at ) )
							);
							?>
						</p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'No automatic price change has been applied yet.', 'competitor-price-stock-monitor' ); ?></p>
					<?php endif; ?>
				</div>
				<?php if ( $pro_is_active ) : ?>
				<div class="wccm-product-summary">
					<strong><?php esc_html_e( 'Profit impact 30d', 'competitor-price-stock-monitor' ); ?></strong>
					<p>
						<?php
						printf(
							/* translators: 1: money amount, 2: units sold. */
							esc_html__( '%1$s attributed gross profit from %2$s units.', 'competitor-price-stock-monitor' ),
							wp_kses_post( $this->format_money( (float) ( $profit_impact['attributed_gross_profit'] ?? 0 ) ) ),
							esc_html( number_format_i18n( (float) ( $profit_impact['units_sold_after_adjustment'] ?? 0 ), 0 ) )
						);
						?>
					</p>
					<?php if ( $latest_adjustment ) : ?>
						<p class="description">
							<?php
							printf(
								/* translators: 1: old price, 2: new price, 3: date. */
								esc_html__( 'Latest event: %1$s to %2$s on %3$s.', 'competitor-price-stock-monitor' ),
								wp_kses_post( $this->format_money( (float) $latest_adjustment['old_price'] ) ),
								wp_kses_post( $this->format_money( (float) $latest_adjustment['new_price'] ) ),
								esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $latest_adjustment['changed_at'] ) )
							);
							?>
						</p>
					<?php endif; ?>
					<?php if ( ! empty( $profit_impact['missing_cost_events'] ) ) : ?>
						<p class="description"><?php esc_html_e( 'Some automatic pricing events are excluded from gross profit because product cost was missing at the time of change.', 'competitor-price-stock-monitor' ); ?></p>
					<?php endif; ?>
				</div>
				<?php endif; ?>
			</div>

			<h4><?php esc_html_e( 'Mapped competitors for this product', 'competitor-price-stock-monitor' ); ?></h4>
			<p class="description" id="wccm-no-mappings"<?php echo empty( $mappings ) ? '' : ' hidden'; ?>><?php esc_html_e( 'No competitor URLs are mapped to this product yet.', 'competitor-price-stock-monitor' ); ?></p>
			<table class="widefat striped wccm-product-mappings" id="wccm-mappings-table"<?php echo empty( $mappings ) ? ' hidden' : ''; ?>>
				<thead>
					<tr>
						<th><?php esc_html_e( 'Competitor', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Last price', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Stock', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Last check', 'competitor-price-stock-monitor' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'competitor-price-stock-monitor' ); ?></th>
					</tr>
				</thead>
				<tbody id="wccm-mappings-tbody">
					<?php foreach ( $mappings as $mapping ) : ?>
						<?php echo $this->render_mapping_row_html( $mapping ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h4><?php esc_html_e( 'Add competitor URL', 'competitor-price-stock-monitor' ); ?></h4>
			<div class="wccm-product-grid" id="wccm-quick-add-section"
				data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
				data-nonce="<?php echo esc_attr( wp_create_nonce( 'wccm_quick_add_mapping' ) ); ?>">
				<label>
					<span><?php esc_html_e( 'Competitor name', 'competitor-price-stock-monitor' ); ?></span>
					<input type="text" name="wccm_product_mapping_competitor_name" maxlength="190" placeholder="<?php echo esc_attr_x( 'Amazon', 'competitor name example', 'competitor-price-stock-monitor' ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Currency', 'competitor-price-stock-monitor' ); ?></span>
					<input type="text" name="wccm_product_mapping_currency" maxlength="10" value="<?php echo esc_attr( $currency ); ?>">
				</label>
				<label class="wccm-product-full">
					<span><?php esc_html_e( 'Competitor product URL', 'competitor-price-stock-monitor' ); ?></span>
					<input type="url" name="wccm_product_mapping_competitor_url" placeholder="https://www.amazon.com/example-product">
				</label>
				<label>
					<span><?php esc_html_e( 'Minimum margin %', 'competitor-price-stock-monitor' ); ?></span>
					<input type="number" step="0.01" min="0" max="99" name="wccm_product_mapping_min_margin_percentage" value="20.00">
				</label>
				<label>
					<span><?php esc_html_e( 'Status', 'competitor-price-stock-monitor' ); ?></span>
					<select name="wccm_product_mapping_active">
						<option value="1"><?php esc_html_e( 'Active', 'competitor-price-stock-monitor' ); ?></option>
						<option value="0"><?php esc_html_e( 'Inactive', 'competitor-price-stock-monitor' ); ?></option>
					</select>
				</label>
				<div class="wccm-product-full">
					<button type="button" id="wccm-quick-add-btn" class="button"><?php esc_html_e( 'Add competitor', 'competitor-price-stock-monitor' ); ?></button>
					<p class="description" id="wccm-quick-add-message" hidden></p>
				</div>
			</div>
			<p class="description"><?php esc_html_e( 'Advanced selectors can be edited later from Competitor Monitor > Product Mapping.', 'competitor-price-stock-monitor' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Saves product-level Pro automation and optional quick mapping.
	 *
	 * @param int     $post_id Product ID.
	 * @param WP_Post $post Product post.
	 * @return void
	 */
	public function handle_save_product_metabox( int $post_id, WP_Post $post ): void {
		if ( 'product' !== $post->post_type ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) || ! WC_Competitor_Monitor_Security::current_user_can_manage() ) {
			return;
		}

		$nonce = isset( $_POST['wc_competitor_monitor_product_metabox_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_competitor_monitor_product_metabox_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wc_competitor_monitor_product_metabox_' . absint( $post_id ) ) ) {
			return;
		}

		$this->update_product_auto_price_mode(
			absint( $post_id ),
			isset( $_POST['wccm_product_auto_price_mode'] ) ? sanitize_key( wp_unslash( $_POST['wccm_product_auto_price_mode'] ) ) : 'global'
		);
		$this->update_product_original_price_restore_mode(
			absint( $post_id ),
			isset( $_POST['wccm_product_original_price_restore_mode'] ) ? sanitize_key( wp_unslash( $_POST['wccm_product_original_price_restore_mode'] ) ) : 'global'
		);
		$this->save_product_cost_from_request(
			absint( $post_id ),
			isset( $_POST['wccm_product_cost'] ) ? sanitize_text_field( wp_unslash( $_POST['wccm_product_cost'] ) ) : null
		);

		$url = isset( $_POST['wccm_product_mapping_competitor_url'] ) ? esc_url_raw( wp_unslash( $_POST['wccm_product_mapping_competitor_url'] ) ) : '';
		if ( '' === $url ) {
			return;
		}

		$validation = WC_Competitor_Monitor_Security::validate_competitor_url( $url );
		if ( is_wp_error( $validation ) ) {
			$this->add_product_metabox_notice( 'error', $validation->get_error_message() );
			return;
		}

		if ( $this->product_mapping_url_exists( absint( $post_id ), $url ) ) {
			$this->add_product_metabox_notice( 'warning', __( 'This competitor URL is already mapped to the product.', 'competitor-price-stock-monitor' ) );
			return;
		}

		$host            = (string) wp_parse_url( $url, PHP_URL_HOST );
		$competitor_name = isset( $_POST['wccm_product_mapping_competitor_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wccm_product_mapping_competitor_name'] ) ) : '';
		$competitor_name = '' !== $competitor_name ? $competitor_name : preg_replace( '/^www\./', '', $host );
		$currency        = isset( $_POST['wccm_product_mapping_currency'] ) ? WC_Competitor_Monitor_Security::sanitize_currency( sanitize_text_field( wp_unslash( $_POST['wccm_product_mapping_currency'] ) ) ) : '';
		$margin          = isset( $_POST['wccm_product_mapping_min_margin_percentage'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['wccm_product_mapping_min_margin_percentage'] ) ) : 20.0;
		$active          = isset( $_POST['wccm_product_mapping_active'] ) ? absint( wp_unslash( $_POST['wccm_product_mapping_active'] ) ) : 1;

		$this->db->capture_original_product_price( absint( $post_id ), 'product_metabox_mapping_created' );

		$mapping_id = $this->db->insert_mapping(
			array(
				'product_id'                    => absint( $post_id ),
				'competitor_name'               => $competitor_name,
				'competitor_product_title'      => $this->competitor_product_title_from_url( $url, $competitor_name ),
				'competitor_url'                => $url,
				'price_selector'                => '',
				'stock_selector'                => '',
				'currency'                      => $currency,
				'min_margin_percentage'         => $margin,
				'suggested_increase_mode'       => 'global',
				'suggested_increase_percentage' => null,
				'active'                        => $active ? 1 : 0,
			)
		);

		if ( $mapping_id > 0 ) {
			$this->sync->sync_mapping( $mapping_id, 'product_metabox_created' );
			$this->add_product_metabox_notice( 'success', __( 'Competitor mapping added to this product.', 'competitor-price-stock-monitor' ) );
		}
	}

	/**
	 * Handles mapping save.
	 *
	 * @return void
	 */
	public function handle_save_mapping(): void {
		WC_Competitor_Monitor_Security::require_capability();
		check_admin_referer( 'wc_competitor_monitor_save_mapping' );

		$mapping_id = isset( $_POST['mapping_id'] ) ? absint( wp_unslash( $_POST['mapping_id'] ) ) : 0;
		$url        = isset( $_POST['competitor_url'] ) ? esc_url_raw( wp_unslash( $_POST['competitor_url'] ) ) : '';
		$validation = WC_Competitor_Monitor_Security::validate_competitor_url( $url );

		if ( is_wp_error( $validation ) ) {
			$this->redirect_with_notice( 'competitor-price-stock-monitor-products', 'error', $validation->get_error_message() );
		}

		$data = array(
			'product_id'                    => isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0,
			'competitor_name'               => isset( $_POST['competitor_name'] ) ? sanitize_text_field( wp_unslash( $_POST['competitor_name'] ) ) : '',
			'competitor_url'                => $url,
			'price_selector'                => isset( $_POST['price_selector'] ) ? WC_Competitor_Monitor_Security::sanitize_selector( sanitize_text_field( wp_unslash( $_POST['price_selector'] ) ) ) : '',
			'stock_selector'                => isset( $_POST['stock_selector'] ) ? WC_Competitor_Monitor_Security::sanitize_selector( sanitize_text_field( wp_unslash( $_POST['stock_selector'] ) ) ) : '',
			'browser_user_agent'            => isset( $_POST['browser_user_agent'] ) ? sanitize_text_field( wp_unslash( $_POST['browser_user_agent'] ) ) : '',
			'browser_cookie_header'         => isset( $_POST['browser_cookie_header'] ) ? WC_Competitor_Monitor_Security::sanitize_cookie_header( sanitize_textarea_field( wp_unslash( $_POST['browser_cookie_header'] ) ) ) : '',
			'currency'                      => isset( $_POST['currency'] ) ? WC_Competitor_Monitor_Security::sanitize_currency( sanitize_text_field( wp_unslash( $_POST['currency'] ) ) ) : '',
			'min_margin_percentage'         => isset( $_POST['min_margin_percentage'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['min_margin_percentage'] ) ) : 20,
			'suggested_increase_mode'       => isset( $_POST['suggested_increase_mode'] ) ? sanitize_key( wp_unslash( $_POST['suggested_increase_mode'] ) ) : 'global',
			'suggested_increase_percentage' => isset( $_POST['suggested_increase_percentage'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['suggested_increase_percentage'] ) ) : null,
			'active'                        => isset( $_POST['active'] ) ? 1 : 0,
		);

		if ( $data['product_id'] <= 0 ) {
			$this->redirect_with_notice( 'competitor-price-stock-monitor-products', 'error', __( 'Please select a WooCommerce product.', 'competitor-price-stock-monitor' ) );
		}

		if ( '' === $data['competitor_name'] ) {
			$this->redirect_with_notice( 'competitor-price-stock-monitor-products', 'error', __( 'Please enter a competitor name.', 'competitor-price-stock-monitor' ) );
		}

		if ( $this->product_mapping_url_exists( $data['product_id'], $url, $mapping_id ) ) {
			$this->redirect_with_notice( 'competitor-price-stock-monitor-products', 'error', __( 'This competitor URL is already mapped to this product.', 'competitor-price-stock-monitor' ) );
		}

		$this->db->capture_original_product_price( absint( $data['product_id'] ), $mapping_id > 0 ? 'mapping_updated' : 'mapping_created' );

		$this->update_product_auto_price_mode(
			$data['product_id'],
			isset( $_POST['auto_price_adjustment_mode'] ) ? sanitize_key( wp_unslash( $_POST['auto_price_adjustment_mode'] ) ) : 'global'
		);

		if ( $mapping_id > 0 ) {
			$this->db->update_mapping( $mapping_id, $data );
			$message = __( 'Mapping updated.', 'competitor-price-stock-monitor' );
		} else {
			$data['competitor_product_title'] = $this->competitor_product_title_from_url( $url, $data['competitor_name'] );
			$mapping_id                       = $this->db->insert_mapping( $data );
			$message                          = __( 'Mapping created.', 'competitor-price-stock-monitor' );
		}

		if ( $mapping_id > 0 ) {
			$this->sync->sync_mapping( $mapping_id, 'admin_save' );
		}

		$this->redirect_with_notice( 'competitor-price-stock-monitor-products', 'updated', $message );
	}

	/**
	 * Handles manual Pro original price restore from the product edit screen.
	 *
	 * @return void
	 */
	public function handle_restore_original_price(): void {
		WC_Competitor_Monitor_Security::require_capability();

		$product_id = isset( $_REQUEST['product_id'] ) ? absint( wp_unslash( $_REQUEST['product_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified below before mutation.
		check_admin_referer( 'wc_competitor_monitor_restore_original_price_' . $product_id );

		if ( $product_id <= 0 || ! current_user_can( 'edit_post', $product_id ) ) {
			wp_die( esc_html__( 'You do not have permission to restore this product price.', 'competitor-price-stock-monitor' ) );
		}

		$result = $this->monitor->restore_original_product_price( $product_id, get_current_user_id() );
		if ( empty( $result['success'] ) ) {
			$this->add_product_metabox_notice(
				'error',
				(string) ( $result['message'] ?? __( 'Original price restore was blocked.', 'competitor-price-stock-monitor' ) )
			);
		} else {
			$this->add_product_metabox_notice(
				'success',
				__( 'Original customer price restored.', 'competitor-price-stock-monitor' )
			);
		}

		wp_safe_redirect( get_edit_post_link( $product_id, 'raw' ) ?: admin_url( 'edit.php?post_type=product' ) );
		exit;
	}

	/**
	 * Handles mapping delete.
	 *
	 * @return void
	 */
	public function handle_delete_mapping(): void {
		WC_Competitor_Monitor_Security::require_capability();
		$mapping_id = isset( $_POST['mapping_id'] ) ? absint( wp_unslash( $_POST['mapping_id'] ) ) : 0;
		check_admin_referer( 'wc_competitor_monitor_delete_mapping_' . $mapping_id );

		if ( $mapping_id > 0 ) {
			$mapping = $this->db->get_mapping( $mapping_id );
			if ( $mapping ) {
				$this->sync->notify_mapping_deleted( $mapping );
			}
			$this->db->delete_mapping( $mapping_id );
		}

		$this->redirect_with_notice( 'competitor-price-stock-monitor-products', 'updated', __( 'Mapping deleted.', 'competitor-price-stock-monitor' ) );
	}

	/**
	 * Handles mapping toggle.
	 *
	 * @return void
	 */
	public function handle_toggle_mapping(): void {
		WC_Competitor_Monitor_Security::require_capability();
		$mapping_id = isset( $_POST['mapping_id'] ) ? absint( wp_unslash( $_POST['mapping_id'] ) ) : 0;
		check_admin_referer( 'wc_competitor_monitor_toggle_mapping_' . $mapping_id );

		$mapping = $this->db->get_mapping( $mapping_id );
		if ( $mapping ) {
			$this->db->set_mapping_active( $mapping_id, empty( $mapping->active ) );
			$this->sync->sync_mapping( $mapping_id, 'admin_toggle' );
		}

		$this->redirect_with_notice( 'competitor-price-stock-monitor-products', 'updated', __( 'Mapping status updated.', 'competitor-price-stock-monitor' ) );
	}

	/**
	 * Handles manual check.
	 *
	 * @return void
	 */
	public function handle_run_check(): void {
		WC_Competitor_Monitor_Security::require_capability();
		$mapping_id = isset( $_POST['mapping_id'] ) ? absint( wp_unslash( $_POST['mapping_id'] ) ) : 0;
		check_admin_referer( 'wc_competitor_monitor_run_check_' . $mapping_id );

		if ( $mapping_id > 0 ) {
			$result = $this->monitor->check_mapping( $mapping_id );
			if ( empty( $result['success'] ) ) {
				$this->redirect_with_notice( 'competitor-price-stock-monitor-products', 'error', (string) ( $result['error'] ?? __( 'Check failed.', 'competitor-price-stock-monitor' ) ) );
			}
			$this->monitor->sync_profit_impact();
			$this->sync->sync_mapping( $mapping_id, 'manual_check' );
			$this->redirect_with_notice( 'competitor-price-stock-monitor-products', 'updated', __( 'Manual check completed.', 'competitor-price-stock-monitor' ) );
		}

		$settings = $this->db->get_settings();
		$mappings = $this->db->get_active_mappings_for_check( max( 1, absint( $settings['batch_size'] ) ) );
		foreach ( $mappings as $mapping ) {
			$this->monitor->check_mapping( $mapping );
		}
		$this->monitor->sync_profit_impact();
		$this->sync->sync_all_mappings();

		$this->redirect_with_notice( 'competitor-price-stock-monitor', 'updated', __( 'Batch check completed.', 'competitor-price-stock-monitor' ) );
	}

	/**
	 * Handles settings save.
	 *
	 * @return void
	 */
	public function handle_save_settings(): void {
		WC_Competitor_Monitor_Security::require_capability();
		check_admin_referer( 'wc_competitor_monitor_save_settings' );

		$previous = $this->db->get_settings();
		$settings = array(
			'alert_email'                         => isset( $_POST['alert_email'] ) ? sanitize_email( wp_unslash( $_POST['alert_email'] ) ) : get_option( 'admin_email' ),
			'email_alerts'                        => isset( $_POST['email_alerts'] ) ? 1 : 0,
			'price_change_threshold'              => isset( $_POST['price_change_threshold'] ) ? max( 0, (float) sanitize_text_field( wp_unslash( $_POST['price_change_threshold'] ) ) ) : 5.0,
			'suggested_increase_limit_mode'       => isset( $_POST['suggested_increase_limit_mode'] ) ? sanitize_key( wp_unslash( $_POST['suggested_increase_limit_mode'] ) ) : 'percent',
			'suggested_increase_limit_percentage' => isset( $_POST['suggested_increase_limit_percentage'] ) ? max( 0, min( 999.99, (float) sanitize_text_field( wp_unslash( $_POST['suggested_increase_limit_percentage'] ) ) ) ) : 5.0,
			'auto_price_adjustment_mode'          => isset( $_POST['auto_price_adjustment_mode'] ) ? sanitize_key( wp_unslash( $_POST['auto_price_adjustment_mode'] ) ) : (string) ( $previous['auto_price_adjustment_mode'] ?? 'disabled' ),
			'auto_price_kill_switch'              => isset( $_POST['auto_price_kill_switch'] ) ? 1 : 0,
			'original_price_restore_mode'         => isset( $_POST['original_price_restore_mode'] ) ? sanitize_key( wp_unslash( $_POST['original_price_restore_mode'] ) ) : (string) ( $previous['original_price_restore_mode'] ?? 'disabled' ),
			'check_frequency'                     => isset( $_POST['check_frequency'] ) ? sanitize_key( wp_unslash( $_POST['check_frequency'] ) ) : 'daily',
			'user_agent'                          => isset( $_POST['user_agent'] ) ? sanitize_text_field( wp_unslash( $_POST['user_agent'] ) ) : '',
			'timeout'                             => isset( $_POST['timeout'] ) ? max( 3, min( 30, absint( wp_unslash( $_POST['timeout'] ) ) ) ) : 10,
			'max_response_size'                   => isset( $_POST['max_response_size'] ) ? max( 10240, min( 5242880, absint( wp_unslash( $_POST['max_response_size'] ) ) ) ) : 1048576,
			'batch_size'                          => isset( $_POST['batch_size'] ) ? max( 1, min( 100, absint( wp_unslash( $_POST['batch_size'] ) ) ) ) : 10,
			'delete_data_on_uninstall'            => isset( $_POST['delete_data_on_uninstall'] ) ? 1 : 0,
			'pro_enabled'                         => isset( $_POST['pro_enabled'] ) ? 1 : 0,
			'pro_saas_url'                        => 'https://competitor-monitor-pro-production.up.railway.app',
		);

		if ( ! in_array( $settings['check_frequency'], array( 'daily', 'twelve_hours', 'six_hours', 'hourly' ), true ) ) {
			$settings['check_frequency'] = 'daily';
		}

		if ( ! in_array( $settings['suggested_increase_limit_mode'], array( 'percent', 'none' ), true ) ) {
			$settings['suggested_increase_limit_mode'] = 'percent';
		}

		if ( ! in_array( $settings['auto_price_adjustment_mode'], array( 'disabled', 'enabled' ), true ) ) {
			$settings['auto_price_adjustment_mode'] = 'disabled';
		}

		if ( ! in_array( $settings['original_price_restore_mode'], array( 'disabled', 'enabled' ), true ) ) {
			$settings['original_price_restore_mode'] = 'disabled';
		}

		$pro_is_active = ! empty( $previous['pro_enabled'] ) && 'active' === (string) ( $previous['pro_license_status'] ?? '' );
		if ( ! $pro_is_active ) {
			$settings['check_frequency']             = 'daily';
			$settings['auto_price_adjustment_mode']  = 'disabled';
			$settings['original_price_restore_mode'] = 'disabled';
		}

		if ( '' === $settings['user_agent'] ) {
			$settings['user_agent'] = $this->db->default_settings()['user_agent'];
		}

		$this->db->update_settings( $settings );

		WC_Competitor_Monitor_Activator::ensure_cron( $settings['check_frequency'] );

		$this->redirect_with_notice( 'competitor-price-stock-monitor-settings', 'updated', __( 'Settings saved.', 'competitor-price-stock-monitor' ) );
	}

	/**
	 * Handles the prominent Pro automatic pricing toggle.
	 *
	 * @return void
	 */
	public function handle_save_auto_pricing(): void {
		WC_Competitor_Monitor_Security::require_capability();
		check_admin_referer( 'wc_competitor_monitor_save_auto_pricing' );

		$mode = isset( $_POST['auto_price_adjustment_mode'] ) ? sanitize_key( wp_unslash( $_POST['auto_price_adjustment_mode'] ) ) : 'disabled';
		if ( ! in_array( $mode, array( 'disabled', 'enabled' ), true ) ) {
			$mode = 'disabled';
		}

		$current_settings = $this->db->get_settings();
		$pro_is_active    = ! empty( $current_settings['pro_enabled'] ) && 'active' === (string) ( $current_settings['pro_license_status'] ?? '' );
		if ( ! $pro_is_active ) {
			$mode = 'disabled';
		}

		$this->db->update_settings(
			array(
				'auto_price_adjustment_mode' => $mode,
			)
		);

		$message = 'enabled' === $mode
			? __( 'Automatic recommended price updates are enabled for products that use the global setting.', 'competitor-price-stock-monitor' )
			: __( 'Automatic recommended price updates are disabled globally.', 'competitor-price-stock-monitor' );

		$this->redirect_with_notice( 'competitor-price-stock-monitor-settings', 'updated', $message );
	}

	/**
	 * Handles Pro license activation.
	 *
	 * @return void
	 */
	public function handle_activate_pro_license(): void {
		WC_Competitor_Monitor_Security::require_capability();
		check_admin_referer( 'wc_competitor_monitor_activate_pro_license' );

		$saas_url    = 'https://competitor-monitor-pro-production.up.railway.app';
		$license_key = isset( $_POST['pro_license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['pro_license_key'] ) ) : '';
		$result      = $this->pro_client->activate_license( $saas_url, $license_key );

		if ( empty( $result['success'] ) ) {
			$this->redirect_with_notice( 'competitor-price-stock-monitor-settings', 'error', (string) ( $result['error'] ?? __( 'License activation failed.', 'competitor-price-stock-monitor' ) ) );
		}

		$sync_result = $this->sync->sync_all_mappings();
		$message     = empty( $sync_result['success'] )
			? sprintf(
				/* translators: %s: sync error. */
				__( 'Pro bridge activated. Mapping sync needs attention: %s', 'competitor-price-stock-monitor' ),
				(string) ( $sync_result['error'] ?? '' )
			)
			: sprintf(
				/* translators: %d: number of mappings synchronized. */
				_n( 'Pro bridge activated. %d mapping synchronized with SaaS.', 'Pro bridge activated. %d mappings synchronized with SaaS.', absint( $sync_result['synced'] ?? 0 ), 'competitor-price-stock-monitor' ),
				absint( $sync_result['synced'] ?? 0 )
			);

		$this->redirect_with_notice( 'competitor-price-stock-monitor-settings', 'updated', $message );
	}

	/**
	 * Handles Pro bridge key rotation.
	 *
	 * @return void
	 */
	public function handle_rotate_bridge(): void {
		WC_Competitor_Monitor_Security::require_capability();
		check_admin_referer( 'wc_competitor_monitor_rotate_bridge' );

		$result = $this->pro_client->rotate_bridge_credentials();
		if ( empty( $result['success'] ) ) {
			$this->redirect_with_notice( 'competitor-price-stock-monitor-settings', 'error', (string) ( $result['error'] ?? __( 'Bridge credential rotation failed.', 'competitor-price-stock-monitor' ) ) );
		}

		$this->redirect_with_notice( 'competitor-price-stock-monitor-settings', 'updated', __( 'Pro bridge credentials rotated.', 'competitor-price-stock-monitor' ) );
	}

	/**
	 * Handles alert read state.
	 *
	 * @return void
	 */
	public function handle_mark_alert_read(): void {
		WC_Competitor_Monitor_Security::require_capability();
		$alert_id = isset( $_POST['alert_id'] ) ? absint( wp_unslash( $_POST['alert_id'] ) ) : 0;
		check_admin_referer( 'wc_competitor_monitor_mark_alert_read_' . $alert_id );

		if ( $alert_id > 0 ) {
			$this->db->mark_alert_read( $alert_id );
		}

		$this->redirect_with_notice( 'competitor-price-stock-monitor-alerts', 'updated', __( 'Alert marked as read.', 'competitor-price-stock-monitor' ) );
	}

	/**
	 * Handles alert delete.
	 *
	 * @return void
	 */
	public function handle_delete_alert(): void {
		WC_Competitor_Monitor_Security::require_capability();
		$alert_id = isset( $_POST['alert_id'] ) ? absint( wp_unslash( $_POST['alert_id'] ) ) : 0;
		check_admin_referer( 'wc_competitor_monitor_delete_alert_' . $alert_id );

		if ( $alert_id > 0 ) {
			$this->db->delete_alert( $alert_id );
		}

		$this->redirect_with_notice( 'competitor-price-stock-monitor-alerts', 'updated', __( 'Alert deleted.', 'competitor-price-stock-monitor' ) );
	}

	/**
	 * Handles log cleanup.
	 *
	 * @return void
	 */
	public function handle_clear_logs(): void {
		WC_Competitor_Monitor_Security::require_capability();
		check_admin_referer( 'wc_competitor_monitor_clear_logs' );

		$this->db->clear_logs();
		$this->redirect_with_notice( 'competitor-price-stock-monitor-logs', 'updated', __( 'Logs cleared.', 'competitor-price-stock-monitor' ) );
	}

	/**
	 * Gets an admin menu capability string.
	 *
	 * @return string
	 */
	private function menu_capability(): string {
		return current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';
	}

	/**
	 * Enforces page capability.
	 *
	 * @return void
	 */
	private function require_page_capability(): void {
		WC_Competitor_Monitor_Security::require_capability();
		$this->render_notices();
	}

	/**
	 * Stores the per-product Pro automatic pricing override.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $mode Override mode.
	 * @return void
	 */
	private function update_product_auto_price_mode( int $product_id, string $mode ): void {
		$product_id = absint( $product_id );
		if ( $product_id <= 0 ) {
			return;
		}

		$mode = $this->sanitize_product_auto_price_mode( $mode );
		if ( 'global' === $mode ) {
			delete_post_meta( $product_id, WC_Competitor_Monitor_DB::PRODUCT_AUTO_PRICE_MODE_META );
			return;
		}

		update_post_meta( $product_id, WC_Competitor_Monitor_DB::PRODUCT_AUTO_PRICE_MODE_META, $mode );
	}

	/**
	 * Stores the per-product Pro original price restore override.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $mode Override mode.
	 * @return void
	 */
	private function update_product_original_price_restore_mode( int $product_id, string $mode ): void {
		$product_id = absint( $product_id );
		if ( $product_id <= 0 ) {
			return;
		}

		$mode = $this->sanitize_product_original_price_restore_mode( $mode );
		if ( 'global' === $mode ) {
			delete_post_meta( $product_id, WC_Competitor_Monitor_DB::PRODUCT_ORIGINAL_PRICE_RESTORE_MODE_META );
			return;
		}

		update_post_meta( $product_id, WC_Competitor_Monitor_DB::PRODUCT_ORIGINAL_PRICE_RESTORE_MODE_META, $mode );
	}

	/**
	 * Saves the product cost fallback from the product metabox.
	 *
	 * @param int         $product_id Product ID.
	 * @param string|null $raw_cost Raw cost value from verified product metabox submission.
	 * @return void
	 */
	private function save_product_cost_from_request( int $product_id, ?string $raw_cost ): void {
		if ( null === $raw_cost ) {
			return;
		}

		$raw_cost = trim( $raw_cost );
		if ( '' === $raw_cost ) {
			$this->db->save_product_cost( $product_id, null );
			return;
		}

		$normalized = function_exists( 'wc_format_decimal' )
			? wc_format_decimal( $raw_cost )
			: str_replace( ',', '.', $raw_cost );

		if ( ! is_numeric( $normalized ) || (float) $normalized < 0 ) {
			$this->add_product_metabox_notice( 'error', __( 'Product cost must be a positive number or empty.', 'competitor-price-stock-monitor' ) );
			return;
		}

		$this->db->save_product_cost( $product_id, (float) $normalized );
	}

	/**
	 * Checks whether a competitor URL is already mapped to a product.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $url Competitor URL.
	 * @param int    $exclude_mapping_id Mapping ID to exclude (used when editing an existing mapping).
	 * @return bool
	 */
	private function product_mapping_url_exists( int $product_id, string $url, int $exclude_mapping_id = 0 ): bool {
		$url      = esc_url_raw( $url );
		$mappings = $this->db->get_mappings(
			array(
				'product_id' => absint( $product_id ),
				'limit'      => 200,
			)
		);

		foreach ( $mappings as $mapping ) {
			if ( $exclude_mapping_id > 0 && absint( $mapping->id ) === $exclude_mapping_id ) {
				continue;
			}
			if ( untrailingslashit( (string) $mapping->competitor_url ) === untrailingslashit( $url ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Stores a short product edit notice for the next request.
	 *
	 * @param string $type Notice type.
	 * @param string $message Notice message.
	 * @return void
	 */
	private function add_product_metabox_notice( string $type, string $message ): void {
		set_transient(
			'wc_competitor_monitor_product_notice_' . get_current_user_id(),
			array(
				'type'    => sanitize_key( $type ),
				'message' => sanitize_text_field( $message ),
			),
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * Renders product edit notices created during save_post.
	 *
	 * @return void
	 */
	public function render_product_metabox_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		$key    = 'wc_competitor_monitor_product_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( $key );
		$type  = 'error' === ( $notice['type'] ?? '' ) ? 'error' : ( 'warning' === ( $notice['type'] ?? '' ) ? 'warning' : 'success' );
		$class = 'notice notice-' . $type;

		printf(
			'<div class="%1$s"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html( (string) $notice['message'] )
		);
	}

	/**
	 * Shows a warning when server-level WP-Cron is disabled and no external cron is configured.
	 *
	 * @return void
	 */
	public function render_cron_disabled_notice(): void {
		if ( ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! str_contains( (string) $screen->id, 'competitor-price-stock-monitor' ) ) {
			return;
		}

		$settings_url = admin_url( 'admin.php?page=competitor-price-stock-monitor-settings' );
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			wp_kses(
				sprintf(
					/* translators: %s: link to documentation */
					__( '<strong>Competitor Monitor:</strong> WP-Cron is disabled on this server (<code>DISABLE_WP_CRON</code> is set). Automatic monitoring will not run until you configure a real server cron job to call <code>wp-cron.php</code>. <a href="%s">Go to Settings</a>.', 'competitor-price-stock-monitor' ),
					esc_url( $settings_url )
				),
				array(
					'strong' => array(),
					'code'   => array(),
					'a'      => array( 'href' => array() ),
				)
			)
		);
	}

	/**
	 * Sanitizes the per-product automatic pricing mode.
	 *
	 * @param string $mode Mode.
	 * @return string
	 */
	private function sanitize_product_auto_price_mode( string $mode ): string {
		$mode = sanitize_key( $mode );
		return in_array( $mode, array( 'global', 'enabled', 'disabled' ), true ) ? $mode : 'global';
	}

	/**
	 * Sanitizes the per-product original price restore mode.
	 *
	 * @param string $mode Mode.
	 * @return string
	 */
	private function sanitize_product_original_price_restore_mode( string $mode ): string {
		$mode = sanitize_key( $mode );
		return in_array( $mode, array( 'global', 'enabled', 'disabled' ), true ) ? $mode : 'global';
	}

	/**
	 * Renders redirected notices.
	 *
	 * @return void
	 */
	private function render_notices(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirected admin notices are read-only.
		if ( empty( $_GET['wccm_notice'] ) || empty( $_GET['wccm_message'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirected admin notices are read-only.
		$type = sanitize_key( wp_unslash( $_GET['wccm_notice'] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirected admin notices are read-only.
		$message = sanitize_text_field( wp_unslash( $_GET['wccm_message'] ) );
		$class   = 'error' === $type ? 'notice notice-error' : 'notice notice-success';

		printf(
			'<div class="%1$s"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html( $message )
		);
	}

	/**
	 * Redirects to a plugin page with a notice.
	 *
	 * @param string $page Page slug.
	 * @param string $type Notice type.
	 * @param string $message Message.
	 * @return never
	 */
	private function redirect_with_notice( string $page, string $type, string $message ): never {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => sanitize_key( $page ),
					'wccm_notice'  => sanitize_key( $type ),
					'wccm_message' => $message,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Formats a price for admin display.
	 *
	 * @param float|null $price Price.
	 * @return string
	 */
	public function format_price( ?float $price ): string {
		if ( null === $price || $price <= 0 ) {
			return '&mdash;';
		}

		if ( function_exists( 'wc_price' ) ) {
			return wp_kses_post( wc_price( $price ) );
		}

		return esc_html( number_format_i18n( $price, 2 ) );
	}

	/**
	 * Formats an impact amount, including zero or negative values.
	 *
	 * @param float|null $amount Amount.
	 * @return string
	 */
	public function format_money( ?float $amount ): string {
		if ( null === $amount ) {
			return '&mdash;';
		}

		if ( function_exists( 'wc_price' ) ) {
			return wp_kses_post( wc_price( $amount ) );
		}

		return esc_html( number_format_i18n( $amount, 2 ) );
	}

	/**
	 * Gets the best available competitor product title for display.
	 *
	 * @param object $mapping Mapping row.
	 * @return string
	 */
	private function competitor_product_title( object $mapping ): string {
		$title = isset( $mapping->competitor_product_title ) ? sanitize_text_field( (string) $mapping->competitor_product_title ) : '';
		if ( '' !== $title ) {
			return $title;
		}

		return $this->competitor_product_title_from_url(
			(string) ( $mapping->competitor_url ?? '' ),
			$this->competitor_store_name( $mapping )
		);
	}

	/**
	 * Gets the competitor store label for display.
	 *
	 * @param object $mapping Mapping row.
	 * @return string
	 */
	private function competitor_store_name( object $mapping ): string {
		$name = isset( $mapping->competitor_name ) ? sanitize_text_field( (string) $mapping->competitor_name ) : '';
		if ( '' !== $name ) {
			return $name;
		}

		$host = wp_parse_url( (string) ( $mapping->competitor_url ?? '' ), PHP_URL_HOST );
		$host = is_string( $host ) ? preg_replace( '/^www\./', '', $host ) : '';

		return $host ?: __( 'Competitor', 'competitor-price-stock-monitor' );
	}

	/**
	 * Builds a readable product title from a competitor URL slug.
	 *
	 * @param string $url Competitor URL.
	 * @param string $fallback Fallback title.
	 * @return string
	 */
	private function competitor_product_title_from_url( string $url, string $fallback ): string {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$path = is_string( $path ) ? trim( $path, '/' ) : '';
		if ( '' === $path ) {
			return sanitize_text_field( $fallback );
		}

		$parts = array_values( array_filter( explode( '/', $path ) ) );
		$slug  = $this->competitor_url_product_slug( $parts );
		$slug  = is_string( $slug ) ? rawurldecode( $slug ) : '';
		$slug  = preg_replace( '/\.[a-z0-9]{2,8}$/i', '', $slug );
		$slug  = preg_replace( '/[-_+]+/', ' ', (string) $slug );
		$slug  = preg_replace( '/\s+/', ' ', (string) $slug );
		$title = trim( (string) $slug );

		if ( '' === $title ) {
			return sanitize_text_field( $fallback );
		}

		return sanitize_text_field( ucwords( strtolower( $title ) ) );
	}

	/**
	 * Finds the most product-like slug segment in a competitor URL path.
	 *
	 * @param array<int,string> $parts URL path segments.
	 * @return string
	 */
	private function competitor_url_product_slug( array $parts ): string {
		foreach ( $parts as $index => $part ) {
			$part = strtolower( rawurldecode( (string) $part ) );
			if ( 'dp' === $part && ! empty( $parts[ $index - 1 ] ) ) {
				return (string) $parts[ $index - 1 ];
			}

			if ( 'product' === $part && 'gp' === strtolower( (string) ( $parts[ $index - 1 ] ?? '' ) ) && ! empty( $parts[ $index + 1 ] ) ) {
				return (string) $parts[ $index + 1 ];
			}
		}

		$ignored    = array( 'p', 'dp', 'gp', 'es', 'en', 'fr', 'de', 'it', 'pt', 'shop', 'catalog', 'category', 'product', 'products', 'producto', 'productos' );
		$candidates = array();
		foreach ( $parts as $part ) {
			$decoded = rawurldecode( (string) $part );
			$key     = strtolower( $decoded );

			if ( in_array( $key, $ignored, true ) || str_starts_with( $key, 'ref=' ) || preg_match( '/^[a-z0-9]{8,14}$/i', $decoded ) ) {
				continue;
			}

			$candidates[] = $decoded;
		}

		usort(
			$candidates,
			static function ( string $left, string $right ): int {
				return strlen( $right ) <=> strlen( $left );
			}
		);

		return (string) ( $candidates[0] ?? end( $parts ) );
	}

	/**
	 * Truncates long competitor product titles for compact product edit tables.
	 *
	 * @param string $title Product title.
	 * @return string
	 */
	private function truncate_competitor_product_title( string $title ): string {
		$title = sanitize_text_field( $title );
		if ( strlen( $title ) <= 70 ) {
			return $title;
		}

		return wp_html_excerpt( $title, 70, '...' );
	}

	/**
	 * Gets product options for the non-JavaScript Product Mapping fallback.
	 *
	 * @param int $limit Maximum products to load.
	 * @return array<int,string>
	 */
	private function product_select_options( int $limit = 300 ): array {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$product_ids = wc_get_products(
			array(
				'limit'   => max( 1, $limit ),
				'orderby' => 'name',
				'order'   => 'ASC',
				'return'  => 'ids',
				'status'  => array( 'publish', 'private', 'draft' ),
			)
		);

		$options = array();
		foreach ( $product_ids as $product_id ) {
			$product_id = absint( $product_id );
			$product    = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$label                  = method_exists( $product, 'get_formatted_name' ) ? (string) $product->get_formatted_name() : $this->product_title( $product_id );
			$options[ $product_id ] = rawurldecode( wp_strip_all_tags( $label ) );
		}

		return $options;
	}

	/**
	 * Gets product title.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public function product_title( int $product_id ): string {
		$title = get_the_title( $product_id );
		if ( $title ) {
			return $title;
		}

		return sprintf(
			/* translators: %d: product ID. */
			__( 'Product #%d', 'competitor-price-stock-monitor' ),
			$product_id
		);
	}

	/**
	 * Builds display context for an alert row.
	 *
	 * @param object $alert Alert row.
	 * @return array<string,string>
	 */
	public function alert_context( object $alert ): array {
		$mapping = (object) array(
			'competitor_name'          => sanitize_text_field( (string) ( $alert->mapping_competitor_name ?? '' ) ),
			'competitor_product_title' => sanitize_text_field( (string) ( $alert->mapping_competitor_product_title ?? '' ) ),
			'competitor_url'           => esc_url_raw( (string) ( $alert->mapping_competitor_url ?? '' ) ),
		);

		return array(
			'woocommerce_product' => $this->product_title( absint( $alert->product_id ) ),
			'competitor_name'     => $this->competitor_store_name( $mapping ),
			'competitor_product'  => $this->competitor_product_title( $mapping ),
			'competitor_url'      => esc_url_raw( (string) $mapping->competitor_url ),
		);
	}

	/**
	 * Builds a clearer alert message for legacy and current alerts.
	 *
	 * @param object $alert Alert row.
	 * @return string
	 */
	public function alert_display_message( object $alert ): string {
		$context            = $this->alert_context( $alert );
		$type               = sanitize_key( (string) $alert->alert_type );
		$stored_message     = sanitize_textarea_field( (string) $alert->message );
		$price_change_text  = $this->alert_price_change_text( $stored_message );
		$competitor_product = $context['competitor_product'];
		$competitor_name    = $context['competitor_name'];
		$woo_product        = $context['woocommerce_product'];

		switch ( $type ) {
			case 'competitor_price_dropped':
				$message = sprintf(
					/* translators: 1: competitor name, 2: competitor product title, 3: WooCommerce product name. */
					__( '%1$s dropped the competitor price for "%2$s". Mapped WooCommerce product: %3$s.', 'competitor-price-stock-monitor' ),
					$competitor_name,
					$competitor_product,
					$woo_product
				);
				return '' !== $price_change_text ? $message . ' ' . $price_change_text : $message;

			case 'competitor_price_increased':
				$message = sprintf(
					/* translators: 1: competitor name, 2: competitor product title, 3: WooCommerce product name. */
					__( '%1$s increased the competitor price for "%2$s". Mapped WooCommerce product: %3$s.', 'competitor-price-stock-monitor' ),
					$competitor_name,
					$competitor_product,
					$woo_product
				);
				return '' !== $price_change_text ? $message . ' ' . $price_change_text : $message;

			case 'competitor_out_of_stock':
				return sprintf(
					/* translators: 1: competitor name, 2: competitor product title, 3: WooCommerce product name. */
					__( '%1$s is out of stock for competitor product "%2$s". Mapped WooCommerce product: %3$s.', 'competitor-price-stock-monitor' ),
					$competitor_name,
					$competitor_product,
					$woo_product
				);

			case 'competitor_back_in_stock':
				return sprintf(
					/* translators: 1: competitor name, 2: competitor product title, 3: WooCommerce product name. */
					__( '%1$s is back in stock for competitor product "%2$s". Mapped WooCommerce product: %3$s.', 'competitor-price-stock-monitor' ),
					$competitor_name,
					$competitor_product,
					$woo_product
				);

			case 'we_are_more_expensive':
				return sprintf(
					/* translators: 1: WooCommerce product name, 2: competitor product title, 3: competitor name. */
					__( 'WooCommerce product "%1$s" is more expensive than competitor product "%2$s" at %3$s.', 'competitor-price-stock-monitor' ),
					$woo_product,
					$competitor_product,
					$competitor_name
				);

			case 'we_are_much_cheaper':
				return sprintf(
					/* translators: 1: WooCommerce product name, 2: competitor product title, 3: competitor name. */
					__( 'WooCommerce product "%1$s" is cheaper than competitor product "%2$s" at %3$s.', 'competitor-price-stock-monitor' ),
					$woo_product,
					$competitor_product,
					$competitor_name
				);

			case 'crawl_failed':
				return sprintf(
					/* translators: 1: competitor product title, 2: competitor name, 3: WooCommerce product name. */
					__( 'Crawler failed for competitor product "%1$s" at %2$s. Mapped WooCommerce product: %3$s.', 'competitor-price-stock-monitor' ),
					$competitor_product,
					$competitor_name,
					$woo_product
				);

			case 'auto_price_adjusted':
				return sprintf(
					/* translators: 1: WooCommerce product name, 2: competitor product title, 3: competitor name. */
					__( 'Automatic Pro pricing updated WooCommerce product "%1$s" after checking competitor product "%2$s" at %3$s.', 'competitor-price-stock-monitor' ),
					$woo_product,
					$competitor_product,
					$competitor_name
				);

			case 'original_price_restored':
				return sprintf(
					/* translators: %s: WooCommerce product name. */
					__( 'Original customer price was restored for WooCommerce product "%s".', 'competitor-price-stock-monitor' ),
					$woo_product
				);
		}

		return $stored_message;
	}

	/**
	 * Extracts price-change detail from current or legacy alert copy.
	 *
	 * @param string $message Stored alert message.
	 * @return string
	 */
	private function alert_price_change_text( string $message ): string {
		if ( preg_match( '/\bfrom\s+(.+?)\s+to\s+(.+?)(?:\.|$)/i', $message, $matches ) ) {
			return sprintf(
				/* translators: 1: old price, 2: new price. */
				__( 'Price changed from %1$s to %2$s.', 'competitor-price-stock-monitor' ),
				sanitize_text_field( $matches[1] ),
				sanitize_text_field( $matches[2] )
			);
		}

		return '';
	}

	/**
	 * Gets current WooCommerce product price.
	 *
	 * @param int $product_id Product ID.
	 * @return float|null
	 */
	public function product_price( int $product_id ): ?float {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return null;
		}

		$price = $product->get_price( 'edit' );
		if ( '' === $price || null === $price ) {
			$price = $product->get_regular_price( 'edit' );
		}

		return is_numeric( $price ) ? (float) $price : null;
	}

	/**
	 * Gets latest history for a mapping.
	 *
	 * @param int $mapping_id Mapping ID.
	 * @return object|null
	 */
	public function latest_history( int $mapping_id ): ?object {
		return $this->db->get_latest_history_for_mapping( $mapping_id );
	}

	/**
	 * Gets 30-day attributed impact for a mapping.
	 *
	 * @param int $mapping_id Mapping ID.
	 * @return array<string,mixed>
	 */
	public function profit_impact_for_mapping( int $mapping_id ): array {
		return $this->db->get_profit_impact_for_mapping( $mapping_id );
	}

	/**
	 * Builds a product edit link.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public function product_edit_link( int $product_id ): string {
		return get_edit_post_link( $product_id, '' ) ?: '';
	}

	/**
	 * Shows a one-time welcome notice after plugin activation.
	 *
	 * @return void
	 */
	public function render_welcome_notice(): void {
		if ( ! get_transient( 'wc_competitor_monitor_welcome' ) ) {
			return;
		}
		if ( ! WC_Competitor_Monitor_Security::current_user_can_manage() ) {
			return;
		}
		$dismiss_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=wc_competitor_monitor_dismiss_welcome' ),
			'wc_competitor_monitor_dismiss_welcome'
		);
		?>
		<div class="notice notice-success">
			<p><strong><?php esc_html_e( 'Competitor Price & Stock Monitor is active.', 'competitor-price-stock-monitor' ); ?></strong></p>
			<p>
				<strong><?php esc_html_e( 'Step 1:', 'competitor-price-stock-monitor' ); ?></strong>
				<?php esc_html_e( 'Go to Product Mapping and add a competitor URL for your first product.', 'competitor-price-stock-monitor' ); ?>
				&nbsp;
				<strong><?php esc_html_e( 'Step 2:', 'competitor-price-stock-monitor' ); ?></strong>
				<?php esc_html_e( 'Click "Run check now" to see the competitor price immediately.', 'competitor-price-stock-monitor' ); ?>
				&nbsp;
				<strong><?php esc_html_e( 'Step 3:', 'competitor-price-stock-monitor' ); ?></strong>
				<?php esc_html_e( 'Set up email alerts in Settings so you\'re notified of every change.', 'competitor-price-stock-monitor' ); ?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=competitor-price-stock-monitor-products' ) ); ?>"><?php esc_html_e( 'Get started →', 'competitor-price-stock-monitor' ); ?></a>
				&nbsp;
				<a href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'Dismiss', 'competitor-price-stock-monitor' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Deletes the welcome notice transient.
	 *
	 * @return void
	 */
	public function handle_dismiss_welcome(): void {
		WC_Competitor_Monitor_Security::require_capability();
		check_admin_referer( 'wc_competitor_monitor_dismiss_welcome' );
		delete_transient( 'wc_competitor_monitor_welcome' );
		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}

	/**
	 * Renders a single mapping table row as an HTML string.
	 *
	 * @param object $mapping Mapping row object.
	 * @return string
	 */
	private function render_mapping_row_html( object $mapping ): string {
		$competitor_product_title = $this->truncate_competitor_product_title( $this->competitor_product_title( $mapping ) );
		$competitor_store_name    = $this->competitor_store_name( $mapping );
		$edit_url                 = add_query_arg(
			array(
				'page'       => 'competitor-price-stock-monitor-products',
				'mapping_id' => absint( $mapping->id ),
			),
			admin_url( 'admin.php' )
		);

		ob_start();
		?>
		<tr>
			<td>
				<strong><?php echo esc_html( $competitor_product_title ); ?></strong><br>
				<span class="wccm-muted-line"><?php echo esc_html( $competitor_store_name ); ?></span><br>
				<a href="<?php echo esc_url( $mapping->competitor_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Visit', 'competitor-price-stock-monitor' ); ?></a>
			</td>
			<td><?php echo $this->format_price( null !== $mapping->last_price ? (float) $mapping->last_price : null ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
			<td><span class="wccm-badge wccm-badge-<?php echo esc_attr( $mapping->last_stock_status ?: 'unknown' ); ?>"><?php echo esc_html( $mapping->last_stock_status ?: __( 'unknown', 'competitor-price-stock-monitor' ) ); ?></span></td>
			<td><?php echo $mapping->last_checked_at ? esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $mapping->last_checked_at ) ) : '&mdash;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
			<td><a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit mapping', 'competitor-price-stock-monitor' ); ?></a></td>
		</tr>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * AJAX handler: quick-add competitor mapping from product metabox.
	 *
	 * @return void
	 */
	public function handle_ajax_quick_add_mapping(): void {
		check_ajax_referer( 'wccm_quick_add_mapping' );

		if ( ! WC_Competitor_Monitor_Security::current_user_can_manage() ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'competitor-price-stock-monitor' ), 403 );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;

		if ( ! $product_id || ! current_user_can( 'edit_post', $product_id ) ) {
			wp_send_json_error( __( 'Invalid product.', 'competitor-price-stock-monitor' ), 400 );
		}

		$url        = isset( $_POST['competitor_url'] ) ? esc_url_raw( wp_unslash( $_POST['competitor_url'] ) ) : '';
		$validation = WC_Competitor_Monitor_Security::validate_competitor_url( $url );

		if ( is_wp_error( $validation ) ) {
			wp_send_json_error( $validation->get_error_message() );
		}

		if ( $this->product_mapping_url_exists( $product_id, $url ) ) {
			wp_send_json_error( __( 'This competitor URL is already mapped to the product.', 'competitor-price-stock-monitor' ) );
		}

		$host            = (string) wp_parse_url( $url, PHP_URL_HOST );
		$competitor_name = isset( $_POST['competitor_name'] ) ? sanitize_text_field( wp_unslash( $_POST['competitor_name'] ) ) : '';
		$competitor_name = '' !== $competitor_name ? $competitor_name : preg_replace( '/^www\./', '', $host );
		$currency        = isset( $_POST['currency'] ) ? WC_Competitor_Monitor_Security::sanitize_currency( sanitize_text_field( wp_unslash( $_POST['currency'] ) ) ) : '';
		$margin          = isset( $_POST['min_margin_percentage'] ) ? max( 0.0, min( 99.0, (float) sanitize_text_field( wp_unslash( $_POST['min_margin_percentage'] ) ) ) ) : 20.0;
		$active          = isset( $_POST['active'] ) ? absint( wp_unslash( $_POST['active'] ) ) : 1;

		$this->db->capture_original_product_price( $product_id, 'product_metabox_mapping_created' );

		$mapping_id = $this->db->insert_mapping(
			array(
				'product_id'                    => $product_id,
				'competitor_name'               => $competitor_name,
				'competitor_product_title'      => $this->competitor_product_title_from_url( $url, $competitor_name ),
				'competitor_url'                => $url,
				'price_selector'                => '',
				'stock_selector'                => '',
				'currency'                      => $currency,
				'min_margin_percentage'         => $margin,
				'suggested_increase_mode'       => 'global',
				'suggested_increase_percentage' => null,
				'active'                        => $active ? 1 : 0,
			)
		);

		if ( $mapping_id <= 0 ) {
			wp_send_json_error( __( 'Could not save the mapping.', 'competitor-price-stock-monitor' ) );
		}

		$this->sync->sync_mapping( $mapping_id, 'product_metabox_created' );

		$mapping = $this->db->get_mapping( $mapping_id );
		wp_send_json_success(
			array(
				'row_html' => $this->render_mapping_row_html( $mapping ),
				'message'  => __( 'Competitor mapping added.', 'competitor-price-stock-monitor' ),
			)
		);
	}
}
