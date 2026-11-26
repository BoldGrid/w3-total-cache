<?php
/**
 * File: class-w3tc-pagespeed-oauth-state-test.php
 *
 * PageSpeed OAuth state is per-user and single-use. The General Settings
 * callback stores `widget.pagespeed.access_token` and
 * `widget.pagespeed.w3tc_pagespeed_key` only when that state verifies, and
 * Deauthorize requires the `w3tc_pagespeed_deauthorize` nonce.
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.1
 */

declare( strict_types = 1 );

use W3TC\PageSpeed_Api;
use W3TC\PageSpeed_OAuthState;

/**
 * In-memory PageSpeed config for the OAuth callback tests.
 *
 * @since 2.11.1
 */
class W3tc_Pagespeed_OAuth_Config_Stub {
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
	 * Seed PageSpeed credential keys.
	 *
	 * @param array $values Initial config values.
	 */
	public function __construct( array $values ) {
		$this->values = $values;
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
 * PageSpeed OAuth state and authorize-callback coverage.
 *
 * @since 2.11.1
 */
class W3tc_PageSpeed_OAuthState_Test extends WP_UnitTestCase {
	/**
	 * Credential stored before a callback runs.
	 *
	 * @var string
	 */
	const ORIGINAL_TOKEN = '{"access_token":"original-token","expires_in":3600,"created":1}';

	/**
	 * PageSpeed key stored before a callback runs.
	 *
	 * @var string
	 */
	const ORIGINAL_KEY = 'original-key';

	/**
	 * Request superglobals captured before each test.
	 *
	 * @var array
	 */
	private $saved_request;

	/**
	 * Users created by the test, so their state transients can be removed.
	 *
	 * @var int[]
	 */
	private $user_ids = array();

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
	 * Snapshot request globals and block outbound HTTP.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->saved_request = array(
			'GET'     => $_GET,
			'POST'    => $_POST,
			'REQUEST' => $_REQUEST,
		);
		$this->user_ids      = array();

		$this->http_request_count = 0;
		$this->http_filter        = function () {
			++$this->http_request_count;
			return new WP_Error( 'http_request_failed', 'Unexpected outbound request.' );
		};
		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );

		$this->delete_pagespeed_options();
		wp_set_current_user( 0 );
	}

	/**
	 * Restore request globals, users, and options.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		if ( null !== $this->http_filter ) {
			remove_filter( 'pre_http_request', $this->http_filter, 10 );
		}

		$_GET     = $this->saved_request['GET'];
		$_POST    = $this->saved_request['POST'];
		$_REQUEST = $this->saved_request['REQUEST'];

		foreach ( $this->user_ids as $user_id ) {
			delete_transient( PageSpeed_OAuthState::TRANSIENT_PREFIX . $user_id );
		}

		$this->delete_pagespeed_options();
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * The callback query parameter is `w3tc_pagespeed_state`.
	 *
	 * @return void
	 */
	public function test_state_param_is_w3tc_pagespeed_state() {
		$this->assertSame( 'w3tc_pagespeed_state', PageSpeed_OAuthState::STATE_PARAM );
	}

