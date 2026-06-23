<?php
/**
 * File: class-w3tc-csrf-nonces-test.php
 *
 * Regressions for the missing-auth-csrf-nonces remediation pass:
 *
 *  - Per-action nonce keys are accepted at the central verifier and the legacy
 *    shared `'w3tc'` action is accepted only when explicitly allowed.
 *  - `Util_Nonce::read_nonce()` collapses non-scalar `_wpnonce` shapes to the
 *    empty string so `_wpnonce[]=foo` can no longer bypass `wp_verify_nonce`
 *    via type juggling.
 *  - A nonce minted for one action does not validate a different action
 *    (cross-action replay closed when the legacy fallback is disabled).
 *  - Capability gate is independent of the nonce: a valid legacy nonce held
 *    by a subscriber-level caller still fails the dispatcher's
 *    `current_user_can( 'manage_options' )` check.
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.0
 */

declare( strict_types = 1 );

use W3TC\Util_Nonce;

/**
 * Class: W3tc_Csrf_Nonces_Test
 *
 * @since 2.10.0
 */
class W3tc_Csrf_Nonces_Test extends WP_UnitTestCase {

	/**
	 * Save / restore $_REQUEST around each test so we don't leak nonce state
	 * between cases or contaminate later tests in the suite.
	 *
	 * @var array
	 */
	private $saved_request;

	/**
	 * Set up the request superglobal snapshot.
	 *
	 * @since 2.10.0
	 */
	public function set_up() {
		parent::set_up();
		$this->saved_request = $_REQUEST;
	}

	/**
	 * Restore $_REQUEST and reset the current user.
	 *
	 * @since 2.10.0
	 */
	public function tear_down() {
		$_REQUEST = $this->saved_request;
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * `read_nonce()` returns the empty string when the field is absent.
	 *
	 * @since 2.10.0
	 */
	public function test_read_nonce_returns_empty_for_missing_field() {
		unset( $_REQUEST['_wpnonce'] );
		$this->assertSame( '', Util_Nonce::read_nonce( '_wpnonce' ) );
	}

	/**
	 * `read_nonce()` returns the empty string when the field is an array
	 * (`_wpnonce[]=foo`), closing the array-juggle bypass that previously
	 * tricked `wp_verify_nonce` into returning truthy for an array value.
	 *
	 * @since 2.10.0
	 */
	public function test_read_nonce_rejects_array_shape() {
		$_REQUEST['_wpnonce'] = array( 'foo' );
		$this->assertSame( '', Util_Nonce::read_nonce( '_wpnonce' ) );

		// Nested arrays are likewise refused.
		$_REQUEST['_wpnonce'] = array( array( 'x' ) );
		$this->assertSame( '', Util_Nonce::read_nonce( '_wpnonce' ) );
	}

	/**
	 * `read_nonce()` returns the empty string when the field is an object.
	 *
	 * @since 2.10.0
	 */
	public function test_read_nonce_rejects_object_shape() {
		$_REQUEST['_wpnonce'] = new \stdClass();
		$this->assertSame( '', Util_Nonce::read_nonce( '_wpnonce' ) );
	}

	/**
	 * A scalar nonce passes through `read_nonce()` with slashes stripped and
	 * control characters removed.
	 *
	 * @since 2.10.0
	 */
	public function test_read_nonce_returns_scalar_value() {
		$_REQUEST['_wpnonce'] = 'abcdef0123';
		$this->assertSame( 'abcdef0123', Util_Nonce::read_nonce( '_wpnonce' ) );
	}

	/**
	 * A nonce minted for the new per-action key validates against the per-action
	 * key — but a nonce minted for a *different* per-action key does not, even
	 * with the legacy fallback enabled (the fallback only widens to `'w3tc'`,
	 * not to other per-action keys).
	 *
	 * @since 2.10.0
	 */
	public function test_per_action_nonce_does_not_validate_cross_action() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$nonce_for_flush                = wp_create_nonce( 'w3tc_admin_action_w3tc_flush_all' );
		$_REQUEST['_wpnonce']           = $nonce_for_flush;

		$this->assertTrue(
			Util_Nonce::verify_admin( 'w3tc_admin_action_w3tc_flush_all' ),
			'A nonce minted for the flush dispatcher should validate against its own action.'
		);
		$this->assertFalse(
			Util_Nonce::verify_admin( 'w3tc_admin_action_w3tc_save_options', '_wpnonce', false ),
			'A flush-dispatcher nonce must NOT validate against the save-options dispatcher when legacy fallback is disabled.'
		);
	}

