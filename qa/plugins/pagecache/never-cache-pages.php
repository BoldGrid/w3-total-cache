<?php
/**
 * File: never-cache-pages.php
 *
 * Page cache: Never cache pages test.
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput, WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.DB.RestrictedClasses.mysql__mysqli, WordPress.WP.CapitalPDangit.Misspelled
 */

$mysqli  = new mysqli( 'localhost', 'wordpress', 'wordpress', 'wordpress' );
$title   = $_REQUEST['title'];
$post_id = $_REQUEST['post_id'];

if ( $mysqli->connect_errno ) {
	echo 'error';
	exit;
}

$table = 'wp_posts';

if ( 'b2.wp.sandbox' === $_SERVER['HTTP_HOST'] || strpos( $_SERVER['REQUEST_URI'], '/b2/' ) !== false ) {
	$table = 'wp_2_posts';
}

$update = $mysqli->query( "update $table set post_title = '$title' where ID = $post_id" );

echo $update ? 'ok' : 'error';
