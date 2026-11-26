<?php
/**
 * Tests that New Relic APM mode turns off the agent's automatic browser script on wp-admin screens.
 *
 * Admin screens are not output-buffered, so ob_callback_apm() never runs there.
 * The plugin's disable_auto_rum() method, which forwards to NewRelicWrapper, is
 * replaced with a mock so no real New Relic agent call is made.
 *
 * @package W3TC\Tests
 *
 * @since 2.11.1
 */

use W3TC\Dispatcher;
use W3TC\Extension_NewRelic_Plugin;

/**
 * Class Newrelic_Admin_Auto_Rum_Test.
 *
 * @since 2.11.1
 */
class Newrelic_Admin_Auto_Rum_Test extends WP_UnitTestCase {
	/**
	 * Builds the plugin in APM mode with the extension active on the front end.
	 *
	 * Autoloading the class first runs the file's own bootstrap (before WP_ADMIN is
	 * defined by a test), so that run does nothing because the extension is inactive.
	 * The wrapper-facing disable_auto_rum() method is mocked; core admin_init callbacks
	 * are cleared so only the plugin's own hook is exercised.
	 *
	 * @since 2.11.1
	 *
	 * @param object $expectation Expected calls to disable_auto_rum().
	 *
	 * @return Extension_NewRelic_Plugin
	 */
	private function make_apm_plugin( $expectation ) {
		class_exists( Extension_NewRelic_Plugin::class );

		$config = Dispatcher::config();
		$config->set( 'extensions.active_frontend', array( 'newrelic' => true ) );
		$config->set( array( 'newrelic', 'monitoring_type' ), 'apm' );

		$plugin = $this->getMockBuilder( Extension_NewRelic_Plugin::class )
			->onlyMethods( array( 'disable_auto_rum' ) )
			->getMock();
		$plugin->expects( $expectation )->method( 'disable_auto_rum' );

		remove_all_actions( 'admin_init' );
		$plugin->run();

		return $plugin;
	}

	/**
	 * APM mode on an admin screen disables auto-RUM on admin_init.
	 *
	 * @since 2.11.1
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_apm_mode_disables_auto_rum_on_admin_screen() {
		class_exists( Extension_NewRelic_Plugin::class );
		define( 'WP_ADMIN', true );

		$this->make_apm_plugin( $this->once() );

		do_action( 'admin_init' );
	}

	/**
	 * APM mode on the front end does not disable auto-RUM up front.
	 *
	 * @since 2.11.1
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_apm_mode_does_not_disable_auto_rum_on_front_end() {
		$this->make_apm_plugin( $this->never() );

		do_action( 'admin_init' );
	}

	/**
	 * A WP_ADMIN constant defined as false is not an admin screen.
	 *
	 * @since 2.11.1
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_apm_mode_ignores_wp_admin_defined_false() {
		class_exists( Extension_NewRelic_Plugin::class );
		define( 'WP_ADMIN', false );

		$this->make_apm_plugin( $this->never() );

		do_action( 'admin_init' );
	}
}
