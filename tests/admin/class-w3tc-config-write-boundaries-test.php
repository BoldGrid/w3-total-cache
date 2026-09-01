<?php
/**
 * File: class-w3tc-config-write-boundaries-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.6
 */

declare( strict_types = 1 );

use W3TC\Config;
use W3TC\Generic_AdminActions_Default;

/**
 * Class: W3tc_Config_Write_Boundaries_Test
 *
 * Pins ENG7-4277 Phase 1A behaviour for `read_request()`:
 *
 *  - same-page writes of dedicated_page keys succeed
 *  - cross-page writes of dedicated_page keys are dropped
 *  - compound extension credential keys obey the same boundary
 *  - mixed POSTs update only the keys owned by the active page
 *  - no_import keys without dedicated_page never write via POST
 *  - secret clear companions may still clear from General
 *
 * @since 2.10.6
 */
class W3tc_Config_Write_Boundaries_Test extends WP_UnitTestCase {

	/**
	 * @since 2.10.6
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
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$_GET  = array();
		$_POST = array();
		parent::tearDown();
	}

	/**
	 * @since 2.10.6
	 *
	 * @param string               $key   Config key (dotted string).
	 * @param string|array|bool    $value Seed value.
	 *
	 * @return Config
	 */
	private function seeded_config( $key, $value ): Config {
		$config = new Config();
		$ref    = new \ReflectionObject( $config );
		$prop   = $ref->getProperty( '_data' );
		$prop->setAccessible( true );
		$data         = $prop->getValue( $config );
		$data[ $key ] = $value;
		$prop->setValue( $config, $data );

		return $config;
	}

	/**
	 * Build Generic_AdminActions_Default with a forced current page.
	 *
	 * Constructor reads `Util_Admin::get_current_page()` from `$_GET['page']`.
	 *
	 * @since 2.10.6
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
	 * Same-page CDN credential write is accepted.
	 *
	 * @since 2.10.6
	 */
	public function test_allows_owned_scalar_key_on_dedicated_page() {
		$_POST = array(
			'cdn__s3__secret' => 'new-secret-from-cdn-page',
		);

		$config = $this->seeded_config( 'cdn.s3.secret', 'old-secret' );
		$this->admin_for_page( 'w3tc_cdn' )->read_request( $config );

		$this->assertSame( 'new-secret-from-cdn-page', $config->get_string( 'cdn.s3.secret' ) );
	}

	/**
	 * Cross-page CDN credential write is dropped.
	 *
	 * @since 2.10.6
	 */
	public function test_rejects_scalar_key_outside_dedicated_page() {
		$_POST = array(
			'cdn__s3__secret' => 'attacker-secret',
		);

		$config = $this->seeded_config( 'cdn.s3.secret', 'keep-me' );
		$this->admin_for_page( 'w3tc_general' )->read_request( $config );

		$this->assertSame( 'keep-me', $config->get_string( 'cdn.s3.secret' ) );
	}

	/**
	 * Compound Cloudflare API key cannot be written from General.
	 *
	 * @since 2.10.6
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_rejects_compound_credential_from_wrong_page() {
		add_filter(
			'w3tc_extensions',
			function ( $exts ) {
				$exts['cloudflare'] = array(
					'name' => 'Cloudflare',
					'path' => 'w3-total-cache/Extension_CloudFlare_Plugin.php',
				);
				return $exts;
			},
			20,
			1
		);

		$_POST = array(
			'cloudflare___key' => 'PROOF_COMPOUND_BYPASS',
		);

		$config = new Config();
		$config->set( array( 'cloudflare', 'key' ), 'baseline-key' );
		$this->admin_for_page( 'w3tc_general' )->read_request( $config );

		$this->assertSame(
			'baseline-key',
			$config->get_string( array( 'cloudflare', 'key' ) ),
			'Compound cloudflare.key must not write from w3tc_general'
		);
	}

	/**
	 * Compound Cloudflare API key writes on the extensions page.
	 *
	 * @since 2.10.6
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_allows_compound_credential_on_dedicated_page() {
		add_filter(
			'w3tc_extensions',
			function ( $exts ) {
				$exts['cloudflare'] = array(
					'name' => 'Cloudflare',
					'path' => 'w3-total-cache/Extension_CloudFlare_Plugin.php',
				);
				return $exts;
			},
			20,
			1
		);

		$_POST = array(
			'cloudflare___key' => 'legit-extension-page-key',
		);

		$config = new Config();
		$config->set( array( 'cloudflare', 'key' ), 'baseline-key' );
		$this->admin_for_page( 'w3tc_extensions' )->read_request( $config );

		$this->assertSame(
			'legit-extension-page-key',
			$config->get_string( array( 'cloudflare', 'key' ) )
		);
	}

	/**
	 * Mixed POST: owned keys update, foreign dedicated_page keys do not.
	 *
	 * @since 2.10.6
	 */
	public function test_mixed_request_updates_only_owned_keys() {
		$_POST = array(
			'pgcache__enabled'                   => '1',
			'cdn__s3__secret'                    => 'should-not-land',
			'browsercache__no404wp__exceptions'  => "evil\nlines",
			'cdn__import__files'                 => '*.php',
		);

		$config = $this->seeded_config( 'cdn.s3.secret', 'keep-secret' );
		$config->set( 'cdn.import.files', '*.css' );
		$config->set( 'browsercache.no404wp.exceptions', array( 'robots\.txt' ) );
		$config->set( 'pgcache.enabled', false );

		$this->admin_for_page( 'w3tc_general' )->read_request( $config );

		$this->assertTrue( $config->get_boolean( 'pgcache.enabled' ) );
		$this->assertSame( 'keep-secret', $config->get_string( 'cdn.s3.secret' ) );
		$this->assertSame( '*.css', $config->get_string( 'cdn.import.files' ) );
		$this->assertSame( array( 'robots\.txt' ), $config->get_array( 'browsercache.no404wp.exceptions' ) );
	}

