<?php
/**
 * File: bootstrap.php
 *
 * Bootstrap file for tests.
 *
 * @package    W3TC
 * @subpackage W3TC/tests
 * @author     BoldGrid <development@boldgrid.com>
 * @since      X.X.X
 */

$_tests_dir = rtrim( getenv( 'WP_TESTS_DIR' ), '/' );

if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

if ( ! defined( 'W3TC_DIR' ) ) {
	define( 'W3TC_DIR', dirname( __DIR__ ) );
}

// Require necessary files.
$files = array(

	/*
	 * Yoast/PHPUnit-Polyfills, required for running the WP test suite.
	 * Please see https://make.wordpress.org/core/2021/09/27/changes-to-the-wordpress-core-php-test-suite/
	 *
	 * The WP Core test suite can now run on all PHPUnit versions between PHPUnit 5.7.21 up to the latest
	 * release (at the time of writing: PHPUnit 9.5.10), which allows for running the test suite against
	 * all supported PHP versions using the most appropriate PHPUnit version for that PHP version.
	 */
	'vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php',
);

foreach ( $files as $file ) {
	require_once W3TC_DIR . '/' . $file;
}

/**
 * Debug to console.
 *
 * @since X.X.X
 *
 * @param mixed $var Message to write to STDERR.
 */
function phpunit_error_log( $var ) {
	fwrite( // phpcs:ignore
		STDERR,
		"\n\n## --------------------\n" .
			print_r( $var, 1 ) . // phpcs:ignore
		"\n## ------------------\n\n"
	);
}

/**
 * Manually load the plugin being tested.
 *
 * @since X.X.X
 */
function _manually_load_plugin() {
	require W3TC_DIR . '/w3-total-cache.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

// Create the W3TC configuration directory.
mkdir( ABSPATH . 'wp-content/w3tc-config' );

// Remove old configuration file.
unlink( ABSPATH . 'wp-content/w3tc-config/master.php' );
