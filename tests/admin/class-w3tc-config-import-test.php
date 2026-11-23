<?php
/**
 * File: class-w3tc-config-import-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.6
 */

declare( strict_types = 1 );

use W3TC\Config;
use W3TC\ConfigKeysSchema;

/**
 * Class: W3tc_Config_Import_Test
 *
 * Coverage for Config::import_from_array() — schema allowlist, soft
 * unknown/no_import skips, structural aborts with no partial writes,
 * and directive-string rule-generation sanitisation on persistence.
 *
 * @since 2.10.6
 */
class W3tc_Config_Import_Test extends WP_UnitTestCase {

	/**
	 * A supported export-shaped blob applies known keys.
	 *
	 * @since 2.10.6
	 */
	public function test_valid_supported_export_applies_expected_keys() {
		$config = new Config();
		$config->set( 'pgcache.enabled', false );
		$config->set( 'browsercache.cssjs.lifetime', 100 );

		$ok = $config->import_from_array(
			array(
				'version'                     => '2.10.5',
				'pgcache.enabled'             => true,
				'browsercache.cssjs.lifetime' => 3600,
			)
		);

		$this->assertTrue( $ok );
		$this->assertSame( '', $config->get_last_import_error() );
		$this->assertTrue( $config->get_boolean( 'pgcache.enabled' ) );
		$this->assertSame( 3600, $config->get_integer( 'browsercache.cssjs.lifetime' ) );
	}

	/**
	 * Unknown and no_import keys are soft-skipped; valid neighbours apply.
	 *
	 * @since 2.10.6
	 */
	public function test_mixed_valid_invalid_soft_skips_unknown_and_no_import() {
		$config = new Config();
		$config->set( 'pgcache.enabled', false );
		$config->set( 'minify.ccjs.path.java', '/usr/bin/java-original' );

		$ok = $config->import_from_array(
			array(
				'pgcache.enabled'       => true,
				'totally.made.up.key'   => 'evil',
				'minify.ccjs.path.java' => '/tmp/evil-java',
			)
		);

		$this->assertTrue( $ok, 'Soft skips must not abort the import.' );
		$this->assertTrue( $config->get_boolean( 'pgcache.enabled' ) );
		$this->assertSame(
			'/usr/bin/java-original',
			$config->get_string( 'minify.ccjs.path.java' ),
			'no_import keys must retain their pre-import value.'
		);
		$this->assertFalse(
			$config->get( 'totally.made.up.key', false ),
			'Unknown keys must not be persisted.'
		);
	}

	/**
	 * Non-string / compound key shapes abort with no writes.
	 *
	 * @since 2.10.6
	 */
	public function test_malformed_key_shape_aborts_without_partial_update() {
		$config = new Config();
		$config->set( 'pgcache.enabled', false );

		$payload = array(
			'pgcache.enabled' => true,
		);
		/**
		 * Simulate a JSON-array / compound key shape the file importer
		 * must never partially apply.
		 */
		$payload[0] = array( 'nested', 'compound' );

		$ok = $config->import_from_array( $payload );

		$this->assertFalse( $ok );
		$this->assertSame( 'invalid_key_shape', $config->get_last_import_error() );
		$this->assertFalse(
			$config->get_boolean( 'pgcache.enabled' ),
			'Structural abort must leave prior config untouched.'
		);
	}

	/**
	 * Nested objects inside directive-string array values abort with
	 * no writes (rule-generation inputs must be list-of-scalars).
	 *
	 * @since 2.10.6
	 */
	public function test_nested_rule_generation_value_aborts_without_partial_update() {
		$config = new Config();
		$config->set( 'pgcache.enabled', false );
		$config->set(
			'browsercache.no404wp.exceptions',
			array( 'robots\.txt' )
		);

		$ok = $config->import_from_array(
			array(
				'pgcache.enabled'                 => true,
				'browsercache.no404wp.exceptions' => array(
					array( 'nested' => "evil\nHeader set X-Injected \"1\"" ),
				),
			)
		);

		$this->assertFalse( $ok );
		$this->assertSame( 'nested_rule_generation_value', $config->get_last_import_error() );
		$this->assertFalse(
			$config->get_boolean( 'pgcache.enabled' ),
			'Nested rule-gen failure must not apply sibling keys.'
		);
		$this->assertSame(
			array( 'robots\.txt' ),
			$config->get_array( 'browsercache.no404wp.exceptions' )
		);
	}

	/**
	 * CRLF payloads in no404wp.exceptions are stripped at set time so
	 * they cannot reach renderers or master.php via import.
	 *
	 * @since 2.10.6
	 */
	public function test_directive_string_no404wp_exceptions_stripped_on_import() {
		$this->assertTrue(
			ConfigKeysSchema::is_directive_string_key( 'browsercache.no404wp.exceptions' ),
			'browsercache.no404wp.exceptions must carry directive_string.'
		);

		$config = new Config();
		$ok     = $config->import_from_array(
			array(
				'browsercache.no404wp.exceptions' => array(
					"robots\\.txt\nHeader set X-RT19-Proof \"injected\"",
					'clean-exception',
				),
			)
		);

		$this->assertTrue( $ok );
		$stored = $config->get_array( 'browsercache.no404wp.exceptions' );
		$this->assertNotEmpty( $stored );
		foreach ( $stored as $entry ) {
			$this->assertIsString( $entry );
			$this->assertSame(
				0,
				preg_match( '/[\r\n\x00<>"]/', $entry ),
				'Imported rule-gen entries must not retain directive-breaking bytes: ' . $entry
			);
		}
		$this->assertContains( 'clean-exception', $stored );
	}

	/**
	 * Schema helpers distinguish importable key shapes.
	 *
	 * @since 2.10.6
	 */
	public function test_is_import_key_shape() {
		$this->assertTrue( ConfigKeysSchema::is_import_key_shape( 'pgcache.enabled' ) );
		$this->assertFalse( ConfigKeysSchema::is_import_key_shape( '' ) );
		$this->assertFalse( ConfigKeysSchema::is_import_key_shape( null ) );
		$this->assertFalse( ConfigKeysSchema::is_import_key_shape( array( 'a', 'b' ) ) );
		$this->assertFalse( ConfigKeysSchema::is_import_key_shape( 0 ) );
	}
}
