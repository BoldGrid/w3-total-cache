<?php
/**
 * File: Cache_Redis.php
 *
 * @package W3TC
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore,PSR2.Classes.PropertyDeclaration.Underscore,WordPress.PHP.DiscouragedPHPFunctions,WordPress.PHP.NoSilencedErrors
 */

namespace W3TC;

/**
 * Redis cache engine.
 */
class Cache_Redis extends Cache_Base {
	/**
	 * Accessors.
	 *
	 * @var array
	 */
	private $_accessors = array();

	/**
	 * Key value.
	 *
	 * @var array
	 */
	private $_key_version = array();

	/**
	 * Persistent.
	 *
	 * @var bool
	 */
	private $_persistent;

	/**
	 * Password.
	 *
	 * @var string
	 */
	private $_password;

	/**
	 * Servers.
	 *
	 * @var array
	 */
	private $_servers;

	/**
	 * Verify TLS certificate.
	 *
	 * @var bool
	 */
	private $_verify_tls_certificates;

	/**
	 * DB id.
	 *
	 * @var string
	 */
	private $_dbid;

	/**
	 * Timeout.
	 *
	 * @var int.
	 */
	private $_timeout;

	/**
	 * Retry interval.
	 *
	 * @var int
	 */
	private $_retry_interval;

	/**
	 * Retry timeout.
	 *
	 * @var int
	 */
	private $_read_timeout;

	/**
	 * Constructor.
	 *
	 * @param array $w3tc_config Config.
	 */
	public function __construct( $w3tc_config ) {
		parent::__construct( $w3tc_config );

		$this->_persistent              = ( isset( $w3tc_config['persistent'] ) && $w3tc_config['persistent'] );
		$this->_servers                 = (array) $w3tc_config['servers'];
		$this->_verify_tls_certificates = ( isset( $w3tc_config['verify_tls_certificates'] ) && $w3tc_config['verify_tls_certificates'] );
		$this->_password                = $w3tc_config['password'];
		$this->_dbid                    = $w3tc_config['dbid'];
		$this->_timeout                 = $w3tc_config['timeout'] ?? 3600000;
		$this->_retry_interval          = $w3tc_config['retry_interval'] ?? 3600000;
		$this->_read_timeout            = $w3tc_config['read_timeout'] ?? 60.0;

		/**
		 * When disabled - no extra requests are made to obtain key version,
		 * but flush operations not supported as a result group should be always empty.
		 */
		if ( isset( $w3tc_config['key_version_mode'] ) && 'disabled' === $w3tc_config['key_version_mode'] ) {
			$this->_key_version[''] = 1;
		}
	}

	/**
	 * Adds data.
	 *
	 * @param string  $w3tc_key    Key.
	 * @param mixed   $w3tc_value  Var.
	 * @param integer $expire Expire.
	 * @param string  $w3tc_group  Used to differentiate between groups of cache values.
	 * @return bool
	 */
	public function add( $w3tc_key, &$w3tc_value, $expire = 0, $w3tc_group = '' ) {
		return $this->set( $w3tc_key, $w3tc_value, $expire, $w3tc_group );
	}

	/**
	 * Sets data.
	 *
	 * @param string  $w3tc_key    Key.
	 * @param mixed   $w3tc_value  Value.
	 * @param integer $expire Expire.
	 * @param string  $w3tc_group  Used to differentiate between groups of cache values.
	 * @return bool
	 */
	public function set( $w3tc_key, $w3tc_value, $expire = 0, $w3tc_group = '' ) {
		if ( ! isset( $w3tc_value['key_version'] ) ) {
			$w3tc_value['key_version'] = $this->_get_key_version( $w3tc_group );
		}

		$storage_key = $this->get_item_key( $w3tc_key );
		$accessor    = $this->_get_accessor( $storage_key );

		if ( is_null( $accessor ) ) {
			return false;
		}

		if ( ! $expire ) {
			return $accessor->set( $storage_key, serialize( $w3tc_value ) );
		}

		return $accessor->setex( $storage_key, $expire, serialize( $w3tc_value ) );
	}

