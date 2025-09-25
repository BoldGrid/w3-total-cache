<?php
/**
 * File: Cdn_TotalCdn_FsdDisablePopup_View.php
 *
 * @since X.X.X
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
	<h3><b><?php esc_html_e( 'Full Site Delivery DNS reminder', 'w3-total-cache' ); ?></b></h3>
	<p>
		<?php
		echo esc_html(
			sprintf(
				// Translators: 1 CDN product name.
				__(
					'You just disabled Full Site Delivery (FSD) for %1$s. If your DNS is still pointed at the CDN network the following will likely apply.',
					'w3-total-cache'
				),
				W3TC_CDN_NAME
			)
		);
		?>
	</p>
	<ul style="margin-left:15px;">
		<li><?php esc_html_e( 'Content edits and other purge events from W3 Total Cache will not clear the CDN cache.', 'w3-total-cache' ); ?></li>
		<li><?php esc_html_e( 'If you plan to switch back to static asset delivery, update DNS to point at your origin hostname so the pull zone and DNS are in sync.', 'w3-total-cache' ); ?></li>
	</ul>
	<div>
		<input type="submit" class="btn w3tc-size image btn-primary outset save palette-turquoise"
			value="<?php esc_attr_e( 'Continue', 'w3-total-cache' ); ?>">
		<input type="button" class="btn w3tc-size image btn-secondary outset palette-light-grey"
			value="<?php esc_attr_e( 'Cancel', 'w3-total-cache' ); ?>">
	</div>
</div>
