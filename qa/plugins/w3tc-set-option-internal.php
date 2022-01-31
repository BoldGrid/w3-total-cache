<?php
/**
 * File: w3tc-set-option-internal.php
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput, WordPress.PHP.DevelopmentFunctions.error_log_var_dump, WordPress.WP.GlobalVariablesOverride.Prohibited
 */

define( 'DONOTCACHEPAGE', true );

require __DIR__ . '/wp-load.php';

$blog_id = $_REQUEST['blog_id'];
$name    = json_decode( stripslashes( $_REQUEST['name'] ) );
$value   = json_decode( stripslashes( $_REQUEST['value'] ) );

var_dump( $name, $value );

$c = w3tc_config();

$c->set( $name, $value );
$c->save();

var_dump( $c->get( $name ) );

echo 'ok';
