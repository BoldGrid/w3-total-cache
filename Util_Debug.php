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
	 * @param unknown $w3tc_module  Module.
	 * @param null    $blog_id Blog ID.
	 *
	 * @return string
	 */
	public static function log_filename( $w3tc_module, $blog_id = null ) {
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

		$filename = $dir_path . '/' . $postfix . '/' . $w3tc_module . '.log';
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
	 *     request body fragment) is run through {@see self::redact()}
	 *     before any subscriber sees it. Nested arrays are walked
	 *     (depth-capped) so secret-keyed scalars and string blobs
	 *     in child values are filtered too. This keeps `_wpnonce`,
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
	 *    Nested string values and secret-keyed scalars are redacted.
	 *
	 * Designed to be cheap and safe to call from any handler path.
	 *
	 * @since 2.10.0
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

		if ( ! \array_key_exists( 'ip', $context ) ) {
			$ip = Util_Environment::get_client_ip();
			if ( '' !== $ip ) {
				$context['ip'] = $ip;
			}
		}

		$context = self::redact_nested( $context );

		if ( \function_exists( 'do_action' ) ) {
			\do_action( 'w3tc_audit_log', $event, $context );
		}
	}

	/**
	 * Log a single line to the per-module debug log.
	 *
	 * Callers historically passed user-controlled content (request
	 * URIs, exception strings, HTTP bodies) verbatim. The
	 * old implementation stripped `<` and `>` only — not enough to
	 * keep a single entry on a single physical line. A newline or
	 * carriage return in `$w3tc_message` would close the current entry
	 * and let the remainder of the value appear as a fabricated
	 * additional line (different timestamp, different "user"),
	 * which would confuse incident response.
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
	 * @since 2.10.0
	 *
	 * @param unknown $w3tc_module  Module.
	 * @param string  $w3tc_message Message.
	 *
	 * @return int|false       Bytes written, or false on failure.
	 */
	public static function log( $w3tc_module, $w3tc_message ) {
		// Redact while structure (JSON, print_r, newlines) is intact,
		// then flatten control characters for a single physical line.
		$w3tc_message = self::sanitize_log_message( self::redact( (string) $w3tc_message ) );

		$filename = self::log_filename( $w3tc_module );

		return @file_put_contents( $filename, '[' . gmdate( 'r' ) . '] ' . $w3tc_message . "\n", FILE_APPEND ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
	}

	/**
	 * Sanitize a log-line message for safe append to a debug log file.
	 *
	 * Exposed as a separate static helper so the unit test suite can
	 * exercise the exact production sanitiser without writing to disk
	 * — calling `Util_Debug::log()` directly inside a test would
	 * require figuring out the per-module log file path and asserting
	 * against its trailing line. Tests now invoke this helper
	 * directly so a regression that re-introduces CR/LF/TAB/NUL into
	 * the sanitiser is caught by the existing assertions.
	 *
	 * The strip set:
	 *  - `<` / `>`  → `.`  so logs are HTML-safe when rendered in a
	 *                       browser-based viewer (some operator dashboards
	 *                       display debug-log lines verbatim).
	 *  - `\r` / `\n` → space  so a single submission stays a single
	 *                       physical line (closes log forging — a
	 *                       newline in a user-supplied value would
	 *                       otherwise close the current entry and let
	 *                       the remainder appear as a fabricated
	 *                       additional line).
	 *  - `\t`       → space  so column-aligned readers can't be tricked.
	 *  - `\0`       → ''     since some terminal pagers truncate on NUL.
	 *
	 * @since 2.10.0
	 *
	 * @param mixed $w3tc_message Anything stringifiable.
	 *
	 * @return string Sanitised single-line message.
	 */
	public static function sanitize_log_message( $w3tc_message ) {
		return strtr(
			(string) $w3tc_message,
			array(
				'<'  => '.',
				'>'  => '.',
				"\r" => ' ',
				"\n" => ' ',
				"\t" => ' ',
				"\0" => '',
			)
		);
	}

	/**
	 * Log cache purge event
	 *
	 * @param unknown $w3tc_module           Module.
	 * @param string  $w3tc_message          Message.
	 * @param array   $parameters       Parameters.
	 * @param array   $explicit_postfix Explicit postfix.
	 *
	 * @return bool
	 */
	public static function log_purge( $w3tc_module, $w3tc_message, $parameters = null, $explicit_postfix = null ) {
		$backtrace       = debug_backtrace( 0 ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		$backtrace_count = count( $backtrace );
		$backtrace_lines = array();
		$pos             = 0;

		for ( $n = 2; $n < $backtrace_count; $n++ ) {
			if ( ! self::log_purge_should_print_item( $backtrace, $n ) ) {
				continue;
			}

			$w3tc_i   = $backtrace[ $n ];
			$filename = isset( $w3tc_i['file'] ) ? $w3tc_i['file'] : '';
			$filename = str_replace( ABSPATH, '', $filename );

			$w3tc_line = isset( $w3tc_i['line'] ) ? $w3tc_i['line'] : '';

			$method            = ( ! empty( $w3tc_i['class'] ) ? $w3tc_i['class'] . '--' : '' ) . $w3tc_i['function'];
			$args              = ' ' . self::encode_params( $w3tc_i['args'], 'dbcache' === $w3tc_module );
			$backtrace_lines[] = "\t#" . ( $pos ) . ' ' . $filename . '(' . $w3tc_line . '): ' . $method . $args;
			++$pos;
		}

		$w3tc_message = $w3tc_message;
		if ( ! is_null( $parameters ) ) {
			if ( 'dbcache' === $w3tc_module ) {
				if ( \is_array( $parameters ) ) {
					$parameters = self::scrub_dbcache_flush_extras_for_log( $parameters );
				} elseif ( \is_string( $parameters ) ) {
					$parameters = self::scrub_sql_literals( $parameters );
				}
			}

			$w3tc_message .= self::encode_params( $parameters );
		}

		$user          = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
		$username      = ( empty( $user ) ? 'anonymous' : $user->user_login );
		$w3tc_message .= "\n\tusername:$username";

		/**
		 * RT9-56: Enrich the purge audit-log line with the source IP,
		 * user-agent, request method+URI, the user's primary role, and
		 * a per-request correlation ID so coordinated flush attacks or
		 * unauthorised purges can be traced to attacker tooling.
		 *
		 * Every field is one-line-flattened via {@see self::log_purge_field_clean()}
		 * — bytes that would break the single-record format (newlines,
		 * tabs, `<` / `>` for markup-like content) are scrubbed, and
		 * the UA / URI are length-capped so an attacker-controlled
		 * 8KB User-Agent string cannot blow up the log file.
		 *
		 * `purge_role` is the user's *first* role only — that's enough
		 * for forensic triage ("subscriber initiated a flush, escalate")
		 * without leaking the full role array. Anonymous purges record
		 * the literal `anonymous`.
		 *
		 * `purge_id` is a 16-hex correlation ID, derived from
		 * `wp_generate_password()` with `false` for the special-char
		 * argument so the value is alphanum-only and safe to grep for.
		 * When `wp_generate_password()` isn't available (early purge
		 * before WordPress fully loads) we fall back to `uniqid()`,
		 * also alphanum.
		 */
		$ip      = Util_Environment::get_client_ip();
		$ua      = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Debug log context only.
		$uri     = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Debug log context only.
		$method  = isset( $_SERVER['REQUEST_METHOD'] ) ? (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Debug log context only.
		$role    = ( ! empty( $user ) && ! empty( $user->roles ) && \is_array( $user->roles ) )
			? (string) $user->roles[0]
			: 'anonymous';
		$corr_id = '';
		if ( \function_exists( 'wp_generate_password' ) ) {
			$corr_id = wp_generate_password( 16, false, false );
		} else {
			$corr_id = \uniqid( '', true );
			$corr_id = \preg_replace( '/[^A-Za-z0-9]/', '', $corr_id );
		}

		$w3tc_message .= "\n\tip:" . self::log_purge_field_clean( $ip, 45 );
		$w3tc_message .= "\n\tua:" . self::log_purge_field_clean( $ua, 256 );
		$w3tc_message .= "\n\tmethod:" . self::log_purge_field_clean( $method, 16 );
		$w3tc_message .= "\n\turi:" . self::log_purge_field_clean( $uri, 256 );
		$w3tc_message .= "\n\trole:" . self::log_purge_field_clean( $role, 32 );
		$w3tc_message .= "\n\tpurge_id:" . self::log_purge_field_clean( $corr_id, 32 );

		if ( is_array( $explicit_postfix ) ) {
			$w3tc_message .= "\n\t" . implode( "\n\t", $explicit_postfix );
		}

		$w3tc_message .= "\n" . implode( "\n", $backtrace_lines );

		return self::log( $w3tc_module . '-purge', $w3tc_message );
	}

	/**
	 * Single-line-flatten + length-cap a value before it lands in the
	 * purge audit log. Strips bytes that would break the per-record
	 * format (CR / LF / tab / null) and replaces markup-significant
	 * `<` / `>` with `.` so an attacker-controlled User-Agent or URI
	 * containing `<script>` doesn't render if a log viewer happens to
	 * be HTML-aware. Then trims to `$max` bytes — 256 is plenty for
	 * the worst legitimate UA / URI and prevents an 8KB User-Agent
	 * from ballooning every purge entry.
	 *
	 * @since 2.10.0
	 *
	 * @param string $w3tc_value Field value (already wp_unslash'd by caller).
	 * @param int    $max   Maximum length in bytes after cleaning.
	 *
	 * @return string
	 */
	private static function log_purge_field_clean( $w3tc_value, $max ) {
		if ( ! \is_string( $w3tc_value ) || '' === $w3tc_value ) {
			return '';
		}
		$w3tc_value = self::redact( $w3tc_value );
		$w3tc_value = \strtr(
			$w3tc_value,
			array(
				"\r" => ' ',
				"\n" => ' ',
				"\t" => ' ',
				"\0" => '',
				'<'  => '.',
				'>'  => '.',
			)
		);
		if ( \strlen( $w3tc_value ) > $max ) {
			$w3tc_value = \substr( $w3tc_value, 0, $max );
		}
		return $w3tc_value;
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
	 * @param array $args      Arguments.
	 * @param bool  $scrub_sql Optional. Replace every quoted and numeric value in the
	 *                         JSON-encoded arguments, so a string argument becomes `?`
	 *                         whether or not it is SQL. Default false.
	 *
	 * @return string
	 */
	private static function encode_params( $args, $scrub_sql = false ) {
		$args_strings = array();
		if ( ! is_array( $args ) ) {
			$s = (string) $args;
			if ( $scrub_sql ) {
				$s = self::scrub_sql_literals( \is_string( $args ) ? (string) wp_json_encode( $args, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : $s );
			}

			if ( strlen( $s ) > 100 ) {
				$s = substr( $s, 0, 98 ) . '..';
			}

			$args_strings[] = $s;
		} else {
			foreach ( $args as $arg ) {
				if ( $scrub_sql ) {
					$s = self::scrub_sql_literals( (string) wp_json_encode( $arg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
				} else {
					$s = wp_json_encode( $arg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				}
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
	 * @param string $w3tc_label Label.
	 * @param array  $w3tc_data  Data.
	 *
	 * phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
	 * phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_print_r
	 */
	public static function debug( $w3tc_label, $w3tc_data ) {
		$content = self::redact( \print_r( $w3tc_data, true ) );

		error_log(
			"\n\n" . '===============Debug ' . $w3tc_label . ' Start===============' . "\n" .
			'Microtime: ' . microtime( true ) . "\n" .
			'Content  : ' . $content . "\n" .
			'===============Debug ' . $w3tc_label . ' End===============' . "\n"
		);
	}

	/**
	 * Redacts the value of every `_wpnonce` / bare `nonce` parameter
	 * in a log line.
	 *
	 * @since 2.10.0
	 *
	 * @param  string $log_line The log line containing the nonce parameter.
	 * @return string The log line with every nonce value redacted to `REDACTED`.
	 */
	public static function redact_wpnonce( string $log_line ): string {
		return (string) preg_replace( '/(nonce=)[^&\s\]]*/i', '$1REDACTED', $log_line );
	}

	/**
	 * General-purpose log-content redactor.
	 *
	 * Filters secret-bearing values in the formats diagnostic output
	 * actually uses (query strings, JSON, PHP print_r / array dumps,
	 * HTTP auth headers, connection URIs, WordPress auth cookies,
	 * wp-config define() blocks) while leaving neighboring structure
	 * intact. All matching quantifiers are bounded.
	 *
	 * @since 2.10.0
	 *
	 * @param mixed $blob Anything stringifiable.
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

		$secret = self::redact_secret_name_pattern();
		// Dotted `.key` (cdn.s3.key) is secret; bare JSON `"key"` is not.
		$key    = '(?:(?:[A-Za-z0-9][A-Za-z0-9_.-]{0,80}[._-])?' . $secret . '|(?:[A-Za-z0-9][A-Za-z0-9_.-]{0,80}\.)key)';
		$define = '(?:(?:DB_(?:PASSWORD|USER|HOST|NAME)|AUTH_KEY|SECURE_AUTH_KEY|LOGGED_IN_KEY|NONCE_KEY|AUTH_SALT|SECURE_AUTH_SALT|LOGGED_IN_SALT|NONCE_SALT|' . $key . ')(?:[_-][A-Za-z0-9]+)*)';

		$blob = (string) \preg_replace(
			"/define\\s*\\(\\s*(['\"])(" . $define . ")(\\1)\\s*,\\s*(['\"])((?:\\\\.|(?!\\4).)*)(\\4)\\s*\\)\\s*;/i",
			"define( '\$2', 'REDACTED' );",
			$blob
		);

		$blob = (string) \preg_replace(
			'/(Authorization:\s*(?:Bearer|Basic|Token|Digest)\s+)[^\s\r\n,;]+/i',
			'$1REDACTED',
			$blob
		);

		$blob = (string) \preg_replace(
			'/((?:AccessKey|(?:X-)?Api-?Key|(?:X-)?Auth-Token|(?:X-)?Access-Token)\s*:\s*)[^\s\r\n,;]+/i',
			'$1REDACTED',
			$blob
		);

		$blob = (string) \preg_replace(
			'/((?:wordpress_logged_in|wordpress_sec)_[A-Za-z0-9]*=)[^;\s]*/i',
			'$1REDACTED',
			$blob
		);

		// wordpress_{COOKIEHASH}= — not wordpress_test_cookie.
		$blob = (string) \preg_replace(
			'/(wordpress_[a-f0-9]{32}=)[^;\s]*/i',
			'$1REDACTED',
			$blob
		);

		$blob = (string) \preg_replace(
			'/((?:https?|ftps?|redis|rediss|mysql|mongodb|amqp|smtps?):\/\/[^:\/\s?#]{0,128}:)[^@\/\s]{1,256}@/i',
			'$1REDACTED@',
			$blob
		);

		$blob = (string) \preg_replace(
			'/(^|[?&;\s])((?:' . $key . '|key)\s*=\s*)[^&\s]*/i',
			'$1$2REDACTED',
			$blob
		);

		$blob = (string) \preg_replace(
			'/(^|[?&;\s]|%3[Ff]|%26)((?:' . $key . '|key)%3[Dd])[^&%\s]{0,2048}/i',
			'$1$2REDACTED',
			$blob
		);

		$blob = (string) \preg_replace(
			'/(["\'])(' . $key . ')\1(\s*:\s*)(["\'])(?:\\\\.|(?!\4)[^\\\\])*\4/i',
			'$1$2$1$3$4REDACTED$4',
			$blob
		);

		$blob = (string) \preg_replace(
			'/(["\'])(' . $key . ')\1(\s*:\s*)(["\'])[^"\'\r\n]*/i',
			'$1$2$1$3$4REDACTED',
			$blob
		);

		$blob = (string) \preg_replace(
			'/(%22(?:' . $key . ')%22%3[Aa]%22)((?:[^%]|%(?!22))*)(%22)/i',
			'$1REDACTED$3',
			$blob
		);

		$blob = (string) \preg_replace(
			'/(%22(?:' . $key . ')%22%3[Aa]%22)(?:[^%]|%(?!22))*/i',
			'$1REDACTED',
			$blob
		);

		// print_r: after a secret `[key] =>`, consume until the next
		// `[key] =>` at the same or shallower indent (or EOF). Nested
		// Array/Object blocks and multiline PEM bodies go with the key.
		$blob = (string) \preg_replace(
			'/((?:^|\n)([ \t]{0,32})\[(' . $key . ')\]\s*=>\s*)(?:[^\n]|\n(?!\2\[[^\]]{1,120}\]\s*=>)(?!(?!\2)[ \t]{0,32}\[[^\]]{1,120}\]\s*=>))*/i',
			'$1REDACTED',
			$blob
		);

		$blob = (string) \preg_replace(
			'/(["\'])(' . $key . ')\1(\s*=>\s*)(["\'])(?:\\\\.|(?!\4)[^\\\\])*\4/i',
			'$1$2$1$3$4REDACTED$4',
			$blob
		);

		// var_export: nested `array (` / next-line values only — not
		// same-line quoted scalars (those keep quotes via the pass above).
		$blob = (string) \preg_replace(
			'/((?:^|\n)([ \t]{0,32})(["\'])(' . $key . ')\3[ \t]*=>[ \t]*)(?:array\b|\n)(?:[^\n]|\n(?!\2["\'][A-Za-z0-9_.-]{1,80}["\'][ \t]*=>)(?!(?!\2)[ \t]{0,32}["\'][A-Za-z0-9_.-]{1,80}["\'][ \t]*=>))*/i',
			'$1REDACTED',
			$blob
		);

		return self::redact_wpnonce( $blob );
	}

	/**
	 * Secret-bearing assignment names (query / JSON / print_r keys).
	 *
	 * Bare `key` is query-string only — JSON `"key"` is a common cache
	 * identifier and must stay visible.
	 *
	 * @since 2.10.6
	 *
	 * @return string Bounded alternation, no delimiters.
	 */
	private static function redact_secret_name_pattern() {
		return '(?:password|passwd|pass|secret|token|api[_-]?key|access[_-]?key|private[_-]?key|license[_-]?key|auth(?:orization|_token)?|credential|pwd|privkey)';
	}

	/**
	 * Recursively redact string values. Secret-bearing keys blank
	 * the entire value (scalars and arrays) so nested `value`
	 * children cannot keep a raw secret.
	 *
	 * Depth is capped so a pathological nested dump cannot recurse
	 * without bound. Non-string / non-array values are left as-is.
	 *
	 * @since 2.10.6
	 *
	 * @param mixed $value Context value.
	 * @param int   $depth Recursion depth.
	 * @return mixed
	 */
	private static function redact_nested( $value, $depth = 0 ) {
		if ( $depth > 8 ) {
			return \is_string( $value ) ? self::redact( $value ) : $value;
		}

		if ( \is_string( $value ) ) {
			return self::redact( $value );
		}

		if ( ! \is_array( $value ) ) {
			return $value;
		}

		foreach ( $value as $k => $v ) {
			if ( \is_string( $k ) && self::redact_key_is_secret( $k ) ) {
				$value[ $k ] = 'REDACTED';
				continue;
			}
			$value[ $k ] = self::redact_nested( $v, $depth + 1 );
		}

		return $value;
	}

	/**
	 * Whether an array / JSON key name is secret-bearing.
	 *
	 * @since 2.10.6
	 *
	 * @param string $name Key.
	 * @return bool
	 */
	private static function redact_key_is_secret( $name ) {
		$pattern = '/^(?:(?:[A-Za-z0-9][A-Za-z0-9_.-]{0,80}[._-])?' . self::redact_secret_name_pattern() . '|(?:[A-Za-z0-9][A-Za-z0-9_.-]{0,80}\.)key)$/i';
		if ( (bool) \preg_match( $pattern, $name ) ) {
			return true;
		}

		static $schema_secrets = null;
		if ( null === $schema_secrets ) {
			$schema_secrets = array();
			if ( \class_exists( __NAMESPACE__ . '\\Config' ) ) {
				$schema_secrets = Config::secret_keys();
			}
		}

		return isset( $schema_secrets[ $name ] );
	}

	/**
	 * Back-compat alias for {@see self::redact()}.
	 *
	 * @since 2.10.0
	 *
	 * @param mixed $blob Raw text that may contain secrets.
	 * @return string Redacted text.
	 */
	public static function redact_secrets( $blob ) {
		return self::redact( $blob );
	}

	/**
	 * Replace quoted string literals and numeric literals in a SQL
	 * statement with `?` placeholders.
	 *
	 * Keywords, identifiers (including digit-leading ones), operators, and
	 * comment markers stay in place. Backtick-quoted identifiers are copied
	 * through. Literals inside comment text are replaced too. A leading
	 * `+` / `-` is kept as an operator. Parameter masking via
	 * {@see self::redact()} is not applied here.
	 *
	 * @since 2.11.1
	 *
	 * @param string $sql SQL statement.
	 * @return string Statement with literals replaced by `?`.
	 */
	public static function scrub_sql_literals( string $sql ): string {
		return self::scrub_sql_scan( $sql, true );
	}

	/**
	 * Replace literals in one pass over $sql.
	 *
	 * Comment bodies are scanned with $comments false, so comment openers
	 * inside them are plain text. Each byte is scanned at most twice.
	 *
	 * @since 2.11.1
	 *
	 * @param string $sql      SQL statement or comment body.
	 * @param bool   $comments Whether to recognize comment openers.
	 * @return string Text with literals replaced by `?`.
	 */
	private static function scrub_sql_scan( string $sql, bool $comments ): string {
		$length = \strlen( $sql );
		$out    = '';
		$i      = 0;

		while ( $i < $length ) {
			$ch   = $sql[ $i ];
			$next = ( $i + 1 ) < $length ? $sql[ $i + 1 ] : '';

			if ( '`' === $ch ) {
				$end  = self::scrub_sql_span_end( $sql, $i, '`' );
				$out .= \substr( $sql, $i, $end - $i );
				$i    = $end;
				continue;
			}

			if ( '\'' === $ch || '"' === $ch ) {
				$i    = self::scrub_sql_span_end( $sql, $i, $ch );
				$out .= '?';
				continue;
			}

			if ( $comments && '/' === $ch && '*' === $next ) {
				$start = $i + 2;
				if ( $start < $length && '!' === $sql[ $start ] ) {
					$start += 1 + \strspn( $sql, '0123456789', $start + 1 );
				}

				$close = \strpos( $sql, '*/', $start );
				$end   = ( false === $close ) ? $length : $close;
				$out  .= \substr( $sql, $i, $start - $i ) . self::scrub_sql_scan( \substr( $sql, $start, $end - $start ), false );
				if ( false === $close ) {
					$i = $length;
				} else {
					$out .= '*/';
					$i    = $close + 2;
				}
				continue;
			}

			if ( $comments && ( '#' === $ch || ( '-' === $ch && '-' === $next && ( ( $i + 2 ) >= $length || \ord( $sql[ $i + 2 ] ) <= 32 ) ) ) ) {
				$start = $i + ( '#' === $ch ? 1 : 2 );
				$end   = $start + \strcspn( $sql, "\r\n", $start );
				$out  .= \substr( $sql, $i, $start - $i ) . self::scrub_sql_scan( \substr( $sql, $start, $end - $start ), false );
				$i     = $end;
				continue;
			}

			$token = self::scrub_sql_identifier_length( $sql, $i );
			if ( $token > 0 ) {
				if ( $ch >= '0' && $ch <= '9' ) {
					$end = self::scrub_sql_numeric_end( $sql, $i );
					if ( $end >= $i + $token ) {
						$out .= '?';
						$i    = $end;
						continue;
					}
				}

				$out .= \substr( $sql, $i, $token );
				$i   += $token;
				continue;
			}

			if ( '.' === $ch && '' !== $next && $next >= '0' && $next <= '9' ) {
				$prev = ( $i > 0 ) ? $sql[ $i - 1 ] : '';
				if ( '' === $prev || ( '`' !== $prev && ! self::scrub_sql_is_identifier_char( $prev ) ) ) {
					$out .= '?';
					$i    = self::scrub_sql_numeric_end( $sql, $i );
					continue;
				}
			}

			$out .= $ch;
			++$i;
		}

		return $out;
	}

	/**
	 * Copy dbcache flush extras with SQL literals scrubbed for logs.
	 *
	 * @since 2.11.1
	 *
	 * @param array $extras Optional extra parameters for cache flushing.
	 * @return array Copy with every string value, at any depth, scrubbed for debug / purge logs.
	 */
	public static function scrub_dbcache_flush_extras_for_log( array $extras ): array {
		\array_walk_recursive(
			$extras,
			function ( &$value ) {
				if ( \is_string( $value ) ) {
					$value = self::scrub_sql_literals( $value );
				}
			}
		);

		return $extras;
	}

	/**
	 * Index just past a quoted span that opens at $start.
	 *
	 * Strings treat a backslash and a doubled quote as escapes. Backtick
	 * identifiers treat a doubled backtick as an escape. An unclosed span
	 * runs to the end of the statement.
	 *
	 * @since 2.11.1
	 *
	 * @param string $sql   SQL statement.
	 * @param int    $start Index of the opening quote.
	 * @param string $quote Quote character.
	 * @return int
	 */
	private static function scrub_sql_span_end( $sql, $start, $quote ) {
		$length            = \strlen( $sql );
		$backslash_escapes = '`' !== $quote;
		$i                 = $start + 1;

		while ( $i < $length ) {
			if ( $backslash_escapes && '\\' === $sql[ $i ] && ( $i + 1 ) < $length ) {
				$i += 2;
				continue;
			}

			if ( $quote === $sql[ $i ] ) {
				if ( ( $i + 1 ) < $length && $quote === $sql[ $i + 1 ] ) {
					$i += 2;
					continue;
				}

				return $i + 1;
			}

			++$i;
		}

		return $length;
	}

	/**
	 * Index just past the numeric literal that starts at $i.
	 *
	 * Consumes a hex (`0x`) or binary (`0b`) literal, or a decimal with an
	 * optional integer part, fractional part, and exponent.
	 *
	 * @since 2.11.1
	 *
	 * @param string $sql SQL statement.
	 * @param int    $i   Index of the first character of the literal.
	 * @return int
	 */
	private static function scrub_sql_numeric_end( $sql, $i ) {
		$length = \strlen( $sql );

		if ( ( $i + 1 ) < $length && '0' === $sql[ $i ] ) {
			$radix = $sql[ $i + 1 ];
			if ( 'x' === $radix || 'X' === $radix ) {
				return $i + 2 + \strspn( $sql, '0123456789abcdefABCDEF', $i + 2 );
			}

			if ( 'b' === $radix || 'B' === $radix ) {
				return $i + 2 + \strspn( $sql, '01', $i + 2 );
			}
		}

		$i += \strspn( $sql, '0123456789', $i );

		if ( $i < $length && '.' === $sql[ $i ] ) {
			++$i;
			$i += \strspn( $sql, '0123456789', $i );
		}

		if ( $i < $length && ( 'e' === $sql[ $i ] || 'E' === $sql[ $i ] ) ) {
			$j = $i + 1;
			if ( $j < $length && ( '+' === $sql[ $j ] || '-' === $sql[ $j ] ) ) {
				++$j;
			}

			$digits = \strspn( $sql, '0123456789', $j );
			if ( $digits > 0 ) {
				$i = $j + $digits;
			}
		}

		return $i;
	}

	/**
	 * Length of the run of unquoted identifier bytes that starts at $i.
	 *
	 * @since 2.11.1
	 *
	 * @param string $sql SQL statement.
	 * @param int    $i   Start index.
	 * @return int
	 */
	private static function scrub_sql_identifier_length( $sql, $i ) {
		static $mask = null;

		if ( null === $mask ) {
			$mask = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz_$';
			for ( $b = 128; $b < 256; $b++ ) {
				$mask .= \chr( $b );
			}
		}

		return \strspn( $sql, $mask, $i );
	}

	/**
	 * Whether a byte can appear inside an unquoted SQL identifier.
	 *
	 * @since 2.11.1
	 *
	 * @param string $ch Single byte.
	 * @return bool
	 */
	private static function scrub_sql_is_identifier_char( $ch ) {
		return 1 === self::scrub_sql_identifier_length( $ch, 0 );
	}
}
