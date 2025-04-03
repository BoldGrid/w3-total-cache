<?php
/**
 * File: Cdnfsd_StackPath_Engine.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdnfsd_StackPath_Engine
 */
class Cdnfsd_StackPath_Engine {
	/**
	 * API key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Zone ID
	 *
	 * @var string
	 */
	private $zone_id;

	/**
	 * Constructor for initializing the StackPath Engine.
	 *
	 * @param array $config Configuration array containing the API key and zone ID.
	 */
	public function __construct( $config = array() ) {
		$this->api_key = $config['api_key'];
		$this->zone_id = $config['zone_id'];
	}

	/**
	 * Flushes specific URLs from the StackPath CDN cache.
	 *
	 * @param array $urls Array of URLs to be flushed from the CDN cache.
	 *
	 * @return void
	 *
	 * @throws \Exception If API key or zone ID is missing.
	 */
	public function flush_urls( $urls ) {
		if ( empty( $this->api_key ) || empty( $this->zone_id ) ) {
			throw new \Exception( \esc_html__( 'API key not specified.', 'w3-total-cache' ) );
		}

		$api = Cdn_StackPath_Api::create( $this->api_key );

		$files = array();
		foreach ( $urls as $url ) {
			$parsed       = wp_parse_url( $url );
			$relative_url = ( isset( $parsed['path'] ) ? $parsed['path'] : '/' ) .
				( isset( $parsed['query'] ) ? '?' . $parsed['query'] : '' );
			$files[]      = $relative_url;
		}
		$api->delete_site_cache( $this->zone_id, $files );
	}

	/**
	 * Flushes all content from the StackPath CDN cache.
	 *
	 * @return void
	 *
	 * @throws \Exception If API key or zone ID is missing.
	 */
	public function flush_all() {
		if ( empty( $this->api_key ) || empty( $this->zone_id ) ) {
			throw new \Exception( \esc_html__( 'API key not specified.', 'w3-total-cache' ) );
		}

		$api = Cdn_StackPath_Api::create( $this->api_key );
		$api->delete_site_cache( $this->zone_id );
	}
}
