<?php
/**
 * File: Extension_Wpml_Plugin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_Wpml_Plugin
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class Extension_Wpml_Plugin {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config;

	/**
	 * Constructor for the Extension_Wpml_Plugin class.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Executes the main logic of the extension.
	 *
	 * Checks if the W3 Total Cache Pro plugin is active and adds the necessary filter.
	 *
	 * @return void
	 */
	public function run() {
		if ( Util_Environment::is_w3tc_pro( $this->_config ) ) {
			add_filter( 'w3tc_url_to_docroot_filename', array( $this, 'w3tc_url_to_docroot_filename' ) );
		}
	}

	/**
	 * Converts the URL to the document root filename.
	 *
	 * @param array $data Associative array containing 'url' and 'home_url' keys.
	 *
	 * @return array The modified data array with updated 'home_url'.
	 */
	public function w3tc_url_to_docroot_filename( $data ) {
		$home_url = $data['home_url'];

		if ( substr( $data['url'], 0, strlen( $home_url ) ) !== $home_url ) {
			$data['home_url'] = get_option( 'home' );
		}

		return $data;
	}
}

$p = new Extension_Wpml_Plugin();
$p->run();

if ( is_admin() ) {
	$p = new Extension_Wpml_Plugin_Admin();
	$p->run();
}
