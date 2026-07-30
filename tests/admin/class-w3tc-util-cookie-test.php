<?php
/**
 * File: class-w3tc-util-cookie-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.0
 */

declare( strict_types = 1 );

use W3TC\Util_Cookie;

/**
 * Class: W3tc_Util_Cookie_Test
 *
 * Coverage for the role-cookie name derivation. The cookie-flag
 * behaviour of `set()` / `clear()` is verified manually (Set-Cookie
 * response header inspection); here we focus on the name-derivation
 * helpers because those are the load-bearing piece of the MD5 → HMAC
 * migration.
 *
 * @since 2.10.0
 */
class W3tc_Util_Cookie_Test extends WP_UnitTestCase {

	/**
	 * The HMAC-derived role cookie name is deterministic for a given
	 * (role, salt) pair: the same call twice in the same request must
	 * return the same value, otherwise the read path and the reject-
	 * cookie list won't agree.
	 *
	 * @since 2.10.0
	 */
	public function test_role_cookie_name_is_deterministic() {
		$a = Util_Cookie::role_cookie_name( 'administrator' );
		$b = Util_Cookie::role_cookie_name( 'administrator' );

		$this->assertSame( $a, $b );
	}

	/**
	 * Different roles produce different cookie names (otherwise every
	 * role would share one bypass-cookie).
	 *
	 * @since 2.10.0
	 */
	public function test_role_cookie_name_differs_per_role() {
		$admin     = Util_Cookie::role_cookie_name( 'administrator' );
		$editor    = Util_Cookie::role_cookie_name( 'editor' );
		$author    = Util_Cookie::role_cookie_name( 'author' );

		$this->assertNotSame( $admin, $editor );
		$this->assertNotSame( $admin, $author );
		$this->assertNotSame( $editor, $author );
	}

	/**
	 * The cookie name is 32 lowercase hex chars (same length as the
	 * legacy MD5 form, so reject-cookie regexes / nginx config rules
	 * that assumed 32 chars continue to work).
	 *
	 * @since 2.10.0
	 */
	public function test_role_cookie_name_shape() {
		$name = Util_Cookie::role_cookie_name( 'administrator' );

		$this->assertSame( 32, strlen( $name ) );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{32}$/', $name );
	}

	/**
	 * The legacy MD5 form is also 32 lowercase hex chars and
	 * deterministic — used by the back-compat reader.
	 *
	 * @since 2.10.0
	 */
	public function test_role_cookie_name_legacy_shape() {
		$name = Util_Cookie::role_cookie_name_legacy( 'administrator' );

		$this->assertSame( 32, strlen( $name ) );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{32}$/', $name );
	}

	/**
	 * The new and legacy names MUST differ — otherwise the
	 * back-compat reader would be redundant.
	 *
	 * @since 2.10.0
	 */
	public function test_new_and_legacy_names_differ() {
		$new    = Util_Cookie::role_cookie_name( 'administrator' );
		$legacy = Util_Cookie::role_cookie_name_legacy( 'administrator' );

		$this->assertNotSame( $new, $legacy );
	}

	/**
	 * Regression: the role cookie name MUST derive only from the
	 * `AUTH_KEY` / `AUTH_SALT` wp-config constants, never `wp_salt()`.
	 *
	 * The name is computed in two contexts that must agree: the login
	 * WRITE path (full WP, `wp_salt()` available) and the page-cache READ
	 * path in `PgCache_ContentGrabber::_can_read_cache()`, which runs
	 * inline from `advanced-cache.php` BEFORE `wp-settings.php` loads
	 * `pluggable.php` (where `wp_salt()` is defined). A `wp_salt()`-based
	 * derivation produced a different name in the early read context, so
	 * the bypass cookie set at login was never matched and role-based
	 * page-cache exclusion silently failed.
	 *
	 * This pins the derivation to the constants present in both contexts.
	 * The true end-to-end cross-context assertion (the early read path
	 * with `wp_salt()` genuinely undefined) lives in the puppeteer spec
	 * `qa/tests/pagecache/user-roles.js`; it cannot be reproduced here
	 * because `wp_salt()` is always defined under the WP test bootstrap.
	 *
	 * @since 2.10.0
	 */
	public function test_role_cookie_name_derives_from_wpconfig_constants_only() {
		$salt     = ( defined( 'AUTH_KEY' ) ? (string) AUTH_KEY : '' )
			. ( defined( 'AUTH_SALT' ) ? (string) AUTH_SALT : '' );
		$expected = substr( hash_hmac( 'sha256', 'administrator', $salt ), 0, 32 );

		$this->assertSame( $expected, Util_Cookie::role_cookie_name( 'administrator' ) );
	}
}
