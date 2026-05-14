<?php
/**
 * File: class-w3tc-util-cookie-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
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
 * @since X.X.X
 */
class W3tc_Util_Cookie_Test extends WP_UnitTestCase {

	/**
	 * The HMAC-derived role cookie name is deterministic for a given
	 * (role, salt) pair: the same call twice in the same request must
	 * return the same value, otherwise the read path and the reject-
	 * cookie list won't agree.
	 *
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
	 */
	public function test_new_and_legacy_names_differ() {
		$new    = Util_Cookie::role_cookie_name( 'administrator' );
		$legacy = Util_Cookie::role_cookie_name_legacy( 'administrator' );

		$this->assertNotSame( $new, $legacy );
	}
}
