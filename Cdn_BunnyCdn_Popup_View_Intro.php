<?php
/**
 * File: Cdn_BunnyCdn_Popup_View_Intro.php
 *
 * Assists with configuring Bunny CDN as an object storage CDN.
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
<form class="w3tc_cdn_bunnycdn_form">
	<?php if ( isset( $details['error_message'] ) ) : ?>
		<div class="error">
			<?php
			/**
			 * Escape at the sink — single escape point. Suppliers
			 * (Cdn_BunnyCdn_Popup::render_intro callers) pass raw
			 * `__()` strings per the docblock contract above. If a
			 * future supplier mistakenly wraps in `esc_html()`, the
			 * only visible effect is cosmetic double-escape; the
			 * XSS guarantee here is unaffected (defense in depth).
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
					 * RT9-19: This intro wizard step takes a Bunny CDN
					 * Account API key. Render as `type="password"` with
					 * `autocomplete="new-password"` so browsers don't
					 * autofill / autosave / display the key, and so a
					 * page reload after entry doesn't leave the value
					 * on-screen for shoulder-surfing. The wizard never
					 * pre-fills this from stored config (the API key is
					 * only sent forward to the listing step), but the
					 * principle is the same: a credential never wants
					 * `type="text"` on a settings page.
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
				class="w3tc_cdn_bunnycdn_list_pull_zones w3tc-button-save button-primary"
				value="<?php esc_attr_e( 'Next', 'w3-total-cache' ); ?>" />
		</p>
		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>
