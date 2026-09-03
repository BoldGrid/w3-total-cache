<?php
/**
 * File: class-w3tc-pagespeed-refresh-token-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\PageSpeed_Api;

/**
 * In-memory PageSpeed config used by the refresh tests.
 *
 * @since X.X.X
 */
class W3tc_Pagespeed_Config_Stub {
	/**
	 * Config values.
	 *
	 * @var array
	 */
	private $values;

	/**
	 * Number of config saves.
	 *
	 * @var int
	 */
	private $save_count = 0;

	/**
	 * Initialize known PageSpeed credentials.
	 */
	public function __construct() {
		$this->values = array(
			'widget.pagespeed.access_token'       => wp_json_encode(
				array(
					'access_token' => 'expired-token',
					'expires_in'   => 3600,
					'created'      => 1,
				)
			),
			'widget.pagespeed.w3tc_pagespeed_key' => 'pagespeed-key',
		);
	}

	/**
	 * Return a string config value.
	 *
	 * @param string $key Config key.
	 *
	 * @return string
	 */
	public function get_string( $key ) {
		return isset( $this->values[ $key ] ) ? (string) $this->values[ $key ] : '';
	}

	/**
	 * Set a config value.
	 *
	 * @param string $key   Config key.
	 * @param mixed  $value Config value.
	 *
	 * @return void
	 */
	public function set( $key, $value ) {
		$this->values[ $key ] = $value;
	}

	/**
	 * Match the production config save interface.
	 *
	 * @return void
	 */
	public function save() {
		++$this->save_count;
	}

	/**
	 * Return the number of config saves.
	 *
	 * @return int
	 */
	public function get_save_count() {
		return $this->save_count;
	}
}

/**
 * PageSpeed token refresh coverage.
 *
 * @since X.X.X
 */
class W3tc_Pagespeed_Refresh_Token_Test extends WP_UnitTestCase {
	/**
	 * W3TC config.
	 *
	 * @var W3tc_Pagespeed_Config_Stub
	 */
	private $config;

	/**
	 * HTTP interception callback.
	 *
	 * @var callable|null
	 */
	private $http_filter;

	/**
	 * Number of intercepted HTTP requests.
	 *
	 * @var int
	 */
	private $http_request_count = 0;

	/**
	 * Preserve config changed by each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->config = new W3tc_Pagespeed_Config_Stub();
	}

	/**
	 * Restore config and HTTP filters.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( null !== $this->http_filter ) {
			remove_filter( 'pre_http_request', $this->http_filter, 10 );
		}
		delete_option( 'w3tcps_refresh_fail' );
		delete_option( 'w3tcps_refresh_fail_message' );
		delete_option( 'w3tcps_refresh_retry_after' );
		delete_option( 'w3tcps_revoke_fail' );
		delete_option( 'w3tcps_revoke_fail_message' );

		parent::tearDown();
	}

	/**
	 * A successful response is persisted and receives a creation timestamp.
	 *
	 * @return void
	 */
	public function test_successful_refresh_updates_client_and_config() {
		$this->mock_http_response(
			200,
			array(
				'access_token' => 'renewed-token',
				'expires_in'   => 3600,
				'token_type'   => 'Bearer',
			)
		);

		$api = $this->create_api( true );
		$api->maybe_refresh_token();

		$stored = json_decode( $this->config->get_string( 'widget.pagespeed.access_token' ), true );
		$client = json_decode( $api->client->getAccessToken(), true );

		$this->assertSame( 'renewed-token', $stored['access_token'] );
		$this->assertSame( 'renewed-token', $client['access_token'] );
		$this->assertSame( 3600, $stored['expires_in'] );
		$this->assertIsInt( $stored['created'] );
		$this->assertFalse( $api->client->isAccessTokenExpired() );
	}

	/**
	 * A later successful refresh must not leave a prior failure notice.
	 *
	 * @return void
	 */
	public function test_successful_refresh_clears_prior_failure_notice() {
		update_option( 'w3tcps_refresh_fail', 'Google PageSpeed access token refresh failed.' );
		update_option( 'w3tcps_refresh_fail_message', 'Connection timed out.' );
		update_option( 'w3tcps_refresh_retry_after', time() + 900 );
		update_option( 'w3tcps_revoke_fail', 'Google PageSpeed access token revocation failed.' );
		update_option( 'w3tcps_revoke_fail_message', 'Connection timed out.' );

		$this->mock_http_response(
			200,
			array(
				'access_token' => 'renewed-token',
				'expires_in'   => 3600,
				'token_type'   => 'Bearer',
			)
		);

		$api = $this->create_api();
		$api->refresh_token( 'site-id', 'pagespeed-key' );

		$this->assertFalse( get_option( 'w3tcps_refresh_fail' ) );
		$this->assertFalse( get_option( 'w3tcps_refresh_fail_message' ) );
		$this->assertFalse( get_option( 'w3tcps_refresh_retry_after' ) );
		$this->assertFalse( get_option( 'w3tcps_revoke_fail' ) );
		$this->assertFalse( get_option( 'w3tcps_revoke_fail_message' ) );
	}

