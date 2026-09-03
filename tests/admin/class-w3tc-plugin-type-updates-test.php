<?php
/**
 * File: class-w3tc-plugin-type-updates-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.6
 */

declare( strict_types = 1 );

use W3TC\Config;
use W3TC\ConfigKeysSchema;
use W3TC\Generic_AdminActions_Default;

/**
 * Class: W3tc_Plugin_Type_Updates_Test
 *
 * Pins ENG7-4291 Phase 2 behaviour for `plugin.type`:
 *
 *  - schema: no_import, no dedicated_page, enum of license outcomes
 *  - settings POST cannot elevate or clear entitlement
 *  - config import cannot change entitlement
 *  - license-path Config::set() may still promote / clear allowed values
 *  - out-of-enum Config::set() retains the prior value
 *
 * @since 2.10.6
 */
class W3tc_Plugin_Type_Updates_Test extends WP_UnitTestCase {

	/**
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$tmp = WP_CONTENT_DIR . '/cache/tmp';
		if ( ! is_dir( $tmp ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@mkdir( $tmp, 0755, true );
		}
		$_GET  = array();
		$_POST = array();
	}

	/**
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$_GET  = array();
		$_POST = array();
		parent::tearDown();
	}

	/**
	 * @since 2.10.6
	 *
	 * @param string $value Seed plugin.type value.
	 *
	 * @return Config
	 */
	private function seeded_plugin_type( string $value ): Config {
		$config = new Config();
		$ref    = new \ReflectionObject( $config );
		$prop   = $ref->getProperty( '_data' );
		$prop->setAccessible( true );
		$data               = $prop->getValue( $config );
		$data['plugin.type'] = $value;
		$prop->setValue( $config, $data );

		return $config;
	}

	/**
	 * @since 2.10.6
	 *
	 * @param string $page Admin page slug.
	 *
	 * @return Generic_AdminActions_Default
	 */
	private function admin_for_page( string $page ): Generic_AdminActions_Default {
		$_GET['page'] = $page;
		return new Generic_AdminActions_Default();
	}

	/**
	 * Metadata: no_import, no dedicated_page escape hatch.
	 *
	 * @since 2.10.6
	 */
	public function test_schema_marks_plugin_type_license_path_only() {
		$this->assertTrue( ConfigKeysSchema::is_known( 'plugin.type' ) );
		$this->assertTrue( ConfigKeysSchema::is_no_import( 'plugin.type' ) );
		$this->assertFalse( ConfigKeysSchema::can_import( 'plugin.type' ) );
		$this->assertNull( ConfigKeysSchema::dedicated_page( 'plugin.type' ) );
	}

	/**
	 * Settings POST cannot self-elevate to Pro.
	 *
	 * @since 2.10.6
	 */
	public function test_read_request_rejects_plugin_type_elevation() {
		$_POST = array(
			'plugin__type' => 'pro',
		);

		$config = $this->seeded_plugin_type( '' );
		$this->admin_for_page( 'w3tc_general' )->read_request( $config );

		$this->assertSame( '', $config->get_string( 'plugin.type' ) );
	}

	/**
	 * Settings POST cannot clear an existing Pro entitlement either.
	 *
	 * @since 2.10.6
	 */
	public function test_read_request_rejects_plugin_type_clear() {
		$_POST = array(
			'plugin__type' => '',
		);

		$config = $this->seeded_plugin_type( 'pro' );
		$this->admin_for_page( 'w3tc_cdn' )->read_request( $config );

		$this->assertSame( 'pro', $config->get_string( 'plugin.type' ) );
	}

	/**
	 * Bulk import soft-skips plugin.type; neighbours still apply.
	 *
	 * @since 2.10.6
	 */
	public function test_import_rejects_plugin_type_mutation() {
		$config = $this->seeded_plugin_type( '' );
		$config->set( 'pgcache.enabled', false );

		$ok = $config->import_from_array(
			array(
				'pgcache.enabled' => true,
				'plugin.type'     => 'pro',
			)
		);

		$this->assertTrue( $ok );
		$this->assertTrue( $config->get_boolean( 'pgcache.enabled' ) );
		$this->assertSame( '', $config->get_string( 'plugin.type' ) );
	}

	/**
	 * License path (Config::set after EDD) may promote to pro / pro_dev
	 * and may clear back to empty.
	 *
	 * @since 2.10.6
	 */
	public function test_license_path_set_allows_supported_values() {
		$config = $this->seeded_plugin_type( '' );

		$config->set( 'plugin.type', 'pro' );
		$this->assertSame( 'pro', $config->get_string( 'plugin.type' ) );

		$config->set( 'plugin.type', 'pro_dev' );
		$this->assertSame( 'pro_dev', $config->get_string( 'plugin.type' ) );

		$config->set( 'plugin.type', '' );
		$this->assertSame( '', $config->get_string( 'plugin.type' ) );
	}

	/**
	 * Out-of-enum values are refused at Config::set (retain prior).
	 *
	 * @since 2.10.6
	 */
	public function test_set_rejects_out_of_enum_plugin_type() {
		$config = $this->seeded_plugin_type( 'pro' );

		$config->set( 'plugin.type', 'enterprise' );
		$this->assertSame( 'pro', $config->get_string( 'plugin.type' ) );

		$config->set( 'plugin.type', 'free' );
		$this->assertSame( 'pro', $config->get_string( 'plugin.type' ) );
	}
}
