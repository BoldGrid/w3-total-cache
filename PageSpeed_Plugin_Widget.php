<?php
namespace W3TC;

/**
 * Setups Google PageSpeed dashboard widget
 */
class PageSpeed_Plugin_Widget {
	public function run() {
		add_filter( 'w3tc_monitoring_score', array(
				$this,
				'w3tc_monitoring_score' ) );

		add_action( 'admin_init_w3tc_dashboard', array(
				$this,
				'admin_init_w3tc_dashboard' ) );
		add_action( 'w3tc_ajax', array(
				$this,
				'w3tc_ajax' ) );
	}



	public function w3tc_ajax() {
		add_action( 'w3tc_ajax_pagespeed_widgetdata', array(
				$this, 'w3tc_ajax_pagespeed_widgetdata' ) );
	}



	public function admin_init_w3tc_dashboard() {
		add_action( 'w3tc_widget_setup',
			array( $this, 'wp_dashboard_setup' ), 3000 );
		add_action( 'w3tc_network_dashboard_setup',
			array( $this, 'wp_dashboard_setup' ), 3000 );

		wp_enqueue_script( 'w3tc-widget-pagespeed',
			plugins_url( 'PageSpeed_Widget_View.js', W3TC_FILE ),
			array(), W3TC_VERSION );
		wp_enqueue_style( 'w3tc-widget-pagespeed',
			plugins_url( 'PageSpeed_Widget_View.css', W3TC_FILE ),
			array(), W3TC_VERSION );
	}



	/**
	 * Dashboard setup action
	 *
	 * @return void
	 */
	public function wp_dashboard_setup() {
		Util_Widget::add( 'w3tc_pagespeed',
			'<div class="w3tc-widget-pagespeed-logo"></div>' .
			'<div class="w3tc-widget-text">' .
			__( 'Page Speed Report', 'w3-total-cache' ) .
			'</div>',
			array( $this, 'widget_pagespeed' ),
			Util_Ui::admin_url( 'admin.php?page=w3tc_general#miscellaneous' ),
			'normal' );
	}



	/**
	 * PageSpeed widget
	 *
	 * @return void
	 */
	public function widget_pagespeed() {
		$config = Dispatcher::config();
		$key = $config->get_string( 'widget.pagespeed.key' );

		if ( empty( $key ) )
			include W3TC_DIR . '/PageSpeed_Widget_View_NotConfigured.php';
		else
			include W3TC_DIR . '/PageSpeed_Widget_View.php';
	}



	public function w3tc_ajax_pagespeed_widgetdata() {
		$api_response = null;
		if ( Util_Request::get( 'cache' ) != 'no' ) {
			$r = get_transient( 'w3tc_pagespeed_widgetdata' );
			$r = @json_decode( $r, true );
			if ( is_array( $r ) && isset( $r['time'] ) &&
					$r['time'] >= time() - 3600 ) {
				$api_response = $r;
			}
		}

		if ( is_null( $api_response ) ) {
			$config = Dispatcher::config();
			$key = $config->get_string( 'widget.pagespeed.key' );
			$ref = $config->get_string( 'widget.pagespeed.key.restrict.referrer' );

			$w3_pagespeed = new PageSpeed_Api( $key, $ref );
			$api_response = $w3_pagespeed->analyze( get_home_url() );

			if ( !$api_response ) {
				echo json_encode( array( 'error' => 'API call failed' ) );
				return;
			}

			$api_response['time'] = time();
			set_transient( 'w3tc_pagespeed_widgetdata', json_encode( $api_response ), 3600 );
		}

		ob_start();
		include __DIR__ . '/PageSpeed_Widget_View_FromApi.php';
		$content = ob_get_contents();
		ob_end_clean();

		echo json_encode( array( '.w3tcps_content' => $content ) );
	}



	public function w3tc_monitoring_score( $score ) {
		if ( empty( $_SERVER['HTTP_REFERER'] ) ) {
			return 'n/a';
		}

		$url = $_SERVER['HTTP_REFERER'];

		$config = Dispatcher::config();
		$key = $config->get_string( 'widget.pagespeed.key' );
		$ref = $config->get_string( 'widget.pagespeed.key.restrict.referrer' );
		$w3_pagespeed = new PageSpeed_Api( $key, $ref );

		$r = $w3_pagespeed->get_page_score( $url );

		if ( !is_null( $r ) ) {
			$score .= (int)((float)$r * 100) . ' / 100';
		}

		return $score;
	}
}
