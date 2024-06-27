<?php
/**
 * File: Extension_AlwaysCached_Plugin.php
 *
 * AlwaysCached plugin admin controller.
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * AlwaysCached Plugin.
 *
 * @since X.X.X
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
	 * @since X.X.X
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
	}

	/**
	 * Init for AlwaysCached.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function init() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST['w3tc_alwayscached'] ) ) {
			Extension_AlwaysCached_Worker::run();
			exit();
		}
	}

	/**
	 * Adds admin bar menu links.
	 *
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
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
			$this->request_queue_item_extension = @unserialize( $queue_item['extension'] );
			header( 'w3tcalwayscached: ' . ( empty( $queue_item ) ? 'none' : $queue_item['key'] ) );
		}
	}

	/**
	 * ???
	 *
	 * @since X.X.X
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
	 * @since X.X.X
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

			if ( $data['cache']->exists( $page_key, $data['group'] ) ) {
				Extension_AlwaysCached_Queue::add(
					$data['url'],
					array( 'group' => $data['group'] )
				);
			}
		}

		return array();
	}

	/**
	 * Flush all groups.
	 *
	 * @since X.X.X
	 *
	 * @param array $groups Groups.
	 *
	 * @return array
	 */
	public function w3tc_pagecache_flush_all_groups( $groups ) {
		$c = Dispatcher::config();

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
					if ( ! array_intersect( $no_cache_vals, $cache_control_vals ) ) {
						Extension_AlwaysCached_Queue::add( $home_url, $extension );
					}
				}
			}

			$posts_count = $c->get_integer( array( 'alwayscached', 'flush_all_posts_count' ) );
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

					Extension_AlwaysCached_Queue::add( $permalink, $extension );
				}
			}

			$pages_count = $c->get_integer( array( 'alwayscached', 'flush_all_pages_count' ) );
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

					Extension_AlwaysCached_Queue::add( $permalink, $extension );
				}
			}

			$o->flush_group_after_ahead_generation(
				empty( $extension['group'] ) ? '' : $extension['group'],
				$extension
			);

			$groups = array_filter(
				$groups,
				function( $i ) {
					return ! empty( $i );
				}
			);
		}

		return $groups;
	}

	/**
	 * Gets the enabled status of the extension.
	 *
	 * @since X.X.X
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$config            = Dispatcher::config();
		$extensions_active = $config->get_array( 'extensions.active' );
		return Util_Environment::is_w3tc_pro( $config ) && array_key_exists( 'alwayscached', $extensions_active );
	}
}

$p = new Extension_AlwaysCached_Plugin();
$p->run();

if ( is_admin() ) {
	$p = new Extension_AlwaysCached_Plugin_Admin();
	$p->run();
}
