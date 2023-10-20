<?php
/**
 * File: Extension_AlwaysCached_Page_View.php
 *
 * Render the AlwaysCached settings page.
 *
 * @since 2.5.1
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
<p>
	<?php esc_html_e( 'AlwaysCached extension is currently ', 'w3-total-cache' ); ?>
	<?php
	if ( Extension_AlwaysCached_Plugin::is_enabled() ) {
		echo '<span class="w3tc-enabled">' . esc_html__( 'enabled.', 'w3-total-cache' ) . '</span>';
	} else {
		echo '<span class="w3tc-disabled">' . esc_html__( 'disabled.', 'w3-total-cache' ) . '</span>';
	}
	?>
<p>
<p>
	<?php esc_html_e( 'The Always Cached extension prevents page/post updates from clearing corresponding cache entries and instead adds them to a queue that can be manually cleared or scheduled to clear via cron.', 'w3-total-cache' ); ?>
</p>
<p>
	<?php esc_html_e( 'The "Pending pages" represent pages/posts that have been updated but are still serving the pre-update cache entry.', 'w3-total-cache' ); ?>
</p>
<p>
	<?php esc_html_e( 'The "Postponed pages" represent pages/posts that failed to process either due to an error or due to the queue processor exceeding its 60 second time slot. These entries will be reprocessed on the next scheduled queue processor execution.', 'w3-total-cache' ); ?>
</p>
<p>
	<?php esc_html_e( 'To schedule the queue processor you can either utilize WordPress Cron or the System Cron handlers. Below is an example of a WordPress Cron job:', 'w3-total-cache' ); ?>
</p>
<code style="display: block;white-space: pre-wrap">if ( ! wp_next_scheduled( 'w3tc_alwayscached_worker_cron' ) ) {
    wp_schedule_event( time(), 'twicedaily', 'w3tc_alwayscached_worker_cron' );
}
add_action( 'w3tc_alwayscached_worker_cron', 'w3tc_alwayscached_worker_cron' );

function w3tc_alwayscached_worker_cron () {
    \W3TC\Util_Http::get( get_site_url() . '?w3tc_alwayscached' );
}
</code>
<form action="admin.php?page=w3tc_extensions&amp;extension=alwayscached&amp;action=view" method="post">
	<?php
	echo wp_kses(
		Util_Ui::nonce_field( 'w3tc' ),
		array(
			'input' => array(
				'type'  => array(),
				'name'  => array(),
				'value' => array(),
			),
		)
	);
	?>
	<div class="metabox-holder">
		<?php Util_Ui::postbox_header( esc_html__( 'Queue', 'w3-total-cache' ), '', 'credentials' ); ?>
		<table class="form-table">
			<tr>
				<th style="width: 300px;">
					<label>
						<?php esc_html_e( 'Pending pages in a queue:', 'w3-total-cache' ); ?>
					</label>
				</th>
				<td>
					<?php echo esc_html( $count_pending ); ?>
					<?php if ( $count_pending > 0 ) : ?>
						&nbsp;
						<a href="#" class="w3tc_alwayscached_queue" data-mode="pending">
							<?php esc_html_e( 'View', 'w3-total-cache' ); ?>
						</a>
						<section></section>
					<?php endif ?>
				</td>
			</tr>
			<tr>
				<th>
					<label>
						<?php esc_html_e( 'Postponed pages in a queue:', 'w3-total-cache' ); ?>
					</label>
				</th>
				<td>
					<?php echo esc_html( $count_postponed ); ?>
					<?php if ( $count_postponed > 0 ) : ?>
						&nbsp;
						<a href="#" class="w3tc_alwayscached_queue" data-mode="postponed">
							<?php esc_html_e( 'View', 'w3-total-cache' ); ?>
						</a>
						<section></section>
					<?php endif ?>
				</td>
			</tr>
			<tr>
				<th>
					<label>
						<?php esc_html_e( 'Last regeneration run:', 'w3-total-cache' ); ?>
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
					<input type="submit" name="w3tc_alwayscached_empty"
						value="<?php esc_html_e( 'Empty Queue', 'w3-total-cache' ); ?>" class="button" />
				</td>
			</tr>
		</table>
		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>
