<?php
/**
 * File: Cdn_TransparentCDN_Api.php
 *
 * @package W3TC
 *
 * @since 0.15.0
 */

namespace W3TC;

if ( ! defined( 'W3TC_CDN_TRANSPARENTCDN_PURGE_URL' ) ) {
	define( 'W3TC_CDN_TRANSPARENTCDN_PURGE_URL', 'https://api.transparentcdn.com/v1/companies/%s/invalidate/' );
}

if ( ! defined( 'W3TC_CDN_TRANSPARENTCDN_AUTHORIZATION_URL' ) ) {
	define( 'W3TC_CDN_TRANSPARENTCDN_AUTHORIZATION_URL', 'https://api.transparentcdn.com/v1/oauth2/access_token/' );
}


/**
 * Class: Cdn_TransparentCDN_Api
 *
 * @since 0.15.0
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 * phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
 */
class Cdn_TransparentCDN_Api {
	/**
	 * Token.
	 *
	 * @since 0.15.0
	 *
	 * @var string
	 */
	private $_token;

	/**
	 * Config.
	 *
	 * @since 0.15.0
	 *
	 * @var array
	 */
	private $_config;

	/**
	 * Constructs the Cdn_TransparentCDN_Api object with the provided configuration.
	 *
	 * @since 0.15.0
	 *
	 * @param array $config Configuration array to initialize the API.
	 *
	 * @return void
	 */
	public function __construct( $config = array() ) {
		$config = array_merge(
			array(
				'company_id'    => '',
				'client_id'     => '',
				'client_secret' => '',
			),
			$config
		);

		$this->_config = $config;
	}

	/**
	 * Purges the specified URLs from the content delivery network.
	 *
	 * @since 0.15.0
	 *
	 * @param array $urls Array of URLs to purge from the CDN.
	 *
	 * @return bool True if purge is successful, throws exception otherwise.
	 *
	 * @throws \Exception If required configuration parameters are missing or if the purge fails.
	 */
	public function purge( $urls ) {
		if ( empty( $this->_config['company_id'] ) ) {
			throw new \Exception( \esc_html__( 'Company ID not specified.', 'w3-total-cache' ) );
		}

		if ( empty( $this->_config['client_id'] ) ) {
			throw new \Exception( \esc_html__( 'Client ID not specified.', 'w3-total-cache' ) );
		}
		if ( empty( $this->_config['client_secret'] ) ) {
			throw new \Exception( \esc_html__( 'Client secret not specified.', 'w3-total-cache' ) );
		}

		// We ask for the authorization token.
		$this->_get_token();

		$invalidation_urls = array();
		// Included a regex filter because some of our clients reported receiving urls as "True" or "False".
		foreach ( $urls as $url ) {
			// Oh array_map+lambdas, how I miss u...
			if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
				$invalidation_urls[] = $url;
			}
		}

		if ( count( $invalidation_urls ) === 0 ) {
			$invalidation_urls[] = '';
		}

