<?php
/**
 * File: class-w3tc-pgcache-rest-flush-test.php
 *
 * @package W3TC
 */

declare( strict_types = 1 );

use W3TC\Dispatcher;
use W3TC\PgCache_Flush;

/**
 * Page-cache flush must include the REST group when REST caching is active,
 * and must not fatal when the 2.11.0 helper is not loaded.
 */
class W3tc_PgCache_Rest_Flush_Test extends WP_UnitTestCase {

	/**
	 * Replace config values on the Dispatcher singleton.
	 *
	 * @param array $pairs Config key => value.
	 * @return void
	 */
	private function set_config( array $pairs ): void {
		$config = Dispatcher::config();
		$ref    = new \ReflectionObject( $config );
		$prop   = $ref->getProperty( '_data' );
		$prop->setAccessible( true );
		$data = $prop->getValue( $config );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		foreach ( $pairs as $key => $value ) {
			$data[ $key ] = $value;
		}
		$prop->setValue( $config, $data );
	}

	/**
	 * Mark a flush-all so cleanup walks the group list.
	 *
	 * @param PgCache_Flush $flush Flush instance.
	 * @return void
	 */
	private function request_flush_all( PgCache_Flush $flush ): void {
		$ref  = new \ReflectionClass( PgCache_Flush::class );
		$prop = $ref->getProperty( 'flush_all_operation_requested' );
		$prop->setAccessible( true );
		$prop->setValue( $flush, true );
	}

	/**
	 * Active REST caching adds the rest group.
	 *
	 * @return void
	 */
	public function test_flush_includes_rest_group_when_rest_cache_is_active(): void {
		$this->set_config(
			array(
				'plugin.type'   => 'pro',
				'pgcache.rest'  => 'cache',
				'pgcache.debug' => false,
			)
		);
		$flush = new W3tc_PgCache_Flush_Spy();
		$this->request_flush_all( $flush );

		$flush->flush_post_cleanup();

		$this->assertContains( 'rest', $flush->flushed_groups );
	}

	/**
	 * REST caching off does not add the rest group.
	 *
	 * @return void
	 */
	public function test_flush_skips_rest_group_when_rest_cache_is_inactive(): void {
		$this->set_config(
			array(
				'plugin.type'   => 'pro',
				'pgcache.rest'  => '',
				'pgcache.debug' => false,
			)
		);
		$flush = new W3tc_PgCache_Flush_Spy();
		$this->request_flush_all( $flush );

		$flush->flush_post_cleanup();

		$this->assertNotContains( 'rest', $flush->flushed_groups );
	}

	/**
	 * A Util_Environment from before 2.11.0 has no helper. Flush still runs
	 * and still includes the REST group when the config asks for it.
	 *
	 * @return void
	 */
	public function test_flush_does_not_fatal_when_rest_helper_is_missing(): void {
		$this->set_config(
			array(
				'plugin.type'   => 'pro',
				'pgcache.rest'  => 'cache',
				'pgcache.debug' => false,
			)
		);
		$flush = new W3tc_PgCache_Flush_Stale_Helper();
		$this->request_flush_all( $flush );

		$count = $flush->flush_post_cleanup();

		$this->assertSame( 999, $count );
		$this->assertContains( 'rest', $flush->flushed_groups );
	}
}

/**
 * Records groups passed to the cache flush.
 */
class W3tc_PgCache_Flush_Spy extends PgCache_Flush {

	/**
	 * Groups flushed.
	 *
	 * @var array
	 */
	public $flushed_groups = array();

	/**
	 * Avoid a real cache engine.
	 *
	 * @param string $group Cache group.
	 * @return object
	 */
	public function _get_cache( $group = '*' ) {
		$spy = $this;
		return new class( $spy ) {
			/**
			 * Flush spy.
			 *
			 * @var W3tc_PgCache_Flush_Spy
			 */
			private $spy;

			/**
			 * Constructor.
			 *
			 * @param W3tc_PgCache_Flush_Spy $spy Flush spy.
			 */
			public function __construct( W3tc_PgCache_Flush_Spy $spy ) {
				$this->spy = $spy;
			}

			/**
			 * Record the flushed group.
			 *
			 * @param string $group Cache group.
			 * @return void
			 */
			public function flush( $group ) {
				$this->spy->flushed_groups[] = $group;
			}
		};
	}
}

/**
 * Pretend the 2.11.0 helper was not loaded.
 */
class W3tc_PgCache_Flush_Stale_Helper extends W3tc_PgCache_Flush_Spy {

	/**
	 * The stale class does not define the helper.
	 *
	 * @return bool
	 */
	protected function rest_cache_helper_loaded() {
		return false;
	}
}
