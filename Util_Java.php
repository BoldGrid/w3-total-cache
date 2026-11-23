<?php
/**
 * File: Util_Java.php
 *
 * @package W3TC
 *
 * @since 2.10.0
 */

namespace W3TC;

/**
 * Class Util_Java
 *
 * Validates administrator-supplied paths to a Java binary and to the
 * minifier JAR against allowlists of trusted directories before those
 * paths are assigned to the vendored minifier wrapper statics
 * (`$javaExecutable`, `$jarFile`). The vendored code
 * (`lib/Minify/Minify/{YUICompressor,ClosureCompiler}.php`) concatenates
 * those properties into the command string passed to `exec()`, so values
 * that are not first run through these validators would not be escaped
 * at the boundary.
 *
 * The candidate path must canonicalize via `realpath()`, must be
 * `is_file()` + `is_executable()`, and must live underneath one of the
 * allowed directories returned by `allowed_dirs()`. Operators with
 * non-standard layouts may extend the list by defining
 * `W3TC_JAVA_BIN_ALLOWED_DIRS` in `wp-config.php`. The override is
 * parsed using PHP's `PATH_SEPARATOR` so it is portable between *nix
 * (`:`-separated) and Windows (`;`-separated) installs and does not
 * collide with the colon in a Windows drive letter. The constant is
 * server-side only — it is never read from web input.
 *
 * @since 2.10.0
 */
class Util_Java {
	/**
	 * Validate a candidate Java executable path against the allowlist.
	 *
	 * Returns the canonical absolute path on success, or `false` on
	 * any failure. Callers should treat a `false` return as a signal
	 * to disable the engine; they MUST NOT fall back to the raw
	 * value, because the vendored wrapper does not escape it at the
	 * boundary.
	 *
	 * @since 2.10.0
	 *
	 * @param string $path Candidate path to a Java binary.
	 *
	 * @return string|false Canonical path on success, false on rejection.
	 */
	public static function validate( $path ) {
		if ( ! \is_string( $path ) || '' === $path ) {
			return false;
		}

		/**
		 * Reject any control character or shell metacharacter before
		 * we even touch the filesystem. realpath() would normally
		 * strip most of these, but rejecting early keeps the log
		 * output sane and makes the validate-then-use sequence
		 * single-step.
		 *
		 * The metachar set is platform-aware: on Windows the path
		 * separator is `\\` and legitimate Java installs commonly
		 * live under `C:\Program Files (x86)\Java\...` (spaces and
		 * parentheses are allowed in the directory name), so the
		 * non-Windows regex would block every real Windows path.
		 * On Windows we still reject the cmd.exe metacharacters
		 * (`& | < > ^ "` plus glob characters) and rely on
		 * `realpath()` + the allowlist below to enforce the actual
		 * "is this a Java binary" check.
		 */
		if ( self::contains_shell_metacharacters( $path ) ) {
			return false;
		}

		$real = \realpath( $path );
		if ( false === $real || '' === $real ) {
			return false;
		}

		if ( ! \is_file( $real ) || ! \is_executable( $real ) ) {
			return false;
		}

		/**
		 * On Windows the binary must end in `.exe` to be runnable
		 * without a shell-side resolution step.
		 */
		if ( '\\' === DIRECTORY_SEPARATOR ) {
			if ( '.exe' !== \strtolower( \substr( $real, -4 ) ) ) {
				return false;
			}
		}

		if ( ! self::is_under_allowed_dirs( $real, self::allowed_dirs() ) ) {
			return false;
		}

		return $real;
	}

	/**
	 * Validate a candidate value for the Java executable and emit a
	 * debug-log entry if the candidate is rejected.
	 *
	 * Thin wrapper around `validate()` that surfaces the rejection
	 * to operators via the `minify` debug log so admins can diagnose
	 * a "minifier stopped working" symptom directly from the log.
	 *
	 * @since 2.10.0
	 *
	 * @param string $path    Candidate path to a Java binary.
	 * @param string $context Short tag used in the debug-log entry
	 *                       (for example, the engine slug `yuijs`).
	 *
	 * @return string|false Canonical path on success, false on rejection.
	 */
	public static function validate_with_log( $path, $context = '' ) {
		$w3tc_result = self::validate( $path );
		if ( false === $w3tc_result ) {
			$tag = '' === $context ? 'java' : $context;
			/**
			 * Include the rejected path verbatim in the log line. The metachar
			 * regex inside `validate()` runs before this code path, so the
			 * string is free of control bytes; debug logs land on the
			 * filesystem (not in any UI), so echoing the path is operator-
			 * facing only. Without this, debugging "minifier silently
			 * stopped working" requires instrumenting PHP to see what value
			 * was being validated.
			 */
			$logged_path = \is_string( $path ) ? $path : '(non-string)';
			Util_Debug::log(
				'minify',
				\sprintf(
					'Util_Java: rejected non-allowlisted Java executable path "%s" for %s (allowed dirs: %s).',
					$logged_path,
					$tag,
					\implode( PATH_SEPARATOR, self::allowed_dirs() )
				)
			);
		}
		return $w3tc_result;
	}

