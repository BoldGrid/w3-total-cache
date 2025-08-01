<?php
/**
 * File: Extension_AiCrawler_Plugin.php
 *
 * @package W3TC
 * @since   x.x.x
 */

namespace W3TC;

/**
 * Class Extension_AiCrawler_Plugin
 *
 * @since x.x.x
 */
class Extension_AiCrawler_Plugin {
	/**
	 * Initialize the extension.
	 *
	 * @return void
	 * @since  x.x.x
	 */
	public function run() {
		// No frontend functionality yet.
	}
}

add_action(
	'wp_loaded',
	function () {
		( new Extension_AiCrawler_Plugin() )->run();

		if ( is_admin() ) {
			( new Extension_AiCrawler_Plugin_Admin() )->run();
		}
	}
);
