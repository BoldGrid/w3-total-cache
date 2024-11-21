<?php
/**
 * File: Generic_WidgetBoldGrid_AdminActions.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Generic_WidgetBoldGrid_AdminActions
 */
class Generic_WidgetBoldGrid_AdminActions {
	/**
	 * Redirect user depending on if boldgrid backup is installed
	 *
	 * @return void
	 */
	public function w3tc_boldgrid_install() {
		$plugin_file = 'boldgrid-backup/boldgrid-backup.php';
		$slug        = 'boldgrid-backup';

		$plugins = get_plugins();

		if ( isset( $plugins[ $plugin_file ] ) ) {
			$install_url = 'plugins.php?action=activate' .
				'&plugin=' . rawurlencode( $plugin_file ) .
				'&plugin_status=all&paged=1&s&_wpnonce=' .
				wp_create_nonce( 'activate-plugin_' . $plugin_file );
		} else {
			$install_url =
				'update.php?action=install-plugin&plugin=' . $slug .
				'&_wpnonce=' . wp_create_nonce( 'install-plugin_' . $slug );
		}

		wp_safe_redirect( $install_url );
		exit();
	}
}
