<?php
/**
 * File: ModuleStatus.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class ModuleStatus
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class ModuleStatus {
	/**
	 * Opcode Engines
	 *
	 * @var array
	 */
	private $_opcode_engines = array(
		'apc',
		'eaccelerator',
		'xcache',
		'wincache',
	);

	/**
	 * File engines
	 *
	 * @var array
	 */
	private $_file_engines = array(
		'file',
		'file_generic',
	);

	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config;

	/**
	 * Constructor for initializing the ModuleStatus class.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Checks whether any caching or optimization plugin is enabled.
	 *
	 * @return bool True if any plugin is enabled, false otherwise.
	 */
	public function plugin_is_enabled() {
		return $this->is_enabled( 'pgcache' ) ||
			$this->is_enabled( 'minify' ) ||
			$this->is_enabled( 'dbcache' ) ||
			$this->is_enabled( 'objectcache' ) ||
			$this->is_enabled( 'browsercache' ) ||
			$this->is_enabled( 'cdn' ) ||
			$this->is_enabled( 'cdnfsd' ) ||
			$this->is_enabled( 'varnish' ) ||
			$this->is_enabled( 'newrelic' ) ||
			$this->is_enabled( 'fragmentcache' );
	}

	/**
	 * Checks if a specific module is enabled.
	 *
	 * @param string $module The name of the module to check.
	 *
	 * @return bool True if the module is enabled, false otherwise.
	 */
	public function is_enabled( $module ) {
		return $this->_config->get_boolean( "$module.enabled" );
	}

	/**
	 * Checks if a specific module is running.
	 *
	 * phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores
	 *
	 * @param string $module The name of the module to check.
	 *
	 * @return bool True if the module is running, false otherwise.
	 */
	public function is_running( $module ) {
		return apply_filters( "w3tc_module_is_running-{$module}", $this->is_enabled( $module ) );
	}

	/**
	 * Determines whether Memcached can be emptied.
	 *
	 * @return bool True if Memcached can be emptied, false otherwise.
	 */
	public function can_empty_memcache() {
		return $this->_enabled_module_uses_engine( 'pgcache', 'memcached' ) ||
			$this->_enabled_module_uses_engine( 'dbcache', 'memcached' ) ||
			$this->_enabled_module_uses_engine( 'objectcache', 'memcached' ) ||
			$this->_enabled_module_uses_engine( 'minify', 'memcached' ) ||
			$this->_enabled_module_uses_engine( 'fragmentcache', 'memcached' );
	}

	/**
	 * Determines whether the Opcache can be emptied.
	 *
	 * @return bool True if Opcache can be emptied, false otherwise.
	 */
	public function can_empty_opcode() {
		$o = Dispatcher::component( 'SystemOpCache_Core' );
		return $o->is_enabled();
	}

	/**
	 * Determines whether file-based caches can be emptied.
	 *
	 * @return bool True if file-based caches can be emptied, false otherwise.
	 */
	public function can_empty_file() {
		return $this->_enabled_module_uses_engine( 'pgcache', $this->_file_engines ) ||
			$this->_enabled_module_uses_engine( 'dbcache', $this->_file_engines ) ||
			$this->_enabled_module_uses_engine( 'objectcache', $this->_file_engines ) ||
			$this->_enabled_module_uses_engine( 'minify', $this->_file_engines ) ||
			$this->_enabled_module_uses_engine( 'fragmentcache', $this->_file_engines );
	}

	/**
	 * Determines whether Varnish cache can be emptied.
	 *
	 * @return bool True if Varnish cache can be emptied, false otherwise.
	 */
	public function can_empty_varnish() {
		return $this->_config->get_boolean( 'varnish.enabled' );
	}

	/**
	 * Retrieves the engine used by a specific module.
	 *
	 * @param string $module The name of the module.
	 *
	 * @return string The engine associated with the module.
	 */
	public function get_module_engine( $module ) {
		return $this->_config->get_string( "$module.engine" );
	}

	/**
	 * Checks if an enabled module uses a specific caching engine.
	 *
	 * @param string $module The name of the module.
	 * @param mixed  $engine The engine or an array of engines to check.
	 *
	 * @return bool True if the module uses the specified engine, false otherwise.
	 */
	private function _enabled_module_uses_engine( $module, $engine ) {
		if ( is_array( $engine ) ) {
			return $this->is_enabled( $module ) && in_array( $this->get_module_engine( $module ), $engine, true );
		} else {
			return $this->is_enabled( $module ) && $this->get_module_engine( $module ) === $engine;
		}
	}
}
