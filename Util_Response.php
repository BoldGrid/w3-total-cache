<?php
/**
 * File: Util_Response.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Util_Response
 *
 * Centralised response-header emitter that refuses to write any
 * header containing CRLF or NUL bytes.
 *
 * Background — :
 *
 * W3TC interpolates several admin-settable strings into response
 * headers (most notably `cdn.engine` → `X-W3TC-CDN`). On PHP 8.3+
 * the runtime already rejects raw CRLF inside `header()`; on
 * PHP 7.4 – 8.2 the protection is partial and version-dependent.
 * Routing every header emission through this helper gives us a
 * single, version-independent chokepoint to validate the bytes
 * we send, and a place future code can hang additional rules
 * (length, character class, well-known header names).
 *
 * @since 2.10.0
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */
class Util_Response {
	/**
	 * Emit a response header after validating that both name and
	 * value are free of CR, LF, and NUL bytes.
	 *
	 * Behavior:
	 *
	 *  * The header name is validated against RFC 7230 token rules
	 *    (visible ASCII, no separators, no whitespace). Anything
	 *    failing that check is dropped silently — a downstream
	 *    sanitizer that emits a bad name is a bug, not a path we
	 *    want to "fix" by mangling characters.
	 *  * The value is checked for CR / LF / NUL only. Any of those
	 *    three causes the entire emission to be skipped; we never
	 *    try to "repair" a poisoned value because the cleaned
	 *    version may not match what the caller intended to assert.
	 *  * If `headers_sent()` is already true when the emitter is
	 *    called, the underlying `header()` is a no-op. The return
	 *    value reflects that: `false` when the response has already
	 *    started, so callers can distinguish "emitted" from
	 *    "swallowed because headers were flushed".
	 *
	 * @since 2.10.0
	 *
	 * @param string $w3tc_name    HTTP header name (e.g. `X-W3TC-CDN`).
	 * @param string $w3tc_value   HTTP header value.
	 * @param bool   $replace Replace any existing header of the same name.
	 *                        Mirrors the third argument of PHP's `header()`.
	 *
	 * @return bool True if the header was actually emitted (validated
	 *              AND the response had not already started), false
	 *              when the value was rejected at validation OR when
	 *              `headers_sent()` was already true.
	 */
	public static function header( $w3tc_name, $w3tc_value, $replace = true ) {
		if ( ! \is_string( $w3tc_name ) || ! \is_string( $w3tc_value ) ) {
			return false;
		}

		if ( '' === $w3tc_name ) {
			return false;
		}

		/**
		 * RFC 7230 §3.2.6 — header names are tokens: VCHAR minus
		 * separators. Anything outside that set is a bug or a
		 * CRLF-bearing value passed through the wrong code path;
		 * refuse to emit either way.
		 */
		if ( ! \preg_match( '/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/', $w3tc_name ) ) {
			return false;
		}

		/**
		 * CR / LF / NUL in the value would split or terminate the
		 * response header section. PHP 8.3 already throws on these
		 * from `header()`; this guard keeps PHP 7.x parity and
		 * avoids the warning by short-circuiting first.
		 */
		if ( false !== \strpbrk( $w3tc_value, "\r\n\0" ) ) {
			return false;
		}

		/**
		 * If headers have already been flushed there's nothing we can
		 * do; `header()` will warn and silently no-op. Surface that
		 * to the caller via the return value rather than reporting
		 * "true" for a no-op.
		 */
		if ( \headers_sent() ) {
			return false;
		}

		@\header( $w3tc_name . ': ' . $w3tc_value, $replace );

		return true;
	}

	/**
	 * Return the canonical "safe" form of a header value, or the
	 * empty string if the value contains characters we refuse to
	 * emit. Convenience accessor for callers that want to test a
	 * value before deciding whether to fall back to a default.
	 *
	 * @since 2.10.0
	 *
	 * @param string $w3tc_value Candidate value.
	 *
	 * @return string The original value if it contains no CR / LF /
	 *                NUL bytes, otherwise an empty string.
	 */
	public static function sanitize_header_value( $w3tc_value ) {
		if ( ! \is_string( $w3tc_value ) ) {
			return '';
		}

		if ( false !== \strpbrk( $w3tc_value, "\r\n\0" ) ) {
			return '';
		}

		return $w3tc_value;
	}
}
