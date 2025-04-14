<?php
/**
 * File: Extension_NewRelic_AdminActions.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_NewRelic_AdminActions
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class Extension_NewRelic_AdminActions {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config = null;

	/**
	 * Constructor for initializing the configuration.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Saves the New Relic application settings.
	 *
	 * @return void
	 */
	public function w3tc_save_new_relic() {
		$service                       = Dispatcher::component( 'Extension_NewRelic_Service' );
		$application                   = Util_Request::get_array( 'application' );
		$application['alerts_enabled'] = empty( $application['alerts_enabled'] ) ? 'false' : 'true';
		$application['rum_enabled']    = empty( $application['rum_enabled'] ) ? 'false' : 'true';
		$result                        = $service->update_application_settings( $application );
		Util_Admin::redirect(
			array(
				'w3tc_note' => 'new_relic_save',
			),
			true
		);
	}
}
