<?php
/**
 * File: PgCache_ConfigLabels.php
 *
 * @package W3TC
 */

namespace W3TC;

// To support legacy updates with old add-ins.
if ( class_exists( 'PgCache_ContentGrabber' ) ) {
	return;
}

/**
 * Class PgCache_ContentGrabber
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 * phpcs:disable WordPress.Security.ValidatedSanitizedInput
 */
class PgCache_ContentGrabber {
	/**
	 * Advanced cache config
	 *
	 * @var Config
	 */
	protected $_config = null;

	/**
	 * Mobile object
	 *
	 * @var W3_Mobile
	 */
	protected $_mobile = null;

	/**
	 * Referrer object
	 *
	 * @var W3_Referrer
	 */
	protected $_referrer = null;

	/**
	 * Caching flag
	 *
	 * @var boolean
	 */
	private $_caching = false;

	/**
	 * Time start
	 *
	 * @var double
	 */
	private $_time_start = 0;

	/**
	 * Lifetime
	 *
	 * @var integer
	 */
	private $_lifetime = 0;

	/**
	 * Enhanced mode flag
	 *
	 * @var boolean
	 */
	private $_enhanced_mode = false;

	/**
	 * Debug flag
	 *
	 * @var boolean
	 */
	private $_debug = false;

	/**
	 * Request URI
	 *
	 * @var string
	 */
	private $_request_uri;

	/**
	 * Request URL fragments
	 *
	 * Filled by _preprocess_request_uri
	 * - ['host' => 'path' => , 'querystring' => ]
	 *
	 * @var array
	 */
	private $_request_url_fragments;

	/**
	 * Page key
	 *
	 * @var string
	 */
	private $_page_key = '';

	/**
	 * Page key extension
	 *
	 * @var string
	 */
	private $_page_key_extension;

	/**
	 * Shutdown buffer
	 *
	 * @var string
	 */
	private $_shutdown_buffer = '';

	/**
	 * Cache reject reason
	 *
	 * @var string
	 */
	private $cache_reject_reason = '';

	/**
	 * Process status
	 *
	 * @var string
	 */
	private $process_status = '';

	/**
	 * Output size
	 *
	 * @var int
	 */
	private $output_size = 0;

	/**
	 * Late init flag
	 *
	 * @var bool If cached page should be displayed after init
	 */
	private $_late_init = false;

	/**
	 * Late caching flag
	 *
	 * @var bool late caching
	 */
	private $_late_caching = false;

	/**
	 * Cached data
	 *
	 * @var array
	 */
	private $_cached_data = null;

	/**
	 * Old exists flag
	 *
	 * @var bool
	 */
	private $_old_exists = false;

	/**
	 * Nginx/Memcached flag
	 *
	 * @var bool Nginx memcached flag
	 */
	private $_nginx_memcached = false;

	/**
	 * Page group
	 *
	 * @var string
	 */
	private $_page_group;

	/**
	 * Constructs the PgCache_ContentGrabber instance.
	 *
	 * Initializes configuration, debug settings, and request URL fragments.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
		$this->_debug  = $this->_config->get_boolean( 'pgcache.debug' );

		$this->_request_url_fragments = array(
			'host' => Util_Environment::host_port(),
		);

		$this->_request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? filter_var( $_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL ) : '';
		$this->_lifetime     = $this->_config->get_integer( 'pgcache.lifetime' );
		$this->_late_init    = $this->_config->get_boolean( 'pgcache.late_init' );
		$this->_late_caching = $this->_config->get_boolean( 'pgcache.late_caching' );

		$engine                 = $this->_config->get_string( 'pgcache.engine' );
		$this->_enhanced_mode   = 'file_generic' === $engine;
		$this->_nginx_memcached = 'nginx_memcached' === $engine;

		if ( $this->_config->get_boolean( 'mobile.enabled' ) ) {
			$this->_mobile = Dispatcher::component( 'Mobile_UserAgent' );
		}

		if ( $this->_config->get_boolean( 'referrer.enabled' ) ) {
			$this->_referrer = Dispatcher::component( 'Mobile_Referrer' );
		}
	}

	/**
	 * Processes the page cache logic.
	 *
	 * Handles caching based on conditions and outputs the cached or generated content.
	 *
	 * @return void
	 */
	public function process() {
		$this->run_extensions_dropin();

		// Skip caching for some pages.
		switch ( true ) {
			case defined( 'DONOTCACHEPAGE' ):
				$this->process_status      = 'miss_third_party';
				$this->cache_reject_reason = 'DONOTCACHEPAGE defined';
				if ( $this->_debug ) {
					self::log( 'skip processing because of DONOTCACHEPAGE constant' );
				}
				return;

			case defined( 'DOING_AJAX' ):
				$this->process_status      = 'miss_ajax';
				$this->cache_reject_reason = 'AJAX request';
				if ( $this->_debug ) {
					self::log( 'skip processing because of AJAX constant' );
				}
				return;

			case defined( 'APP_REQUEST' ):
			case defined( 'XMLRPC_REQUEST' ):
				$this->cache_reject_reason = 'API call constant defined';
				$this->process_status      = 'miss_api_call';
				if ( $this->_debug ) {
					self::log( 'skip processing because of API call constant' );
				}
				return;

			case defined( 'DOING_CRON' ):
			case defined( 'WP_ADMIN' ):
			case ( defined( 'SHORTINIT' ) && SHORTINIT ):
				$this->cache_reject_reason = 'WP_ADMIN defined';
				$this->process_status      = 'miss_wp_admin';
				if ( $this->_debug ) {
					self::log( 'skip processing because of generic constant' );
				}
				return;
		}

		// Do page cache logic.
		if ( $this->_debug ) {
			$this->_time_start = Util_Debug::microtime();
		}

		// TODO: call modifies object state, rename method at least.
		$this->_caching = $this->_can_read_cache();
		global $w3_late_init;

		if ( $this->_debug ) {
			self::log( 'start, can_cache: ' . ( $this->_caching ? 'true' : 'false' ) . ', reject reason: ' . $this->cache_reject_reason );
		}

		$this->_page_key_extension = $this->_get_key_extension();

		if ( ! $this->_page_key_extension['cache'] ) {
			$this->_caching            = false;
			$this->cache_reject_reason = $this->_page_key_extension['cache_reject_reason'];
		}

		if ( ! empty( $_SERVER['HTTP_W3TCALWAYSCACHED'] ) ) {
			$this->_page_key_extension['alwayscached'] = true;
		}

		if ( $this->_caching && ! $this->_late_caching ) {
			$this->_cached_data = $this->_extract_cached_page( false );
			if ( $this->_cached_data ) {
				if ( $this->_late_init ) {
					$w3_late_init = true;
					return;
				} else {
					$this->process_status = 'hit';
					$this->process_cached_page_and_exit( $this->_cached_data );
					// if is passes here - exit is not possible now and will happen on init.
					return;
				}
			} else {
				$this->_late_init = false;
			}
		} else {
			$this->_late_init = false;
		}

		$w3_late_init = $this->_late_init;
		// Start output buffering.

		Util_Bus::add_ob_callback( 'pagecache', array( $this, 'ob_callback' ) );
	}

	/**
	 * Executes the extensions drop-in logic.
	 *
	 * Includes active extensions defined in the configuration.
	 *
	 * @return void
	 */
	private function run_extensions_dropin() {
		$c          = $this->_config;
		$extensions = $c->get_array( 'extensions.active' );

		$dropin = $c->get_array( 'extensions.active_dropin' );
		foreach ( $dropin as $extension => $nothing ) {
			if ( isset( $extensions[ $extension ] ) ) {
				$path     = $extensions[ $extension ];
				$filename = W3TC_EXTENSION_DIR . '/' .
					str_replace( '..', '', trim( $path, '/' ) );

				if ( file_exists( $filename ) ) {
					include_once $filename;
				}
			}
		}
	}

	/**
	 * Extracts a cached page from storage.
	 *
	 * @param bool $with_filter Whether to apply filters to the cache keys.
	 *
	 * @return array|null An array of cached page data or null if not found.
	 */
	public function _extract_cached_page( $with_filter ) {
		if ( ! empty( $this->_page_key_extension['alwayscached'] ) ) {
			return null;
		}

		$cache = $this->_get_cache( $this->_page_key_extension['group'] );

		$mobile_group   = $this->_page_key_extension['useragent'];
		$referrer_group = $this->_page_key_extension['referrer'];
		$encryption     = $this->_page_key_extension['encryption'];
		$compression    = $this->_page_key_extension['compression'];

		// Check if page is cached.
		if ( ! $this->_set_extract_page_key( $this->_page_key_extension, $with_filter ) ) {
			$data = null;
		} else {
			$data                             = $cache->get_with_old( $this->_page_key, $this->_page_group );
			list( $data, $this->_old_exists ) = $data;
		}

		// Try to get uncompressed version of cache.
		if ( $compression && ! $data ) {
			if (
				! $this->_set_extract_page_key(
					array_merge(
						$this->_page_key_extension,
						array( 'compression' => '' )
					),
					$with_filter
				)
			) {
				$data = null;
			} else {
				$data                             = $cache->get_with_old( $this->_page_key, $this->_page_group );
				list( $data, $this->_old_exists ) = $data;
				$compression                      = false;
			}
		}

		if ( ! $data ) {
			if ( $this->_debug ) {
				self::log( 'no cache entry for ' . $this->_request_url_fragments['host'] . $this->_request_uri . ' ' . $this->_page_key );
			}

			return null;
		}

		$data['compression'] = $compression;

		return $data;
	}

