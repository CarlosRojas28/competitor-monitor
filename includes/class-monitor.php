<?php
/**
 * Monitoring orchestration.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs manual and scheduled mapping checks.
 */
class WC_Competitor_Monitor_Monitor {

	/**
	 * Database layer.
	 *
	 * @var WC_Competitor_Monitor_DB
	 */
	private WC_Competitor_Monitor_DB $db;

	/**
	 * Crawler.
	 *
	 * @var WC_Competitor_Monitor_Crawler
	 */
	private WC_Competitor_Monitor_Crawler $crawler;

	/**
	 * Parser.
	 *
	 * @var WC_Competitor_Monitor_Parser
	 */
	private WC_Competitor_Monitor_Parser $parser;

	/**
	 * Alerts.
	 *
	 * @var WC_Competitor_Monitor_Alerts
	 */
	private WC_Competitor_Monitor_Alerts $alerts;

	/**
	 * Recommendations.
	 *
	 * @var WC_Competitor_Monitor_Recommendations
	 */
	private WC_Competitor_Monitor_Recommendations $recommendations;

	/**
	 * Optional Pro SaaS client.
	 *
	 * @var WC_Competitor_Monitor_Pro_Client|null
	 */
	private ?WC_Competitor_Monitor_Pro_Client $pro_client;

	/**
	 * Constructor.
	 *
	 * @param WC_Competitor_Monitor_DB              $db Database layer.
	 * @param WC_Competitor_Monitor_Crawler         $crawler Crawler.
	 * @param WC_Competitor_Monitor_Parser          $parser Parser.
	 * @param WC_Competitor_Monitor_Alerts          $alerts Alerts.
	 * @param WC_Competitor_Monitor_Recommendations $recommendations Recommendations.
	 * @param WC_Competitor_Monitor_Pro_Client|null $pro_client Pro SaaS client.
	 */
	public function __construct(
		WC_Competitor_Monitor_DB $db,
		WC_Competitor_Monitor_Crawler $crawler,
		WC_Competitor_Monitor_Parser $parser,
		WC_Competitor_Monitor_Alerts $alerts,
		WC_Competitor_Monitor_Recommendations $recommendations,
		?WC_Competitor_Monitor_Pro_Client $pro_client = null
	) {
		$this->db              = $db;
		$this->crawler         = $crawler;
		$this->parser          = $parser;
		$this->alerts          = $alerts;
		$this->recommendations = $recommendations;
		$this->pro_client      = $pro_client;
	}

	/**
	 * Runs scheduled checks in a bounded batch.
	 *
	 * @return void
	 */
	public function run_scheduled_check(): void {
		if ( ! function_exists( 'wc_get_product' ) ) {
			$this->db->insert_log( 'warning', __( 'Scheduled check skipped because WooCommerce is not active.', 'competitor-price-stock-monitor' ) );
			return;
		}

		$settings = $this->db->get_settings();
		$batch    = max( 1, min( 100, absint( $settings['batch_size'] ) ) );
		$mappings = $this->db->get_active_mappings_for_check( $batch );

		foreach ( $mappings as $mapping ) {
			$this->check_mapping( $mapping );
		}

		$this->sync_profit_impact();
	}

	/**
	 * Syncs the current WooCommerce-attributed Pro pricing impact to the SaaS.
	 *
	 * @return array<string,mixed>
	 */
	public function sync_profit_impact(): array {
		if ( ! $this->pro_client || ! $this->pro_client->is_connected() ) {
			return array(
				'success' => false,
				'reason'  => 'pro_not_connected',
			);
		}

		$result = $this->pro_client->sync_profit_impact( $this->db->get_profit_impact_summary( 10 ) );
		if ( empty( $result['success'] ) ) {
			$this->db->insert_log(
				'warning',
				__( 'Could not sync Pro pricing impact to the SaaS.', 'competitor-price-stock-monitor' ),
				array(
					'error' => sanitize_text_field( (string) ( $result['error'] ?? '' ) ),
				)
			);
		}

		return $result;
	}

