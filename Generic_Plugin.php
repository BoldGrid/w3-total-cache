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
	 * Frontend notice data when redirecting back from admin actions.
	 *
	 * @since 2.0.0
	 *
	 * @var ?array
	 */
	private $frontend_notice;

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
		add_action( 'init', array( $this, 'load_frontend_message' ), 0 );
		add_action( 'init', array( $this, 'init' ), 1 );

		if ( Util_Environment::is_w3tc_pro_dev() && Util_Environment::is_w3tc_pro( $this->_config ) ) {
			add_action( 'wp_footer', array( $this, 'pro_dev_mode' ) );
		}

		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 150 );
		add_action( 'admin_bar_init', array( $this, 'admin_bar_init' ) );

		if ( defined( 'W3TC_DYNAMIC_SECURITY' ) && ! empty( W3TC_DYNAMIC_SECURITY ) ) {
			add_filter( 'rest_post_dispatch', array( $this, 'sanitize_rest_response_dynamic_tags' ), 10, 3 );
			add_filter( 'the_content_feed', array( $this, 'strip_dynamic_fragment_tags_filter' ) );
			add_filter( 'the_excerpt_rss', array( $this, 'strip_dynamic_fragment_tags_filter' ) );
			add_filter( 'comment_text_rss', array( $this, 'strip_dynamic_fragment_tags_filter' ) );
			add_filter( 'preprocess_comment', array( $this, 'strip_dynamic_fragment_tags_from_comment' ) );
		}

		$http_user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		/**
		 * The W3TC theme preview filters force-swap the active theme
		 * for any visitor that supplies the public W3TC_POWERED_BY
		 * User-Agent. Gate the registration on edit_theme_options so
		 * unauthenticated visitors cannot probe installed themes
		 *.
		 *
		 * @since 2.10.0
		 */
		if ( ! empty( Util_Request::get_string( 'w3tc_theme' ) )
			&& stristr( $http_user_agent, W3TC_POWERED_BY ) !== false
			&& \current_user_can( 'edit_theme_options' ) ) {
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

		$this->register_plugin_check_filters();

		// Run tasks after updating this plugin.
		$this->post_update_tasks();
	}

	/**
	 * Removes dynamic fragment tags from comment content before storage.
	 *
	 * @since 2.8.13
	 *
	 * @param array $comment_data Comment data being processed.
	 *
	 * @return array
	 */
	public function strip_dynamic_fragment_tags_from_comment( $comment_data ) {
		if ( isset( $comment_data['comment_content'] ) ) {
			$comment_data['comment_content'] = $this->strip_dynamic_fragment_tags_from_string( $comment_data['comment_content'] );
		}

		return $comment_data;
	}

	/**
	 * Removes dynamic fragment tags from RSS/feed content.
	 *
	 * @since 2.8.13
	 *
	 * @param string $content Content to sanitize.
	 *
	 * @return string
	 */
	public function strip_dynamic_fragment_tags_filter( $content ) {
		return $this->strip_dynamic_fragment_tags_from_string( $content );
	}

	/**
	 * Sanitises REST API responses to strip dynamic-fragment tags before they reach the client.
	 *
	 * @since 2.8.13
	 *
	 * @param \WP_REST_Response|mixed $w3tc_result  Response data.
	 * @param \WP_REST_Server         $server  REST server instance.
	 * @param \WP_REST_Request        $request Current request.
	 *
	 * @return \WP_REST_Response|mixed
	 */
	public function sanitize_rest_response_dynamic_tags( $w3tc_result, $server, $request ) {
		unset( $server );

		if ( $request instanceof \WP_REST_Request && 'edit' === $request->get_param( 'context' ) ) {
			return $w3tc_result;
		}

		$response  = ( $w3tc_result instanceof \WP_REST_Response ) ? $w3tc_result : rest_ensure_response( $w3tc_result );
		$w3tc_data = $response->get_data();
		$w3tc_data = $this->sanitize_dynamic_fragment_data( $w3tc_data );
		$response->set_data( $w3tc_data );

		return $response;
	}

	/**
	 * Recursively removes dynamic fragment tags from REST data structures.
	 *
	 * @since 2.8.13
	 *
	 * @param mixed $w3tc_data Response data.
	 *
	 * @return mixed
	 */
	private function sanitize_dynamic_fragment_data( $w3tc_data ) {
		if ( is_string( $w3tc_data ) ) {
			return $this->strip_dynamic_fragment_tags_from_string( $w3tc_data );
		}

		if ( is_array( $w3tc_data ) ) {
			foreach ( $w3tc_data as $w3tc_key => $w3tc_value ) {
				$w3tc_data[ $w3tc_key ] = $this->sanitize_dynamic_fragment_data( $w3tc_value );
			}

			return $w3tc_data;
		}

		if ( is_object( $w3tc_data ) ) {
			foreach ( $w3tc_data as $w3tc_key => $w3tc_value ) {
				$w3tc_data->{$w3tc_key} = $this->sanitize_dynamic_fragment_data( $w3tc_value );
			}

			return $w3tc_data;
		}

		return $w3tc_data;
	}

	/**
	 * Removes dynamic fragment tags from a text string.
	 *
	 * @since 2.8.13
	 *
	 * @param string $w3tc_value Raw content to sanitize.
	 *
	 * @return string
	 */
	private function strip_dynamic_fragment_tags_from_string( $w3tc_value ) {
		// Early return if the value is not a string or the W3TC_DYNAMIC_SECURITY constant is not defined or empty.
		if ( ! is_string( $w3tc_value ) || ! defined( 'W3TC_DYNAMIC_SECURITY' ) || empty( W3TC_DYNAMIC_SECURITY ) ) {
			return $w3tc_value;
		}

		$original_value = $w3tc_value;

		/**
		 * Remove dynamic fragment tags from the value.
		 * Use \s*\S+ (zero-or-more whitespace, then one-or-more non-whitespace) so that
		 * tags with no space between the keyword and token (e.g. <!-- mfuncTOKEN -->) are
		 * also caught and not passed through to the str_replace step below where a crafted
		 * token-containing name could otherwise be morphed into a valid mfunc tag.
		 */
		$pattern = array(
			'~<!--\s*mfunc\s*\S+.*?-->(.*?)<!--\s*/mfunc\s*\S+.*?\s*-->~Uis',
			'~<!--\s*mclude\s*\S+.*?-->(.*?)<!--\s*/mclude\s*\S+.*?\s*-->~Uis',
		);

		$w3tc_value = preg_replace_callback(
			$pattern,
			function ( $matches ) {
				return $matches[1]; // Keep only the captured content between the tags.
			},
			$w3tc_value
		);

		// If the value is null (preg_replace error), return the original value.
		if ( null === $w3tc_value ) {
			return $original_value;
		}

		// The W3TC_DYNAMIC_SECURITY constant should be a unique string and not an int or boolean, so don't strip "1"s.
		if ( 1 === (int) W3TC_DYNAMIC_SECURITY ) {
			return $w3tc_value;
		}

		// Remove the dynamic security token from the value.
		return str_replace( W3TC_DYNAMIC_SECURITY, '', $w3tc_value );
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
		$w3tc_c = $this->_config;

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

		/**
		 * Check for rewrite test request.
		 *
		 * This probe exists so PgCache_Environment can verify that the
		 * server's rewrite rules actually fire end-to-end. Without a token
		 * the endpoint is reachable to any anonymous caller and acts as a
		 * 2-byte plugin-presence oracle plus a cache-bypass side channel
		 * (the response always bypasses page cache by design). Require the
		 * single-use token issued by PgCache_Environment::test_rewrite()
		 * so only the plugin's own admin-side verifier can drive it.
		 *
		 * Handle before multisite blogmap first-frontend redirect logic
		 * so anonymous probes receive 404 instead of a 307 repeat=w3tc
		 * redirect on subdirectory subsites whose blogmap entry is not
		 * yet established.
		 */
		$rewrite_test = Util_Request::get_boolean( 'w3tc_rewrite_test' );

		if ( $rewrite_test ) {
			if ( ! Util_ProbeToken::consume(
				PgCache_Environment::PROBE_TOKEN_PREFIX,
				PgCache_Environment::PROBE_TOKEN_HEADER
			) ) {
				Util_ProbeToken::reject();
			}
			echo 'OK';
			exit();
		}

		if ( is_multisite() && ! is_network_admin() ) {
			global $w3tc_w3_current_blog_id, $current_blog;
			if ( $w3tc_w3_current_blog_id !== $current_blog->blog_id && ! isset( $GLOBALS['w3tc_blogmap_register_new_item'] ) ) {
				$w3tc_url = Util_Environment::host_port() . ( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' );
				$pos      = strpos( $w3tc_url, '?' );
				if ( false !== $pos ) {
					$w3tc_url = substr( $w3tc_url, 0, $pos );
				}
				$GLOBALS['w3tc_blogmap_register_new_item'] = $w3tc_url;
			}
		}

		if ( isset( $GLOBALS['w3tc_blogmap_register_new_item'] ) ) {
			$do_redirect = Util_WpmuBlogmap::register_new_item( $this->_config );

			// reset cache of blog_id.
			Util_Environment::reset_microcache();
			Dispatcher::reset_config();

			// change config to actual blog, it was master before.
			$this->_config = new Config();

			/**
			 * RT9-180 sub-C: Only run the cross-cache-layer
			 * `fix_on_event('first_frontend')` fan-out when
			 * `register_new_item()` actually wrote a new blogmap
			 * entry (return value === true). On dedup short-circuit
			 * or per-IP rate-limit, no new blog was discovered — the
			 * env-fix handlers (PgCache, BrowserCache, Cdn, etc.)
			 * have already run for this URL in a prior request, so
			 * re-running is wasted IO. This also closes the
			 * enumeration-amplification leg: an attacker probing N
			 * undiscovered subsites can no longer drive N env-fix
			 * fan-outs from one IP burst — the rate limit in
			 * register_new_item caps the writes and the gate here
			 * caps the env-fix calls that would otherwise fire
			 * unconditionally.
			 *
			 * @since 2.10.0
			 */
			if ( $do_redirect ) {
				$environment = Dispatcher::component( 'Root_Environment' );
				$environment->fix_on_event( $this->_config, 'first_frontend', $this->_config );
			}

			/**
			 * Need to repeat request processing, since we was not able to realize
			 * blog_id before so we are running with master config now.
			 * redirect to the same url causes "redirect loop" error in browser,
			 * so need to redirect to something a bit different.
			 */
			if ( $do_redirect ) {
				if ( ( defined( 'WP_CLI' ) && WP_CLI ) || php_sapi_name() === 'cli' ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
					/**
					 * Command-line mode, no real requests made,
					 * try to switch context in-request.
					 */
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

		if ( ! is_admin() && ! is_null( $this->frontend_notice ) ) {
			$bg_color = '#2271b1';

			if ( 'error' === $this->frontend_notice['type'] ) {
				$bg_color = '#d63638';
			} elseif ( 'note' === $this->frontend_notice['type'] ) {
				$bg_color = '#00a32a';
			}

			$css .= "
				#wp-admin-bar-w3tc_frontend_notice > .ab-item {
					background: $bg_color !important;
					color: #fff !important;
					font-weight: 600;
					display: flex;
					align-items: center;
					gap: 0.5em;
				}
				#wp-admin-bar-w3tc_frontend_notice .w3tc-frontend-notice-dismiss {
					border-left: 1px solid rgba(255,255,255,0.4);
					margin-left: 0.5em;
					padding-left: 0.5em;
					font-size: 18px;
					line-height: 1;
					font-weight: 700;
					cursor: pointer;
				}
				#wp-admin-bar-w3tc_frontend_notice .w3tc-frontend-notice-dismiss:focus {
					outline: 2px solid rgba(255,255,255,0.8);
					outline-offset: 2px;
				}";
		}

		wp_add_inline_style( 'admin-bar', $css );

		if ( ! is_admin() && ! is_null( $this->frontend_notice ) ) {
			$js = "(function() {
				var init = function() {
					var notice = document.getElementById('wp-admin-bar-w3tc_frontend_notice');
					if (!notice) {
						return;
					}
					var remove = function() {
						if (notice && notice.parentNode) {
							notice.parentNode.removeChild(notice);
							notice = null;
						}
					};
					var dismiss = notice.querySelector('.w3tc-frontend-notice-dismiss');
					if (dismiss) {
						dismiss.addEventListener('click', function(event) {
							event.preventDefault();
							remove();
						});
					}
				};
				if (document.readyState === 'loading') {
					document.addEventListener('DOMContentLoaded', init);
				} else {
					init();
				}
			})();";
			wp_add_inline_script( 'admin-bar', $js );
		}
	}

	/**
	 * Admin bar menu
	 *
	 * @return void
	 */
	public function admin_bar_menu() {
		global $wp_admin_bar;

		/**
		 * Hard floor at `manage_options` so the `w3tc_capability_admin_bar`
		 * filter cannot downgrade the admin bar to lower-capability users
		 * (consistent with the floor applied at every other
		 * `w3tc_capability_*` filter site).
		 */
		if ( ! \current_user_can( 'manage_options' ) ) {
			return;
		}

		$base_capability = apply_filters( 'w3tc_capability_admin_bar', 'manage_options' );

		if ( current_user_can( $base_capability ) ) {
			$w3tc_modules = Dispatcher::component( 'ModuleStatus' );

			$menu_items = array();

			$menu_items['00010.generic'] = array(
				'id'    => 'w3tc',
				'title' => sprintf(
					'<span class="w3tc-icon ab-icon"></span><span class="ab-label">%s</span>',
					__( 'Performance', 'w3-total-cache' )
				),
				'href'  => network_admin_url( 'admin.php?page=w3tc_dashboard' ),
			);

			$current_page = Util_Request::get_string( 'page', 'w3tc_dashboard' );

			if ( $w3tc_modules->plugin_is_enabled() ) {
				$menu_items['10010.generic'] = array(
					'id'     => 'w3tc_flush_all',
					'parent' => 'w3tc',
					'title'  => __( 'Purge All Caches', 'w3-total-cache' ),
					'href'   => Util_Nonce::admin_nonce_url(
						network_admin_url( 'admin.php?page=' . $current_page . '&w3tc_flush_all' ),
						'w3tc_flush_all'
					),
				);

				// Add menu item to flush all cached except Cloudflare.
				if (
					$this->_config->get_boolean( 'cdnfsd.enabled' ) &&
					'cloudflare' === $this->_config->get_string( 'cdnfsd.engine' ) &&
					Extension_CloudFlare_Api::are_api_credentials_usable(
						$this->_config->get_string( array( 'cloudflare', 'email' ) ),
						$this->_config->get_string( array( 'cloudflare', 'key' ) )
					) &&
					! empty( $this->_config->get_string( array( 'cloudflare', 'zone_id' ) ) ) &&
					in_array( 'cloudflare', array_keys( Extensions_Util::get_active_extensions( $this->_config ) ), true ) &&
					(
						$w3tc_modules->can_empty_memcache() ||
						$w3tc_modules->can_empty_opcode() ||
						$w3tc_modules->can_empty_file() ||
						$w3tc_modules->can_empty_varnish()
					)
				) {
					$menu_items['10015.generic'] = array(
						'id'     => 'w3tc_flush_all_except_cf',
						'parent' => 'w3tc',
						'title'  => __( 'Purge All Caches Except Cloudflare', 'w3-total-cache' ),
						'href'   => Util_Nonce::admin_nonce_url(
							network_admin_url( 'admin.php?page=w3tc_dashboard&amp;w3tc_cloudflare_flush_all_except_cf' ),
							'w3tc_cloudflare_flush_all_except_cf'
						),
					);
				}

				if ( ! is_admin() ) {
					$menu_items['10020.generic'] = array(
						'id'     => 'w3tc_flush_current_page',
						'parent' => 'w3tc',
						'title'  => __( 'Purge Current Page', 'w3-total-cache' ),
						'href'   => Util_Nonce::admin_nonce_url(
							admin_url( 'admin.php?page=w3tc_dashboard&amp;w3tc_flush_post&amp;post_id=' . Util_Environment::detect_post_id() . '&force=true' ),
							'w3tc_flush_post'
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
				'href'   => network_admin_url( 'admin.php?page=w3tc_feature_showcase' ),
			);

			$menu_items['40010.generic'] = array(
				'id'     => 'w3tc_settings_general',
				'parent' => 'w3tc',
				'title'  => __( 'General Settings', 'w3-total-cache' ),
				'href'   => network_admin_url( 'admin.php?page=w3tc_general' ),
			);

			if ( Extension_AlwaysCached_Plugin::is_enabled() ) {
				$menu_items['40015.alwayscached'] = array(
					'id'     => 'w3tc_alwayscached',
					'parent' => 'w3tc',
					'title'  => __( 'Page Cache Queue', 'w3-total-cache' ),
					'href'   => network_admin_url( 'admin.php?page=w3tc_extensions&extension=alwayscached&action=view' ),
				);
			}

			$menu_items['40020.generic'] = array(
				'id'     => 'w3tc_settings_extensions',
				'parent' => 'w3tc',
				'title'  => __( 'Manage Extensions', 'w3-total-cache' ),
				'href'   => network_admin_url( 'admin.php?page=w3tc_extensions' ),
			);

			$menu_items['40030.generic'] = array(
				'id'     => 'w3tc_settings_faq',
				'parent' => 'w3tc',
				'title'  => __( 'FAQ', 'w3-total-cache' ),
				'href'   => network_admin_url( 'admin.php?page=w3tc_faq' ),
			);

			$menu_items['60010.generic'] = array(
				'id'     => 'w3tc_support',
				'parent' => 'w3tc',
				'title'  => __( 'Support', 'w3-total-cache' ),
				'href'   => network_admin_url( 'admin.php?page=w3tc_support' ),
			);

			if ( defined( 'W3TC_DEBUG' ) && W3TC_DEBUG ) {
				$menu_items['90010.generic'] = array(
					'id'     => 'w3tc_debug_overlays',
					'parent' => 'w3tc',
					'title'  => __( 'Debug: Overlays', 'w3-total-cache' ),
				);
			}

			$menu_items = apply_filters( 'w3tc_admin_bar_menu', $menu_items );

			$w3tc_keys = array_keys( $menu_items );
			asort( $w3tc_keys );

			foreach ( $w3tc_keys as $w3tc_key ) {
				$capability = apply_filters(
					'w3tc_capability_admin_bar_' . $menu_items[ $w3tc_key ]['id'],
					$base_capability
				);

				if ( current_user_can( $capability ) ) {
					$wp_admin_bar->add_menu( $menu_items[ $w3tc_key ] );
				}
			}

			if ( ! is_admin() && ! is_null( $this->frontend_notice ) && ! empty( $this->frontend_notice['messages'] ) ) {
				$sanitized_messages = array_map( 'wp_strip_all_tags', $this->frontend_notice['messages'] );
				$w3tc_label         = esc_html( wp_html_excerpt( implode( ' ', $sanitized_messages ), 120, '…' ) );

				if ( '' !== $w3tc_label ) {
					$wp_admin_bar->add_menu(
						array(
							'id'     => 'w3tc_frontend_notice',
							'parent' => 'top-secondary',
							'title'  => $w3tc_label . '<span class="w3tc-frontend-notice-dismiss" role="button" aria-label="' . esc_attr__( 'Dismiss notice', 'w3-total-cache' ) . '">&times;</span>',
							'href'   => false,
							'meta'   => array(
								'class' => 'w3tc-frontend-notice w3tc-frontend-notice-' . $this->frontend_notice['type'],
								'title' => $w3tc_label,
							),
						)
					);
				}
			}
		}
	}

	/**
	 * Loads a pending frontend message triggered during an admin redirect.
	 *
	 * @since 2.8.14
	 *
	 * @return void
	 */
	public function load_frontend_message() {
		if ( is_admin() ) {
			return;
		}

		/**
		 * Restrict frontend-message replay to admin callers.
		 *
		 * `load_frontend_message()` reads and deletes the `w3tc_message`
		 * option that an admin-side redirect set, then renders the
		 * stored data as a frontend notice. Without this gate any
		 * visitor could trigger `delete_option('w3tc_message')` by
		 * guessing the message id, or read stored notice contents
		 *.
		 *
		 * @since 2.10.0
		 */
		if ( ! \current_user_can( 'manage_options' ) ) {
			return;
		}

		$message_id = Util_Request::get_string( 'w3tc_message' );
		if ( '' !== $message_id ) {
			$stored_messages = get_option( 'w3tc_message' );
			if ( is_array( $stored_messages ) && isset( $stored_messages[ $message_id ] ) ) {
				$w3tc_message = $stored_messages[ $message_id ];
				delete_option( 'w3tc_message' );

				$notice_type = '';
				$entries     = array();

				if ( isset( $w3tc_message['errors'] ) && is_array( $w3tc_message['errors'] ) ) {
					$entries = array_values( array_filter( $w3tc_message['errors'], 'strlen' ) );
					if ( ! empty( $entries ) ) {
						$notice_type = 'error';
					}
				}

				if ( '' === $notice_type && isset( $w3tc_message['notes'] ) && is_array( $w3tc_message['notes'] ) ) {
					$entries = array_values( array_filter( $w3tc_message['notes'], 'strlen' ) );
					if ( ! empty( $entries ) ) {
						$notice_type = 'note';
					}
				}

				if ( '' !== $notice_type && ! empty( $entries ) ) {
					$this->frontend_notice = array(
						'type'     => $notice_type,
						'messages' => $entries,
					);
				}
			}

			if ( ! is_null( $this->frontend_notice ) ) {
				return;
			}
		}

		$note_key = Util_Request::get_string( 'w3tc_note' );
		if ( '' === $note_key ) {
			return;
		}

		$note_messages = array(
			'flush_all'                 => __( 'All caches successfully emptied.', 'w3-total-cache' ),
			'flush_all_except_w3tc_cdn' => __( 'All caches successfully emptied except CDN.', 'w3-total-cache' ),
			'flush_memcached'           => __( 'Memcached cache(s) successfully emptied.', 'w3-total-cache' ),
			'flush_opcode'              => __( 'Opcode cache(s) successfully emptied.', 'w3-total-cache' ),
			'flush_file'                => __( 'Disk cache(s) successfully emptied.', 'w3-total-cache' ),
			'flush_pgcache'             => __( 'Page cache successfully emptied.', 'w3-total-cache' ),
			'flush_dbcache'             => __( 'Database cache successfully emptied.', 'w3-total-cache' ),
			'flush_objectcache'         => __( 'Object cache successfully emptied.', 'w3-total-cache' ),
			'flush_fragmentcache'       => __( 'Fragment cache successfully emptied.', 'w3-total-cache' ),
			'flush_minify'              => __( 'Minify cache successfully emptied.', 'w3-total-cache' ),
			'flush_browser_cache'       => __( 'Media Query string has been successfully updated.', 'w3-total-cache' ),
			'flush_varnish'             => __( 'Varnish servers successfully purged.', 'w3-total-cache' ),
			'flush_cdn'                 => __( 'CDN was successfully purged.', 'w3-total-cache' ),
			'pgcache_purge_post'        => __( 'Post successfully purged.', 'w3-total-cache' ),
		);

		if ( isset( $note_messages[ $note_key ] ) ) {
			$this->frontend_notice = array(
				'type'     => 'note',
				'messages' => array( $note_messages[ $note_key ] ),
			);
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
		$w3tc_theme_name = Util_Request::get_string( 'w3tc_theme' );

		$theme = Util_Theme::get( $w3tc_theme_name );

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
		$w3tc_theme_name = Util_Request::get_string( 'w3tc_theme' );

		$theme = Util_Theme::get( $w3tc_theme_name );

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

		global $w3tc_w3_late_caching_succeeded;
		if ( $w3tc_w3_late_caching_succeeded ) {
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
					$strings[] = 'Performance optimized by W3 Total Cache. Learn more: https://www.boldgrid.com/w3-total-cache/?utm_source=w3tc&utm_medium=footer_comment&utm_campaign=free_plugin';
					$strings[] = '';
				}

				$strings = (array) apply_filters( 'w3tc_footer_comment', $strings );

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
		global $w3tc_w3_late_init;
		if ( $w3tc_w3_late_init ) {
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
		 * Do not skip output buffering based on User-Agent: the value is client-controlled.
		 * A request claiming "W3 Total Cache" would previously bypass ob_callback, skipping
		 * page-cache processing and emitting W3TC_DYNAMIC_SECURITY in unprocessed mfunc/mclude.
		 */

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
				Util_Cookie::clear( 'w3tc_logged_' . Util_Cookie::role_cookie_name( $role ) );
				/**
				 * One-release back-compat: also clear the legacy MD5-named
				 * cookie so an upgrade doesn't leave a stale bypass-cookie
				 * behind. Drop this `clear()` call in the next release.
				 */
				Util_Cookie::clear( 'w3tc_logged_' . Util_Cookie::role_cookie_name_legacy( $role ) );
			}

			return;
		}

		if ( 'logged_in' !== $action ) {
			return;
		}

		foreach ( $roles as $role ) {
			if ( in_array( $role, $rejected_roles, true ) ) {
				Util_Cookie::set(
					'w3tc_logged_' . Util_Cookie::role_cookie_name( $role ),
					'1',
					$expire
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
	 * @since 2.2.9
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

	/**
	 * Registers Plugin Check filters so they run in all contexts.
	 *
	 * @since 2.8.13
	 *
	 * @link https://github.com/WordPress/plugin-check/blob/1.6.0/includes/Utilities/Plugin_Request_Utility.php#L160
	 * @link https://github.com/WordPress/plugin-check/blob/1.6.0/includes/Utilities/Plugin_Request_Utility.php#L180
	 * @link https://github.com/WordPress/plugin-check/blob/1.6.0/includes/Checker/Checks/Plugin_Repo/Plugin_Readme_Check.php#L928
	 *
	 * @return void
	 */
	private function register_plugin_check_filters(): void {
		// Ignore vendor packages and external library directories when running the plugin check plugin.
		add_filter(
			'wp_plugin_check_ignore_directories',
			static function ( array $dirs_to_ignore ) {
				return array_merge(
					$dirs_to_ignore,
					array(
						'.claude',
						'.cursor',
						'.github',
						'.vscode',
						'bin',
						'extension-example',
						'lib',
						'node_modules',
						'tests',
						'qa',
						'vendor',
					)
				);
			}
		);

		// Ignore specific files when running the plugin check plugin.
		add_filter(
			'wp_plugin_check_ignore_files',
			static function ( array $files_to_ignore ) {
				return array_merge(
					$files_to_ignore,
					array(
						'.editorconfig',
						'.gitattributes',
						'.gitignore',
						'.htaccess',
						'.jshintrc',
						'.phpunit.result.cache',
						'.travis.yml',
						'AGENTS.md',
						'CLAUDE.md',
						'codecov',
						'coverage.xml',
						'package.json',
						'package-lock.json',
						'phpcs.xml',
						'pub/sns.php',
						'yarn.lock',
					)
				);
			}
		);

		// Ignore specific warnings when running the plugin check plugin.
		add_filter(
			'wp_plugin_check_ignored_readme_warnings',
			static function ( array $ignored ) {
				return array_merge(
					$ignored,
					array(
						'trimmed_section_changelog',
					)
				);
			}
		);
	}
}
