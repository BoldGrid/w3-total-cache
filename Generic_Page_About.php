<?php
/**
 * FIle: Generic_Page_About.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Generic_Page_About
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class Generic_Page_About extends Base_Page_Settings {
	/**
	 * Current page
	 *
	 * @var string
	 */
	protected $_page = 'w3tc_about';

	/**
	 * About tab
	 *
	 * @return void
	 */
	public function view() {
		include W3TC_INC_DIR . '/options/about.php';
	}
}
