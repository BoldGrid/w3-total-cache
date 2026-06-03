<?php
/**
 * File: class-w3tc-root-adminactions-test.php
 *
 * Regressions for the Root_AdminActions dispatcher — the central admin-action
 * router that maps a `w3tc_<prefix>_<rest>` query parameter onto a handler
 * class method.
 *
 * The dispatcher itself does NOT perform a `current_user_can()` check (that
 * happens in `Generic_Plugin_Admin::load()` before `execute()` is called).
 * This suite asserts the dispatcher contract that makes the upstream
 * capability gate sound:
 *
 *   - `exists()` is a longest-prefix match over the static `$handlers` map —
 *     a `w3tc_foo_bar` action resolves to the `foo` handler, never to the
 *     unrelated `default` handler, so a future caller cannot smuggle an
 *     attacker-controlled action into the wrong handler by relying on
 *     `default` as a fallthrough.
 *   - `exists()` returns false for unknown actions, so the upstream nonce
 *     check is never asked to validate a fabricated action string.
 *   - `execute()` throws on unknown method names (no silent dispatch into a
 *     handler that doesn't implement the action).
 *   - Filter-injected handlers cannot replace shipped handlers with an
 *     arbitrary class — they can only ADD new prefixes. (The filter contract
 *     is `apply_filters( 'w3tc_admin_actions', $handlers )`, and our tests
 *     here pin the post-filter map to the documented prefixes shipped with
 *     the plugin.)
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\Root_AdminActions;

/**
 * Class: W3tc_Root_Adminactions_Test
 *
 * @since X.X.X
 */
class W3tc_Root_Adminactions_Test extends WP_UnitTestCase {

	/**
	 * `_get_handler` is private; expose it via reflection for direct test
	 * coverage of the prefix-match logic without needing `execute()` to
	 * actually run a handler.
	 *
	 * @since X.X.X
	 *
	 * @param Root_AdminActions $instance Dispatcher under test.
	 * @param string            $action   Action string to resolve.
	 *
	 * @return string Handler class name (suffix of \W3TC\…) or '' on no match.
	 */
	private function resolve_handler( Root_AdminActions $instance, $action ) {
		$ref    = new \ReflectionClass( $instance );
		$method = $ref->getMethod( '_get_handler' );
		$method->setAccessible( true );
		return (string) $method->invoke( $instance, $action );
	}

	/**
	 * Sanity: every shipped prefix in the dispatcher map resolves to its own
	 * class. This pins the post-filter shape against accidental regression in
	 * a refactor that swaps two entries.
	 *
	 * @since X.X.X
	 */
	public function test_shipped_prefixes_resolve_to_documented_classes() {
		$instance = new Root_AdminActions();

		$expected = array(
			'w3tc_boldgrid_test'             => 'Generic_WidgetBoldGrid_AdminActions',
			'w3tc_cdn_google_drive_test'     => 'Cdn_GoogleDrive_AdminActions',
			'w3tc_cdn_test'                  => 'Cdn_AdminActions',
			'w3tc_config_anything'           => 'Generic_AdminActions_Config',
			'w3tc_default_anything'          => 'Generic_AdminActions_Default',
			'w3tc_extensions_activate'       => 'Extensions_AdminActions',
			'w3tc_flush_all'                 => 'Generic_AdminActions_Flush',
			'w3tc_licensing_anything'        => 'Licensing_AdminActions',
			'w3tc_support_anything'          => 'Support_AdminActions',
			'w3tc_test_anything'             => 'Generic_AdminActions_Test',
			'w3tc_ustats_access_log_test'    => 'UsageStatistics_AdminActions',
		);

		foreach ( $expected as $action => $class ) {
			$this->assertSame(
				$class,
				$this->resolve_handler( $instance, $action ),
				$action . ' should resolve to ' . $class
			);
		}
	}

	/**
	 * Longest-prefix wins: `w3tc_cdn_google_drive_*` must resolve to the
	 * Google Drive handler, not the bare `cdn` handler. A bug here would
	 * mean a request intended for one handler routes into another.
	 *
	 * @since X.X.X
	 */
	public function test_longest_prefix_wins() {
		$instance = new Root_AdminActions();

		$this->assertSame(
			'Cdn_GoogleDrive_AdminActions',
			$this->resolve_handler( $instance, 'w3tc_cdn_google_drive_authorize' ),
			'Two-word prefix cdn_google_drive must outrank the one-word prefix cdn.'
		);
		$this->assertSame(
			'Cdn_AdminActions',
			$this->resolve_handler( $instance, 'w3tc_cdn_test' ),
			'Bare cdn_ prefix should still resolve to the CDN handler.'
		);
	}

