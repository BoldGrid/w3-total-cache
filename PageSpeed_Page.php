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
			$w3tc_w3_pagespeed = empty( $w3tc_access_token ) ? null : new PageSpeed_Api( $w3tc_access_token );

			// Construction may clear credentials when a refresh is irrecoverable, so re-read the stored token.
			$w3tc_stored_token = $w3tc_config->get_string( 'widget.pagespeed.access_token' );

			if ( empty( $w3tc_w3_pagespeed ) || empty( $w3tc_stored_token ) ) {
				echo wp_json_encode(
					array(
						'missing_token' => PageSpeed_Api::get_authorize_required_message(),
					)
				);
				return;
			}

			if ( $w3tc_w3_pagespeed->client->isAccessTokenExpired() ) {
				echo wp_json_encode(
					array(
						'refresh_failed' => PageSpeed_Api::get_refresh_pending_message(),
					)
				);
				return;
			}

			$api_response = $w3tc_w3_pagespeed->analyze( $w3tc_url );
			$w3tc_error   = self::get_api_error_html( $w3tc_url, $api_response );

			if ( '' !== $w3tc_error ) {
				$api_response_error = array(
					'error' => $w3tc_error,
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

	/**
	 * Builds the error markup for a failed PageSpeed analysis.
	 *
	 * The analyzed URL and the API error code/message are rendered as text.
	 *
	 * @since 2.11.1
	 *
	 * @param string $w3tc_url     Analyzed URL.
	 * @param mixed  $api_response PageSpeed API response.
	 *
	 * @return string Error markup, or an empty string when the response has no error.
	 */
	public static function get_api_error_html( $w3tc_url, $api_response ) {
		if ( ! $api_response ) {
			$w3tc_title = \__( 'API request failed!', 'w3-total-cache' );
			$w3tc_rows  = array(
				array( \__( 'Analyze URL : ', 'w3-total-cache' ), $w3tc_url ),
			);
		} elseif ( ! empty( $api_response['error'] ) ) {
			$w3tc_title = \__( 'API request error!', 'w3-total-cache' );
			$w3tc_rows  = array(
				array( \__( 'Analyze URL : ', 'w3-total-cache' ), $w3tc_url ),
				array( \__( 'Response Code : ', 'w3-total-cache' ), self::get_error_field( $api_response, 'code' ) ),
				array( \__( 'Response Message : ', 'w3-total-cache' ), self::get_error_field( $api_response, 'message' ) ),
			);
		} elseif ( ! empty( $api_response['mobile']['error'] ) && ! empty( $api_response['desktop']['error'] ) ) {
			$w3tc_title = \__( 'API request error!', 'w3-total-cache' );
			$w3tc_rows  = array(
				array( \__( 'Analyze URL : ', 'w3-total-cache' ), $w3tc_url ),
				array( \__( 'Mobile response Code : ', 'w3-total-cache' ), self::get_error_field( $api_response['mobile'], 'code' ) ),
				array( \__( 'Mobile response Message : ', 'w3-total-cache' ), self::get_error_field( $api_response['mobile'], 'message' ) ),
				array( \__( 'Desktop response Code : ', 'w3-total-cache' ), self::get_error_field( $api_response['desktop'], 'code' ) ),
				array( \__( 'Desktop response Message : ', 'w3-total-cache' ), self::get_error_field( $api_response['desktop'], 'message' ) ),
			);
		} else {
			return '';
		}

		$w3tc_html = '<p><strong>' . \esc_html( $w3tc_title ) . '</strong></p>';

		foreach ( $w3tc_rows as $w3tc_row ) {
			$w3tc_html .= '<p>' . \esc_html( $w3tc_row[0] ) . \esc_html( (string) $w3tc_row[1] ) . '</p>';
		}

		return $w3tc_html;
	}

	/**
	 * Reads a scalar field from a PageSpeed API error entry.
	 *
	 * @since 2.11.1
	 *
	 * @param array  $w3tc_result PageSpeed result containing an 'error' entry.
	 * @param string $w3tc_field  Error field name.
	 *
	 * @return string Field value, or 'N/A' when missing or not scalar.
	 */
	private static function get_error_field( $w3tc_result, $w3tc_field ) {
		$w3tc_value = isset( $w3tc_result['error'][ $w3tc_field ] ) ? $w3tc_result['error'][ $w3tc_field ] : null;

		return ( ! empty( $w3tc_value ) && \is_scalar( $w3tc_value ) ) ? (string) $w3tc_value : 'N/A';
	}
}
