<?php
/**
 * File: Util_ProbeToken.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Util_ProbeToken
 *
 * Single-use authorisation tokens used to gate unauthenticated "probe"
 * endpoints. W3TC ships a handful of endpoints that exist purely so the
 * plugin can self-test its own rewrite / cache configuration from the
 * admin: e.g. {@see Generic_Plugin}'s `?w3tc_rewrite_test=1` echo and
 * {@see Minify_MinifiedFileRequestHandler}'s `rewrite_test.css` /
 * `XXX.css` probes.
 *
 * Without a token the probes are world-reachable. Even though their
 * impact is "Low" individually, they:
 *
 *   1. Fingerprint W3TC's presence (plugin enumeration).
 *   2. Side-channel the cache layer (the rewrite test bypasses the
 *      page cache by design — an attacker can use it to force
 *      uncached requests).
 *
 * This helper centralises the issue/consume pattern so all probe
 * endpoints look the same and the security-sensitive details
 * (cryptographically strong random, site-transient single-use, strict
 * format validation, header-based delivery) live in one place.
 *
 * Issue side: {@see issue()} mints a 32-hex token, stores it as a
 * site transient keyed by token value, returns the raw token. The
 * caller is expected to send it on a self-request via the named
 * header.
 *
 * Consume side: {@see consume()} reads the same header off the
 * inbound request, validates the format, looks up the transient, and
 * deletes it on success so the token cannot be replayed.
 *
 * Each probe class uses its own prefix + header pair so tokens
 * issued for one probe cannot be replayed against another.
 *
 * @since 2.10.0
 */
class Util_ProbeToken {
	/**
	 * Lifetime (seconds) of an issued probe token. Probes are
	 * server-to-self within a single admin request, so a short window
	 * suffices.
	 *
	 * @since 2.10.0
	 *
	 * @var int
	 */
	const TTL = 60;

	/**
	 * Issue a fresh single-use probe token under the given prefix.
	 *
	 * @since 2.10.0
	 *
	 * @param string $prefix Transient-key prefix unique to the probe
	 *                       endpoint, e.g. `w3tc_pgcache_probe_`.
	 *
	 * @return string A 32-character hex token. Callers are expected to
	 *                forward this via the matching HTTP header so the
	 *                consume side can validate it.
	 */
	public static function issue( $prefix ) {
		try {
			// 16 random bytes -> 32 hex chars; cryptographically strong.
			$token = bin2hex( random_bytes( 16 ) );
		} catch ( \Exception $e ) {
			/**
			 * Random_bytes() only throws if the OS RNG is unavailable.
			 * Fall back to wp_generate_password but normalise so the
			 * output still matches the strict /^[a-f0-9]{32}$/ shape
			 * the consume side requires.
			 */
			$raw   = \wp_generate_password( 16, false, false );
			$token = strtolower( bin2hex( substr( $raw, 0, 16 ) ) );
		}

		\set_site_transient( $prefix . $token, '1', self::TTL );

		return $token;
	}

	/**
	 * Validate the inbound probe token and consume it on success so it
	 * cannot be replayed.
	 *
	 * @since 2.10.0
	 *
	 * @param string $prefix      Transient-key prefix used at issue
	 *                            time, e.g. `w3tc_pgcache_probe_`.
	 * @param string $header_name HTTP header carrying the token,
	 *                            e.g. `X-W3TC-PgCache-Probe`. Case-
	 *                            insensitive; PHP normalises to the
	 *                            `HTTP_X_W3TC_PGCACHE_PROBE` env var.
	 *
	 * @return bool True if the request presents a matching probe token.
	 */
	public static function consume( $prefix, $header_name ) {
		$w3tc_key = 'HTTP_' . strtoupper( str_replace( '-', '_', $header_name ) );
		if ( empty( $_SERVER[ $w3tc_key ] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- value is normalised by the strict /^[a-f0-9]{32}$/ regex on the next non-comment line.
		$presented = trim( (string) \wp_unslash( $_SERVER[ $w3tc_key ] ) );
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $presented ) ) {
			return false;
		}

		/**
		 * Token IS the transient key. Lookup is the validation: a
		 * missing transient (false) means the token is invalid, has
		 * expired, or has already been consumed. The hex-format check
		 * above bounds the lookup keyspace; the unguessable random
		 * token bounds the probability that a guess collides with a
		 * live issued token.
		 */
		$tkey  = $prefix . $presented;
		$valid = \get_site_transient( $tkey );
		if ( false === $valid ) {
			return false;
		}

		\delete_site_transient( $tkey );

		return true;
	}

	/**
	 * Emit a 404-style response and exit. Standard rejection used by
	 * probe endpoints when {@see consume()} fails.
	 *
	 * @since 2.10.0
	 *
	 * @return void
	 */
	public static function reject() {
		if ( ! \headers_sent() ) {
			\status_header( 404 );
			\nocache_headers();
		}
		exit();
	}
}
