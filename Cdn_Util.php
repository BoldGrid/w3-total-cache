<?php
/**
 * File: Cdn_Util.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdn_Util
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */
class Cdn_Util {
	/**
	 * Check whether $w3tc_engine is correct CDN engine
	 *
	 * @param string $w3tc_engine CDN engine.
	 *
	 * @return bool
	 */
	public static function is_engine( $w3tc_engine ) {
		return in_array(
			$w3tc_engine,
			array(
				'azure',
				'azuremi',
				'cf',
				'cf2',
				'ftp',
				'google_drive',
				'mirror',
				'rscf',
				'rackspace_cdn',
				's3',
				's3_compatible',
			),
			true
		);
	}

	/**
	 * Returns true if CDN engine is mirror.
	 *
	 * @param string $w3tc_engine CDN engine.
	 * @static
	 *
	 * @return bool
	 */
	public static function is_engine_mirror( $w3tc_engine ) {
		return in_array(
			$w3tc_engine,
			array(
				'mirror',
				'cf2',
				'rackspace_cdn',
				'bunnycdn',
			),
			true
		);
	}

	/**
	 * Returns true if CDN engine is mirror.
	 *
	 * @param string $w3tc_engine CDN engine.
	 *
	 * @return bool
	 */
	public static function is_engine_push( $w3tc_engine ) {
		return ! self::is_engine_mirror( $w3tc_engine );
	}

	/**
	 * Returns true if CDN has purge all support.
	 *
	 * @param unknown $w3tc_engine CDN engine.
	 *
	 * @return bool
	 */
	public static function can_purge_all( $w3tc_engine ) {
		return in_array(
			$w3tc_engine,
			array(
				'bunnycdn',
				'cf2',
			),
			true
		);
	}

	/**
	 * Returns true if CDN engine is supporting purge.
	 *
	 * @param string $w3tc_engine CDN engine.
	 *
	 * @return bool
	 */
	public static function can_purge( $w3tc_engine ) {
		return in_array(
			$w3tc_engine,
			array(
				'azure',
				'azuremi',
				'cf',
				'cf2',
				'ftp',
				'rscf',
				's3',
				's3_compatible',
			),
			true
		);
	}

	/**
	 * Returns true if CDN supports realtime purge. That is purging on post changes, comments etc.
	 *
	 * @param unknown $w3tc_engine CDN engine.
	 *
	 * @return bool
	 */
	public static function supports_realtime_purge( $w3tc_engine ) {
		return ! in_array( $w3tc_engine, array( 'cf2' ), true );
	}

