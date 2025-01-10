<?php
/**
 * File: SystemOpCache_GeneralPage_View.php
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

Util_Ui::postbox_header_tabs(
	esc_html__( 'Opcode Cache', 'w3-total-cache' ),
	esc_html__(
		'Opcode cache is a powerful feature that enhances the performance of a WordPress website by caching 
			compiled PHP code. By storing pre-compiled code in memory, opcode cache eliminates the need for 
			repetitive interpretation and compilation of PHP files, resulting in significantly faster execution 
			times. Opcode cache reduces server load and improves response times, ultimately enhancing the 
			overall speed and responsiveness of your WordPress site. If opcode cache is available on the 
			hosting server it will automatically be selected in the dropdown and enabled.',
		'w3-total-cache'
	),
	'',
	'system_opcache'
);

?>
<table class="form-table">
	<?php
	Util_Ui::config_item(
		array(
			'key'              => 'opcache.engine',
			'label'            => esc_html__( 'Opcode Cache', 'w3-total-cache' ),
			'control'          => 'selectbox',
			'value'            => $opcode_engine,
			'selectbox_values' => array(
				'Not Available' => array(
					'disabled' => ( 'Not Available' !== $opcode_engine ),
					'label'    => esc_html__( 'Not Available', 'w3-total-cache' ),
				),
				'OPcache'       => array(
					'disabled' => ( 'OPcache' !== $opcode_engine ),
					'label'    => esc_html__( 'Opcode: Zend Opcache', 'w3-total-cache' ),
				),
				'APC'           => array(
					'disabled' => ( 'APC' !== $opcode_engine ),
					'label'    => esc_html__( 'Opcode: Alternative PHP Cache (APC / APCu)', 'w3-total-cache' ),
				),
			),
		)
	);
	Util_Ui::config_item(
		array(
			'key'            => 'opcache.validate_timestamps',
			'label'          => 'Validate timestamps:',
			'control'        => 'checkbox',
			'disabled'       => true,
			'value'          => $validate_timestamps,
			'checkbox_label' => esc_html__( 'Enable', 'w3-total-cache' ),
			'description'    => esc_html__( 'Once enabled, each file request will update the cache with the latest version. When this setting is off, the Opcode Cache will not check, instead PHP must be restarted in order for setting changes to be reflected.', 'w3-total-cache' ),
		)
	);
	?>
</table>

<?php Util_Ui::postbox_footer(); ?>
