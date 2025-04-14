<?php
/**
 * File: Extension_AlwaysCached_Page_View_Exclusions.php
 *
 * Render the AlwaysCached settings page - exclusions.
 *
 * @since 2.8.0
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$c                = Dispatcher::config();
$pgcache_disabled = ! $c->get_boolean( 'pgcache.enabled' )
?>
<div class="metabox-holder">
	<?php Util_Ui::postbox_header( esc_html__( 'Exclusions', 'w3-total-cache' ), '', 'exclusions' ); ?>
	<table class="form-table">
		<?php
		Util_Ui::config_item(
			array(
				'key'         => array(
					'alwayscached',
					'exclusions',
				),
				'control'     => 'textarea',
				'label'       => esc_html__( 'Exclusions:', 'w3-total-cache' ),
				'description' => esc_html__( 'URLs defined here will be excluded from the Always Cached process and will behave normally in that updates will invalidate relevent cache entries rather than be added to the queue. Specify one URL per line. These can be absolute or releative, and can include wildcards.', 'w3-total-cache' ),
				'disabled'    => $pgcache_disabled,
			)
		);
		?>
	</table>
	<?php Util_Ui::postbox_footer(); ?>
</div>
