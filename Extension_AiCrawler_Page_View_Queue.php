<?php
/**
 * File: Extension_AiCrawler_Page_View_Queue.php
 *
 * Render the AI Crawler queue section.
 *
 * @package W3TC
 * @since   X.X.X
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
		die();
}

$counts         = Extension_AiCrawler_Markdown::get_status_counts();
$queue_paged    = isset( $_GET['queue_page'] ) ? absint( $_GET['queue_page'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$queue_per_page = 20;
$queue_items    = Extension_AiCrawler_Markdown::get_queue_items( $queue_paged, $queue_per_page );
$queue_posts    = $queue_items['items'];
$queue_total    = $queue_items['total'];
$queue_pages    = max( 1, ceil( $queue_total / $queue_per_page ) );
?>
<div class="metabox-holder">
	<?php Util_Ui::postbox_header( esc_html__( 'Queue', 'w3-total-cache' ), '', 'queue' ); ?>
	<p>
		<?php
		/* translators: %d: total number of items. */
		printf( esc_html__( 'Total items: %d', 'w3-total-cache' ), intval( $counts['total'] ) );
		?>
	</p>
	<ul>
		<li>
			<?php
			/* translators: %d: number of queued items. */
			printf( esc_html__( 'Queued: %d', 'w3-total-cache' ), intval( $counts['queued'] ) );
			?>
		</li>
		<li>
			<?php
			/* translators: %d: number of processing items. */
			printf( esc_html__( 'Processing: %d', 'w3-total-cache' ), intval( $counts['processing'] ) );
			?>
		</li>
		<li>
			<?php
			/* translators: %d: number of completed items. */
			printf( esc_html__( 'Complete: %d', 'w3-total-cache' ), intval( $counts['complete'] ) );
			?>
		</li>
		<li>
			<?php
			/* translators: %d: number of error items. */
			printf( esc_html__( 'Error: %d', 'w3-total-cache' ), intval( $counts['error'] ) );
			?>
		</li>
	</ul>
	<table class="widefat fixed striped">
		<thead>
			<tr>
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
					$queue_url     = wp_make_link_relative( get_permalink( $queue_post_id ) );
					$queue_status  = get_post_meta( $queue_post_id, Extension_AiCrawler_Markdown::META_STATUS, true );
					$queue_message = get_post_meta( $queue_post_id, Extension_AiCrawler_Markdown::META_ERROR_MESSAGE, true );
					?>
					<tr>
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
							'base'      => add_query_arg( 'queue_page', '%#%' ),
							'format'    => '',
							'current'   => $queue_paged,
							'total'     => $queue_pages,
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
						)
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>
	<?php Util_Ui::postbox_footer(); ?>
</div>
