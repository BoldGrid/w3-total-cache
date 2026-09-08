<?php
/**
 * File: Generic_Tos.php
 *
 * Community terms of service, independent of usage tracking.
 *
 * @package W3TC
 * @since   2.10.5
 */

namespace W3TC;

/**
 * Class: Generic_Tos
 *
 * @since 2.10.5
 */
class Generic_Tos {
	/**
	 * Accept community terms without enabling analytics.
	 *
	 * @since 2.10.5
	 *
	 * @return void
	 */
	public static function accept() {
		$state_master = Dispatcher::config_state_master();
		$state_master->set( 'license.community_terms', 'accept' );
		$state_master->save();
	}

	/**
	 * Decline community terms.
	 *
	 * @since 2.10.5
	 *
	 * @return void
	 */
	public static function decline() {
		$state_master = Dispatcher::config_state_master();
		$state_master->set( 'license.community_terms', 'decline' );
		$state_master->save();
	}

	/**
	 * Current terms choice.
	 *
	 * @since 2.10.5
	 *
	 * @return string
	 */
	public static function get_choice() {
		$w3tc_config = Dispatcher::config();

		if ( Util_Environment::is_w3tc_pro( $w3tc_config ) ) {
			$state = Dispatcher::config_state();
			return $state->get_string( 'license.terms' );
		}

		$state_master = Dispatcher::config_state_master();
		return $state_master->get_string( 'license.community_terms' );
	}
}
