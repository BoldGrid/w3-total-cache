<?php
/**
 * File: DbCache_Plugin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class DbCache_Plugin
 *
 * W3 DbCache plugin
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class DbCache_Plugin {
	/**
	 * Config.
	 *
	 * @var array
	 */
	private $_config = null;

	/**
	 * Constructor for the DbCache_Plugin class.
	 *
	 * Initializes the configuration for the plugin.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Runs the initialization logic for the DbCache plugin.
	 *
	 * Hooks into various WordPress actions and filters to manage database cache operations.
	 *
	 * @return void
	 */
	public function run() {
		// phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( 'w3tc_dbcache_purge_wpcron', array( $this, 'w3tc_dbcache_purge_wpcron' ) );

		if ( 'file' === $this->_config->get_string( 'dbcache.engine' ) ) {
			add_action( 'w3_dbcache_cleanup', array( $this, 'cleanup' ) );
		}

		// Posts.
		add_action( 'publish_phone', array( $this, 'on_change' ), 0 );
		add_action( 'wp_trash_post', array( $this, 'on_post_change' ), 0 );
		add_action( 'save_post', array( $this, 'on_post_change' ), 0 );
		add_action( 'clean_post_cache', array( $this, 'on_post_change' ), 0, 2 );
		add_action( 'delete_post', array( $this, 'on_post_change' ), 0 );

		// Comments.
		add_action( 'comment_post', array( $this, 'on_comment_change' ), 0 );
		add_action( 'edit_comment', array( $this, 'on_comment_change' ), 0 );
		add_action( 'delete_comment', array( $this, 'on_comment_change' ), 0 );
		add_action( 'wp_set_comment_status', array( $this, 'on_comment_status' ), 0, 2 );
		add_action( 'trackback_post', array( $this, 'on_comment_change' ), 0 );
		add_action( 'pingback_post', array( $this, 'on_comment_change' ), 0 );

		// Theme.
		add_action( 'switch_theme', array( $this, 'on_change' ), 0 );

		// Profile.
		add_action( 'edit_user_profile_update', array( $this, 'on_change' ), 0 );

		// Multisite.
		if ( Util_Environment::is_wpmu() ) {
			$util_attachtoactions = new Util_AttachToActions();

			/**
			 * Fires once a site has been deleted from the database.
			 *
			 * @since 5.1.0
			 *
			 * @see w3tc_flush_posts()
			 *
			 * @link https://developer.wordpress.org/reference/hooks/wp_delete_site/
			 *
			 * @param WP_Site $old_site Deleted site object.
			 */
			add_action( 'wp_delete_site', 'w3tc_flush_posts', 0, 0 );

			/**
			 * Fires once a site has been updated in the database.
			 *
			 * @since 5.1.0
			 *
			 * @link https://developer.wordpress.org/reference/hooks/wp_update_site/
			 *
			 * @param WP_Site $new_site New site object.
			 * @param WP_Site $old_site Old site object.
			 */
			add_action( 'wp_update_site', array( $util_attachtoactions, 'on_update_site' ), 0, 2 );
		}

		add_filter( 'w3tc_admin_bar_menu', array( $this, 'w3tc_admin_bar_menu' ) );

		// usage statistics handling.
		add_filter( 'w3tc_usage_statistics_metrics', array( $this, 'w3tc_usage_statistics_metrics' ) );
		add_filter( 'w3tc_usage_statistics_sources', array( $this, 'w3tc_usage_statistics_sources' ) );
	}

	/**
	 * Cleans up expired database cache files.
	 *
	 * Instantiates and executes the Cache_File_Cleaner with the specified configurations.
	 *
	 * @return void
	 */
	public function cleanup() {
		$w3_cache_file_cleaner = new Cache_File_Cleaner(
			array(
				'cache_dir'       => Util_Environment::cache_blog_dir( 'db' ),
				'clean_timelimit' => $this->_config->get_integer( 'timelimit.cache_gc' ),
			)
		);

		$w3_cache_file_cleaner->clean();
	}

	/**
	 * Modifies the WordPress cron schedules to include custom schedules for database cache cleanup.
	 *
	 * @param array $schedules Existing WordPress cron schedules.
	 *
	 * @return array Modified array of cron schedules.
	 */
	public function cron_schedules( $schedules ) {
		$c               = $this->_config;
		$dbcache_enabled = $c->get_boolean( 'dbcache.enabled' );
		$engine          = $c->get_string( 'dbcache.engine' );

		if ( $dbcache_enabled && ( 'file' === $engine || 'file_generic' === $engine ) ) {
			$interval                        = $c->get_integer( 'dbcache.file.gc' );
			$schedules['w3_dbcache_cleanup'] = array(
				'interval' => $interval,
				'display'  => sprintf(
					// translators: 1 interval in seconds.
					__( '[W3TC] Database Cache file GC (every %d seconds)', 'w3-total-cache' ),
					$interval
				),
			);
		}

		return $schedules;
	}

	/**
	 * Triggers the purging of the database cache via wp-cron.
	 *
	 * Dispatches the CacheFlush component to handle the flush operation.
	 *
	 * @return void
	 */
	public function w3tc_dbcache_purge_wpcron() {
		$flusher = Dispatcher::component( 'CacheFlush' );
		$flusher->dbcache_flush();
	}

	/**
	 * Handles generic cache changes triggered by various events.
	 *
	 * Ensures the database cache is flushed only once during a request cycle.
	 *
	 * @return void
	 */
	public function on_change() {
		static $flushed = false;

		if ( ! $flushed ) {
			$flusher = Dispatcher::component( 'CacheFlush' );
			$flusher->dbcache_flush();

			$flushed = true;
		}
	}

	/**
	 * Handles cache changes triggered by post-related actions.
	 *
	 * Validates the post before initiating a database cache flush.
	 *
	 * @param int          $post_id The ID of the post being changed.
	 * @param WP_Post|null $post    Optional post object.
	 *
	 * @return void
	 */
	public function on_post_change( $post_id = 0, $post = null ) {
		static $flushed = false;

		if ( ! $flushed ) {
			if ( is_null( $post ) ) {
				$post = $post_id;
			}

			if ( $post_id > 0 && ! Util_Environment::is_flushable_post( $post, 'dbcache', $this->_config ) ) {
				return;
			}

			$flusher = Dispatcher::component( 'CacheFlush' );
			$flusher->dbcache_flush();

			$flushed = true;
		}
	}

	/**
	 * Handles cache changes triggered by comment-related actions.
	 *
	 * Flushes the database cache based on the post associated with the comment.
	 *
	 * @param int $comment_id The ID of the comment being changed.
	 *
	 * @return void
	 */
	public function on_comment_change( $comment_id ) {
		$post_id = 0;

		if ( $comment_id ) {
			$comment = get_comment( $comment_id, ARRAY_A );
			$post_id = ! empty( $comment['comment_post_ID'] ) ? (int) $comment['comment_post_ID'] : 0;
		}

		$this->on_post_change( $post_id );
	}

	/**
	 * Handles comment status updates and triggers a cache flush.
	 *
	 * @link https://developer.wordpress.org/reference/functions/wp_set_comment_status/
	 *
	 * @param int    $comment_id The ID of the comment being updated.
	 * @param string $status     The new comment status.
	 *
	 * @return void
	 */
	public function on_comment_status( $comment_id, $status ) {
		$this->on_comment_change( $comment_id );
	}

	/**
	 * Modifies the W3 Total Cache admin bar menu items.
	 *
	 * Adds a menu item for flushing the database cache.
	 *
	 * @param array $menu_items Existing admin bar menu items.
	 *
	 * @return array Modified admin bar menu items.
	 */
	public function w3tc_admin_bar_menu( $menu_items ) {
		$menu_items['20310.dbcache'] = array(
			'id'     => 'w3tc_flush_dbcache',
			'parent' => 'w3tc_flush',
			'title'  => __( 'Database', 'w3-total-cache' ),
			'href'   => wp_nonce_url(
				admin_url( 'admin.php?page=w3tc_dashboard&amp;w3tc_flush_dbcache' ),
				'w3tc'
			),
		);

		return $menu_items;
	}

	/**
	 * Collects usage statistics for the current request related to the database cache.
	 *
	 * Delegates the request statistics to the ObjectCache_WpObjectCache_Regular component.
	 *
	 * @param array $storage The storage array for request usage statistics.
	 *
	 * @return void
	 */
	public function w3tc_usage_statistics_of_request( $storage ) {
		$o = Dispatcher::component( 'ObjectCache_WpObjectCache_Regular' );
		$o->w3tc_usage_statistics_of_request( $storage );
	}

	/**
	 * Adds custom metrics for usage statistics related to the database cache.
	 *
	 * @param array $metrics Existing usage statistics metrics.
	 *
	 * @return array Modified array of metrics.
	 */
	public function w3tc_usage_statistics_metrics( $metrics ) {
		return array_merge(
			$metrics,
			array(
				'dbcache_calls_total',
				'dbcache_calls_hits',
				'dbcache_flushes',
				'dbcache_time_ms',
			)
		);
	}

	/**
	 * Adds custom sources for usage statistics based on the configured database cache engine.
	 *
	 * @param array $sources Existing usage statistics sources.
	 *
	 * @return array Modified array of sources.
	 */
	public function w3tc_usage_statistics_sources( $sources ) {
		$c = Dispatcher::config();
		if ( 'apc' === $c->get_string( 'dbcache.engine' ) ) {
			$sources['apc_servers']['dbcache'] = array(
				'name' => __( 'Database Cache', 'w3-total-cache' ),
			);
		} elseif ( 'memcached' === $c->get_string( 'dbcache.engine' ) ) {
			$sources['memcached_servers']['dbcache'] = array(
				'servers'  => $c->get_array( 'dbcache.memcached.servers' ),
				'username' => $c->get_string( 'dbcache.memcached.username' ),
				'password' => $c->get_string( 'dbcache.memcached.password' ),
				'name'     => __( 'Database Cache', 'w3-total-cache' ),
			);
		} elseif ( 'redis' === $c->get_string( 'dbcache.engine' ) ) {
			$sources['redis_servers']['dbcache'] = array(
				'servers'                 => $c->get_array( 'dbcache.redis.servers' ),
				'verify_tls_certificates' => $c->get_boolean( 'dbcache.redis.verify_tls_certificates' ),
				'username'                => $c->get_boolean( 'dbcache.redis.username' ),
				'dbid'                    => $c->get_integer( 'dbcache.redis.dbid' ),
				'password'                => $c->get_string( 'dbcache.redis.password' ),
				'name'                    => __( 'Database Cache', 'w3-total-cache' ),
			);
		}

		return $sources;
	}
}
