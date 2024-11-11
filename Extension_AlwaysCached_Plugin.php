<?php
/**
 * File: Extension_AlwaysCached_Plugin.php
 *
 * AlwaysCached plugin admin controller.
 *
 * @since 2.8.0
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * AlwaysCached Plugin.
 *
 * @since 2.8.0
 */
class Extension_AlwaysCached_Plugin {
	/**
	 * Ahead generation extension data.
	 *
	 * @var array
	 */
	private $request_queue_item_extension = null;

	/**
	 * Run method for AlwaysCached.
	 *
	 * @since 2.8.0
	 *
	 * @return void|null
	 */
	public function run() {
		if ( ! self::is_enabled() ) {
			return null;
		}

		add_action( 'init', array( $this, 'init' ) );
		add_filter( 'w3tc_admin_bar_menu', array( $this, 'w3tc_admin_bar_menu' ) );
		add_action( 'w3tc_pagecache_before_set', array( $this, 'w3tc_pagecache_before_set' ) );
		add_filter( 'w3tc_pagecache_set', array( $this, 'w3tc_pagecache_set' ) );
		add_filter( 'w3tc_pagecache_flush_url', array( $this, 'w3tc_pagecache_flush_url' ), 1000 );
		add_filter( 'w3tc_pagecache_flush_all_groups', array( $this, 'w3tc_pagecache_flush_all_groups' ), 1000 );
		add_filter( 'w3tc_pagecache_rules_apache_rewrite_cond', array( $this, 'w3tc_pagecache_rules_apache_rewrite_cond' ) );

		// Cron job.
		add_action( 'w3tc_alwayscached_wp_cron', array( $this, 'w3tc_alwayscached_wp_cron' ) );

		/**
		 * This filter is documented in Generic_AdminActions_Default.php under the read_request method.
		*/
		add_filter( 'w3tc_config_key_descriptor', array( $this, 'w3tc_config_key_descriptor' ), 10, 2 );
	}

