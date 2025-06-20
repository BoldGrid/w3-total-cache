<?php
/**
 * File: Cdn_TotalCdn_Page_View.php
 *
 * Total CDN settings page section view.
 *
 * @since   2.6.0
 * @package W3TC
 *
 * @param array $config W3TC configuration.
 */

namespace W3TC;

defined( 'W3TC' ) || die();

$account_api_key = $config->get_string( 'cdn.' . W3TC_CDN_SLUG . '.account_api_key' );
$is_authorized   = ! empty( $account_api_key ) && $config->get_string( 'cdn.' . W3TC_CDN_SLUG . '.pull_zone_id' );
$is_unavailable  = ! empty( $account_api_key ) && $config->get_string( 'cdnfsd.' . W3TC_CDN_SLUG . '.pull_zone_id' ); // CDN is unavailable if CDN FSD is authorized for Total CDN.

$custom_hostname = $config->get_string( 'cdn.' . W3TC_CDN_SLUG . '.custom_hostname' );
$ssl_cert_loaded = $config->get_string( 'cdn.' . W3TC_CDN_SLUG . '.custom_hostname_ssl_loaded' );

?>
<table class="form-table">
	<tr>
		<th style="width: 300px;">
			<label>
				<?php esc_html_e( 'Account API key authorization', 'w3-total-cache' ); ?>:
			</label>
		</th>
		<td>
			<?php if ( $is_authorized ) : ?>
				<input class="w3tc_cdn_<?php esc_attr( W3TC_CDN_SLUG ); ?>_deauthorization button-primary" type="button" value="<?php esc_attr_e( 'Deauthorize', 'w3-total-cache' ); ?>" />
			<?php else : ?>
				<input class="w3tc_cdn_<?php esc_attr( W3TC_CDN_SLUG ); ?>_authorize button-primary" type="button" value="<?php esc_attr_e( 'Authorize', 'w3-total-cache' ); ?>"
				<?php echo ( $is_unavailable ? 'disabled' : '' ); ?> />
				<?php if ( $is_unavailable ) : ?>
					<div class="notice notice-info">
						<p>
							<?php esc_html_e( 'CDN for objects cannot be authorized if full-site delivery is already configured.', 'w3-total-cache' ); ?>
						</p>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</td>
	</tr>

	<?php if ( $is_authorized ) : ?>
	<tr>
		<th><label><?php esc_html_e( 'Pull zone name:', 'w3-total-cache' ); ?></label></th>
		<td class="w3tc_config_value_text">
			<?php echo esc_html( $config->get_string( 'cdn.' . W3TC_CDN_SLUG . '.name' ) ); ?>
		</td>
	</tr>
	<tr>
		<th>
			<label>
				<?php
				echo wp_kses(
					sprintf(
						// translators: 1: Opening HTML acronym tag, 2: Opening HTML acronym tag, 3: Closing HTML acronym tag.
						esc_html__(
							'Origin %1$sURL%3$s/%2$sIP%3$s address:',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Universal Resource Locator', 'w3-total-cache' ) . '">',
						'<acronym title="' . esc_attr__( 'Internet Protocol', 'w3-total-cache' ) . '">',
						'</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				);
				?>
			</label>
		</th>
		<td class="w3tc_config_value_text">
			<?php echo esc_html( $config->get_string( 'cdn.' . W3TC_CDN_SLUG . '.origin_url' ) ); ?>
		</td>
	</tr>
	<tr>
		<th>
			<label>
				<?php
				echo wp_kses(
					sprintf(
						// translators: 1: Opening HTML acronym tag, 2: Closing HTML acronym tag.
						esc_html__(
							'%1$sCDN%2$s hostname:',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Content Delivery Network', 'w3-total-cache' ) . '">',
						'</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				);
				?>
			</label>
		</th>
		<td class="w3tc_config_value_text">
			<p class="description">
			<?php echo esc_html( $config->get_string( 'cdn.' . W3TC_CDN_SLUG . '.cdn_hostname' ) ); ?>
			</p>
		</td>
	</tr>
	<tr>
		<th>
			<label>
				<?php
				echo esc_html__(
					'Custom Hostname: ',
					'w3-total-cache'
				);
				echo esc_html( $custom_hostname );
				?>
			</label>
		</th>
		<td class="w3tc_config_value_text">
			<?php if ( empty( $custom_hostname ) ) { ?>
				<input class="w3tc_cdn_<?php esc_attr( W3TC_CDN_SLUG ); ?>_add_custom_hostname button-primary"
					type="button"
					value="<?php esc_attr_e( 'Add Custom Hostname', 'w3-total-cache' ); ?>"
				/>
				<?php
			} elseif ( ! $ssl_cert_loaded ) {
				// If the SSL certificate is not loaded, show the button to load it.
				?>
				<input class="w3tc_cdn_<?php esc_attr( W3TC_CDN_SLUG ); ?>_load_free_ssl button-primary"
					type="button"
					value="<?php esc_attr_e( 'Load SSL Certificate', 'w3-total-cache' ); ?>"
				/>
				<?php
			} elseif ( $ssl_cert_loaded && ! empty( $custom_hostname ) ) {
				// Show a notice that the custom hostname and ssl are properly configured.
				?>
				<p class="notice notice-success">
					<?php esc_html_e( 'Custom hostname and SSL certificate are properly configured.', 'w3-total-cache' ); ?>
				</p>
				<?php } ?>
		</td>
	</tr>
	<?php endif; ?>
</table>
