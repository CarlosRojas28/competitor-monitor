<?php
/**
 * Database layer.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- This class is the dedicated custom table gateway. Table names are generated from $wpdb->prefix and dynamic values are prepared or passed through $wpdb CRUD helpers.

/**
 * Encapsulates custom tables, options, and persistence.
 */
class WC_Competitor_Monitor_DB {

	public const DB_VERSION                               = '1.7.0';
	public const OPTION_KEY                               = 'wc_competitor_monitor_settings';
	public const DB_OPTION                                = 'wc_competitor_monitor_db_version';
	public const CRON_OFFSET                              = 'wc_competitor_monitor_cron_offset';
	public const PRODUCT_AUTO_PRICE_MODE_META             = '_cpsm_auto_price_adjustment_mode';
	public const PRODUCT_ORIGINAL_PRICE_RESTORE_MODE_META = '_cpsm_original_price_restore_mode';
	public const PRODUCT_ORIGINAL_PRICE_META              = '_cpsm_original_customer_price';
	public const PRODUCT_ORIGINAL_PRICE_CAPTURED_AT_META  = '_cpsm_original_customer_price_captured_at';
	public const PRODUCT_ORIGINAL_PRICE_SOURCE_META       = '_cpsm_original_customer_price_source';
	public const PRODUCT_COST_META                        = '_cpsm_product_cost';
	public const PRODUCT_COST_SOURCE_META                 = '_cpsm_product_cost_source';

	/**
	 * Returns all custom table names.
	 *
	 * @return array<string,string>
	 */
	public function tables(): array {
		global $wpdb;

		return array(
			'mappings'          => $wpdb->prefix . 'wc_competitor_monitor_mappings',
			'history'           => $wpdb->prefix . 'wc_competitor_monitor_history',
			'alerts'            => $wpdb->prefix . 'wc_competitor_monitor_alerts',
			'logs'              => $wpdb->prefix . 'wc_competitor_monitor_logs',
			'price_adjustments' => $wpdb->prefix . 'wc_competitor_monitor_price_adjustments',
		);
	}

	/**
	 * Creates or upgrades the custom tables.
	 *
	 * @return void
	 */
	public function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$tables          = $this->tables();

