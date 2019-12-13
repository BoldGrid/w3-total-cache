<?php
/**
 * Template Name: template-amp
 * Description: A Page Template for testing AMP extension
 *
 * @package W3TC
 * @subpackage W3TC QA
 */

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

				if (!empty($_REQUEST['amp'])) {
					echo '!amp-page!';
				} else {
					echo '!regular-page!';
				}

				?>

			</div><!-- #content -->
		</div><!-- #primary -->
</div><!-- #primary -->

<?php get_footer(); ?>
