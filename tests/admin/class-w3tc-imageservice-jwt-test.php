<?php
/**
 * File: class-w3tc-imageservice-jwt-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.8
 */

declare( strict_types = 1 );

require_once __DIR__ . '/class-w3tc-license-jwt-harness.php';

use W3TC\Extension_ImageService_Api;

/**
 * Class: W3tc_Imageservice_Jwt_Test
 *
 * Image service /v2/ routes, Bearer auth, refresh, and refresh lock.
 *
 * @since 2.10.8
 */
class W3tc_Imageservice_Jwt_Test extends W3tc_License_Jwt_Harness {

	/**
	 * Token returned by check_license.
	 *
	 * @var string
	 */
	private $check_token = '';

	/**
	 * Refresh HTTP code.
	 *
	 * @var int
	 */
	private $refresh_code = 200;

	/**
	 * Token returned by /v2/refresh.
	 *
	 * @var string
	 */
	private $refresh_token = '';

	/**
	 * Hook run while /v2/refresh is in flight.
	 *
	 * @var callable|null
	 */
	private $on_refresh = null;

	/**
	 * Queued codes for image calls, per kind.
	 *
	 * @var array
	 */
	private $image_codes = array();

	/**
	 * Set up a Pro site with a routed HTTP mock.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->set_config(
			array(
				'plugin.type'        => 'pro',
				'plugin.license_key' => self::FAKE_KEY,
			)
		);
		$this->check_token   = $this->fresh_token();
		$this->refresh_token = $this->fresh_token();
		$this->refresh_code  = 200;
		$this->on_refresh    = null;
		$this->image_codes   = array(
			'convert' => array(),
			'usage'   => array(),
		);
		$this->install_http( array( $this, 'route' ) );
	}

	/**
	 * HTTP router.
	 *
	 * @param string $url  URL.
	 * @param array  $args Args.
	 *
	 * @return array
	 */
	public function route( $url, $args ) {
		$kind = $this->kind( $url, $this->body_string( $args ) );
		switch ( $kind ) {
			case 'activate_license':
			case 'check_license':
				return $this->response( 200, $this->license_body( $this->check_token ) );
			case 'refresh':
				if ( $this->on_refresh ) {
					call_user_func( $this->on_refresh );
				}
				return $this->response( $this->refresh_code, $this->license_body( $this->refresh_token ) );
			case 'convert':
				$code = array_shift( $this->image_codes['convert'] );
				return $this->response(
					null === $code ? 200 : $code,
					array(
						'job_id'    => 1,
						'signature' => 'sig',
					)
				);
			case 'usage':
				$code = array_shift( $this->image_codes['usage'] );
				return $this->response(
					null === $code ? 200 : $code,
					array(
						'usage_hourly'  => 1,
						'usage_monthly' => 2,
						'limit_hourly'  => 100,
						'limit_monthly' => 1000,
					)
				);
			default:
				return $this->response( 200, array() );
		}
	}

	/**
	 * Fixture image path.
	 *
	 * @return string
	 */
	private function image_path(): string {
		return DIR_TESTDATA . '/images/canola.jpg';
	}

	/**
	 * Run an image call.
	 *
	 * @param string $call convert|usage.
	 *
	 * @return void
	 */
	private function run_image_call( string $call ): void {
		$api = new Extension_ImageService_Api();
		if ( 'convert' === $call ) {
			$api->convert( $this->image_path() );
		} else {
			$api->get_usage( true );
		}
	}

	/**
	 * Token expiring soon.
	 *
	 * @param string $claim expires_at|exp.
	 *
	 * @return string
	 */
	private function near_expiry_token( string $claim ): string {
		$soon = time() + 60;
		return $this->make_token(
			'exp' === $claim ? array( 'exp' => $soon ) : array( 'expires_at' => gmdate( 'c', $soon ) )
		);
	}

	/**
	 * Data provider: image calls.
	 *
	 * @return array
	 */
	public function provider_image_calls(): array {
		return array(
			'convert' => array( 'convert' ),
			'usage'   => array( 'usage' ),
		);
	}

	/**
	 * Data provider: expiry claims.
	 *
	 * @return array
	 */
	public function provider_expiry_claims(): array {
		return array(
			'ISO-8601 expires_at' => array( 'expires_at' ),
			'numeric exp'         => array( 'exp' ),
		);
	}

	/**
	 * Missing token: one /v2/ check_license before the image call.
	 *
	 * @dataProvider provider_image_calls
	 *
	 * @param string $call Image call.
	 *
	 * @return void
	 */
	public function test_missing_token_runs_one_check_license_first( string $call ) {
		$this->run_image_call( $call );

		$checks = $this->requests_of( 'check_license' );
		$this->assertCount( 1, $checks, 'A missing token must trigger one check_license.' );
		$this->assertStringContainsString( '/v2/', $checks[0]['url'] );
		$kinds = $this->kinds();
		$this->assertContains( $call, $kinds );
		$this->assertLessThan(
			array_search( $call, $kinds, true ),
			array_search( 'check_license', $kinds, true ),
			'check_license must run before the image request.'
		);
	}

