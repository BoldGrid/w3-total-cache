<?php
/**
 * File: Cdn_TotalCdn_Popup_View_Fsd_Blocked.php
 *
 * @since X.X.X
 * @package W3TC
 */

namespace W3TC;

defined( 'W3TC' ) || die;

?>
<div class="metabox-holder">
	<?php Util_Ui::postbox_header( \esc_html__( 'Deauthorization Unavailable', 'w3-total-cache' ) ); ?>
	<div class="notice notice-warning">
		<p>
			<?php
			\printf(
				// Translators: %s: CDN name.
				esc_html__(
					'In order to deauthorize or delete this pull zone you must first point the DNS back to your origin and then disable %1$s Full Site Delivery (FSD). If the DNS is not updated before disabling FSD, your site may become unreachable.',
					'w3-total-cache'
				),
				esc_html( W3TC_CDN_NAME )
			);
			?>
		</p>
	</div>
	<?php Util_Ui::postbox_footer(); ?>
</div>
