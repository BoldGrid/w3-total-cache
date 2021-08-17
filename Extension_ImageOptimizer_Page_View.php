<?php
/**
 * File: Extension_ImageOptimizer_Page_View.php
 *
 * Page view for the image optimizer extension settings.
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

?>
<p>
	Image Optimizer service is currently
	<?php
	if ( $c->is_extension_active( 'optimager' ) ) {
		?>
		<span class="w3tc-enabled">enabled</span>
		<?php
	} else {
		?>
		<span class="w3tc-disabled">disabled</span>
		<?php
	}
	?>
	.
<p>

<form action="admin.php?page=w3tc_extensions&amp;extension=optimager&amp;action=view" method="post">
<div class="metabox-holder">
	<?php Util_Ui::postbox_header( __( 'Configuration', 'w3-total-cache' ), '', '' ); ?>
	<table class="form-table">
		<?php
		Util_Ui::config_item(
			array(
				'key'               => array(
					'optimager',
					'compression',
				),
				'label'             => __( 'Compression type:', 'w3-total-cache' ),
				'control'           => 'radiogroup',
				'radiogroup_values' => array(
					'lossy'    => 'Lossy',
					'lossless' => 'Lossless',
				),
				'description'       => __( 'Image compression type.', 'w3-total-cache' ),
			)
		);

		Util_Ui::config_item(
			array(
				'key'               => array(
					'optimager',
					'auto',
				),
				'label'             => __( 'Auto-optimize:', 'w3-total-cache' ),
				'control'           => 'radiogroup',
				'radiogroup_values' => array(
					'enabled'  => 'Enabled',
					'disabled' => 'Disabled',
				),
				'description'       => __( 'Auto-optimize images on upload.', 'w3-total-cache' ),
			)
		);
		?>
	</table>
	<?php
	Util_Ui::button_config_save( 'extension_optimager_configuration' );
	Util_Ui::postbox_footer();
	Util_Ui::postbox_header( __( 'Tools', 'w3-total-cache' ), '', '' );
	?>
	<table class="form-table">
	<?php
		Util_Ui::config_item(
			array(
				'key'         => null,
				'label'       => __( 'Optimize all images:', 'w3-total-cache' ),
				'label_class' => 'w3tc-optimager-all',
				'control'     => 'button',
				'none_label'  => 'Optimize All',
				'description' => __( 'Optimize all images in the media library.', 'w3-total-cache' ),
			)
		);
	?>
	</table>
</div>
</form>
