<?php
/**
 * Standalone regression test for two server-config hardening fixes:
 *
 *  - rt9-30: Util_Environment::safe_simplexml_load_string() parses XML with
 *    XXE protections (external entities are not expanded), so the sitemap /
 *    Azure-MI XML parsers cannot be coerced into local-file disclosure.
 *  - rt9-32: the vendored lib/S3Compatible.php XML parser is hardened with
 *    the same LIBXML_NONET + entity-loader guard (asserted at source level,
 *    since the call is buried in the request/response flow).
 *  - rt9-209: Util_Rule::is_valid_custom_rules_path() constrains the admin
 *    `config.path` value so the nginx / LiteSpeed rules file cannot be
 *    written to an arbitrary location (e.g. a .php payload in the docroot or
 *    an extensionless /etc/cron.d target), while still allowing legitimate
 *    .conf include paths inside or outside the docroot.
 *
 * Run with: php tests/test-server-rules-hardening.php
 *
 * Exit code 0 = all pass, non-zero = failures.
 *
 * @package W3TC\Tests
 * @since   X.X.X
 */

// Only run when invoked directly; bail under PHPUnit auto-discovery.
if ( realpath( __FILE__ ) !== realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	return;
}

$srh_pass  = 0;
$srh_fail  = 0;
$srh_cases = array();

/**
 * Registers a test result.
 *
 * @param string $label       Human-readable description.
 * @param bool   $expectation Condition that should hold.
 * @param string $detail      Optional failure context.
 */
function srh_assert( string $label, bool $expectation, string $detail = '' ): void {
	global $srh_pass, $srh_fail, $srh_cases;
	if ( $expectation ) {
		$srh_cases[] = array( 'PASS', $label );
		++$srh_pass;
	} else {
		$srh_cases[] = array( 'FAIL', $label . ( $detail ? " | $detail" : '' ) );
		++$srh_fail;
	}
}

/**
 * Load the real classes under test. Both are plain `namespace W3TC` class
 * definitions with no load-time side effects; the methods exercised here
 * touch only PHP built-ins (libxml/simplexml) and Util_Environment::normalize_path.
 */
require_once __DIR__ . '/../Util_Environment.php';
require_once __DIR__ . '/../Util_Rule.php';

/**
 * ---------------------------------------------------------------------------
 * rt9-30 — safe_simplexml_load_string() XXE protections.
 * ---------------------------------------------------------------------------
 */

// Valid XML parses into a usable SimpleXMLElement.
$valid = \W3TC\Util_Environment::safe_simplexml_load_string(
	'<?xml version="1.0"?><urlset><url><loc>https://example.com/a</loc></url></urlset>'
);
srh_assert(
	'[1] well-formed sitemap XML parses',
	$valid instanceof \SimpleXMLElement && 'https://example.com/a' === (string) $valid->url->loc,
	'got ' . var_export( $valid, true )
);

/**
 * XXE: an external-entity reference to a local secret file must NOT expand to
 * the file's contents. Proves external entities are not resolved.
 */
$secret_path = sys_get_temp_dir() . '/w3tc_xxe_' . getmypid() . '_' . bin2hex( random_bytes( 4 ) ) . '.txt';
file_put_contents( $secret_path, 'XXE_SECRET_SENTINEL_8675309' );
$xxe_payload = '<?xml version="1.0"?>'
	. '<!DOCTYPE r [ <!ENTITY xxe SYSTEM "file://' . $secret_path . '"> ]>'
	. '<r>&xxe;</r>';
$xxe_result = \W3TC\Util_Environment::safe_simplexml_load_string( $xxe_payload );
$xxe_string = ( $xxe_result instanceof \SimpleXMLElement ) ? (string) $xxe_result : '';
srh_assert(
	'[2] external-entity (file://) is NOT expanded — no local-file disclosure',
	false === strpos( $xxe_string, 'XXE_SECRET_SENTINEL' ),
	'parsed content leaked the file: "' . $xxe_string . '"'
);
unlink( $secret_path );

// Empty / non-string input returns false without warnings.
srh_assert( '[3] empty string returns false', false === \W3TC\Util_Environment::safe_simplexml_load_string( '' ) );
srh_assert( '[4] non-string input returns false', false === \W3TC\Util_Environment::safe_simplexml_load_string( null ) );

