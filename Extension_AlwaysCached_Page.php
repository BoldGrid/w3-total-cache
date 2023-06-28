<?php
namespace W3TC;

class Extension_AlwaysCached_Page {
	static public function admin_print_scripts() {
		if ( Util_Request::get_string( 'extension' ) == 'alwayscached' ) {
			wp_enqueue_script( 'w3tc_extension_alwayscached',
				plugins_url( 'Extension_AlwaysCached_Page_View.js', W3TC_FILE ),
				[], '1.0', true );
		}
	}



	static public function w3tc_extension_page_alwayscached() {
		$config = Dispatcher::config();
		include  W3TC_DIR . '/Extension_AlwaysCached_Page_View.php';
	}



	static public function w3tc_ajax() {
		add_action( 'w3tc_ajax_extension_alwayscached_queue', [
			'\W3TC\Extension_AlwaysCached_Page',
			'w3tc_ajax_extension_alwayscached_queue'
		] );
	}



	static public function w3tc_ajax_extension_alwayscached_queue() {
		include W3TC_DIR . '/Extension_AlwaysCached_Page_Queue_View.php';
		exit();
	}
}