	/**
	 * Returns the current original-price restore status for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string,mixed>
	 */
	public function get_original_price_restore_status( int $product_id ): array {
		$product_id = absint( $product_id );
		$status     = array(
			'pro_active'                => $this->pro_client && $this->pro_client->is_connected(),
			'enabled'                   => false,
			'has_original_price'        => false,
			'current_price'             => null,
			'original_price'            => null,
			'lowest_competitor_price'   => null,
			'lowest_competitor_name'    => '',
			'competitive_restore_limit' => null,
			'can_restore'               => false,
			'reason'                    => 'not_checked',
			'message'                   => '',
		);

		if ( ! function_exists( 'wc_get_product' ) ) {
			$status['reason']  = 'woocommerce_inactive';
			$status['message'] = __( 'WooCommerce is not active.', 'competitor-price-stock-monitor' );
			return $status;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			$status['reason']  = 'missing_product';
			$status['message'] = __( 'WooCommerce product was not found.', 'competitor-price-stock-monitor' );
			return $status;
		}

		$status['enabled'] = $this->is_original_price_restore_enabled( $product_id );
		if ( ! $status['pro_active'] ) {
			$status['reason']  = 'pro_inactive';
			$status['message'] = __( 'Original price restore requires an active Pro license.', 'competitor-price-stock-monitor' );
			return $status;
		}

		if ( ! $status['enabled'] ) {
			$status['reason']  = 'disabled';
			$status['message'] = __( 'Original price restore is disabled for this product.', 'competitor-price-stock-monitor' );
			return $status;
		}

		$current_price                = $this->get_product_price( $product );
		$original_price               = $this->db->get_original_product_price( $product_id );
		$status['current_price']      = $current_price;
		$status['original_price']     = $original_price;
		$status['has_original_price'] = null !== $original_price;

		if ( null === $original_price || $original_price <= 0 ) {
			$status['reason']  = 'missing_original_price';
			$status['message'] = __( 'No original customer price has been captured for this product yet.', 'competitor-price-stock-monitor' );
			return $status;
		}

		if ( $current_price <= 0 ) {
			$status['reason']  = 'invalid_current_price';
			$status['message'] = __( 'The current WooCommerce product price is not valid.', 'competitor-price-stock-monitor' );
			return $status;
		}

		$minimum_change = 1 / ( 10 ** max( 0, wc_get_price_decimals() ) );
		if ( abs( $current_price - $original_price ) < $minimum_change ) {
			$status['reason']  = 'same_price';
			$status['message'] = __( 'The product is already using the original customer price.', 'competitor-price-stock-monitor' );
			return $status;
		}

		$lowest = $this->lowest_in_stock_competitor( $product_id );
		if ( null !== $lowest ) {
			$limit                               = round( max( 0, (float) $lowest['price'] - 0.01 ), wc_get_price_decimals() );
			$status['lowest_competitor_price']   = (float) $lowest['price'];
			$status['lowest_competitor_name']    = (string) $lowest['competitor_name'];
			$status['competitive_restore_limit'] = $limit;

			if ( $original_price > $limit ) {
				$status['reason']  = 'original_price_not_competitive';
				$status['message'] = __( 'Original price is above the cheapest in-stock competitor, so restore was blocked.', 'competitor-price-stock-monitor' );
				return $status;
			}
		}

		$status['can_restore'] = true;
		$status['reason']      = 'can_restore';
		$status['message']     = __( 'Original customer price can be restored for this product.', 'competitor-price-stock-monitor' );

		return $status;
	}

	/**
	 * Restores a product to its captured original customer price when allowed.
	 *
	 * @param int $product_id Product ID.
	 * @param int $user_id User ID.
	 * @return array<string,mixed>
	 */
	public function restore_original_product_price( int $product_id, int $user_id = 0 ): array {
		$product_id = absint( $product_id );
		$status     = $this->get_original_price_restore_status( $product_id );
		if ( empty( $status['can_restore'] ) ) {
			return array(
				'success' => false,
				'reason'  => sanitize_key( (string) ( $status['reason'] ?? 'blocked' ) ),
				'message' => (string) ( $status['message'] ?? __( 'Original price restore was blocked.', 'competitor-price-stock-monitor' ) ),
				'status'  => $status,
			);
		}

		$product        = wc_get_product( $product_id );
		$old_price      = (float) $status['current_price'];
		$original_price = (float) $status['original_price'];
		$applied        = $product ? $this->apply_product_price( $product, $original_price ) : array(
			'success' => false,
			'error'   => __( 'WooCommerce product was not found.', 'competitor-price-stock-monitor' ),
		);

		if ( empty( $applied['success'] ) ) {
			return array(
				'success' => false,
				'reason'  => 'update_failed',
				'message' => (string) ( $applied['error'] ?? __( 'Original price restore failed.', 'competitor-price-stock-monitor' ) ),
				'status'  => $status,
			);
		}

		$changed_at          = current_time( 'mysql' );
		$changed_ts          = strtotime( $changed_at ) ?: time();
		$attribution_ends_at = wp_date( 'Y-m-d H:i:s', $changed_ts );
		$closed_events       = $this->db->close_active_auto_adjustments_for_product( $product_id, $changed_at );
		$mapping_id          = $this->mapping_id_for_restore_audit( $product_id );
		$currency            = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';

		$adjustment_id = $this->db->insert_price_adjustment(
			array(
				'product_id'          => $product_id,
				'mapping_id'          => $mapping_id,
				'old_price'           => $old_price,
				'baseline_price'      => $original_price,
				'new_price'           => $original_price,
				'recommended_price'   => $original_price,
				'cost_at_change'      => $this->get_product_cost( $product_id ),
				'currency'            => $currency,
				'changed_at'          => $changed_at,
				'attribution_ends_at' => $attribution_ends_at,
				'adjustment_type'     => 'original_restore',
				'status'              => 'active',
			)
		);

		update_post_meta( $product_id, '_cpsm_last_original_price_restored_at', $changed_at );
		update_post_meta( $product_id, '_cpsm_last_original_price_restore_old', wc_format_decimal( $old_price, wc_get_price_decimals() ) );
		update_post_meta( $product_id, '_cpsm_last_original_price_restore_new', wc_format_decimal( $original_price, wc_get_price_decimals() ) );

		$message = sprintf(
			/* translators: 1: product name, 2: old price, 3: restored price. */
			__( 'Original customer price was restored for "%1$s" from %2$s to %3$s.', 'competitor-price-stock-monitor' ),
			$this->get_product_name( $product_id ),
			$this->format_price_text( $old_price ),
			$this->format_price_text( $original_price )
		);

		$this->alerts->create( $mapping_id, $product_id, 'original_price_restored', $message, 'success' );
		$this->db->insert_log(
			'info',
			__( 'Original customer price restored for a WooCommerce product.', 'competitor-price-stock-monitor' ),
			array(
				'product_id'              => $product_id,
				'mapping_id'              => $mapping_id,
				'user_id'                 => absint( $user_id ),
				'old_price'               => $old_price,
				'restored_price'          => $original_price,
				'baseline_price'          => $original_price,
				'price_adjustment_id'     => $adjustment_id,
				'closed_adjustments'      => $closed_events,
				'lowest_competitor_price' => $status['lowest_competitor_price'],
				'lowest_competitor_name'  => $status['lowest_competitor_name'],
			)
		);

		$this->sync_profit_impact();

		return array(
			'success'             => true,
			'old_price'           => $old_price,
			'new_price'           => $original_price,
			'price_adjustment_id' => $adjustment_id,
			'closed_adjustments'  => $closed_events,
			'message'             => $message,
		);
	}

