<?php
/**
 * Standalone regression test for PgCache_Environment::wp_config_add_directive() insertion.
 *
 * Verifies that the WP_CACHE directive is inserted immediately after the opening PHP
 * tag, and — when a `declare(strict_types=1);` statement follows the tag — *after* that
 * declaration, so it never displaces strict_types from being the first statement in the
 * file (which would be a fatal error).
 *
 * Run with: php tests/test-wp-config-directive.php
 *
 * @package W3TC\Tests
 * @since   2.10.0
 */

if ( realpath( __FILE__ ) !== realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	return;
}

$wpcd_pass = 0;
$wpcd_fail = 0;

/**
 * The directive block appended by {@see W3TC\PgCache_Environment::wp_config_addon()}.
 *
 * @var string
 */
$wpcd_addon = "/** Enable W3 Total Cache */\r\ndefine('WP_CACHE', true); // Added by W3 Total Cache\r\n";

/**
 * Mirrors the insertion performed by {@see W3TC\PgCache_Environment::wp_config_add_directive()}.
 *
 * @param string $config_data Raw wp-config.php contents.
 * @param string $addon       Directive block to insert.
 * @return string
 */
function wpcd_insert_directive( $config_data, $addon ) {
	return preg_replace(
		'~
			<\?(?:php)?                    # Opening PHP tag: "<?php" or "<?".
			(?:                            # Optionally consume through a strict_types declare.
				(?:                        # Whitespace and comments preceding the declare:
					\s                     #   whitespace,
					| /\*.*?\*/            #   a block comment,
					| //[^\r\n]*           #   a line comment, or
					| \#[^\r\n]*           #   a hash comment.
				)*
				declare\s*\(               # "declare("
					[^)]*strict_types[^)]* #   a directive list containing "strict_types"
				\)\s*;                     # ");"
			)?
		~six',
		"\\0\r\n" . $addon,
		$config_data,
		1
	);
}

/**
 * Exact-match assertion.
 *
 * @param string $label    Case label.
 * @param string $expected Expected output.
 * @param string $actual   Actual output.
 */
function wpcd_assert_equals( $label, $expected, $actual ) {
	global $wpcd_pass, $wpcd_fail;

	if ( $expected === $actual ) {
		++$wpcd_pass;
		echo "  \033[32m✔\033[0m  {$label}\n";
		return;
	}

	++$wpcd_fail;
	echo "  \033[31m✘\033[0m  {$label}\n";
	echo '        expected: ' . var_export( $expected, true ) . "\n";
	echo '        got:      ' . var_export( $actual, true ) . "\n";
}

/**
 * Assertion that $needle appears before $before in $haystack (both must be present).
 *
 * @param string $label    Case label.
 * @param string $haystack Output to inspect.
 * @param string $needle   Substring expected first.
 * @param string $before   Substring expected after $needle.
 */
function wpcd_assert_precedes( $label, $haystack, $needle, $before ) {
	global $wpcd_pass, $wpcd_fail;

	$pos_needle = strpos( $haystack, $needle );
	$pos_before = strpos( $haystack, $before );
	$ok         = false !== $pos_needle && false !== $pos_before && $pos_needle < $pos_before;

	if ( $ok ) {
		++$wpcd_pass;
		echo "  \033[32m✔\033[0m  {$label}\n";
		return;
	}

	++$wpcd_fail;
	echo "  \033[31m✘\033[0m  {$label} (needle@" . var_export( $pos_needle, true ) . ', before@' . var_export( $pos_before, true ) . ")\n";
}

echo "\n=== wp-config.php WP_CACHE directive insertion ===\n\n";

// 1. Standard long open tag: directive inserted immediately after `<?php` (backward compat).
$input    = "<?php\r\n\r\n\$table_prefix = 'wp_';\r\n";
$expected = '<?php' . "\r\n" . $wpcd_addon . "\r\n\r\n\$table_prefix = 'wp_';\r\n";
wpcd_assert_equals( 'standard <?php tag: directive follows opening tag', $expected, wpcd_insert_directive( $input, $wpcd_addon ) );

// 2. strict_types on its own line: directive inserted AFTER the declare statement.
$input    = "<?php\r\n\r\ndeclare(strict_types=1);\r\n\r\n\$table_prefix = 'wp_';\r\n";
$expected = "<?php\r\n\r\ndeclare(strict_types=1);" . "\r\n" . $wpcd_addon . "\r\n\r\n\$table_prefix = 'wp_';\r\n";
$actual   = wpcd_insert_directive( $input, $wpcd_addon );
wpcd_assert_equals( 'declare(strict_types=1) on next line: directive follows declare', $expected, $actual );
wpcd_assert_precedes( 'strict_types stays first statement (before WP_CACHE)', $actual, 'strict_types', "define('WP_CACHE'" );

// 3. strict_types on the same line as the open tag.
$input    = "<?php declare(strict_types=1);\r\n\$table_prefix = 'wp_';\r\n";
$expected = '<?php declare(strict_types=1);' . "\r\n" . $wpcd_addon . "\r\n\$table_prefix = 'wp_';\r\n";
$actual   = wpcd_insert_directive( $input, $wpcd_addon );
wpcd_assert_equals( 'declare(strict_types=1) on same line: directive follows declare', $expected, $actual );
wpcd_assert_precedes( 'same-line strict_types stays first statement', $actual, 'strict_types', "define('WP_CACHE'" );

