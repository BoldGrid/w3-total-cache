<?php
/**
 * File: class-w3tc-admin-actions-capability-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.1
 */

declare( strict_types = 1 );

use W3TC\Cdn_AdminActions;
use W3TC\Support_AdminActions;

/**
 * Class: W3tc_Admin_Actions_Capability_Test
 *
 * @since 2.11.1
 */
class W3tc_Admin_Actions_Capability_Test extends WP_UnitTestCase {

	/**
	 * CDN action handlers require an administrative capability.
	 *
	 * @since 2.11.1
	 */
	public function test_cdn_handler_rejects_subscriber() {
		$subscriber = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		\wp_set_current_user( $subscriber );

		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'You do not have permission to perform this action.' );

		( new Cdn_AdminActions() )->w3tc_cdn_bunnycdn_signup();
	}

	/**
	 * Support action handlers require an administrative capability.
	 *
	 * @since 2.11.1
	 */
	public function test_support_handler_rejects_subscriber() {
		$subscriber = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		\wp_set_current_user( $subscriber );

		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'You do not have permission to perform this action.' );

		( new Support_AdminActions() )->w3tc_support_send_details();
	}

	/**
	 * Every dispatchable CDN and support handler self-gates before side effects.
	 *
	 * @since 2.11.1
	 */
	public function test_all_handlers_self_gate() {
		$cdn_source     = (string) \file_get_contents( \dirname( __DIR__, 2 ) . '/Cdn_AdminActions.php' );
		$support_source = (string) \file_get_contents( \dirname( __DIR__, 2 ) . '/Support_AdminActions.php' );

		$this->assertSame(
			16,
			\preg_match_all( '/public function w3tc_cdn_[a-z_]+\(\) \{\n\t\t\$this->require_admin_cap\(\);/', $cdn_source )
		);
		$this->assertSame(
			1,
			\preg_match_all( '/public function w3tc_support_[a-z_]+\(\) \{\n\t\t\$this->require_admin_cap\(\);/', $support_source )
		);
	}
}
