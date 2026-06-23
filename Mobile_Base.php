<?php
/**
 * File: Mobile_Base.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Mobile_Base
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
abstract class Mobile_Base {
	/**
	 * Groups
	 *
	 * @var array
	 */
	private $_groups = array();

	/**
	 * Compare key
	 *
	 * @var string
	 */
	private $_compare_key = '';

	/**
	 * Config key
	 *
	 * @var string
	 */
	private $_config_key = '';

	/**
	 * Cache case
	 *
	 * @var string
	 */
	private $_cachecase = '';

	/**
	 * Initializes the Mobile_Base instance with the provided configuration and comparison keys.
	 *
	 * @param string $config_key  The configuration key for retrieving group settings.
	 * @param string $compare_key The comparison key for validating groups.
	 *
	 * @return void
	 */
	public function __construct( $config_key, $compare_key ) {
		$w3tc_config        = Dispatcher::config();
		$this->_groups      = $w3tc_config->get_array( $config_key );
		$this->_config_key  = $config_key;
		$this->_compare_key = $compare_key;
		$this->_cachecase   = substr( $config_key, 0, strpos( $config_key, '.' ) );
	}

	/**
	 * Abstract method to verify a group based on the provided comparison value.
	 *
	 * @param mixed $group_compare_value The value to compare against group settings.
	 *
	 * @return void
	 */
	abstract public function group_verifier( $group_compare_value );

	/**
	 * Retrieves the active group if one exists.
	 *
	 * @return string|false The name of the active group or false if no group is active.
	 */
	public function get_group() {
		static $w3tc_group = null;

		if ( null === $w3tc_group ) {
			if ( $this->do_get_group() ) {
				foreach ( $this->_groups as $config_group => $w3tc_config ) {
					if ( isset( $w3tc_config['enabled'] ) && $w3tc_config['enabled'] && isset( $w3tc_config[ $this->_compare_key ] ) ) {
						foreach ( (array) $w3tc_config[ $this->_compare_key ] as $group_compare_value ) {
							if ( $group_compare_value && $this->group_verifier( $group_compare_value ) ) {
								$w3tc_group = $config_group;
								return $w3tc_group;
							}
						}
					}
				}
			}

			$w3tc_group = false;
		}

		return $w3tc_group;
	}

	/**
	 * Retrieves the template part of the theme associated with the active group.
	 *
	 * @return string|false The template name or false if no theme is associated.
	 */
	public function get_template() {
		$theme = $this->get_theme();

		if ( $theme ) {
			list( $template, ) = explode( '/', $theme );

			return $template;
		}

		return false;
	}

	/**
	 * Retrieves the stylesheet part of the theme associated with the active group.
	 *
	 * @return string|false The stylesheet name or false if no theme is associated.
	 */
	public function get_stylesheet() {
		$theme = $this->get_theme();

		if ( $theme ) {
			$v = explode( '/', $theme );
			return isset( $v[1] ) ? $v[1] : '';
		}

		return false;
	}

	/**
	 * Retrieves the redirect URL for the active group.
	 *
	 * @return string|false The redirect URL or false if none is defined.
	 */
	public function get_redirect() {
		$w3tc_group = $this->get_group();

		if ( isset( $this->_groups[ $w3tc_group ]['redirect'] ) ) {
			return $this->_groups[ $w3tc_group ]['redirect'];
		}

		return false;
	}

	/**
	 * Retrieves the theme associated with the active group.
	 *
	 * @return string|false The theme key or false if none is associated.
	 */
	public function get_theme() {
		$w3tc_group = $this->get_group();

		if ( isset( $this->_groups[ $w3tc_group ]['theme'] ) ) {
			return $this->_groups[ $w3tc_group ]['theme'];
		}

		return false;
	}

	/**
	 * Retrieves all available themes as a key-value array.
	 *
	 * @return array Associative array of themes with keys as theme identifiers and values as names.
	 */
	public function get_themes() {
		$themes    = array();
		$wp_themes = Util_Theme::get_themes();

		foreach ( $wp_themes as $wp_theme ) {
			$w3tc_theme_key            = sprintf( '%s/%s', $wp_theme['Template'], $wp_theme['Stylesheet'] );
			$themes[ $w3tc_theme_key ] = $wp_theme['Name'];
		}

		return $themes;
	}

	/**
	 * Checks if any groups are enabled in the configuration.
	 *
	 * @return bool True if at least one group is enabled, otherwise false.
	 */
	public function has_enabled_groups() {
		foreach ( $this->_groups as $w3tc_group => $w3tc_config ) {
			if ( isset( $w3tc_config['enabled'] ) && $w3tc_config['enabled'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determines whether a group should be retrieved.
	 *
	 * @return bool Always returns true.
	 */
	public function do_get_group() {
		return true;
	}

	/**
	 * Saves or updates the configuration for a specific group.
	 *
	 * @param string $w3tc_group    The name of the group to save or update.
	 * @param string $theme    The theme associated with the group.
	 * @param string $redirect The redirect URL for the group.
	 * @param array  $values   The values used to compare for this group.
	 * @param bool   $w3tc_enabled  Whether the group is enabled.
	 *
	 * @return void
	 */
	public function save_group( $w3tc_group, $theme = 'default', $redirect = '', $values = array(), $w3tc_enabled = false ) {
		$w3tc_config                   = Dispatcher::config();
		$groups                        = $w3tc_config->get_array( $this->_config_key );
		$w3tc_group_config             = array();
		$w3tc_group_config['theme']    = $theme;
		$w3tc_group_config['enabled']  = $w3tc_enabled;
		$w3tc_group_config['redirect'] = $redirect;
		$values                        = array_unique( $values );
		$values                        = array_map( 'strtolower', $values );

		sort( $values );

		$w3tc_group_config[ $this->_compare_key ] = $values;
		$groups[ $w3tc_group ]                    = $w3tc_group_config;

		$enable = false;
		foreach ( $groups as $w3tc_group => $w3tc_group_config ) {
			if ( $w3tc_group_config['enabled'] ) {
				$enable = true;
				break;
			}
		}

		$w3tc_config->set( $this->_cachecase . '.enabled', $enable );
		$w3tc_config->set( $this->_config_key, $groups );
		$w3tc_config->save();
		$this->_groups = $groups;
	}

	/**
	 * Deletes a specific group from the configuration.
	 *
	 * @param string $w3tc_group The name of the group to delete.
	 *
	 * @return void
	 */
	public function delete_group( $w3tc_group ) {
		$w3tc_config = Dispatcher::config();
		$groups      = $w3tc_config->get_array( 'mobile.rgroups' );
		unset( $groups[ $w3tc_group ] );

		$enable = false;
		foreach ( $groups as $w3tc_group => $w3tc_group_config ) {
			if ( $w3tc_group_config['enabled'] ) {
				$enable = true;
				break;
			}
		}

		$w3tc_config->set( $this->_cachecase . '.enabled', $enable );
		$w3tc_config->set( $this->_config_key, $groups );
		$w3tc_config->save();
		$this->_groups = $groups;
	}

	/**
	 * Retrieves the configuration values for a specific group.
	 *
	 * @param string $w3tc_group The name of the group whose values are retrieved.
	 *
	 * @return array The configuration values of the specified group.
	 */
	public function get_group_values( $w3tc_group ) {
		$w3tc_config = Dispatcher::config();
		$groups      = $w3tc_config->get_array( $this->_config_key );

		return $groups[ $w3tc_group ];
	}

	/**
	 * Retrieves all groups currently configured.
	 *
	 * @return array Associative array of all groups and their configurations.
	 */
	public function get_groups() {
		return $this->_groups;
	}
}
