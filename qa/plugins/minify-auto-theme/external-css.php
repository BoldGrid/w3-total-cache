<?php
/**
 * File: external-css.php
 *
 * Minify Url Deformation CSS Test Template: A Page Template for testing minify manual css font split.
 *
 * Template Name: Minify: Auto: Theme: External CSS
 * Template Post Type: post, page
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */

/**
 * Enqueue showcase script for the slider.
 */
function external_font_script() {
	echo '<link rel="stylesheet" type="text/css" id="font_test" href="//fonts.googleapis.com/css?family=Ubuntu%3A400%2C700%26subset%3Dlatin%2Clatin-ex" media="all" />'; // phpcs:ignore
}

add_action( 'wp_head', 'external_font_script' );

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
