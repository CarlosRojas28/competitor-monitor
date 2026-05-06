<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for WC_Competitor_Monitor_Security — pure logic that runs without WordPress.
 */
class SecurityTest extends TestCase {

	// ── is_blocked_host ────────────────────────────────────────────────────

	public function test_localhost_is_blocked(): void {
		$this->assertTrue( WC_Competitor_Monitor_Security::is_blocked_host( 'localhost' ) );
	}

	public function test_loopback_ip_is_blocked(): void {
		$this->assertTrue( WC_Competitor_Monitor_Security::is_blocked_host( '127.0.0.1' ) );
	}

	public function test_ipv6_loopback_is_blocked(): void {
		$this->assertTrue( WC_Competitor_Monitor_Security::is_blocked_host( '::1' ) );
	}

	public function test_private_class_a_is_blocked(): void {
		$this->assertTrue( WC_Competitor_Monitor_Security::is_blocked_host( '10.0.0.1' ) );
	}

	public function test_private_class_b_is_blocked(): void {
		$this->assertTrue( WC_Competitor_Monitor_Security::is_blocked_host( '172.16.0.1' ) );
	}

	public function test_private_class_c_is_blocked(): void {
		$this->assertTrue( WC_Competitor_Monitor_Security::is_blocked_host( '192.168.1.1' ) );
	}

	public function test_empty_host_is_blocked(): void {
		$this->assertTrue( WC_Competitor_Monitor_Security::is_blocked_host( '' ) );
	}

	public function test_dot_local_is_blocked(): void {
		$this->assertTrue( WC_Competitor_Monitor_Security::is_blocked_host( 'mydevsite.local' ) );
	}

	public function test_dot_localhost_is_blocked(): void {
		$this->assertTrue( WC_Competitor_Monitor_Security::is_blocked_host( 'myapp.localhost' ) );
	}

	public function test_public_ip_is_not_blocked(): void {
		// 8.8.8.8 is a well-known public IP — not private, not reserved.
		$this->assertFalse( WC_Competitor_Monitor_Security::is_blocked_host( '8.8.8.8' ) );
	}

	// ── sanitize_currency ──────────────────────────────────────────────────

	public function test_valid_currency_code_passes(): void {
		$this->assertSame( 'EUR', WC_Competitor_Monitor_Security::sanitize_currency( 'eur' ) );
		$this->assertSame( 'USD', WC_Competitor_Monitor_Security::sanitize_currency( 'USD' ) );
		$this->assertSame( 'GBP', WC_Competitor_Monitor_Security::sanitize_currency( 'gbp' ) );
	}

	public function test_currency_strips_invalid_characters(): void {
		$result = WC_Competitor_Monitor_Security::sanitize_currency( 'US<script>D' );
		$this->assertSame( 'USD', $result );
	}

	public function test_currency_is_truncated_to_10_chars(): void {
		$result = WC_Competitor_Monitor_Security::sanitize_currency( 'ABCDEFGHIJK' );
		$this->assertSame( 10, strlen( $result ) );
	}

	public function test_empty_currency_returns_empty(): void {
		$this->assertSame( '', WC_Competitor_Monitor_Security::sanitize_currency( '' ) );
	}

	// ── redact_sensitive_context ───────────────────────────────────────────

	public function test_password_key_is_redacted(): void {
		$context = array( 'password' => 'mysecretpassword' );
		$result  = WC_Competitor_Monitor_Security::redact_sensitive_context( $context );
		$this->assertStringNotContainsString( 'mysecretpassword', $result['password'] );
		$this->assertStringContainsString( 'redacted', $result['password'] );
	}

	public function test_license_key_is_redacted(): void {
		$context = array( 'license_key' => 'lic_abc123def456' );
		$result  = WC_Competitor_Monitor_Security::redact_sensitive_context( $context );
		$this->assertStringContainsString( 'redacted', $result['license_key'] );
	}

	public function test_api_key_is_redacted(): void {
		$context = array( 'api_key' => 'sk_live_abcdefgh' );
		$result  = WC_Competitor_Monitor_Security::redact_sensitive_context( $context );
		$this->assertStringContainsString( 'redacted', $result['api_key'] );
	}

	public function test_non_sensitive_key_is_preserved(): void {
		$context = array( 'product_id' => 42, 'site_url' => 'https://example.com' );
		$result  = WC_Competitor_Monitor_Security::redact_sensitive_context( $context );
		$this->assertSame( 42, $result['product_id'] );
		$this->assertSame( 'https://example.com', $result['site_url'] );
	}

	public function test_nested_context_is_redacted(): void {
		$context = array(
			'request' => array(
				'headers' => array( 'authorization' => 'Bearer super-secret-token' ),
				'method'  => 'POST',
			),
		);
		$result  = WC_Competitor_Monitor_Security::redact_sensitive_context( $context );
		$this->assertStringContainsString( 'redacted', $result['request']['headers']['authorization'] );
		$this->assertSame( 'POST', $result['request']['method'] );
	}

	public function test_short_secret_is_fully_redacted(): void {
		$context = array( 'token' => 'abc' );
		$result  = WC_Competitor_Monitor_Security::redact_sensitive_context( $context );
		$this->assertSame( '[redacted]', $result['token'] );
	}
}
