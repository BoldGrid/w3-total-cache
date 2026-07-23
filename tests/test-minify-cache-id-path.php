<?php
/**
 * Standalone regression test for minify filesystem cache-id path containment
 * and the manual minify filename template charset.
 *
 * Run with: php tests/test-minify-cache-id-path.php
 *
 * @package W3TC\Tests
 * @since   X.X.X
 */

if ( realpath( __FILE__ ) !== realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	return;
}

$mcp_pass  = 0;
$mcp_fail  = 0;
$mcp_cases = array();

if ( ! function_exists( 'mcp_assert' ) ) {
	/**
	 * Registers a test result.
	 *
	 * @param string $label       Human-readable description.
	 * @param bool   $expectation Condition that should hold.
	 * @param string $detail      Optional failure context.
	 */
	function mcp_assert( string $label, bool $expectation, string $detail = '' ): void {
		global $mcp_pass, $mcp_fail, $mcp_cases;
		if ( $expectation ) {
			$mcp_cases[] = array( 'PASS', $label );
			++$mcp_pass;
		} else {
			$mcp_cases[] = array( 'FAIL', $label . ( $detail ? " | $detail" : '' ) );
			++$mcp_fail;
		}
	}
}

$handler_src       = file_get_contents( __DIR__ . '/../Minify_MinifiedFileRequestHandler.php' );
$manual_re         = '([a-f0-9]+)\\.([a-zA-Z0-9_-]+)\\.(include(\\-(footer|body))?)\\.[a-f0-9]+\\.(css|js)';
$mcp_define_match  = array();
$defined_re        = '';

if ( is_string( $handler_src ) ) {
	preg_match(
		"/define\\(\\s*'W3TC_MINIFY_MANUAL_FILENAME_REGEX'\\s*,\\s*'([^']+)'\\s*\\)/",
		$handler_src,
		$mcp_define_match
	);
	$defined_re = isset( $mcp_define_match[1] ) ? stripcslashes( $mcp_define_match[1] ) : '';
}

mcp_assert(
	'[1] handler defines tightened manual filename regex',
	$manual_re === $defined_re,
	'got ' . $defined_re
);

$valid_manual   = '74ab2.index.include.deadbeef.js';
$valid_footer   = '74ab2.page-home.include-footer.abc123.css';
$nested_id      = '74ab2.stage/seed.include.deadbeef.js';
$traversal_id   = '74ab2.stage/../../../outside.include.deadbeef.js';
$dot_template   = '74ab2.stage..x.include.deadbeef.js';

mcp_assert(
	'[2] valid manual filename matches',
	(bool) preg_match( '~^' . $manual_re . '$~', $valid_manual )
);
mcp_assert(
	'[3] hyphenated template + include-footer matches',
	(bool) preg_match( '~^' . $manual_re . '$~', $valid_footer )
);
mcp_assert(
	'[4] path separator in template is rejected',
	! preg_match( '~^' . $manual_re . '$~', $nested_id )
);
mcp_assert(
	'[5] parent-directory segments in template are rejected',
	! preg_match( '~^' . $manual_re . '$~', $traversal_id )
);
mcp_assert(
	'[6] dots in template segment are rejected',
	! preg_match( '~^' . $manual_re . '$~', $dot_template )
);

$base_tmp  = sys_get_temp_dir() . '/w3tc_mcp_' . getmypid() . '_' . bin2hex( random_bytes( 4 ) );
$cache_dir = $base_tmp . '/cache/minify';
$outside   = $base_tmp . '/outside-target.js';

mkdir( $cache_dir, 0777, true );
file_put_contents( $cache_dir . '/index.html', '' );
file_put_contents( $outside, "BEFORE\n" );

if ( ! defined( 'W3TC_CACHE_DIR' ) ) {
	define( 'W3TC_CACHE_DIR', $base_tmp . '/cache' );
}

require_once __DIR__ . '/../lib/Minify/Minify/Cache/File.php';

$cache = new \W3TCL\Minify\Minify_Cache_File( $cache_dir );

$ok_store = $cache->store(
	$valid_manual,
	array( 'content' => "var ok = 1;\n" )
);
mcp_assert( '[7] store accepts a flat cache id under the cache root', false !== $ok_store && is_file( $cache_dir . '/' . $valid_manual ) );

$fetched = $cache->fetch( $valid_manual );
mcp_assert(
	'[8] fetch returns stored content for a flat cache id',
	is_array( $fetched ) && isset( $fetched['content'] ) && "var ok = 1;\n" === $fetched['content']
);

