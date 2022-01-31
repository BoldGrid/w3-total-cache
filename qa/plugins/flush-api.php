<?php
/**
 * File: flush-api.php
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
 */

define( 'DONOTCACHEPAGE', true );

require __DIR__ . '/wp-load.php';

$url = $_REQUEST['url'];

w3tc_flush_url( $url );

echo 'ok';