	/**
	 * State is minted per user, reused until consumed, checked only for that
	 * user, and dies on consume.
	 *
	 * @return void
	 */
	public function test_issue_verify_consume_is_per_user_and_single_use() {
		$this->assertSame( '', PageSpeed_OAuthState::issue() );
		$this->assertFalse( PageSpeed_OAuthState::verify( 'anything' ) );
		PageSpeed_OAuthState::consume();

		$user_a = $this->make_user();
		$user_b = $this->make_user();

		wp_set_current_user( $user_a );
		$token_a = PageSpeed_OAuthState::issue();
		$this->assertNotSame( '', $token_a );
		$this->assertTrue( PageSpeed_OAuthState::verify( $token_a ) );

		$this->assertSame( $token_a, PageSpeed_OAuthState::issue() );
		$this->assertTrue( PageSpeed_OAuthState::verify( $token_a ) );

		wp_set_current_user( $user_b );
		$this->assertFalse( PageSpeed_OAuthState::verify( $token_a ) );
		$token_b = PageSpeed_OAuthState::issue();
		$this->assertTrue( PageSpeed_OAuthState::verify( $token_b ) );
		$this->assertFalse( PageSpeed_OAuthState::verify( $token_a ) );

		wp_set_current_user( $user_a );
		$this->assertTrue( PageSpeed_OAuthState::verify( $token_a ) );
		$this->assertFalse( PageSpeed_OAuthState::verify( $token_b ) );

		PageSpeed_OAuthState::consume();
		$this->assertFalse( PageSpeed_OAuthState::verify( $token_a ) );

		$reissued = PageSpeed_OAuthState::issue();
		$this->assertNotSame( $token_a, $reissued );
		$this->assertFalse( PageSpeed_OAuthState::verify( $token_a ) );
		$this->assertTrue( PageSpeed_OAuthState::verify( $reissued ) );

		wp_set_current_user( $user_b );
		$this->assertTrue( PageSpeed_OAuthState::verify( $token_b ) );
		PageSpeed_OAuthState::consume();
		$this->assertFalse( PageSpeed_OAuthState::verify( $token_b ) );
	}

	/**
	 * Verification rejects mismatches of any length and compares with hash_equals.
	 *
	 * @return void
	 */
	public function test_verify_uses_constant_time_compare() {
		wp_set_current_user( $this->make_user() );
		$token = PageSpeed_OAuthState::issue();

		$same_length    = $token;
		$same_length[0] = ( 'a' === $same_length[0] ) ? 'b' : 'a';

		$this->assertFalse( PageSpeed_OAuthState::verify( $same_length ) );
		$this->assertFalse( PageSpeed_OAuthState::verify( 'short' ) );
		$this->assertFalse( PageSpeed_OAuthState::verify( $token . 'extra' ) );
		$this->assertFalse( PageSpeed_OAuthState::verify( array( $token ) ) );
		$this->assertFalse( PageSpeed_OAuthState::verify( null ) );
		$this->assertTrue( PageSpeed_OAuthState::verify( $token ) );

		$method = new ReflectionMethod( PageSpeed_OAuthState::class, 'verify' );
		$lines  = file( $method->getFileName() );
		$this->assertIsArray( $lines );

		$body = implode(
			'',
			array_slice(
				$lines,
				$method->getStartLine() - 1,
				$method->getEndLine() - $method->getStartLine() + 1
			)
		);
		$this->assertStringContainsString( 'hash_equals', $body );
	}

	/**
	 * No authorize, error, or deauthorize parameter leaves the handler idle.
	 *
	 * @return void
	 */
	public function test_handle_authorize_request_returns_none_without_oauth_params() {
		$this->set_request( array() );
		$config = $this->new_config();

		$this->assertSame( 'none', PageSpeed_Api::handle_authorize_request( $config ) );
		$this->assert_credentials_unchanged( $config );
		$this->assertSame( 0, $this->http_request_count );
	}

	/**
	 * An authorization error is reported and does not replace stored credentials.
	 *
	 * @return void
	 */
	public function test_authorize_error_returns_error_and_leaves_credentials() {
		wp_set_current_user( $this->make_user() );
		$state = PageSpeed_OAuthState::issue();
		$this->set_request(
			array(
				'w3tc_authorize_error' => wp_json_encode(
					array(
						'error' => array(
							'id' => 'authorize-out-token-missing',
						),
					)
				),
				'w3tc_access_token'    => $this->token_json( 'should-not-store' ),
				'w3tc_pagespeed_key'   => 'should-not-store',
				'w3tc_pagespeed_state' => $state,
			)
		);
		$config = $this->new_config();

		$this->assertSame( 'error', PageSpeed_Api::handle_authorize_request( $config ) );
		$this->assert_credentials_unchanged( $config );
		$this->assertTrue( PageSpeed_OAuthState::verify( $state ) );
	}

