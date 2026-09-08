<?php
/**
 * File: UserExperience_LazyLoad_Page_View.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die();
}

?>
<?php Util_Ui::postbox_header( esc_html__( 'Lazy Loading', 'w3-total-cache' ), '', 'lazy-loading' ); ?>
<table class="form-table">
	<?php
	Util_Ui::config_item(
		array(
			'key'            => 'lazyload.process_img',
			'control'        => 'checkbox',
			'checkbox_label' => esc_html__( 'Process HTML image tags', 'w3-total-cache' ),
			'description'    => wp_kses(
				sprintf(
					// translators: 1 opening HTML code tag, 2 closing HTML code tag.
					__(
						'Process %1$simg%2$s tags',
						'w3-total-cache'
					),
					'<code>',
					'</code>'
				),
				array(
					'code' => array(),
				)
			),
		)
	);

	Util_Ui::config_item(
		array(
			'key'            => 'lazyload.process_background',
			'control'        => 'checkbox',
			'checkbox_label' => esc_html__( 'Process background images', 'w3-total-cache' ),
			'description'    => wp_kses(
				sprintf(
					// translators: 1 opening HTML code tag, 2 closing HTML code tag.
					__(
						'Process %1$sbackground%2$s styles',
						'w3-total-cache'
					),
					'<code>',
					'</code>'
				),
				array(
					'code' => array(),
				)
			),
		)
	);

	Util_Ui::config_item(
		array(
			'key'         => 'lazyload.exclude',
			'label'       => esc_html__( 'Exclude words:', 'w3-total-cache' ),
			'control'     => 'textarea',
			'description' => esc_html__( 'Exclude tags containing words', 'w3-total-cache' ),
		)
	);

	Util_Ui::config_item(
		array(
			'key'         => 'lazyload.threshold',
			'control'     => 'textbox',
			'label'       => esc_html__( 'Threshold', 'w3-total-cache' ),
			'description' => esc_html__( 'The outer distance off the scrolling area from which to start loading the elements (example: 100px, 10%).', 'w3-total-cache' ),
		)
	);

	Util_Ui::config_item(
		array(
			'key'              => 'lazyload.embed_method',
			'label'            => esc_html__( 'Script Embed method:', 'w3-total-cache' ),
			'control'          => 'selectbox',
			'selectbox_values' => array(
				'async_head'    => esc_attr__( 'async', 'w3-total-cache' ),
				'sync_head'     => esc_attr__( 'sync (to head)', 'w3-total-cache' ),
				'inline_footer' => esc_attr__( 'inline', 'w3-total-cache' ),
			),
			'description'      => wp_kses(
				sprintf(
					// translators: 1 opening HTML code tag, 2 closing HTML code tag.
					__(
						'Use %1$sinline%2$s method only when your website has just a few pages',
						'w3-total-cache'
					),
					'<code>',
					'</code>'
				),
				array(
					'code' => array(),
				)
			),
		)
	);

	?>
</table>
<?php do_action( 'w3tc_settings_userexperience_lazyload_pro' ); ?>

<?php Util_Ui::postbox_footer(); ?>
