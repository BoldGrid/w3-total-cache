<?php
/**
 * File: class-w3tc-header-injection-test.php
 *
 * Regressions for the ENG7-3035 response-header validation pass:
 *  - Config::set rejects out-of-enum writes for cdn.engine,
 *    retaining the previously stored value.
 *  - Util_Response::sanitize_header_value drops values containing
 *    CR, LF, or NUL.
 *  - Util_Response::header refuses to emit a header with an
 *    invalid name or a CRLF-bearing value.
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\Config;
use W3TC\Util_Response;

/**
 * Class: W3tc_Header_Injection_Test
 *
 * @since X.X.X
 */
class W3tc_Header_Injection_Test extends WP_UnitTestCase {

	/**
	 * Allowlisted slugs round-trip through Config::set untouched.
	 *
	 * @since X.X.X
	 */
	public function test_cdn_engine_enum_accepts_known_slugs() {
		$config = new Config();

		foreach ( array( '', 'bunnycdn', 's3', 'cloudfront', 'ftp', 'rackspace_cdn' ) as $engine ) {
			$config->set( 'cdn.engine', $engine );
			$this->assertSame( $engine, $config->get_string( 'cdn.engine' ), 'Failed for: ' . $engine );
		}
	}

	/**
	 * Out-of-enum writes retain the previously stored value and
	 * never reach the data array.
	 *
	 * @since X.X.X
	 */
	public function test_cdn_engine_enum_rejects_unknown_slug() {
		$config = new Config();

		$config->set( 'cdn.engine', 'bunnycdn' );
		$this->assertSame( 'bunnycdn', $config->get_string( 'cdn.engine' ) );

		// CRLF-bearing input that would have split the X-W3TC-CDN response header.
		$input = "bunnycdn\r\nSet-Cookie: pwned=1";
		$config->set( 'cdn.engine', $input );

		$this->assertSame( 'bunnycdn', $config->get_string( 'cdn.engine' ) );
		$this->assertStringNotContainsString( "\r", $config->get_string( 'cdn.engine' ) );
		$this->assertStringNotContainsString( "\n", $config->get_string( 'cdn.engine' ) );
	}

	/**
	 * A schema-less key is not enum-enforced (no regression for
	 * the 99% of config keys that don't declare an enum).
	 *
	 * @since X.X.X
	 */
	public function test_non_enum_keys_pass_through() {
		$config = new Config();

		$config->set( 'pgcache.enabled', true );
		$this->assertTrue( $config->get_boolean( 'pgcache.enabled' ) );

		$config->set( 'pgcache.engine', 'file_generic' );
		$this->assertSame( 'file_generic', $config->get_string( 'pgcache.engine' ) );
	}

	/**
	 * `sanitize_header_value` strips CR / LF / NUL by returning the
	 * empty string; otherwise it round-trips the input.
	 *
	 * @since X.X.X
	 */
	public function test_sanitize_header_value() {
		$this->assertSame( 'bunnycdn', Util_Response::sanitize_header_value( 'bunnycdn' ) );
		$this->assertSame( '', Util_Response::sanitize_header_value( "bunnycdn\r\nX-Y: z" ) );
		$this->assertSame( '', Util_Response::sanitize_header_value( "bunny\ncdn" ) );
		$this->assertSame( '', Util_Response::sanitize_header_value( "bunny\rcdn" ) );
		$this->assertSame( '', Util_Response::sanitize_header_value( "bunny\0cdn" ) );
		$this->assertSame( '', Util_Response::sanitize_header_value( null ) );
		$this->assertSame( '', Util_Response::sanitize_header_value( array( 'x' ) ) );
	}

	/**
	 * `header()` refuses values containing CR / LF / NUL, refuses
	 * empty names, and refuses names that fail the RFC 7230 token
	 * check.
	 *
	 * @since X.X.X
	 */
	public function test_header_rejects_invalid_inputs() {
		/**
		 * Cannot inspect emitted headers reliably under PHPUnit
		 * (headers_sent is true once the test runner has emitted
		 * anything), so we assert the return-value contract.
		 */

		$this->assertFalse( Util_Response::header( '', 'value' ) );
		$this->assertFalse( Util_Response::header( "X-Bad\r\nInjected", 'value' ) );
		$this->assertFalse( Util_Response::header( 'X-With Space', 'value' ) );
		$this->assertFalse( Util_Response::header( 'X-CDN', "bunnycdn\r\nSet-Cookie: x=1" ) );
		$this->assertFalse( Util_Response::header( 'X-CDN', "bunnycdn\0" ) );
		$this->assertFalse( Util_Response::header( 'X-CDN', null ) );
		$this->assertFalse( Util_Response::header( null, 'value' ) );
	}
}
