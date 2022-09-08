<?php
/**
 * File: PageSpeed_Api.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * PageSpeed API.
 */
class PageSpeed_Api {
	/**
	 * Config
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
	 * PageSpeed API constructor.
	 *
	 * @param string $access_token_json API access token JSON.
	 */
	public function __construct( $access_token_json = null ) {
		$this->config = Dispatcher::config();
		$this->client = new \W3TCG_Google_Client();
		$this->client->setApplicationName( 'W3TC PageSpeed Analyzer' );
		$this->client->setAuthConfig( W3TC_GOOGLE_CLIENT_JSON );
		$this->client->setRedirectUri( W3TC_PAGESPEED_RETURN_URL );
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
	 * Fully analyze URL via PageSpeed API.
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
				W3TC_PAGESPEED_API_URL,
				array( 'url' => $url )
			),
		);
	}

	/**
	 * Analyze URL via PageSpeed API using strategy.
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
			PageSpeed_Instructions::get_pagespeed_instructions(),
		);
	}

	/**
	 * Make API request.
	 *
	 * @param string $query API request query.
	 *
	 * @return string | false
	 */
	public function process_request( $query ) {
		if ( empty( $this->client->getAccessToken() ) ) {
			return array(
				'error' => array(
					'code'    => 403,
					'message' => __( 'Missing Google access token.', 'w3-total-cache' ),
				),
			);
		}

		$access_token = json_decode( $this->client->getAccessToken() );

		$request = Util_Environment::url_format(
			W3TC_PAGESPEED_API_URL,
			array_merge(
				$query,
				array(
					'quotaUser'    => Util_Http::generate_site_id(),
					'access_token' => $access_token->access_token,
				)
			)
		);

		// Attempt the request up to x times with an increasing delay between each attempt. Uses W3TC_PAGESPEED_MAX_ATTEMPTS.
		$attempts = 0;

		while ( ++$attempts <= W3TC_PAGESPEED_MAX_ATTEMPTS ) {
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
				if ( $attempts >= W3TC_PAGESPEED_MAX_ATTEMPTS ) {
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
		};

		if ( isset( $response['response']['code'] ) && 200 !== $response['response']['code'] ) {
			// Google PageSpeed Insights sometimes will return a 500 and message body with details so we still grab the body response.
			$decoded_body = json_decode( wp_remote_retrieve_body( $response ), true );
			return array(
				'error' => array(
					'code'    => $response['response']['code'],
					'message' => ( ! empty( $decoded_body['error']['message'] ) ? $decoded_body['error']['message'] : $response['response']['message'] ),
				),
			);
		} elseif ( is_wp_error( $response ) ) {
			return array(
				'error' => array(
					'message' => $response->get_error_message(),
				),
			);
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * Checks if the Google access token is expired and attempts to refresh.
	 *
	 * @return void
	 */
	public function maybe_refresh_token() {
		if ( $this->client->isAccessTokenExpired() && ! empty( $this->config->get_string( 'widget.pagespeed.w3key' ) ) ) {
			$this->refresh_token();
		}
	}

	/**
	 * Refreshes the Google access token if a valid refresh token is defined.
	 *
	 * @return string | null
	 */
	public function refresh_token() {
		$initial_refresh_token = $this->client->getRefreshToken();
		if ( empty( $initial_refresh_token ) ) {
			$initial_refresh_token_json = $this->get_refresh_token( Util_Http::generate_site_id(), $this->config->get_string( 'widget.pagespeed.w3key' ) );
			$initial_refresh_token      = json_decode( $initial_refresh_token_json );
			if ( ! empty( $initial_refresh_token->error ) ) {
				return wp_json_encode(
					array(
						'error' => sprintf(
							// translators: 1 Refresh URL value, 2 Request response code, 3 Error message.
							__(
								'API request error<br/><br/>
									Refresh URL: %1$s<br/><br/>
									Response Code: %2$s<br/>
									Response Message: %3$s<br/>',
								'w3-total-cache'
							),
							W3TC_API_GPS_GET_TOKEN_URL . '/' . Util_Http::generate_site_id() . '/' . $this->config->get_string( 'widget.pagespeed.w3key' ),
							! empty( $initial_refresh_token->error->code ) ? $initial_refresh_token->error->code : 'N/A',
							! empty( $initial_refresh_token->error->message ) ? $initial_refresh_token->error->message : 'N/A'
						),
					)
				);
			}
		}

		try {
			$this->client->refreshToken( $initial_refresh_token->refresh_token );
		} catch ( \Exception $e ) {
			return wp_json_encode(
				array(
					'error' => array(
						'code'    => 500,
						'message' => $e->getMessage(),
					),
				)
			);
		}

		$new_access_token = json_decode( $this->client->getAccessToken() );

		if ( ! empty( $new_access_token->refresh_token ) && $new_access_token->refresh_token !== $initial_refresh_token->refresh_token ) {
			$new_refresh_token = $new_access_token->refresh_token;
			unset( $new_access_token->refresh_token );

			$request = Util_Environment::url_format(
				W3TC_API_GPS_UPDATE_TOKEN_URL,
				array(
					'site_id'       => Util_Http::generate_site_id(),
					'w3key'         => $this->config->get_string( 'widget.pagespeed.w3key' ),
					'refresh_token' => $new_refresh_token,
				)
			);

			try {
				$response = wp_remote_get(
					$request,
					array(
						'timeout' => 60,
					)
				);
			} catch ( \Exception $e ) {
				return wp_json_encode(
					array(
						'error' => array(
							'code'    => 500,
							'message' => $e->getMessage(),
						),
					)
				);
			}

			if ( is_wp_error( $response ) ) {
				return wp_json_encode(
					array(
						'error' => array(
							'message' => $response->get_error_message(),
						),
					)
				);
			} elseif ( isset( $response['response']['code'] ) && 200 !== $response['response']['code'] ) {
				return wp_json_encode(
					array(
						'error' => array(
							'code'    => $response['response']['code'],
							'message' => $response['response']['message'],
						),
					)
				);
			}
		}

		$this->config->set( 'widget.pagespeed.access_token', wp_json_encode( $new_access_token ) );
		$this->config->save();

		return wp_json_encode( array( 'access_key' => $new_access_token ) );
	}

	/**
	 * Creates new Google access token from authorize request response.
	 *
	 * @param string $gacode New Google access authentication code.
	 * @param string $w3key  W3 API access key.
	 *
	 * @return string | null
	 */
	public function new_token( $gacode, $w3key ) {
		if ( empty( $gacode ) ) {
			return wp_json_encode(
				array(
					'error' => array(
						'code'    => 409,
						'message' => __( 'Missing/invalid Google access authentication code.', 'w3-total-cache' ),
					),
				)
			);
		} elseif ( empty( $w3key ) ) {
			return wp_json_encode(
				array(
					'error' => array(
						'code'    => 409,
						'message' => __( 'Missing/invalid W3 API key.', 'w3-total-cache' ),
					),
				)
			);
		}

		try {
			$this->client->authenticate( $gacode );
		} catch ( \Exception $e ) {
			return wp_json_encode(
				array(
					'error' => array(
						'code'    => 500,
						'message' => $e->getMessage(),
					),
				)
			);
		}

		$access_token_json = $this->client->getAccessToken();

		if ( empty( $access_token_json ) ) {
			return wp_json_encode(
				array(
					'error' => array(
						'code'    => 409,
						'message' => __( 'Missing/invalid Google access token JSON setting after authentication.', 'w3-total-cache' ),
					),
				)
			);
		}

		$access_token = ( ! empty( $access_token_json ) ? json_decode( $access_token_json ) : '' );

		$request = Util_Environment::url_format(
			W3TC_API_GPS_UPDATE_TOKEN_URL,
			array(
				'site_id'       => Util_Http::generate_site_id(),
				'w3key'         => $w3key,
				'refresh_token' => $access_token->refresh_token,
			)
		);

		try {
			$response = wp_remote_get(
				$request,
				array(
					'timeout' => 60,
				)
			);
		} catch ( \Exception $e ) {
			return wp_json_encode(
				array(
					'error' => array(
						'code'    => 500,
						'message' => $e->getMessage(),
					),
				)
			);
		}

		if ( is_wp_error( $response ) ) {
			return wp_json_encode(
				array(
					'error' => array(
						'message' => $response->get_error_message(),
					),
				)
			);
		} elseif ( isset( $response['response']['code'] ) && 200 !== $response['response']['code'] ) {
			return wp_json_encode(
				array(
					'error' => array(
						'code'    => $response['response']['code'],
						'message' => $response['response']['message'],
					),
				)
			);
		}

		unset( $access_token->refresh_token );

		$this->config->set( 'widget.pagespeed.access_token', wp_json_encode( $access_token ) );
		$this->config->set( 'widget.pagespeed.w3key', $w3key );
		$this->config->save();

		return null;
	}

	/**
	 * Fetches Google refresh token from W3 API server.
	 *
	 * @param string $site_id W3 API access key.
	 * @param string $w3key   W3 API access key.
	 *
	 * @return string | null
	 */
	public function get_refresh_token( $site_id, $w3key ) {
		if ( empty( $site_id ) ) {
			return wp_json_encode(
				array(
					'error' => array(
						'code'    => 409,
						'message' => __( 'Missing/invalid Site ID.', 'w3-total-cache' ),
					),
				)
			);
		} elseif ( empty( $w3key ) ) {
			return wp_json_encode(
				array(
					'error' => array(
						'code'    => 409,
						'message' => __( 'Missing/invalid W3 API key.', 'w3-total-cache' ),
					),
				)
			);
		}

		$request = W3TC_API_GPS_GET_TOKEN_URL . '/' . $site_id . '/' . $w3key;
		try {
			$response = wp_remote_get(
				$request,
				array(
					'timeout' => 60,
				)
			);
		} catch ( \Exception $e ) {
			return wp_json_encode(
				array(
					'error' => array(
						'code'    => 500,
						'message' => $e->getMessage(),
					),
				)
			);
		}

		if ( is_wp_error( $response ) ) {
			return wp_json_encode(
				array(
					'error' => array(
						'message' => $response->get_error_message(),
					),
				)
			);
		} elseif ( isset( $response['response']['code'] ) && 200 !== $response['response']['code'] ) {
			return wp_json_encode(
				array(
					'error' => array(
						'code'    => $response['response']['code'],
						'message' => $response['response']['message'],
					),
				)
			);
		}

		return wp_remote_retrieve_body( $response );
	}
}
