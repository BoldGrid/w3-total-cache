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
			$files = glob( $dir . '/w3tc-redis-*' );

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
	 * A missing timeout is one second, same as an explicit 0.
	 */
	public function test_missing_timeout_fails_fast() {
		$cache  = new Cache_Redis(
			array(
				'blog_id'    => 1,
				'servers'    => array( '127.0.0.1:6379' ),
				'persistent' => false,
				'password'   => '',
				'dbid'       => 0,
			)
		);
		$method = new ReflectionMethod( Cache_Redis::class, 'build_connect_args' );
		$method->setAccessible( true );
		$args = $method->invoke( $cache, '127.0.0.1:6379' );

		$this->assertSame( 1.0, $args[2] );
	}

	/**
	 * The bypass file keeps the expiry after the process-local map is cleared.
	 */
	public function test_bypass_file_is_readable_after_static_reset() {
		$this->use_temp_bypass();
		Cache_Redis::mark_server_bypassed( '127.0.0.1:6390', 0 );

		$files = glob( Cache_Redis::$bypass_directory . '/w3tc-redis-bypass-*' );
		$this->assertIsArray( $files );
		$this->assertCount( 1, $files );
		$stored = (int) file_get_contents( $files[0] );
		$this->assertGreaterThan( time(), $stored );

		$property = new ReflectionProperty( Cache_Redis::class, 'bypass_until' );
		$property->setAccessible( true );
		$property->setValue( null, array() );

		$this->assertTrue( Cache_Redis::is_server_bypassed( '127.0.0.1:6390', 0 ) );
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
	 * A failed probe restores every Redis connection setting, not just servers.
	 */
	public function test_unverified_save_restores_connection_settings_as_a_unit() {
		$old = new W3tc_Redis_Config_Stub(
			array(
				'objectcache.engine'                        => 'redis',
				'objectcache.redis.servers'                 => array( '127.0.0.1:6379' ),
				'objectcache.redis.password'                => 'old-pass',
				'objectcache.redis.dbid'                    => 1,
				'objectcache.redis.verify_tls_certificates' => true,
			)
		);
		$new = new W3tc_Redis_Config_Stub(
			array(
				'objectcache.engine'                        => 'redis',
				'objectcache.redis.servers'                 => array( '127.0.0.1:6393' ),
				'objectcache.redis.password'                => 'new-pass',
				'objectcache.redis.dbid'                    => 4,
				'objectcache.redis.verify_tls_certificates' => false,
				'dbcache.engine'                            => 'file',
				'pgcache.engine'                            => 'file',
				'minify.engine'                             => 'file',
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
		$this->assertSame( array( '127.0.0.1:6379' ), $new->get_array( 'objectcache.redis.servers' ) );
		$this->assertSame( 'old-pass', $new->get_string( 'objectcache.redis.password' ) );
		$this->assertSame( 1, $new->get_integer( 'objectcache.redis.dbid' ) );
		$this->assertTrue( $new->get_boolean( 'objectcache.redis.verify_tls_certificates' ) );
	}

	/**
	 * Changing only the Redis password on the same server is probed.
	 */
	public function test_password_change_is_probed() {
		$base   = array(
			'objectcache.engine'        => 'redis',
			'objectcache.redis.servers' => array( '127.0.0.1:6379' ),
			'dbcache.engine'            => 'file',
			'pgcache.engine'            => 'file',
			'minify.engine'             => 'file',
		);
		$called = 0;

		Cache_Redis::enforce_verified_servers(
			new W3tc_Redis_Config_Stub( $base + array( 'objectcache.redis.password' => 'new' ) ),
			new W3tc_Redis_Config_Stub( $base + array( 'objectcache.redis.password' => 'old' ) ),
			function () use ( &$called ) {
				++$called;

				return true;
			}
		);

		$this->assertSame( 1, $called );
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
	 * MULTI and PIPELINE chains stay guarded, including exec().
	 */
	public function test_pipeline_chain_is_guarded() {
		if ( ! class_exists( 'Redis' ) ) {
			$this->markTestSkipped( 'set_multi() with an expiry needs the Redis extension constants.' );
		}

		$this->use_temp_bypass();
		$client                      = new W3tc_Redis_Pipeline_Client_Stub();
		Cache_Redis::$client_factory = function () use ( $client ) {
			return $client;
		};

		$cache  = $this->engine( array( '10.0.0.9:6379' ) );
		$result = $cache->set_multi(
			array(
				'a' => array( 'content' => '1' ),
				'b' => array( 'content' => '2' ),
			),
			'',
			30
		);
		$this->assertSame(
			array(
				'a' => true,
				'b' => true,
			),
			$result
		);

		$client->fail_exec = true;
		$result            = $cache->set_multi( array( 'c' => array( 'content' => '3' ) ), '', 30 );
		$this->assertSame( array( 'c' => false ), $result );
		$this->assertTrue( Cache_Redis::is_server_bypassed( '10.0.0.9:6379', 0 ) );
	}

	/**
	 * A multi() that throws leaves every queued write reported as failed.
	 */
	public function test_throwing_multi_reports_all_writes_failed() {
		if ( ! class_exists( 'Redis' ) ) {
			$this->markTestSkipped( 'set_multi() with an expiry needs the Redis extension constants.' );
		}

		$this->use_temp_bypass();
		$client                      = new W3tc_Redis_Pipeline_Client_Stub();
		$client->fail_multi          = true;
		Cache_Redis::$client_factory = function () use ( $client ) {
			return $client;
		};

		$cache  = $this->engine( array( '10.0.0.12:6379' ) );
		$result = $cache->set_multi(
			array(
				'a' => array( 'content' => '1' ),
				'b' => array( 'content' => '2' ),
			),
			'',
			30
		);

		$this->assertSame(
			array(
				'a' => false,
				'b' => false,
			),
			$result
		);
		$this->assertTrue( Cache_Redis::is_server_bypassed( '10.0.0.12:6379', 0 ) );
		$this->assertSame( 1, $client->discards );
		$this->assertFalse( $cache->set_if_maybe_equals( 'k', array( 'content' => 'v' ), array( 'content' => 'w' ) ) );
	}

	/**
	 * After a failed command, chained calls return false instead of a fatal error.
	 */
	public function test_dead_client_chains_do_not_fatal() {
		if ( ! class_exists( 'Redis' ) ) {
			$this->markTestSkipped( 'set_multi() with an expiry needs the Redis extension constants.' );
		}

		$this->use_temp_bypass();
		$client                      = new W3tc_Redis_Pipeline_Client_Stub();
		Cache_Redis::$client_factory = function () use ( $client ) {
			return $client;
		};

		$cache = $this->engine( array( '10.0.0.10:6379' ) );
		$this->assertTrue( $cache->set( 'k', array( 'content' => 'v' ), 30 ) );

		$client->fail_all = true;
		$this->assertFalse( $cache->set( 'k', array( 'content' => 'v' ), 30 ) );

		$result = $cache->set_multi( array( 'a' => array( 'content' => '1' ) ), '', 30 );
		$this->assertSame( array( 'a' => false ), $result );

		$client->fail_all = false;
		$this->assertFalse( $cache->set_if_maybe_equals( 'k', array( 'content' => 'v' ), array( 'content' => 'w' ) ) );
		$this->assertSame( array( 'z' => false ), $cache->set_multi( array( 'z' => array( 'content' => '1' ) ), '', 30 ) );
	}

	/**
	 * The save probe tries a server again even while a bypass is recorded.
	 */
	public function test_probe_ignores_stale_bypass() {
		$this->use_temp_bypass();
		Cache_Redis::mark_server_bypassed( '127.0.0.1:6392', 0 );
		Cache_Redis::$client_factory = function () {
			++$this->clients;

			return new W3tc_Redis_Client_Stub( false );
		};

		$this->assertTrue( Cache_Redis::probe_servers( array( '127.0.0.1:6392' ), '', 0, false ) );
		$this->assertSame( 1, $this->clients );
		$this->assertFalse( Cache_Redis::is_server_bypassed( '127.0.0.1:6392', 0 ) );
	}

	/**
	 * A database that cannot be selected is treated as a failed connect.
	 */
	public function test_failed_select_is_a_failed_connect() {
		$this->use_temp_bypass();
		Cache_Redis::$client_factory = function () {
			$client                = new W3tc_Redis_Client_Stub( false );
			$client->select_result = false;

			return $client;
		};

		$cache = $this->engine( array( '10.0.0.13:6379' ) );
		$this->assertFalse( $cache->set( 'k', array( 'content' => 'v' ), 30 ) );
		$this->assertTrue( Cache_Redis::is_server_bypassed( '10.0.0.13:6379', 0 ) );

		Cache_Redis::clear_server_bypass( '10.0.0.13:6379', 0 );
		$this->assertFalse( Cache_Redis::probe_servers( array( '10.0.0.13:6379' ), '', 0 ) );
	}

	/**
	 * A failed save probe leaves no bypass for the settings still in use.
	 */
	public function test_failed_probe_leaves_no_bypass() {
		$this->use_temp_bypass();
		Cache_Redis::$client_factory = function () {
			return new W3tc_Redis_Client_Stub( true );
		};

		$this->assertFalse( Cache_Redis::probe_servers( array( '127.0.0.1:6394' ), 'wrong', 0 ) );
		$this->assertFalse( Cache_Redis::is_server_bypassed( '127.0.0.1:6394', 0 ) );
		$this->assertSame( array(), glob( Cache_Redis::$bypass_directory . '/w3tc-redis-bypass-*' ) );
	}

	/**
	 * The save probe and the Test button use the module's positive timeouts; 0 fails fast.
	 */
	public function test_save_probe_uses_configured_timeouts() {
		$this->use_temp_bypass();
		$clients                     = array();
		Cache_Redis::$client_factory = function () use ( &$clients ) {
			$clients[] = new W3tc_Redis_Client_Stub( false );

			return end( $clients );
		};

		$this->assertTrue( Cache_Redis::probe_servers( array( '127.0.0.1:6395' ), '', 0, true, 5, 7 ) );
		$args = end( $clients )->connect_args;
		$this->assertSame( 5.0, $args[2] );

		if ( version_compare( (string) phpversion( 'redis' ), '3.1.3', '>=' ) ) {
			$this->assertSame( 7.0, $args[5] );
		}

		$this->assertTrue( Cache_Redis::probe_servers( array( '127.0.0.1:6395' ), '', 0, true, 0, 0 ) );
		$this->assertSame( 1.0, end( $clients )->connect_args[2] );

		$test_button = new \W3TC\Generic_AdminActions_Test();
		$test_button->redis_servers_pass_test( array( '127.0.0.1:6405' ), '', 0, true, 5, 7 );
		$args = end( $clients )->connect_args;
		$this->assertSame( 5.0, $args[2] );

		if ( version_compare( (string) phpversion( 'redis' ), '3.1.3', '>=' ) ) {
			$this->assertSame( 7.0, $args[5] );
		}
	}

	/**
	 * A delete skipped during a bypass is applied before the first read after recovery.
	 */
	public function test_delete_during_bypass_is_replayed_before_read() {
		$this->use_temp_bypass();
		$server = '127.0.0.1:6397';
		$engine = $this->engine( array( $server ) );

		Cache_Redis::$client_factory = function () {
			return new W3tc_Redis_Replay_Client_Stub( true );
		};
		$this->assertFalse( $engine->delete( 'k', 'g' ) );

		Cache_Redis::clear_server_bypass( $server, 0 );
		$recovered                   = new W3tc_Redis_Replay_Client_Stub( false );
		Cache_Redis::$client_factory = function () use ( $recovered ) {
			return $recovered;
		};
		$engine->get_with_old( 'k', 'g' );

		$this->assertContains( array( 'setex', $engine->get_item_key( 'k' ), 1, '' ), $recovered->calls );
		$this->assertSame( array(), glob( Cache_Redis::$bypass_directory . '/w3tc-redis-pending-*' ) );
	}

	/**
	 * A flush skipped during a bypass moves the group version on after recovery.
	 */
	public function test_flush_during_bypass_moves_group_version_on_recovery() {
		$this->use_temp_bypass();
		$server = '127.0.0.1:6398';
		$engine = $this->engine( array( $server ) );

		Cache_Redis::$client_factory = function () {
			return new W3tc_Redis_Replay_Client_Stub( true );
		};
		$this->assertTrue( $engine->flush( 'g' ) );

		$version_key = ( new ReflectionMethod( \W3TC\Cache_Base::class, '_get_key_version_key' ) );
		$version_key->setAccessible( true );
		$version_key = $version_key->invoke( $engine, 'g' );

		Cache_Redis::clear_server_bypass( $server, 0 );
		$recovered                    = new W3tc_Redis_Replay_Client_Stub( false );
		$recovered->get_values[ $version_key ] = '3';
		Cache_Redis::$client_factory = function () use ( $recovered ) {
			return $recovered;
		};
		$engine->get_with_old( 'k', 'g' );

		$this->assertContains( array( 'set', $version_key, 4 ), $recovered->calls );
	}

	/**
	 * A replay that fails keeps the queue and the server bypassed.
	 */
	public function test_failed_replay_keeps_queued_invalidations() {
		$this->use_temp_bypass();
		$server = '127.0.0.1:6399';
		$engine = $this->engine( array( $server ) );

		Cache_Redis::$client_factory = function () {
			return new W3tc_Redis_Replay_Client_Stub( true );
		};
		$engine->delete( 'k', 'g' );

		Cache_Redis::clear_server_bypass( $server, 0 );
		$recovered                   = new W3tc_Redis_Replay_Client_Stub( false );
		$recovered->fail_writes      = true;
		Cache_Redis::$client_factory = function () use ( $recovered ) {
			return $recovered;
		};
		$engine->get_with_old( 'k', 'g' );

		$files = glob( Cache_Redis::$bypass_directory . '/w3tc-redis-pending-*' );
		$this->assertCount( 1, $files );
		$this->assertStringContainsString( '"op":"delete"', file_get_contents( $files[0] ) );
		$this->assertTrue( Cache_Redis::is_server_bypassed( $server, 0 ) );
	}

	/**
	 * A queue past the size cap collapses to one database flush on recovery.
	 */
	public function test_oversized_queue_collapses_to_database_flush() {
		$this->use_temp_bypass();
		$server = '127.0.0.1:6400';
		$engine = $this->engine( array( $server ) );
		$path   = Cache_Redis::$bypass_directory . '/w3tc-redis-pending-' . md5( $server . '|0' );

		file_put_contents( $path, str_repeat( 'x', Cache_Redis::PENDING_MAX_BYTES + 1 ) );
		Cache_Redis::$client_factory = function () {
			return new W3tc_Redis_Replay_Client_Stub( true );
		};
		$engine->delete( 'k', 'g' );

		Cache_Redis::clear_server_bypass( $server, 0 );
		$recovered                   = new W3tc_Redis_Replay_Client_Stub( false );
		Cache_Redis::$client_factory = function () use ( $recovered ) {
			return $recovered;
		};
		$engine->get_with_old( 'k', 'g' );

		$this->assertContains( array( 'flushdb' ), $recovered->calls );
		$this->assertFileDoesNotExist( $path );
	}

	/**
	 * An expired marker file is left in place for the next write to replace.
	 */
	public function test_expired_marker_is_not_removed_on_read() {
		$this->use_temp_bypass();
		Cache_Redis::mark_server_bypassed( '127.0.0.1:6396', 0 );

		$files = glob( Cache_Redis::$bypass_directory . '/w3tc-redis-bypass-*' );
		file_put_contents( $files[0], (string) ( time() - 1 ) );
		$property = new ReflectionProperty( Cache_Redis::class, 'bypass_until' );
		$property->setAccessible( true );
		$property->setValue( null, array() );

		$this->assertFalse( Cache_Redis::is_server_bypassed( '127.0.0.1:6396', 0 ) );
		$this->assertFileExists( $files[0] );
	}

	/**
	 * A client that failed reconnects once the bypass window has ended.
	 */
	public function test_failed_client_reconnects_after_window() {
		$this->use_temp_bypass();
		$fail                        = true;
		Cache_Redis::$client_factory = function () use ( &$fail ) {
			++$this->clients;

			return new W3tc_Redis_Client_Stub( false, $fail );
		};

		$cache = $this->engine( array( '10.0.0.14:6379' ) );
		$this->assertFalse( $cache->set( 'k', array( 'content' => 'v' ), 30 ) );
		$this->assertSame( 1, $this->clients );

		$this->assertFalse( $cache->set( 'k', array( 'content' => 'v' ), 30 ) );
		$this->assertSame( 1, $this->clients );

		$fail = false;
		Cache_Redis::clear_server_bypass( '10.0.0.14:6379', 0 );
		$this->assertTrue( $cache->set( 'k', array( 'content' => 'v' ), 30 ) );
		$this->assertSame( 2, $this->clients );
	}

	/**
	 * A failed key-version read is not cached, so a reconnect reads the real version.
	 */
	public function test_failed_key_version_read_is_not_cached() {
		$this->use_temp_bypass();
		$fail                        = true;
		Cache_Redis::$client_factory = function () use ( &$fail ) {
			$client            = new W3tc_Redis_Client_Stub( false, $fail );
			$client->get_value = '5';

			return $client;
		};

		$cache = $this->engine( array( '10.0.0.15:6379' ) );
		$this->assertFalse( $cache->set( 'k', array( 'content' => 'v' ), 30 ) );

		$property = new ReflectionProperty( Cache_Redis::class, '_key_version' );
		$property->setAccessible( true );
		$this->assertArrayNotHasKey( '', $property->getValue( $cache ) );

		$fail = false;
		Cache_Redis::clear_server_bypass( '10.0.0.15:6379', 0 );
		$this->assertTrue( $cache->set( 'k', array( 'content' => 'v' ), 30 ) );
		$this->assertSame( 5, $property->getValue( $cache )[''] );
	}

	/**
	 * The save gate passes the module's TLS verification setting to the probe.
	 */
	public function test_save_probe_uses_module_tls_setting() {
		if ( version_compare( (string) phpversion( 'redis' ), '5.3.2', '<' ) ) {
			$this->markTestSkipped( 'Stream context is passed only with phpredis 5.3.2 or later.' );
		}

		$this->use_temp_bypass();
		$seen                        = array();
		Cache_Redis::$client_factory = function () use ( &$seen ) {
			return new W3tc_Redis_Client_Stub( false, false, $seen );
		};

		$new = new W3tc_Redis_Config_Stub(
			array(
				'objectcache.engine'                        => 'redis',
				'objectcache.redis.servers'                 => array( 'tls://10.0.0.11:6379' ),
				'objectcache.redis.verify_tls_certificates' => false,
				'dbcache.engine'                            => 'file',
				'pgcache.engine'                            => 'file',
				'minify.engine'                             => 'file',
			)
		);

		$reverted = Cache_Redis::enforce_verified_servers( $new, null );

		$this->assertSame( array(), $reverted );
		$this->assertNotEmpty( $seen );
		$context = end( $seen );
		$this->assertFalse( $context['stream']['verify_peer'] );
	}

	/**
	 * A queued flush moves the queuing engine's version key, not the reconnecting engine's.
	 */
	public function test_flush_replay_uses_the_queued_version_key() {
		$this->use_temp_bypass();
		$server = '127.0.0.1:6401';
		$page   = $this->engine_for( $server, 1, 'pgcache' );
		$object = $this->engine_for( $server, 2, 'objectcache' );

		Cache_Redis::$client_factory = function () {
			return new W3tc_Redis_Replay_Client_Stub( true );
		};
		$this->assertTrue( $page->flush( 'g' ) );

		$version_key = new ReflectionMethod( \W3TC\Cache_Base::class, '_get_key_version_key' );
		$version_key->setAccessible( true );
		$page_key   = $version_key->invoke( $page, 'g' );
		$object_key = $version_key->invoke( $object, 'g' );

		Cache_Redis::clear_server_bypass( $server, 0 );
		$recovered                          = new W3tc_Redis_Replay_Client_Stub( false );
		$recovered->get_values[ $page_key ] = '3';
		Cache_Redis::$client_factory        = function () use ( $recovered ) {
			return $recovered;
		};
		$object->get_with_old( 'k', 'g' );

		$this->assertContains( array( 'set', $page_key, 4 ), $recovered->calls );
		$this->assertNotContains( array( 'set', $object_key, 2 ), $recovered->calls );
	}

	/**
	 * UNWATCH is still sent when get() throws after watch().
	 */
	public function test_failed_get_after_watch_still_unwatches() {
		$this->use_temp_bypass();
		$client                      = new W3tc_Redis_Watch_Client_Stub();
		Cache_Redis::$client_factory = function () use ( $client ) {
			return $client;
		};

		$cache = $this->engine( array( '10.0.0.14:6379' ) );
		$this->assertFalse( $cache->set_if_maybe_equals( 'k', array( 'content' => 'v' ), array( 'content' => 'w' ) ) );
		$this->assertSame( array( 'watch', 'get', 'unwatch' ), $client->calls );
	}

	/**
	 * A failed usage statistics connect does not skip the live cache.
	 */
	public function test_failed_stats_connect_leaves_no_bypass() {
		$this->use_temp_bypass();
		Cache_Redis::$client_factory = function () {
			return new W3tc_Redis_Client_Stub( true );
		};

		$sources = new \W3TC\UsageStatistics_Sources_Redis(
			array(
				array(
					'servers'  => array( '127.0.0.1:6402' ),
					'password' => '',
					'dbid'     => 0,
					'name'     => 'Object Cache',
				),
			)
		);
		$sources->get_snapshot();
		$this->assertFalse( Cache_Redis::is_server_bypassed( '127.0.0.1:6402', 0 ) );

		$sources->get_summary();
		$this->assertFalse( Cache_Redis::is_server_bypassed( '127.0.0.1:6402', 0 ) );
	}

	/**
	 * Usage statistics leaves a bypass the live cache already recorded.
	 */
	public function test_stats_keep_an_existing_bypass() {
		$this->use_temp_bypass();
		Cache_Redis::mark_server_bypassed( '127.0.0.1:6403', 0 );

		$sources = new \W3TC\UsageStatistics_Sources_Redis(
			array(
				array(
					'servers'  => array( '127.0.0.1:6403' ),
					'password' => '',
					'dbid'     => 0,
					'name'     => 'Object Cache',
				),
			)
		);
		$sources->get_snapshot();
		$this->assertTrue( Cache_Redis::is_server_bypassed( '127.0.0.1:6403', 0 ) );
	}

	/**
	 * @param string $server  Server.
	 * @param int    $blog_id Blog id.
	 * @param string $module  Module.
	 * @return Cache_Redis
	 */
	private function engine_for( $server, $blog_id, $module ) {
		return new Cache_Redis(
			array(
				'blog_id'        => $blog_id,
				'module'         => $module,
				'servers'        => array( $server ),
				'persistent'     => false,
				'password'       => '',
				'dbid'           => 0,
				'timeout'        => 0,
				'retry_interval' => 0,
				'read_timeout'   => 0,
			)
		);
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
	 * @param string $key           Key.
	 * @param bool   $default_value Default.
	 * @return bool
	 */
	public function get_boolean( $key, $default_value = false ) {
		return isset( $this->data[ $key ] ) ? (bool) $this->data[ $key ] : $default_value;
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
	 * Last connect argument of each connect call.
	 *
	 * @var array|null
	 */
	private $seen;

	/**
	 * Value get() returns.
	 *
	 * @var string
	 */
	public $get_value = '1';

	/**
	 * Arguments of the last connect call.
	 *
	 * @var array
	 */
	public $connect_args = array();

	/**
	 * @param bool       $fail_connect Fail connect.
	 * @param bool       $fail_command Fail the first command.
	 * @param array|null $seen         Receives the last connect argument.
	 */
	public function __construct( $fail_connect, $fail_command = false, &$seen = null ) {
		$this->fail_connect = $fail_connect;
		$this->fail_command = $fail_command;
		$this->seen         = &$seen;
	}

	/**
	 * @param mixed ...$args Connect arguments.
	 * @return void
	 */
	public function connect( ...$args ) {
		$this->connect_args = $args;

		if ( is_array( $this->seen ) ) {
			$this->seen[] = end( $args );
		}

		if ( $this->fail_connect ) {
			throw new Exception( 'redis down' );
		}
	}

	/**
	 * @param mixed ...$args Connect arguments.
	 * @return void
	 */
	public function pconnect( ...$args ) {
		$this->connect( ...$args );
	}

	/**
	 * @param string $password Password.
	 * @return bool
	 */
	public function auth( $password ) {
		return true;
	}

	/**
	 * Value select() returns.
	 *
	 * @var bool
	 */
	public $select_result = true;

	/**
	 * @param mixed $dbid Database.
	 * @return bool
	 */
	public function select( $dbid ) {
		return $this->select_result;
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

		return $this->get_value;
	}
}

/**
 * Redis client double with MULTI and PIPELINE support.
 */
class W3tc_Redis_Pipeline_Client_Stub {
	/**
	 * Throw from exec().
	 *
	 * @var bool
	 */
	public $fail_exec = false;

	/**
	 * Throw from every command.
	 *
	 * @var bool
	 */
	public $fail_all = false;

	/**
	 * Throw from multi().
	 *
	 * @var bool
	 */
	public $fail_multi = false;

	/**
	 * Commands queued since multi().
	 *
	 * @var int|null
	 */
	private $queued = null;

	/**
	 * @return void
	 */
	public function connect() {
	}

	/**
	 * @return bool
	 */
	public function select() {
		return true;
	}

	/**
	 * @return $this
	 */
	public function multi() {
		$this->maybe_fail();

		if ( $this->fail_multi ) {
			throw new Exception( 'redis dropped' );
		}
		$this->queued = 0;

		return $this;
	}

	/**
	 * @return array|false
	 */
	public function exec() {
		$count        = (int) $this->queued;
		$this->queued = null;

		if ( $this->fail_exec ) {
			throw new Exception( 'redis dropped' );
		}

		$this->maybe_fail();

		return array_fill( 0, max( 1, $count ), true );
	}

	/**
	 * @return $this|bool
	 */
	public function setex() {
		return $this->command( true );
	}

	/**
	 * @return $this|bool
	 */
	public function set() {
		return $this->command( true );
	}

	/**
	 * @return $this|string|false
	 */
	public function get() {
		return $this->command( serialize( array( 'content' => 'v', 'key_version' => 1 ) ) );
	}

	/**
	 * @return bool
	 */
	public function watch() {
		$this->maybe_fail();

		return true;
	}

	/**
	 * @return bool
	 */
	public function unwatch() {
		return true;
	}

	/**
	 * How many times discard() was sent.
	 *
	 * @var int
	 */
	public $discards = 0;

	/**
	 * @return bool
	 */
	public function discard() {
		++$this->discards;
		$this->queued = null;

		return true;
	}

	/**
	 * @param mixed $value Value when not queued.
	 * @return $this|mixed
	 */
	private function command( $value ) {
		$this->maybe_fail();

		if ( null !== $this->queued ) {
			++$this->queued;

			return $this;
		}

		return $value;
	}

	/**
	 * @return void
	 */
	private function maybe_fail() {
		if ( $this->fail_all ) {
			throw new Exception( 'redis dropped' );
		}
	}
}

/**
 * Client double that records writes and can fail them after reconnect.
 */
class W3tc_Redis_Replay_Client_Stub {
	/**
	 * Recorded calls, each as array( method, args... ).
	 *
	 * @var array
	 */
	public $calls = array();

	/**
	 * Values returned by get(), keyed by key.
	 *
	 * @var array
	 */
	public $get_values = array();

	/**
	 * Throw from write commands.
	 *
	 * @var bool
	 */
	public $fail_writes = false;

	/**
	 * Throw on connect.
	 *
	 * @var bool
	 */
	private $fail_connect;

	/**
	 * @param bool $fail_connect Throw on connect.
	 */
	public function __construct( $fail_connect ) {
		$this->fail_connect = $fail_connect;
	}

	/**
	 * @param mixed ...$args Connect arguments.
	 * @return bool
	 */
	public function connect( ...$args ) {
		if ( $this->fail_connect ) {
			throw new Exception( 'redis down' );
		}

		return true;
	}

	/**
	 * @param mixed ...$args Connect arguments.
	 * @return bool
	 */
	public function pconnect( ...$args ) {
		return $this->connect( ...$args );
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
	 * @param string $key Key.
	 * @return mixed
	 */
	public function get( $key ) {
		$this->calls[] = array( 'get', $key );

		return isset( $this->get_values[ $key ] ) ? $this->get_values[ $key ] : false;
	}

	/**
	 * @param string $key Key.
	 * @return int
	 */
	public function ttl( $key ) {
		$this->calls[] = array( 'ttl', $key );

		return 60;
	}

	/**
	 * @param string $key   Key.
	 * @param int    $ttl   TTL.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	public function setex( $key, $ttl, $value ) {
		return $this->record( 'setex', $key, $ttl, $value );
	}

	/**
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	public function set( $key, $value ) {
		return $this->record( 'set', $key, $value );
	}

	/**
	 * @return bool
	 */
	public function flushdb() {
		return $this->record( 'flushdb' );
	}

	/**
	 * Any other command is recorded and succeeds.
	 *
	 * @param string $name Method.
	 * @param array  $args Arguments.
	 * @return bool
	 */
	public function __call( $name, $args ) {
		return $this->record( $name, ...$args );
	}

	/**
	 * @param string $name Method.
	 * @param mixed  ...$args Arguments.
	 * @return bool
	 */
	private function record( $name, ...$args ) {
		if ( $this->fail_writes && in_array( $name, array( 'setex', 'set', 'flushdb' ), true ) ) {
			throw new Exception( 'redis dropped' );
		}

		$this->calls[] = array_merge( array( $name ), $args );

		return true;
	}
}

/**
 * Client double whose get() throws after watch().
 */
class W3tc_Redis_Watch_Client_Stub {
	/**
	 * Commands sent after connect.
	 *
	 * @var string[]
	 */
	public $calls = array();

	/**
	 * @return bool
	 */
	public function connect() {
		return true;
	}

	/**
	 * @return bool
	 */
	public function select() {
		return true;
	}

	/**
	 * @return bool
	 */
	public function watch() {
		$this->calls[] = 'watch';

		return true;
	}

	/**
	 * @return string
	 */
	public function get() {
		$this->calls[] = 'get';

		throw new Exception( 'redis read timeout' );
	}

	/**
	 * @return bool
	 */
	public function unwatch() {
		$this->calls[] = 'unwatch';

		return true;
	}
}
