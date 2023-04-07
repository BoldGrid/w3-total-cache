<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$config = Dispatcher::config();

$settings_menu_array = array(
	Util_UI::admin_url( 'admin.php?page=w3tc_general' )        => esc_attr__( 'General Settings', 'w3-total-cache' ),
	Util_UI::admin_url( 'admin.php?page=w3tc_pgcache' )        => esc_attr__( 'Page Cache', 'w3-total-cache' ),
	Util_UI::admin_url( 'admin.php?page=w3tc_minify' )         => esc_attr__( 'Minify', 'w3-total-cache' ),
	Util_UI::admin_url( 'admin.php?page=w3tc_dbcache' )        => esc_attr__( 'Database Cache', 'w3-total-cache' ),
	Util_UI::admin_url( 'admin.php?page=w3tc_objectcache' )    => esc_attr__( 'Object Cache', 'w3-total-cache' ),
	Util_UI::admin_url( 'admin.php?page=w3tc_browsercache' )   => esc_attr__( 'Browser Cache', 'w3-total-cache' ),
	Util_UI::admin_url( 'admin.php?page=w3tc_cachegroups' )    => esc_attr__( 'Cache Groups', 'w3-total-cache' ),
	Util_UI::admin_url( 'admin.php?page=w3tc_cdn' )            => 'CDN',
	Util_UI::admin_url( 'admin.php?page=w3tc_fragmentcache' )  => esc_attr__( 'Fragment Cache', 'w3-total-cache' ),
	Util_UI::admin_url( 'admin.php?page=w3tc_userexperience' ) => esc_attr__( 'User Experience', 'w3-total-cache' ),
);

$tools_menu_array = array(
	Util_UI::admin_url( 'admin.php?page=w3tc_extensions' )  => esc_attr__( 'Extensions', 'w3-total-cache' ),
	Util_UI::admin_url( 'admin.php?page=w3tc_stats' )       => esc_attr__( 'Statistics', 'w3-total-cache' ),
	Util_UI::admin_url( 'admin.php?page=w3tc_pagespeed' )   => esc_attr__( 'Google PageSpeed', 'w3-total-cache' ),
	Util_UI::admin_url( 'admin.php?page=w3tc_setup_guide' ) => esc_attr__( 'Setup Guide', 'w3-total-cache' ),
);

$about_menu_array = array(
	Util_UI::admin_url( 'admin.php?page=w3tc_feature_showcase' ) => esc_attr__( 'Feature Showcase', 'w3-total-cache' ),
	Util_UI::admin_url( 'admin.php?page=w3tc_install' )          => esc_attr__( 'Install', 'w3-total-cache' ),
	'https://api.w3-edge.com/v1/redirects/faq'                   => 'FAQ',
);

do_action( 'w3tc-dashboard-top-nav-bar' );
?>
<div id="w3tc-top-nav-bar">
	<div id="w3tc-top-nav-bar-content">
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
		<div id="w3tc-top-nav-bar-content-links">
			<a class="w3tc-top-nav-support" href="<?php echo Util_UI::admin_url( 'admin.php?page=w3tc_dashboard' ); ?>" alt="<?php esc_attr_e( 'Dashboard', 'w3-total-cache' ); ?>">
				<?php esc_html_e( 'Dashboard', 'w3-total-cache' ); ?>
			</a>
			<div class="w3tc-top-nav-dropdown">
				<a class="w3tc-top-nav-settings" href="<?php echo Util_UI::admin_url( 'admin.php?page=w3tc_general' ); ?>" alt="<?php esc_attr_e( 'Settings', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'Settings', 'w3-total-cache' ); ?><span class="dashicons dashicons-arrow-down-alt2"></span>
				</a>
				<div id="w3tc-top-nav-settings-menu" class="w3tc-top-nav-dropdown-content">
					<?php
					foreach ( $settings_menu_array as $url => $label ) {
						echo '<a href="' . $url . '" alt="' . $label . '">' . $label . '</a>';
					}
					?>
				</div>
			</div>
			<div class="w3tc-top-nav-dropdown">
				<a class="w3tc-top-nav-tools" href="<?php echo Util_UI::admin_url( 'admin.php?page=w3tc_extensions' ); ?>" alt="<?php esc_attr_e( 'Tools', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'Tools', 'w3-total-cache' ); ?><span class="dashicons dashicons-arrow-down-alt2"></span>
				</a>
				<div id="w3tc-top-nav-tools-menu" class="w3tc-top-nav-dropdown-content">
					<?php
					foreach ( $tools_menu_array as $url => $label ) {
						echo '<a href="' . $url . '" alt="' . $label . '">' . $label . '</a>';
					}
					?>
				</div>
			</div>
			<div class="w3tc-top-nav-dropdown">
				<a class="w3tc-top-nav-about" href="<?php echo Util_UI::admin_url( 'admin.php?page=w3tc_about' ); ?>" alt="<?php esc_attr_e( 'About', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'About', 'w3-total-cache' ); ?><span class="dashicons dashicons-arrow-down-alt2"></span>
				</a>
				<div id="w3tc-top-nav-about-menu" class="w3tc-top-nav-dropdown-content">
					<?php
					foreach ( $about_menu_array as $url => $label ) {
						echo '<a href="' . $url . '" alt="' . $label . '">' . $label . '</a>';
					}
					?>
				</div>
			</div>
			<a class="w3tc-top-nav-support" href="<?php echo Util_UI::admin_url( 'admin.php?page=w3tc_support' ); ?>" alt="<?php esc_attr_e( 'Support', 'w3-total-cache' ); ?>">
				<?php esc_html_e( 'Support', 'w3-total-cache' ); ?>
			</a>
			<?php
			if( ! Util_Environment::is_w3tc_pro( $config ) ) {
				echo '<input type="button" class="button w3tc-gopro-button button-buy-plugin" data-src="dashboard_banner" value="' . esc_attr__( 'Upgrade', 'w3-total-cache' ) . '" />';
			}
			?>
		</div>
	</div>
</div>