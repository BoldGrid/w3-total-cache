<?php
/**
 * File: Cdn_RackSpaceCloudFiles_Page.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_RackSpaceCloudFiles_Page
 */
class Cdn_RackSpaceCloudFiles_Page {
	/**
	 * Enqueues JavaScript specific to the Rackspace Cloud Files CDN configuration.
	 *
	 * This method loads the required JavaScript file for the Rackspace Cloud Files CDN settings page.
	 * It ensures that the script is available in the WordPress admin area when configuring the CDN.
	 *
	 * @return void
	 */
	public static function admin_print_scripts_w3tc_cdn() {
		wp_enqueue_script( 'w3tc_cdn_rackspace', plugins_url( 'Cdn_RackSpaceCloudFiles_Page_View.js', W3TC_FILE ), array( 'jquery' ), '1.0', false );
	}

	/**
	 * Outputs the configuration box area for the Rackspace Cloud Files CDN settings.
	 *
	 * This method generates the HTML for the configuration area of the Rackspace Cloud Files CDN
	 * settings. It retrieves and validates the API key, determines the CDN hosts for HTTP and HTTPS,
	 * and includes the view file to display the settings interface.
	 *
	 * @return void
	 */
	public static function w3tc_settings_cdn_boxarea_configuration() {
		$config     = Dispatcher::config();
		$api_key    = $config->get_string( 'cdn.rscf.key' );
		$authorized = ! empty( $api_key );

		$cdn_host_http  = '';
		$cdn_host_https = '';

		if ( $authorized ) {
			try {
				$cdn            = Dispatcher::component( 'Cdn_Core' )->get_cdn();
				$cdn_host_http  = $cdn->get_host_http();
				$cdn_host_https = $cdn->get_host_https();
			} catch ( \Exception $ex ) {
				$cdn_host_http  = 'failed to obtain';
				$cdn_host_https = 'failed to obtain';
			}
		}

		include W3TC_DIR . '/Cdn_RackSpaceCloudFiles_Page_View.php';
	}
}
