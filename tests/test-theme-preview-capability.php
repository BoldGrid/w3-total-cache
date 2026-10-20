<?php
/**
 * Theme preview waits until the capability check can run.
 *
 * @package W3TC\Tests
 */

use W3TC\Generic_Plugin;

/**
 * Tests for the Minify wizard theme preview.
 */
class Theme_Preview_Capability_Test extends WP_UnitTestCase {
	/**
	 * Saved request values.
	 *
	 * @var array
	 */
	private $request = array();

	/**
	 * Output buffer level at the start of the test.
	 *
	 * @var int
	 */
	private $ob_level = 0;

	/**
	 * Remember the request before each test.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->ob_level = ob_get_level();
		$this->request  = array(
			'get'       => $_GET,
			'request'   => $_REQUEST,
			'useragent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : null,
		);
	}

	/**
	 * Restore the request after each test.
	 *
	 * @return void
	 */
	public function tear_down() {
		$_GET     = $this->request['get'];
		$_REQUEST = $this->request['request'];
		if ( null === $this->request['useragent'] ) {
			unset( $_SERVER['HTTP_USER_AGENT'] );
		} else {
			$_SERVER['HTTP_USER_AGENT'] = $this->request['useragent'];
		}
		unset( $GLOBALS['wp_themes'] );
		while ( ob_get_level() > $this->ob_level ) {
			ob_end_clean();
		}
		parent::tear_down();
	}

	/**
	 * Prepare a wizard loopback request.
	 *
	 * @param string $theme Theme name.
	 *
	 * @return Generic_Plugin
	 */
	private function wizard_request( $theme ) {
		$_GET['w3tc_theme']             = $theme;
		$_REQUEST['w3tc_theme']         = $theme;
		$_SERVER['HTTP_USER_AGENT']     = W3TC_POWERED_BY . '/2.11.0';
		$plugin = new Generic_Plugin();
		$plugin->run();
		return $plugin;
	}

	/**
	 * An anonymous wizard request registers preview filters and does not swap the theme.
	 *
	 * @return void
	 */
	public function test_anonymous_loopback_does_not_swap_theme() {
		wp_set_current_user( 0 );
		$plugin = $this->wizard_request( 'WordPress Default' );

		$this->assertSame( 10, has_filter( 'template', array( $plugin, 'template_preview' ) ) );
		$this->assertSame( 'original-template', apply_filters( 'template', 'original-template' ) );
		$this->assertSame( 'original-style', apply_filters( 'stylesheet', 'original-style' ) );
	}

	/**
	 * A user who can edit themes gets the requested theme.
	 *
	 * @return void
	 */
	public function test_capable_user_swaps_theme() {
		global $wp_themes;

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$wp_themes = array(
			'Preview Theme' => array(
				'Template'   => 'preview-template',
				'Stylesheet' => 'preview-style',
			),
		);
		$plugin    = $this->wizard_request( 'Preview Theme' );

		$this->assertSame( 'preview-template', apply_filters( 'template', 'original-template' ) );
		$this->assertSame( 'preview-style', apply_filters( 'stylesheet', 'original-style' ) );
		unset( $plugin );
	}

	/**
	 * A normal visitor request does not register the preview filters.
	 *
	 * @return void
	 */
	public function test_normal_request_is_unchanged() {
		wp_set_current_user( 0 );
		unset( $_GET['w3tc_theme'], $_REQUEST['w3tc_theme'] );
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
		$plugin                     = new Generic_Plugin();
		$plugin->run();

		$this->assertFalse( has_filter( 'template', array( $plugin, 'template_preview' ) ) );
	}
}
