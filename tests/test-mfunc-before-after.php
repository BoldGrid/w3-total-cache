<?php
/**
 * Before-vs-after comparison test for the mfunc/mclude security fix.
 *
 * Demonstrates the vulnerability using the OLD patterns, then confirms it is
 * closed using the NEW (fixed) patterns.  No WordPress installation required.
 *
 * Usage:
 *   php tests/test-mfunc-before-after.php
 *
 * Expected result:
 *   BEFORE section – every "should be blocked" case shows VULNERABLE (red).
 *   AFTER  section – every case shows SAFE (green).
 *
 * Exit code 0 = after-fix tests all pass (patch verified).
 *
 * @package W3TC\Tests
 * @since   X.X.X
 */

// Use the same token as the Patchstack report.
const SECURITY_TOKEN = 'test';

// ---------------------------------------------------------------------------
// Colour helpers
// ---------------------------------------------------------------------------

function green( string $s ): string { return "\033[32m{$s}\033[0m"; }
function red( string $s ): string   { return "\033[31m{$s}\033[0m"; }
function bold( string $s ): string  { return "\033[1m{$s}\033[0m"; }

// ---------------------------------------------------------------------------
// Pattern sets
// ---------------------------------------------------------------------------

/**
 * OLD vulnerable patterns (pre-fix).
 * - \s*  between keyword and token (allows zero whitespace)
 * - No preg_quote on token
 * - Sanitization uses \s+ (misses no-space variants)
 */
function old_parse_mfunc(): string {
	return '~<!--\s*mfunc\s*' . SECURITY_TOKEN . '(.*)-->(.*)<!--\s*/mfunc\s*' . SECURITY_TOKEN . '\s*-->~Uis';
}
function old_has_dynamic(): string {
	return '~<!--\s*m(func|clude)\s*' . SECURITY_TOKEN . '(.*)-->(.*)<!--\s*/m(func|clude)\s*' . SECURITY_TOKEN . '\s*-->~Uis';
}
/** Old sanitization pattern (required \s+ before token). */
function old_sanitize_patterns(): array {
	return array(
		'~<!--\s*mfunc\s+[^\s]+.*?-->(.*?)<!--\s*/mfunc\s+[^\s]+.*?\s*-->~Uis',
		'~<!--\s*mclude\s+[^\s]+.*?-->(.*?)<!--\s*/mclude\s+[^\s]+.*?\s*-->~Uis',
	);
}

/**
 * NEW fixed patterns (post-fix).
 * - \s+  between keyword and token (requires at least one space)
 * - preg_quote on token
 * - Sanitization uses \s*\S+ (catches no-space variants)
 */
function new_parse_mfunc(): string {
	return '~<!--\s*mfunc\s+' . preg_quote( SECURITY_TOKEN, '~' ) . '(.*)-->(.*)<!--\s*/mfunc\s+' . preg_quote( SECURITY_TOKEN, '~' ) . '\s*-->~Uis';
}
function new_has_dynamic(): string {
	return '~<!--\s*m(func|clude)\s+' . preg_quote( SECURITY_TOKEN, '~' ) . '(.*)-->(.*)<!--\s*/m(func|clude)\s+' . preg_quote( SECURITY_TOKEN, '~' ) . '\s*-->~Uis';
}
function new_sanitize_patterns(): array {
	return array(
		'~<!--\s*mfunc\s*\S+.*?-->(.*?)<!--\s*/mfunc\s*\S+.*?\s*-->~Uis',
		'~<!--\s*mclude\s*\S+.*?-->(.*?)<!--\s*/mclude\s*\S+.*?\s*-->~Uis',
	);
}

// ---------------------------------------------------------------------------
// Shared simulation helpers
// ---------------------------------------------------------------------------

/**
 * Simulates Generic_Plugin::strip_dynamic_fragment_tags_from_string.
 *
 * @param string   $value    Raw input.
 * @param string[] $patterns Sanitisation patterns to use.
 * @return string Sanitised value.
 */
