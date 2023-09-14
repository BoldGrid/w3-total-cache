<?php
/**
 * File: Cdn_BunnyCdn_Page.php
 *
 * @since   X.X.X
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdn_BunnyCdn_Page
 *
 * @since X.X.X
 */
class Cdn_BunnyCdn_Page {
	/**
	 * W3TC AJAX.
	 *
	 * @since  X.X.X
	 * @static
	 *
	 * @return void
	 */
	public static function w3tc_ajax() {
		$o = new Cdn_BunnyCdn_Page();

		\add_action(
			'w3tc_ajax_cdn_bunnycdn_purge_url',
			array( $o, 'w3tc_ajax_cdn_bunnycdn_purge_url' )
		);
	}

	/**
	 * Enqueue scripts.
	 *
	 * Called from plugin-admin.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function admin_print_scripts_w3tc_cdn() {
		\wp_register_script(
			'w3tc_cdn_bunnycdn',
			\plugins_url( 'Cdn_BunnyCdn_Page_View.js', W3TC_FILE ),
			array( 'jquery' ),
			W3TC_VERSION
		);

		\wp_localize_script(
			'w3tc_cdn_bunnycdn',
			'W3TC_Bunnycdn_lang',
			array(
				'empty_url'       => \esc_html__( 'No URL specified', 'w3-total-cache' ),
				'success_purging' => \esc_html__( 'Successfully purged URL', 'w3-total-cache' ),
				'error_purging'   => \esc_html__( 'Error purging URL', 'w3-total-cache' ),
				'error_ajax'      => \esc_html__( 'Error with AJAX', 'w3-total-cache' ),
			)
		);

		\wp_enqueue_script( 'w3tc_cdn_bunnycdn' );
	}

	/**
	 * CDN settings.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function w3tc_settings_cdn_boxarea_configuration() {
		$config = Dispatcher::config();

		include W3TC_DIR . '/Cdn_BunnyCdn_Page_View.php';
	}

	/**
	 * Display purge URLs page.
	 *
	 * @since X.X.X
	 */
	public static function w3tc_purge_urls_box() {
		$config = Dispatcher::config();

		include W3TC_DIR . '/Cdn_BunnyCdn_Page_View_Purge_Urls.php';
	}

	/**
	 * W3TC AJAX: Purge a URL.
	 *
	 * Purging a URL will remove the file from the CDN cache and re-download it from your origin server.
	 * Please enter the exact CDN URL of each individual file.
	 * You can also purge folders or wildcard files using * inside of the URL path.
	 * Wildcard values are not supported if using Perma-Cache.
	 *
	 * @since X.X.X
	 */
	public function w3tc_ajax_cdn_bunnycdn_purge_url() {
		$url = Util_Request::get_string( 'url' );

		// Check if URL starts with "http", starts with a valid protocol, and passes a URL validation check.
		if ( 0 !== \strpos( $url, 'http' ) || ! \preg_match( '~^http(s?)://(.+)~i', $url ) || ! \filter_var( $url, FILTER_VALIDATE_URL ) ) {
			\wp_send_json_error(
				array( 'error_message' => \esc_html__( 'Invalid URL', 'w3-total-cache' ) ),
				400
			);
		}

		$config          = Dispatcher::config();
		$account_api_key = $config->get_string( 'cdn.bunnycdn.account_api_key' );

		$api = new Cdn_BunnyCdn_Api( array( 'account_api_key' => $account_api_key ) );

		// Try to delete pull zone.
		try {
			$api->purge(
				array(
					'url'   => \esc_url( $url, array( 'http', 'https' ) ),
					'async' => true,
				)
			);
		} catch ( \Exception $ex ) {
			\wp_send_json_error( array( 'error_message' => $ex->getMessage() ), 422 );
		}

		\wp_send_json_success();
	}
}
