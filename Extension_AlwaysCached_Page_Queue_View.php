<?php
/**
 * File: Extension_AlwaysCached_Page_Queue.php
 *
 * Controller for AlwaysCached queue.
 *
 * @since 2.5.1
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$queue_mode = Util_Request::get_string( 'mode' );
$rows       = Extension_AlwaysCached_Queue::rows( $queue_mode );

?>
<table>
	<thead>
		<tr>
			<th>URL</th>
			<th>Rebuild Requests</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $rows as $r ) : ?>
			<tr>
				<td style="white-space: nowrap">
					<?php if( substr( $r['key'], 0, 1 ) == ':' ): ?>
						<?php if ( $r['key'] == ':flush_group.regenerate'): ?>
							Command: Queue Regeneration on Purge all
						<?php elseif ( $r['key'] == ':flush_group.remainder'): ?>
							Command: Purge Remainder after Purge all
						<?php else: ?>
							command <?php echo esc_html( $r['key'] ); ?>
						<?php endif ?>
					<?php else: ?>
						<?php echo esc_html( $r['url'] ); ?>
					<?php endif ?>
				</td>
				<td>
					<?php echo esc_html( $r['requests_count'] ); ?>
				</td>
			</tr>
		<?php endforeach ?>
	</tbody>
</table>
