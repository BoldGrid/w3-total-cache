<?php
/**
 * File: Cdn_TotalCdn_Popup_View_Add_Custom_Hostname.php
 *
 * Shows a form a to add a custom hostname to a pull zone
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
<form class="w3tc_cdn_<?php echo esc_attr( 'totalcdn' ); ?>_form">
	<div class="metabox-holder">
		<?php Util_Ui::postbox_header( esc_html__( 'Add a Custom Hostname', 'w3-total-cache' ) ); ?>
			<table class="form-table">
				<tbody>
					<tr>
						<td><?php \esc_html_e( 'Custom hostname', 'w3-total-cache' ); ?>:</td>
						<td>
							<input id="w3tc-custom-hostname" type="text" name="custom_hostname"
								value="<?php echo \esc_attr( $custom_hostname ); ?>" size="80"/>
							<p class="description">
								<?php \esc_html_e( 'The custom hostname must be a CNAME to the CDN hostname.', 'w3-total-cache' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<td><?php \esc_html_e( 'Current CDN Hostname', 'w3-total-cache' ); ?>:</td>
						<td class="w3tc_config_value_text">
							<?php echo \esc_html( $cdn_hostname ); ?>
						</td>
				</tbody>
			</table>
			<p class="submit">
			<input type="button"
				class="w3tc_cdn_<?php echo esc_attr( 'totalcdn' ); ?>_save_custom_hostname w3tc-button-save button-primary"
				value="<?php esc_attr_e( 'Save Custom Hostname', 'w3-total-cache' ); ?>" />
			</p>
		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>
