<?php
/**
 * File: Extension_FragmentCache_WpObjectCache.php
 *
 * @package W3TC
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore, PSR2.Methods.MethodDeclaration.Underscore
 */

namespace W3TC;

/**
 * W3 Fragment Cache object
 */
class Extension_FragmentCache_WpObjectCache {
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
	private $global_groups = array( 'site-transient' );

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
	 * Cache misses count
	 *
	 * @var integer
	 */
	private $cache_misses = 0;

	/**
	 * Total time
	 *
	 * @var integer
	 */
	private $time_total = 0;

	/**
	 * Store debug information of w3tc using
	 *
	 * @var array
	 */
	private $debug_info = array();

	/**
	 * Blog id of cache
	 *
	 * @var integer
	 */
	private $_blog_id;

	/**
	 * Key cache
	 *
	 * @var array
	 */
	private $_key_cache = array();

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
	private $_lifetime = 0;

	/**
	 * Debug flag
	 *
	 * @var boolean
	 */
	private $_debug = false;

	/**
	 * Core
	 *
	 * @var object
	 */
	private $_core;

	/**
	 * PHP5 style constructor
	 */
	public function __construct() {
		$this->_config   = Dispatcher::config();
		$this->_lifetime = $this->_config->get_integer( array( 'fragmentcache', 'lifetime' ) );
		$this->_debug    = $this->_config->get_boolean( array( 'fragmentcache', 'debug' ) );
		$this->_caching  = $this->_can_cache();

		$this->_blog_id = Util_Environment::blog_id();
		$this->_core    = Dispatcher::component( 'Extension_FragmentCache_Core' );
	}

