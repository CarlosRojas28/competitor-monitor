<?php
/**
 * Pricing recommendations.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds margin-aware pricing suggestions without changing prices.
 */
class WC_Competitor_Monitor_Recommendations {

	/**
	 * Database layer.
	 *
	 * @var WC_Competitor_Monitor_DB
	 */
	private WC_Competitor_Monitor_DB $db;

	/**
	 * Constructor.
	 *
	 * @param WC_Competitor_Monitor_DB $db Database layer.
	 */
	public function __construct( WC_Competitor_Monitor_DB $db ) {
		$this->db = $db;
	}

	/**
	 * Gets recent recommendations from active mappings.
	 *
	 * @param int $limit Limit.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_recent_recommendations( int $limit = 10 ): array {
		$recommendations = array();
		$seen_products   = array();
		$mappings        = $this->db->get_mappings(
			array(
				'active' => 1,
				'limit'  => max( 1, $limit * 10 ),
			)
		);

		foreach ( $mappings as $mapping ) {
			$product_id = absint( $mapping->product_id );
			if ( $product_id <= 0 || isset( $seen_products[ $product_id ] ) ) {
				continue;
			}

			$seen_products[ $product_id ] = true;
			$recommendation              = $this->recommend_for_product( $product_id );
			if ( ! empty( $recommendation ) ) {
				$recommendations[] = $recommendation;
			}

			if ( count( $recommendations ) >= $limit ) {
				break;
			}
		}

		return $recommendations;
	}

	/**
	 * Builds an aggregate recommendation for one WooCommerce product using all active competitors.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string,mixed>
	 */
	public function recommend_for_product( int $product_id ): array {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return array();
		}

		$product_id = absint( $product_id );
		$product    = wc_get_product( $product_id );
		if ( ! $product ) {
			return array();
		}

		$our_price = $this->get_product_price( $product );
		if ( $our_price <= 0 ) {
			return array(
				'type'              => 'insufficient_data',
				'severity'          => 'info',
				'product_id'        => $product_id,
				'mapping_id'        => 0,
				'competitor_name'   => '',
				'current_price'     => $our_price,
				'competitor_price'  => 0.0,
				'recommended_price' => null,
				'message'           => __( 'Price recommendation is conservative because the WooCommerce product price is missing.', 'competitor-price-stock-monitor' ),
			);
		}

		$mappings = $this->db->get_mappings(
			array(
				'product_id' => $product_id,
				'active'     => 1,
				'limit'      => 250,
			)
		);

		$eligible          = array();
		$min_margin        = 0.0;
		$competitors_seen  = 0;

		foreach ( $mappings as $mapping ) {
			$competitors_seen++;
			$min_margin = max( $min_margin, max( 0, min( 99, (float) ( $mapping->min_margin_percentage ?? 0 ) ) ) );
			$price      = isset( $mapping->last_price ) ? (float) $mapping->last_price : 0.0;

			if ( $price <= 0 || ! $this->is_in_stock_status( (string) ( $mapping->last_stock_status ?? '' ) ) ) {
				continue;
			}

			$eligible[] = $mapping;
		}

		if ( empty( $eligible ) ) {
			return array(
				'type'                  => 'no_in_stock_competitor',
				'severity'              => 'info',
				'product_id'            => $product_id,
				'mapping_id'            => ! empty( $mappings[0]->id ) ? absint( $mappings[0]->id ) : 0,
				'competitor_name'       => ! empty( $mappings[0]->competitor_name ) ? (string) $mappings[0]->competitor_name : '',
				'current_price'         => $our_price,
				'competitor_price'      => 0.0,
				'recommended_price'     => null,
				'competitors_evaluated' => $competitors_seen,
				'message'               => __( 'No active in-stock competitor with a valid latest price is available for this product.', 'competitor-price-stock-monitor' ),
			);
		}

		usort(
			$eligible,
			static function ( object $left, object $right ): int {
				return (float) $left->last_price <=> (float) $right->last_price;
			}
		);

