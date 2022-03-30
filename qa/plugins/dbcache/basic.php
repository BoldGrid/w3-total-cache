<?php
define('DONOTCACHEPAGE', true);
require_once dirname(__FILE__) . '/wp-load.php';

global $wpdb;
$value = 'Change';
$blog_id = $_REQUEST['blog_id'];
$action = $_REQUEST['action'];
$engine = $_REQUEST['engine'];
if ($blog_id > 0 ) {
	switch_to_blog((int)$blog_id);
}

switch ($action) {
	case 'add_cache':
		$title = $wpdb->get_var("Select post_title from $wpdb->posts where ID = 1");
		echo '<div id="added">';
		echo $title ? 'ok' : 'error';
		echo '</div>';
		break;

	case 'get_cache' :
		$title = $wpdb->get_var("Select post_title from $wpdb->posts where ID = 1");
		echo $title;
		break;

	case 'change_cache':
		$key = md5("Select post_title from $wpdb->posts where ID = 1");
		//$storage_key = sprintf('w3tc_%s_%d_%s_%s', $domain, $blog, 'dbcache', $key);

		switch($engine) {

			case 'file':
				$cacheInstance = new \W3TC\Cache_File(array(
					'section' => 'db',
					'locking' => false,
					'flush_timelimit' => 100,
					'blog_id' => $blog_id,
					'module' => 'dbcache',
					'host' => \W3TC\Util_Environment::host(),
					'instance_id' => \W3TC\Util_Environment::instance_id(),
					'use_wp_hash' => true
				));
			break;

			case 'apc':
				$cacheInstance = new \W3TC\Cache_Apcu(array(
					'section' => 'db',
					'blog_id' => $blog_id,
					'module' => 'dbcache',
					'host' => \W3TC\Util_Environment::host(),
					'instance_id' => \W3TC\Util_Environment::instance_id(),
				));
			break;

			case 'xcache':
				$cacheInstance = new \W3TC\Cache_Xcache(array(
					'section' => 'db',
					'blog_id' => $blog_id,
					'module' => 'dbcache',
					'host' => \W3TC\Util_Environment::host(),
					'instance_id' => \W3TC\Util_Environment::instance_id()
				));
			break;

			case 'memcached':
				$params = array(
					'section' => 'db',
					'servers' => array( '127.0.0.1:11211' ),
					'blog_id' => $blog_id,
					'module' => 'dbcache',
					'host' => \W3TC\Util_Environment::host(),
					'instance_id' => \W3TC\Util_Environment::instance_id()
				);
				if (class_exists('Memcached')) {
					$cacheInstance = new \W3TC\Cache_Memcached($params);
				} else {
					$cacheInstance = new \W3TC\Cache_Memcache($params);
				}
			break;

			case 'redis':
				$cacheInstance = new \W3TC\Cache_Redis(array(
					'section' => 'db',
					'servers' => array( '127.0.0.1:6379' ),
					'dbid' => 0,
					'password' => '',
					'blog_id' => $blog_id,
					'module' => 'dbcache',
					'host' => \W3TC\Util_Environment::host(),
					'instance_id' => \W3TC\Util_Environment::instance_id(),
					'timeout' => 0,
					'retry_interval' => 0,
					'read_timeout' => 0,
				));
			break;

			default:
				echo 'Error: wrong engine';
				die;
				break;
		}
		$cache = $cacheInstance->get($key, 'singletables');
		$if_saved = false;

		var_dump($cache);
		if (isset($cache['last_result'])) {
			$cache['last_result'][0]->post_title = $value;
			$if_saved = $cacheInstance->set($key, $cache, 100, 'singletables');
		}

		echo '<div id="changed">';
		echo $if_saved ? 'ok' : 'error';
		echo '</div>';
		break;

	default:
		echo 'error2';
		break;
}
die;
