<?php
/**
 * Standalone test for Util_WpFile::write_to_file() with empty content.
 *
 * file_put_contents() returns int(0) for an empty write, and a truthiness test read that as a
 * failure: a file that had just been written raised the filesystem credentials form.
 *
 * Run with: php tests/test-wpfile-empty-write.php
 *
 * @package W3TC\Tests
 * @since   X.X.X
 */

if ( \realpath( __FILE__ ) !== \realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	return;
}

$wef_pass  = 0;
$wef_fail  = 0;
$wef_cases = array();

if ( ! \function_exists( 'wef_assert' ) ) {
	/**
	 * Records one case.
	 *
	 * @param string $label       Case label.
	 * @param bool   $expectation Whether the case holds.
	 * @param string $detail      What was seen instead.
	 *
	 * @return void
	 */
	function wef_assert( string $label, bool $expectation, string $detail = '' ): void {
		global $wef_pass, $wef_fail, $wef_cases;

		if ( $expectation ) {
			++$wef_pass;
			$wef_cases[] = array( 'PASS', $label );

			return;
		}

		++$wef_fail;
		$wef_cases[] = array( 'FAIL', $label . ( '' !== $detail ? ' | ' . $detail : '' ) );
	}
}

// The failure path requires these; WordPress is not loaded here.
if ( ! \function_exists( 'request_filesystem_credentials' ) ) {
	/**
	 * Stub: credentials are never obtained in this harness.
	 *
	 * @return false
	 */
	function request_filesystem_credentials() {
		return false;
	}
}

if ( ! \function_exists( 'WP_Filesystem' ) ) {
	/**
	 * Stub: the filesystem abstraction is never available here.
	 *
	 * @return false
	 */
	function WP_Filesystem() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
		return false;
	}
}

if ( ! \function_exists( 'esc_url_raw' ) ) {
	/**
	 * Stub.
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	function esc_url_raw( $url ) {
		return $url;
	}
}

if ( ! \function_exists( 'wp_unslash' ) ) {
	/**
	 * Stub.
	 *
	 * @param string $value Value.
	 *
	 * @return string
	 */
	function wp_unslash( $value ) {
		return $value;
	}
}

$wef_root = \sys_get_temp_dir() . '/w3tc-wef-' . \getmypid();
\mkdir( $wef_root . '/wp-admin/includes', 0777, true );
\file_put_contents( $wef_root . '/wp-admin/includes/file.php', "<?php\n" );
\file_put_contents( $wef_root . '/wp-admin/includes/template.php', "<?php\n" );

\define( 'ABSPATH', $wef_root . '/' );
\define( 'W3TC', true );

require_once __DIR__ . '/../Util_WpFile_FilesystemOperationException.php';
require_once __DIR__ . '/../Util_WpFile_FilesystemWriteException.php';
require_once __DIR__ . '/../Util_WpFile.php';

// [1] The regression: an empty write is a successful write.
$wef_file = $wef_root . '/nginx.conf';
\file_put_contents( $wef_file, "# BEGIN W3TC Page Cache core\n# END W3TC Page Cache core\n" );

$wef_thrown = '';

try {
	\W3TC\Util_WpFile::write_to_file( $wef_file, '' );
} catch ( \Exception $wef_e ) {
	$wef_thrown = \get_class( $wef_e );
}

wef_assert(
	'[1] emptying a rules file does not ask for filesystem credentials',
	'' === $wef_thrown,
	'threw: ' . $wef_thrown
);

wef_assert(
	'[2] and the file really is empty afterwards',
	\file_exists( $wef_file ) && '' === \file_get_contents( $wef_file ),
	'contents: ' . \var_export( \file_get_contents( $wef_file ), true )
);

// [3] Ordinary content still lands.
\W3TC\Util_WpFile::write_to_file( $wef_file, "rules\n" );

wef_assert(
	'[3] a non-empty write is unaffected',
	"rules\n" === \file_get_contents( $wef_file ),
	'contents: ' . \var_export( \file_get_contents( $wef_file ), true )
);

// [4] A write that really fails still reports, so the guard did not swallow the failure path.
$wef_thrown = '';

try {
	\W3TC\Util_WpFile::write_to_file( $wef_root . '/no-such-directory/nginx.conf', '' );
} catch ( \Exception $wef_e ) {
	$wef_thrown = \get_class( $wef_e );
}

wef_assert(
	'[4] an unwritable path still raises the write exception',
	'W3TC\Util_WpFile_FilesystemWriteException' === $wef_thrown,
	'threw: ' . ( '' === $wef_thrown ? '(nothing)' : $wef_thrown )
);

\unlink( $wef_root . '/wp-admin/includes/file.php' );
\unlink( $wef_root . '/wp-admin/includes/template.php' );
\unlink( $wef_file );
\rmdir( $wef_root . '/wp-admin/includes' );
\rmdir( $wef_root . '/wp-admin' );
\rmdir( $wef_root );

foreach ( $wef_cases as $wef_case ) {
	echo '  ' . $wef_case[0] . '  ' . $wef_case[1] . "\n";
}

echo "\n  Total: " . ( $wef_pass + $wef_fail ) . '  Passed: ' . $wef_pass . '  Failed: ' . $wef_fail . "\n";

exit( $wef_fail > 0 ? 1 : 0 );
