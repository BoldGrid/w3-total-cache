<?php
/**
 * File: Cdnfsd_TransparentCDN_Page.php
 *
 * @package W3TC
 *
 * @since 0.15.0
 */

namespace W3TC;

/**
 * Class: Cdnfsd_TransparentCDN_Page
 *
 * @since 2.0.0
 */
class Cdnfsd_TransparentCDN_Page {
	/**
	 * Registers and enqueues the script for the transparent CDN settings page.
	 *
	 * @return void
	 */
	public static function admin_test_api_parameters_transparentcdn() {
		wp_register_script(
			'w3tc-cdn-admin-ui',
			plugins_url( 'pub/js/cdn-admin-ui.js', W3TC_FILE ),
			array(),
			W3TC_VERSION,
			false
		);
		wp_register_script(
			'w3tc_cdn_transparentcdn_fsd',
			plugins_url( 'Cdnfsd_TransparentCDN_Page_View.js', W3TC_FILE ),
			array( 'jquery', 'w3tc-cdn-admin-ui' ),
			'1.0',
			false
		);

		wp_localize_script(
			'w3tc_cdn_transparentcdn_fsd',
			'transparent_configuration_strings',
			array(
				'test_string'         => __( 'Test the API parameters offered for you site', 'w3-total-cache' ),
				'test_success'        => __( 'Ok. Correct parameters', 'w3-total-cache' ),
				'test_failure_prefix' => __( 'Error. Check your parameters and try again or ', 'w3-total-cache' ),
				'test_failure_link'   => __( 'contact support', 'w3-total-cache' ),
				'test_failure_suffix' => __( '.', 'w3-total-cache' ),
				'support_url'         => esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_support' ) ),
			)
		);

		wp_enqueue_script(
			'w3tc_cdn_transparentcdn_fsd',
			plugins_url( 'Cdnfsd_TransparentCDN_Page_View.js', W3TC_FILE ),
			array( 'jquery', 'w3tc-cdn-admin-ui' ),
			'1.0',
			true
		);
	}

	/**
	 * Displays the settings box for the Cdnfsd TransparentCDN configuration.
	 *
	 * @return void
	 */
	public static function w3tc_settings_box_cdnfsd() {
		$w3tc_config = Dispatcher::config();
		require W3TC_DIR . '/Cdnfsd_TransparentCDN_Page_View.php';
	}
}
