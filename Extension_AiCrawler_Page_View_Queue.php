<?php
/**
 * File: Extension_AiCrawler_Page_View_Queue.php
 *
 * Render the AI Crawler queue section container.
 *
 * @package W3TC
 * @since   X.X.X
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<div class="metabox-holder">
	<?php Util_Ui::postbox_header( esc_html__( 'Queue', 'w3-total-cache' ), '', 'queue' ); ?>
	<div id="w3tc-aicrawler-queue-content">
		<p><?php esc_html_e( 'Loading queue list...', 'w3-total-cache' ); ?></p>
	</div>
	<?php Util_Ui::postbox_footer(); ?>
</div>

