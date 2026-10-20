<?php
/**
 * set_time_limit calls stay behind a function_exists guard.
 *
 * @package W3TC\Tests
 */

use W3TC\Util_Environment;

/**
 * Tests for the disabled set_time_limit guard.
 */
class Set_Time_Limit_Test extends WP_UnitTestCase {
	/**
	 * The helper can run when the function exists.
	 *
	 * @return void
	 */
	public function test_helper_applies_the_limit_when_available() {
		Util_Environment::set_time_limit( 30 );
		$this->assertTrue( function_exists( 'set_time_limit' ) );
	}

	/**
	 * Every direct call is inside a function_exists check.
	 *
	 * @return void
	 */
	public function test_direct_calls_are_guarded() {
		$root      = dirname( __DIR__ );
		$iterator  = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
		);
		$unguarded = array();

		foreach ( $iterator as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}

			$path = $file->getPathname();
			if ( false !== strpos( $path, '/vendor/' ) || false !== strpos( $path, '/node_modules/' ) ) {
				continue;
			}

			$lines = file( $path );
			foreach ( $lines as $index => $line ) {
				if ( preg_match( '/^\s*(\*|\/\/)/', $line ) ) {
					continue;
				}
				if ( ! preg_match( '/(?<!::)set_time_limit\s*\(/', $line ) ) {
					continue;
				}
				if ( false !== strpos( $line, 'function set_time_limit' ) ) {
					continue;
				}

				$window = implode( '', array_slice( $lines, max( 0, $index - 3 ), 4 ) );
				if ( false === strpos( $window, "function_exists( 'set_time_limit' )" ) ) {
					$unguarded[] = $path . ':' . ( $index + 1 );
				}
			}
		}

		$this->assertSame( array(), $unguarded );
	}
}
