<?php
/**
 * File: Cdn_RackSpaceCdn_Page.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_RackSpaceCdn_Page
 */
class Cdn_RackSpaceCdn_Page {
	/**
	 * Adds Rackspace CDN-specific admin action handlers.
	 *
	 * This method integrates the Rackspace CDN admin action handlers into the W3 Total Cache admin framework,
	 * enabling the handling of actions specific to the Rackspace CDN configuration.
	 *
	 * @param array $handlers An array of existing admin action handlers.
	 *
	 * @return array The updated array of admin action handlers, including the Rackspace CDN handler.
	 */
	public static function w3tc_admin_actions( $handlers ) {
		$handlers['cdn_rackspace_cdn'] = 'Cdn_RackSpaceCdn_AdminActions';

		return $handlers;
	}

	/**
	 * Enqueues Rackspace CDN-specific JavaScript for the admin area.
	 *
	 * This method loads the JavaScript file necessary for the Rackspace CDN admin page in W3 Total Cache.
	 * It ensures the script is added to the admin area when needed.
	 *
	 * @return void
	 */
	public static function admin_print_scripts_w3tc_cdn() {
		wp_enqueue_script( 'w3tc_cdn_rackspace', plugins_url( 'Cdn_RackSpaceCdn_Page_View.js', W3TC_FILE ), array( 'jquery' ), '1.0', false );
	}

	/**
	 * Renders the Rackspace CDN configuration box area in the settings.
	 *
	 * This method outputs the HTML for the Rackspace CDN configuration box in the W3 Total Cache settings.
	 * It retrieves the necessary configuration values, checks authorization, and prepares access URLs
	 * before including the view file.
	 *
	 * @return void
	 */
	public static function w3tc_settings_cdn_boxarea_configuration() {
		$config     = Dispatcher::config();
		$api_key    = $config->get_string( 'cdn.rackspace_cdn.api_key' );
		$authorized = ! empty( $api_key );

		$access_url_full = '';
		if ( $authorized ) {
			$p               = $config->get_string( 'cdn.rackspace_cdn.service.protocol' );
			$access_url_full = ( 'https' === $p ? 'https://' : 'http://' ) . $config->get_string( 'cdn.rackspace_cdn.service.access_url' );
		}

		include W3TC_DIR . '/Cdn_RackSpaceCdn_Page_View.php';
	}
}
