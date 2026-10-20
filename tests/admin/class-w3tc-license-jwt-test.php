<?php
/**
 * File: class-w3tc-license-jwt-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.0
 */

declare( strict_types = 1 );

require_once __DIR__ . '/class-w3tc-license-jwt-harness.php';

use W3TC\Dispatcher;
use W3TC\Extension_ImageService_Api;
use W3TC\Licensing_AdminActions;
use W3TC\Licensing_Core;
use W3TC\Licensing_Plugin_Admin;

/**
 * Minimal config used by possible_state_change().
 *
 * @since 2.11.0
 */
class W3tc_License_Jwt_Config_Stub {
	/**
	 * Values.
	 *
	 * @var array
	 */
	private $values;

	/**
	 * Constructor.
	 *
	 * @param string $license_key License key.
	 */
	public function __construct( string $license_key ) {
		$this->values = array( 'plugin.license_key' => $license_key );
	}

	/**
	 * Get a string.
	 *
	 * @param string $key Key.
	 *
	 * @return string
	 */
	public function get_string( $key ) {
		return isset( $this->values[ $key ] ) ? (string) $this->values[ $key ] : '';
	}

	/**
	 * Set a value.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 *
	 * @return void
	 */
	public function set( $key, $value ) {
		$this->values[ $key ] = $value;
	}

	/**
	 * No-op save.
	 *
	 * @return void
	 */
	public function save() {
	}
}

/**
 * Class: W3tc_License_Jwt_Test
 *
 * Licensing /v2/ routes, license.jwt storage, and checkout iframe.
 *
 * @since 2.11.0
 */
class W3tc_License_Jwt_Test extends W3tc_License_Jwt_Harness {

	/**
	 * Path of the configured legacy license root.
	 *
	 * @return string
	 */
	private function legacy_root_path(): string {
		$path = (string) wp_parse_url( W3TC_LICENSE_API_URL, PHP_URL_PATH );
		return '' === $path ? '/' : $path;
	}

	/**
	 * Path of a URL, defaulting to '/'.
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	private function path_of( string $url ): string {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		return '' === $path ? '/' : $path;
	}

	/**
	 * Responder that answers licensing calls with a token.
	 *
	 * @param string $token Token to return.
	 * @param int    $code  HTTP code.
	 *
	 * @return callable
	 */
	private function licensing_responder( string $token, int $code = 200 ): callable {
		return function ( $url, $args ) use ( $token, $code ) {
			return $this->response( $code, $this->license_body( $token ) );
		};
	}

