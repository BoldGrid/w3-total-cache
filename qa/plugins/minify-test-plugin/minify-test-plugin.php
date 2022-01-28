<?php
/**
 * File: minify-test-plugin.php
 *
 * Minify test: Adds plugin-based files for minification.
 *
 * @package W3TC
 * @subpackage QA
 */

add_action(
	'init',
	function () {
		wp_enqueue_script( 'minify-test-1', plugins_url( 'test-js.js', __FILE__ ), array(), W3TC_VERSION, false );
		wp_enqueue_style( 'minify-test-2', plugins_url( 'test-css.css', __FILE__ ), array(), W3TC_VERSION );
	}
);
