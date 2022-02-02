<?php
/**
 * File: redirect-cacheing-redirect.php
 *
 * Page cache: Cache redirect
 *
 * Template Name: Page cache: Redirect caching redirect
 * Template Post Type: post, page
 *
 * @package W3TC
 * @subpackage W3TC QA
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput, WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
 */

if ( ! defined( 'W3TCQA_NO_REDIRECT' ) ) {
	$scheme = 'http';

	if ( isset( $_SERVER['REQUEST_SCHEME'] ) ) {
		$scheme = $_SERVER['REQUEST_SCHEME'];
	} elseif ( '443' === $_SERVER['SERVER_PORT'] ) {
		$scheme = 'https';   // Nginx.
	}

	$url = $scheme . '://for-tests.sandbox';

	if ( '80' !== $_SERVER['SERVER_PORT'] && '443' !== $_SERVER['SERVER_PORT'] ) {
		$url .= ':' . $_SERVER['SERVER_PORT'];
	}

	$url .= '/';

	wp_redirect( $url );
	exit;
} else {
	echo 'no redirect\n';
}
