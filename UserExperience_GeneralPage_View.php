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
	<?php
	Util_Ui::config_item_extension_enabled( array(
			'extension_id' => 'user-experience-emoji',
			'checkbox_label' => __( 'Disable Emoji', 'w3-total-cache' ),
			'description' => __( 'Remove emojis support from your website.',
				'w3-total-cache' )
	) );
	?>
	<?php
	Util_Ui::config_item_extension_enabled( array(
			'extension_id' => 'user-experience-oembed',
			'checkbox_label' => __( 'Disable wp-embed script', 'w3-total-cache' ),
			'description' => __( 'Remove wp-embed.js script from your website. oEmbed functionality still works but you will not be able to embed other WordPress posts on your pages.',
				'w3-total-cache' )
	) );
	?>
</table>

<?php Util_Ui::button_config_save( 'general_userexperience' ); ?>
<?php Util_Ui::postbox_footer(); ?>
