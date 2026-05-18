<?php
/**
 * File: class-w3tc-license-autoheal-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\Config;
use W3TC\Dispatcher;
use W3TC\Licensing_Plugin_Admin;

/**
 * Class: W3tc_License_Autoheal_Test
 *
 * Regression coverage for the `Licensing_Plugin_Admin::maybe_update_license_status()`
 * auto-heal for the early-bootstrap decrypt regression (#1321 follow-up,
 * #1329).
 *
 * Before #1329, every install touched by #1321 with `WP_CACHE` defined
 * would land in a stuck state after the first admin hit post-upgrade:
 *
 *   plugin.license_key   = '<correct key on disk, but wiped from singleton>'
 *   plugin.type          = ''
 *   license.status       = 'no_key'
 *   license.next_check   = time() + 5 days
 *
 * The 5-day `next_check` cache then blocked recovery for up to 5 days
 * even after the decrypt bug is fixed and the key reads correctly.
 *
 * The auto-heal added in #1329 short-circuits the rate-limit gate when
 * the cached state is logically impossible: a non-empty `plugin.license_key`
 * combined with `license.status === 'no_key'`. That pair can only be
 * reached by an earlier check running with a wiped key — by the time the
 * cache is read, the key is valid and the EDD recheck should fire
 * immediately.
 *
 * @since X.X.X
 */
class W3tc_License_Autoheal_Test extends WP_UnitTestCase {

	/**
	 * EDD HTTP mock — captures the call and returns a canned active
	 * license response so the test never hits the real network.
	 *
	 * @var bool
	 */
	private $http_called = false;

	/**
	 * Captured args from the mocked HTTP call.
	 *
	 * @var array
	 */
	private $http_args = array();

	/**
	 * Captured URL from the mocked HTTP call.
	 *
	 * @var string
	 */
	private $http_url = '';

	/**
	 * Ensure the cache temp directory the Config save() path expects is
	 * present — some WP test fixtures don't provision it.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$tmp = WP_CONTENT_DIR . '/cache/tmp';
		if ( ! is_dir( $tmp ) ) {
			@mkdir( $tmp, 0755, true );
		}
	}

	/**
	 * Tear down the HTTP mock between tests so the filter doesn't leak.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_all_filters( 'pre_http_request' );
		parent::tearDown();
	}

	/**
	 * Reflectively mutates the Dispatcher Config singleton's `_data`
	 * map without going through `save()` — which requires a writable
	 * cache/tmp dir the WP test fixtures don't always provision and
	 * which we don't need exercised for this guard test.
	 *
	 * @since X.X.X
	 *
	 * @param array $pairs Map of config key => value to apply.
	 *
	 * @return void
	 */
	private function set_config( array $pairs ): void {
		$config = Dispatcher::config();
		$ref    = new \ReflectionObject( $config );
		$prop   = $ref->getProperty( '_data' );
		$prop->setAccessible( true );
		$data = $prop->getValue( $config );
		foreach ( $pairs as $k => $v ) {
			$data[ $k ] = $v;
		}
		$prop->setValue( $config, $data );
	}

	/**
	 * Reflectively mutates the Dispatcher ConfigState singleton's `_data`
	 * map without going through `save()`.
	 *
	 * @since X.X.X
	 *
	 * @param array $pairs Map of state key => value to apply.
	 *
	 * @return void
	 */
	private function set_state( array $pairs ): void {
		$state = Dispatcher::config_state();
		$ref   = new \ReflectionObject( $state );
		$prop  = $ref->getProperty( '_data' );
		$prop->setAccessible( true );
		$data = $prop->getValue( $state );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		foreach ( $pairs as $k => $v ) {
			$data[ $k ] = $v;
		}
		$prop->setValue( $state, $data );
	}

