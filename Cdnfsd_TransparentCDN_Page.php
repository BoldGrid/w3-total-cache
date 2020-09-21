<?php
namespace W3TC;


class Cdnfsd_TransparentCDN_Page {
	// called from plugin-admin
	static public function admin_print_scripts_performance_page_w3tc_cdn() {
		wp_enqueue_script( 'w3tc_cdn_transparentcdn_fsd',
			plugins_url( 'Cdnfsd_TransparentCDN_Page_View.js', W3TC_FILE ),
			array( 'jquery' ), '1.0' , true);
	}

	static public function print_dummy_text() {
		wp_enqueue_script ( 'w3tc_cdn_transparent_dummytext', 
			plugins_url('Cdnfsd_TransparentCDN_Test_Api.js', W3TC_FILE),
			array( 'jquery' ), '1.0');

		$translation_array = array(
			'test_string' => __('Probar los parámetros de cdn ofrecidos para su site', 'w3-total-cache'),
			'test_success' => __('Ok. Parámetros correctos', 'w3-total-cache'),
			'test_failure' => __('Error. Revise sus parámetros o póngase en contacto con soporte.', 'w3-total-cache')
		);
		wp_localize_script('w3tc_cdn_transparent_dummytext', 'transparent_configuration_strings', $translation_array);
	}

	static public function w3tc_settings_box_cdnfsd() {
		$config = Dispatcher::config();
		include  W3TC_DIR . '/Cdnfsd_TransparentCDN_Page_View.php';
	}

}
