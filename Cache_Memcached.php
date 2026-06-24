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
	 * @param array $w3tc_config {
	 *     Configuration settings for the Memcached client, including server details, options, and credentials.
	 *
	 *     @type bool $persistent Whether to use persistent connections.
	 * }
	 *
	 * @return bool True on successful initialization, false on failure.
	 */
	public function __construct( $w3tc_config ) {
		parent::__construct( $w3tc_config );

		if ( isset( $w3tc_config['persistent'] ) && $w3tc_config['persistent'] ) {
			$this->_config   = $w3tc_config;
			$this->_memcache = new \Memcached( $this->_get_key_version_key( '' ) );
			$server_list     = $this->_memcache->getServerList();

			if ( empty( $server_list ) ) {
				return $this->initialize( $w3tc_config );
			} else {
				return true;
			}
		} else {
			$this->_memcache = new \Memcached();
			return $this->initialize( $w3tc_config );
		}
	}

	/**
	 * Initializes the Memcached client with the given configuration.
	 *
	 * @param array $w3tc_config {
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
	private function initialize( $w3tc_config ) {
		if ( empty( $w3tc_config['servers'] ) ) {
			return false;
		}

		if ( defined( '\Memcached::OPT_REMOVE_FAILED_SERVERS' ) ) {
			$this->_memcache->setOption( \Memcached::OPT_REMOVE_FAILED_SERVERS, true );
		}

		if ( isset( $w3tc_config['binary_protocol'] ) && ! empty( $w3tc_config['binary_protocol'] ) && defined( '\Memcached::OPT_BINARY_PROTOCOL' ) ) {
			$this->_memcache->setOption( \Memcached::OPT_BINARY_PROTOCOL, true );
		}

		if ( defined( '\Memcached::OPT_TCP_NODELAY' ) ) {
			$this->_memcache->setOption( \Memcached::OPT_TCP_NODELAY, true );
		}

		if (
			isset( $w3tc_config['aws_autodiscovery'] ) &&
			$w3tc_config['aws_autodiscovery'] &&
			defined( '\Memcached::OPT_CLIENT_MODE' ) &&
			defined( '\Memcached::DYNAMIC_CLIENT_MODE' )
		) {
			$this->_memcache->setOption( \Memcached::OPT_CLIENT_MODE, \Memcached::DYNAMIC_CLIENT_MODE );
		}

		foreach ( (array) $w3tc_config['servers'] as $server ) {
			list( $ip, $port ) = Util_Content::endpoint_to_host_port( $server );

			// For unix sockets, php-memcached expects the socket path as host and port 0.
			// Users may configure servers like "unix:/var/run/memcached/memcached.sock".
			if ( 0 === (int) $port && ( 0 === strpos( $ip, 'unix:' ) || false !== strpos( $ip, '/' ) ) ) {
				$ip = preg_replace( '#^unix:(/*)#', '/', $ip );
				if ( '/' !== substr( $ip, 0, 1 ) ) {
					$ip = '/' . $ip;
				}
				$port = 0;
			}

			$this->_memcache->addServer( $ip, $port );
		}

		if ( isset( $w3tc_config['username'] ) && ! empty( $w3tc_config['username'] ) && method_exists( $this->_memcache, 'setSaslAuthData' ) ) {
			$this->_memcache->setSaslAuthData( $w3tc_config['username'], $w3tc_config['password'] );
		}

		// when disabled - no extra requests are made to obtain key version, but flush operations not supported as a result
		// group should be always empty.
		if ( isset( $w3tc_config['key_version_mode'] ) && 'disabled' === $w3tc_config['key_version_mode'] ) {
			$this->_key_version[''] = 1;
		}

		return true;
	}

	/**
	 * Adds a new key-value pair to the Memcached server, or updates it if it already exists.
	 *
	 * @param string $w3tc_key    The key under which the data will be stored.
	 * @param mixed  $w3tc_value  The data to store.
	 * @param int    $expire The expiration time in seconds (default is 0 for no expiration).
	 * @param string $w3tc_group  The group to which the item belongs (default is an empty string).
	 *
	 * @return bool True on success, false on failure.
	 */
	public function add( $w3tc_key, &$w3tc_value, $expire = 0, $w3tc_group = '' ) {
		return $this->set( $w3tc_key, $w3tc_value, $expire, $w3tc_group );
	}

	/**
	 * Sets a key-value pair in the Memcached server, or updates it if it already exists.
	 *
	 * @param string $w3tc_key    The key under which the data will be stored.
	 * @param mixed  $w3tc_value  The data to store.
	 * @param int    $expire The expiration time in seconds (default is 0 for no expiration).
	 * @param string $w3tc_group  The group to which the item belongs (default is an empty string).
	 *
	 * @return bool True on success, false on failure.
	 */
	public function set( $w3tc_key, $w3tc_value, $expire = 0, $w3tc_group = '' ) {
		if ( ! isset( $w3tc_value['key_version'] ) ) {
			$w3tc_value['key_version'] = $this->_get_key_version( $w3tc_group );
		}

		$storage_key = $this->get_item_key( $w3tc_key );

		return @$this->_memcache->set( $storage_key, $w3tc_value, $expire );
	}

	/**
	 * Retrieves a value from Memcached along with a flag indicating if old data was found.
	 *
	 * Checks for data associated with the given key and ensures the version is up-to-date.
	 *
	 * @param string $w3tc_key   The key to fetch from the Memcached server.
	 * @param string $w3tc_group The group the item belongs to (default is an empty string).
	 *
	 * @return array An array containing the fetched data (or null) and a flag indicating whether old data was returned.
	 */
	public function get_with_old( $w3tc_key, $w3tc_group = '' ) {
		$has_old_data = false;

		$storage_key = $this->get_item_key( $w3tc_key );

		$v = @$this->_memcache->get( $storage_key );
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
	 * @param string $w3tc_key    The key under which the data will be stored.
	 * @param mixed  $w3tc_value  The data to store.
	 * @param int    $expire The expiration time in seconds (default is 0 for no expiration).
	 * @param string $w3tc_group  The group to which the item belongs (default is an empty string).
	 *
	 * @return bool True on success, false on failure.
	 */
	public function replace( $w3tc_key, &$w3tc_value, $expire = 0, $w3tc_group = '' ) {
		return $this->set( $w3tc_key, $w3tc_value, $expire, $w3tc_group );
	}

	/**
	 * Retrieves multiple cached values in a single request.
	 *
	 * @since 2.9.0
	 *
	 * @param array  $w3tc_keys  Cache keys.
	 * @param string $w3tc_group Cache group.
	 *
	 * @return array Map of cache key => cached payload (or null).
	 */
	public function get_multi( array $w3tc_keys, $w3tc_group = '' ) {
		if ( empty( $w3tc_keys ) ) {
			return array();
		}

		$storage_keys = array();
		foreach ( $w3tc_keys as $w3tc_key ) {
			$storage_keys[] = $this->get_item_key( $w3tc_key );
		}

		$values         = null;
		$preserve_order = false;

		if ( defined( '\Memcached::GET_PRESERVE_ORDER' ) ) {
			$values         = @$this->_memcache->getMulti( $storage_keys, \Memcached::GET_PRESERVE_ORDER );
			$preserve_order = true;
		}

		if ( ! is_array( $values ) ) {
			$values         = @$this->_memcache->getMulti( $storage_keys );
			$preserve_order = false;
		}

		$results = array();
		foreach ( $w3tc_keys as $w3tc_i => $w3tc_key ) {
			$storage_key = $storage_keys[ $w3tc_i ];

			if ( isset( $values[ $storage_key ] ) ) {
				$results[ $w3tc_key ] = $values[ $storage_key ];
			} elseif ( $preserve_order && isset( $values[ $w3tc_i ] ) ) {
				$results[ $w3tc_key ] = $values[ $w3tc_i ];
			} else {
				$results[ $w3tc_key ] = null;
			}
		}

		return $results;
	}

	/**
	 * Stores multiple values in a single request.
	 *
	 * @since 2.9.0
	 *
	 * @param array  $items  Map of cache key => payload.
	 * @param string $w3tc_group  Cache group.
	 * @param int    $expire Expiration.
	 *
	 * @return array Map of cache key => success boolean.
	 */
	public function set_multi( array $items, $w3tc_group = '', $expire = 0 ) {
		if ( empty( $items ) ) {
			return array();
		}

		$key_version = $this->_get_key_version( $w3tc_group );
		$payload     = array();

		foreach ( $items as $w3tc_key => $w3tc_value ) {
			if ( ! isset( $w3tc_value['key_version'] ) ) {
				$w3tc_value['key_version'] = $key_version;
			}

			$payload[ $this->get_item_key( $w3tc_key ) ] = $w3tc_value;
		}

		$ok      = @$this->_memcache->setMulti( $payload, $expire );
		$results = array();

		foreach ( $items as $w3tc_key => $_ ) {
			$results[ $w3tc_key ] = (bool) $ok;
		}

		return $results;
	}

	/**
	 * Deletes a key-value pair from Memcached.
	 *
	 * @param string $w3tc_key   The key to delete from the Memcached server.
	 * @param string $w3tc_group The group the item belongs to (default is an empty string).
	 *
	 * @return bool True on success, false on failure.
	 */
	public function delete( $w3tc_key, $w3tc_group = '' ) {
		$storage_key = $this->get_item_key( $w3tc_key );

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
	 * @param string $w3tc_key   The cache key to delete.
	 * @param string $w3tc_group The cache group. Default is an empty string.
	 *
	 * @return bool True if the item was successfully deleted, false otherwise.
	 */
	public function hard_delete( $w3tc_key, $w3tc_group = '' ) {
		$storage_key = $this->get_item_key( $w3tc_key );
		return @$this->_memcache->delete( $storage_key );
	}

	/**
	 * Flushes all items in the Memcached storage.
	 *
	 * This method resets the server list if persistent connections are used, and reinitializes the Memcached object with the new
	 * configuration.
	 *
	 * @param string $w3tc_group The cache group to flush. Default is an empty string.
	 *
	 * @return bool Always returns true.
	 */
	public function flush( $w3tc_group = '' ) {
		$this->_increment_key_version( $w3tc_group );

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
	 * @param string $w3tc_group The cache group for which to retrieve version information.
	 *
	 * @return array Associative array containing 'key_version' and 'key_version_at_creation'.
	 */
	public function get_ahead_generation_extension( $w3tc_group ) {
		$v = $this->_get_key_version( $w3tc_group );
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
	 * @param string $w3tc_group The cache group to update.
	 * @param array  $w3tc_extension {
	 *     The extension data containing the new key version.
	 *
	 *     @type string $key_version The new key version to set.
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
		$w3tc_a = $this->_memcache->getStats();
		if ( ! empty( $w3tc_a ) && count( $w3tc_a ) > 0 ) {
			$w3tc_keys = array_keys( $w3tc_a );
			$w3tc_key  = $w3tc_keys[0];
			return $w3tc_a[ $w3tc_key ];
		}

		return $w3tc_a;
	}

	/**
	 * Retrieves the current key version for a specific cache group.
	 *
	 * If the version is not set or is invalid, it attempts to fetch it from Memcached.
	 *
	 * @param string $w3tc_group The cache group to get the key version for. Default is an empty string.
	 *
	 * @return int The current key version.
	 */
	private function _get_key_version( $w3tc_group = '' ) {
		if ( ! isset( $this->_key_version[ $w3tc_group ] ) || $this->_key_version[ $w3tc_group ] <= 0 ) {
			$v = @$this->_memcache->get( $this->_get_key_version_key( $w3tc_group ) );
			$v = intval( $v );

			$this->_key_version[ $w3tc_group ] = ( $v > 0 ? $v : 1 );
		}

		return $this->_key_version[ $w3tc_group ];
	}

	/**
	 * Sets the key version for a specific cache group.
	 *
	 * The expiration is set to 0, which means the version does not expire.
	 *
	 * @param int    $v      The key version to set.
	 * @param string $w3tc_group The cache group to set the version for. Default is an empty string.
	 *
	 * @return void
	 */
	private function _set_key_version( $v, $w3tc_group = '' ) {
		// expiration has to be as long as possible since all cache data expires when key version expires.
		@$this->_memcache->set( $this->_get_key_version_key( $w3tc_group ), $v, 0 );
		$this->_key_version[ $w3tc_group ] = $v;
	}

	/**
	 * Increments the key version for a specific cache group.
	 *
	 * This method attempts to increment the version in Memcached. If the key does not exist, it sets the version to 2 to initialize it.
	 *
	 * @since 2.0.0
	 *
	 * @param string $w3tc_group The cache group to increment the version for. Default is an empty string.
	 *
	 * @return void
	 */
	private function _increment_key_version( $w3tc_group = '' ) {
		$w3tc_r = @$this->_memcache->increment( $this->_get_key_version_key( $w3tc_group ), 1 );

		if ( $w3tc_r ) {
			$this->_key_version[ $w3tc_group ] = $w3tc_r;
		} else {
			// it doesn't initialize the key if it doesn't exist.
			$this->_set_key_version( 2, $w3tc_group );
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

				foreach ( $cdump as $w3tc_line ) {
					$key_data = explode( ' ', $w3tc_line );
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

					$w3tc_key = $key_data[1];
					$bytes    = substr( $key_data[2], 1 );

					if ( substr( $w3tc_key, 0, strlen( $key_prefix ) ) === $key_prefix ) {
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
	 * @param string $w3tc_key       The cache key to update.
	 * @param array  $old_value {
	 *     The current value to compare against.
	 *
	 *     @type mixed $content The content to compare in the old value.
	 * }
	 * @param array  $new_value The new value to set if the old value matches.
	 *
	 * @return bool True if the item was updated, false otherwise.
	 */
	public function set_if_maybe_equals( $w3tc_key, $old_value, $new_value ) {
		$storage_key = $this->get_item_key( $w3tc_key );

		$cas        = null;
		$w3tc_value = @$this->_memcache->get( $storage_key, null, $cas );

		if ( ! is_array( $w3tc_value ) ) {
			return false;
		}

		if ( isset( $old_value['content'] ) && $w3tc_value['content'] !== $old_value['content'] ) {
			return false;
		}

		return @$this->_memcache->cas( $cas, $storage_key, $new_value );
	}

	/**
	 * Increments a counter in the cache by the specified value.
	 *
	 * If the counter does not exist, it initializes it with 0.
	 *
	 * @param string $w3tc_key   The cache key for the counter.
	 * @param int    $w3tc_value The value to add to the counter.
	 *
	 * @return bool True if the counter was incremented successfully, false otherwise.
	 */
	public function counter_add( $w3tc_key, $w3tc_value ) {
		if ( 0 === $w3tc_value ) {
			return true;
		}

		$storage_key = $this->get_item_key( $w3tc_key );
		$w3tc_r      = $this->_memcache->increment( $storage_key, $w3tc_value, 0, 3600 );
		if ( ! $w3tc_r ) { // it doesnt initialize counter by itself.
			$this->counter_set( $w3tc_key, 0 );
		}

		return $w3tc_r;
	}

	/**
	 * Sets a counter value in the Memcached storage.
	 *
	 * This method sets a specified value for a given key in the Memcached storage. If the key doesn't already exist, it initializes
	 * it with the provided value. This is typically used to set counters or similar numeric values that need to be stored persistently.
	 *
	 * @param string $w3tc_key   The key under which the counter is stored.
	 * @param int    $w3tc_value The value to set for the counter.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function counter_set( $w3tc_key, $w3tc_value ) {
		$storage_key = $this->get_item_key( $w3tc_key );
		return @$this->_memcache->set( $storage_key, $w3tc_value );
	}

	/**
	 * Retrieves the counter value from Memcached storage.
	 *
	 * This method fetches the stored counter value for the given key. If the key doesn't exist or the value is not a valid integer,
	 * it returns 0. This is typically used to retrieve counters or similar numeric values stored persistently.
	 *
	 * @param string $w3tc_key The key under which the counter is stored.
	 *
	 * @return int The current counter value, or 0 if the key does not exist or is invalid.
	 */
	public function counter_get( $w3tc_key ) {
		$storage_key = $this->get_item_key( $w3tc_key );
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
	 * @param string $w3tc_name The item name to generate a key for.
	 *
	 * @return string The generated unique Memcached key.
	 */
	public function get_item_key( $w3tc_name ) {
		// memcached doesn't survive spaces in a key.
		$w3tc_key = sprintf( 'w3tc_%d_%s_%d_%s_%s', $this->_instance_id, $this->_host, $this->_blog_id, $this->_module, md5( $w3tc_name ) );
		return $w3tc_key;
	}
}
