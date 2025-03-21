<?php
/**
 * File: Cdnfsd_StackPath_Engine.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdnfsd_StackPath_Page
 */
class Cdnfsd_StackPath_Page {
	/**
	 * Enqueues the script for the StackPath CDN FSD on the performance page.
	 *
	 * @return void
	 */
	public static function admin_print_scripts_performance_page_w3tc_cdn() {
		wp_enqueue_script(
			'w3tc_cdn_stackpath_fsd',
			plugins_url( 'Cdnfsd_StackPath_Page_View.js', W3TC_FILE ),
			array( 'jquery' ),
			'1.0',
			false
		);
	}

	/**
	 * Displays the settings box for StackPath CDN FSD.
	 *
	 * @return void
	 */
	public static function w3tc_settings_box_cdnfsd() {
		$config = Dispatcher::config();
		include W3TC_DIR . '/Cdnfsd_StackPath_Page_View.php';
	}
}