	/**
	 * Sets the page key and group for cache extraction.
	 *
	 * @param array $page_key_extension {
	 *     Cache key extension data.
	 *
	 *     @type string $group        The cache group.
	 *     @type string $useragent    The user agent string.
	 *     @type string $referrer     The referrer URL.
	 *     @type string $encryption   Encryption type.
	 *     @type string $compression  Compression type.
	 *     @type string $content_type The content type.
	 * }
	 * @param bool  $with_filter Whether to apply filters to the cache keys.
	 *
	 * @return bool True if the page key was set successfully, false otherwise.
	 */
	private function _set_extract_page_key( $page_key_extension, $with_filter ) {
		// set page group.
		$this->_page_group = $page_key_extension['group'];
		if ( $with_filter ) {
			// return empty value if caching should not happen.
			$this->_page_group = apply_filters(
				'w3tc_page_extract_group',
				$page_key_extension['group'],
				$this->_request_url_fragments['host'] . $this->_request_uri,
				$page_key_extension
			);

			$page_key_extension['group'] = $this->_page_group;
		}

		// set page key.
		$this->_page_key = $this->_get_page_key( $page_key_extension );

		if ( $with_filter ) {
			// return empty value if caching should not happen.
			$this->_page_key = apply_filters(
				'w3tc_page_extract_key',
				$this->_page_key,
				$page_key_extension['useragent'],
				$page_key_extension['referrer'],
				$page_key_extension['encryption'],
				$page_key_extension['compression'],
				$page_key_extension['content_type'],
				$this->_request_url_fragments['host'] . $this->_request_uri,
				$page_key_extension
			);
		}

		if ( ! empty( $this->_page_key ) ) {
			return true;
		}

		$this->caching             = false;
		$this->cache_reject_reason = 'w3tc_page_extract_key filter result forced not to cache';

		return false;
	}

	/**
	 * Processes the cached page and terminates execution.
	 *
	 * @param array $data {
	 *     Cached page data.
	 *
	 *     @type bool   $404        Whether the page is a 404 response. Defaults to false.
	 *     @type array  $headers    Headers to be sent with the response.
	 *     @type string $content    Cached page content.
	 *     @type bool   $has_dynamic Whether the page contains dynamic content.
	 *     @type int    $time       Timestamp of when the page was cached.
	 *     @type string $compression Compression type used for the cached page.
	 * }
	 *
	 * @return void
	 */
	private function process_cached_page_and_exit( $data ) {
		// Do Bad Behavior check.
		$this->_bad_behavior();

		$is_404      = isset( $data['404'] ) ? $data['404'] : false;
		$headers     = isset( $data['headers'] ) ? $data['headers'] : array();
		$content     = $data['content'];
		$has_dynamic = isset( $data['has_dynamic'] ) && $data['has_dynamic'];
		$etag        = md5( $content );

		if ( $has_dynamic ) {
			// its last modification date is now, and any compression browser wants cant be used, since its compressed now.
			$time        = time();
			$compression = $this->_page_key_extension['compression'];
		} else {
			$time        = isset( $data['time'] ) ? $data['time'] : time();
			$compression = $data['compression'];
		}

		// Send headers.
		$this->_send_headers( $is_404, $time, $etag, $compression, $headers );
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'HEAD' === $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		// parse dynamic content and compress if it's dynamic page with mfuncs.
		if ( $has_dynamic ) {
			$content = $this->_parse_dynamic( $content );
			$content = $this->_compress( $content, $compression );
		}

		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		Dispatcher::usage_statistics_apply_before_init_and_exit(
			array(
				$this,
				'w3tc_usage_statistics_of_request',
			)
		);
	}

	/**
	 * Output buffering callback for caching.
	 *
	 * @param string $buffer The current output buffer.
	 *
	 * @return string Processed buffer to be output.
	 */
	public function ob_callback( $buffer ) {
		$this->output_size = strlen( $buffer );

		if ( ! $this->_is_cacheable_content_type() ) {
			if ( $this->_debug ) {
				self::log( 'storing cached page - not a cached content' );
			}

			return $buffer;
		}

		$compression      = false;
		$has_dynamic      = $this->_has_dynamic( $buffer );
		$response_headers = $this->_get_response_headers();

		// TODO: call modifies object state, rename method at least.
		$original_can_cache = $this->_can_write_cache( $buffer, $response_headers );
		$can_cache          = apply_filters( 'w3tc_can_cache', $original_can_cache, $this, $buffer );
		if ( $can_cache !== $original_can_cache ) {
			$this->cache_reject_reason = 'Third-party plugin has modified caching activity';
		}

		if ( $this->_debug ) {
			self::log(
				'storing cached page: ' . ( $can_cache ? 'true' : 'false' ) . ' original ' .
				( $this->_caching ? ' true' : 'false' ) . ' reason ' . $this->cache_reject_reason
			);
		}

		$buffer = str_replace(
			'{w3tc_pagecache_reject_reason}',
			( '' !== $this->cache_reject_reason ? sprintf( ' (%s)', $this->cache_reject_reason ) : '' ),
			$buffer
		);

		if ( $can_cache ) {
			$buffer = $this->_maybe_save_cached_result( $buffer, $response_headers, $has_dynamic );
		} else {
			if ( $has_dynamic ) {
				// send common headers since output will be compressed.
				$compression_header = $this->_page_key_extension['compression'];
				if ( defined( 'W3TC_PAGECACHE_OUTPUT_COMPRESSION_OFF' ) ) {
					$compression_header = false;
				}

				$headers = $this->_get_common_headers( $compression_header );
				$this->_headers( $headers );
			}

			// remove cached entries if its not cached anymore.
			if ( $this->cache_reject_reason ) {
				if ( $this->_old_exists ) {
					$cache = $this->_get_cache( $this->_page_key_extension['group'] );

					$compressions_to_store = $this->_get_compressions();

					foreach ( $compressions_to_store as $_compression ) {
						$_page_key = $this->_get_page_key(
							array_merge(
								$this->_page_key_extension,
								array( 'compression' => $_compression )
							)
						);
						$cache->hard_delete( $_page_key );
					}
				}
			}
		}

		// We can't capture output in ob_callback so we use shutdown function.
		if ( $has_dynamic ) {
			$this->_shutdown_buffer = $buffer;

			$buffer = '';

			register_shutdown_function(
				array(
					$this,
					'shutdown',
				)
			);
		}

		return $buffer;
	}

	/**
	 * Handles the shutdown process for compressing and outputting the page buffer.
	 *
	 * @return void
	 */
	public function shutdown() {
		$compression = $this->_page_key_extension['compression'];

		// Parse dynamic content.
		$buffer = $this->_parse_dynamic( $this->_shutdown_buffer );

		// Compress page according to headers already set.
		$compressed_buffer = $this->_compress( $buffer, $compression );

		echo $compressed_buffer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Determines if the cache can be read for the current request.
	 *
	 * @return bool True if cache can be read, false otherwise.
	 */
	private function _can_read_cache() {
		// Don't cache in console mode.
		if ( PHP_SAPI === 'cli' ) {
			$this->cache_reject_reason = 'Console mode';
			return false;
		}

		// Skip if session defined.
		if ( defined( 'SID' ) && ! empty( SID ) ) {
			$this->cache_reject_reason = 'Session started';
			return false;
		}

		if ( ! $this->_config->get_boolean( 'pgcache.cache.ssl' ) && Util_Environment::is_https() ) {
			$this->cache_reject_reason = 'SSL caching disabled';
			$this->process_status      = 'miss_configuration';
			return false;
		}

		// Skip if posting.
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? htmlspecialchars( stripslashes( $_SERVER['REQUEST_METHOD'] ) ) : '';

		if ( in_array( strtoupper( $request_method ), array( 'DELETE', 'PUT', 'OPTIONS', 'TRACE', 'CONNECT', 'POST' ), true ) ) {
			$this->cache_reject_reason = sprintf( 'Requested method is %s', $request_method );
			return false;
		}

		// Skip if HEAD request..
		if (
			isset( $_SERVER['REQUEST_METHOD'] ) &&
			strtoupper( $_SERVER['REQUEST_METHOD'] ) === 'HEAD' &&
			( $this->_enhanced_mode || $this->_config->get_boolean( 'pgcache.reject.request_head' ) )
		) {
			$this->cache_reject_reason = 'Requested method is HEAD';
			return false;
		}

		// Skip if there is query in the request uri.
		$this->_preprocess_request_uri();

		if ( ! empty( $this->_request_url_fragments['querystring'] ) ) {
			$should_reject_qs = (
				! $this->_config->get_boolean( 'pgcache.cache.query' ) ||
				'file_generic' === $this->_config->get_string( 'pgcache.engine' )
			);

			if (
				$should_reject_qs &&
				'cache' === $this->_config->get_string( 'pgcache.rest' ) &&
				Util_Environment::is_rest_request( $this->_request_uri )
			) {
				$should_reject_qs = false;
			}

			if ( $should_reject_qs ) {
				$this->cache_reject_reason = 'Requested URI contains query';
				$this->process_status      = 'miss_query_string';
				return false;
			}
		}

		// Check request URI.
		if ( ! $this->_passed_accept_files() && ! $this->_passed_reject_uri() ) {
			$this->cache_reject_reason = 'Requested URI is rejected';
			$this->process_status      = 'miss_configuration';
			return false;
		}

		// Check User Agent.
		if ( ! $this->_check_ua() ) {
			$this->cache_reject_reason = 'User agent is rejected';
			if ( ! empty( Util_Request::get_string( 'w3tc_rewrite_test' ) ) ) {
				// special common case - w3tc_rewrite_test check request.
				$this->process_status = 'miss_wp_admin';
			} else {
				$this->process_status = 'miss_configuration';
			}

			return false;
		}

		// Check WordPress cookies.
		if ( ! $this->_check_cookies() ) {
			$this->cache_reject_reason = 'Cookie is rejected';
			$this->process_status      = 'miss_configuration';
			return false;
		}

		// Skip if user is logged in or user role is logged in.
		if ( $this->_config->get_boolean( 'pgcache.reject.logged' ) ) {
			if ( ! $this->_check_logged_in() ) {
				$this->cache_reject_reason = 'User is logged in';
				$this->process_status      = 'miss_logged_in';
				return false;
			}
		} elseif ( ! $this->_check_logged_in_role_allowed() ) {
			$this->cache_reject_reason = 'Rejected user role is logged in';
			$this->process_status      = 'miss_logged_in';
			return false;
		}

		return true;
	}

	/**
	 * Determines if the cache can be written for the given buffer and response headers.
	 *
	 * @param string $buffer The content buffer to potentially cache.
	 * @param array  $response_headers {
	 *     Response headers from the current request.
	 *
	 *     @type array $kv Key-value pairs of response headers.
	 * }
	 *
	 * @return bool True if cache can be written, false otherwise.
	 */
	private function _can_write_cache( $buffer, $response_headers ) {
		// Skip if caching is disabled.
		if ( ! $this->_caching ) {
			return false;
		}

		// Check for DONOTCACHEPAGE constant.
		if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
			$this->cache_reject_reason = 'DONOTCACHEPAGE constant is defined';
			$this->process_status      = 'miss_third_party';
			return false;
		}

		if ( 'cache' !== $this->_config->get_string( 'pgcache.rest' ) ) {
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				$this->cache_reject_reason = 'REST request';
				$this->process_status      = 'miss_api_call';
				return false;
			}
		}