		$target_mapping     = $eligible[0];
		$competitor_price   = (float) $target_mapping->last_price;
		$decimals           = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
		$competitive_target = round( max( 0, $competitor_price - 0.01 ), $decimals );
		$cost               = $this->get_product_cost( $product_id );
		$min_allowed_price  = null;
		$settings           = $this->db->get_settings();
		$increase_limit     = $this->get_suggested_increase_limit( $target_mapping, $settings );
		$baseline_price     = $this->db->get_original_product_price( $product_id ) ?: $our_price;

		if ( $cost > 0 && $min_margin < 99 ) {
			$min_allowed_price = $cost / ( 1 - ( $min_margin / 100 ) );
			$min_allowed_price = round( $min_allowed_price, $decimals );
		}

		$base = array(
			'product_id'                => $product_id,
			'mapping_id'                => absint( $target_mapping->id ),
			'target_mapping_id'         => absint( $target_mapping->id ),
			'competitor_name'           => (string) $target_mapping->competitor_name,
			'current_price'             => $our_price,
			'competitor_price'          => $competitor_price,
			'competitive_target'        => $competitive_target,
			'baseline_price'            => $baseline_price,
			'min_allowed_price'         => $min_allowed_price,
			'increase_limit_percentage' => $increase_limit,
			'competitors_evaluated'     => $competitors_seen,
			'eligible_competitors'      => count( $eligible ),
			'recommended_price'         => null,
		);

		if ( $competitive_target <= 0 ) {
			return array_merge(
				$base,
				array(
					'type'     => 'insufficient_data',
					'severity' => 'info',
					'message'  => __( 'The lowest in-stock competitor price is not usable for a price recommendation.', 'competitor-price-stock-monitor' ),
				)
			);
		}

		if ( $our_price > $competitive_target ) {
			if ( null === $min_allowed_price ) {
				return array_merge(
					$base,
					array(
						'type'     => 'missing_cost',
						'severity' => 'warning',
						'message'  => __( 'The lowest in-stock competitor is cheaper, but no product cost is available. Add cost data before lowering prices automatically.', 'competitor-price-stock-monitor' ),
					)
				);
			}

			if ( $min_allowed_price > $competitive_target ) {
				return array_merge(
					$base,
					array(
						'type'     => 'margin_floor_blocks_drop',
						'severity' => 'error',
						'message'  => __( 'The lowest in-stock competitor is cheaper, but matching them would break the configured minimum margin.', 'competitor-price-stock-monitor' ),
					)
				);
			}

			return array_merge(
				$base,
				array(
					'type'              => 'lower_to_lowest_in_stock',
					'severity'          => 'warning',
					'recommended_price' => $competitive_target,
					'message'           => __( 'Lower price just below the cheapest in-stock competitor while respecting the configured margin floor.', 'competitor-price-stock-monitor' ),
				)
			);
		}

		if ( $our_price < $competitive_target ) {
			$target = $competitive_target;
			if ( null !== $increase_limit ) {
				$target = min( $target, $baseline_price * ( 1 + ( $increase_limit / 100 ) ) );
			}
			$target = round( max( $target, $our_price ), $decimals );

			if ( $target > $our_price ) {
				return array_merge(
					$base,
					array(
						'type'              => 'raise_toward_lowest_in_stock',
						'severity'          => 'success',
						'recommended_price' => $target,
						'message'           => __( 'Raise price toward the cheapest in-stock competitor without exceeding the configured increase limit.', 'competitor-price-stock-monitor' ),
					)
				);
			}
		}

