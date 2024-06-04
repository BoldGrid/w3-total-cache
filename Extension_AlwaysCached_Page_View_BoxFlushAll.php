<?php
/**
 * File: Extension_AlwaysCached_Page_View.php
 *
 * Render the AlwaysCached settings page - flush all box.
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$c        = Dispatcher::config();
$disabled = ! $c->get_boolean( array( 'alwayscached', 'flush_all' ) );

?>
<div class="metabox-holder">
	<?php Util_Ui::postbox_header( esc_html__( 'Purge All', 'w3-total-cache' ), '', 'credentials' ); ?>
	<table class="form-table">
		<?php

		Util_Ui::config_item(
			array(
				'key'            => array(
					'alwayscached',
					'flush_all',
				),
				'label'          => esc_html__( 'Queue Purge All Requests', 'w3-total-cache' ),
				'checkbox_label' => esc_html__( 'Enable', 'w3-total-cache' ),
				'control'        => 'checkbox',
				'description'    => esc_html__( 'With this enabled, the "Purge All Caches" action will instead queue items based on the below settings. If this is NOT enabled, the "Flush All" action will purge all caches and clear all queue entries as pending changes will be applied.', 'w3-total-cache' ),
			)
		);

		?>
		<tr>
			<th></th>
			
			<td><strong><?php esc_html_e( 'When Queue is processed, regenerate:', 'w3-total-cache' ); ?></strong></td>
		</tr>
		<?php

		Util_Ui::config_item(
			array(
				'key'            => array(
					'alwayscached',
					'flush_all_home',
				),
				'label'          => esc_html__( 'Homepage', 'w3-total-cache' ),
				'checkbox_label' => esc_html__( 'Enable', 'w3-total-cache' ),
				'control'        => 'checkbox',
				'disabled'       => $disabled,
			)
		);

		Util_Ui::config_item(
			array(
				'key'         => array(
					'alwayscached',
					'flush_all_posts_count',
				),
				'label'       => esc_html__( 'Number of Latest Posts:', 'w3-total-cache' ),
				'description' => esc_html__( 'Number of latest posts to regenerate', 'w3-total-cache' ),
				'control'     => 'textbox',
				'disabled'    => $disabled,
			)
		);

		Util_Ui::config_item(
			array(
				'key'         => array(
					'alwayscached',
					'flush_all_pages_count',
				),
				'label'       => esc_html__( 'Number of Latest Pages:', 'w3-total-cache' ),
				'description' => esc_html__( 'Number of latest pages to regenerate', 'w3-total-cache' ),
				'control'     => 'textbox',
				'disabled'    => $disabled,
			)
		);

		?>
	</table>
	<?php Util_Ui::postbox_footer(); ?>
</div>
