<?php
/**
 * Legacy W3 Total Cache 0.9.4 compatibility shims.
 *
 * @package W3TC
 *
 * phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed, WordPress.NamingConventions.PrefixAllGlobals -- Legacy shim file intentionally mixes stubs and a class.
 */

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die;
}

if ( @is_dir( W3TC_DIR ) && file_exists( W3TC_DIR . '/w3-total-cache-api.php' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	require_once W3TC_DIR . '/w3-total-cache-api.php';
}

define( 'W3TC_LIB_W3_DIR', W3TC_DIR );

/**
 * Legacy require-once shim.
 *
 * @param string $w3tc_file File path.
 *
 * @return void
 */
function w3_require_once( $w3tc_file ) {
}

/**
 * Legacy dbcluster probe.
 *
 * @return bool
 */
function w3_is_dbcluster() {
	return false;
}

/**
 * Legacy database accessor.
 */
class W3_Db {
	/**
	 * Returns the db cache instance.
	 *
	 * @return \W3TC\DbCache_Wpdb
	 */
	public static function instance() {
		return \W3TC\DbCache_Wpdb::instance();
	}
}
