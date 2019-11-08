<?php
include(dirname(__FILE__) . '/wp-load.php');
define('DONOTCACHEPAGE', true);

$blog_id = $_REQUEST['blog_id'];
$url = $_REQUEST['url'];

$parsed = parse_url($url);
$host_port = strtolower($parsed['host']) .
	(isset($parsed['port']) ? ':' . $parsed['port'] : '');

$path = $parsed['path'];


function get_cache_key($path, $host_port, $group) {
	global $url;
	global $engine;

	if ($engine == 'file_generic') {
		return $host_port . $path . '_index' .
			(empty($group) ? '' : '_' . $group) .
			(preg_match('/https/', $url) ? '_ssl' : '') .
			'.html';
	}

	$cache_key = md5($host_port . $path) . (empty($group) ? '' : '_' . $group);
	if (preg_match('/https/', $url)) {
		$cache_key .= '_ssl';
	};
	return $cache_key;
}

$engine = $_REQUEST['engine'];
$cache_key1 = get_cache_key($path, $host_port, 'test1');
$cache_key2 = get_cache_key($path, $host_port, '');

if ($engine == 'apc')
	$cache = new \W3TC\Cache_Apc(array(
			'section' => 'page',
			'blog_id' => $blog_id,
			'module' => 'pgcache',
			'host' => '',
			'instance_id' => \W3TC\Util_Environment::instance_id()
		));
else if ($engine == 'file')
	$cache = new \W3TC\Cache_File(array(
			'section' => 'page',
			'locking' => false,
			'flush_timelimit' => 100,
			'blog_id' => $blog_id,
			'module' => 'pgcache',
			'host' => '',
			'instance_id' => \W3TC\Util_Environment::instance_id()
		));
else if ($engine == 'file_generic')
	$cache = new \W3TC\Cache_File_Generic(array(
			'cache_dir' => W3TC_CACHE_PAGE_ENHANCED_DIR,
			'section' => 'page',
			'locking' => false,
			'flush_timelimit' => 100,
			'blog_id' => $blog_id,
			'module' => 'pgcache',
			'host' => '',
			'instance_id' => \W3TC\Util_Environment::instance_id()
		));
else if ($engine == 'memcached') {
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
} else if ($engine == 'redis')
	$cache = new \W3TC\Cache_Redis(array(
			'section' => 'page',
			'servers' => array( '127.0.0.1:6379' ),
			'dbid' => 0,
			'password' => '',
			'blog_id' => $blog_id,
			'module' => 'pgcache',
			'host' => '',
			'instance_id' => \W3TC\Util_Environment::instance_id()
		));
else if ($engine == 'xcache')
	$cache = new \W3TC\Cache_Xcache(array(
			'section' => 'page',
			'blog_id' => $blog_id,
			'module' => 'pgcache',
			'host' => '',
			'instance_id' => \W3TC\Util_Environment::instance_id()
		));

var_dump($cache_key1);
var_dump($cache_key2);
$v1 = $cache->get($cache_key1);
$v2 = $cache->get($cache_key2);
echo isset($v1['content']) && isset($v2['content']) ? 'ok' : 'error';
