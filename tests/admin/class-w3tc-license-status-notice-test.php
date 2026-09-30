<?php
/**
 * File: class-w3tc-license-status-notice-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.6
 */

declare( strict_types = 1 );

use W3TC\Licensing_Plugin_Admin;

/**
 * Class: W3tc_License_Status_Notice_Test
 *
 * _status_is() is a prefix match. Longer license statuses must be chosen
 * before a shorter prefix, or inactive.by_rooturi hides
 * inactive.by_rooturi.activations_limit_reached.
 *
 * @since 2.10.6
 */
class W3tc_License_Status_Notice_Test extends WP_UnitTestCase {

	/**
	 * License admin under test.
	 *
	 * @var Licensing_Plugin_Admin
	 */
	private $admin;

	/**
	 * Reflected get_license_notice().
	 *
	 * @var ReflectionMethod
	 */
	private $notice;

	/**
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->admin  = new Licensing_Plugin_Admin();
		$this->notice = new ReflectionMethod( Licensing_Plugin_Admin::class, 'get_license_notice' );
		$this->notice->setAccessible( true );
	}

	/**
	 * Notice text for a status.
	 *
	 * @since 2.10.6
	 *
	 * @param string $status License status.
	 *
	 * @return string
	 */
	private function notice_for( $status ) {
		return (string) $this->notice->invoke( $this->admin, $status, 'test-license-key' );
	}

	/**
	 * A site over the activation limit must say the limit was reached.
	 *
	 * @since 2.10.6
	 */
	public function test_activation_limit_reached_is_not_hidden_by_rooturi_prefix() {
		$message = $this->notice_for( 'inactive.by_rooturi.activations_limit_reached' );

		$this->assertStringContainsString( 'activation limit being reached', $message );
		$this->assertStringNotContainsString( 'switch your license', $message );
	}

	/**
	 * A license that can still move to this site keeps the reset link.
	 *
	 * @since 2.10.6
	 */
	public function test_rooturi_without_limit_offers_license_switch() {
		foreach ( array( 'inactive.by_rooturi', 'inactive.by_rooturi.activations_limit_not_reached' ) as $status ) {
			$message = $this->notice_for( $status );

			$this->assertStringContainsString( 'not active for this site', $message, $status );
			$this->assertStringContainsString( 'w3tc_licensing_reset_rooturi', $message, $status );
			$this->assertStringNotContainsString( 'activation limit being reached', $message, $status );
		}
	}

	/**
	 * A generic inactive status does not offer a site switch.
	 *
	 * @since 2.10.6
	 */
	public function test_generic_inactive_does_not_offer_license_switch() {
		$message = $this->notice_for( 'inactive' );

		$this->assertStringContainsString( 'is not active', $message );
		$this->assertStringNotContainsString( 'switch your license', $message );
		$this->assertStringNotContainsString( 'activation limit being reached', $message );
	}

	/**
	 * Usable statuses stay silent.
	 *
	 * @since 2.10.6
	 */
	public function test_usable_statuses_have_no_notice() {
		foreach ( array( 'no_key', 'active', 'active.by_rooturi', 'free' ) as $status ) {
			$this->assertSame( '', $this->notice_for( $status ), $status );
		}
	}
}
