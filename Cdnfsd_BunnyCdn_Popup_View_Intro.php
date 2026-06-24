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
 *     @type string $w3tc_account_api_key Account API key.
 *     @type string $error_message   Error message (optional). Suppliers MUST pass
 *                                    the raw translated string (no `esc_html()`,
 *                                    no `esc_html__()`) — this view is the single
 *                                    sink-side escape point. See Copilot PR #4
 *                                    feedback on the double-escape cosmetic
 *                                    regression that the prior supplier-side
 *                                    `\esc_html()` wraps were causing.
 * }
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
defined( 'W3TC' ) || die();

?>
<form class="w3tc_cdn_bunnycdn_fsd_form">
	<?php if ( isset( $details['error_message'] ) ) : ?>
		<div class="error">
			<?php
			/**
			 * Escape at the sink — single escape point. SDK
			 * exception messages occasionally embed user-controlled
			 * URLs / IDs; pinning the escape here means a future
			 * supplier passing a raw `$ex->getMessage()` to
			 * `wp_send_json_error` still renders safely. Suppliers
			 * in the BunnyCDN code paths pass raw translated text
			 * per the docblock contract above — no double-encoded
			 * entities show up in legitimate error strings.
			 */
			echo esc_html( (string) $details['error_message'] );
			?>
		</div>
	<?php endif; ?>
	<div class="metabox-holder">
		<?php Util_Ui::postbox_header( esc_html__( 'Bunny CDN API Configuration', 'w3-total-cache' ) ); ?>
		<table class="form-table">
			<tr>
				<td><?php esc_html_e( 'Account API Key', 'w3-total-cache' ); ?>:</td>
				<td>
					<?php
					/**
					 * RT9-19: See Cdn_BunnyCdn_Popup_View_Intro.php for
					 * the rationale — render the Account API key as
					 * `type="password"` + `autocomplete="new-password"`
					 * so the credential never sits in cleartext on the
					 * wizard form.
					 */
					$w3tc_intro_api_key = isset( $details['account_api_key'] ) ? (string) $details['account_api_key'] : '';
					?>
					<input id="w3tc-account-api-key" name="account_api_key" type="password" class="w3tc-ignore-change"
						style="width: 550px" autocomplete="new-password"
						value="<?php echo esc_attr( $w3tc_intro_api_key ); ?>" />
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
