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
	 * @param array $w3tc_config Configuration array containing 'key', 'zone_id', and 'timelimit_api_request'.
	 *                     'email' is required only for the legacy Global API key (37 characters); API tokens omit it.
	 *
	 * @return void
	 */
	public function __construct( $w3tc_config ) {
		$this->_email = isset( $w3tc_config['email'] ) ? trim( (string) $w3tc_config['email'] ) : '';
		$this->_key   = isset( $w3tc_config['key'] ) ? trim( (string) $w3tc_config['key'] ) : '';

		/**
		 * `zone_id` flows into Cloudflare API URLs as a path segment
		 * (`/zones/<zone_id>/settings/<name>`). The API host is locked
		 * to api.cloudflare.com so an unexpected zone_id value can at
		 * most rewrite the API path (extra `/`, query separators) —
		 * the validation is cheap and closes that path.
		 */
		$raw_zone_id    = isset( $w3tc_config['zone_id'] ) ? (string) $w3tc_config['zone_id'] : '';
		$this->_zone_id = self::validate_api_path_segment( $raw_zone_id );

		if ( ! isset( $w3tc_config['timelimit_api_request'] ) ||
			$w3tc_config['timelimit_api_request'] < 1 ) {
			$this->_timelimit_api_request = 30;
		} else {
			$this->_timelimit_api_request = $w3tc_config['timelimit_api_request'];
		}
	}

	/**
	 * Returns the input untouched if it matches the conservative
	 * Cloudflare path-segment alphabet (`A-Z a-z 0-9 . _ -`), otherwise
	 * returns the empty string. Used to gate every external input that
	 * lands in an API URL path segment.
	 *
	 * @since 2.10.0
	 *
	 * @param string $w3tc_value Candidate segment.
	 *
	 * @return string Validated segment, or '' on rejection.
	 */
	private static function validate_api_path_segment( $w3tc_value ) {
		if ( ! \is_string( $w3tc_value ) ) {
			return '';
		}
		/**
		 * Trim before the alphabet check. Stored config values often
		 * carry stray whitespace from copy-paste / multi-line dashboards;
		 * rejecting those would silently break installs whose stored
		 * zone_id has a trailing newline / space / tab.
		 */
		$w3tc_value = \trim( $w3tc_value );
		if ( '' === $w3tc_value ) {
			return '';
		}
		if ( ! \preg_match( '/^[A-Za-z0-9._-]+$/', $w3tc_value ) ) {
			return '';
		}
		return $w3tc_value;
	}

	/**
	 * Whether the key string is a legacy Global API Key (37 characters, legacy dashboard format).
	 *
	 * Any other length uses Bearer authentication for API tokens (including historical 40-character
	 * tokens and newer Cloudflare token formats).
	 *
	 * @since 2.10.0
	 *
	 * @param string $w3tc_key Raw key from settings or request.
	 *
	 * @return bool
	 */
	public static function is_legacy_global_api_key_string( $w3tc_key ) {
		$w3tc_key = trim( (string) $w3tc_key );

		return 37 === \strlen( $w3tc_key );
	}

	/**
	 * Whether email and key are sufficient to authenticate to the Cloudflare v4 API.
	 *
	 * Global API keys require the account email. API tokens only require a non-empty token.
	 *
	 * @since 2.10.0
	 *
	 * @param string $email Account email (may be empty when using an API token).
	 * @param string $w3tc_key   Global API key or API token.
	 *
	 * @return bool
	 */
	public static function are_api_credentials_usable( $email, $w3tc_key ) {
		$w3tc_key = trim( (string) $w3tc_key );
		if ( '' === $w3tc_key ) {
			return false;
		}

		if ( self::is_legacy_global_api_key_string( $w3tc_key ) ) {
			return '' !== trim( (string) $email );
		}

		return true;
	}

	/**
	 * Sends an external event notification to Cloudflare.
	 *
	 * @param string $type  The event type.
	 * @param string $w3tc_value The event value.
	 *
	 * @return mixed|null Decoded response body or null on failure.
	 */
	public function external_event( $type, $w3tc_value ) {
		$w3tc_url = sprintf(
			'https://www.cloudflare.com/ajax/external-event.html?u=%s&tkn=%s&evnt_t=%s&evnt_v=%s',
			rawurlencode( $this->_email ),
			rawurlencode( $this->_key ),
			rawurlencode( $type ),
			rawurlencode( $w3tc_value )
		);
		$response = Util_Http::get( $w3tc_url );

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
		$w3tc_data = array();
		$response  = Util_Http::get( 'https://www.cloudflare.com/ips-v4' );

		if ( ! is_wp_error( $response ) ) {
			$ip4_data         = $response['body'];
			$ip4_data         = explode( "\n", $ip4_data );
			$w3tc_data['ip4'] = $ip4_data;
		}

		$response = Util_Http::get( 'https://www.cloudflare.com/ips-v6' );

		if ( ! is_wp_error( $response ) ) {
			$ip6_data         = $response['body'];
			$ip6_data         = explode( "\n", $ip6_data );
			$w3tc_data['ip6'] = $ip6_data;
		}

		return $w3tc_data;
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
		$id = self::validate_api_path_segment( (string) $id );
		if ( '' === $id ) {
			return array();
		}

		$w3tc_a = $this->_wp_remote_request( 'GET', self::$_root_uri . '/zones/' . $id );

		return $w3tc_a;
	}

	/**
	 * Retrieves the settings for a specific zone.
	 *
	 * @return array An associative array of settings indexed by their IDs.
	 */
	public function zone_settings() {
		/**
		 * Refuse to issue `/zones//settings` when the validated zone_id
		 * was empty (constructor rejected the configured value, or no
		 * zone is configured yet). Without this guard the API call
		 * returns a confusing 4xx that surfaces as a generic admin
		 * error; bailing out early produces an empty result set and
		 * keeps the caller's `foreach` safe.
		 */
		if ( '' === $this->_zone_id ) {
			return array();
		}

		$w3tc_a = $this->_wp_remote_request( 'GET', self::$_root_uri . '/zones/' . $this->_zone_id . '/settings' );

		$by_id = array();
		foreach ( $w3tc_a as $w3tc_i ) {
			$by_id[ $w3tc_i['id'] ] = $w3tc_i;
		}

		return $by_id;
	}

	/**
	 * Updates a specific zone setting.
	 *
	 * @param string $w3tc_name  The name of the setting to update.
	 * @param mixed  $w3tc_value The new value for the setting.
	 *
	 * @return array The response from the Cloudflare API.
	 */
	public function zone_setting_set( $w3tc_name, $w3tc_value ) {
		/**
		 * `$w3tc_name` becomes a path segment. The legacy code spliced any
		 * admin-supplied string straight in; restrict to the Cloudflare
		 * setting-name alphabet so a value like `evil/../foo` can't
		 * bend the URL.
		 */
		$w3tc_name = self::validate_api_path_segment( (string) $w3tc_name );
		if ( '' === $w3tc_name || '' === $this->_zone_id ) {
			return array();
		}

		// Convert numeric values to the integer type.
		if ( is_numeric( $w3tc_value ) ) {
			$w3tc_value = intval( $w3tc_value );
		}

		return $this->_wp_remote_request(
			'PATCH',
			self::$_root_uri . '/zones/' . $this->_zone_id . '/settings/' . $w3tc_name,
			wp_json_encode( array( 'value' => $w3tc_value ) )
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
		/**
		 * Empty zone_id (constructor rejected the configured value) →
		 * empty result set; the GraphQL query would otherwise inline
		 * `zoneTag: ""` and the API returns an error that surfaces as
		 * "analytics broken" to the admin.
		 */
		if ( '' === $this->_zone_id ) {
			return array();
		}

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
		/**
		 * Empty zone_id (constructor rejected the configured value) →
		 * don't issue `DELETE /zones//purge_cache`. The API responds
		 * with a generic 4xx that the cache-flush admin surfaces as a
		 * "purge failed" alert; bailing out early is the same effect
		 * without the request round trip.
		 */
		if ( '' === $this->_zone_id ) {
			return array();
		}

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
	 * @param string $w3tc_url    The API endpoint URL.
	 * @param mixed  $body   Optional. The request body.
	 *
	 * @return array The decoded response result.
	 *
	 * @throws \Exception If authentication is missing or the request fails.
	 */
	private function _wp_remote_request( $method, $w3tc_url, $body = array() ) {
		if ( ! $this->_credentials_configured() ) {
			throw new \Exception( \esc_html__( 'Not authenticated.', 'w3-total-cache' ) );
		}

		$headers = $this->_generate_wp_remote_request_headers();

		$w3tc_result = wp_remote_request(
			$w3tc_url,
			array(
				'method'  => $method,
				'headers' => $headers,
				'timeout' => $this->_timelimit_api_request,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $w3tc_result ) ) {
			throw new \Exception( \esc_html__( 'Failed to reach API endpoint.', 'w3-total-cache' ) );
		}

		$response_json = @json_decode( $w3tc_result['body'], true );
		if ( is_null( $response_json ) || ! isset( $response_json['success'] ) ) {
			throw new \Exception(
				\esc_html(
					sprintf(
						// Translators: 1 Result body.
						\__( 'Failed to reach API endpoint, got unexpected response: %1$s', 'w3-total-cache' ),
						str_replace( '<', '.', str_replace( '>', '.', $w3tc_result['body'] ) )
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
	 * @param string $w3tc_url    The API endpoint URL.
	 * @param mixed  $body   Optional. The request body.
	 *
	 * @return array The full response including metadata.
	 *
	 * @throws \Exception If authentication is missing or the request fails.
	 */
	private function _wp_remote_request_with_meta( $method, $w3tc_url, $body = array() ) {
		if ( ! $this->_credentials_configured() ) {
			throw new \Exception( \esc_html__( 'Not authenticated.', 'w3-total-cache' ) );
		}

		$headers = $this->_generate_wp_remote_request_headers();

		$w3tc_result = wp_remote_request(
			$w3tc_url,
			array(
				'method'  => $method,
				'headers' => $headers,
				'timeout' => $this->_timelimit_api_request,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $w3tc_result ) ) {
			throw new \Exception( \esc_html__( 'Failed to reach API endpoint.', 'w3-total-cache' ) );
		}

		$response_json = @json_decode( $w3tc_result['body'], true );
		if ( is_null( $response_json ) || ! isset( $response_json['success'] ) ) {
			throw new \Exception(
				\esc_html(
					sprintf(
						// Translators: 1 Response body.
						\__( 'Failed to reach API endpoint, got unexpected response: %1$s', 'w3-total-cache' ),
						$w3tc_result['body']
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
	 * @param string $w3tc_url    The GraphQL API endpoint URL.
	 * @param string $body   The GraphQL query string.
	 *
	 * @return array The response data from the API.
	 *
	 * @throws \Exception If authentication is missing or the request fails.
	 */
	private function _wp_remote_request_graphql( $method, $w3tc_url, $body ) {
		if ( ! $this->_credentials_configured() ) {
			throw new \Exception( \esc_html__( 'Not authenticated.', 'w3-total-cache' ) );
		}

		$headers = $this->_generate_wp_remote_request_headers();

		$body = preg_replace( '/\s\s+/', ' ', $body );

		$w3tc_result = wp_remote_request(
			$w3tc_url,
			array(
				'method'  => $method,
				'headers' => $headers,
				'timeout' => $this->_timelimit_api_request,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $w3tc_result ) ) {
			throw new \Exception( \esc_html__( 'Failed to reach API endpoint.', 'w3-total-cache' ) );
		}

		$response_json = @json_decode( $w3tc_result['body'], true );
		if ( is_null( $response_json ) ) {
			throw new \Exception(
				\esc_html(
					sprintf(
						// Translators: 1 Response body.
						\__( 'Failed to reach API endpoint, got unexpected response: %1$s', 'w3-total-cache' ),
						str_replace( '<', '.', str_replace( '>', '.', $w3tc_result['body'] ) )
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
	 * Whether the stored key matches the legacy Global API Key format (X-Auth-Key + X-Auth-Email).
	 *
	 * Cloudflare Global API keys are 37 characters. API tokens use Bearer auth and are opaque strings
	 * of varying lengths (including historical 40-character tokens and newer formats).
	 *
	 * @since 2.10.0
	 *
	 * @return bool
	 */
	private function _is_legacy_global_api_key() {
		return self::is_legacy_global_api_key_string( $this->_key );
	}

	/**
	 * Whether credentials are sufficient for the detected auth mode.
	 *
	 * API tokens require only a non-empty key. Legacy global keys also require the account email.
	 *
	 * @since 2.10.0
	 *
	 * @return bool
	 */
	private function _credentials_configured() {
		return self::are_api_credentials_usable( $this->_email, $this->_key );
	}

	/**
	 * Generates HTTP request headers for Cloudflare API requests.
	 *
	 * @return array The headers array for API requests.
	 *
	 * @throws \Exception If the authentication credentials are invalid or missing.
	 */
	private function _generate_wp_remote_request_headers() {
		if ( ! $this->_credentials_configured() ) {
			throw new \Exception(
				\esc_html__(
					'Missing API token, or Global API key with account email.',
					'w3-total-cache'
				)
			);
		}

		if ( $this->_is_legacy_global_api_key() ) {
			return array(
				'Content-Type' => 'application/json',
				'X-Auth-Key'   => $this->_key,
				'X-Auth-Email' => $this->_email,
			);
		}

		return array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $this->_key,
		);
	}
}
