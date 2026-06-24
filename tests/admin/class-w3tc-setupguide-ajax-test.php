<?php
/**
 * File: class-w3tc-setupguide-ajax-test.php
 *
 * Regressions for the missing-auth-setupguide-ajax remediation pass.
 *
 * Historically `SetupGuide_Plugin_Admin` registered its `wp_ajax_w3tc_*`
 * handlers for every logged-in user (subscriber+), because the constructor
 * registered `set_template` on `init` whenever the request looked like a
 * `w3tc_*` AJAX call — there was no capability gate on the registration
 * itself, and the per-handler check was nonce-only.
 *
 * The remediation enforces three layers, all of which we exercise here:
 *
 *   1. `set_template()` short-circuits for callers without `manage_options`,
 *      so non-admins never get the wizard template instantiated.
 *   2. `load()` (the wizard page itself) explicitly `wp_die`s for non-admins.
 *   3. Every AJAX handler goes through the private `verify_ajax_request()`
 *      helper, which checks `manage_options` BEFORE the nonce — so a
 *      subscriber with a leaked / leakable nonce cannot reach a config write.
 *
 * Also: the per-action nonce map exists and covers all 15 wizard surfaces, so
 * a nonce minted for one wizard step cannot be replayed against a different
 * step (cross-action replay closed).
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.0
 */

declare( strict_types = 1 );

use W3TC\SetupGuide_Plugin_Admin;

/**
 * Class: W3tc_Setupguide_Ajax_Test
 *
 * @since 2.10.0
 */
class W3tc_Setupguide_Ajax_Test extends WP_UnitTestCase {

	/**
	 * Backup of $_REQUEST around tests.
	 *
	 * @var array
	 */
	private $saved_request;

	/**
	 * Custom AJAX die handler that throws WPDieException instead of exiting.
	 *
	 * The WP test bootstrap only installs a throwing handler for the
	 * non-AJAX `wp_die_handler` filter. AJAX requests route through
	 * `wp_die_ajax_handler`, which has no override — under PHPUnit that
	 * means `wp_send_json_error` echoes JSON and calls plain `exit()`,
	 * terminating the test runner. Installing our own throwing handler
	 * matches the WP_Ajax_UnitTestCase pattern without dragging in the
	 * rest of its setup.
	 *
	 * @since 2.10.0
	 *
	 * @return callable
	 */
	public static function ajax_die_handler() {
		return static function ( $message = '' ) {
			throw new \WPDieException( (string) $message );
		};
	}

	/**
	 * Set up.
	 *
	 * Forces `wp_doing_ajax()` to true AND installs a throwing AJAX die
	 * handler so `wp_send_json_error` raises WPDieException instead of
	 * exiting the test runner.
	 *
	 * @since 2.10.0
	 */
	public function set_up() {
		parent::set_up();
		$this->saved_request = $_REQUEST;
		\add_filter( 'wp_doing_ajax', '__return_true' );
		\add_filter( 'wp_die_ajax_handler', array( __CLASS__, 'ajax_die_handler' ) );
	}

