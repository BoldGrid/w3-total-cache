<?php
/**
 * File: Cdn_RackSpace_Api_CloudFiles.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_RackSpace_Api_CloudFiles
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class Cdn_RackSpace_Api_CloudFiles {
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
	 * Constructor for the RackSpace API CloudFiles client.
	 *
	 * Initializes the object with configuration values, including the access token,
	 * region descriptor, and a callback for handling new access requirements.
	 *
	 * @param array $config {
	 *     Configuration parameters for the client.
	 *
	 *     @type string   $access_token             The access token for API authentication.
	 *     @type array    $access_region_descriptor Describes the region for API calls.
	 *     @type callable $new_access_required      Callback to handle token renewal.
	 * }
	 *
	 * @return void
	 */
	public function __construct( $config = array() ) {
		$this->_access_token             = $config['access_token'];
		$this->_access_region_descriptor = $config['access_region_descriptor'];

		$this->_new_access_required = $config['new_access_required'];
	}

	/**
	 * Creates a container in the RackSpace cloud.
	 *
	 * This method uses the API to create a new container. The container name
	 * is specified as a parameter.
	 *
	 * @param string $container The name of the container to create.
	 *
	 * @return mixed The API response on success, or an error on failure.
	 */
	public function container_create( $container ) {
		return $this->_wp_remote_put( '/' . $container );
	}

	/**
	 * Creates an object within a specified container in the RackSpace cloud.
	 *
	 * This method uploads an object to a container with its content and metadata.
	 *
	 * @param array $data {
	 *     Information about the object to be created.
	 *
	 *     @type string $container     The name of the container.
	 *     @type string $name          The name of the object.
	 *     @type string $content       The content of the object.
	 *     @type string $content_type  Optional. The MIME type of the object.
	 * }
	 *
	 * @return mixed The API response on success, or an error on failure.
	 */
	public function object_create( $data ) {
		$headers = array(
			'ETag' => md5( $data['content'] ),
		);
		if ( isset( $data['content_type'] ) ) {
			$headers['Content-Type'] = $data['content_type'];
		}

		return $this->_wp_remote_put(
			'/' . $data['container'] . '/' . ltrim( $data['name'], '/' ),
			$data['content'],
			$headers
		);
	}

	/**
	 * Retrieves metadata for an object or returns null if the object does not exist.
	 *
	 * This method sends a HEAD request to the API to fetch the metadata for
	 * the specified object.
	 *
	 * @param string $container The name of the container.
	 * @param string $name      The name of the object.
	 *
	 * @return array|null An associative array of metadata headers, or null if the object does not exist.
	 */
	public function object_get_meta_or_null( $container, $name ) {
		return $this->_wp_remote_head( '/' . $container . '/' . ltrim( $name, '/' ) );
	}

	/**
	 * Deletes an object from a specified container in the RackSpace cloud.
	 *
	 * This method sends a DELETE request to remove an object from a container.
	 *
	 * @param string $container The name of the container.
	 * @param string $name      The name of the object.
	 *
	 * @return mixed The API response on success, or an error on failure.
	 */
	public function object_delete( $container, $name ) {
		return $this->_wp_remote_delete( '/' . $container . '/' . ltrim( $name, '/' ) );
	}

	/**
	 * Sends a PUT request to the RackSpace API.
	 *
	 * This method handles PUT requests for creating or updating resources in
	 * the RackSpace cloud. It includes headers for authentication and additional metadata.
	 *
	 * @param string $uri     The API URI for the request.
	 * @param array  $body    Optional. The body of the request.
	 * @param array  $headers Optional. Headers to include in the request.
	 *
	 * @return mixed The API response on success, or an error on failure.
	 */
	private function _wp_remote_put( $uri, $body = array(), $headers = array() ) {
		if ( ! empty( $this->_access_region_descriptor['object-store.publicURL'] ) ) {
			$url_base                = $this->_access_region_descriptor['object-store.publicURL'];
			$headers['X-Auth-Token'] = $this->_access_token;
			$headers['Accept']       = 'application/json';

			$result = wp_remote_post(
				$url_base . $uri . '?format=json',
				array(
					'headers' => $headers,
					'body'    => $body,
					// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
					// 'sslcertificates' => dirname( __FILE__ ) .
					// '/Cdn_RackSpace_Api_CaCert.pem',
					'timeout' => 120,
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
	 * Sends a HEAD request to the RackSpace API.
	 *
	 * This method handles HEAD requests to retrieve metadata for resources in
	 * the RackSpace cloud.
	 *
	 * @param string $uri The API URI for the request.
	 *
	 * @return array|null Metadata headers on success, or null if the resource does not exist.
	 */
	private function _wp_remote_head( $uri ) {
		if ( ! empty( $this->_access_region_descriptor['object-store.publicURL'] ) ) {
			$url_base = $this->_access_region_descriptor['object-store.publicURL'];

			$result = wp_remote_get(
				$url_base . $uri . '?format=json',
				array(
					'headers' => array( 'X-Auth-Token' => $this->_access_token ),
					// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
					// 'sslcertificates' => dirname( __FILE__ ) .
					// '/Cdn_RackSpace_Api_CaCert.pem',
					'method'  => 'HEAD',
				)
			);

			if ( '404' === $result['response']['code'] ) {
				return null;
			}

			$r = self::_decode_response( $result );
			if ( ! $r['auth_required'] ) {
				return $result['headers'];
			}
		}

		$new_object = call_user_func( $this->_new_access_required );

		return $new_object->_wp_remote_head( $uri );
	}

	/**
	 * Sends a DELETE request to the RackSpace API.
	 *
	 * This method handles DELETE requests for removing resources in the
	 * RackSpace cloud.
	 *
	 * @param string $uri The API URI for the request.
	 *
	 * @return mixed The API response on success, or an error on failure.
	 */
	private function _wp_remote_delete( $uri ) {
		if ( ! empty( $this->_access_region_descriptor['object-store.publicURL'] ) ) {
			$url_base = $this->_access_region_descriptor['object-store.publicURL'];

			$result = wp_remote_post(
				$url_base . $uri . '?format=json',
				array(
					'headers' => array( 'X-Auth-Token' => $this->_access_token ),
					// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
					// 'sslcertificates' => dirname( __FILE__ ) .
					// '/Cdn_RackSpace_Api_CaCert.pem',
					'method'  => 'DELETE',
				)
			);

			$r = self::_decode_response( $result );
			if ( ! $r['auth_required'] ) {
				return;
			}
		}

		$new_object = call_user_func( $this->_new_access_required );

		return $new_object->_wp_remote_delete( $uri );
	}

	/**
	 * Decodes and validates the API response.
	 *
	 * This method checks the API response for errors and determines whether
	 * authentication is required.
	 *
	 * @param array $result The API response from `wp_remote_*`.
	 *
	 * @return array {
	 *     An array with authentication status.
	 *
	 *     @type bool $auth_required Whether new authentication is required.
	 * }
	 *
	 * @throws \Exception If the response indicates an error or is unexpected.
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
						// Translators: 1 Reponse message.
						\__( 'Failed to reach API endpoint, got unexpected response: %1$s', 'w3-total-cache' ),
						$result['response']['message']
					)
				)
			);
		}

		return array( 'auth_required' => false );
	}
}
