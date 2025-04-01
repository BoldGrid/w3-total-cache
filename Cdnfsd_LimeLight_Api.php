<?php
/**
 * File: Cdnfsd_LimeLight_Api.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdnfsd_LimeLight_Api
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */
class Cdnfsd_LimeLight_Api {
	/**
	 * URL base
	 *
	 * @var string
	 */
	private $url_base;

	/**
	 * Constructor to initialize the API client with required credentials.
	 *
	 * @param string $short_name The short name of the account.
	 * @param string $username   The username for authentication.
	 * @param string $api_key    The API key for authentication.
	 *
	 * @return void
	 */
	public function __construct( $short_name, $username, $api_key ) {
		$this->url_base = 'https://purge.llnw.com/purge/v1/account/' . $short_name . '/requests';
		$this->username = $username;
		$this->api_key  = $api_key;
	}

	/**
	 * Purges specified items from the cache.
	 *
	 * @param array $items The list of items (patterns) to purge.
	 *
	 * @return array Response from the API, typically the result of the purge operation.
	 */
	public function purge( $items ) {
		$body = wp_json_encode( array( 'patterns' => $items ) );
		return $this->_wp_remote_post( '', $body );
	}

	/**
	 * Retrieves data from a specified API endpoint.
	 *
	 * @param string $uri The URI of the API endpoint to call.
	 *
	 * @return array The response data from the API.
	 */
	public function get( $uri ) {
		return $this->_wp_remote_get( $uri );
	}

	/**
	 * Sends a GET request to the specified API endpoint with necessary headers.
	 *
	 * @param string $uri     The URI of the API endpoint to call.
	 * @param string $body    Optional body data to send with the request.
	 * @param array  $headers Optional headers to send with the request.
	 *
	 * @return array The decoded response from the API.
	 */
	private function _wp_remote_get( $uri, $body = '', $headers = array() ) {
		$url     = $this->url_base . $uri;
		$headers = $this->_add_headers( $headers, $url, 'GET', $body );

		$result = wp_remote_get(
			$url,
			array(
				'headers' => $headers,
				'body'    => $body,
			)
		);

		return $this->_decode_response( $result );
	}

	/**
	 * Sends a POST request to the specified API endpoint with necessary headers.
	 *
	 * @param string $uri     The URI of the API endpoint to call.
	 * @param string $body    The body data to send with the request.
	 * @param array  $headers Optional headers to send with the request.
	 *
	 * @return array The decoded response from the API.
	 */
	private function _wp_remote_post( $uri, $body, $headers = array() ) {
		$url     = $this->url_base . $uri;
		$headers = $this->_add_headers( $headers, $url, 'POST', $body );

		$result = wp_remote_post(
			$url,
			array(
				'headers' => $headers,
				'body'    => $body,
			)
		);

		return $this->_decode_response( $result );
	}

	/**
	 * Adds necessary headers for the API request.
	 *
	 * @param array  $headers {
	 *     The headers for the request.
	 *
	 *     @type string $Content-Type              The content type for the request, usually 'application/json'.
	 *     @type string $X-LLNW-Security-Principal The security principal for authentication.
	 *     @type string $X-LLNW-Security-Timestamp The timestamp for the request, in milliseconds.
	 *     @type string $X-LLNW-Security-Token     The HMAC token for security, based on the method, URL, timestamp, and body.
	 * }
	 * @param string $url     The URL of the API endpoint.
	 * @param string $method  The HTTP method to use ('GET' or 'POST').
	 * @param string $body    The body data to include with the request.
	 *
	 * @return array The modified headers.
	 */
	private function _add_headers( $headers, $url, $method, $body ) {
		$timestamp = '' . ( time() * 1000 );

		$headers['Content-Type']              = 'application/json';
		$headers['X-LLNW-Security-Principal'] = $this->username;
		$headers['X-LLNW-Security-Timestamp'] = $timestamp;
		$headers['X-LLNW-Security-Token']     = hash_hmac(
			'sha256',
			$method . $url . $timestamp . $body,
			pack( 'H*', $this->api_key )
		);

		return $headers;
	}

	/**
	 * Decodes the response from the API and handles errors.
	 *
	 * @param array $result {
	 *     The result from the API request.
	 *
	 *     @type string $body The body of the API response.
	 *     @type array  $response Response metadata including status code.
	 * }
	 *
	 * @return array The decoded JSON response.
	 *
	 * @throws \Exception If the response is an error or cannot be decoded.
	 */
	private function _decode_response( $result ) {
		if ( is_wp_error( $result ) ) {
			throw new \Exception( \esc_html__( 'Failed to reach API endpoint', 'w3-total-cache' ) );
		}

		$response_json = @json_decode( $result['body'], true );
		if ( is_null( $response_json ) ) {
			throw new \Exception(
				\esc_html(
					sprintf(
						// Translators: 1 Result body.
						\__( 'Failed to reach API endpoint, got unexpected response: %1$s', 'w3-total-cache' ),
						$result['body']
					)
				)
			);
		}

		if ( ! in_array( (int) $result['response']['code'], array( 200, 201, 202, 204 ), true ) ) {
			if ( isset( $response_json['errors'] ) &&
				isset( $response_json['errors'][0]['description'] ) ) {
				throw new \Exception( \esc_html( $response_json['errors'][0]['description'] ) );
			}

			throw new \Exception( \esc_html( $result['body'] ) );
		}

		return $response_json;
	}
}
