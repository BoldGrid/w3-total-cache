<?php
/**
 * FIle: Cache_Nginx_Memcached.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cache_Nginx_Memcached
 *
 * PECL Memcached class
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable Universal.CodeAnalysis.ConstructorDestructorReturn.ReturnValueFound
 */
class Cache_Nginx_Memcached extends Cache_Base {
	/**
	 * Memcache object
	 *
	 * @var Memcache
	 */
	private $_memcache = null;

	/**
	 * Configuration used to reinitialize persistent object
	 *
	 * @var Config
	 */
	private $_config = null;

	/**
	 * Constructor for initializing the Memcached client.
	 *
	 * This constructor initializes the Memcached client with the specified configuration. It checks if persistent connections are
	 * enabled, and if so, attempts to reconnect to existing Memcached servers. If no servers are available, it calls the
	 * initialization method. If persistence is not enabled, it initializes a non-persistent Memcached connection.
	 *
	 * @param array $config Configuration array containing settings for Memcached.
	 *
	 * @return bool True if the Memcached connection was initialized successfully, false otherwise.
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
	 * Initializes the Memcached connection and configures the servers.
	 *
	 * This method is responsible for configuring the Memcached instance with options like compression, server list, and
	 * authentication. It adds each server from the configuration and handles optional features like AWS autodiscovery and
	 * SASL authentication if configured.
	 *
	 * @param array $config Configuration array containing Memcached settings, including server details and authentication.
	 *
	 * @return bool True if initialization is successful, false if no servers are configured.
	 */
	private function initialize( $config ) {
		if ( empty( $config['servers'] ) ) {
			return false;
		}

		if ( defined( '\Memcached::OPT_REMOVE_FAILED_SERVERS' ) ) {
			$this->_memcache->setOption( \Memcached::OPT_REMOVE_FAILED_SERVERS, true );
		}

		$this->_memcache->setOption( \Memcached::OPT_COMPRESSION, false );

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

		return true;
	}

	/**
	 * Adds an item to Memcached with a given key.
	 *
	 * This method adds a new item to Memcached. It first calls the `set` method to store the item. This is typically used for
	 * storing objects or arrays in Memcached.
	 *
	 * @param string $key    The key under which the item is stored.
	 * @param mixed  $value  The variable to store in Memcached.
	 * @param int    $expire The expiration time for the item in seconds. Default is 0 (no expiration).
	 * @param string $group  An optional group to categorize the item.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function add( $key, &$value, $expire = 0, $group = '' ) {
		return $this->set( $key, $value, $expire, $group );
	}

	/**
	 * Sets an item in Memcached.
	 *
	 * This method stores an item in Memcached under the specified key. The item will be serialized and stored, and an expiration
	 * time can be set.
	 *
	 * @param string $key    The key under which the item is stored.
	 * @param mixed  $value  The variable to store in Memcached.
	 * @param int    $expire The expiration time in seconds. Default is 0 (no expiration).
	 * @param string $group  An optional group to categorize the item.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function set( $key, $value, $expire = 0, $group = '' ) {
		$this->_memcache->setOption( \Memcached::OPT_USER_FLAGS, ( isset( $value['c'] ) ? 1 : 0 ) );

		return @$this->_memcache->set( $key, $value['content'], $expire );
	}

	/**
	 * Retrieves an item from Memcached with its old data.
	 *
	 * This method attempts to retrieve an item from Memcached. If the item exists, it returns the content along with an indicator
	 * of whether compression was applied based on the key suffix.
	 *
	 * @param string $key     The key of the item to retrieve.
	 * @param string $group   The group associated with the item.
	 *
	 * @return array|null The content of the item along with compression info, or null if not found.
	 */
	public function get_with_old( $key, $group = '' ) {
		$has_old_data = false;

		$v = @$this->_memcache->get( $key );
		if ( false === $v ) {
			return null;
		}

		$data                = array( 'content' => $v );
		$data['compression'] = ( ' _gzip' === substr( $key, -5 ) ? 'gzip' : '' );
		return array( $data, false );
	}

