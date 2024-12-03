<?php
/**
 * File: Cdnfsd_StackPath2_Page.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdnfsd_StackPath2_Page
 */
class Cdnfsd_StackPath2_Page {
	/**
	 * Enqueues the script for the StackPath2 CDN page in the WordPress admin.
	 *
	 * @return void
	 */
	public static function admin_print_scripts_performance_page_w3tc_cdn() {
		wp_enqueue_script(
			'w3tc_cdn_stackpath2_fsd',
			plugins_url( 'Cdnfsd_StackPath2_Page_View.js', W3TC_FILE ),
			array( 'jquery' ),
			'1.0',
			false
		);
	}

	/**
	 * Outputs the settings box for the StackPath2 CDN in the WordPress admin.
	 *
	 * @return void
	 */
	public static function w3tc_settings_box_cdnfsd() {
		$config = Dispatcher::config();
		include W3TC_DIR . '/Cdnfsd_StackPath2_Page_View.php';
	}
}
