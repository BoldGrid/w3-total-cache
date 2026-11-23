<?php
/**
 * File: ObjectCache_WpObjectCache.php
 *
 * @package W3TC
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */

namespace W3TC;

/**
 * W3 Object Cache object
 */
class ObjectCache_WpObjectCache {
	/**
	 * Config
	 *
	 * @var object|null
	 */
	private $_config = null;

	/**
	 * Default cache
	 *
	 * @var object
	 */
	private $_default_cache;

	/**
	 * Caches
	 *
	 * @var array
	 */
	private $_caches = array();

	/**
	 * Cache by group
	 *
	 * @var array
	 */
	private $_cache_by_group = array();

	/**
	 * Supported features
	 *
	 * @var array
	 */
	private $supported_features = array(
		'flush_runtime',
		'flush_group',
		'add_multiple',
		'set_multiple',
		'get_multiple',
		'delete_multiple',
		'incr',
		'decr',
		'groups',
		'global_groups',
		'non_persistent',
		'persistent',
	);

	/**
	 * Constructor for the ObjectCache_WpObjectCache class.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config        = Dispatcher::config();
		$this->_default_cache = Dispatcher::component( 'ObjectCache_WpObjectCache_Regular' );
		$this->_caches[]      = $this->_default_cache;
	}

	/**
	 * Registers a new cache engine to be used for object groups.
	 *
	 * @param object $cache                 The cache engine to register.
	 * @param array  $use_for_object_groups Array of object groups this cache should be used for.
	 *
	 * @return void
	 */
	public function register_cache( $cache, $use_for_object_groups ) {
		$this->_caches[] = $cache;

		foreach ( $use_for_object_groups as $w3tc_group ) {
			$this->_cache_by_group[ $w3tc_group ] = $cache;
		}
	}

	/**
	 * Retrieves a cached value by its ID from the specified group.
	 *
	 * @param string $id    The cache key.
	 * @param string $w3tc_group The cache group.
	 * @param bool   $force Whether to force a cache retrieval, bypassing the cache expiration.
	 * @param mixed  $found A reference to the variable that will store whether the value was found.
	 *
	 * @return mixed The cached value if found, otherwise false.
	 */
	public function get( $id, $w3tc_group = 'default', $force = false, &$found = null ) {
		$cache = $this->_get_engine( $w3tc_group );
		return $cache->get( $id, $w3tc_group, $force, $found );
	}

	/**
	 * Retrieves multiple cached values by their IDs from the specified group.
	 *
	 * @since 2.4.0
	 *
	 * @param array  $ids    The cache keys.
	 * @param string $w3tc_group  The cache group.
	 * @param bool   $force  Whether to force a cache retrieval, bypassing the cache expiration.
	 *
	 * @return array An array of cached values, indexed by their respective IDs.
	 */
	public function get_multiple( $ids, $w3tc_group = 'default', $force = false ) {
		$cache = $this->_get_engine( $w3tc_group );
		return $cache->get_multiple( $ids, $w3tc_group, $force );
	}

	/**
	 * Sets a cache value for a given ID and group.
	 *
	 * @param string $id     The cache key.
	 * @param mixed  $w3tc_data   The data to store in the cache.
	 * @param string $w3tc_group  The cache group.
	 * @param int    $expire The cache expiration time in seconds.
	 *
	 * @return bool True if the data was successfully cached, otherwise false.
	 */
	public function set( $id, $w3tc_data, $w3tc_group = 'default', $expire = 0 ) {
		$cache = $this->_get_engine( $w3tc_group );
		return $cache->set( $id, $w3tc_data, $w3tc_group, $expire );
	}

	/**
	 * Sets multiple cache values for their respective IDs and groups.
	 *
	 * @since 2.4.0
	 *
	 * @param array  $w3tc_data   An array of data indexed by cache key.
	 * @param string $w3tc_group  The cache group.
	 * @param int    $expire The cache expiration time in seconds.
	 *
	 * @return bool True if the data was successfully cached, otherwise false.
	 */
	public function set_multiple( $w3tc_data, $w3tc_group = 'default', $expire = 0 ) {
		$cache = $this->_get_engine( $w3tc_group );
		return $cache->set_multiple( $w3tc_data, $w3tc_group, $expire );
	}

