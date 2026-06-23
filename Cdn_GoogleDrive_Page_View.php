<?php
/**
 * File: Cdn_GoogleDrive_Page_View.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die();
}

$w3tc_refresh_token = $w3tc_config->get_string( 'cdn.google_drive.refresh_token' );

?>
<tr>
	<th style="width: 300px;"><label><?php esc_html_e( 'Authorize:', 'w3-total-cache' ); ?></label></th>
	<td>
		<?php if ( empty( $w3tc_refresh_token ) ) : ?>
			<input class="w3tc_cdn_google_drive_authorize button" type="button"
				value="<?php esc_attr_e( 'Authorize', 'w3-total-cache' ); ?>" />
		<?php else : ?>
			<input class="w3tc_cdn_google_drive_authorize button" type="button"
				value="<?php esc_attr_e( 'Reauthorize', 'w3-total-cache' ); ?>" />
		<?php endif ?>
	</td>
</tr>

<?php if ( ! empty( $w3tc_refresh_token ) ) : ?>
	<tr>
		<th><label for="cdn_s3_bucket"><?php esc_html_e( 'Folder:', 'w3-total-cache' ); ?></label></th>
		<td>
			<a href="<?php echo esc_url( $w3tc_config->get_string( 'cdn.google_drive.folder.url' ) ); ?>">/<?php echo esc_html( $w3tc_config->get_string( 'cdn.google_drive.folder.title' ) ); ?></a>
		</td>
	</tr>
	<tr>
		<th colspan="2">
			<input id="cdn_test"
				class="button {type: 'google_drive', nonce: '<?php echo esc_attr( Util_Nonce::create_admin( 'w3tc_cdn_test' ) ); ?>'}"
				type="button"
				value="<?php esc_attr_e( 'Test upload', 'w3-total-cache' ); ?>" />
			<span id="cdn_test_status" class="w3tc-status w3tc-process"></span>
		</th>
	</tr>
<?php endif ?>
