<?php
/**
 * File: Cdn_StackPath2_Api.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_StackPath2_Api
 *
 * StackPath REST Client Library
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class Cdn_StackPath2_Api {
	/**
	 * Client ID.
	 *
	 * @var string
	 */
	private $client_id;

	/**
	 * Client secret.
	 *
	 * @var string
	 */
	private $client_secret;

	/**
	 * Stack ID.
	 *
	 * @var string
	 */
	private $stack_id;

	/**
	 * Access token.
	 *
	 * @var string
	 */
	private $access_token;

	/**
	 * Callback on new access token.
	 *
	 * @var callable|null
	 */
	private $on_new_access_token;

	/**
	 * Class constructor.
	 *
	 * Initializes the API client with the provided configuration.
	 *
	 * @param array $config {
	 *     Configuration settings for the API client.
	 *
	 *     @type string $client_id           The client ID for authentication.
	 *     @type string $client_secret       The client secret for authentication.
	 *     @type string $stack_id            (Optional) The Stack ID.
	 *     @type string $access_token        (Optional) An existing access token.
	 *     @type callable $on_new_access_token (Optional) Callback for handling new access tokens.
	 * }
	 *
	 * @return void
	 */
	public function __construct( $config ) {
		$this->client_id           = $config['client_id'];
		$this->client_secret       = $config['client_secret'];
		$this->stack_id            = isset( $config['stack_id'] ) ? $config['stack_id'] : '';
		$this->access_token        = isset( $config['access_token'] ) ? $config['access_token'] : '';
		$this->on_new_access_token = isset( $config['on_new_access_token'] ) ? $config['on_new_access_token'] : null;
	}

	/**
	 * Authenticates the client and retrieves an access token.
	 *
	 * Sends a request to authenticate using client credentials.
	 *
	 * @throws \Exception If authentication fails or the response is invalid.
	 *
	 * @return string The access token.
	 */
	public function authenticate() {
		$request_json = array(
			'client_id'     => $this->client_id,
			'client_secret' => $this->client_secret,
			'grant_type'    => 'client_credentials',
		);

		$result = wp_remote_post(
			'https://gateway.stackpath.com/identity/v1/oauth2/token',
			array(
				'headers' => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $request_json ),
			)
		);

		$r = $this->_decode_response( $result );
		if ( $r['auth_required'] ) {
			throw new \Exception( \esc_html__( 'Authentication failed', 'w3-total-cache' ) );
		}

		if ( ! isset( $r['response_json']['access_token'] ) ) {
			throw new \Exception( \esc_html__( 'Unexpected authentication response: access token not found', 'w3-total-cache' ) );
		}

		$this->access_token = $r['response_json']['access_token'];

		return $this->access_token;
	}

	/**
	 * Retrieves the list of sites associated with the stack.
	 *
	 * @return array The list of sites.
	 */
	public function site_list() {
		return $this->_wp_remote_get( "https://gateway.stackpath.com/cdn/v1/stacks/$this->stack_id/sites" );
	}

	/**
	 * Retrieves details of a specific site.
	 *
	 * @param string $site_id The ID of the site to retrieve.
	 *
	 * @return array The site details.
	 */
	public function site_get( $site_id ) {
		return $this->_wp_remote_get( "https://gateway.stackpath.com/cdn/v1/stacks/$this->stack_id/sites/$site_id" );
	}

	/**
	 * Creates a new site.
	 *
	 * @param array $data The data for creating the site.
	 *
	 * @return array The response from the API.
	 */
	public function site_create( $data ) {
		return $this->_wp_remote_post( "https://gateway.stackpath.com/cdn/v1/stacks/$this->stack_id/sites", $data );
	}

	/**
	 * Retrieves site metrics for a given period.
	 *
	 * @param string $site_id The ID of the site.
	 * @param int    $days    The number of days for the metrics.
	 *
	 * @return array The site metrics.
	 */
	public function site_metrics( $site_id, $days ) {
		$d = new \DateTime();

		$end_date   = $d->format( 'Y-m-d' ) . 'T00:00:00Z';
		$start_date = $d->sub( new \DateInterval( 'P' . $days . 'D' ) )->format( 'Y-m-d' ) . 'T00:00:00Z';

		return $this->_wp_remote_get(
			"https://gateway.stackpath.com/cdn/v1/stacks/$this->stack_id/metrics",
			array(
				'site_id'     => $site_id,
				'start_date'  => $start_date,
				'end_date'    => $end_date,
				'platforms'   => 'CDS',
				'granularity' => 'P1D',
			)
		);
	}

	/**
	 * Purges content for a site.
	 *
	 * @param array $data The data specifying the content to purge.
	 *
	 * @return array The response from the API.
	 */
	public function purge( $data ) {
		return $this->_wp_remote_post( "https://gateway.stackpath.com/cdn/v1/stacks/$this->stack_id/purge", $data );
	}


	/**
	 * Retrieves DNS targets for a specific site.
	 *
	 * @param string $site_id The ID of the site.
	 *
	 * @return array The DNS targets.
	 */
	public function site_dns_targets_get( $site_id ) {
		return $this->_wp_remote_get( "https://gateway.stackpath.com/cdn/v1/stacks/$this->stack_id/sites/$site_id/dns/targets" );
	}

	/**
	 * Retrieves the scope of a site for a specified platform.
	 *
	 * @param string $site_id  The ID of the site.
	 * @param string $platform The platform to filter scopes by.
	 *
	 * @return array|null The scope details or null if not found.
	 */
	private function site_scope_get_by_platform( $site_id, $platform ) {
		$scopes = $this->_wp_remote_get( "https://gateway.stackpath.com/cdn/v1/stacks/$this->stack_id/sites/$site_id/scopes" );
		foreach ( $scopes['results'] as $scope ) {
			if ( $scope['platform'] === $platform ) {
				return $scope;
			}
		}

		return null;
	}

	/**
	 * Retrieves the CDS configuration for a specific site.
	 *
	 * @param string $site_id The ID of the site.
	 *
	 * @return array The CDS configuration.
	 */
	public function site_cds_get( $site_id ) {
		$scope    = $this->site_scope_get_by_platform( $site_id, 'CDS' );
		$scope_id = $scope['id'];

		return $this->_wp_remote_get( "https://gateway.stackpath.com/cdn/v1/stacks/$this->stack_id/sites/$site_id/scopes/$scope_id/configuration" );
	}

	/**
	 * Retrieves the list of stacks.
	 *
	 * @return array The list of stacks.
	 */
	public function stacks_list() {
		return $this->_wp_remote_get( 'https://gateway.stackpath.com/stack/v1/stacks' );
	}

	/**
	 * Retrieves details of a specific stack.
	 *
	 * @param string $stack_id The ID of the stack.
	 *
	 * @return array The stack details.
	 */
	public function stack_get( $stack_id ) {
		return $this->_wp_remote_get( "https://gateway.stackpath.com/stack/v1/stacks/$stack_id" );
	}

	/**
	 * Decodes the API response and handles errors.
	 *
	 * @param array|WP_Error $result The response from the API.
	 *
	 * @throws \Exception If the response contains an error.
	 *
	 * @return array {
	 *     Decoded response data.
	 *
	 *     @type bool  $auth_required Whether authentication is required.
	 *     @type array $response_json The decoded JSON response.
	 * }
	 */
	private function _decode_response( $result ) {
		if ( is_wp_error( $result ) ) {
			throw new \Exception( \esc_html__( 'Failed to reach API endpoint', 'w3-total-cache' ) );
		}

		$response_json = @json_decode( $result['body'], true );
		if ( is_null( $response_json ) ) {
			throw new \Exception(
				\esc_html(
					sprintf(
						// Translators: 1 Result body.
						\__( 'Failed to reach API endpoint, got unexpected response: %1$s', 'w3-total-cache' ),
						$result['body']
					)
				)
			);
		}

		if ( '401' === $result['response']['code'] ) {
			return array(
				'auth_required' => true,
				'response_json' => array(),
			);
		}

		if ( '200' !== $result['response']['code'] && '201' !== $result['response']['code'] ) {
			if ( isset( $response_json['message'] ) ) {
				throw new \Exception( \esc_html( $response_json['message'] ) );
			} else {
				throw new \Exception(
					\esc_html(
						sprintf(
							// Translators: 1 Response status code, 2 Response body.
							\__( 'Response code %1$s with %2$s.', 'w3-total-cache' ),
							$result['response']['code'],
							$result['body']
						)
					)
				);
			}
		}

			return array(
				'auth_required' => false,
				'response_json' => $response_json,
			);
	}

	/**
	 * Sends a GET request to the API.
	 *
	 * @param string $url  The API endpoint URL.
	 * @param array  $data (Optional) Query parameters to include in the request.
	 *
	 * @return array The response from the API.
	 */
	private function _wp_remote_get( $url, $data = array() ) {
		if ( ! empty( $this->access_token ) ) {
			$result = wp_remote_get(
				$url . ( empty( $data ) ? '' : '?' . http_build_query( $data ) ),
				array(
					'headers' => 'authorization: Bearer ' . $this->access_token,
				)
			);

			$r = self::_decode_response( $result );
			if ( ! $r['auth_required'] ) {
				return $r['response_json'];
			}
		}

		$this->authenticate();
		if ( ! is_null( $this->on_new_access_token ) ) {
			call_user_func( $this->on_new_access_token, $this->access_token );
		}

		return $this->_wp_remote_get( $url, $data );
	}

	/**
	 * Sends a POST request to the API.
	 *
	 * @param string $url  The API endpoint URL.
	 * @param array  $data The data to send in the request body.
	 *
	 * @return array The response from the API.
	 */
	private function _wp_remote_post( $url, $data ) {
		if ( ! empty( $this->access_token ) ) {
			add_filter( 'http_request_timeout', array( $this, 'filter_timeout_time' ) );
			add_filter( 'https_ssl_verify', array( $this, 'https_ssl_verify' ) );

			$result = wp_remote_post(
				$url,
				array(
					'headers' => array(
						'authorization' => 'Bearer ' . $this->access_token,
						'Accept'        => 'application/json',
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $data ),
				)
			);

			remove_filter( 'https_ssl_verify', array( $this, 'https_ssl_verify' ) );
			remove_filter( 'http_request_timeout', array( $this, 'filter_timeout_time' ) );

			$r = self::_decode_response( $result );
			if ( ! $r['auth_required'] ) {
				return $r['response_json'];
			}
		}

		$this->authenticate();
		if ( ! is_null( $this->on_new_access_token ) ) {
			call_user_func( $this->on_new_access_token, $this->access_token );
		}

		return $this->_wp_remote_post( $url, $data );
	}

	/**
	 * Filters the timeout duration for HTTP requests.
	 *
	 * @param int $time The current timeout value.
	 *
	 * @return int The updated timeout value.
	 */
	public function filter_timeout_time( $time ) {
		return 600;
	}

	/**
	 * Disables SSL verification for HTTPS requests.
	 *
	 * @param bool $v The current SSL verification setting.
	 *
	 * @return bool False to disable SSL verification.
	 */
	public function https_ssl_verify( $v ) {
		return false;
	}
}
