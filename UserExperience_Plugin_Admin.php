<?php
/**
 * File: UserExperience_Plugin_Admin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UserExperience_Plugin_Admin
 *
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class UserExperience_Plugin_Admin {
	/**
	 * Initializes the admin hooks for the User Experience plugin.
	 *
	 * This method registers WordPress filters and actions necessary for the
	 * integration of the User Experience plugin into the W3 Total Cache admin area.
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
	 * Adds a custom menu entry for the User Experience plugin.
	 *
	 * This method adds a "User Experience" menu item to the W3 Total Cache admin menu.
	 *
	 * @param array $menu The existing admin menu items.
	 *
	 * @return array The modified admin menu items, including the User Experience menu.
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
	 * Registers User Experience-related extensions for W3 Total Cache.
	 *
	 * This method adds several custom extensions to W3 Total Cache that handle specific
	 * User Experience features like deferring scripts, preloading requests, and more.
	 *
	 * @param array  $extensions The existing W3TC extensions.
	 * @param object $config     The W3TC configuration object.
	 *
	 * @return array The modified W3TC extensions array with added User Experience extensions.
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
	 * Renders the settings page for the User Experience plugin.
	 *
	 * This method initializes and displays the settings page content for the
	 * User Experience plugin in the W3 Total Cache admin area.
	 *
	 * @return void
	 */
	public function w3tc_settings_page_w3tc_userexperience() {
		$v = new UserExperience_Page();
		$v->render_content();
	}
}
