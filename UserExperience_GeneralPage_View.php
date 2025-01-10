<?php
/**
 * File: UserExperience_GeneralPage_View.php
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$config = Dispatcher::config();
?>
<?php
Util_Ui::postbox_header_tabs(
	esc_html__( 'User Experience', 'w3-total-cache' ),
	esc_html__(
		'User Experience (UX) is a setting that focuses on enhancing the overall browsing experience for visitors
		of your website. By enabling this feature, you can optimize your website\'s performance by minimizing
		load times, reducing server requests, and delivering content more efficiently. This ultimately leads
		to faster page loading, improved user satisfaction, and increased engagement, resulting in a speedier
		and more enjoyable WordPress website.',
		'w3-total-cache'
	),
	'',
	'userexperience',
	Util_UI::admin_url( 'admin.php?page=w3tc_userexperience' )
);
Util_Ui::config_overloading_button( array( 'key' => 'lazyload.configuration_overloaded' ) );

?>

<table class="form-table">
	<?php
	Util_Ui::config_item(
		array(
			'key'            => 'lazyload.enabled',
			'control'        => 'checkbox',
			'checkbox_label' => esc_html__( 'Lazy Load Images', 'w3-total-cache' ),
			'label_class'    => 'w3tc_single_column',
			'description'    => esc_html__( 'Defer loading offscreen images.', 'w3-total-cache' ),
		)
	);

	Util_Ui::config_item_pro(
		array(
			'key'               => 'lazyload_googlemaps_general_settings',
			'control'           => 'none',
			'label_class'       => 'w3tc_single_column',
			'none_label'        => esc_html__( 'Lazy Load Google Maps', 'w3-total-cache' ),
			'excerpt'           => wp_kses(
				sprintf(
					// translators: 1 opening HTML strong tag, 2 closing HTML strong tag, 3 opening HTML strong tag, 4 closing HTML strong tag,
					// translators: 5 opening HTML a tag to W3TC User Experience page, 6 closing HTML a tag.
					__(
						'In addition to lazy loading images, with %1$sW3 Total Cache Pro%2$s you can lazy load %3$sGoogle Maps%4$s! More information and settings can be found on the %5$sUser Experience page%6$s.',
						'w3-total-cache'
					),
					'<strong>',
					'</strong>',
					'<strong>',
					'</strong>',
					'<a href="' . Util_Ui::admin_url( 'admin.php?page=w3tc_userexperience#lazy-loading' ) . '">',
					'</a>'
				),
				array(
					'a'      => array(
						'href' => array(),
					),
					'strong' => array(),
				)
			),
			'description'       => array(),
			'show_learn_more'   => false,
			'intro_label'       => __( 'Potential Google PageSpeed Gain', 'w3-total-cache' ),
			'score'             => '+10',
			'score_label'       => __( 'Points', 'w3-total-cache' ),
			'score_description' => __( 'In a recent test, using the Lazy Load Google Maps feature added 10 points to the Google PageSpeed mobile score!', 'w3-total-cache' ),
			'score_link'        => 'https://www.boldgrid.com/support/w3-total-cache/pagespeed-tests/lazy-load-maps/?utm_source=w3tc&utm_medium=lazy-load-maps&utm_campaign=proof',
		)
	);

	Util_Ui::config_item_extension_enabled(
		array(
			'extension_id'   => 'user-experience-emoji',
			'checkbox_label' => esc_html__( 'Disable Emoji', 'w3-total-cache' ),
			'description'    => esc_html__( 'Remove emojis support from your website.', 'w3-total-cache' ),
			'label_class'    => 'w3tc_single_column',
		)
	);

	Util_Ui::config_item_extension_enabled(
		array(
			'extension_id'      => 'user-experience-defer-scripts',
			'checkbox_label'    => esc_html__( 'Delay Scripts', 'w3-total-cache' ),
			'description'       => __(
				'Delay the loading of specified internal/external JavaScript sources on your pages separate from Minify.',
				'w3-total-cache'
			) . (
				UserExperience_DeferScripts_Extension::is_enabled()
				? wp_kses(
					sprintf(
						// translators: 1 opening HTML a tag to W3TC User Experience page, 2 closing HTML a tag.
						__(
							' Settings can be found on the %1$sUser Experience page%2$s.',
							'w3-total-cache'
						),
						'<a href="' . Util_Ui::admin_url( 'admin.php?page=w3tc_userexperience#defer-scripts' ) . '">',
						'</a>'
					),
					array(
						'a' => array(
							'href' => array(),
						),
					)
				)
				: ''
			),
			'label_class'       => 'w3tc_single_column',
			'pro'               => true,
			'disabled'          => ! Util_Environment::is_w3tc_pro( $config ) ? true : false,
			'show_learn_more'   => false,
			'intro_label'       => __( 'Potential Google PageSpeed Gain', 'w3-total-cache' ),
			'score'             => '+18',
			'score_label'       => __( 'Points', 'w3-total-cache' ),
			'score_description' => __( 'In a recent test, using the Delay Scripts feature added 18 points on mobile devices to the Google PageSpeed score!', 'w3-total-cache' ),
			'score_link'        => 'https://www.boldgrid.com/support/w3-total-cache/pagespeed-tests/delay-scripts-test/?utm_source=w3tc&utm_medium=defer-js&utm_campaign=proof',
		)
	);

	Util_Ui::config_item_extension_enabled(
		array(
			'extension_id'      => 'user-experience-remove-cssjs',
			'checkbox_label'    => esc_html__( 'Remove Unwanted/Unused CSS/JS', 'w3-total-cache' ),
			'description'       => __(
				'Removes specified CSS/JS tags from the homepage or on a per page basis.',
				'w3-total-cache'
			) . (
				UserExperience_Remove_CssJs_Extension::is_enabled()
				? wp_kses(
					sprintf(
						// translators: 1 opening HTML a tag to W3TC User Experience page, 2 closing HTML a tag.
						__(
							' Settings can be found on the %1$sUser Experience page%2$s.',
							'w3-total-cache'
						),
						'<a href="' . Util_Ui::admin_url( 'admin.php?page=w3tc_userexperience#remove-cssjs' ) . '">',
						'</a>'
					),
					array(
						'a' => array(
							'href' => array(),
						),
					)
				)
				: ''
			),
			'label_class'       => 'w3tc_single_column',
			'pro'               => true,
			'disabled'          => ! Util_Environment::is_w3tc_pro( $config ) ? true : false,
			'show_learn_more'   => false,
			'intro_label'       => __( 'Potential Google PageSpeed Gain', 'w3-total-cache' ),
			'score'             => '+27',
			'score_label'       => __( 'Points', 'w3-total-cache' ),
			'score_description' => __( 'In one recent test, removing unused CSS and JS added over 27 points to the Google PageSpeed score!', 'w3-total-cache' ),
			'score_link'        => 'https://www.boldgrid.com/support/w3-total-cache/pagespeed-tests/remove-scripts/?utm_source=w3tc&utm_medium=remove-css-js&utm_campaign=proof',
		)
	);

	Util_Ui::config_item_extension_enabled(
		array(
			'extension_id'   => 'user-experience-preload-requests',
			'checkbox_label' => esc_html__( 'Preload Requests', 'w3-total-cache' ),
			'description'    => __(
				'DNS prefetching, pre-connecting, and preloading are essential web optimization techniques that enhance website performance by proactively resolving network-related tasks.',
				'w3-total-cache'
			) . (
				UserExperience_Preload_Requests_Extension::is_enabled()
				? wp_kses(
					sprintf(
						// translators: 1 opening HTML a tag to W3TC User Experience page, 2 closing HTML a tag.
						__(
							' Settings can be found on the %1$sUser Experience page%2$s.',
							'w3-total-cache'
						),
						'<a href="' . Util_Ui::admin_url( 'admin.php?page=w3tc_userexperience#preload-requests' ) . '">',
						'</a>'
					),
					array(
						'a' => array(
							'href' => array(),
						),
					)
				)
				: ''
			),
			'label_class'    => 'w3tc_single_column',
			'pro'            => true,
			'disabled'       => ! Util_Environment::is_w3tc_pro( $config ) ? true : false,
		)
	);

	Util_Ui::config_item_extension_enabled(
		array(
			'extension_id'   => 'user-experience-oembed',
			'checkbox_label' => esc_html__( 'Disable wp-embed script', 'w3-total-cache' ),
			'description'    => esc_html__( 'Remove wp-embed.js script from your website. oEmbed functionality still works but you will not be able to embed other WordPress posts on your pages.', 'w3-total-cache' ),
			'label_class'    => 'w3tc_single_column',
		)
	);

	Util_Ui::config_item(
		array(
			'key'            => 'jquerymigrate.disabled',
			'control'        => 'checkbox',
			'checkbox_label' => esc_html__( 'Disable jquery-migrate on the front-end', 'w3-total-cache' ),
			'label_class'    => 'w3tc_single_column',
			'description'    => esc_html__( 'Remove jquery-migrate support from your website front-end.', 'w3-total-cache' ),
		)
	);
	?>
</table>

<?php Util_Ui::postbox_footer(); ?>
