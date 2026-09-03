<?php
/**
 * File: class-w3tc-minify-precache-url-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.6
 */

declare( strict_types = 1 );

use W3TC\Minify_MinifiedFileRequestHandler;
use W3TC\Util_Http;

/**
 * Class: W3tc_Minify_Precache_Url_Test
 *
 * Regression coverage for minify precache / ID-generation URL checks
 * and download redirect revalidation.
 *
 * @since 2.10.6
 */
class W3tc_Minify_Precache_Url_Test extends WP_UnitTestCase {

	/**
	 * Cleanup HTTP mocks between tests.
	 *
	 * @since 2.10.6
	 */
	public function tearDown(): void {
		\remove_all_filters( 'pre_http_request' );
		parent::tearDown();
	}

	/**
	 * Precache refuses destinations outside the outbound allow policy
	 * without issuing an HTTP request.
	 *
	 * @since 2.10.6
	 */
	public function test_precache_file_refuses_disallowed_urls_without_http() {
		$http_calls = 0;
		\add_filter(
			'pre_http_request',
			static function () use ( &$http_calls ) {
				++$http_calls;
				return new \WP_Error( 'unexpected', 'HTTP should not run' );
			},
			10,
			3
		);

		$handler = new Minify_MinifiedFileRequestHandler();
		$denied  = array(
			'http://127.0.0.1/evil.css',
			'http://10.1.2.3/evil.css',
			'http://169.254.169.254/latest/meta-data/',
			'file:///tmp/x.css',
			'gopher://example.com/1',
		);

		foreach ( $denied as $url ) {
			$this->assertFalse(
				$handler->_precache_file( $url, 'css' ),
				'Expected precache refusal for ' . $url
			);
		}

		$this->assertSame( 0, $http_calls );
	}

	/**
	 * Approved same-host URLs may proceed to download.
	 *
	 * @since 2.10.6
	 */
	public function test_precache_file_allows_self_host_download() {
		$url      = \home_url( '/wp-content/themes/twentytwentyfour/style.css' );
		$tmp_body = 'body{color:red}';
		\add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $request_url ) use ( $url, $tmp_body ) {
				unset( $preempt, $args );
				if ( $request_url === $url ) {
					return array(
						'headers'  => array(),
						'body'     => $tmp_body,
						'response' => array(
							'code'    => 200,
							'message' => 'OK',
						),
					);
				}
				return new \WP_Error( 'unexpected_url', $request_url );
			},
			10,
			3
		);

		$handler = new Minify_MinifiedFileRequestHandler();
		$result  = $handler->_precache_file( $url, 'css' );

		$this->assertNotFalse( $result );
		$this->assertTrue( \is_object( $result ) );
		$this->assertFileExists( $result->filepath );
		$this->assertSame( $tmp_body, \file_get_contents( $result->filepath ) );

		@\unlink( $result->filepath );
	}

	/**
	 * Download follows a public redirect hop and refuses a private
	 * Location target.
	 *
	 * @since 2.10.6
	 */
	public function test_download_revalidates_redirect_destination() {
		$tmp   = \sys_get_temp_dir() . '/w3tc-precache-redirect-' . \uniqid( '', true ) . '.css';
		$start = \home_url( '/redirect-start.css' );

		\add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $request_url ) use ( $start ) {
				unset( $preempt, $args );
				if ( $request_url === $start ) {
					return array(
						'headers'  => array( 'location' => 'http://127.0.0.1/secret.css' ),
						'body'     => '',
						'response' => array(
							'code'    => 302,
							'message' => 'Found',
						),
					);
				}

				return new \WP_Error( 'should_not_follow_private', $request_url );
			},
			10,
			3
		);

		$this->assertFalse( Util_Http::download( $start, $tmp ) );
		$this->assertFileDoesNotExist( $tmp );
	}

	/**
	 * Download accepts a same-policy redirect and writes the final body.
	 *
	 * @since 2.10.6
	 */
	public function test_download_follows_allowed_redirect() {
		$tmp   = \sys_get_temp_dir() . '/w3tc-precache-ok-redirect-' . \uniqid( '', true ) . '.css';
		$start = \home_url( '/redirect-ok-start.css' );
		$final = \home_url( '/redirect-ok-final.css' );
		$body  = '/* ok */';

		\add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $request_url ) use ( $start, $final, $body ) {
				unset( $preempt, $args );
				if ( $request_url === $start ) {
					return array(
						'headers'  => array( 'location' => $final ),
						'body'     => '',
						'response' => array(
							'code'    => 302,
							'message' => 'Found',
						),
					);
				}
				if ( $request_url === $final ) {
					return array(
						'headers'  => array(),
						'body'     => $body,
						'response' => array(
							'code'    => 200,
							'message' => 'OK',
						),
					);
				}
				return new \WP_Error( 'unexpected_url', $request_url );
			},
			10,
			3
		);

		$this->assertTrue( Util_Http::download( $start, $tmp ) );
		$this->assertFileExists( $tmp );
		$this->assertSame( $body, \file_get_contents( $tmp ) );
		@\unlink( $tmp );
	}

	/**
	 * ID generation refuses remote sources outside the allow policy.
	 *
	 * @since 2.10.6
	 */
	public function test_generate_id_refuses_disallowed_remote_source() {
		$http_calls = 0;
		\add_filter(
			'pre_http_request',
			static function () use ( &$http_calls ) {
				++$http_calls;
				return new \WP_Error( 'unexpected', 'HTTP should not run' );
			},
			10,
			3
		);

		$source                      = new \stdClass();
		$source->filepath            = '';
		$source->minifyOptions       = array(
			'prependRelativePath' => 'http://127.0.0.1/evil.css',
		);

		$handler = new Minify_MinifiedFileRequestHandler();
		$this->assertFalse( $handler->_generate_id( array( $source ), 'css' ) );
		$this->assertSame( 0, $http_calls );
	}
}
