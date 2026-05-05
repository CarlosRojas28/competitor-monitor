<?php
/**
 * Pro SaaS client.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Connects the WordPress plugin to the Pro SaaS API.
 */
class WC_Competitor_Monitor_Pro_Client {

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
	 * Checks whether Pro is enabled and connected.
	 *
	 * @return bool
	 */
	public function is_connected(): bool {
		$settings = $this->db->get_settings();

		$hmac_connected = ! empty( $settings['pro_enabled'] )
			&& 'active' === (string) $settings['pro_license_status']
			&& 'hmac_v1' === (string) ( $settings['bridge_auth_version'] ?? '' )
			&& ! empty( $settings['pro_site_id'] )
			&& ! empty( $settings['pro_key_id'] )
			&& ! empty( $settings['pro_plugin_to_saas_secret_encrypted'] )
			&& ! empty( $settings['pro_saas_url'] );

		$legacy_dev_connected = WC_Competitor_Monitor_Bridge_Auth::dev_mode()
			&& ! empty( $settings['pro_enabled'] )
			&& 'active' === (string) $settings['pro_license_status']
			&& ! empty( $settings['pro_api_key'] )
			&& ! empty( $settings['pro_saas_url'] );

		return $hmac_connected || $legacy_dev_connected;
	}

	/**
	 * Activates a Pro bridge with the SaaS.
	 *
	 * @param string $saas_url SaaS base URL.
	 * @param string $license_key Plugin registration key.
	 * @return array<string,mixed>
	 */
	public function activate_license( string $saas_url, string $license_key ): array {
		$saas_url    = $this->sanitize_saas_url( $saas_url );
		$license_key = sanitize_text_field( $license_key );

		if ( '' === $saas_url || '' === $license_key ) {
			return array(
				'success' => false,
				'error'   => __( 'SaaS URL and plugin registration key are required.', 'competitor-price-stock-monitor' ),
			);
		}

		$response = $this->request(
			$saas_url,
			'/v1/licenses/activate',
			array(
				'license_key'    => $license_key,
				'site_url'       => home_url( '/' ),
				'plugin_version' => WC_COMPETITOR_MONITOR_VERSION,
			),
			''
		);

		if ( empty( $response['success'] ) ) {
			$this->save_license_state( $saas_url, $license_key, '', '', '', (string) ( $response['error'] ?? '' ) );
			return $response;
		}

		$body    = is_array( $response['body'] ?? null ) ? $response['body'] : array();
		$license = is_array( $body['license'] ?? null ) ? $body['license'] : array();
		$api_key = isset( $body['api_key'] ) ? sanitize_text_field( (string) $body['api_key'] ) : '';
		$bridge  = is_array( $body['bridge'] ?? null ) ? $body['bridge'] : array();
		$status  = isset( $license['status'] ) ? sanitize_key( (string) $license['status'] ) : '';
		$plan    = isset( $license['plan'] ) ? sanitize_key( (string) $license['plan'] ) : '';

		if ( 'active' !== $status || empty( $bridge['site_id'] ) || empty( $bridge['key_id'] ) || empty( $bridge['plugin_to_saas_secret'] ) || empty( $bridge['saas_to_plugin_secret'] ) ) {
			$this->save_license_state( $saas_url, $license_key, '', $status, $plan, __( 'License activation response was incomplete.', 'competitor-price-stock-monitor' ), array() );
			return array(
				'success' => false,
				'error'   => __( 'License activation response was incomplete.', 'competitor-price-stock-monitor' ),
			);
		}

		$this->save_license_state( $saas_url, $license_key, $api_key, $status, $plan, __( 'License active.', 'competitor-price-stock-monitor' ), $bridge );
		$this->sync_site_profile( true, 'activation' );

		return array(
			'success' => true,
			'license' => $license,
		);
	}

