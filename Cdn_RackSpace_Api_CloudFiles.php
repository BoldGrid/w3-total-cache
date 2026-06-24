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
	 * @param array $w3tc_config {
	 *     Configuration parameters for the client.
	 *
	 *     @type string   $w3tc_access_token             The access token for API authentication.
	 *     @type array    $access_region_descriptor Describes the region for API calls.
	 *     @type callable $new_access_required      Callback to handle token renewal.
	 * }
	 *
	 * @return void
	 */
	public function __construct( $w3tc_config = array() ) {
		$this->_access_token             = $w3tc_config['access_token'];
		$this->_access_region_descriptor = self::_sanitize_region_descriptor( $w3tc_config['access_region_descriptor'] );

		$this->_new_access_required = $w3tc_config['new_access_required'];
	}

	/**
	 * Strip attacker-controlled URL bases out of the
	 * `access_region_descriptor` before any `_wp_remote_*` method can
	 * use them. The descriptor reaches the API class from
	 * {@see Cdn_RackSpaceCloudFiles_Popup} via
	 * `Util_Request::get_string('access_region_descriptor')` —
	 * attacker-controlled in transit even though the legitimate
	 * round-trip carries a Rackspace-issued descriptor. Unsetting a
	 * bad URL key drops the API call back to the
	 * `new_access_required` callback, which triggers a fresh OAuth
	 * fetch from Rackspace rather than connecting to an attacker host.
	 *
	 * Each `_wp_remote_*` method checks `! empty(...['object-store.publicURL'])`
	 * before issuing a request, so unset is the right primitive: it
	 * preserves the rest of the descriptor while neutering the SSRF
	 * primitive.
	 *
	 * @since 2.10.0
	 *
	 * @param mixed $w3tc_descriptor Raw descriptor (typically an array but
	 *                          we guard against scalar / null too).
	 *
	 * @return array
	 */
	private static function _sanitize_region_descriptor( $w3tc_descriptor ) {
		if ( ! \is_array( $w3tc_descriptor ) ) {
			return array();
		}
		/**
		 * Rackspace-issued URLs end in `.rackspacecloud.com` or
		 * `.rackcdn.com`. Leading dot in the suffix list prevents
		 * attacker-owned `*rackspacecloud.com.evil.example` from
		 * matching by accident.
		 */
		$suffixes = array( '.rackspacecloud.com', '.rackcdn.com' );
		if (
			! empty( $w3tc_descriptor['object-store.publicURL'] )
			&& ! Util_Url::is_https_public_host_with_suffix(
				$w3tc_descriptor['object-store.publicURL'],
				$suffixes
			)
		) {
			unset( $w3tc_descriptor['object-store.publicURL'] );
		}
		return $w3tc_descriptor;
	}

	/**
	 * Creates a container in the RackSpace cloud.
	 *
	 * This method uses the API to create a new container. The container name
	 * is specified as a parameter.
	 *
	 * @param string $w3tc_container The name of the container to create.
	 *
	 * @return mixed The API response on success, or an error on failure.
	 */
	public function container_create( $w3tc_container ) {
		return $this->_wp_remote_put( '/' . $w3tc_container );
	}

	/**
	 * Creates an object within a specified container in the RackSpace cloud.
	 *
	 * This method uploads an object to a container with its content and metadata.
	 *
	 * @param array $w3tc_data {
	 *     Information about the object to be created.
	 *
	 *     @type string $w3tc_container     The name of the container.
	 *     @type string $w3tc_name          The name of the object.
	 *     @type string $content       The content of the object.
	 *     @type string $content_type  Optional. The MIME type of the object.
	 * }
	 *
	 * @return mixed The API response on success, or an error on failure.
	 */
	public function object_create( $w3tc_data ) {
		$headers = array(
			'ETag' => md5( $w3tc_data['content'] ),
		);
		if ( isset( $w3tc_data['content_type'] ) ) {
			$headers['Content-Type'] = $w3tc_data['content_type'];
		}

		return $this->_wp_remote_put(
			'/' . $w3tc_data['container'] . '/' . ltrim( $w3tc_data['name'], '/' ),
			$w3tc_data['content'],
			$headers
		);
	}

	/**
	 * Retrieves metadata for an object or returns null if the object does not exist.
	 *
	 * This method sends a HEAD request to the API to fetch the metadata for
	 * the specified object.
	 *
	 * @param string $w3tc_container The name of the container.
	 * @param string $w3tc_name      The name of the object.
	 *
	 * @return array|null An associative array of metadata headers, or null if the object does not exist.
	 */
	public function object_get_meta_or_null( $w3tc_container, $w3tc_name ) {
		return $this->_wp_remote_head( '/' . $w3tc_container . '/' . ltrim( $w3tc_name, '/' ) );
	}

	/**
	 * Deletes an object from a specified container in the RackSpace cloud.
	 *
	 * This method sends a DELETE request to remove an object from a container.
	 *
	 * @param string $w3tc_container The name of the container.
	 * @param string $w3tc_name      The name of the object.
	 *
	 * @return mixed The API response on success, or an error on failure.
	 */
	public function object_delete( $w3tc_container, $w3tc_name ) {
		return $this->_wp_remote_delete( '/' . $w3tc_container . '/' . ltrim( $w3tc_name, '/' ) );
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

			$w3tc_result = wp_remote_post(
				$url_base . $uri . '?format=json',
				array(
					'headers' => $headers,
					'body'    => $body,
					/**
					 * Disabled SSL certificate path.
					 *
					 * phpcs:ignore Squiz.PHP.CommentedOutCode.Found
					 * 'sslcertificates' => __DIR__ .
					 * '/Cdn_RackSpace_Api_CaCert.pem',
					 */
					'timeout' => 120,
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

			$w3tc_result = wp_remote_get(
				$url_base . $uri . '?format=json',
				array(
					'headers' => array( 'X-Auth-Token' => $this->_access_token ),
					/**
					 * Disabled SSL certificate path.
					 *
					 * phpcs:ignore Squiz.PHP.CommentedOutCode.Found
					 * 'sslcertificates' => __DIR__ .
					 * '/Cdn_RackSpace_Api_CaCert.pem',
					 */
					'method'  => 'HEAD',
				)
			);

			if ( 404 === (int) $w3tc_result['response']['code'] ) {
				return null;
			}

			$w3tc_r = self::_decode_response( $w3tc_result );
			if ( ! $w3tc_r['auth_required'] ) {
				return $w3tc_result['headers'];
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

			$w3tc_result = wp_remote_post(
				$url_base . $uri . '?format=json',
				array(
					'headers' => array( 'X-Auth-Token' => $this->_access_token ),
					/**
					 * Disabled SSL certificate path.
					 *
					 * phpcs:ignore Squiz.PHP.CommentedOutCode.Found
					 * 'sslcertificates' => __DIR__ .
					 * '/Cdn_RackSpace_Api_CaCert.pem',
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

		return $new_object->_wp_remote_delete( $uri );
	}

	/**
	 * Decodes and validates the API response.
	 *
	 * This method checks the API response for errors and determines whether
	 * authentication is required.
	 *
	 * @param array $w3tc_result The API response from `wp_remote_*`.
	 *
	 * @return array {
	 *     An array with authentication status.
	 *
	 *     @type bool $auth_required Whether new authentication is required.
	 * }
	 *
	 * @throws \Exception If the response indicates an error or is unexpected.
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
						// Translators: 1 Reponse message.
						\__( 'Failed to reach API endpoint, got unexpected response: %1$s', 'w3-total-cache' ),
						$w3tc_result['response']['message']
					)
				)
			);
		}

		return array( 'auth_required' => false );
	}
}
