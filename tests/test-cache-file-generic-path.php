<?php
/**
 * Standalone regression test for Disk Enhanced (Cache_File_Generic) path containment
 * and PgCache Disk Enhanced URL-part key construction.
 *
 * Run with: php tests/test-cache-file-generic-path.php
 *
 * @package W3TC\Tests
 * @since   2.10.5
 */

if ( realpath( __FILE__ ) !== realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	return;
}

$cfg_pass  = 0;
$cfg_fail  = 0;
$cfg_cases = array();

if ( ! function_exists( 'cfg_assert' ) ) {
	/**
	 * Registers a test result.
	 *
	 * @param string $label       Human-readable description.
	 * @param bool   $expectation Condition that should hold.
	 * @param string $detail      Optional failure context.
	 */
	function cfg_assert( string $label, bool $expectation, string $detail = '' ): void {
		global $cfg_pass, $cfg_fail, $cfg_cases;
		if ( $expectation ) {
			$cfg_cases[] = array( 'PASS', $label );
			++$cfg_pass;
		} else {
			$cfg_cases[] = array( 'FAIL', $label . ( $detail ? " | $detail" : '' ) );
			++$cfg_fail;
		}
	}
}

$plugin_root = realpath( __DIR__ . '/..' );
$base_tmp    = sys_get_temp_dir() . '/w3tc_cfg_' . getmypid() . '_' . bin2hex( random_bytes( 4 ) );
$cache_root  = $base_tmp . '/cache';
$cache_dir   = $cache_root . '/page_enhanced/example.com';
$outside     = $base_tmp . '/outside-target.html';

mkdir( $cache_dir, 0777, true );
file_put_contents( $outside, "BEFORE\n" );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $plugin_root . '/' );
}

if ( ! defined( 'W3TC_CACHE_FILE_EXPIRE_MAX' ) ) {
	define( 'W3TC_CACHE_FILE_EXPIRE_MAX', 2592000 );
}

if ( ! defined( 'W3TC_CACHE_DIR' ) ) {
	define( 'W3TC_CACHE_DIR', $cache_root );
}

require_once $plugin_root . '/Cache_Base.php';
require_once $plugin_root . '/Util_Environment.php';
require_once $plugin_root . '/Util_File.php';
require_once $plugin_root . '/Cache_File.php';
require_once $plugin_root . '/Cache_File_Generic.php';

$cache = new \W3TC\Cache_File_Generic(
	array(
		'blog_id'          => 0,
		'cache_dir'        => $cache_dir,
		'section'          => 'page',
		'locking'          => false,
		'flush_timelimit'  => 0,
		'use_expired_data' => false,
		'expire'           => 3600,
	)
);

$ok_key   = 'example.com/search/W3TC-PoC/_index_slash.html';
$ok_store = $cache->set(
	$ok_key,
	array(
		'content' => "ok-body\n",
		'headers' => array(),
	)
);
cfg_assert(
	'[1] set accepts a normal Disk Enhanced key under the cache root',
	false !== $ok_store && is_file( $cache_dir . '/' . $ok_key )
);

$fetched = $cache->get_with_old( $ok_key );
cfg_assert(
	'[2] get_with_old returns stored content for a normal key',
	is_array( $fetched )
	&& isset( $fetched[0]['content'] )
	&& "ok-body\n" === $fetched[0]['content']
);

$before_hash = hash_file( 'sha256', $outside );
$bad_store   = $cache->set(
	'example.com/search/W3TC-PoC/../../../outside-target.html',
	array(
		'content' => "AFTER\n",
		'headers' => array(),
	)
);
$after_hash = hash_file( 'sha256', $outside );
cfg_assert( '[3] set rejects parent-directory cache keys', false === $bad_store );
cfg_assert(
	'[4] rejected set does not modify a file outside the cache root',
	$before_hash === $after_hash && "BEFORE\n" === file_get_contents( $outside ),
	'outside file hash changed'
);

$bad_bs = $cache->set(
	'example.com/search/W3TC-PoC/..\\..\\..\\outside-target.html',
	array(
		'content' => "AFTER-BS\n",
		'headers' => array(),
	)
);
cfg_assert( '[5] set rejects backslash parent-directory segments', false === $bad_bs );
cfg_assert(
	'[6] rejected backslash set leaves the outside file unchanged',
	"BEFORE\n" === file_get_contents( $outside )
);

$bad_abs = $cache->set(
	'/etc/passwd',
	array(
		'content' => "ABS\n",
		'headers' => array(),
	)
);
cfg_assert( '[7] set rejects absolute cache keys', false === $bad_abs );

$bad_nul = $cache->set(
	"example.com/x\0/../outside-target.html",
	array(
		'content' => "NUL\n",
		'headers' => array(),
	)
);
cfg_assert( '[8] set rejects NUL-bearing cache keys', false === $bad_nul );

