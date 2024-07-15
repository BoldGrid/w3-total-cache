<?php
/**
 * File: class-w3tc-admin-util-environment.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @author     BoldGrid <development@boldgrid.com>
 * @since      X.X.X
 * @link       https://www.boldgrid.com/w3-total-cache/
 */

declare( strict_types = 1 );

use W3TC\Util_Environment;

/**
 * Class: W3tc_Admin_Util_File_Test
 *
 * @since X.X.X
 */
class W3tc_Admin_Util_Environment extends WP_UnitTestCase {
	/**
	 * Test array_intersect_partial().
	 *
	 * @since X.X.X
	 */
	public function test_array_intersect_partial() {
		// Ensure matches.
		$data = array(
			array(
				array( 'https://www.example.com/test.php' ),
				array( '/test.php' ),
			),
			array(
				array( '/test.php' ),
				array( 'https://www.example.com/test.php' ),
			),
			array(
				array( 'https://www.example.com/test.php' ),
				array( 'test.php' ),
			),
			array(
				array( 'test.php' ),
				array( 'https://www.example.com/test.php' ),
			),
			array(
				array( 'https://www.example.com/match' ),
				array(
					'/test.php',
					'/extra.php',
					'nomatch',
					'match',
				),
			),
		);

		foreach ( $data as $set ) {
			$this->assertTrue( Util_Environment::array_intersect_partial( $set[0], $set[1] ) );
		}

		// Ensure no matches.
		$data = array(
			array(
				array( 'https://www.example.com/test.php' ),
				array( '/' ),
			),
			array(
				array( '/' ),
				array( 'https://www.example.com/test.php' ),
			),
			array(
				array( 'https://www.example.com/test.php' ),
				array( 'nomatch.php' ),
			),
			array(
				array( 'nomatch.php' ),
				array( 'https://www.example.com/test.php' ),
			),
			array(
				array( 'https://www.example.com/test.php' ),
				array( '/test2.php' ),
			),
			array(
				array( 'https://www.example.com/test.php' ),
				array( 'https://www.example.com/test2.php' ),
			),
			array(
				array( 'https://www.example.com/match' ),
				array(
					'/test.php',
					'/extra.php',
					'nomatch',
				),
			),
		);

		foreach ( $data as $set ) {
			$this->assertFalse( Util_Environment::array_intersect_partial( $set[0], $set[1] ) );
		}
	}
}
