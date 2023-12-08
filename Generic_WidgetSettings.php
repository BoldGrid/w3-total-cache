<?php
/**
 * File: Generic_WidgetSettings.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Generic_WidgetSettings
 */
class Generic_WidgetSettings {
	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 * Dashboard setup action
	 *
	 * @return void
	 */
	public static function admin_init_w3tc_dashboard() {
		$o = new Generic_WidgetSettings();
		add_action( 'w3tc_widget_setup', array( $o, 'wp_dashboard_setup' ), 600 );
		add_action( 'w3tc_network_dashboard_setup', array( $o, 'wp_dashboard_setup' ), 600 );
	}

	/**
	 * W3TC dashboard Premium Services widget.
	 */
	public function wp_dashboard_setup() {
		Util_Widget::add(
			'w3tc_settings',
			'<div class="w3tc-widget-w3tc-logo"></div><div class="w3tc-widget-text">' . __( 'General Settings', 'w3-total-cache' ) . '</div>',
			array( $this, 'widget_form' ),
			null,
			'normal'
		);
	}

	/**
	 * Premium Services widget content.
	 */
	public function widget_form() {
		include W3TC_DIR . '/Generic_WidgetSettings_View.php';
	}
}