	/**
	 * Checks one mapping.
	 *
	 * @param int|object $mapping Mapping ID or row.
	 * @return array<string,mixed>
	 */
	public function check_mapping( int|object $mapping ): array {
		if ( is_int( $mapping ) ) {
			$mapping = $this->db->get_mapping( $mapping );
		}

		if ( ! $mapping ) {
			return array(
				'success' => false,
				'error'   => __( 'Mapping not found.', 'competitor-price-stock-monitor' ),
			);
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			$error = __( 'WooCommerce is not active.', 'competitor-price-stock-monitor' );
			$this->db->insert_log( 'warning', $error, array( 'mapping_id' => absint( $mapping->id ) ) );
			return array(
				'success' => false,
				'error'   => $error,
			);
		}

		$product = wc_get_product( absint( $mapping->product_id ) );
		if ( ! $product ) {
			$error = __( 'Mapped WooCommerce product could not be found.', 'competitor-price-stock-monitor' );
			$this->record_failed_check( $mapping, $error, 'missing_product' );
			return array(
				'success' => false,
				'error'   => $error,
			);
		}

		$previous = array(
			'price'        => null !== $mapping->last_price ? (float) $mapping->last_price : null,
			'stock_status' => (string) ( $mapping->last_stock_status ?? '' ),
		);

		if ( $this->pro_client && $this->pro_client->is_connected() ) {
			$pro_result = $this->check_mapping_via_pro( $mapping, $product, $previous );
			if ( ! empty( $pro_result['success'] ) ) {
				return $pro_result;
			}

			$this->db->insert_log(
				'warning',
				__( 'Pro SaaS check failed. Falling back to local crawler.', 'competitor-price-stock-monitor' ),
				array(
					'mapping_id' => absint( $mapping->id ),
					'error'      => (string) ( $pro_result['error'] ?? '' ),
				)
			);
		}

		$response = $this->crawler->fetch(
			(string) $mapping->competitor_url,
			(string) ( $mapping->browser_cookie_header ?? '' ),
			(string) ( $mapping->browser_user_agent ?? '' )
		);
		if ( empty( $response['success'] ) ) {
			$error = (string) ( $response['error'] ?? __( 'Unknown crawler error.', 'competitor-price-stock-monitor' ) );
			$this->record_failed_check( $mapping, $error, (string) ( $response['status'] ?? 'failed' ) );
			$this->alerts->crawl_failed( $mapping, $error );
			return array(
				'success' => false,
				'error'   => $error,
			);
		}

		$parsed = $this->parser->parse(
			(string) $response['body'],
			(string) $mapping->price_selector,
			(string) $mapping->stock_selector
		);

		$our_price        = $this->get_product_price( $product );
		$competitor_price = isset( $parsed['price'] ) ? $parsed['price'] : null;
		$stock_status     = sanitize_text_field( (string) ( $parsed['stock_status'] ?? 'unknown' ) );
		$diff_amount      = null;
		$diff_percentage  = null;

		if ( null !== $competitor_price && $competitor_price > 0 && $our_price > 0 ) {
			$diff_amount     = $our_price - (float) $competitor_price;
			$diff_percentage = ( $diff_amount / (float) $competitor_price ) * 100;
		}

		$raw_status = null === $competitor_price ? 'success_no_price' : 'success';

		$history_id = $this->db->insert_history(
			array(
				'mapping_id'              => absint( $mapping->id ),
				'product_id'              => absint( $mapping->product_id ),
				'competitor_price'        => $competitor_price,
				'competitor_stock_status' => $stock_status,
				'our_price'               => $our_price > 0 ? $our_price : null,
				'difference_amount'       => $diff_amount,
				'difference_percentage'   => $diff_percentage,
				'checked_at'              => current_time( 'mysql' ),
				'raw_status'              => $raw_status,
				'error_message'           => null === $competitor_price ? __( 'Price could not be extracted.', 'competitor-price-stock-monitor' ) : null,
			)
		);

		$this->db->update_mapping_after_check(
			absint( $mapping->id ),
			null !== $competitor_price ? (float) $competitor_price : null,
			$stock_status,
			'' === sanitize_text_field( (string) ( $mapping->competitor_product_title ?? '' ) ) ? sanitize_text_field( (string) ( $parsed['title'] ?? '' ) ) : '' // phpcs:ignore WordPress.PHP.YodaConditions.NotYoda -- condition IS Yoda; phpcs false positive on ternary
		);
		if ( '' === sanitize_text_field( (string) ( $mapping->competitor_product_title ?? '' ) ) && ! empty( $parsed['title'] ) ) {
			$mapping->competitor_product_title = sanitize_text_field( (string) $parsed['title'] );
		}

		$current = array(
			'competitor_price'      => null !== $competitor_price ? (float) $competitor_price : 0.0,
			'stock_status'          => $stock_status,
			'difference_percentage' => $diff_percentage,
			'history_id'            => $history_id,
		);

		$this->alerts->evaluate_successful_check( $mapping, $previous, $current );
		$checked_mapping = $this->mapping_with_latest_values( $mapping, $competitor_price, $stock_status );
		$auto_adjustment = $this->maybe_apply_auto_price_adjustment( $checked_mapping, $product );

		$this->db->insert_log(
			'info',
			__( 'Mapping checked successfully.', 'competitor-price-stock-monitor' ),
			array(
				'mapping_id'         => absint( $mapping->id ),
				'product_id'         => absint( $mapping->product_id ),
				'competitor_price'   => $competitor_price,
				'stock_status'       => $stock_status,
				'difference_percent' => $diff_percentage,
			)
		);

		do_action( 'wc_competitor_monitor_mapping_changed', absint( $mapping->id ), 'check_completed' );

		return array(
			'success'               => true,
			'price'                 => $competitor_price,
			'stock_status'          => $stock_status,
			'difference'            => $diff_percentage,
			'page_title'            => $parsed['title'] ?? '',
			'raw_status'            => $raw_status,
			'history_id'            => $history_id,
			'auto_price_adjustment' => $auto_adjustment,
		);
	}

