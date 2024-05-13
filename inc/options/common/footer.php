<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$config = Dispatcher::config();

do_action( 'w3tc-dashboard-footer' );
?>
</div>
<div id="w3tc-footer">
	<div id="w3tc-footer-container">
		<div class="w3tc-footer-column-1">
			<a class="logo-link" href="<?php echo esc_url( Util_UI::admin_url( 'admin.php?page=w3tc_dashboard' ) ); ?>" alt="W3 Total Cache">
				<h2 class="logo">
					<?php
					echo wp_kses(
						sprintf(
							// translators: 1 opening HTML span tag, 2 opening HTML sup tag, 3 closing HTML sup tag, 4 closing HTML span tag.
							__(
								'W3 Total Cache %1$sby W3 EDGE %2$s&reg;%3$s%4$s',
								'w3-total-cache'
							),
							'<span>',
							'<sup>',
							'</sup>',
							'</span>'
						),
						array(
							'span' => array(),
							'sup'  => array(),
						)
					);
					?>
				</h2>
			</a>
			<?php
			if ( ! Util_Environment::is_w3tc_pro( $config ) ) {
				echo '<input type="button" class="button button-buy-plugin {nonce: \'' . esc_attr( wp_create_nonce( 'w3tc' ) ) . '\'}"
					data-src="footer" value="' . esc_html__( 'Learn more about Pro!', 'w3-total-cache' ) . '" />';
			}
			?>
		</div>
		<div class="w3tc-footer-column-1">
			<h2><?php esc_html_e( 'Documentation', 'w3-total-cache' ); ?></h2>
			<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( 'https://www.boldgrid.com/support/w3-total-cache/' ); ?>" alt="<?php esc_attr_e( 'Support Center', 'w3-total-cache' ); ?>">
				<?php esc_html_e( 'Support Center', 'w3-total-cache' ); ?>
			</a>
			<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( 'https://github.com/BoldGrid/w3-total-cache' ); ?>" alt="<?php esc_attr_e( 'GitHub', 'w3-total-cache' ); ?>">
				<?php esc_html_e( 'GitHub', 'w3-total-cache' ); ?>
			</a>
			<h2><?php esc_html_e( 'Support', 'w3-total-cache' ); ?></h2>
			<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( 'https://www.boldgrid.com/support/forum/w3-total-cache/' ); ?>" alt="<?php esc_attr_e( 'Forums', 'w3-total-cache' ); ?>">
				<?php esc_html_e( 'Forums', 'w3-total-cache' ); ?>
			</a>
			<a class="w3tc-footer-link" href="<?php echo esc_url( Util_UI::admin_url( 'admin.php?page=w3tc_support' ) ); ?>" alt="<?php esc_attr_e( 'Premium Support Services', 'w3-total-cache' ); ?>">
				<?php esc_html_e( 'Premium Support Services', 'w3-total-cache' ); ?>
			</a>
		</div>
		<div class="w3tc-footer-column-2">
			<h2><?php esc_html_e( 'Pro Features', 'w3-total-cache' ); ?></h2>
			<div class="w3tc-footer-inner-column-50">
				<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( 'https://www.boldgrid.com/support/w3-total-cache/cdn/full-site-delivery/' ); ?>" alt="<?php esc_attr_e( 'Full Site Delivery', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'Full Site Delivery', 'w3-total-cache' ); ?>
				</a>
				<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( 'https://www.boldgrid.com/support/w3-total-cache/what-is-fragment-caching-and-why-do-i-need-it/' ); ?>" alt="<?php esc_attr_e( 'Fragment Cache', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'Fragment Cache', 'w3-total-cache' ); ?>
				</a>
				<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( 'https://www.boldgrid.com/support/w3-total-cache/page-cache/rest-api/' ); ?>" alt="<?php esc_attr_e( 'Rest API Caching', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'Rest API Caching', 'w3-total-cache' ); ?>
				</a>
				<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( 'https://www.boldgrid.com/support/w3-total-cache/minify/render-blocking-css/' ); ?>" alt="<?php esc_attr_e( 'Eliminate Render Blocking CSS', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'Eliminate Render Blocking CSS', 'w3-total-cache' ); ?>
				</a>
				<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( 'https://www.boldgrid.com/support/w3-total-cache/delay-scripts-tool/' ); ?>" alt="<?php esc_attr_e( 'Delay Scripts', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'Delay Scripts', 'w3-total-cache' ); ?>
				</a>
				<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( 'https://www.boldgrid.com/support/w3-total-cache/preload-requests/' ); ?>" alt="<?php esc_attr_e( 'Preload Requests', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'Preload Requests', 'w3-total-cache' ); ?>
				</a>
			</div>
			<div class="w3tc-footer-inner-column-50">
				<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( 'https://www.boldgrid.com/support/w3-total-cache/remove-cssjs/' ); ?>" alt="<?php esc_attr_e( 'Remove CSS/JS', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'Remove CSS/JS', 'w3-total-cache' ); ?>
				</a>
				<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( 'https://www.boldgrid.com/support/w3-total-cache/configuring-lazy-loading-for-your-wordpress-website-with-w3-total-cache/' ); ?>" alt="<?php esc_attr_e( 'Lazy Load Google Maps', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'Lazy Load Google Maps', 'w3-total-cache' ); ?>
				</a>
				<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( 'https://www.boldgrid.com/support/w3-total-cache/wpml/' ); ?>" alt="<?php esc_attr_e( 'WPML Extension', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'WPML Extension', 'w3-total-cache' ); ?>
				</a>
				<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( 'https://www.boldgrid.com/support/w3-total-cache/configuring-w3-total-cache-statistics-to-give-detailed-information-about-your-cache/' ); ?>" alt="<?php esc_attr_e( 'Caching Statistics', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'Caching Statistics', 'w3-total-cache' ); ?>
				</a>
				<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( 'https://www.boldgrid.com/support/w3-total-cache/purge-cache-log/' ); ?>" alt="<?php esc_attr_e( 'Purge Logs', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'Purge Logs', 'w3-total-cache' ); ?>
				</a>
			</div>
		</div>
		<div class="w3tc-footer-column-1">
			<h2><?php esc_html_e( 'Follow Us', 'w3-total-cache' ); ?></h2>
			<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( 'https://twitter.com/w3edge' ); ?>" alt="<?php esc_attr_e( 'W3 Edge', 'w3-total-cache' ); ?>">
				<span class="dashicons dashicons-twitter"></span><?php esc_html_e( 'W3 Edge', 'w3-total-cache' ); ?>
			</a>
			<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( 'https://twitter.com/boldgrid' ); ?>" alt="<?php esc_attr_e( 'BoldGrid', 'w3-total-cache' ); ?>">
				<span class="dashicons dashicons-twitter"></span><?php esc_html_e( 'BoldGrid', 'w3-total-cache' ); ?>
			</a>
			<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( 'https://www.facebook.com/boldgrid/' ); ?>" alt="<?php esc_attr_e( 'BoldGrid', 'w3-total-cache' ); ?>">
				<span class="dashicons dashicons-facebook"></span><?php esc_html_e( 'BoldGrid', 'w3-total-cache' ); ?>
			</a>
		</div>
		<div class="w3tc-footer-column-1">
			<h2><?php esc_html_e( 'Partners', 'w3-total-cache' ); ?></h2>
			<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( W3TC_BUNNYCDN_SIGNUP_URL ); ?>" alt="Bunny CDN">
				<div class="w3tc-bunnycdn-logo"></div>
			</a>
		</div>
	</div>
</div>
