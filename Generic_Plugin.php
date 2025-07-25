<?php
/**
 * File: Generic_Plugin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Generic_Plugin
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class Generic_Plugin {
	/**
	 * Is WP die
	 *
	 * @var bool
	 */
	private $is_wp_die = false;

	/**
	 * Translations
	 *
	 * @var array
	 */
	private $_translations = array();

	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config = null;

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Runs plugin
	 *
	 * @return void
	 */
	public function run() {
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ), 5 ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
		add_action( 'w3tc_purge_all_wpcron', array( $this, 'w3tc_purgeall_wpcron' ) );

		/* need this to run before wp-cron to issue w3tc redirect */
		add_action( 'init', array( $this, 'init' ), 1 );

		if ( Util_Environment::is_w3tc_pro_dev() && Util_Environment::is_w3tc_pro( $this->_config ) ) {
			add_action( 'wp_footer', array( $this, 'pro_dev_mode' ) );
		}

		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 150 );
		add_action( 'admin_bar_init', array( $this, 'admin_bar_init' ) );

		$http_user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( ! empty( Util_Request::get_string( 'w3tc_theme' ) ) && stristr( $http_user_agent, W3TC_POWERED_BY ) !== false ) {
			add_filter( 'template', array( $this, 'template_preview' ) );
			add_filter( 'stylesheet', array( $this, 'stylesheet_preview' ) );
		} elseif ( $this->_config->get_boolean( 'mobile.enabled' ) || $this->_config->get_boolean( 'referrer.enabled' ) ) {
			add_filter( 'template', array( $this, 'template' ) );
			add_filter( 'stylesheet', array( $this, 'stylesheet' ) );
		}

		/**
		 * Create cookies to flag if a pgcache role was loggedin
		 */
		if ( ! $this->_config->get_boolean( 'pgcache.reject.logged' ) && $this->_config->get_array( 'pgcache.reject.logged_roles' ) ) {
			add_action( 'set_logged_in_cookie', array( $this, 'check_login_action' ), 0, 5 );
			add_action( 'clear_auth_cookie', array( $this, 'check_login_action' ), 0, 5 );
		}

		if ( $this->can_ob() ) {
			add_filter( 'wp_die_xml_handler', array( $this, 'wp_die_handler' ) );
			add_filter( 'wp_die_handler', array( $this, 'wp_die_handler' ) );

			ob_start( array( $this, 'ob_callback' ) );
		}

		// Run tasks after updating this plugin.
		$this->post_update_tasks();
	}

	/**
	 * Marks wp_die was called so response is system message
	 *
	 * @param callable $v Callback.
	 *
	 * @return callable
	 */
	public function wp_die_handler( $v ) {
		$this->is_wp_die = true;
		return $v;
	}

	/**
	 * Cron schedules filter
	 *
	 * Sets default values which are overriden by apropriate plugins if they are enabled
	 *
	 * Absense of keys (if e.g. pgcaching became disabled, but there is cron event scheduled in db) causes PHP notices.
	 *
	 * @param array $schedules Schedules.
	 *
	 * @return array
	 */
	public function cron_schedules( $schedules ) {
		$c = $this->_config;

		$schedules = array_merge(
			$schedules,
			array(
				'w3_cdn_cron_queue_process' => array(
					'interval' => 0,
					'display'  => '[W3TC] CDN queue process (disabled)',
				),
				'w3_cdn_cron_upload'        => array(
					'interval' => 0,
					'display'  => '[W3TC] CDN auto upload (disabled)',
				),
				'w3_dbcache_cleanup'        => array(
					'interval' => 0,
					'display'  => '[W3TC] Database Cache file GC (disabled)',
				),
				'w3_fragmentcache_cleanup'  => array(
					'interval' => 0,
					'display'  => '[W3TC] Fragment Cache file GC (disabled)',
				),
				'w3_minify_cleanup'         => array(
					'interval' => 0,
					'display'  => '[W3TC] Minify file GC (disabled)',
				),
				'w3_objectcache_cleanup'    => array(
					'interval' => 0,
					'display'  => '[W3TC] Object Cache file GC (disabled)',
				),
				'w3_pgcache_cleanup'        => array(
					'interval' => 0,
					'display'  => '[W3TC] Page Cache file GC (disabled)',
				),
				'w3_pgcache_prime'          => array(
					'interval' => 0,
					'display'  => '[W3TC] Page Cache file GC (disabled)',
				),
			)
		);

		return $schedules;
	}

	/**
	 * Cron job for processing purging page cache.
	 *
	 * @since 2.8.0
	 *
	 * @return void
	 */
	public function w3tc_purgeall_wpcron() {
		$flusher = Dispatcher::component( 'CacheFlush' );
		$flusher->flush_all();
	}

	/**
	 * Init action
	 *
	 * @return void
	 */
	public function init() {
		// Load W3TC textdomain for translations.
		$this->reset_l10n();
		load_plugin_textdomain( W3TC_TEXT_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		if ( is_multisite() && ! is_network_admin() ) {
			global $w3_current_blog_id, $current_blog;
			if ( $w3_current_blog_id !== $current_blog->blog_id && ! isset( $GLOBALS['w3tc_blogmap_register_new_item'] ) ) {
				$url = Util_Environment::host_port() . ( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' );
				$pos = strpos( $url, '?' );
				if ( false !== $pos ) {
					$url = substr( $url, 0, $pos );
				}
				$GLOBALS['w3tc_blogmap_register_new_item'] = $url;
			}
		}

		if ( isset( $GLOBALS['w3tc_blogmap_register_new_item'] ) ) {
			$do_redirect = Util_WpmuBlogmap::register_new_item( $this->_config );

			// reset cache of blog_id.
			Util_Environment::reset_microcache();
			Dispatcher::reset_config();

			// change config to actual blog, it was master before.
			$this->_config = new Config();

			// fix environment, potentially it's first request to a specific blog.
			$environment = Dispatcher::component( 'Root_Environment' );
			$environment->fix_on_event( $this->_config, 'first_frontend', $this->_config );

			// need to repeat request processing, since we was not able to realize
			// blog_id before so we are running with master config now.
			// redirect to the same url causes "redirect loop" error in browser,
			// so need to redirect to something a bit different.
			if ( $do_redirect ) {
				if ( ( defined( 'WP_CLI' ) && WP_CLI ) || php_sapi_name() === 'cli' ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
					// command-line mode, no real requests made,
					// try to switch context in-request.
				} else {
					$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
					if ( strpos( $request_uri, '?' ) === false ) {
						Util_Environment::safe_redirect_temp( $request_uri . '?repeat=w3tc' );
					} elseif ( strpos( $request_uri, 'repeat=w3tc' ) === false ) {
						Util_Environment::safe_redirect_temp( $request_uri . '&repeat=w3tc' );
					}
				}
			}
		}

		/**
		 * Check for rewrite test request
		 */
		$rewrite_test = Util_Request::get_boolean( 'w3tc_rewrite_test' );

		if ( $rewrite_test ) {
			echo 'OK';
			exit();
		}
		$admin_bar = false;
		if ( function_exists( 'is_admin_bar_showing' ) ) {
			$admin_bar = is_admin_bar_showing();
		}

		if ( $admin_bar ) {
			add_action( 'wp_print_scripts', array( $this, 'popup_script' ) );
		}

		// dont add system stuff to search results.
		$repeat_val = Util_Request::get_string( 'repeat' );
		if ( ( ! empty( $repeat_val ) && 'w3tc' === $repeat_val ) || Util_Environment::is_preview_mode() ) {
			header( 'X-Robots-Tag: noindex' );
		}
	}

	/**
	 * Admin bar init
	 *
	 * @return void
	 */
	public function admin_bar_init() {
		$font_base = plugins_url( 'pub/fonts/w3tc', W3TC_FILE );
		$css       = "
			@font-face {
				font-family: 'w3tc';
			src: url('$font_base.eot');
			src: url('$font_base.eot?#iefix') format('embedded-opentype'),
				 url('$font_base.woff') format('woff'),
				 url('$font_base.ttf') format('truetype'),
				 url('$font_base.svg#w3tc') format('svg');
			font-weight: normal;
			font-style: normal;
		}
		.w3tc-icon:before{
			content:'\\0041'; top: 2px;
			font-family: 'w3tc';
		}";

		wp_add_inline_style( 'admin-bar', $css );
	}

	/**
	 * Admin bar menu
	 *
	 * @return void
	 */
	public function admin_bar_menu() {
		global $wp_admin_bar;

		$base_capability = apply_filters( 'w3tc_capability_admin_bar', 'manage_options' );

		if ( current_user_can( $base_capability ) ) {
			$modules = Dispatcher::component( 'ModuleStatus' );

			$menu_items = array();

			$menu_items['00010.generic'] = array(
				'id'    => 'w3tc',
				'title' => sprintf(
					'<span class="w3tc-icon ab-icon"></span><span class="ab-label">%s</span>',
					__( 'Performance', 'w3-total-cache' )
				),
				'href'  => wp_nonce_url(
					network_admin_url( 'admin.php?page=w3tc_dashboard' ),
					'w3tc'
				),
			);

			if ( $modules->plugin_is_enabled() ) {
				$menu_items['10010.generic'] = array(
					'id'     => 'w3tc_flush_all',
					'parent' => 'w3tc',
					'title'  => __( 'Purge All Caches', 'w3-total-cache' ),
					'href'   => wp_nonce_url(
						network_admin_url( 'admin.php?page=w3tc_dashboard&amp;w3tc_flush_all' ),
						'w3tc'
					),
				);

				// Add menu item to flush all cached except Bunny CDN.
				if (
					0 && // @todo Revisit this item.
					Cdn_BunnyCdn_Page::is_active() && (
						$modules->can_empty_memcache()
						|| $modules->can_empty_opcode()
						|| $modules->can_empty_file()
						|| $modules->can_empty_varnish()
					)
				) {
					$menu_items['10012.generic'] = array(
						'id'     => 'w3tc_flush_all_except_bunnycdn',
						'parent' => 'w3tc',
						'title'  => __( 'Purge All Caches Except Bunny CDN', 'w3-total-cache' ),
						'href'   => wp_nonce_url(
							network_admin_url( 'admin.php?page=w3tc_dashboard&amp;w3tc_bunnycdn_flush_all_except_bunnycdn' ),
							'w3tc'
						),
					);
				}

				// Add menu item to flush all cached except Cloudflare.
				if (
					$this->_config->get_boolean( 'cdnfsd.enabled' ) &&
					'cloudflare' === $this->_config->get_string( 'cdnfsd.engine' ) &&
					! empty( $this->_config->get_string( array( 'cloudflare', 'email' ) ) ) &&
					! empty( $this->_config->get_string( array( 'cloudflare', 'key' ) ) ) &&
					! empty( $this->_config->get_string( array( 'cloudflare', 'zone_id' ) ) ) &&
					in_array( 'cloudflare', array_keys( Extensions_Util::get_active_extensions( $this->_config ) ), true ) &&
					(
						$modules->can_empty_memcache() ||
						$modules->can_empty_opcode() ||
						$modules->can_empty_file() ||
						$modules->can_empty_varnish()
					)
				) {
					$menu_items['10015.generic'] = array(
						'id'     => 'w3tc_flush_all_except_cf',
						'parent' => 'w3tc',
						'title'  => __( 'Purge All Caches Except Cloudflare', 'w3-total-cache' ),
						'href'   => wp_nonce_url(
							network_admin_url( 'admin.php?page=w3tc_dashboard&amp;w3tc_cloudflare_flush_all_except_cf' ),
							'w3tc'
						),
					);
				}

				if ( ! is_admin() ) {
					$menu_items['10020.generic'] = array(
						'id'     => 'w3tc_flush_current_page',
						'parent' => 'w3tc',
						'title'  => __( 'Purge Current Page', 'w3-total-cache' ),
						'href'   => wp_nonce_url(
							admin_url( 'admin.php?page=w3tc_dashboard&amp;w3tc_flush_post&amp;post_id=' . Util_Environment::detect_post_id() . '&force=true' ),
							'w3tc'
						),
					);
				}

				$menu_items['20010.generic'] = array(
					'id'     => 'w3tc_flush',
					'parent' => 'w3tc',
					'title'  => __( 'Purge Modules', 'w3-total-cache' ),
				);
			}

			$menu_items['30000.generic'] = array(
				'id'     => 'w3tc_feature_showcase',
				'parent' => 'w3tc',
				'title'  => __( 'Feature Showcase', 'w3-total-cache' ),
				'href'   => wp_nonce_url(
					network_admin_url( 'admin.php?page=w3tc_feature_showcase' ),
					'w3tc'
				),
			);

			$menu_items['40010.generic'] = array(
				'id'     => 'w3tc_settings_general',
				'parent' => 'w3tc',
				'title'  => __( 'General Settings', 'w3-total-cache' ),
				'href'   => wp_nonce_url(
					network_admin_url( 'admin.php?page=w3tc_general' ),
					'w3tc'
				),
			);

			if ( Extension_AlwaysCached_Plugin::is_enabled() ) {
				$menu_items['40015.alwayscached'] = array(
					'id'     => 'w3tc_alwayscached',
					'parent' => 'w3tc',
					'title'  => __( 'Page Cache Queue', 'w3-total-cache' ),
					'href'   => wp_nonce_url(
						network_admin_url( 'admin.php?page=w3tc_extensions&extension=alwayscached&action=view' ),
						'w3tc'
					),
				);
			}

			$menu_items['40020.generic'] = array(
				'id'     => 'w3tc_settings_extensions',
				'parent' => 'w3tc',
				'title'  => __( 'Manage Extensions', 'w3-total-cache' ),
				'href'   => wp_nonce_url(
					network_admin_url( 'admin.php?page=w3tc_extensions' ),
					'w3tc'
				),
			);

			$menu_items['40030.generic'] = array(
				'id'     => 'w3tc_settings_faq',
				'parent' => 'w3tc',
				'title'  => __( 'FAQ', 'w3-total-cache' ),
				'href'   => wp_nonce_url(
					network_admin_url( 'admin.php?page=w3tc_faq' ),
					'w3tc'
				),
			);

			$menu_items['60010.generic'] = array(
				'id'     => 'w3tc_support',
				'parent' => 'w3tc',
				'title'  => __( 'Support', 'w3-total-cache' ),
				'href'   => wp_nonce_url(
					network_admin_url( 'admin.php?page=w3tc_support' ),
					'w3tc'
				),
			);

			if ( defined( 'W3TC_DEBUG' ) && W3TC_DEBUG ) {
				$menu_items['90010.generic'] = array(
					'id'     => 'w3tc_debug_overlays',
					'parent' => 'w3tc',
					'title'  => __( 'Debug: Overlays', 'w3-total-cache' ),
				);
			}

			$menu_items = apply_filters( 'w3tc_admin_bar_menu', $menu_items );

			$keys = array_keys( $menu_items );
			asort( $keys );

			foreach ( $keys as $key ) {
				$capability = apply_filters(
					'w3tc_capability_admin_bar_' . $menu_items[ $key ]['id'],
					$base_capability
				);

				if ( current_user_can( $capability ) ) {
					$wp_admin_bar->add_menu( $menu_items[ $key ] );
				}
			}
		}
	}

	/**
	 * Template filter
	 *
	 * @param unknown $template Template.
	 *
	 * @return string
	 */
	public function template( $template ) {
		$w3_mobile = Dispatcher::component( 'Mobile_UserAgent' );

		$mobile_template = $w3_mobile->get_template();

		if ( $mobile_template ) {
			return $mobile_template;
		} else {
			$w3_referrer = Dispatcher::component( 'Mobile_Referrer' );

			$referrer_template = $w3_referrer->get_template();

			if ( $referrer_template ) {
				return $referrer_template;
			}
		}

		return $template;
	}

	/**
	 * Stylesheet filter
	 *
	 * @param unknown $stylesheet Stylesheet.
	 *
	 * @return string
	 */
	public function stylesheet( $stylesheet ) {
		$w3_mobile = Dispatcher::component( 'Mobile_UserAgent' );

		$mobile_stylesheet = $w3_mobile->get_stylesheet();

		if ( $mobile_stylesheet ) {
			return $mobile_stylesheet;
		} else {
			$w3_referrer = Dispatcher::component( 'Mobile_Referrer' );

			$referrer_stylesheet = $w3_referrer->get_stylesheet();

			if ( $referrer_stylesheet ) {
				return $referrer_stylesheet;
			}
		}

		return $stylesheet;
	}

	/**
	 * Template filter
	 *
	 * @param unknown $template Template.
	 *
	 * @return string
	 */
	public function template_preview( $template ) {
		$theme_name = Util_Request::get_string( 'w3tc_theme' );

		$theme = Util_Theme::get( $theme_name );

		if ( $theme ) {
			return $theme['Template'];
		}

		return $template;
	}

	/**
	 * Stylesheet filter
	 *
	 * @param unknown $stylesheet Stylesheet.
	 *
	 * @return string
	 */
	public function stylesheet_preview( $stylesheet ) {
		$theme_name = Util_Request::get_string( 'w3tc_theme' );

		$theme = Util_Theme::get( $theme_name );

		if ( $theme ) {
			return $theme['Stylesheet'];
		}

		return $stylesheet;
	}

	/**
	 * Output buffering callback
	 *
	 * @param string $buffer Buffer.
	 *
	 * @return string
	 */
	public function ob_callback( $buffer ) {
		global $wpdb;

		global $w3_late_caching_succeeded;
		if ( $w3_late_caching_succeeded ) {
			return $buffer;
		}

		if ( $this->is_wp_die && ! apply_filters( 'w3tc_process_wp_die', false, $buffer ) ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
			// wp_die is dynamic output (usually fatal errors), dont process it.
		} else {
			$buffer = apply_filters( 'w3tc_process_content', $buffer );

			if ( Util_Content::can_print_comment( $buffer ) ) {
				/**
				 * Add footer comment
				 */
				$date = date_i18n( 'Y-m-d H:i:s' );
				$host = ( ! empty( $_SERVER['SERVER_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) ) : 'localhost' );

				if ( Util_Environment::is_preview_mode() ) {
					$buffer .= "\r\n<!-- W3 Total Cache used in preview mode -->";
				}

				$strings = array();

				if ( ! $this->_config->get_boolean( 'common.tweeted' ) ) {
					$strings[] = 'Performance optimized by W3 Total Cache. Learn more: https://www.boldgrid.com/w3-total-cache/';
					$strings[] = '';
				}

				$strings = apply_filters( 'w3tc_footer_comment', $strings );

				if ( count( $strings ) ) {
					$strings[] = '';
					$strings[] = sprintf(
						'Served from: %1$s @ %2$s by W3 Total Cache',
						Util_Content::escape_comment( $host ),
						$date
					);

					$buffer .= "\r\n<!--\r\n" .
						Util_Content::escape_comment( implode( "\r\n", $strings ) ) .
						"\r\n-->";
				}
			}

			$buffer = Util_Bus::do_ob_callbacks(
				array(
					'swarmify',
					'lazyload',
					'removecssjs',
					'deferscripts',
					'minify',
					'newrelic',
					'cdn',
					'browsercache',
				),
				$buffer
			);

			$buffer = apply_filters( 'w3tc_processed_content', $buffer );

			// Apply the w3tc_processed_content filter before pagecache callback.
			$buffer = Util_Bus::do_ob_callbacks(
				array( 'pagecache' ),
				$buffer
			);
		}

		return $buffer;
	}

	/**
	 * Check if we can do modify contents
	 *
	 * @return boolean
	 */
	public function can_ob() {
		global $w3_late_init;
		if ( $w3_late_init ) {
			return false;
		}

		/**
		 * Skip if doing AJAX
		 */
		if ( defined( 'DOING_AJAX' ) ) {
			return false;
		}

		/**
		 * Skip if doing cron
		 */
		if ( defined( 'DOING_CRON' ) ) {
			return false;
		}

		/**
		 * Skip if APP request
		 */
		if ( defined( 'APP_REQUEST' ) ) {
			return false;
		}

		/**
		 * Skip if XMLRPC request
		 */
		if ( defined( 'XMLRPC_REQUEST' ) ) {
			return false;
		}

		/**
		 * Check for WPMU's and WP's 3.0 short init
		 */
		if ( defined( 'SHORTINIT' ) && SHORTINIT ) {
			return false;
		}

		/**
		 * Check User Agent
		 */
		$http_user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( stristr( $http_user_agent, W3TC_POWERED_BY ) !== false ) {
			return false;
		}

		return true;
	}

	/**
	 * User login hook. Check if current user is not listed in pgcache.reject.* rules
	 * If so, set a role cookie so the requests wont be cached
	 *
	 * @param bool   $logged_in_cookie Logged in cookie flag.
	 * @param string $expire           Expire timestamp.
	 * @param int    $expiration       Time to expire.
	 * @param int    $user_id          User ID.
	 * @param string $action           Action.
	 *
	 * @return void
	 */
	public function check_login_action( $logged_in_cookie = false, $expire = ' ', $expiration = 0, $user_id = 0, $action = 'logged_out' ) {
		$current_user = wp_get_current_user();
		if ( isset( $current_user->ID ) && ! $current_user->ID ) {
			$user_id = new \WP_User( $user_id );
		} else {
			$user_id = $current_user;
		}

		if ( is_string( $user_id->roles ) ) {
			$roles = array( $user_id->roles );
		} elseif ( ! is_array( $user_id->roles ) || count( $user_id->roles ) <= 0 ) {
			return;
		} else {
			$roles = $user_id->roles;
		}

		$rejected_roles = $this->_config->get_array( 'pgcache.reject.roles' );

		if ( 'logged_out' === $action ) {
			foreach ( $rejected_roles as $role ) {
				$role_hash = md5( NONCE_KEY . $role );
				setcookie(
					'w3tc_logged_' . $role_hash,
					$expire,
					time() - 31536000,
					COOKIEPATH,
					COOKIE_DOMAIN
				);
			}

			return;
		}

		if ( 'logged_in' !== $action ) {
			return;
		}

		foreach ( $roles as $role ) {
			if ( in_array( $role, $rejected_roles, true ) ) {
				$role_hash = md5( NONCE_KEY . $role );
				setcookie(
					'w3tc_logged_' . $role_hash,
					true,
					$expire,
					COOKIEPATH,
					COOKIE_DOMAIN,
					is_ssl(),
					true
				);
			}
		}
	}

	/**
	 * Popup script embed
	 *
	 * @return void
	 */
	public function popup_script() {
		if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
			return;
		}
		?>
		<script type="text/javascript">
			function w3tc_popupadmin_bar(url) {
				return window.open(url, '', 'width=800,height=600,status=no,toolbar=no,menubar=no,scrollbars=yes');
			}
		</script>
		<?php
	}

	/**
	 * Check if debugging is enabled
	 *
	 * @return bool
	 */
	private function is_debugging() {
		$debug = $this->_config->get_boolean( 'pgcache.enabled' ) && $this->_config->get_boolean( 'pgcache.debug' );
		$debug = $debug || ( $this->_config->get_boolean( 'dbcache.enabled' ) && $this->_config->get_boolean( 'dbcache.debug' ) );
		$debug = $debug || ( $this->_config->getf_boolean( 'objectcache.enabled' ) && $this->_config->get_boolean( 'objectcache.debug' ) );
		$debug = $debug || ( $this->_config->get_boolean( 'browsercache.enabled' ) && $this->_config->get_boolean( 'browsercache.debug' ) );
		$debug = $debug || ( $this->_config->get_boolean( 'minify.enabled' ) && $this->_config->get_boolean( 'minify.debug' ) );
		$debug = $debug || ( $this->_config->get_boolean( 'cdn.enabled' ) && $this->_config->get_boolean( 'cdn.debug' ) );

		return $debug;
	}

	/**
	 * Output HTML in footer if dev mode enabled
	 *
	 * @return void
	 */
	public function pro_dev_mode() {
		echo '<!-- W3 Total Cache is currently running in Pro version Development mode. --><div style="border:2px solid red;text-align:center;font-size:1.2em;color:red"><p><strong>W3 Total Cache is currently running in Pro version Development mode.</strong></p></div>';
	}

	/**
	 * Reset the l10n global variables for our text domain.
	 *
	 * @return void
	 *
	 * @since 2.2.8
	 */
	public function reset_l10n() {
		global $l10n;

		unset( $l10n['w3-total-cache'] );
	}

	/**
	 * Run post-update generic (non-admin) tasks.
	 *
	 * Post-update generic (non-admin) tasks are run only once per version.
	 *
	 * @since 2.8.6
	 *
	 * @return void
	 */
	public function post_update_tasks(): void {
		// Check if W3TC was updated.
		$state            = Dispatcher::config_state();
		$last_run_version = $state->get_string( 'tasks.generic.last_run_version' );

		if ( empty( $last_run_version ) || \version_compare( W3TC_VERSION, $last_run_version, '>' ) ) {
			$ran_versions  = get_option( 'w3tc_post_update_generic_tasks_ran_versions', array() );
			$has_completed = false;

			// Check if W3TC was updated to 2.8.6 or higher.
			if ( \version_compare( W3TC_VERSION, '2.8.6', '>=' ) && ! in_array( '2.8.6', $ran_versions, true ) ) {
				// Disable Object Cache if using Disk, purge the cache files, and show a notice in wp-admin.  Only for main/blog ID 1.
				if (
					1 === get_current_blog_id() &&
					$this->_config->get_boolean( 'objectcache.enabled' ) &&
					'file' === $this->_config->get_string( 'objectcache.engine' )
				) {
					$this->_config->set( 'objectcache.enabled', false );
					$this->_config->save();

					// Purge the Object Cache files.
					Util_File::rmdir( Util_Environment::cache_blog_dir( 'object' ) );

					// Set the flag to show the notice.
					$state->set( 'tasks.notices.disabled_objdisk', true );
				}

				// Mark the task as ran.
				$ran_versions[] = '2.8.6';
				$has_completed  = true;

				// Delete cached notices.
				delete_option( 'w3tc_cached_notices' );
			}

			// Mark completed tasks as ran.
			if ( $has_completed ) {
				update_option( 'w3tc_post_update_generic_tasks_ran_versions', $ran_versions, false );
			}

			// Mark the task runner as ran for the current version.
			$state->set( 'tasks.generic.last_run_version', W3TC_VERSION );
			$state->save();
		}
	}
}
