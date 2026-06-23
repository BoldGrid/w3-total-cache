<?php
/**
 * File: class-w3tc-configkeysschema-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.0
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
 * @since 2.10.0
 */
class W3tc_ConfigKeysSchema_Test extends WP_UnitTestCase {

	/**
	 * The schema array loads successfully and contains real keys.
	 *
	 * @since 2.10.0
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
	 * @since 2.10.0
	 */
	public function test_is_known_distinguishes_documented_keys() {
		$this->assertTrue( ConfigKeysSchema::is_known( 'pgcache.enabled' ) );
		$this->assertTrue( ConfigKeysSchema::is_known( 'minify.ccjs.path.java' ) );
		$this->assertFalse( ConfigKeysSchema::is_known( 'totally.made.up.key' ) );
		$this->assertFalse( ConfigKeysSchema::is_known( '' ) );
		$this->assertFalse( ConfigKeysSchema::is_known( null ) );

		/**
		 * Compound keys (extension sub-keys) pass through unconditionally —
		 * the extension's own filter is the real gate.
		 */
		$this->assertTrue( ConfigKeysSchema::is_known( array( 'my_extension', 'some_setting' ) ) );
	}

	/**
	 * is_known() admits `extension.<id>` and `extension.<id>.<setting>`
	 * keys whose `<id>` is registered through the `w3tc_extensions`
	 * filter. This restores the Export → Import round-trip for any
	 * install with active extensions; the static schema cannot
	 * enumerate dynamically-registered keys, so without this branch
	 * `Config::import()` silently drops every extension's settings.
	 *
	 * Runs in a separate process so the static `$extension_ids` cache
	 * inside ConfigKeysSchema does not leak across tests.
	 *
	 * @since 2.10.0
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_is_known_admits_extension_prefix_keys() {
		add_filter(
			'w3tc_extensions',
			function ( $exts ) {
				$exts['test_qa_ext'] = array(
					'name' => 'Test QA Extension',
					'path' => 'w3-total-cache/Extension_TestQa_Plugin.php',
				);
				return $exts;
			},
			20,
			1
		);

		$this->assertTrue(
			ConfigKeysSchema::is_known( 'extension.test_qa_ext' ),
			'Bare extension enable-flag key should be admitted'
		);
		$this->assertTrue(
			ConfigKeysSchema::is_known( 'extension.test_qa_ext.some_setting' ),
			'Per-setting extension key should be admitted'
		);
		$this->assertTrue(
			ConfigKeysSchema::is_known( 'extension.test_qa_ext.nested.deep' ),
			'Multi-level extension key should be admitted'
		);

		$this->assertFalse(
			ConfigKeysSchema::is_known( 'extension.not_registered.setting' ),
			'Unregistered extension id must not be admitted'
		);
		$this->assertFalse(
			ConfigKeysSchema::is_known( 'extension.' ),
			'Bare `extension.` prefix must not be admitted'
		);
	}

	/**
	 * descriptor() returns the type/default block, or null.
	 *
	 * @since 2.10.0
	 */
	public function test_descriptor_returns_type_and_default() {
		$d = ConfigKeysSchema::descriptor( 'pgcache.enabled' );
		$this->assertIsArray( $d );
		$this->assertSame( 'boolean', $d['type'] );

		$this->assertNull( ConfigKeysSchema::descriptor( 'totally.made.up.key' ) );

		/**
		 * Compound keys are not in the static schema; we get null even
		 * though `is_known()` returns true for them.
		 */
		$this->assertNull( ConfigKeysSchema::descriptor( array( 'my_extension', 'some_setting' ) ) );
	}

	/**
	 * High-impact keys flagged `no_import` are refused at the import
	 * boundary even when they're known to the schema.
	 *
	 * @since 2.10.0
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
	 * @since 2.10.0
	 */
	public function test_can_import_allows_ordinary_keys() {
		$this->assertTrue( ConfigKeysSchema::can_import( 'pgcache.enabled' ) );
		$this->assertTrue( ConfigKeysSchema::can_import( 'minify.enabled' ) );
		$this->assertTrue( ConfigKeysSchema::can_import( 'browsercache.cssjs.lifetime' ) );
	}

	/**
	 * Unknown keys can NOT be imported.
	 *
	 * @since 2.10.0
	 */
	public function test_can_import_rejects_unknown_keys() {
		$this->assertFalse( ConfigKeysSchema::can_import( 'totally.made.up.key' ) );
		$this->assertFalse( ConfigKeysSchema::can_import( '' ) );
	}

