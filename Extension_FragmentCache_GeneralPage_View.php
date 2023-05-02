<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

Util_Ui::postbox_header_tabs(
	esc_html__( 'Fragment Cache', 'w3-total-cache' ),
	esc_html__( 'This needs a description!', 'w3-total-cache' ),
	'',
	'fragmentcache',
	Util_UI::admin_url( 'admin.php?page=w3tc_fragmentcache' )
);

?>
<p><?php esc_html_e( 'Enable fragment caching reduce execution time for common operations.', 'w3-total-cache' ); ?></p>

<table class="form-table">
	<?php
	Util_Ui::config_item_engine(
		array(
			'key'         => array( 'fragmentcache', 'engine' ),
			'label'       => __( 'Fragment Cache Method:', 'w3-total-cache' ),
			'empty_value' => true,
		)
	);
	?>
</table>

<?php Util_Ui::postbox_footer(); ?>
