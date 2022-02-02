<?php
namespace W3TC;



class Extension_Swarmify_AdminActions {
	public function w3tc_swarmify_set_key() {
		if ( isset( Util_Request::get_string( 'status' ) ) && isset( Util_Request::get_string( 'swarmcdnkey' ) ) && Util_Request::get_string( 'status' ) == '1' ) {
			$config = Dispatcher::config();
			$config->set( array( 'swarmify', 'api_key' ), Util_Request::get_string( 'swarmcdnkey' ) );
			$config->save();
		}

		Util_Environment::redirect( Util_Ui::admin_url(
			'admin.php?page=w3tc_extensions&extension=swarmify&action=view' ) );
		exit();
	}
}
