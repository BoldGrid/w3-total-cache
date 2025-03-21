<?php
/**
 * File: Cdn_Highwinds_Api.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_Highwinds_Api
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */
class Cdn_Highwinds_Api {
	/**
	 * Root URI.
	 *
	 * @var string
	 */
	private static $root_uri = 'https://striketracker3.highwinds.com';

	/**
	 * Account hash.
	 *
	 * @var string
	 */
	private $account_hash;

	/**
	 * API token.
	 *
	 * @var string
	 */
	private $api_token;

	/**
	 * Retrieves information about the authenticated user.
	 *
	 * This method sends a request to the API to get details about the current authenticated user.
	 * It requires a valid API token for authentication. If the authentication fails, an exception is thrown.
	 *
	 * @param string $api_token The API token used for authentication.
	 *
	 * @return array The response containing user information.
	 *
	 * @throws \Exception If authentication fails.
	 */
	public static function users_me( $api_token ) {
		$result = wp_remote_get(
			self::$root_uri . '/api/v1/users/me',
			array(
				'headers' => 'Authorization: Bearer ' . $api_token,
			)
		);

		$r = self::_decode_response( $result );
		if ( ! $r['auth_required'] ) {
			return $r['response_json'];
		}

		throw new \Exception( 'Authentication failed' );
	}

	/**
	 * Constructs a new instance of the Cdn_Highwinds_Api class.
	 *
	 * This method initializes an object with the account hash and API token for making authorized API requests.
	 *
	 * @param string $account_hash The account hash associated with the account.
	 * @param string $api_token    The API token used for authentication.
	 *
	 * @return void
	 */
	public function __construct( $account_hash, $api_token ) {
		$this->account_hash = $account_hash;
		$this->api_token    = $api_token;
	}

	/**
	 * Retrieves analytics transfer data for a given date range and granularity.
	 *
	 * This method fetches the analytics transfer data from the API for the specified host, granularity,
	 * platforms, and date range. It returns the response of the API request.
	 *
	 * @param string $host         The host for which analytics data is being requested.
	 * @param string $granularity  The granularity of the analytics data (e.g., daily, monthly).
	 * @param string $platforms    A comma-separated list of platforms (e.g., web, mobile).
	 * @param string $start_date   The start date of the range in YYYY-MM-DD format.
	 * @param string $end_date     The end date of the range in YYYY-MM-DD format.
	 *
	 * @return array The response containing the analytics data.
	 */
	public function analytics_transfer( $host, $granularity, $platforms,
		$start_date, $end_date ) {
		return $this->_wp_remote_get(
			self::$root_uri . '/api/v1/accounts/' . $this->account_hash .
			'/analytics/transfer?startDate=' . rawurlencode( $start_date ) .
			'&endDate=' . rawurlencode( $end_date ) .
			'&granularity=' . rawurlencode( $granularity ) .
			'&platforms=' . rawurlencode( $platforms )
		);
	}

	/**
	 * Configures scopes for the given host.
	 *
	 * This method retrieves the configuration scopes for a specific host associated with the account.
	 *
	 * @param string $host The host for which to configure the scopes.
	 *
	 * @return array The response containing the configuration scopes.
	 */
	public function configure_scopes( $host ) {
		return $this->_wp_remote_get(
			self::$root_uri . '/api/v1/accounts/' . $this->account_hash . '/hosts/' . $host . '/configuration/scopes'
		);
	}

	/**
	 * Retrieves a specific configuration scope for a host.
	 *
	 * This method fetches the details of a specific configuration scope for a given host.
	 *
	 * @param string $host     The host for which the scope is being requested.
	 * @param string $scope_id The ID of the scope to retrieve.
	 *
	 * @return array The response containing the details of the configuration scope.
	 */
	public function configure_scope_get( $host, $scope_id ) {
		return $this->_wp_remote_get(
			self::$root_uri . '/api/v1/accounts/' . $this->account_hash . '/hosts/' . $host . '/configuration/' . $scope_id
		);
	}

	/**
	 * Updates a specific configuration scope for a host.
	 *
	 * This method updates a configuration scope with the provided settings for a specific host.
	 *
	 * @param string $host        The host for which the scope is being updated.
	 * @param string $scope_id    The ID of the scope to update.
	 * @param array  $configuration The configuration data to update the scope with.
	 *
	 * @return array The response containing the updated configuration.
	 */
	public function configure_scope_set( $host, $scope_id, $configuration ) {
		return $this->_wp_remote_put(
			self::$root_uri . '/api/v1/accounts/' . $this->account_hash . '/hosts/' . $host . '/configuration/' . $scope_id,
			wp_json_encode( $configuration )
		);
	}

	/**
	 * Adds a new host to the account.
	 *
	 * This method sends a request to the API to add a new host to the account.
	 *
	 * @param array $host The host data to be added.
	 *
	 * @return array The response containing the added host information.
	 */
	public function host_add( $host ) {
		return $this->_wp_remote_post(
			self::$root_uri . '/api/v1/accounts/' . $this->account_hash . '/hosts',
			wp_json_encode( $host )
		);
	}

	/**
	 * Retrieves a list of hosts associated with the account.
	 *
	 * This method fetches all hosts associated with the account.
	 *
	 * @return array The list of hosts associated with the account.
	 */
	public function hosts() {
		return $this->_wp_remote_get( self::$root_uri . '/api/v1/accounts/' . $this->account_hash . '/hosts' );
	}

	/**
	 * Adds a new origin to the account.
	 *
	 * This method sends a request to the API to add a new origin to the account.
	 *
	 * @param array $origin The origin data to be added.
	 *
	 * @return array The response containing the added origin information.
	 */
	public function origin_add( $origin ) {
		return $this->_wp_remote_post(
			self::$root_uri . '/api/v1/accounts/' . $this->account_hash . '/origins',
			wp_json_encode( $origin )
		);
	}

