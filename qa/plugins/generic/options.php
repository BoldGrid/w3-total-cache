<?php
require_once dirname(__FILE__) . '/wp-load.php';
$value = isset($_GET['value']) ? $_GET['value'] : '';
$autoload = isset($_GET['autoload']) ? $_GET['autoload'] : '';
$action = $_REQUEST['action'];

switch($action) {

	case 'add_option':
		$added = add_option('test_option', $value, '', $autoload);
		echo $added ? 'added' : 'error';
		break;

	case 'update_option':
		$added = update_option('test_option', $value);
		echo $added ? 'updated' : 'error';
		break;

	case 'get_option':
		$option = get_option('test_option');
		echo $option;
		break;

	case 'delete_option':
		$deleted = delete_option('test_option');
		echo $deleted ? 'deleted' : 'error';
		break;

	default:
		echo 'error';
		break;
}