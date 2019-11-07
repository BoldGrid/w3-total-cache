<?php
/**
 * Template Name: browsercache prevent-caching
 * Description: A Page Template for testing browser cache
 *
 * @package W3TC
 * @subpackage W3TC QA
 */


$parsed = parse_url( get_template_directory_uri() );
$no_domain_uri = '//' . $parsed['host'] .
	(isset($parsed['port']) ? ':' . $parsed['port'] : '') .
	$parsed['path'];


get_header();?>
	<img id="image1" src="<?php echo get_template_directory_uri();?>/qa/image.jpg" />
	<img id="image2" src="<?php echo $no_domain_uri ?>/qa/image.jpg" />
	<img id="image3" src="//for-tests.sandbox/qa/image.jpg" />
<?php get_footer();
