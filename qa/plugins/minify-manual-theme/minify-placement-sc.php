<?php

add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_script( 'jquery' );

	wp_enqueue_script( 'minify-js1', get_template_directory_uri() . '/qa/minify-auto-js1.js' );
	wp_enqueue_script( 'minify-js2', get_template_directory_uri() . '/qa/minify-auto-js2.js' );
	wp_enqueue_script( 'minify-js3', get_template_directory_uri() . '/qa/minify-auto-js3.js' );
	wp_enqueue_style( 'minify-css', get_template_directory_uri() . '/qa/minify-style.css' );
} );



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
	<script type="text/javascript" src='<?php echo esc_url( $url5 ); ?>'></script>
	<!-- test js placement --><!-- W3TC-include-js-head -->

	<!-- test css placement --><!-- W3TC-include-css -->
	<?php
} );



add_shortcode( 'w3tcqa', function( $atts ) {
	ob_start();

	?>
	<!-- test minify body start --><!-- W3TC-include-js-body-start -->
	<p>JS1: <span id="js1">failed</span></p>
	<p>JS2: <span id="js2">failed</span></p>
	<p>JS3: <span id="js3">failed</span></p>
	<p>JS4: <span id="js4">failed</span></p>
	<p>JS5: <span id="js5">failed</span></p>
	<p>JS6: <span id="js6">failed</span></p>
	<?php

	$url6 = get_template_directory_uri() . '/qa/minify-auto-js6.js';

	// no quotes around script url.
	echo '<script type="text/javascript" src=' . esc_url( $url6 ) . '></script>';
	?>
	<!-- test minify body end --><!-- W3TC-include-js-body-end -->
	<?php

	$output = ob_get_contents();
	ob_end_clean();

	return $output;
} );
