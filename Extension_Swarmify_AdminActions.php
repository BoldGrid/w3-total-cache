<?php
/**
 * File: Extension_Swarmify_AdminActions.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_Swarmify_AdminActions
 */
class Extension_Swarmify_AdminActions {
	/**
	 * Sets the Swarmify API key based on the provided input.
	 *
	 * @return void
	 */
	public function w3tc_swarmify_set_key() {
		$status_val      = Util_Request::get_string( 'status' );
		$swarmcdnkey_val = Util_Request::get_string( 'swarmcdnkey' );
		if ( ! empty( $status_val ) && ! empty( $swarmcdnkey_val ) && '1' === $status_val ) {
			$w3tc_config = Dispatcher::config();
			$w3tc_config->set( array( 'swarmify', 'api_key' ), $swarmcdnkey_val );
			$w3tc_config->save();
		}

		Util_Environment::redirect( Util_Ui::admin_url( 'admin.php?page=w3tc_extensions&extension=swarmify&action=view' ) );
		exit();
	}
}
