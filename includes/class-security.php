<?php
/**
 * Security helpers.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralized security and validation helpers.
 */
class WC_Competitor_Monitor_Security {

	/**
	 * Checks whether the current user can manage this plugin.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage(): bool {
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Stops execution when the current user cannot manage the plugin.
	 *
	 * @return void
	 */
	public static function require_capability(): void {
		if ( ! self::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage competitor monitoring.', 'competitor-price-stock-monitor' ) );
		}
	}

	/**
	 * Validates a URL and applies basic SSRF protections.
	 *
	 * @param string $url URL to validate.
	 * @return true|WP_Error
	 */
	public static function validate_competitor_url( string $url ) {
		$url = trim( $url );

		if ( '' === $url ) {
			return new WP_Error( 'empty_url', __( 'The competitor URL is required.', 'competitor-price-stock-monitor' ) );
		}

		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return new WP_Error( 'invalid_url', __( 'The competitor URL must include a valid host.', 'competitor-price-stock-monitor' ) );
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return new WP_Error( 'invalid_scheme', __( 'Only HTTP and HTTPS competitor URLs are allowed.', 'competitor-price-stock-monitor' ) );
		}

		$host = self::normalize_host( (string) $parts['host'] );
		if ( self::is_blocked_host( $host ) ) {
			return new WP_Error( 'blocked_host', __( 'Local, private, and reserved hosts cannot be monitored.', 'competitor-price-stock-monitor' ) );
		}

		if ( function_exists( 'wp_http_validate_url' ) && ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'invalid_http_url', __( 'WordPress rejected this URL as unsafe.', 'competitor-price-stock-monitor' ) );
		}

		return true;
	}

	/**
	 * Checks obvious blocked hostnames and IP ranges.
	 *
	 * @param string $host Hostname or IP address.
	 * @return bool
	 */
	public static function is_blocked_host( string $host ): bool {
		$host = self::normalize_host( $host );

		if ( '' === $host ) {
			return true;
		}

		$blocked_hosts = array(
			'localhost',
			'localhost.localdomain',
			'0.0.0.0',
			'127.0.0.1',
			'::1',
		);

		if ( in_array( $host, $blocked_hosts, true ) || str_ends_with( $host, '.localhost' ) || str_ends_with( $host, '.local' ) ) {
			return true;
		}

		$normalized_ip = self::normalize_ip_literal( $host );
		if ( null !== $normalized_ip && self::is_private_or_reserved_ip( $normalized_ip ) ) {
			return true;
		}

		$resolved_ips = self::resolve_host_ips( $host );
		foreach ( $resolved_ips as $ip ) {
			if ( self::is_private_or_reserved_ip( $ip ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Sanitizes a short CSS selector.
	 *
	 * @param string $selector Selector.
	 * @return string
	 */
	public static function sanitize_selector( string $selector ): string {
		$selector = sanitize_text_field( $selector );
		return substr( $selector, 0, 255 );
	}

	/**
	 * Sanitizes a currency code or symbol.
	 *
	 * @param string $currency Currency.
	 * @return string
	 */
	public static function sanitize_currency( string $currency ): string {
		$currency = strtoupper( sanitize_text_field( $currency ) );
		$currency = preg_replace( '/[^A-Z0-9\x{20AC}\x{00A3}$]/u', '', $currency );
		return substr( (string) $currency, 0, 10 );
	}

	/**
	 * Sanitizes a browser Cookie header supplied by an administrator.
	 *
	 * @param string $cookie_header Raw Cookie header value.
	 * @return string
	 */
	public static function sanitize_cookie_header( string $cookie_header ): string {
		$cookie_header = sanitize_textarea_field( $cookie_header );
		$cookie_header = str_replace( array( "\r", "\n" ), '', $cookie_header );
		$cookie_header = preg_replace( '/[^\x20-\x7E]/', '', $cookie_header );
		$cookie_header = trim( (string) $cookie_header );

		return substr( $cookie_header, 0, 4096 );
	}

	/**
	 * Redacts sensitive values before logging.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	public static function redact_sensitive_context( mixed $value ): mixed {
		if ( is_array( $value ) ) {
			$redacted = array();
			foreach ( $value as $key => $item ) {
				if ( preg_match( '/authorization|cookie|x-api-key|api[_-]?key|license[_-]?key|secret|token|password/i', (string) $key ) ) {
					$redacted[ $key ] = self::redact_secret_value( $item );
					continue;
				}
				$redacted[ $key ] = self::redact_sensitive_context( $item );
			}
			return $redacted;
		}

		return $value;
	}

	/**
	 * Normalizes a host string.
	 *
	 * @param string $host Host.
	 * @return string
	 */
	private static function normalize_host( string $host ): string {
		$host = strtolower( trim( $host, " \t\n\r\0\x0B[]" ) );
		$host = rtrim( $host, '.' );

		if ( function_exists( 'idn_to_ascii' ) && preg_match( '/[^\x20-\x7f]/', $host ) ) {
			$ascii = idn_to_ascii( $host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 );
			if ( is_string( $ascii ) && '' !== $ascii ) {
				$host = strtolower( $ascii );
			}
		}

		return $host;
	}

	/**
	 * Redacts one secret-ish value.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function redact_secret_value( mixed $value ): string {
		$text = (string) $value;
		if ( strlen( $text ) <= 8 ) {
			return '[redacted]';
		}
		return substr( $text, 0, 4 ) . '...[redacted]...' . substr( $text, -4 );
	}

	/**
	 * Detects private, loopback, and reserved IP addresses.
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	private static function is_private_or_reserved_ip( string $ip ): bool {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		return ! filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);
	}

	/**
	 * Normalizes decimal integer and IPv4 literals where possible.
	 *
	 * @param string $host Host.
	 * @return string|null
	 */
	private static function normalize_ip_literal( string $host ): ?string {
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return $host;
		}

		if ( ctype_digit( $host ) ) {
			$number = (int) $host;
			if ( $number >= 0 && $number <= 4294967295 ) {
				return long2ip( $number );
			}
		}

		if ( preg_match( '/^0x[0-9a-f]+$/i', $host ) ) {
			$number = hexdec( substr( $host, 2 ) );
			if ( $number >= 0 && $number <= 4294967295 ) {
				return long2ip( (int) $number );
			}
		}

		return null;
	}

	/**
	 * Resolves DNS A/AAAA records when possible.
	 *
	 * @param string $host Hostname.
	 * @return array<int,string>
	 */
	private static function resolve_host_ips( string $host ): array {
		$literal_ip = self::normalize_ip_literal( $host );
		if ( null !== $literal_ip ) {
			return array( $literal_ip );
		}

		$ips = array();

		if ( function_exists( 'dns_get_record' ) ) {
			$records = @dns_get_record( $host, DNS_A + DNS_AAAA );
			if ( is_array( $records ) ) {
				foreach ( $records as $record ) {
					if ( ! empty( $record['ip'] ) ) {
						$ips[] = (string) $record['ip'];
					}
					if ( ! empty( $record['ipv6'] ) ) {
						$ips[] = (string) $record['ipv6'];
					}
				}
			}
		}

		if ( empty( $ips ) ) {
			$resolved = gethostbyname( $host );
			if ( $resolved && $resolved !== $host ) {
				$ips[] = $resolved;
			}
		}

		return array_values( array_unique( $ips ) );
	}
}
