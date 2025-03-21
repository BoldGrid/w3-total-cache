<?php
/**
 * File: Cdn_Highwinds_Page.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_Highwinds_Page
 */
class Cdn_Highwinds_Page {
	/**
	 * Enqueues the Highwinds CDN-related JavaScript for the WordPress admin page.
	 *
	 * This method enqueues a custom JavaScript file (`Cdn_Highwinds_Page_View.js`) to be loaded in the WordPress admin area.
	 * The script is dependent on jQuery and is versioned as '1.0'. It is used to enhance the CDN configuration page.
	 *
	 * @return void
	 */
	public static function admin_print_scripts_w3tc_cdn() {
		wp_enqueue_script( 'w3tc_cdn_highwinds', plugins_url( 'Cdn_Highwinds_Page_View.js', W3TC_FILE ), array( 'jquery' ), '1.0', false );
	}

	/**
	 * Displays the CDN settings configuration box in the WordPress admin.
	 *
	 * This method retrieves the CDN configuration settings via the Dispatcher class and includes the HTML view file
	 * (`Cdn_Highwinds_Page_View.php`) to display the settings UI in the WordPress admin area. It is used to present the
	 * configuration options for the Highwinds CDN in the settings page.
	 *
	 * @return void
	 */
	public static function w3tc_settings_cdn_boxarea_configuration() {
		$config = Dispatcher::config();
		include W3TC_DIR . '/Cdn_Highwinds_Page_View.php';
	}
}
