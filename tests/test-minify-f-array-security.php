<?php
/**
 * Standalone regression test for the minify `f_array` arbitrary-file-read fix
 * (CVE-2026-9282).
 *
 * Exercises the real `\W3TCL\Minify\Minify_Controller_MinApp::setupSources()`
 * sink against a controlled temporary docroot and asserts:
 *
 *  - An `f_array[]` entry resolving to `wp-config.php` (or any non-CSS/JS file)
 *    is refused and never added to the served sources.
 *  - Legitimate `.css` / `.js` assets are still served.
 *  - The docroot boundary (`checkAllowDirs`) still blocks `../` traversal even
 *    for an allowed extension.
 *  - The hidden-file guard (`checkNotHidden`) still blocks dotfiles.
 *
 * It also asserts, at the source level, that the request handler clears a
 * caller-supplied `f_array` in manual mode (the precondition the exploit
 * depends on).
 *
 * Run with: php tests/test-minify-f-array-security.php
 *
 * Exit code 0 = all pass, non-zero = failures.
 *
 * @package W3TC\Tests
 * @since   X.X.X
 */

/**
 * Only run when invoked directly. When PHPUnit includes this file during test
 * discovery, return immediately so the top-level body does not break the suite.
 */
if ( realpath( __FILE__ ) !== realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	return;
}

/**
 * ---------------------------------------------------------------------------
 * Result helpers (file-unique names per CLAUDE.md standalone-test guidance).
 * ---------------------------------------------------------------------------
 */

$fa_pass  = 0;
$fa_fail  = 0;
$fa_cases = array();

/**
 * Registers a test result.
 *
 * @param string $label       Human-readable description.
 * @param bool   $expectation Condition that should hold.
 * @param string $detail      Optional failure context.
 */
function fa_assert( string $label, bool $expectation, string $detail = '' ): void {
	global $fa_pass, $fa_fail, $fa_cases;
	if ( $expectation ) {
		$fa_cases[] = array( 'PASS', $label );
		++$fa_pass;
	} else {
		$fa_cases[] = array( 'FAIL', $label . ( $detail ? " | $detail" : '' ) );
		++$fa_fail;
	}
}

/**
 * ---------------------------------------------------------------------------
 * Load stubs + the real bundled-Minify classes under test.
 * ---------------------------------------------------------------------------
 */

require_once __DIR__ . '/minify-f-array-stubs.php';

$lib = __DIR__ . '/../lib/Minify/Minify';
require_once $lib . '/Logger.php';
require_once $lib . '/Source.php';
require_once $lib . '/Controller/Base.php';
require_once $lib . '/Controller/MinApp.php';

/**
 * ---------------------------------------------------------------------------
 * Build an isolated temporary docroot.
 * ---------------------------------------------------------------------------
 */

$base_tmp = sys_get_temp_dir() . '/w3tc_fa_' . getmypid() . '_' . bin2hex( random_bytes( 4 ) );
$docroot  = $base_tmp . '/docroot';
mkdir( $docroot, 0777, true );

// Legitimate assets.
file_put_contents( $docroot . '/app.js', "var x = 1;\n" );
file_put_contents( $docroot . '/style.css', "body{color:red}\n" );
// Sensitive in-docroot file the exploit targets.
file_put_contents( $docroot . '/wp-config.php', "<?php define('DB_PASSWORD','S3CR3T-SENTINEL');\n" );
// Hidden dotfile that also happens to carry an allowed extension.
file_put_contents( $docroot . '/.hidden.js', "/* hidden */\n" );
// A real file ONE LEVEL ABOVE the docroot, reachable only via traversal.
file_put_contents( $base_tmp . '/outside.js', "/* outside docroot */\n" );

\W3TC\Util_Environment::$docroot = $docroot;

/**
 * Runs setupSources() on a fresh controller with the given f_array and returns
 * the resolved source filepaths (basenames) that would be served.
 *
 * @param array $f_array Raw caller-supplied f_array value.
 *
 * @return string[] Basenames of files that became Minify sources.
 */
function fa_resolve( array $f_array ): array {
	// Reset request state for each case.
	unset( $_GET['g'], $_GET['b'], $_GET['ext'] );
	$_GET['f_array'] = $f_array;
	$_GET['ext']     = 'js';

	$controller = new \W3TCL\Minify\Minify_Controller_MinApp();
	$controller->setupSources( array() );

	$served = array();
	foreach ( $controller->sources as $source ) {
		// Skip the synthetic "missingFile" placeholder source (id-based, no filepath).
		if ( isset( $source->filepath ) && null !== $source->filepath ) {
			$served[] = basename( $source->filepath );
		}
	}

	return $served;
}

/**
 * ---------------------------------------------------------------------------
 * Tests — the sink (real setupSources).
 * ---------------------------------------------------------------------------
 */

// [1] The CVE: wp-config.php must NOT be served.
$served = fa_resolve( array( 'wp-config.php' ) );
fa_assert(
	'[1] f_array[]=wp-config.php is refused (not served)',
	! in_array( 'wp-config.php', $served, true ) && empty( $served ),
	'served=' . implode( ',', $served )
);

// [2] Legitimate JS asset is served.
$served = fa_resolve( array( 'app.js' ) );
fa_assert(
	'[2] f_array[]=app.js is served',
	in_array( 'app.js', $served, true ),
	'served=' . implode( ',', $served )
);

