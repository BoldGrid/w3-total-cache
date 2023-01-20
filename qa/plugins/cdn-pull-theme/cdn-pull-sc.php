<?php

add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_script( 'jquery' );

	wp_enqueue_script( 'js1', get_template_directory_uri() . '/qa/theme-js.js',
		array(), W3TC_VERSION, false );
	wp_enqueue_script( 'js2', plugins_url( 'test-plugin/plugin-js.js' ),
		array(), W3TC_VERSION, false );
});



/**
 * Get images from Media Library.
 *
 * @return array
 */
function get_images_from_media_library() {
	$args = array(
		'post_type'      => 'attachment',
		'post_mime_type' => 'image',
		'post_status'    => 'inherit',
		'posts_per_page' => 5,
		'orderby'        => 'rand',
	);

	$query_images = new WP_Query( $args );
	$images       = array();

	foreach ( $query_images->posts as $image ) {
		$images[] = $image->guid;
	}

	return $images;
}

/**
 * Display images from Media Library.
 *
 * @return string
 */
function display_images_from_media_library() {
	$imgs = get_images_from_media_library();
	$html = '<div id="media-gallery">';

	foreach ( $imgs as $img ) {
		$html .= '<img src="' . $img . '" alt="" />';
	}

	$html .= '</div>';

	return $html;
}



add_shortcode( 'w3tcqa', function( $atts ) {
	ob_start();

	?>
	<img src="<?php echo esc_url( plugins_url( 'test-plugin/plugin-image1.jpg' ) ); ?>" />
	<img src="<?php echo esc_url( plugins_url( 'test-plugin/plugin-image2.png' ) ); ?>" />
	<img src="<?php echo esc_url( plugins_url( 'test-plugin/plugin-image3.gif' ) ); ?>" />
	<img src=<?php echo esc_url( get_template_directory_uri() . '/qa-theme-image1.jpg' ); ?> />
	<img src=<?php echo esc_url( get_template_directory_uri() . '/qa-theme-image2.png' ); ?> />
	<img src=<?php echo esc_url( get_template_directory_uri() . '/qa-theme-image3.gif' ); ?> />

	<?php echo display_images_from_media_library(); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?>
	<?php

	$output = ob_get_contents();
	ob_end_clean();

	return $output;
} );
