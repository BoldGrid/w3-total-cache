<?php
/**
 * File: Cdn_RackSpace_Api_Cdn.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_RackSpace_Api_Cdn
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */
class Cdn_RackSpace_Api_Cdn {
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
	private $_new_access_required = null;

	/**
	 * Constructor for initializing the API client with configuration.
	 *
	 * @param array $config Configuration array containing 'access_token', 'access_region_descriptor', and 'new_access_required'.
	 *
	 * @return void
	 */
	public function __construct( $config = array() ) {
		$this->_access_token             = $config['access_token'];
		$this->_access_region_descriptor = $config['access_region_descriptor'];
		$this->_new_access_required      = $config['new_access_required'];
	}

	/**
	 * Retrieves the list of services available in the API.
	 *
	 * @return array An array of services or an empty array if no services are found.
	 */
	public function services() {
		$r = $this->_wp_remote_get( '/services' );
		if ( ! isset( $r['services'] ) ) {
			return array();
		}

		return $r['services'];
	}

	/**
	 * Retrieves details of a specific service.
	 *
	 * @param string $service The service identifier.
	 *
	 * @return array The response containing the service details.
	 */
	public function service_get( $service ) {
		$response = $this->_wp_remote_get( '/services/' . $service );

		// expand links to links_by_rel.
		if ( isset( $response['links'] ) ) {
			$by_rel = array();
			foreach ( $response['links'] as $r ) {
				$by_rel[ $r['rel'] ] = $r;
			}
			$response['links_by_rel'] = $by_rel;
		}

		return $response;
	}

	/**
	 * Creates a new service with the given data.
	 *
	 * @param array $data The data used to create the service.
	 *
	 * @return mixed The response from the API on success, or an error on failure.
	 */
	public function service_create( $data ) {
		// required static.
		$data['flavor_id'] = 'cdn';

		return $this->_wp_remote_post(
			'/services',
			wp_json_encode( $data ),
			array(
				'Accept'       => 'application/json',
				'Content-type' => 'application/json',
			)
		);
	}

	/**
	 * Updates an existing service with new data.
	 *
	 * @param string $service_id The service identifier.
	 * @param array  $data       The data to update the service.
	 *
	 * @return mixed The response from the API on success, or an error on failure.
	 */
	public function service_set( $service_id, $data ) {
		return $this->_wp_remote_patch(
			'/services/' . $service_id,
			wp_json_encode( $data ),
			array(
				'Accept'       => 'application/json',
				'Content-type' => 'application/json',
			)
		);
	}

	/**
	 * Purges a specific URL from the cache for a given service.
	 *
	 * @param string $service_id The service identifier.
	 * @param string $url        The URL to be purged.
	 *
	 * @return mixed The response from the API on success, or an error on failure.
	 */
	public function purge( $service_id, $url ) {
		return $this->_wp_remote_delete( '/services/' . $service_id . '/assets?url=' . rawurlencode( $url ) );
	}

