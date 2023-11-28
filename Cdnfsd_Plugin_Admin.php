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
	 * Run.
	 */
	public function run() {
		$c             = Dispatcher::config();
		$cdnfsd_engine = $c->get_string( 'cdnfsd.engine' );

		// Attach to actions without firing class loading at all without need.
		switch ( $cdnfsd_engine ) {
			case 'cloudfront':
				add_action( 'w3tc_ajax', array( '\W3TC\Cdnfsd_CloudFront_Popup', 'w3tc_ajax' ) );
				add_action( 'w3tc_settings_box_cdnfsd', array( '\W3TC\Cdnfsd_CloudFront_Page', 'w3tc_settings_box_cdnfsd' ) );
				break;
			case 'limelight':
				add_action( 'w3tc_ajax', array( '\W3TC\Cdnfsd_LimeLight_Popup', 'w3tc_ajax' ) );
				add_action( 'w3tc_settings_box_cdnfsd', array( '\W3TC\Cdnfsd_LimeLight_Page', 'w3tc_settings_box_cdnfsd' ) );
				break;
			case 'stackpath':
				add_action( 'w3tc_ajax', array( '\W3TC\Cdnfsd_StackPath_Popup', 'w3tc_ajax' ) );
				add_action( 'w3tc_settings_box_cdnfsd', array( '\W3TC\Cdnfsd_StackPath_Page', 'w3tc_settings_box_cdnfsd' ) );
				break;
			case 'stackpath2':
				add_action( 'w3tc_ajax', array( '\W3TC\Cdnfsd_StackPath2_Popup', 'w3tc_ajax' ) );
				add_action( 'w3tc_settings_box_cdnfsd', array( '\W3TC\Cdnfsd_StackPath2_Page', 'w3tc_settings_box_cdnfsd' ) );
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
	 * Print the general settings page CDN footer.
	 */
	public function w3tc_settings_general_boxarea_cdn_footer() {
		$config               = Dispatcher::config();
		$cdnfsd_enabled       = $config->get_boolean( 'cdnfsd.enabled' );
		$cdnfsd_engine        = $config->get_string( 'cdnfsd.engine' );
		$is_pro               = Util_Environment::is_w3tc_pro( $config );
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
			'label' => __( 'CloudFlare (extension not activated)', 'w3-total-cache' ),
			'disabled' => true,
		);

		$cdnfsd_engine_values['limelight'] = array(
			'label' => __( 'Limelight', 'w3-total-cache' ),
		);

		$cdnfsd_engine_values['stackpath'] = array(
			'label' => __( 'StackPath SecureCDN (Legacy)', 'w3-total-cache' ),
		);

		$cdnfsd_engine_values['stackpath2'] = array(
			'label' => __( 'StackPath', 'w3-total-cache' ),
		);

		$cdnfsd_engine_values['transparentcdn'] = array(
			'label' => __( 'TransparentCDN', 'w3-total-cache' ),
		);

		if ( 'cloudfront' === $cdnfsd_engine ) {
			$tag = 'https://api.w3-edge.com/v1/redirects/faq/cdn-fsd/cloudfront';
		} elseif ( 'stackpath' === $cdnfsd_engine || 'stackpath2' === $cdnfsd_engine ) {
			$tag = 'https://api.w3-edge.com/v1/redirects/faq/cdn-fsd/stackpath';
		}

		$cdnfsd_engine_extra_description = empty( $tag ) ? '' : ' See <a href="' . $tag . '">setup instructions</a>';

		include W3TC_DIR . '/Cdnfsd_GeneralPage_View.php';
	}
}
