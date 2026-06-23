<?php
/**
 * File: class-w3tc-info-leak-test.php
 *
 * Regressions for the ENG7-3027 log/header redaction pass:
 *  - Util_Debug::redact_wpnonce stops at whitespace and `]` (was
 *    `[^&\]]`, which preserved the value past spaces, tabs, newlines).
 *  - Util_Debug::redact_secrets rewrites the standard wp-config.php
 *    `define()` blocks for DB_PASSWORD + the eight auth keys/salts.
 *  - Util_Environment::w3tc_header no longer appends W3TC_VERSION,
 *    so the X-Powered-By value carries the brand only.
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.0
 */

declare( strict_types = 1 );

use W3TC\Util_Debug;
use W3TC\Util_Environment;

/**
 * Class: W3tc_Info_Leak_Test
 *
 * @since 2.10.0
 */
class W3tc_Info_Leak_Test extends WP_UnitTestCase {

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
	 * The eight WordPress auth keys/salts + DB_PASSWORD are rewritten
	 * to `'REDACTED'`. Other `define()` calls (e.g. WP_DEBUG) are not
	 * touched.
	 *
	 * @since 2.10.0
	 */
	public function test_redact_secrets_strips_wp_config_keys() {
		$blob = <<<PHP
<?php
define( 'DB_NAME', 'wordpress' );
define( 'DB_USER', 'wp' );
define( 'DB_PASSWORD', 'super-secret-1' );
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

		// All nine secret defines must be rewritten.
		$this->assertStringContainsString( "define( 'DB_PASSWORD', 'REDACTED' );", $result );
		$this->assertStringContainsString( "define( 'AUTH_KEY', 'REDACTED' );", $result );
		$this->assertStringContainsString( "define( 'SECURE_AUTH_KEY', 'REDACTED' );", $result );
		$this->assertStringContainsString( "define( 'LOGGED_IN_KEY', 'REDACTED' );", $result );
		$this->assertStringContainsString( "define( 'NONCE_KEY', 'REDACTED' );", $result );
		$this->assertStringContainsString( "define( 'AUTH_SALT', 'REDACTED' );", $result );
		$this->assertStringContainsString( "define( 'SECURE_AUTH_SALT', 'REDACTED' );", $result );
		$this->assertStringContainsString( "define( 'LOGGED_IN_SALT', 'REDACTED' );", $result );
		$this->assertStringContainsString( "define( 'NONCE_SALT', 'REDACTED' );", $result );

		// No original secret value survives.
		$this->assertStringNotContainsString( 'super-secret-1', $result );
		$this->assertStringNotContainsString( 'AAA-key', $result );
		$this->assertStringNotContainsString( 'HHH-salt', $result );

		// Non-secret defines are preserved verbatim.
		$this->assertStringContainsString( "define( 'DB_NAME', 'wordpress' );", $result );
		$this->assertStringContainsString( "define( 'DB_USER', 'wp' );", $result );
		$this->assertStringContainsString( "define( 'WP_DEBUG', true );", $result );
	}

	/**
	 * Non-string input — null, arrays — returns an empty string
	 * rather than blowing up; scalars are stringified.
	 *
	 * @since 2.10.0
	 */
	public function test_redact_secrets_non_string_input() {
		$this->assertSame( '', Util_Debug::redact_secrets( null ) );
		$this->assertSame( '', Util_Debug::redact_secrets( array( 'x' ) ) );
		$this->assertSame( '42', Util_Debug::redact_secrets( 42 ) );
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
}
