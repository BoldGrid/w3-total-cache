<?php
/**
 * File: Generic_WidgetAccount.php
 *
 * @package W3TC
 *
 * @since X.X.X
 */

namespace W3TC;

/**
 * Class Generic_WidgetAccount
 *
 * @since X.X.X
 */
class Generic_WidgetAccount {
	/**
	 * Dashboard setup action
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function admin_init_w3tc_dashboard() {
		$o = new Generic_WidgetAccount();
		add_action( 'w3tc_widget_setup', array( $o, 'wp_dashboard_setup' ), 100 );
		add_action( 'w3tc_network_dashboard_setup', array( $o, 'wp_dashboard_setup' ), 100 );
	}

	/**
	 * W3TC dashboard Premium Services widget.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function wp_dashboard_setup() {
		Util_Widget::add(
			'w3tc_account',
			'<div class="w3tc-widget-w3tc-logo"></div><div class="w3tc-widget-text">' . __( 'Account', 'w3-total-cache' ) . '</div>',
			array( $this, 'widget_form' ),
			null,
			'normal'
		);
	}

	/**
	 * Premium Services widget content.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function widget_form() {
		include W3TC_DIR . '/Generic_WidgetAccount_View.php';
	}
}
