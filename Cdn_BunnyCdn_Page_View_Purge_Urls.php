<?php
/**
 * File: Cdn_BunnyCdn_Page_View_Purge_Urls.php
 *
 * BunnyCDN settings purge URLs view.
 *
 * @since   X.X.X
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
				<textarea id="w3tc-purge-urls" class="w3tc-ignore-change" cols="60" rows="5" placeholder="<?php echo \esc_html( $placeholder ); ?> "></textarea>
				<p><?php \esc_html_e( 'Purging a URL will remove the file from the CDN cache and re-download it from your origin server. Please enter the exact CDN URL of each individual file. You can also purge folders or wildcard files using * inside of the URL path. Wildcard values are not supported if using Perma-Cache.', 'w3-total-cache' ); ?></p>
				<p>
					<input class="w3tc_cdn_bunnycdn_purge_urls button-primary" type="button"
						value="<?php \esc_attr_e( 'Purge URLs Now', 'w3-total-cache' ); ?>"
						<?php echo ( $is_authorized ? '' : 'disabled' ); ?>/>
				</p>
			<?php if ( ! $is_authorized ) : ?>
			<p>
				<?php
				\printf(
					// translators: 1: Name of the CDN service.
					\esc_html__( 'Please configure %1$s in order to purge URLs.', 'w3-total-cache' ),
					'Bunny CDN'
				);
				?>
			</p>
			<?php else : ?>
				<br />
				<p><div id="w3tc-purge-messages"></div></p>
			<?php endif; ?>
		</td>
	</tr>
</table>