	/**
	 * Validate a candidate JAR path before it is passed to `java -jar`.
	 *
	 * Returns the canonical absolute path on success, or `false` on
	 * any failure. Callers must not fall back to the raw value: the
	 * vendored minifier wrappers interpolate `$jarFile` into `exec()`.
	 *
	 * The candidate must canonicalize via `realpath()` (symlink targets
	 * that leave the allowlist are refused), must be a readable regular
	 * file whose name ends in `.jar`, must start with ZIP magic bytes,
	 * and must live under one of `allowed_jar_dirs()`. Operators with
	 * non-standard layouts may extend the list by defining
	 * `W3TC_JAVA_JAR_ALLOWED_DIRS` in `wp-config.php`.
	 *
	 * @since 2.10.6
	 *
	 * @param string $path Candidate path to a JAR.
	 *
	 * @return string|false Canonical path on success, false on rejection.
	 */
	public static function validate_jar( $path ) {
		if ( ! \is_string( $path ) || '' === $path ) {
			return false;
		}

		if ( self::contains_shell_metacharacters( $path ) ) {
			return false;
		}

		$real = \realpath( $path );
		if ( false === $real || '' === $real ) {
			return false;
		}

		if ( ! \is_file( $real ) || ! \is_readable( $real ) ) {
			return false;
		}

		if ( '.jar' !== \strtolower( \substr( $real, -4 ) ) ) {
			return false;
		}

		if ( ! self::is_under_allowed_dirs( $real, self::allowed_jar_dirs() ) ) {
			return false;
		}

		/**
		 * JAR files are ZIP archives. Read magic only after the
		 * allowlist check so out-of-boundary files are never opened.
		 */
		$handle = \fopen( $real, 'rb' );
		if ( false === $handle ) {
			return false;
		}
		$magic = \fread( $handle, 4 );
		\fclose( $handle );
		if ( ! \is_string( $magic ) || 2 > \strlen( $magic ) || 0 !== \strpos( $magic, 'PK' ) ) {
			return false;
		}

		return $real;
	}

	/**
	 * Validate a candidate JAR path and emit a minify debug-log entry
	 * if the candidate is rejected.
	 *
	 * @since 2.10.6
	 *
	 * @param string $path    Candidate path to a JAR.
	 * @param string $context Short tag used in the debug-log entry.
	 *
	 * @return string|false Canonical path on success, false on rejection.
	 */
	public static function validate_jar_with_log( $path, $context = '' ) {
		$w3tc_result = self::validate_jar( $path );
		if ( false === $w3tc_result ) {
			$tag         = '' === $context ? 'jar' : $context;
			$logged_path = \is_string( $path ) ? $path : '(non-string)';
			Util_Debug::log(
				'minify',
				\sprintf(
					'Util_Java: rejected JAR path "%s" for %s (allowed dirs: %s).',
					$logged_path,
					$tag,
					\implode( PATH_SEPARATOR, self::allowed_jar_dirs() )
				)
			);
		}
		return $w3tc_result;
	}

	/**
	 * Validate a Java executable and JAR together.
	 *
	 * Returns canonical paths on success, or `false` if either path is
	 * rejected. Callers should treat `false` as "do not invoke the tool".
	 *
	 * @since 2.10.6
	 *
	 * @param string $path_java Candidate Java executable.
	 * @param string $path_jar  Candidate JAR.
	 * @param string $context   Short tag for debug-log entries.
	 *
	 * @return array{java:string,jar:string}|false
	 */
	public static function validate_tools( $path_java, $path_jar, $context = '' ) {
		$java = self::validate_with_log( $path_java, $context );
		if ( false === $java ) {
			return false;
		}

		$jar = self::validate_jar_with_log( $path_jar, $context );
		if ( false === $jar ) {
			return false;
		}

		return array(
			'java' => $java,
			'jar'  => $jar,
		);
	}