	/**
	 * A stale created timestamp in the response must not keep the token expired.
	 *
	 * @return void
	 */
	public function test_successful_refresh_overwrites_stale_created() {
		$this->mock_http_response(
			200,
			array(
				'access_token' => 'renewed-token',
				'expires_in'   => 3600,
				'token_type'   => 'Bearer',
				'created'      => 1,
			)
		);

		$before = time();
		$api    = $this->create_api();
		$api->refresh_token( 'site-id', 'pagespeed-key' );

		$stored = json_decode( $this->config->get_string( 'widget.pagespeed.access_token' ), true );

		$this->assertGreaterThanOrEqual( $before, $stored['created'] );
		$this->assertLessThanOrEqual( time(), $stored['created'] );
		$this->assertFalse( $api->client->isAccessTokenExpired() );
	}

	/**
	 * A flat Google error must not be mistaken for an access token.
	 *
	 * @return void
	 */
	public function test_google_error_on_http_200_keeps_credentials() {
		$this->mock_http_response(
			200,
			array(
				'error'             => 'invalid_grant',
				'error_description' => 'Token has been expired or revoked.',
			)
		);

		$api = $this->create_api();
		$api->refresh_token( 'site-id', 'pagespeed-key' );

		$this->assert_credentials_preserved();
		$this->assertSame(
			'Google PageSpeed access token refresh failed.',
			get_option( 'w3tcps_refresh_fail' )
		);
		$this->assertStringContainsString(
			'kept for retry',
			get_option( 'w3tcps_refresh_fail_message' )
		);
	}

	/**
	 * Transient API failures keep the local state for a later retry.
	 *
	 * @return void
	 */
	public function test_server_error_keeps_credentials() {
		$this->mock_http_response(
			502,
			array(
				'status' => 'error',
				'error'  => array(
					'code' => 502,
					'id'   => 'refresh-token-google-failed',
				),
			)
		);

		$api = $this->create_api();
		$api->refresh_token( 'site-id', 'pagespeed-key' );

		$this->assert_credentials_preserved();
		$this->assertStringContainsString(
			'kept for retry',
			get_option( 'w3tcps_refresh_fail_message' )
		);
	}

	/**
	 * An unknown server error remains retryable and reports its status.
	 *
	 * @return void
	 */
	public function test_unknown_server_error_keeps_credentials() {
		$this->mock_http_response(
			500,
			array(
				'status' => 'error',
				'error'  => array(
					'code' => 500,
					'id'   => 'refresh-token-future-error',
				),
			)
		);

		$api = $this->create_api();
		$api->refresh_token( 'site-id', 'pagespeed-key' );

		$this->assert_credentials_preserved();
		$this->assertStringContainsString(
			'HTTP 500',
			get_option( 'w3tcps_refresh_fail_message' )
		);
	}

	/**
	 * A successful status without expiry data must not write config.
	 *
	 * @return void
	 */
	public function test_missing_expires_in_does_not_save_config() {
		$this->mock_http_response(
			200,
			array(
				'access_token' => 'renewed-token',
			)
		);

		$api = $this->create_api();
		$api->refresh_token( 'site-id', 'pagespeed-key' );

		$this->assertSame( 0, $this->config->get_save_count() );
		$this->assert_credentials_preserved();
	}

	/**
	 * A non-JSON success response must not write config.
	 *
	 * @return void
	 */
	public function test_non_json_success_does_not_save_config() {
		$this->mock_raw_http_response( 200, '<html>Maintenance</html>' );

		$api = $this->create_api();
		$api->refresh_token( 'site-id', 'pagespeed-key' );

		$this->assertSame( 0, $this->config->get_save_count() );
		$this->assert_credentials_preserved();
	}

	/**
	 * Automatic refresh makes no request without an API key.
	 *
	 * @return void
	 */
	public function test_automatic_refresh_without_key_makes_no_request() {
		$this->config->set( 'widget.pagespeed.w3tc_pagespeed_key', '' );
		$this->mock_http_response( 200, array() );

		$api = $this->create_api( true );
		$api->maybe_refresh_token();

		$this->assertSame( 0, $this->http_request_count );
	}