	/**
	 * Missing or wrong state is rejected and does not consume a real state.
	 *
	 * @return void
	 */
	public function test_missing_or_wrong_state_is_rejected_and_leaves_credentials() {
		wp_set_current_user( $this->make_user() );
		$state = PageSpeed_OAuthState::issue();

		$rejected_states = array(
			null,
			'',
			'not-the-issued-state',
			array( $state ),
		);

		foreach ( $rejected_states as $rejected_state ) {
			$config = $this->new_config();
			$this->set_authorize_request( $this->token_json( 'unverified-token' ), 'unverified-key', $rejected_state );

			$this->assertSame( 'rejected', PageSpeed_Api::handle_authorize_request( $config ) );
			$this->assert_credentials_unchanged( $config );
		}

		$this->assertTrue( PageSpeed_OAuthState::verify( $state ) );
		$this->assertSame( 0, $this->http_request_count );
	}

	/**
	 * A state issued to someone else is rejected and left intact for that user.
	 *
	 * @return void
	 */
	public function test_state_issued_to_another_user_is_rejected_and_leaves_credentials() {
		$user_a = $this->make_user();
		$user_b = $this->make_user();

		wp_set_current_user( $user_a );
		$state = PageSpeed_OAuthState::issue();

		wp_set_current_user( $user_b );
		$config = $this->new_config();
		$this->set_authorize_request( $this->token_json( 'unverified-token' ), 'unverified-key', $state );

		$this->assertSame( 'rejected', PageSpeed_Api::handle_authorize_request( $config ) );
		$this->assert_credentials_unchanged( $config );

		wp_set_current_user( $user_a );
		$this->assertTrue( PageSpeed_OAuthState::verify( $state ) );
	}

	/**
	 * A verified state stores the token and key, then a replay is rejected.
	 *
	 * @return void
	 */
	public function test_verified_state_stores_token_and_key_then_replay_is_rejected() {
		wp_set_current_user( $this->make_user() );
		$state  = PageSpeed_OAuthState::issue();
		$config = $this->new_config();

		$this->set_authorize_request( $this->token_json( 'stored-token' ), 'stored-key', $state );
		$this->assertSame( 'stored', PageSpeed_Api::handle_authorize_request( $config ) );
		$this->assertSame( 1, $config->get_save_count() );
		$this->assert_stored_token( $config, 'stored-token' );
		$this->assertSame( 'stored-key', $config->get_string( 'widget.pagespeed.w3tc_pagespeed_key' ) );
		$this->assertFalse( PageSpeed_OAuthState::verify( $state ) );

		$this->set_authorize_request( $this->token_json( 'replay-token' ), 'replay-key', $state );
		$this->assertSame( 'rejected', PageSpeed_Api::handle_authorize_request( $config ) );
		$this->assertSame( 1, $config->get_save_count() );
		$this->assert_stored_token( $config, 'stored-token' );
		$this->assertSame( 'stored-key', $config->get_string( 'widget.pagespeed.w3tc_pagespeed_key' ) );
		$this->assertSame( 0, $this->http_request_count );
	}

	/**
	 * Rendering the Authorize link again keeps a pending authorization valid.
	 *
	 * @return void
	 */
	public function test_rerender_before_return_still_stores_credentials() {
		wp_set_current_user( $this->make_user() );
		$state = PageSpeed_OAuthState::issue();
		PageSpeed_OAuthState::issue();
		PageSpeed_OAuthState::issue();

		$config = $this->new_config();
		$this->set_authorize_request( $this->token_json( 'rerender-token' ), 'rerender-key', $state );

		$this->assertSame( 'stored', PageSpeed_Api::handle_authorize_request( $config ) );
		$this->assert_stored_token( $config, 'rerender-token' );
		$this->assertSame( 'rerender-key', $config->get_string( 'widget.pagespeed.w3tc_pagespeed_key' ) );
		$this->assertFalse( PageSpeed_OAuthState::verify( $state ) );
	}

