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

		/**
		 * Follow Location responses manually so each hop is evaluated
		 * with {@see Util_Url::is_allowed_outbound_url()} before the next
		 * request. Automatic redirect following would skip that check.
		 */
		$max_redirects = 5;
		for ( $hop = 0; $hop <= $max_redirects; $hop++ ) {
			if ( ! Util_Url::is_allowed_outbound_url( $w3tc_url ) ) {
				return false;
			}

			$request_args = \array_merge(
				$args,
				array(
					'redirection' => 0,
				)
			);

			$response = self::get( $w3tc_url, $request_args );
			if ( \is_wp_error( $response ) ) {
				return false;
			}

			$code = isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
			if ( $code >= 300 && $code < 400 ) {
				$location = self::response_location_header( $response );
				if ( '' === $location ) {
					return false;
				}

				$w3tc_url = self::resolve_redirect_url( $w3tc_url, $location );
				if ( '' === $w3tc_url ) {
					return false;
				}

				continue;
			}

			if ( 200 !== $code ) {
				return false;
			}

			return (bool) @file_put_contents( $w3tc_file, $response['body'] );
		}

		return false;
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

		$location = Util_Url::normalize_protocol_relative_url( $location );
		if ( '' === $location ) {
			return '';
		}

		if ( Util_Environment::is_url( $location ) ) {
			return $location;
		}

		$parts = \wp_parse_url( $current );
		if ( ! \is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		$authority = $parts['scheme'] . '://' . $parts['host'];
		if ( ! empty( $parts['port'] ) ) {
			$authority .= ':' . $parts['port'];
		}

		if ( isset( $location[0] ) && '/' === $location[0] ) {
			return $authority . $location;
		}

		$base_path = isset( $parts['path'] ) ? $parts['path'] : '/';
		$base_dir  = \trailingslashit( \dirname( $base_path ) );

		return $authority . $base_dir . $location;
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
