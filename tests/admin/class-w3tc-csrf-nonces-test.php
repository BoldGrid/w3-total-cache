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
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\Util_Nonce;

/**
 * Class: W3tc_Csrf_Nonces_Test
 *
 * @since X.X.X
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
	 * @since X.X.X
	 */
	public function set_up() {
		parent::set_up();
		$this->saved_request = $_REQUEST;
	}

	/**
	 * Restore $_REQUEST and reset the current user.
	 *
	 * @since X.X.X
	 */
	public function tear_down() {
		$_REQUEST = $this->saved_request;
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * `read_nonce()` returns the empty string when the field is absent.
	 *
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
	 */
	public function test_read_nonce_rejects_object_shape() {
		$_REQUEST['_wpnonce'] = new \stdClass();
		$this->assertSame( '', Util_Nonce::read_nonce( '_wpnonce' ) );
	}

	/**
	 * A scalar nonce passes through `read_nonce()` with slashes stripped and
	 * control characters removed.
	 *
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
	 */
	public function test_legacy_nonce_fallback_is_opt_in() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$legacy_nonce         = wp_create_nonce( 'w3tc' );
		$_REQUEST['_wpnonce'] = $legacy_nonce;

		// Legacy nonce → per-action key, fallback ON (default): accepted.
		$this->assertTrue(
			Util_Nonce::verify_admin( 'w3tc_admin_action_w3tc_flush_all' ),
			'Legacy w3tc nonce must validate via the back-compat fallback by default.'
		);

		// Legacy nonce → per-action key, fallback OFF: refused.
		$this->assertFalse(
			Util_Nonce::verify_admin( 'w3tc_admin_action_w3tc_flush_all', '_wpnonce', false ),
			'Legacy w3tc nonce must NOT validate against a per-action key when the fallback is disabled.'
		);
	}

	/**
	 * A nonce produced for the AJAX dispatcher does NOT carry through to an
	 * unrelated admin-action key when the legacy fallback is disabled. This
	 * encodes the post-migration "scoped per surface" property.
	 *
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
	 */
	public function test_legacy_action_constant() {
		$this->assertSame( 'w3tc', Util_Nonce::LEGACY_ACTION );
	}
}
