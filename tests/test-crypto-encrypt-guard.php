<?php
/**
 * Standalone regression test for the Config::encrypt_secrets() pre-pluggable guard.
 *
 * Companion to test-crypto-early-bootstrap.php. That test covers the crypto
 * primitive; this one covers the policy layer: secret-flagged config values
 * must NOT be envelope-encrypted before `wp-includes/pluggable.php` defines
 * `wp_salt()`.
 *
 * The envelope key is derived from `wp_salt('secure_auth')`, which is the only
 * authoritative source of that key. It cannot be reproduced from the wp-config
 * salt constants in the general case — WordPress ignores key/salt values that
 * are duplicated across schemes, honours a `salt` filter, and falls back to
 * DB-generated salts. So encrypting during the early-bootstrap window (page /
 * object-cache drop-ins, or a plugin `run()` that saves config before
 * pluggable.php) can key the envelope with a salt that won't match at decrypt
 * time, collapsing every secret to '' on the next admin read.
 *
 * This test deliberately defines `wp_salt()` to return a value UNRELATED to the
 * salt constants, reproducing the real-world case where the constants cannot
 * reconstruct the runtime salt, and proves the guard is correct regardless.
 *
 * This test verifies:
 *   1. `encrypt_secrets()` leaves a secret-flagged value untouched (no
 *      `enc:v1:` envelope) while `wp_salt()` is unavailable.
 *   2. Once `wp_salt()` is defined, `encrypt_secrets()` wraps the value and it
 *      round-trips — even when `wp_salt()` bears no relation to the constants.
 *
 * Run with: php tests/test-crypto-encrypt-guard.php
 *
 * Exit code 0 = all pass, non-zero = failures.
 *
 * @package W3TC\Tests
 * @since   2.10.0
 */

if ( realpath( __FILE__ ) !== realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	return;
}

// Stand-ins for wp-config.php. Defined BEFORE wp_salt() so encrypt_secrets()
// runs through its pre-pluggable guard. ConfigKeys.php (included by
// secret_keys()) guards on ABSPATH, and secret_keys() needs W3TC_DIR.
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'W3TC_DIR', dirname( __DIR__ ) );
define( 'SECURE_AUTH_KEY', 'ceg-unit-secure-auth-key-0123456789abcdef' );
define( 'SECURE_AUTH_SALT', 'ceg-unit-secure-auth-salt-fedcba9876543210' );
define( 'AUTH_KEY', 'ceg-unit-auth-key-should-not-be-used-here' );

require_once __DIR__ . '/../Util_Crypto.php';
// ConfigKeys.php (included by Config::secret_keys()) references this class at
// include time for a default value; preload it so the standalone run resolves.
require_once __DIR__ . '/../PgCache_QsExempts.php';
require_once __DIR__ . '/../Config.php';

use W3TC\Config;
use W3TC\Util_Crypto;

$ceg_pass = 0;
$ceg_fail = 0;

/**
 * Minimal assert helper (file-unique name per the standalone-test convention).
 *
 * @param bool   $cond  Condition that must hold.
 * @param string $label Human-readable description.
 *
 * @return void
 */
function ceg_assert( $cond, $label ) {
	global $ceg_pass, $ceg_fail;
	if ( $cond ) {
		$ceg_pass++;
		echo "  PASS: $label\n";
	} else {
		$ceg_fail++;
		echo "  FAIL: $label\n";
	}
}

if ( ! Util_Crypto::is_available() ) {
	echo "SKIP: OpenSSL AES-256-CBC not available in this PHP build.\n";
	return;
}

echo "Config::encrypt_secrets() pre-pluggable guard\n";

// 'cdn.cf.secret' carries the 'secret' flag in ConfigKeys.php.
$secret_key = 'cdn.cf.secret';
$plaintext  = 'AKIA-not-a-real-secret-value';

// 1. Pre-pluggable window: wp_salt() undefined → value must be left as-is.
ceg_assert( ! function_exists( 'wp_salt' ), 'precondition: wp_salt() is undefined (early-bootstrap window)' );

$data = array( $secret_key => $plaintext );
Config::encrypt_secrets( $data );

ceg_assert( $plaintext === $data[ $secret_key ], 'secret left untouched when wp_salt() is unavailable (no mis-keyed envelope)' );
ceg_assert( ! Util_Crypto::is_envelope( $data[ $secret_key ] ), 'no enc:v1: envelope produced pre-pluggable' );

// 2. Simulate pluggable.php loading. Return a salt UNRELATED to the constants
// (the duplicated-keys / DB-salt reality), so the test asserts correctness
// independent of any constant-based reproduction.
if ( ! function_exists( 'wp_salt' ) ) {
	/**
	 * Stub of WordPress's wp_salt(): a runtime salt unrelated to the constants.
	 *
	 * @param string $scheme Salt scheme.
	 *
	 * @return string
	 */
	function wp_salt( $scheme = 'auth' ) {
		return 'db-generated-secure-auth-salt-unrelated-to-wpconfig-constants';
	}
}

ceg_assert( function_exists( 'wp_salt' ), 'post-condition: wp_salt() now defined (post-pluggable window)' );

$data_post = array( $secret_key => $plaintext );
Config::encrypt_secrets( $data_post );

ceg_assert( Util_Crypto::is_envelope( $data_post[ $secret_key ] ), 'secret is encrypted once wp_salt() is available' );
ceg_assert( $plaintext === Util_Crypto::envelope_decrypt( $data_post[ $secret_key ] ), 'post-pluggable envelope round-trips under wp_salt()' );

echo "\n$ceg_pass passed, $ceg_fail failed\n";
exit( $ceg_fail > 0 ? 1 : 0 );