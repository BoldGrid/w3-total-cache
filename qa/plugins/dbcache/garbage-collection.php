<?php
require_once dirname(__FILE__) . '/wp-load.php';

$blog_id = $_REQUEST['blog_id'];
$url = $_REQUEST['url'];

$parsed = parse_url($url);
$host = strtolower( $parsed['host'] );

global $wpdb;
$action = $_REQUEST['action'];

switch ($action) {
	case 'add_cache':
		$title = $wpdb->get_var( "Select post_title from $wpdb->posts where ID = 1" );
		echo '<div id="added">';
		echo $title ? 'ok' : 'error';
		echo '</div>';
		break;

	case 'get_path':
		$key = md5( "Select post_title from $wpdb->posts where ID = 1" );

		$c = new \W3TC\Cache_File(array(
			'section' => 'db',
			'locking' => false,
			'flush_timelimit' => 100,
			'blog_id' => $blog_id,
			'module' => 'dbcache',
			'host' => $host,
			'instance_id' => \W3TC\Util_Environment::instance_id(),
			'use_wp_hash' => true
		));

		$cache = $c->get( $key );
		$storage_key = sprintf( 'w3tc_%d_%s_%d_%s_%s',
			\W3TC\Util_Environment::instance_id(),
			$host, $blog_id, 'dbcache', $key);

		$sub_path = $c->_get_path($storage_key);
		$cache_dir = \W3TC\Util_Environment::cache_blog_dir('db', $blog_id);
		$path = $cache_dir . DIRECTORY_SEPARATOR . 'singletables' . DIRECTORY_SEPARATOR . $sub_path;
		echo '<div id="path">';
		echo $path;
		echo '</div>';

		break;


	case 'garbage_collection':
		$cronjob = wp_get_schedule('w3_dbcache_cleanup');
		/** taking garbage collection value in seconds */
		$schedules = wp_get_schedules();
		$seconds = $schedules['w3_dbcache_cleanup']['interval'];
		echo '<div id="schedule">';
		echo $cronjob . " " . $seconds;
		echo '</div>';
		do_action('w3_dbcache_cleanup');
		break;
	die;
}
