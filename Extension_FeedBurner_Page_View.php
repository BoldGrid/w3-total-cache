<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<p>
	Jump to:
	<a href="admin.php?page=w3tc_general"><?php esc_html_e( 'Main Menu', 'w3-total-cache' ); ?></a> |
	<a href="admin.php?page=w3tc_extensions"><?php esc_html_e( 'Extensions', 'w3-total-cache' ); ?></a>
</p>
<p>
	FeedBurner extension is currently 
	<?php
	if ( $config->is_extension_active_frontend( 'feedburner' ) ) {
		echo '<span class="w3tc-enabled">enabled</span>';
	} else {
		echo '<span class="w3tc-disabled">disabled</span>';
	}
	?>
	.
<p>

<div class="metabox-holder">
	<?php Util_Ui::postbox_header( esc_html__( 'Google FeedBurner', 'w3-total-cache' ) ); ?>
	<table class="form-table">
		<?php
		Util_Ui::config_item(
			array(
				'key'         => array( 'feedburner', 'urls' ),
				'control'     => 'textarea',
				'label'       => wp_kses(
					sprintf(
						// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag.
						__(
							'Additional %1$sURL%2$ss:',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Uniform Resource Locator', 'w3-total-cache' ) . '">',
						'</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'description' => wp_kses(
					sprintf(
						// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag.
						__(
							'Specify any additional feed %1$sURL%2$ss to ping on FeedBurner.',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Uniform Resource Locator', 'w3-total-cache' ) . '">',
						'</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
			)
		);
		?>
	</table>
	<?php Util_Ui::button_config_save( 'extension_feedburner' ); ?>
	<?php Util_Ui::postbox_footer(); ?>
</div>
