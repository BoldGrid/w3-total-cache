<?php
/**
 * File: Extension_NewRelic_GeneralPage.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_NewRelic_GeneralPage
 */
class Extension_NewRelic_GeneralPage {
	/**
	 * Initializes the New Relic extension settings in the admin interface.
	 *
	 * @return void
	 */
	public static function admin_init_w3tc_general() {
		$o = new Extension_NewRelic_GeneralPage();

		add_filter( 'w3tc_settings_general_anchors', array( $o, 'w3tc_settings_general_anchors' ) );
		add_action( 'w3tc_settings_general_boxarea_monitoring', array( $o, 'w3tc_settings_general_boxarea_monitoring' ) );

		wp_enqueue_script( 'w3tc_extension_newrelic_popup', plugins_url( 'Extension_NewRelic_Popup_View.js', W3TC_FILE ), array( 'jquery' ), '1.0', false );
	}

	/**
	 * Adds the 'Monitoring' anchor to the settings page.
	 *
	 * @param array $anchors Array of existing anchor items.
	 *
	 * @return array Modified array of anchor items.
	 */
	public function w3tc_settings_general_anchors( $anchors ) {
		$anchors[] = array(
			'id'   => 'monitoring',
			'text' => 'Monitoring',
		);
		return $anchors;
	}

	/**
	 * Outputs the monitoring settings area for the New Relic extension.
	 *
	 * @return void
	 */
	public function w3tc_settings_general_boxarea_monitoring() {
		$config = Dispatcher::config();

		$nerser              = Dispatcher::component( 'Extension_NewRelic_Service' );
		$new_relic_installed = $nerser->module_is_enabled();
		$effective_appname   = $nerser->get_effective_appname();

		include W3TC_DIR . '/Extension_NewRelic_GeneralPage_View.php';
	}
}