	/**
	 * Retrieves a list of origins associated with the account.
	 *
	 * This method fetches all origins associated with the account.
	 *
	 * @return array The list of origins associated with the account.
	 */
	public function origins() {
		return $this->_wp_remote_get( self::$root_uri . '/api/v1/accounts/' . $this->account_hash . '/origins' );
	}

	/**
	 * Purges specified URLs from the cache.
	 *
	 * This method sends a purge request for a list of URLs, with an option to recursively purge content.
	 *
	 * @param array $urls      The list of URLs to purge.
	 * @param bool  $recursive Whether the purge should be recursive.
	 *
	 * @return void
	 */
	public function purge( $urls, $recursive ) {
		$list = array();
		foreach ( $urls as $url ) {
			$list[] = array(
				'url'       => $url,
				'recursive' => $recursive,
			);
		}

		$response = $this->_wp_remote_post(
			self::$root_uri . '/api/v1/accounts/' . $this->account_hash . '/purge',
			wp_json_encode( array( 'list' => $list ) )
		);
	}

	/**
	 * Retrieves a list of services associated with the account.
	 *
	 * This method fetches all services associated with the account.
	 *
	 * @return array The list of services associated with the account.
	 */
	public function services() {
		return $this->_wp_remote_get(
			self::$root_uri . '/api/v1/accounts/' . $this->account_hash . '/services'
		);
	}

	/**
	 * Sends a GET request to the API with authentication.
	 *
	 * This method is used internally to send a GET request to the API with the necessary authentication headers.
	 *
	 * @param string $url  The URL to send the GET request to.
	 * @param array  $body Optional body data to include with the request.
	 *
	 * @return array The response from the API.
	 *
	 * @throws \Exception If authentication fails.
	 */
	private function _wp_remote_get( $url, $body = array() ) {
		$result = wp_remote_get(
			$url,
			array(
				'headers' => 'Authorization: Bearer ' . $this->api_token,
				'body'    => $body,
			)
		);

		$r = self::_decode_response( $result );
		if ( ! $r['auth_required'] ) {
			return $r['response_json'];
		}

		throw new \Exception( 'Authentication failed' );
	}

	/**
	 * Sends a POST request to the API with authentication.
	 *
	 * This method is used internally to send a POST request to the API with the necessary authentication headers.
	 *
	 * @param string $url  The URL to send the POST request to.
	 * @param mixed  $body The body data to send with the request.
	 *
	 * @return array The response from the API.
	 *
	 * @throws \Exception If authentication fails.
	 */
	private function _wp_remote_post( $url, $body ) {
		$headers = array(
			'Authorization' => 'Bearer ' . $this->api_token,
		);

		if ( ! is_array( $body ) ) {
			$headers['Content-Type'] = 'application/json';
		}

		$result = wp_remote_post(
			$url,
			array(
				'headers' => $headers,
				'body'    => $body,
			)
		);

		$r = self::_decode_response( $result );
		if ( ! $r['auth_required'] ) {
			return $r['response_json'];
		}

		throw new \Exception( 'Authentication failed' );
	}

	/**
	 * Sends a PUT request to the API with authentication.
	 *
	 * This method is used internally to send a PUT request to the API with the necessary authentication headers.
	 *
	 * @param string $url  The URL to send the PUT request to.
	 * @param mixed  $body The body data to send with the request.
	 *
	 * @return array The response from the API.
	 *
	 * @throws \Exception If authentication fails.
	 */
	private function _wp_remote_put( $url, $body ) {
		$headers = array(
			'Authorization' => 'Bearer ' . $this->api_token,
		);

		if ( ! is_array( $body ) ) {
			$headers['Content-Type'] = 'application/json';
		}

		$result = wp_remote_post(
			$url,
			array(
				'headers' => $headers,
				'body'    => $body,
				'method'  => 'PUT',
			)
		);

		$r = self::_decode_response( $result );
		if ( ! $r['auth_required'] ) {
			return $r['response_json'];
		}

		throw new \Exception( 'Authentication failed' );
	}

	/**
	 * Decodes the response from the API and checks for authentication errors.
	 *
	 * This method processes the response from the API and ensures that it is valid. If authentication is required,
	 * it returns a flag indicating so, or throws an exception if the response is invalid.
	 *
	 * @param array $result The response from the API.
	 *
	 * @return array An array containing the decoded JSON response and an authentication required flag.
	 *
	 * @throws \Exception If the response is invalid or authentication fails.
	 */
	private static function _decode_response( $result ) {
		if ( is_wp_error( $result ) ) {
			throw new \Exception( 'Failed to reach API endpoint' );
		}

		$response_json = @json_decode( $result['body'], true );
		if ( is_null( $response_json ) ) {
			if ( 200 === (int) $result['response']['code'] && empty( $result['body'] ) ) {
				return array(
					'response_json' => array(),
					'auth_required' => false,
				);
			}

			throw new \Exception( 'Failed to reach API endpoint, got unexpected response ' . $result['body'] );
		}

		if ( isset( $response_json['error'] ) ) {
			if ( isset( $response_json['code'] ) && 203 === (int) $response_json['code'] ) {
				return array(
					'response_json' => $response_json,
					'auth_required' => true,
				);
			}

			throw new \Exception( $response_json['error'] );
		}

		if ( 200 !== (int) $result['response']['code'] && 201 !== (int) $result['response']['code'] ) {
			throw new \Exception( $result['body'] );
		}

		return array(
			'response_json' => $response_json,
			'auth_required' => false,
		);
	}
}
