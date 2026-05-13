<?php
/**
 * File: class-w3tc-configkeysschema-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\ConfigKeysSchema;

/**
 * Class: W3tc_ConfigKeysSchema_Test
 *
 * Coverage for the configuration-key schema accessor that backs the
 * mass-assignment hardening. The schema accessor is the single source
 * of truth for "is this key importable?" — every boundary (Config::import,
 * read_request, overloaded toggles) consults it, so regressions here
 * translate directly to re-opened mass-assignment primitives.
 *
 * @since X.X.X
 */
class W3tc_ConfigKeysSchema_Test extends WP_UnitTestCase {

	/**
	 * The schema array loads successfully and contains real keys.
	 *
	 * @since X.X.X
	 */
	public function test_get_keys_loads_schema() {
		$keys = ConfigKeysSchema::get_keys();
		$this->assertIsArray( $keys );
		$this->assertNotEmpty( $keys );
		$this->assertArrayHasKey( 'pgcache.enabled', $keys );
		$this->assertArrayHasKey( 'minify.ccjs.path.java', $keys );
		$this->assertArrayHasKey( 'extensions.active', $keys );
	}

	/**
	 * is_known() distinguishes documented keys from unknown ones, and
	 * accepts compound keys unconditionally (extensions own their gate).
	 *
	 * @since X.X.X
	 */
	public function test_is_known_distinguishes_documented_keys() {
		$this->assertTrue( ConfigKeysSchema::is_known( 'pgcache.enabled' ) );
		$this->assertTrue( ConfigKeysSchema::is_known( 'minify.ccjs.path.java' ) );
		$this->assertFalse( ConfigKeysSchema::is_known( 'totally.made.up.key' ) );
		$this->assertFalse( ConfigKeysSchema::is_known( '' ) );
		$this->assertFalse( ConfigKeysSchema::is_known( null ) );

		// Compound keys (extension sub-keys) pass through unconditionally —
		// the extension's own filter is the real gate.
		$this->assertTrue( ConfigKeysSchema::is_known( array( 'my_extension', 'some_setting' ) ) );
	}

	/**
	 * descriptor() returns the type/default block, or null.
	 *
	 * @since X.X.X
	 */
	public function test_descriptor_returns_type_and_default() {
		$d = ConfigKeysSchema::descriptor( 'pgcache.enabled' );
		$this->assertIsArray( $d );
		$this->assertSame( 'boolean', $d['type'] );

		$this->assertNull( ConfigKeysSchema::descriptor( 'totally.made.up.key' ) );

		// Compound keys are not in the static schema; we get null even
		// though `is_known()` returns true for them.
		$this->assertNull( ConfigKeysSchema::descriptor( array( 'my_extension', 'some_setting' ) ) );
	}

	/**
	 * High-impact keys flagged `no_import` are refused at the import
	 * boundary even when they're known to the schema.
	 *
	 * @since X.X.X
	 */
	public function test_can_import_blocks_no_import_keys() {
		$no_import_keys = array(
			'extensions.active',
			'extensions.active_frontend',
			'extensions.active_dropin',
			'minify.yuijs.path.java',
			'minify.yuicss.path.java',
			'minify.ccjs.path.java',
			'minify.yuijs.path.jar',
			'minify.yuicss.path.jar',
			'minify.ccjs.path.jar',
			'pgcache.engine',
			'dbcache.engine',
			'objectcache.engine',
			'cdn.engine',
			'minify.css.engine',
			'minify.js.engine',
		);
		foreach ( $no_import_keys as $key ) {
			$this->assertTrue(
				ConfigKeysSchema::is_known( $key ),
				"Schema must know $key (otherwise the no_import flag never runs)."
			);
			$this->assertFalse(
				ConfigKeysSchema::can_import( $key ),
				"$key must be marked no_import."
			);
		}
	}

	/**
	 * Ordinary known keys may be imported.
	 *
	 * @since X.X.X
	 */
	public function test_can_import_allows_ordinary_keys() {
		$this->assertTrue( ConfigKeysSchema::can_import( 'pgcache.enabled' ) );
		$this->assertTrue( ConfigKeysSchema::can_import( 'minify.enabled' ) );
		$this->assertTrue( ConfigKeysSchema::can_import( 'browsercache.cssjs.lifetime' ) );
	}

	/**
	 * Unknown keys can NOT be imported.
	 *
	 * @since X.X.X
	 */
	public function test_can_import_rejects_unknown_keys() {
		$this->assertFalse( ConfigKeysSchema::can_import( 'totally.made.up.key' ) );
		$this->assertFalse( ConfigKeysSchema::can_import( '' ) );
	}

