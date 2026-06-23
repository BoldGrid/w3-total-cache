<?php
/**
 * File: Util_Rule.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Util_Rule
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class Util_Rule {
	/**
	 * Check if WP permalink directives exists
	 *
	 * @return boolean
	 */
	public static function is_permalink_rules() {
		if ( ( Util_Environment::is_apache() || Util_Environment::is_litespeed() ) && ! Util_Environment::is_wpmu() ) {
			$path      = self::get_pgcache_rules_core_path();
			$w3tc_data = @file_get_contents( $path );
			return $w3tc_data && strstr( $w3tc_data, W3TC_MARKER_BEGIN_WORDPRESS ) !== false;
		}

		return true;
	}

	/**
	 * Removes empty elements
	 *
	 * @param array $w3tc_a Input array.
	 *
	 * @return void
	 */
	public static function array_trim( &$w3tc_a ) {
		for ( $n = count( $w3tc_a ) - 1; $n >= 0; $n-- ) {
			if ( empty( $w3tc_a[ $n ] ) ) {
				array_splice( $w3tc_a, $n, 1 );
			}
		}
	}

	/**
	 * Strips characters that would terminate or escape an Apache /
	 * Nginx directive line when an admin-supplied config value is
	 * concatenated into `.htaccess` or `nginx.conf`.
	 *
	 * The W3TC `*_Environment.php` writers compose server-config blocks
	 * by string concatenation:
	 *
	 *     $rules .= '    Header set Content-Security-Policy "' . $csp . "\"\n";
	 *
	 * If `$csp` contains a literal newline, the generated block looks
	 * like
	 *
	 *     Header set Content-Security-Policy "default-src 'self';
	 *     SetHandler application/x-httpd-php"
	 *
	 * — the newline ends the `Header set` directive and the next line
	 * is parsed by Apache as a fresh directive whose body is supplied
	 * by the config value.
	 *
	 * The strip set:
	 *
	 *  - `\r\n`     — terminates the current directive in both Apache
	 *                 and Nginx.
	 *  - `\x00`     — NUL byte; truncates the rest of the string in
	 *                 some downstream consumers and confuses log
	 *                 readers.
	 *  - `<` `>`    — Apache uses `<Directive>...</Directive>` for
	 *                 sectional containers; an unexpected `<Files>` /
	 *                 `<Location>` block could change the file-handler
	 *                 for a glob supplied via the config value.
	 *  - `"`        — every emitter wraps the value in a double-quoted
	 *                 directive argument (Apache `Header set X "$v"`,
	 *                 Nginx `add_header X "$v";`). A literal `"` would
	 *                 close the quoted string and let subsequent bytes
	 *                 become a fresh directive token.
	 *
	 * Note: `;` is preserved deliberately. The only legitimate users
	 * of the helper are CSP / referrer-policy / HSTS-style values, all
	 * of which use `;` as an in-directive separator AND only land in
	 * quoted-string contexts (Apache `Header set`, Nginx `add_header`,
	 * or regex alternations like `RewriteCond ... !(<v>)`) where `;`
	 * carries no directive-terminating semantics.
	 *
	 * Returns `''` for non-strings so the helper is safe to call on
	 * an `isset()`-result value without an extra type check at the
	 * caller.
	 *
	 * @since 2.10.0
	 *
	 * @param mixed $w3tc_value Raw config value about to be written into a
	 *                     server-config file.
	 *
	 * @return string Sanitised value safe to embed inside a quoted
	 *                directive argument.
	 */
	public static function sanitize_directive_value( $w3tc_value ) {
		if ( ! \is_string( $w3tc_value ) ) {
			return '';
		}

		return \preg_replace( '/[\r\n\x00<>"]/', '', $w3tc_value );
	}

	/**
	 * Validates an admin-supplied custom server-rules file path
	 * (`config.path`).
	 *
	 * `config.path` is a free-text general-settings field ("Nginx server
	 * configuration file path") that lets operators point W3TC at the
	 * nginx / LiteSpeed config file its rule block is written into — which
	 * legitimately lives outside the document root (e.g.
	 * `/etc/nginx/conf.d/w3tc.conf`). Because the value is written to disk
	 * verbatim by the environment writers, an unconstrained value is an
	 * arbitrary-file-write primitive (drop a `.php` payload into the
	 * docroot, or an extensionless write to `/etc/cron.d`, `/etc/sudoers`,
	 * a php-fpm pool include, …).
	 *
	 * The rules file is, by definition, a server-config file, so we require
	 * a `.conf` extension and reject null bytes and `..` traversal. That
	 * preserves every legitimate use (the built-in defaults `nginx.conf` /
	 * `litespeed.conf` and any real `*.conf` include path) while blocking
	 * the documented write-to-RCE targets. Invalid values fall back to the
	 * safe in-site-root default.
	 *
	 * @since 2.10.0
	 *
	 * @param string $path Admin-configured path.
	 *
	 * @return bool True when the path is an acceptable rules-file target.
	 */
	private static function is_valid_custom_rules_path( $path ) {
		if ( ! is_string( $path ) || '' === $path || false !== strpos( $path, "\0" ) ) {
			return false;
		}

		$normalized = Util_Environment::normalize_path( $path );

		if ( preg_match( '~(?:^|/)\.\.(?:/|$)~', $normalized ) ) {
			return false;
		}

		return (bool) preg_match( '~\.conf$~i', $normalized );
	}

	/**
	 * Returns nginx rules path
	 *
	 * @return string
	 */
	public static function get_nginx_rules_path() {
		$w3tc_config = Dispatcher::config();

		$path = $w3tc_config->get_string( 'config.path' );

		if ( ! self::is_valid_custom_rules_path( $path ) ) {
			$path = Util_Environment::site_path() . 'nginx.conf';
		}

		return $path;
	}

	/**
	 * Returns litespeed rules path
	 *
	 * @return string
	 */
	public static function get_litespeed_rules_path() {
		$w3tc_config = Dispatcher::config();

		$path = $w3tc_config->get_string( 'config.path' );

		if ( ! self::is_valid_custom_rules_path( $path ) ) {
			$path = Util_Environment::site_path() . 'litespeed.conf';
		}

		return $path;
	}

	/**
	 * Returns path of apache's primary rules file
	 *
	 * @return string
	 */
	public static function get_apache_rules_path() {
		return Util_Environment::site_path() . '.htaccess';
	}

	/**
	 * Returns path of pagecache core rules file
	 *
	 * @return string
	 */
	public static function get_pgcache_rules_core_path() {
		switch ( true ) {
			case Util_Environment::is_apache():
			case Util_Environment::is_litespeed():
				return self::get_apache_rules_path();

			case Util_Environment::is_nginx():
				return self::get_nginx_rules_path();
		}

		return false;
	}

	/**
	 * Returns path of browsercache cache rules file
	 *
	 * @return string
	 */
	public static function get_browsercache_rules_cache_path() {
		if ( Util_Environment::is_litespeed() ) {
			return self::get_litespeed_rules_path();
		}

		return self::get_pgcache_rules_core_path();
	}

	/**
	 * Returns path of minify rules file
	 *
	 * @return string
	 */
	public static function get_minify_rules_core_path() {
		switch ( true ) {
			case Util_Environment::is_apache():
			case Util_Environment::is_litespeed():
				return W3TC_CACHE_MINIFY_DIR . DIRECTORY_SEPARATOR . '.htaccess';

			case Util_Environment::is_nginx():
				return self::get_nginx_rules_path();
		}

		return false;
	}

	/**
	 * Returns path of minify rules file
	 *
	 * @return string
	 */
	public static function get_minify_rules_cache_path() {
		switch ( true ) {
			case Util_Environment::is_apache():
			case Util_Environment::is_litespeed():
				return W3TC_CACHE_MINIFY_DIR . DIRECTORY_SEPARATOR . '.htaccess';

			case Util_Environment::is_nginx():
				return self::get_nginx_rules_path();
		}

		return false;
	}

	/**
	 * Returns path of CDN rules file
	 *
	 * @return string
	 */
	public static function get_cdn_rules_path() {
		switch ( true ) {
			case Util_Environment::is_apache():
			case Util_Environment::is_litespeed():
				return '.htaccess';

			case Util_Environment::is_nginx():
				return 'nginx.conf';
		}

		return false;
	}

	/**
	 * Get NewRelic rules core path
	 *
	 * @return string
	 */
	public static function get_new_relic_rules_core_path() {
		return self::get_pgcache_rules_core_path();
	}

	/**
	 * Returns true if we can modify rules
	 *
	 * @param string $path Path.
	 *
	 * @return boolean
	 */
	public static function can_modify_rules( $path ) {
		if ( Util_Environment::is_wpmu() ) {
			if ( Util_Environment::is_apache() || Util_Environment::is_litespeed() || Util_Environment::is_nginx() ) {
				switch ( $path ) {
					case self::get_pgcache_rules_cache_path():
					case self::get_minify_rules_core_path():
					case self::get_minify_rules_cache_path():
						return true;
				}
			}

			return false;
		}

		return true;
	}

	/**
	 * Trim rules
	 *
	 * @param string $rules Rules.
	 *
	 * @return string
	 */
	public static function trim_rules( $rules ) {
		$rules = trim( $rules );

		if ( '' !== $rules ) {
			$rules .= "\n";
		}

		return $rules;
	}

	/**
	 * Cleanup rewrite rules
	 *
	 * @param string $rules Rules.
	 *
	 * @return string
	 */
	public static function clean_rules( $rules ) {
		$rules = preg_replace( '~[\r\n]+~', "\n", $rules );
		$rules = preg_replace( '~^\s+~m', '', $rules );
		$rules = self::trim_rules( $rules );

		return $rules;
	}

	/**
	 * Erases text from start to end
	 *
	 * @param string $rules Rules.
	 * @param string $start Star.
	 * @param string $end   End.
	 *
	 * @return string
	 */
	public static function erase_rules( $rules, $start, $end ) {
		$w3tc_r = '~' . Util_Environment::preg_quote( $start ) . "\n.*?" . Util_Environment::preg_quote( $end ) . "\n*~s";

		$rules = preg_replace( $w3tc_r, '', $rules );
		$rules = self::trim_rules( $rules );

		return $rules;
	}

	/**
	 * Check if rules exist
	 *
	 * @param string $rules Rules.
	 * @param string $start Start.
	 * @param string $end   End.
	 *
	 * @return int
	 */
	public static function has_rules( $rules, $start, $end ) {
		return preg_match( '~' . Util_Environment::preg_quote( $start ) . "\n.*?" . Util_Environment::preg_quote( $end ) . "\n*~s", $rules );
	}

	/**
	 * Add rules
	 *
	 * @param Util_Environment_Exceptions $exs exceptions to fill on error.
	 * @param string                      $path        Filename of rules file to modify.
	 * @param string                      $rules       Rules to add.
	 * @param string                      $start       Start marker.
	 * @param string                      $end         End marker.
	 * @param array                       $order       Order where to place if some marker exists.
	 * @param boolean                     $remove_wpsc If WPSC rules should be removed to avoid inconsistent rules generation.
	 *
	 * @return void
	 */
	public static function add_rules( $exs, $path, $rules, $start, $end, $order, $remove_wpsc = false ) {
		if ( empty( $path ) ) {
			return;
		}

		$w3tc_data = @file_get_contents( $path );
		if ( empty( $w3tc_data ) ) {
			$w3tc_data = '';
		}

		$modified = false;
		if ( $remove_wpsc ) {
			if (
				self::has_rules(
					$w3tc_data,
					W3TC_MARKER_BEGIN_PGCACHE_WPSC,
					W3TC_MARKER_END_PGCACHE_WPSC
				)
			) {
				$w3tc_data = self::erase_rules(
					$w3tc_data,
					W3TC_MARKER_BEGIN_PGCACHE_WPSC,
					W3TC_MARKER_END_PGCACHE_WPSC
				);
				$modified  = true;
			}
		}

		if ( empty( $rules ) ) {
			// rules removal mode.
			$rules_present = ( strpos( $w3tc_data, $start ) !== false );
			if ( ! $modified && ! $rules_present ) {
				return;
			}
		} else {
			// rules creation mode.
			$rules_missing = ( strstr( self::clean_rules( $w3tc_data ), self::clean_rules( $rules ) ) === false );
			if ( ! $modified && ! $rules_missing ) {
				return;
			}
		}

		$replace_start = strpos( $w3tc_data, $start );
		$replace_end   = strpos( $w3tc_data, $end );

		if ( false !== $replace_start && false !== $replace_end && $replace_start < $replace_end ) {
			// old rules exists, replace mode.
			$replace_length = $replace_end - $replace_start + strlen( $end ) + 1;
		} else {
			$replace_start  = false;
			$replace_length = 0;

			$search = $order;

			foreach ( $search as $string => $length ) {
				$replace_start = strpos( $w3tc_data, $string );

				if ( false !== $replace_start ) {
					$replace_start += $length;
					break;
				}
			}
		}

		$w3tc_data_before = $w3tc_data;

		if ( false !== $replace_start ) {
			$w3tc_data = self::trim_rules( substr_replace( $w3tc_data, $rules, $replace_start, $replace_length ) );
		} else {
			$w3tc_data = self::trim_rules( rtrim( $w3tc_data ) . "\n" . $rules );
		}

		if ( ! $modified && ( $w3tc_data === $w3tc_data_before || self::clean_rules( $w3tc_data_before ) === self::clean_rules( $w3tc_data ) ) ) {
			return;
		}

		$nginx_rules_path = self::get_nginx_rules_path();
		if (
			! empty( $nginx_rules_path ) &&
			$path === $nginx_rules_path &&
			@file_exists( $path )
		) {
			$on_disk = @file_get_contents( $path );
			if ( false !== $on_disk && self::clean_rules( $on_disk ) === self::clean_rules( $w3tc_data ) ) {
				return;
			}
		}

		if ( strpos( $path, W3TC_CACHE_DIR ) === false || Util_Environment::is_nginx() ) {
			// writing to system rules file, may be potentially write-protected.
			try {
				Util_WpFile::write_to_file( $path, $w3tc_data );
			} catch ( Util_WpFile_FilesystemOperationException $ex ) {
				if ( false !== $replace_start ) {
					$w3tc_message = sprintf(
						// Translators: 1 path, 2 starting line, 3 ending line, 4 opening HTML strong tag, 5 closing HTML strong tag.
						__(
							'Edit file %4$s%1$s%5$s and replace all lines between and including %4$s%2$s%5$s and %4$s%3$s%5$s markers with:',
							'w3-total-cache'
						),
						$path,
						$start,
						$end,
						'<strong>',
						'</strong>'
					);
				} else {
					$w3tc_message = sprintf(
						// Translators: 1 path, 2 opening HTML strong tag, 3 closing HTML strong tag.
						__(
							'Edit file %2$s%1$s%3$s and add the following rules above the WordPress directives:',
							'w3-total-cache'
						),
						$path,
						'<strong>',
						'</strong>'
					);
				}

				$ex = new Util_WpFile_FilesystemModifyException(
					$ex->getMessage(),
					$ex->credentials_form(),
					$w3tc_message,
					$path,
					$rules
				);

				$exs->push( $ex );
				return;
			}
		} else {
			// writing to own rules file in cache folder.
			if ( ! @file_exists( dirname( $path ) ) ) {
				Util_File::mkdir_from( dirname( $path ), W3TC_CACHE_DIR );
			}

			if ( ! @file_put_contents( $path, $w3tc_data ) ) {
				try {
					Util_WpFile::delete_folder(
						dirname( $path ),
						'',
						isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : ''
					);
				} catch ( Util_WpFile_FilesystemOperationException $ex ) {
					$exs->push( $ex );
					return;
				}
			}

			$chmod = 0644;
			if ( defined( 'FS_CHMOD_FILE' ) ) {
				$chmod = FS_CHMOD_FILE;
			}
			@chmod( $path, $chmod );
		}
	}

	/**
	 * Fingerprint of the W3TC-managed nginx rules file on disk.
	 *
	 * @since 2.10.0
	 *
	 * @return string
	 */
	public static function nginx_rules_file_fingerprint() {
		$path = self::get_nginx_rules_path();

		if ( empty( $path ) || ! @file_exists( $path ) ) {
			return self::nginx_rules_fingerprint( '' );
		}

		$content = @file_get_contents( $path );
		if ( false === $content ) {
			$content = '';
		}

		return self::nginx_rules_fingerprint( $content );
	}

	/**
	 * Fingerprint of nginx rules content for dismiss / re-notify tracking.
	 *
	 * @since 2.10.0
	 *
	 * @param string $rules_content Raw rules file or block content.
	 *
	 * @return string
	 */
	public static function nginx_rules_fingerprint( $rules_content ) {
		return \md5( self::clean_rules( (string) $rules_content ) );
	}

	/**
	 * Update nginx-restart notice state once per environment-fix pass.
	 *
	 * Individual `add_rules()` calls can touch the same nginx.conf several
	 * times while handlers run; evaluating notice state after the full pass
	 * avoids clearing a dismiss when the net file content is unchanged.
	 *
	 * @since 2.10.0
	 *
	 * @param string $fingerprint_before Fingerprint captured before the fix pass.
	 *
	 * @return void
	 */
	public static function finalize_nginx_restart_notice_after_environment_fix( $fingerprint_before ) {
		if ( ! Util_Environment::is_nginx() ) {
			return;
		}

		$fingerprint_after = self::nginx_rules_file_fingerprint();
		$state             = Dispatcher::config_state_master();
		$dismiss_fp        = $state->get_string( 'common.nginx_rules_dismiss_fingerprint', '' );
		$hide              = $state->get_boolean( 'common.hide_note_nginx_restart_required' );

		if ( $fingerprint_before === $fingerprint_after ) {
			if (
				$hide ||
				( '' !== $dismiss_fp && $dismiss_fp === $fingerprint_after )
			) {
				$state->set( 'common.hide_note_nginx_restart_required', true );
				$state->save();
			}
			return;
		}

		$state->set( 'common.show_note.nginx_restart_required', true );

		if ( '' === $dismiss_fp || $dismiss_fp !== $fingerprint_after ) {
			$state->set( 'common.hide_note_nginx_restart_required', false );
		}

		$state->save();
	}

	/**
	 * Called when rules are modified, sets notification
	 *
	 * @deprecated 2.10.0 Notice state is finalized in {@see self::finalize_nginx_restart_notice_after_environment_fix()}.
	 *
	 * @since 2.10.0
	 *
	 * @param string|null $path         Rules file path that was written.
	 * @param string|null $new_content  Post-write rules file content.
	 *
	 * @return void
	 */
	public static function after_rules_modified( $path = null, $new_content = null ) {
	}

	/**
	 * Remove rules
	 *
	 * @param Util_Environment_Exceptions $exs exceptions to fill on error.
	 * @param string                      $path        Filename of rules file to modify.
	 * @param string                      $start       Start marker.
	 * @param string                      $end         End marker.
	 *
	 * @return void
	 */
	public static function remove_rules( $exs, $path, $start, $end ) {
		if ( ! file_exists( $path ) ) {
			return;
		}

		$w3tc_data = @file_get_contents( $path );
		if ( false === $w3tc_data ) {
			return;
		}

		if ( false === strstr( $w3tc_data, $start ) ) {
			return;
		}

		$w3tc_data = self::erase_rules( $w3tc_data, $start, $end );

		try {
			Util_WpFile::write_to_file( $path, $w3tc_data );
		} catch ( Util_WpFile_FilesystemOperationException $ex ) {
			$exs->push(
				new Util_WpFile_FilesystemModifyException(
					$ex->getMessage(),
					$ex->credentials_form(),
					sprintf(
						// Translators: 1 path, 2 starting line, 3 ending line, 4 opening HTML strong tag, 5 closing HTML strong tag.
						__(
							'Edit file %4$s%1$s%5$s and remove all lines between and including %4$s%2$s%5$s and %4$s%3$s%5$s markers.',
							'w3-total-cache'
						),
						$path,
						$start,
						$end,
						'<strong>',
						'</strong>'
					),
					$path
				)
			);
		}
	}

	/**
	 * Returns path of pgcache cache rules file
	 * Moved to separate file to not load rule.php for each disk enhanced request
	 *
	 * @return string
	 */
	public static function get_pgcache_rules_cache_path() {
		switch ( true ) {
			case Util_Environment::is_apache():
			case Util_Environment::is_litespeed():
				if ( Util_Environment::is_wpmu() ) {
					$w3tc_url   = get_home_url();
					$w3tc_match = null;
					if ( preg_match( '~http(s)?://(.+?)(/)?$~', $w3tc_url, $w3tc_match ) ) {
						$home_path = $w3tc_match[2];

						return W3TC_CACHE_PAGE_ENHANCED_DIR . DIRECTORY_SEPARATOR . $home_path . DIRECTORY_SEPARATOR . '.htaccess';
					}
				}

				return W3TC_CACHE_PAGE_ENHANCED_DIR . DIRECTORY_SEPARATOR . '.htaccess';

			case Util_Environment::is_nginx():
				return self::get_nginx_rules_path();
		}

		return false;
	}

	/**
	 * Returns true if we can check rules
	 *
	 * @return bool
	 */
	public static function can_check_rules() {
		return Util_Environment::is_apache() ||
			Util_Environment::is_litespeed() ||
			Util_Environment::is_nginx() ||
			Util_Environment::is_iis();
	}

	/**
	 * Support for GoDaddy servers configuration which uses.
	 * SUBDOMAIN_DOCUMENT_ROOT variable.
	 */
	public static function apache_docroot_variable() {
		$document_root           = isset( $_SERVER['DOCUMENT_ROOT'] ) ? esc_url_raw( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) : '';
		$subdomain_document_root = isset( $_SERVER['SUBDOMAIN_DOCUMENT_ROOT'] ) ? esc_url_raw( wp_unslash( $_SERVER['SUBDOMAIN_DOCUMENT_ROOT'] ) ) : '';
		$php_document_root       = isset( $_SERVER['PHP_DOCUMENT_ROOT'] ) ? esc_url_raw( wp_unslash( $_SERVER['PHP_DOCUMENT_ROOT'] ) ) : '';
		if ( ! empty( $subdomain_document_root ) && $subdomain_document_root !== $document_root ) {
			return '%{ENV:SUBDOMAIN_DOCUMENT_ROOT}';
		} elseif ( ! empty( $php_document_root ) && $php_document_root !== $document_root ) {
			return '%{ENV:PHP_DOCUMENT_ROOT}';
		} else {
			return '%{DOCUMENT_ROOT}';
		}
	}



	/**
	 * Takes an array of extensions single per row and/or extensions delimited by |
	 *
	 * @param unknown $extensions Extensions.
	 * @param unknown $w3tc_ext        Extension.
	 *
	 * @return array
	 */
	public static function remove_extension_from_list( $extensions, $w3tc_ext ) {
		$size = count( $extensions );
		for ( $w3tc_i = 0; $w3tc_i < $size; $w3tc_i++ ) {
			if ( $extensions[ $w3tc_i ] === $w3tc_ext ) {
				unset( $extensions[ $w3tc_i ] );
				return $extensions;
			} elseif ( false !== strpos( $extensions[ $w3tc_i ], $w3tc_ext ) && false !== strpos( $extensions[ $w3tc_i ], '|' ) ) {
				$exts     = explode( '|', $extensions[ $w3tc_i ] );
				$w3tc_key = array_search( $w3tc_ext, $exts, true );
				unset( $exts[ $w3tc_key ] );
				$extensions[ $w3tc_i ] = implode( '|', $exts );
				return $extensions;
			}
		}
		return $extensions;
	}
}
