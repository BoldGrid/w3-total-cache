<?php
/**
 * File: Cdn_BunnyCdn_Widget.php
 *
 * @since   2.6.0
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdn_BunnyCdn_Widget
 *
 * @since 2.6.0
 */
class Cdn_BunnyCdn_Widget {
	/**
	 * Initializes the W3TC BunnyCDN widget in the admin dashboard.
	 *
	 * This method adds the necessary actions to initialize the BunnyCDN widget on the W3TC dashboard. It creates an instance
	 * of the widget class, registers the required styles, and hooks the widget form display to the proper location on the admin page.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public static function admin_init_w3tc_dashboard() {
		$o = new Cdn_BunnyCdn_Widget();

		add_action( 'admin_print_styles', array( $o, 'admin_print_styles' ) );

		Util_Widget::add2(
			'w3tc_bunnycdn',
			400,
			'<div class="w3tc-widget-bunnycdn-logo"></div>',
			array( $o, 'widget_form' ),
			Util_Ui::admin_url( 'admin.php?page=w3tc_cdn' ),
			'normal'
		);
	}

	/**
	 * Displays the widget form for BunnyCDN configuration.
	 *
	 * This method checks whether the user is authorized to view the BunnyCDN widget. If authorized, it includes a view that
	 * shows the authorized settings. If the user is not authorized, a view indicating that they are unauthorized will be shown.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function widget_form() {
		$c          = Dispatcher::config();
		$authorized = $c->get_string( 'cdn.engine' ) === 'bunnycdn' &&
			( ! empty( $c->get_integer( 'cdn.bunnycdn.pull_zone_id' ) ) || ! empty( $c->get_integer( 'cdnfsd.bunnycdn.pull_zone_id' ) ) );

		if ( $authorized ) {
			include __DIR__ . DIRECTORY_SEPARATOR . 'Cdn_BunnyCdn_Widget_View_Authorized.php';
		} else {
			include __DIR__ . DIRECTORY_SEPARATOR . 'Cdn_BunnyCdn_Widget_View_Unauthorized.php';
		}
	}

	/**
	 * Enqueues the styles for the BunnyCDN widget in the admin area.
	 *
	 * This method enqueues the required CSS files for the BunnyCDN widget in the WordPress admin area. It ensures that the
	 * widget's styles are applied correctly on the dashboard page.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function admin_print_styles() {
		wp_enqueue_style( 'w3tc-widget' );

		wp_enqueue_style(
			'w3tc-bunnycdn-widget',
			plugins_url( 'Cdn_BunnyCdn_Widget_View.css', W3TC_FILE ),
			array(),
			W3TC_VERSION
		);
	}
}
