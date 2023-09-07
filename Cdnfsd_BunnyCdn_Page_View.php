<?php
/**
 * File: Cdnfsd_BunnyCdn_Page_View.php
 *
 * @since   X.X.X
 * @package W3TC
 *
 * @param array $config W3TC configuration.
 */

namespace W3TC;

defined( 'W3TC' ) || die;

$account_api_key = $config->get_string( 'cdn.bunnycdn.account_api_key' );
$is_authorized   = $config->get_boolean( 'cdn.bunnycdn.is_authorized' );

Util_Ui::postbox_header(
	esc_html__( 'Configuration: Full-Site Delivery', 'w3-total-cache' ),
	'',
	'configuration'
);

?>
<table class="form-table">
	<tr>
		<th style="width: 300px;">
			<label>
				<?php esc_html_e( 'Specify account API key', 'w3-total-cache' ); ?>:
			</label>
		</th>
		<td>
			<?php if ( $is_authorized ) : ?>
				<input class="w3tc_cdn_bunnycdn_fsd_authorize button-primary" type="button" value="<?php esc_attr_e( 'Reauthorize', 'w3-total-cache' ); ?>" />
			<?php else : ?>
				<input class="w3tc_cdn_bunnycdn_fsd_authorize button-primary" type="button" value="<?php esc_attr_e( 'Authorize', 'w3-total-cache' ); ?>" />
			<?php endif; ?>
		</td>
	</tr>

	<?php if ( $is_authorized ) : ?>
	<tr>
		<th><label><?php esc_html_e( 'Pull zone name:', 'w3-total-cache' ); ?></label></th>
		<td class="w3tc_config_value_text">
			<?php echo esc_html( $config->get_string( 'cdnfsd.bunnycdn.name' ) ); ?>
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
		</td>
	</tr>
	<tr>
		<th>
			<label>
				<?php
				echo wp_kses(
					sprintf(
						// translators: 1: Opening HTML acronym tag, 2: Opening HTML acronym tag, 4: Closing HTML acronym tag.
						esc_html__(
							'%1$sCDN%3$s %2$sCNAME%3$s:',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Content Delivery Network', 'w3-total-cache' ) . '">',
						'<acronym title="' . esc_attr__( 'Canonical Name', 'w3-total-cache' ) . '">',
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
				The website domain <acronym title="Canonical Name">CNAME</acronym> must point to the <acronym title="Content Delivery Network">CDN</acronym> domain.
			</p>
		</td>
	</tr>
	<?php endif; ?>
</table>

<?php Util_Ui::postbox_footer(); ?>
