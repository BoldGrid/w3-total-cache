<?php
/**
 * File: class-w3tc-gdrive-oauth-tokens-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.1
 */

declare( strict_types = 1 );

use W3TC\CdnEngine_GoogleDrive;
use W3TC\Cdn_GoogleDrive_OAuthState;
use W3TC\Cdn_GoogleDrive_OAuthTokens;
use W3TC\Cdn_GoogleDrive_Page;

/**
 * Google Drive OAuth return stores tokens server-side.
 *
 * @since 2.11.1
 */
class W3tc_Gdrive_Oauth_Tokens_Test extends WP_UnitTestCase {
	/**
	 * Remove request values and stored tokens after each test.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		Cdn_GoogleDrive_OAuthTokens::consume();
		Cdn_GoogleDrive_OAuthState::consume();
		parent::tearDown();
	}

	/**
	 * A valid return stores the tokens and redirects without them.
	 *
	 * @since 2.11.1
	 */
	public function test_oauth_return_redirect_strips_tokens() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $user_id );
		$state = Cdn_GoogleDrive_OAuthState::issue();

		$_GET = $_REQUEST = array(
			'page'                                  => 'w3tc_cdn',
			Cdn_GoogleDrive_OAuthState::STATE_PARAM => $state,
			'oa_client_id'                          => 'client-id',
			'oa_access_token'                       => 'access-token-value',
			'oa_refresh_token'                      => 'refresh-token-value',
			'oa_token_type'                         => 'Bearer',
			'oa_expires_in'                         => '3600',
			'oa_created'                            => '1',
		);

		$url = Cdn_GoogleDrive_Page::oauth_return_redirect_url();

		$this->assertStringNotContainsString( 'oa_access_token', $url );
		$this->assertStringNotContainsString( 'oa_refresh_token', $url );
		$this->assertStringNotContainsString( 'access-token-value', $url );
		$this->assertStringContainsString( Cdn_GoogleDrive_OAuthTokens::HANDLE_PARAM . '=', $url );

		$handle = $this->query_value( $url, Cdn_GoogleDrive_OAuthTokens::HANDLE_PARAM );
		$tokens = Cdn_GoogleDrive_OAuthTokens::read( $handle );

		$this->assertSame( 'access-token-value', $tokens['oa_access_token'] );
		$this->assertSame( 'refresh-token-value', $tokens['oa_refresh_token'] );
	}

	/**
	 * A request without the handle does not restore stored tokens.
	 *
	 * @since 2.11.1
	 */
	public function test_read_without_handle_does_not_restore_tokens() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $user_id );
		$handle = Cdn_GoogleDrive_OAuthTokens::store(
			array(
				'oa_client_id'     => 'client-id',
				'oa_access_token'  => 'access-token-value',
				'oa_refresh_token' => 'refresh-token-value',
			)
		);

		$this->assertNotSame( '', $handle );
		$this->assertSame( array(), Cdn_GoogleDrive_OAuthTokens::read( '' ) );
		$this->assertSame( array(), Cdn_GoogleDrive_OAuthTokens::read( 'wrong-handle' ) );
		$this->assertSame( '', Cdn_GoogleDrive_Page::auth_return_popup_url() );
	}

	/**
	 * Another user cannot read the stored tokens with the same handle.
	 *
	 * @since 2.11.1
	 */
	public function test_other_user_cannot_read_handle() {
		$owner_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $owner_id );
		$handle = Cdn_GoogleDrive_OAuthTokens::store(
			array(
				'oa_client_id'     => 'client-id',
				'oa_access_token'  => 'access-token-value',
				'oa_refresh_token' => 'refresh-token-value',
			)
		);

		$other_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $other_id );

		$this->assertSame( array(), Cdn_GoogleDrive_OAuthTokens::read( $handle ) );
	}

	/**
	 * The auth-set form accepts only the posted handle.
	 *
	 * @since 2.11.1
	 */
	public function test_posted_handle_loads_tokens_once() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $user_id );
		$handle = Cdn_GoogleDrive_OAuthTokens::store(
			array(
				'oa_client_id'     => 'client-id',
				'oa_access_token'  => 'access-token-value',
				'oa_refresh_token' => 'refresh-token-value',
			)
		);

		$_GET[ Cdn_GoogleDrive_OAuthTokens::HANDLE_PARAM ] = $handle;
		$this->assertSame( '', Cdn_GoogleDrive_OAuthTokens::posted_handle() );

		$_POST[ Cdn_GoogleDrive_OAuthTokens::HANDLE_PARAM ] = $handle;
		$tokens = Cdn_GoogleDrive_OAuthTokens::posted_tokens();
		$this->assertSame( 'access-token-value', $tokens['oa_access_token'] );

		Cdn_GoogleDrive_OAuthTokens::consume();
		$this->assertSame( array(), Cdn_GoogleDrive_OAuthTokens::posted_tokens() );
	}

	/**
	 * Incomplete stored tokens must not open the folder-picker popup URL.
	 *
	 * @since 2.11.1
	 */
	public function test_auth_return_popup_url_requires_complete_tokens() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $user_id );
		$handle = 'incomplete-handle';
		$state  = Cdn_GoogleDrive_OAuthState::issue();

		\set_transient(
			Cdn_GoogleDrive_OAuthTokens::TRANSIENT_PREFIX . $user_id,
			array(
				'handle' => $handle,
				'tokens' => array(
					'oa_client_id'     => '',
					'oa_access_token'  => 'access-only',
					'oa_refresh_token' => '',
				),
			),
			Cdn_GoogleDrive_OAuthTokens::TRANSIENT_TTL
		);

		$_GET = array(
			Cdn_GoogleDrive_OAuthTokens::HANDLE_PARAM => $handle,
			Cdn_GoogleDrive_OAuthState::STATE_PARAM   => $state,
		);

		$this->assertSame( '', Cdn_GoogleDrive_Page::auth_return_popup_url() );
	}

	/**
	 * The popup URL from a stored return is not redirected on admin_init.
	 *
	 * @since 2.11.1
	 */
	public function test_popup_url_reaches_auth_return_handler() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $user_id );
		$state = Cdn_GoogleDrive_OAuthState::issue();

		$_GET = $_REQUEST = array(
			'page'                                  => 'w3tc_cdn',
			Cdn_GoogleDrive_OAuthState::STATE_PARAM => $state,
			'oa_client_id'                          => 'client-id',
			'oa_access_token'                       => 'access-token-value',
			'oa_refresh_token'                      => 'refresh-token-value',
		);
		$handle = $this->query_value( Cdn_GoogleDrive_Page::oauth_return_redirect_url(), Cdn_GoogleDrive_OAuthTokens::HANDLE_PARAM );

		$_GET = $_REQUEST = array(
			'page'                                    => 'w3tc_cdn',
			Cdn_GoogleDrive_OAuthState::STATE_PARAM   => $state,
			Cdn_GoogleDrive_OAuthTokens::HANDLE_PARAM => $handle,
		);
		$popup_url = Cdn_GoogleDrive_Page::auth_return_popup_url();

		$this->assertStringContainsString( 'w3tc_cdn_google_drive_auth_return', $popup_url );
		$this->assertStringNotContainsString( 'oa_', $popup_url );

		$_GET     = array();
		$_REQUEST = array();
		\parse_str( (string) \wp_parse_url( \html_entity_decode( $popup_url ), PHP_URL_QUERY ), $_GET );
		$_REQUEST = $_GET;

		$this->assertSame( '', Cdn_GoogleDrive_Page::oauth_return_redirect_url() );
	}

	/**
	 * A backslash is escaped before a single quote in the Drive query literal.
	 *
	 * @since 2.11.1
	 */
	public function test_drive_query_escapes_backslash_before_quote() {
		$this->assertSame( "a\\\\b\\'c", CdnEngine_GoogleDrive::escape_query_literal( "a\\b'c" ) );
	}

	/**
	 * Read one query parameter from a URL.
	 *
	 * @since 2.11.1
	 *
	 * @param string $url Admin URL.
	 * @param string $key Query key.
	 *
	 * @return string
	 */
	private function query_value( $url, $key ) {
		$query = \wp_parse_url( $url, PHP_URL_QUERY );
		$args  = array();
		\parse_str( (string) $query, $args );

		return isset( $args[ $key ] ) ? (string) $args[ $key ] : '';
	}
}
