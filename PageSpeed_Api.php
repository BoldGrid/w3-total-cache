<?php
/**
 * File: PageSpeed_Api.php
 *
 * Controls Google OAuth2.0 requests both for authentication and queries against the PageSpeed API.
 *
 * @since 2.3.0 Update to utilize OAuth2.0 and overhaul of feature.
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * PageSpeed API.
 *
 * @since 2.0.0
 */
class PageSpeed_Api {
	/**
	 * Delay automatic retries after a failed token refresh.
	 *
	 * @var int
	 */
	const REFRESH_RETRY_DELAY = 15 * MINUTE_IN_SECONDS;

	/**
	 * Config.
	 *
	 * @var object
	 */
	private $w3tc_config;

	/**
	 * W3TCG_Google_Client.
	 *
	 * @var object
	 */
	public $client;

	/**
	 * Retry Attemps. Overwritten by W3TC_PAGESPEED_MAX_ATTEMPTS constant.
	 *
	 * @var string
	 */
	private $retry_attempts = 4;

	/**
	 * Google PageSpeed API URL. Overwritten by W3TC_PAGESPEED_API_URL constant.
	 *
	 * @var string
	 */
	private $pagespeed_api_base_url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

	/**
	 * PageSpeed API constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param string $w3tc_access_token_json API access token JSON.
	 */
	public function __construct( $w3tc_access_token_json = null ) {
		$this->w3tc_config = Dispatcher::config();
		$this->client      = new \W3TCG_Google_Client();
		$this->client->setApplicationName( 'W3TC PageSpeed Analyzer' );
		$this->client->addScope( 'openid' );
		$this->client->setAccessType( 'offline' );
		$this->client->setApprovalPrompt( 'force' );
		$this->client->setDefer( true );

		if ( ! empty( $w3tc_access_token_json ) ) {
			$this->client->setAccessToken( $w3tc_access_token_json );
			$this->maybe_refresh_token();
		}
	}
	/**
	 * Run PageSpeed API.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'admin_notices', array( $this, 'errors_notice' ) );
	}
	/**
	 * Fully analyze URL via PageSpeed API.
	 *
	 * @since 2.0.0
	 *
	 * @param string $w3tc_url URL to analyze via PageSpeed API.
	 *
	 * @return array
	 */
	public function analyze( $w3tc_url ) {
		$mobile  = $this->analyze_strategy( $w3tc_url, 'mobile' );
		$desktop = $this->analyze_strategy( $w3tc_url, 'desktop' );
		return array(
			'mobile'   => $mobile,
			'desktop'  => $desktop,
			'test_url' => Util_Environment::url_format(
				$this->get_pagespeed_url(),
				array( 'url' => $w3tc_url )
			),
		);
	}

	/**
	 * Analyze URL via PageSpeed API using strategy.
	 *
	 * @since 2.0.0
	 *
	 * @param string $w3tc_url URL to analyze.
	 * @param string $strategy Strategy to use desktop/mobile.
	 *
	 * @return array
	 */
	public function analyze_strategy( $w3tc_url, $strategy ) {
		$w3tc_data = $this->process_request(
			array(
				'url'      => $w3tc_url,
				'category' => 'performance',
				'strategy' => $strategy,
			)
		);

		if ( ! empty( Util_PageSpeed::get_value_recursive( $w3tc_data, array( 'error', 'code' ) ) ) ) {
			return array(
				'error' => array(
					'code'    => Util_PageSpeed::get_value_recursive( $w3tc_data, array( 'error', 'code' ) ),
					'message' => Util_PageSpeed::get_value_recursive( $w3tc_data, array( 'error', 'message' ) ),
				),
			);
		}

		return PageSpeed_Data::prepare_pagespeed_data( $w3tc_data );
	}

