<?php
/**
 * File: Extension_Swarmify_Core.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_Swarmify_Page
 */
class Extension_Swarmify_Page {
	/**
	 * Renders the content of the Swarmify page.
	 *
	 * @return void
	 */
	public function render_content() {
		$w3tc_config = Dispatcher::config();

		$api_key         = $w3tc_config->get_string( array( 'swarmify', 'api_key' ) );
		$w3tc_authorized = ! empty( $api_key );

		$email     = get_bloginfo( 'admin_email' );
		$u         = wp_get_current_user();
		$w3tc_name = $u->first_name . ( empty( $u->first_name ) ? '' : ' ' ) . $u->last_name;

		$swarmify_signup_url = Extension_Swarmify_Core::signup_url();

		include W3TC_DIR . '/Extension_Swarmify_Page_View.php';
	}
}
