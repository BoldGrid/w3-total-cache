<?php
/**
 * File: Extension_NewRelic_AdminNotes.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_NewRelic_Api
 *
 * Interacts with the New Relic Connect API
 *
 * @link newrelic.github.com/newrelic_api/
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */
class Extension_NewRelic_Api {
	/**
	 * API Key
	 *
	 * @var string
	 */
	private $_api_key;

	/**
	 * New Relic API URL
	 *
	 * @var string
	 */
	private static $url = 'https://api.newrelic.com';

	/**
	 * Constructor to initialize the API key.
	 *
	 * @param string $api_key The API key to authenticate requests.
	 *
	 * @return void
	 */
	public function __construct( $api_key ) {
		$this->_api_key = $api_key;
	}

	/**
	 * Sends a GET request to the specified API endpoint.
	 *
	 * @param string $api_call_url The API URL to call.
	 * @param array  $query        Optional query parameters to include in the request.
	 *
	 * @return string The response body from the API.
	 *
	 * @throws \Exception If the API request fails or returns an error.
	 */
	private function _get( $api_call_url, $query = array() ) {
		$defaults = array(
			'headers' => 'x-api-key:' . $this->_api_key,
			'body'    => $query,
		);
		$url      = self::$url . $api_call_url;

		$response = wp_remote_get( $url, $defaults );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'Could not get data' );
		} elseif ( 200 === $response['response']['code'] ) {
			$return = $response['body'];
		} else {
			switch ( $response['response']['code'] ) {
				case '403':
					$message = __( 'Invalid API key', 'w3-total-cache' );
					break;
				default:
					$message = $response['response']['message'];
			}

			throw new \Exception(
				\esc_html( $message ),
				\esc_html( $response['response']['code'] )
			);
		}
		return $return;
	}

	/**
	 * Sends a PUT request to the specified API endpoint.
	 *
	 * @param string $api_call_url The API URL to call.
	 * @param array  $params       The data to send in the PUT request.
	 *
	 * @return bool True if the request was successful.
	 *
	 * @throws \Exception If the API request fails or returns an error.
	 */
	private function _put( $api_call_url, $params ) {
		$defaults = array(
			'method'  => 'PUT',
			'headers' => 'x-api-key:' . $this->_api_key,
			'body'    => $params,
		);
		$url      = self::$url . $api_call_url;
		$response = wp_remote_request( $url, $defaults );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'Could not put data' );
		} elseif ( 200 === $response['response']['code'] ) {
			$return = true;
		} else {
			throw new \Exception(
				\esc_html( $response['response']['message'] ),
				\esc_html( $response['response']['code'] )
			);
		}
		return $return;
	}

	/**
	 * Retrieves a list of browser applications from the API.
	 *
	 * @return array A list of browser applications.
	 *
	 * @throws \Exception If the API response is invalid or unexpected.
	 */
	public function get_browser_applications() {
		$response = $this->_get( '/v2/browser_applications.json' );
		$r        = @json_decode( $response, true );
		if ( ! $r ) {
			throw new \Exception( 'Received unexpected response' );
		}

		if ( ! isset( $r['browser_applications'] ) ) {
			return array();
		}

		return $r['browser_applications'];
	}

	/**
	 * Retrieves a specific browser application by its ID from the API.
	 *
	 * @param string $id The ID of the browser application to retrieve.
	 *
	 * @return array|null The browser application data, or null if not found.
	 *
	 * @throws \Exception If the API response is invalid or unexpected.
	 */
	public function get_browser_application( $id ) {
		$response = $this->_get( '/v2/browser_applications.json', array( 'filter[ids]' => $id ) );
		$r        = @json_decode( $response, true );
		if ( ! $r ) {
			throw new \Exception( 'Received unexpected response' );
		}

		if ( ! isset( $r['browser_applications'] ) || 1 !== count( $r['browser_applications'] ) ) {
			return null;
		}

		return $r['browser_applications'][0];
	}
}
