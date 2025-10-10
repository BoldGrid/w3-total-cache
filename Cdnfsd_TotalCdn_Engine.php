<?php
/**
 * File: Cdnfsd_TotalCdn_Engine.php
 *
 * @since   x.x.x
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdnfsd_TotalCdn_Engine
 *
 * @since x.x.x
 */
class Cdnfsd_TotalCdn_Engine {
	/**
	 * CDN configuration.
	 *
	 * @since x.x.x
	 *
	 * @var array
	 */
	private $config;

	/**
	 * Constructor.
	 *
	 * @since x.x.x
	 *
	 * @param array $config CDN configuration.
	 */
	public function __construct( array $config = array() ) {
		$this->config = $config;
	}

	/**
	 * Flush URLs.
	 *
	 * @since x.x.x
	 *
	 * @param  array $urls URLs.
	 *
	 * @throws \Exception Exception.
	 *
	 * @return void
	 */
	public function flush_urls( array $urls ) {
		if ( empty( $this->config['account_api_key'] ) ) {
			throw new \Exception( \esc_html__( 'Account API key not specified.', 'w3-total-cache' ) );
		}

		$api   = new Cdn_TotalCdn_Api( $this->config );
		$items = array();

		foreach ( $urls as $url ) {
			try {
				$result = $api->purge(
					array(
						'url' => $url,
					)
				);
			} catch ( \Exception $ex ) {
				if ( 'Validation Failure: Purge url must contain one of your hostnames' === $ex->getMessage() ) {
					throw new \Exception( \esc_html__( 'CDN site is not configured correctly: Delivery Domain must match your site domain', 'w3-total-cache' ) );
				} else {
					throw $ex;
				}
			}
		}
	}

	/**
	 * Flushes CDN completely.
	 *
	 * @since x.x.x
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

		$api = new Cdn_TotalCdn_Api( $this->config );

		try {
			$r = $api->purge_pull_zone();
		} catch ( \Exception $ex ) {
			throw new \Exception( \esc_html( \__( 'Could not purge pull zone', 'w3-total-cache' ) . '; ' . $ex->getMessage() ) );
		}
	}
}
