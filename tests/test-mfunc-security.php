<?php
/**
 * Standalone regression test for the mfunc/mclude security fix.
 *
 * Tests both the sanitization layer (Generic_Plugin) and the execution layer
 * (PgCache_ContentGrabber) against every meaningful bypass variant.
 *
 * Run with: php tests/test-mfunc-security.php
 *
 * Exit code 0 = all pass, non-zero = failures.
 *
 * @package W3TC\Tests
 * @since   X.X.X
 */

// Only run when invoked directly (php tests/test-mfunc-security.php).
// When PHPUnit includes this file during test discovery, return immediately
// so that the top-level echo/exit calls do not break the suite.
if ( realpath( __FILE__ ) !== realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	return;
}

define( 'W3TC_DYNAMIC_SECURITY', 'test' );

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

$pass  = 0;
$fail  = 0;
$cases = array();

/**
 * Registers a test result.
 *
 * @param string $label       Human-readable description.
 * @param bool   $expectation True when the condition we are asserting should hold.
 * @param string $detail      Optional extra context on failure.
 */
function assert_true( string $label, bool $expectation, string $detail = '' ): void {
	global $pass, $fail, $cases;
	if ( $expectation ) {
		$cases[] = array( 'PASS', $label );
		++$pass;
	} else {
		$cases[] = array( 'FAIL', $label . ( $detail ? " | $detail" : '' ) );
		++$fail;
	}
}

// ---------------------------------------------------------------------------
// Regex builders (mirrors the production code exactly)
// ---------------------------------------------------------------------------

/**
 * Returns the _parse_dynamic mfunc pattern (production).
 */
function parse_dynamic_mfunc_pattern(): string {
	$security = preg_quote( W3TC_DYNAMIC_SECURITY, '~' );
	return '~<!--\s*mfunc\s+' . $security . '(.*)-->(.*)<!--\s*/mfunc\s+' . $security . '\s*-->~Uis';
}

/**
 * Returns the _parse_dynamic mclude pattern (production).
 */
function parse_dynamic_mclude_pattern(): string {
	$security = preg_quote( W3TC_DYNAMIC_SECURITY, '~' );
	return '~<!--\s*mclude\s+' . $security . '(.*)-->(.*)<!--\s*/mclude\s+' . $security . '\s*-->~Uis';
}

/**
 * Returns the _has_dynamic pattern (production).
 */
function has_dynamic_pattern(): string {
	$security = preg_quote( W3TC_DYNAMIC_SECURITY, '~' );
	return '~<!--\s*m(func|clude)\s+' . $security . '(.*)-->(.*)<!--\s*/m(func|clude)\s+' . $security . '\s*-->~Uis';
}

/**
 * Returns the sanitization patterns (production).
 *
 * @return string[]
 */
function sanitize_patterns(): array {
	return array(
		'~<!--\s*mfunc\s*\S+.*?-->(.*?)<!--\s*/mfunc\s*\S+.*?\s*-->~Uis',
		'~<!--\s*mclude\s*\S+.*?-->(.*?)<!--\s*/mclude\s*\S+.*?\s*-->~Uis',
	);
}

/**
 * Simulates what strip_dynamic_fragment_tags_from_string does.
 *
 * @param string $value Input string.
 * @return string Sanitized string.
 */
function sanitize( string $value ): string {
	$original = $value;

	$value = preg_replace_callback(
		sanitize_patterns(),
		function ( array $m ): string {
			return $m[1]; // keep only content between tags
		},
		$value
	);

	if ( null === $value ) {
		return $original;
	}

	if ( 1 === (int) W3TC_DYNAMIC_SECURITY ) {
		return $value;
	}

	return str_replace( W3TC_DYNAMIC_SECURITY, '', $value );
}

// ---------------------------------------------------------------------------
// ── SECTION 1: Sanitization layer (Generic_Plugin)
// ──   These tags must be STRIPPED (mfunc wrappers removed) before storage.
// ---------------------------------------------------------------------------

echo "\n=== SANITIZATION (Generic_Plugin) ===\n\n";

$token = W3TC_DYNAMIC_SECURITY; // 'test'

// 1a. Reported bypass exactly as described by Patchstack:
//     <!-- mfunctetestst -->phpinfo();<!-- /mfunctetestst -->
//     After old str_replace: mfunctetestst → mfunctest (crafted to morph into a valid tag).
$bypass_reported = '<!-- mfunc' . 'te' . $token . 'st -->phpinfo();<!-- /mfunc' . 'te' . $token . 'st -->';
$result          = sanitize( $bypass_reported );
assert_true(
	'[1a] Reported bypass tag is stripped by sanitization',
	false === strpos( $result, 'mfunc' ),
	"remaining: $result"
);
assert_true(
	'[1a] Reported bypass: security token absent after sanitize',
	false === strpos( $result, $token ),
	"remaining: $result"
);

