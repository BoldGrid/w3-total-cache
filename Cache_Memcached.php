<?php
/**
 * File: Cache_Memcached.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cache_Memcached
 *
 * PECL Memcached class
 * Preferred upon Memcache
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable Universal.CodeAnalysis.ConstructorDestructorReturn.ReturnValueFound
 */
class Cache_Memcached extends Cache_Base {
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
	 * Configuration used to reinitialize persistent object
	 *
	 * @var integer $_key_version
	 */
	private $_config = null;

	/**
	 * Constructor to initialize the Memcached client with provided configuration.
	 *
	 * @param array $config {
	 *     Configuration settings for the Memcached client, including server details, options, and credentials.
	 *
	 *     @type bool $persistent Whether to use persistent connections.
	 * }
	 *
	 * @return bool True on successful initialization, false on failure.
	 */
	public function __construct( $config ) {
		parent::__construct( $config );

		if ( isset( $config['persistent'] ) && $config['persistent'] ) {
			$this->_config   = $config;
			$this->_memcache = new \Memcached( $this->_get_key_version_key( '' ) );
			$server_list     = $this->_memcache->getServerList();

			if ( empty( $server_list ) ) {
				return $this->initialize( $config );
			} else {
				return true;
			}
		} else {
			$this->_memcache = new \Memcached();
			return $this->initialize( $config );
		}
	}

	/**
	 * Initializes the Memcached client with the given configuration.
	 *
	 * @param array $config {
	 *     Configuration settings for the Memcached client, including server details, options, and credentials.
	 *
	 *     @type array|string $servers           List of Memcached server endpoints (host:port).
	 *     @type bool         $binary_protocol   Enable binary protocol if true.
	 *     @type bool         $aws_autodiscovery Enable AWS autodiscovery if true.
	 *     @type string       $username          Username for SASL authentication.
	 *     @type string       $password          Password for SASL authentication.
	 *     @type string       $key_version_mode  Key versioning mode (disabled to skip versioning).
	 * }
	 *
	 * @return bool True on successful initialization, false on failure.
	 */
	private function initialize( $config ) {
		if ( empty( $config['servers'] ) ) {
			return false;
		}

		if ( defined( '\Memcached::OPT_REMOVE_FAILED_SERVERS' ) ) {
			$this->_memcache->setOption( \Memcached::OPT_REMOVE_FAILED_SERVERS, true );
		}

		if ( isset( $config['binary_protocol'] ) && ! empty( $config['binary_protocol'] ) && defined( '\Memcached::OPT_BINARY_PROTOCOL' ) ) {
			$this->_memcache->setOption( \Memcached::OPT_BINARY_PROTOCOL, true );
		}

		if ( defined( '\Memcached::OPT_TCP_NODELAY' ) ) {
			$this->_memcache->setOption( \Memcached::OPT_TCP_NODELAY, true );
		}

		if (
			isset( $config['aws_autodiscovery'] ) &&
			$config['aws_autodiscovery'] &&
			defined( '\Memcached::OPT_CLIENT_MODE' ) &&
			defined( '\Memcached::DYNAMIC_CLIENT_MODE' )
		) {
			$this->_memcache->setOption( \Memcached::OPT_CLIENT_MODE, \Memcached::DYNAMIC_CLIENT_MODE );
		}

		foreach ( (array) $config['servers'] as $server ) {
			list( $ip, $port ) = Util_Content::endpoint_to_host_port( $server );
			$this->_memcache->addServer( $ip, $port );
		}

		if ( isset( $config['username'] ) && ! empty( $config['username'] ) && method_exists( $this->_memcache, 'setSaslAuthData' ) ) {
			$this->_memcache->setSaslAuthData( $config['username'], $config['password'] );
		}

		// when disabled - no extra requests are made to obtain key version, but flush operations not supported as a result
		// group should be always empty.
		if ( isset( $config['key_version_mode'] ) && 'disabled' === $config['key_version_mode'] ) {
			$this->_key_version[''] = 1;
		}

		return true;
	}

