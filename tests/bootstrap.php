<?php
/**
 * File: bootstrap.php
 *
 * Bootstrap file for tests.
 *
 * @package    W3TC
 * @subpackage W3TC/tests
 * @author     BoldGrid <development@boldgrid.com>
 * @since      2.3.1
 */

$_tests_dir = rtrim( getenv( 'WP_TESTS_DIR' ), '/' );

if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

if ( ! defined( 'W3TC_DIR' ) ) {
	define( 'W3TC_DIR', dirname( __DIR__ ) );
}

/**
 * PHPUnit loads the plugin from the checkout, not from the test
 * WordPress install's WP_PLUGIN_DIR. Extension paths are relative to
 * the plugins parent (e.g. w3-total-cache/Extension_*.php).
 */
if ( ! defined( 'W3TC_EXTENSION_DIR' ) ) {
	define( 'W3TC_EXTENSION_DIR', dirname( W3TC_DIR ) );
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
 * @since 2.3.1
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
 * Normalize $_SERVER so Util_Environment::document_root() resolves under PHPUnit CLI.
 *
 * WP's test bootstrap sets PHP_SELF to /index.php while PATH_TRANSLATED points at
 * the PHPUnit binary, which yields a non-resolving document root.
 *
 * @since 2.10.0
 */
function w3tc_phpunit_normalize_server_docroot() {
	$_SERVER['DOCUMENT_ROOT'] = \untrailingslashit( ABSPATH );
	unset( $_SERVER['PATH_TRANSLATED'] );
}

/**
 * Manually load the plugin being tested.
 *
 * @since 2.3.1
 */
function _manually_load_plugin() {
	w3tc_phpunit_normalize_server_docroot();
	require W3TC_DIR . '/w3-total-cache.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

// Create the W3TC configuration directory.
if ( ! file_exists( ABSPATH . 'wp-content/w3tc-config' ) ) {
	mkdir( ABSPATH . 'wp-content/w3tc-config' );
}

// Remove old configuration file.
if ( file_exists( ABSPATH . 'wp-content/w3tc-config/master.php' ) ) {
	unlink( ABSPATH . 'wp-content/w3tc-config/master.php' );
}
