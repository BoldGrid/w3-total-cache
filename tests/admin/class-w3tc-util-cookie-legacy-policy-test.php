<?php
/**
 * File: class-w3tc-util-cookie-legacy-policy-test.php
 *
 * Cache-auth readers and rewrite reject lists honor HMAC role-cookie
 * names only. Pre-HMAC leftover names do not reject cache.
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.6
 */

declare( strict_types = 1 );

use W3TC\Util_Cookie;

/**
 * Class: W3tc_Util_Cookie_Legacy_Policy_Test
 *
 * @since 2.10.6
 */
class W3tc_Util_Cookie_Legacy_Policy_Test extends WP_UnitTestCase {

	/**
	 * Backup of $_COOKIE.
	 *
	 * @var array
	 */
	private $saved_cookie;

	/**
	 * Set up.
	 *
	 * @since 2.10.6
	 */
	public function set_up() {
		parent::set_up();
		$this->saved_cookie = $_COOKIE;
		$_COOKIE            = array();
	}

	/**
	 * Tear down.
	 *
	 * @since 2.10.6
	 */
	public function tear_down() {
		$_COOKIE = $this->saved_cookie;
		parent::tear_down();
	}

	/**
	 * Pre-HMAC leftover name for a role (inline derivation so tests
	 * do not keep a production helper).
	 *
	 * @since X.X.X
	 *
	 * @param string $role WordPress role slug.
	 *
	 * @return string
	 */
	private function leftover_role_cookie_name( $role ) {
		$nonce_key = defined( 'NONCE_KEY' ) ? (string) NONCE_KEY : '';

		return 'w3tc_logged_' . md5( $nonce_key . (string) $role );
	}

	/**
	 * Current HMAC cookie names still reject cache.
	 *
	 * @since 2.10.6
	 */
	public function test_hmac_cookie_still_rejects_cache() {
		$cookie = 'w3tc_logged_' . Util_Cookie::role_cookie_name( 'administrator' );

		$this->assertTrue( Util_Cookie::cookie_matches_rejected_role( $cookie, 'administrator' ) );

		$_COOKIE[ $cookie ] = '1';
		$this->assertTrue( Util_Cookie::request_has_rejected_role_cookie( array( 'administrator' ) ) );
	}

	/**
	 * Pre-HMAC leftover names do not reject cache.
	 *
	 * @since 2.10.6
	 */
	public function test_leftover_cookie_does_not_reject_cache() {
		$cookie = $this->leftover_role_cookie_name( 'administrator' );

		$this->assertFalse( Util_Cookie::cookie_matches_rejected_role( $cookie, 'administrator' ) );

		$_COOKIE[ $cookie ] = '1';
		$this->assertFalse( Util_Cookie::request_has_rejected_role_cookie( array( 'administrator' ) ) );
	}

	/**
	 * Mixed current + leftover: HMAC still rejects cache.
	 *
	 * @since 2.10.6
	 */
	public function test_mixed_hmac_and_leftover_hmac_still_rejects() {
		$hmac     = 'w3tc_logged_' . Util_Cookie::role_cookie_name( 'administrator' );
		$leftover = $this->leftover_role_cookie_name( 'administrator' );

		$_COOKIE[ $hmac ]     = '1';
		$_COOKIE[ $leftover ] = '1';

		$this->assertTrue( Util_Cookie::request_has_rejected_role_cookie( array( 'administrator' ) ) );
	}

	/**
	 * Malformed and unsupported names never grant a cache bypass.
	 *
	 * @since 2.10.6
	 */
	public function test_malformed_and_unsupported_cookies_are_rejected() {
		$names = array(
			'w3tc_logged_',
			'w3tc_logged_not-a-hash',
			'w3tc_logged_' . str_repeat( 'z', 32 ),
			'wordpress_logged_in',
			'w3tc_preview',
		);

		foreach ( $names as $name ) {
			$this->assertFalse(
				Util_Cookie::cookie_matches_rejected_role( $name, 'administrator' ),
				$name
			);
			$_COOKIE[ $name ] = '1';
		}

		$this->assertFalse( Util_Cookie::request_has_rejected_role_cookie( array( 'administrator' ) ) );
	}

	/**
	 * Rewrite emitters list the HMAC name only.
	 *
	 * @since 2.10.6
	 */
	public function test_reject_names_are_hmac_only() {
		$names    = Util_Cookie::role_cookie_reject_names( 'editor' );
		$hmac     = 'w3tc_logged_' . Util_Cookie::role_cookie_name( 'editor' );
		$leftover = $this->leftover_role_cookie_name( 'editor' );

		$this->assertSame( array( $hmac ), $names );
		$this->assertNotContains( $leftover, $names );
	}

	/**
	 * Plugin PHP must not keep the retired helper, grace constant,
	 * leftover write helper, or honor audit log.
	 *
	 * @since 2.10.6
	 */
	public function test_leftover_class_retired_from_plugin_php() {
		$plugin_files = array(
			W3TC_DIR . '/Util_Cookie.php',
			W3TC_DIR . '/PgCache_ContentGrabber.php',
			W3TC_DIR . '/PgCache_Environment.php',
			W3TC_DIR . '/Generic_Plugin.php',
		);

		foreach ( $plugin_files as $path ) {
			$contents = file_get_contents( $path );
			$this->assertIsString( $contents, $path );
			$this->assertStringNotContainsString( 'role_cookie_name_legacy', $contents, $path );
			$this->assertStringNotContainsString( 'legacy_role_names_accepted', $contents, $path );
			$this->assertStringNotContainsString( 'W3TC_COOKIE_LEGACY_NAMES_ACCEPTED', $contents, $path );
			$this->assertStringNotContainsString( 'request_has_legacy_role_cookie', $contents, $path );
			$this->assertStringNotContainsString( 'cookie_matches_legacy_role_name', $contents, $path );
			$this->assertStringNotContainsString( 'flush_legacy_honor_log', $contents, $path );
			$this->assertStringNotContainsString( 'cookie.legacy_role_name', $contents, $path );
			$this->assertStringNotContainsString( 'w3tc_cookie_legacy_names_accepted', $contents, $path );
		}

		$grabber     = file_get_contents( W3TC_DIR . '/PgCache_ContentGrabber.php' );
		$environment = file_get_contents( W3TC_DIR . '/PgCache_Environment.php' );

		$this->assertStringContainsString( 'request_has_rejected_role_cookie', $grabber );
		$this->assertStringContainsString( 'current_user_has_rejected_role', $grabber );
		$this->assertStringContainsString( 'role_cookie_reject_names', $environment );
	}

	/**
	 * Write-path helper uses the loaded WordPress user, not leftover cookies.
	 *
	 * @since X.X.X
	 */
	public function test_current_user_rejected_role_uses_wp_user() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$sub_id   = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		wp_set_current_user( 0 );
		$this->assertFalse( Util_Cookie::current_user_has_rejected_role( array( 'administrator' ) ) );

		wp_set_current_user( $admin_id );
		$this->assertTrue( Util_Cookie::current_user_has_rejected_role( array( 'administrator' ) ) );
		$this->assertFalse( Util_Cookie::current_user_has_rejected_role( array( 'editor' ) ) );

		wp_set_current_user( $sub_id );
		$this->assertFalse( Util_Cookie::current_user_has_rejected_role( array( 'administrator' ) ) );
	}
}