		return array_merge(
			$base,
			array(
				'type'     => 'hold',
				'severity' => 'info',
				'message'  => __( 'Current pricing is already aligned with the cheapest in-stock competitor.', 'competitor-price-stock-monitor' ),
			)
		);
	}

	/**
	 * Builds a recommendation for one mapping.
	 *
	 * @param object $mapping Mapping row.
	 * @return array<string,mixed>
	 */
	public function recommend_for_mapping( object $mapping ): array {
		$aggregate = $this->recommend_for_product( absint( $mapping->product_id ) );
		if ( ! empty( $aggregate ) ) {
			return $aggregate;
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			return array();
		}

		$product = wc_get_product( absint( $mapping->product_id ) );
		if ( ! $product ) {
			return array();
		}

		$our_price        = $this->get_product_price( $product );
		$competitor_price = isset( $mapping->last_price ) ? (float) $mapping->last_price : 0.0;
		$stock_status     = (string) ( $mapping->last_stock_status ?? 'unknown' );

		if ( $our_price <= 0 || $competitor_price <= 0 ) {
			return array(
				'type'              => 'insufficient_data',
				'severity'          => 'info',
				'product_id'        => absint( $mapping->product_id ),
				'mapping_id'        => absint( $mapping->id ),
				'competitor_name'   => (string) $mapping->competitor_name,
				'current_price'     => $our_price,
				'competitor_price'  => $competitor_price,
				'recommended_price' => null,
				'message'           => __( 'Price recommendation is conservative because the latest competitor or product price is missing.', 'competitor-price-stock-monitor' ),
			);
		}

		$cost               = $this->get_product_cost( absint( $mapping->product_id ) );
		$min_margin_percent = max( 0, min( 99, (float) $mapping->min_margin_percentage ) );
		$min_allowed_price  = null;
		$settings           = $this->db->get_settings();
		$increase_limit     = $this->get_suggested_increase_limit( $mapping, $settings );

		if ( $cost > 0 && $min_margin_percent < 99 ) {
			$min_allowed_price = $cost / ( 1 - ( $min_margin_percent / 100 ) );
		}

		if ( 'out_of_stock' === $stock_status ) {
			$recommended = round( $our_price * 1.03, wc_get_price_decimals() );
			return array(
				'type'              => 'competitor_out_of_stock',
				'severity'          => 'success',
				'product_id'        => absint( $mapping->product_id ),
				'mapping_id'        => absint( $mapping->id ),
				'competitor_name'   => (string) $mapping->competitor_name,
				'current_price'     => $our_price,
				'competitor_price'  => $competitor_price,
				'recommended_price' => $recommended,
				'message'           => __( 'Competitor is out of stock. Consider holding price or testing a small increase while availability is in your favor.', 'competitor-price-stock-monitor' ),
			);
		}

		if ( $competitor_price < $our_price ) {
			if ( null === $min_allowed_price ) {
				return array(
					'type'              => 'missing_cost',
					'severity'          => 'warning',
					'product_id'        => absint( $mapping->product_id ),
					'mapping_id'        => absint( $mapping->id ),
					'competitor_name'   => (string) $mapping->competitor_name,
					'current_price'     => $our_price,
					'competitor_price'  => $competitor_price,
					'recommended_price' => null,
					'message'           => __( 'Competitor is cheaper, but no product cost is available. Keep pricing conservative until cost data is added.', 'competitor-price-stock-monitor' ),
				);
			}

			$target = max( $min_allowed_price, $competitor_price - 0.01 );
			$target = round( $target, wc_get_price_decimals() );

			if ( $target < $our_price && $target >= $min_allowed_price ) {
				return array(
					'type'              => 'lower_without_breaking_margin',
					'severity'          => 'warning',
					'product_id'        => absint( $mapping->product_id ),
					'mapping_id'        => absint( $mapping->id ),
					'competitor_name'   => (string) $mapping->competitor_name,
					'current_price'     => $our_price,
					'competitor_price'  => $competitor_price,
					'recommended_price' => $target,
					'message'           => __( 'Competitor is cheaper and the margin floor allows a controlled price reduction.', 'competitor-price-stock-monitor' ),
				);
			}

			return array(
				'type'              => 'margin_floor_blocks_drop',
				'severity'          => 'error',
				'product_id'        => absint( $mapping->product_id ),
				'mapping_id'        => absint( $mapping->id ),
				'competitor_name'   => (string) $mapping->competitor_name,
				'current_price'     => $our_price,
				'competitor_price'  => $competitor_price,
				'recommended_price' => null,
				'message'           => __( 'Competitor is cheaper, but matching them would break the configured minimum margin.', 'competitor-price-stock-monitor' ),
			);
		}

		$gap_percent = ( ( $competitor_price - $our_price ) / $competitor_price ) * 100;
		if ( $gap_percent >= 10 ) {
			$target = $competitor_price - 0.01;
			if ( null !== $increase_limit ) {
				$target = min( $target, $our_price * ( 1 + ( $increase_limit / 100 ) ) );
			}
			$target = round( max( $target, $our_price ), wc_get_price_decimals() );

			return array(
				'type'              => 'raise_when_much_cheaper',
				'severity'          => 'success',
				'product_id'        => absint( $mapping->product_id ),
				'mapping_id'        => absint( $mapping->id ),
				'competitor_name'   => (string) $mapping->competitor_name,
				'current_price'     => $our_price,
				'competitor_price'  => $competitor_price,
				'recommended_price' => $target,
				'increase_limit_percentage' => $increase_limit,
				'message'           => __( 'You are materially cheaper than the competitor. Consider a measured increase while staying competitive.', 'competitor-price-stock-monitor' ),
			);
		}

		return array(
			'type'              => 'hold',
			'severity'          => 'info',
			'product_id'        => absint( $mapping->product_id ),
			'mapping_id'        => absint( $mapping->id ),
			'competitor_name'   => (string) $mapping->competitor_name,
			'current_price'     => $our_price,
			'competitor_price'  => $competitor_price,
			'recommended_price' => null,
			'message'           => __( 'Current pricing is close to the competitor. No automatic change is recommended.', 'competitor-price-stock-monitor' ),
		);
	}

	/**
	 * Checks whether a stored competitor stock status is usable for automatic pricing.
	 *
	 * @param string $stock_status Stock status.
	 * @return bool
	 */
	private function is_in_stock_status( string $stock_status ): bool {
		return in_array( sanitize_key( $stock_status ), array( 'in_stock', 'instock', 'available' ), true );
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
	 * Reads product cost from supported COGS plugins or this plugin's fallback field.
	 *
	 * @param int $product_id Product ID.
	 * @return float
	 */
	private function get_product_cost( int $product_id ): float {
		$cost = $this->db->get_product_cost( $product_id );
		return null !== $cost ? (float) $cost : 0.0;
	}

	/**
	 * Gets the configured suggested increase cap for a mapping.
	 *
	 * A null return value means no cap should be applied.
	 *
	 * @param object              $mapping Mapping row.
	 * @param array<string,mixed> $settings Plugin settings.
	 * @return float|null
	 */
	private function get_suggested_increase_limit( object $mapping, array $settings ): ?float {
		$mapping_mode = sanitize_key( (string) ( $mapping->suggested_increase_mode ?? 'global' ) );

		if ( 'none' === $mapping_mode ) {
			return null;
		}

		if ( 'percent' === $mapping_mode ) {
			$value = $mapping->suggested_increase_percentage ?? $settings['suggested_increase_limit_percentage'] ?? 5.0;
			return $this->normalize_increase_limit( $value );
		}

		$global_mode = sanitize_key( (string) ( $settings['suggested_increase_limit_mode'] ?? 'percent' ) );
		if ( 'none' === $global_mode ) {
			return null;
		}

		return $this->normalize_increase_limit( $settings['suggested_increase_limit_percentage'] ?? 5.0 );
	}

	/**
	 * Normalizes a suggested increase percentage cap.
	 *
	 * @param mixed $value Value.
	 * @return float
	 */
	private function normalize_increase_limit( mixed $value ): float {
		return max( 0, min( 999.99, (float) $value ) );
	}
}
