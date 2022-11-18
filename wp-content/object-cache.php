<?php
//ObjectCache Version: 1.4
/**
 * W3 Total Cache Object Cache
 */
if ( !defined( 'ABSPATH' ) ) {
	die();
}

if ( !defined( 'W3TC_DIR' ) ) {
	define( 'W3TC_DIR', ( defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins' ) . '/w3-total-cache' );
}

if ( !@is_dir( W3TC_DIR ) || !file_exists( W3TC_DIR . '/w3-total-cache-api.php' ) ) {
	if ( !defined( 'WP_ADMIN' ) ) { // lets don't show error on front end
		require_once ABSPATH . WPINC . '/cache.php';
	} else {
		echo sprintf( '<strong>W3 Total Cache Error:</strong> some files appear to be missing or out of place. Please re-install plugin or remove <strong>%s</strong>. <br />', __FILE__ );
	}
} else {
	require_once W3TC_DIR . '/w3-total-cache-api.php';

	/**
	 * Init cache
	 *
	 * @return void
	 */
	function wp_cache_init() {
		$GLOBALS['wp_object_cache'] =
			\W3TC\Dispatcher::component( 'ObjectCache_WpObjectCache' );
	}

	/**
	 * Close cache
	 *
	 * @return boolean
	 */
	function wp_cache_close() {
		return true;
	}

	/**
	 * Get cache
	 *
	 * @param string  $id
	 * @param string  $group
	 * @return mixed
	 */
	function wp_cache_get( $id, $group = 'default', $force = false, &$found = null ) {
		global $wp_object_cache;

		return $wp_object_cache->get( $id, $group, $force, $found );
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
	function wp_cache_get_multiple( $ids, $group = 'default', $force = false ) {
		global $wp_object_cache;

		return $wp_object_cache->get_multiple( $ids, $group, $force );
	}

	/**
	 * Set cache
	 *
	 * @param string  $id
	 * @param mixed   $data
	 * @param string  $group
	 * @param integer $expire
	 * @return boolean
	 */
	function wp_cache_set( $id, $data, $group = 'default', $expire = 0 ) {
		global $wp_object_cache;

		return $wp_object_cache->set( $id, $data, $group, (int)$expire );
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
	function wp_cache_set_multiple( $data, $group = 'default', $expire = 0 ) {
		global $wp_object_cache;

		return $wp_object_cache->set_multiple( $data, $group, (int) $expire );
	}

	/**
	 * Delete from cache
	 *
	 * @param string  $id
	 * @param string  $group
	 * @return boolean
	 */
	function wp_cache_delete( $id, $group = 'default' ) {
		global $wp_object_cache;

		return $wp_object_cache->delete( $id, $group );
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
	function wp_cache_delete_multiple( $keys, $group = 'default' ) {
		global $wp_object_cache;

		return $wp_object_cache->delete_multiple( $keys, $group );
	}

	/**
	 * Add data to cache
	 *
	 * @param string  $id
	 * @param mixed   $data
	 * @param string  $group
	 * @param integer $expire
	 * @return boolean
	 */
	function wp_cache_add( $id, $data, $group = 'default', $expire = 0 ) {
		global $wp_object_cache;

		return $wp_object_cache->add( $id, $data, $group, (int)$expire );
	}

	/**
	 * Adds multiple values to the cache in one call, if the cache keys don't already exist.
	 *
	 * @since 2.2.8
	 *
	 * @param array  $data   Array of keys and values to be added.
	 * @param string $group  Optional. Where the cache contents are grouped. Default empty.
	 * @param int    $expire Optional. When to expire the cache contents, in seconds.
	 *                       Default 0 (no expiration).
	 * @return bool[] Array of return values, grouped by key. Each value is either
	 *                true on success, or false if cache key and group already exist.
	 */
	function wp_cache_add_multiple( array $data, $group = '', $expire = 0 ) {
		global $wp_object_cache;

		return $wp_object_cache->add_multiple( $data, $group, $expire );
	}

	/**
	 * Replace data in cache
	 *
	 * @param string  $id
	 * @param mixed   $data
	 * @param string  $group
	 * @param integer $expire
	 * @return boolean
	 */
	function wp_cache_replace( $id, $data, $group = 'default', $expire = 0 ) {
		global $wp_object_cache;

		return $wp_object_cache->replace( $id, $data, $group, (int)$expire );
	}

	/**
	 * Reset cache
	 *
	 * @return boolean
	 */
	function wp_cache_reset() {
		global $wp_object_cache;

		return $wp_object_cache->reset();
	}

	/**
	 * Flush cache
	 *
	 * @return boolean
	 */
	function wp_cache_flush() {
		global $wp_object_cache;

		return $wp_object_cache->flush();
	}

	/**
	 * Add global groups
	 *
	 * @param array   $groups
	 * @return void
	 */
	function wp_cache_add_global_groups( $groups ) {
		global $wp_object_cache;

		$wp_object_cache->add_global_groups( $groups );
	}

	/**
	 * Add non-persistent groups
	 *
	 * @param array   $groups
	 * @return void
	 */
	function wp_cache_add_non_persistent_groups( $groups ) {
		global $wp_object_cache;

		$wp_object_cache->add_nonpersistent_groups( $groups );
	}

	/**
	 * Increment numeric cache item's value
	 *
	 * @param int|string $key    The cache key to increment
	 * @param int     $offset The amount by which to increment the item's value. Default is 1.
	 * @param string  $group  The group the key is in.
	 * @return bool|int False on failure, the item's new value on success.
	 */
	function wp_cache_incr( $key, $offset = 1, $group = 'default' ) {
		global $wp_object_cache;

		return $wp_object_cache->incr( $key, $offset, $group );
	}

	/**
	 * Decrement numeric cache item's value
	 *
	 * @param int|string $key    The cache key to increment
	 * @param int     $offset The amount by which to decrement the item's value. Default is 1.
	 * @param string  $group  The group the key is in.
	 * @return bool|int False on failure, the item's new value on success.
	 */
	function wp_cache_decr( $key, $offset = 1, $group = 'default' ) {
		global $wp_object_cache;

		return $wp_object_cache->decr( $key, $offset, $group );
	}

	/**
	 * Switch the internal blog id.
	 *
	 * This changes the blog id used to create keys in blog specific groups.
	 *
	 * @param int     $blog_id Blog ID
	 */
	function wp_cache_switch_to_blog( $blog_id ) {
		global $wp_object_cache;

		return $wp_object_cache->switch_to_blog( $blog_id );
	}
}
