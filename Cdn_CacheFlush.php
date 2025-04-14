<?php
/**
 * File: Cdn_CacheFlush.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_CacheFlush
 *
 * CDN cache purge object
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class Cdn_CacheFlush {
	/**
	 * Advanced cache config
	 *
	 * @var Config
	 */
	private $_config = null;


	/**
	 * Array of urls to flush
	 *
	 * @var array
	 */
	private $flush_operation_requested = false;

	/**
	 * Constructor for the Cdn_CacheFlush class.
	 *
	 * Initializes the configuration by fetching it from the Dispatcher. This constructor sets up the necessary
	 * configuration needed for the class to function correctly.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Purges all cached content.
	 *
	 * This method triggers a purge of all cached content. It sets a flag indicating that a flush operation has been
	 * requested, and returns true to confirm that the operation was initiated.
	 *
	 * @return bool True if the purge operation is initiated successfully.
	 */
	public function purge_all() {
		$this->flush_operation_requested = true;
		return true;
	}

	/**
	 * Purges the cache for a specific URL.
	 *
	 * This method purges the cache for the provided URL. It parses the URL, constructs the appropriate CDN path,
	 * and triggers the cache purge operation through the CDN service.
	 *
	 * @param string $url The URL whose cache needs to be purged.
	 *
	 * @return void
	 */
	public function purge_url( $url ) {
		$common                = Dispatcher::component( 'Cdn_Core' );
		$results               = array();
		$files                 = array();
		$parsed                = wp_parse_url( $url );
		$local_site_path       = isset( $parsed['path'] ) ? ltrim( $parsed['path'], '/' ) : '';
		$remote_path           = $common->uri_to_cdn_uri( $local_site_path );
		$files[]               = $common->build_file_descriptor( $local_site_path, $remote_path );
		$this->_flushed_urls[] = $url;
		$common->purge( $files, $results );
	}

	/**
	 * Performs cleanup actions after a post purge operation.
	 *
	 * This method is responsible for performing any necessary cleanup after a cache purge operation. It checks
	 * whether a flush operation has been requested, triggers a full CDN cache purge if necessary, and resets the
	 * relevant flags.
	 *
	 * @return int The number of items that were processed during the cleanup.
	 */
	public function purge_post_cleanup() {
		if ( $this->flush_operation_requested ) {
			$common  = Dispatcher::component( 'Cdn_Core' );
			$results = array();
			$common->purge_all( $results );

			$count = 999;

			$this->flush_operation_requested = false;
		}

		return $count;
	}
}
