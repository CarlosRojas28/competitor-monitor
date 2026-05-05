<?php
/**
 * Alert generation and delivery.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates internal and optional email alerts.
 */
class WC_Competitor_Monitor_Alerts {

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
	 * Creates an alert unless a duplicate exists in the recent window.
	 *
	 * @param int    $mapping_id Mapping ID.
	 * @param int    $product_id Product ID.
	 * @param string $alert_type Alert type.
	 * @param string $message Message.
	 * @param string $severity Severity.
	 * @return int
	 */
	public function maybe_create( int $mapping_id, int $product_id, string $alert_type, string $message, string $severity = 'info' ): int {
		if ( $this->db->has_recent_alert( $mapping_id, $alert_type, 24 ) ) {
			return 0;
		}

		return $this->create( $mapping_id, $product_id, $alert_type, $message, $severity );
	}

	/**
	 * Creates an alert and sends the optional email notification.
	 *
	 * Use this for events that must notify every time, such as an automatic price change.
	 *
	 * @param int    $mapping_id Mapping ID.
	 * @param int    $product_id Product ID.
	 * @param string $alert_type Alert type.
	 * @param string $message Message.
	 * @param string $severity Severity.
	 * @return int
	 */
	public function create( int $mapping_id, int $product_id, string $alert_type, string $message, string $severity = 'info' ): int {
		$alert_id = $this->db->insert_alert(
			array(
				'mapping_id' => $mapping_id,
				'product_id' => $product_id,
				'alert_type' => $alert_type,
				'message'    => $message,
				'severity'   => $severity,
			)
		);

		$this->send_email_alert( $message, $alert_type, $severity );

		return $alert_id;
	}

	/**
	 * Creates alerts from a successful check.
	 *
	 * @param object              $mapping Mapping row.
	 * @param array<string,mixed> $previous Previous values.
	 * @param array<string,mixed> $current Current values.
	 * @return void
	 */
	public function evaluate_successful_check( object $mapping, array $previous, array $current ): void {
		$product_name       = $this->get_product_name( absint( $mapping->product_id ) );
		$competitor_name    = $mapping->competitor_name ?: __( 'Competitor', 'competitor-price-stock-monitor' );
		$competitor_product = $this->get_competitor_product_name( $mapping );
		$threshold_percent  = max( 0, (float) $this->db->get_settings()['price_change_threshold'] );
		$mapping_id         = absint( $mapping->id );
		$product_id         = absint( $mapping->product_id );
		$current_price      = isset( $current['competitor_price'] ) ? (float) $current['competitor_price'] : 0.0;
		$previous_price     = isset( $previous['price'] ) ? (float) $previous['price'] : 0.0;
		$current_stock      = (string) ( $current['stock_status'] ?? 'unknown' );
		$previous_stock     = (string) ( $previous['stock_status'] ?? '' );
		$difference_pct     = isset( $current['difference_percentage'] ) ? (float) $current['difference_percentage'] : 0.0;

		if ( $current_price > 0 && $previous_price > 0 ) {
			$change_pct = ( ( $current_price - $previous_price ) / $previous_price ) * 100;
			if ( $change_pct <= -1 * $threshold_percent ) {
				$this->maybe_create(
					$mapping_id,
					$product_id,
					'competitor_price_dropped',
					sprintf(
						/* translators: 1: competitor name, 2: competitor product title, 3: old price, 4: new price, 5: WooCommerce product name. */
						__( '%1$s dropped the competitor price for "%2$s" from %3$s to %4$s. Mapped WooCommerce product: %5$s.', 'competitor-price-stock-monitor' ),
						$competitor_name,
						$competitor_product,
						$this->format_price_text( $previous_price ),
						$this->format_price_text( $current_price ),
						$product_name
					),
					'warning'
				);
			} elseif ( $change_pct >= $threshold_percent ) {
				$this->maybe_create(
					$mapping_id,
					$product_id,
					'competitor_price_increased',
					sprintf(
						/* translators: 1: competitor name, 2: competitor product title, 3: old price, 4: new price, 5: WooCommerce product name. */
						__( '%1$s increased the competitor price for "%2$s" from %3$s to %4$s. Mapped WooCommerce product: %5$s.', 'competitor-price-stock-monitor' ),
						$competitor_name,
						$competitor_product,
						$this->format_price_text( $previous_price ),
						$this->format_price_text( $current_price ),
						$product_name
					),
					'info'
				);
			}
		}

		if ( 'out_of_stock' === $current_stock && 'out_of_stock' !== $previous_stock ) {
			$this->maybe_create(
				$mapping_id,
				$product_id,
				'competitor_out_of_stock',
				sprintf(
					/* translators: 1: competitor name, 2: competitor product title, 3: WooCommerce product name. */
					__( '%1$s is out of stock for competitor product "%2$s". Mapped WooCommerce product: %3$s.', 'competitor-price-stock-monitor' ),
					$competitor_name,
					$competitor_product,
					$product_name
				),
				'success'
			);
		}

		if ( 'in_stock' === $current_stock && 'out_of_stock' === $previous_stock ) {
			$this->maybe_create(
				$mapping_id,
				$product_id,
				'competitor_back_in_stock',
				sprintf(
					/* translators: 1: competitor name, 2: competitor product title, 3: WooCommerce product name. */
					__( '%1$s is back in stock for competitor product "%2$s". Mapped WooCommerce product: %3$s.', 'competitor-price-stock-monitor' ),
					$competitor_name,
					$competitor_product,
					$product_name
				),
				'warning'
			);
		}

		if ( $difference_pct >= $threshold_percent ) {
			$this->maybe_create(
				$mapping_id,
				$product_id,
				'we_are_more_expensive',
				sprintf(
					/* translators: 1: WooCommerce product name, 2: percentage, 3: competitor product title, 4: competitor name. */
					__( 'Your WooCommerce product "%1$s" is %2$s%% higher than competitor product "%3$s" at %4$s.', 'competitor-price-stock-monitor' ),
					$product_name,
					number_format_i18n( $difference_pct, 2 ),
					$competitor_product,
					$competitor_name
				),
				'warning'
			);
		}

		if ( $difference_pct <= -1 * $threshold_percent ) {
			$this->maybe_create(
				$mapping_id,
				$product_id,
				'we_are_much_cheaper',
				sprintf(
					/* translators: 1: WooCommerce product name, 2: percentage, 3: competitor product title, 4: competitor name. */
					__( 'Your WooCommerce product "%1$s" is %2$s%% below competitor product "%3$s" at %4$s.', 'competitor-price-stock-monitor' ),
					$product_name,
					number_format_i18n( abs( $difference_pct ), 2 ),
					$competitor_product,
					$competitor_name
				),
				'success'
			);
		}
	}

