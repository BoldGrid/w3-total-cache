<?php
/**
 * File: Cdn_Plugin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdn_Plugin
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class Cdn_Plugin {
	/**
	 * Reject reason.
	 *
	 * @var string
	 */
	private $cdn_reject_reason = '';

	/**
	 * Config.
	 *
	 * @var Config
	 */
	private $_config = null;

	/**
	 * Debug flag.
	 *
	 * @var bool
	 */
	private $_debug = false;

	/**
	 * Attachements action.
	 *
	 * @var array
	 */
	private $_attachments_action = array();

	/**
	 * Constructor for initializing CDN plugin configuration and debug flag.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
		$this->_debug  = $this->_config->get_boolean( 'cdn.debug' );
	}

	/**
	 * Runs the CDN plugin by setting up necessary hooks and actions.
	 *
	 * @return void
	 */
	public function run() {
		$cdn_engine = $this->_config->get_string( 'cdn.engine' );

		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		add_filter( 'w3tc_footer_comment', array( $this, 'w3tc_footer_comment' ) );

		if ( ! Cdn_Util::is_engine_mirror( $cdn_engine ) ) {
			add_action( 'w3_cdn_cron_queue_process', array( $this, 'cron_queue_process' ) );
			add_action( 'w3_cdn_cron_upload', array( $this, 'cron_upload' ) );
			add_action( 'switch_theme', array( $this, 'switch_theme' ) );

			add_filter( 'update_feedback', array( $this, 'update_feedback' ) );
		}

		$default_override = Cdn_Util::get_flush_manually_default_override( $cdn_engine );
		$flush_on_actions = ! $this->_config->get_boolean( 'cdn.flush_manually', $default_override );

		if ( $flush_on_actions ) {
			add_action( 'delete_attachment', array( $this, 'delete_attachment' ) );
			add_filter( 'wp_insert_attachment_data', array( $this, 'check_inserting_new_attachment' ), 10, 2 );
			add_filter( 'update_attached_file', array( $this, 'update_attached_file' ) );
			add_filter( 'wp_update_attachment_metadata', array( $this, 'update_attachment_metadata' ) );
		}

		add_filter( 'w3tc_preflush_cdn_all', array( $this, 'w3tc_preflush_cdn_all' ), 10, 2 );
		add_filter( 'w3tc_admin_bar_menu', array( $this, 'w3tc_admin_bar_menu' ) );

		if ( is_admin() ) {
			add_filter( 'w3tc_module_is_running-cdn', array( $this, 'cdn_is_running' ) );
		}

		if ( ! is_admin() || $this->_config->get_boolean( 'cdn.admin.media_library' ) ) {
			add_filter( 'wp_prepare_attachment_for_js', array( $this, 'wp_prepare_attachment_for_js' ), 0 );
		}

		// Start rewrite engine.
		\add_action( 'init', array( $this, 'maybe_can_cdn' ), 10, 0 );

		if ( is_admin() && Cdn_Util::can_purge( $cdn_engine ) ) {
			add_filter( 'media_row_actions', array( $this, 'media_row_actions' ), 0, 2 );
		}

		add_filter( 'w3tc_minify_http2_preload_url', array( $this, 'w3tc_minify_http2_preload_url' ), 3000 );
	}

	/**
	 * Callback: Start rewrite engine, if CDN can be used.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function maybe_can_cdn(): void {
		if ( $this->can_cdn() ) {
			Util_Bus::add_ob_callback( 'cdn', array( $this, 'ob_callback' ) );
		}
	}

	/**
	 * Retrieves the admin component for the CDN plugin.
	 *
	 * @return Cdn_Core_Admin Admin component of the CDN plugin.
	 */
	public function get_admin() {
		return Dispatcher::component( 'Cdn_Core_Admin' );
	}

	/**
	 * Processes the CDN queue via cron.
	 *
	 * @return int The number of items successfully processed.
	 */
	public function cron_queue_process() {
		$queue_limit = $this->_config->get_integer( 'cdn.queue.limit' );
		return $this->get_admin()->queue_process( $queue_limit );
	}

	/**
	 * Uploads files to the CDN via cron.
	 *
	 * @return void
	 */
	public function cron_upload() {
		$files = $this->get_files();

		$upload  = array();
		$results = array();

		$common = Dispatcher::component( 'Cdn_Core' );

		foreach ( $files as $file ) {
			$local_path  = $common->docroot_filename_to_absolute_path( $file );
			$remote_path = $common->uri_to_cdn_uri( $common->docroot_filename_to_uri( $file ) );
			$upload[]    = $common->build_file_descriptor( $local_path, $remote_path );
		}

		$common->upload( $upload, true, $results );
	}

	/**
	 * Handles the insertion of new attachments by tracking their action (insert or update).
	 *
	 * @param array $data     Attachment data.
	 * @param array $postarr  Post data for the attachment.
	 *
	 * @return array Modified attachment data.
	 */
	public function check_inserting_new_attachment( $data, $postarr ) {
		$this->_attachments_action[ $postarr['file'] ] = empty( $postarr['ID'] ) ? 'insert' : 'update';

		return $data;
	}

	/**
	 * Preflushes CDN files based on user settings.
	 *
	 * @param bool  $do_flush Whether or not to flush the CDN.
	 * @param array $extras   Extra parameters.
	 *
	 * @return bool Whether or not the flush should occur.
	 */
	public function w3tc_preflush_cdn_all( $do_flush, $extras = array() ) {
		$default_override = Cdn_Util::get_flush_manually_default_override( $this->_config->get_string( 'cdn.engine' ) );
		if ( $this->_config->get_boolean( 'cdn.flush_manually', $default_override ) ) {
			if ( ! isset( $extras['ui_action'] ) ) {
				$do_flush = false;
			}
		}

		return $do_flush;
	}

	/**
	 * Updates the attached file on the CDN.
	 *
	 * @param string $attached_file The attached file path.
	 *
	 * @return string The attached file path after potential modifications.
	 */
	public function update_attached_file( $attached_file ) {
		$common = Dispatcher::component( 'Cdn_Core' );
		$files  = $common->get_files_for_upload( $attached_file );
		$files  = apply_filters( 'w3tc_cdn_update_attachment', $files );

		$results = array();

		$cdn_engine = $this->_config->get_string( 'cdn.engine' );
		if ( Cdn_Util::is_engine_mirror( $cdn_engine ) ) {
			if ( ! array_key_exists( $attached_file, $this->_attachments_action ) || 'update' === $this->_attachments_action[ $attached_file ] ) {
				$common->purge( $files, $results );
			}
		} else {
			$common->upload( $files, true, $results );
		}

		return $attached_file;
	}

	/**
	 * Deletes the attachment from the CDN when it is deleted from WordPress.
	 *
	 * @param int $attachment_id The ID of the attachment to delete.
	 *
	 * @return void
	 */
	public function delete_attachment( $attachment_id ) {
		$common = Dispatcher::component( 'Cdn_Core' );
		$files  = $common->get_attachment_files( $attachment_id );
		$files  = apply_filters( 'w3tc_cdn_delete_attachment', $files );

		$results = array();

		$cdn_engine = $this->_config->get_string( 'cdn.engine' );
		if ( Cdn_Util::is_engine_mirror( $cdn_engine ) ) {
			$common->purge( $files, $results );
		} else {
			$common->delete( $files, true, $results );
		}
	}

	/**
	 * Updates attachment metadata on the CDN.
	 *
	 * @param array $metadata The attachment metadata.
	 *
	 * @return array The updated attachment metadata.
	 */
	public function update_attachment_metadata( $metadata ) {
		$common = Dispatcher::component( 'Cdn_Core' );
		$files  = $common->get_metadata_files( $metadata );
		$files  = apply_filters( 'w3tc_cdn_update_attachment_metadata', $files );

		$results = array();

		$cdn_engine = $this->_config->get_string( 'cdn.engine' );
		if ( Cdn_Util::is_engine_mirror( $cdn_engine ) ) {
			if ( $this->_config->get_boolean( 'cdn.uploads.enable' ) ) {
				$common->purge( $files, $results );
			}
		} else {
			$common->upload( $files, true, $results );
		}

		return $metadata;
	}

	/**
	 * Adds custom cron schedules for CDN tasks.
	 *
	 * @param array $schedules The existing cron schedules.
	 *
	 * @return array Modified cron schedules.
	 */
	public function cron_schedules( $schedules ) {
		$c = $this->_config;

		if ( $c->get_boolean( 'cdn.enabled' ) && ! Cdn_Util::is_engine_mirror( $c->get_string( 'cdn.engine' ) ) ) {
			$queue_interval = $c->get_integer( 'cdn.queue.interval' );

			$schedules['w3_cdn_cron_queue_process'] = array(
				'interval' => $queue_interval,
				'display'  => sprintf(
					// translators: 1 queue interval value.
					__(
						'[W3TC] CDN queue process (every %1$d seconds)',
						'w3-total-cache'
					),
					$queue_interval
				),
			);
		}

		if ( $c->get_boolean( 'cdn.enabled' ) &&
			$c->get_boolean( 'cdn.autoupload.enabled' ) &&
			! Cdn_Util::is_engine_mirror( $c->get_string( 'cdn.engine' ) ) ) {
			$autoupload_interval = $c->get_integer( 'cdn.autoupload.interval' );

			$schedules['w3_cdn_cron_upload'] = array(
				'interval' => $autoupload_interval,
				'display'  => sprintf(
					// translators: 1 queue interval value.
					__(
						'[W3TC] CDN auto upload (every %1$d seconds)',
						'w3-total-cache'
					),
					$autoupload_interval
				),
			);
		}

		return $schedules;
	}

	/**
	 * Handles actions when the theme is switched.
	 *
	 * @return void
	 */
	public function switch_theme() {
		$state = Dispatcher::config_state();
		$state->set( 'cdn.show_note_theme_changed', true );
		$state->save();
	}


	/**
	 * Handles the feedback message for database upgrades.
	 *
	 * @param string $message The feedback message to handle.
	 *
	 * @return void
	 */
	public function update_feedback( $message ) {
		if ( 'Upgrading database' === $message ) {
			$state = Dispatcher::config_state();
			$state->set( 'cdn.show_note_wp_upgraded', true );
			$state->save();
		}
	}

	/**
	 * Callback function for output buffering to process the buffer content.
	 *
	 * @param string $buffer The content to be processed.
	 *
	 * @return string The processed buffer content.
	 */
	public function ob_callback( $buffer ) {
		if ( '' !== $buffer && Util_Content::is_html_xml( $buffer ) ) {
			if ( $this->can_cdn2( $buffer ) ) {
				$srcset_helper = new _Cdn_Plugin_ContentFilter();
				$buffer        = $srcset_helper->replace_all_links( $buffer );

				if ( $this->_debug ) {
					$replaced_urls = $srcset_helper->get_replaced_urls();
					$buffer        = $this->w3tc_footer_comment_after( $buffer, $replaced_urls );
				}
			}
		}

		return $buffer;
	}

	/**
	 * Retrieves an array of files to be processed based on configuration settings.
	 *
	 * @return array List of files to be processed.
	 */
	public function get_files() {
		$files = array();

		if ( $this->_config->get_boolean( 'cdn.includes.enable' ) ) {
			$files = array_merge( $files, $this->get_files_includes() );
		}

		if ( $this->_config->get_boolean( 'cdn.theme.enable' ) ) {
			$files = array_merge( $files, $this->get_files_theme() );
		}

		if ( $this->_config->get_boolean( 'cdn.minify.enable' ) ) {
			$files = array_merge( $files, $this->get_files_minify() );
		}

		if ( $this->_config->get_boolean( 'cdn.custom.enable' ) ) {
			$files = array_merge( $files, $this->get_files_custom() );
		}

		return $files;
	}

	/**
	 * Retrieves an array of files from the includes directory to be processed.
	 *
	 * @return array List of files from the includes directory.
	 */
	public function get_files_includes() {
		$includes_root = Util_Environment::normalize_path( ABSPATH . WPINC );
		$doc_root      = Util_Environment::normalize_path( Util_Environment::document_root() );
		$includes_path = ltrim( str_replace( $doc_root, '', $includes_root ), '/' );

		$files = Cdn_Util::search_files(
			$includes_root,
			$includes_path,
			$this->_config->get_string( 'cdn.includes.files' )
		);

		return $files;
	}

	/**
	 * Retrieves an array of files from the theme directory to be processed.
	 *
	 * @return array List of theme files to be processed.
	 */
	public function get_files_theme() {
		// If mobile or referrer support enabled we should upload whole themes directory.
		if ( $this->_config->get_boolean( 'mobile.enabled' ) || $this->_config->get_boolean( 'referrer.enabled' ) ) {
			$themes_root = get_theme_root();
		} else {
			$themes_root = get_stylesheet_directory();
		}

		$themes_root = Util_Environment::normalize_path( $themes_root );
		$themes_path = ltrim( str_replace( Util_Environment::normalize_path( Util_Environment::document_root() ), '', $themes_root ), '/' );
		$files       = Cdn_Util::search_files(
			$themes_root,
			$themes_path,
			$this->_config->get_string( 'cdn.theme.files' )
		);

		return $files;
	}

	/**
	 * Retrieves an array of minified files to be processed.
	 *
	 * @return array List of minified files to be processed.
	 */
	public function get_files_minify() {
		$files = array();

		if ( $this->_config->get_boolean( 'minify.rewrite' ) &&
			Util_Rule::can_check_rules() &&
			(
				! $this->_config->get_boolean( 'minify.auto' ) ||
				Cdn_Util::is_engine_mirror( $this->_config->get_string( 'cdn.engine' ) )
			) ) {

			$minify = Dispatcher::component( 'Minify_Plugin' );

			$document_root = Util_Environment::normalize_path( Util_Environment::document_root() );
			$minify_root   = Util_Environment::normalize_path( Util_Environment::cache_blog_dir( 'minify' ) );
			$minify_path   = ltrim( str_replace( $document_root, '', $minify_root ), '/' );

			$urls = $minify->get_urls();

			// In WPMU + network admin (this code used for minify manual only)
			// common minify files are stored under context of main blog (i.e. 1)
			// but have urls of 0 blog, so download has to be used.
			if ( 'file' === $this->_config->get_string( 'minify.engine' ) && ! ( Util_Environment::is_wpmu() && is_network_admin() ) ) {
				foreach ( $urls as $url ) {
					Util_Http::get( $url );
				}

				$files = Cdn_Util::search_files(
					$minify_root,
					$minify_path,
					'*.css;*.js'
				);
			} else {
				foreach ( $urls as $url ) {
					$file = Util_Environment::normalize_file_minify( $url );

					if ( ! Util_Environment::is_url( $file ) ) {
						$file = $document_root . '/' . $file;
						$file = ltrim( str_replace( $minify_root, '', $file ), '/' );

						$dir = dirname( $file );

						if ( $dir ) {
							Util_File::mkdir( $dir, 0777, $minify_root );
						}

						if ( Util_Http::download( $url, $minify_root . '/' . $file ) !== false ) {
							$files[] = $minify_path . '/' . $file;
						}
					}
				}
			}
		}

		return $files;
	}

	/**
	 * Retrieves an array of custom files to be processed based on configuration settings.
	 *
	 * @return array List of custom files to be processed.
	 */
	public function get_files_custom() {
		$files         = array();
		$document_root = Util_Environment::normalize_path( Util_Environment::document_root() );
		$custom_files  = $this->_config->get_array( 'cdn.custom.files' );
		$custom_files  = array_map( array( '\W3TC\Util_Environment', 'parse_path' ), $custom_files );
		$site_root     = Util_Environment::normalize_path( Util_Environment::site_root() );
		$path          = Util_Environment::site_url_uri();
		$site_root_dir = str_replace( $document_root, '', $site_root );
		if ( strstr( WP_CONTENT_DIR, Util_Environment::site_root() ) === false ) {
			$site_root = Util_Environment::normalize_path( Util_Environment::document_root() );
			$path      = '';
		}

		$content_path = trim( str_replace( WP_CONTENT_DIR, '', $site_root ), '/\\' );

		foreach ( $custom_files as $custom_file ) {
			if ( '' !== $custom_file ) {
				$custom_file = Cdn_Util::replace_folder_placeholders( $custom_file );
				$custom_file = Util_Environment::normalize_file( $custom_file );

				$dir      = trim( dirname( $custom_file ), '/\\' );
				$rel_path = $dir;

				if ( strpos( $dir, '<currentblog>' ) !== false ) {
					$dir      = str_replace( '<currentblog>', 'blogs.dir/' . Util_Environment::blog_id(), $dir );
					$rel_path = $dir;
				}

				if ( '.' === $dir ) {
					$dir      = '';
					$rel_path = $dir;
				}

				$mask  = basename( $custom_file );
				$files = array_merge(
					$files,
					Cdn_Util::search_files(
						$document_root . '/' . $dir,
						$rel_path,
						$mask
					)
				);
			}
		}

		return $files;
	}

	/**
	 * Checks whether CDN can be applied based on various conditions such as admin access,
	 * user agent, request URI, and SSL settings.
	 *
	 * @return bool True if CDN can be applied, false otherwise.
	 */
	public function can_cdn() {
		// Skip if admin.
		if ( defined( 'WP_ADMIN' ) ) {
			$this->cdn_reject_reason = esc_html__( 'wp-admin', 'w3-total-cache' );

			return false;
		}

		// Check for WPMU's and WP's 3.0 short init.
		if ( defined( 'SHORTINIT' ) && SHORTINIT ) {
			$this->cdn_reject_reason = esc_html__( 'Short init', 'w3-total-cache' );

			return false;
		}

		// Check User agent.
		if ( ! $this->check_ua() ) {
			$this->cdn_reject_reason = esc_html__( 'user agent is rejected', 'w3-total-cache' );

			return false;
		}

		// Check request URI.
		if ( ! $this->_check_request_uri() ) {
			$this->cdn_reject_reason = esc_html__( 'request URI is rejected', 'w3-total-cache' );

			return false;
		}

		// Do not replace urls if SSL and SSL support is do not replace.
		if ( Util_Environment::is_https() && $this->_config->get_boolean( 'cdn.reject.ssl' ) ) {
			$this->cdn_reject_reason = esc_html__( 'SSL is rejected', 'w3-total-cache' );

			return false;
		}

		return true;
	}

	/**
	 * Determines whether CDN processing is allowed based on various conditions.
	 *
	 * Checks for the presence of the DONOTCDN constant, user roles, and request URI conditions.
	 *
	 * @param string $buffer The content buffer to be processed.
	 *
	 * @return bool True if CDN is allowed, false otherwise.
	 */
	public function can_cdn2( $buffer ) {
		// Check for DONOTCDN constant.
		if ( defined( 'DONOTCDN' ) && DONOTCDN ) {
			$this->cdn_reject_reason = esc_html__( 'DONOTCDN constant is defined', 'w3-total-cache' );

			return false;
		}

		// Check logged users roles.
		if ( $this->_config->get_boolean( 'cdn.reject.logged_roles' ) && ! $this->_check_logged_in_role_allowed() ) {
			$this->cdn_reject_reason = esc_html__( 'logged in role is rejected', 'w3-total-cache' );

			return false;
		}

		return true;
	}

	/**
	 * Checks the User-Agent (UA) for any rejection conditions based on configuration.
	 *
	 * @return bool True if the User-Agent is allowed, false if rejected.
	 */
	public function check_ua() {
		$uas = array_merge(
			$this->_config->get_array( 'cdn.reject.ua' ),
			array( W3TC_POWERED_BY )
		);

		foreach ( $uas as $ua ) {
			if ( ! empty( $ua ) ) {
				if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && stristr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), $ua ) !== false ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Checks the request URI for any patterns that should reject the request.
	 *
	 * Evaluates if the current request URI matches any configured rejection patterns.
	 *
	 * @return bool True if the request URI is allowed, false otherwise.
	 */
	public function _check_request_uri() {
		$reject_uri = $this->_config->get_array( 'cdn.reject.uri' );
		$reject_uri = array_map( array( '\W3TC\Util_Environment', 'parse_path' ), $reject_uri );

		foreach ( $reject_uri as $expr ) {
			$expr = trim( $expr );
			$expr = str_replace( '~', '\~', $expr );

			if ( '' !== $expr && preg_match( '~' . $expr . '~i', isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' ) ) {
				return false;
			}
		}

		if ( Util_Request::get_string( 'wp_customize' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks if the current logged-in user's role is allowed to bypass CDN restrictions.
	 *
	 * Verifies whether the logged-in user’s role is listed in the rejection roles configuration.
	 *
	 * @return bool True if the user's role is allowed, false otherwise.
	 */
	private function _check_logged_in_role_allowed() {
		$current_user = wp_get_current_user();

		if ( ! is_user_logged_in() ) {
			return true;
		}

		$roles = $this->_config->get_array( 'cdn.reject.roles' );

		if ( empty( $roles ) || empty( $current_user->roles ) || ! is_array( $current_user->roles ) ) {
			return true;
		}

		foreach ( $current_user->roles as $role ) {
			if ( in_array( $role, $roles, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Filters the media row actions for the admin dashboard.
	 *
	 * Allows the modification of actions related to media items.
	 *
	 * @param array   $actions The list of available actions for the media item.
	 * @param WP_Post $post    The post object representing the media item.
	 *
	 * @return array The modified list of actions.
	 */
	public function media_row_actions( $actions, $post ) {
		return $this->get_admin()->media_row_actions( $actions, $post );
	}

	/**
	 * Determines if the CDN system is currently running.
	 *
	 * Checks the current status of the CDN system.
	 *
	 * @param bool $current_state The current state of the CDN system.
	 *
	 * @return bool True if the CDN system is running, false otherwise.
	 */
	public function cdn_is_running( $current_state ) {
		$admin = $this->get_admin();
		return $admin->is_running();
	}

	/**
	 * Changes the canonical header for the current request.
	 *
	 * Modifies the canonical header for the page, if necessary.
	 *
	 * @return void
	 */
	public function change_canonical_header() {
		$admin = $this->get_admin();
		$admin->change_canonical_header();
	}

	/**
	 * Prepares attachment data for JavaScript use, modifying URLs if necessary.
	 *
	 * Modifies the URLs of attachments to point to the appropriate CDN locations.
	 *
	 * @param array $response The attachment data.
	 *
	 * @return array The modified attachment data.
	 */
	public function wp_prepare_attachment_for_js( $response ) {
		$response['url']  = $this->wp_prepare_attachment_for_js_url( $response['url'] );
		$response['link'] = $this->wp_prepare_attachment_for_js_url( $response['link'] );

		if ( ! empty( $response['sizes'] ) ) {
			foreach ( $response['sizes'] as $size => &$data ) {
				$data['url'] = $this->wp_prepare_attachment_for_js_url( $data['url'] );
			}
		}

		return $response;
	}

	/**
	 * Prepares a URL for use in JavaScript, modifying it if necessary.
	 *
	 * @param string $url The original URL.
	 *
	 * @return string The potentially modified URL.
	 */
	private function wp_prepare_attachment_for_js_url( $url ) {
		$url = trim( $url );
		if ( ! empty( $url ) ) {
			$parsed = wp_parse_url( $url );
			$uri    = ( isset( $parsed['path'] ) ? $parsed['path'] : '/' ) .
				( isset( $parsed['query'] ) ? '?' . $parsed['query'] : '' );

			$wp_upload_dir   = wp_upload_dir();
			$upload_base_url = $wp_upload_dir['baseurl'];

			if ( substr( $url, 0, strlen( $upload_base_url ) ) === $upload_base_url ) {
				$common  = Dispatcher::component( 'Cdn_Core' );
				$new_url = $common->url_to_cdn_url( $url, $uri );
				if ( ! is_null( $new_url ) ) {
					$url = $new_url;
				}
			}
		}

		return $url;
	}

	/**
	 * Prepares a URL for HTTP2 preload by modifying it to point to the CDN.
	 *
	 * Modifies the result link to point to the CDN URL for HTTP2 preload.
	 *
	 * @param array $data The data containing the result link.
	 *
	 * @return array The modified data with the CDN URL.
	 */
	public function w3tc_minify_http2_preload_url( $data ) {
		$url = $data['result_link'];

		$url = trim( $url );
		if ( empty( $url ) ) {
			return $data;
		}

		$parsed = wp_parse_url( $url );
		$uri    = ( isset( $parsed['path'] ) ? $parsed['path'] : '/' ) .
			( isset( $parsed['query'] ) ? '?' . $parsed['query'] : '' );

		$common  = Dispatcher::component( 'Cdn_Core' );
		$new_url = $common->url_to_cdn_url( $url, $uri );
		if ( is_null( $new_url ) ) {
			return $data;
		}

		$data['result_link'] = $new_url;

		// url_to_cdn_url processed by browsercache internally.
		$data['browsercache_processed'] = '*';

		return $data;
	}

	/**
	 * Adds CDN-related items to the WordPress admin bar menu.
	 *
	 * Modifies the admin bar menu to include CDN cache options if applicable.
	 *
	 * @param array $menu_items The current admin bar menu items.
	 *
	 * @return array The modified admin bar menu items.
	 */
	public function w3tc_admin_bar_menu( $menu_items ) {
		$cdn_engine = $this->_config->get_string( 'cdn.engine' );

		if ( Cdn_Util::can_purge_all( $cdn_engine ) ) {
			$menu_items['20710.cdn'] = array(
				'id'     => 'w3tc_cdn_flush_all',
				'parent' => 'w3tc_flush',
				'title'  => __( 'CDN Cache', 'w3-total-cache' ),
				'href'   => wp_nonce_url( admin_url( 'admin.php?page=w3tc_cdn&amp;w3tc_flush_cdn' ), 'w3tc' ),
			);
		}

		if ( Cdn_Util::can_purge( $cdn_engine ) ) {
			$menu_items['20790.cdn'] = array(
				'id'     => 'w3tc_cdn_flush',
				'parent' => 'w3tc_flush',
				'title'  => __( 'CDN: Manual Purge', 'w3-total-cache' ),
				'href'   => wp_nonce_url( admin_url( 'admin.php?page=w3tc_cdn&amp;w3tc_cdn_purge' ), 'w3tc' ),
				'meta'   => array( 'onclick' => 'w3tc_popupadmin_bar(this.href); return false' ),
			);
		}

		return $menu_items;
	}

	/**
	 * Appends a footer comment to indicate the CDN engine and rejection reason.
	 *
	 * Adds details about the CDN engine and any rejection reasons to the footer comment.
	 *
	 * @param array $strings The current strings to be included in the footer comment.
	 *
	 * @return array The modified footer comment strings.
	 */
	public function w3tc_footer_comment( $strings ) {
		$common = Dispatcher::component( 'Cdn_Core' );
		$cdn    = $common->get_cdn();
		$via    = $cdn->get_via();

		$strings[] = sprintf(
			// translators: 1 CDN engine name, 2 rejection reason.
			__(
				'Content Delivery Network via %1$s%2$s',
				'w3-total-cache'
			),
			( $via ? $via : 'N/A' ),
			( empty( $this->cdn_reject_reason ) ? '' : sprintf( ' (%s)', $this->cdn_reject_reason ) )
		);

		if ( $this->_debug ) {
			$strings[] = '{w3tc_cdn_debug_info}';
		}

		return $strings;
	}

	/**
	 * Appends debug information about replaced URLs for CDN to the footer.
	 *
	 * Adds debug information about replaced URLs to the footer comment.
	 *
	 * @param string $buffer        The content buffer being processed.
	 * @param array  $replaced_urls The array of replaced URLs.
	 *
	 * @return string The modified content buffer with the appended debug information.
	 */
	public function w3tc_footer_comment_after( $buffer, $replaced_urls ) {
		$strings = array();

		if ( is_array( $replaced_urls ) && count( $replaced_urls ) ) {
			$strings[] = __( 'Replaced URLs for CDN:', 'w3-total-cache' );

			foreach ( $replaced_urls as $old_url => $new_url ) {
				$strings[] = sprintf(
					'%1$s => %2$s',
					Util_Content::escape_comment( $old_url ),
					Util_Content::escape_comment( $new_url )
				);
			}

			$strings[] = '';
		}

		$buffer = str_replace( '{w3tc_cdn_debug_info}', implode( "\n", $strings ), $buffer );

		return $buffer;
	}
}

/**
 * Class: _Cdn_Plugin_ContentFilter
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class _Cdn_Plugin_ContentFilter { // phpcs:ignore Generic.Classes.OpeningBraceSameLine.ContentAfterBrace, PEAR.NamingConventions.ValidClassName.StartWithCapital, Generic.Files.OneObjectStructurePerFile.MultipleFound
	/**
	 * Regular expressions.
	 *
	 * @var array
	 */
	private $_regexps = array();

	/**
	 * Placeholders.
	 *
	 * @var array
	 */
	private $_placeholders = array();

	/**
	 * Config.
	 *
	 * @var Config
	 */
	private $_config;

	/**
	 * Replaced URLs.
	 *
	 * @var array
	 */
	private $_replaced_urls = array();

	/**
	 * If background uploading already scheduled
	 *
	 * @var boolean
	 */
	private static $_upload_scheduled = false;

	/**
	 * Initializes the CDN plugin with configuration from the Dispatcher.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Replaces all links in the given buffer with appropriate CDN URLs.
	 *
	 * @param string $buffer The content to process and replace URLs in.
	 *
	 * @return string The modified content with replaced links.
	 */
	public function replace_all_links( $buffer ) {
		$this->fill_regexps();

		$srcset_pattern = '~srcset\s*=\s*[\"\'](.*?)[\"\']~s';
		$buffer         = preg_replace_callback(
			$srcset_pattern,
			array( $this, '_srcset_replace_callback' ),
			$buffer
		);

		foreach ( $this->_regexps as $regexp ) {
			$buffer = preg_replace_callback(
				$regexp,
				array( $this, '_link_replace_callback' ),
				$buffer
			);
		}

		if ( $this->_config->get_boolean( 'cdn.minify.enable' ) ) {
			if ( $this->_config->get_boolean( 'minify.auto' ) ) {
				$minify_url_regexp = $this->minify_url_regexp( '/[a-zA-Z0-9-_]+\.(css|js)' );

				if ( Cdn_Util::is_engine_mirror( $this->_config->get_string( 'cdn.engine' ) ) ) {
					$processor = array( $this, '_link_replace_callback' );
				} else {
					$processor = array( $this, '_minify_auto_pushcdn_link_replace_callback' );
				}
			} else {
				$minify_url_regexp = $this->minify_url_regexp( '/[a-z0-9]+\..+\.include(-(footer|body))?(-nb)?\.[a-f0-9]+\.(css|js)' );
				$processor         = array( $this, '_link_replace_callback' );
			}

			if ( ! empty( $minify_url_regexp ) ) {
				$regexp = '~(["\'(=])\s*' . $minify_url_regexp . '~U';
				$buffer = preg_replace_callback( $regexp, $processor, $buffer );
			}
		}

		$buffer = $this->replace_placeholders( $buffer );

		return $buffer;
	}

	/**
	 * Callback function to replace URLs in `srcset` attributes with CDN URLs.
	 *
	 * @param array $matches The matches from the regular expression.
	 *
	 * @return string The modified `srcset` attribute value.
	 */
	public function _link_replace_callback( $matches ) {
		list( $matched_url, $quote, $url, , , , $path ) = $matches;

		$path = ltrim( $path, '/' );
		$r    = $this->_link_replace_callback_checks( $matched_url, $quote, $url, $path );
		if ( is_null( $r ) ) {
			$r = $this->_link_replace_callback_ask_cdn( $matched_url, $quote, $url, $path );
		}

		return $r;
	}

	/**
	 * Callback function to replace a link URL with the appropriate CDN URL.
	 *
	 * @param array $matches The matches from the regular expression.
	 *
	 * @return string|null The modified URL or null if no replacement is necessary.
	 */
	public function _srcset_replace_callback( $matches ) {
		list( $matched_url, $srcset ) = $matches;

		if ( empty( $this->_regexps ) ) {
			return $matched_url;
		}

		$index = '%srcset-' . count( $this->_placeholders ) . '%';

		$srcset_urls     = explode( ',', $srcset );
		$new_srcset_urls = array();

		foreach ( $srcset_urls as $set ) {
			preg_match( '~(?P<spaces>^\s*)(?P<url>\S+)(?P<rest>.*)~', $set, $parts );
			if ( isset( $parts['url'] ) ) {
				foreach ( $this->_regexps as $regexp ) {
					$new_url = preg_replace_callback(
						$regexp,
						array(
							$this,
							'_link_replace_callback',
						),
						'"' . $parts['url'] . '">'
					);

					if ( '"' . $parts['url'] . '">' !== $new_url ) {
						$parts['url'] = substr( $new_url, 1, -2 );
						break;
					}
				}
				$new_srcset_urls[] = $parts['spaces'] . $parts['url'] . $parts['rest'];
			} else {
				$new_srcset_urls[] = $set;
			}
		}

		$this->_placeholders[ $index ] = implode( ',', $new_srcset_urls );

		return 'srcset="' . $index . '"';
	}

	/**
	 * Replaces placeholders in the buffer with actual URLs or content.
	 *
	 * @param string $buffer The content to process and replace placeholders in.
	 *
	 * @return string The modified content with replaced placeholders.
	 */
	private function replace_placeholders( $buffer ) {
		foreach ( $this->_placeholders as $srcset_id => $srcset_content ) {
			$buffer = str_replace( $srcset_id, $srcset_content, $buffer );
		}
		return $buffer;
	}

	/**
	 * Generates a regular expression for matching minified URLs.
	 *
	 * @param string $filename_mask The filename mask for minified files.
	 *
	 * @return string The generated regular expression for matching minified URLs.
	 */
	private function minify_url_regexp( $filename_mask ) {
		$minify_base_url = Util_Environment::filename_to_url(
			Util_Environment::cache_blog_minify_dir()
		);
		$matches         = null;
		if ( ! preg_match( '~((https?://)?([^/]+))(.+)~i', $minify_base_url, $matches ) ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				__(
					'Cant find minification base url, make sure minification folder sits inside WP_CONTENT_DIR and DOCUMENT_ROOT is set correctly',
					'w3-total-cache'
				)
			);
			return '';
		}

		$protocol_domain_regexp = Util_Environment::get_url_regexp( $matches[1] );
		$path_regexp            = Util_Environment::preg_quote( $matches[4] );

		$regexp =
			'(' .
			'(' . $protocol_domain_regexp . ')?' .
			'(' . $path_regexp . $filename_mask . ')' .
			')';
		return $regexp;
	}

	/**
	 * Generates regular expressions for matching upload URLs.
	 *
	 * @param string $domain_url_regexp The domain URL regular expression.
	 * @param string $baseurl The base URL for uploads.
	 * @param array  $upload_info Information about the uploads directory.
	 * @param array  $regexps The array of existing regular expressions.
	 *
	 * @return array The updated array of regular expressions.
	 */
	private function make_uploads_regexes( $domain_url_regexp, $baseurl, $upload_info, $regexps ) {
		if ( preg_match( '~' . $domain_url_regexp . '~i', $baseurl ) ) {
			$regexps[] = '~(["\'(=])\s*((' . $domain_url_regexp . ')?('
				. Util_Environment::preg_quote( $upload_info['baseurlpath'] )
				. '([^"\')>]+)))~i';
		} else {
			$parsed                   = @wp_parse_url( $baseurl ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$upload_url_domain_regexp = isset( $parsed['host'] ) ?
				Util_Environment::get_url_regexp( $parsed['scheme'] . '://' . $parsed['host'] ) :
				$domain_url_regexp;
			$baseurlpath              = isset( $parsed['path'] ) ? rtrim( $parsed['path'], '/' ) : '';
			if ( $baseurlpath ) {
				$regexps[] = '~(["\'])\s*((' . $upload_url_domain_regexp . ')?('
					. Util_Environment::preg_quote( $baseurlpath )
					. '([^"\'>]+)))~i';
			} else {
				$regexps[] = '~(["\'])\s*((' . $upload_url_domain_regexp
					. ')(([^"\'>]+)))~i';
			}
		}
		return $regexps;
	}

	/**
	 * Fills the array of regular expressions for matching different URLs (uploads, includes, theme, etc.).
	 *
	 * @return void
	 */
	private function fill_regexps() {
		$regexps = array();

		$site_path         = Util_Environment::site_url_uri();
		$domain_url_regexp = Util_Environment::home_domain_root_url_regexp();

		$site_domain_url_regexp = false;
		if ( Util_Environment::get_url_regexp( Util_Environment::url_to_host( site_url() ) ) !== $domain_url_regexp ) {
			$site_domain_url_regexp = Util_Environment::get_url_regexp( Util_Environment::url_to_host( site_url() ) );
		}

		if ( $this->_config->get_boolean( 'cdn.uploads.enable' ) ) {
			$upload_info = Util_Http::upload_info();

			if ( $upload_info ) {
				$baseurl = $upload_info['baseurl'];

				if ( defined( 'DOMAIN_MAPPING' ) && DOMAIN_MAPPING ) {
					$parsed  = @wp_parse_url( $upload_info['baseurl'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					$baseurl = home_url() . $parsed['path'];
				}

				$regexps = $this->make_uploads_regexes(
					$domain_url_regexp,
					$baseurl,
					$upload_info,
					$regexps
				);

				if ( $site_domain_url_regexp ) {
					$regexps = $this->make_uploads_regexes(
						$site_domain_url_regexp,
						$baseurl,
						$upload_info,
						$regexps
					);
				}
			}
		}

		if ( $this->_config->get_boolean( 'cdn.includes.enable' ) ) {
			$mask = $this->_config->get_string( 'cdn.includes.files' );
			if ( '' !== $mask ) {
				$regexps[] = '~(["\'(=])\s*((' . $domain_url_regexp . ')?(' .
					Util_Environment::preg_quote( $site_path . WPINC ) .
					'/(' . Cdn_Util::get_regexp_by_mask( $mask ) . ')([^"\'() >]*)))~i';
				if ( $site_domain_url_regexp ) {
					$regexps[] = '~(["\'(=])\s*((' . $site_domain_url_regexp . ')?(' .
						Util_Environment::preg_quote( $site_path . WPINC ) .
						'/(' . Cdn_Util::get_regexp_by_mask( $mask ) .
						')([^"\'() >]*)))~i';
				}
			}
		}

		if ( $this->_config->get_boolean( 'cdn.theme.enable' ) ) {
			$theme_dir = preg_replace( '~' . $domain_url_regexp . '~i', '', get_theme_root_uri() );

			$mask = $this->_config->get_string( 'cdn.theme.files' );

			if ( '' !== $mask ) {
				$regexps[] = '~(["\'(=])\s*((' . $domain_url_regexp . ')?(' .
					Util_Environment::preg_quote( $theme_dir ) . '/(' .
					Cdn_Util::get_regexp_by_mask( $mask ) . ')([^"\'() >]*)))~i';
				if ( $site_domain_url_regexp ) {
					$theme_dir2 = preg_replace( '~' . $site_domain_url_regexp . '~i', '', get_theme_root_uri() );
					$regexps[]  = '~(["\'(=])\s*((' . $site_domain_url_regexp . ')?(' .
						Util_Environment::preg_quote( $theme_dir ) . '/(' .
						Cdn_Util::get_regexp_by_mask( $mask ) .
						')([^"\'() >]*)))~i';
					$regexps[]  = '~(["\'(=])\s*((' . $site_domain_url_regexp . ')?(' .
						Util_Environment::preg_quote( $theme_dir2 ) .
						'/(' . Cdn_Util::get_regexp_by_mask( $mask ) .
						')([^"\'() >]*)))~i';
				}
			}
		}

		if ( $this->_config->get_boolean( 'cdn.custom.enable' ) ) {
			$masks = $this->_config->get_array( 'cdn.custom.files' );
			$masks = array_map( array( '\W3TC\Cdn_Util', 'replace_folder_placeholders_to_uri' ), $masks );
			$masks = array_map( array( '\W3TC\Util_Environment', 'parse_path' ), $masks );

			if ( count( $masks ) ) {
				$custom_regexps_urls            = array();
				$custom_regexps_uris            = array();
				$custom_regexps_docroot_related = array();

				foreach ( $masks as $mask ) {
					if ( ! empty( $mask ) ) {
						if ( Util_Environment::is_url( $mask ) ) {
							$url_match = array();
							if ( preg_match( '~^((https?:)?//([^/]*))(.*)~', $mask, $url_match ) ) {
								$custom_regexps_urls[] = array(
									'domain_url' => Util_Environment::get_url_regexp( $url_match[1] ),
									'uri'        => Cdn_Util::get_regexp_by_mask( $url_match[4] ),
								);
							}
						} elseif ( '/' === substr( $mask, 0, 1 ) ) { // uri.
							$custom_regexps_uris[] = Cdn_Util::get_regexp_by_mask( $mask );
						} else {
							$file = Util_Environment::normalize_path( $mask );   // \ -> backspaces.
							$file = str_replace( Util_Environment::site_root(), '', $file );
							$file = ltrim( $file, '/' );

							$custom_regexps_docroot_related[] = Cdn_Util::get_regexp_by_mask( $mask );
						}
					}
				}

				if ( count( $custom_regexps_urls ) > 0 ) {
					foreach ( $custom_regexps_urls as $regexp ) {
						$regexps[] = '~(["\'(=])\s*((' . $regexp['domain_url'] .
						')?((' . $regexp['uri'] . ')([^"\'() >]*)))~i';
					}
				}
				if ( count( $custom_regexps_uris ) > 0 ) {
					$regexps[] = '~(["\'(=])\s*((' . $domain_url_regexp .
						')?((' . implode( '|', $custom_regexps_uris ) . ')([^"\'() >]*)))~i';
				}

				if ( count( $custom_regexps_docroot_related ) > 0 ) {
					$regexps[] = '~(["\'(=])\s*((' . $domain_url_regexp . ')?(' .
						Util_Environment::preg_quote( $site_path ) .
						'(' . implode( '|', $custom_regexps_docroot_related ) . ')([^"\'() >]*)))~i';
					if ( $site_domain_url_regexp ) {
						$regexps[] = '~(["\'(=])\s*((' . $site_domain_url_regexp . ')?(' .
							Util_Environment::preg_quote( $site_path ) . '(' .
							implode( '|', $custom_regexps_docroot_related ) . ')([^"\'() >]*)))~i';
					}
				}
			}
		}

		$this->_regexps = $regexps;
	}

	/**
	 * Callback to perform checks before replacing URLs in links.
	 *
	 * This method checks if a given URL has already been replaced, evaluates the URL against rejected file patterns,
	 * and determines if the URL is queued for replacement. If the URL matches any rejection pattern or is already
	 * queued, the method returns the original match without replacing it. This method is used as part of the URL
	 * replacement process to ensure only accepted URLs are replaced.
	 *
	 * @param string $matched_url The matched URL string.
	 * @param string $quote       The quote character around the matched URL.
	 * @param string $url         The original URL to be replaced.
	 * @param string $path        The path portion of the matched URL.
	 *
	 * @return string|null Returns the replaced URL if accepted; otherwise, the original match.
	 */
	public function _link_replace_callback_checks( $matched_url, $quote, $url, $path ) {
		global $wpdb;

		static $queue = null, $reject_files = null;

		/**
		 * Check if URL was already replaced
		 */
		if ( isset( $this->_replaced_urls[ $url ] ) ) {
			return $quote . $this->_replaced_urls[ $url ];
		}

		/**
		 * Check URL for rejected files
		 */
		if ( null === $reject_files ) {
			$reject_files = $this->_config->get_array( 'cdn.reject.files' );
		}

		foreach ( $reject_files as $reject_file ) {
			if ( '' !== $reject_file ) {
				$reject_file = Cdn_Util::replace_folder_placeholders( $reject_file );
				$reject_file = Util_Environment::normalize_file( $reject_file );

				$reject_file_regexp = '~^(' . Cdn_Util::get_regexp_by_mask( $reject_file ) . ')~i';

				if ( preg_match( $reject_file_regexp, $path ) ) {
					return $matched_url;
				}
			}
		}

		// Don't replace URL for files that are in the CDN queue.
		if ( null === $queue ) {
			if ( ! Cdn_Util::is_engine_mirror( $this->_config->get_string( 'cdn.engine' ) ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$queue = $wpdb->get_var(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
						'SELECT `remote_path` FROM `%1$s` WHERE `remote_path` = \'%2$s\'',
						$wpdb->base_prefix . W3TC_CDN_TABLE_QUEUE,
						$path
					)
				);
			} else {
				$queue = false;
			}
		}

		if ( $queue ) {
			return $matched_url;
		}

		return null;
	}

	/**
	 * Callback to request a replacement URL from the CDN.
	 *
	 * This method checks if a given URL has a valid replacement URL in the CDN and replaces it. If the URL does not
	 * have a valid CDN replacement, it returns the original matched URL. This method is used to replace URLs with
	 * CDN-hosted versions when such replacements are supported and valid.
	 *
	 * @param string $matched_url The matched URL string.
	 * @param string $quote       The quote character around the matched URL.
	 * @param string $url         The original URL to be replaced.
	 * @param string $path        The path portion of the matched URL.
	 *
	 * @return string The replaced URL if a valid replacement is found; otherwise, the original match.
	 */
	public function _link_replace_callback_ask_cdn( $matched_url, $quote, $url, $path ) {
		$common  = Dispatcher::component( 'Cdn_Core' );
		$new_url = $common->url_to_cdn_url( $url, $path );
		if ( ! is_null( $new_url ) ) {
			$this->_replaced_urls[ $url ] = $new_url;
			return $quote . $new_url;
		}

		return $matched_url;
	}

	/**
	 * Callback to replace URLs with CDN-hosted versions during minification.
	 *
	 * This method is used to replace matched URLs with CDN-hosted versions when performing minification. If the URL
	 * does not have a valid replacement in the CDN, it queues the URL for later processing. This method handles URLs
	 * found during the minification process and attempts to replace them with their CDN-hosted equivalents.
	 *
	 * @param array $matches Array containing match details: full match, quote character, original URL, etc.
	 *
	 * @return string The replaced URL if accepted; otherwise, the original match.
	 */
	public function _minify_auto_pushcdn_link_replace_callback( $matches ) {
		static $dispatcher = null;

		list( $matched_url, $quote, $url, , , , $path ) = $matches;

		$path = ltrim( $path, '/' );
		$r    = $this->_link_replace_callback_checks( $matched_url, $quote, $url, $path );

		// Check if we can replace that URL (for auto mode it should be uploaded).
		if ( ! Dispatcher::is_url_cdn_uploaded( $url ) ) {
			Dispatcher::component( 'Cdn_Core' )->queue_upload_url( $url );
			if ( ! self::$_upload_scheduled ) {
				wp_schedule_single_event( time(), 'w3_cdn_cron_queue_process' );
				add_action( 'shutdown', 'wp_cron' );

				self::$_upload_scheduled = true;
			}

			return $matched_url;
		}

		if ( is_null( $r ) ) {
			$r = $this->_link_replace_callback_ask_cdn( $matched_url, $quote, $url, $path );
		}
		return $r;
	}

	/**
	 * Retrieves the list of replaced URLs.
	 *
	 * This method returns an array of URLs that have been replaced with their CDN-hosted versions during the link
	 * replacement process. This list can be used for debugging, analysis, and verification of CDN replacements.
	 *
	 * @return array An associative array of replaced URLs.
	 */
	public function get_replaced_urls() {
		return $this->_replaced_urls;
	}
}
