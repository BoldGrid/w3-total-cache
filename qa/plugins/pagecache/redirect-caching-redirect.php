<?php
/**
 * Template Name: Cache redirect
 * Description: A Page Template for testing redirect caching
 *
 * @package W3TC
 * @subpackage W3TC QA
 */

if ( !defined( 'W3TCQA_NO_REDIRECT' ) ) {
	$scheme = 'http';
	if ( isset( $_SERVER['REQUEST_SCHEME'] ) ) {
		$scheme = $_SERVER['REQUEST_SCHEME'];
	} elseif ( $_SERVER['SERVER_PORT'] == 443 ) {
		$scheme = 'https';   // nginx
	}

	$url = $scheme . '://for-tests.sandbox';

	if ( $_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443 )
		$url .= ':' . $_SERVER['SERVER_PORT'];

	$url .= '/';

	wp_redirect($url); exit;
} else {
	echo 'no redirect\n';
}
