<?php
/**
 * File: Cdnfsd_Plugin_Admin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdnfsd_Plugin_Admin
 */
class Cdnfsd_Plugin_Admin {
	/**
	 * Registers actions based on the selected CDNFSD engine.
	 *
	 * @return void
	 */
	public function run() {
		$w3tc_c             = Dispatcher::config();
		$w3tc_cdnfsd_engine = $w3tc_c->get_string( 'cdnfsd.engine' );

		// Attach to actions without firing class loading at all without need.
		switch ( $w3tc_cdnfsd_engine ) {
			case 'cloudfront':
				add_action( 'w3tc_ajax', array( '\W3TC\Cdnfsd_CloudFront_Popup', 'w3tc_ajax' ) );
				add_action( 'w3tc_settings_box_cdnfsd', array( '\W3TC\Cdnfsd_CloudFront_Page', 'w3tc_settings_box_cdnfsd' ) );
				break;
			case 'transparentcdn':
				add_action( 'init', array( '\W3TC\Cdnfsd_TransparentCDN_Page', 'admin_test_api_parameters_transparentcdn' ) );
				add_action( 'w3tc_settings_box_cdnfsd', array( '\W3TC\Cdnfsd_TransparentCDN_Page', 'w3tc_settings_box_cdnfsd' ) );
				break;
			case 'bunnycdn':
				add_action( 'w3tc_ajax', array( '\W3TC\Cdnfsd_BunnyCdn_Popup', 'w3tc_ajax' ) );
				add_action( 'w3tc_settings_box_cdnfsd', array( '\W3TC\Cdnfsd_BunnyCdn_Page', 'w3tc_settings_box_cdnfsd' ) );
				break;
			default:
				break;
		}

		add_action( 'w3tc_settings_general_boxarea_cdn_footer', array( $this, 'w3tc_settings_general_boxarea_cdn_footer' ) );
	}

	/**
	 * Displays the CDNFSD settings footer for the general settings page.
	 *
	 * @return void
	 */
	public function w3tc_settings_general_boxarea_cdn_footer() {
		$w3tc_config          = Dispatcher::config();
		$w3tc_cdnfsd_enabled  = $w3tc_config->get_boolean( 'cdnfsd.enabled' );
		$w3tc_cdnfsd_engine   = $w3tc_config->get_string( 'cdnfsd.engine' );
		$w3tc_is_pro          = Util_Environment::is_w3tc_pro( $w3tc_config );
		$cdnfsd_engine_values = array();
		$tag                  = '';

		$cdnfsd_engine_values[''] = array(
			'label' => 'Select a provider',
		);

		$cdnfsd_engine_values['bunnycdn'] = array(
			'label' => __( 'Bunny CDN (recommended)', 'w3-total-cache' ),
		);

		$cdnfsd_engine_values['cloudfront'] = array(
			'label' => __( 'Amazon CloudFront', 'w3-total-cache' ),
		);

		$cdnfsd_engine_values['cloudflare'] = array(
			'label'    => __( 'Cloudflare (extension not activated)', 'w3-total-cache' ),
			'disabled' => true,
		);

		$cdnfsd_engine_values['transparentcdn'] = array(
			'label' => __( 'TransparentCDN', 'w3-total-cache' ),
		);

		if ( 'cloudfront' === $w3tc_cdnfsd_engine ) {
			$tag = 'https://api.w3-edge.com/v1/redirects/faq/cdn-fsd/cloudfront';
		}

		$cdnfsd_engine_extra_description = empty( $tag ) ? '' : ' See <a href="' . $tag . '">setup instructions</a>';

		include W3TC_DIR . '/Cdnfsd_GeneralPage_View.php';
	}
}
