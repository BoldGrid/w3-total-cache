<?php
/**
 * File: Cdnfsd_BunnyCdn_Engine.php
 *
 * @since   X.X.X
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdnfsd_Bunny_Cdn_Engine
 *
 * @since X.X.X
 */
class Cdnfsd_BunnyCdn_Engine {
	/**
	 * CDN configuration.
	 *
	 * @since X.X.X
	 *
	 * @var array
	 */
	private $config;

	/**
	 * Constructor.
	 *
	 * @since X.X.X
	 *
	 * @param array $config CDN configuration.
	 */
	public function __construct( array $config = array() ) {
		$this->config = $config;
	}

	/**
	 * Flush URLs.
	 *
	 * @since X.X.X
	 *
	 * @param  array $urls URLs.
	 * @throws \Exception Exception.
	 * @return void
	 */
	public function flush_urls( array $urls ) {
		if ( empty( $this->config['account_api_key'] ) ) {
			throw new \Exception( esc_html__( 'Account API key not specified.', 'w3-total-cache' ) );
		}

		$api   = new Cdn_BunnyCdn_Api( $this->config );
		$items = array();

		foreach ( $urls as $url ) {
			$items[] = array(
				'url'       => $url,
				'recursive' => true,
			);
		}

		try {
			$api->purge( array( 'items' => $items ) );
		} catch ( \Exception $ex ) {
			if ( $ex->getMessage() == 'Validation Failure: Purge url must contain one of your hostnames' ) {
				throw new \Exception( esc_html__( 'CDN site is not configured correctly: Delivery Domain must match your site domain', 'w3-total-cache' ) );
			} else {
				throw $ex;
			}
		}
	}

	/**
	 * Flushes CDN completely.
	 *
	 * @since X.X.X
	 *
	 * @throws \Exception Exception.
	 */
	public function flush_all() {
		if ( empty( $this->config['account_api_key'] ) ) {
			throw new \Exception( \esc_html__( 'Account API key not specified.', 'w3-total-cache' ) );
		}

		if ( empty( $this->config['pull_zone_id'] ) || ! \is_int( $this->config['pull_zone_id'] ) ) {
			throw new \Exception( \esc_html__( 'Invalid pull zone id.', 'w3-total-cache' ) );
		}

		$api = new Cdn_BunnyCdn_Api( $this->config );

		try {
			$r = $api->purge_pull_zone();
		} catch ( \Exception $ex ) {
			throw new \Exception( \esc_html( \__( 'Could not purge pull zone', 'w3-total-cache' ) . '; ' . $ex->getMessage() ) );
		}
	}
}
