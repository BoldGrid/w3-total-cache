<?php
namespace W3TC;



class LazyLoad_Plugin_Admin {
	function run() {
		add_filter( 'w3tc_admin_menu', array( $this, 'w3tc_admin_menu' ) );
		add_action( 'w3tc_settings_page-w3tc_userexperience',
			array( $this, 'w3tc_settings_page_w3tc_userexperience' ) );
		add_action( 'admin_init_w3tc_general',
			array( '\W3TC\LazyLoad_GeneralPage', 'admin_init_w3tc_general' ) );
	}



	public function w3tc_admin_menu( $menu ) {
		$c = Dispatcher::config();

		$menu['w3tc_userexperience'] = array(
			'page_title' => __( 'User Experience', 'w3-total-cache' ),
			'menu_text' => __( 'User Experience', 'w3-total-cache' ),
			'visible_always' => false,
			'order' => 1200
		);

		return $menu;
	}



	public function w3tc_settings_page_w3tc_userexperience() {
		$v = new LazyLoad_Page();
		$v->render_content();
	}
}
