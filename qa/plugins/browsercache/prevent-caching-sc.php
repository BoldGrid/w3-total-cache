<?php

add_shortcode( 'w3tcqa', function( $atts ) {
	ob_start();

	$parsed = wp_parse_url( get_template_directory_uri() );
	$no_domain_uri = '//' . $parsed['host'] . ( isset( $parsed['port'] ) ? ':' . $parsed['port'] : '' ) . $parsed['path'];

	?>
	<img id="image1" src="<?php echo esc_url( get_template_directory_uri() ); ?>/qa/image.jpg" />
	<img id="image2" src="<?php echo esc_url( $no_domain_uri ); ?>/qa/image.jpg" />
	<img id="image3" src="//for-tests.sandbox/qa/image.jpg" />
	<?php

	$output = ob_get_contents();
	ob_end_clean();

	return $output;
} );
