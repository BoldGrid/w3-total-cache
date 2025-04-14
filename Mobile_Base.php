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
		$config             = Dispatcher::config();
		$this->_groups      = $config->get_array( $config_key );
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
		static $group = null;

		if ( null === $group ) {
			if ( $this->do_get_group() ) {
				foreach ( $this->_groups as $config_group => $config ) {
					if ( isset( $config['enabled'] ) && $config['enabled'] && isset( $config[ $this->_compare_key ] ) ) {
						foreach ( (array) $config[ $this->_compare_key ] as $group_compare_value ) {
							if ( $group_compare_value && $this->group_verifier( $group_compare_value ) ) {
								$group = $config_group;
								return $group;
							}
						}
					}
				}
			}

			$group = false;
		}

		return $group;
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
		$group = $this->get_group();

		if ( isset( $this->_groups[ $group ]['redirect'] ) ) {
			return $this->_groups[ $group ]['redirect'];
		}

		return false;
	}

	/**
	 * Retrieves the theme associated with the active group.
	 *
	 * @return string|false The theme key or false if none is associated.
	 */
	public function get_theme() {
		$group = $this->get_group();

		if ( isset( $this->_groups[ $group ]['theme'] ) ) {
			return $this->_groups[ $group ]['theme'];
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
			$theme_key            = sprintf( '%s/%s', $wp_theme['Template'], $wp_theme['Stylesheet'] );
			$themes[ $theme_key ] = $wp_theme['Name'];
		}

		return $themes;
	}

	/**
	 * Checks if any groups are enabled in the configuration.
	 *
	 * @return bool True if at least one group is enabled, otherwise false.
	 */
	public function has_enabled_groups() {
		foreach ( $this->_groups as $group => $config ) {
			if ( isset( $config['enabled'] ) && $config['enabled'] ) {
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
	 * @param string $group    The name of the group to save or update.
	 * @param string $theme    The theme associated with the group.
	 * @param string $redirect The redirect URL for the group.
	 * @param array  $values   The values used to compare for this group.
	 * @param bool   $enabled  Whether the group is enabled.
	 *
	 * @return void
	 */
	public function save_group( $group, $theme = 'default', $redirect = '', $values = array(), $enabled = false ) {
		$config                   = Dispatcher::config();
		$groups                   = $config->get_array( $this->_config_key );
		$group_config             = array();
		$group_config['theme']    = $theme;
		$group_config['enabled']  = $enabled;
		$group_config['redirect'] = $redirect;
		$values                   = array_unique( $values );
		$values                   = array_map( 'strtolower', $values );

		sort( $values );

		$group_config[ $this->_compare_key ] = $values;
		$groups[ $group ]                    = $group_config;

		$enable = false;
		foreach ( $groups as $group => $group_config ) {
			if ( $group_config['enabled'] ) {
				$enable = true;
				break;
			}
		}

		$config->set( $this->_cachecase . '.enabled', $enable );
		$config->set( $this->_config_key, $groups );
		$config->save();
		$this->_groups = $groups;
	}

	/**
	 * Deletes a specific group from the configuration.
	 *
	 * @param string $group The name of the group to delete.
	 *
	 * @return void
	 */
	public function delete_group( $group ) {
		$config = Dispatcher::config();
		$groups = $config->get_array( 'mobile.rgroups' );
		unset( $groups[ $group ] );

		$enable = false;
		foreach ( $groups as $group => $group_config ) {
			if ( $group_config['enabled'] ) {
				$enable = true;
				break;
			}
		}

		$config->set( $this->_cachecase . '.enabled', $enable );
		$config->set( $this->_config_key, $groups );
		$config->save();
		$this->_groups = $groups;
	}

	/**
	 * Retrieves the configuration values for a specific group.
	 *
	 * @param string $group The name of the group whose values are retrieved.
	 *
	 * @return array The configuration values of the specified group.
	 */
	public function get_group_values( $group ) {
		$config = Dispatcher::config();
		$groups = $config->get_array( $this->_config_key );

		return $groups[ $group ];
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
