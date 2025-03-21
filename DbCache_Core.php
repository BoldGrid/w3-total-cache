<?php
/**
 * File: DbCache_Core.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class DbCache_Core
 * Component of shared code used by dbcache
 */
class DbCache_Core {
	/**
	 * Retrieves the configuration for database cache usage statistics.
	 *
	 * This method fetches the cache configuration for the specified caching engine,
	 * such as Memcached or Redis, based on the system configuration.
	 *
	 * @return array The configuration details for the caching engine, including servers,
	 *               authentication details, and protocol settings.
	 */
	public function get_usage_statistics_cache_config() {
		$c      = Dispatcher::config();
		$engine = $c->get_string( 'dbcache.engine' );

		switch ( $engine ) {
			case 'memcached':
				$engine_config = array(
					'servers'           => $c->get_array( 'dbcache.memcached.servers' ),
					'persistent'        => $c->get_boolean( 'dbcache.memcached.persistent' ),
					'aws_autodiscovery' => $c->get_boolean( 'dbcache.memcached.aws_autodiscovery' ),
					'username'          => $c->get_string( 'dbcache.memcached.username' ),
					'password'          => $c->get_string( 'dbcache.memcached.password' ),
					'binary_protocol'   => $c->get_boolean( 'dbcache.memcached.binary_protocol' ),
				);
				break;

			case 'redis':
				$engine_config = array(
					'servers'                 => $c->get_array( 'dbcache.redis.servers' ),
					'verify_tls_certificates' => $c->get_boolean( 'dbcache.redis.verify_tls_certificates' ),
					'persistent'              => $c->get_boolean( 'dbcache.redis.persistent' ),
					'timeout'                 => $c->get_integer( 'dbcache.redis.timeout' ),
					'retry_interval'          => $c->get_integer( 'dbcache.redis.retry_interval' ),
					'read_timeout'            => $c->get_integer( 'dbcache.redis.read_timeout' ),
					'dbid'                    => $c->get_integer( 'dbcache.redis.dbid' ),
					'password'                => $c->get_string( 'dbcache.redis.password' ),
				);
				break;

			default:
				$engine_config = array();
		}

		$engine_config['engine'] = $engine;

		return $engine_config;
	}
}
