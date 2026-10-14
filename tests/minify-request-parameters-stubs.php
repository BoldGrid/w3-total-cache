<?php
/**
 * Namespaced stubs for the standalone minify request-parameter test.
 *
 * @package W3TC\Tests
 * @since   X.X.X
 */

namespace W3TC;

if ( ! class_exists( __NAMESPACE__ . '\\Util_Environment', false ) ) {
	/**
	 * Supplies a cache directory that has no generated minify files.
	 *
	 * @since X.X.X
	 */
	class Util_Environment {
		/**
		 * Returns a nonexistent cache directory.
		 *
		 * @since X.X.X
		 *
		 * @return string
		 */
		public static function cache_blog_minify_dir() {
			return __DIR__ . '/minify-request-parameters-missing';
		}
	}
}
