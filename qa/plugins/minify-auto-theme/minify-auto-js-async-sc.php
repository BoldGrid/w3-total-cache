<?php
/** shortcode-based version of minify-auto-async-js test */

add_shortcode( 'w3tcqa', function( $atts ) {
	ob_start();

	?>
	<p>JS1: <span id="js1">failed</span></p>
	<p>JS2: <span id="js2">failed</span></p>
	<p>JS3: <span id="js3">failed</span></p>
	<p>JS4: <span id="js4">failed</span></p>
	<p>JS5: <span id="js5">failed</span></p>

	<?php

	$url6 = get_template_directory_uri() . '/qa/minify-auto-js6.js';

	// No quotes around script url.
	echo '<script type="text/javascript" src=' . esc_url( $url6 ) . '></script>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript

	$output = ob_get_contents();
	ob_end_clean();

	return $output;
} );



add_action( 'wp_enqueue_scripts', function() {
	// Enqueue showcase script for the slider.
	wp_enqueue_script( 'jquery' );

	wp_enqueue_script( 'minify-js1', get_template_directory_uri() . '/qa/minify-auto-js1.js' );
	wp_script_add_data( 'minify-js1', 'async', true );

	wp_enqueue_script( 'minify-js2', get_template_directory_uri() . '/qa/minify-auto-js2.js' );
	wp_script_add_data( 'minify-js2', 'async', true );

	wp_enqueue_script( 'minify-js3', get_template_directory_uri() . '/qa/minify-auto-async-js3.js' );
	wp_script_add_data( 'minify-js3', 'defer', true );

	wp_enqueue_script( 'minify-js4', get_template_directory_uri() . '/qa/minify-auto-async-js4.js' );
	wp_script_add_data( 'minify-js4', 'defer', true );

	wp_enqueue_script( 'minify-js5', get_template_directory_uri() . '/qa/minify-auto-async-js5.js' );
	wp_script_add_data( 'minify-js5', 'defer', true );
} );
