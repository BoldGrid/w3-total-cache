<?php
/**
 * File: Cdnfsd_CacheFlush.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdnfsd_CacheFlush
 */
class Cdnfsd_CacheFlush {
	/**
	 * Array of urls to flush.
	 *
	 * @var array
	 */
	private $queued_urls = array();

	/**
	 * Flush all requests flag.
	 *
	 * @var bool
	 */
	private $flush_all_requested = false;

	/**
	 * Purges everything from CDNs that supports it.
	 *
	 * @param array $extras Extras to purge.
	 *
	 * @return bool
	 */
	public static function w3tc_flush_all( $extras = null ) {
		if ( isset( $extras['only'] ) && 'cdn' !== $extras['only'] ) {
			return;
		}

		$w3tc_config      = Dispatcher::config();
		$common           = Dispatcher::component( 'Cdn_Core' );
		$default_override = Cdn_Util::get_flush_manually_default_override();
		if ( $w3tc_config->get_boolean( 'cdn.flush_manually', $default_override ) ) {
			// in this mode flush only on purge button clicks.
			if ( ! isset( $extras['ui_action'] ) ) {
				return true;
			}
		}

		$w3tc_o                      = Dispatcher::component( 'Cdnfsd_CacheFlush' );
		$w3tc_o->flush_all_requested = true;

		return true;
	}

