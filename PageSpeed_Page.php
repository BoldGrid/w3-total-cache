<?php
/**
 * File: PageSpeed_Page.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * PageSpeed Page
 */
class PageSpeed_Page {
	/**
	 * Run PageSpeed Page
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'admin_print_scripts-performance_page_w3tc_pagespeed', array( $this, 'admin_print_scripts_w3tc_pagespeed' ) );
		add_action( 'w3tc_ajax', array( $this, 'w3tc_ajax' ) );
	}

	/**
	 * Register AJAX methods
	 *
	 * @return void
	 */
	public function w3tc_ajax() {
		// This AJAX works but is disabled due to being non-active
		//add_action( 'w3tc_ajax_pagespeed_data', array( $this, 'w3tc_ajax_pagespeed_data' ) );
	}

	/**
	 * Initialize PageSpeed scripts/styles
	 *
	 * @return void
	 */
	public function admin_print_scripts_w3tc_pagespeed() {
		wp_enqueue_style( 'w3tc-pagespeed-google-material-icons', 'https://fonts.googleapis.com/icon?family=Material+Icons', array(), W3TC_VERSION );
		wp_enqueue_style( 'w3tc-pagespeed', plugins_url( 'PageSpeed_Page_View.css', W3TC_FILE ), array(), W3TC_VERSION );
		wp_enqueue_script( 'w3tc-pagespeed', plugins_url( 'PageSpeed_Page_View.js', W3TC_FILE ), array(), W3TC_VERSION, false );
	}

	/**
	 * Renders the PageSpeed feature
	 *
	 * @return void
	 */
	public function render() {
		$c = Dispatcher::config();

		//require W3TC_DIR . '/PageSpeed_Page_View.php';
		require W3TC_DIR . '/PageSpeed_Page_View_FromAPI.php';
	}

	/**
	 * PageSpeed AJAX fetch data
	 *
	 * @return JSON
	 */
	public function w3tc_ajax_pagespeed_data() {
		$encoded_url = Util_Request::get( 'url' );

		$url = ( ! empty( $encoded_url ) ? urldecode( $encoded_url ) : get_home_url() );

		$api_response = null;

		if ( Util_Request::get( 'cache' ) !== 'no' ) {
			$r = get_transient( 'w3tc_pagespeed_data_' . $encoded_url );
			$r = @json_decode( $r, true );
			if ( is_array( $r ) && isset( $r['time'] ) &&
					$r['time'] >= time() - 3600 ) {
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

			$w3_pagespeed->refresh_token_check();

			$api_response = $w3_pagespeed->analyze( $url );

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
							$url
						),
					)
				);
				delete_transient( 'w3tc_pagespeed_data_' . $encoded_url );
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
							$url,
							! empty( $api_response['error']['code'] ) ? $api_response['error']['code'] : 'N/A',
							! empty( $api_response['error']['message'] ) ? $api_response['error']['message'] : 'N/A'
						),
					)
				);
				delete_transient( 'w3tc_pagespeed_data_' . $encoded_url );
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
							$url,
							! empty( $api_response['mobile']['error']['code'] ) ? $api_response['mobile']['error']['code'] : 'N/A',
							! empty( $api_response['mobile']['error']['message'] ) ? $api_response['mobile']['error']['message'] : 'N/A',
							! empty( $api_response['desktop']['error']['code'] ) ? $api_response['desktop']['error']['code'] : 'N/A',
							! empty( $api_response['desktop']['error']['message'] ) ? $api_response['desktop']['error']['message'] : 'N/A'
						),
					)
				);
				delete_transient( 'w3tc_pagespeed_data_' . $encoded_url );
				return;
			}

			$api_response['time'] = time();

			set_transient( 'w3tc_pagespeed_data_' . $encoded_url, wp_json_encode( $api_response ), 3600 );
		}

		ob_start();
		include __DIR__ . '/PageSpeed_Page_View_FromAPI.php';
		$content = ob_get_contents();
		ob_end_clean();

		echo wp_json_encode( array( '.w3tcps_content' => $content ) );
	}
}