		$sql_mappings = "CREATE TABLE {$tables['mappings']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			competitor_name VARCHAR(190) NOT NULL DEFAULT '',
			competitor_product_title VARCHAR(255) NOT NULL DEFAULT '',
			competitor_url TEXT NOT NULL,
			price_selector VARCHAR(255) NULL,
			stock_selector VARCHAR(255) NULL,
			browser_user_agent VARCHAR(255) NULL,
			browser_cookie_header TEXT NULL,
			currency VARCHAR(10) NOT NULL DEFAULT '',
			min_margin_percentage DECIMAL(5,2) NOT NULL DEFAULT 20.00,
			suggested_increase_mode VARCHAR(20) NOT NULL DEFAULT 'global',
			suggested_increase_percentage DECIMAL(6,2) NULL,
			active TINYINT(1) NOT NULL DEFAULT 1,
			last_checked_at DATETIME NULL,
			last_price DECIMAL(12,4) NULL,
			last_stock_status VARCHAR(50) NULL,
			sync_uuid VARCHAR(64) NOT NULL DEFAULT '',
			sync_hash CHAR(64) NOT NULL DEFAULT '',
			last_synced_at DATETIME NULL,
			sync_status VARCHAR(30) NOT NULL DEFAULT 'pending',
			sync_error TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY active (active),
			KEY sync_uuid (sync_uuid),
			KEY sync_status (sync_status),
			KEY last_checked_at (last_checked_at)
		) {$charset_collate};";

		$sql_history = "CREATE TABLE {$tables['history']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			mapping_id BIGINT UNSIGNED NOT NULL,
			product_id BIGINT UNSIGNED NOT NULL,
			competitor_price DECIMAL(12,4) NULL,
			competitor_stock_status VARCHAR(50) NULL,
			our_price DECIMAL(12,4) NULL,
			difference_amount DECIMAL(12,4) NULL,
			difference_percentage DECIMAL(8,2) NULL,
			checked_at DATETIME NOT NULL,
			raw_status VARCHAR(50) NOT NULL DEFAULT '',
			error_message TEXT NULL,
			PRIMARY KEY  (id),
			KEY mapping_id (mapping_id),
			KEY product_id (product_id),
			KEY checked_at (checked_at)
		) {$charset_collate};";

		$sql_alerts = "CREATE TABLE {$tables['alerts']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			mapping_id BIGINT UNSIGNED NOT NULL,
			product_id BIGINT UNSIGNED NOT NULL,
			alert_type VARCHAR(100) NOT NULL DEFAULT '',
			message TEXT NOT NULL,
			severity VARCHAR(30) NOT NULL DEFAULT 'info',
			is_read TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY mapping_id (mapping_id),
			KEY product_id (product_id),
			KEY alert_type (alert_type),
			KEY is_read (is_read),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql_logs = "CREATE TABLE {$tables['logs']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			level VARCHAR(30) NOT NULL DEFAULT 'info',
			message TEXT NOT NULL,
			context LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY level (level),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql_price_adjustments = "CREATE TABLE {$tables['price_adjustments']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			mapping_id BIGINT UNSIGNED NOT NULL,
			old_price DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
			baseline_price DECIMAL(12,4) NULL,
			new_price DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
			recommended_price DECIMAL(12,4) NULL,
			cost_at_change DECIMAL(12,4) NULL,
			currency VARCHAR(10) NOT NULL DEFAULT '',
			changed_at DATETIME NOT NULL,
			attribution_ends_at DATETIME NOT NULL,
			adjustment_type VARCHAR(30) NOT NULL DEFAULT 'auto_adjustment',
			status VARCHAR(30) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY mapping_id (mapping_id),
			KEY adjustment_type (adjustment_type),
			KEY changed_at (changed_at),
			KEY attribution_ends_at (attribution_ends_at),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql_mappings );
		dbDelta( $sql_history );
		dbDelta( $sql_alerts );
		dbDelta( $sql_logs );
		dbDelta( $sql_price_adjustments );

		update_option( self::DB_OPTION, self::DB_VERSION );
		add_option( self::OPTION_KEY, $this->default_settings(), '', 'no' );
	}

	/**
	 * Drops plugin data when uninstall is explicitly configured to do so.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		global $wpdb;

		$tables = $this->tables();
		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		delete_option( self::OPTION_KEY );
		delete_option( self::DB_OPTION );
		delete_option( self::CRON_OFFSET );

		delete_post_meta_by_key( self::PRODUCT_AUTO_PRICE_MODE_META );
		delete_post_meta_by_key( self::PRODUCT_ORIGINAL_PRICE_RESTORE_MODE_META );
		delete_post_meta_by_key( self::PRODUCT_ORIGINAL_PRICE_META );
		delete_post_meta_by_key( self::PRODUCT_ORIGINAL_PRICE_CAPTURED_AT_META );
		delete_post_meta_by_key( self::PRODUCT_ORIGINAL_PRICE_SOURCE_META );
		delete_post_meta_by_key( self::PRODUCT_COST_META );
		delete_post_meta_by_key( self::PRODUCT_COST_SOURCE_META );
	}

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public function default_settings(): array {
		return array(
			'alert_email'                         => get_option( 'admin_email' ),
			'email_alerts'                        => 0,
			'price_change_threshold'              => 5.0,
			'suggested_increase_limit_mode'       => 'percent',
			'suggested_increase_limit_percentage' => 5.0,
			'auto_price_adjustment_mode'          => 'disabled',
			'auto_price_kill_switch'              => 0,
			'original_price_restore_mode'         => 'disabled',
			'check_frequency'                     => 'daily',
			'user_agent'                          => 'Competitor Price Stock Monitor/' . WC_COMPETITOR_MONITOR_VERSION . ' (' . home_url( '/' ) . ')',
			'timeout'                             => 10,
			'max_response_size'                   => 1048576,
			'batch_size'                          => 10,
			'delete_data_on_uninstall'            => 0,
			'pro_enabled'                         => 0,
			'pro_saas_url'                        => 'https://competitor-monitor-pro-production.up.railway.app',
			'pro_license_key'                     => '',
			'pro_license_key_encrypted'           => '',
			'pro_license_key_preview'             => '',
			'pro_api_key'                         => '',
			'bridge_auth_version'                 => '',
			'pro_site_id'                         => '',
			'pro_key_id'                          => '',
			'pro_plugin_to_saas_secret_encrypted' => '',
			'pro_plugin_to_saas_secret_preview'   => '',
			'pro_saas_to_plugin_secret_encrypted' => '',
			'pro_saas_to_plugin_secret_preview'   => '',
			'pro_license_status'                  => '',
			'pro_plan'                            => '',
			'pro_license_message'                 => '',
			'last_mapping_sync_at'                => '',
			'last_mapping_sync_status'            => '',
			'last_mapping_sync_message'           => '',
		);
	}

	/**
	 * Gets sanitized settings merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public function get_settings(): array {
		$settings = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings = wp_parse_args( $settings, $this->default_settings() );
		if ( ! empty( $settings['pro_license_key'] ) && class_exists( 'WC_Competitor_Monitor_Bridge_Auth' ) ) {
			$license_key = sanitize_text_field( (string) $settings['pro_license_key'] );
			if ( '' !== $license_key ) {
				if ( empty( $settings['pro_license_key_encrypted'] ) ) {
					$settings['pro_license_key_encrypted'] = WC_Competitor_Monitor_Bridge_Auth::encrypt_secret( $license_key );
				}
				if ( empty( $settings['pro_license_key_preview'] ) ) {
					$settings['pro_license_key_preview'] = strlen( $license_key ) > 14 ? substr( $license_key, 0, 10 ) . '...' . substr( $license_key, -4 ) : '';
				}
				$settings['pro_license_key'] = '';
				update_option( self::OPTION_KEY, $settings );
			}
		}

		return $settings;
	}

	/**
	 * Updates plugin settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	public function update_settings( array $settings ): void {
		update_option( self::OPTION_KEY, wp_parse_args( $settings, $this->get_settings() ) );
	}

	/**
	 * Captures the customer's original product price once before automated pricing can change it.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $source Capture source.
	 * @return float|null
	 */
	public function capture_original_product_price( int $product_id, string $source = 'mapping_created' ): ?float {
		$product_id = absint( $product_id );
		if ( $product_id <= 0 ) {
			return null;
		}

		$existing = $this->get_original_product_price( $product_id );
		if ( null !== $existing ) {
			return $existing;
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$legacy_adjustment_price = $this->earliest_adjustment_old_price( $product_id );
		if ( null !== $legacy_adjustment_price ) {
			return $this->store_original_product_price( $product_id, $legacy_adjustment_price, 'legacy_auto_adjustment' );
		}

		$legacy_meta_price = $this->legacy_last_auto_old_price( $product_id );
		if ( null !== $legacy_meta_price ) {
			return $this->store_original_product_price( $product_id, $legacy_meta_price, 'legacy_last_auto_price_old' );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return null;
		}

		$price = $this->product_price_for_baseline( $product );
		if ( $price <= 0 ) {
			return null;
		}

		return $this->store_original_product_price( $product_id, $price, $source );
	}

	/**
	 * Returns the customer's original product price if it was captured.
	 *
	 * @param int $product_id Product ID.
	 * @return float|null
	 */
	public function get_original_product_price( int $product_id ): ?float {
		$value = get_post_meta( absint( $product_id ), self::PRODUCT_ORIGINAL_PRICE_META, true );
		if ( is_numeric( $value ) && (float) $value > 0 ) {
			return (float) $value;
		}

		return null;
	}

	/**
	 * Gets the best editable product price to use as the customer baseline.
	 *
	 * @param WC_Product $product Product.
	 * @return float
	 */
	private function product_price_for_baseline( $product ): float {
		$price = method_exists( $product, 'get_price' ) ? $product->get_price( 'edit' ) : '';
		if ( '' === $price || null === $price ) {
			$price = method_exists( $product, 'get_regular_price' ) ? $product->get_regular_price( 'edit' ) : '';
		}

		return is_numeric( $price ) ? (float) $price : 0.0;
	}

	/**
	 * Stores original customer price metadata.
	 *
	 * @param int    $product_id Product ID.
	 * @param float  $price Price.
	 * @param string $source Source.
	 * @return float
	 */
	private function store_original_product_price( int $product_id, float $price, string $source ): float {
		update_post_meta( $product_id, self::PRODUCT_ORIGINAL_PRICE_META, wc_format_decimal( $price, wc_get_price_decimals() ) );
		update_post_meta( $product_id, self::PRODUCT_ORIGINAL_PRICE_CAPTURED_AT_META, current_time( 'mysql' ) );
		update_post_meta( $product_id, self::PRODUCT_ORIGINAL_PRICE_SOURCE_META, sanitize_key( $source ) );

		return $price;
	}

	/**
	 * Saves the plugin-owned product cost fallback.
	 *
	 * External cost plugin metadata is still preferred when available.
	 *
	 * @param int        $product_id Product ID.
	 * @param float|null $cost Product cost, or null to clear.
	 * @return void
	 */
	public function save_product_cost( int $product_id, ?float $cost ): void {
		$product_id = absint( $product_id );
		if ( $product_id <= 0 ) {
			return;
		}

		if ( null === $cost || $cost <= 0 ) {
			delete_post_meta( $product_id, self::PRODUCT_COST_META );
			delete_post_meta( $product_id, self::PRODUCT_COST_SOURCE_META );
			return;
		}

		update_post_meta( $product_id, self::PRODUCT_COST_META, wc_format_decimal( $cost, wc_get_price_decimals() ) );
		update_post_meta( $product_id, self::PRODUCT_COST_SOURCE_META, 'manual' );
	}

	/**
	 * Returns the product cost value used by margin and profit calculations.
	 *
	 * @param int $product_id Product ID.
	 * @return float|null
	 */
	public function get_product_cost( int $product_id ): ?float {
		$cost_data = $this->get_product_cost_data( $product_id );
		return null !== $cost_data['cost'] ? (float) $cost_data['cost'] : null;
	}

	/**
	 * Returns product cost with source metadata.
	 *
	 * Known third-party COGS fields are preferred over this plugin's fallback.
	 *
	 * @param int $product_id Product ID.
	 * @return array{cost:float|null,source:string,source_label:string,source_key:string}
	 */
	public function get_product_cost_data( int $product_id ): array {
		$product_id = absint( $product_id );
		foreach ( $this->product_cost_meta_keys() as $meta_key => $source_label ) {
			$value = get_post_meta( $product_id, $meta_key, true );
			if ( '' !== $value && is_numeric( $value ) && (float) $value > 0 ) {
				return array(
					'cost'         => (float) $value,
					'source'       => self::PRODUCT_COST_META === $meta_key ? 'manual' : 'external',
					'source_label' => $source_label,
					'source_key'   => $meta_key,
				);
			}
		}

		return array(
			'cost'         => null,
			'source'       => 'none',
			'source_label' => __( 'No product cost found', 'competitor-price-stock-monitor' ),
			'source_key'   => '',
		);
	}

	/**
	 * Known product cost meta keys, ordered by source priority.
	 *
	 * @return array<string,string>
	 */
	public function product_cost_meta_keys(): array {
		$keys = array(
			'_wc_cog_cost'                => __( 'WooCommerce cost of goods', 'competitor-price-stock-monitor' ),
			'_wc_cogs_cost'               => __( 'WooCommerce cost of goods', 'competitor-price-stock-monitor' ),
			'_wc_cog_cost_value'          => __( 'WooCommerce cost of goods', 'competitor-price-stock-monitor' ),
			'_wc_cogs_total_value'        => __( 'WooCommerce cost of goods', 'competitor-price-stock-monitor' ),
			'_alg_wc_cog_cost'            => __( 'Cost of Goods for WooCommerce', 'competitor-price-stock-monitor' ),
			'_wcj_purchase_price'         => __( 'Booster purchase price', 'competitor-price-stock-monitor' ),
			'_wcj_product_purchase_price' => __( 'Booster purchase price', 'competitor-price-stock-monitor' ),
			'_yith_cog_cost'              => __( 'YITH cost of goods', 'competitor-price-stock-monitor' ),
			'yith_cog_cost'               => __( 'YITH cost of goods', 'competitor-price-stock-monitor' ),
			'_atum_purchase_price'        => __( 'ATUM purchase price', 'competitor-price-stock-monitor' ),
			'_product_cost'               => __( 'Product cost metadata', 'competitor-price-stock-monitor' ),
			'_cost'                       => __( 'Product cost metadata', 'competitor-price-stock-monitor' ),
			self::PRODUCT_COST_META       => __( 'Competitor Monitor product cost', 'competitor-price-stock-monitor' ),
		);

		/**
		 * Filters product cost meta keys used by Competitor Monitor.
		 *
		 * Return an associative array where key is the meta key and value is a source label.
		 *
		 * @param array<string,string> $keys Product cost meta keys.
		 */
		$filtered = apply_filters( 'wc_competitor_monitor_product_cost_meta_keys', $keys );
		if ( ! is_array( $filtered ) ) {
			return $keys;
		}

		$clean = array();
		foreach ( $filtered as $meta_key => $source_label ) {
			$meta_key = sanitize_key( (string) $meta_key );
			if ( '' === $meta_key ) {
				continue;
			}

			$clean[ $meta_key ] = sanitize_text_field( (string) $source_label );
		}

		if ( ! isset( $clean[ self::PRODUCT_COST_META ] ) ) {
			$clean[ self::PRODUCT_COST_META ] = __( 'Competitor Monitor product cost', 'competitor-price-stock-monitor' );
		}

		return $clean;
	}

	/**
	 * Returns the earliest pre-adjustment price for legacy installations that did not capture the baseline yet.
	 *
	 * @param int $product_id Product ID.
	 * @return float|null
	 */
	private function earliest_adjustment_old_price( int $product_id ): ?float {
		global $wpdb;

		$table = $this->tables()['price_adjustments'];
		$sql   = $wpdb->prepare(
			"SELECT old_price FROM {$table} WHERE product_id = %d AND old_price > 0 ORDER BY changed_at ASC, id ASC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			absint( $product_id )
		);
		$value = $wpdb->get_var( $sql );

		return is_numeric( $value ) && (float) $value > 0 ? (float) $value : null;
	}

	/**
	 * Uses the legacy last-auto-price meta when a site upgraded after automatic pricing already ran.
	 *
	 * @param int $product_id Product ID.
	 * @return float|null
	 */
	private function legacy_last_auto_old_price( int $product_id ): ?float {
		$value = get_post_meta( absint( $product_id ), '_cpsm_last_auto_price_old', true );
		if ( is_numeric( $value ) && (float) $value > 0 ) {
			return (float) $value;
		}

		return null;
	}

	/**
	 * Inserts a mapping.
	 *
	 * @param array<string,mixed> $data Mapping data.
	 * @return int
	 */
	public function insert_mapping( array $data ): int {
		global $wpdb;

		$table = $this->tables()['mappings'];
		$now   = current_time( 'mysql' );

		$insert = array(
			'product_id'                    => absint( $data['product_id'] ?? 0 ),
			'competitor_name'               => sanitize_text_field( (string) ( $data['competitor_name'] ?? '' ) ),
			'competitor_product_title'      => sanitize_text_field( (string) ( $data['competitor_product_title'] ?? '' ) ),
			'competitor_url'                => esc_url_raw( (string) ( $data['competitor_url'] ?? '' ) ),
			'price_selector'                => WC_Competitor_Monitor_Security::sanitize_selector( (string) ( $data['price_selector'] ?? '' ) ),
			'stock_selector'                => WC_Competitor_Monitor_Security::sanitize_selector( (string) ( $data['stock_selector'] ?? '' ) ),
			'browser_user_agent'            => sanitize_text_field( (string) ( $data['browser_user_agent'] ?? '' ) ),
			'browser_cookie_header'         => WC_Competitor_Monitor_Security::sanitize_cookie_header( (string) ( $data['browser_cookie_header'] ?? '' ) ),
			'currency'                      => WC_Competitor_Monitor_Security::sanitize_currency( (string) ( $data['currency'] ?? '' ) ),
			'min_margin_percentage'         => $this->normalize_percentage( $data['min_margin_percentage'] ?? 20 ),
			'suggested_increase_mode'       => $this->sanitize_mapping_increase_mode( (string) ( $data['suggested_increase_mode'] ?? 'global' ) ),
			'suggested_increase_percentage' => $this->normalize_optional_increase_percentage( $data['suggested_increase_percentage'] ?? null ),
			'active'                        => empty( $data['active'] ) ? 0 : 1,
			'sync_uuid'                     => ! empty( $data['sync_uuid'] ) ? sanitize_text_field( (string) $data['sync_uuid'] ) : wp_generate_uuid4(),
			'sync_status'                   => 'pending',
			'created_at'                    => $now,
			'updated_at'                    => $now,
		);

		$wpdb->insert(
			$table,
			$insert,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%f', '%d', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Updates a mapping.
	 *
	 * @param int                 $id Mapping ID.
	 * @param array<string,mixed> $data Mapping data.
	 * @return bool
	 */
	public function update_mapping( int $id, array $data ): bool {
		global $wpdb;

		$table = $this->tables()['mappings'];

		$update  = array(
			'product_id'                    => absint( $data['product_id'] ?? 0 ),
			'competitor_name'               => sanitize_text_field( (string) ( $data['competitor_name'] ?? '' ) ),
			'competitor_url'                => esc_url_raw( (string) ( $data['competitor_url'] ?? '' ) ),
			'price_selector'                => WC_Competitor_Monitor_Security::sanitize_selector( (string) ( $data['price_selector'] ?? '' ) ),
			'stock_selector'                => WC_Competitor_Monitor_Security::sanitize_selector( (string) ( $data['stock_selector'] ?? '' ) ),
			'browser_user_agent'            => sanitize_text_field( (string) ( $data['browser_user_agent'] ?? '' ) ),
			'browser_cookie_header'         => WC_Competitor_Monitor_Security::sanitize_cookie_header( (string) ( $data['browser_cookie_header'] ?? '' ) ),
			'currency'                      => WC_Competitor_Monitor_Security::sanitize_currency( (string) ( $data['currency'] ?? '' ) ),
			'min_margin_percentage'         => $this->normalize_percentage( $data['min_margin_percentage'] ?? 20 ),
			'suggested_increase_mode'       => $this->sanitize_mapping_increase_mode( (string) ( $data['suggested_increase_mode'] ?? 'global' ) ),
			'suggested_increase_percentage' => $this->normalize_optional_increase_percentage( $data['suggested_increase_percentage'] ?? null ),
			'active'                        => empty( $data['active'] ) ? 0 : 1,
			'sync_status'                   => 'pending',
			'sync_error'                    => '',
			'updated_at'                    => current_time( 'mysql' ),
		);
		$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%f', '%d', '%s', '%s', '%s' );

		if ( array_key_exists( 'competitor_product_title', $data ) ) {
			$update['competitor_product_title'] = sanitize_text_field( (string) $data['competitor_product_title'] );
			$formats[]                          = '%s';
		}

		$result = $wpdb->update(
			$table,
			$update,
			array( 'id' => absint( $id ) ),
			$formats,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Gets one mapping.
	 *
	 * @param int $id Mapping ID.
	 * @return object|null
	 */
	public function get_mapping( int $id ): ?object {
		global $wpdb;

		$table = $this->tables()['mappings'];
		$sql   = $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row   = $wpdb->get_row( $sql );

		return $row ?: null;
	}

	/**
	 * Gets one mapping by competitor URL using exact and normalized comparisons.
	 *
	 * @param string $url Competitor URL.
	 * @return object|null
	 */
	public function get_mapping_by_competitor_url( string $url ): ?object {
		global $wpdb;

		$url = esc_url_raw( $url );
		if ( '' === $url ) {
			return null;
		}

		$table    = $this->tables()['mappings'];
		$variants = array_values(
			array_unique(
				array_filter(
					array(
						$url,
						untrailingslashit( $url ),
						trailingslashit( $url ),
					)
				)
			)
		);

		$prepared_variants = array_pad( array_slice( $variants, 0, 3 ), 3, '__wc_competitor_monitor_no_match__' );
		$row               = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE competitor_url IN ( %s, %s, %s ) ORDER BY updated_at DESC, id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$prepared_variants[0],
				$prepared_variants[1],
				$prepared_variants[2]
			)
		);

		if ( $row ) {
			return $row;
		}

		$needle_variants = $this->normalized_url_variants( $url );
		if ( empty( $needle_variants ) ) {
			return null;
		}

		$candidates = $this->get_mappings( array( 'limit' => 5000 ) );
		foreach ( $candidates as $candidate ) {
			if ( array_intersect( $needle_variants, $this->normalized_url_variants( (string) $candidate->competitor_url ) ) ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Lists mappings.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return array<int,object>
	 */
	public function get_mappings( array $args = array() ): array {
		global $wpdb;

		$table = $this->tables()['mappings'];
		$where = 'WHERE 1=1';

		if ( array_key_exists( 'active', $args ) && null !== $args['active'] ) {
			$where .= $wpdb->prepare( ' AND active = %d', absint( $args['active'] ) );
		}

		if ( ! empty( $args['product_id'] ) ) {
			$where .= $wpdb->prepare( ' AND product_id = %d', absint( $args['product_id'] ) );
		}

		$limit  = isset( $args['limit'] ) ? max( 1, absint( $args['limit'] ) ) : 100;
		$offset = isset( $args['offset'] ) ? max( 0, absint( $args['offset'] ) ) : 0;
		$sql    = $wpdb->prepare(
			"SELECT * FROM {$table} {$where} ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$limit,
			$offset
		);

		return $wpdb->get_results( $sql );
	}

	/**
	 * Returns the total number of mappings matching the given filters.
	 *
	 * @param array<string,mixed> $args Same filters as get_mappings() — active, product_id.
	 * @return int
	 */
	public function count_mappings( array $args = array() ): int {
		global $wpdb;

		$table = $this->tables()['mappings'];
		$where = 'WHERE 1=1';

		if ( array_key_exists( 'active', $args ) && null !== $args['active'] ) {
			$where .= $wpdb->prepare( ' AND active = %d', absint( $args['active'] ) );
		}

		if ( ! empty( $args['product_id'] ) ) {
			$where .= $wpdb->prepare( ' AND product_id = %d', absint( $args['product_id'] ) );
		}

		$sql = "SELECT COUNT(*) FROM {$table} {$where}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Gets active mappings due for checking.
	 *
	 * @param int $limit Max rows.
	 * @return array<int,object>
	 */
	public function get_active_mappings_for_check( int $limit ): array {
		global $wpdb;

		$table = $this->tables()['mappings'];
		$limit = max( 1, $limit );
		$sql   = $wpdb->prepare(
			"SELECT * FROM {$table}
			WHERE active = 1
			ORDER BY (last_checked_at IS NULL) DESC, last_checked_at ASC, id ASC
			LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$limit
		);

		return $wpdb->get_results( $sql );
	}

	/**
	 * Deletes a mapping and its linked records.
	 *
	 * @param int $id Mapping ID.
	 * @return void
	 */
	public function delete_mapping( int $id ): void {
		global $wpdb;

		$tables = $this->tables();
		$id     = absint( $id );

		$wpdb->delete( $tables['mappings'], array( 'id' => $id ), array( '%d' ) );
		$wpdb->delete( $tables['history'], array( 'mapping_id' => $id ), array( '%d' ) );
		$wpdb->delete( $tables['alerts'], array( 'mapping_id' => $id ), array( '%d' ) );
		$wpdb->delete( $tables['price_adjustments'], array( 'mapping_id' => $id ), array( '%d' ) );
	}

	/**
	 * Ensures an existing mapping has a stable synchronization UUID.
	 *
	 * @param int $id Mapping ID.
	 * @return string
	 */
	public function ensure_mapping_sync_uuid( int $id ): string {
		global $wpdb;

		$id      = absint( $id );
		$mapping = $this->get_mapping( $id );
		if ( ! $mapping ) {
			return '';
		}

		$current = sanitize_text_field( (string) ( $mapping->sync_uuid ?? '' ) );
		if ( '' !== $current ) {
			return $current;
		}

		$uuid = wp_generate_uuid4();
		$wpdb->update(
			$this->tables()['mappings'],
			array(
				'sync_uuid'   => $uuid,
				'sync_status' => 'pending',
				'updated_at'  => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return $uuid;
	}

	/**
	 * Marks a mapping as synchronized with the SaaS.
	 *
	 * @param int    $id Mapping ID.
	 * @param string $hash Payload hash.
	 * @return void
	 */
	public function mark_mapping_sync_success( int $id, string $hash ): void {
		global $wpdb;

		$wpdb->update(
			$this->tables()['mappings'],
			array(
				'sync_hash'      => sanitize_text_field( $hash ),
				'last_synced_at' => current_time( 'mysql' ),
				'sync_status'    => 'synced',
				'sync_error'     => '',
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Marks a mapping sync attempt as failed.
	 *
	 * @param int    $id Mapping ID.
	 * @param string $error Error message.
	 * @return void
	 */
	public function mark_mapping_sync_error( int $id, string $error ): void {
		global $wpdb;

		$wpdb->update(
			$this->tables()['mappings'],
			array(
				'sync_status' => 'error',
				'sync_error'  => sanitize_text_field( $error ),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Inserts an automatic Pro price adjustment event.
	 *
	 * @param array<string,mixed> $data Adjustment data.
	 * @return int
	 */
	public function insert_price_adjustment( array $data ): int {
		global $wpdb;

		$now = current_time( 'mysql' );
		$wpdb->insert(
			$this->tables()['price_adjustments'],
			array(
				'product_id'          => absint( $data['product_id'] ?? 0 ),
				'mapping_id'          => absint( $data['mapping_id'] ?? 0 ),
				'old_price'           => isset( $data['old_price'] ) ? (float) $data['old_price'] : 0,
				'baseline_price'      => isset( $data['baseline_price'] ) && is_numeric( $data['baseline_price'] ) ? (float) $data['baseline_price'] : null,
				'new_price'           => isset( $data['new_price'] ) ? (float) $data['new_price'] : 0,
				'recommended_price'   => isset( $data['recommended_price'] ) ? (float) $data['recommended_price'] : null,
				'cost_at_change'      => isset( $data['cost_at_change'] ) && is_numeric( $data['cost_at_change'] ) ? (float) $data['cost_at_change'] : null,
				'currency'            => WC_Competitor_Monitor_Security::sanitize_currency( (string) ( $data['currency'] ?? '' ) ),
				'changed_at'          => sanitize_text_field( (string) ( $data['changed_at'] ?? $now ) ),
				'attribution_ends_at' => sanitize_text_field( (string) ( $data['attribution_ends_at'] ?? gmdate( 'Y-m-d H:i:s', time() + ( 30 * DAY_IN_SECONDS ) ) ) ),
				'adjustment_type'     => $this->sanitize_adjustment_type( (string) ( $data['adjustment_type'] ?? 'auto_adjustment' ) ),
				'status'              => sanitize_key( (string) ( $data['status'] ?? 'active' ) ),
				'created_at'          => sanitize_text_field( (string) ( $data['created_at'] ?? $now ) ),
			),
			array( '%d', '%d', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Shortens active attribution windows for a product.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $ended_at End timestamp.
	 * @return int
	 */
	public function close_active_auto_adjustments_for_product( int $product_id, string $ended_at ): int {
		global $wpdb;

		$table = $this->tables()['price_adjustments'];
		$sql   = $wpdb->prepare(
			"UPDATE {$table}
			SET attribution_ends_at = %s
			WHERE product_id = %d
				AND status = %s
				AND adjustment_type = %s
				AND attribution_ends_at > %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			sanitize_text_field( $ended_at ),
			absint( $product_id ),
			'active',
			'auto_adjustment',
			sanitize_text_field( $ended_at )
		);

		$result = $wpdb->query( $sql );
		return false === $result ? 0 : (int) $result;
	}

	/**
	 * Lists automatic price adjustment events.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return array<int,object>
	 */
	public function get_price_adjustments( array $args = array() ): array {
		global $wpdb;

		$table = $this->tables()['price_adjustments'];
		$where = 'WHERE 1=1';

		if ( ! empty( $args['product_id'] ) ) {
			$where .= $wpdb->prepare( ' AND product_id = %d', absint( $args['product_id'] ) );
		}

		if ( ! empty( $args['mapping_id'] ) ) {
			$where .= $wpdb->prepare( ' AND mapping_id = %d', absint( $args['mapping_id'] ) );
		}

		if ( ! empty( $args['status'] ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', sanitize_key( (string) $args['status'] ) );
		}

		if ( ! empty( $args['since_days'] ) ) {
			$where .= $wpdb->prepare( ' AND changed_at >= DATE_SUB(NOW(), INTERVAL %d DAY)', absint( $args['since_days'] ) );
		}

		$limit = isset( $args['limit'] ) ? max( 1, absint( $args['limit'] ) ) : 500;
		$sql   = $wpdb->prepare(
			"SELECT * FROM {$table} {$where} ORDER BY changed_at DESC, id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$limit
		);

		return $wpdb->get_results( $sql );
	}

	/**
	 * Gets the latest automatic price adjustment for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return object|null
	 */
	public function get_latest_price_adjustment_for_product( int $product_id ): ?object {
		global $wpdb;

		$table = $this->tables()['price_adjustments'];
		$sql   = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE product_id = %d ORDER BY changed_at DESC, id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			absint( $product_id )
		);
		$row   = $wpdb->get_row( $sql );

		return $row ?: null;
	}

	/**
	 * Gets the overall attributed gross profit impact.
	 *
	 * @param int $top_limit Top product limit.
	 * @return array<string,mixed>
	 */
	public function get_profit_impact_summary( int $top_limit = 5 ): array {
		return $this->build_profit_impact(
			$this->get_price_adjustments(
				array(
					'status'     => 'active',
					'since_days' => 90,
					'limit'      => 500,
				)
			),
			$top_limit
		);
	}

	/**
	 * Gets attributed gross profit impact for one WooCommerce product.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string,mixed>
	 */
	public function get_profit_impact_for_product( int $product_id ): array {
		$summary = $this->build_profit_impact(
			$this->get_price_adjustments(
				array(
					'product_id' => absint( $product_id ),
					'status'     => 'active',
					'since_days' => 90,
					'limit'      => 200,
				)
			),
			5
		);

		$summary['latest_adjustment'] = $this->public_price_adjustment( $this->get_latest_price_adjustment_for_product( $product_id ) );

		return $summary;
	}

	/**
	 * Gets attributed gross profit impact for one mapping.
	 *
	 * @param int $mapping_id Mapping ID.
	 * @return array<string,mixed>
	 */
	public function get_profit_impact_for_mapping( int $mapping_id ): array {
		return $this->build_profit_impact(
			$this->get_price_adjustments(
				array(
					'mapping_id' => absint( $mapping_id ),
					'status'     => 'active',
					'since_days' => 90,
					'limit'      => 200,
				)
			),
			3
		);
	}

	/**
	 * Sets a mapping active/inactive.
	 *
	 * @param int  $id Mapping ID.
	 * @param bool $active Active state.
	 * @return bool
	 */
	public function set_mapping_active( int $id, bool $active ): bool {
		global $wpdb;

		$result = $wpdb->update(
			$this->tables()['mappings'],
			array(
				'active'      => $active ? 1 : 0,
				'sync_status' => 'pending',
				'sync_error'  => '',
				'updated_at'  => current_time( 'mysql' ),
			),
			array( 'id' => absint( $id ) ),
			array( '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Updates the last check values for a mapping.
	 *
	 * @param int         $id Mapping ID.
	 * @param float|null  $price Price.
	 * @param string|null $stock_status Stock status.
	 * @param string      $competitor_product_title Competitor product title.
	 * @return void
	 */
	public function update_mapping_after_check( int $id, ?float $price, ?string $stock_status, string $competitor_product_title = '' ): void {
		global $wpdb;

		$update  = array(
			'last_checked_at'   => current_time( 'mysql' ),
			'last_price'        => $price,
			'last_stock_status' => $stock_status ? sanitize_text_field( $stock_status ) : null,
			'sync_status'       => 'pending',
			'sync_error'        => '',
			'updated_at'        => current_time( 'mysql' ),
		);
		$formats = array( '%s', '%f', '%s', '%s', '%s', '%s' );

		$competitor_product_title = sanitize_text_field( $competitor_product_title );
		if ( '' !== $competitor_product_title ) {
			$update['competitor_product_title'] = $competitor_product_title;
			$formats[]                          = '%s';
		}

		$wpdb->update(
			$this->tables()['mappings'],
			$update,
			array( 'id' => absint( $id ) ),
			$formats,
			array( '%d' )
		);
	}

	/**
	 * Touches a mapping after a failed check.
	 *
	 * @param int $id Mapping ID.
	 * @return void
	 */
	public function touch_mapping_checked_at( int $id ): void {
		global $wpdb;

		$wpdb->update(
			$this->tables()['mappings'],
			array(
				'last_checked_at' => current_time( 'mysql' ),
				'sync_status'     => 'pending',
				'sync_error'      => '',
				'updated_at'      => current_time( 'mysql' ),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Inserts a history row.
	 *
	 * @param array<string,mixed> $data History data.
	 * @return int
	 */
	public function insert_history( array $data ): int {
		global $wpdb;

		$insert = array(
			'mapping_id'              => absint( $data['mapping_id'] ?? 0 ),
			'product_id'              => absint( $data['product_id'] ?? 0 ),
			'competitor_price'        => isset( $data['competitor_price'] ) ? (float) $data['competitor_price'] : null,
			'competitor_stock_status' => isset( $data['competitor_stock_status'] ) ? sanitize_text_field( (string) $data['competitor_stock_status'] ) : null,
			'our_price'               => isset( $data['our_price'] ) ? (float) $data['our_price'] : null,
			'difference_amount'       => isset( $data['difference_amount'] ) ? (float) $data['difference_amount'] : null,
			'difference_percentage'   => isset( $data['difference_percentage'] ) ? (float) $data['difference_percentage'] : null,
			'checked_at'              => $data['checked_at'] ?? current_time( 'mysql' ),
			'raw_status'              => sanitize_text_field( (string) ( $data['raw_status'] ?? '' ) ),
			'error_message'           => isset( $data['error_message'] ) ? sanitize_textarea_field( (string) $data['error_message'] ) : null,
		);

		$wpdb->insert(
			$this->tables()['history'],
			$insert,
			array( '%d', '%d', '%f', '%s', '%f', '%f', '%f', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Gets history rows.
	 *
	 * @param int $limit Limit.
	 * @param int $mapping_id Optional mapping ID.
	 * @return array<int,object>
	 */
	public function get_history( int $limit = 50, int $mapping_id = 0 ): array {
		global $wpdb;

		$table = $this->tables()['history'];
		$limit = max( 1, absint( $limit ) );

		if ( $mapping_id > 0 ) {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE mapping_id = %d ORDER BY checked_at DESC, id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $mapping_id ),
				$limit
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY checked_at DESC, id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			);
		}

		return $wpdb->get_results( $sql );
	}

	/**
	 * Gets the latest history row for a mapping.
	 *
	 * @param int $mapping_id Mapping ID.
	 * @return object|null
	 */
	public function get_latest_history_for_mapping( int $mapping_id ): ?object {
		global $wpdb;

		$table = $this->tables()['history'];
		$sql   = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE mapping_id = %d ORDER BY checked_at DESC, id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			absint( $mapping_id )
		);
		$row   = $wpdb->get_row( $sql );

		return $row ?: null;
	}

	/**
	 * Inserts an alert.
	 *
	 * @param array<string,mixed> $data Alert data.
	 * @return int
	 */
	public function insert_alert( array $data ): int {
		global $wpdb;

		$wpdb->insert(
			$this->tables()['alerts'],
			array(
				'mapping_id' => absint( $data['mapping_id'] ?? 0 ),
				'product_id' => absint( $data['product_id'] ?? 0 ),
				'alert_type' => sanitize_key( (string) ( $data['alert_type'] ?? '' ) ),
				'message'    => sanitize_textarea_field( (string) ( $data['message'] ?? '' ) ),
				'severity'   => sanitize_key( (string) ( $data['severity'] ?? 'info' ) ),
				'is_read'    => empty( $data['is_read'] ) ? 0 : 1,
				'created_at' => $data['created_at'] ?? current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Checks whether an alert type was recently created.
	 *
	 * @param int    $mapping_id Mapping ID.
	 * @param string $alert_type Alert type.
	 * @param int    $hours Window in hours.
	 * @return bool
	 */
	public function has_recent_alert( int $mapping_id, string $alert_type, int $hours = 24 ): bool {
		global $wpdb;

		$table = $this->tables()['alerts'];
		$since = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, $hours ) * HOUR_IN_SECONDS ) );
		$sql   = $wpdb->prepare(
			"SELECT id FROM {$table} WHERE mapping_id = %d AND alert_type = %s AND created_at >= %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			absint( $mapping_id ),
			sanitize_key( $alert_type ),
			get_date_from_gmt( $since )
		);

		return (bool) $wpdb->get_var( $sql );
	}

	/**
	 * Gets alerts.
	 *
	 * @param int       $limit Limit.
	 * @param bool|null $unread_only Unread filter.
	 * @return array<int,object>
	 */
	public function get_alerts( int $limit = 50, ?bool $unread_only = null ): array {
		global $wpdb;

		$table          = $this->tables()['alerts'];
		$mappings_table = $this->tables()['mappings'];
		$limit          = max( 1, absint( $limit ) );
		$where          = 'WHERE 1=1';

		if ( null !== $unread_only ) {
			$where .= $wpdb->prepare( ' AND a.is_read = %d', $unread_only ? 0 : 1 );
		}

		$sql = $wpdb->prepare(
			"SELECT a.*, m.competitor_name AS mapping_competitor_name, m.competitor_product_title AS mapping_competitor_product_title, m.competitor_url AS mapping_competitor_url FROM {$table} a LEFT JOIN {$mappings_table} m ON a.mapping_id = m.id {$where} ORDER BY a.created_at DESC, a.id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$limit
		);

		return $wpdb->get_results( $sql );
	}

	/**
	 * Marks an alert as read.
	 *
	 * @param int $id Alert ID.
	 * @return void
	 */
	public function mark_alert_read( int $id ): void {
		global $wpdb;

		$wpdb->update(
			$this->tables()['alerts'],
			array( 'is_read' => 1 ),
			array( 'id' => absint( $id ) ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Deletes an alert.
	 *
	 * @param int $id Alert ID.
	 * @return void
	 */
	public function delete_alert( int $id ): void {
		global $wpdb;

		$wpdb->delete( $this->tables()['alerts'], array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/**
	 * Inserts a log entry.
	 *
	 * @param string              $level Level.
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Context.
	 * @return int
	 */
	public function insert_log( string $level, string $message, array $context = array() ): int {
		global $wpdb;

		$wpdb->insert(
			$this->tables()['logs'],
			array(
				'level'      => sanitize_key( $level ),
				'message'    => sanitize_textarea_field( $message ),
				'context'    => empty( $context ) ? null : wp_json_encode( WC_Competitor_Monitor_Security::redact_sensitive_context( $context ) ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Gets logs.
	 *
	 * @param int $limit Limit.
	 * @return array<int,object>
	 */
	public function get_logs( int $limit = 100 ): array {
		global $wpdb;

		$table = $this->tables()['logs'];
		$limit = max( 1, absint( $limit ) );
		$sql   = $wpdb->prepare(
			"SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$limit
		);

		return $wpdb->get_results( $sql );
	}

	/**
	 * Deletes logs.
	 *
	 * @return void
	 */
	public function clear_logs(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$this->tables()['logs']}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Gets dashboard stats from latest history rows.
	 *
	 * @return array<string,int>
	 */
	public function get_dashboard_stats(): array {
		global $wpdb;

		$tables = $this->tables();

		$monitored_products = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT product_id) FROM {$tables['mappings']} WHERE active = 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$active_urls        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['mappings']} WHERE active = 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$unread_alerts      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['alerts']} WHERE is_read = 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$latest_sql = "SELECT h.*
			FROM {$tables['history']} h
			INNER JOIN (
				SELECT mapping_id, MAX(id) AS latest_id
				FROM {$tables['history']}
				GROUP BY mapping_id
			) latest ON latest.latest_id = h.id";

		$more_expensive = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ({$latest_sql}) latest_rows WHERE difference_percentage > 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$cheaper        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ({$latest_sql}) latest_rows WHERE difference_percentage < 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$out_of_stock   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ({$latest_sql}) latest_rows WHERE competitor_stock_status = 'out_of_stock'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'monitored_products' => $monitored_products,
			'active_urls'        => $active_urls,
			'unread_alerts'      => $unread_alerts,
			'more_expensive'     => $more_expensive,
			'cheaper'            => $cheaper,
			'out_of_stock'       => $out_of_stock,
		);
	}

	/**
	 * Calculates attributed gross profit from real WooCommerce orders.
	 *
	 * A sale line is attributed to the most recent automatic price adjustment for
	 * that product when the order was created inside the 30-day attribution window.
	 *
	 * @param array<int,object> $adjustments Adjustment rows.
	 * @param int               $top_limit Top product limit.
	 * @return array<string,mixed>
	 */
	private function build_profit_impact( array $adjustments, int $top_limit ): array {
		$summary = $this->empty_profit_impact_summary();
		if ( empty( $adjustments ) ) {
			return $summary;
		}

		$events            = array();
		$events_by_product = array();
		$product_ids       = array();
		$missing_cost      = 0;
		$currency          = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR';

		foreach ( $adjustments as $adjustment ) {
			$adjustment_type = $this->sanitize_adjustment_type( (string) ( $adjustment->adjustment_type ?? 'auto_adjustment' ) );
			if ( 'original_restore' === $adjustment_type ) {
				continue;
			}

			$changed_ts = strtotime( (string) $adjustment->changed_at );
			$ends_ts    = strtotime( (string) $adjustment->attribution_ends_at );
			if ( ! $changed_ts || ! $ends_ts || $ends_ts < $changed_ts ) {
				continue;
			}

			$product_id = absint( $adjustment->product_id );
			if ( $product_id <= 0 ) {
				continue;
			}

			$event = array(
				'id'                  => absint( $adjustment->id ),
				'product_id'          => $product_id,
				'mapping_id'          => absint( $adjustment->mapping_id ),
				'old_price'           => (float) $adjustment->old_price,
				'baseline_price'      => isset( $adjustment->baseline_price ) && is_numeric( $adjustment->baseline_price ) ? (float) $adjustment->baseline_price : null,
				'new_price'           => (float) $adjustment->new_price,
				'recommended_price'   => null !== $adjustment->recommended_price ? (float) $adjustment->recommended_price : null,
				'cost_at_change'      => null !== $adjustment->cost_at_change ? (float) $adjustment->cost_at_change : null,
				'currency'            => sanitize_text_field( (string) $adjustment->currency ),
				'adjustment_type'     => $adjustment_type,
				'changed_ts'          => $changed_ts,
				'attribution_ends_ts' => $ends_ts,
				'changed_at'          => (string) $adjustment->changed_at,
				'attribution_ends_at' => (string) $adjustment->attribution_ends_at,
			);

			$events[]                           = $event;
			$events_by_product[ $product_id ][] = $event;
			$product_ids[ $product_id ]         = true;
			if ( ! empty( $event['currency'] ) ) {
				$currency = (string) $event['currency'];
			}
			if ( empty( $event['cost_at_change'] ) || (float) $event['cost_at_change'] <= 0 ) {
				++$missing_cost;
			}
		}

		if ( empty( $events ) ) {
			return $summary;
		}

		foreach ( $events_by_product as $product_id => $product_events ) {
			usort(
				$product_events,
				static function ( array $left, array $right ): int {
					return $right['changed_ts'] <=> $left['changed_ts'];
				}
			);
			$events_by_product[ $product_id ] = $product_events;
		}

		$summary['events_count']        = count( $events );
		$summary['adjusted_products']   = count( $product_ids );
		$summary['missing_cost_events'] = $missing_cost;
		$summary['currency']            = $currency;
		$summary['as_of']               = current_time( 'mysql' );

		if ( ! function_exists( 'wc_get_orders' ) ) {
			$summary['calculation_note'] = __( 'WooCommerce is not active, so order attribution cannot be calculated.', 'competitor-price-stock-monitor' );
			return $summary;
		}

		$start_ts = min( array_column( $events, 'changed_ts' ) );
		$end_ts   = min( max( array_column( $events, 'attribution_ends_ts' ) ), strtotime( current_time( 'mysql' ) ) ?: time() );
		if ( $end_ts < $start_ts ) {
			return $summary;
		}

		try {
			$orders      = array();
			$page        = 1;
			$batch_size  = 100;
			$batch_count = 0;
			do {
				$batch = wc_get_orders(
					array(
						'status'       => array( 'processing', 'completed' ),
						'type'         => 'shop_order',
						'limit'        => $batch_size,
						'paged'        => $page,
						'return'       => 'objects',
						'date_created' => wp_date( 'Y-m-d H:i:s', $start_ts ) . '...' . wp_date( 'Y-m-d H:i:s', $end_ts ),
					)
				);

				if ( empty( $batch ) ) {
					break;
				}

				$batch_count = count( $batch );
				$orders      = array_merge( $orders, $batch );
				++$page;
			} while ( $batch_count === $batch_size && $page <= 50 );
		} catch ( Throwable $exception ) {
			$summary['calculation_note'] = sanitize_text_field( $exception->getMessage() );
			return $summary;
		}

		$product_impacts = array();
		$counted_orders  = array();
		$mapping_cache   = array();

		foreach ( $orders as $order ) {
			if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) || ! method_exists( $order, 'get_date_created' ) ) {
				continue;
			}

			$date_created = $order->get_date_created();
			if ( ! $date_created || ! method_exists( $date_created, 'date' ) ) {
				continue;
			}

			$order_ts = strtotime( $date_created->date( 'Y-m-d H:i:s' ) );
			if ( ! $order_ts ) {
				continue;
			}

			foreach ( $order->get_items( 'line_item' ) as $item ) {
				if ( ! is_object( $item ) || ! method_exists( $item, 'get_quantity' ) || ! method_exists( $item, 'get_total' ) ) {
					continue;
				}

				$quantity = (float) $item->get_quantity();
				if ( $quantity <= 0 ) {
					continue;
				}

				$candidate_ids = array();
				if ( method_exists( $item, 'get_variation_id' ) ) {
					$candidate_ids[] = absint( $item->get_variation_id() );
				}
				if ( method_exists( $item, 'get_product_id' ) ) {
					$candidate_ids[] = absint( $item->get_product_id() );
				}
				$candidate_ids = array_values( array_unique( array_filter( $candidate_ids ) ) );

				$event = $this->find_profit_attribution_event( $events_by_product, $candidate_ids, $order_ts );
				if ( null === $event ) {
					continue;
				}

				$line_total       = (float) $item->get_total();
				$unit_net_price   = $line_total / $quantity;
				$comparison_price = null !== $event['baseline_price'] && (float) $event['baseline_price'] > 0
					? (float) $event['baseline_price']
					: (float) $event['old_price'];
				$product_id       = absint( $event['product_id'] );
				$mapping_id       = absint( $event['mapping_id'] );

				if ( ! isset( $product_impacts[ $product_id ] ) ) {
					if ( ! isset( $mapping_cache[ $mapping_id ] ) ) {
						$mapping_cache[ $mapping_id ] = $mapping_id > 0 ? $this->get_mapping( $mapping_id ) : null;
					}

					$product_impacts[ $product_id ] = array(
						'product_id'                  => $product_id,
						'product_name'                => $this->product_name_for_impact( $product_id ),
						'mapping_id'                  => $mapping_id,
						'competitor_name'             => $mapping_cache[ $mapping_id ] ? sanitize_text_field( (string) $mapping_cache[ $mapping_id ]->competitor_name ) : '',
						'baseline_gross_profit'       => 0.0,
						'dynamic_gross_profit'        => 0.0,
						'gross_profit_uplift'         => 0.0,
						'uplift_percentage'           => 0.0,
						'baseline_revenue'            => 0.0,
						'dynamic_revenue'             => 0.0,
						'attributed_gross_profit'     => 0.0,
						'revenue_uplift_without_cost' => 0.0,
						'units_sold_after_adjustment' => 0.0,
						'orders_count'                => 0,
						'costed_units'                => 0.0,
						'uncosted_units'              => 0.0,
						'cost_data_status'            => 'needs_cost_data',
						'order_ids'                   => array(),
					);
				}

				$baseline_revenue = $comparison_price * $quantity;
				$dynamic_revenue  = $unit_net_price * $quantity;
				$revenue_uplift   = $dynamic_revenue - $baseline_revenue;

				$product_impacts[ $product_id ]['baseline_revenue']            += $baseline_revenue;
				$product_impacts[ $product_id ]['dynamic_revenue']             += $dynamic_revenue;
				$summary['baseline_revenue']                                   += $baseline_revenue;
				$summary['dynamic_revenue']                                    += $dynamic_revenue;
				$product_impacts[ $product_id ]['units_sold_after_adjustment'] += $quantity;
				$summary['units_sold_after_adjustment']                        += $quantity;
				$order_id                    = method_exists( $order, 'get_id' ) ? absint( $order->get_id() ) : spl_object_id( $order );
				$counted_orders[ $order_id ] = true;
				$product_impacts[ $product_id ]['order_ids'][ $order_id ] = true;

				if ( ! empty( $event['cost_at_change'] ) && (float) $event['cost_at_change'] > 0 ) {
					$cost                  = (float) $event['cost_at_change'];
					$baseline_gross_profit = ( $comparison_price - $cost ) * $quantity;
					$dynamic_gross_profit  = ( $unit_net_price - $cost ) * $quantity;
					$gross_profit_uplift   = $dynamic_gross_profit - $baseline_gross_profit;

					$product_impacts[ $product_id ]['baseline_gross_profit']   += $baseline_gross_profit;
					$product_impacts[ $product_id ]['dynamic_gross_profit']    += $dynamic_gross_profit;
					$product_impacts[ $product_id ]['gross_profit_uplift']     += $gross_profit_uplift;
					$product_impacts[ $product_id ]['attributed_gross_profit'] += $gross_profit_uplift;
					$product_impacts[ $product_id ]['costed_units']            += $quantity;
					$summary['baseline_gross_profit']                          += $baseline_gross_profit;
					$summary['dynamic_gross_profit']                           += $dynamic_gross_profit;
					$summary['gross_profit_uplift']                            += $gross_profit_uplift;
					$summary['attributed_gross_profit']                        += $gross_profit_uplift;
				} else {
					$product_impacts[ $product_id ]['revenue_uplift_without_cost'] += $revenue_uplift;
					$product_impacts[ $product_id ]['uncosted_units']              += $quantity;
					$summary['revenue_uplift_without_cost']                        += $revenue_uplift;
				}
			}
		}

		foreach ( $product_impacts as $product_id => $impact ) {
			$product_impacts[ $product_id ]['orders_count']      = count( $impact['order_ids'] );
			$baseline_gross_profit                               = (float) $product_impacts[ $product_id ]['baseline_gross_profit'];
			$gross_profit_uplift                                 = (float) $product_impacts[ $product_id ]['gross_profit_uplift'];
			$product_impacts[ $product_id ]['uplift_percentage'] = $baseline_gross_profit > 0 ? ( $gross_profit_uplift / $baseline_gross_profit ) * 100 : 0.0;
			if ( (float) $product_impacts[ $product_id ]['costed_units'] > 0 && (float) $product_impacts[ $product_id ]['uncosted_units'] > 0 ) {
				$product_impacts[ $product_id ]['cost_data_status'] = 'partial';
			} elseif ( (float) $product_impacts[ $product_id ]['costed_units'] > 0 ) {
				$product_impacts[ $product_id ]['cost_data_status'] = 'complete';
			}
			unset( $product_impacts[ $product_id ]['costed_units'], $product_impacts[ $product_id ]['uncosted_units'], $product_impacts[ $product_id ]['order_ids'] );
		}

		usort(
			$product_impacts,
			static function ( array $left, array $right ): int {
				return (float) $right['attributed_gross_profit'] <=> (float) $left['attributed_gross_profit'];
			}
		);

		$summary['orders_count']               = count( $counted_orders );
		$summary['gross_profit_uplift']        = (float) $summary['dynamic_gross_profit'] - (float) $summary['baseline_gross_profit'];
		$summary['attributed_gross_profit']    = (float) $summary['gross_profit_uplift'];
		$summary['uplift_percentage']          = (float) $summary['baseline_gross_profit'] > 0 ? ( (float) $summary['gross_profit_uplift'] / (float) $summary['baseline_gross_profit'] ) * 100 : 0.0;
		$summary['products_missing_cost_data'] = count(
			array_filter(
				$product_impacts,
				static function ( array $impact ): bool {
					return 'complete' !== (string) ( $impact['cost_data_status'] ?? '' );
				}
			)
		);
		$summary['product_impacts']            = array_slice( $product_impacts, 0, 250 );
		$summary['top_products']               = array_slice( $product_impacts, 0, max( 1, absint( $top_limit ) ) );

		return $summary;
	}

	/**
	 * Finds the most recent applicable attribution event for an order line.
	 *
	 * @param array<int,array<int,array<string,mixed>>> $events_by_product Events keyed by product ID.
	 * @param array<int,int>                            $candidate_product_ids Order line product IDs.
	 * @param int                                       $order_ts Order timestamp.
	 * @return array<string,mixed>|null
	 */
	private function find_profit_attribution_event( array $events_by_product, array $candidate_product_ids, int $order_ts ): ?array {
		$best = null;
		foreach ( $candidate_product_ids as $product_id ) {
			if ( empty( $events_by_product[ $product_id ] ) ) {
				continue;
			}

			foreach ( $events_by_product[ $product_id ] as $event ) {
				if ( $order_ts < (int) $event['changed_ts'] || $order_ts > (int) $event['attribution_ends_ts'] ) {
					continue;
				}

				if ( null === $best || (int) $event['changed_ts'] > (int) $best['changed_ts'] ) {
					$best = $event;
				}
				break;
			}
		}

		return $best;
	}

	/**
	 * Returns the empty profit impact payload used by admin UI, REST, and SaaS sync.
	 *
	 * @return array<string,mixed>
	 */
	private function empty_profit_impact_summary(): array {
		return array(
			'baseline_gross_profit'       => 0.0,
			'dynamic_gross_profit'        => 0.0,
			'gross_profit_uplift'         => 0.0,
			'uplift_percentage'           => 0.0,
			'baseline_revenue'            => 0.0,
			'dynamic_revenue'             => 0.0,
			'attributed_gross_profit'     => 0.0,
			'revenue_uplift_without_cost' => 0.0,
			'adjusted_products'           => 0,
			'units_sold_after_adjustment' => 0.0,
			'missing_cost_events'         => 0,
			'products_missing_cost_data'  => 0,
			'events_count'                => 0,
			'orders_count'                => 0,
			'currency'                    => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR',
			'as_of'                       => current_time( 'mysql' ),
			'window_days'                 => 30,
			'product_impacts'             => array(),
			'top_products'                => array(),
			'calculation_note'            => '',
		);
	}

	/**
	 * Converts an adjustment row into a REST-safe array.
	 *
	 * @param object|null $adjustment Adjustment row.
	 * @return array<string,mixed>|null
	 */
	private function public_price_adjustment( ?object $adjustment ): ?array {
		if ( ! $adjustment ) {
			return null;
		}

		return array(
			'id'                  => absint( $adjustment->id ),
			'product_id'          => absint( $adjustment->product_id ),
			'mapping_id'          => absint( $adjustment->mapping_id ),
			'old_price'           => (float) $adjustment->old_price,
			'baseline_price'      => isset( $adjustment->baseline_price ) && is_numeric( $adjustment->baseline_price ) ? (float) $adjustment->baseline_price : null,
			'new_price'           => (float) $adjustment->new_price,
			'recommended_price'   => null !== $adjustment->recommended_price ? (float) $adjustment->recommended_price : null,
			'cost_at_change'      => null !== $adjustment->cost_at_change ? (float) $adjustment->cost_at_change : null,
			'currency'            => sanitize_text_field( (string) $adjustment->currency ),
			'adjustment_type'     => $this->sanitize_adjustment_type( (string) ( $adjustment->adjustment_type ?? 'auto_adjustment' ) ),
			'changed_at'          => (string) $adjustment->changed_at,
			'attribution_ends_at' => (string) $adjustment->attribution_ends_at,
			'status'              => sanitize_key( (string) $adjustment->status ),
		);
	}

	/**
	 * Gets a product name for attribution output.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	private function product_name_for_impact( int $product_id ): string {
		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				return sanitize_text_field( $product->get_name() );
			}
		}

		$title = get_the_title( $product_id );
		return sanitize_text_field(
			$title ?: sprintf(
				/* translators: %d: product ID. */
				__( 'Product #%d', 'competitor-price-stock-monitor' ),
				$product_id
			)
		);
	}

	/**
	 * Keeps percentages in a reasonable range.
	 *
	 * @param mixed $value Value.
	 * @return float
	 */
	private function normalize_percentage( mixed $value ): float {
		$value = (float) $value;
		return max( 0, min( 99.99, $value ) );
	}

	/**
	 * Sanitizes a per-mapping suggested increase mode.
	 *
	 * @param string $mode Mode.
	 * @return string
	 */
	private function sanitize_mapping_increase_mode( string $mode ): string {
		$mode = sanitize_key( $mode );
		return in_array( $mode, array( 'global', 'percent', 'none' ), true ) ? $mode : 'global';
	}

	/**
	 * Normalizes an optional suggested increase percentage.
	 *
	 * @param mixed $value Value.
	 * @return float|null
	 */
	private function normalize_optional_increase_percentage( mixed $value ): ?float {
		if ( null === $value || '' === $value ) {
			return null;
		}

		return max( 0, min( 999.99, (float) $value ) );
	}

	/**
	 * Sanitizes price adjustment event types.
	 *
	 * @param string $type Adjustment type.
	 * @return string
	 */
	private function sanitize_adjustment_type( string $type ): string {
		$type = sanitize_key( $type );
		return in_array( $type, array( 'auto_adjustment', 'original_restore' ), true ) ? $type : 'auto_adjustment';
	}

	/**
	 * Builds stable URL variants for competitor mapping lookups.
	 *
	 * @param string $url URL.
	 * @return array<int,string>
	 */
	private function normalized_url_variants( string $url ): array {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return array();
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		$host   = strtolower( (string) $parts['host'] );
		$path   = isset( $parts['path'] ) ? untrailingslashit( '/' . ltrim( (string) $parts['path'], '/' ) ) : '';
		$base   = $scheme . '://' . $host . $path;
		$query  = array();

		if ( ! empty( $parts['query'] ) ) {
			wp_parse_str( (string) $parts['query'], $query );
			foreach ( array_keys( $query ) as $key ) {
				$normalized_key = strtolower( (string) $key );
				if ( str_starts_with( $normalized_key, 'utm_' ) || in_array( $normalized_key, array( 'fbclid', 'gclid', 'mc_cid', 'mc_eid' ), true ) ) {
					unset( $query[ $key ] );
				}
			}
			ksort( $query );
		}

		$variants = array( $base );
		if ( ! empty( $query ) ) {
			$variants[] = $base . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		}

		return array_values( array_unique( $variants ) );
	}
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
