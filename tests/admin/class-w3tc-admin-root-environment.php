<?php
/**
 * File: class-w3tc-admin-root-environment.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @author     BoldGrid <development@boldgrid.com>
 * @since      X.X.X
 * @link       https://www.boldgrid.com/w3-total-cache/
 */

declare( strict_types = 1 );

use W3TC\Root_Environment;

/**
 * Class: W3tc_Admin_Root_Environment_Test
 *
 * @since X.X.X
 */
class W3tc_Admin_Root_Environment_Test extends WP_UnitTestCase {
	/**
	 * Test delete_plugin_data().
	 *
	 * @since X.X.X
	 *
	 * @see w3tc_config()
	 *
	 * @return void
	 */
	public function test_delete_plugin_data() {
		// Setup: Add dummy plugin data.
		$option_name = 'w3tc_plugin_data' . wp_rand();
		add_option( $option_name, 'Test value', '', false );

		// Verify that the option exists.
		$this->assertNotFalse( get_option( $option_name ), 'Pre-condition failed: Plugin option should exist.' );

		// Call the method to delete plugin data.
		Root_Environment::delete_plugin_data( w3tc_config() );

		// Verify that the plugin option is deleted.
		$this->assertFalse( get_option( $option_name ), 'The plugin option should have been deleted.' );
	}
}