$group_id   = '74ab2/default.include.js.id';
$group_path = $cache_dir . '/' . $group_id;
mkdir( dirname( $group_path ), 0777, true );
$group_store = $cache->store(
	$group_id,
	array( 'content' => "group-id\n" )
);
$group_fetch = $cache->fetch( $group_id );
mcp_assert(
	'[9] store accepts get_id_key_group-shaped nested cache ids',
	false !== $group_store && is_file( $group_path )
);
mcp_assert(
	'[10] fetch returns stored content for nested group ids',
	is_array( $group_fetch ) && isset( $group_fetch['content'] ) && "group-id\n" === $group_fetch['content']
);

$before_hash = hash_file( 'sha256', $outside );
$bad_store   = $cache->store(
	'stage/../../../outside-target.js',
	array( 'content' => "AFTER\n" )
);
$after_hash = hash_file( 'sha256', $outside );

mcp_assert( '[11] store rejects parent-directory cache ids', false === $bad_store );
mcp_assert(
	'[12] rejected store does not modify a file outside the cache root',
	$before_hash === $after_hash && "BEFORE\n" === file_get_contents( $outside ),
	'outside file hash changed'
);

$bad_fetch = $cache->fetch( '../outside-target.js' );
mcp_assert( '[13] fetch rejects parent-directory cache ids', false === $bad_fetch );

$bad_size = $cache->getSize( '..\\outside-target.js' );
mcp_assert( '[14] getSize rejects backslash parent-directory segments', false === $bad_size );

$bad_valid = $cache->isValid( 'foo/../outside-target.js', 0 );
mcp_assert( '[15] isValid rejects embedded parent-directory segments', false === $bad_valid );

$bad_abs = $cache->store(
	'/etc/passwd',
	array( 'content' => "ABS\n" )
);
mcp_assert( '[16] store rejects absolute cache ids', false === $bad_abs );

$outside_dir = $base_tmp . '/outside-dir';
$link_dir    = $cache_dir . '/linkout';
mkdir( $outside_dir, 0777, true );
file_put_contents( $outside_dir . '/marker.js', "MARKER\n" );
$symlink_ok = @symlink( $outside_dir, $link_dir );
if ( $symlink_ok ) {
	$before_marker = hash_file( 'sha256', $outside_dir . '/marker.js' );
	$bad_link      = $cache->store(
		'linkout/escaped.js',
		array( 'content' => "ESCAPED\n" )
	);
	$after_marker = hash_file( 'sha256', $outside_dir . '/marker.js' );
	mcp_assert( '[17] store rejects ids under symlinked subdir outside cache root', false === $bad_link );
	mcp_assert(
		'[18] rejected symlink store does not modify the outside target dir',
		$before_marker === $after_marker && ! is_file( $outside_dir . '/escaped.js' ),
		'outside symlink target was written'
	);
} else {
	mcp_assert( '[17] store rejects ids under symlinked subdir outside cache root (skipped: no symlink)', true );
	mcp_assert( '[18] rejected symlink store does not modify the outside target dir (skipped: no symlink)', true );
}

/**
 * Cleanup.
 */
@unlink( $cache_dir . '/' . $valid_manual );
@unlink( $cache_dir . '/' . $valid_manual . '_meta' );
@unlink( $group_path );
@unlink( $group_path . '_meta' );
@rmdir( dirname( $group_path ) );
if ( $symlink_ok ) {
	@unlink( $link_dir );
}
@unlink( $outside_dir . '/marker.js' );
@rmdir( $outside_dir );
@unlink( $cache_dir . '/index.html' );
@rmdir( $cache_dir );
@rmdir( dirname( $cache_dir ) );
@unlink( $outside );
@rmdir( $base_tmp );

echo "\n=== Minify cache-id path containment ===\n\n";
foreach ( $mcp_cases as $case ) {
	$mark = 'PASS' === $case[0] ? "\033[32m✔\033[0m" : "\033[31m✘\033[0m";
	echo "  {$mark}  {$case[1]}\n";
}
echo "\n  Total: " . ( $mcp_pass + $mcp_fail ) . "  Passed: \033[32m{$mcp_pass}\033[0m  Failed: \033[31m{$mcp_fail}\033[0m\n\n";

exit( $mcp_fail > 0 ? 1 : 0 );
