<?php
/**
 * File: class-w3tc-config-overloaded-nonce-test.php
 *
 * Subsite "Use specific settings" / "Use common settings" buttons must mint
 * a nonce for the dispatcher action that includes the `~` parameter.
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.0
 */

declare( strict_types = 1 );

use W3TC\Dispatcher;
use W3TC\Util_Nonce;
use W3TC\Util_Ui;

/**
 * Class: W3tc_Config_Overloaded_Nonce_Test
 *
 * @since 2.11.0
 */
class W3tc_Config_Overloaded_Nonce_Test extends WP_UnitTestCase {

	/**
	 * Previous W3TC blog-id override.
	 *
	 * @var int|string|null
	 */
	private $previous_blog_id;

	/**
	 * Force a non-master blog id so the overload button renders.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->previous_blog_id = array_key_exists( 'w3tc_w3_current_blog_id', $GLOBALS )
			? $GLOBALS['w3tc_w3_current_blog_id']
			: null;

		$GLOBALS['w3tc_w3_current_blog_id'] = 2;
		Dispatcher::reset_config();
	}

	/**
	 * Restore the blog-id override.
	 *
	 * @return void
	 */
	public function tear_down() {
		if ( null === $this->previous_blog_id ) {
			unset( $GLOBALS['w3tc_w3_current_blog_id'] );
		} else {
			$GLOBALS['w3tc_w3_current_blog_id'] = $this->previous_blog_id;
		}

		Dispatcher::reset_config();
		parent::tear_down();
	}

	/**
	 * The enable button's nonce matches its full action and not save-all.
	 *
	 * @return void
	 */
	public function test_specific_settings_button_nonce_verifies() {
		$this->assert_overloaded_button_nonce(
			false,
			'w3tc_config_overloaded_enable~minify__configuration_overloaded'
		);
	}

	/**
	 * The disable button's nonce matches its full action and not save-all.
	 *
	 * @return void
	 */
	public function test_common_settings_button_nonce_verifies() {
		$this->assert_overloaded_button_nonce(
			true,
			'w3tc_config_overloaded_disable~minify__configuration_overloaded'
		);
	}

	/**
	 * Render the minify overload button and check both nonce actions.
	 *
	 * @param bool   $overloaded Whether specific settings are already on.
	 * @param string $action     Expected dispatcher action, including `~`.
	 *
	 * @return void
	 */
	private function assert_overloaded_button_nonce( $overloaded, $action ) {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$config = Dispatcher::config();
		$config->set( 'minify.configuration_overloaded', $overloaded );

		ob_start();
		Util_Ui::config_overloading_button( array( 'key' => 'minify.configuration_overloaded' ) );
		$html = ob_get_clean();

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'name="' . $action . '"', $html );
		$this->assertSame( 1, preg_match( '/data-w3tc-nonce="([^"]+)"/', $html, $matches ) );

		$_REQUEST['_wpnonce'] = $matches[1];
		$this->assertTrue(
			Util_Nonce::verify_admin( Util_Nonce::admin_action( $action ) ),
			'Overload button nonce must verify its own action.'
		);
		$this->assertFalse(
			Util_Nonce::verify_admin( Util_Nonce::admin_action( 'w3tc_save_options' ) ),
			'Overload button nonce must not authorize save-all.'
		);

		$_REQUEST['_wpnonce'] = Util_Nonce::create_admin( 'w3tc_save_options' );
		$this->assertTrue(
			Util_Nonce::verify_admin( Util_Nonce::admin_action( 'w3tc_save_options' ) ),
			'Save-all must still verify its own action.'
		);
		$this->assertFalse(
			Util_Nonce::verify_admin( Util_Nonce::admin_action( $action ) ),
			'Save-all nonce must not authorize the overload action.'
		);

		unset( $_REQUEST['_wpnonce'] );
	}
}
