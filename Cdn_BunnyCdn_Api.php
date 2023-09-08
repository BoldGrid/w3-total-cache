<?php
/**
 * File: Cdn_BunnyCDN_Api.php
 *
 * @since   X.X.X
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdn_BunnyCdn_Api
 *
 * @since X.X.X
 */
class Cdn_BunnyCdn_Api {
	/**
	 * Account API Key.
	 *
	 * @since  X.X.X
	 * @access private
	 *
	 * @var string
	 */
	private $account_api_key;

	/**
	 * Storage API Key.
	 *
	 * @since  X.X.X
	 * @access private
	 *
	 * @var string
	 */
	private $storage_api_key;

	/**
	 * Stream API Key.
	 *
	 * @since  X.X.X
	 * @access private
	 *
	 * @var string
	 */
	private $stream_api_key;

	/**
	 * API type.
	 *
	 * One of: "account", "storage", "stream".
	 *
	 * @since  X.X.X
	 * @access private
	 *
	 * @var string
	 */
	private $api_type;

	/**
	 * Constructor.
	 *
	 * @since X.X.X
	 *
	 * @param array $config Configuration.
	 */
	public function __construct( array $config ) {
		$this->account_api_key = ! empty( $config['account_api_key'] ) ? $config['account_api_key'] : '';
		$this->storage_api_key = ! empty( $config['storage_api_key'] ) ? $config['storage_api_key'] : '';
		$this->stream_api_key  = ! empty( $config['stream_api_key'] ) ? $config['stream_api_key'] : '';
	}

	/**
	 * Increase http request timeout to 60 seconds.
	 *
	 * @since X.X.X
	 *
	 * @param int $time Timeout in seconds.
	 */
	public function filter_timeout_time( $time ) {
		return 600;
	}

	/**
	 * Don't check certificate, some users have limited CA list
	 *
	 * @since X.X.X
	 *
	 * @param bool $verify Always false.
	 */
	public function https_ssl_verify( $verify = false ) {
		return false;
	}

	/**
	 * List pull zones.
	 *
	 * @since X.X.X
	 *
	 * @link https://docs.bunny.net/reference/pullzonepublic_index
	 *
	 * @return array
	 */
	public function list_pull_zones() {
		$this->api_type = 'account';

		return $this->wp_remote_get( \esc_url( 'https://api.bunny.net/pullzone' ) );
	}

	/**
	 * Get pull zone details by pull zone id.
	 *
	 * @since X.X.X
	 *
	 * @link https://docs.bunny.net/reference/pullzonepublic_index2
	 *
	 * @param  int $id Pull zone id.
	 * @return array
	 */
	public function get_pull_zone( $id ) {
		$this->api_type = 'account';

		return $this->wp_remote_get(
			\esc_url( 'https://api.bunny.net/pullzone/id' . $id )
		);
	}

	/**
	 * Add a pull zone.
	 *
	 * @since X.X.X
	 *
	 * @link https://docs.bunny.net/reference/pullzonepublic_add
	 *
	 * @param  array $data {
	 *     Data used to create the pull zone.
	 *
	 *     @type string $Name             The name/hostname for the pull zone where the files will be accessible; only letters, numbers, and dashes.
	 *     @type string $OriginUrl        Origin URL or IP (with optional port number).
	 *     @type string $OriginHostHeader Optional: The host HTTP header that will be sent to the origin.  If empty, hostname will be automatically extracted from the Origin URL.
	 *     @type bool   $AddHostHeader    Optional: If enabled, the original host header of the request will be forwarded to the origin server.  This should be disabled in most cases.
	 * }
	 *
	 * @return array
	 * @throws \Exception Exception.
	 */
	public function add_pull_zone( array $data ) {
		$this->api_type = 'account';

		if ( empty( $data['Name'] ) || ! \is_string( $data['Name'] ) ) { // A Name string is required, which is used for the CDN hostname.
			throw new \Exception( \esc_html__( 'A pull zone name (string) is required.', 'w3-total-cache' ) );
		}

		if ( \preg_match( '[^\w\d-]', $data['Name'] ) ) { // Only letters, numbers, and dashes are allowed in the Name.
			throw new \Exception( \esc_html__( 'A pull zone name (string) is required.', 'w3-total-cache' ) );
		}

		return $this->wp_remote_post(
			'https://api.bunny.net/pullzone',
			$data
		);
	}

	/**
	 * Update a pull zone.
	 *
	 * @since X.X.X
	 *
	 * @link https://docs.bunny.net/reference/pullzonepublic_updatepullzone
	 *
	 * @param  int   $id Pull zone id.
	 * @param  array $data Data used to update the pull zone.
	 * @return array
	 */
	public function update_pull_zone( int $id, array $data ) {
		$this->api_type = 'account';

		return $this->wp_remote_post(
			'https://api.bunny.net/pullzone/' . $id,
			$data
		);
	}

	/**
	 * Delete a pull zone.
	 *
	 * @since X.X.X
	 *
	 * @link https://docs.bunny.net/reference/pullzonepublic_delete
	 *
	 * @param  int $id Pull zone id.
	 * @return array
	 */
	public function delete_pull_zone( $id ) {
		$this->api_type = 'account';

		return $this->wp_remote_post(
			\esc_url( 'https://api.bunny.net/pullzone/' . $id ),
			array(),
			array( 'method' => 'DELETE' )
		);
	}

