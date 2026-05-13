<?php
/**
 * File: class-w3tc-util-extension-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\Util_Extension;

/**
 * Class: W3tc_Util_Extension_Test
 *
 * Coverage for the slug-allowlist extension loader (Layer 1 of the
 * file-inclusion hardening). Regressions in `resolve()` re-open the
 * `extensions.active` → `include_once` RCE primitive; regressions in
 * `convert_legacy_entries()` let an attacker who lands a known slug
 * smuggle a malicious path past the read-side normalisation.
 *
 * @since X.X.X
 */
class W3tc_Util_Extension_Test extends WP_UnitTestCase {

	/**
	 * `known_extensions()` returns a non-empty slug map.
	 *
	 * @since X.X.X
	 */
	public function test_known_extensions_is_populated() {
		$known = Util_Extension::known_extensions();
		$this->assertIsArray( $known );
		$this->assertNotEmpty( $known );
		// A few sentinel slugs from the curated set.
		$this->assertArrayHasKey( 'newrelic', $known );
		$this->assertArrayHasKey( 'cloudflare', $known );
		$this->assertArrayHasKey( 'alwayscached', $known );
	}

	/**
	 * `resolve()` returns a canonical absolute path for a known slug
	 * whose file is shipped with the plugin.
	 *
	 * Skipped when the W3TC plugin isn't physically present under
	 * `W3TC_EXTENSION_DIR` (some test layouts manually load the plugin
	 * from outside `WP_PLUGIN_DIR`). In that case `resolve()` can't see
	 * the on-disk file regardless of the slug allowlist — the test
	 * still runs against CI / staging where the plugin IS at the
	 * canonical location.
	 *
	 * @since X.X.X
	 */
	public function test_resolve_accepts_known_slug() {
		$known    = Util_Extension::known_extensions();
		$expected = W3TC_EXTENSION_DIR . '/' . $known['newrelic'];
		if ( ! file_exists( $expected ) ) {
			$this->markTestSkipped( 'W3TC plugin not physically present under W3TC_EXTENSION_DIR.' );
		}

		$path = Util_Extension::resolve( 'newrelic' );
		$this->assertIsString( $path );
		$this->assertFileExists( $path );
		$this->assertStringContainsString( 'Extension_NewRelic_Plugin.php', $path );
	}

	/**
	 * Unknown / malformed slugs are refused. The "unknown slug drop"
	 * behaviour is the load-bearing piece of the slug-allowlist
	 * primitive — without it, attacker-supplied keys would still flow.
	 *
	 * @since X.X.X
	 */
	public function test_resolve_rejects_unknown_or_malformed_slug() {
		$this->assertFalse( Util_Extension::resolve( 'totally-not-a-real-extension' ) );
		$this->assertFalse( Util_Extension::resolve( '' ) );
		$this->assertFalse( Util_Extension::resolve( null ) );
		$this->assertFalse( Util_Extension::resolve( array( 'newrelic' ) ) );
		// A path-shaped value masquerading as a slug must NOT resolve.
		$this->assertFalse( Util_Extension::resolve( '../../etc/passwd' ) );
		$this->assertFalse( Util_Extension::resolve( '/etc/passwd' ) );
	}

	/**
	 * `convert_legacy_entries()` accepts the four documented legacy
	 * shapes and normalises them to a slug → expected-path map.
	 *
	 * @since X.X.X
	 */
	public function test_convert_legacy_entries_accepts_documented_shapes() {
		$known    = Util_Extension::known_extensions();
		$expected = $known['cloudflare'];

		$cases = array(
			// slug => expected-path (the original extensions.active format).
			'expected path string' => array(
				'in'  => array( 'cloudflare' => $expected ),
				'out' => array( 'cloudflare' => $expected ),
			),
			// slug => '*' (the legacy extensions.active_frontend marker).
			'wildcard marker'      => array(
				'in'  => array( 'cloudflare' => '*' ),
				'out' => array( 'cloudflare' => $expected ),
			),
			// slug => true (some call sites store booleans).
			'boolean true'         => array(
				'in'  => array( 'cloudflare' => true ),
				'out' => array( 'cloudflare' => $expected ),
			),
			// slug => 1 (truthy non-string non-bool).
			'truthy int'           => array(
				'in'  => array( 'cloudflare' => 1 ),
				'out' => array( 'cloudflare' => $expected ),
			),
		);

		foreach ( $cases as $label => $case ) {
			$this->assertSame(
				$case['out'],
				Util_Extension::convert_legacy_entries( $case['in'] ),
				"Expected match for [$label]"
			);
		}
	}

	/**
	 * Unknown slugs in the input map are silently dropped.
	 *
	 * @since X.X.X
	 */
	public function test_convert_legacy_entries_drops_unknown_slugs() {
		$out = Util_Extension::convert_legacy_entries(
			array(
				'cloudflare'              => '*',
				'totally-fake-extension'  => '*',
				'../../etc/passwd'        => '*',
				''                        => '*',
			)
		);
		$this->assertArrayHasKey( 'cloudflare', $out );
		$this->assertArrayNotHasKey( 'totally-fake-extension', $out );
		$this->assertArrayNotHasKey( '../../etc/passwd', $out );
		$this->assertArrayNotHasKey( '', $out );
		$this->assertCount( 1, $out );
	}

	/**
	 * **The load-bearing defence-in-depth check.** A known slug whose
	 * value is a relative path that does NOT match the expected path is
	 * also dropped. This stops an attacker who lands a legitimate slug
	 * in `extensions.active` (via a config-write surface) from
	 * substituting a malicious path under the same key.
	 *
	 * @since X.X.X
	 */
	public function test_convert_legacy_entries_drops_alien_path_under_known_slug() {
		$payloads = array(
			'absolute traversal' => array( 'cloudflare' => '/tmp/evil.php' ),
			'relative traversal' => array( 'cloudflare' => '../../tmp/evil.php' ),
			'wrong plugin file'  => array( 'cloudflare' => 'w3-total-cache/Extension_NewRelic_Plugin.php' ),
			'random string'      => array( 'cloudflare' => 'something-else.php' ),
		);
		foreach ( $payloads as $label => $in ) {
			$out = Util_Extension::convert_legacy_entries( $in );
			$this->assertArrayNotHasKey(
				'cloudflare',
				$out,
				"Expected drop for [$label] " . var_export( $in, true )
			);
		}
	}

	/**
	 * Non-array input → empty array (the function never throws or warns).
	 *
	 * @since X.X.X
	 */
	public function test_convert_legacy_entries_rejects_non_array() {
		$this->assertSame( array(), Util_Extension::convert_legacy_entries( null ) );
		$this->assertSame( array(), Util_Extension::convert_legacy_entries( 'string' ) );
		$this->assertSame( array(), Util_Extension::convert_legacy_entries( 42 ) );
		$this->assertSame( array(), Util_Extension::convert_legacy_entries( new stdClass() ) );
	}

	/**
	 * Empty map → empty result; no crash, no warning.
	 *
	 * @since X.X.X
	 */
	public function test_convert_legacy_entries_handles_empty_map() {
		$this->assertSame( array(), Util_Extension::convert_legacy_entries( array() ) );
	}
}
