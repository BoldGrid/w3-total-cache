<?php
include(dirname(__FILE__) . '/wp-load.php');
define('DONOTCACHEPAGE', true);

$url = $_REQUEST['url'];

w3tc_flush_url( $url );
echo 'ok';
