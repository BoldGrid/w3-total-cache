<?php
/**
 * File: buy.php
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

?>
<div>
	<?php
	echo wp_kses(
		sprintf(
			// translators: 1 HTML a link to buy plugin.
			__(
				'Unlock more speed, %1$s now!',
				'w3-total-cache'
			),
			'<a class="button w3tc-gopro-button" href="' . esc_url( 'https://www.boldgrid.com/w3-total-cache/' ) . '" target="_blank">' . esc_html__( 'Upgrade', 'w3-total-cache' ) . '</a>'
		),
		array(
			'a' => array(
				'class'  => array(),
				'href'   => array(),
				'target' => array(),
			),
		)
	);
	?>
	<div id="w3tc-license-instruction" style="display: none;">
		<p class="description">
			<?php
			echo wp_kses(
				sprintf(
					// translators: 1 HTML a tag to W3TC licencing page.
					__(
						'Please enter the license key you received after successful checkout %1$s.',
						'w3-total-cache'
					),
					'<a href="' . esc_url( network_admin_url( 'admin.php?page=w3tc_general#licensing' ) ) . '">' . esc_html( __( 'here', 'w3-total-cache' ) ) . '</a>'
				),
				array(
					'a' => array(
						'href' => array(),
					),
				)
			);
			?>
		</p>
	</div>
</div>
