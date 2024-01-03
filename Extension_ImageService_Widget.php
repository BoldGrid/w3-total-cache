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
		$config = Dispatcher::config();
		$o      = new Extension_ImageService_Widget();
		add_action( 'w3tc_widget_setup', array( $o, 'wp_dashboard_setup' ), 2000 );
		add_action( 'w3tc_network_dashboard_setup', array( $o, 'wp_dashboard_setup' ), 2000 );
		wp_enqueue_script( 'w3tc-dashboard', plugins_url( 'pub/js/google-charts.js', W3TC_FILE ), array(), W3TC_VERSION, true );

		$ipa = new Extension_ImageService_Plugin_Admin();
		// Get WebP count data.
		$counts = $ipa->get_counts();
		// Strip total data that won't be used in pie chart.
		$counts = array_diff_key( $counts, array_flip( array( 'total', 'totalbytes' ) ) );

		// Get WebP API Usage data.
		$usage = get_transient( 'w3tc_imageservice_usage' );
		// Get data via API if no transient exists.
		$usage = empty( $usage ) ? Extension_ImageService_Plugin::get_api()->get_usage() : $usage;
		// Strip timestamp.
		unset( $usage['updated_at'] );

		wp_register_script(
			'w3tc-webp-widget',
			esc_url( plugins_url( 'Extension_ImageService_Widget.js', W3TC_FILE ) ),
			array(),
			W3TC_VERSION,
			'true'
		);
		wp_localize_script(
			'w3tc-webp-widget',
			'w3tc_webp_data',
			array(
				'counts' => array(
					'data' => $counts,
					'type' => 'pie',
				),
				'api'    => array(
					'data' => $usage,
					'type' => 'gauge',
				),
			)
		);
		wp_enqueue_script( 'w3tc-webp-widget' );
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
		// Get WebP API Usage data.
		$usage = get_transient( 'w3tc_imageservice_usage' );
		// Get data via API if no transient exists.
		$usage = empty( $usage ) ? Extension_ImageService_Plugin::get_api()->get_usage() : $usage;
		include W3TC_DIR . '/Extension_ImageService_Widget_View.php';
	}
}
