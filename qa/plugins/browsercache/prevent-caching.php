<?php
/**
 * File: prevent-cache.php
 *
 * Browsercache prevent caching: A Page Template for testing browser cache.
 *
 * Template Name: Browser cache: Prevent cache
 * Template Post Type: post, page
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */

$parsed = wp_parse_url( get_template_directory_uri() );

$no_domain_uri = '//' . $parsed['host'] . ( isset( $parsed['port'] ) ? ':' . $parsed['port'] : '' ) . $parsed['path'];

@get_header();
?>

	<img id="image1" src="<?php echo esc_url( get_template_directory_uri() ); ?>/qa/image.jpg" />
	<img id="image2" src="<?php echo esc_url( $no_domain_uri ); ?>/qa/image.jpg" />
	<img id="image3" src="//for-tests.sandbox/qa/image.jpg" />

<?php
@get_footer();
