<?php
/**
 * File: class-w3tc-cdn-totalcdn-api-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      x.x.x
 */

declare( strict_types = 1 );

use W3TC\Cdn_TotalCdn_Api;

/**
 * Class: W3tc_Cdn_TotalCdn_Api_Test
 *
 * @since x.x.x
 */
class W3tc_Cdn_TotalCdn_Api_Test extends WP_UnitTestCase {
	/**
	 * Ensure a pull zone id is required when checking custom hostnames.
	 *
	 * @since x.x.x
	 */
	public function test_check_custom_hostname_requires_pull_zone_id() {
		$api = new Cdn_TotalCdn_Api(
			array(
				'account_api_key' => 'account-key',
			)
		);

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Invalid pull zone id.' );

		$api->check_custom_hostname( 'example.com' );
	}

	/**
	 * Ensure the hostname must be valid when checking custom hostnames.
	 *
	 * @since x.x.x
	 */
	public function test_check_custom_hostname_requires_valid_hostname() {
		$api = new Cdn_TotalCdn_Api(
			array(
				'account_api_key' => 'account-key',
				'pull_zone_id'    => 1,
			)
		);

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Invalid hostname "".' );

		$api->check_custom_hostname( '' );
	}

	/**
	 * Ensure the API request is issued with the expected parameters and response is returned.
	 *
	 * @since x.x.x
	 */
	public function test_check_custom_hostname_success() {
		$api = new Cdn_TotalCdn_Api(
			array(
				'account_api_key' => 'account-key',
				'pull_zone_id'    => 1,
			)
		);

		$requested_url     = '';
		$requested_headers = array();
		$filter            = function( $preempt, $args, $url ) use ( &$requested_url, &$requested_headers ) {
			$requested_url     = $url;
			$requested_headers = isset( $args['headers'] ) ? $args['headers'] : array();

			return array(
				'body'     => wp_json_encode( array( 'Status' => 'Valid' ) ),
				'headers'  => array(),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
			);
		};

		add_filter( 'pre_http_request', $filter, 10, 3 );

		try {
			$response = $api->check_custom_hostname( 'example.com' );
		} finally {
			remove_filter( 'pre_http_request', $filter, 10 );
		}

		$this->assertSame( array( 'Status' => 'Valid' ), $response );
		$this->assertSame(
			W3TC_CDN_API_URL . '/pullzone/1/checkCustomHostname?CustomHostName=example.com',
			$requested_url
		);
		$this->assertArrayHasKey( 'ApiKey', $requested_headers );
		$this->assertSame( 'account-key', $requested_headers['ApiKey'] );
		$this->assertArrayHasKey( 'Accept', $requested_headers );
		$this->assertSame( 'application/json', $requested_headers['Accept'] );
	}
}