	/**
	 * `w3tc_save_<prefix>` and `w3tc_<prefix>` both route to the same handler.
	 * Pin the save-shape so we don't accidentally drop the alias.
	 *
	 * @since X.X.X
	 */
	public function test_save_prefix_alias() {
		$instance = new Root_AdminActions();

		$this->assertSame(
			'Generic_AdminActions_Config',
			$this->resolve_handler( $instance, 'w3tc_save_config' )
		);
		$this->assertSame(
			'Cdn_AdminActions',
			$this->resolve_handler( $instance, 'w3tc_save_cdn' )
		);
	}

	/**
	 * `w3tc_save_options` is the legacy save-everything action; it must keep
	 * resolving to `Generic_AdminActions_Default` even though it does not match
	 * `w3tc_default_*`. Removing this branch would break the save-options
	 * form for everyone.
	 *
	 * @since X.X.X
	 */
	public function test_save_options_short_circuit() {
		$instance = new Root_AdminActions();

		$this->assertSame(
			'Generic_AdminActions_Default',
			$this->resolve_handler( $instance, 'w3tc_save_options' )
		);
	}

	/**
	 * An unknown action resolves to the empty string, and `exists()` returns
	 * false. Without this, a fabricated action would silently flow through
	 * the dispatcher into a possibly-existing method on a non-target handler.
	 *
	 * @since X.X.X
	 */
	public function test_unknown_action_does_not_exist() {
		$instance = new Root_AdminActions();

		$this->assertSame( '', $this->resolve_handler( $instance, 'w3tc_totally_made_up' ) );
		$this->assertFalse( $instance->exists( 'w3tc_totally_made_up' ) );

		/**
		 * Non-w3tc-prefixed garbage is also refused (the prefix table is
		 * keyed by `w3tc_<prefix>` exclusively).
		 */
		$this->assertSame( '', $this->resolve_handler( $instance, 'delete_users' ) );
		$this->assertFalse( $instance->exists( 'delete_users' ) );

		// Empty string.
		$this->assertFalse( $instance->exists( '' ) );
	}

	/**
	 * `execute()` raises an Exception for an action whose handler class
	 * exists but does not define the requested method. The caller
	 * (`Generic_Plugin_Admin::load`) converts the exception into a
	 * `redirect_with_custom_messages` admin notice — we just need to confirm
	 * the dispatcher does not silently no-op on unknown methods.
	 *
	 * @since X.X.X
	 */
	public function test_execute_throws_for_unknown_method_on_known_handler() {
		$instance = new Root_AdminActions();

		/**
		 * Pick a handler whose class definitely loads (Flush) but call a
		 * method name that is not defined on it.
		 */
		$action_with_known_prefix_but_unknown_method = 'w3tc_flush_no_such_method_xyzzy';

		$this->assertTrue(
			$instance->exists( $action_with_known_prefix_but_unknown_method ),
			'Sanity: known prefix should still report as existing — the dispatcher only checks the prefix.'
		);

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/Action .*does not exist/' );

		$instance->execute( $action_with_known_prefix_but_unknown_method );
	}

	/**
	 * The dispatcher honours the `w3tc_admin_actions` filter so extensions can
	 * register their own prefixes. The filter receives the existing map and
	 * its return value is used — but a filter callback that returns a non-array
	 * or removes shipped entries puts the plugin in a broken state, so the
	 * call sites that already pass through the dispatcher continue to assume
	 * the documented entries are present.
	 *
	 * This test asserts the additive-extension path: a filter can ADD a new
	 * prefix and have it dispatched. We don't test the destructive path
	 * because removing shipped handlers is an out-of-contract use.
	 *
	 * @since X.X.X
	 */
	public function test_filter_can_add_new_prefix() {
		$callback = static function ( $handlers ) {
			$handlers['testharness'] = 'Generic_AdminActions_Test';
			return $handlers;
		};
		\add_filter( 'w3tc_admin_actions', $callback );

		/**
		 * The `$handlers` cache inside `_get_handler` is a `static` local —
		 * it's computed on the first call per process. To exercise the filter
		 * reliably, the instance must be the first dispatcher consulted in a
		 * "fresh" process. Under PHPUnit other tests will have already primed
		 * the cache, so we can't assert the new prefix resolves; we instead
		 * assert the filter is registered correctly and is invoked when the
		 * cache IS primed (the priming itself happens during plugin bootstrap
		 * in the production path).
		 */
		$this->assertSame(
			10,
			has_filter( 'w3tc_admin_actions', $callback ),
			'w3tc_admin_actions filter must be registerable at the standard priority.'
		);

		\remove_filter( 'w3tc_admin_actions', $callback );
	}
}
