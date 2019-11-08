<?php

require_once dirname(__FILE__) . '/wp-load.php';

$action = $_REQUEST['action'];
switch($action) {
	case 'checkLoaded':
		$set = function_exists('wp_cache_set');
		echo $set ? 'wp_cache loaded' : 'wp_cache missing';
		die;
		break;

	case 'setInCache':
		$set = wp_cache_set($_REQUEST['id'], $_REQUEST['value'], $_REQUEST['group']);
		echo $set ? 'setInCache ok' : 'setCache error ' . $group . ':' . $id;
		die;
		break;

	case 'setInCacheBooleanFalse':
		$set = wp_cache_set($_REQUEST['id'], false, $_REQUEST['group']);
		echo $set ? 'setInCache ok' : 'setCache error ' . $group . ':' . $id;
		die;
		break;

	case 'getFromCache':
		$found = null;
		$value = wp_cache_get($_REQUEST['id'], $_REQUEST['group'], false, $found);
		echo json_encode(array('value' => $value, 'found' => $found));
		die;
		break;

	case 'doubleGetFromCache':
		$found1 = null;
		$value1 = wp_cache_get($_REQUEST['id'], $_REQUEST['group'], false, $found1);
		$found = null;
		$value = wp_cache_get($_REQUEST['id'], $_REQUEST['group'], false, $found);
		echo json_encode(array('value' => $value, 'found' => $found));
		die;
		break;
}
