<?php
/**
 * File: class-w3tc-constant-url-allowlist-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.6
 */

declare( strict_types = 1 );

use W3TC\Cdn_TransparentCDN_Api;
use W3TC\Licensing_Core;
use W3TC\PageSpeed_Api;
use W3TC\Util_Environment;
use W3TC\Util_Url;

/**
 * Class: W3tc_Constant_Url_Allowlist_Test
 *
 * Regression coverage for constant-override URL allowlists at NOTICE_FEED,
 * API2, PageSpeed, TransparentCDN, and license call sites.
 *
 * @since 2.10.6
 */
class W3tc_Constant_Url_Allowlist_Test extends WP_UnitTestCase {

	/**
	 * Captured outbound request URLs from HTTP mocks.
	 *
	 * @var array
	 */
	private $http_urls = array();

	/**
	 * Captured outbound request args from HTTP mocks.
	 *
	 * @var array
	 */
	private $http_args = array();

	/**
	 * Reset HTTP capture between tests.
	 *
	 * @since 2.10.6
	 */
	public function tearDown(): void {
		\remove_all_filters( 'pre_http_request' );
		$this->http_urls = array();
		$this->http_args = array();
		parent::tearDown();
	}

	/**
	 * Absent W3TC_API2_URL keeps the production default.
	 *
	 * @since 2.10.6
	 */
	public function test_get_api_base_url_absent_constant_uses_default() {
		if ( \defined( 'W3TC_API2_URL' ) ) {
			$this->markTestSkipped( 'W3TC_API2_URL already defined in this process.' );
		}

		$this->assertSame( 'https://api2.w3-edge.com', Util_Environment::get_api_base_url() );
	}

	/**
	 * Rejected W3TC_API2_URL cannot redirect API traffic; falls back.
	 *
	 * Defines the constant once for this process — keep after absent test.
	 *
	 * @since 2.10.6
	 */
	public function test_get_api_base_url_rejected_constant_falls_back() {
		if ( ! \defined( 'W3TC_API2_URL' ) ) {
			\define( 'W3TC_API2_URL', 'https://evil.example/api' );
		}

		if ( 'https://evil.example/api' !== W3TC_API2_URL ) {
			$this->markTestSkipped( 'W3TC_API2_URL already defined to a different value.' );
		}

		$this->assertSame( 'https://api2.w3-edge.com', Util_Environment::get_api_base_url() );
	}

	/**
	 * Absent PageSpeed override uses the built-in Google URL.
	 *
	 * @since 2.10.6
	 */
	public function test_get_pagespeed_url_absent_constant_uses_default() {
		if ( \defined( 'W3TC_PAGESPEED_API_URL' ) ) {
			$this->markTestSkipped( 'W3TC_PAGESPEED_API_URL already defined in this process.' );
		}

		$api = new PageSpeed_Api();
		$this->assertSame(
			'https://www.googleapis.com/pagespeedonline/v5/runPagespeed',
			$api->get_pagespeed_url()
		);
	}

