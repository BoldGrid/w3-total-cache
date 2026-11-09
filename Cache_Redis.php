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
	 * Seconds to skip a server after a failed connect or command.
	 *
	 * @since X.X.X
	 *
	 * @var int
	 */
	const BYPASS_SECONDS = 30;

	/**
	 * Process-local bypass expiry, keyed by server and database.
	 *
	 * @since X.X.X
	 *
	 * @var array<string,int>
	 */
	private static $bypass_until = array();

	/**
	 * Directory for the cross-request bypass files. Tests set this.
	 *
	 * @since X.X.X
	 *
	 * @var string|null
	 */
	public static $bypass_directory = null;

	/**
	 * Optional factory for the Redis client. Tests set this.
	 *
	 * @since X.X.X
	 *
	 * @var callable|null
	 */
	public static $client_factory = null;

	/**
	 * Constructor.
	 *
	 * @param array $w3tc_config Config.
	 */
	public function __construct( $w3tc_config ) {
		parent::__construct( $w3tc_config );

		$this->_persistent              = ( isset( $w3tc_config['persistent'] ) && $w3tc_config['persistent'] );
		$this->_servers                 = (array) $w3tc_config['servers'];
		$this->_verify_tls_certificates = self::verify_tls_certificates_enabled( $w3tc_config );
		$this->_password                = $w3tc_config['password'];
		$this->_dbid                    = $w3tc_config['dbid'];
		$this->_timeout                 = self::fail_fast_seconds( $w3tc_config['timeout'] ?? 0 );
		$this->_retry_interval          = $w3tc_config['retry_interval'] ?? 3600000;
		$this->_read_timeout            = self::fail_fast_seconds( $w3tc_config['read_timeout'] ?? 0 );

		/**
		 * When disabled - no extra requests are made to obtain key version,
		 * but flush operations not supported as a result group should be always empty.
		 */
		if ( isset( $w3tc_config['key_version_mode'] ) && 'disabled' === $w3tc_config['key_version_mode'] ) {
			$this->_key_version[''] = 1;
		}
	}

	/**
	 * Whether TLS peer and certificate verification is enabled.
	 *
	 * Missing keys verify. Only an explicit false/0 opt-out disables.
	 *
	 * @since 2.10.6
	 *
	 * @param array $w3tc_config Redis engine construction array.
	 *
	 * @return bool
	 */
	public static function verify_tls_certificates_enabled( array $w3tc_config ) {
		if ( ! array_key_exists( 'verify_tls_certificates', $w3tc_config ) ) {
			return true;
		}

		return (bool) $w3tc_config['verify_tls_certificates'];
	}

	/**
	 * Stream context for a Redis server URI.
	 *
	 * Non-TLS URIs get an empty context. TLS URIs always set verify_peer
	 * and verify_peer_name explicitly so PHP defaults cannot silently
	 * disable verification.
	 *
	 * @since 2.10.6
	 *
	 * @param string $server                   Server URI.
	 * @param bool   $verify_tls_certificates  Whether to verify.
	 *
	 * @return array
	 */
	public static function tls_stream_context( $server, $verify_tls_certificates ) {
		if ( 0 !== strpos( (string) $server, 'tls:' ) ) {
			return array();
		}

		$verify = (bool) $verify_tls_certificates;

		return array(
			'stream' => array(
				'verify_peer'      => $verify,
				'verify_peer_name' => $verify,
			),
		);
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
			// Queue the invalidation so recovery applies it before any read.
			$this->queue_invalidation( $storage_key, $this->_use_expired_data ? 'delete_expired' : 'delete' );
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
			$this->queue_invalidation( $storage_key, 'hard_delete' );
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

			if ( $this->_set_key_version( $this->_key_version[ $w3tc_group ], $w3tc_group ) ) {
				return true;
			}
		}

		// No live server: queue the group so recovery moves its version on.
		$this->queue_invalidation( $this->_get_key_version_key( $w3tc_group ), 'flush', $w3tc_group );

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

			if ( \method_exists( $accessor, 'is_failed' ) && $accessor->is_failed() ) {
				return 0;
			}

			$v = intval( $v_original );
			$v = ( $v > 0 ? $v : 1 );

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

		$connect_args[] = self::fail_fast_seconds( $this->_timeout );
		$connect_args[] = $this->_persistent ? $this->_instance_id . '_' . $this->_dbid : null;
		$connect_args[] = $this->_retry_interval;

		$phpredis_version = phpversion( 'redis' );

		// The read_timeout parameter was added in phpredis 3.1.3.
		if ( version_compare( $phpredis_version, '3.1.3', '>=' ) ) {
			$connect_args[] = self::fail_fast_seconds( $this->_read_timeout );
		}

		// Support for stream context was added in phpredis 5.3.2.
		if ( version_compare( $phpredis_version, '5.3.2', '>=' ) ) {
			$connect_args[] = self::tls_stream_context( $server, $this->_verify_tls_certificates );
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
			$cached = $this->_accessors[ $w3tc_index ];

			if (
				! \method_exists( $cached, 'is_failed' ) ||
				! $cached->is_failed() ||
				self::is_server_bypassed( $this->_servers[ $w3tc_index ], $this->_dbid )
			) {
				return $cached;
			}

			unset( $this->_accessors[ $w3tc_index ] );
		}

		if ( ! isset( $this->_servers[ $w3tc_index ] ) ) {
			$this->_accessors[ $w3tc_index ] = null;
		} else {
			$server = $this->_servers[ $w3tc_index ];

			if ( self::is_server_bypassed( $server, $this->_dbid ) ) {
				$accessor = null;
			} else {
				try {
					$connect_args = $this->build_connect_args( $server );
					$accessor     = $this->new_redis_client();

					if ( $this->_persistent ) {
						$accessor->pconnect( ...$connect_args );
					} else {
						$accessor->connect( ...$connect_args );
					}

					$ready = ( empty( $this->_password ) || false !== $accessor->auth( $this->_password ) )
						&& false !== $accessor->select( $this->_dbid );

					// Queued invalidations apply before this connection serves any read.
					if ( $ready && $this->replay_queued_invalidations( $accessor, $server ) ) {
						self::clear_server_bypass( $server, $this->_dbid );
						$accessor = $this->guard_client( $accessor, $server );
					} else {
						\error_log( __METHOD__ . ': AUTH, SELECT, or queued replay failed for database ' . $this->_dbid ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						self::mark_server_bypassed( $server, $this->_dbid );
						$accessor = null;
					}
				} catch ( \Exception $e ) {
					error_log( __METHOD__ . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					self::mark_server_bypassed( $server, $this->_dbid );
					$accessor = null;
				}
			}

			$this->_accessors[ $w3tc_index ] = $accessor;
		}

		return $this->_accessors[ $w3tc_index ];
	}

	/**
	 * A configured timeout of 0 waits forever. Use one second instead.
	 *
	 * @since X.X.X
	 *
	 * @param mixed $timeout Configured seconds.
	 * @return float
	 */
	public static function fail_fast_seconds( $timeout ) {
		$timeout = (float) $timeout;

		return $timeout > 0 ? $timeout : 1.0;
	}

	/**
	 * Drop Redis engine and connection changes that did not answer a probe.
	 *
	 * Unchanged Redis connection settings are left alone so an outage does
	 * not block saving unrelated settings. A failed probe restores the
	 * previous engine and every Redis connection setting for that module,
	 * so old servers are never paired with new credentials or options.
	 *
	 * @since X.X.X
	 *
	 * @param object        $new_config New config.
	 * @param object|null   $old_config Previous config.
	 * @param callable|null $probe      Optional probe ( $servers, $module ) : bool.
	 * @return string[] Module names that were reverted.
	 */
	public static function enforce_verified_servers( $new_config, $old_config, $probe = null ) {
		$modules  = array( 'dbcache', 'objectcache', 'pgcache', 'minify' );
		$reverted = array();

		foreach ( $modules as $module ) {
			$engine_key = $module . '.engine';

			if ( 'redis' !== (string) $new_config->get_string( $engine_key ) ) {
				continue;
			}

			$new_settings = self::connection_settings( $new_config, $module );
			$old_engine   = $old_config ? (string) $old_config->get_string( $engine_key ) : '';
			$old_settings = $old_config ? self::connection_settings( $old_config, $module ) : array();

			if ( 'redis' === $old_engine && $new_settings === $old_settings ) {
				continue;
			}

			$new_servers = $new_settings[ $module . '.redis.servers' ];

			if ( null === $probe ) {
				$ok = self::probe_servers(
					$new_servers,
					$new_settings[ $module . '.redis.password' ],
					$new_settings[ $module . '.redis.dbid' ],
					$new_settings[ $module . '.redis.verify_tls_certificates' ],
					$new_settings[ $module . '.redis.timeout' ],
					$new_settings[ $module . '.redis.read_timeout' ]
				);
			} else {
				$ok = (bool) \call_user_func( $probe, $new_servers, $module );
			}

			if ( $ok ) {
				continue;
			}

			if ( $old_config ) {
				$new_config->set( $engine_key, $old_engine );

				foreach ( $old_settings as $key => $value ) {
					$new_config->set( $key, $value );
				}
			}

			$reverted[] = $module;
		}

		return $reverted;
	}

	/**
	 * Redis connection settings for one module, keyed by config key.
	 *
	 * @since X.X.X
	 *
	 * @param object $config Config.
	 * @param string $module Module prefix.
	 * @return array
	 */
	private static function connection_settings( $config, $module ) {
		$prefix = $module . '.redis.';

		return array(
			$prefix . 'servers'                 => $config->get_array( $prefix . 'servers' ),
			$prefix . 'password'                => (string) $config->get_string( $prefix . 'password' ),
			$prefix . 'dbid'                    => $config->get_integer( $prefix . 'dbid' ),
			$prefix . 'verify_tls_certificates' => $config->get_boolean( $prefix . 'verify_tls_certificates', true ),
			$prefix . 'persistent'              => $config->get_boolean( $prefix . 'persistent' ),
			$prefix . 'timeout'                 => $config->get_integer( $prefix . 'timeout' ),
			$prefix . 'retry_interval'          => $config->get_integer( $prefix . 'retry_interval' ),
			$prefix . 'read_timeout'            => $config->get_integer( $prefix . 'read_timeout' ),
		);
	}

	/**
	 * Connect and write a short-lived key. False when any server fails.
	 *
	 * Any bypass for the server is cleared before and after the probe, so a
	 * recovered server is actually tried and a rejected save does not skip
	 * the settings still in use.
	 *
	 * @since X.X.X
	 *
	 * @param array  $servers                 Server strings.
	 * @param string $password                Password.
	 * @param mixed  $dbid                    Database id.
	 * @param bool   $verify_tls_certificates Whether to verify TLS certificates.
	 * @param mixed  $timeout                 Connect timeout; 0 uses one second.
	 * @param mixed  $read_timeout            Read timeout; 0 uses one second.
	 * @return bool
	 */
	public static function probe_servers( $servers, $password, $dbid, $verify_tls_certificates = true, $timeout = 0, $read_timeout = 0 ) {
		if ( ! \is_callable( self::$client_factory ) && ! \class_exists( 'Redis' ) ) {
			return false;
		}

		foreach ( (array) $servers as $server ) {
			$server = \trim( (string) $server );

			if ( '' === $server ) {
				return false;
			}

			self::clear_server_bypass( $server, $dbid );

			try {
				$cache = new self(
					array(
						'blog_id'                 => 0,
						'servers'                 => array( $server ),
						'persistent'              => false,
						'password'                => (string) $password,
						'dbid'                    => $dbid,
						'timeout'                 => self::fail_fast_seconds( $timeout ),
						'retry_interval'          => 0,
						'read_timeout'            => self::fail_fast_seconds( $read_timeout ),
						'verify_tls_certificates' => (bool) $verify_tls_certificates,
					)
				);
				$wrote = $cache->set( 'w3tc_redis_probe', array( 'content' => '1' ), 30 );
			} catch ( \Exception $e ) {
				$wrote = false;
			}

			/**
			 * The marker is keyed by server and database only, so a rejected
			 * edit must not leave it behind for the configuration still in use.
			 */
			self::clear_server_bypass( $server, $dbid );

			if ( ! $wrote ) {
				return false;
			}
		}

		return ! empty( $servers );
	}

	/**
	 * Forget process-local test overrides.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function reset_runtime_state() {
		self::$bypass_until     = array();
		self::$bypass_directory = null;
		self::$client_factory   = null;
	}

	/**
	 * Whether this server should be skipped until the probe window ends.
	 *
	 * @since X.X.X
	 *
	 * @param string $server Server URI.
	 * @param mixed  $dbid   Database id.
	 * @return bool
	 */
	public static function is_server_bypassed( $server, $dbid ) {
		$key   = self::bypass_key( $server, $dbid );
		$until = isset( self::$bypass_until[ $key ] ) ? (int) self::$bypass_until[ $key ] : 0;

		if ( $until > time() ) {
			return true;
		}

		$path = self::bypass_path( $server, $dbid );

		if ( '' === $path || ! is_file( $path ) ) {
			return false;
		}

		// Concurrent requests may remove the file between these calls.
		$until = (int) @\file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.NoSilencedErrors.Discouraged

		if ( $until > time() ) {
			self::$bypass_until[ $key ] = $until;
			return true;
		}

		return false;
	}

	/**
	 * Skip this server for BYPASS_SECONDS.
	 *
	 * @since X.X.X
	 *
	 * @param string $server Server URI.
	 * @param mixed  $dbid   Database id.
	 * @return void
	 */
	public static function mark_server_bypassed( $server, $dbid ) {
		$key   = self::bypass_key( $server, $dbid );
		$until = time() + self::BYPASS_SECONDS;

		self::$bypass_until[ $key ] = $until;

		$path = self::bypass_path( $server, $dbid );

		if ( '' === $path ) {
			return;
		}

		$dir = dirname( $path );

		if ( ! is_dir( $dir ) ) {
			@\mkdir( $dir, 0755, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir, WordPress.PHP.NoSilencedErrors.Discouraged
		}

		if ( is_dir( $dir ) && \is_writable( $dir ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
			@\file_put_contents( $path, (string) $until, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/**
	 * Allow this server to be used again.
	 *
	 * @since X.X.X
	 *
	 * @param string $server Server URI.
	 * @param mixed  $dbid   Database id.
	 * @return void
	 */
	public static function clear_server_bypass( $server, $dbid ) {
		unset( self::$bypass_until[ self::bypass_key( $server, $dbid ) ] );

		$path = self::bypass_path( $server, $dbid );

		if ( '' !== $path && is_file( $path ) ) {
			@\unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/**
	 * Key for one server and database.
	 *
	 * @param string $server Server URI.
	 * @param mixed  $dbid   Database id.
	 * @return string
	 */
	private static function bypass_key( $server, $dbid ) {
		return md5( (string) $server . '|' . (string) $dbid );
	}

	/**
	 * Path of the cross-request bypass file.
	 *
	 * @param string $server Server URI.
	 * @param mixed  $dbid   Database id.
	 * @return string
	 */
	private static function bypass_path( $server, $dbid ) {
		$dir = self::$bypass_directory;

		if ( ! is_string( $dir ) || '' === $dir ) {
			if ( \defined( 'W3TC_CACHE_DIR' ) ) {
				$dir = W3TC_CACHE_DIR;
			} else {
				$dir = \defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/cache' : '';
			}
		}

		if ( '' === $dir ) {
			return '';
		}

		return $dir . '/w3tc-redis-bypass-' . self::bypass_key( $server, $dbid );
	}

	/**
	 * Queue file size above which the queue collapses to one database flush.
	 *
	 * @since X.X.X
	 */
	const PENDING_MAX_BYTES = 1048576;

	/**
	 * Path of the cross-request queue of invalidations skipped during a bypass.
	 *
	 * @since X.X.X
	 *
	 * @param string $server Server URI.
	 * @param mixed  $dbid   Database id.
	 * @return string
	 */
	private static function pending_path( $server, $dbid ) {
		$path = self::bypass_path( $server, $dbid );

		if ( '' === $path ) {
			return '';
		}

		return dirname( $path ) . '/w3tc-redis-pending-' . self::bypass_key( $server, $dbid );
	}

	/**
	 * Server that owns a storage key, mirroring _get_accessor().
	 *
	 * @since X.X.X
	 *
	 * @param string $storage_key Storage key.
	 * @return string|null
	 */
	private function server_for_key( $storage_key ) {
		$count = count( $this->_servers );
		$index = $count <= 1 ? 0 : crc32( $storage_key ) % $count;

		return isset( $this->_servers[ $index ] ) ? $this->_servers[ $index ] : null;
	}

	/**
	 * Record an invalidation that could not reach Redis, for replay on recovery.
	 *
	 * @since X.X.X
	 *
	 * @param string $storage_key Storage key, or version key for 'flush'.
	 * @param string $op          One of delete, delete_expired, hard_delete, flush.
	 * @param string $group       Group, for 'flush'.
	 * @return bool
	 */
	private function queue_invalidation( $storage_key, $op, $group = '' ) {
		$server = $this->server_for_key( $storage_key );

		if ( null === $server ) {
			return false;
		}

		$path = self::pending_path( $server, $this->_dbid );

		if ( '' === $path ) {
			return false;
		}

		$dir = dirname( $path );

		if ( ! is_dir( $dir ) ) {
			@\mkdir( $dir, 0755, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir, WordPress.PHP.NoSilencedErrors.Discouraged
		}

		if ( ! is_dir( $dir ) || ! \is_writable( $dir ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
			return false;
		}

		if ( is_file( $path ) && (int) @\filesize( $path ) > self::PENDING_MAX_BYTES ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			// Too many to replay one by one: recovery flushes the database instead.
			$line = \json_encode( array( 'op' => 'flush_all' ) ) . "\n"; // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode

			return false !== @\file_put_contents( $path, $line, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.NoSilencedErrors.Discouraged
		}

		if ( 'flush' === $op ) {
			$entry = array(
				'op'    => 'flush',
				'group' => (string) $group,
			);
		} else {
			$entry = array(
				'op'  => $op,
				'key' => (string) $storage_key,
			);
		}

		$line = \json_encode( $entry ) . "\n"; // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode

		return false !== @\file_put_contents( $path, $line, FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Replay queued invalidations on a freshly connected client.
	 *
	 * The queue is claimed by rename first, so a concurrent request appends to a
	 * new file. Entries that fail to apply are written back for the next try.
	 *
	 * @since X.X.X
	 *
	 * @param object $redis  Connected client, before any guard.
	 * @param string $server Server URI.
	 * @return bool False when the server must stay bypassed.
	 */
	private function replay_queued_invalidations( $redis, $server ) {
		$path = self::pending_path( $server, $this->_dbid );

		if ( '' === $path || ! is_file( $path ) ) {
			return true;
		}

		$claimed = $path . '.replay-' . getmypid() . '-' . uniqid( '', true );

		if ( ! @\rename( $path, $claimed ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename, WordPress.PHP.NoSilencedErrors.Discouraged
			// Another request claimed the queue; it replays it.
			return ! is_file( $path );
		}

		$lines   = @\file( $claimed, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file, WordPress.PHP.NoSilencedErrors.Discouraged
		$lines   = is_array( $lines ) ? $lines : array();
		$applied = 0;

		try {
			foreach ( $lines as $line ) {
				$entry = json_decode( $line, true );

				if ( is_array( $entry ) && ! $this->apply_queued_invalidation( $redis, $entry ) ) {
					break;
				}

				++$applied;
			}
		} catch ( \Exception $e ) {
			error_log( __METHOD__ . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		if ( $applied < count( $lines ) ) {
			$rest = implode( "\n", array_slice( $lines, $applied ) ) . "\n";
			@\file_put_contents( $path, $rest, FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.NoSilencedErrors.Discouraged
			@\unlink( $claimed ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.PHP.NoSilencedErrors.Discouraged

			return false;
		}

		@\unlink( $claimed ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.PHP.NoSilencedErrors.Discouraged

		// In-process version cache may be behind Redis after replay.
		$this->_key_version = array();

		return true;
	}

	/**
	 * Apply one queued invalidation.
	 *
	 * @since X.X.X
	 *
	 * @param object $redis Connected client.
	 * @param array  $entry Decoded queue line.
	 * @return bool False when the command did not succeed.
	 */
	private function apply_queued_invalidation( $redis, array $entry ) {
		$op = isset( $entry['op'] ) ? $entry['op'] : '';

		if ( 'flush_all' === $op ) {
			return false !== $redis->flushdb();
		}

		if ( 'flush' === $op ) {
			$version_key = $this->_get_key_version_key( isset( $entry['group'] ) ? (string) $entry['group'] : '' );
			$v           = intval( $redis->get( $version_key ) );
			$v           = $v > 0 ? $v : 1;

			return false !== $redis->set( $version_key, $v + 1 );
		}

		if ( ! isset( $entry['key'] ) ) {
			return true;
		}

		$key = $entry['key'];

		if ( 'delete_expired' === $op ) {
			$v   = $redis->get( $key );
			$ttl = $redis->ttl( $key );

			if ( is_array( $v ) && $ttl > 0 ) {
				$v['key_version'] = 0;

				return false !== $redis->setex( $key, $ttl, $v );
			}
		}

		if ( in_array( $op, array( 'delete', 'delete_expired', 'hard_delete' ), true ) ) {
			return false !== $redis->setex( $key, 1, '' );
		}

		return true;
	}

	/**
	 * Build a Redis client, or the test factory's client.
	 *
	 * @return object
	 */
	private function new_redis_client() {
		if ( is_callable( self::$client_factory ) ) {
			return call_user_func( self::$client_factory );
		}

		return new \Redis();
	}

	/**
	 * Catch later Redis exceptions so a dropped connection cannot escape.
	 *
	 * @param object $redis  Connected client.
	 * @param string $server Server URI.
	 * @return object
	 */
	private function guard_client( $redis, $server ) {
		$dbid = $this->_dbid;

		return new class( $redis, $server, $dbid ) {
			/**
			 * Connected client.
			 *
			 * @var object
			 */
			private $redis;

			/**
			 * Server URI.
			 *
			 * @var string
			 */
			private $server;

			/**
			 * Database id.
			 *
			 * @var mixed
			 */
			private $dbid;

			/**
			 * Whether a command already failed.
			 *
			 * @var bool
			 */
			private $dead = false;

			/**
			 * Whether a MULTI or PIPELINE block is open.
			 *
			 * @var bool
			 */
			private $queued = false;

			/**
			 * Store the client this guard wraps.
			 *
			 * @param object $redis  Connected client.
			 * @param string $server Server URI.
			 * @param mixed  $dbid   Database id.
			 */
			public function __construct( $redis, $server, $dbid ) {
				$this->redis  = $redis;
				$this->server = $server;
				$this->dbid   = $dbid;
			}

			/**
			 * Whether a command on this client already failed.
			 *
			 * @return bool
			 */
			public function is_failed() {
				return $this->dead;
			}

			/**
			 * Run a Redis command and bypass the server if it throws.
			 *
			 * Calls that return the client (MULTI and PIPELINE chains) return
			 * this guard so queued commands and exec() stay guarded. After a
			 * failure, an open chain keeps returning this guard until exec()
			 * or discard() so callers never chain onto false.
			 *
			 * @param string $method Method.
			 * @param array  $args   Arguments.
			 * @return mixed
			 */
			public function __call( $method, $args ) {
				$name     = \strtolower( $method );
				$opens    = 'multi' === $name || 'pipeline' === $name;
				$closes   = 'exec' === $name || 'discard' === $name;
				$was_open = $this->queued;

				if ( $opens ) {
					$this->queued = true;
				} elseif ( $closes ) {
					$this->queued = false;
				}

				if ( ! $this->dead ) {
					try {
						$result = $this->redis->$method( ...$args );

						if ( $result === $this->redis ) {
							return $this;
						}

						if ( $opens ) {
							$this->queued = false;
						}

						return $result;
					} catch ( \Exception $e ) {
						$this->dead = true;
						\error_log( 'W3TC Cache_Redis::' . $method . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						Cache_Redis::mark_server_bypassed( $this->server, $this->dbid );
					}
				}

				return ( $opens || ( $was_open && ! $closes ) ) ? $this : false;
			}
		};
	}
}
