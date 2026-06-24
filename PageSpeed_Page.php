<?php
/**
 * File: PageSpeed_Page.php
 *
 * Controller for PageSpeed page setup, display, and AJAX handler.
 *
 * @since 2.3.0 Update to utilize OAuth2.0 and overhaul of feature.
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * PageSpeed Page.
 *
 * @since 2.3.0
 */
class PageSpeed_Page {
	/**
	 * Run PageSpeed Page.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'w3tc_ajax_pagespeed_data', array( $this, 'w3tc_ajax_pagespeed_data' ) );
	}

	/**
	 * Initialize PageSpeed scripts/styles.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public static function admin_print_scripts_w3tc_pagespeed() {
		wp_register_script(
			'w3tc-pagespeed',
			esc_url( plugins_url( 'PageSpeed_Page_View.js', W3TC_FILE ) ),
			array( 'w3tc-nonce' ),
			W3TC_VERSION,
			true
		);
		wp_localize_script(
			'w3tc-pagespeed',
			'w3tcData',
			array(
				'lang' => array(
					'pagespeed_data_error'   => __( 'Error : ', 'w3-total-cache' ),
					'pagespeed_filter_error' => __( 'An unknown error occured attempting to filter audit results!', 'w3-total-cache' ),
				),
			)
		);
		wp_enqueue_script( 'w3tc-pagespeed' );

		wp_enqueue_style(
			'w3tc-pagespeed',
			esc_url( plugins_url( 'PageSpeed_Page_View.css', W3TC_FILE ) ),
			array(),
			W3TC_VERSION
		);
	}

	/**
	 * Renders the PageSpeed feature.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function render() {
		$w3tc_c = Dispatcher::config();

		require W3TC_DIR . '/PageSpeed_Page_View.php';
	}

	/**
	 * PageSpeed AJAX fetch data.
	 *
	 * @since 2.3.0
	 *
	 * @return JSON
	 */
	public function w3tc_ajax_pagespeed_data() {
		$encoded_url        = Util_Request::get( 'url' );
		$w3tc_url           = ( ! empty( $encoded_url ) ? urldecode( $encoded_url ) : get_home_url() );
		$api_response       = null;
		$api_response_error = null;

		if ( Util_Request::get( 'cache' ) !== 'no' ) {
			$cache = get_option( 'w3tc_pagespeed_data_' . $encoded_url );
			$cache = json_decode( $cache, true );
			if ( is_array( $cache ) && isset( $cache['time'] ) && $cache['time'] >= time() - Util_PageSpeed::get_cache_life() ) {
				$api_response = $cache;
			}
		}

		if ( is_null( $api_response ) ) {
			$w3tc_config       = Dispatcher::config();
			$w3tc_access_token = ! empty( $w3tc_config->get_string( 'widget.pagespeed.access_token' ) ) ? $w3tc_config->get_string( 'widget.pagespeed.access_token' ) : null;

			if ( empty( $w3tc_access_token ) ) {
				echo wp_json_encode(
					array(
						'missing_token' => sprintf(
							// translators: 1 HTML a tag to W3TC settings page Google PageSpeed meta box.
							__(
								'Before you can get started using the Google PageSpeed tool, you’ll first need to authorize access. Please click %1$s.',
								'w3-total-cache'
							),
							'<a href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#google_pagespeed' ) ) . '" target="_blank">' . esc_html__( 'here', 'w3-total-cache' ) . '</a>'
						),
					)
				);
				return;
			}

			$w3tc_w3_pagespeed = new PageSpeed_Api( $w3tc_access_token );
			$api_response      = $w3tc_w3_pagespeed->analyze( $w3tc_url );

