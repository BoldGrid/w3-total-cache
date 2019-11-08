<?php
require_once dirname(__FILE__) . '/wp-load.php';
$action = ( isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '' );

if ( $action == 'cron_queue_process' ) {
	do_action( 'w3_cdn_cron_queue_process' );
	echo 'cron_queue_process';
} else {
	$path = $_REQUEST['path'];
	exec('sudo chown www-data:www-data ' . $path);
	$user = posix_getpwuid(fileowner($path));
	echo $user['name'];
}
