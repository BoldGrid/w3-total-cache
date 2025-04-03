<?php
/**
 * File: Cli.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Config
 *
 * Provides configuration data using cache
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
 */
class Config {
	/**
	 * Blog ID of loaded config
	 *
	 * @var integer
	 */
	private $_blog_id;

	/**
	 * Is master flag
	 *
	 * @var bool
	 */
	private $_is_master;

	/**
	 * Is this preview config
	 *
	 * @var boolean
	 */
	private $_preview;

	/**
	 * First 20 digits of data MD5
	 *
	 * @var integer
	 */
	private $_md5;

	/**
	 * Data
	 *
	 * @var array
	 */
	private $_data;

	/**
	 * Compiled flag
	 *
	 * @var bool
	 */
	private $_compiled;

	/**
	 * Retrieves a configuration array from cache storage if enabled and present, otherwise retrieves from
	 * database/file via _util_array_from_storage private method
	 *
	 * @param int  $blog_id  The ID of the blog.
	 * @param bool $preview  Whether to load the preview configuration.
	 *
	 * @return array|null The configuration array or null if not found.
	 */
	public static function util_array_from_storage( $blog_id, $preview ) {
		if ( ! defined( 'W3TC_CONFIG_CACHE_ENGINE' ) ) {
			return self::_util_array_from_storage( $blog_id, $preview );
		}

		// config cache enabled.
		$config = ConfigCache::util_array_from_storage( $blog_id, $preview );
		if ( ! is_null( $config ) ) {
			return $config;
		}

		$config = self::_util_array_from_storage( $blog_id, $preview );
		ConfigCache::save_item( $blog_id, $preview, $config );

		return $config;
	}

	/**
	 * Retrieves a configuration array from database/file storage.
	 *
	 * @param int  $blog_id  The ID of the blog.
	 * @param bool $preview  Whether to load the preview configuration.
	 *
	 * @return array|null The configuration array or null if not found.
	 */
	private static function _util_array_from_storage( $blog_id, $preview ) {
		if ( defined( 'W3TC_CONFIG_DATABASE' ) && W3TC_CONFIG_DATABASE ) {
			return ConfigDbStorage::util_array_from_storage( $blog_id, $preview );
		}

		$filename = self::util_config_filename( $blog_id, $preview );
		if ( file_exists( $filename ) && is_readable( $filename ) ) {
			// including file directly instead of read+eval causes constant problems with APC, ZendCache, and
			// WSOD in a case of broken config file.
			$content = @file_get_contents( $filename );
			$config  = @json_decode( substr( $content, 14 ), true );

			if ( is_array( $config ) ) {
				return $config;
			}
		}

		return null;
	}

	/**
	 * Retrieves the filename for the configuration.
	 *
	 * @param int  $blog_id  The ID of the blog.
	 * @param bool $preview  Whether to load the preview configuration.
	 *
	 * @return string The configuration file path.
	 */
	public static function util_config_filename( $blog_id, $preview ) {
		$postfix = ( $preview ? '-preview' : '' ) . '.php';

		if ( $blog_id <= 0 ) {
			$filename = W3TC_CONFIG_DIR . '/master' . $postfix;
		} else {
			$filename = W3TC_CONFIG_DIR . '/' . sprintf( '%06d', $blog_id ) . $postfix;
		}

		$d = w3tc_apply_filters(
			'config_filename',
			array(
				'blog_id'  => $blog_id,
				'preview'  => $preview,
				'filename' => $filename,
			)
		);

		return $d['filename'];
	}

	/**
	 * Retrieves the legacy configuration filename.
	 *
	 * @param int  $blog_id  The ID of the blog.
	 * @param bool $preview  Whether to load the preview configuration.
	 *
	 * @return string The legacy configuration file path.
	 */
	public static function util_config_filename_legacy_v2( $blog_id, $preview ) {
		$postfix = ( $preview ? '-preview' : '' ) . '.json';

		if ( $blog_id <= 0 ) {
			return W3TC_CONFIG_DIR . '/master' . $postfix;
		} else {
			return W3TC_CONFIG_DIR . '/' . sprintf( '%06d', $blog_id ) . $postfix;
		}
	}

