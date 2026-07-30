<?php
/**
 * File: PgCache_Flush.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class PgCache_Plugin_Admin
 *
 * W3 PgCache plugin - administrative interface
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class PgCache_Plugin_Admin {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config = null;

	/**
	 * Initializes the plugin configuration.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Runs the plugin by registering various filters and actions.
	 *
	 * @return void
	 */
	public function run() {
		add_filter( 'w3tc_save_options', array( $this, 'w3tc_save_options' ) );

		$config_labels = new PgCache_ConfigLabels();
		add_filter( 'w3tc_config_labels', array( $config_labels, 'config_labels' ) );

		if ( $this->_config->get_boolean( 'pgcache.enabled' ) ) {
			add_filter( 'w3tc_errors', array( $this, 'w3tc_errors' ) );
			add_filter( 'w3tc_usage_statistics_summary_from_history', array( $this, 'w3tc_usage_statistics_summary_from_history' ), 10, 2 );
		}

		// Cache groups.
		add_action(
			'w3tc_config_ui_save-w3tc_cachegroups',
			array(
				'\W3TC\CacheGroups_Plugin_Admin',
				'w3tc_config_ui_save_w3tc_cachegroups',
			),
			10,
			1
		);
	}

	/**
	 * Cleans up the cache either locally or in a cluster.
	 *
	 * @return void
	 */
	public function cleanup() {
		// We check to see if we're dealing with a cluster.
		$w3tc_config = Dispatcher::config();
		$is_cluster  = $w3tc_config->get_boolean( 'cluster.messagebus.enabled' );

		// If we are, we notify the subscribers. If not, we just cleanup in here.
		if ( $is_cluster ) {
			$this->cleanup_cluster();
		} else {
			$this->cleanup_local();
		}
	}

	/**
	 * Cleans up the cache in a cluster environment.
	 *
	 * @return void
	 */
	public function cleanup_cluster() {
		$sns_client = Dispatcher::component( 'Enterprise_CacheFlush_MakeSnsEvent' );
		$sns_client->pgcache_cleanup();
	}

	/**
	 * Cleans up the cache in a local environment.
	 *
	 * @return void
	 */
	public function cleanup_local() {
		$w3tc_engine = $this->_config->get_string( 'pgcache.engine' );

		switch ( $w3tc_engine ) {
			case 'file':
				$w3_cache_file_cleaner = new Cache_File_Cleaner(
					array(
						'cache_dir'       => Util_Environment::cache_blog_dir( 'page' ),
						'clean_timelimit' => $this->_config->get_integer( 'timelimit.cache_gc' ),
					)
				);

				$w3_cache_file_cleaner->clean();
				break;

			case 'file_generic':
				if ( 0 === Util_Environment::blog_id() ) {
					$flush_dir = W3TC_CACHE_PAGE_ENHANCED_DIR;
				} else {
					$flush_dir = W3TC_CACHE_PAGE_ENHANCED_DIR . '/' . Util_Environment::host();
				}

				$w3_cache_file_cleaner_generic = new Cache_File_Cleaner_Generic(
					array(
						'exclude'         => array( // phpcs:ignore WordPressVIPMinimum
							'.htaccess',
						),
						'cache_dir'       => $flush_dir,
						'expire'          => $this->_config->get_integer( 'pgcache.lifetime' ),
						'clean_timelimit' => $this->_config->get_integer( 'timelimit.cache_gc' ),
					)
				);

				$w3_cache_file_cleaner_generic->clean();
				break;
		}
	}

	/**
	 * Primes the cache based on a sitemap or other criteria.
	 *
	 * @param int|null      $start        The starting point for priming, or null to use the default.
	 * @param int|null      $w3tc_limit        The limit for how many pages to prime, or null for the default limit.
	 * @param callable|null $log_callback A callback function for logging progress, or null to disable logging.
	 *
	 * @return void
	 */
	public function prime( $start = null, $w3tc_limit = null, $log_callback = null ) {
		if ( is_null( $start ) ) {
			$start = get_option( 'w3tc_pgcache_prime_offset' );
		}

		if ( $start < 0 ) {
			$start = 0;
		}

		$interval = $this->_config->get_integer( 'pgcache.prime.interval' );
		if ( is_null( $w3tc_limit ) ) {
			$w3tc_limit = $this->_config->get_integer( 'pgcache.prime.limit' );
		}

		if ( $w3tc_limit < 1 ) {
			$w3tc_limit = 1;
		}

		$sitemap = $this->_config->get_string( 'pgcache.prime.sitemap' );

		if ( ! is_null( $log_callback ) ) {
			$log_callback(
				'Priming from sitemap ' . $sitemap . ' entries ' . ( $start + 1 ) . '..' . ( $start + $w3tc_limit )
			);
		}

		// Parse XML sitemap.
		$urls = $this->parse_sitemap( $sitemap );

		// Queue URLs.
		$queue = array_slice( $urls, $start, $w3tc_limit );

		if ( count( $urls ) > ( $start + $w3tc_limit ) ) {
			$next_offset = $start + $w3tc_limit;
		} else {
			$next_offset = 0;
		}

		update_option( 'w3tc_pgcache_prime_offset', $next_offset, false );

		/**
		 * Make HTTP requests and prime cache.
		 * Use 'WordPress' since by default we use W3TC-powered by which blocks caching.
		 */
		foreach ( $queue as $w3tc_url ) {
			Util_Http::get( $w3tc_url, array( 'user-agent' => 'WordPress' ) );

			if ( ! is_null( $log_callback ) ) {
				$log_callback( 'Priming ' . $w3tc_url );
			}
		}
	}

	/**
	 * Parses a sitemap URL and returns the list of URLs contained in it.
	 *
	 * The sitemap URL is configured by an admin and can contain
	 * `<sitemap><loc>...</loc></sitemap>` entries that the parser will
	 * recursively fetch. Three constraints bound the outbound-fetch
	 * surface:
	 *
	 *  1. Each fetched URL must resolve to a public host (no localhost,
	 *     no RFC1918, no AWS metadata, etc.).
	 *  2. Nested `<sitemap>` entries are required to share the origin
	 *     of the root sitemap. A sub-URL placed inside a legitimately-
	 *     fetched sitemap can't redirect the fetcher to a third-party
	 *     host. Combined with (1) this also limits a recursive walk to
	 *     a single first-party origin.
	 *  3. Recursion is depth-capped (default 3). A pathological
	 *     sitemap that points at itself, or a deeply nested index, can
	 *     no longer DOS the worker.
	 *
	 * @param string      $w3tc_url           The URL of the sitemap to parse.
	 * @param string|null $origin_host   Internal: host of the root sitemap; nested
	 *                                   fetches must match. Auto-populated.
	 * @param int         $depth         Internal: current recursion depth (0-based).
	 *
	 * @return array The list of URLs parsed from the sitemap.
	 */
	public function parse_sitemap( $w3tc_url, $origin_host = null, $depth = 0 ) {
		if ( ! Util_Environment::is_url( $w3tc_url ) ) {
			$w3tc_url = home_url( $w3tc_url );
		}

		/**
		 * Depth cap. The first call lands at depth 0; nested fetches
		 * increment. Three levels is enough to cover a sitemap index
		 * → child sitemap → URL list shape, the deepest WordPress
		 * emits in practice. Refusal returns the empty array, NOT
		 * `array( $w3tc_url )` — the caller (`prime()`) iterates the
		 * returned list and calls `Util_Http::get()` on each entry, so
		 * returning the rejected URL would defeat the depth cap
		 * (the URL would still be fetched, just not recursed into).
		 */
		if ( $depth > 3 ) {
			return array();
		}

		/**
		 * Per-hop public-host check. Refused URLs are dropped from the
		 * fetch list for the same reason as above.
		 */
		if ( ! Util_Url::is_public_host( $w3tc_url ) ) {
			return array();
		}

		// First call sets the origin host for the rest of the recursion.
		$current_host = \wp_parse_url( $w3tc_url, PHP_URL_HOST );
		if ( null === $origin_host ) {
			$origin_host = $current_host;
		} elseif ( strcasecmp( $origin_host, (string) $current_host ) !== 0 ) {
			/**
			 * Cross-origin nested sitemap entry — refuse silently and
			 * drop it from the fetch list.
			 */
			return array();
		}

		$urls     = array( $w3tc_url );
		$response = Util_Http::get( $w3tc_url );

		if ( ! is_wp_error( $response ) && 200 === $response['response']['code'] ) {
			$url_matches     = null;
			$sitemap_matches = null;

			/**
			 * Parse the fetched sitemap XML with XXE protections. The body is
			 * fetched from an admin-configured (and origin-host-constrained)
			 * sitemap URL, so a hostile or compromised endpoint must not be
			 * able to use external entities to read local files or pivot to
			 * internal services. Util_Environment::safe_simplexml_load_string()
			 * also handles the libxml error-suppression dance.
			 */
			$xml = Util_Environment::safe_simplexml_load_string( $response['body'] );

			// Check if the XML load failed; return the URLs found so far (sitemap URL).
			if ( false === $xml ) {
				return $urls;
			}

			if ( $xml->getName() === 'sitemapindex' ) {
				foreach ( $xml->sitemap as $sitemap ) {
					if ( $sitemap->loc ) {
						$urls = array_merge(
							$urls,
							$this->parse_sitemap( (string) $sitemap->loc, $origin_host, $depth + 1 )
						);
					}
				}
			} elseif ( $xml->getName() === 'urlset' ) {
				$locs = array();

				foreach ( $xml->url as $w3tc_url ) {
					if ( $w3tc_url->loc ) {
						$priority                        = isset( $w3tc_url->priority ) ? (float) $w3tc_url->priority : 0.5;
						$locs[ (string) $w3tc_url->loc ] = $priority;
					}
				}

				arsort( $locs );

				$urls = array_merge( $urls, array_keys( $locs ) );
			} elseif ( $xml->getName() === 'rss' ) {
				foreach ( $xml->channel->item as $w3tc_item ) {
					if ( $w3tc_item->link ) {
						$urls[] = (string) $w3tc_item->link;
					}
				}
			}
		}

		return $urls;
	}

	/**
	 * Primes the cache for a given post ID by making HTTP requests for each associated URL.
	 *
	 * @param int $post_id The post ID for which to prime the cache.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function prime_post( $post_id ) {
		$post_urls = Util_PageUrls::get_post_urls( $post_id );

		// Make HTTP requests and prime cache.
		foreach ( $post_urls as $w3tc_url ) {
			$w3tc_result = Util_Http::get( $w3tc_url, array( 'user-agent' => 'WordPress' ) );
			if ( is_wp_error( $w3tc_result ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Saves configuration options and triggers actions based on changes.
	 *
	 * @param array $w3tc_data The configuration data containing both new and old configurations.
	 *
	 * @return array The updated configuration data.
	 */
	public function w3tc_save_options( $w3tc_data ) {
		$new_config = $w3tc_data['new_config'];
		$old_config = $w3tc_data['old_config'];

		if ( $new_config->get_boolean( 'pgcache.cache.feed' ) ) {
			$new_config->set( 'pgcache.cache.nginx_handle_xml', true );
		}

		if (
			(
				! $new_config->get_boolean( 'pgcache.cache.home' ) &&
				$old_config->get_boolean( 'pgcache.cache.home' )
			) ||
			(
				$new_config->get_boolean( 'pgcache.reject.front_page' ) &&
				! $old_config->get_boolean( 'pgcache.reject.front_page' )
			) ||
			(
				! $new_config->get_boolean( 'pgcache.cache.feed' ) &&
				$old_config->get_boolean( 'pgcache.cache.feed' )
			) ||
			(
				! $new_config->get_boolean( 'pgcache.cache.query' ) &&
				$old_config->get_boolean( 'pgcache.cache.query' )
			) ||
			(
				! $new_config->get_boolean( 'pgcache.cache.ssl' ) &&
				$old_config->get_boolean( 'pgcache.cache.ssl' )
			)
		) {
			$state = Dispatcher::config_state();
			$state->set( 'common.show_note.flush_posts_needed', true );
			$state->save();
		}

		// Schedule purge if enabled.
		if ( $new_config->get_boolean( 'pgcache.enabled' ) && $new_config->get_boolean( 'pgcache.wp_cron' ) ) {
			$new_wp_cron_time      = $new_config->get_integer( 'pgcache.wp_cron_time' );
			$old_wp_cron_time      = $old_config ? $old_config->get_integer( 'pgcache.wp_cron_time' ) : -1;
			$new_wp_cron_interval  = $new_config->get_string( 'pgcache.wp_cron_interval' );
			$old_wp_cron_interval  = $old_config ? $old_config->get_string( 'pgcache.wp_cron_interval' ) : -1;
			$schedule_needs_update = $new_wp_cron_time !== $old_wp_cron_time || $new_wp_cron_interval !== $old_wp_cron_interval;

			// Clear the scheduled hook if a change in time or interval is detected.
			if ( wp_next_scheduled( 'w3tc_pgcache_purge_wpcron' ) && $schedule_needs_update ) {
				wp_clear_scheduled_hook( 'w3tc_pgcache_purge_wpcron' );
			}

			// Schedule if no existing cron event or settings have changed.
			if ( ! wp_next_scheduled( 'w3tc_pgcache_purge_wpcron' ) || $schedule_needs_update ) {
				$scheduled_timestamp_server = Util_Environment::get_cron_schedule_time( $new_wp_cron_time );
				wp_schedule_event( $scheduled_timestamp_server, $new_wp_cron_interval, 'w3tc_pgcache_purge_wpcron' );
			}
		} elseif ( wp_next_scheduled( 'w3tc_pgcache_purge_wpcron' ) ) {
			wp_clear_scheduled_hook( 'w3tc_pgcache_purge_wpcron' );
		}

		return $w3tc_data;
	}

	/**
	 * Checks and reports any errors related to the page cache engine (e.g., Memcached).
	 *
	 * @param array $errors The array of errors to append to.
	 *
	 * @return array The updated array of errors.
	 */
	public function w3tc_errors( $errors ) {
		$w3tc_c = Dispatcher::config();

		if ( 'memcached' === $w3tc_c->get_string( 'pgcache.engine' ) ) {
			$memcached_servers         = $w3tc_c->get_array( 'pgcache.memcached.servers' );
			$memcached_binary_protocol = $w3tc_c->get_boolean( 'pgcache.memcached.binary_protocol' );
			$memcached_username        = $w3tc_c->get_string( 'pgcache.memcached.username' );
			$memcached_password        = $w3tc_c->get_string( 'pgcache.memcached.password' );

			if (
				! Util_Installed::is_memcache_available(
					$memcached_servers,
					$memcached_binary_protocol,
					$memcached_username,
					$memcached_password
				)
			) {
				if ( ! isset( $errors['memcache_not_responding.details'] ) ) {
					$errors['memcache_not_responding.details'] = array();
				}

				$errors['memcache_not_responding.details'][] = sprintf(
					// Translators: 1 memcached servers.
					__(
						'Page Cache: %1$s.',
						'w3-total-cache'
					),
					implode( ', ', $memcached_servers )
				);
			}
		}

		return $errors;
	}

	/**
	 * Summarizes usage statistics from historical data, including cache size and request hits.
	 *
	 * @param array $summary The summary data to update.
	 * @param array $history The historical usage data to process.
	 *
	 * @return array The updated summary with additional page cache statistics.
	 */
	public function w3tc_usage_statistics_summary_from_history( $summary, $history ) {
		// total size.
		$g         = Dispatcher::component( 'PgCache_ContentGrabber' );
		$pagecache = array();

		$e                        = $this->_config->get_string( 'pgcache.engine' );
		$pagecache['engine_name'] = Cache::engine_name( $e );
		$file_generic             = ( 'file_generic' === $e );

		// build metrics in php block.
		if ( ! isset( $summary['php'] ) ) {
			$summary['php'] = array();
		}

		Util_UsageStatistics::sum_by_prefix_positive( $summary['php'], $history, 'php_requests_pagecache' );

		// need to return cache size.
		if ( $file_generic ) {
			list( $v, $should_count ) = Util_UsageStatistics::get_or_init_size_transient(
				'w3tc_ustats_pagecache_size',
				$summary
			);

			if ( $should_count ) {
				$size           = $g->get_cache_stats_size( $summary['timeout_time'] );
				$v['size_used'] = Util_UsageStatistics::bytes_to_size2( $size, 'bytes' );
				if ( isset( $size['timeout_occurred'] ) && $size['timeout_occurred'] ) {
					$v['items'] = Util_UsageStatistics::integer2( $size, 'items' ) . ' (partial)';
				} else {
					$items_count = isset( $size['items'] ) ? (int) $size['items'] : 0;
					$v['items']  = Util_UsageStatistics::integer2( $size, 'items' );
				}

				set_transient( 'w3tc_ustats_pagecache_size', $v, 55 );
			} elseif ( isset( $v['items'] ) && '...counting' === $v['items'] ) {
				// If still counting, try to get a fresh count.
				$size           = $g->get_cache_stats_size( $summary['timeout_time'] );
				$v['size_used'] = Util_UsageStatistics::bytes_to_size2( $size, 'bytes' );
				if ( isset( $size['timeout_occurred'] ) && $size['timeout_occurred'] ) {
					$v['items'] = Util_UsageStatistics::integer2( $size, 'items' ) . ' (partial)';
				} else {
					$v['items'] = Util_UsageStatistics::integer2( $size, 'items' );
				}
				set_transient( 'w3tc_ustats_pagecache_size', $v, 55 );
			}

			if ( isset( $v['size_used'] ) ) {
				$pagecache['size_used'] = $v['size_used'];
				$pagecache['items']     = $v['items'];
			}

			if ( isset( $summary['access_log'] ) ) {
				$php_requests_v            = isset( $summary['php']['php_requests_v'] ) ? $summary['php']['php_requests_v'] : 0;
				$pagecache['requests']     = $summary['access_log']['dynamic_requests_total_v'];
				$pagecache['requests_hit'] = $pagecache['requests'] - $php_requests_v;
				if ( $pagecache['requests_hit'] < 0 ) {
					$pagecache['requests_hit'] = 0;
				}
			}
		} else {
			// all request counts data available.
			$pagecache['requests']     = isset( $summary['php']['php_requests_v'] ) ? $summary['php']['php_requests_v'] : 0;
			$pagecache['requests_hit'] = isset( $summary['php']['php_requests_pagecache_hit'] ) ? $summary['php']['php_requests_pagecache_hit'] : 0;

			$requests_time_ms = Util_UsageStatistics::sum( $history, 'pagecache_requests_time_10ms' ) * 10;
			$php_requests     = Util_UsageStatistics::sum( $history, 'php_requests' );

			if ( $php_requests > 0 ) {
				$pagecache['request_time_ms'] = Util_UsageStatistics::integer( $requests_time_ms / $php_requests );
			}
		}

		if ( 'memcached' === $e ) {
			$pagecache['size_percent'] = $summary['memcached']['size_percent'];
		}

		if ( isset( $pagecache['requests_hit'] ) ) {
			$pagecache['requests_hit_rate'] = Util_UsageStatistics::percent(
				$pagecache['requests_hit'],
				$pagecache['requests']
			);
		}

		if ( ! isset( $summary['php']['php_requests_pagecache_hit'] ) ) {
			$summary['php']['php_requests_pagecache_hit'] = 0;
		}

		if ( isset( $summary['php']['php_requests_v'] ) ) {
			$v = $summary['php']['php_requests_v'] - $summary['php']['php_requests_pagecache_hit'];
			if ( $v < 0 ) {
				$v = 0;
			}

			$summary['php']['php_requests_pagecache_miss'] = $v;
		}

		if ( isset( $pagecache['requests'] ) ) {
			$pagecache['requests_per_second'] = Util_UsageStatistics::value_per_period_seconds(
				$pagecache['requests'],
				$summary
			);
		}

		$summary['pagecache'] = $pagecache;

		return $summary;
	}
}
