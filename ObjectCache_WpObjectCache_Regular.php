<?php
/**
 * File: ObjectCache_WpObjectCache_Regular.php
 *
 * @package W3TC
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore, PSR2.Methods.MethodDeclaration.Underscore
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
	 * PHP5 style constructor
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
	 * Get from the cache
	 *
	 * @param string    $id    ID.
	 * @param string    $group Group.
	 * @param bool      $force Force.
	 * @param bool|null $found Found.
	 *
	 * @return mixed
	 */
	public function get( $id, $group = 'default', $force = false, &$found = null ) {
		// Abort if this is a WP-CLI call, objectcache engine is set to Disk, and is disabled for WP-CLI.
		if ( $this->is_wpcli_disk() ) {
			return false;
		}

		if ( $this->_debug || $this->stats_enabled ) {
			$time_start = Util_Debug::microtime();
		}

		if ( empty( $group ) ) {
			$group = 'default';
		}

		$key             = $this->_get_cache_key( $id, $group );
		$in_incall_cache = isset( $this->cache[ $key ] );
		$fallback_used   = false;

		$cache_total_inc = 0;
		$cache_hits_inc  = 0;

		if ( $in_incall_cache && ! $force ) {
			$found = true;
			$value = $this->cache[ $key ];
		} elseif (
			$this->_caching
				&& ! in_array( $group, $this->nonpersistent_groups, true )
				&& $this->_check_can_cache_runtime( $group )
		) {
			$cache = $this->_get_cache( null, $group );
			$v     = $cache->get( $key, $group );

			/* // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
				For debugging
				$a = $cache->_get_with_old_raw( $key );
				$path = $cache->get_full_path( $key);
				$returned = 'x ' . $path . ' ' .
					(is_readable( $path ) ? ' readable ' : ' not-readable ') .
					json_encode($a);
			*/

			$cache_total_inc = 1;

			if (
				is_array( $v )
					&& isset( $v['content'] )
					&& isset( $v['key_version_all'] )
					&& intval( $v['key_version_all'] ) >= $this->key_version_all_get()
			) {
				$found          = true;
				$value          = $v['content'];
				$cache_hits_inc = 1;
			} else {
				$found = false;
				$value = false;
			}
		} else {
			$found = false;
			$value = false;
		}

		if ( null === $value ) {
			$value = false;
		}

		if ( is_object( $value ) ) {
			$value = clone $value;
		}

		if (
			! $found
				&& $this->_is_transient_group( $group )
				&& $this->_config->get_boolean( 'objectcache.fallback_transients' )
		) {
			$fallback_used = true;
			$value         = $this->_transient_fallback_get( $id, $group );
			$found         = ( false !== $value );
		}

		if ( $found && ! $in_incall_cache ) {
			$this->cache[ $key ] = $value;
		}

		/**
		 * Add debug info
		 */
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
					} else {
						if ( ! $found ) {
							if ( $cache_total_inc <= 0 ) {
								$returned = 'not tried cache';
							} else {
								$returned = 'not in cache';
							}
						} else {
							$returned = 'from persistent cache';
						}
					}

					$this->log_call(
						array(
							gmdate( 'r' ),
							'get',
							$group,
							$id,
							$returned,
							( $value ? strlen( serialize( $value ) ) : 0 ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
							(int) ( $time * 1000000 ),
						)
					);
				}
			}
		}

		return $value;
	}

	/**
	 * Get multiple from the cache
	 *
	 * @since 2.2.8
	 *
	 * @param array  $ids   IDs.
	 * @param string $group Group.
	 * @param bool   $force Force flag.
	 *
	 * @return mixed
	 */
	public function get_multiple( $ids, $group = 'default', $force = false ) {
		$found_cache = array();

		foreach ( $ids as $id ) {
			$found_cache[ $id ] = $this->get( $id, $group, $force );
		}

		return $found_cache;
	}

	/**
	 * Set to the cache
	 *
	 * @param string  $id     ID.
	 * @param mixed   $data   Data.
	 * @param string  $group  Group.
	 * @param integer $expire Expire.
	 *
	 * @return boolean
	 */
	public function set( $id, $data, $group = 'default', $expire = 0 ) {
		// Abort if this is a WP-CLI call, objectcache engine is set to Disk, and is disabled for WP-CLI.
		if ( $this->is_wpcli_disk() ) {
			return false;
		}

		if ( $this->_debug || $this->stats_enabled ) {
			$time_start = Util_Debug::microtime();
		}

		if ( empty( $group ) ) {
			$group = 'default';
		}

		$key = $this->_get_cache_key( $id, $group );

		if ( is_object( $data ) ) {
			$data = clone $data;
		}

		$this->cache[ $key ] = $data;
		$return              = true;
		$ext_return          = null;
		$cache_sets_inc      = 0;

		if (
			$this->_caching
				&& ! in_array( $group, $this->nonpersistent_groups, true )
				&& $this->_check_can_cache_runtime( $group )
		) {
			$cache = $this->_get_cache( null, $group );

			if ( 'alloptions' === $id && 'options' === $group ) {
				// alloptions are deserialized on the start when some classes are not loaded yet so postpone it until requested.
				foreach ( $data as $k => $v ) {
					if ( is_object( $v ) ) {
						$data[ $k ] = serialize( $v ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
					}
				}
			}

			$v              = array(
				'content'         => $data,
				'key_version_all' => $this->key_version_all_get(),
			);
			$cache_sets_inc = 1;
			$ext_return     = $cache->set(
				$key,
				$v,
				( $expire ? $expire : $this->_lifetime ),
				$group
			);
			$return         = $ext_return;
		}

		if ( $this->_is_transient_group( $group ) &&
			$this->_config->get_boolean( 'objectcache.fallback_transients' ) ) {
			$this->_transient_fallback_set( $id, $data, $group, $expire );
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
						$group,
						$id,
						$reason,
						( $data ? strlen( serialize( $data ) ) : 0 ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
						(int) ( $time * 1000000 ),
					)
				);
			}
		}

		return $return;
	}

	/**
	 * Sets multiple values to the cache in one call.
	 *
	 * @since 2.2.8
	 *
	 * @param array  $data   Array of keys and values to be set.
	 * @param string $group  Optional. Where the cache contents are grouped. Default empty.
	 * @param int    $expire Optional. When to expire the cache contents, in seconds.
	 *                       Default 0 (no expiration).
	 *
	 * @return bool[] Array of return values, grouped by key. Each value is either
	 *                true on success, or false on failure.
	 */
	public function set_multiple( array $data, $group = '', $expire = 0 ) {
		$values = array();
		foreach ( $data as $key => $value ) {
			$values[ $key ] = $this->set( $key, $value, $group, $expire );
		}
		return $values;
	}

	/**
	 * Delete from the cache
	 *
	 * @param string $id    ID.
	 * @param string $group Group.
	 * @param bool   $force Force.
	 *
	 * @return boolean
	 */
	public function delete( $id, $group = 'default', $force = false ) {
		if ( ! $force && $this->get( $id, $group ) === false ) {
			return false;
		}

		$key    = $this->_get_cache_key( $id, $group );
		$return = true;

		unset( $this->cache[ $key ] );

		if ( $this->_caching && ! in_array( $group, $this->nonpersistent_groups, true ) ) {
			$cache  = $this->_get_cache( null, $group );
			$return = $cache->delete( $key, $group );
		}

		if ( $this->_is_transient_group( $group ) &&
			$this->_config->get_boolean( 'objectcache.fallback_transients' ) ) {
			$this->_transient_fallback_delete( $id, $group );
		}

		if ( $this->_debug ) {
			$this->log_call(
				array(
					gmdate( 'r' ),
					'delete',
					$group,
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
	 * Deletes multiple values from the cache in one call.
	 *
	 * @since 2.2.8
	 *
	 * @param array  $keys  Array of keys under which the cache to deleted.
	 * @param string $group Optional. Where the cache contents are grouped. Default empty.
	 *
	 * @return bool[] Array of return values, grouped by key. Each value is either
	 *                true on success, or false if the contents were not deleted.
	 */
	public function delete_multiple( array $keys, $group = '' ) {
		$values = array();
		foreach ( $keys as $key ) {
			$values[ $key ] = $this->delete( $key, $group );
		}
		return $values;
	}

	/**
	 * Add to the cache
	 *
	 * @param string  $id     ID.
	 * @param mixed   $data   Data.
	 * @param string  $group  Group.
	 * @param integer $expire Expire.
	 *
	 * @return boolean
	 */
	public function add( $id, $data, $group = 'default', $expire = 0 ) {
		if ( $this->get( $id, $group ) !== false ) {
			return false;
		}

		return $this->set( $id, $data, $group, $expire );
	}

	/**
	 * Add multiple to the cache
	 *
	 * @since 2.2.8
	 *
	 * @param array  $data   Array of keys and values to be added.
	 * @param string $group  Optional. Where the cache contents are grouped. Default empty.
	 * @param int    $expire Optional. When to expire the cache contents, in seconds.
	 *                       Default 0 (no expiration).
	 *
	 * @return bool[] Array of return values, grouped by key. Each value is either
	 *                true on success, or false if cache key and group already exist.
	 */
	public function add_multiple( array $data, $group = '', $expire = 0 ) {
		$values = array();
		foreach ( $data as $key => $value ) {
			$values[ $key ] = $this->add( $key, $value, $group, $expire );
		}
		return $values;
	}

	/**
	 * Replace in the cache
	 *
	 * @param string  $id     ID.
	 * @param mixed   $data   Data.
	 * @param string  $group  Group.
	 * @param integer $expire Expire.
	 *
	 * @return boolean
	 */
	public function replace( $id, $data, $group = 'default', $expire = 0 ) {
		if ( $this->get( $id, $group ) === false ) {
			return false;
		}

		return $this->set( $id, $data, $group, $expire );
	}

	/**
	 * Reset keys
	 *
	 * @return void
	 */
	public function reset() {
		$this->flush_runtime();
	}

	/**
	 * Flush cache
	 *
	 * @param string $reason Reason.
	 *
	 * @return boolean
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

			$this->cache_flushes++;
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
	 * Flush runtime.
	 *
	 * @return boolean
	 */
	public function flush_runtime() {
		$this->cache = array();

		if ( $this->_debug || $this->stats_enabled ) {
			$time = Util_Debug::microtime();

			$this->cache_flushes++;
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
	 * Check supported features.
	 *
	 * @param string $feature Feature.
	 *
	 * @return boolean
	 */
	public function supports( string $feature ) {
		return in_array( $feature, $this->supported_features, true );
	}

	/**
	 * Flush group.
	 *
	 * @param string $group Group.
	 *
	 * @return boolean
	 */
	public function flush_group( $group ) {
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
				$cache->flush( $group );
			}
		} else {
			if ( 0 !== $this->_blog_id ) {
				$cache = $this->_get_cache( 0 );
				$cache->flush( $group );
			}

			$cache = $this->_get_cache();

			$cache->flush( $group );
		}

		if ( $this->_debug || $this->stats_enabled ) {
			$time = Util_Debug::microtime() - $time_start;

			$this->cache_flushes++;
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
	 * Add global groups
	 *
	 * @param array $groups Groups.
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
	 * Add non-persistent groups
	 *
	 * @param array $groups Groups.
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
	 * Increment numeric cache item's value
	 *
	 * @param int|string $key    The cache key to increment.
	 * @param int        $offset The amount by which to increment the item's value. Default is 1.
	 * @param string     $group  The group the key is in.
	 *
	 * @return bool|int False on failure, the item's new value on success.
	 */
	public function incr( $key, $offset = 1, $group = 'default' ) {
		$value = $this->get( $key, $group );

		if ( false === $value ) {
			return false;
		}

		if ( ! is_numeric( $value ) ) {
			$value = 0;
		}

		$offset = (int) $offset;
		$value += $offset;

		if ( $value < 0 ) {
			$value = 0;
		}

		$this->replace( $key, $value, $group );

		return $value;
	}

	/**
	 * Decrement numeric cache item's value
	 *
	 * @param int|string $key    The cache key to increment.
	 * @param int        $offset The amount by which to decrement the item's value. Default is 1.
	 * @param string     $group  The group the key is in.
	 *
	 * @return bool|int False on failure, the item's new value on success.
	 */
	public function decr( $key, $offset = 1, $group = 'default' ) {
		$value = $this->get( $key, $group );

		if ( false === $value ) {
			return false;
		}

		if ( ! is_numeric( $value ) ) {
			$value = 0;
		}

		$offset = (int) $offset;
		$value -= $offset;

		if ( $value < 0 ) {
			$value = 0;
		}

		$this->replace( $key, $value, $group );

		return $value;
	}

	/**
	 * Get transient fallback
	 *
	 * @param string $transient Transient key.
	 * @param string $group     The group the key is in.
	 *
	 * @return bool|int False on failure, the item's new value on success.
	 */
	private function _transient_fallback_get( $transient, $group ) {
		if ( 'transient' === $group ) {
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
						$value = false;
					}
				}
			}

			if ( ! isset( $value ) ) {
				$value = get_option( $transient_option );
			}
		} elseif ( 'site-transient' === $group ) {
			// Core transients that do not have a timeout. Listed here so querying timeouts can be avoided.
			$no_timeout       = array( 'update_core', 'update_plugins', 'update_themes' );
			$transient_option = '_site_transient_' . $transient;

			if ( ! in_array( $transient, $no_timeout, true ) ) {
				$transient_timeout = '_site_transient_timeout_' . $transient;
				$timeout           = get_site_option( $transient_timeout );

				if ( false !== $timeout && $timeout < time() ) {
					delete_site_option( $transient_option );
					delete_site_option( $transient_timeout );
					$value = false;
				}
			}

			if ( ! isset( $value ) ) {
				$value = get_site_option( $transient_option );
			}
		} else {
			$value = false;
		}

		return $value;
	}

	/**
	 * Delete transient fallback
	 *
	 * @param string $transient Transient key.
	 * @param string $group     The group the key is in.
	 *
	 * @return void
	 */
	private function _transient_fallback_delete( $transient, $group ) {
		if ( 'transient' === $group ) {
			$option_timeout = '_transient_timeout_' . $transient;
			$option         = '_transient_' . $transient;
			$result         = delete_option( $option );

			if ( $result ) {
				delete_option( $option_timeout );
			}
		} elseif ( 'site-transient' === $group ) {
			$option_timeout = '_site_transient_timeout_' . $transient;
			$option         = '_site_transient_' . $transient;
			$result         = delete_site_option( $option );
			if ( $result ) {
				delete_site_option( $option_timeout );
			}
		}
	}

	/**
	 * Set transient fallback
	 *
	 * @param string    $transient Transient key.
	 * @param mixed     $value     Transient value.
	 * @param string    $group     The group the key is in.
	 * @param bool|null $expiration Expiration.
	 *
	 * @return void
	 */
	private function _transient_fallback_set( $transient, $value, $group, $expiration ) {
		if ( 'transient' === $group ) {
			$transient_timeout = '_transient_timeout_' . $transient;
			$transient_option  = '_transient_' . $transient;
			if ( false === get_option( $transient_option ) ) {
				$autoload = 'yes';
				if ( $expiration ) {
					$autoload = 'no';
					add_option( $transient_timeout, time() + $expiration, '', 'no' );
				}
				$result = add_option( $transient_option, $value, '', $autoload );
			} else {
				// If expiration is requested, but the transient has no timeout option,
				// delete, then re-create transient rather than update.
				$update = true;
				if ( $expiration ) {
					if ( false === get_option( $transient_timeout ) ) {
						delete_option( $transient_option );
						add_option( $transient_timeout, time() + $expiration, '', 'no' );
						$result = add_option( $transient_option, $value, '', 'no' );
						$update = false;
					} else {
						update_option( $transient_timeout, time() + $expiration );
					}
				}
				if ( $update ) {
					$result = update_option( $transient_option, $value );
				}
			}
		} elseif ( 'site-transient' === $group ) {
			$transient_timeout = '_site_transient_timeout_' . $transient;
			$option            = '_site_transient_' . $transient;

			if ( false === get_site_option( $option ) ) {
				if ( $expiration ) {
					add_site_option( $transient_timeout, time() + $expiration );
				}

				$result = add_site_option( $option, $value );
			} else {
				if ( $expiration ) {
					update_site_option( $transient_timeout, time() + $expiration );
				}

				$result = update_site_option( $option, $value );
			}
		}
	}

	/**
	 * Switches context to another blog
	 *
	 * @param integer $blog_id Blog ID.
	 *
	 * @return void
	 */
	public function switch_blog( $blog_id ) {
		$this->reset();
		$this->_blog_id = $blog_id;
	}

	/**
	 * Global key fetch.
	 *
	 * @param integer $blog_id Blog ID.
	 *
	 * @return string
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
	 * Global key increment.
	 *
	 * @param integer $blog_id Blog ID.
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
	 * Returns cache key
	 *
	 * @param string $id    ID.
	 * @param string $group Group.
	 *
	 * @return string
	 */
	private function _get_cache_key( $id, $group = 'default' ) {
		if ( ! $group ) {
			$group = 'default';
		}

		$blog_id = $this->_blog_id;

		if ( in_array( $group, $this->global_groups, true ) ) {
			$blog_id = 0;
		}

		return $blog_id . $group . $id;
	}

	/**
	 * Get usage statistics cache config.
	 *
	 * @return array
	 */
	public function get_usage_statistics_cache_config() {
		$engine = $this->_config->get_string( 'objectcache.engine' );

		switch ( $engine ) {
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

		$engine_config['engine'] = $engine;

		return $engine_config;
	}

	/**
	 * Returns cache object
	 *
	 * @param int|null $blog_id Blog ID.
	 * @param string   $group   Group.
	 *
	 * @return W3_Cache_Base
	 */
	private function _get_cache( $blog_id = null, $group = '' ) {
		static $cache = array();

		if ( is_null( $blog_id ) && ! in_array( $group, $this->global_groups, true ) ) {
			$blog_id = $this->_blog_id;
		} elseif ( is_null( $blog_id ) ) {
			$blog_id = 0;
		}

		if ( ! isset( $cache[ $blog_id ] ) ) {
			$engine = $this->_config->get_string( 'objectcache.engine' );

			switch ( $engine ) {
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

			$cache[ $blog_id ] = Cache::instance( $engine, $engine_config );
		}

		return $cache[ $blog_id ];
	}

	/**
	 * Check if caching allowed on init
	 *
	 * @return boolean
	 */
	private function _can_cache() {
		/**
		 * Skip if disabled
		 */
		if ( ! $this->_config->getf_boolean( 'objectcache.enabled' ) ) {
			$this->cache_reject_reason = 'objectcache.disabled';

			return false;
		}

		/**
		 * Check for DONOTCACHEOBJECT constant
		 */
		if ( defined( 'DONOTCACHEOBJECT' ) && DONOTCACHEOBJECT ) {
			$this->cache_reject_reason = 'DONOTCACHEOBJECT';

			return false;
		}

		return true;
	}

	/**
	 * Returns if we can cache, that condition can change in runtime
	 *
	 * @param unknown $group Group.
	 *
	 * @return boolean
	 */
	private function _check_can_cache_runtime( $group ) {
		// Need to be handled in wp admin as well as frontend.
		if ( $this->_is_transient_group( $group ) ) {
			return true;
		}

		if ( null !== $this->_can_cache_dynamic ) {
			return $this->_can_cache_dynamic;
		}

		if ( $this->_config->get_boolean( 'objectcache.enabled_for_wp_admin' ) ) {
			$this->_can_cache_dynamic = true;
		} else {
			if (
				$this->_caching
					&& defined( 'WP_ADMIN' )
					&& ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX )
			) {
				$this->_can_cache_dynamic  = false;
				$this->cache_reject_reason = 'WP_ADMIN defined';

				return $this->_can_cache_dynamic;
			}
		}

		return $this->_caching;
	}

	/**
	 * Is transient group.
	 *
	 * @param unknown $group Group.
	 *
	 * @return boolean
	 */
	private function _is_transient_group( $group ) {
		return in_array( $group, array( 'transient', 'site-transient' ), true );
	}

	/**
	 * Modify footer comment.
	 *
	 * @param array $strings Strings.
	 *
	 * @return array
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
	 * Usage statistics of request.
	 *
	 * @param object $storage Storage.
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
	 * Get reject reason.
	 *
	 * @return string
	 */
	public function get_reject_reason() {
		if ( is_null( $this->cache_reject_reason ) ) {
			return '';
		}

		return $this->_get_reject_reason_message( $this->cache_reject_reason );
	}

	/**
	 * Get reject reason message.
	 *
	 * @param unknown $key Key.
	 *
	 * @return string
	 */
	private function _get_reject_reason_message( $key ) {
		if ( ! function_exists( '__' ) ) {
			return $key;
		}

		switch ( $key ) {
			case 'objectcache.disabled':
				return __( 'Object caching is disabled', 'w3-total-cache' );
			case 'DONOTCACHEOBJECT':
				return __( 'DONOTCACHEOBJECT constant is defined', 'w3-total-cache' );
			default:
				return '';
		}
	}

	/**
	 * Log call.
	 *
	 * @param  array $data Log data.
	 * @return void
	 */
	private function log_call( array $data ): void {
		$filepath = Util_Debug::log_filename( 'objectcache-calls' );
		$content  = implode( "\t", $data ) . PHP_EOL;

		file_put_contents( $filepath, $content, FILE_APPEND );
	}

	/**
	 * Check if this is a WP-CLI call and objectcache.engine is using Disk and disabled for WP-CLI.
	 *
	 * @since  2.8.1
	 * @access private
	 *
	 * @return bool
	 */
	private function is_wpcli_disk(): bool {
		$is_engine_disk = 'file' === $this->_config->get_string( 'objectcache.engine' );
		$is_wpcli_disk  = $this->_config->get_boolean( 'objectcache.wpcli_disk' );
		return defined( 'WP_CLI' ) && \WP_CLI && $is_engine_disk && ! $is_wpcli_disk;
	}
}
