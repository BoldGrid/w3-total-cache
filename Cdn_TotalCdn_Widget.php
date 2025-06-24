<?php
/**
 * File: Cdn_TotalCdn_Widget.php
 *
 * @since   X.X.X
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdn_TotalCdn_Widget
 *
 * @since X.X.X
 */
class Cdn_TotalCdn_Widget {
	/**
	 * Initializes the W3TC W3TC provided CDN widget in the admin dashboard.
	 *
	 * This method adds the necessary actions to initialize the W3TC provided CDN widget on the W3TC dashboard. It creates an instance
	 * of the widget class, registers the required styles, and hooks the widget form display to the proper location on the admin page.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function admin_init_w3tc_dashboard() {
		$widget_class = '\W3TC\Cdn_TotalCdn_Widget';
		$o            = new $widget_class();

		add_action( 'admin_print_styles', array( $o, 'admin_print_styles' ) );

		Util_Widget::add2(
			'w3tc_' . 'totalcdn',
			400,
			'<div class="w3tc-widget-totalcdn-logo"></div>',
			array( $o, 'widget_form' ),
			Util_Ui::admin_url( 'admin.php?page=w3tc_cdn' ),
			'normal'
		);
	}

	/**
	 * Displays the widget form for W3TC provided CDN configuration.
	 *
	 * This method checks whether the user is authorized to view the W3TC provided CDN widget. If authorized, it includes a view that
	 * shows the authorized settings. If the user is not authorized, a view indicating that they are unauthorized will be shown.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function widget_form() {
		$config = Dispatcher::config();
		$state  = Dispatcher::config_state();

		$engine             = $config->get_string( 'cdn.engine' );
		$cdn_pullzone_id    = $config->get_integer( 'cdn.totalcdn.pull_zone_id' );
		$cdnfsd_pullzone_id = $config->get_integer( 'cdnfsd.totalcdn.pull_zone_id' );
		$authorized         = 'totalcdn' === $engine &&
			( ! empty( $cdn_pullzone_id ) || ! empty( $cdnfsd_pullzone_id ) );

		if ( $authorized ) {
			include __DIR__ . DIRECTORY_SEPARATOR . 'Cdn_TotalCdn_Widget_View_Authorized.php';
		} else {
			include __DIR__ . DIRECTORY_SEPARATOR . 'Cdn_TotalCdn_Widget_View_Unauthorized.php';
		}
	}

	/**
	 * Enqueues the styles for the W3TC provided CDN widget in the admin area.
	 *
	 * This method enqueues the required CSS files for the W3TC provided CDN widget in the WordPress admin area. It ensures that the
	 * widget's styles are applied correctly on the dashboard page.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function admin_print_styles() {
		wp_enqueue_style( 'w3tc-widget' );

		wp_enqueue_style(
			'w3tc-totalcdn-widget',
			plugins_url( 'Cdn_TotalCdn_Widget_View.css', W3TC_FILE ),
			array(),
			W3TC_VERSION
		);
	}
}
