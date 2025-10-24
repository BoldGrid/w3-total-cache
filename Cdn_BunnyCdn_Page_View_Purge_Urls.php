<?php
/**
 * File: Cdn_BunnyCdn_Page_View_Purge_Urls.php
 *
 * Bunny CDN settings purge URLs view.
 *
 * @since   2.6.0
 * @package W3TC
 *
 * @param array $config W3TC configuration.
 */

namespace W3TC;

defined( 'W3TC' ) || die;

$account_api_key = $config->get_string( 'cdn.bunnycdn.account_api_key' );
$is_authorized   = ! empty( $account_api_key ) &&
	( $config->get_string( 'cdn.bunnycdn.pull_zone_id' ) || $config->get_string( 'cdnfsd.bunnycdn.pull_zone_id' ) );
$placeholder     = \esc_url( \home_url() . '/about-us' ) . "\r\n" . \esc_url( \home_url() . '/css/*' );

?>
<table class="form-table">
	<tr>
		<th style="width: 300px;">
			<label>
				<?php \esc_html_e( 'Purge URLs', 'w3-total-cache' ); ?>:
			</label>
		</th>
		<td>
				<textarea id="w3tc-purge-urls" class="w3tc-ignore-change" cols="60" rows="5"
					placeholder="<?php echo \esc_html( $placeholder ); ?>" <?php echo ( $is_authorized ? '' : 'disabled' ); ?>></textarea>
				<p><?php \esc_html_e( 'Purging a URL will remove the file from the CDN cache and re-download it from your origin server. Please enter the exact CDN URL of each individual file. You can also purge folders or wildcard files using * inside of the URL path. Wildcard values are not supported if using Perma-Cache.', 'w3-total-cache' ); ?></p>
				<p>
					<input class="w3tc_cdn_bunnycdn_purge_urls button-primary" type="button"
						value="<?php \esc_attr_e( 'Purge URLs Now', 'w3-total-cache' ); ?>"
						<?php echo ( $is_authorized ? '' : 'disabled' ); ?>/>
				</p>
			<?php
			if ( ! $is_authorized ) :
				echo wp_kses(
					\sprintf(
						// translators: 1: Opening HTML elements, 2: Name of the CDN service, 3: Closing HTML elements.
						\esc_html__( '%1$sPlease configure %2$s in order to purge URLs.%3$s', 'w3-total-cache' ),
						'<div class="notice notice-info"><p>',
						'Bunny CDN',
						'</p></div>'
					),
					array(
						'div' => array(
							'class' => array(),
						),
						'p'   => array(),
					)
				);
				else :
					?>
				<br />
				<p><div id="w3tc-purge-messages"></div></p>
			<?php endif; ?>
		</td>
	</tr>
</table>
