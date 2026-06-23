<?php
/**
 * Namespaced stubs for the standalone minify f_array security regression test.
 *
 * Defines just enough of the W3TC + bundled-Minify runtime to exercise the real
 * `\W3TCL\Minify\Minify_Controller_MinApp::setupSources()` sink in isolation:
 *
 *  - `\W3TC\Util_Environment` — faithful copies of `realpath()` / `normalize_path()`
 *    (lexical path collapse, no filesystem access — mirrors production exactly) plus
 *    a test-settable `document_root()`.
 *  - `\W3TCL\Minify\Minify` — the three symbols `Minify_Source` / `Minify_Controller_MinApp`
 *    reference at runtime (`URL_DEBUG`, `$uploaderHoursBehind`, `$recoverableError`).
 *  - WordPress `sanitize_text_field()` / `wp_unslash()` shims.
 *
 * Kept in a separate file because the runtime classes live under `namespace` blocks,
 * which cannot be mixed with the test's top-level (global-namespace) body.
 *
 * @package W3TC\Tests
 * @since   2.10.0
 */

namespace W3TC {
	if ( ! \class_exists( 'W3TC\\Util_Environment' ) ) {
		/**
		 * Minimal Util_Environment stub. `realpath()` / `normalize_path()` are
		 * verbatim copies of the production implementation so the test resolves
		 * paths exactly as the live code does.
		 */
		class Util_Environment {
			/**
			 * Test-settable document root.
			 *
			 * @var string
			 */
			public static $docroot = '';

			/**
			 * Returns the (test-controlled) document root.
			 *
			 * @return string
			 */
			public static function document_root() {
				return self::$docroot;
			}

			/**
			 * Lexically normalizes a path (collapses separators, trims trailing slash).
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
			 * Lexically collapses `.` / `..` segments without touching the filesystem.
			 *
			 * @param string $path Path.
			 *
			 * @return string
			 */
			public static function realpath( $path ) {
				$path      = self::normalize_path( $path );
				$parts     = \explode( '/', $path );
				$absolutes = array();

				foreach ( $parts as $part ) {
					if ( '.' === $part ) {
						continue;
					}

					if ( '..' === $part ) {
						\array_pop( $absolutes );
					} else {
						$absolutes[] = $part;
					}
				}

				return \implode( '/', $absolutes );
			}
		}
	}
}

namespace W3TCL\Minify {
	if ( ! \class_exists( 'W3TCL\\Minify\\Minify' ) ) {
		/**
		 * Stub of the bundled Minify facade — only the members the controller
		 * and source classes touch at runtime are defined.
		 */
		class Minify {
			const URL_DEBUG = 'https://example.com/minify-debug';

			/**
			 * Clock-skew offset (hours). Zero for tests.
			 *
			 * @var int
			 */
			public static $uploaderHoursBehind = 0;

			/**
			 * Last recoverable error.
			 *
			 * @var mixed
			 */
			public static $recoverableError = null;
		}
	}
}

namespace {
	if ( ! \function_exists( 'sanitize_text_field' ) ) {
		/**
		 * Trivial sanitize_text_field shim.
		 *
		 * @param mixed $value Value.
		 *
		 * @return mixed
		 */
		function sanitize_text_field( $value ) {
			return \is_string( $value ) ? \trim( $value ) : $value;
		}
	}

	if ( ! \function_exists( 'wp_unslash' ) ) {
		/**
		 * Trivial wp_unslash shim.
		 *
		 * @param mixed $value Value.
		 *
		 * @return mixed
		 */
		function wp_unslash( $value ) {
			return \is_string( $value ) ? \stripslashes( $value ) : $value;
		}
	}
}
