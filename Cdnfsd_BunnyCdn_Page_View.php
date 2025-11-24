<?php
/**
 * File: Cdnfsd_BunnyCdn_Page_View.php
 *
 * @since   2.6.0
 * @package W3TC
 *
 * @param array $config W3TC configuration.
 */

namespace W3TC;

defined( 'W3TC' ) || die;

$account_api_key = $config->get_string( 'cdn.bunnycdn.account_api_key' );
$is_authorized   = $account_api_key && $config->get_integer( 'cdnfsd.bunnycdn.pull_zone_id' );
$is_unavailable  = ! empty( $account_api_key ) && $config->get_string( 'cdn.bunnycdn.pull_zone_id' ); // CDN FSD is unavailable if CDN is authorized for Bunny CDN.

Util_Ui::postbox_header(
	esc_html__( 'Configuration: Full-Site Delivery', 'w3-total-cache' ),
	'',
	'configuration-fsd'
);

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
				<input class="w3tc_cdn_bunnycdn_fsd_deauthorization button-primary" type="button" value="<?php esc_attr_e( 'Deauthorize', 'w3-total-cache' ); ?>" />
				<p class="description">
					<?php
					esc_html_e(
						'Deauthorizing will disconnect your site from Bunny CDN full-site delivery and stop routing site requests through the CDN. Before deauthorizing or deleting the pull zone in your Bunny CDN account, ensure that your DNS records are reverted to point to your origin server to avoid site downtime.',
						'w3-total-cache'
					);
					?>
				</p>
			<?php else : ?>
				<input class="w3tc_cdn_bunnycdn_fsd_authorize button-primary" type="button" value="<?php esc_attr_e( 'Authorize', 'w3-total-cache' ); ?>" />
				<p class="description">
					<?php
					esc_html_e(
						'Authorize your site to use Bunny CDN for full site delivery.',
						'w3-total-cache'
					);
					?>
				</p>
			<?php endif; ?>
		</td>
	</tr>

	<?php if ( $is_authorized ) : ?>
	<tr>
		<th><label><?php esc_html_e( 'Pull zone name:', 'w3-total-cache' ); ?></label></th>
		<td class="w3tc_config_value_text">
			<?php echo esc_html( $config->get_string( 'cdnfsd.bunnycdn.name' ) ); ?>
			<p class="description">
				<?php
				esc_html_e(
					'This pull zone routes every request for your site through Bunny CDN\'s edge network.',
					'w3-total-cache'
				);
				?>
			</p>
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
			<?php echo esc_html( $config->get_string( 'cdnfsd.bunnycdn.origin_url' ) ); ?>
			<p class="description">
				<?php
				esc_html_e(
					'This is the origin server that Bunny CDN requests when it needs to pull fresh copies of your website\'s content.',
					'w3-total-cache'
				);
				?>
			</p>
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
			<?php echo esc_html( $config->get_string( 'cdnfsd.bunnycdn.cdn_hostname' ) ); ?>
			<p class="description">
				<?php
				echo wp_kses(
					sprintf(
						// translators: 1: Opening HTML acronym tag, 2: Opening HTML acronym tag, 3: Closing HTML acronym tag.
						esc_html__(
							'Point your domain\'s %1$sCNAME%3$s record to this %2$sCDN%3$s hostname so Bunny CDN can serve the entire site.',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Canonical Name', 'w3-total-cache' ) . '">',
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

<?php Util_Ui::postbox_footer(); ?>
