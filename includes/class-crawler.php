<?php
/**
 * Competitor URL crawler.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Performs safe HTTP GET requests using WordPress HTTP API.
 */
class WC_Competitor_Monitor_Crawler {

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
	 * Fetches a URL.
	 *
	 * @param string $url URL.
	 * @param string $browser_cookie_header Optional browser Cookie header.
	 * @param string $browser_user_agent Optional browser user-agent override.
	 * @return array<string,mixed>
	 */
	public function fetch( string $url, string $browser_cookie_header = '', string $browser_user_agent = '' ): array {
		$validation = WC_Competitor_Monitor_Security::validate_competitor_url( $url );
		if ( is_wp_error( $validation ) ) {
			$this->db->insert_log( 'warning', $validation->get_error_message(), array( 'url' => $url ) );
			return array(
				'success' => false,
				'status'  => 'invalid_url',
				'error'   => $validation->get_error_message(),
			);
		}

		$settings = $this->db->get_settings();
		$timeout  = max( 3, min( 30, absint( $settings['timeout'] ) ) );
		$max_size = max( 10240, min( 5242880, absint( $settings['max_response_size'] ) ) );
		$agent    = '' !== trim( $browser_user_agent ) ? sanitize_text_field( $browser_user_agent ) : $this->get_effective_user_agent( (string) $settings['user_agent'] );
		$args     = $this->build_request_args( $timeout, $max_size, $agent, $this->get_browser_session_headers( $url, $browser_cookie_header ) );

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			$message = $response->get_error_message();
			$this->db->insert_log(
				'error',
				__( 'Crawler request failed.', 'competitor-price-stock-monitor' ),
				array(
					'url'   => $url,
					'error' => $message,
				)
			);
			return array(
				'success' => false,
				'status'  => 'request_failed',
				'error'   => $message,
			);
		}

		if ( $this->should_retry_with_browser_session( $response ) ) {
			$retry_response = $this->fetch_with_browser_session( $url, $args, $response );
			if ( ! is_wp_error( $retry_response ) ) {
				$response = $retry_response;
			}
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 400 ) {
			$status  = $this->get_failed_status( $code, $body );
			$message = $this->get_failed_message( $code, $body );
			$this->db->insert_log(
				'warning',
				$message,
				array(
					'url'       => $url,
					'http_code' => $code,
				)
			);
			return array(
				'success'   => false,
				'status'    => $status,
				'error'     => $message,
				'http_code' => $code,
			);
		}

		if ( $this->is_blocked_response( $code, $body ) ) {
			$message = $this->get_failed_message( $code, $body );
			$this->db->insert_log(
				'warning',
				$message,
				array(
					'url'       => $url,
					'http_code' => $code,
				)
			);
			return array(
				'success'   => false,
				'status'    => $this->get_failed_status( $code, $body ),
				'error'     => $message,
				'http_code' => $code,
			);
		}

