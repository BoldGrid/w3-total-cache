<?php

require_once dirname(__FILE__) . '/wp-load.php';

$content = '';
$fc_key = 'transient_test';

if (!isset($_REQUEST['action']) || !isset($_REQUEST['group']))
	return;

$action = $_REQUEST['action'];
$group = $_REQUEST['group'];
$id = 'test';
$data = 'object cache test';

switch($action) {
	case 'setCache':
		$set = wp_cache_set($id, $data, $group);
		echo $set ? 'setCache ok' : 'error';
		die;
		break;

	case 'getCache':
		$cache = wp_cache_get($id, $group);
		echo $cache;
		die;
		break;
}
