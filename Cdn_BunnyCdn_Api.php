<?php
/**
 * File: Cdn_BunnyCdn_Api.php
 *
 * @since   2.6.0
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdn_BunnyCdn_Api
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 *
 * @since 2.6.0
 */
class Cdn_BunnyCdn_Api {
	/**
	 * Account API Key.
	 *
	 * @since 2.6.0
	 *
	 * @var string
	 */
	private $account_api_key;

	/**
	 * Storage API Key.
	 *
	 * @since 2.6.0
	 *
	 * @var string
	 */
	private $storage_api_key;

	/**
	 * Stream API Key.
	 *
	 * @since 2.6.0
	 *
	 * @var string
	 */
	private $stream_api_key;

	/**
	 * API type.
	 *
	 * One of: "account", "storage", "stream".
	 *
	 * @since 2.6.0
	 *
	 * @var string
	 */
	private $api_type;

	/**
	 * Pull zone id.
	 *
	 * @since 2.6.0
	 *
	 * @var int
	 */
	private $pull_zone_id;

	/**
	 * Default edge rules.
	 *
	 * @since 2.6.0
	 *
	 * @var array
	 */
	private static $default_edge_rules = array(
		array(
			'ActionType'          => 15, // BypassPermaCache.
			'TriggerMatchingType' => 0, // MatchAny.
			'Enabled'             => true,
			'Triggers'            => array(
				array(
					'Type'                => 3, // UrlExtension.
					'PatternMatchingType' => 0, // MatchAny.
					'PatternMatches'      => array( '.zip' ),
				),
			),
			'Description'         => 'Bypass PermaCache for ZIP files',
		),
		array(
			'ActionType'          => 3, // OverrideCacheTime.
			'TriggerMatchingType' => 0, // MatchAny.
			'ActionParameter1'    => '0',
			'ActionParameter2'    => '',
			'Enabled'             => true,
			'Triggers'            => array(
				array(
					'Type'                => 1, // RequestHeader.
					'PatternMatchingType' => 0, // MatchAny.
					'PatternMatches'      => array(
						'*wordpress_logged_in_*',
						'*wordpress_sec_*',
					),
					'Parameter1'          => 'Cookie',
				),
			),
			'Description'         => 'Override Cache Time if logged into WordPress',
		),
		array(
			'ActionType'          => 15, // BypassPermaCache.
			'TriggerMatchingType' => 0, // MatchAny.
			'Enabled'             => true,
			'Triggers'            => array(
				array(
					'Type'                => 1, // RequestHeader.
					'PatternMatchingType' => 0, // MatchAny.
					'PatternMatches'      => array(
						'*wordpress_logged_in_*',
						'*wordpress_sec_*',
					),
					'Parameter1'          => 'Cookie',
				),
			),
			'Description'         => 'Bypass PermaCache if logged into WordPress',
		),
		array(
			'ActionType'          => 16, // OverrideBrowserCacheTime.
			'TriggerMatchingType' => 0, // MatchAny.
			'ActionParameter1'    => '0',
			'Enabled'             => true,
			'Triggers'            => array(
				array(
					'Type'                => 1, // RequestHeader.
					'PatternMatchingType' => 0, // MatchAny.
					'PatternMatches'      => array(
						'*wordpress_logged_in_*',
						'*wordpress_sec_*',
					),
					'Parameter1'          => 'Cookie',
				),
			),
			'Description'         => 'Override Browser Cache Time if logged into WordPress',
		),
	);

	/**
	 * Class constructor for initializing API keys and pull zone ID.
	 *
	 * @since 2.6.0
	 *
	 * @param array $config Configuration array containing API keys and pull zone ID.
	 */
	public function __construct( array $config ) {
		$this->account_api_key = ! empty( $config['account_api_key'] ) ? $config['account_api_key'] : '';
		$this->storage_api_key = ! empty( $config['storage_api_key'] ) ? $config['storage_api_key'] : '';
		$this->stream_api_key  = ! empty( $config['stream_api_key'] ) ? $config['stream_api_key'] : '';
		$this->pull_zone_id    = ! empty( $config['pull_zone_id'] ) ? $config['pull_zone_id'] : '';
	}

