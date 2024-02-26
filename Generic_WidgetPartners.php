<?php
/**
 * File: Generic_WidgetPartners.php
 *
 * @since   2.7.0
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Generic_WidgetServices
 *
 * @since 2.7.0
 */
class Generic_WidgetPartners {
	/**
	 * Dashboard setup action
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public static function admin_init_w3tc_dashboard() {
		$o = new Generic_WidgetPartners();
		add_action( 'w3tc_widget_setup', array( $o, 'wp_dashboard_setup' ), 290 );
		add_action( 'w3tc_network_dashboard_setup', array( $o, 'wp_dashboard_setup' ), 290 );
	}

	/**
	 * W3TC dashboard Premium Services widget.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function wp_dashboard_setup() {
		Util_Widget::add(
			'w3tc_partners',
			'<div class="w3tc-widget-w3tc-logo"></div><div class="w3tc-widget-text">' . __( 'Setup Guides from Partner Hosts', 'w3-total-cache' ) . '</div>',
			array( $this, 'widget_form' ),
			null,
			'normal'
		);
	}

	/**
	 * Premium Services widget content.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function widget_form() {
		include W3TC_DIR . '/Generic_WidgetPartners_View.php';
	}
}
