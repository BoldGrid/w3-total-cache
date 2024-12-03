<?php
/**
 * File: Cdnfsd_Util.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdnfsd_Util
 */
class Cdnfsd_Util {
	/**
	 * Engine name retrieval.
	 *
	 * @param string $engine The name of the engine to retrieve.
	 *
	 * @return string The name of the engine.
	 */
	public static function engine_name( $engine ) {
		return $engine;
	}

	/**
	 * Get suggested home IP address.
	 *
	 * @return string The resolved IP address or an empty string if it resolves to a local IP.
	 */
	public static function get_suggested_home_ip() {
		$ip = gethostbyname( Util_Environment::home_url_host() );

		// check if it resolves to local IP, means host cant know its real IP.
		if (
			'127.' === substr( $ip, 0, 4 ) ||
			'10.' === substr( $ip, 0, 3 ) ||
			'192.168.' === substr( $ip, 0, 8 )
		) {
			return '';
		}

		return $ip;
	}
}
