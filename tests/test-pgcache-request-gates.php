<?php
/**
 * Standalone regression test for page-cache URI and response storage gates.
 *
 * Run with: php tests/test-pgcache-request-gates.php
 *
 * Exit code 0 = all pass, non-zero = failures.
 *
 * @package W3TC\Tests
 * @since   2.11.1
 */

namespace {
	if ( \realpath( __FILE__ ) !== \realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
		return;
	}

	require_once \dirname( __DIR__ ) . '/Util_Reflection.php';
}

namespace W3TC {
	if ( ! \class_exists( __NAMESPACE__ . '\\Dispatcher', false ) ) {
		/**
		 * Config stub for the page-cache request-gate test.
		 */
		class PgCacheGateTestConfigStub {
			/**
			 * Engine returned for pgcache.engine.
			 *
			 * @var string
			 */
			public $engine = 'file';

			/**
			 * Configured arrays, keyed by config name.
			 *
			 * @var array
			 */
			public $arrays = array();

			/**
			 * Boolean config values.
			 *
			 * @param string $key Config key.
			 * @return bool
			 */
			public function get_boolean( $key ) {
				unset( $key );
				return false;
			}

			/**
			 * Integer config values.
			 *
			 * @param string $key Config key.
			 * @return int
			 */
			public function get_integer( $key ) {
				unset( $key );
				return 0;
			}

			/**
			 * String config values.
			 *
			 * @param string $key Config key.
			 * @return string
			 */
			public function get_string( $key ) {
				if ( 'pgcache.engine' === $key ) {
					return $this->engine;
				}

				return '';
			}

			/**
			 * Array config values.
			 *
			 * @param string $key Config key.
			 * @return array
			 */
			public function get_array( $key ) {
				return isset( $this->arrays[ $key ] ) ? $this->arrays[ $key ] : array();
			}
		}

		/**
		 * Dispatcher stub for the page-cache request-gate test.
		 */
		class Dispatcher {
			/**
			 * Active config stub.
			 *
			 * @var PgCacheGateTestConfigStub
			 */
			public static $config;

			/**
			 * Returns the active config stub.
			 *
			 * @return PgCacheGateTestConfigStub
			 */
			public static function config() {
				return self::$config;
			}

			/**
			 * Component lookup stub.
			 *
			 * @param string $name Component name.
			 * @return null
			 */
			public static function component( $name ) {
				unset( $name );
				return null;
			}
		}

		/**
		 * Environment stub for the page-cache request-gate test.
		 */
		class Util_Environment {
			/**
			 * Host used by the grabber constructor.
			 *
			 * @return string
			 */
			public static function host_port() {
				return 'example.com';
			}

			/**
			 * HTTPS is off in this test.
			 *
			 * @return bool
			 */
			public static function is_https() {
				return false;
			}

			/**
			 * Preview mode is off in this test.
			 *
			 * @return bool
			 */
			public static function is_preview_mode() {
				return false;
			}

			/**
			 * Path expressions are used as written.
			 *
			 * @param string $path Path expression.
			 * @return string
			 */
			public static function parse_path( $path ) {
				return $path;
			}

			/**
			 * REST cache is off in this test.
			 *
			 * @param mixed $config Config object.
			 * @return bool
			 */
			public static function is_rest_cache_active( $config = null ) {
				unset( $config );
				return false;
			}

			/**
			 * No URI in this test is a REST request.
			 *
			 * @param string $url Request URI.
			 * @return bool
			 */
			public static function is_rest_request( $url ) {
				unset( $url );
				return false;
			}

			/**
			 * Quote a literal for a regular expression.
			 *
			 * @param string $string_value Value to quote.
			 * @param string $delimiter    Delimiter.
			 * @return string
			 */
			public static function preg_quote( $string_value, $delimiter = '~' ) {
				return \preg_quote( $string_value, $delimiter );
			}
		}
	}
}

namespace {
	if ( ! \function_exists( 'w3tc_apply_filters' ) ) {
		/**
		 * Filter stub. Returns the value unchanged.
		 *
		 * @param string $tag   Filter name.
		 * @param mixed  $value Filter value.
		 * @return mixed
		 */
		function w3tc_apply_filters( $tag, $value ) {
			unset( $tag );
			return $value;
		}
	}

	if ( ! \function_exists( 'is_single' ) ) {
		/**
		 * WordPress conditional stub.
		 *
		 * @return bool
		 */
		function is_single() {
			return false;
		}
	}

	require_once __DIR__ . '/../PgCache_ContentGrabber.php';

	$w3tc_pgcache_gate_failures = 0;

	/**
	 * Record one assertion.
	 *
	 * @param string $label    Case label.
	 * @param mixed  $expected Expected value.
	 * @param mixed  $actual   Actual value.
	 * @return void
	 */
	function pgcg_assert_same( $label, $expected, $actual ) {
		global $w3tc_pgcache_gate_failures;

		if ( $expected === $actual ) {
			echo "[PASS] $label\n";
			return;
		}

		++$w3tc_pgcache_gate_failures;
		echo "[FAIL] $label\n";
		echo '  Expected: ' . \var_export( $expected, true ) . "\n";
		echo '  Actual:   ' . \var_export( $actual, true ) . "\n";
	}

	/**
	 * Build a grabber for one request URI and page-cache engine.
	 *
	 * @param string $request_uri Request URI, including any query string.
	 * @param string $engine      Page cache engine.
	 * @return \W3TC\PgCache_ContentGrabber
	 */
	function pgcg_new_grabber( $request_uri, $engine = 'file' ) {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI']    = $request_uri;
		$_COOKIE                   = array();

		$config         = new \W3TC\PgCacheGateTestConfigStub();
		$config->engine = $engine;
		$config->arrays = array(
			'pgcache.accept.files' => array(
				'wp-comments-popup.php',
				'wp-links-opml.php',
				'wp-locations.php',
			),
			'pgcache.reject.uri'   => array(
				'wp-.*\.php',
				'index\.php',
			),
		);

		\W3TC\Dispatcher::$config = $config;

		$grabber = new \W3TC\PgCache_ContentGrabber();

		$caching = new \ReflectionProperty( \W3TC\PgCache_ContentGrabber::class, '_caching' );
		\W3TC\Util_Reflection::set_accessible( $caching );
		$caching->setValue( $grabber, true );

		return $grabber;
	}

	/**
	 * Invoke the private write gate.
	 *
	 * @param object $grabber Grabber under test.
	 * @param string $buffer  Response body.
	 * @param array  $headers Response headers.
	 * @return mixed
	 */
	function pgcg_can_write_cache( $grabber, $buffer, $headers ) {
		$method = new \ReflectionMethod( \W3TC\PgCache_ContentGrabber::class, '_can_write_cache' );
		\W3TC\Util_Reflection::set_accessible( $method );
		return $method->invoke( $grabber, $buffer, $headers );
	}

	/**
	 * Response headers with one Cache-Control value.
	 *
	 * @param string $cache_control Cache-Control value, or an empty string for none.
	 * @return array
	 */
	function pgcg_headers( $cache_control = '' ) {
		if ( '' === $cache_control ) {
			return array(
				'kv'    => array(),
				'plain' => array(),
			);
		}

		return array(
			'kv'    => array(
				'cache-control' => $cache_control,
			),
			'plain' => array(
				array(
					'name'  => 'Cache-Control',
					'value' => $cache_control,
				),
			),
		);
	}

