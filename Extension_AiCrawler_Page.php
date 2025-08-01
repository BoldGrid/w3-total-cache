<?php
/**
 * File: Extension_AiCrawler_Page.php
 *
 * @package W3TC
 * @since   x.x.x
 */

namespace W3TC;

/**
 * Class Extension_AiCrawler_Page
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 *
 * @since x.x.x
 */
class Extension_AiCrawler_Page extends Base_Page_Settings {
	/**
	 * Current page slug.
	 *
	 * @var   string
	 * @since x.x.x
	 */
	protected $_page = 'w3tc_aicrawler';

	/**
	 * Renders the AI Crawler extension settings page.
	 *
	 * @return void
	 * @since  x.x.x
	 */
	public function render_content() {
		require W3TC_DIR . '/Extension_AiCrawler_Page_View.php';
	}
}