	/**
	 * Filters the timeout time.
	 *
	 * @since 2.6.0
	 *
	 * @param int $time The original timeout time.
	 *
	 * @return int The adjusted timeout time.
	 */
	public function filter_timeout_time( $time ) {
		return 600;
	}

	/**
	 * Disables SSL verification for HTTPS requests.
	 *
	 * @since 2.6.0
	 *
	 * @param bool $verify Whether to enable SSL verification (defaults to false).
	 *
	 * @return bool False to disable SSL verification.
	 */
	public function https_ssl_verify( $verify = false ) {
		return false;
	}

	/**
	 * Lists all pull zones.
	 *
	 * @since 2.6.0
	 *
	 * @link https://docs.bunny.net/reference/pullzonepublic_index
	 *
	 * @return array|WP_Error API response or error object.
	 */
	public function list_pull_zones() {
		$this->api_type = 'account';

		return $this->wp_remote_get( \esc_url( 'https://api.bunny.net/pullzone' ) );
	}

	/**
	 * Gets the details of a specific pull zone.
	 *
	 * @since 2.6.0
	 *
	 * @param int $id The pull zone ID.
	 *
	 * @link https://docs.bunny.net/reference/pullzonepublic_index2
	 *
	 * @return array|WP_Error API response or error object.
	 */
	public function get_pull_zone( $id ) {
		$this->api_type = 'account';

		return $this->wp_remote_get(
			\esc_url( 'https://api.bunny.net/pullzone/id' . $id )
		);
	}

	/**
	 * Adds a new pull zone.
	 *
	 * @since 2.6.0
	 *
	 * @param array $data Data for the new pull zone.
	 *
	 * @link https://docs.bunny.net/reference/pullzonepublic_add
	 *
	 * @return array|WP_Error API response or error object.
	 *
	 * @throws \Exception If the pull zone name is invalid.
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
	 * Updates an existing pull zone.
	 *
	 * @since 2.6.0
	 *
	 * @param int   $id   The pull zone ID.
	 * @param array $data Data for updating the pull zone.
	 *
	 * @link https://docs.bunny.net/reference/pullzonepublic_updatepullzone
	 *
	 * @return array|WP_Error API response or error object.
	 *
	 * @throws \Exception If the pull zone ID is invalid.
	 */
	public function update_pull_zone( $id, array $data ) {
		$this->api_type = 'account';
		$id             = empty( $this->pull_zone_id ) ? $id : $this->pull_zone_id;

		if ( empty( $id ) || ! \is_int( $id ) ) {
			throw new \Exception( \esc_html__( 'Invalid pull zone id.', 'w3-total-cache' ) );
		}

		return $this->wp_remote_post(
			'https://api.bunny.net/pullzone/' . $id,
			$data
		);
	}

	/**
	 * Deletes a pull zone.
	 *
	 * @since 2.6.0
	 *
	 * @param int $id The pull zone ID.
	 *
	 * @link https://docs.bunny.net/reference/pullzonepublic_delete
	 *
	 * @return array|WP_Error API response or error object.
	 *
	 * @throws \Exception If the pull zone ID is invalid.
	 */
	public function delete_pull_zone( $id ) {
		$this->api_type = 'account';
		$id             = empty( $this->pull_zone_id ) ? $id : $this->pull_zone_id;

		if ( empty( $id ) || ! \is_int( $id ) ) {
			throw new \Exception( \esc_html__( 'Invalid pull zone id.', 'w3-total-cache' ) );
		}

		return $this->wp_remote_post(
			\esc_url( 'https://api.bunny.net/pullzone/' . $id ),
			array(),
			array( 'method' => 'DELETE' )
		);
	}

