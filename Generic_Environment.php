<?php
/**
 * FIle: Generic_Environment.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Generic_Environment
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class Generic_Environment {
	/**
	 * Fixes environment
	 *
	 * @param Config $w3tc_config           Config.
	 * @param bool   $force_all_checks Force checks flag.
	 *
	 * @throws Util_Environment_Exceptions Exceptions.
	 */
	public function fix_on_wpadmin_request( $w3tc_config, $force_all_checks ) {
		$exs = new Util_Environment_Exceptions();

		// create add-ins.
		$this->create_required_files( $w3tc_config, $exs );

		// create folders.
		$this->create_required_folders( $exs );
		$this->add_index_to_folders();

		/**
		 * Maintain the nginx plugin-dir deny block. Apache and
		 * LiteSpeed get the same defense via the shipped
		 * pub/.htaccess and ini/.htaccess; nginx ignores those, so
		 * we have to write equivalent location rules into the
		 * W3TC-managed nginx.conf.
		 */
		if ( Util_Environment::is_nginx() ) {
			$this->rules_plugin_dir_deny_add( $exs );
		}

		if ( count( $exs->exceptions() ) <= 0 ) {
			// save actual version of config is it's built on legacy configs.
			$f  = ConfigUtil::is_item_exists( 0, false );
			$f2 = file_exists( Config::util_config_filename_legacy_v2( 0, false ) );

			$w3tc_c = Dispatcher::config_master();
			if ( ( $f || $f2 ) && $w3tc_c->is_compiled() ) {
				$w3tc_c->save();
				$f = ConfigUtil::is_item_exists( 0, false );
			}

			if ( $f && $f2 ) {
				@unlink( Config::util_config_filename_legacy_v2( 0, false ) );
			}
		}

		if ( count( $exs->exceptions() ) > 0 ) {
			throw $exs;
		}
	}

	/**
	 * Fixes environment once event occurs
	 *
	 * @param Config      $w3tc_config     Config.
	 * @param string      $event      Event.
	 * @param null|Config $old_config Old Config.
	 *
	 * @throws Util_Environment_Exceptions Exceptions.
	 */
	public function fix_on_event( $w3tc_config, $event, $old_config = null ) {
	}

	/**
	 * Fixes environment after plugin deactivation
	 *
	 * @throws Util_Environment_Exceptions Environment exception.
	 *
	 * @return void
	 */
	public function fix_after_deactivation() {
		$exs = new Util_Environment_Exceptions();

		$this->delete_required_files( $exs );

		$this->unschedule_purge_wpcron();

		/**
		 * Strip the nginx plugin-dir deny block on deactivation so
		 * the W3TC-managed nginx.conf isn't left holding rules for
		 * a plugin that no longer ships them.
		 */
		if ( Util_Environment::is_nginx() ) {
			$this->rules_plugin_dir_deny_remove( $exs );
		}

		if ( count( $exs->exceptions() ) > 0 ) {
			throw $exs;
		}
	}

	/**
	 * Returns required rules for module.
	 *
	 * On nginx, emits a deny block scoped to the plugin's `pub/` and
	 * `ini/` subdirectories. The corresponding defense lives in
	 * `pub/.htaccess` and `ini/.htaccess` on Apache and LiteSpeed (both
	 * of which honour per-directory `.htaccess`); nginx ignores
	 * `.htaccess` entirely, so without an equivalent `location ~` block
	 * in nginx.conf an attacker on an nginx-only install could still
	 * GET `ini/config-db-sample.php` or POST to any future
	 * `pub/<something>.php` directly. The only allowed `pub/*.php`
	 * entrypoint is `sns.php` (the SNS webhook handler), which has its
	 * own pre-bootstrap signature gate.
	 *
	 * Markers `W3TC_MARKER_BEGIN_PLUGIN_DIR_DENY` /
	 * `W3TC_MARKER_END_PLUGIN_DIR_DENY` allow the environment writer
	 * to replace the block on upgrade without rewriting the whole
	 * `nginx.conf`.
	 *
	 * @since 2.10.0
	 *
	 * @param Config $w3tc_config Config.
	 *
	 * @return array|null Array of `{filename, content}` descriptors,
	 *                    or null when no environment rules are needed.
	 */
	public function get_required_rules( $w3tc_config ) {
		if ( Util_Environment::is_nginx() ) {
			return array(
				array(
					'filename' => Util_Rule::get_nginx_rules_path(),
					'content'  => $this->generate_nginx_plugin_dir_deny(),
				),
			);
		}

		/**
		 * Apache and LiteSpeed get the per-directory deny via the
		 * shipped `pub/.htaccess` and `ini/.htaccess` — no rewrite
		 * rule emission needed here.
		 */
		return null;
	}

	/**
	 * Build the nginx deny block for W3TC paths that must never be
	 * served directly: the plugin's `pub/` and `ini/` directories and
	 * the debug-log directory.
	 *
	 * The plugin-dir rules use anchored, case-insensitive regex
	 * `location ~*` directives so they match regardless of whether
	 * the site is installed at the document root, under a subpath,
	 * or in a custom plugins directory — as long as the URI still
	 * contains `/w3-total-cache/` (the plugin folder name is fixed
	 * by the WordPress.org plugin slug and is the same across every
	 * supported install layout).
	 *
	 * `(?!sns\.php$)` is a PCRE negative lookahead, which nginx
	 * supports because it links against PCRE/PCRE2. The
	 * `[^/]+\.php$` tail prevents the rule from matching
	 * arbitrarily-deep paths under `pub/` — only the immediate-child
	 * `.php` files (the same scope `pub/.htaccess` covers via
	 * `FilesMatch "\.php$"`).
	 *
	 * The debug-log rule denies the cache log directory
	 * (`wp-content/cache/log/` by default), where modules such as the
	 * CDN, page cache, and minify write JSON operation dumps when
	 * their `*.debug` flags are on. On Apache / LiteSpeed the log
	 * directory's shipped `.htaccess` (`Require all denied`, written by
	 * `Util_Debug::log_filename()` → `Util_File::check_htaccess()`)
	 * keeps those logs unreadable; nginx ignores `.htaccess`, so
	 * without this an attacker on an nginx install with debug logging
	 * enabled could read file paths, request URIs, and — via the
	 * log_purge path — potentially leaked nonces. The rule is only
	 * emitted when the log directory resolves inside the document root
	 * (the default layout); a cache directory relocated outside the
	 * docroot is not web-reachable and needs no rule.
	 *
	 * @since 2.10.0
	 *
	 * @return string Nginx config fragment delimited by the
	 *                W3TC_MARKER_BEGIN_PLUGIN_DIR_DENY /
	 *                W3TC_MARKER_END_PLUGIN_DIR_DENY markers.
	 */
	private function generate_nginx_plugin_dir_deny() {
		$rules  = W3TC_MARKER_BEGIN_PLUGIN_DIR_DENY . "\n";
		$rules .= "# Deny every file under the plugin's ini/ sample-config\n";
		$rules .= "# directory. These are templates meant to be copied by a\n";
		$rules .= "# sysadmin, never served directly.\n";
		$rules .= "location ~* /w3-total-cache/ini/ {\n";
		$rules .= "    deny all;\n";
		$rules .= "}\n";
		$rules .= "\n";
		$rules .= "# Deny every immediate-child .php file under the plugin's\n";
		$rules .= "# pub/ directory EXCEPT sns.php — the SNS webhook handler\n";
		$rules .= "# is the only allowed public entrypoint and has its own\n";
		$rules .= "# pre-bootstrap signature gate.\n";
		$rules .= "location ~* /w3-total-cache/pub/(?!sns\\.php$)[^/]+\\.php$ {\n";
		$rules .= "    deny all;\n";
		$rules .= "}\n";

		/**
		 * Deny the W3TC debug-log directory (Apache / LiteSpeed enforce
		 * this through the log dir's shipped .htaccess; nginx needs the
		 * equivalent location block). Skip when the log directory is not
		 * under the document root — it is then unreachable over HTTP.
		 */
		$docroot = Util_Environment::normalize_path( Util_Environment::document_root() );
		$log_dir = Util_Environment::normalize_path( Util_Environment::cache_dir( 'log' ) );
		$log_uri = str_replace( $docroot, '', $log_dir );
		if ( $log_uri !== $log_dir && 0 === strpos( $log_uri, '/' ) ) {
			$rules .= "\n";
			$rules .= "# Deny the W3TC debug-log directory (cdn.log, pgcache.log,\n";
			$rules .= "# minify.log, etc.). These hold internal operation dumps and\n";
			$rules .= "# must never be served, regardless of the obscuring hash in\n";
			$rules .= "# the subdirectory name.\n";
			$rules .= 'location ~* ' . $log_uri . "/ {\n";
			$rules .= "    deny all;\n";
			$rules .= "}\n";
		}

		$rules .= W3TC_MARKER_END_PLUGIN_DIR_DENY . "\n";

		return $rules;
	}

	/**
	 * Write the nginx plugin-dir deny block into the W3TC-managed
	 * `nginx.conf` (or replace the existing block if one is already
	 * present). Idempotent: `Util_Rule::add_rules()` is a no-op when
	 * the rule set is already up to date, so this can run on every
	 * admin request without thrashing the file.
	 *
	 * Ordering: the deny block is placed before the page-cache /
	 * minify rule blocks because location matching in nginx is
	 * regex-most-specific-wins, but to keep the file diff readable
	 * for operators we anchor it near the top of the file when no
	 * earlier W3TC block exists.
	 *
	 * @since 2.10.0
	 *
	 * @param Util_Environment_Exceptions $exs Environment exceptions accumulator.
	 *
	 * @return void
	 */
	private function rules_plugin_dir_deny_add( $exs ) {
		Util_Rule::add_rules(
			$exs,
			Util_Rule::get_nginx_rules_path(),
			$this->generate_nginx_plugin_dir_deny(),
			W3TC_MARKER_BEGIN_PLUGIN_DIR_DENY,
			W3TC_MARKER_END_PLUGIN_DIR_DENY,
			array(
				W3TC_MARKER_BEGIN_PGCACHE_CORE       => 0,
				W3TC_MARKER_BEGIN_BROWSERCACHE_CACHE => 0,
				W3TC_MARKER_BEGIN_MINIFY_CORE        => 0,
				W3TC_MARKER_BEGIN_WORDPRESS          => 0,
			)
		);
	}

	/**
	 * Strip the nginx plugin-dir deny block on plugin deactivation.
	 * Passing empty content to `Util_Rule::add_rules()` puts the
	 * helper into removal mode (its documented contract).
	 *
	 * @since 2.10.0
	 *
	 * @param Util_Environment_Exceptions $exs Environment exceptions accumulator.
	 *
	 * @return void
	 */
	private function rules_plugin_dir_deny_remove( $exs ) {
		Util_Rule::add_rules(
			$exs,
			Util_Rule::get_nginx_rules_path(),
			'',
			W3TC_MARKER_BEGIN_PLUGIN_DIR_DENY,
			W3TC_MARKER_END_PLUGIN_DIR_DENY,
			array()
		);
	}

	/**
	 * Checks if addins in wp-content is available and correct version.
	 *
	 * @param unknown                     $w3tc_config Config.
	 * @param Util_Environment_Exceptions $exs    Enfironment exceptions.
	 *
	 * @return void
	 */
	private function create_required_files( $w3tc_config, $exs ) {
		$src = W3TC_INSTALL_FILE_ADVANCED_CACHE;
		$dst = W3TC_ADDIN_FILE_ADVANCED_CACHE;

		if ( $this->advanced_cache_installed() ) {
			if ( $this->is_advanced_cache_add_in() ) {
				if ( @hash_file( 'sha256', $src ) === @hash_file( 'sha256', $dst ) ) {
					return;
				}
			} elseif ( 'yes' === get_transient( 'w3tc_remove_add_in_pgcache' ) ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedElseif
				/**
				 * User already manually asked to remove another plugin's add in, we should
				 * try to apply ours (in case of missing permissions deletion could fail).
				 */
			} elseif ( ! $this->advanced_cache_check_old_add_in() ) {
				$remove_url = Util_Ui::admin_url( 'admin.php?page=w3tc_dashboard&amp;w3tc_default_remove_add_in=pgcache' );

				$exs->push(
					new Util_WpFile_FilesystemOperationException(
						sprintf(
							// Translators: 1 button link.
							__(
								'The Page Cache add-in file advanced-cache.php is not a W3 Total Cache drop-in. It should be removed. %s',
								'w3-total-cache'
							),
							Util_Ui::button_link(
								__( 'Yes, remove it for me', 'w3-total-cache' ),
								Util_Nonce::admin_nonce_url( $remove_url, 'w3tc_default_remove_add_in' )
							)
						)
					)
				);
				return;
			}
		}

		try {
			Util_WpFile::copy_file( $src, $dst );
		} catch ( Util_WpFile_FilesystemOperationException $ex ) {
			$exs->push( $ex );
		}
	}

	/**
	 * Checks if addins in wp-content are available and deletes them.
	 *
	 * @param Util_Environment_Exceptions $exs Environment exceptions.
	 *
	 * @return void
	 */
	private function delete_required_files( $exs ) {
		try {
			if ( $this->is_advanced_cache_add_in() ) {
				Util_WpFile::delete_file( W3TC_ADDIN_FILE_ADVANCED_CACHE );
			}
		} catch ( Util_WpFile_FilesystemOperationException $ex ) {
			$exs->push( $ex );
		}
	}

	/**
	 * Checks if addins in wp-content is available and correct version.
	 *
	 * @param Util_Environment_Exceptions $exs Environment exceptions.
	 *
	 * @return void
	 */
	private function create_required_folders( $exs ) {
		// Folders that we create if not exists.
		$directories = array(
			W3TC_CACHE_DIR,
		);

		if ( ! ( defined( 'W3TC_CONFIG_DATABASE' ) && W3TC_CONFIG_DATABASE ) ) {
			$directories[] = W3TC_CONFIG_DIR;
		}

		foreach ( $directories as $directory ) {
			try {
				Util_WpFile::create_writeable_folder( $directory, WP_CONTENT_DIR );
			} catch ( Util_WpFile_FilesystemOperationException $ex ) {
				$exs->push( $ex );
			}
		}

		// folders that we delete if exists and not writeable.
		$directories = array(
			W3TC_CACHE_TMP_DIR,
			W3TC_CACHE_BLOGMAP_FILENAME,
			W3TC_CACHE_DIR . '/object',
			W3TC_CACHE_DIR . '/db',
		);

		foreach ( $directories as $directory ) {
			try {
				if ( file_exists( $directory ) && ! is_writeable( $directory ) ) {
					Util_WpFile::delete_folder( $directory );
				}
			} catch ( Util_WpFile_FilesystemRmdirException $ex ) {
				$exs->push( $ex );
			}
		}
	}

	/**
	 * Adds index files
	 *
	 * @return void
	 */
	private function add_index_to_folders() {
		$directories = array(
			W3TC_CACHE_DIR,
			W3TC_CONFIG_DIR,
		);

		$add_files = array();
		foreach ( $directories as $dir ) {
			if ( is_dir( $dir ) && ! file_exists( $dir . '/index.html' ) ) {
				@file_put_contents( $dir . '/index.html', '' );
			}
		}
	}

	/**
	 * Returns true if advanced-cache.php is installed
	 *
	 * @return boolean
	 */
	public function advanced_cache_installed() {
		return file_exists( W3TC_ADDIN_FILE_ADVANCED_CACHE );
	}

	/**
	 * Returns true if advanced-cache.php is old version.
	 *
	 * @return boolean
	 */
	public function advanced_cache_check_old_add_in() {
		$script_data = @file_get_contents( W3TC_ADDIN_FILE_ADVANCED_CACHE );
		return ( $script_data && strstr( $script_data, 'w3_instance' ) !== false );
	}

	/**
	 * Checks if advanced-cache.php exists
	 *
	 * @return boolean
	 */
	public function is_advanced_cache_add_in() {
		$script_data = @file_get_contents( W3TC_ADDIN_FILE_ADVANCED_CACHE );
		return ( $script_data && strstr( $script_data, 'PgCache_ContentGrabber' ) !== false );
	}

	/**
	 * Remove cron job for purge all caches.
	 *
	 * @since 2.8.0
	 *
	 * @return void
	 */
	private function unschedule_purge_wpcron() {
		if ( wp_next_scheduled( 'w3tc_purgeall_wpcron' ) ) {
			wp_clear_scheduled_hook( 'w3tc_purgeall_wpcron' );
		}
	}
}
