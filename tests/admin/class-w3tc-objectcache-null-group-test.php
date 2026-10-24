<?php
/**
 * Regression tests for object cache group engine selection.
 *
 * @package W3TC
 */

declare( strict_types = 1 );

use W3TC\ObjectCache_WpObjectCache;

/**
 * Ensure null cache groups retain the default engine without PHP 8.5 deprecations.
 */
class W3tc_Objectcache_Null_Group_Test extends WP_UnitTestCase {
	/**
	 * Test null, empty, registered and unknown groups.
	 *
	 * @return void
	 */
	public function test_engine_selection_for_null_and_named_groups() {
		$reflection = new ReflectionClass( ObjectCache_WpObjectCache::class );
		$cache      = $reflection->newInstanceWithoutConstructor();
		$default    = new stdClass();
		$custom     = new stdClass();

		$reflection->getProperty( '_default_cache' )->setValue( $cache, $default );
		$reflection->getProperty( '_cache_by_group' )->setValue( $cache, array( 'custom' => $custom ) );

		$get_engine = $reflection->getMethod( '_get_engine' );
		$diagnostics = array();

		set_error_handler(
			static function ( $severity, $message ) use ( &$diagnostics ) {
				$diagnostics[] = $message;
				return true;
			}
		);

		try {
			$this->assertSame( $default, $get_engine->invoke( $cache, null ) );
			$this->assertSame( $default, $get_engine->invoke( $cache, '' ) );
			$this->assertSame( $custom, $get_engine->invoke( $cache, 'custom' ) );
			$this->assertSame( $default, $get_engine->invoke( $cache, 'unknown' ) );
		} finally {
			restore_error_handler();
		}

		$this->assertSame( array(), $diagnostics, 'Engine selection must not emit PHP diagnostics.' );
	}
}
