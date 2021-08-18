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
</p>

<form action="admin.php?page=w3tc_extensions&amp;extension=optimager&amp;action=view" method="post">
<div class="metabox-holder">

	<?php Util_Ui::postbox_header( esc_html__( 'Configuration', 'w3-total-cache' ), '', '' ); ?>

	<table class="form-table">
		<?php
		Util_Ui::config_item(
			array(
				'key'               => array(
					'optimager',
					'compression',
				),
				'label'             => esc_html__( 'Compression type:', 'w3-total-cache' ),
				'control'           => 'radiogroup',
				'radiogroup_values' => array(
					'lossy'    => 'Lossy',
					'lossless' => 'Lossless',
				),
				'description'       => esc_html__( 'Image compression type.', 'w3-total-cache' ),
			)
		);

		Util_Ui::config_item(
			array(
				'key'               => array(
					'optimager',
					'auto',
				),
				'label'             => esc_html__( 'Auto-optimize:', 'w3-total-cache' ),
				'control'           => 'radiogroup',
				'radiogroup_values' => array(
					'enabled'  => 'Enabled',
					'disabled' => 'Disabled',
				),
				'description'       =>esc_html__( 'Auto-optimize images on upload.', 'w3-total-cache' ),
			)
		);
		?>
	</table>

	<?php
	Util_Ui::button_config_save( 'extension_optimager_configuration' );
	Util_Ui::postbox_footer();

	Util_Ui::postbox_header( esc_html__( 'Tools', 'w3-total-cache' ), '', '' );
	?>

	<table class="form-table">
	<?php
		Util_Ui::config_item(
			array(
				'key'         => null,
				'label'       => esc_html__( 'Optimize all images:', 'w3-total-cache' ),
				'label_class' => 'w3tc-optimager-all',
				'control'     => 'button',
				'none_label'  => 'Optimize All',
				'description' => esc_html__( 'Optimize all images in the media library.', 'w3-total-cache' ),
			)
		);

		Util_Ui::config_item(
			array(
				'key'         => null,
				'label'       => esc_html__( 'Revert all images:', 'w3-total-cache' ),
				'label_class' => 'w3tc-optimager-revertall',
				'control'     => 'button',
				'none_label'  => 'Revert All',
				'description' => esc_html__( 'Revert all optimized images in the media library.', 'w3-total-cache' ),
			)
		);
	?>
	</table>

	<?php
	Util_Ui::postbox_footer();

	Util_Ui::postbox_header( esc_html__( 'Statistics', 'w3-total-cache' ), '', '' );
	?>

	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Optimizations:', 'w3-total-cache' ); ?></th>
			<td>
				<table>
					<tr>
						<td><?php esc_html_e( 'Total:', 'w3-total-cache' ); ?></td>
						<td><?php echo $total_count; ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Optimized:', 'w3-total-cache' ); ?></td>
						<td><?php echo $optimized_count; ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Unoptimized:', 'w3-total-cache' ); ?></td>
						<td><?php echo $unoptimized_count; ?></td>
					</tr>
				</table>
			</td>
		</tr>
	<?php
	?>
	</table>

	<?php Util_Ui::postbox_footer(); ?>

</div>
</form>
