<?php
namespace W3TC;

class Generic_WidgetBoldGrid {
	static public function admin_init_w3tc_dashboard() {
		$o = new Generic_WidgetBoldGrid();

		add_action( 'w3tc_widget_setup', array( $o, 'wp_dashboard_setup' ), 3000 );
		add_action( 'w3tc_network_dashboard_setup',
			array( $o, 'wp_dashboard_setup' ), 5000 );

		add_thickbox();
		wp_enqueue_script( 'plugin-install' );

		wp_enqueue_script( 'w3tc-boldgrid-widget',
			plugins_url( 'Generic_WidgetBoldGrid_View.js', W3TC_FILE ),
			array( 'thickbox' ), W3TC_VERSION );
	}



	function wp_dashboard_setup() {
		$show = apply_filters( 'w3tc_generic_boldgrid_show', $this->should_show_widget() );
		if ( !$show ) {
			return;
		}

		Util_Widget::add( 'w3tc_boldgrid',
			'<div class="w3tc-widget-boldgrid-logo"></div>',
			array( $this, 'widget_form' ),
			self_admin_url(
				'plugin-install.php?tab=plugin-information&amp;plugin=boldgrid-backup' .
				'&amp;TB_iframe=true&amp;width=772&amp;height=550'
			), 'normal', __( 'View Details' ), 'thickbox open-plugin-details-modal' );
	}



	/**
	 * Determine whether or not we should show the backup widget.
	 *
	 * We will only recommend the backup plugin if we detect that the user is not already
	 * running a popular WordPress backup plugin.
	 *
	 * @since 0.11.0
	 *
	 * @return bool
	 */
	private function should_show_widget() {
		$plugins = get_option( 'active_plugins' );

		$backup_plugins = array(
			'backup/backup.php',
			'backwpup/backwpup.php',
			'boldgrid-backup/boldgrid-backup.php',
			'duplicator/duplicator.php',
			'updraftplus/updraftplus.php',
			'wpvivid-backuprestore/wpvivid-backuprestore.php'
		);

		foreach ( $plugins as $plugin ) {
			if ( in_array( $plugin, $backup_plugins ) ) {
				return false;
			}
		}

		return true;
	}



	public function widget_form() {
		$install_url = wp_nonce_url(
			'admin.php?page=w3tc_dashboard&w3tc_boldgrid_install', 'w3tc' );

		include  W3TC_DIR . '/Generic_WidgetBoldGrid_View.php';
	}
}