	/**
	 * Returns the list of directories from which a JAR is accepted.
	 *
	 * Defaults include the plugin directory, `WP_CONTENT_DIR`, and
	 * platform package locations. Operators may override by defining
	 * `W3TC_JAVA_JAR_ALLOWED_DIRS` in `wp-config.php` using
	 * `PATH_SEPARATOR`. Existing directories are canonicalized so a
	 * symlinked `wp-content` still matches `realpath()` of a JAR.
	 *
	 * @since 2.10.6
	 *
	 * @return string[]
	 */
	public static function allowed_jar_dirs() {
		if ( \defined( 'W3TC_JAVA_JAR_ALLOWED_DIRS' ) ) {
			$raw  = (string) \constant( 'W3TC_JAVA_JAR_ALLOWED_DIRS' );
			$dirs = \array_filter( \array_map( 'trim', \explode( PATH_SEPARATOR, $raw ) ) );
			if ( ! empty( $dirs ) ) {
				return self::canonicalize_allowed_dirs( \array_values( $dirs ) );
			}
		}

		$dirs = array();
		if ( \defined( 'W3TC_DIR' ) && \is_string( \constant( 'W3TC_DIR' ) ) && '' !== \constant( 'W3TC_DIR' ) ) {
			$dirs[] = \constant( 'W3TC_DIR' );
		}
		if ( \defined( 'WP_CONTENT_DIR' ) && \is_string( \WP_CONTENT_DIR ) && '' !== \WP_CONTENT_DIR ) {
			$dirs[] = \WP_CONTENT_DIR;
		}

		if ( '\\' === DIRECTORY_SEPARATOR ) {
			$dirs[] = 'C:\\Program Files';
			$dirs[] = 'C:\\Program Files (x86)';
		} else {
			$dirs[] = '/usr/share/java';
			$dirs[] = '/usr/share';
			$dirs[] = '/opt';
		}

		return self::canonicalize_allowed_dirs( $dirs );
	}

	/**
	 * Returns the list of directories from which a Java binary is
	 * accepted. Defaults to a conservative set per platform;
	 * operators may override by defining `W3TC_JAVA_BIN_ALLOWED_DIRS`
	 * in `wp-config.php`. The override is parsed using PHP's
	 * `PATH_SEPARATOR` (`;` on Windows, `:` elsewhere) so a Windows
	 * drive letter such as `C:\Program Files\Java\bin` is not
	 * accidentally split on its own colon.
	 *
	 * The constant must be defined server-side; it is never read
	 * from web input.
	 *
	 * **Allowlist semantics — sharp edge.** The check in `validate()`
	 * is a pure string-prefix match against `<dir>/`. Single-segment
	 * values broaden the allowlist substantially: setting
	 * `W3TC_JAVA_BIN_ALLOWED_DIRS = '/usr'` accepts every file under
	 * `/usr/*` (e.g. `/usr/anything/at/all/java`). Defensive operators
	 * should prefer the narrowest viable parent — for example
	 * `/opt/openjdk-17/bin` rather than `/opt` — so a future
	 * world-writable subdirectory cannot turn into an exec sink.
	 *
	 * @since 2.10.0
	 *
	 * @return string[]
	 */
	public static function allowed_dirs() {
		if ( \defined( 'W3TC_JAVA_BIN_ALLOWED_DIRS' ) ) {
			$raw  = (string) \constant( 'W3TC_JAVA_BIN_ALLOWED_DIRS' );
			$dirs = \array_filter( \array_map( 'trim', \explode( PATH_SEPARATOR, $raw ) ) );
			if ( ! empty( $dirs ) ) {
				return \array_values( $dirs );
			}
		}

		if ( '\\' === DIRECTORY_SEPARATOR ) {
			return array(
				'C:\\Program Files\\Java',
				'C:\\Program Files (x86)\\Java',
			);
		}

		/**
		 * On most Linux distributions (Debian/Ubuntu/RHEL/Amazon/Alpine),
		 * the canonical `/usr/bin/java` is a symlink chain that resolves
		 * (via `/etc/alternatives/java`) to a binary under `/usr/lib/jvm/...`.
		 * `validate()` checks the realpath-resolved location, so the
		 * default allowlist must include `/usr/lib/jvm` — otherwise the
		 * documented "configure path.java = /usr/bin/java" path fails
		 * validation and the Java minifiers silently stay disabled on
		 * every stock-distro install.
		 */
		return array(
			'/usr/bin',
			'/usr/local/bin',
			'/usr/lib/jvm',
			'/opt',
		);
	}

