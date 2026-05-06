<?php
/**
 * PHPUnit bootstrap: define WordPress stubs so plugin classes can be loaded
 * without a full WordPress installation.
 */

// Prevent direct web access. PHPUnit runs as CLI so this guard is skipped there.
if ( 'cli' !== PHP_SAPI && ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/../' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'CPSM_DEV_MODE', false );

// WordPress function stubs -------------------------------------------------

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( string $url, int $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'wp_parse_str' ) ) {
	function wp_parse_str( string $string, array &$array ): void {
		parse_str( $string, $array );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $str ): string {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( string $str ): string {
		return $str;
	}
}

if ( ! function_exists( 'wp_get_environment_type' ) ) {
	function wp_get_environment_type(): string {
		return 'production';
	}
}

if ( ! function_exists( 'wp_salt' ) ) {
	// Fixed key for deterministic tests. Must be exactly 64+ chars.
	function wp_salt( string $scheme = 'auth' ): string {
		return str_repeat( 'test-salt-for-phpunit-', 4 );
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( string $key ) {
		return false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( string $key, mixed $value, int $expiration = 0 ): bool {
		return true;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability ): bool {
		return false;
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( string $message = '' ): never {
		throw new \RuntimeException( $message );
	}
}

if ( ! function_exists( 'wp_http_validate_url' ) ) {
	function wp_http_validate_url( string $url ): bool {
		return (bool) filter_var( $url, FILTER_VALIDATE_URL );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return htmlspecialchars( $text, ENT_QUOTES );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

// WP_Error stub -----------------------------------------------------------

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public string $code;
		public string $message;
		public array $data;

		public function __construct( string $code = '', string $message = '', mixed $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = is_array( $data ) ? $data : array( $data );
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}
	}
}

// Load the classes under test ----------------------------------------------

require_once __DIR__ . '/../includes/class-security.php';
require_once __DIR__ . '/../includes/class-bridge-auth.php';
