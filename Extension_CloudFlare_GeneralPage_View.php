<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

Util_Ui::postbox_header_tabs(
	esc_html__( 'Network Performance &amp; Security powered by Cloudflare', 'w3-total-cache' ),
	esc_html__(
		'Cloudflare is a powerful content delivery network (CDN) and security service that can greatly enhance
			the performance and security of your WordPress website. By integrating Cloudflare with W3 Total Cache,
			you can take advantage of its global network of servers to deliver your website\'s content faster to
			visitors from around the world, resulting in reduced loading times and improved user experience.
			Additionally, Cloudflare offers various optimization features like minification, caching, and image
			optimization, further accelerating your website\'s loading speed and overall performance.',
		'w3-total-cache'
	),
	'',
	'cloudflare',
	Util_UI::admin_url( 'admin.php?page=w3tc_extensions&extension=cloudflare&action=view' )
);
Util_Ui::config_overloading_button( array( 'key' => 'cloudflare.configuration_overloaded' ) );

?>

<table class="form-table">
	<?php
	Util_Ui::config_item(
		array(
			'key'              => array( 'cloudflare', 'widget_interval' ),
			'label'            => esc_html__( 'Widget statistics interval:', 'w3-total-cache' ),
			'control'          => 'selectbox',
			'selectbox_values' => array(
				'-30'    => esc_html__( 'Last 30 minutes', 'w3-total-cache' ),
				'-360'   => esc_html__( 'Last 6 hours', 'w3-total-cache' ),
				'-720'   => esc_html__( 'Last 12 hours', 'w3-total-cache' ),
				'-1440'  => esc_html__( 'Last 24 hours', 'w3-total-cache' ),
				'-10080' => esc_html__( 'Last week', 'w3-total-cache' ),
				'-43200' => esc_html__( 'Last month', 'w3-total-cache' ),
			),
		)
	);

	Util_Ui::config_item(
		array(
			'key'         => array( 'cloudflare', 'widget_cache_mins' ),
			'label'       => esc_html__( 'Cache time:', 'w3-total-cache' ),
			'control'     => 'textbox',
			'description' => esc_html__( 'How many minutes data retrieved from Cloudflare should be stored. Minimum is 1 minute.', 'w3-total-cache' ),
		)
	);

	Util_Ui::config_item(
		array(
			'key'            => array( 'cloudflare', 'pagecache' ),
			'label'          => esc_html__( 'Page Caching:', 'w3-total-cache' ),
			'control'        => 'checkbox',
			'checkbox_label' => esc_html__( 'Flush Cloudflare on Post Modifications', 'w3-total-cache' ),
			'description'    => esc_html__( 'Enable when you have html pages cached on Cloudflare level.', 'w3-total-cache' ),
		)
	);

	Util_Ui::config_item(
		array(
			'key'            => array( 'cloudflare', 'minify_js_rl_exclude' ),
			'label'          => esc_html__( 'Minified JS Rocket Loader Exclude:', 'w3-total-cache' ),
			'checkbox_label' => esc_html__( 'Exclude minified JS files from being processed by Rocket Loader:', 'w3-total-cache' ),
			'control'        => 'checkbox',
			'description'    => esc_html__( 'Exclusion achieved by adding data-cfasync="false" to script tags.', 'w3-total-cache' ),
		)
	);

	?>
</table>

<?php Util_Ui::postbox_footer(); ?>
