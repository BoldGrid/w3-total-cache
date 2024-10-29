<?php
/**
 * File: ConfigSettingsTabs.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * W3 Total Cache Config settings class.
 *
 * This class is used to manage the configuration file that includes settings, options, and tabs for W3 Total Cache settings page.
 */
class Config_Tab_Settings {
	/**
	 * Get the configuration settings.
	 *
	 * @return array The configuration settings.
	 */
	public static function get_configs() {
		$configs = include 'ConfigSettingsTabsKeys.php';
		return $configs;
	}

	/**
	 * Get the configuration setting.
	 *
	 * @param string $key The key of the configuration setting.
	 * @param array  $default The default value of the configuration setting.
	 *
	 * @return array The configuration setting.
	 */
	public static function get_config( $key, $default = array() ) {
		$configs = self::get_configs();

		return isset( $configs[ $key ] ) ? $configs[ $key ] : $default;
	}
}
