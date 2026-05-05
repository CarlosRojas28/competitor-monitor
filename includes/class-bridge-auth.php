<?php
/**
 * HMAC bridge authentication helpers.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Signs and verifies Pro bridge requests.
 */
class WC_Competitor_Monitor_Bridge_Auth {

	/**
	 * Returns whether local development shortcuts are explicitly allowed.
	 *
	 * @return bool
	 */
	public static function dev_mode(): bool {
		$env = getenv( 'CPSM_DEV_MODE' );
		return ( defined( 'CPSM_DEV_MODE' ) && CPSM_DEV_MODE )
			|| in_array( strtolower( (string) $env ), array( '1', 'true', 'yes', 'on' ), true )
			|| 'local' === wp_get_environment_type();
	}

	/**
	 * Encrypts a secret using WordPress salts.
	 *
	 * @param string $secret Secret.
	 * @return string
	 */
	public static function encrypt_secret( string $secret ): string {
		if ( '' === $secret ) {
			return '';
		}

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return self::dev_mode() ? $secret : '';
		}

		$iv  = random_bytes( 12 );
		$tag = '';
		$key = self::encryption_key();
		$encrypted = openssl_encrypt( $secret, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

		if ( false === $encrypted ) {
			return self::dev_mode() ? $secret : '';
		}

		return 'v1.' . self::base64url_encode( $iv ) . '.' . self::base64url_encode( $tag ) . '.' . self::base64url_encode( $encrypted );
	}

	/**
	 * Decrypts a stored bridge secret.
	 *
	 * @param string $stored Stored value.
	 * @return string
	 */
	public static function decrypt_secret( string $stored ): string {
		if ( '' === $stored ) {
			return '';
		}

		$parts = explode( '.', $stored );
		if ( 4 !== count( $parts ) || 'v1' !== $parts[0] ) {
			return self::dev_mode() ? $stored : '';
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}

		$plain = openssl_decrypt(
			self::base64url_decode( $parts[3] ),
			'aes-256-gcm',
			self::encryption_key(),
			OPENSSL_RAW_DATA,
			self::base64url_decode( $parts[1] ),
			self::base64url_decode( $parts[2] )
		);

		return is_string( $plain ) ? $plain : '';
	}

	/**
	 * Builds signed headers for an outbound request.
	 *
	 * @param string $method HTTP method.
	 * @param string $url Full URL.
	 * @param string $body Raw body.
	 * @param string $site_id Site ID.
	 * @param string $key_id Key ID.
	 * @param string $secret HMAC secret.
	 * @return array<string,string>
	 */
	public static function sign_headers( string $method, string $url, string $body, string $site_id, string $key_id, string $secret ): array {
		$timestamp = (string) time();
		$nonce     = self::base64url_encode( random_bytes( 16 ) );
		$payload   = self::canonical_payload( $method, $url, $timestamp, $nonce, $body );

		return array(
			'X-CPSM-Site-Id'        => $site_id,
			'X-CPSM-Key-Id'         => $key_id,
			'X-CPSM-Timestamp'      => $timestamp,
			'X-CPSM-Nonce'          => $nonce,
			'X-CPSM-Content-SHA256' => hash( 'sha256', $body ),
			'X-CPSM-Signature'      => self::base64url_encode( hash_hmac( 'sha256', $payload, $secret, true ) ),
		);
	}