	/**
	 * Activate and check use /v2/, not the site root.
	 *
	 * @dataProvider provider_v2_actions
	 *
	 * @param string $action Licensing action.
	 *
	 * @return void
	 */
	public function test_v2_actions_request_v2_route( string $action ) {
		$this->install_http( $this->licensing_responder( $this->fresh_token() ) );

		Licensing_Core::$action( self::FAKE_KEY, W3TC_VERSION );

		$calls = $this->requests_of( $action );
		$this->assertCount( 1, $calls, "{$action} must make one licensing request." );
		$this->assertStringContainsString( '/v2/', $calls[0]['url'], "{$action} must call the /v2/ licensing route." );
		$this->assertNotSame( $this->legacy_root_path(), $this->path_of( $calls[0]['url'] ), "{$action} must not request the legacy site root." );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function provider_v2_actions(): array {
		return array(
			'activate_license' => array( 'activate_license' ),
			'check_license'    => array( 'check_license' ),
		);
	}

	/**
	 * Deactivate stays on the legacy root.
	 *
	 * @return void
	 */
	public function test_deactivate_license_stays_on_legacy_root() {
		$this->install_http( $this->licensing_responder( '' ) );

		Licensing_Core::deactivate_license( self::FAKE_KEY );

		$calls = $this->requests_of( 'deactivate_license' );
		$this->assertCount( 1, $calls );
		$this->assertStringNotContainsString( '/v2/', $calls[0]['url'] );
		$this->assertSame( $this->legacy_root_path(), $this->path_of( $calls[0]['url'] ) );
	}

	/**
	 * Reset rooturi stays on the legacy root.
	 *
	 * @return void
	 */
	public function test_reset_rooturi_stays_on_legacy_root() {
		$this->install_http( $this->licensing_responder( '' ) );

		Licensing_Core::reset_rooturi( self::FAKE_KEY, W3TC_VERSION );

		$calls = $this->requests_of( 'reset_rooturi' );
		$this->assertCount( 1, $calls );
		$this->assertStringNotContainsString( '/v2/', $calls[0]['url'] );
		$this->assertSame( $this->legacy_root_path(), $this->path_of( $calls[0]['url'] ) );
	}

	/**
	 * A 2xx token lands on master license.jwt; plugin.license_key is untouched.
	 *
	 * @dataProvider provider_v2_actions
	 *
	 * @param string $action Licensing action.
	 *
	 * @return void
	 */
	public function test_successful_response_stores_token_on_master_state( string $action ) {
		$this->set_config( array( 'plugin.license_key' => self::FAKE_KEY ) );
		$token = $this->fresh_token();
		$this->install_http( $this->licensing_responder( $token ) );

		Licensing_Core::$action( self::FAKE_KEY, W3TC_VERSION );

		$this->assertSame( $token, $this->raw_jwt(), 'license.jwt must hold the returned token in w3tc_state.' );
		$key_after = Dispatcher::config()->get_string( 'plugin.license_key' );
		$this->assertSame( self::FAKE_KEY, $key_after, 'plugin.license_key must not change.' );
		$this->assertNotSame( $token, $key_after, 'The token must not be stored as the license key.' );
	}

	/**
	 * The stored token is never sent to the licensing URL or body.
	 *
	 * @dataProvider provider_v2_actions
	 *
	 * @param string $action Licensing action.
	 *
	 * @return void
	 */
	public function test_stored_token_is_not_sent_to_licensing( string $action ) {
		$stored = $this->fresh_token();
		$this->seed_master_state( array( 'license.jwt' => $stored ) );
		$this->install_http( $this->licensing_responder( $this->fresh_token() ) );

		Licensing_Core::$action( self::FAKE_KEY, W3TC_VERSION );

		$calls = $this->requests_of( $action );
		$this->assertCount( 1, $calls );
		$this->assertStringContainsString( '/v2/', $calls[0]['url'] );
		$this->assertStringNotContainsString( $stored, $calls[0]['url'] );
		$this->assertStringNotContainsString( $stored, $calls[0]['body'] );
	}

	/**
	 * Non-2xx or malformed tokens never store license.jwt.
	 *
	 * @dataProvider provider_rejected_tokens
	 *
	 * @param int    $code  HTTP code.
	 * @param string $token Token returned.
	 *
	 * @return void
	 */
	public function test_rejected_response_does_not_store_token( int $code, string $token ) {
		$this->install_http( $this->licensing_responder( $token, $code ) );

		Licensing_Core::check_license( self::FAKE_KEY, W3TC_VERSION );

		$this->assertSame( '', $this->raw_jwt(), 'license.jwt must stay empty.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function provider_rejected_tokens(): array {
		$b64 = function ( $v ) {
			return rtrim( strtr( base64_encode( $v ), '+/', '-_' ), '=' ); // phpcs:ignore
		};
		$good_payload = $b64( '{"exp":' . ( time() + 3600 ) . '}' );
		$header       = $b64( '{"alg":"HS256","typ":"JWT"}' );

		return array(
			'non-2xx with valid token' => array( 500, $header . '.' . $good_payload . '.c2ln' ),
			'401 with valid token'     => array( 401, $header . '.' . $good_payload . '.c2ln' ),
			'two segments'             => array( 200, $header . '.' . $good_payload ),
			'four segments'            => array( 200, $header . '.' . $good_payload . '.c2ln.eHg' ),
			'middle is JSON string'    => array( 200, $header . '.' . $b64( '"not-an-object"' ) . '.c2ln' ),
			'middle is JSON number'    => array( 200, $header . '.' . $b64( '42' ) . '.c2ln' ),
			'middle is not JSON'       => array( 200, $header . '.' . $b64( 'not json' ) . '.c2ln' ),
		);
	}

	/**
	 * Replacing the key clears license.jwt before activation; failed
	 * activation leaves it empty.
	 *
	 * @return void
	 */
	public function test_replacing_key_clears_token_before_activation() {
		$this->seed_master_state( array( 'license.jwt' => $this->fresh_token() ) );
		$jwt_during_activation = null;
		$this->install_http(
			function ( $url, $args ) use ( &$jwt_during_activation ) {
				$kind = $this->kind( $url, $this->body_string( $args ) );
				if ( 'activate_license' === $kind ) {
					$jwt_during_activation = $this->raw_jwt();
					return $this->response(
						500,
						array(
							'license'        => 'invalid',
							'license_status' => 'invalid',
						)
					);
				}
				return $this->response( 200, array( 'license_status' => 'inactive' ) );
			}
		);

		$admin = new Licensing_Plugin_Admin();
		$admin->possible_state_change(
			new W3tc_License_Jwt_Config_Stub( 'fakeNewLicenseKey000000000000BB' ),
			new W3tc_License_Jwt_Config_Stub( self::FAKE_KEY )
		);

		$this->assertNotNull( $jwt_during_activation, 'Activation for the new key must run.' );
		$this->assertSame( '', $jwt_during_activation, 'license.jwt must be cleared before the new activation request.' );
		$this->assertSame( '', $this->raw_jwt(), 'Failed activation must leave license.jwt empty.' );
	}

	/**
	 * A key change clears license.jwt while another caller holds the refresh lock.
	 *
	 * Failed activation leaves the token empty, so the next image call checks
	 * the new key instead of refreshing the previous bearer.
	 *
	 * @return void
	 */
	public function test_key_change_clears_token_while_refresh_lock_is_held() {
		$new_key = 'fakeNewLicenseKey000000000000BB';
		$this->seed_master_state( array( 'license.jwt' => $this->fresh_token() ) );
		update_site_option(
			self::LOCK_OPTION,
			array(
				'owner'   => 'another-process',
				'expires' => time() + 60,
			)
		);
		$this->set_config(
			array(
				'plugin.license_key' => $new_key,
				'plugin.type'        => 'pro',
			)
		);
		$this->install_http(
			function ( $url, $args ) {
				$kind = $this->kind( $url, $this->body_string( $args ) );
				if ( 'activate_license' === $kind ) {
					return $this->response(
						500,
						array(
							'license'        => 'invalid',
							'license_status' => 'invalid',
						)
					);
				}
				if ( 'check_license' === $kind ) {
					return $this->response( 200, $this->license_body( $this->fresh_token() ) );
				}
				return $this->response( 200, array( 'license_status' => 'inactive' ) );
			}
		);

		$admin = new Licensing_Plugin_Admin();
		$admin->possible_state_change(
			new W3tc_License_Jwt_Config_Stub( $new_key ),
			new W3tc_License_Jwt_Config_Stub( self::FAKE_KEY )
		);

		$this->assertSame( '', $this->raw_jwt(), 'A key change must clear license.jwt while the refresh lock is held.' );
		$lock = get_site_option( self::LOCK_OPTION, false );
		$this->assertIsArray( $lock );
		$this->assertSame( 'another-process', $lock['owner'] ?? null, 'Clearing the token must not take over another caller\'s lock.' );

		( new Extension_ImageService_Api() )->get_usage( true );

		$checks = $this->requests_of( 'check_license' );
		$this->assertCount( 1, $checks, 'The next image call must check the new key.' );
		$this->assertStringContainsString( $new_key, $checks[0]['url'] );
		$this->assertStringNotContainsString( self::FAKE_KEY, $checks[0]['url'] );
		$this->assertSame( array(), $this->requests_of( 'refresh' ), 'An empty token must not refresh the previous bearer.' );
	}

	/**
	 * Removing the key clears license.jwt.
	 *
	 * @return void
	 */
	public function test_removing_key_clears_token() {
		$this->seed_master_state( array( 'license.jwt' => $this->fresh_token() ) );
		$this->install_http(
			function () {
				return $this->response( 200, array( 'license_status' => 'inactive' ) );
			}
		);

		$admin = new Licensing_Plugin_Admin();
		$admin->possible_state_change(
			new W3tc_License_Jwt_Config_Stub( '' ),
			new W3tc_License_Jwt_Config_Stub( self::FAKE_KEY )
		);

		$this->assertSame( '', $this->raw_jwt(), 'Removing the license key must clear license.jwt.' );
	}

	/**
	 * Changing the license key clears the image service transients.
	 *
	 * @return void
	 */
	public function test_changing_key_clears_imageservice_transients() {
		set_transient( 'w3tc_imageservice_usage', array( 'usage_hourly' => 1 ), DAY_IN_SECONDS );
		set_transient( 'w3tc_imageservice_limited', array( 'status' => 'error' ), DAY_IN_SECONDS );
		$this->install_http(
			function () {
				return $this->response(
					500,
					array(
						'license'        => 'invalid',
						'license_status' => 'invalid',
					)
				);
			}
		);

		$admin = new Licensing_Plugin_Admin();
		$admin->possible_state_change(
			new W3tc_License_Jwt_Config_Stub( 'fakeNewLicenseKey000000000000BB' ),
			new W3tc_License_Jwt_Config_Stub( self::FAKE_KEY )
		);

		$this->assertFalse( get_transient( 'w3tc_imageservice_usage' ), 'Key change must clear w3tc_imageservice_usage.' );
		$this->assertFalse( get_transient( 'w3tc_imageservice_limited' ), 'Key change must clear w3tc_imageservice_limited.' );
	}

	/**
	 * Storing a new license.jwt clears the image service transients.
	 *
	 * @return void
	 */
	public function test_new_token_clears_imageservice_transients() {
		$this->seed_master_state( array( 'license.jwt' => $this->fresh_token() ) );
		set_transient( 'w3tc_imageservice_usage', array( 'usage_hourly' => 1 ), DAY_IN_SECONDS );
		set_transient( 'w3tc_imageservice_limited', array( 'status' => 'error' ), DAY_IN_SECONDS );
		$new = $this->fresh_token();
		$this->install_http( $this->licensing_responder( $new ) );

		Licensing_Core::check_license( self::FAKE_KEY, W3TC_VERSION );

		$this->assertSame( $new, $this->raw_jwt() );
		$this->assertFalse( get_transient( 'w3tc_imageservice_usage' ), 'New license.jwt must clear w3tc_imageservice_usage.' );
		$this->assertFalse( get_transient( 'w3tc_imageservice_limited' ), 'New license.jwt must clear w3tc_imageservice_limited.' );
	}

	/**
	 * Scheduled check_license does not refresh first, even with an expired token.
	 *
	 * @return void
	 */
	public function test_scheduled_check_does_not_refresh_expired_token() {
		$this->set_config(
			array(
				'plugin.license_key' => self::FAKE_KEY,
				'plugin.type'        => 'pro',
			)
		);
		$this->set_blog_state(
			array(
				'license.status'     => 'active.by_rooturi',
				'license.next_check' => 0,
			)
		);
		$past = time() - 3600;
		$this->seed_master_state(
			array(
				'license.jwt' => $this->make_token(
					array(
						'expires_at' => gmdate( 'c', $past ),
						'exp'        => $past,
					)
				),
			)
		);
		$this->install_http( $this->licensing_responder( $this->fresh_token() ) );

		$admin  = Dispatcher::component( 'Licensing_Plugin_Admin' );
		$method = new \ReflectionMethod( $admin, 'maybe_update_license_status' );
		$method->setAccessible( true );
		$method->invoke( $admin );

		$this->assertSame( array(), $this->requests_of( 'refresh' ), 'Status check must not call /v2/refresh.' );
		$checks = $this->requests_of( 'check_license' );
		$this->assertCount( 1, $checks );
		$this->assertSame( 'check_license', $this->kinds()[0], 'check_license must be the first request.' );
		$this->assertStringContainsString( '/v2/', $checks[0]['url'] );
	}

	/**
	 * Activation and check hold the refresh lock only around the token write.
	 *
	 * @dataProvider provider_v2_actions
	 *
	 * @param string $action Licensing action.
	 *
	 * @return void
	 */
	public function test_licensing_request_locks_only_around_token_write( string $action ) {
		$lock_during_http = 'unset';
		$token            = $this->fresh_token();
		$this->install_http(
			function ( $url, $args ) use ( &$lock_during_http, $token ) {
				$lock_during_http = get_site_option( self::LOCK_OPTION, false );
				return $this->response( 200, $this->license_body( $token ) );
			}
		);

		Licensing_Core::$action( self::FAKE_KEY, W3TC_VERSION );

		$this->assertFalse( $lock_during_http, "{$action} must not hold " . self::LOCK_OPTION . ' during the HTTP call.' );
		$this->assertFalse( get_site_option( self::LOCK_OPTION, false ), 'The refresh lock must be released after the write.' );
		$this->assertSame( $token, $this->raw_jwt() );
	}

	/**
	 * Upgrade lightbox iframe uses Licensing_Core::purchase_url().
	 *
	 * @return void
	 */
	public function test_upgrade_lightbox_iframe_uses_purchase_url() {
		$_GET     = array(
			'data_src'  => 'test_src',
			'renew_key' => 'renew123',
			'client_id' => 'client456',
		);
		$_REQUEST = $_GET;

		ob_start();
		( new Licensing_AdminActions() )->w3tc_licensing_upgrade();
		$html = (string) ob_get_clean();

		$this->assertSame( 1, preg_match( '/<iframe[^>]*\ssrc="([^"]*)"/i', $html, $m ), 'Upgrade lightbox must render an iframe.' );
		$src = html_entity_decode( $m[1], ENT_QUOTES );

		$this->assertStringNotContainsString( 'checkout-ad', $src, 'Iframe must not use the hardcoded checkout-ad URL.' );
		$this->assertSame( Licensing_Core::purchase_url( 'test_src', 'renew123', 'client456' ), $src );
	}
}
