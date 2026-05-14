<?php
/**
 * File: class-w3tc-xss-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\CacheGroups_Plugin_Admin;
use W3TC\Config;

/**
 * Class: W3tc_Xss_Test
 *
 * Coverage for the XSS hardening pass:
 *  - `CacheGroups_Plugin_Admin::clean_values()` strips HTML tags at the
 *    write boundary (stored-XSS leg of CHAIN-010).
 *  - `Config::export()` hex-escapes HTML-significant characters so a
 *    JSON config blob can never carry a literal `<script>`.
 *
 * @since X.X.X
 */
class W3tc_Xss_Test extends WP_UnitTestCase {

	/**
	 * The cachegroups User-Agent / referrer sanitiser strips a
	 * `<script>` payload at write time. Pins the stored-XSS leg of
	 * CHAIN-010 — even if a future view drops the `esc_textarea` on
	 * output, the stored value can never be a script tag.
	 *
	 * @since X.X.X
	 */
	public function test_clean_values_strips_script_payloads() {
		$cleaned = CacheGroups_Plugin_Admin::clean_values(
			array(
				'<script>alert(1)</script>',
				'<img src=x onerror=alert(1)>',
				'normal-user-agent',
				'mobile<svg/onload=alert(1)>',
			)
		);
		foreach ( $cleaned as $value ) {
			$this->assertSame(
				0,
				preg_match( '/<[a-z]/i', (string) $value ),
				"Stored value still contains an HTML tag: " . var_export( $value, true )
			);
		}
	}

	/**
	 * Legitimate UA strings round-trip unchanged through the cleaner
	 * (modulo the existing lowercase + space-escape transforms).
	 *
	 * @since X.X.X
	 */
	public function test_clean_values_preserves_legitimate_user_agents() {
		$cleaned = CacheGroups_Plugin_Admin::clean_values(
			array(
				'mozilla',
				'iphone',
				'android',
			)
		);
		$this->assertContains( 'mozilla', $cleaned );
		$this->assertContains( 'iphone', $cleaned );
		$this->assertContains( 'android', $cleaned );
	}

	/**
	 * `Config::export()` encodes `<` and `&` as their `\uXXXX` escape
	 * sequences. Even with `Content-Type: application/json` the export
	 * endpoint can be coerced into rendering as HTML in edge cases
	 * (browser history walks, view-source, intermediary proxies); the
	 * hex escape ensures no literal `<script>` can ever appear in the
	 * response body regardless of what an admin saved in config.
	 *
	 * @since X.X.X
	 */
	public function test_config_export_hex_escapes_html_significant_chars() {
		$config = new Config();
		$config->set( 'pgcache.bad_behavior_path', '<script>alert(1)</script>' );

		$json = $config->export();
		$this->assertIsString( $json );

		// No literal `<` in the response body.
		$this->assertStringNotContainsString( '<', $json );
		// `<` MUST be present as its hex-encoded form. PHP's JSON encoder
		// emits `<` (uppercase hex) — strcasecmp handles both.
		$this->assertStringContainsStringIgnoringCase( '\\u003c', $json );

		// `&` and `'` are likewise hex-encoded.
		$config->set( 'pgcache.bad_behavior_path', "tag\\'attr&value" );
		$json = $config->export();
		$this->assertStringNotContainsString( '&', $json );

		// JSON parser MUST still round-trip the value transparently.
		$decoded = json_decode( $json, true );
		$this->assertIsArray( $decoded );
		$this->assertSame( "tag\\'attr&value", $decoded['pgcache.bad_behavior_path'] );
	}
}
