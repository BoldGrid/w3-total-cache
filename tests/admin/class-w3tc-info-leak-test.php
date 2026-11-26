<?php
/**
 * File: class-w3tc-info-leak-test.php
 *
 * Regressions for the ENG7-3027 log/header redaction pass:
 *  - Util_Debug::redact_wpnonce stops at whitespace and `]` (was
 *    `[^&\]]`, which preserved the value past spaces, tabs, newlines).
 *  - Util_Debug::redact() rewrites the standard wp-config.php
 *    `define()` blocks for DB_* and the eight auth keys/salts.
 *  - Util_Environment::w3tc_header no longer appends W3TC_VERSION,
 *    so the X-Powered-By value carries the brand only.
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.0
 */

declare( strict_types = 1 );

use W3TC\Extension_ImageService_Plugin_Admin;
use W3TC\Generic_Plugin_Admin;
use W3TC\SetupGuide_Plugin_Admin;
use W3TC\Util_Debug;
use W3TC\Util_Environment;

/**
 * Class: W3tc_Info_Leak_Test
 *
 * @since 2.10.0
 */
class W3tc_Info_Leak_Test extends WP_UnitTestCase {

	/**
	 * Original values of config keys changed by patch_config_values().
	 *
	 * @var array<string, mixed>
	 */
	private $config_backup = array();

	/**
	 * Script registry in place before a test replaced it.
	 *
	 * @var \WP_Scripts|null
	 */
	private $saved_wp_scripts;

	/**
	 * Set up.
	 *
	 * @since 2.11.1
	 */
	public function set_up() {
		parent::set_up();
		$tmp = WP_CONTENT_DIR . '/cache/tmp';
		if ( ! is_dir( $tmp ) ) {
			@mkdir( $tmp, 0755, true );
		}
		$this->config_backup    = array();
		$this->saved_wp_scripts = $GLOBALS['wp_scripts'] ?? null;
	}

	/**
	 * Tear down.
	 *
	 * @since 2.11.1
	 */
	public function tear_down() {
		if ( ! empty( $this->config_backup ) ) {
			$config = w3tc_config();
			foreach ( $this->config_backup as $key => $value ) {
				$config->set( $key, $value );
			}
			$config->save();
			$this->config_backup = array();
		}

		$GLOBALS['wp_scripts'] = $this->saved_wp_scripts;
		unset( $GLOBALS['current_screen'] );

		parent::tear_down();
	}

	/**
	 * `_wpnonce=...` and bare `nonce=...` are redacted up to the next
	 * `&`, `]`, or whitespace. The old pattern (`[^&\]]+`) ran on past
	 * whitespace and preserved the nonce value in adjacent log context.
	 *
	 * @since 2.10.0
	 */
	public function test_redact_wpnonce_stops_at_whitespace() {
		$cases = array(
			'/foo?_wpnonce=ABC123&x=1'                     => '/foo?_wpnonce=REDACTED&x=1',
			'/foo?_wpnonce=ABC123 next-token'              => '/foo?_wpnonce=REDACTED next-token',
			"line1 _wpnonce=ABC123\nline2"                 => "line1 _wpnonce=REDACTED\nline2",
			'admin-ajax.php?action=x&nonce=AAA bbb=ccc'    => 'admin-ajax.php?action=x&nonce=REDACTED bbb=ccc',
			'/foo?_wpnonce=ABC123&_wpnonce=DEF456 trailer' => '/foo?_wpnonce=REDACTED&_wpnonce=REDACTED trailer',
		);

		foreach ( $cases as $input => $expected ) {
			$this->assertSame( $expected, Util_Debug::redact_wpnonce( $input ), 'Failed for input: ' . $input );
		}
	}