		// Don't cache 404 pages.
		if ( ! $this->_config->get_boolean( 'pgcache.cache.404' ) && function_exists( 'is_404' ) && is_404() ) {
			$this->cache_reject_reason = 'Page is 404';
			$this->process_status      = 'miss_404';
			return false;
		}

		// Don't cache homepage.
		if ( ! $this->_config->get_boolean( 'pgcache.cache.home' ) && function_exists( 'is_home' ) && is_home() ) {
			$this->cache_reject_reason = is_front_page() && is_home() ? 'Page is front page' : 'Page is posts page';
			$this->process_status      = 'miss_configuration';
			return false;
		}

		// Don't cache front page.
		if ( $this->_config->get_boolean( 'pgcache.reject.front_page' ) && function_exists( 'is_front_page' ) && is_front_page() && ! is_home() ) {
			$this->cache_reject_reason = 'Page is front page';
			$this->process_status      = 'miss_configuration';
			return false;
		}

		// Don't cache feed.
		if ( ! $this->_config->get_boolean( 'pgcache.cache.feed' ) && function_exists( 'is_feed' ) && is_feed() ) {
			$this->cache_reject_reason = 'Page is feed';
			$this->process_status      = 'miss_configuration';
			return false;
		}

		// Check if page contains dynamic tags.
		if ( $this->_enhanced_mode && $this->_has_dynamic( $buffer ) ) {
			$this->cache_reject_reason = 'Page contains dynamic tags (mfunc or mclude) can not be cached in enhanced mode';
			$this->process_status      = 'miss_mfunc';
			return false;
		}

		if ( ! $this->_passed_accept_files() ) {
			if ( is_single() ) {
				// Don't cache pages associated with categories.
				if ( $this->_passed_reject_categories() ) {
					$this->cache_reject_reason = 'Page associated with a rejected category';
					$this->process_status      = 'miss_configuration';
					return false;
				}

				// Don't cache pages that use tags.
				if ( $this->_passed_reject_tags() ) {
					$this->cache_reject_reason = 'Page using a rejected tag';
					$this->process_status      = 'miss_configuration';
					return false;
				}
			}

			// Don't cache pages by these authors.
			if ( $this->_passed_reject_authors() ) {
				$this->cache_reject_reason = 'Page written by a rejected author';
				$this->process_status      = 'miss_configuration';
				return false;
			}

			// Don't cache pages using custom fields.
			if ( $this->_passed_reject_custom_fields() ) {
				$this->cache_reject_reason = 'Page using a rejected custom field';
				$this->process_status      = 'miss_configuration';
				return false;
			}
		}

		if ( ! empty( $response_headers['kv']['content-encoding'] ) ) {
			$this->cache_reject_reason = 'Response is compressed';
			$this->process_status      = 'miss_compressed';
			return false;
		}

		if ( empty( $buffer ) && empty( $response_headers['kv']['location'] ) ) {
			$this->cache_reject_reason = 'Empty response';
			$this->process_status      = 'miss_empty_response';
			return false;
		}

