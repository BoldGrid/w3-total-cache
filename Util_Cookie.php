<?php
/**
 * File: Util_Cookie.php
 *
 * @package W3TC
 *
 * @since 2.10.0
 */

namespace W3TC;

/**
 * Class Util_Cookie
 *
 * Central wrapper for every `w3tc_*` cookie write.
 *
 * Two reasons this exists:
 *
 * 1. **Cookie flags.** The pre-fix code used `setcookie( $w3tc_name, $w3tc_value, $expires )`
 *    (positional, 3-arg form) — no `secure`, no `httponly`, no
 *    `samesite`. The fixed version always uses the array-form
 *    `setcookie` (available since PHP 7.3, well below the plugin's
 *    PHP 7.4+ floor) so every w3tc_* cookie carries the flags
 *    WordPress convention requires (secure on HTTPS, httponly,
 *    samesite=Lax).
 *
 * 2. **Role-cookie name derivation.** The legacy code names the
 *    "logged-in-as-this-role" cache-bypass cookie
 *    `'w3tc_logged_' . md5( NONCE_KEY . $role )`. MD5 is broken and
 *    the role list is small — an attacker who leaks `NONCE_KEY` (any
 *    file-disclosure primitive, debug-log leak, etc.) forges the
 *    bypass cookie for any role.
 *
 *    The new name is
 *    `'w3tc_logged_' . substr( hash_hmac('sha256', $role, AUTH_KEY . AUTH_SALT), 0, 32 )`.
 *    Same length (32 hex chars), HMAC-keyed off the WP auth secrets so
 *    leaking the cookie value doesn't help an attacker forge another
 *    role's cookie. The salt is taken from the `AUTH_KEY`/`AUTH_SALT`
 *    wp-config constants rather than `wp_salt()` because the page-cache
 *    read path runs from `advanced-cache.php` before `wp_salt()` is
 *    defined; see `role_cookie_name()` for the full rationale. Rotating
 *    `AUTH_KEY`/`AUTH_SALT` invalidates these cookies cleanly.
 *
 *    Role cookies use HMAC names only. Pre-HMAC leftover cookies
 *    are ignored on read and write.
 *
 * @since 2.10.0
 */
class Util_Cookie {

