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
	 * Fire the W3TC audit-log action.
	 *
	 * Provides the canonical entry point for all security-relevant
	 * events in W3TC — failed nonce checks, capability denials,
	 * config writes, extension toggles, support-handler invocations,
	 * Cloudflare API requests, and exceptions caught at the admin
	 * AJAX boundary.
	 *
	 * Two responsibilities live here:
	 *
	 *  1. Sanitize `$context` so any string value that may carry
	 *     user-supplied content (request URI, exception message,
	 *     payload fragment) is run through {@see self::redact()}
	 *     before any subscriber sees it. This keeps `_wpnonce`,
	 *     passwords, API keys, and wp-config secrets out of the
	 *     audit stream regardless of who subscribes.
	 *
	 *  2. Fire the `w3tc_audit_log` action with the redacted
	 *     context. Subscribers (a SIEM bridge plugin, a WordPress
	 *     activity-log plugin) decide what to do with the event;
	 *     W3TC itself does not persist it.
	 *
	 * Hook signature:
	 *
	 *     do_action( 'w3tc_audit_log', string $event, array $context )
	 *
	 *  - `$event` is a stable identifier
	 *    (e.g. `cap_denied`, `cloudflare_api_failed`,
	 *    `config_imported`).
	 *  - `$context` is an associative array. `user_id` and `ip` are
	 *    populated automatically if the caller doesn't include them.
	 *
	 * Designed to be cheap and safe to call from any handler path.
	 *
	 * @since X.X.X
	 *
	 * @param string $event   Event identifier (snake_case).
	 * @param array  $context Optional event context. String values
	 *                        are redacted before dispatch.
	 *
	 * @return void
	 */
	public static function audit_log( $event, array $context = array() ) {
		if ( ! is_string( $event ) || '' === $event ) {
			return;
		}

		if ( ! \array_key_exists( 'user_id', $context ) && \function_exists( 'get_current_user_id' ) ) {
			$context['user_id'] = \get_current_user_id();
		}

		if ( ! \array_key_exists( 'ip', $context ) && ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$context['ip'] = \sanitize_text_field( \wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		foreach ( $context as $k => $v ) {
			if ( \is_string( $v ) ) {
				$context[ $k ] = self::redact( $v );
			}
		}

		if ( \function_exists( 'do_action' ) ) {
			\do_action( 'w3tc_audit_log', $event, $context );
		}
	}

	/**
	 * Log a single line to the per-module debug log.
	 *
	 * rt9-51: callers historically passed user-controlled content
	 * (request URIs, exception strings, HTTP bodies) verbatim. The
	 * old implementation stripped `<` and `>` only — which is not
	 * sufficient to prevent log forging. A newline or carriage
	 * return in `$message` would let an attacker close the current
	 * entry and inject a fabricated one (different timestamp,
	 * different "user"), poisoning incident response.
	 *
	 * Sanitization rules:
	 *  * Replace CR / LF with a space so a single entry stays a
	 *    single physical line.
	 *  * Replace TAB with a space so column-aligned readers don't
	 *    get tricked.
	 *  * Drop NUL bytes (some terminal pagers truncate on NUL).
	 *  * Keep the existing `<>` → `.` swap so logs are HTML-safe if
	 *    rendered in a browser-based viewer.
	 *
	 * @since X.X.X
	 *
	 * @param unknown $module  Module.
	 * @param string  $message Message.
	 *
	 * @return int|false       Bytes written, or false on failure.
	 */
	public static function log( $module, $message ) {
		$message = (string) $message;
		$message = strtr(
			$message,
			array(
				'<'  => '.',
				'>'  => '.',
				"\r" => ' ',
				"\n" => ' ',
				"\t" => ' ',
				"\0" => '',
			)
		);

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
	 * Redacts the value of every `_wpnonce` / bare `nonce` parameter
	 * in a log line.
	 *
	 * The old pattern `/(nonce=)[^&\]]+/` excluded only `&` and `]`,
	 * so values ran past whitespace, tabs, and newlines into the
	 * adjacent log context. Stop at the first whitespace character
	 * as well.
	 *
	 * @since X.X.X
	 *
	 * @param  string $log_line The log line containing the nonce parameter.
	 * @return string The log line with every nonce value redacted to `REDACTED`.
	 */
	public static function redact_wpnonce( string $log_line ): string {
		return (string) preg_replace( '/(nonce=)[^&\s]*/i', '$1REDACTED', $log_line );
	}

	/**
	 * General-purpose log-content redactor.
	 *
	 * Applied to any value that may carry user-supplied or
	 * secret-bearing data on its way into a log file or an outbound
	 * support payload. Covers the patterns seen across the W3TC
	 * codebase:
	 *
	 *  * `_wpnonce=` / `nonce=` URL parameter values.
	 *  * `password=`, `passwd=`, `pass=`, `secret=`, `token=`,
	 *    `apikey=` / `api_key=`, `key=`, and `Authorization:
	 *    Bearer …` HTTP header content.
	 *  * `define( 'KEY', 'value' )` blocks for the documented
	 *    wp-config.php secret-bearing constants (DB_PASSWORD plus
	 *    the eight auth keys / salts).
	 *
	 * Designed to be idempotent — calling it twice yields the same
	 * output as calling it once.
	 *
	 * @since X.X.X
	 *
	 * @param mixed $blob Anything stringifiable. Non-string input
	 *                    returns an empty string rather than throwing.
	 *
	 * @return string Redacted text.
	 */
	public static function redact( $blob ) {
		if ( ! \is_string( $blob ) ) {
			if ( \is_scalar( $blob ) ) {
				$blob = (string) $blob;
			} else {
				return '';
			}
		}

		// wp-config-style secret defines.
		$blob = (string) \preg_replace(
			"/define\\(\\s*(['\"])(DB_PASSWORD|AUTH_KEY|SECURE_AUTH_KEY|LOGGED_IN_KEY|NONCE_KEY|AUTH_SALT|SECURE_AUTH_SALT|LOGGED_IN_SALT|NONCE_SALT)(\\1)\\s*,\\s*(['\"])([^'\"]*)(\\4)\\s*\\)\\s*;/i",
			"define( '\$2', 'REDACTED' );",
			$blob
		);

		// HTTP Authorization header (Bearer / Basic) — header may be
		// embedded in an exception/dump.
		$blob = (string) \preg_replace(
			'/(Authorization:\s*(?:Bearer|Basic)\s+)[^\s\r\n,;]+/i',
			'$1REDACTED',
			$blob
		);

		// Common secret-named query / form parameter values.
		$blob = (string) \preg_replace(
			'/((?:password|passwd|pass|secret|token|api[_-]?key|key)=)[^&\s]*/i',
			'$1REDACTED',
			$blob
		);

		// Nonces (idempotent with redact_wpnonce, applied last so
		// any nonce embedded inside one of the earlier-redacted
		// patterns still picks up the standard token form).
		return self::redact_wpnonce( $blob );
	}
}
