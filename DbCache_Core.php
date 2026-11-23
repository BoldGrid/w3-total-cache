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
		$w3tc_c      = Dispatcher::config();
		$w3tc_engine = $w3tc_c->get_string( 'dbcache.engine' );

		switch ( $w3tc_engine ) {
			case 'memcached':
				$engine_config = array(
					'servers'           => $w3tc_c->get_array( 'dbcache.memcached.servers' ),
					'persistent'        => $w3tc_c->get_boolean( 'dbcache.memcached.persistent' ),
					'aws_autodiscovery' => $w3tc_c->get_boolean( 'dbcache.memcached.aws_autodiscovery' ),
					'username'          => $w3tc_c->get_string( 'dbcache.memcached.username' ),
					'password'          => $w3tc_c->get_string( 'dbcache.memcached.password' ),
					'binary_protocol'   => $w3tc_c->get_boolean( 'dbcache.memcached.binary_protocol' ),
				);
				break;

			case 'redis':
				$engine_config = array(
					'servers'                 => $w3tc_c->get_array( 'dbcache.redis.servers' ),
					'verify_tls_certificates' => $w3tc_c->get_boolean( 'dbcache.redis.verify_tls_certificates' ),
					'persistent'              => $w3tc_c->get_boolean( 'dbcache.redis.persistent' ),
					'timeout'                 => $w3tc_c->get_integer( 'dbcache.redis.timeout' ),
					'retry_interval'          => $w3tc_c->get_integer( 'dbcache.redis.retry_interval' ),
					'read_timeout'            => $w3tc_c->get_integer( 'dbcache.redis.read_timeout' ),
					'dbid'                    => $w3tc_c->get_integer( 'dbcache.redis.dbid' ),
					'password'                => $w3tc_c->get_string( 'dbcache.redis.password' ),
				);
				break;

			default:
				$engine_config = array();
		}

		$engine_config['engine'] = $w3tc_engine;

		return $engine_config;
	}
}
