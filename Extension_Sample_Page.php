<?php
/**
 * File: Extension_Sample_Page.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_Sample_Page
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class Extension_Sample_Page extends Base_Page_Settings {
	/**
	 * Current page slug.
	 *
	 * @var string
	 */
	protected $_page = 'w3tc_sample';

	/**
	 * Renders the Sample extension settings page.
	 *
	 * @return void
	 */
	public function render_content() {
		require W3TC_DIR . '/Extension_Sample_Page_View.php';
	}
}
