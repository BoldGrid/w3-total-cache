<?php
/**
 * File: Cache_Memcache.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cache_Memcache
 *
 * PECL Memcache class
 * Older than Memcached
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable Universal.CodeAnalysis.ConstructorDestructorReturn.ReturnValueFound
 */
class Cache_Memcache extends Cache_Base {
	/**
	 * Memcache object
	 *
	 * @var Memcache
	 */
	private $_memcache = null;

	/**
	 * Used for faster flushing
	 *
	 * @var integer $_key_version
	 */
	private $_key_version = array();

	/**
	 * Cache_Memcache constructor.
	 *
	 * Initializes the Memcache connection and sets up servers based on the provided configuration.
	 *
	 * @param array $config {
	 *     Configuration for Memcache, including.
	 *
	 *     @type array  $servers          List of Memcache server endpoints.
	 *     @type bool   $persistent       Whether to use persistent connections.
	 *     @type string $key_version_mode Mode for key versioning ('disabled' to disable it).
	 * }
	 */
	public function __construct( $config ) {
		parent::__construct( $config );

		$this->_memcache = new \Memcache();

		if ( ! empty( $config['servers'] ) ) {
			$persistent = isset( $config['persistent'] ) ? (bool) $config['persistent'] : false;

			foreach ( (array) $config['servers'] as $server ) {
				list( $ip, $port ) = Util_Content::endpoint_to_host_port( $server );
				$this->_memcache->addServer( $ip, $port, $persistent );
			}
		} else {
			return false;
		}

		// when disabled - no extra requests are made to obtain key version, but flush operations not supported as a result
		// group should be always empty.
		if ( isset( $config['key_version_mode'] ) && 'disabled' === $config['key_version_mode'] ) {
			$this->_key_version[''] = 1;
		}

		return true;
	}

	/**
	 * Adds a new value to the cache.
	 *
	 * If the key already exists, it will overwrite the value. This method is functionally equivalent to `set()`.
	 *
	 * @param string $key    Cache key.
	 * @param mixed  $value  Value to store.
	 * @param int    $expire Time to live for the cached item in seconds. Default is 0 (no expiration).
	 * @param string $group  Cache group. Default is an empty string.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function add( $key, &$value, $expire = 0, $group = '' ) {
		return $this->set( $key, $value, $expire, $group );
	}

	/**
	 * Sets a value in the cache.
	 *
	 * This method stores the value in Memcache with a specific key, expiration time, and group. It also includes a versioning
	 * mechanism to handle key updates.
	 *
	 * @param string $key    Cache key.
	 * @param mixed  $value  Value to store.
	 * @param int    $expire Time to live for the cached item in seconds. Default is 0 (no expiration).
	 * @param string $group  Cache group. Default is an empty string.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function set( $key, $value, $expire = 0, $group = '' ) {
		if ( ! isset( $value['key_version'] ) ) {
			$value['key_version'] = $this->_get_key_version( $group );
		}

		$storage_key = $this->get_item_key( $key );

		return @$this->_memcache->set( $storage_key, $value, false, $expire );
	}

	/**
	 * Retrieves a value and its old version from the cache.
	 *
	 * This method fetches the cached value for the given key. If the key version does not match the current version, it may return
	 * expired data based on configuration.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group. Default is an empty string.
	 *
	 * @return array Array containing the value (or null) and a boolean indicating if old data was returned.
	 */
	public function get_with_old( $key, $group = '' ) {
		$has_old_data = false;

		$storage_key = $this->get_item_key( $key );

		$v = @$this->_memcache->get( $storage_key );
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
			@$this->_memcache->set( $storage_key, $v, false, 0 );
			$has_old_data = true;

			return array( null, $has_old_data );
		}