	/**
	 * Automatic refresh backs off after a failure.
	 *
	 * @return void
	 */
	public function test_failed_automatic_refresh_starts_cooldown() {
		$this->mock_http_response(
			502,
			array(
				'status' => 'error',
				'error'  => array(
					'code' => 502,
					'id'   => 'refresh-token-google-failed',
				),
			)
		);

		$api = $this->create_api( true );
		$api->maybe_refresh_token();
		$api->maybe_refresh_token();

		$this->assertSame( 1, $this->http_request_count );
		$this->assertGreaterThan( time(), (int) get_option( 'w3tcps_refresh_retry_after' ) );
		$this->assertTrue( $api->client->isAccessTokenExpired() );
	}

	/**
	 * A missing API record requires fresh authorization.
	 *
	 * @return void
	 */
	public function test_missing_api_record_clears_credentials() {
		$this->mock_http_response(
			428,
			array(
				'status' => 'error',
				'error'  => array(
					'code' => 428,
					'id'   => 'refresh-token-not-found',
				),
			)
		);

		$api = $this->create_api();
		$api->refresh_token( 'site-id', 'pagespeed-key' );

		$this->assertSame( '', $this->config->get_string( 'widget.pagespeed.access_token' ) );
		$this->assertSame( '', $this->config->get_string( 'widget.pagespeed.w3tc_pagespeed_key' ) );
		$this->assertFalse( get_option( 'w3tcps_refresh_retry_after' ) );
	}

	/**
	 * A blank stored refresh token also requires fresh authorization.
	 *
	 * @return void
	 */
	public function test_missing_refresh_token_clears_credentials() {
		$this->mock_http_response(
			428,
			array(
				'status' => 'error',
				'error'  => array(
					'code' => 428,
					'id'   => 'refresh-token-missing-refresh-token',
				),
			)
		);

		$api = $this->create_api();
		$api->refresh_token( 'site-id', 'pagespeed-key' );

		$this->assertSame( '', $this->config->get_string( 'widget.pagespeed.access_token' ) );
		$this->assertSame( '', $this->config->get_string( 'widget.pagespeed.w3tc_pagespeed_key' ) );
		$this->assertFalse( get_option( 'w3tcps_refresh_retry_after' ) );
	}

	/**
	 * A transport failure keeps credentials and records a notice.
	 *
	 * @return void
	 */
	public function test_transport_error_keeps_credentials() {
		$this->http_filter = static function() {
			return new WP_Error( 'http_request_failed', 'Connection timed out.' );
		};
		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );

		$api = $this->create_api();
		$api->refresh_token( 'site-id', 'pagespeed-key' );

