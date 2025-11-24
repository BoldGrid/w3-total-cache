<?php
/**
 * File: Cdnfsd_TotalCdn_Page_View.php
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'W3TC' ) || die;

$account_api_key = $config->get_string( 'cdn.totalcdn.account_api_key' );
$is_enabled      = Cdn_TotalCdn_Util::is_totalcdn_cdnfsd_enabled();
$is_authorized   = Cdn_TotalCdn_Util::is_totalcdn_authorized();

Util_Ui::postbox_header(
	esc_html__( 'Configuration: Full-Site Delivery', 'w3-total-cache' ),
	'',
	'configuration-fsd'
);
?>
<table class="form-table">
	<?php
	if ( $is_authorized ) {
		?>
		<tr>
			<th><label><?php esc_html_e( 'Pull zone name:', 'w3-total-cache' ); ?></label></th>
			<td class="w3tc_config_value_text">
				<?php echo esc_html( $config->get_string( 'cdn.totalcdn.name' ) ); ?>
				<p class="description">
					<?php
					printf(
						// translators: %s: CDN name.
						esc_html__(
							'This pull zone routes every request for your site through %1$s\'s edge network.',
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
							'This is the origin server that %1$s requests when it needs to pull fresh copies of your site\'s content.',
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
					echo wp_kses(
						sprintf(
							// translators: 1: Opening HTML acronym tag, 2: Opening HTML acronym tag, 3: Closing HTML acronym tag.
							esc_html__(
								'Point your domain\'s %1$sCNAME%3$s record to this %2$sCDN%3$s hostname so visitors route through Total CDN.',
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
		<?php
	}

	if ( $is_enabled && $is_authorized ) {
		?>
		<tr>
			<th style="width: 300px;">
				<label><?php esc_html_e( 'FSD Status:', 'w3-total-cache' ); ?></label>
			</th>
			<td>
				<div class="w3tc-fsd-status-actions">
					<button type="button" class="button button-secondary w3tc-fsd-status-run" data-default-text="<?php esc_attr_e( 'Check Status', 'w3-total-cache' ); ?>" data-testing-text="<?php esc_attr_e( 'Testing...', 'w3-total-cache' ); ?>">
						<?php esc_html_e( 'Check Status', 'w3-total-cache' ); ?>
					</button>
					<span class="spinner"></span>
				</div>

				<p class="description">
					<?php esc_html_e( 'Run automated checks to confirm your Total CDN full-site delivery configuration. The status check verifies your pull zone hostname, DNS routing, SSL setup, and CDN headers.', 'w3-total-cache' ); ?>
				</p>

				<div class="w3tc-fsd-status-tests">
					<ul class="w3tc-fsd-status-list">
						<?php foreach ( $tests as $test ) : ?>
							<li class="w3tc-fsd-status-item w3tc-fsd-status-untested" data-test-id="<?php echo esc_attr( $test['id'] ); ?>">
								<span class="w3tc-fsd-status-indicator" aria-hidden="true">
									<span class="w3tc-fsd-status-symbol dashicons dashicons-minus"></span>
								</span>
								<span class="w3tc-fsd-status-title"><?php echo esc_html( $test['title'] ); ?></span>
								<span class="screen-reader-text">
									<?php
									printf(
										// Translators: 1 test title, 2 test status.
										esc_html__( '%1$s status: %2$s', 'w3-total-cache' ),
										esc_html( $test['title'] ),
										esc_html__( 'Not run', 'w3-total-cache' )
									);
									?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>

				<div class="w3tc-fsd-status-notices" aria-live="polite"></div>
			</td>
		</tr>
		<?php
	}
	?>
</table>
<?php Util_Ui::postbox_footer(); ?>
