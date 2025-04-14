<?php
/**
 * File: Extension_Amp_Plugin_Admin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_Amp_Plugin_Admin
 *
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class Extension_Amp_Plugin_Admin {
	/**
	 * Modifies the extensions array to include AMP extension details.
	 *
	 * @param array  $extensions Array of current extensions.
	 * @param object $config     Configuration object.
	 *
	 * @return array Modified extensions array with AMP extension.
	 */
	public static function w3tc_extensions( $extensions, $config ) {
		$enabled          = true;
		$disabled_message = '';

		$requirements = array();

		$extensions['amp'] = array(
			'name'             => 'AMP',
			'author'           => 'W3 EDGE',
			'description'      => __( 'Adds compatibility for accelerated mobile pages (AMP) to minify.', 'w3-total-cache' ),
			'author_uri'       => 'https://www.w3-edge.com/',
			'extension_uri'    => 'https://www.w3-edge.com/',
			'extension_id'     => 'amp',
			'settings_exists'  => true,
			'version'          => '0.1',
			'enabled'          => $enabled,
			'disabled_message' => $disabled_message,
			'requirements'     => implode( ', ', $requirements ),
			'path'             => 'w3-total-cache/Extension_Amp_Plugin.php',
		);

		return $extensions;
	}

	/**
	 * Loads the AMP extension's admin functionalities.
	 *
	 * @return void
	 */
	public static function w3tc_extension_load_admin() {
		$o = new Extension_Amp_Plugin_Admin();

		add_action( 'w3tc_extension_page_amp', array( $o, 'w3tc_extension_page_amp' ) );
		add_action( 'w3tc_config_save', array( $o, 'w3tc_config_save' ), 10, 1 );
	}

	/**
	 * Displays the AMP extension settings page.
	 *
	 * @return void
	 */
	public function w3tc_extension_page_amp() {
		include W3TC_DIR . '/Extension_Amp_Page_View.php';
	}

	/**
	 * Saves the AMP configuration settings.
	 *
	 * @param object $config Configuration object containing AMP settings.
	 *
	 * @return void
	 */
	public function w3tc_config_save( $config ) {
		// frontend activity.
		$url_type         = $config->get_string( array( 'amp', 'url_type' ) );
		$is_active_dropin = ( 'querystring' === $url_type );

		$config->set_extension_active_dropin( 'amp', $is_active_dropin );
	}
}