	/**
	 * Returns data
	 *
	 * @param string $w3tc_key   Key.
	 * @param string $w3tc_group Used to differentiate between groups of cache values.
	 * @return mixed
	 */
	public function get_with_old( $w3tc_key, $w3tc_group = '' ) {
		$has_old_data = false;

		$storage_key = $this->get_item_key( $w3tc_key );
		$accessor    = $this->_get_accessor( $storage_key );

		if ( is_null( $accessor ) ) {
			return array( null, false );
		}

		$v = $accessor->get( $storage_key );
		$v = $this->_unserialize(
			$v,
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

		// Key version is old.
		if ( ! $this->_use_expired_data ) {
			return array( null, $has_old_data );
		}

		// If we have expired data - update it for future use and let current process recalculate it.
		$expires_at = isset( $v['expires_at'] ) ? $v['expires_at'] : null;

		if ( is_null( $expires_at ) || time() > $expires_at ) {
			$v['expires_at'] = time() + 30;
			$accessor->setex( $storage_key, 60, serialize( $v ) );
			$has_old_data = true;

			return array( null, $has_old_data );
		}

		// Return old version.
		return array( $v, $has_old_data );
	}

	/**
	 * Replaces data.
	 *
	 * @param string  $w3tc_key    Key.
	 * @param mixed   $w3tc_value  Value.
	 * @param integer $expire Expire.
	 * @param string  $w3tc_group  Used to differentiate between groups of cache values.
	 * @return bool
	 */
	public function replace( $w3tc_key, &$w3tc_value, $expire = 0, $w3tc_group = '' ) {
		return $this->set( $w3tc_key, $w3tc_value, $expire, $w3tc_group );
	}

	/**
	 * Deletes data.
	 *
	 * @param string $w3tc_key   Key.
	 * @param string $w3tc_group Group.
	 * @return bool
	 */
	public function delete( $w3tc_key, $w3tc_group = '' ) {
		$storage_key = $this->get_item_key( $w3tc_key );
		$accessor    = $this->_get_accessor( $storage_key );

		if ( is_null( $accessor ) ) {
			return false;
		}

		if ( $this->_use_expired_data ) {
			$v   = $accessor->get( $storage_key );
			$ttl = $accessor->ttl( $storage_key );

			if ( is_array( $v ) ) {
				$v['key_version'] = 0;
				$accessor->setex( $storage_key, $ttl, $v );
				return true;
			}
		}

		return $accessor->setex( $storage_key, 1, '' );
	}

	/**
	 * Key to delete, deletes _old and primary if exists.
	 *
	 * @param string $w3tc_key   Key.
	 * @param string $w3tc_group Group.
	 * @return bool
	 */
	public function hard_delete( $w3tc_key, $w3tc_group = '' ) {
		$storage_key = $this->get_item_key( $w3tc_key );
		$accessor    = $this->_get_accessor( $storage_key );

		if ( is_null( $accessor ) ) {
			return false;
		}

		return $accessor->setex( $storage_key, 1, '' );
	}

	/**
	 * Flushes all data.
	 *
	 * @param string $w3tc_group Used to differentiate between groups of cache values.
	 * @return bool
	 */
	public function flush( $w3tc_group = '' ) {
		$this->_get_key_version( $w3tc_group );   // Initialize $this->_key_version.
		if ( isset( $this->_key_version[ $w3tc_group ] ) ) {
			++$this->_key_version[ $w3tc_group ];
			$this->_set_key_version( $this->_key_version[ $w3tc_group ], $w3tc_group );
		}

		return true;
	}

	/**
	 * Gets a key extension for "ahead generation" mode.
	 * Used by AlwaysCached functionality to regenerate content
	 *
	 * @param string $w3tc_group Used to differentiate between groups of cache values.
	 *
	 * @return array
	 */
	public function get_ahead_generation_extension( $w3tc_group ) {
		$v = $this->_get_key_version( $w3tc_group );
		return array(
			'key_version'             => $v + 1,
			'key_version_at_creation' => $v,
		);
	}

	/**
	 * Flushes group with before condition
	 *
	 * @param string $w3tc_group Used to differentiate between groups of cache values.
	 * @param array  $w3tc_extension Used to set a condition what version to flush.
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
	 * Checks if engine can function properly in this environment.
	 *
	 * @return bool
	 */
	public function available() {
		return class_exists( 'Redis' );
	}

	/**
	 * Get statistics.
	 *
	 * @return array
	 */
	public function get_statistics() {
		$accessor = $this->_get_accessor( '' ); // Single-server mode used for stats.

		if ( is_null( $accessor ) ) {
			return array();
		}

		$w3tc_a = $accessor->info();

		return $w3tc_a;
	}

	/**
	 * Returns key version.
	 *
	 * @param string $w3tc_group Used to differentiate between groups of cache values.
	 * @return int
	 */
	private function _get_key_version( $w3tc_group = '' ) {
		if ( ! isset( $this->_key_version[ $w3tc_group ] ) || $this->_key_version[ $w3tc_group ] <= 0 ) {
			$storage_key = $this->_get_key_version_key( $w3tc_group );
			$accessor    = $this->_get_accessor( $storage_key );

			if ( is_null( $accessor ) ) {
				return 0;
			}

			$v_original = $accessor->get( $storage_key );
			$v          = intval( $v_original );
			$v          = ( $v > 0 ? $v : 1 );

			if ( (string) $v_original !== (string) $v ) {
				$accessor->set( $storage_key, $v );
			}

			$this->_key_version[ $w3tc_group ] = $v;
		}

		return $this->_key_version[ $w3tc_group ];
	}

	/**
	 * Sets new key version.
	 *
	 * @param string $v     Version.
	 * @param string $w3tc_group Used to differentiate between groups of cache values.
	 * @return bool
	 */
	private function _set_key_version( $v, $w3tc_group = '' ) {
		$storage_key = $this->_get_key_version_key( $w3tc_group );
		$accessor    = $this->_get_accessor( $storage_key );

		if ( is_null( $accessor ) ) {
			return false;
		}

		$accessor->set( $storage_key, $v );

		return true;
	}

	/**
	 * Used to replace as atomically as possible known value to new one.
	 *
	 * @param string $w3tc_key       Key.
	 * @param string $old_value Old value.
	 * @param string $new_value New value.
	 */
	public function set_if_maybe_equals( $w3tc_key, $old_value, $new_value ) {
		$storage_key = $this->get_item_key( $w3tc_key );
		$accessor    = $this->_get_accessor( $storage_key );

		if ( is_null( $accessor ) ) {
			return false;
		}

		$accessor->watch( $storage_key );

		$w3tc_value = $accessor->get( $storage_key );
		$w3tc_value = $this->_unserialize( $w3tc_value, array( 'key' => $w3tc_key ) );

		if ( ! is_array( $w3tc_value ) ) {
			$accessor->unwatch();
			return false;
		}

		if ( isset( $old_value['content'] ) && $w3tc_value['content'] !== $old_value['content'] ) {
			$accessor->unwatch();
			return false;
		}

		return $accessor->multi()
			->set( $storage_key, $new_value )
			->exec();
	}

	/**
	 * Retrieves multiple cached values in a single request.
	 *
	 * @since 2.9.0
	 *
	 * @param array  $w3tc_keys  Cache keys.
	 * @param string $w3tc_group Cache group.
	 *
	 * @return array Map of cache key => raw cached payload (serialized array or null).
	 */
	public function get_multi( array $w3tc_keys, $w3tc_group = '' ) {
		if ( empty( $w3tc_keys ) ) {
			return array();
		}

		$results        = array();
		$server_buckets = array();
		$servers_count  = count( $this->_servers );

		foreach ( $w3tc_keys as $w3tc_key ) {
			$storage_key = $this->get_item_key( $w3tc_key );
			$w3tc_index  = ( $servers_count <= 1 ) ? 0 : crc32( $storage_key ) % $servers_count;

			$server_buckets[ $w3tc_index ]['storage_keys'][] = $storage_key;
			$server_buckets[ $w3tc_index ]['orig_keys'][]    = $w3tc_key;
		}

		foreach ( $server_buckets as $w3tc_index => $bucket ) {
			$storage_keys = $bucket['storage_keys'];
			$orig_keys    = $bucket['orig_keys'];

			$accessor = $this->_get_accessor( $storage_keys[0] );
			if ( is_null( $accessor ) ) {
				foreach ( $orig_keys as $orig_key ) {
					$results[ $orig_key ] = null;
				}
				continue;
			}

			$values = $accessor->mget( $storage_keys );

			foreach ( $orig_keys as $w3tc_i => $orig_key ) {
				if ( isset( $values[ $w3tc_i ] ) && false !== $values[ $w3tc_i ] ) {
					// This backend only writes cache envelopes — arrays
					// shaped `[ 'key_version' => ..., 'content' => ... ]`
					// via set() / set_multi(). The single-key path in
					// get_with_old() drops anything that isn't `is_array()`
					// (corruption, manually-injected serialized scalar/
					// object, etc.) so the batch path must match: coerce
					// non-arrays to null instead of leaking through.
					// _unserialize() also returns false for the gadget-
					// guard miss; that's covered by the same is_array()
					// check, since false is not an array.
					$decoded              = $this->_unserialize(
						$values[ $w3tc_i ],
						array(
							'group' => $w3tc_group,
							'key'   => $orig_key,
						)
					);
					$results[ $orig_key ] = is_array( $decoded ) ? $decoded : null;
				} else {
					$results[ $orig_key ] = null;
				}
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

		$key_version    = $this->_get_key_version( $w3tc_group );
		$results        = array();
		$server_buckets = array();
		$servers_count  = count( $this->_servers );

		foreach ( $items as $w3tc_key => $w3tc_value ) {
			if ( ! isset( $w3tc_value['key_version'] ) ) {
				$w3tc_value['key_version'] = $key_version;
			}

			$storage_key = $this->get_item_key( $w3tc_key );
			$w3tc_index  = ( $servers_count <= 1 ) ? 0 : crc32( $storage_key ) % $servers_count;

			$server_buckets[ $w3tc_index ]['storage'][ $storage_key ] = serialize( $w3tc_value );
			$server_buckets[ $w3tc_index ]['orig_keys'][]             = $w3tc_key;
		}

		foreach ( $server_buckets as $w3tc_index => $bucket ) {
			$storage_map = $bucket['storage'];
			$orig_keys   = $bucket['orig_keys'];

			$first_storage_key = function_exists( 'array_key_first' )
				? array_key_first( $storage_map )
				: reset( array_keys( $storage_map ) );

			$accessor = $this->_get_accessor( $first_storage_key );
			if ( is_null( $accessor ) ) {
				foreach ( $orig_keys as $orig_key ) {
					$results[ $orig_key ] = false;
				}
				continue;
			}

			if ( ! $expire ) {
				$ok = $accessor->mset( $storage_map );
				foreach ( $orig_keys as $orig_key ) {
					$results[ $orig_key ] = (bool) $ok;
				}
			} else {
				$pipe = $accessor->multi( \Redis::PIPELINE );

				foreach ( $storage_map as $storage_key => $payload ) {
					$pipe->setex( $storage_key, $expire, $payload );
				}

				$exec_results = $pipe->exec();

				foreach ( $orig_keys as $idx => $orig_key ) {
					$results[ $orig_key ] = (bool) ( $exec_results[ $idx ] ?? false );
				}
			}
		}

		return $results;
	}

	/**
	 * Use key as a counter and add integer value to it.
	 *
	 * @param string $w3tc_key   Key.
	 * @param int    $w3tc_value Value.
	 */
	public function counter_add( $w3tc_key, $w3tc_value ) {
		if ( empty( $w3tc_value ) ) {
			return true;
		}

		$storage_key = $this->get_item_key( $w3tc_key );
		$accessor    = $this->_get_accessor( $storage_key );

		if ( is_null( $accessor ) ) {
			return false;
		}

		$w3tc_r = $accessor->incrBy( $storage_key, (int) $w3tc_value );

		if ( ! $w3tc_r ) { // It doesn't initialize counter by itself.
			$this->counter_set( $w3tc_key, 0 );
		}

		return $w3tc_r;
	}

	/**
	 * Use key as a counter and add integet value to it.
	 *
	 * @param string $w3tc_key   Key.
	 * @param int    $w3tc_value Value.
	 */
	public function counter_set( $w3tc_key, $w3tc_value ) {
		$storage_key = $this->get_item_key( $w3tc_key );
		$accessor    = $this->_get_accessor( $storage_key );

		if ( is_null( $accessor ) ) {
			return false;
		}

		return $accessor->set( $storage_key, $w3tc_value );
	}

	/**
	 * Get counter's value.
	 *
	 * @param string $w3tc_key Key.
	 */
	public function counter_get( $w3tc_key ) {
		$storage_key = $this->get_item_key( $w3tc_key );
		$accessor    = $this->_get_accessor( $storage_key );

		if ( is_null( $accessor ) ) {
			return 0;
		}

		$v = (int) $accessor->get( $storage_key );

		return $v;
	}

	/**
	 * Build Redis connection arguments based on server URI
	 *
	 * @param string $server Server URI to connect to.
	 */
	private function build_connect_args( $server ) {
		$connect_args = array();

		if ( substr( $server, 0, 5 ) === 'unix:' ) {
			$connect_args[] = trim( substr( $server, 5 ) );
			$connect_args[] = 0; // Port (int).  For no port, use integer 0.
		} else {
			list( $ip, $port ) = Util_Content::endpoint_to_host_port( $server, 0 ); // Port (int).  For no port, use integer 0.
			$connect_args[]    = $ip;
			$connect_args[]    = $port;
		}

		$connect_args[] = $this->_timeout;
		$connect_args[] = $this->_persistent ? $this->_instance_id . '_' . $this->_dbid : null;
		$connect_args[] = $this->_retry_interval;

		$phpredis_version = phpversion( 'redis' );

		// The read_timeout parameter was added in phpredis 3.1.3.
		if ( version_compare( $phpredis_version, '3.1.3', '>=' ) ) {
			$connect_args[] = $this->_read_timeout;
		}

		// Support for stream context was added in phpredis 5.3.2.
		if ( version_compare( $phpredis_version, '5.3.2', '>=' ) ) {
			$context = array();
			if ( 'tls:' === substr( $server, 0, 4 ) && ! $this->_verify_tls_certificates ) {
				$context['stream'] = array(
					'verify_peer'      => false,
					'verify_peer_name' => false,
				);
			}
			$connect_args[] = $context;
		}

		return $connect_args;
	}

	/**
	 * Get accessor.
	 *
	 * @param string $w3tc_key Key.
	 * @return object
	 */
	private function _get_accessor( $w3tc_key ) {
		if ( count( $this->_servers ) <= 1 ) {
			$w3tc_index = 0;
		} else {
			$w3tc_index = crc32( $w3tc_key ) % count( $this->_servers );
		}

		if ( isset( $this->_accessors[ $w3tc_index ] ) ) {
			return $this->_accessors[ $w3tc_index ];
		}

		if ( ! isset( $this->_servers[ $w3tc_index ] ) ) {
			$this->_accessors[ $w3tc_index ] = null;
		} else {
			try {
				$server       = $this->_servers[ $w3tc_index ];
				$connect_args = $this->build_connect_args( $server );

				$accessor = new \Redis();

				if ( $this->_persistent ) {
					$accessor->pconnect( ...$connect_args );
				} else {
					$accessor->connect( ...$connect_args );
				}

				if ( ! empty( $this->_password ) ) {
					$accessor->auth( $this->_password );
				}

				$accessor->select( $this->_dbid );
			} catch ( \Exception $e ) {
				error_log( __METHOD__ . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				$accessor = null;
			}

			$this->_accessors[ $w3tc_index ] = $accessor;
		}

		return $this->_accessors[ $w3tc_index ];
	}
}
