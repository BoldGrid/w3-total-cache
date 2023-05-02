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
		</div>
		<div class="w3tc-footer-column-1">
			<h2><?php esc_html_e( 'Documentation', 'w3-total-cache' ); ?></h2>
			<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( 'https://www.boldgrid.com/support/w3-total-cache/' ); ?>" alt="<?php esc_attr_e( 'Support Center', 'w3-total-cache' ); ?>">
				<?php esc_html_e( 'Support Center', 'w3-total-cache' ); ?>
			</a>
			<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( 'https://github.com/BoldGrid/w3-total-cache' ); ?>" alt="<?php esc_attr_e( 'GitHub', 'w3-total-cache' ); ?>">
				<?php esc_html_e( 'GitHub', 'w3-total-cache' ); ?>
			</a>
		</div>
		<div class="w3tc-footer-column-1">
			<h2><?php esc_html_e( 'Support', 'w3-total-cache' ); ?></h2>
			<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( 'https://www.boldgrid.com/support/forum/w3-total-cache/' ); ?>" alt="<?php esc_attr_e( 'Forums', 'w3-total-cache' ); ?>">
				<?php esc_html_e( 'Forums', 'w3-total-cache' ); ?>
			</a>
			<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( 'https://www.boldgrid.com/central/login' ); ?>" alt="<?php esc_attr_e( 'Premium Support Services', 'w3-total-cache' ); ?>">
				<?php esc_html_e( 'Premium Support Services', 'w3-total-cache' ); ?>
			</a>
		</div>
		<div class="w3tc-footer-column-2">
			<h2><?php esc_html_e( 'Pro Features', 'w3-total-cache' ); ?></h2>
			<div class="w3tc-footer-inner-column-50">
				<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( Util_UI::admin_url( 'admin.php?page=w3tc_general#cdn' ) ); ?>" alt="<?php esc_attr_e( 'Full Site Delivery', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'Full Site Delivery', 'w3-total-cache' ); ?>
				</a>
				<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( ( $config->is_extension_active( 'fragmentcache' ) ) ? Util_UI::admin_url( 'admin.php?page=w3tc_fragmentcache' ) : Util_UI::admin_url( 'admin.php?page=w3tc_extensions#fragmentcache' ) ); ?>" alt="<?php esc_attr_e( 'Fragment Cache', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'Fragment Cache', 'w3-total-cache' ); ?>
				</a>
				<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( Util_UI::admin_url( 'admin.php?page=w3tc_pgcache#rest' ) ); ?>" alt="<?php esc_attr_e( 'Rest API Caching', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'Rest API Caching', 'w3-total-cache' ); ?>
				</a>
				<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( Util_UI::admin_url( 'admin.php?page=w3tc_minify#css' ) ); ?>" alt="<?php esc_attr_e( 'Eliminate Render Blocking CSS', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'Eliminate Render Blocking CSS', 'w3-total-cache' ); ?>
				</a>
			</div>
			<div class="w3tc-footer-inner-column-50">
				<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( ( $config->is_extension_active( 'genesis.theme' ) ) ? Util_UI::admin_url( 'admin.php?page=w3tc_extensions&extension=genesis.theme&action=view' ) : Util_UI::admin_url( 'admin.php?page=w3tc_extensions#genesis.theme' ) ); ?>" alt="<?php esc_attr_e( 'Genesis Framework Acceleration', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'Genesis Framework Acceleration', 'w3-total-cache' ); ?>
				</a>
				<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( Util_UI::admin_url( 'admin.php?page=w3tc_extensions#wpml' ) ); ?>" alt="<?php esc_attr_e( 'WPML Extension', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'WPML Extension', 'w3-total-cache' ); ?>
				</a>
				<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( Util_UI::admin_url( 'admin.php?page=w3tc_general#stats' ) ); ?>" alt="<?php esc_attr_e( 'Caching Statistics', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'Caching Statistics', 'w3-total-cache' ); ?>
				</a>
				<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( Util_UI::admin_url( 'admin.php?page=w3tc_general#debug' ) ); ?>" alt="<?php esc_attr_e( 'Purge Logs', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'Purge Logs', 'w3-total-cache' ); ?>
				</a>
			</div>
			<?php
			if( ! Util_Environment::is_w3tc_pro( $config ) ) {
				echo '<input type="button" class="button w3tc-gopro-button button-buy-plugin" data-src="dashboard_banner" value="' . esc_attr__( 'Learn more about Pro!', 'w3-total-cache' ) . '" />';
			}
			?>
		</div>
		<div class="w3tc-footer-column-1">
			<h2><?php esc_html_e( 'Follow Us', 'w3-total-cache' ); ?></h2>
			<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( 'https://twitter.com/boldgrid' ); ?>" alt="<?php esc_attr_e( 'W3 Edge', 'w3-total-cache' ); ?>">
				<span class="dashicons dashicons-twitter"></span><?php esc_html_e( 'W3 Edge', 'w3-total-cache' ); ?>
			</a>
			<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( 'https://twitter.com/w3edge' ); ?>" alt="<?php esc_attr_e( 'BoldGrid', 'w3-total-cache' ); ?>">
				<span class="dashicons dashicons-twitter"></span><?php esc_html_e( 'BoldGrid', 'w3-total-cache' ); ?>
			</a>
			<a class="w3tc-footer-link" target="_blank" href="<?php echo esc_url( 'https://www.facebook.com/boldgrid/' ); ?>" alt="<?php esc_attr_e( 'BoldGrid', 'w3-total-cache' ); ?>">
				<span class="dashicons dashicons-facebook"></span><?php esc_html_e( 'BoldGrid', 'w3-total-cache' ); ?>
			</a>
		</div>
	</div>
</div>
