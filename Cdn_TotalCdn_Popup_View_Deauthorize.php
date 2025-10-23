<?php
/**
 * File: Cdnfsd_TotalCdn_Popup_Deauthorize.php
 *
 * Assists to deauthorize Total CDN as an objects CDN and optionally delete the pull zone.
 *
 * @since   2.6.0
 * @package W3TC
 *
 * @param Config  $config       W3TC configuration.
 * @param string  $origin_url   Origin URL or IP.
 * @param string  $name         Pull zone name.
 * @param string  $cdn_hostname CDN hostname.
 * @param string  $pull_zone_id CDN pull zone id.
 */

namespace W3TC;

defined( 'W3TC' ) || die;

?>
<form class="w3tc_cdn_totalcdn_form" method="post">
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
					<p class="notice notice-warning">
						<?php
						if ( $is_pro ) {
							\esc_html_e( 'This same pull zone is used for full-site delivery.  If you delete this pull zone, then full-site delivery will be deauthorized.', 'w3-total-cache' );
						}
						?>
					</p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<input type="button" class="w3tc_cdn_totalcdn_deauthorize w3tc-button-save button-primary"
				value="<?php \esc_attr_e( 'Deauthorize', 'w3-total-cache' ); ?>" />
		</p>
		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>
