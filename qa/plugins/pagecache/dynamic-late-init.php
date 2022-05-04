<?php
/**
 * Plugin Name: Dynami Late Init
 */

add_action('wp_footer', 'test_wp_footer');

function test_wp_footer() {
	?>
	<!-- mfunc phptest -->
	echo (function_exists('esc_attr') ? esc_attr(484763 * 2) : 'no_function');
	<!-- /mfunc phptest -->
	<?php
}