	/**
	 * Adds a new key-value pair to the Memcached server, or updates it if it already exists.
	 *
	 * @param string $key    The key under which the data will be stored.
	 * @param mixed  $value  The data to store.
	 * @param int    $expire The expiration time in seconds (default is 0 for no expiration).
	 * @param string $group  The group to which the item belongs (default is an empty string).
	 *
	 * @return bool True on success, false on failure.
	 */
	public function add( $key, &$value, $expire = 0, $group = '' ) {
		return $this->set( $key, $value, $expire, $group );
	}

	/**
	 * Sets a key-value pair in the Memcached server, or updates it if it already exists.
	 *
	 * @param string $key    The key under which the data will be stored.
	 * @param mixed  $value  The data to store.
	 * @param int    $expire The expiration time in seconds (default is 0 for no expiration).
	 * @param string $group  The group to which the item belongs (default is an empty string).
	 *
	 * @return bool True on success, false on failure.
	 */
	public function set( $key, $value, $expire = 0, $group = '' ) {
		if ( ! isset( $value['key_version'] ) ) {
			$value['key_version'] = $this->_get_key_version( $group );
		}

		$storage_key = $this->get_item_key( $key );

		return @$this->_memcache->set( $storage_key, $value, $expire );
	}

	/**
	 * Retrieves a value from Memcached along with a flag indicating if old data was found.
	 *
	 * Checks for data associated with the given key and ensures the version is up-to-date.
	 *
	 * @param string $key   The key to fetch from the Memcached server.
	 * @param string $group The group the item belongs to (default is an empty string).
	 *
	 * @return array An array containing the fetched data (or null) and a flag indicating whether old data was returned.
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
			@$this->_memcache->set( $storage_key, $v, 0 );
			$has_old_data = true;

			return array( null, $has_old_data );
		}

		// return old version.
		return array( $v, $has_old_data );
	}

	/**
	 * Replaces an existing key-value pair in Memcached if it already exists.
	 *
	 * @param string $key    The key under which the data will be stored.
	 * @param mixed  $value  The data to store.
	 * @param int    $expire The expiration time in seconds (default is 0 for no expiration).
	 * @param string $group  The group to which the item belongs (default is an empty string).
	 *
	 * @return bool True on success, false on failure.
	 */
	public function replace( $key, &$value, $expire = 0, $group = '' ) {
		return $this->set( $key, $value, $expire, $group );
	}

	/**
	 * Deletes a key-value pair from Memcached.
	 *
	 * @param string $key   The key to delete from the Memcached server.
	 * @param string $group The group the item belongs to (default is an empty string).
	 *
	 * @return bool True on success, false on failure.
	 */
	public function delete( $key, $group = '' ) {
		$storage_key = $this->get_item_key( $key );

		if ( $this->_use_expired_data ) {
			$v = @$this->_memcache->get( $storage_key );
			if ( is_array( $v ) ) {
				$v['key_version'] = 0;
				@$this->_memcache->set( $storage_key, $v, 0 );
				return true;
			}
		}
		return @$this->_memcache->delete( $storage_key );
	}

	/**
	 * Deletes an item from the Memcached storage.
	 *
	 * @param string $key   The cache key to delete.
	 * @param string $group The cache group. Default is an empty string.
	 *
	 * @return bool True if the item was successfully deleted, false otherwise.
	 */
	public function hard_delete( $key, $group = '' ) {
		$storage_key = $this->get_item_key( $key );
		return @$this->_memcache->delete( $storage_key );
	}

	/**
	 * Flushes all items in the Memcached storage.
	 *
	 * This method resets the server list if persistent connections are used, and reinitializes the Memcached object with the new
	 * configuration.
	 *
	 * @param string $group The cache group to flush. Default is an empty string.
	 *
	 * @return bool Always returns true.
	 */
	public function flush( $group = '' ) {
		$this->_increment_key_version( $group );

		// for persistent connections - apply new config to the object otherwise it will keep old servers list.
		if ( ! is_null( $this->_config ) ) {
			if ( method_exists( $this->_memcache, 'resetServerList' ) ) {
				$this->_memcache->resetServerList();
			}

			$this->initialize( $this->_config );
		}

		return true;
	}

