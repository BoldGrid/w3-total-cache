<?php
/**
 * File: Extension_AiCrawler_Page_View_Tools.php
 *
 * Render the AI Crawler settings page - Tool box.
 *
 * @package W3TC
 * @since   X.X.X
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$config = Dispatcher::config();
?>
<div class="metabox-holder">
	<?php Util_Ui::postbox_header( esc_html__( 'Tools', 'w3-total-cache' ), '', 'tools' ); ?>
	<table class="form-table">
		<tr>
			<th>
				<label for="aicrawler___regenerate_url"><?php esc_html_e( 'Regenerate URL:', 'w3-total-cache' ); ?></label>
			</th>
			<td>
				<input class="aicrawler___regenerate_url" type="text" size="60" placeholder="<?php esc_attr_e( 'Specify URL to regenerate', 'w3-total-cache' ); ?>"/>
				<button class="w3tc_aicrawler_regenerate_url button"><?php esc_html_e( 'Regenerate', 'w3-total-cache' ); ?></button>
			</td>
		</tr>
		<tr>
			<th>
				<label for="aicrawler___regenerate_all"><?php esc_html_e( 'Regenerate All:', 'w3-total-cache' ); ?></label>
			</th>
			<td>
				<button class="aicrawler___regenerate_all button"><?php esc_html_e( 'Regenerate', 'w3-total-cache' ); ?></button>
			</td>
		</tr>
	</table>
	<?php Util_Ui::postbox_footer(); ?>
</div>
