<?php
namespace W3TC;

class PageSpeed_Page {
	public function run() {
		add_action( 'admin_print_scripts-performance_page_w3tc_pagespeed', array(
				$this,
				'admin_print_scripts_w3tc_pagespeed' ) );
		add_action( 'w3tc_ajax', array(
				$this,
				'w3tc_ajax' ) );
	}

    public function w3tc_ajax() {
		add_action( 'w3tc_ajax_pagespeed_data', array(
				$this, 'w3tc_ajax_pagespeed_data' ) );
	}

	public function admin_print_scripts_w3tc_pagespeed() {
        wp_enqueue_style( 'w3tc-pagespeed-google-material-icons',
            'https://fonts.googleapis.com/icon?family=Material+Icons',
            array(), W3TC_VERSION );
        wp_enqueue_style( 'w3tc-pagespeed',
			plugins_url( 'PageSpeed_Page_View.css', W3TC_FILE ),
			array(), W3TC_VERSION );
		wp_enqueue_script( 'w3tc-pagespeed',
			plugins_url( 'PageSpeed_Page_View.js', W3TC_FILE ),
			array(), W3TC_VERSION );
	}

	public function render() {
		$c = Dispatcher::config();
		
        include  W3TC_DIR . '/PageSpeed_Page_View.php';
	}

    public function w3tc_ajax_pagespeed_data() {
        $encoded_url = Util_Request::get( 'url');

        $url = ( !empty( $encoded_url ) ? urldecode( $encoded_url ) : get_home_url() );

		$api_response = null;

        /*
		if ( Util_Request::get( 'cache' ) != 'no' ) {
			$r = get_transient( 'w3tc_pagespeed_data_' . $encoded_url );
			$r = @json_decode( $r, true );
			if ( is_array( $r ) && isset( $r['time'] ) &&
					$r['time'] >= time() - 3600 ) {
				$api_response = $r;
			}
        }
        */

		if ( is_null( $api_response ) ) {
			$config = Dispatcher::config();
			$key = $config->get_string( 'widget.pagespeed.key' );
			$ref = $config->get_string( 'widget.pagespeed.key.restrict.referrer' );

			$w3_pagespeed = new PageSpeed_Api( $key, $ref );
			$api_response = $w3_pagespeed->analyze( $url );

			if ( !$api_response ) {
				echo json_encode( array( 'error' => 'API call failed' ) );
				return;
			}

			$api_response['time'] = time();
			//set_transient( 'w3tc_pagespeed_data_' . $encoded_url, json_encode( $api_response ), 3600 );
		}

        ob_start();
		include __DIR__ . '/PageSpeed_Page_View_FromAPI.php';
		$content = ob_get_contents();
		ob_end_clean();

		echo json_encode( array( '.w3tcps_content' => $content ) );
        
	}
}