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
	 * Initializes the W3TC Total CDN widget in the admin dashboard.
	 *
	 * This method adds the necessary actions to initialize the Total CDN widget on the W3TC dashboard. It creates an instance
	 * of the widget class, registers the required styles, and hooks the widget form display to the proper location on the admin page.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function admin_init_w3tc_dashboard() {
		$config = Dispatcher::config();
		$o      = new Cdn_TotalCdn_Widget();

		$cdn_enabled    = $config->get_boolean( 'cdn.enabled' );
		$cdnfsd_enabled = $config->get_boolean( 'cdnfsd.enabled' );

		if ( 'cloudflare' === $config->get_string( 'cdnfsd.engine' ) && ! $config->is_extension_active( 'cloudflare' ) ) {
			$cdnfsd_enabled = false;
		}

		$configuration_link = $cdn_enabled || $cdnfsd_enabled
			? Util_Ui::admin_url( 'admin.php?page=w3tc_cdn' )
			: Util_Ui::admin_url( 'admin.php?page=w3tc_general#cdn' );

		add_action( 'admin_print_styles', array( $o, 'admin_print_styles' ) );

		Util_Widget::add2(
			'w3tc_totalcdn',
			400,
			'<div class="w3tc-widget-totalcdn-logo"></div>',
			array( $o, 'widget_form' ),
			$configuration_link,
			'normal'
		);
	}

	/**
	 * Displays the widget form for Total CDN configuration.
	 *
	 * This method checks whether the user is authorized to view the Total CDN widget. If authorized, it includes a view that
	 * shows the authorized settings. If the user is not authorized, a view indicating that they are unauthorized will be shown.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function widget_form() {
		$config = Dispatcher::config();
		$state  = Dispatcher::config_state();

		$cdn_engine  = $config->get_string( 'cdn.engine' );
		$cdn_zone_id = $config->get_integer( 'cdn.totalcdn.pull_zone_id' );
		$authorized  = 'totalcdn' === $cdn_engine && ! empty( $cdn_zone_id );

		if ( $authorized ) {
			include __DIR__ . DIRECTORY_SEPARATOR . 'Cdn_TotalCdn_Widget_View_Authorized.php';
		} else {
			include __DIR__ . DIRECTORY_SEPARATOR . 'Cdn_TotalCdn_Widget_View_Unauthorized.php';
		}
	}

	/**
	 * Enqueues the styles for the Total CDN widget in the admin area.
	 *
	 * This method enqueues the required CSS files for the Total CDN widget in the WordPress admin area. It ensures that the
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
