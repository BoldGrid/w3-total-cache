<?php
/**
 * File: Extension_AiCrawler_Page.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_AiCrawler_Page
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class Extension_AiCrawler_Page extends Base_Page_Settings {
	/**
	 * Current page slug.
	 *
	 * @var string
	 */
	protected $_page = 'w3tc_aicrawler';

	/**
	 * Renders the AI Crawler extension settings page.
	 *
	 * @return void
	 */
	public function render_content() {
		require W3TC_DIR . '/Extension_AiCrawler_Page_View.php';
	}
}