		if ( isset( $response_headers['kv']['location'] ) ) {
			// dont cache query-string normalization redirects (e.g. from wp core) when cache key is normalized,
			// since that cause redirect loop.

			if (
				$this->_get_page_key( $this->_page_key_extension ) === $this->_get_page_key(
					$this->_page_key_extension,
					$response_headers['kv']['location']
				)
			) {
				$this->cache_reject_reason = 'Normalization redirect';
				$this->process_status      = 'miss_normalization_redirect';
				return false;
			}
		}

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'HEAD' === $_SERVER['REQUEST_METHOD'] ) {
			$this->cache_reject_reason = 'HEAD request';
			$this->process_status      = 'miss_request_method';
			return;
		}

		return true;
	}

	/**
	 * Retrieves the size of cache statistics.
	 *
	 * @param int $timeout_time Timeout in seconds for the operation.
	 *
	 * @return int|null The size of the cache statistics, or null if unavailable.
	 */
	public function get_cache_stats_size( $timeout_time ) {
		$cache = $this->_get_cache();
		if ( method_exists( $cache, 'get_stats_size' ) ) {
			return $cache->get_stats_size( $timeout_time );
		}

		return null;
	}

	/**
	 * Retrieves usage statistics and configuration for the cache engine.
	 *
	 * @return array Configuration data for the cache engine.
	 */
	public function get_usage_statistics_cache_config() {
		$engine = $this->_config->get_string( 'pgcache.engine' );

		switch ( $engine ) {
			case 'memcached':
			case 'nginx_memcached':
				$engine_config = array(
					'servers'           => $this->_config->get_array( 'pgcache.memcached.servers' ),
					'persistent'        => $this->_config->get_boolean( 'pgcache.memcached.persistent' ),
					'aws_autodiscovery' => $this->_config->get_boolean( 'pgcache.memcached.aws_autodiscovery' ),
					'username'          => $this->_config->get_string( 'pgcache.memcached.username' ),
					'password'          => $this->_config->get_string( 'pgcache.memcached.password' ),
					'binary_protocol'   => $this->_config->get_boolean( 'pgcache.memcached.binary_protocol' ),
				);
				break;

			case 'redis':
				$engine_config = array(
					'servers'                 => $this->_config->get_array( 'pgcache.redis.servers' ),
					'verify_tls_certificates' => $this->_config->get_boolean( 'pgcache.redis.verify_tls_certificates' ),
					'persistent'              => $this->_config->get_boolean( 'pgcache.redis.persistent' ),
					'timeout'                 => $this->_config->get_integer( 'pgcache.redis.timeout' ),
					'retry_interval'          => $this->_config->get_integer( 'pgcache.redis.retry_interval' ),
					'read_timeout'            => $this->_config->get_integer( 'pgcache.redis.read_timeout' ),
					'dbid'                    => $this->_config->get_integer( 'pgcache.redis.dbid' ),
					'password'                => $this->_config->get_string( 'pgcache.redis.password' ),
				);
				break;

			case 'file_generic':
				$engine = 'file';
				break;

			default:
				$engine_config = array();
		}

		$engine_config['engine'] = $engine;

		return $engine_config;
	}

	/**
	 * Retrieves the cache instance for a specific group.
	 *
	 * @param string $group Cache group name. Defaults to '*'.
	 *
	 * @return mixed Cache instance.
	 */
	public function _get_cache( $group = '*' ) {
		static $caches = array();

		if ( empty( $group ) ) {
			$group = '*';
		}

		if ( empty( $caches[ $group ] ) ) {
			$engine = $this->_config->get_string( 'pgcache.engine' );

			switch ( $engine ) {
				case 'memcached':
				case 'nginx_memcached':
					$engine_config = array(
						'servers'           => $this->_config->get_array( 'pgcache.memcached.servers' ),
						'persistent'        => $this->_config->get_boolean( 'pgcache.memcached.persistent' ),
						'aws_autodiscovery' => $this->_config->get_boolean( 'pgcache.memcached.aws_autodiscovery' ),
						'username'          => $this->_config->get_string( 'pgcache.memcached.username' ),
						'password'          => $this->_config->get_string( 'pgcache.memcached.password' ),
						'binary_protocol'   => $this->_config->get_boolean( 'pgcache.memcached.binary_protocol' ),
						'host'              => Util_Environment::host(),
					);
					break;

				case 'redis':
					$engine_config = array(
						'servers'                 => $this->_config->get_array( 'pgcache.redis.servers' ),
						'verify_tls_certificates' => $this->_config->get_boolean( 'pgcache.redis.verify_tls_certificates' ),
						'persistent'              => $this->_config->get_boolean( 'pgcache.redis.persistent' ),
						'timeout'                 => $this->_config->get_integer( 'pgcache.redis.timeout' ),
						'retry_interval'          => $this->_config->get_integer( 'pgcache.redis.retry_interval' ),
						'read_timeout'            => $this->_config->get_integer( 'pgcache.redis.read_timeout' ),
						'dbid'                    => $this->_config->get_integer( 'pgcache.redis.dbid' ),
						'password'                => $this->_config->get_string( 'pgcache.redis.password' ),
					);
					break;

				case 'file':
					$engine_config = array(
						'section'         => 'page',
						'flush_parent'    => ( Util_Environment::blog_id() === 0 ),
						'locking'         => $this->_config->get_boolean( 'pgcache.file.locking' ),
						'flush_timelimit' => $this->_config->get_integer( 'timelimit.cache_flush' ),
					);
					break;

				case 'file_generic':
					if ( '*' !== $group ) {
						$engine = 'file';

						$engine_config = array(
							'section'         => 'page',
							'cache_dir'       => W3TC_CACHE_PAGE_ENHANCED_DIR . DIRECTORY_SEPARATOR . Util_Environment::host_port(),
							'flush_parent'    => ( Util_Environment::blog_id() === 0 ),
							'locking'         => $this->_config->get_boolean( 'pgcache.file.locking' ),
							'flush_timelimit' => $this->_config->get_integer( 'timelimit.cache_flush' ),
						);
						break;
					}

					if ( 0 === Util_Environment::blog_id() ) {
						$flush_dir = W3TC_CACHE_PAGE_ENHANCED_DIR;
					} else {
						$flush_dir = W3TC_CACHE_PAGE_ENHANCED_DIR . DIRECTORY_SEPARATOR . Util_Environment::host();
					}

					$engine_config = array(
						'exclude'         => array(
							'.htaccess',
						),
						'expire'          => $this->_lifetime,
						'cache_dir'       => W3TC_CACHE_PAGE_ENHANCED_DIR,
						'locking'         => $this->_config->get_boolean( 'pgcache.file.locking' ),
						'flush_timelimit' => $this->_config->get_integer( 'timelimit.cache_flush' ),
						'flush_dir'       => $flush_dir,
					);
					break;

				default:
					$engine_config = array();
			}

			$engine_config['use_expired_data'] = true;
			$engine_config['module']           = 'pgcache';
			$engine_config['host']             = '';
			$engine_config['instance_id']      = Util_Environment::instance_id();

			$caches[ $group ] = Cache::instance( $engine, $engine_config );
		}

		return $caches[ $group ];
	}

	/**
	 * Determines if the current request passes the reject URI conditions.
	 *
	 * @return bool True if the request passes the reject URI conditions, false otherwise.
	 */
	public function _passed_reject_uri() {
		$auto_reject_uri = array(
			'wp-login',
			'wp-register',
		);

		foreach ( $auto_reject_uri as $uri ) {
			if ( strstr( $this->_request_uri, $uri ) !== false ) {
				return false;
			}
		}

		$reject_uri = $this->_config->get_array( 'pgcache.reject.uri' );
		$reject_uri = array_map( array( '\W3TC\Util_Environment', 'parse_path' ), $reject_uri );

		foreach ( $reject_uri as $expr ) {
			$expr = trim( $expr );
			if ( '' !== $expr && preg_match( '~' . $expr . '~i', $this->_request_uri ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Determines if the current request passes the accept files conditions.
	 *
	 * @return bool True if the request passes the accept files conditions, false otherwise.
	 */
	public function _passed_accept_files() {
		$accept_uri = $this->_config->get_array( 'pgcache.accept.files' );
		$accept_uri = array_map( array( '\W3TC\Util_Environment', 'parse_path' ), $accept_uri );

		foreach ( $accept_uri as &$val ) {
			$val = trim( str_replace( '~', '\~', $val ) );
		}

		$accept_uri = array_filter(
			$accept_uri,
			function ( $val ) {
				return '' !== $val;
			}
		);

		if ( ! empty( $accept_uri ) && @preg_match( '~' . implode( '|', $accept_uri ) . '~i', $this->_request_uri ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Determines if the current request is rejected based on categories.
	 *
	 * @return bool True if the request is rejected due to categories, false otherwise.
	 */
	public function _passed_reject_categories() {
		$reject_categories = $this->_config->get_array( 'pgcache.reject.categories' );
		if ( ! empty( $reject_categories ) ) {
			$cats = get_the_category();
			if ( $cats ) {
				foreach ( $cats as $cat ) {
					if ( in_array( $cat->slug, $reject_categories, true ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Determines if the current request is rejected based on tags.
	 *
	 * @return bool True if the request is rejected due to tags, false otherwise.
	 */
	public function _passed_reject_tags() {
		$reject_tags = $this->_config->get_array( 'pgcache.reject.tags' );
		if ( ! empty( $reject_tags ) ) {
			$tags = get_the_tags();
			if ( $tags ) {
				foreach ( $tags as $tag ) {
					if ( in_array( $tag->slug, $reject_tags, true ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Determines if the current request is rejected based on authors.
	 *
	 * @return bool True if the request is rejected due to authors, false otherwise.
	 */
	public function _passed_reject_authors() {
		$reject_authors = $this->_config->get_array( 'pgcache.reject.authors' );
		if ( ! empty( $reject_authors ) ) {
			$author = get_the_author_meta( 'user_login' );
			if ( $author ) {
				if ( in_array( $author, $reject_authors, true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Determines if the current request is rejected based on custom fields.
	 *
	 * @return bool True if the request is rejected due to custom fields, false otherwise.
	 */
	public function _passed_reject_custom_fields() {
		$reject_custom = $this->_config->get_array( 'pgcache.reject.custom' );
		if ( empty( $reject_custom ) ) {
			return false;
		}

		foreach ( $reject_custom as &$val ) {
			$val = preg_quote( trim( $val ), '~' );
		}

		$reject_custom = implode( '|', array_filter( $reject_custom ) );
		if ( ! empty( $reject_custom ) ) {
			$customs = get_post_custom();
			if ( $customs ) {
				foreach ( $customs as $key => $value ) {
					if ( @preg_match( '~' . $reject_custom . '~i', $key . ( isset( $value[0] ) ? "={$value[0]}" : '' ) ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Validates the User-Agent against rejection rules.
	 *
	 * @return bool True if the User-Agent passes validation, false otherwise.
	 */
	public function _check_ua() {
		$uas = $this->_config->get_array( 'pgcache.reject.ua' );

		$uas = array_merge( $uas, array( W3TC_POWERED_BY ) );

		foreach ( $uas as $ua ) {
			if ( ! empty( $ua ) ) {
				if (
					isset( $_SERVER['HTTP_USER_AGENT'] ) &&
					stristr( htmlspecialchars( stripslashes( $_SERVER['HTTP_USER_AGENT'] ) ), $ua ) !== false
				) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Checks if the current request passes the cookie rejection rules.
	 *
	 * @return bool True if the request passes cookie validation, false otherwise.
	 */
	public function _check_cookies() {
		foreach ( array_keys( $_COOKIE ) as $cookie_name ) {
			if ( 'wordpress_test_cookie' === $cookie_name ) {
				continue;
			}

			if ( preg_match( '/^(wp-postpass|comment_author)/', $cookie_name ) ) {
				return false;
			}
		}

		foreach ( $this->_config->get_array( 'pgcache.reject.cookie' ) as $reject_cookie ) {
			if ( ! empty( $reject_cookie ) ) {
				foreach ( array_keys( $_COOKIE ) as $cookie_name ) {
					if ( strstr( $cookie_name, $reject_cookie ) !== false ) {
						return false;
					}
				}
			}
		}

		return true;
	}

	/**
	 * Checks if the user is logged in and rejects the request if true.
	 *
	 * @return bool True if the user is not logged in, false otherwise.
	 */
	public function _check_logged_in() {
		foreach ( array_keys( $_COOKIE ) as $cookie_name ) {
			if ( strpos( $cookie_name, 'wordpress_logged_in' ) === 0 ) {
				return false;
			}
		}

		return true;
	}


	/**
	 * Validates if a logged-in user's role is allowed.
	 *
	 * @return bool True if the logged-in user's role is allowed, false otherwise.
	 */
	public function _check_logged_in_role_allowed() {
		if ( ! $this->_config->get_boolean( 'pgcache.reject.logged_roles' ) ) {
			return true;
		}

		$roles = $this->_config->get_array( 'pgcache.reject.roles' );

		if ( empty( $roles ) ) {
			return true;
		}

		foreach ( array_keys( $_COOKIE ) as $cookie_name ) {
			if ( strpos( $cookie_name, 'w3tc_logged_' ) === 0 ) {
				foreach ( $roles as $role ) {
					if ( strstr( $cookie_name, md5( NONCE_KEY . $role ) ) ) {
						return false;
					}
				}
			}
		}

		return true;
	}

	/**
	 * Ensures that the required cache rules are present.
	 *
	 * @return void
	 */
	public function _check_rules_present() {
		if ( Util_Environment::is_nginx() ) {
			return; // nginx store it in a single file.
		}

		$filename = Util_Rule::get_pgcache_rules_cache_path();
		if ( file_exists( $filename ) ) {
			return;
		}

		// we call it as little times as possible its expensive, but have to restore lost .htaccess file.
		$e = Dispatcher::component( 'PgCache_Environment' );
		try {
			$e->fix_on_wpadmin_request( $this->_config, true );
		} catch ( \Exception $ex ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Exception.
		}
	}

	/**
	 * Compresses the given data using the specified compression method.
	 *
	 * @param string $data        The data to be compressed.
	 * @param string $compression The compression method ('gzip', 'deflate', 'br').
	 *
	 * @return string The compressed data.
	 */
	public function _compress( $data, $compression ) {
		switch ( $compression ) {
			case 'gzip':
				$data = gzencode( $data );
				break;

			case 'deflate':
				$data = gzdeflate( $data );
				break;

			case 'br':
				$data = brotli_compress( $data );
				break;
		}

		return $data;
	}

	/**
	 * Retrieves the key extension used for cache identification.
	 *
	 * @return array An associative array representing the key extension.
	 */
	private function _get_key_extension() {
		$extension = array(
			'useragent'           => '',
			'referrer'            => '',
			'cookie'              => '',
			'encryption'          => '',
			'compression'         => $this->_get_compression(),
			'content_type'        => '',
			'cache'               => true,
			'cache_reject_reason' => '',
			'group'               => '',
		);

		if ( $this->_mobile ) {
			$extension['useragent'] = $this->_mobile->get_group();
		}

		if ( $this->_referrer ) {
			$extension['referrer'] = $this->_referrer->get_group();
		}

		if ( Util_Environment::is_https() ) {
			$extension['encryption'] = 'ssl';
		}

		$this->_fill_key_extension_cookie( $extension );

		// fill group.
		$extension['group'] = $this->get_cache_group_by_uri( $this->_request_uri );
		$extension          = w3tc_apply_filters(
			'pagecache_key_extension',
			$extension,
			$this->_request_url_fragments['host'],
			$this->_request_uri
		);

		return $extension;
	}

	/**
	 * Fills the key extension array with cookie-related information based on configured cookie groups.
	 *
	 * @param array $extension {
	 *     Reference to the key extension array.
	 *
	 *     @type string|null $cookie              The name of the matched cookie group, if any.
	 *     @type bool|null   $cache               Whether caching is allowed. Set to false if a matching group disallows caching.
	 *     @type string|null $cache_reject_reason Reason for cache rejection if caching is disabled.
	 * }
	 *
	 * @return void
	 */
	private function _fill_key_extension_cookie( &$extension ) {
		if ( ! $this->_config->get_boolean( 'pgcache.cookiegroups.enabled' ) ) {
			return;
		}

		$groups = $this->_config->get_array( 'pgcache.cookiegroups.groups' );
		foreach ( $groups as $group_name => $g ) {
			if ( isset( $g['enabled'] ) && $g['enabled'] ) {
				$cookies = array();
				foreach ( $g['cookies'] as $cookie ) {
					$cookie = trim( $cookie );
					if ( ! empty( $cookie ) ) {
						$cookie = str_replace( '+', ' ', $cookie );
						if ( strpos( $cookie, '=' ) === false ) {
							$cookie .= '=.*';
						}

						$cookies[] = $cookie;
					}
				}

				if ( count( $cookies ) > 0 ) {
					$cookies_regexp = '~^(' . implode( '|', $cookies ) . ')$~i';

					foreach ( $_COOKIE as $key => $value ) {
						if ( @preg_match( $cookies_regexp, $key . '=' . $value ) ) {
							$extension['cookie'] = $group_name;
							if ( ! $g['cache'] ) {
								$extension['cache']               = false;
								$extension['cache_reject_reason'] = 'cookiegroup ' . $group_name;
							}

							return;
						}
					}
				}
			}
		}
	}

	/**
	 * Determines the cache group based on the provided URI.
	 *
	 * @param string $uri The URI to analyze.
	 *
	 * @return string The cache group name.
	 */
	protected function get_cache_group_by_uri( $uri ) {
		// "!$this->_enhanced_mode" in condition above prevents usage of separate group under disk-enhanced
		// so that rewrite rules still work. Flushing is handled by workaround in this case.
		if ( ! $this->_enhanced_mode ) {
			$sitemap_regex = $this->_config->get_string( 'pgcache.purge.sitemap_regex' );
			if ( $sitemap_regex && preg_match( '~' . $sitemap_regex . '~', basename( $uri ) ) ) {
				return 'sitemaps';
			}
		}

		if (
			'cache' === $this->_config->get_string( 'pgcache.rest' ) &&
			Util_Environment::is_rest_request( $uri ) &&
			Util_Environment::is_w3tc_pro( $this->_config )
		) {
			return 'rest';
		}

		return '';
	}

	/**
	 * Retrieves the compression type supported by the server.
	 *
	 * @return string The supported compression type ('gzip', 'br', or empty string).
	 */
	public function _get_compression() {
		if ( $this->_debug ) { // Can't generate/use compressed files during debug mode.
			return '';
		}

		if ( ! Util_Environment::is_zlib_enabled() && ! $this->_is_buggy_ie() ) {
			$compressions = $this->_get_compressions();
			foreach ( $compressions as $compression ) {
				if (
					is_string( $compression ) &&
					isset( $_SERVER['HTTP_ACCEPT_ENCODING'] ) &&
					stristr( htmlspecialchars( stripslashes( $_SERVER['HTTP_ACCEPT_ENCODING'] ) ), $compression ) !== false
				) {
					return $compression;
				}
			}
		}

		return '';
	}

	/**
	 * Retrieves the list of supported compression methods.
	 *
	 * @return array An array of supported compression methods.
	 */
	public function _get_compressions() {
		$compressions = array(
			false,
		);

		if ( defined( 'W3TC_PAGECACHE_STORE_COMPRESSION_OFF' ) ) {
			return $compressions;
		}

		if (
			$this->_config->get_boolean( 'browsercache.enabled' ) &&
			$this->_config->get_boolean( 'browsercache.html.compression' ) &&
			function_exists( 'gzencode' )
		) {
			$compressions[] = 'gzip';
		}

		if (
			$this->_config->get_boolean( 'browsercache.enabled' ) &&
			$this->_config->get_boolean( 'browsercache.html.brotli' ) &&
			function_exists( 'brotli_compress' )
		) {
			$compressions[] = 'br';
		}

		return $compressions;
	}

	/**
	 * Retrieves the response headers sent by the server.
	 *
	 * @return array An associative array containing 'kv' and 'plain' header representations.
	 */
	public function _get_response_headers() {
		$headers_kv    = array();
		$headers_plain = array();

		if ( function_exists( 'headers_list' ) ) {
			$headers_list = headers_list();
			if ( $headers_list ) {
				foreach ( $headers_list as $header ) {
					$pos = strpos( $header, ':' );
					if ( $pos ) {
						$header_name  = trim( substr( $header, 0, $pos ) );
						$header_value = trim( substr( $header, $pos + 1 ) );
					} else {
						$header_name  = $header;
						$header_value = '';
					}

					$headers_kv[ strtolower( $header_name ) ] = $header_value;
					$headers_plain[]                          = array(
						'name'  => $header_name,
						'value' => $header_value,
					);
				}
			}
		}

		return array(
			'kv'    => $headers_kv,
			'plain' => $headers_plain,
		);
	}

	/**
	 * Checks if the user is using a buggy version of Internet Explorer.
	 *
	 * @return bool True if a buggy version of Internet Explorer is detected, false otherwise.
	 */
	public function _is_buggy_ie() {
		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$ua = htmlspecialchars( stripslashes( $_SERVER['HTTP_USER_AGENT'] ) ); // phpcs:ignore

			if ( strpos( $ua, 'Mozilla/4.0 (compatible; MSIE ' ) === 0 && strpos( $ua, 'Opera' ) === false ) {
				$version = (float) substr( $ua, 30 );

				return $version < 6 || ( 6.0 === $version && false === strpos( $ua, 'SV1' ) );
			}
		}

		return false;
	}

	/**
	 * Filters and retrieves cached headers from the response.
	 *
	 * @param array $response_headers {
	 *     An array of response headers.
	 *
	 *     @type string[] $name  The header name.
	 *     @type string   $value The header value.
	 * }
	 *
	 * @return array {
	 *     An array of cached headers.
	 *
	 *     @type int      $Status-Code  The HTTP status code (if available).
	 *     @type string[] $n            The repeated header name.
	 *     @type string   $v            The repeated header value.
	 *     @type string   $header_name  Cached header name.
	 *     @type string   $header_value Cached header value.
	 * }
	 */
	public function _get_cached_headers( $response_headers ) {
		$data_headers  = array();
		$cache_headers = array_merge(
			array( 'Location', 'X-WP-Total', 'X-WP-TotalPages' ),
			$this->_config->get_array( 'pgcache.cache.headers' )
		);

		if ( function_exists( 'http_response_code' ) ) { // php5.3 compatibility.
			$data_headers['Status-Code'] = http_response_code();
		}

		$repeating_headers = array(
			'link',
			'cookie',
			'set-cookie',
		);
		$repeating_headers = apply_filters( 'w3tc_repeating_headers', $repeating_headers );

		foreach ( $response_headers as $i ) {
			$header_name  = $i['name'];
			$header_value = $i['value'];

			foreach ( $cache_headers as $cache_header_name ) {
				if ( strcasecmp( $header_name, $cache_header_name ) === 0 ) {
					$header_name_lo = strtolower( $header_name );
					if ( in_array( $header_name_lo, $repeating_headers, true ) ) {
						// headers may repeat.
						$data_headers[] = array(
							'n' => $header_name,
							'v' => $header_value,
						);
					} else {
						$data_headers[ $header_name ] = $header_value;
					}
				}
			}
		}

		return $data_headers;
	}

	/**
	 * Constructs a unique cache key for the page.
	 *
	 * @param array  $page_key_extension {
	 *     An array of page key extensions.
	 *
	 *     @type string $useragent    User agent key extension.
	 *     @type string $referrer     Referrer key extension.
	 *     @type string $cookie       Cookie key extension.
	 *     @type string $encryption   Encryption key extension.
	 *     @type string $group        Optional. Cache group key extension.
	 *     @type string $content_type Optional. Content type for XML handling.
	 *     @type string $compression  Optional. Compression type key extension.
	 * }
	 * @param string $request_url Optional. The request URL.
	 *
	 * @return string The constructed cache key.
	 */
	public function _get_page_key( $page_key_extension, $request_url = '' ) {
		// key url part.
		if ( empty( $request_url ) ) {
			$request_url_fragments = $this->_request_url_fragments;
		} else {
			$request_url_fragments = array();

			$parts = wp_parse_url( $request_url );

			if ( isset( $parts['host'] ) ) {
				$request_url_fragments['host'] = $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
			} else {
				$request_url_fragments['host'] = $this->_request_url_fragments['host'];
			}

			$request_url_fragments['path']        = ( isset( $parts['path'] ) ? $parts['path'] : '' );
			$request_url_fragments['querystring'] = ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
			$request_url_fragments                = $this->_normalize_url_fragments( $request_url_fragments );
		}

		$key_urlpart = $request_url_fragments['host'] . ( $request_url_fragments['path'] ?? '' ) .
			( $request_url_fragments['querystring'] ?? '' );

		$key_urlpart = $this->_get_page_key_urlpart( $key_urlpart, $page_key_extension );

		// key extension.
		$key_extension = '';
		$extensions    = array( 'useragent', 'referrer', 'cookie', 'encryption' );
		foreach ( $extensions as $e ) {
			if ( ! empty( $page_key_extension[ $e ] ) ) {
				$key_extension .= '_' . $page_key_extension[ $e ];
			}
		}

		if ( Util_Environment::is_preview_mode() ) {
			$key_extension .= '_preview';
		}

		// key postfix.
		$key_postfix = '';
		if ( $this->_enhanced_mode && empty( $page_key_extension['group'] ) ) {
			$key_postfix = '.html';
			if ( $this->_config->get_boolean( 'pgcache.cache.nginx_handle_xml' ) ) {
				$content_type = isset( $page_key_extension['content_type'] ) ? $page_key_extension['content_type'] : '';

				if (
					@preg_match( '~(text/xml|text/xsl|application/xhtml\+xml|application/rdf\+xml|application/rss\+xml|application/atom\+xml|application/xml)~i', $content_type ) ||
					preg_match( W3TC_FEED_REGEXP, $request_url_fragments['path'] ) ||
					false !== strpos( $request_url_fragments['path'], '.xsl' )
				) {
					$key_postfix = '.xml';
				}
			}
		}

		// key compression.
		$key_compression = '';
		if ( $page_key_extension['compression'] ) {
			$key_compression = '_' . $page_key_extension['compression'];
		}

		$key = w3tc_apply_filters(
			'pagecache_page_key',
			array(
				'key'                => array(
					$key_urlpart,
					$key_extension,
					$key_postfix,
					$key_compression,
				),
				'page_key_extension' => $page_key_extension,
				'url_fragments'      => $this->_request_url_fragments,
			)
		);

		return implode( '', $key['key'] );
	}

	/**
	 * Normalizes and modifies the URL part of the page key.
	 *
	 * @param string $key  The URL part of the cache key.
	 * @param array  $page_key_extension {
	 *     An array of page key extensions.
	 *
	 *     @type string $group Optional. Group identifier for the page key.
	 * }
	 *
	 * @return string The normalized URL part of the cache key.
	 */
	private function _get_page_key_urlpart( $key, $page_key_extension ) {
		// remove fragments.
		$key = preg_replace( '~#.*$~', '', $key );

		// host/uri in different cases means the same page in wp.
		$key = strtolower( $key );

		if ( empty( $page_key_extension['group'] ) ) {
			if ( $this->_enhanced_mode || $this->_nginx_memcached ) {
				$extra = '';

				// URL decode.
				$key = urldecode( $key );

				// replace double slashes.
				$key = preg_replace( '~[/\\\]+~', '/', $key );

				// replace index.php.
				$key = str_replace( '/index.php', '/', $key );

				// remove querystring.
				$key = preg_replace( '~\?.*$~', '', $key );

				// make sure one slash is at the end.
				if ( '/' === substr( $key, strlen( $key ) - 1, 1 ) ) {
					$extra = '_slash';
				}

				$key = trim( $key, '/' ) . '/';

				if ( $this->_nginx_memcached ) {
					return $key;
				}

				return $key . '_index' . $extra;
			}
		}

		return md5( $key );
	}

	/**
	 * Appends debugging information to the footer comment.
	 *
	 * @param array $strings An array of strings to append debugging information to.
	 *
	 * @return array The modified array of footer comment strings.
	 */
	public function w3tc_footer_comment( $strings ) {
		$strings[] = sprintf(
			// translators: 1: Engine name, 2: Reject reason placeholder, 3: Page key extension.
			__( 'Page Caching using %1$s%2$s%3$s', 'w3-total-cache' ),
			Cache::engine_name( $this->_config->get_string( 'pgcache.engine' ) ),
			'{w3tc_pagecache_reject_reason}',
			isset( $this->_page_key_extension['cookie'] ) ? ' ' . $this->_page_key_extension['cookie'] : ''
		);

		if ( $this->_debug ) {
			$time_total = Util_Debug::microtime() - $this->_time_start;
			$engine     = $this->_config->get_string( 'pgcache.engine' );
			$strings[]  = '';
			$strings[]  = 'Page cache debug info:';
			$strings[]  = sprintf( '%s%s', str_pad( 'Engine: ', 20 ), Cache::engine_name( $engine ) );
			$strings[]  = sprintf( '%s%s', str_pad( 'Cache key: ', 20 ), $this->_page_key );

			$strings[] = sprintf( '%s%.3fs', str_pad( 'Creation Time: ', 20 ), time() );

			$headers = $this->_get_response_headers();

			if ( count( $headers['plain'] ) ) {
				$strings[] = 'Header info:';

				foreach ( $headers['plain'] as $i ) {
					$strings[] = sprintf(
						'%s%s',
						str_pad( $i['name'] . ': ', 20 ),
						Util_Content::escape_comment( $i['value'] )
					);
				}
			}

			$strings[] = '';
		}

		return $strings;
	}

	/**
	 * Sends the provided headers.
	 *
	 * @param array $headers {
	 *     An associative array of headers to send.
	 *
	 *     @type string       $name  The header name.
	 *     @type string|array $value The header value or an array with 'n' for the name and 'v' for the value.
	 * }
	 *
	 * @return bool True on success, false if headers were already sent.
	 */
	public function _headers( $headers ) {
		if ( headers_sent() ) {
			return false;
		}

		$repeating = array();
		// headers are sent as name->value and array(n=>, v=>) to support repeating headers.
		foreach ( $headers as $name0 => $value0 ) {
			if ( is_array( $value0 ) && isset( $value0['n'] ) ) {
				$name  = $value0['n'];
				$value = $value0['v'];
			} else {
				$name  = $name0;
				$value = $value0;
			}

			if ( 'Status' === $name ) {
				@header( $headers['Status'] );
			} elseif ( 'Status-Code' === $name ) {
				if ( function_exists( 'http_response_code' ) ) { // php5.3 compatibility.
					@http_response_code( $headers['Status-Code'] );
				}
			} elseif ( ! empty( $name ) && ! empty( $value ) ) {
				@header( $name . ': ' . $value, ! isset( $repeating[ $name ] ) );
				$repeating[ $name ] = true;
			}
		}

		return true;
	}

	/**
	 * Sends caching-related headers.
	 *
	 * @param bool   $is_404         Whether the current request is a 404 error.
	 * @param int    $time           The cache creation time.
	 * @param string $etag           The ETag value.
	 * @param string $compression    The compression method used.
	 * @param array  $custom_headers Optional. Custom headers to include.
	 *
	 * @return void|bool True on success, false if headers were already sent, void if not modified.
	 */
	public function _send_headers( $is_404, $time, $etag, $compression, $custom_headers = array() ) {
		$exit      = false;
		$headers   = ( is_array( $custom_headers ) ? $custom_headers : array() );
		$curr_time = time();

		$bc_lifetime = $this->_config->get_integer( 'browsercache.html.lifetime' );

		$expires = ( is_null( $time ) ? $curr_time : $time ) + $bc_lifetime;
		$max_age = ( $expires > $curr_time ? $expires - $curr_time : 0 );

		if ( $is_404 ) {
			// Add 404 header.
			$headers['Status'] = 'HTTP/1.1 404 Not Found';
		} elseif ( ( ! is_null( $time ) && $this->_check_modified_since( $time ) ) || $this->_check_match( $etag ) ) {
			// Add 304 header.
			$headers['Status'] = 'HTTP/1.1 304 Not Modified';

			// Don't send content if it isn't modified.
			$exit = true;
		}

		if ( $this->_config->get_boolean( 'browsercache.enabled' ) ) {
			if ( $this->_config->get_boolean( 'browsercache.html.last_modified' ) ) {
				$headers['Last-Modified'] = Util_Content::http_date( $time );
			}

			if ( $this->_config->get_boolean( 'browsercache.html.expires' ) ) {
				$headers['Expires'] = Util_Content::http_date( $expires );
			}

			if ( $this->_config->get_boolean( 'browsercache.html.cache.control' ) ) {
				switch ( $this->_config->get_string( 'browsercache.html.cache.policy' ) ) {
					case 'cache':
						$headers['Pragma']        = 'public';
						$headers['Cache-Control'] = 'public';
						break;

					case 'cache_public_maxage':
						$headers['Pragma']        = 'public';
						$headers['Cache-Control'] = sprintf( 'max-age=%d, public', $max_age );
						break;

					case 'cache_validation':
						$headers['Pragma']        = 'public';
						$headers['Cache-Control'] = 'public, must-revalidate, proxy-revalidate';
						break;

					case 'cache_noproxy':
						$headers['Pragma']        = 'public';
						$headers['Cache-Control'] = 'private, must-revalidate';
						break;

					case 'cache_maxage':
						$headers['Pragma']        = 'public';
						$headers['Cache-Control'] = sprintf( 'max-age=%d, public, must-revalidate, proxy-revalidate', $max_age );
						break;

					case 'no_cache':
						$headers['Pragma']        = 'no-cache';
						$headers['Cache-Control'] = 'private, no-cache';
						break;

					case 'no_store':
						$headers['Pragma']        = 'no-store';
						$headers['Cache-Control'] = 'no-store';
						break;
				}
			}

			if ( $this->_config->get_boolean( 'browsercache.html.etag' ) ) {
				$headers['ETag'] = '"' . $etag . '"';
			}
		}

		$headers = array_merge(
			$headers,
			$this->_get_common_headers( $compression )
		);

		// Send headers to client.
		$result = $this->_headers( $headers );

		if ( $exit ) {
			exit();
		}

		return $result;
	}

	/**
	 * Retrieves common headers for caching purposes.
	 *
	 * @param string|null $compression The compression type to use, if any.
	 *
	 * @return array An associative array of headers to include.
	 */
	public function _get_common_headers( $compression ) {
		$headers = array();

		if ( $this->_config->get_boolean( 'browsercache.enabled' ) ) {
			if ( $this->_config->get_boolean( 'browsercache.html.w3tc' ) ) {
				$headers['X-Powered-By'] = Util_Environment::w3tc_header();
			}
		}

		$vary = '';
		// compressed && UAG.
		if ( $compression && $this->_page_key_extension['useragent'] ) {
			$vary                        = 'Accept-Encoding,User-Agent,Cookie';
			$headers['Content-Encoding'] = $compression;
			// compressed.
		} elseif ( $compression ) {
			$vary                        = 'Accept-Encoding';
			$headers['Content-Encoding'] = $compression;
			// uncompressed && UAG.
		} elseif ( $this->_page_key_extension['useragent'] ) {
			$vary = 'User-Agent,Cookie';
		}

		// Add Cookie to vary if user logged in and not previously set.
		if ( ! $this->_check_logged_in() && strpos( $vary, 'Cookie' ) === false ) {
			if ( $vary ) {
				$vary .= ',Cookie';
			} else {
				$vary = 'Cookie';
			}
		}

		// Add vary header.
		if ( $vary ) {
			$headers['Vary'] = $vary;
		}

		// Disable caching for preview mode.
		if ( Util_Environment::is_preview_mode() ) {
			$headers['Pragma']        = 'private';
			$headers['Cache-Control'] = 'private';
		}

		return $headers;
	}

	/**
	 * Checks if the request's "If-Modified-Since" header matches the given time.
	 *
	 * @param int $time The timestamp to compare against.
	 *
	 * @return bool True if the header matches the given time, false otherwise.
	 */
	public function _check_modified_since( $time ) {
		if ( ! empty( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) {
			$if_modified_since = htmlspecialchars( stripslashes( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) );

			// IE has tacked on extra data to this header, strip it.
			$semicolon = strrpos( $if_modified_since, ';' );
			if ( false !== $semicolon ) {
				$if_modified_since = substr( $if_modified_since, 0, $semicolon );
			}

			return strtotime( $if_modified_since ) === $time;
		}

		return false;
	}

	/**
	 * Checks if the request's "If-None-Match" header matches the given ETag.
	 *
	 * @param string $etag The ETag to compare against.
	 *
	 * @return bool True if the ETag matches, false otherwise.
	 */
	public function _check_match( $etag ) {
		if ( ! empty( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) {
			$if_none_match = htmlspecialchars( stripslashes( $_SERVER['HTTP_IF_NONE_MATCH'] ) ); // phpcs:ignore
			$client_etags  = explode( ',', $if_none_match );

			foreach ( $client_etags as $client_etag ) {
				$client_etag = trim( $client_etag );

				if ( $etag === $client_etag ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Executes bad behavior protection if configured.
	 *
	 * @return void
	 */
	public function _bad_behavior() {
		$bb_file = $this->_config->get_string( 'pgcache.bad_behavior_path' );
		if ( '' !== $bb_file ) {
			require_once $bb_file;
		}
	}

	/**
	 * Parses dynamic content within the provided buffer.
	 *
	 * @param string $buffer The content buffer to parse.
	 *
	 * @return string The buffer with parsed dynamic content.
	 */
	public function _parse_dynamic( $buffer ) {
		if ( ! defined( 'W3TC_DYNAMIC_SECURITY' ) ) {
			return $buffer;
		}

		$buffer = preg_replace_callback(
			'~<!--\s*mfunc\s*' . W3TC_DYNAMIC_SECURITY . '(.*)-->(.*)<!--\s*/mfunc\s*' . W3TC_DYNAMIC_SECURITY . '\s*-->~Uis',
			array(
				$this,
				'_parse_dynamic_mfunc',
			),
			$buffer
		);

		$buffer = preg_replace_callback(
			'~<!--\s*mclude\s*' . W3TC_DYNAMIC_SECURITY . '(.*)-->(.*)<!--\s*/mclude\s*' . W3TC_DYNAMIC_SECURITY . '\s*-->~Uis',
			array(
				$this,
				'_parse_dynamic_mclude',
			),
			$buffer
		);

		return $buffer;
	}

	/**
	 * Executes a dynamic mfunc tag found in content.
	 *
	 * phpcs:disable Squiz.PHP.Eval.Discouraged
	 *
	 * @param array $matches The matches from the regular expression.
	 *
	 * @return string The result of executing the mfunc code.
	 */
	public function _parse_dynamic_mfunc( $matches ) {
		$code1 = trim( $matches[1] );
		$code2 = trim( $matches[2] );
		$code  = ( $code1 ? $code1 : $code2 );

		if ( $code ) {
			$code = trim( $code, ';' ) . ';';

			try {
				ob_start();
				$result = eval( $code );
				$output = ob_get_contents();
				ob_end_clean();
			} catch ( \Exception $ex ) {
				$result = false;
			}

			if ( false === $result ) {
				$output = sprintf( 'Unable to execute code: %s', htmlspecialchars( $code ) );
			}
		} else {
			$output = htmlspecialchars( 'Invalid mfunc tag syntax. The correct format is: <!-- W3TC_DYNAMIC_SECURITY mfunc PHP code --><!-- /mfunc W3TC_DYNAMIC_SECURITY --> or <!-- W3TC_DYNAMIC_SECURITY mfunc -->PHP code<!-- /mfunc W3TC_DYNAMIC_SECURITY -->.' );
		}

		return $output;
	}

	/**
	 * Includes a file based on a dynamic mclude tag found in content.
	 *
	 * @param array $matches The matches from the regular expression.
	 *
	 * @return string The content of the included file, or an error message.
	 */
	public function _parse_dynamic_mclude( $matches ) {
		$file1 = trim( $matches[1] );
		$file2 = trim( $matches[2] );

		$file = ( $file1 ? $file1 : $file2 );

		if ( $file ) {
			$file = ABSPATH . $file;

			if ( file_exists( $file ) && is_readable( $file ) ) {
				ob_start();
				include $file;
				$output = ob_get_contents();
				ob_end_clean();
			} else {
				$output = sprintf( 'Unable to open file: %s', htmlspecialchars( $file ) );
			}
		} else {
			$output = htmlspecialchars( 'Incorrect mclude tag syntax. The correct format is: <!-- mclude W3TC_DYNAMIC_SECURITY path/to/file.php --><!-- /mclude W3TC_DYNAMIC_SECURITY --> or <!-- mclude W3TC_DYNAMIC_SECURITY -->path/to/file.php<!-- /mclude W3TC_DYNAMIC_SECURITY -->.' );
		}

		return $output;
	}

	/**
	 * Checks if the buffer contains dynamic tags.
	 *
	 * @param string $buffer The content buffer to check.
	 *
	 * @return bool True if dynamic tags are present, false otherwise.
	 */
	public function _has_dynamic( $buffer ) {
		if ( ! defined( 'W3TC_DYNAMIC_SECURITY' ) ) {
			return false;
		}

		return preg_match(
			'~<!--\s*m(func|clude)\s*' . W3TC_DYNAMIC_SECURITY . '(.*)-->(.*)<!--\s*/m(func|clude)\s*' . W3TC_DYNAMIC_SECURITY . '\s*-->~Uis',
			$buffer
		);
	}

	/**
	 * Determines if the content type is cacheable.
	 *
	 * @return bool True if the content type is cacheable, false otherwise.
	 */
	private function _is_cacheable_content_type() {
		$content_type = '';
		$headers      = headers_list();
		foreach ( $headers as $header ) {
			$header = strtolower( $header );
			$m      = null;
			if ( preg_match( '~\s*content-type\s*:([^;]+)~', $header, $m ) ) {
				$content_type = trim( $m[1] );
			}
		}

		$cache_headers = apply_filters(
			'w3tc_is_cacheable_content_type',
			array(
				'', // redirects, they have only Location header set.
				'application/json',
				'text/html',
				'text/xml',
				'text/xsl',
				'application/xhtml+xml',
				'application/rss+xml',
				'application/atom+xml',
				'application/rdf+xml',
				'application/xml',
			)
		);
		return in_array( $content_type, $cache_headers, true );
	}

	/**
	 * Preprocesses the request URI into its components.
	 *
	 * @return void
	 */
	private function _preprocess_request_uri() {
		$p = explode( '?', $this->_request_uri, 2 );

		$this->_request_url_fragments['path']        = $p[0];
		$this->_request_url_fragments['querystring'] = ( empty( $p[1] ) ? '' : '?' . $p[1] );

		$this->_request_url_fragments = $this->_normalize_url_fragments( $this->_request_url_fragments );
	}

	/**
	 * Normalizes URL fragments.
	 *
	 * @param array $fragments {
	 *     The URL fragments to normalize.
	 *
	 *     @type string $querystring The query string to normalize.
	 * }
	 *
	 * @return array The normalized URL fragments.
	 */
	private function _normalize_url_fragments( $fragments ) {
		$fragments                = w3tc_apply_filters( 'pagecache_normalize_url_fragments', $fragments );
		$fragments['querystring'] = $this->_normalize_querystring( $fragments['querystring'] );

		return $fragments;
	}

	/**
	 * Normalizes the query string in a URL.
	 *
	 * @param string $querystring The query string to normalize.
	 *
	 * @return string The normalized query string.
	 */
	private function _normalize_querystring( $querystring ) {
		$ignore_qs = $this->_config->get_array( 'pgcache.accept.qs' );
		$ignore_qs = w3tc_apply_filters( 'pagecache_extract_accept_qs', $ignore_qs );
		Util_Rule::array_trim( $ignore_qs );

		if ( empty( $ignore_qs ) || empty( $querystring ) ) {
			return $querystring;
		}

		$querystring_naked = substr( $querystring, 1 );

		foreach ( $ignore_qs as $qs ) {
			$m = null;
			if ( strpos( $qs, '=' ) === false ) {
				$regexp = Util_Environment::preg_quote( str_replace( '+', ' ', $qs ) );
				if ( @preg_match( "~^(.*?&|)$regexp(=[^&]*)?(&.*|)$~i", $querystring_naked, $m ) ) {
					$querystring_naked = $m[1] . $m[3];
				}
			} else {
				$regexp = Util_Environment::preg_quote( str_replace( '+', ' ', $qs ) );

				if ( @preg_match( "~^(.*?&|)$regexp(&.*|)$~i", $querystring_naked, $m ) ) {
					$querystring_naked = $m[1] . $m[2];
				}
			}
		}

		$querystring_naked = preg_replace( '~[&]+~', '&', $querystring_naked );
		$querystring_naked = trim( $querystring_naked, '&' );

		return empty( $querystring_naked ) ? '' : '?' . $querystring_naked;
	}

	/**
	 * Handles delayed cache printing when applicable.
	 *
	 * @return void
	 */
	public function delayed_cache_print() {
		if ( $this->_late_caching && $this->_caching ) {
			$this->_cached_data = $this->_extract_cached_page( true );
			if ( $this->_cached_data ) {
				global $w3_late_caching_succeeded;
				$w3_late_caching_succeeded = true;

				$this->process_status = 'hit';
				$this->process_cached_page_and_exit( $this->_cached_data );

				// if is passes here - exit is not possible now and will happen on init.
				return;
			}
		}

		if ( $this->_late_init && $this->_caching ) {
			$this->process_status = 'hit';
			$this->process_cached_page_and_exit( $this->_cached_data );

			// if is passes here - exit is not possible now and will happen on init.
			return;
		}
	}

	/**
	 * Conditionally saves the cached result with optional compression.
	 *
	 * @param string $buffer            The content buffer to cache.
	 * @param array  $response_headers  The response headers to include in the cache.
	 * @param bool   $has_dynamic       Whether the content includes dynamic tags.
	 *
	 * @return string The buffer, possibly modified for compression.
	 */
	private function _maybe_save_cached_result( $buffer, $response_headers, $has_dynamic ) {
		$mobile_group          = $this->_page_key_extension['useragent'];
		$referrer_group        = $this->_page_key_extension['referrer'];
		$encryption            = $this->_page_key_extension['encryption'];
		$compression_header    = $this->_page_key_extension['compression'];
		$compressions_to_store = $this->_get_compressions();

		// Don't compress here for debug mode or dynamic tags because we need to modify buffer before send it to client.
		if ( $this->_debug || $has_dynamic ) {
			$compressions_to_store = array( false );
		}

		// Right now dont return compressed buffer if we are dynamic that will happen on shutdown after processing dynamic stuff.
		$compression_of_returned_content = ( $has_dynamic ? false : $compression_header );

		$headers = $this->_get_cached_headers( $response_headers['plain'] );
		if ( ! empty( $headers['Status-Code'] ) ) {
			$is_404 = ( '404' === $headers['Status-Code'] );
		} elseif ( function_exists( 'is_404' ) ) {
			$is_404 = is_404();
		} else {
			$is_404 = false;
		}

		if ( $this->_enhanced_mode ) {
			// Redirect issued, if we have some old cache entries they will be turned into fresh files and catch further requests.
			if ( isset( $response_headers['kv']['location'] ) ) {
				$cache = $this->_get_cache( $this->_page_key_extension['group'] );

				foreach ( $compressions_to_store as $_compression ) {
					$_page_key = $this->_get_page_key(
						array_merge(
							$this->_page_key_extension,
							array(
								'compression' => $_compression,
							)
						)
					);
					$cache->hard_delete( $_page_key );
				}

				return $buffer;
			}
		}

		$content_type = '';
		if ( $this->_enhanced_mode && ! $this->_late_init ) {
			register_shutdown_function(
				array(
					$this,
					'_check_rules_present',
				)
			);

			if ( isset( $response_headers['kv']['content-type'] ) ) {
				$content_type = $response_headers['kv']['content-type'];
			}
		}

		$time  = time();
		$cache = $this->_get_cache( $this->_page_key_extension['group'] );

		// Store different versions of cache.
		$buffers           = array();
		$something_was_set = false;

		do_action(
			'w3tc_pagecache_before_set',
			array(
				'request_url_fragments' => $this->_request_url_fragments,
				'page_key_extension'    => $this->_page_key_extension,
			)
		);

		foreach ( $compressions_to_store as $_compression ) {
			$this->_set_extract_page_key(
				array_merge(
					$this->_page_key_extension,
					array(
						'compression'  => $_compression,
						'content_type' => $content_type,
					)
				),
				true
			);

			if ( empty( $this->_page_key ) ) {
				continue;
			}

			// Compress content.
			$buffers[ $_compression ] = $this->_compress( $buffer, $_compression );

			// Store cache data.
			$_data = array(
				'404'     => $is_404,
				'headers' => $headers,
				'time'    => $time,
				'content' => $buffers[ $_compression ],
			);

			if ( ! empty( $_compression ) ) {
				$_data['c'] = $_compression;
			}

			if ( $has_dynamic ) {
				$_data['has_dynamic'] = true;
			}

			$_data = apply_filters( 'w3tc_pagecache_set', $_data, $this->_page_key, $this->_page_group );

			if ( ! empty( $_data ) ) {
				$cache->set( $this->_page_key, $_data, $this->_lifetime, $this->_page_group );
				$something_was_set = true;
			}
		}

		if ( $something_was_set ) {
			$this->process_status = 'miss_fill';
		} else {
			$this->process_status = 'miss_third_party';
		}

		// Change buffer if using compression.
		if ( defined( 'W3TC_PAGECACHE_OUTPUT_COMPRESSION_OFF' ) ) {
			$compression_header = false;
		} elseif ( $compression_of_returned_content &&
			isset( $buffers[ $compression_of_returned_content ] ) ) {
			$buffer = $buffers[ $compression_of_returned_content ];
		}

		// Calculate content etag.
		$etag = md5( $buffer );

		// Send headers.
		$this->_send_headers( $is_404, $time, $etag, $compression_header, $headers );

		return $buffer;
	}

	/**
	 * Collects usage statistics for the current request.
	 *
	 * @param object $storage The storage object for saving statistics.
	 *
	 * @return void
	 */
	public function w3tc_usage_statistics_of_request( $storage ) {
		global $w3tc_start_microtime;

		$time_ms = 0;
		if ( ! empty( $w3tc_start_microtime ) ) {
			$time_ms = (int) ( ( microtime( true ) - $w3tc_start_microtime ) * 1000 );
			$storage->counter_add( 'pagecache_requests_time_10ms', (int) ( $time_ms / 10 ) );
		}

		if ( ! empty( $this->process_status ) ) {
			// see registered keys in PgCache_Plugin.w3tc_usage_statistics_metrics.
			$storage->counter_add( 'php_requests_pagecache_' . $this->process_status, 1 );

			if ( $this->_debug ) {
				self::log(
					'finished in ' . $time_ms . ' size ' . $this->output_size . ' with process status ' .
						$this->process_status . ' reason ' . $this->cache_reject_reason
				);
			}
		}
	}

	/**
	 * Logs a message to the page cache log.
	 *
	 * @param string $msg The message to log.
	 *
	 * @return bool|int The number of bytes written, or false on failure.
	 */
	protected static function log( $msg ) {
		$data = sprintf(
			'[%1$s] [%2$s] [%3$s] %4$s ' . "\n",
			gmdate( 'r' ),
			isset( $_SERVER['REQUEST_URI'] ) ? filter_var( stripslashes( $_SERVER['REQUEST_URI'] ), FILTER_SANITIZE_URL ) : '',
			! empty( $_SERVER['HTTP_REFERER'] ) ? htmlspecialchars( $_SERVER['HTTP_REFERER'] ) : '-',
			$msg
		);
		$data = strtr( $data, '<>', '..' );
		$date = Util_Debug::redact_wpnonce( $data );

		$filename = Util_Debug::log_filename( 'pagecache' );

		return @file_put_contents( $filename, $data, FILE_APPEND );
	}
}