	/**
	 * Returns an array containing the key version and its version at creation for the specified group.
	 *
	 * @param string $group The cache group for which to retrieve version information.
	 *
	 * @return array Associative array containing 'key_version' and 'key_version_at_creation'.
	 */
	public function get_ahead_generation_extension( $group ) {
		$v = $this->_get_key_version( $group );
		return array(
			'key_version'             => $v + 1,
			'key_version_at_creation' => $v,
		);
	}

	/**
	 * Updates the key version for a given cache group after ahead generation.
	 *
	 * If the provided extension's key version is greater than the current version, it updates the key version.
	 *
	 * @param string $group The cache group to update.
	 * @param array  $extension {
	 *     The extension data containing the new key version.
	 *
	 *     @type string $key_version The new key version to set.
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
	 * Checks if Memcached is available for use.
	 *
	 * @return bool True if the Memcached class exists and is available, false otherwise.
	 */
	public function available() {
		return class_exists( 'Memcached' );
	}

	/**
	 * Retrieves Memcached statistics.
	 *
	 * @return array|false Memcached statistics if available, false otherwise.
	 */
	public function get_statistics() {
		$a = $this->_memcache->getStats();
		if ( ! empty( $a ) && count( $a ) > 0 ) {
			$keys = array_keys( $a );
			$key  = $keys[0];
			return $a[ $key ];
		}

		return $a;
	}

	/**
	 * Retrieves the current key version for a specific cache group.
	 *
	 * If the version is not set or is invalid, it attempts to fetch it from Memcached.
	 *
	 * @param string $group The cache group to get the key version for. Default is an empty string.
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
	 * Sets the key version for a specific cache group.
	 *
	 * The expiration is set to 0, which means the version does not expire.
	 *
	 * @param int    $v      The key version to set.
	 * @param string $group The cache group to set the version for. Default is an empty string.
	 *
	 * @return void
	 */
	private function _set_key_version( $v, $group = '' ) {
		// expiration has to be as long as possible since all cache data expires when key version expires.
		@$this->_memcache->set( $this->_get_key_version_key( $group ), $v, 0 );
		$this->_key_version[ $group ] = $v;
	}

	/**
	 * Increments the key version for a specific cache group.
	 *
	 * This method attempts to increment the version in Memcached. If the key does not exist, it sets the version to 2 to initialize it.
	 *
	 * @since 0.14.5
	 *
	 * @param string $group The cache group to increment the version for. Default is an empty string.
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
	 * Retrieves cache statistics size, including the total number of items and bytes.
	 *
	 * It checks for timeouts and returns statistics based on the cache server list.
	 *
	 * @param int $timeout_time The timestamp to check if the timeout has occurred.
	 *
	 * @return array An associative array containing 'bytes', 'items', and 'timeout_occurred'.
	 */
	public function get_stats_size( $timeout_time ) {
		$size = array(
			'bytes'            => 0,
			'items'            => 0,
			'timeout_occurred' => false,
		);

		$key_prefix     = $this->get_item_key( '' );
		$error_occurred = false;

		$server_list = $this->_memcache->getServerList();
		$n           = 0;

		foreach ( $server_list as $server ) {
			$loader = new Cache_Memcached_Stats( $server['host'], $server['port'] );
			$slabs  = $loader->slabs();
			if ( ! is_array( $slabs ) ) {
				$error_occurred = true;
				continue;
			}

			foreach ( $slabs as $slab_id ) {
				$cdump = $loader->cachedump( $slab_id );
				if ( ! is_array( $cdump ) ) {
					continue;
				}

				foreach ( $cdump as $line ) {
					$key_data = explode( ' ', $line );
					if ( ! is_array( $key_data ) || count( $key_data ) < 3 ) {
						continue;
					}

					++$n;
					if ( 0 === $n % 10 ) {
						$size['timeout_occurred'] = ( time() > $timeout_time );
						if ( $size['timeout_occurred'] ) {
							return $size;
						}
					}

					$key   = $key_data[1];
					$bytes = substr( $key_data[2], 1 );

					if ( substr( $key, 0, strlen( $key_prefix ) ) === $key_prefix ) {
						$size['bytes'] += $bytes;
						++$size['items'];
					}
				}
			}
		}

		if ( $error_occurred && $size['items'] <= 0 ) {
			$size['bytes'] = null;
			$size['items'] = null;
		}

		return $size;
	}

