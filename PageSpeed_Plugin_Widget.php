<?php
/**
 * File: PageSpeed_Plugin_Widget.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Setups Google PageSpeed dashboard widget
 */
class PageSpeed_Plugin_Widget {
	/**
	 * Run PageSpeed widget
	 *
	 * @return void
	 */
	public function run() {
		add_filter( 'w3tc_monitoring_score', array( $this, 'w3tc_monitoring_score' ) );
		add_action( 'admin_init_w3tc_dashboard', array( $this, 'admin_init_w3tc_dashboard' ) );
		add_action( 'w3tc_ajax', array( $this, 'w3tc_ajax' ) );
	}

	/**
	 * Register AJAX method for fetching PageSpeed widget data
	 *
	 * @return void
	 */
	public function w3tc_ajax() {
		add_action( 'w3tc_ajax_pagespeed_widgetdata', array( $this, 'w3tc_ajax_pagespeed_widgetdata' ) );
	}

	/**
	 * Initialize PageSpeed widget scripts/styles
	 *
	 * @return void
	 */
	public function admin_init_w3tc_dashboard() {
		add_action( 'w3tc_widget_setup', array( $this, 'wp_dashboard_setup' ), 3000 );
		add_action( 'w3tc_network_dashboard_setup', array( $this, 'wp_dashboard_setup' ), 3000 );

		wp_enqueue_style(
			'w3tc-pagespeed-google-material-icons',
			'https://fonts.googleapis.com/icon?family=Material+Icons',
			array(),
			W3TC_VERSION
		);
		wp_enqueue_script(
			'w3tc-widget-pagespeed',
			plugins_url( 'PageSpeed_Widget_View.js', W3TC_FILE ),
			array(),
			W3TC_VERSION,
			'false'
		);
		wp_enqueue_style(
			'w3tc-widget-pagespeed',
			plugins_url( 'PageSpeed_Widget_View.css', W3TC_FILE ),
			array(),
			W3TC_VERSION
		);
	}

	/**
	 * Dashboard setup action
	 *
	 * @return void
	 */
	public function wp_dashboard_setup() {
		Util_Widget::add(
			'w3tc_pagespeed',
			'<div class="w3tc-widget-pagespeed-logo"></div>' .
				'<div class="w3tc-widget-text">' . esc_html__( 'Page Speed Report', 'w3-total-cache' ) . '</div>',
			array( $this, 'widget_pagespeed' ),
			Util_Ui::admin_url( 'admin.php?page=w3tc_general#miscellaneous' ),
			'normal'
		);
	}

	/**
	 * PageSpeed widget
	 *
	 * @return void
	 */
	public function widget_pagespeed() {
		$config = Dispatcher::config();
		$key    = $config->get_string( 'widget.pagespeed.key' );

		if ( empty( $key ) ) {
			include W3TC_DIR . '/PageSpeed_Widget_View_NotConfigured.php';
		} else {
			include W3TC_DIR . '/PageSpeed_Widget_View.php';
		}
	}

