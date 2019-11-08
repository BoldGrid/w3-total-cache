<?php
/**
 * Plugin Name: Cookie Groups
 */
if (isset($_REQUEST['action']))
	add_action('template_redirect', 'cookie_groups_template_redirect');

function cookie_groups_template_redirect() {
	$action = $_REQUEST['action'];

	if ($action == 'setcookie') {
		setcookie($_REQUEST['name'], $_REQUEST['value']);
		echo 'ok';
		exit;
	}
}

add_action('wp_footer', 'cookie_groups_wp_footer');

function cookie_groups_wp_footer() {
	echo '<div id="cookie_groupcookie">';
	echo isset($_COOKIE['groupcookie']) ? $_COOKIE['groupcookie'] : '';
	echo '</div>';

	echo '<div id="incremental_key">';
	$v = (int)get_option('w3tcqa_incremental_key');
	update_option('w3tcqa_incremental_key', $v + 1);

	echo $v;
	echo '</div>';
}
