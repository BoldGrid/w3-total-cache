<?php
/**
 * Standalone regression test for PgCache_Environment::wp_config_content_for_cache_constant().
 *
 * Verifies that the WP_CACHE directive is inserted immediately after the opening PHP
 * tag, and — when a `declare(strict_types=1);` statement follows the tag — *after* that
 * declaration, so it never displaces strict_types from being the first statement in the
 * file (which would be a fatal error).
 *
 * Run with: php tests/test-wp-config-directive.php
 *
 * @package W3TC\Tests
 * @since   X.X.X
 */

if ( realpath( __FILE__ ) !== realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	return;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once dirname( __DIR__ ) . '/PgCache_Environment.php';

$wpcd_pass = 0;
$wpcd_fail = 0;

/**
 * The directive block returned by {@see W3TC\PgCache_Environment::wp_config_addon()}.
 *
 * @since X.X.X
 *
 * @var string
 */
$wpcd_addon = "/** Enable W3 Total Cache */\r\ndefine('WP_CACHE', true); // Added by W3 Total Cache\r\n";

/**
 * Runs {@see W3TC\PgCache_Environment::wp_config_content_for_cache_constant()}.
 *
 * @since X.X.X
 *
 * @param string $config_data Raw wp-config.php contents.
 * @return string
 */
function wpcd_insert_directive( $config_data ) {
	$class  = new ReflectionClass( 'W3TC\PgCache_Environment' );
	$method = new ReflectionMethod( 'W3TC\PgCache_Environment', 'wp_config_content_for_cache_constant' );

	if ( PHP_VERSION_ID < 80100 ) {
		$method->setAccessible( true );
	}

	return $method->invoke( $class->newInstanceWithoutConstructor(), __DIR__ . '/wpcd-missing/wp-config.php', $config_data );
}

/**
 * Asserts that $code passes `php -l`.
 *
 * @since X.X.X
 *
 * @param string $label Case label.
 * @param string $code  PHP source.
 */
function wpcd_assert_lints( $label, $code ) {
	global $wpcd_pass, $wpcd_fail;

	$file   = __DIR__ . '/wpcd-lint-' . getmypid() . '.php';
	$output = array();
	$status = 1;

	if ( false === file_put_contents( $file, $code ) ) {
		$output[] = 'could not write ' . $file;
	} else {
		exec( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $file ) . ' 2>&1', $output, $status );
		unlink( $file );
	}

	if ( 0 === $status ) {
		++$wpcd_pass;
		echo "  \033[32m✔\033[0m  {$label}\n";
		return;
	}

	++$wpcd_fail;
	echo "  \033[31m✘\033[0m  {$label}\n        " . implode( "\n        ", $output ) . "\n";
}

