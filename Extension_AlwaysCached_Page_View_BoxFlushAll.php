<?php
/**
 * File: Extension_AlwaysCached_Page_View_BoxFlushAll.php
 *
 * Render the AlwaysCached settings page - flush all box.
 *
 * @since 2.8.0
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$c                 = Dispatcher::config();
$pgcache_disabled  = ! $c->get_boolean( 'pgcache.enabled' );
$flushall_disabled = ! $c->get_boolean( array( 'alwayscached', 'flush_all' ) );

?>
<div class="metabox-holder">
	<?php Util_Ui::postbox_header( esc_html__( 'Purge All Behavior', 'w3-total-cache' ), '', 'purge-all-behavior' ); ?>
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
				'description'    => esc_html__( 'With this enabled, the "Purge All Caches" action will instead queue items based on the below settings. If this is NOT enabled, the "Flush All" action will purge all caches and clear all queue entries as pending changes will be applied. Note that enabling this can cause the "Flush All" action to take longer, especially if the "Number of Latests Pages/Posts" are set at a high value.', 'w3-total-cache' ),
				'disabled'       => $pgcache_disabled,
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
				'description'    => esc_html__( 'This setting controls whether the homepage should be added to the queue when a flush all operation occurs.', 'w3-total-cache' ),
				'checkbox_label' => esc_html__( 'Enable', 'w3-total-cache' ),
				'control'        => 'checkbox',
				'disabled'       => $pgcache_disabled || $flushall_disabled,
			)
		);

		Util_Ui::config_item(
			array(
				'key'          => array(
					'alwayscached',
					'flush_all_posts_count',
				),
				'label'        => esc_html__( 'Number of Latest Posts:', 'w3-total-cache' ),
				'description'  => esc_html__( 'This setting controls the number of latest posts that will be added to the queue when a flush all operation occurs. If this field is left blank it will default to the latest 15 posts.', 'w3-total-cache' ),
				'control'      => 'textbox',
				'textbox_type' => 'number',
				'default'      => '10',
				'disabled'     => $pgcache_disabled || $flushall_disabled,
			)
		);

		Util_Ui::config_item(
			array(
				'key'          => array(
					'alwayscached',
					'flush_all_pages_count',
				),
				'label'        => esc_html__( 'Number of Latest Pages:', 'w3-total-cache' ),
				'description'  => esc_html__( 'This setting controls the number of latest pages that will be added to the queue when a flush all operation occurs. If this field is left blank it will default to the latest 15 pages.', 'w3-total-cache' ),
				'control'      => 'textbox',
				'textbox_type' => 'number',
				'default'      => '10',
				'disabled'     => $pgcache_disabled || $flushall_disabled,
			)
		);

		?>
	</table>
	<?php Util_Ui::postbox_footer(); ?>
</div>
