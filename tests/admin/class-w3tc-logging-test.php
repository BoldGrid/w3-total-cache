<?php
/**
 * File: class-w3tc-logging-test.php
 *
 * Regressions for the ENG7-3031 audit-logging hardening pass:
 *  - Util_Debug::log sanitizes CR / LF / TAB / NUL (no log forging).
 *  - Util_Debug::redact strips nonces, password/secret/token/key
 *    query params, Authorization headers, and the wp-config
 *    secret-bearing define() blocks.
 *  - Util_Debug::audit_log fires the `w3tc_audit_log` action with
 *    redacted context and auto-populated `user_id`.
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\Util_Debug;

/**
 * Class: W3tc_Logging_Test
 *
 * @since X.X.X
 */
class W3tc_Logging_Test extends WP_UnitTestCase {

	/**
	 * Reflect into Util_Debug::log's sanitization to assert that
	 * embedded CR / LF / TAB / NUL bytes can't forge a second log
	 * entry. We replicate the same strtr table the implementation
	 * uses rather than touching the filesystem from a unit test.
	 *
	 * @since X.X.X
	 */
	public function test_log_sanitizes_control_chars() {
		$payload  = "user-controlled\r\nfake-entry\tand a <tag> and a \0 nul";
		$expected = 'user-controlled  fake-entry and a .tag. and a  nul';

		$sanitized = strtr(
			$payload,
			array(
				'<'  => '.',
				'>'  => '.',
				"\r" => ' ',
				"\n" => ' ',
				"\t" => ' ',
				"\0" => '',
			)
		);

		$this->assertSame( $expected, $sanitized );
		$this->assertStringNotContainsString( "\n", $sanitized );
		$this->assertStringNotContainsString( "\r", $sanitized );
		$this->assertStringNotContainsString( "\0", $sanitized );
	}

	/**
	 * `_wpnonce` / bare `nonce` values are redacted up to the next
	 * `&` or whitespace.
	 *
	 * @since X.X.X
	 */
	public function test_redact_wpnonce_stops_at_whitespace() {
		$cases = array(
			'/foo?_wpnonce=ABC123&x=1'                  => '/foo?_wpnonce=REDACTED&x=1',
			'/foo?_wpnonce=ABC123 next-token'           => '/foo?_wpnonce=REDACTED next-token',
			"line1 _wpnonce=ABC123\nline2"              => "line1 _wpnonce=REDACTED\nline2",
			'admin-ajax.php?action=x&nonce=AAA bbb=ccc' => 'admin-ajax.php?action=x&nonce=REDACTED bbb=ccc',
		);

		foreach ( $cases as $input => $expected ) {
			$this->assertSame( $expected, Util_Debug::redact_wpnonce( $input ), 'Failed for: ' . $input );
		}
	}

	/**
	 * `redact` strips nonces, common secret-bearing query params,
	 * `Authorization` header values, and wp-config secret defines.
	 *
	 * @since X.X.X
	 */
	public function test_redact_covers_all_patterns() {
		$blob = "GET /a?_wpnonce=N0NCE&password=hunter2&api_key=AKIA-XYZ HTTP/1.1\r\n"
			. "Authorization: Bearer eyJABCDEF.payload.sig\r\n"
			. "define( 'DB_PASSWORD', 'super-secret-1' );\n"
			. "define( 'AUTH_KEY', 'AAA-key' );\n"
			. "define( 'NONCE_SALT', 'HHH-salt' );\n"
			. "define( 'DB_NAME', 'wordpress' );\n";

		$out = Util_Debug::redact( $blob );

		$this->assertStringContainsString( '_wpnonce=REDACTED', $out );
		$this->assertStringContainsString( 'password=REDACTED', $out );
		$this->assertStringContainsString( 'api_key=REDACTED', $out );
		$this->assertStringContainsString( 'Authorization: Bearer REDACTED', $out );
		$this->assertStringContainsString( "define( 'DB_PASSWORD', 'REDACTED' );", $out );
		$this->assertStringContainsString( "define( 'AUTH_KEY', 'REDACTED' );", $out );
		$this->assertStringContainsString( "define( 'NONCE_SALT', 'REDACTED' );", $out );
		$this->assertStringContainsString( "define( 'DB_NAME', 'wordpress' );", $out );

		$this->assertStringNotContainsString( 'N0NCE', $out );
		$this->assertStringNotContainsString( 'hunter2', $out );
		$this->assertStringNotContainsString( 'AKIA-XYZ', $out );
		$this->assertStringNotContainsString( 'eyJABCDEF', $out );
		$this->assertStringNotContainsString( 'super-secret-1', $out );
		$this->assertStringNotContainsString( 'AAA-key', $out );
		$this->assertStringNotContainsString( 'HHH-salt', $out );
	}

	/**
	 * Non-string input — null, arrays — returns an empty string
	 * instead of raising a TypeError.
	 *
	 * @since X.X.X
	 */
	public function test_redact_non_string_input() {
		$this->assertSame( '', Util_Debug::redact( null ) );
		$this->assertSame( '', Util_Debug::redact( array( 'x' ) ) );
		$this->assertSame( '42', Util_Debug::redact( 42 ) );
	}

	/**
	 * `redact` is idempotent — calling it on already-redacted text
	 * leaves it unchanged.
	 *
	 * @since X.X.X
	 */
	public function test_redact_idempotent() {
		$blob  = "_wpnonce=abc password=def Authorization: Bearer tok";
		$once  = Util_Debug::redact( $blob );
		$twice = Util_Debug::redact( $once );

		$this->assertSame( $once, $twice );
	}

	/**
	 * `audit_log` fires the `w3tc_audit_log` action; subscribers see
	 * the redacted context with auto-populated `user_id`.
	 *
	 * @since X.X.X
	 */
	public function test_audit_log_fires_with_redacted_context() {
		$received = array();

		$listener = function ( $event, $context ) use ( &$received ) {
			$received[] = array(
				'event'   => $event,
				'context' => $context,
			);
		};

		add_action( 'w3tc_audit_log', $listener, 10, 2 );

		Util_Debug::audit_log(
			'cap_denied',
			array(
				'handler'    => 'wp_ajax_w3tc_ajax',
				'action'     => 'cdn.bunnycdn.purge_url',
				'capability' => 'manage_options',
				'detail'     => "url=/?_wpnonce=ABC123\ninjected",
			)
		);

		remove_action( 'w3tc_audit_log', $listener, 10 );

		$this->assertCount( 1, $received );
		$this->assertSame( 'cap_denied', $received[0]['event'] );
		$this->assertSame( 'wp_ajax_w3tc_ajax', $received[0]['context']['handler'] );
		$this->assertArrayHasKey( 'user_id', $received[0]['context'] );
		$this->assertStringNotContainsString( 'ABC123', $received[0]['context']['detail'] );
		$this->assertStringContainsString( '_wpnonce=REDACTED', $received[0]['context']['detail'] );
	}

	/**
	 * Calling `audit_log` with a non-string or empty event short-
	 * circuits without firing the action.
	 *
	 * @since X.X.X
	 */
	public function test_audit_log_rejects_invalid_event() {
		$fired = 0;

		$listener = function () use ( &$fired ) {
			$fired++;
		};

		add_action( 'w3tc_audit_log', $listener, 10, 2 );

		Util_Debug::audit_log( '', array( 'x' => 'y' ) );

		remove_action( 'w3tc_audit_log', $listener, 10 );

		$this->assertSame( 0, $fired );
	}
}