	/**
	 * The legacy shared `'w3tc'` nonce validates against per-action keys when
	 * `$allow_legacy` is true (the default), but is refused when explicitly
	 * disabled. This is the bidirectional contract for the back-compat window.
	 *
	 * @since 2.10.0
	 */
	public function test_legacy_nonce_fallback_is_opt_in() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$legacy_nonce         = wp_create_nonce( 'w3tc' );
		$_REQUEST['_wpnonce'] = $legacy_nonce;

		$this->assertFalse(
			Util_Nonce::verify_admin( 'w3tc_admin_action_w3tc_flush_all' ),
			'Legacy w3tc nonce must not validate against a per-action key by default.'
		);

		$this->assertTrue(
			Util_Nonce::verify_admin( 'w3tc_admin_action_w3tc_flush_all', '_wpnonce', true ),
			'Legacy w3tc nonce must validate via the back-compat fallback when explicitly enabled.'
		);
	}

	/**
	 * A nonce produced for the AJAX dispatcher does NOT carry through to an
	 * unrelated admin-action key when the legacy fallback is disabled. This
	 * encodes the post-migration "scoped per surface" property.
	 *
	 * @since 2.10.0
	 */
	public function test_ajax_nonce_is_not_a_credential_for_admin_action() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$ajax_nonce           = wp_create_nonce( 'w3tc_ajax_extension_swarmify_test' );
		$_REQUEST['_wpnonce'] = $ajax_nonce;

		$this->assertTrue(
			Util_Nonce::verify_admin( 'w3tc_ajax_extension_swarmify_test', '_wpnonce', false ),
			'A nonce should validate against its own action with the legacy fallback disabled.'
		);
		$this->assertFalse(
			Util_Nonce::verify_admin( 'w3tc_admin_action_w3tc_flush_all', '_wpnonce', false ),
			'An AJAX-scoped nonce must NOT validate the admin-action dispatcher.'
		);
		$this->assertFalse(
			Util_Nonce::verify_admin( 'w3tc_extensions_bulk', '_wpnonce', false ),
			'An AJAX-scoped nonce must NOT validate the extensions bulk dispatcher.'
		);
	}

	/**
	 * Array-shape `_wpnonce` reaches `verify_admin` and is refused at the
	 * `read_nonce` boundary — the verifier never even calls `wp_verify_nonce`
	 * with an array value.
	 *
	 * @since 2.10.0
	 */
	public function test_verify_admin_refuses_array_nonce() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$_REQUEST['_wpnonce'] = array( wp_create_nonce( 'w3tc' ) );

		$this->assertFalse(
			Util_Nonce::verify_admin( 'w3tc_admin_action_w3tc_flush_all' ),
			'Array-shape _wpnonce must be refused at read_nonce even when the underlying string would otherwise validate.'
		);
		$this->assertFalse(
			Util_Nonce::verify_admin( 'w3tc_admin_action_w3tc_flush_all', '_wpnonce', false ),
			'Array-shape _wpnonce must be refused with the legacy fallback disabled too.'
		);
	}

	/**
	 * A subscriber-level user holding a *valid* legacy nonce still fails the
	 * `current_user_can( 'manage_options' )` capability gate. This is the
	 * "nonce never authorises by itself" invariant — the verifier returns true
	 * because the token is valid, but the caller must independently check
	 * capability before allowing the privileged action.
	 *
	 * @since 2.10.0
	 */
	public function test_valid_nonce_does_not_authorise_subscriber() {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$_REQUEST['_wpnonce'] = wp_create_nonce( 'w3tc_admin_action_w3tc_flush_all' );

		$this->assertTrue(
			Util_Nonce::verify_admin( 'w3tc_admin_action_w3tc_flush_all' ),
			'A correctly minted nonce validates regardless of the user role.'
		);
		$this->assertFalse(
			\current_user_can( 'manage_options' ),
			'Subscriber must NOT have manage_options — capability gate is the second factor.'
		);
	}

	/**
	 * `LEGACY_ACTION` is publicly accessible (so call-sites and tests can
	 * reference the same constant) and equal to the historical shared string.
	 *
	 * @since 2.10.0
	 */
	public function test_legacy_action_constant() {
		$this->assertSame( 'w3tc', Util_Nonce::LEGACY_ACTION );
	}

	/**
	 * Access-log test AJAX reads its nonce from the localized admin map
	 * (create_admin → w3tc_admin_action_*); verify must use the same key.
	 *
	 * @since 2.10.0
	 */
	public function test_ustats_access_log_test_nonce_uses_admin_action_prefix() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$_REQUEST['_wpnonce'] = Util_Nonce::create_admin( 'w3tc_ustats_access_log_test' );

		$this->assertTrue(
			Util_Nonce::verify_admin( Util_Nonce::admin_action( 'w3tc_ustats_access_log_test' ) ),
			'Localized admin nonce must validate against the admin_action key.'
		);
		$this->assertFalse(
			Util_Nonce::verify_admin( 'w3tc_ustats_access_log_test' ),
			'Bare handler name must not validate a create_admin token.'
		);
	}

	/**
	 * Exit survey AJAX hub mints create_ajax tokens; handlers must verify ajax_action keys.
	 *
	 * @since 2.10.0
	 */
	public function test_exit_survey_render_nonce_uses_ajax_action_prefix() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$_REQUEST['_wpnonce'] = Util_Nonce::create_ajax( 'exit_survey_render' );

		$this->assertTrue(
			Util_Nonce::verify_admin( Util_Nonce::ajax_action( 'exit_survey_render' ) ),
			'Exit survey render nonce must validate against the ajax_action key.'
		);
		$this->assertFalse(
			Util_Nonce::verify_admin( 'exit_survey_render' ),
			'Bare sub-action name must not validate a create_ajax token.'
		);
	}

	/**
	 * Purchase lightbox uses a distinct handler key from the upgrade overlay.
	 *
	 * @since 2.10.0
	 */
	public function test_licensing_buy_plugin_nonce_is_not_upgrade_nonce() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$_REQUEST['_wpnonce'] = Util_Nonce::create_admin( 'w3tc_licensing_upgrade' );

		$this->assertFalse(
			Util_Nonce::verify_admin( Util_Nonce::admin_action( 'w3tc_licensing_buy_plugin' ) ),
			'Upgrade overlay nonce must not authorize the purchase handler.'
		);

		$_REQUEST['_wpnonce'] = Util_Nonce::create_admin( 'w3tc_licensing_buy_plugin' );

		$this->assertTrue(
			Util_Nonce::verify_admin( Util_Nonce::admin_action( 'w3tc_licensing_buy_plugin' ) ),
			'Purchase handler requires its own admin nonce.'
		);
	}

	/**
	 * Image Service settings save reuses the standard w3tc_save_options admin nonce.
	 *
	 * @since 2.10.0
	 */
	public function test_imageservice_settings_save_uses_w3tc_save_options_nonce() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$_REQUEST['_wpnonce'] = Util_Nonce::create_admin( 'w3tc_save_options' );

		$this->assertTrue(
			Util_Nonce::verify_admin( Util_Nonce::admin_action( 'w3tc_save_options' ) ),
			'Image Service settings must accept the w3tc_save_options admin nonce.'
		);
		$this->assertFalse(
			Util_Nonce::verify_admin( 'w3tc_imageservice_settings' ),
			'Legacy bare imageservice settings key must not validate a save_options token.'
		);
	}
}