	/**
	 * A verified state with an unusable token does not replace credentials.
	 *
	 * @return void
	 */
	public function test_unusable_token_returns_invalid_token_and_leaves_credentials() {
		wp_set_current_user( $this->make_user() );
		$state  = PageSpeed_OAuthState::issue();
		$config = $this->new_config();

		$this->set_authorize_request( 'not-json', 'new-key', $state );

		$this->assertSame( 'invalid-token', PageSpeed_Api::handle_authorize_request( $config ) );
		$this->assert_credentials_unchanged( $config );
		$this->assertFalse( PageSpeed_OAuthState::verify( $state ) );
	}

	/**
	 * Deauthorize without the `w3tc_pagespeed_deauthorize` nonce is rejected
	 * and leaves a revoke-failure notice.
	 *
	 * @return void
	 */
	public function test_deauthorize_without_nonce_is_rejected() {
		$this->assertSame( 'w3tc_pagespeed_deauthorize', PageSpeed_OAuthState::DEAUTHORIZE_NONCE_ACTION );

		wp_set_current_user( $this->make_user() );

		$nonces = array(
			null,
			'',
			wp_create_nonce( 'w3tc' ),
			wp_create_nonce( 'w3tc_pagespeed_authorize' ),
			array( wp_create_nonce( 'w3tc_pagespeed_deauthorize' ) ),
		);

		foreach ( $nonces as $nonce ) {
			$config = $this->new_config();
			$params = array(
				'w3tc_deauthorize' => '1',
			);
			if ( null !== $nonce ) {
				$params['_wpnonce'] = $nonce;
			}
			$this->set_request( $params );

			delete_option( 'w3tcps_revoke_fail' );
			delete_option( 'w3tcps_revoke_fail_message' );

			$this->assertSame( 'deauthorize-rejected', PageSpeed_Api::handle_authorize_request( $config ) );
			$this->assert_credentials_unchanged( $config );
			$this->assertNotEmpty( get_option( 'w3tcps_revoke_fail' ) );
			$this->assertNotEmpty( get_option( 'w3tcps_revoke_fail_message' ) );
			$this->assertFalse( get_option( 'w3tcps_authorize_success' ) );
		}

		$this->assertSame( 0, $this->http_request_count );
	}

	/**
	 * Deauthorize with the per-action nonce returns deauthorized.
	 *
	 * @return void
	 */
	public function test_deauthorize_with_nonce_returns_deauthorized() {
		wp_set_current_user( $this->make_user() );
		$config = $this->new_config(
			wp_json_encode(
				array(
					'access_token' => 'original-token',
					'expires_in'   => 3600,
					'created'      => time(),
				)
			),
			null
		);

		remove_filter( 'pre_http_request', $this->http_filter, 10 );
		add_filter(
			'pre_http_request',
			function () {
				++$this->http_request_count;
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode( array( 'status' => 'success' ) ),
				);
			},
			10,
			3
		);

		$this->set_request(
			array(
				'w3tc_deauthorize' => '1',
				'_wpnonce'         => wp_create_nonce( 'w3tc_pagespeed_deauthorize' ),
			)
		);