	/**
	 * Constructor to initialize configuration for a given blog.
	 *
	 * @param int|null $blog_id The ID of the blog, or null to determine based on environment.
	 */
	public function __construct( $blog_id = null ) {
		if ( ! is_null( $blog_id ) ) {
			$this->_blog_id   = $blog_id;
			$this->_is_master = ( 0 === $this->_blog_id );
		} else {
			if ( Util_Environment::is_using_master_config() ) {
				$this->_blog_id = 0;
			} else {
				$this->_blog_id = Util_Environment::blog_id();
			}

			$this->_is_master = ( 0 === Util_Environment::blog_id() );
		}

		$this->_preview = Util_Environment::is_preview_mode();
		$this->load();
	}

	/**
	 * Retrieves a configuration value for a given key or returns cached/uncached default value if not found.
	 *
	 * @param string $key           The key to look up in the configuration.
	 * @param mixed  $default_value The default value to return if the key is not found.
	 *
	 * @return mixed The configuration value, or the default if not found.
	 */
	public function get( $key, $default_value = null ) {
		$v = $this->_get( $this->_data, $key );
		if ( ! is_null( $v ) ) {
			return $v;
		}

		// take default value.
		if ( ! empty( $default_value ) || ! function_exists( 'apply_filters' ) ) {
			return $default_value;
		}

		// try cached default values.
		static $default_values = null;
		if ( is_null( $default_values ) ) {
			$default_values = apply_filters( 'w3tc_config_default_values', array() );
		}

		$v = $this->_get( $default_values, $key );
		if ( ! is_null( $v ) ) {
			return $v;
		}

		// update default values.
		$default_values = apply_filters( 'w3tc_config_default_values', array() );

		$v = $this->_get( $default_values, $key );
		if ( ! is_null( $v ) ) {
			return $v;
		}

		return $default_value;
	}

	/**
	 * Retrieves a configuration value for a given key.
	 *
	 * @param array  $a   The array to search in.
	 * @param string $key The key to look up in the array.
	 *
	 * @return mixed The configuration value, or null if not found.
	 */
	private function _get( &$a, $key ) {
		if ( is_array( $key ) ) {
			$key0 = $key[0];
			if ( isset( $a[ $key0 ] ) ) {
				$key1 = $key[1];
				if ( isset( $a[ $key0 ][ $key1 ] ) ) {
					return $a[ $key0 ][ $key1 ];
				}
			}
		} elseif ( isset( $a[ $key ] ) ) {
			return $a[ $key ];
		}

		return null;
	}

	/**
	 * Retrieves a string configuration value for a given key.
	 *
	 * @param string $key           The key to look up in the configuration.
	 * @param string $default_value The default string value to return if the key is not found.
	 * @param bool   $trim          Whether to trim the value.
	 *
	 * @return string The configuration value as a string.
	 */
	public function get_string( $key, $default_value = '', $trim = true ) {
		$value = (string) $this->get( $key, $default_value );

		return $trim ? trim( $value ) : $value;
	}

	/**
	 * Retrieves an integer configuration value for a given key.
	 *
	 * @param string $key           The key to look up in the configuration.
	 * @param int    $default_value The default integer value to return if the key is not found.
	 *
	 * @return int The configuration value as an integer.
	 */
	public function get_integer( $key, $default_value = 0 ) {
		return (int) $this->get( $key, $default_value );
	}

	/**
	 * Retrieves a boolean configuration value for a given key.
	 *
	 * @param string $key           The key to look up in the configuration.
	 * @param bool   $default_value The default boolean value to return if the key is not found.
	 *
	 * @return bool The configuration value as a boolean.
	 */
	public function get_boolean( $key, $default_value = false ) {
		return (bool) $this->get( $key, $default_value );
	}

	/**
	 * Retrieves an array configuration value for a given key.
	 *
	 * @param string $key           The key to look up in the configuration.
	 * @param array  $default_value The default array value to return if the key is not found.
	 *
	 * @return array The configuration value as an array.
	 */
	public function get_array( $key, $default_value = array() ) {
		return (array) $this->get( $key, $default_value );
	}

	/**
	 * Retrieves a filtered configuration value for a given key.
	 *
	 * @param string $key           The key to look up in the configuration.
	 * @param mixed  $default_value The default value to return if the key is not found.
	 *
	 * @return mixed The configuration value, potentially filtered.
	 */
	public function getf( $key, $default_value = null ) {
		$v = $this->get( $key, $default_value );
		return apply_filters( 'w3tc_config_item_' . $key, $v );
	}

