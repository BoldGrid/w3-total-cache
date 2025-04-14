<?php
/**
 * File: DbCache_WpdbBase.php
 *
 * @package W3TC
 */

namespace W3TC;

global $wp_version;

if ( version_compare( $wp_version, '6.1-beta1', '>=' ) ) {
	require_once ABSPATH . WPINC . '/class-wpdb.php';
} else {
	require_once ABSPATH . WPINC . '/wp-db.php';
}

/**
 * Class DbCache_WpdbBase
 */
class DbCache_WpdbBase extends \wpdb {
}
