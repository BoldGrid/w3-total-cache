<?php
/**
 * File: Extension_AiCrawler_Plugin_Admin.php
 *
 * @package W3TC
 * @since   X.X.X
 */

namespace W3TC;

/**
 * Class: Extension_AiCrawler_Plugin_Admin
 *
 * @since X.X.X
 */
class Extension_AiCrawler_Plugin_Admin {
	/**
	 * Adds AI Crawler to the extension list.
	 *
	 * @since  X.X.X
	 *
	 * @param array $extensions Extensions array.
	 *
	 * @return array
	 */
	public static function w3tc_extensions( $extensions ) {
		$extensions['aicrawler'] = array(
			'name'            => 'AI Crawler',
			'author'          => 'W3 Edge',
			'description'     => __( 'AI Crawler', 'w3-total-cache' ),
			'author_uri'      => 'https://www.boldgrid.com/',
			'extension_uri'   => 'https://www.boldgrid.com/w3-total-cache/',
			'extension_id'    => 'aicrawler',
			'settings_exists' => true,
			'version'         => '1.0',
			'enabled'         => true,
			'requirements'    => '',
			'path'            => 'w3-total-cache/Extension_AiCrawler_Plugin.php',
		);

		return $extensions;
	}

	/**
	 * Registers hooks for the admin environment.
	 *
	 * @since  X.X.X
	 *
	 * @return void
	 */
	public function run() {
		add_filter( 'w3tc_admin_menu', array( $this, 'w3tc_admin_menu' ) );
		add_filter( 'w3tc_extension_plugin_links_aicrawler', array( $this, 'w3tc_extension_plugin_links' ) );
		add_action( 'w3tc_settings_page-w3tc_aicrawler', array( $this, 'w3tc_extension_page' ) );
	}

	/**
	 * Adds the AI Crawler settings page to the Performance menu.
	 *
	 * @since  X.X.X
	 *
	 * @param array $menu Existing menu entries.
	 *
	 * @todo Possibly add a capability check here to restrict access.
	 *       to administrators or specific user roles.
	 *
	 * @return array
	 */
	public function w3tc_admin_menu( $menu ) {
		$menu['w3tc_aicrawler'] = array(
			'page_title'     => __( 'AI Crawler', 'w3-total-cache' ),
			'menu_text'      => __( 'AI Crawler', 'w3-total-cache' ),
			'visible_always' => false,
			'order'          => 2000,
		);

		return $menu;
	}

	/**
	 * Displays the settings page.
	 *
	 * @since  X.X.X
	 *
	 * @return void
	 */
	public function w3tc_extension_page() {
		( new Extension_AiCrawler_Page() )->render_content();
	}

	/**
	 * Adds custom plugin links.
	 *
	 * @param array $links Existing array of plugin links.
	 *
	 * @return array Modified array of plugin links with New Relic settings link added.
	 */
	public function w3tc_extension_plugin_links( $links ) {
		$links   = array();
		$links[] = '<a class="edit" href="' . esc_attr( Util_Ui::admin_url( 'admin.php?page=w3tc_aicrawler' ) ) .
			'">' . __( 'Settings' ) . '</a>';

		return $links;
	}
}
