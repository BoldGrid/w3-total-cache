<?php
/**
 * File: Cache_Wincache.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cache_Wincache
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
 */
class Cache_Wincache extends Cache_Base {

	/**
	 * Used for faster flushing
	 *
	 * @var integer $_key_version
	 */
	private $_key_version = array();

	/**
	 * Adds a value to the cache.
	 *
	 * This method is an alias of the `set()` method and allows adding data to the cache with an optional expiration
	 * time and group. It is used for storing data that is not already present in the cache.
	 *
	 * @param string $key    The unique key to identify the cached item.
	 * @param mixed  $value  The value to be stored in the cache, passed by reference.
	 * @param int    $expire The expiration time in seconds. Default is 0 (no expiration).
	 * @param string $group  The group to which the cache item belongs. Default is an empty string (no group).
	 *
	 * @return bool True on success, false on failure.
	 */
	public function add( $key, &$value, $expire = 0, $group = '' ) {
		return $this->set( $key, $value, $expire, $group );
	}

	/**
	 * Sets a value in the cache.
	 *
	 * This method stores a value in the cache for a given key, and optionally allows setting an expiration time
	 * and group. The value is serialized before storing to ensure it can handle complex data types.
	 *
	 * @param string $key    The unique key to identify the cached item.
	 * @param mixed  $value  The value to be stored in the cache.
	 * @param int    $expire The expiration time in seconds. Default is 0 (no expiration).
	 * @param string $group  The group to which the cache item belongs. Default is an empty string (no group).
	 *
	 * @return bool True on success, false on failure.
	 */
	public function set( $key, $value, $expire = 0, $group = '' ) {
		if ( ! isset( $value['key_version'] ) ) {
			$value['key_version'] = $this->_get_key_version( $group );
		}

		$storage_key = $this->get_item_key( $key );

		return wincache_ucache_set( $storage_key, serialize( $value ), $expire );
	}

	/**
	 * Retrieves a cached value with version checking.
	 *
	 * This method retrieves a cached value for a given key, checking the version of the cached data. If the stored
	 * data's version is different from the current version, it may return old data or null depending on settings.
	 *
	 * @param string $key   The unique key to identify the cached item.
	 * @param string $group The group to which the cache item belongs. Default is an empty string (no group).
	 *
	 * @return array The cached value and a flag indicating if old data was returned.
	 */
	public function get_with_old( $key, $group = '' ) {
		$has_old_data = false;

		$storage_key = $this->get_item_key( $key );

		$v = @unserialize( wincache_ucache_get( $storage_key ) );
		if ( ! is_array( $v ) || ! isset( $v['key_version'] ) ) {
			return array( null, $has_old_data );
		}

		$key_version = $this->_get_key_version( $group );
		if ( $v['key_version'] === $key_version ) {
			return array( $v, $has_old_data );
		}

		if ( $v['key_version'] > $key_version ) {
			if ( ! empty( $v['key_version_at_creation'] ) && $v['key_version_at_creation'] !== $key_version ) {
				$this->_set_key_version( $v['key_version'], $group );
			}
			return array( $v, $has_old_data );
		}

		// key version is old.
		if ( ! $this->_use_expired_data ) {
			return array( null, $has_old_data );
		}

		// if we have expired data - update it for future use and let current process recalculate it.
		$expires_at = isset( $v['expires_at'] ) ? $v['expires_at'] : null;
		if ( null === $expires_at || time() > $expires_at ) {
			$v['expires_at'] = time() + 30;
			wincache_ucache_set( $storage_key, serialize( $v ), 0 );
			$has_old_data = true;

			return array( null, $has_old_data );
		}

		// return old version.
		return array( $v, $has_old_data );
	}