	/**
	 * Rotates Pro bridge credentials using the current signed bridge.
	 *
	 * @return array<string,mixed>
	 */
	public function rotate_bridge_credentials(): array {
		if ( ! $this->is_connected() ) {
			return array(
				'success' => false,
				'error'   => __( 'Pro license is not active.', 'competitor-price-stock-monitor' ),
			);
		}

		$settings = $this->db->get_settings();
		$response = $this->request(
			$this->sanitize_saas_url( (string) $settings['pro_saas_url'] ),
			'/v1/licenses/rotate-bridge',
			array(
				'site_url'       => home_url( '/' ),
				'plugin_version' => WC_COMPETITOR_MONITOR_VERSION,
			),
			''
		);

		if ( empty( $response['success'] ) ) {
			return $response;
		}

		$body   = is_array( $response['body'] ?? null ) ? $response['body'] : array();
		$bridge = is_array( $body['bridge'] ?? null ) ? $body['bridge'] : array();
		if ( empty( $bridge['site_id'] ) || empty( $bridge['key_id'] ) || empty( $bridge['plugin_to_saas_secret'] ) || empty( $bridge['saas_to_plugin_secret'] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'The SaaS did not return complete bridge credentials.', 'competitor-price-stock-monitor' ),
			);
		}

		$this->save_license_state(
			(string) $settings['pro_saas_url'],
			'',
			(string) ( $settings['pro_api_key'] ?? '' ),
			'active',
			(string) ( $settings['pro_plan'] ?? '' ),
			__( 'Bridge credentials rotated.', 'competitor-price-stock-monitor' ),
			$bridge
		);

