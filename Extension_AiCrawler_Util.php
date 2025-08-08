<?php
/**
 * File: Extension_AiCrawler_Util.php
 *
 * @package W3TC
 * @since   X.X.X
 */

namespace W3TC;

/**
 * Class: Extension_AiCrawler_Util
 *
 * @since X.X.X
 */
class Extension_AiCrawler_Util {
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

		// @todo: Check for Central environment and add to return.

		return array_key_exists( 'aicrawler', $extensions_active );
	}

	/**
	 * Checks if the current environment supports the AI Crawler extension.
	 *
	 * Initially the extension is limited to specific hosting platforms.
	 * This helper centralizes the environment detection so support can be
	 * expanded in the future.
	 *
	 * @since  X.X.X
	 * @static
	 *
	 * @return bool
	 */
	public static function is_allowed() {
		// @todo: add checks for valid environments.
		return true;
	}
}
