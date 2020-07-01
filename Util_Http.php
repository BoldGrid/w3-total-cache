<?php
namespace W3TC;

class Util_Http {
	/**
	 * Filter handler for use_curl_transport.
	 * Workaround to not use curl for extra http methods
	 *
	 * @param unknown $result boolean
	 * @param unknown $args   array
	 * @return boolean
	 */
	static public function use_curl_transport( $result, $args ) {
		if ( isset( $args['method'] ) && $args['method'] != 'GET' && $args['method'] != 'POST' )
			return false;

		return $result;
	}

	/**
	 * Sends HTTP request
	 *
	 * @param unknown $url  string
	 * @param unknown $args array
	 * @return WP_Error|array
	 */
	static public function request( $url, $args = array() ) {
		static $filter_set = false;
		if ( !$filter_set ) {
			add_filter( 'use_curl_transport',
				array( '\W3TC\Util_Http', 'use_curl_transport' ), 10, 2 );
			$filter_set = true;
		}

		$args = array_merge( array(
				'user-agent' => W3TC_POWERED_BY
			), $args );

		return wp_remote_request( $url, $args );
	}

	/**
	 * Sends HTTP GET request
	 *
	 * @param string  $url
	 * @param array   $args
	 * @return array|WP_Error
	 */
	static public function get( $url, $args = array() ) {
		$args = array_merge( $args, array(
				'method' => 'GET'
			) );

		return self::request( $url, $args );
	}

	/**
	 * Downloads URL into a file
	 *
	 * @param string  $url
	 * @param string  $file
	 * @return boolean
	 */
	static public function download( $url, $file, $args = array() ) {
		if ( strpos( $url, '//' ) === 0 ) {
			$url = ( Util_Environment::is_https() ? 'https:' : 'http:' ) . $url;
		}

		$response = self::get( $url, $args );

		if ( !is_wp_error( $response ) && $response['response']['code'] == 200 ) {
			return @file_put_contents( $file, $response['body'] );
		}

		return false;
	}

	/**
	 * Returns upload info
	 *
	 * @return array
	 */
	static public function upload_info() {
		static $upload_info = null;

		if ( $upload_info === null ) {
			$upload_info = Util_Environment::wp_upload_dir();

			if ( empty( $upload_info['error'] ) ) {
				$parse_url = @parse_url( $upload_info['baseurl'] );

				if ( $parse_url ) {
					$baseurlpath = ( !empty( $parse_url['path'] ) ? trim( $parse_url['path'], '/' ) : '' );
				} else {
					$baseurlpath = 'wp-content/uploads';
				}

				$upload_info['baseurlpath'] = '/' . $baseurlpath . '/';
			} else {
				$upload_info = false;
			}
		}

		return $upload_info;
	}

	/**
	 * Test the time to first byte.
	 *
	 * @param string $url URL address.
	 * @param bool   $nocache Whether or not to request no cache response, by sending a Cache-Control header.
	 * @return float|false Time in seconds until the first byte is about to be transferred or false on error.
	 */
	public static function ttfb( $url, $nocache = false ) {
		$ch   = curl_init( esc_url( $url ) );
		$pass = (bool) $ch;
		$ttfb = false;
		$opts = array(
			CURLOPT_FORBID_REUSE   => 1,
			CURLOPT_FRESH_CONNECT  => 1,
			CURLOPT_HEADER         => 0,
			CURLOPT_RETURNTRANSFER => 0,
			CURLOPT_NOBODY         => 1,
			CURLOPT_FOLLOWLOCATION => 0,
			CURLOPT_USERAGENT      => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
		);

		if ( $nocache ) {
			$opts[ CURLOPT_HTTPHEADER ] = array( 'Cache-Control: no-cache' );
		}

		if ( $ch ) {
			$pass = curl_setopt_array( $ch, $opts );
		}

		if ( $pass ) {
			$pass = curl_exec( $ch );
		}

		if ( $pass ) {
			$info = curl_getinfo( $ch );
		}

		if ( isset( $info['starttransfer_time'] ) ) {
			$ttfb = $info['starttransfer_time'];
		}

		if ( $ch ) {
			curl_close( $ch );
		}

		return $ttfb;
	}
}