	/**
	 * Response headers with repeated fields, matching headers_list() order.
	 *
	 * The map keeps only the last value of a repeated name.
	 *
	 * @param array $fields Header fields, each with name and value.
	 * @return array
	 */
	function pgcg_headers_plain( $fields ) {
		$kv    = array();
		$plain = array();

		foreach ( $fields as $field ) {
			$plain[] = array(
				'name'  => $field['name'],
				'value' => $field['value'],
			);
			$kv[ \strtolower( $field['name'] ) ] = $field['value'];
		}

		return array(
			'kv'    => $kv,
			'plain' => $plain,
		);
	}

	$html = '<html><body>ok</body></html>';

	$login_query = pgcg_new_grabber( '/wp-login.php?action=rp&utm_source=wp-locations.php' );
	pgcg_assert_same(
		'Accepted file name only in the query does not pass accept',
		false,
		$login_query->_passed_accept_files()
	);
	pgcg_assert_same(
		'Login request is still rejected when the query contains an accepted file name',
		false,
		$login_query->_passed_reject_uri()
	);
	pgcg_assert_same(
		'Login request with an accepted file name only in the query is not stored',
		false,
		pgcg_can_write_cache( $login_query, $html, pgcg_headers( 'no-cache, must-revalidate, max-age=0, no-store, private' ) )
	);

	$login_plain = pgcg_new_grabber( '/wp-login.php?action=rp' );
	pgcg_assert_same(
		'Login request without an accepted file name is rejected',
		false,
		$login_plain->_passed_reject_uri()
	);
	pgcg_assert_same(
		'Login request without an accepted file name does not pass accept',
		false,
		$login_plain->_passed_accept_files()
	);

	$accepted = pgcg_new_grabber( '/wp-locations.php?utm_source=newsletter' );
	pgcg_assert_same(
		'Accepted file path still passes accept when a query is present',
		true,
		$accepted->_passed_accept_files()
	);
	pgcg_assert_same(
		'Accepted file response with a public cache policy can be written',
		true,
		pgcg_can_write_cache( $accepted, $html, pgcg_headers( 'max-age=60, public' ) )
	);

	$accepted_subdir = pgcg_new_grabber( '/blog/wp-links-opml.php' );
	pgcg_assert_same(
		'Accepted file in a subdirectory still passes accept',
		true,
		$accepted_subdir->_passed_accept_files()
	);

	$near_miss = pgcg_new_grabber( '/wp-locations.php.bak' );
	pgcg_assert_same(
		'Accepted file name does not match a longer path segment',
		false,
		$near_miss->_passed_accept_files()
	);

	$ordinary = pgcg_new_grabber( '/hello-world/', 'file_generic' );
	pgcg_assert_same(
		'Ordinary page is not rejected',
		true,
		$ordinary->_passed_reject_uri()
	);
	pgcg_assert_same(
		'Ordinary page does not pass accept',
		false,
		$ordinary->_passed_accept_files()
	);
	pgcg_assert_same(
		'Enhanced mode can still write an ordinary page',
		true,
		pgcg_can_write_cache( $ordinary, $html, pgcg_headers() )
	);

	$ordinary_query = pgcg_new_grabber( '/hello-world/?utm_source=wp-locations.php', 'file_generic' );
	pgcg_assert_same(
		'Accepted file name only in an ordinary query does not pass accept',
		false,
		$ordinary_query->_passed_accept_files()
	);
	pgcg_assert_same(
		'Ordinary page is not rejected when the query contains an accepted file name',
		true,
		$ordinary_query->_passed_reject_uri()
	);

	$index = pgcg_new_grabber( '/index.php' );
	pgcg_assert_same(
		'Default reject list still rejects index.php',
		false,
		$index->_passed_reject_uri()
	);

	$cron = pgcg_new_grabber( '/wp-cron.php' );
	pgcg_assert_same(
		'Default reject list still rejects wp-cron.php',
		false,
		$cron->_passed_reject_uri()
	);

	$reset = pgcg_new_grabber( '/hello-world/' );
	$_COOKIE['wp-resetpass-abc'] = 'user:key';
	pgcg_assert_same(
		'wp-resetpass cookie is rejected',
		false,
		$reset->_check_cookies()
	);

	$activate = pgcg_new_grabber( '/hello-world/' );
	$_COOKIE['wp-activate-abc'] = 'user:key';
	pgcg_assert_same(
		'wp-activate cookie is rejected',
		false,
		$activate->_check_cookies()
	);

	$other_cookie = pgcg_new_grabber( '/hello-world/' );
	$_COOKIE['wordpress_test_cookie'] = 'WP Cookie check';
	$_COOKIE['session']               = '1';
	pgcg_assert_same(
		'Unrelated cookies are still allowed',
		true,
		$other_cookie->_check_cookies()
	);

	$postpass = pgcg_new_grabber( '/hello-world/' );
	$_COOKIE['wp-postpass_abc'] = '1';
	pgcg_assert_same(
		'wp-postpass cookie is still rejected',
		false,
		$postpass->_check_cookies()
	);

	$closed = pgcg_new_grabber( '/hello-world/' );
	$caching = new \ReflectionProperty( \W3TC\PgCache_ContentGrabber::class, '_caching' );
	\W3TC\Util_Reflection::set_accessible( $caching );
	$caching->setValue( $closed, false );
	pgcg_assert_same(
		'A request marked not cacheable is not stored',
		false,
		pgcg_can_write_cache( $closed, $html, pgcg_headers() )
	);

	$policy = pgcg_new_grabber( '/hello-world/' );
	foreach ( array( 'no-store', 'no-cache', 'private', 'No-Store', 'private, must-revalidate', 'no-cache="Set-Cookie"' ) as $directive ) {
		pgcg_assert_same(
			'Cache-Control ' . $directive . ' is not stored',
			false,
			pgcg_can_write_cache( $policy, $html, pgcg_headers( $directive ) )
		);
	}

	pgcg_assert_same(
		'Public max-age response can still be written',
		true,
		pgcg_can_write_cache( $policy, $html, pgcg_headers( 'max-age=120, public' ) )
	);
	pgcg_assert_same(
		'public must-revalidate can still be written',
		true,
		pgcg_can_write_cache( $policy, $html, pgcg_headers( 'public, must-revalidate, proxy-revalidate' ) )
	);
	pgcg_assert_same(
		'Shared store policy allows must-revalidate and rejects no-store',
		array( false, true, true ),
		array(
			\W3TC\Util_CacheControl::disallows_shared_store( 'public, must-revalidate, proxy-revalidate' ),
			\W3TC\Util_CacheControl::disallows_shared_store( 'no-cache="Set-Cookie"' ),
			\W3TC\Util_CacheControl::disallows_shared_store( 'No-Store' ),
		)
	);
	$always_cached = (string) \file_get_contents( \dirname( __DIR__ ) . '/Extension_AlwaysCached_Plugin.php' );
	pgcg_assert_same(
		'AlwaysCached prefetch uses the shared Cache-Control store policy',
		true,
		false !== \strpos( $always_cached, 'Util_CacheControl::disallows_shared_store' )
			&& false === \strpos( $always_cached, "'must-revalidate'" )
	);
	pgcg_assert_same(
		'Response without Cache-Control can still be written',
		true,
		pgcg_can_write_cache( $policy, $html, pgcg_headers() )
	);
	pgcg_assert_same(
		'Repeated Cache-Control fields are combined before the store check',
		false,
		pgcg_can_write_cache(
			$policy,
			$html,
			pgcg_headers_plain(
				array(
					array(
						'name'  => 'Cache-Control',
						'value' => 'no-cache',
					),
					array(
						'name'  => 'Cache-Control',
						'value' => 'max-age=60',
					),
				)
			)
		)
	);
	pgcg_assert_same(
		'Repeated public Cache-Control fields can still be written',
		true,
		pgcg_can_write_cache(
			$policy,
			$html,
			pgcg_headers_plain(
				array(
					array(
						'name'  => 'Cache-Control',
						'value' => 'public',
					),
					array(
						'name'  => 'CACHE-CONTROL',
						'value' => 'max-age=60',
					),
				)
			)
		)
	);
	pgcg_assert_same(
		'A map-only Cache-Control value is still honored',
		false,
		pgcg_can_write_cache(
			$policy,
			$html,
			array(
				'kv'    => array(
					'cache-control' => 'private',
				),
				'plain' => array(),
			)
		)
	);

