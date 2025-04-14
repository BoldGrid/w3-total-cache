<?php
/**
 * File: Cdn_RackSpace_Api_CloudFilesCdn.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_RackSpace_Api_CloudFilesCdn
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class Cdn_RackSpace_Api_CloudFilesCdn {
	/**
	 * Access token.
	 *
	 * @var string
	 */
	private $_access_token;

	/**
	 * Access region descriptor.
	 *
	 * @var string
	 */
	private $_access_region_descriptor;

	/**
	 * New access required flag.
	 *
	 * @var bool
	 */
	private $_new_access_required;

	/**
	 * Constructor for the Rackspace Cloud Files CDN API class.
	 *
	 * Initializes the object with configuration parameters such as access token,
	 * access region descriptor, and a callback for renewing access.
	 *
	 * @param array $config {
	 *     Configuration parameters for the API.
	 *
	 *     @type string   $access_token             The access token for API authentication.
	 *     @type array    $access_region_descriptor Region-specific API endpoint details.
	 *     @type callable $new_access_required      Callback function to handle access renewal.
	 * }
	 *
	 * @return void
	 */
	public function __construct( $config = array() ) {
		$this->_access_token             = $config['access_token'];
		$this->_access_region_descriptor = $config['access_region_descriptor'];
		$this->_new_access_required      = $config['new_access_required'];
	}

	/**
	 * Retrieves the list of containers from the Cloud Files service.
	 *
	 * Uses a GET request to fetch the list of containers associated with the account.
	 *
	 * @return array|string|WP_Error The response data or error from the API.
	 */
	public function containers() {
		return $this->_wp_remote_get( '' );
	}

	/**
	 * Retrieves metadata for a specific container.
	 *
	 * Sends a HEAD request to fetch details about a specified container, such as size and object count.
	 *
	 * @param string $container The name of the container to fetch metadata for.
	 *
	 * @return array|string|WP_Error The response headers or error from the API.
	 */
	public function container_get( $container ) {
		return $this->_wp_remote_head( '/' . $container );
	}

	/**
	 * Enables CDN for a specific container.
	 *
	 * Sends a PUT request to enable the Content Delivery Network (CDN) for a specified container.
	 *
	 * @param string $container The name of the container to enable CDN for.
	 *
	 * @return mixed The API response on success, or an error on failure.
	 */
	public function container_cdn_enable( $container ) {
		return $this->_wp_remote_put(
			'/' . $container,
			array(
				'X-Cdn-Enabled' => 'True',
			)
		);
	}

	/**
	 * Sends a GET request to the Rackspace API.
	 *
	 * Retrieves data from the specified URI. If authentication is required, it attempts
	 * to renew the access token and re-execute the request.
	 *
	 * @param string $uri The endpoint URI for the GET request.
	 *
	 * @return array|string|WP_Error The response data or error from the API.
	 */
	private function _wp_remote_get( $uri ) {
		if ( ! empty( $this->_access_region_descriptor['object-cdn.publicURL'] ) ) {
			$url_base = $this->_access_region_descriptor['object-cdn.publicURL'];

			$result = wp_remote_get(
				$url_base . $uri . '?format=json',
				array(
					'headers' => 'X-Auth-Token: ' . $this->_access_token,
					// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
					// 'sslcertificates' => dirname( __FILE__ ) . '/Cdn_RackSpace_Api_CaCert.pem'
				)
			);

			$r = self::_decode_response_json( $result );
			if ( ! $r['auth_required'] ) {
				return $r['response_json'];
			}
		}

		$new_object = call_user_func( $this->_new_access_required );

		return $new_object->_wp_remote_get( $uri );
	}

	/**
	 * Sends a HEAD request to the Rackspace API.
	 *
	 * Retrieves headers from the specified URI. If authentication is required, it attempts
	 * to renew the access token and re-execute the request.
	 *
	 * @param string $uri    The endpoint URI for the HEAD request.
	 * @param string $method The HTTP method to use (default: 'GET').
	 *
	 * @return array|string|WP_Error The response headers or error from the API.
	 */
	private function _wp_remote_head( $uri, $method = 'GET' ) {
		if ( ! empty( $this->_access_region_descriptor['object-cdn.publicURL'] ) ) {
			$url_base = $this->_access_region_descriptor['object-cdn.publicURL'];

			$result = wp_remote_get(
				$url_base . $uri . '?format=json',
				array(
					'headers' => 'X-Auth-Token: ' . $this->_access_token,
					// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
					// 'sslcertificates' => dirname( __FILE__ ) . '/Cdn_RackSpace_Api_CaCert.pem',
					'method'  => 'HEAD',
				)
			);

			$r = self::_decode_response( $result );
			if ( ! $r['auth_required'] ) {
				return $result['headers'];
			}
		}

		$new_object = call_user_func( $this->_new_access_required );

		return $new_object->_wp_remote_head( $uri, $body );
	}

	/**
	 * Sends a PUT request to the Rackspace API.
	 *
	 * Updates data at the specified URI. If authentication is required, it attempts
	 * to renew the access token and re-execute the request.
	 *
	 * @param string $uri     The endpoint URI for the PUT request.
	 * @param array  $body    The body of the PUT request.
	 * @param array  $headers The headers for the PUT request.
	 *
	 * @return void
	 */
	private function _wp_remote_put( $uri, $body = array(), $headers = array() ) {
		if ( ! empty( $this->_access_region_descriptor['object-cdn.publicURL'] ) ) {
			$url_base                = $this->_access_region_descriptor['object-cdn.publicURL'];
			$headers['X-Auth-Token'] = $this->_access_token;

			$result = wp_remote_post(
				$url_base . $uri,
				array(
					'headers' => $headers,
					'body'    => $body,
					// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
					// 'sslcertificates' => dirname( __FILE__ ) . '/Cdn_RackSpace_Api_CaCert.pem',
					'method'  => 'PUT',
				)
			);

			$r = self::_decode_response( $result );
			if ( ! $r['auth_required'] ) {
				return;
			}
		}

		$new_object = call_user_func( $this->_new_access_required );

		return $new_object->_wp_remote_put( $uri, $body, $headers );
	}

	/**
	 * Decodes a JSON response from the Rackspace API.
	 *
	 * Validates the response, checks for errors, and decodes the JSON content.
	 *
	 * @param array $result The result array returned by a WordPress HTTP request.
	 *
	 * @return array {
	 *     Decoded response data.
	 *
	 *     @type array $response_json The JSON-decoded response data.
	 *     @type bool  $auth_required Whether re-authentication is required.
	 * }
	 *
	 * @throws \Exception If the response indicates an error or invalid JSON.
	 */
	private static function _decode_response_json( $result ) {
		if ( is_wp_error( $result ) ) {
			throw new \Exception( 'Failed to reach API endpoint' );
		}

		if ( empty( $result['body'] ) ) {
			$response_json = array();
		} else {
			$response_json = @json_decode( $result['body'], true );
			if ( is_null( $response_json ) ) {
				throw new \Exception(
					sprintf(
						// Translators: 1 Result body.
						\esc_html__( 'Failed to reach API endpoint, got unexpected response: %1$s', 'w3-total-cache' ),
						\wp_kses_post( $result['body'] )
					)
				);
			}
		}

		if ( ! in_array( (int) $result['response']['code'], array( 200, 201, 202, 204 ), true ) ) {
			throw new \Exception( \wp_kses_post( $result['body'] ) );
		}

		return array(
			'response_json' => $response_json,
			'auth_required' => false,
		);
	}

	/**
	 * Decodes a response from the Rackspace API.
	 *
	 * Validates the response and checks if re-authentication is required.
	 *
	 * @param array $result The result array returned by a WordPress HTTP request.
	 *
	 * @return array {
	 *     Response metadata.
	 *
	 *     @type bool $auth_required Whether re-authentication is required.
	 * }
	 *
	 * @throws \Exception If the response indicates an error.
	 */
	private static function _decode_response( $result ) {
		if ( is_wp_error( $result ) ) {
			throw new \Exception( 'Failed to reach API endpoint' );
		}

		if ( ! in_array( (int) $result['response']['code'], array( 200, 201, 202, 204 ), true ) ) {
			if ( 'Unauthorized' === $result['response']['message'] ) {
				return array(
					'auth_required' => true,
				);
			}

			throw new \Exception(
				\esc_html(
					sprintf(
						// Translators: 1 Response message.
						\__( 'Failed to reach API endpoint, got unexpected response: %1$s', 'w3-total-cache' ),
						$result['response']['message']
					)
				)
			);
		}

		return array( 'auth_required' => false );
	}
}
