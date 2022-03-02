<?php
add_shortcode( 'w3tcqa', 'w3tcqa_shortcode' );

function w3tcqa_shortcode( $atts ) {
	ob_start();

	?>
	<img id="image" src="<?php echo esc_url( get_template_directory_uri() ); ?>/qa/image.jpg" />
	<?php

	$output = ob_get_contents();
	ob_end_clean();

	return $output;
}
