<?php
/**
 * File: Cdn_GoogleDrive_Popup_AuthReturn_View.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<form action="admin.php?page=w3tc_cdn" method="post" style="padding: 20px">
	<?php
	// The folder choice is posted with the opaque handle. Credentials stay in the server-side store.
	Util_Ui::hidden(
		'w3tc-googledrive-oauth-handle',
		Cdn_GoogleDrive_OAuthTokens::HANDLE_PARAM,
		$oauth_handle
	);
	Util_Ui::hidden(
		'w3tc-googledrive-oauth-state',
		Cdn_GoogleDrive_OAuthState::STATE_PARAM,
		$oauth_state
	);
	echo wp_kses(
		Util_Ui::nonce_field( Util_Nonce::admin_action( 'w3tc_cdn_google_drive_auth_set' ) ),
		array(
			'input' => array(
				'type'  => array(),
				'name'  => array(),
				'value' => array(),
			),
		)
	);
	?>
	<br /><br />
	<div class="metabox-holder">
		<?php Util_Ui::postbox_header( esc_html__( 'Select folder', 'w3-total-cache' ) ); ?>
		<table class="form-table">
			<tr>
				<td><?php esc_html_e( 'Folder:', 'w3-total-cache' ); ?></td>
				<td>
					<?php foreach ( $folders as $w3tc_folder ) : ?>
						<label>
							<input name="folder" type="radio" class="w3tc-ignore-change"
								value="<?php echo esc_attr( $w3tc_folder->id ); ?>" />
							<?php echo esc_html( $w3tc_folder->title ); ?>
						</label><br />
					<?php endforeach ?>
					<label>
						<input name="folder" type="radio" class="w3tc-ignore-change" value="" />
						<?php esc_html_e( 'Add new folder:', 'w3-total-cache' ); ?>
					</label>
					<input name="folder_new" type="text" class="w3tc-ignore-change" />
				</td>
			</tr>
		</table>

		<p class="submit">
			<input type="submit" name="w3tc_cdn_google_drive_auth_set"
				class="w3tc-button-save button-primary"
				value="<?php esc_attr_e( 'Apply', 'w3-total-cache' ); ?>"<?php echo wp_kses( Util_Ui::admin_submit_nonce_attr( 'w3tc_cdn_google_drive_auth_set' ), array( 'data-w3tc-nonce' => array() ) ); ?> />
		</p>
		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>
