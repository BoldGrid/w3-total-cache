<?php
/**
 * File: PageSpeed_Widget_View_NotConfigured.php
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<p>
	<?php esc_html_e( 'Google Page Speed score is not available.', 'w3-total-cache' ); ?>
</p>
<p>
	<?php
	echo wp_kses(
		sprintf(
			// translators: 1 opening HTML a tag, 2 closing HTML a tag.
			__(
				'Please follow the directions found in the Miscellanous settings box on the %1$sGeneral Settings%2$s tab.',
				'w3-total-cache'
			),
			'<a href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general' ) ) . '">',
			'</a>'
		),
		array(
			'a' => array(
				'href' => array(),
			),
		)
	);
	?>
</p>