	/**
	 * Checks a mapping through the Pro SaaS renderer.
	 *
	 * @param object              $mapping Mapping row.
	 * @param WC_Product          $product Product.
	 * @param array<string,mixed> $previous Previous values.
	 * @return array<string,mixed>
	 */
	private function check_mapping_via_pro( object $mapping, $product, array $previous ): array {
		if ( ! $this->pro_client ) {
			return array(
				'success' => false,
				'error'   => __( 'Pro client is not available.', 'competitor-price-stock-monitor' ),
			);
		}

		$result = $this->pro_client->auto_map(
			(string) $mapping->competitor_url,
			true,
			array(
				'product_id' => absint( $mapping->product_id ),
			)
		);
		if ( empty( $result['success'] ) ) {
			return $result;
		}

		$job        = is_array( $result['job'] ?? null ) ? $result['job'] : array();
		$suggestion = is_array( $job['result']['mapping_suggestion'] ?? null ) ? $job['result']['mapping_suggestion'] : array();

		if ( empty( $suggestion ) ) {
			return array(
				'success' => false,
				'error'   => __( 'The SaaS did not return a mapping suggestion.', 'competitor-price-stock-monitor' ),
			);
		}

		$competitor_price = isset( $suggestion['last_price'] ) && is_numeric( $suggestion['last_price'] ) ? (float) $suggestion['last_price'] : null;
		$stock_status     = isset( $suggestion['last_stock_status'] ) ? sanitize_key( (string) $suggestion['last_stock_status'] ) : 'unknown';
		$current_title    = sanitize_text_field( (string) ( $mapping->competitor_product_title ?? '' ) );
		$suggested_title  = sanitize_text_field( (string) ( $suggestion['product_title'] ?? '' ) );
		$our_price        = $this->get_product_price( $product );
		$diff_amount      = null;
		$diff_percentage  = null;

		if ( null !== $competitor_price && $competitor_price > 0 && $our_price > 0 ) {
			$diff_amount     = $our_price - $competitor_price;
			$diff_percentage = ( $diff_amount / $competitor_price ) * 100;
		}

		$raw_status = null === $competitor_price ? 'pro_success_no_price' : 'pro_success';
		$history_id = $this->db->insert_history(
			array(
				'mapping_id'              => absint( $mapping->id ),
				'product_id'              => absint( $mapping->product_id ),
				'competitor_price'        => $competitor_price,
				'competitor_stock_status' => $stock_status,
				'our_price'               => $our_price > 0 ? $our_price : null,
				'difference_amount'       => $diff_amount,
				'difference_percentage'   => $diff_percentage,
				'checked_at'              => current_time( 'mysql' ),
				'raw_status'              => $raw_status,
				'error_message'           => null === $competitor_price ? __( 'Price could not be extracted by Pro SaaS.', 'competitor-price-stock-monitor' ) : null,
			)
		);

		$this->db->update_mapping(
			absint( $mapping->id ),
			array(
				'product_id'                    => absint( $mapping->product_id ),
				'competitor_name'               => sanitize_text_field( (string) ( $mapping->competitor_name ?: ( $suggestion['competitor_name'] ?? '' ) ) ),
				'competitor_product_title'      => '' !== $current_title ? $current_title : $suggested_title,
				'competitor_url'                => esc_url_raw( (string) ( $suggestion['competitor_url'] ?? $mapping->competitor_url ) ),
				'price_selector'                => WC_Competitor_Monitor_Security::sanitize_selector( (string) ( $suggestion['price_selector'] ?? $mapping->price_selector ) ),
				'stock_selector'                => WC_Competitor_Monitor_Security::sanitize_selector( (string) ( $suggestion['stock_selector'] ?? $mapping->stock_selector ) ),
				'browser_user_agent'            => (string) ( $mapping->browser_user_agent ?? '' ),
				'browser_cookie_header'         => (string) ( $mapping->browser_cookie_header ?? '' ),
				'currency'                      => WC_Competitor_Monitor_Security::sanitize_currency( (string) ( $suggestion['currency'] ?? $mapping->currency ) ),
				'min_margin_percentage'         => (float) $mapping->min_margin_percentage,
				'suggested_increase_mode'       => (string) ( $mapping->suggested_increase_mode ?? 'global' ),
				'suggested_increase_percentage' => isset( $mapping->suggested_increase_percentage ) ? (float) $mapping->suggested_increase_percentage : null,
				'active'                        => (int) $mapping->active,
			)
		);

		$this->db->update_mapping_after_check(
			absint( $mapping->id ),
			null !== $competitor_price ? $competitor_price : null,
			$stock_status,
			'' === $current_title ? $suggested_title : ''
		);
		$mapping->competitor_name          = sanitize_text_field( (string) ( $mapping->competitor_name ?: ( $suggestion['competitor_name'] ?? '' ) ) );
		$mapping->competitor_product_title = '' !== $current_title ? $current_title : $suggested_title;

		$current = array(
			'competitor_price'      => null !== $competitor_price ? $competitor_price : 0.0,
			'stock_status'          => $stock_status,
			'difference_percentage' => $diff_percentage,
			'history_id'            => $history_id,
		);

		$this->alerts->evaluate_successful_check( $mapping, $previous, $current );
		$checked_mapping = $this->mapping_with_latest_values( $mapping, $competitor_price, $stock_status );
		$auto_adjustment = $this->maybe_apply_auto_price_adjustment( $checked_mapping, $product );

		$this->db->insert_log(
			'info',
			__( 'Mapping checked successfully through Pro SaaS.', 'competitor-price-stock-monitor' ),
			array(
				'mapping_id'         => absint( $mapping->id ),
				'product_id'         => absint( $mapping->product_id ),
				'competitor_price'   => $competitor_price,
				'stock_status'       => $stock_status,
				'difference_percent' => $diff_percentage,
				'engine'             => sanitize_text_field( (string) ( $job['result']['render']['engine'] ?? '' ) ),
				'ai_used'            => ! empty( $job['result']['extraction']['ai']['used'] ),
			)
		);

		do_action( 'wc_competitor_monitor_mapping_changed', absint( $mapping->id ), 'pro_check_completed' );

		return array(
			'success'               => true,
			'price'                 => $competitor_price,
			'stock_status'          => $stock_status,
			'difference'            => $diff_percentage,
			'page_title'            => sanitize_text_field( (string) ( $suggestion['product_title'] ?? '' ) ),
			'raw_status'            => $raw_status,
			'history_id'            => $history_id,
			'engine'                => sanitize_text_field( (string) ( $job['result']['render']['engine'] ?? 'pro_saas' ) ),
			'ai_used'               => ! empty( $job['result']['extraction']['ai']['used'] ),
			'auto_price_adjustment' => $auto_adjustment,
		);
	}

