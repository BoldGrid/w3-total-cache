<?php
/**
 * File: class-w3tc-util-cookie-legacy-policy-test.php
 *
 * Cache-auth readers reject legacy MD5 role-cookie names by default.
 * HMAC names still reject cache. Rewrite emitters always list both
 * names so a later wp-config constant toggle reaches PHP on
 * disk-enhanced installs. Writes still refuse leftover MD5 names.
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
		Util_Cookie::reset_legacy_honor_log();
		\remove_all_filters( 'w3tc_cookie_legacy_names_accepted' );
	}

	/**
	 * Tear down.
	 *
	 * @since 2.10.6
	 */
	public function tear_down() {
		\remove_all_filters( 'w3tc_cookie_legacy_names_accepted' );
		\remove_all_actions( 'w3tc_audit_log' );
		Util_Cookie::reset_legacy_honor_log();
		$_COOKIE = $this->saved_cookie;
		parent::tear_down();
	}

	/**
	 * Rewrite emitters always list both names. The request reader
	 * honors leftover names only when the wp-config constant is on.
	 * The write helper sees leftover names even when the constant is off.
	 *
	 * @since 2.10.6
	 *
	 * @param string $role WordPress role slug.
	 *
	 * @return void
	 */
	private function assert_rewrite_lists_both_reader_follows_constant( $role ) {
		$hmac   = 'w3tc_logged_' . Util_Cookie::role_cookie_name( $role );
		$legacy = 'w3tc_logged_' . Util_Cookie::role_cookie_name_legacy( $role );
		$names  = Util_Cookie::role_cookie_reject_names( $role );

		$this->assertContains( $hmac, $names );
		$this->assertContains( $legacy, $names );

		$_COOKIE = array( $legacy => '1' );
		$this->assertSame(
			Util_Cookie::legacy_role_names_accepted(),
			Util_Cookie::request_has_rejected_role_cookie( array( $role ) )
		);
		$this->assertTrue( Util_Cookie::request_has_legacy_role_cookie( array( $role ) ) );
	}

	/**
	 * Default policy is fail-closed: legacy names are not accepted.
	 *
	 * @since 2.10.6
	 */
	public function test_legacy_names_rejected_by_default() {
		$this->assertFalse( Util_Cookie::legacy_role_names_accepted() );
	}

	/**
	 * A plugin-time filter is not a production switch.
	 *
	 * @since 2.10.6
	 */
	public function test_plugin_time_filter_does_not_opt_in() {
		\add_filter( 'w3tc_cookie_legacy_names_accepted', '__return_true' );

		$this->assertFalse( Util_Cookie::legacy_role_names_accepted() );
		$this->assert_rewrite_lists_both_reader_follows_constant( 'editor' );
	}

	/**
	 * Non-boolean filter returns also stay rejected.
	 *
	 * @since 2.10.6
	 */
	public function test_legacy_names_filter_ignored_on_non_bool() {
		\add_filter(
			'w3tc_cookie_legacy_names_accepted',
			static function () {
				return 'yes';
			}
		);

		$this->assertFalse( Util_Cookie::legacy_role_names_accepted() );
	}

	/**
	 * Filter registered at plugin time does not change the
	 * advanced-cache decision. Rewrite lists still include both
	 * names; the reader stays fail-closed.
	 *
	 * @since 2.10.6
	 */
	public function test_plugin_time_filter_does_not_change_early_bootstrap_policy() {
		global $wp_actions;

		$saved_plugins_loaded = isset( $wp_actions['plugins_loaded'] ) ? $wp_actions['plugins_loaded'] : null;
		unset( $wp_actions['plugins_loaded'] );

		try {
			\add_filter( 'w3tc_cookie_legacy_names_accepted', '__return_true' );

			$this->assertFalse( Util_Cookie::legacy_role_names_accepted() );
			$this->assert_rewrite_lists_both_reader_follows_constant( 'editor' );
		} finally {
			if ( null !== $saved_plugins_loaded ) {
				$wp_actions['plugins_loaded'] = $saved_plugins_loaded;
			}
		}
	}

	/**
	 * Current HMAC cookie names still reject cache regardless of the
	 * retired filter.
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
	 * HMAC cookies are not leftover MD5 names, so they do not trip
	 * the write-side leftover helper.
	 *
	 * @since 2.10.6
	 */
	public function test_hmac_cookie_does_not_count_as_legacy_for_writes() {
		$cookie = 'w3tc_logged_' . Util_Cookie::role_cookie_name( 'administrator' );

		$_COOKIE[ $cookie ] = '1';
		$this->assertTrue( Util_Cookie::request_has_rejected_role_cookie( array( 'administrator' ) ) );
		$this->assertFalse( Util_Cookie::request_has_legacy_role_cookie( array( 'administrator' ) ) );
	}

	/**
	 * Legacy MD5 cookie names do not reject cache by default.
	 *
	 * @since 2.10.6
	 */
	public function test_legacy_cookie_does_not_reject_cache_by_default() {
		$cookie = 'w3tc_logged_' . Util_Cookie::role_cookie_name_legacy( 'administrator' );

		$this->assertFalse( Util_Cookie::cookie_matches_rejected_role( $cookie, 'administrator' ) );

		$_COOKIE[ $cookie ] = '1';
		$this->assertFalse( Util_Cookie::request_has_rejected_role_cookie( array( 'administrator' ) ) );
		$this->assertTrue( Util_Cookie::request_has_legacy_role_cookie( array( 'administrator' ) ) );
	}

	/**
	 * Legacy MD5 cookie names reject cache when the wp-config
	 * constant is true. Isolated so the constant cannot leak.
	 *
	 * @since 2.10.6
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_legacy_cookie_rejects_cache_when_constant_true() {
		if ( defined( 'W3TC_COOKIE_LEGACY_NAMES_ACCEPTED' ) ) {
			$this->markTestSkipped( 'W3TC_COOKIE_LEGACY_NAMES_ACCEPTED already defined' );
		}

		define( 'W3TC_COOKIE_LEGACY_NAMES_ACCEPTED', true );

		$this->assertTrue( Util_Cookie::legacy_role_names_accepted() );
		$this->assert_rewrite_lists_both_reader_follows_constant( 'administrator' );

		$cookie = 'w3tc_logged_' . Util_Cookie::role_cookie_name_legacy( 'administrator' );
		$this->assertTrue( Util_Cookie::cookie_matches_rejected_role( $cookie, 'administrator' ) );

		$_COOKIE[ $cookie ] = '1';
		$this->assertTrue( Util_Cookie::request_has_rejected_role_cookie( array( 'administrator' ) ) );
	}

	/**
	 * Mixed current + leftover legacy: HMAC still rejects cache.
	 *
	 * @since 2.10.6
	 */
	public function test_mixed_hmac_and_legacy_hmac_still_rejects() {
		$hmac   = 'w3tc_logged_' . Util_Cookie::role_cookie_name( 'administrator' );
		$legacy = 'w3tc_logged_' . Util_Cookie::role_cookie_name_legacy( 'administrator' );

		$_COOKIE[ $hmac ]   = '1';
		$_COOKIE[ $legacy ] = '1';

		$this->assertTrue( Util_Cookie::request_has_rejected_role_cookie( array( 'administrator' ) ) );
		$this->assertTrue( Util_Cookie::request_has_legacy_role_cookie( array( 'administrator' ) ) );
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
		$this->assertFalse( Util_Cookie::request_has_legacy_role_cookie( array( 'administrator' ) ) );
	}

	/**
	 * Rewrite emitters always list both names. The request reader
	 * still ignores leftover names when the constant is off.
	 *
	 * @since 2.10.6
	 */
	public function test_reject_names_include_legacy_for_rewrite_rules() {
		$names  = Util_Cookie::role_cookie_reject_names( 'editor' );
		$hmac   = 'w3tc_logged_' . Util_Cookie::role_cookie_name( 'editor' );
		$legacy = 'w3tc_logged_' . Util_Cookie::role_cookie_name_legacy( 'editor' );

		$this->assertSame( array( $hmac, $legacy ), $names );
		$this->assertFalse( Util_Cookie::legacy_role_names_accepted() );
		$this->assert_rewrite_lists_both_reader_follows_constant( 'editor' );
	}

	/**
	 * Honoring a legacy name fires the audit log once.
	 *
	 * @since 2.10.6
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_legacy_honor_emits_audit_log() {
		if ( defined( 'W3TC_COOKIE_LEGACY_NAMES_ACCEPTED' ) ) {
			$this->markTestSkipped( 'W3TC_COOKIE_LEGACY_NAMES_ACCEPTED already defined' );
		}

		define( 'W3TC_COOKIE_LEGACY_NAMES_ACCEPTED', true );

		$events = array();
		\add_action(
			'w3tc_audit_log',
			static function ( $event, $context ) use ( &$events ) {
				$events[] = array( $event, $context );
			},
			10,
			2
		);

		$cookie = 'w3tc_logged_' . Util_Cookie::role_cookie_name_legacy( 'author' );
		Util_Cookie::cookie_matches_rejected_role( $cookie, 'author' );
		Util_Cookie::cookie_matches_rejected_role( $cookie, 'author' );

		$this->assertCount( 1, $events );
		$this->assertSame( 'cookie.legacy_role_name', $events[0][0] );
		$this->assertSame( 'author', $events[0][1]['role'] );
	}

	/**
	 * On the advanced-cache path (`plugins_loaded` has not run), the
	 * honor event is held until subscribers can register.
	 *
	 * @since 2.10.6
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_legacy_honor_audit_defers_until_plugins_loaded() {
		if ( defined( 'W3TC_COOKIE_LEGACY_NAMES_ACCEPTED' ) ) {
			$this->markTestSkipped( 'W3TC_COOKIE_LEGACY_NAMES_ACCEPTED already defined' );
		}

		define( 'W3TC_COOKIE_LEGACY_NAMES_ACCEPTED', true );

		global $wp_actions;

		$saved_plugins_loaded = isset( $wp_actions['plugins_loaded'] ) ? $wp_actions['plugins_loaded'] : null;
		unset( $wp_actions['plugins_loaded'] );

		try {
			$events = array();
			\add_action(
				'w3tc_audit_log',
				static function ( $event, $context ) use ( &$events ) {
					$events[] = array( $event, $context );
				},
				10,
				2
			);

			$cookie = 'w3tc_logged_' . Util_Cookie::role_cookie_name_legacy( 'author' );
			Util_Cookie::cookie_matches_rejected_role( $cookie, 'author' );

			$this->assertCount( 0, $events );
			$this->assertSame(
				\PHP_INT_MAX,
				\has_action( 'plugins_loaded', array( Util_Cookie::class, 'flush_legacy_honor_log' ) )
			);

			Util_Cookie::flush_legacy_honor_log();

			$this->assertCount( 1, $events );
			$this->assertSame( 'cookie.legacy_role_name', $events[0][0] );
			$this->assertSame( 'author', $events[0][1]['role'] );
		} finally {
			if ( null !== $saved_plugins_loaded ) {
				$wp_actions['plugins_loaded'] = $saved_plugins_loaded;
			}
		}
	}

	/**
	 * Cache-auth readers and rewrite emitters must not call the legacy
	 * helper directly; logout still clears leftover names. The policy
	 * helper must not consult a plugin filter. Rewrite lists both
	 * names; writes refuse leftover MD5 via a dedicated helper.
	 *
	 * @since 2.10.6
	 */
	public function test_leftover_class_readers_go_through_policy_helper() {
		$grabber     = file_get_contents( W3TC_DIR . '/PgCache_ContentGrabber.php' );
		$environment = file_get_contents( W3TC_DIR . '/PgCache_Environment.php' );
		$generic     = file_get_contents( W3TC_DIR . '/Generic_Plugin.php' );
		$cookie      = file_get_contents( W3TC_DIR . '/Util_Cookie.php' );

		$this->assertStringNotContainsString( 'role_cookie_name_legacy', $grabber );
		$this->assertStringNotContainsString( 'role_cookie_name_legacy', $environment );
		$this->assertStringContainsString( 'request_has_rejected_role_cookie', $grabber );
		$this->assertStringContainsString( 'request_has_legacy_role_cookie', $grabber );
		$this->assertStringContainsString( 'role_cookie_reject_names', $environment );
		$this->assertStringContainsString( 'role_cookie_name_legacy', $generic );
		$this->assertIsString( $cookie );
		$this->assertStringContainsString( "'plugins_loaded'", $cookie );
		$this->assertStringContainsString( 'flush_legacy_honor_log', $cookie );
		$this->assertStringContainsString( 'W3TC_COOKIE_LEGACY_NAMES_ACCEPTED', $cookie );
		$this->assertDoesNotMatchRegularExpression(
			'/apply_filters\s*\(\s*[\'"]w3tc_cookie_legacy_names_accepted[\'"]/',
			$cookie
		);
		$this->assertDoesNotMatchRegularExpression(
			'/function role_cookie_reject_names.*?if\s*\(\s*self::legacy_role_names_accepted/s',
			$cookie
		);
		$this->assertStringNotContainsString( 'w3tc_cookie_legacy_names_accepted', $grabber );
		$this->assertStringNotContainsString( 'w3tc_cookie_legacy_names_accepted', $environment );
		$this->assertStringNotContainsString( 'w3tc_cookie_legacy_names_accepted', $generic );
	}
}