	/**
	 * Init for AlwaysCached.
	 *
	 * @since 2.8.0
	 *
	 * @return void
	 */
	public function init() {
		$c = Dispatcher::config();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST['w3tc_alwayscached'] ) ) {
			Extension_AlwaysCached_Worker::run();
			wp_die();
		}

		$enabled  = $c->get_boolean( array( 'alwayscached', 'wp_cron' ) );
		$time     = $c->get_string( array( 'alwayscached', 'wp_cron_time' ) );
		$interval = $c->get_string( array( 'alwayscached', 'wp_cron_interval' ) );

		// Retrieve stored previous time and interval.
		$prev_time     = get_option( 'w3tc_alwayscached_wp_cron_time', '' );
		$prev_interval = get_option( 'w3tc_alwayscached_wp_cron_interval', '' );

		// Check if cron needs updating or scheduling.
		if ( $enabled && ! empty( $time ) && ! empty( $interval ) ) {
			// If no event is scheduled or the time/interval have changed, update the cron.
			if ( ! wp_next_scheduled( 'w3tc_alwayscached_wp_cron' ) || $time !== $prev_time || $interval !== $prev_interval ) {
				// Clear existing scheduled event.
				wp_clear_scheduled_hook( 'w3tc_alwayscached_wp_cron' );

				// Convert the time to a timestamp for scheduling.
				$start_time = Util_Environment::get_cron_schedule_time( $time );

				// Schedule the new event.
				wp_schedule_event( $start_time, $interval, 'w3tc_alwayscached_wp_cron' );

				// Store the new time and interval.
				update_option( 'w3tc_alwayscached_wp_cron_time', $time );
				update_option( 'w3tc_alwayscached_wp_cron_interval', $interval );
			}
		} elseif ( ! $enabled ) {
			// Clear the cron job if it's disabled.
			wp_clear_scheduled_hook( 'w3tc_alwayscached_wp_cron' );

			// Remove the stored values.
			delete_option( 'w3tc_alwayscached_wp_cron_time' );
			delete_option( 'w3tc_alwayscached_wp_cron_interval' );
		}
	}

	/**
	 * Adds admin bar menu links.
	 *
	 * @since 2.8.0
	 *
	 * @param array $menu_items Menu items.
	 *
	 * @return array
	 */
	public function w3tc_admin_bar_menu( $menu_items ) {
		if ( ! is_admin() ) {
			$menu_items['10025.always_cached'] = array(
				'id'     => 'w3tc_flush_current_page',
				'parent' => 'w3tc',
				'title'  => __( 'Regenerate Current Page', 'w3-total-cache' ),
				'href'   => wp_nonce_url(
					admin_url( 'admin.php?page=w3tc_dashboard&amp;w3tc_alwayscached_regenerate&amp;post_id=' . Util_Environment::detect_post_id() ),
					'w3tc'
				),
			);
		}

		return $menu_items;
	}

	/**
	 * Adds AlwaysCached Apache rules.
	 *
	 * @since 2.8.0
	 *
	 * @param string $rewrite_conditions Apache rules buffer.
	 *
	 * @return string
	 */
	public function w3tc_pagecache_rules_apache_rewrite_cond( $rewrite_conditions ) {
		$rewrite_conditions .= "    RewriteCond %{HTTP:w3tcalwayscached} =\"\"\n";
		return $rewrite_conditions;
	}

	/**
	 * ???
	 *
	 * @since 2.8.0
	 *
	 * @param array $o Page data.
	 *
	 * @return void
	 */
	public function w3tc_pagecache_before_set( $o ) {
		if ( empty( $o['page_key_extension']['alwayscached'] ) ) {
			return;
		}

		$url = ( empty( $o['page_key_extension']['encryption'] ) ? 'http://' : 'https://' ) .
			$o['request_url_fragments']['host'] .
			$o['request_url_fragments']['path'] .
			$o['request_url_fragments']['querystring'];

		$queue_item = Extension_AlwaysCached_Queue::get_by_url( $url );

		if ( ! empty( $queue_item ) ) {
			$this->request_queue_item_extension = @unserialize( $queue_item['extension'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
			header( 'w3tcalwayscached: ' . ( empty( $queue_item ) ? 'none' : $queue_item['key'] ) );
		}
	}

	/**
	 * ???
	 *
	 * @since 2.8.0
	 *
	 * @param array $data Page data.
	 *
	 * @return array
	 */
	public function w3tc_pagecache_set( $data ) {
		// in a case of alwayscached-regeneration request - apply cache's "ahead generation extension" data.
		if ( ! empty( $this->request_queue_item_extension ) ) {
			$keys_to_store = array( 'key_version', 'key_version_at_creation' );
			foreach ( $keys_to_store as $k ) {
				if ( isset( $this->request_queue_item_extension[ $k ] ) ) {
					$data[ $k ] = $this->request_queue_item_extension[ $k ];
				}
			}
		}

		return $data;
	}

	/**
	 * Flush URL.
	 *
	 * Data format expected:
	 * array(
	 *  'url' =>
	 *  'cache' =>
	 *  'mobile_groups' =>
	 *  'referrer_groups' =>
	 *  'cookies' =>
	 *  'encryptions' =>
	 *  'compressions' =>
	 *  'group' =>
	 *  'parent' => object with _get_page_key method
	 * )
	 *
	 * @since 2.8.0
	 *
	 * @param array $data Data for flush request.
	 *
	 * @return array
	 */
	public function w3tc_pagecache_flush_url( $data ) {
		// no support for mobile_groups, referrer_groups, cookies, group atm.
		foreach ( $data['encryptions'] as $encryption ) {
			$page_key_extension = array(
				'useragent'   => $data['mobile_groups'][0],
				'referrer'    => $data['referrer_groups'][0],
				'cookie'      => $data['cookies'][0],
				'encryption'  => $encryption,
				'compression' => false,
				'group'       => $data['group'],
			);

			$page_key = $data['parent']->_get_page_key( $page_key_extension, $data['url'] );

			// If the URL is excluded, store the data for later flushing.
			if ( self::is_excluded( $data['url'] ) ) {
				$excluded_data = $data;
				continue;
			}

			// If cache key doesn't exist, skip to the next iteration.
			if ( ! $data['cache']->exists( $page_key, $data['group'] ) ) {
				continue;
			}

			// Queue the URL for later processing if it's not excluded and exists in cache.
			Extension_AlwaysCached_Queue::add(
				$data['url'],
				array( 'group' => $data['group'] )
			);
		}

		// Return the excluded URLs if any were found, so they can be flushed.
		if ( ! empty( $excluded_data ) ) {
			return $excluded_data;
		}

		return array();
	}

	/**
	 * Flush all groups.
	 *
	 * @since 2.8.0
	 *
	 * @param array $groups Groups.
	 *
	 * @return array
	 */
	public function w3tc_pagecache_flush_all_groups( $groups ) {
		$c             = Dispatcher::config();
		$excluded_data = array();

		// Flush all action will purge the queue as any queued changes will now be live.
		if ( ! $c->get_boolean( array( 'alwayscached', 'flush_all' ) ) ) {
			Extension_AlwaysCached_Queue::empty();
			return $groups;
		}

		if ( in_array( '', $groups, true ) && $c->get_boolean( array( 'alwayscached', 'flush_all' ) ) ) {
			$o             = Dispatcher::component( 'PgCache_Flush' );
			$extension     = $o->get_ahead_generation_extension( '' );
			$no_cache_vals = array( 'no-cache', 'no-store', 'must-revalidate', 'private' );

			if ( $c->get_boolean( array( 'alwayscached', 'flush_all_home' ) ) ) {
				$home_url         = rtrim( home_url(), '/' ) . '/';
				$response_headers = wp_remote_head( $home_url );

				if ( ! is_wp_error( $response_headers ) ) {
					$cache_control_vals = array_map( 'trim', explode( ',', wp_remote_retrieve_header( $response_headers, 'Cache-Control' ) ) );
					if ( ! self::is_excluded( $home_url ) && ! array_intersect( $no_cache_vals, $cache_control_vals ) ) {
						Extension_AlwaysCached_Queue::add( $home_url, $extension );
					} else {
						$o->flush_url( $home_url );
					}
				}
			}

			$posts_count = $c->get_integer( array( 'alwayscached', 'flush_all_posts_count' ) ) ?? 15;
			if ( $posts_count > 0 ) {
				$posts = get_posts(
					array(
						'post_type'      => 'post',
						'post_status'    => 'publish',
						'posts_per_page' => $posts_count,
						'order'          => 'DESC',
						'orderby'        => 'modified',
					)
				);
				foreach ( $posts as $post ) {
					$permalink        = get_permalink( $post );
					$response_headers = wp_remote_head( $permalink );

					if ( is_wp_error( $response_headers ) ) {
						continue;
					}

					$cache_control_vals = array_map( 'trim', explode( ',', wp_remote_retrieve_header( $response_headers, 'Cache-Control' ) ) );
					if ( array_intersect( $no_cache_vals, $cache_control_vals ) ) {
						continue;
					}

					if ( ! self::is_excluded( $permalink ) ) {
						Extension_AlwaysCached_Queue::add( $permalink, $extension );
					} else {
						$o->flush_url( $permalink );
					}
				}
			}

			$pages_count = $c->get_integer( array( 'alwayscached', 'flush_all_pages_count' ) ) ?? 15;
			if ( $pages_count > 0 ) {
				$posts = get_posts(
					array(
						'post_type'      => 'page',
						'post_status'    => 'publish',
						'posts_per_page' => $pages_count,
						'order'          => 'DESC',
						'orderby'        => 'modified',
					)
				);
				foreach ( $posts as $post ) {
					$permalink        = get_permalink( $post );
					$response_headers = wp_remote_head( $permalink );

					if ( is_wp_error( $response_headers ) ) {
						continue;
					}

					$cache_control_vals = array_map( 'trim', explode( ',', wp_remote_retrieve_header( $response_headers, 'Cache-Control' ) ) );
					if ( array_intersect( $no_cache_vals, $cache_control_vals ) ) {
						continue;
					}

					if ( ! self::is_excluded( $permalink ) ) {
						Extension_AlwaysCached_Queue::add( $permalink, $extension );
					} else {
						$o->flush_url( $permalink );
					}
				}
			}
		}

		return array();
	}

	/**
	 * Gets the enabled status of the extension.
	 *
	 * @since 2.8.0
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$config            = Dispatcher::config();
		$extensions_active = $config->get_array( 'extensions.active' );
		return Util_Environment::is_w3tc_pro( $config ) && array_key_exists( 'alwayscached', $extensions_active );
	}

	/**
	 * Cron job for processing queue via WP cron.
	 *
	 * @since 2.8.0
	 *
	 * @return void
	 */
	public function w3tc_alwayscached_wp_cron() {
		Extension_AlwaysCached_Worker::run();
	}

	/**
	 * Specify config key typing for fields that need it.
	 *
	 * @since 2.8.0
	 *
	 * @param mixed $descriptor Descriptor.
	 * @param mixed $key Compound key array.
	 *
	 * @return array
	 */
	public function w3tc_config_key_descriptor( $descriptor, $key ) {
		if ( is_array( $key ) && 'alwayscached.exclusions' === implode( '.', $key ) ) {
			$descriptor = array( 'type' => 'array' );
		}

		return $descriptor;
	}

	/**
	 * Checks if the given URL matches any exclusions.
	 *
	 * @since 2.8.0
	 *
	 * @param string $url URL.
	 *
	 * @return bool
	 */
	private function is_excluded( $url ) {
		$c          = Dispatcher::config();
		$exclusions = $c->get_array( array( 'alwayscached', 'exclusions' ) );

		// Normalize the URL to handle trailing slashes and parse the path.
		$parsed_url     = rtrim( wp_parse_url( $url, PHP_URL_PATH ), '/' );
		$url_with_slash = $parsed_url . '/';

		foreach ( $exclusions as $exclusion ) {
			// Check both with and without trailing slash.
			if ( fnmatch( $exclusion, $parsed_url ) || fnmatch( $exclusion, $url_with_slash ) ) {
				return true;
			}
		}

		return false;
	}
}

$p = new Extension_AlwaysCached_Plugin();
$p->run();

if ( is_admin() ) {
	$p = new Extension_AlwaysCached_Plugin_Admin();
	$p->run();
}
