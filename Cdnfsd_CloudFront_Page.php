<?php
/**
 * File: Cdnfsd_CloudFront_Page.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdnfsd_CloudFront_Page
 */
class Cdnfsd_CloudFront_Page {
	/**
	 * Enqueues scripts required for the CloudFront FSD settings page.
	 *
	 * @return void
	 */
	public static function admin_print_scripts_performance_page_w3tc_cdn() {
		wp_enqueue_script(
			'w3tc_cdn_cloudfront_fsd',
			plugins_url( 'Cdnfsd_CloudFront_Page_View.js', W3TC_FILE ),
			array( 'jquery' ),
			'1.0',
			false
		);
	}

	/**
	 * Renders the settings box for the CloudFront FSD integration.
	 *
	 * @return void
	 */
	public static function w3tc_settings_box_cdnfsd() {
		$config = Dispatcher::config();
		include W3TC_DIR . '/Cdnfsd_CloudFront_Page_View.php';
	}
}
