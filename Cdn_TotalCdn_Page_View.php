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

$account_api_key = $config->get_string( 'cdn.totalcdn.account_api_key' );
$cdn_zone_id     = $config->get_integer( 'cdn.totalcdn.pull_zone_id' );
$cdnfsd_enabled  = $config->get_boolean( 'cdnfsd.enabled' );
$cdnfsd_engine   = $config->get_string( 'cdnfsd.engine' );
$is_authorized   = Cdn_TotalCdn_Util::is_totalcdn_authorized();
$is_fsd          = $is_authorized && Cdn_TotalCdn_Util::is_totalcdn_cdnfsd_enabled();
$custom_hostname = $config->get_string( 'cdn.totalcdn.custom_hostname' );
$ssl_cert_loaded = $config->get_string( 'cdn.totalcdn.custom_hostname_ssl_loaded' );

?>
<table class="form-table">
	<tr>
		<th style="width: 300px;">
			<label>
				<?php esc_html_e( 'Initial Configuration', 'w3-total-cache' ); ?>:
			</label>
		</th>
		<td>
			<?php if ( $is_authorized ) : ?>
				<input class="w3tc_cdn_totalcdn_deauthorization button-primary" type="button"
					value="<?php esc_attr_e( 'Deauthorize', 'w3-total-cache' ); ?>"
					<?php echo ( $is_fsd ? 'disabled' : '' ); ?> />
				<?php if ( $is_fsd ) : ?>
					<div class="notice notice-info">
						<p>
							<?php
							\printf(
								// Translators: %s: CDN name.
								esc_html__(
									'In order to deauthorize or delete this pull zone you must first point the DNS back to your origin and then disable %1$s Full Site Delivery (FSD). If the DNS is not updated before disabling FSD, your site may become unreachable.',
									'w3-total-cache'
								),
								esc_html( W3TC_CDN_NAME )
							);
							?>
						</p>
					</div>
				<?php endif; ?>
			<?php else : ?>
				<input class="w3tc_cdn_totalcdn_authorize button-primary" type="button"
					value="
					<?php
					if ( $account_api_key ) {
						echo esc_attr__( 'Authorize', 'w3-total-cache' );
					} else {
						// translators: %s: CDN name.
						printf( esc_attr__( 'Subscribe to %s', 'w3-total-cache' ), esc_html( W3TC_CDN_NAME ) );
					}
					?>
					"
					<?php echo ( $is_fsd_unavailable ? 'disabled' : '' ); ?> />
				<p class="description">
					<?php
					printf(
						// translators: %s: CDN name.
						esc_html__(
							'Authorize your site to use %s for static asset delivery.',
							'w3-total-cache'
						),
						esc_html( W3TC_CDN_NAME )
					);
					?>
				</p>
				<?php if ( $is_fsd_unavailable ) : ?>
					<div class="notice notice-info">
						<p>
							<?php esc_html_e( 'CDN for static assets cannot be authorized if full-site delivery is already configured.', 'w3-total-cache' ); ?>
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
			<?php echo esc_html( $config->get_string( 'cdn.totalcdn.name' ) ); ?>
			<p class="description">
				<?php
				esc_html_e(
					'The pull zone identifies the Total CDN endpoint serving your files. Each pull zone has its own caching rules and hostname.',
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
			<?php echo esc_html( $config->get_string( 'cdn.totalcdn.origin_url' ) ); ?>
			<p class="description">
				<?php
				printf(
					// translators: %s: CDN name.
					esc_html__(
						'This is the origin server that %1$s requests when it needs to pull fresh copies of your assets.',
						'w3-total-cache'
					),
					esc_html( W3TC_CDN_NAME )
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
			<?php echo esc_html( $config->get_string( 'cdn.totalcdn.cdn_hostname' ) ); ?>
			<p class="description">
				<?php
				esc_html_e(
					'Assets requested by visitors will use this Total CDN hostname unless you map a custom domain to it.',
					'w3-total-cache'
				);
				?>
			</p>
		</td>
	</tr>
	<tr>
		<th>
			<label>
				<?php esc_html_e( 'Custom Hostname:', 'w3-total-cache' ); ?>
			</label>
		</th>
		<td class="w3tc_config_value_text">
			<?php
			if ( ! empty( $custom_hostname ) ) {
				echo esc_html( $custom_hostname );
				?>
				<br />
				<?php
				if ( ! $ssl_cert_loaded ) {
					// If the SSL certificate is not loaded, show the button to load it.
					?>
					<br />
					<input class="w3tc_cdn_totalcdn_load_free_ssl button-primary"
						type="button"
						value="<?php esc_attr_e( 'Load SSL Certificate', 'w3-total-cache' ); ?>"
					/>
					<?php
				} else {
					// Show a notice that the custom hostname and ssl are properly configured.
					?>
					<br />
					<p class="notice notice-success">
						<?php esc_html_e( 'Custom hostname and SSL certificate are properly configured.', 'w3-total-cache' ); ?>
					</p>
					<?php
				}
				?>
				<input class="w3tc_cdn_totalcdn_remove_custom_hostname button"
					type="button"
					value="<?php esc_attr_e( 'Remove Custom Hostname', 'w3-total-cache' ); ?>"
				/>
				<p class="description">
					<?php
					esc_html_e(
						'After creating the DNS CNAME, load a free SSL certificate so HTTPS requests to your custom hostname remain secure.',
						'w3-total-cache'
					);
					?>
				</p>
				<?php
			} else {
				?>
				<input class="w3tc_cdn_totalcdn_add_custom_hostname button-primary" type="button"
					value="<?php esc_attr_e( 'Add Custom Hostname', 'w3-total-cache' ); ?>"
				/>
				<?php
			}
			?>
			<p class="description">
				<?php
				printf(
					// translators: %s: CDN name.
					esc_html__(
						'A custom hostname allows you to use a different domain for static asset delivery instead of the default %1$s hostname.',
						'w3-total-cache'
					),
					esc_html( W3TC_CDN_NAME )
				);
				?>
			</p>
		</td>
	</tr>
	<?php endif; ?>
</table>
