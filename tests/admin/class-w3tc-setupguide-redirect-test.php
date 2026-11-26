<?php
/**
 * File: class-w3tc-setupguide-redirect-test.php
 *
 * The Setup Guide redirect must run before the admin header sends output.
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.1
 */

declare( strict_types = 1 );

use W3TC\Dispatcher;
use W3TC\Generic_Plugin_Admin;

/**
 * Class: W3tc_SetupGuide_Redirect_Test
 *
 * @since 2.11.1
 */
class W3tc_SetupGuide_Redirect_Test extends WP_UnitTestCase {

	/**
	 * Backup of $_GET around tests.
	 *
	 * @var array
	 */
	private $saved_get;

	/**
	 * Backup of $_REQUEST around tests.
	 *
	 * @var array
	 */
	private $saved_request;

	/**
	 * Backup of the global hook suffix.
	 *
	 * @var mixed
	 */
	private $saved_hook_suffix;

	/**
	 * Backup of the page cache enabled setting.
	 *
	 * @var bool
	 */
	private $saved_pgcache_enabled;

	/**
	 * Backup of the install timestamp in the master config state.
	 *
	 * @var int
	 */
	private $saved_install;

	/**
	 * Set up.
	 *
	 * @since 2.11.1
	 */
	public function set_up() {
		parent::set_up();

		global $hook_suffix;

		$this->saved_get         = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->saved_request     = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->saved_hook_suffix = $hook_suffix;

		$config                      = Dispatcher::config();
		$state                       = Dispatcher::config_state_master();
		$this->saved_pgcache_enabled = $config->get_boolean( 'pgcache.enabled' );
		$this->saved_install         = $state->get_integer( 'common.install' );

		$config->set( 'pgcache.enabled', false );
		$state->set( 'common.install', time() );
		delete_site_option( 'w3tc_setupguide_completed' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		add_filter( 'wp_redirect', array( $this, 'throw_on_redirect' ), PHP_INT_MAX );
	}

	/**
	 * Tear down.
	 *
	 * @since 2.11.1
	 */
	public function tear_down() {
		global $hook_suffix;

		remove_filter( 'wp_redirect', array( $this, 'throw_on_redirect' ), PHP_INT_MAX );

		$_GET        = $this->saved_get;
		$_REQUEST    = $this->saved_request;
		$hook_suffix = $this->saved_hook_suffix; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		Dispatcher::config()->set( 'pgcache.enabled', $this->saved_pgcache_enabled );
		Dispatcher::config_state_master()->set( 'common.install', $this->saved_install );

		set_current_screen( 'front' );

		parent::tear_down();
	}

	/**
	 * Stop the request at wp_redirect() so the caller's exit is not reached.
	 *
	 * @since 2.11.1
	 *
	 * @param string $location Redirect location.
	 *
	 * @throws \RuntimeException Always.
	 */
	public function throw_on_redirect( $location ) {
		throw new \RuntimeException( (string) $location );
	}

	/**
	 * Simulate wp-admin/admin.php for a W3TC page up to the screen's load action.
	 *
	 * @since 2.11.1
	 *
	 * @param string $page      Value of the page query arg.
	 * @param string $page_hook Plugin page hook suffix.
	 *
	 * @return Generic_Plugin_Admin
	 */
	private function load_w3tc_page( string $page, string $page_hook ): Generic_Plugin_Admin {
		global $hook_suffix;

		$_GET['page']     = $page;
		$_REQUEST['page'] = $page;
		$hook_suffix      = $page_hook; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$admin = new Generic_Plugin_Admin();
		add_action( 'current_screen', array( $admin, 'queue_setup_guide_redirect' ) );

		set_current_screen( $page_hook );

		remove_action( 'current_screen', array( $admin, 'queue_setup_guide_redirect' ) );

		return $admin;
	}

	/**
	 * The redirect is queued on the load action, after W3TC page actions at the default priority.
	 *
	 * @since 2.11.1
	 */
	public function test_redirect_is_queued_on_load_action_after_page_actions() {
		$admin = $this->load_w3tc_page( 'w3tc_dashboard', 'toplevel_page_w3tc_dashboard' );

		$this->assertSame(
			PHP_INT_MAX,
			has_action( 'load-toplevel_page_w3tc_dashboard', array( $admin, 'maybe_redirect_to_setup_guide' ) )
		);
	}

	/**
	 * The load action redirects to the Setup Guide before any admin output.
	 *
	 * @since 2.11.1
	 */
	public function test_load_action_redirects_to_setup_guide() {
		$this->load_w3tc_page( 'w3tc_dashboard', 'toplevel_page_w3tc_dashboard' );

		$location = null;
		try {
			do_action( 'load-toplevel_page_w3tc_dashboard' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		} catch ( \RuntimeException $e ) {
			$location = $e->getMessage();
		}

		$this->assertNotNull( $location, 'Expected a redirect to the Setup Guide.' );
		$this->assertStringContainsString( 'page=w3tc_setup_guide', $location );
	}

	/**
	 * The admin header no longer issues the redirect.
	 *
	 * @since 2.11.1
	 */
	public function test_admin_head_does_not_redirect() {
		$admin  = $this->load_w3tc_page( 'w3tc_dashboard', 'toplevel_page_w3tc_dashboard' );
		$config = Dispatcher::config();
		$saved  = $config->get_integer( 'pgcache.migrated.qsexempts' );

		$config->set( 'pgcache.migrated.qsexempts', time() );

		$redirected = false;
		ob_start();
		try {
			$admin->admin_head();
		} catch ( \RuntimeException $e ) {
			$redirected = true;
		} finally {
			ob_end_clean();
			$config->set( 'pgcache.migrated.qsexempts', $saved );
		}

		$this->assertFalse( $redirected );
	}

	/**
	 * No redirect once the Setup Guide is completed or skipped.
	 *
	 * @since 2.11.1
	 */
	public function test_no_redirect_after_setup_guide_completed() {
		update_site_option( 'w3tc_setupguide_completed', time() );

		$admin = $this->load_w3tc_page( 'w3tc_dashboard', 'toplevel_page_w3tc_dashboard' );

		$admin->maybe_redirect_to_setup_guide();

		$this->assertTrue( true );
	}

	/**
	 * No redirect when already on the Setup Guide page.
	 *
	 * @since 2.11.1
	 */
	public function test_no_redirect_on_setup_guide_page() {
		$admin = $this->load_w3tc_page( 'w3tc_setup_guide', 'performance_page_w3tc_setup_guide' );

		$admin->maybe_redirect_to_setup_guide();

		$this->assertTrue( true );
	}
}