// 1b. Direct no-space concatenation: <!-- mfunctest -->code<!-- /mfunctest -->
//     (the morphed form that old code would execute via eval)
$no_space = '<!-- mfunc' . $token . ' -->code<!-- /mfunc' . $token . ' -->';
$result   = sanitize( $no_space );
assert_true(
	'[1b] No-space mfunc+token tag stripped',
	false === strpos( $result, 'mfunc' ),
	"remaining: $result"
);

// 1c. Token embedded with padding on both sides (generic embedding variant):
//     <!-- mfuncAAAtestBBB -->code<!-- /mfuncAAAtestBBB -->
$padded = '<!-- mfuncAAA' . $token . 'BBB -->code<!-- /mfuncAAA' . $token . 'BBB -->';
$result = sanitize( $padded );
assert_true(
	'[1c] Padded-embedding mfunc tag stripped',
	false === strpos( $result, 'mfunc' ),
	"remaining: $result"
);

// 1d. Legitimate well-formed tag must be stripped:
//     <!-- mfunc test code --><!-- /mfunc test -->
$legit  = '<!-- mfunc ' . $token . " -->echo 'ok';<!-- /mfunc " . $token . ' -->';
$result = sanitize( $legit );
assert_true(
	'[1d] Legitimate spaced mfunc tag stripped',
	false === strpos( $result, 'mfunc' ),
	"remaining: $result"
);

// 1e. mclude variant of reported bypass
$bypass_mclude = '<!-- mclude' . 'te' . $token . 'st -->file.php<!-- /mclude' . 'te' . $token . 'st -->';
$result        = sanitize( $bypass_mclude );
assert_true(
	'[1e] mclude bypass variant stripped',
	false === strpos( $result, 'mclude' ),
	"remaining: $result"
);

// 1f. Multiline content inside the bypass tag
$multiline = "<!-- mfunc" . "te{$token}st -->\n\$x = 1;\necho \$x;\n<!-- /mfunc" . "te{$token}st -->";
$result    = sanitize( $multiline );
assert_true(
	'[1f] Multiline bypass tag stripped',
	false === strpos( $result, 'mfunc' ),
	"remaining: $result"
);

// 1g. Surrounding safe text is preserved
$with_text = 'Hello world. ' . $bypass_reported . ' Goodbye world.';
$result    = sanitize( $with_text );
assert_true(
	'[1g] Safe surrounding text preserved after sanitize',
	false !== strpos( $result, 'Hello world.' ) && false !== strpos( $result, 'Goodbye world.' ),
	"remaining: $result"
);

// ---------------------------------------------------------------------------
// ── SECTION 2: Execution layer — _parse_dynamic / _has_dynamic
// ──   Malicious tags must NOT match (not executed, not flagged as dynamic).
// ──   Legitimate tags MUST match.
// ---------------------------------------------------------------------------

echo "\n=== EXECUTION LAYER (PgCache_ContentGrabber) ===\n\n";

$mfunc_pat  = parse_dynamic_mfunc_pattern();
$mclude_pat = parse_dynamic_mclude_pattern();
$hasdyn_pat = has_dynamic_pattern();

// ── 2a. Reported bypass must NOT match execution regex ──
$bypass_reported = '<!-- mfunc' . 'te' . $token . 'st -->phpinfo();<!-- /mfunc' . 'te' . $token . 'st -->';
assert_true(
	'[2a] Reported bypass: _parse_dynamic mfunc pattern does NOT match',
	0 === preg_match( $mfunc_pat, $bypass_reported )
);
assert_true(
	'[2a] Reported bypass: _has_dynamic pattern does NOT match',
	0 === preg_match( $hasdyn_pat, $bypass_reported )
);

// ── 2b. Morphed form (what str_replace used to produce) must NOT match ──
//        <!-- mfunctest -->code<!-- /mfunctest -->
$morphed = '<!-- mfunc' . $token . ' -->phpinfo();<!-- /mfunc' . $token . ' -->';
assert_true(
	'[2b] Morphed no-space tag (mfunctest): _parse_dynamic does NOT match',
	0 === preg_match( $mfunc_pat, $morphed )
);
assert_true(
	'[2b] Morphed no-space tag (mfunctest): _has_dynamic does NOT match',
	0 === preg_match( $hasdyn_pat, $morphed )
);

// ── 2c. Token embedded mid-word must NOT match ──
$embedded = '<!-- mfuncAAA' . $token . 'BBB -->code<!-- /mfuncAAA' . $token . 'BBB -->';
assert_true(
	'[2c] Embedded-token tag: _parse_dynamic does NOT match',
	0 === preg_match( $mfunc_pat, $embedded )
);

// ── 2d. Token with only trailing chars (e.g. mfunctestXXX) must NOT match ──
$trailing = '<!-- mfunc' . $token . 'XXX -->code<!-- /mfunc' . $token . 'XXX -->';
assert_true(
	'[2d] Trailing-chars tag: _parse_dynamic does NOT match',
	0 === preg_match( $mfunc_pat, $trailing )
);

