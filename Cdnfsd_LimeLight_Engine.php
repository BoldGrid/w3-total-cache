<?php
/**
 * File: Cdnfsd_LimeLight_Api.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdnfsd_LimeLight_Engine
 */
class Cdnfsd_LimeLight_Engine {
	/**
	 * Short name
	 *
	 * @var string
	 */
	private $short_name;

	/**
	 * Username
	 *
	 * @var string
	 */
	private $username;

	/**
	 * API key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Debug flag
	 *
	 * @var bool
	 */
	private $debug;

	/**
	 * Constructs the Cdnfsd_LimeLight_Engine object.
	 *
	 * @param array $config Configuration array containing 'short_name', 'username', 'api_key', and 'debug'.
	 *
	 * @return void
	 */
	public function __construct( $config = array() ) {
		$this->short_name = $config['short_name'];
		$this->username   = $config['username'];
		$this->api_key    = $config['api_key'];
		$this->debug      = $config['debug'];
	}

	/**
	 * Flushes the specified URLs.
	 *
	 * @param array $urls Array of URLs to be flushed.
	 *
	 * @return void
	 *
	 * @throws \Exception If credentials are not specified.
	 */
	public function flush_urls( $urls ) {
		if ( empty( $this->short_name ) || empty( $this->username ) || empty( $this->api_key ) ) {
			throw new \Exception( __( 'Credentials are not specified.', 'w3-total-cache' ) );
		}

		$api   = new Cdnfsd_LimeLight_Api( $this->short_name, $this->username, $this->api_key );
		$items = array();

		foreach ( $urls as $url ) {
			$items[] = array(
				'pattern' => $url,
				'exact'   => true,
				'evict'   => false,
				'incqs'   => false,
			);

			// max number of items per request based on API docs.
			if ( count( $items ) >= 100 ) {
				if ( $this->debug ) {
					Util_Debug::log( 'cdnfsd', wp_json_encode( $items, JSON_PRETTY_PRINT ) );
				}

				$api->purge( $items );
				$items = array();
			}
		}

		if ( $this->debug ) {
			Util_Debug::log( 'cdnfsd', wp_json_encode( $items, JSON_PRETTY_PRINT ) );
		}

		$api->purge( $items );
	}

	/**
	 * Flushes all cached content.
	 *
	 * @return void
	 *
	 * @throws \Exception If the access key is not specified.
	 */
	public function flush_all() {
		if ( empty( $this->short_name ) || empty( $this->username ) || empty( $this->api_key ) ) {
			throw new \Exception( __( 'Access key not specified.', 'w3-total-cache' ) );
		}

		$api = new Cdnfsd_LimeLight_Api( $this->short_name, $this->username, $this->api_key );
		$url = Util_Environment::home_domain_root_url() . '/*';

		$items = array(
			array(
				'pattern' => $url,
				'exact'   => false,
				'evict'   => false,
				'incqs'   => false,
			),
		);

		if ( $this->debug ) {
			Util_Debug::log( 'cdnfsd', wp_json_encode( $items, JSON_PRETTY_PRINT ) );
		}

		$api->purge( $items );
	}
}
