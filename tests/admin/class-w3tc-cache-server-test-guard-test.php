<?php
/**
 * File: class-w3tc-cache-server-test-guard-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.1
 */

declare( strict_types = 1 );

use W3TC\Dispatcher;
use W3TC\Generic_AdminActions_Test;

/**
 * Class: W3tc_Cache_Server_Test_Guard_Test
 *
 * Destination checks for the memcached / redis "Test" admin actions.
 *
 * @since 2.11.1
 */
class W3tc_Cache_Server_Test_Guard_Test extends WP_UnitTestCase {

	/**
	 * Unsaved loopback, link-local, metadata, reserved, and socket
	 * servers are refused.
	 *
	 * @since 2.11.1
	 */
	public function test_unsaved_internal_servers_are_refused() {
		$refused = array(
			'127.0.0.1:11211',
			'127.0.0.2:6379',
			'localhost:6379',
			'169.254.169.254:11211',
			'169.254.169.254',
			'169.254.10.20:6379',
			'0.0.0.0:11211',
			'[::1]:6379',
			'::1',
			'[fe80::1]:11211',
			'[::ffff:127.0.0.1]:6379',
			'[::ffff:169.254.169.254]:6379',
			'tls://127.0.0.1:6380',
			'tcp://169.254.169.254:6379',
			'unix:/var/run/redis/redis.sock',
			'/var/run/memcached/memcached.sock',
			'[::1',
		);

		foreach ( $refused as $server ) {
			$this->assertFalse(
				Generic_AdminActions_Test::are_test_servers_allowed( array( $server ), array() ),
				'Expected refusal for ' . $server
			);
		}
	}

	/**
	 * Private-network and public servers remain testable without being
	 * saved first.
	 *
	 * @since 2.11.1
	 */
	public function test_private_and_public_servers_are_allowed() {
		$allowed = array(
			'10.0.0.5:11211',
			'172.16.4.2:6379',
			'192.168.1.10:6379',
			'tls://10.1.2.3:6380',
			'8.8.8.8:11211',
			'[2001:4860:4860::8888]:6379',
		);

		$this->assertTrue( Generic_AdminActions_Test::are_test_servers_allowed( $allowed, array() ) );
	}

	/**
	 * Saved servers keep working even when they are loopback or sockets.
	 *
	 * @since 2.11.1
	 */
	public function test_saved_servers_are_allowed() {
		$saved = array( '127.0.0.1:11211', 'unix:/var/run/redis/redis.sock', 'LOCALHOST:6379' );

		$this->assertTrue(
			Generic_AdminActions_Test::are_test_servers_allowed(
				array( '127.0.0.1:11211', ' unix:/var/run/redis/redis.sock ', 'localhost:6379', '10.0.0.5:11211' ),
				$saved
			)
		);

		$this->assertFalse(
			Generic_AdminActions_Test::are_test_servers_allowed(
				array( '127.0.0.1:11211', '127.0.0.1:6379' ),
				$saved
			),
			'An unsaved loopback port must not pass because another port is saved.'
		);

		$this->assertFalse(
			Generic_AdminActions_Test::are_test_servers_allowed(
				array( '169.254.169.254:11211' ),
				$saved
			)
		);
	}

	/**
	 * Non-array and non-string input is refused.
	 *
	 * @since 2.11.1
	 */
	public function test_malformed_input_is_refused() {
		$this->assertFalse( Generic_AdminActions_Test::are_test_servers_allowed( '10.0.0.5:11211', array() ) );
		$this->assertFalse( Generic_AdminActions_Test::are_test_servers_allowed( array( array( '10.0.0.5' ) ), array() ) );
		$this->assertFalse( Generic_AdminActions_Test::are_test_servers_allowed( array( 11211 ), array() ) );
	}

	/**
	 * Saved servers are read from every cache module for the engine.
	 *
	 * @since 2.11.1
	 */
	public function test_saved_cache_servers_reads_module_config() {
		$config            = Dispatcher::config();
		$original          = array(
			'objectcache.redis.servers' => $config->get_array( 'objectcache.redis.servers' ),
			'dbcache.redis.servers'     => $config->get_array( 'dbcache.redis.servers' ),
		);
		$original_fragment    = $config->get_array( array( 'fragmentcache', 'redis.servers' ) );

		$config->set( 'objectcache.redis.servers', array( '127.0.0.1:6380' ) );
		$config->set( 'dbcache.redis.servers', array( 'unix:/tmp/redis.sock' ) );
		$config->set( array( 'fragmentcache', 'redis.servers' ), array( '10.0.0.1:6379' ) );

		try {
			$handler = new Generic_AdminActions_Test();
			$method  = new ReflectionMethod( Generic_AdminActions_Test::class, 'saved_cache_servers' );
			if ( PHP_VERSION_ID < 80100 ) {
				$method->setAccessible( true );
			}

			$saved = $method->invoke( $handler, 'redis' );

			$this->assertContains( '127.0.0.1:6380', $saved );
			$this->assertContains( 'unix:/tmp/redis.sock', $saved );
			$this->assertContains( '10.0.0.1:6379', $saved );
			$this->assertTrue(
				Generic_AdminActions_Test::are_test_servers_allowed( array( '127.0.0.1:6380', 'unix:/tmp/redis.sock' ), $saved )
			);
			$this->assertFalse(
				Generic_AdminActions_Test::are_test_servers_allowed( array( '127.0.0.1:6390' ), $saved )
			);
		} finally {
			foreach ( $original as $key => $value ) {
				$config->set( $key, $value );
			}
			$config->set( array( 'fragmentcache', 'redis.servers' ), $original_fragment );
		}
	}
}