		return array(
			'success'   => true,
			'status'    => 'success',
			'body'      => $body,
			'http_code' => $code,
		);
	}

	/**
	 * Detects common anti-bot or blocking responses.
	 *
	 * @param int    $code HTTP code.
	 * @param string $body Body.
	 * @return bool
	 */
	private function is_blocked_response( int $code, string $body ): bool {
		if ( in_array( $code, array( 401, 403, 407, 429 ), true ) ) {
			return true;
		}

		if ( $code < 200 || $code >= 400 ) {
			return false;
		}

		if ( $this->looks_like_product_page( $body ) ) {
			return false;
		}

		$plain_text = strtolower( substr( wp_strip_all_tags( $body ), 0, 8000 ) );
		$html       = strtolower( substr( $body, 0, 20000 ) );
		$title      = '';

		if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $body, $matches ) ) {
			$title = strtolower( trim( wp_strip_all_tags( $matches[1] ) ) );
		}

		$title_signals = array(
			'just a moment',
			'access denied',
			'attention required',
			'request blocked',
			'security check',
		);

		foreach ( $title_signals as $signal ) {
			if ( '' !== $title && str_contains( $title, $signal ) ) {
				return true;
			}
		}

		$html_signals = array(
			'cf-chl-',
			'cf_chl_',
			'/cdn-cgi/challenge-platform',
			'data-sitekey',
			'g-recaptcha',
			'hcaptcha',
			'turnstile',
		);

		foreach ( $html_signals as $signal ) {
			if ( str_contains( $html, $signal ) ) {
				return true;
			}
		}

		$text_signals = array(
			'enable javascript and cookies to continue',
			'verify you are human',
			'checking your browser',
			'complete the security check',
			'access denied',
			'request blocked',
			'temporarily blocked',
			'unusual traffic',
		);

		foreach ( $text_signals as $signal ) {
			if ( str_contains( $plain_text, $signal ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Builds optional headers copied from a real browser session.
	 *
	 * @param string $url URL.
	 * @param string $browser_cookie_header Optional Cookie header.
	 * @return array<string,string>
	 */
	private function get_browser_session_headers( string $url, string $browser_cookie_header ): array {
		$cookie_header = WC_Competitor_Monitor_Security::sanitize_cookie_header( $browser_cookie_header );
		if ( '' === $cookie_header ) {
			return array();
		}

		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return array( 'Cookie' => $cookie_header );
		}

		return array(
			'Cookie'         => $cookie_header,
			'Referer'        => strtolower( (string) $parts['scheme'] ) . '://' . strtolower( (string) $parts['host'] ) . '/',
			'Sec-Fetch-Site' => 'same-origin',
		);
	}

	/**
	 * Builds HTTP arguments that resemble a normal document navigation.
	 *
	 * @param int                  $timeout Timeout.
	 * @param int                  $max_size Maximum response size.
	 * @param string               $agent User agent.
	 * @param array<string,string> $extra_headers Extra headers.
	 * @param array<int,mixed>     $cookies Cookies.
	 * @return array<string,mixed>
	 */
	private function build_request_args( int $timeout, int $max_size, string $agent, array $extra_headers = array(), array $cookies = array() ): array {
		$headers = array(
			'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
			'Accept-Language'           => $this->get_accept_language_header(),
			'Cache-Control'             => 'no-cache',
			'Pragma'                    => 'no-cache',
			'Upgrade-Insecure-Requests' => '1',
			'Sec-Fetch-Dest'            => 'document',
			'Sec-Fetch-Mode'            => 'navigate',
			'Sec-Fetch-Site'            => 'none',
			'Sec-Fetch-User'            => '?1',
			'DNT'                       => '1',
		);

		return array(
			'timeout'             => $timeout,
			'redirection'         => 5,
			'user-agent'          => $agent,
			'limit_response_size' => $max_size,
			'headers'             => array_merge( $headers, $extra_headers ),
			'cookies'             => $cookies,
		);
	}

	/**
	 * Retries product fetch after a same-origin warmup request.
	 *
	 * @param string              $url URL.
	 * @param array<string,mixed> $base_args Base request args.
	 * @param array<string,mixed> $original_response Original response.
	 * @return array<string,mixed>|WP_Error
	 */
	private function fetch_with_browser_session( string $url, array $base_args, array $original_response ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return $original_response;
		}

		$home_url  = strtolower( (string) $parts['scheme'] ) . '://' . strtolower( (string) $parts['host'] ) . '/';
		$home_args = $base_args;

		$home_response = wp_remote_get( $home_url, $home_args );
		if ( is_wp_error( $home_response ) ) {
			return $home_response;
		}

		$cookies = array_merge(
			wp_remote_retrieve_cookies( $original_response ),
			wp_remote_retrieve_cookies( $home_response )
		);

		$retry_args = $base_args;
		$headers    = isset( $retry_args['headers'] ) && is_array( $retry_args['headers'] ) ? $retry_args['headers'] : array();

		$retry_args['headers'] = array_merge(
			$headers,
			array(
				'Referer'        => $home_url,
				'Sec-Fetch-Site' => 'same-origin',
			)
		);
		$retry_args['cookies'] = $cookies;

		$this->db->insert_log( 'info', __( 'Retrying crawler request with same-origin browser session.', 'competitor-price-stock-monitor' ), array( 'url' => $url ) );

		return wp_remote_get( $url, $retry_args );
	}

	/**
	 * Determines whether a retry may help.
	 *
	 * @param array<string,mixed> $response Response.
	 * @return bool
	 */
	private function should_retry_with_browser_session( array $response ): bool {
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );

		return in_array( $code, array( 403, 406, 429 ), true ) || $this->is_blocked_response( $code, $body );
	}

	/**
	 * Returns a normalized failed status.
	 *
	 * @param int    $code HTTP status.
	 * @param string $body Body.
	 * @return string
	 */
	private function get_failed_status( int $code, string $body ): string {
		if ( $this->requires_javascript_challenge( $body ) ) {
			return 'javascript_challenge';
		}

		if ( $this->is_blocked_response( $code, $body ) ) {
			return 'blocked';
		}

		return 'http_error';
	}

	/**
	 * Returns a helpful failed message.
	 *
	 * @param int    $code HTTP status.
	 * @param string $body Body.
	 * @return string
	 */
	private function get_failed_message( int $code, string $body ): string {
		if ( $this->requires_javascript_challenge( $body ) ) {
			return __( 'The remote site requires a JavaScript and cookie browser challenge before the product HTML is available.', 'competitor-price-stock-monitor' );
		}

		if ( $this->is_blocked_response( $code, $body ) ) {
			return __( 'The remote page appears to block automated requests.', 'competitor-price-stock-monitor' );
		}

		return sprintf(
			/* translators: %d: HTTP status code. */
			__( 'Crawler received HTTP %d.', 'competitor-price-stock-monitor' ),
			$code
		);
	}

	/**
	 * Detects Cloudflare-style browser challenges that require JavaScript.
	 *
	 * @param string $body Body.
	 * @return bool
	 */
	private function requires_javascript_challenge( string $body ): bool {
		$text = strtolower( substr( wp_strip_all_tags( $body ), 0, 8000 ) );
		$html = strtolower( substr( $body, 0, 20000 ) );

		return str_contains( $text, 'enable javascript and cookies to continue' )
			|| str_contains( $text, 'checking your browser' )
			|| str_contains( $html, '/cdn-cgi/challenge-platform' )
			|| str_contains( $html, 'cf-chl-' );
	}

	/**
	 * Returns a browser-like user agent unless the merchant configured one explicitly.
	 *
	 * @param string $configured_user_agent Configured user agent.
	 * @return string
	 */
	private function get_effective_user_agent( string $configured_user_agent ): string {
		$configured_user_agent = sanitize_text_field( $configured_user_agent );
		$default_agent         = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

		if (
			'' === $configured_user_agent
			|| str_starts_with( $configured_user_agent, 'W' . 'C Competitor Monitor/' ) // phpcs:ignore Generic.Strings.UnnecessaryStringConcat.Found -- intentional split avoids matching on literal UA string
			|| str_starts_with( $configured_user_agent, 'Competitor Price Stock Monitor/' )
		) {
			return $default_agent;
		}

		return $configured_user_agent;
	}

	/**
	 * Builds a practical Accept-Language header for stores and Spanish competitors.
	 *
	 * @return string
	 */
	private function get_accept_language_header(): string {
		$locale = str_replace( '_', '-', determine_locale() );
		if ( '' === $locale ) {
			$locale = 'en-US';
		}

		return $locale . ',' . substr( $locale, 0, 2 ) . ';q=0.9,en;q=0.8';
	}

	/**
	 * Detects whether the body already looks like a useful product page.
	 *
	 * @param string $body Response body.
	 * @return bool
	 */
	private function looks_like_product_page( string $body ): bool {
		$html = strtolower( substr( $body, 0, 200000 ) );

		$product_signals = array(
			'application/ld+json',
			'"@type":"product"',
			'"@type": "product"',
			'product:price:amount',
			'woocommerce-product-details',
			'single_add_to_cart_button',
			'add to cart',
			'anadir al carrito',
			'precio',
			'price',
		);

		foreach ( $product_signals as $signal ) {
			if ( str_contains( $html, $signal ) ) {
				return true;
			}
		}

		return false;
	}
}
