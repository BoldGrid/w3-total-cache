<?php
/**
 * File: reject-query-words.php
 *
 * Database cache: Reject query words.
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput, WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.DB, WordPress.WP.CapitalPDangit.Misspelled
 */

require __DIR__ . '/wp-load.php';

$content = '';
$blog_id = $_REQUEST['blog_id'];
$action  = $_REQUEST['action'];
$title   = $_REQUEST['title'];

switch ( $action ) {
	case 'get_cache':
		$cache = $wpdb->get_var( "Select ID from $wpdb->posts where post_title = '$title'" );
		echo '<div id="post_id">' . esc_html( $cache ) . '</div>';
		break;

	case 'update_record':
		update_record_directly();
		break;

	default:
		var_dump( 'unknown action' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_dump
}

/**
 * Update database record directly.
 */
function update_record_directly() {
	$mysqli = new mysqli( 'localhost', 'wordpress', 'wordpress', 'wordpress' );
	$title  = $_REQUEST['title'];

	if ( $mysqli->connect_errno ) {
		echo 'error';
		exit;
	}

	global $wpdb;

	$table  = $wpdb->posts;
	$update = $mysqli->query( "update $table set post_title = '$title' where ID = 1" );

	echo $update ? 'ok' : 'error';
}
