<?php
/**
 * File: Extension_CloudFlare_Api.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_CloudFlare_Api
 *
 * Cloudflare API.
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */
class Extension_CloudFlare_Api {
	/**
	 * Root URI
	 *
	 * @var string
	 */
	private static $_root_uri = 'https://api.cloudflare.com/client/v4';

	/**
	 * Email
	 *
	 * @var string
	 */
	private $_email;

	/**
	 * Key
	 *
	 * @var string
	 */
	private $_key;

	/**
	 * Zone ID
	 *
	 * @var string
	 */
	private $_zone_id;

	/**
	 * API request time limit
	 *
	 * @var int
	 */
	private $_timelimit_api_request;

	/**
	 * Constructs the Cloudflare API client with the provided configuration.
	 *
	 * @param array $config Configuration array containing 'email', 'key', 'zone_id', and 'timelimit_api_request'.
	 *
	 * @return void
	 */
	public function __construct( $config ) {
		$this->_email   = $config['email'];
		$this->_key     = $config['key'];
		$this->_zone_id = ( isset( $config['zone_id'] ) ? $config['zone_id'] : '' );

		if ( ! isset( $config['timelimit_api_request'] ) ||
			$config['timelimit_api_request'] < 1 ) {
			$this->_timelimit_api_request = 30;
		} else {
			$this->_timelimit_api_request = $config['timelimit_api_request'];
		}
	}

	/**
	 * Sends an external event notification to Cloudflare.
	 *
	 * @param string $type  The event type.
	 * @param string $value The event value.
	 *
	 * @return mixed|null Decoded response body or null on failure.
	 */
	public function external_event( $type, $value ) {
		$url      = sprintf(
			'https://www.cloudflare.com/ajax/external-event.html?u=%s&tkn=%s&evnt_t=%s&evnt_v=%s',
			rawurlencode( $this->_email ),
			rawurlencode( $this->_key ),
			rawurlencode( $type ),
			rawurlencode( $value )
		);
		$response = Util_Http::get( $url );

		if ( ! is_wp_error( $response ) ) {
			return json_decode( $response['body'] );
		}

		return null;
	}

	/**
	 * Retrieves the Cloudflare IP ranges for IPv4 and IPv6.
	 *
	 * @return array An array containing 'ip4' and 'ip6' keys with corresponding IP ranges.
	 */
	public function get_ip_ranges() {
		$data     = array();
		$response = Util_Http::get( 'https://www.cloudflare.com/ips-v4' );

		if ( ! is_wp_error( $response ) ) {
			$ip4_data    = $response['body'];
			$ip4_data    = explode( "\n", $ip4_data );
			$data['ip4'] = $ip4_data;
		}

		$response = Util_Http::get( 'https://www.cloudflare.com/ips-v6' );

		if ( ! is_wp_error( $response ) ) {
			$ip6_data    = $response['body'];
			$ip6_data    = explode( "\n", $ip6_data );
			$data['ip6'] = $ip6_data;
		}

		return $data;
	}

	/**
	 * Retrieves a list of zones.
	 *
	 * @param int $page The page number to fetch.
	 *
	 * @return array An array of zone data.
	 */
	public function zones( $page = 1 ) {
		return $this->_wp_remote_request_with_meta( 'GET', self::$_root_uri . '/zones?page=' . rawurlencode( $page ) );
	}

	/**
	 * Retrieves details of a specific zone.
	 *
	 * @param string $id The ID of the zone to retrieve.
	 *
	 * @return array An array of zone details.
	 */
	public function zone( $id ) {
		$a = $this->_wp_remote_request( 'GET', self::$_root_uri . '/zones/' . $id );

		return $a;
	}

	/**
	 * Retrieves the settings for a specific zone.
	 *
	 * @return array An associative array of settings indexed by their IDs.
	 */
	public function zone_settings() {
		$a = $this->_wp_remote_request( 'GET', self::$_root_uri . '/zones/' . $this->_zone_id . '/settings' );

		$by_id = array();
		foreach ( $a as $i ) {
			$by_id[ $i['id'] ] = $i;
		}

		return $by_id;
	}

	/**
	 * Updates a specific zone setting.
	 *
	 * @param string $name  The name of the setting to update.
	 * @param mixed  $value The new value for the setting.
	 *
	 * @return array The response from the Cloudflare API.
	 */
	public function zone_setting_set( $name, $value ) {
		// Convert numeric values to the integer type.
		if ( is_numeric( $value ) ) {
			$value = intval( $value );
		}

		return $this->_wp_remote_request(
			'PATCH',
			self::$_root_uri . '/zones/' . $this->_zone_id . '/settings/' . $name,
			wp_json_encode( array( 'value' => $value ) )
		);
	}

