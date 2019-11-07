<?php
/**
 * Template Name: Minify Auto JS Test Template
 * Description: A Page Template for testing minify auto js split
 *
 * @package W3TC
 * @subpackage W3TC QA
 */

// Enqueue showcase script for the slider
wp_enqueue_script( 'jquery' );


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
add_action('wp_head', 'minify_js');

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


			</div><!-- #content -->
		</div><!-- #primary -->
</div><!-- #primary -->

<?php get_footer(); ?>