	/**
	 * Retrieves a filtered string configuration value for a given key.
	 *
	 * @param string $key           The key to look up in the configuration.
	 * @param string $default_value The default string value to return if the key is not found.
	 * @param bool   $trim          Whether to trim the value.
	 *
	 * @return string The filtered configuration value as a string.
	 */
	public function getf_string( $key, $default_value = '', $trim = true ) {
		$value = (string) $this->getf( $key, $default_value );

		return $trim ? trim( $value ) : $value;
	}

	/**
	 * Retrieves a filtered integer configuration value for a given key.
	 *
	 * @param string $key           The key to look up in the configuration.
	 * @param int    $default_value The default integer value to return if the key is not found.
	 *
	 * @return int The filtered configuration value as an integer.
	 */
	public function getf_integer( $key, $default_value = 0 ) {
		return (int) $this->getf( $key, $default_value );
	}

	/**
	 * Retrieves a filtered boolean configuration value for a given key.
	 *
	 * @param string $key           The key to look up in the configuration.
	 * @param bool   $default_value The default boolean value to return if the key is not found.
	 *
	 * @return bool The filtered configuration value as a boolean.
	 */
	public function getf_boolean( $key, $default_value = false ) {
		return (bool) $this->getf( $key, $default_value );
	}

	/**
	 * Retrieves a filtered array configuration value for a given key.
	 *
	 * @param string $key           The key to look up in the configuration.
	 * @param array  $default_value The default array value to return if the key is not found.
	 *
	 * @return array The filtered configuration value as an array.
	 */
	public function getf_array( $key, $default_value = array() ) {
		return (array) $this->getf( $key, $default_value );
	}

	/**
	 * Checks if a specific extension is active in the configuration.
	 *
	 * @param string $extension The extension to check.
	 *
	 * @return bool True if the extension is active, false otherwise.
	 */
	public function is_extension_active( $extension ) {
		$extensions = $this->get_array( 'extensions.active' );
		return isset( $extensions[ $extension ] );
	}

	/**
	 * Checks if a specific extension is active on the frontend.
	 *
	 * @param string $extension The extension to check.
	 *
	 * @return bool True if the extension is active on the frontend, false otherwise.
	 */
	public function is_extension_active_frontend( $extension ) {
		$extensions = $this->get_array( 'extensions.active_frontend' );
		return isset( $extensions[ $extension ] );
	}

	/**
	 * Sets the active frontend extension.
	 *
	 * @param string $extension         The extension key to be set as active.
	 * @param bool   $is_active_frontend Whether the extension should be active on the frontend.
	 *
	 * @return void
	 */
	public function set_extension_active_frontend( $extension, $is_active_frontend ) {
		$a = $this->get_array( 'extensions.active_frontend' );
		if ( ! $is_active_frontend ) {
			unset( $a[ $extension ] );
		} else {
			$a[ $extension ] = '*';
		}

		$this->set( 'extensions.active_frontend', $a );
	}

	/**
	 * Sets the active dropin extension.
	 *
	 * @param string $extension        The extension key to be set as active dropin.
	 * @param bool   $is_active_dropin Whether the extension should be active as a dropin.
	 *
	 * @return void
	 */
	public function set_extension_active_dropin( $extension, $is_active_dropin ) {
		$a = $this->get_array( 'extensions.active_dropin' );
		if ( ! $is_active_dropin ) {
			unset( $a[ $extension ] );
		} else {
			$a[ $extension ] = '*';
		}

		$this->set( 'extensions.active_dropin', $a );
	}

	/**
	 * Sets a key-value pair in the configuration data.
	 *
	 * @param string|array $key   The key or array of keys to set.
	 * @param mixed        $value The value to set.
	 *
	 * @return mixed The value that was set.
	 */
	public function set( $key, $value ) {
		if ( ! is_array( $key ) ) {
			$this->_data[ $key ] = $value;
		} else {
			// set extension's key.
			$key0 = $key[0];
			$key1 = $key[1];

			if ( ! isset( $this->_data[ $key0 ] ) || ! is_array( $this->_data[ $key0 ] ) ) {
				$this->_data[ $key0 ] = array();
			}

			$this->_data[ $key0 ][ $key1 ] = $value;
		}

		return $value;
	}

	/**
	 * Checks if the current configuration is a preview.
	 *
	 * @return bool True if preview mode is enabled, false otherwise.
	 */
	public function is_preview() {
		return $this->_preview;
	}

	/**
	 * Checks if the current configuration is the master configuration.
	 *
	 * @return bool True if the configuration is the master, false otherwise.
	 */
	public function is_master() {
		return $this->_is_master;
	}