	/**
	 * Replaces a cached value only if it already exists.
	 *
	 * This method updates the cache with a new value for the given key, but only if the key already exists in the cache.
	 * If the key does not exist, it returns false.
	 *
	 * @param string $key    The unique key to identify the cached item.
	 * @param mixed  $value  The new value to be stored in the cache, passed by reference.
	 * @param int    $expire The expiration time in seconds. Default is 0 (no expiration).
	 * @param string $group  The group to which the cache item belongs. Default is an empty string (no group).
	 *
	 * @return bool True on success, false on failure.
	 */
	public function replace( $key, &$value, $expire = 0, $group = '' ) {
		if ( $this->get( $key, $group ) !== false ) {
			return $this->set( $key, $value, $expire, $group );
		}

		return false;
	}

	/**
	 * Deletes a cached value.
	 *
	 * This method deletes a cached item for a given key, optionally checking the group. If expired data is allowed,
	 * it may mark the data as invalid instead of deleting it immediately.
	 *
	 * @param string $key    The unique key to identify the cached item.
	 * @param string $group  The group to which the cache item belongs. Default is an empty string (no group).
	 *
	 * @return bool True on success, false on failure.
	 */
	public function delete( $key, $group = '' ) {
		$storage_key = $this->get_item_key( $key );

		if ( $this->_use_expired_data ) {
			$v = @unserialize( wincache_ucache_get( $storage_key ) );
			if ( is_array( $v ) ) {
				$v['key_version'] = 0;
				wincache_ucache_set( $storage_key, serialize( $v ), 0 );
				return true;
			}
		}

		return wincache_ucache_delete( $storage_key );
	}

	/**
	 * Forcefully deletes a cached value without considering expired data.
	 *
	 * This method immediately deletes the cached item for a given key, disregarding any expired data settings.
	 *
	 * @param string $key    The unique key to identify the cached item.
	 * @param string $group  The group to which the cache item belongs. Default is an empty string (no group).
	 *
	 * @return bool True on success, false on failure.
	 */
	public function hard_delete( $key, $group = '' ) {
		$storage_key = $this->get_item_key( $key );
		return wincache_ucache_delete( $storage_key );
	}

	/**
	 * Flushes the entire cache for a given group.
	 *
	 * This method increments the key version for the specified group and clears the associated cache data,
	 * effectively resetting the cache for that group.
	 *
	 * @param string $group The group to which the cache belongs. Default is an empty string (no group).
	 *
	 * @return bool True on success, false on failure.
	 */
	public function flush( $group = '' ) {
		$this->_get_key_version( $group ); // initialize $this->_key_version.
		++$this->_key_version[ $group ];
		$this->_set_key_version( $this->_key_version[ $group ], $group );
		return true;
	}

	/**
	 * Generates the next key version and return the extension data.
	 *
	 * This method generates a new key version for a given group and returns an array containing both the new version
	 * and the version at creation. This is used to track versioning and support cache invalidation.
	 *
	 * @param string $group The group to which the cache belongs.
	 *
	 * @return array An array containing the next key version and the version at creation.
	 */
	public function get_ahead_generation_extension( $group ) {
		$v = $this->_get_key_version( $group );
		return array(
			'key_version'             => $v + 1,
			'key_version_at_creation' => $v,
		);
	}

	/**
	 * Flushes the cache group after ahead generation.
	 *
	 * This method flushes the cache for a given group if the generated extension has a higher key version. It ensures
	 * that the cache version is updated appropriately to avoid serving outdated data.
	 *
	 * @param string $group     The group to which the cache belongs.
	 * @param array  $extension {
	 *     The extension data containing the new key version.
	 *
	 *     @type string $key_version The new key version.
	 * }
	 *
	 * @return void
	 */
	public function flush_group_after_ahead_generation( $group, $extension ) {
		$v = $this->_get_key_version( $group );
		if ( $extension['key_version'] > $v ) {
			$this->_set_key_version( $extension['key_version'], $group );
		}
	}

	/**
	 * Checks if WinCache is available for use.
	 *
	 * This method checks whether the necessary WinCache functions are available. It is used to determine if
	 * WinCache is enabled and can be utilized for caching operations.
	 *
	 * @return bool True if WinCache is available, false otherwise.
	 */
	public function available() {
		return function_exists( 'wincache_ucache_set' );
	}