	/**
	 * Sets a w3tc_* cookie with the standard security flags.
	 *
	 * Always uses the array-form `setcookie` so `samesite` lands in
	 * the response header. `secure` follows `is_ssl()` so the
	 * cookie stays usable on http-only test installs; `httponly` is
	 * always true; `samesite=Lax` is WordPress convention (`Strict`
	 * breaks legitimate cross-tab admin flows).
	 *
	 * @since 2.10.0
	 *
	 * @param string      $w3tc_name    Cookie name.
	 * @param string|bool $w3tc_value   Cookie value (cast to string).
	 * @param int         $expires Unix epoch expiry (`0` for session cookie).
	 * @param string|null $path    Override path; defaults to `COOKIEPATH ?: '/'`.
	 * @param string|null $domain  Override domain; defaults to `COOKIE_DOMAIN ?: ''`.
	 *
	 * @return bool Result of `setcookie()`.
	 */
	public static function set( $w3tc_name, $w3tc_value, $expires = 0, $path = null, $domain = null ) {
		if ( null === $path ) {
			$path = ( defined( 'COOKIEPATH' ) && COOKIEPATH ) ? COOKIEPATH : '/';
		}
		if ( null === $domain ) {
			$domain = ( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ) ? COOKIE_DOMAIN : '';
		}

		$secure   = \function_exists( 'is_ssl' ) ? (bool) \is_ssl() : false;
		$httponly = true;

		/**
		 * PHP 7.3+ accepts the options-array form natively, which is
		 * always available on the plugin's PHP 7.4+ floor. The
		 * positional-form fallback below is retained defensively in
		 * case a host downgrades the runtime below the declared
		 * minimum; it smuggles `SameSite=Lax` via the trailing
		 * `; samesite=Lax` on the path argument — Set-Cookie emits
		 * the path verbatim, and every supported browser parses the
		 * trailing semicolon-separated attribute as a real SameSite
		 * token.
		 */
		if ( \PHP_VERSION_ID >= 70300 ) {
			$options = array(
				'expires'  => (int) $expires,
				'path'     => (string) $path,
				'domain'   => (string) $domain,
				'secure'   => $secure,
				'httponly' => $httponly,
				'samesite' => 'Lax',
			);
			return \setcookie( $w3tc_name, (string) $w3tc_value, $options );
		}

		return \setcookie(
			$w3tc_name,
			(string) $w3tc_value,
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
	 * @since 2.10.0
	 *
	 * @param string      $w3tc_name   Cookie name.
	 * @param string|null $path   Override path; defaults to `COOKIEPATH ?: '/'`.
	 * @param string|null $domain Override domain; defaults to `COOKIE_DOMAIN ?: ''`.
	 *
	 * @return bool Result of `setcookie()`.
	 */
	public static function clear( $w3tc_name, $path = null, $domain = null ) {
		return self::set( $w3tc_name, '', \time() - 31536000, $path, $domain );
	}

	/**
	 * Returns the HMAC-derived cookie-name suffix for a given WP role.
	 *
	 * Mirror of the legacy `md5( NONCE_KEY . $role )` but HMAC-SHA256
	 * (preimage- and collision-resistant) instead of MD5, truncated to
	 * 32 hex chars to preserve the legacy length (rejected-cookie lists,
	 * regex patterns, and cache-key fragments that assume a 32-char
	 * suffix continue to work).
	 *
	 * CRITICAL — context independence. This name is derived in two
	 * execution contexts that MUST agree:
	 *
	 *  - WRITE: at login, in `Generic_Plugin::check_login_action()`,
	 *    with full WordPress loaded.
	 *  - READ: in the page-cache path
	 *    (`PgCache_ContentGrabber::_can_read_cache()`), invoked inline
	 *    from `advanced-cache.php` — which `wp-settings.php` loads BEFORE
	 *    `pluggable.php` defines `wp_salt()`.
	 *
	 * An earlier version keyed the HMAC off `wp_salt('auth')` with a
	 * different constant fallback when `wp_salt()` was missing. The two
	 * contexts then produced different names, so the bypass cookie set at
	 * login was never matched on read and role-based page-cache exclusion
	 * silently failed — a logged-in privileged user's page got cached and
	 * could be served to others. We therefore derive the salt ONLY from
	 * the wp-config secret constants, which are present in both contexts.
	 * `AUTH_KEY . AUTH_SALT` is exactly what `wp_salt('auth')` returns on
	 * a configured install, so cookies minted by the previous
	 * (wp_salt-based) code keep matching.
	 *
	 * Truncation note: 128 bits of HMAC output is sufficient for the
	 * "is this cookie for role X?" check — there are <100 plausible WP
	 * roles, so collision probability is negligible and preimage
	 * resistance is what matters here.
	 *
	 * @since 2.10.0
	 *
	 * @param string $role WordPress role slug.
	 *
	 * @return string 32-char lowercase hex.
	 */
	public static function role_cookie_name( $role ) {
		$salt = ( defined( 'AUTH_KEY' ) ? (string) AUTH_KEY : '' )
			. ( defined( 'AUTH_SALT' ) ? (string) AUTH_SALT : '' );

		return \substr( \hash_hmac( 'sha256', (string) $role, $salt ), 0, 32 );
	}

	/**
	 * Cookie names written into page-cache rewrite reject lists
	 * for a given role.
	 *
	 * @since 2.10.6
	 *
	 * @param string $role WordPress role slug.
	 *
	 * @return string[]
	 */
	public static function role_cookie_reject_names( $role ) {
		return array(
			'w3tc_logged_' . self::role_cookie_name( $role ),
		);
	}

	/**
	 * Whether a request cookie name matches a rejected role.
	 *
	 * Malformed or unrecognized `w3tc_logged_*` names never match.
	 *
	 * @since 2.10.6
	 *
	 * @param string $cookie_name Raw cookie name from the request.
	 * @param string $role        WordPress role slug.
	 *
	 * @return bool
	 */
	public static function cookie_matches_rejected_role( $cookie_name, $role ) {
		if ( ! is_string( $cookie_name ) || 0 !== \strpos( $cookie_name, 'w3tc_logged_' ) ) {
			return false;
		}

		return false !== \strstr( $cookie_name, self::role_cookie_name( (string) $role ) );
	}

	/**
	 * Whether the current request carries a rejected-role cache-bypass cookie.
	 *
	 * @since 2.10.6
	 *
	 * @param string[] $roles Role slugs configured for cache rejection.
	 *
	 * @return bool True when page cache must be skipped.
	 */
	public static function request_has_rejected_role_cookie( $roles ) {
		if ( ! is_array( $roles ) || empty( $roles ) ) {
			return false;
		}

		if ( empty( $_COOKIE ) || ! is_array( $_COOKIE ) ) {
			return false;
		}

		foreach ( \array_keys( $_COOKIE ) as $cookie_name ) {
			foreach ( $roles as $role ) {
				if ( self::cookie_matches_rejected_role( (string) $cookie_name, (string) $role ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Whether the current WordPress user has a rejected page-cache role.
	 *
	 * Used on the page-cache write path after WordPress has loaded.
	 * The early `advanced-cache.php` read path cannot use this.
	 *
	 * @since X.X.X
	 *
	 * @param string[] $roles Role slugs configured for cache rejection.
	 *
	 * @return bool
	 */
	public static function current_user_has_rejected_role( $roles ) {
		if ( ! is_array( $roles ) || empty( $roles ) ) {
			return false;
		}

		if ( ! \function_exists( 'is_user_logged_in' ) || ! \function_exists( 'wp_get_current_user' ) ) {
			return false;
		}

		if ( ! \is_user_logged_in() ) {
			return false;
		}

		$current_user = \wp_get_current_user();

		if ( empty( $current_user->roles ) || ! is_array( $current_user->roles ) ) {
			return false;
		}

		foreach ( $current_user->roles as $role ) {
			if ( \in_array( $role, $roles, true ) ) {
				return true;
			}
		}

		return false;
	}
}