// 4. Short open tag.
$input    = "<?\r\n\$x = 1;\r\n";
$expected = '<?' . "\r\n" . $wpcd_addon . "\r\n\$x = 1;\r\n";
wpcd_assert_equals( 'short open tag <?: directive follows opening tag', $expected, wpcd_insert_directive( $input, $wpcd_addon ) );

// 5. Case-insensitive keyword and internal whitespace inside declare().
$input    = "<?php\r\nDECLARE( strict_types = 1 );\r\n\$x = 1;\r\n";
$expected = "<?php\r\nDECLARE( strict_types = 1 );" . "\r\n" . $wpcd_addon . "\r\n\$x = 1;\r\n";
$actual   = wpcd_insert_directive( $input, $wpcd_addon );
wpcd_assert_equals( 'uppercase DECLARE with inner spaces: directive follows declare', $expected, $actual );
wpcd_assert_precedes( 'spaced/uppercase strict_types stays first statement', $actual, 'strict_types', "define('WP_CACHE'" );

// 6. Comment (docblock) between the open tag and the strict_types declare.
$input    = "<?php\r\n/**\r\n * The base configuration.\r\n */\r\ndeclare(strict_types=1);\r\n\$x = 1;\r\n";
$expected = "<?php\r\n/**\r\n * The base configuration.\r\n */\r\ndeclare(strict_types=1);" . "\r\n" . $wpcd_addon . "\r\n\$x = 1;\r\n";
$actual   = wpcd_insert_directive( $input, $wpcd_addon );
wpcd_assert_equals( 'docblock before declare: directive follows declare', $expected, $actual );
wpcd_assert_precedes( 'docblock case: strict_types stays first statement', $actual, 'strict_types', "define('WP_CACHE'" );

// 7. Line/hash comments between the open tag and the declare.
$input    = "<?php\r\n// a note\r\n# another\r\ndeclare(strict_types=1);\r\n\$x = 1;\r\n";
$expected = "<?php\r\n// a note\r\n# another\r\ndeclare(strict_types=1);" . "\r\n" . $wpcd_addon . "\r\n\$x = 1;\r\n";
$actual   = wpcd_insert_directive( $input, $wpcd_addon );
wpcd_assert_equals( 'line/hash comments before declare: directive follows declare', $expected, $actual );
wpcd_assert_precedes( 'comment case: strict_types stays first statement', $actual, 'strict_types', "define('WP_CACHE'" );

// 8. Multi-directive declare, strict_types first.
$input    = "<?php\r\ndeclare(strict_types=1, ticks=1);\r\n\$x = 1;\r\n";
$expected = "<?php\r\ndeclare(strict_types=1, ticks=1);" . "\r\n" . $wpcd_addon . "\r\n\$x = 1;\r\n";
$actual   = wpcd_insert_directive( $input, $wpcd_addon );
wpcd_assert_equals( 'declare(strict_types=1, ticks=1): directive follows declare', $expected, $actual );
wpcd_assert_precedes( 'multi-directive case: strict_types stays first statement', $actual, 'strict_types', "define('WP_CACHE'" );

// 9. Multi-directive declare, strict_types last.
$input    = "<?php\r\ndeclare(ticks=1, strict_types=1);\r\n\$x = 1;\r\n";
$expected = "<?php\r\ndeclare(ticks=1, strict_types=1);" . "\r\n" . $wpcd_addon . "\r\n\$x = 1;\r\n";
$actual   = wpcd_insert_directive( $input, $wpcd_addon );
wpcd_assert_equals( 'declare(ticks=1, strict_types=1): directive follows declare', $expected, $actual );
wpcd_assert_precedes( 'strict_types-last case: strict_types stays first statement', $actual, 'strict_types', "define('WP_CACHE'" );

// 10. A non-strict_types declare must NOT be treated as strict_types: directive goes after the tag,
// before the declare (ticks has no "first statement" restriction).
$input    = "<?php\r\ndeclare(ticks=1);\r\n\$x = 1;\r\n";
$expected = '<?php' . "\r\n" . $wpcd_addon . "\r\ndeclare(ticks=1);\r\n\$x = 1;\r\n";
wpcd_assert_equals( 'declare(ticks=1) only: directive follows the opening tag', $expected, wpcd_insert_directive( $input, $wpcd_addon ) );

// 11. A standard WordPress docblock (no declare) must not be skipped: directive follows the tag.
$input    = "<?php\r\n/**\r\n * The base configuration for WordPress.\r\n */\r\ndefine('DB_NAME', 'wp');\r\n";
$expected = '<?php' . "\r\n" . $wpcd_addon . "\r\n/**\r\n * The base configuration for WordPress.\r\n */\r\ndefine('DB_NAME', 'wp');\r\n";
wpcd_assert_equals( 'docblock but no declare: directive follows the opening tag', $expected, wpcd_insert_directive( $input, $wpcd_addon ) );

// 12. Only one directive is inserted even when the file body mentions the open tag again.
$input  = "<?php\r\n\$a = '<?php';\r\n";
$actual = wpcd_insert_directive( $input, $wpcd_addon );
wpcd_assert_equals( 'single insertion only', 1, substr_count( $actual, "define('WP_CACHE'" ) );

echo "\n  Total: " . ( $wpcd_pass + $wpcd_fail ) . "  Passed: \033[32m{$wpcd_pass}\033[0m  Failed: \033[31m{$wpcd_fail}\033[0m\n\n";

exit( $wpcd_fail > 0 ? 1 : 0 );