// [3] Legitimate CSS asset is served.
$served = fa_resolve( array( 'style.css' ) );
fa_assert(
	'[3] f_array[]=style.css is served',
	in_array( 'style.css', $served, true ),
	'served=' . implode( ',', $served )
);

// [4] Mixed batch: only the asset survives, wp-config.php is dropped.
$served = fa_resolve( array( 'wp-config.php', 'app.js' ) );
fa_assert(
	'[4] mixed f_array serves app.js but drops wp-config.php',
	in_array( 'app.js', $served, true ) && ! in_array( 'wp-config.php', $served, true ),
	'served=' . implode( ',', $served )
);

// [5] Arbitrary non-asset extension (e.g. .env-style / .txt) refused.
file_put_contents( $docroot . '/secrets.txt', "TOKEN=abc\n" );
$served = fa_resolve( array( 'secrets.txt' ) );
fa_assert(
	'[5] f_array[]=secrets.txt (non-asset) is refused',
	empty( $served ),
	'served=' . implode( ',', $served )
);

// [6] Docroot boundary still blocks ../ traversal even for an allowed extension.
$served = fa_resolve( array( '../outside.js' ) );
fa_assert(
	'[6] traversal ../outside.js blocked by checkAllowDirs (not served)',
	! in_array( 'outside.js', $served, true ),
	'served=' . implode( ',', $served )
);

// [7] Hidden dotfile still blocked by checkNotHidden even with .js extension.
$served = fa_resolve( array( '.hidden.js' ) );
fa_assert(
	'[7] hidden .hidden.js blocked (not served)',
	! in_array( '.hidden.js', $served, true ),
	'served=' . implode( ',', $served )
);

// [8] Case-insensitive extension bypass attempt (uppercase PHP) refused.
file_put_contents( $docroot . '/shell.PHP', "<?php phpinfo();\n" );
$served = fa_resolve( array( 'shell.PHP' ) );
fa_assert(
	'[8] f_array[]=shell.PHP (uppercase ext) is refused',
	empty( $served ),
	'served=' . implode( ',', $served )
);

/**
 * ---------------------------------------------------------------------------
 * Tests — the request handler precondition (source-level assertions).
 * ---------------------------------------------------------------------------
 */

$handler_src = file_get_contents( __DIR__ . '/../Minify_MinifiedFileRequestHandler.php' );

// [9] Manual-mode else-branch clears any caller-supplied f_array.
fa_assert(
	'[9] handler unsets $_GET[\'f_array\'] in manual mode',
	(bool) preg_match( '/else\s*\{[^}]*unset\(\s*\$_GET\[\s*\'f_array\'\s*\]\s*\)/s', $handler_src ),
	'manual-mode else-branch must drop request-supplied f_array'
);

// [10] Manual-mode else-branch forces groups-only resolution.
fa_assert(
	'[10] handler forces minApp.groupsOnly=true in manual mode',
	false !== strpos( $handler_src, "\$serve_options['minApp']['groupsOnly']" )
		&& (bool) preg_match( "/\\\$serve_options\\['minApp'\\]\\['groupsOnly'\\]\\s*=\\s*true/", $handler_src ),
	'manual-mode else-branch must set groupsOnly = true'
);

/**
 * [11] Confirm the exploit precondition: the PoC token parses as MANUAL (empty hash),
 * which is exactly the branch the fix guards.
 */
define( 'MINIFY_AUTO_FILENAME_REGEX_T', '([a-zA-Z0-9-_]+)\\.(css|js)([?].*)?' );
define( 'MINIFY_MANUAL_FILENAME_REGEX_T', '([a-f0-9]+)\\.(.+)\\.(include(\\-(footer|body))?)\\.[a-f0-9]+\\.(css|js)' );
$poc_token   = '87fee.default.include.452061.js';
$is_auto     = (bool) preg_match( '~^' . MINIFY_AUTO_FILENAME_REGEX_T . '$~', $poc_token );
$is_manual   = (bool) preg_match( '~^' . MINIFY_MANUAL_FILENAME_REGEX_T . '$~', $poc_token );
fa_assert(
	'[11] PoC token parses as manual (empty hash) — the guarded branch',
	! $is_auto && $is_manual,
	"auto=$is_auto manual=$is_manual"
);

/**
 * ---------------------------------------------------------------------------
 * Cleanup.
 * ---------------------------------------------------------------------------
 */

foreach (
	array(
		$docroot . '/app.js',
		$docroot . '/style.css',
		$docroot . '/wp-config.php',
		$docroot . '/.hidden.js',
		$docroot . '/secrets.txt',
		$docroot . '/shell.PHP',
		$base_tmp . '/outside.js',
	) as $f
) {
	if ( file_exists( $f ) ) {
		unlink( $f );
	}
}
@rmdir( $docroot );
@rmdir( $base_tmp );

/**
 * ---------------------------------------------------------------------------
 * Report.
 * ---------------------------------------------------------------------------
 */

echo "\n  Minify f_array arbitrary-file-read regression (CVE-2026-9282)\n";
echo '  ' . str_repeat( '-', 70 ) . "\n";
foreach ( $fa_cases as $case ) {
	$mark = 'PASS' === $case[0] ? "\033[32m✔\033[0m" : "\033[31m✗\033[0m";
	printf( "  %s  %-66s %s\n", $mark, $case[1], $case[0] );
}
echo "\n  Total: " . ( $fa_pass + $fa_fail )
	. "  Passed: \033[32m{$fa_pass}\033[0m  Failed: \033[31m{$fa_fail}\033[0m\n\n";

exit( $fa_fail > 0 ? 1 : 0 );
