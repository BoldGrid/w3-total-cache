<?php
/**
 * File: Generic_Plugin_Admin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Generic_Plugin_Admin
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class Generic_Plugin_Admin {
	/**
	 * Current page
	 *
	 * @var string
	 */
	private $_page = 'w3tc_dashboard';

	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config = null;

	/**
	 * Config
	 *
	 * @var bool
	 */
	private $is_w3tc_page;

	/**
	 * Message data (see Util_Admin::redirect*).
	 *
	 * @var array
	 */
	private $w3tc_message = null;

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
		$this->is_w3tc_page = Util_Admin::is_w3tc_admin_page();

		add_filter( 'w3tc_save_options', array( $this, 'w3tc_save_options' ) );

		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_init_w3tc_dashboard', array( '\W3TC\Generic_WidgetAccount', 'admin_init_w3tc_dashboard' ) );
		add_action( 'admin_init_w3tc_dashboard', array( '\W3TC\Generic_WidgetSettings', 'admin_init_w3tc_dashboard' ) );
		add_action( 'admin_init_w3tc_dashboard', array( '\W3TC\Generic_WidgetPartners', 'admin_init_w3tc_dashboard' ) );
		add_action( 'admin_init_w3tc_dashboard', array( '\W3TC\Generic_WidgetServices', 'admin_init_w3tc_dashboard' ) );
		add_action( 'admin_init_w3tc_dashboard', array( '\W3TC\Generic_WidgetBoldGrid', 'admin_init_w3tc_dashboard' ) );
		add_action( 'admin_init_w3tc_dashboard', array( '\W3TC\Extension_ImageService_Widget', 'admin_init_w3tc_dashboard' ) );

		// Pro widgets.
		if ( Util_Environment::is_w3tc_pro( $this->_config ) ) {
			add_action( 'admin_init_w3tc_dashboard', array( '\W3TC\Generic_WidgetStats', 'admin_init_w3tc_dashboard' ) );
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_print_styles-toplevel_page_w3tc_dashboard', array( '\W3TC\Generic_Page_Dashboard', 'admin_print_styles_w3tc_dashboard' ) );
		add_action( 'wp_ajax_w3tc_ajax', array( $this, 'wp_ajax_w3tc_ajax' ) );
		add_action( 'wp_ajax_w3tc_forums_api', array( $this, 'wp_ajax_w3tc_forums_api' ), 10, 1 );

		add_action( 'admin_head', array( $this, 'admin_head' ) );
		add_action( 'admin_footer', array( $this, 'admin_footer' ) );

		if ( is_network_admin() ) {
			add_action( 'network_admin_menu', array( $this, 'network_admin_menu' ) );
			add_filter( 'network_admin_plugin_action_links_' . W3TC_FILE, array( $this, 'plugin_action_links' ) );
			add_action( 'network_admin_notices', array( $this, 'top_nav_bar' ), 0 );
		} else {
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_filter( 'plugin_action_links_' . W3TC_FILE, array( $this, 'plugin_action_links' ) );
			add_action( 'admin_notices', array( $this, 'top_nav_bar' ), 0 );
		}

		add_filter( 'favorite_actions', array( $this, 'favorite_actions' ) );

		add_action( 'in_plugin_update_message-' . W3TC_FILE, array( $this, 'in_plugin_update_message' ) );

		if ( $this->_config->get_boolean( 'pgcache.enabled' ) || $this->_config->get_boolean( 'minify.enabled' ) ) {
			add_filter( 'pre_update_option_active_plugins', array( $this, 'pre_update_option_active_plugins' ) );
		}

		$config_labels = new Generic_ConfigLabels();
		add_filter( 'w3tc_config_labels', array( $config_labels, 'config_labels' ) );

		$admin_notes = new Generic_AdminNotes();
		add_filter( 'w3tc_notes', array( $admin_notes, 'w3tc_notes' ) );
		add_filter( 'w3tc_errors', array( $admin_notes, 'w3tc_errors' ), 1000 );

		add_action( 'w3tc_ajax_faq', array( $this, 'w3tc_ajax_faq' ) );

		// Load w3tc_message.
		$message_id = Util_Request::get_string( 'w3tc_message' );
		if ( $message_id ) {
			$v = get_option( 'w3tc_message' );

			if ( isset( $v[ $message_id ] ) ) {
				$this->w3tc_message = $v[ $message_id ];
				delete_option( 'w3tc_message' );
			}
		}

		// Run post-update tasks.
		$this->post_update_tasks();
	}

	/**
	 * Load action
	 *
	 * @return void
	 */
	public function load() {
		$this->add_help_tabs();
		$this->_page = Util_Admin::get_current_page();

		// Run plugin action.
		$action            = false;
		$display_only_keys = array(
			'w3tc_note',
			'w3tc_error',
			'w3tc_message',
			'w3tc_message_action',
		);

		$executor = new Root_AdminActions();

		/**
		 * Resolve the handler key from POST on form submissions so a stale
		 * `w3tc_*` query arg left on the URL cannot shadow the clicked submit
		 * button. Do not fall back to GET on POST requests — metabox saves and
		 * other non-W3TC POSTs must not inherit a GET-side action key. Admin-bar
		 * / nonce-link actions remain GET-driven.
		 */
		$request_sources = array();
		$request_method  = isset( $_SERVER['REQUEST_METHOD'] ) ? \strtoupper( (string) \wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Compared to a literal HTTP method only.
		if ( 'POST' === $request_method ) {
			$request_sources[] = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Action key discovery only; nonce verified before dispatch.
		} else {
			$request_sources[] = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET admin-action links carry the handler key here.
		}

		foreach ( $request_sources as $request_source ) {
			foreach ( $request_source as $w3tc_key => $w3tc_value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,Generic.CodeAnalysis.UnusedFunctionParameter.Found
				if ( 'w3tc_' !== \substr( $w3tc_key, 0, 5 ) ) {
					continue;
				}

				if ( \in_array( $w3tc_key, $display_only_keys, true ) ) {
					continue;
				}

				if ( $executor->is_dispatchable( $w3tc_key ) ) {
					$action = $w3tc_key;
					break 2;
				}
			}
		}

		if ( $action ) {
			/**
			 * Per-action nonce key for the admin-action dispatcher.
			 *
			 * @since 2.10.0
			 */
			if ( ! Util_Nonce::verify_admin( Util_Nonce::admin_action( $action ) ) ) {
				wp_nonce_ays( Util_Nonce::admin_action( $action ) );
			}

			/**
			 * Defence-in-depth: enforce manage_options at the dispatcher
			 * regardless of how the menu was registered or how
			 * w3tc_capability_menu was filtered.
			 *
			 * @since 2.10.0
			 */
			if ( ! \current_user_can( 'manage_options' ) ) {
				wp_die(
					\esc_html__( 'You do not have sufficient permissions to perform this action.', 'w3-total-cache' ),
					'',
					array( 'response' => 403 )
				);
			}

			try {
				$executor->execute( $action );
			} catch ( \Exception $e ) {
				$w3tc_key = 'admin_action_failed_' . $action;
				Util_Admin::redirect_with_custom_messages( array(), array( $w3tc_key => $e->getMessage() ) );
			}

			exit();
		}
	}

	/**
	 * Save settings handler.
	 *
	 * @param array $w3tc_data Data.
	 *
	 * @return array
	 */
	public function w3tc_save_options( $w3tc_data ) {
		$new_config = $w3tc_data['new_config'];
		$old_config = $w3tc_data['old_config'];

		// Schedule purge if enabled.
		if ( $new_config->get_boolean( 'allcache.wp_cron' ) ) {
			$new_wp_cron_time      = $new_config->get_integer( 'allcache.wp_cron_time' );
			$old_wp_cron_time      = $old_config ? $old_config->get_integer( 'allcache.wp_cron_time' ) : -1;
			$new_wp_cron_interval  = $new_config->get_string( 'allcache.wp_cron_interval' );
			$old_wp_cron_interval  = $old_config ? $old_config->get_string( 'allcache.wp_cron_interval' ) : -1;
			$schedule_needs_update = $new_wp_cron_time !== $old_wp_cron_time || $new_wp_cron_interval !== $old_wp_cron_interval;

			// Clear the scheduled hook if a change in time or interval is detected.
			if ( wp_next_scheduled( 'w3tc_purge_all_wpcron' ) && $schedule_needs_update ) {
				wp_clear_scheduled_hook( 'w3tc_purge_all_wpcron' );
			}

			// Schedule if no existing cron event or settings have changed.
			if ( ! wp_next_scheduled( 'w3tc_purge_all_wpcron' ) || $schedule_needs_update ) {
				$scheduled_timestamp_server = Util_Environment::get_cron_schedule_time( $new_wp_cron_time );
				wp_schedule_event( $scheduled_timestamp_server, $new_wp_cron_interval, 'w3tc_purge_all_wpcron' );
			}
		} elseif ( wp_next_scheduled( 'w3tc_purge_all_wpcron' ) ) {
			wp_clear_scheduled_hook( 'w3tc_purge_all_wpcron' );
		}

		return $w3tc_data;
	}

	/**
	 * Load action — generic W3TC admin AJAX dispatcher.
	 *
	 * The prior implementation wrapped both the capability check and
	 * the handler dispatch in a single try/catch that echoed
	 * `$e->getMessage()` with a 200 status. Authorization
	 * failures looked indistinguishable from success on the wire
	 * (HTTP 200 + body `"no permissions"`), and the underlying audit
	 * event never reached any log. Now:
	 *
	 *  * Nonce failure → `w3tc_audit_log` (`nonce_failed`) +
	 *    `wp_nonce_ays` (which emits 403 / dies).
	 *  * Capability failure → `w3tc_audit_log` (`cap_denied`) +
	 *    `wp_send_json_error( ..., 403 )` (matches the pattern
	 *    already used by `wp_ajax_w3tc_forums_api` below).
	 *  * Handler exception → `w3tc_audit_log` (`handler_exception`)
	 *    + `Util_Debug::log` (sanitized) + `wp_send_json_error(
	 *    ..., 500 )`. The exception body never reaches the client
	 *    verbatim; only a stable generic message does.
	 *
	 * @return void
	 */
	public function wp_ajax_w3tc_ajax() {
		$action      = Util_Request::get_string( 'w3tc_action' );
		$ajax_action = 'w3tc_ajax_' . $action;

		if ( ! Util_Nonce::verify_admin( $ajax_action ) ) {
			Util_Debug::audit_log(
				'nonce_failed',
				array(
					'handler' => 'wp_ajax_w3tc_ajax',
					'action'  => $action,
				)
			);
			wp_nonce_ays( $ajax_action );
		}

		if ( ! \current_user_can( 'manage_options' ) ) {
			Util_Debug::audit_log(
				'cap_denied',
				array(
					'handler'    => 'wp_ajax_w3tc_ajax',
					'action'     => $action,
					'capability' => 'manage_options',
				)
			);
			wp_send_json_error( 'no permissions', 403 );
		}

		$base_capability = apply_filters( 'w3tc_ajax_base_capability_', 'manage_options' );
		$capability      = apply_filters( 'w3tc_ajax_capability_' . $action, $base_capability );

		if ( empty( $capability ) || ! \current_user_can( $capability ) ) {
			Util_Debug::audit_log(
				'cap_denied',
				array(
					'handler'    => 'wp_ajax_w3tc_ajax',
					'action'     => $action,
					'capability' => $capability,
				)
			);
			wp_send_json_error( 'no permissions', 403 );
		}

		try {
			do_action( 'w3tc_ajax' );
			do_action( 'w3tc_ajax_' . $action );
		} catch ( \Exception $e ) {
			Util_Debug::audit_log(
				'handler_exception',
				array(
					'handler' => 'wp_ajax_w3tc_ajax',
					'action'  => $action,
					'class'   => get_class( $e ),
					'message' => $e->getMessage(),
				)
			);
			/**
			 * Route the raw exception message through `redact()` before
			 * it lands in `admin-ajax.log`: SDK exceptions can embed
			 * request parameters (`_wpnonce=`, `token=`, `Authorization:
			 * Bearer …`, `define('AUTH_KEY', '…')`) directly in the
			 * message string. `Util_Debug::log()` only strips control
			 * characters and `<>`; it does NOT redact secrets, so a
			 * raw `$e->getMessage()` could persist sensitive values
			 * even though the audit-side context map is already
			 * redacted.
			 */
			Util_Debug::log( 'admin-ajax', 'exception in ' . $action . ': ' . Util_Debug::redact( $e->getMessage() ) );
			wp_send_json_error( 'handler error', 500 );
		}

		exit();
	}

	/**
	 * Forums API Callback
	 *
	 * This function reached out to the W3TC forums API to get the posts with the corresponding cache tag
	 * on boldgrid.com/support.
	 *
	 * @return void
	 */
	public function wp_ajax_w3tc_forums_api() {
		/**
		 * Per-action nonce key. Accepts the legacy `'w3tc'`
		 * action via Util_Nonce as a back-compat fallback.
		 *
		 * @since 2.10.0
		 */
		if ( ! Util_Nonce::verify_admin( 'w3tc_forums_api' ) ) {
			wp_nonce_ays( 'w3tc_forums_api' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'no permissions', 403 );
		}

		$tag = Util_Request::get_string( 'tabId' );

		/**
		 * $tag is concatenated into the forums API URL as a path / query
		 * segment. Even though the host (`W3TC_BOLDGRID_FORUM_API`) is
		 * fixed, an unprivileged caller who reaches this handler with
		 * a valid admin nonce could submit `../../`, query-string-shaped,
		 * or scheme-relative values that would otherwise change the
		 * effective request target. Restrict to a conservative alphabet
		 * that matches the documented `tabId` shape.
		 */
		if ( '' === $tag || ! preg_match( '/^[A-Za-z0-9._-]+$/', $tag ) ) {
			wp_send_json_error( 'invalid tabId', 400 );
		}

		$posts = wp_remote_get( W3TC_BOLDGRID_FORUM_API . $tag, array( 'timeout' => 10 ) );

		wp_send_json( $posts );
	}

	/**
	 * Admin init (administrators only).
	 */
	public function admin_init() {
		if ( ! \user_can( \get_current_user_id(), 'manage_options' ) ) {
			return;
		}

		// Special handling for deactivation link, it's plugins.php file.
		if ( 'w3tc_deactivate_plugin' === Util_Request::get_string( 'action' ) ) {
			if ( ! Util_Nonce::verify_admin( Util_Nonce::admin_action( 'w3tc_deactivate_plugin' ) ) ) {
				wp_nonce_ays( Util_Nonce::admin_action( 'w3tc_deactivate_plugin' ) );
			}

			Util_Activation::deactivate_plugin();
		}

		/**
		 * These have been moved here as the admin_print_scripts-{$suffix} hook with translations won't take the user locale setting
		 * into account if it's called too soon, resulting in JS not loading.
		 *
		 * Translations are needed as the "prefix" used is based on the menu/page title, which is translated (11+ year old WP bug).
		 */

		// Support page.
		add_action(
			'admin_print_scripts-' . sanitize_title( __( 'Performance', 'w3-total-cache' ) ) . '_page_w3tc_support',
			array( '\W3TC\Support_Page', 'admin_print_scripts_w3tc_support' )
		);

		// Minify.
		add_action(
			'admin_print_scripts-' . sanitize_title( __( 'Performance', 'w3-total-cache' ) ) . '_page_w3tc_general',
			array( '\W3TC\Minify_Plugin_Admin', 'admin_print_scripts_w3tc_general' )
		);

		// PageCache.
		add_action(
			'admin_print_scripts-' . sanitize_title( __( 'Performance', 'w3-total-cache' ) ) . '_page_w3tc_pgcache',
			array( '\W3TC\PgCache_Page', 'admin_print_scripts_w3tc_pgcache' )
		);

		// Extensions.
		add_action(
			'admin_print_scripts-' . sanitize_title( __( 'Performance', 'w3-total-cache' ) ) . '_page_w3tc_extensions',
			array( '\W3TC\Extension_CloudFlare_Page', 'admin_print_scripts_performance_page_w3tc_cdn' )
		);

		// Usage Statistics.
		add_action(
			'admin_print_scripts-' . sanitize_title( __( 'Performance', 'w3-total-cache' ) ) . '_page_w3tc_stats',
			array( '\W3TC\UsageStatistics_Page', 'admin_print_scripts_w3tc_stats' )
		);

		$w3tc_c = Dispatcher::config();

		// CDN.
		switch ( $w3tc_c->get_string( 'cdn.engine' ) ) {
			case 'bunnycdn':
				$cdn_class = '\W3TC\Cdn_BunnyCdn_Page';
				break;

			case 'google_drive':
				$cdn_class = '\W3TC\Cdn_GoogleDrive_Page';
				break;

			case 'rackspace_cdn':
				$cdn_class = '\W3TC\Cdn_RackSpaceCdn_Page';
				break;

			case 'rscf':
				$cdn_class = '\W3TC\Cdn_RackSpaceCloudFiles_Page';
				break;

			default:
				break;
		}

		if ( ! empty( $cdn_class ) ) {
			add_action(
				'admin_print_scripts-' . sanitize_title( __( 'Performance', 'w3-total-cache' ) ) . '_page_w3tc_cdn',
				array( $cdn_class, 'admin_print_scripts_w3tc_cdn' )
			);
		}

		// CDNFSD.
		switch ( $w3tc_c->get_string( 'cdnfsd.engine' ) ) {
			case 'bunnycdn':
				$cdnfsd_class = '\W3TC\Cdnfsd_BunnyCdn_Page';
				break;

			case 'cloudflare':
				$cdnfsd_class = '\W3TC\Extension_CloudFlare_Page';
				break;

			case 'cloudfront':
				$cdnfsd_class = '\W3TC\Cdnfsd_CloudFront_Page';
				break;

			default:
				break;
		}

		if ( ! empty( $cdnfsd_class ) ) {
			add_action(
				'admin_print_scripts-' . sanitize_title( __( 'Performance', 'w3-total-cache' ) ) . '_page_w3tc_cdn',
				array( $cdnfsd_class, 'admin_print_scripts_performance_page_w3tc_cdn' )
			);
		}

		// PageSpeed page/widget.
		add_action(
			'admin_print_scripts-' . sanitize_title( __( 'Performance', 'w3-total-cache' ) ) . '_page_w3tc_pagespeed',
			array( '\W3TC\PageSpeed_Page', 'admin_print_scripts_w3tc_pagespeed' )
		);
		add_action(
			'admin_print_scripts-toplevel_page_w3tc_dashboard',
			array( '\W3TC\PageSpeed_Widget', 'admin_print_scripts_w3tc_pagespeed_widget' )
		);

		$page_val = Util_Request::get_string( 'page' );
		if ( ! empty( $page_val ) ) {
			do_action( 'admin_init_' . $page_val ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		}
	}

	/**
	 * Enqueue admin scripts (administrators only).
	 */
	public function admin_enqueue_scripts() {
		if ( ! \user_can( \get_current_user_id(), 'manage_options' ) ) {
			return;
		}

		wp_register_style( 'w3tc-options', plugins_url( 'pub/css/options.css', W3TC_FILE ), array(), W3TC_VERSION );
		wp_register_style( 'w3tc-lightbox', plugins_url( 'pub/css/lightbox.css', W3TC_FILE ), array(), W3TC_VERSION );
		wp_register_style( 'w3tc-bootstrap-css', plugins_url( 'pub/css/bootstrap-buttons.css', W3TC_FILE ), array(), W3TC_VERSION );
		wp_register_style( 'w3tc-widget', plugins_url( 'pub/css/widget.css', W3TC_FILE ), array(), W3TC_VERSION );

		wp_register_script( 'w3tc-metadata', plugins_url( 'pub/js/metadata.js', W3TC_FILE ), array(), W3TC_VERSION, false );
		// Registered in load_scripts() so dependents can list w3tc-nonce; maps are localized in admin_print_scripts().
		wp_register_script( 'w3tc-nonce', plugins_url( 'pub/js/w3tc-nonce.js', W3TC_FILE ), array( 'jquery' ), W3TC_VERSION, true );
		wp_register_script( 'w3tc-options', plugins_url( 'pub/js/options.js', W3TC_FILE ), array( 'jquery', 'w3tc-nonce' ), W3TC_VERSION, false );
		wp_register_script( 'w3tc-lightbox', plugins_url( 'pub/js/lightbox.js', W3TC_FILE ), array( 'jquery', 'w3tc-nonce' ), W3TC_VERSION, false );
		wp_register_script( 'w3tc-widget', plugins_url( 'pub/js/widget.js', W3TC_FILE ), array( 'jquery', 'w3tc-nonce' ), W3TC_VERSION, false );
		wp_register_script( 'w3tc-jquery-masonry', plugins_url( 'pub/js/jquery.masonry.min.js', W3TC_FILE ), array( 'jquery' ), W3TC_VERSION, false );

		// New feature count for the Feature Showcase.
		wp_register_script( 'w3tc-feature-counter', plugins_url( 'pub/js/feature-counter.js', W3TC_FILE ), array(), W3TC_VERSION, true );

		wp_localize_script(
			'w3tc-feature-counter',
			'W3TCFeatureShowcaseData',
			array( 'unseenCount' => FeatureShowcase_Plugin_Admin::get_unseen_count() )
		);

		wp_enqueue_script( 'w3tc-feature-counter' );

		// Conditional loading for the exit survey on the plugins page.
		$current_screen = get_current_screen();
		if ( isset( $current_screen->id ) && 'plugins' === $current_screen->id ) {
			wp_enqueue_style( 'w3tc-exit-survey', plugins_url( 'pub/css/exit-survey.css', W3TC_FILE ), array(), W3TC_VERSION, false );
			wp_register_script( 'w3tc-exit-survey', plugins_url( 'pub/js/exit-survey.js', W3TC_FILE ), array( 'w3tc-nonce' ), W3TC_VERSION, false );
			wp_localize_script(
				'w3tc-exit-survey',
				'w3tcData',
				array(
					'nonces' => Util_Nonce::create_ajax_map(
						array(
							'exit_survey_render',
							'exit_survey_submit',
							'exit_survey_skip',
						)
					),
				)
			);
			wp_enqueue_script( 'w3tc-exit-survey' );

			wp_enqueue_style( 'w3tc-lightbox' );
			wp_enqueue_script( 'w3tc-lightbox' );
		}

		// Messages.
		if ( ! is_null( $this->w3tc_message ) && isset( $this->w3tc_message['actions'] ) && is_array( $this->w3tc_message['actions'] ) ) {
			foreach ( $this->w3tc_message['actions'] as $action ) {
				do_action( 'w3tc_message_action_' . $action );
			}
		}

		// For testing.
		$w3tc_message_action_val = Util_Request::get_string( 'w3tc_message_action' );
		if ( ! empty( $w3tc_message_action_val ) ) {
			do_action( 'w3tc_message_action_' . $w3tc_message_action_val );
		}
	}

	/**
	 * Render sticky top navigation bar on all W3TC admin pages (administrators only).
	 */
	public function top_nav_bar() {
		if ( \user_can( \get_current_user_id(), 'manage_options' ) && Util_Admin::is_w3tc_admin_page() ) {
			require W3TC_INC_DIR . '/options/common/top_nav_bar.php';
		}
	}

	/**
	 * Define icon styles for the custom post type (administrators only).
	 *
	 * @throws \Exception Exception.
	 */
	public function admin_head() {
		if ( ! \user_can( \get_current_user_id(), 'manage_options' ) ) {
			return;
		}

		global $wp_version;
		global $wpdb;

		// Attempt to get the 'page' parameter from the request.
		$page = Util_Request::get_string( 'page', null );

		// If 'page' is null or an empty string, fallback to current screen ID.
		if ( empty( $page ) ) {
			$page = get_current_screen()->id ?? null;
		}

		if ( ( ! is_multisite() || is_super_admin() ) && false !== strpos( $page, 'w3tc' ) && 'w3tc_setup_guide' !== $page && ! get_site_option( 'w3tc_setupguide_completed' ) ) {
			$state_master = Dispatcher::config_state_master();

			if ( ! $this->_config->get_boolean( 'pgcache.enabled' ) && $state_master->get_integer( 'common.install' ) > strtotime( 'NOW - 1 WEEK' ) ) {
				wp_safe_redirect( esc_url( network_admin_url( 'admin.php?page=w3tc_setup_guide' ) ) );
				exit;
			}
		}

		if ( empty( $this->_config->get_integer( 'pgcache.migrated.qsexempts' ) ) ) {
			$pgcache_accept_qs = array_unique( array_merge( $this->_config->get_array( 'pgcache.accept.qs' ), PgCache_QsExempts::get_qs_exempts() ) );
			sort( $pgcache_accept_qs );
			$this->_config->set( 'pgcache.accept.qs', $pgcache_accept_qs );
			$this->_config->set( 'pgcache.migrated.qsexempts', time() );

			// Save the config if the environment is ready; filesystem needs to be writable.
			try {
				$this->_config->save();
			} catch ( \Exception $e ) {
				$this->_config->set( 'pgcache.migrated.qsexempts', null );
			}
		}

		if ( $this->_config->get_boolean( 'common.track_usage' ) && ( $this->is_w3tc_page || 'plugins' === $page ) ) {
			$current_user = wp_get_current_user();
			$page         = Util_Request::get_string( 'page' );
			if ( 'w3tc_extensions' === $page ) {
				$page = 'extensions/' . Util_Request::get_string( 'extension' );
			}

			if ( defined( 'W3TC_DEVELOPER' ) && W3TC_DEVELOPER ) {
				$profile = 'G-Q3CHQJWERM';
			} else {
				$profile = 'G-5TFS8M5TTY';
			}

			$state = Dispatcher::config_state();

			wp_enqueue_script(
				'w3tc_ga',
				'https://www.googletagmanager.com/gtag/js?id=' . esc_attr( $profile ),
				array(),
				W3TC_VERSION,
				false
			);
			?>
			<script type="application/javascript">
				window.dataLayer = window.dataLayer || [];

				function w3tc_ga(){dataLayer.push(arguments);}

				w3tc_ga('js', new Date());

				w3tc_ga('config', '<?php echo esc_attr( $profile ); ?>', {
					'user_properties': {
						'plugin': 'w3-total-cache',
						'w3tc_version': '<?php echo esc_html( W3TC_VERSION ); ?>',
						'wp_version': '<?php echo esc_html( $wp_version ); ?>',
						'php_version': 'php<?php echo esc_html( phpversion() ); ?>',
						'server_software': '<?php echo esc_attr( isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '' ); ?>',
						'wpdb_version': 'mysql<?php echo esc_attr( $wpdb->db_version() ); ?>',
						'home_url': '<?php echo esc_url( Util_Environment::home_url_host() ); ?>',
						'w3tc_install_version': '<?php echo esc_attr( $state->get_string( 'common.install_version' ) ); ?>',
						'w3tc_edition': '<?php echo esc_attr( Util_Environment::w3tc_edition( $this->_config ) ); ?>',
						'w3tc_widgets': '<?php echo esc_attr( Util_Widget::list_widgets() ); ?>',
						'page': '<?php echo esc_attr( $page ); ?>',
						'w3tc_install_date': '<?php echo esc_attr( get_option( 'w3tc_install_date' ) ); ?>',
						'w3tc_pro': '<?php echo Util_Environment::is_w3tc_pro( $this->_config ) ? 1 : 0; ?>',
						'w3tc_has_key': '<?php $this->_config->get_string( 'plugin.license_key' ) ? 1 : 0; ?>',
						'w3tc_pro_c': '<?php echo defined( 'W3TC_PRO' ) && W3TC_PRO ? 1 : 0; ?>',
						'w3tc_enterprise_c': '<?php echo defined( 'W3TC_ENTERPRISE' ) && W3TC_ENTERPRISE ? 1 : 0; ?>',
						'w3tc_plugin_type': '<?php echo esc_attr( $this->_config->get_string( 'plugin.type' ) ); ?>',
					}
				});

				function getGACookie() {
					const match = document.cookie.match(/_ga=([^;]+)/);
					if (match) {
						const parts = match[1].split('.');
						if (parts.length > 2) {
							return parts[2] + '.' + parts[3];
						}
					}
					console.error('GA cookie not found or not set yet.');
					return null;
				}

				const w3tc_ga_cid = getGACookie();

				// Track clicks on W3TC Pro Services tab.
				document.addEventListener('click', function(event) {
					if (event.target.getAttribute('data-tab-type')) {
						w3tc_ga('event', 'click', {
							'eventCategory': event.target.closest('.postbox-tabs').getAttribute('id'),
							'eventLabel': event.target.getAttribute('data-tab-type'),
						});
					}
				});
			</script>
			<?php
		}

		?>
		<style type="text/css" media="screen">
			li.toplevel_page_w3tc_dashboard .wp-menu-image:before{
				content:'\0041';
				top: 2px;
				font-family: 'w3tc';
			}
		</style>
		<script>
			jQuery(document).ready( function($) {
				$('#toplevel_page_w3tc_dashboard ul li').find('a[href*="w3tc_faq"]')
					.prop('target','_blank')
					.prop('href', <?php echo wp_json_encode( W3TC_FAQ_URL ); ?>);
			} );
		</script>
		<?php
	}

	/**
	 * Defines the W3TC footer
	 */
	public function admin_footer() {
		if ( \user_can( \get_current_user_id(), 'manage_options' ) && $this->is_w3tc_page ) {
			require W3TC_INC_DIR . '/options/common/footer.php';
		}
	}

	/**
	 * Render network admin menu.
	 */
	public function network_admin_menu() {
		$this->_admin_menu( 'manage_network_options' );
	}

	/**
	 * Render admin menu.
	 */
	public function admin_menu() {
		$this->_admin_menu( 'manage_options' );
	}

	/**
	 * Admin menu
	 *
	 * @param string $base_capability Base compatibility.
	 *
	 * @return void
	 */
	private function _admin_menu( $base_capability ) {
		$base_capability = apply_filters( 'w3tc_capability_menu', $base_capability );

		/**
		 * Floor the filtered menu capability at manage_options. The
		 * w3tc_capability_menu filter remains for back-compat but cannot
		 * lower the menu/dispatcher gate below manage_options
		 *.
		 *
		 * @since 2.10.0
		 */
		if ( ! \current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( current_user_can( $base_capability ) ) {
			$menus         = Dispatcher::component( 'Root_AdminMenu' );
			$submenu_pages = $menus->generate( $base_capability );

			/**
			 * Only admin can modify W3TC settings
			 */
			foreach ( $submenu_pages as $submenu_page ) {
				add_action( 'load-' . $submenu_page, array( $this, 'load' ) );
				add_action( 'admin_print_styles-' . $submenu_page, array( $this, 'admin_print_styles' ) );
				add_action( 'admin_print_scripts-' . $submenu_page, array( $this, 'admin_print_scripts' ) );
			}

			global $pagenow;
			if ( 'plugins.php' === $pagenow ) {
				add_action( 'admin_print_scripts', array( $this, 'load_plugins_page_js' ) );
				add_action( 'admin_print_styles', array( $this, 'print_plugins_page_css' ) );
			}

			global $pagenow;
			if ( 'plugins.php' === $pagenow || $this->is_w3tc_page ||
				! empty( Util_Request::get_string( 'w3tc_note' ) ) ||
				! empty( Util_Request::get_string( 'w3tc_error' ) ) ||
				! empty( Util_Request::get_string( 'w3tc_message' ) ) ) {

				// Only admin can see W3TC notices and errors.
				add_action( 'admin_notices', array( $this, 'admin_notices' ) );
				add_action( 'network_admin_notices', array( $this, 'admin_notices' ) );
			}

			global $pagenow;
			if ( ! $this->is_w3tc_page &&
				(
					! empty( Util_Request::get_string( 'w3tc_note' ) ) ||
					! empty( Util_Request::get_string( 'w3tc_error' ) ) ||
					! empty( Util_Request::get_string( 'w3tc_message' ) )
				)
			) {
				// This is needed for admin notice buttons displayed on non-w3tc pages after actions via admin top menu.
				add_action( 'admin_print_scripts-' . $pagenow, array( $this, 'admin_print_scripts' ) );
			}
		}
	}

	/**
	 * Print styles (administrators only).
	 *
	 * @return void
	 */
	public function admin_print_styles() {
		if ( ! \user_can( \get_current_user_id(), 'manage_options' ) ) {
			return;
		}

		wp_enqueue_style( 'w3tc-options' );
		wp_enqueue_style( 'w3tc-bootstrap-css' );
		wp_enqueue_style( 'w3tc-lightbox' );
	}

	/**
	 * Print scripts (administrators only).
	 */
	public function admin_print_scripts() {
		if ( ! \user_can( \get_current_user_id(), 'manage_options' ) ) {
			return;
		}

		wp_enqueue_script( 'w3tc-metadata' );
		wp_enqueue_script( 'w3tc-nonce' );

		wp_localize_script(
			'w3tc-nonce',
			'w3tc_ajax_nonces',
			Util_Nonce::create_ajax_map( Util_Nonce::known_ajax_actions() )
		);

		wp_localize_script(
			'w3tc-nonce',
			'w3tc_admin_nonces',
			Util_Nonce::create_admin_map(
				array(
					'w3tc_save_options',
					'w3tc_default_save_and_flush',
					'w3tc_flush_all',
					'w3tc_flush_pgcache',
					'w3tc_flush_browser_cache',
					'w3tc_flush_minify',
					'w3tc_flush_dbcache',
					'w3tc_flush_objectcache',
					'w3tc_flush_cdn',
					'w3tc_flush_fragmentcache',
					'w3tc_flush_varnish',
					'w3tc_cloudflare_flush',
					'w3tc_cloudflare_flush_all_except_cf',
					'w3tc_opcache_flush',
					'w3tc_cdn_purge_files',
					'w3tc_cdn_google_drive_auth_set',
					'w3tc_cdn_rackspace_cdn_domains_reload',
					'w3tc_save_new_relic',
					'w3tc_config_dbcluster_config_save',
					'w3tc_test_self',
					'w3tc_licensing_upgrade',
					'w3tc_licensing_buy_plugin',
					'w3tc_default_save_license_key',
					'w3tc_test_minify_recommendations',
					'w3tc_ustats_access_log_test',
					// Handlers invoked from dashboard AJAX/GET without an options-page form nonce field.
					'w3tc_test_memcached',
					'w3tc_test_redis',
					'w3tc_default_hide_note',
					'w3tc_default_config_state_master',
					'w3tc_config_import',
					'w3tc_config_export',
					'w3tc_config_reset',
					'w3tc_config_preview_enable',
					'w3tc_config_preview_disable',
					'w3tc_config_preview_deploy',
					'w3tc_alwayscached_process',
					'w3tc_alwayscached_empty',
					'w3tc_alwayscached_regenerate',
				)
			)
		);

		wp_enqueue_script( 'w3tc-options' );
		wp_enqueue_script( 'w3tc-lightbox' );

		if ( $this->is_w3tc_page ) {
			wp_localize_script(
				'w3tc-options',
				'w3tc_forums_api_nonce',
				array( wp_create_nonce( 'w3tc_forums_api' ) )
			);

			wp_localize_script(
				'w3tc-options',
				'w3tcData',
				array(
					'cdnEnabled'       => $this->_config->get_boolean( 'cdn.enabled' ),
					'cdnEngine'        => $this->_config->get_string( 'cdn.engine' ),
					'cdnFlushManually' => $this->_config->get_boolean(
						'cdn.flush_manually',
						Cdn_Util::get_flush_manually_default_override( $this->_config->get_string( 'cdn.engine' ) )
					),
					'cdnfsdEnabled'    => $this->_config->get_boolean( 'cdnfsd.enabled' ),
					'cdnfsdEngine'     => $this->_config->get_string( 'cdnfsd.engine' ),
					'cfWarning'        => wp_kses(
						sprintf(
							/* translators: 1: HTML opening a tag to docs.aws.amazon.com for invalidation payments, 2: HTML closing a tag followed by HTML line break tag, 4: HTML line break tag, 5: HTML opening a tag to purge CDN manually, 6: HTML closing a tag. */
							__(
								'Please see %1$sAmazon\'s CloudFront documentation -- Paying for file invalidation%2$sThe first 1,000 invalidation paths that you submit per month are free; you pay for each invalidation path over 1,000 in a month.%3$sYou can disable automatic purging by enabling %4$sOnly purge CDN manually%5$s.',
								'w3-total-cache'
							),
							'<a target="_blank" href="https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/Invalidation.html#PayingForInvalidation">',
							'</a>.<br/>',
							'<br/>',
							'<a href="' . esc_url( admin_url( 'admin.php?page=w3tc_cdn#advanced' ) ) . '">',
							'</a>'
						),
						array(
							'a'  => array(
								'target' => array(),
								'href'   => array(),
							),
							'br' => array(),
						)
					),
					'bunnyCdnWarning'  => esc_html__(
						'Bunny CDN should only be enabled as either a CDN for objects or full-site delivery, not both at the same time.  The CDN settings have been reverted.',
						'w3-total-cache'
					),
				)
			);
		}

		switch ( $this->_page ) {
			case 'w3tc_minify':
			case 'w3tc_cachegroups':
				wp_enqueue_script(
					'w3tc_cachegroups',
					plugins_url( 'CacheGroups_Plugin_Admin_View.js', W3TC_FILE ),
					array(
						'jquery',
						'jquery-ui-sortable',
					),
					W3TC_VERSION,
					true
				);
				// No break.
			case 'w3tc_userexperience':
				if ( UserExperience_Remove_CssJs_Extension::is_enabled() ) {
					wp_register_script( 'w3tc_remove_cssjs', plugins_url( 'UserExperience_Remove_CssJs_Page_View.js', W3TC_FILE ), array( 'jquery' ), W3TC_VERSION, true );

					wp_localize_script(
						'w3tc_remove_cssjs',
						'W3TCRemoveCssJsData',
						array(
							'lang' => array(
								'singlesPathDescription' => __( 'Enter the path of the CSS/JS file to be managed. If a directory is used, all CSS/JS files within that directory will be managed with this entry.', 'w3-total-cache' ),
								'singlesExampleTrigger'  => __( 'View Examples', 'w3-total-cache' ),
								'singlesExampleTriggerClose' => __( 'Hide Examples', 'w3-total-cache' ),
								'singlesPathExampleDirLabel' => __( 'Target all CSS/JS from a plugin/theme:', 'w3-total-cache' ),
								'singlesPathExampleDir'  => wp_kses(
									'https://example.com/wp-content/plugins/example-plugin/<br/>/wp-content/plugins/example-plugin/',
									array(
										'br' => array(),
									)
								),
								'singlesPathExampleFileLabel' => __( 'Target a specific CSS/JS file:', 'w3-total-cache' ),
								'singlesPathExampleFile' => wp_kses(
									'https://example.com/wp-content/themes/example-theme/example-script.js<br/>/wp-content/themes/example-script.js<br/>example-script.js',
									array(
										'br' => array(),
									)
								),
								'singlesNoEntries'       => __( 'No CSS/JS entries added.', 'w3-total-cache' ),
								'singlesExists'          => __( 'Entry already exists!', 'w3-total-cache' ),
								'singlesPathLabel'       => __( 'Target CSS/JS:', 'w3-total-cache' ),
								'singlesDelete'          => __( 'Delete', 'w3-total-cache' ),
								'singlesBehaviorLabel'   => __( 'Action:', 'w3-total-cache' ),
								'singlesBehaviorExcludeText' => __( 'Exclude', 'w3-total-cache' ),
								'singlesBehaviorExcludeText2' => __( '(Remove the script ONLY WHEN a condition below matches)', 'w3-total-cache' ),
								'singlesBehaviorIncludeText' => __( 'Include', 'w3-total-cache' ),
								'singlesBehaviorIncludeText2' => __( '(Allow the script ONLY WHEN a condition below matches)', 'w3-total-cache' ),
								'singlesBehaviorDescription' => __( 'When the above CSS/JS file is found within your markup.', 'w3-total-cache' ),
								'singlesIncludesLabelExclude' => __( 'Exclude on URL Match:', 'w3-total-cache' ),
								'singlesIncludesLabelInclude' => __( 'Include on URL Match:', 'w3-total-cache' ),
								'singlesIncludesDescriptionExclude' => __( 'Specify the conditions for which the target file should be excluded based on matching absolute/relative page URLs. Include one entry per line.', 'w3-total-cache' ),
								'singlesIncludesDescriptionInclude' => __( 'Specify the conditions for which the target file should be included based on matching absolute/relative page URLs. Include one entry per line.', 'w3-total-cache' ),
								'singlesIncludesExample' => wp_kses(
									'https://example.com/example-page/<br/>/example-page/<br/>example-page?arg=example-arg',
									array(
										'br' => array(),
									)
								),
								'singlesIncludesContentLabelExclude' => __( 'Exclude on Content Match:', 'w3-total-cache' ),
								'singlesIncludesContentLabelInclude' => __( 'Include on Content Match:', 'w3-total-cache' ),
								'singlesIncludesContentDescriptionExclude' => __( 'Specify the conditions for which the target file should be excluded based on matching page content. Include one entry per line.', 'w3-total-cache' ),
								'singlesIncludesContentDescriptionInclude' => __( 'Specify the conditions for which the target file should be included based on matching page content. Include one entry per line.', 'w3-total-cache' ),
								'singlesIncludesContentExample' => wp_kses(
									'&lt;div id="example-id"&gt;<br/>&lt;span class="example-class"&gt;<br/>name="example-name"',
									array(
										'br' => array(),
									)
								),
								'singlesEmptyUrl'        => __( 'Empty match pattern!', 'w3-total-cache' ),
							),
						)
					);

					wp_enqueue_script( 'w3tc_remove_cssjs' );
				}
				// No break.
			case 'w3tc_cdn':
				wp_enqueue_script( 'jquery-ui-sortable' );
				break;
		}

		if ( 'w3tc_cdn' === $this->_page ) {
			wp_enqueue_script( 'jquery-ui-dialog' );
		}

		if ( 'w3tc_dashboard' === $this->_page ) {
			wp_enqueue_script( 'w3tc-jquery-masonry' );
		}
	}

	/**
	 * Load plugins page JS (administrators only).
	 */
	public function load_plugins_page_js() {
		if ( ! \user_can( \get_current_user_id(), 'manage_options' ) ) {
			return;
		}

		wp_enqueue_script( 'w3tc-options' );
	}

	/**
	 * Load plugins page CSS.
	 */
	public function print_plugins_page_css() {
		?>
		<style type="text/css">
			.w3tc-missing-files ul {
				margin-left: 20px;
				list-style-type: disc;
			}
			#w3tc {
				padding: 0;
			}
			#w3tc span {
				font-size: 0.6em;
				font-style: normal;
				text-shadow: none;
			}
			ul.w3tc-incomp-plugins, ul.w3-bullet-list {
				list-style: disc outside;
				margin-left: 17px;
				margin-top: 0;
				margin-bottom: 0;
			}
			ul.w3tc-incomp-plugins li div {
				width: 170px;
				display: inline-block;
			}
		</style>
		<?php
	}

	/**
	 * Contextual help list filter.
	 */
	public function add_help_tabs() {
		$w3tc_screen = get_current_screen();
		$sections    = Generic_Faq::sections();
		$n           = 0;

		foreach ( $sections as $section => $w3tc_data ) {
			$content = '<div class="w3tchelp_content" data-section="' . $section . '"></div>';

			$w3tc_screen->add_help_tab(
				array(
					'id'      => 'w3tc_faq_' . $n,
					'title'   => $section,
					'content' => $content,
				)
			);
			++$n;
		}
	}

	/**
	 * FAQ ajax handler.
	 */
	public function w3tc_ajax_faq() {
		$section = Util_Request::get_string( 'section' );
		$entries = Generic_Faq::parse( $section );

		ob_start();
		include W3TC_DIR . '/Generic_Plugin_Admin_View_Faq.php';
		$content = ob_get_contents();
		ob_end_clean();

		echo wp_json_encode( array( 'content' => $content ) );
	}

	/**
	 * Plugin action links filter
	 *
	 * @param array $w3tc_links Links array.
	 *
	 * @return array
	 */
	public function plugin_action_links( $w3tc_links ) {
		array_unshift( $w3tc_links, '<a class="edit" href="admin.php?page=w3tc_general">' . esc_html__( 'Settings', 'w3-total-cache' ) . '</a>' );
		array_unshift( $w3tc_links, '<a class="edit" style="color: red" href="admin.php?page=w3tc_support">' . esc_html__( 'Premium Support', 'w3-total-cache' ) . '</a>' );

		if ( ! is_writable( WP_CONTENT_DIR ) || ! is_writable( Util_Rule::get_browsercache_rules_cache_path() ) ) {
			$delete_link = '<a href="' .
				esc_url( Util_Nonce::admin_nonce_url( admin_url( 'plugins.php?action=w3tc_deactivate_plugin' ), 'w3tc_deactivate_plugin' ) ) .
				'">Uninstall</a>';
			array_unshift( $w3tc_links, $delete_link );
		}

		return $w3tc_links;
	}

	/**
	 * Favorite actions filter.
	 *
	 * @param array $actions Actions.
	 *
	 * @return array
	 */
	public function favorite_actions( $actions ) {
		/**
		 * Floor the filterable cap at manage_options so a downstream
		 * filter cannot expose the "Empty Caches" favorite action to
		 * non-admins.
		 *
		 * Early-return when the current user is not an admin: there is no
		 * reason to compute or filter a capability for an action we would
		 * never expose to them anyway.
		 *
		 * @since 2.10.0
		 */
		if ( ! \current_user_can( 'manage_options' ) ) {
			return $actions;
		}

		$capability = apply_filters( 'w3tc_capability_favorite_action_flush_all', 'manage_options' );
		if ( empty( $capability ) ) {
			$capability = 'manage_options';
		}

		$actions[ Util_Nonce::admin_nonce_url( admin_url( 'admin.php?page=w3tc_dashboard&amp;w3tc_flush_all' ), 'w3tc_flush_all' ) ] = array(
			__( 'Empty Caches', 'w3-total-cache' ),
			$capability,
		);

		return $actions;
	}

	/**
	 * Active plugins pre update option filter
	 *
	 * @param string $new_value New value.
	 *
	 * @return string
	 */
	public function pre_update_option_active_plugins( $new_value ) {
		$old_value = (array) get_option( 'active_plugins' );

		if ( $new_value !== $old_value && in_array( W3TC_FILE, (array) $new_value, true ) && in_array( W3TC_FILE, (array) $old_value, true ) ) {
			$state_note = Dispatcher::config_state_note();
			$state_note->set( 'common.show_note.plugins_updated', true );
		}

		return $new_value;
	}

	/**
	 * Show plugin changes
	 *
	 * @return void
	 */
	public function in_plugin_update_message() {
		$response = Util_Http::get( W3TC_README_URL );

		if ( is_wp_error( $response ) || 200 !== $response['response']['code'] ) {
			return;
		}

		$matches = null;
		$regexp  = '~==\s*Changelog\s*==\s*=\s*[0-9.]+\s*=(.*)(=\s*' . preg_quote( W3TC_VERSION, '~' ) . '\s*=|$)~Uis';

		$body = $response['body'];
		if ( ! preg_match( $regexp, $body, $matches ) ) {
			return;
		}

		$changelog = (array) preg_split( '~[\r\n]+~', trim( $matches[1] ) );

		echo '<div style="color: #f00;">' . esc_html__( 'Take a minute to update, here\'s why:', 'w3-total-cache' ) . '</div><div style="font-weight: normal;height:300px;overflow:auto">';
		$ul = false;

		foreach ( $changelog as $w3tc_index => $w3tc_line ) {
			if ( preg_match( '~^\s*\*\s*~', $w3tc_line ) ) {
				if ( ! $ul ) {
					echo '<ul style="list-style: disc; margin-left: 20px;margin-top:0;">';
					$ul = true;
				}
				$w3tc_line = preg_replace( '~^\s*\*\s*~', '', htmlspecialchars( $w3tc_line ) );
				echo '<li style="width: 50%; margin: 0; float: left; ' . ( 0 === $w3tc_index % 2 ? 'clear: left;' : '' ) . '">' . esc_html( $w3tc_line ) . '</li>';
			} elseif ( $ul ) {
				echo '</ul><div style="clear: left;"></div>';
				$ul = false;
			}
		}

		if ( $ul ) {
			echo '</ul><div style="clear: left;"></div>';
		}

		echo '</div>';
	}

	/**
	 * Admin notices action (administrators only).
	 *
	 * @return void
	 */
	public function admin_notices() {
		if ( ! \user_can( \get_current_user_id(), 'manage_options' ) ) {
			return;
		}

		$cookie_domain = Util_Admin::get_cookie_domain();

		$error_messages = array(
			'fancy_permalinks_disabled_pgcache'      => sprintf(
				// translators: 1 enable button link.
				__(
					'Fancy permalinks are disabled. Please %1$s it first, then re-attempt to enabling enhanced disk mode.',
					'w3-total-cache'
				),
				Util_Ui::button_link( 'enable', 'options-permalink.php' )
			),
			'fancy_permalinks_disabled_browsercache' => sprintf(
				// translators: 1 enable button link.
				__(
					'Fancy permalinks are disabled. Please %1$s it first, then re-attempt to enabling the \'Do not process 404 errors for static objects with WordPress\'.',
					'w3-total-cache'
				),
				Util_Ui::button_link( 'enable', 'options-permalink.php' )
			),
			'support_request_type'                   => __( 'Please select request type.', 'w3-total-cache' ),
			'support_request_url'                    => sprintf(
				// translators: 1 HTML acronym URL (uniform resource locator).
				__(
					'Please enter the address of the site in the site %1$s field.',
					'w3-total-cache'
				),
				'<acronym title="' . esc_attr__( 'Uniform Resource Locator', 'w3-total-cache' ) . '">' . esc_html__( 'URL', 'w3-total-cache' ) . '</acronym>'
			),
			'support_request_name'                   => __( 'Please enter your name in the Name field', 'w3-total-cache' ),
			'support_request_email'                  => __( 'Please enter valid email address in the E-Mail field.', 'w3-total-cache' ),
			'support_request_phone'                  => __( 'Please enter your phone in the phone field.', 'w3-total-cache' ),
			'support_request_subject'                => __( 'Please enter subject in the subject field.', 'w3-total-cache' ),
			'support_request_description'            => __( 'Please describe the issue in the issue description field.', 'w3-total-cache' ),
			'support_request_wp_login'               => __( 'Please enter an administrator login. Create a temporary one just for this support case if needed.', 'w3-total-cache' ),
			'support_request_wp_password'            => __( 'Please enter WP Admin password, be sure it\'s spelled correctly.', 'w3-total-cache' ),
			'support_request_ftp_host'               => sprintf(
				// translators: 1 HTML acronym SSH (secure shell), 2 HTML acronym FTP (file transfer protocol).
				__(
					'Please enter %1$s or %2$s host for the site.',
					'w3-total-cache'
				),
				'<acronym title="' . esc_attr__( 'Secure Shell', 'w3-total-cache' ) . '">' . esc_html__( 'SSH', 'w3-total-cache' ) . '</acronym>',
				'<acronym title="' . esc_attr__( 'File Transfer Protocol', 'w3-total-cache' ) . '">' . esc_html__( 'FTP', 'w3-total-cache' ) . '</acronym>'
			),
			'support_request_ftp_login'              => sprintf(
				// translators: 1 HTML acronym SSH (secure shell), 2 HTML acronym FTP (file transfer protocol).
				__(
					'Please enter %1$s or %2$s login for the server. Create a temporary one just for this support case if needed.',
					'w3-total-cache'
				),
				'<acronym title="' . esc_attr__( 'Secure Shell', 'w3-total-cache' ) . '">' . esc_html__( 'SSH', 'w3-total-cache' ) . '</acronym>',
				'<acronym title="' . esc_attr__( 'File Transfer Protocol', 'w3-total-cache' ) . '">' . esc_html__( 'FTP', 'w3-total-cache' ) . '</acronym>'
			),
			'support_request_ftp_password'           => sprintf(
				// translators: 1 HTML acronym SSH (secure shell), 2 HTML acronym FTP (file transfer protocol).
				__(
					'Please enter %1$s or %2$s password for the %2$s account.',
					'w3-total-cache'
				),
				'<acronym title="' . esc_attr__( 'Secure Shell', 'w3-total-cache' ) . '">' . esc_html__( 'SSH', 'w3-total-cache' ) . '</acronym>',
				'<acronym title="' . esc_attr__( 'File Transfer Protocol', 'w3-total-cache' ) . '">' . esc_html__( 'FTP', 'w3-total-cache' ) . '</acronym>'
			),
			'support_request'                        => sprintf(
				// translators: 1: HTML anchor open tag, 2: HTML anchor close tag.
				__( 'Unable to send the support request. Please %1$scontact support%2$s for assistance.', 'w3-total-cache' ),
				'<a href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_support' ) ) . '">',
				'</a>'
			),
			'config_import_no_file'                  => __( 'Please select config file.', 'w3-total-cache' ),
			'config_import_upload'                   => sprintf(
				// translators: 1: HTML anchor open tag, 2: HTML anchor close tag.
				__( 'Unable to upload config file. Please %1$scontact support%2$s for assistance.', 'w3-total-cache' ),
				'<a href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_support' ) ) . '">',
				'</a>'
			),
			'config_import_import'                   => sprintf(
				// translators: 1: HTML anchor open tag, 2: HTML anchor close tag.
				__( 'Configuration file could not be imported. Please %1$scontact support%2$s for assistance.', 'w3-total-cache' ),
				'<a href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_support' ) ) . '">',
				'</a>'
			),
			'config_reset'                           => sprintf(
				// translators: 1 W3TC config director path.
				__(
					'Default settings could not be restored. Please run %1$s to make the configuration file write-able, then try again.',
					'w3-total-cache'
				),
				'<strong>chmod 777 ' . W3TC_CONFIG_DIR . '</strong>'
			),
			'cdn_purge_attachment'                   => sprintf(
				// translators: 1: HTML anchor open tag, 2: HTML anchor close tag.
				__( 'Unable to purge attachment. Please %1$scontact support%2$s for assistance.', 'w3-total-cache' ),
				'<a href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_support' ) ) . '">',
				'</a>'
			),
			'pgcache_purge_post'                     => sprintf(
				// translators: 1: HTML anchor open tag, 2: HTML anchor close tag.
				__( 'Unable to purge post. Please %1$scontact support%2$s for assistance.', 'w3-total-cache' ),
				'<a href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_support' ) ) . '">',
				'</a>'
			),
			'enable_cookie_domain'                   => sprintf(
				// translators: 1 absolute path to wp-config.php, 2 cookie domain definition, 3 require once wp-setting.php definition.
				__(
					'%1$s could not be written, please edit config and add: %2$s before %3$s.',
					'w3-total-cache'
				),
				'<strong>' . ABSPATH . 'wp-config.php</strong>',
				'<br /><strong style="color:#f00;">define(\'COOKIE_DOMAIN\', \'' . addslashes( $cookie_domain ) . '\');</strong>',
				'<strong style="color:#f00;">require_once(ABSPATH . \'wp-settings.php\');</strong>'
			),
			'disable_cookie_domain'                  => sprintf(
				// translators: 1 absolute path to wp-config.php, 2 cooke domain definition, 3 require once wp-setting.php definition.
				__(
					'%1$s could not be written, please edit config and add:%2$s before %3$s.',
					'w3-total-cache'
				),
				'<strong>' . ABSPATH . 'wp-config.php</strong>',
				'<br /><strong style="color:#f00;">define(\'COOKIE_DOMAIN\', false);</strong>',
				'<strong style="color:#f00;">require_once(ABSPATH . \'wp-settings.php\');</strong>'
			),
			'pull_zone'                              => sprintf(
				// translators: 1: HTML anchor open tag, 2: HTML anchor close tag.
				__( 'Pull Zone could not be automatically created. Please %1$scontact support%2$s for assistance.', 'w3-total-cache' ),
				'<a href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_support' ) ) . '">',
				'</a>'
			),
		);

		$note_messages = array(
			'config_save'          => __( 'Plugin configuration successfully updated.', 'w3-total-cache' ),
			'config_save_flush'    => __( 'Plugin configuration successfully updated and all caches successfully emptied.', 'w3-total-cache' ),
			'flush_all'            => __( 'All caches successfully emptied.', 'w3-total-cache' ),
			'flush_memcached'      => __( 'Memcached cache(s) successfully emptied.', 'w3-total-cache' ),
			'flush_opcode'         => __( 'Opcode cache(s) successfully emptied.', 'w3-total-cache' ),
			'flush_file'           => __( 'Disk cache(s) successfully emptied.', 'w3-total-cache' ),
			'flush_pgcache'        => __( 'Page cache successfully emptied.', 'w3-total-cache' ),
			'flush_dbcache'        => __( 'Database cache successfully emptied.', 'w3-total-cache' ),
			'flush_objectcache'    => __( 'Object cache successfully emptied.', 'w3-total-cache' ),
			'flush_fragmentcache'  => __( 'Fragment cache successfully emptied.', 'w3-total-cache' ),
			'flush_minify'         => __( 'Minify cache successfully emptied.', 'w3-total-cache' ),
			'flush_browser_cache'  => __( 'Media Query string has been successfully updated.', 'w3-total-cache' ),
			'flush_varnish'        => __( 'Varnish servers successfully purged.', 'w3-total-cache' ),
			'flush_cdn'            => sprintf(
				// translators: 1 HTML acronym for CDN (content delivery network).
				__(
					'%1$s was successfully purged.',
					'w3-total-cache'
				),
				'<acronym title="' . esc_attr__( 'Content Delivery Network', 'w3-total-cache' ) . '">' . esc_html__( 'CDN', 'w3-total-cache' ) . '</acronym>'
			),
			'support_request'      => __( 'The support request has been successfully sent.', 'w3-total-cache' ),
			'config_import'        => __( 'Settings successfully imported.', 'w3-total-cache' ),
			'config_reset'         => __( 'Settings successfully restored.', 'w3-total-cache' ),
			'preview_enable'       => __( 'Preview mode was successfully enabled', 'w3-total-cache' ),
			'preview_disable'      => __( 'Preview mode was successfully disabled', 'w3-total-cache' ),
			'preview_deploy'       => __( 'Preview settings successfully deployed. Preview mode remains enabled until it\'s disabled. Continue testing new settings or disable preview mode if done.', 'w3-total-cache' ),
			'cdn_purge_attachment' => __( 'Attachment successfully purged.', 'w3-total-cache' ),
			'pgcache_purge_post'   => __( 'Post successfully purged.', 'w3-total-cache' ),
			'new_relic_save'       => __( 'New relic settings have been updated.', 'w3-total-cache' ),
			'add_in_removed'       => __( 'The add-in has been removed.', 'w3-total-cache' ),
			'enabled_edge'         => __( 'Edge mode has been enabled.', 'w3-total-cache' ),
			'disabled_edge'        => __( 'Edge mode has been disabled.', 'w3-total-cache' ),
			'pull_zone'            => __( 'Pull Zone was automatically created.', 'w3-total-cache' ),
		);

		$errors                    = array();
		$w3tc_notes                = array();
		$environment_error_present = false;

		$error = Util_Request::get_string( 'w3tc_error' );
		if ( isset( $error_messages[ $error ] ) ) {
			$errors[ $error ] = $error_messages[ $error ];
		}

		$w3tc_note = Util_Request::get_string( 'w3tc_note' );
		if ( isset( $note_messages[ $w3tc_note ] ) ) {
			$w3tc_notes[ $w3tc_note ] = $note_messages[ $w3tc_note ];
		}

		/**
		 * Print errors happened during last request execution,
		 * when we decided to redirect with error message instead of
		 * printing it directly (to avoid reexecution on refresh).
		 */
		if ( ! is_null( $this->w3tc_message ) ) {
			$v = $this->w3tc_message;
			if ( isset( $v['errors'] ) && is_array( $v['errors'] ) ) {
				foreach ( $v['errors'] as $error ) {
					if ( isset( $error_messages[ $error ] ) ) {
						$errors[] = $error_messages[ $error ];
					} else {
						$errors[] = $error;
					}
				}
			}
			if ( isset( $v['notes'] ) && is_array( $v['notes'] ) ) {
				foreach ( $v['notes'] as $w3tc_note ) {
					if ( isset( $note_messages[ $w3tc_note ] ) ) {
						$w3tc_notes[] = $note_messages[ $w3tc_note ];
					} else {
						$w3tc_notes[] = $w3tc_note;
					}
				}
			}
		}

		/*
		 * Filesystem environment fix, if needed.
		 */
		try {
			$environment = Dispatcher::component( 'Root_Environment' );
			$environment->fix_in_wpadmin( $this->_config );

			if ( ! empty( Util_Request::get_string( 'upgrade' ) ) ) {
				$w3tc_notes[] = __( 'Required files and directories have been automatically created', 'w3-total-cache' );
			}
		} catch ( Util_Environment_Exceptions $exs ) {
			$w3tc_r = Util_Activation::parse_environment_exceptions( $exs );
			$n      = 1;

			foreach ( $w3tc_r['before_errors'] as $e ) {
				$errors[ 'generic_env_' . $n ] = $e;
				++$n;
			}

			if ( strlen( $w3tc_r['required_changes'] ) > 0 ) {
				$changes_style = 'border: 1px solid black; ' .
					'background: white; ' .
					'margin: 10px 30px 10px 30px; ' .
					'padding: 10px; display: none';

				$ftp_style = 'border: 1px solid black; background: white; ' .
					'margin: 10px 30px 10px 30px; ' .
					'padding: 20px; max-width: 450px; display: none';

				$ftp_form = str_replace( 'class="wrap"', '', $exs->credentials_form() );
				$ftp_form = str_replace( '<form ', '<form name="w3tc_ftp_form" ', $ftp_form );
				$ftp_form = str_replace( '<fieldset>', '', $ftp_form );
				$ftp_form = str_replace( '</fieldset>', '', $ftp_form );
				$ftp_form = str_replace( 'id="upgrade" class="button"', 'id="upgrade" class="button w3tc-button-save"', $ftp_form );

				$error = '<strong>' . esc_html__( 'W3 Total Cache Error:', 'w3-total-cache' ) . '</strong> ' .
					esc_html__( 'Files and directories could not be automatically created to complete the installation.', 'w3-total-cache' ) .
					'<table>' .
					'<tr>' .
						'<td>' . esc_html__( 'Please execute commands manually', 'w3-total-cache' ) . '</td>' .
						'<td>' . Util_Ui::button( __( 'View required changes', 'w3-total-cache' ), '', 'w3tc-show-required-changes button' ) . '</td>' .
					'</tr>' .
					'<tr>' .
						'<td>' . esc_html__( 'or use FTP form to allow ', 'w3-total-cache' ) .
							'<strong>' . esc_html__( 'W3 Total Cache', 'w3-total-cache' ) . '</strong> ' .
							esc_html__( 'make it automatically.', 'w3-total-cache' ) .
						'</td>' .
						'<td>' . Util_Ui::button( 'Update via FTP', '', 'w3tc-show-ftp-form button' ) . '</td>' .
					'</tr>' .
					'<tr>' .
						'<td>' . sprintf(
							// translators: 1: HTML anchor open tag, 2: HTML anchor close tag.
							esc_html__( 'Need help? Please %1$scontact support%2$s for assistance.', 'w3-total-cache' ),
							'<a href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_support' ) ) . '">',
							'</a>'
						) . '</td>' .
						'<td>' . Util_Ui::button_link( __( 'Contact Support', 'w3-total-cache' ), Util_Ui::admin_url( 'admin.php?page=w3tc_support' ), false, 'button' ) . '</td>' .
					'</tr>' .
					'</table>' .
					'<div class="w3tc-required-changes" style="' . $changes_style . '">' . $w3tc_r['required_changes'] . '</div>' .
					'<div class="w3tc-ftp-form" style="' . $ftp_style . '">' . $ftp_form . '</div>';

				$environment_error_present = true;
				$errors['generic_ftp']     = $error;
			}

			foreach ( $w3tc_r['later_errors'] as $e ) {
				$errors[ 'generic_env_' . $n ] = $e;
				++$n;
			}
		}

		Generic_AdminActions_Default::apply_pending_nginx_restart_notice_dismiss();

		$errors     = apply_filters( 'w3tc_errors', $errors );
		$w3tc_notes = apply_filters( 'w3tc_notes', $w3tc_notes );

		/**
		 * Show messages.
		 */
		foreach ( $w3tc_notes as $w3tc_key => $w3tc_note ) {
			echo wp_kses(
				sprintf(
					'<div class="updated w3tc_note inline" id="%1$s"><p>%2$s</p></div>',
					esc_attr( $w3tc_key ),
					$w3tc_note
				),
				array(
					'div'   => array(
						'class' => array(),
						'id'    => array(),
					),
					'input' => array(
						'class'   => array(),
						'name'    => array(),
						'onclick' => array(),
						'type'    => array(),
						'value'   => array(),
					),
					'p'     => array(),
					'a'     => array(
						'target' => array(),
						'href'   => array(),
						'class'  => array(),
					),
				)
			);
		}

		foreach ( $errors as $w3tc_key => $error ) {
				printf(
					'<div class="error w3tc_error inline" id="%1$s"><p>%2$s</p></div>',
					esc_attr( $w3tc_key ),
					$error // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
		}
	}

	/**
	 * Run post-update admin tasks.
	 *
	 * Post-update admin tasks are run only once per version.
	 *
	 * @since 2.8.2
	 *
	 * @see Util_Admin::fix_on_event()
	 *
	 * @return void
	 */
	public function post_update_tasks(): void {
		// Check if W3TC was updated.
		$state            = Dispatcher::config_state();
		$last_run_version = $state->get_string( 'tasks.admin.last_run_version' );

		if ( empty( $last_run_version ) || \version_compare( W3TC_VERSION, $last_run_version, '>' ) ) {
			$ran_versions  = get_option( 'w3tc_post_update_admin_tasks_ran_versions', array() );
			$has_completed = false;

			// Check if W3TC was updated to 2.8.1 or higher and not already run.
			if ( \version_compare( W3TC_VERSION, '2.8.1', '>=' ) && ! in_array( '2.8.1', $ran_versions, true ) ) {
				// Fix environment.
				Util_Admin::fix_on_event( $this->_config, 'w3tc_plugin_updated' );

				// Adjust "objectcache.file.gc".
				if ( $this->_config->get_integer( 'objectcache.file.gc' ) === 3600 ) {
					$this->_config->set( 'objectcache.file.gc', 600 );
					$this->_config->save();
				}

				// Mark the task as ran.
				$ran_versions[] = '2.8.1';
				$has_completed  = true;
			}

			// Check if W3TC was updated to 2.8.6 or higher and not already run.
			if ( \version_compare( W3TC_VERSION, '2.8.6', '>=' ) && ! in_array( '2.8.6', $ran_versions, true ) ) {
				// Delete old option.
				delete_option( 'w3tc_post_update_tasks_ran_versions' );

				// Null old state key.
				$state->set( 'tasks.last_run_version', null );

				// Mark the task as ran.
				$ran_versions[] = '2.8.6';
				$has_completed  = true;
			}

			// Mark completed tasks as ran.
			if ( $has_completed ) {
				update_option( 'w3tc_post_update_admin_tasks_ran_versions', $ran_versions, false );
			}

			// Mark the task runner as ran for the current version.
			$state->set( 'tasks.admin.last_run_version', W3TC_VERSION );
			$state->save();
		}
	}
}
