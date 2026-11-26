<?php
/**
 * File: Util_Http.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Util_Http
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class Util_Http {
	/**
	 * Filter handler for use_curl_transport.
	 * Workaround to not use curl for extra HTTP methods.
	 *
	 * @param bool  $w3tc_result Result of the filter.
	 * @param array $args   Arguments passed to the filter.
	 *
	 * @return bool Returns false if the HTTP method is not GET or POST, otherwise returns the original result.
	 */
	public static function use_curl_transport( $w3tc_result, $args ) {
		/**
		 * Check if the 'method' argument is set and ensure it is either 'GET' or 'POST'.
		 * If it's not, disable the use of cURL transport by returning false.
		 */
		if ( isset( $args['method'] ) && 'GET' !== $args['method'] && 'POST' !== $args['method'] ) {
			return false;
		}

		// Return the original result if the method is GET or POST.
		return $w3tc_result;
	}

	/**
	 * Sends HTTP request.
	 *
	 * @param string $w3tc_url  URL to send the request to.
	 * @param array  $args Arguments for the HTTP request.
	 *
	 * @return WP_Error|array Returns either a WP_Error object on failure or an array containing the response data.
	 */
	public static function request( $w3tc_url, $args = array() ) {
		// Static variable to ensure the filter is only added once during the lifetime of the script.
		static $filter_set = false;

		// Add the 'use_curl_transport' filter if it hasn't been added yet.
		if ( ! $filter_set ) {
			/**
			 * Attach the 'use_curl_transport' method to the 'use_curl_transport' filter hook.
			 * This ensures that the filter is applied whenever the transport mechanism is determined.
			 */
			add_filter( 'use_curl_transport', array( '\W3TC\Util_Http', 'use_curl_transport' ), 10, 2 );

			$filter_set = true; // Mark the filter as set to prevent duplicate additions.
		}

		// Merge the provided arguments with default values defined below.
		$args = array_merge(
			array( 'user-agent' => W3TC_POWERED_BY ),
			$args
		);

		return wp_remote_request( $w3tc_url, $args );
	}

	/**
	 * Sends HTTP GET request
	 *
	 * @param string $w3tc_url  URL to send the GET request to.
	 * @param array  $args Arguments for the GET request.
	 *
	 * @return array|\WP_Error Returns the response data or a \WP_Error object on failure.
	 */
	public static function get( $w3tc_url, $args = array() ) {
		// Merge the provided arguments with the GET method.
		$args = array_merge(
			$args,
			array( 'method' => 'GET' )
		);

		// Use the request method to send the GET request.
		return self::request( $w3tc_url, $args );
	}

	/**
	 * Downloads URL into a file
	 *
	 * @param string $w3tc_url  URL to download.
	 * @param string $w3tc_file Path to the file where the content will be saved.
	 * @param array  $args Optional. Arguments for the download request.
	 *
	 * @return bool Returns true on success, or false on failure.
	 */
	public static function download( $w3tc_url, $w3tc_file, $args = array() ) {
		$w3tc_url = Util_Url::normalize_protocol_relative_url( $w3tc_url );
		if ( '' === $w3tc_url ) {
			return false;
		}

		$public_only = ! empty( $args['w3tc_public_only'] );
		$https_only  = ! empty( $args['w3tc_https_only'] );
		$same_host   = ! empty( $args['w3tc_same_host'] );
		$max_size    = isset( $args['w3tc_max_response_size'] ) ? (int) $args['w3tc_max_response_size'] : 0;
		$validator   = isset( $args['w3tc_response_validator'] ) && \is_callable( $args['w3tc_response_validator'] ) ?
			$args['w3tc_response_validator'] :
			null;
		unset(
			$args['w3tc_public_only'],
			$args['w3tc_https_only'],
			$args['w3tc_same_host'],
			$args['w3tc_max_response_size'],
			$args['w3tc_response_validator']
		);

		$original_host = self::normalized_url_host( $w3tc_url );
		if ( $same_host && '' === $original_host ) {
			return false;
		}

		/**
		 * Follow Location responses manually so each hop is evaluated
		 * against the requested policy before the next request.
		 */
		$max_redirects = 5;
		$jar           = array();
		for ( $hop = 0; $hop <= $max_redirects; $hop++ ) {
			if ( $https_only && 'https' !== \wp_parse_url( $w3tc_url, PHP_URL_SCHEME ) ) {
				return false;
			}

			if ( $same_host && self::normalized_url_host( $w3tc_url ) !== $original_host ) {
				return false;
			}

			$allowed = $public_only ?
				Util_Url::is_public_host( $w3tc_url ) :
				Util_Url::is_allowed_outbound_url( $w3tc_url );
			if ( ! $allowed ) {
				return false;
			}

			$request_args = \array_merge(
				$args,
				array(
					'redirection' => 0,
				)
			);
			if ( array() !== $jar ) {
				$request_args['cookies'] = self::redirect_cookies( $args, $jar, $w3tc_url );
			}
			if ( $max_size > 0 ) {
				$request_args['limit_response_size'] = $max_size + 1;
			}

			$response = self::request_pinned( $w3tc_url, $request_args );
			if ( \is_wp_error( $response ) ) {
				return false;
			}

			$code = isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
			if ( $code >= 300 && $code < 400 ) {
				$location = self::response_location_header( $response );
				if ( '' === $location ) {
					return false;
				}

				self::collect_redirect_cookies( $jar, $response, $w3tc_url );

				$w3tc_url = self::resolve_redirect_url( $w3tc_url, $location );
				if ( '' === $w3tc_url ) {
					return false;
				}

				continue;
			}

			if ( 200 !== $code ) {
				return false;
			}

			$body = isset( $response['body'] ) && \is_string( $response['body'] ) ? $response['body'] : '';
			if ( ( $max_size > 0 && \strlen( $body ) > $max_size ) || ( $validator && ! \call_user_func( $validator, $response ) ) ) {
				return false;
			}

			return (bool) @file_put_contents( $w3tc_file, $body );
		}

		return false;
	}

	/**
	 * Sends a GET request to an allowed destination, pinned to the
	 * addresses that were validated for it.
	 *
	 * Redirects are followed manually. Every hop must pass `$validator`
	 * before it is requested; a redirect whose target is refused is
	 * returned to the caller unfollowed. An allowed redirect past
	 * `$max_redirects` returns a WP_Error, unless `$max_redirects` is 0.
	 *
	 * @since 2.11.1
	 *
	 * @param string        $w3tc_url      URL to request.
	 * @param array         $args          Arguments for the GET request.
	 * @param callable|null $validator     Receives each hop URL and returns bool.
	 *                                     Defaults to Util_Url::is_allowed_outbound_url().
	 * @param int           $max_redirects Maximum number of redirects to follow.
	 *
	 * @return array|\WP_Error Response data, or a WP_Error when the first hop is refused, a request fails, or the redirect limit is reached.
	 */
	public static function get_pinned( $w3tc_url, $args = array(), $validator = null, $max_redirects = 5 ) {
		if ( ! \is_callable( $validator ) ) {
			$validator = array( '\W3TC\Util_Url', 'is_allowed_outbound_url' );
		}

		$w3tc_url = Util_Url::normalize_protocol_relative_url( $w3tc_url );
		if ( '' === $w3tc_url || ! \call_user_func( $validator, $w3tc_url ) ) {
			return new \WP_Error( 'w3tc_url_refused', \__( 'The URL is not an allowed destination.', 'w3-total-cache' ) );
		}

		$max_redirects = \max( 0, (int) $max_redirects );
		$jar           = array();
		for ( $hop = 0; ; $hop++ ) {
			$request_args = $args;
			if ( array() !== $jar ) {
				$request_args['cookies'] = self::redirect_cookies( $args, $jar, $w3tc_url );
			}

			$response = self::request_pinned( $w3tc_url, $request_args );
			if ( \is_wp_error( $response ) ) {
				return $response;
			}

			$code = (int) \wp_remote_retrieve_response_code( $response );
			if ( $code < 300 || $code >= 400 || 0 === $max_redirects ) {
				return $response;
			}

			$next = self::resolve_redirect_url( $w3tc_url, self::response_location_header( $response ) );
			if ( '' === $next || ! \call_user_func( $validator, $next ) ) {
				return $response;
			}

			if ( $hop >= $max_redirects ) {
				return new \WP_Error( 'w3tc_too_many_redirects', \__( 'Too many redirects.', 'w3-total-cache' ) );
			}

			self::collect_redirect_cookies( $jar, $response, $w3tc_url );
			$w3tc_url = $next;
		}
	}

	/**
	 * Sends a single GET request without following redirects. When the
	 * host resolves only to public addresses, the cURL transport is
	 * pinned to those addresses with `CURLOPT_RESOLVE`, so a second DNS
	 * answer cannot change the destination. This site's own host is
	 * requested unpinned when it does not resolve publicly.
	 *
	 * @since 2.11.1
	 *
	 * @param string $w3tc_url URL to request.
	 * @param array  $args     Arguments for the GET request.
	 *
	 * @return array|\WP_Error
	 */
	private static function request_pinned( $w3tc_url, $args ) {
		$args['redirection'] = 0;

		$ips = Util_Url::public_host_ips( $w3tc_url );
		if ( array() === $ips ) {
			if ( ! Util_Url::is_self_host_url( $w3tc_url ) ) {
				return new \WP_Error( 'w3tc_url_refused', \__( 'The URL is not an allowed destination.', 'w3-total-cache' ) );
			}

			return self::get( $w3tc_url, $args );
		}

		$entry = self::curl_resolve_entry( $w3tc_url, $ips );
		if ( '' === $entry ) {
			return self::get( $w3tc_url, $args );
		}

		$pin = self::curl_pin_callback( $w3tc_url, $entry );

		\add_action( 'http_api_curl', $pin, 10, 3 );
		try {
			$response = self::get( $w3tc_url, $args );
		} finally {
			\remove_action( 'http_api_curl', $pin, 10 );
		}

		return $response;
	}

	/**
	 * Returns an `http_api_curl` callback that applies `$entry` as
	 * `CURLOPT_RESOLVE` on the handle for `$w3tc_url` only.
	 *
	 * @since 2.11.1
	 *
	 * @param string $w3tc_url URL being requested.
	 * @param string $entry    Entry from {@see self::curl_resolve_entry()}.
	 *
	 * @return \Closure
	 */
	public static function curl_pin_callback( $w3tc_url, $entry ) {
		return static function ( $handle, $parsed_args, $url ) use ( $w3tc_url, $entry ) {
			unset( $parsed_args );
			if ( $url === $w3tc_url ) {
				\curl_setopt( $handle, CURLOPT_RESOLVE, array( $entry ) );
			}
		};
	}

	/**
	 * Builds a `CURLOPT_RESOLVE` entry (`host:port:address[,address]`)
	 * for a URL. Returns an empty string for IP-literal hosts, which need
	 * no pinning, or when the constant is unavailable.
	 *
	 * @since 2.11.1
	 *
	 * @param string   $w3tc_url URL being requested.
	 * @param string[] $ips      Validated addresses for the URL's host.
	 *
	 * @return string
	 */
	public static function curl_resolve_entry( $w3tc_url, $ips ) {
		if ( ! \defined( 'CURLOPT_RESOLVE' ) || ! \is_array( $ips ) || array() === $ips ) {
			return '';
		}

		$parts = \wp_parse_url( $w3tc_url );
		if ( ! \is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['scheme'] ) ) {
			return '';
		}

		$host = \strtolower( $parts['host'] );
		if ( false !== \filter_var( \trim( $host, '[]' ), FILTER_VALIDATE_IP ) ) {
			return '';
		}

		$port = isset( $parts['port'] ) ? (int) $parts['port'] : ( 'https' === \strtolower( $parts['scheme'] ) ? 443 : 80 );

		$addresses = array();
		foreach ( $ips as $ip ) {
			if ( \is_string( $ip ) && false !== \filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				$addresses[] = false !== \filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ? '[' . $ip . ']' : $ip;
			}
		}

		if ( array() === $addresses ) {
			return '';
		}

		// Multiple addresses per entry require cURL 7.59.0.
		$version = \function_exists( 'curl_version' ) ? \curl_version() : array();
		if ( empty( $version['version_number'] ) || $version['version_number'] < 0x073b00 ) {
			$addresses = \array_slice( $addresses, 0, 1 );
		}

		return $host . ':' . $port . ':' . \implode( ',', $addresses );
	}

	/**
	 * Adds the cookies set by a redirect response to `$jar`, replacing
	 * earlier cookies with the same name, domain, and path. A cookie
	 * without a domain is scoped to the host that set it. A cookie the
	 * response clears is kept as expired so it overrides earlier values.
	 *
	 * @since 2.11.1
	 *
	 * @param \WP_Http_Cookie[] $jar      Cookies collected so far.
	 * @param array|\WP_Error   $response HTTP API response.
	 * @param string            $w3tc_url URL that returned the response.
	 *
	 * @return void
	 */
	private static function collect_redirect_cookies( array &$jar, $response, $w3tc_url ) {
		/**
		 * WP_Http_Cookie does not carry Max-Age, so read expiry from the
		 * underlying Requests cookies when they are available.
		 */
		$expired = array();
		if (
			\is_array( $response ) &&
			isset( $response['http_response'] ) &&
			\is_object( $response['http_response'] ) &&
			\method_exists( $response['http_response'], 'get_response_object' )
		) {
			$raw = $response['http_response']->get_response_object();
			if ( \is_object( $raw ) && isset( $raw->cookies ) && \is_iterable( $raw->cookies ) ) {
				foreach ( $raw->cookies as $raw_cookie ) {
					if ( \is_object( $raw_cookie ) && \method_exists( $raw_cookie, 'is_expired' ) && $raw_cookie->is_expired() ) {
						$expired[ (string) $raw_cookie->name ] = true;
					}
				}
			}
		}

		foreach ( (array) \wp_remote_retrieve_cookies( $response ) as $cookie ) {
			if ( ! $cookie instanceof \WP_Http_Cookie || ! \is_string( $cookie->name ) ) {
				continue;
			}

			$cookie = clone $cookie;
			if ( empty( $cookie->domain ) ) {
				$cookie->domain    = self::normalized_url_host( $w3tc_url );
				$cookie->host_only = true;
			}

			if ( isset( $expired[ $cookie->name ] ) ) {
				$cookie->expires = 0;
			}

			$jar[ $cookie->name . '|' . \strtolower( (string) $cookie->domain ) . '|' . (string) $cookie->path ] = $cookie;
		}
	}

	/**
	 * Returns the request cookies for the next redirect hop: collected
	 * cookies that apply to `$w3tc_url`, plus the caller's cookies that
	 * no applicable collected cookie replaces or clears. Host-only
	 * cookies are sent to the exact host that set them.
	 *
	 * @since 2.11.1
	 *
	 * @param array             $args     Caller's request arguments.
	 * @param \WP_Http_Cookie[] $jar      Cookies collected from redirects.
	 * @param string            $w3tc_url URL of the next hop.
	 *
	 * @return array
	 */
	private static function redirect_cookies( $args, $jar, $w3tc_url ) {
		$host     = self::normalized_url_host( $w3tc_url );
		$cookies  = array();
		$replaced = array();

		foreach ( $jar as $cookie ) {
			$scope          = clone $cookie;
			$scope->expires = null;
			if ( ! $scope->test( $w3tc_url ) ) {
				continue;
			}

			if ( ! empty( $cookie->host_only ) && \strtolower( \rtrim( (string) $cookie->domain, '.' ) ) !== $host ) {
				continue;
			}

			$replaced[ $cookie->name ] = true;
			if ( $cookie->test( $w3tc_url ) ) {
				$cookies[] = $cookie;
			}
		}

		$caller = isset( $args['cookies'] ) && \is_array( $args['cookies'] ) ? $args['cookies'] : array();
		foreach ( $caller as $name => $cookie ) {
			$cookie_name = $cookie instanceof \WP_Http_Cookie ? (string) $cookie->name : (string) $name;
			if ( isset( $replaced[ $cookie_name ] ) ) {
				continue;
			}

			if ( \is_int( $name ) ) {
				$cookies[] = $cookie;
			} else {
				$cookies[ $name ] = $cookie;
			}
		}

		return $cookies;
	}

	/**
	 * Returns a normalized host for redirect comparisons.
	 *
	 * @since 2.10.7
	 *
	 * @param string $url URL to inspect.
	 *
	 * @return string
	 */
	private static function normalized_url_host( $url ) {
		$host = \wp_parse_url( $url, PHP_URL_HOST );

		return \is_string( $host ) ? \strtolower( \rtrim( $host, '.' ) ) : '';
	}

	/**
	 * Reads the Location header from a wp_remote_* response.
	 *
	 * @since 2.10.6
	 *
	 * @param array $response HTTP API response.
	 *
	 * @return string
	 */
	private static function response_location_header( $response ) {
		if ( empty( $response['headers'] ) ) {
			return '';
		}

		$headers = $response['headers'];
		if ( \is_array( $headers ) && isset( $headers['location'] ) ) {
			$location = $headers['location'];
		} elseif ( \is_object( $headers ) && isset( $headers['location'] ) ) {
			$location = $headers['location'];
		} else {
			return '';
		}

		if ( \is_array( $location ) ) {
			$location = \reset( $location );
		}

		return \is_string( $location ) ? \trim( $location ) : '';
	}

	/**
	 * Resolves a redirect Location against the current request URL.
	 *
	 * @since 2.10.6
	 *
	 * @param string $current  Absolute URL of the current hop.
	 * @param string $location Location header value.
	 *
	 * @return string Absolute URL, or empty string when resolution fails.
	 */
	private static function resolve_redirect_url( $current, $location ) {
		if ( ! \is_string( $location ) || '' === $location ) {
			return '';
		}

		$parts = \wp_parse_url( $current );
		if ( ! \is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		// A protocol-relative Location keeps the scheme of the hop that returned it.
		if ( 0 === \strpos( $location, '//' ) ) {
			$location = $parts['scheme'] . ':' . $location;
		}

		if ( ! \class_exists( '\WP_Http' ) ) {
			return '';
		}

		$resolved = \WP_Http::make_absolute_url( $location, $current );

		return Util_Url::is_valid_http_scheme( $resolved ) ? $resolved : '';
	}

	/**
	 * Returns upload info
	 *
	 * @return array|false Returns an array containing upload directory information or false on error.
	 */
	public static function upload_info() {
		// Use a static variable to cache the upload info, avoiding repeated calls to wp_upload_dir().
		static $upload_info = null;

		// If the upload info has not been cached yet, retrieve it.
		if ( null === $upload_info ) {
			// Get the WordPress upload directory information.
			$upload_info = Util_Environment::wp_upload_dir();

			// Check if there is no error in the upload directory information.
			if ( empty( $upload_info['error'] ) ) {
				// Parse the base URL of the upload directory to extract its components.
				$parse_url = @parse_url( $upload_info['baseurl'] );

				// If parsing the URL was successful, extract the path component.
				if ( $parse_url ) {
					// Trim any leading or trailing slashes from the path component.
					$baseurlpath = ( ! empty( $parse_url['path'] ) ? trim( $parse_url['path'], '/' ) : '' );
				} else {
					// If parsing failed, default to 'wp-content/uploads' as the base URL path.
					$baseurlpath = 'wp-content/uploads';
				}

				// Add the base URL path to the upload info array, prefixed and suffixed with slashes.
				$upload_info['baseurlpath'] = '/' . $baseurlpath . '/';
			} else {
				// If there was an error, set the upload info to false.
				$upload_info = false;
			}
		}

		// Return the cached upload info or false if there was an error.
		return $upload_info;
	}

	/**
	 * Test the time to first byte (TTFB).
	 *
	 * @param string $w3tc_url URL to test.
	 * @param bool   $nocache Whether or not to request no cache response, by sending a Cache-Control header.
	 *
	 * @return float|false Time in seconds until the first byte is about to be transferred or false on error.
	 */
	public static function ttfb( $w3tc_url, $nocache = false ) {
		$ch   = curl_init( esc_url( $w3tc_url ) );
		$pass = (bool) $ch;
		$ttfb = false;
		$opts = array(
			CURLOPT_FORBID_REUSE   => 1,
			CURLOPT_FRESH_CONNECT  => 1,
			CURLOPT_HEADER         => 0,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_FOLLOWLOCATION => 1,
			/**
			 * SSL peer verification is the cURL default; the legacy
			 * `CURLOPT_SSL_VERIFYPEER => false` was removed (// TTFB leg). Note: this function calls raw cURL directly
			 * (not the WordPress HTTP API), so the WP `http_request_args`
			 * / `https_ssl_verify` filters do NOT apply here. Operators
			 * with broken CA bundles need to fix the bundle itself; a
			 * TTFB probe MUST NOT silently accept a forged cert.
			 */
			CURLOPT_USERAGENT      => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
		);

		if ( $nocache ) {
			$opts[ CURLOPT_HTTPHEADER ] = array(
				'Cache-Control: no-cache',
				'Pragma: no-cache',
			);

			$opts[ CURLOPT_URL ] = add_query_arg( 'time', microtime( true ), $w3tc_url );
		}

		if ( $ch ) {
			$pass = curl_setopt_array( $ch, $opts ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		if ( $pass ) {
			$pass = (bool) curl_exec( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		if ( $pass ) {
			$ttfb = curl_getinfo( $ch, CURLINFO_STARTTRANSFER_TIME ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		if ( $ch ) {
			curl_close( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		return $ttfb;
	}

	/**
	 * Generate unique md5 value based on domain.
	 *
	 * @return string Returns an MD5 hash generated from the site's network home URL.
	 */
	public static function generate_site_id() {
		// Generate an MD5 hash of the network home URL to create a unique site identifier.
		return md5( network_home_url() );
	}
}
