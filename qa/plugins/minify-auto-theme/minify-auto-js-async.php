<?php
/**
 * Template Name: minify-auto-js-async
 * Description: A Page Template for testing minify auto js split
 *
 * @package W3TC
 * @subpackage W3TC QA
 */

// Enqueue showcase script for the slider
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

get_header(); ?>
	<div id="main-content" class="main-content">
		<div id="primary" class="content-area">
			<div id="content" role="main" class="site-content">
			<p>JS1: <span id="js1">failed</span></p>
			<p>JS2: <span id="js2">failed</span></p>
			<p>JS3: <span id="js3">failed</span></p>
			<p>JS4: <span id="js4">failed</span></p>
			<p>JS5: <span id="js5">failed</span></p>



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