	/**
	 * PageSpeed widget AJAX fetch data
	 *
	 * @return JSON
	 */
	public function w3tc_ajax_pagespeed_widgetdata() {
		$api_response = null;
		if ( Util_Request::get( 'cache' ) !== 'no' ) {
			$r = get_transient( 'w3tc_pagespeed_widgetdata' );
			$r = json_decode( $r, true );
			if ( is_array( $r ) && isset( $r['time'] ) && $r['time'] >= time() - 3600 ) {
				$api_response = $r;
			}
		}

		if ( is_null( $api_response ) ) {
			$config       = Dispatcher::config();
			$access_token = ! empty( $config->get_string( 'widget.pagespeed.access_token' ) ) ? $config->get_string( 'widget.pagespeed.access_token' ) : null;

			if ( empty( $access_token ) ) {
				echo wp_json_encode(
					array(
						'error' => sprintf(
							// translators: 1 HTML a tag to W3TC settings page Google PageSpeed meta box.
							__(
								'It appears that your Google Access token is either missing, expired, or invalid. Please click %1$s to obtain a new Google access token or to refresh an expired one.',
								'w3-total-cache'
							),
							'<a href="' . filter_var( '/wp-admin/admin.php?page=w3tc_general#google_page_speed', FILTER_SANITIZE_URL ) . '">' . esc_html__( 'here', 'w3-total-cache' ) . '</a>'
						),
					),
				);
				return;
			}

			$w3_pagespeed = new PageSpeed_Api( $access_token );
			$api_response = $w3_pagespeed->analyze( get_home_url() );
			$home_url = get_home_url();

			if ( ! $api_response ) {
				echo wp_json_encode(
					array(
						'error' => sprintf(
							// translators: 1 Request URL value.
							__(
								'API request failed<br/><br/>
									Analyze URL: %1$s',
								'w3-total-cache'
							),
							$home_url
						),
					)
				);
				return;
			} elseif ( ! empty( $api_response['error'] ) ) {
				echo wp_json_encode(
					array(
						'error' => sprintf(
							// translators: 1 Request URL value, 2 Request response code, 3 Error message.
							__(
								'API request error<br/><br/>
									Analyze URL: %1$s<br/><br/>
									Response Code: %2$s<br/>
									Response Message: %3$s<br/>',
								'w3-total-cache'
							),
							$home_url,
							! empty( $api_response['error']['code'] ) ? $api_response['error']['code'] : 'N/A',
							! empty( $api_response['error']['message'] ) ? $api_response['error']['message'] : 'N/A'
						),
					)
				);
				return;
			} elseif ( ! empty( $api_response['mobile']['error'] ) && ! empty( $api_response['desktop']['error'] ) ) {
				echo wp_json_encode(
					array(
						'error' => sprintf(
							// translators: 1 Request URL value, 2 Request response code, 3 Error message.
							__(
								'API request error<br/><br/>
									Analyze URL: %1$s<br/><br/>
									Mobile response Code: %2$s<br/>Mobile response Message: %3$s<br/><br/>
									Desktop response Code: %4$s<br/>Desktop response Message: %5$s',
								'w3-total-cache'
							),
							$home_url,
							! empty( $api_response['mobile']['error']['code'] ) ? $api_response['mobile']['error']['code'] : 'N/A',
							! empty( $api_response['mobile']['error']['message'] ) ? $api_response['mobile']['error']['message'] : 'N/A',
							! empty( $api_response['desktop']['error']['code'] ) ? $api_response['desktop']['error']['code'] : 'N/A',
							! empty( $api_response['desktop']['error']['message'] ) ? $api_response['desktop']['error']['message'] : 'N/A'
						),
					)
				);
				return;
			}


			$api_response['time'] = time();

			set_transient( 'w3tc_pagespeed_widgetdata', wp_json_encode( $api_response ), 3600 );
		}

		ob_start();
		include __DIR__ . '/PageSpeed_Widget_View_FromApi.php';
		$content = ob_get_contents();
		ob_end_clean();

		echo wp_json_encode( array( '.w3tcps_content' => $content ) );
	}

	/**
	 * Generates the desktop/mobile PageSpeed scores
	 *
	 * @param int $score PageSpeed score.
	 *
	 * @return [type]
	 */
	public function w3tc_monitoring_score( $score ) {
		if ( empty( $_SERVER['HTTP_REFERER'] ) ) {
			return 'n/a';
		}

		$config       = Dispatcher::config();
		$access_token = ! empty( $config->get_string( 'widget.pagespeed.access_token' ) ) ? $config->get_string( 'widget.pagespeed.access_token' ) : null;
		
		if ( empty( $access_token ) ) {
			header( 'Location: ' . filter_var( '/wp-admin/admin.php?page=w3tc_general#google_page_speed', FILTER_SANITIZE_URL ) );
			exit;
		}

		$url          = isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$w3_pagespeed = new PageSpeed_Api( $access_token );
		$r            = $w3_pagespeed->get_page_score( $url );
		
		if ( ! is_null( $r ) ) {
			$score .= (int) ( (float) $r * 100 ) . ' / 100';
		}

		return $score;
	}
}