	/**
	 * Builds a mapping-like object with the latest extracted values.
	 *
	 * @param object      $mapping Mapping row.
	 * @param float|null  $price Latest competitor price.
	 * @param string|null $stock_status Latest stock status.
	 * @return object
	 */
	private function mapping_with_latest_values( object $mapping, ?float $price, ?string $stock_status ): object {
		$updated                    = clone $mapping;
		$updated->last_price        = $price;
		$updated->last_stock_status = $stock_status ?: 'unknown';

		return $updated;
	}

	/**
	 * Applies the recommended price when Pro automatic pricing is enabled.
	 *
	 * @param object     $mapping Mapping row with latest values.
	 * @param WC_Product $product WooCommerce product.
	 * @return array<string,mixed>
	 */
	private function maybe_apply_auto_price_adjustment( object $mapping, $product ): array {
		if ( ! $this->is_auto_price_adjustment_enabled( $mapping ) ) {
			return array(
				'applied' => false,
				'reason'  => 'disabled',
			);
		}

		$this->db->capture_original_product_price( absint( $mapping->product_id ), 'first_auto_adjustment' );
		$recommendation = $this->recommendations->recommend_for_product( absint( $mapping->product_id ) );
		$allowed_types  = array(
			'lower_to_lowest_in_stock',
			'raise_toward_lowest_in_stock',
		);

		if (
			empty( $recommendation['recommended_price'] )
			|| empty( $recommendation['type'] )
			|| ! in_array( (string) $recommendation['type'], $allowed_types, true )
		) {
			if ( ! empty( $recommendation['type'] ) && 'margin_floor_blocks_drop' === (string) $recommendation['type'] ) {
				$this->alerts->maybe_create(
					absint( $recommendation['mapping_id'] ?? $mapping->id ),
					absint( $mapping->product_id ),
					'auto_price_margin_floor_blocked',
					(string) ( $recommendation['message'] ?? __( 'Automatic Pro pricing could not lower the price without breaking the configured margin floor.', 'competitor-price-stock-monitor' ) ),
					'warning'
				);
			}

			return array(
				'applied' => false,
				'reason'  => ! empty( $recommendation['type'] ) ? sanitize_key( (string) $recommendation['type'] ) : 'no_actionable_recommendation',
			);
		}

		$target_mapping_id = absint( $recommendation['target_mapping_id'] ?? $recommendation['mapping_id'] ?? $mapping->id );
		$target_mapping    = $target_mapping_id > 0 ? $this->db->get_mapping( $target_mapping_id ) : null;
		if ( ! $target_mapping ) {
			$target_mapping = $mapping;
		}

		$old_price = $this->get_product_price( $product );
		$new_price = round( (float) $recommendation['recommended_price'], wc_get_price_decimals() );
		if ( $old_price <= 0 || $new_price <= 0 ) {
			return array(
				'applied' => false,
				'reason'  => 'invalid_price',
			);
		}

		$minimum_change = 1 / ( 10 ** max( 0, wc_get_price_decimals() ) );
		if ( abs( $old_price - $new_price ) < $minimum_change ) {
			return array(
				'applied' => false,
				'reason'  => 'same_price',
			);
		}

		if ( ! $this->can_apply_daily_price_change( absint( $mapping->product_id ) ) ) {
			$this->alerts->maybe_create(
				absint( $target_mapping->id ),
				absint( $mapping->product_id ),
				'auto_price_daily_limit_reached',
				__( 'Automatic Pro pricing was blocked because this product reached the daily automatic price-change limit.', 'competitor-price-stock-monitor' ),
				'warning'
			);
			return array(
				'applied' => false,
				'reason'  => 'daily_limit_reached',
			);
		}

		$baseline_price = $this->db->capture_original_product_price( absint( $mapping->product_id ), 'first_auto_adjustment' );
		$applied        = $this->apply_product_price( $product, $new_price );
		if ( empty( $applied['success'] ) ) {
			$this->alerts->maybe_create(
				absint( $target_mapping->id ),
				absint( $mapping->product_id ),
				'auto_price_adjustment_failed',
				sprintf(
					/* translators: 1: product name, 2: failure reason. */
					__( 'Automatic Pro pricing could not update %1$s: %2$s', 'competitor-price-stock-monitor' ),
					$this->get_product_name( absint( $mapping->product_id ) ),
					(string) ( $applied['error'] ?? __( 'Unknown error.', 'competitor-price-stock-monitor' ) )
				),
				'error'
			);

			return array(
				'applied' => false,
				'reason'  => 'update_failed',
				'error'   => (string) ( $applied['error'] ?? '' ),
			);
		}

		$changed_at          = current_time( 'mysql' );
		$changed_ts          = strtotime( $changed_at ) ?: time();
		$attribution_ends_at = wp_date( 'Y-m-d H:i:s', $changed_ts + ( 30 * DAY_IN_SECONDS ) );
		$cost_at_change      = $this->get_product_cost( absint( $mapping->product_id ) );
		$currency            = '' !== (string) ( $target_mapping->currency ?? '' )
			? WC_Competitor_Monitor_Security::sanitize_currency( (string) $target_mapping->currency )
			: ( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '' );
		$adjustment_id       = $this->db->insert_price_adjustment(
			array(
				'product_id'          => absint( $mapping->product_id ),
				'mapping_id'          => absint( $target_mapping->id ),
				'old_price'           => $old_price,
				'baseline_price'      => $baseline_price,
				'new_price'           => $new_price,
				'recommended_price'   => (float) $recommendation['recommended_price'],
				'cost_at_change'      => $cost_at_change,
				'currency'            => $currency,
				'changed_at'          => $changed_at,
				'attribution_ends_at' => $attribution_ends_at,
				'adjustment_type'     => 'auto_adjustment',
				'status'              => 'active',
			)
		);

		update_post_meta( absint( $mapping->product_id ), '_cpsm_last_auto_price_adjusted_at', $changed_at );
		update_post_meta( absint( $mapping->product_id ), '_cpsm_last_auto_price_old', wc_format_decimal( $old_price, wc_get_price_decimals() ) );
		update_post_meta( absint( $mapping->product_id ), '_cpsm_last_auto_price_new', wc_format_decimal( $new_price, wc_get_price_decimals() ) );
		$this->record_daily_price_change( absint( $mapping->product_id ) );
		$this->fire_repricing_telemetry( absint( $mapping->product_id ), $old_price, $new_price );

		$message = sprintf(
			/* translators: 1: WooCommerce product name, 2: old price, 3: new price, 4: competitor product title, 5: competitor name, 6: recommendation message. */
			__( 'Automatic Pro pricing updated WooCommerce product "%1$s" from %2$s to %3$s after checking competitor product "%4$s" at %5$s. Recommendation: %6$s', 'competitor-price-stock-monitor' ),
			$this->get_product_name( absint( $mapping->product_id ) ),
			$this->format_price_text( $old_price ),
			$this->format_price_text( $new_price ),
			$this->get_competitor_product_name( $target_mapping ),
			(string) ( $target_mapping->competitor_name ?: __( 'Competitor', 'competitor-price-stock-monitor' ) ),
			(string) ( $recommendation['message'] ?? '' )
		);

		$this->alerts->create(
			absint( $target_mapping->id ),
			absint( $mapping->product_id ),
			'auto_price_adjusted',
			$message,
			'success'
		);

		$this->db->insert_log(
			'info',
			__( 'Automatic Pro pricing updated a WooCommerce product price.', 'competitor-price-stock-monitor' ),
			array(
				'mapping_id'            => absint( $target_mapping->id ),
				'product_id'            => absint( $mapping->product_id ),
				'old_price'             => $old_price,
				'baseline_price'        => $baseline_price,
				'new_price'             => $new_price,
				'price_adjustment_id'   => $adjustment_id,
				'cost_at_change'        => $cost_at_change,
				'recommendation_type'   => sanitize_key( (string) $recommendation['type'] ),
				'competitive_target'    => isset( $recommendation['competitive_target'] ) ? (float) $recommendation['competitive_target'] : null,
				'competitors_evaluated' => isset( $recommendation['competitors_evaluated'] ) ? absint( $recommendation['competitors_evaluated'] ) : 0,
			)
		);

		$this->sync_profit_impact();

		return array(
			'applied'             => true,
			'old_price'           => $old_price,
			'new_price'           => $new_price,
			'price_adjustment_id' => $adjustment_id,
			'recommendation_type' => (string) $recommendation['type'],
			'target_mapping_id'   => absint( $target_mapping->id ),
		);
	}

