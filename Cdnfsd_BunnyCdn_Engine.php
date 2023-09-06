<?php
/**
 * File: Cdnfsd_BunnyCdn_Engine.php
 *
 * @since X.X.X
 *
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
	 * Configuration.
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
	 * @param array $config Configuration.
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
			throw new \Exception( __( 'Account API key not specified.', 'w3-total-cache' ) );
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
				throw new \Exception( __( 'CDN site is not configured correctly: Delivery Domain must match your site domain', 'w3-total-cache' ) );
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
			throw new \Exception( __( 'Account API key not specified.', 'w3-total-cache' ) );
		}

		$api = new Cdn_BunnyCdn_Api( $this->config );

		$items   = array();
		$items[] = array(
			'url'       => home_url( '/' ),
			'recursive' => true,
		);

		try {
			$r = $api->purge( array( 'items' => $items ) );
		} catch ( \Exception $ex ) {
			if ( $ex->getMessage() == 'Validation Failure: Purge url must contain one of your hostnames' ) {
				throw new \Exception( __( 'CDN site is not configured correctly: Delivery Domain must match your site domain', 'w3-total-cache' ) );
			} else {
				throw $ex;
			}
		}
	}
}
