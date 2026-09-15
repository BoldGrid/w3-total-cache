<?php
/**
 * File: class-w3tc-wp-cron-config-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\Config;
use W3TC\ConfigKeysSchema;
use W3TC\Generic_AdminActions_Default;

/**
 * Class: W3tc_Wp_Cron_Config_Test
 *
 * Purge-via-WP-Cron settings were rendered in the admin UI but never
 * registered in ConfigKeys.php. After the settings-save allowlist,
 * those POST fields were dropped, so Enable / Start Time / Interval
 * reset on every save.
 *
 * @since X.X.X
 */
class W3tc_Wp_Cron_Config_Test extends WP_UnitTestCase {

	/**
	 * HTTP name prefix and admin page for each WP-Cron settings group.
	 *
	 * @since X.X.X
	 *
	 * @return array<string, array{page: string}>
	 */
	private function cron_groups(): array {
		return array(
			'allcache'    => array( 'page' => 'w3tc_general' ),
			'pgcache'     => array( 'page' => 'w3tc_pgcache' ),
			'dbcache'     => array( 'page' => 'w3tc_dbcache' ),
			'minify'      => array( 'page' => 'w3tc_minify' ),
			'objectcache' => array( 'page' => 'w3tc_objectcache' ),
		);
	}

	/**
	 * @since X.X.X
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
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$_GET  = array();
		$_POST = array();
	}

	/**
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$_GET  = array();
		$_POST = array();
		parent::tearDown();
	}

	/**
	 * Every dotted WP-Cron UI key is registered in the schema.
	 *
	 * @since X.X.X
	 */
	public function test_schema_knows_every_wp_cron_ui_key() {
		foreach ( $this->cron_groups() as $prefix => $unused ) {
			unset( $unused );
			$this->assertTrue( ConfigKeysSchema::is_known( $prefix . '.wp_cron' ), $prefix . '.wp_cron' );
			$this->assertTrue( ConfigKeysSchema::is_known( $prefix . '.wp_cron_time' ), $prefix . '.wp_cron_time' );
			$this->assertTrue( ConfigKeysSchema::is_known( $prefix . '.wp_cron_interval' ), $prefix . '.wp_cron_interval' );
		}
	}

	/**
	 * Options PHP files must not introduce WP-Cron keys the schema omits.
	 *
	 * @since X.X.X
	 */
	public function test_options_php_wp_cron_keys_are_in_schema() {
		$files = glob( W3TC_DIR . '/inc/options/*.php' );
		$this->assertNotFalse( $files );
		$this->assertNotEmpty( $files );

		$found = array();
		foreach ( $files as $file ) {
			$src = \file_get_contents( $file );
			$this->assertNotFalse( $src, $file );
			if ( \preg_match_all( "/'key'\\s*=>\\s*'((?:[a-z]+\\.)?wp_cron(?:_time|_interval)?)'/", $src, $matches ) ) {
				foreach ( $matches[1] as $key ) {
					$found[ $key ] = true;
				}
			}
		}

		$this->assertNotEmpty( $found );
		foreach ( \array_keys( $found ) as $key ) {
			$this->assertTrue( ConfigKeysSchema::is_known( $key ), $key );
		}
	}

	/**
	 * Saving General with Enable + 3:00 AM + Daily persists allcache keys.
	 *
	 * @since X.X.X
	 */
	public function test_general_save_persists_allcache_wp_cron() {
		$_POST = array(
			'allcache__wp_cron'          => '1',
			'allcache__wp_cron_time'     => '180',
			'allcache__wp_cron_interval' => 'daily',
		);

		$config = new Config();
		$this->admin_for_page( 'w3tc_general' )->read_request( $config );

		$this->assertTrue( $config->get_boolean( 'allcache.wp_cron' ) );
		$this->assertSame( 180, $config->get_integer( 'allcache.wp_cron_time' ) );
		$this->assertSame( 'daily', $config->get_string( 'allcache.wp_cron_interval' ) );
	}

	/**
	 * Twice Daily on a cache-specific page also persists.
	 *
	 * @since X.X.X
	 */
	public function test_pgcache_save_persists_wp_cron_twicedaily() {
		$_POST = array(
			'pgcache__wp_cron'          => '1',
			'pgcache__wp_cron_time'     => '180',
			'pgcache__wp_cron_interval' => 'twicedaily',
		);

		$config = new Config();
		$this->admin_for_page( 'w3tc_pgcache' )->read_request( $config );

		$this->assertTrue( $config->get_boolean( 'pgcache.wp_cron' ) );
		$this->assertSame( 180, $config->get_integer( 'pgcache.wp_cron_time' ) );
		$this->assertSame( 'twicedaily', $config->get_string( 'pgcache.wp_cron_interval' ) );
	}

	/**
	 * Remaining cache-module WP-Cron groups persist on their settings pages.
	 *
	 * @since X.X.X
	 */
	public function test_module_pages_persist_wp_cron_settings() {
		$modules = array(
			'dbcache'     => 'w3tc_dbcache',
			'minify'      => 'w3tc_minify',
			'objectcache' => 'w3tc_objectcache',
		);

		foreach ( $modules as $prefix => $page ) {
			$_POST = array(
				$prefix . '__wp_cron'          => '1',
				$prefix . '__wp_cron_time'     => '90',
				$prefix . '__wp_cron_interval' => 'hourly',
			);

			$config = new Config();
			$this->admin_for_page( $page )->read_request( $config );

			$this->assertTrue( $config->get_boolean( $prefix . '.wp_cron' ), $prefix );
			$this->assertSame( 90, $config->get_integer( $prefix . '.wp_cron_time' ), $prefix );
			$this->assertSame( 'hourly', $config->get_string( $prefix . '.wp_cron_interval' ), $prefix );
		}
	}

	/**
	 * Unchecking Enable still writes false through the hidden companion field.
	 *
	 * @since X.X.X
	 */
	public function test_general_save_can_disable_allcache_wp_cron() {
		$_POST = array(
			'allcache__wp_cron'          => '0',
			'allcache__wp_cron_time'     => '180',
			'allcache__wp_cron_interval' => 'daily',
		);

		$config = new Config();
		$config->set( 'allcache.wp_cron', true );
		$this->admin_for_page( 'w3tc_general' )->read_request( $config );

		$this->assertFalse( $config->get_boolean( 'allcache.wp_cron' ) );
		$this->assertSame( 180, $config->get_integer( 'allcache.wp_cron_time' ) );
		$this->assertSame( 'daily', $config->get_string( 'allcache.wp_cron_interval' ) );
	}
}