	$segments = pgcg_new_grabber( '/shopping-cart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( 'cart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.accept.files'] = array( 'foo|bar' );
	pgcg_assert_same(
		'Anchored reject expression does not match inside a longer segment',
		true,
		$segments->_passed_reject_uri()
	);

	$cart = pgcg_new_grabber( '/shop/cart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( 'cart' );
	pgcg_assert_same(
		'Anchored reject expression matches a path segment',
		false,
		$cart->_passed_reject_uri()
	);

	$either = pgcg_new_grabber( '/bar' );
	\W3TC\Dispatcher::$config->arrays['pgcache.accept.files'] = array( 'foo|bar' );
	pgcg_assert_same(
		'Anchored alternation matches either side on the path',
		true,
		$either->_passed_accept_files()
	);

	$either_query = pgcg_new_grabber( '/hello-world/?file=bar' );
	\W3TC\Dispatcher::$config->arrays['pgcache.accept.files'] = array( 'foo|bar' );
	pgcg_assert_same(
		'Anchored alternation does not match a query value',
		false,
		$either_query->_passed_accept_files()
	);

	$either_longer = pgcg_new_grabber( '/foobar' );
	\W3TC\Dispatcher::$config->arrays['pgcache.accept.files'] = array( 'foo|bar' );
	pgcg_assert_same(
		'Anchored alternation does not match inside a longer segment',
		false,
		$either_longer->_passed_accept_files()
	);

	$mixed = pgcg_new_grabber( '/cart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '/catalog/|cart' );
	pgcg_assert_same(
		'Directory alternative and segment alternative both match',
		false,
		$mixed->_passed_reject_uri()
	);

	$mixed_child = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '/catalog/|cart' );
	pgcg_assert_same(
		'Directory alternative still matches a descendant beside another alternative',
		false,
		$mixed_child->_passed_reject_uri()
	);

	$mixed_nested = pgcg_new_grabber( '/shop/catalog/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( 'cart|/catalog/' );
	pgcg_assert_same(
		'Nested directory alternative matches when it is not the first branch',
		false,
		$mixed_nested->_passed_reject_uri()
	);

	$mixed_near = pgcg_new_grabber( '/shopping-cart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( 'cart|/catalog/' );
	pgcg_assert_same(
		'Segment alternative does not match inside a longer segment',
		true,
		$mixed_near->_passed_reject_uri()
	);

	$mixed_prefix = pgcg_new_grabber( '/foobar' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( 'foo|bar/' );
	pgcg_assert_same(
		'A directory alternative does not drop the boundary on earlier alternatives',
		true,
		$mixed_prefix->_passed_reject_uri()
	);

	$catalog_child = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '/catalog/' );
	pgcg_assert_same(
		'Directory reject rule matches a descendant',
		false,
		$catalog_child->_passed_reject_uri()
	);

	$catalog_anchored = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog/' );
	pgcg_assert_same(
		'Anchored directory reject rule matches a descendant',
		false,
		$catalog_anchored->_passed_reject_uri()
	);

	$catalog_nested = pgcg_new_grabber( '/shop/catalog/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '/catalog/' );
	pgcg_assert_same(
		'Directory reject rule matches a nested directory',
		false,
		$catalog_nested->_passed_reject_uri()
	);

	$catalog_nested_anchored = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog/' );
	pgcg_assert_same(
		'Anchored directory reject rule stays at the root',
		true,
		$catalog_nested_anchored->_passed_reject_uri()
	);

	$catalog_prefix = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( 'catalog/' );
	pgcg_assert_same(
		'Directory reject rule with a trailing separator matches a nested descendant',
		false,
		$catalog_prefix->_passed_reject_uri()
	);

	$catalog_near = pgcg_new_grabber( '/catalog-extra/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '/catalog/' );
	pgcg_assert_same(
		'Directory reject rule does not match a longer segment',
		true,
		$catalog_near->_passed_reject_uri()
	);

	$catalog_query = pgcg_new_grabber( '/hello-world/?path=/catalog/item' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '/catalog/' );
	pgcg_assert_same(
		'Directory reject rule does not match a query value',
		true,
		$catalog_query->_passed_reject_uri()
	);

	$catalog_accept = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.accept.files'] = array( '/catalog/' );
	pgcg_assert_same(
		'Directory accept rule matches a descendant',
		true,
		$catalog_accept->_passed_accept_files()
	);

	$catalog_accept_nested = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.accept.files'] = array( '/catalog/' );
	pgcg_assert_same(
		'Directory accept rule matches a nested directory',
		true,
		$catalog_accept_nested->_passed_accept_files()
	);

	$grouped_child = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog(?:/|$)' );
	pgcg_assert_same(
		'Grouped directory reject rule matches a descendant',
		false,
		$grouped_child->_passed_reject_uri()
	);

	$grouped_exact = pgcg_new_grabber( '/catalog' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog(?:/|$)' );
	pgcg_assert_same(
		'Grouped directory reject rule matches the directory itself',
		false,
		$grouped_exact->_passed_reject_uri()
	);

	$grouped_near = pgcg_new_grabber( '/catalog-extra/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog(?:/|$)' );
	pgcg_assert_same(
		'Grouped directory reject rule does not match a longer segment',
		true,
		$grouped_near->_passed_reject_uri()
	);

	$grouped_anchored_nested = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog(?:/|$)' );
	pgcg_assert_same(
		'Grouped anchored directory reject rule stays at the root',
		true,
		$grouped_anchored_nested->_passed_reject_uri()
	);

	$grouped_nested = pgcg_new_grabber( '/shop/catalog/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?:/catalog/)' );
	pgcg_assert_same(
		'Grouped directory reject rule matches a nested directory',
		false,
		$grouped_nested->_passed_reject_uri()
	);

	$grouped_nested_child = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?:/catalog/)' );
	pgcg_assert_same(
		'Grouped directory reject rule matches a nested descendant',
		false,
		$grouped_nested_child->_passed_reject_uri()
	);

	$grouped_capture = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(/catalog/)' );
	pgcg_assert_same(
		'Capturing directory reject rule matches a nested descendant',
		false,
		$grouped_capture->_passed_reject_uri()
	);

	$grouped_capture_end = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog(/|$)' );
	pgcg_assert_same(
		'Capturing directory reject rule matches a descendant',
		false,
		$grouped_capture_end->_passed_reject_uri()
	);

	$grouped_mixed = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?:/catalog/|cart)' );
	pgcg_assert_same(
		'Grouped directory alternative matches a nested descendant',
		false,
		$grouped_mixed->_passed_reject_uri()
	);

	$grouped_mixed_segment = pgcg_new_grabber( '/cart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?:/catalog/|cart)' );
	pgcg_assert_same(
		'Grouped segment alternative matches its own path segment',
		false,
		$grouped_mixed_segment->_passed_reject_uri()
	);

	$grouped_mixed_near = pgcg_new_grabber( '/shopping-cart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?:/catalog/|cart)' );
	pgcg_assert_same(
		'Grouped segment alternative does not match inside a longer segment',
		true,
		$grouped_mixed_near->_passed_reject_uri()
	);

	$grouped_accept = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.accept.files'] = array( '^/catalog(?:/|$)' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri']   = array();
	pgcg_assert_same(
		'Grouped directory accept rule matches a descendant',
		true,
		$grouped_accept->_passed_accept_files()
	);

	$grouped_accept_nested = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.accept.files'] = array( '(?:/catalog/)' );
	pgcg_assert_same(
		'Grouped directory accept rule matches a nested directory',
		true,
		$grouped_accept_nested->_passed_accept_files()
	);

	$grouped_accept_root = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.accept.files'] = array( '^/catalog(?:/|$)' );
	pgcg_assert_same(
		'Grouped anchored directory accept rule stays at the root',
		false,
		$grouped_accept_root->_passed_accept_files()
	);

	$nested_group = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog(?:(?:/|$))' );
	pgcg_assert_same(
		'Nested grouped directory reject rule matches a descendant',
		false,
		$nested_group->_passed_reject_uri()
	);

	$nested_group_wrapped = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?:(?:/catalog/))' );
	pgcg_assert_same(
		'Nested grouped directory reject rule matches a nested directory',
		false,
		$nested_group_wrapped->_passed_reject_uri()
	);

	$optional_suffix = pgcg_new_grabber( '/page.html.extra' );
	\W3TC\Dispatcher::$config->arrays['pgcache.accept.files'] = array( 'page(\.html)?' );
	pgcg_assert_same(
		'Optional grouped suffix does not match a longer name',
		false,
		$optional_suffix->_passed_accept_files()
	);

	$optional_suffix_hit = pgcg_new_grabber( '/page.html' );
	\W3TC\Dispatcher::$config->arrays['pgcache.accept.files'] = array( 'page(\.html)?' );
	pgcg_assert_same(
		'Optional grouped suffix still matches the file name',
		true,
		$optional_suffix_hit->_passed_accept_files()
	);

	$mixed_tail = pgcg_new_grabber( '/catalog/page/extra/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog(?:/item|/page/)' );
	pgcg_assert_same(
		'Mixed grouped directory alternative still matches a descendant',
		false,
		$mixed_tail->_passed_reject_uri()
	);

	$mixed_tail_item = pgcg_new_grabber( '/catalog/item' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog(?:/item|/page/)' );
	pgcg_assert_same(
		'Mixed grouped segment alternative matches its own path',
		false,
		$mixed_tail_item->_passed_reject_uri()
	);

	$mixed_tail_near = pgcg_new_grabber( '/catalog/itemextra' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog(?:/item|/page/)' );
	pgcg_assert_same(
		'Mixed grouped segment alternative does not match a longer segment',
		true,
		$mixed_tail_near->_passed_reject_uri()
	);

	$tilde_plain = pgcg_new_grabber( '/catalog~archive' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( 'catalog~archive' );
	pgcg_assert_same(
		'A tilde in a reject expression still matches that path',
		false,
		$tilde_plain->_passed_reject_uri()
	);

	$tilde_plain_near = pgcg_new_grabber( '/catalog-archive' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( 'catalog~archive' );
	pgcg_assert_same(
		'A tilde in a reject expression does not match a different character',
		true,
		$tilde_plain_near->_passed_reject_uri()
	);

	$tilde_quoted = pgcg_new_grabber( '/catalog~archive' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( 'catalog\~archive' );
	pgcg_assert_same(
		'An already quoted tilde still matches that path',
		false,
		$tilde_quoted->_passed_reject_uri()
	);

	$tilde_quoted_near = pgcg_new_grabber( '/catalog-archive' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( 'catalog\~archive' );
	pgcg_assert_same(
		'An already quoted tilde does not match a different character',
		true,
		$tilde_quoted_near->_passed_reject_uri()
	);

	$tilde_even = pgcg_new_grabber( '/catalog\\~archive' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( 'catalog\\\\~archive' );
	pgcg_assert_same(
		'A tilde after an even run of backslashes is still quoted',
		false,
		$tilde_even->_passed_reject_uri()
	);

	$lookbehind = pgcg_new_grabber( '/shop/cart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?<=/shop)/cart' );
	pgcg_assert_same(
		'A leading lookbehind still matches its path',
		false,
		$lookbehind->_passed_reject_uri()
	);

	$lookbehind_child = pgcg_new_grabber( '/shop/cart/item' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?<=/shop)/cart' );
	pgcg_assert_same(
		'A leading lookbehind still matches a descendant',
		false,
		$lookbehind_child->_passed_reject_uri()
	);

	$lookbehind_near = pgcg_new_grabber( '/shop/cartoon' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?<=/shop)/cart' );
	pgcg_assert_same(
		'A leading lookbehind does not match a longer segment',
		true,
		$lookbehind_near->_passed_reject_uri()
	);

	$lookbehind_other = pgcg_new_grabber( '/cart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?<=/shop)/cart' );
	pgcg_assert_same(
		'A leading lookbehind does not match without its prefix',
		true,
		$lookbehind_other->_passed_reject_uri()
	);

	$lookbehind_negative = pgcg_new_grabber( '/cart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?<!/shop)/cart' );
	pgcg_assert_same(
		'A leading negative lookbehind still matches without its prefix',
		false,
		$lookbehind_negative->_passed_reject_uri()
	);

	$lookbehind_negative_miss = pgcg_new_grabber( '/shop/cart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?<!/shop)/cart' );
	pgcg_assert_same(
		'A leading negative lookbehind does not match its excluded prefix',
		true,
		$lookbehind_negative_miss->_passed_reject_uri()
	);

	$lookahead = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?=/catalog/)/catalog/' );
	pgcg_assert_same(
		'A leading lookahead still matches a nested directory',
		false,
		$lookahead->_passed_reject_uri()
	);

	$lookahead_root = pgcg_new_grabber( '/catalog/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?=/catalog/)/catalog/' );
	pgcg_assert_same(
		'A leading lookahead still matches at the root',
		false,
		$lookahead_root->_passed_reject_uri()
	);

	$lookahead_near = pgcg_new_grabber( '/shop/catalog-extra/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?=/catalog/)/catalog/' );
	pgcg_assert_same(
		'A leading lookahead does not match a longer segment',
		true,
		$lookahead_near->_passed_reject_uri()
	);

	$capture_tail = pgcg_new_grabber( '/catalog/page/extra/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog(/item|/page/)' );
	pgcg_assert_same(
		'Capturing directory alternative still matches a descendant',
		false,
		$capture_tail->_passed_reject_uri()
	);

	$capture_tail_item = pgcg_new_grabber( '/catalog/item' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog(/item|/page/)' );
	pgcg_assert_same(
		'Capturing segment alternative matches its own path',
		false,
		$capture_tail_item->_passed_reject_uri()
	);

	$capture_tail_near = pgcg_new_grabber( '/catalog/itemextra' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog(/item|/page/)' );
	pgcg_assert_same(
		'Capturing segment alternative does not match a longer segment',
		true,
		$capture_tail_near->_passed_reject_uri()
	);

	$capture_mixed = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(/catalog/|cart)' );
	pgcg_assert_same(
		'Capturing directory alternative matches a nested descendant',
		false,
		$capture_mixed->_passed_reject_uri()
	);

	$capture_mixed_segment = pgcg_new_grabber( '/cart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(/catalog/|cart)' );
	pgcg_assert_same(
		'Capturing segment alternative matches its own path segment',
		false,
		$capture_mixed_segment->_passed_reject_uri()
	);

	$capture_mixed_near = pgcg_new_grabber( '/shopping-cart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(/catalog/|cart)' );
	pgcg_assert_same(
		'Capturing segment alternative does not match inside a longer segment',
		true,
		$capture_mixed_near->_passed_reject_uri()
	);

	$capture_backref = pgcg_new_grabber( '/cart/cart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/(cart|page)/\1' );
	pgcg_assert_same(
		'A capturing alternative still matches its backreference',
		false,
		$capture_backref->_passed_reject_uri()
	);

	$capture_backref_miss = pgcg_new_grabber( '/cart/page' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/(cart|page)/\1' );
	pgcg_assert_same(
		'A capturing alternative does not match a different backreference',
		true,
		$capture_backref_miss->_passed_reject_uri()
	);

	$nested_backref = pgcg_new_grabber( '/barbar' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(/(foo)/|(bar)\3)' );
	pgcg_assert_same(
		'A nested backreference still matches its own group',
		false,
		$nested_backref->_passed_reject_uri()
	);

	$nested_backref_miss = pgcg_new_grabber( '/barfoo' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(/(foo)/|(bar)\3)' );
	pgcg_assert_same(
		'A nested backreference does not match a different group',
		true,
		$nested_backref_miss->_passed_reject_uri()
	);

	$nested_backref_other = pgcg_new_grabber( '/foo/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(/(foo)/|(bar)\3)' );
	pgcg_assert_same(
		'The other nested alternative still matches its path',
		false,
		$nested_backref_other->_passed_reject_uri()
	);

	$nested_backref_child = pgcg_new_grabber( '/shop/foo/item' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(/(foo)/|(bar)\3)' );
	pgcg_assert_same(
		'A nested alternative still matches a descendant',
		false,
		$nested_backref_child->_passed_reject_uri()
	);

	$nested_backref_near = pgcg_new_grabber( '/shopping-barbar' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(/(foo)/|(bar)\3)' );
	pgcg_assert_same(
		'A nested backreference does not match inside a longer segment',
		true,
		$nested_backref_near->_passed_reject_uri()
	);

	$nested_backref_tail = pgcg_new_grabber( '/shop/barbar' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/shop(/(foo)/|/(bar)\3)' );
	pgcg_assert_same(
		'A trailing nested backreference still matches its path',
		false,
		$nested_backref_tail->_passed_reject_uri()
	);

	$nested_backref_tail_child = pgcg_new_grabber( '/shop/foo/item' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/shop(/(foo)/|/(bar)\3)' );
	pgcg_assert_same(
		'A trailing nested alternative still matches a descendant',
		false,
		$nested_backref_tail_child->_passed_reject_uri()
	);

	$nested_backref_tail_miss = pgcg_new_grabber( '/shop/barfoo' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/shop(/(foo)/|/(bar)\3)' );
	pgcg_assert_same(
		'A trailing nested backreference does not match a different group',
		true,
		$nested_backref_tail_miss->_passed_reject_uri()
	);

	$nested_backref_lead = pgcg_new_grabber( '/barbarend' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(/(foo)/|(bar)\3)end' );
	pgcg_assert_same(
		'A leading nested backreference still matches its path',
		false,
		$nested_backref_lead->_passed_reject_uri()
	);

	$nested_backref_lead_child = pgcg_new_grabber( '/shop/foo/end' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(/(foo)/|(bar)\3)end' );
	pgcg_assert_same(
		'A leading nested alternative still matches a nested path',
		false,
		$nested_backref_lead_child->_passed_reject_uri()
	);

	$nested_backref_lead_miss = pgcg_new_grabber( '/barbarending' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(/(foo)/|(bar)\3)end' );
	pgcg_assert_same(
		'A leading nested backreference does not match a longer suffix',
		true,
		$nested_backref_lead_miss->_passed_reject_uri()
	);

	$outer_backref = pgcg_new_grabber( '/cartcart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(cart|/catalog/)\1' );
	pgcg_assert_same(
		'A backreference to the outer group still matches after a leading boundary',
		false,
		$outer_backref->_passed_reject_uri()
	);

	$outer_backref_dir = pgcg_new_grabber( '/catalog//catalog/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(cart|/catalog/)\1' );
	pgcg_assert_same(
		'A backreference to the outer group still matches a directory alternative',
		false,
		$outer_backref_dir->_passed_reject_uri()
	);

	$outer_backref_miss = pgcg_new_grabber( '/cartpage' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(cart|/catalog/)\1' );
	pgcg_assert_same(
		'A backreference to the outer group does not match a different suffix',
		true,
		$outer_backref_miss->_passed_reject_uri()
	);

	$empty_tail = pgcg_new_grabber( '/cartoon' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( 'cart(?:/|)' );
	pgcg_assert_same(
		'An empty alternative does not match inside a longer segment',
		true,
		$empty_tail->_passed_reject_uri()
	);

	$empty_tail_hit = pgcg_new_grabber( '/cart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( 'cart(?:/|)' );
	pgcg_assert_same(
		'An empty alternative still matches the segment',
		false,
		$empty_tail_hit->_passed_reject_uri()
	);

	$empty_tail_child = pgcg_new_grabber( '/cart/extra' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( 'cart(?:/|)' );
	pgcg_assert_same(
		'An empty alternative still matches a descendant',
		false,
		$empty_tail_child->_passed_reject_uri()
	);

	$empty_lead = pgcg_new_grabber( '/shopping-cart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?:^|)cart' );
	pgcg_assert_same(
		'An empty leading alternative does not match inside a longer segment',
		true,
		$empty_lead->_passed_reject_uri()
	);

	$empty_lead_hit = pgcg_new_grabber( '/cart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?:^|)cart' );
	pgcg_assert_same(
		'An empty leading alternative still matches the segment',
		false,
		$empty_lead_hit->_passed_reject_uri()
	);

	$empty_lead_nested = pgcg_new_grabber( '/shop/cart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?:^|)cart' );
	pgcg_assert_same(
		'An empty leading alternative still matches a later segment',
		false,
		$empty_lead_nested->_passed_reject_uri()
	);

	$class_child = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog[/]' );
	pgcg_assert_same(
		'A separator class still matches a descendant',
		false,
		$class_child->_passed_reject_uri()
	);

	$class_exact = pgcg_new_grabber( '/catalog/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog[/]' );
	pgcg_assert_same(
		'A separator class still matches the directory itself',
		false,
		$class_exact->_passed_reject_uri()
	);

	$class_near = pgcg_new_grabber( '/catalog-extra/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog[/]' );
	pgcg_assert_same(
		'A separator class does not match a longer segment',
		true,
		$class_near->_passed_reject_uri()
	);

	$class_nested = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog[/]' );
	pgcg_assert_same(
		'An anchored separator class stays at the root',
		true,
		$class_nested->_passed_reject_uri()
	);

	$class_set = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog[/?]' );
	pgcg_assert_same(
		'A separator class of more than one character still matches a descendant',
		false,
		$class_set->_passed_reject_uri()
	);

	$class_lead = pgcg_new_grabber( '/shop/catalog' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '[/]catalog' );
	pgcg_assert_same(
		'A leading separator class matches a nested segment',
		false,
		$class_lead->_passed_reject_uri()
	);

	$class_lead_near = pgcg_new_grabber( '/cataloging' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '[/]catalog' );
	pgcg_assert_same(
		'A leading separator class does not match a longer segment',
		true,
		$class_lead_near->_passed_reject_uri()
	);

	$class_negated = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog[^a]' );
	pgcg_assert_same(
		'A negated class does not count as a separator boundary',
		true,
		$class_negated->_passed_reject_uri()
	);

	$class_negated_end = pgcg_new_grabber( '/catalog/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog[^a]' );
	pgcg_assert_same(
		'A negated class still matches when the following character ends the path',
		false,
		$class_negated_end->_passed_reject_uri()
	);

	$option_child = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?i)/catalog/' );
	pgcg_assert_same(
		'A leading option group still matches a nested descendant',
		false,
		$option_child->_passed_reject_uri()
	);

	$option_root = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?i)/catalog/' );
	pgcg_assert_same(
		'A leading option group still matches a descendant',
		false,
		$option_root->_passed_reject_uri()
	);

	$option_near = pgcg_new_grabber( '/catalog-extra/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?i)/catalog/' );
	pgcg_assert_same(
		'A leading option group does not match a longer segment',
		true,
		$option_near->_passed_reject_uri()
	);

	$option_scoped = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?i:/catalog/)' );
	pgcg_assert_same(
		'A scoped option group still matches a descendant',
		false,
		$option_scoped->_passed_reject_uri()
	);

	$option_scoped_nested = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?i:/catalog/)' );
	pgcg_assert_same(
		'A scoped option group still matches a nested descendant',
		false,
		$option_scoped_nested->_passed_reject_uri()
	);

	$option_scoped_near = pgcg_new_grabber( '/catalog-extra/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?i:/catalog/)' );
	pgcg_assert_same(
		'A scoped option group does not match a longer segment',
		true,
		$option_scoped_near->_passed_reject_uri()
	);

	$option_case = pgcg_new_grabber( '/Catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?-i)/Catalog/' );
	pgcg_assert_same(
		'A leading option group keeps its case',
		false,
		$option_case->_passed_reject_uri()
	);

	$option_case_miss = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?-i)/Catalog/' );
	pgcg_assert_same(
		'A leading option group does not match a different case',
		true,
		$option_case_miss->_passed_reject_uri()
	);

	$option_scope_dir = pgcg_new_grabber( '/Catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?-i:/Catalog/|cart)' );
	pgcg_assert_same(
		'A scoped option group keeps its case on a directory alternative',
		false,
		$option_scope_dir->_passed_reject_uri()
	);

	$option_scope_miss = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?-i:/Catalog/|cart)' );
	pgcg_assert_same(
		'A scoped option group does not match a different case',
		true,
		$option_scope_miss->_passed_reject_uri()
	);

	$option_scope_segment = pgcg_new_grabber( '/cart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?-i:/Catalog/|cart)' );
	pgcg_assert_same(
		'A scoped option group keeps its case on a segment alternative',
		false,
		$option_scope_segment->_passed_reject_uri()
	);

	$option_scope_segment_miss = pgcg_new_grabber( '/CART' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?-i:/Catalog/|cart)' );
	pgcg_assert_same(
		'A scoped option group does not match a different case on a segment',
		true,
		$option_scope_segment_miss->_passed_reject_uri()
	);

	$option_scope_near = pgcg_new_grabber( '/cartoon' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?-i:/Catalog/|cart)' );
	pgcg_assert_same(
		'A scoped option group does not match inside a longer segment',
		true,
		$option_scope_near->_passed_reject_uri()
	);

	$option_mixed = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?i)(?:/catalog/|cart)' );
	pgcg_assert_same(
		'An option group before a mixed group still matches a nested descendant',
		false,
		$option_mixed->_passed_reject_uri()
	);

	$option_mixed_segment = pgcg_new_grabber( '/cart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?i)(?:/catalog/|cart)' );
	pgcg_assert_same(
		'An option group before a mixed group still matches the segment',
		false,
		$option_mixed_segment->_passed_reject_uri()
	);

	$repeat_child = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog/+' );
	pgcg_assert_same(
		'A repeated separator still matches a descendant',
		false,
		$repeat_child->_passed_reject_uri()
	);

	$repeat_exact = pgcg_new_grabber( '/catalog/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog/+' );
	pgcg_assert_same(
		'A repeated separator still matches the directory itself',
		false,
		$repeat_exact->_passed_reject_uri()
	);

	$repeat_bare = pgcg_new_grabber( '/catalog' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog/+' );
	pgcg_assert_same(
		'A repeated separator does not match the name without a separator',
		true,
		$repeat_bare->_passed_reject_uri()
	);

	$repeat_near = pgcg_new_grabber( '/catalog-extra/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog/+' );
	pgcg_assert_same(
		'A repeated separator does not match a longer segment',
		true,
		$repeat_near->_passed_reject_uri()
	);

	$repeat_class = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog[/]+' );
	pgcg_assert_same(
		'A repeated separator class still matches a descendant',
		false,
		$repeat_class->_passed_reject_uri()
	);

	$repeat_brace = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog/{1,}' );
	pgcg_assert_same(
		'A brace repetition with a positive minimum still matches a descendant',
		false,
		$repeat_brace->_passed_reject_uri()
	);

	$repeat_lazy = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog/+?' );
	pgcg_assert_same(
		'A lazy repetition with a positive minimum still matches a descendant',
		false,
		$repeat_lazy->_passed_reject_uri()
	);

	$repeat_optional = pgcg_new_grabber( '/catalog-extra/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog/*' );
	pgcg_assert_same(
		'An optional repetition does not match a longer segment',
		true,
		$repeat_optional->_passed_reject_uri()
	);

	$repeat_zero = pgcg_new_grabber( '/catalog-extra/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog/{0}' );
	pgcg_assert_same(
		'A zero minimum does not match a longer segment',
		true,
		$repeat_zero->_passed_reject_uri()
	);

	$look_hit = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog/(?=item)' );
	pgcg_assert_same(
		'A trailing lookahead still matches a descendant',
		false,
		$look_hit->_passed_reject_uri()
	);

	$look_miss = pgcg_new_grabber( '/catalog/other/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog/(?=item)' );
	pgcg_assert_same(
		'A trailing lookahead does not match a different descendant',
		true,
		$look_miss->_passed_reject_uri()
	);

	$look_neg = pgcg_new_grabber( '/catalog/other/extra' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog/(?!item)' );
	pgcg_assert_same(
		'A trailing negative lookahead still matches another descendant',
		false,
		$look_neg->_passed_reject_uri()
	);

	$look_neg_miss = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog/(?!item)' );
	pgcg_assert_same(
		'A trailing negative lookahead does not match its excluded descendant',
		true,
		$look_neg_miss->_passed_reject_uri()
	);

	$look_behind = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog/(?<=/)' );
	pgcg_assert_same(
		'A trailing lookbehind still matches a descendant',
		false,
		$look_behind->_passed_reject_uri()
	);

	$look_behind_neg = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog/(?<!y)' );
	pgcg_assert_same(
		'A trailing negative lookbehind still matches a descendant',
		false,
		$look_behind_neg->_passed_reject_uri()
	);

	$look_optional = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog/(?=item)?' );
	pgcg_assert_same(
		'An optional trailing lookahead still matches a descendant',
		false,
		$look_optional->_passed_reject_uri()
	);

	$named_nested = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?<dir>/catalog/)' );
	pgcg_assert_same(
		'A named directory group still matches a nested descendant',
		false,
		$named_nested->_passed_reject_uri()
	);

	$named_near = pgcg_new_grabber( '/catalog-extra/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?<dir>/catalog/)' );
	pgcg_assert_same(
		'A named directory group does not match a longer segment',
		true,
		$named_near->_passed_reject_uri()
	);

	$named_ref = pgcg_new_grabber( '/catalog//catalog/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?<dir>/catalog/)\k<dir>' );
	pgcg_assert_same(
		'A named directory group still matches its reference',
		false,
		$named_ref->_passed_reject_uri()
	);

	$named_ref_nested = pgcg_new_grabber( '/shop/catalog//catalog/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?<dir>/catalog/)\k<dir>' );
	pgcg_assert_same(
		'A named directory group still matches its reference under another path',
		false,
		$named_ref_nested->_passed_reject_uri()
	);

	$named_ref_miss = pgcg_new_grabber( '/catalog//shop/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?<dir>/catalog/)\k<dir>' );
	pgcg_assert_same(
		'A named directory group does not match a different reference',
		true,
		$named_ref_miss->_passed_reject_uri()
	);

	$named_mixed = pgcg_new_grabber( '/cartcart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?<dir>/catalog/|cart)\k<dir>' );
	pgcg_assert_same(
		'A named alternative still matches its reference',
		false,
		$named_mixed->_passed_reject_uri()
	);

	$named_mixed_dir = pgcg_new_grabber( '/shop/catalog//catalog/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?<dir>/catalog/|cart)\k<dir>' );
	pgcg_assert_same(
		'A named directory alternative still matches its reference',
		false,
		$named_mixed_dir->_passed_reject_uri()
	);

	$named_mixed_miss = pgcg_new_grabber( '/cartpage' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?<dir>/catalog/|cart)\k<dir>' );
	pgcg_assert_same(
		'A named alternative does not match a different reference',
		true,
		$named_mixed_miss->_passed_reject_uri()
	);

	$named_p = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?P<dir>/catalog/)' );
	pgcg_assert_same(
		'A P-form named directory group still matches a nested descendant',
		false,
		$named_p->_passed_reject_uri()
	);

	$named_p_ref = pgcg_new_grabber( '/catalog//catalog/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?P<dir>/catalog/)(?P=dir)' );
	pgcg_assert_same(
		'A P-form named directory group still matches its reference',
		false,
		$named_p_ref->_passed_reject_uri()
	);

	$named_quote = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?\'dir\'/catalog/)' );
	pgcg_assert_same(
		'A quoted named directory group still matches a nested descendant',
		false,
		$named_quote->_passed_reject_uri()
	);

	$atomic_nested = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?>/catalog/)' );
	pgcg_assert_same(
		'An atomic directory group still matches a nested descendant',
		false,
		$atomic_nested->_passed_reject_uri()
	);

	$comment_nested = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?#note)/catalog/' );
	pgcg_assert_same(
		'A leading comment still matches a nested descendant',
		false,
		$comment_nested->_passed_reject_uri()
	);

	$branch_nested = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?|/catalog/)' );
	pgcg_assert_same(
		'A branch-reset directory group still matches a nested descendant',
		false,
		$branch_nested->_passed_reject_uri()
	);

	$branch_ref = pgcg_new_grabber( '/barbar' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?|/(foo)/|(bar)\1)' );
	pgcg_assert_same(
		'A branch-reset backreference still matches its own group',
		false,
		$branch_ref->_passed_reject_uri()
	);

	$branch_ref_miss = pgcg_new_grabber( '/barfoo' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?|/(foo)/|(bar)\1)' );
	pgcg_assert_same(
		'A branch-reset backreference does not match a different group',
		true,
		$branch_ref_miss->_passed_reject_uri()
	);

	$branch_ref_other = pgcg_new_grabber( '/foo/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?|/(foo)/|(bar)\1)' );
	pgcg_assert_same(
		'The other branch-reset alternative still matches its path',
		false,
		$branch_ref_other->_passed_reject_uri()
	);

	$branch_ref_near = pgcg_new_grabber( '/shopping-barbar' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?|/(foo)/|(bar)\1)' );
	pgcg_assert_same(
		'A branch-reset backreference does not match inside a longer segment',
		true,
		$branch_ref_near->_passed_reject_uri()
	);

	$repeat_group = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?:/catalog/)+' );
	pgcg_assert_same(
		'A repeated directory group still matches a nested descendant',
		false,
		$repeat_group->_passed_reject_uri()
	);

	$repeat_group_near = pgcg_new_grabber( '/catalog-extra/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?:/catalog/)+' );
	pgcg_assert_same(
		'A repeated directory group does not match a longer segment',
		true,
		$repeat_group_near->_passed_reject_uri()
	);

	$repeat_named = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?<dir>/catalog/)+' );
	pgcg_assert_same(
		'A repeated named directory group still matches a nested descendant',
		false,
		$repeat_named->_passed_reject_uri()
	);

	$repeat_mixed = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?:/catalog/|cart)+' );
	pgcg_assert_same(
		'A repeated mixed group still matches a nested descendant',
		false,
		$repeat_mixed->_passed_reject_uri()
	);

	$repeat_mixed_segment = pgcg_new_grabber( '/shop/cart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?:/catalog/|cart)+' );
	pgcg_assert_same(
		'A repeated mixed group still matches a later segment',
		false,
		$repeat_mixed_segment->_passed_reject_uri()
	);

	$repeat_mixed_near = pgcg_new_grabber( '/cartoon' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?:/catalog/|cart)+' );
	pgcg_assert_same(
		'A repeated mixed group does not match inside a longer segment',
		true,
		$repeat_mixed_near->_passed_reject_uri()
	);

	$repeat_possessive = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog/++' );
	pgcg_assert_same(
		'A possessive repetition still matches a descendant',
		false,
		$repeat_possessive->_passed_reject_uri()
	);

	$option_flags = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?imsx:/catalog/)' );
	pgcg_assert_same(
		'A scoped option group with several flags still matches a nested descendant',
		false,
		$option_flags->_passed_reject_uri()
	);

	$option_accept = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.accept.files'] = array( '(?i:/catalog/)' );
	pgcg_assert_same(
		'A scoped option accept rule matches a descendant',
		true,
		$option_accept->_passed_accept_files()
	);

	$repeat_accept = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.accept.files'] = array( '^/catalog/+' );
	pgcg_assert_same(
		'A repeated separator accept rule matches a descendant',
		true,
		$repeat_accept->_passed_accept_files()
	);

	$named_accept = pgcg_new_grabber( '/shop/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.accept.files'] = array( '(?<dir>/catalog/)' );
	pgcg_assert_same(
		'A named directory accept rule matches a nested descendant',
		true,
		$named_accept->_passed_accept_files()
	);

	$repeat_twice = pgcg_new_grabber( '/cartcart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?:/catalog/|cart){2}' );
	pgcg_assert_same(
		'A repeated mixed group matches two segment iterations',
		false,
		$repeat_twice->_passed_reject_uri()
	);

	$repeat_twice_dir = pgcg_new_grabber( '/cart/catalog/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?:/catalog/|cart){2}' );
	pgcg_assert_same(
		'A repeated mixed group matches a segment and a directory',
		false,
		$repeat_twice_dir->_passed_reject_uri()
	);

	$repeat_twice_child = pgcg_new_grabber( '/cart/catalog/item' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?:/catalog/|cart){2}' );
	pgcg_assert_same(
		'A repeated mixed group still matches a descendant of the last iteration',
		false,
		$repeat_twice_child->_passed_reject_uri()
	);

	$repeat_twice_near = pgcg_new_grabber( '/cartcartoon' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?:/catalog/|cart){2}' );
	pgcg_assert_same(
		'A repeated mixed group does not match inside a longer segment',
		true,
		$repeat_twice_near->_passed_reject_uri()
	);

	$repeat_plus = pgcg_new_grabber( '/cartcart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?:/catalog/|cart)+' );
	pgcg_assert_same(
		'A repeated mixed group matches two segment iterations with +',
		false,
		$repeat_plus->_passed_reject_uri()
	);

	$repeat_plus_near = pgcg_new_grabber( '/cartoon' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?:/catalog/|cart)+' );
	pgcg_assert_same(
		'A repeated mixed group with + does not match inside a longer segment',
		true,
		$repeat_plus_near->_passed_reject_uri()
	);

	$repeat_capture = pgcg_new_grabber( '/cartcart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(cart|/catalog/){2}' );
	pgcg_assert_same(
		'A repeated capturing group still matches two segment iterations',
		false,
		$repeat_capture->_passed_reject_uri()
	);

	$repeat_range = pgcg_new_grabber( '/cartcart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?:/catalog/|cart){2,}' );
	pgcg_assert_same(
		'A repeated mixed group matches two segment iterations with a brace range',
		false,
		$repeat_range->_passed_reject_uri()
	);

	$repeat_segment = pgcg_new_grabber( '/cartcart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?:cart){2}' );
	pgcg_assert_same(
		'A repeated segment group matches both iterations',
		false,
		$repeat_segment->_passed_reject_uri()
	);

	$repeat_segment_near = pgcg_new_grabber( '/cartoon' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?:cart){2}' );
	pgcg_assert_same(
		'A repeated segment group does not match inside a longer segment',
		true,
		$repeat_segment_near->_passed_reject_uri()
	);

	$wrapped_dir = pgcg_new_grabber( '/catalog/item' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '((?:/catalog/|cart))' );
	pgcg_assert_same(
		'A wrapped directory group still matches a descendant',
		false,
		$wrapped_dir->_passed_reject_uri()
	);

	$wrapped_nested = pgcg_new_grabber( '/shop/catalog/item' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '((?:/catalog/|cart))' );
	pgcg_assert_same(
		'A wrapped directory group still matches a nested descendant',
		false,
		$wrapped_nested->_passed_reject_uri()
	);

	$wrapped_near = pgcg_new_grabber( '/cartoon' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '((?:/catalog/|cart))' );
	pgcg_assert_same(
		'A wrapped directory group does not match inside a longer segment',
		true,
		$wrapped_near->_passed_reject_uri()
	);

	$wrapped_twice = pgcg_new_grabber( '/catalog/item' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(((?:/catalog/|cart)))' );
	pgcg_assert_same(
		'A twice-wrapped directory group still matches a descendant',
		false,
		$wrapped_twice->_passed_reject_uri()
	);

	$wrapped_tail_twice = pgcg_new_grabber( '/catalog/page/extra/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog(?:(?:(?:/item|/page/)))' );
	pgcg_assert_same(
		'A twice-wrapped trailing group still matches a descendant',
		false,
		$wrapped_tail_twice->_passed_reject_uri()
	);

	$wrapped_tail = pgcg_new_grabber( '/catalog/page/extra/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog(?:(?:/item|/page/))' );
	pgcg_assert_same(
		'A wrapped trailing group still matches a descendant',
		false,
		$wrapped_tail->_passed_reject_uri()
	);

	$wrapped_tail_near = pgcg_new_grabber( '/catalog/itemextra' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog(?:(?:/item|/page/))' );
	pgcg_assert_same(
		'A wrapped trailing group does not match a longer segment',
		true,
		$wrapped_tail_near->_passed_reject_uri()
	);

	$wrapped_tail_page = pgcg_new_grabber( '/catalog/pageextra' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '^/catalog(?:(?:/item|/page/))' );
	pgcg_assert_same(
		'A wrapped trailing directory does not match a longer segment',
		true,
		$wrapped_tail_page->_passed_reject_uri()
	);

	$wrapped_ref = pgcg_new_grabber( '/cartcart' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '((?:/catalog/|(cart)))\2' );
	pgcg_assert_same(
		'A wrapped group keeps its capture number',
		false,
		$wrapped_ref->_passed_reject_uri()
	);

	$wrapped_option = pgcg_new_grabber( '/Catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?-i:((?:/Catalog/|cart)))' );
	pgcg_assert_same(
		'A wrapped option group keeps its case on a directory',
		false,
		$wrapped_option->_passed_reject_uri()
	);

	$wrapped_option_miss = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?-i:((?:/Catalog/|cart)))' );
	pgcg_assert_same(
		'A wrapped option group does not match a different case',
		true,
		$wrapped_option_miss->_passed_reject_uri()
	);

	$extended_child = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?x)^/catalog/ # directory' );
	pgcg_assert_same(
		'An extended-mode comment still matches a descendant',
		false,
		$extended_child->_passed_reject_uri()
	);

	$extended_near = pgcg_new_grabber( '/catalog-extra/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?x)^/catalog/ # directory' );
	pgcg_assert_same(
		'An extended-mode comment does not match a longer segment',
		true,
		$extended_near->_passed_reject_uri()
	);

	$extended_hash = pgcg_new_grabber( '/catalog#archive' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?-x)catalog#archive' );
	pgcg_assert_same(
		'A hash is literal when extended mode is off',
		false,
		$extended_hash->_passed_reject_uri()
	);

	$extended_space = pgcg_new_grabber( '/catalog/item/' );
	\W3TC\Dispatcher::$config->arrays['pgcache.reject.uri'] = array( '(?x)^ / catalog / # directory' );
	pgcg_assert_same(
		'Extended-mode space still matches a descendant',
		false,
		$extended_space->_passed_reject_uri()
	);

	\W3TC\Dispatcher::$config->arrays['pgcache.accept.files'] = array( 'page(\.html)?' );

	require_once __DIR__ . '/../Util_Rule.php';
	require_once __DIR__ . '/../PgCache_Environment.php';

	$rule_cookies = \W3TC\PgCache_Environment::reject_cookies( \W3TC\Dispatcher::$config, false );
	pgcg_assert_same(
		'Server rules skip the page cache for wp-resetpass',
		true,
		\in_array( 'wp-resetpass', $rule_cookies, true )
	);
	pgcg_assert_same(
		'Server rules skip the page cache for wp-activate',
		true,
		\in_array( 'wp-activate', $rule_cookies, true )
	);

	$forced_uri = pgcg_new_grabber( '/wp-login.php?action=rp&utm_source=wp-locations.php' );
	pgcg_assert_same(
		'Rejected URI is not stored when caching is forced and the cache policy is public',
		false,
		pgcg_can_write_cache( $forced_uri, $html, pgcg_headers( 'max-age=120, public' ) )
	);

	$forced_cookie = pgcg_new_grabber( '/hello-world/' );
	$_COOKIE['wp-resetpass-abc'] = 'user:key';
	pgcg_assert_same(
		'wp-resetpass cookie is not stored when caching is forced',
		false,
		pgcg_can_write_cache( $forced_cookie, $html, pgcg_headers( 'max-age=120, public' ) )
	);
	$head = pgcg_new_grabber( '/hello-world/' );
	$_SERVER['REQUEST_METHOD'] = 'HEAD';
	pgcg_assert_same(
		'HEAD responses are not written',
		false,
		pgcg_can_write_cache( $head, $html, pgcg_headers() )
	);

	$iis_sample = \file_get_contents( __DIR__ . '/../ini/web.config' );
	pgcg_assert_same(
		'IIS sample skips the page cache for wp-resetpass',
		true,
		false !== \strpos( $iis_sample, 'wp-resetpass-' )
	);
	pgcg_assert_same(
		'IIS sample skips the page cache for wp-activate',
		true,
		false !== \strpos( $iis_sample, 'wp-activate-' )
	);

	if ( $w3tc_pgcache_gate_failures > 0 ) {
		exit( 1 );
	}
}
