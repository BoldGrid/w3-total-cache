<?php
/**
 * File: Extension_Swarmify_Core.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_Swarmify_Core
 */
class Extension_Swarmify_Core {
	/**
	 * Generates the signup URL for Swarmify.
	 *
	 * @return string The generated signup URL.
	 */
	public static function signup_url() {
		$email = get_bloginfo( 'admin_email' );
		$u     = wp_get_current_user();
		$name  = $u->first_name . ( empty( $u->first_name ) ? '' : ' ' ) . $u->last_name;

		return 'https://www.swarmify.com/landing/w3tc?' .
			'email=' . rawurlencode( $email ) .
			'&name=' . rawurlencode( $name ) .
			'&return=' . rawurlencode(
				wp_nonce_url( Util_Ui::admin_url( 'admin.php' ), 'w3tc' ) . '&page=w3tc_extensions&w3tc_swarmify_set_key=set'
			);
	}
}
