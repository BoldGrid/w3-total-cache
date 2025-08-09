<?php
/**
 * File: Extension_AiCrawler_Central_Api.php
 *
 * Wrapper for InMotion Hosting Central API calls.
 *
 * @package W3TC
 * @since   x.x.x
 */

namespace W3TC;

/**
 * Class Extension_AiCrawler_Central_Api
 *
 * Provides helper for making authenticated requests to IMH Central.
 *
 * @since x.x.x
 */
class Extension_AiCrawler_Central_Api {
	/**
	 * Base API URL.
	 *
	 * @var   string
	 * @since x.x.x
	 */
	private static $api_url = IMH_CENTRAL_API_URL;

	/**
	 * API prefix used for crawler endpoints.
	 *
	 * @var   string
	 * @since x.x.x
	 */
	private static $api_prefix = 'central-crawler/';

	/**
	 * Executes a request against the Central API.
	 *
	 * @param string $endpoint API endpoint to call.
	 * @param string $method   HTTP method to use. Defaults to GET.
	 * @param array  $data     Optional data to send with the request.
	 *
	 * @return array Standardized response array.
	 *               This will include 'success' (bool), 'data' (array) or 'error' (array).
	 *               'success' will be true if the request was successful,
	 *               and 'data' will contain the response data.
	 *               If there was an error, 'success' will be false and 'error'
	 *               will contain an array with 'code' and 'message'.
	 * @since  x.x.x
	 */
	public static function call( $endpoint, $method = 'GET', array $data = array() ) {
		$url = trailingslashit( self::$api_url ) . self::$api_prefix . ltrim( $endpoint, '/' );

		$method = strtoupper( $method );
		$args   = array(
			'headers'   => self::get_headers(),
			'sslverify' => false,
			'timeout'   => 30,
			'method'    => $method,
		);

		if ( 'GET' === $method && ! empty( $data ) ) {
			$url = add_query_arg( $data, $url );
		} elseif ( 'POST' === $method ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		return self::parse_response( $response );
	}

	/**
	 * Parses the API response.
	 *
	 * @param array|\WP_Error $response The response from wp_remote_request().
	 *
	 * @return array Parsed response with status and data.
	 * @since  x.x.x
	 */
	private static function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => array(
					'code'    => 'request_failed',
					'message' => $response->get_error_message(),
				),
			);
		} elseif ( '2' !== substr( (string) wp_remote_retrieve_response_code( $response ), 0, 1 ) ) {
			return array(
				'success' => false,
				'error'   => array(
					'code'    => wp_remote_retrieve_response_code( $response ),
					'message' => wp_remote_retrieve_response_message( $response ),
				),
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return array(
				'success' => false,
				'error'   => array(
					'code'    => 'invalid_request',
					'message' => __( 'The request is invalid or cannot be processed.', 'w3-total-cache' ),
				),
			);
		}

		return array(
			'success' => true,
			'data'    => $data,
		);
	}

	/**
	 * Retrieves required headers for API authentication.
	 *
	 * @return array
	 * @since  x.x.x
	 */
	private static function get_headers() {
		$config = Dispatcher::config();

		return array(
			'X-Central-Token'  => $config->get_string( array( 'aicrawler', 'imh_central_token' ), '' ),
			'X-Central-Client' => $config->get_string( array( 'aicrawler', 'imh_central_client' ), '' ),
			'Accept'           => 'application/json',
		);
	}
}
