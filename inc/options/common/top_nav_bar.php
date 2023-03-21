<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$config            = Dispatcher::config();
$state             = Dispatcher::config_state();
$page              = Util_Admin::get_current_page();
$licensing_visible = (
	( ! Util_Environment::is_wpmu() || is_network_admin() ) &&
	! ini_get( 'w3tc.license_key' ) &&
	'host_valid' !== $state->get_string( 'license.status' )
);

$allowed_button_tags = array(
	'input'   => array(
		'type'    => array(),
		'name'    => array(),
		'class'   => array(),
		'value'   => array(),
		'onclick' => array(),
	),
);

$settings_menu_array = array(
	admin_url( 'admin.php?page=w3tc_dashboard' )        => esc_attr__( 'Dashboard', 'w3-total-cache' ),
	admin_url( 'admin.php?page=w3tc_feature_showcase' ) => esc_attr__( 'Feature Showcase', 'w3-total-cache' ),
	admin_url( 'admin.php?page=w3tc_pgcache' )          => esc_attr__( 'Page Cache', 'w3-total-cache' ),
	admin_url( 'admin.php?page=w3tc_minify' )           => esc_attr__( 'Minify', 'w3-total-cache' ),
	admin_url( 'admin.php?page=w3tc_dbcache' )          => esc_attr__( 'Database Cache', 'w3-total-cache' ),
	admin_url( 'admin.php?page=w3tc_objectcache' )      => esc_attr__( 'Object Cache', 'w3-total-cache' ),
	admin_url( 'admin.php?page=w3tc_browsercache' )     => esc_attr__( 'Browser Cache', 'w3-total-cache' ),
	admin_url( 'admin.php?page=w3tc_cachegroups' )      => esc_attr__( 'Cache Groups', 'w3-total-cache' ),
	admin_url( 'admin.php?page=w3tc_cdn' )              => 'CDN',
	admin_url( 'admin.php?page=w3tc_userexperience' )   => esc_attr__( 'User Experience', 'w3-total-cache' ),
);

$tools_menu_array = array(
	admin_url( 'admin.php?page=w3tc_pagespeed' )   => esc_attr__( 'Google PageSpeed', 'w3-total-cache' ),
	admin_url( 'admin.php?page=w3tc_setup_guide' ) => esc_attr__( 'Setup Guide', 'w3-total-cache' ),
);

$about_menu_array = array(
	'https://api.w3-edge.com/v1/redirects/faq'     => 'FAQ',
	admin_url( 'admin.php?page=w3tc_install' )     => esc_attr__( 'Install', 'w3-total-cache' ),
);

do_action( 'w3tc-dashboard-top-nav-bar' );
?>
<div id="w3tc-top-nav-bar">
	<div id="w3tc-top-nav-bar-content">
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
		<div id="w3tc-top-nav-bar-content-links">
		<div class="w3tc-top-nav-dropdown">
				<a class="w3tc-top-nav-settings" href="<?php echo admin_url( 'admin.php?page=w3tc_general' ); ?>" alt="<?php esc_attr_e( 'Settings', 'w3-total-cache' ); ?>">
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
				<a class="w3tc-top-nav-tools" href="<?php echo admin_url( 'admin.php?page=w3tc_tools' ); ?>" alt="<?php esc_attr_e( 'Tools', 'w3-total-cache' ); ?>">
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
				<a class="w3tc-top-nav-about" href="<?php echo admin_url( 'admin.php?page=w3tc_about' ); ?>" alt="<?php esc_attr_e( 'About', 'w3-total-cache' ); ?>">
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
			<a class="w3tc-top-nav-support" href="<?php echo admin_url( 'admin.php?page=w3tc_support' ); ?>" alt="<?php esc_attr_e( 'Support', 'w3-total-cache' ); ?>">
				<?php esc_html_e( 'Support', 'w3-total-cache' ); ?>
			</a>
			<input type="button" class="button w3tc-gopro-button button-buy-plugin" data-src="dashboard_banner" value="<?php esc_attr_e( 'Go Pro', 'w3-total-cache' ); ?>" />
		</div>
	</div>
</div>