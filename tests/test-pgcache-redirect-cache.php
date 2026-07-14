<?php
/**
 * Standalone regression test for page-cache redirect write decisions.
 *
 * Run with: php tests/test-pgcache-redirect-cache.php
 *
 * Exit code 0 = all pass, non-zero = failures.
 *
 * @package W3TC\Tests
 * @since   2.10.0
 */

namespace {
	if ( \realpath( __FILE__ ) !== \realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
		return;
	}
}

namespace W3TC {
	if ( ! \class_exists( __NAMESPACE__ . '\\Dispatcher', false ) ) {
		/**
		 * Config stub for the standalone page-cache redirect test.
		 */
		class PgCacheRedirectTestConfigStub {
			public function get_boolean( $key ) {
				return false;
			}

			public function get_integer( $key ) {
				return 0;
			}

			public function get_string( $key ) {
				return '';
			}

			public function get_array( $key ) {
				return array();
			}
		}

		/**
		 * Dispatcher stub for the standalone page-cache redirect test.
		 */
		class Dispatcher {
			public static function config() {
				return new PgCacheRedirectTestConfigStub();
			}

			public static function component( $name ) {
				return null;
			}
		}

		/**
		 * Environment stub for the standalone page-cache redirect test.
		 */
		class Util_Environment {
			public static function host_port() {
				return 'example.com';
			}

			public static function is_https() {
				return false;
			}

			public static function is_preview_mode() {
				return false;
			}

			public static function parse_path( $path ) {
				return $path;
			}
		}
	}
}

namespace {
	if ( ! \function_exists( 'w3tc_apply_filters' ) ) {
		function w3tc_apply_filters( $tag, $value ) {
			return $value;
		}
	}

	if ( ! \function_exists( 'wp_parse_url' ) ) {
		function wp_parse_url( $url ) {
			return \parse_url( $url );
		}
	}

	require_once __DIR__ . '/../PgCache_ContentGrabber.php';

	class W3TC_Test_PgCacheRedirectGrabber extends \W3TC\PgCache_ContentGrabber {
		public function _passed_accept_files() {
			return true;
		}
	}

	$w3tc_pgcache_redirect_failures = 0;

	function pgcr_assert_same( $label, $expected, $actual ) {
		global $w3tc_pgcache_redirect_failures;

		if ( $expected === $actual ) {
			echo "[PASS] $label\n";
			return;
		}

		++$w3tc_pgcache_redirect_failures;
		echo "[FAIL] $label\n";
		echo '  Expected: ' . \var_export( $expected, true ) . "\n";
		echo '  Actual:   ' . \var_export( $actual, true ) . "\n";
	}

	function pgcr_set_private_property( $object, $property, $value ) {
		$reflection = new \ReflectionProperty( \W3TC\PgCache_ContentGrabber::class, $property );
		$reflection->setAccessible( true );
		$reflection->setValue( $object, $value );
	}

	function pgcr_get_private_property( $object, $property ) {
		$reflection = new \ReflectionProperty( \W3TC\PgCache_ContentGrabber::class, $property );
		$reflection->setAccessible( true );
		return $reflection->getValue( $object );
	}

	function pgcr_can_write_cache( $object, $buffer, $headers ) {
		$reflection = new \ReflectionMethod( \W3TC\PgCache_ContentGrabber::class, '_can_write_cache' );
		$reflection->setAccessible( true );
		return $reflection->invoke( $object, $buffer, $headers );
	}

	function pgcr_new_grabber() {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI']    = '/';

		$grabber = new W3TC_Test_PgCacheRedirectGrabber();

		pgcr_set_private_property( $grabber, '_caching', true );
		pgcr_set_private_property(
			$grabber,
			'_request_url_fragments',
			array(
				'host'        => 'example.com',
				'path'        => '/',
				'querystring' => '',
			)
		);
		pgcr_set_private_property(
			$grabber,
			'_page_key_extension',
			array(
				'useragent'    => '',
				'referrer'     => '',
				'cookie'       => '',
				'encryption'   => '',
				'group'        => '',
				'content_type' => '',
				'compression'  => false,
			)
		);

		return $grabber;
	}

	$redirect_grabber = pgcr_new_grabber();
	$redirect_headers = array(
		'kv'    => array(
			'location' => 'http://127.0.0.1',
		),
		'plain' => array(
			array(
				'name'  => 'Location',
				'value' => 'http://127.0.0.1',
			),
		),
	);

	pgcr_assert_same(
		'Redirect responses are not written to page cache',
		false,
		pgcr_can_write_cache( $redirect_grabber, '', $redirect_headers )
	);
	pgcr_assert_same(
		'Redirect rejection reason is recorded',
		'Redirect response',
		pgcr_get_private_property( $redirect_grabber, 'cache_reject_reason' )
	);
	pgcr_assert_same(
		'Redirect rejection status is recorded',
		'miss_redirect',
		pgcr_get_private_property( $redirect_grabber, 'process_status' )
	);

	$html_grabber = pgcr_new_grabber();
	$html_headers = array(
		'kv'    => array(),
		'plain' => array(),
	);

	pgcr_assert_same(
		'Non-empty HTML response can still be written',
		true,
		pgcr_can_write_cache( $html_grabber, '<html><body>ok</body></html>', $html_headers )
	);

	if ( $w3tc_pgcache_redirect_failures > 0 ) {
		exit( 1 );
	}
}
