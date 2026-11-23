<?php
/**
 * Failed database-cache reads must not touch column info.
 *
 * @package W3TC\Tests
 */

use W3TC\DbCache_WpdbInjection_QueryCaching;

/**
 * Records cache writes from the query-caching injection.
 */
class Query_Caching_Failed_Read_Cache {
	/**
	 * Stored cache entries.
	 *
	 * @var array
	 */
	public $sets = array();

	/**
	 * Cache misses.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 *
	 * @return false
	 */
	public function get( $key, $group ) {
		unset( $key, $group );
		return false;
	}

	/**
	 * Record a cache write.
	 *
	 * @param string $key        Cache key.
	 * @param mixed  $data       Cached data.
	 * @param int    $expiration Lifetime.
	 * @param string $group      Cache group.
	 *
	 * @return void
	 */
	public function set( $key, $data, $expiration, $group ) {
		unset( $key, $expiration, $group );
		$this->sets[] = $data;
	}
}

/**
 * Database mixin that fatals the same way wpdb does on a failed result.
 */
class Query_Caching_Failed_Read_Mixin {
	/**
	 * Database is connected.
	 *
	 * @var bool
	 */
	public $ready = true;

	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	public $prefix = 'wp_';

	/**
	 * Last error.
	 *
	 * @var string
	 */
	public $last_error = '';

	/**
	 * Last query.
	 *
	 * @var string
	 */
	public $last_query = '';

	/**
	 * Last result rows.
	 *
	 * @var array
	 */
	public $last_result = array();

	/**
	 * Row count.
	 *
	 * @var int
	 */
	public $num_rows = 0;

	/**
	 * Driver result. False when the query failed.
	 *
	 * @var mixed
	 */
	public $result = false;

	/**
	 * Whether column info was read.
	 *
	 * @var bool
	 */
	public $col_info_read = false;

	/**
	 * Start the query timer.
	 *
	 * @return void
	 */
	public function timer_start() {
	}

	/**
	 * Stop the query timer.
	 *
	 * @return int
	 */
	public function timer_stop() {
		return 0;
	}

	/**
	 * Read a wpdb property.
	 *
	 * @param string $name Property name.
	 *
	 * @throws RuntimeException When column info is read from a failed result.
	 *
	 * @return mixed
	 */
	public function __get( $name ) {
		if ( 'col_info' === $name ) {
			$this->col_info_read = true;
			if ( false === $this->result ) {
				throw new RuntimeException( 'mysqli_num_fields on a failed result' );
			}
			return array( 'Field' => 'id' );
		}
		return null;
	}
}

/**
 * Next database injection.
 */
class Query_Caching_Failed_Read_Next {
	/**
	 * Mixin that receives the query outcome.
	 *
	 * @var Query_Caching_Failed_Read_Mixin
	 */
	public $mixin;

	/**
	 * Value returned by query().
	 *
	 * @var mixed
	 */
	public $return_value = false;

	/**
	 * Error stored for a failed query.
	 *
	 * @var string
	 */
	public $error = '';

	/**
	 * Execute a query.
	 *
	 * @param string $query SQL.
	 *
	 * @return mixed
	 */
	public function query( $query ) {
		$this->mixin->last_error  = $this->error;
		$this->mixin->last_query  = $query;
		$this->mixin->last_result = array();
		$this->mixin->num_rows    = false === $this->return_value ? 0 : 1;
		$this->mixin->result      = false === $this->return_value ? false : new stdClass();
		return $this->return_value;
	}
}

/**
 * Query caching injection with the request checks bypassed.
 */
class Query_Caching_Failed_Read_Injection extends DbCache_WpdbInjection_QueryCaching {
	/**
	 * Cache double.
	 *
	 * @var Query_Caching_Failed_Read_Cache
	 */
	public $fake_cache;

	/**
	 * Treat the SQL as cacheable.
	 *
	 * @param string $sql                 SQL.
	 * @param string $cache_reject_reason Reject reason.
	 *
	 * @return bool
	 */
	public function _can_cache( $sql, &$cache_reject_reason ) {
		unset( $sql, $cache_reject_reason );
		return true;
	}

	/**
	 * Return the cache double.
	 *
	 * @return Query_Caching_Failed_Read_Cache
	 */
	public function _get_cache() {
		return $this->fake_cache;
	}
}

/**
 * Tests for a failed cacheable read.
 */
class Query_Caching_Failed_Read_Test extends WP_UnitTestCase {
	/**
	 * Build the injection around the doubles.
	 *
	 * @param Query_Caching_Failed_Read_Next $next Next injection.
	 *
	 * @return array
	 */
	private function injection( $next ) {
		global $wpdb;

		$mixin               = new Query_Caching_Failed_Read_Mixin();
		$mixin->prefix       = $wpdb->prefix;
		$next->mixin         = $mixin;
		$cache               = new Query_Caching_Failed_Read_Cache();
		$caching             = new Query_Caching_Failed_Read_Injection();
		$caching->fake_cache = $cache;
		$caching->initialize_injection( $mixin, $next );

		return array( $caching, $mixin, $cache );
	}

	/**
	 * A failed read is not cached and does not read column info.
	 *
	 * @return void
	 */
	public function test_failed_read_is_not_cached() {
		global $wpdb;

		$next               = new Query_Caching_Failed_Read_Next();
		$next->return_value = false;
		$next->error        = 'Table does not exist';
		list( $caching, $mixin, $cache ) = $this->injection( $next );

		$result = $caching->query( 'SELECT * FROM ' . $wpdb->prefix . 'posts WHERE ID = 1' );

		$this->assertFalse( $result );
		$this->assertFalse( $mixin->col_info_read );
		$this->assertSame( array(), $cache->sets );
	}

	/**
	 * A successful read still stores column info.
	 *
	 * @return void
	 */
	public function test_successful_read_stores_column_info() {
		global $wpdb;

		$next               = new Query_Caching_Failed_Read_Next();
		$next->return_value = 1;
		$next->error        = '';
		list( $caching, $mixin, $cache ) = $this->injection( $next );

		$result = $caching->query( 'SELECT * FROM ' . $wpdb->prefix . 'posts WHERE ID = 1' );

		$this->assertSame( 1, $result );
		$this->assertTrue( $mixin->col_info_read );
		$this->assertCount( 1, $cache->sets );
		$this->assertSame( array( 'Field' => 'id' ), $cache->sets[0]['col_info'] );
	}
}
