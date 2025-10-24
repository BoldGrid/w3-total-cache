<?php
/**
 * File: Util_Debug.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Util_Debug
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class Util_Debug {
	/**
	 * Returns current microtime
	 *
	 * @return float
	 */
	public static function microtime() {
		list ( $usec, $sec ) = explode( ' ', microtime() );

		return (float) $usec + (float) $sec;
	}

	/**
	 * Return full path to log file for module
	 * Path used in priority
	 * 1) W3TC_DEBUG_DIR
	 * 2) WP_DEBUG_LOG
	 * 3) W3TC_CACHE_DIR
	 *
	 * @param unknown $module  Module.
	 * @param null    $blog_id Blog ID.
	 *
	 * @return string
	 */
	public static function log_filename( $module, $blog_id = null ) {
		if ( is_null( $blog_id ) ) {
			$blog_id = Util_Environment::blog_id();
		}

		$postfix = sprintf( '%06d', $blog_id );

		if ( defined( 'W3TC_BLOG_LEVELS' ) ) {
			for ( $n = 0; $n < W3TC_BLOG_LEVELS; $n++ ) {
				$postfix = substr( $postfix, strlen( $postfix ) - 1 - $n, 1 ) . '/' . $postfix;
			}
		}

		$from_dir = W3TC_CACHE_DIR;
		if ( defined( 'W3TC_DEBUG_DIR' ) && W3TC_DEBUG_DIR ) {
			$dir_path = W3TC_DEBUG_DIR;
			if ( ! is_dir( W3TC_DEBUG_DIR ) ) {
				$from_dir = dirname( W3TC_DEBUG_DIR );
			}
		} else {
			$dir_path = Util_Environment::cache_dir( 'log' );
		}

		/**
		 * Prefix the postfix (log subdirectory).
		 *
		 * Uses a definition/contant that should exist in "wp-config.php".
		 *
		 * @link https://api.wordpress.org/secret-key/1.1/salt/
		 */
		$salt    = defined( 'NONCE_SALT' ) ? NONCE_SALT : '';
		$postfix = hash( 'crc32b', W3TC_DIR . $salt ) . '-' . $postfix;

		$filename = $dir_path . '/' . $postfix . '/' . $module . '.log';
		if ( ! is_dir( dirname( $filename ) ) ) {
			Util_File::mkdir_from_safe( dirname( $filename ), $from_dir );
		}

		// Ensure .htaccess exists in $dir_path.
		Util_File::check_htaccess( $dir_path );

		return $filename;
	}

	/**
	 * Log
	 *
	 * @param unknown $module  Module.
	 * @param string  $message Message.
	 *
	 * @return string
	 */
	public static function log( $module, $message ) {
		$message  = strtr( $message, '<>', '..' );
		$filename = self::log_filename( $module );

		return @file_put_contents( $filename, '[' . gmdate( 'r' ) . '] ' . $message . "\n", FILE_APPEND ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
	}

	/**
	 * Log cache purge event
	 *
	 * @param unknown $module           Module.
	 * @param string  $message          Message.
	 * @param array   $parameters       Parameters.
	 * @param array   $explicit_postfix Explicit postfix.
	 *
	 * @return bool
	 */
	public static function log_purge( $module, $message, $parameters = null, $explicit_postfix = null ) {
		$backtrace       = debug_backtrace( 0 ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		$backtrace_count = count( $backtrace );
		$backtrace_lines = array();
		$pos             = 0;

		for ( $n = 2; $n < $backtrace_count; $n++ ) {
			if ( ! self::log_purge_should_print_item( $backtrace, $n ) ) {
				continue;
			}

			$i        = $backtrace[ $n ];
			$filename = isset( $i['file'] ) ? $i['file'] : '';
			$filename = str_replace( ABSPATH, '', $filename );

			$line = isset( $i['line'] ) ? $i['line'] : '';

			$method            = ( ! empty( $i['class'] ) ? $i['class'] . '--' : '' ) . $i['function'];
			$args              = ' ' . self::encode_params( $i['args'] );
			$backtrace_lines[] = "\t#" . ( $pos ) . ' ' . $filename . '(' . $line . '): ' . $method . $args;
			++$pos;
		}

		$message = $message;
		if ( ! is_null( $parameters ) ) {
			$message .= self::encode_params( $parameters );
		}

		$user     = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
		$username = ( empty( $user ) ? 'anonymous' : $user->user_login );
		$message .= "\n\tusername:$username";

		if ( is_array( $explicit_postfix ) ) {
			$message .= "\n\t" . implode( "\n\t", $explicit_postfix );
		}

		$message .= "\n" . implode( "\n", $backtrace_lines );

		return self::log( $module . '-purge', $message );
	}

	/**
	 * Log purge should print item
	 *
	 * @param array  $backtrace Backtrace.
	 * @param string $n         Key.
	 *
	 * @return bool
	 */
	private static function log_purge_should_print_item( $backtrace, $n ) {
		if ( ! empty( $backtrace[ $n ]['class'] ) && 'W3TC\\CacheFlush_Locally' === $backtrace[ $n ]['class'] ) {
			return false;
		}

		if ( ! empty( $backtrace[ $n ]['class'] ) && 'WP_Hook' === $backtrace[ $n ]['class'] && ! empty( $backtrace[ $n + 1 ]['function'] ) ) {
			$f = $backtrace[ $n + 1 ]['function'];
			if ( 'do_action' === $f || 'apply_filters' === $f ) {
				return false;
			}

			return self::log_purge_should_print_item( $backtrace, $n + 1 );
		}

		return true;
	}

	/**
	 * Encode parameters
	 *
	 * @param array $args Arguments.
	 *
	 * @return string
	 */
	private static function encode_params( $args ) {
		$args_strings = array();
		if ( ! is_array( $args ) ) {
			$s = (string) $args;

			if ( strlen( $s ) > 100 ) {
				$s = substr( $s, 0, 98 ) . '..';
			}

			$args_strings[] = $s;
		} else {
			foreach ( $args as $arg ) {
				$s = wp_json_encode( $arg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				if ( strlen( $s ) > 100 ) {
					$s = substr( $s, 0, 98 ) . '..';
				}

				$args_strings[] = $s;
			}
		}

		return '(' . implode( ', ', $args_strings ) . ')';
	}

	/**
	 * Clean debug output with label headers.
	 *
	 * @param string $label Label.
	 * @param array  $data  Data.
	 *
	 * phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
	 * phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_print_r
	 */
	public static function debug( $label, $data ) {
		error_log(
			"\n\n" . '===============Debug ' . $label . ' Start===============' . "\n" .
			'Microtime: ' . microtime( true ) . "\n" .
			'Content  : ' . print_r( $data, true ) . "\n" .
			'===============Debug ' . $label . ' End===============' . "\n"
		);
	}

	/**
	 * Redacts the value of the _wpnonce parameter in a log line.
	 *
	 * @param  string $log_line The log line containing the nonce parameter.
	 * @return string The log line with the nonce value redacted.
	 */
	public static function redact_wpnonce( string $log_line ): string {
		// Regular expression to match the nonce parameter and its value.
		$pattern = '/(nonce=)[^&\]]+/';

		// Replace the value of nonce with "REDACTED".
		$redacted_log_line = preg_replace( $pattern, '$1REDACTED', $log_line );

		return $redacted_log_line;
	}
}
