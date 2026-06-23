<?php
/**
 * File: BrowserCache_Plugin_Admin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class BrowserCache_Environment
 *
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class BrowserCache_Plugin_Admin {
	/**
	 * Run
	 *
	 * @return void
	 */
	public function run() {
		$config_labels = new BrowserCache_ConfigLabels();

		add_filter( 'w3tc_config_labels', array( $config_labels, 'config_labels' ) );

		add_action( 'w3tc_ajax', array( '\W3TC\BrowserCache_Page', 'w3tc_ajax' ) );

		add_action( 'w3tc_config_ui_save-w3tc_browsercache', array( $this, 'w3tc_config_ui_save_w3tc_browsercache' ), 10, 2 );
	}

	/**
	 * Config UI save
	 *
	 * @param Config $w3tc_config     Config.
	 * @param Config $old_config Config.
	 *
	 * @return void
	 */
	public function w3tc_config_ui_save_w3tc_browsercache( $w3tc_config, $old_config ) {
		$prefix  = 'browsercache__security__fp__values__keyvalues__';
		$prefixl = strlen( $prefix );

		$w3tc_fp_values = array();

		foreach ( $_REQUEST as $w3tc_key => $w3tc_value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$w3tc_value = Util_Request::get_string( $w3tc_key );
			if ( substr( $w3tc_key, 0, $prefixl ) === $prefix ) {
				$k = substr( $w3tc_key, $prefixl );
				if ( ! empty( $w3tc_value ) ) {
					$w3tc_fp_values[ $k ] = $w3tc_value;
				}
			}
		}

		$w3tc_config->set( 'browsercache.security.fp.values', $w3tc_fp_values );
	}
}
