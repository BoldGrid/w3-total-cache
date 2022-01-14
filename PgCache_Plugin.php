<?php
namespace W3TC;

/**
 * W3 PgCache plugin
 */
class PgCache_Plugin {
	/**
	 * Config
	 */
	private $_config = null;

	function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Runs plugin
	 */
	function run() {
		add_action( 'w3tc_flush_all',
			array( $this, 'w3tc_flush_posts' ),
			1100, 1 );
		add_action( 'w3tc_flush_group',
			array( $this, 'w3tc_flush_group' ),
			1100, 2 );
		add_action( 'w3tc_flush_post',
			array( $this, 'w3tc_flush_post' ),
			1100, 1 );
		add_action( 'w3tc_flushable_posts',
			'__return_true',
			1100 );
		add_action( 'w3tc_flush_posts',
			array( $this, 'w3tc_flush_posts' ),
			1100 );
		add_action( 'w3tc_flush_url',
			array( $this, 'w3tc_flush_url' ),
			1100, 1 );

		add_filter( 'w3tc_pagecache_set_header',
			array( $this, 'w3tc_pagecache_set_header' ), 10, 3 );
		add_filter( 'w3tc_admin_bar_menu',
			array( $this, 'w3tc_admin_bar_menu' ) );

		add_filter( 'cron_schedules',
			array( $this, 'cron_schedules' ) );

		add_action( 'w3tc_config_save',
			array( $this, 'w3tc_config_save' ),
			10, 1 );

		$o = Dispatcher::component( 'PgCache_ContentGrabber' );

		add_filter( 'w3tc_footer_comment',
			array( $o, 'w3tc_footer_comment' ) );

		add_action( 'w3tc_usage_statistics_of_request',
			array( $o, 'w3tc_usage_statistics_of_request' ),
			10, 1 );
		add_filter( 'w3tc_usage_statistics_metrics',
			array( $this, 'w3tc_usage_statistics_metrics' ) );
		add_filter( 'w3tc_usage_statistics_sources', array(
				$this, 'w3tc_usage_statistics_sources' ) );


		if ( $this->_config->get_string( 'pgcache.engine' ) == 'file' ||
			$this->_config->get_string( 'pgcache.engine' ) == 'file_generic' ) {
			add_action( 'w3_pgcache_cleanup',
				array( $this, 'cleanup' ) );
		}

		add_action( 'w3_pgcache_prime', array( $this, 'prime' ) );

		Util_AttachToActions::flush_posts_on_actions();

		add_filter( 'comment_cookie_lifetime',
			array( $this, 'comment_cookie_lifetime' ) );

		if ( $this->_config->get_string( 'pgcache.engine' ) == 'file_generic' ) {
			add_action( 'wp_logout',
				array( $this, 'on_logout' ),
				0 );

			add_action( 'wp_login',
				array( $this, 'on_login' ),
				0 );
		}

		if ( $this->_config->get_boolean( 'pgcache.prime.post.enabled', false ) ) {
			add_action( 'publish_post',
				array( $this, 'prime_post' ),
				30 );
		}

		if ( ( $this->_config->get_boolean( 'pgcache.late_init' ) ||
				$this->_config->get_boolean( 'pgcache.late_caching' ) ) &&
			!is_admin() ) {
			$o = Dispatcher::component( 'PgCache_ContentGrabber' );
			add_action( 'init',
				array( $o, 'delayed_cache_print' ),
				99999 );
		}

		if ( !$this->_config->get_boolean( 'pgcache.mirrors.enabled' ) &&
			!Util_Environment::is_wpmu_subdomain() ) {
			add_action( 'init',
				array( $this, 'redirect_on_foreign_domain' ) );
		}
		if ( $this->_config->get_string( 'pgcache.rest' ) == 'disable' ) {
			// remove XMLRPC edit link
			remove_action( 'xmlrpc_rsd_apis', 'rest_output_rsd' );
			// remove wp-json in <head>
			remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
			// remove HTTP Header
			remove_action( 'template_redirect', 'rest_output_link_header', 11 );

			add_filter( 'rest_authentication_errors',
				array( $this, 'rest_authentication_errors' ),
				100 );
		}
	}



	public function rest_authentication_errors( $result ) {
		$error_message = __( 'REST API disabled.', 'w3-total-cache' );

		return new \WP_Error( 'rest_disabled', $error_message, array( 'status' => rest_authorization_required_code() ) );
	}



