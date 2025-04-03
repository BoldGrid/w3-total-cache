<?php
/**
 * File: Varnish_Flush.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Varnish_Flush
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 * phpcs:disable WordPress.DB.DirectDatabaseQuery
 */
class Varnish_Flush {
	/**
	 * Debug flag
	 *
	 * @var bool
	 */
	private $_debug = false;

	/**
	 * Varnish servers
	 *
	 * @var array
	 */
	private $_servers = array();

	/**
	 * Operation timeout
	 *
	 * @var int
	 */
	private $_timeout = 30;

	/**
	 * Advanced cache config
	 *
	 * @var Config
	 */
	private $_config = null;

	/**
	 * Array of already flushed urls
	 *
	 * @var array
	 */
	private $queued_urls = array();

	/**
	 * Flush operation requested flag
	 *
	 * @var bool
	 */
	private $flush_operation_requested = false;

	/**
	 * Initializes the Varnish purging system.
	 *
	 * Configures the object with settings for debug mode, server details, and purge timeout
	 * using the dispatcher configuration.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();

		$this->_debug   = $this->_config->get_boolean( 'varnish.debug' );
		$this->_servers = $this->_config->get_array( 'varnish.servers' );
		$this->_timeout = $this->_config->get_integer( 'timelimit.varnish_purge' );
	}

	/**
	 * Sends a PURGE request to all configured Varnish servers for a given URL.
	 *
	 * Iterates through all the servers in the configuration and attempts to send a PURGE request. Logs any errors or successes
	 * during the process.
	 *
	 * @param string $url The URL to be purged from the Varnish cache.
	 *
	 * @return bool Returns `true` if all purge requests were successful, otherwise `false`.
	 *
	 * @details
	 * - Sets the script's timeout to the configured value for long-running operations.
	 * - For each server:
	 *   - Sends a PURGE request.
	 *   - Logs errors if the response is not successful or is malformed.
	 *   - Logs successful purge requests.
	 *
	 * @throws \Exception Not explicitly thrown, but errors are logged using `_log` and returned as a `WP_Error`.
	 */
	protected function _purge( $url ) {
		@set_time_limit( $this->_timeout );
		$return = true;

		foreach ( (array) $this->_servers as $server ) {
			$response = $this->_request( $server, $url );

			if ( is_wp_error( $response ) ) {
				$this->_log( $url, sprintf( 'Unable to send request: %s.', implode( '; ', $response->get_error_messages() ) ) );
				$return = false;
			} elseif ( 200 !== $response['response']['code'] ) {
				$this->_log( $url, 'Bad response: ' . $response['response']['status'] );
				$return = false;
			} else {
				$this->_log( $url, 'PURGE OK' );
			}
		}

		return $return;
	}

