<?php
/**
 * File: Licensing_AdminActions.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Licensing_AdminActions
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class Licensing_AdminActions {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config = null;

	/**
	 * Initializes the Licensing_AdminActions class.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Handles the purchase of a plugin license.
	 *
	 * @return void
	 */
	public function w3tc_licensing_buy_plugin() {
		$data_src  = $this->param( 'data_src' );
		$renew_key = $this->param( 'renew_key' );
		$client_id = $this->param( 'client_id' );

		$iframe_url = Licensing_Core::purchase_url( $data_src, $renew_key, $client_id );

		include W3TC_INC_DIR . '/lightbox/purchase.php';
	}

	/**
	 * Retrieves and sanitizes a request parameter.
	 *
	 * @param string $name The name of the parameter to retrieve.
	 *
	 * @return string The sanitized parameter value.
	 */
	private function param( $name ) {
		$param = Util_Request::get_string( $name );
		return preg_replace( '/[^0-9a-zA-Z._\-]/', '', isset( $param ) ? $param : '' );
	}

	/**
	 * Handles the upgrade of a plugin license.
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
	 * Checks and activates the license key.
	 *
	 * @return void
	 */
	public function w3tc_licensing_check_key() {
		$state = Dispatcher::config_state();
		$state->set( 'license.next_check', 0 );
		$state->save();

		Licensing_Core::activate_license( $this->_config->get_string( 'plugin.license_key' ), W3TC_VERSION );
		Util_Admin::redirect( array(), true );
	}

	/**
	 * Resets the root URI for the license.
	 *
	 * @return void
	 */
	public function w3tc_licensing_reset_rooturi() {
		$license_key = $this->_config->get_string( 'plugin.license_key' );

		$state = Dispatcher::config_state();
		$state->set( 'license.next_check', 0 );
		$state->save();

		Licensing_Core::activate_license( $license_key, W3TC_VERSION );

		$license = Licensing_Core::check_license( $license_key, W3TC_VERSION );
		if ( $license ) {
			$status = $license->license_status;
			if ( 'active.' === substr( $status . '.', 0, 7 ) ) {
				Util_Admin::redirect_with_custom_messages2(
					array(
						'notes' => array( 'Your license has been reset already. Activated for this website now.' ),
					),
					true
				);
			}
		}

		$r = Licensing_Core::reset_rooturi( $this->_config->get_string( 'plugin.license_key' ), W3TC_VERSION );

		if ( isset( $r->status ) && 'done' === $r->status ) {
			Util_Admin::redirect_with_custom_messages2(
				array(
					'notes' => array( 'Email with a link for license reset was sent to you' ),
				),
				true
			);
		} else {
			Util_Admin::redirect_with_custom_messages2(
				array(
					'errors' => array( 'Failed to reset license' ),
				),
				true
			);
		}
	}

	/**
	 * Accepts the terms and conditions of the license.
	 *
	 * @return void
	 */
	public static function w3tc_licensing_terms_accept() {
		Licensing_Core::terms_accept();

		Util_Admin::redirect_with_custom_messages2(
			array(
				'notes' => array( 'Terms have been accepted' ),
			),
			true
		);
	}

	/**
	 * Declines the terms and conditions of the license.
	 *
	 * @return void
	 */
	public static function w3tc_licensing_terms_decline() {
		$c = Dispatcher::config();
		if ( ! Util_Environment::is_w3tc_pro( $c ) ) {
			$state_master = Dispatcher::config_state_master();
			$state_master->set( 'license.community_terms', 'decline' );
			$state_master->save();
		}

		Util_Admin::redirect_with_custom_messages2(
			array(
				'notes' => array( 'Terms have been declined' ),
			),
			true
		);
	}

	/**
	 * Refreshes the acceptance or postponement of license terms.
	 *
	 * @return void
	 */
	public static function w3tc_licensing_terms_refresh() {
		$state = Dispatcher::config_state();
		$state->set( 'license.next_check', 0 );
		$state->set( 'license.terms', 'postpone' );
		$state->save();

		Util_Admin::redirect_with_custom_messages2(
			array(
				'notes' => array( 'Thank you for your reaction' ),
			),
			true
		);
	}
}
