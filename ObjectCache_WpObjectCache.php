<?php
namespace W3TC;

class ObjectCache_WpObjectCache {
	private $_config = null;
	private $_default_cache;
	private $_caches = array();
	private $_cache_by_group = array();

	function __construct() {
		$this->_config = Dispatcher::config();
		$this->_default_cache = Dispatcher::component(
			'ObjectCache_WpObjectCache_Regular' );
		$this->_caches[] = $this->_default_cache;
	}

	/**
	 * Registers cache object so that its used for specific groups of
	 * object cache instead of default cache
	 */
	public function register_cache( $cache, $use_for_object_groups ) {
		$this->_caches[] = $cache;
		foreach ( $use_for_object_groups as $group )
			$this->_cache_by_group[$group] = $cache;
	}

	/**
	 * Get from the cache
	 *
	 * @param string  $id
	 * @param string  $group
	 * @return mixed
	 */
	function get( $id, $group = 'default', $force = false, &$found = null ) {
		$cache = $this->_get_engine( $group );
		return $cache->get( $id, $group, $force, $found );
	}

	/**
	 * Retrieves multiple values from the cache in one call.
	 *
	 * @since 2.2.8
	 *
	 * @param array  $ids  Array of keys under which the cache contents are stored.
	 * @param string $group Optional. Where the cache contents are grouped. Default 'default'.
	 * @param bool   $force Optional. Whether to force an update of the local cache
	 *                      from the persistent cache. Default false.
	 *
	 * @return array Array of return values, grouped by key. Each value is either
	 *               the cache contents on success, or false on failure.
	 */
	public function get_multiple( $ids, $group = 'default', $force = false ) {
		$cache = $this->_get_engine( $group );
		return $cache->get_multiple( $ids, $group, $force );
	}

	/**
	 * Set to the cache
	 *
	 * @param string  $id
	 * @param mixed   $data
	 * @param string  $group
	 * @param integer $expire
	 * @return boolean
	 */
	function set( $id, $data, $group = 'default', $expire = 0 ) {
		$cache = $this->_get_engine( $group );
		return $cache->set( $id, $data, $group, $expire );
	}

	/**
	 * Sets multiple values to the cache in one call.
	 *
	 * @since 2.2.8
	 *
	 * @param array  $data   Array of key and value to be set.
	 * @param string $group  Optional. Where the cache contents are grouped. Default empty.
	 * @param int    $expire Optional. When to expire the cache contents, in seconds.
	 *                       Default 0 (no expiration).
	 *
	 * @return bool[] Array of return values, grouped by key. Each value is always true.
	 */
	public function set_multiple( $data, $group = 'default', $expire = 0 ) {
		$cache = $this->_get_engine( $group );
		return $cache->set_multiple( $data, $group, $expire );
	}

	/**
	 * Delete from the cache
	 *
	 * @param string  $id
	 * @param string  $group
	 * @param bool    $force
	 * @return boolean
	 */
	function delete( $id, $group = 'default', $force = false ) {
		$cache = $this->_get_engine( $group );
		return $cache->delete( $id, $group, $force );
	}

	/**
	 * Deletes multiple values from the cache in one call.
	 *
	 * @since 2.2.8
	 *
	 * @param array  $keys  Array of keys to be deleted.
	 * @param string $group Optional. Where the cache contents are grouped. Default empty.
	 *
	 * @return bool[] Array of return values, grouped by key. Each value is either
	 *                true on success, or false if the contents were not deleted.
	 */
	public function delete_multiple( $keys, $group = 'default' ) {
		$cache = $this->_get_engine( $group );
		return $cache->delete_multiple( $keys, $group );
	}

	/**
	 * Add to the cache
	 *
	 * @param string  $id
	 * @param mixed   $data
	 * @param string  $group
	 * @param integer $expire
	 * @return boolean
	 */
	function add( $id, $data, $group = 'default', $expire = 0 ) {
		$cache = $this->_get_engine( $group );
		return $cache->add( $id, $data, $group, $expire );
	}

	/**
	 * Adds multiple values to the cache in one call.
	 *
	 * @since 2.2.8
	 *
	 * @param array  $data   Array of keys and values to be added.
	 * @param string $group  Optional. Where the cache contents are grouped. Default empty.
	 * @param int    $expire Optional. When to expire the cache contents, in seconds.
	 *                       Default 0 (no expiration).
	 *
	 * @return bool[] Array of return values, grouped by key. Each value is either
	 *                true on success, or false if cache key and group already exist.
	 */
	public function add_multiple( array $data, $group = '', $expire = 0 ) {
		$cache = $this->_get_engine( $group );
		return $cache->add_multiple( $data, $group, $expire );
	}

	/**
	 * Replace in the cache
	 *
	 * @param string  $id
	 * @param mixed   $data
	 * @param string  $group
	 * @param integer $expire
	 * @return boolean
	 */
	function replace( $id, $data, $group = 'default', $expire = 0 ) {
		$cache = $this->_get_engine( $group );
		return $cache->replace( $id, $data, $group, $expire );
	}

	/**
	 * Reset keys
	 *
	 * @return boolean
	 */
	function reset() {
		$result = true;
		foreach ( $this->_caches as $engine )
			$result = $result && $engine->reset();
		return $result;
	}

	/**
	 * Flush cache
	 *
	 * @return boolean
	 */
	function flush() {
		$result = true;
		foreach ( $this->_caches as $engine )
			$result = $result && $engine->flush();
		return $result;
	}

	/**
	 * Add global groups
	 *
	 * @param array   $groups
	 * @return void
	 */
	function add_global_groups( $groups ) {
		if ( !is_array( $groups ) )
			$groups = array( $groups );

		foreach ( $groups as $group ) {
			$cache = $this->_get_engine( $group );
			$cache->add_global_groups( array( $group ) );
		}
	}

	/**
	 * Add non-persistent groups
	 *
	 * @param array   $groups
	 * @return void
	 */
	function add_nonpersistent_groups( $groups ) {
		if ( !is_array( $groups ) )
			$groups = array( $groups );

		foreach ( $groups as $group ) {
			$cache = $this->_get_engine( $group );
			$cache->add_nonpersistent_groups( array( $group ) );
		}
	}

	/**
	 * Return engine based on which group the OC value belongs to.
	 *
	 * @param string  $group
	 * @return mixed
	 */
	private function _get_engine( $group = '' ) {
		if ( isset( $this->_cache_by_group[$group] ) )
			return $this->_cache_by_group[$group];

		return $this->_default_cache;
	}

	/**
	 * Decrement numeric cache item's value
	 *
	 * @param int|string $id     The cache key to increment
	 * @param int     $offset The amount by which to decrement the item's value. Default is 1.
	 * @param string  $group  The group the key is in.
	 * @return bool|int False on failure, the item's new value on success.
	 */
	function decr( $id, $offset = 1, $group = 'default' ) {
		$cache = $this->_get_engine( $group );
		return $cache->decr( $id, $offset, $group );
	}

	/**
	 * Increment numeric cache item's value
	 *
	 * @param int|string $id     The cache key to increment
	 * @param int     $offset The amount by which to increment the item's value. Default is 1.
	 * @param string  $group  The group the key is in.
	 * @return false|int False on failure, the item's new value on success.
	 */
	function incr( $id, $offset = 1, $group = 'default' ) {
		$cache = $this->_get_engine( $group );
		return $cache->incr( $id, $offset, $group );
	}

	function switch_to_blog( $blog_id ) {
		foreach ( $this->_caches as $cache )
			$cache->switch_blog( $blog_id );
	}
}
