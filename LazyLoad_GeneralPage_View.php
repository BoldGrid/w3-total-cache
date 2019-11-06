<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

?>
<?php
Util_Ui::postbox_header( __( 'User Experience', 'w3-total-cache' ), '', 'userexperience' );
Util_Ui::config_overloading_button( array(
		'key' => 'lazyload.configuration_overloaded'
	) );
?>

<table class="form-table">
	<?php
	Util_Ui::config_item( array(
			'key' => 'lazyload.enabled',
			'control' => 'checkbox',
			'checkbox_label' => __( 'Lazy Loading', 'w3-total-cache' ),
			'description' => __( 'Defer loading offscreen images.',
				'w3-total-cache' )
	) );
	?>
</table>

<?php Util_Ui::button_config_save( 'general_userexperience' ); ?>
<?php Util_Ui::postbox_footer(); ?>
