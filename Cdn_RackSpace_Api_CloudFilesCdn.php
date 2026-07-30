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
	 * @param array $w3tc_config {
	 *     Configuration parameters for the API.
	 *
	 *     @type string   $w3tc_access_token             The access token for API authentication.
	 *     @type array    $access_region_descriptor Region-specific API endpoint details.
	 *     @type callable $new_access_required      Callback function to handle access renewal.
	 * }
	 *
	 * @return void
	 */
	public function __construct( $w3tc_config = array() ) {
		$this->_access_token             = $w3tc_config['access_token'];
		$this->_access_region_descriptor = self::_sanitize_region_descriptor( $w3tc_config['access_region_descriptor'] );
		$this->_new_access_required      = $w3tc_config['new_access_required'];
	}

	/**
	 * Strip attacker-controlled URL bases out of the
	 * `access_region_descriptor` before any `_wp_remote_*` method can
	 * use them. See {@see Cdn_RackSpace_Api_CloudFiles::_sanitize_region_descriptor()}
	 * for the rationale; this class uses the `object-cdn.publicURL`
	 * key.
	 *
	 * @since 2.10.0
	 *
	 * @param mixed $w3tc_descriptor Raw descriptor.
	 *
	 * @return array
	 */
	private static function _sanitize_region_descriptor( $w3tc_descriptor ) {
		if ( ! \is_array( $w3tc_descriptor ) ) {
			return array();
		}
		$suffixes = array( '.rackspacecloud.com', '.rackcdn.com' );
		if (
			! empty( $w3tc_descriptor['object-cdn.publicURL'] )
			&& ! Util_Url::is_https_public_host_with_suffix(
				$w3tc_descriptor['object-cdn.publicURL'],
				$suffixes
			)
		) {
			unset( $w3tc_descriptor['object-cdn.publicURL'] );
		}
		return $w3tc_descriptor;
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
	 * @param string $w3tc_container The name of the container to fetch metadata for.
	 *
	 * @return array|string|WP_Error The response headers or error from the API.
	 */
	public function container_get( $w3tc_container ) {
		return $this->_wp_remote_head( '/' . $w3tc_container );
	}

	/**
	 * Enables CDN for a specific container.
	 *
	 * Sends a PUT request to enable the Content Delivery Network (CDN) for a specified container.
	 *
	 * @param string $w3tc_container The name of the container to enable CDN for.
	 *
	 * @return mixed The API response on success, or an error on failure.
	 */
	public function container_cdn_enable( $w3tc_container ) {
		return $this->_wp_remote_put(
			'/' . $w3tc_container,
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

			$w3tc_result = wp_remote_get(
				$url_base . $uri . '?format=json',
				array(
					'headers' => 'X-Auth-Token: ' . $this->_access_token,
					/**
					 * Disabled SSL certificate path.
					 *
					 * phpcs:ignore Squiz.PHP.CommentedOutCode.Found
					 * 'sslcertificates' => __DIR__ . '/Cdn_RackSpace_Api_CaCert.pem'
					 */
				)
			);

			$w3tc_r = self::_decode_response_json( $w3tc_result );
			if ( ! $w3tc_r['auth_required'] ) {
				return $w3tc_r['response_json'];
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

			$w3tc_result = wp_remote_get(
				$url_base . $uri . '?format=json',
				array(
					'headers' => 'X-Auth-Token: ' . $this->_access_token,
					/**
					 * Disabled SSL certificate path.
					 *
					 * phpcs:ignore Squiz.PHP.CommentedOutCode.Found
					 * 'sslcertificates' => __DIR__ . '/Cdn_RackSpace_Api_CaCert.pem',
					 */
					'method'  => 'HEAD',
				)
			);

			$w3tc_r = self::_decode_response( $w3tc_result );
			if ( ! $w3tc_r['auth_required'] ) {
				return $w3tc_result['headers'];
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

			$w3tc_result = wp_remote_post(
				$url_base . $uri,
				array(
					'headers' => $headers,
					'body'    => $body,
					/**
					 * Disabled SSL certificate path.
					 *
					 * phpcs:ignore Squiz.PHP.CommentedOutCode.Found
					 * 'sslcertificates' => __DIR__ . '/Cdn_RackSpace_Api_CaCert.pem',
					 */
					'method'  => 'PUT',
				)
			);

			$w3tc_r = self::_decode_response( $w3tc_result );
			if ( ! $w3tc_r['auth_required'] ) {
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
	 * @param array $w3tc_result The result array returned by a WordPress HTTP request.
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
	private static function _decode_response_json( $w3tc_result ) {
		if ( is_wp_error( $w3tc_result ) ) {
			throw new \Exception( 'Failed to reach API endpoint' );
		}

		if ( empty( $w3tc_result['body'] ) ) {
			$response_json = array();
		} else {
			$response_json = @json_decode( $w3tc_result['body'], true );
			if ( is_null( $response_json ) ) {
				throw new \Exception(
					sprintf(
						// Translators: 1 Result body.
						\esc_html__( 'Failed to reach API endpoint, got unexpected response: %1$s', 'w3-total-cache' ),
						\wp_kses_post( $w3tc_result['body'] )
					)
				);
			}
		}

		if ( ! in_array( (int) $w3tc_result['response']['code'], array( 200, 201, 202, 204 ), true ) ) {
			throw new \Exception( \wp_kses_post( $w3tc_result['body'] ) );
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
	 * @param array $w3tc_result The result array returned by a WordPress HTTP request.
	 *
	 * @return array {
	 *     Response metadata.
	 *
	 *     @type bool $auth_required Whether re-authentication is required.
	 * }
	 *
	 * @throws \Exception If the response indicates an error.
	 */
	private static function _decode_response( $w3tc_result ) {
		if ( is_wp_error( $w3tc_result ) ) {
			throw new \Exception( 'Failed to reach API endpoint' );
		}

		if ( ! in_array( (int) $w3tc_result['response']['code'], array( 200, 201, 202, 204 ), true ) ) {
			if ( 'Unauthorized' === $w3tc_result['response']['message'] ) {
				return array(
					'auth_required' => true,
				);
			}

			throw new \Exception(
				\esc_html(
					sprintf(
						// Translators: 1 Response message.
						\__( 'Failed to reach API endpoint, got unexpected response: %1$s', 'w3-total-cache' ),
						$w3tc_result['response']['message']
					)
				)
			);
		}

		return array( 'auth_required' => false );
	}
}
