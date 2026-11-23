<?php
/**
 * File: class-w3tc-account-widget-license-gate-test.php
 *
 * @package W3TC
 */

declare( strict_types = 1 );

use W3TC\Dispatcher;
use W3TC\Generic_WidgetAccount;
use W3TC\Licensing_Plugin_Admin;

/**
 * The Account widget reads the stored license state and does not add its own
 * remote license check.
 */
class W3tc_Account_Widget_License_Gate_Test extends WP_UnitTestCase {

	/**
	 * Whether the licensing HTTP endpoint was called.
	 *
	 * @var int
	 */
	private $http_calls = 0;

	/**
	 * Prepare a writable cache temp directory.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$tmp = WP_CONTENT_DIR . '/cache/tmp';
		if ( ! is_dir( $tmp ) ) {
			mkdir( $tmp, 0755, true );
		}
		$this->http_calls = 0;
	}

	/**
	 * Drop the HTTP mock.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_all_filters( 'pre_http_request' );
		parent::tearDown();
	}

	/**
	 * Replace state values on the Dispatcher singleton.
	 *
	 * @param array $pairs State key => value.
	 * @return void
	 */
	private function set_state( array $pairs ): void {
		$state = Dispatcher::config_state();
		$ref   = new \ReflectionObject( $state );
		$prop  = $ref->getProperty( '_data' );
		$prop->setAccessible( true );
		$data = $prop->getValue( $state );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		foreach ( $pairs as $key => $value ) {
			$data[ $key ] = $value;
		}
		$prop->setValue( $state, $data );
	}

	/**
	 * Replace config values on the Dispatcher singleton.
	 *
	 * @param array $pairs Config key => value.
	 * @return void
	 */
	private function set_config( array $pairs ): void {
		$config = Dispatcher::config();
		$ref    = new \ReflectionObject( $config );
		$prop   = $ref->getProperty( '_data' );
		$prop->setAccessible( true );
		$data = $prop->getValue( $config );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		foreach ( $pairs as $key => $value ) {
			$data[ $key ] = $value;
		}
		$prop->setValue( $config, $data );
	}

	/**
	 * Count licensing HTTP calls.
	 *
	 * @return void
	 */
	private function install_edd_mock(): void {
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) {
				if ( false === strpos( (string) $url, 'edd_action=check_license' ) ) {
					return $pre;
				}
				++$this->http_calls;
				return array(
					'headers'  => array(),
					'body'     => wp_json_encode(
						array(
							'license'        => 'valid',
							'license_status' => 'active.by_rooturi',
							'license_terms'  => 'accept',
						)
					),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);
	}

	/**
	 * Render the Account widget.
	 *
	 * @return string
	 */
	private function render_widget(): string {
		$widget = new Generic_WidgetAccount();
		ob_start();
		$widget->widget_form();
		return (string) ob_get_clean();
	}

	/**
	 * Run the existing status-update path.
	 *
	 * @return void
	 */
	private function run_status_update(): void {
		$admin  = new Licensing_Plugin_Admin();
		$ref    = new \ReflectionObject( $admin );
		$method = $ref->getMethod( 'maybe_update_license_status' );
		$method->setAccessible( true );
		$method->invoke( $admin );
	}

	/**
	 * Inside the next_check window the widget shows stored state and makes
	 * no remote call.
	 *
	 * @return void
	 */
	public function test_widget_skips_remote_check_inside_the_window(): void {
		$this->install_edd_mock();
		$this->set_state(
			array(
				'license.status'     => 'active.by_rooturi',
				'license.next_check' => time() + DAY_IN_SECONDS,
			)
		);

		$html = $this->render_widget();

		$this->assertSame( 0, $this->http_calls );
		$this->assertStringContainsString( 'Pro', $html );
		$this->assertStringContainsString( 'Active', $html );
	}

	/**
	 * After the window, the status-update path may check once. Rendering the
	 * widget on that same load does not add a second call.
	 *
	 * @return void
	 */
	public function test_widget_does_not_add_a_second_check_after_the_window(): void {
		$this->install_edd_mock();
		$this->set_config(
			array(
				'plugin.license_key' => 'test-license-key',
				'plugin.type'        => '',
			)
		);
		$this->set_state(
			array(
				'license.status'     => 'invalid.not_present',
				'license.next_check' => time() - MINUTE_IN_SECONDS,
			)
		);

		$this->run_status_update();
		$calls_after_status = $this->http_calls;
		$this->render_widget();

		$this->assertSame( 1, $calls_after_status );
		$this->assertSame( 1, $this->http_calls );
	}
}
