<?php
/**
 * File: Extension_ImageOptimizer_Page_View.php
 *
 * Page view for the image optimizer extension settings.
 *
 * @since X.X.X
 *
 * @package W3TC
 *
 * @uses Config $c      Configuration object.
 * @uses array  $counts Optimager counts.
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

?>
<div class="wrap" id="w3tc">

<p>
	Total Cache Image Service is currently
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

<form id="w3tc-optimager-settings" action="upload.php?page=w3tc_extension_page_optimager" method="post">
<div class="metabox-holder">

	<?php Util_Ui::postbox_header( esc_html__( 'Configuration', 'w3-total-cache' ), '', '' ); ?>

	<table class="form-table" id="w3tc-optimager-config">
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

	<table class="form-table" id="w3tc-optimager-tools">
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

	Util_Ui::postbox_header(
		esc_html__( 'Statistics', 'w3-total-cache' ),
		'',
		'w3tc-optimager-statistics'
	);
	?>

	<table class="form-table" id="w3tc-optimager-stats">
		<tr>
			<th><?php esc_html_e( 'Counts and filesizes by status:', 'w3-total-cache' ); ?></th>
			<td>
				<table id="w3tc-optimager-counts">
					<tr>
						<td><?php esc_html_e( 'Total:', 'w3-total-cache' ); ?></td>
						<td id="w3tc-optimager-total"><?php echo $counts['total']; ?></td>
						<td id="w3tc-optimager-totalbytes"><?php echo size_format( $counts['totalbytes'], 2 ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Optimized:', 'w3-total-cache' ); ?></td>
						<td id="w3tc-optimager-optimized"><?php echo $counts['optimized']; ?></td>
						<td id="w3tc-optimager-optimizedbytes"><?php echo size_format( $counts['optimizedbytes'], 2 ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Sending:', 'w3-total-cache' ); ?></td>
						<td id="w3tc-optimager-sending"><?php echo $counts['sending']; ?></td>
						<td id="w3tc-optimager-sendingbytes"><?php echo size_format( $counts['sendingbytes'], 2 ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Processing:', 'w3-total-cache' ); ?></td>
						<td id="w3tc-optimager-processing"><?php echo $counts['processing']; ?></td>
						<td id="w3tc-optimager-processingbytes"><?php echo size_format( $counts['processingbytes'], 2 ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Not optimized:', 'w3-total-cache' ); ?></td>
						<td id="w3tc-optimager-notoptimized"><?php echo $counts['notoptimized']; ?></td>
						<td id="w3tc-optimager-notoptimizedbytes"><?php echo size_format( $counts['notoptimizedbytes'], 2 ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Unoptimized:', 'w3-total-cache' ); ?></td>
						<td id="w3tc-optimager-unoptimized"><?php echo $counts['unoptimized']; ?></td>
						<td id="w3tc-optimager-unoptimizedbytes"><?php echo size_format( $counts['unoptimizedbytes'], 2 ); ?></td>
					</tr>
					<tr><td height="10"></td></tr>
					<tr>
						<td colspan="3"><input id="w3tc-optimager-refresh" class="button" type="button" value="<?php esc_attr_e( 'Refresh', 'w3-total-cache' ) ?>" /></td>
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

</div>
