<?php
/**
 * File: Extension_ImageService_Widget.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_ImageService_Widget
 */
class Extension_ImageService_Widget {
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
		$o = new Extension_ImageService_Widget();
		add_action( 'w3tc_widget_setup', array( $o, 'wp_dashboard_setup' ), 2000 );
		add_action( 'w3tc_network_dashboard_setup', array( $o, 'wp_dashboard_setup' ), 2000 );
	}

	/**
	 * W3TC dashboard Premium Services widget.
	 */
	public function wp_dashboard_setup() {
		Util_Widget::add(
			'w3tc_imageservice',
			'<div class="w3tc-widget-w3tc-logo"></div><div class="w3tc-widget-text">' . __( 'Image Optimization Summary', 'w3-total-cache' ) . '</div>',
			array( $this, 'widget_form' ),
			null,
			'normal'
		);
	}

	/**
	 * Premium Services widget content.
	 */
	public function widget_form() {
		$o      = new Extension_ImageService_Plugin_Admin();
		$counts = $o->get_counts();
		$usage  = get_transient( 'w3tc_imageservice_usage' );

		// If usage is not stored, then retrieve it from the API.
		if ( empty( $usage ) ) {
			$usage = Extension_ImageService_Plugin::get_api()->get_usage();
		}

		// Ensure that the monthly limit is represented correctly.
		$usage['limit_monthly'] = $usage['limit_monthly'] ? $usage['limit_monthly'] : __( 'Unlimited', 'w3-total-cache' );
		
		include W3TC_DIR . '/Extension_ImageService_Widget_View.php';
	}
}
