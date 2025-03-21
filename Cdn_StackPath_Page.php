<?php
/**
 * File: Cdn_StackPath_Page.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_StackPath_Page
 */
class Cdn_StackPath_Page {
	/**
	 * Enqueues the necessary JavaScript for the StackPath CDN admin page.
	 *
	 * @return void
	 */
	public static function admin_print_scripts_w3tc_cdn() {
		wp_enqueue_script( 'w3tc_cdn_stackpath', plugins_url( 'Cdn_StackPath_Page_View.js', W3TC_FILE ), array( 'jquery' ), '1.0', false );
	}

	/**
	 * Renders the StackPath CDN settings box in the configuration page.
	 *
	 * Loads configuration details such as the authorization key, zone ID, and
	 * StackPath CDN domains. These values are used to populate the settings
	 * form in the admin interface.
	 *
	 * @return void
	 */
	public static function w3tc_settings_cdn_boxarea_configuration() {
		$config  = Dispatcher::config();
		$key     = $config->get_string( 'cdn.stackpath.authorization_key' );
		$zone    = $config->get_string( 'cdn.stackpath.zone_id' );
		$domains = $config->get_array( 'cdn.stackpath.domain' );

		$authorized   = ! empty( $key ) && ! empty( $zone );
		$http_domain  = isset( $domains['http_default'] ) ? $domains['http_default'] : null;
		$https_domain = isset( $domains['https_default'] ) ? $domains['https_default'] : null;

		include W3TC_DIR . '/Cdn_StackPath_Page_View.php';
	}
}