	/**
	 * Determines whether automatic pricing is enabled for a product mapping.
	 *
	 * @param object $mapping Mapping row.
	 * @return bool
	 */
	private function is_auto_price_adjustment_enabled( object $mapping ): bool {
		if ( ! $this->pro_client || ! $this->pro_client->is_connected() ) {
			return false;
		}

		$settings = $this->db->get_settings();
		if ( ! empty( $settings['auto_price_kill_switch'] ) ) {
			return false;
		}

		$product_mode = sanitize_key( (string) get_post_meta( absint( $mapping->product_id ), WC_Competitor_Monitor_DB::PRODUCT_AUTO_PRICE_MODE_META, true ) );
		if ( 'enabled' === $product_mode ) {
			return true;
		}

		if ( 'disabled' === $product_mode ) {
			return false;
		}

		return 'enabled' === sanitize_key( (string) ( $settings['auto_price_adjustment_mode'] ?? 'disabled' ) );
	}

	/**
	 * Determines whether original price restore is enabled for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	private function is_original_price_restore_enabled( int $product_id ): bool {
		$product_mode = sanitize_key( (string) get_post_meta( absint( $product_id ), WC_Competitor_Monitor_DB::PRODUCT_ORIGINAL_PRICE_RESTORE_MODE_META, true ) );
		if ( 'enabled' === $product_mode ) {
			return true;
		}

		if ( 'disabled' === $product_mode ) {
			return false;
		}

		$settings = $this->db->get_settings();
		return 'enabled' === sanitize_key( (string) ( $settings['original_price_restore_mode'] ?? 'disabled' ) );
	}

	/**
	 * Returns the cheapest active in-stock competitor for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string,mixed>|null
	 */
	private function lowest_in_stock_competitor( int $product_id ): ?array {
		$mappings = $this->db->get_mappings(
			array(
				'product_id' => absint( $product_id ),
				'active'     => 1,
				'limit'      => 250,
			)
		);

		$lowest = null;
		foreach ( $mappings as $mapping ) {
			$price = isset( $mapping->last_price ) ? (float) $mapping->last_price : 0.0;
			if ( $price <= 0 || ! $this->is_in_stock_status( (string) ( $mapping->last_stock_status ?? '' ) ) ) {
				continue;
			}

			if ( null === $lowest || $price < (float) $lowest['price'] ) {
				$lowest = array(
					'mapping_id'      => absint( $mapping->id ),
					'price'           => $price,
					'competitor_name' => sanitize_text_field( (string) $mapping->competitor_name ),
				);
			}
		}

		return $lowest;
	}