	/**
	 * Search files.
	 *
	 * @param string  $search_dir Search path.
	 * @param string  $base_dir Base path.
	 * @param string  $mask Mask value.
	 * @param boolean $recursive Recursive flag.
	 *
	 * @return array
	 */
	public static function search_files( $search_dir, $base_dir, $mask = '*.*', $recursive = true ) {
		static $stack = array();

		$files  = array();
		$ignore = array(
			'.svn',
			'.git',
			'.DS_Store',
			'CVS',
			'Thumbs.db',
			'desktop.ini',
		);

		$dir = @opendir( $search_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( $dir ) {
			$w3tc_entry = @readdir( $dir ); // Initialize before the loop.
			while ( false !== $w3tc_entry ) {
				if ( '.' !== $w3tc_entry && '..' !== $w3tc_entry && ! in_array( $w3tc_entry, $ignore, true ) ) {
					$path = $search_dir . '/' . $w3tc_entry;

					if ( @is_dir( $path ) && $recursive ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
						array_push( $stack, $w3tc_entry );
						$files = array_merge(
							$files,
							self::search_files(
								$path,
								$base_dir,
								$mask,
								$recursive
							)
						);
						array_pop( $stack );
					} else {
						$regexp = '~^(' . self::get_regexp_by_mask( $mask ) . ')$~i';

						if ( preg_match( $regexp, $w3tc_entry ) ) {
							$tmp     = '' !== $base_dir ? $base_dir . '/' : '';
							$w3tc_p  = implode( '/', $stack );
							$tmp    .= '' !== $w3tc_p ? $w3tc_p . '/' : '';
							$files[] = $tmp . $w3tc_entry;
						}
					}
				}

				$w3tc_entry = @readdir( $dir ); // Re-assign for the next iteration.
			}

			@closedir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		return $files;
	}

	/**
	 * Returns regexp by mask.
	 *
	 * @param string $mask Mask pattern.
	 *
	 * @return string
	 */
	public static function get_regexp_by_mask( $mask ) {
		$mask = trim( $mask );
		$mask = Util_Environment::preg_quote( $mask );

		$mask = str_replace(
			array(
				'\*',
				'\?',
				';',
			),
			array(
				'@ASTERISK@',
				'@QUESTION@',
				'|',
			),
			$mask
		);

		$regexp = str_replace(
			array(
				'@ASTERISK@',
				'@QUESTION@',
			),
			array(
				'[^\\?\\*:\\|\'"<>]*',
				'[^\\?\\*:\\|\'"<>]',
			),
			$mask
		);

		return $regexp;
	}

	/**
	 * Replaces folder placeholders.
	 *
	 * @param string $w3tc_file Replacement value.
	 *
	 * @return string
	 */
	public static function replace_folder_placeholders( $w3tc_file ) {
		static $content_dir, $plugin_dir, $upload_dir;
		if ( empty( $content_dir ) ) {
			$content_dir = str_replace( Util_Environment::document_root(), '', WP_CONTENT_DIR );
			$content_dir = substr( $content_dir, strlen( Util_Environment::site_url_uri() ) );
			$content_dir = trim( $content_dir, '/' );
			if ( defined( 'WP_PLUGIN_DIR' ) ) {
				$plugin_dir = str_replace( Util_Environment::document_root(), '', WP_PLUGIN_DIR );
				$plugin_dir = trim( $plugin_dir, '/' );
			} else {
				$plugin_dir = str_replace( Util_Environment::document_root(), '', WP_CONTENT_DIR . '/plugins' );
				$plugin_dir = trim( $plugin_dir, '/' );
			}
			$upload_dir = Util_Environment::wp_upload_dir();
			$upload_dir = str_replace( Util_Environment::document_root(), '', $upload_dir['basedir'] );
			$upload_dir = trim( $upload_dir, '/' );
		}
		$w3tc_file = str_replace( '{wp_content_dir}', $content_dir, $w3tc_file );
		$w3tc_file = str_replace( '{plugins_dir}', $plugin_dir, $w3tc_file );
		$w3tc_file = str_replace( '{uploads_dir}', $upload_dir, $w3tc_file );

		return $w3tc_file;
	}

	/**
	 * Replaces folder placeholders URI.
	 *
	 * @param string $w3tc_file Replacement value.
	 *
	 * @return string
	 */
	public static function replace_folder_placeholders_to_uri( $w3tc_file ) {
		static $content_uri, $plugins_uri, $uploads_uri;
		if ( empty( $content_uri ) ) {
			$content_uri = Util_Environment::url_to_uri( content_url() );
			$plugins_uri = Util_Environment::url_to_uri( plugins_url() );

			$upload_dir = Util_Environment::wp_upload_dir();
			if ( isset( $upload_dir['baseurl'] ) ) {
				$uploads_uri = Util_Environment::url_to_uri( $upload_dir['baseurl'] );
			} else {
				$uploads_uri = '';
			}
		}
		$w3tc_file = str_replace( '{wp_content_dir}', $content_uri, $w3tc_file );
		$w3tc_file = str_replace( '{plugins_dir}', $plugins_uri, $w3tc_file );
		$w3tc_file = str_replace( '{uploads_dir}', $uploads_uri, $w3tc_file );

		return $w3tc_file;
	}

	/**
	 * Get the override default value for cdn.flush_manually to prevent excessive invalidation charges for S3 CF and CF2.
	 *
	 * @since 2.2.6
	 *
	 * @param string $w3tc_cdn_engine CDN engine value.
	 *
	 * @return boolean default value override;
	 */
	public static function get_flush_manually_default_override( $w3tc_cdn_engine = null ) {
		$override_targets = array( 's3', 'cf', 'cf2' );
		return in_array( $w3tc_cdn_engine, $override_targets, true );
	}

	/**
	 * Extensions Media Library import may pull from third-party URLs.
	 *
	 * Fail closed: executable, HTML, SVG, and Flash types are never
	 * imported even if the operator mask lists them.
	 *
	 * @since 2.10.6
	 *
	 * @return string[]
	 */
	public static function import_allowed_extensions() {
		return array(
			'jpg',
			'jpeg',
			'gif',
			'png',
			'webp',
			'avif',
			'bmp',
			'ico',
			'css',
			'js',
			'mp3',
			'wma',
			'ogg',
			'wav',
			'mp4',
			'webm',
			'woff',
			'woff2',
			'ttf',
			'eot',
			'otf',
		);
	}

	/**
	 * Extensions that must never appear in an imported filename.
	 *
	 * @since 2.10.6
	 *
	 * @return string[]
	 */
	public static function import_forbidden_extensions() {
		return array(
			'php',
			'phtml',
			'pht',
			'php3',
			'php4',
			'php5',
			'php7',
			'php8',
			'phar',
			'cgi',
			'pl',
			'py',
			'exe',
			'sh',
			'html',
			'htm',
			'shtml',
			'svg',
			'swf',
			'htaccess',
		);
	}

	/**
	 * Allowed extensions implied by an operator import mask.
	 *
	 * Empty / false / `*` / `*.*` mean the built-in allowlist, not
	 * "every file". Tokens outside the allowlist are dropped so a
	 * browser-altered mask cannot broaden the set.
	 *
	 * @since 2.10.6
	 *
	 * @param string|bool $mask Operator mask (`*.jpg;*.png`).
	 *
	 * @return string[]
	 */
	public static function import_extensions_from_mask( $mask ) {
		$allowlist = self::import_allowed_extensions();

		if ( ! is_string( $mask ) ) {
			return $allowlist;
		}

		$mask = trim( $mask );
		if ( '' === $mask ) {
			return $allowlist;
		}

		$parts = preg_split( '/[;,\s]+/', strtolower( $mask ) );
		if ( ! is_array( $parts ) ) {
			return array();
		}

		$exts = array();
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( '' === $part ) {
				continue;
			}

			if ( '*' === $part || '*.*' === $part ) {
				return $allowlist;
			}

			$part = ltrim( $part, '*.' );
			$part = trim( $part, '.' );
			if ( '' === $part || ! preg_match( '/^[a-z0-9]{1,8}$/', $part ) ) {
				continue;
			}

			$exts[] = $part;
		}

		$exts = array_values( array_intersect( array_unique( $exts ), $allowlist ) );

		return $exts;
	}

	/**
	 * Path portion of a URL or file path used for import matching.
	 *
	 * Query strings and fragments are stripped.
	 *
	 * @since 2.10.6
	 *
	 * @param string $url URL or path.
	 *
	 * @return string
	 */
	public static function import_url_path( $url ) {
		$path = (string) $url;
		$hash = strpos( $path, '#' );
		if ( false !== $hash ) {
			$path = substr( $path, 0, $hash );
		}

		$query = strpos( $path, '?' );
		if ( false !== $query ) {
			$path = substr( $path, 0, $query );
		}

		if ( \function_exists( 'wp_parse_url' ) && Util_Environment::is_url( $path ) ) {
			$parsed = \wp_parse_url( $path, PHP_URL_PATH );
			if ( is_string( $parsed ) && '' !== $parsed ) {
				$path = $parsed;
			}
		}

		return $path;
	}

	/**
	 * Last path extension of a URL or file path, lowercased.
	 *
	 * Query strings are stripped. Missing extensions return empty.
	 *
	 * @since 2.10.6
	 *
	 * @param string $url URL or path.
	 *
	 * @return string
	 */
	public static function import_url_extension( $url ) {
		return strtolower( (string) pathinfo( self::import_url_path( $url ), PATHINFO_EXTENSION ) );
	}

	/**
	 * Whether a URL may be imported into the Media Library.
	 *
	 * Last extension must be in the mask∩allowlist. Any forbidden
	 * compound extension (`file.php.jpg`) is rejected.
	 *
	 * @since 2.10.6
	 *
	 * @param string      $url  Source URL or path.
	 * @param string|bool $mask Operator mask.
	 *
	 * @return bool
	 */
	public static function url_matches_import_policy( $url, $mask ) {
		if ( self::import_url_has_forbidden_fragment( $url ) ) {
			return false;
		}

		$basename = strtolower( (string) pathinfo( self::import_url_path( $url ), PATHINFO_BASENAME ) );

		$labels = explode( '.', $basename );
		if ( count( $labels ) > 2 ) {
			$forbidden = self::import_forbidden_extensions();
			array_pop( $labels );
			foreach ( $labels as $label ) {
				if ( in_array( $label, $forbidden, true ) ) {
					return false;
				}
			}
		}

		$ext = self::import_url_extension( $url );
		if ( '' === $ext ) {
			return false;
		}

		if ( in_array( $ext, self::import_forbidden_extensions(), true ) ) {
			return false;
		}

		return in_array( $ext, self::import_extensions_from_mask( $mask ), true );
	}

	/**
	 * Whether a URL fragment tries to smuggle a forbidden extension.
	 *
	 * `import_url_path()` strips `#…` for matching and destination
	 * names. Fragments like `#.php` or `#shell.php` must still fail
	 * closed so policy and `pathinfo( $src )` cannot disagree.
	 *
	 * @since 2.10.6
	 *
	 * @param string $url URL or path.
	 *
	 * @return bool
	 */
	public static function import_url_has_forbidden_fragment( $url ) {
		$hash = strpos( (string) $url, '#' );
		if ( false === $hash ) {
			return false;
		}

		$fragment = substr( (string) $url, $hash + 1 );
		$query    = strpos( $fragment, '?' );
		if ( false !== $query ) {
			$fragment = substr( $fragment, 0, $query );
		}

		$fragment = strtolower( rawurldecode( $fragment ) );
		if ( '' === $fragment ) {
			return false;
		}

		$forbidden = self::import_forbidden_extensions();
		$basename  = (string) pathinfo( $fragment, PATHINFO_BASENAME );
		$ext       = (string) pathinfo( $basename, PATHINFO_EXTENSION );

		if ( '' === $ext && preg_match( '/^[a-z0-9]{1,8}$/', $basename ) ) {
			$ext = $basename;
		}

		if ( '' !== $ext && in_array( $ext, $forbidden, true ) ) {
			return true;
		}

		foreach ( explode( '.', $basename ) as $label ) {
			if ( in_array( $label, $forbidden, true ) ) {
				return true;
			}
		}

		return false;
	}
}
