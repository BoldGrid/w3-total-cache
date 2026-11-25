<?php
/**
 * Namespaced stubs for the standalone document_root() normalization test.
 *
 * Two modes, picked by the constant the child process defines before loading this file:
 *
 *  - DRN_STUB_UTIL_ENV: a `\W3TC\Util_Environment` stub with a test-settable `document_root()`
 *    and a verbatim copy of `normalize_path()`, so the REAL `Cdn_Util` / `Cdn_Core` callers run
 *    against a controlled Windows-style document root.
 *  - otherwise: the real `Util_Environment` is loaded by the test; only `\W3TC\Dispatcher::config()`
 *    and the WordPress functions `document_root()` / `apache_cache_uri_path()` reach are stubbed.
 *
 * Kept in a separate file because the runtime classes live under `namespace` blocks,
 * which cannot be mixed with the test's top-level (global-namespace) body.
 *
 * @package W3TC\Tests
 * @since   X.X.X
 */

namespace W3TC {
	if ( \defined( 'DRN_STUB_UTIL_ENV' ) && ! \class_exists( 'W3TC\\Util_Environment' ) ) {
		/**
		 * Minimal Util_Environment stub for the CDN callers.
		 */
		class Util_Environment {
			/**
			 * Test-settable document root.
			 *
			 * @var string
			 */
			public static $docroot = '';

			/**
			 * Test-settable uploads basedir.
			 *
			 * @var string
			 */
			public static $upload_basedir = '';

			/**
			 * Returns the (test-controlled) document root.
			 *
			 * @return string
			 */
			public static function document_root() {
				return self::$docroot;
			}

			/**
			 * Verbatim copy of the production implementation.
			 *
			 * @param string $path Path.
			 *
			 * @return string
			 */
			public static function normalize_path( $path ) {
				$path = \preg_replace( '~[/\\\]+~', '/', $path );
				$path = \rtrim( $path, '/' );

				return $path;
			}

			/**
			 * Site installed at the document root.
			 *
			 * @return string
			 */
			public static function site_url_uri() {
				return '/';
			}

			/**
			 * Returns the (test-controlled) uploads dir.
			 *
			 * @return array
			 */
			public static function wp_upload_dir() {
				return array( 'basedir' => self::$upload_basedir );
			}
		}
	}

	if ( ! \class_exists( 'W3TC\\Dispatcher' ) ) {
		/**
		 * Dispatcher stub: config() with docroot_fix.enable off.
		 */
		class Dispatcher {
			/**
			 * Returns a config stub.
			 *
			 * @return object
			 */
			public static function config() {
				return new class() {
					/**
					 * Every boolean option is off.
					 *
					 * @param string $key Key.
					 *
					 * @return bool
					 */
					public function get_boolean( $key ) {
						return false;
					}
				};
			}
		}
	}
}

namespace {
	if ( ! \function_exists( 'untrailingslashit' ) ) {
		/**
		 * WordPress shim.
		 *
		 * @param string $value Value.
		 *
		 * @return string
		 */
		function untrailingslashit( $value ) {
			return \rtrim( $value, '/\\' );
		}
	}

	if ( ! \function_exists( 'trailingslashit' ) ) {
		/**
		 * WordPress shim.
		 *
		 * @param string $value Value.
		 *
		 * @return string
		 */
		function trailingslashit( $value ) {
			return untrailingslashit( $value ) . '/';
		}
	}

	if ( ! \function_exists( 'wp_parse_url' ) ) {
		/**
		 * WordPress shim.
		 *
		 * @param string $url       URL.
		 * @param int    $component Component.
		 *
		 * @return mixed
		 */
		function wp_parse_url( $url, $component = -1 ) {
			return \parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		}
	}

	if ( ! \function_exists( 'network_site_url' ) ) {
		/**
		 * WordPress shim: site at the domain root.
		 *
		 * @param string $path Path.
		 *
		 * @return string
		 */
		function network_site_url( $path = '' ) {
			return 'http://example.com' . $path;
		}
	}

	if ( ! \function_exists( 'get_option' ) ) {
		/**
		 * WordPress shim: home and siteurl are the same.
		 *
		 * @param string $name Option.
		 *
		 * @return string
		 */
		function get_option( $name ) {
			return 'http://example.com';
		}
	}

	if ( ! \function_exists( 'set_url_scheme' ) ) {
		/**
		 * WordPress shim.
		 *
		 * @param string $url    URL.
		 * @param string $scheme Scheme.
		 *
		 * @return string
		 */
		function set_url_scheme( $url, $scheme = null ) {
			return $url;
		}
	}
}
