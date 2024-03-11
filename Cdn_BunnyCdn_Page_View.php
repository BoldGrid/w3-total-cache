<?php
/**
 * File: Cdn_BunnyCdn_Page_View.php
 *
 * Bunny CDN settings page section view.
 *
 * @since   2.6.0
 * @package W3TC
 *
 * @param array $config W3TC configuration.
 */

namespace W3TC;

defined( 'W3TC' ) || die();

$account_api_key = $config->get_string( 'cdn.bunnycdn.account_api_key' );
$is_authorized   = ! empty( $account_api_key ) && $config->get_string( 'cdn.bunnycdn.pull_zone_id' );
$is_unavailable  = ! empty( $account_api_key ) && $config->get_string( 'cdnfsd.bunnycdn.pull_zone_id' ); // CDN is unavailable if CDN FSD is authorized for Bunny CDN.

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
				<input class="w3tc_cdn_bunnycdn_deauthorization button-primary" type="button" value="<?php esc_attr_e( 'Deauthorize', 'w3-total-cache' ); ?>" />
			<?php else : ?>
				<input class="w3tc_cdn_bunnycdn_authorize button-primary" type="button" value="<?php esc_attr_e( 'Authorize', 'w3-total-cache' ); ?>"
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
			<?php echo esc_html( $config->get_string( 'cdn.bunnycdn.name' ) ); ?>
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
			<?php echo esc_html( $config->get_string( 'cdn.bunnycdn.origin_url' ) ); ?>
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
		<input id="w3tc_bunnycdn_hostname" type="text" name="cdn__bunnycdn__cdn_hostname"
			value="<?php echo esc_html( $config->get_string( 'cdn.bunnycdn.cdn_hostname' ) ); ?>" size="100" />
			<p class="description">
				<?php
				echo wp_kses(
					sprintf(
						// translators: 1: Opening HTML acronym tag, 2: Closing HTML acronym tag.
						esc_html__(
							'The %1$sCDN%2$s hostname is used in media links on pages.  For example: example.b-cdn.net',
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
			</p>
		</td>
	</tr>
	<?php endif; ?>
</table>
