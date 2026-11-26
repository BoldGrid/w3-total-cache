<?php
/**
 * Standalone regression test for REST page-cache session gates.
 *
 * Run with: php tests/test-pgcache-rest-session-gates.php
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
		 * Config stub for the REST session gate test.
		 */
		class PgCacheRestSessionTestConfigStub {
			/**
			 * Value returned for pgcache.rest.
			 *
			 * @var string
			 */
			public $rest = 'cache';

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
					return 'file';
				}

				if ( 'pgcache.rest' === $key ) {
					return $this->rest;
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
		 * Dispatcher stub for the REST session gate test.
		 */
		class Dispatcher {
			/**
			 * Active config stub.
			 *
			 * @var PgCacheRestSessionTestConfigStub
			 */
			public static $config;

			/**
			 * Returns the active config stub.
			 *
			 * @return PgCacheRestSessionTestConfigStub
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
		 * Environment stub for the REST session gate test.
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
			 * REST caching follows pgcache.rest; Pro is assumed active.
			 *
			 * @param mixed $config Config object.
			 * @return bool
			 */
			public static function is_rest_cache_active( $config = null ) {
				return \is_object( $config ) && 'cache' === $config->get_string( 'pgcache.rest' );
			}

			/**
			 * Matches the plugin's REST request check.
			 *
			 * @param string $url Request URI.
			 * @return bool
			 */
			public static function is_rest_request( $url ) {
				if ( \defined( 'REST_REQUEST' ) && REST_REQUEST ) {
					return true;
				}

				return 1 === \preg_match( '~/wp-json/~', $url );
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

	$w3tc_pgcache_rest_session_failures = 0;

	/**
	 * Record one assertion.
	 *
	 * @param string $label    Case label.
	 * @param mixed  $expected Expected value.
	 * @param mixed  $actual   Actual value.
	 * @return void
	 */
	function pgrs_assert_same( $label, $expected, $actual ) {
		global $w3tc_pgcache_rest_session_failures;

		if ( $expected === $actual ) {
			echo "[PASS] $label\n";
			return;
		}

		++$w3tc_pgcache_rest_session_failures;
		echo "[FAIL] $label\n";
		echo '  Expected: ' . \var_export( $expected, true ) . "\n";
		echo '  Actual:   ' . \var_export( $actual, true ) . "\n";
	}

	/**
	 * Build a grabber for one request.
	 *
	 * @param string $request_uri Request URI, including any query string.
	 * @param string $rest        Value of pgcache.rest.
	 * @param array  $cookies     Request cookies.
	 * @param array  $server      Extra $_SERVER entries.
	 * @return \W3TC\PgCache_ContentGrabber
	 */
	function pgrs_new_grabber( $request_uri, $rest = 'cache', $cookies = array(), $server = array() ) {
		unset( $_SERVER['HTTP_CART_TOKEN'], $_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI']    = $request_uri;
		$_COOKIE                   = $cookies;

		foreach ( $server as $name => $value ) {
			$_SERVER[ $name ] = $value;
		}

		$config         = new \W3TC\PgCacheRestSessionTestConfigStub();
		$config->rest   = $rest;
		$config->arrays = array(
			'pgcache.accept.files'  => array(
				'wp-comments-popup.php',
				'wp-links-opml.php',
				'wp-locations.php',
			),
			'pgcache.reject.uri'    => array(
				'wp-.*\.php',
				'index\.php',
			),
			'pgcache.reject.cookie' => array(
				'wptouch_switch_toggle',
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
	 * @param array  $headers Response headers.
	 * @param string $buffer  Response body.
	 * @return mixed
	 */
	function pgrs_can_write_cache( $grabber, $headers, $buffer = '{"items":[]}' ) {
		$method = new \ReflectionMethod( \W3TC\PgCache_ContentGrabber::class, '_can_write_cache' );
		\W3TC\Util_Reflection::set_accessible( $method );
		return $method->invoke( $grabber, $buffer, $headers );
	}

	/**
	 * Invoke the private read gate and return its decision and reason.
	 *
	 * @param object $grabber Grabber under test.
	 * @return array
	 */
	function pgrs_can_read_cache( $grabber ) {
		$method = new \ReflectionMethod( \W3TC\PgCache_ContentGrabber::class, '_can_read_cache' );
		\W3TC\Util_Reflection::set_accessible( $method );
		$allowed = $method->invoke( $grabber );

		$reason = new \ReflectionProperty( \W3TC\PgCache_ContentGrabber::class, 'cache_reject_reason' );
		\W3TC\Util_Reflection::set_accessible( $reason );

		return array( $allowed, $reason->getValue( $grabber ) );
	}

	/**
	 * Reject reason recorded by the last gate call.
	 *
	 * @param object $grabber Grabber under test.
	 * @return string
	 */
	function pgrs_reason( $grabber ) {
		$reason = new \ReflectionProperty( \W3TC\PgCache_ContentGrabber::class, 'cache_reject_reason' );
		\W3TC\Util_Reflection::set_accessible( $reason );
		return $reason->getValue( $grabber );
	}

	/**
	 * JSON response headers, plus any extra fields in headers_list() order.
	 *
	 * @param array $fields Extra header fields, each with name and value.
	 * @return array
	 */
	function pgrs_headers( $fields = array() ) {
		$fields = \array_merge(
			array(
				array(
					'name'  => 'Content-Type',
					'value' => 'application/json; charset=UTF-8',
				),
			),
			$fields
		);

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

	$posts_uri = '/wp-json/wp/v2/posts';

	$ordinary = pgrs_new_grabber( $posts_uri );
	pgrs_assert_same( 'Ordinary REST GET passes the URI gate', true, $ordinary->_passed_reject_uri() );
	pgrs_assert_same( 'Ordinary REST GET passes the cookie gate', true, $ordinary->_check_cookies() );
	pgrs_assert_same( 'Ordinary REST GET without directives can be stored', true, pgrs_can_write_cache( $ordinary, pgrs_headers() ) );
	pgrs_assert_same(
		'Ordinary REST GET with a public policy can be stored',
		true,
		pgrs_can_write_cache(
			$ordinary,
			pgrs_headers(
				array(
					array(
						'name'  => 'Cache-Control',
						'value' => 'max-age=60, public',
					),
				)
			)
		)
	);
	list( $read_allowed, $read_reason ) = pgrs_can_read_cache( pgrs_new_grabber( $posts_uri ) );
	pgrs_assert_same( 'Ordinary REST GET only stops at console mode on read', 'Console mode', $read_reason );

	foreach ( array( 'no-store', 'private', 'no-cache', 'no-cache, no-store, must-revalidate' ) as $directive ) {
		$policy = pgrs_new_grabber( '/wp-json/wc/v3/products' );
		pgrs_assert_same(
			'REST response with Cache-Control ' . $directive . ' is not stored',
			false,
			pgrs_can_write_cache(
				$policy,
				pgrs_headers(
					array(
						array(
							'name'  => 'Cache-Control',
							'value' => $directive,
						),
					)
				)
			)
		);
	}

	$set_cookie = pgrs_new_grabber( $posts_uri );
	pgrs_assert_same(
		'REST response with Set-Cookie is not stored',
		false,
		pgrs_can_write_cache(
			$set_cookie,
			pgrs_headers(
				array(
					array(
						'name'  => 'Set-Cookie',
						'value' => 'wp_woocommerce_session_abc=1%7C%7C2; path=/; HttpOnly',
					),
				)
			)
		)
	);
	pgrs_assert_same( 'Set-Cookie refusal records its reason', 'Response sets a cookie or cart token', pgrs_reason( $set_cookie ) );
	pgrs_assert_same(
		'REST response with a Cart-Token header is not stored',
		false,
		pgrs_can_write_cache(
			pgrs_new_grabber( '/wp-json/wc/v3/products' ),
			pgrs_headers(
				array(
					array(
						'name'  => 'Cart-Token',
						'value' => 'eyJ0eXAiOiJKV1QifQ.e30.sig',
					),
				)
			)
		)
	);
	pgrs_assert_same(
		'A map-only Cart-Token value is not stored',
		false,
		pgrs_can_write_cache(
			pgrs_new_grabber( '/wp-json/wc/v3/products' ),
			array(
				'kv'    => array(
					'content-type' => 'application/json',
					'cart-token'   => 'abc',
				),
				'plain' => array(),
			)
		)
	);
	pgrs_assert_same(
		'Lower-case set-cookie field is not stored',
		false,
		pgrs_can_write_cache(
			pgrs_new_grabber( $posts_uri ),
			pgrs_headers(
				array(
					array(
						'name'  => 'set-cookie',
						'value' => 'a=b',
					),
				)
			)
		)
	);
	pgrs_assert_same(
		'A map-only Set-Cookie value is not stored',
		false,
		pgrs_can_write_cache(
			pgrs_new_grabber( $posts_uri ),
			array(
				'kv'    => array(
					'content-type' => 'application/json',
					'set-cookie'   => 'a=b',
				),
				'plain' => array(),
			)
		)
	);
	pgrs_assert_same(
		'HTML page with Set-Cookie is not stored',
		false,
		pgrs_can_write_cache(
			pgrs_new_grabber( '/hello-world/', '' ),
			pgrs_headers(
				array(
					array(
						'name'  => 'Set-Cookie',
						'value' => 'a=b; path=/',
					),
				)
			),
			'<html><body>ok</body></html>'
		)
	);
	pgrs_assert_same(
		'Empty Set-Cookie value does not block storage',
		true,
		pgrs_can_write_cache(
			pgrs_new_grabber( $posts_uri ),
			pgrs_headers(
				array(
					array(
						'name'  => 'Set-Cookie',
						'value' => '',
					),
				)
			)
		)
	);
	pgrs_assert_same(
		'Set-Cookie-like header names do not block storage',
		true,
		pgrs_can_write_cache(
			pgrs_new_grabber( $posts_uri ),
			pgrs_headers(
				array(
					array(
						'name'  => 'X-Set-Cookie-Policy',
						'value' => 'none',
					),
				)
			)
		)
	);

	$session_cookies = array(
		'wp_woocommerce_session_0123abcd' => '7%7C%7C1700000000%7C%7Chash',
		'woocommerce_items_in_cart'       => '1',
		'woocommerce_cart_hash'           => 'abc123',
	);

	foreach ( $session_cookies as $name => $value ) {
		$guest = pgrs_new_grabber( '/wp-json/wc/v3/products', 'cache', array( $name => $value ) );
		pgrs_assert_same( 'REST read rejects cookie ' . $name, false, $guest->_check_cookies() );
		pgrs_assert_same( 'REST write rejects cookie ' . $name, false, pgrs_can_write_cache( $guest, pgrs_headers() ) );
		pgrs_assert_same( 'REST write reason for cookie ' . $name, 'Cookie is rejected', pgrs_reason( $guest ) );

		$plain_route = pgrs_new_grabber( '/?rest_route=/wp/v2/posts', 'cache', array( $name => $value ) );
		pgrs_assert_same( 'rest_route request rejects cookie ' . $name, false, $plain_route->_check_cookies() );

		$page = pgrs_new_grabber( '/shop/', 'cache', array( $name => $value ) );
		pgrs_assert_same( 'HTML page is not newly rejected for cookie ' . $name, true, $page->_check_cookies() );

		$rest_off = pgrs_new_grabber( '/wp-json/wc/v3/products', '', array( $name => $value ) );
		pgrs_assert_same( 'REST caching off leaves cookie gate unchanged for ' . $name, true, $rest_off->_check_cookies() );
	}

	foreach (
		array(
			'my_wp_woocommerce_session_abc',
			'woocommerce_items_in_cart_extra',
			'xwoocommerce_cart_hash',
			'woocommerce_cart_hash_backup',
			'woocommerce_recently_viewed',
			'wp_woocommerce_session',
		) as $near_miss
	) {
		$other = pgrs_new_grabber( $posts_uri, 'cache', array( $near_miss => '1' ) );
		pgrs_assert_same( 'Cookie ' . $near_miss . ' is not a session cookie match', true, $other->_check_cookies() );
	}

	$store_routes = array(
		'/wp-json/wc/store/v1/cart',
		'/wp-json/wc/store/v1/checkout',
		'/wp-json/wc/store/v1/cart/items',
		'/wp-json/wc/store/cart',
		'/wp-json/wc/store',
		'/wp-json/WC/Store/v1/cart',
		'/wp-json/wc%2Fstore/v1/cart',
		'/wp-json/wc/store/v1/cart?_locale=user',
		'/?rest_route=/wc/store/v1/cart',
		'/?rest_route=%2Fwc%2Fstore%2Fv1%2Fcheckout',
	);

	foreach ( $store_routes as $route_uri ) {
		$store = pgrs_new_grabber( $route_uri );
		pgrs_assert_same( 'Store API route is rejected: ' . $route_uri, false, $store->_passed_reject_uri() );
		pgrs_assert_same( 'Store API route is not stored: ' . $route_uri, false, pgrs_can_write_cache( $store, pgrs_headers() ) );
	}

	foreach (
		array(
			'/wp-json/wc/v3/products',
			'/wp-json/wc/storefront/v1/settings',
			'/wp-json/wc/store-locator/v1/stores',
			'/wp-json/wp/v2/pages?search=wc/store',
			'/?rest_route=/wp/v2/posts',
		) as $other_route
	) {
		$other = pgrs_new_grabber( $other_route );
		pgrs_assert_same( 'Other REST route is not rejected: ' . $other_route, true, $other->_passed_reject_uri() );
	}

	$accepted = pgrs_new_grabber( '/wp-json/wc/store/v1/products' );
	\W3TC\Dispatcher::$config->arrays['pgcache.accept.files'][] = 'wp-json/wc/store/v1/products';
	pgrs_assert_same( 'Explicit cache exception list entry passes the read URI gate', true, $accepted->_passed_accept_files() );
	pgrs_assert_same(
		'Explicit cache exception list entry still allows a Store API route',
		true,
		pgrs_can_write_cache( $accepted, pgrs_headers() )
	);

	$cart_token = pgrs_new_grabber( '/wp-json/wc/v3/products', 'cache', array(), array( 'HTTP_CART_TOKEN' => 'eyJ0eXAiOiJKV1QifQ.e30.sig' ) );
	pgrs_assert_same( 'Cart-Token REST request is not stored', false, pgrs_can_write_cache( $cart_token, pgrs_headers() ) );
	list( $read_allowed, $read_reason ) = pgrs_can_read_cache(
		pgrs_new_grabber( '/wp-json/wc/v3/products', 'cache', array(), array( 'HTTP_CART_TOKEN' => 'eyJ0eXAiOiJKV1QifQ.e30.sig' ) )
	);
	pgrs_assert_same( 'Cart-Token REST request is not read', array( false, 'Request has credentials' ), array( $read_allowed, $read_reason ) );

	foreach ( array( '/wp-json/wp/v2/users/1', '/?rest_route=/wp/v2/users/1' ) as $logged_uri ) {
		list( $read_allowed, $read_reason ) = pgrs_can_read_cache(
			pgrs_new_grabber( $logged_uri, 'cache', array( 'wordpress_logged_in_abc' => 'user|1|token' ) )
		);
		pgrs_assert_same( 'Logged-in REST caller is not read: ' . $logged_uri, array( false, 'User is logged in' ), array( $read_allowed, $read_reason ) );
	}

	$empty_token = pgrs_new_grabber( $posts_uri, 'cache', array(), array( 'HTTP_CART_TOKEN' => ' ' ) );
	pgrs_assert_same( 'Blank Cart-Token does not block storage', true, pgrs_can_write_cache( $empty_token, pgrs_headers() ) );

	$rest_off_store = pgrs_new_grabber( '/wp-json/wc/store/v1/cart', '' );
	pgrs_assert_same( 'REST caching off leaves the Store API URI gate unchanged', true, $rest_off_store->_passed_reject_uri() );
	list( $read_allowed, $read_reason ) = pgrs_can_read_cache( pgrs_new_grabber( '/wp-json/wc/store/v1/cart', '' ) );
	pgrs_assert_same( 'REST caching off still does not read a Store API URL', array( false, 'REST request' ), array( $read_allowed, $read_reason ) );

	\define( 'REST_REQUEST', true );
	$rest_off_write = pgrs_new_grabber( '/wp-json/wc/store/v1/cart', '' );
	pgrs_assert_same( 'REST caching off still does not store a Store API response', false, pgrs_can_write_cache( $rest_off_write, pgrs_headers() ) );
	pgrs_assert_same( 'REST caching off write reason is unchanged', 'REST request', pgrs_reason( $rest_off_write ) );

	echo "\n";
	if ( $w3tc_pgcache_rest_session_failures > 0 ) {
		echo "{$w3tc_pgcache_rest_session_failures} failure(s)\n";
		exit( 1 );
	}

	echo "All REST session gate checks passed.\n";
	exit( 0 );
}
