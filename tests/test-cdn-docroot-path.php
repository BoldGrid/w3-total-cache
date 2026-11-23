<?php
/**
 * Standalone regression test for Cdn_Core docroot path containment.
 *
 * Run with: php tests/test-cdn-docroot-path.php
 *
 * @package W3TC\Tests
 * @since   2.10.0
 */

if ( realpath( __FILE__ ) !== realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	return;
}

$cdu_pass = 0;
$cdu_fail = 0;

/**
 * Mirrors {@see W3TC\Cdn_Core::docroot_filename_to_absolute_path()} containment rules.
 *
 * @param string $w3tc_file   Candidate path.
 * @param string $docroot_real Resolved document root.
 *
 * @return string
 */
function cdu_resolve_under_docroot( $w3tc_file, $docroot_real ) {
	if ( ! is_string( $w3tc_file ) || '' === $w3tc_file ) {
		return '';
	}

	if ( false !== strpos( $w3tc_file, '..' ) ) {
		return '';
	}

	if ( '/' !== DIRECTORY_SEPARATOR ) {
		$w3tc_file = str_replace( '/', DIRECTORY_SEPARATOR, $w3tc_file );
	}

	if ( function_exists( 'path_is_absolute' ) && path_is_absolute( $w3tc_file ) ) {
		$candidate = $w3tc_file;
	} else {
		$candidate = $docroot_real . DIRECTORY_SEPARATOR . ltrim( $w3tc_file, '/\\' );
	}

	$resolved = realpath( $candidate );
	if ( false === $resolved ) {
		$parts     = explode( '/', str_replace( '\\', '/', $candidate ) );
		$absolutes = array();
		foreach ( $parts as $part ) {
			if ( '.' === $part || '' === $part ) {
				continue;
			}
			if ( '..' === $part ) {
				array_pop( $absolutes );
				continue;
			}
			$absolutes[] = $part;
		}
		$resolved = implode( '/', $absolutes );
	}

	$docroot_norm   = str_replace( '\\', '/', $docroot_real );
	$resolved_norm  = str_replace( '\\', '/', $resolved );
	$docroot_prefix = $docroot_norm . '/';

	if ( $resolved_norm !== $docroot_norm && 0 !== strpos( $resolved_norm, $docroot_prefix ) ) {
		return '';
	}

	return $resolved;
}

/**
 * @param bool   $expect_pass Expected pass/fail.
 * @param string $label       Case label.
 * @param string $input       Input path.
 * @param string $docroot     Document root.
 */
function cdu_case( $expect_pass, $label, $input, $docroot ) {
	global $cdu_pass, $cdu_fail;

	$result = cdu_resolve_under_docroot( $input, $docroot );
	$ok     = $expect_pass ? ( '' !== $result ) : ( '' === $result );

	if ( $ok ) {
		++$cdu_pass;
		echo "  \033[32m✔\033[0m  {$label}\n";
		return;
	}

	++$cdu_fail;
	echo "  \033[31m✘\033[0m  {$label} (got " . var_export( $result, true ) . ")\n";
}

$root = sys_get_temp_dir() . '/w3tc-docroot-path-' . uniqid();
mkdir( $root, 0777, true );
$inside = $root . '/inside.txt';
file_put_contents( $inside, 'ok' );
$outside = sys_get_temp_dir() . '/w3tc-docroot-outside-' . uniqid() . '.txt';
file_put_contents( $outside, 'secret' );

echo "\n=== CDN docroot path containment ===\n\n";

cdu_case( true, 'relative file under docroot resolves', 'inside.txt', $root );
cdu_case( false, 'absolute outside docroot is rejected', $outside, $root );
cdu_case( false, 'traversal escapes docroot', '../' . basename( $outside ), $root );
cdu_case( false, 'embedded traversal is rejected', 'foo/../../outside', $root );

@unlink( $inside );
@unlink( $outside );
@rmdir( $root );

echo "\n  Total: " . ( $cdu_pass + $cdu_fail ) . "  Passed: \033[32m{$cdu_pass}\033[0m  Failed: \033[31m{$cdu_fail}\033[0m\n\n";

exit( $cdu_fail > 0 ? 1 : 0 );
