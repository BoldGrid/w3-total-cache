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
 *    Migration: HMAC names are written and accepted. Legacy MD5
 *    names are no longer accepted on the cache-auth read path
 *    unless `W3TC_COOKIE_LEGACY_NAMES_ACCEPTED` is defined true in
 *    wp-config.php. Rewrite-rule emitters always list both names so
 *    a later constant toggle reaches PHP on disk-enhanced installs
 *    without regenerating rules. The PHP writer still refuses to
 *    store a response when a leftover MD5 name is present, so the
 *    rewrite superset cannot land a rejected-role page in the
 *    shared store. Logout still clears leftover legacy cookies.
 *
 * @since 2.10.0
 */
class Util_Cookie {

	/**
	 * Whether a legacy role cookie has already been audit-logged
	 * this request.
	 *
	 * @since 2.10.6
	 *
	 * @var bool
	 */
	private static $legacy_honor_logged = false;

	/**
	 * Role slug waiting to be audit-logged after plugins load.
	 *
	 * @since 2.10.6
	 *
	 * @var string|null
	 */
	private static $legacy_honor_pending_role = null;

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
	 * Returns the legacy MD5 cookie-name suffix for a given WP role.
	 *
	 * Writers always use `role_cookie_name()`. Cache-auth readers
	 * honor this only when `legacy_role_names_accepted()` is true.
	 * Rewrite-rule emitters always list it so toggling the
	 * wp-config constant does not require regenerating rules.
	 * Logout still uses it to clear leftover pre-upgrade cookies.
	 *
	 * @since 2.10.0
	 *
	 * @param string $role WordPress role slug.
	 *
	 * @return string 32-char lowercase hex.
	 */
	public static function role_cookie_name_legacy( $role ) {
		$nonce_key = defined( 'NONCE_KEY' ) ? (string) NONCE_KEY : '';

		return \md5( $nonce_key . (string) $role );
	}

	/**
	 * Whether cache-auth readers may honor the pre-HMAC MD5
	 * role-cookie name.
	 *
	 * Default is false (fail closed). The only production switch is
	 * `W3TC_COOKIE_LEGACY_NAMES_ACCEPTED` defined true in
	 * wp-config.php. A plugin filter cannot reach the
	 * `advanced-cache.php` path and is therefore not consulted.
	 * Rewrite reject lists always include both names; this method
	 * is the live PHP read decision. The PHP writer uses
	 * `request_has_legacy_role_cookie()` and refuses leftover MD5
	 * names even when this returns false.
	 *
	 * @since 2.10.6
	 *
	 * @return bool
	 */
	public static function legacy_role_names_accepted() {
		return defined( 'W3TC_COOKIE_LEGACY_NAMES_ACCEPTED' ) && true === W3TC_COOKIE_LEGACY_NAMES_ACCEPTED;
	}

