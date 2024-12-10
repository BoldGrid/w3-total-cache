<?php
/**
 * File: BrowserCache_Plugin_Admin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class BrowserCache_Environment
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
	 * @param Config $config     Config.
	 * @param Config $old_config Config.
	 *
	 * @return void
	 */
	public function w3tc_config_ui_save_w3tc_browsercache( $config, $old_config ) {
		$prefix  = 'browsercache__security__fp__values__keyvalues__';
		$prefixl = strlen( $prefix );

		$fp_values = array();

		foreach ( $_REQUEST as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$value = Util_Request::get_string( $key );
			if ( substr( $key, 0, $prefixl ) === $prefix ) {
				$k = substr( $key, $prefixl );
				if ( ! empty( $value ) ) {
					$fp_values[ $k ] = $value;
				}
			}
		}

		$config->set( 'browsercache.security.fp.values', $fp_values );
	}
}
