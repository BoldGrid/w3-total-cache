<?php
/**
 * File: Cdn_GeneralPage_View.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'W3TC' ) || die;

Util_Ui::postbox_header_tabs(
	wp_kses(
		sprintf(
			// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag.
			__(
				'%1$sCDN%2$s',
				'w3-total-cache'
			),
			'<acronym title="' . __( 'Content Delivery Network', 'w3-total-cache' ) . '">',
			'</acronym>'
		),
		array(
			'acronym' => array(
				'title' => array(),
			),
		)
	),
	esc_html__(
		'Content Delivery Network (CDN) is a powerful feature that can significantly enhance the performance of
			your WordPress website. By leveraging a distributed network of servers located worldwide, a CDN helps
			deliver your website\'s static files, such as images, CSS, and JavaScript, to visitors more efficiently.
			This reduces the latency and improves the loading speed of your website, resulting in a faster and
			smoother browsing experience for your users. With W3 Total Cache\'s CDN integration, you can easily
			configure and connect your website to a CDN service of your choice, unleashing the full potential of
			your WordPress site\'s speed optimization.',
		'w3-total-cache'
	),
	'',
	'cdn',
	Util_UI::admin_url( 'admin.php?page=w3tc_cdn' ),
	'w3tc_premium_services'
);
Util_Ui::config_overloading_button(
	array(
		'key' => 'cdn.configuration_overloaded',
	)
);
?>
<div id="w3tc-tcdn-ad-general">
	<?php
	$api_key = $config->get_string( 'cdn.totalcdn.account_api_key' );
	if ( ! $cdn_enabled && empty( $api_key ) ) {
		echo wp_kses(
			sprintf(
				// translators: 1 opening HTML strong tag, 2 closing HTML strong tag,
				// translators: 3 HTML input for Total CDN sign up, 4 HTML img tag for Total CDN logo.
				__(
					'%1$sLooking for a top rated CDN Provider? Try Total CDN.%2$s%3$s%4$s',
					'w3-total-cache'
				),
				'<strong>',
				'</strong>',
				'<input type="button" class="button-primary btn button-buy-tcdn" data-renew-key="' . $config->get_string( 'plugin.license_key' ) . '" data-src="general_page_cdn_subscribe" value="' . esc_attr__( 'Subscribe To Total CDN', 'w3-total-cache' ) . '">',
				'<img class="w3tc-tcdn-icon" src="' . esc_url( plugins_url( '/pub/img/w3tc_w3tc-logo.png', W3TC_FILE ) ) . '" alt="Total CDN Icon">'
			),
			array(
				'strong' => array(),
				'img'    => array(
					'class'  => array(),
					'src'    => array(),
					'alt'    => array(),
					'width'  => array(),
					'height' => array(),
				),
				'input'  => array(
					'type'           => array(),
					'name'           => array(),
					'class'          => array(),
					'value'          => array(),
					'onclick'        => array(),
					'data-renew-key' => array(),
					'data-src'       => array(),
				),
			)
		);
	}
	?>
</div>
<table class="form-table">
	<?php
	Util_Ui::config_item(
		array(
			'key'            => 'cdn.enabled',
			'control'        => 'checkbox',
			'checkbox_label' => __( 'Enable', 'w3-total-cache' ),
			'description'    => wp_kses(
				sprintf(
					// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag,
					// translators: 3 opening HTML acronym tag, 4 closing acronym tag.
					__(
						'Theme files, media library attachments, %1$sCSS%2$s, and %3$sJS%4$s files will load quickly for site visitors.',
						'w3-total-cache'
					),
					'<acronym title="' . __( 'Cascading Style Sheet', 'w3-total-cache' ) . '">',
					'</acronym>',
					'<acronym title="' . __( 'JavaScript', 'w3-total-cache' ) . '">',
					'</acronym>'
				),
				array(
					'acronym' => array(
						'title' => array(),
					),
				)
			),
		)
	);

	Util_Ui::config_item(
		array(
			'key'                 => 'cdn.engine',
			'control'             => 'selectbox',
			'selectbox_values'    => $engine_values,
			'selectbox_optgroups' => $engine_optgroups,
			'description'         => wp_kses(
				sprintf(
					// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag.
					__(
						'Select the %1$sCDN%2$s type you wish to use.',
						'w3-total-cache'
					),
					'<acronym title="' . __( 'Content Delivery Network', 'w3-total-cache' ) . '">',
					'</acronym>'
				),
				array(
					'acronym' => array(
						'title' => array(),
					),
				)
			),
		)
	);
	?>
</table>

<?php
do_action( 'w3tc_settings_general_boxarea_cdn_footer' );
?>

<?php Util_Ui::postbox_footer(); ?>
