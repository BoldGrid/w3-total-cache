<?php
include(dirname(__FILE__) . '/wp-load.php');
define('DONOTCACHEPAGE', true);

$blog_id = $_REQUEST['blog_id'];
$url = $_REQUEST['url'];
$wp_content_path = $_REQUEST['wp_content_path'];

$parsed = parse_url($url);
$scheme = $parsed['scheme'];
$host_port = strtolower($parsed['host']) .
	(isset($parsed['port']) ? ':' . $parsed['port'] : '');
$path = $parsed['path'];


$content = '';

$engine = $_REQUEST['engine'];

$cache_key = md5($host_port . $path);
if ($scheme == 'https')
	$cache_key .= '_ssl';

echo 'checking ' . $host_port . $path . ' blog ' . $blog_id . ' cache key ' . $cache_key . ' ';

if ($engine == 'apc') {
	$cache = new \W3TC\Cache_Apcu(array(
			'section' => 'page',
			'blog_id' => $blog_id,
			'module' => 'pgcache',
			'host' => '',
			'instance_id' => \W3TC\Util_Environment::instance_id()
		));
} else if ($engine == 'file') {
	$cache = new \W3TC\Cache_File(array(
			'section' => 'page',
			'locking' => false,
			'flush_timelimit' => 100,
			'blog_id' => $blog_id,
			'module' => 'pgcache',
			'host' => '',
			'instance_id' => \W3TC\Util_Environment::instance_id()
		));
} else if ($engine == 'file_generic') {
	$cache = new \W3TC\Cache_File(array(
		'section' => 'page',
		'cache_dir' =>
			W3TC_CACHE_PAGE_ENHANCED_DIR .
			DIRECTORY_SEPARATOR .
			$host_port,
		'locking' => false,
		'flush_timelimit' => 100,
		'blog_id' => $blog_id,
		'module' => 'pgcache',
		'host' => '',
		'instance_id' => \W3TC\Util_Environment::instance_id()
		));
} else if ($engine == 'memcached') {
	$params = array(
		'section' => 'page',
		'servers' => array( '127.0.0.1:11211' ),
		'blog_id' => $blog_id,
		'module' => 'pgcache',
		'host' => '',
		'instance_id' => \W3TC\Util_Environment::instance_id()
	);
	if (class_exists('Memcached')) {
		$cache = new \W3TC\Cache_Memcached($params);
	} else {
		$cache = new \W3TC\Cache_Memcache($params);
	}
} else if ($engine == 'redis') {
	$cache = new \W3TC\Cache_Redis(array(
			'section' => 'page',
			'servers' => array( '127.0.0.1:6379' ),
			'dbid' => 0,
			'password' => '',
			'blog_id' => $blog_id,
			'module' => 'pgcache',
			'host' => '',
			'instance_id' => \W3TC\Util_Environment::instance_id(),
			'timeout' => 0,
			'retry_interval' => 0,
			'read_timeout' => 0,
	));
} else if ($engine == 'xcache') {
	$cache = new \W3TC\Cache_Xcache(array(
			'section' => 'page',
			'blog_id' => $blog_id,
			'module' => 'pgcache',
			'host' => '',
			'instance_id' => \W3TC\Util_Environment::instance_id()
		));
} else {
	echo 'Unknown engine';
	exit;
}

$v = $cache->get($cache_key, 'rest');
if (!isset($v['content'])) {
	echo 'no entry found';
	exit;
}

$v['content'] = 'Test of cache';
$cache->set($cache_key, $v, 100, 'rest');

// try to read again
$v = $cache->get($cache_key, 'rest');
$content = $v['content'];

echo $content;
