<?php
/**
 * File: class-w3tc-redis-fallback-test.php
 *
 * @package W3TC
 */

declare( strict_types = 1 );

use W3TC\Cache_Redis;

/**
 * Redis connect failures fail fast and bypass the server until a later probe.
 */
class W3tc_Redis_Fallback_Test extends WP_UnitTestCase {
	/**
	 * How many clients the factory built.
	 *
	 * @var int
	 */
	private $clients = 0;

	/**
	 * Reset Redis test doubles.
	 */
	public function tearDown(): void {
		$dir = Cache_Redis::$bypass_directory;

		if ( is_string( $dir ) && is_dir( $dir ) ) {
			$files = glob( $dir . '/w3tc-redis-bypass-*' );

			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					unlink( $file );
				}
			}

			rmdir( $dir );
		}

		Cache_Redis::reset_runtime_state();
		parent::tearDown();
	}

	/**
	 * A timeout of 0 becomes one second, and the given port is kept.
	 */
	public function test_fail_fast_timeout_and_configured_port() {
		$this->assertSame( 1.0, Cache_Redis::fail_fast_seconds( 0 ) );
		$this->assertSame( 2.0, Cache_Redis::fail_fast_seconds( 2 ) );

		$cache  = $this->engine( array( '127.0.0.1:6380' ), 0, 0 );
		$method = new ReflectionMethod( Cache_Redis::class, 'build_connect_args' );
		$method->setAccessible( true );
		$args = $method->invoke( $cache, '127.0.0.1:6380' );

		$this->assertSame( '127.0.0.1', $args[0] );
		$this->assertSame( 6380, $args[1] );
		$this->assertSame( 1.0, $args[2] );
	}

	/**
	 * A failed connect is skipped on the next request until the window ends.
	 */
	public function test_failed_connect_is_bypassed_until_a_later_probe() {
		$this->use_temp_bypass();
		Cache_Redis::$client_factory = function () {
			++$this->clients;

			return new W3tc_Redis_Client_Stub( true );
		};

		$first = $this->engine( array( '127.0.0.1:6390' ) );
		$this->assertFalse( $first->set( 'k', array( 'content' => 'v' ), 30 ) );
		$this->assertSame( 1, $this->clients );
		$this->assertTrue( Cache_Redis::is_server_bypassed( '127.0.0.1:6390', 0 ) );

		$second = $this->engine( array( '127.0.0.1:6390' ) );
		$this->assertFalse( $second->set( 'k', array( 'content' => 'v' ), 30 ) );
		$this->assertSame( 1, $this->clients );

		Cache_Redis::clear_server_bypass( '127.0.0.1:6390', 0 );
		Cache_Redis::$client_factory = function () {
			++$this->clients;

			return new W3tc_Redis_Client_Stub( false );
		};

		$third = $this->engine( array( '127.0.0.1:6390' ) );
		$this->assertTrue( $third->set( 'k', array( 'content' => 'v' ), 30 ) );
		$this->assertFalse( Cache_Redis::is_server_bypassed( '127.0.0.1:6390', 0 ) );
	}

	/**
	 * A command that throws after connect starts the bypass.
	 */
	public function test_command_exception_starts_bypass() {
		$this->use_temp_bypass();
		Cache_Redis::$client_factory = function () {
			return new W3tc_Redis_Client_Stub( false, true );
		};

		$cache = $this->engine( array( '10.0.0.8:6379' ) );
		$this->assertFalse( $cache->set( 'k', array( 'content' => 'v' ), 30 ) );
		$this->assertTrue( Cache_Redis::is_server_bypassed( '10.0.0.8:6379', 0 ) );
	}

	/**
	 * Saving a Redis server that fails the probe keeps the previous engine.
	 */
	public function test_unverified_save_reverts_engine_and_servers() {
		$old = new W3tc_Redis_Config_Stub(
			array(
				'objectcache.engine'        => 'file',
				'objectcache.redis.servers' => array( '127.0.0.1:6379' ),
			)
		);
		$new = new W3tc_Redis_Config_Stub(
			array(
				'objectcache.engine'        => 'redis',
				'objectcache.redis.servers' => array( '127.0.0.1:6391' ),
				'dbcache.engine'            => 'file',
				'pgcache.engine'            => 'file',
				'minify.engine'             => 'file',
			)
		);

		$reverted = Cache_Redis::enforce_verified_servers(
			$new,
			$old,
			function () {
				return false;
			}
		);

		$this->assertSame( array( 'objectcache' ), $reverted );
		$this->assertSame( 'file', $new->get_string( 'objectcache.engine' ) );
		$this->assertSame( array( '127.0.0.1:6379' ), $new->get_array( 'objectcache.redis.servers' ) );
	}

	/**
	 * An already-saved Redis server is not probed again on an unrelated save.
	 */
	public function test_unchanged_redis_servers_are_not_reprobed() {
		$data = array(
			'objectcache.engine'        => 'redis',
			'objectcache.redis.servers' => array( '127.0.0.1:6379' ),
			'dbcache.engine'            => 'file',
			'pgcache.engine'             => 'file',
			'minify.engine'             => 'file',
		);
		$called = 0;
		$reverted = Cache_Redis::enforce_verified_servers(
			new W3tc_Redis_Config_Stub( $data ),
			new W3tc_Redis_Config_Stub( $data ),
			function () use ( &$called ) {
				++$called;

				return false;
			}
		);

		$this->assertSame( array(), $reverted );
		$this->assertSame( 0, $called );
	}

	/**
	 * @param string[] $servers Server list.
	 * @param int      $timeout Connect timeout.
	 * @param int      $read    Read timeout.
	 * @return Cache_Redis
	 */
	private function engine( array $servers, $timeout = 0, $read = 0 ) {
		return new Cache_Redis(
			array(
				'blog_id'        => 1,
				'servers'        => $servers,
				'persistent'     => false,
				'password'       => '',
				'dbid'           => 0,
				'timeout'        => $timeout,
				'retry_interval' => 0,
				'read_timeout'   => $read,
			)
		);
	}

	/**
	 * Point bypass files at an empty directory.
	 */
	private function use_temp_bypass() {
		$dir = sys_get_temp_dir() . '/w3tc-redis-bypass-' . getmypid();

		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0755, true );
		}

		Cache_Redis::$bypass_directory = $dir;
	}
}

