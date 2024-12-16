<?php
/**
 * File: Extension_FragmentCache_Page.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_FragmentCache_Page
 */
class Extension_FragmentCache_Page {
	/**
	 * Renders the content for the FragmentCache page.
	 *
	 * This method retrieves configuration settings, initializes core components,
	 * fetches registered fragment groups, and includes the corresponding view file.
	 *
	 * @return void
	 */
	public function render_content() {
		$config = Dispatcher::config();
		$core   = Dispatcher::component( 'Extension_FragmentCache_Core' );

		$registered_groups = $core->get_registered_fragment_groups();
		include W3TC_DIR . '/Extension_FragmentCache_Page_View.php';
	}
}
