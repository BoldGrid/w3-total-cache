<?php
/**
 * File: Cdn_StackPath2_Page.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_StackPath2_Page
 */
class Cdn_StackPath2_Page {
	/**
	 * Enqueues the necessary JavaScript file for the StackPath CDN settings page.
	 *
	 * This method is responsible for adding the `Cdn_StackPath2_Page_View.js` script
	 * to the WordPress admin interface, ensuring it is loaded only when required.
	 *
	 * @return void
	 */
	public static function admin_print_scripts_w3tc_cdn() {
		wp_enqueue_script( 'w3tc_cdn_stackpath', plugins_url( 'Cdn_StackPath2_Page_View.js', W3TC_FILE ), array( 'jquery' ), '1.0', false );
	}

	/**
	 * Outputs the CDN configuration settings box for the StackPath CDN integration.
	 *
	 * This method retrieves the necessary configuration values, including the client ID,
	 * site ID, and domain information, and then includes the view file responsible
	 * for rendering the configuration UI.
	 *
	 * @return void
	 */
	public static function w3tc_settings_cdn_boxarea_configuration() {
		$config    = Dispatcher::config();
		$client_id = $config->get_string( 'cdn.stackpath2.client_id' );
		$site_id   = $config->get_string( 'cdn.stackpath2.site_id' );
		$domains   = $config->get_array( 'cdn.stackpath2.domain' );

		$authorized   = ! empty( $client_id ) && ! empty( $site_id );
		$http_domain  = isset( $domains['http_default'] ) ? $domains['http_default'] : null;
		$https_domain = isset( $domains['https_default'] ) ? $domains['https_default'] : null;

		include W3TC_DIR . '/Cdn_StackPath2_Page_View.php';
	}
}
