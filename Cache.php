<?php
/**
 * File: Cache.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * W3 Cache class
 */

/**
 * Class: Cache
 */
class Cache {
	/**
	 * Returns cache engine instance
	 *
	 * @param string $engine Engine key code.
	 * @param array  $config Configuration.
	 * @return W3_Cache_Base
	 */
	public static function instance( $engine, $config = array() ) {
		static $instances = array();

		// Common configuration data.
		if ( ! isset( $config['blog_id'] ) ) {
			$config['blog_id'] = Util_Environment::blog_id();
		}

		$instance_key = sprintf( '%s_%s', $engine, md5( serialize( $config ) ) );

		if ( ! isset( $instances[ $instance_key ] ) ) {
			switch ( $engine ) {
				case 'apc':
					if ( function_exists( 'apcu_store' ) ) {
						$instances[ $instance_key ] = new Cache_Apcu( $config );
					} else if ( function_exists( 'apc_store' ) ) {
							$instances[ $instance_key ] = new Cache_Apc( $config );
					}
					break;

				case 'eaccelerator':
					$instances[ $instance_key ] = new Cache_Eaccelerator( $config );
					break;

				case 'file':
					$instances[ $instance_key ] = new Cache_File( $config );
					break;

				case 'file_generic':
					$instances[ $instance_key ] = new Cache_File_Generic( $config );
					break;

				case 'memcached':
					if ( class_exists( '\Memcached' ) ) {
						$instances[ $instance_key ] = new Cache_Memcached( $config );
					} elseif ( class_exists( '\Memcache' ) ) {
						$instances[ $instance_key ] = new Cache_Memcache( $config );
					}
					break;

				case 'nginx_memcached':
					$instances[ $instance_key ] = new Cache_Nginx_Memcached( $config );
					break;

				case 'redis':
					$instances[ $instance_key ] = new Cache_Redis( $config );
					break;

				case 'wincache':
					$instances[ $instance_key ] = new Cache_Wincache( $config );
					break;

				case 'xcache':
					$instances[ $instance_key ] = new Cache_Xcache( $config );
					break;

				default:
					trigger_error( 'Incorrect cache engine ' . esc_html( $engine ), E_USER_WARNING );
					$instances[ $instance_key ] = new Cache_Base( $config );
					break;
			}

			if ( ! isset( $instances[ $instance_key ] ) || ! $instances[ $instance_key ]->available() ) {
				$instances[ $instance_key ] = new Cache_Base( $config );
			}
		}

		return $instances[ $instance_key ];
	}

	/**
	 * Returns caching engine name.
	 *
	 * @param string $engine Engine key code.
	 * @param string $module Module.
	 *
	 * @return string
	 */
	public static function engine_name( $engine, $module = '' ) {
		switch ( $engine ) {
			case 'memcached':
				if ( class_exists( 'Memcached' ) ) {
					$engine_name = 'Memcached';
				} else {
					$engine_name = 'Memcache';
				}
				break;

			case 'nginx_memcached':
				$engine_name = 'Nginx + Memcached';
				break;

			case 'apc':
				$engine_name = 'APC';
				break;

			case 'eaccelerator':
				$engine_name = 'EAccelerator';
				break;

			case 'redis':
				$engine_name = 'Redis';
				break;

			case 'xcache':
				$engine_name = 'XCache';
				break;

			case 'wincache':
				$engine_name = 'WinCache';
				break;

			case 'file':
				if ( 'pgcache' === $module ) {
					$engine_name = 'Disk: Basic';
				} else {
					$engine_name = 'Disk';
				}
				break;

			case 'file_generic':
				$engine_name = 'Disk: Enhanced';
				break;

			case 'ftp':
				$engine_name = 'Self-hosted / file transfer protocol upload';
				break;

			case 's3':
				$engine_name = 'Amazon Simple Storage Service (S3)';
				break;

			case 's3_compatible':
				$engine_name = 'S3 compatible';
				break;

			case 'cf':
				$engine_name = 'Amazon CloudFront';
				break;

			case 'google_drive':
				$engine_name = 'Google Drive';
				break;

			case 'highwinds':
				$engine_name = 'Highwinds';
				break;

			case 'cf2':
				$engine_name = 'Amazon CloudFront';
				break;

			case 'cloudfront':
				$engine_name = 'Amazon CloudFront';
				break;

			case 'rscf':
				$engine_name = 'Rackspace Cloud Files';
				break;

			case 'azure':
				$engine_name = 'Microsoft Azure Storage';
				break;

			case 'edgecast':
				$engine_name = 'Media Template ProCDN / EdgeCast';
				break;

			case 'att':
				$engine_name = 'AT&amp;T';
				break;

			case 'rackspace_cdn':
				$engine_name = 'Rackspace';
				break;

			case 'stackpath2':
				$engine_name = 'StackPath';
				break;

			case 'bunnycdn':
				$engine_name = 'Bunny CDN';
				break;

			case '':
				$engine_name = __( 'None', 'w3-total-cache' );
				break;

			default:
				$engine_name = $engine;
				break;
		}

		return $engine_name;
	}
}