	/**
	 * Does disk cache cleanup
	 *
	 * @return void
	 */
	function cleanup() {
		$this->_get_admin()->cleanup();
	}

	/**
	 * Prime cache
	 *
	 * @return void
	 */
	function prime() {
		$this->_get_admin()->prime();
	}

	/**
	 * Instantiates worker on demand
	 */
	private function _get_admin() {
		return Dispatcher::component( 'PgCache_Plugin_Admin' );
	}

	/**
	 * Cron schedules filter
	 *
	 * @param array   $schedules
	 * @return array
	 */
	function cron_schedules( $schedules ) {
		$c = $this->_config;

		if ( $c->get_boolean( 'pgcache.enabled' ) &&
			( $c->get_string( 'pgcache.engine' ) == 'file' ||
				$c->get_string( 'pgcache.engine' ) == 'file_generic' ) ) {
			$v = $c->get_integer( 'pgcache.file.gc' );
			$schedules['w3_pgcache_cleanup'] = array(
				'interval' => $v,
				'display' => sprintf( '[W3TC] Page Cache file GC (every %d seconds)',
					$v )
			);
		}

		if ( $c->get_boolean( 'pgcache.enabled' ) &&
			$c->get_boolean( 'pgcache.prime.enabled' ) ) {
			$v = $c->get_integer( 'pgcache.prime.interval' );
			$schedules['w3_pgcache_prime'] = array(
				'interval' => $v,
				'display' => sprintf( '[W3TC] Page Cache prime (every %d seconds)',
					$v )
			);
		}

		return $schedules;
	}

	public function redirect_on_foreign_domain() {
		$request_host = Util_Environment::host();
		// host not known, potentially we are in console mode not http request
		if ( empty( $request_host ) || defined( 'WP_CLI' ) && WP_CLI )
			return;

		$home_url = get_home_url();
		$parsed_url = @parse_url( $home_url );

		if ( isset( $parsed_url['host'] ) &&
			strtolower( $parsed_url['host'] ) != strtolower( $request_host ) ) {
			$redirect_url = $parsed_url['scheme'] . '://';
			if ( !empty( $parsed_url['user'] ) ) {
				$redirect_url .= $parsed_url['user'];
				if ( !empty( $parsed_url['pass'] ) )
					$redirect_url .= ':' . $parsed_url['pass'];
			}
			if ( !empty( $parsed_url['host'] ) )
				$redirect_url .= $parsed_url['host'];

			if ( !empty( $parsed_url['port'] ) && $parsed_url['port'] != 80 ) {
				$redirect_url .= ':' . (int)$parsed_url['port'];
			}

			$redirect_url .= $_SERVER['REQUEST_URI'];

			//echo $redirect_url;
			wp_redirect( $redirect_url, 301 );
			exit();
		}
	}

	function comment_cookie_lifetime( $lifetime ) {
		$l = $this->_config->get_integer( 'pgcache.comment_cookie_ttl' );
		if ( $l != -1 )
			return $l;
		else
			return $lifetime;
	}

	/**
	 * Add cookie on logout to circumvent pagecache due to browser cache resulting in 304s
	 */
	function on_logout() {
		setcookie( 'w3tc_logged_out' );
	}

	/**
	 * Remove logout cookie on logins
	 */
	function on_login() {
		if ( isset( $_COOKIE['w3tc_logged_out'] ) )
			setcookie( 'w3tc_logged_out', '', 1 );
	}

	/**
	 *
	 *
	 * @param unknown $post_id
	 * @return boolean
	 */
	function prime_post( $post_id ) {
		$w3_pgcache = Dispatcher::component( 'CacheFlush' );
		return $w3_pgcache->prime_post( $post_id );
	}

	public function w3tc_usage_statistics_metrics( $metrics ) {
		return array_merge( $metrics, array(
				'php_requests_pagecache_hit',
				'php_requests_pagecache_miss_404',
				'php_requests_pagecache_miss_ajax',
				'php_requests_pagecache_miss_api_call',
				'php_requests_pagecache_miss_configuration',
				'php_requests_pagecache_miss_fill',
				'php_requests_pagecache_miss_logged_in',
				'php_requests_pagecache_miss_mfunc',
				'php_requests_pagecache_miss_query_string',
				'php_requests_pagecache_miss_third_party',
				'php_requests_pagecache_miss_wp_admin',
				'pagecache_requests_time_10ms' ) );
	}