	/**
	 * Creates a crawl failed alert.
	 *
	 * @param object $mapping Mapping row.
	 * @param string $error Error message.
	 * @return void
	 */
	public function crawl_failed( object $mapping, string $error ): void {
		$product_name       = $this->get_product_name( absint( $mapping->product_id ) );
		$competitor_name    = $mapping->competitor_name ?: __( 'Competitor', 'competitor-price-stock-monitor' );
		$competitor_product = $this->get_competitor_product_name( $mapping );

		$this->maybe_create(
			absint( $mapping->id ),
			absint( $mapping->product_id ),
			'crawl_failed',
			sprintf(
				/* translators: 1: competitor product title, 2: competitor name, 3: WooCommerce product name, 4: error message. */
				__( 'Crawl failed for competitor product "%1$s" at %2$s. Mapped WooCommerce product: %3$s. Error: %4$s', 'competitor-price-stock-monitor' ),
				$competitor_product,
				$competitor_name,
				$product_name,
				$error
			),
			'error'
		);
	}

	/**
	 * Sends an email alert when enabled.
	 *
	 * @param string $message Alert message.
	 * @param string $alert_type Alert type.
	 * @param string $severity Severity.
	 * @return void
	 */
	private function send_email_alert( string $message, string $alert_type, string $severity ): void {
		$settings = $this->db->get_settings();
		if ( empty( $settings['email_alerts'] ) ) {
			return;
		}

		$email = sanitize_email( (string) $settings['alert_email'] );
		if ( ! is_email( $email ) ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: alert type. */
			__( '[Competitor Monitor] %s', 'competitor-price-stock-monitor' ),
			ucwords( str_replace( '_', ' ', sanitize_key( $alert_type ) ) )
		);

		$body = sprintf(
			/* translators: 1: severity, 2: message. */
			__( "Severity: %1\$s\n\n%2\$s", 'competitor-price-stock-monitor' ),
			$severity,
			$message
		);

		wp_mail( $email, $subject, $body );
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
	 * Gets a safe competitor product name for alert copy.
	 *
	 * @param object $mapping Mapping row.
	 * @return string
	 */
	private function get_competitor_product_name( object $mapping ): string {
		$title = isset( $mapping->competitor_product_title ) ? sanitize_text_field( (string) $mapping->competitor_product_title ) : '';
		if ( '' !== $title ) {
			return $title;
		}

		$url_path = wp_parse_url( (string) ( $mapping->competitor_url ?? '' ), PHP_URL_PATH );
		if ( is_string( $url_path ) && '' !== trim( $url_path, '/' ) ) {
			$parts = array_values( array_filter( explode( '/', trim( $url_path, '/' ) ) ) );
			$slug  = rawurldecode( (string) end( $parts ) );
			$slug  = preg_replace( '/\.[a-z0-9]{2,8}$/i', '', $slug );
			$slug  = preg_replace( '/[-_+]+/', ' ', (string) $slug );
			$slug  = preg_replace( '/\s+/', ' ', (string) $slug );
			$title = trim( (string) $slug );
			if ( '' !== $title ) {
				return sanitize_text_field( ucwords( strtolower( $title ) ) );
			}
		}

		return sanitize_text_field( (string) ( $mapping->competitor_name ?: __( 'Competitor product', 'competitor-price-stock-monitor' ) ) );
	}

	/**
	 * Formats a WooCommerce price as plain text for storage and email.
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
}
