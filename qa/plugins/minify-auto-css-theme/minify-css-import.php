<?php
/**
 * Template Name: Minify Auto Css
 * Description: A Page Template for testing minify auto js split
 *
 * @package W3TC
 * @subpackage W3TC QA
 */

wp_enqueue_style( 'minify-css1', get_template_directory_uri() . '/qa/minify-css1.css' );

// test relative paths, without host
add_action( 'wp_head', 'w3tcqa_wp_head' );

function w3tcqa_wp_head() {
	$css3_parts = parse_url( get_template_directory_uri() . '/qa/minify-css3.css' );
	$css3_path = $css3_parts['path'];

	echo "<link rel='stylesheet' id='minify-css3-css'  href='$css3_path' type='text/css' media='all' />";
}

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

				<?php

				$url6 = get_template_directory_uri() . '/qa/minify-auto-js6.js';
				// no quotes around script url
    			echo '<script type="text/javascript" src=' . $url6 . '></script>';

    			?>

			</div><!-- #content -->
		</div><!-- #primary -->
</div><!-- #primary -->

<?php get_footer(); ?>
