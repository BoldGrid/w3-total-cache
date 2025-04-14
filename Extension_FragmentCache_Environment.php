<?php
/**
 * File: Extension_FragmentCache_Environment.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_FragmentCache_Environment
 */
class Extension_FragmentCache_Environment {
	/**
	 * Fixes the fragment cache based on the provided event and configuration.
	 *
	 * @param object      $config     Configuration object for the fragment cache.
	 * @param string      $event      The event triggering this method.
	 * @param object|null $old_config Optional previous configuration object.
	 *
	 * @return void
	 */
	public static function fix_on_event( $config, $event, $old_config = null ) {
		if ( 'file' === $config->get_string( array( 'fragmentcache', 'engine' ) ) ) {
			if ( ! wp_next_scheduled( 'w3_fragmentcache_cleanup' ) ) {
				wp_schedule_event(
					time(),
					'w3_fragmentcache_cleanup',
					'w3_fragmentcache_cleanup'
				);
			}
		} else {
			self::unschedule();
		}
	}

	/**
	 * Deactivates the fragment cache extension.
	 *
	 * @return void
	 */
	public static function deactivate_extension() {
		$config = Dispatcher::config();
		$config->set_extension_active_frontend( 'fragmentcache', false );
		$config->save();

		self::unschedule();
	}

	/**
	 * Unschedules the fragment cache cleanup event if it is scheduled.
	 *
	 * @return void
	 */
	private static function unschedule() {
		if ( wp_next_scheduled( 'w3_fragmentcache_cleanup' ) ) {
			wp_clear_scheduled_hook( 'w3_fragmentcache_cleanup' );
		}
	}
}