			if ( ! $api_response ) {
				$api_response_error = array(
					'error' => '<p><strong>' . esc_html__( 'API request failed!', 'w3-total-cache' ) . '</strong></p>
						<p>' . esc_html__( 'Analyze URL : ', 'w3-total-cache' ) . $w3tc_url . '</p>',
				);
				delete_option( 'w3tc_pagespeed_data_' . $encoded_url );
			} elseif ( ! empty( $api_response['error'] ) ) {
				$error_code    = ! empty( $api_response['error']['code'] ) ? $api_response['error']['code'] : 'N/A';
				$error_message = ! empty( $api_response['error']['message'] ) ? $api_response['error']['message'] : 'N/A';

				$api_response_error = array(
					'error' => '<p><strong>' . esc_html__( 'API request error!', 'w3-total-cache' ) . '</strong></p>
						<p>' . esc_html__( 'Analyze URL : ', 'w3-total-cache' ) . $w3tc_url . '</p>
						<p>' . esc_html__( 'Response Code : ', 'w3-total-cache' ) . $error_code . '</p>
						<p>' . esc_html__( 'Response Message : ', 'w3-total-cache' ) . $error_message . '</p>',
				);
				delete_option( 'w3tc_pagespeed_data_' . $encoded_url );
			} elseif ( ! empty( $api_response['mobile']['error'] ) && ! empty( $api_response['desktop']['error'] ) ) {
				$mobile_error_code     = ! empty( $api_response['mobile']['error']['code'] ) ? $api_response['mobile']['error']['code'] : 'N/A';
				$mobile_error_message  = ! empty( $api_response['mobile']['error']['message'] ) ? $api_response['mobile']['error']['message'] : 'N/A';
				$desktop_error_code    = ! empty( $api_response['desktop']['error']['code'] ) ? $api_response['desktop']['error']['code'] : 'N/A';
				$desktop_error_message = ! empty( $api_response['desktop']['error']['message'] ) ? $api_response['desktop']['error']['message'] : 'N/A';

				$api_response_error = array(
					'error' => '<p><strong>' . esc_html__( 'API request error!', 'w3-total-cache' ) . '</strong></p>
						<p>' . esc_html__( 'Analyze URL : ', 'w3-total-cache' ) . $w3tc_url . '</p>
						<p>' . esc_html__( 'Mobile response Code : ', 'w3-total-cache' ) . $mobile_error_code . '</p>
						<p>' . esc_html__( 'Mobile response Message : ', 'w3-total-cache' ) . $mobile_error_message . '</p>
						<p>' . esc_html__( 'Desktop response Code : ', 'w3-total-cache' ) . $desktop_error_code . '</p>
						<p>' . esc_html__( 'Desktop response Message : ', 'w3-total-cache' ) . $desktop_error_message . '</p>',
				);
				delete_option( 'w3tc_pagespeed_data_' . $encoded_url );
			} else {
				$api_response['time']         = time();
				$api_response['display_time'] = \current_time( 'M jS, Y g:ia', false );
				update_option( 'w3tc_pagespeed_data_' . $encoded_url, wp_json_encode( $api_response ), false );
			}
		}

		ob_start();
		include __DIR__ . '/PageSpeed_Page_View_FromAPI.php';
		$content = ob_get_contents();
		ob_end_clean();

		/*
		 * Build the response metric-by-metric. A strategy (mobile/desktop) that errored carries an
		 * 'error' entry instead of metric data, so reading its keys directly would emit "Undefined index"
		 * notices that corrupt the JSON payload. Util_PageSpeed::get_value_recursive() returns null for any
		 * missing key, keeping the response well-formed regardless of partial failures.
		 */
		$w3tcps_metrics = array(
			'w3tcps_first_contentful_paint'   => 'first-contentful-paint',
			'w3tcps_largest_contentful_paint' => 'largest-contentful-paint',
			'w3tcps_interactive'              => 'interactive',
			'w3tcps_cumulative_layout_shift'  => 'cumulative-layout-shift',
			'w3tcps_total_blocking_time'      => 'total-blocking-time',
			'w3tcps_speed_index'              => 'speed-index',
		);

		$w3tcps_response = array(
			'w3tcps_domain' => $w3tc_url,
			'w3tcps_score'  => array(
				'mobile'  => Util_PageSpeed::get_value_recursive( $api_response, array( 'mobile', 'score' ) ),
				'desktop' => Util_PageSpeed::get_value_recursive( $api_response, array( 'desktop', 'score' ) ),
			),
		);

		foreach ( $w3tcps_metrics as $w3tcps_key => $w3tcps_metric ) {
			$w3tcps_response[ $w3tcps_key ] = array(
				'mobile'  => array(
					'score'        => Util_PageSpeed::get_value_recursive( $api_response, array( 'mobile', $w3tcps_metric, 'score' ) ),
					'displayValue' => Util_PageSpeed::get_value_recursive( $api_response, array( 'mobile', $w3tcps_metric, 'displayValue' ) ),
				),
				'desktop' => array(
					'score'        => Util_PageSpeed::get_value_recursive( $api_response, array( 'desktop', $w3tcps_metric, 'score' ) ),
					'displayValue' => Util_PageSpeed::get_value_recursive( $api_response, array( 'desktop', $w3tcps_metric, 'displayValue' ) ),
				),
			);
		}

		$w3tcps_response['w3tcps_content']   = $content;
		$w3tcps_response['w3tcps_timestamp'] = ! empty( $api_response['display_time'] ) ? $api_response['display_time'] : '';

		echo wp_json_encode( $w3tcps_response );
	}
}
