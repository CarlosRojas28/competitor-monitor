<?php
/**
 * Pro REST API bridge for browser extension and SaaS.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes mapping operations to the Pro SaaS using the plugin API key.
 */
class WC_Competitor_Monitor_REST_API {

	/**
	 * REST namespace.
	 */
	private const NAMESPACE = 'competitor-price-stock-monitor/v1';

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

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/mappings/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_mapping_status' ),
				'permission_callback' => array( $this, 'authorize_request' ),
				'args'                => array(
					'url' => array(
						'required'          => true,
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/products/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search_products' ),
				'permission_callback' => array( $this, 'authorize_request' ),
				'args'                => array(
					'search' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/products/catalog',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_products_catalog' ),
				'permission_callback' => array( $this, 'authorize_request' ),
				'args'                => array(
					'page'     => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/products/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_product' ),
				'permission_callback' => array( $this, 'authorize_request' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/mappings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_mappings' ),
					'permission_callback' => array( $this, 'authorize_request' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_mapping' ),
					'permission_callback' => array( $this, 'authorize_request' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/mappings/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_mapping' ),
					'permission_callback' => array( $this, 'authorize_request' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_mapping' ),
					'permission_callback' => array( $this, 'authorize_request' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/profit-impact',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_profit_impact' ),
				'permission_callback' => array( $this, 'authorize_request' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/site-profile',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_site_profile' ),
				'permission_callback' => array( $this, 'authorize_request' ),
			)
		);
	}

	/**
	 * Lists mappings for the connected site.
	 *
	 * @return WP_REST_Response
	 */
	public function list_mappings(): WP_REST_Response {
		$mappings = $this->db->get_mappings( array( 'limit' => 5000 ) );
		$payloads = array();
		foreach ( $mappings as $mapping ) {
			$this->db->ensure_mapping_sync_uuid( absint( $mapping->id ) );
			$payloads[] = $this->public_mapping( $this->db->get_mapping( absint( $mapping->id ) ) ?: $mapping );
		}

		return rest_ensure_response(
			array(
				'site_url'  => home_url( '/' ),
				'plugin_version' => WC_COMPETITOR_MONITOR_VERSION,
				'mappings'  => array_values( array_filter( $payloads ) ),
				'synced_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Authenticates the SaaS API key stored by Pro license activation.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function authorize_request( WP_REST_Request $request ) {
		$settings = $this->db->get_settings();
		$api_key  = $this->request_api_key( $request );
		$rate     = $this->check_rate_limit( $request );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		if ( empty( $settings['pro_enabled'] ) || 'active' !== (string) ( $settings['pro_license_status'] ?? '' ) ) {
			return new WP_Error( 'cpsm_pro_inactive', __( 'Pro license is not active on this site.', 'competitor-price-stock-monitor' ), array( 'status' => 401 ) );
		}

		$signature_result = WC_Competitor_Monitor_Bridge_Auth::verify_rest_request( $request, $settings );
		if ( true === $signature_result ) {
			return true;
		}

		if ( WC_Competitor_Monitor_Bridge_Auth::dev_mode() && ! empty( $settings['pro_api_key'] ) && '' !== $api_key && hash_equals( (string) $settings['pro_api_key'], $api_key ) ) {
			return true;
		}

		return $signature_result;
	}

	/**
	 * Returns whether a competitor URL is already mapped.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_mapping_status( WP_REST_Request $request ) {
		$url        = esc_url_raw( (string) $request->get_param( 'url' ) );
		$validation = WC_Competitor_Monitor_Security::validate_competitor_url( $url );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$mapping = $this->db->get_mapping_by_competitor_url( $url );

		return rest_ensure_response(
			array(
				'site_url' => home_url( '/' ),
				'mapped'   => (bool) $mapping,
				'mapping'  => $mapping ? $this->public_mapping( $mapping ) : null,
			)
		);
	}

	/**
	 * Searches WooCommerce products and variations by name or SKU.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function search_products( WP_REST_Request $request ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return new WP_Error( 'cpsm_woocommerce_inactive', __( 'WooCommerce is not active.', 'competitor-price-stock-monitor' ), array( 'status' => 503 ) );
		}

		$term = sanitize_text_field( (string) $request->get_param( 'search' ) );
		if ( strlen( $term ) < 3 ) {
			return rest_ensure_response( array( 'products' => array() ) );
		}

		$ids = array();
		if ( class_exists( 'WC_Data_Store' ) ) {
			$data_store = WC_Data_Store::load( 'product' );
			if ( $data_store && method_exists( $data_store, 'search_products' ) ) {
				$ids = $data_store->search_products( $term, '', true, false, 20 );
			}
		}

		if ( empty( $ids ) && function_exists( 'wc_get_products' ) ) {
			$ids = wc_get_products(
				array(
					'limit'  => 20,
					'status' => array( 'publish', 'private', 'draft' ),
					's'      => $term,
					'return' => 'ids',
				)
			);
		}

		$products = array();
		foreach ( array_slice( array_map( 'absint', (array) $ids ), 0, 20 ) as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$products[] = $this->product_payload( $product );
		}

		return rest_ensure_response( array( 'products' => $products ) );
	}

	/**
	 * Returns a compact WooCommerce catalog snapshot for SaaS onboarding.
	 *
	 * Descriptions, content, images, and customer/order data are intentionally
	 * excluded. The SaaS only needs pricing, stock, identifiers, taxonomy
	 * signals, and attributes for competitor discovery and mapping.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_products_catalog( WP_REST_Request $request ) {
		if ( ! function_exists( 'wc_get_products' ) || ! function_exists( 'wc_get_product' ) ) {
			return new WP_Error( 'cpsm_woocommerce_inactive', __( 'WooCommerce is not active.', 'competitor-price-stock-monitor' ), array( 'status' => 503 ) );
		}

		$page     = max( 1, absint( $request->get_param( 'page' ) ) );
		$per_page = absint( $request->get_param( 'per_page' ) );
		$per_page = max( 1, min( 250, $per_page ?: 100 ) );

		$result = wc_get_products(
			array(
				'limit'    => $per_page,
				'page'     => $page,
				'paginate' => true,
				'return'   => 'objects',
				'status'   => array( 'publish', 'private', 'draft' ),
				'type'     => array( 'simple', 'variable', 'variation', 'grouped', 'external' ),
				'orderby'  => 'ID',
				'order'    => 'ASC',
			)
		);

		$items       = is_object( $result ) && isset( $result->products ) && is_array( $result->products ) ? $result->products : array();
		$total       = is_object( $result ) && isset( $result->total ) ? absint( $result->total ) : count( $items );
		$total_pages = is_object( $result ) && isset( $result->max_num_pages ) ? absint( $result->max_num_pages ) : 1;
		$products    = array();

		foreach ( $items as $product ) {
			if ( ! $product instanceof WC_Product ) {
				continue;
			}
			$products[] = $this->product_payload( $product );
		}

		return rest_ensure_response(
			array(
				'site_url'       => home_url( '/' ),
				'plugin_version' => WC_COMPETITOR_MONITOR_VERSION,
				'page'           => $page,
				'per_page'       => $per_page,
				'total'          => $total,
				'total_pages'    => $total_pages,
				'products'       => $products,
				'synced_at'      => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Returns the current WooCommerce store profile for SaaS-side refresh.
	 *
	 * @return WP_REST_Response
	 */
	public function get_site_profile(): WP_REST_Response {
		$country_state = sanitize_text_field( (string) get_option( 'woocommerce_default_country', '' ) );
		$country       = $country_state;
		$region        = '';
		if ( str_contains( $country_state, ':' ) ) {
			$parts   = explode( ':', $country_state, 2 );
			$country = sanitize_text_field( (string) ( $parts[0] ?? '' ) );
			$region  = sanitize_text_field( (string) ( $parts[1] ?? '' ) );
		}

		$postal_code = sanitize_text_field( (string) get_option( 'woocommerce_store_postcode', '' ) );
		$profile     = array(
			'site_name'          => sanitize_text_field( (string) get_bloginfo( 'name' ) ),
			'country'            => strtoupper( substr( $country, 0, 2 ) ),
			'region'             => strtoupper( substr( $region, 0, 80 ) ),
			'city'               => sanitize_text_field( (string) get_option( 'woocommerce_store_city', '' ) ),
			'postal_code'        => $postal_code,
			'postal_code_prefix' => strtoupper( substr( preg_replace( '/[^A-Za-z0-9]/', '', $postal_code ) ?: '', 0, 3 ) ),
			'address_line_1'     => sanitize_text_field( (string) get_option( 'woocommerce_store_address', '' ) ),
			'address_line_2'     => sanitize_text_field( (string) get_option( 'woocommerce_store_address_2', '' ) ),
			'currency'           => function_exists( 'get_woocommerce_currency' ) ? sanitize_text_field( get_woocommerce_currency() ) : sanitize_text_field( (string) get_option( 'woocommerce_currency', '' ) ),
			'timezone'           => sanitize_text_field( wp_timezone_string() ),
			'locale'             => sanitize_text_field( get_locale() ),
			'catalog_signals'    => array(
				'categories' => $this->catalog_signal_terms( 'product_cat' ),
				'tags'       => $this->catalog_signal_terms( 'product_tag' ),
				'keywords'   => $this->catalog_signal_keywords(),
			),
		);

		return rest_ensure_response(
			array(
				'site_url'       => home_url( '/' ),
				'plugin_version' => WC_COMPETITOR_MONITOR_VERSION,
				'site_profile'   => $profile,
				'synced_at'      => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Gets one WooCommerce product without exposing descriptions or content.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_product( WP_REST_Request $request ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return new WP_Error( 'cpsm_woocommerce_inactive', __( 'WooCommerce is not active.', 'competitor-price-stock-monitor' ), array( 'status' => 503 ) );
		}

		$product_id = absint( $request->get_param( 'id' ) );
		if ( $product_id <= 0 ) {
			return new WP_Error( 'cpsm_invalid_product', __( 'A valid WooCommerce product is required.', 'competitor-price-stock-monitor' ), array( 'status' => 400 ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'cpsm_product_not_found', __( 'WooCommerce product not found.', 'competitor-price-stock-monitor' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'product' => $this->product_payload( $product ),
			)
		);
	}

	/**
	 * Creates a mapping from a Pro browser capture suggestion.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_mapping( WP_REST_Request $request ) {
		$params     = $this->json_params( $request );
		$product_id = absint( $params['product_id'] ?? 0 );
		$url        = esc_url_raw( (string) ( $params['competitor_url'] ?? '' ) );
		$validation = $this->validate_product_and_url( $product_id, $url );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$existing = $this->db->get_mapping_by_competitor_url( $url );
		if ( $existing ) {
			return rest_ensure_response(
				array(
					'created' => false,
					'mapping' => $this->public_mapping( $existing ),
				)
			);
		}

		$competitor_name = $this->competitor_name_from_params( $params, $url );
		$this->db->capture_original_product_price( $product_id, 'rest_mapping_created' );
		$mapping_id      = $this->db->insert_mapping(
			array(
				'product_id'             => $product_id,
				'competitor_name'        => $competitor_name,
				'competitor_product_title' => sanitize_text_field( (string) ( $params['competitor_product_title'] ?? $params['product_title'] ?? '' ) ),
				'competitor_url'         => $url,
				'price_selector'         => WC_Competitor_Monitor_Security::sanitize_selector( (string) ( $params['price_selector'] ?? '' ) ),
				'stock_selector'         => WC_Competitor_Monitor_Security::sanitize_selector( (string) ( $params['stock_selector'] ?? '' ) ),
				'currency'               => WC_Competitor_Monitor_Security::sanitize_currency( (string) ( $params['currency'] ?? '' ) ),
				'min_margin_percentage'  => isset( $params['min_margin_percentage'] ) ? (float) $params['min_margin_percentage'] : 20,
				'suggested_increase_mode' => 'global',
				'suggested_increase_percentage' => null,
				'active'                 => isset( $params['active'] ) ? (int) ! empty( $params['active'] ) : 1,
			)
		);

		$mapping = $this->db->get_mapping( $mapping_id );
		$this->db->ensure_mapping_sync_uuid( $mapping_id );
		$this->store_latest_values( $mapping_id, $product_id, $params );

		return rest_ensure_response(
			array(
				'created' => true,
				'mapping' => $this->public_mapping( $this->db->get_mapping( $mapping_id ) ?: $mapping ),
			)
		);
	}

	/**
	 * Updates mapped values so Pro users can fix selectors and extracted values.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_mapping( WP_REST_Request $request ) {
		$mapping_id = absint( $request->get_param( 'id' ) );
		$mapping    = $this->db->get_mapping( $mapping_id );
		if ( ! $mapping ) {
			return new WP_Error( 'cpsm_mapping_not_found', __( 'Mapping not found.', 'competitor-price-stock-monitor' ), array( 'status' => 404 ) );
		}

		$params     = $this->json_params( $request );
		$product_id = isset( $params['product_id'] ) ? absint( $params['product_id'] ) : absint( $mapping->product_id );
		$url        = isset( $params['competitor_url'] ) ? esc_url_raw( (string) $params['competitor_url'] ) : (string) $mapping->competitor_url;
		$validation = $this->validate_product_and_url( $product_id, $url );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$this->db->capture_original_product_price( $product_id, 'rest_mapping_updated' );

		$this->db->update_mapping(
			$mapping_id,
			array(
				'product_id'             => $product_id,
				'competitor_name'        => isset( $params['competitor_name'] ) ? sanitize_text_field( (string) $params['competitor_name'] ) : (string) $mapping->competitor_name,
				'competitor_product_title' => isset( $params['competitor_product_title'] ) || isset( $params['product_title'] ) ? sanitize_text_field( (string) ( $params['competitor_product_title'] ?? $params['product_title'] ?? '' ) ) : (string) ( $mapping->competitor_product_title ?? '' ),
				'competitor_url'         => $url,
				'price_selector'         => isset( $params['price_selector'] ) ? WC_Competitor_Monitor_Security::sanitize_selector( (string) $params['price_selector'] ) : (string) $mapping->price_selector,
				'stock_selector'         => isset( $params['stock_selector'] ) ? WC_Competitor_Monitor_Security::sanitize_selector( (string) $params['stock_selector'] ) : (string) $mapping->stock_selector,
				'browser_user_agent'     => (string) ( $mapping->browser_user_agent ?? '' ),
				'browser_cookie_header'  => (string) ( $mapping->browser_cookie_header ?? '' ),
				'currency'               => isset( $params['currency'] ) ? WC_Competitor_Monitor_Security::sanitize_currency( (string) $params['currency'] ) : (string) $mapping->currency,
				'min_margin_percentage'  => isset( $params['min_margin_percentage'] ) ? (float) $params['min_margin_percentage'] : (float) $mapping->min_margin_percentage,
				'suggested_increase_mode' => (string) ( $mapping->suggested_increase_mode ?? 'global' ),
				'suggested_increase_percentage' => isset( $mapping->suggested_increase_percentage ) ? (float) $mapping->suggested_increase_percentage : null,
				'active'                 => isset( $params['active'] ) ? (int) ! empty( $params['active'] ) : (int) $mapping->active,
			)
		);

		$this->store_latest_values( $mapping_id, $product_id, $params );
		$this->db->ensure_mapping_sync_uuid( $mapping_id );

		return rest_ensure_response(
			array(
				'updated' => true,
				'mapping' => $this->public_mapping( $this->db->get_mapping( $mapping_id ) ),
			)
		);
	}

	/**
	 * Deletes a mapping from a signed SaaS request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_mapping( WP_REST_Request $request ) {
		$mapping_id = absint( $request->get_param( 'id' ) );
		$mapping    = $this->db->get_mapping( $mapping_id );
		if ( ! $mapping ) {
			return new WP_Error( 'cpsm_mapping_not_found', __( 'Mapping not found.', 'competitor-price-stock-monitor' ), array( 'status' => 404 ) );
		}

		$sync_uuid = $this->db->ensure_mapping_sync_uuid( $mapping_id );
		$this->db->delete_mapping( $mapping_id );

		return rest_ensure_response(
			array(
				'deleted'   => true,
				'id'        => $mapping_id,
				'sync_uuid' => $sync_uuid,
				'site_url'  => home_url( '/' ),
			)
		);
	}

	/**
	 * Returns the WooCommerce-calculated attributed Pro pricing impact.
	 *
	 * @return WP_REST_Response
	 */
	public function get_profit_impact(): WP_REST_Response {
		return rest_ensure_response(
			array(
				'site_url' => home_url( '/' ),
				'summary'  => $this->db->get_profit_impact_summary( 10 ),
			)
		);
	}

	/**
	 * Gets request JSON parameters.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>
	 */
	private function json_params( WP_REST_Request $request ): array {
		$params = $request->get_json_params();
		return is_array( $params ) ? $params : array();
	}

	/**
	 * Stores latest extracted values and a history row when available.
	 *
	 * @param int                 $mapping_id Mapping ID.
	 * @param int                 $product_id Product ID.
	 * @param array<string,mixed> $params Request params.
	 * @return void
	 */
	private function store_latest_values( int $mapping_id, int $product_id, array $params ): void {
		$has_price = array_key_exists( 'last_price', $params ) || array_key_exists( 'price', $params );
		$has_stock = array_key_exists( 'last_stock_status', $params ) || array_key_exists( 'stock_status', $params );

		if ( ! $has_price && ! $has_stock ) {
			return;
		}

		$price = $this->nullable_float( $params['last_price'] ?? $params['price'] ?? null );
		$stock = isset( $params['last_stock_status'] ) ? sanitize_key( (string) $params['last_stock_status'] ) : ( isset( $params['stock_status'] ) ? sanitize_key( (string) $params['stock_status'] ) : '' );
		$title = sanitize_text_field( (string) ( $params['competitor_product_title'] ?? $params['product_title'] ?? '' ) );

		if ( null === $price && '' === $stock ) {
			return;
		}

		$this->db->update_mapping_after_check( $mapping_id, $price, '' !== $stock ? $stock : null, $title );

		$our_price = $this->product_price( $product_id );
		$this->db->insert_history(
			array(
				'mapping_id'              => $mapping_id,
				'product_id'              => $product_id,
				'competitor_price'        => $price,
				'competitor_stock_status' => '' !== $stock ? $stock : null,
				'our_price'               => $our_price,
				'difference_amount'       => ( null !== $price && null !== $our_price ) ? $our_price - $price : null,
				'difference_percentage'   => ( null !== $price && $price > 0 && null !== $our_price ) ? ( ( $our_price - $price ) / $price ) * 100 : null,
				'raw_status'              => 'browser_extension',
			)
		);
	}

	/**
	 * Validates a WooCommerce product and competitor URL.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $url Competitor URL.
	 * @return true|WP_Error
	 */
	private function validate_product_and_url( int $product_id, string $url ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return new WP_Error( 'cpsm_woocommerce_inactive', __( 'WooCommerce is not active.', 'competitor-price-stock-monitor' ), array( 'status' => 503 ) );
		}

		if ( $product_id <= 0 || ! wc_get_product( $product_id ) ) {
			return new WP_Error( 'cpsm_product_required', __( 'A valid WooCommerce product is required.', 'competitor-price-stock-monitor' ), array( 'status' => 400 ) );
		}

		$validation = WC_Competitor_Monitor_Security::validate_competitor_url( $url );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		return true;
	}

	/**
	 * Converts a mapping row into an API-safe payload.
	 *
	 * @param object|null $mapping Mapping row.
	 * @return array<string,mixed>|null
	 */
	private function public_mapping( ?object $mapping ): ?array {
		if ( ! $mapping ) {
			return null;
		}

		$product_id = absint( $mapping->product_id );
		$product    = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;

		return array(
			'id'                       => absint( $mapping->id ),
			'sync_uuid'                => (string) ( $mapping->sync_uuid ?? '' ),
			'product_id'               => $product_id,
			'product_name'             => $product ? $product->get_name() : get_the_title( $product_id ),
			'product_sku'              => $product ? $product->get_sku() : '',
			'product_edit_url'         => admin_url( 'post.php?post=' . $product_id . '&action=edit' ),
			'competitor_name'          => (string) $mapping->competitor_name,
			'competitor_product_title' => (string) ( $mapping->competitor_product_title ?? '' ),
			'competitor_url'           => (string) $mapping->competitor_url,
			'price_selector'           => (string) $mapping->price_selector,
			'stock_selector'           => (string) $mapping->stock_selector,
			'currency'                 => (string) $mapping->currency,
			'min_margin_percentage'    => (float) $mapping->min_margin_percentage,
			'active'                   => (bool) $mapping->active,
			'last_price'               => null !== $mapping->last_price ? (float) $mapping->last_price : null,
			'last_stock_status'        => (string) ( $mapping->last_stock_status ?? '' ),
			'last_checked_at'          => (string) ( $mapping->last_checked_at ?? '' ),
			'updated_at'               => (string) ( $mapping->updated_at ?? '' ),
			'sync_status'              => (string) ( $mapping->sync_status ?? '' ),
			'edit_url'                 => add_query_arg(
				array(
					'page'       => 'competitor-price-stock-monitor-products',
					'mapping_id' => absint( $mapping->id ),
				),
				admin_url( 'admin.php' )
			),
		);
	}

	/**
	 * Builds a privacy-safe WooCommerce product payload.
	 *
	 * @param WC_Product $product Product.
	 * @return array<string,mixed>
	 */
	private function product_payload( $product ): array {
		$product_id = absint( $product->get_id() );
		$parent_id  = method_exists( $product, 'get_parent_id' ) ? absint( $product->get_parent_id() ) : 0;
		$terms_id   = $parent_id > 0 && 'variation' === $product->get_type() ? $parent_id : $product_id;
		$cost       = $this->db->get_product_cost( $product_id );
		if ( null === $cost && $parent_id > 0 ) {
			$cost = $this->db->get_product_cost( $parent_id );
		}
		$modified = method_exists( $product, 'get_date_modified' ) ? $product->get_date_modified() : null;

		return array(
			'id'            => $product_id,
			'parent_id'     => $parent_id,
			'name'          => $product->get_name(),
			'sku'           => $product->get_sku(),
			'gtin'          => $this->product_identifier( $product_id, array( '_global_unique_id', '_gtin', '_ean', '_upc', '_wpm_gtin_code', '_alg_ean' ) ),
			'mpn'           => $this->product_identifier( $product_id, array( '_mpn', 'mpn', '_manufacturer_part_number' ) ),
			'type'          => $product->get_type(),
			'price'         => $product->get_price( 'edit' ),
			'regular_price' => $product->get_regular_price( 'edit' ),
			'stock_status'  => $product->get_stock_status( 'edit' ),
			'cost'          => null !== $cost ? (float) $cost : null,
			'edit_url'      => admin_url( 'post.php?post=' . $product_id . '&action=edit' ),
			'categories'    => $this->product_terms_payload( $terms_id, 'product_cat' ),
			'tags'          => $this->product_terms_payload( $terms_id, 'product_tag' ),
			'attributes'    => $this->product_attributes_payload( $product ),
			'updated_at'    => $modified ? $modified->date_i18n( 'Y-m-d H:i:s' ) : '',
		);
	}

	/**
	 * Reads the first available product identifier from known metadata keys.
	 *
	 * @param int              $product_id Product ID.
	 * @param array<int,string> $meta_keys Candidate metadata keys.
	 * @return string
	 */
	private function product_identifier( int $product_id, array $meta_keys ): string {
		foreach ( $meta_keys as $meta_key ) {
			$value = get_post_meta( $product_id, $meta_key, true );
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				return sanitize_text_field( (string) $value );
			}
		}

		return '';
	}

	/**
	 * Builds compact product attributes for discovery matching.
	 *
	 * @param WC_Product $product Product.
	 * @return array<int,array{name:string,value:string}>
	 */
	private function product_attributes_payload( $product ): array {
		$payload    = array();
		$product_id = absint( $product->get_id() );
		$attributes = $product->get_attributes();

		foreach ( (array) $attributes as $name => $attribute ) {
			if ( count( $payload ) >= 12 ) {
				break;
			}

			if ( $attribute instanceof WC_Product_Attribute ) {
				$attribute_name = wc_attribute_label( $attribute->get_name(), $product );
				if ( $attribute->is_taxonomy() ) {
					$values = wc_get_product_terms( $product_id, $attribute->get_name(), array( 'fields' => 'names' ) );
				} else {
					$values = $attribute->get_options();
				}
				$value = implode( ', ', array_slice( array_map( 'sanitize_text_field', (array) $values ), 0, 4 ) );
			} else {
				$clean_name     = preg_replace( '/^attribute_/', '', (string) $name );
				$attribute_name = wc_attribute_label( $clean_name, $product );
				$value          = sanitize_text_field( (string) $attribute );
			}

			if ( '' === trim( (string) $attribute_name ) && '' === trim( (string) $value ) ) {
				continue;
			}

			$payload[] = array(
				'name'  => sanitize_text_field( (string) $attribute_name ),
				'value' => sanitize_text_field( (string) $value ),
			);
		}

		return $payload;
	}

	/**
	 * Gets aggregated WooCommerce catalog term signals without product names.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return array<int,array{name:string,slug:string,count:int}>
	 */
	private function catalog_signal_terms( string $taxonomy ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => 10,
			)
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$payload = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$payload[] = array(
				'name'  => sanitize_text_field( $term->name ),
				'slug'  => sanitize_title( $term->slug ),
				'count' => absint( $term->count ),
			);
		}

		return $payload;
	}

	/**
	 * Gets compact catalog keywords from visible product taxonomy signals.
	 *
	 * @return array<int,string>
	 */
	private function catalog_signal_keywords(): array {
		$keywords = array();
		foreach ( array( 'product_cat', 'product_tag' ) as $taxonomy ) {
			foreach ( $this->catalog_signal_terms( $taxonomy ) as $term ) {
				foreach ( array( $term['name'] ?? '', $term['slug'] ?? '' ) as $value ) {
					$value = sanitize_text_field( (string) $value );
					if ( '' !== $value ) {
						$keywords[] = $value;
					}
				}
			}
		}

		return array_values( array_slice( array_unique( $keywords ), 0, 20 ) );
	}

	/**
	 * Gets compact product term signals.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $taxonomy Taxonomy.
	 * @return array<int,array<string,string>>
	 */
	private function product_terms_payload( int $product_id, string $taxonomy ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$terms = get_the_terms( $product_id, $taxonomy );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$payload = array();
		foreach ( array_slice( $terms, 0, 8 ) as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$payload[] = array(
				'name' => sanitize_text_field( $term->name ),
				'slug' => sanitize_title( $term->slug ),
			);
		}

		return $payload;
	}

	/**
	 * Gets a competitor name from request data or URL host.
	 *
	 * @param array<string,mixed> $params Request params.
	 * @param string              $url Competitor URL.
	 * @return string
	 */
	private function competitor_name_from_params( array $params, string $url ): string {
		$name = isset( $params['competitor_name'] ) ? sanitize_text_field( (string) $params['competitor_name'] ) : '';
		if ( '' !== $name ) {
			return $name;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		$host = is_string( $host ) ? preg_replace( '/^www\./', '', $host ) : '';

		return sanitize_text_field( $host ?: __( 'Competitor', 'competitor-price-stock-monitor' ) );
	}

	/**
	 * Extracts the API key from headers.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string
	 */
	private function request_api_key( WP_REST_Request $request ): string {
		$api_key = $request->get_header( 'x-api-key' );
		if ( $api_key ) {
			return sanitize_text_field( $api_key );
		}

		$authorization = $request->get_header( 'authorization' );
		if ( is_string( $authorization ) && str_starts_with( $authorization, 'Bearer ' ) ) {
			return sanitize_text_field( substr( $authorization, 7 ) );
		}

		return '';
	}

	/**
	 * Applies a lightweight REST bridge rate limit.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	private function check_rate_limit( WP_REST_Request $request ) {
		$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$site_id = sanitize_text_field( (string) $request->get_header( 'x-cpsm-site-id' ) );
		$key_id  = sanitize_text_field( (string) $request->get_header( 'x-cpsm-key-id' ) );
		$key     = 'cpsm_rest_rate_' . md5( $ip . '|' . $site_id . '|' . $key_id );
		$count   = absint( get_transient( $key ) );
		if ( $count >= 240 ) {
			return new WP_Error( 'cpsm_rest_rate_limited', __( 'Too many Pro bridge requests. Please try again shortly.', 'competitor-price-stock-monitor' ), array( 'status' => 429 ) );
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Reads a nullable float from an API value.
	 *
	 * @param mixed $value Input value.
	 * @return float|null
	 */
	private function nullable_float( mixed $value ): ?float {
		if ( null === $value || '' === $value || ! is_numeric( $value ) ) {
			return null;
		}

		return (float) $value;
	}

	/**
	 * Gets the current WooCommerce product price.
	 *
	 * @param int $product_id Product ID.
	 * @return float|null
	 */
	private function product_price( int $product_id ): ?float {
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
}
