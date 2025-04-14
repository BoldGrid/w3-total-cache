<?php
/**
 * File: Generic_AdminActions_Flush.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Generic_AdminActions_Flush
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class Generic_AdminActions_Flush {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config = null;

	/**
	 * Initializes the class and sets up configurations.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Flushes all caches and redirects after the operation.
	 *
	 * @return void
	 */
	public function w3tc_flush_all() {
		w3tc_flush_all( array( 'ui_action' => 'flush_button' ) );

		$state_note = Dispatcher::config_state_note();
		$state_note->set( 'common.show_note.flush_statics_needed', false );
		$state_note->set( 'common.show_note.flush_posts_needed', false );
		$state_note->set( 'common.show_note.plugins_updated', false );

		$this->_redirect_after_flush( 'flush_all' );
	}

	/**
	 * Flushes the cache for the current page and outputs a success message.
	 *
	 * @return void
	 */
	public function w3tc_flush_current_page() {
		$url = filter_input( INPUT_GET, 'url', FILTER_SANITIZE_URL );
		if ( empty( $url ) && isset( $_SERVER['HTTP_REFERER'] ) ) {
			$url = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		}

		w3tc_flush_url( $url );

		?>
		<div style="text-align: center; margin-top: 30px">
			<h3><?php esc_html_e( 'Page has been flushed successfully', 'w3-total-cache' ); ?></h3>
			<a id="w3tc_return" href="<?php echo esc_attr( $url ); ?>"><?php esc_html_e( 'Return', 'w3-total-cache' ); ?></a>
		</div>
		<script>
			setTimeout(function() {
				window.location = document.getElementById('w3tc_return').href;
			}, 2000);
		</script>
		<?php
		exit();
	}

	/**
	 * Flushes Memcached cache and redirects after the operation.
	 *
	 * @return void
	 */
	public function w3tc_flush_memcached() {
		$this->flush_memcached();

		Util_Admin::redirect(
			array(
				'w3tc_note' => 'flush_memcached',
			),
			true
		);
	}

	/**
	 * Flushes opcode cache and redirects after the operation.
	 *
	 * @return void
	 */
	public function w3tc_flush_opcode() {
		$this->flush_opcode();

		Util_Admin::redirect(
			array(
				'w3tc_note' => 'flush_opcode',
			),
			true
		);
	}

	/**
	 * Flushes file cache and redirects after the operation.
	 *
	 * @return void
	 */
	public function w3tc_flush_file() {
		$this->flush_file();

		Util_Admin::redirect(
			array(
				'w3tc_note' => 'flush_file',
			),
			true
		);
	}

	/**
	 * Flushes static files cache and redirects after the operation.
	 *
	 * @return void
	 */
	public function w3tc_flush_statics() {
		$cf = Dispatcher::component( 'CacheFlush' );
		$cf->browsercache_flush();
		w3tc_flush_posts();

		$state_note = Dispatcher::config_state_note();
		$state_note->set( 'common.show_note.flush_statics_needed', false );
		$state_note->set( 'common.show_note.flush_posts_needed', false );
		$state_note->set( 'common.show_note.plugins_updated', false );

		Util_Admin::redirect_with_custom_messages2(
			array(
				'notes' => array(
					__( 'Static files cache successfully emptied.', 'w3-total-cache' ),
				),
			),
			true
		);
	}

	/**
	 * Flushes posts cache and redirects after the operation.
	 *
	 * @return void
	 */
	public function w3tc_flush_posts() {
		w3tc_flush_posts();

		$state_note = Dispatcher::config_state_note();
		$state_note->set( 'common.show_note.flush_posts_needed', false );
		$state_note->set( 'common.show_note.plugins_updated', false );

		$this->_redirect_after_flush( 'flush_pgcache' );
	}

	/**
	 * Flushes page cache and redirects after the operation.
	 *
	 * @return void
	 */
	public function w3tc_flush_pgcache() {
		w3tc_flush_posts( array( 'ui_action' => 'flush_button' ) );

		$state_note = Dispatcher::config_state_note();
		$state_note->set( 'common.show_note.flush_posts_needed', false );
		$state_note->set( 'common.show_note.plugins_updated', false );

		Util_Admin::redirect(
			array(
				'w3tc_note' => 'flush_pgcache',
			),
			true
		);
	}

	/**
	 * Flushes database cache and redirects after the operation.
	 *
	 * @return void
	 */
	public function w3tc_flush_dbcache() {
		$this->flush_dbcache();

		Util_Admin::redirect(
			array(
				'w3tc_note' => 'flush_dbcache',
			),
			true
		);
	}

	/**
	 * Flushes object cache and redirects after the operation.
	 *
	 * @return void
	 */
	public function w3tc_flush_objectcache() {
		$this->flush_objectcache();

		$state_note = Dispatcher::config_state_note();
		$state_note->set( 'objectcache.show_note.flush_needed', false );

		Util_Admin::redirect(
			array(
				'w3tc_note' => 'flush_objectcache',
			),
			true
		);
	}

	/**
	 * Flushes fragment cache and updates configurations.
	 *
	 * @return void
	 */
	public function w3tc_flush_fragmentcache() {
		$this->flush_fragmentcache();

		$this->_config->set( 'notes.need_empty_fragmentcache', false );
		$this->_config->save();

		Util_Admin::redirect(
			array(
				'w3tc_note' => 'flush_fragmentcache',
			),
			true
		);
	}

	/**
	 * Flushes minify cache and redirects after the operation.
	 *
	 * @return void
	 */
	public function w3tc_flush_minify() {
		$this->flush_minify();

		$state_note = Dispatcher::config_state_note();
		$state_note->set( 'minify.show_note.need_flush', false );

		Util_Admin::redirect(
			array(
				'w3tc_note' => 'flush_minify',
			),
			true
		);
	}

	/**
	 * Flushes browser cache and redirects after the operation.
	 *
	 * @return void
	 */
	public function w3tc_flush_browser_cache() {
		$cacheflush = Dispatcher::component( 'CacheFlush' );
		$cacheflush->browsercache_flush();

		$state_note = Dispatcher::config_state_note();
		$state_note->set( 'common.show_note.flush_statics_needed', false );
		$state_note->set( 'common.show_note.flush_posts_needed', true );

		Util_Admin::redirect(
			array(
				'w3tc_note' => 'flush_browser_cache',
			),
			true
		);
	}

	/**
	 * Flushes Varnish cache and redirects after the operation.
	 *
	 * @return void
	 */
	public function w3tc_flush_varnish() {
		$this->flush_varnish();

		Util_Admin::redirect(
			array(
				'w3tc_note' => 'flush_varnish',
			),
			true
		);
	}

	/**
	 * Flushes CDN cache and redirects after the operation.
	 *
	 * @return void
	 */
	public function w3tc_flush_cdn() {
		$this->flush_cdn( array( 'ui_action' => 'flush_button' ) );

		Util_Admin::redirect(
			array(
				'w3tc_note' => 'flush_cdn',
			),
			true
		);
	}

	/**
	 * Flushes cache for a specific post and redirects after the operation.
	 *
	 * @return void
	 */
	public function w3tc_flush_post() {
		$post_id = Util_Request::get_integer( 'post_id' );
		w3tc_flush_post( $post_id, true, array( 'ui_action' => 'flush_button' ) );

		Util_Admin::redirect(
			array(
				'w3tc_note' => 'pgcache_purge_post',
			),
			true
		);
	}

	/**
	 * Flushes caches based on the given type.
	 *
	 * @param string $type The type of cache to flush.
	 *
	 * @return void
	 */
	public function flush( $type ) {
		$state      = Dispatcher::config_state();
		$state_note = Dispatcher::config_state_note();

		if ( $this->_config->get_string( 'pgcache.engine' ) === $type && $this->_config->get_boolean( 'pgcache.enabled' ) ) {
			$state_note->set( 'common.show_note.flush_posts_needed', false );
			$state_note->set( 'common.show_note.plugins_updated', false );

			$pgcacheflush = Dispatcher::component( 'PgCache_Flush' );
			$pgcacheflush->flush();
			$pgcacheflush->flush_post_cleanup();
		}

		if ( $this->_config->get_string( 'dbcache.engine' ) === $type && $this->_config->get_boolean( 'dbcache.enabled' ) ) {
			$this->flush_dbcache();
		}

		if ( $this->_config->get_string( 'objectcache.engine' ) === $type && $this->_config->getf_boolean( 'objectcache.enabled' ) ) {
			$this->flush_objectcache();
		}

		if ( $this->_config->get_string( array( 'fragmentcache', 'engine' ) ) === $type ) {
			$this->flush_fragmentcache();
		}

		if ( $this->_config->get_string( 'minify.engine' ) === $type && $this->_config->get_boolean( 'minify.enabled' ) ) {
			$state_note->set( 'minify.show_note.need_flush', false );
			$this->flush_minify();
		}
	}

	/**
	 * Flushes Memcached cache.
	 *
	 * @return void
	 */
	public function flush_memcached() {
		$this->flush( 'memcached' );
	}

	/**
	 * Flushes opcode cache.
	 *
	 * @return void
	 */
	public function flush_opcode() {
		$cacheflush = Dispatcher::component( 'CacheFlush' );
		$cacheflush->opcache_flush();
	}

	/**
	 * Flushes file cache.
	 *
	 * @return void
	 */
	public function flush_file() {
		$this->flush( 'file' );
		$this->flush( 'file_generic' );
	}

	/**
	 * Flushes database cache.
	 *
	 * @return void
	 */
	public function flush_dbcache() {
		$flusher = Dispatcher::component( 'CacheFlush' );
		$flusher->dbcache_flush();
	}

	/**
	 * Flushes object cache.
	 *
	 * @return void
	 */
	public function flush_objectcache() {
		$flusher = Dispatcher::component( 'CacheFlush' );
		$flusher->objectcache_flush();
	}

	/**
	 * Flushes fragment cache.
	 *
	 * @return void
	 */
	public function flush_fragmentcache() {
		$flusher = Dispatcher::component( 'CacheFlush' );
		$flusher->fragmentcache_flush();
	}

	/**
	 * Flushes minify cache.
	 *
	 * @return void
	 */
	public function flush_minify() {
		$w3_minify = Dispatcher::component( 'Minify_MinifiedFileRequestHandler' );
		$w3_minify->flush();
	}

	/**
	 * Flushes Varnish cache.
	 *
	 * @return void
	 */
	public function flush_varnish() {
		// this attaches execute_delayed_operations! otherwise specific module flush will not have effect.
		$cacheflush = Dispatcher::component( 'CacheFlush' );

		$varnishflush = Dispatcher::component( 'Varnish_Flush' );
		$varnishflush->flush();
	}

	/**
	 * Flushes CDN cache with optional additional parameters.
	 *
	 * @param array $extras Additional parameters for the cache flush operation.
	 *
	 * @return void
	 */
	public function flush_cdn( $extras = array() ) {
		$cacheflush = Dispatcher::component( 'CacheFlush' );
		$cacheflush->cdn_purge_all( $extras );
	}

	/**
	 * Redirects after a successful flush operation and handles errors.
	 *
	 * @param string $success_note A note to indicate the success of the flush operation.
	 *
	 * @return void
	 */
	private function _redirect_after_flush( $success_note ) {
		$flush  = Dispatcher::component( 'CacheFlush' );
		$status = $flush->execute_delayed_operations();

		$errors = array();
		foreach ( $status as $i ) {
			if ( isset( $i['error'] ) ) {
				$errors[] = $i['error'];
			}
		}

		if ( empty( $errors ) ) {
			Util_Admin::redirect(
				array(
					'w3tc_note' => $success_note,
				),
				true
			);
		} else {
			Util_Admin::redirect_with_custom_messages2(
				array(
					'errors' => array(
						'Failed to purge: ' . implode( ', ', $errors ),
					),
				),
				true
			);
		}
	}
}
