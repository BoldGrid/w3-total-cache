<?php
/**
 * File: Cache_Xcache.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cache_Xcache
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */
class Cache_Xcache extends Cache_Base {
	/**
	 * Used for faster flushing
	 *
	 * @var integer $_key_version
	 */
	private $_key_version = array();

	/**
	 * Adds a new item to the cache if it does not already exist.
	 *
	 * If the item does not exist in the cache, it is added with the specified expiration and group. If it already exists,
	 * the method returns false.
	 *
	 * @param string $w3tc_key    The unique key to identify the cached item.
	 * @param mixed  $w3tc_value  The value to store in the cache (passed by reference).
	 * @param int    $expire The expiration time in seconds. Defaults to 0 (no expiration).
	 * @param string $w3tc_group  The group to which the key belongs. Defaults to an empty string.
	 *
	 * @return bool True if the item was added successfully, false if the item already exists.
	 */
	public function add( $w3tc_key, &$w3tc_value, $expire = 0, $w3tc_group = '' ) {
		if ( false === $this->get( $w3tc_key, $w3tc_group ) ) {
			return $this->set( $w3tc_key, $w3tc_value, $expire, $w3tc_group );
		}

		return false;
	}

	/**
	 * Sets a value in the cache, overwriting any existing value.
	 *
	 * This method sets a value in the cache for a given key, with an optional expiration time and group. If the value
	 * does not have a `key_version`, it is assigned the current group key version.
	 *
	 * @param string $w3tc_key    The unique key to identify the cached item.
	 * @param mixed  $w3tc_value  The value to store in the cache.
	 * @param int    $expire The expiration time in seconds. Defaults to 0 (no expiration).
	 * @param string $w3tc_group  The group to which the key belongs. Defaults to an empty string.
	 *
	 * @return bool True if the value was successfully stored, false otherwise.
	 */
	public function set( $w3tc_key, $w3tc_value, $expire = 0, $w3tc_group = '' ) {
		if ( ! isset( $w3tc_value['key_version'] ) ) {
			$w3tc_value['key_version'] = $this->_get_key_version( $w3tc_group );
		}

		$storage_key = $this->get_item_key( $w3tc_key );

		return xcache_set( $storage_key, serialize( $w3tc_value ), $expire );
	}