function run_sanitize( string $value, array $patterns ): string {
	$original = $value;
	$value    = preg_replace_callback(
		$patterns,
		fn( array $m ) => $m[1],
		$value
	);
	if ( null === $value ) {
		return $original;
	}
	return str_replace( SECURITY_TOKEN, '', $value );
}

/**
 * Simulates _parse_dynamic: returns true if the eval() callback would fire.
 *
 * @param string $buffer  Buffer to parse.
 * @param string $pattern mfunc execution pattern.
 * @return bool True if code would be executed.
 */
function would_execute( string $buffer, string $pattern ): bool {
	$fired = false;
	preg_replace_callback(
		$pattern,
		function () use ( &$fired ): string {
			$fired = true;
			return '';
		},
		$buffer
	);
	return $fired;
}

// ---------------------------------------------------------------------------
// Test runner
// ---------------------------------------------------------------------------

$after_failures = 0;

/**
 * Prints one result row.
 *
 * @param string $era       'BEFORE' or 'AFTER'.
 * @param string $label     Test description.
 * @param bool   $condition The assertion result.
 * @param string $expect    'BLOCKED' or 'EXECUTES' — what the correct outcome is.
 * @param string $detail    Optional extra context.
 */
function print_row( string $era, string $label, bool $condition, string $expect, string $detail = '' ): void {
	global $after_failures;

	// For BEFORE rows the "correct" behaviour is that the OLD code is VULNERABLE,
	// so $condition = true means the attack succeeds (bad, but expected pre-fix).
	// For AFTER rows $condition = false means the attack is blocked (good).
	if ( 'BEFORE' === $era ) {
		$status = $condition ? red( 'VULNERABLE' ) : green( 'SAFE (unexpected)' );
	} else {
		$status = $condition ? red( 'VULNERABLE' ) : green( 'SAFE' );
		if ( $condition ) {
			++$after_failures;
		}
	}

	printf(
		"  %-7s  %-58s  %s%s\n",
		"[$era]",
		$label,
		$status,
		$detail ? "  ($detail)" : ''
	);
}

// ---------------------------------------------------------------------------
// ── Test cases ──────────────────────────────────────────────────────────────
// ---------------------------------------------------------------------------

$token = SECURITY_TOKEN; // 'test'

// Payloads used across both eras.
$bypass_reported  = '<!-- mfunc' . 'te' . $token . 'st -->phpinfo();<!-- /mfunc' . 'te' . $token . 'st -->';
$no_space_direct  = '<!-- mfunc' . $token . ' -->phpinfo();<!-- /mfunc' . $token . ' -->';
$padded_embedding = '<!-- mfuncAAA' . $token . 'BBB -->phpinfo();<!-- /mfuncAAA' . $token . 'BBB -->';
$legit_between    = '<!-- mfunc ' . $token . ' -->echo "ok";<!-- /mfunc ' . $token . ' -->';
$legit_inline     = '<!-- mfunc ' . $token . ' echo "ok"; --><!-- /mfunc ' . $token . ' -->';

// ---------------------------------------------------------------------------
printf( "\n%s\n\n", bold( '=== BEFORE FIX (demonstrates the vulnerability) ===' ) );
// ---------------------------------------------------------------------------

$old_san  = old_sanitize_patterns();
$old_mfun = old_parse_mfunc();
$old_has  = old_has_dynamic();

// 1. Reported bypass passes through old sanitization
$stored_old = run_sanitize( $bypass_reported, $old_san );
print_row( 'BEFORE', 'Reported bypass survives old sanitization',
	false !== strpos( $stored_old, 'mfunc' ), // tag still present = vulnerable
	'BLOCKED',
	"stored: $stored_old"
);

// 2. Stored value (after old str_replace morphing) is executed by old _parse_dynamic
print_row( 'BEFORE', 'Morphed tag executes via old _parse_dynamic',
	would_execute( $stored_old, $old_mfun ),
	'BLOCKED',
	"stored: $stored_old"
);

