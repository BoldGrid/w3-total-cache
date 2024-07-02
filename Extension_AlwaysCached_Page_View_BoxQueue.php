<?php
/**
 * File: Extension_AlwaysCached_Page_ViewBoxQueue.php
 *
 * Render the AlwaysCached settings page - queue box.
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$count_pending   = Extension_AlwaysCached_Queue::row_count_pending();
$count_postponed = Extension_AlwaysCached_Queue::row_count_postponed();

$time_lastrun = get_option( 'w3tc_alwayscached_worker_timestamp' );
?>
<div class="metabox-holder">
	<?php Util_Ui::postbox_header( esc_html__( 'Queue', 'w3-total-cache' ), '', 'queue' ); ?>
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
					<a href="#" class="w3tc_alwayscached_queue" data-mode="pending">
						<?php esc_html_e( 'View', 'w3-total-cache' ); ?>
					</a>
					<section class="w3tc_alwayscached_queue_section"></section>
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
					<a href="#" class="w3tc_alwayscached_queue" data-mode="postponed">
						<?php esc_html_e( 'View', 'w3-total-cache' ); ?>
					</a>
					<section class="w3tc_alwayscached_queue_section"></section>
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
				<input id="w3tc_alwayscached_process" type="submit" name="w3tc_alwayscached_process"
					value="<?php esc_html_e( 'Regenerate All', 'w3-total-cache' ); ?>" class="button" />
				<input id="w3tc_alwayscached_empty" type="submit" name="w3tc_alwayscached_empty"
					value="<?php esc_html_e( 'Clear Queue', 'w3-total-cache' ); ?>" class="button" />
			</td>
		</tr>
	</table>
	<?php Util_Ui::postbox_footer(); ?>
</div>
