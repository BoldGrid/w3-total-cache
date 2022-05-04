<?php
/*
Plugin Name: w3tc - plugin for testing users signup
Description: Store new user info in wp-content/mail.txt.
*/
add_filter('wp_mail', 'save_activation_key', 99);

function save_activation_key($message) {
	$f = fopen(WP_CONTENT_DIR .'/mail.txt', 'w+');
	fwrite($f, $message['message']);
	fclose($f);
}
