<?php
/**
 * File: Cdn_BunnyCdn_Popup_View_Configured.php
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
<form class="w3tc_cdn_bunnycdn_form">
	<div class="metabox-holder">
		<?php Util_Ui::postbox_header( esc_html__( 'Success', 'w3-total-cache' ) ); ?>

		<div style="text-align: center">
			<?php esc_html_e( 'A pull zone has been configured successfully', 'w3-total-cache' ); ?>.<br />
		</div>

		<p class="submit">
			<input type="button" class="w3tc_cdn_bunnycdn_done w3tc-button-save button-primary"
				value="<?php esc_html_e( 'Done', 'w3-total-cache' ); ?>" />
		</p>
		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>