	/**
	 * Sends a PURGE request to a specific Varnish server for a given URL.
	 *
	 * Uses the WordPress HTTP API when the Varnish server matches the target host. Otherwise, a custom request is sent via `fsockopen`
	 * for direct communication.
	 *
	 * @param string $varnish_server The Varnish server endpoint in the format `host:port`.
	 * @param string $url The URL to be purged.
	 *
	 * @return array|\WP_Error An array containing the response code and status if the request is successful, or a `WP_Error` object
	 *                         in case of failure.
	 *
	 * @details
	 * - Validates and parses the provided URL.
	 * - Determines whether to use WordPress HTTP API or `fsockopen` based on the
	 *   relationship between the Varnish server and the URL's host.
	 * - Logs details of the purge request, including the target server and response.
	 * - Handles timeouts, stream settings, and unexpected response formats.
	 *
	 * @throws \WP_Error Thrown if the URL format is invalid, the server connection fails, or the response is malformed.
	 */
	public function _request( $varnish_server, $url ) {
		$parse_url = @wp_parse_url( $url );

		if ( ! $parse_url || ! isset( $parse_url['host'] ) ) {
			return new \WP_Error( 'http_request_failed', 'Unrecognized URL format ' . $url );
		}

		$host        = $parse_url['host'];
		$port        = ( isset( $parse_url['port'] ) ? (int) $parse_url['port'] : 80 );
		$path        = ( ! empty( $parse_url['path'] ) ? $parse_url['path'] : '/' );
		$query       = ( isset( $parse_url['query'] ) ? $parse_url['query'] : '' );
		$request_uri = $path . ( '' !== $query ? '?' . $query : '' );

		list( $varnish_host, $varnish_port ) = Util_Content::endpoint_to_host_port( $varnish_server, 80 );

		// If url host is the same as varnish server - we can use regular WordPress http infrastructure, otherwise custom request
		// should be sent using fsockopen, since we send request to other server than specified by $url.
		if ( $host === $varnish_host && $port === $varnish_port ) {
			return Util_Http::request( $url, array( 'method' => 'PURGE' ) );
		}

		$request_headers_array = array(
			sprintf( 'PURGE %s HTTP/1.1', $request_uri ),
			sprintf( 'Host: %s', $host ),
			sprintf( 'User-Agent: %s', W3TC_POWERED_BY ),
			'Connection: close',
		);

		$request_headers = implode( "\r\n", $request_headers_array );
		$request         = $request_headers . "\r\n\r\n";

		// log what we are about to do.
		$this->_log( $url, sprintf( 'Connecting to %s ...', $varnish_host ) );
		$this->_log( $url, sprintf( 'PURGE %s HTTP/1.1', $request_uri ) );
		$this->_log( $url, sprintf( 'Host: %s', $host ) );

		$errno  = null;
		$errstr = null;
		$fp     = @fsockopen( $varnish_host, $varnish_port, $errno, $errstr, 10 );
		if ( ! $fp ) {
			return new \WP_Error( 'http_request_failed', $errno . ': ' . $errstr );
		}

		@stream_set_timeout( $fp, 60 );

		@fputs( $fp, $request );

		$response = '';
		while ( ! @feof( $fp ) ) {
			$response .= @fgets( $fp, 4096 );
		}

		@fclose( $fp );

		list( $response_headers, $contents ) = explode( "\r\n\r\n", $response, 2 );

		$matches = null;
		if ( preg_match( '~^HTTP/1.[01] (\d+)~', $response_headers, $matches ) ) {
			$code   = (int) $matches[1];
			$a      = explode( "\n", $response_headers );
			$status = ( count( $a ) >= 1 ? $a[0] : '' );
			$return = array(
				'response' => array(
					'code'   => $code,
					'status' => $status,
				),
			);

			return $return;
		}

		return new \WP_Error( 'http_request_failed', 'Unrecognized response header' . $response_headers );
	}

	/**
	 * Logs messages related to Varnish operations when debug mode is enabled.
	 *
	 * Writes log messages to a designated debug file if debugging is enabled. The log file is determined by
	 * `Util_Debug::log_filename('varnish')`.
	 *
	 * @param string $url The URL associated with the log message.
	 * @param string $msg The message to be logged.
	 *
	 * @return bool Returns `true` if logging is disabled or if the log is successfully written. Returns `false` if file writing fails.
	 *
	 * @details
	 * - Formats the log entry with a timestamp, URL, and message.
	 * - Removes unsafe characters (`<`, `>`) from the log message to avoid potential issues.
	 * - If debugging is not enabled, it exits early with a `true` value.
	 *
	 * @throws \Exception Not explicitly thrown but can fail silently if `file_put_contents` encounters an error.
	 */
	public function _log( $url, $msg ) {
		if ( $this->_debug ) {
			$data = sprintf( "[%s] [%s] %s\n", gmdate( 'r' ), $url, $msg );
			$data = strtr( $data, '<>', '' );

			$filename = Util_Debug::log_filename( 'varnish' );

			return @file_put_contents( $filename, $data, FILE_APPEND );
		}

		return true;
	}

	/**
	 * Marks the flush operation as requested.
	 *
	 * This method sets the `flush_operation_requested` flag to `true`, signaling that a flush operation should be executed later.
	 *
	 * @return bool Always returns `true`.
	 *
	 * @details
	 * - This method acts as a trigger or a placeholder for actual flush execution.
	 * - The flag `flush_operation_requested` is used internally to track the flush status.
	 */
	public function flush() {
		$this->flush_operation_requested = true;
		return true;
	}

