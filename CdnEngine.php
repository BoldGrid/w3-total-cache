<?php
/**
 * File: CdnEngine.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: CdnEngine
 *
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
 */
class CdnEngine {
	/**
	 * Returns CdnEngine_Base instance.
	 *
	 * @param string $w3tc_engine CDN engine.
	 * @param array  $w3tc_config Configuration.
	 *
	 * @return CdnEngine_Base
	 */
	public static function instance( $w3tc_engine, array $w3tc_config = array() ) {
		static $instances = array();
		$instance_key     = sprintf( '%s_%s', $w3tc_engine, md5( serialize( $w3tc_config ) ) );

		if ( ! isset( $instances[ $instance_key ] ) ) {
			switch ( $w3tc_engine ) {
				case 'azure':
					$instances[ $instance_key ] = new CdnEngine_Azure( $w3tc_config );
					break;

				case 'azuremi':
					$instances[ $instance_key ] = new CdnEngine_Azure_MI( $w3tc_config );
					break;

				case 'bunnycdn':
					$instances[ $instance_key ] = new CdnEngine_Mirror_BunnyCdn( $w3tc_config );
					break;

				case 'cf':
					$instances[ $instance_key ] = new CdnEngine_CloudFront( $w3tc_config );
					break;

				case 'cf2':
					$instances[ $instance_key ] = new CdnEngine_Mirror_CloudFront( $w3tc_config );
					break;

				case 'ftp':
					$instances[ $instance_key ] = new CdnEngine_Ftp( $w3tc_config );
					break;

				case 'google_drive':
					$instances[ $instance_key ] = new CdnEngine_GoogleDrive( $w3tc_config );
					break;

				case 'mirror':
					$instances[ $instance_key ] = new CdnEngine_Mirror( $w3tc_config );
					break;

				case 'rackspace_cdn':
					$instances[ $instance_key ] = new CdnEngine_Mirror_RackSpaceCdn( $w3tc_config );
					break;

				case 'rscf':
					$instances[ $instance_key ] = new CdnEngine_RackSpaceCloudFiles( $w3tc_config );
					break;

				case 's3':
					$instances[ $instance_key ] = new CdnEngine_S3( $w3tc_config );
					break;

				case 's3_compatible':
					$instances[ $instance_key ] = new CdnEngine_S3_Compatible( $w3tc_config );
					break;

				default:
					empty( $w3tc_engine ) || trigger_error( 'Incorrect CDN engine', E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error

					$instances[ $instance_key ] = new CdnEngine_Base();
					break;
			}
		}

		return $instances[ $instance_key ];
	}
}
