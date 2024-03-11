<?php

add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_script( 'jquery' );

	wp_enqueue_script( 'minify-js1', get_template_directory_uri() . '/qa/minify-auto-js1.js' );
	wp_enqueue_script( 'minify-js2', get_template_directory_uri() . '/qa/minify-auto-js2.js' );
	wp_enqueue_script( 'minify-js3', get_template_directory_uri() . '/qa/minify-auto-js3.js' );
} );



add_shortcode( 'w3tcqa', function( $atts ) {
	ob_start();

	?>
	<p>JS1: <span id="js1">failed</span></p>
	<p>JS2: <span id="js2">failed</span></p>
	<p>JS3: <span id="js3">failed</span></p>
	<p>JS4: <span id="js4">failed</span></p>

	<script>
	jQuery(document).ready(function() {
	var s = jQuery('#js4');
	s.removeClass( 'enhanced' );
	s.addClass( 'disabled' );
	jQuery('#js4').text('passed');
	});
	</script>
	<?php

	$output = ob_get_contents();
	ob_end_clean();

	return $output;
} );
