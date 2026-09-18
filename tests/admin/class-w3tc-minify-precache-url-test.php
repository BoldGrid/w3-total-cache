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
use W3TC\Minify_AutoJs;
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
	 * Cache files created by a test.
	 *
	 * @var array
	 */
	private $cache_files = array();

	/**
	 * Cleanup HTTP mocks between tests.
	 *
	 * @since 2.10.6
	 */
	public function tearDown(): void {
		\remove_all_filters( 'pre_http_request' );
		foreach ( $this->cache_files as $cache_file ) {
			@\unlink( $cache_file );
		}
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
	 * HTTPS JavaScript from a public destination may be cached.
	 *
	 * @since X.X.X
	 */
	public function test_precache_file_allows_https_public_javascript() {
		$url          = 'https://8.8.8.8/assets/app.js';
		$body         = 'window.w3tcExternal = true;';
		$request_args = array();
		\add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $request_url ) use ( $url, $body, &$request_args ) {
				unset( $preempt );
				$request_args = $args;
				if ( $request_url !== $url ) {
					return new \WP_Error( 'unexpected_url', $request_url );
				}

				return array(
					'headers'  => array( 'content-type' => 'application/javascript' ),
					'body'     => $body,
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
				);
			},
			10,
			3
		);

		$result = $this->handler_with_lifetime( DAY_IN_SECONDS )->_precache_file( $url, 'js' );

		$this->assertNotFalse( $result );
		$this->cache_files[] = $result->filepath;
		$this->assertSame( 'js', \pathinfo( $result->filepath, PATHINFO_EXTENSION ) );
		$this->assertSame( $body, \file_get_contents( $result->filepath ) );
		$this->assertSame( Minify_MinifiedFileRequestHandler::EXTERNAL_JS_TIMEOUT, $request_args['timeout'] );
		$this->assertSame(
			Minify_MinifiedFileRequestHandler::EXTERNAL_JS_MAX_SIZE + 1,
			$request_args['limit_response_size']
		);
		$this->assertSame( 0, $request_args['redirection'] );
	}

	/**
	 * JavaScript caching refuses non-HTTPS, non-public, and non-asset types.
	 *
	 * @since X.X.X
	 */
	public function test_precache_file_rejects_ineligible_javascript_requests() {
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

		$handler = $this->handler_with_lifetime( DAY_IN_SECONDS );

		$this->assertFalse( $handler->_precache_file( 'http://8.8.8.8/app.js', 'js' ) );
		$this->assertFalse( $handler->_precache_file( 'https://127.0.0.1/app.js', 'js' ) );
		$this->assertFalse( $handler->_precache_file( 'https://8.8.8.8/app.js', 'php' ) );
		$this->assertSame( 0, $http_calls );
	}

	/**
	 * JavaScript redirects remain limited to public destinations.
	 *
	 * @since X.X.X
	 */
	public function test_precache_file_rejects_non_public_javascript_redirect() {
		$url        = 'https://8.8.8.8/assets/redirect.js';
		$http_calls = 0;
		\add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $request_url ) use ( $url, &$http_calls ) {
				unset( $preempt, $args );
				++$http_calls;
				if ( $request_url !== $url ) {
					return new \WP_Error( 'unexpected_url', $request_url );
				}

				return array(
					'headers'  => array( 'location' => 'https://127.0.0.1/internal.js' ),
					'body'     => '',
					'response' => array(
						'code'    => 302,
						'message' => 'Found',
					),
				);
			},
			10,
			3
		);

		$result = $this->handler_with_lifetime( DAY_IN_SECONDS )->_precache_file( $url, 'js' );

		$this->assertFalse( $result );
		$this->assertSame( 1, $http_calls );
	}

	/**
	 * JavaScript redirects may not downgrade from HTTPS.
	 *
	 * @since X.X.X
	 */
	public function test_precache_file_rejects_http_javascript_redirect() {
		$this->assert_javascript_redirect_is_rejected(
			'https://8.8.8.8/assets/https-start.js',
			'http://8.8.8.8/assets/http-final.js'
		);
	}

	/**
	 * JavaScript redirects may not switch to another public host.
	 *
	 * @since X.X.X
	 */
	public function test_precache_file_rejects_cross_host_javascript_redirect() {
		$this->assert_javascript_redirect_is_rejected(
			'https://8.8.8.8/assets/host-start.js',
			'https://8.8.4.4/assets/host-final.js'
		);
	}

	/**
	 * JavaScript refreshes no less often than the maximum bounded interval.
	 *
	 * @since X.X.X
	 */
	public function test_precache_file_bounds_javascript_refresh_interval() {
		$url        = 'https://8.8.4.4/assets/refresh.js';
		$http_calls = 0;
		\add_filter(
			'pre_http_request',
			static function () use ( &$http_calls ) {
				++$http_calls;
				return array(
					'headers'  => array( 'content-type' => 'application/javascript' ),
					'body'     => 'window.refresh=' . $http_calls . ';',
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
				);
			},
			10,
			3
		);

		$handler = $this->handler_with_lifetime( PHP_INT_MAX );
		$first   = $handler->_precache_file( $url, 'js' );
		$this->assertNotFalse( $first );
		$this->cache_files[] = $first->filepath;

		\touch( $first->filepath, \time() - WEEK_IN_SECONDS - 10 );
		\clearstatcache( true, $first->filepath );
		$second = $handler->_precache_file( $url, 'js' );

		$this->assertNotFalse( $second );
		$this->assertSame( 2, $http_calls );
		$this->assertSame( 'window.refresh=2;', \file_get_contents( $second->filepath ) );
	}

	/**
	 * A failed refresh continues to use the last complete cached script.
	 *
	 * @since X.X.X
	 */
	public function test_precache_file_uses_stale_javascript_when_refresh_fails() {
		$url        = 'https://1.1.1.1/assets/stale.js';
		$cache_file = $this->cache_path( $url, 'js' );
		\wp_mkdir_p( \dirname( $cache_file ) );
		\file_put_contents( $cache_file, 'window.stale=true;' );
		\touch( $cache_file, \time() - HOUR_IN_SECONDS - 10 );

		\add_filter(
			'pre_http_request',
			static function () {
				return new \WP_Error( 'download_failed', 'Unavailable' );
			},
			10,
			3
		);

		$result = $this->handler_with_lifetime( 0 )->_precache_file( $url, 'js' );

		$this->assertNotFalse( $result );
		$this->assertSame( 'window.stale=true;', \file_get_contents( $result->filepath ) );
	}

	/**
	 * A zero configured lifetime still observes the one-hour floor.
	 *
	 * @since X.X.X
	 */
	public function test_precache_file_bounds_javascript_refresh_interval_floor() {
		$url        = 'https://1.0.0.1/assets/floor.js';
		$cache_file = $this->cache_path( $url, 'js' );
		\wp_mkdir_p( \dirname( $cache_file ) );
		\file_put_contents( $cache_file, 'window.floor=true;' );
		\touch( $cache_file, \time() - HOUR_IN_SECONDS + 60 );

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

		$result = $this->handler_with_lifetime( 0 )->_precache_file( $url, 'js' );

		$this->assertNotFalse( $result );
		$this->assertSame( 0, $http_calls );
		$this->assertSame( 'window.floor=true;', \file_get_contents( $result->filepath ) );
	}

	/**
	 * Invalid successful responses do not replace a stale script.
	 *
	 * @since X.X.X
	 *
	 * @dataProvider invalid_javascript_response_provider
	 *
	 * @param array  $headers Response headers.
	 * @param string $body    Response body.
	 */
	public function test_precache_file_retains_stale_javascript_for_invalid_200_response( $headers, $body ) {
		$url        = 'https://9.9.9.9/assets/invalid.js';
		$cache_file = $this->cache_path( $url, 'js' );
		\wp_mkdir_p( \dirname( $cache_file ) );
		\file_put_contents( $cache_file, 'window.previous=true;' );
		\touch( $cache_file, \time() - HOUR_IN_SECONDS - 10 );

		\add_filter(
			'pre_http_request',
			static function () use ( $headers, $body ) {
				return array(
					'headers'  => $headers,
					'body'     => $body,
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
				);
			},
			10,
			3
		);

		$result = $this->handler_with_lifetime( 0 )->_precache_file( $url, 'js' );

		$this->assertNotFalse( $result );
		$this->assertSame( 'window.previous=true;', \file_get_contents( $result->filepath ) );
	}

	/**
	 * Invalid JavaScript response cases.
	 *
	 * @since X.X.X
	 *
	 * @return array
	 */
	public static function invalid_javascript_response_provider() {
		return array(
			'empty response' => array(
				array( 'content-type' => 'application/javascript' ),
				'',
			),
			'HTML response'  => array(
				array( 'content-type' => 'text/html; charset=UTF-8' ),
				'<!doctype html><title>Error</title>',
			),
			'oversized response' => array(
				array( 'content-type' => 'application/javascript' ),
				\str_repeat( 'x', Minify_MinifiedFileRequestHandler::EXTERNAL_JS_MAX_SIZE + 1 ),
			),
		);
	}

	/**
	 * Auto Minify leaves the original script tag when initial caching fails.
	 *
	 * @since X.X.X
	 */
	public function test_auto_js_preserves_original_url_when_precache_fails() {
		$url    = 'https://8.8.8.8/assets/unavailable.js';
		$buffer = '<html><head><script src="' . $url . '"></script></head><body></body></html>';
		$config = new class() {
			public function get_boolean( $key ) {
				return false;
			}

			public function get_array( $key ) {
				return array();
			}

			public function get_string( $key ) {
				return 'blocking';
			}
		};
		$helpers = new class() {
			public function is_file_for_minification( $url, $file ) {
				return 'url';
			}

			public function precache_external_script( $url ) {
				return false;
			}
		};

		$minifier = new Minify_AutoJs( $config, $buffer, $helpers );

		$this->assertSame( $buffer, $minifier->execute() );
	}

	/**
	 * Auto Minify replaces an external script after caching succeeds.
	 *
	 * @since X.X.X
	 */
	public function test_auto_js_replaces_external_url_when_precache_succeeds() {
		$url    = 'https://8.8.8.8/assets/available.js';
		$buffer = '<html><head><script src="' . $url . '"></script></head><body></body></html>';
		$config = new class() {
			public function get_boolean( $key ) {
				return false;
			}

			public function getf_boolean( $key ) {
				return false;
			}

			public function get_array( $key ) {
				return array();
			}

			public function get_string( $key ) {
				return 'blocking';
			}
		};
		$helpers = new class() {
			public function is_file_for_minification( $url, $file ) {
				return 'url';
			}

			public function precache_external_script( $url ) {
				return true;
			}

			public function get_minify_url_for_files( $files, $type ) {
				return '/cache/external.js';
			}

			public function generate_script_tag( $url, $embed_type, $attributes ) {
				return '<script src="' . $url . '"></script>';
			}
		};

		$result = ( new Minify_AutoJs( $config, $buffer, $helpers ) )->execute();

		$this->assertStringNotContainsString( $url, $result );
		$this->assertStringContainsString( '<script src="/cache/external.js"></script>', $result );
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
	 * HTTPS redirect locations retain the request scheme on an HTTP site.
	 *
	 * @since X.X.X
	 *
	 * @dataProvider https_redirect_location_provider
	 *
	 * @param string $location Location header value.
	 * @param string $final    Expected final URL.
	 */
	public function test_precache_file_resolves_https_redirect_locations_without_downgrade( $location, $final ) {
		$start        = 'https://8.8.8.8/assets/start.js';
		$body         = 'window.redirected=true;';
		$request_urls = array();
		$server       = $_SERVER;
		$http_site    = static function () {
			return 'http://example.org';
		};

		\add_filter( 'pre_option_home', $http_site );
		\add_filter( 'pre_option_siteurl', $http_site );
		$_SERVER['HTTPS']       = 'off';
		$_SERVER['SERVER_PORT'] = '80';
		unset( $_SERVER['HTTP_FORWARDED'], $_SERVER['HTTP_X_FORWARDED_PROTO'] );

		try {
			$this->assertFalse( \W3TC\Util_Environment::is_https() );
			\add_filter(
				'pre_http_request',
				static function ( $preempt, $args, $request_url ) use ( $start, $final, $location, $body, &$request_urls ) {
					unset( $preempt, $args );
					$request_urls[] = $request_url;
					if ( $request_url === $start ) {
						return array(
							'headers'  => array( 'location' => $location ),
							'body'     => '',
							'response' => array(
								'code'    => 302,
								'message' => 'Found',
							),
						);
					}
					if ( $request_url === $final ) {
						return array(
							'headers'  => array( 'content-type' => 'application/javascript' ),
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

			$result = $this->handler_with_lifetime( DAY_IN_SECONDS )->_precache_file( $start, 'js' );

			$this->assertNotFalse( $result );
			$this->cache_files[] = $result->filepath;
			$this->assertSame( $body, \file_get_contents( $result->filepath ) );
			$this->assertSame( array( $start, $final ), $request_urls );
		} finally {
			$_SERVER = $server;
			\remove_filter( 'pre_option_home', $http_site );
			\remove_filter( 'pre_option_siteurl', $http_site );
		}
	}

	/**
	 * HTTPS redirect location cases.
	 *
	 * @since X.X.X
	 *
	 * @return array
	 */
	public static function https_redirect_location_provider() {
		return array(
			'protocol relative' => array(
				'//8.8.8.8/assets/protocol-relative.js',
				'https://8.8.8.8/assets/protocol-relative.js',
			),
			'root relative'     => array(
				'/assets/root-relative.js',
				'https://8.8.8.8/assets/root-relative.js',
			),
			'path relative'     => array(
				'path-relative.js',
				'https://8.8.8.8/assets/path-relative.js',
			),
			'absolute HTTPS'    => array(
				'https://8.8.8.8/assets/absolute.js',
				'https://8.8.8.8/assets/absolute.js',
			),
			'query only'        => array(
				'?cachebust=1',
				'https://8.8.8.8/assets/start.js?cachebust=1',
			),
			'parent relative'   => array(
				'../lib/parent-relative.js',
				'https://8.8.8.8/lib/parent-relative.js',
			),
		);
	}

	/**
	 * Versioned remote URLs still generate minify IDs.
	 *
	 * @since X.X.X
	 *
	 * @dataProvider versioned_remote_source_provider
	 *
	 * @param string $url  Remote asset URL.
	 * @param string $type Group type.
	 * @param string $body Response body.
	 */
	public function test_generate_id_accepts_versioned_remote_sources( $url, $type, $body ) {
		\add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $request_url ) use ( $url, $type, $body ) {
				unset( $preempt, $args );
				if ( $request_url !== $url ) {
					return new \WP_Error( 'unexpected_url', $request_url );
				}

				return array(
					'headers'  => array(
						'content-type' => 'js' === $type ? 'application/javascript' : 'text/css',
					),
					'body'     => $body,
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
				);
			},
			10,
			3
		);

		$source                = new \stdClass();
		$source->filepath      = '';
		$source->minifyOptions = array(
			'prependRelativePath' => $url,
		);

		$handler = $this->handler_with_lifetime( DAY_IN_SECONDS );
		$id      = $handler->_generate_id( array( $source ), $type );

		$this->assertNotFalse( $id );
		$this->assertNotSame( '', $id );
	}

	/**
	 * Versioned remote source cases.
	 *
	 * @since X.X.X
	 *
	 * @return array
	 */
	public static function versioned_remote_source_provider() {
		return array(
			'js with query'  => array(
				'https://8.8.8.8/jquery.min.js?ver=1.2.3',
				'js',
				'console.log(\"ok\");',
			),
			'css with query' => array(
				'https://8.8.8.8/theme.css?ver=2',
				'css',
				'body{color:red;}',
			),
		);
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

	/**
	 * Creates a handler with an isolated lifetime configuration.
	 *
	 * @since X.X.X
	 *
	 * @param int $lifetime Configured cache lifetime.
	 *
	 * @return Minify_MinifiedFileRequestHandler
	 */
	private function handler_with_lifetime( $lifetime ) {
		$handler  = new Minify_MinifiedFileRequestHandler();
		$config   = new class( $lifetime ) {
			private $lifetime;

			public function __construct( $lifetime ) {
				$this->lifetime = $lifetime;
			}

			public function get_integer( $key ) {
				return $this->lifetime;
			}

			public function get_string( $key ) {
				return '';
			}

			public function get_boolean( $key ) {
				return false;
			}

			public function get( $key ) {
				return null;
			}
		};
		$property = new \ReflectionProperty( Minify_MinifiedFileRequestHandler::class, '_config' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		$property->setValue( $handler, $config );

		return $handler;
	}

	/**
	 * Returns and tracks the cache path for a remote asset.
	 *
	 * @since X.X.X
	 *
	 * @param string $url  Asset URL.
	 * @param string $type Asset type.
	 *
	 * @return string
	 */
	private function cache_path( $url, $type ) {
		$cache_file         = sprintf(
			'%s/minify_%s.%s',
			\W3TC\Util_Environment::cache_blog_dir( 'minify' ),
			\md5( $url ),
			$type
		);
		$this->cache_files[] = $cache_file;

		return $cache_file;
	}

	/**
	 * Verifies that a JavaScript redirect stops after its first response.
	 *
	 * @since X.X.X
	 *
	 * @param string $start    Initial URL.
	 * @param string $location Redirect target.
	 *
	 * @return void
	 */
	private function assert_javascript_redirect_is_rejected( $start, $location ) {
		$http_calls = 0;
		\add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $request_url ) use ( $start, $location, &$http_calls ) {
				unset( $preempt, $args );
				++$http_calls;
				if ( $request_url !== $start ) {
					return new \WP_Error( 'unexpected_url', $request_url );
				}

				return array(
					'headers'  => array( 'location' => $location ),
					'body'     => '',
					'response' => array(
						'code'    => 302,
						'message' => 'Found',
					),
				);
			},
			10,
			3
		);

		$result = $this->handler_with_lifetime( DAY_IN_SECONDS )->_precache_file( $start, 'js' );

		$this->assertFalse( $result );
		$this->assertSame( 1, $http_calls );
	}
}
