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
	 * @param object $config_state        The configuration state object.
	 *
	 * @return void
	 */
	public function __construct( $config_state_master, $config_state ) {
		$this->_config_state_master = $config_state_master;
		$this->_config_state        = $config_state;
	}

	/**
	 * Retrieves a configuration value for a given key, considering timestamps.
	 *
	 * @param string $key The key for which to retrieve the configuration value.
	 *
	 * @return bool The boolean value of the configuration for the given key.
	 */
	public function get( $key ) {
		$timestamp        = $this->_config_state->get_integer( $key . '.timestamp' );
		$timestamp_master = $this->_config_state_master->get_integer( $key . '.timestamp' );

		if ( $timestamp > $timestamp_master ) {
			return $this->_config_state->get_boolean( $key );
		} else {
			return $this->_config_state_master->get_boolean( $key );
		}
	}

	/**
	 * Sets a configuration value and updates its timestamp.
	 *
	 * @param string $key   The key for which to set the configuration value.
	 * @param mixed  $value The value to set for the given key.
	 *
	 * @return void
	 */
	public function set( $key, $value ) {
		$this->_config_state->set( $key, $value );
		$this->_config_state->set( $key . '.timestamp', time() );
		$this->_config_state->save();
	}
}
