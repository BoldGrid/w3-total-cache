<?php
/**
 * File: Cache.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cache
 *
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
 */
class Cache {
	/**
	 * Returns cache engine instance
	 *
	 * @param string $w3tc_engine Engine key code.
	 * @param array  $w3tc_config Configuration.
	 *
	 * @return W3_Cache_Base
	 */
	public static function instance( $w3tc_engine, $w3tc_config = array() ) {
		static $instances = array();

		// Common configuration data.
		if ( ! isset( $w3tc_config['blog_id'] ) ) {
			$w3tc_config['blog_id'] = Util_Environment::blog_id();
		}

		$instance_key = sprintf( '%s_%s', $w3tc_engine, md5( serialize( $w3tc_config ) ) );

		if ( ! isset( $instances[ $instance_key ] ) ) {
			switch ( $w3tc_engine ) {
				case 'apc':
					if ( function_exists( 'apcu_store' ) ) {
						$instances[ $instance_key ] = new Cache_Apcu( $w3tc_config );
					} elseif ( function_exists( 'apc_store' ) ) {
							$instances[ $instance_key ] = new Cache_Apc( $w3tc_config );
					}
					break;

				case 'eaccelerator':
					$instances[ $instance_key ] = new Cache_Eaccelerator( $w3tc_config );
					break;

				case 'file':
					$instances[ $instance_key ] = new Cache_File( $w3tc_config );
					break;

				case 'file_generic':
					$instances[ $instance_key ] = new Cache_File_Generic( $w3tc_config );
					break;

				case 'memcached':
					if ( class_exists( '\Memcached' ) ) {
						$instances[ $instance_key ] = new Cache_Memcached( $w3tc_config );
					} elseif ( class_exists( '\Memcache' ) ) {
						$instances[ $instance_key ] = new Cache_Memcache( $w3tc_config );
					}
					break;

				case 'nginx_memcached':
					$instances[ $instance_key ] = new Cache_Nginx_Memcached( $w3tc_config );
					break;

				case 'redis':
					$instances[ $instance_key ] = new Cache_Redis( $w3tc_config );
					break;

				case 'wincache':
					$instances[ $instance_key ] = new Cache_Wincache( $w3tc_config );
					break;

				case 'xcache':
					$instances[ $instance_key ] = new Cache_Xcache( $w3tc_config );
					break;

				default:
					trigger_error( 'Incorrect cache engine ' . esc_html( $w3tc_engine ), E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
					$instances[ $instance_key ] = new Cache_Base( $w3tc_config );
					break;
			}

			if ( ! isset( $instances[ $instance_key ] ) || ! $instances[ $instance_key ]->available() ) {
				$instances[ $instance_key ] = new Cache_Base( $w3tc_config );
			}
		}

		return $instances[ $instance_key ];
	}

	/**
	 * Returns caching engine name.
	 *
	 * @param string $w3tc_engine Engine key code.
	 * @param string $w3tc_module Module.
	 *
	 * @return string
	 */
	public static function engine_name( $w3tc_engine, $w3tc_module = '' ) {
		switch ( $w3tc_engine ) {
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
				if ( 'pgcache' === $w3tc_module ) {
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

			case 'azuremi':
				$engine_name = 'Microsoft Azure Storage (Managed Identity)';
				break;

			case 'rackspace_cdn':
				$engine_name = 'Rackspace';
				break;

			case 'bunnycdn':
				$engine_name = 'Bunny CDN';
				break;

			case '':
				$engine_name = __( 'None', 'w3-total-cache' );
				break;

			default:
				$engine_name = $w3tc_engine;
				break;
		}

		return $engine_name;
	}
}
