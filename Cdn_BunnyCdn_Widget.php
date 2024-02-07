<?php
/**
 * File: Cdn_BunnyCdn_Widget.php
 *
 * @since   X.X.X
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdn_BunnyCdn_Widget
 *
 * @since X.X.X
 */
class Cdn_BunnyCdn_Widget {
	/**
	 * Initialize the WP Admin Dashboard.
	 *
	 * @since X.X.X
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
	 * Print widget form.
	 *
	 * @since X.X.X
	 *
	 * return void
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
	 * Enqueue styles.
	 *
	 * @since X.X.X
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
