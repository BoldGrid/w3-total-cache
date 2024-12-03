<?php
/**
 * File: Cdn_LimeLight_Page.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_LimeLight_Page
 */
class Cdn_LimeLight_Page {
	/**
	 * Enqueues the JavaScript file for the Limelight CDN settings page.
	 *
	 * This method enqueues a JavaScript file required for the Limelight CDN configuration page in the WordPress admin.
	 * It ensures that the script is loaded only when needed, and it is dependent on jQuery.
	 *
	 * @return void
	 */
	public static function admin_print_scripts_w3tc_cdn() {
		wp_enqueue_script( 'w3tc_cdn_limelight', plugins_url( 'Cdn_LimeLight_Page_View.js', W3TC_FILE ), array( 'jquery' ), '1.0', false );
	}

	/**
	 * Outputs the configuration box area for the Limelight CDN settings.
	 *
	 * This method is used to render the configuration box for the Limelight CDN settings page in the WordPress admin.
	 * It retrieves the configuration settings using the Dispatcher class and includes a view file for rendering.
	 *
	 * @return void
	 */
	public static function w3tc_settings_cdn_boxarea_configuration() {
		$config = Dispatcher::config();
		include W3TC_DIR . '/Cdn_LimeLight_Page_View.php';
	}
}
