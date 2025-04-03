<?php
/**
 * File: Cdnfsd_StackPath2_Engine.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdnfsd_StackPath2_Engine
 */
class Cdnfsd_StackPath2_Engine {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Constructs the object with the given configuration.
	 *
	 * @param array $config Configuration array for setting up the object.
	 *
	 * @return void
	 */
	public function __construct( $config = array() ) {
		$this->config = $config;
	}

	/**
	 * Flushes a list of URLs from the CDN.
	 *
	 * @param array $urls List of URLs to flush from the CDN.
	 *
	 * @return void
	 *
	 * @throws \Exception If the API key is not specified or the purge fails.
	 */
	public function flush_urls( $urls ) {
		if ( empty( $this->config['client_id'] ) ) {
			throw new \Exception( \esc_html__( 'API key not specified.', 'w3-total-cache' ) );
		}

		$api = new Cdn_StackPath2_Api( $this->config );

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
			if ( 'Validation Failure: Purge url must contain one of your hostnames' === $ex->getMessage() ) {
				throw new \Exception( \esc_html__( 'CDN site is not configured correctly: Delivery Domain must match your site domain', 'w3-total-cache' ) );
			} else {
				throw $ex;
			}
		}
	}

	/**
	 * Flushes all content from the CDN.
	 *
	 * @return void
	 *
	 * @throws \Exception If the API key is not specified or the purge fails.
	 */
	public function flush_all() {
		if ( empty( $this->config['client_id'] ) ) {
			throw new \Exception( \esc_html__( 'API key not specified.', 'w3-total-cache' ) );
		}

		$api = new Cdn_StackPath2_Api( $this->config );

		$items   = array();
		$items[] = array(
			'url'       => home_url( '/' ),
			'recursive' => true,
		);

		try {
			$r = $api->purge( array( 'items' => $items ) );
		} catch ( \Exception $ex ) {
			if ( 'Validation Failure: Purge url must contain one of your hostnames' === $ex->getMessage() ) {
				throw new \Exception( \esc_html__( 'CDN site is not configured correctly: Delivery Domain must match your site domain', 'w3-total-cache' ) );
			} else {
				throw $ex;
			}
		}
	}
}
