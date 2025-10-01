<?php
/**
 * File: class-w3tc-cdn-totalcdn-util-test.php
 *
 * @package    W3TC
 *
 * @subpackage W3TC/tests/admin
 *
 * @since      x.x.x
 */

declare( strict_types = 1 );

use W3TC\Cdn_TotalCdn_Util;
use W3TC\Dispatcher;

/**
 * Class: W3tc_Cdn_TotalCdn_Util_Test
 *
 * @since x.x.x
 */
class W3tc_Cdn_TotalCdn_Util_Test extends WP_UnitTestCase {
	/**
	 * Config reference.
	 *
	 * @since x.x.x
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Config state reference.
	 *
	 * @since x.x.x
	 *
	 * @var ConfigState
	 */
	private $config_state;

	/**
	 * Backup of original config values to restore after each test.
	 *
	 * @since x.x.x
	 *
	 * @var array
	 */
	private $original_config = array();

	/**
	 * Backup of original state values to restore after each test.
	 *
	 * @since x.x.x
	 *
	 * @var array
	 */
	private $original_state = array();

	/**
	 * Keys we touch in these tests (so we can back up / restore predictably).
	 *
	 * @since x.x.x
	 *
	 * @var array
	 */
	private $touched_config_keys = array(
		'cdnfsd.enabled',
		'cdnfsd.engine',
		'cdn.totalcdn.account_api_key',
		'cdn.totalcdn.pull_zone_id',
	);

	/**
	 * Keys in config_state we touch.
	 *
	 * @since x.x.x
	 *
	 * @var array
	 */
	private $touched_state_keys = array(
		'cdn.totalcdn.status',
	);

	/**
	 * Sets up the test case with fresh references and backups.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->config       = Dispatcher::config();
		$this->config_state = Dispatcher::config_state();

		// Back up config values we might modify.
		foreach ( $this->touched_config_keys as $key ) {
			$this->original_config[ $key ] = $this->config->get( $key, null );
		}

		// Back up state values we might modify.
		foreach ( $this->touched_state_keys as $key ) {
			$this->original_state[ $key ] = $this->config_state->get( $key, null );
		}
	}

	/**
	 * Restore any changes made during a test.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		// Restore config.
		foreach ( $this->original_config as $key => $value ) {
			$this->config->set( $key, $value );
		}

		// Restore state.
		foreach ( $this->original_state as $key => $value ) {
			if ( null !== $value ) {
				$this->config_state->set( $key, $value );
			}
		}

		parent::tearDown();
	}

	/**
	 * Ensure Total CDN FSD is reported as enabled when configured correctly.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function test_is_totalcdn_cdnfsd_enabled_returns_true_with_expected_configuration() {
		$this->config->set( 'cdnfsd.enabled', true );
		$this->config->set( 'cdnfsd.engine', 'totalcdn' );

		$this->assertTrue( Cdn_TotalCdn_Util::is_totalcdn_cdnfsd_enabled() );
	}

	/**
	 * Ensure Total CDN FSD is reported as disabled when configuration does not match requirements.
	 *
	 * @since x.x.x
	 * @dataProvider data_provider_for_cdnfsd_disabled
	 *
	 * @param array $overrides Configuration values to apply.
	 *
	 * @return void
	 */
	public function test_is_totalcdn_cdnfsd_enabled_returns_false_when_requirements_are_not_met( $overrides ) {
		// Start from known "disabled" baseline, then apply overrides to simulate each case.
		$this->config->set( 'cdnfsd.enabled', false );
		$this->config->set( 'cdnfsd.engine', '' );

		foreach ( $overrides as $key => $value ) {
			$this->config->set( $key, $value );
		}

		$this->assertFalse( Cdn_TotalCdn_Util::is_totalcdn_cdnfsd_enabled() );
	}

	/**
	 * Data provider for disabled CDN FSD scenarios.
	 *
	 * @since x.x.x
	 *
	 * @return array
	 */
	public function data_provider_for_cdnfsd_disabled() {
		return array(
			'not enabled'      => array(
				array(
					'cdnfsd.enabled' => false,
					'cdnfsd.engine'  => 'totalcdn',
				),
			),
			'wrong engine'     => array(
				array(
					'cdnfsd.enabled' => true,
					'cdnfsd.engine'  => 'another',
				),
			),
			'missing settings' => array(
				// Simulate missing by not overriding anything; baseline leaves them "unset"/empty.
				array(),
			),
		);
	}

	/**
	 * Ensure Total CDN authorization requires both API key and pull zone.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function test_is_totalcdn_cdnfsd_authorized_requires_api_key_and_pull_zone() {
		// Both present.
		$this->config->set( 'cdn.totalcdn.account_api_key', 'key-123' );
		$this->config->set( 'cdn.totalcdn.pull_zone_id', 42 );
		$this->assertTrue( Cdn_TotalCdn_Util::is_totalcdn_authorized() );

		// Missing key.
		$this->config->set( 'cdn.totalcdn.account_api_key', '' );
		$this->config->set( 'cdn.totalcdn.pull_zone_id', 42 );
		$this->assertFalse( Cdn_TotalCdn_Util::is_totalcdn_authorized() );

		// Missing pull zone.
		$this->config->set( 'cdn.totalcdn.account_api_key', 'key-123' );
		$this->config->set( 'cdn.totalcdn.pull_zone_id', 0 );
		$this->assertFalse( Cdn_TotalCdn_Util::is_totalcdn_authorized() );
	}

	/**
	 * Ensure license check respects the stored Total CDN status value.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function test_is_totalcdn_license_active_checks_status_prefix() {
		$this->config_state->set( 'cdn.totalcdn.status', 'active.connected' );
		$this->assertTrue( Cdn_TotalCdn_Util::is_totalcdn_license_active() );

		$this->config_state->set( 'cdn.totalcdn.status', 'inactive.no_key' );
		$this->assertFalse( Cdn_TotalCdn_Util::is_totalcdn_license_active() );
	}
}
