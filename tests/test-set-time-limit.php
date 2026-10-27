<?php
/**
 * set_time_limit calls go through the disable_functions guard.
 *
 * @package W3TC\Tests
 */

use W3TC\Util_Environment;

/**
 * Tests for the disabled set_time_limit guard.
 */
class Set_Time_Limit_Test extends WP_UnitTestCase {
	/**
	 * The helper can run when the function is allowed.
	 *
	 * @return void
	 */
	public function test_helper_applies_the_limit_when_available() {
		Util_Environment::set_time_limit( 30 );
		$this->assertTrue( Util_Environment::can_set_time_limit( '' ) );
	}

	/**
	 * A disable_functions entry blocks the call even though the function exists.
	 *
	 * @return void
	 */
	public function test_helper_rejects_a_disabled_function() {
		$this->assertFalse( Util_Environment::can_set_time_limit( 'set_time_limit' ) );
		$this->assertFalse( Util_Environment::can_set_time_limit( 'exec, set_time_limit' ) );
		$this->assertFalse( Util_Environment::can_set_time_limit( 'SET_TIME_LIMIT' ) );
		$this->assertTrue( Util_Environment::can_set_time_limit( 'set_time_limits, exec' ) );
		$this->assertTrue( Util_Environment::can_set_time_limit( 'exec' ) );
	}

	/**
	 * Direct calls stay inside the helper that checks disable_functions.
	 *
	 * @return void
	 */
	public function test_direct_calls_use_the_helper() {
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
				if ( ! preg_match( '/(?<![:\w])set_time_limit\s*\(/', $line ) ) {
					continue;
				}
				if ( false !== strpos( $line, 'function set_time_limit' ) || false !== strpos( $line, 'function can_set_time_limit' ) ) {
					continue;
				}

				if ( substr( $path, -strlen( 'Util_Environment.php' ) ) !== 'Util_Environment.php' ) {
					$unguarded[] = $path . ':' . ( $index + 1 );
				}
			}
		}

		$source = file_get_contents( $root . '/Util_Environment.php' );
		$this->assertNotFalse( $source );
		$this->assertStringContainsString( "ini_get( 'disable_functions' )", $source );
		$this->assertStringContainsString( "'set_time_limit' === trim( \$name )", $source );
		$this->assertSame( array(), $unguarded );
	}
}
