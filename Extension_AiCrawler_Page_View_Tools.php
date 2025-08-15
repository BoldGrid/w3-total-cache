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
				<label for="w3tc-aicrawler-regenerate-url"><?php esc_html_e( 'Regenerate URL:', 'w3-total-cache' ); ?></label>
			</th>
			<td>
								<input id="w3tc-aicrawler-regenerate-url" type="text" size="60" placeholder="<?php esc_attr_e( 'Specify URL to regenerate', 'w3-total-cache' ); ?>"/>
								<button id="w3tc-aicrawler-regenerate-url-button" class="button"><?php esc_html_e( 'Regenerate', 'w3-total-cache' ); ?></button>
								<p id="w3tc-aicrawler-regenerate-url-message" class="w3tc-aicrawler-message"></p>
			</td>
		</tr>
		<tr>
			<th>
				<label for="w3tc-aicrawler-regenerate-all-button"><?php esc_html_e( 'Regenerate All:', 'w3-total-cache' ); ?></label>
			</th>
			<td>
				<button id="w3tc-aicrawler-regenerate-all-button" class="button"><?php esc_html_e( 'Regenerate', 'w3-total-cache' ); ?></button>
			</td>
		</tr>
	</table>
	<?php Util_Ui::postbox_footer(); ?>
</div>