// 3. Old _has_dynamic flags the morphed value (causes page to be cached as dynamic)
print_row( 'BEFORE', 'Morphed tag detected as dynamic by old _has_dynamic',
	(bool) preg_match( $old_has, $stored_old ),
	'BLOCKED'
);

// 4. No-space direct concatenation executes via old patterns
print_row( 'BEFORE', 'No-space mfunc+token tag executes via old _parse_dynamic',
	would_execute( $no_space_direct, $old_mfun ),
	'BLOCKED'
);

// 5. Padded embedding executes (old sanitize misses it, str_replace morphs it)
$stored_padded_old = run_sanitize( $padded_embedding, $old_san );
print_row( 'BEFORE', 'Padded-embedding tag executes after old sanitize+parse',
	would_execute( $stored_padded_old, $old_mfun ),
	'BLOCKED',
	"stored: $stored_padded_old"
);

// 6. Legitimate tag still works with old patterns (sanity check)
print_row( 'BEFORE', 'Legitimate tag still executes with old patterns (expected)',
	would_execute( $legit_between, $old_mfun ),
	'EXECUTES'
);

// ---------------------------------------------------------------------------
printf( "\n%s\n\n", bold( '=== AFTER FIX (verifies the patch) ===' ) );
// ---------------------------------------------------------------------------

$new_san  = new_sanitize_patterns();
$new_mfun = new_parse_mfunc();
$new_has  = new_has_dynamic();

// 1. Reported bypass is stripped by new sanitization
$stored_new = run_sanitize( $bypass_reported, $new_san );
print_row( 'AFTER', 'Reported bypass stripped by new sanitization',
	false !== strpos( $stored_new, 'mfunc' ), // mfunc still present = still vulnerable
	'BLOCKED',
	"stored: $stored_new"
);

// 2. Even if the tag somehow reached _parse_dynamic, \s+ blocks it
$morphed = run_sanitize( $bypass_reported, $old_san ); // deliberately use OLD sanitize
print_row( 'AFTER', 'Morphed tag does NOT execute via new _parse_dynamic',
	would_execute( $morphed, $new_mfun ),
	'BLOCKED',
	"morphed value: $morphed"
);

// 3. New _has_dynamic does not flag the morphed value
print_row( 'AFTER', 'Morphed tag NOT detected as dynamic by new _has_dynamic',
	(bool) preg_match( $new_has, $morphed ),
	'BLOCKED'
);

// 4. No-space direct tag does NOT execute via new patterns
print_row( 'AFTER', 'No-space mfunc+token tag does NOT execute via new _parse_dynamic',
	would_execute( $no_space_direct, $new_mfun ),
	'BLOCKED'
);

// 5. Padded-embedding tag stripped and not executed
$stored_padded_new = run_sanitize( $padded_embedding, $new_san );
print_row( 'AFTER', 'Padded-embedding tag stripped and not executed after new sanitize+parse',
	would_execute( $stored_padded_new, $new_mfun ),
	'BLOCKED',
	"stored: $stored_padded_new"
);

// 6. Legitimate between-tags form still executes
print_row( 'AFTER', 'Legitimate between-tags form still executes (no regression)',
	! would_execute( $legit_between, $new_mfun ),
	'EXECUTES'
);

// 7. Legitimate inline-code form still executes
print_row( 'AFTER', 'Legitimate inline-code form still executes (no regression)',
	! would_execute( $legit_inline, $new_mfun ),
	'EXECUTES'
);

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

printf( "\n%s\n\n", bold( '=== SUMMARY ===' ) );

if ( 0 === $after_failures ) {
	echo green( '  All AFTER checks passed. The patch is effective.' ) . "\n\n";
} else {
	echo red( "  $after_failures AFTER check(s) failed. The patch is NOT fully effective." ) . "\n\n";
}

exit( $after_failures > 0 ? 1 : 0 );
