<?php
/**
 * File: DbCache_Plugin_Admin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class DbCache_Wpdb
 *
 * Database access mediator
 */
class DbCache_Wpdb extends DbCache_WpdbBase {
	/**
	 * Returns the singleton instance of the class.
	 *
	 * Ensures only one instance of the class is created and provides access to it.
	 *
	 * @return DbCache_WpdbNew The instance of the class.
	 */
	public static function instance() {
		static $instance = null;

		if ( is_null( $instance ) ) {
			$processors               = array();
			$call_default_constructor = true;

			// no caching during activation.
			$w3tc_is_installing = ( defined( 'WP_INSTALLING' ) && WP_INSTALLING );

			$w3tc_config = Dispatcher::config();
			if ( ! $w3tc_is_installing && $w3tc_config->get_boolean( 'dbcache.enabled' ) ) {
				$processors[] = new DbCache_WpdbInjection_QueryCaching();
			}
			if ( Util_Environment::is_dbcluster( $w3tc_config ) ) {
				// dbcluster use mysqli only since other is obsolete now.
				if ( ! defined( 'WP_USE_EXT_MYSQL' ) ) {
					define( 'WP_USE_EXT_MYSQL', false ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
				}

				$processors[] = new Enterprise_Dbcache_WpdbInjection_Cluster();
			}

			$processors[] = new DbCache_WpdbInjection();

			$w3tc_o = new DbCache_WpdbNew( $processors );

			$next_injection = new _CallUnderlying( $w3tc_o );

			foreach ( $processors as $processor ) {
				$processor->initialize_injection( $w3tc_o, $next_injection );
			}

			// initialize after processors configured.
			$w3tc_o->initialize();

			$instance = $w3tc_o;
		}

		return $instance;
	}
}
