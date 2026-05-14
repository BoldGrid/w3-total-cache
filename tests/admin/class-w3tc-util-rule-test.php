<?php
/**
 * File: class-w3tc-util-rule-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\Util_Rule;
use W3TC\Config;

/**
 * Class: W3tc_Util_Rule_Test
 *
 * Coverage for `Util_Rule::sanitize_directive_value()` and the
 * `Config::set()`-time directive-string reject path. The two
 * defences are paired: the renderer strip is the load-bearing
 * security control; the write-time reject keeps the bad bytes out
 * of `master.php` in the first place so subsequent reads from any
 * consumer return clean data.
 *
 * @since X.X.X
 */
class W3tc_Util_Rule_Test extends WP_UnitTestCase {

	/**
	 * Every character the directive sanitiser is supposed to strip
	 * actually drops out of the result, in every position.
	 *
	 * @since X.X.X
	 */
	public function test_sanitize_directive_value_strips_forbidden_bytes() {
		$payloads = array(
			'LF'                    => "ok\nForceType application/x-httpd-php",
			'CR'                    => "ok\rForceType application/x-httpd-php",
			'CRLF'                  => "ok\r\nSetHandler application/x-httpd-php",
			'NUL'                   => "ok\x00trailing",
			'embedded angle open'   => 'ok<Files "*">SetHandler',
			'embedded angle close'  => 'ok>Files',
			'multiple metachars'    => "a\nb\rc\x00d<e>f",
		);

		foreach ( $payloads as $label => $input ) {
			$out = Util_Rule::sanitize_directive_value( $input );
			$this->assertIsString( $out, $label );
			$this->assertSame(
				0,
				preg_match( '/[\r\n\x00<>]/', $out ),
				"[$label] still contains a forbidden byte: " . var_export( $out, true )
			);
		}
	}

	/**
	 * Legitimate directive content survives the sanitiser unchanged.
	 *
	 * @since X.X.X
	 */
	public function test_sanitize_directive_value_preserves_legitimate_input() {
		$cases = array(
			"default-src 'self'; script-src https://cdn.example.com",
			'no-referrer-when-downgrade',
			'1; mode=block',
			"camera=(), microphone=(), geolocation=(self)",
			"max-age=63072000; includeSubDomains; preload",
			'',  // empty stays empty
		);

		foreach ( $cases as $input ) {
			$this->assertSame( $input, Util_Rule::sanitize_directive_value( $input ) );
		}
	}

	/**
	 * Non-string inputs collapse to '' (never throw, never warn).
	 *
	 * @since X.X.X
	 */
	public function test_sanitize_directive_value_handles_non_strings() {
		$this->assertSame( '', Util_Rule::sanitize_directive_value( null ) );
		$this->assertSame( '', Util_Rule::sanitize_directive_value( 42 ) );
		$this->assertSame( '', Util_Rule::sanitize_directive_value( true ) );
		$this->assertSame( '', Util_Rule::sanitize_directive_value( array( 'x' ) ) );
		$this->assertSame( '', Util_Rule::sanitize_directive_value( new stdClass() ) );
	}

	/**
	 * `Config::set` strips CR/LF/NUL/<> from directive-flagged keys
	 * before storing. The write-time reject is paired with the
	 * renderer strip: even if a future contributor forgets to call
	 * the sanitiser at a new Header concat site, the stored value
	 * has already been cleaned.
	 *
	 * @since X.X.X
	 */
	public function test_config_set_strips_directive_string_keys() {
		$config = new Config();
		$config->set(
			'browsercache.security.csp.script',
			"'self' https://cdn.example.com\nForceType evil"
		);
		$out = $config->get_string( 'browsercache.security.csp.script' );
		$this->assertSame(
			"'self' https://cdn.example.com" . 'ForceType evil',
			$out,
			'Newline should be stripped at Config::set time.'
		);
		$this->assertSame(
			0,
			preg_match( '/[\r\n\x00<>]/', $out ),
			'No forbidden bytes should remain in the stored CSP value.'
		);
	}

	/**
	 * Non-directive-flagged keys (`pgcache.lifetime`, etc.) round-
	 * trip through `Config::set` unchanged. The write-time strip
	 * targets only the flagged subset.
	 *
	 * @since X.X.X
	 */
	public function test_config_set_leaves_non_directive_keys_untouched() {
		$config = new Config();
		// Pick an unflagged string-typed key; its value should survive
		// containing every "forbidden" byte in the directive set.
		$weird = "value with \n newline and <angle> brackets";
		$config->set( 'pgcache.bad_behavior_path', $weird );
		$this->assertSame( $weird, $config->get_string( 'pgcache.bad_behavior_path' ) );
	}
}
