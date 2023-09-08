<?php
/**
 * File: Cdnfsd_BunnyCdn_Popup_View_Configured.php
 *
 * @since   X.X.X
 * @package W3TC
 */

namespace W3TC;

defined( 'W3TC' ) || die();

?>
<form class="w3tc_cdn_bunnycdn_fsd_form">
	<div class="metabox-holder">
		<?php Util_Ui::postbox_header( esc_html__( 'Success', 'w3-total-cache' ) ); ?>

		<div style="text-align: center">
			<?php esc_html_e( 'A pull zone was created successfully', 'w3-total-cache' ); ?>.<br />
		</div>

		<p class="submit">
			<input type="button" class="w3tc_cdn_bunnycdn_fsd_done w3tc-button-save button-primary"
				value="<?php esc_html_e( 'Done', 'w3-total-cache' ); ?>" />
		</p>
		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>