	/**
	 * Make API request.
	 *
	 * @since 2.3.0
	 *
	 * @param string $query API request query.
	 *
	 * @return array
	 */
	public function process_request( $query ) {
		$w3tc_access_token_json = $this->client->getAccessToken();

		if ( empty( $w3tc_access_token_json ) ) {
			return array(
				'error' => array(
					'code'    => 403,
					'message' => __( 'Missing Google access token.', 'w3-total-cache' ),
				),
			);
		}

		$w3tc_access_token = json_decode( $w3tc_access_token_json );

		$request = Util_Environment::url_format(
			$this->get_pagespeed_url(),
			array_merge(
				$query,
				array(
					'quotaUser'    => Util_Http::generate_site_id(),
					'access_token' => $w3tc_access_token->access_token,
				)
			)
		);

		// Attempt the request up to x times with an increasing delay between each attempt. Uses W3TC_PAGESPEED_MAX_ATTEMPTS constant if defined.
		$attempts = 0;
		$response = null;

		while ( ++$attempts <= $this->get_max_attempts() ) {
			try {
				$response = wp_remote_get(
					$request,
					array(
						'timeout' => 60,
					)
				);

				if ( ! is_wp_error( $response ) && 200 === $response['response']['code'] ) {
					break;
				}
			} catch ( \Exception $e ) {
				if ( $attempts >= $this->get_max_attempts() ) {
					return array(
						'error' => array(
							'code'    => 500,
							'message' => $e->getMessage(),
						),
					);
				}
			}

			// Sleep for a cumulative .5 seconds each attempt.
			usleep( $attempts * 500000 );
		}

		// wp_remote_get() returns a WP_Error (it does not throw) on transport failures such as a timeout,
		// DNS failure, or refused connection. Without this guard the array access below fatals with
		// "Cannot use object of type WP_Error as array" once the retries are exhausted.
		if ( is_wp_error( $response ) ) {
			return array(
				'error' => array(
					'code'    => $response->get_error_code(),
					'message' => $response->get_error_message(),
				),
			);
		}

		if ( isset( $response['response']['code'] ) && 200 !== $response['response']['code'] ) {
			// Google PageSpeed Insights sometimes will return a 500 and message body with details so we still grab the body response.
			$decoded_body = json_decode( wp_remote_retrieve_body( $response ), true );
			return array(
				'error' => array(
					'code'    => $response['response']['code'],
					'message' => ( ! empty( $decoded_body['error']['message'] ) ? $decoded_body['error']['message'] : $response['response']['message'] ),
				),
			);
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * Checks if the Google access token is expired and attempts to refresh.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function maybe_refresh_token() {
		$w3tc_site_id       = Util_Http::generate_site_id();
		$w3tc_pagespeed_key = $this->w3tc_config->get_string( 'widget.pagespeed.w3tc_pagespeed_key' );
		$retry_after        = (int) get_option( 'w3tcps_refresh_retry_after' );

		if (
			$this->client->isAccessTokenExpired() &&
			! empty( $w3tc_pagespeed_key ) &&
			$retry_after <= time()
		) {
			$this->refresh_token( $w3tc_site_id, $w3tc_pagespeed_key );
		}
	}

	/**
	 * Refreshes the Google access token if a valid refresh token is defined.
	 *
	 * @param string $w3tc_site_id            Site ID.
	 * @param string $w3tc_pagespeed_key W3 API access key.
	 *
	 * @return void
	 */
	public function refresh_token( $w3tc_site_id, $w3tc_pagespeed_key ) {
		if ( empty( $w3tc_site_id ) || empty( $w3tc_pagespeed_key ) ) {
			$this->set_refresh_failure(
				__( 'Google PageSpeed access token refresh missing required parameters!', 'w3-total-cache' )
			);
			return;
		}

		$request = Util_Environment::get_api_base_url() . '/google/refresh-token/' . rawurlencode( $w3tc_site_id ) . '/' . rawurlencode( $w3tc_pagespeed_key );

		$response = wp_remote_get(
			$request,
			array(
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->set_refresh_failure(
				__( 'Google PageSpeed access token refresh failed.', 'w3-total-cache' ),
				$response->get_error_message()
			);
			return;
		}

		$response_code = (int) wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $response_body ) ) {
			$response_body = array();
		}

		$w3tc_error_id = '';
		if ( isset( $response_body['error'] ) && is_array( $response_body['error'] ) ) {
			$w3tc_error_id = isset( $response_body['error']['id'] ) ? (string) $response_body['error']['id'] : '';
		}

		if (
			$response_code < 200 ||
			$response_code >= 300 ||
			isset( $response_body['error'] )
		) {
			$this->set_refresh_failure(
				__( 'Google PageSpeed access token refresh failed.', 'w3-total-cache' ),
				$this->get_refresh_failure_message( $w3tc_error_id, $response_body, $response_code )
			);

			if (
				in_array(
					$w3tc_error_id,
					array(
						'refresh-token-not-found',
						'refresh-token-missing-refresh-token',
					),
					true
				)
			) {
				$this->clear_pagespeed_credentials();
			}
			return;
		}

		if (
			empty( $response_body['access_token'] ) ||
			! is_string( $response_body['access_token'] ) ||
			empty( $response_body['expires_in'] ) ||
			! is_numeric( $response_body['expires_in'] )
		) {
			$this->set_refresh_failure(
				__( 'Google PageSpeed access token refresh failed due to response missing access token.', 'w3-total-cache' )
			);
			return;
		}

		$response_body['created'] = time();

		$w3tc_access_token = wp_json_encode( $response_body );
		if ( false === $w3tc_access_token ) {
			$this->set_refresh_failure(
				__( 'Google PageSpeed access token refresh failed because the response could not be encoded.', 'w3-total-cache' )
			);
			return;
		}

		$this->w3tc_config->set( 'widget.pagespeed.access_token', $w3tc_access_token );
		$this->w3tc_config->save();
		$this->client->setAccessToken( $w3tc_access_token );
		self::clear_failure_notices();
	}

	/**
	 * Records a token refresh failure for the admin notice.
	 *
	 * @since X.X.X
	 *
	 * @param string $title   Notice title.
	 * @param string $message Optional failure detail.
	 *
	 * @return void
	 */
	private function set_refresh_failure( $title, $message = '' ) {
		update_option( 'w3tcps_refresh_fail', $title );
		update_option( 'w3tcps_refresh_fail_message', $message );
		update_option( 'w3tcps_refresh_retry_after', time() + self::REFRESH_RETRY_DELAY );
	}

	/**
	 * Returns operator-facing text for a token refresh failure.
	 *
	 * @since X.X.X
	 *
	 * @param string $w3tc_error_id Nested W3 API error ID, if any.
	 * @param array  $response_body Decoded response body.
	 * @param int    $response_code HTTP response code.
	 *
	 * @return string
	 */
	private function get_refresh_failure_message( $w3tc_error_id, $response_body, $response_code ) {
		switch ( $w3tc_error_id ) {
			case 'refresh-token-missing-site-id':
				return __( 'No site ID provided for access key refresh!', 'w3-total-cache' );

			case 'refresh-token-missing-w3tc-pagespeed-key':
				return __( 'No W3TC API key provided for access key refresh!', 'w3-total-cache' );

			case 'refresh-token-not-found':
				return __( 'No matching Google access record found for W3TC API key!', 'w3-total-cache' );

			case 'refresh-token-missing-refresh-token':
				return __( 'Matching Google access record found but the refresh token value is blank!', 'w3-total-cache' );

			case 'refresh-token-google-failed':
				return __( 'Google rejected the access token refresh. Local authorization was kept for retry.', 'w3-total-cache' );

			case 'refresh-token-persist-failed':
				return __( 'The renewed Google access token could not be saved by the W3 API. Local authorization was kept for retry.', 'w3-total-cache' );
		}

		if ( isset( $response_body['error'] ) && is_string( $response_body['error'] ) ) {
			return __( 'Google rejected the access token refresh. Local authorization was kept for retry.', 'w3-total-cache' );
		}

		return sprintf(
			/* translators: %d: HTTP response code. */
			__( 'Unexpected token refresh response (HTTP %d).', 'w3-total-cache' ),
			$response_code
		);
	}

	/**
	 * Removes local PageSpeed credentials after an irrecoverable API response.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	private function clear_pagespeed_credentials() {
		$this->w3tc_config->set( 'widget.pagespeed.access_token', '' );
		$this->w3tc_config->set( 'widget.pagespeed.w3tc_pagespeed_key', '' );
		$this->w3tc_config->save();
		delete_option( 'w3tcps_refresh_retry_after' );
	}

	/**
	 * Get W3TC PageSpeed API max attempts.
	 *
	 * @since 2.3.0
	 *
	 * @return int
	 */
	public function get_max_attempts() {
		return defined( 'W3TC_PAGESPEED_MAX_ATTEMPTS' ) && W3TC_PAGESPEED_MAX_ATTEMPTS ? W3TC_PAGESPEED_MAX_ATTEMPTS : $this->retry_attempts;
	}

	/**
	 * Get Google PageSpeed API URL.
	 *
	 * Overrides via W3TC_PAGESPEED_API_URL must be https on
	 * `googleapis.com` / `*.googleapis.com`; rejected values fall back
	 * to the built-in default.
	 *
	 * @since 2.3.0
	 *
	 * @return string
	 */
	public function get_pagespeed_url() {
		if ( ! \defined( 'W3TC_PAGESPEED_API_URL' ) || ! W3TC_PAGESPEED_API_URL ) {
			return $this->pagespeed_api_base_url;
		}

		$candidate = W3TC_PAGESPEED_API_URL;
		if ( ! Util_Url::is_https_host_allowlisted( $candidate, array( 'googleapis.com' ), array( '.googleapis.com' ) ) ) {
			return $this->pagespeed_api_base_url;
		}

		return $candidate;
	}

	/**
	 * PageSpeed authorize admin notice.
	 *
	 * @since 2.7.6
	 */
	public function errors_notice() {
		if ( current_user_can( 'manage_options' ) ) {
			switch ( true ) {
				case get_option( 'w3tcps_authorize_success' ):
					echo '<div class="updated is-dismissible"><p>' . esc_html( get_option( 'w3tcps_authorize_success' ) ) . '</p></div>';
					delete_option( 'w3tcps_authorize_success' );
					break;

				case get_option( 'w3tcps_authorize_fail' ):
					echo '<div class="error is-dismissible"><p>' . esc_html( get_option( 'w3tcps_authorize_fail' ) ) . '</p><p>' . wp_kses( get_option( 'w3tcps_authorize_fail_message' ), Util_PageSpeed::get_allowed_tags() ) . '</p></div>';
					delete_option( 'w3tcps_authorize_fail' );
					delete_option( 'w3tcps_authorize_fail_message' );
					break;

				case get_option( 'w3tcps_refresh_fail' ):
					echo '<div class="error is-dismissible"><p>' . esc_html( get_option( 'w3tcps_refresh_fail' ) ) . '</p><p>' . wp_kses( get_option( 'w3tcps_refresh_fail_message' ), Util_PageSpeed::get_allowed_tags() ) . '</p></div>';
					delete_option( 'w3tcps_refresh_fail' );
					delete_option( 'w3tcps_refresh_fail_message' );
					break;

				case get_option( 'w3tcps_revoke_fail' ):
					echo '<div class="error is-dismissible"><p>' . esc_html( get_option( 'w3tcps_revoke_fail' ) ) . '</p><p>' . wp_kses( get_option( 'w3tcps_revoke_fail_message' ), Util_PageSpeed::get_allowed_tags() ) . '</p></div>';
					delete_option( 'w3tcps_revoke_fail' );
					delete_option( 'w3tcps_revoke_fail_message' );
					break;
			}
		}
	}

	/**
	 * Returns operator-facing text for an authorization failure.
	 *
	 * @since X.X.X
	 *
	 * @param string $error_json W3 API authorization error JSON.
	 *
	 * @return string
	 */
	public static function get_authorize_failure_message( $error_json ) {
		if ( ! is_string( $error_json ) ) {
			$error_json = '';
		}

		$error    = json_decode( $error_json );
		$error_id = isset( $error->error->id ) ? (string) $error->error->id : '';
		if ( 1 !== preg_match( '/\A[a-z0-9-]+\z/', $error_id ) ) {
			$error_id = '';
		}

		switch ( $error_id ) {
			case 'authorize-in-missing-site-id':
				return __( 'Unique site ID missing for authorize request!', 'w3-total-cache' );

			case 'authorize-in-missing-auth-url':
				return __( 'Authorize URL missing for authorize request!', 'w3-total-cache' );

			case 'authorize-in-missing-return-url':
				return __( 'Return URL missing for authorize request!', 'w3-total-cache' );

			case 'authorize-in-failed':
				return __( 'Failed to process authorize request!', 'w3-total-cache' );

			case 'authorize-out-code-missing':
				return __( 'No authorize code returned to W3-API from Google!', 'w3-total-cache' );

			case 'authorize-out-site-id-missing':
				return __( 'Unique site ID missing during Google authorization return processing!', 'w3-total-cache' );

			case 'authorize-out-w3tc-pagespeed-key-missing':
				return __( 'No W3Key returned to W3-API from Google!', 'w3-total-cache' );

			case 'authorize-out-not-found':
				return __( 'No W3-API matching record found during Google authorization return processing!', 'w3-total-cache' );

			case 'authorize-out-token-missing':
				return __( 'No Google access token found during Google authorization return processing!', 'w3-total-cache' );

			case 'authorize-out-refresh-token-missing':
				return __( 'Google did not return a refresh token. Please authorize Google PageSpeed again.', 'w3-total-cache' );

			case 'authorize-out-refresh-token-persist-failed':
				return __( 'The Google refresh token could not be saved by the W3 API. Please try authorizing again after the service is updated.', 'w3-total-cache' );
		}

		return sprintf(
			/* translators: %s: Google authorization error ID. */
			__( 'Unexpected Google PageSpeed authorization error: %s', 'w3-total-cache' ),
			'' !== $error_id ? $error_id : __( 'unknown', 'w3-total-cache' )
		);
	}

	/**
	 * Clears PageSpeed failure notices and automatic refresh cooldown.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function clear_failure_notices() {
		delete_option( 'w3tcps_refresh_fail' );
		delete_option( 'w3tcps_refresh_fail_message' );
		delete_option( 'w3tcps_refresh_retry_after' );
		delete_option( 'w3tcps_revoke_fail' );
		delete_option( 'w3tcps_revoke_fail_message' );
	}

	/**
	 * Reset authentication.
	 *
	 * @since 2.3.0
	 *
	 * @return bool Whether local and remote authorization were reset.
	 */
	public function reset() {
		$w3tc_access_token  = $this->client->getAccessToken();
		$w3tc_site_id       = Util_Http::generate_site_id();
		$w3tc_pagespeed_key = $this->w3tc_config->get_string( 'widget.pagespeed.w3tc_pagespeed_key' );

		if ( empty( $w3tc_access_token ) || empty( $w3tc_site_id ) || empty( $w3tc_pagespeed_key ) ) {
			update_option(
				'w3tcps_revoke_fail',
				__( 'Google PageSpeed access token revocation missing required parameters!', 'w3-total-cache' )
			);
			return false;
		}

		$request = Util_Environment::get_api_base_url() . '/google/revoke-token/' . rawurlencode( $w3tc_access_token ) . '/' . rawurlencode( $w3tc_site_id ) . '/' . rawurlencode( $w3tc_pagespeed_key );

		$response = wp_remote_get(
			$request,
			array(
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->set_revoke_failure(
				$response->get_error_message()
			);
			return false;
		}

		$response_code = (int) wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $response_body ) ) {
			$response_body = array();
		}

		$w3tc_error_id = '';
		if ( isset( $response_body['error'] ) && is_array( $response_body['error'] ) ) {
			$w3tc_error_id = isset( $response_body['error']['id'] ) ? (string) $response_body['error']['id'] : '';
		}

		if (
			$response_code < 200 ||
			$response_code >= 300 ||
			isset( $response_body['error'] )
		) {
			if ( 'revoke-token-not-found' === $w3tc_error_id ) {
				$this->clear_pagespeed_credentials();
				self::clear_failure_notices();
				return true;
			}

			$this->set_revoke_failure(
				$this->get_revoke_failure_message( $w3tc_error_id, $response_code )
			);
			return false;
		}

		$this->clear_pagespeed_credentials();
		self::clear_failure_notices();
		return true;
	}