		return $this->_purge_content( $invalidation_urls );
	}

	/**
	 * Purges content from the CDN for the provided list of files.
	 *
	 * @since 0.15.0
	 *
	 * @param array $files Array of file URLs to purge.
	 *
	 * @return bool True if content purge is successful, throws exception otherwise.
	 *
	 * @throws \Exception If there is an issue with the HTTP request.
	 */
	public function _purge_content( $files ) {
		$url  = sprintf( W3TC_CDN_TRANSPARENTCDN_PURGE_URL, $this->_config['company_id'] );
		$args = array(
			'method'     => 'POST',
			'user-agent' => W3TC_POWERED_BY,
			'headers'    => array(
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => sprintf( 'Bearer %s', $this->_token ),
			),
			'body'       => wp_json_encode( array( 'urls' => $files ) ),
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$error = implode( '; ', $response->get_error_messages() );
			throw new \Exception( \esc_html( $error ) );
		}

		$api_error_message = $this->get_api_error_message_from_response( $response );

		switch ( $response['response']['code'] ) {
			case 200:
				$body = json_decode( $response['body'] );
				if ( is_array( $body->urls_to_send ) && count( $body->urls_to_send ) > 0 ) {
					// We have invalidated at least one URL.
					return true;
				} elseif ( 0 < count( $files ) && ! empty( $files[0] ) ) {
					$error = empty( $api_error_message ) ? \esc_html__( 'Invalid Request URL', 'w3-total-cache' ) : $api_error_message;
					throw new \Exception( $error );
				}

				return true;

			case 400:
				if ( count( $files ) > 0 && empty( $files[0] ) ) {
					// Test case.
					return true;
				}

				$error = empty( $api_error_message ) ? \esc_html__( 'Invalid Request Parameter', 'w3-total-cache' ) : $api_error_message;
				throw new \Exception( $error );

			case 403:
				$error = empty( $api_error_message ) ? \esc_html__( 'Authentication Failure or Insufficient Access Rights', 'w3-total-cache' ) : $api_error_message;
				throw new \Exception( $error );

			case 404:
				$error = empty( $api_error_message ) ? \esc_html__( 'Invalid Request URI', 'w3-total-cache' ) : $api_error_message;
				throw new \Exception( $error );

			case 500:
				$error = empty( $api_error_message ) ? \esc_html__( 'Server Error', 'w3-total-cache' ) : $api_error_message;
				throw new \Exception( $error );
			default:
				$error = empty( $api_error_message ) ? \esc_html__( 'Unknown error', 'w3-total-cache' ) : $api_error_message;
				throw new \Exception( $error );
		}
	}

	/**
	 * Extracts an error message from the API response payload.
	 *
	 * @since X.X.X
	 *
	 * @param array $response Response array returned by wp_remote_request().
	 *
	 * @return string
	 */
	private function get_api_error_message_from_response( $response ) {
		if ( empty( $response['body'] ) || ! is_string( $response['body'] ) ) {
			return '';
		}

		$body = $response['body'];
		$data = json_decode( $body );

		if ( isset( $data->message ) && is_string( $data->message ) ) {
			return \esc_html( $data->message );
		}

		$trimmed = trim( \wp_strip_all_tags( $body ) );
		return empty( $trimmed ) ? '' : \esc_html( $trimmed );
	}

	/**
	 * Purges all content from the CDN.
	 *
	 * @since 0.15.0
	 *
	 * @param array $results Reference to an array where results will be stored.
	 *
	 * @return bool Always returns false as this functionality is not yet implemented.
	 *
	 * @todo Implement bans using "*".
	 */
	public function purge_all( &$results ) {
		return false;
	}

	/**
	 * Retrieves an authentication token for making API requests.
	 *
	 * @since 0.15.0
	 *
	 * @return bool True if token retrieval is successful, false otherwise.
	 *
	 * @throws \Exception If the token retrieval fails.
	 */
	public function _get_token() {
		$client_id     = $this->_config['client_id'];
		$client_secret = $this->_config['client_secret'];
		$args          = array(
			'method'     => 'POST',
			'user-agent' => W3TC_POWERED_BY,
			'headers'    => array(
				'Accept'       => 'application/json',
				'Content-Type' => 'application/x-www-form-urlencoded',
			),
			'body'       => "grant_type=client_credentials&client_id=$client_id&client_secret=$client_secret",
		);

		$response = wp_remote_request( W3TC_CDN_TRANSPARENTCDN_AUTHORIZATION_URL, $args );

		if ( is_wp_error( $response ) ) {
			$error = implode( '; ', $response->get_error_messages() );
			throw new \Exception( \esc_html( $error ) );
		}

		$body = $response['body'];
		$jobj = json_decode( $body );
		if ( ! isset( $jobj->access_token ) || empty( $jobj->access_token ) ) {
			throw new \Exception( \esc_html__( 'Unable to retrieve access token.', 'w3-total-cache' ) );
		}

		$this->_token = $jobj->access_token;

		return true;
	}
}

/**
 * Class: Cdnfsd_TransparentCDN_Engine
 *
 * @since 0.15.0
 *
 * phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
 */
class Cdnfsd_TransparentCDN_Engine {
	/**
	 * Config.
	 *
	 * @since 0.15.0
	 *
	 * @var array
	 */
	private $config;

	/**
	 * Constructs the Cdnfsd_TransparentCDN_Engine object with configuration options.
	 *
	 * @since 0.15.0
	 *
	 * @param array $config Configuration options to initialize the engine.
	 *
	 * @return void
	 */
	public function __construct( $config = array() ) {
		$this->config = $config;
	}

	/**
	 * Flushes specific URLs from the CDN.
	 *
	 * @since 0.15.0
	 *
	 * @param array $urls An array of URLs to be purged from the CDN.
	 *
	 * @return void
	 *
	 * @throws \Exception If the API key is not provided or an error occurs during the purge process.
	 */
	public function flush_urls( $urls ) {
		if ( empty( $this->config['client_id'] ) ) {
			throw new \Exception( \esc_html__( 'API key not specified.', 'w3-total-cache' ) );
		}

		$api = new Cdn_TransparentCDN_Api( $this->config );

		try {
			$api->purge( $urls );
		} catch ( \Exception $ex ) {
			if ( $ex->getMessage() === 'Validation Failure: Purge url must contain one of your hostnames' ) {
				throw new \Exception(
					\esc_html__(
						'CDN site is not configured correctly: Delivery Domain must match your site domain',
						'w3-total-cache'
					)
				);
			} else {
				throw $ex;
			}
		}
	}

	/**
	 * Flushes all content from the CDN.
	 *
	 * @since 0.15.0
	 *
	 * @return void
	 *
	 * @throws \Exception If the API key is not provided or an error occurs during the purge process.
	 */
	public function flush_all() {
		if ( empty( $this->config['client_id'] ) ) {
			throw new \Exception( \esc_html__( 'API key not specified.', 'w3-total-cache' ) );
		}

		$api = new Cdn_TransparentCDN_Api( $this->config );

		$items   = array();
		$items[] = array(
			'url'       => home_url( '/' ),
			'recursive' => true,
		);

		try {
			$api->purge( array( 'items' => $items ) );
		} catch ( \Exception $ex ) {
			if ( $ex->getMessage() === 'Validation Failure: Purge url must contain one of your hostnames' ) {
				throw new \Exception(
					\esc_html__(
						'CDN site is not configured correctly: Delivery Domain must match your site domain',
						'w3-total-cache'
					)
				);
			} else {
				throw $ex;
			}
		}
	}
}
