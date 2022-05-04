<?php
require_once dirname(__FILE__) . '/wp-load.php';
$blog = 0;
$domain = 'wp.sandbox';
$path = '/';
$content = '';
global $wpdb;

if( is_multisite() ) {
	$blog_id = \W3TC\Util_Environment::blog_id();
	$blog = $blog_id;
	$blog_details = get_blog_details($blog);
	$domain = $blog_details->domain;
	$path = $blog_details->path;
}

$action = $_REQUEST['action'];
$title = $_REQUEST['title'];

switch ($action) {
	case 'get_cache':

		$cache = $wpdb->get_var("Select ID from $wpdb->posts where post_title = '$title'");
		echo $cache;
		break;

	case 'update_record':
		update_record_directly();
		break;
}

function update_record_directly() {
	$mysqli = new mysqli("localhost", "wordpress", "wordpress", "wordpress");
	$title = $_REQUEST['title'];
	if ($mysqli->connect_errno) {
		echo 'error';
		exit();
	}
	$table = 'wp_posts';
	if ( $_SERVER['HTTP_HOST'] == 'b2.wp.sandbox' ||
		strpos($_SERVER['REQUEST_URI'], '/b2/') !== false ) {
		$table = 'wp_2_posts';
	}
	$update = $mysqli->query("update $table set post_title = '$title' where ID = 1");

	echo $update ? 'ok' : 'error';
}
