<?php
/**
 * File: Extension_ImageService_Widget_View.php
 *
 * @package W3TC
 *
 * @since 2.7.0
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$config = Dispatcher::config();

if ( $config->is_extension_active( 'imageservice' ) ) {
	?>
	<div id="w3tc-webp-widget-stats-container">
		<h3 class="w3tc-webp-widget-stats-title"><?php esc_html_e( 'Status', 'w3-total-cache' ); ?></h3>
		<div id="counts_chart"></div>
		<h3 class="w3tc-webp-widget-stats-title"><?php esc_html_e( 'API Use Limits', 'w3-total-cache' ); ?></h3>
		<div id="api_charts"></div>
	</div>
	<?php
} else {
	?>
	<div id="w3tc-webp-widget-stats-container" class="w3tc-webp-widget-stats-inactive">
		<?php
		echo wp_kses(
			Util_Ui::button_link(
				__( 'Enable WebP Converter', 'w3-total-cache' ),
				Util_Ui::admin_url( 'admin.php?page=w3tc_general&w3tc_message=65b942a33d66c#image_service' ),
				false,
				'button-primary'
			),
			array(
				'input' => array(
					'type'     => array(),
					'name'     => array(),
					'class'    => array(),
					'value'    => array(),
					'onclick'  => array(),
					'data-src' => array(),
				),
			)
		);
		?>
	</div>
	<?php
}