	/**
	 * Purges cdn's post cache
	 *
	 * @param integer $post_id Post ID.
	 * @param boolean $force   Force Flag (optional).
	 * @param array   $extras  Extras.
	 *
	 * @return bool
	 */
	public static function w3tc_flush_post( $post_id, $force = false, $extras = null ) {
		if ( ! $post_id ) {
			$post_id = Util_Environment::detect_post_id();
		}

		if ( ! $post_id ) {
			return false;
		}

		$w3tc_config      = Dispatcher::config();
		$common           = Dispatcher::component( 'Cdn_Core' );
		$default_override = Cdn_Util::get_flush_manually_default_override();
		if ( $w3tc_config->get_boolean( 'cdn.flush_manually', $default_override ) ) {
			// in this mode flush only on purge button clicks.
			if ( ! isset( $extras['ui_action'] ) ) {
				return true;
			}
		}

		global $wp_rewrite; // required by many Util_PageUrls methods.
		if ( empty( $wp_rewrite ) ) {
			error_log( __( 'Post was modified before wp_rewrite initialization. Cant flush cache.', 'w3-total-cache' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return false;
		}

		$full_urls = array();
		$post      = null;
		$terms     = array();

		$feeds            = $w3tc_config->get_array( 'pgcache.purge.feed.types' );
		$limit_post_pages = $w3tc_config->get_integer( 'pgcache.purge.postpages_limit' );

		if ( $w3tc_config->get_boolean( 'pgcache.purge.terms' ) || $w3tc_config->get_boolean( 'varnish.pgcache.feed.terms' ) ) {
			$taxonomies = get_post_taxonomies( $post_id );
			$terms      = wp_get_post_terms( $post_id, $taxonomies );
		}

		switch ( true ) {
			case $w3tc_config->get_boolean( 'pgcache.purge.author' ):
			case $w3tc_config->get_boolean( 'pgcache.purge.archive.daily' ):
			case $w3tc_config->get_boolean( 'pgcache.purge.archive.monthly' ):
			case $w3tc_config->get_boolean( 'pgcache.purge.archive.yearly' ):
			case $w3tc_config->get_boolean( 'pgcache.purge.feed.author' ):
				$post = get_post( $post_id );
		}

		$front_page = get_option( 'show_on_front' );

		// Home (Frontpage) URL.
		if ( ( $w3tc_config->get_boolean( 'pgcache.purge.home' ) &&
			'posts' === $front_page ) ||
			$w3tc_config->get_boolean( 'pgcache.purge.front_page' ) ) {
			$full_urls = array_merge( $full_urls, Util_PageUrls::get_frontpage_urls( $limit_post_pages ) );
		}

		// Home (Post page) URL.
		if ( $w3tc_config->get_boolean( 'pgcache.purge.home' ) && 'posts' !== $front_page ) {
			$full_urls = array_merge( $full_urls, Util_PageUrls::get_postpage_urls( $limit_post_pages ) );
		}

		// Post URL.
		if ( $w3tc_config->get_boolean( 'pgcache.purge.post' ) || $force ) {
			$full_urls = array_merge( $full_urls, Util_PageUrls::get_post_urls( $post_id ) );
		}

		// Post comments URLs.
		if ( $w3tc_config->get_boolean( 'pgcache.purge.comments' ) && function_exists( 'get_comments_pagenum_link' ) ) {
			$full_urls = array_merge( $full_urls, Util_PageUrls::get_post_comments_urls( $post_id ) );
		}

		// Post author URLs.
		if ( $w3tc_config->get_boolean( 'pgcache.purge.author' ) && $post ) {
			$full_urls = array_merge( $full_urls, Util_PageUrls::get_post_author_urls( $post->post_author, $limit_post_pages ) );
		}

		/**
		 * Post terms URLs
		 */
		if ( $w3tc_config->get_boolean( 'pgcache.purge.terms' ) ) {
			$full_urls = array_merge( $full_urls, Util_PageUrls::get_post_terms_urls( $terms, $limit_post_pages ) );
		}

		/**
		 * Daily archive URLs
		 */
		if ( $w3tc_config->get_boolean( 'pgcache.purge.archive.daily' ) && $post ) {
			$full_urls = array_merge( $full_urls, Util_PageUrls::get_daily_archive_urls( $post, $limit_post_pages ) );
		}

		/**
		 * Monthly archive URLs
		 */
		if ( $w3tc_config->get_boolean( 'pgcache.purge.archive.monthly' ) && $post ) {
			$full_urls = array_merge( $full_urls, Util_PageUrls::get_monthly_archive_urls( $post, $limit_post_pages ) );
		}

		// Yearly archive URLs.
		if ( $w3tc_config->get_boolean( 'pgcache.purge.archive.yearly' ) && $post ) {
			$full_urls = array_merge( $full_urls, Util_PageUrls::get_yearly_archive_urls( $post, $limit_post_pages ) );
		}

		// Feed blog URLs.
		if ( $w3tc_config->get_boolean( 'pgcache.purge.feed.blog' ) ) {
			$full_urls = array_merge( $full_urls, Util_PageUrls::get_feed_urls( $feeds ) );
		}

		// Feed comments URLs.
		if ( $w3tc_config->get_boolean( 'pgcache.purge.feed.comments' ) ) {
			$full_urls = array_merge( $full_urls, Util_PageUrls::get_feed_comments_urls( $post_id, $feeds ) );
		}

		// Feed autor URLs.
		if ( $w3tc_config->get_boolean( 'pgcache.purge.feed.author' ) && $post ) {
			$full_urls = array_merge( $full_urls, Util_PageUrls::get_feed_author_urls( $post->post_author, $feeds ) );
		}

		// Feed terms URLs.
		if ( $w3tc_config->get_boolean( 'pgcache.purge.feed.terms' ) ) {
			$full_urls = array_merge( $full_urls, Util_PageUrls::get_feed_terms_urls( $terms, $feeds ) );
		}

		// Purge selected pages.
		if ( $w3tc_config->get_array( 'pgcache.purge.pages' ) ) {
			$pages     = $w3tc_config->get_array( 'pgcache.purge.pages' );
			$full_urls = array_merge( $full_urls, Util_PageUrls::get_pages_urls( $pages ) );
		}

		// Queue flush.
		if ( count( $full_urls ) ) {
			$w3tc_o = Dispatcher::component( 'Cdnfsd_CacheFlush' );

			foreach ( $full_urls as $w3tc_url ) {
				$w3tc_o->queued_urls[ $w3tc_url ] = '*';
			}
		}

		return true;
	}

	/**
	 * Purge a single url.
	 *
	 * @param unknown $w3tc_url URL to purge.
	 * @param array   $extras Extras.
	 *
	 * @return bool
	 */
	public static function w3tc_flush_url( $w3tc_url, $extras = null ) {
		$w3tc_config      = Dispatcher::config();
		$common           = Dispatcher::component( 'Cdn_Core' );
		$default_override = Cdn_Util::get_flush_manually_default_override();
		if ( $w3tc_config->get_boolean( 'cdn.flush_manually', $default_override ) ) {
			// in this mode flush only on purge button clicks.
			if ( ! isset( $extras['ui_action'] ) ) {
				return true;
			}
		}

		$w3tc_o                           = Dispatcher::component( 'Cdnfsd_CacheFlush' );
		$w3tc_o->queued_urls[ $w3tc_url ] = '*';

		return true;
	}

	/**
	 * Clears global and repeated urls
	 *
	 * @param array $actions_made Actions made.
	 *
	 * @throws \Exception Exception.
	 *
	 * @return array
	 */
	public static function w3tc_flush_execute_delayed_operations( $actions_made ) {
		$w3tc_o = Dispatcher::component( 'Cdnfsd_CacheFlush' );

		// Protection from incorrect w3tc upgrade operation when engine gets empty.
		$w3tc_c      = Dispatcher::config();
		$w3tc_engine = $w3tc_c->get_string( 'cdnfsd.engine' );
		if ( empty( $w3tc_engine ) ) {
			return $actions_made;
		}

		if ( $w3tc_o->flush_all_requested ) {
			$core = Dispatcher::component( 'Cdnfsd_Core' );

			try {
				$w3tc_engine = $core->get_engine();

				if ( ! is_null( $w3tc_engine ) ) {
					$w3tc_engine->flush_all();
					$actions_made[] = array( 'module' => 'cdn' );
				}
			} catch ( \Exception $ex ) {
				$actions_made[] = array(
					'module' => 'cdn',
					'error'  => $ex->getMessage(),
				);
			}

			$w3tc_o->flush_all_requested = false;
			$w3tc_o->queued_urls         = array();
		} else {
			$w3tc_count = count( $w3tc_o->queued_urls );
			if ( $w3tc_count > 0 ) {
				$urls = array_keys( $w3tc_o->queued_urls );

				$core = Dispatcher::component( 'Cdnfsd_Core' );

				try {
					$w3tc_engine = $core->get_engine();

					if ( ! is_null( $w3tc_engine ) ) {
						$w3tc_engine->flush_urls( $urls );
						$actions_made[] = array( 'module' => 'cdn' );
					}
				} catch ( \Exception $ex ) {
					$actions_made[] = array(
						'module' => 'cdn',
						'error'  => $ex->getMessage(),
					);
				}

				$w3tc_o->queued_urls = array();
			}
		}

		return $actions_made;
	}
}
