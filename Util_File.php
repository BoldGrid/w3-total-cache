<?php
/**
 * File: Util_File.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Util_File
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class Util_File {
	/**
	 * Recursive creates directory
	 *
	 * @param string  $path      Path.
	 * @param integer $mask      Mask.
	 * @param string  $curr_path Current Path.
	 *
	 * @return boolean
	 */
	public static function mkdir( $path, $mask = 0777, $curr_path = '' ) {
		$path = Util_Environment::realpath( $path );
		$path = trim( $path, '/' );
		$dirs = explode( '/', $path );

		foreach ( $dirs as $dir ) {
			if ( '' === $dir ) {
				return false;
			}

			$curr_path .= ( '' === $curr_path ? '' : '/' ) . $dir;

			if ( ! @file_exists( $curr_path ) ) {
				if ( ! @mkdir( $curr_path, $mask ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Recursive creates directory from some directory
	 * Does not try to create directory before from
	 *
	 * @param string  $path      Path.
	 * @param string  $from_path From path.
	 * @param integer $mask      Mask.
	 *
	 * @return boolean
	 */
	public static function mkdir_from( $path, $from_path = '', $mask = 0777 ) {
		$path = Util_Environment::realpath( $path );

		$from_path = Util_Environment::realpath( $from_path );
		if ( substr( $path, 0, strlen( $from_path ) ) !== $from_path ) {
			return false;
		}

		$path = substr( $path, strlen( $from_path ) );

		$path = trim( $path, '/' );
		$dirs = explode( '/', $path );

		$curr_path = $from_path;

		foreach ( $dirs as $dir ) {
			if ( '' === $dir ) {
				return false;
			}

			$curr_path .= ( '' === $curr_path ? '' : '/' ) . $dir;

			if ( ! @file_exists( $curr_path ) ) {
				if ( ! @mkdir( $curr_path, $mask, true ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Recursive creates directory from some directory
	 * Safely for web-accessible folders
	 * (no .htaccess folders which cause 403 error later)
	 * Does not try to create directory before from
	 *
	 * @param string  $path      Path.
	 * @param string  $from_path From path.
	 * @param integer $mask      Mask.
	 *
	 * @return boolean
	 */
	public static function mkdir_from_safe( $path, $from_path = '', $mask = 0777 ) {
		$path = Util_Environment::realpath( $path );

		$from_path = Util_Environment::realpath( $from_path );
		if ( substr( $path, 0, strlen( $from_path ) ) !== $from_path ) {
			return false;
		}

		$path = substr( $path, strlen( $from_path ) );

		$path = trim( $path, '/' );
		$dirs = explode( '/', $path );

		$curr_path          = realpath( $from_path );   // use canonicalization.
		$curr_path_previous = $curr_path;

		foreach ( $dirs as $dir ) {
			if ( '' === $dir ) {
				return false;
			}

			if ( '.' === substr( $dir, 0, 1 ) ) { // (no .htaccess folders).
				return false;
			}

			$curr_path .= ( '' === $curr_path ? '' : '/' ) . $dir;

			if ( ! @file_exists( $curr_path ) ) {
				if ( ! @mkdir( $curr_path, $mask, true ) ) {
					return false;
				}

				$curr_path = realpath( $curr_path );
				// make sure we grow from previous step and dont jump elsewhere.
				if ( strlen( $curr_path ) <= 0 ||
						substr( $curr_path, 0, strlen( $curr_path_previous ) ) !== $curr_path_previous ) {
					return false;
				}

				$curr_path_previous = $curr_path;
			}
		}

		return true;
	}

	/**
	 * Recursive remove dir
	 *
	 * @param string $path    Path.
	 * @param array  $exclude Exclude.
	 * @param bool   $remove  Remove.
	 *
	 * @return void
	 */
	public static function rmdir( $path, $exclude = array(), $remove = true ) {
		if ( ! \is_string( $path ) || '' === $path ) {
			return;
		}

		// Normalize first to avoid duplicate separators and malformed input.
		$path = Util_Environment::realpath( $path );

		if ( '' === $path ) {
			return;
		}

		// Refresh cached stats before checking the filesystem.
		\clearstatcache();

		if ( ! @\is_dir( $path ) ) {
			return;
		}

		$dir = @\opendir( $path );

		if ( $dir ) {
			$w3tc_entry = @\readdir( $dir );
			while ( false !== $w3tc_entry ) {
				if ( '.' === $w3tc_entry || '..' === $w3tc_entry ) {
					$w3tc_entry = @\readdir( $dir );
					continue;
				}

				foreach ( $exclude as $mask ) {
					if ( \fnmatch( $mask, \basename( $w3tc_entry ) ) ) {
						$w3tc_entry = @\readdir( $dir );
						continue 2;
					}
				}

				$full_path = $path . DIRECTORY_SEPARATOR . $w3tc_entry;

				if ( @\is_dir( $full_path ) ) {
					self::rmdir( $full_path, $exclude );
				} else {
					@\unlink( $full_path );
				}

				$w3tc_entry = @\readdir( $dir );
			}

			@\closedir( $dir );

			if ( $remove ) {
				@\rmdir( $path );
			}
		}
	}

	/**
	 * Recursive empty dir
	 *
	 * @param string $path    Path.
	 * @param array  $exclude Exclude.
	 *
	 * @return void
	 */
	public static function emptydir( $path, $exclude = array() ) {
		self::rmdir( $path, $exclude, false );
	}

	/**
	 * Check if file is write-able
	 *
	 * @param string $w3tc_file File.
	 *
	 * @return boolean
	 */
	public static function is_writable( $w3tc_file ) {
		$exists = file_exists( $w3tc_file );

		$fp = @fopen( $w3tc_file, 'a' );

		if ( $fp ) {
			fclose( $fp );

			if ( ! $exists ) {
				@unlink( $w3tc_file );
			}

			return true;
		}

		return false;
	}

	/**
	 * Cehck if dir is write-able
	 *
	 * @param string $dir Directory.
	 *
	 * @return boolean
	 */
	public static function is_writable_dir( $dir ) {
		$w3tc_file = $dir . '/' . uniqid( mt_rand() ) . '.tmp';

		return self::is_writable( $w3tc_file );
	}

	/**
	 * Returns dirname of path
	 *
	 * @param string $path Path.
	 *
	 * @return string
	 */
	public static function dirname( $path ) {
		$dirname = dirname( $path );

		if ( '.' === $dirname || '/' === $dirname || '\\' === $dirname ) {
			$dirname = '';
		}

		return $dirname;
	}

	/**
	 * Make path relative
	 *
	 * @param string $filename File name.
	 * @param string $base_dir Base directory.
	 *
	 * @return string
	 */
	public static function make_relative_path( $filename, $base_dir ) {
		$filename = Util_Environment::realpath( $filename );
		$base_dir = Util_Environment::realpath( $base_dir );

		$filename_parts = explode( '/', trim( $filename, '/' ) );
		$base_dir_parts = explode( '/', trim( $base_dir, '/' ) );

		// count number of equal path parts.
		for ( $equal_number = 0;;$equal_number++ ) {
			if ( $equal_number >= count( $filename_parts ) || $equal_number >= count( $base_dir_parts ) ) {
				break;
			}

			if ( $filename_parts[ $equal_number ] !== $base_dir_parts[ $equal_number ] ) {
				break;
			}
		}

		$relative_dir  = str_repeat( '../', count( $base_dir_parts ) - $equal_number );
		$relative_dir .= implode( '/', array_slice( $filename_parts, $equal_number ) );

		return $relative_dir;
	}

	/**
	 * Returns open basedirs
	 *
	 * @return array
	 */
	public static function get_open_basedirs() {
		$open_basedir_ini = ini_get( 'open_basedir' );
		$open_basedirs    = ( W3TC_WIN ? preg_split( '~[;,]~', $open_basedir_ini ) : explode( ':', $open_basedir_ini ) );
		$w3tc_result      = array();

		foreach ( $open_basedirs as $w3tc_open_basedir ) {
			$w3tc_open_basedir = trim( $w3tc_open_basedir );
			if ( ! empty( $w3tc_open_basedir ) && '' !== $w3tc_open_basedir ) {
				$w3tc_result[] = Util_Environment::realpath( $w3tc_open_basedir );
			}
		}

		return $w3tc_result;
	}

	/**
	 * Checks if path is restricted by open_basedir
	 *
	 * @param string $path Path.
	 *
	 * @return boolean
	 */
	public static function check_open_basedir( $path ) {
		$path          = Util_Environment::realpath( $path );
		$open_basedirs = self::get_open_basedirs();

		if ( ! count( $open_basedirs ) ) {
			return true;
		}

		foreach ( $open_basedirs as $w3tc_open_basedir ) {
			if ( strstr( $path, $w3tc_open_basedir ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the octal file permission number of a file or directory.
	 *
	 * @param string $w3tc_file File path.
	 *
	 * @return int
	 */
	public static function get_file_permissions( $w3tc_file ) {
		if ( function_exists( 'fileperms' ) && $fileperms = @fileperms( $w3tc_file ) ) { // phpcs:ignore
			$fileperms = 0777 & $fileperms;
		} else {
			clearstatcache();
			$stat = @stat( $w3tc_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			if ( $stat ) {
				$fileperms = 0777 & $stat['mode'];
			} else {
				$fileperms = 0;
			}
		}
		return intval( decoct( $fileperms ) );
	}

	/**
	 * Get file owner
	 *
	 * @param string $w3tc_file File path.
	 *
	 * @return string
	 */
	public static function get_file_owner( $w3tc_file = '' ) {
		$fileowner = 'unknown';
		$filegroup = 'unknown';

		if ( $w3tc_file ) {
			if ( function_exists( 'fileowner' ) && function_exists( 'fileowner' ) ) {
				$fileowner = @fileowner( $w3tc_file );
				$filegroup = @filegroup( $w3tc_file );
				if ( function_exists( 'posix_getpwuid' ) && function_exists( 'posix_getgrgid' ) ) {
					$fileowner = @posix_getpwuid( $fileowner );
					$fileowner = $fileowner['name'];
					$filegroup = @posix_getgrgid( $filegroup );
					$filegroup = $filegroup['name'];
				}
			}
		} elseif ( function_exists( 'getmyuid' ) && function_exists( 'getmygid' ) ) {
			$fileowner = @getmyuid();
			$filegroup = @getmygid();
			if ( function_exists( 'posix_getpwuid' ) && function_exists( 'posix_getgrgid' ) ) {
				$fileowner = @posix_getpwuid( $fileowner );
				$fileowner = $fileowner['name'];
				$filegroup = @posix_getgrgid( $filegroup );
				$filegroup = $filegroup['name'];
			}
		}

		return $fileowner . ':' . $filegroup;
	}

	/**
	 * Creates W3TC_CACHE_TMP_DIR dir if required
	 *
	 * @throws \Exception Exception.
	 *
	 * @return string
	 */
	public static function create_tmp_dir() {
		if ( ! is_dir( W3TC_CACHE_TMP_DIR ) || ! is_writable( W3TC_CACHE_TMP_DIR ) ) {
			self::mkdir_from( W3TC_CACHE_TMP_DIR, W3TC_CACHE_DIR );

			if ( ! is_dir( W3TC_CACHE_TMP_DIR ) || ! is_writable( W3TC_CACHE_TMP_DIR ) ) {
				$e           = error_get_last();
				$description = ( isset( $e['message'] ) ? $e['message'] : '' );

				throw new \Exception(
					\wp_kses_post(
						sprintf(
							// Translators: 1 Cache TMP dir path surround by HTML strong tag, 2 Description.
							\__( 'Can\'t create folder %1$s: %2$s', 'w3-total-cache' ),
							'<strong>' . W3TC_CACHE_TMP_DIR . '</strong>',
							$description
						)
					)
				);
			}
		}

		return W3TC_CACHE_TMP_DIR;
	}

	/**
	 * Atomically writes file inside W3TC_CACHE_DIR dir
	 *
	 * @param unknown $filename Filename.
	 * @param unknown $content  Content.
	 *
	 * @throws \Exception Exception.
	 *
	 * @return void
	 */
	public static function file_put_contents_atomic( $filename, $content ) {
		self::create_tmp_dir();
		$temp = tempnam( W3TC_CACHE_TMP_DIR, 'temp' );

		try {
			$f = @fopen( $temp, 'wb' );
			if ( ! $f ) {
				if ( file_exists( $temp ) ) {
					@unlink( $temp );
				}

				throw new \Exception( 'Can\'t write to temporary file <strong>' . $temp . '</strong>' );
			}

			fwrite( $f, $content );
			fclose( $f );

			if ( ! @rename( $temp, $filename ) ) {
				@unlink( $filename );
				if ( ! @rename( $temp, $filename ) ) {
					self::mkdir_from( dirname( $filename ), W3TC_CACHE_DIR );

					if ( ! @rename( $temp, $filename ) ) {
						throw new \Exception( 'Can\'t write to file <strong>' . $filename . '</strong>' );
					}
				}
			}

			$chmod = 0644;
			if ( defined( 'FS_CHMOD_FILE' ) ) {
				$chmod = FS_CHMOD_FILE;
			}

			@chmod( $filename, $chmod );
		} catch ( \Exception $ex ) {
			if ( file_exists( $temp ) ) {
				@unlink( $temp );
			}

			throw $ex;
		}
	}


	/**
	 * Takes a W3TC settings array and formats it to a PHP String
	 *
	 * @param unknown $w3tc_data Data.
	 *
	 * @return string
	 */
	public static function format_data_as_settings_file( $w3tc_data ) {
		$w3tc_config = "<?php\r\n\r\nreturn array(\r\n";
		foreach ( $w3tc_data as $w3tc_key => $w3tc_value ) {
			$w3tc_config .= self::format_array_entry_as_settings_file_entry( 1, $w3tc_key, $w3tc_value );
		}

		$w3tc_config .= ');';

		return $w3tc_config;
	}

	/**
	 * Writes array item to file
	 *
	 * @param int    $tabs  Tabs.
	 * @param string $w3tc_key   Key.
	 * @param mixed  $w3tc_value Value.
	 *
	 * @return string
	 */
	public static function format_array_entry_as_settings_file_entry( $tabs, $w3tc_key, $w3tc_value ) {
		$w3tc_item = str_repeat( "\t", $tabs );

		if ( is_numeric( $w3tc_key ) && (string) (int) $w3tc_key === (string) $w3tc_key ) {
			$w3tc_item .= sprintf( '%d => ', $w3tc_key );
		} else {
			$w3tc_item .= sprintf( "'%s' => ", addcslashes( $w3tc_key, "'\\" ) );
		}

		switch ( gettype( $w3tc_value ) ) {
			case 'object':
			case 'array':
				$w3tc_item .= "array(\r\n";
				foreach ( (array) $w3tc_value as $k => $v ) {
					$w3tc_item .= self::format_array_entry_as_settings_file_entry( $tabs + 1, $k, $v );
				}

				$w3tc_item .= sprintf( "%s),\r\n", str_repeat( "\t", $tabs ) );

				return $w3tc_item;

			case 'integer':
				$w3tc_data = (string) $w3tc_value;
				break;

			case 'double':
				$w3tc_data = (string) $w3tc_value;
				break;

			case 'boolean':
				$w3tc_data = ( $w3tc_value ? 'true' : 'false' );
				break;

			case 'NULL':
				$w3tc_data = 'null';
				break;

			default:
			case 'string':
				$w3tc_data = "'" . addcslashes( $w3tc_value, "'\\" ) . "'";
				break;
		}

		$w3tc_item .= $w3tc_data . ",\r\n";

		return $w3tc_item;
	}

	/**
	 * Ensure that ".htaccess" exists in the specified directory.
	 *
	 * If the WP_Filesystem is "direct", then create the file (with mode 0644) if needed.
	 *
	 * @since 2.8.2
	 *
	 * @param  string $dir Directory.
	 * @return bool
	 */
	public static function check_htaccess( string $dir ): bool {
		$filepath = $dir . DIRECTORY_SEPARATOR . '.htaccess';

		if ( ! @file_exists( $filepath ) ) {
			$chmod = 0644;

			if ( defined( 'FS_CHMOD_FILE' ) ) {
				$chmod = FS_CHMOD_FILE;
			}

			$contents = "<IfModule mod_authz_core.c>\n    # Apache 2.4\n    Require all denied\n</IfModule>\n\n<IfModule !mod_authz_core.c>\n    # Apache 2.2\n    Deny from all\n</IfModule>\n";
			return @file_put_contents( $filepath, $contents ) && chmod( $filepath, $chmod );
		}

		return false;
	}
}