	/**
	 * Executes the flush operation for Varnish cache.
	 *
	 * This method purges cache URLs either for a single site or across a multisite network, depending on the context (network admin
	 * or site admin).
	 *
	 * @details
	 * - If not in a network admin context:
	 *   - Purges the home URL and its mirror URLs.
	 * - If in a network admin context:
	 *   - Handles multisite scenarios, including domain-mapped sites.
	 *   - Uses `SUNRISE_LOADED` and `$wpdb->dmtable` to detect the WPMU Domain Mapping plugin.
	 * - Purges all domains and paths of non-spam, non-archived, non-deleted blogs.
	 *
	 * @return void
	 *
	 * @throws \Exception Not explicitly thrown, but relies on `_purge` for error handling.
	 */
	private function do_flush() {
		if ( ! is_network_admin() ) {
			$full_urls = array( get_home_url() . '/.*' );
			$full_urls = Util_PageUrls::complement_with_mirror_urls( $full_urls );

			foreach ( $full_urls as $url ) {
				$this->_purge( $url );
			}
		} else {
			// todo: remove. doesnt work for all caches. replace with tool to flush network.
			global $wpdb;
			$protocall = Util_Environment::is_https() ? 'https://' : 'http://';

			// If WPMU Domain Mapping plugin is installed and active.
			if ( defined( 'SUNRISE_LOADED' ) && SUNRISE_LOADED && isset( $wpdb->dmtable ) && ! empty( $wpdb->dmtable ) ) {
				$blogs = $wpdb->get_results(
					"
					SELECT {$wpdb->blogs}.domain, {$wpdb->blogs}.path, {$wpdb->dmtable}.domain AS mapped_domain
					FROM {$wpdb->dmtable}
					RIGHT JOIN {$wpdb->blogs} ON {$wpdb->dmtable}.blog_id = {$wpdb->blogs}.blog_id
					WHERE site_id = {$wpdb->siteid}
					AND spam = 0
					AND deleted = 0
					AND archived = '0'"
				);
				foreach ( $blogs as $blog ) {
					if ( ! isset( $blog->mapped_domain ) ) {
						$url = $protocall . $blog->domain . ( strlen( $blog->path ) > 1 ? '/' . trim( $blog->path, '/' ) : '' ) . '/.*';
					} else {
						$url = $protocall . $blog->mapped_domain . '/.*';
					}
					$this->_purge( $url );
				}
			} elseif ( ! Util_Environment::is_wpmu_subdomain() ) {
				$this->_purge( get_home_url() . '/.*' );
			} else {
				$blogs = $wpdb->get_results(
					"
					SELECT domain, path
					FROM {$wpdb->blogs}
					WHERE site_id = '{$wpdb->siteid}'
					AND spam = 0
					AND deleted = 0
					AND archived = '0'"
				);

				foreach ( $blogs as $blog ) {
					$url = $protocall . $blog->domain . ( strlen( $blog->path ) > 1 ? '/' . trim( $blog->path, '/' ) : '' ) . '/.*';
					$this->_purge( $url );
				}
			}
		}
	}

