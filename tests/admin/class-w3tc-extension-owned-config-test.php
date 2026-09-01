<?php
/**
 * File: class-w3tc-extension-owned-config-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\Config;
use W3TC\Extension_NewRelic_Popup;
use W3TC\Extension_NewRelic_Service;
use W3TC\Generic_AdminActions_Default;

/**
 * Class: W3tc_Extension_Owned_Config_Test
 *
 * Pins ENG7-4278 Phase 1C behaviour:
 *
 *  - New Relic / extension namespaces only write from their dedicated page
 *  - Unrelated keys in the same POST remain unchanged
 *  - New Relic apply-configuration AJAX refuses non-conforming API keys
 *
 * @since X.X.X
 */
class W3tc_Extension_Owned_Config_Test extends WP_UnitTestCase {

	/**
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$tmp = WP_CONTENT_DIR . '/cache/tmp';
		if ( ! is_dir( $tmp ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@mkdir( $tmp, 0755, true );
		}
		$_GET  = array();
		$_POST = array();
	}

	/**
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$_GET  = array();
		$_POST = array();
		parent::tearDown();
	}

	/**
	 * @since X.X.X
	 *
	 * @param string $page Admin page slug.
	 *
	 * @return Generic_AdminActions_Default
	 */
	private function admin_for_page( string $page ): Generic_AdminActions_Default {
		$_GET['page'] = $page;
		return new Generic_AdminActions_Default();
	}

