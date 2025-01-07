<?php
/**
 * File: ObjectCache_Page.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class ObjectCache_Page
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class ObjectCache_Page extends Base_Page_Settings {
	/**
	 * Current page
	 *
	 * @var string
	 */
	protected $_page = 'w3tc_objectcache';

	/**
	 * Displays the object cache options page.
	 *
	 * @return void
	 */
	public function view() {
		$objectcache_enabled = $this->_config->getf_boolean( 'objectcache.enabled' );

		include W3TC_INC_DIR . '/options/objectcache.php';
	}
}
