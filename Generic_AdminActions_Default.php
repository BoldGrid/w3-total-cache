<?php
/**
 * File: Generic_AdminActions_Default.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;

define( 'W3TC_PLUGIN_TOTALCACHE_REGEXP_COOKIEDOMAIN', '~define\s*\(\s*[\'"]COOKIE_DOMAIN[\'"]\s*,.*?\)~is' );

/**
 * Class Generic_AdminActions_Default
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class Generic_AdminActions_Default {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config = null;

	/**
	 * Config master
	 *
	 * @var Config
	 */
	private $_config_master = null;

	/**
	 * Current page
	 *
	 * @var null|string
	 */
	private $_page = null;

	/**
	 * Initializes the class instance and loads configuration settings.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config        = Dispatcher::config();
		$this->_config_master = Dispatcher::config_master();

		$this->_page = Util_Admin::get_current_page();
	}

	/**
	 * Defence-in-depth capability gate for every public action method
	 * dispatched through Root_AdminActions. Calls wp_die on failure.
	 *
	 * Even though Generic_Plugin_Admin::load() now floors the dispatcher
	 * at manage_options, this per-handler check guarantees that any
	 * future caller (custom AJAX route, programmatic invocation) cannot
	 * bypass the cap gate.
	 *
	 * @since 2.10.0
	 *
	 * @return void
	 */
	private function _require_admin_cap() {
		if ( ! \current_user_can( 'manage_options' ) ) {
			wp_die(
				\esc_html__( 'You do not have sufficient permissions to perform this action.', 'w3-total-cache' ),
				'',
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Enables preview mode and redirects to the home URL.
	 *
	 * @return void
	 */
	public function w3tc_default_previewing() {
		$this->_require_admin_cap();
		Util_Environment::set_preview( true );
		Util_Environment::redirect( get_home_url() );
	}

	/**
	 * Disables preview mode and redirects to the current page.
	 *
	 * @return void
	 */
	public function w3tc_default_stop_previewing() {
		$this->_require_admin_cap();
		Util_Environment::set_preview( false );
		Util_Admin::redirect( array(), true );
	}

	/**
	 * Saves the provided license key to the configuration.
	 *
	 * @return void
	 *
	 * @throws \Exception If saving the license key or configuration fails.
	 */
	public function w3tc_default_save_license_key() {
		$this->_require_admin_cap();
		$w3tc_license = Util_Request::get_string( 'license_key' );
		try {
			$old_config = new Config();

			$this->_config->set( 'plugin.license_key', $w3tc_license );
			$this->_config->save();

			Dispatcher::component( 'Licensing_Plugin_Admin' )->possible_state_change(
				$this->_config,
				$old_config
			);
		} catch ( \Exception $ex ) {
			echo wp_json_encode( array( 'result' => 'failed' ) );
			exit();
		}

		echo wp_json_encode( array( 'result' => 'success' ) );
		exit();
	}

	/**
	 * Hides a specified admin note and updates the configuration.
	 *
	 * @return void
	 */
	public function w3tc_default_hide_note() {
		$this->_require_admin_cap();
		$w3tc_note    = Util_Request::get_string( 'note' );
		$w3tc_setting = sprintf( 'notes.%s', $w3tc_note );

		$this->_config->set( $w3tc_setting, false );
		$this->_config->save();

		do_action( "w3tc_hide_button-{$w3tc_note}" );
		Util_Admin::redirect( array(), true );
	}

	/**
	 * Updates a specified configuration state value and saves the changes.
	 *
	 * The state-key namespace is shared with restricted-write config
	 * keys (`license.*`, `common.*`, dismissable-notice flags), so
	 * the writable set is restricted to {@see ConfigKeysSchema::is_known_state_key()}.
	 * Unknown keys are silently refused without writing.
	 *
	 * @return void
	 */
	public function w3tc_default_config_state() {
		$this->_require_admin_cap();
		$w3tc_key   = Util_Request::get_string( 'key' );
		$w3tc_value = Util_Request::get_string( 'value' );

		if ( ! ConfigKeysSchema::is_known_state_key( $w3tc_key ) ) {
			Util_Admin::redirect( array(), true );
			return;
		}

		$w3tc_config_state = Dispatcher::config_state_master();
		$w3tc_config_state->set( $w3tc_key, $w3tc_value );
		$w3tc_config_state->save();
		Util_Admin::redirect( array(), true );
	}

	/**
	 * Updates a specified master configuration state value and saves the changes.
	 *
	 * @return void
	 */
	public function w3tc_default_config_state_master() {
		$this->_require_admin_cap();
		$w3tc_key   = Util_Request::get_string( 'key' );
		$w3tc_value = Util_Request::get_string( 'value' );

		if ( ! ConfigKeysSchema::is_known_state_key( $w3tc_key ) ) {
			Util_Admin::redirect( array(), true );
			return;
		}

		if (
			'common.hide_note_nginx_restart_required' === $w3tc_key &&
			Util_Environment::to_boolean( $w3tc_value )
		) {
			self::record_nginx_restart_notice_dismiss();
			self::flag_pending_nginx_restart_notice_dismiss();
		} else {
			$w3tc_config_state = Dispatcher::config_state_master();
			$w3tc_config_state->set( $w3tc_key, $w3tc_value );
			$w3tc_config_state->save();
		}

		Util_Admin::redirect( array(), true );
	}

	/**
	 * Persist an nginx-restart notice dismiss against the current rules file.
	 *
	 * @since 2.10.0
	 *
	 * @return void
	 */
	public static function record_nginx_restart_notice_dismiss() {
		$state = Dispatcher::config_state_master();
		$state->set( 'common.hide_note_nginx_restart_required', true );
		$state->set(
			'common.nginx_rules_dismiss_fingerprint',
			Util_Rule::nginx_rules_file_fingerprint()
		);
		$state->save();
	}

	/**
	 * Remember an nginx-restart notice dismiss for the next admin_notices pass.
	 *
	 * The dismiss handler runs on `load-{page}` and exits before
	 * `admin_notices`, but the redirect target always runs the
	 * environment writer first. That writer can call
	 * `Util_Rule::after_rules_modified()`, which clears the hide flag.
	 * Re-applying the dismiss after the writer completes keeps the
	 * operator's choice on the redirect response.
	 *
	 * @since 2.10.0
	 *
	 * @return void
	 */
	public static function flag_pending_nginx_restart_notice_dismiss() {
		\set_site_transient( 'w3tc_pending_hide_nginx_restart', 1, MINUTE_IN_SECONDS );
	}

	/**
	 * Persist a pending nginx-restart notice dismiss, if any.
	 *
	 * @since 2.10.0
	 *
	 * @return void
	 */
	public static function apply_pending_nginx_restart_notice_dismiss() {
		if ( ! \get_site_transient( 'w3tc_pending_hide_nginx_restart' ) ) {
			return;
		}

		\delete_site_transient( 'w3tc_pending_hide_nginx_restart' );

		self::record_nginx_restart_notice_dismiss();
	}

	/**
	 * Updates a specified note configuration state and redirects.
	 *
	 * @return void
	 */
	public function w3tc_default_config_state_note() {
		$this->_require_admin_cap();
		$w3tc_key   = Util_Request::get_string( 'key' );
		$w3tc_value = Util_Request::get_string( 'value' );

		if ( ! ConfigKeysSchema::is_known_state_key( $w3tc_key ) ) {
			Util_Admin::redirect( array(), true );
			return;
		}

		$s = Dispatcher::config_state_note();
		$s->set( $w3tc_key, $w3tc_value );

		Util_Admin::redirect( array(), true );
	}

	/**
	 * Hides a custom admin note and redirects.
	 *
	 * @return void
	 */
	public function w3tc_default_hide_note_custom() {
		$this->_require_admin_cap();
		$w3tc_note = Util_Request::get_string( 'note' );
		do_action( "w3tc_hide_button_custom-{$w3tc_note}" );
		Util_Admin::redirect( array(), true );
	}

	/**
	 * Clears the purge log for the specified module.
	 *
	 * @return void
	 */
	public function w3tc_default_purgelog_clear() {
		$this->_require_admin_cap();
		$w3tc_module  = Util_Request::get_label( 'module' );
		$log_filename = Util_Debug::log_filename( $w3tc_module . '-purge' );

		if ( file_exists( $log_filename ) ) {
			unlink( $log_filename );
		}

		Util_Admin::redirect(
			array(
				'page'   => 'w3tc_general',
				'view'   => 'purge_log',
				'module' => $w3tc_module,
			),
			true
		);
	}

	/**
	 * Removes an add-in module, handles deletion, and performs necessary replacements.
	 *
	 * @return void
	 */
	public function w3tc_default_remove_add_in() {
		$this->_require_admin_cap();
		$w3tc_module = Util_Request::get_string( 'w3tc_default_remove_add_in' );

		/**
		 * In the case of missing permissions to delete
		 * environment will use that to try to override addin via ftp.
		 */
		set_transient( 'w3tc_remove_add_in_' . $w3tc_module, 'yes', 600 );

		switch ( $w3tc_module ) {
			case 'pgcache':
				Util_WpFile::delete_file( W3TC_ADDIN_FILE_ADVANCED_CACHE );
				$src = W3TC_INSTALL_FILE_ADVANCED_CACHE;
				$dst = W3TC_ADDIN_FILE_ADVANCED_CACHE;
				try {
					Util_WpFile::copy_file( $src, $dst );
				} catch ( Util_WpFile_FilesystemOperationException $ex ) {
					/**
					 * Previously an empty catch silently
					 * dropped a copy failure here, then fell through
					 * to the success redirect. The drop-in was left
					 * missing while the admin saw "add-in removed"
					 * (false audit trail). Surface the failure to
					 * both the audit hook and the admin notice so
					 * operators learn the drop-in needs manual
					 * restoration.
					 */
					Util_Debug::audit_log(
						'addin_install_failed',
						array(
							'module'  => 'pgcache',
							'source'  => $src,
							'dest'    => $dst,
							'message' => $ex->getMessage(),
						)
					);
					Util_Debug::log( 'admin', 'advanced-cache.php restore failed: ' . $ex->getMessage() );
					Util_Admin::redirect_with_custom_messages(
						array(),
						array(
							sprintf(
								// translators: %s = filesystem operation error message.
								__( 'Could not restore advanced-cache.php: %s. The page-cache drop-in needs manual restoration.', 'w3-total-cache' ),
								$ex->getMessage()
							),
						)
					);
					return;
				}
				break;
			case 'dbcache':
				Util_WpFile::delete_file( W3TC_ADDIN_FILE_DB );
				break;
			case 'objectcache':
				Util_WpFile::delete_file( W3TC_ADDIN_FILE_OBJECT_CACHE );
				break;
		}
		Util_Admin::redirect(
			array(
				'w3tc_note' => 'add_in_removed',
			),
			true
		);
	}

	/**
	 * Saves configuration options and processes the save request.
	 *
	 * @return void
	 */
	public function w3tc_save_options() {
		$this->_require_admin_cap();
		$redirect_data = $this->_w3tc_save_options_process();
		Util_Admin::redirect_with_custom_messages2( $redirect_data );
	}

	/**
	 * Saves configuration options, flushes caches, and updates necessary states.
	 *
	 * @return void
	 */
	public function w3tc_default_save_and_flush() {
		$this->_require_admin_cap();
		$redirect_data = $this->_w3tc_save_options_process();

		$f = Dispatcher::component( 'CacheFlush' );
		$f->flush_all();

		$state_note = Dispatcher::config_state_note();
		$state_note->set( 'common.show_note.flush_statics_needed', false );
		$state_note->set( 'common.show_note.flush_posts_needed', false );
		$state_note->set( 'common.show_note.plugins_updated', false );
		$state_note->set( 'minify.show_note.need_flush', false );
		$state_note->set( 'objectcache.show_note.flush_needed', false );

		Util_Admin::redirect_with_custom_messages2( $redirect_data );
	}

	/**
	 * Processes saving options for the W3 Total Cache plugin.
	 *
	 * @return array
	 */
	private function _w3tc_save_options_process() {
		$w3tc_data = array(
			'old_config'            => $this->_config,
			'response_query_string' => array(),
			'response_actions'      => array(),
			'response_errors'       => array(),
			'response_notes'        => array( 'config_save' ),
		);

		// if we are on extension settings page - stay on the same page.
		if ( 'w3tc_extensions' === Util_Request::get_string( 'page' ) ) {
			$w3tc_data['response_query_string']['page']      = Util_Request::get_string( 'page' );
			$w3tc_data['response_query_string']['extension'] = Util_Request::get_string( 'extension' );
			$w3tc_data['response_query_string']['action']    = Util_Request::get_string( 'action' );
		}

		/**
		 * Floor the filterable cap at manage_options so a downstream
		 * filter cannot lower the gate below admin.
		 *
		 * @since 2.10.0
		 */
		$capability = apply_filters( 'w3tc_capability_config_save', 'manage_options' );
		if ( ! \current_user_can( 'manage_options' ) || empty( $capability ) || ! \current_user_can( $capability ) ) {
			wp_die( esc_html__( 'You do not have the rights to perform this action.', 'w3-total-cache' ) );
		}

		/**
		 * Read config
		 * We should use new instance of WP_Config object here
		 */
		$w3tc_config = new Config();
		$this->read_request( $w3tc_config );

		/**
		 * General tab
		 */
		if ( 'w3tc_general' === $this->_page ) {
			$file_nfs     = Util_Request::get_boolean( 'file_nfs' );
			$file_locking = Util_Request::get_boolean( 'file_locking' );

			$w3tc_config->set( 'pgcache.file.nfs', $file_nfs );
			$w3tc_config->set( 'minify.file.nfs', $file_nfs );

			$w3tc_config->set( 'dbcache.file.locking', $file_locking );
			$w3tc_config->set( 'objectcache.file.locking', $file_locking );
			$w3tc_config->set( 'pgcache.file.locking', $file_locking );
			$w3tc_config->set( 'minify.file.locking', $file_locking );

			if ( is_network_admin() ) {
				if ( ( $this->_config->get_boolean( 'common.force_master' ) !== $w3tc_config->get_boolean( 'common.force_master' ) ) ) {
					// blogmap is wrong so empty it.
					@unlink( W3TC_CACHE_BLOGMAP_FILENAME );
					$blogmap_dir = dirname( W3TC_CACHE_BLOGMAP_FILENAME ) . '/' . basename( W3TC_CACHE_BLOGMAP_FILENAME, '.php' ) . '/';
					if ( @is_dir( $blogmap_dir ) ) {
						Util_File::rmdir( $blogmap_dir );
					}
				}
			}

			/**
			 * Check permalinks for page cache
			 */
			if ( $w3tc_config->get_boolean( 'pgcache.enabled' ) &&
				'file_generic' === $w3tc_config->get_string( 'pgcache.engine' ) &&
				! get_option( 'permalink_structure' ) ) {

				$w3tc_config->set( 'pgcache.enabled', false );
				$w3tc_data['response_errors'][] = 'fancy_permalinks_disabled_pgcache';
			}

			/**
			 * Check for Object Cache using Disk being disabled or changed to another engine.
			 *
			 * @since 2.8.6
			 */
			if (
				$this->_config->get_boolean( 'objectcache.enabled' ) && 'file' === $this->_config->get_string( 'objectcache.engine' ) &&
				( ! $w3tc_config->get_boolean( 'objectcache.enabled' ) || 'file' !== $w3tc_config->get_string( 'objectcache.engine' ) )
			) {
				Util_File::rmdir( Util_Environment::cache_blog_dir( 'object' ) );
			}

			/**
			 * Check for Image Service extension status changes.
			 */
			if ( $w3tc_config->get_boolean( 'extension.imageservice' ) !== $this->_config->get_boolean( 'extension.imageservice' ) ) {
				if ( $w3tc_config->get_boolean( 'extension.imageservice' ) ) {
					Extensions_Util::activate_extension( 'imageservice', $w3tc_config );
				} else {
					Extensions_Util::deactivate_extension( 'imageservice', $w3tc_config );
				}
			}
		}

		/**
		 * Minify tab
		 */
		if ( 'w3tc_minify' === $this->_page ) {
			if ( ( $this->_config->get_boolean( 'minify.js.http2push' ) && ! $w3tc_config->get_boolean( 'minify.js.http2push' ) ) ||
			( $this->_config->get_boolean( 'minify.css.http2push' ) && ! $w3tc_config->get_boolean( 'minify.css.http2push' ) ) ) {
				if ( 'file_generic' === $w3tc_config->get_string( 'pgcache.engine' ) ) {
					$cache_dir = Util_Environment::cache_blog_dir( 'page_enhanced' );
					$this->_delete_all_htaccess_files( $cache_dir );
				}
			}

			if ( ! $this->_config->get_boolean( 'minify.auto' ) ) {
				$js_groups  = array();
				$css_groups = array();

				$w3tc_js_files  = Util_Request::get_array( 'js_files' );
				$w3tc_css_files = Util_Request::get_array( 'css_files' );

				foreach ( $w3tc_js_files as $theme => $templates ) {
					foreach ( $templates as $template => $locations ) {
						foreach ( (array) $locations as $location => $types ) {
							foreach ( (array) $types as $files ) {
								foreach ( (array) $files as $w3tc_file ) {
									if ( ! empty( $w3tc_file ) ) {
										$js_groups[ $theme ][ $template ][ $location ]['files'][] =
											Util_Environment::normalize_file_minify( $w3tc_file );
									}
								}
							}
						}
					}
				}

				foreach ( $w3tc_css_files as $theme => $templates ) {
					foreach ( $templates as $template => $locations ) {
						foreach ( (array) $locations as $location => $files ) {
							foreach ( (array) $files as $w3tc_file ) {
								if ( ! empty( $w3tc_file ) ) {
									$css_groups[ $theme ][ $template ][ $location ]['files'][] =
										Util_Environment::normalize_file_minify( $w3tc_file );
								}
							}
						}
					}
				}

				$w3tc_config->set( 'minify.js.groups', $js_groups );
				$w3tc_config->set( 'minify.css.groups', $css_groups );

				$w3tc_js_theme  = Util_Request::get_string( 'js_theme' );
				$w3tc_css_theme = Util_Request::get_string( 'css_theme' );

				$w3tc_data['response_query_string']['js_theme']  = $w3tc_js_theme;
				$w3tc_data['response_query_string']['css_theme'] = $w3tc_css_theme;
			}
		}

		/**
		 * Browser Cache tab
		 */
		if ( 'w3tc_browsercache' === $this->_page ) {
			if ( $w3tc_config->get_boolean( 'browsercache.enabled' ) &&
				$w3tc_config->get_boolean( 'browsercache.no404wp' ) &&
				! get_option( 'permalink_structure' ) ) {

				$w3tc_config->set( 'browsercache.no404wp', false );
				$w3tc_data['response_errors'][] = 'fancy_permalinks_disabled_browsercache';
			}
		}

		/**
		 * CDN tab
		 */
		if ( 'w3tc_cdn' === $this->_page ) {
			$cdn_cnames  = Util_Request::get_array( 'cdn_cnames' );
			$cdn_domains = array();

			foreach ( $cdn_cnames as $cdn_cname ) {
				$cdn_cname = preg_replace( '~[^0-9a-zA-Z/_.:\-]~', '', wp_strip_all_tags( $cdn_cname ) );

				/**
				 * Auto expand wildcard domain to 10 subdomains
				 */
				$matches = null;

				if ( preg_match( '~^\*\.(.*)$~', $cdn_cname, $matches ) ) {
					$cdn_domains = array();

					for ( $w3tc_i = 1; $w3tc_i <= 10; $w3tc_i++ ) {
						$cdn_domains[] = sprintf( 'cdn%d.%s', $w3tc_i, $matches[1] );
					}

					break;
				}

				if ( $cdn_cname ) {
					$cdn_domains[] = $cdn_cname;
				}
			}

			switch ( $this->_config->get_string( 'cdn.engine' ) ) {
				case 'azure':
					$w3tc_config->set( 'cdn.azure.cname', $cdn_domains );
					break;

				case 'azuremi':
					$w3tc_config->set( 'cdn.azuremi.cname', $cdn_domains );
					break;

				case 'cf':
					$w3tc_config->set( 'cdn.cf.cname', $cdn_domains );
					break;

				case 'cf2':
					$w3tc_config->set( 'cdn.cf2.cname', $cdn_domains );
					break;

				case 'ftp':
					$w3tc_config->set( 'cdn.ftp.domain', $cdn_domains );
					break;

				case 'mirror':
					$w3tc_config->set( 'cdn.mirror.domain', $cdn_domains );
					break;

				case 'rackspace_cdn':
					$w3tc_config->set( 'cdn.rackspace_cdn.domains', $cdn_domains );
					break;

				case 'rscf':
					$w3tc_config->set( 'cdn.rscf.cname', $cdn_domains );
					break;

				case 's3':
				case 's3_compatible':
					$w3tc_config->set( 'cdn.s3.cname', $cdn_domains );
					break;
			}
		}

		$old_ext_settings = $this->_config->get_array( 'extensions.settings', array() );
		$new_ext_settings = $old_ext_settings;
		$modified         = false;

		$extensions = Extensions_Util::get_extensions( $w3tc_config );
		foreach ( $extensions as $w3tc_extension => $w3tc_descriptor ) {
			$request = Util_Request::get_as_array( 'extensions.settings.' . $w3tc_extension . '.' );
			if ( count( $request ) > 0 ) {
				if ( ! isset( $new_ext_settings[ $w3tc_extension ] ) ) {
					$new_ext_settings[ $w3tc_extension ] = array();
				}

				foreach ( $request as $w3tc_key => $w3tc_value ) {
					if ( ! isset( $old_ext_settings[ $w3tc_extension ] ) ||
						! isset( $old_ext_settings[ $w3tc_extension ][ $w3tc_key ] ) ||
						$old_ext_settings[ $w3tc_extension ][ $w3tc_key ] !== $w3tc_value ) {

						$new_ext_settings[ $w3tc_extension ][ $w3tc_key ] = $w3tc_value;
						$modified = true;
					}
				}
			}
		}

		if ( $modified ) {
			$w3tc_config->set( 'extensions.settings', $new_ext_settings );
		}

		$w3tc_data['new_config'] = $w3tc_config;
		$w3tc_data               = apply_filters( 'w3tc_save_options', $w3tc_data, $this->_page );
		$w3tc_config             = $w3tc_data['new_config'];

		do_action( 'w3tc_config_ui_save', $w3tc_config, $this->_config );
		do_action( "w3tc_config_ui_save-{$this->_page}", $w3tc_config, $this->_config );

		Util_Admin::config_save( $this->_config, $w3tc_config );

		if ( 'w3tc_cdn' === $this->_page ) {
			/**
			 * Handle Set Cookie Domain
			 */
			$set_cookie_domain_old = Util_Request::get_boolean( 'set_cookie_domain_old' );
			$set_cookie_domain_new = Util_Request::get_boolean( 'set_cookie_domain_new' );

			if ( $set_cookie_domain_old !== $set_cookie_domain_new ) {
				if ( $set_cookie_domain_new ) {
					if ( ! $this->enable_cookie_domain() ) {
						Util_Admin::redirect(
							array_merge(
								$w3tc_data['response_query_string'],
								array(
									'w3tc_error' => 'enable_cookie_domain',
								)
							)
						);
					}
				} elseif ( ! $this->disable_cookie_domain() ) {
					Util_Admin::redirect(
						array_merge(
							$w3tc_data['response_query_string'],
							array(
								'w3tc_error' => 'disable_cookie_domain',
							)
						)
					);
				}
			}
		}

		return array(
			'query_string' => $w3tc_data['response_query_string'],
			'actions'      => $w3tc_data['response_actions'],
			'errors'       => $w3tc_data['response_errors'],
			'notes'        => $w3tc_data['response_notes'],
		);
	}

	/**
	 * Deletes all .htaccess files in the specified directory and its subdirectories.
	 *
	 * @param string $dir Directory path where .htaccess files will be deleted.
	 *
	 * @return void
	 */
	private function _delete_all_htaccess_files( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$handle = opendir( $dir );
		if ( false === $handle ) {
			return;
		}

		while ( true ) {
			$w3tc_file = readdir( $handle );
			if ( false === $w3tc_file ) {
				break;
			}

			if ( '.' === $w3tc_file || '..' === $w3tc_file ) {
				continue;
			}

			if ( is_dir( $w3tc_file ) ) {
				$this->_delete_all_htaccess_files( $w3tc_file );
				continue;
			} elseif ( '.htaccess' === $w3tc_file ) {
				@unlink( $dir . '/' . $w3tc_file );
			}
		}

		closedir( $handle );
	}

	/**
	 * Enables COOKIE_DOMAIN by modifying the wp-config.php file.
	 *
	 * @return bool True if COOKIE_DOMAIN is successfully enabled, false otherwise.
	 */
	public function enable_cookie_domain() {
		WP_Filesystem();

		global $wp_filesystem;

		$config_path = Util_Environment::wp_config_path();
		$config_data = $wp_filesystem->get_contents( $config_path );

		if ( false === $config_data ) {
			return false;
		}

		$cookie_domain = Util_Admin::get_cookie_domain();

		if ( $this->is_cookie_domain_define( $config_data ) ) {
			$new_config_data = preg_replace(
				W3TC_PLUGIN_TOTALCACHE_REGEXP_COOKIEDOMAIN,
				"define('COOKIE_DOMAIN', '" . addslashes( $cookie_domain ) . "')",
				$config_data,
				1
			);
		} else {
			$new_config_data = preg_replace(
				'~<\?(php)?~',
				"\\0\r\ndefine('COOKIE_DOMAIN', '" . addslashes( $cookie_domain ) .
					"'); // " . __( 'Added by W3 Total Cache', 'w3-total-cache' ) . "\r\n",
				$config_data,
				1
			);
		}

		if ( $new_config_data !== $config_data ) {
			if ( ! $wp_filesystem->put_contents( $config_path, $new_config_data ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Disables COOKIE_DOMAIN by modifying the wp-config.php file.
	 *
	 * @return bool True if COOKIE_DOMAIN is successfully disabled, false otherwise.
	 */
	public function disable_cookie_domain() {
		WP_Filesystem();

		global $wp_filesystem;

		$config_path = Util_Environment::wp_config_path();
		$config_data = $wp_filesystem->get_contents( $config_path );

		if ( false === $config_data ) {
			return false;
		}

		if ( $this->is_cookie_domain_define( $config_data ) ) {
			$new_config_data = preg_replace(
				W3TC_PLUGIN_TOTALCACHE_REGEXP_COOKIEDOMAIN,
				"define('COOKIE_DOMAIN', false)",
				$config_data,
				1
			);

			if ( $new_config_data !== $config_data ) {
				if ( ! $wp_filesystem->put_contents( $config_path, $new_config_data ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Checks if COOKIE_DOMAIN is defined in the given configuration content.
	 *
	 * @param string $content The configuration file content to check.
	 *
	 * @return int|bool True if COOKIE_DOMAIN is defined, false otherwise.
	 */
	public function is_cookie_domain_define( $content ) {
		return preg_match( W3TC_PLUGIN_TOTALCACHE_REGEXP_COOKIEDOMAIN, $content );
	}

	/**
	 * Checks if a configuration section is sealed.
	 *
	 * @param string $section The section name to check.
	 *
	 * @return bool Always returns true, indicating the section is sealed.
	 */
	protected function is_sealed( $section ) {
		return true;
	}

	/**
	 * Reads configuration settings from a request and updates the configuration object.
	 *
	 * The schema in `ConfigKeys.php` (queried via {@see ConfigKeysSchema})
	 * is treated as an allowlist:
	 *
	 *  - Request keys that do not map to a known schema entry are dropped
	 *    silently. Previously every request key was written into config
	 *    regardless of whether ConfigKeys.php declared it.
	 *  - Compound keys (`extension__<id>__<sub>`) — written through the
	 *    `w3tc_config_key_descriptor` filter by extensions — are still
	 *    accepted; their gate is the extension's own filter.
	 *  - Values are type-coerced from the schema before write, so a
	 *    boolean-typed key always becomes `true`/`false`.
	 *
	 * @param object $w3tc_config Configuration object to update.
	 *
	 * @return void
	 */
	public function read_request( $w3tc_config ) {
		$request = Util_Request::get_request();

		foreach ( $request as $request_key => $request_value ) {
			/**
			 * The `{name}__w3tc_clear` companion fields rendered next to
			 * masked secret inputs by `Util_Ui::secret_input()` are
			 * processed alongside their parent key in the secret block
			 * below — skip them here so they don't get treated as
			 * standalone config keys (the descriptor lookup would miss
			 * and they'd fall through to a stray `$w3tc_config->set()`).
			 */
			if ( '__w3tc_clear' === substr( (string) $request_key, -12 ) ) {
				continue;
			}

			if ( is_array( $request_value ) ) {
				$request_value = array_map( 'stripslashes_deep', $request_value );
			} else {
				$request_value = stripslashes( $request_value );
			}

			if ( 'extension__' === substr( $request_key, 0, 11 ) ) {
				$extension_id = Util_Ui::config_key_from_http_name( substr( $request_key, 11 ) );

				if ( '1' === $request_value ) {
					Extensions_Util::activate_extension( $extension_id, $w3tc_config, true );
				} else {
					Extensions_Util::deactivate_extension( $extension_id, $w3tc_config, true );
				}
			}

			$w3tc_key        = Util_Ui::config_key_from_http_name( $request_key );
			$w3tc_descriptor = ConfigKeysSchema::descriptor( $w3tc_key );

			/**
			 * This filter is needed for compound keys to set the appropirate data type to save as.
			 * Mainly used by extensions with textarea fields that don't feature a ConfigKeys entry.
			 * If no filter exists to define such fields it will save as a string, requiring post-processing.
			 *
			 * @since 2.4.2
			 *
			 * @param mixed $w3tc_descriptor Array containing correct data type or null if not matched.
			 * @param array $w3tc_key        Key to match on.
			*/
			$w3tc_descriptor = apply_filters( 'w3tc_config_key_descriptor', $w3tc_descriptor, $w3tc_key );

			/**
			 * Strict allowlist gate: a key reaches `$w3tc_config->set()` only if
			 * either (a) it's a compound extension key (the extension owns its
			 * own gate via the filter above), or (b) the schema knows it or
			 * the filter supplied a descriptor for it.
			 */
			if ( ! is_array( $w3tc_key ) && null === $w3tc_descriptor && ! ConfigKeysSchema::is_known( $w3tc_key ) ) {
				continue;
			}

			/**
			 * RT9-210: Page-boundary discipline for high-impact CDN
			 * credential namespaces.
			 *
			 * The strict-allowlist gate above stops *unknown* keys from
			 * landing in config, but every per-engine CDN credential key
			 * (S3, CloudFront, Azure, FTP, RackSpace, Google Drive, etc.)
			 * IS in the schema — so a known-key allowlist alone lets an
			 * admin viewing any W3TC submenu (General, Page Cache, Object
			 * Cache, …) POST `cdn__s3__secret=foo` along with their save
			 * and silently overwrite the configured CDN credentials. The
			 * proof for this finding lit up 8/8 CDN engines from the
			 * General page POST.
			 *
			 * The fix is a namespace → page-slug map. When the key falls
			 * into one of the per-engine CDN credential namespaces, the
			 * write is only accepted if `$this->_page` matches the
			 * legitimate page where that engine's form lives. The CDN
			 * Settings UI lives at `w3tc_cdn`; the full-site-delivery
			 * variant at `w3tc_cdnfsd`. Top-level keys like `cdn.enabled`
			 * / `cdn.engine` (toggled from General) are not prefixed by
			 * an engine slug and stay writable as before — only the
			 * `cdn.<engine>.*` / `cdnfsd.<engine>.*` subtree is gated.
			 *
			 * The check sits AFTER the allowlist (so an attacker can't
			 * smuggle an unknown key past it) and BEFORE the secret /
			 * type-coercion paths (so a cross-page write is dropped
			 * before any sensitive transformation runs).
			 */
			if ( is_string( $w3tc_key ) ) {
				$expected_page = self::_credential_namespace_page( $w3tc_key );
				if ( null !== $expected_page && $expected_page !== $this->_page ) {
					continue;
				}
			}

			if ( isset( $w3tc_descriptor['type'] ) ) {
				if ( 'array' === $w3tc_descriptor['type'] ) {
					if ( is_array( $request_value ) ) {
						// This is needed for radio inputs.
						$request_value = implode( "\n", $request_value );
					}
					$request_value = Util_Environment::textarea_to_array( $request_value );
				} elseif ( 'boolean' === $w3tc_descriptor['type'] ) {
					$request_value = ( '1' === $request_value );
				} elseif ( 'integer' === $w3tc_descriptor['type'] ) {
					$request_value = (int) $request_value;
				}
			}

			$is_secret = (
				is_array( $w3tc_descriptor )
				&& isset( $w3tc_descriptor['flags'] )
				&& is_array( $w3tc_descriptor['flags'] )
				&& ! empty( $w3tc_descriptor['flags']['secret'] )
			);

			if ( $is_secret ) {
				/**
				 * Explicit-clear path: a companion `{name}__w3tc_clear`
				 * checkbox submitted with value `1` is the ONLY way to
				 * wipe a stored secret through the admin UI — see
				 * `Util_Ui::secret_input()` for the rendering side.
				 * Checked BEFORE the empty-POST-preserves-secret rule
				 * below so admins can drop a credential (e.g. revoke a
				 * Pro license, replace an S3 key after rotation) without
				 * needing to type a placeholder value first.
				 *
				 * The downstream `Config::save()` then fires the usual
				 * `w3tc_config_ui_save-w3tc_general` hook, so e.g.
				 * `Licensing_Plugin_Admin::possible_state_change()`
				 * still observes the `! $new_key_set && $old_key_set`
				 * branch and deactivates the license against EDD.
				 */
				$clear_key = $request_key . '__w3tc_clear';
				if ( isset( $request[ $clear_key ] ) && '1' === (string) $request[ $clear_key ] ) {
					$w3tc_config->set( $w3tc_key, '' );
					continue;
				}

				/**
				 * Empty-POST-preserves-secret: masked secret inputs
				 * always submit `value=""`, so an admin saving Settings
				 * without rotating the credential MUST not blank the
				 * stored value. Skip the set() entirely; the existing
				 * encrypted blob on disk is preserved.
				 */
				if ( ! is_string( $request_value ) || '' === $request_value ) {
					continue;
				}
			}

			$w3tc_config->set( $w3tc_key, $request_value );
		}
	}

	/**
	 * Map a CDN-engine credential namespace to the admin page slug
	 * that legitimately renders the form for those keys. Returns
	 * `null` for keys outside the gated namespaces — the caller treats
	 * `null` as "no page-boundary requirement, write normally".
	 *
	 * Each entry is an exact-prefix match against the dotted config
	 * key. `cdn.s3.*` keys (S3 Access Key, Secret, Bucket, CNAME, …)
	 * map to `w3tc_cdn`; `cdnfsd.cloudfront.*` keys (full-site
	 * delivery CloudFront access/secret) map to `w3tc_cdnfsd`. New
	 * engines added to the schema MUST be added here too — the rule
	 * is positive-list, not derived from the schema, so a future
	 * engine without an entry would default to the previous unsafe
	 * "any logged-in admin on any W3TC page can write the
	 * credentials" behaviour. A short test of `w3tc_cdn_test` /
	 * `w3tc_cdn_credentials_required` confirms missing engines.
	 *
	 * @since 2.10.0
	 *
	 * @param string $w3tc_key Dotted config key.
	 *
	 * @return string|null Expected admin page slug, or null if no
	 *                    page-boundary constraint applies.
	 */
	private static function _credential_namespace_page( $w3tc_key ) {
		static $map = null;
		if ( null === $map ) {
			$map = array(
				// Standard CDN engines (Settings → CDN).
				'cdn.ftp.'               => 'w3tc_cdn',
				'cdn.google_drive.'      => 'w3tc_cdn',
				'cdn.s3.'                => 'w3tc_cdn',
				'cdn.s3_compatible.'     => 'w3tc_cdn',
				'cdn.cf.'                => 'w3tc_cdn',
				'cdn.cf2.'               => 'w3tc_cdn',
				'cdn.rscf.'              => 'w3tc_cdn',
				'cdn.rackspace_cdn.'     => 'w3tc_cdn',
				'cdn.azure.'             => 'w3tc_cdn',
				'cdn.azuremi.'           => 'w3tc_cdn',
				'cdn.bunnycdn.'          => 'w3tc_cdn',
				'cdn.transparentcdn.'    => 'w3tc_cdn',

				// Full-site-delivery CDN engines (Settings → CDN FSD).
				'cdnfsd.cloudfront.'     => 'w3tc_cdnfsd',
				'cdnfsd.bunnycdn.'       => 'w3tc_cdnfsd',
				'cdnfsd.transparentcdn.' => 'w3tc_cdnfsd',
			);
		}
		foreach ( $map as $prefix => $page ) {
			if ( 0 === \strpos( $w3tc_key, $prefix ) ) {
				return $page;
			}
		}
		return null;
	}
}
