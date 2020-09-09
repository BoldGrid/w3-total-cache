<?php
namespace W3TC;



class LazyLoad_GeneralPage {
	/**
	 * W3TC General settings page modifications
	 */
	static public function admin_init_w3tc_general() {
		$o = new LazyLoad_GeneralPage();

		add_filter( 'w3tc_settings_general_anchors',
			array( $o, 'w3tc_settings_general_anchors' ) );
		add_action( 'w3tc_settings_general_boxarea_userexperience',
			array( $o, 'w3tc_settings_general_boxarea_userexperience' ) );
	}




	public function w3tc_settings_general_anchors( $anchors ) {
		$anchors[] = array( 'id' => 'userexperience', 'text' => 'User Experience' );
		return $anchors;
	}



	public function w3tc_settings_general_boxarea_userexperience() {
		$config = Dispatcher::config();

		include  W3TC_DIR . '/LazyLoad_GeneralPage_View.php';
	}
}
