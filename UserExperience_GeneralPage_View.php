<?php
/**
 * File: UserExperience_GeneralPage_View.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die();
}

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

	Util_Ui::config_item_extension_enabled(
		array(
			'extension_id'   => 'user-experience-emoji',
			'checkbox_label' => esc_html__( 'Disable Emoji', 'w3-total-cache' ),
			'description'    => esc_html__( 'Remove emojis support from your website.', 'w3-total-cache' ),
			'label_class'    => 'w3tc_single_column',
		)
	);

	do_action( 'w3tc_settings_general_userexperience_pro' );

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
