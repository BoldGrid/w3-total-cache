<?php
/**
 * Standalone regression test for Util_Crypto early-bootstrap key derivation.
 *
 * Pins the 2.10.0 fix for stored values not resolving at runtime: the
 * page-cache (`advanced-cache.php`) and object-cache drop-ins read stored
 * `enc:v1:...` values BEFORE `wp-includes/pluggable.php` defines `wp_salt()`.
 * The envelope key must be derivable in that pre-pluggable window with the SAME
 * bytes used at encrypt time (admin context, `wp_salt()` available) — otherwise
 * the derived key differs and the stored value cannot be recovered.
 *
 * This test verifies:
 *   1. A value enveloped while `wp_salt()` is UNAVAILABLE (constants branch)
 *      round-trips immediately (early-bootstrap self-consistency).
 *   2. That same envelope still decrypts once `wp_salt()` IS defined to the
 *      value it returns in the common case (`SECURE_AUTH_KEY . SECURE_AUTH_SALT`).
 *      Because the derivation is deterministic in the salt string, identical
 *      salts ⇒ identical keys ⇒ envelopes cross-decrypt in BOTH directions —
 *      i.e. a value stored in admin resolves at cache bootstrap.
 *   3. `salt_constants_available()` reflects the wp-config salt constants.
 *   4. Legacy plaintext passes through unchanged; a bad-MAC envelope is rejected.
 *
 * Run with: php tests/test-crypto-early-bootstrap.php
 *
 * Exit code 0 = all pass, non-zero = failures.
 *
 * @package W3TC\Tests
 * @since   2.10.0
 */

if ( realpath( __FILE__ ) !== realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	return;
}

// Salt constants that stand in for wp-config.php. Defined BEFORE wp_salt() so
// the encrypt below exercises the pre-pluggable constants branch.
define( 'SECURE_AUTH_KEY', 'ceb-unit-secure-auth-key-0123456789abcdef' );
define( 'SECURE_AUTH_SALT', 'ceb-unit-secure-auth-salt-fedcba9876543210' );
define( 'AUTH_KEY', 'ceb-unit-auth-key-should-not-be-used-here' );

require_once __DIR__ . '/../Util_Crypto.php';

use W3TC\Util_Crypto;

$ceb_pass = 0;
$ceb_fail = 0;

/**
 * Minimal assert helper (file-unique name per the standalone-test convention).
 *
 * @param bool   $cond  Condition that must hold.
 * @param string $label Human-readable description.
 *
 * @return void
 */
function ceb_assert( $cond, $label ) {
	global $ceb_pass, $ceb_fail;
	if ( $cond ) {
		$ceb_pass++;
		echo "  PASS: $label\n";
	} else {
		$ceb_fail++;
		echo "  FAIL: $label\n";
	}
}

if ( ! Util_Crypto::is_available() ) {
	echo "SKIP: OpenSSL AES-256-CBC not available in this PHP build.\n";
	return;
}

echo "Util_Crypto early-bootstrap key derivation\n";

// 1. Constants branch is active (wp_salt not yet defined) and reports ready.
ceb_assert( ! function_exists( 'wp_salt' ), 'precondition: wp_salt() is undefined (early-bootstrap window)' );
ceb_assert( Util_Crypto::salt_constants_available(), 'salt_constants_available() is true with the constants defined' );

// 1. Envelope a value in the pre-pluggable window, then decrypt it there.
$plaintext     = 'round-trip-sample-value';
$envelope_boot = Util_Crypto::envelope_encrypt( $plaintext );

ceb_assert( Util_Crypto::is_envelope( $envelope_boot ), 'envelope_encrypt() produced an enc:v1: envelope pre-pluggable' );
ceb_assert( $plaintext === Util_Crypto::envelope_decrypt( $envelope_boot ), 'pre-pluggable envelope round-trips (early-bootstrap self-consistency)' );

// 4. Legacy plaintext and bad-MAC behaviour (independent of the salt branch).
ceb_assert( 'legacy-plain' === Util_Crypto::envelope_decrypt( 'legacy-plain' ), 'legacy plaintext passes through unchanged' );
ceb_assert( false === Util_Crypto::envelope_decrypt( 'enc:v1:' . base64_encode( 'too-short' ) ), 'malformed/short envelope returns false' );

$bad_mac = 'enc:v1:' . base64_encode( str_repeat( "\x00", 64 ) ); // valid length, invalid MAC
ceb_assert( false === Util_Crypto::envelope_decrypt( $bad_mac ), 'bad-MAC envelope is rejected' );

// 2. Now simulate pluggable.php loading: define wp_salt() to the value it
// returns in the common case. The envelope made via the constants branch must
// still decrypt via the wp_salt branch — proving both derive an identical key.
if ( ! function_exists( 'wp_salt' ) ) {
	/**
	 * Stub of WordPress's wp_salt() for the 'secure_auth' scheme.
	 *
	 * @param string $scheme Salt scheme.
	 *
	 * @return string
	 */
	function wp_salt( $scheme = 'auth' ) {
		return SECURE_AUTH_KEY . SECURE_AUTH_SALT;
	}
}

ceb_assert( function_exists( 'wp_salt' ), 'post-condition: wp_salt() now defined (post-pluggable window)' );
ceb_assert(
	$plaintext === Util_Crypto::envelope_decrypt( $envelope_boot ),
	'constants-branch envelope decrypts under wp_salt() — key match across the pluggable boundary (THE fix)'
);

// 5. Encrypt under the wp_salt branch and confirm it round-trips too.
$envelope_admin = Util_Crypto::envelope_encrypt( $plaintext );
ceb_assert( $plaintext === Util_Crypto::envelope_decrypt( $envelope_admin ), 'wp_salt-branch envelope round-trips' );

echo "\n$ceb_pass passed, $ceb_fail failed\n";
exit( $ceb_fail > 0 ? 1 : 0 );