// ── 2e. Missing security token entirely must NOT match ──
$no_token = '<!-- mfunc -->phpinfo();<!-- /mfunc -->';
assert_true(
	'[2e] Tag without any token: _parse_dynamic does NOT match',
	0 === preg_match( $mfunc_pat, $no_token )
);
assert_true(
	'[2e] Tag without any token: _has_dynamic does NOT match',
	0 === preg_match( $hasdyn_pat, $no_token )
);

// ── 2f. Wrong token must NOT match ──
$wrong_token = '<!-- mfunc wrongtoken -->code<!-- /mfunc wrongtoken -->';
assert_true(
	'[2f] Wrong-token tag: _parse_dynamic does NOT match',
	0 === preg_match( $mfunc_pat, $wrong_token )
);

// ── 2g. Legitimate inline-code form MUST match ──
//        <!-- mfunc test phpinfo(); --><!-- /mfunc test -->
$legit_inline = '<!-- mfunc ' . $token . ' phpinfo(); --><!-- /mfunc ' . $token . ' -->';
assert_true(
	'[2g] Legitimate inline-code mfunc: _parse_dynamic MATCHES',
	1 === preg_match( $mfunc_pat, $legit_inline )
);
assert_true(
	'[2g] Legitimate inline-code mfunc: _has_dynamic MATCHES',
	1 === preg_match( $hasdyn_pat, $legit_inline )
);

// ── 2h. Legitimate between-tags form MUST match ──
//        <!-- mfunc test -->phpinfo();<!-- /mfunc test -->
$legit_between = '<!-- mfunc ' . $token . ' -->phpinfo();<!-- /mfunc ' . $token . ' -->';
assert_true(
	'[2h] Legitimate between-tags mfunc: _parse_dynamic MATCHES',
	1 === preg_match( $mfunc_pat, $legit_between )
);
assert_true(
	'[2h] Legitimate between-tags mfunc: _has_dynamic MATCHES',
	1 === preg_match( $hasdyn_pat, $legit_between )
);

// ── 2i. Legitimate mclude MUST match ──
$legit_mclude = '<!-- mclude ' . $token . ' -->file.php<!-- /mclude ' . $token . ' -->';
assert_true(
	'[2i] Legitimate mclude: _parse_dynamic MATCHES',
	1 === preg_match( $mclude_pat, $legit_mclude )
);
assert_true(
	'[2i] Legitimate mclude: _has_dynamic MATCHES',
	1 === preg_match( $hasdyn_pat, $legit_mclude )
);

// ── 2j. Extra whitespace around token MUST still match (tabs, multiple spaces) ──
$extra_ws = "<!-- mfunc  \t " . $token . "  \t -->phpinfo();<!-- /mfunc\t" . $token . ' -->';
assert_true(
	'[2j] Extra whitespace around token: _parse_dynamic MATCHES',
	1 === preg_match( $mfunc_pat, $extra_ws )
);

// ── 2k. mclude reported-bypass variant must NOT match ──
$mclude_bypass = '<!-- mclude' . 'te' . $token . 'st -->file.php<!-- /mclude' . 'te' . $token . 'st -->';
assert_true(
	'[2k] mclude bypass: _parse_dynamic does NOT match',
	0 === preg_match( $mclude_pat, $mclude_bypass )
);
assert_true(
	'[2k] mclude bypass: _has_dynamic does NOT match',
	0 === preg_match( $hasdyn_pat, $mclude_bypass )
);

// ---------------------------------------------------------------------------
// ── SECTION 3: End-to-end simulation of the full reported attack chain
// ──   Simulates: submit → sanitize → store → serve from cache → parse
// ---------------------------------------------------------------------------

echo "\n=== END-TO-END ATTACK CHAIN SIMULATION ===\n\n";

// Step 1: Attacker submits the bypass payload.
$submitted = '<!-- mfunc' . 'te' . $token . 'st -->phpinfo();<!-- /mfunc' . 'te' . $token . 'st -->';

// Step 2: Sanitization runs (Generic_Plugin::strip_dynamic_fragment_tags_from_string).
$stored = sanitize( $submitted );

// Step 3: Page renders — stored value is in the buffer.
// Step 4: _parse_dynamic runs over the buffer.
$executed_mfunc = false;
$buffer         = $stored;
$buffer         = preg_replace_callback(
	parse_dynamic_mfunc_pattern(),
	function () use ( &$executed_mfunc ): string {
		$executed_mfunc = true;
		return 'EXECUTED';
	},
	$buffer
);

assert_true(
	'[3a] Full chain: _parse_dynamic does NOT execute payload after sanitize',
	false === $executed_mfunc,
	"buffer after parse: $buffer"
);

// Also verify _has_dynamic would not flag the stored value.
assert_true(
	'[3b] Full chain: _has_dynamic does NOT flag stored payload',
	0 === preg_match( has_dynamic_pattern(), $stored ),
	"stored value: $stored"
);

