<?php
namespace W3TC;

abstract class Mobile_Base {
	/**
	 * Groups
	 *
	 * @var array
	 */
	private $_groups = array();
	private $_compare_key = '';
	private $_config_key = '';
	private $_cachecase = '';

	/**
	 * PHP5-style constructor
	 */
	function __construct( $config_key, $compare_key ) {
		$config = Dispatcher::config();
		$this->_groups = $config->get_array( $config_key );
		$this->_config_key = $config_key;
		$this->_compare_key = $compare_key;
		$this->_cachecase = substr( $config_key, 0, strpos( $config_key, '.' ) );
	}

	abstract function group_verifier( $group_compare_value );

	public function get_group() {
		static $group = null;

		if ( $group === null ) {
			if ( $this->do_get_group() ) {
				foreach ( $this->_groups as $config_group => $config ) {
					if ( isset( $config['enabled'] ) && $config['enabled'] && isset( $config[$this->_compare_key] ) ) {
						foreach ( (array) $config[$this->_compare_key] as $group_compare_value ) {
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
	 * Returns temaplte
	 *
	 * @return string
	 */
	function get_template() {
		$theme = $this->get_theme();

		if ( $theme ) {
			list( $template, ) = explode( '/', $theme );

			return $template;
		}

		return false;
	}

	/**
	 * Returns stylesheet
	 *
	 * @return string
	 */
	function get_stylesheet() {
		$theme = $this->get_theme();

		if ( $theme ) {
			$v = explode( '/', $theme );
			return isset( $v[1] ) ? $v[1] : '';
		}

		return false;
	}

	/**
	 * Returns redirect
	 *
	 * @return string
	 */
	function get_redirect() {
		$group = $this->get_group();

		if ( isset( $this->_groups[$group]['redirect'] ) ) {
			return $this->_groups[$group]['redirect'];
		}

		return false;
	}

	/**
	 * Returns theme
	 *
	 * @return string
	 */
	function get_theme() {
		$group = $this->get_group();

		if ( isset( $this->_groups[$group]['theme'] ) ) {
			return $this->_groups[$group]['theme'];
		}

		return false;
	}

	/**
	 * Return array of themes
	 *
	 * @return array
	 */
	function get_themes() {
		$themes = array();
		$wp_themes = Util_Theme::get_themes();

		foreach ( $wp_themes as $wp_theme ) {
			$theme_key = sprintf( '%s/%s', $wp_theme['Template'], $wp_theme['Stylesheet'] );
			$themes[$theme_key] = $wp_theme['Name'];
		}

		return $themes;
	}


	/**
	 * Checks if there are enabled referrer groups
	 *
	 * @return bool
	 */
	function has_enabled_groups() {
		foreach ( $this->_groups as $group => $config )
			if ( isset( $config['enabled'] ) && $config['enabled'] )
				return true;
			return false;
	}

	function do_get_group() {
		return true;
	}

	/**
	 * Use Util_Theme::get_themes() to get a list themenames to use with user agent groups
	 *
	 * @param unknown $group
	 * @param string  $theme    the themename default is default theme. For childtheme it should be parentthemename/childthemename
	 * @param string  $redirect
	 * @param array   $values   Remember to escape special characters like spaces, dots or dashes with a backslash. Regular expressions are also supported.
	 * @param bool    $enabled
	 */
	function save_group( $group, $theme = 'default', $redirect = '', $values = array(), $enabled = false ) {
		$config = Dispatcher::config();
		$groups = $config->get_array( $this->_config_key );
		$group_config = array();
		$group_config['theme'] = $theme;
		$group_config['enabled'] = $enabled;
		$group_config['redirect'] = $redirect;
		$values = array_unique( $values );
		$values = array_map( 'strtolower', $values );
		sort( $values );
		$group_config[$this->_compare_key] = $values;
		$groups[$group] = $group_config;

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


	function delete_group( $group ) {
		$config = Dispatcher::config();
		$groups = $config->get_array( 'mobile.rgroups' );
		unset( $groups[$group] );

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

	function get_group_values( $group ) {
		$config = Dispatcher::config();
		$groups = $config->get_array( $this->_config_key );
		return $groups[$group];
	}

	function get_groups() {
		return $this->_groups;
	}
}