	/**
	 * Checks if the configuration is compiled.
	 *
	 * @return bool True if the configuration is compiled, false otherwise.
	 */
	public function is_compiled() {
		return $this->_compiled;
	}

	/**
	 * Sets the default configuration values.
	 *
	 * @return void
	 */
	public function set_defaults() {
		$c           = new ConfigCompiler( $this->_blog_id, $this->_preview );
		$this->_data = $c->get_data();
	}

	/**
	 * Saves the current configuration.
	 *
	 * @return void
	 */
	public function save() {
		if ( function_exists( 'do_action' ) ) {
			do_action( 'w3tc_config_save', $this );
		}

		$c = new ConfigCompiler( $this->_blog_id, $this->_preview );
		$c->apply_data( $this->_data );
		$c->save();
	}

	/**
	 * Checks if a configuration key is sealed (immutable).
	 *
	 * @param string $key The configuration key to check.
	 *
	 * @return bool True if the key is sealed, false otherwise.
	 */
	public function is_sealed( $key ) {
		if ( $this->is_master() ) {
			return false;
		}

		// better to use master config data here, but its faster and preciese enough for UI.
		return ConfigCompiler::child_key_sealed( $key, $this->_data, $this->_data );
	}

	/**
	 * Exports the current configuration as a JSON string.
	 *
	 * @return string The configuration data as a JSON string.
	 */
	public function export() {
		if ( defined( 'JSON_PRETTY_PRINT' ) ) {
			$content = wp_json_encode( $this->_data, JSON_PRETTY_PRINT );
		} else {
			$content = wp_json_encode( $this->_data );
		}

		return $content;
	}

	/**
	 * Imports a configuration from a file.
	 *
	 * @global $wp_filesystem
	 * @see get_filesystem_method()
	 *
	 * @param string $filename The path to the file to import.
	 *
	 * @return bool True if the import was successful, false otherwise.
	 */
	public function import( string $filename ): bool {
		if ( 'direct' !== \get_filesystem_method() ) {
			return false;
		}

		// Initialize WP_Filesystem.
		global $wp_filesystem;
		WP_Filesystem();

		if ( $wp_filesystem->exists( $filename ) && $wp_filesystem->is_readable( $filename ) ) {
			$content = $wp_filesystem->get_contents( $filename );
			if ( \substr( $content, 0, 14 ) === '<?php exit; ?>' ) {
				$content = \substr( $content, 14 );
			}

			$data = @json_decode( $content, true );

			if ( \is_array( $data ) ) {
				if ( ! isset( $data['version'] ) || W3TC_VERSION !== $data['version'] ) {
					$c = new ConfigCompiler( $this->_blog_id, false );
					$c->load( $data );
					$data = $c->get_data();
				}

				foreach ( $data as $key => $value ) {
					$this->set( $key, $value );
				}

				return true;
			}
		}

		return false;
	}

	/**
	 * Gets the MD5 hash of the current configuration data.
	 *
	 * @return string The MD5 hash of the configuration data.
	 */
	public function get_md5() {
		if ( is_null( $this->_md5 ) ) {
			$this->_md5 = substr( md5( serialize( $this->_data ) ), 20 );
		}

		return $this->_md5;
	}

	/**
	 * Loads the configuration data.
	 *
	 * @return void
	 */
	public function load() {
		$data = self::util_array_from_storage( 0, $this->_preview );

		// config file assumed is not up to date, use slow version.
		if ( ! isset( $data['version'] ) || W3TC_VERSION !== $data['version'] ) {
			$this->load_full();
			return;
		}

		if ( ! $this->is_master() ) {
			$child_data = self::util_array_from_storage( $this->_blog_id, $this->_preview );

			if ( ! is_null( $child_data ) ) {
				if ( ! isset( $data['version'] ) || W3TC_VERSION !== $data['version'] ) {
					$this->load_full();
					return;
				}

				foreach ( $child_data as $key => $value ) {
					$data[ $key ] = $value;
				}
			}
		}

		$this->_data     = $data;
		$this->_compiled = false;
	}

	/**
	 * Loads the full configuration data when necessary.
	 *
	 * @return void
	 */
	private function load_full() {
		$c = new ConfigCompiler( $this->_blog_id, $this->_preview );
		$c->load();
		$this->_data     = $c->get_data();
		$this->_compiled = true;
	}
}