/**
 * Exact-match assertion.
 *
 * @since X.X.X
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
 * @since X.X.X
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
wpcd_assert_equals( 'standard <?php tag: directive follows opening tag', $expected, wpcd_insert_directive( $input ) );

// 2. strict_types on its own line: directive inserted AFTER the declare statement.
$input    = "<?php\r\n\r\ndeclare(strict_types=1);\r\n\r\n\$table_prefix = 'wp_';\r\n";
$expected = "<?php\r\n\r\ndeclare(strict_types=1);" . "\r\n" . $wpcd_addon . "\r\n\r\n\$table_prefix = 'wp_';\r\n";
$actual   = wpcd_insert_directive( $input );
wpcd_assert_equals( 'declare(strict_types=1) on next line: directive follows declare', $expected, $actual );
wpcd_assert_precedes( 'strict_types stays first statement (before WP_CACHE)', $actual, 'strict_types', "define('WP_CACHE'" );
wpcd_assert_lints( 'strict_types stays first statement (before WP_CACHE) (php -l)', $actual );

// 3. strict_types on the same line as the open tag.
$input    = "<?php declare(strict_types=1);\r\n\$table_prefix = 'wp_';\r\n";
$expected = '<?php declare(strict_types=1);' . "\r\n" . $wpcd_addon . "\r\n\$table_prefix = 'wp_';\r\n";
$actual   = wpcd_insert_directive( $input );
wpcd_assert_equals( 'declare(strict_types=1) on same line: directive follows declare', $expected, $actual );
wpcd_assert_precedes( 'same-line strict_types stays first statement', $actual, 'strict_types', "define('WP_CACHE'" );
wpcd_assert_lints( 'same-line strict_types stays first statement (php -l)', $actual );

// 4. Short open tag.
$input    = "<?\r\n\$x = 1;\r\n";
$expected = '<?' . "\r\n" . $wpcd_addon . "\r\n\$x = 1;\r\n";
wpcd_assert_equals( 'short open tag <?: directive follows opening tag', $expected, wpcd_insert_directive( $input ) );

// 5. Case-insensitive keyword and internal whitespace inside declare().
$input    = "<?php\r\nDECLARE( strict_types = 1 );\r\n\$x = 1;\r\n";
$expected = "<?php\r\nDECLARE( strict_types = 1 );" . "\r\n" . $wpcd_addon . "\r\n\$x = 1;\r\n";
$actual   = wpcd_insert_directive( $input );
wpcd_assert_equals( 'uppercase DECLARE with inner spaces: directive follows declare', $expected, $actual );
wpcd_assert_precedes( 'spaced/uppercase strict_types stays first statement', $actual, 'strict_types', "define('WP_CACHE'" );
wpcd_assert_lints( 'spaced/uppercase strict_types stays first statement (php -l)', $actual );

// 6. Comment (docblock) between the open tag and the strict_types declare.
$input    = "<?php\r\n/**\r\n * The base configuration.\r\n */\r\ndeclare(strict_types=1);\r\n\$x = 1;\r\n";
$expected = "<?php\r\n/**\r\n * The base configuration.\r\n */\r\ndeclare(strict_types=1);" . "\r\n" . $wpcd_addon . "\r\n\$x = 1;\r\n";
$actual   = wpcd_insert_directive( $input );
wpcd_assert_equals( 'docblock before declare: directive follows declare', $expected, $actual );
wpcd_assert_precedes( 'docblock case: strict_types stays first statement', $actual, 'strict_types', "define('WP_CACHE'" );
wpcd_assert_lints( 'docblock case: strict_types stays first statement (php -l)', $actual );

// 7. Line/hash comments between the open tag and the declare.
$input    = "<?php\r\n// a note\r\n# another\r\ndeclare(strict_types=1);\r\n\$x = 1;\r\n";
$expected = "<?php\r\n// a note\r\n# another\r\ndeclare(strict_types=1);" . "\r\n" . $wpcd_addon . "\r\n\$x = 1;\r\n";
$actual   = wpcd_insert_directive( $input );
wpcd_assert_equals( 'line/hash comments before declare: directive follows declare', $expected, $actual );
wpcd_assert_precedes( 'comment case: strict_types stays first statement', $actual, 'strict_types', "define('WP_CACHE'" );
wpcd_assert_lints( 'comment case: strict_types stays first statement (php -l)', $actual );

// 8. Multi-directive declare, strict_types first.
$input    = "<?php\r\ndeclare(strict_types=1, ticks=1);\r\n\$x = 1;\r\n";
$expected = "<?php\r\ndeclare(strict_types=1, ticks=1);" . "\r\n" . $wpcd_addon . "\r\n\$x = 1;\r\n";
$actual   = wpcd_insert_directive( $input );
wpcd_assert_equals( 'declare(strict_types=1, ticks=1): directive follows declare', $expected, $actual );
wpcd_assert_precedes( 'multi-directive case: strict_types stays first statement', $actual, 'strict_types', "define('WP_CACHE'" );
wpcd_assert_lints( 'multi-directive case: strict_types stays first statement (php -l)', $actual );

// 9. Multi-directive declare, strict_types last.
$input    = "<?php\r\ndeclare(ticks=1, strict_types=1);\r\n\$x = 1;\r\n";
$expected = "<?php\r\ndeclare(ticks=1, strict_types=1);" . "\r\n" . $wpcd_addon . "\r\n\$x = 1;\r\n";
$actual   = wpcd_insert_directive( $input );
wpcd_assert_equals( 'declare(ticks=1, strict_types=1): directive follows declare', $expected, $actual );
wpcd_assert_precedes( 'strict_types-last case: strict_types stays first statement', $actual, 'strict_types', "define('WP_CACHE'" );
wpcd_assert_lints( 'strict_types-last case: strict_types stays first statement (php -l)', $actual );