	/**
	 * Adds a custom hostname to a pull zone.
	 *
	 * @since 2.6.0
	 *
	 * @param string   $hostname The custom hostname to add.
	 * @param int|null $pull_zone_id The pull zone ID (optional).
	 *
	 * @link https://docs.bunny.net/reference/pullzonepublic_addhostname
	 *
	 * @return void
	 *
	 * @throws \Exception If the pull zone ID or hostname is invalid.
	 */
	public function add_custom_hostname( $hostname, $pull_zone_id = null ) {
		$this->api_type = 'account';
		$pull_zone_id   = empty( $this->pull_zone_id ) ? $pull_zone_id : $this->pull_zone_id;

		if ( empty( $pull_zone_id ) || ! \is_int( $pull_zone_id ) ) {
			throw new \Exception( \esc_html__( 'Invalid pull zone id.', 'w3-total-cache' ) );
		}

		if ( empty( $hostname ) || ! \filter_var( $hostname, FILTER_VALIDATE_DOMAIN ) ) {
			throw new \Exception( \esc_html__( 'Invalid hostname', 'w3-total-cache' ) . ' "' . \esc_html( $hostname ) . '".' );
		}

		$this->wp_remote_post(
			\esc_url( 'https://api.bunny.net/pullzone/' . $pull_zone_id . '/addHostname' ),
			array( 'Hostname' => $hostname )
		);
	}

	/**
	 * Gets the default edge rules for the pull zone.
	 *
	 * @since 2.6.0
	 *
	 * @return array Default edge rules.
	 */
	public static function get_default_edge_rules() {
		return self::$default_edge_rules;
	}

	/**
	 * Adds an edge rule to a pull zone.
	 *
	 * @since 2.6.0
	 *
	 * @param array    $data Data for the edge rule.
	 * @param int|null $pull_zone_id The pull zone ID (optional).
	 *
	 * @return void
	 *
	 * @throws \Exception If any required parameters are missing or invalid.
	 */
	public function add_edge_rule( array $data, $pull_zone_id = null ) {
		$this->api_type = 'account';
		$pull_zone_id   = empty( $this->pull_zone_id ) ? $pull_zone_id : $this->pull_zone_id;

		if ( empty( $pull_zone_id ) || ! \is_int( $pull_zone_id ) ) {
			throw new \Exception( \esc_html__( 'Invalid pull zone id.', 'w3-total-cache' ) );
		}

		if ( ! isset( $data['ActionType'] ) || ! \is_int( $data['ActionType'] ) || $data['ActionType'] < 0 ) {
			throw new \Exception( \esc_html__( 'Invalid parameter "ActionType".', 'w3-total-cache' ) );
		}

		if ( ! isset( $data['TriggerMatchingType'] ) || ! \is_int( $data['TriggerMatchingType'] ) || $data['TriggerMatchingType'] < 0 ) {
			throw new \Exception( \esc_html__( 'Invalid parameter "TriggerMatchingType".', 'w3-total-cache' ) );
		}

		if ( ! isset( $data['Enabled'] ) || ! \is_bool( $data['Enabled'] ) ) {
			throw new \Exception( \esc_html__( 'Missing parameter "Enabled".', 'w3-total-cache' ) );
		}

		if ( empty( $data['Triggers'] ) ) {
			throw new \Exception( \esc_html__( 'Missing parameter "Triggers".', 'w3-total-cache' ) );
		}

		$this->wp_remote_post(
			\esc_url( 'https://api.bunny.net/pullzone/' . $pull_zone_id . '/edgerules/addOrUpdate' ),
			$data
		);
	}

	/**
	 * Purges the cache.
	 *
	 * @since 2.6.0
	 *
	 * @param array $data Data for the purge operation.
	 *
	 * @return array|WP_Error API response or error object.
	 */
	public function purge( array $data ) {
		$this->api_type = 'account';

		return $this->wp_remote_get(
			\esc_url( 'https://api.bunny.net/purge' ),
			$data
		);
	}

