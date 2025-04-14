<?php
/**
 * File: UserExperience_Page.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UserExperience_Page
 */
class UserExperience_Page {
	/**
	 * Renders the content of the User Experience page.
	 *
	 * This method retrieves configuration data using the Dispatcher class and includes
	 * the corresponding view file for rendering the User Experience page content.
	 *
	 * @return void
	 */
	public function render_content() {
		$c = Dispatcher::config();
		include W3TC_DIR . '/UserExperience_Page_View.php';
	}
}
