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
		// Ensure the URL has a protocol.
		if ( strpos( $w3tc_url, '//' ) === 0 ) {
			$w3tc_url = ( Util_Environment::is_https() ? 'https:' : 'http:' ) . $w3tc_url;
		}

		if ( ! Util_Url::is_self_host_url( $w3tc_url ) && ! Util_Url::is_public_host( $w3tc_url ) ) {
			return false;
		}

		// Send a GET request to the URL to fetch the content.
		$response = self::get( $w3tc_url, $args );

		// Check if the response contains an error.
		if ( \is_wp_error( $response ) || 200 !== $response['response']['code'] ) {
			return false;
		}

		// Attempt to write the response body to the specified file.
		return (bool) @file_put_contents( $w3tc_file, $response['body'] );
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