	/**
	 * Deletes a cached value by its ID from the specified group.
	 *
	 * @param string $id    The cache key.
	 * @param string $w3tc_group The cache group.
	 * @param bool   $force Whether to forcefully delete the cache.
	 *
	 * @return bool True if the cache was successfully deleted, otherwise false.
	 */
	public function delete( $id, $w3tc_group = 'default', $force = false ) {
		$cache = $this->_get_engine( $w3tc_group );
		return $cache->delete( $id, $w3tc_group, $force );
	}

	/**
	 * Deletes multiple cached values by their IDs from the specified group.
	 *
	 * @since 2.4.0
	 *
	 * @param array  $w3tc_keys  The cache keys.
	 * @param string $w3tc_group The cache group.
	 *
	 * @return bool True if the caches were successfully deleted, otherwise false.
	 */
	public function delete_multiple( $w3tc_keys, $w3tc_group = 'default' ) {
		$cache = $this->_get_engine( $w3tc_group );
		return $cache->delete_multiple( $w3tc_keys, $w3tc_group );
	}


	/**
	 * Adds a new value to the cache if it does not already exist for the given ID and group.
	 *
	 * @param string $id     The cache key.
	 * @param mixed  $w3tc_data   The data to store in the cache.
	 * @param string $w3tc_group  The cache group.
	 * @param int    $expire The cache expiration time in seconds.
	 *
	 * @return bool True if the data was added, otherwise false.
	 */
	public function add( $id, $w3tc_data, $w3tc_group = 'default', $expire = 0 ) {
		$cache = $this->_get_engine( $w3tc_group );
		return $cache->add( $id, $w3tc_data, $w3tc_group, $expire );
	}

	/**
	 * Adds multiple new values to the cache, ensuring they do not overwrite existing data.
	 *
	 * @since 2.4.0
	 *
	 * @param array  $w3tc_data   An array of data indexed by cache key.
	 * @param string $w3tc_group  The cache group.
	 * @param int    $expire The cache expiration time in seconds.
	 *
	 * @return bool True if the data was successfully added, otherwise false.
	 */
	public function add_multiple( array $w3tc_data, $w3tc_group = '', $expire = 0 ) {
		$cache = $this->_get_engine( $w3tc_group );
		return $cache->add_multiple( $w3tc_data, $w3tc_group, $expire );
	}

	/**
	 * Replaces a cache value for the given ID and group.
	 *
	 * @param string $id     The cache key.
	 * @param mixed  $w3tc_data   The data to store in the cache.
	 * @param string $w3tc_group  The cache group.
	 * @param int    $expire The cache expiration time in seconds.
	 *
	 * @return bool True if the data was successfully replaced, otherwise false.
	 */
	public function replace( $id, $w3tc_data, $w3tc_group = 'default', $expire = 0 ) {
		$cache = $this->_get_engine( $w3tc_group );
		return $cache->replace( $id, $w3tc_data, $w3tc_group, $expire );
	}

	/**
	 * Resets the cache, clearing all stored data.
	 *
	 * @return bool True if the cache was successfully reset, otherwise false.
	 */
	public function reset() {
		$w3tc_result = true;

		foreach ( $this->_caches as $w3tc_engine ) {
			$w3tc_result = $w3tc_result && $w3tc_engine->reset();
		}

		return $w3tc_result;
	}

	/**
	 * Flushes all cached data across all cache engines.
	 *
	 * @return bool True if the cache was successfully flushed, otherwise false.
	 */
	public function flush() {
		$w3tc_result = true;

		foreach ( $this->_caches as $w3tc_engine ) {
			$w3tc_result = $w3tc_result && $w3tc_engine->flush();
		}

		return $w3tc_result;
	}

