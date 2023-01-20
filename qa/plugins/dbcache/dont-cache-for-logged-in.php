<?php
/**
 * File: dont-cache-for-logged-in
 *
 * DB cache: Don't cache logged-in users.
 *
 * Template Name: Database cache: Don't cache logged-in users
 * Template Post Type: post, page
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput, WordPress.DB, WordPress.WP.CapitalPDangit.Misspelled
 */

require __DIR__ . '/wp-load.php';

$blog    = 0;
$domain  = 'wp.sandbox';
$path    = '/';
$content = '';

global $wpdb;

if ( is_multisite() ) {
	$blog_id      = \W3TC\Util_Environment::blog_id();
	$blog         = $blog_id;
	$blog_details = get_blog_details( $blog );
	$domain       = $blog_details->domain;
	$path         = $blog_details->path;
}

$action = $_REQUEST['action'];
$title  = $_REQUEST['title'];

switch ( $action ) {
	case 'get_cache':
		$cache = $wpdb->get_var( "Select ID from $wpdb->posts where post_title = '$title'" );
		echo esc_html( $cache );
		break;

	case 'update_record':
		update_record_directly();
		break;
}

/**
 * Update database record directly.
 */
function update_record_directly() {
	$mysqli = new mysqli( 'localhost', 'wordpress', 'wordpress', 'wordpress' );
	$title  = $_REQUEST['title'];

	if ( $mysqli->connect_errno ) {
		echo 'error';
		exit();
	}

	$table = 'wp_posts';

	if ( 'b2.wp.sandbox' === $_SERVER['HTTP_HOST'] || strpos( $_SERVER['REQUEST_URI'], '/b2/' ) !== false ) {
		$table = 'wp_2_posts';
	}

	$update = $mysqli->query( "update $table set post_title = '$title' where ID = 1" );

	echo $update ? 'ok' : 'error';
}
