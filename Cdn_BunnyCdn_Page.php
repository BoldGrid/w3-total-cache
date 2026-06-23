<?php
/**
 * File: Cdn_BunnyCdn_Page.php
 *
 * @since   2.6.0
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdn_BunnyCdn_Page
 *
 * @since 2.6.0
 */
class Cdn_BunnyCdn_Page {
	/**
	 * Handles the AJAX action to purge a specific URL from Bunny CDN.
	 *
	 * This method listens for the `w3tc_ajax_cdn_bunnycdn_purge_url` AJAX action and processes the URL purging request.
	 * It validates the URL, calls the Bunny CDN API to purge the URL, and sends a JSON response indicating success or failure.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public static function w3tc_ajax() {
		$w3tc_o = new Cdn_BunnyCdn_Page();

		\add_action( 'w3tc_ajax_cdn_bunnycdn_purge_url', array( $w3tc_o, 'w3tc_ajax_cdn_bunnycdn_purge_url' ) );
	}

	/**
	 * Enqueues scripts and localizes variables for Bunny CDN on the admin page.
	 *
	 * This method registers and enqueues the necessary JavaScript for Bunny CDN functionality in the W3 Total Cache admin
	 * panel. It also localizes important variables like authorization status and localized strings for use in the script.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public static function admin_print_scripts_w3tc_cdn() {
		$w3tc_config        = Dispatcher::config();
		$w3tc_is_authorized = ! empty( $w3tc_config->get_string( 'cdn.bunnycdn.account_api_key' ) ) &&
			( $w3tc_config->get_string( 'cdn.bunnycdn.pull_zone_id' ) || $w3tc_config->get_string( 'cdnfsd.bunnycdn.pull_zone_id' ) );

		\wp_register_script(
			'w3tc_cdn_bunnycdn',
			\plugins_url( 'Cdn_BunnyCdn_Page_View.js', W3TC_FILE ),
			array( 'jquery', 'w3tc-nonce', 'w3tc-lightbox' ),
			W3TC_VERSION,
			false
		);

		\wp_localize_script(
			'w3tc_cdn_bunnycdn',
			'W3TC_Bunnycdn',
			array(
				'is_authorized' => $w3tc_is_authorized,
				'lang'          => array(
					'empty_url'       => \esc_html__( 'No URL specified', 'w3-total-cache' ),
					'success_purging' => \esc_html__( 'Successfully purged URL', 'w3-total-cache' ),
					'error_purging'   => \esc_html__( 'Error purging URL', 'w3-total-cache' ),
					'error_ajax'      => \esc_html__( 'Error with AJAX', 'w3-total-cache' ),
				),
			)
		);

		\wp_enqueue_script( 'w3tc_cdn_bunnycdn' );
	}

	/**
	 * Displays the configuration settings for Bunny CDN in the W3 Total Cache settings page.
	 *
	 * This method includes the view file for Bunny CDN configuration options, allowing users to modify the Bunny CDN
	 * settings from the W3 Total Cache admin panel.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public static function w3tc_settings_cdn_boxarea_configuration() {
		$w3tc_config = Dispatcher::config();

		include W3TC_DIR . '/Cdn_BunnyCdn_Page_View.php';
	}

	/**
	 * Displays the URL purge settings in the W3 Total Cache admin panel.
	 *
	 * This method includes the view file for managing the Bunny CDN URL purge functionality, where users can specify
	 * URLs to purge from Bunny CDN.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public static function w3tc_purge_urls_box() {
		// Prevent duplicate renders when CDN and CDNFSD both hook this box.
		static $ran = false;

		if ( $ran ) {
			return;
		}

		$ran         = true;
		$w3tc_config = Dispatcher::config();

		include W3TC_DIR . '/Cdn_BunnyCdn_Page_View_Purge_Urls.php';
	}

	/**
	 * Processes the AJAX request to purge a specified URL from Bunny CDN.
	 *
	 * This method validates the provided URL, sends a purge request to the Bunny CDN API, and returns a JSON response
	 * indicating the success or failure of the operation. If the URL is invalid or an error occurs, a failure response
	 * is sent with the appropriate error message.
	 *
	 * Purging a URL will remove the file from the CDN cache and re-download it from your origin server.
	 * Please enter the exact CDN URL of each individual file.
	 * You can also purge folders or wildcard files using * inside of the URL path.
	 * Wildcard values are not supported if using Perma-Cache.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_bunnycdn_purge_url() {
		$w3tc_url = Util_Request::get_string( 'url' );

		// Check if URL starts with "http", starts with a valid protocol, and passes a URL validation check.
		if ( 0 !== \strpos( $w3tc_url, 'http' ) || ! \preg_match( '~^http(s?)://(.+)~i', $w3tc_url ) || ! \filter_var( $w3tc_url, FILTER_VALIDATE_URL ) ) {
			\wp_send_json_error(
				array( 'error_message' => \esc_html__( 'Invalid URL', 'w3-total-cache' ) ),
				400
			);
		}

		$w3tc_config          = Dispatcher::config();
		$w3tc_account_api_key = $w3tc_config->get_string( 'cdn.bunnycdn.account_api_key' );

		$api = new Cdn_BunnyCdn_Api( array( 'account_api_key' => $w3tc_account_api_key ) );

		// Try to delete pull zone.
		try {
			$api->purge(
				array(
					'url'   => \esc_url( $w3tc_url, array( 'http', 'https' ) ),
					'async' => true,
				)
			);
		} catch ( \Exception $ex ) {
			/**
			 * Log the full SDK exception detail server-side (URLs,
			 * request IDs, and any other request metadata embedded
			 * in the upstream string); return only a generic message
			 * to the admin so the AJAX response body doesn't carry
			 * that upstream context.
			 */
			Util_Debug::log( 'bunnycdn', 'purge failed: ' . $ex->getMessage() );
			\wp_send_json_error(
				array(
					'error_message' => \__( 'Bunny CDN purge request failed; see the W3TC debug log for details.', 'w3-total-cache' ),
				),
				422
			);
		}

		\wp_send_json_success();
	}
}