		// return old version.
		return array( $v, $has_old_data );
	}

	/**
	 * Replaces the value for an existing key in the cache.
	 *
	 * This method is functionally equivalent to `set()`.
	 *
	 * @param string $key    Cache key.
	 * @param mixed  $value  Value to store.
	 * @param int    $expire Time to live for the cached item in seconds. Default is 0 (no expiration).
	 * @param string $group  Cache group. Default is an empty string.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function replace( $key, &$value, $expire = 0, $group = '' ) {
		return $this->set( $key, $value, $expire, $group );
	}

	/**
	 * Deletes a value from the cache.
	 *
	 * If expired data is allowed, it sets the key version to 0 and updates the cache. Otherwise, it removes the key completely.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group. Default is an empty string.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function delete( $key, $group = '' ) {
		$storage_key = $this->get_item_key( $key );

		if ( $this->_use_expired_data ) {
			$v = @$this->_memcache->get( $storage_key );
			if ( is_array( $v ) ) {
				$v['key_version'] = 0;
				@$this->_memcache->set( $storage_key, $v, false, 0 );
				return true;
			}
		}
		return @$this->_memcache->delete( $storage_key, 0 );
	}

	/**
	 * Deletes a key and its value from the cache without considering versioning.
	 *
	 * @param string $key   Cache key to delete.
	 * @param string $group Cache group. Default is an empty string.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function hard_delete( $key, $group = '' ) {
		$storage_key = $this->get_item_key( $key );
		return @$this->_memcache->delete( $storage_key, 0 );
	}

	/**
	 * Flushes the cache for the specified group by incrementing its key version.
	 *
	 * @param string $group Cache group. Default is an empty string.
	 *
	 * @return bool Always returns true.
	 */
	public function flush( $group = '' ) {
		$this->_increment_key_version( $group );
		return true;
	}

	/**
	 * Prepares an ahead-generation extension for cache key versioning.
	 *
	 * Used to create a new version of a cache key for precomputing or updating data.
	 *
	 * @param string $group Cache group.
	 *
	 * @return array Associative array with:
	 *               - 'key_version' (int): The new key version.
	 *               - 'key_version_at_creation' (int): The current key version.
	 */
	public function get_ahead_generation_extension( $group ) {
		$v = $this->_get_key_version( $group );
		return array(
			'key_version'             => $v + 1,
			'key_version_at_creation' => $v,
		);
	}

	/**
	 * Updates the cache key version after an ahead-generation operation.
	 *
	 * @param string $group Cache group.
	 * @param array  $extension {
	 *     The extension data with the new key version.
	 *
	 *     @type string $key_version The new cache key version.
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
	 * Checks if the Memcache extension is available.
	 *
	 * @return bool True if Memcache is available, false otherwise.
	 */
	public function available() {
		return class_exists( 'Memcache' );
	}

	/**
	 * Retrieves statistics about the Memcache instance.
	 *
	 * @return array|false An associative array of Memcache statistics, or false on failure.
	 */
	public function get_statistics() {
		return $this->_memcache->getStats();
	}

	/**
	 * Gets the current key version for a cache group.
	 *
	 * @param string $group Cache group. Default is an empty string.
	 *
	 * @return int The current key version.
	 */
	private function _get_key_version( $group = '' ) {
		if ( ! isset( $this->_key_version[ $group ] ) || $this->_key_version[ $group ] <= 0 ) {
			$v = @$this->_memcache->get( $this->_get_key_version_key( $group ) );
			$v = intval( $v );

			$this->_key_version[ $group ] = ( $v > 0 ? $v : 1 );
		}

		return $this->_key_version[ $group ];
	}

	/**
	 * Sets a new key version for a cache group.
	 *
	 * @param int    $v     The new key version.
	 * @param string $group Cache group. Default is an empty string.
	 *
	 * @return void
	 */
	private function _set_key_version( $v, $group = '' ) {
		// expiration has to be as long as possible since all cache data expires when key version expires.
		@$this->_memcache->set( $this->_get_key_version_key( $group ), $v, false, 0 );
		$this->_key_version[ $group ] = $v;
	}

	/**
	 * Increments the key version for a cache group.
	 *
	 * If the key does not exist, initializes it with version 2.
	 *
	 * @since 0.14.5
	 *
	 * @param string $group Cache group. Default is an empty string.
	 *
	 * @return void
	 */
	private function _increment_key_version( $group = '' ) {
		$r = @$this->_memcache->increment( $this->_get_key_version_key( $group ), 1 );

		if ( $r ) {
			$this->_key_version[ $group ] = $r;
		} else {
			// it doesn't initialize the key if it doesn't exist.
			$this->_set_key_version( 2, $group );
		}
	}

	/**
	 * Retrieves the size and item count of the cache.
	 *
	 * This method collects statistics about the size of the cache and the number of items stored in it.
	 *
	 * @param int $timeout_time The timeout duration for retrieving stats.
	 *
	 * @return array Associative array with:
	 *               - 'bytes' (int): Total size of the cached items in bytes.
	 *               - 'items' (int): Total number of cached items.
	 *               - 'timeout_occurred' (bool): Whether a timeout occurred while retrieving stats.
	 */
	public function get_stats_size( $timeout_time ) {
		$size = array(
			'bytes'            => 0,
			'items'            => 0,
			'timeout_occurred' => false,
		);

		$key_prefix = $this->get_item_key( '' );

		$slabs       = @$this->_memcache->getExtendedStats( 'slabs' );
		$slabs_plain = array();

		if ( is_array( $slabs ) ) {
			foreach ( $slabs as $server => $server_slabs ) {
				foreach ( $server_slabs as $slab_id => $slab_meta ) {
					if ( (int) $slab_id > 0 ) {
						$slabs_plain[ (int) $slab_id ] = '*';
					}
				}
			}
		}

		foreach ( $slabs_plain as $slab_id => $nothing ) {
			$cdump = @$this->_memcache->getExtendedStats( 'cachedump', (int) $slab_id );
			if ( ! is_array( $cdump ) ) {
				continue;
			}

			foreach ( $cdump as $server => $keys_data ) {
				if ( ! is_array( $keys_data ) ) {
					continue;
				}

				foreach ( $keys_data as $key => $size_expiration ) {
					if ( substr( $key, 0, strlen( $key_prefix ) ) === $key_prefix ) {
						if ( count( $size_expiration ) > 0 ) {
							$size['bytes'] += $size_expiration[0];
							++$size['items'];
						}
					}
				}
			}
		}

		return $size;
	}

	/**
	 * Conditionally sets a new value if the current value matches the old value.
	 *
	 * Since Memcache does not support Compare-And-Swap (CAS), atomicity cannot be guaranteed.
	 *
	 * @param string $key       The cache key.
	 * @param array  $old_value {
	 *     The expected old value.
	 *
	 *     @type mixed $content The content to match against.
	 * }
	 * @param array  $new_value The new value to set if the old value matches.
	 *
	 * @return bool True if the value was set, false otherwise.
	 */
	public function set_if_maybe_equals( $key, $old_value, $new_value ) {
		// cant guarantee atomic action here, memcache doesnt support CAS.
		$value = $this->get( $key );
		if ( isset( $old_value['content'] ) && $value['content'] !== $old_value['content'] ) {
			return false;
		}

		return $this->set( $key, $new_value );
	}

	/**
	 * Adds a value to a counter stored in the cache.
	 *
	 * If the counter does not exist, it initializes the counter to 0 before adding the value.
	 *
	 * @param string $key   The cache key for the counter.
	 * @param int    $value The value to add to the counter. If 0, no changes are made.
	 *
	 * @return int|bool The new counter value on success, or false on failure.
	 */
	public function counter_add( $key, $value ) {
		if ( 0 === $value ) {
			return true;
		}

		$storage_key = $this->get_item_key( $key );
		$r           = @$this->_memcache->increment( $storage_key, $value );
		if ( ! $r ) { // it doesnt initialize counter by itself.
			$this->counter_set( $key, 0 );
		}

		return $r;
	}

	/**
	 * Sets a counter to a specific value.
	 *
	 * @param string $key   The cache key for the counter.
	 * @param int    $value The value to set for the counter.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function counter_set( $key, $value ) {
		$storage_key = $this->get_item_key( $key );
		return @$this->_memcache->set( $storage_key, $value );
	}

	/**
	 * Retrieves the current value of a counter stored in the cache.
	 *
	 * @param string $key The cache key for the counter.
	 *
	 * @return int The counter value. Returns 0 if the counter does not exist.
	 */
	public function counter_get( $key ) {
		$storage_key = $this->get_item_key( $key );
		$v           = (int) @$this->_memcache->get( $storage_key );

		return $v;
	}

	/**
	 * Generates a unique storage key for a given cache item.
	 *
	 * The key includes the instance ID, host, blog ID, module, and an MD5 hash of the item name. Spaces in the name are sanitized
	 * to ensure compatibility with Memcache.
	 *
	 * @param string $name The name of the cache item.
	 *
	 * @return string The unique storage key for the cache item.
	 */
	public function get_item_key( $name ) {
		// memcached doesn't survive spaces in a key.
		$key = sprintf( 'w3tc_%d_%s_%d_%s_%s', $this->_instance_id, $this->_host, $this->_blog_id, $this->_module, md5( $name ) );
		return $key;
	}
}
