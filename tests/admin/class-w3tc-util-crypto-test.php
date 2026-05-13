<?php
/**
 * File: class-w3tc-util-crypto-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\Util_Crypto;

/**
 * Class: W3tc_Util_Crypto_Test
 *
 * Coverage for the at-rest credential encryption envelope. Regressions
 * here weaken the central protection for CDN credentials in master.php,
 * so the assertions are deliberately exhaustive: round-trip, tamper
 * detection, legacy passthrough, idempotency, fresh-IV-per-call,
 * envelope detection.
 *
 * @since X.X.X
 */
class W3tc_Util_Crypto_Test extends WP_UnitTestCase {

	/**
	 * Skip the suite when OpenSSL or AES-256-CBC is missing on the
	 * test host (no point asserting against a not-available helper).
	 */
	protected function setUp(): void {
		parent::setUp();
		if ( ! Util_Crypto::is_available() ) {
			$this->markTestSkipped( 'OpenSSL AES-256-CBC not available on this host.' );
		}
	}

	/**
	 * Plaintext → envelope → plaintext round-trips for every shape of
	 * value the config layer might pass through (short, long, UTF-8,
	 * credentials-with-special-chars).
	 *
	 * @since X.X.X
	 */
	public function test_round_trip_preserves_value() {
		$cases = array(
			'short ascii'         => 'abc',
			'aws-key-shaped'      => 'AKIA0123456789ABCDEF',
			'aws-secret-shaped'   => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
			'user:pass-ish'       => 'admin@example.com:hunter2!',
			'has spaces'          => 'pass with spaces and / slashes',
			'with quotes'         => "she said \"hello\"\nand 'goodbye'",
			'long string'         => str_repeat( 'X', 4096 ),
			'utf-8 unicode'       => '🔐 unicode 秘密 шифр',
			'single char'         => 'a',
		);

		foreach ( $cases as $label => $plaintext ) {
			$envelope = Util_Crypto::envelope_encrypt( $plaintext );
			$this->assertStringStartsWith( 'enc:v1:', $envelope, "Encryption did not produce envelope for [$label]." );

			$decrypted = Util_Crypto::envelope_decrypt( $envelope );
			$this->assertSame( $plaintext, $decrypted, "Round-trip lost data for [$label]." );
		}
	}

	/**
	 * Empty / non-string inputs pass through (the helper is a no-op so
	 * the config layer can hand it any value without special-casing).
	 *
	 * @since X.X.X
	 */
	public function test_empty_and_non_string_passthrough() {
		$this->assertSame( '', Util_Crypto::envelope_encrypt( '' ) );
		$this->assertSame( null, Util_Crypto::envelope_encrypt( null ) );
		$this->assertSame( '', Util_Crypto::envelope_decrypt( '' ) );
		$this->assertSame( null, Util_Crypto::envelope_decrypt( null ) );
	}

	/**
	 * A pre-fix install's plaintext value reads through `envelope_decrypt`
	 * unchanged so the upgrade is transparent — values stay readable
	 * until the next `save()` re-wraps them.
	 *
	 * @since X.X.X
	 */
	public function test_legacy_plaintext_passes_through_decrypt() {
		$legacy = 'AKIAIOSFODNN7EXAMPLE';
		$this->assertSame( $legacy, Util_Crypto::envelope_decrypt( $legacy ) );
	}

	/**
	 * Encrypting an already-enveloped value MUST NOT double-wrap. A
	 * double-wrap would mean the upgrade path encrypts an already-
	 * encrypted blob and then can't decrypt it back to the original.
	 *
	 * @since X.X.X
	 */
	public function test_no_double_wrap() {
		$plain = 'wJalrXUtnFEMI/K7MDENG';
		$once  = Util_Crypto::envelope_encrypt( $plain );
		$twice = Util_Crypto::envelope_encrypt( $once );

		$this->assertSame( $once, $twice );
	}

	/**
	 * Each encryption uses a fresh random IV, so the same plaintext
	 * produces a different envelope each time. Determinism here would
	 * be a serious crypto smell (CBC with reused IV leaks structure).
	 *
	 * @since X.X.X
	 */
	public function test_fresh_iv_per_call() {
		$a = Util_Crypto::envelope_encrypt( 'same' );
		$b = Util_Crypto::envelope_encrypt( 'same' );

		$this->assertNotSame( $a, $b );
		$this->assertSame( 'same', Util_Crypto::envelope_decrypt( $a ) );
		$this->assertSame( 'same', Util_Crypto::envelope_decrypt( $b ) );
	}

	/**
	 * A flipped byte anywhere in the body fails the HMAC check and
	 * decrypt returns `false`. The config layer collapses that to '' so
	 * the admin sees "credential needs re-entry" rather than the
	 * literal false.
	 *
	 * @since X.X.X
	 */
	public function test_tampered_envelope_returns_false() {
		$envelope = Util_Crypto::envelope_encrypt( 'legit-credential' );

		// Flip a byte in the body (just outside the prefix).
		$body              = substr( $envelope, strlen( 'enc:v1:' ) );
		$tampered_body     = $body;
		$tampered_body[10] = ( 'A' === $tampered_body[10] ? 'B' : 'A' );
		$tampered          = 'enc:v1:' . $tampered_body;

		$this->assertFalse( Util_Crypto::envelope_decrypt( $tampered ) );

		// Truncation also fails the HMAC.
		$truncated = 'enc:v1:' . substr( $body, 0, 30 );
		$this->assertFalse( Util_Crypto::envelope_decrypt( $truncated ) );
	}

	/**
	 * Malformed base64 in the body fails cleanly (no warning, no fatal).
	 *
	 * @since X.X.X
	 */
	public function test_malformed_base64_returns_false() {
		$this->assertFalse( Util_Crypto::envelope_decrypt( 'enc:v1:not!valid!base64!!!' ) );
	}

	/**
	 * `is_envelope()` recognises the prefix and rejects everything else.
	 *
	 * @since X.X.X
	 */
	public function test_is_envelope_recognises_prefix() {
		$this->assertTrue( Util_Crypto::is_envelope( 'enc:v1:abc' ) );
		$this->assertFalse( Util_Crypto::is_envelope( 'plain credential' ) );
		$this->assertFalse( Util_Crypto::is_envelope( '' ) );
		$this->assertFalse( Util_Crypto::is_envelope( null ) );
		$this->assertFalse( Util_Crypto::is_envelope( array() ) );
		$this->assertFalse( Util_Crypto::is_envelope( 'enc:v0:legacy' ) );
	}
}
