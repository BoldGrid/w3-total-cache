<?php
/**
 * File: Cdnfsd_BunnyCdn_Popup_View_Configured.php
 *
 * @since   2.6.0
 * @package W3TC
 */

namespace W3TC;

defined( 'W3TC' ) || die();

?>
<?php if ( ! empty( $error_messages ) ) : ?>
	<div class="error">
		<?php echo $error_messages; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
<?php endif; ?>
<form class="w3tc_cdn_bunnycdn_fsd_form">
	<div class="metabox-holder">
		<?php Util_Ui::postbox_header( esc_html__( 'Success', 'w3-total-cache' ) ); ?>

		<div style="text-align: center">
		<p>
			<?php esc_html_e( 'A pull zone has been configured successfully.', 'w3-total-cache' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'There may be additional configuration required for full-site delivery, such as DNS changes and SSL/TLS certificate installation.', 'w3-total-cache' ); ?>
		</p>
		<p>
			<a target="_blank" href="<?php echo esc_url( W3TC_BUNNYCDN_CDN_URL ); ?>"><?php esc_html_e( 'Click here', 'w3-total-cache' ); ?></a>
			<?php esc_html_e( 'to configure additional settings for this pull zone at Bunny.net.', 'w3-total-cache' ); ?>
		</p>
		</div>

		<p class="submit">
			<input type="button" class="w3tc_cdn_bunnycdn_fsd_done w3tc-button-save button-primary"
				value="<?php esc_html_e( 'Done', 'w3-total-cache' ); ?>" />
		</p>
		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>
