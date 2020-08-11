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
			'checkbox_label' => __( 'Lazy Load Images', 'w3-total-cache' ),
			'description' => __( 'Defer loading offscreen images.',
				'w3-total-cache' )
	) );

	Util_Ui::config_item_pro( array(
		'key' => 'lazyload_googlemaps_general_settings',
		'control' => 'none',
		'none_label' => __( 'Lazy Load Google Maps', 'w3-total-cache' ),
		'excerpt' => wp_kses(
			sprintf(
				// Translators: 1 an opening anchor to the user experience page, 2 its closing anchor tag, 3 an opening strong tag, 4 its closing tag.
				__( 'In addition to lazy loading images, with %3$sW3 Total Cache Pro%4$s you can lazy load %3$sGoogle Maps%4$s! More information and settings can be found on the %1$sUser Experience page%2$s.', 'w3-total-cache' ),
				'<a href="' . admin_url( 'admin.php?page=w3tc_userexperience' ) . '">',
				'</a>',
				'<strong>',
				'</strong>'
			),
			array(
				'a'      => array(
					'href' => array(),
				),
				'strong' => array(),
			)
		),
		'description' => array(),
	) );

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

	Util_Ui::config_item(
		array(
			'key'            => 'jquerymigrate.disabled',
			'control'        => 'checkbox',
			'checkbox_label' => __( 'Disable jquery-migrate on the front-end', 'w3-total-cache' ),
			'description'    => __( 'Remove jquery-migrate support from your website front-end.', 'w3-total-cache' ),
		)
	);
	?>
</table>

<?php Util_Ui::button_config_save( 'general_userexperience' ); ?>
<?php Util_Ui::postbox_footer(); ?>
