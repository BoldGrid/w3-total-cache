<?php
/**
 * File: ConfigStateNote.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class ConfigStateNote
 *
 * Used to show notes at blog-level when master configs are changed
 *
 * keys - see ConfigState comment with a list of keys with "timestamp" word
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class ConfigStateNote {
	/**
	 * Config state master
	 *
	 * @var array
	 */
	private $_config_state_master;

	/**
	 * Config state
	 *
	 * @var array
	 */
	private $_config_state;

	/**
	 * Initializes the ConfigStateNote instance with master and state configurations.
	 *
	 * @param object $config_state_master The master configuration state object.
	 * @param object $w3tc_config_state        The configuration state object.
	 *
	 * @return void
	 */
	public function __construct( $config_state_master, $w3tc_config_state ) {
		$this->_config_state_master = $config_state_master;
		$this->_config_state        = $w3tc_config_state;
	}

	/**
	 * Retrieves a configuration value for a given key, considering timestamps.
	 *
	 * @param string $w3tc_key The key for which to retrieve the configuration value.
	 *
	 * @return bool The boolean value of the configuration for the given key.
	 */
	public function get( $w3tc_key ) {
		$timestamp        = $this->_config_state->get_integer( $w3tc_key . '.timestamp' );
		$timestamp_master = $this->_config_state_master->get_integer( $w3tc_key . '.timestamp' );

		if ( $timestamp > $timestamp_master ) {
			return $this->_config_state->get_boolean( $w3tc_key );
		} else {
			return $this->_config_state_master->get_boolean( $w3tc_key );
		}
	}

	/**
	 * Sets a configuration value and updates its timestamp.
	 *
	 * @param string $w3tc_key   The key for which to set the configuration value.
	 * @param mixed  $w3tc_value The value to set for the given key.
	 *
	 * @return void
	 */
	public function set( $w3tc_key, $w3tc_value ) {
		$this->_config_state->set( $w3tc_key, $w3tc_value );
		$this->_config_state->set( $w3tc_key . '.timestamp', time() );
		$this->_config_state->save();
	}
}
