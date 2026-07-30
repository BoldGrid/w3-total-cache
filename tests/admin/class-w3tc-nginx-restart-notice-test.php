<?php
/**
 * File: class-w3tc-nginx-restart-notice-test.php
 *
 * Regressions for the nginx restart admin notice dismiss flow.
 *
 * Dismiss must set a persistent `hide_note` flag. Clearing only
 * `show_note.nginx_restart_required` is not enough because
 * environment rule writers re-assert the show flag whenever
 * nginx.conf is touched during an admin request.
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.0
 */

declare( strict_types = 1 );

use W3TC\ConfigKeysSchema;
use W3TC\Dispatcher;
use W3TC\Generic_AdminActions_Default;
use W3TC\Generic_AdminNotes;
use W3TC\Util_Environment_Exceptions;
use W3TC\Util_Nonce;
use W3TC\Util_Rule;

/**
 * Class: W3tc_Nginx_Restart_Notice_Test
 *
 * @since 2.10.0
 */
class W3tc_Nginx_Restart_Notice_Test extends WP_UnitTestCase {

	/**
	 * Saved $_REQUEST snapshot.
	 *
	 * @var array
	 */
	private $saved_request;

	/**
	 * Saved $_SERVER['SERVER_SOFTWARE'] snapshot.
	 *
	 * @var string|null
	 */
	private $saved_server_software;

	/**
	 * Set up request/server snapshots.
	 *
	 * @since 2.10.0
	 */
	public function set_up() {
		parent::set_up();
		$this->saved_request           = $_REQUEST;
		$this->saved_server_software   = $_SERVER['SERVER_SOFTWARE'] ?? null;
	}

	/**
	 * Restore request/server snapshots.
	 *
	 * @since 2.10.0
	 */
	public function tear_down() {
		$_REQUEST = $this->saved_request;
		if ( null === $this->saved_server_software ) {
			unset( $_SERVER['SERVER_SOFTWARE'] );
		} else {
			$_SERVER['SERVER_SOFTWARE'] = $this->saved_server_software;
		}
		parent::tear_down();
	}

	/**
	 * Hide key for the nginx restart notice is admitted by the state gate.
	 *
	 * @since 2.10.0
	 */
	public function test_hide_note_key_is_admitted_by_state_key_gate() {
		$this->assertTrue(
			ConfigKeysSchema::is_known_state_key( 'common.hide_note_nginx_restart_required' )
		);
	}

	/**
	 * Dismiss nonce validates against the config_state_master handler key.
	 *
	 * @since 2.10.0
	 */
	public function test_dismiss_nonce_uses_config_state_master_action() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$_REQUEST['_wpnonce'] = Util_Nonce::create_admin( 'w3tc_default_config_state_master' );

