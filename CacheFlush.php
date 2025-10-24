<?php
/**
 * File: CacheFlush.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class CacheFlush
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class CacheFlush {
	/**
	 * Config
	 *
	 * @var Config $_config
	 */
	private $_config;

	/**
	 * Executor
	 *
	 * @var Object $_executor
	 */
	private $_executor;

	/**
	 * Initializes the cache flush executor and registers necessary hooks.
	 *
	 * This constructor sets up the cache flush mechanism based on configuration, using either
	 * a local executor or a message bus for distributed environments. It also registers
	 * WordPress actions and filters to handle delayed operations and cache flush events.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
		$sns           = $this->_config->get_boolean( 'cluster.messagebus.enabled' );

		if ( $sns ) {
			$this->_executor = new Enterprise_CacheFlush_MakeSnsEvent();
		} else {
			$this->_executor = new CacheFlush_Locally();
		}

		if ( function_exists( 'add_action' ) ) {
			add_action( 'w3tc_redirect', array( $this, 'execute_delayed_operations' ), 100000, 0 );
			add_filter( 'wp_redirect', array( $this, 'execute_delayed_operations_filter' ), 100000, 1 );
			add_action( 'w3tc_messagebus_message_processed', array( $this, 'execute_delayed_operations' ), 0 );
			add_action( 'shutdown', array( $this, 'execute_delayed_operations' ), 100000, 0 );
		}
	}

	/**
	 * Flushes the database cache if enabled.
	 *
	 * This method clears the database cache by delegating the operation to the executor
	 * if the database cache is enabled in the configuration.
	 *
	 * @return void
	 */
	public function dbcache_flush() {
		if ( $this->_config->get_boolean( 'dbcache.enabled' ) ) {
			$this->_executor->dbcache_flush();
		}
	}

	/**
	 * Flushes the minification cache if enabled.
	 *
	 * This method clears the minification cache by delegating the operation to the executor
	 * if the minification feature is enabled in the configuration.
	 *
	 * @return void
	 */
	public function minifycache_flush() {
		if ( $this->_config->get_boolean( 'minify.enabled' ) ) {
			$this->_executor->minifycache_flush();
		}
	}

	/**
	 * Flushes the object cache if enabled.
	 *
	 * This method clears the object cache by delegating the operation to the executor
	 * if the object cache feature is enabled in the configuration.
	 *
	 * @return void
	 */
	public function objectcache_flush() {
		if ( $this->_config->getf_boolean( 'objectcache.enabled' ) ) {
			$this->_executor->objectcache_flush();
		}
	}

	/**
	 * Flushes the fragment cache.
	 *
	 * This method clears all stored fragment cache entries using the executor.
	 *
	 * @return void
	 */
	public function fragmentcache_flush() {
		$this->_executor->fragmentcache_flush();
	}

	/**
	 * Flushes a specific fragment cache group.
	 *
	 * @param string $group The cache group to flush.
	 *
	 * @return void
	 */
	public function fragmentcache_flush_group( $group ) {
		$this->_executor->fragmentcache_flush_group( $group );
	}

	/**
	 * Flushes the browser cache if enabled.
	 *
	 * This method clears the browser cache rules by delegating the operation to the executor
	 * if the browser cache feature is enabled in the configuration.
	 *
	 * @return void
	 */
	public function browsercache_flush() {
		if ( $this->_config->get_boolean( 'browsercache.enabled' ) ) {
			$this->_executor->browsercache_flush();
		}
	}

	/**
	 * Purges all CDN cache files.
	 *
	 * This method clears all files stored in the CDN cache if the CDN feature is enabled.
	 *
	 * @param array $extras Additional options or context for the purge.
	 *
	 * @return bool True on success, false otherwise.
	 */
	public function cdn_purge_all( $extras = array() ) {
		if ( $this->_config->get_boolean( 'cdn.enabled' ) || $this->_config->get_boolean( 'cdnfsd.enabled' ) ) {
			return $this->_executor->cdn_purge_all( $extras );
		}

		return false;
	}

	/**
	 * Purges specific files from the CDN cache.
	 *
	 * @param array $purgefiles List of file paths to purge from the CDN cache.
	 *
	 * @return void
	 */
	public function cdn_purge_files( $purgefiles ) {
		$this->_executor->cdn_purge_files( $purgefiles );
	}

	/**
	 * Flushes the OPcache.
	 *
	 * This method clears the PHP OPcache by delegating the operation to the executor.
	 *
	 * @return bool True on success, false otherwise.
	 */
	public function opcache_flush() {
		return $this->_executor->opcache_flush();
	}

	/**
	 * Flushes the cache for a specific post.
	 *
	 * @param int   $post_id The ID of the post to flush.
	 * @param mixed $extras  Additional options or context for the flush.
	 *
	 * @return bool True on success, false otherwise.
	 */
	public function flush_post( $post_id, $extras = null ) {
		return $this->_executor->flush_post( $post_id, $extras );
	}

	/**
	 * Retrieves a list of flushable posts.
	 *
	 * This method applies a filter to allow customization of which posts can be flushed.
	 *
	 * @param mixed $extras Additional options or context for filtering flushable posts.
	 *
	 * @return array|bool List of flushable posts or false if none are defined.
	 */
	public function flushable_posts( $extras = null ) {
		$flushable_posts = apply_filters( 'w3tc_flushable_posts', false, $extras );
		return $flushable_posts;
	}

	/**
	 * Flushes all eligible posts.
	 *
	 * This method clears the cache for all eligible posts as determined by the executor.
	 *
	 * @param mixed $extras Additional options or context for the flush.
	 *
	 * @return bool True on success, false otherwise.
	 */
	public function flush_posts( $extras = null ) {
		return $this->_executor->flush_posts( $extras );
	}

	/**
	 * Flushes all caches.
	 *
	 * This method clears all caches once per execution, preventing redundant flush operations.
	 *
	 * @param mixed $extras Additional options or context for the flush.
	 *
	 * @return void
	 */
	public function flush_all( $extras = null ) {
		static $flushed = false;
		if ( ! $flushed ) {
			$flushed = true;

			if ( Util_Environment::is_elementor() ) {
				// Flush Elementor's file manager cache.
				\elementor\Plugin::$instance->files_manager->clear_cache();

				// Flush W3 Total Cache's Object Cache to ensure Elementor changes are reflected.
				$this->objectcache_flush();
			}

			$this->_executor->flush_all( $extras );
		}
	}

	/**
	 * Flushes a specific cache group.
	 *
	 * @param string $group  The cache group to flush.
	 * @param mixed  $extras Additional options or context for the flush.
	 *
	 * @return void
	 */
	public function flush_group( $group, $extras = null ) {
		static $flushed_groups = array();
		if ( ! isset( $flushed_groups[ $group ] ) ) {
			$flushed_groups[ $group ] = '*';
			$this->_executor->flush_group( $group, $extras );
		}
	}

	/**
	 * Flushes a specific URL from the cache.
	 *
	 * This method clears the cache for a single URL, ensuring it is only flushed once per execution.
	 *
	 * @param string $url    The URL to flush.
	 * @param mixed  $extras Additional options or context for the flush.
	 *
	 * @return bool True on success, false otherwise.
	 */
	public function flush_url( $url, $extras = null ) {
		static $flushed_urls = array();

		if ( ! in_array( $url, $flushed_urls, true ) ) {
			$flushed_urls[] = $url;
			return $this->_executor->flush_url( $url, $extras );
		}
		return true;
	}

	/**
	 * Primes the cache for a specific post.
	 *
	 * This method preloads the cache for the specified post by delegating to the executor.
	 *
	 * @param int $post_id The ID of the post to prime.
	 *
	 * @return bool True on success, false otherwise.
	 */
	public function prime_post( $post_id ) {
		return $this->_executor->prime_post( $post_id );
	}

	/**
	 * Executes delayed cache operations.
	 *
	 * This method processes any delayed operations queued by the executor.
	 *
	 * @return bool True on success, false otherwise.
	 */
	public function execute_delayed_operations() {
		return $this->_executor->execute_delayed_operations();
	}


	/**
	 * Executes delayed cache operations as part of a filter.
	 *
	 * This method ensures delayed operations are executed and returns the original value.
	 *
	 * @param mixed $v The value being filtered.
	 *
	 * @return mixed The unmodified value.
	 */
	public function execute_delayed_operations_filter( $v ) {
		$this->execute_delayed_operations();

		return $v;
	}
}
