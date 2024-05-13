<?php
/**
 * File: Extension_AlwaysCached_Page_View.php
 *
 * Render the AlwaysCached settings page.
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

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
<code style="display: block;white-space: pre-wrap">
if ( ! wp_next_scheduled( 'w3tc_alwayscached_worker_cron' ) ) {
	wp_schedule_event( time(), 'twicedaily', 'w3tc_alwayscached_worker_cron' );
}
add_action( 'w3tc_alwayscached_worker_cron', 'w3tc_alwayscached_worker_cron' );

function w3tc_alwayscached_worker_cron () {
	\W3TC\Util_Http::get( get_site_url() . '?w3tc_alwayscached' );
}
</code>
<form action="admin.php?page=w3tc_extensions&amp;extension=alwayscached&amp;action=view" method="post">
	<?php
	Util_Ui::print_control_bar( 'extension_alwayscached_form_control' );

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

	require __DIR__ . '/Extension_AlwaysCached_Page_View_BoxQueue.php';
	require __DIR__ . '/Extension_AlwaysCached_Page_View_BoxFlushAll.php';

	?>
</form>
