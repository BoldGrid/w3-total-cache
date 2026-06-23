<?php
/**
 * File: SetupGuide_Plugin_Admin.php
 *
 * @since 2.0.0
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: SetupGuide_Plugin_Admin
 *
 * @since 2.0.0
 */
class SetupGuide_Plugin_Admin {
	/**
	 * Current page.
	 *
	 * @since  2.0.0
	 * @access protected
	 *
	 * @var string
	 */
	protected $_page = 'w3tc_setup_guide'; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Wizard template.
	 *
	 * @var \W3TC\Wizard\Template
	 */
	private static $template;

	/**
	 * Per-AJAX-action nonce map.
	 *
	 * Replaces the previous shared `w3tc_wizard` nonce so a nonce minted for one
	 * wizard step cannot be replayed against a different step's handler.
	 *
	 * @since 2.10.0
	 *
	 * @var array
	 */
	private static $nonce_actions = array(
		'w3tc_wizard_skip'               => 'w3tc_wizard_skip',
		'w3tc_tos_choice'                => 'w3tc_wizard_tos_choice',
		'w3tc_get_pgcache_settings'      => 'w3tc_wizard_get_pgcache_settings',
		'w3tc_test_pgcache'              => 'w3tc_wizard_test_pgcache',
		'w3tc_config_pgcache'            => 'w3tc_wizard_config_pgcache',
		'w3tc_get_dbcache_settings'      => 'w3tc_wizard_get_dbcache_settings',
		'w3tc_test_dbcache'              => 'w3tc_wizard_test_dbcache',
		'w3tc_config_dbcache'            => 'w3tc_wizard_config_dbcache',
		'w3tc_get_objcache_settings'     => 'w3tc_wizard_get_objcache_settings',
		'w3tc_test_objcache'             => 'w3tc_wizard_test_objcache',
		'w3tc_config_objcache'           => 'w3tc_wizard_config_objcache',
		'w3tc_get_browsercache_settings' => 'w3tc_wizard_get_browsercache_settings',
		'w3tc_config_browsercache'       => 'w3tc_wizard_config_browsercache',
		'w3tc_get_imageservice_settings' => 'w3tc_wizard_get_imageservice_settings',
		'w3tc_config_imageservice'       => 'w3tc_wizard_config_imageservice',
		'w3tc_get_lazyload_settings'     => 'w3tc_wizard_get_lazyload_settings',
		'w3tc_config_lazyload'           => 'w3tc_wizard_config_lazyload',
	);

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {
		$page         = Util_Request::get_string( 'page' );
		$is_w3tc_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX &&
			! empty( Util_Request::get_string( 'action' ) )
			&& 0 === strpos( Util_Request::get_string( 'action' ), 'w3tc_' );

		if ( 'w3tc_setup_guide' === $page || $is_w3tc_ajax ) {
			require_once W3TC_INC_DIR . '/wizard/template.php';

			if ( is_null( self::$template ) ) {
				\add_action( 'init', array( $this, 'set_template' ), 10, 0 );
			}
		}
	}

	/**
	 * Set the template.
	 *
	 * @since 2.8.8
	 *
	 * @see self::get_config()
	 *
	 * @return void
	 */
	public function set_template() {
		/**
		 * Gate the SetupGuide AJAX/template registration to admins only.
		 * SetupGuide is admin-only by design; without this, wp_ajax_w3tc_*
		 * handlers would be registered for any logged-in user (subscriber+),
		 * exposing privileged config writes via wizard nonce alone.
		 */
		if ( ! \current_user_can( 'manage_options' ) ) {
			return;
		}

		self::$template = new Wizard\Template( $this->get_config() );
	}

	/**
	 * Resolve the per-action nonce key for the current AJAX action.
	 *
	 * Falls back to the legacy shared `w3tc_wizard` nonce so older cached
	 * wizard pages (still posting a shared nonce) don't break for admins
	 * mid-session.
	 *
	 * @since 2.10.0
	 *
	 * @param string $action The action key (e.g. `w3tc_config_pgcache`).
	 *
	 * @return string Nonce action name to verify against.
	 */
	private static function get_nonce_action( $action ) {
		if ( isset( self::$nonce_actions[ $action ] ) ) {
			return self::$nonce_actions[ $action ];
		}

		return 'w3tc_wizard';
	}

	/**
	 * Verify the request's nonce for a SetupGuide AJAX handler.
	 *
	 * Performs the inner capability check first (defense-in-depth) and then
	 * checks the per-action nonce. Sends a JSON error and dies on failure.
	 *
	 * @since 2.10.0
	 *
	 * @param string $action The action key (e.g. `w3tc_config_pgcache`).
	 *
	 * @return void
	 */
	private function verify_ajax_request( $action ) {
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( __( 'Insufficient permissions', 'w3-total-cache' ), 403 );
		}

		$primary  = self::get_nonce_action( $action );
		$provided = Util_Nonce::read_nonce( '_wpnonce' );

