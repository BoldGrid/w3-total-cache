<?php
/**
 * File: user-roles-single.php
 *
 * Page cache: Testing user signup, storing new user info in "wp-content/mail.txt".
 *
 * Template Name: Page cache: User roles: Single
 * Template Post Type: post, page
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */

add_filter(
	'wp_mail',
	function ( $message ) {
		$f = fopen( WP_CONTENT_DIR . '/mail.txt', 'a' );
		fwrite( $f, $message['message'] );
		fclose( $f );
	},
	99
);
