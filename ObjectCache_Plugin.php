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
	 * Constructor.
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Runs plugin
	 */
	public function run() {
		// @link https://developer.wordpress.org/reference/hooks/updated_option/
		add_action( 'updated_option', array( $this, 'delete_option_cache' ), 10, 0 );

		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );

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
	 * Delete the options cache object.
	 *
	 * @since X.X.X
	 *
	 * @link https://developer.wordpress.org/reference/functions/wp_cache_delete/
	 *
	 * @return bool
	 */
	public function delete_option_cache() {
		return wp_cache_delete( 'alloptions', 'options' );
	}

	/**
	 * Does disk cache cleanup
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
	 * Cron schedules filter
	 *
	 * @param array $schedules Schedules.
	 *
	 * @return array
	 */
	public function cron_schedules( $schedules ) {
		$gc = $this->_config->get_integer( 'objectcache.file.gc' );

		return array_merge(
			$schedules,
			array(
				'w3_objectcache_cleanup' => array(
					'interval' => $gc,
					'display'  => sprintf(
						// translators: 1 interval in seconds.
						__( '[W3TC] Object Cache file GC (every %d seconds)', 'w3-total-cache' ),
						$gc
					),
				),
			)
		);
	}

	/**
	 * Setup admin menu elements
	 *
	 * @param array $menu_items Menu items.
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
	 * Setup admin menu elements
	 *
	 * @param array $strings Strings.
	 */
	public function w3tc_footer_comment( $strings ) {
		$o       = Dispatcher::component( 'ObjectCache_WpObjectCache_Regular' );
		$strings = $o->w3tc_footer_comment( $strings );

		return $strings;
	}

	/**
	 * Usage statistics of request filter
	 *
	 * @param object $storage Storage object.
	 */
	public function w3tc_usage_statistics_of_request( $storage ) {
		$o = Dispatcher::component( 'ObjectCache_WpObjectCache_Regular' );
		$o->w3tc_usage_statistics_of_request( $storage );
	}

	/**
	 * Retrive usage statistics metrics
	 *
	 * @param array $metrics Metrics.
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
	 * Usage Statisitcs sources filter.
	 *
	 * @param array $sources Sources.
	 *
	 * @return array
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
