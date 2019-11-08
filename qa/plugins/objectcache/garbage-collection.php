<?php

require_once dirname(__FILE__) . '/wp-load.php';


$action = $_REQUEST['action'];
$group = $_REQUEST['group'];
$id = 'test';
$data = 'object cache test';

switch($action) {
	case 'setCache':
		$set = wp_cache_set($id, $data, $group);
		echo $set ? 'setCache ok' : 'setCache error';
		die;
		break;

	case 'getCache':
		$blog_id = $_REQUEST['blog_id'];
		$url = $_REQUEST['url'];

		$parsed = parse_url($url);
		$scheme = $parsed['scheme'];
		$host = strtolower($parsed['host']);

		$cache_key = $blog_id . $group . $id;

		$CF = new \W3TC\Cache_File(array(
			'section' => 'object',
			'locking' => false,
			'flush_timelimit' => 3,
			'blog_id' => $blog_id,
			'module' => 'object',
			'host' => $host,
			'instance_id' => \W3TC\Util_Environment::instance_id(),
		));
		$storage_key = $CF->get_item_key($cache_key);
		$sub_path = $CF->_get_path($storage_key);
		$cache_dir = \W3TC\Util_Environment::cache_blog_dir('object', $blog_id);
		$path = $cache_dir . DIRECTORY_SEPARATOR . $sub_path;
		echo file_exists($path) ? 'cache exists' : 'cache not exists';
		die;
		break;

	case 'flush':
		$cronjob = wp_get_schedule('w3_objectcache_cleanup');
		/** taking garbage collection value in seconds */
		$schedules = wp_get_schedules();
		$seconds = $schedules['w3_objectcache_cleanup']['interval'];
		echo $cronjob . " " . $seconds;
		do_action('w3_objectcache_cleanup');
		die;
}
