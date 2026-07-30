<?php
/**
 * File: top_nav_bar.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die();
}

$w3tc_config = Dispatcher::config();

$w3tc_menu_array = array(
	'settings' => array(
		0 => array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_general' ),
			'text' => __( 'General Settings', 'w3-total-cache' ),
		),
		1 => array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_pgcache' ),
			'text' => __( 'Page Cache', 'w3-total-cache' ),
		),
		2 => array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_minify' ),
			'text' => __( 'Minify', 'w3-total-cache' ),
		),
		3 => array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_dbcache' ),
			'text' => __( 'Database Cache', 'w3-total-cache' ),
		),
		4 => array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_objectcache' ),
			'text' => __( 'Object Cache', 'w3-total-cache' ),
		),
		5 => array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_browsercache' ),
			'text' => __( 'Browser Cache', 'w3-total-cache' ),
		),
		6 => array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_cachegroups' ),
			'text' => __( 'Cache Groups', 'w3-total-cache' ),
		),
		7 => array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_cdn' ),
			'text' => 'CDN',
		),
		9 => array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_userexperience' ),
			'text' => __( 'User Experience', 'w3-total-cache' ),
		),
	),
	'tools'    => array(
		0 => array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_extensions' ),
			'text' => __( 'Extensions', 'w3-total-cache' ),
		),
		1 => array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_stats' ),
			'text' => __( 'Statistics', 'w3-total-cache' ),
		),
		2 => array(
			'url'  => $w3tc_config->is_extension_active( 'imageservice' )
				? Util_Ui::admin_url( 'upload.php?page=w3tc_extension_page_imageservice' )
				: Util_Ui::admin_url( 'admin.php?page=w3tc_general#image_service' ),
			'text' => __( 'Image Converter', 'w3-total-cache' ),
		),
		3 => array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_pagespeed' ),
			'text' => __( 'Google PageSpeed', 'w3-total-cache' ),
		),
		4 => array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_setup_guide' ),
			'text' => __( 'Setup Guide', 'w3-total-cache' ),
		),
	),
	'info'     => array(
		0 => array(
			'url'  => Util_UI::admin_url( 'admin.php?page=w3tc_about' ),
			'text' => __( 'About', 'w3-total-cache' ),
		),
		1 => array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_feature_showcase' ),
			'text' => __( 'Feature Showcase', 'w3-total-cache' ),
		),
		2 => array(
			'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_install' ),
			'text' => __( 'Install', 'w3-total-cache' ),
		),
		3 => array(
			'url'   => '#',
			'text'  => __( 'Compatibility Test', 'w3-total-cache' ),
			'class' => 'compatiblity-test button-self-test',
		),
		4 => array(
			'url'      => 'https://api.w3-edge.com/v1/redirects/faq',
			'text'     => 'FAQ',
			'target'   => '_blank',
			'dashicon' => '<span class="dashicons dashicons-external"></span>',
		),
	),
);

if ( $w3tc_config->is_extension_active_frontend( 'fragmentcache' ) && Util_Environment::is_w3tc_pro( $w3tc_config ) ) {
	$w3tc_menu_array['settings'][8] = array(
		'url'  => Util_Ui::admin_url( 'admin.php?page=w3tc_fragmentcache' ),
		'text' => __( 'Fragment Cache', 'w3-total-cache' ),
	);
}

ksort( $w3tc_menu_array['settings'] );

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
				<a class="w3tc-top-nav-settings no-link" href="#" alt="<?php esc_attr_e( 'Settings', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'Settings', 'w3-total-cache' ); ?><span class="dashicons dashicons-arrow-down-alt2"></span>
				</a>
				<div id="w3tc-top-nav-settings-menu" class="w3tc-top-nav-dropdown-content">
					<?php
					foreach ( $w3tc_menu_array['settings'] as $w3tc_index => $w3tc_entry ) {
						$w3tc_output = sprintf(
							// translators: 1 link class, 2 link href URL , 3 link alt text, 4 link target, 5 link text, 6 link text dashicon.
							'<a %1$s href="%2$s" alt="%3$s"%4$s>%5$s%6$s</a>',
							! empty( $w3tc_entry['class'] ) ? ' class="' . esc_attr( $w3tc_entry['class'] ) . '" ' : '',
							esc_url( $w3tc_entry['url'] ),
							esc_attr( $w3tc_entry['text'] ),
							( ! empty( $w3tc_entry['target'] ) ? ' target="' . esc_attr( $w3tc_entry['target'] ) . '"' : '' ),
							esc_html( $w3tc_entry['text'] ),
							( ! empty( $w3tc_entry['dashicon'] ) ? $w3tc_entry['dashicon'] : '' )
						);
						echo wp_kses( $w3tc_output, Util_Ui::get_allowed_html_for_wp_kses_from_content( $w3tc_output ) );
					}
					?>
				</div>
			</div>
			<div class="w3tc-top-nav-dropdown">
				<a class="w3tc-top-nav-tools no-link" href="#" alt="<?php esc_attr_e( 'Tools', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'Tools', 'w3-total-cache' ); ?><span class="dashicons dashicons-arrow-down-alt2"></span>
				</a>
				<div id="w3tc-top-nav-tools-menu" class="w3tc-top-nav-dropdown-content">
					<?php
					foreach ( $w3tc_menu_array['tools'] as $w3tc_entry ) {
						$w3tc_output = sprintf(
							// translators: 1 link class, 2 link href URL , 3 link alt text, 4 link target, 5 link text, 6 link text dashicon.
							'<a %1$s href="%2$s" alt="%3$s"%4$s>%5$s%6$s</a>',
							! empty( $w3tc_entry['class'] ) ? ' class="' . esc_attr( $w3tc_entry['class'] ) . '" ' : '',
							esc_url( $w3tc_entry['url'] ),
							esc_attr( $w3tc_entry['text'] ),
							( ! empty( $w3tc_entry['target'] ) ? ' target="' . esc_attr( $w3tc_entry['target'] ) . '"' : '' ),
							esc_html( $w3tc_entry['text'] ),
							( ! empty( $w3tc_entry['dashicon'] ) ? $w3tc_entry['dashicon'] : '' )
						);
						echo wp_kses( $w3tc_output, Util_Ui::get_allowed_html_for_wp_kses_from_content( $w3tc_output ) );
					}
					?>
				</div>
			</div>
			<div class="w3tc-top-nav-dropdown">
				<a class="w3tc-top-nav-info no-link" href="#" alt="<?php esc_attr_e( 'Info', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'Info', 'w3-total-cache' ); ?><span class="dashicons dashicons-arrow-down-alt2"></span>
				</a>
				<div id="w3tc-top-nav-info-menu" class="w3tc-top-nav-dropdown-content">
					<?php
					foreach ( $w3tc_menu_array['info'] as $w3tc_entry ) {
						$w3tc_output = sprintf(
							// translators: 1 link class, 2 link href URL , 3 link alt text, 4 link target, 5 link text, 6 link text dashicon.
							'<a %1$s href="%2$s" alt="%3$s"%4$s>%5$s%6$s</a>',
							! empty( $w3tc_entry['class'] ) ? ' class="' . esc_attr( $w3tc_entry['class'] ) . '" ' : '',
							esc_url( $w3tc_entry['url'] ),
							esc_attr( $w3tc_entry['text'] ),
							( ! empty( $w3tc_entry['target'] ) ? ' target="' . esc_attr( $w3tc_entry['target'] ) . '"' : '' ),
							esc_html( $w3tc_entry['text'] ),
							( ! empty( $w3tc_entry['dashicon'] ) ? $w3tc_entry['dashicon'] : '' )
						);
						echo wp_kses( $w3tc_output, Util_Ui::get_allowed_html_for_wp_kses_from_content( $w3tc_output ) );
					}
					?>
				</div>
			</div>
			<a class="w3tc-top-nav-support" href="<?php echo esc_url( Util_UI::admin_url( 'admin.php?page=w3tc_support' ) ); ?>" alt="<?php esc_attr_e( 'Support', 'w3-total-cache' ); ?>">
				<?php esc_html_e( 'Support', 'w3-total-cache' ); ?>
			</a>
			<?php
			if ( \user_can( \get_current_user_id(), 'manage_options' ) && ! Util_Environment::is_w3tc_pro( $w3tc_config ) ) {
				echo '<input type="button" class="button-primary button-buy-plugin {nonce: \'' . esc_attr( Util_Nonce::create_admin( 'w3tc_licensing_upgrade' ) ) . '\'}"
					data-src="top_nav_bar" value="' . esc_html__( 'Upgrade', 'w3-total-cache' ) . '" />';
			}
			?>
		</div>
	</div>
</div>
