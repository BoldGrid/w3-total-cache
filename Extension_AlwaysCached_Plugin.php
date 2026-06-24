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

defined( 'ABSPATH' ) || exit;
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
		$w3tc_c = Dispatcher::config();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST['w3tc_alwayscached'] ) ) {
			self::authorize_worker_trigger_or_die();
			Extension_AlwaysCached_Worker::run();
			wp_die();
		}

		$w3tc_enabled = $w3tc_c->get_boolean( array( 'alwayscached', 'wp_cron' ) );
		$time         = $w3tc_c->get_string( array( 'alwayscached', 'wp_cron_time' ) );
		$interval     = $w3tc_c->get_string( array( 'alwayscached', 'wp_cron_interval' ) );

		// Retrieve stored previous time and interval.
		$prev_time     = get_option( 'w3tc_alwayscached_wp_cron_time', '' );
		$prev_interval = get_option( 'w3tc_alwayscached_wp_cron_interval', '' );

		// Check if cron needs updating or scheduling.
		if ( $w3tc_enabled && ! empty( $time ) && ! empty( $interval ) ) {
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
		} elseif ( ! $w3tc_enabled ) {
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
			$current_page = Util_Request::get_string( 'page', 'w3tc_dashboard' );

			$menu_items['10025.always_cached'] = array(
				'id'     => 'w3tc_flush_current_page',
				'parent' => 'w3tc',
				'title'  => __( 'Regenerate Current Page', 'w3-total-cache' ),
				'href'   => Util_Nonce::admin_nonce_url(
					admin_url(
						'admin.php?page=' . $current_page .
						'&amp;w3tc_alwayscached_regenerate&amp;post_id=' . Util_Environment::detect_post_id()
					),
					'w3tc_alwayscached_regenerate'
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
	 * @param array $w3tc_o Page data.
	 *
	 * @return void
	 */
	public function w3tc_pagecache_before_set( $w3tc_o ) {
		if ( empty( $w3tc_o['page_key_extension']['alwayscached'] ) ) {
			return;
		}

		$w3tc_url = ( empty( $w3tc_o['page_key_extension']['encryption'] ) ? 'http://' : 'https://' ) .
			$w3tc_o['request_url_fragments']['host'] .
			$w3tc_o['request_url_fragments']['path'] .
			$w3tc_o['request_url_fragments']['querystring'];

		$queue_item = Extension_AlwaysCached_Queue::get_by_url( $w3tc_url );

		if ( ! empty( $queue_item ) ) {
			$decoded = @unserialize( $queue_item['extension'], array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize

			/**
			 * `allowed_classes => false` returns `__PHP_Incomplete_Class` for
			 * crafted object payloads; `w3tc_pagecache_set()` later does
			 * `isset( $this->request_queue_item_extension[ $k ] )`, which would
			 * fatal on a non-array. Coerce anything that isn't an array to an
			 * empty array so the downstream path stays well-typed.
			 */
			$this->request_queue_item_extension = is_array( $decoded ) ? $decoded : array();

			header( 'w3tcalwayscached: ' . ( empty( $queue_item ) ? 'none' : $queue_item['key'] ) );
		}
	}

	/**
	 * ???
	 *
	 * @since 2.8.0
	 *
	 * @param array $w3tc_data Page data.
	 *
	 * @return array
	 */
	public function w3tc_pagecache_set( $w3tc_data ) {
		// in a case of alwayscached-regeneration request - apply cache's "ahead generation extension" data.
		if ( ! empty( $this->request_queue_item_extension ) ) {
			$keys_to_store = array( 'key_version', 'key_version_at_creation' );
			foreach ( $keys_to_store as $k ) {
				if ( isset( $this->request_queue_item_extension[ $k ] ) ) {
					$w3tc_data[ $k ] = $this->request_queue_item_extension[ $k ];
				}
			}
		}

		return $w3tc_data;
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
	 * @param array $w3tc_data Data for flush request.
	 *
	 * @return array
	 */
	public function w3tc_pagecache_flush_url( $w3tc_data ) {
		// no support for mobile_groups, referrer_groups, cookies, group atm.
		foreach ( $w3tc_data['encryptions'] as $encryption ) {
			$page_key_extension = array(
				'useragent'   => $w3tc_data['mobile_groups'][0],
				'referrer'    => $w3tc_data['referrer_groups'][0],
				'cookie'      => $w3tc_data['cookies'][0],
				'encryption'  => $encryption,
				'compression' => false,
				'group'       => $w3tc_data['group'],
			);

			$page_key = $w3tc_data['parent']->_get_page_key( $page_key_extension, $w3tc_data['url'] );

			// If the URL is excluded, store the data for later flushing.
			if ( self::is_excluded( $w3tc_data['url'] ) ) {
				$excluded_data = $w3tc_data;
				continue;
			}

			// If cache key doesn't exist, skip to the next iteration.
			if ( ! $w3tc_data['cache']->exists( $page_key, $w3tc_data['group'] ) ) {
				continue;
			}

			// Queue the URL for later processing if it's not excluded and exists in cache.
			Extension_AlwaysCached_Queue::add(
				$w3tc_data['url'],
				array( 'group' => $w3tc_data['group'] )
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
		$w3tc_c        = Dispatcher::config();
		$excluded_data = array();

		// Flush all action will purge the queue as any queued changes will now be live.
		if ( ! $w3tc_c->get_boolean( array( 'alwayscached', 'flush_all' ) ) ) {
			Extension_AlwaysCached_Queue::empty();
			return $groups;
		}

		if ( in_array( '', $groups, true ) && $w3tc_c->get_boolean( array( 'alwayscached', 'flush_all' ) ) ) {
			$w3tc_o         = Dispatcher::component( 'PgCache_Flush' );
			$w3tc_extension = $w3tc_o->get_ahead_generation_extension( '' );
			$no_cache_vals  = array( 'no-cache', 'no-store', 'must-revalidate', 'private' );

			if ( $w3tc_c->get_boolean( array( 'alwayscached', 'flush_all_home' ) ) ) {
				$home_url         = rtrim( home_url(), '/' ) . '/';
				$response_headers = wp_remote_head( $home_url );

				if ( ! is_wp_error( $response_headers ) ) {
					$cache_control_vals = array_map( 'trim', explode( ',', wp_remote_retrieve_header( $response_headers, 'Cache-Control' ) ) );
					if ( ! self::is_excluded( $home_url ) && ! array_intersect( $no_cache_vals, $cache_control_vals ) ) {
						Extension_AlwaysCached_Queue::add( $home_url, $w3tc_extension );
					} else {
						$w3tc_o->flush_url( $home_url );
					}
				}
			}

			$posts_count = $w3tc_c->get_integer( array( 'alwayscached', 'flush_all_posts_count' ) ) ?? 15;
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
						Extension_AlwaysCached_Queue::add( $permalink, $w3tc_extension );
					} else {
						$w3tc_o->flush_url( $permalink );
					}
				}
			}

			$pages_count = $w3tc_c->get_integer( array( 'alwayscached', 'flush_all_pages_count' ) ) ?? 15;
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
						Extension_AlwaysCached_Queue::add( $permalink, $w3tc_extension );
					} else {
						$w3tc_o->flush_url( $permalink );
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
		$w3tc_config       = Dispatcher::config();
		$extensions_active = $w3tc_config->get_array( 'extensions.active' );
		return Util_Environment::is_w3tc_pro( $w3tc_config ) && array_key_exists( 'alwayscached', $extensions_active );
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
	 * Authorise the HTTP-side queue-worker trigger or terminate the
	 * request with 403.
	 *
	 * The `?w3tc_alwayscached` request parameter triggers the queue
	 * worker, which spends up to 60 seconds dequeueing items and
	 * issuing server-side HTTP fetches for each URL. Historically
	 * the param was unauthenticated, so any internet-reachable W3TC
	 * install with the extension enabled could be used to:
	 *
	 *   1. DoS the host (each request runs for ~60s of CPU + I/O).
	 *   2. SSRF an internal URL by enqueueing it through any other
	 *      flush-on-publish hook the attacker can reach, then
	 *      triggering the dequeue from outside.
	 *   3. Mutate the `w3tc_alwayscached_worker_timestamp` option
	 *      from unauthenticated context.
	 *
	 * Authorised callers:
	 *
	 *   - Logged-in administrators (manage_options): legitimate
	 *     browser-driven "process now" buttons. Matches the cap
	 *     used by the admin AJAX + admin POST paths for the same
	 *     worker.
	 *   - Operators using a pre-shared secret. To enable, put
	 *     `define( 'W3TC_WORKER_SECRET', 'long-random-string' );`
	 *     in wp-config.php and curl with
	 *     `Authorization: Bearer <secret>`. Constant-time compare
	 *     via `hash_equals`. Empty / unset constant disables this
	 *     path entirely — admins-only by default.
	 *
	 * The WP-Cron and WP-CLI paths (`w3tc_alwayscached_wp_cron`
	 * action and `Cli::alwayscached_process()`) call the worker
	 * directly and never reach this gate; those paths remain the
	 * recommended automation entry points.
	 *
	 * @since 2.10.0
	 *
	 * @return void
	 */
	private static function authorize_worker_trigger_or_die() {
		// Layer 1 — admin in a browser session.
		if ( \current_user_can( 'manage_options' ) ) {
			return;
		}

		/**
		 * Layer 2 — pre-shared secret via Authorization: Bearer.
		 * The constant is intentionally not exposed through the
		 * admin UI; it must be set in wp-config.php (or via a
		 * hosting platform's secret manager) so a config-mass-
		 * assignment cannot enable the unauth path from inside
		 * W3TC's own config store.
		 */
		if ( \defined( 'W3TC_WORKER_SECRET' ) && '' !== \W3TC_WORKER_SECRET ) {
			$presented = self::read_bearer_token();
			if ( '' !== $presented &&
				\hash_equals( (string) \W3TC_WORKER_SECRET, $presented )
			) {
				return;
			}
		}

		/**
		 * Unauthenticated: emit a short text body and stop. We do
		 * not echo any hint about which constant to set — the
		 * admin who configured the install knows.
		 */
		if ( ! \headers_sent() ) {
			\http_response_code( 403 );
			\header( 'Content-Type: text/plain; charset=utf-8' );
			\header( 'Cache-Control: no-store' );
			\header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
		}
		echo 'Forbidden';
		exit;
	}

	/**
	 * Extract the bearer token from the `Authorization` request
	 * header. Returns an empty string if absent or malformed.
	 *
	 * Apache exposes the header through `HTTP_AUTHORIZATION`;
	 * FastCGI / php-fpm typically expose it through
	 * `REDIRECT_HTTP_AUTHORIZATION` when an Apache rewrite has
	 * propagated it. PHP's `getallheaders()` is not available
	 * under all SAPIs, so we fall back through the well-known
	 * `$_SERVER` slots.
	 *
	 * @since 2.10.0
	 *
	 * @return string Bearer token value (without the `Bearer `
	 *                prefix), or empty string if not present.
	 */
	private static function read_bearer_token() {
		$header = '';

		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$header = (string) \wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		} elseif ( ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			$header = (string) \wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		} elseif ( \function_exists( 'getallheaders' ) ) {
			$all = \getallheaders();
			if ( is_array( $all ) ) {
				foreach ( $all as $k => $v ) {
					if ( 0 === \strcasecmp( (string) $k, 'authorization' ) ) {
						$header = (string) $v;
						break;
					}
				}
			}
		}

		if ( '' === $header ) {
			return '';
		}

		/**
		 * RFC 6750: `Authorization: Bearer <token>`. We tolerate
		 * any whitespace between scheme and token and reject any
		 * other auth scheme.
		 */
		if ( ! \preg_match( '/^\s*Bearer\s+(\S+)\s*$/i', $header, $m ) ) {
			return '';
		}

		return $m[1];
	}

	/**
	 * Specify config key typing for fields that need it.
	 *
	 * @since 2.8.0
	 *
	 * @param mixed $w3tc_descriptor Descriptor.
	 * @param mixed $w3tc_key Compound key array.
	 *
	 * @return array
	 */
	public function w3tc_config_key_descriptor( $w3tc_descriptor, $w3tc_key ) {
		if ( is_array( $w3tc_key ) && 'alwayscached.exclusions' === implode( '.', $w3tc_key ) ) {
			$w3tc_descriptor = array( 'type' => 'array' );
		}

		return $w3tc_descriptor;
	}

	/**
	 * Checks if the given URL matches any exclusions.
	 *
	 * @since 2.8.0
	 *
	 * @param string $w3tc_url URL.
	 *
	 * @return bool
	 */
	private function is_excluded( $w3tc_url ) {
		$w3tc_c     = Dispatcher::config();
		$exclusions = $w3tc_c->get_array( array( 'alwayscached', 'exclusions' ) );

		// Normalize the URL to handle trailing slashes and parse the path.
		$parsed_url     = rtrim( wp_parse_url( $w3tc_url, PHP_URL_PATH ), '/' );
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

$w3tc_p = new Extension_AlwaysCached_Plugin();
$w3tc_p->run();

if ( is_admin() ) {
	$w3tc_p = new Extension_AlwaysCached_Plugin_Admin();
	$w3tc_p->run();
}
