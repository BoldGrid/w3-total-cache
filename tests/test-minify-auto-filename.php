<?php
/**
 * Standalone checks for auto minify filenames and stored file-list bases.
 *
 * Run with: php tests/test-minify-auto-filename.php
 *
 * @package W3TC\Tests
 * @since   X.X.X
 */

if ( realpath( __FILE__ ) !== realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	return;
}

$maf_pass  = 0;
$maf_fail  = 0;
$maf_cases = array();

/**
 * Registers a test result.
 *
 * @param string $label       Human-readable description.
 * @param bool   $expectation Condition that should hold.
 * @param string $detail      Optional failure context.
 */
function maf_assert( string $label, bool $expectation, string $detail = '' ): void {
	global $maf_pass, $maf_fail, $maf_cases;
	if ( $expectation ) {
		$maf_cases[] = array( 'PASS', $label );
		++$maf_pass;
	} else {
		$maf_cases[] = array( 'FAIL', $label . ( $detail ? " | $detail" : '' ) );
		++$maf_fail;
	}
}

$handler_src = file_get_contents( __DIR__ . '/../Minify_MinifiedFileRequestHandler.php' );
$auto_re     = '([a-zA-Z0-9_-]+)\\.(css|js)';
$define      = array();

if ( is_string( $handler_src ) ) {
	preg_match(
		"/define\\(\\s*'W3TC_MINIFY_AUTO_FILENAME_REGEX'\\s*,\\s*'([^']+)'\\s*\\)/",
		$handler_src,
		$define
	);
}

$defined_re = isset( $define[1] ) ? stripcslashes( $define[1] ) : '';

maf_assert(
	'[1] auto filename regex is hash plus css or js',
	$auto_re === $defined_re,
	'got ' . $defined_re
);

$samples = array(
	'864c2.js'                   => true,
	'Ab_c-1.css'                 => true,
	'864c2.js?x12345'            => false,
	'864c2.js?nested/carrier.js' => false,
	'864c2.js?x12345/extra.js'   => false,
	'864c2.js/nested/carrier.js' => false,
	'../864c2.js'                => false,
);

foreach ( $samples as $sample => $expect ) {
	$matched = (bool) preg_match( '~^' . $auto_re . '$~', $sample );
	maf_assert(
		'[2] auto filename ' . $sample . ( $expect ? ' matches' : ' is rejected' ),
		$matched === $expect,
		$matched ? 'matched' : 'rejected'
	);
}

/**
 * Applies the handler's version-suffix strip, then the auto filename regex.
 *
 * @param string $file Requested filename.
 *
 * @return bool
 */
function maf_accepts_after_version_strip( string $file ): bool {
	if ( preg_match( '~(.+)(\?x[0-9]{5})$~', $file, $m ) ) {
		$file = $m[1];
	}

	return (bool) preg_match( '~^([a-zA-Z0-9_-]+)\\.(css|js)$~', $file );
}

maf_assert(
	'[3] handler still strips the ?x##### version suffix before the filename check',
	is_string( $handler_src ) && false !== strpos( $handler_src, '~(.+)(\?x[0-9]{5})$~' )
);
maf_assert( '[4] hash.js?x12345 is accepted after the version-suffix strip', maf_accepts_after_version_strip( '864c2.js?x12345' ) );
maf_assert( '[5] hash.js?nested/carrier.js is rejected after the version-suffix strip', ! maf_accepts_after_version_strip( '864c2.js?nested/carrier.js' ) );
maf_assert(
	'[6] auto cache id is hash plus extension',
	is_string( $handler_src ) && false !== strpos( $handler_src, "\$cache_id = \$hash . '.' . \$type;" )
);
maf_assert(
	'[7] cache id passed to setCacheId is the parsed id',
	is_string( $handler_src )
		&& false !== strpos( $handler_src, 'Minify::setCacheId( $cache_id )' )
		&& false === strpos( $handler_src, 'Minify::setCacheId( $w3tc_file )' )
);
maf_assert(
	'[8] hash branch drops the request base',
	is_string( $handler_src ) && (bool) preg_match(
		"/if\\s*\\(\\s*\\\$hash\\s*\\)\\s*\\{.*?unset\\(\\s*\\\$_GET\\[\\s*'b'\\s*\\]\\s*\\)/s",
		$handler_src
	)
);
maf_assert(
	'[8b] dropped request base is restored after serve',
	is_string( $handler_src )
		&& false !== strpos( $handler_src, '$saved_request_base = $_GET[\'b\'];' )
		&& substr_count( $handler_src, '$_GET[\'b\'] = $saved_request_base;' ) >= 3
);

$base_tmp = sys_get_temp_dir() . '/w3tc_maf_' . getmypid() . '_' . bin2hex( random_bytes( 4 ) );
$docroot  = $base_tmp . '/docroot';
$minify   = $docroot . '/wp-content/cache/minify';

