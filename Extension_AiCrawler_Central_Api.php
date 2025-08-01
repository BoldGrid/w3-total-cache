<?php
/**
 * File: Extension_AiCrawler_Central_Api.php
 *
 * Wrapper for InMotion Hosting Central API calls.
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_AiCrawler_Central_Api
 *
 * Provides helper for making authenticated requests to IMH Central.
 */
class Extension_AiCrawler_Central_Api {
	/**
	 * Base API URL.
	 *
	 * @var string
	 */
	private static $api_url = IMH_CENTRAL_API_URL;

	/**
	 * API prefix used for crawler endpoints.
	 *
	 * @var string
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
	 */
	public static function call( $endpoint, $method = 'GET', array $data = array() ) {
		$url = trailingslashit( self::$api_url ) . self::$api_prefix . ltrim( $endpoint, '/' );

		$method = strtoupper( $method );
		$args   = array(
			'headers' => self::get_headers(),
			'timeout' => 30,
			'method'  => $method,
		);

		if ( 'GET' === $method && ! empty( $data ) ) {
			$url = add_query_arg( $data, $url );
		} elseif ( 'POST' === $method ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return array(
				'success' => false,
				'error'   => array(
					'code'    => 'invalid_request',
					'message' => __( 'The request is invalid or cannot be processed.', 'w3-total-cache' ),
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
	 */
	private static function get_headers() {
		$config = Dispatcher::config();

		return array(
			'X-Central-Token'  => $config->get_string( array( 'aicrawler', 'central_token' ) ),
			'X-Central-Client' => $config->get_string( array( 'aicrawler', 'central_client' ) ),
			'Accept'           => 'application/json',
		);
	}
}