	/**
	 * The eight WordPress auth keys/salts and database defines are
	 * rewritten to `'REDACTED'`. Other `define()` calls (e.g. WP_DEBUG)
	 * are not touched.
	 *
	 * @since 2.10.0
	 */
	public function test_redact_masks_wp_config_keys() {
		$blob = <<<PHP
<?php
define( 'DB_NAME', 'wordpress' );
define( 'DB_USER', 'wp' );
define( 'DB_PASSWORD', 'super-sensitive-1' );
define( 'AUTH_KEY',         'AAA-key' );
define( 'SECURE_AUTH_KEY',  'BBB-key' );
define( 'LOGGED_IN_KEY',    'CCC-key' );
define( 'NONCE_KEY',        'DDD-key' );
define( 'AUTH_SALT',        'EEE-salt' );
define( 'SECURE_AUTH_SALT', 'FFF-salt' );
define( 'LOGGED_IN_SALT',   'GGG-salt' );
define( 'NONCE_SALT',       'HHH-salt' );
define( 'WP_DEBUG', true );
PHP;

		$result = Util_Debug::redact_secrets( $blob );

		// All WordPress auth keys/salts and database defines must be rewritten.
		$this->assertStringContainsString( "define( 'DB_PASSWORD', 'REDACTED' );", $result );
		$this->assertStringContainsString( "define( 'DB_NAME', 'REDACTED' );", $result );
		$this->assertStringContainsString( "define( 'DB_USER', 'REDACTED' );", $result );
		$this->assertStringContainsString( "define( 'AUTH_KEY', 'REDACTED' );", $result );
		$this->assertStringContainsString( "define( 'SECURE_AUTH_KEY', 'REDACTED' );", $result );
		$this->assertStringContainsString( "define( 'LOGGED_IN_KEY', 'REDACTED' );", $result );
		$this->assertStringContainsString( "define( 'NONCE_KEY', 'REDACTED' );", $result );
		$this->assertStringContainsString( "define( 'AUTH_SALT', 'REDACTED' );", $result );
		$this->assertStringContainsString( "define( 'SECURE_AUTH_SALT', 'REDACTED' );", $result );
		$this->assertStringContainsString( "define( 'LOGGED_IN_SALT', 'REDACTED' );", $result );
		$this->assertStringContainsString( "define( 'NONCE_SALT', 'REDACTED' );", $result );

		// No original define values survive.
		$this->assertStringNotContainsString( 'super-sensitive-1', $result );
		$this->assertStringNotContainsString( 'AAA-key', $result );
		$this->assertStringNotContainsString( 'HHH-salt', $result );

		// Non-matching defines are preserved verbatim.
		$this->assertStringContainsString( "define( 'WP_DEBUG', true );", $result );
	}

	/**
	 * Define values identified by the general name pattern and DB_*
	 * keys are removed from support bundle content. Optional `_`-suffix
	 * segments and whitespace before `(` are matched.
	 *
	 * @since 2.11.1
	 */
	public function test_redact_masks_custom_and_database_defines() {
		$blob = <<<PHP
<?php
define( 'MY_TEST_TOKEN', 'custom-define-value' );
define( 'DB_USER', 'bundle-db-user' );
define( 'DB_HOST', 'bundle-db-host' );
define( 'DB_NAME', 'bundle-db-name' );
define ( 'AWS_SECRET_ACCESS_KEY', 'aws-key-value' );
define( 'REDIS_PASSWORD_PRIMARY', 'redis-endpoint' );
define( 'WP_DEBUG', true );
PHP;

		$result = Util_Debug::redact_secrets( $blob );

		$this->assertStringContainsString( "define( 'MY_TEST_TOKEN', 'REDACTED' );", $result );
		$this->assertStringContainsString( "define( 'DB_USER', 'REDACTED' );", $result );
		$this->assertStringContainsString( "define( 'DB_HOST', 'REDACTED' );", $result );
		$this->assertStringContainsString( "define( 'DB_NAME', 'REDACTED' );", $result );
		$this->assertStringContainsString( "define( 'AWS_SECRET_ACCESS_KEY', 'REDACTED' );", $result );
		$this->assertStringContainsString( "define( 'REDIS_PASSWORD_PRIMARY', 'REDACTED' );", $result );
		$this->assertStringContainsString( "define( 'WP_DEBUG', true );", $result );

		$this->assertStringNotContainsString( 'custom-define-value', $result );
		$this->assertStringNotContainsString( 'bundle-db-user', $result );
		$this->assertStringNotContainsString( 'bundle-db-host', $result );
		$this->assertStringNotContainsString( 'bundle-db-name', $result );
		$this->assertStringNotContainsString( 'aws-key-value', $result );
		$this->assertStringNotContainsString( 'redis-endpoint', $result );
	}

	/**
	 * Non-string input — null, arrays — returns an empty string
	 * rather than blowing up; scalars are stringified.
	 *
	 * @since 2.10.0
	 */
	public function test_redact_non_string_input() {
		$this->assertSame( '', Util_Debug::redact_secrets( null ) );
		$this->assertSame( '', Util_Debug::redact_secrets( array( 'x' ) ) );
		$this->assertSame( '42', Util_Debug::redact_secrets( 42 ) );
	}

	/**
	 * Setup Guide localization exposes `w3tc_has_key` as a boolean that
	 * tracks whether `plugin.license_key` is non-empty.
	 *
	 * @since 2.11.1
	 */
	public function test_setup_guide_w3tc_has_key_reflects_license_key() {
		$this->patch_config_values( array( 'plugin.license_key' => '' ) );
		$empty = $this->setup_guide_localize_data();
		$this->assertFalse( $empty['w3tc_has_key'] );

		$this->patch_config_values( array( 'plugin.license_key' => 'TEST-LICENSE-KEY-32-CHARS-XXXXX' ) );
		$present = $this->setup_guide_localize_data();
		$this->assertTrue( $present['w3tc_has_key'] );
	}

