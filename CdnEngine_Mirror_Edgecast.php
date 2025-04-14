<?php
/**
 * File: CdnEngine_Mirror_Edgecast.php
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC_CDN_EDGECAST_PURGE_URL' ) ) {
	define( 'W3TC_CDN_EDGECAST_PURGE_URL', 'http://api.edgecast.com/v2/mcc/customers/%s/edge/purge' );
}

define( 'W3TC_CDN_EDGECAST_MEDIATYPE_WINDOWS_MEDIA_STREAMING', 1 );
define( 'W3TC_CDN_EDGECAST_MEDIATYPE_FLASH_MEDIA_STREAMING', 2 );
define( 'W3TC_CDN_EDGECAST_MEDIATYPE_HTTP_LARGE_OBJECT', 3 );
define( 'W3TC_CDN_EDGECAST_MEDIATYPE_HTTP_SMALL_OBJECT', 8 );
define( 'W3TC_CDN_EDGECAST_MEDIATYPE_APPLICATION_DELIVERY_NETWORK', 14 );

/**
 * Class CdnEngine_Mirror_Edgecast
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class CdnEngine_Mirror_Edgecast extends CdnEngine_Mirror {
	/**
	 * Constructor for the class.
	 *
	 * @param array $config Configuration array with API credentials and other settings.
	 *
	 * @return void
	 */
	public function __construct( $config = array() ) {
		$config = array_merge(
			array(
				'apiid'  => '',
				'apikey' => '',
			),
			$config
		);

		parent::__construct( $config );
	}

	/**
	 * Purges specified files from the CDN.
	 *
	 * @param array $files  Array of files to purge.
	 * @param array $results Reference to an array where the purge results will be stored.
	 *
	 * @return bool True if all files were purged successfully, false otherwise.
	 */
	public function purge( $files, &$results ) {
		if ( empty( $this->_config['account'] ) ) {
			$results = $this->_get_results( $files, W3TC_CDN_RESULT_HALT, __( 'Empty account #.', 'w3-total-cache' ) );

			return false;
		}

		if ( empty( $this->_config['token'] ) ) {
			$results = $this->_get_results( $files, W3TC_CDN_RESULT_HALT, __( 'Empty token.', 'w3-total-cache' ) );

			return false;
		}

		foreach ( $files as $file ) {
			$local_path  = $file['local_path'];
			$remote_path = $file['remote_path'];

			$url = $this->format_url( $remote_path );

			$error = null;

			if ( $this->_purge_content( $url, W3TC_CDN_EDGECAST_MEDIATYPE_HTTP_SMALL_OBJECT, $error ) ) {
				$results[] = $this->_get_result(
					$local_path,
					$remote_path,
					W3TC_CDN_RESULT_OK,
					__( 'OK', 'w3-total-cache' ),
					$file
				);
			} else {
				$results[] = $this->_get_result(
					$local_path,
					$remote_path,
					W3TC_CDN_RESULT_ERROR,
					sprintf(
						// Translators: 1 error message.
						__(
							'Unable to purge (%1$s).',
							'w3-total-cache'
						),
						$error
					),
					$file
				);
			}
		}

		return ! $this->_is_error( $results );
	}

	/**
	 * Purges all files from the CDN.
	 *
	 * @param array $results Reference to an array where the purge results will be stored.
	 *
	 * @return bool True if the purge was successful, false otherwise.
	 */
	public function purge_all( &$results ) {
		return $this->purge(
			array(
				array(
					'local_path'  => '*',
					'remote_path' => '*',
				),
			),
			$results
		);
	}

	/**
	 * Sends a request to purge content from the CDN.
	 *
	 * @param string $path   The path of the content to purge.
	 * @param string $type   The type of the content to purge.
	 * @param string $error  Reference to a variable where any error message will be stored.
	 *
	 * @return bool True if the purge request was successful, false otherwise.
	 */
	public function _purge_content( $path, $type, &$error ) {
		$url  = sprintf( W3TC_CDN_EDGECAST_PURGE_URL, $this->_config['account'] );
		$args = array(
			'method'     => 'PUT',
			'user-agent' => W3TC_POWERED_BY,
			'headers'    => array(
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => sprintf( 'TOK:%s', $this->_config['token'] ),
			),
			'body'       => wp_json_encode(
				array(
					'MediaPath' => $path,
					'MediaType' => $type,
				)
			),
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$error = implode( '; ', $response->get_error_messages() );

			return false;
		}

		switch ( $response['response']['code'] ) {
			case 200:
				return true;

			case 400:
				$error = __( 'Invalid Request Parameter', 'w3-total-cache' );
				return false;

			case 403:
				$error = __( 'Authentication Failure or Insufficient Access Rights', 'w3-total-cache' );
				return false;

			case 404:
				$error = __( 'Invalid Request URI', 'w3-total-cache' );
				return false;

			case 405:
				$error = __( 'Invalid Request', 'w3-total-cache' );
				return false;

			case 500:
				$error = __( 'Server Error', 'w3-total-cache' );
				return false;
		}

		$error = 'Unknown error';

		return false;
	}
}