	public function w3tc_usage_statistics_sources( $sources ) {
		$c = Dispatcher::config();
		if ( $c->get_string( 'pgcache.engine' ) == 'apc' ) {
			$sources['apc_servers']['pgcache'] = array(
				'name' => __( 'Page Cache', 'w3-total-cache' )
			);
		} elseif ( $c->get_string( 'pgcache.engine' ) == 'memcached' ) {
			$sources['memcached_servers']['pgcache'] = array(
				'servers' => $c->get_array( 'pgcache.memcached.servers' ),
				'username' => $c->get_string( 'pgcache.memcached.username' ),
				'password' => $c->get_string( 'pgcache.memcached.password' ),
				'binary_protocol' => $c->get_boolean( 'pgcache.memcached.binary_protocol' ),
				'name' => __( 'Page Cache', 'w3-total-cache' )
			);
		} elseif ( $c->get_string( 'pgcache.engine' ) == 'redis' ) {
			$sources['redis_servers']['pgcache'] = array(
				'servers' => $c->get_array( 'pgcache.redis.servers' ),
				'dbid' => $c->get_integer( 'pgcache.redis.dbid' ),
				'password' => $c->get_string( 'pgcache.redis.password' ),
				'name' => __( 'Page Cache', 'w3-total-cache' )
			);
		}

		return $sources;
	}

	public function w3tc_admin_bar_menu( $menu_items ) {
		$menu_items['20110.pagecache'] = array(
			'id' => 'w3tc_flush_pgcache',
			'parent' => 'w3tc_flush',
			'title' => __( 'Page Cache: All', 'w3-total-cache' ),
			'href' => wp_nonce_url( admin_url(
					'admin.php?page=w3tc_dashboard&amp;w3tc_flush_pgcache' ),
				'w3tc' )
		);

		if ( Util_Environment::detect_post_id() && ( !defined( 'DOING_AJAX' ) || !DOING_AJAX ) ) {
			$menu_items['20120.pagecache'] = array(
				'id' => 'w3tc_pgcache_flush_post',
				'parent' => 'w3tc_flush',
				'title' => __( 'Page Cache: Current Page', 'w3-total-cache' ),
				'href' => wp_nonce_url( admin_url(
						'admin.php?page=w3tc_dashboard&amp;w3tc_flush_post&amp;post_id=' .
						Util_Environment::detect_post_id() ), 'w3tc' )
			);
		}

		return $menu_items;
	}

	function w3tc_flush_group( $group, $extras = array() ) {
		if ( isset( $extras['only'] ) && $extras['only'] != 'pagecache' )
			return;

		$pgcacheflush = Dispatcher::component( 'PgCache_Flush' );
		$v = $pgcacheflush->flush_group( $group );

		return $v;
	}

	/**
	 * Flushes all caches
	 *
	 * @return boolean
	 */
	function w3tc_flush_posts( $extras = array() ) {
		if ( isset( $extras['only'] ) && $extras['only'] != 'pagecache' )
			return;

		$pgcacheflush = Dispatcher::component( 'PgCache_Flush' );
		$v = $pgcacheflush->flush();

		return $v;
	}

	/**
	 * Flushes post cache
	 *
	 * @param integer $post_id
	 * @return boolean
	 */
	function w3tc_flush_post( $post_id ) {
		$pgcacheflush = Dispatcher::component( 'PgCache_Flush' );
		$v = $pgcacheflush->flush_post( $post_id );

		return $v;
	}

	/**
	 * Flushes post cache
	 *
	 * @param string  $url
	 * @return boolean
	 */
	function w3tc_flush_url( $url ) {
		$pgcacheflush = Dispatcher::component( 'PgCache_Flush' );
		$v = $pgcacheflush->flush_url( $url );

		return $v;
	}



	/**
	 * By default headers are not cached by file_generic
	 */
	public function w3tc_pagecache_set_header( $header, $header_original,
			$pagecache_engine ) {
		if ( $pagecache_engine == 'file_generic' ) {
			return null;
		}

		return $header;
	}



	public function w3tc_config_save( $config ) {
		// frontend activity
		if ( $config->get_boolean( 'pgcache.cache.feed' ) ) {
			$config->set( 'pgcache.cache.nginx_handle_xml', true );
		}
	}

}