// ---------------------------------------------------------------------------
// ── SECTION 4: Registered-callback dispatcher + HMAC envelope (eval-rce fix)
// ──   Exercises the actual PgCache_ContentGrabber methods, not just regex.
// ---------------------------------------------------------------------------

echo "\n=== DISPATCHER + HMAC (PgCache_ContentGrabber, eval-rce fix) ===\n\n";

// Minimal stub for WordPress functions the dispatcher consults.
if ( ! function_exists( 'apply_filters' ) ) {
	$GLOBALS['__test_dynamic_callbacks'] = array();
	function apply_filters( $hook, $value ) {
		if ( 'w3tc_dynamic_callbacks' === $hook ) {
			return $GLOBALS['__test_dynamic_callbacks'];
		}
		return $value;
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
	}
}
// Stubs for the rate-limiter + deprecation-notice path. Each test that cares
// about the contents resets `__test_did_wrong` / `__test_transients` first.
if ( ! function_exists( '_doing_it_wrong' ) ) {
	$GLOBALS['__test_did_wrong'] = array();
	function _doing_it_wrong( $fn, $msg, $ver ) {
		$GLOBALS['__test_did_wrong'][] = array( 'fn' => $fn, 'msg' => $msg, 'ver' => $ver );
	}
}
if ( ! function_exists( 'get_site_transient' ) ) {
	$GLOBALS['__test_transients'] = array();
	function get_site_transient( $key ) {
		return $GLOBALS['__test_transients'][ $key ] ?? false;
	}
	function set_site_transient( $key, $value, $ttl ) {
		$GLOBALS['__test_transients'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( $scheme = 'auth' ) {
		return 'test-mfunc-salt-' . $scheme;
	}
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

// Load real W3TC namespace stubs (Dispatcher / Util_Environment / config) so
// PgCache_ContentGrabber's constructor can run in standalone PHP.  Using a
// separate `namespace W3TC;` file rather than eval() keeps this regression
// test free of the very primitive (eval) it exists to prove was removed.
if ( ! class_exists( 'W3TC\\Dispatcher' ) ) {
	require_once __DIR__ . '/mfunc-security-stubs.php';
}

// Load the class under test.  W3TC_DYNAMIC_SECURITY is already defined above.
require_once __DIR__ . '/../PgCache_ContentGrabber.php';

$grabber = new \W3TC\PgCache_ContentGrabber();

// Reset the registry for each subsection.
$reset_callbacks = function () {
	$GLOBALS['__test_dynamic_callbacks'] = array();
};

// Register a known-safe callback for the dispatch tests.
$register_callback = function ( $slug, $fn ) {
	$GLOBALS['__test_dynamic_callbacks'][ $slug ] = $fn;
};

// ── 4a. Raw-PHP mfunc payload (legacy eval form) is REFUSED ──
$reset_callbacks();
$raw_php = '<!-- mfunc ' . $token . ' phpinfo(); --><!-- /mfunc ' . $token . ' -->';
$result  = $grabber->_parse_dynamic( $raw_php );
assert_true(
	'[4a] Raw-PHP mfunc payload is refused (no eval, error sentinel)',
	false === strpos( $result, 'phpinfo' )
		&& false !== strpos( $result, 'refused' ),
	"result: $result"
);

// ── 4b. Raw-PHP mfunc between-tags form is REFUSED ──
$reset_callbacks();
$raw_between = '<!-- mfunc ' . $token . ' -->phpinfo();<!-- /mfunc ' . $token . ' -->';
$result      = $grabber->_parse_dynamic( $raw_between );
assert_true(
	'[4b] Raw-PHP between-tags mfunc is refused',
	false === strpos( $result, 'phpinfo' )
		&& false !== strpos( $result, 'refused' ),
	"result: $result"
);

// ── 4c. mclude with attacker-controlled file path is REFUSED (rt9-232 closed) ──
$reset_callbacks();
$lfi      = '<!-- mclude ' . $token . ' ../../../../etc/passwd --><!-- /mclude ' . $token . ' -->';
$result   = $grabber->_parse_dynamic( $lfi );
assert_true(
	'[4c] mclude with file-path payload is refused (rt9-232 closed)',
	false === strpos( $result, 'root:' )
		&& false !== strpos( $result, 'refused' ),
	"result: $result"
);

// ── 4d. mfunc with call:slug but no HMAC is REFUSED ──
$reset_callbacks();
$register_callback( 'render_user', function ( $args ) {
	return 'USER:' . ( $args['name'] ?? '' );
} );
$unsigned = '<!-- mfunc ' . $token . ' call:render_user {"name":"alice"} --><!-- /mfunc ' . $token . ' -->';
$result   = $grabber->_parse_dynamic( $unsigned );
assert_true(
	'[4d] mfunc with call:slug but no HMAC is refused',
	false === strpos( $result, 'USER:alice' )
		&& false !== strpos( $result, 'refused' ),
	"result: $result"
);

// ── 4e. mfunc with WRONG HMAC is REFUSED (cache-poisoning defense) ──
$reset_callbacks();
$register_callback( 'render_user', function ( $args ) {
	return 'USER:' . ( $args['name'] ?? '' );
} );
$wrong_hmac = '<!-- mfunc ' . $token . ' call:render_user {"name":"alice"} hmac:' . str_repeat( '0', 64 ) . ' --><!-- /mfunc ' . $token . ' -->';
$result     = $grabber->_parse_dynamic( $wrong_hmac );
assert_true(
	'[4e] mfunc with wrong HMAC is refused (cache-poisoning defense)',
	false === strpos( $result, 'USER:alice' )
		&& false !== strpos( $result, 'HMAC mismatch' ),
	"result: $result"
);

// ── 4f. mfunc with correct HMAC + registered slug DISPATCHES ──
$reset_callbacks();
$register_callback( 'render_user', function ( $args ) {
	return 'USER:' . ( $args['name'] ?? '' );
} );
$args_json   = '{"name":"alice"}';
$good_hmac   = $grabber->_dynamic_hmac( 'mfunc', 'render_user', $args_json );
$good_tag    = '<!-- mfunc ' . $token . ' call:render_user ' . $args_json . ' hmac:' . $good_hmac . ' --><!-- /mfunc ' . $token . ' -->';
$result      = $grabber->_parse_dynamic( $good_tag );
assert_true(
	'[4f] mfunc with correct HMAC + registered slug dispatches',
	false !== strpos( $result, 'USER:alice' ),
	"result: $result"
);

// ── 4g. mfunc with correct HMAC but UNREGISTERED slug is REFUSED ──
$reset_callbacks();
// Register an unrelated callback so the registry is non-empty, then dispatch a different slug.
$register_callback( 'other_cb', function ( $args ) {
	return 'OTHER';
} );
$bad_slug    = 'not_registered';
$args_json   = '{}';
$hmac_for_bad = $grabber->_dynamic_hmac( 'mfunc', $bad_slug, $args_json );
$bad_tag     = '<!-- mfunc ' . $token . ' call:' . $bad_slug . ' ' . $args_json . ' hmac:' . $hmac_for_bad . ' --><!-- /mfunc ' . $token . ' -->';
$result      = $grabber->_parse_dynamic( $bad_tag );
assert_true(
	'[4g] mfunc with valid HMAC but unregistered slug is refused',
	false === strpos( $result, 'OTHER' )
		&& false !== strpos( $result, 'not registered' ),
	"result: $result"
);

// ── 4h. mclude with valid HMAC + UNREGISTERED slug is REFUSED (rt9-232 part 2) ──
$reset_callbacks();
$register_callback( 'other_cb', function ( $args ) {
	return 'OTHER';
} );
$bad_mclude_slug = 'inc_passwd';
$args_json       = '{}';
$mclude_hmac     = $grabber->_dynamic_hmac( 'mclude', $bad_mclude_slug, $args_json );
$mclude_tag      = '<!-- mclude ' . $token . ' call:' . $bad_mclude_slug . ' ' . $args_json . ' hmac:' . $mclude_hmac . ' --><!-- /mclude ' . $token . ' -->';
$result          = $grabber->_parse_dynamic( $mclude_tag );
assert_true(
	'[4h] mclude with valid HMAC but unregistered slug is refused',
	false === strpos( $result, 'OTHER' )
		&& false !== strpos( $result, 'not registered' ),
	"result: $result"
);

// ── 4i. mclude with correct HMAC + registered slug DISPATCHES ──
$reset_callbacks();
$register_callback( 'render_inc', function ( $args ) {
	return 'INC:' . ( $args['part'] ?? '' );
} );
$args_json    = '{"part":"header"}';
$mclude_hmac  = $grabber->_dynamic_hmac( 'mclude', 'render_inc', $args_json );
$mclude_good  = '<!-- mclude ' . $token . ' call:render_inc ' . $args_json . ' hmac:' . $mclude_hmac . ' --><!-- /mclude ' . $token . ' -->';
$result       = $grabber->_parse_dynamic( $mclude_good );
assert_true(
	'[4i] mclude with correct HMAC + registered slug dispatches',
	false !== strpos( $result, 'INC:header' ),
	"result: $result"
);

// ── 4j. Empty callback registry: even a perfectly-signed tag is REFUSED ──
$reset_callbacks(); // empty registry
$args_json   = '{}';
$hmac        = $grabber->_dynamic_hmac( 'mfunc', 'render_user', $args_json );
$tag         = '<!-- mfunc ' . $token . ' call:render_user ' . $args_json . ' hmac:' . $hmac . ' --><!-- /mfunc ' . $token . ' -->';
$result      = $grabber->_parse_dynamic( $tag );
assert_true(
	'[4j] Empty callback registry refuses dispatch (no callbacks registered)',
	false !== strpos( $result, 'registry is empty' ),
	"result: $result"
);

// ── 4k. _sign_dynamic_tags adds an HMAC envelope to a call:slug tag ──
$reset_callbacks();
$unsigned_call = '<!-- mfunc ' . $token . ' call:render_user {"name":"bob"} --><!-- /mfunc ' . $token . ' -->';
$signed        = $grabber->_sign_dynamic_tags( $unsigned_call );
$expected_hmac = $grabber->_dynamic_hmac( 'mfunc', 'render_user', '{"name":"bob"}' );
assert_true(
	'[4k] _sign_dynamic_tags adds hmac:<hex> envelope to call:slug tag',
	false !== strpos( $signed, 'hmac:' . $expected_hmac ),
	"signed: $signed"
);

// ── 4l. _sign_dynamic_tags leaves raw-PHP tags unsigned (will be refused later) ──
$reset_callbacks();
$raw           = '<!-- mfunc ' . $token . ' phpinfo(); --><!-- /mfunc ' . $token . ' -->';
$signed_raw    = $grabber->_sign_dynamic_tags( $raw );
assert_true(
	'[4l] _sign_dynamic_tags leaves raw-PHP tags unsigned (no hmac: added)',
	false === strpos( $signed_raw, 'hmac:' ),
	"signed_raw: $signed_raw"
);

// ── 4m. _sign_dynamic_tags is idempotent: signing twice does not double-add HMAC ──
$reset_callbacks();
$once  = $grabber->_sign_dynamic_tags( $unsigned_call );
$twice = $grabber->_sign_dynamic_tags( $once );
assert_true(
	'[4m] _sign_dynamic_tags is idempotent (no double-hmac)',
	$once === $twice,
	"once vs twice differ:\nonce: $once\ntwice: $twice"
);

// ── 4n. End-to-end: register → sign → dispatch yields callback output ──
$reset_callbacks();
$register_callback( 'render_user', function ( $args ) {
	return 'HELLO:' . ( $args['name'] ?? '' );
} );
$e2e_raw      = '<!-- mfunc ' . $token . ' call:render_user {"name":"world"} --><!-- /mfunc ' . $token . ' -->';
$e2e_signed   = $grabber->_sign_dynamic_tags( $e2e_raw );
$e2e_executed = $grabber->_parse_dynamic( $e2e_signed );
assert_true(
	'[4n] End-to-end sign → dispatch yields callback output',
	false !== strpos( $e2e_executed, 'HELLO:world' ),
	"result: $e2e_executed"
);

// ── 4n.body. Body-form (descriptor between tags) is signed by _sign_dynamic_tags ──
//        Emitters can place the `call:slug` descriptor in the body too — the
//        signer must reach it there, otherwise dispatch will refuse a safe tag.
$reset_callbacks();
$body_raw      = '<!-- mfunc ' . $token . ' -->call:render_user {"name":"bob"}<!-- /mfunc ' . $token . ' -->';
$body_signed   = $grabber->_sign_dynamic_tags( $body_raw );
$expected_body_hmac = $grabber->_dynamic_hmac( 'mfunc', 'render_user', '{"name":"bob"}' );
assert_true(
	'[4n.body] _sign_dynamic_tags signs body-form call:slug tags',
	false !== strpos( $body_signed, 'hmac:' . $expected_body_hmac ),
	"signed: $body_signed"
);

// ── 4n.body-e2e. End-to-end: register → sign body-form → dispatch ──
$reset_callbacks();
$register_callback( 'render_user', function ( $args ) {
	return 'BODY:' . ( $args['name'] ?? '' );
} );
$body_e2e_raw      = '<!-- mfunc ' . $token . ' -->call:render_user {"name":"carol"}<!-- /mfunc ' . $token . ' -->';
$body_e2e_signed   = $grabber->_sign_dynamic_tags( $body_e2e_raw );
$body_e2e_executed = $grabber->_parse_dynamic( $body_e2e_signed );
assert_true(
	'[4n.body-e2e] Body-form sign → dispatch yields callback output',
	false !== strpos( $body_e2e_executed, 'BODY:carol' ),
	"result: $body_e2e_executed"
);

// ── 4n.body-idem. Body-form signing is idempotent ──
$reset_callbacks();
$body_once   = $grabber->_sign_dynamic_tags( $body_raw );
$body_twice  = $grabber->_sign_dynamic_tags( $body_once );
assert_true(
	'[4n.body-idem] Body-form _sign_dynamic_tags is idempotent (no double-hmac)',
	$body_once === $body_twice,
	"once vs twice differ:\nonce: $body_once\ntwice: $body_twice"
);

// ── 4n.body-nows. Tags without whitespace between token and `-->` are signed.
//        Parse / has_dynamic accept `<!-- mfunc TOKEN-->call:slug...-->`,
//        so sign must accept the same set or dispatch refuses a safe tag.
$reset_callbacks();
$register_callback( 'render_user', function ( $args ) {
	return 'NOWS:' . ( $args['name'] ?? '' );
} );
$nows_raw      = '<!-- mfunc ' . $token . '-->call:render_user {"name":"dave"}<!-- /mfunc ' . $token . ' -->';
$nows_signed   = $grabber->_sign_dynamic_tags( $nows_raw );
$nows_executed = $grabber->_parse_dynamic( $nows_signed );
assert_true(
	'[4n.body-nows] Body-form without space before `-->` is signed + dispatched',
	false !== strpos( $nows_executed, 'NOWS:dave' ),
	"result: $nows_executed"
);

// ── 4n.kind. Dispatcher invokes callbacks with ($args, $kind) — the documented
//        contract. A callback that asserts on $kind must receive the right value.
$reset_callbacks();
$register_callback( 'render_kind', function ( $args, $kind ) {
	return 'KIND[' . $kind . ']:' . ( $args['x'] ?? '' );
} );
$kind_args  = '{"x":"y"}';
$kind_hmac  = $grabber->_dynamic_hmac( 'mfunc', 'render_kind', $kind_args );
$kind_tag   = '<!-- mfunc ' . $token . ' call:render_kind ' . $kind_args . ' hmac:' . $kind_hmac . ' --><!-- /mfunc ' . $token . ' -->';
$kind_out   = $grabber->_parse_dynamic( $kind_tag );
assert_true(
	'[4n.kind] Callback receives $kind as second positional argument',
	false !== strpos( $kind_out, 'KIND[mfunc]:y' ),
	"result: $kind_out"
);

// ── 4o. Tampered-after-sign tag is REFUSED (HMAC binds args_json) ──
$reset_callbacks();
$register_callback( 'render_user', function ( $args ) {
	return 'PWNED:' . ( $args['name'] ?? '' );
} );
$valid_args  = '{"name":"alice"}';
$valid_hmac  = $grabber->_dynamic_hmac( 'mfunc', 'render_user', $valid_args );
// Attacker tampers with args after signing.
$tampered    = '<!-- mfunc ' . $token . ' call:render_user {"name":"attacker"} hmac:' . $valid_hmac . ' --><!-- /mfunc ' . $token . ' -->';
$result      = $grabber->_parse_dynamic( $tampered );
assert_true(
	'[4o] Tampered args after signing is refused (HMAC mismatch)',
	false === strpos( $result, 'PWNED:attacker' )
		&& false !== strpos( $result, 'HMAC mismatch' ),
	"result: $result"
);

// ── 4p. Rate-limit: identical (kind|reason) refusals collapse to one notice
// ──   _log_dynamic_deprecation must dedupe via a site transient so a busy
// ──   site post-upgrade doesn't flood admin notices / error_log on every
// ──   request that re-renders a stale legacy mfunc tag.
$reset_callbacks();
$GLOBALS['__test_did_wrong']  = array();
$GLOBALS['__test_transients'] = array();
$raw_payload = '<!-- mfunc ' . $token . ' phpinfo(); --><!-- /mfunc ' . $token . ' -->';
for ( $i = 0; $i < 5; $i++ ) {
	$grabber->_parse_dynamic( $raw_payload );
}
assert_true(
	'[4p] 5 identical refusals collapse to 1 _doing_it_wrong call (rate-limit)',
	1 === count( $GLOBALS['__test_did_wrong'] ),
	'_doing_it_wrong calls: ' . count( $GLOBALS['__test_did_wrong'] )
);

// ── 4p.distinct. Distinct (kind|reason) crosses the dedupe key. A wrong
// ──   HMAC + a raw payload produce different reasons so both should log.
$GLOBALS['__test_did_wrong']  = array();
$GLOBALS['__test_transients'] = array();
$grabber->_parse_dynamic( $raw_payload );
$wrong_hmac_tag = '<!-- mfunc ' . $token . ' call:nonexistent {} hmac:' . str_repeat( '0', 64 ) . ' --><!-- /mfunc ' . $token . ' -->';
$grabber->_parse_dynamic( $wrong_hmac_tag );
assert_true(
	'[4p.distinct] Distinct refusal reasons produce distinct log entries',
	2 === count( $GLOBALS['__test_did_wrong'] ),
	'_doing_it_wrong calls: ' . count( $GLOBALS['__test_did_wrong'] )
);

// ── 4q. _doing_it_wrong is called with esc_html($kind) and 'X.X.X' version
// ──   ($kind is internal but PHPCS flags it; X.X.X is the build placeholder)
$GLOBALS['__test_did_wrong']  = array();
$GLOBALS['__test_transients'] = array();
$grabber->_parse_dynamic( $raw_payload );
assert_true(
	'[4q.esc] _doing_it_wrong first arg is esc_html($kind)',
	isset( $GLOBALS['__test_did_wrong'][0]['fn'] )
		&& htmlspecialchars( 'mfunc', ENT_QUOTES, 'UTF-8' ) === $GLOBALS['__test_did_wrong'][0]['fn'],
	'fn: ' . ( $GLOBALS['__test_did_wrong'][0]['fn'] ?? '<unset>' )
);
assert_true(
	'[4q.version] _doing_it_wrong version is X.X.X (build placeholder)',
	isset( $GLOBALS['__test_did_wrong'][0]['ver'] )
		&& 'X.X.X' === $GLOBALS['__test_did_wrong'][0]['ver'],
	'ver: ' . ( $GLOBALS['__test_did_wrong'][0]['ver'] ?? '<unset>' )
);

// ── 4r. Non-string callback returns are logged AND safely coerced.
// ──   - scalars (null, false, int) coerce via (string) — no warning
// ──   - arrays / non-Stringable objects yield '' instead of "Array" + warning
// ──   - and either case fires the deprecation log channel so the bug is visible.
$reset_callbacks();
$register_callback( 'returns_null',  function () { return null; } );
$register_callback( 'returns_false', function () { return false; } );
$register_callback( 'returns_int',   function () { return 42; } );
$register_callback( 'returns_array', function () { return array( 'x' ); } );

$GLOBALS['__test_did_wrong']  = array();
$GLOBALS['__test_transients'] = array();

$null_args  = '{}';
$null_hmac  = $grabber->_dynamic_hmac( 'mfunc', 'returns_null', $null_args );
$null_tag   = '<!-- mfunc ' . $token . ' call:returns_null ' . $null_args . ' hmac:' . $null_hmac . ' --><!-- /mfunc ' . $token . ' -->';
$null_out   = $grabber->_parse_dynamic( $null_tag );
assert_true(
	'[4r.null] null callback return coerces to empty string without warning',
	'' === $null_out,
	'out: ' . var_export( $null_out, true )
);

$false_hmac = $grabber->_dynamic_hmac( 'mfunc', 'returns_false', $null_args );
$false_tag  = '<!-- mfunc ' . $token . ' call:returns_false ' . $null_args . ' hmac:' . $false_hmac . ' --><!-- /mfunc ' . $token . ' -->';
$false_out  = $grabber->_parse_dynamic( $false_tag );
assert_true(
	'[4r.false] false callback return coerces to empty string',
	'' === $false_out
);

$int_hmac = $grabber->_dynamic_hmac( 'mfunc', 'returns_int', $null_args );
$int_tag  = '<!-- mfunc ' . $token . ' call:returns_int ' . $null_args . ' hmac:' . $int_hmac . ' --><!-- /mfunc ' . $token . ' -->';
$int_out  = $grabber->_parse_dynamic( $int_tag );
assert_true(
	'[4r.int] int callback return coerces to digit string',
	'42' === $int_out,
	'out: ' . var_export( $int_out, true )
);

// Catch warnings explicitly so the "no warning on array coercion" check is
// not a false negative due to PHP's default error reporting masking notices.
$warning_caught = false;
$prev_handler   = set_error_handler( function ( $errno ) use ( &$warning_caught ) {
	$warning_caught = true;
	return true;
}, E_WARNING | E_NOTICE );
$array_hmac = $grabber->_dynamic_hmac( 'mfunc', 'returns_array', $null_args );
$array_tag  = '<!-- mfunc ' . $token . ' call:returns_array ' . $null_args . ' hmac:' . $array_hmac . ' --><!-- /mfunc ' . $token . ' -->';
$array_out  = $grabber->_parse_dynamic( $array_tag );
set_error_handler( $prev_handler );
assert_true(
	'[4r.array] array callback return yields "" (no "Array" leak, no warning)',
	'' === $array_out && false === $warning_caught,
	'out: ' . var_export( $array_out, true ) . ' warning_caught: ' . ( $warning_caught ? 'yes' : 'no' )
);

// All four non-string returns should have produced log entries (one per
// distinct gettype: NULL, boolean, integer, array → 4 distinct dedupe keys).
assert_true(
	'[4r.log] Non-string returns produced log entries through the dedupe channel',
	4 === count( $GLOBALS['__test_did_wrong'] ),
	'_doing_it_wrong calls: ' . count( $GLOBALS['__test_did_wrong'] )
);

// ---------------------------------------------------------------------------
// Results
// ---------------------------------------------------------------------------

echo "\n=== RESULTS ===\n\n";

$width = max( array_map( function( $c ) { return strlen( $c[1] ); }, $cases ) ) + 2;

foreach ( $cases as $c ) {
	$status = $c[0];
	$label  = $c[1];
	$marker = ( 'PASS' === $status ) ? "\033[32m✔\033[0m" : "\033[31m✘\033[0m";
	printf( "  %s  %-{$width}s  %s\n", $marker, $label, $status );
}

printf( "\n  Total: %d  Passed: \033[32m%d\033[0m  Failed: \033[31m%d\033[0m\n\n", $pass + $fail, $pass, $fail );

exit( $fail > 0 ? 1 : 0 );
