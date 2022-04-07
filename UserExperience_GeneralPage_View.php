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
				'<a href="' . admin_url( 'admin.php?page=w3tc_userexperience' ) . '">',
				'</a>',
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
