<?php
/**
 * File: minify-auto-js.php
 *
 * Minify Auto JS Test Template: A Page Template for testing minify auto js split.
 *
 * Template Name: Minify: Auto: JS: Semicolon
 * Template Post Type: post, page
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */

// Enqueue showcase script for the slider.
wp_enqueue_script( 'jquery' );

wp_enqueue_script( 'minify-js1', get_template_directory_uri() . '/qa/minify-auto-js1.js', array(), W3TC_VERSION, false );
wp_enqueue_script( 'minify-js2', get_template_directory_uri() . '/qa/minify-auto-js2.js', array(), W3TC_VERSION, false );
wp_enqueue_script( 'minify-js3', get_template_directory_uri() . '/qa/minify-auto-js3.js', array(), W3TC_VERSION, false );

@get_header();
?>

	<div id="main-content" class="main-content">
		<div id="primary" class="content-area">
			<div id="content" role="main" class="site-content">
			<p>JS1: <span id="js1">failed</span></p>
			<p>JS2: <span id="js2">failed</span></p>
			<p>JS3: <span id="js3">failed</span></p>
			<p>JS4: <span id="js4">failed</span></p>

				<?php
				while ( have_posts() ) {
					the_post();

					/**
					 * We are using a heading by rendering the_content
					 * If we have content for this page, let's display it.
					 */
					if ( empty( get_the_content() ) ) {
						get_template_part( 'content', 'intro' );
					}
				}

				?>

			</div><!-- #content -->
		</div><!-- #primary -->
</div><!-- #primary -->
<script>
	jQuery(document).ready(function() {
		var s = jQuery('#js4');
		s.removeClass( 'enhanced' );
		s.addClass( 'disabled' );
		jQuery('#js4').text('passed');
	});
</script>

<?php
@get_footer();
