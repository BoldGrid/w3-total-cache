<?php
/**
 * Template Name: CDN PullTest Template
 * Description: w3tc
 *
 * @package W3TC
 * @subpackage W3TC QA
 */



function get_images_from_media_library() {
    $args = array(
        'post_type' => 'attachment',
        'post_mime_type' =>'image',
        'post_status' => 'inherit',
        'posts_per_page' => 5,
        'orderby' => 'rand'
    );
    $query_images = new WP_Query( $args );
    $images = array();
    foreach ( $query_images->posts as $image) {
        $images[]= $image->guid;
    }
    return $images;
}

function display_images_from_media_library() {
    $imgs = get_images_from_media_library();
    $html = '<div id="media-gallery">';

    foreach($imgs as $img) {
        $html .= '<img src="' . $img . '" alt="" />';
    }

    $html .= '</div>';

    return $html;
}



// Enqueue showcase script for the slider
wp_enqueue_script( 'jquery' );

wp_enqueue_script( 'js1', get_template_directory_uri() . '/qa/theme-js.js' );
wp_enqueue_script( 'js2', plugins_url( 'test-plugin/plugin-js.js' ) );

get_header(); ?>
	<div id="main-content" class="main-content">
		<div id="primary" class="content-area">
			<div id="content" role="main" class="site-content">
				<?php while ( have_posts() ) : the_post(); ?>

				<?php
					/**
					 * We are using a heading by rendering the_content
					 * If we have content for this page, let's display it.
					 */
					if ( '' != get_the_content() )
						get_template_part( 'content', 'intro' );
				?>

				<?php endwhile; ?>

				<img src="<?php echo plugins_url( 'test-plugin/plugin-image1.jpg' ) ?>" />
				<img src="<?php echo plugins_url( 'test-plugin/plugin-image2.png' ) ?>" />
				<img src="<?php echo plugins_url( 'test-plugin/plugin-image3.gif' ) ?>" />
				<img src=<?php echo get_template_directory_uri() . '/qa-theme-image1.jpg' ?> />
				<img src=<?php echo get_template_directory_uri() . '/qa-theme-image2.png' ?> />
				<img src=<?php echo get_template_directory_uri() . '/qa-theme-image3.gif' ?> />

				<?php echo display_images_from_media_library(); ?>

				<?php

    			?>

			</div><!-- #content -->
		</div><!-- #primary -->
</div><!-- #primary -->

<?php get_footer(); ?>
