<?php
/**
 * File: Cdn_TotalCdn_FsdEnablePopup_View.php
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<div class="w3tc-overlay-logo"></div>
<div class="w3tchelp_content">
	<h3><b><?php esc_html_e( 'Full Site Delivery setup required', 'w3-total-cache' ); ?></b></h3>
	<p>
		<?php
		echo esc_html(
			sprintf(
				// Translators: 1 CDN product name.
				__(
					'You just enabled Full Site Delivery (FSD) for %1$s. To finish the setup you need to update your DNS so traffic is routed through the CDN.',
					'w3-total-cache'
				),
				W3TC_CDN_NAME
			)
		);
		?>
	</p>
	<p>
		<?php
		echo wp_kses(
			sprintf(
				// translators: 1 opening HTML anchor tag, 2 closing HTML anchor tag.
				__( 'Need help? Follow our %1$sFull Site Delivery setup guide%2$s for step-by-step DNS instructions.', 'w3-total-cache' ),
				'<a href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/cdn/full-site-delivery/' ) . '" target="_blank" rel="noopener noreferrer">',
				'</a>'
			),
			array(
				'a' => array(
					'href'   => array(),
					'target' => array(),
					'rel'    => array(),
				),
			)
		);
		?>
	</p>
	<ul style="margin-left:15px;">
		<li><?php esc_html_e( 'After saving these settings, look for the new shortcut link to the CDN settings page to complete the configuration.', 'w3-total-cache' ); ?></li>
		<li><?php esc_html_e( 'The CDN settings screen shows an FSD status indicator—when it reads Authorized the pull zone is ready and DNS updates are detected.', 'w3-total-cache' ); ?></li>
		<li><?php esc_html_e( 'Until DNS changes propagate, your site will continue serving from the origin and purge requests from W3 Total Cache will fail.', 'w3-total-cache' ); ?></li>
	</ul>
	<div>
		<input type="submit" class="btn w3tc-size image btn-primary outset save palette-turquoise"
			value="<?php esc_attr_e( 'Continue', 'w3-total-cache' ); ?>">
		<input type="button" class="btn w3tc-size image btn-secondary outset palette-light-grey"
			value="<?php esc_attr_e( 'Cancel', 'w3-total-cache' ); ?>">
	</div>
</div>