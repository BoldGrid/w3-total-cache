<?php
/**
 * File: Extension_AiCrawler_Plugin.php
 *
 * @package W3TC
 * @since   X.X.X
 */

namespace W3TC;

/**
 * Class Extension_AiCrawler_Plugin
 *
 * @since X.X.X
 */
class Extension_AiCrawler_Plugin {
	/**
	 * Initialize the extension.
	 *
	 * @return void
	 * @since  X.X.X
	 */
	public function run() {
		// No frontend functionality yet.
	}

	/**
	 * Gets the enabled status of the extension.
	 *
	 * @since X.X.X
	 *
	 * @return bool Whether the AI Crawler extension is active.
	 */
	public static function is_enabled() {
		$config            = Dispatcher::config();
		$extensions_active = $config->get_array( 'extensions.active' );

		// TODO: Check for Central environement and add to return.

		return array_key_exists( 'aicrawler', $extensions_active );
	}

	/**
	 * Checks if the current environment supports the AI Crawler extension.
	 *
	 * Initially the extension is limited to specific hosting platforms.
	 * This helper centralizes the environment detection so support can be
	 * expanded in the future.
	 *
	 * @since X.X.X
	 *
	 * @static
	 *
	 * @return bool
	 */
	public static function is_allowed() {
		// TODO: add checks for valid environments.
		return true;
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
