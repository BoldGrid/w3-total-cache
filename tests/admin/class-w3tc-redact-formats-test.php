<?php
/**
 * File: class-w3tc-redact-formats-test.php
 *
 * Remaining Util_Debug::redact patterns (query, JSON, print_r,
 * encoded, malformed, nested, headers, URIs). Each case documents
 * the expected transformed output.
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.6
 */

declare( strict_types = 1 );

use W3TC\Util_Debug;

/**
 * Class: W3tc_Redact_Formats_Test
 *
 * @since 2.10.6
 */
class W3tc_Redact_Formats_Test extends WP_UnitTestCase {

	/**
	 * Positive, negative, and edge fixtures for remaining redact
	 * patterns. Expected output is the full transformed string.
	 *
	 * @since 2.10.6
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function redact_fixture_provider() {
		return array(
			'query password'                   => array(
				'GET /a?password=hunter2&x=1',
				'GET /a?password=REDACTED&x=1',
			),
			'query spaces around equals'       => array(
				'GET /a?password = hunter2&x=1',
				'GET /a?password = REDACTED&x=1',
			),
			'query repeated secrets'           => array(
				'token=aaa&token=bbb&password=ccc',
				'token=REDACTED&token=REDACTED&password=REDACTED',
			),
			'negative delimiter monkey'        => array(
				'GET /foo?monkey=banana&pass=hunter2',
				'GET /foo?monkey=banana&pass=REDACTED',
			),
			'negative compass not pass'        => array(
				'GET /foo?compass=north&x=1',
				'GET /foo?compass=north&x=1',
			),
			'json password'                    => array(
				'{"user":"wp","password":"hunter2","db":"wordpress"}',
				'{"user":"wp","password":"REDACTED","db":"wordpress"}',
			),
			'json nested secret'               => array(
				'{"credentials":{"cdn.s3.secret":"s3-secret-value","region":"us-east-1"}}',
				'{"credentials":{"cdn.s3.secret":"REDACTED","region":"us-east-1"}}',
			),
			'json dotted s3 key'               => array(
				'{"cdn.s3.key":"AKIAEXAMPLE","region":"us-east-1"}',
				'{"cdn.s3.key":"REDACTED","region":"us-east-1"}',
			),
			'json negative bare key'           => array(
				'{"key":"page/index","engine":"file"}',
				'{"key":"page/index","engine":"file"}',
			),
			'print_r ftp privkey'              => array(
				"    [cdn.ftp.privkey] => -----BEGIN KEY-----\n    [cdn.ftp.host] => ftp.example.com",
				"    [cdn.ftp.privkey] => REDACTED\n    [cdn.ftp.host] => ftp.example.com",
			),
			'json pretty multiline'            => array(
				"{\n  \"token\": \"abc-123\",\n  \"status\": \"ok\"\n}",
				"{\n  \"token\": \"REDACTED\",\n  \"status\": \"ok\"\n}",
			),
			'json escaped quote in value'      => array(
				'{"password":"aaa\\"bbb"}',
				'{"password":"REDACTED"}',
			),
			'json malformed unclosed value'    => array(
				'{"password": "hunter2',
				'{"password": "REDACTED',
			),
			'json negative timeout'            => array(
				'{"timeout": 30, "engine": "redis"}',
				'{"timeout": 30, "engine": "redis"}',
			),
			'print_r ftp pass'                 => array(
				"    [cdn.ftp.pass] => ftp-secret\n    [cdn.ftp.host] => ftp.example.com",
				"    [cdn.ftp.pass] => REDACTED\n    [cdn.ftp.host] => ftp.example.com",
			),
			'php array arrow'                  => array(
				"'cdn.s3.secret' => 's3-secret-value'",
				"'cdn.s3.secret' => 'REDACTED'",
			),
			'urlencoded query'                 => array(
				'q=1&password%3Dhunter2&x=2',
				'q=1&password%3DREDACTED&x=2',
			),
			'urlencoded json'                  => array(
				'%22password%22%3A%22hunter2%22',
				'%22password%22%3A%22REDACTED%22',
			),
			'query dotted s3 key'              => array(
				'GET /x?cdn.s3.key=AKIAEXAMPLE&region=us-east-1',
				'GET /x?cdn.s3.key=REDACTED&region=us-east-1',
			),
			'urlencoded dotted s3 key'         => array(
				'q=1&cdn.s3.key%3DAKIAEXAMPLE&x=2',
				'q=1&cdn.s3.key%3DREDACTED&x=2',
			),
			'urlencoded json dotted key'       => array(
				'%22cdn.s3.key%22%3A%22AKIAEXAMPLE%22',
				'%22cdn.s3.key%22%3A%22REDACTED%22',
			),
			'wp hash auth cookie'              => array(
				'Cookie: wordpress_a1b2c3d4e5f6789012345678901234ab=user|expiry|hmac; path=/',
				'Cookie: wordpress_a1b2c3d4e5f6789012345678901234ab=REDACTED; path=/',
			),
			'negative wordpress_test_cookie'   => array(
				'Cookie: wordpress_test_cookie=WP Cookie check; path=/',
				'Cookie: wordpress_test_cookie=WP Cookie check; path=/',
			),
			'accesskey header'                 => array(
				"AccessKey: bunny-secret-token\r\nHost: example.com",
				"AccessKey: REDACTED\r\nHost: example.com",
			),
			'x-api-key header'                 => array(
				'X-Api-Key: header-secret',
				'X-Api-Key: REDACTED',
			),
			'wp auth cookie'                   => array(
				'Cookie: wordpress_logged_in_abc123=user|expiry|hash; path=/',
				'Cookie: wordpress_logged_in_abc123=REDACTED; path=/',
			),
			'redis uri with password'          => array(
				'redis://cacheuser:redis-secret@127.0.0.1:6379/0',
				'redis://cacheuser:REDACTED@127.0.0.1:6379/0',
			),
			'redis uri empty user'             => array(
				'redis://:redis-secret@127.0.0.1:6379',
				'redis://:REDACTED@127.0.0.1:6379',
			),
			'negative host port not userinfo'  => array(
				'redis://127.0.0.1:6379',
				'redis://127.0.0.1:6379',
			),
			'define escaped quote'             => array(
				"define( 'AUTH_KEY', 'aaa\\'bbb' );",
				"define( 'AUTH_KEY', 'REDACTED' );",
			),
			'define non-secret preserved'      => array(
				"define( 'DB_NAME', 'wordpress' );",
				"define( 'DB_NAME', 'wordpress' );",
			),
		);
	}

	/**
	 * @since 2.10.6
	 *
	 * @dataProvider redact_fixture_provider
	 *
	 * @param string $input    Raw diagnostic text.
	 * @param string $expected Transformed output.
	 */
	public function test_redact_fixtures( $input, $expected ) {
		$this->assertSame( $expected, Util_Debug::redact( $input ) );
	}

