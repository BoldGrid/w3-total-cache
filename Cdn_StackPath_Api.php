<?php
/**
 * File: Cdn_StackPath_Api.php
 *
 * @package W3TC
 */

namespace W3TC;

require_once W3TC_LIB_DIR . '/OAuth/W3tcOAuth.php';
require_once W3TC_LIB_NETDNA_DIR . '/W3tcWpHttpException.php';

/**
 * Class Cdn_StackPath_Api
 *
 * StackPath REST Client Library
 */
class Cdn_StackPath_Api {
	/**
	 * Alias.
	 *
	 * @var string
	 */
	private $alias;

	/**
	 * Key.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Secret.
	 *
	 * @var string
	 */
	private $secret;

	/**
	 * API URL.
	 *
	 * @var string
	 */
	private $stackpath_api_url = 'https://api.stackpath.com/v1';

	/**
	 * Creates an instance of the Cdn_StackPath_Api class using the provided authorization key.
	 *
	 * @param string $authorization_key The authorization key containing alias, consumer key, and consumer secret.
	 *
	 * @return Cdn_StackPath_Api The initialized API instance.
	 */
	public static function create( $authorization_key ) {
		$keys           = explode( '+', $authorization_key );
		$alias          = '';
		$consumerkey    = '';
		$consumersecret = '';

		if ( 3 === count( $keys ) ) {
			list( $alias, $consumerkey, $consumersecret ) = $keys;
		}

		$api = new Cdn_StackPath_Api( $alias, $consumerkey, $consumersecret, $endpoint );

		return $api;
	}

	/**
	 * Constructor for the Cdn_StackPath_Api class.
	 *
	 * @param string $alias   The StackPath alias.
	 * @param string $key     The consumer key.
	 * @param string $secret  The consumer secret.
	 *
	 * @return void
	 */
	public function __construct( $alias, $key, $secret ) {
		$this->alias  = $alias;
		$this->key    = $key;
		$this->secret = $secret;
	}

	/**
	 * Validates the current API instance.
	 *
	 * @return bool True if the alias, key, and secret are set; otherwise, false.
	 */
	public function is_valid() {
		return ! empty( $this->alias ) && ! empty( $this->key ) && ! empty( $this->secret );
	}

	/**
	 * Executes an HTTP request to the StackPath API.
	 *
	 * @param string $selected_call The API endpoint or resource path.
	 * @param string $method_type   The HTTP method type (e.g., 'GET', 'POST', 'PUT').
	 * @param array  $params        Parameters to include in the request.
	 *
	 * @return string The response body from the API request.
	 *
	 * @throws \W3tcWpHttpException W3TC HTTP Exception if authorization request fails.
	 */
	private function execute( $selected_call, $method_type, $params ) {
		// increase the http request timeout.
		add_filter( 'http_request_timeout', array( $this, 'filter_timeout_time' ) );
		add_filter( 'https_ssl_verify', array( $this, 'https_ssl_verify' ) );

		$consumer = new \W3tcOAuthConsumer( $this->key, $this->secret, null );

		// the endpoint for your request.
		$endpoint = "$this->stackpath_api_url/$this->alias$selected_call";

		// parse endpoint before creating OAuth request.
		$parsed = wp_parse_url( $endpoint );
		if ( array_key_exists( 'parsed', $parsed ) ) {
			parse_str( $parsed['query'], $params );
		}

		// generate a request from your consumer.
		$req_req = \W3tcOAuthRequest::from_consumer_and_token( $consumer, null, $method_type, $endpoint, $params );

		// sign your OAuth request using hmac_sha1.
		$sig_method = new \W3tcOAuthSignatureMethod_HMAC_SHA1();
		$req_req->sign_request( $sig_method, $consumer, null );

		$request              = array();
		$request['sslverify'] = false;
		$request['method']    = $method_type;

		if ( 'POST' === $method_type || 'PUT' === $method_type ) {
			$request['body']                    = $req_req->to_postdata();
			$request['headers']['Content-Type'] = 'application/x-www-form-urlencoded; charset=' . get_option( 'blog_charset' );

			$url = $req_req->get_normalized_http_url();
		} else {
			// notice GET, PUT and DELETE both needs to be passed in URL.
			$url = $req_req->to_url();
		}

		$response = wp_remote_request( $url, $request );

		$json_output = '';
		if ( ! is_wp_error( $response ) ) {
			// make call.
			$result        = wp_remote_retrieve_body( $response );
			$headers       = wp_remote_retrieve_headers( $response );
			$response_code = wp_remote_retrieve_response_code( $response );
			// $json_output contains the output string.
			$json_output = $result;
		} else {
			$response_code = $response->get_error_code();
		}

		remove_filter( 'https_ssl_verify', array( $this, 'https_ssl_verify' ) );
		remove_filter( 'http_request_timeout', array( $this, 'filter_timeout_time' ) );

		// catch errors.
		if ( is_wp_error( $response ) ) {
			throw new \W3tcWpHttpException(
				"ERROR: {$response->get_error_message()}, Output: $json_output",
				$response_code,
				null,
				$headers
			);
		}

		return $json_output;
	}

