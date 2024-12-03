<?php
/**
 * File: Cdnfsd_BunnyCdn_Page.php
 *
 * @since   2.6.0
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdnfsd_BunnyCdn_Page
 *
 * @since 2.6.0
 */
class Cdnfsd_BunnyCdn_Page {
	/**
	 * Enqueue scripts.
	 *
	 * @since 2.6.0
	 */
	public static function admin_print_scripts_performance_page_w3tc_cdn() {
		wp_enqueue_script(
			'w3tc_cdn_bunnycdn_fsd',
			plugins_url( 'Cdnfsd_BunnyCdn_Page_View.js', W3TC_FILE ),
			array( 'jquery' ),
			'1.0',
			false
		);
	}

	/**
	 * Display settings page.
	 *
	 * @since 2.6.0
	 */
	public static function w3tc_settings_box_cdnfsd() {
		$config = Dispatcher::config();

		include W3TC_DIR . '/Cdnfsd_BunnyCdn_Page_View.php';
	}
}