	/**
	 * Retrieves the current version of a key for a given group.
	 *
	 * This method gets the current version of the cache key for a specified group. If the key does not exist, it
	 * initializes the version for the group.
	 *
	 * @param string $group The group to which the cache belongs.
	 *
	 * @return int The current version of the cache key.
	 */
	private function _get_key_version( $group = '' ) {
		if ( ! isset( $this->_key_version[ $group ] ) || $this->_key_version[ $group ] <= 0 ) {
			$v                            = wincache_ucache_get( $this->_get_key_version_key( $group ) );
			$v                            = intval( $v );
			$this->_key_version[ $group ] = ( $v > 0 ? $v : 1 );
		}

		return $this->_key_version[ $group ];
	}

	/**
	 * Sets the version of the cache key for a specified group.
	 *
	 * This method sets the version of the cache key for a specified group. It is used when updating the version
	 * of a cache key to reflect changes in the cache.
	 *
	 * @param int    $v     The new version of the cache key.
	 * @param string $group The group to which the cache belongs.
	 *
	 * @return void
	 */
	private function _set_key_version( $v, $group ) {
		wincache_ucache_set( $this->_get_key_version_key( $group ), $v, 0 );
	}

	/**
	 * Sets a value in the cache if it matches the given old value.
	 *
	 * This method checks if the cached value for a given key matches the provided `old_value`. If the `content` of
	 * the cached value does not match the `old_value['content']`, it returns false. Otherwise, it updates the cache
	 * with the new value.
	 *
	 * Note: This method does not guarantee atomicity as file locks may fail.
	 *
	 * @param string $key       The unique key to identify the cached item.
	 * @param array  $old_value {
	 *     The old value to compare against, should include a 'content' key.
	 *
	 *     @type mixed $content The content to compare.
	 * }
	 * @param mixed  $new_value The new value to be stored in the cache.
	 *
	 * @return bool True on success, false on failure (e.g., if the old value does not match).
	 */
	public function set_if_maybe_equals( $key, $old_value, $new_value ) {
		// cant guarantee atomic action here, filelocks fail often.
		$value = $this->get( $key );
		if ( isset( $old_value['content'] ) && $value['content'] !== $old_value['content'] ) {
			return false;
		}

		return $this->set( $key, $new_value );
	}

	/**
	 * Increments a cached counter by a specified value.
	 *
	 * This method increments the counter stored in the cache for a given key by the provided value. If the key does
	 * not exist or the counter is not initialized, it will initialize the counter with a value of 0.
	 *
	 * @param string $key   The unique key to identify the cached counter.
	 * @param int    $value The value by which to increment the counter.
	 *
	 * @return bool True on success, false if the counter could not be incremented or initialized.
	 */
	public function counter_add( $key, $value ) {
		if ( 0 === $value ) {
			return true;
		}

		$storage_key = $this->get_item_key( $key );
		$r           = wincache_ucache_inc( $storage_key, $value );
		if ( ! $r ) { // it doesnt initialize counter by itself.
			$this->counter_set( $key, 0 );
		}

		return $r;
	}

	/**
	 * Sets a cached counter to a specified value.
	 *
	 * This method sets the counter for a given key in the cache to the specified value.
	 *
	 * @param string $key   The unique key to identify the cached counter.
	 * @param int    $value The value to set the counter to.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function counter_set( $key, $value ) {
		$storage_key = $this->get_item_key( $key );
		return wincache_ucache_set( $storage_key, $value );
	}

	/**
	 * Retrieves the current value of a cached counter.
	 *
	 * This method retrieves the cached value of a counter for a given key. If the counter does not exist, it returns 0.
	 *
	 * @param string $key The unique key to identify the cached counter.
	 *
	 * @return int The current value of the counter.
	 */
	public function counter_get( $key ) {
		$storage_key = $this->get_item_key( $key );
		$v           = (int) wincache_ucache_get( $storage_key );

		return $v;
	}
}
