<?php
/**
 * Minimal W3TC namespace stubs for the standalone mfunc-security regression test.
 *
 * `tests/test-mfunc-security.php` is run with `php tests/test-mfunc-security.php`
 * — no WordPress or autoloader is bootstrapped — but
 * `PgCache_ContentGrabber::__construct()` looks up its dependencies through
 * `\W3TC\Dispatcher::config()` and `\W3TC\Util_Environment`.  These tiny stubs
 * satisfy that lookup without eval() so the security regression test does not
 * itself depend on the very primitive (eval) it exists to prove was removed.
 *
 * Loaded only when the real classes are not already in scope (i.e. only in the
 * standalone-CLI test path; PHPUnit/WordPress runs use the real classes).
 *
 * @package W3TC\Tests
 * @since   2.9.5
 */

namespace W3TC;

if ( ! class_exists( __NAMESPACE__ . '\\Dispatcher', false ) ) {

	/**
	 * No-op config used in standalone tests.  Returns benign defaults for every key.
	 */
	class MfuncSecurityTestConfigStub {
		public function get_boolean( $k ) {
			return false;
		}
		public function get_integer( $k ) {
			return 0;
		}
		public function get_string( $k ) {
			return '';
		}
	}

	/**
	 * Minimal Dispatcher used in standalone tests — returns the config stub and
	 * `null` for every component lookup so `PgCache_ContentGrabber` can construct.
	 */
	class Dispatcher {
		public static function config() {
			return new MfuncSecurityTestConfigStub();
		}
		public static function component( $name ) {
			return null;
		}
	}

	/**
	 * Minimal Util_Environment used in standalone tests.
	 */
	class Util_Environment {
		public static function host_port() {
			return 'localhost';
		}
		public static function is_https() {
			return false;
		}
	}
}