	/**
	 * Retrieves analytics data for the zone.
	 *
	 * phpcs:disable Squiz.Strings.DoubleQuoteUsage.NotRequired
	 *
	 * @param string $start The start date in ISO 8601 format.
	 * @param string $end   The end date in ISO 8601 format.
	 * @param string $type  The granularity of data ('day' or 'hour'). Default is 'day'.
	 *
	 * @return array The analytics data.
	 */
	public function analytics_dashboard( $start, $end, $type = 'day' ) {
		$dataset         = 'httpRequests1dGroups';
		$datetime_filter = 'date';

		if ( 'hour' === $type ) {
			$dataset         = 'httpRequests1hGroups';
			$datetime_filter = 'datetime';
		}

		return $this->_wp_remote_request_graphql(
			'POST',
			self::$_root_uri . '/graphql',
			"{ \"query\": \"query {
				viewer {
					zones(filter: {zoneTag: \\\"" . $this->_zone_id . "\\\"}) {
						" . $dataset . "(
							orderBy: [" . $datetime_filter . "_ASC],
							limit: 100,
							filter: {
								" . $datetime_filter . "_geq: \\\"" . $start . "\\\",
								" . $datetime_filter . "_lt: \\\"" . $end . "\\\"
							}
						) {
							dimensions {
								" . $datetime_filter . "
							}
							sum {
								bytes
								cachedBytes
								cachedRequests
								pageViews
								requests
								threats
							}
							uniq {
								uniques
							}
						}
					}
				}
			}\"}"
		);
	}

	/**
	 * Purges the cache for the entire zone.
	 *
	 * @return array The response from the Cloudflare API.
	 */
	public function purge() {
		return $this->_wp_remote_request(
			'DELETE',
			self::$_root_uri . '/zones/' . $this->_zone_id . '/purge_cache',
			'{"purge_everything":true}'
		);
	}

	/**
	 * Sends an HTTP request to the Cloudflare API.
	 *
	 * @param string $method The HTTP method ('GET', 'POST', 'PATCH', 'DELETE').
	 * @param string $url    The API endpoint URL.
	 * @param mixed  $body   Optional. The request body.
	 *
	 * @return array The decoded response result.
	 *
	 * @throws \Exception If authentication is missing or the request fails.
	 */
	private function _wp_remote_request( $method, $url, $body = array() ) {
		if ( empty( $this->_email ) || empty( $this->_key ) ) {
			throw new \Exception( \esc_html__( 'Not authenticated.', 'w3-total-cache' ) );
		}

		$headers = $this->_generate_wp_remote_request_headers();

		$result = wp_remote_request(
			$url,
			array(
				'method'  => $method,
				'headers' => $headers,
				'timeout' => $this->_timelimit_api_request,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $result ) ) {
			throw new \Exception( \esc_html__( 'Failed to reach API endpoint.', 'w3-total-cache' ) );
		}

		$response_json = @json_decode( $result['body'], true );
		if ( is_null( $response_json ) || ! isset( $response_json['success'] ) ) {
			throw new \Exception(
				\esc_html(
					sprintf(
						// Translators: 1 Result body.
						\__( 'Failed to reach API endpoint, got unexpected response: %1$s', 'w3-total-cache' ),
						str_replace( '<', '.', str_replace( '>', '.', $result['body'] ) )
					)
				)
			);
		}

		if ( ! $response_json['success'] ) {
			$errors = array();

			if ( isset( $response_json['errors'] ) ) {
				foreach ( $response_json['errors'] as $e ) {
					if ( ! empty( $e['message'] ) ) {
						$errors[] = $e['message'];
					}
				}
			}

			if ( empty( $errors ) ) {
				$errors[] = 'Request failed';
			}

			throw new \Exception( \esc_html( implode( ', ', $errors ) ) );
		}

		if ( isset( $response_json['result'] ) ) {
			return $response_json['result'];
		}

		return array();
	}

