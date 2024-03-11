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
 * @since 2.6.0
 */
class Cdn_BunnyCdn_Api {
	/**
	 * Account API Key.
	 *
	 * @since  2.6.0
	 * @access private
	 *
	 * @var string
	 */
	private $account_api_key;

	/**
	 * Storage API Key.
	 *
	 * @since  2.6.0
	 * @access private
	 *
	 * @var string
	 */
	private $storage_api_key;

	/**
	 * Stream API Key.
	 *
	 * @since  2.6.0
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
	 * @since  2.6.0
	 * @access private
	 *
	 * @var string
	 */
	private $api_type;

	/**
	 * Pull zone id.
	 *
	 * @since  2.6.0
	 * @access private
	 *
	 * @var int
	 */
	private $pull_zone_id;

	/**
	 * Default Edge Rules.
	 *
	 * @since  2.6.0
	 * @access private
	 * @static
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
	 * Constructor.
	 *
	 * @since 2.6.0
	 *
	 * @param array $config Configuration.
	 */
	public function __construct( array $config ) {
		$this->account_api_key = ! empty( $config['account_api_key'] ) ? $config['account_api_key'] : '';
		$this->storage_api_key = ! empty( $config['storage_api_key'] ) ? $config['storage_api_key'] : '';
		$this->stream_api_key  = ! empty( $config['stream_api_key'] ) ? $config['stream_api_key'] : '';
		$this->pull_zone_id    = ! empty( $config['pull_zone_id'] ) ? $config['pull_zone_id'] : '';
	}

	/**
	 * Increase http request timeout to 60 seconds.
	 *
	 * @since 2.6.0
	 *
	 * @param int $time Timeout in seconds.
	 */
	public function filter_timeout_time( $time ) {
		return 600;
	}

	/**
	 * Don't check certificate, some users have limited CA list
	 *
	 * @since 2.6.0
	 *
	 * @param bool $verify Always false.
	 */
	public function https_ssl_verify( $verify = false ) {
		return false;
	}

	/**
	 * List pull zones.
	 *
	 * @since 2.6.0
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
	 * @since 2.6.0
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
	 * @since 2.6.0
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
	 * @since 2.6.0
	 *
	 * @link https://docs.bunny.net/reference/pullzonepublic_updatepullzone
	 *
	 * @param  int   $id   Optional pull zone ID.  Can be specified in the constructor configuration array parameter.
	 * @param  array $data Data used to update the pull zone.
	 * @return array
	 * @throws \Exception Exception.
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
	 * Delete a pull zone.
	 *
	 * @since 2.6.0
	 *
	 * @link https://docs.bunny.net/reference/pullzonepublic_delete
	 *
	 * @param  int $id Optional pull zone ID.  Can be specified in the constructor configuration array parameter.
	 * @return array
	 * @throws \Exception Exception.
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
	 * Add a custom hostname to a pull zone.
	 *
	 * @since 2.6.0
	 *
	 * @link https://docs.bunny.net/reference/pullzonepublic_addhostname
	 *
	 * @param  string $hostname Custom hostname.
	 * @param  int    $pull_zone_id Optional pull zone ID.  Can be specified in the constructor configuration array parameter.
	 * @return void
	 * @throws \Exception Exception.
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
	 * Get the default edge rules.
	 *
	 * @since  2.6.0
	 * @static
	 *
	 * @return array
	 */
	public static function get_default_edge_rules() {
		return self::$default_edge_rules;
	}

	/**
	 * Add/Update Edge Rule.
	 *
	 * @since 2.6.0
	 *
	 * @param  array $data Data.
	 * @param  int   $pull_zone_id Optional pull zone ID.  Can be specified in the constructor configuration array parameter.
	 * @return void
	 * @throws \Exception Exception.
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
	 * Purge.
	 *
	 * @since 2.6.0
	 *
	 * @param  array $data Data for the POST request.
	 * @return array
	 */
	public function purge( array $data ) {
		$this->api_type = 'account';

		return $this->wp_remote_get(
			\esc_url( 'https://api.bunny.net/purge' ),
			$data
		);
	}

	/**
	 * Purge an entire pull zone.
	 *
	 * @since 2.6.0
	 *
	 * @param  int $pull_zone_id Optional pull zone ID.  Can be specified in the constructor configuration array parameter.
	 * @return void
	 * @throws \Exception Exception.
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
	 * Get the API key by API type.
	 *
	 * API type can be passed or the class property will be used.
	 *
	 * @since 2.6.0
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
	 * @since 2.6.0
	 *
	 * @param  array|WP_Error $result Result.
	 * @return array
	 * @throws \Exception Exception.
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
	 * Remote GET request.
	 *
	 * @since 2.6.0
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
	 * @since 2.6.0
	 *
	 * @link https://developer.wordpress.org/reference/functions/wp_remote_post/
	 * @link https://developer.wordpress.org/reference/classes/wp_http/request/
	 *
	 * @param  string $url URL address.
	 * @param  array  $data Optional data for the POSt request.
	 * @param  array  $args Optional additional arguments for the wp_remote_port call.
	 * @return string
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
