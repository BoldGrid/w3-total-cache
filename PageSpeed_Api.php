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
		if ( $this->client->isAccessTokenExpired() && ! empty( $w3tc_pagespeed_key ) ) {
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
			update_option(
				'w3tcps_refresh_fail',
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

		$response_body_json = wp_remote_retrieve_body( $response );
		$response_body      = json_decode( $response_body_json, true );

		if ( is_wp_error( $response ) ) {
			return wp_json_encode(
				array(
					'error' => array(
						'code'    => $response->get_error_code(),
						'message' => $response->get_error_message(),
					),
				)
			);
		} elseif ( isset( $response_body['error']['code'] ) && 200 !== $response_body['error']['code'] ) {
			if ( 'refresh-token-missing-site-id' === $response_body['error']['id'] ) {
				$w3tc_message = __( 'No site ID provided for access key refresh!', 'w3-total-cache' );
			} elseif ( 'refresh-token-missing-w3tc-pagespeed-key' === $response_body['error']['id'] ) {
				$w3tc_message = __( 'No W3TC API key provided for access key refresh!', 'w3-total-cache' );
			} elseif ( 'refresh-token-not-found' === $response_body['error']['id'] ) {
				$w3tc_message = __( 'No matching Google access record found for W3TC API key!', 'w3-total-cache' );
			} elseif ( 'refresh-token-missing-refresh-token' === $response_body['error']['id'] ) {
				$w3tc_message = __( 'Matching Google access record found but the refresh token value is blank!', 'w3-total-cache' );
			}

			update_option(
				'w3tcps_refresh_fail',
				__( 'Google PageSpeed access token refresh failed.', 'w3-total-cache' )
			);
			update_option(
				'w3tcps_refresh_fail_message',
				$w3tc_message
			);

			// Reset the token and key.
			$this->w3tc_config->set( 'widget.pagespeed.access_token', '' );
			$this->w3tc_config->set( 'widget.pagespeed.w3tc_pagespeed_key', '' );
			$this->w3tc_config->save();

			return;
		}

		$w3tc_access_token = $response_body_json;

		if ( empty( $w3tc_access_token ) || empty( $response_body['access_token'] ) ) {
			update_option(
				'w3tcps_refresh_fail',
				__( 'Google PageSpeed access token refresh failed due to response missing access token.', 'w3-total-cache' )
			);
			return;
		}

		$this->w3tc_config->set( 'widget.pagespeed.access_token', $w3tc_access_token );
		$this->w3tc_config->save();
		$this->client->setAccessToken( $w3tc_access_token );
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
	 * @since 2.3.0
	 *
	 * @return string
	 */
	public function get_pagespeed_url() {
		return defined( 'W3TC_PAGESPEED_API_URL' ) && W3TC_PAGESPEED_API_URL ? W3TC_PAGESPEED_API_URL : $this->pagespeed_api_base_url;
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
	 * Reset authentication.
	 *
	 * @since 2.3.0
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
			return;
		}

		$request = Util_Environment::get_api_base_url() . '/google/revoke-token/' . rawurlencode( $w3tc_access_token ) . '/' . rawurlencode( $w3tc_site_id ) . '/' . rawurlencode( $w3tc_pagespeed_key );

		$response = wp_remote_get(
			$request,
			array(
				'timeout' => 60,
			)
		);

		$response_body_json = wp_remote_retrieve_body( $response );
		$response_body      = json_decode( $response_body_json, true );

		if ( is_wp_error( $response ) ) {
			return wp_json_encode(
				array(
					'error' => array(
						'code'    => $response->get_error_code(),
						'message' => $response->get_error_message(),
					),
				)
			);
		} elseif ( isset( $response_body['error']['code'] ) && 200 !== $response_body['error']['code'] ) {
			if ( 'revoke-token-access-token-missing' === $response_body['error']['id'] ) {
				$w3tc_message = __( 'No access token provided for revoke!', 'w3-total-cache' );
			} elseif ( 'revoke-token-api-key-missing' === $response_body['error']['id'] ) {
				$w3tc_message = __( 'No W3TC API key provided for revoke!', 'w3-total-cache' );
			} elseif ( 'revoke-token-not-found' === $response_body['error']['id'] ) {
				$w3tc_message = __( 'No matching Google access record found for W3TC API key!', 'w3-total-cache' );
			}

			update_option(
				'w3tcps_revoke_fail',
				__( 'Google PageSpeed Access Token revocation failed.', 'w3-total-cache' )
			);
			update_option(
				'w3tcps_revoke_fail_message',
				$w3tc_message
			);

			return;
		}

		$this->w3tc_config->set( 'widget.pagespeed.access_token', '' );
		$this->w3tc_config->set( 'widget.pagespeed.w3tc_pagespeed_key', '' );
		$this->w3tc_config->save();
	}
}