	/**
	 * Validate the user-supplied options for the Closure Compiler
	 * engine against an allowlist of expected values. Returns a
	 * sanitised array suitable for handing to the vendored
	 * `Minify_ClosureCompiler` minify call.
	 *
	 * The vendored `_getCmd` only consumes `compilation_level` and
	 * `charset` (the latter is hardcoded to `utf-8`). The vendored
	 * code already passes `compilation_level` through
	 * `escapeshellarg()`, so the values here are belt-and-braces:
	 * any value outside the documented set is dropped, falling
	 * back to the default.
	 *
	 * @since 2.10.0
	 *
	 * @param array $options Raw options.
	 *
	 * @return array Sanitised options.
	 */
	public static function sanitize_ccjs_options( $options ) {
		$out = array();

		$valid_levels = array(
			'WHITESPACE_ONLY',
			'SIMPLE_OPTIMIZATIONS',
			'ADVANCED_OPTIMIZATIONS',
		);
		if ( isset( $options['compilation_level'] ) && \in_array( $options['compilation_level'], $valid_levels, true ) ) {
			$out['compilation_level'] = $options['compilation_level'];
		}

		/**
		 * `formatting` is consumed by the W3TC-side wrapper, not the
		 * vendored `_getCmd`, but pass it through with the same
		 * allowlist so downstream code stays consistent.
		 */
		$valid_formatting = array( 'pretty_print', 'print_input_delimiter', '' );
		if ( isset( $options['formatting'] ) && \in_array( $options['formatting'], $valid_formatting, true ) ) {
			$out['formatting'] = $options['formatting'];
		}

		return $out;
	}

	/**
	 * Validate the user-supplied options for the YUI Compressor
	 * engine against an allowlist. The vendored `_getCmd` consumes
	 * a handful of named options; only well-typed values are
	 * forwarded.
	 *
	 * @since 2.10.0
	 *
	 * @param array $options Raw options.
	 *
	 * @return array Sanitised options.
	 */
	public static function sanitize_yui_options( $options ) {
		$out = array();

		if ( isset( $options['line-break'] ) && \is_numeric( $options['line-break'] ) ) {
			$out['line-break'] = (int) $options['line-break'];
		}
		if ( isset( $options['stack-size'] ) && \is_numeric( $options['stack-size'] ) ) {
			$out['stack-size'] = (int) $options['stack-size'];
		}
		foreach ( array( 'nomunge', 'preserve-semi', 'disable-optimizations' ) as $w3tc_key ) {
			if ( isset( $options[ $w3tc_key ] ) ) {
				$out[ $w3tc_key ] = (bool) $options[ $w3tc_key ];
			}
		}

		return $out;
	}

	/**
	 * True when $path contains a control byte or shell metacharacter.
	 *
	 * @since 2.10.6
	 *
	 * @param string $path Candidate path.
	 *
	 * @return bool
	 */
	private static function contains_shell_metacharacters( $path ) {
		if ( '\\' === DIRECTORY_SEPARATOR ) {
			$metachar_re = '/[\x00-\x1F\x7F&|<>^"\*\?]/';
		} else {
			$metachar_re = '/[\x00-\x1F\x7F;&|`$<>"\'\\\\(){}\[\]\*\?]/';
		}

		return 1 === \preg_match( $metachar_re, $path );
	}

	/**
	 * True when canonical $real is under one of $allowed as a directory prefix.
	 *
	 * @since 2.10.6
	 *
	 * @param string   $real    Canonical absolute path.
	 * @param string[] $allowed Directory prefixes.
	 *
	 * @return bool
	 */
	private static function is_under_allowed_dirs( $real, array $allowed ) {
		$is_windows   = '\\' === DIRECTORY_SEPARATOR;
		$real_compare = $is_windows ? \strtolower( $real ) : $real;
		foreach ( $allowed as $dir ) {
			if ( '' === $dir ) {
				continue;
			}
			$prefix = \rtrim( $dir, '/\\' ) . DIRECTORY_SEPARATOR;
			if ( $is_windows ) {
				$prefix = \strtolower( $prefix );
			}
			if ( 0 === \strpos( $real_compare, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Canonicalize existing allowlist directories so prefix checks match realpath().
	 *
	 * @since 2.10.6
	 *
	 * @param string[] $dirs Directory prefixes.
	 *
	 * @return string[]
	 */
	private static function canonicalize_allowed_dirs( array $dirs ) {
		$out = array();
		foreach ( $dirs as $dir ) {
			if ( ! \is_string( $dir ) || '' === $dir ) {
				continue;
			}
			$resolved = \realpath( $dir );
			$out[]    = false !== $resolved && '' !== $resolved ? $resolved : $dir;
		}

		return \array_values( \array_unique( $out ) );
	}
}
