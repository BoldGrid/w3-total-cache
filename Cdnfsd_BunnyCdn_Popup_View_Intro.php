<?php
/**
 * File: Cdnfsd_BunnyCdn_Popup_View_Intro.php
 *
 * Assists with configuring Bunny CDN as a full-site delivery CDN.
 * Asks to enter an account API key from the Bunny CDN main account.
 *
 * @since   2.6.0
 * @package W3TC
 *
 * @param array $details {
 *     Bunny CDN API configuration details.
 *
 *     @type string $account_api_key Account API key.
 *     @type string $error_message   Error message (optional).  String already escaped.
 * }
 */

namespace W3TC;

defined( 'W3TC' ) || die();

?>
<form class="w3tc_cdn_bunnycdn_fsd_form">
	<?php if ( isset( $details['error_message'] ) ) : ?>
		<div class="error">
			<?php echo $details['error_message']; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	<?php endif; ?>
	<div class="metabox-holder">
		<?php Util_Ui::postbox_header( esc_html__( 'Bunny CDN API Configuration', 'w3-total-cache' ) ); ?>
		<table class="form-table">
			<tr>
				<td><?php esc_html_e( 'Account API Key', 'w3-total-cache' ); ?>:</td>
				<td>
					<input id="w3tc-account-api-key" name="account_api_key" type="text" class="w3tc-ignore-change"
						style="width: 550px" value="<?php echo esc_attr( $details['account_api_key'] ); ?>" />
					<p class="description">
						<?php esc_html_e( 'To obtain your account API key,', 'w3-total-cache' ); ?>
						<a target="_blank" href="<?php echo esc_url( W3TC_BUNNYCDN_SETTINGS_URL ); ?>"><?php esc_html_e( 'click here', 'w3-total-cache' ); ?></a>,
						<?php esc_html_e( 'log in using the main account credentials, and paste the API key into the field above.', 'w3-total-cache' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<input type="button"
				class="w3tc_cdn_bunnycdn_fsd_list_pull_zones w3tc-button-save button-primary"
				value="<?php esc_attr_e( 'Next', 'w3-total-cache' ); ?>" />
		</p>
		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>
