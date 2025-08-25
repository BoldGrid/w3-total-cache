<?php
/**
 * File: Extension_AiCrawler_Page_View_Queue_List.php
 *
 * Render the AI Crawler queue list via AJAX.
 *
 * @package W3TC
 * @since	X.X.X
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<div class="w3tc-queue-summary variant-cards" aria-label="<?php esc_attr_e( 'Queue summary', 'w3-total-cache' ); ?>">
	<h3 class="w3tc-queue-summary__title"><?php esc_html_e( 'Summary', 'w3-total-cache' ); ?></h3>
	<ul class="w3tc-queue-summary__stats" role="list">
		<li class="stat is-queued"	 aria-label="<?php esc_attr_e( 'Queued', 'w3-total-cache' ); ?>">
			<span class="stat__value"><?php echo intval( $counts['queued'] ); ?></span>
			<span class="stat__label"><?php esc_html_e( 'Queued', 'w3-total-cache' ); ?></span>
		</li>
		<li class="stat is-processing" aria-label="<?php esc_attr_e( 'Processing', 'w3-total-cache' ); ?>">
			<span class="stat__value"><?php echo intval( $counts['processing'] ); ?></span>
			<span class="stat__label"><?php esc_html_e( 'Processing', 'w3-total-cache' ); ?></span>
		</li>
		<li class="stat is-complete" aria-label="<?php esc_attr_e( 'Complete', 'w3-total-cache' ); ?>">
			<span class="stat__value"><?php echo intval( $counts['complete'] ); ?></span>
			<span class="stat__label"><?php esc_html_e( 'Complete', 'w3-total-cache' ); ?></span>
		</li>
		<li class="stat is-error" aria-label="<?php esc_attr_e( 'Error', 'w3-total-cache' ); ?>">
			<span class="stat__value"><?php echo intval( $counts['error'] ); ?></span>
			<span class="stat__label"><?php esc_html_e( 'Error', 'w3-total-cache' ); ?></span>
		</li>
	</ul>
	<p class="w3tc-queue-summary__total">
		<?php /* translators: %d: total number of items. */ ?>
		<?php printf( esc_html__( 'Total items: %d', 'w3-total-cache' ), intval( $counts['total'] ) ); ?>
	</p>
	<p class="w3tc-queue-summary__last-run">
		<?php /* translators: %s: last run timestamp. */ ?>
		<?php printf( esc_html__( 'Last run: %s', 'w3-total-cache' ), esc_html( $last_run_formatted ) ); ?>
	</p>
</div>
<table class="widefat fixed">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Time', 'w3-total-cache' ); ?></th>
			<th><?php esc_html_e( 'ID', 'w3-total-cache' ); ?></th>
			<th><?php esc_html_e( 'Name', 'w3-total-cache' ); ?></th>
			<th><?php esc_html_e( 'URL', 'w3-total-cache' ); ?></th>
			<th><?php esc_html_e( 'Status', 'w3-total-cache' ); ?></th>
			<th><?php esc_html_e( 'Message', 'w3-total-cache' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( ! empty( $queue_posts ) ) : ?>
			<?php foreach ( $queue_posts as $queue_post_id ) : ?>
				<?php
				$queue_title   = get_the_title( $queue_post_id );
				$queue_url	   = wp_make_link_relative( get_permalink( $queue_post_id ) );
				$queue_status  = get_post_meta( $queue_post_id, Extension_AiCrawler_Markdown::META_STATUS, true );
				$queue_message = get_post_meta( $queue_post_id, Extension_AiCrawler_Markdown::META_ERROR_MESSAGE, true );
				$status_class  = 'w3tc-queue-status-' . ( $queue_status ? $queue_status : 'unknown' );
				$timestamp	   = get_post_meta( $queue_post_id, Extension_AiCrawler_Markdown::META_TIMESTAMP, true );
				?>
				<tr class="<?php echo esc_attr( $status_class ); ?>">
					<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) ); ?></td>
					<td><?php echo esc_html( $queue_post_id ); ?></td>
					<td><?php echo esc_html( $queue_title ); ?></td>
					<td><?php echo esc_html( $queue_url ); ?></td>
					<td><?php echo esc_html( $queue_status ); ?></td>
					<td><?php echo esc_html( $queue_message ); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php else : ?>
			<tr>
				<td colspan="5"><?php esc_html_e( 'No items found.', 'w3-total-cache' ); ?></td>
			</tr>
		<?php endif; ?>
	</tbody>
</table>
<?php if ( $queue_pages > 1 ) : ?>
	<div class="tablenav">
		<div class="tablenav-pages">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'		=> add_query_arg( 'queue_page', '%#%' ),
						'format'	=> '',
						'current'	=> $queue_paged,
						'total'		=> $queue_pages,
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
					)
				)
			);
			?>
		</div>
	</div>
<?php endif; ?>

