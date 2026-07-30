<?php

/**
 * Enqueue showcase script for the slider.
 */
add_action( 'wp_enqueue_scripts', function() {
	// Enqueue a local CSS file that will be minified
	wp_enqueue_style( 'minify-css1', get_template_directory_uri() . '/qa/minify-css1.css',
		array(), W3TC_VERSION, false );
} );

add_action( 'wp_head', function() {
	echo '<link rel="stylesheet" type="text/css" id="font_test" href="//fonts.googleapis.com/css?family=Ubuntu%3A400%2C700%26subset%3Dlatin%2Clatin-ex" media="all" />'; // phpcs:ignore
} );