	/**
	 * Retrieves a cached item along with its version status.
	 *
	 * This method retrieves the cached value for a given key, checking if the key version matches the current group key
	 * version. It also determines whether the cached value is expired and returns a flag indicating if old data is used.
	 *
	 * @param string $w3tc_key   The unique key to identify the cached item.
	 * @param string $w3tc_group The group to which the key belongs. Defaults to an empty string.
	 *
	 * @return array An array containing the cached value (or null if not found) and a boolean indicating old data usage.
	 */
	public function get_with_old( $w3tc_key, $w3tc_group = '' ) {
		$has_old_data = false;

		$storage_key = $this->get_item_key( $w3tc_key );

		$v = $this->_unserialize(
			xcache_get( $storage_key ),
			array(
				'group' => $w3tc_group,
				'key'   => $w3tc_key,
			)
		);
		if ( ! is_array( $v ) || ! isset( $v['key_version'] ) ) {
			return array( null, $has_old_data );
		}

		$key_version = $this->_get_key_version( $w3tc_group );
		if ( $v['key_version'] === $key_version ) {
			return array( $v, $has_old_data );
		}

		if ( $v['key_version'] > $key_version ) {
			if ( ! empty( $v['key_version_at_creation'] ) && $v['key_version_at_creation'] !== $key_version ) {
				$this->_set_key_version( $v['key_version'], $w3tc_group );
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
			xcache_set( $storage_key, serialize( $v ), 0 );
			$has_old_data = true;

			return array( null, $has_old_data );
		}

		// return old version.
		return array( $v, $has_old_data );
	}

	/**
	 * Replaces an existing cached item with a new value.
	 *
	 * This method updates the value for a given key only if the key already exists in the cache.
	 *
	 * @param string $w3tc_key    The unique key to identify the cached item.
	 * @param mixed  $w3tc_value  The new value to store in the cache (passed by reference).
	 * @param int    $expire The expiration time in seconds. Defaults to 0 (no expiration).
	 * @param string $w3tc_group  The group to which the key belongs. Defaults to an empty string.
	 *
	 * @return bool True if the value was replaced successfully, false if the key does not exist.
	 */
	public function replace( $w3tc_key, &$w3tc_value, $expire = 0, $w3tc_group = '' ) {
		if ( $this->get( $w3tc_key, $w3tc_group ) !== false ) {
			return $this->set( $w3tc_key, $w3tc_value, $expire, $w3tc_group );
		}

		return false;
	}

	/**
	 * Deletes a cached item, optionally keeping expired data.
	 *
	 * If expired data usage is enabled, the key version is set to 0 instead of completely removing the item.
	 * Otherwise, the item is fully deleted from the cache.
	 *
	 * @param string $w3tc_key   The unique key to identify the cached item.
	 * @param string $w3tc_group The group to which the key belongs. Defaults to an empty string.
	 *
	 * @return bool True if the item was deleted successfully, false otherwise.
	 */
	public function delete( $w3tc_key, $w3tc_group = '' ) {
		$storage_key = $this->get_item_key( $w3tc_key );

		if ( $this->_use_expired_data ) {
			$v = $this->_unserialize(
				xcache_get( $storage_key ),
				array(
					'group' => $w3tc_group,
					'key'   => $w3tc_key,
				)
			);
			if ( is_array( $v ) ) {
				$v['key_version'] = 0;
				xcache_set( $storage_key, serialize( $v ), 0 );
				return true;
			}
		}

		return xcache_unset( $storage_key );
	}


	/**
	 * Completely removes a cached item from the cache.
	 *
	 * This method fully deletes the cached item, bypassing any logic for handling expired data.
	 *
	 * @param string $w3tc_key   The unique key to identify the cached item.
	 * @param string $w3tc_group The group to which the key belongs. Defaults to an empty string.
	 *
	 * @return bool True if the item was removed successfully, false otherwise.
	 */
	public function hard_delete( $w3tc_key, $w3tc_group = '' ) {
		$storage_key = $this->get_item_key( $w3tc_key );
		return xcache_unset( $storage_key );
	}

	/**
	 * Flushes the cache for a specific group or all groups.
	 *
	 * This increments the key version for the specified group, effectively invalidating all cache entries
	 * associated with the current key version.
	 *
	 * @param string $w3tc_group (Optional) The cache group to flush. Default is an empty string, which applies to all groups.
	 *
	 * @return bool True on success.
	 */
	public function flush( $w3tc_group = '' ) {
		$this->_get_key_version( $w3tc_group ); // initialize $this->_key_version.
		++$this->_key_version[ $w3tc_group ];
		$this->_set_key_version( $this->_key_version[ $w3tc_group ], $w3tc_group );
		return true;
	}

	/**
	 * Gets the key version extension for ahead-of-time cache generation.
	 *
	 * This provides the current key version and the next version to be used for generating ahead-of-time cache.
	 *
	 * @param string $w3tc_group The cache group to retrieve the extension for.
	 *
	 * @return array An associative array with:
	 *               - 'key_version' (int): The next key version.
	 *               - 'key_version_at_creation' (int): The current key version.
	 */
	public function get_ahead_generation_extension( $w3tc_group ) {
		$v = $this->_get_key_version( $w3tc_group );
		return array(
			'key_version'             => $v + 1,
			'key_version_at_creation' => $v,
		);
	}

	/**
	 * Updates the key version for a cache group after ahead-of-time generation.
	 *
	 * If the provided key version is higher than the current version, the key version is updated.
	 *
	 * @param string $w3tc_group The cache group to update.
	 * @param array  $w3tc_extension {
	 *     The extension data containing 'key_version'.
	 *
	 *     @type string $key_version The version of the cache key.
	 * }
	 *
	 * @return void
	 */
	public function flush_group_after_ahead_generation( $w3tc_group, $w3tc_extension ) {
		$v = $this->_get_key_version( $w3tc_group );
		if ( $w3tc_extension['key_version'] > $v ) {
			$this->_set_key_version( $w3tc_extension['key_version'], $w3tc_group );
		}
	}

	/**
	 * Checks if Wincache is available on the server.
	 *
	 * @return bool True if Wincache functions are available, false otherwise.
	 */
	public function available() {
		return function_exists( 'xcache_set' );
	}

	/**
	 * Retrieves the current key version for a cache group.
	 *
	 * If no version exists, initializes the version to 1.
	 *
	 * @param string $w3tc_group (Optional) The cache group to retrieve the key version for. Default is an empty string.
	 *
	 * @return int The current key version for the specified group.
	 */
	private function _get_key_version( $w3tc_group = '' ) {
		if ( ! isset( $this->_key_version[ $w3tc_group ] ) || $this->_key_version[ $w3tc_group ] <= 0 ) {
			$v = xcache_get( $this->_get_key_version_key( $w3tc_group ) );
			$v = intval( $v );

			$this->_key_version[ $w3tc_group ] = ( $v > 0 ? $v : 1 );
		}

		return $this->_key_version[ $w3tc_group ];
	}

	/**
	 * Sets the key version for a cache group.
	 *
	 * @param int    $v     The key version to set.
	 * @param string $w3tc_group (Optional) The cache group to set the key version for. Default is an empty string.
	 *
	 * @return void
	 */
	private function _set_key_version( $v, $w3tc_group = '' ) {
		xcache_set( $this->_get_key_version_key( $w3tc_group ), $v, 0 );
	}

	/**
	 * Sets a value conditionally if the old value matches the expected value.
	 *
	 * This method attempts to simulate an atomic check-and-set operation. If the current value does not
	 * match the old value, the operation fails.
	 *
	 * @param string $w3tc_key       The cache key to update.
	 * @param array  $old_value {
	 *     The expected current value.
	 *
	 *     @type string $content The expected content to compare.
	 * }
	 * @param array  $new_value The new value to set.
	 *
	 * @return bool True if the operation succeeds, false otherwise.
	 */
	public function set_if_maybe_equals( $w3tc_key, $old_value, $new_value ) {
		// cant guarantee atomic action here, filelocks fail often.
		$w3tc_value = $this->get( $w3tc_key );
		if ( isset( $old_value['content'] ) && $w3tc_value['content'] !== $old_value['content'] ) {
			return false;
		}

		return $this->set( $w3tc_key, $new_value );
	}

	/**
	 * Increments a counter by the specified value.
	 *
	 * If the counter does not exist, initializes it with a value of 0 before incrementing.
	 *
	 * @param string $w3tc_key   The key of the counter to increment.
	 * @param int    $w3tc_value The value to increment the counter by.
	 *
	 * @return int|bool The new counter value on success, or false on failure.
	 */
	public function counter_add( $w3tc_key, $w3tc_value ) {
		if ( 0 === $w3tc_value ) {
			return true;
		}

		$storage_key = $this->get_item_key( $w3tc_key );
		$w3tc_r      = xcache_inc( $storage_key, $w3tc_value );
		if ( ! $w3tc_r ) { // it doesnt initialize counter by itself.
			$this->counter_set( $w3tc_key, 0 );
		}

		return $w3tc_r;
	}

	/**
	 * Sets the value of a counter.
	 *
	 * @param string $w3tc_key   The key of the counter to set.
	 * @param int    $w3tc_value The value to set the counter to.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function counter_set( $w3tc_key, $w3tc_value ) {
		$storage_key = $this->get_item_key( $w3tc_key );
		return xcache_set( $storage_key, $w3tc_value );
	}

	/**
	 * Retrieves the value of a counter.
	 *
	 * @param string $w3tc_key The key of the counter to retrieve.
	 *
	 * @return int The current counter value, or 0 if the counter does not exist.
	 */
	public function counter_get( $w3tc_key ) {
		$storage_key = $this->get_item_key( $w3tc_key );
		$v           = (int) xcache_get( $storage_key );

		return $v;
	}
}
