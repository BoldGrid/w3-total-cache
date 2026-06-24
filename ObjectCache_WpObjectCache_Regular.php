<?php
/**
 * File: ObjectCache_WpObjectCache_Regular.php
 *
 * @package W3TC
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */

namespace W3TC;

/**
 * W3 Object Cache Regular object
 */
class ObjectCache_WpObjectCache_Regular {
	/**
	 * Internal cache array
	 *
	 * @var array
	 */
	private $cache = array();

	/**
	 * Array of global groups
	 *
	 * @var array
	 */
	private $global_groups = array();

	/**
	 * List of non-persistent groups
	 *
	 * @var array
	 */
	private $nonpersistent_groups = array();

	/**
	 * Total count of calls
	 *
	 * @var integer
	 */
	private $cache_total = 0;

	/**
	 * Cache hits count
	 *
	 * @var integer
	 */
	private $cache_hits = 0;

	/**
	 * Number of flushes
	 *
	 * @var integer
	 */
	private $cache_flushes = 0;

	/**
	 * Number of cache sets
	 *
	 * @var integer
	 */
	private $cache_sets = 0;

	/**
	 * Total time (microsecs)
	 *
	 * @var integer
	 */
	private $time_total = 0;

	/**
	 * Blog id of cache
	 *
	 * @var integer
	 */
	private $_blog_id;

	/**
	 * Config
	 *
	 * @var object
	 */
	private $_config = null;

	/**
	 * Caching flag
	 *
	 * @var boolean
	 */
	private $_caching = false;

	/**
	 * Dynamic Caching flag
	 *
	 * @var boolean
	 */
	private $_can_cache_dynamic = null;
	/**
	 * Cache reject reason
	 *
	 * @var string
	 */
	private $cache_reject_reason = '';

	/**
	 * Lifetime
	 *
	 * @var integer
	 */
	private $_lifetime = null;

	/**
	 * Current global version of cache.
	 * It's a level above group's cache version.
	 *
	 * @var integer
	 */
	private $key_version_all = null;

	/**
	 * Debug flag
	 *
	 * @var boolean
	 */
	private $_debug = false;

	/**
	 * Stats enabled flag
	 *
	 * @var boolean
	 */
	private $stats_enabled = false;

	/**
	 * Supported features
	 *
	 * @var array
	 */
	private $supported_features = array(
		'flush_runtime',
		'flush_group',
		'add_multiple',
		'set_multiple',
		'get_multiple',
		'delete_multiple',
		'incr',
		'decr',
		'groups',
		'global_groups',
		'non_persistent',
		'persistent',
	);

