<?php
/**
 * File: basic.php
 *
 * Browser cache: Basic: A Page Template for testing browser cache.
 *
 * Template Name: Browser Cache: Basic
 * Template Post Type: post, page
 *
 * @package W3TC
 *
 * @subpackage QA
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */

get_header();
?>
	<img id="image" src="<?php echo esc_url( get_template_directory_uri() ); ?>/qa/image.jpg" />
<?php
get_footer();
