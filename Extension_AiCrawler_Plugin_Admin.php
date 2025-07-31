<?php
/**
 * File: Extension_AiCrawler_Plugin_Admin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_AiCrawler_Plugin_Admin
 */
class Extension_AiCrawler_Plugin_Admin {
	/**
	 * Adds AI Crawler extension to the extension list.
	 *
	 * @param array  $extensions Extensions array.
	 * @param Config $config     Plugin configuration.
	 *
	 * @return array
	 */
	public static function w3tc_extensions( $extensions, $config ) {// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$extensions['aicrawler'] = array(
			'name'            => 'AI Crawler Extension',
			'author'          => 'W3 EDGE',
			'description'     => __( 'AI Crawler extension', 'w3-total-cache' ),
			'author_uri'      => 'https://www.w3-edge.com/',
			'extension_uri'   => 'https://www.w3-edge.com/',
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
	 */
	public function run() {
		add_filter( 'w3tc_admin_menu', array( $this, 'w3tc_admin_menu' ) );
		add_action( 'w3tc_settings_page-w3tc_aicrawler', array( $this, 'w3tc_extension_page' ) );
	}

	/**
	 * Adds the AI Crawler settings page to the Performance menu.
	 *
	 * @param array $menu Existing menu entries.
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
	 * Displays the AI Crawler extension settings page.
	 *
	 * @return void
	 */
	public function w3tc_extension_page() {
		$view = new Extension_AiCrawler_Page();
		$view->render_content();
	}
}
