<?php
/**
 * File: ConfigSettingsTabs.php
 *
 * @since 2.8.0
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Config_Tab_Settings
 *
 * W3 Total Cache Config settings class.
 *
 * This class is used to manage the configuration file that includes settings, options, and tabs for W3 Total Cache settings page.
 */
class Config_Tab_Settings {
	/**
	 * Retrieves all configuration settings.
	 *
	 * @since 2.8.0
	 *
	 * @return array An array containing all configuration settings.
	 */
	public static function get_configs(): array {
		$configs = include 'ConfigSettingsTabsKeys.php';
		return $configs;
	}

	/**
	 * Retrieves a specific configuration by key.
	 *
	 * @since 2.8.0
	 *
	 * @param string $key           The key of the configuration to retrieve.
	 * @param array  $default_value The default value to return if the key does not exist. Defaults to an empty array.
	 *
	 * @return array The configuration value associated with the provided key, or the default value if the key does not exist.
	 */
	public static function get_config( string $key, $default_value = array() ): array {
		$configs = self::get_configs();

		return isset( $configs[ $key ] ) ? $configs[ $key ] : $default_value;
	}
}
