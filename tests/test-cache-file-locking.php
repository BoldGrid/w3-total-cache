<?php
/**
 * Standalone regression test for file-cache locking teardown order.
 *
 * Verifies flock(LOCK_UN) runs before fclose() when locking is enabled.
 * On PHP 8+, calling flock() after fclose() raises an uncaught TypeError.
 *
 * Run with: php tests/test-cache-file-locking.php
 *
 * Exit code 0 = all pass, non-zero = failures.
 *
 * @package W3TC\Tests
 * @since   2.10.2
 */

if ( realpath( __FILE__ ) !== realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	return;
}

$plugin_root = realpath( __DIR__ . '/..' );
$cache_root  = $plugin_root . '/.cursor/working/test-cache-file-locking-cache';

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

$pass = 0;

/**
 * Remove a directory tree.
 *
 * @since 2.10.2
 *
 * @param string $dir Directory path.
 *
 * @return void
 */
function cfl_rmtree( $dir ) {
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
		if ( is_dir( $path ) ) {
			cfl_rmtree( $path );
		} else {
			unlink( $path );
		}
	}

	rmdir( $dir );
}

/**
 * Record a passing assertion.
 *
 * @since 2.10.2
 *
 * @param string $message Description.
 *
 * @return void
 */
function cfl_pass( $message ) {
	global $pass;
	++$pass;
	echo "PASS: {$message}\n";
}

/**
 * Record a failing assertion and exit.
 *
 * @since 2.10.2
 *
 * @param string $message Description.
 *
 * @return void
 */
function cfl_fail( $message ) {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

/**
 * Assert a callable completes without TypeError.
 *
 * @since 2.10.2
 *
 * @param callable $callable Operation to run.
 * @param string   $label    Failure label.
 *
 * @return mixed Return value from callable.
 */
function cfl_assert_no_type_error( $callable, $label ) {
	try {
		return call_user_func( $callable );
	} catch ( \TypeError $e ) {
		cfl_fail( $label . ': ' . $e->getMessage() );
	}
}

cfl_rmtree( $cache_root );
mkdir( $cache_root, 0777, true );

$config = array(
	'cache_dir'        => $cache_root,
	'locking'          => true,
	'use_expired_data' => true,
	'blog_id'          => 1,
	'module'           => 'pgcache',
	'host'             => 'example.test',
	'instance_id'      => 1,
);

// ---------------------------------------------------------------------------
// Cache_File (db/object cache and pgcache "file" engine).
// ---------------------------------------------------------------------------

$file_cache = new \W3TC\Cache_File( $config );
$file_key   = 'locking-file-key';
$file_value = array(
	'hello' => 'world',
	'ts'    => time(),
);

$set_ok = cfl_assert_no_type_error(
	function () use ( $file_cache, $file_key, $file_value ) {
		return $file_cache->set( $file_key, $file_value, 3600, 'default' );
	},
	'Cache_File::set() raised TypeError with locking enabled'
);

if ( ! $set_ok ) {
	cfl_fail( 'Cache_File::set() returned false' );
}
cfl_pass( 'Cache_File::set() with locking enabled' );

$got = cfl_assert_no_type_error(
	function () use ( $file_cache, $file_key ) {
		return $file_cache->get( $file_key, 'default' );
	},
	'Cache_File::get() raised TypeError with locking enabled'
);

if ( $got !== $file_value ) {
	cfl_fail( 'Cache_File round-trip get() mismatch after set()' );
}
cfl_pass( 'Cache_File round-trip get() after set()' );

$del_ok = cfl_assert_no_type_error(
	function () use ( $file_cache, $file_key ) {
		return $file_cache->delete( $file_key, 'default' );
	},
	'Cache_File::delete() raised TypeError with locking + use_expired_data'
);

if ( ! $del_ok ) {
	cfl_fail( 'Cache_File::delete() returned false' );
}
cfl_pass( 'Cache_File::delete() with locking + use_expired_data' );

// ---------------------------------------------------------------------------
// Cache_File_Generic (default pgcache "file_generic" / Disk: Enhanced engine).
// ---------------------------------------------------------------------------

$generic_cache = new \W3TC\Cache_File_Generic( $config );
$generic_key   = 'locking-generic-key';
$generic_value = array(
	'content' => '<html><body>generic</body></html>',
	'headers' => array(),
);

$generic_set_ok = cfl_assert_no_type_error(
	function () use ( $generic_cache, $generic_key, $generic_value ) {
		return $generic_cache->set( $generic_key, $generic_value, 3600, 'default' );
	},
	'Cache_File_Generic::set() raised TypeError with locking enabled'
);

if ( ! $generic_set_ok ) {
	cfl_fail( 'Cache_File_Generic::set() returned false' );
}
cfl_pass( 'Cache_File_Generic::set() with locking enabled' );

$generic_got = cfl_assert_no_type_error(
	function () use ( $generic_cache, $generic_key ) {
		return $generic_cache->get( $generic_key, 'default' );
	},
	'Cache_File_Generic::get() raised TypeError with locking enabled'
);

if ( ! is_array( $generic_got ) || false === strpos( $generic_got['content'], 'generic' ) ) {
	cfl_fail( 'Cache_File_Generic round-trip get() mismatch after set()' );
}
cfl_pass( 'Cache_File_Generic round-trip get() after set()' );

cfl_rmtree( $cache_root );

echo "All {$pass} checks passed on PHP " . PHP_VERSION . "\n";
exit( 0 );
