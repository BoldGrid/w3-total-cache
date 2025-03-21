<?php
/**
 * File: Util_Installed.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Util_Installed
 *
 * Checks if different server modules are enabled and installed
 */
class Util_Installed {
	/**
	 * Check for opcache
	 *
	 * @return bool
	 */
	public static function opcache() {
		return function_exists( 'opcache_reset' ) && ini_get( 'opcache.enable' );
	}

	/**
	 * Check for apc
	 *
	 * @return bool
	 */
	public static function apc() {
		return function_exists( 'apc_store' ) || function_exists( 'apcu_store' );
	}

	/**
	 * Check for apc opcache
	 *
	 * @return bool
	 */
	public static function apc_opcache() {
		return function_exists( 'apc_compile_file' ) && ini_get( 'apc.enable' );
	}

	/**
	 * Checks for opcache valid timestamps
	 *
	 * @return string|bool
	 */
	public static function is_opcache_validate_timestamps() {
		return ini_get( 'opcache.validate_timestamps' );
	}

	/**
	 * Checks for apc valid timestamps
	 *
	 * @return string|bool
	 */
	public static function is_apc_validate_timestamps() {
		return ini_get( 'apc.stat' );
	}

	/**
	 * Check for curl
	 *
	 * @return bool
	 */
	public static function curl() {
		return function_exists( 'curl_init' );
	}

	/**
	 * Check for eaccelerator
	 *
	 * @return bool
	 */
	public static function eaccelerator() {
		return function_exists( 'eaccelerator_put' );
	}

	/**
	 * Check for ftp
	 *
	 * @return bool
	 */
	public static function ftp() {
		return function_exists( 'ftp_connect' );
	}

	/**
	 * Check for memcached auth
	 *
	 * @return bool
	 */
	public static function memcached_auth() {
		static $r = null;
		if ( is_null( $r ) ) {
			if ( ! class_exists( '\Memcached' ) ) {
				$r = false;
			} else {
				$o = new \Memcached();
				$r = method_exists( $o, 'setSaslAuthData' );
			}
		}

		return $r;
	}

	/**
	 * Check for memcache/memcached
	 *
	 * @return bool
	 */
	public static function memcached() {
		return class_exists( 'Memcache' ) || class_exists( 'Memcached' );
	}

	/**
	 * Check for memcached
	 *
	 * @return bool
	 */
	public static function memcached_memcached() {
		return class_exists( 'Memcached' );
	}

	/**
	 * Check for memcached aws
	 *
	 * @return bool
	 */
	public static function memcached_aws() {
		return class_exists( '\Memcached' ) && defined( '\Memcached::OPT_CLIENT_MODE' ) && defined( '\Memcached::DYNAMIC_CLIENT_MODE' );
	}

	/**
	 * Check for memcache auth
	 *
	 * @return bool
	 */
	public static function memcache_auth() {
		static $r = null;
		if ( is_null( $r ) ) {
			if ( ! class_exists( '\Memcached' ) ) {
				$r = false;
			} else {
				$o = new \Memcached();
				$r = method_exists( $o, 'setSaslAuthData' );
			}
		}

		return $r;
	}

	/**
	 * Check for redis
	 *
	 * @return bool
	 */
	public static function redis() {
		return class_exists( 'Redis' );
	}

	/**
	 * Check for tidy
	 *
	 * @return bool
	 */
	public static function tidy() {
		return class_exists( 'tidy' );
	}

	/**
	 * Check for wincache
	 *
	 * @return bool
	 */
	public static function wincache() {
		return function_exists( 'wincache_ucache_set' );
	}

	/**
	 * Check for xcache
	 *
	 * @return bool
	 */
	public static function xcache() {
		return function_exists( 'xcache_set' );
	}

	/**
	 * Check if memcache is available
	 *
	 * @param array   $servers         Servers.
	 * @param boolean $binary_protocol Binary protocol.
	 * @param string  $username        Username.
	 * @param string  $password        Password.
	 *
	 * @return boolean
	 */
	public static function is_memcache_available( $servers, $binary_protocol, $username, $password ) {
		static $results = array();

		$key = md5( implode( '', $servers ) );

		if ( ! isset( $results[ $key ] ) ) {
			$memcached = Cache::instance(
				'memcached',
				array(
					'servers'         => $servers,
					'persistent'      => false,
					'binary_protocol' => $binary_protocol,
					'username'        => $username,
					'password'        => $password,
				)
			);

			if ( is_null( $memcached ) ) {
				return false;
			}

			$test_string = sprintf( 'test_' . md5( time() ) );
			$test_value  = array( 'content' => $test_string );

			$memcached->set( $test_string, $test_value, 60 );

			$test_value      = $memcached->get( $test_string );
			$results[ $key ] = ( ! empty( $test_value['content'] ) && $test_value['content'] === $test_string );
		}

		return $results[ $key ];
	}
}