	/**
	 * A near-expiry token is refreshed before the image call.
	 *
	 * @dataProvider provider_expiry_claims
	 *
	 * @param string $claim Expiry claim.
	 *
	 * @return void
	 */
	public function test_near_expiry_token_is_refreshed( string $claim ) {
		$old = $this->near_expiry_token( $claim );
		$this->seed_master_state( array( 'license.jwt' => $old ) );

		$this->run_image_call( 'usage' );

		$refresh = $this->requests_of( 'refresh' );
		$this->assertCount( 1, $refresh, 'A token within 120 seconds of expiry must be refreshed.' );
		$r = $refresh[0];
		$this->assertSame( 'POST', $r['method'] );
		$this->assertSame( 'Bearer ' . $old, $this->header( $r['args'], 'Authorization' ) );
		$this->assertStringContainsString( 'application/json', (string) $this->header( $r['args'], 'Content-Type' ) );
		$this->assertArrayHasKey( 'redirection', $r['args'] );
		$this->assertSame( 0, (int) $r['args']['redirection'] );
		$json = json_decode( $r['body'], true );
		$this->assertIsArray( $json, 'Refresh body must be JSON.' );
		$this->assertSame( network_home_url(), $json['home_url'] ?? null );
		$this->assertStringNotContainsString( self::FAKE_KEY, $r['body'], 'The license key must not be in the refresh body.' );
		$this->assertSame( $this->refresh_token, $this->raw_jwt(), 'A 2xx refresh must replace license.jwt.' );

		$kinds = $this->kinds();
		$this->assertLessThan( array_search( 'usage', $kinds, true ), array_search( 'refresh', $kinds, true ) );
	}

	/**
	 * Malformed stored tokens are refreshed or reissued before the call.
	 *
	 * @return void
	 */
	public function test_malformed_token_is_refreshed_or_reissued() {
		$this->seed_master_state( array( 'license.jwt' => 'not-a-token' ) );

		$this->run_image_call( 'usage' );

		$kinds = $this->kinds();
		$first = array_values( array_intersect( $kinds, array( 'refresh', 'check_license' ) ) );
		$this->assertNotEmpty( $first, 'A malformed token must trigger refresh or check_license.' );
		$this->assertLessThan(
			array_search( 'usage', $kinds, true ),
			array_search( $first[0], $kinds, true )
		);
	}

	/**
	 * A newer token written during refresh is not overwritten.
	 *
	 * @return void
	 */
	public function test_refresh_keeps_newer_token_written_concurrently() {
		$old   = $this->near_expiry_token( 'exp' );
		$newer = $this->fresh_token();
		$this->seed_master_state( array( 'license.jwt' => $old ) );
		$this->on_refresh = function () use ( $newer ) {
			$this->write_raw_jwt( $newer );
		};

		$this->run_image_call( 'usage' );

		$this->assertCount( 1, $this->requests_of( 'refresh' ) );
		$this->assertSame( $newer, $this->raw_jwt(), 'Refresh must not overwrite a newer license.jwt.' );
	}

	/**
	 * An unexpired lock prevents a second refresh.
	 *
	 * @return void
	 */
	public function test_unexpired_lock_prevents_refresh() {
		$this->seed_master_state( array( 'license.jwt' => $this->near_expiry_token( 'exp' ) ) );
		update_site_option(
			self::LOCK_OPTION,
			array(
				'owner'   => 'another-process',
				'expires' => time() + 60,
			)
		);

		$this->run_image_call( 'usage' );

		$this->assertSame( array(), $this->requests_of( 'refresh' ), 'A held lock must block a second /v2/refresh.' );
	}

	/**
	 * Refresh must not delete a lock that another owner took over.
	 *
	 * @return void
	 */
	public function test_refresh_does_not_delete_replacement_lock() {
		$this->seed_master_state( array( 'license.jwt' => $this->near_expiry_token( 'exp' ) ) );
		$replacement      = array(
			'owner'   => 'replacement-owner',
			'expires' => time() + 60,
		);
		$this->on_refresh = function () use ( $replacement ) {
			update_site_option( self::LOCK_OPTION, $replacement );
		};

		$this->run_image_call( 'usage' );

		$this->assertCount( 1, $this->requests_of( 'refresh' ) );
		$this->assertSame( $replacement, get_site_option( self::LOCK_OPTION, false ), 'The replacement lock must remain.' );
	}

	/**
	 * Pro convert uses /v2/image/convert with Bearer, no license key.
	 *
	 * @return void
	 */
	public function test_pro_convert_uses_v2_with_bearer() {
		$token = $this->fresh_token();
		$this->seed_master_state( array( 'license.jwt' => $token ) );

		$this->run_image_call( 'convert' );

		$calls = $this->requests_of( 'convert' );
		$this->assertCount( 1, $calls );
		$this->assertStringContainsString( '/v2/image/convert', $calls[0]['url'] );
		$this->assertSame( 'Bearer ' . $token, $this->header( $calls[0]['args'], 'Authorization' ) );
		$this->assertSame( 0, (int) ( $calls[0]['args']['redirection'] ?? -1 ) );
		$this->assertStringNotContainsString( self::FAKE_KEY, $calls[0]['body'], 'License key must not be in the multipart body.' );
	}