	/**
	 * Get from the cache
	 *
	 * @param string    $id    Id.
	 * @param string    $w3tc_group Group.
	 * @param bool      $force Force.
	 * @param bool|null $found Found.
	 *
	 * @return mixed
	 */
	public function get( $id, $w3tc_group = 'transient', $force = false, &$found = null ) {
		// Abort if this is a WP-CLI call and fragmentcache engine is set to Disk.
		if ( $this->is_wpcli_disk() ) {
			return false;
		}

		if ( $this->_debug ) {
			$time_start = Util_Debug::microtime();
		}

		$w3tc_key = $this->_get_cache_key( $id );
		list( $fragment_group, $fragment_group_expiration, $fragment_group_global ) = $this->_fragment_group( $id, $w3tc_group );
		$internal = isset( $this->cache[ $fragment_group ][ $w3tc_key ] );

		if ( $internal ) {
			$found      = true;
			$w3tc_value = $this->cache[ $fragment_group ][ $w3tc_key ];
		} elseif (
			$this->_caching
			&& ! in_array( $w3tc_group, $this->nonpersistent_groups, true )
		) {
			$cache = $this->_get_cache( $fragment_group_global );
			$v     = $cache->get( $w3tc_key, $fragment_group );

			if ( is_array( $v ) && null !== $v['content'] ) {
				$found      = true;
				$w3tc_value = $v['content'];
			} else {
				$w3tc_value = false;
			}
		} else {
			$w3tc_value = false;
		}

		if ( null === $w3tc_value ) {
			$w3tc_value = false;
		}

		if ( is_object( $w3tc_value ) ) {
			$w3tc_value = clone $w3tc_value;
		}

		$this->cache[ $fragment_group ][ $w3tc_key ] = $w3tc_value;
		++$this->cache_total;

		if ( false !== $w3tc_value ) {
			$cached = true;
			++$this->cache_hits;
		} else {
			$cached = false;
			++$this->cache_misses;
		}

		/**
		 * Add debug info
		 */
		if ( $this->_debug ) {
			$time              = Util_Debug::microtime() - $time_start;
			$this->time_total += $time;

			if ( ! $w3tc_group ) {
				$w3tc_group = 'transient';
			}

			$this->debug_info[] = array(
				'id'        => $id,
				'group'     => $w3tc_group,
				'cached'    => $cached,
				'internal'  => $internal,
				'data_size' => ( $w3tc_value ? strlen( serialize( $w3tc_value ) ) : '' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
				'time'      => $time,
			);
		}

		return $w3tc_value;
	}

	/**
	 * Set to the cache
	 *
	 * @param string  $id     ID.
	 * @param mixed   $w3tc_data   Data.
	 * @param string  $w3tc_group  Group.
	 * @param integer $expire Expire.
	 *
	 * @return bool
	 */
	public function set( $id, $w3tc_data, $w3tc_group = 'transient', $expire = 0 ) {
		// Abort if this is a WP-CLI call and fragmentcache engine is set to Disk.
		if ( $this->is_wpcli_disk() ) {
			return false;
		}

		$w3tc_key = $this->_get_cache_key( $id );

		if ( is_object( $w3tc_data ) ) {
			$w3tc_data = clone $w3tc_data;
		}

		list( $fragment_group, $fragment_group_expiration, $fragment_group_global ) = $this->_fragment_group( $id, $w3tc_group );
		if ( is_int( $fragment_group_expiration ) ) {
			$expire = $fragment_group_expiration;
		}

		$this->cache[ $fragment_group ][ $w3tc_key ] = $w3tc_data;

		if (
			$this->_caching
				&& ! in_array( $w3tc_group, $this->nonpersistent_groups, true )
		) {
			$cache = $this->_get_cache( $fragment_group_global );
			$v     = array( 'content' => $w3tc_data );

			return $cache->set( $w3tc_key, $v, ( $expire ? $expire : $this->_lifetime ), $fragment_group );
		}

		return true;
	}

	/**
	 * Delete from the cache
	 *
	 * @param string $id    ID.
	 * @param string $w3tc_group Group.
	 * @param bool   $force Force.
	 *
	 * @return boolean
	 */
	public function delete( $id, $w3tc_group = 'transient', $force = false ) {
		if ( ! $force && $this->get( $id, $w3tc_group ) === false ) {
			return false;
		}

		list( $fragment_group, $fragment_group_expiration, $fragment_group_global ) = $this->_fragment_group( $id, $w3tc_group );

		$w3tc_key = $this->_get_cache_key( $id );

		unset( $this->cache[ $fragment_group ][ $w3tc_key ] );

		if ( $this->_caching && ! in_array( $w3tc_group, $this->nonpersistent_groups, true ) ) {
			$cache = $this->_get_cache( $fragment_group_global );
			return $cache->delete( $w3tc_key, $fragment_group );
		}

		return true;
	}

	/**
	 * Add to the cache
	 *
	 * @param string  $id     ID.
	 * @param mixed   $w3tc_data   Data.
	 * @param string  $w3tc_group  Group.
	 * @param integer $expire Expire.
	 *
	 * @return boolean
	 */
	public function add( $id, $w3tc_data, $w3tc_group = 'default', $expire = 0 ) {
		if ( $this->get( $id, $w3tc_group ) !== false ) {
			return false;
		}

		return $this->set( $id, $w3tc_data, $w3tc_group, $expire );
	}

	/**
	 * Replace in the cache
	 *
	 * @param string  $id     ID.
	 * @param mixed   $w3tc_data   Data.
	 * @param string  $w3tc_group  Group.
	 * @param integer $expire Expire.
	 *
	 * @return boolean
	 */
	public function replace( $id, $w3tc_data, $w3tc_group = 'default', $expire = 0 ) {
		if ( $this->get( $id, $w3tc_group ) === false ) {
			return false;
		}

		return $this->set( $id, $w3tc_data, $w3tc_group, $expire );
	}

	/**
	 * Reset keys
	 *
	 * @return boolean
	 */
	public function reset() {
		$this->cache = array();

		return true;
	}

	/**
	 * Flush cache
	 *
	 * @return boolean
	 */
	public function flush() {
		$this->cache = array();

		$cache = $this->_get_cache( false );
		$cache->flush( 'nogroup' );

		$cache = $this->_get_cache( true );
		$cache->flush( 'global-nogroup' );

		$groups = $this->_core->get_registered_fragment_groups();
		foreach ( $groups as $w3tc_group => $w3tc_descriptor ) {
			$cache = $this->_get_cache( $w3tc_descriptor['global'] );
			$cache->flush( $w3tc_group );
		}

		return true;
	}

	/**
	 * Flushes runtime.
	 *
	 * @return bool
	 */
	public function flush_runtime() {
		$this->cache = array();

		return true;
	}

	/**
	 * Purges all transients that belong to a transient group
	 *
	 * @param string $fragment_group fragment grouping.
	 *
	 * @return bool
	 */
	public function flush_group( $fragment_group ) {
		unset( $this->cache[ $fragment_group ] );

		if ( $this->_caching ) {
			list( $f1, $f2, $fragment_group_global ) =
				$this->_fragment_group( $fragment_group, '' );

			$cache = $this->_get_cache( $fragment_group_global );
			return $cache->flush( $fragment_group );
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
	 * @param int|string $w3tc_key    The cache key to increment.
	 * @param int        $w3tc_offset The amount by which to increment the item's value. Default is 1.
	 * @param string     $w3tc_group  The group the key is in.
	 *
	 * @return bool|int False on failure, the item's new value on success.
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
	 * Decrement numeric cache item's value
	 *
	 * @param int|string $w3tc_key    The cache key to increment.
	 * @param int        $w3tc_offset The amount by which to decrement the item's value. Default is 1.
	 * @param string     $w3tc_group  The group the key is in.
	 *
	 * @return bool|int False on failure, the item's new value on success.
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
	 * Returns cache key
	 *
	 * @param string $id ID.
	 *
	 * @return string
	 */
	private function _get_cache_key( $id ) {
		return md5( $id );
	}

	/**
	 * Returns cache object
	 *
	 * @param bool $global_flag Global.
	 *
	 * @return W3_Cache_Base
	 */
	private function _get_cache( $global_flag = false ) {
		static $cache = array();

		if ( ! $global_flag ) {
			$blog_id = $this->_blog_id;
		} else {
			$blog_id = 0;
		}

		if ( ! isset( $cache[ $blog_id ] ) ) {
			$w3tc_engine = $this->_config->get_string( array( 'fragmentcache', 'engine' ) );

			switch ( $w3tc_engine ) {
				case 'memcached':
					$engine_config = array(
						'servers'           => $this->_config->get_array( array( 'fragmentcache', 'memcached.servers' ) ),
						'persistent'        => $this->_config->get_boolean( array( 'fragmentcache', 'memcached.persistent' ) ),
						'aws_autodiscovery' => $this->_config->get_boolean( array( 'fragmentcache', 'memcached.aws_autodiscovery' ) ),
						'username'          => $this->_config->get_string( array( 'fragmentcache', 'memcached.username' ) ),
						'password'          => $this->_config->get_string( array( 'fragmentcache', 'memcached.password' ) ),
					);
					break;

				case 'redis':
					$engine_config = array(
						'servers'                 => $this->_config->get_array( array( 'fragmentcache', 'redis.servers' ) ),
						'verify_tls_certificates' => $this->_config->get_boolean( array( 'fragmentcache', 'redis.verify_tls_certificates' ) ),
						'persistent'              => $this->_config->get_boolean( array( 'fragmentcache', 'redis.persistent' ) ),
						'timeout'                 => $this->_config->get_integer( array( 'fragmentcache', 'redis.timeout' ) ),
						'retry_interval'          => $this->_config->get_integer( array( 'fragmentcache', 'redis.retry_interval' ) ),
						'read_timeout'            => $this->_config->get_integer( array( 'fragmentcache', 'redis.read_timeout' ) ),
						'dbid'                    => $this->_config->get_integer( array( 'fragmentcache', 'redis.dbid' ) ),
						'password'                => $this->_config->get_string( array( 'fragmentcache', 'redis.password' ) ),
					);
					break;

				case 'file':
					$engine_config = array(
						'section'         => 'fragment',
						'locking'         => $this->_config->get_boolean( array( 'fragmentcache', 'file.locking' ) ),
						'flush_timelimit' => $this->_config->get_integer( 'timelimit.cache_flush' ),
					);
					break;

				default:
					$engine_config = array();
			}
			$engine_config['blog_id']     = $blog_id;
			$engine_config['module']      = 'fragmentcache';
			$engine_config['host']        = Util_Environment::host();
			$engine_config['instance_id'] = Util_Environment::instance_id();

			$cache[ $blog_id ] = Cache::instance( $w3tc_engine, $engine_config );
		}

		return $cache[ $blog_id ];
	}

	/**
	 * Check if caching allowed on init
	 *
	 * @return boolean
	 */
	private function _can_cache() {
		return true;
	}

	/**
	 * Returns debug info
	 *
	 * @param  array $strings Strings.
	 * @return array
	 */
	public function w3tc_footer_comment( $strings ) {
		$append = '' !== $this->cache_reject_reason ? sprintf( ' (%s)', $this->cache_reject_reason ) : '';

		$strings[] = sprintf(
			// translators: 1 cache hits, 2 cache total, 3 engine name, 4 reject reason.
			__( 'Fragment Caching %1$d/%2$d fragments using %3$s%4$s', 'w3-total-cache' ),
			$this->cache_hits,
			$this->cache_total,
			Cache::engine_name( $this->_config->get_string( array( 'fragmentcache', 'engine' ) ) ),
			$append
		);

		if ( $this->_config->get_boolean( array( 'fragmentcache', 'debug' ) ) ) {
			$strings[] = '';
			$strings[] = __( 'Fragment Cache debug info:', 'w3-total-cache' );
			$strings[] = sprintf( '%s%s', str_pad( 'Caching: ', 20 ), ( $this->_caching ? 'enabled' : 'disabled' ) );
			$strings[] = sprintf( '%s%d', str_pad( 'Total calls: ', 20 ), $this->cache_total );
			$strings[] = sprintf( '%s%d', str_pad( 'Cache hits: ', 20 ), $this->cache_hits );
			$strings[] = sprintf( '%s%d', str_pad( 'Cache misses: ', 20 ), $this->cache_misses );
			$strings[] = sprintf( '%s%.4f', str_pad( 'Total time: ', 20 ), $this->time_total );
			$strings[] = __( 'W3TC Fragment Cache info:', 'w3-total-cache' );
			$strings[] = sprintf(
				'%s | %s | %s | %s | %s | %s| %s| %s',
				str_pad( '#', 5, ' ', STR_PAD_LEFT ),
				str_pad( 'Status', 15, ' ', STR_PAD_BOTH ),
				str_pad( 'Source', 15, ' ', STR_PAD_BOTH ),
				str_pad( 'Data size (b)', 13, ' ', STR_PAD_LEFT ),
				str_pad( 'Query time (s)', 14, ' ', STR_PAD_LEFT ),
				str_pad( 'Group', 14, ' ', STR_PAD_LEFT ),
				str_pad( 'Accessible', 10, ' ', STR_PAD_LEFT ),
				'Transient ID'
			);

			foreach ( $this->debug_info as $w3tc_index => $debug ) {
				list( $fragment_group, $fragment_group_expiration, $fragment_group_global ) = $this->_fragment_group( $debug['id'], $debug['group'] );
				$strings[] = sprintf(
					'%s | %s | %s | %s | %s | %s| %s| %s',
					str_pad( $w3tc_index + 1, 5, ' ', STR_PAD_LEFT ),
					str_pad( ( $debug['cached'] ? 'cached' : 'not cached' ), 15, ' ', STR_PAD_BOTH ),
					str_pad( ( $debug['internal'] ? 'internal' : 'persistent' ), 15, ' ', STR_PAD_BOTH ),
					str_pad( $debug['data_size'], 13, ' ', STR_PAD_LEFT ),
					str_pad( round( $debug['time'], 4 ), 14, ' ', STR_PAD_LEFT ),
					str_pad( $fragment_group, 14, ' ', STR_PAD_LEFT ),
					str_pad( ( 'transient' === $debug['group'] ? 'site' : 'network' ), 10, ' ', STR_PAD_LEFT ),
					$debug['id']
				);
			}
			$strings[] = '';
		}

		return $strings;
	}

	/**
	 * Returns the group part of a transient/site-transient id.
	 * Uses registered fragment groups to identify it.
	 *
	 * @param unknown $id    ID.
	 * @param string  $w3tc_group Group.
	 *
	 * @return string
	 */
	private function _fragment_group( $id, $w3tc_group ) {
		if ( empty( $id ) ) {
			return array( 'nogroup', 0, false );
		}

		$groups    = $this->_core->get_registered_fragment_groups();
		$use_group = '';
		$length    = 0;

		foreach ( $groups as $w3tc_group => $w3tc_descriptor ) {
			if ( strpos( $id, $w3tc_group ) !== false ) {
				if ( strlen( $w3tc_group ) > $length ) {
					$length    = strlen( $w3tc_group );
					$use_group = array( $w3tc_group, $w3tc_descriptor['expiration'], $w3tc_descriptor['global'] );
				}
			}
		}
		if ( $use_group ) {
			return $use_group;
		}

		if ( 'site-transient' === $w3tc_group ) {
			return array( 'global-nogroup', 0, true );
		}

		return array( 'nogroup', 0, false );
	}

	/**
	 * Usage statistics of request.
	 *
	 * @param object $storage Storage.
	 *
	 * @return void
	 */
	public function w3tc_usage_statistics_of_request( $storage ) {
		$storage->counter_add( 'fragmentcache_calls_total', $this->cache_total );
		$storage->counter_add( 'fragmentcache_calls_hits', $this->cache_hits );
	}

	/**
	 * Check if this is a WP-CLI call and fragmentcache[engine] is using Disk.
	 *
	 * @since  2.8.2
	 * @access private
	 *
	 * @return bool
	 */
	private function is_wpcli_disk(): bool {
		$w3tc_engine = $this->_config->get_string( array( 'fragmentcache', 'engine' ) );
		return defined( 'WP_CLI' ) && WP_CLI && 'file' === $w3tc_engine;
	}
}
