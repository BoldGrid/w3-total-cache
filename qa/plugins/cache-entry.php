<?php
include(dirname(__FILE__) . '/wp-load.php');
define('DONOTCACHEPAGE', true);

$blog_id = $_REQUEST['blog_id'];
$url = $_REQUEST['url'];
$wp_content_path = $_REQUEST['wp_content_path'];
$page_key_postfix = isset($_REQUEST['page_key_postfix']) ? $_REQUEST['page_key_postfix'] : '';

$parsed = parse_url($url);
$scheme = $parsed['scheme'];
$host_port = strtolower($parsed['host']) .
	(isset($parsed['port']) ? ':' . $parsed['port'] : '');
$path = $parsed['path'];


$content = '';

$engine = $_REQUEST['engine'];
if ($engine == 'file_generic') {
	$cache_path =  $wp_content_path . 'cache/page_enhanced/' . $host_port . $path .
		'_index' .
		($scheme == 'https' ? '_ssl' : '') .
		$page_key_postfix .
		'.html';
	$content = 'Test of cache' . file_get_contents($cache_path);
	$success = file_put_contents($cache_path, $content);

	echo $content;
	exit;
}

$cache_key = md5($host_port . $path);
if ($scheme == 'https')
	$cache_key .= '_ssl';
$cache_key .= $page_key_postfix;

echo 'checking ' . $host_port . $path . ' blog ' . $blog_id . ' cache key ' . $cache_key;

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

$v = $cache->get($cache_key);
if (!isset($v['content'])) {
	echo 'no entry found';
	exit;
}

$v['content'] = 'Test of cache' . $v['content'];
$cache->set($cache_key, $v, 100);

// try to read again
$v = $cache->get($cache_key);
$content = $v['content'];

echo $content;