		$this->assert_credentials_preserved();
		$this->assertSame( 'Connection timed out.', get_option( 'w3tcps_refresh_fail_message' ) );
	}

	/**
	 * Unknown and malformed authorization errors receive useful fallback text.
	 *
	 * @return void
	 */
	public function test_authorize_failure_message_has_safe_fallback() {
		$unknown = wp_json_encode(
			array(
				'error' => array(
					'id' => 'authorize-out-future-error',
				),
			)
		);

		$this->assertStringContainsString(
			'authorize-out-future-error',
			PageSpeed_Api::get_authorize_failure_message( $unknown )
		);
		$this->assertStringContainsString(
			'unknown',
			PageSpeed_Api::get_authorize_failure_message( 'not-json' )
		);
		$this->assertStringContainsString(
			'unknown',
			PageSpeed_Api::get_authorize_failure_message(
				'{"error":{"id":"<img src=x onerror=alert(1)>evil"}}'
			)
		);
	}

	/**
	 * A Google rejection during authorization is described in plain terms.
	 *
	 * @return void
	 */
	public function test_authorize_google_failure_has_message() {
		$error = wp_json_encode(
			array(
				'error' => array(
					'id' => 'authorize-out-google-failed',
				),
			)
		);

		$message = PageSpeed_Api::get_authorize_failure_message( $error );

		$this->assertStringContainsString( 'Google rejected the authorization request', $message );
		$this->assertStringNotContainsString( 'authorize-out-google-failed', $message );
	}

	/**
	 * A transport failure during revoke keeps local credentials.
	 *
	 * @return void
	 */
	public function test_revoke_transport_error_keeps_credentials() {
		$this->http_filter = static function() {
			return new WP_Error( 'http_request_failed', 'Connection timed out.' );
		};
		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );

		$api = $this->create_api( true );

		$this->assertFalse( $api->reset() );
		$this->assert_credentials_preserved();
		$this->assertSame( 'Connection timed out.', get_option( 'w3tcps_revoke_fail_message' ) );
	}

	/**
	 * A server error during revoke keeps local credentials.
	 *
	 * @return void
	 */
	public function test_revoke_server_error_keeps_credentials() {
		$this->mock_http_response(
			502,
			array(
				'status' => 'error',
				'error'  => array(
					'code' => 502,
					'id'   => 'revoke-token-google-failed',
				),
			)
		);

		$api = $this->create_api( true );

		$this->assertFalse( $api->reset() );
		$this->assert_credentials_preserved();
		$this->assertStringContainsString(
			'kept for retry',
			get_option( 'w3tcps_revoke_fail_message' )
		);
	}

	/**
	 * A missing remote record is terminal and clears local credentials.
	 *
	 * @return void
	 */
	public function test_revoke_not_found_clears_credentials_and_cooldown() {
		update_option( 'w3tcps_refresh_retry_after', time() + 900 );
		$this->mock_http_response(
			428,
			array(
				'status' => 'error',
				'error'  => array(
					'code' => 428,
					'id'   => 'revoke-token-not-found',
				),
			)
		);

		$api = $this->create_api( true );

		$this->assertTrue( $api->reset() );
		$this->assertSame( '', $this->config->get_string( 'widget.pagespeed.access_token' ) );
		$this->assertSame( '', $this->config->get_string( 'widget.pagespeed.w3tc_pagespeed_key' ) );
		$this->assertFalse( get_option( 'w3tcps_refresh_retry_after' ) );
	}

	/**
	 * A successful revoke clears local credentials.
	 *
	 * @return void
	 */
	public function test_successful_revoke_clears_credentials() {
		update_option( 'w3tcps_refresh_fail', 'Old refresh failure.' );
		update_option( 'w3tcps_refresh_fail_message', 'Old refresh failure detail.' );
		update_option( 'w3tcps_refresh_retry_after', time() + 900 );
		update_option( 'w3tcps_revoke_fail', 'Old revoke failure.' );
		update_option( 'w3tcps_revoke_fail_message', 'Old revoke failure detail.' );
		$this->mock_http_response( 200, array( 'status' => 'success' ) );

		$api = $this->create_api( true );

		$this->assertTrue( $api->reset() );
		$this->assertSame( '', $this->config->get_string( 'widget.pagespeed.access_token' ) );
		$this->assertSame( '', $this->config->get_string( 'widget.pagespeed.w3tc_pagespeed_key' ) );
		$this->assertFalse( get_option( 'w3tcps_refresh_fail' ) );
		$this->assertFalse( get_option( 'w3tcps_refresh_fail_message' ) );
		$this->assertFalse( get_option( 'w3tcps_refresh_retry_after' ) );
		$this->assertFalse( get_option( 'w3tcps_revoke_fail' ) );
		$this->assertFalse( get_option( 'w3tcps_revoke_fail_message' ) );
	}

	/**
	 * Create an API instance backed by the in-memory config.
	 *
	 * @param bool $expired Whether to load an expired client token.
	 *
	 * @return PageSpeed_Api
	 */
	private function create_api( $expired = false ) {
		$api      = new PageSpeed_Api();
		$property = new ReflectionProperty( PageSpeed_Api::class, 'w3tc_config' );
		$property->setAccessible( true );
		$property->setValue( $api, $this->config );

		if ( $expired ) {
			$api->client->setAccessToken(
				$this->config->get_string( 'widget.pagespeed.access_token' )
			);
		}

		return $api;
	}

	/**
	 * Intercept the refresh request.
	 *
	 * @param int   $code HTTP response code.
	 * @param array $body JSON response body.
	 *
	 * @return void
	 */
	private function mock_http_response( $code, $body ) {
		$this->mock_raw_http_response( $code, wp_json_encode( $body ) );
	}

	/**
	 * Intercept the refresh request with a raw response body.
	 *
	 * @param int    $code HTTP response code.
	 * @param string $body Raw response body.
	 *
	 * @return void
	 */
	private function mock_raw_http_response( $code, $body ) {
		$this->http_filter = function() use ( $code, $body ) {
			++$this->http_request_count;
			return array(
				'headers'  => array(),
				'body'     => $body,
				'response' => array(
					'code'    => $code,
					'message' => '',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};
		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );
	}

	/**
	 * Assert that a retryable failure did not destroy local OAuth state.
	 *
	 * @return void
	 */
	private function assert_credentials_preserved() {
		$stored = json_decode( $this->config->get_string( 'widget.pagespeed.access_token' ), true );

		$this->assertSame( 'expired-token', $stored['access_token'] );
		$this->assertSame( 'pagespeed-key', $this->config->get_string( 'widget.pagespeed.w3tc_pagespeed_key' ) );
	}
}
