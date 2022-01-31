<?php
/**
 * File: user-signup.php
 *
 * Store new user info in "wp-content/mail.txt" when testing user signup.
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */

add_filter(
	'wp_mail',
	function ( $message ) {
		$f = fopen( WP_CONTENT_DIR . '/mail.txt', 'w+' );
		fwrite( $f, $message['message'] );
		fclose( $f );
	},
	99
);