	/**
	 * Secret values from the fixtures must not survive; neighboring
	 * non-secret tokens must.
	 *
	 * @since 2.10.6
	 */
	public function test_redact_strips_secret_values() {
		$blob = '{"credentials":{"password":"hunter2","cdn.s3.secret":"s3-secret-value"},'
			. '"timeout":30}' . "\n"
			. 'GET /foo?monkey=banana&pass=hunter2&password%3Dalt-secret' . "\n"
			. 'AccessKey: bunny-secret-token' . "\n"
			. 'redis://cacheuser:redis-secret@127.0.0.1:6379/0' . "\n"
			. 'wordpress_logged_in_abc123=user|expiry|hash';

		$out = Util_Debug::redact( $blob );

		$this->assertStringNotContainsString( 'hunter2', $out );
		$this->assertStringNotContainsString( 's3-secret-value', $out );
		$this->assertStringNotContainsString( 'alt-secret', $out );
		$this->assertStringNotContainsString( 'bunny-secret-token', $out );
		$this->assertStringNotContainsString( 'redis-secret', $out );
		$this->assertStringNotContainsString( 'user|expiry|hash', $out );
		$this->assertStringContainsString( 'monkey=banana', $out );
		$this->assertStringContainsString( '"timeout":30', $out );
		$this->assertStringContainsString( '127.0.0.1:6379', $out );
	}

