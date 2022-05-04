<?php
include(dirname(__FILE__) . '/wp-load.php');
define('DONOTCACHEPAGE', true);

$blog_id = $_REQUEST['blog_id'];

$name = json_decode( stripslashes( $_REQUEST['name'] ) );
$value = json_decode( stripslashes( $_REQUEST['value'] ) );

var_dump($name);
var_dump($value);

$c = w3tc_config();
$c->set( $name, $value );
$c->save();

var_dump( $c->get( $name ) );
echo 'ok';