	/**
	 * Reset request state and current user.
	 *
	 * @since 2.10.0
	 */
	public function tear_down() {
		\remove_filter( 'wp_die_ajax_handler', array( __CLASS__, 'ajax_die_handler' ) );
		\remove_filter( 'wp_doing_ajax', '__return_true' );
		$_REQUEST = $this->saved_request;
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Helper: read the private `$template` static property via reflection so we
	 * can assert that `set_template()` did or didn't instantiate it.
	 *
	 * @since 2.10.0
	 *
	 * @return mixed Current value of `SetupGuide_Plugin_Admin::$template`.
	 */
	private function get_template_static() {
		$ref  = new \ReflectionClass( SetupGuide_Plugin_Admin::class );
		$prop = $ref->getProperty( 'template' );
		$prop->setAccessible( true );
		return $prop->getValue();
	}

	/**
	 * Helper: clear the private `$template` static so each test starts clean.
	 *
	 * @since 2.10.0
	 */
	private function clear_template_static() {
		$ref  = new \ReflectionClass( SetupGuide_Plugin_Admin::class );
		$prop = $ref->getProperty( 'template' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
	}

	/**
	 * Helper: read the private `$nonce_actions` static via reflection.
	 *
	 * @since 2.10.0
	 *
	 * @return array
	 */
	private function get_nonce_actions_map() {
		$ref  = new \ReflectionClass( SetupGuide_Plugin_Admin::class );
		$prop = $ref->getProperty( 'nonce_actions' );
		$prop->setAccessible( true );
		return (array) $prop->getValue();
	}

	/**
	 * `set_template()` short-circuits for a non-admin caller — the wizard
	 * template is never instantiated, so subsequent AJAX hooks that the
	 * template would have registered are not present.
	 *
	 * @since 2.10.0
	 */
	public function test_set_template_short_circuits_for_subscriber() {
		$this->clear_template_static();

		$subscriber = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		/**
		 * Load the wizard template file so the class is available — this
		 * mirrors the constructor's `require_once`, but without taking the
		 * privileged path through the constructor (which we test elsewhere).
		 */
		require_once W3TC_DIR . '/inc/wizard/template.php';

		$plugin = new SetupGuide_Plugin_Admin();
		$plugin->set_template();

		$this->assertNull(
			$this->get_template_static(),
			'set_template() must NOT instantiate the wizard template for a non-admin caller.'
		);
	}

	/**
	 * `set_template()` instantiates the wizard template for an admin caller.
	 *
	 * @since 2.10.0
	 */
	public function test_set_template_runs_for_admin() {
		$this->clear_template_static();

		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		require_once W3TC_DIR . '/inc/wizard/template.php';

		$plugin = new SetupGuide_Plugin_Admin();
		$plugin->set_template();

		$this->assertNotNull(
			$this->get_template_static(),
			'set_template() must instantiate the wizard template for an admin caller.'
		);
		$this->assertInstanceOf(
			\W3TC\Wizard\Template::class,
			$this->get_template_static()
		);

		$this->clear_template_static();
	}

	/**
	 * `load()` (the wizard page entrypoint) dies with a 403-shaped message
	 * for a non-admin caller, even if the static template is somehow set.
	 *
	 * @since 2.10.0
	 */
	public function test_load_dies_for_subscriber() {
		$subscriber = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		$plugin = new SetupGuide_Plugin_Admin();

		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'You do not have sufficient permissions' );

		$plugin->load();
	}

	/**
	 * The AJAX `skip` handler refuses subscriber callers regardless of nonce,
	 * because `verify_ajax_request()` checks capability before the nonce.
	 *
	 * @since 2.10.0
	 */
	public function test_skip_handler_refuses_subscriber() {
		$subscriber = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		/**
		 * Mint a valid nonce so the capability gate (not the nonce gate) is
		 * the only thing that can fail.
		 */
		$_REQUEST['_wpnonce'] = \wp_create_nonce( 'w3tc_wizard_skip' );

		$plugin = new SetupGuide_Plugin_Admin();

		$this->expectException( \WPDieException::class );
		$plugin->skip();
	}

	/**
	 * The AJAX `set_tos_choice` handler refuses subscriber callers — the TOS
	 * choice writes to `config_state_master` and toggles `common.track_usage`
	 * in the global config, so subscriber reach would be a config-write
	 * privilege escalation.
	 *
	 * @since 2.10.0
	 */
	public function test_set_tos_choice_refuses_subscriber() {
		$subscriber = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		$_REQUEST['_wpnonce'] = \wp_create_nonce( 'w3tc_wizard_tos_choice' );
		$_REQUEST['choice']   = 'accept';

		$plugin = new SetupGuide_Plugin_Admin();

		$this->expectException( \WPDieException::class );
		$plugin->set_tos_choice();
	}

	/**
	 * The capability gate refuses a subscriber even when no nonce is provided.
	 * (Capability check is BEFORE the nonce check; the nonce isn't even
	 * inspected before the capability gate fails.)
	 *
	 * @since 2.10.0
	 */
	public function test_skip_handler_refuses_subscriber_without_nonce() {
		$subscriber = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );
		unset( $_REQUEST['_wpnonce'] );

		$plugin = new SetupGuide_Plugin_Admin();

		$this->expectException( \WPDieException::class );
		$plugin->skip();
	}

	/**
	 * The per-action nonce map covers every wizard surface — no shared
	 * `w3tc_wizard` key remains as the canonical action for any handler.
	 * Each value must be distinct so a leak of one nonce cannot replay
	 * against a different surface.
	 *
	 * @since 2.10.0
	 */
	public function test_per_action_nonce_map_covers_all_surfaces() {
		$map = $this->get_nonce_actions_map();

		$expected_actions = array(
			'w3tc_wizard_skip',
			'w3tc_tos_choice',
			'w3tc_get_pgcache_settings',
			'w3tc_test_pgcache',
			'w3tc_config_pgcache',
			'w3tc_get_dbcache_settings',
			'w3tc_test_dbcache',
			'w3tc_config_dbcache',
			'w3tc_get_objcache_settings',
			'w3tc_test_objcache',
			'w3tc_config_objcache',
			'w3tc_get_imageservice_settings',
			'w3tc_config_imageservice',
			'w3tc_get_lazyload_settings',
			'w3tc_config_lazyload',
		);

		foreach ( $expected_actions as $action ) {
			$this->assertArrayHasKey(
				$action,
				$map,
				'SetupGuide nonce map missing entry for ' . $action
			);
		}

		/**
		 * Every value must be distinct so a leaked nonce minted for one wizard
		 * surface cannot validate a different surface.
		 */
		$values = array_values( $map );
		$this->assertCount(
			count( $values ),
			array_unique( $values ),
			'SetupGuide per-action nonce values must be distinct — cross-action replay is closed only when each handler has its own action key.'
		);

		// And nothing in the map is still the legacy shared key.
		foreach ( $map as $action => $nonce_action ) {
			$this->assertNotSame(
				'w3tc_wizard',
				$nonce_action,
				$action . ' must not still be using the legacy shared w3tc_wizard nonce action.'
			);
		}
	}

	/**
	 * `get_nonce_action()` falls back to `w3tc_wizard` for an unknown action so
	 * cached wizard pages (rendered before this release) continue to function
	 * for admins. Closing the legacy fallback is a follow-up release.
	 *
	 * @since 2.10.0
	 */
	public function test_get_nonce_action_falls_back_to_legacy_for_unknown() {
		$ref    = new \ReflectionClass( SetupGuide_Plugin_Admin::class );
		$method = $ref->getMethod( 'get_nonce_action' );
		$method->setAccessible( true );

		$this->assertSame(
			'w3tc_wizard_skip',
			$method->invoke( null, 'w3tc_wizard_skip' ),
			'Known action should resolve to its per-action nonce key.'
		);
		$this->assertSame(
			'w3tc_wizard',
			$method->invoke( null, 'this_is_not_a_wizard_action' ),
			'Unknown action should fall back to the legacy w3tc_wizard key (back-compat window).'
		);
	}
}
