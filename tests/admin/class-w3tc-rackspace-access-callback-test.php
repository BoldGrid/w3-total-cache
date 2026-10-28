<?php
/**
 * File: class-w3tc-rackspace-access-callback-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.1
 */

declare( strict_types = 1 );

use W3TC\CdnEngine_Mirror_RackSpaceCdn;
use W3TC\CdnEngine_RackSpaceCloudFiles;

/**
 * Class: W3tc_Rackspace_Access_Callback_Test
 *
 * The second Rackspace access callback is invoked with call_user_func()
 * from another class. It must stay callable on PHP 8.
 *
 * @since 2.11.1
 */
class W3tc_Rackspace_Access_Callback_Test extends WP_UnitTestCase {

	/**
	 * Cloud Files second-attempt callback throws authentication failure.
	 *
	 * @return void
	 */
	public function test_cloudfiles_second_access_callback_is_invocable() {
		$engine   = new CdnEngine_RackSpaceCloudFiles(
			array(
				'new_access_state_callback' => static function () {
				},
			)
		);
		$callback = $this->second_access_callback( $engine );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Authentication failed' );
		call_user_func( $callback );
	}

	/**
	 * Rackspace mirror CDN second-attempt callback throws authentication failure.
	 *
	 * @return void
	 */
	public function test_mirror_second_access_callback_is_invocable() {
		$engine   = new CdnEngine_Mirror_RackSpaceCdn();
		$callback = $this->second_access_callback( $engine );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Authentication failed' );
		call_user_func( $callback );
	}

	/**
	 * Second-attempt callback from an engine.
	 *
	 * @param object $engine Rackspace engine.
	 *
	 * @return callable
	 */
	private function second_access_callback( $engine ) {
		$method = new ReflectionMethod( $engine, '_second_access_callback' );
		$method->setAccessible( true );
		$callback = $method->invoke( $engine );
		$this->assertIsCallable( $callback );
		return $callback;
	}
}
