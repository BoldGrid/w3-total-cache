<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

?>
<?php Util_Ui::postbox_header( __( 'Lazy Loading', 'w3-total-cache' ), '', 'application' ); ?>
<table class="form-table">
	<?php
	Util_Ui::config_item( array(
			'key' => 'lazyload.process_img',
			'control' => 'checkbox',
			'checkbox_label' => __( 'Process HTML image tags', 'w3-total-cache' ),
			'description' => __( 'Process <code>img</code> tags',
				'w3-total-cache' )
	) );

	Util_Ui::config_item( array(
			'key' => 'lazyload.process_background',
			'control' => 'checkbox',
			'checkbox_label' => __( 'Process background images', 'w3-total-cache' ),
			'description' => __( 'Process <code>background</code> styles',
				'w3-total-cache' )
	) );

	Util_Ui::config_item( array(
			'key' => 'lazyload.exclude',
			'label' => 'Exclude words:',
			'control' => 'textarea',
			'description' => __( 'Exclude tags containing words',
				'w3-total-cache' )
	) );

	Util_Ui::config_item( array(
			'key' => 'lazyload.embed_method',
			'label' => __( 'Script Embed method:', 'w3-total-cache' ),
			'control' => 'selectbox',
			'selectbox_values' => array(
				'async_head' => 'async',
				'sync_head' => 'sync (to head)',
				'inline_footer' => 'inline'
			),
			'description' => 'Use <code>inline</code> method only when your website has just a few pages'
		)
	);
	?>
</table>
<p class="submit">
	<?php Util_Ui::button_config_save( 'lazyload' ); ?>
</p>

<?php Util_Ui::postbox_footer(); ?>