cfg_assert( '[9] exists rejects parent-directory cache keys', false === $cache->exists( '../outside-target.html' ) );
cfg_assert( '[10] delete rejects parent-directory cache keys', false === $cache->delete( '../outside-target.html' ) );
cfg_assert( '[11] hard_delete rejects parent-directory cache keys', false === $cache->hard_delete( '../outside-target.html' ) );

$outside_dir = $base_tmp . '/outside-dir';
$link_dir    = $cache_dir . '/linkout';
mkdir( $outside_dir, 0777, true );
file_put_contents( $outside_dir . '/marker.html', "MARKER\n" );
$symlink_ok = @symlink( $outside_dir, $link_dir );
if ( $symlink_ok ) {
	$before_marker = hash_file( 'sha256', $outside_dir . '/marker.html' );
	$bad_link      = $cache->set(
		'linkout/escaped.html',
		array(
			'content' => "ESCAPED\n",
			'headers' => array(),
		)
	);
	$after_marker = hash_file( 'sha256', $outside_dir . '/marker.html' );
	cfg_assert( '[12] set rejects keys under a symlinked subdir outside the cache root', false === $bad_link );
	cfg_assert(
		'[13] rejected symlink set does not modify the outside target dir',
		$before_marker === $after_marker && ! is_file( $outside_dir . '/escaped.html' ),
		'outside symlink target was written'
	);
} else {
	cfg_assert( '[12] set rejects keys under a symlinked subdir outside the cache root (skipped: no symlink)', true );
	cfg_assert( '[13] rejected symlink set does not modify the outside target dir (skipped: no symlink)', true );
}

// Existing-directory write must still be confined (mkdir_from_safe is skipped when dir exists).
$home_poison = $cache->set(
	'example.com/../../_index_slash.html',
	array(
		'content' => "POISON\n",
		'headers' => array(),
	)
);
cfg_assert( '[14] set rejects traversal that targets an already-existing parent directory', false === $home_poison );
cfg_assert(
	'[15] existing parent outside the page_enhanced host dir was not written',
	! is_file( $cache_root . '/_index_slash.html' )
	&& ! is_file( $base_tmp . '/_index_slash.html' )
);

$grabber_src = file_get_contents( $plugin_root . '/PgCache_ContentGrabber.php' );
cfg_assert(
	'[16] PgCache_ContentGrabber no longer urldecodes Disk Enhanced URL parts',
	is_string( $grabber_src )
	&& false === strpos( $grabber_src, "\$w3tc_key = urldecode( \$w3tc_key );" )
);
cfg_assert(
	'[17] PgCache_ContentGrabber no longer promotes backslashes via [/\\\\]+ collapse',
	is_string( $grabber_src )
	&& false === strpos( $grabber_src, "~[/\\\\\\]+~" )
	&& false !== strpos( $grabber_src, "~/+~" )
);
cfg_assert(
	'[18] PgCache_ContentGrabber rejects parent-directory / backslash / NUL URL parts',
	is_string( $grabber_src )
	&& false !== strpos( $grabber_src, "strpos( \$w3tc_key, '..' )" )
	&& false !== strpos( $grabber_src, "strpos( \$w3tc_key, '\\\\' )" )
);

$generic_src = file_get_contents( $plugin_root . '/Cache_File_Generic.php' );
cfg_assert(
	'[19] Cache_File_Generic mkdir_from_safe uses W3TC_CACHE_DIR (not its parent)',
	is_string( $generic_src )
	&& false !== strpos( $generic_src, 'mkdir_from_safe( $dir, W3TC_CACHE_DIR )' )
	&& false === strpos( $generic_src, 'mkdir_from_safe( $dir, dirname( W3TC_CACHE_DIR ) )' )
);
cfg_assert(
	'[20] Cache_File_Generic defines _resolve_path for write/delete/exists confinement',
	is_string( $generic_src )
	&& false !== strpos( $generic_src, 'function _resolve_path(' )
);

/**
 * Cleanup.
 *
 * @param string $dir Directory path.
 */
function cfg_rmtree( string $dir ): void {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$items = scandir( $dir );
	if ( false === $items ) {
		return;
	}
	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . DIRECTORY_SEPARATOR . $item;
		if ( is_dir( $path ) && ! is_link( $path ) ) {
			cfg_rmtree( $path );
		} else {
			@unlink( $path );
		}
	}
	@rmdir( $dir );
}

@unlink( $cache_dir . '/' . $ok_key );
@unlink( dirname( $cache_dir . '/' . $ok_key ) . '/.htaccess' );
if ( $symlink_ok ) {
	@unlink( $link_dir );
}
@unlink( $outside_dir . '/marker.html' );
@rmdir( $outside_dir );
cfg_rmtree( $base_tmp );
@unlink( $outside );

foreach ( $cfg_cases as $case ) {
	echo $case[0] . ': ' . $case[1] . "\n";
}
echo "\n{$cfg_pass} passed, {$cfg_fail} failed\n";
exit( $cfg_fail > 0 ? 1 : 0 );