	/**
	 * Adjusts the HTTP request timeout for API calls.
	 *
	 * @param int $time The current timeout time in seconds.
	 *
	 * @return int The modified timeout time in seconds.
	 */
	public function filter_timeout_time( $time ) {
		return 600;
	}

	/**
	 * Disables SSL verification for API requests.
	 *
	 * @param bool $v The current SSL verification setting.
	 *
	 * @return bool Always returns false to disable SSL verification.
	 */
	public function https_ssl_verify( $v ) {
		return false;
	}

	/**
	 * Executes an HTTP request and waits for a 200 or 201 response code.
	 *
	 * @param string $selected_call The API endpoint or resource path.
	 * @param string $method_type   The HTTP method type (e.g., 'GET', 'POST', 'PUT').
	 * @param array  $params        Parameters to include in the request.
	 *
	 * @return array The decoded JSON response from the API request.
	 *
	 * @throws Exception If the response code is not 200 or 201.
	 */
	private function execute_await_200( $selected_call, $method_type, $params ) {
		$r = json_decode( $this->execute( $selected_call, $method_type, $params ), true );
		if ( ! preg_match( '(200|201)', $r['code'] ) ) {
			throw $this->to_exception( $r );
		}

		return $r;
	}

	/**
	 * Executes a GET request to the StackPath API.
	 *
	 * @param string $selected_call The API endpoint or resource path.
	 * @param array  $params        Parameters to include in the request (optional).
	 *
	 * @return array The decoded JSON response from the GET request.
	 */
	private function get( $selected_call, $params = array() ) {
		return $this->execute_await_200( $selected_call, 'GET', $params );
	}

	/**
	 * Executes a POST request to the StackPath API.
	 *
	 * @param string $selected_call The API endpoint or resource path.
	 * @param array  $params        Parameters to include in the request (optional).
	 *
	 * @return array The decoded JSON response from the POST request.
	 */
	private function post( $selected_call, $params = array() ) {
		return $this->execute_await_200( $selected_call, 'POST', $params );
	}

	/**
	 * Executes a PUT request to the StackPath API.
	 *
	 * @param string $selected_call The API endpoint or resource path.
	 * @param array  $params        Parameters to include in the request (optional).
	 *
	 * @return array The decoded JSON response from the PUT request.
	 */
	private function put( $selected_call, $params = array() ) {
		return $this->execute_await_200( $selected_call, 'PUT', $params );
	}

	/**
	 * Deletes a resource from StackPath.
	 *
	 * @param string $selected_call The API endpoint to call.
	 * @param array  $params        Optional. Additional parameters for the request.
	 *
	 * @return array The decoded JSON response from the DELETE request.
	 */
	private function delete( $selected_call, $params = array() ) {
		return $this->execute_await_200( $selected_call, 'DELETE', $params );
	}

	/**
	 * Converts an API response into an exception object.
	 *
	 * @param array $response {
	 *     The API response to convert.
	 *
	 *     @type string $error['message'] Error message from the response.
	 *     @type array  $data['errors']  Array of errors from the response data.
	 * }
	 *
	 * @return \W3tcWpHttpException Exception object containing the error details.
	 */
	private function to_exception( $response ) {
		if ( isset( $response['error']['message'] ) ) {
			$message = $response['error']['message'];
		} else {
			$message = 'Failed to communicate with StackPath';
		}

		if ( isset( $response['data'] ) && isset( $response['data']['errors'] ) ) {
			foreach ( $response['data']['errors'] as $field => $error ) {
				if ( isset( $error['error'] ) ) {
					$message .= '. ' . $field . ': ' . $error['error'];
				} else {
					$message .= '. ' . $field . ': ' . $error;
				}
			}
		}

		return new \W3tcWpHttpException( $message );
	}

