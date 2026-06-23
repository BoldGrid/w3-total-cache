<?php
/**
 * File: UsageStatistics_Page_PageCacheRequests_View.php
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended
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
	<h1><?php esc_html_e( 'Page Cache Reject Requests for ', 'w3-total-cache' ); ?><?php echo esc_html( isset( $_REQUEST['status_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status_name'] ) ) : '' ); ?></h1>
	<p>
		<?php esc_html_e( 'Period', 'w3-total-cache' ); ?>
		<?php echo esc_html( $w3tc_result['date_min'] ); ?>
		-
		<?php echo esc_html( $w3tc_result['date_max'] ); ?>
	</p>

	<table style="width: 100%">
		<tr>
			<td><?php $this->sort_link( $w3tc_result, 'URI', 'uri' ); ?></td>
			<td><?php $this->sort_link( $w3tc_result, 'Count', 'count' ); ?></td>
			<td><?php $this->sort_link( $w3tc_result, 'Total processed time (ms)', 'sum_time_ms' ); ?></td>
			<td><?php $this->sort_link( $w3tc_result, 'Avg Processed time (ms)', 'avg_time_ms' ); ?></td>
			<td><?php $this->sort_link( $w3tc_result, 'Avg Size', 'avg_size' ); ?></td>
		</tr>
	<?php foreach ( $w3tc_result['items'] as $w3tc_i ) : ?>
		<tr>
			<td title="<?php echo esc_attr__( 'Reject reasons: ', 'w3-total-cache' ) . esc_attr( implode( ',', $w3tc_i['reasons'] ) ); ?>"><?php echo esc_html( $w3tc_i['uri'] ); ?></td>
			<td><?php echo esc_html( $w3tc_i['count'] ); ?></td>
			<td><?php echo esc_html( $w3tc_i['sum_time_ms'] ); ?></td>
			<td><?php echo esc_html( $w3tc_i['avg_time_ms'] ); ?></td>
			<td><?php echo 0 === $w3tc_i['avg_size'] ? 'n/a' : esc_html( $w3tc_i['avg_size'] ); ?></td>
		</tr>
	<?php endforeach ?>
	</table>

	<?php Util_Ui::postbox_footer(); ?>
</div>
