<?php
/**
 * File: class-w3tc-cdn-totalcdn-customhostname-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\Cdn_TotalCdn_CustomHostname;
use W3TC\Config;

/**
 * Class: W3tc_Cdn_TotalCdn_CustomHostname_Test
 *
 * @since X.X.X
 */
class W3tc_Cdn_TotalCdn_CustomHostname_Test extends WP_UnitTestCase {
	/**
	 * Stored config data keyed by mock object id.
	 *
	 * @var array
	 */
	private $config_storage = array();

	/**
	 * Sets up the test case with fresh config storage and static resets.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->config_storage = array();
		$this->reset_class_state();
	}

	/**
	 * Tears down the test case and clears static state.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$this->reset_class_state();
		$this->config_storage = array();
		parent::tearDown();
	}

	/**
	 * Ensures should_attempt_on_save() bails when FSD is disabled or not applicable.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_should_attempt_on_save_returns_false_when_not_applicable() {
		$config = $this->create_config_mock(
			array(
				'cdnfsd.enabled' => false,
			)
		);

		$this->assertFalse( Cdn_TotalCdn_CustomHostname::should_attempt_on_save( $config ) );
	}

	/**
	 * Confirms should_attempt_on_save() triggers when the pull zone ID changes.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_should_attempt_on_save_detects_pull_zone_change() {
		$new_config = $this->create_config_mock(
			array(
				'cdn.totalcdn.pull_zone_id' => 2,
			)
		);
		$old_config = $this->create_config_mock(
			array(
				'cdn.totalcdn.pull_zone_id' => 1,
			)
		);

		$this->assertTrue( Cdn_TotalCdn_CustomHostname::should_attempt_on_save( $new_config, $old_config ) );
	}

	/**
	 * Verifies should_attempt_on_save() retries when the status is empty.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_should_attempt_on_save_detects_empty_status() {
		$site_filter = function() {
			return 'https://example.com';
		};

		add_filter( 'pre_option_siteurl', $site_filter );
		add_filter( 'pre_option_home', $site_filter );

		try {
			$new_config = $this->create_config_mock(
				array(
					'cdn.totalcdn.pull_zone_id'              => 3,
					'cdnfsd.totalcdn.custom_hostname'        => 'example.com',
					'cdnfsd.totalcdn.custom_hostname_status' => '',
				)
			);

			$old_config = $this->create_config_mock(
				array(
					'cdn.totalcdn.pull_zone_id'              => 3,
					'cdnfsd.totalcdn.custom_hostname'        => 'example.com',
					'cdnfsd.totalcdn.custom_hostname_status' => 'exists',
				)
			);

			$this->assertTrue( Cdn_TotalCdn_CustomHostname::should_attempt_on_save( $new_config, $old_config ) );
		} finally {
			remove_filter( 'pre_option_home', $site_filter );
			remove_filter( 'pre_option_siteurl', $site_filter );
		}
	}

	/**
	 * Ensures ensure() short-circuits when FSD TotalCDN is not active.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_ensure_skips_when_not_applicable() {
		$config = $this->create_config_mock(
			array(
				'cdnfsd.enabled' => false,
			)
		);

		$config->expects( $this->never() )->method( 'save' );

		$result = Cdn_TotalCdn_CustomHostname::ensure( $config );

		$this->assertTrue( $result['success'] );
		$this->assertTrue( $result['skipped'] );
		$this->assertNull( $result['hostname'] );
		$this->assertNull( $result['status'] );
		$this->assertNull( $result['error'] );
		$this->assertFalse( $result['added'] );
	}

	/**
	 * Confirms ensure() records an error when the site hostname cannot be resolved.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_ensure_records_error_when_hostname_missing() {
		$config = $this->create_config_mock();

		$config->expects( $this->never() )->method( 'save' );

		$site_filter = function() {
			return '';
		};
		$home_filter = function( $url, $path = '', $orig_scheme = null, $blog_id = null ) {
			return '';
		};

		add_filter( 'pre_option_siteurl', $site_filter );
		add_filter( 'pre_option_home', $site_filter );
		add_filter( 'home_url', $home_filter, 10, 4 );

		try {
			$result = Cdn_TotalCdn_CustomHostname::ensure( $config );

			$message = __( 'Unable to determine the site hostname required for Full Site Delivery.', 'w3-total-cache' );

			$this->assertFalse( $result['success'] );
			$this->assertFalse( $result['skipped'] );
			$this->assertNull( $result['hostname'] );
			$this->assertSame( $message, $result['error'] );
			$this->assertSame( 'error', $this->get_config_storage_value( $config, 'cdnfsd.totalcdn.custom_hostname_status' ) );
			$this->assertSame( $message, $this->get_config_storage_value( $config, 'cdnfsd.totalcdn.custom_hostname_last_error' ) );
		} finally {
			remove_filter( 'home_url', $home_filter, 10 );
			remove_filter( 'pre_option_home', $site_filter );
			remove_filter( 'pre_option_siteurl', $site_filter );
		}
	}

	/**
	 * Verifies ensure() recognizes an already configured hostname and caches the result.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_ensure_confirms_existing_hostname() {
		$config = $this->create_config_mock();

		$config->expects( $this->never() )->method( 'save' );

		$site_filter = function() {
			return 'https://example.com';
		};
		add_filter( 'pre_option_siteurl', $site_filter );
		add_filter( 'pre_option_home', $site_filter );

		$http_filter = function( $preempt, $args, $url ) {
			if ( false !== strpos( $url, '/checkCustomHostname' ) ) {
				return array(
					'body'     => wp_json_encode( array( 'Exists' => true ) ),
					'headers'  => array(),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
				);
			}

			return $preempt;
		};
		add_filter( 'pre_http_request', $http_filter, 10, 3 );

		try {
			$result = Cdn_TotalCdn_CustomHostname::ensure( $config );

			$this->assertTrue( $result['success'] );
			$this->assertFalse( $result['skipped'] );
			$this->assertSame( 'example.com', $result['hostname'] );
			$this->assertSame( 'exists', $result['status'] );
			$this->assertNull( $result['error'] );
			$this->assertFalse( $result['added'] );

			remove_filter( 'pre_http_request', $http_filter, 10 );

			$cached = Cdn_TotalCdn_CustomHostname::ensure( $config );
			$this->assertSame( $result, $cached );

			$this->assertSame( 'example.com', $this->get_config_storage_value( $config, 'cdnfsd.totalcdn.custom_hostname' ) );
			$this->assertSame( 'exists', $this->get_config_storage_value( $config, 'cdnfsd.totalcdn.custom_hostname_status' ) );
			$this->assertSame( '', $this->get_config_storage_value( $config, 'cdnfsd.totalcdn.custom_hostname_last_error' ) );
		} finally {
			remove_filter( 'pre_http_request', $http_filter, 10 );
			remove_filter( 'pre_option_home', $site_filter );
			remove_filter( 'pre_option_siteurl', $site_filter );
		}
	}

	/**
	 * Ensures ensure() issues an add request when the hostname is missing.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_ensure_adds_hostname_when_not_found() {
		$config = $this->create_config_mock();

		$config->expects( $this->once() )->method( 'save' );

		$site_filter = function() {
			return 'https://example.com';
		};
		add_filter( 'pre_option_siteurl', $site_filter );
		add_filter( 'pre_option_home', $site_filter );

		$calls = array(
			'check' => 0,
			'add'   => 0,
		);

		$http_filter = function( $preempt, $args, $url ) use ( &$calls ) {
			if ( false !== strpos( $url, '/checkCustomHostname' ) ) {
				$calls['check']++;
				return array(
					'body'     => wp_json_encode( array( 'Message' => 'Custom hostname not found' ) ),
					'headers'  => array(),
					'response' => array(
						'code'    => 404,
						'message' => 'Not Found',
					),
					'cookies'  => array(),
				);
			}

			if ( false !== strpos( $url, '/addCustomHostname' ) ) {
				$calls['add']++;
				return array(
					'body'     => wp_json_encode( array() ),
					'headers'  => array(),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
				);
			}

			return $preempt;
		};
		add_filter( 'pre_http_request', $http_filter, 10, 3 );

		try {
			$result = Cdn_TotalCdn_CustomHostname::ensure(
				$config,
				array( 'persist' => true )
			);

			$this->assertTrue( $result['success'] );
			$this->assertFalse( $result['skipped'] );
			$this->assertTrue( $result['added'] );
			$this->assertSame( 'pending', $result['status'] );
			$this->assertNull( $result['error'] );
			$this->assertSame( 1, $calls['check'] );
			$this->assertSame( 1, $calls['add'] );
			$this->assertSame( 'example.com', $this->get_config_storage_value( $config, 'cdnfsd.totalcdn.custom_hostname' ) );
			$this->assertSame( 'pending', $this->get_config_storage_value( $config, 'cdnfsd.totalcdn.custom_hostname_status' ) );
			$this->assertSame( '', $this->get_config_storage_value( $config, 'cdnfsd.totalcdn.custom_hostname_last_error' ) );
			$this->assertGreaterThan( 0, (int) $this->get_config_storage_value( $config, 'cdnfsd.totalcdn.custom_hostname_last_checked' ) );
		} finally {
			remove_filter( 'pre_http_request', $http_filter, 10 );
			remove_filter( 'pre_option_home', $site_filter );
			remove_filter( 'pre_option_siteurl', $site_filter );
		}
	}

	/**
	 * Confirms ensure() treats already-exists errors as success and marks status pending.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_ensure_handles_existing_hostname_error() {
		$config = $this->create_config_mock();

		$config->expects( $this->never() )->method( 'save' );

		$site_filter = function() {
			return 'https://example.com';
		};
		add_filter( 'pre_option_siteurl', $site_filter );
		add_filter( 'pre_option_home', $site_filter );

		$calls = array(
			'check' => 0,
			'add'   => 0,
		);

		$http_filter = function( $preempt, $args, $url ) use ( &$calls ) {
			if ( false !== strpos( $url, '/checkCustomHostname' ) ) {
				$calls['check']++;
				return array(
					'body'     => wp_json_encode( array( 'Message' => 'Custom hostname not found' ) ),
					'headers'  => array(),
					'response' => array(
						'code'    => 404,
						'message' => 'Not Found',
					),
					'cookies'  => array(),
				);
			}

			if ( false !== strpos( $url, '/addCustomHostname' ) ) {
				$calls['add']++;
				return array(
					'body'     => wp_json_encode( array( 'Message' => 'Hostname already exists' ) ),
					'headers'  => array(),
					'response' => array(
						'code'    => 400,
						'message' => 'Bad Request',
					),
					'cookies'  => array(),
				);
			}

			return $preempt;
		};
		add_filter( 'pre_http_request', $http_filter, 10, 3 );

		try {
			$result = Cdn_TotalCdn_CustomHostname::ensure( $config );

			$this->assertTrue( $result['success'] );
			$this->assertFalse( $result['skipped'] );
			$this->assertFalse( $result['added'] );
			$this->assertSame( 'pending', $result['status'] );
			$this->assertNull( $result['error'] );
			$this->assertSame( 1, $calls['check'] );
			$this->assertSame( 1, $calls['add'] );
			$this->assertSame( 'example.com', $this->get_config_storage_value( $config, 'cdnfsd.totalcdn.custom_hostname' ) );
			$this->assertSame( 'pending', $this->get_config_storage_value( $config, 'cdnfsd.totalcdn.custom_hostname_status' ) );
			$this->assertSame( '', $this->get_config_storage_value( $config, 'cdnfsd.totalcdn.custom_hostname_last_error' ) );
		} finally {
			remove_filter( 'pre_http_request', $http_filter, 10 );
			remove_filter( 'pre_option_home', $site_filter );
			remove_filter( 'pre_option_siteurl', $site_filter );
		}
	}

	/**
	 * Creates a Config mock seeded with provided overrides.
	 *
	 * @since X.X.X
	 *
	 * @param array $values Configuration overrides.
	 *
	 * @return Config
	 */
	private function create_config_mock( array $values = array() ): Config {
		$defaults = array(
			'cdnfsd.enabled'                               => true,
			'cdnfsd.engine'                                => 'totalcdn',
			'cdn.totalcdn.account_api_key'                 => 'account-key',
			'cdn.totalcdn.pull_zone_id'                    => 1,
			'cdnfsd.totalcdn.custom_hostname'              => '',
			'cdnfsd.totalcdn.custom_hostname_status'       => '',
			'cdnfsd.totalcdn.custom_hostname_last_error'   => '',
			'cdnfsd.totalcdn.custom_hostname_last_checked' => 0,
		);

		$storage = array_merge( $defaults, $values );

		$config = $this->getMockBuilder( Config::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_boolean', 'get_string', 'get_integer', 'set', 'save' ) )
			->getMock();

		$config->method( 'get_boolean' )->willReturnCallback(
			function( $key ) use ( &$storage ) {
				return ! empty( $storage[ $key ] );
			}
		);

		$config->method( 'get_string' )->willReturnCallback(
			function( $key ) use ( &$storage ) {
				return isset( $storage[ $key ] ) ? (string) $storage[ $key ] : '';
			}
		);

		$config->method( 'get_integer' )->willReturnCallback(
			function( $key ) use ( &$storage ) {
				return isset( $storage[ $key ] ) ? (int) $storage[ $key ] : 0;
			}
		);

		$config->method( 'set' )->willReturnCallback(
			function( $key, $value ) use ( &$storage ) {
				$storage[ $key ] = $value;
			}
		);

		$this->config_storage[ spl_object_id( $config ) ] = &$storage;

		return $config;
	}

	/**
	 * Retrieves a stored configuration value for assertions.
	 *
	 * @since X.X.X
	 *
	 * @param Config $config Config mock reference.
	 * @param string $key    Config key to fetch.
	 *
	 * @return mixed|null
	 */
	private function get_config_storage_value( Config $config, string $key ) {
		$object_id = spl_object_id( $config );

		if ( ! isset( $this->config_storage[ $object_id ] ) ) {
			return null;
		}

		return isset( $this->config_storage[ $object_id ][ $key ] ) ? $this->config_storage[ $object_id ][ $key ] : null;
	}

	/**
	 * Resets static caches on the class under test between runs.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	private function reset_class_state(): void {
		$classname = Cdn_TotalCdn_CustomHostname::class;

		try {
			$reflection = new \ReflectionClass( $classname );
		} catch ( ReflectionException $e ) {
			// Fail the test early with a clear message if the class can't be reflected.
			$this->fail( sprintf( 'Unable to reflect class %s: %s', $classname, $e->getMessage() ) );
			return;
		}

		foreach ( array( 'checked', 'runtime_errors' ) as $property_name ) {
			if ( ! $reflection->hasProperty( $property_name ) ) {
				// Property not present on the class — skip to avoid exceptions.
				continue;
			}

			$property = $reflection->getProperty( $property_name );

			// setAccessible may be deprecated in newer PHP; guard its use.
			if ( method_exists( $property, 'setAccessible' ) ) {
				$property->setAccessible( true );
			}

			$empty_value = array();

			if ( $property->isStatic() ) {
				// For static properties pass null as the first argument.
				$property->setValue( null, $empty_value );
			} else {
				// For non-static properties we need an instance. Create one without running constructor.
				$instance = $reflection->newInstanceWithoutConstructor();
				$property->setValue( $instance, $empty_value );
			}
		}
	}
}
