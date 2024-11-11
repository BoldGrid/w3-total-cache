<?php
/**
 * File: Extension_AlwaysCached_Page_Queue.php
 *
 * Controller for AlwaysCached queue.
 *
 * @since 2.8.0
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$queue_mode = Util_Request::get_string( 'mode' );
$offset     = 0;
$limit      = 15;
$rows       = Extension_AlwaysCached_Queue::rows( $queue_mode, $offset, $limit );

if ( 'postponed' === $queue_mode ) {
	$total_rows = Extension_AlwaysCached_Queue::row_count_postponed();
} else {
	$total_rows = Extension_AlwaysCached_Queue::row_count_pending();
}

?>
<input type="text" class="w3tc-alwayscached-queue-filter" data-mode="<?php echo esc_attr( $queue_mode ); ?>" name="search" placeholder="Search...">
<input class="button w3tc-alwayscached-queue-filter-submit" data-mode="<?php echo esc_attr( $queue_mode ); ?>" type="submit" value="<?php esc_html_e( 'Search', 'w3-total-cache' ); ?>">
<table class="w3tc-alwayscached-queue-view-table" data-mode="<?php echo esc_attr( $queue_mode ); ?>">
	<thead>
		<tr>
			<th class="th-full"></th>
			<th class="th-full"><?php esc_html_e( 'URL', 'w3-total-cache' ); ?></th>
			<th class="th-full"><?php esc_html_e( 'Rebuild Requests', 'w3-total-cache' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $rows as $r ) : ?>
			<tr>
				<td>
					<span class="w3tc-alwayscached-queue-item dashicons dashicons-update" title="<?php esc_attr_e( 'Regenerate', 'w3-total-cache' ); ?>" data-url="<?php echo esc_url( $r['url'] ); ?>"></span>
				</td>
				<td style="white-space: nowrap">
					<?php echo esc_html( $r['url'] ); ?>
				</td>
				<td>
					<?php echo esc_html( $r['requests_count'] ); ?>
				</td>
			</tr>
		<?php endforeach ?>
	</tbody>
</table>
<div class="w3tc-alwayscached-queue-view-pagination-container" data-mode="<?php echo esc_attr( $queue_mode ); ?>">
	<span>Pages: </span>
	<?php
	$total_pages = ceil( $total_rows / $limit );

	if ( 10 >= $total_pages ) {
		for ( $i = 1; $i <= $total_pages; $i++ ) {
			?>
			<a href="#" class="w3tc-alwayscached-queue-view-pagination-page<?php echo ( 1 === $i ) ? ' active' : ''; ?>" data-mode="<?php echo esc_attr( $queue_mode ); ?>" data-page="<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?></a>
			<?php
		}
	} else {
		for ( $i = 1; $i <= 9; $i++ ) {
			?>
			<a href="#" class="w3tc-alwayscached-queue-view-pagination-page<?php echo ( 1 === $i ) ? ' active' : ''; ?>" data-mode="<?php echo esc_attr( $queue_mode ); ?>" data-page="<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?></a>
			<?php
		}
		?>
		<span>...</span>
		<a href="#" class="w3tc-alwayscached-queue-view-pagination-page" data-mode="<?php echo esc_attr( $queue_mode ); ?>" data-page="<?php echo esc_attr( $total_pages ); ?>"><?php echo esc_html( $total_pages ); ?></a>
		<br>
		<input type="number" min="1" max="<?php echo esc_attr( $total_pages ); ?>" class="w3tc-alwayscached-queue-view-pagination-page-input" data-mode="<?php echo esc_attr( $queue_mode ); ?>" name="page-jump" placeholder="Page #">
		<input class="button w3tc-alwayscached-queue-view-pagination-page-input-submit" data-mode="<?php echo esc_attr( $queue_mode ); ?>" type="submit" value="<?php esc_html_e( 'Go', 'w3-total-cache' ); ?>">
		<?php
	}
	?>
</div>
