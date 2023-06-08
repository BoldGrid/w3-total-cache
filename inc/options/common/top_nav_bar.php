<?php
/**
 * File: top_nav_bar.php
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$config = Dispatcher::config();

$menu_array = array(
	'settings' => array(
		array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_general' ),
			'text' => __( 'General Settings', 'w3-total-cache' ),
		),
		array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_pgcache' ),
			'text' => __( 'Page Cache', 'w3-total-cache' ),
		),
		array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_minify' ),
			'text' => __( 'Minify', 'w3-total-cache' ),
		),
		array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_dbcache' ),
			'text' => __( 'Database Cache', 'w3-total-cache' ),
		),
		array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_objectcache' ),
			'text' => __( 'Object Cache', 'w3-total-cache' ),
		),
		array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_browsercache' ),
			'text' => __( 'Browser Cache', 'w3-total-cache' ),
		),
		array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_cachegroups' ),
			'text' => __( 'Cache Groups', 'w3-total-cache' ),
		),
		array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_cdn' ),
			'text' => 'CDN',
		),
		array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_fragmentcache' ),
			'text' => __( 'Fragment Cache', 'w3-total-cache' ),
		),
		array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_userexperience' ),
			'text' => __( 'User Experience', 'w3-total-cache' ),
		),
	),
	'tools'    => array(
		array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_extensions' ),
			'text' => __( 'Extensions', 'w3-total-cache' ),
		),
		array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_stats' ),
			'text' => __( 'Statistics', 'w3-total-cache' ),
		),
		array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_pagespeed' ),
			'text' => __( 'Google PageSpeed', 'w3-total-cache' ),
		),
		array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_setup_guide' ),
			'text' => __( 'Setup Guide', 'w3-total-cache' ),
		),
	),
	'about'    => array(
		array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_feature_showcase' ),
			'text' => __( 'Feature Showcase', 'w3-total-cache' ),
		),
		array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_install' ),
			'text' => __( 'Install', 'w3-total-cache' ),
		),
		array(
			'url'      => 'https://api.w3-edge.com/v1/redirects/faq',
			'text'     => 'FAQ',
			'target'   => '_blank',
			'dashicon' => '<span class="dashicons dashicons-external"></span>',
		),
	),
);

do_action( 'w3tc_dashboard_top_nav_bar' );
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
			<a class="w3tc-top-nav-support" href="<?php echo esc_url( Util_UI::admin_url( 'admin.php?page=w3tc_dashboard' ) ); ?>" alt="<?php esc_attr_e( 'Dashboard', 'w3-total-cache' ); ?>">
				<?php esc_html_e( 'Dashboard', 'w3-total-cache' ); ?>
			</a>
			<div class="w3tc-top-nav-dropdown">
				<a class="w3tc-top-nav-settings" href="<?php echo esc_url( Util_UI::admin_url( 'admin.php?page=w3tc_general' ) ); ?>" alt="<?php esc_attr_e( 'Settings', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'Settings', 'w3-total-cache' ); ?><span class="dashicons dashicons-arrow-down-alt2"></span>
				</a>
				<div id="w3tc-top-nav-settings-menu" class="w3tc-top-nav-dropdown-content">
					<?php
					foreach ( $menu_array['settings'] as $entry ) {
						$target   = ! empty( $entry['target'] ) ? ' target="' . esc_attr( $entry['target'] ) . '"' : '';
						$dashicon = ! empty( $entry['dashicon'] ) ? $entry['dashicon'] : '';
						$output   = '<a href="' . esc_url( $entry['url'] ) . '" alt="' . esc_attr( $entry['text'] ) . '"' . $target . '>' . esc_html( $entry['text'] ) . $dashicon . '</a>';
						echo wp_kses( $output, Util_Ui::get_allowed_html_for_wp_kses_from_content( $output ) );
					}
					?>
				</div>
			</div>
			<div class="w3tc-top-nav-dropdown">
				<a class="w3tc-top-nav-tools" href="<?php echo esc_url( Util_UI::admin_url( 'admin.php?page=w3tc_extensions' ) ); ?>" alt="<?php esc_attr_e( 'Tools', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'Tools', 'w3-total-cache' ); ?><span class="dashicons dashicons-arrow-down-alt2"></span>
				</a>
				<div id="w3tc-top-nav-tools-menu" class="w3tc-top-nav-dropdown-content">
					<?php
					foreach ( $menu_array['tools'] as $entry ) {
						$target   = ! empty( $entry['target'] ) ? ' target="' . esc_attr( $entry['target'] ) . '"' : '';
						$dashicon = ! empty( $entry['dashicon'] ) ? $entry['dashicon'] : '';
						$output   = '<a href="' . esc_url( $entry['url'] ) . '" alt="' . esc_attr( $entry['text'] ) . '"' . $target . '>' . esc_html( $entry['text'] ) . $dashicon . '</a>';
						echo wp_kses( $output, Util_Ui::get_allowed_html_for_wp_kses_from_content( $output ) );
					}
					?>
				</div>
			</div>
			<div class="w3tc-top-nav-dropdown">
				<a class="w3tc-top-nav-about" href="<?php echo esc_url( Util_UI::admin_url( 'admin.php?page=w3tc_about' ) ); ?>" alt="<?php esc_attr_e( 'About', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'About', 'w3-total-cache' ); ?><span class="dashicons dashicons-arrow-down-alt2"></span>
				</a>
				<div id="w3tc-top-nav-about-menu" class="w3tc-top-nav-dropdown-content">
					<?php
					foreach ( $menu_array['about'] as $entry ) {
						$target   = ! empty( $entry['target'] ) ? ' target="' . esc_attr( $entry['target'] ) . '"' : '';
						$dashicon = ! empty( $entry['dashicon'] ) ? $entry['dashicon'] : '';
						$output   = '<a href="' . esc_url( $entry['url'] ) . '" alt="' . esc_attr( $entry['text'] ) . '"' . $target . '>' . esc_html( $entry['text'] ) . $dashicon . '</a>';
						echo wp_kses( $output, Util_Ui::get_allowed_html_for_wp_kses_from_content( $output ) );
					}
					?>
				</div>
			</div>
			<a class="w3tc-top-nav-support" href="<?php echo esc_url( Util_UI::admin_url( 'admin.php?page=w3tc_support' ) ); ?>" alt="<?php esc_attr_e( 'Support', 'w3-total-cache' ); ?>">
				<?php esc_html_e( 'Support', 'w3-total-cache' ); ?>
			</a>
			<?php
			if ( ! Util_Environment::is_w3tc_pro( $config ) ) {
				echo '<a class="button w3tc-gopro-button" href="' . esc_url( 'https://www.boldgrid.com/w3-total-cache/' ) . '" target="_blank">' . esc_html__( 'Upgrade', 'w3-total-cache' ) . '</a>';
			}
			?>
		</div>
	</div>
</div>
