<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for WC_Competitor_Monitor_Bridge_Auth — HMAC signing and AES-256-GCM encryption.
 */
class BridgeAuthTest extends TestCase {

	private const SECRET  = 'test-bridge-secret-abc123';
	private const SITE_ID = 'site_test_01';
	private const KEY_ID  = 'key_test_01';
	private const URL     = 'https://saas.example.com/v1/plugin-sync/mappings';
	private const BODY    = '{"mappings":[{"product_id":1,"competitor_url":"https://competitor.com/product"}]}';

	// ── sign_headers ───────────────────────────────────────────────────────

	public function test_sign_headers_returns_all_required_headers(): void {
		$headers = WC_Competitor_Monitor_Bridge_Auth::sign_headers(
			'POST',
			self::URL,
			self::BODY,
			self::SITE_ID,
			self::KEY_ID,
			self::SECRET
		);

		$this->assertArrayHasKey( 'X-CPSM-Site-Id', $headers );
		$this->assertArrayHasKey( 'X-CPSM-Key-Id', $headers );
		$this->assertArrayHasKey( 'X-CPSM-Timestamp', $headers );
		$this->assertArrayHasKey( 'X-CPSM-Nonce', $headers );
		$this->assertArrayHasKey( 'X-CPSM-Content-SHA256', $headers );
		$this->assertArrayHasKey( 'X-CPSM-Signature', $headers );
	}

	public function test_sign_headers_embeds_site_and_key_id(): void {
		$headers = WC_Competitor_Monitor_Bridge_Auth::sign_headers(
			'POST',
			self::URL,
			self::BODY,
			self::SITE_ID,
			self::KEY_ID,
			self::SECRET
		);

		$this->assertSame( self::SITE_ID, $headers['X-CPSM-Site-Id'] );
		$this->assertSame( self::KEY_ID, $headers['X-CPSM-Key-Id'] );
	}

	public function test_sign_headers_body_hash_matches_sha256_of_body(): void {
		$headers = WC_Competitor_Monitor_Bridge_Auth::sign_headers(
			'POST',
			self::URL,
			self::BODY,
			self::SITE_ID,
			self::KEY_ID,
			self::SECRET
		);

		$this->assertSame( hash( 'sha256', self::BODY ), $headers['X-CPSM-Content-SHA256'] );
	}

	public function test_sign_headers_timestamp_is_recent(): void {
		$headers = WC_Competitor_Monitor_Bridge_Auth::sign_headers(
			'POST',
			self::URL,
			self::BODY,
			self::SITE_ID,
			self::KEY_ID,
			self::SECRET
		);

		$ts = (int) $headers['X-CPSM-Timestamp'];
		$this->assertEqualsWithDelta( time(), $ts, 5, 'Timestamp should be within 5 seconds of now' );
	}

	public function test_sign_headers_produces_different_nonces_each_call(): void {
		$h1 = WC_Competitor_Monitor_Bridge_Auth::sign_headers( 'POST', self::URL, self::BODY, self::SITE_ID, self::KEY_ID, self::SECRET );
		$h2 = WC_Competitor_Monitor_Bridge_Auth::sign_headers( 'POST', self::URL, self::BODY, self::SITE_ID, self::KEY_ID, self::SECRET );

		$this->assertNotSame( $h1['X-CPSM-Nonce'], $h2['X-CPSM-Nonce'] );
	}

	public function test_sign_headers_signature_changes_with_different_secret(): void {
		$h1 = WC_Competitor_Monitor_Bridge_Auth::sign_headers( 'POST', self::URL, self::BODY, self::SITE_ID, self::KEY_ID, 'secret-one' );
		$h2 = WC_Competitor_Monitor_Bridge_Auth::sign_headers( 'POST', self::URL, self::BODY, self::SITE_ID, self::KEY_ID, 'secret-two' );

		$this->assertNotSame( $h1['X-CPSM-Signature'], $h2['X-CPSM-Signature'] );
	}

	public function test_sign_headers_empty_body_produces_known_hash(): void {
		$headers = WC_Competitor_Monitor_Bridge_Auth::sign_headers(
			'GET',
			self::URL,
			'',
			self::SITE_ID,
			self::KEY_ID,
			self::SECRET
		);

		// SHA-256 of empty string is well-known.
		$this->assertSame(
			'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
			$headers['X-CPSM-Content-SHA256']
		);
	}

	// ── encrypt_secret / decrypt_secret ───────────────────────────────────

	public function test_encrypt_and_decrypt_round_trip(): void {
		$secret    = 'my-super-secret-value';
		$encrypted = WC_Competitor_Monitor_Bridge_Auth::encrypt_secret( $secret );

		$this->assertNotSame( $secret, $encrypted );
		$this->assertStringStartsWith( 'v1.', $encrypted );

		$decrypted = WC_Competitor_Monitor_Bridge_Auth::decrypt_secret( $encrypted );
		$this->assertSame( $secret, $decrypted );
	}

	public function test_encrypt_empty_secret_returns_empty(): void {
		$this->assertSame( '', WC_Competitor_Monitor_Bridge_Auth::encrypt_secret( '' ) );
	}

	public function test_decrypt_empty_stored_returns_empty(): void {
		$this->assertSame( '', WC_Competitor_Monitor_Bridge_Auth::decrypt_secret( '' ) );
	}

	public function test_decrypt_invalid_format_returns_empty(): void {
		$this->assertSame( '', WC_Competitor_Monitor_Bridge_Auth::decrypt_secret( 'not-a-valid-encrypted-value' ) );
	}

	public function test_each_encryption_produces_different_ciphertext(): void {
		$secret = 'same-secret';
		$enc1   = WC_Competitor_Monitor_Bridge_Auth::encrypt_secret( $secret );
		$enc2   = WC_Competitor_Monitor_Bridge_Auth::encrypt_secret( $secret );

		// IVs are random, so ciphertexts must differ.
		$this->assertNotSame( $enc1, $enc2 );

		// Both must still decrypt correctly.
		$this->assertSame( $secret, WC_Competitor_Monitor_Bridge_Auth::decrypt_secret( $enc1 ) );
		$this->assertSame( $secret, WC_Competitor_Monitor_Bridge_Auth::decrypt_secret( $enc2 ) );
	}

	public function test_tampered_ciphertext_returns_empty(): void {
		$encrypted = WC_Competitor_Monitor_Bridge_Auth::encrypt_secret( 'my-secret' );
		$tampered  = $encrypted . 'XXXINVALID';

		$this->assertSame( '', WC_Competitor_Monitor_Bridge_Auth::decrypt_secret( $tampered ) );
	}
}