		return array( 'success' => true );
	}

	/**
	 * Requests an automatic mapping suggestion from the SaaS.
	 *
	 * @param string $competitor_url Competitor URL.
	 * @param bool   $force_duplicate Force a fresh extraction even when the SaaS has seen this URL before.
	 * @param array<string,mixed> $context Optional job context.
	 * @return array<string,mixed>
	 */
	public function auto_map( string $competitor_url, bool $force_duplicate = false, array $context = array() ): array {
		if ( ! $this->is_connected() ) {
			return array(
				'success' => false,
				'error'   => __( 'Pro license is not active.', 'competitor-price-stock-monitor' ),
			);
		}

		$settings = $this->db->get_settings();
		$this->sync_site_profile( false, 'ai_discovery' );
		$saas_url = $this->sanitize_saas_url( (string) $settings['pro_saas_url'] );
		$api_key  = $this->legacy_api_key_if_needed( $settings );
		$body     = array(
			'url'      => esc_url_raw( $competitor_url ),
			'site_url' => home_url( '/' ),
		);

		if ( $force_duplicate ) {
			$body['force_duplicate'] = true;
		}

		if ( ! empty( $context['product_id'] ) ) {
			$body['product_id'] = absint( $context['product_id'] );
		}

		$response = $this->request(
			$saas_url,
			'/v1/jobs/auto-map',
			$body,
			$api_key
		);

		if ( empty( $response['success'] ) ) {
			return $response;
		}

		$job = is_array( $response['body'] ?? null ) ? $response['body'] : array();
		if ( empty( $job['id'] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'The SaaS did not return a job ID.', 'competitor-price-stock-monitor' ),
			);
		}

		return $this->poll_job( $saas_url, $api_key, sanitize_text_field( (string) $job['id'] ) );
	}

	/**
	 * Sends the latest WooCommerce-attributed Pro pricing impact to the SaaS.
	 *
	 * @param array<string,mixed> $summary Profit impact summary.
	 * @return array<string,mixed>
	 */
	public function sync_profit_impact( array $summary ): array {
		if ( ! $this->is_connected() ) {
			return array(
				'success' => false,
				'error'   => __( 'Pro license is not active.', 'competitor-price-stock-monitor' ),
			);
		}

		$settings = $this->db->get_settings();
		return $this->request(
			$this->sanitize_saas_url( (string) $settings['pro_saas_url'] ),
			'/v1/site-impact',
			array(
				'site_url'       => home_url( '/' ),
				'plugin_version' => WC_COMPETITOR_MONITOR_VERSION,
				'summary'        => $summary,
			),
			$this->legacy_api_key_if_needed( $settings )
		);
	}

	/**
	 * Synchronizes the WooCommerce store location profile to the SaaS.
	 *
	 * @param bool   $force Force sending even when unchanged.
	 * @param string $reason Sync reason.
	 * @return array<string,mixed>
	 */
	public function sync_site_profile( bool $force = false, string $reason = 'manual' ): array {
		$settings = $this->db->get_settings();
		if ( ! $this->has_hmac_bridge( $settings ) ) {
			return array(
				'success' => false,
				'error'   => __( 'A signed Pro bridge is required before syncing store location.', 'competitor-price-stock-monitor' ),
			);
		}

		$profile = $this->site_profile_payload();
		$hash    = hash( 'sha256', (string) wp_json_encode( $profile ) );
		if ( ! $force && hash_equals( (string) ( $settings['last_site_profile_hash'] ?? '' ), $hash ) ) {
			return array(
				'success' => true,
				'skipped' => true,
			);
		}

		$response = $this->request(
			$this->sanitize_saas_url( (string) $settings['pro_saas_url'] ),
			'/v1/site-profile',
			array(
				'site_url'       => home_url( '/' ),
				'plugin_version' => WC_COMPETITOR_MONITOR_VERSION,
				'reason'         => sanitize_key( $reason ),
				'site_profile'   => $profile,
			),
			''
		);

		$this->db->update_settings(
			array(
				'last_site_profile_sync_at'      => current_time( 'mysql' ),
				'last_site_profile_sync_status'  => empty( $response['success'] ) ? 'error' : 'success',
				'last_site_profile_sync_message' => empty( $response['success'] ) ? sanitize_text_field( (string) ( $response['error'] ?? '' ) ) : __( 'Store location profile synchronized with SaaS.', 'competitor-price-stock-monitor' ),
				'last_site_profile_hash'         => empty( $response['success'] ) ? (string) ( $settings['last_site_profile_hash'] ?? '' ) : $hash,
			)
		);

		return $response;
	}

	/**
	 * Synchronizes local competitor mappings to the SaaS.
	 *
	 * @param string              $mode Sync mode: full, upsert, or delete.
	 * @param array<int,array<string,mixed>> $mappings Mapping payloads.
	 * @param array<int,string>   $deleted_sync_uuids Deleted mapping sync UUIDs.
	 * @param string              $reason Change reason.
	 * @return array<string,mixed>
	 */
	public function sync_mappings( string $mode, array $mappings, array $deleted_sync_uuids = array(), string $reason = 'manual' ): array {
		if ( ! $this->is_connected() ) {
			return array(
				'success' => false,
				'error'   => __( 'Pro license is not active.', 'competitor-price-stock-monitor' ),
			);
		}

		$mode = sanitize_key( $mode );
		if ( ! in_array( $mode, array( 'full', 'upsert', 'delete' ), true ) ) {
			$mode = 'upsert';
		}

		$settings = $this->db->get_settings();
		return $this->request(
			$this->sanitize_saas_url( (string) $settings['pro_saas_url'] ),
			'/v1/plugin-sync/mappings',
			array(
				'site_url'           => home_url( '/' ),
				'plugin_version'     => WC_COMPETITOR_MONITOR_VERSION,
				'mode'               => $mode,
				'reason'             => sanitize_key( $reason ),
				'mappings'           => array_values( $mappings ),
				'deleted_sync_uuids' => array_values( array_map( 'sanitize_text_field', $deleted_sync_uuids ) ),
			),
			$this->legacy_api_key_if_needed( $settings )
		);
	}

	/**
	 * Polls the SaaS until a job is complete.
	 *
	 * @param string $saas_url SaaS URL.
	 * @param string $api_key API key.
	 * @param string $job_id Job ID.
	 * @return array<string,mixed>
	 */
	private function poll_job( string $saas_url, string $api_key, string $job_id ): array {
		for ( $attempt = 0; $attempt < 45; $attempt++ ) {
			sleep( 1 );
			$response = $this->get( $saas_url, '/v1/jobs/' . rawurlencode( $job_id ), $api_key );
			if ( empty( $response['success'] ) ) {
				return $response;
			}

			$job    = is_array( $response['body'] ?? null ) ? $response['body'] : array();
			$status = isset( $job['status'] ) ? sanitize_key( (string) $job['status'] ) : '';

			if ( in_array( $status, array( 'queued', 'running' ), true ) ) {
				continue;
			}

			if ( 'completed' !== $status ) {
				$message = '';
				if ( isset( $job['error']['message'] ) ) {
					$message = sanitize_text_field( (string) $job['error']['message'] );
				} elseif ( isset( $job['result']['message'] ) ) {
					$message = sanitize_text_field( (string) $job['result']['message'] );
				}

				return array(
					'success' => false,
					'error'   => $message ?: __( 'The SaaS could not complete the mapping job.', 'competitor-price-stock-monitor' ),
					'job'     => $job,
				);
			}

			return array(
				'success' => true,
				'job'     => $job,
			);
		}

		return array(
			'success' => false,
			'error'   => __( 'The SaaS mapping job timed out.', 'competitor-price-stock-monitor' ),
		);
	}

	/**
	 * Sends a POST request.
	 *
	 * @param string              $saas_url SaaS URL.
	 * @param string              $path API path.
	 * @param array<string,mixed> $body Request body.
	 * @param string              $api_key Optional API key.
	 * @return array<string,mixed>
	 */
	private function request( string $saas_url, string $path, array $body, string $api_key ): array {
		return $this->remote_request( 'POST', $saas_url, $path, $api_key, $body );
	}

	/**
	 * Sends a GET request.
	 *
	 * @param string $saas_url SaaS URL.
	 * @param string $path API path.
	 * @param string $api_key API key.
	 * @return array<string,mixed>
	 */
	private function get( string $saas_url, string $path, string $api_key ): array {
		return $this->remote_request( 'GET', $saas_url, $path, $api_key, array() );
	}

	/**
	 * Performs a remote request.
	 *
	 * @param string              $method HTTP method.
	 * @param string              $saas_url SaaS base URL.
	 * @param string              $path API path.
	 * @param string              $api_key API key.
	 * @param array<string,mixed> $body Request body.
	 * @return array<string,mixed>
	 */
	private function remote_request( string $method, string $saas_url, string $path, string $api_key, array $body ): array {
		$url     = trailingslashit( $saas_url ) . ltrim( $path, '/' );
		$headers = array(
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
		);
		$raw_body = 'GET' !== $method ? (string) wp_json_encode( $body ) : '';

		if ( '' !== $api_key ) {
			$headers['X-Api-Key'] = $api_key;
		}

		if ( '' === $api_key ) {
			$settings = $this->db->get_settings();
			$secret   = WC_Competitor_Monitor_Bridge_Auth::decrypt_secret( (string) ( $settings['pro_plugin_to_saas_secret_encrypted'] ?? '' ) );
			if ( '' !== $secret && ! empty( $settings['pro_site_id'] ) && ! empty( $settings['pro_key_id'] ) ) {
				$headers = array_merge(
					$headers,
					WC_Competitor_Monitor_Bridge_Auth::sign_headers(
						$method,
						$url,
						$raw_body,
						(string) $settings['pro_site_id'],
						(string) $settings['pro_key_id'],
						$secret
					)
				);
			}
		}

		$args = array(
			'method'      => $method,
			'timeout'     => 20,
			'headers'     => $headers,
			'redirection' => 2,
			'user-agent'  => 'Competitor Monitor WordPress/' . WC_COMPETITOR_MONITOR_VERSION,
		);

		if ( 'GET' !== $method ) {
			$args['body'] = $raw_body;
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = (string) wp_remote_retrieve_body( $response );
		$json   = json_decode( $raw, true );

		if ( ! is_array( $json ) ) {
			return array(
				'success' => false,
				'error'   => __( 'The SaaS returned an invalid JSON response.', 'competitor-price-stock-monitor' ),
			);
		}

		if ( $status < 200 || $status >= 300 ) {
			$message = isset( $json['error']['message'] ) ? sanitize_text_field( (string) $json['error']['message'] ) : __( 'The SaaS request failed.', 'competitor-price-stock-monitor' );
			return array(
				'success' => false,
				'error'   => $message,
				'body'    => $json,
			);
		}

		return array(
			'success' => true,
			'body'    => $json,
		);
	}

	/**
	 * Stores license state.
	 *
	 * @param string $saas_url SaaS URL.
	 * @param string $license_key License key.
	 * @param string $api_key API key.
	 * @param string $status License status.
	 * @param string $plan Plan.
	 * @param string $message Status message.
	 * @return void
	 */
	private function save_license_state( string $saas_url, string $license_key, string $api_key, string $status, string $plan, string $message, array $bridge = array() ): void {
		$settings = array(
				'pro_enabled'         => 1,
				'pro_saas_url'        => $saas_url,
				'pro_license_key'     => '',
				'pro_api_key'         => WC_Competitor_Monitor_Bridge_Auth::dev_mode() ? $api_key : '',
				'pro_license_status'  => $status,
				'pro_plan'            => $plan,
				'pro_license_message' => $message,
		);

		if ( '' !== $license_key ) {
			$settings['pro_license_key_encrypted'] = WC_Competitor_Monitor_Bridge_Auth::encrypt_secret( $license_key );
			$settings['pro_license_key_preview']   = $this->preview_secret( $license_key );
		}

		if ( ! empty( $bridge ) ) {
			$plugin_to_saas_secret = sanitize_text_field( (string) ( $bridge['plugin_to_saas_secret'] ?? '' ) );
			$saas_to_plugin_secret = sanitize_text_field( (string) ( $bridge['saas_to_plugin_secret'] ?? '' ) );
			$settings = array_merge(
				$settings,
				array(
					'bridge_auth_version' => sanitize_key( (string) ( $bridge['auth_version'] ?? 'hmac_v1' ) ),
					'pro_site_id'         => sanitize_text_field( (string) ( $bridge['site_id'] ?? '' ) ),
					'pro_key_id'          => sanitize_text_field( (string) ( $bridge['key_id'] ?? '' ) ),
					'pro_plugin_to_saas_secret_encrypted' => WC_Competitor_Monitor_Bridge_Auth::encrypt_secret( $plugin_to_saas_secret ),
					'pro_plugin_to_saas_secret_preview' => sanitize_text_field( (string) ( $bridge['plugin_to_saas_secret_preview'] ?? $this->preview_secret( $plugin_to_saas_secret ) ) ),
					'pro_saas_to_plugin_secret_encrypted' => WC_Competitor_Monitor_Bridge_Auth::encrypt_secret( $saas_to_plugin_secret ),
					'pro_saas_to_plugin_secret_preview' => sanitize_text_field( (string) ( $bridge['saas_to_plugin_secret_preview'] ?? $this->preview_secret( $saas_to_plugin_secret ) ) ),
				)
			);
		}

		$this->db->update_settings( $settings );
	}

	/**
	 * Builds the sync-safe WooCommerce store location profile.
	 *
	 * @return array<string,mixed>
	 */
	private function site_profile_payload(): array {
		$country_state = sanitize_text_field( (string) get_option( 'woocommerce_default_country', '' ) );
		$country       = $country_state;
		$region        = '';
		if ( str_contains( $country_state, ':' ) ) {
			$parts   = explode( ':', $country_state, 2 );
			$country = sanitize_text_field( (string) ( $parts[0] ?? '' ) );
			$region  = sanitize_text_field( (string) ( $parts[1] ?? '' ) );
		}

		$postal_code = sanitize_text_field( (string) get_option( 'woocommerce_store_postcode', '' ) );

		return array(
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
	}

	/**
	 * Gets aggregated WooCommerce catalog term signals without sending product names.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return array<int,array<string,mixed>>
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

		$signals = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$signals[] = array(
				'name'  => sanitize_text_field( $term->name ),
				'slug'  => sanitize_title( $term->slug ),
				'count' => absint( $term->count ),
			);
		}

		return $signals;
	}

	/**
	 * Builds compact keyword signals from product categories and tags.
	 *
	 * @return array<int,string>
	 */
	private function catalog_signal_keywords(): array {
		$keywords = array();
		foreach ( array_merge( $this->catalog_signal_terms( 'product_cat' ), $this->catalog_signal_terms( 'product_tag' ) ) as $term ) {
			if ( ! empty( $term['slug'] ) ) {
				$keywords[] = sanitize_title( (string) $term['slug'] );
			}
		}

		return array_values( array_slice( array_unique( $keywords ), 0, 20 ) );
	}

	/**
	 * Checks whether the saved Pro bridge can sign plugin-to-SaaS requests.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return bool
	 */
	private function has_hmac_bridge( array $settings ): bool {
		return ! empty( $settings['pro_enabled'] )
			&& 'active' === (string) ( $settings['pro_license_status'] ?? '' )
			&& 'hmac_v1' === (string) ( $settings['bridge_auth_version'] ?? '' )
			&& ! empty( $settings['pro_site_id'] )
			&& ! empty( $settings['pro_key_id'] )
			&& ! empty( $settings['pro_plugin_to_saas_secret_encrypted'] )
			&& ! empty( $settings['pro_saas_url'] );
	}

	/**
	 * Sanitizes the SaaS URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function sanitize_saas_url( string $url ): string {
		$url = esc_url_raw( trim( $url ) );
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return '';
		}

		$parts = wp_parse_url( $url );
		$host  = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		if ( ! str_starts_with( strtolower( $url ), 'https://' ) && ! $this->is_allowed_local_saas_url( $host ) ) {
			return '';
		}

		return untrailingslashit( $url );
	}

	/**
	 * Checks whether an HTTP SaaS URL is acceptable for local development only.
	 *
	 * @param string $saas_host SaaS host.
	 * @return bool
	 */
	private function is_allowed_local_saas_url( string $saas_host ): bool {
		$saas_host       = strtolower( trim( $saas_host ) );
		$local_saas_hosts = array( '127.0.0.1', 'localhost', '::1' );
		if ( ! in_array( $saas_host, $local_saas_hosts, true ) ) {
			return false;
		}

		if ( WC_Competitor_Monitor_Bridge_Auth::dev_mode() ) {
			return true;
		}

		$site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		if ( in_array( $site_host, array( '127.0.0.1', 'localhost', '::1' ), true ) ) {
			return true;
		}

		return str_ends_with( $site_host, '.test' );
	}

	/**
	 * Builds a secret preview.
	 *
	 * @param string $secret Secret.
	 * @return string
	 */
	private function preview_secret( string $secret ): string {
		return strlen( $secret ) > 14 ? substr( $secret, 0, 10 ) . '...' . substr( $secret, -4 ) : '';
	}

	/**
	 * Returns the legacy API key only for explicit local development fallback.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	private function legacy_api_key_if_needed( array $settings ): string {
		if ( ! WC_Competitor_Monitor_Bridge_Auth::dev_mode() || ! empty( $settings['pro_plugin_to_saas_secret_encrypted'] ) ) {
			return '';
		}

		return sanitize_text_field( (string) ( $settings['pro_api_key'] ?? '' ) );
	}
}
