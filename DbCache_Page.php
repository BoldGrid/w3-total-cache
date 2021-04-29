<?php
namespace W3TC;



class DbCache_Page extends Base_Page_Settings {
	/**
	 * Current page
	 *
	 * @var string
	 */
	protected $_page = 'w3tc_dbcache';


	/**
	 * Database cache tab
	 *
	 * @return void
	 */
	function view() {
		$dbcache_enabled = $this->_config->get_boolean( 'dbcache.enabled' );

		include W3TC_INC_DIR . '/options/dbcache.php';
	}
}