	/**
	 * Nested audit_log context: secret-keyed scalars and JSON blobs
	 * inside child arrays are filtered; non-secret keys stay useful.
	 *
	 * @since 2.10.6
	 */
	public function test_audit_log_walks_nested_context() {
		$received = array();
		$listener = function ( $event, $context ) use ( &$received ) {
			$received[] = $context;
		};
		add_action( 'w3tc_audit_log', $listener, 10, 2 );

		Util_Debug::audit_log(
			'cdn_failed',
			array(
				'handler' => 'cdn.purge',
				'detail'  => '{"password":"hunter2","status":"fail"}',
				'nested'  => array(
					'cdn.ftp.pass' => 'ftp-secret',
					'cdn.s3.key'   => 'AKIAEXAMPLE',
					'region'       => 'us-east-1',
					'body'         => 'token=abc-123',
				),
			)
		);

		remove_action( 'w3tc_audit_log', $listener, 10 );

		$this->assertCount( 1, $received );
		$ctx = $received[0];
		$this->assertSame( 'cdn.purge', $ctx['handler'] );
		$this->assertStringNotContainsString( 'hunter2', $ctx['detail'] );
		$this->assertStringContainsString( '"status":"fail"', $ctx['detail'] );
		$this->assertSame( 'REDACTED', $ctx['nested']['cdn.ftp.pass'] );
		$this->assertSame( 'REDACTED', $ctx['nested']['cdn.s3.key'] );
		$this->assertSame( 'us-east-1', $ctx['nested']['region'] );
		$this->assertStringNotContainsString( 'abc-123', $ctx['nested']['body'] );
		$this->assertStringContainsString( 'token=REDACTED', $ctx['nested']['body'] );
	}

	/**
	 * Malformed oversize JSON value is bounded and does not warn.
	 *
	 * @since 2.10.6
	 */
	public function test_redact_malformed_oversize_is_bounded() {
		$long = str_repeat( 'A', 4096 );
		$blob = '{"password": "' . $long;

		$out = Util_Debug::redact( $blob );

		$this->assertStringNotContainsString( 'AAAA', $out );
		$this->assertStringContainsString( '"password": "REDACTED', $out );
	}

	/**
	 * Second pass is a no-op on already-redacted mixed-format text.
	 *
	 * @since 2.10.6
	 */
	public function test_redact_new_patterns_are_idempotent() {
		$blob = '{"password":"hunter2"} token=abc AccessKey: xyz redis://u:p@h:1';
		$once  = Util_Debug::redact( $blob );
		$twice = Util_Debug::redact( $once );

		$this->assertSame( $once, $twice );
	}

	/**
	 * Real print_r() of a multiline PEM privkey redacts the body and
	 * keeps the neighboring host field.
	 *
	 * @since 2.10.6
	 */
	public function test_redact_print_r_multiline_privkey() {
		$dump = print_r(
			array(
				'cdn.ftp.privkey' => "-----BEGIN PRIVATE KEY-----\nMIIE-SECRET-BODY\n-----END PRIVATE KEY-----",
				'cdn.ftp.host'    => 'ftp.example.com',
			),
			true
		);

		$out = Util_Debug::redact( $dump );

		$this->assertStringNotContainsString( 'MIIE-SECRET-BODY', $out );
		$this->assertStringNotContainsString( 'BEGIN PRIVATE KEY', $out );
		$this->assertStringContainsString( '[cdn.ftp.privkey] => REDACTED', $out );
		$this->assertStringContainsString( '[cdn.ftp.host] => ftp.example.com', $out );
	}

	/**
	 * Nested print_r Array under a secret key is blanked; sibling
	 * fields at the same indent stay visible.
	 *
	 * @since 2.10.6
	 */
	public function test_redact_print_r_nested_array() {
		$dump = print_r(
			array(
				'password' => array(
					'value' => 'hunter2nested',
				),
				'region'   => 'us-east-1',
			),
			true
		);

		$out = Util_Debug::redact( $dump );

		$this->assertStringNotContainsString( 'hunter2nested', $out );
		$this->assertStringContainsString( '[password] => REDACTED', $out );
		$this->assertStringContainsString( '[region] => us-east-1', $out );
	}

