<?php
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
$update = $mysqli->query("update $table set post_title = '$title' where post_type = 'post'");

echo $update ? 'ok' : 'error';
