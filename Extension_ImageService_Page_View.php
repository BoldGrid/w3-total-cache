<?php
/**
 * File: Extension_ImageService_Page_View.php
 *
 * View for the Image Service extension settings, tools, and statistics page.
 *
 * @since X.X.X
 *
 * @package W3TC
 *
 * @uses Config $c      Configuration object.
 * @uses array  $counts Image Service media counts.
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
	if ( $c->is_extension_active( 'imageservice' ) ) {
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

<form id="w3tc-imageservice-settings" action="upload.php?page=w3tc_extension_page_imageservice" method="post">
<div class="metabox-holder">

	<?php Util_Ui::postbox_header( esc_html__( 'Configuration', 'w3-total-cache' ), '', '' ); ?>

	<table class="form-table" id="w3tc-imageservice-config">
		<?php
		Util_Ui::config_item(
			array(
				'key'               => array(
					'imageservice',
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
					'imageservice',
					'auto',
				),
				'label'             => esc_html__( 'Auto-convert:', 'w3-total-cache' ),
				'control'           => 'radiogroup',
				'radiogroup_values' => array(
					'enabled'  => 'Enabled',
					'disabled' => 'Disabled',
				),
				'description'       =>esc_html__( 'Auto-convert images on upload.', 'w3-total-cache' ),
			)
		);
		?>
	</table>

	<?php
	Util_Ui::button_config_save( 'extension_imageservice_configuration' );
	Util_Ui::postbox_footer();

	Util_Ui::postbox_header( esc_html__( 'Tools', 'w3-total-cache' ), '', '' );
	?>

	<table class="form-table" id="w3tc-imageservice-tools">
	<?php
		Util_Ui::config_item(
			array(
				'key'         => null,
				'label'       => esc_html__( 'Convert all images:', 'w3-total-cache' ),
				'label_class' => 'w3tc-imageservice-all',
				'control'     => 'button',
				'none_label'  => 'Convert All',
				'description' => esc_html__( 'Convert all images in the media library.', 'w3-total-cache' ),
			)
		);

		Util_Ui::config_item(
			array(
				'key'         => null,
				'label'       => esc_html__( 'Revert all images:', 'w3-total-cache' ),
				'label_class' => 'w3tc-imageservice-revertall',
				'control'     => 'button',
				'none_label'  => 'Revert All',
				'description' => esc_html__( 'Revert all converted images in the media library.', 'w3-total-cache' ),
			)
		);
	?>
	</table>

	<?php
	Util_Ui::postbox_footer();

	Util_Ui::postbox_header(
		esc_html__( 'Statistics', 'w3-total-cache' ),
		'',
		'w3tc-imageservice-statistics'
	);
	?>

	<table class="form-table" id="w3tc-imageservice-stats">
		<tr>
			<th><?php esc_html_e( 'Counts and filesizes by status:', 'w3-total-cache' ); ?></th>
			<td>
				<table id="w3tc-imageservice-counts">
					<tr>
						<td><?php esc_html_e( 'Total:', 'w3-total-cache' ); ?></td>
						<td id="w3tc-imageservice-total"><?php echo $counts['total']; ?></td>
						<td id="w3tc-imageservice-totalbytes"><?php echo size_format( $counts['totalbytes'], 2 ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Converted:', 'w3-total-cache' ); ?></td>
						<td id="w3tc-imageservice-converted"><?php echo $counts['converted']; ?></td>
						<td id="w3tc-imageservice-convertedbytes"><?php echo size_format( $counts['convertedbytes'], 2 ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Sending:', 'w3-total-cache' ); ?></td>
						<td id="w3tc-imageservice-sending"><?php echo $counts['sending']; ?></td>
						<td id="w3tc-imageservice-sendingbytes"><?php echo size_format( $counts['sendingbytes'], 2 ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Processing:', 'w3-total-cache' ); ?></td>
						<td id="w3tc-imageservice-processing"><?php echo $counts['processing']; ?></td>
						<td id="w3tc-imageservice-processingbytes"><?php echo size_format( $counts['processingbytes'], 2 ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Not converted:', 'w3-total-cache' ); ?></td>
						<td id="w3tc-imageservice-notconverted"><?php echo $counts['notconverted']; ?></td>
						<td id="w3tc-imageservice-notconvertedbytes"><?php echo size_format( $counts['notconvertedbytes'], 2 ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Unconverted:', 'w3-total-cache' ); ?></td>
						<td id="w3tc-imageservice-unconverted"><?php echo $counts['unconverted']; ?></td>
						<td id="w3tc-imageservice-unconvertedbytes"><?php echo size_format( $counts['unconvertedbytes'], 2 ); ?></td>
					</tr>
					<tr><td height="10"></td></tr>
					<tr>
						<td colspan="3"><input id="w3tc-imageservice-refresh" class="button" type="button" value="<?php esc_attr_e( 'Refresh', 'w3-total-cache' ) ?>" /></td>
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