	/**
	 * Checks if a competitor stock status should count as in stock.
	 *
	 * @param string $stock_status Stock status.
	 * @return bool
	 */
	private function is_in_stock_status( string $stock_status ): bool {
		return in_array( sanitize_key( $stock_status ), array( 'in_stock', 'instock', 'available' ), true );
	}

	/**
	 * Gets a mapping ID to link original restore audit events to the product context.
	 *
	 * @param int $product_id Product ID.
	 * @return int
	 */
	private function mapping_id_for_restore_audit( int $product_id ): int {
		$lowest = $this->lowest_in_stock_competitor( $product_id );
		if ( null !== $lowest && ! empty( $lowest['mapping_id'] ) ) {
			return absint( $lowest['mapping_id'] );
		}

		$mappings = $this->db->get_mappings(
			array(
				'product_id' => absint( $product_id ),
				'active'     => 1,
				'limit'      => 1,
			)
		);

		return ! empty( $mappings[0]->id ) ? absint( $mappings[0]->id ) : 0;
	}

	/**
	 * Persists a new WooCommerce product price.
	 *
	 * @param WC_Product $product Product.
	 * @param float      $new_price New active price.
	 * @return array<string,mixed>
	 */
	private function apply_product_price( $product, float $new_price ): array {
		if ( method_exists( $product, 'is_type' ) && $product->is_type( array( 'variable', 'grouped' ) ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Variable and grouped products must be adjusted at variation or child-product level.', 'competitor-price-stock-monitor' ),
			);
		}

		try {
			$formatted_price = wc_format_decimal( $new_price, wc_get_price_decimals() );
			$current_price   = $product->get_price( 'edit' );
			$sale_price      = method_exists( $product, 'get_sale_price' ) ? $product->get_sale_price( 'edit' ) : '';
			$regular_price   = method_exists( $product, 'get_regular_price' ) ? $product->get_regular_price( 'edit' ) : '';

			if ( '' !== $sale_price && is_numeric( $current_price ) && (float) $current_price === (float) $sale_price ) {
				if ( '' !== $regular_price && is_numeric( $regular_price ) && $new_price >= (float) $regular_price ) {
					$product->set_regular_price( $formatted_price );
					$product->set_sale_price( '' );
				} else {
					$product->set_sale_price( $formatted_price );
				}
			} else {
				$product->set_regular_price( $formatted_price );
			}

			$product->save();
			wc_delete_product_transients( $product->get_id() );
		} catch ( Throwable $exception ) {
			return array(
				'success' => false,
				'error'   => $exception->getMessage(),
			);
		}

		return array( 'success' => true );
	}

