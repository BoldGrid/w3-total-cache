<?php
/**
 * File: Cdnfsd_BunnyCdn_Popup_Deauthorize.php
 *
 * Assists to deauthorize Bunny CDN as an objects CDN and optionally delete the pull zone.
 *
 * @since   2.6.0
 * @package W3TC
 *
 * @param Config  $config              W3TC configuration.
 * @param string  $origin_url          Origin URL or IP.
 * @param string  $name                Pull zone name.
 * @param string  $cdn_hostname        CDN hostname.
 * @param string  $cdn_pull_zone_id    CDN pull zone id.
 * @param string  $cdnfsd_pull_zone_id CDN FSD pull zone id.
 */

namespace W3TC;

defined( 'W3TC' ) || die;

// Determine if the same pull zone is used for CDN and CDN FSD.  If so, then we'll show a message that it will deactivate both.
$is_same_zone = $cdn_pull_zone_id === $cdnfsd_pull_zone_id;

?>
<form class="w3tc_cdn_bunnycdn_form" method="post">
	<input type="hidden" name="pull_zone_id" />
	<div class="metabox-holder">
		<?php Util_Ui::postbox_header( \esc_html__( 'Deauthorize pull zone', 'w3-total-cache' ) ); ?>
		<table class="form-table">
			<tr>
				<td><?php \esc_html_e( 'Name', 'w3-total-cache' ); ?>:</td>
				<td><?php echo \esc_html( $name ); ?></td>
			</tr>
			<tr>
				<td><?php \esc_html_e( 'Origin URL / IP', 'w3-total-cache' ); ?>:</td>
				<td><?php echo \esc_html( $origin_url ); ?></td>
			</tr>
			<tr>
				<td><?php \esc_html_e( 'CDN hostname', 'w3-total-cache' ); ?>:</td>
				<td><?php echo \esc_html( $cdn_hostname ); ?></td>
			</tr>
			<tr>
				<td><?php \esc_html_e( 'Delete', 'w3-total-cache' ); ?>:</td>
				<td>
					<input id="w3tc-delete-zone" type="checkbox" name="delete_pull_zone" value="yes" /> Delete the pull zone
					<?php if ( $is_same_zone ) : ?>
					<p class="notice notice-warning">
						<?php \esc_html_e( 'This same pull zone is used for full-site delivery.  If you delete this pull zone, then full-site delivery will be deauthorized.', 'w3-total-cache' ); ?>
					</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<p class="submit">
			<input type="button" class="w3tc_cdn_bunnycdn_deauthorize w3tc-button-save button-primary"
				value="<?php \esc_attr_e( 'Deauthorize', 'w3-total-cache' ); ?>" />
		</p>
		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>
