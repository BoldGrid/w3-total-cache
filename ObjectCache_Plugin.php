<?php
/**
 * File: ObjectCache_Plugin.php
 *
 * @package W3TC
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */

namespace W3TC;

/**
 * W3 ObjectCache plugin
 */
class ObjectCache_Plugin {
	/**
	 * Config.
	 *
	 * @var Config
	 */
	private $_config = null;

	/**
	 * If the object cache has been flushed.
	 *
	 * @since 2.2.10
	 *
	 * @var boolean
	 */
	private static $flushed = false;

	/**
	 * Constructs the ObjectCache_Plugin class and initializes configuration.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Registers necessary actions and filters for object cache functionality.
	 *
	 * phpcs:disable WordPress.WP.CronInterval.ChangeDetected
	 *
	 * @link https://developer.wordpress.org/reference/hooks/updated_option/
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'updated_option', array( $this, 'delete_option_cache' ), 10, 0 );

		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( 'w3tc_objectcache_purge_wpcron', array( $this, 'w3tc_objectcache_purge_wpcron' ) );

		add_filter( 'w3tc_footer_comment', array( $this, 'w3tc_footer_comment' ) );

		if ( 'file' === $this->_config->get_string( 'objectcache.engine' ) ) {
			add_action( 'w3_objectcache_cleanup', array( $this, 'cleanup' ) );
		}

		add_filter( 'w3tc_admin_bar_menu', array( $this, 'w3tc_admin_bar_menu' ) );

		// usage statistics handling.
		add_action( 'w3tc_usage_statistics_of_request', array( $this, 'w3tc_usage_statistics_of_request' ), 10, 1 );
		add_filter( 'w3tc_usage_statistics_metrics', array( $this, 'w3tc_usage_statistics_metrics' ) );
		add_filter( 'w3tc_usage_statistics_sources', array( $this, 'w3tc_usage_statistics_sources' ) );
	}

	/**
	 * Deletes the cache for all options.
	 *
	 * @since 2.7.6
	 *
	 * @return bool True on successful removal, false on failure.
	 */
	public function delete_option_cache() {
		return wp_cache_delete( 'alloptions', 'options' );
	}

	/**
	 * Cleans up object cache files based on the configured time limit.
	 *
	 * @return void
	 */
	public function cleanup() {
		$w3_cache_file_cleaner = new Cache_File_Cleaner(
			array(
				'cache_dir'       => Util_Environment::cache_blog_dir( 'object' ),
				'clean_timelimit' => $this->_config->get_integer( 'timelimit.cache_gc' ),
			)
		);

		$w3_cache_file_cleaner->clean();
	}

	/**
	 * Adds custom cron schedules for object cache cleanup.
	 *
	 * @param array $schedules Existing cron schedules.
	 *
	 * @return array Modified cron schedules.
	 */
	public function cron_schedules( $schedules ) {
		$c                   = $this->_config;
		$objectcache_enabled = $c->get_boolean( 'objectcache.enabled' );
		$engine              = $c->get_string( 'objectcache.engine' );

		if ( $objectcache_enabled && 'file' === $engine ) {
			$interval                            = $c->get_integer( 'objectcache.file.gc' );
			$schedules['w3_objectcache_cleanup'] = array(
				'interval' => $interval,
				'display'  => sprintf(
					// translators: 1 interval in seconds.
					__( '[W3TC] Object Cache file GC (every %d seconds)', 'w3-total-cache' ),
					$interval
				),
			);
		}

		return $schedules;
	}

	/**
	 * Purges the object cache via WP-Cron.
	 *
	 * @since 2.8.0
	 *
	 * @return void
	 */
	public function w3tc_objectcache_purge_wpcron() {
		$flusher = Dispatcher::component( 'CacheFlush' );
		$flusher->objectcache_flush();
	}

	/**
	 * Adds an item to the admin bar menu for object cache flushing.
	 *
	 * @param array $menu_items Existing menu items.
	 *
	 * @return array Modified menu items.
	 */
	public function w3tc_admin_bar_menu( $menu_items ) {
		$menu_items['20410.objectcache'] = array(
			'id'     => 'w3tc_flush_objectcache',
			'parent' => 'w3tc_flush',
			'title'  => __( 'Object Cache', 'w3-total-cache' ),
			'href'   => wp_nonce_url( admin_url( 'admin.php?page=w3tc_dashboard&amp;w3tc_flush_objectcache' ), 'w3tc' ),
		);

		return $menu_items;
	}

	/**
	 * Adds a footer comment related to object cache to the strings.
	 *
	 * @param  array $strings Existing footer strings.
	 * @return array Modified footer strings.
	 */
	public function w3tc_footer_comment( $strings ) {
		$o       = Dispatcher::component( 'ObjectCache_WpObjectCache_Regular' );
		$strings = $o->w3tc_footer_comment( $strings );

		return $strings;
	}

	/**
	 * Collects usage statistics for the object cache.
	 *
	 * @param mixed $storage Data storage to collect statistics.
	 *
	 * @return void
	 */
	public function w3tc_usage_statistics_of_request( $storage ) {
		$o = Dispatcher::component( 'ObjectCache_WpObjectCache_Regular' );
		$o->w3tc_usage_statistics_of_request( $storage );
	}

	/**
	 * Adds object cache metrics to the usage statistics.
	 *
	 * @param array $metrics Existing metrics.
	 *
	 * @return array Modified metrics.
	 */
	public function w3tc_usage_statistics_metrics( $metrics ) {
		$metrics = array_merge(
			$metrics,
			array(
				'objectcache_get_total',
				'objectcache_get_hits',
				'objectcache_sets',
				'objectcache_flushes',
				'objectcache_time_ms',
			)
		);

		return $metrics;
	}

	/**
	 * Adds object cache sources to the usage statistics.
	 *
	 * @param array $sources Existing sources.
	 *
	 * @return array Modified sources.
	 */
	public function w3tc_usage_statistics_sources( $sources ) {
		$c = Dispatcher::config();
		if ( 'apc' === $c->get_string( 'objectcache.engine' ) ) {
			$sources['apc_servers']['objectcache'] = array(
				'name' => __( 'Object Cache', 'w3-total-cache' ),
			);
		} elseif ( 'memcached' === $c->get_string( 'objectcache.engine' ) ) {
			$sources['memcached_servers']['objectcache'] = array(
				'servers'         => $c->get_array( 'objectcache.memcached.servers' ),
				'username'        => $c->get_string( 'objectcache.memcached.username' ),
				'password'        => $c->get_string( 'objectcache.memcached.password' ),
				'binary_protocol' => $c->get_boolean( 'objectcache.memcached.binary_protocol' ),
				'name'            => __( 'Object Cache', 'w3-total-cache' ),
			);
		} elseif ( 'redis' === $c->get_string( 'objectcache.engine' ) ) {
			$sources['redis_servers']['objectcache'] = array(
				'servers'                 => $c->get_array( 'objectcache.redis.servers' ),
				'verify_tls_certificates' => $c->get_boolean( 'objectcache.redis.verify_tls_certificates' ),
				'username'                => $c->get_boolean( 'objectcache.redis.username' ),
				'dbid'                    => $c->get_integer( 'objectcache.redis.dbid' ),
				'password'                => $c->get_string( 'objectcache.redis.password' ),
				'name'                    => __( 'Object Cache', 'w3-total-cache' ),
			);
		}

		return $sources;
	}
}