	/**
	 * can_import() admits `extension.<id>...` keys whose id is
	 * registered through the `w3tc_extensions` filter. Mirrors
	 * `test_is_known_admits_extension_prefix_keys` so the export →
	 * import round-trip restores an active extension's own settings.
	 * Without this, `Config::import()` counts every extension key as
	 * `rejected_locked` and silently drops it.
	 *
	 * Runs in a separate process so the static `$extension_ids` cache
	 * inside ConfigKeysSchema does not leak across tests.
	 *
	 * @since 2.10.0
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_can_import_allows_registered_extension_prefix_keys() {
		add_filter(
			'w3tc_extensions',
			function ( $exts ) {
				$exts['test_qa_ext'] = array(
					'name' => 'Test QA Extension',
					'path' => 'w3-total-cache/Extension_TestQa_Plugin.php',
				);
				return $exts;
			},
			20,
			1
		);

		$this->assertTrue(
			ConfigKeysSchema::can_import( 'extension.test_qa_ext' ),
			'Bare extension enable-flag key should be importable'
		);
		$this->assertTrue(
			ConfigKeysSchema::can_import( 'extension.test_qa_ext.some_setting' ),
			'Per-setting extension key should be importable'
		);
		$this->assertTrue(
			ConfigKeysSchema::can_import( 'extension.test_qa_ext.nested.deep' ),
			'Multi-level extension key should be importable'
		);

		$this->assertFalse(
			ConfigKeysSchema::can_import( 'extension.not_registered.setting' ),
			'Unregistered extension id must not be importable'
		);
		$this->assertFalse(
			ConfigKeysSchema::can_import( 'extension.' ),
			'Bare `extension.` prefix must not be importable'
		);
	}

	/**
	 * coerce() collapses every type's invalid payloads to the type's
	 * safe default, regardless of the incoming PHP type.
	 *
	 * @since 2.10.0
	 */
	public function test_coerce_collapses_invalid_payloads_to_type_default() {
		/**
		 * boolean — scalars only; structured payloads collapse to false.
		 * The point is that no object / array shape reaches storage as a
		 * truthy toggle. `(bool) ['x'=>1]` would otherwise be `true`,
		 * smuggling a structured value through a boolean key.
		 */
		$bool_d = array( 'type' => 'boolean' );
		$this->assertSame( true, ConfigKeysSchema::coerce( '1', $bool_d ) );
		$this->assertSame( true, ConfigKeysSchema::coerce( 1, $bool_d ) );
		$this->assertSame( false, ConfigKeysSchema::coerce( 0, $bool_d ) );
		$this->assertSame( false, ConfigKeysSchema::coerce( '', $bool_d ) );
		/**
		 * Structured payloads (POP gadget shape, JSON-imported sub-array)
		 * fold to false — not to PHP's natural `(bool)` of a non-empty
		 * structure, which would be true.
		 */
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
	 * @since 2.10.0
	 */
	public function test_coerce_passthrough_for_unknown_type() {
		$this->assertSame( 'x', ConfigKeysSchema::coerce( 'x', array() ) );
		$this->assertSame( 'x', ConfigKeysSchema::coerce( 'x', array( 'type' => 'unknown_type' ) ) );
	}

	/**
	 * `is_known_state_key()` admits the dismiss-notice idiom across
	 * every URL builder in the plugin (and its extensions). One
	 * representative key from each caller cssjoe enumerated in the
	 * round-1 review is pinned here so the structural gate doesn't
	 * drift away from what the UI actually submits.
	 *
	 * @since 2.10.0
	 */
	public function test_is_known_state_key_accepts_dismiss_notice_callers() {
		$accepted = array(
			// Generic_AdminNotes.
			'common.hide_note_wp_content_permissions',
			'common.hide_note_no_zlib',
			'common.show_note.nginx_restart_required',
			'common.show_note.plugins_updated',
			// Cdn_AdminNotes.
			'cdn.show_note_theme_changed',
			'cdn.hide_note_no_curl',
			// UsageStatistics_Plugin_Admin.
			'common.hide_note_stats_enabled',
			// Minify_Plugin_Admin.
			'minify.show_note_minify_error',
			// Extension_Genesis_Plugin_Admin.
			'genesis.theme.hide_note_suggest_activation',
			// Extension_Wpml_Plugin_Admin.
			'wpml.hide_note_language_negotiation_type',
			'wpml.hide_note_suggest_activation',
			// Extension_WordPressSeo_Plugin_Admin.
			'wordpress_seo.hide_note_suggest_activation',
			// Extension_NewRelic_AdminNotes.
			'newrelic.hide_note_pageload_slow',
		);
		foreach ( $accepted as $key ) {
			$this->assertTrue(
				ConfigKeysSchema::is_known_state_key( $key ),
				"Expected dismiss-notice key [$key] to be admitted"
			);
		}
	}

	/**
	 * `is_known_state_key()` rejects non-notice keys (license/install
	 * state written internally via Dispatcher::config_state*()->set(),
	 * never reached through the gated handlers), malformed shapes, and
	 * traversal-shaped payloads.
	 *
	 * @since 2.10.0
	 */
	public function test_is_known_state_key_rejects_non_notice_shapes() {
		$rejected = array(
			''                            => 'empty string',
			'arbitrary.key'               => 'no hide_note / show_note substring',
			'license.status'              => 'license state (internal-only)',
			'common.install_version'      => 'install state (internal-only)',
			'common.support_us'           => 'non-notice flag',
			'UPPERCASE.hide_note_x'       => 'uppercase first segment violates shape',
			'1bad.hide_note_x'            => 'digit-first segment violates shape',
			'one.two.three.four.five'     => 'too many segments',
			'..hide_note'                 => 'leading dot',
			'common.hide_note_x.'         => 'trailing dot',
			'common.hide note'            => 'whitespace in key',
			'../../etc/passwd'            => 'traversal payload',
			'common.hide_note$evil()'     => 'shell metacharacters in key',
		);
		foreach ( $rejected as $key => $why ) {
			$this->assertFalse(
				ConfigKeysSchema::is_known_state_key( $key ),
				"Expected key [$key] to be rejected ($why)"
			);
		}
	}
}
