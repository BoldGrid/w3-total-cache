<?php
/**
 * File: class-w3tc-rackspace-access-callback-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\CdnEngine_Mirror_RackSpaceCdn;
use W3TC\CdnEngine_RackSpaceCloudFiles;

/**
 * Class: W3tc_Rackspace_Access_Callback_Test
 *
 * The second Rackspace access callback is invoked with call_user_func()
 * from the API classes. It must stay callable on PHP 8.
 *
 * @since X.X.X
 */
class W3tc_Rackspace_Access_Callback_Test extends WP_UnitTestCase {

	/**
	 * HTTP requests seen by the pre_http_request filter.
	 *
	 * @var array
	 */
	private $requests = array();

	/**
	 * Fakes the Rackspace identity response.
	 *
	 * The region endpoints use plain http so the API classes drop them
	 * without a DNS lookup, and every API call goes straight to the
	 * access-renewal callback.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->requests = array();
		add_filter( 'pre_http_request', array( $this, 'fake_identity_response' ), 10, 3 );
	}

	/**
	 * Removes the HTTP fake.
	 *
	 * @return void
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'fake_identity_response' ), 10 );
		parent::tear_down();
	}

	/**
	 * Returns a token and service catalog for the identity endpoint.
	 *
	 * @param false|array $preempt Preempt value.
	 * @param array       $args    Request arguments.
	 * @param string      $url     Request URL.
	 *
	 * @return array
	 */
	public function fake_identity_response( $preempt, $args, $url ) {
		$this->requests[] = $url;

		return array(
			'headers'  => array(),
			'body'     => wp_json_encode(
				array(
					'access' => array(
						'token'          => array( 'id' => 'test-token' ),
						'serviceCatalog' => array(
							array(
								'type'      => 'object-store',
								'endpoints' => array(
									array(
										'region'      => 'ORD',
										'publicURL'   => 'http://storage.example.test/v1',
										'internalURL' => 'http://storage.example.test/v1',
									),
								),
							),
							array(
								'type'      => 'rax:object-cdn',
								'endpoints' => array(
									array(
										'region'    => 'ORD',
										'publicURL' => 'http://cdn.example.test/v1',
									),
								),
							),
							array(
								'type'      => 'rax:cdn',
								'endpoints' => array(
									array(
										'region'    => 'ORD',
										'publicURL' => 'http://cdn.example.test/v1.0',
									),
								),
							),
						),
					),
				)
			),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
		);
	}

	/**
	 * Cloud Files renewal reaches the second-attempt callback and gets
	 * the authentication failure, not a TypeError.
	 *
	 * @return void
	 */
	public function test_cloudfiles_second_access_callback_runs_from_api() {
		$engine = new CdnEngine_RackSpaceCloudFiles(
			array(
				'user_name'                 => 'user',
				'api_key'                   => 'key',
				'region'                    => 'ORD',
				'container'                 => 'container',
				'new_access_state_callback' => static function () {
				},
			)
		);

		try {
			$engine->_on_new_access_requested_api_cdn();
			$this->fail( 'Expected the second access attempt to fail.' );
		} catch ( Exception $e ) {
			$this->assertStringContainsString( 'Authentication failed', $e->getMessage() );
		}

		$this->assertCount( 1, $this->requests );
	}

	/**
	 * Rackspace mirror CDN renewal reaches the second-attempt callback
	 * and gets the authentication failure, not a TypeError.
	 *
	 * @return void
	 */
	public function test_mirror_second_access_callback_runs_from_api() {
		$engine = new CdnEngine_Mirror_RackSpaceCdn(
			array(
				'user_name'  => 'user',
				'api_key'    => 'key',
				'region'     => 'ORD',
				'service_id' => 'service',
			)
		);

		try {
			$engine->service_domains_get();
			$this->fail( 'Expected the second access attempt to fail.' );
		} catch ( Exception $e ) {
			$this->assertStringContainsString( 'Authentication failed', $e->getMessage() );
		}

		$this->assertCount( 1, $this->requests );
	}

	/**
	 * The second-attempt handlers stay private.
	 *
	 * @return void
	 */
	public function test_second_access_handlers_stay_private() {
		foreach ( array( CdnEngine_RackSpaceCloudFiles::class, CdnEngine_Mirror_RackSpaceCdn::class ) as $class ) {
			$method = new ReflectionMethod( $class, '_on_new_access_requested_second_time' );
			$this->assertTrue( $method->isPrivate(), $class );
		}
	}
}
