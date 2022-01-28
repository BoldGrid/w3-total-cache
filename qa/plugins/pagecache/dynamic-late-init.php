<?php
/**
 * File: dynamic-late-init.php
 *
 * Page cache: Dynami late init.
 *
 * @package W3TC
 * @subpackage QA
 */

add_action(
	'wp_footer',
	function () {
		?>
		<!-- mfunc phptest -->
		echo ( function_exists( 'esc_attr' ) ? esc_attr( 484763 * 2 ) : 'no_function' );
		<!-- /mfunc phptest -->
		<?php
	}
);
