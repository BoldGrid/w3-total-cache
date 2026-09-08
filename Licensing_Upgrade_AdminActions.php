<?php
/**
 * File: Licensing_Upgrade_AdminActions.php
 *
 * Community overlay for the Pro upsell lightbox. Checkout is not
 * iframed; that flow is a new-tab link from upgrade.php. Companion
 * Pro_Plugin replaces this handler with Licensing_AdminActions.
 *
 * @package W3TC
 * @since   2.10.5
 */

namespace W3TC;

/**
 * Class: Licensing_Upgrade_AdminActions
 *
 * @since 2.10.5
 */
class Licensing_Upgrade_AdminActions {
	/**
	 * Renders the upgrade overlay (ad iframe + checkout link).
	 *
	 * @since 2.10.5
	 *
	 * @return void
	 */
	public function w3tc_licensing_upgrade() {
		$data_src  = $this->param( 'data_src' );
		$renew_key = $this->param( 'renew_key' );
		$client_id = $this->param( 'client_id' );

		include W3TC_INC_DIR . '/lightbox/upgrade.php';
	}

	/**
	 * Sanitizes a request parameter for the overlay.
	 *
	 * @since 2.10.5
	 *
	 * @param string $w3tc_name Parameter name.
	 *
	 * @return string
	 */
	private function param( $w3tc_name ) {
		$param = Util_Request::get_string( $w3tc_name );

		return preg_replace( '/[^0-9a-zA-Z._\-]/', '', isset( $param ) ? $param : '' );
	}
}
