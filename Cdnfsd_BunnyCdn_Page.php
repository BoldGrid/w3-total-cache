<?php
/**
 * File: Cdnfsd_BunnyCdn_Page.php
 *
 * @since   X.X.X
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdnfsd_BunnyCdn_Page
 *
 * @since X.X.X
 */
class Cdnfsd_BunnyCdn_Page {
	/**
	 * Enqueue scripts.
	 *
	 * @since X.X.X
	 */
	public static function admin_print_scripts_performance_page_w3tc_cdn() {
		wp_enqueue_script(
			'w3tc_cdn_bunnycdn_fsd',
			plugins_url( 'Cdnfsd_BunnyCdn_Page_View.js', W3TC_FILE ),
			array( 'jquery' ),
			'1.0'
		);
	}

	/**
	 * Display settings page.
	 *
	 * @since X.X.X
	 */
	public static function w3tc_settings_box_cdnfsd() {
		$config = Dispatcher::config();

		include W3TC_DIR . '/Cdnfsd_BunnyCdn_Page_View.php';
	}
}
