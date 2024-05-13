<?php
/**
 * File: UserExperience_Plugin_Admin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * UserExperience Plugin Admin.
 */
class UserExperience_Plugin_Admin {
	/**
	 * Runs the user experience feature.
	 *
	 * @return void
	 */
	public function run() {
		add_filter( 'w3tc_admin_menu', array( $this, 'w3tc_admin_menu' ) );
		add_action( 'w3tc_settings_page-w3tc_userexperience', array( $this, 'w3tc_settings_page_w3tc_userexperience' ) );
		add_action( 'admin_init_w3tc_general', array( '\W3TC\UserExperience_GeneralPage', 'admin_init_w3tc_general' ) );
		add_filter( 'w3tc_extensions', array( '\W3TC\UserExperience_Plugin_Admin', 'w3tc_extensions' ), 10, 2 );
	}

	/**
	 * Set user experience admin menu item.
	 *
	 * @param array $menu Menu array.
	 *
	 * @return array
	 */
	public function w3tc_admin_menu( $menu ) {
		$menu['w3tc_userexperience'] = array(
			'page_title'     => __( 'User Experience', 'w3-total-cache' ),
			'menu_text'      => __( 'User Experience', 'w3-total-cache' ),
			'visible_always' => false,
			'order'          => 1200,
		);

		return $menu;
	}

	/**
	 * Configures extensions for user experience.
	 *
	 * @param array  $extensions Extensions array.
	 * @param object $config     Config object.
	 *
	 * @return array
	 */
	public static function w3tc_extensions( $extensions, $config ) {
		$extensions['user-experience-defer-scripts']    = array(
			'public'       => false,
			'extension_id' => 'user-experience-defer-scripts',
			'path'         => 'w3-total-cache/UserExperience_DeferScripts_Extension.php',
		);
		$extensions['user-experience-preload-requests'] = array(
			'public'       => false,
			'extension_id' => 'user-experience-preload-requests',
			'path'         => 'w3-total-cache/UserExperience_Preload_Requests_Extension.php',
		);
		$extensions['user-experience-remove-cssjs']     = array(
			'public'       => false,
			'extension_id' => 'user-experience-remove-cssjs',
			'path'         => 'w3-total-cache/UserExperience_Remove_CssJs_Extension.php',
		);
		$extensions['user-experience-emoji']            = array(
			'public'       => false,
			'extension_id' => 'user-experience-emoji',
			'path'         => 'w3-total-cache/UserExperience_Emoji_Extension.php',
		);
		$extensions['user-experience-oembed']           = array(
			'public'       => false,
			'extension_id' => 'user-experience-oembed',
			'path'         => 'w3-total-cache/UserExperience_OEmbed_Extension.php',
		);

		return $extensions;
	}

	/**
	 * Render user experience advanced settings page.
	 *
	 * @return void
	 */
	public function w3tc_settings_page_w3tc_userexperience() {
		$v = new UserExperience_Page();
		$v->render_content();
	}
}
