<?php
/**
 * File: Extension_ImageService_Widget.php
 *
 * @package W3TC
 *
 * @since 2.7.0
 */

namespace W3TC;

/**
 * Class Extension_ImageService_Widget
 *
 * @since 2.7.0
 */
class Extension_ImageService_Widget {
	/**
	 * Dashboard setup action
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public static function admin_init_w3tc_dashboard() {
		$w3tc_config = Dispatcher::config();
		$w3tc_is_pro = Util_Environment::is_w3tc_pro( $w3tc_config );
		$w3tc_o      = new Extension_ImageService_Widget();

		add_action( 'w3tc_widget_setup', array( $w3tc_o, 'wp_dashboard_setup' ), 300 );
		add_action( 'w3tc_network_dashboard_setup', array( $w3tc_o, 'wp_dashboard_setup' ), 300 );

		if ( ! $w3tc_config->is_extension_active( 'imageservice' ) ) {
			// If extension is inactive don't load data or chart.js. This will show instead show an "enable" button with sample background.
			return;
		}

		wp_enqueue_script( 'w3tc-dashboard', plugins_url( 'pub/js/google-charts.js', W3TC_FILE ), array(), W3TC_VERSION, true );

		$ipa = new Extension_ImageService_Plugin_Admin();
		// Get WebP count data.
		$counts = $ipa->get_counts();
		// Strip total data that won't be used in pie chart.
		$counts = array_diff_key( $counts, array_flip( array( 'total', 'totalbytes' ) ) );

		// Get WebP API Usage data.
		$w3tc_usage = get_transient( 'w3tc_imageservice_usage' );
		// Get data via API if no transient exists.
		$w3tc_usage = empty( $w3tc_usage ) ? Extension_ImageService_Plugin::get_api()->get_usage() : $w3tc_usage;
		// Strip timestamp.
		unset( $w3tc_usage['updated_at'] );

		// Validate hourly data. If no data then set usage to 0 and appropriate limits.
		$w3tc_usage['usage_hourly'] = 'Unknown' !== $w3tc_usage['usage_hourly'] ? $w3tc_usage['usage_hourly'] : 0;

		if ( $w3tc_is_pro ) {
			$w3tc_usage['limit_hourly'] = 'Unknown' !== $w3tc_usage['limit_hourly_licensed'] ? $w3tc_usage['limit_hourly_licensed'] : 10000;
		} else {
			$w3tc_usage['limit_hourly'] = 'Unknown' !== $w3tc_usage['limit_hourly'] ? $w3tc_usage['limit_hourly'] : 100;
		}

		// Validate monthly data. If no data then set usage to 0 and appropriate limits.
		// Remove if pro as we don't show a gauge for pro usage.
		if ( $w3tc_is_pro ) {
			unset( $w3tc_usage['usage_monthly'] );
			unset( $w3tc_usage['limit_monthly'] );
		} else {
			$w3tc_usage['usage_monthly'] = 'Unknown' !== $w3tc_usage['usage_monthly'] ? $w3tc_usage['usage_monthly'] : 0;
			$w3tc_usage['limit_monthly'] = 'Unknown' !== $w3tc_usage['limit_monthly'] ? $w3tc_usage['limit_monthly'] : 1000;
		}

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
					'data' => $w3tc_usage,
					'type' => 'gauge',
				),
			)
		);
		wp_enqueue_script( 'w3tc-webp-widget' );
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
			'w3tc_imageservice',
			'<div class="w3tc-widget-w3tc-logo"></div><div class="w3tc-widget-text">' . __( 'Image Optimization Summary', 'w3-total-cache' ) . '</div>',
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
		include W3TC_DIR . '/Extension_ImageService_Widget_View.php';
	}
}
