<?php
/**
 * File: template-amp.php
 *
 * Page cache: Page template for testing AMP extension.
 *
 * @package W3TC
 * @subpackage W3TC QA
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */

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

					the_content();
				}

				if ( isset( $_REQUEST['amp'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					echo '!amp-page!';
				} else {
					echo '!regular-page!';
				}

				?>

			</div><!-- #content -->
		</div><!-- #primary -->
</div><!-- #primary -->

<?php
@get_footer();