// Malformed XML returns false (errors suppressed, no fatal).
srh_assert(
	'[5] malformed XML returns false (errors suppressed)',
	false === \W3TC\Util_Environment::safe_simplexml_load_string( '<r><unclosed>' )
);

// The libxml error state is restored to the caller's prior setting.
$before = libxml_use_internal_errors( false );
\W3TC\Util_Environment::safe_simplexml_load_string( '<r>ok</r>' );
$after = libxml_use_internal_errors( $before );
srh_assert(
	'[6] caller libxml_use_internal_errors state is restored',
	false === $after,
	'expected restored state false, got ' . var_export( $after, true )
);

/**
 * ---------------------------------------------------------------------------
 * rt9-209 — is_valid_custom_rules_path() containment.
 * ---------------------------------------------------------------------------
 */

$ref = new \ReflectionMethod( '\W3TC\Util_Rule', 'is_valid_custom_rules_path' );
$ref->setAccessible( true );
$valid_path = function ( $p ) use ( $ref ) {
	return (bool) $ref->invoke( null, $p );
};

// Accept: legitimate .conf rules files (the field's documented purpose).
srh_assert( '[7] accepts default-style nginx.conf', true === $valid_path( '/var/www/html/nginx.conf' ) );
srh_assert( '[8] accepts external include path /etc/nginx/conf.d/w3tc.conf', true === $valid_path( '/etc/nginx/conf.d/w3tc.conf' ) );
srh_assert( '[9] accepts uppercase .CONF extension', true === $valid_path( '/etc/nginx/W3TC.CONF' ) );

// Reject: the documented write-to-RCE targets.
srh_assert( '[10] rejects .php payload in docroot', false === $valid_path( '/var/www/html/wp-content/uploads/shell.php' ) );
srh_assert( '[11] rejects extensionless /etc/cron.d target', false === $valid_path( '/etc/cron.d/pwn' ) );
srh_assert( '[12] rejects /etc/sudoers', false === $valid_path( '/etc/sudoers' ) );
srh_assert( '[13] rejects path traversal sequence', false === $valid_path( '/var/www/html/../../etc/cron.d/x.conf' ) );
srh_assert( '[14] rejects null-byte truncation (shell.php\\0.conf)', false === $valid_path( "/var/www/html/shell.php\0.conf" ) );
srh_assert( '[15] rejects empty value (falls back to default)', false === $valid_path( '' ) );
srh_assert( '[16] rejects non-string value', false === $valid_path( array( 'x' ) ) );

/**
 * ---------------------------------------------------------------------------
 * rt9-32 — vendored S3Compatible XML parser hardened (source-level guard).
 * The call is buried in the cURL request/response flow, so we assert the
 * hardening is present in source rather than constructing a full S3 response.
 * ---------------------------------------------------------------------------
 */

$s3_src = file_get_contents( __DIR__ . '/../lib/S3Compatible.php' );

srh_assert(
	'[17] S3Compatible simplexml call passes LIBXML_NONET',
	(bool) preg_match( '/simplexml_load_string\([^;]*LIBXML_NONET/', $s3_src ),
	'expected LIBXML_NONET on the S3 XML parse'
);
srh_assert(
	'[18] S3Compatible has no unhardened simplexml_load_string',
	! preg_match( '/simplexml_load_string\(\s*\$this->response->body\s*\)/', $s3_src ),
	'a bare simplexml_load_string($this->response->body) remains'
);

/**
 * ---------------------------------------------------------------------------
 * Report.
 * ---------------------------------------------------------------------------
 */

echo "\n  Server-config hardening regression (rt9-30 XXE, rt9-209 config.path)\n";
echo '  ' . str_repeat( '-', 70 ) . "\n";
foreach ( $srh_cases as $case ) {
	$mark = 'PASS' === $case[0] ? "\033[32m✔\033[0m" : "\033[31m✗\033[0m";
	printf( "  %s  %-66s %s\n", $mark, $case[1], $case[0] );
}
echo "\n  Total: " . ( $srh_pass + $srh_fail )
	. "  Passed: \033[32m{$srh_pass}\033[0m  Failed: \033[31m{$srh_fail}\033[0m\n\n";

exit( $srh_fail > 0 ? 1 : 0 );