	/**
	 * Nested print_r object under a secret key is blanked.
	 *
	 * @since 2.10.6
	 */
	public function test_redact_print_r_nested_object() {
		$obj        = new \stdClass();
		$obj->value = 'hunter2nested';
		$dump       = print_r(
			array(
				'password' => $obj,
				'region'   => 'us-east-1',
			),
			true
		);

		$out = Util_Debug::redact( $dump );

		$this->assertStringNotContainsString( 'hunter2nested', $out );
		$this->assertStringContainsString( '[password] => REDACTED', $out );
		$this->assertStringContainsString( '[region] => us-east-1', $out );
	}

	/**
	 * Numeric children under a secret print_r key are consumed.
	 *
	 * @since 2.10.6
	 */
	public function test_redact_print_r_numeric_kids() {
		$dump = print_r(
			array(
				'cdn.ftp.privkey' => array(
					0 => 'BEGINLINE',
					1 => 'MIIE-SECRET-BODY',
				),
				'cdn.ftp.host'    => 'ftp.example.com',
			),
			true
		);

		$out = Util_Debug::redact( $dump );

		$this->assertStringNotContainsString( 'MIIE-SECRET-BODY', $out );
		$this->assertStringNotContainsString( 'BEGINLINE', $out );
		$this->assertStringContainsString( '[cdn.ftp.privkey] => REDACTED', $out );
		$this->assertStringContainsString( '[cdn.ftp.host] => ftp.example.com', $out );
	}

	/**
	 * Shallower-indent sibling after a nested secret key stays visible.
	 *
	 * @since 2.10.6
	 */
	public function test_redact_print_r_shallower_sibling() {
		$dump = print_r(
			array(
				'outer' => array(
					'password' => array(
						'value' => 'hunter2nested',
					),
				),
				'ok'    => 'visible-ok',
			),
			true
		);

		$out = Util_Debug::redact( $dump );

		$this->assertStringNotContainsString( 'hunter2nested', $out );
		$this->assertStringContainsString( '[password] => REDACTED', $out );
		$this->assertStringContainsString( '[ok] => visible-ok', $out );
	}

	/**
	 * Nested var_export arrays under a secret key are blanked.
	 *
	 * @since 2.10.6
	 */
	public function test_redact_var_export_nested_array() {
		$dump = var_export(
			array(
				'password' => array(
					'value' => 'hunter2nested',
				),
				'region'   => 'us-east-1',
			),
			true
		);

		$out = Util_Debug::redact( $dump );

		$this->assertStringNotContainsString( 'hunter2nested', $out );
		$this->assertStringContainsString( "'password' => REDACTED", $out );
		$this->assertStringContainsString( "'region' => 'us-east-1'", $out );
	}

	/**
	 * Secret-keyed nested arrays are blanked wholesale so a child
	 * named `value` cannot keep the secret.
	 *
	 * @since 2.10.6
	 */
	public function test_audit_log_blanks_secret_keyed_arrays() {
		$received = array();
		$listener = function ( $event, $context ) use ( &$received ) {
			$received[] = $context;
		};
		add_action( 'w3tc_audit_log', $listener, 10, 2 );

		Util_Debug::audit_log(
			'cdn_failed',
			array(
				'password' => array(
					'value' => 'hunter2nested',
				),
				'region'   => 'us-east-1',
			)
		);

		remove_action( 'w3tc_audit_log', $listener, 10 );

		$this->assertCount( 1, $received );
		$this->assertSame( 'REDACTED', $received[0]['password'] );
		$this->assertSame( 'us-east-1', $received[0]['region'] );
	}

	/**
	 * URL-encoded JSON values longer than 256 chars still redact, and
	 * unclosed encoded values do not keep the secret.
	 *
	 * @since 2.10.6
	 */
	public function test_redact_urlencoded_json_long_and_unclosed() {
		$long = str_repeat( 'A', 400 );
		$closed = '%22password%22%3A%22' . $long . '%22';
		$open   = '%22password%22%3A%22' . $long;

		$closed_out = Util_Debug::redact( $closed );
		$open_out   = Util_Debug::redact( $open );

		$this->assertSame( '%22password%22%3A%22REDACTED%22', $closed_out );
		$this->assertStringNotContainsString( 'AAAA', $closed_out );
		$this->assertStringNotContainsString( 'AAAA', $open_out );
		$this->assertStringContainsString( '%22password%22%3A%22REDACTED', $open_out );
	}
}