	/**
	 * coerce() collapses every type's invalid payloads to the type's
	 * safe default, regardless of the incoming PHP type.
	 *
	 * @since X.X.X
	 */
	public function test_coerce_collapses_invalid_payloads_to_type_default() {
		// boolean — scalars only; structured payloads collapse to false.
		// The point is that no object / array shape reaches storage as a
		// truthy toggle. `(bool) ['x'=>1]` would otherwise be `true`,
		// smuggling a structured value through a boolean key.
		$bool_d = array( 'type' => 'boolean' );
		$this->assertSame( true, ConfigKeysSchema::coerce( '1', $bool_d ) );
		$this->assertSame( true, ConfigKeysSchema::coerce( 1, $bool_d ) );
		$this->assertSame( false, ConfigKeysSchema::coerce( 0, $bool_d ) );
		$this->assertSame( false, ConfigKeysSchema::coerce( '', $bool_d ) );
		// Structured payloads (POP gadget shape, JSON-imported sub-array)
		// fold to false — not to PHP's natural `(bool)` of a non-empty
		// structure, which would be true.
		$this->assertSame( false, ConfigKeysSchema::coerce( new stdClass(), $bool_d ) );
		$this->assertSame( false, ConfigKeysSchema::coerce( array( 'x' => 1 ), $bool_d ) );
		$this->assertSame( false, ConfigKeysSchema::coerce( array(), $bool_d ) );

		// integer — strict int cast.
		$int_d = array( 'type' => 'integer' );
		$this->assertSame( 42, ConfigKeysSchema::coerce( '42', $int_d ) );
		$this->assertSame( 0, ConfigKeysSchema::coerce( 'not a number', $int_d ) );
		$this->assertSame( 7, ConfigKeysSchema::coerce( 7.9, $int_d ) );
		$this->assertIsInt( ConfigKeysSchema::coerce( new stdClass(), $int_d ) );
		$this->assertSame( 0, ConfigKeysSchema::coerce( array( 'x' ), $int_d ) );

		// string — scalars preserved, non-scalars → ''.
		$str_d = array( 'type' => 'string' );
		$this->assertSame( 'hello', ConfigKeysSchema::coerce( 'hello', $str_d ) );
		$this->assertSame( '42', ConfigKeysSchema::coerce( 42, $str_d ) );
		$this->assertSame( '', ConfigKeysSchema::coerce( array( 'a' ), $str_d ) );
		$this->assertSame( '', ConfigKeysSchema::coerce( new stdClass(), $str_d ) );

		// array — arrays preserved, non-arrays → [].
		$arr_d = array( 'type' => 'array' );
		$this->assertSame( array( 'a' => 1 ), ConfigKeysSchema::coerce( array( 'a' => 1 ), $arr_d ) );
		$this->assertSame( array(), ConfigKeysSchema::coerce( 'string', $arr_d ) );
		$this->assertSame( array(), ConfigKeysSchema::coerce( new stdClass(), $arr_d ) );
	}

	/**
	 * coerce() with no descriptor / unknown type falls through unchanged.
	 *
	 * @since X.X.X
	 */
	public function test_coerce_passthrough_for_unknown_type() {
		$this->assertSame( 'x', ConfigKeysSchema::coerce( 'x', array() ) );
		$this->assertSame( 'x', ConfigKeysSchema::coerce( 'x', array( 'type' => 'unknown_type' ) ) );
	}

	/**
	 * is_known_state_key() guards the w3tc_default_config_state handlers.
	 *
	 * @since X.X.X
	 */
	public function test_is_known_state_key() {
		$this->assertTrue( ConfigKeysSchema::is_known_state_key( 'license.status' ) );
		$this->assertTrue( ConfigKeysSchema::is_known_state_key( 'common.hide_note_php_is_old' ) );
		$this->assertFalse( ConfigKeysSchema::is_known_state_key( 'arbitrary.key' ) );
		$this->assertFalse( ConfigKeysSchema::is_known_state_key( '' ) );
	}

	/**
	 * The state-key allowlist is exposed (non-empty) for tests / UI.
	 *
	 * @since X.X.X
	 */
	public function test_state_key_allowlist_is_exposed() {
		$allowlist = ConfigKeysSchema::state_key_allowlist();
		$this->assertIsArray( $allowlist );
		$this->assertNotEmpty( $allowlist );
	}
}