	/**
	 * Verifies a signed inbound REST request from the SaaS.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @param array<string,mixed> $settings Plugin settings.
	 * @return true|WP_Error
	 */
	public static function verify_rest_request( WP_REST_Request $request, array $settings ) {
		$site_id   = sanitize_text_field( (string) $request->get_header( 'x-cpsm-site-id' ) );
		$key_id    = sanitize_text_field( (string) $request->get_header( 'x-cpsm-key-id' ) );
		$timestamp = sanitize_text_field( (string) $request->get_header( 'x-cpsm-timestamp' ) );
		$nonce     = sanitize_text_field( (string) $request->get_header( 'x-cpsm-nonce' ) );
		$body_hash = sanitize_text_field( (string) $request->get_header( 'x-cpsm-content-sha256' ) );
		$signature = sanitize_text_field( (string) $request->get_header( 'x-cpsm-signature' ) );

		if ( '' === $site_id || '' === $key_id || '' === $timestamp || '' === $nonce || '' === $body_hash || '' === $signature ) {
			return new WP_Error( 'cpsm_missing_bridge_signature', __( 'Missing Pro bridge signature headers.', 'competitor-price-stock-monitor' ), array( 'status' => 401 ) );
		}

		if ( ! hash_equals( (string) ( $settings['pro_site_id'] ?? '' ), $site_id ) || ! hash_equals( (string) ( $settings['pro_key_id'] ?? '' ), $key_id ) ) {
			return new WP_Error( 'cpsm_bridge_site_mismatch', __( 'Pro bridge site or key does not match this store.', 'competitor-price-stock-monitor' ), array( 'status' => 401 ) );
		}

		$timestamp_int = absint( $timestamp );
		if ( 0 === $timestamp_int || abs( time() - $timestamp_int ) > 300 ) {
			return new WP_Error( 'cpsm_stale_bridge_signature', __( 'Pro bridge signature has expired.', 'competitor-price-stock-monitor' ), array( 'status' => 401 ) );
		}

		if ( ! self::remember_nonce( $site_id, $key_id, $nonce ) ) {
			return new WP_Error( 'cpsm_replayed_bridge_signature', __( 'Pro bridge nonce has already been used.', 'competitor-price-stock-monitor' ), array( 'status' => 401 ) );
		}

		$body = (string) $request->get_body();
		if ( ! hash_equals( hash( 'sha256', $body ), $body_hash ) ) {
			return new WP_Error( 'cpsm_bridge_body_mismatch', __( 'Pro bridge body hash does not match.', 'competitor-price-stock-monitor' ), array( 'status' => 401 ) );
		}

		$secret = self::decrypt_secret( (string) ( $settings['pro_saas_to_plugin_secret_encrypted'] ?? '' ) );
		if ( '' === $secret ) {
			return new WP_Error( 'cpsm_bridge_secret_missing', __( 'Pro bridge secret is not configured.', 'competitor-price-stock-monitor' ), array( 'status' => 401 ) );
		}

		$url      = rest_url( $request->get_route() );
		$query    = $request->get_query_params();
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}
		$payload  = self::canonical_payload( $request->get_method(), $url, $timestamp, $nonce, $body );
		$expected = self::base64url_encode( hash_hmac( 'sha256', $payload, $secret, true ) );

		if ( ! hash_equals( $expected, $signature ) ) {
			return new WP_Error( 'cpsm_invalid_bridge_signature', __( 'Invalid Pro bridge signature.', 'competitor-price-stock-monitor' ), array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * Canonical payload shared with the SaaS.
	 *
	 * @param string $method HTTP method.
	 * @param string $url URL.
	 * @param string $timestamp Timestamp.
	 * @param string $nonce Nonce.
	 * @param string $body Body.
	 * @return string
	 */
	private static function canonical_payload( string $method, string $url, string $timestamp, string $nonce, string $body ): string {
		$parts = wp_parse_url( $url );
		$path  = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		$query = self::canonical_query( isset( $parts['query'] ) ? (string) $parts['query'] : '' );

		return implode(
			"\n",
			array(
				strtoupper( $method ),
				$path,
				$query,
				$timestamp,
				$nonce,
				hash( 'sha256', $body ),
			)
		);
	}

	/**
	 * Sorts a query string canonically.
	 *
	 * @param string $query Query string.
	 * @return string
	 */
	private static function canonical_query( string $query ): string {
		if ( '' === $query ) {
			return '';
		}

		$params = array();
		wp_parse_str( $query, $params );
		ksort( $params, SORT_STRING );

		$pairs = array();
		foreach ( $params as $key => $value ) {
			if ( is_array( $value ) ) {
				sort( $value, SORT_STRING );
				foreach ( $value as $item ) {
					$pairs[] = rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $item );
				}
				continue;
			}
			$pairs[] = rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value );
		}

		return implode( '&', $pairs );
	}

	/**
	 * Stores a nonce transient to prevent replay.
	 *
	 * @param string $site_id Site ID.
	 * @param string $key_id Key ID.
	 * @param string $nonce Nonce.
	 * @return bool
	 */
	private static function remember_nonce( string $site_id, string $key_id, string $nonce ): bool {
		$key = 'cpsm_bridge_nonce_' . md5( $site_id . '|' . $key_id . '|' . $nonce );
		if ( get_transient( $key ) ) {
			return false;
		}
		set_transient( $key, 1, 5 * MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Gets encryption key.
	 *
	 * @return string
	 */
	private static function encryption_key(): string {
		return hash( 'sha256', wp_salt( 'auth' ) . wp_salt( 'secure_auth' ), true );
	}

	/**
	 * Encodes base64url.
	 *
	 * @param string $value Raw bytes.
	 * @return string
	 */
	private static function base64url_encode( string $value ): string {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}

	/**
	 * Decodes base64url.
	 *
	 * @param string $value Encoded.
	 * @return string
	 */
	private static function base64url_decode( string $value ): string {
		return (string) base64_decode( strtr( $value, '-_', '+/' ) );
	}
}

