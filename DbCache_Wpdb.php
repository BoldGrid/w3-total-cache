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
	 * @return DbCache_WpdbLegacy|DbCache_WpdbNew The instance of the class.
	 */
	public static function instance() {
		static $instance = null;

		if ( is_null( $instance ) ) {
			$processors               = array();
			$call_default_constructor = true;

			// no caching during activation.
			$is_installing = ( defined( 'WP_INSTALLING' ) && WP_INSTALLING );

			$config = Dispatcher::config();
			if ( ! $is_installing && $config->get_boolean( 'dbcache.enabled' ) ) {
				$processors[] = new DbCache_WpdbInjection_QueryCaching();
			}
			if ( Util_Environment::is_dbcluster( $config ) ) {
				// dbcluster use mysqli only since other is obsolete now.
				if ( ! defined( 'WP_USE_EXT_MYSQL' ) ) {
					define( 'WP_USE_EXT_MYSQL', false );
				}

				$processors[] = new Enterprise_Dbcache_WpdbInjection_Cluster();
			}

			$processors[] = new DbCache_WpdbInjection();

			global $wp_version;
			if ( version_compare( $wp_version, '5.3' ) >= 0 ) {
				$o = new DbCache_WpdbNew( $processors );
			} else {
				$o = new DbCache_WpdbLegacy( $processors );
			}

			$next_injection = new _CallUnderlying( $o );

			foreach ( $processors as $processor ) {
				$processor->initialize_injection( $o, $next_injection );
			}

			// initialize after processors configured.
			$o->initialize();

			$instance = $o;
		}

		return $instance;
	}
}
