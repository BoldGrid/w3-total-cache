<?php
/**
 * File: Extension_AiCrawler_Plugin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_AiCrawler_Plugin
 */
class Extension_AiCrawler_Plugin {
	/**
	 * Initialize the extension.
	 *
	 * @return void
	 */
	public function run() {
		// No frontend functionality yet.
	}
}

$aicrawler_plugin = new Extension_AiCrawler_Plugin();
$aicrawler_plugin->run();

if ( is_admin() ) {
	$admin = new Extension_AiCrawler_Plugin_Admin();
	$admin->run();
}