	/**
	 * Sets a new value for a cache item if the current value matches the specified old value.
	 *
	 * This method uses CAS (Compare and Swap) to atomically update the cache item.
	 *
	 * @param string $key       The cache key to update.
	 * @param array  $old_value {
	 *     The current value to compare against.
	 *
	 *     @type mixed $content The content to compare in the old value.
	 * }
	 * @param array  $new_value The new value to set if the old value matches.
	 *
	 * @return bool True if the item was updated, false otherwise.
	 */
	public function set_if_maybe_equals( $key, $old_value, $new_value ) {
		$storage_key = $this->get_item_key( $key );

		$cas   = null;
		$value = @$this->_memcache->get( $storage_key, null, $cas );

		if ( ! is_array( $value ) ) {
			return false;
		}

		if ( isset( $old_value['content'] ) && $value['content'] !== $old_value['content'] ) {
			return false;
		}

		return @$this->_memcache->cas( $cas, $storage_key, $new_value );
	}

	/**
	 * Increments a counter in the cache by the specified value.
	 *
	 * If the counter does not exist, it initializes it with 0.
	 *
	 * @param string $key   The cache key for the counter.
	 * @param int    $value The value to add to the counter.
	 *
	 * @return bool True if the counter was incremented successfully, false otherwise.
	 */
	public function counter_add( $key, $value ) {
		if ( 0 === $value ) {
			return true;
		}

		$storage_key = $this->get_item_key( $key );
		$r           = $this->_memcache->increment( $storage_key, $value, 0, 3600 );
		if ( ! $r ) { // it doesnt initialize counter by itself.
			$this->counter_set( $key, 0 );
		}

		return $r;
	}

	/**
	 * Sets a counter value in the Memcached storage.
	 *
	 * This method sets a specified value for a given key in the Memcached storage. If the key doesn't already exist, it initializes
	 * it with the provided value. This is typically used to set counters or similar numeric values that need to be stored persistently.
	 *
	 * @param string $key   The key under which the counter is stored.
	 * @param int    $value The value to set for the counter.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function counter_set( $key, $value ) {
		$storage_key = $this->get_item_key( $key );
		return @$this->_memcache->set( $storage_key, $value );
	}

	/**
	 * Retrieves the counter value from Memcached storage.
	 *
	 * This method fetches the stored counter value for the given key. If the key doesn't exist or the value is not a valid integer,
	 * it returns 0. This is typically used to retrieve counters or similar numeric values stored persistently.
	 *
	 * @param string $key The key under which the counter is stored.
	 *
	 * @return int The current counter value, or 0 if the key does not exist or is invalid.
	 */
	public function counter_get( $key ) {
		$storage_key = $this->get_item_key( $key );
		$v           = (int) @$this->_memcache->get( $storage_key );

		return $v;
	}

	/**
	 * Generates a unique Memcached key for the given item name.
	 *
	 * This method generates a unique key for an item to be stored in Memcached. The key is constructed using various instance-specific
	 * properties (e.g., instance ID, host, blog ID, module) combined with the md5 hash of the item name. This ensures that keys are
	 * unique and can safely be used in a multi-instance or multi-module environment.
	 *
	 * @param string $name The item name to generate a key for.
	 *
	 * @return string The generated unique Memcached key.
	 */
	public function get_item_key( $name ) {
		// memcached doesn't survive spaces in a key.
		$key = sprintf( 'w3tc_%d_%s_%d_%s_%s', $this->_instance_id, $this->_host, $this->_blog_id, $this->_module, md5( $name ) );
		return $key;
	}
}
