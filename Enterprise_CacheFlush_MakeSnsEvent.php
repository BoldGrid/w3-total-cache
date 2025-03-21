<?php
/**
 * File: Enterprise_CacheFlush_MakeSnsEvent.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Enterprise_CacheFlush_MakeSnsEvent
 *
 * Purge using AmazonSNS object
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */
class Enterprise_CacheFlush_MakeSnsEvent extends Enterprise_SnsBase {
	/**
	 * Messages
	 *
	 * @var array
	 */
	private $messages = array();

	/**
	 * Messages by signature
	 *
	 * @var array
	 */
	private $messages_by_signature = array();

	/**
	 * Flushes the database cache.
	 *
	 * @return void
	 */
	public function dbcache_flush() {
		$this->_prepare_message( array( 'action' => 'dbcache_flush' ) );
	}

	/**
	 * Flushes the minify cache.
	 *
	 * @return void
	 */
	public function minifycache_flush() {
		$this->_prepare_message( array( 'action' => 'minifycache_flush' ) );
	}

	/**
	 * Flushes the object cache.
	 *
	 * @return void
	 */
	public function objectcache_flush() {
		$this->_prepare_message( array( 'action' => 'objectcache_flush' ) );
	}

	/**
	 * Flushes the fragment cache.
	 *
	 * @return void
	 */
	public function fragmentcache_flush() {
		$this->_prepare_message( array( 'action' => 'fragmentcache_flush' ) );
	}

	/**
	 * Flushes a specific fragment cache group.
	 *
	 * @param string $group The cache group to flush.
	 *
	 * @return void
	 */
	public function fragmentcache_flush_group( $group ) {
		$this->_prepare_message(
			array(
				'action' => 'fragmentcache_flush_group',
				'group'  => $group,
			)
		);
	}

	/**
	 * Flushes the browser cache.
	 *
	 * @return void
	 */
	public function browsercache_flush() {
		$this->_prepare_message( array( 'action' => 'browsercache_flush' ) );
	}

	/**
	 * Purges all CDN cache.
	 *
	 * @param mixed|null $extras Optional additional data for purging.
	 *
	 * @return bool
	 */
	public function cdn_purge_all( $extras = null ) {
		return $this->_prepare_message(
			array(
				'action' => 'cdn_purge_all',
				'extras' => $extras,
			)
		);
	}

	/**
	 * Purges specific files from the CDN cache.
	 *
	 * @param array $purgefiles List of files to purge.
	 *
	 * @return void
	 */
	public function cdn_purge_files( $purgefiles ) {
		$this->_prepare_message(
			array(
				'action'     => 'cdn_purge_files',
				'purgefiles' => $purgefiles,
			)
		);
	}

	/**
	 * Cleans up the page cache.
	 *
	 * @return void
	 */
	public function pgcache_cleanup() {
		$this->_prepare_message( array( 'action' => 'pgcache_cleanup' ) );
	}

	/**
	 * Flushes the OPcache.
	 *
	 * @return void
	 */
	public function opcache_flush() {
		$this->_prepare_message( array( 'action' => 'opcache_flush' ) );
	}

	/**
	 * Flushes the cache for a specific post.
	 *
	 * @param int        $post_id The ID of the post to flush.
	 * @param mixed|null $extras  Optional additional data for flushing.
	 *
	 * @return bool
	 */
	public function flush_post( $post_id, $extras = null ) {
		return $this->_prepare_message(
			array(
				'action'  => 'flush_post',
				'post_id' => $post_id,
				'extras'  => $extras,
			)
		);
	}

	/**
	 * Flushes the cache for multiple posts.
	 *
	 * @param mixed $extras Additional data for flushing.
	 *
	 * @return bool
	 */
	public function flush_posts( $extras ) {
		return $this->_prepare_message(
			array(
				'action' => 'flush_posts',
				'extras' => $extras,
			)
		);
	}

	/**
	 * Flushes all caches.
	 *
	 * @param mixed $extras Additional data for flushing.
	 *
	 * @return bool
	 */
	public function flush_all( $extras ) {
		return $this->_prepare_message(
			array(
				'action' => 'flush_all',
				'extras' => $extras,
			)
		);
	}

