<?php
/**
 * File: Cdnfsd_BunnyCdn_Popup_Pull_Zones.php
 *
 * Assists with configuring Bunny CDN as a full-site delivery CDN.
 * A pull zone selection is presented along with a form to add a new pull zone.
 *
 * @since   2.6.0
 * @package W3TC
 *
 * @param array $details {
 *     Bunny CDN API configuration details.
 *
 *     @type array  $pull_zones      Pull zones.
 *     @type string $error_message   Error message (optional).
 * }
 */

namespace W3TC;

defined( 'W3TC' ) || die();

?>
<form class="w3tc_cdn_bunnycdn_fsd_form" method="post">
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
				foreach ( $details['pull_zones'] as $pull_zone ) {
					// Skip pull zones that are disabled or suspended.
					if ( ! $pull_zone['Enabled'] || $pull_zone['Suspended'] ) {
						continue;
					}

					// Get the CDN hostname and custom hostnames.
					$cdn_hostname = '?';
					$custom_hostnames = array();

					foreach ( $pull_zone['Hostnames'] as $hostname ) {
						// Get the CDN hostname.  It should be the system hostname.
						if ( ! empty( $hostname['Value'] ) ) {
							if ( ! empty( $hostname['IsSystemHostname'] ) ) {
								// CDN hostname (system); there should only be one.
								$cdn_hostname = $hostname['Value'];
							} else {
								// Custom hostnames; 0 or more.
								$custom_hostnames[] = $hostname['Value'];
							}
						}
					}

					// Determine the origin URL/IP.
					$origin_url = empty( $pull_zone['OriginUrl'] ) ? $cdn_hostname : $pull_zone['OriginUrl'];

					// Determine if the current option is selected.
					$is_selected = isset( $details['pull_zone_id'] ) && $details['pull_zone_id'] === $pull_zone['Id'];

					// Print the select option.
					?>
					<option value="<?php echo esc_attr( $pull_zone['Id'] ); ?>"
						<?php echo $is_selected ? ' selected' : ''; ?>
						data-origin="<?php echo esc_html( $origin_url ); ?>"
						data-name="<?php echo esc_attr( $pull_zone['Name'] ); ?>"
						data-cdn-hostname="<?php echo esc_attr( $cdn_hostname ); ?>"
						data-custom-hostnames="<?php echo esc_attr( implode( ',', $custom_hostnames ) ); ?>">
						<?php echo esc_attr( $pull_zone['Name'] ); ?>
						(<?php echo esc_html( $origin_url ); ?>)
					</option>
					<?php
					// If selected, then get the origin URL/IP and pull zone name.
					if ( $is_selected ) {
						$selected_origin_url       = $origin_url;
						$selected_name             = $pull_zone['Name'];
						$selected_custom_hostnames = implode( "\r\n", $custom_hostnames );
					}
				}
			}

			// Determine origin URL and pull zone name for the fields below.
			$field_origin_url       = isset( $selected_origin_url ) ? $selected_origin_url : $details['suggested_origin_url'];
			$field_name             = isset( $selected_name ) ? $selected_name : $details['suggested_zone_name'];
			$field_custom_hostnames = isset( $selected_name ) ? $selected_name : $details['suggested_custom_hostname'];
			?>
				</select>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Pull Zone Name', 'w3-total-cache' ); ?>:</td>
				<td>
					<input id="w3tc-pull-zone-name" name="name" type="text" class="w3tc-ignore-change"
						style="width: 550px" value="<?php echo esc_attr( $field_name ); ?>"
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
						style="width: 550px" value="<?php echo esc_attr( $field_origin_url ); ?>"
						<?php echo ( empty( $details['pull_zone_id'] ) ? '' : 'readonly ' ); ?>
						data-suggested="<?php echo esc_attr( $details['suggested_origin_url'] ); ?>" />
					<p class="description">
						<?php esc_html_e( 'Pull origin site URL or IP address.', 'w3-total-cache' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Custom Hostnames', 'w3-total-cache' ); ?>:</td>
				<td>
					<input id="w3tc-custom-hostnames" name="custom_hostnames" type="text" class="w3tc-ignore-change"
						style="width: 550px" value="<?php echo esc_attr( $field_custom_hostnames ); ?>"
						<?php echo ( empty( $details['pull_zone_id'] ) ? '' : 'readonly ' ); ?>
						data-suggested="<?php echo esc_attr( $details['suggested_custom_hostname'] ); ?>" />
					<p class="description">
						<?php esc_html_e( 'Custom hostnames can be used instead of the default *.b-cdn.net hostname. After adding a hostname, create a CNAME record to the CDN hostname provided after the pull zone is configured.', 'w3-total-cache' ); ?>
						<br />
						<?php esc_html_e( '* Separate hostnames using commas (",").', 'w3-total-cache' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<input type="button"
				class="w3tc_cdn_bunnycdn_fsd_configure_pull_zone w3tc-button-save button-primary"
				value="<?php esc_attr_e( 'Apply', 'w3-total-cache' ); ?>" />
		</p>
		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>
