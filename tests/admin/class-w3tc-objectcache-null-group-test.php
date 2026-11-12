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

		$default_property = $reflection->getProperty( '_default_cache' );
		$groups_property  = $reflection->getProperty( '_cache_by_group' );
		$get_engine       = $reflection->getMethod( '_get_engine' );

		// PHP 7.4 requires explicit access to non-public Reflection members.
		if ( PHP_VERSION_ID < 80100 ) {
			$default_property->setAccessible( true );
			$groups_property->setAccessible( true );
			$get_engine->setAccessible( true );
		}

		$default_property->setValue( $cache, $default );
		$groups_property->setValue( $cache, array( 'custom' => $custom ) );
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
