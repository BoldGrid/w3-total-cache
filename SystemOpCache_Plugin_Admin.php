<?php
/**
 * File: SystemOpCache_Plugin_Admin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class SystemOpCache_Plugin_Admin
 */
class SystemOpCache_Plugin_Admin {
	/**
	 * Initializes the plugin by registering actions and filters with WordPress.
	 *
	 * Adds functionality for OPcache and APC OPcache support in the admin panel,
	 * such as menu items, settings, and actions.
	 *
	 * @return void
	 */
	public function run() {
		if ( Util_Installed::opcache() || Util_Installed::apc_opcache() ) {
			add_filter( 'w3tc_admin_bar_menu', array( $this, 'w3tc_admin_bar_menu' ) );
		}

		add_filter( 'w3tc_admin_actions', array( $this, 'w3tc_admin_actions' ) );
		add_action( 'w3tc_settings_general_boxarea_system_opcache', array( $this, 'w3tc_settings_general_boxarea_system_opcache' ) );
	}

	/**
	 * Adds an entry for "System OPcache" to the W3 Total Cache settings anchors.
	 *
	 * This method appends the "System OPcache" anchor to the list of settings
	 * anchors, enabling users to navigate to the relevant settings section.
	 *
	 * @param array $anchors Existing settings anchors.
	 *
	 * @return array Updated settings anchors including "System OPcache".
	 */
	public function w3tc_settings_general_anchors( $anchors ) {
		$anchors[] = array(
			'id'   => 'system_opcache',
			'text' => 'System OPcache',
		);

		return $anchors;
	}

	/**
	 * Registers the "opcache" handler with W3 Total Cache admin actions.
	 *
	 * This static method maps the "opcache" identifier to the `SystemOpCache_AdminActions`
	 * class, enabling the corresponding actions to be handled correctly.
	 *
	 * @param array $handlers Existing action handlers.
	 *
	 * @return array Updated action handlers including the "opcache" handler.
	 */
	public static function w3tc_admin_actions( $handlers ) {
		$handlers['opcache'] = 'SystemOpCache_AdminActions';

		return $handlers;
	}

	/**
	 * Displays the settings box for OPcache in the general settings section.
	 *
	 * This method determines the availability of OPcache or APC OPcache and
	 * their configurations, then includes the view file to render the settings.
	 *
	 * @return void
	 */
	public function w3tc_settings_general_boxarea_system_opcache() {
		$opcode_engine       = 'Not Available';
		$validate_timestamps = false;

		if ( Util_Installed::opcache() ) {
			$opcode_engine       = 'OPcache';
			$validate_timestamps = Util_Installed::is_opcache_validate_timestamps();
		} elseif ( Util_Installed::apc_opcache() ) {
			$opcode_engine = 'APC';
			$engine_status = Util_Installed::is_apc_validate_timestamps();
		}

		include W3TC_DIR . '/SystemOpCache_GeneralPage_View.php';
	}


	/**
	 * Adds an OPcache flush menu item to the WordPress admin bar.
	 *
	 * This method creates a menu item in the admin bar for flushing the
	 * OPcache, providing a quick access point for this operation.
	 *
	 * @param array $menu_items Existing admin bar menu items.
	 *
	 * @return array Updated admin bar menu items including the OPcache flush menu item.
	 */
	public function w3tc_admin_bar_menu( $menu_items ) {
		$menu_items['20910.system_opcache'] = array(
			'id'     => 'w3tc_flush_opcache',
			'parent' => 'w3tc_flush',
			'title'  => __( 'Opcode Cache', 'w3-total-cache' ),
			'href'   => Util_Ui::url(
				array(
					'page'               => 'w3tc_dashboard',
					'w3tc_opcache_flush' => '',
				)
			),
		);

		return $menu_items;
	}
}