	/**
	 * Engine selectors remain writable from General (dedicated_page).
	 *
	 * @since 2.10.6
	 */
	public function test_allows_no_import_engine_on_general_page() {
		$_POST = array(
			'pgcache__engine' => 'memcached',
		);

		$config = $this->seeded_config( 'pgcache.engine', 'file_generic' );
		$this->admin_for_page( 'w3tc_general' )->read_request( $config );

		$this->assertSame( 'memcached', $config->get_string( 'pgcache.engine' ) );
	}

	/**
	 * Engine selectors are refused from a non-General page.
	 *
	 * @since 2.10.6
	 */
	public function test_rejects_no_import_engine_off_general_page() {
		$_POST = array(
			'pgcache__engine' => 'memcached',
		);

		$config = $this->seeded_config( 'pgcache.engine', 'file_generic' );
		$this->admin_for_page( 'w3tc_pgcache' )->read_request( $config );

		$this->assertSame( 'file_generic', $config->get_string( 'pgcache.engine' ) );
	}

	/**
	 * extensions.active has no_import and no dedicated_page — never via POST.
	 *
	 * @since 2.10.6
	 */
	public function test_rejects_no_import_without_dedicated_page() {
		$_POST = array(
			'extensions__active' => "evil\next",
		);

		$config = $this->seeded_config( 'extensions.active', array() );
		$this->admin_for_page( 'w3tc_general' )->read_request( $config );

		$this->assertSame( array(), $config->get_array( 'extensions.active' ) );
	}

	/**
	 * New Relic API key clear companion still works from General.
	 *
	 * @since 2.10.6
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_allows_secret_clear_companion_across_pages() {
		add_filter(
			'w3tc_extensions',
			function ( $exts ) {
				$exts['newrelic'] = array(
					'name' => 'New Relic',
					'path' => 'w3-total-cache/Extension_NewRelic_Plugin.php',
				);
				return $exts;
			},
			20,
			1
		);

		add_filter(
			'w3tc_config_key_descriptor',
			function ( $descriptor, $key ) {
				if ( is_array( $key ) && 'newrelic' === $key[0] && 'api_key' === $key[1] ) {
					return array(
						'type'  => 'string',
						'flags' => array(
							'secret'         => true,
							'dedicated_page' => 'w3tc_monitoring',
						),
					);
				}
				return $descriptor;
			},
			10,
			2
		);

		$_POST = array(
			'newrelic___api_key'            => '',
			'newrelic___api_key__w3tc_clear' => '1',
		);

		$config = new Config();
		$config->set( array( 'newrelic', 'api_key' ), 'NR-KEEP-OR-CLEAR' );
		$this->admin_for_page( 'w3tc_general' )->read_request( $config );

		$this->assertSame(
			'',
			$config->get_string( array( 'newrelic', 'api_key' ) ),
			'Explicit clear companion must still revoke New Relic key from General'
		);
	}

	/**
	 * A forged `__w3tc_clear` companion must not skip the page gate for
	 * non-secret dedicated_page keys (Bugbot finding on ENG7-4277).
	 *
	 * @since 2.10.6
	 */
	public function test_forged_clear_companion_does_not_bypass_non_secret_gate() {
		$_POST = array(
			'cdn__import__files'             => '*.php',
			'cdn__import__files__w3tc_clear' => '1',
			'pgcache__engine'                => 'memcached',
			'pgcache__engine__w3tc_clear'    => '1',
		);

		$config = $this->seeded_config( 'cdn.import.files', '*.css' );
		$config->set( 'pgcache.engine', 'file_generic' );
		/**
		 * Wrong page for both keys: import.files belongs on CDN, engine
		 * on General. Forged clear companions must not open either gate.
		 */
		$this->admin_for_page( 'w3tc_pgcache' )->read_request( $config );

		$this->assertSame( '*.css', $config->get_string( 'cdn.import.files' ) );
		$this->assertSame( 'file_generic', $config->get_string( 'pgcache.engine' ) );
	}
}
