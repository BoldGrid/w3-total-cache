<?php
/**
 * Plugin Name: Fragment Cache Test Plugin
 */
define('DONOTCACHEPAGE', true);

$content = '';
$fc_group = 'nogroup';
$fc_key = 'transient_test';

if (isset($_REQUEST['action']))
	add_action('template_redirect', 'check_simple_fragment');



function check_simple_fragment() {
	global $fc_group, $fc_key;
	$blog_id = \W3TC\Util_Environment::blog_id();
	$value = !empty($_GET['value']) ? $_GET['value'] : '';

	if ($_REQUEST['action'] == 'setTransient') {
		w3tc_fragmentcache_store($fc_key, $fc_group, $value);
		echo '<div id="added">ok</div>';
		die;
	}

	if ($_REQUEST['action'] == 'getTransient') {
		echo w3tc_fragmentcache_get($fc_key, $fc_group);
		die;
	}

	if ($_REQUEST['action'] == 'setTransientInCache') {
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
				$cacheInstance = new \W3TC\Cache_Apcu(array(
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
					'instance_id' => \W3TC\Util_Environment::instance_id(),
					'timeout' => 0,
					'retry_interval' => 0,
					'read_timeout' => 0,
				));
				break;

			default:
				echo 'Error: wrong engine';
				die;
		}

		$cache = $cacheInstance->get($key, $fc_group);
		$cache['content'] = $value;
		$if_saved = $cacheInstance->set($key, $cache, 100, $fc_group);
		echo '<div id="changed">';
		echo $if_saved ? 'ok' : 'error';
		echo '</div>';
		die;
	}
}
