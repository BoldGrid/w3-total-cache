<?php
/*
Plugin Name: minify-test-plugin
Description: adds plugin-based files for minification
*/
add_action('init', 'minify_test_plugin_init');

function minify_test_plugin_init() {
	wp_enqueue_script( 'minify-test-1', plugins_url( 'test-js.js', __FILE__ ) );
	wp_enqueue_style( 'minify-test-2', plugins_url( 'test-css.css', __FILE__ ) );
}