	/**
	 * Checks the daily automatic price-change limit for one product.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	private function can_apply_daily_price_change( int $product_id ): bool {
		$key = 'cpsm_auto_price_count_' . absint( $product_id ) . '_' . gmdate( 'Ymd' );
		return absint( get_transient( $key ) ) < 12;
	}

	/**
	 * Records one automatic price change for the daily limit.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	private function record_daily_price_change( int $product_id ): void {
		$key   = 'cpsm_auto_price_count_' . absint( $product_id ) . '_' . gmdate( 'Ymd' );
		$count = absint( get_transient( $key ) );
		set_transient( $key, $count + 1, DAY_IN_SECONDS );
	}

	/**
	 * Records a failed check.
	 *
	 * @param object $mapping Mapping row.
	 * @param string $error Error message.
	 * @param string $status Raw status.
	 * @return void
	 */
	private function record_failed_check( object $mapping, string $error, string $status ): void {
		$our_price = null;

		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( absint( $mapping->product_id ) );
			if ( $product ) {
				$our_price = $this->get_product_price( $product );
			}
		}

		$this->db->insert_history(
			array(
				'mapping_id'    => absint( $mapping->id ),
				'product_id'    => absint( $mapping->product_id ),
				'our_price'     => $our_price,
				'checked_at'    => current_time( 'mysql' ),
				'raw_status'    => sanitize_key( $status ),
				'error_message' => $error,
			)
		);

		$this->db->touch_mapping_checked_at( absint( $mapping->id ) );
		$this->db->insert_log(
			'error',
			$error,
			array(
				'mapping_id' => absint( $mapping->id ),
				'status'     => $status,
			)
		);
	}

	/**
	 * Gets a safe product name.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	private function get_product_name( int $product_id ): string {
		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				return $product->get_name();
			}
		}

		$title = get_the_title( $product_id );
		return $title ?: sprintf(
			/* translators: %d: product ID. */
			__( 'Product #%d', 'competitor-price-stock-monitor' ),
			$product_id
		);
	}

	/**
	 * Gets a safe competitor product name for monitoring alerts.
	 *
	 * @param object $mapping Mapping row.
	 * @return string
	 */
	private function get_competitor_product_name( object $mapping ): string {
		$title = isset( $mapping->competitor_product_title ) ? sanitize_text_field( (string) $mapping->competitor_product_title ) : '';
		if ( '' !== $title ) {
			return $title;
		}

		return sanitize_text_field( (string) ( $mapping->competitor_name ?: __( 'Competitor product', 'competitor-price-stock-monitor' ) ) );
	}

	/**
	 * Formats a price as plain text for alerts and logs.
	 *
	 * @param float $price Price.
	 * @return string
	 */
	private function format_price_text( float $price ): string {
		if ( function_exists( 'wc_price' ) ) {
			return trim( html_entity_decode( wp_strip_all_tags( wc_price( $price ) ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ) );
		}

		return number_format_i18n( $price, 2 );
	}

	/**
	 * Gets product price as float.
	 *
	 * @param WC_Product $product Product.
	 * @return float
	 */
	private function get_product_price( $product ): float {
		$price = $product->get_price( 'edit' );
		if ( '' === $price || null === $price ) {
			$price = $product->get_regular_price( 'edit' );
		}

		return (float) $price;
	}

	/**
	 * Gets product cost from supported COGS plugins or this plugin's fallback field.
	 *
	 * @param int $product_id Product ID.
	 * @return float|null
	 */
	private function get_product_cost( int $product_id ): ?float {
		return $this->db->get_product_cost( $product_id );
	}

	/**
	 * Sends a non-blocking repricing.first_applied telemetry event to the SaaS.
	 * The SaaS deduplicates via UNIQUE (account_id, event), so firing on every adjustment is safe.
	 *
	 * @param int   $product_id Product ID.
	 * @param float $old_price  Old price.
	 * @param float $new_price  New price.
	 * @return void
	 */
	private function fire_repricing_telemetry( int $product_id, float $old_price, float $new_price ): void {
		if ( ! $this->pro_client || ! $this->pro_client->is_connected() ) {
			return;
		}

		$settings         = $this->db->get_settings();
		$saas_url         = (string) ( $settings['pro_saas_url'] ?? '' );
		$site_id          = (string) ( $settings['pro_site_id'] ?? '' );
		$key_id           = (string) ( $settings['pro_key_id'] ?? '' );
		$secret_encrypted = (string) ( $settings['pro_plugin_to_saas_secret_encrypted'] ?? '' );

		if ( '' === $saas_url || '' === $site_id || '' === $key_id || '' === $secret_encrypted ) {
			return;
		}

		$secret = WC_Competitor_Monitor_Bridge_Auth::decrypt_secret( $secret_encrypted );
		if ( '' === $secret ) {
			return;
		}

		$body                    = wp_json_encode(
			array(
				'event'      => 'repricing.first_applied',
				'product_id' => $product_id,
				'old_price'  => $old_price,
				'new_price'  => $new_price,
				'site_url'   => home_url( '/' ),
			)
		);
		$url                     = rtrim( $saas_url, '/' ) . '/v1/plugin/telemetry';
		$headers                 = WC_Competitor_Monitor_Bridge_Auth::sign_headers( 'POST', $url, (string) $body, $site_id, $key_id, $secret );
		$headers['Content-Type'] = 'application/json';

		wp_remote_post(
			$url,
			array(
				'headers'  => $headers,
				'body'     => $body,
				'timeout'  => 5,
				'blocking' => false,
			)
		);
	}
}
