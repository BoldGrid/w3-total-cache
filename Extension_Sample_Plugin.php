<?php
/**
 * File: Extension_Sample_Plugin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_Sample_Plugin
 */
class Extension_Sample_Plugin {
	/**
	 * Initialize the extension.
	 *
	 * @return void
	 */
	public function run() {
		// No frontend functionality yet.
	}
}

$sample_plugin = new Extension_Sample_Plugin();
$sample_plugin->run();

if ( is_admin() ) {
	$admin = new Extension_Sample_Plugin_Admin();
	$admin->run();
}
