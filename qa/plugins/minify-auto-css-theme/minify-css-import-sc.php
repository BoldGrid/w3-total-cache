<?php

// Test relative paths, without host.
add_action( 'wp_head', function() {
	$css3_parts = wp_parse_url( get_template_directory_uri() . '/qa/minify-css3.css' );
	$css3_path  = $css3_parts['path'];

	echo '<link rel="stylesheet" id="minify-css3-css"  href="' . $css3_path . '" type="text/css" media="all" />'; // phpcs:ignore
} );



add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_style( 'minify-css1', get_template_directory_uri() . '/qa/minify-css1.css',
		array(), W3TC_VERSION, false );
} );



add_shortcode( 'w3tcqa', function( $atts ) {
	ob_start();

	$url6 = get_template_directory_uri() . '/qa/minify-auto-js6.js';

	// No quotes around script url.
	echo '<script type="text/javascript" src=' . $url6 . '></script>'; // phpcs:ignore

	$output = ob_get_contents();
	ob_end_clean();

	return $output;
} );
