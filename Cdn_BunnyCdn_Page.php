<?php
/**
 * File: Cdn_BunnyCdn_Page.php
 *
 * @since X.X.X
 *
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
	 * Enqueue scripts.
	 *
	 * Called from plugin-admin.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function admin_print_scripts_w3tc_cdn() {
		wp_enqueue_script(
			'w3tc_cdn_bunnycdn',
			plugins_url( 'Cdn_BunnyCdn_Page_View.js', W3TC_FILE ),
			array( 'jquery' ),
			'1.0'
		);
	}

	/**
	 * CDN settings.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function w3tc_settings_cdn_boxarea_configuration() {
		$config          = Dispatcher::config();
		$account_api_key = $config->get_string( 'cdn.bunnycdn.account_api_key' );
		$storage_api_key = $config->get_string( 'cdn.bunnycdn.storage_api_key' );
		$stream_api_key  = $config->get_string( 'cdn.bunnycdn.stream_api_key' );
		$is_authorized   = ! empty( $account_api_key ) && $config->get_string( 'cdn.bunnycdn.authorized_time' );

		include W3TC_DIR . '/Cdn_BunnyCdn_Page_View.php';
	}
}
