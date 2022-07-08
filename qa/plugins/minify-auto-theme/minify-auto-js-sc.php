<?php
/** shortcode-based version of minify-auto-js test */

add_shortcode( 'w3tcqa', function( $atts ) {
	ob_start();

	?>
	<p>JS1: <span id="js1">failed</span></p>
	<p>JS2: <span id="js2">failed</span></p>
	<p>JS3: <span id="js3">failed</span></p>
	<p>JS4: <span id="js4">failed</span></p>
	<p>JS5: <span id="js5">failed</span></p>
	<p>JS6: <span id="js6">failed</span></p>

	<?php

	$url6 = get_template_directory_uri() . '/qa/minify-auto-js6.js';

	// No quotes around script url.
	echo '<script type="text/javascript" src=' . esc_url( $url6 ) . '></script>';

	$output = ob_get_contents();
	ob_end_clean();

	return $output;
} );



/**
 * Minify JS 4.
 */
add_action( 'wp_head', function() {
	$url4 = get_template_directory_uri() . '/qa/minify-auto-js4.js';
	$url5 = get_template_directory_uri() . '/qa/minify-auto-js5.js';

	?>

	<script type="text/javascript">
	/* <![CDATA[ */
	var js4 = '#js4';
	/* ]]> */
	</script>
	<script type="text/javascript" src="<?php echo esc_url( $url4 ); ?>"></script>
	<script type="text/javascript" src="<?php echo esc_url( $url5 ); ?>"></script>

	<?php
} );



add_action( 'wp_enqueue_scripts', function() {
	// Enqueue showcase script for the slider.
	wp_enqueue_script( 'jquery' );

	wp_enqueue_script( 'minify-js1', get_template_directory_uri() . '/qa/minify-auto-js1.js' );

	// Test multiple "/" in url.
	$url2 = get_template_directory_uri() . '/qa//minify-auto-js2.js';
	$url2 = str_replace( 'http://', '//', $url2 );
	$url2 = str_replace( 'https://', '//', $url2 );

	wp_enqueue_script( 'minify-js2', $url2 );
	wp_enqueue_script( 'minify-js3', get_template_directory_uri() . '/qa/minify-auto-js3.js' );
} );
