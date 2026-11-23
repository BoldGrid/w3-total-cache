<?php
/**
 * File: class-w3tc-ustats-hotspot-lock-test.php
 *
 * The hotspot lock runs from advanced-cache.php, before pluggable.php
 * defines wp_rand(). The offset has to come from random_int().
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.1
 */

declare( strict_types = 1 );

use W3TC\UsageStatistics_StorageWriter;

/**
 * Class: W3tc_Ustats_Hotspot_Lock_Test
 *
 * @since 2.11.1
 */
class W3tc_Ustats_Hotspot_Lock_Test extends WP_UnitTestCase {

	/**
	 * The lock fraction is in (0, 1) and the early path does not call wp_rand().
	 *
	 * @since 2.11.1
	 */
	public function test_hotspot_lock_fraction_avoids_wp_rand() {
		$source = file_get_contents( W3TC_DIR . '/UsageStatistics_StorageWriter.php' );
		$this->assertIsString( $source );
		$this->assertStringNotContainsString( 'wp_rand(', $source );

		$method = new ReflectionMethod( UsageStatistics_StorageWriter::class, 'hotspot_lock_fraction' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$fraction = $method->invoke( null );

		$this->assertGreaterThan( 0, $fraction );
		$this->assertLessThan( 1, $fraction );
	}
}
