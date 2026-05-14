<?php
/**
 * File: Util_Cookie.php
 *
 * @package W3TC
 *
 * @since X.X.X
 */

namespace W3TC;

/**
 * Class Util_Cookie
 *
 * Central wrapper for every `w3tc_*` cookie write.
 *
 * Two reasons this exists:
 *
 * 1. **Cookie flags.** The pre-fix code used `setcookie( $name, $value, $expires )`
 *    (positional, 3-arg form) — no `secure`, no `httponly`, no
 *    `samesite`. The fixed version always uses PHP 7.3+ array-form
 *    `setcookie` so every w3tc_* cookie carries the flags WordPress
 *    convention requires (secure on HTTPS, httponly, samesite=Lax).
 *
 * 2. **Role-cookie name derivation.** The legacy code names the
 *    "logged-in-as-this-role" cache-bypass cookie
 *    `'w3tc_logged_' . md5( NONCE_KEY . $role )`. MD5 is broken and
 *    the role list is small — an attacker who leaks `NONCE_KEY` (any
 *    file-disclosure primitive, debug-log leak, etc.) forges the
 *    bypass cookie for any role.
 *
 *    The new name is
 *    `'w3tc_logged_' . substr( hash_hmac('sha256', $role, wp_salt('auth')), 0, 32 )`.
 *    Same length (32 hex chars), HMAC-keyed off the WP auth salt so
 *    leaking the cookie value doesn't help an attacker forge another
 *    role's cookie. `wp_salt()` rotates separately from `NONCE_KEY`,
 *    so salt rotation invalidates these cookies cleanly — which is
 *    the correct security behaviour.
 *
 *    Migration: `role_cookie_name_legacy()` returns the old MD5 form
 *    so readers can accept both names for one release window. Drop
 *    the legacy reader in the next release.
 *
 * @since X.X.X
 */
class Util_Cookie {

	/**
	 * Sets a w3tc_* cookie with the standard security flags.
	 *
	 * Always uses the PHP 7.3+ array-form `setcookie` so `samesite`
	 * lands in the response header. `secure` follows `is_ssl()` so the
	 * cookie stays usable on http-only test installs; `httponly` is
	 * always true; `samesite=Lax` is WordPress convention (`Strict`
	 * breaks legitimate cross-tab admin flows).
	 *
	 * @since X.X.X
	 *
	 * @param string      $name    Cookie name.
	 * @param string|bool $value   Cookie value (cast to string).
	 * @param int         $expires Unix epoch expiry (`0` for session cookie).
	 * @param string|null $path    Override path; defaults to `COOKIEPATH ?: '/'`.
	 * @param string|null $domain  Override domain; defaults to `COOKIE_DOMAIN ?: ''`.
	 *
	 * @return bool Result of `setcookie()`.
	 */
	public static function set( $name, $value, $expires = 0, $path = null, $domain = null ) {
		if ( null === $path ) {
			$path = ( defined( 'COOKIEPATH' ) && COOKIEPATH ) ? COOKIEPATH : '/';
		}
		if ( null === $domain ) {
			$domain = ( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ) ? COOKIE_DOMAIN : '';
		}

		$secure   = \function_exists( 'is_ssl' ) ? (bool) \is_ssl() : false;
		$httponly = true;

		// PHP 7.3+ accepts the options-array form natively. The plugin's
		// composer.json platform pin allows PHP 7.2.5, where passing an
		// array triggers a warning and the cookie is NOT set. Fall back
		// to the positional form on 7.2 and smuggle `SameSite=Lax` via
		// the trailing `; samesite=Lax` on the path argument — Set-Cookie
		// emits the path verbatim, and every supported browser parses the
		// trailing semicolon-separated attribute as a real SameSite token.
		if ( \PHP_VERSION_ID >= 70300 ) {
			$options = array(
				'expires'  => (int) $expires,
				'path'     => (string) $path,
				'domain'   => (string) $domain,
				'secure'   => $secure,
				'httponly' => $httponly,
				'samesite' => 'Lax',
			);
			return \setcookie( $name, (string) $value, $options );
		}

		return \setcookie(
			$name,
			(string) $value,
			(int) $expires,
			(string) $path . '; samesite=Lax',
			(string) $domain,
			$secure,
			$httponly
		);
	}

	/**
	 * Clears a w3tc_* cookie by setting it to an empty value with an
	 * expiry one year in the past, with the same security flags as
	 * `set()`.
	 *
	 * @since X.X.X
	 *
	 * @param string      $name   Cookie name.
	 * @param string|null $path   Override path; defaults to `COOKIEPATH ?: '/'`.
	 * @param string|null $domain Override domain; defaults to `COOKIE_DOMAIN ?: ''`.
	 *
	 * @return bool Result of `setcookie()`.
	 */
	public static function clear( $name, $path = null, $domain = null ) {
		return self::set( $name, '', \time() - 31536000, $path, $domain );
	}

	/**
	 * Returns the HMAC-derived cookie-name suffix for a given WP role.
	 *
	 * Mirror of the legacy `md5( NONCE_KEY . $role )` but:
	 *  - HMAC-SHA256 (preimage- and collision-resistant) instead of MD5.
	 *  - Keyed off `wp_salt('auth')` instead of `NONCE_KEY`. The auth
	 *    salt is the canonical "site identity" secret in WordPress and
	 *    rotates independently of nonces.
	 *  - Truncated to 32 hex chars to preserve the legacy length
	 *    (rejected-cookie lists, regex patterns, and cache-key
	 *    fragments that assume a 32-char suffix continue to work).
	 *
	 * Truncation note: 128 bits of HMAC output is sufficient for the
	 * "is this cookie for role X?" check — there are <100 plausible WP
	 * roles, so collision probability is negligible and preimage
	 * resistance is what matters here.
	 *
	 * @since X.X.X
	 *
	 * @param string $role WordPress role slug.
	 *
	 * @return string 32-char lowercase hex.
	 */
	public static function role_cookie_name( $role ) {
		if ( \function_exists( 'wp_salt' ) ) {
			$salt = (string) \wp_salt( 'auth' );
		} else {
			$salt = ( defined( 'AUTH_KEY' ) ? (string) AUTH_KEY : '' )
				. '|' . ( defined( 'SECURE_AUTH_KEY' ) ? (string) SECURE_AUTH_KEY : '' );
		}

		return \substr( \hash_hmac( 'sha256', (string) $role, $salt ), 0, 32 );
	}

	/**
	 * Returns the legacy MD5 cookie-name suffix for a given WP role.
	 *
	 * Used ONLY by the read path during the one-release back-compat
	 * window so an upgrade doesn't immediately log every cached user
	 * back in. Writers must always use `role_cookie_name()` so that
	 * new cookies carry the HMAC name; readers walk both forms.
	 *
	 * Drop this method (and the dual-read in PgCache_ContentGrabber)
	 * in the release AFTER the one that lands this PR.
	 *
	 * @since X.X.X
	 *
	 * @param string $role WordPress role slug.
	 *
	 * @return string 32-char lowercase hex.
	 */
	public static function role_cookie_name_legacy( $role ) {
		$nonce_key = defined( 'NONCE_KEY' ) ? (string) NONCE_KEY : '';

		return \md5( $nonce_key . (string) $role );
	}
}