mkdir( $docroot . '/assets', 0777, true );
mkdir( $minify . '/nested', 0777, true );
file_put_contents( $docroot . '/app.js', "var app = 1;\n" );
file_put_contents( $docroot . '/assets/theme.js', "var theme = 1;\n" );
file_put_contents( $minify . '/nested/carrier.js', "var carrier = 1;\n" );
file_put_contents( $minify . '/minify_remote.js', "var remote = 1;\n" );

$link_ok = @symlink( $minify . '/nested', $docroot . '/assets/via-link' );

if ( ! defined( 'W3TC_CACHE_MINIFY_DIR' ) ) {
	define( 'W3TC_CACHE_MINIFY_DIR', $minify );
}

require_once __DIR__ . '/minify-f-array-stubs.php';

$lib = __DIR__ . '/../lib/Minify/Minify';
require_once $lib . '/Logger.php';
require_once $lib . '/Source.php';
require_once $lib . '/Controller/Base.php';
require_once $lib . '/Controller/MinApp.php';

\W3TC\Util_Environment::$docroot = $docroot;

/**
 * Resolves f_array through the real controller.
 *
 * @param array       $f_array File list.
 * @param string|null $base    Optional request base.
 *
 * @return string[] Basenames that became sources.
 */
function maf_resolve( array $f_array, ?string $base = null ): array {
	unset( $_GET['g'], $_GET['b'], $_GET['ext'] );
	$_GET['f_array'] = $f_array;
	$_GET['ext']     = 'js';
	if ( null !== $base ) {
		$_GET['b'] = $base;
	}

	$controller = new \W3TCL\Minify\Minify_Controller_MinApp();
	$controller->setupSources( array() );

	$served = array();
	foreach ( $controller->sources as $source ) {
		if ( isset( $source->filepath ) && null !== $source->filepath ) {
			$served[] = basename( $source->filepath );
		}
	}

	return $served;
}

$served = maf_resolve( array( 'app.js' ) );
maf_assert(
	'[9] document-root app.js is served',
	in_array( 'app.js', $served, true ),
	'served=' . implode( ',', $served )
);

$served = maf_resolve( array( 'theme.js' ), 'assets' );
maf_assert(
	'[10] request base outside the minify cache still serves theme.js',
	in_array( 'theme.js', $served, true ),
	'served=' . implode( ',', $served )
);

$served = maf_resolve( array( 'wp-content/cache/minify/nested/carrier.js' ) );
maf_assert(
	'[11] a file list entry under the minify cache is skipped',
	! in_array( 'carrier.js', $served, true ),
	'served=' . implode( ',', $served )
);

$served = maf_resolve( array( 'carrier.js' ), 'wp-content/cache/minify/nested' );
maf_assert(
	'[12] a request base inside the minify cache is skipped',
	! in_array( 'carrier.js', $served, true ),
	'served=' . implode( ',', $served )
);

$served = maf_resolve(
	array(
		new \W3TCL\Minify\Minify_Source(
			array(
				'filepath' => $minify . '/minify_remote.js',
			)
		),
	)
);
maf_assert(
	'[13] a handler-built source under the minify cache is still served',
	in_array( 'minify_remote.js', $served, true ),
	'served=' . implode( ',', $served )
);

if ( $link_ok ) {
	$served = maf_resolve( array( 'via-link/carrier.js' ), 'assets' );
	maf_assert(
		'[14] a link under the document root that points into the minify cache is skipped',
		! in_array( 'carrier.js', $served, true ),
		'served=' . implode( ',', $served )
	);
} else {
	maf_assert( '[14] symlink case skipped: symlink() unavailable', true );
}

foreach (
	array(
		$docroot . '/app.js',
		$docroot . '/assets/theme.js',
		$docroot . '/assets/via-link',
		$minify . '/nested/carrier.js',
		$minify . '/minify_remote.js',
	) as $path
) {
	if ( is_link( $path ) || is_file( $path ) ) {
		unlink( $path );
	}
}
@rmdir( $docroot . '/assets' );
@rmdir( $minify . '/nested' );
@rmdir( $minify );
@rmdir( $docroot . '/wp-content/cache' );
@rmdir( $docroot . '/wp-content' );
@rmdir( $docroot );
@rmdir( $base_tmp );

echo "\n=== Minify auto filename ===\n\n";
foreach ( $maf_cases as $case ) {
	$mark = 'PASS' === $case[0] ? "\033[32m✔\033[0m" : "\033[31m✘\033[0m";
	echo "  {$mark}  {$case[1]}\n";
}
echo "\n  Total: " . ( $maf_pass + $maf_fail ) . "  Passed: \033[32m{$maf_pass}\033[0m  Failed: \033[31m{$maf_fail}\033[0m\n\n";

exit( $maf_fail > 0 ? 1 : 0 );
