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
					<?php echo esc_html( $r['url'] ); ?>
				</td>
				<td>
					<?php echo esc_html( $r['requests_count'] ); ?>
				</td>
			</tr>
		<?php endforeach ?>
	</tbody>
</table>
