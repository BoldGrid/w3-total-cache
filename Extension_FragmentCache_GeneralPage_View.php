<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$config = Dispatcher::config();

Util_Ui::postbox_header_tabs(
	esc_html__( 'Fragment Cache', 'w3-total-cache' ),
	esc_html__(
		'Fragment caching is a powerful feature that helps improve the speed and performance of your
			website. It allows you to cache specific sections or fragments of your web pages instead
			of caching the entire page. By selectively caching these fragments, such as sidebar widgets
			or dynamic content, you can reduce the processing time required to generate the page,
			resulting in faster load times and improved overall site performance.',
		'w3-total-cache'
	),
	'',
	'fragmentcache',
	Util_UI::admin_url( 'admin.php?page=w3tc_fragmentcache' )
);

?>

<table class="form-table">
	<?php
	$fragmentcache_config = array(
		'key'         => array( 'fragmentcache', 'engine' ),
		'label'       => __( 'Fragment Cache Method:', 'w3-total-cache' ),
		'empty_value' => true,
		'pro'         => true,
	);

	if ( ! Util_Environment::is_w3tc_pro( $config ) ) {
		$fragmentcache_config['disabled'] = true;
	}

	Util_Ui::config_item_engine( $fragmentcache_config );
	?>
</table>

<?php Util_Ui::postbox_footer(); ?>
