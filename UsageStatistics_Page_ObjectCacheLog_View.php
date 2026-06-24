<?php
/**
 * File: UsageStatistics_Page_ObjectCacheLog_View.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die();
}

require W3TC_INC_DIR . '/options/common/header.php';
?>
<div class="metabox-holder">
	<?php Util_Ui::postbox_header( esc_html__( 'Usage Statistics', 'w3-total-cache' ) ); ?>

	<div style="float: right"><a href="admin.php?page=w3tc_stats"><?php esc_html_e( '&lt; Back To Statistics', 'w3-total-cache' ); ?></a></div>
	<h1><?php esc_html_e( 'Object Cache Calls', 'w3-total-cache' ); ?></h1>
	<p>
		<?php esc_html_e( 'Period', 'w3-total-cache' ); ?>
		<?php echo esc_html( $w3tc_result['date_min'] ); ?>
		-
		<?php echo esc_html( $w3tc_result['date_max'] ); ?>
	</p>

	<table style="width: 100%">
		<tr>
			<td><?php $this->sort_link( $w3tc_result, 'Group', 'group' ); ?></td>
			<td><?php $this->sort_link( $w3tc_result, 'Calls', 'count_total' ); ?></td>
			<td><?php $this->sort_link( $w3tc_result, 'Get', 'count_get_total' ); ?></td>
			<td><?php $this->sort_link( $w3tc_result, 'Cache Hits', 'count_get_hit' ); ?></td>
			<td><?php $this->sort_link( $w3tc_result, 'Set', 'count_set' ); ?></td>
			<td><?php $this->sort_link( $w3tc_result, 'Total processed time (ms)', 'sum_time_ms' ); ?></td>
			<td><?php $this->sort_link( $w3tc_result, 'Total size transfeffed', 'sum_size' ); ?></td>
			<td><?php $this->sort_link( $w3tc_result, 'Avg Size', 'avg_size' ); ?></td>
		</tr>
	<?php foreach ( $w3tc_result['items'] as $w3tc_i ) : ?>
		<tr>
			<td><?php echo esc_html( $w3tc_i['group'] ); ?></td>
			<td><?php echo esc_html( $w3tc_i['count_total'] ); ?></td>
			<td><?php echo esc_html( $w3tc_i['count_get_total'] ); ?></td>
			<td><?php echo esc_html( $w3tc_i['count_get_hit'] ); ?></td>
			<td><?php echo esc_html( $w3tc_i['count_set'] ); ?></td>
			<td><?php echo esc_html( $w3tc_i['sum_time_ms'] ); ?></td>
			<td><?php echo 0 === $w3tc_i['sum_size'] ? 'n/a' : esc_html( $w3tc_i['sum_size'] ); ?></td>
			<td><?php echo 0 === $w3tc_i['avg_size'] ? 'n/a' : esc_html( $w3tc_i['avg_size'] ); ?></td>
		</tr>
	<?php endforeach ?>
	</table>

	<?php Util_Ui::postbox_footer(); ?>
</div>