	/**
	 * Approved googleapis override is preserved.
	 *
	 * @since 2.10.6
	 */
	public function test_get_pagespeed_url_accepts_googleapis_override() {
		if ( ! \defined( 'W3TC_PAGESPEED_API_URL' ) ) {
			\define( 'W3TC_PAGESPEED_API_URL', 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed' );
		}

		if ( 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed' !== W3TC_PAGESPEED_API_URL ) {
			$this->markTestSkipped( 'W3TC_PAGESPEED_API_URL already defined to a different value.' );
		}

		$api = new PageSpeed_Api();
		$this->assertSame( W3TC_PAGESPEED_API_URL, $api->get_pagespeed_url() );
	}

	/**
	 * Rejected PageSpeed URL shapes fall back (helper contract used by
	 * get_pagespeed_url).
	 *
	 * @since 2.10.6
	 */
	public function test_pagespeed_allowlist_rejects_foreign_hosts() {
		$this->assertFalse(
			Util_Url::is_https_host_allowlisted(
				'https://evil.example/pagespeed',
				array( 'googleapis.com' ),
				array( '.googleapis.com' )
			)
		);
		$this->assertTrue(
			Util_Url::is_https_host_allowlisted(
				'https://www.googleapis.com/pagespeedonline/v5/runPagespeed',
				array( 'googleapis.com' ),
				array( '.googleapis.com' )
			)
		);
	}

	/**
	 * NOTICE_FEED production default remains allowlisted; foreign hosts are not.
	 *
	 * @since 2.10.6
	 */
	public function test_notice_feed_allowlist_matches_call_site_policy() {
		$this->assertTrue(
			Util_Url::is_https_host_allowlisted(
				W3TC_NOTICE_FEED,
				array( 'w3-edge.com' ),
				array( '.w3-edge.com' )
			)
		);
		$this->assertFalse(
			Util_Url::is_https_host_allowlisted(
				'http://evil.example/notices',
				array( 'w3-edge.com' ),
				array( '.w3-edge.com' )
			)
		);
	}

	/**
	 * License base URL allowlist still accepts the configured production host.
	 *
	 * @since 2.10.6
	 */
	public function test_license_api_base_url_uses_shared_allowlist() {
		$ref    = new \ReflectionClass( Licensing_Core::class );
		$method = $ref->getMethod( '_license_api_base_url' );
		$method->setAccessible( true );

		$result = $method->invoke( null );
		$this->assertNotFalse( $result );
		$this->assertTrue(
			Util_Url::is_https_host_allowlisted( $result, array( 'w3-edge.com' ), array( '.w3-edge.com' ) )
		);
	}

	/**
	 * TransparentCDN auth posts only when the authorization constant is
	 * allowlisted; requests disable redirect following.
	 *
	 * @since 2.10.6
	 */
	public function test_transparentcdn_auth_uses_allowlisted_url_without_redirects() {
		require_once W3TC_DIR . '/Cdnfsd_TransparentCDN_Engine.php';

		$this->assertTrue(
			Util_Url::is_https_host_allowlisted(
				W3TC_CDN_TRANSPARENTCDN_AUTHORIZATION_URL,
				array( 'transparentcdn.com' ),
				array( '.transparentcdn.com' )
			)
		);

		\add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				unset( $preempt );
				$this->http_urls[] = $url;
				$this->http_args[] = $args;
				return array(
					'headers'  => array(),
					'body'     => '{"access_token":"tok"}',
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
				);
			},
			10,
			3
		);

		$api = new Cdn_TransparentCDN_Api(
			array(
				'company_id'    => 'co-1',
				'client_id'     => 'id-1',
				'client_secret' => 'sec-1',
			)
		);

		$this->assertTrue( $api->_get_token() );
		$this->assertNotEmpty( $this->http_urls );
		$this->assertSame( W3TC_CDN_TRANSPARENTCDN_AUTHORIZATION_URL, $this->http_urls[0] );
		$this->assertSame( 0, $this->http_args[0]['redirection'] );
	}

	/**
	 * TransparentCDN purge URL template resolves to an allowlisted host and
	 * refuses foreign hosts before credentialed POSTs.
	 *
	 * @since 2.10.6
	 */
	public function test_transparentcdn_purge_url_policy() {
		require_once W3TC_DIR . '/Cdnfsd_TransparentCDN_Engine.php';

		$allowed = \sprintf( W3TC_CDN_TRANSPARENTCDN_PURGE_URL, 'co-1' );
		$this->assertTrue(
			Util_Url::is_https_host_allowlisted(
				$allowed,
				array( 'transparentcdn.com' ),
				array( '.transparentcdn.com' )
			)
		);

		$this->assertFalse(
			Util_Url::is_https_host_allowlisted(
				'https://evil.example/v1/companies/co-1/invalidate/',
				array( 'transparentcdn.com' ),
				array( '.transparentcdn.com' )
			)
		);
	}
}
