<?php
/**
 * The "Use specific settings" / "Use common settings" buttons carry their own nonce.
 *
 * @package W3TC\Tests
 * @since   X.X.X
 */

use W3TC\Dispatcher;
use W3TC\Util_Nonce;
use W3TC\Util_Ui;

/**
 * Minimal subsite configuration for the overloading button.
 */
class W3TC_Config_Overloading_Button_Config_Stub {
	/**
	 * Configuration values.
	 *
	 * @var array
	 */
	public $values = array();

	/**
	 * A subsite configuration is never the master.
	 *
	 * @return bool
	 */
	public function is_master() {
		return false;
	}

	/**
	 * Returns a boolean configuration value.
	 *
	 * @param string $key Configuration key.
	 *
	 * @return bool
	 */
	public function get_boolean( $key ) {
		return ! empty( $this->values[ $key ] );
	}
}

/**
 * Tests for Util_Ui::config_overloading_button().
 */
class W3TC_Config_Overloading_Button_Nonce_Test extends WP_UnitTestCase {
	/**
	 * Original Dispatcher instances.
	 *
	 * @var array
	 */
	private $dispatcher_instances;

	/**
	 * Saved request values.
	 *
	 * @var array
	 */
	private $request = array();

	/**
	 * Swaps in a subsite configuration and an administrator.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$instances = new ReflectionProperty( Dispatcher::class, 'instances' );
		$this->make_property_accessible( $instances );
		$this->dispatcher_instances = $instances->getValue();

		$this->request = array(
			'post'    => $_POST,
			'request' => $_REQUEST,
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Restores shared state.
	 *
	 * @return void
	 */
	public function tear_down() {
		$instances = new ReflectionProperty( Dispatcher::class, 'instances' );
		$this->make_property_accessible( $instances );
		$instances->setValue( null, $this->dispatcher_instances );

		$_POST    = $this->request['post'];
		$_REQUEST = $this->request['request'];

		parent::tear_down();
	}

	/**
	 * Data for both button states.
	 *
	 * @return array
	 */
	public function button_states() {
		return array(
			'use specific settings' => array( false, 'w3tc_config_overloaded_enable~minify__configuration_overloaded' ),
			'use common settings'   => array( true, 'w3tc_config_overloaded_disable~minify__configuration_overloaded' ),
		);
	}

	/**
	 * The nonce on the button passes the dispatcher check for the button's own request key.
	 *
	 * @dataProvider button_states
	 *
	 * @param bool   $overloaded Current value of the overloading key.
	 * @param string $name       Expected button name, which is the dispatcher request key.
	 *
	 * @return void
	 */
	public function test_button_nonce_passes_the_dispatcher_check( $overloaded, $name ) {
		$this->use_subsite_config( array( 'minify.configuration_overloaded' => $overloaded ) );

		ob_start();
		Util_Ui::config_overloading_button( array( 'key' => 'minify.configuration_overloaded' ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="' . $name . '"', $html );
		$this->assertSame( 1, preg_match( '/data-w3tc-nonce="([^"]+)"/', $html, $matches ), 'The button has no data-w3tc-nonce attribute.' );

		// What w3tc-nonce.js copies into _wpnonce before the form submits.
		$_POST['_wpnonce']    = $matches[1];
		$_REQUEST['_wpnonce'] = $matches[1];

		// The check Generic_Plugin_Admin::load() runs for the detected action.
		$this->assertTrue( Util_Nonce::verify_admin( Util_Nonce::admin_action( $name ) ) );
	}

	/**
	 * Uses a subsite configuration stub for Dispatcher::config().
	 *
	 * @param array $values Configuration values.
	 *
	 * @return void
	 */
	private function use_subsite_config( array $values ) {
		$config         = new W3TC_Config_Overloading_Button_Config_Stub();
		$config->values = $values;

		$instances = new ReflectionProperty( Dispatcher::class, 'instances' );
		$this->make_property_accessible( $instances );
		$all           = $instances->getValue();
		$all['Config'] = $config;
		$instances->setValue( null, $all );
	}

	/**
	 * Makes a private property accessible on PHP versions that need it.
	 *
	 * @param ReflectionProperty $property Property.
	 *
	 * @return void
	 */
	private function make_property_accessible( $property ) {
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
	}
}
