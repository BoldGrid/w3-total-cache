<?php
/**
 * File: Cdn_BunnyCdn_Popup_Pull_Zones.php
 *
 * Assists with configuring Bunny CDN as an object storage CDN.
 * A pull zone selection is presented along with a form to add a new pull zone.
 *
 * @since   2.6.0
 * @package W3TC
 *
 * @param string           $w3tc_account_api_key Account PI key.
 * @parm  Cdn_BunnyCdn_Api $api             API class object.
 * @param array            $details {
 *     Bunny CDN API configuration details.
 *
 *     @type array  $pull_zones           Pull zones.
 *     @type string $suggested_origin_url Suggested origin URL or IP.
 *     @type string $suggested_zone_name  Suggested pull zone name.
 *     @type int    $pull_zone_id         Pull zone id.
 *     @type string $error_message        Error message (optional).
 * }
 * @param string           $server_ip       Server IP address.
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
defined( 'W3TC' ) || die();

?>
<form class="w3tc_cdn_bunnycdn_form" method="post">
	<input type="hidden" name="pull_zone_id" />
	<input type="hidden" name="cdn_hostname" />
	<div class="metabox-holder">
		<?php Util_Ui::postbox_header( esc_html__( 'Select a pull zone', 'w3-total-cache' ) ); ?>
		<table class="form-table">
			<tr>
				<select id="w3tc-pull-zone-id">
					<option value=""<?php echo empty( $details['pull_zone_id'] ) ? ' selected' : ''; ?>>Add a new pull zone</option>
			<?php
			if ( ! empty( $details['pull_zones'] ) ) {
				// List pull zones for selection.
				foreach ( $details['pull_zones'] as $w3tc_pull_zone ) {
					// Skip pull zones that are disabled or suspended.
					if ( ! $w3tc_pull_zone['Enabled'] || $w3tc_pull_zone['Suspended'] ) {
						continue;
					}

					// Get the CDN hostname and custom hostnames.
					$w3tc_cdn_hostname     = '?';
					$w3tc_custom_hostnames = array();

					// Get the CDN hostname.  It should be the system hostname.
					foreach ( $w3tc_pull_zone['Hostnames'] as $w3tc_hostname ) {
						if ( ! empty( $w3tc_hostname['Value'] ) ) {
							if ( ! empty( $w3tc_hostname['IsSystemHostname'] ) ) {
								// CDN hostname (system); there should only be one.
								$w3tc_cdn_hostname = $w3tc_hostname['Value'];
							} else {
								// Custom hostnames; 0 or more.
								$w3tc_custom_hostnames[] = $w3tc_hostname['Value'];
							}
						}
					}

					// Determine the origin URL/IP.
					$w3tc_origin_url = empty( $w3tc_pull_zone['OriginUrl'] ) ? $w3tc_cdn_hostname : $w3tc_pull_zone['OriginUrl'];

					// Determine if the current option is selected.
					$w3tc_is_selected = isset( $details['pull_zone_id'] ) && $details['pull_zone_id'] === $w3tc_pull_zone['Id'];

					// Print the select option.
					?>
					<option value="<?php echo esc_attr( $w3tc_pull_zone['Id'] ); ?>"
						<?php echo $w3tc_is_selected ? ' selected' : ''; ?>
						data-origin="<?php echo esc_html( $w3tc_origin_url ); ?>"
						data-name="<?php echo esc_attr( $w3tc_pull_zone['Name'] ); ?>"
						data-cdn-hostname="<?php echo esc_attr( $w3tc_cdn_hostname ); ?>"
						data-custom-hostnames="<?php echo esc_attr( implode( ',', $w3tc_custom_hostnames ) ); ?>">
						<?php echo esc_attr( $w3tc_pull_zone['Name'] ); ?>
						(<?php echo esc_html( $w3tc_origin_url ); ?>)
					</option>
					<?php
					// If selected, then get the origin URL/IP and pull zone name.
					if ( $w3tc_is_selected ) {
						$w3tc_selected_origin_url       = $w3tc_origin_url;
						$w3tc_selected_name             = $w3tc_pull_zone['Name'];
						$w3tc_selected_custom_hostnames = implode( "\r\n", $w3tc_custom_hostnames );
					}
				}
			}

			// Determine origin URL and pull zone name for the fields below.
			$w3tc_field_origin_url = isset( $w3tc_selected_origin_url ) ? $w3tc_selected_origin_url : $details['suggested_origin_url'];
			$w3tc_field_name       = isset( $w3tc_selected_name ) ? $w3tc_selected_name : $details['suggested_zone_name'];
			?>
				</select>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Pull Zone Name', 'w3-total-cache' ); ?>:</td>
				<td>
					<input id="w3tc-pull-zone-name" name="name" type="text" class="w3tc-ignore-change"
						style="width: 550px" value="<?php echo esc_attr( $w3tc_field_name ); ?>"
						<?php echo ( empty( $details['pull_zone_id'] ) ? '' : 'readonly ' ); ?>
						data-suggested="<?php echo esc_attr( $details['suggested_zone_name'] ); ?>" />
					<p class="description">
						<?php esc_html_e( 'Name of the pull zone (letters, numbers, and dashes).  If empty, one will be automatically generated.', 'w3-total-cache' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Origin URL / IP', 'w3-total-cache' ); ?>:</td>
				<td>
					<input id="w3tc-origin-url" name="origin_url" type="text" class="w3tc-ignore-change"
						style="width: 550px" value="<?php echo esc_attr( $w3tc_field_origin_url ); ?>"
						<?php echo ( empty( $details['pull_zone_id'] ) ? '' : 'readonly ' ); ?>
						data-suggested="<?php echo esc_attr( $details['suggested_origin_url'] ); ?>" />
					<p class="description">
						<?php
						esc_html_e( 'Pull origin site URL or IP address.', 'w3-total-cache' );

						if ( ! empty( $server_ip ) ) {
							echo esc_html( ' ' . __( 'Detected server IP address', 'w3-total-cache' ) . ':' . $server_ip );
						}
						?>
					</p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<input type="button"
				class="w3tc_cdn_bunnycdn_configure_pull_zone w3tc-button-save button-primary"
				value="<?php esc_attr_e( 'Apply', 'w3-total-cache' ); ?>" />
		</p>
		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>
