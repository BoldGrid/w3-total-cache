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

Util_Ui::postbox_header(
	esc_html__( 'Configuration: Full-Site Delivery', 'w3-total-cache' ),
	'',
	'configuration-fsd'
);
?>
<table class="form-table">
	<tr>
		<th style="width: 300px;">
			<label><?php esc_html_e( 'FSD Status:', 'w3-total-cache' ); ?></label>
		</th>
		<td>
			<div class="w3tc-fsd-status-actions">
				<button type="button" class="button button-secondary w3tc-fsd-status-run" data-default-text="<?php esc_attr_e( 'Check Status', 'w3-total-cache' ); ?>" data-testing-text="<?php esc_attr_e( 'Testing...', 'w3-total-cache' ); ?>" <?php disabled( ! $is_totalcdn_enabled ); ?>>
					<?php esc_html_e( 'Check Status', 'w3-total-cache' ); ?>
				</button>
				<span class="spinner"></span>
			</div>

			<p class="description">
				<?php esc_html_e( 'Run automated checks to confirm your Total CDN full-site delivery configuration. The status check verifies your pull zone hostname, DNS routing, SSL setup, and CDN headers.', 'w3-total-cache' ); ?>
			</p>

			<?php if ( ! $is_totalcdn_enabled ) : ?>
				<div class="notice notice-info">
					<p><?php esc_html_e( 'Enable Total CDN full-site delivery to run the status check.', 'w3-total-cache' ); ?></p>
				</div>
			<?php endif; ?>

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
</table>
<?php Util_Ui::postbox_footer(); ?>