	/**
	 * Sends an HTTP request to the Cloudflare API and includes metadata.
	 *
	 * @param string $method The HTTP method ('GET', 'POST', 'PATCH', 'DELETE').
	 * @param string $url    The API endpoint URL.
	 * @param mixed  $body   Optional. The request body.
	 *
	 * @return array The full response including metadata.
	 *
	 * @throws \Exception If authentication is missing or the request fails.
	 */
	private function _wp_remote_request_with_meta( $method, $url, $body = array() ) {
		if ( empty( $this->_email ) || empty( $this->_key ) ) {
			throw new \Exception( \esc_html__( 'Not authenticated.', 'w3-total-cache' ) );
		}

		$headers = $this->_generate_wp_remote_request_headers();

		$result = wp_remote_request(
			$url,
			array(
				'method'  => $method,
				'headers' => $headers,
				'timeout' => $this->_timelimit_api_request,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $result ) ) {
			throw new \Exception( \esc_html__( 'Failed to reach API endpoint.', 'w3-total-cache' ) );
		}

		$response_json = @json_decode( $result['body'], true );
		if ( is_null( $response_json ) || ! isset( $response_json['success'] ) ) {
			throw new \Exception(
				\esc_html(
					sprintf(
						// Translators: 1 Response body.
						\__( 'Failed to reach API endpoint, got unexpected response: %1$s', 'w3-total-cache' ),
						$result['body']
					)
				)
			);
		}

		if ( ! $response_json['success'] ) {
			$errors = array();

			if ( isset( $response_json['errors'] ) ) {
				foreach ( $response_json['errors'] as $e ) {
					if ( ! empty( $e['message'] ) ) {
						$errors[] = $e['message'];
					}
				}
			}

			if ( empty( $errors ) ) {
				$errors[] = 'Request failed';
			}

			throw new \Exception( \esc_html( implode( ', ', $errors ) ) );
		}

		if ( isset( $response_json['result'] ) ) {
			return $response_json;
		}

		return array();
	}

	/**
	 * Sends a GraphQL request to the Cloudflare API.
	 *
	 * @param string $method The HTTP method ('POST').
	 * @param string $url    The GraphQL API endpoint URL.
	 * @param string $body   The GraphQL query string.
	 *
	 * @return array The response data from the API.
	 *
	 * @throws \Exception If authentication is missing or the request fails.
	 */
	private function _wp_remote_request_graphql( $method, $url, $body ) {
		if ( empty( $this->_email ) || empty( $this->_key ) ) {
			throw new \Exception( \esc_html__( 'Not authenticated.', 'w3-total-cache' ) );
		}

		$headers = $this->_generate_wp_remote_request_headers();

		$body = preg_replace( '/\s\s+/', ' ', $body );

		$result = wp_remote_request(
			$url,
			array(
				'method'  => $method,
				'headers' => $headers,
				'timeout' => $this->_timelimit_api_request,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $result ) ) {
			throw new \Exception( \esc_html__( 'Failed to reach API endpoint.', 'w3-total-cache' ) );
		}

		$response_json = @json_decode( $result['body'], true );
		if ( is_null( $response_json ) ) {
			throw new \Exception(
				\esc_html(
					sprintf(
						// Translators: 1 Response body.
						\__( 'Failed to reach API endpoint, got unexpected response: %1$s', 'w3-total-cache' ),
						str_replace( '<', '.', str_replace( '>', '.', $result['body'] ) )
					)
				)
			);
		}

		if ( isset( $response_json['errors'] ) ) {
			$errors = array();

			foreach ( $response_json['errors'] as $e ) {
				if ( ! empty( $e['message'] ) ) {
					$errors[] = $e['message'];
				}
			}

			if ( empty( $errors ) ) {
				$errors[] = 'Request failed';
			}

			throw new \Exception( \esc_html( implode( ', ', $errors ) ) );
		}

		if ( isset( $response_json['data'] ) ) {
			return $response_json['data'];
		}

		return array();
	}

	/**
	 * Generates HTTP request headers for Cloudflare API requests.
	 *
	 * @return array The headers array for API requests.
	 *
	 * @throws \Exception If the authentication credentials are invalid or missing.
	 */
	private function _generate_wp_remote_request_headers() {
		if ( empty( $this->_email ) || empty( $this->_key ) ) {
			throw new \Exception( \esc_html__( 'Missing authentication email and/or API token / global key.', 'w3-total-cache' ) );
		}

		$headers = array();

		if ( 40 === strlen( $this->_key ) ) { // CF API Token.
			$headers = array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->_key,
			);
		} elseif ( 37 === strlen( $this->_key ) ) { // CF Legacy API Global Key.
			$headers = array(
				'Content-Type' => 'application/json',
				'X-Auth-Key'   => $this->_key,
				'X-Auth-Email' => $this->_email,
			);
		} else {
			throw new \Exception( \esc_html__( 'Improper API token / global key length.', 'w3-total-cache' ) );
		}

		return $headers;
	}
}
