<?php
/**
 * File: Extension_AiCrawler_Page_View_Status.php
 *
 * Render the AI Crawler settings page - Status box.
 *
 * @package W3TC
 * @since X.X.X
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

?>
<div class="metabox-holder">
	<?php Util_Ui::postbox_header( esc_html__( 'Status', 'w3-total-cache' ), '', 'status' ); ?>
	<?php
		Extension_AiCrawler_Util::render_report_summary();
	?>
	<?php Util_Ui::postbox_footer(); ?>
</div>
