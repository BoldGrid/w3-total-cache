<?php
/**
 * File: db-never-cache-pages.php
 *
 * Template Name: Database cache: Never cache pages
 * Template Post Type: post, page
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput, WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.DB.RestrictedClasses.mysql__mysqli, WordPress.WP.CapitalPDangit.Misspelled
 */

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

$update = $mysqli->query( "update $table set post_title = '$title' where post_type = 'post'" );

echo $update ? 'ok' : 'error';