	/**
	 * Records a token revocation failure for the admin notice.
	 *
	 * @since X.X.X
	 *
	 * @param string $message Failure detail.
	 *
	 * @return void
	 */
	private function set_revoke_failure( $message ) {
		update_option(
			'w3tcps_revoke_fail',
			__( 'Google PageSpeed Access Token revocation failed.', 'w3-total-cache' )
		);
		update_option( 'w3tcps_revoke_fail_message', $message );
	}

	/**
	 * Returns operator-facing text for a token revocation failure.
	 *
	 * @since X.X.X
	 *
	 * @param string $w3tc_error_id Nested W3 API error ID, if any.
	 * @param int    $response_code HTTP response code.
	 *
	 * @return string
	 */
	private function get_revoke_failure_message( $w3tc_error_id, $response_code ) {
		switch ( $w3tc_error_id ) {
			case 'revoke-token-access-token-missing':
				return __( 'No access token provided for revoke!', 'w3-total-cache' );

			case 'revoke-token-site-id-missing':
				return __( 'No site ID provided for revoke!', 'w3-total-cache' );

			case 'revoke-token-api-key-missing':
				return __( 'No W3TC API key provided for revoke!', 'w3-total-cache' );

			case 'revoke-token-not-found':
				return __( 'No matching Google access record found for W3TC API key!', 'w3-total-cache' );

			case 'revoke-token-google-failed':
				return __( 'Google rejected the access token revocation. Local authorization was kept for retry.', 'w3-total-cache' );

			case 'revoke-token-persist-failed':
				return __( 'Google revoked the access token, but the W3 API could not save the result. Local authorization was kept for retry.', 'w3-total-cache' );
		}

		return sprintf(
			/* translators: 1: HTTP response code, 2: API error ID. */
			__( 'Unexpected token revocation response (HTTP %1$d, error %2$s).', 'w3-total-cache' ),
			$response_code,
			'' !== $w3tc_error_id ? $w3tc_error_id : __( 'unknown', 'w3-total-cache' )
		);
	}
}
