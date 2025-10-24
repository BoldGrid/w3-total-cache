<?php
/**
 * File: PgCache_Flush.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class PgCache_Page
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class PgCache_Page extends Base_Page_Settings {
	/**
	 * Current page
	 *
	 * @var string
	 */
	protected $_page = 'w3tc_pgcache';

	/**
	 * Enqueues and registers scripts related to the W3 Total Cache PgCache settings page.
	 *
	 * @return void
	 */
	public static function admin_print_scripts_w3tc_pgcache() {
		wp_enqueue_script( 'w3tc-options-validator', plugins_url( 'pub/js/options-validator.js', W3TC_FILE ), array(), W3TC_VERSION, false );

		wp_register_script( 'w3tc-pgcache-qsexempts', plugins_url( 'PgCache_Page_View.js', W3TC_FILE ), array(), W3TC_VERSION, true );

		wp_localize_script(
			'w3tc-pgcache-qsexempts',
			'W3TCPgCacheQsExemptsData',
			array(
				'defaultQsExempts' => PgCache_QsExempts::get_qs_exempts(),
			)
		);

		wp_enqueue_script( 'w3tc-pgcache-qsexempts' );
	}

	/**
	 * Displays the view for the PgCache settings page.
	 *
	 * @return void
	 */
	public function view() {
		global $wp_rewrite;

		$feeds = $wp_rewrite->feeds;

		$feed_key = array_search( 'feed', $feeds, true );

		if ( false !== $feed_key ) {
			unset( $feeds[ $feed_key ] );
		}

		$default_feed        = get_default_feed();
		$pgcache_enabled     = $this->_config->get_boolean( 'pgcache.enabled' );
		$permalink_structure = get_option( 'permalink_structure' );

		$varnish_enabled = $this->_config->get_boolean( 'varnish.enabled' );
		$cdnfsd_enabled  = $this->_config->get_boolean( 'cdnfsd.enabled' );

		include W3TC_INC_DIR . '/options/pgcache.php';
	}
}
