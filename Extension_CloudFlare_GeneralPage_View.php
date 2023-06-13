<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

Util_Ui::postbox_header_tabs(
	esc_html__( 'Network Performance &amp; Security powered by CloudFlare', 'w3-total-cache' ),
	esc_html__(
		'CloudFlare is a powerful content delivery network (CDN) and security service that can greatly enhance 
			the performance and security of your WordPress website. By integrating CloudFlare with W3 Total Cache, 
			you can take advantage of its global network of servers to deliver your website\'s content faster to 
			visitors from around the world, resulting in reduced loading times and improved user experience. 
			Additionally, CloudFlare offers various optimization features like minification, caching, and image 
			optimization, further accelerating your website\'s loading speed and overall performance.',
		'w3-total-cache'
	),
	'',
	'cloudflare'
);
Util_Ui::config_overloading_button( array( 'key' => 'cloudflare.configuration_overloaded' ) );

?>

<table class="form-table">
	<?php
	Util_Ui::config_item(
		array(
			'key'         => array( 'cloudflare', 'widget_cache_mins' ),
			'label'       => esc_html__( 'Cache time:', 'w3-total-cache' ),
			'control'     => 'textbox',
			'description' => esc_html__( 'How many minutes data retrieved from CloudFlare should be stored. Minimum is 1 minute.', 'w3-total-cache' ),
		)
	);

	Util_Ui::config_item(
		array(
			'key'            => array( 'cloudflare', 'pagecache' ),
			'label'          => esc_html__( 'Page Caching:', 'w3-total-cache' ),
			'control'        => 'checkbox',
			'checkbox_label' => esc_html__( 'Flush CloudFlare on Post Modifications', 'w3-total-cache' ),
			'description'    => esc_html__( 'Enable when you have html pages cached on CloudFlare level.', 'w3-total-cache' ),
		)
	);
	?>
</table>

<?php Util_Ui::postbox_footer(); ?>
