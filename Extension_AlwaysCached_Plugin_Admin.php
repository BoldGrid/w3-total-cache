<?php
/**
 * File: Extension_AlwaysCached_Plugin_Admin.php
 *
 * AlwaysCached plugin admin controller.
 *
 * @since 2.8.0
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * AlwaysCached Plugin Admin.
 *
 * @since 2.8.0
 */
class Extension_AlwaysCached_Plugin_Admin {
	/**
	 * Adds the AlwaysCached extension to extensions list.
	 *
	 * @since 2.8.0
	 *
	 * @param array  $extensions Extensions list.
	 * @param Config $config     Config data.
	 *
	 * @return array
	 */
	public static function w3tc_extensions( $extensions, $config ) {
		$requirements = array();

		if ( ! Util_Environment::is_w3tc_pro( $config ) ) {
			$requirements[] = __( 'Valid W3 Total Cache Pro license', 'w3-total-cache' );
		}

		$extensions['alwayscached'] = array(
			'name'            => 'Always Cached',
			'author'          => 'W3 EDGE',
			'description'     => __( 'Always cached.', 'w3-total-cache' ),
			'author_uri'      => 'https://www.w3-edge.com/',
			'extension_uri'   => 'https://www.w3-edge.com/',
			'extension_id'    => 'alwayscached',
			'pro_feature'     => true,
			'pro_excerpt'     => __( 'Prevents page/post updates from clearing corresponding cache entries and instead add them to a queue that can be manually cleared or scheduled to clear via cron.', 'w3-total-cache' ),
			'pro_description' => array(),
			'settings_exists' => true,
			'version'         => '1.0',
			'enabled'         => empty( $requirements ),
			'requirements'    => implode( ', ', $requirements ),
			'path'            => 'w3-total-cache/Extension_AlwaysCached_Plugin.php',
		);

		return $extensions;
	}

	/**
	 * Run method for AlwaysCached admin.
	 *
	 * @since 2.8.0
	 *
	 * @return void|null
	 */
	public function run() {
		if ( ! Extension_AlwaysCached_Plugin::is_enabled() ) {
			return null;
		}

		add_action(
			'w3tc_extension_page_alwayscached',
			array(
				'\W3TC\Extension_AlwaysCached_Page',
				'w3tc_extension_page_alwayscached',
			)
		);

		add_action(
			'admin_print_scripts',
			array(
				'\W3TC\Extension_AlwaysCached_Page',
				'admin_print_scripts',
			)
		);

		add_filter( 'w3tc_admin_actions', array( $this, 'w3tc_admin_actions' ) );

		add_filter( 'w3tc_admin_menu', array( $this, 'w3tc_admin_menu' ) );

		add_action(
			'w3tc_ajax',
			array(
				'\W3TC\Extension_AlwaysCached_Page',
				'w3tc_ajax',
			)
		);
	}

	/**
	 * Adds admin actions for AlwaysCached.
	 *
	 * @since 2.8.0
	 *
	 * @param array $handlers Handlers array.
	 *
	 * @return array
	 */
	public function w3tc_admin_actions( $handlers ) {
		$handlers['alwayscached'] = 'Extension_AlwaysCached_AdminActions';
		return $handlers;
	}

	/**
	 * Adds admin menu item for AlwaysCached.
	 *
	 * @since 2.8.0
	 *
	 * @param array $menu Menu array.
	 *
	 * @return array
	 */
	public function w3tc_admin_menu( $menu ) {
		if ( Extension_AlwaysCached_Plugin::is_enabled() ) {
			$menu['w3tc_extensions&extension=alwayscached&action=view'] = array(
				'page_title'     => __( 'Page Cache Queue', 'w3-total-cache' ),
				'menu_text'      => __( 'Page Cache Queue', 'w3-total-cache' ),
				'visible_always' => false,
				'order'          => 450,
			);
		}

		return $menu;
	}
}
