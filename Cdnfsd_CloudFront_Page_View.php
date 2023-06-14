<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$key        = $config->get_string( 'cdnfsd.cloudfront.access_key' );
$authorized = ! empty( $key );

?>
		<?php Util_Ui::postbox_header( esc_html__( 'Configuration: Full-Site Delivery', 'w3-total-cache' ), '', 'configuration' ); ?>
		<table class="form-table">
			<tr>
				<th style="width: 300px;">
					<label>
						<?php esc_html_e( 'Specify account credentials:', 'w3-total-cache' ); ?>
					</label>
				</th>
				<td>
					<?php if ( $authorized ) : ?>
						<input class="w3tc_cdn_cloudfront_fsd_authorize button-primary"
							type="button" value="<?php esc_attr_e( 'Reauthorize', 'w3-total-cache' ); ?>" />
					<?php else : ?>
						<input class="w3tc_cdn_cloudfront_fsd_authorize button-primary"
							type="button" value="<?php esc_attr_e( 'Authorize', 'w3-total-cache' ); ?>" />
					<?php endif; ?>
				</td>
			</tr>

			<?php if ( $authorized ) : ?>
			<tr>
				<th>
					<label>
						<?php
						echo wp_kses(
							sprintf(
								// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag,
								// translators: 3 opening HTML acronym tag, 4 closing HTML acronym tag.
								__(
									'%1$sCDN%2$s %3$sCNAME%4$s:',
									'w3-total-cache'
								),
								'<acronym title="' . esc_attr__( 'Content Delivery Network', 'w3-total-cache' ) . '">',
								'</acronym>',
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
					<?php echo esc_html( $config->get_string( 'cdnfsd.cloudfront.distribution_domain' ) ); ?>
					<p class="description">
						The website domain has to be a CNAME pointing to this
						<acronym title="Content Delivery Network">CDN</acronym> domain
					</p>
				</td>
			</tr>
			<?php endif; ?>
		</table>

		<?php Util_Ui::postbox_footer(); ?>