	/**
	 * Replaces an existing item in Memcached.
	 *
	 * This method replaces an existing item in Memcached. It calls the `set` method to store the new value under the same key.
	 * If the key doesn't exist, it behaves like a regular set.
	 *
	 * @param string $key    The key under which the item is stored.
	 * @param mixed  $value  The variable to store in Memcached.
	 * @param int    $expire The expiration time in seconds. Default is 0 (no expiration).
	 * @param string $group  An optional group to categorize the item.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function replace( $key, &$value, $expire = 0, $group = '' ) {
		return $this->set( $key, $value, $expire, $group );
	}

	/**
	 * Deletes an item from Memcached.
	 *
	 * This method deletes an item from Memcached by its key. If the key doesn't exist, it silently does nothing.
	 *
	 * @param string $key     The key of the item to delete.
	 * @param string $group   The group associated with the item.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function delete( $key, $group = '' ) {
		return @$this->_memcache->delete( $key );
	}

	/**
	 * Hard deletes an item from Memcached.
	 *
	 * This method forces the deletion of an item from Memcached. It is similar to the regular `delete` method but emphasizes
	 * immediate removal.
	 *
	 * @param string $key     The key of the item to delete.
	 * @param string $group   The group associated with the item.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function hard_delete( $key, $group = '' ) {
		return @$this->_memcache->delete( $key );
	}

	/**
	 * Flushes all items from Memcached.
	 *
	 * This method clears all the stored items from Memcached. It has no way to flush individual caches.
	 *
	 * @param string $group   An optional group to categorize the items.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function flush( $group = '' ) {
		// can only flush everything from memcached, no way to flush only pgcache cache.
		return @$this->_memcache->flush();
	}

	/**
	 * Checks if Memcached is available.
	 *
	 * This method checks if the Memcached class exists and is available for use.
	 *
	 * @return bool True if Memcached is available, false otherwise.
	 */
	public function available() {
		return class_exists( 'Memcached' );
	}

	/**
	 * Retrieves statistics from Memcached.
	 *
	 * This method returns statistics from the Memcached server. If multiple servers are available, it returns stats from the first server.
	 *
	 * @return array The statistics from Memcached, or an empty array if no stats are available.
	 */
	public function get_statistics() {
		$a = $this->_memcache->getStats();
		if ( count( $a ) > 0 ) {
			$keys = array_keys( $a );
			$key  = $keys[0];
			return $a[ $key ];
		}

		return $a;
	}

	/**
	 * Retrieves cache size statistics from Memcached.
	 *
	 * This method collects statistics on the size of cached data on the Memcached server, including the total bytes and items.
	 *
	 * @param int $timeout_time The timeout time for statistics retrieval.
	 *
	 * @return array An array containing the size of cached data, number of items, and whether a timeout occurred.
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
	 * Sets a new value for a given key in Memcached if it matches the old value.
	 *
	 * This method attempts to update a value in Memcached only if the current value for the given key is equal to the provided
	 * old value. It uses CAS (Check And Set) to ensure that the value is updated atomically and only when the existing value
	 * has not changed.
	 *
	 * @param string $key       The key of the item to update.
	 * @param array  $old_value The old value to compare against the current value in cache.
	 * @param mixed  $new_value The new value to set if the old value matches the current value.
	 *
	 * @return bool True if the value was successfully set, false otherwise.
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
	 * Increments the counter for a given key in Memcached by a specified value.
	 *
	 * This method increments the value of a counter stored in Memcached. If the key does not exist or is not a number, the counter
	 * is initialized to 0 and then incremented.
	 *
	 * @param string $key   The key of the counter to increment.
	 * @param int    $value The amount by which to increment the counter.
	 *
	 * @return bool True if the increment was successful, false otherwise.
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
	 * Sets the counter value for a given key in Memcached.
	 *
	 * This method sets the value of a counter in Memcached. If the key does not exist, it will create a new entry with the provided value.
	 *
	 * @param string $key   The key of the counter to set.
	 * @param int    $value The value to set the counter to.
	 *
	 * @return bool True if the value was successfully set, false otherwise.
	 */
	public function counter_set( $key, $value ) {
		$storage_key = $this->get_item_key( $key );
		return @$this->_memcache->set( $storage_key, $value );
	}

	/**
	 * Retrieves the current value of a counter stored in Memcached.
	 *
	 * This method retrieves the value of a counter stored in Memcached. If the counter does not exist, it returns 0.
	 *
	 * @param string $key The key of the counter to retrieve.
	 *
	 * @return int The current value of the counter.
	 */
	public function counter_get( $key ) {
		$storage_key = $this->get_item_key( $key );
		$v           = (int) @$this->_memcache->get( $storage_key );

		return $v;
	}

	/**
	 * Generates a unique storage key for a given name.
	 *
	 * This method generates a unique key for an item in Memcached by including the instance ID, host, blog ID, module, and a
	 * hashed version of the provided name. Memcached keys cannot contain spaces, so this method ensures the key format is valid
	 * for Memcached.
	 *
	 * @param string $name The name for which to generate a unique key.
	 *
	 * @return string The generated storage key.
	 */
	public function get_item_key( $name ) {
		// memcached doesn't survive spaces in a key.
		$key = sprintf( 'w3tc_%d_%s_%d_%s_%s', $this->_instance_id, $this->_host, $this->_blog_id, $this->_module, md5( $name ) );
		return $key;
	}
}
