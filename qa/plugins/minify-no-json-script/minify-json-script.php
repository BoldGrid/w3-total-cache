<?php
/**
 * File: minify-json-script.php
 *
 * Minify auto javascript test: A Page Template for testing minify auto js split.
 *
 * Template Name: Minify: Auto: JS split
 * Template Post Type: post, page
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */

// Enqueue showcase script for the slider.
wp_enqueue_script( 'jquery' );

/**
 * Minify JS.
 */
function minify_js() {
	?>

	<script type="text/javascript">
		/* <![CDATA[ */
		var js4 = "#js4";
		console.log(   "hello"  + "   world"  );
		/* ]]> */
	</script>
	<script>
		console.log(   "hello2"  + " world2"  );
	</script>
	<script type="application/json">
		{ "a": ["b", "c"]  }
	</script>

	<?php
}

add_action( 'wp_head', 'minify_js' );

@get_header();
?>

	<div id="main-content" class="main-content">
		<div id="primary" class="content-area">
			<div id="content" role="main" class="site-content">

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

<?php
@get_footer();
