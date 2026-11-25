<?php
/**
 * WinCache backend without the WinCache extension loaded.
 *
 * @package W3TC
 */

/**
 * Cache_Wincache must return handled results, not fatal, when the extension is absent.
 *
 * GitHub #426. The extension is missing on the test runtime, which matches the
 * reported environment. Hosts that have WinCache loaded run the normal paths.
 *
 * @since 2.11.0
 */
class Cache_Wincache_Missing_Extension_Test extends WP_UnitTestCase {

	/**
	 * Builds a WinCache backend with the minimum config.
	 *
	 * @since 2.11.0
	 *
	 * @return \W3TC\Cache_Wincache
	 */
	private function cache() {
		return new \W3TC\Cache_Wincache(
			array(
				'blog_id'     => 1,
				'instance_id' => 0,
				'host'        => '',
				'module'      => 'default',
			)
		);
	}

	/**
	 * Skips when the extension is loaded, because these cases cover the missing-extension path.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		if ( function_exists( 'wincache_ucache_set' ) ) {
			$this->markTestSkipped( 'WinCache extension is loaded; missing-extension path not reachable.' );
		}
	}

	/**
	 * A read reports a miss instead of calling the missing function.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public function test_get_with_old_reports_miss() {
		$this->assertSame( array( null, false ), $this->cache()->get_with_old( 'sample', '' ) );
	}

	/**
	 * Writes, deletes, and flushes return failure.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public function test_write_paths_return_failure() {
		$cache = $this->cache();
		$value = array( 'content' => 'x' );

		$this->assertFalse( $cache->set( 'sample', $value, 60, '' ) );
		$this->assertFalse( $cache->add( 'sample', $value, 60, '' ) );
		$this->assertFalse( $cache->replace( 'sample', $value, 60, '' ) );
		$this->assertFalse( $cache->delete( 'sample', '' ) );
		$this->assertFalse( $cache->hard_delete( 'sample', '' ) );
		$this->assertFalse( $cache->flush( '' ) );
	}

	/**
	 * Counter calls return failure or zero.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public function test_counter_paths_are_handled() {
		$cache = $this->cache();

		$this->assertFalse( $cache->counter_add( 'counter', 1 ) );
		$this->assertFalse( $cache->counter_set( 'counter', 5 ) );
		$this->assertSame( 0, $cache->counter_get( 'counter' ) );
	}

	/**
	 * Ahead-generation helpers do not touch the extension.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public function test_ahead_generation_helpers_are_handled() {
		$cache = $this->cache();

		$extension = $cache->get_ahead_generation_extension( '' );

		$this->assertIsInt( $extension['key_version'] );
		$this->assertIsInt( $extension['key_version_at_creation'] );

		$cache->flush_group_after_ahead_generation( '', $extension );
		$this->assertTrue( true );
	}
}
