<?php
/**
 * Standalone regression test: Util_Environment::document_root() returns a normalized
 * (forward-slash) path, and every str_replace() that strips it from a filesystem path
 * normalizes that path too - otherwise the prefix stops matching on Windows.
 *
 * Run with: php tests/test-docroot-normalize.php
 *
 * Each runtime case runs in a child process (this file, with a mode argument), because
 * document_root() caches its result in a static and the CDN cases need a stubbed
 * Util_Environment while the document_root() cases need the real one.
 *
 * @package W3TC\Tests
 * @since   X.X.X
 */

if ( realpath( __FILE__ ) !== realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	return;
}

$drn_plugin_dir = dirname( __DIR__ );
$drn_mode       = $argv[1] ?? '';

/**
 * Turns a path into its Windows-style spelling (backslashes).
 *
 * @param string $path Path.
 *
 * @return string
 */
function drn_backslash( $path ) {
	return str_replace( '/', '\\', $path );
}

// ---------------------------------------------------------------------------------------------
// Child: the real Cdn_Util / Cdn_Core callers against a Windows-style document root.
// ---------------------------------------------------------------------------------------------
if ( 'child-cdn' === $drn_mode ) {
	define( 'DRN_STUB_UTIL_ENV', true );
	define( 'WP_CONTENT_DIR', 'C:\\site/wp-content' );
	define( 'WP_PLUGIN_DIR', 'C:\\site\\wp-content\\plugins' );
	require __DIR__ . '/docroot-normalize-stubs.php';
	require $drn_plugin_dir . '/Cdn_Util.php';
	require $drn_plugin_dir . '/Cdn_Core.php';

	\W3TC\Util_Environment::$docroot        = 'C:/site';
	\W3TC\Util_Environment::$upload_basedir = 'C:\\site\\wp-content\\uploads';

	$cdn_core = ( new ReflectionClass( '\\W3TC\\Cdn_Core' ) )->newInstanceWithoutConstructor();

	echo json_encode(
		array(
			'wp_content_dir' => \W3TC\Cdn_Util::replace_folder_placeholders( '{wp_content_dir}/x' ),
			'plugins_dir'    => \W3TC\Cdn_Util::replace_folder_placeholders( '{plugins_dir}/x' ),
			'uploads_dir'    => \W3TC\Cdn_Util::replace_folder_placeholders( '{uploads_dir}/x' ),
			'relative'       => $cdn_core->abspath_to_relative_path( 'C:\\site\\wp-content\\uploads\\a.jpg' ),
		)
	);
	exit( 0 );
}

// ---------------------------------------------------------------------------------------------
// Child: the real document_root() fed backslash $_SERVER values, and the real
// apache_cache_uri_path() reaching its document_root() fallback.
// ---------------------------------------------------------------------------------------------
if ( 'child-script' === $drn_mode || 'child-docroot' === $drn_mode ) {
	$docroot = $argv[2];
	$style   = $argv[3];

	// ABSPATH that does not exist: apache_cache_uri_path() skips its two realpath() matches.
	define( 'ABSPATH', $docroot . '/no-such-wp/' );
	require __DIR__ . '/docroot-normalize-stubs.php';
	require $drn_plugin_dir . '/Util_Environment.php';
	require $drn_plugin_dir . '/PgCache_Environment.php';

	// 'forward': what Apache on Windows passes. 'backslash': a backslash path as WordPress
	// leaves it after wp_magic_quotes() slashed $_SERVER (document_root() runs stripslashes()).
	$spell = static function ( $path ) use ( $style ) {
		return 'backslash' === $style ? addslashes( drn_backslash( $path ) ) : $path;
	};

	unset( $_SERVER['SCRIPT_FILENAME'], $_SERVER['PHP_SELF'], $_SERVER['PATH_TRANSLATED'], $_SERVER['DOCUMENT_ROOT'] );
	if ( 'child-script' === $drn_mode ) {
		$_SERVER['SCRIPT_FILENAME'] = $spell( $docroot . '/index.php' );
		$_SERVER['PHP_SELF']        = '/index.php';
	} else {
		$_SERVER['DOCUMENT_ROOT'] = $spell( $docroot . '/' );
	}

	$pgcache_env = ( new ReflectionClass( '\\W3TC\\PgCache_Environment' ) )->newInstanceWithoutConstructor();

	echo json_encode(
		array(
			'document_root' => \W3TC\Util_Environment::document_root(),
			'uri_path'      => $pgcache_env->apache_cache_uri_path( drn_backslash( $docroot . '/wp-content/cache/page_enhanced' ) ),
		)
	);
	exit( 0 );
}

// ---------------------------------------------------------------------------------------------
// Parent.
// ---------------------------------------------------------------------------------------------
$drn_pass = 0;
$drn_fail = 0;

/**
 * Records one assertion.
 *
 * @param bool   $ok     Result.
 * @param string $label  Case label.
 * @param string $detail Shown on failure.
 */
function drn_check( $ok, $label, $detail = '' ) {
	global $drn_pass, $drn_fail;

	if ( $ok ) {
		++$drn_pass;
		echo "  \033[32m✔\033[0m  {$label}\n";
		return;
	}

	++$drn_fail;
	echo "  \033[31m✘\033[0m  {$label}" . ( '' !== $detail ? " ({$detail})" : '' ) . "\n";
}

/**
 * Runs this file in a child process and decodes its JSON output.
 *
 * @param string $mode Child mode.
 * @param string $arg  Optional argument.
 *
 * @return array
 */