		/**
		 * Accept either the per-action nonce or the legacy shared `w3tc_wizard`
		 * nonce. The legacy fallback covers admins still on a cached wizard
		 * page rendered before this release; new requests always send the
		 * per-action nonce.
		 */
		if ( ! \wp_verify_nonce( $provided, $primary ) && ! \wp_verify_nonce( $provided, 'w3tc_wizard' ) ) {
			\wp_send_json_error( __( 'Security violation', 'w3-total-cache' ), 403 );
		}
	}

	/**
	 * Run.
	 *
	 * Needed by the Root_Loader.
	 *
	 * @since 2.0.0
	 */
	public function run() {
	}

	/**
	 * Display the setup guide.
	 *
	 * @since 2.0.0
	 *
	 * @see \W3TC\Wizard\Template::render()
	 */
	public function load() {
		/**
		 * Defense-in-depth: SetupGuide is admin-only. The Layer 1 gate in
		 * set_template() should keep self::$template null for non-admins, but
		 * short-circuit here as well so a future refactor can't reintroduce
		 * the bug.
		 */
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_die( \esc_html__( 'You do not have sufficient permissions to access this page.', 'w3-total-cache' ) );
		}

		if ( is_null( self::$template ) ) {
			return;
		}

		self::$template->render();
	}

	/**
	 * Admin-Ajax: Set option to skip the setup guide.
	 *
	 * @since 2.0.0
	 */
	public function skip() {
		$this->verify_ajax_request( 'w3tc_wizard_skip' );

		update_site_option( 'w3tc_setupguide_completed', time() );
		wp_send_json_success();
	}

	/**
	 * Admin-Ajax: Set the terms of service choice.
	 *
	 * @since 2.0.0
	 *
	 * @uses $_POST['choice'] TOS choice: accept/decline.
	 */
	public function set_tos_choice() {
		$this->verify_ajax_request( 'w3tc_tos_choice' );

		$choice          = Util_Request::get_string( 'choice' );
		$allowed_choices = array(
			'accept',
			'decline',
		);

		if ( in_array( $choice, $allowed_choices, true ) ) {
			$w3tc_config = new Config();

			if ( ! Util_Environment::is_w3tc_pro( $w3tc_config ) ) {
				$state_master = Dispatcher::config_state_master();
				$state_master->set( 'license.community_terms', $choice );
				$state_master->save();

				$w3tc_config->set( 'common.track_usage', ( 'accept' === $choice ) );
				$w3tc_config->save();
			}

			wp_send_json_success();
		} else {
			wp_send_json_error( __( 'Invalid choice', 'w3-total-cache' ), 400 );
		}
	}

	/**
	 * Abbreviate a URL for display in a small space.
	 *
	 * @since 2.0.0
	 *
	 * @param  string $w3tc_url URL.
	 * @return string
	 */
	public function abbreviate_url( $w3tc_url ) {
		$w3tc_url = untrailingslashit(
			str_replace(
				array(
					'https://',
					'http://',
					'www.',
				),
				'',
				$w3tc_url
			)
		);

		if ( strlen( $w3tc_url ) > 35 ) {
			$w3tc_url = substr( $w3tc_url, 0, 10 ) . '&hellip;' . substr( $w3tc_url, -20 );
		}

		return $w3tc_url;
	}

	/**
	 * Admin-Ajax: Test Page Cache.
	 *
	 * @since  2.0.0
	 *
	 * @see self::abbreviate_url()
	 * @see \W3TC\Util_Http::ttfb()
	 */
	public function test_pgcache() {
		$this->verify_ajax_request( 'w3tc_test_pgcache' );

		$nocache  = ! empty( Util_Request::get_string( 'nocache' ) );
		$w3tc_url = site_url();
		$results  = array(
			'nocache'  => $nocache,
			'url'      => $w3tc_url,
			'urlshort' => $this->abbreviate_url( $w3tc_url ),
			'ttfb'     => null,
		);

		if ( $nocache ) {
			$ttfb = Util_Http::ttfb( $w3tc_url, true );
			if ( false !== $ttfb ) {
				$results['ttfb'] = $ttfb;
			}
		} else {
			// Warm the cache once before the timed request.
			Util_Http::get( $w3tc_url, array( 'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) ) );

			$ttfb = Util_Http::ttfb( $w3tc_url, false );
			if ( false !== $ttfb ) {
				$results['ttfb'] = $ttfb;
			}

			$ttfb_uncached = Util_Http::ttfb( $w3tc_url, true );
			if ( false !== $ttfb_uncached && null === $results['ttfb'] ) {
				$results['ttfb'] = $ttfb_uncached;
			}
		}

		wp_send_json_success( $results );
	}

	/**
	 * Admin-Ajax: Get the page cache settings.
	 *
	 * @since  2.0.0
	 *
	 * @see \W3TC\Config::get_boolean()
	 * @see \W3TC\Config::get_string()
	 */
	public function get_pgcache_settings() {
		$this->verify_ajax_request( 'w3tc_get_pgcache_settings' );

		$w3tc_config = new Config();

		wp_send_json_success(
			array(
				'enabled' => $w3tc_config->get_boolean( 'pgcache.enabled' ),
				'engine'  => $w3tc_config->get_string( 'pgcache.engine' ),
			)
		);
	}

	/**
	 * Admin-Ajax: Configure the page cache settings.
	 *
	 * @since  2.0.0
	 *
	 * @see \W3TC\Config::get_boolean()
	 * @see \W3TC\Config::get_string()
	 * @see \W3TC\Util_Installed::$w3tc_engine()
	 * @see \W3TC\Config::set()
	 * @see \W3TC\Config::save()
	 * @see \W3TC\Dispatcher::component()
	 * @see \W3TC\CacheFlush::flush_posts()
	 */
	public function config_pgcache() {
		$this->verify_ajax_request( 'w3tc_config_pgcache' );

		$enable          = ! empty( Util_Request::get_string( 'enable' ) );
		$w3tc_engine     = empty( Util_Request::get_string( 'engine' ) ) ? '' : esc_attr( Util_Request::get_string( 'engine', '', true ) );
		$is_updating     = false;
		$success         = false;
		$w3tc_config     = new Config();
		$pgcache_enabled = $w3tc_config->get_boolean( 'pgcache.enabled' );
		$pgcache_engine  = $w3tc_config->get_string( 'pgcache.engine' );
		$allowed_engines = array(
			'',
			'file',
			'file_generic',
			'redis',
			'memcached',
			'nginx_memcached',
			'apc',
			'eaccelerator',
			'xcache',
			'wincache',
		);

		if ( in_array( $w3tc_engine, $allowed_engines, true ) ) {
			if ( empty( $w3tc_engine ) || 'file' === $w3tc_engine || 'file_generic' === $w3tc_engine || Util_Installed::$w3tc_engine() ) {
				if ( $pgcache_enabled !== $enable ) {
					$w3tc_config->set( 'pgcache.enabled', $enable );
					$is_updating = true;
				}

				if ( ! empty( $w3tc_engine ) && $pgcache_engine !== $w3tc_engine ) {
					$w3tc_config->set( 'pgcache.engine', $w3tc_engine );
					$is_updating = true;
				}

				if ( $is_updating ) {
					$w3tc_config->save();

					$f = Dispatcher::component( 'CacheFlush' );
					$f->flush_posts();

					$e = Dispatcher::component( 'PgCache_Environment' );
					$e->fix_on_wpadmin_request( $w3tc_config, true );
				}

				if ( $w3tc_config->get_boolean( 'pgcache.enabled' ) === $enable &&
					( ! $enable || $w3tc_config->get_string( 'pgcache.engine' ) === $w3tc_engine ) ) {
						$success      = true;
						$w3tc_message = __( 'Settings updated', 'w3-total-cache' );
				} else {
					$w3tc_message = __( 'Settings not updated', 'w3-total-cache' );
				}
			} else {
				$w3tc_message = __( 'Requested cache storage engine is not available', 'w3-total-cache' );
			}
		} else {
			$w3tc_message = __( 'Requested cache storage engine is invalid', 'w3-total-cache' );
		}

		wp_send_json_success(
			array(
				'success'          => $success,
				'message'          => $w3tc_message,
				'enable'           => $enable,
				'engine'           => $w3tc_engine,
				'current_enabled'  => $w3tc_config->get_boolean( 'pgcache.enabled' ),
				'current_engine'   => $w3tc_config->get_string( 'pgcache.engine' ),
				'previous_enabled' => $pgcache_enabled,
				'previous_engine'  => $pgcache_engine,
			)
		);
	}

	/**
	 * Admin-Ajax: Test database cache.
	 *
	 * @since 2.0.0
	 *
	 * @see \W3TC\Config::get_boolean()
	 * @see \W3TC\Config::get_string()
	 *
	 * @global $wpdb WordPress database object.
	 */
	public function test_dbcache() {
		$this->verify_ajax_request( 'w3tc_test_dbcache' );

		$w3tc_config = new Config();
		$results     = array(
			'enabled' => $w3tc_config->get_boolean( 'dbcache.enabled' ),
			'engine'  => $w3tc_config->get_string( 'dbcache.engine' ),
			'elapsed' => null,
		);

		global $wpdb;

		// Ensure db.php drop-in is present before testing.
		$env = new DbCache_Environment();
		$env->fix_on_wpadmin_request( $w3tc_config, true );

		// Temporarily mimic a front-end request so dbcache isn't rejected by admin context.
		$original_referer = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$original_uri     = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		$_SERVER['HTTP_REFERER'] = site_url( '/' );
		$_SERVER['REQUEST_URI']  = '/';

		$removed_cookies = array();
		foreach ( array_keys( $_COOKIE ) as $cookie_name ) {
			if ( 0 === strpos( $cookie_name, 'wordpress_logged_in' ) ) {
				$removed_cookies[ $cookie_name ] = $_COOKIE[ $cookie_name ]; // phpcs:ignore WordPress.Security
				unset( $_COOKIE[ $cookie_name ] );
			}
		}

		// Clear any request-wide dbcache reject state from earlier bootstrap queries.
		$this->reset_dbcache_reject_state();

		$queries = $this->get_dbcache_test_queries( $wpdb );

		// Use more iterations to reduce timing noise and amplify cache benefit.
		$iterations = 30;

		// Reduce cross-test interference from runtime caches.
		$this->reset_runtime_caches();

		// Always flush dbcache between engine tests to avoid warm carry-over.
		$flusher = Dispatcher::component( 'CacheFlush' );
		$flusher->dbcache_flush();

		// Run the workload once to capture a single timing per engine.
		$results['elapsed'] = $this->run_dbcache_benchmark( $wpdb, $queries, $iterations );

		// Restore request context.
		if ( null === $original_referer ) {
			unset( $_SERVER['HTTP_REFERER'] );
		} else {
			$_SERVER['HTTP_REFERER'] = $original_referer;
		}

		if ( null === $original_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $original_uri;
		}

		foreach ( $removed_cookies as $cookie_name => $cookie_value ) {
			$_COOKIE[ $cookie_name ] = $cookie_value;
		}

		wp_send_json_success( $results );
	}

	/**
	 * Admin-Ajax: Get the database cache settings.
	 *
	 * @since  2.0.0
	 *
	 * @see \W3TC\Config::get_boolean()
	 * @see \W3TC\Config::get_string()
	 */
	public function get_dbcache_settings() {
		$this->verify_ajax_request( 'w3tc_get_dbcache_settings' );

		$w3tc_config = new Config();

		wp_send_json_success(
			array(
				'enabled' => $w3tc_config->get_boolean( 'dbcache.enabled' ),
				'engine'  => $w3tc_config->get_string( 'dbcache.engine' ),
			)
		);
	}

	/**
	 * Admin-Ajax: Configure the database cache settings.
	 *
	 * @since  2.0.0
	 *
	 * @see \W3TC\Config::get_boolean()
	 * @see \W3TC\Config::get_string()
	 * @see \W3TC\Util_Installed::$w3tc_engine()
	 * @see \W3TC\Config::set()
	 * @see \W3TC\Config::save()
	 * @see \W3TC\Dispatcher::component()
	 * @see \W3TC\CacheFlush::dbcache_flush()
	 */
	public function config_dbcache() {
		$this->verify_ajax_request( 'w3tc_config_dbcache' );

		$enable          = ! empty( Util_Request::get_string( 'enable' ) );
		$w3tc_engine     = empty( Util_Request::get_string( 'engine' ) ) ? '' : esc_attr( Util_Request::get_string( 'engine', '', true ) );
		$is_updating     = false;
		$success         = false;
		$w3tc_config     = new Config();
		$old_enabled     = $w3tc_config->get_boolean( 'dbcache.enabled' );
		$old_engine      = $w3tc_config->get_string( 'dbcache.engine' );
		$allowed_engines = array(
			'',
			'file',
			'redis',
			'memcached',
			'apc',
			'eaccelerator',
			'xcache',
			'wincache',
		);

		if ( in_array( $w3tc_engine, $allowed_engines, true ) ) {
			if ( empty( $w3tc_engine ) || 'file' === $w3tc_engine || Util_Installed::$w3tc_engine() ) {
				if ( $old_enabled !== $enable ) {
					$w3tc_config->set( 'dbcache.enabled', $enable );
					$is_updating = true;
				}

				if ( ! empty( $w3tc_engine ) && $old_engine !== $w3tc_engine ) {
					$w3tc_config->set( 'dbcache.engine', $w3tc_engine );
					$is_updating = true;
				}

				if ( $is_updating ) {
					$w3tc_config->save();

					// Flush Database Cache.
					$f = Dispatcher::component( 'CacheFlush' );
					$f->dbcache_flush();

					// Fix environment on event. Only instates cron if needed.
					Util_Admin::fix_on_event( $w3tc_config, 'setupguide_dbcache' );

					// Ensure db.php drop-in is updated immediately for the next request.
					$env = new DbCache_Environment();
					$env->fix_on_wpadmin_request( $w3tc_config, true );
				}

				if ( $w3tc_config->get_boolean( 'dbcache.enabled' ) === $enable &&
					( ! $enable || $w3tc_config->get_string( 'dbcache.engine' ) === $w3tc_engine ) ) {
						$success      = true;
						$w3tc_message = __( 'Settings updated', 'w3-total-cache' );
				} else {
					$w3tc_message = __( 'Settings not updated', 'w3-total-cache' );
				}
			} else {
				$w3tc_message = __( 'Requested cache storage engine is not available', 'w3-total-cache' );
			}
		} else {
			$w3tc_message = __( 'Requested cache storage engine is invalid', 'w3-total-cache' );
		}

		wp_send_json_success(
			array(
				'success'          => $success,
				'message'          => $w3tc_message,
				'enable'           => $enable,
				'engine'           => $w3tc_engine,
				'current_enabled'  => $w3tc_config->get_boolean( 'dbcache.enabled' ),
				'current_engine'   => $w3tc_config->get_string( 'dbcache.engine' ),
				'previous_enabled' => $old_enabled,
				'previous_engine'  => $old_engine,
			)
		);
	}

	/**
	 * Admin-Ajax: Test object cache.
	 *
	 * @since 2.0.0
	 *
	 * @see \W3TC\Config::get_boolean()
	 * @see \W3TC\Config::get_string()
	 */
	public function test_objcache() {
		$this->verify_ajax_request( 'w3tc_test_objcache' );

		global $wp_object_cache;

		$w3tc_config = new Config();
		$results     = array(
			'enabled' => $w3tc_config->getf_boolean( 'objectcache.enabled' ),
			'engine'  => $w3tc_config->get_string( 'objectcache.engine' ),
			'elapsed' => null,
		);

		// Ensure object-cache.php drop-in is present before testing.
		$oc_env = new ObjectCache_Environment();
		$oc_env->fix_on_wpadmin_request( $w3tc_config, true );

		// Temporarily mimic a front-end request and allow writes in admin-ajax.
		$original_enabled_for_admin = $w3tc_config->getf_boolean( 'objectcache.enabled_for_wp_admin' );
		$w3tc_config->set( 'objectcache.enabled_for_wp_admin', true );
		$original_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		$_SERVER['REQUEST_URI'] = '/';

		$removed_cookies = array();
		foreach ( array_keys( $_COOKIE ) as $cookie_name ) {
			if ( 0 === strpos( $cookie_name, 'wordpress_logged_in' ) ) {
				$removed_cookies[ $cookie_name ] = $_COOKIE[ $cookie_name ]; // phpcs:ignore WordPress.Security
				unset( $_COOKIE[ $cookie_name ] );
			}
		}

		if ( function_exists( 'wp_suspend_cache_addition' ) ) {
			wp_suspend_cache_addition( false );
		}

		$post_ids      = $this->get_objcache_sample_post_ids();
		$payload_key   = 'w3tc_objcache_setupguide_payload';
		$payload_value = array(
			'time'     => time(),
			'post_ids' => $post_ids,
		);

		// Start with a clean runtime cache for consistent runs.
		$this->flush_object_cache_runtime();

		wp_cache_set( $payload_key, $payload_value, 'w3tc_setupguide', 300 );

		// Warm persistent cache.
		$this->run_objcache_scenario( $post_ids, $payload_key );

		// Flush runtime cache only if a runtime-only flush is available; avoid full persistent flush.
		$this->flush_object_cache_runtime();

		// Reinitialize the runtime cache instance to better simulate a new request hitting the persistent store.
		if ( function_exists( 'wp_cache_init' ) ) {
			wp_cache_init();
		}

		$results['elapsed']   = $this->run_objcache_scenario( $post_ids, $payload_key );
		$results['post_ct']   = count( $post_ids );
		$results['cache_hit'] = ( false !== wp_cache_get( $payload_key, 'w3tc_setupguide' ) );

		// Restore context.
		if ( null === $original_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $original_uri;
		}

		foreach ( $removed_cookies as $cookie_name => $cookie_value ) {
			$_COOKIE[ $cookie_name ] = $cookie_value;
		}

		if ( function_exists( 'wp_suspend_cache_addition' ) ) {
			wp_suspend_cache_addition( true );
		}

		$w3tc_config->set( 'objectcache.enabled_for_wp_admin', $original_enabled_for_admin );

		wp_send_json_success( $results );
	}

	/**
	 * Admin-Ajax: Get the object cache settings.
	 *
	 * @since  2.0.0
	 *
	 * @see \W3TC\Config::get_boolean()
	 * @see \W3TC\Config::get_string()
	 */
	public function get_objcache_settings() {
		$this->verify_ajax_request( 'w3tc_get_objcache_settings' );

		$w3tc_config = new Config();

		wp_send_json_success(
			array(
				'enabled' => $w3tc_config->getf_boolean( 'objectcache.enabled' ),
				'engine'  => $w3tc_config->get_string( 'objectcache.engine' ),
			)
		);
	}

	/**
	 * Admin-Ajax: Configure the object cache settings.
	 *
	 * @since  2.0.0
	 *
	 * @see \W3TC\Config::get_boolean()
	 * @see \W3TC\Config::get_string()
	 * @see \W3TC\Util_Installed::$w3tc_engine()
	 * @see \W3TC\Config::set()
	 * @see \W3TC\Config::save()
	 * @see \W3TC\Dispatcher::component()
	 * @see \W3TC\CacheFlush::objcache_flush()
	 */
	public function config_objcache() {
		$this->verify_ajax_request( 'w3tc_config_objcache' );

		$enable          = ! empty( Util_Request::get_string( 'enable' ) );
		$w3tc_engine     = empty( Util_Request::get_string( 'engine' ) ) ? '' : esc_attr( Util_Request::get_string( 'engine', '', true ) );
		$is_updating     = false;
		$success         = false;
		$w3tc_config     = new Config();
		$old_enabled     = $w3tc_config->getf_boolean( 'objectcache.enabled' );
		$old_engine      = $w3tc_config->get_string( 'objectcache.engine' );
		$allowed_engines = array(
			'',
			'file',
			'redis',
			'memcached',
			'apc',
			'eaccelerator',
			'xcache',
			'wincache',
		);

		if ( in_array( $w3tc_engine, $allowed_engines, true ) ) {
			if ( empty( $w3tc_engine ) || 'file' === $w3tc_engine || Util_Installed::$w3tc_engine() ) {
				if ( $old_enabled !== $enable ) {
					$w3tc_config->set( 'objectcache.enabled', $enable );
					$is_updating = true;
				}

				if ( ! empty( $w3tc_engine ) && $old_engine !== $w3tc_engine ) {
					$w3tc_config->set( 'objectcache.engine', $w3tc_engine );
					$is_updating = true;
				}

				if ( $is_updating ) {
					$w3tc_config->save();

					// Flush Object Cache.
					$f = Dispatcher::component( 'CacheFlush' );
					$f->objectcache_flush();

					// Fix environment on event. Only instates cron if needed.
					Util_Admin::fix_on_event( $w3tc_config, 'setupguide_objectcache' );

					// Ensure object-cache.php drop-in is updated immediately for the next request.
					$oc_env = new ObjectCache_Environment();
					$oc_env->fix_on_wpadmin_request( $w3tc_config, true );
				}

				if (
					$w3tc_config->getf_boolean( 'objectcache.enabled' ) === $enable
					&& (
						! $enable
						|| $w3tc_config->get_string( 'objectcache.engine' ) === $w3tc_engine
					)
				) {
					$success      = true;
					$w3tc_message = __( 'Settings updated', 'w3-total-cache' );
				} else {
					$w3tc_message = __( 'Settings not updated', 'w3-total-cache' );
				}
			} else {
				$w3tc_message = __( 'Requested cache storage engine is not available', 'w3-total-cache' );
			}
		} else {
			$w3tc_message = __( 'Requested cache storage engine is invalid', 'w3-total-cache' );
		}

		wp_send_json_success(
			array(
				'success'          => $success,
				'message'          => $w3tc_message,
				'enable'           => $enable,
				'engine'           => $w3tc_engine,
				'current_enabled'  => $w3tc_config->getf_boolean( 'objectcache.enabled' ),
				'current_engine'   => $w3tc_config->get_string( 'objectcache.engine' ),
				'previous_enabled' => $old_enabled,
				'previous_engine'  => $old_engine,
			)
		);
	}

	/**
	 * Admin-Ajax: Get the browser cache settings.
	 *
	 * @since  2.0.0
	 *
	 * @see \W3TC\Config::get_boolean()
	 * @see \W3TC\Config::get_string()
	 */
	public function get_browsercache_settings() {
		$this->verify_ajax_request( 'w3tc_get_browsercache_settings' );

		$w3tc_config = new Config();

		wp_send_json_success(
			array(
				'enabled'             => $w3tc_config->get_boolean( 'browsercache.enabled' ),
				'cssjs.cache.control' => $w3tc_config->get_boolean( 'browsercache.cssjs.cache.control' ),
				'cssjs.cache.policy'  => $w3tc_config->get_string( 'browsercache.cssjs.cache.policy' ),
				'html.cache.control'  => $w3tc_config->get_boolean( 'browsercache.html.cache.control' ),
				'html.cache.policy'   => $w3tc_config->get_string( 'browsercache.html.cache.policy' ),
				'other.cache.control' => $w3tc_config->get_boolean( 'browsercache.other.cache.control' ),
				'other.cache.policy'  => $w3tc_config->get_string( 'browsercache.other.cache.policy' ),
			)
		);
	}

	/**
	 * Admin-Ajax: Configure the browser cache settings.
	 *
	 * @since  2.0.0
	 *
	 * @see \W3TC\Dispatcher::component()
	 * @see \W3TC\Config::get_boolean()
	 * @see \W3TC\Config::set()
	 * @see \W3TC\Config::save()
	 * @see \W3TC\CacheFlush::browsercache_flush()
	 * @see \W3TC\BrowserCache_Environment::fix_on_wpadmin_request()
	 *
	 * @uses $_POST['enable']
	 */
	public function config_browsercache() {
		$this->verify_ajax_request( 'w3tc_config_browsercache' );

		$enable               = ! empty( Util_Request::get_string( 'enable' ) );
		$w3tc_config          = new Config();
		$browsercache_enabled = $w3tc_config->get_boolean( 'browsercache.enabled' );

		if ( $browsercache_enabled !== $enable ) {
			$w3tc_config->set( 'browsercache.enabled', $enable );
			$w3tc_config->set( 'browsercache.cssjs.cache.control', true );
			$w3tc_config->set( 'browsercache.cssjs.cache.policy', 'cache_public_maxage' );
			$w3tc_config->set( 'browsercache.html.cache.control', true );
			$w3tc_config->set( 'browsercache.html.cache.policy', 'cache_public_maxage' );
			$w3tc_config->set( 'browsercache.other.cache.control', true );
			$w3tc_config->set( 'browsercache.other.cache.policy', 'cache_public_maxage' );
			$w3tc_config->save();

			$f = Dispatcher::component( 'CacheFlush' );
			$f->browsercache_flush();

			$e = Dispatcher::component( 'BrowserCache_Environment' );
			$e->fix_on_wpadmin_request( $w3tc_config, true );
		}

		$is_enabled = $w3tc_config->get_boolean( 'browsercache.enabled' );

		wp_send_json_success(
			array(
				'success'               => $is_enabled === $enable,
				'enable'                => $enable,
				'browsercache_enabled'  => $w3tc_config->get_boolean( 'browsercache.enabled' ),
				'browsercache_previous' => $browsercache_enabled,
			)
		);
	}

	/**
	 * Admin-Ajax: Get the lazy load settings.
	 *
	 * @since  2.0.0
	 *
	 * @see \W3TC\Config::get_boolean()
	 * @see \W3TC\Config::get_string()
	 * @see \W3TC\Config::get_array()
	 */
	public function get_lazyload_settings() {
		$this->verify_ajax_request( 'w3tc_get_lazyload_settings' );

		$w3tc_config = new Config();

		wp_send_json_success(
			array(
				'enabled'            => $w3tc_config->get_boolean( 'lazyload.enabled' ),
				'process_img'        => $w3tc_config->get_boolean( 'lazyload.process_img' ),
				'process_background' => $w3tc_config->get_boolean( 'lazyload_process_background' ),
				'exclude'            => $w3tc_config->get_array( 'lazyload.exclude' ), // phpcs:ignore WordPressVIPMinimum
				'embed_method'       => $w3tc_config->get_string( 'lazyload.embed_method' ),
			)
		);
	}

	/**
	 * Admin-Ajax: Configure lazy load.
	 *
	 * @since 2.0.0
	 *
	 * @see \W3TC\Dispatcher::component()
	 * @see \W3TC\Config::get_boolean()
	 * @see \W3TC\Config::set()
	 * @see \W3TC\Config::save()
	 * @see \W3TC\Dispatcher::component()
	 * @see \W3TC\CacheFlush::flush_posts()
	 *
	 * @uses $_POST['enable']
	 */
	public function config_lazyload() {
		$this->verify_ajax_request( 'w3tc_config_lazyload' );

		$enable           = ! empty( Util_Request::get_string( 'enable' ) );
		$w3tc_config      = new Config();
		$lazyload_enabled = $w3tc_config->get_boolean( 'lazyload.enabled' );

		if ( $lazyload_enabled !== $enable ) {
			$w3tc_config->set( 'lazyload.enabled', $enable );
			$w3tc_config->set( 'lazyload.process_img', true );
			$w3tc_config->set( 'lazyload_process_background', true );
			$w3tc_config->set( 'lazyload.embed_method', 'async_head' );
			$w3tc_config->save();

			$f = Dispatcher::component( 'CacheFlush' );
			$f->flush_posts();

			$e = Dispatcher::component( 'PgCache_Environment' );
			$e->fix_on_wpadmin_request( $w3tc_config, true );
		}

		$is_enabled = $w3tc_config->get_boolean( 'lazyload.enabled' );

		wp_send_json_success(
			array(
				'success'           => $is_enabled === $enable,
				'enable'            => $enable,
				'lazyload_enabled'  => $w3tc_config->get_boolean( 'lazyload.enabled' ),
				'lazyload_previous' => $lazyload_enabled,
			)
		);
	}

	/**
	 * Admin-Ajax: Get the imageservice settings.
	 *
	 * @since  2.4.0
	 *
	 * @see \W3TC\Config::is_extension_active()
	 * @see \W3TC\Config::get_string()
	 */
	public function get_imageservice_settings() {
		$this->verify_ajax_request( 'w3tc_get_imageservice_settings' );

		$w3tc_config = new Config();

		wp_send_json_success(
			array(
				'enabled'  => $w3tc_config->is_extension_active( 'imageservice' ),
				'settings' => $this->get_imageservice_settings_with_defaults( $w3tc_config ),
			)
		);
	}

	/**
	 * Admin-Ajax: Configure image converter.
	 *
	 * @since 2.4.0
	 *
	 * @see \W3TC\Dispatcher::component()
	 * @see \W3TC\Config::get_boolean()
	 * @see \W3TC\Config::set()
	 * @see \W3TC\Config::save()
	 * @see \W3TC\Dispatcher::component()
	 * @see \W3TC\CacheFlush::flush_posts()
	 *
	 * @uses $_POST['enable']
	 */
	public function config_imageservice() {
		$this->verify_ajax_request( 'w3tc_config_imageservice' );

		$enable      = ! empty( Util_Request::get_string( 'enable' ) );
		$w3tc_config = new Config();

		// Merge stored values with defaults so new settings are always present.
		$w3tc_settings = $this->get_imageservice_settings_with_defaults( $w3tc_config );

		// Update settings from the request, defaulting to current values if absent.
		$request_settings             = Util_Request::get_array( 'settings', array() );
		$w3tc_settings['compression'] = isset( $request_settings['compression'] ) ? $request_settings['compression'] : $w3tc_settings['compression'];
		$w3tc_settings['auto']        = isset( $request_settings['auto'] ) ? $request_settings['auto'] : $w3tc_settings['auto'];
		$w3tc_settings['visibility']  = isset( $request_settings['visibility'] ) ? $request_settings['visibility'] : $w3tc_settings['visibility'];
		$w3tc_settings['webp']        = array_key_exists( 'webp', $request_settings ) ? Util_Environment::to_boolean( $request_settings['webp'] ) : $w3tc_settings['webp'];
		$w3tc_settings['avif']        = array_key_exists( 'avif', $request_settings ) ? Util_Environment::to_boolean( $request_settings['avif'] ) : $w3tc_settings['avif'];

		$w3tc_config->set( 'imageservice', $w3tc_settings );
		$w3tc_config->save();

		if ( ! empty( $enable ) ) {
			Extensions_Util::activate_extension( 'imageservice', $w3tc_config );
		} else {
			Extensions_Util::deactivate_extension( 'imageservice', $w3tc_config );
		}

		$is_enabled = $w3tc_config->is_extension_active( 'imageservice' );

		wp_send_json_success(
			array(
				'success'               => $is_enabled === $enable,
				'enable'                => $enable,
				'imageservice_enabled'  => $is_enabled,
				'imageservice_settings' => $w3tc_settings,
			)
		);
	}

	/**
	 * Provide Image Service settings merged with defaults.
	 *
	 * @since 2.9.0
	 *
	 * @param Config $w3tc_config Configuration object.
	 * @return array
	 */
	private function get_imageservice_settings_with_defaults( Config $w3tc_config ) {
		$defaults = array(
			'compression' => 'lossy',
			'auto'        => 'enabled',
			'visibility'  => 'never',
			'webp'        => true,
			'avif'        => true,
		);

		return array_merge( $defaults, (array) $w3tc_config->get_array( 'imageservice' ) );
	}

	/**
	 * Display the terms of service dialog if needed.
	 *
	 * @since  2.0.0
	 * @access private
	 *
	 * @see Util_Environment::is_w3tc_pro()
	 * @see Licensing_Core::get_tos_choice()
	 *
	 * @return bool
	 */
	private function maybe_ask_tos() {
		$w3tc_config = new Config();
		if ( Util_Environment::is_w3tc_pro( $w3tc_config ) ) {
			return false;
		}

		$terms = Licensing_Core::get_tos_choice();

		return 'accept' !== $terms && 'decline' !== $terms && 'postpone' !== $terms;
	}

	/**
	 * Build the SQL statements used for database cache benchmarking.
	 *
	 * @since 2.9.0
	 *
	 * @param \wpdb $wpdb WordPress database object.
	 *
	 * @return array
	 */
	private function get_dbcache_test_queries( $wpdb ) {
		return array(
			"SELECT ID, post_title, post_date, post_author, post_type FROM {$wpdb->posts} WHERE post_status IN ('publish','private') ORDER BY post_date DESC LIMIT 60",
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish'",
			"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'theme_mods_%' ORDER BY option_id DESC LIMIT 50",
			"SELECT pm.post_id, pm.meta_key, pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.post_status = 'publish' ORDER BY pm.meta_id DESC LIMIT 80",
			"SELECT tr.object_id, tt.taxonomy, t.name FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id ORDER BY tr.object_id DESC LIMIT 80",
			"SELECT c.comment_post_ID, c.comment_date_gmt, c.user_id, c.comment_approved FROM {$wpdb->comments} c WHERE c.comment_approved IN ('0','1') ORDER BY c.comment_date_gmt DESC LIMIT 60",
		);
	}

	/**
	 * Execute a repeatable set of read-heavy queries to measure cache performance.
	 *
	 * @since 2.9.0
	 *
	 * @param \wpdb $wpdb       WordPress database object.
	 * @param array $queries    List of SQL queries to execute.
	 * @param int   $iterations How many times to repeat the full set.
	 *
	 * @return float Seconds elapsed.
	 */
	private function run_dbcache_benchmark( $wpdb, array $queries, $iterations ) {
		$start_time = microtime( true );

		for ( $w3tc_i = 0; $w3tc_i < $iterations; $w3tc_i++ ) {
			foreach ( $queries as $sql ) {
				$wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			}
		}

		return microtime( true ) - $start_time;
	}

	/**
	 * Clear runtime caches to reduce contamination between timed runs.
	 *
	 * @since 2.9.0
	 *
	 * @return void
	 */
	private function reset_runtime_caches() {
		if ( function_exists( 'wp_cache_flush_runtime' ) ) {
			wp_cache_flush_runtime();
		} elseif ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}

	/**
	 * Clear dbcache reject state for the current request so tests can evaluate with fresh context.
	 *
	 * @since 2.9.0
	 *
	 * @return void
	 */
	private function reset_dbcache_reject_state() {
		global $wpdb;

		/**
		 * DbCache wpdb wrapper instance, when available.
		 *
		 * @var DbCache_WpdbNew|null
		 */
		$dbcache_wpdb = null;
		if ( $wpdb instanceof DbCache_WpdbNew ) {
			$dbcache_wpdb = $wpdb;
		} elseif ( class_exists( '\W3TC\DbCache_Wpdb' ) ) {
			$dbcache_wpdb = DbCache_Wpdb::instance();
		}

		if ( $dbcache_wpdb instanceof DbCache_WpdbNew ) {
			$processors = $dbcache_wpdb->get_processors();

			foreach ( $processors as $processor ) {
				if ( $processor instanceof DbCache_WpdbInjection_QueryCaching ) {
					$processor->reset_reject_state();
				}
			}
		}
	}

	/**
	 * Collect a set of post IDs to be used when benchmarking the object cache.
	 *
	 * @since 2.9.0
	 *
	 * @return array
	 */
	private function get_objcache_sample_post_ids() {
		$query = new \WP_Query(
			array(
				'post_type'              => array( 'post', 'page' ),
				'post_status'            => array( 'publish', 'private', 'inherit' ),
				'posts_per_page'         => 25,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'cache_results'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$post_ids = $query->posts;

		if ( empty( $post_ids ) ) {
			$post_ids = array( 0 );
		}

		return $post_ids;
	}

	/**
	 * Run a representative workload that should benefit from a persistent object cache.
	 *
	 * @since 2.9.0
	 *
	 * @param array  $post_ids    IDs to request in the query.
	 * @param string $payload_key Cache key used to check persistence.
	 * @param int    $iterations  How many times to repeat the workload.
	 *
	 * @return float Seconds elapsed.
	 */
	private function run_objcache_scenario( array $post_ids, $payload_key, $iterations = 1 ) {
		$posts_per_page = max( 1, count( $post_ids ) );
		$start_time     = microtime( true );

		for ( $w3tc_i = 0; $w3tc_i < $iterations; $w3tc_i++ ) {
			$query = new \WP_Query(
				array(
					'post__in'               => $post_ids,
					'post_type'              => array( 'post', 'page' ),
					'post_status'            => array( 'publish', 'private', 'inherit' ),
					'orderby'                => 'post__in',
					'posts_per_page'         => $posts_per_page,
					'no_found_rows'          => true,
					'cache_results'          => true,
					'update_post_meta_cache' => true,
					'update_post_term_cache' => true,
					'ignore_sticky_posts'    => true,
				)
			);

			foreach ( $query->posts as $post ) {
				get_post_meta( $post->ID );
				wp_get_object_terms( $post->ID, 'category' );
			}

			get_option( 'blogname' );
			get_option( 'siteurl' );
			get_option( 'permalink_structure' );
			wp_cache_get( 'alloptions', 'options' );
			wp_cache_get( $payload_key, 'w3tc_setupguide' );

			wp_reset_postdata();
		}

		return microtime( true ) - $start_time;
	}

	/**
	 * Clear runtime object cache data while preserving persistent stores when possible.
	 *
	 * @since 2.9.0
	 *
	 * @return string Which flush method was used.
	 */
	private function flush_object_cache_runtime() {
		if ( function_exists( 'wp_cache_flush_runtime' ) ) {
			wp_cache_flush_runtime();
			return 'runtime';
		}

		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
			return 'full';
		}

		return 'none';
	}

	/**
	 * Get configuration.
	 *
	 * @since  2.0.0
	 * @access private
	 *
	 * @global $wp_version WordPress version string.
	 * @global $wpdb       WordPress database connection.
	 *
	 * @see \W3TC\Config::get_boolean()
	 * @see \W3TC\Util_Request::get_string()
	 * @see \W3TC\Dispatcher::config_state()
	 * @see \W3TC\Licensing_Core::get_tos_choice()
	 * @see \W3TC\Util_Environment::home_url_host()
	 * @see \W3TC\Util_Environment::w3tc_edition()
	 * @see \W3TC\Util_Widget::list_widgets()
	 *
	 * @return array
	 */
	private function get_config() {
		global $wp_version, $wpdb;

		$w3tc_config          = new Config();
		$browsercache_enabled = $w3tc_config->get_boolean( 'browsercache.enabled' );
		$w3tc_is_pro          = Util_Environment::is_w3tc_pro( $w3tc_config );
		$page                 = Util_Request::get_string( 'page' );
		$state                = Dispatcher::config_state();
		$force_master_config  = $w3tc_config->get_boolean( 'common.force_master' );
		$image_service_limits = array(
			'free_hourly'  => number_format_i18n( W3TC_IMAGE_SERVICE_FREE_HLIMIT, 0 ),
			'free_monthly' => number_format_i18n( W3TC_IMAGE_SERVICE_FREE_MLIMIT, 0 ),
			'pro_hourly'   => number_format_i18n( W3TC_IMAGE_SERVICE_PRO_HLIMIT, 0 ),
			'pro_monthly'  => empty( W3TC_IMAGE_SERVICE_PRO_MLIMIT ) ?
				esc_html__( 'Unlimited', 'w3-total-cache' ) :
				number_format_i18n( W3TC_IMAGE_SERVICE_PRO_MLIMIT, 0 ),
		);

		/**
		 * Mint per-action nonces so the JS can post the correct nonce per
		 * AJAX action (defense against cross-action nonce replay).
		 */
		$nonces = array();
		foreach ( self::$nonce_actions as $action => $nonce_action ) {
			$nonces[ $action ] = \wp_create_nonce( $nonce_action );
		}

		if ( 'w3tc_extensions' === $page ) {
			$page = 'extensions/' . Util_Request::get_string( 'extension' );
		}

		return array(
			'title'          => esc_html__( 'Setup Guide', 'w3-total-cache' ),
			'scripts'        => array(
				array(
					'handle'    => 'setup-guide',
					'src'       => esc_url( plugin_dir_url( __FILE__ ) . 'pub/js/setup-guide.js' ),
					'deps'      => array( 'jquery' ),
					'version'   => W3TC_VERSION,
					'in_footer' => false,
					'localize'  => array(
						'object_name' => 'W3TC_SetupGuide',
						'data'        => array(
							'page'              => $page,
							'nonces'            => $nonces,
							'wp_version'        => $wp_version,
							'php_version'       => phpversion(),
							'w3tc_version'      => W3TC_VERSION,
							'server_software'   => isset( $_SERVER['SERVER_SOFTWARE'] ) ?
								sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : null,
							'db_version'        => $wpdb->db_version(),
							'home_url_host'     => Util_Environment::home_url_host(),
							'install_version'   => esc_attr( $state->get_string( 'common.install_version' ) ),
							'w3tc_install_date' => get_option( 'w3tc_install_date' ),
							'w3tc_edition'      => esc_attr( Util_Environment::w3tc_edition( $w3tc_config ) ),
							'list_widgets'      => esc_attr( Util_Widget::list_widgets() ),
							'w3tc_pro'          => $w3tc_is_pro,
							'w3tc_has_key'      => $w3tc_config->get_string( 'plugin.license_key' ),
							'w3tc_pro_c'        => defined( 'W3TC_PRO' ) && W3TC_PRO,
							'w3tc_enterprise_c' => defined( 'W3TC_ENTERPRISE' ) && W3TC_ENTERPRISE,
							'w3tc_plugin_type'  => esc_attr( $w3tc_config->get_string( 'plugin.type' ) ),
							'ga_profile'        => ( defined( 'W3TC_DEVELOPER' ) && W3TC_DEVELOPER ) ? 'G-Q3CHQJWERM' : 'G-5TFS8M5TTY',
							'tos_choice'        => Licensing_Core::get_tos_choice(),
							'track_usage'       => $w3tc_config->get_boolean( 'common.track_usage' ),
							'test_complete_msg' => __(
								'Testing complete.  Click Next to advance to the section and see the results.',
								'w3-total-cache'
							),
							'test_error_msg'    => __(
								'Could not perform this test.  Please reload the page to try again or click skip button to abort the setup guide.',
								'w3-total-cache'
							),
							'config_error_msg'  => __(
								'Could not update configuration.  Please reload the page to try again or click skip button to abort the setup guide.',
								'w3-total-cache'
							),
							'unavailable_text'  => __( 'Unavailable', 'w3-total-cache' ),
							'none'              => __( 'None', 'w3-total-cache' ),
							'disk'              => __( 'Disk', 'w3-total-cache' ),
							'disk_basic'        => __( 'Disk: Basic', 'w3-total-cache' ),
							'disk_enhanced'     => __( 'Disk: Enhanced', 'w3-total-cache' ),
							'enabled'           => __( 'Enabled', 'w3-total-cache' ),
							'notEnabled'        => __( 'Not Enabled', 'w3-total-cache' ),
							'dashboardUrl'      => esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_dashboard' ) ),
							'objcache_disabled' => ( ! $w3tc_config->getf_boolean( 'objectcache.enabled' ) && has_filter( 'w3tc_config_item_objectcache.enabled' ) ),
							'warning_disk'      => __(
								'Warning: Using disk storage for this setting can potentially create a large number of files.  Please be aware of any inode or disk space limits you may have on your hosting account.',
								'w3-total-cache'
							),
						),
					),
				),
			),
			'styles'         => array(
				array(
					'handle'  => 'setup-guide',
					'src'     => esc_url( plugin_dir_url( __FILE__ ) . 'pub/css/setup-guide.css' ),
					'version' => W3TC_VERSION,
				),
			),
			'actions'        => array(
				array(
					'tag'      => 'wp_ajax_w3tc_wizard_skip',
					'function' => array(
						$this,
						'skip',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_tos_choice',
					'function' => array(
						$this,
						'set_tos_choice',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_get_pgcache_settings',
					'function' => array(
						$this,
						'get_pgcache_settings',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_test_pgcache',
					'function' => array(
						$this,
						'test_pgcache',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_config_pgcache',
					'function' => array(
						$this,
						'config_pgcache',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_get_dbcache_settings',
					'function' => array(
						$this,
						'get_dbcache_settings',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_test_dbcache',
					'function' => array(
						$this,
						'test_dbcache',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_config_dbcache',
					'function' => array(
						$this,
						'config_dbcache',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_get_objcache_settings',
					'function' => array(
						$this,
						'get_objcache_settings',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_test_objcache',
					'function' => array(
						$this,
						'test_objcache',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_config_objcache',
					'function' => array(
						$this,
						'config_objcache',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_get_browsercache_settings',
					'function' => array(
						$this,
						'get_browsercache_settings',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_config_browsercache',
					'function' => array(
						$this,
						'config_browsercache',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_get_imageservice_settings',
					'function' => array(
						$this,
						'get_imageservice_settings',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_config_imageservice',
					'function' => array(
						$this,
						'config_imageservice',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_get_lazyload_settings',
					'function' => array(
						$this,
						'get_lazyload_settings',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_config_lazyload',
					'function' => array(
						$this,
						'config_lazyload',
					),
				),
			),
			'steps_location' => 'left',
			'steps'          => array(
				array(
					'id'   => 'welcome',
					'text' => __( 'Welcome', 'w3-total-cache' ),
				),
				array(
					'id'   => 'pgcache',
					'text' => __( 'Page Cache', 'w3-total-cache' ),
				),
				array(
					'id'   => 'dbcache',
					'text' => __( 'Database Cache', 'w3-total-cache' ),
				),
				array(
					'id'   => 'objectcache',
					'text' => __( 'Object Cache', 'w3-total-cache' ),
				),
				array(
					'id'   => 'imageservice',
					'text' => __( 'Image Converter', 'w3-total-cache' ),
				),
				array(
					'id'   => 'lazyload',
					'text' => __( 'Lazy Load', 'w3-total-cache' ),
				),
				array(
					'id'   => 'more',
					'text' => __( 'More Caching Options', 'w3-total-cache' ),
				),
			),
			'slides'         => array(
				array( // Welcome.
					'headline' => __( 'Welcome to the W3 Total Cache Setup Guide!', 'w3-total-cache' ),
					'id'       => 'welcome',
					'markup'   => '<div id="w3tc-welcome"' . ( $this->maybe_ask_tos() ? ' class="hidden"' : '' ) . '>
						<p>' .
						esc_html__(
							'You have selected the Performance Suite that professionals have consistently ranked #1 for options and speed improvements.',
							'w3-total-cache'
						) . '</p>
						<p><strong>' . esc_html__( 'W3 Total Cache', 'w3-total-cache' ) . '</strong>
						' . esc_html__(
							'provides many options to help your website perform faster.  While the ideal settings vary for every website, there are a few settings we recommend that you enable now.',
							'w3-total-cache'
						) . '</p>
						<p><strong>
						' . esc_html__(
							'If a caching method shows as unavailable you do not have the necessary modules installed. You may need to reach out to your host for installation availablity and directions.',
							'w3-total-cache'
						) . '</strong></p>' .
						sprintf(
							// translators: 1: Anchor/link open tag, 2: Anchor/link close tag.
							esc_html__(
								'If you prefer to configure the settings on your own, you can %1$sskip this setup guide%2$s.',
								'w3-total-cache'
							),
							'<a id="w3tc-wizard-skip-link" href="#">',
							'</a>'
						) . '</p>
						</div>' . ( $this->maybe_ask_tos() ?
						'<div id="w3tc-licensing-terms" class="notice notice-info inline">
						<p>' .
						sprintf(
							// translators: 1: Anchor/link open tag, 2: Anchor/link close tag.
							esc_html__(
								'By allowing us to collect data about how W3 Total Cache is used, we can improve our features and experience for everyone. This data will not include any personally identifiable information.  Feel free to review our %1$sterms of use and privacy policy%2$s.',
								'w3-total-cache'
							),
							'<a target="_blank" href="' . esc_url( 'https://api.w3-edge.com/v1/redirects/policies-terms' ) . '">',
							'</a>'
						) . '</p>
						<p>
						<input type="button" class="button" data-choice="accept" value="' . esc_html__( 'Accept', 'w3-total-cache' ) . '" /> &nbsp;
						<input type="button" class="button" data-choice="decline" value="' . esc_html__( 'Decline', 'w3-total-cache' ) . '" />
						</p>
						</div>' : '' ),
				),
				array( // Page Cache.
					'headline' => __( 'Page Cache', 'w3-total-cache' ),
					'id'       => 'pc1',
					'markup'   => '<p>' . sprintf(
						// translators: 1: HTML emphesis open tag, 2: HTML emphesis close tag.
						esc_html__(
							'The time it takes between a visitor\'s browser page request and receiving the first byte of a response is referred to as %1$sTime to First Byte%2$s.',
							'w3-total-cache'
						),
						'<em>',
						'</em>'
					) . '</p>
					<p>
						<strong>' . esc_html__( 'W3 Total Cache', 'w3-total-cache' ) . '</strong> ' .
						esc_html__( 'can help you speed up', 'w3-total-cache' ) .
						' <em>' . esc_html__( 'Time to First Byte', 'w3-total-cache' ) . '</em> by using Page Cache.
					</p>
					<p>' .
					esc_html__(
						'We\'ll test your homepage with Page Cache disabled and then with several storage engines.  You should review the test results and choose the best for your website.',
						'w3-total-cache'
					) . '</p>
					<p>' .
					esc_html__(
						'Individual test runs can vary. Run the test a few times and use the Average column to choose the best option for your site.',
						'w3-total-cache'
					) . '</p>
					<p>
						<input id="w3tc-test-pgcache" class="button-primary" type="button" value="' .
						esc_html__( 'Test Page Cache', 'w3-total-cache' ) . '">
						<span class="hidden"><span class="spinner inline"></span>' . esc_html__( 'Measuring', 'w3-total-cache' ) .
						' <em>' . esc_html__( 'Time to First Byte', 'w3-total-cache' ) . '</em>&hellip;
						</span>
					</p>
					<p class="hidden">
						' . esc_html__( 'Test URL:', 'w3-total-cache' ) . ' <span id="w3tc-test-url"></span>
					</p>
					<table id="w3tc-pgcache-table" class="w3tc-setupguide-table widefat striped hidden">
						<thead>
							<tr>
								<th>' . esc_html__( 'Select', 'w3-total-cache' ) . '</th>
								<th>' . esc_html__( 'Storage Engine', 'w3-total-cache' ) . '</th>
								<th>' . esc_html__( 'Latest (ms)', 'w3-total-cache' ) . '</th>
								<th>' . esc_html__( 'Average (ms)', 'w3-total-cache' ) . '</th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>',
				),
				array( // Database Cache.
					'headline' => __( 'Database Cache', 'w3-total-cache' ),
					'id'       => 'dbc1',
					'markup'   => '<p>' .
						esc_html__(
							'Many database queries are made in every dynamic page request.  A database cache may speed up the generation of dynamic pages.  Database Cache serves query results directly from a storage engine.',
							'w3-total-cache'
						) . '</p>
						<p>' .
						esc_html__(
							'Individual test runs can vary. Run the test a few times and use the Average column to choose the best option for your site.',
							'w3-total-cache'
						) . '</p>
						<p>
						<input id="w3tc-test-dbcache" class="button-primary" type="button" value="' .
						esc_html__( 'Test Database Cache', 'w3-total-cache' ) . '">
						<span class="hidden"><span class="spinner inline"></span>' . esc_html__( 'Testing', 'w3-total-cache' ) .
						' <em>' . esc_html__( 'Database Cache', 'w3-total-cache' ) . '</em>&hellip;
						</span>
						</p>
						<p>' .
						esc_html__(
							'Run the test multiple times to smooth out variability and rely on the Average column when choosing your setting.',
							'w3-total-cache'
						) . '</p>
						<table id="w3tc-dbc-table" class="w3tc-setupguide-table widefat striped hidden">
							<thead>
								<tr>
									<th>' . esc_html__( 'Select', 'w3-total-cache' ) . '</th>
									<th>' . esc_html__( 'Storage Engine', 'w3-total-cache' ) . '</th>
									<th>' . esc_html__( 'Latest (ms)', 'w3-total-cache' ) . '</th>
									<th>' . esc_html__( 'Average (ms)', 'w3-total-cache' ) . '</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
						<div id="w3tc-dbcache-recommended" class="notice notice-info inline hidden">
						<div class="w3tc-notice-recommended"><span class="dashicons dashicons-lightbulb"></span> Recommended</div>
						<div><p>' .
						esc_html__(
							'By default, this feature is disabled.  We recommend using Redis or Memcached, otherwise leave this feature disabled as the server database engine may be faster than using disk caching.',
							'w3-total-cache'
						) . '</p></div>
						</div>',
				),
				array( // Object Cache.
					'headline' => __( 'Object Cache', 'w3-total-cache' ),
					'id'       => 'oc1',
					'markup'   => '<p>' .
						esc_html__(
							'WordPress caches objects used to build pages, but does not reuse them for future page requests.',
							'w3-total-cache'
						) . '</p>
						<p><strong>' . esc_html__( 'W3 Total Cache', 'w3-total-cache' ) . '</strong> ' .
						esc_html__( 'can help you speed up dynamic pages by persistently storing objects.', 'w3-total-cache' ) .
						'</p>
						<p>' .
						esc_html__(
							'Individual test runs can vary. Run the test a few times and use the Average column to choose the best option for your site.',
							'w3-total-cache'
						) . '</p>' .
						( ! $w3tc_config->getf_boolean( 'objectcache.enabled' ) && has_filter( 'w3tc_config_item_objectcache.enabled' ) ? '<p class="notice notice-warning inline">' . esc_html__( 'Object Cache is disabled via filter.', 'w3-total-cache' ) . '</p>' : '' ) .
						( ! has_filter( 'w3tc_config_item_objectcache.enabled' ) ? '<p>
							<input id="w3tc-test-objcache" class="button-primary" type="button" value="' . esc_html__( 'Test Object Cache', 'w3-total-cache' ) . '">
							<span class="hidden"><span class="spinner inline"></span>' . esc_html__( 'Testing', 'w3-total-cache' ) .
								' <em>' . esc_html__( 'Object Cache', 'w3-total-cache' ) . '</em>&hellip;
							</span>
						</p>' : '' ) .
						'<p>' .
						esc_html__(
							'Test several times to account for variability and pick the setting with the best average.',
							'w3-total-cache'
						) . '</p>
						<table id="w3tc-objcache-table" class="w3tc-setupguide-table widefat striped hidden">
							<thead>
								<tr>
									<th>' . esc_html__( 'Select', 'w3-total-cache' ) . '</th>
									<th>' . esc_html__( 'Storage Engine', 'w3-total-cache' ) . '</th>
									<th>' . esc_html__( 'Latest (ms)', 'w3-total-cache' ) . '</th>
									<th>' . esc_html__( 'Average (ms)', 'w3-total-cache' ) . '</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>',
				),
				array( // Image Service.
					'headline' => __( 'Image Converter', 'w3-total-cache' ),
					'id'       => 'io1',
					'markup'   => '<div class="w3tc-io-description"><p>' .
						sprintf(
							// translators: 1: Anchor/link open tag, 2: Anchor/link close tag.
							esc_html__(
								'Adds the ability to convert images in the Media Library to the modern WebP format for better performance. %1$sLearn more%2$s',
								'w3-total-cache'
							),
							'<a id="w3tc-io-learn-more" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/image-service/?utm_source=w3tc&utm_medium=learn_more&utm_campaign=image_service' ) . '" target="_blank">',
							'</a>'
						) . '</p></div>
						<p class="w3tc-io-toggle">
							<input type="checkbox" id="imageservice-enable" value="1" />
							<label for="imageservice-enable">' . esc_html__( 'Enable Image Converter', 'w3-total-cache' ) . '</label>
						</p>
						<div id="imageservice-options" class="hidden">
							<p><strong>' . esc_html__( 'Conversion types', 'w3-total-cache' ) . '</strong></p>
							<p class="w3tc-imageservice-formats">
								<label><input type="checkbox" id="imageservice-webp" value="1" /> ' . esc_html__( 'WebP format', 'w3-total-cache' ) . '</label><br />
								<label><input type="checkbox" id="imageservice-avif" value="1" /> ' . esc_html__( 'AVIF format', 'w3-total-cache' ) . '</label>
							</p>
						</div>
						<div class="w3tc-io-rate-grid">
							<div class="w3tc-io-rate-card' . ( $w3tc_is_pro ? '' : ' w3tc-io-rate-current' ) . '">
								' . ( $w3tc_is_pro ? '' : '<span class="w3tc-io-rate-badge">' . esc_html__( 'Your rate limits', 'w3-total-cache' ) . '</span>' ) . '
								<span class="w3tc-io-rate-label">' . esc_html__( 'Free', 'w3-total-cache' ) . '</span>
								<span class="w3tc-io-rate">' . sprintf(
									// translators: 1: Number of conversions per hour.
									esc_html__( '%s conversions per hour', 'w3-total-cache' ),
									esc_html( $image_service_limits['free_hourly'] )
								) . '</span>
								<span class="w3tc-io-rate">' . sprintf(
									// translators: 1: Number of conversions per month.
									esc_html__( '%s conversions per month', 'w3-total-cache' ),
									esc_html( $image_service_limits['free_monthly'] )
								) . '</span>
							</div>
							<div class="w3tc-io-rate-card w3tc-io-rate-pro' . ( $w3tc_is_pro ? ' w3tc-io-rate-current' : '' ) . '">
								' . ( $w3tc_is_pro ? '<span class="w3tc-io-rate-badge">' . esc_html__( 'Your rate limits', 'w3-total-cache' ) . '</span>' : '' ) . '
								<span class="w3tc-io-rate-label">' . esc_html__( 'Pro', 'w3-total-cache' ) . '</span>
								<span class="w3tc-io-rate">' . sprintf(
									// translators: 1: Number of conversions per hour.
									esc_html__( '%s conversions per hour', 'w3-total-cache' ),
									esc_html( $image_service_limits['pro_hourly'] )
								) . '</span>
								<span class="w3tc-io-rate">' . sprintf(
									// translators: 1: Number of conversions per month.
									esc_html__( '%s conversions per month', 'w3-total-cache' ),
									esc_html( $image_service_limits['pro_monthly'] )
								) . '</span>
							</div>
						</div>' .
						( $w3tc_is_pro ? '' : '<div class="w3tc-gopro-manual-wrap">
							<div class="w3tc-io-upsell w3tc-gopro">
								<div class="w3tc-gopro-ribbon"><span>★ PRO</span></div>
								<div class="w3tc-gopro-content">
									<p><strong>' . esc_html__( 'Need higher limits?', 'w3-total-cache' ) . '</strong> ' .
										sprintf(
											// translators: 1: Number of conversions per hour, 2: Number of conversions per month.
											esc_html__(
												'Upgrade to Pro for up to %1$s conversions per hour and %2$s per month.',
												'w3-total-cache'
											),
											esc_html( $image_service_limits['pro_hourly'] ),
											esc_html( $image_service_limits['pro_monthly'] )
										) . '</p>
								</div>
								<div class="w3tc-gopro-action">
									<input type="button" class="button-primary btn button-buy-plugin" value="' . esc_attr__( 'Upgrade to W3 Total Cache Pro', 'w3-total-cache' ) . '">
								</div>
							</div>
						</div>'
					),
				),
				array( // Lazy load.
					'headline' => __( 'Lazy Load', 'w3-total-cache' ),
					'id'       => 'll1',
					'markup'   => '<p>' .
						esc_html__(
							'Pages containing images and other objects can have their load time reduced by deferring them until they are needed.  For example, images can be loaded when a visitor scrolls down the page to make them visible.',
							'w3-total-cache'
						) . '</p>
						<p>
						<input type="checkbox" id="lazyload-enable" value="1" /> <label for="lazyload-enable">' .
						esc_html__( 'Lazy Load Images', 'w3-total-cache' ) . '</label></p>',
				),
				array( // Setup complete.
					'headline' => __( 'Setup Complete!', 'w3-total-cache' ),
					'id'       => 'complete',
					'markup'   => '<p>' .
						sprintf(
							// translators: 1: HTML strong open tag, 2: HTML strong close tag, 3: Label.
							esc_html__(
								'%1$sPage Cache%2$s engine set to %1$s%3$s%2$s',
								'w3-total-cache'
							),
							'<strong>',
							'</strong>',
							'<span id="w3tc-pgcache-engine">' . esc_html__( 'UNKNOWN', 'w3-total-cache' ) . '</span>'
						) . '</p>
						<p>' .
						sprintf(
							// translators: 1: HTML strong open tag, 2: HTML strong close tag.
							esc_html__(
								'%1$sTime to First Byte%2$s has changed by %1$s%3$s%2$s',
								'w3-total-cache'
							),
							'<strong>',
							'</strong>',
							'<span id="w3tc-ttfb-diff">0%</span>'
						) . '</p>
						<p>' .
						sprintf(
							// translators: 1: HTML strong open tag, 2: HTML strong close tag, 3: Label.
							esc_html__(
								'%1$sDatabase Cache%2$s engine set to %1$s%3$s%2$s',
								'w3-total-cache'
							),
							'<strong>',
							'</strong>',
							'<span id="w3tc-dbcache-engine">' . esc_html__( 'UNKNOWN', 'w3-total-cache' ) . '</span>'
						) . '</p>
						<p>' .
							(
								! $w3tc_config->getf_boolean( 'objectcache.enabled' ) && has_filter( 'w3tc_config_item_objectcache.enabled' )
								?
								sprintf(
									// translators: 1: HTML strong open tag, 2: HTML strong close tag.
									esc_html__(
										'%1$sObject Cache%2$s is %1$sdisabled via filter%2$s',
										'w3-total-cache'
									),
									'<strong>',
									'</strong>'
								)
								:
								sprintf(
									// translators: 1: HTML strong open tag, 2: HTML strong close tag, 3: Label.
									esc_html__(
										'%1$sObject Cache%2$s engine set to %1$s%3$s%2$s',
										'w3-total-cache'
									),
									'<strong>',
									'</strong>',
									'<span id="w3tc-objcache-engine">' . esc_html__( 'UNKNOWN', 'w3-total-cache' ) . '</span>'
								)
							) .
						'</p>
						<p>' . sprintf(
							// translators: 1: HTML strong open tag, 2: HTML strong close tag, 3: Label.
							esc_html__(
								'%1$sImage Converter%2$s enabled? %1$s%3$s%2$s',
								'w3-total-cache'
							),
							'<strong>',
							'</strong>',
							'<span id="w3tc-imageservice-setting">' . esc_html__( 'UNKNOWN', 'w3-total-cache' ) . '</span>'
						) . '</p>
						<p>' . sprintf(
							// translators: 1: HTML strong open tag, 2: HTML strong close tag, 3: Label.
							esc_html__(
								'%1$sLazy Load%2$s images? %1$s%3$s%2$s',
								'w3-total-cache'
							),
							'<strong>',
							'</strong>',
							'<span id="w3tc-lazyload-setting">' . esc_html__( 'UNKNOWN', 'w3-total-cache' ) . '</span>'
						) . '</p>
						<h3>' . esc_html__( 'What\'s Next?', 'w3-total-cache' ) . '</h3>
						<p>' .
						sprintf(
							// translators: 1: HTML emphesis open tag, 2: HTML emphesis close tag.
							esc_html__(
								'Your website\'s performance can still be improved by configuring %1$sminify%2$s settings, setting up a %1$sCDN%2$s, and more!',
								'w3-total-cache'
							),
							'<strong>',
							'</strong>'
						) . '</p>
						<p>' .
						sprintf(
							// translators: 1: Anchor/link open tag, 2: Anchor/link close tag.
							esc_html__(
								'Please visit %1$sGeneral Settings%2$s to learn more about these features.',
								'w3-total-cache'
							),
							'<a href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general' ) ) . '">',
							'</a>'
						) . '</p>
						<h3>' . esc_html__( 'Google PageSpeed Tool', 'w3-total-cache' ) . '</h3>
						<p>' . sprintf(
							// translators: 1: Anchor/link open tag, 2: Anchor/link close tag.
							esc_html__(
								'Google PageSpeed Insights can be used to analyze your homepage and provide an explanation of metrics and recommendations for improvements using W3 Total Cache features/extensions.  This tool is enabled by default but will not function until authorization is granted, which can be done on the %1$sGeneral Settings%2$s page.',
								'w3-total-cache'
							),
							'<a href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#google_pagespeed' ) ) . '">',
							'</a>'
						) . '</p>
						<h3>' . esc_html__( 'Need help?', 'w3-total-cache' ) . '</h3>
						<p>' .
						sprintf(
							// translators: 1: Anchor/link open tag, 2: Anchor/link close tag.
							esc_html__(
								'We\'re here to help you!  Visit our %1$sSupport Center%2$s for helpful information and to ask questions.',
								'w3-total-cache'
							),
							'<a href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/?utm_source=w3tc&utm_medium=setup_guide&utm_campaign=support_center' ) . '" target="_blank">',
							'</a>'
						) . '</p>',
				),
			),
		);
	}
}