		$this->assertSame( 'deauthorized', PageSpeed_Api::handle_authorize_request( $config ) );
		$this->assertSame( 1, $this->http_request_count );
		$this->assertSame( '', $config->get_string( 'widget.pagespeed.access_token' ) );
		$this->assertSame( '', $config->get_string( 'widget.pagespeed.w3tc_pagespeed_key' ) );
	}

	/**
	 * Create an administrator and remember the ID for transient cleanup.
	 *
	 * @return int
	 */
	private function make_user() {
		$user_id          = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		$this->user_ids[] = $user_id;

		return $user_id;
	}

	/**
	 * Config holding the original PageSpeed credentials unless overridden.
	 *
	 * @param string|null $token Stored access token. Null keeps the original.
	 * @param string|null $key   Stored PageSpeed key. Null keeps the original.
	 *
	 * @return W3tc_Pagespeed_OAuth_Config_Stub
	 */
	private function new_config( $token = null, $key = null ) {
		return new W3tc_Pagespeed_OAuth_Config_Stub(
			array(
				'widget.pagespeed.access_token'       => null === $token ? self::ORIGINAL_TOKEN : $token,
				'widget.pagespeed.w3tc_pagespeed_key' => null === $key ? self::ORIGINAL_KEY : $key,
			)
		);
	}

	/**
	 * JSON access-token string accepted by prepare_access_token_json().
	 *
	 * @param string $access_token Google access token.
	 *
	 * @return string
	 */
	private function token_json( $access_token ) {
		return (string) wp_json_encode(
			array(
				'access_token' => $access_token,
				'expires_in'   => 3600,
			)
		);
	}

	/**
	 * Replace request superglobals. Util_Request reads GET/POST; nonces read REQUEST.
	 *
	 * @param array $params Request parameters.
	 *
	 * @return void
	 */
	private function set_request( array $params ) {
		$_GET     = $params;
		$_POST    = array();
		$_REQUEST = $params;
	}

	/**
	 * Set the token, key, and optional state query parameters.
	 *
	 * A null state omits the parameter. An array state is passed through so
	 * the scalar guard is covered.
	 *
	 * @param string       $token_json Access token JSON.
	 * @param string       $key        PageSpeed key.
	 * @param mixed|null   $state      State token, or null when absent.
	 *
	 * @return void
	 */
	private function set_authorize_request( $token_json, $key, $state = null ) {
		$params = array(
			'w3tc_access_token'  => $token_json,
			'w3tc_pagespeed_key' => $key,
		);
		if ( null !== $state ) {
			$params['w3tc_pagespeed_state'] = $state;
		}
		$this->set_request( $params );
	}

	/**
	 * Assert the callback did not write either PageSpeed credential.
	 *
	 * @param W3tc_Pagespeed_OAuth_Config_Stub $config Config stub.
	 *
	 * @return void
	 */
	private function assert_credentials_unchanged( W3tc_Pagespeed_OAuth_Config_Stub $config ) {
		$this->assertSame( self::ORIGINAL_TOKEN, $config->get_string( 'widget.pagespeed.access_token' ) );
		$this->assertSame( self::ORIGINAL_KEY, $config->get_string( 'widget.pagespeed.w3tc_pagespeed_key' ) );
		$this->assertSame( 0, $config->get_save_count() );
	}

	/**
	 * Assert the stored access token JSON carries the expected token string.
	 *
	 * @param W3tc_Pagespeed_OAuth_Config_Stub $config       Config stub.
	 * @param string                           $access_token Expected access token.
	 *
	 * @return void
	 */
	private function assert_stored_token( W3tc_Pagespeed_OAuth_Config_Stub $config, $access_token ) {
		$decoded = json_decode( $config->get_string( 'widget.pagespeed.access_token' ), true );

		$this->assertIsArray( $decoded );
		$this->assertSame( $access_token, $decoded['access_token'] );
		$this->assertGreaterThanOrEqual( time() - 5, $decoded['created'] );
	}

	/**
	 * Remove options written by the authorize and deauthorize paths.
	 *
	 * @return void
	 */
	private function delete_pagespeed_options() {
		delete_option( 'w3tcps_authorize_fail' );
		delete_option( 'w3tcps_authorize_fail_message' );
		delete_option( 'w3tcps_authorize_success' );
		delete_option( 'w3tcps_revoke_fail' );
		delete_option( 'w3tcps_revoke_fail_message' );
		delete_option( 'w3tcps_refresh_fail' );
		delete_option( 'w3tcps_refresh_fail_message' );
		delete_option( 'w3tcps_refresh_retry_after' );
	}
}
