<?php
/**
 * File: Extension_Genesis_Page_View.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_Genesis_Page
 */
class Extension_Genesis_Page {
	/**
	 * Generates the extension page for the Genesis theme.
	 *
	 * @return void
	 */
	public static function w3tc_extension_page_genesis_theme() {
		$config = Dispatcher::config();
		include W3TC_DIR . '/Extension_Genesis_Page_View.php';
	}
}
