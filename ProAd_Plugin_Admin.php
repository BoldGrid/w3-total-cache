<?php
namespace W3TC;

class ProAd_Plugin_Admin {
	function run() {
		$c = Dispatcher::config();
		if ( Util_Environment::is_w3tc_pro( $c ) ) {
			return;
		}

		add_action( 'w3tc_settings_general_boxarea_cdn_footer',
			array( $this, 'w3tc_settings_general_boxarea_cdn_footer' ) );
	}



	public function w3tc_settings_general_boxarea_cdn_footer() {
		$config = Dispatcher::config();

		include  __DIR__ . '/ProAd_Cdnfsd_GeneralPage_View.php';
	}
}
