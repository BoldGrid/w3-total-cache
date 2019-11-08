<?php
/**
 * Plugin Name: Fragment Cache Test Plugin
 */
$fc_key = 'fc_print';
$fc_group = 'fc_test_';
$value = isset($_GET['value']) ? $_GET['value'] : '';
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

add_action('init', function() {
	w3tc_register_fragment_group('fc_test_', array('publish_post','edit_post'), 3000);
});

add_action('template_redirect', 'set_fragment_in_cache');

function print_fc(){
	global $value;
	echo $value;
}

function fc_print_cache_start() {
	$hook = current_filter();
	w3tc_fragmentcache_start('fc_print', 'fc_test_', $hook);
}

/**
 * Store the output buffer per page/post hook basis.
 */
function fc_print_cache_end() {
	w3tc_fragmentcache_end('fc_print', 'fc_test_');
}

function get_cache_key() {
	global $fc_group, $fc_key;
	$host = \W3TC\Util_Environment::host();
	$blog_id = \W3TC\Util_Environment::blog_id();

	$name = md5($fc_group . $fc_key);
	return sprintf('w3tc_%s_%d_%s_%s', $host, $blog_id, 'fragmentcache', $name);
}

function change_cache($content, $new_content) {
	preg_match('/^.+?<\?php exit; \?>/', $content, $matches);
	$content = preg_replace('/^.+?<\?php exit; \?>/', '', $content);
	$cache = unserialize($content);
	$cache['content'] = $new_content;
	return !empty($matches[0]) ? $matches[0] . serialize($cache) : serialize($cache);
}

function set_fragment_in_cache() {
	global $fc_group, $fc_key, $value, $action;
	$blog_id = \W3TC\Util_Environment::blog_id();
	switch ($action) {

		case 'setFragmentInCache':
			$key = md5($fc_group . $fc_key);

			switch($_REQUEST['engine']) {
				case 'file':
					$cacheInstance = new \W3TC\Cache_File(array(
						'section' => 'fragment',
						'locking' => false,
						'flush_timelimit' => 100,
						'blog_id' => $blog_id,
						'module' => 'fragmentcache',
						'host' => \W3TC\Util_Environment::host(),
						'instance_id' => \W3TC\Util_Environment::instance_id(),
					));
					break;

				case 'apc':
					$cacheInstance = new \W3TC\Cache_Apc(array(
						'section' => 'fragment',
						'blog_id' => $blog_id,
						'module' => 'fragmentcache',
						'host' => \W3TC\Util_Environment::host(),
						'instance_id' => \W3TC\Util_Environment::instance_id()
					));
					break;

				case 'xcache':
					$cacheInstance = new \W3TC\Cache_Xcache(array(
						'section' => 'fragment',
						'blog_id' => $blog_id,
						'module' => 'fragmentcache',
						'host' => \W3TC\Util_Environment::host(),
						'instance_id' => \W3TC\Util_Environment::instance_id()
					));
					break;

				case 'memcached':
					$params = array(
						'section' => 'fragment',
						'servers' => array( '127.0.0.1:11211' ),
						'blog_id' => \W3TC\Util_Environment::blog_id(),
						'module' => 'fragmentcache',
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
						'section' => 'fragment',
						'servers' => array( '127.0.0.1:6379' ),
						'dbid' => 0,
						'password' => '',
						'blog_id' => \W3TC\Util_Environment::blog_id(),
						'module' => 'fragmentcache',
						'host' => \W3TC\Util_Environment::host(),
						'instance_id' => \W3TC\Util_Environment::instance_id()
					));
					break;

				default:
					echo 'Error: wrong engine';
					die;
			}
			$cache = $cacheInstance->get($key, $fc_group);
			$cache['content'] = $value;
			$if_saved = $cacheInstance->set($key, $cache, 100, $fc_group);
			echo $if_saved ? 'ok' : 'error';
			die;
		break;

		case 'setFragment':
			add_action('fc_print', 'fc_print_cache_start', 0);
			add_action('fc_print', 'fc_print_cache_end',100);
			add_action('fc_print', 'print_fc');
			do_action('fc_print');
			die;
		break;
	}

}