	/**
	 * Pro usage uses /v2/image/usage with Bearer, no license key in path.
	 *
	 * @return void
	 */
	public function test_pro_usage_uses_v2_with_bearer() {
		$token = $this->fresh_token();
		$this->seed_master_state( array( 'license.jwt' => $token ) );

		$this->run_image_call( 'usage' );

		$calls = $this->requests_of( 'usage' );
		$this->assertCount( 1, $calls );
		$this->assertStringContainsString( '/v2/image/usage', $calls[0]['url'] );
		$this->assertStringNotContainsString( self::FAKE_KEY, $calls[0]['url'], 'License key must not be in the usage path.' );
		$this->assertSame( 'Bearer ' . $token, $this->header( $calls[0]['args'], 'Authorization' ) );
		$this->assertSame( 0, (int) ( $calls[0]['args']['redirection'] ?? -1 ) );
	}

	/**
	 * Free calls use /v2/image paths without Bearer.
	 *
	 * @dataProvider provider_image_calls
	 *
	 * @param string $call Image call.
	 *
	 * @return void
	 */
	public function test_free_calls_use_v2_without_bearer( string $call ) {
		$this->set_config(
			array(
				'plugin.type'        => '',
				'plugin.license_key' => '',
			)
		);

		$this->run_image_call( $call );

		$calls = $this->requests_of( $call );
		$this->assertCount( 1, $calls );
		$this->assertStringContainsString( '/v2/image/' . $call, $calls[0]['url'] );
		$this->assertNull( $this->header( $calls[0]['args'], 'Authorization' ), 'Free requests must not send Authorization.' );
	}

	/**
	 * 401 triggers one refresh/reissue and exactly one retry.
	 *
	 * @dataProvider provider_image_calls
	 *
	 * @param string $call Image call.
	 *
	 * @return void
	 */
	public function test_401_refreshes_once_and_retries_once( string $call ) {
		$this->seed_master_state( array( 'license.jwt' => $this->fresh_token() ) );
		$this->image_codes[ $call ] = array( 401, 200 );

		$this->run_image_call( $call );

		$this->assertCount( 2, $this->requests_of( $call ), '401 must retry exactly once.' );
		$reissues = count( $this->requests_of( 'refresh' ) ) + count( $this->requests_of( 'check_license' ) );
		$this->assertSame( 1, $reissues, '401 must refresh or reissue exactly once.' );
	}

	/**
	 * 403 does not refresh or retry.
	 *
	 * @dataProvider provider_image_calls
	 *
	 * @param string $call Image call.
	 *
	 * @return void
	 */
	public function test_403_does_not_refresh_or_retry( string $call ) {
		$this->seed_master_state( array( 'license.jwt' => $this->fresh_token() ) );
		$this->image_codes[ $call ] = array( 403, 200 );

		$this->run_image_call( $call );

		$this->assertCount( 1, $this->requests_of( $call ), '403 must not retry.' );
		$this->assertSame( array(), $this->requests_of( 'refresh' ) );
		$this->assertSame( array(), $this->requests_of( 'check_license' ) );
	}

	/**
	 * Status and download stay on legacy paths.
	 *
	 * @return void
	 */
	public function test_status_and_download_stay_on_legacy_paths() {
		$this->seed_master_state( array( 'license.jwt' => $this->fresh_token() ) );
		$api = new Extension_ImageService_Api();
		$api->get_status( 1, 'sig' );
		$api->download( 1, 'sig' );

		$status   = $this->requests_of( 'status' );
		$download = $this->requests_of( 'download' );
		$this->assertCount( 1, $status );
		$this->assertCount( 1, $download );
		$this->assertStringContainsString( '/job/status', $status[0]['url'] );
		$this->assertStringNotContainsString( '/v2/', $status[0]['url'] );
		$this->assertStringContainsString( '/image/download', $download[0]['url'] );
		$this->assertStringNotContainsString( '/v2/', $download[0]['url'] );
	}

	/**
	 * Failed refresh falls back to one check_license.
	 *
	 * @return void
	 */
	public function test_failed_refresh_falls_back_to_check_license() {
		$this->seed_master_state( array( 'license.jwt' => $this->near_expiry_token( 'exp' ) ) );
		$this->refresh_code = 500;

		$this->run_image_call( 'usage' );

		$this->assertCount( 1, $this->requests_of( 'refresh' ) );
		$checks = $this->requests_of( 'check_license' );
		$this->assertCount( 1, $checks, 'Failed refresh must fall back to one check_license.' );
		$this->assertStringContainsString( '/v2/', $checks[0]['url'] );
	}
}
