<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

include W3TC_INC_DIR . '/options/common/header.php';
?>
<div class="metabox-holder">
	<?php Util_Ui::postbox_header( __( 'Usage Statistics', 'w3-total-cache' ) ); ?>

	<div style="float: right"><a href="admin.php?page=w3tc_stats">&lt; Back To Statistics</a></div>
	<h1>Database Queries</h1>
	<p>
		Period
		<?php echo $result['date_min'] ?>
		-
		<?php echo $result['date_max'] ?>
	</p>

	<table style="width: 100%">
		<tr>
			<td><?php $this->sort_link( $result, 'Query', 'query' ) ?></td>
			<td><?php $this->sort_link( $result, 'Count', 'count_total' ) ?></td>
			<td><?php $this->sort_link( $result, 'Cache Hits', 'count_hit' ) ?></td>
			<td><?php $this->sort_link( $result, 'Total processed time (ms)', 'sum_time_ms' ) ?></td>
			<td><?php $this->sort_link( $result, 'Avg Processed time (ms)', 'avg_time_ms' ) ?></td>
			<td><?php $this->sort_link( $result, 'Avg Size', 'avg_size' ) ?></td>
		</tr>
	<?php foreach ($result['items'] as $i): ?>
		<tr>
			<td title="Reject reasons: <?php echo esc_attr( implode( ',', $i['reasons'] ) ) ?>"><?php echo esc_html($i['query']) ?></td>
			<td><?php echo esc_html($i['count_total']) ?></td>
			<td><?php echo esc_html($i['count_hit']) ?></td>
			<td><?php echo esc_html($i['sum_time_ms']) ?></td>
			<td><?php echo esc_html($i['avg_time_ms']) ?></td>
			<td><?php echo $i['avg_size'] == 0 ? 'n/a' : esc_html($i['avg_size']) ?></td>
		</tr>
	<?php endforeach ?>
	</table>

	<?php Util_Ui::postbox_footer(); ?>
</div>