	/**
	 * Constructs the object cache instance and initializes various settings.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config              = Dispatcher::config();
		$this->_lifetime            = $this->_config->get_integer( 'objectcache.lifetime' );
		$this->_debug               = $this->_config->get_boolean( 'objectcache.debug' );
		$this->_caching             = $this->_can_cache();
		$this->global_groups        = $this->_config->get_array( 'objectcache.groups.global' );
		$this->nonpersistent_groups = $this->_config->get_array( 'objectcache.groups.nonpersistent' );
		$this->stats_enabled        = $this->_config->get_boolean( 'stats.enabled' );

		$this->_blog_id = Util_Environment::blog_id();
	}

	/**
	 * Retrieves a cached object from the object cache.
	 *
	 * @param string $id    The cache key.
	 * @param string $w3tc_group The cache group.
	 * @param bool   $force Whether to force a cache refresh.
	 * @param bool   $found A reference to a boolean variable indicating whether the cache was found.
	 *
	 * @return mixed The cached object or false if not found.
	 */
	public function get( $id, $w3tc_group = 'default', $force = false, &$found = null ) {
		// Abort if this is a WP-CLI call, objectcache engine is set to Disk, and is disabled for WP-CLI.
		if ( $this->is_wpcli_disk() ) {
			return false;
		}

		if ( $this->_debug || $this->stats_enabled ) {
			$time_start = Util_Debug::microtime();
		}

		if ( empty( $w3tc_group ) ) {
			$w3tc_group = 'default';
		}

		$w3tc_key        = $this->_get_cache_key( $id, $w3tc_group );
		$in_incall_cache = isset( $this->cache[ $w3tc_key ] );
		$fallback_used   = false;

		$cache_total_inc = 0;
		$cache_hits_inc  = 0;

		if ( $in_incall_cache && ! $force ) {
			$found      = true;
			$w3tc_value = $this->cache[ $w3tc_key ];
		} elseif (
			$this->_caching
			&& ! in_array( $w3tc_group, $this->nonpersistent_groups, true )
			&& $this->_check_can_cache_runtime( $w3tc_group )
		) {
			$cache = $this->_get_cache( null, $w3tc_group );
			$v     = $cache->get( $w3tc_key, $w3tc_group );

			/* // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
				For debugging
				$w3tc_a = $cache->_get_with_old_raw( $w3tc_key );
				$path = $cache->get_full_path( $w3tc_key);
				$returned = 'x ' . $path . ' ' .
					(is_readable( $path ) ? ' readable ' : ' not-readable ') .
					json_encode($w3tc_a);
			*/

			$cache_total_inc = 1;

			if (
				is_array( $v )
					&& isset( $v['content'] )
					&& isset( $v['key_version_all'] )
					&& intval( $v['key_version_all'] ) >= $this->key_version_all_get()
			) {
				$found          = true;
				$w3tc_value     = $v['content'];
				$cache_hits_inc = 1;
			} else {
				$found      = false;
				$w3tc_value = false;
			}
		} else {
			$found      = false;
			$w3tc_value = false;
		}

		if ( null === $w3tc_value ) {
			$w3tc_value = false;
		}

		if ( is_object( $w3tc_value ) ) {
			$w3tc_value = clone $w3tc_value;
		}

		if (
			! $found
				&& $this->_is_transient_group( $w3tc_group )
				&& $this->_config->get_boolean( 'objectcache.fallback_transients' )
		) {
			$fallback_used = true;
			$w3tc_value    = $this->_transient_fallback_get( $id, $w3tc_group );
			$found         = ( false !== $w3tc_value );
		}

		if ( $found && ! $in_incall_cache ) {
			$this->cache[ $w3tc_key ] = $w3tc_value;
		}

		// Add debug info.
		if ( ! $in_incall_cache ) {
			$this->cache_total += $cache_total_inc;
			$this->cache_hits  += $cache_hits_inc;

			if ( $this->_debug || $this->stats_enabled ) {
				$time              = Util_Debug::microtime() - $time_start;
				$this->time_total += $time;

				if ( $this->_debug ) {
					if ( $fallback_used ) {
						if ( ! $found ) {
							$returned = 'not in db';
						} else {
							$returned = 'from db fallback';
						}
					} elseif ( ! $found ) {
						if ( $cache_total_inc <= 0 ) {
							$returned = 'not tried cache';
						} else {
							$returned = 'not in cache';
						}
					} else {
						$returned = 'from persistent cache';
					}

					$this->log_call(
						array(
							gmdate( 'r' ),
							'get',
							$w3tc_group,
							$id,
							$returned,
							( $w3tc_value ? strlen( serialize( $w3tc_value ) ) : 0 ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
							(int) ( $time * 1000000 ),
						)
					);
				}
			}
		}

		return $w3tc_value;
	}

	/**
	 * Retrieves multiple cached objects.
	 *
	 * @since 2.4.0
	 *
	 * @param array  $ids    An array of cache keys.
	 * @param string $w3tc_group  The cache group.
	 * @param bool   $force  Whether to force a cache refresh.
	 *
	 * @return array An associative array of cached objects, indexed by cache key.
	 */
	public function get_multiple( $ids, $w3tc_group = 'default', $force = false ) {
		if ( empty( $ids ) ) {
			return array();
		}

		if ( empty( $w3tc_group ) ) {
			$w3tc_group = 'default';
		}

		$results          = array();
		$runtime_misses   = array();
		$cache_total_inc  = 0;
		$cache_hits_inc   = 0;
		$time_start_debug = ( $this->_debug || $this->stats_enabled ) ? Util_Debug::microtime() : 0;

		// First satisfy anything already in the in-request cache unless $force is true.
		foreach ( $ids as $id ) {
			$w3tc_key = $this->_get_cache_key( $id, $w3tc_group );
			if ( ! $force && isset( $this->cache[ $w3tc_key ] ) ) {
				$results[ $id ] = $this->cache[ $w3tc_key ];
				++$cache_hits_inc;
				continue;
			}
			$runtime_misses[ $id ] = $w3tc_key;
		}

		// Attempt a batched persistent fetch for the remaining keys when allowed.
		if (
			! empty( $runtime_misses ) &&
			$this->_caching &&
			! in_array( $w3tc_group, $this->nonpersistent_groups, true ) &&
			$this->_check_can_cache_runtime( $w3tc_group )
		) {
			$cache       = $this->_get_cache( null, $w3tc_group );
			$storage_map = $runtime_misses;
			$raw_values  = array();

			if ( method_exists( $cache, 'get_multi' ) ) {
				$raw_values = $cache->get_multi( array_values( $storage_map ), $w3tc_group );
			} else {
				foreach ( $storage_map as $storage_key ) {
					$raw_values[ $storage_key ] = $cache->get( $storage_key, $w3tc_group );
				}
			}

			foreach ( $runtime_misses as $id => $storage_key ) {
				++$cache_total_inc;
				$v = isset( $raw_values[ $storage_key ] ) ? $raw_values[ $storage_key ] : null;

				if (
					is_array( $v ) &&
					isset( $v['content'] ) &&
					isset( $v['key_version_all'] ) &&
					intval( $v['key_version_all'] ) >= $this->key_version_all_get()
				) {
					$results[ $id ]              = $v['content'];
					$this->cache[ $storage_key ] = $v['content'];
					++$cache_hits_inc;
				} else {
					$results[ $id ] = false;
				}
			}
		} else {
			// Not eligible for persistent fetch; fall back to per-key get() honoring $force.
			foreach ( $runtime_misses as $id => $unused_key ) {
				$results[ $id ] = $this->get( $id, $w3tc_group, $force );
			}
		}

		// Add debug info.
		if ( $this->_debug || $this->stats_enabled ) {
			$time = Util_Debug::microtime() - $time_start_debug;

			$this->cache_total += $cache_total_inc;
			$this->cache_hits  += $cache_hits_inc;
			$this->time_total  += $time;
		}

		return $results;
	}

	/**
	 * Sets a cached object in the object cache.
	 *
	 * @param string $id      The cache key.
	 * @param mixed  $w3tc_data    The data to cache.
	 * @param string $w3tc_group   The cache group.
	 * @param int    $expire  The expiration time, in seconds.
	 *
	 * @return bool True if the cache was set successfully, false otherwise.
	 */
	public function set( $id, $w3tc_data, $w3tc_group = 'default', $expire = 0 ) {
		// Abort if this is a WP-CLI call, objectcache engine is set to Disk, and is disabled for WP-CLI.
		if ( $this->is_wpcli_disk() ) {
			return false;
		}

		if ( $this->_debug || $this->stats_enabled ) {
			$time_start = Util_Debug::microtime();
		}

		if ( empty( $w3tc_group ) ) {
			$w3tc_group = 'default';
		}

		$w3tc_key = $this->_get_cache_key( $id, $w3tc_group );

		if ( is_object( $w3tc_data ) ) {
			$w3tc_data = clone $w3tc_data;
		}

		$this->cache[ $w3tc_key ] = $w3tc_data;
		$return                   = true;
		$ext_return               = null;
		$cache_sets_inc           = 0;

		if (
			$this->_caching
				&& ! in_array( $w3tc_group, $this->nonpersistent_groups, true )
				&& $this->_check_can_cache_runtime( $w3tc_group )
		) {
			$cache = $this->_get_cache( null, $w3tc_group );

			if ( 'alloptions' === $id && 'options' === $w3tc_group ) {
				// alloptions are deserialized on the start when some classes are not loaded yet so postpone it until requested.
				foreach ( $w3tc_data as $k => $v ) {
					if ( is_object( $v ) ) {
						$w3tc_data[ $k ] = serialize( $v ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
					}
				}
			}

			$v              = array(
				'content'         => $w3tc_data,
				'key_version_all' => $this->key_version_all_get(),
			);
			$cache_sets_inc = 1;
			$ext_return     = $cache->set(
				$w3tc_key,
				$v,
				( $expire ? $expire : $this->_lifetime ),
				$w3tc_group
			);
			$return         = $ext_return;
		}

		if ( $this->_is_transient_group( $w3tc_group ) &&
			$this->_config->get_boolean( 'objectcache.fallback_transients' ) ) {
			$this->_transient_fallback_set( $id, $w3tc_data, $w3tc_group, $expire );
		}

		if ( $this->_debug || $this->stats_enabled ) {
			$time = Util_Debug::microtime() - $time_start;

			$this->cache_sets += $cache_sets_inc;
			$this->time_total += $time;

			if ( $this->_debug ) {
				if ( is_null( $ext_return ) ) {
					$reason = 'not set ' . $this->cache_reject_reason;
				} elseif ( $ext_return ) {
					$reason = 'put in cache';
				} else {
					$reason = 'failed';
				}

				$this->log_call(
					array(
						gmdate( 'r' ),
						'set',
						$w3tc_group,
						$id,
						$reason,
						( $w3tc_data ? strlen( serialize( $w3tc_data ) ) : 0 ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
						(int) ( $time * 1000000 ),
					)
				);
			}
		}

		return $return;
	}

	/**
	 * Sets multiple cached objects.
	 *
	 * @since 2.4.0
	 *
	 * @param array  $items  An associative array of data to cache, indexed by cache key.
	 * @param string $w3tc_group  The cache group.
	 * @param int    $expire The expiration time, in seconds.
	 *
	 * @return array An associative array of cache set results, indexed by cache key.
	 */
	public function set_multiple( array $items, $w3tc_group = '', $expire = 0 ) {
		if ( empty( $w3tc_group ) ) {
			$w3tc_group = 'default';
		}

		if ( empty( $items ) ) {
			return array();
		}

		// Abort if this is a WP-CLI call, objectcache engine is set to Disk, and is disabled for WP-CLI.
		if ( $this->is_wpcli_disk() ) {
			return array_fill_keys( array_keys( $items ), false );
		}

		$results            = array();
		$payload            = array();
		$cache_key_to_id    = array();
		$cache_sets_inc     = 0;
		$time_start_debug   = ( $this->_debug || $this->stats_enabled ) ? Util_Debug::microtime() : 0;
		$key_version_all    = $this->key_version_all_get();
		$persistent_allowed = (
			$this->_caching &&
			! in_array( $w3tc_group, $this->nonpersistent_groups, true ) &&
			$this->_check_can_cache_runtime( $w3tc_group )
		);

		foreach ( $items as $id => $w3tc_value ) {
			$cache_key = $this->_get_cache_key( $id, $w3tc_group );
			$stored    = $w3tc_value;

			$cache_key_to_id[ $cache_key ] = $id;
			if ( is_object( $stored ) ) {
				$stored = clone $stored;
			}

			$this->cache[ $cache_key ] = $stored;
			$results[ $id ]            = true;

			if ( $persistent_allowed ) {
				$stored_content = $stored;

				if ( 'alloptions' === $id && 'options' === $w3tc_group ) {
					foreach ( $stored_content as $k => $v ) {
						if ( is_object( $v ) ) {
							$stored_content[ $k ] = serialize( $v ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
						}
					}
				}

				$payload[ $cache_key ] = array(
					'content'         => $stored_content,
					'key_version_all' => $key_version_all,
				);
			}
		}

		if ( $persistent_allowed && ! empty( $payload ) ) {
			$cache          = $this->_get_cache( null, $w3tc_group );
			$results        = $this->set_multiple_to_cache(
				$cache,
				$payload,
				$results,
				$cache_key_to_id,
				$w3tc_group,
				( $expire ? $expire : $this->_lifetime )
			);
			$cache_sets_inc = count( $payload );
		}

		if ( $this->_is_transient_group( $w3tc_group ) &&
			$this->_config->get_boolean( 'objectcache.fallback_transients' ) ) {
			foreach ( $items as $id => $w3tc_value ) {
				$this->_transient_fallback_set( $id, $w3tc_value, $w3tc_group, $expire );
			}
		}

		if ( $this->_debug || $this->stats_enabled ) {
			$time              = Util_Debug::microtime() - $time_start_debug;
			$this->cache_sets += $cache_sets_inc;
			$this->time_total += $time;
		}

		return $results;
	}

	/**
	 * Deletes a cached object from the object cache.
	 *
	 * @param string $id    The cache key.
	 * @param string $w3tc_group The cache group.
	 * @param bool   $force Whether to force a cache deletion.
	 *
	 * @return bool True if the cache was deleted, false otherwise.
	 */
	public function delete( $id, $w3tc_group = 'default', $force = false ) {
		if ( ! $force && $this->get( $id, $w3tc_group ) === false ) {
			return false;
		}

		$w3tc_key = $this->_get_cache_key( $id, $w3tc_group );
		$return   = true;

		unset( $this->cache[ $w3tc_key ] );

		if ( $this->_caching && ! in_array( $w3tc_group, $this->nonpersistent_groups, true ) ) {
			$cache  = $this->_get_cache( null, $w3tc_group );
			$return = $cache->delete( $w3tc_key, $w3tc_group );
		}

		if ( $this->_is_transient_group( $w3tc_group ) &&
			$this->_config->get_boolean( 'objectcache.fallback_transients' ) ) {
			$this->_transient_fallback_delete( $id, $w3tc_group );
		}

		if ( $this->_debug ) {
			$this->log_call(
				array(
					gmdate( 'r' ),
					'delete',
					$w3tc_group,
					$id,
					( $return ? 'deleted' : 'discarded' ),
					0,
					0,
				)
			);
		}

		return $return;
	}

	/**
	 * Deletes multiple cached objects.
	 *
	 * @since 2.4.0
	 *
	 * @param array  $w3tc_keys   An array of cache keys to delete.
	 * @param string $w3tc_group  The cache group.
	 *
	 * @return array An associative array of cache delete results, indexed by cache key.
	 */
	public function delete_multiple( array $w3tc_keys, $w3tc_group = '' ) {
		$values = array();
		foreach ( $w3tc_keys as $w3tc_key ) {
			$values[ $w3tc_key ] = $this->delete( $w3tc_key, $w3tc_group );
		}
		return $values;
	}

	/**
	 * Adds a cached object to the object cache if it doesn't already exist.
	 *
	 * @param string $id     The cache key.
	 * @param mixed  $w3tc_data   The data to cache.
	 * @param string $w3tc_group  The cache group.
	 * @param int    $expire The expiration time, in seconds.
	 *
	 * @return bool True if the cache was added, false otherwise.
	 */
	public function add( $id, $w3tc_data, $w3tc_group = 'default', $expire = 0 ) {
		if ( $this->get( $id, $w3tc_group ) !== false ) {
			return false;
		}

		return $this->set( $id, $w3tc_data, $w3tc_group, $expire );
	}

	/**
	 * Adds multiple cached objects to the object cache if they don't already exist.
	 *
	 * @since 2.4.0
	 *
	 * @param array  $w3tc_data   An associative array of data to cache, indexed by cache key.
	 * @param string $w3tc_group  The cache group.
	 * @param int    $expire The expiration time, in seconds.
	 *
	 * @return array An associative array of cache add results, indexed by cache key.
	 */
	public function add_multiple( array $w3tc_data, $w3tc_group = '', $expire = 0 ) {
		$values = array();
		foreach ( $w3tc_data as $w3tc_key => $w3tc_value ) {
			$values[ $w3tc_key ] = $this->add( $w3tc_key, $w3tc_value, $w3tc_group, $expire );
		}
		return $values;
	}

	/**
	 * Replaces a cached object in the object cache if it already exists.
	 *
	 * @param string $id     The cache key.
	 * @param mixed  $w3tc_data   The data to cache.
	 * @param string $w3tc_group  The cache group.
	 * @param int    $expire The expiration time, in seconds.
	 *
	 * @return bool True if the cache was replaced, false otherwise.
	 */
	public function replace( $id, $w3tc_data, $w3tc_group = 'default', $expire = 0 ) {
		if ( $this->get( $id, $w3tc_group ) === false ) {
			return false;
		}

		return $this->set( $id, $w3tc_data, $w3tc_group, $expire );
	}

	/**
	 * Resets the cache by flushing runtime data.
	 *
	 * @return void
	 */
	public function reset() {
		$this->flush_runtime();
	}

	/**
	 * Flushes the entire object cache.
	 *
	 * @param string $reason The reason for flushing the cache.
	 *
	 * @return bool Always returns true.
	 */
	public function flush( $reason = '' ) {
		if ( $this->_debug || $this->stats_enabled ) {
			$time_start = Util_Debug::microtime();
		}
		if ( $this->_config->get_boolean( 'objectcache.debug_purge' ) ) {
			Util_Debug::log_purge( 'objectcache', 'flush', $reason );
		}

		$this->cache = array();

		global $w3_multisite_blogs;
		if ( isset( $w3_multisite_blogs ) ) {
			foreach ( $w3_multisite_blogs as $blog ) {
				$this->key_version_all_increment( $blog->userblog_id );
			}
		} else {
			if ( 0 !== $this->_blog_id ) {
				$this->key_version_all_increment( 0 );
			}

			$this->key_version_all_increment();
		}

		if ( $this->_debug || $this->stats_enabled ) {
			$time = Util_Debug::microtime() - $time_start;

			++$this->cache_flushes;
			$this->time_total += $time;

			if ( $this->_debug ) {
				$this->log_call(
					array(
						gmdate( 'r' ),
						'flush',
						'',
						'',
						$reason,
						0,
						(int) ( $time * 1000000 ),
					)
				);
			}
		}

		return true;
	}

	/**
	 * Clears all cached data in runtime.
	 *
	 * @return bool Returns true on success.
	 */
	public function flush_runtime() {
		$this->cache = array();

		if ( $this->_debug || $this->stats_enabled ) {
			$time = Util_Debug::microtime();

			++$this->cache_flushes;
			$this->time_total += $time;

			if ( $this->_debug ) {
				$this->log_call(
					array(
						gmdate( 'r' ),
						'flush_runtime',
						'',
						'',
						'',
						0,
						(int) ( $time * 1000000 ),
					)
				);
			}
		}

		return true;
	}

	/**
	 * Checks if a specific feature is supported.
	 *
	 * @param string $feature Feature to check for.
	 *
	 * @return bool Returns true if the feature is supported.
	 */
	public function supports( string $feature ) {
		return in_array( $feature, $this->supported_features, true );
	}

	/**
	 * Clears the cache for a specific group.
	 *
	 * @param string $w3tc_group The cache group to flush.
	 *
	 * @return bool Returns true on success.
	 */
	public function flush_group( $w3tc_group ) {
		if ( $this->_debug || $this->stats_enabled ) {
			$time_start = Util_Debug::microtime();
		}

		if ( $this->_config->get_boolean( 'objectcache.debug_purge' ) ) {
			Util_Debug::log_purge( 'objectcache', 'flush' );
		}

		$this->cache = array();

		global $w3_multisite_blogs;

		if ( isset( $w3_multisite_blogs ) ) {
			foreach ( $w3_multisite_blogs as $blog ) {
				$cache = $this->_get_cache( $blog->userblog_id );
				$cache->flush( $w3tc_group );
			}
		} else {
			if ( 0 !== $this->_blog_id ) {
				$cache = $this->_get_cache( 0 );
				$cache->flush( $w3tc_group );
			}

			$cache = $this->_get_cache();

			$cache->flush( $w3tc_group );
		}

		if ( $this->_debug || $this->stats_enabled ) {
			$time = Util_Debug::microtime() - $time_start;

			++$this->cache_flushes;
			$this->time_total += $time;

			if ( $this->_debug ) {
				$this->log_call(
					array(
						gmdate( 'r' ),
						'flush_group',
						'',
						'',
						'',
						0,
						(int) ( $time * 1000000 ),
					)
				);
			}
		}

		return true;
	}

	/**
	 * Adds global groups to be cached.
	 *
	 * @param array|string $groups Groups to be added.
	 *
	 * @return void
	 */
	public function add_global_groups( $groups ) {
		if ( ! is_array( $groups ) ) {
			$groups = (array) $groups;
		}

		$this->global_groups = array_merge( $this->global_groups, $groups );
		$this->global_groups = array_unique( $this->global_groups );
	}

	/**
	 * Adds non-persistent groups to be cached.
	 *
	 * @param array|string $groups Groups to be added.
	 *
	 * @return void
	 */
	public function add_nonpersistent_groups( $groups ) {
		if ( ! is_array( $groups ) ) {
			$groups = (array) $groups;
		}

		$this->nonpersistent_groups = array_merge( $this->nonpersistent_groups, $groups );
		$this->nonpersistent_groups = array_unique( $this->nonpersistent_groups );
	}

	/**
	 * Increments the value of a cached key.
	 *
	 * @param string $w3tc_key    The cache key to increment.
	 * @param int    $w3tc_offset The value to increment by.
	 * @param string $w3tc_group  The group the cache belongs to.
	 *
	 * @return int|false Returns the new value on success, or false if the key does not exist.
	 */
	public function incr( $w3tc_key, $w3tc_offset = 1, $w3tc_group = 'default' ) {
		$w3tc_value = $this->get( $w3tc_key, $w3tc_group );

		if ( false === $w3tc_value ) {
			return false;
		}

		if ( ! is_numeric( $w3tc_value ) ) {
			$w3tc_value = 0;
		}

		$w3tc_offset = (int) $w3tc_offset;
		$w3tc_value += $w3tc_offset;

		if ( $w3tc_value < 0 ) {
			$w3tc_value = 0;
		}

		$this->replace( $w3tc_key, $w3tc_value, $w3tc_group );

		return $w3tc_value;
	}

	/**
	 * Decrements the value of a cached key.
	 *
	 * @param string $w3tc_key    The cache key to decrement.
	 * @param int    $w3tc_offset The value to decrement by.
	 * @param string $w3tc_group  The group the cache belongs to.
	 *
	 * @return int|false Returns the new value on success, or false if the key does not exist.
	 */
	public function decr( $w3tc_key, $w3tc_offset = 1, $w3tc_group = 'default' ) {
		$w3tc_value = $this->get( $w3tc_key, $w3tc_group );

		if ( false === $w3tc_value ) {
			return false;
		}

		if ( ! is_numeric( $w3tc_value ) ) {
			$w3tc_value = 0;
		}

		$w3tc_offset = (int) $w3tc_offset;
		$w3tc_value -= $w3tc_offset;

		if ( $w3tc_value < 0 ) {
			$w3tc_value = 0;
		}

		$this->replace( $w3tc_key, $w3tc_value, $w3tc_group );

		return $w3tc_value;
	}

	/**
	 * Fallback function to retrieve transient data.
	 *
	 * @param string $transient The transient key.
	 * @param string $w3tc_group     The cache group.
	 *
	 * @return mixed|null The cached value, or null if not found.
	 */
	private function _transient_fallback_get( $transient, $w3tc_group ) {
		if ( 'transient' === $w3tc_group ) {
			$transient_option = '_transient_' . $transient;

			if ( function_exists( 'wp_installing' ) && ! wp_installing() ) {
				// If option is not in alloptions, it is not autoloaded and thus has a timeout.
				$alloptions = wp_load_alloptions();

				if ( ! isset( $alloptions[ $transient_option ] ) ) {
					$transient_timeout = '_transient_timeout_' . $transient;
					$timeout           = get_option( $transient_timeout );

					if ( false !== $timeout && $timeout < time() ) {
						delete_option( $transient_option );
						delete_option( $transient_timeout );
						$w3tc_value = false;
					}
				}
			}

			if ( ! isset( $w3tc_value ) ) {
				$w3tc_value = get_option( $transient_option );
			}
		} elseif ( 'site-transient' === $w3tc_group ) {
			// Core transients that do not have a timeout. Listed here so querying timeouts can be avoided.
			$no_timeout       = array( 'update_core', 'update_plugins', 'update_themes' );
			$transient_option = '_site_transient_' . $transient;

			if ( ! in_array( $transient, $no_timeout, true ) ) {
				$transient_timeout = '_site_transient_timeout_' . $transient;
				$timeout           = get_site_option( $transient_timeout );

				if ( false !== $timeout && $timeout < time() ) {
					delete_site_option( $transient_option );
					delete_site_option( $transient_timeout );
					$w3tc_value = false;
				}
			}

			if ( ! isset( $w3tc_value ) ) {
				$w3tc_value = get_site_option( $transient_option );
			}
		} else {
			$w3tc_value = false;
		}

		return $w3tc_value;
	}

	/**
	 * Fallback function to delete transient data.
	 *
	 * @param string $transient The transient key.
	 * @param string $w3tc_group     The cache group.
	 *
	 * @return void
	 */
	private function _transient_fallback_delete( $transient, $w3tc_group ) {
		if ( 'transient' === $w3tc_group ) {
			$option_timeout = '_transient_timeout_' . $transient;
			$option         = '_transient_' . $transient;
			$w3tc_result    = delete_option( $option );

			if ( $w3tc_result ) {
				delete_option( $option_timeout );
			}
		} elseif ( 'site-transient' === $w3tc_group ) {
			$option_timeout = '_site_transient_timeout_' . $transient;
			$option         = '_site_transient_' . $transient;
			$w3tc_result    = delete_site_option( $option );
			if ( $w3tc_result ) {
				delete_site_option( $option_timeout );
			}
		}
	}

	/**
	 * Fallback function to set transient data.
	 *
	 * @param string $transient  The transient key.
	 * @param mixed  $w3tc_value      The value to store.
	 * @param string $w3tc_group      The cache group.
	 * @param int    $expiration The expiration time in seconds.
	 *
	 * @return void
	 */
	private function _transient_fallback_set( $transient, $w3tc_value, $w3tc_group, $expiration ) {
		if ( 'transient' === $w3tc_group ) {
			$transient_timeout = '_transient_timeout_' . $transient;
			$transient_option  = '_transient_' . $transient;
			if ( false === get_option( $transient_option ) ) {
				$autoload = 'yes';
				if ( $expiration ) {
					$autoload = 'no';
					add_option( $transient_timeout, time() + $expiration, '', 'no' );
				}
				$w3tc_result = add_option( $transient_option, $w3tc_value, '', $autoload );
			} else {
				// If expiration is requested, but the transient has no timeout option,
				// delete, then re-create transient rather than update.
				$update = true;
				if ( $expiration ) {
					if ( false === get_option( $transient_timeout ) ) {
						delete_option( $transient_option );
						add_option( $transient_timeout, time() + $expiration, '', 'no' );
						$w3tc_result = add_option( $transient_option, $w3tc_value, '', 'no' );
						$update      = false;
					} else {
						update_option( $transient_timeout, time() + $expiration );
					}
				}
				if ( $update ) {
					$w3tc_result = update_option( $transient_option, $w3tc_value );
				}
			}
		} elseif ( 'site-transient' === $w3tc_group ) {
			$transient_timeout = '_site_transient_timeout_' . $transient;
			$option            = '_site_transient_' . $transient;

			if ( false === get_site_option( $option ) ) {
				if ( $expiration ) {
					add_site_option( $transient_timeout, time() + $expiration );
				}

				$w3tc_result = add_site_option( $option, $w3tc_value );
			} else {
				if ( $expiration ) {
					update_site_option( $transient_timeout, time() + $expiration );
				}

				$w3tc_result = update_site_option( $option, $w3tc_value );
			}
		}
	}

	/**
	 * Switches the blog context for caching.
	 *
	 * @param int $blog_id The blog ID to switch to.
	 *
	 * @return void
	 */
	public function switch_blog( $blog_id ) {
		$this->reset();
		$this->_blog_id = $blog_id;
	}

	/**
	 * Retrieves the version number for all keys in the cache.
	 *
	 * @param int|null $blog_id The blog ID to get the version for.
	 *
	 * @return int The version number.
	 */
	private function key_version_all_get( $blog_id = null ) {
		if ( is_null( $this->key_version_all ) ) {
			$cache = $this->_get_cache( $blog_id, 'key_version_all' );
			$v     = $cache->get( 'key_version_all', 'key_version_all' );

			$this->key_version_all = empty( $v['content'] ) ? 1 : max( 1, intval( $v['content'] ) );
		}

		return $this->key_version_all;
	}

	/**
	 * Increments the version number for all keys in the cache.
	 *
	 * @param int|null $blog_id The blog ID to increment the version for.
	 *
	 * @return void
	 */
	private function key_version_all_increment( $blog_id = null ) {
		$cache = $this->_get_cache( $blog_id, 'key_version_all' );
		$cache->set(
			'key_version_all',
			array( 'content' => $this->key_version_all_get( $blog_id ) + 1 ),
			0,
			'key_version_all'
		);
	}

	/**
	 * Retrieves the cache key for a given ID and group.
	 *
	 * @param string $id    The cache ID.
	 * @param string $w3tc_group The cache group.
	 *
	 * @return string The generated cache key.
	 */
	private function _get_cache_key( $id, $w3tc_group = 'default' ) {
		if ( ! $w3tc_group ) {
			$w3tc_group = 'default';
		}

		$blog_id = $this->_blog_id;

		if ( in_array( $w3tc_group, $this->global_groups, true ) ) {
			$blog_id = 0;
		}

		return $blog_id . $w3tc_group . $id;
	}

	/**
	 * Persist multiple cache entries using the most efficient method available.
	 *
	 * @since 2.9.0
	 *
	 * @param object $cache           Cache engine instance.
	 * @param array  $payload         Map of cache_key => structured payload.
	 * @param array  $results         Current result map keyed by original IDs.
	 * @param array  $cache_key_to_id Map of cache_key => original ID.
	 * @param string $w3tc_group           Cache group.
	 * @param int    $expire          Expiration.
	 *
	 * @return array Updated result map keyed by original IDs.
	 */
	private function set_multiple_to_cache( $cache, array $payload, array $results, array $cache_key_to_id, $w3tc_group, $expire ) {
		if ( method_exists( $cache, 'set_multi' ) ) {
			$response = $cache->set_multi( $payload, $w3tc_group, $expire );

			foreach ( $payload as $cache_key => $_ ) {
				$id             = isset( $cache_key_to_id[ $cache_key ] ) ? $cache_key_to_id[ $cache_key ] : $cache_key;
				$results[ $id ] = is_array( $response ) ? (bool) ( $response[ $cache_key ] ?? false ) : (bool) $response;
			}
		} else {
			foreach ( $payload as $cache_key => $w3tc_value ) {
				$id             = isset( $cache_key_to_id[ $cache_key ] ) ? $cache_key_to_id[ $cache_key ] : $cache_key;
				$results[ $id ] = $cache->set( $cache_key, $w3tc_value, $expire, $w3tc_group );
			}
		}

		return $results;
	}

	/**
	 * Retrieves the cache configuration for usage statistics.
	 *
	 * @return array The cache configuration.
	 */
	public function get_usage_statistics_cache_config() {
		$w3tc_engine = $this->_config->get_string( 'objectcache.engine' );

		switch ( $w3tc_engine ) {
			case 'memcached':
				$engine_config = array(
					'servers'           => $this->_config->get_array( 'objectcache.memcached.servers' ),
					'persistent'        => $this->_config->get_boolean( 'objectcache.memcached.persistent' ),
					'aws_autodiscovery' => $this->_config->get_boolean( 'objectcache.memcached.aws_autodiscovery' ),
					'username'          => $this->_config->get_string( 'objectcache.memcached.username' ),
					'password'          => $this->_config->get_string( 'objectcache.memcached.password' ),
					'binary_protocol'   => $this->_config->get_boolean( 'objectcache.memcached.binary_protocol' ),
				);
				break;

			case 'redis':
				$engine_config = array(
					'servers'                 => $this->_config->get_array( 'objectcache.redis.servers' ),
					'verify_tls_certificates' => $this->_config->get_boolean( 'objectcache.redis.verify_tls_certificates' ),
					'persistent'              => $this->_config->get_boolean( 'objectcache.redis.persistent' ),
					'timeout'                 => $this->_config->get_integer( 'objectcache.redis.timeout' ),
					'retry_interval'          => $this->_config->get_integer( 'objectcache.redis.retry_interval' ),
					'read_timeout'            => $this->_config->get_integer( 'objectcache.redis.read_timeout' ),
					'dbid'                    => $this->_config->get_integer( 'objectcache.redis.dbid' ),
					'password'                => $this->_config->get_string( 'objectcache.redis.password' ),
				);
				break;

			default:
				$engine_config = array();
		}

		$engine_config['engine'] = $w3tc_engine;

		return $engine_config;
	}

	/**
	 * Retrieves the cache instance for a given blog ID and group.
	 *
	 * @param int|null $blog_id The blog ID.
	 * @param string   $w3tc_group   The cache group.
	 *
	 * @return Cache The cache instance.
	 */
	private function _get_cache( $blog_id = null, $w3tc_group = '' ) {
		static $cache = array();

		if ( is_null( $blog_id ) && ! in_array( $w3tc_group, $this->global_groups, true ) ) {
			$blog_id = $this->_blog_id;
		} elseif ( is_null( $blog_id ) ) {
			$blog_id = 0;
		}

		if ( ! isset( $cache[ $blog_id ] ) ) {
			$w3tc_engine = $this->_config->get_string( 'objectcache.engine' );

			switch ( $w3tc_engine ) {
				case 'memcached':
					$engine_config = array(
						'servers'           => $this->_config->get_array( 'objectcache.memcached.servers' ),
						'persistent'        => $this->_config->get_boolean( 'objectcache.memcached.persistent' ),
						'aws_autodiscovery' => $this->_config->get_boolean( 'objectcache.memcached.aws_autodiscovery' ),
						'username'          => $this->_config->get_string( 'objectcache.memcached.username' ),
						'password'          => $this->_config->get_string( 'objectcache.memcached.password' ),
						'binary_protocol'   => $this->_config->get_boolean( 'objectcache.memcached.binary_protocol' ),
					);
					break;

				case 'redis':
					$engine_config = array(
						'servers'                 => $this->_config->get_array( 'objectcache.redis.servers' ),
						'verify_tls_certificates' => $this->_config->get_boolean( 'objectcache.redis.verify_tls_certificates' ),
						'persistent'              => $this->_config->get_boolean( 'objectcache.redis.persistent' ),
						'timeout'                 => $this->_config->get_integer( 'objectcache.redis.timeout' ),
						'retry_interval'          => $this->_config->get_integer( 'objectcache.redis.retry_interval' ),
						'read_timeout'            => $this->_config->get_integer( 'objectcache.redis.read_timeout' ),
						'dbid'                    => $this->_config->get_integer( 'objectcache.redis.dbid' ),
						'password'                => $this->_config->get_string( 'objectcache.redis.password' ),
					);
					break;

				case 'file':
					$engine_config = array(
						'section'         => 'object',
						'locking'         => $this->_config->get_boolean( 'objectcache.file.locking' ),
						'flush_timelimit' => $this->_config->get_integer( 'timelimit.cache_flush' ),
					);
					break;

				default:
					$engine_config = array();
			}

			$engine_config['blog_id']     = $blog_id;
			$engine_config['module']      = 'object';
			$engine_config['host']        = Util_Environment::host();
			$engine_config['instance_id'] = Util_Environment::instance_id();

			$cache[ $blog_id ] = Cache::instance( $w3tc_engine, $engine_config );
		}

		return $cache[ $blog_id ];
	}

	/**
	 * Determines whether caching is enabled based on configuration.
	 *
	 * @return bool Returns true if caching is enabled, false otherwise.
	 */
	private function _can_cache() {
		// Skip if disabled.
		if ( ! $this->_config->getf_boolean( 'objectcache.enabled' ) ) {
			$this->cache_reject_reason = 'objectcache.disabled';

			return false;
		}

		// Check for DONOTCACHEOBJECT constant.
		if ( defined( 'DONOTCACHEOBJECT' ) && DONOTCACHEOBJECT ) {
			$this->cache_reject_reason = 'DONOTCACHEOBJECT';

			return false;
		}

		return true;
	}

	/**
	 * Checks if caching is allowed for runtime based on the group.
	 *
	 * @param string $w3tc_group The cache group to check.
	 *
	 * @return bool Returns true if caching is allowed for the group.
	 */
	private function _check_can_cache_runtime( $w3tc_group ) {
		// Need to be handled in wp admin as well as frontend.
		if ( $this->_is_transient_group( $w3tc_group ) ) {
			return true;
		}

		if ( null !== $this->_can_cache_dynamic ) {
			return $this->_can_cache_dynamic;
		}

		if ( $this->_config->get_boolean( 'objectcache.enabled_for_wp_admin' ) ) {
			$this->_can_cache_dynamic = true;
		} elseif (
			$this->_caching
				&& defined( 'WP_ADMIN' )
				&& ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX )
		) {
			$this->_can_cache_dynamic  = false;
			$this->cache_reject_reason = 'WP_ADMIN defined';

			return $this->_can_cache_dynamic;
		}

		return $this->_caching;
	}

	/**
	 * Determines whether the specified group is a transient group.
	 *
	 * @param string $w3tc_group The cache group to check.
	 *
	 * @return bool Returns true if the group is transient.
	 */
	private function _is_transient_group( $w3tc_group ) {
		return in_array( $w3tc_group, array( 'transient', 'site-transient' ), true );
	}

	/**
	 * Appends information about object cache usage to the footer comment.
	 *
	 * @param array $strings The array of strings to append the data to.
	 *
	 * @return array The modified array of strings.
	 */
	public function w3tc_footer_comment( $strings ) {
		$reason = $this->get_reject_reason();
		$append = empty( $reason ) ? '' : sprintf( ' (%1$s)', $reason );

		$strings[] = sprintf(
			// translators: 1: Cache hits, 2: Cache total cache objects, 3: Engine anme, 4: Reason.
			__( 'Object Caching %1$d/%2$d objects using %3$s%4$s', 'w3-total-cache' ),
			$this->cache_hits,
			$this->cache_total,
			Cache::engine_name( $this->_config->get_string( 'objectcache.engine' ) ),
			$append
		);

		if ( $this->_config->get_boolean( 'objectcache.debug' ) ) {
			$strings[] = '';
			$strings[] = __( 'Object Cache debug info:', 'w3-total-cache' );
			$strings[] = sprintf( '%s%s', str_pad( 'Caching: ', 20 ), ( $this->_caching ? 'enabled' : 'disabled' ) );
			$strings[] = sprintf( '%s%d', str_pad( 'Total calls: ', 20 ), $this->cache_total );
			$strings[] = sprintf( '%s%d', str_pad( 'Cache hits: ', 20 ), $this->cache_hits );
			$strings[] = sprintf( '%s%.4f', str_pad( 'Total time: ', 20 ), $this->time_total );
		}

		return $strings;
	}

	/**
	 * Tracks object cache usage statistics.
	 *
	 * @param Storage $storage The storage instance to track statistics in.
	 *
	 * @return void
	 */
	public function w3tc_usage_statistics_of_request( $storage ) {
		$storage->counter_add( 'objectcache_get_total', $this->cache_total );
		$storage->counter_add( 'objectcache_get_hits', $this->cache_hits );
		$storage->counter_add( 'objectcache_sets', $this->cache_sets );
		$storage->counter_add( 'objectcache_flushes', $this->cache_flushes );
		$storage->counter_add( 'objectcache_time_ms', (int) ( $this->time_total * 1000 ) );
	}

	/**
	 * Retrieves the reason why the cache is being rejected.
	 *
	 * @return string The rejection reason.
	 */
	public function get_reject_reason() {
		if ( is_null( $this->cache_reject_reason ) ) {
			return '';
		}

		return $this->_get_reject_reason_message( $this->cache_reject_reason );
	}

	/**
	 * Retrieves a rejection message based on a given key.
	 *
	 * @param string $w3tc_key The rejection key.
	 *
	 * @return string The rejection message.
	 */
	private function _get_reject_reason_message( $w3tc_key ) {
		if ( ! function_exists( '__' ) ) {
			return $w3tc_key;
		}

		switch ( $w3tc_key ) {
			case 'objectcache.disabled':
				return __( 'Object caching is disabled', 'w3-total-cache' );
			case 'DONOTCACHEOBJECT':
				return __( 'DONOTCACHEOBJECT constant is defined', 'w3-total-cache' );
			default:
				return '';
		}
	}


	/**
	 * Logs cache-related calls for debugging purposes.
	 *
	 * @param array $w3tc_data The data to log.
	 *
	 * @return void
	 */
	private function log_call( array $w3tc_data ): void {
		$filepath = Util_Debug::log_filename( 'objectcache-calls' );
		$content  = implode( "\t", $w3tc_data ) . PHP_EOL;

		file_put_contents( $filepath, $content, FILE_APPEND );
	}

	/**
	 * Check if this is a WP-CLI call and objectcache.engine is using Disk and disabled for WP-CLI.
	 *
	 * @since  2.8.2
	 *
	 * @return bool True if running WP-CLI with a file-based object cache, false otherwise.
	 */
	private function is_wpcli_disk(): bool {
		$is_engine_disk = 'file' === $this->_config->get_string( 'objectcache.engine' );
		$is_wpcli_disk  = $this->_config->get_boolean( 'objectcache.wpcli_disk' );
		return defined( 'WP_CLI' ) && \WP_CLI && $is_engine_disk && ! $is_wpcli_disk;
	}
}
