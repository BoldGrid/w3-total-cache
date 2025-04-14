<?php
/**
 * File: Extension_FragmentCache_GeneralPage.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_FragmentCache_GeneralPage
 */
class Extension_FragmentCache_GeneralPage {
	/**
	 * Initializes the general page for the fragment cache extension in W3TC.
	 *
	 * @return void
	 */
	public static function admin_init_w3tc_general() {
		$o = new Extension_FragmentCache_GeneralPage();

		add_filter( 'w3tc_settings_general_anchors', array( $o, 'w3tc_settings_general_anchors' ) );
		add_action( 'w3tc_settings_general_boxarea_fragmentcache', array( $o, 'w3tc_settings_general_boxarea_fragmentcache' ) );
	}

	/**
	 * Adds the fragment cache section anchor to the W3TC general settings page.
	 *
	 * @param array $anchors Array of existing anchors for the settings page.
	 *
	 * @return array Modified array of anchors including the fragment cache section.
	 */
	public function w3tc_settings_general_anchors( $anchors ) {
		$anchors[] = array(
			'id'   => 'fragmentcache',
			'text' => 'Fragment Cache',
		);
		return $anchors;
	}

	/**
	 * Displays the fragment cache settings box in the general settings page.
	 *
	 * @return void
	 */
	public function w3tc_settings_general_boxarea_fragmentcache() {
		include W3TC_DIR . '/Extension_FragmentCache_GeneralPage_View.php';
	}
}
