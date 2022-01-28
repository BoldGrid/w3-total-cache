<?php
/**
 * File: minify-auto-comment-after.php
 *
 * Minify Auto Comment After JS Test Template: A Page Template for testing minify auto js split.
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.EnqueuedResources.NonEnqueuedScript
 */

// Enqueue showcase script for the slider.
wp_enqueue_script( 'jquery' );

wp_enqueue_script( 'minify-js1', get_template_directory_uri() . '/qa/minify-auto-js1.js', array(), W3TC_VERSION, false );
wp_enqueue_script( 'minify-js2', get_template_directory_uri() . '/qa/minify-auto-js2.js', array(), W3TC_VERSION, false );
wp_enqueue_script( 'minify-js3', get_template_directory_uri() . '/qa/minify-auto-js3.js', array(), W3TC_VERSION, false );

/**
 * Minify JS 4.
 */
function minify_js4() {
	$url4 = get_template_directory_uri() . '/qa/minify-auto-js4.js';
	$url5 = get_template_directory_uri() . '/qa/minify-auto-js5.js';

	?>

	<script type="text/javascript">
	/* <![CDATA[ */
	var js4 = '#js4';
	/* ]]> */
	</script>
	<script type="text/javascript" src="<?php echo esc_url( $url4 ); ?>"></script>
	<script type="text/javascript" src='<?php echo esc_url( $url5 ); ?>'></script>
	<!--some comment -->

	<?php
}

add_action( 'wp_head', 'minify_js4' );

@get_header();
?>

	<div id="main-content" class="main-content">
		<div id="primary" class="content-area">
			<div id="content" role="main" class="site-content">
			<p>JS1: <span id="js1">failed</span></p>
			<p>JS2: <span id="js2">failed</span></p>
			<p>JS3: <span id="js3">failed</span></p>
			<p>JS4: <span id="js4">failed</span></p>
			<p>JS5: <span id="js5">failed</span></p>
			<p>JS6: <span id="js6">failed</span></p>

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

				$url6 = get_template_directory_uri() . '/qa/minify-auto-js6.js';

				// No quotes around script url.
				echo '<script type="text/javascript" src=' . esc_url( $url6 ) . '></script>';

				?>

			</div><!-- #content -->
		</div><!-- #primary -->
</div><!-- #primary -->

<?php
@get_footer();