	/**
	 * Makes a GET request to the specified URI.
	 *
	 * @param string $uri The URI to request.
	 *
	 * @return mixed The decoded response from the API.
	 */
	private function _wp_remote_get( $uri ) {
		if ( ! empty( $this->_access_region_descriptor['cdn.publicURL'] ) ) {
			$url_base = $this->_access_region_descriptor['cdn.publicURL'];

			$result = wp_remote_get(
				$url_base . $uri . '?format=json',
				array(
					'headers' => 'X-Auth-Token: ' . $this->_access_token,
					// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
					// 'sslcertificates' => dirname( __FILE__ ) . '/Cdn_RackSpace_Api_CaCert.pem',
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
	 * Makes a PATCH request to the specified URI with the given body and headers.
	 *
	 * @param string $uri     The URI to request.
	 * @param array  $body    The body of the request.
	 * @param array  $headers The headers for the request.
	 *
	 * @return mixed The response from the API on success.
	 */
	private function _wp_remote_patch( $uri, $body = array(), $headers = array() ) {
		if ( ! empty( $this->_access_region_descriptor['cdn.publicURL'] ) ) {
			$url_base                = $this->_access_region_descriptor['cdn.publicURL'];
			$headers['X-Auth-Token'] = $this->_access_token;

			$result = wp_remote_post(
				$url_base . $uri,
				array(
					'headers' => $headers,
					'body'    => $body,
					// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
					// 'sslcertificates' => dirname( __FILE__ ) . '/Cdn_RackSpace_Api_CaCert.pem',
					'method'  => 'PATCH',
				)
			);

			$r = self::_decode_response( $result );
			if ( ! $r['auth_required'] ) {
				$location = explode( '/', $result['headers']['location'] );

				return $location[ count( $location ) - 1 ];
			}
		}

		$new_object = call_user_func( $this->_new_access_required );

		return $new_object->_wp_remote_patch( $uri, $body );
	}

	/**
	 * Makes a POST request to the specified URI with the given body and headers.
	 *
	 * @param string $uri     The URI to request.
	 * @param array  $body    The body of the request.
	 * @param array  $headers The headers for the request.
	 *
	 * @return mixed The response from the API on success.
	 */
	private function _wp_remote_post( $uri, $body = array(), $headers = array() ) {
		if ( ! empty( $this->_access_region_descriptor['cdn.publicURL'] ) ) {
			$url_base                = $this->_access_region_descriptor['cdn.publicURL'];
			$headers['X-Auth-Token'] = $this->_access_token;

			$result = wp_remote_post(
				$url_base . $uri,
				array(
					'headers' => $headers,
					'body'    => $body,
					// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
					// 'sslcertificates' => dirname( __FILE__ ) . '/Cdn_RackSpace_Api_CaCert.pem'
				)
			);

			$r = self::_decode_response( $result );
			if ( ! $r['auth_required'] ) {
				$location = explode( '/', $result['headers']['location'] );

				return $location[ count( $location ) - 1 ];
			}
		}

		$new_object = call_user_func( $this->_new_access_required );

		return $new_object->_wp_remote_post( $uri, $body );
	}

	/**
	 * Makes a DELETE request to the specified URI with the given headers.
	 *
	 * @param string $uri     The URI to request.
	 * @param array  $headers The headers for the request.
	 *
	 * @return void
	 */
	private function _wp_remote_delete( $uri, $headers = array() ) {
		if ( ! empty( $this->_access_region_descriptor['cdn.publicURL'] ) ) {
			$url_base                = $this->_access_region_descriptor['cdn.publicURL'];
			$headers['X-Auth-Token'] = $this->_access_token;

			$result = wp_remote_post(
				$url_base . $uri,
				array(
					'headers' => $headers,
					// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
					// 'sslcertificates' => dirname( __FILE__ ) . '/Cdn_RackSpace_Api_CaCert.pem',
					'method'  => 'DELETE',
				)
			);

			$r = self::_decode_response( $result );
			if ( ! $r['auth_required'] ) {
				return;
			}
		}

		$new_object = call_user_func( $this->_new_access_required );

		return $new_object->_wp_remote_delete( $uri, array() );
	}

	/**
	 * Decodes the JSON response from the API for a GET request.
	 *
	 * @param mixed $result The result from the GET request.
	 *
	 * @return array The decoded response JSON.
	 *
	 * @throws \Exception If the response is not valid or contains errors.
	 */
	private static function _decode_response_json( $result ) {
		if ( is_wp_error( $result ) ) {
			throw new \Exception( 'Failed to reach API endpoint' );
		}

		if ( empty( $result['body'] ) ) {
			$response_json = array();
		} else {
			if ( 'Unauthorized' === $result['body'] ) {
				return array(
					'response_json' => array(),
					'auth_required' => true,
				);
			}

			$response_json = @wp_json_decode( $result['body'], true );
			if ( is_null( $response_json ) ) {
				throw new \Exception( 'Failed to reach API endpoint, got unexpected response ' . $result['body'] );
			}
		}

		if (
			'200' !== $result['response']['code'] &&
			'201' !== $result['response']['code'] &&
			'202' !== $result['response']['code'] &&
			'204' !== $result['response']['code']
		) {
			throw new \Exception( $result['body'] );
		}

		return array(
			'response_json' => $response_json,
			'auth_required' => false,
		);
	}

	/**
	 * Decodes the response from the API for a PATCH or POST request.
	 *
	 * @param mixed $result The result from the PATCH or POST request.
	 *
	 * @return array The decoded response.
	 *
	 * @throws \Exception If the response contains errors or is unauthorized.
	 */
	private static function _decode_response( $result ) {
		if ( is_wp_error( $result ) ) {
			throw new \Exception( 'Failed to reach API endpoint' );
		}

		if (
			'200' !== $result['response']['code'] &&
			'201' !== $result['response']['code'] &&
			'202' !== $result['response']['code'] &&
			'204' !== $result['response']['code']
		) {
			if ( 'Unauthorized' === $result['response']['message'] ) {
				return array(
					'response_json' => array(),
					'auth_required' => true,
				);
			}

			// try to decode response.
			$response_json = @wp_json_decode( $result['body'], true );
			if ( is_null( $response_json ) || ! isset( $response_json['message'] ) ) {
				throw new \Exception( 'Failed to reach API endpoint, got unexpected response ' . $result['response']['message'] );
			} else {
				$errors = array();
				if ( is_string( $response_json['message'] ) ) {
					$errors[] = $response_json['message'];
				} elseif ( isset( $response_json['message']['errors'] ) ) {
					foreach ( $response_json['message']['errors'] as $error ) {
						$errors[] = $error['message'];
					}
				}

				throw new \Exception( implode( ';', $errors ) );
			}
		}

		return array( 'auth_required' => false );
	}
}
