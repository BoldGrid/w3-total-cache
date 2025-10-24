<?php
/**
 * File: Extension_AlwaysCached_Page_ViewBoxQueue.php
 *
 * Render the AlwaysCached settings page - queue box.
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
$pgcache_disabled = ! $c->get_boolean( 'pgcache.enabled' );

$count_pending   = Extension_AlwaysCached_Queue::row_count_pending();
$count_postponed = Extension_AlwaysCached_Queue::row_count_postponed();

$time_lastrun = get_option( 'w3tc_alwayscached_worker_timestamp' );
?>
<div class="metabox-holder">
	<?php Util_Ui::postbox_header( esc_html__( 'Queue', 'w3-total-cache' ), '', 'queue' ); ?>
	<p>
		<?php esc_html_e( 'The "Pending in queue" represent pages/posts that have been updated but are still serving the pre-update cache entry.', 'w3-total-cache' ); ?>
	</p>
	<p>
		<?php esc_html_e( 'The "Postponed in queue" represent pages/posts that failed to process either due to an error or due to the queue processor exceeding its allocated time slot. These entries will be processed on the next scheduled queue processor execution.', 'w3-total-cache' ); ?>
	</p>
	<p>
		<?php esc_html_e( 'The "Last processed" represents the last time the queue processor was executed.', 'w3-total-cache' ); ?>
	</p>
	<p>
		<?php esc_html_e( 'When viewing the queue, each entry will have a circular arrow icon that can be clicked to manually regenerate the corresponding cache entry. Once this completes, the queue entry will be removed from the queue.', 'w3-total-cache' ); ?>
	</p>
	<table class="form-table">
		<tr>
			<th style="width: 300px;">
				<label>
					<?php esc_html_e( 'Pending in queue:', 'w3-total-cache' ); ?>
				</label>
			</th>
			<td>
				<?php echo esc_html( $count_pending ); ?>
				<?php if ( $count_pending > 0 ) : ?>
					&nbsp;
					<a href="#" class="w3tc-alwayscached-queue" data-mode="pending">
						<?php esc_html_e( 'View', 'w3-total-cache' ); ?>
					</a>
					<section class="w3tc-alwayscached-queue-section"></section>
				<?php endif ?>
			</td>
		</tr>
		<tr>
			<th>
				<label>
					<?php esc_html_e( 'Postponed in queue:', 'w3-total-cache' ); ?>
				</label>
			</th>
			<td>
				<?php echo esc_html( $count_postponed ); ?>
				<?php if ( $count_postponed > 0 ) : ?>
					&nbsp;
					<a href="#" class="w3tc-alwayscached-queue" data-mode="postponed">
						<?php esc_html_e( 'View', 'w3-total-cache' ); ?>
					</a>
					<section class="w3tc-alwayscached-queue-section"></section>
				<?php endif ?>
			</td>
		</tr>
		<tr>
			<th>
				<label>
					<?php esc_html_e( 'Last processed:', 'w3-total-cache' ); ?>
				</label>
			</th>
			<td>
				<?php
				if ( empty( $time_lastrun ) ) {
					esc_html_e( 'n/a', 'w3-total-cache' );
				} else {
					echo wp_kses(
						sprintf(
							// translators: 1 opening HTML span tag, 2 last queue run time, 3 closing HTML span tag.
							__(
								'%1$s%2$s ago%3$s',
								'w3-total-cache'
							),
							'<span title="' . esc_html( $time_lastrun ) . '">',
							esc_html( human_time_diff( strtotime( $time_lastrun ), time() ) ),
							'</span>'
						),
						array(
							'span' => array(
								'title' => array(),
							),
						)
					);
				}
				?>
			</td>
		</tr>
		<tr>
			<th></th>
			<td>
				<input id="w3tc-alwayscached-process" type="submit" name="w3tc_alwayscached_process"
					value="<?php esc_html_e( 'Regenerate All', 'w3-total-cache' ); ?>" class="button" <?php echo $pgcache_disabled ? 'disabled="disabled"' : ''; ?>/>
				<p>
					<?php esc_html_e( 'This button will manually trigger the queue processor to begin regenerating the cache entry for each item in the queue, thereby publishing all pending changes.', 'w3-total-cache' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th></th>
			<td>
				<input id="w3tc-alwayscached-empty" type="submit" name="w3tc_alwayscached_empty"
					value="<?php esc_html_e( 'Clear Queue', 'w3-total-cache' ); ?>" class="button" <?php echo $pgcache_disabled ? 'disabled="disabled"' : ''; ?>/>
				<p>
					<?php esc_html_e( 'This button removes all items in the queue. The pending changes for each removed item will not be published until the corresponding existing cache entry expires. Removed items can be re-added to the queue via further modifications to the item or a flush all caches operation with the "Queue Purge All Requests" option enabled.', 'w3-total-cache' ); ?>
				</p>
			</td>
		</tr>
	</table>
	<?php Util_Ui::postbox_footer(); ?>
</div>
