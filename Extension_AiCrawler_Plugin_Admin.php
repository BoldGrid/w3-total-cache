<?php
/**
 * File: Extension_AiCrawler_Plugin_Admin.php
 *
 * @package W3TC
 * @since   x.x.x
 */

namespace W3TC;

/**
 * Class Extension_AiCrawler_Plugin_Admin
 *
 * @since x.x.x
 */
class Extension_AiCrawler_Plugin_Admin {
	/**
	 * Adds AI Crawler extension to the extension list.
	 *
	 * @param array  $extensions Extensions array.
	 * @param Config $config     Plugin configuration.
	 *
	 * @return array
	 * @since  x.x.x
	 */
	public static function w3tc_extensions( $extensions, $config ) {// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$extensions['aicrawler'] = array(
			'name'            => 'AI Crawler Extension',
			'author'          => 'BoldGrid',
			'description'     => __( 'AI Crawler extension', 'w3-total-cache' ),
			'author_uri'      => 'https://www.boldgrid.com',
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
	 * @return void
	 * @since  x.x.x
	 */
	public function run() {
		add_filter( 'w3tc_admin_menu', array( $this, 'w3tc_admin_menu' ) );
		add_filter( 'w3tc_extension_plugin_links_aicrawler', array( $this, 'w3tc_extension_plugin_links' ) );
		add_action( 'w3tc_settings_page-w3tc_aicrawler', array( $this, 'w3tc_extension_page' ) );
	}

	/**
	 * Adds the AI Crawler settings page to the Performance menu.
	 *
	 * @param array $menu Existing menu entries.
	 *
	 * @todo Possibly add a capability check here to restrict access.
	 *       to administrators or specific user roles.
	 *
	 * @return array
	 * @since  x.x.x
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
	 * Displays the AI Crawler extension settings page.
	 *
	 * @return void
	 * @since  x.x.x
	 */
	public function w3tc_extension_page() {
		$view = new Extension_AiCrawler_Page();
		$view->render_content();
	}

	/**
	 * Adds custom plugin links for the New Relic extension.
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
