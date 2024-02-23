<?php
namespace W3TC;

/**
 * component of shared code used by minify
 */
class Minify_Core {
	/**
	 * Encode an array of files into a filename containing all files.
	 */
	static public function urls_for_minification_to_minify_filename( $files, $type ) {
		$files_string = json_encode( $files );

		$key = self::urls_for_minification_to_minify_key( $files_string,
			substr( md5( $files_string ), 0, 5 ) );
		if ( is_null( $key ) ) {
			// key collision (rare case), try full md5
			$key = self::urls_for_minification_to_minify_key( $files_string,
				md5( $files_string ) );
			if ( is_null( $key ) ) {
				// collision of full md5 - unprobable
				return null;
			}
		}

		$minify_filename = $key . '.' . $type;

		if ( has_filter( 'w3tc_minify_urls_for_minification_to_minify_filename' ) ) {
			$minify_filename = apply_filters(
				'w3tc_minify_urls_for_minification_to_minify_filename',
				$minify_filename,
				$files,
				$type
			);
			update_option( 'w3tc_minify_filter_' . hash( 'crc32b', $minify_filename ), $key, false );
		}

		return $minify_filename;
	}

	/**
	 * Tries key for minified array of files
	 */
	static private function urls_for_minification_to_minify_key( $files_string, $key ) {
		$v = get_option( 'w3tc_minify_' . $key );
		$minify_filenames = @json_decode( $v, true );
		if ( empty( $minify_filenames ) || !is_array( $minify_filenames ) ) {
			update_option( 'w3tc_minify_' . $key, $files_string, false );
			return $key;
		}

		if ( $v === $files_string ) {
			return $key;
		}

		return null;
	}

	/**
	 * Decode a minify auto filename into an array of files.
	 *
	 * @param unknown $compressed
	 * @param unknown $type
	 * @return array
	 */
	static public function minify_filename_to_urls_for_minification( $filename, $type ) {
		$hash = has_filter( 'w3tc_minify_urls_for_minification_to_minify_filename' ) ?
			get_option( 'w3tc_minify_filter_' . hash( 'crc32b', $filename . '.' . $type ) ) : $filename;
		$v    = get_option( 'w3tc_minify_' . $hash );

		$urls_unverified = @json_decode( $v, true );
		if ( !is_array( $urls_unverified ) ) {
			return array();
		}

		$urls = array();

		foreach ( $urls_unverified as $file ) {
			$verified = false;
			if ( Util_Environment::is_url( $file ) ) {
				$c = Dispatcher::config();
				$external = $c->get_array( 'minify.cache.files' );
				$external_regexp = $c->get_boolean( 'minify.cache.files_regexp' );

				foreach ( $external as $ext ) {
					if ( empty( $ext ) )
						continue;

					if ( !$external_regexp &&
						preg_match( '~^' . Util_Environment::get_url_regexp( $ext ) . '~', $file ) &&
						!$verified ) {
						$verified = true;
					}
					if ( $external_regexp &&
						preg_match( '~' . $ext . '~', $file ) && !$verified ) {
						$verified = true;
					}
				}
				if ( !$verified ) {
					Minify_Core::debug_error( sprintf( 'Remote file not in external files/libraries list: "%s"', $file ) );
				}
			} elseif (  /* no .. */  strpos( $file, '..' ) != false
				// no "//"
				|| strpos( $file, '//' ) !== false
				// no "\"
				|| ( strpos( $file, '\\' ) !== false && strtoupper( substr( PHP_OS, 0, 3 ) ) != 'WIN' )
				// no "./"
				|| preg_match( '/(?:^|[^\\.])\\.\\//', $file )
				/* no unwanted chars */ ||
				!preg_match( '/^[a-zA-Z0-9_.\\/-]|[\\\\]+$/', $file ) ) {
				$verified = false;
				Minify_Core::debug_error( sprintf( 'File path invalid: "%s"', $file ) );
			} else {
				$verified = true;
			}

			if ( $verified )
				$urls[] = $file;
		}

		return $urls;
	}



	static public function minified_url( $minify_filename ) {
		$path = Util_Environment::cache_blog_minify_dir();
		$filename = $path . '/' . $minify_filename;

		$c = Dispatcher::config();
		if ( Util_Rule::can_check_rules() && $c->get_boolean( 'minify.rewrite' ) ) {
			return Util_Environment::filename_to_url( $filename );
		}

		return home_url( '?w3tc_minify=' . $minify_filename );
	}



	/**
	 * Sends error response
	 *
	 * @param string  $error
	 * @param boolean $handle
	 * @param integer $status
	 * @return void
	 */
	static public function debug_error( $error ) {
		$c = Dispatcher::config();
		$debug = $c->get_boolean( 'minify.debug' );

		if ( $debug ) {
			Minify_Core::log( $error );
			echo "\r\n/* " . esc_html( $error ) . " */\r\n";
		}
	}



	/**
	 * Log
	 *
	 * @param string  $msg
	 * @return bool
	 */
	static public function log( $msg ) {
		$data = sprintf(
			"[%s] [%s] [%s] %s\n",
			date( 'r' ),
			isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
			! empty( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '-',
			$msg
		);

		$data = strtr( $data, '<>', '..' );

		$filename = Util_Debug::log_filename( 'minify' );
		return @file_put_contents( $filename, $data, FILE_APPEND );
	}



	public function get_usage_statistics_cache_config() {
		$c = Dispatcher::config();
		$engine = $c->get_string( 'minify.engine' );

		switch ( $engine ) {
		case 'memcached':
			$engineConfig = array(
				'servers' => $c->get_array( 'minify.memcached.servers' ),
				'persistent' => $c->get_boolean( 'minify.memcached.persistent' ),
				'aws_autodiscovery' =>
				$c->get_boolean( 'minify.memcached.aws_autodiscovery' ),
				'username' => $c->get_string( 'minify.memcached.username' ),
				'password' => $c->get_string( 'minify.memcached.password' )
			);
			break;

		case 'redis':
			$engineConfig = array(
				'servers' => $c->get_array( 'minify.redis.servers' ),
				'verify_tls_certificates' => $c->get_boolean( 'minify.redis.verify_tls_certificates' ),
				'persistent' => $c->get_boolean( 'minify.redis.persistent' ),
				'timeout' => $c->get_integer( 'minify.redis.timeout' ),
				'retry_interval' => $c->get_integer( 'minify.redis.retry_interval' ),
				'read_timeout' => $c->get_integer( 'minify.redis.read_timeout' ),
				'dbid' => $c->get_integer( 'minify.redis.dbid' ),
				'password' => $c->get_string( 'minify.redis.password' )
			);
			break;

		default:
			$engineConfig = array();
		}

		$engineConfig['engine'] = $engine;
		return $engineConfig;
	}
}