	/**
	 * Register the first-party extensions used by ownership tests.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	private function register_extensions(): void {
		add_filter(
			'w3tc_extensions',
			static function ( $exts ) {
				$exts['cloudflare'] = array(
					'name' => 'Cloudflare',
					'path' => 'w3-total-cache/Extension_CloudFlare_Plugin.php',
				);
				$exts['swarmify']   = array(
					'name' => 'Swarmify',
					'path' => 'w3-total-cache/Extension_Swarmify_Plugin.php',
				);
				$exts['amp']        = array(
					'name' => 'AMP',
					'path' => 'w3-total-cache/Extension_Amp_Plugin.php',
				);
				$exts['newrelic']   = array(
					'name' => 'New Relic',
					'path' => 'w3-total-cache/Extension_NewRelic_Plugin.php',
				);
				return $exts;
			},
			20,
			1
		);
	}

	/**
	 * New Relic monitoring settings save on w3tc_monitoring.
	 *
	 * @since X.X.X
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_allows_newrelic_keys_on_monitoring_page() {
		$this->register_extensions();

		$_POST = array(
			'newrelic___monitoring_type'      => 'browser',
			'newrelic___apm__application_name' => 'My App',
			'newrelic___include_rum'           => '1',
		);

		$config = new Config();
		$config->set( array( 'newrelic', 'monitoring_type' ), 'apm' );
		$config->set( array( 'newrelic', 'apm.application_name' ), 'Old App' );
		$config->set( array( 'newrelic', 'include_rum' ), false );

		$this->admin_for_page( 'w3tc_monitoring' )->read_request( $config );

		$this->assertSame( 'browser', $config->get_string( array( 'newrelic', 'monitoring_type' ) ) );
		$this->assertSame( 'My App', $config->get_string( array( 'newrelic', 'apm.application_name' ) ) );
		$this->assertTrue( $config->get_boolean( array( 'newrelic', 'include_rum' ) ) );
	}

	/**
	 * New Relic keys are refused from an unrelated settings page.
	 *
	 * @since X.X.X
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_rejects_newrelic_keys_from_unrelated_page() {
		$this->register_extensions();

		$_POST = array(
			'newrelic___apm__application_name' => 'Injected App',
			'newrelic___api_key'               => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
		);

		$config = new Config();
		$config->set( array( 'newrelic', 'apm.application_name' ), 'Keep App' );
		$config->set( array( 'newrelic', 'api_key' ), 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' );

		$this->admin_for_page( 'w3tc_pgcache' )->read_request( $config );

		$this->assertSame( 'Keep App', $config->get_string( array( 'newrelic', 'apm.application_name' ) ) );
		$this->assertSame(
			'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
			$config->get_string( array( 'newrelic', 'api_key' ) )
		);
	}

	/**
	 * Extension page settings save for Cloudflare / Swarmify / AMP.
	 *
	 * @since X.X.X
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_allows_extension_keys_on_extensions_page() {
		$this->register_extensions();

		$_POST = array(
			'cloudflare___email'     => 'ops@example.com',
			'swarmify___api_key'    => 'swarm-key-from-ext-page',
			'amp___url_type'        => 'querystring',
		);

		$config = new Config();
		$config->set( array( 'cloudflare', 'email' ), 'old@example.com' );
		$config->set( array( 'swarmify', 'api_key' ), 'old-swarm' );
		$config->set( array( 'amp', 'url_type' ), 'tag' );

		$this->admin_for_page( 'w3tc_extensions' )->read_request( $config );

		$this->assertSame( 'ops@example.com', $config->get_string( array( 'cloudflare', 'email' ) ) );
		$this->assertSame( 'swarm-key-from-ext-page', $config->get_string( array( 'swarmify', 'api_key' ) ) );
		$this->assertSame( 'querystring', $config->get_string( array( 'amp', 'url_type' ) ) );
	}

	/**
	 * Cloudflare General-box settings remain writable from w3tc_general.
	 *
	 * @since X.X.X
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_allows_cloudflare_general_box_keys_on_general_page() {
		$this->register_extensions();

		$_POST = array(
			'cloudflare___widget_interval' => '60',
			'cloudflare___pagecache'       => '1',
			'cloudflare___key'             => 'should-not-write-from-general',
		);

		$config = new Config();
		$config->set( array( 'cloudflare', 'widget_interval' ), 30 );
		$config->set( array( 'cloudflare', 'pagecache' ), false );
		$config->set( array( 'cloudflare', 'key' ), 'baseline-key' );

		$this->admin_for_page( 'w3tc_general' )->read_request( $config );

		$this->assertSame( '60', $config->get_string( array( 'cloudflare', 'widget_interval' ) ) );
		$this->assertTrue( (bool) $config->get( array( 'cloudflare', 'pagecache' ) ) );
		$this->assertSame( 'baseline-key', $config->get_string( array( 'cloudflare', 'key' ) ) );
	}

	/**
	 * Mixed POST: owned extension keys update, foreign keys do not.
	 *
	 * @since X.X.X
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_mixed_request_updates_only_owned_extension_keys() {
		$this->register_extensions();

		$_POST = array(
			'swarmify___api_key'              => 'owned-swarm-key',
			'newrelic___apm__application_name' => 'should-not-write',
			'cdn__s3__secret'                 => 'should-not-write-cdn',
			'pgcache__enabled'                => '1',
		);

		$config = new Config();
		$config->set( array( 'swarmify', 'api_key' ), 'baseline-swarm' );
		$config->set( array( 'newrelic', 'apm.application_name' ), 'baseline-nr' );
		$config->set( 'cdn.s3.secret', 'baseline-cdn' );
		$config->set( 'pgcache.enabled', false );

		$this->admin_for_page( 'w3tc_extensions' )->read_request( $config );

		$this->assertSame( 'owned-swarm-key', $config->get_string( array( 'swarmify', 'api_key' ) ) );
		$this->assertSame( 'baseline-nr', $config->get_string( array( 'newrelic', 'apm.application_name' ) ) );
		$this->assertSame( 'baseline-cdn', $config->get_string( 'cdn.s3.secret' ) );
		$this->assertTrue( $config->get_boolean( 'pgcache.enabled' ) );
	}

	/**
	 * API key format gate accepts REST / NRAK shapes and rejects freeform.
	 *
	 * @since X.X.X
	 */
	public function test_newrelic_api_key_format_gate() {
		$this->assertTrue(
			Extension_NewRelic_Service::is_valid_api_key_format(
				'abcdef0123456789abcdef0123456789abcdef01'
			)
		);
		$this->assertTrue(
			Extension_NewRelic_Service::is_valid_api_key_format(
				'NRAK-abcdefghijklmnopqrst'
			)
		);
		$this->assertFalse(
			Extension_NewRelic_Service::is_valid_api_key_format(
				'ATTACKER_PROOF_RT9217_1777157452'
			)
		);
		$this->assertFalse( Extension_NewRelic_Service::is_valid_api_key_format( '' ) );
		$this->assertFalse( Extension_NewRelic_Service::is_valid_api_key_format( 'short' ) );
		$this->assertFalse( Extension_NewRelic_Service::is_valid_api_key_format( "NRAK-has space" ) );
	}

	/**
	 * Apply-configuration AJAX refuses a freeform API key and leaves config untouched.
	 *
	 * @since X.X.X
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_newrelic_apply_configuration_rejects_invalid_api_key() {
		$_REQUEST = array(
			'api_key'                => 'ATTACKER_PROOF_RT9217_1777157452',
			'monitoring_type'        => 'apm',
			'apm_application_name'   => 'Injected',
			'browser_application_id' => '',
		);
		$_POST    = $_REQUEST;

		$config = \W3TC\Dispatcher::config();
		$config->set( array( 'newrelic', 'api_key' ), 'baseline-keep' );
		$config->set( array( 'newrelic', 'apm.application_name' ), 'baseline-app' );

		ob_start();
		( new Extension_NewRelic_Popup() )->w3tc_ajax_newrelic_apply_configuration();
		$out = ob_get_clean();

		$this->assertStringContainsString( 'Location admin.php?page=w3tc_general&', $out );
		$this->assertSame( 'baseline-keep', $config->get_string( array( 'newrelic', 'api_key' ) ) );
		$this->assertSame( 'baseline-app', $config->get_string( array( 'newrelic', 'apm.application_name' ) ) );
	}
}
