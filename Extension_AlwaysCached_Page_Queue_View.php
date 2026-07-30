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

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die();
}

$w3tc_queue_mode = Util_Request::get_string( 'mode' );
$w3tc_offset     = 0;
$w3tc_limit      = 15;
$w3tc_rows       = Extension_AlwaysCached_Queue::rows( $w3tc_queue_mode, $w3tc_offset, $w3tc_limit );

if ( 'postponed' === $w3tc_queue_mode ) {
	$w3tc_total_rows = Extension_AlwaysCached_Queue::row_count_postponed();
} else {
	$w3tc_total_rows = Extension_AlwaysCached_Queue::row_count_pending();
}

?>
<input type="text" class="w3tc-alwayscached-queue-filter" data-mode="<?php echo esc_attr( $w3tc_queue_mode ); ?>" name="search" placeholder="Search...">
<input class="button w3tc-alwayscached-queue-filter-submit" data-mode="<?php echo esc_attr( $w3tc_queue_mode ); ?>" type="submit" value="<?php esc_html_e( 'Search', 'w3-total-cache' ); ?>">
<table class="w3tc-alwayscached-queue-view-table" data-mode="<?php echo esc_attr( $w3tc_queue_mode ); ?>">
	<thead>
		<tr>
			<th class="th-full"></th>
			<th class="th-full"><?php esc_html_e( 'URL', 'w3-total-cache' ); ?></th>
			<th class="th-full"><?php esc_html_e( 'Rebuild Requests', 'w3-total-cache' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $w3tc_rows as $w3tc_r ) : ?>
			<tr>
				<td>
					<span class="w3tc-alwayscached-queue-item dashicons dashicons-update" title="<?php esc_attr_e( 'Regenerate', 'w3-total-cache' ); ?>" data-url="<?php echo esc_url( $w3tc_r['url'] ); ?>"></span>
				</td>
				<td style="white-space: nowrap">
					<?php echo esc_html( $w3tc_r['url'] ); ?>
				</td>
				<td>
					<?php echo esc_html( $w3tc_r['requests_count'] ); ?>
				</td>
			</tr>
		<?php endforeach ?>
	</tbody>
</table>
<div class="w3tc-alwayscached-queue-view-pagination-container" data-mode="<?php echo esc_attr( $w3tc_queue_mode ); ?>">
	<span>Pages: </span>
	<?php
	$w3tc_total_pages = ceil( $w3tc_total_rows / $w3tc_limit );

	if ( 10 >= $w3tc_total_pages ) {
		for ( $w3tc_i = 1; $w3tc_i <= $w3tc_total_pages; $w3tc_i++ ) {
			?>
			<a href="#" class="w3tc-alwayscached-queue-view-pagination-page<?php echo ( 1 === $w3tc_i ) ? ' active' : ''; ?>" data-mode="<?php echo esc_attr( $w3tc_queue_mode ); ?>" data-page="<?php echo esc_attr( $w3tc_i ); ?>"><?php echo esc_html( $w3tc_i ); ?></a>
			<?php
		}
	} else {
		for ( $w3tc_i = 1; $w3tc_i <= 9; $w3tc_i++ ) {
			?>
			<a href="#" class="w3tc-alwayscached-queue-view-pagination-page<?php echo ( 1 === $w3tc_i ) ? ' active' : ''; ?>" data-mode="<?php echo esc_attr( $w3tc_queue_mode ); ?>" data-page="<?php echo esc_attr( $w3tc_i ); ?>"><?php echo esc_html( $w3tc_i ); ?></a>
			<?php
		}
		?>
		<span>...</span>
		<a href="#" class="w3tc-alwayscached-queue-view-pagination-page" data-mode="<?php echo esc_attr( $w3tc_queue_mode ); ?>" data-page="<?php echo esc_attr( $w3tc_total_pages ); ?>"><?php echo esc_html( $w3tc_total_pages ); ?></a>
		<br>
		<input type="number" min="1" max="<?php echo esc_attr( $w3tc_total_pages ); ?>" class="w3tc-alwayscached-queue-view-pagination-page-input" data-mode="<?php echo esc_attr( $w3tc_queue_mode ); ?>" name="page-jump" placeholder="Page #">
		<input class="button w3tc-alwayscached-queue-view-pagination-page-input-submit" data-mode="<?php echo esc_attr( $w3tc_queue_mode ); ?>" type="submit" value="<?php esc_html_e( 'Go', 'w3-total-cache' ); ?>">
		<?php
	}
	?>
</div>