	/**
	 * Cookie names written into page-cache rewrite reject lists
	 * for a given role.
	 *
	 * Always includes the HMAC name and the leftover MD5 name.
	 * Disk-enhanced Apache/Nginx serve static files before PHP
	 * runs, so the leftover name must stay in the generated rules
	 * or a later `W3TC_COOKIE_LEGACY_NAMES_ACCEPTED` toggle cannot
	 * take effect until rules are rewritten. The PHP reader still
	 * consults `legacy_role_names_accepted()` on every request.
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
			'w3tc_logged_' . self::role_cookie_name_legacy( $role ),
		);
	}

	/**
	 * Whether a request cookie name matches a rejected role.
	 *
	 * HMAC names always match. Legacy MD5 names match only when
	 * `legacy_role_names_accepted()` is true; a match is audit-logged
	 * once per request so a site that turns the grace window on is
	 * visible in `w3tc_audit_log`.
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

		$role = (string) $role;

		if ( false !== \strstr( $cookie_name, self::role_cookie_name( $role ) ) ) {
			return true;
		}

		if ( ! self::cookie_matches_legacy_role_name( $cookie_name, $role ) ) {
			return false;
		}

		if ( ! self::legacy_role_names_accepted() ) {
			return false;
		}

		self::log_legacy_role_cookie_honored( $role );
		return true;
	}

	/**
	 * Whether a request cookie name is the leftover MD5 name for a role.
	 *
	 * Independent of `legacy_role_names_accepted()`. Used to refuse
	 * page-cache writes so a rewrite-listed leftover cookie cannot
	 * store a rejected-role response.
	 *
	 * @since 2.10.6
	 *
	 * @param string $cookie_name Raw cookie name from the request.
	 * @param string $role        WordPress role slug.
	 *
	 * @return bool
	 */
	public static function cookie_matches_legacy_role_name( $cookie_name, $role ) {
		if ( ! is_string( $cookie_name ) || 0 !== \strpos( $cookie_name, 'w3tc_logged_' ) ) {
			return false;
		}

		return false !== \strstr( $cookie_name, self::role_cookie_name_legacy( (string) $role ) );
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
	 * Whether the current request carries a leftover MD5 role cookie.
	 *
	 * True regardless of `W3TC_COOKIE_LEGACY_NAMES_ACCEPTED`. Page
	 * cache must not *write* in that case; *read* still follows
	 * `request_has_rejected_role_cookie()`.
	 *
	 * @since 2.10.6
	 *
	 * @param string[] $roles Role slugs configured for cache rejection.
	 *
	 * @return bool
	 */
	public static function request_has_legacy_role_cookie( $roles ) {
		if ( ! is_array( $roles ) || empty( $roles ) ) {
			return false;
		}

		if ( empty( $_COOKIE ) || ! is_array( $_COOKIE ) ) {
			return false;
		}

		foreach ( \array_keys( $_COOKIE ) as $cookie_name ) {
			foreach ( $roles as $role ) {
				if ( self::cookie_matches_legacy_role_name( (string) $cookie_name, (string) $role ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Audit-log that a legacy MD5 role cookie was honored.
	 *
	 * Once per request. Does not record cookie names. When this
	 * runs from `advanced-cache.php`, `w3tc_audit_log` subscribers
	 * have not registered yet, so the event is deferred until
	 * `plugins_loaded`. Honoring the cookie skips page cache, so
	 * WordPress continues and the hook fires.
	 *
	 * @since 2.10.6
	 *
	 * @param string $role WordPress role slug.
	 *
	 * @return void
	 */
	private static function log_legacy_role_cookie_honored( $role ) {
		if ( self::$legacy_honor_logged ) {
			return;
		}

		self::$legacy_honor_logged       = true;
		self::$legacy_honor_pending_role = (string) $role;

		if ( \function_exists( 'did_action' ) && \function_exists( 'add_action' ) && ! \did_action( 'plugins_loaded' ) ) {
			\add_action(
				'plugins_loaded',
				array( __CLASS__, 'flush_legacy_honor_log' ),
				\PHP_INT_MAX
			);
			return;
		}

		self::flush_legacy_honor_log();
	}

	/**
	 * Dispatch a deferred legacy-honor audit event.
	 *
	 * @since 2.10.6
	 *
	 * @internal Invoked from `plugins_loaded`. Unit tests may call directly.
	 *
	 * @return void
	 */
	public static function flush_legacy_honor_log() {
		if ( null === self::$legacy_honor_pending_role ) {
			return;
		}

		$role                            = self::$legacy_honor_pending_role;
		self::$legacy_honor_pending_role = null;

		Util_Debug::audit_log(
			'cookie.legacy_role_name',
			array(
				'role' => $role,
			)
		);
	}

	/**
	 * Resets the once-per-request legacy honor log.
	 *
	 * @since 2.10.6
	 *
	 * @internal Unit tests only.
	 *
	 * @return void
	 */
	public static function reset_legacy_honor_log() {
		self::$legacy_honor_logged       = false;
		self::$legacy_honor_pending_role = null;

		if ( \function_exists( 'remove_action' ) ) {
			\remove_action( 'plugins_loaded', array( __CLASS__, 'flush_legacy_honor_log' ), \PHP_INT_MAX );
		}
	}
}
