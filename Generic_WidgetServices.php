<?php
/**
 * File: Generic_WidgetServices.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Generic_Plugin_WidgetServices
 */
class Generic_WidgetServices {
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
		$o = new Generic_WidgetServices();
		add_action( 'w3tc_widget_setup', array( $o, 'wp_dashboard_setup' ), 5000 );
		add_action( 'w3tc_network_dashboard_setup', array( $o, 'wp_dashboard_setup' ), 5000 );
	}

	/**
	 * W3TC dashboard Premium Services widget.
	 */
	public function wp_dashboard_setup() {
		Util_Widget::add(
			'w3tc_services',
			'<div class="w3tc-widget-w3tc-logo"></div><div class="w3tc-widget-text">' . __( 'Premium Services', 'w3-total-cache' ) . '</div>',
			array( $this, 'widget_form' ),
			null,
			'normal'
		);
	}

	/**
	 * Premium Services widget content.
	 */
	public function widget_form() {
		include W3TC_DIR . '/Generic_WidgetServices_View.php';
	}

	/**
	 * Premium Services widget services list.
	 */
	public static function get_services() {
		return array(
			__( 'Billing Support', 'w3-total-cache' ),
			__( 'Sales Questions', 'w3-total-cache' ),
			__( 'Submit a Bug Report', 'w3-total-cache' ),
			__( 'Suggest a New Feature', 'w3-total-cache' ),
			__( 'Performance Audit & Consultation', 'w3-total-cache' ),
			__( 'Plugin Configuration', 'w3-total-cache' ),
			__( 'CDN Configuration: Full-Site Delivery', 'w3-total-cache' ),
			__( 'Hosting Environment Troubleshooting', 'w3-total-cache' ),
			__( 'Eliminate render-blocking Javascripts', 'w3-total-cache' ),
			__( 'Investigate Compatibility Issue', 'w3-total-cache' ),
		);
	}
}
