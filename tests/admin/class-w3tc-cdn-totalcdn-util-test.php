<?php
/**
 * File: class-w3tc-cdn-totalcdn-util-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
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
	 * Original dispatcher instances.
	 *
	 * @var array
	 */
	private $original_dispatcher_instances = array();

	/**
	 * Config state reference.
	 *
	 * @var object
	 */
	private $config_state;

	/**
	 * Original Total CDN status value.
	 *
	 * @var string
	 */
	private $original_totalcdn_status = 'inactive.no_key';

	/**
	 * Sets up the test environment before each test.
	 *
	 * This method is called before each test is executed to initialize
	 * any necessary preconditions or configurations required for the test.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$this->restore_dispatcher_instances();

		if ( $this->config_state ) {
			$this->config_state->set( 'cdn.totalcdn.status', $this->original_totalcdn_status );
		}

		parent::tearDown();
	}

	/**
	 * Ensure Total CDN FSD is reported as enabled when configured correctly.
	 *
	 * @since x.x.x
	 */
	public function test_is_totalcdn_cdnfsd_enabled_returns_true_with_expected_configuration() {
		$this->mock_dispatcher_config(
			array(
				'cdnfsd.enabled' => true,
				'cdnfsd.engine'  => 'totalcdn',
			)
		);

		$this->assertTrue( Cdn_TotalCdn_Util::is_totalcdn_cdnfsd_enabled() );
	}

	/**
	 * Ensure Total CDN FSD is reported as disabled when configuration does not match requirements.
	 *
	 * @since x.x.x
	 *
	 * @dataProvider data_provider_for_cdnfsd_disabled
	 *
	 * @param array $config Configuration values to mock.
	 */
	public function test_is_totalcdn_cdnfsd_enabled_returns_false_when_requirements_are_not_met( $config ) {
		$this->mock_dispatcher_config( $config );

		$this->assertFalse( Cdn_TotalCdn_Util::is_totalcdn_cdnfsd_enabled() );
	}

	/**
	 * Data provider for disabled CDN FSD scenarios.
	 *
	 * @since x.x.x
	 *
	 * @return array[]
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
			'missing settings' => array( array() ),
		);
	}

	/**
	 * Ensure Total CDN authorization requires both API key and pull zone.
	 *
	 * @since x.x.x
	 */
	public function test_is_totalcdn_cdnfsd_authorized_requires_api_key_and_pull_zone() {
		$this->mock_dispatcher_config(
			array(
				'cdn.totalcdn.account_api_key' => 'key-123',
				'cdn.totalcdn.pull_zone_id'    => 42,
			)
		);

		$this->assertTrue( Cdn_TotalCdn_Util::is_totalcdn_cdnfsd_authorized() );

		$this->mock_dispatcher_config(
			array(
				'cdn.totalcdn.account_api_key' => '',
				'cdn.totalcdn.pull_zone_id'    => 42,
			)
		);

		$this->assertFalse( Cdn_TotalCdn_Util::is_totalcdn_cdnfsd_authorized() );

		$this->mock_dispatcher_config(
			array(
				'cdn.totalcdn.account_api_key' => 'key-123',
				'cdn.totalcdn.pull_zone_id'    => 0,
			)
		);

		$this->assertFalse( Cdn_TotalCdn_Util::is_totalcdn_cdnfsd_authorized() );
	}

	/**
	 * Ensure license check respects the stored Total CDN status value.
	 *
	 * @since x.x.x
	 */
	public function test_is_totalcdn_license_active_checks_status_prefix() {
		$this->config_state->set( 'cdn.totalcdn.status', 'active.connected' );
		$this->assertTrue( Cdn_TotalCdn_Util::is_totalcdn_license_active() );

		$this->config_state->set( 'cdn.totalcdn.status', 'inactive.no_key' );
		$this->assertFalse( Cdn_TotalCdn_Util::is_totalcdn_license_active() );
	}

	/**
	 * Retrieve Dispatcher instances via reflection.
	 *
	 * @since x.x.x
	 *
	 * @return array
	 */
	private function get_dispatcher_instances() {
		$reflection = new ReflectionClass( Dispatcher::class );
		$property   = $reflection->getProperty( 'instances' );
		$property->setAccessible( true );

		$instances = $property->getValue();
		if ( ! is_array( $instances ) ) {
			$instances = array();
		}

		return $instances;
	}

	/**
	 * Restore original Dispatcher instances.
	 *
	 * @since x.x.x
	 */
	private function restore_dispatcher_instances() {
		$this->set_dispatcher_instances( $this->original_dispatcher_instances );
	}

	/**
	 * Set Dispatcher instances via reflection.
	 *
	 * @since x.x.x
	 *
	 * @param array $instances Instances to set.
	 */
	private function set_dispatcher_instances( $instances ) {
		$reflection = new ReflectionClass( Dispatcher::class );
		$property   = $reflection->getProperty( 'instances' );
		$property->setAccessible( true );
		$property->setValue( $instances );
	}

	/**
	 * Mock the Dispatcher configuration component.
	 *
	 * @since x.x.x
	 *
	 * @param array $config Configuration values to provide.
	 */
	private function mock_dispatcher_config( array $config ) {
		$instances            = $this->get_dispatcher_instances();
		$instances['Config'] = new class( $config ) {
			/**
			 * Configuration data.
			 *
			 * @since x.x.x
			 *
			 * @var array
			 */
			private $config;

			/**
			 * Constructor.
			 *
			 * @since x.x.x
			 *
			 * @param array $config Configuration values.
			 */
			public function __construct( array $config ) {
				$this->config = $config;
			}

			/**
			 * Retrieve boolean configuration values.
			 *
			 * @since x.x.x
			 *
			 * @param string $key Configuration key.
			 *
			 * @return bool
			 */
			public function get_boolean( $key ) {
				return isset( $this->config[ $key ] ) ? (bool) $this->config[ $key ] : false;
			}

			/**
			 * Retrieve string configuration values.
			 *
			 * @since x.x.x
			 *
			 * @param string $key Configuration key.
			 *
			 * @return string
			 */
			public function get_string( $key ) {
				return isset( $this->config[ $key ] ) ? (string) $this->config[ $key ] : '';
			}

			/**
			 * Retrieve integer configuration values.
			 *
			 * @since x.x.x
			 *
			 * @param string $key Configuration key.
			 *
			 * @return int
			 */
			public function get_integer( $key ) {
				return isset( $this->config[ $key ] ) ? (int) $this->config[ $key ] : 0;
			}
		};

		$this->set_dispatcher_instances( $instances );
	}
}