<?php
/**
 * File: UserExperience_GeneralPage.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UserExperience_GeneralPage
 */
class UserExperience_GeneralPage {
	/**
	 * Initializes the admin hooks and filters for the general settings page.
	 *
	 * This method sets up the necessary filters and actions to integrate
	 * the User Experience functionality into the W3TC general settings page.
	 *
	 * @return void
	 */
	public static function admin_init_w3tc_general() {
		$o = new UserExperience_GeneralPage();

		add_filter( 'w3tc_settings_general_anchors', array( $o, 'w3tc_settings_general_anchors' ) );
		add_action( 'w3tc_settings_general_boxarea_userexperience', array( $o, 'w3tc_settings_general_boxarea_userexperience' ) );
	}

	/**
	 * Modifies the anchors used in the W3TC general settings navigation.
	 *
	 * Adds the "User Experience" section to the list of anchors displayed
	 * in the general settings page, enabling users to quickly navigate
	 * to the relevant section.
	 *
	 * @param array $anchors Existing anchors in the general settings navigation.
	 *
	 * @return array Updated list of anchors including the User Experience section.
	 */
	public function w3tc_settings_general_anchors( $anchors ) {
		$anchors[] = array(
			'id'   => 'userexperience',
			'text' => 'User Experience',
		);

		return $anchors;
	}

	/**
	 * Displays the User Experience settings box area on the W3TC general settings page.
	 *
	 * Includes the necessary view file to render the User Experience settings
	 * section in the W3TC general settings interface.
	 *
	 * @return void
	 */
	public function w3tc_settings_general_boxarea_userexperience() {
		$config = Dispatcher::config();

		include W3TC_DIR . '/UserExperience_GeneralPage_View.php';
	}
}