	/**
	 * Flushes the cached data for a specific group.
	 *
	 * @param string $w3tc_group  The cache group.
	 *
	 * @return bool True if the cache for the group was successfully flushed, otherwise false.
	 */
	public function flush_group( $w3tc_group ) {
		$w3tc_result = true;

		foreach ( $this->_caches as $w3tc_engine ) {
			$w3tc_result = $w3tc_result && $w3tc_engine->flush_group( $w3tc_group );
		}

		return $w3tc_result;
	}

	/**
	 * Flushes runtime cache data that is temporary and non-persistent.
	 *
	 * @return bool True if the runtime cache was successfully flushed, otherwise false.
	 */
	public function flush_runtime() {
		$w3tc_result = true;

		foreach ( $this->_caches as $w3tc_engine ) {
			$w3tc_result = $w3tc_result && $w3tc_engine->flush_runtime();
		}

		return $w3tc_result;
	}

	/**
	 * Checks if a given cache feature is supported.
	 *
	 * @param string $feature The feature to check.
	 *
	 * @return bool True if the feature is supported, otherwise false.
	 */
	public function supports( string $feature ) {
		return in_array( $feature, $this->supported_features, true );
	}

	/**
	 * Adds global cache groups to the cache engine.
	 *
	 * @param mixed $groups An array or string of cache groups to add as global.
	 *
	 * @return void
	 */
	public function add_global_groups( $groups ) {
		if ( ! is_array( $groups ) ) {
			$groups = array( $groups );
		}

		foreach ( $groups as $w3tc_group ) {
			$cache = $this->_get_engine( $w3tc_group );
			$cache->add_global_groups( array( $w3tc_group ) );
		}
	}

	/**
	 * Adds non-persistent cache groups to the cache engine.
	 *
	 * @param mixed $groups An array or string of cache groups to add as non-persistent.
	 *
	 * @return void
	 */
	public function add_nonpersistent_groups( $groups ) {
		if ( ! is_array( $groups ) ) {
			$groups = array( $groups );
		}

		foreach ( $groups as $w3tc_group ) {
			$cache = $this->_get_engine( $w3tc_group );
			$cache->add_nonpersistent_groups( array( $w3tc_group ) );
		}
	}

	/**
	 * Retrieves the appropriate cache engine based on the group.
	 *
	 * @param string $w3tc_group The cache group.
	 *
	 * @return object The cache engine for the specified group.
	 */
	private function _get_engine( $w3tc_group = '' ) {
		if ( isset( $this->_cache_by_group[ $w3tc_group ] ) ) {
			return $this->_cache_by_group[ $w3tc_group ];
		}

		return $this->_default_cache;
	}

	/**
	 * Decreases the cached value of a given ID by a specified offset.
	 *
	 * @param string $id     The cache key.
	 * @param int    $w3tc_offset The value to decrease by.
	 * @param string $w3tc_group  The cache group.
	 *
	 * @return mixed The updated value if successful, otherwise false.
	 */
	public function decr( $id, $w3tc_offset = 1, $w3tc_group = 'default' ) {
		$cache = $this->_get_engine( $w3tc_group );
		return $cache->decr( $id, $w3tc_offset, $w3tc_group );
	}

	/**
	 * Increases the cached value of a given ID by a specified offset.
	 *
	 * @param string $id     The cache key.
	 * @param int    $w3tc_offset The value to increase by.
	 * @param string $w3tc_group  The cache group.
	 *
	 * @return mixed The updated value if successful, otherwise false.
	 */
	public function incr( $id, $w3tc_offset = 1, $w3tc_group = 'default' ) {
		$cache = $this->_get_engine( $w3tc_group );
		return $cache->incr( $id, $w3tc_offset, $w3tc_group );
	}

	/**
	 * Switches to a different blog context in a multisite environment.
	 *
	 * @param int $blog_id The blog ID to switch to.
	 *
	 * @return void
	 */
	public function switch_to_blog( $blog_id ) {
		foreach ( $this->_caches as $cache ) {
			$cache->switch_blog( $blog_id );
		}
	}
}