	/**
	 * Image Service admin localization exposes only webp, avif, and
	 * compression under `settings`, even when extra imageservice keys
	 * exist in config.
	 *
	 * @since 2.11.1
	 */
	public function test_imageservice_localized_settings_allowlist() {
		$this->patch_config_values(
			array(
				'imageservice' => array(
					'webp'        => false,
					'avif'        => true,
					'compression' => 'lossless',
					'auto'        => 'enabled',
					'processing'  => 'lossy',
				),
			)
		);

		$settings = $this->imageservice_localized_settings();

		$this->assertSame( array( 'webp', 'avif', 'compression' ), array_keys( $settings ) );
		$this->assertFalse( $settings['webp'] );
		$this->assertTrue( $settings['avif'] );
		$this->assertSame( 'lossless', $settings['compression'] );
	}

	/**
	 * The admin usage-tracking snippet reports `w3tc_has_key` as `1` or
	 * `0` and does not print the stored license string.
	 *
	 * @since 2.11.1
	 */
	public function test_admin_head_w3tc_has_key_omits_license_key() {
		$license = 'TEST-LICENSE-KEY-32-CHARS-XXXXX';

		$this->patch_config_values(
			array(
				'common.track_usage'         => true,
				'plugin.license_key'         => $license,
				'pgcache.migrated.qsexempts' => time(),
			)
		);
		$present = $this->admin_head_output();
		$this->assertStringContainsString( "'w3tc_has_key': '1'", $present );
		$this->assertStringNotContainsString( $license, $present );

		$this->patch_config_values( array( 'plugin.license_key' => '' ) );
		$this->assertStringContainsString( "'w3tc_has_key': '0'", $this->admin_head_output() );
	}

	/**
	 * `X-Powered-By` no longer carries the plugin version. The brand
	 * still appears (for legitimate support/identification), but the
	 * `/<version>` suffix that used to be appended is gone.
	 *
	 * @since 2.10.0
	 */
	public function test_w3tc_header_omits_version() {
		$header = Util_Environment::w3tc_header();

		$this->assertSame( W3TC_POWERED_BY, $header );
		$this->assertStringNotContainsString( '/', $header );
		if ( defined( 'W3TC_VERSION' ) ) {
			$this->assertStringNotContainsString( W3TC_VERSION, $header );
		}
	}

	/**
	 * Reflectively invoke SetupGuide_Plugin_Admin::get_config().
	 *
	 * @since 2.11.1
	 *
	 * @return array<string, mixed> W3TC_SetupGuide localize payload.
	 */
	private function setup_guide_localize_data(): array {
		$plugin = new SetupGuide_Plugin_Admin();
		$ref    = new \ReflectionMethod( SetupGuide_Plugin_Admin::class, 'get_config' );
		$ref->setAccessible( true );
		$config = $ref->invoke( $plugin );

		foreach ( $config['scripts'] as $script ) {
			if ( 'setup-guide' !== $script['handle'] ) {
				continue;
			}
			return $script['localize']['data'];
		}

		$this->fail( 'Setup Guide localize data not found.' );
	}

	/**
	 * Capture Generic_Plugin_Admin::admin_head() output on the Plugins screen.
	 *
	 * @since 2.11.1
	 *
	 * @return string
	 */
	private function admin_head_output(): string {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		set_current_screen( 'plugins' );

		$GLOBALS['wp_scripts'] = new \WP_Scripts();

		$plugin = new Generic_Plugin_Admin();
		ob_start();
		$plugin->admin_head();

		return (string) ob_get_clean();
	}

	/**
	 * Run Image Service admin_enqueue_scripts and return localized settings.
	 *
	 * @since 2.11.1
	 *
	 * @return array<string, mixed>
	 */
	private function imageservice_localized_settings(): array {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		set_current_screen( 'upload' );

		$GLOBALS['wp_scripts'] = new \WP_Scripts();

		$plugin = new Extension_ImageService_Plugin_Admin();
		$plugin->admin_enqueue_scripts();

		global $wp_scripts;
		$raw = $wp_scripts->get_data( 'w3tc-imageservice', 'data' );
		$this->assertIsString( $raw );
		$this->assertMatchesRegularExpression( '/var w3tcData = (\{.*\});/s', $raw, $raw );

		if ( ! preg_match( '/var w3tcData = (\{.*\});/s', $raw, $matches ) ) {
			$this->fail( 'Could not parse w3tc-imageservice localize payload.' );
		}

		$payload = json_decode( $matches[1], true );
		$this->assertIsArray( $payload );
		$this->assertArrayHasKey( 'settings', $payload );

		return $payload['settings'];
	}

	/**
	 * Patch master config values in storage and refresh the singleton.
	 *
	 * @since 2.11.1
	 *
	 * @param array<string, mixed> $pairs Config key => value.
	 */
	private function patch_config_values( array $pairs ): void {
		$config = w3tc_config();
		foreach ( $pairs as $key => $value ) {
			if ( ! \array_key_exists( $key, $this->config_backup ) ) {
				$this->config_backup[ $key ] = $config->get( $key );
			}
			$config->set( $key, $value );
		}
		$config->save();
	}

}