	/**
	 * Builds a `pre_http_request` filter that intercepts EDD licensing
	 * calls and returns a canned active response.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	private function install_edd_mock(): void {
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) {
				if ( false === strpos( $url, 'edd_action=check_license' ) ) {
					return $pre;
				}
				$this->http_called = true;
				$this->http_args   = $args;
				$this->http_url    = $url;

				return array(
					'headers'  => array(),
					'body'     => wp_json_encode(
						array(
							'license'        => 'valid',
							'license_status' => 'active.by_rooturi',
							'license_terms'  => 'accept',
						)
					),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);
	}

	/**
	 * Auto-heal MUST fire when `plugin.license_key` is non-empty but
	 * `license.status === 'no_key'` AND `license.next_check` is in the
	 * future — that's the exact stuck state #1329 recovers from.
	 *
	 * Without the auto-heal, `maybe_update_license_status()` returns
	 * early via the rate-limit gate and the user stays in the broken
	 * state for up to 5 days.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_autoheal_fires_when_status_no_key_with_present_key() {
		$this->install_edd_mock();

		$this->set_config(
			array(
				'plugin.license_key' => 'usEr689iH6ZX4vJwCxR9RQDzpBvaT3es',
				'plugin.type'        => '',
			)
		);
		$this->set_state(
			array(
				'license.status'     => 'no_key',
				'license.next_check' => time() + 3600 * 24 * 5,
			)
		);

		$admin = Dispatcher::component( 'Licensing_Plugin_Admin' );
		$this->invoke_maybe_update( $admin );

		$this->assertTrue(
			$this->http_called,
			'Auto-heal must bypass the next_check rate-limit when state is stuck `no_key` with a present key.'
		);

		$state_after = Dispatcher::config_state();
		$this->assertSame(
			'active.by_rooturi',
			$state_after->get_string( 'license.status' ),
			'license.status must update to the EDD response after the bypass.'
		);

		$config_after = Dispatcher::config();
		$this->assertSame(
			'pro',
			$config_after->get_string( 'plugin.type' ),
			'plugin.type must be promoted to pro after an active EDD response.'
		);
	}

	/**
	 * Auto-heal MUST NOT fire when `license.status` is something other
	 * than `'no_key'` (i.e., the state is logically consistent). The
	 * rate-limit gate should keep its 5-day cache to avoid hammering
	 * the EDD API on every admin page load.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_autoheal_does_not_fire_when_status_is_active() {
		$this->install_edd_mock();

		$this->set_config(
			array(
				'plugin.license_key' => 'usEr689iH6ZX4vJwCxR9RQDzpBvaT3es',
				'plugin.type'        => 'pro',
			)
		);
		$this->set_state(
			array(
				'license.status'     => 'active.by_rooturi',
				'license.next_check' => time() + 3600 * 24 * 5,
			)
		);

		$admin = Dispatcher::component( 'Licensing_Plugin_Admin' );
		$this->invoke_maybe_update( $admin );

		$this->assertFalse(
			$this->http_called,
			'Auto-heal must NOT fire when status is already coherent — the next_check rate-limit must hold.'
		);
	}

	/**
	 * Auto-heal MUST NOT fire when `plugin.license_key` is empty —
	 * `license.status === 'no_key'` is the correct, expected state in
	 * that case, not a stuck cache. Bypassing the gate would just
	 * pound the EDD API with empty keys on every admin page load.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_autoheal_does_not_fire_when_license_key_is_empty() {
		$this->install_edd_mock();

		$this->set_config(
			array(
				'plugin.license_key' => '',
				'plugin.type'        => '',
			)
		);
		$this->set_state(
			array(
				'license.status'     => 'no_key',
				'license.next_check' => time() + 3600 * 24 * 5,
			)
		);

		$admin = Dispatcher::component( 'Licensing_Plugin_Admin' );
		$this->invoke_maybe_update( $admin );

		$this->assertFalse(
			$this->http_called,
			'Auto-heal must NOT fire when license_key is empty — `no_key` is the correct state, not stuck.'
		);
	}

	/**
	 * Reflectively invokes the private `maybe_update_license_status()`
	 * so we can assert the guard logic without exposing internals.
	 *
	 * @since X.X.X
	 *
	 * @param Licensing_Plugin_Admin $admin The licensing admin component.
	 *
	 * @return void
	 */
	private function invoke_maybe_update( Licensing_Plugin_Admin $admin ): void {
		$ref    = new \ReflectionObject( $admin );
		$method = $ref->getMethod( 'maybe_update_license_status' );
		$method->setAccessible( true );
		$method->invoke( $admin );
	}
}
