<?php
/**
 * File: Extension_AlwaysCached_Page.php
 *
 * Controls the AlwaysCached settings page.
 *
 * @since 2.5.1
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * AlwaysCached Page.
 *
 * @since 2.5.1
 */
class Extension_AlwaysCached_Page {

	/**
	 * Prints the admin scripts.
	 *
	 * @since 2.5.1
	 *
	 * @return void
	 */
	public static function admin_print_scripts() {
		if ( 'alwayscached' === Util_Request::get_string( 'extension' ) ) {
			wp_enqueue_script(
				'w3tc_extension_alwayscached',
				plugins_url( 'Extension_AlwaysCached_Page_View.js', W3TC_FILE ),
				array(),
				'1.0',
				true
			);
		}
	}

	/**
	 * Prints the settings page.
	 *
	 * @since 2.5.1
	 *
	 * @return void
	 */
	public static function w3tc_extension_page_alwayscached() {
		$config = Dispatcher::config();
		include W3TC_DIR . '/Extension_AlwaysCached_Page_View.php';
	}

	/**
	 * Adds AJAX actions.
	 *
	 * @since 2.5.1
	 *
	 * @return void
	 */
	public static function w3tc_ajax() {
		add_action(
			'w3tc_ajax_extension_alwayscached_queue',
			array(
				'\W3TC\Extension_AlwaysCached_Page',
				'w3tc_ajax_extension_alwayscached_queue',
			)
		);
	}

	/**
	 * Prints the queue content via AJAX.
	 *
	 * @since 2.5.1
	 *
	 * @return void
	 */
	public static function w3tc_ajax_extension_alwayscached_queue() {
		include W3TC_DIR . '/Extension_AlwaysCached_Page_Queue_View.php';
		exit();
	}
}