	/**
	 * Flushes a specific cache group.
	 *
	 * @param string $group  The group to flush.
	 * @param mixed  $extras Additional data for flushing.
	 *
	 * @return bool
	 */
	public function flush_group( $group, $extras ) {
		return $this->_prepare_message(
			array(
				'action' => 'flush_group',
				'group'  => $group,
				'extras' => $extras,
			)
		);
	}

	/**
	 * Flushes the cache for a specific URL.
	 *
	 * @param string $url    The URL to flush.
	 * @param mixed  $extras Additional data for flushing.
	 *
	 * @return bool
	 */
	public function flush_url( $url, $extras ) {
		return $this->_prepare_message(
			array(
				'action' => 'flush_url',
				'url'    => $url,
				'extras' => $extras,
			)
		);
	}

	/**
	 * Primes the cache for a specific post.
	 *
	 * @param int $post_id The ID of the post to prime.
	 *
	 * @return bool
	 */
	public function prime_post( $post_id ) {
		return $this->_prepare_message(
			array(
				'action'  => 'prime_post',
				'post_id' => $post_id,
			)
		);
	}

	/**
	 * Prepares a message for the caching system.
	 *
	 * @param array $message The message to prepare.
	 *
	 * @return bool
	 */
	private function _prepare_message( $message ) {
		$message_signature = wp_json_encode( $message );
		if ( isset( $this->messages_by_signature[ $message_signature ] ) ) {
			return true;
		}

		$this->messages_by_signature[ $message_signature ] = '*';
		$this->messages[]                                  = $message;

		$action = $this->_get_action();
		if ( ! $action ) {
			$this->execute_delayed_operations();
			return true;
		}

		return true;
	}

	/**
	 * Executes delayed cache operations.
	 *
	 * @return bool
	 *
	 * @throws \Exception If an error occurs during API publishing.
	 */
	public function execute_delayed_operations() {
		if ( count( $this->messages ) <= 0 ) {
			return true;
		}

		$this->_log( $this->_get_action() . ' sending messages' );

		$message             = array();
		$message['actions']  = $this->messages;
		$message['blog_id']  = Util_Environment::blog_id();
		$message['host']     = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : null;
		$message['hostname'] = @gethostname();
		$v                   = wp_json_encode( $message );

		try {
			$api = $this->_get_api();
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				$origin = 'WP CLI';
			} else {
				$origin = 'WP';
			}

			$this->_log( $origin . ' sending message ' . $v );
			$this->_log( 'Host: ' . $message['host'] );

			if ( isset( $_SERVER['REQUEST_URI'] ) ) {
				$this->_log( 'URL: ' . sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
			}

			if ( function_exists( 'current_filter' ) ) {
				$this->_log( 'Current WP hook: ' . current_filter() );
			}

			$backtrace           = debug_backtrace(); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
			$backtrace_optimized = array();
			foreach ( $backtrace as $b ) {
				$opt                   = isset( $b['function'] ) ? $b['function'] . ' ' : '';
				$opt                  .= isset( $b['file'] ) ? $b['file'] . ' ' : '';
				$opt                  .= isset( $b['line'] ) ? '#' . $b['line'] . ' ' : '';
				$backtrace_optimized[] = $opt;

			}

			$this->_log( 'Backtrace ', $backtrace_optimized );

			$r = $api->publish(
				array(
					'Message'  => $v,
					'TopicArn' => $this->_topic_arn,
				)
			);
			if ( 200 !== $r['@metadata']['statusCode'] ) {
				$this->_log( 'Error' );
				$this->_log( wp_json_encode( $r ) );
				return false;
			}
		} catch ( \Exception $e ) {
			$this->_log( 'Error ' . $e->getMessage() );
			return false;
		}

		// on success - reset messages array, but not hash (not resent repeatedly the same messages).
		$this->messages = array();

		return true;
	}

	/**
	 * Retrieves the current action name.
	 *
	 * @return string The current action name.
	 */
	private function _get_action() {
		$action = '';
		if ( function_exists( 'current_filter' ) ) {
			$action = current_filter();
		}

		return $action;
	}
}