// 10. A non-strict_types declare must NOT be treated as strict_types: directive goes after the tag,
// before the declare (ticks has no "first statement" restriction).
$input    = "<?php\r\ndeclare(ticks=1);\r\n\$x = 1;\r\n";
$expected = '<?php' . "\r\n" . $wpcd_addon . "\r\ndeclare(ticks=1);\r\n\$x = 1;\r\n";
wpcd_assert_equals( 'declare(ticks=1) only: directive follows the opening tag', $expected, wpcd_insert_directive( $input ) );

// 11. A standard WordPress docblock (no declare) must not be skipped: directive follows the tag.
$input    = "<?php\r\n/**\r\n * The base configuration for WordPress.\r\n */\r\ndefine('DB_NAME', 'wp');\r\n";
$expected = '<?php' . "\r\n" . $wpcd_addon . "\r\n/**\r\n * The base configuration for WordPress.\r\n */\r\ndefine('DB_NAME', 'wp');\r\n";
wpcd_assert_equals( 'docblock but no declare: directive follows the opening tag', $expected, wpcd_insert_directive( $input ) );

// 12. Only one directive is inserted even when the file body mentions the open tag again.
$input  = "<?php\r\n\$a = '<?php';\r\n";
$actual = wpcd_insert_directive( $input );
wpcd_assert_equals( 'single insertion only', 1, substr_count( $actual, "define('WP_CACHE'" ) );

// 13. Comments that mention the declaration are not code: directive follows the tag.
foreach ( array( '//', '#' ) as $marker ) {
	$input    = "<?php\r\n{$marker} declare(strict_types=1); breaks plugins\r\n\$x = 1;\r\n";
	$expected = '<?php' . "\r\n" . $wpcd_addon . "\r\n{$marker} declare(strict_types=1); breaks plugins\r\n\$x = 1;\r\n";
	$actual   = wpcd_insert_directive( $input );
	wpcd_assert_equals( "{$marker} commented-out declare: directive follows the opening tag", $expected, $actual );
	wpcd_assert_lints( "{$marker} commented-out declare (php -l)", $actual );
}

// 14. Long runs of comment characters stay within the PCRE backtrack limit, with and without a declare.
$runs = array(
	'slash divider'  => str_repeat( '/', 60 ) . "\r\n",
	'hash divider'   => str_repeat( '#', 60 ) . "\r\n",
	'block comments' => str_repeat( "/* a */\r\n", 30 ),
);
foreach ( $runs as $name => $run ) {
	$input    = "<?php\r\n{$run}\$x = 1;\r\n";
	$expected = '<?php' . "\r\n" . $wpcd_addon . "\r\n{$run}\$x = 1;\r\n";
	wpcd_assert_equals( "{$name}, no declare: directive follows the opening tag", $expected, wpcd_insert_directive( $input ) );

	$input    = "<?php\r\n{$run}declare(strict_types=1);\r\n\$x = 1;\r\n";
	$expected = "<?php\r\n{$run}declare(strict_types=1);" . "\r\n" . $wpcd_addon . "\r\n\$x = 1;\r\n";
	wpcd_assert_equals( "{$name} before declare: directive follows declare", $expected, wpcd_insert_directive( $input ) );
}

// 15. PHP allows other declare statements ahead of strict_types: directive follows strict_types.
$input    = "<?php\r\ndeclare(ticks=1);\r\ndeclare(strict_types=1);\r\n\$x = 1;\r\n";
$expected = "<?php\r\ndeclare(ticks=1);\r\ndeclare(strict_types=1);" . "\r\n" . $wpcd_addon . "\r\n\$x = 1;\r\n";
$actual   = wpcd_insert_directive( $input );
wpcd_assert_equals( 'declare(ticks=1) before strict_types: directive follows strict_types', $expected, $actual );
wpcd_assert_lints( 'declare(ticks=1) before strict_types (php -l)', $actual );

echo "\n  Total: " . ( $wpcd_pass + $wpcd_fail ) . "  Passed: \033[32m{$wpcd_pass}\033[0m  Failed: \033[31m{$wpcd_fail}\033[0m\n\n";

exit( $wpcd_fail > 0 ? 1 : 0 );
