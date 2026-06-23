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
		$email     = get_bloginfo( 'admin_email' );
		$u         = wp_get_current_user();
		$w3tc_name = $u->first_name . ( empty( $u->first_name ) ? '' : ' ' ) . $u->last_name;

		return 'https://www.swarmify.com/landing/w3tc?' .
			'email=' . rawurlencode( $email ) .
			'&name=' . rawurlencode( $w3tc_name ) .
			'&return=' . rawurlencode(
				Util_Nonce::admin_nonce_url(
					Util_Ui::admin_url( 'admin.php?page=w3tc_extensions&w3tc_swarmify_set_key=set' ),
					'w3tc_swarmify_set_key'
				)
			);
	}
}
