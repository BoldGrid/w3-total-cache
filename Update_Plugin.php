<?php
namespace W3TC;

class Update_Plugin {
	static $key = 'w3-total-cache/w3-total-cache.php';
	static $rubicon_version = '2.1.4';

	static $pro_key = 'w3-total-cache-pro/w3-total-cache-pro.php';



	public function run() {
		add_filter( 'site_transient_update_plugins',
			array( $this, 'site_transient_update_plugins' ) );
		add_filter( 'w3tc_admin_menu',
			array( $this, 'w3tc_admin_menu' ) );
		add_action( 'w3tc_settings_page-w3tc_update',
			array( $this, 'w3tc_settings_page_w3tc_update' ) );
		add_action( 'after_plugin_row_' . self::$key,
			array( $this, 'after_plugin_row' ) );
	}



	public function after_plugin_row() {
		$v = get_site_transient( 'update_plugins' );
		if ( !isset( $v->w3tc_new_available_version ) ) {
			return;
		}

		require_once( __DIR__ . '/Update_PluginsRow_View.php' );
	}



	public function site_transient_update_plugins( $v ) {
		if ( isset( $v->response[self::$key] ) ) {
			$new_version = $v->response[self::$key]->new_version;
			if ( version_compare( $new_version, self::$rubicon_version ) > 0 &&
					!is_plugin_active( self::$pro_key ) ) {
				$v->w3tc_new_available_version = $new_version;
				unset( $v->response[self::$key] );
			}
		}

		return $v;
	}


	public function w3tc_admin_menu( $menu ) {
		$v = get_site_transient( 'update_plugins' );

		if ( isset( $v->w3tc_new_available_version ) ) {
			$menu['w3tc_update'] = array(
				'page_title'     => __( 'Update', 'w3-total-cache' ),
				'menu_text'      => __( 'Update', 'w3-total-cache' ),
				'visible_always' => true,
				'order'          => 50,
			);
		}

		return $menu;
	}



	public function w3tc_settings_page_w3tc_update() {
		require_once( __DIR__ . '/Update_Page_View.php' );
	}
}
