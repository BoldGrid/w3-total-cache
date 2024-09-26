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
	Util_UI::admin_url( 'admin.php?page=w3tc_cdn' )
);
Util_Ui::config_overloading_button(
	array(
		'key' => 'cdn.configuration_overloaded',
	)
);
?>
<div id="w3tc-bunnycdn-ad-general">
	<?php
	if ( ! $cdn_enabled ) {
		echo wp_kses(
			sprintf(
				// translators: 1 opening HTML strong tag, 2 closing HTML strong tag,
				// translators: 3 HTML input for Bunny CDN sign up, 4 HTML img tag for Bunny CDN white logo.
				__(
					'%1$sLooking for a top rated CDN Provider? Try Bunny CDN.%2$s%3$s%4$s',
					'w3-total-cache'
				),
				'<strong>',
				'</strong>',
				Util_Ui::button_link(
					__( 'Sign up now to enjoy a special offer!', 'w3-total-cache' ),
					esc_url( W3TC_BUNNYCDN_SIGNUP_URL ),
					true,
					'w3tc-bunnycdn-promotion-button',
					'w3tc-bunnycdn-promotion-button'
				),
				'<img class="w3tc-bunnycdn-icon-white" src="' . esc_url( plugins_url( '/pub/img/w3tc_bunnycdn_icon_white.png', W3TC_FILE ) ) . '" alt="Bunny CDN Icon White">'
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
					'type'    => array(),
					'name'    => array(),
					'class'   => array(),
					'value'   => array(),
					'onclick' => array(),
				),
			)
		);
	}
	?>
</div>
<p>
	<?php
	$config        = Dispatcher::config();
	$cdn_engine    = $config->get_string( 'cdn.engine' );
	$cdnfsd_engine = $config->get_string( 'cdnfsd.engine' );
	$stackpaths    = array( 'stackpath', 'stackpath2' );

	if ( in_array( $cdn_engine, $stackpaths, true ) || in_array( $cdnfsd_engine, $stackpaths, true ) ) {
		?>
		<div class="notice notice-warning inline">
			<p>
				<?php
				// StackPath sunset is 12:00 am Central (UTC-6:00) on November, 22, 2023 (1700629200).
				\printf(
					// translators: 1 StackPath sunset datetime.
					\esc_html__(
						'StackPath ceased operations on %1$s.',
						'w3-total-cache'
					),
					\wp_date( \get_option( 'date_format' ), '1700629200' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
				?>
			</p>
		</div>
		<?php
	} elseif ( 'highwinds' === $cdn_engine || 'highwinds' === $cdnfsd_engine ) {
		?>
		<div class="notice notice-warning inline">
			<p>
				<?php
				// HighWinds sunset is 12:00 am Central (UTC-6:00) on November, 22, 2023 (1700629200).
				\printf(
					// translators: 1 HighWinds sunset datetime.
					\esc_html__(
						'HighWinds ceased operations on %1$s.',
						'w3-total-cache'
					),
					\wp_date( \get_option( 'date_format' ), '1700629200' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
				?>
			</p>
		</div>
		<?php
	}
	?>
</p>
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
