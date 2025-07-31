<?php
/**
 * File: Extension_Sample_Plugin_Admin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_Sample_Plugin_Admin
 */
class Extension_Sample_Plugin_Admin {
	/**
	 * Adds Sample extension to the extension list.
	 *
	 * @param array  $extensions Extensions array.
	 * @param Config $config     Plugin configuration.
	 *
	 * @return array
	 */
	public static function w3tc_extensions( $extensions, $config ) {// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$extensions['sample'] = array(
			'author'          => 'W3 EDGE',
			'description'     => __( 'Sample extension', 'w3-total-cache' ),
			'author_uri'      => 'https://www.w3-edge.com/',
			'extension_uri'   => 'https://www.w3-edge.com/',
			'extension_id'    => 'sample',
			'settings_exists' => true,
			'version'         => '1.0',
			'enabled'         => true,
			'requirements'    => '',
			'path'            => 'w3-total-cache/Extension_Sample_Plugin.php',
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
		add_action( 'w3tc_extension_page_sample', array( $this, 'w3tc_extension_page' ) );
	}

	/**
	 * Adds the Sample settings page to the Performance menu.
	 *
	 * @param array $menu Existing menu entries.
	 *
	 * @return array
	 */
	public function w3tc_admin_menu( $menu ) {
		$menu['w3tc_sample'] = array(
			'page_title'     => __( 'Sample Extension', 'w3-total-cache' ),
			'menu_text'      => __( 'Sample Extension', 'w3-total-cache' ),
			'visible_always' => false,
			'order'          => 2000,
		);

		return $menu;
	}

	/**
	 * Displays the Sample extension settings page.
	 *
	 * @return void
	 */
	public function w3tc_extension_page() {
		$view = new Extension_Sample_Page();
		$view->render_content();
	}
}
