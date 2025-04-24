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
 * @since 2.3.0
 */
class PageSpeed_Api {
	/**
	 * Config.
	 *
	 * @var object
	 */
	private $config;

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
	 * @since 2.3.0
	 *
	 * @param string $access_token_json API access token JSON.
	 */
	public function __construct( $access_token_json = null ) {
		$this->config = Dispatcher::config();
		$this->client = new \W3TCG_Google_Client();
		$this->client->setApplicationName( 'W3TC PageSpeed Analyzer' );
		$this->client->addScope( 'openid' );
		$this->client->setAccessType( 'offline' );
		$this->client->setApprovalPrompt( 'force' );
		$this->client->setDefer( true );

		if ( ! empty( $access_token_json ) ) {
			$this->client->setAccessToken( $access_token_json );
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
	 * @since 2.3.0
	 *
	 * @param string $url URL to analyze via PageSpeed API.
	 *
	 * @return array
	 */
	public function analyze( $url ) {
		$mobile  = $this->analyze_strategy( $url, 'mobile' );
		$desktop = $this->analyze_strategy( $url, 'desktop' );
		return array(
			'mobile'   => $mobile,
			'desktop'  => $desktop,
			'test_url' => Util_Environment::url_format(
				$this->get_pagespeed_url(),
				array( 'url' => $url )
			),
		);
	}

	/**
	 * Analyze URL via PageSpeed API using strategy.
	 *
	 * @since 2.3.0
	 *
	 * @param string $url URL to analyze.
	 * @param string $strategy Strategy to use desktop/mobile.
	 *
	 * @return array
	 */
	public function analyze_strategy( $url, $strategy ) {
		$data = $this->process_request(
			array(
				'url'      => $url,
				'category' => 'performance',
				'strategy' => $strategy,
			)
		);

		if ( ! empty( Util_PageSpeed::get_value_recursive( $data, array( 'error', 'code' ) ) ) ) {
			return array(
				'error' => array(
					'code'    => Util_PageSpeed::get_value_recursive( $data, array( 'error', 'code' ) ),
					'message' => Util_PageSpeed::get_value_recursive( $data, array( 'error', 'message' ) ),
				),
			);
		}

		return array_merge_recursive(
			PageSpeed_Data::prepare_pagespeed_data( $data ),
			PageSpeed_Instructions::get_pagespeed_instructions()
		);
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
		$access_token_json = $this->client->getAccessToken();

		if ( empty( $access_token_json ) ) {
			return array(
				'error' => array(
					'code'    => 403,
					'message' => __( 'Missing Google access token.', 'w3-total-cache' ),
				),
			);
		}

		$access_token = json_decode( $access_token_json );

		$request = Util_Environment::url_format(
			$this->get_pagespeed_url(),
			array_merge(
				$query,
				array(
					'quotaUser'    => Util_Http::generate_site_id(),
					'access_token' => $access_token->access_token,
				)
			)
		);

		// Attempt the request up to x times with an increasing delay between each attempt. Uses W3TC_PAGESPEED_MAX_ATTEMPTS constant if defined.
		$attempts = 0;

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
		$site_id            = Util_Http::generate_site_id();
		$w3tc_pagespeed_key = $this->config->get_string( 'widget.pagespeed.w3tc_pagespeed_key' );
		if ( $this->client->isAccessTokenExpired() && ! empty( $w3tc_pagespeed_key ) ) {
			$this->refresh_token( $site_id, $w3tc_pagespeed_key );
		}
	}

	/**
	 * Refreshes the Google access token if a valid refresh token is defined.
	 *
	 * @param string $site_id            Site ID.
	 * @param string $w3tc_pagespeed_key W3 API access key.
	 *
	 * @return string
	 */
	public function refresh_token( $site_id, $w3tc_pagespeed_key ) {
		if ( empty( $site_id ) || empty( $w3tc_pagespeed_key ) ) {
			update_option(
				'w3tcps_refresh_fail',
				__( 'Google PageSpeed access token refresh missing required parameters!', 'w3-total-cache' )
			);
			return;
		}

		$request = Util_Environment::get_api_base_url() . '/google/refresh-token/' . rawurlencode( $site_id ) . '/' . rawurlencode( $w3tc_pagespeed_key );

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
				$message = __( 'No site ID provided for access key refresh!', 'w3-total-cache' );
			} elseif ( 'refresh-token-missing-w3tc-pagespeed-key' === $response_body['error']['id'] ) {
				$message = __( 'No W3TC API key provided for access key refresh!', 'w3-total-cache' );
			} elseif ( 'refresh-token-not-found' === $response_body['error']['id'] ) {
				$message = __( 'No matching Google access record found for W3TC API key!', 'w3-total-cache' );
			} elseif ( 'refresh-token-missing-refresh-token' === $response_body['error']['id'] ) {
				$message = __( 'Matching Google access record found but the refresh token value is blank!', 'w3-total-cache' );
			}

			update_option(
				'w3tcps_refresh_fail',
				__( 'Google PageSpeed access token refresh failed.', 'w3-total-cache' )
			);
			update_option(
				'w3tcps_refresh_fail_message',
				$message
			);

			// Reset the token and key.
			$this->config->set( 'widget.pagespeed.access_token', '' );
			$this->config->set( 'widget.pagespeed.w3tc_pagespeed_key', '' );
			$this->config->save();

			return;
		}

		$access_token = $response_body_json;

		if ( empty( $access_token ) || empty( $response_body['access_token'] ) ) {
			update_option(
				'w3tcps_refresh_fail',
				__( 'Google PageSpeed access token refresh failed due to response missing access token.', 'w3-total-cache' )
			);
			return;
		}

		$this->config->set( 'widget.pagespeed.access_token', $access_token );
		$this->config->save();
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
	 * @since 2.3.0
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
		$access_token       = $this->client->getAccessToken();
		$site_id            = Util_Http::generate_site_id();
		$w3tc_pagespeed_key = $this->config->get_string( 'widget.pagespeed.w3tc_pagespeed_key' );

		if ( empty( $access_token ) || empty( $site_id ) || empty( $w3tc_pagespeed_key ) ) {
			update_option(
				'w3tcps_revoke_fail',
				__( 'Google PageSpeed access token revocation missing required parameters!', 'w3-total-cache' )
			);
			return;
		}

		$request = Util_Environment::get_api_base_url() . '/google/revoke-token/' . rawurlencode( $access_token ) . '/' . rawurlencode( $site_id ) . '/' . rawurlencode( $w3tc_pagespeed_key );

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
				$message = __( 'No access token provided for revoke!', 'w3-total-cache' );
			} elseif ( 'revoke-token-api-key-missing' === $response_body['error']['id'] ) {
				$message = __( 'No W3TC API key provided for revoke!', 'w3-total-cache' );
			} elseif ( 'revoke-token-not-found' === $response_body['error']['id'] ) {
				$message = __( 'No matching Google access record found for W3TC API key!', 'w3-total-cache' );
			}

			update_option(
				'w3tcps_revoke_fail',
				__( 'Google PageSpeed Access Token revocation failed.', 'w3-total-cache' )
			);
			update_option(
				'w3tcps_revoke_fail_message',
				$message
			);

			return;
		}

		$this->config->set( 'widget.pagespeed.access_token', '' );
		$this->config->set( 'widget.pagespeed.w3tc_pagespeed_key', '' );
		$this->config->save();
	}
}
