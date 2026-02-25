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
