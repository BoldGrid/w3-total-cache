<?php
/**
 * File: Extension_MaxCache_Plugin.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'W3TC' ) || die();

/**
 * Hands page-cache delivery to the CloudLinux MAx Cache web-server module.
 *
 * @since X.X.X
 */
class Extension_MaxCache_Plugin {

	/**
	 * Runs the extension.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function run() {
		// W3TC's own rules must stop claiming cache hits; this filter covers every path that writes them.
		\add_filter( 'w3tc_pgcache_rules_required', array( $this, 'w3tc_pgcache_rules_required' ), 10, 2 );

		// Late: Root_Environment wraps the whole do_action in one try/catch, so an earlier exception would skip later listeners.
		\add_action( 'w3tc_environment_fix_on_wpadmin_request', array( '\W3TC\Extension_MaxCache_Environment', 'fix_on_wpadmin_request' ), 100, 2 );
		\add_action( 'w3tc_environment_fix_on_event', array( '\W3TC\Extension_MaxCache_Environment', 'fix_on_event' ), 100, 2 );
		\add_action( 'w3tc_environment_fix_after_deactivation', array( '\W3TC\Extension_MaxCache_Environment', 'fix_after_deactivation' ), 100 );

		// Switched off, this file stops loading, so the block must go while the code still runs. Slug spelled out to avoid autoloading Core.
		\add_action( 'w3tc_deactivate_extension_maxcache', array( '\W3TC\Extension_MaxCache_Environment', 'deactivate_extension' ) );
	}

	/**
	 * Withholds W3TC's own page-cache rules while the module is the one serving.
	 *
	 * @since X.X.X
	 *
	 * @param bool   $required    Whether W3TC's rules are required.
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return bool
	 */
	public function w3tc_pgcache_rules_required( $required, $w3tc_config ) {
		// Only where rules get written: PgCache_ContentGrabber asks this on every front-end cache miss, and refresh() is expensive.
		if ( ( \is_admin() && ! \wp_doing_ajax() ) || \wp_doing_cron() || ( \defined( 'WP_CLI' ) && WP_CLI ) ) {
			// config_save() drives both passes with a Config instance no earlier refresh has seen; re-entrant by design.
			Extension_MaxCache_Core::refresh( $w3tc_config );
		}

		return $required && ! Extension_MaxCache_Core::is_enabled( $w3tc_config );
	}
}

$w3tc_p = new Extension_MaxCache_Plugin();
$w3tc_p->run();
