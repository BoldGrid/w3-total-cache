<?php
/**
 * File: Extension_AlwaysCached_Page_View_BoxCron.php
 *
 * Render the AlwaysCached settings page - cron box.
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$c           = Dispatcher::config();
$wp_disabled = ! $c->get_boolean( array( 'alwayscached', 'wp_cron' ) );
?>
<div class="metabox-holder">
	<?php Util_Ui::postbox_header( esc_html__( 'Cron', 'w3-total-cache' ), '', 'cron' ); ?>
	<table class="form-table">
		<?php

		Util_Ui::config_item(
			array(
				'key'            => array(
					'alwayscached',
					'wp_cron',
				),
				'label'          => esc_html__( 'Enable WP-Cron Event', 'w3-total-cache' ),
				'checkbox_label' => esc_html__( 'Enable', 'w3-total-cache' ),
				'control'        => 'checkbox',
				'description'    => esc_html__( 'Enabling this will schedule a WP-Cron event that will process the queue and regenerate cache files. If you prefer to use a system cron job instead of WP-Cron, you can schedule the following command to run at your desired interval: "wp w3tc alwayscached_process".', 'w3-total-cache' ),
			)
		);

		Util_Ui::config_item(
			array(
				'key'              => array(
					'alwayscached',
					'wp_cron_interval',
				),
				'label'            => esc_html__( 'Interval', 'w3-total-cache' ),
				'control'          => 'selectbox',
				'selectbox_values' => array(
					''           => esc_html__( 'Never', 'w3-total-cache' ),
					'hourly'     => esc_html__( 'Hourly', 'w3-total-cache' ),
					'twicedaily' => esc_html__( 'Twice Daily', 'w3-total-cache' ),
					'daily'      => esc_html__( 'Daily', 'w3-total-cache' ),
					'weekly'     => esc_html__( 'Weekly', 'w3-total-cache' ),
				),
				'disabled'         => $wp_disabled,
			)
		);
		?>
	</table>
	<?php Util_Ui::postbox_footer(); ?>
</div>
