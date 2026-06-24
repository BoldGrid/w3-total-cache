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
	 * @param array $w3tc_config {
	 *     Configuration array containing the following keys.
	 *
	 *     @type string $w3tc_access_token             Access token for API authentication.
	 *     @type string $access_region_descriptor Region descriptor for the API.
	 *     @type bool   $new_access_required      Flag indicating if new access is required.
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
	 * for the rationale; this class uses the `cdn.publicURL` key
	 * instead of `object-store.publicURL`.
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
			! empty( $w3tc_descriptor['cdn.publicURL'] )
			&& ! Util_Url::is_https_public_host_with_suffix(
				$w3tc_descriptor['cdn.publicURL'],
				$suffixes
			)
		) {
			unset( $w3tc_descriptor['cdn.publicURL'] );
		}
		return $w3tc_descriptor;
	}

	/**
	 * Retrieves the list of services available in the API.
	 *
	 * @return array An array of services or an empty array if no services are found.
	 */
	public function services() {
		$w3tc_r = $this->_wp_remote_get( '/services' );
		if ( ! isset( $w3tc_r['services'] ) ) {
			return array();
		}

		return $w3tc_r['services'];
	}

	/**
	 * Retrieves details of a specific service.
	 *
	 * @param string $w3tc_service The service identifier.
	 *
	 * @return array The response containing the service details.
	 */
	public function service_get( $w3tc_service ) {
		$response = $this->_wp_remote_get( '/services/' . $w3tc_service );

		// expand links to links_by_rel.
		if ( isset( $response['links'] ) ) {
			$by_rel = array();
			foreach ( $response['links'] as $w3tc_r ) {
				$by_rel[ $w3tc_r['rel'] ] = $w3tc_r;
			}
			$response['links_by_rel'] = $by_rel;
		}

		return $response;
	}

	/**
	 * Creates a new service with the given data.
	 *
	 * @param array $w3tc_data {
	 *     The data used to create the service.
	 *
	 *     @type string $flavor_id The flavor ID for the service. Defaults to 'cdn'.
	 * }
	 *
	 * @return mixed The response from the API on success, or an error on failure.
	 */
	public function service_create( $w3tc_data ) {
		// required static.
		$w3tc_data['flavor_id'] = 'cdn';

		return $this->_wp_remote_post(
			'/services',
			wp_json_encode( $w3tc_data ),
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
	 * @param array  $w3tc_data       The data to update the service.
	 *
	 * @return mixed The response from the API on success, or an error on failure.
	 */
	public function service_set( $service_id, $w3tc_data ) {
		return $this->_wp_remote_patch(
			'/services/' . $service_id,
			wp_json_encode( $w3tc_data ),
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
	 * @param string $w3tc_url        The URL to be purged.
	 *
	 * @return mixed The response from the API on success, or an error on failure.
	 */
	public function purge( $service_id, $w3tc_url ) {
		return $this->_wp_remote_delete( '/services/' . $service_id . '/assets?url=' . rawurlencode( $w3tc_url ) );
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
	 * Makes a PATCH request to the specified URI with the given body and headers.
	 *
	 * @param string $uri     The URI to request.
	 * @param array  $body    The body of the request.
	 * @param array  $headers {
	 *     The headers for the request.
	 *
	 *     @type string $X-Auth-Token The authentication token for the request.
	 * }
	 *
	 * @return mixed The response from the API on success.
	 */
	private function _wp_remote_patch( $uri, $body = array(), $headers = array() ) {
		if ( ! empty( $this->_access_region_descriptor['cdn.publicURL'] ) ) {
			$url_base                = $this->_access_region_descriptor['cdn.publicURL'];
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
					'method'  => 'PATCH',
				)
			);

			$w3tc_r = self::_decode_response( $w3tc_result );
			if ( ! $w3tc_r['auth_required'] ) {
				$location = explode( '/', $w3tc_result['headers']['location'] );

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
	 * @param array  $headers {
	 *     The headers for the request.
	 *
	 *     @type string $X-Auth-Token The authentication token for the request.
	 * }
	 *
	 * @return mixed The response from the API on success.
	 */
	private function _wp_remote_post( $uri, $body = array(), $headers = array() ) {
		if ( ! empty( $this->_access_region_descriptor['cdn.publicURL'] ) ) {
			$url_base                = $this->_access_region_descriptor['cdn.publicURL'];
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
					 * 'sslcertificates' => __DIR__ . '/Cdn_RackSpace_Api_CaCert.pem'
					 */
				)
			);

			$w3tc_r = self::_decode_response( $w3tc_result );
			if ( ! $w3tc_r['auth_required'] ) {
				$location = explode( '/', $w3tc_result['headers']['location'] );

				return $location[ count( $location ) - 1 ];
			}
		}

		$new_object = call_user_func( $this->_new_access_required );

		return $new_object->_wp_remote_post( $uri, $body );
	}

	/**
	 * Makes a DELETE request to the specified URI with the given headers.
	 *
	 * @param string $uri The URI to request.
	 * @param array  $headers {
	 *     The headers for the request.
	 *
	 *     @type string $X-Auth-Token The authentication token for the request.
	 * }
	 *
	 * @return void
	 */
	private function _wp_remote_delete( $uri, $headers = array() ) {
		if ( ! empty( $this->_access_region_descriptor['cdn.publicURL'] ) ) {
			$url_base                = $this->_access_region_descriptor['cdn.publicURL'];
			$headers['X-Auth-Token'] = $this->_access_token;

			$w3tc_result = wp_remote_post(
				$url_base . $uri,
				array(
					'headers' => $headers,
					/**
					 * Disabled SSL certificate path.
					 *
					 * phpcs:ignore Squiz.PHP.CommentedOutCode.Found
					 * 'sslcertificates' => __DIR__ . '/Cdn_RackSpace_Api_CaCert.pem',
					 */
					'method'  => 'DELETE',
				)
			);

			$w3tc_r = self::_decode_response( $w3tc_result );
			if ( ! $w3tc_r['auth_required'] ) {
				return;
			}
		}

		$new_object = call_user_func( $this->_new_access_required );

		return $new_object->_wp_remote_delete( $uri, array() );
	}

	/**
	 * Decodes the JSON response from the API for a GET request.
	 *
	 * @param mixed $w3tc_result The result from the GET request.
	 *
	 * @return array The decoded response JSON.
	 *
	 * @throws \Exception If the response is not valid or contains errors.
	 */
	private static function _decode_response_json( $w3tc_result ) {
		if ( is_wp_error( $w3tc_result ) ) {
			throw new \Exception( 'Failed to reach API endpoint' );
		}

		if ( empty( $w3tc_result['body'] ) ) {
			$response_json = array();
		} else {
			if ( 'Unauthorized' === $w3tc_result['body'] ) {
				return array(
					'response_json' => array(),
					'auth_required' => true,
				);
			}

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
	 * Decodes the response from the API for a PATCH or POST request.
	 *
	 * @param mixed $w3tc_result The result from the PATCH or POST request.
	 *
	 * @return array The decoded response.
	 *
	 * @throws \Exception If the response contains errors or is unauthorized.
	 */
	private static function _decode_response( $w3tc_result ) {
		if ( is_wp_error( $w3tc_result ) ) {
			throw new \Exception( 'Failed to reach API endpoint' );
		}

		if ( ! in_array( (int) $w3tc_result['response']['code'], array( 200, 201, 202, 204 ), true ) ) {
			if ( 'Unauthorized' === $w3tc_result['response']['message'] ) {
				return array(
					'response_json' => array(),
					'auth_required' => true,
				);
			}

			// try to decode response.
			$response_json = @json_decode( $w3tc_result['body'], true );
			if ( is_null( $response_json ) || ! isset( $response_json['message'] ) ) {
				throw new \Exception(
					\esc_html(
						sprintf(
							// Translators: 1 Response message.
							\__( 'Failed to reach API endpoint, got unexpected response: %1$s', 'w3-total-cache' ),
							$w3tc_result['response']['message']
						)
					)
				);
			} else {
				$errors = array();
				if ( is_string( $response_json['message'] ) ) {
					$errors[] = $response_json['message'];
				} elseif ( isset( $response_json['message']['errors'] ) ) {
					foreach ( $response_json['message']['errors'] as $error ) {
						$errors[] = $error['message'];
					}
				}

				throw new \Exception( \esc_html( implode( ';', $errors ) ) );
			}
		}

		return array( 'auth_required' => false );
	}
}