	/**
	 * Flushes cache related to a specific post and its associated URLs.
	 *
	 * This method generates a list of URLs related to the given post, including homepages, post pages, comment pages, author pages,
	 * term pages, archive pages, feeds, and custom purge pages. The URLs are queued for purging.
	 *
	 * @param int  $post_id The ID of the post to flush. If 0, the method attempts to detect the post ID automatically.
	 * @param bool $force   Whether to force purging the post URL even if the configuration does not explicitly allow it.
	 *
	 * @return bool True if URLs were successfully queued for flushing; false otherwise.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object used for network-level queries.
	 *
	 * @uses Util_Environment::detect_post_id() To detect the post ID if not provided.
	 * @uses Util_PageUrls::get_frontpage_urls() To retrieve the front page URLs.
	 * @uses Util_PageUrls::get_postpage_urls() To retrieve the post page URLs.
	 * @uses Util_PageUrls::get_post_urls() To retrieve the post-specific URLs.
	 * @uses Util_PageUrls::get_post_comments_urls() To retrieve the comment page URLs for the post.
	 * @uses Util_PageUrls::get_post_author_urls() To retrieve URLs related to the post's author.
	 * @uses Util_PageUrls::get_post_terms_urls() To retrieve URLs for terms associated with the post.
	 * @uses Util_PageUrls::get_daily_archive_urls() To retrieve daily archive URLs for the post.
	 * @uses Util_PageUrls::get_monthly_archive_urls() To retrieve monthly archive URLs for the post.
	 * @uses Util_PageUrls::get_yearly_archive_urls() To retrieve yearly archive URLs for the post.
	 * @uses Util_PageUrls::get_feed_urls() To retrieve global feed URLs.
	 * @uses Util_PageUrls::get_feed_comments_urls() To retrieve comment feed URLs for the post.
	 * @uses Util_PageUrls::get_feed_author_urls() To retrieve feed URLs related to the post's author.
	 * @uses Util_PageUrls::get_feed_terms_urls() To retrieve feed URLs for terms associated with the post.
	 * @uses Util_PageUrls::get_pages_urls() To retrieve URLs of custom purge pages.
	 * @uses Util_PageUrls::complement_with_mirror_urls() To add mirror URLs to the purge list.
	 * @uses apply_filters() To allow customization of the queued URLs through the 'varnish_flush_post_queued_urls' filter.
	 */
	public function flush_post( $post_id, $force ) {
		if ( ! $post_id ) {
			$post_id = Util_Environment::detect_post_id();
		}

		if ( $post_id ) {
			$full_urls = array();

			$post  = null;
			$terms = array();

			$feeds            = $this->_config->get_array( 'pgcache.purge.feed.types' );
			$limit_post_pages = $this->_config->get_integer( 'pgcache.purge.postpages_limit' );

			if ( $this->_config->get_boolean( 'pgcache.purge.terms' ) || $this->_config->get_boolean( 'varnish.pgcache.feed.terms' ) ) {
				$taxonomies = get_post_taxonomies( $post_id );
				$terms      = wp_get_post_terms( $post_id, $taxonomies );
			}

			switch ( true ) {
				case $this->_config->get_boolean( 'pgcache.purge.author' ):
				case $this->_config->get_boolean( 'pgcache.purge.archive.daily' ):
				case $this->_config->get_boolean( 'pgcache.purge.archive.monthly' ):
				case $this->_config->get_boolean( 'pgcache.purge.archive.yearly' ):
				case $this->_config->get_boolean( 'pgcache.purge.feed.author' ):
					$post = get_post( $post_id );
			}

			$front_page = get_option( 'show_on_front' );

			/**
			 * Home (Frontpage) URL
			 */
			if (
				( $this->_config->get_boolean( 'pgcache.purge.home' ) && 'posts' === $front_page ) ||
				$this->_config->get_boolean( 'pgcache.purge.front_page' )
			) {
				$full_urls = array_merge( $full_urls, Util_PageUrls::get_frontpage_urls( $limit_post_pages ) );
			}

			/**
			 * Home (Post page) URL
			 */
			if ( $this->_config->get_boolean( 'pgcache.purge.home' ) && 'posts' !== $front_page ) {
				$full_urls = array_merge( $full_urls, Util_PageUrls::get_postpage_urls( $limit_post_pages ) );
			}

			/**
			 * Post URL
			 */
			if ( $this->_config->get_boolean( 'pgcache.purge.post' ) || $force ) {
				$full_urls = array_merge( $full_urls, Util_PageUrls::get_post_urls( $post_id ) );
			}

			/**
			 * Post comments URLs
			 */
			if ( $this->_config->get_boolean( 'pgcache.purge.comments' ) && function_exists( 'get_comments_pagenum_link' ) ) {
				$full_urls = array_merge( $full_urls, Util_PageUrls::get_post_comments_urls( $post_id ) );
			}

			/**
			 * Post author URLs
			 */
			if ( $this->_config->get_boolean( 'pgcache.purge.author' ) && $post ) {
				$full_urls = array_merge( $full_urls, Util_PageUrls::get_post_author_urls( $post->post_author, $limit_post_pages ) );
			}

			/**
			 * Post terms URLs
			 */
			if ( $this->_config->get_boolean( 'pgcache.purge.terms' ) ) {
				$full_urls = array_merge( $full_urls, Util_PageUrls::get_post_terms_urls( $terms, $limit_post_pages ) );
			}

			/**
			 * Daily archive URLs
			 */
			if ( $this->_config->get_boolean( 'pgcache.purge.archive.daily' ) && $post ) {
				$full_urls = array_merge( $full_urls, Util_PageUrls::get_daily_archive_urls( $post, $limit_post_pages ) );
			}

			/**
			 * Monthly archive URLs
			 */
			if ( $this->_config->get_boolean( 'pgcache.purge.archive.monthly' ) && $post ) {
				$full_urls = array_merge( $full_urls, Util_PageUrls::get_monthly_archive_urls( $post, $limit_post_pages ) );
			}

			/**
			 * Yearly archive URLs
			 */
			if ( $this->_config->get_boolean( 'pgcache.purge.archive.yearly' ) && $post ) {
				$full_urls = array_merge( $full_urls, Util_PageUrls::get_yearly_archive_urls( $post, $limit_post_pages ) );
			}

			/**
			 * Feed URLs
			 */
			if ( $this->_config->get_boolean( 'pgcache.purge.feed.blog' ) ) {
				$full_urls = array_merge( $full_urls, Util_PageUrls::get_feed_urls( $feeds ) );
			}

			if ( $this->_config->get_boolean( 'pgcache.purge.feed.comments' ) ) {
				$full_urls = array_merge( $full_urls, Util_PageUrls::get_feed_comments_urls( $post_id, $feeds ) );
			}

			if ( $this->_config->get_boolean( 'pgcache.purge.feed.author' ) && $post ) {
				$full_urls = array_merge( $full_urls, Util_PageUrls::get_feed_author_urls( $post->post_author, $feeds ) );
			}

			if ( $this->_config->get_boolean( 'pgcache.purge.feed.terms' ) ) {
				$full_urls = array_merge( $full_urls, Util_PageUrls::get_feed_terms_urls( $terms, $feeds ) );
			}

			/**
			 * Purge selected pages
			 */
			if ( $this->_config->get_array( 'pgcache.purge.pages' ) ) {
				$pages     = $this->_config->get_array( 'pgcache.purge.pages' );
				$full_urls = array_merge( $full_urls, Util_PageUrls::get_pages_urls( $pages ) );
			}

			if ( $this->_config->get_string( 'pgcache.purge.sitemap_regex' ) ) {
				$sitemap_regex = $this->_config->get_string( 'pgcache.purge.sitemap_regex' );
				$full_urls[]   = Util_Environment::home_domain_root_url() . '/' . trim( $sitemap_regex, '^$' );
			}

			// add mirror urls.
			$full_urls = Util_PageUrls::complement_with_mirror_urls( $full_urls );

			$full_urls = apply_filters( 'varnish_flush_post_queued_urls', $full_urls );

			/**
			 * Queue flush
			 */
			if ( count( $full_urls ) ) {
				foreach ( $full_urls as $url ) {
					$this->queued_urls[ $url ] = '*';
				}
			}

			return true;
		}

		return false;
	}

	/**
	 * Flushes a specific URL from the cache.
	 *
	 * This method purges the cache for a single URL using the internal `_purge` method.
	 *
	 * @param string $url The URL to be purged.
	 *
	 * @return void
	 */
	public function flush_url( $url ) {
		$this->_purge( $url );
	}

	/**
	 * Cleans up the queued URLs and performs the flush operation if requested.
	 *
	 * If a flush operation is explicitly requested, it executes the `do_flush` method, clears the queue, and resets the flush request
	 * flag. Otherwise, it processes all queued URLs individually using `flush_url` and clears the queue afterward.
	 *
	 * @return int The count of URLs flushed. Returns 999 if a bulk flush operation was requested, or the number of individual URLs
	 *             processed otherwise.
	 */
	public function flush_post_cleanup() {
		if ( $this->flush_operation_requested ) {
			$this->do_flush();
			$count = 999;

			$this->flush_operation_requested = false;
			$this->queued_urls               = array();
		} else {
			$count = count( $this->queued_urls );
			if ( $count > 0 ) {
				foreach ( $this->queued_urls as $url => $nothing ) {
					$this->flush_url( $url );
				}

				$this->queued_urls = array();
			}
		}

		return $count;
	}
}