	/**
	 * Get site metrics.
	 *
	 * @since X.X.X
	 *
	 * @param  int    $site_id Site id.
	 * @param  string $days Days.
	 * @return array
	 */
	public function site_metrics( $site_id, $days ) {
		$d             = new \DateTime();
		$end_date      = $d->format( 'Y-m-d' ) . 'T00:00:00Z';
		$start_date    = $d->sub( new \DateInterval( 'P' . $days . 'D' ) )->format( 'Y-m-d' ) . 'T00:00:00Z';
		$optional_data = array();

		return $this->wp_remote_get( \esc_url( 'https://@todo' ), array( $optional_data ) );
	}

	/**
	 * Purge.
	 *
	 * @since X.X.X
	 *
	 * @param  array $data Data for the POST request.
	 * @return array
	 */
	public function purge( array $data ) {
		return $this->wp_remote_post(
			\esc_url( 'https://@todo' ),
			$data
		);
	}

	/**
	 * Get the API key by API type.
	 *
	 * API type can be passed or the class property will be used.
	 *
	 * @since X.X.X
	 *
	 * @param  string $type API type: One of "account", "storage", "stream" (optional).
	 * @return string|null
	 * @throws \Exception Exception.
	 */
	private function get_api_key( $type = null ) {
		if ( empty( $type ) ) {
			$type = $this->api_type;
		}

		if ( ! \in_array( $type, array( 'account', 'storage', 'stream' ), true ) ) {
			throw new \Exception( \esc_html__( 'Invalid API type; must be one of "account", "storage", "stream".', 'w3-total-cache' ) );
		}

		if ( empty( $this->{$type . '_api_key'} ) ) {
			throw new \Exception( \esc_html__( 'API key value is empty.', 'w3-total-cache' ) );
		}

		return $this->{$type . '_api_key'};
	}

	/**
	 * Decode response from a wp_remote_* call.
	 *
	 * @since X.X.X
	 *
	 * @param  array|WP_Error $result Result.
	 * @return array
	 * @throws \Exception Exception.
	 */
	private function decode_response( $result ) {
		if ( \is_wp_error( $result ) ) {
			throw new \Exception( \esc_html__( 'Failed to reach API endpoint', 'w3-total-cache' ) );
		}

		// If a response body was expected and not present, then throw an exception.
		if ( 204 !== $result['response']['code'] && ( empty( $result['body'] ) || ! \is_string( $result['body'] ) ) ) {
			throw new \Exception( \esc_html__( 'Response body is invalid', 'w3-total-cache' ) );
		}

		$response_body = @\json_decode( $result['body'], true );

		// Throw an exception if the response code/status is not ok.
		if ( ! \in_array( $result['response']['code'], array( 200, 201, 204 ), true ) ) {
			$message = isset( $response_body['Message'] ) ? $response_body['Message'] : $result['body'];

			throw new \Exception(
				\esc_html( \__( 'Response code ', 'w3-total-cache' ) . $result['response']['code'] . ': ' . $message )
			);
		}

		return is_array( $response_body ) ? $response_body : array();
	}

	/**
	 * Remote GET request.
	 *
	 * @since X.X.X
	 *
	 * @link https://developer.wordpress.org/reference/functions/wp_remote_get/
	 * @link https://developer.wordpress.org/reference/classes/wp_http/request/
	 *
	 * @param  string $url URL address.
	 * @param  array  $data Query string data for the GET request.
	 * @return array
	 */
	private function wp_remote_get( $url, array $data = array() ) {
		$api_key = $this->get_api_key();

		\add_filter( 'http_request_timeout', array( $this, 'filter_timeout_time' ) );
		\add_filter( 'https_ssl_verify', array( $this, 'https_ssl_verify' ) );

		$result = \wp_remote_get(
			$url . ( empty( $data ) ? '' : '?' . \http_build_query( $data ) ),
			array(
				'headers' => array(
					'AccessKey' => $api_key,
					'Accept'    => 'application/json',
				),
			)
		);

		\remove_filter( 'https_ssl_verify', array( $this, 'https_ssl_verify' ) );
		\remove_filter( 'http_request_timeout', array( $this, 'filter_timeout_time' ) );

		return self::decode_response( $result );
	}

	/**
	 * Remote POST request.
	 *
	 * @since X.X.X
	 *
	 * @link https://developer.wordpress.org/reference/functions/wp_remote_post/
	 * @link https://developer.wordpress.org/reference/classes/wp_http/request/
	 *
	 * @param  string $url URL address.
	 * @param  array  $data Data for the POSt request.
	 * @param  array  $args Optional additional arguments for the wp_remote_port call.
	 * @return string
	 */
	private function wp_remote_post( $url, array $data, array $args = array() ) {
		$api_key = $this->get_api_key();

		\add_filter( 'http_request_timeout', array( $this, 'filter_timeout_time' ) );
		\add_filter( 'https_ssl_verify', array( $this, 'https_ssl_verify' ) );

		$result = \wp_remote_post(
			$url,
			\array_merge(
				array(
					'headers' => array(
						'AccessKey'    => $api_key,
						'Accept'       => 'application/json',
						'Content-Type' => 'application/json',
					),
					'body'    => empty( $data ) ? null : \json_encode( $data ),
				),
				$args
			)
		);

		\remove_filter( 'https_ssl_verify', array( $this, 'https_ssl_verify' ) );
		\remove_filter( 'http_request_timeout', array( $this, 'filter_timeout_time' ) );

		return self::decode_response( $result );
	}
}