/**
 * Config double for the save gate.
 */
class W3tc_Redis_Config_Stub {
	/**
	 * @var array
	 */
	private $data;

	/**
	 * @param array $data Values.
	 */
	public function __construct( array $data ) {
		$this->data = $data;
	}

	/**
	 * @param string $key Key.
	 * @return string
	 */
	public function get_string( $key ) {
		return isset( $this->data[ $key ] ) ? (string) $this->data[ $key ] : '';
	}

	/**
	 * @param string $key Key.
	 * @return array
	 */
	public function get_array( $key ) {
		return isset( $this->data[ $key ] ) ? (array) $this->data[ $key ] : array();
	}

	/**
	 * @param string $key Key.
	 * @return int
	 */
	public function get_integer( $key ) {
		return isset( $this->data[ $key ] ) ? (int) $this->data[ $key ] : 0;
	}

	/**
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public function set( $key, $value ) {
		$this->data[ $key ] = $value;
	}
}

/**
 * Redis client double.
 */
class W3tc_Redis_Client_Stub {
	/**
	 * @var bool
	 */
	private $fail_connect;

	/**
	 * @var bool
	 */
	private $fail_command;

	/**
	 * @param bool $fail_connect Fail connect.
	 * @param bool $fail_command Fail the first command.
	 */
	public function __construct( $fail_connect, $fail_command = false ) {
		$this->fail_connect = $fail_connect;
		$this->fail_command = $fail_command;
	}

	/**
	 * @return void
	 */
	public function connect() {
		if ( $this->fail_connect ) {
			throw new Exception( 'redis down' );
		}
	}

	/**
	 * @return void
	 */
	public function pconnect() {
		$this->connect();
	}

	/**
	 * @param string $password Password.
	 * @return bool
	 */
	public function auth( $password ) {
		return true;
	}

	/**
	 * @param mixed $dbid Database.
	 * @return bool
	 */
	public function select( $dbid ) {
		return true;
	}

	/**
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	public function set( $key, $value ) {
		if ( $this->fail_command ) {
			throw new Exception( 'redis dropped' );
		}

		return true;
	}

	/**
	 * @param string $key   Key.
	 * @param int    $ttl   TTL.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	public function setex( $key, $ttl, $value ) {
		return $this->set( $key, $value );
	}

	/**
	 * @param string $key Key.
	 * @return string
	 */
	public function get( $key ) {
		if ( $this->fail_command ) {
			throw new Exception( 'redis dropped' );
		}

		return '1';
	}
}
