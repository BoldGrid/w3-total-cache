<?php
/**
 * File: DbCache_Page.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class DbCache_Page
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
 */
class DbCache_Page extends Base_Page_Settings {
	/**
	 * Current page
	 *
	 * @var string
	 */
	protected $_page = 'w3tc_dbcache';

	/**
	 * Renders the database cache view page.
	 *
	 * @return void
	 */
	public function view() {
		$dbcache_enabled = $this->_config->get_boolean( 'dbcache.enabled' );

		include W3TC_INC_DIR . '/options/dbcache.php';
	}

	/**
	 * Configures the database cluster settings.
	 *
	 * @return void
	 */
	public function dbcluster_config() {
		$this->_page = 'w3tc_dbcluster_config';
		if ( Util_Environment::is_dbcluster( $this->_config ) ) {
			$content = @file_get_contents( W3TC_FILE_DB_CLUSTER_CONFIG );
		} else {
			$content = @file_get_contents( W3TC_DIR . '/ini/dbcluster-config-sample.php' );
		}

		include W3TC_INC_OPTIONS_DIR . '/enterprise/dbcluster-config.php';
	}
}
