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
	<?php
	esc_html_e( 'The Always Cached extension prevents page/post updates from clearing corresponding cache entries and instead adds them to a queue that can be manually cleared or scheduled to clear via cron.
	?>
</p>
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
