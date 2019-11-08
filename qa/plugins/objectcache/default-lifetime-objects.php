<?php

require_once dirname(__FILE__) . '/wp-load.php';

$action = $_REQUEST['action'];
$group = $_REQUEST['group'];
$id = 'test';
$data = 'object cache test';

switch($action) {
	case 'setCache':
		$set = wp_cache_set($id, $data, $group);
		echo $set ? 'setCache ok' : 'setCache error ' . $group . ':' . $id;
		die;
		break;

	case 'getCache':
		$cache = wp_cache_get($id, $group);
		echo $cache;
		die;
		break;
}
