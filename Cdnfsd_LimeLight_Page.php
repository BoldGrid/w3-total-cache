<?php
/**
 * File: Cdnfsd_LimeLight_Page.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdnfsd_LimeLight_Page
 */
class Cdnfsd_LimeLight_Page {
	/**
	 * Enqueues the JavaScript file for the Limelight CDN page in the WordPress admin.
	 *
	 * @return void
	 */
	public static function admin_print_scripts_performance_page_w3tc_cdn() {
		wp_enqueue_script(
			'w3tc_cdnfsd_limelight',
			plugins_url( 'Cdnfsd_LimeLight_Page_View.js', W3TC_FILE ),
			array( 'jquery' ),
			'1.0',
			false
		);
	}

	/**
	 * Renders the CDN settings box for the Cdnfsd LimeLight integration in the WordPress admin.
	 *
	 * @return void
	 */
	public static function w3tc_settings_box_cdnfsd() {
		$config = Dispatcher::config();
		include W3TC_DIR . '/Cdnfsd_LimeLight_Page_View.php';
	}
}