		$this->assertTrue(
			Util_Nonce::verify_admin( Util_Nonce::admin_action( 'w3tc_default_config_state_master' ) )
		);
	}

	/**
	 * Notice is not rendered once the hide flag is set, even if show remains true.
	 *
	 * @since 2.10.0
	 */
	public function test_notice_suppressed_when_hide_flag_set() {
		$state = Dispatcher::config_state_master();
		$state->set( 'common.show_note.nginx_restart_required', true );
		$state->set( 'common.hide_note_nginx_restart_required', true );
		$state->save();

		$notes  = new Generic_AdminNotes();
		$result = $notes->w3tc_notes( array() );

		$this->assertArrayNotHasKey( 'nginx_restart_required', $result );
	}

	/**
	 * Notice is rendered when show is set and hide is not.
	 *
	 * @since 2.10.0
	 */
	public function test_notice_rendered_when_show_set_and_not_hidden() {
		$state = Dispatcher::config_state_master();
		$state->set( 'common.show_note.nginx_restart_required', true );
		$state->set( 'common.hide_note_nginx_restart_required', false );
		$state->save();

		$notes  = new Generic_AdminNotes();
		$result = $notes->w3tc_notes( array() );

		$this->assertArrayHasKey( 'nginx_restart_required', $result );
		$this->assertStringContainsString( 'nginx.conf rules have been updated', $result['nginx_restart_required'] );
	}

	/**
	 * A net nginx.conf change clears a prior dismiss so the notice can reappear.
	 *
	 * @since 2.10.0
	 */
	public function test_finalize_clears_hide_when_rules_changed() {
		$_SERVER['SERVER_SOFTWARE'] = 'nginx/1.18';

		$old_content = "# BEGIN W3TC TEST\nlocation /w3tc-test/ { return 403; }\n# END W3TC TEST\n";
		$new_content = "# BEGIN W3TC TEST\nlocation /w3tc-test/ { return 404; }\n# END W3TC TEST\n";
		$path        = Util_Rule::get_nginx_rules_path();
		$this->assertNotSame( '', $path );

		$existed = \file_exists( $path );
		$backup  = $existed ? (string) @\file_get_contents( $path ) : null;
		$this->assertNotFalse( @\file_put_contents( $path, $new_content ) );

		$state = Dispatcher::config_state_master();
		$state->set( 'common.show_note.nginx_restart_required', false );
		$state->set( 'common.hide_note_nginx_restart_required', true );
		$state->save();

		Util_Rule::finalize_nginx_restart_notice_after_environment_fix(
			Util_Rule::nginx_rules_fingerprint( $old_content )
		);

		$state = Dispatcher::config_state_master();
		$this->assertTrue( $state->get_boolean( 'common.show_note.nginx_restart_required' ) );
		$this->assertFalse( $state->get_boolean( 'common.hide_note_nginx_restart_required' ) );

		if ( $existed ) {
			@\file_put_contents( $path, $backup );
		} else {
			@\unlink( $path );
		}
	}

	/**
	 * finalize must not undo a dismiss when nginx.conf is unchanged.
	 *
	 * @since 2.10.0
	 */
	public function test_finalize_preserves_hide_when_rules_unchanged() {
		$_SERVER['SERVER_SOFTWARE'] = 'nginx/1.18';

		$content = "# BEGIN W3TC TEST\nlocation /w3tc-test/ { return 403; }\n# END W3TC TEST\n";
		$path    = Util_Rule::get_nginx_rules_path();
		$this->assertNotSame( '', $path );

		$existed = \file_exists( $path );
		$backup  = $existed ? (string) @\file_get_contents( $path ) : null;
		$this->assertNotFalse( @\file_put_contents( $path, $content ) );

		$fingerprint = Util_Rule::nginx_rules_file_fingerprint();

		$state = Dispatcher::config_state_master();
		$state->set( 'common.hide_note_nginx_restart_required', true );
		$state->set( 'common.nginx_rules_dismiss_fingerprint', $fingerprint );
		$state->save();

		Util_Rule::finalize_nginx_restart_notice_after_environment_fix( $fingerprint );

		$state = Dispatcher::config_state_master();
		$this->assertTrue(
			$state->get_boolean( 'common.hide_note_nginx_restart_required' ),
			'Unchanged nginx.conf must not reopen a dismissed notice.'
		);

		if ( $existed ) {
			@\file_put_contents( $path, $backup );
		} else {
			@\unlink( $path );
		}
	}

	/**
	 * finalize must restore hide when dismiss fingerprint matches unchanged rules.
	 *
	 * @since 2.10.0
	 */
	public function test_finalize_restores_hide_from_dismiss_fingerprint() {
		$_SERVER['SERVER_SOFTWARE'] = 'nginx/1.18';

		$content     = "# BEGIN W3TC TEST\nlocation /w3tc-test/ { return 403; }\n# END W3TC TEST\n";
		$path        = Util_Rule::get_nginx_rules_path();
		$this->assertNotSame( '', $path );

		$existed = \file_exists( $path );
		$backup  = $existed ? (string) @\file_get_contents( $path ) : null;
		$this->assertNotFalse( @\file_put_contents( $path, $content ) );

		$fingerprint = Util_Rule::nginx_rules_file_fingerprint();

		$state = Dispatcher::config_state_master();
		$state->set( 'common.hide_note_nginx_restart_required', false );
		$state->set( 'common.nginx_rules_dismiss_fingerprint', $fingerprint );
		$state->save();

		Util_Rule::finalize_nginx_restart_notice_after_environment_fix( $fingerprint );

		$state = Dispatcher::config_state_master();
		$this->assertTrue(
			$state->get_boolean( 'common.hide_note_nginx_restart_required' ),
			'A stored dismiss fingerprint must keep the notice hidden while rules are unchanged.'
		);

		if ( $existed ) {
			@\file_put_contents( $path, $backup );
		} else {
			@\unlink( $path );
		}
	}

	/**
	 * add_rules must not undo a dismiss when the rules file is already current.
	 *
	 * @since 2.10.0
	 */
	public function test_add_rules_noop_preserves_dismissed_nginx_restart_notice() {
		$_SERVER['SERVER_SOFTWARE'] = 'nginx/1.18';

		$start = '# BEGIN W3TC TEST';
		$end   = '# END W3TC TEST';
		$rules = $start . "\nlocation /w3tc-test/ { return 403; }\n" . $end . "\n";

		$path = \wp_tempnam( 'w3tc-rules' );
		$this->assertNotFalse( $path );
		\file_put_contents( $path, $rules );

		$state = Dispatcher::config_state_master();
		$state->set( 'common.hide_note_nginx_restart_required', true );
		$state->save();

		$exs = new Util_Environment_Exceptions();
		Util_Rule::add_rules( $exs, $path, $rules, $start, $end, array() );

		$state = Dispatcher::config_state_master();
		$this->assertTrue(
			$state->get_boolean( 'common.hide_note_nginx_restart_required' ),
			'Dismiss must survive a no-op nginx rules rewrite.'
		);

		@\unlink( $path );
	}

	/**
	 * Pending dismiss is re-applied after the environment writer clears hide.
	 *
	 * @since 2.10.0
	 */
	public function test_pending_dismiss_survives_environment_fix_pass() {
		$_SERVER['SERVER_SOFTWARE'] = 'nginx/1.18';

		Generic_AdminActions_Default::flag_pending_nginx_restart_notice_dismiss();

		Util_Rule::finalize_nginx_restart_notice_after_environment_fix(
			Util_Rule::nginx_rules_file_fingerprint()
		);

		Generic_AdminActions_Default::apply_pending_nginx_restart_notice_dismiss();

		$state = Dispatcher::config_state_master();
		$this->assertTrue(
			$state->get_boolean( 'common.hide_note_nginx_restart_required' ),
			'Pending dismiss must win over environment-fix notice updates on the redirect pass.'
		);
		$this->assertNotSame(
			'',
			$state->get_string( 'common.nginx_rules_dismiss_fingerprint', '' )
		);
		$this->assertFalse( \get_site_transient( 'w3tc_pending_hide_nginx_restart' ) );
	}
}
