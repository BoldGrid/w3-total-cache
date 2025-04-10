<?php
/**
 * File: Cdnfsd_Core.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdnfsd_Core
 *
 * Core for FSD CDN.
 */
class Cdnfsd_Core {
	/**
	 * Retrieves the engine object for the CDNFSd configuration.
	 *
	 * Initializes the appropriate engine based on the configuration settings.
	 *
	 * @return object|null The engine object or null if no engine is set.
	 *
	 * @throws \Exception If an unknown engine is specified in the configuration.
	 */
	public function get_engine() {
		static $engine_object = null;

		if ( is_null( $engine_object ) ) {
			$c      = Dispatcher::config();
			$engine = $c->get_string( 'cdnfsd.engine' );

			switch ( $engine ) {
				case 'cloudflare':
					$engine_object = null; // Extension handles everything.
					break;

				case 'transparentcdn':
					$engine_object = new Cdnfsd_TransparentCDN_Engine(
						array(
							'company_id'    => $c->get_string( 'cdnfsd.transparentcdn.company_id' ),
							'client_id'     => $c->get_string( 'cdnfsd.transparentcdn.client_id' ),
							'client_secret' => $c->get_string( 'cdnfsd.transparentcdn.client_secret' ),
						)
					);
					break;

				case 'cloudfront':
					$engine_object = new Cdnfsd_CloudFront_Engine(
						array(
							'access_key'      => $c->get_string( 'cdnfsd.cloudfront.access_key' ),
							'secret_key'      => $c->get_string( 'cdnfsd.cloudfront.secret_key' ),
							'distribution_id' => $c->get_string( 'cdnfsd.cloudfront.distribution_id' ),
						)
					);
					break;

				case 'bunnycdn':
					$engine_object = new Cdnfsd_BunnyCdn_Engine(
						array(
							'account_api_key' => $c->get_string( 'cdn.bunnycdn.account_api_key' ),
							'pull_zone_id'    => $c->get_integer( 'cdnfsd.bunnycdn.pull_zone_id' ),
						)
					);
					break;

				default:
					throw new \Exception(
						\esc_html(
							sprintf(
								// Translators: 1 Engine name.
								\__( 'Unknown engine: %1$s', 'w3-total-cache' ),
								$engine
							)
						)
					);
			}
		}

		return $engine_object;
	}
}
