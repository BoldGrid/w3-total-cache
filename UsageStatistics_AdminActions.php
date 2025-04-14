<?php
/**
 * File: UsageStatistics_AdminActions.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UsageStatistics_AdminActions
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class UsageStatistics_AdminActions {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config = null;

	/**
	 * Constructor for the UsageStatistics_AdminActions class.
	 *
	 * Initializes the configuration property by retrieving settings from the Dispatcher.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Disables usage statistics tracking.
	 *
	 * This method updates the configuration to disable usage statistics tracking
	 * by setting the 'stats.enabled' key to `false` and saving the configuration.
	 * It then redirects the user back to the appropriate location using `Util_Admin::redirect`.
	 *
	 * @return void
	 *
	 * @throws Exception If there is an error saving the configuration or during the redirect process.
	 */
	public function w3tc_ustats_note_disable() {
		$this->_config->set( 'stats.enabled', false );
		$this->_config->save();

		Util_Admin::redirect( array(), true );
	}
}
