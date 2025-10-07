<?php
/**
 * File: class-w3tc-cdn-totalcdn-fsd-origin-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\Cdn_TotalCdn_Api;
use W3TC\Cdn_TotalCdn_Fsd_Origin;
use W3TC\Config;

/**
 * Class: W3tc_Cdn_TotalCdn_Fsd_Origin_Test
 *
 * @since X.X.X
 */
class W3tc_Cdn_TotalCdn_Fsd_Origin_Test extends WP_UnitTestCase {
	/**
	 * Stored config data keyed by mock object id.
	 *
	 * @since X.X.X
	 *
	 * @var array
	 */
	private $config_storage = array();

	/**
	 * Callback used to override the site URL for predictable host headers.
	 *
	 * @since X.X.X
	 *
	 * @var callable|null
	 */
	private $site_filter = null;

	/**
	 * Sets up default configuration storage and site filters.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->config_storage = array();

		$this->site_filter = function() {
			return 'https://example.com';
		};

		add_filter( 'pre_option_siteurl', $this->site_filter );
		add_filter( 'pre_option_home', $this->site_filter );
	}

	/**
	 * Tears down stored configuration and removes filters.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( $this->site_filter ) {
			remove_filter( 'pre_option_siteurl', $this->site_filter );
			remove_filter( 'pre_option_home', $this->site_filter );
		}

		$this->site_filter    = null;
		$this->config_storage = array();

		parent::tearDown();
	}

	/**
	 * Ensures should_update_on_save() returns false when FSD is not applicable.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_should_update_on_save_returns_false_when_not_applicable(): void {
		$config = $this->create_config_mock(
			array(
				'cdnfsd.enabled' => false,
			)
		);

		$this->assertFalse( Cdn_TotalCdn_Fsd_Origin::should_update_on_save( $config ) );
	}

	/**
	 * Confirms should_update_on_save() triggers when enabling FSD.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_should_update_on_save_returns_true_when_enabling_fsd(): void {
		$new_config = $this->create_config_mock(
			array(
				'cdnfsd.enabled' => true,
			)
		);
		$old_config = $this->create_config_mock(
			array(
				'cdnfsd.enabled' => false,
			)
		);

		$this->assertTrue( Cdn_TotalCdn_Fsd_Origin::should_update_on_save( $new_config, $old_config ) );
	}

	/**
	 * Verifies should_update_on_save() executes when the origin is not an IP.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_should_update_on_save_returns_true_when_origin_not_ip(): void {
		$new_config = $this->create_config_mock(
			array(
				'cdn.totalcdn.origin_url' => 'https://example.com',
			)
		);
		$old_config = $this->create_config_mock(
			array(
				'cdn.totalcdn.origin_url' => 'https://example.com',
			)
		);

		$this->assertTrue( Cdn_TotalCdn_Fsd_Origin::should_update_on_save( $new_config, $old_config ) );
	}

	/**
	 * Ensures should_update_on_save() skips when the origin already uses an IP address.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_should_update_on_save_returns_false_when_origin_uses_ip(): void {
		$origin_url = 'https://203.0.113.1';

		$new_config = $this->create_config_mock(
			array(
				'cdn.totalcdn.origin_url' => $origin_url,
			)
		);
		$old_config = $this->create_config_mock(
			array(
				'cdn.totalcdn.origin_url' => $origin_url,
			)
		);

		$this->assertFalse( Cdn_TotalCdn_Fsd_Origin::should_update_on_save( $new_config, $old_config ) );
	}

	/**
	 * Confirms ensure() bails with a skipped result when not applicable.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_ensure_skips_when_not_applicable(): void {
		$config = $this->create_config_mock(
			array(
				'cdnfsd.enabled' => false,
			)
		);

		$result = Cdn_TotalCdn_Fsd_Origin::ensure( $config );

		$this->assertTrue( $result['success'] );
		$this->assertTrue( $result['skipped'] );
	}

	/**
	 * Verifies ensure() updates the origin and records configuration values.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_ensure_updates_origin_and_config(): void {
		$config = $this->create_config_mock();

		$api = $this->getMockBuilder( Cdn_TotalCdn_Api::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_origin_ip_address', 'update_pull_zone' ) )
			->getMock();

		$api->expects( $this->once() )
			->method( 'get_origin_ip_address' )
			->willReturn( '198.51.100.10' );

		$api->expects( $this->once() )
			->method( 'update_pull_zone' )
			->with(
				$this->equalTo( 1 ),
				$this->callback(
					function( $payload ) {
						$this->assertSame( 'https://198.51.100.10', $payload['OriginUrl'] );
						$this->assertSame( 'example.com', $payload['OriginHostHeader'] );
						return true;
					}
				)
			)
			->willReturn( array( 'success' => true ) );

		$result = Cdn_TotalCdn_Fsd_Origin::ensure(
			$config,
			array(
				'api' => $api,
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertFalse( $result['skipped'] );
		$this->assertSame( '198.51.100.10', $result['ip_address'] );
		$this->assertSame( 'https://198.51.100.10', $result['origin_url'] );
		$this->assertSame( 'example.com', $result['host_header'] );
		$this->assertSame( 'https://198.51.100.10', $this->get_config_storage_value( $config, 'cdn.totalcdn.origin_url' ) );
		$this->assertSame( 'https://198.51.100.10', $this->get_config_storage_value( $config, 'cdnfsd.totalcdn.origin_url' ) );
	}

	/**
	 * Ensures ensure() reports an error when the API does not provide a valid IP.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_ensure_returns_error_when_api_returns_invalid_ip(): void {
		$config = $this->create_config_mock();

		$api = $this->getMockBuilder( Cdn_TotalCdn_Api::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_origin_ip_address', 'update_pull_zone' ) )
			->getMock();

		$api->expects( $this->once() )
			->method( 'get_origin_ip_address' )
			->willReturn( 'not-an-ip' );

		$api->expects( $this->never() )
			->method( 'update_pull_zone' );

		$result = Cdn_TotalCdn_Fsd_Origin::ensure(
			$config,
			array(
				'api' => $api,
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame(
			__( 'The CDN API did not return a valid IP address for Full Site Delivery.', 'w3-total-cache' ),
			$result['error']
		);
	}

	/**
	 * Creates a configuration mock with default values and overridable storage.
	 *
	 * @since X.X.X
	 *
	 * @param array $values Optional values to override.
	 *
	 * @return Config
	 */
	private function create_config_mock( array $values = array() ): Config {
		$defaults = array(
			'cdnfsd.enabled'               => true,
			'cdnfsd.engine'                => 'totalcdn',
			'cdn.totalcdn.account_api_key' => 'account-key',
			'cdn.totalcdn.pull_zone_id'    => 1,
			'cdn.totalcdn.origin_url'      => 'https://example.com',
			'cdnfsd.totalcdn.origin_url'   => '',
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
}