	/**
	 * Retrieves the list of sites from StackPath.
	 *
	 * @return array List of sites retrieved from the API.
	 */
	public function get_sites() {
		$r     = $this->get( '/sites' );
		$zones = array();
		foreach ( $r ['data']['zones'] as $zone ) {
			$zones[] = $zone;
		}

		return $zones;
	}

	/**
	 * Creates a new site on StackPath.
	 *
	 * @param array $zone The zone data for the new site.
	 *
	 * @return array Data of the created pull zone.
	 */
	public function create_site( $zone ) {
		$r = $this->post( '/sites', $zone );

		return $r['data']['pullzone'];
	}

	/**
	 * Updates an existing site on StackPath.
	 *
	 * @param string $zone_id The ID of the zone to update.
	 * @param array  $zone    The zone data to update.
	 *
	 * @return array Data of the updated pull zone.
	 */
	public function update_site( $zone_id, $zone ) {
		$r = $this->put( "/sites/$zone_id", $zone );

		return $r['data']['pullzone'];
	}

	/**
	 * Retrieves the details of a specific site.
	 *
	 * @param string $zone_id The ID of the zone to retrieve.
	 *
	 * @return array Data of the pull zone.
	 */
	public function get_site( $zone_id ) {
		$r = $this->get( "/sites/$zone_id" );

		return $r['data']['pullzone'];
	}

	/**
	 * Retrieves the list of custom domains for a specific site.
	 *
	 * @param string $zone_id The ID of the zone to retrieve custom domains for.
	 *
	 * @return array List of custom domains.
	 */
	public function get_custom_domains( $zone_id ) {
		$r       = $this->get( "/sites/$zone_id/customdomains" );
		$domains = array();
		foreach ( $r['data']['customdomains'] as $domain ) {
			$domains[] = $domain['custom_domain'];
		}

		return $domains;
	}

	/**
	 * Deletes the cache for a specific site.
	 *
	 * @param string $zone_id       The ID of the zone to clear the cache for.
	 * @param array  $files_to_pass Optional. Specific files to delete from the cache.
	 *
	 * @return bool True on success.
	 */
	public function delete_site_cache( $zone_id, $files_to_pass = null ) {
		$params = array();
		if ( ! empty( $files_to_pass ) ) {
			$params['files'] = $files_to_pass;
		}

		$r = $this->delete( "/sites/$zone_id/cache", $params );

		return true;
	}

	/**
	 * Retrieves the statistics summary for a specific zone.
	 *
	 * @param string $zone_id The ID of the zone to retrieve statistics for.
	 *
	 * @return array Summary statistics data.
	 */
	public function get_stats_per_zone( $zone_id ) {
		$r = $this->get( "/reports/{$zone_id}/stats" );

		return $r['data']['summary'];
	}


	/**
	 * Retrieves the list of file types and their statistics for a specific zone.
	 *
	 * @param string $zone_id The ID of the zone to retrieve file types for.
	 *
	 * @return array File type statistics and summary data.
	 */
	public function get_list_of_file_types_per_zone( $zone_id ) {
		$r     = $this->get( "/reports/{$zone_id}/filetypes" );
		$stats = array(
			'total'     => $r['data']['total'],
			'filetypes' => array(),
		);

		foreach ( $r['data']['filetypes'] as $filetyp ) {
			$stats['filetypes'][] = $filetyp;
		}

		$stats['summary'] = $r['data']['summary'];

		return $stats;
	}

	/**
	 * Retrieves the list of popular files for a specific zone.
	 *
	 * @param string $zone_id The ID of the zone to retrieve popular files for.
	 *
	 * @return array List of popular files.
	 */
	public function get_list_of_popularfiles_per_zone( $zone_id ) {
		$r = $this->get( "/reports/{$zone_id}/popularfiles" );

		return $r['data']['popularfiles'];
	}

	/**
	 * Retrieves the account details from StackPath.
	 *
	 * @return array Account details.
	 */
	public function get_account() {
		$r = $this->get( '/account' );

		return $r['data']['account'];
	}

	/**
	 * Creates a new custom domain for a specific site.
	 *
	 * @param string $zone_id       The ID of the zone to add the custom domain to.
	 * @param string $custom_domain The custom domain to add.
	 *
	 * @return array Data of the created custom domain.
	 */
	public function create_custom_domain( $zone_id, $custom_domain ) {
		$custom_domain = $this->post( "/sites/$zone_id/customdomains", array( 'custom_domain' => $custom_domain ) );

		return $custom_domain;
	}
}
