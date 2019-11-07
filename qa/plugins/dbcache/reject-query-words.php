<?php
require_once dirname(__FILE__) . '/wp-load.php';

$content = '';
$blog_id = $_REQUEST['blog_id'];
$action = $_REQUEST['action'];
$title = $_REQUEST['title'];

switch ($action) {
	case 'get_cache':
		$cache = $wpdb->get_var("Select ID from $wpdb->posts where post_title = '$title'");
		echo '<div id="post_id">' . $cache . '</div>';
		break;

	case 'update_record':
		update_record_directly();
		break;

	default:
		var_dump('unknown action');
}

function update_record_directly() {
	$mysqli = new mysqli("localhost", "wordpress", "wordpress", "wordpress");
	$title = $_REQUEST['title'];
	if ($mysqli->connect_errno) {
		echo 'error';
		exit();
	}
	global $wpdb;
	$table = $wpdb->posts;
	$update = $mysqli->query("update $table set post_title = '$title' where ID = 1");

	echo $update ? 'ok' : 'error';
}
