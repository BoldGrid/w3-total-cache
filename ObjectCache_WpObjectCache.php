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

		foreach ( $use_for_object_groups as $group ) {
			$this->_cache_by_group[ $group ] = $cache;
		}
	}

	/**
	 * Retrieves a cached value by its ID from the specified group.
	 *
	 * @param string $id    The cache key.
	 * @param string $group The cache group.
	 * @param bool   $force Whether to force a cache retrieval, bypassing the cache expiration.
	 * @param mixed  $found A reference to the variable that will store whether the value was found.
	 *
	 * @return mixed The cached value if found, otherwise false.
	 */
	public function get( $id, $group = 'default', $force = false, &$found = null ) {
		$cache = $this->_get_engine( $group );
		return $cache->get( $id, $group, $force, $found );
	}

	/**
	 * Retrieves multiple cached values by their IDs from the specified group.
	 *
	 * @since 2.2.8
	 *
	 * @param array  $ids    The cache keys.
	 * @param string $group  The cache group.
	 * @param bool   $force  Whether to force a cache retrieval, bypassing the cache expiration.
	 *
	 * @return array An array of cached values, indexed by their respective IDs.
	 */
	public function get_multiple( $ids, $group = 'default', $force = false ) {
		$cache = $this->_get_engine( $group );
		return $cache->get_multiple( $ids, $group, $force );
	}

	/**
	 * Sets a cache value for a given ID and group.
	 *
	 * @param string $id     The cache key.
	 * @param mixed  $data   The data to store in the cache.
	 * @param string $group  The cache group.
	 * @param int    $expire The cache expiration time in seconds.
	 *
	 * @return bool True if the data was successfully cached, otherwise false.
	 */
	public function set( $id, $data, $group = 'default', $expire = 0 ) {
		$cache = $this->_get_engine( $group );
		return $cache->set( $id, $data, $group, $expire );
	}

	/**
	 * Sets multiple cache values for their respective IDs and groups.
	 *
	 * @since 2.2.8
	 *
	 * @param array  $data   An array of data indexed by cache key.
	 * @param string $group  The cache group.
	 * @param int    $expire The cache expiration time in seconds.
	 *
	 * @return bool True if the data was successfully cached, otherwise false.
	 */
	public function set_multiple( $data, $group = 'default', $expire = 0 ) {
		$cache = $this->_get_engine( $group );
		return $cache->set_multiple( $data, $group, $expire );
	}

	/**
	 * Deletes a cached value by its ID from the specified group.
	 *
	 * @param string $id    The cache key.
	 * @param string $group The cache group.
	 * @param bool   $force Whether to forcefully delete the cache.
	 *
	 * @return bool True if the cache was successfully deleted, otherwise false.
	 */
	public function delete( $id, $group = 'default', $force = false ) {
		$cache = $this->_get_engine( $group );
		return $cache->delete( $id, $group, $force );
	}

	/**
	 * Deletes multiple cached values by their IDs from the specified group.
	 *
	 * @since 2.2.8
	 *
	 * @param array  $keys  The cache keys.
	 * @param string $group The cache group.
	 *
	 * @return bool True if the caches were successfully deleted, otherwise false.
	 */
	public function delete_multiple( $keys, $group = 'default' ) {
		$cache = $this->_get_engine( $group );
		return $cache->delete_multiple( $keys, $group );
	}


	/**
	 * Adds a new value to the cache if it does not already exist for the given ID and group.
	 *
	 * @param string $id     The cache key.
	 * @param mixed  $data   The data to store in the cache.
	 * @param string $group  The cache group.
	 * @param int    $expire The cache expiration time in seconds.
	 *
	 * @return bool True if the data was added, otherwise false.
	 */
	public function add( $id, $data, $group = 'default', $expire = 0 ) {
		$cache = $this->_get_engine( $group );
		return $cache->add( $id, $data, $group, $expire );
	}

	/**
	 * Adds multiple new values to the cache, ensuring they do not overwrite existing data.
	 *
	 * @since 2.2.8
	 *
	 * @param array  $data   An array of data indexed by cache key.
	 * @param string $group  The cache group.
	 * @param int    $expire The cache expiration time in seconds.
	 *
	 * @return bool True if the data was successfully added, otherwise false.
	 */
	public function add_multiple( array $data, $group = '', $expire = 0 ) {
		$cache = $this->_get_engine( $group );
		return $cache->add_multiple( $data, $group, $expire );
	}

	/**
	 * Replaces a cache value for the given ID and group.
	 *
	 * @param string $id     The cache key.
	 * @param mixed  $data   The data to store in the cache.
	 * @param string $group  The cache group.
	 * @param int    $expire The cache expiration time in seconds.
	 *
	 * @return bool True if the data was successfully replaced, otherwise false.
	 */
	public function replace( $id, $data, $group = 'default', $expire = 0 ) {
		$cache = $this->_get_engine( $group );
		return $cache->replace( $id, $data, $group, $expire );
	}

	/**
	 * Resets the cache, clearing all stored data.
	 *
	 * @return bool True if the cache was successfully reset, otherwise false.
	 */
	public function reset() {
		$result = true;

		foreach ( $this->_caches as $engine ) {
			$result = $result && $engine->reset();
		}

		return $result;
	}

	/**
	 * Flushes all cached data across all cache engines.
	 *
	 * @return bool True if the cache was successfully flushed, otherwise false.
	 */
	public function flush() {
		$result = true;

		foreach ( $this->_caches as $engine ) {
			$result = $result && $engine->flush();
		}

		return $result;
	}

	/**
	 * Flushes the cached data for a specific group.
	 *
	 * @param string $group  The cache group.
	 *
	 * @return bool True if the cache for the group was successfully flushed, otherwise false.
	 */
	public function flush_group( $group ) {
		$result = true;

		foreach ( $this->_caches as $engine ) {
			$result = $result && $engine->flush_group( $group );
		}

		return $result;
	}

	/**
	 * Flushes runtime cache data that is temporary and non-persistent.
	 *
	 * @return bool True if the runtime cache was successfully flushed, otherwise false.
	 */
	public function flush_runtime() {
		$result = true;

		foreach ( $this->_caches as $engine ) {
			$result = $result && $engine->flush_runtime();
		}

		return $result;
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

		foreach ( $groups as $group ) {
			$cache = $this->_get_engine( $group );
			$cache->add_global_groups( array( $group ) );
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

		foreach ( $groups as $group ) {
			$cache = $this->_get_engine( $group );
			$cache->add_nonpersistent_groups( array( $group ) );
		}
	}

	/**
	 * Retrieves the appropriate cache engine based on the group.
	 *
	 * @param string $group The cache group.
	 *
	 * @return object The cache engine for the specified group.
	 */
	private function _get_engine( $group = '' ) {
		if ( isset( $this->_cache_by_group[ $group ] ) ) {
			return $this->_cache_by_group[ $group ];
		}

		return $this->_default_cache;
	}

	/**
	 * Decreases the cached value of a given ID by a specified offset.
	 *
	 * @param string $id     The cache key.
	 * @param int    $offset The value to decrease by.
	 * @param string $group  The cache group.
	 *
	 * @return mixed The updated value if successful, otherwise false.
	 */
	public function decr( $id, $offset = 1, $group = 'default' ) {
		$cache = $this->_get_engine( $group );
		return $cache->decr( $id, $offset, $group );
	}

	/**
	 * Increases the cached value of a given ID by a specified offset.
	 *
	 * @param string $id     The cache key.
	 * @param int    $offset The value to increase by.
	 * @param string $group  The cache group.
	 *
	 * @return mixed The updated value if successful, otherwise false.
	 */
	public function incr( $id, $offset = 1, $group = 'default' ) {
		$cache = $this->_get_engine( $group );
		return $cache->incr( $id, $offset, $group );
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