function drn_child( $mode, ...$args ) {
	$cmd = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' ' . escapeshellarg( $mode );
	foreach ( $args as $arg ) {
		$cmd .= ' ' . escapeshellarg( $arg );
	}

	$output = shell_exec( $cmd . ' 2>&1' );
	$result = json_decode( (string) $output, true );

	return is_array( $result ) ? $result : array( 'error' => trim( (string) $output ) );
}

/**
 * Shows an actual value next to the expected one.
 *
 * @param mixed $expected Expected.
 * @param mixed $actual   Actual.
 *
 * @return string
 */
function drn_diff( $expected, $actual ) {
	return 'expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true );
}

echo "\n=== str_replace( document_root() ) callers normalize their subject ===\n\n";

// Every `str_replace( Util_Environment::document_root(), '', <subject> )` in the plugin root:
// the subject is wrapped in normalize_path(), or is a variable assigned from normalize_path()
// shortly before.
$drn_call_regex = '~str_replace\(\s*(?:Util_Environment|self)::document_root\(\)\s*,\s*\'\'\s*,\s*(.+?)\s*\)\s*;~s';
$drn_sites      = 0;
foreach ( glob( $drn_plugin_dir . '/*.php' ) as $drn_file ) {
	$drn_source = file_get_contents( $drn_file );
	if ( ! preg_match_all( $drn_call_regex, $drn_source, $drn_matches, PREG_OFFSET_CAPTURE ) ) {
		continue;
	}

	foreach ( $drn_matches[1] as $drn_match ) {
		++$drn_sites;
		list( $drn_subject, $drn_offset ) = $drn_match;
		$drn_line                         = substr_count( substr( $drn_source, 0, $drn_offset ), "\n" ) + 1;
		$drn_where                        = basename( $drn_file ) . ':' . $drn_line;

		$drn_ok = false !== strpos( $drn_subject, 'normalize_path(' );
		if ( ! $drn_ok && preg_match( '~^\$\w+$~', $drn_subject ) ) {
			// The enclosing function body, up to the call.
			$drn_before = substr( $drn_source, 0, $drn_offset );
			$drn_before = substr( $drn_before, (int) strrpos( $drn_before, 'function ' ) );
			$drn_ok     = (bool) preg_match( '~' . preg_quote( $drn_subject, '~' ) . '\s*=\s*(?:Util_Environment|self)::normalize_path\(~', $drn_before );
		}

		drn_check( $drn_ok, "{$drn_where}: subject {$drn_subject} is normalized" );
	}
}
drn_check( $drn_sites >= 6, "found the call sites ({$drn_sites})" );

echo "\n=== CDN paths with a Windows document root (real Cdn_Util / Cdn_Core) ===\n\n";

$drn_cdn = drn_child( 'child-cdn' );
foreach (
	array(
		'wp_content_dir' => 'wp-content/x',
		'plugins_dir'    => 'wp-content/plugins/x',
		'uploads_dir'    => 'wp-content/uploads/x',
		'relative'       => '/wp-content/uploads/a.jpg',
	) as $drn_key => $drn_expected
) {
	$drn_actual = $drn_cdn[ $drn_key ] ?? ( $drn_cdn['error'] ?? null );
	drn_check( $drn_expected === $drn_actual, "{$drn_key} => {$drn_expected}", drn_diff( $drn_expected, $drn_actual ) );
}

echo "\n=== document_root() with backslash \$_SERVER input (real Util_Environment) ===\n\n";

$drn_root = sys_get_temp_dir() . '/w3tc-docroot-normalize-' . uniqid();
mkdir( $drn_root, 0777, true );
$drn_expected_root = str_replace( '\\', '/', realpath( $drn_root ) );

foreach (
	array(
		array( 'child-script', 'forward', 'SCRIPT_FILENAME, forward slashes' ),
		array( 'child-script', 'backslash', 'SCRIPT_FILENAME, slashed backslashes' ),
		array( 'child-docroot', 'forward', 'DOCUMENT_ROOT, forward slashes' ),
		array( 'child-docroot', 'backslash', 'DOCUMENT_ROOT, slashed backslashes' ),
	) as list( $drn_child_mode, $drn_style, $drn_label )
) {
	$drn_result = drn_child( $drn_child_mode, $drn_expected_root, $drn_style );
	$drn_root_a = $drn_result['document_root'] ?? ( $drn_result['error'] ?? null );
	$drn_uri_a  = $drn_result['uri_path'] ?? null;

	drn_check( is_string( $drn_root_a ) && false === strpos( $drn_root_a, '\\' ), "{$drn_label}: no backslashes", var_export( $drn_root_a, true ) );
	drn_check( $drn_expected_root === $drn_root_a, "{$drn_label}: the real document root", drn_diff( $drn_expected_root, $drn_root_a ) );
	drn_check(
		'/wp-content/cache/page_enhanced' === $drn_uri_a,
		"{$drn_label}: apache_cache_uri_path() is docroot-relative",
		drn_diff( '/wp-content/cache/page_enhanced', $drn_uri_a )
	);
}

@rmdir( $drn_root );

echo "\n  Total: " . ( $drn_pass + $drn_fail ) . "  Passed: \033[32m{$drn_pass}\033[0m  Failed: \033[31m{$drn_fail}\033[0m\n\n";

exit( $drn_fail > 0 ? 1 : 0 );
