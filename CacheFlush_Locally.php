<?php
/**
 * File: CacheFlush_Locally.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class CacheFlush_Locally
 *
 * W3 Cache flushing
 *
 * Priorities are very important for actions here.
 * if e.g. CDN is flushed before local page cache - CDN can cache again
 * still not flushed pages from local page cache.
 *  100 - db
 *  200 - 999 local objects, like object cache
 *  1000 - 1999 local files (minify, pagecache)
 *  2000 - 2999 local reverse proxies varnish, nginx
 *  3000 -  external caches like cdn, cloudflare
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class CacheFlush_Locally {
	/**
	 * Flushes the database cache.
	 *
	 * Triggers the `w3tc_flush_dbcache` action and attempts to clear the database cache
	 * if supported by the `$wpdb` global.
	 *
	 * @param array $extras {
	 *     Optional. Additional parameters for flushing. Default empty array.
	 *
	 *     @type string $only Flush specific cache type.
	 * }
	 *
	 * @return bool|void True if cache flushed successfully, false otherwise, void if not applicable.
	 */
	public function dbcache_flush( $extras = array() ) {
		if ( isset( $extras['only'] ) && 'dbcache' !== $extras['only'] ) {
			return;
		}

		do_action( 'w3tc_flush_dbcache' );

		if ( ! method_exists( $GLOBALS['wpdb'], 'flush_cache' ) ) {
			return false;
		}

		return $GLOBALS['wpdb']->flush_cache( $extras );
	}

	/**
	 * Flushes the object cache.
	 *
	 * Triggers the `w3tc_flush_objectcache` and `w3tc_flush_after_objectcache` actions
	 * and clears the object cache using the appropriate component.
	 *
	 * @param array $extras {
	 *     Optional. Additional parameters for flushing. Default empty array.
	 *
	 *     @type string $only Flush specific cache type.
	 * }
	 *
	 * @return bool True if cache flushed successfully, false otherwise.
	 */
	public function objectcache_flush( $extras = array() ) {
		if ( isset( $extras['only'] ) && 'objectcache' !== $extras['only'] ) {
			return;
		}

		do_action( 'w3tc_flush_objectcache' );
		$objectcache = Dispatcher::component( 'ObjectCache_WpObjectCache_Regular' );
		$v           = $objectcache->flush();

		do_action( 'w3tc_flush_after_objectcache' );

		return $v;
	}

	/**
	 * Flushes the fragment cache.
	 *
	 * Triggers the `w3tc_flush_fragmentcache` and `w3tc_flush_after_fragmentcache` actions
	 * and clears the fragment cache.
	 *
	 * @param array $extras {
	 *     Optional. Additional parameters for flushing. Default empty array.
	 *
	 *     @type string $only Flush specific cache type.
	 * }
	 *
	 * @return bool Always true.
	 */
	public function fragmentcache_flush( $extras = array() ) {
		if ( isset( $extras['only'] ) && 'fragment' !== $extras['only'] ) {
			return;
		}

		do_action( 'w3tc_flush_fragmentcache' );
		do_action( 'w3tc_flush_after_fragmentcache' );

		return true;
	}

	/**
	 * Flushes a specific fragment cache group.
	 *
	 * Triggers the `w3tc_flush_fragmentcache_group` and `w3tc_flush_after_fragmentcache_group` actions.
	 *
	 * @param string $group The fragment cache group to flush.
	 *
	 * @return bool Always true.
	 */
	public function fragmentcache_flush_group( $group ) {
		do_action( 'w3tc_flush_fragmentcache_group', $group );
		do_action( 'w3tc_flush_after_fragmentcache_group', $group );

		return true;
	}

	/**
	 * Flushes the minify cache.
	 *
	 * Triggers the `w3tc_flush_minify` and `w3tc_flush_after_minify` actions and clears
	 * the minify cache using the appropriate component.
	 *
	 * @param array $extras {
	 *     Optional. Additional parameters for flushing. Default empty array.
	 *
	 *     @type string $only Flush specific cache type.
	 * }
	 *
	 * @return bool True if cache flushed successfully, false otherwise.
	 */
	public function minifycache_flush( $extras = array() ) {
		if ( isset( $extras['only'] ) && 'minify' !== $extras['only'] ) {
			return;
		}

		do_action( 'w3tc_flush_minify' );
		$minifycache = Dispatcher::component( 'Minify_MinifiedFileRequestHandler' );
		$v           = $minifycache->flush( $extras );
		do_action( 'w3tc_flush_after_minify' );

		return $v;
	}

	/**
	 * Flushes all minify caches.
	 *
	 * Delegates the flush to the `minifycache_flush` method.
	 *
	 * @param array $extras Optional. Additional parameters for flushing. Default empty array.
	 *
	 * @return void
	 */
	public function minifycache_flush_all( $extras = array() ) {
		$this->minifycache_flush( $extras );
	}

	/**
	 * Flushes the browser cache.
	 *
	 * Triggers the `w3tc_flush_browsercache` and `w3tc_flush_after_browsercache` actions
	 * and updates the browser cache flush timestamp.
	 *
	 * @param array $extras {
	 *     Optional. Additional parameters for flushing. Default empty array.
	 *
	 *     @type string $only Flush specific cache type. Defaults to 'browsercache'.
	 * }
	 *
	 * @return void
	 */
	public function browsercache_flush( $extras = array() ) {
		if ( isset( $extras['only'] ) && 'browsercache' !== $extras['only'] ) {
			return;
		}

		do_action( 'w3tc_flush_browsercache' );
		update_option( 'w3tc_browsercache_flush_timestamp', wp_rand( 10000, 99999 ) . '' );
		do_action( 'w3tc_flush_after_browsercache' );
	}

	/**
	 * Purges all content from the CDN.
	 *
	 * Applies the `w3tc_preflush_cdn_all` filter to determine whether the purge should occur.
	 * If true, it triggers the `w3tc_cdn_purge_all` and `w3tc_cdn_purge_all_after` actions
	 * and clears the CDN cache.
	 *
	 * @param array $extras Optional. Additional parameters for flushing. Default empty array.
	 *
	 * @return bool True if purge succeeded, false otherwise.
	 */
	public function cdn_purge_all( $extras = array() ) {
		$do_flush = apply_filters( 'w3tc_preflush_cdn_all', true, $extras );

		$v = false;
		if ( $do_flush ) {
			do_action( 'w3tc_cdn_purge_all' );
			$cdn_core = Dispatcher::component( 'Cdn_Core' );
			$cdn      = $cdn_core->get_cdn();
			$results  = array();
			$v        = $cdn->purge_all( $results );
			do_action( 'w3tc_cdn_purge_all_after' );
		}

		return $v;
	}

	/**
	 * Purges specific files from the CDN.
	 *
	 * Triggers the `w3tc_cdn_purge_files` and `w3tc_cdn_purge_files_after` actions and clears
	 * the specified files from the CDN cache.
	 *
	 * @param array $purgefiles List of files to purge from the CDN.
	 *
	 * @return bool True if purge succeeded, false otherwise.
	 */
	public function cdn_purge_files( $purgefiles ) {
		do_action( 'w3tc_cdn_purge_files', $purgefiles );
		$common  = Dispatcher::component( 'Cdn_Core' );
		$results = array();
		$v       = $common->purge( $purgefiles, $results );
		do_action( 'w3tc_cdn_purge_files_after', $purgefiles );

		return $v;
	}

	/**
	 * Flushes the OPcache.
	 *
	 * This method triggers the flushing of the OPcache using the `SystemOpCache_Core` component.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function opcache_flush() {
		$o = Dispatcher::component( 'SystemOpCache_Core' );
		return $o->flush();
	}

	/**
	 * Flushes a specific post from the cache.
	 *
	 * Executes the `w3tc_flush_post` action if the `w3tc_preflush_post` filter allows it.
	 *
	 * @param int   $post_id The ID of the post to flush.
	 * @param bool  $force   Optional. Whether to force the flush. Default false.
	 * @param mixed $extras Optional. Additional data passed to the filter and action.
	 *
	 * @return void
	 */
	public function flush_post( $post_id, $force = false, $extras = null ) {
		$do_flush = apply_filters( 'w3tc_preflush_post', true, $extras );
		if ( $do_flush ) {
			do_action( 'w3tc_flush_post', $post_id, $force, $extras );
		}
	}

	/**
	 * Flushes all posts from the cache.
	 *
	 * Executes the `w3tc_flush_posts` action if the `w3tc_preflush_posts` filter allows it.
	 *
	 * @param mixed $extras Optional. Additional data passed to the filter and action.
	 *
	 * @return void
	 */
	public function flush_posts( $extras = null ) {
		$do_flush = apply_filters( 'w3tc_preflush_posts', true, $extras );
		if ( $do_flush ) {
			do_action( 'w3tc_flush_posts', $extras );
		}
	}

	/**
	 * Flushes all cached content across multiple modules.
	 *
	 * Registers default actions for various cache components (OPcache, object cache, database cache, minify cache)
	 * and executes the `w3tc_flush_all` action if the `w3tc_preflush_all` filter allows it.
	 *
	 * @param mixed $extras Additional data passed to the filter and action.
	 *
	 * @return void
	 */
	public function flush_all( $extras ) {
		static $default_actions_added = false;
		if ( ! $default_actions_added ) {
			$config = Dispatcher::config();

			$opcache = Dispatcher::component( 'SystemOpCache_Core' );
			if ( $opcache->is_enabled() ) {
				add_action( 'w3tc_flush_all', array( $this, 'opcache_flush' ), 50, 1 );
			}

			if ( $config->get_boolean( 'dbcache.enabled' ) ) {
				add_action( 'w3tc_flush_all', array( $this, 'dbcache_flush' ), 100, 2 );
			}

			if ( $config->getf_boolean( 'objectcache.enabled' ) ) {
				add_action( 'w3tc_flush_all', array( $this, 'objectcache_flush' ), 200, 1 );
			}

			if ( $config->get_boolean( 'minify.enabled' ) ) {
				add_action( 'w3tc_flush_all', array( $this, 'minifycache_flush_all' ), 1000, 1 );
			}

			$default_actions_added = true;
		}

		$do_flush = apply_filters( 'w3tc_preflush_all', true, $extras );
		if ( $do_flush ) {
			do_action( 'w3tc_flush_all', $extras );
		}
	}

	/**
	 * Flushes a specific cache group.
	 *
	 * Executes the `w3tc_flush_group` action if the `w3tc_preflush_group` filter allows it.
	 *
	 * @param string $group  The name of the group to flush.
	 * @param mixed  $extras Additional data passed to the filter and action.
	 *
	 * @return void
	 */
	public function flush_group( $group, $extras ) {
		$do_flush = apply_filters( 'w3tc_preflush_group', true, $group, $extras );
		if ( $do_flush ) {
			do_action( 'w3tc_flush_group', $group, $extras );
		}
	}

	/**
	 * Flushes the cache for a specific URL.
	 *
	 * Executes the `w3tc_flush_url` action if the `w3tc_preflush_url` filter allows it.
	 *
	 * @param string $url    The URL to flush.
	 * @param mixed  $extras Optional. Additional data passed to the filter and action.
	 *
	 * @return void
	 */
	public function flush_url( $url, $extras = null ) {
		$do_flush = apply_filters( 'w3tc_preflush_url', true, $extras );
		if ( $do_flush ) {
			do_action( 'w3tc_flush_url', $url, $extras );
		}
	}

	/**
	 * Primes the cache for a specific post.
	 *
	 * Utilizes the `PgCache_Plugin_Admin` component to prime the post.
	 *
	 * @param int $post_id The ID of the post to prime.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function prime_post( $post_id ) {
		$pgcache = Dispatcher::component( 'PgCache_Plugin_Admin' );
		return $pgcache->prime_post( $post_id );
	}

	/**
	 * Executes delayed cache operations for specific modules.
	 *
	 * Registers default delayed operations for page cache and Varnish, and triggers the operations
	 * using the `w3tc_flush_execute_delayed_operations` filter.
	 *
	 * @return array List of actions performed, with module names and error messages (empty if no error).
	 */
	public function execute_delayed_operations() {
		static $default_actions_added = false;
		if ( ! $default_actions_added ) {
			$config = Dispatcher::config();

			if ( $config->get_boolean( 'pgcache.enabled' ) ) {
				add_filter( 'w3tc_flush_execute_delayed_operations', array( $this, '_execute_delayed_operations_pgcache' ), 1100 );
			}

			if ( $config->get_boolean( 'varnish.enabled' ) ) {
				add_filter( 'w3tc_flush_execute_delayed_operations', array( $this, '_execute_delayed_operations_varnish' ), 2000 );
			}

			$default_actions_added = true;
		}

		// build response in a form 'module' => 'error message' (empty if no error).
		$actions_made = array();
		$actions_made = apply_filters( 'w3tc_flush_execute_delayed_operations', $actions_made );

		return $actions_made;
	}

	/**
	 * Executes delayed operations for page cache.
	 *
	 * Flushes stale page cache entries and logs the action if successful.
	 *
	 * @param array $actions_made List of actions already performed.
	 *
	 * @return array Updated list of actions performed.
	 */
	public function _execute_delayed_operations_pgcache( $actions_made ) {
		$o             = Dispatcher::component( 'PgCache_Flush' );
		$count_flushed = $o->flush_post_cleanup();
		if ( $count_flushed > 0 ) {
			$actions_made[] = array( 'module' => 'pgcache' );
		}

		return $actions_made;
	}

	/**
	 * Executes delayed operations for Varnish.
	 *
	 * Flushes stale Varnish cache entries and logs the action if successful.
	 *
	 * @param array $actions_made List of actions already performed.
	 *
	 * @return array Updated list of actions performed.
	 */
	public function _execute_delayed_operations_varnish( $actions_made ) {
		$o             = Dispatcher::component( 'Varnish_Flush' );
		$count_flushed = $o->flush_post_cleanup();
		if ( $count_flushed > 0 ) {
			$actions_made[] = array( 'module' => 'varnish' );
		}

		return $actions_made;
	}
}