	/**
	 * Purges the cache for a specific pull zone.
	 *
	 * @since 2.6.0
	 *
	 * @param int|null $pull_zone_id The pull zone ID (optional).
	 *
	 * @return void
	 *
	 * @throws \Exception If the pull zone ID is invalid.
	 */
	public function purge_pull_zone( $pull_zone_id = null ) {
		$this->api_type = 'account';
		$pull_zone_id   = empty( $this->pull_zone_id ) ? $pull_zone_id : $this->pull_zone_id;

		if ( empty( $pull_zone_id ) || ! \is_int( $pull_zone_id ) ) {
			throw new \Exception( \esc_html__( 'Invalid pull zone id.', 'w3-total-cache' ) );
		}

		$this->wp_remote_post( \esc_url( 'https://api.bunny.net/pullzone/' . $pull_zone_id . '/purgeCache' ) );
	}

	/**
	 * Retrieves the appropriate API key based on the specified type.
	 *
	 * @since 2.6.0
	 *
	 * @param string|null $type The type of API key to retrieve ('account', 'storage', or 'stream').
	 *
	 * @return string The API key.
	 *
	 * @throws \Exception If the API key type is invalid or the key is empty.
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
	 * Decodes the API response.
	 *
	 * @since 2.6.0
	 *
	 * @param array|WP_Error $result The result returned from the API request.
	 *
	 * @return array The decoded response data.
	 *
	 * @throws \Exception If the response is not successful or fails to decode.
	 */
	private function decode_response( $result ) {
		if ( \is_wp_error( $result ) ) {
			throw new \Exception( \esc_html__( 'Failed to reach API endpoint', 'w3-total-cache' ) );
		}

		$response_body = @\json_decode( $result['body'], true );

		// Throw an exception if the response code/status is not ok.
		if ( ! \in_array( $result['response']['code'], array( 200, 201, 204 ), true ) ) {
			$message = isset( $response_body['Message'] ) ? $response_body['Message'] : $result['body'];

			throw new \Exception(
				\esc_html( \__( 'Response code ', 'w3-total-cache' ) . $result['response']['code'] . ': ' . $message )
			);
		}

		return \is_array( $response_body ) ? $response_body : array();
	}


	/**
	 * Sends a GET request to a specified URL with optional data parameters.
	 *
	 * This method sends a GET request using `wp_remote_get` to the specified URL, including optional query parameters.
	 * It also adds custom headers for API authentication and content type. Timeout and SSL verification filters
	 * are applied during the request process. The response is processed using `decode_response` method.
	 *
	 * @since 2.6.0
	 *
	 * @param string $url  The URL to send the GET request to.
	 * @param array  $data Optional. An associative array of data to send as query parameters. Default is an empty array.
	 *
	 * @return mixed The decoded response from the API request.
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
	 * Sends a POST request to a specified URL with optional data and additional arguments.
	 *
	 * This method sends a POST request using `wp_remote_post` to the specified URL, including optional data in the request body
	 * and additional arguments. Custom headers for API authentication, content type, and accept type are included in the request.
	 * Filters for request timeout and SSL verification are applied during the request process. The response is processed using
	 * `decode_response` method.
	 *
	 * @since 2.6.0
	 *
	 * @param string $url   The URL to send the POST request to.
	 * @param array  $data  Optional. An associative array of data to send in the request body. Default is an empty array.
	 * @param array  $args  Optional. Additional arguments to customize the POST request, such as custom headers or settings. Default is an empty array.
	 *
	 * @return mixed The decoded response from the API request.
	 */
	private function wp_remote_post( $url, array $data = array(), array $args = array() ) {
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
					'body'    => empty( $data ) ? null : \wp_json_encode( $data ),
				),
				$args
			)
		);

		\remove_filter( 'https_ssl_verify', array( $this, 'https_ssl_verify' ) );
		\remove_filter( 'http_request_timeout', array( $this, 'filter_timeout_time' ) );

		return self::decode_response( $result );
	}
}
