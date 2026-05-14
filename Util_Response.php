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
 * Centralized response-header emitter that refuses to write any
 * header containing CRLF or NUL bytes.
 *
 * Background — rt9-102 / header-injection:
 *
 * W3TC interpolates several admin-settable strings into response
 * headers (most notably `cdn.engine` → `X-W3TC-CDN`). On PHP 8.3+
 * the runtime already rejects raw CRLF inside `header()`; on
 * PHP 7.2 – 8.2 the protection is partial and version-dependent.
 * Routing every header emission through this helper gives us a
 * single, version-independent chokepoint to validate the bytes
 * we send, and a place future code can hang additional rules
 * (length, character class, well-known header names).
 *
 * @since X.X.X
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
	 *  * Headers are emitted with `@header()` so emission attempts
	 *    after `headers_sent()` are quiet, matching the prior
	 *    `@header()` pattern used at all the call sites we replace.
	 *
	 * @since X.X.X
	 *
	 * @param string $name    HTTP header name (e.g. `X-W3TC-CDN`).
	 * @param string $value   HTTP header value.
	 * @param bool   $replace Replace any existing header of the same name.
	 *                        Mirrors the third argument of PHP's `header()`.
	 *
	 * @return bool True if the header was emitted, false if it was rejected.
	 */
	public static function header( $name, $value, $replace = true ) {
		if ( ! \is_string( $name ) || ! \is_string( $value ) ) {
			return false;
		}

		if ( '' === $name ) {
			return false;
		}

		// RFC 7230 §3.2.6 — header names are tokens: VCHAR minus
		// separators. Anything outside that set is a bug or an
		// injection attempt; refuse to emit either way.
		if ( ! \preg_match( '/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/', $name ) ) {
			return false;
		}

		// CR / LF / NUL in the value would split or terminate the
		// response header section. PHP 8.3 already throws on these
		// from `header()`; this guard keeps PHP 7.x parity and
		// avoids the warning by short-circuiting first.
		if ( false !== \strpbrk( $value, "\r\n\0" ) ) {
			return false;
		}

		@\header( $name . ': ' . $value, $replace );

		return true;
	}

	/**
	 * Return the canonical "safe" form of a header value, or the
	 * empty string if the value contains characters we refuse to
	 * emit. Convenience accessor for callers that want to test a
	 * value before deciding whether to fall back to a default.
	 *
	 * @since X.X.X
	 *
	 * @param string $value Candidate value.
	 *
	 * @return string The original value if it contains no CR / LF /
	 *                NUL bytes, otherwise an empty string.
	 */
	public static function sanitize_header_value( $value ) {
		if ( ! \is_string( $value ) ) {
			return '';
		}

		if ( false !== \strpbrk( $value, "\r\n\0" ) ) {
			return '';
		}

		return $value;
	}
}
