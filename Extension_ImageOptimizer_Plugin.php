<?php
namespace W3TC;

/**
 * Extension_ImageOptimizer_Plugin
 *
 * @since X.X.X
 */
class Extension_ImageOptimizer_Plugin {
	/**
	 * Add hooks.
	 *
	 * @since X.X.X
	 * @static
	 */
	public static function wp_loaded() {
		add_action(
			'w3tc_extension_load_admin',
			array(
				'\W3TC\Extension_ImageOptimizer_Plugin_Admin',
				'w3tc_extension_load_admin',
			)
		);
	}
}

w3tc_add_action(
	'wp_loaded',
	array(
		'\W3TC\Extension_ImageOptimizer_Plugin',
		'wp_loaded',
	)
);
